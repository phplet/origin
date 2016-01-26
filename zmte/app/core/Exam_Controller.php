<?php
/**
 * 机考系统 控制器类 exam
 */
class Exam_Controller extends MY_Controller
{
    /**
     * controller->view 打包数据
     * @var array
     */
    protected $_data = array();

    function __construct()
    {
        parent::__construct();
        $this->redirect_to_new_window();
        defined('__HTML_URL__') || define('__HTML_URL__', base_url());
        $this->load->set_template(APPPATH.'views/exam');
        
        //引入考生日志类型
        require_once (APPPATH.'config/app/exam/log_type_constants.php');
        //设置机考系统 别名
        $this->set_exam_system_name();
        //控制浏览器版本
        //control_agent_version();
        //检查权限
        $segment_controller =$this->uri->rsegment(1);
        $segment_action = $this->uri->rsegment(2);

        if ($segment_controller == 'invigilate')
        {
            $this->check_invigilate_permission();
        }
        else
        {
            if (($segment_controller == 'test' && $segment_action == 'ping') ||($segment_controller == 'index' && $segment_action == 'end') )
            {
                return;
            }
            else
            {
                $this->check_permission();
            }
        }
    }

    /**
     * 将pc桌面转过来的地址 用新窗口打开
     */
    function redirect_to_new_window()
    {
        $from = trim($this->input->get('from'));
        if ($from == md5('desktop'))
        {
            echo '<script>';
            echo '
                    function fullscreen(path){
                             //先定义一个新窗口，窗口的左边距和上边距分别为0，窗口大小比用户屏幕大小小一点点
                             var features = "fullscreen=1,status=no,resizable=yes,top=0,left=0,scrollbars=no," +
                                      "titlebar=no,menubar=no,location=no,toolbar=no,z-look=yes," +
                                      "width="+(screen.availWidth-8)+",height="+(screen.availHeight-45);
                             var newWin = window.open(path, "_blank", features);
                             if(newWin != null){
                              window.opener = null;
                              //关闭父窗口
                              window.close();
                             }
                    }';
            $action = trim($this->input->get('act'));
            $redirect_url = $action == 'i' ? site_url('exam/invigilate/index/') : site_url('exam/index/index/');
            echo '	fullscreen("' . $redirect_url . '");';
            echo '</script>';
            die;
        }
    }

    //监考人员权限控制
    function check_invigilate_permission()
    {
        $this->load->model('exam/exam_invigilator_model');
        $exam_invigilator_model = $this->exam_invigilator_model;
        
        //检查监考人员是否已经登录
        $segment_action = $this->uri->rsegment(2);
        $except_actions = array('login', 'check_login', 'logout');
        if (!in_array($segment_action, $except_actions))
        {
            $exam_invigilator_model->check_invigilator_is_login();
        }
        
        
        //检查该监考人员是否在当前考场中
        $exams = $exam_invigilator_model->check_exist_current_place_invigilator();
        $this->_data['exams'] = $exams;
        if ($segment_action == 'check_login')
        {
            //设置监考人员session失效时间
            $exam_id = intval($this->input->post('exam_id'));
            $place_id = intval($this->input->post('place_id'));
            $start_time = null;
            $end_time = null;
            foreach($exams as $exam)
            {
                if ($exam_id == $exam['exam_id'])
                {
                    $places = $exam['place'];
                    foreach ($places as $place)
                    {
                        if ($place['place_id'] == $place_id)
                        {
                            $start_time = $place['c_start_time'];
                            $end_time = $place['c_end_time'];
                            break;
                        }
                    }
                    break;
                }
            }

            if (!is_null($start_time) && !is_null($end_time))
            {
                $this->session->sess_expiration = $end_time - $start_time;
            }
        }
    }

    function check_permission()
    {
        $domain = C('memcache_pre');
        $this->load->model('exam/exam_model');
        $this->load->model('exam/exam_place_model');
        $this->load->driver('cache');
        $cache_time = 3 * 24 * 60 * 60;
        //检查当前考试是否已经结束
        $this->exam_model->check_exam_status();
        //检查 当前时间段  是否存在 考试
        //获取考场信息
        $exam_config = C('exam_config', 'app/exam/website');
        
        //考试等待
        $time_start = $exam_config['time_period_limit']['wait']['start'];
        $time_end = $exam_config['time_period_limit']['submit'];
        $time_min = time() + $time_start*60;//考试起始时间点
        $time_max = time() - $time_end*60;  //考试终结时间点
        //选择考场缓冲时间(s)
        $wait_start_time_period = $exam_config['time_period_limit']['wait']['start']*60;
        $wait_end_time_period = $exam_config['time_period_limit']['wait']['end']*60;
        
        //交卷后缓冲时间(s)
        $submit_time_period = $time_end*60;
        $where = array(
            'ip'            => $this->input->ip_address(),
            'period_time'   => array($time_min, $time_max));
        
        $select_what = array(
                    'ep.exam_pid',
                    'place_id',
                    'place_name',
                    'start_time',
                    'end_time',
                    'address');
        $order_by = 'ep.start_time desc, ep.exam_pid desc ';
        $places = $this->exam_place_model->get_exam_place_list($where, $order_by, false, $select_what);
        
        if (!count($places))
        {
            $this->show_error();
        }
        
        //根据exam_pid归档
        $exam_places = array();
        $exam_subjects = array();
        foreach ($places as $place)
        {
            //补齐学科
            $subjects = array();
            //文件缓存
            $tmp_subject = $this->cache->file->get('tmp_subject_'.$place['place_id']);
            if (!$tmp_subject)
            {
                $tmp_subject = $this->exam_place_model->get_exam_place_subject($place['place_id'],$place['exam_pid']);
                $this->cache->file->save('tmp_subject_'.$place['place_id'], $tmp_subject, $cache_time);
            }
            //end
            if (is_array($tmp_subject) && count($tmp_subject))
            {
                foreach ($tmp_subject as $sub)
                {
                    //按考场ID归档
                    $place_id = $place['place_id'];
                    $sub['place_id'] = $place['place_id'];
                    $exam_subjects[$place['exam_pid']][$place_id][] = $sub;
                }
            }
            $exam_places[$place['exam_pid']][] = $place;
        }

        //获取期次信息
        $select_what = array(
                'exam_id',
                'exam_name',
                'introduce',
                'student_notice',
                'exam_type',
                'status',
                'cheat_number',
                'kickornot',
        );
        $exam_ids = array_keys($exam_places);
        $exam_data = array();
        foreach ($exam_ids as $id)
        {
            //文件缓存
            $exam =  $this->cache->file->get('exam_'.$id);
            if (!$exam)
            {
                $exam = $this->exam_model->get_exam_by_id($id, $select_what);
                $this->cache->file->save('exam_'.$id, $exam, $cache_time);
            }
            
            if (count($exam) && $exam['status'] == '1')
            {
                $exam['wait_start_time_period'] = $wait_start_time_period;
                $exam['wait_end_time_period'] = $wait_end_time_period;
                $exam['submit_time_period'] = $submit_time_period;
                $exam['subject'] = isset($exam_subjects[$id]) ? $exam_subjects[$id]:array();
                $exam['place'] = $exam_places[$id];
                $exam_data[] = $exam;
            }
        }
        
        if (!count($exam_data))
        {
            $this->show_error();
        }
        
        //将考试信息全局保存到整个考试中
        $this->_data['exams'] = $exam_data;
    
        //设置考生session失效时间
        $cookie_current_exam = $this->exam_model->get_cookie_current_exam();
        $segment_action = $this->uri->rsegment(2);
        if ($cookie_current_exam && $segment_action == 'check_login')
        {
            //40分钟（无论你怎么设置开始结束时间？）
            $this->session->sess_expiration = ($cookie_current_exam['end_time'] + $submit_time_period) - ($cookie_current_exam['start_time'] - $exam_config['time_period_limit']['login']*60);
        }
    }
    
    /**
     * 显示错误
     */
    function show_error()
    {
        //清除所有关联会话
        $this->load->model('exam/exam_model');
        $this->exam_model->destory_exam_session();
        $content = $this->load->view('error/404', $this->_data, TRUE);
        ob_end_clean();
        echo $content;
        die;
    }
        
    /**
     * 设置机考系统别名
     */
    function set_exam_system_name()
    {
        $this->load->model('exam/exam_model');
        $exam_config = C('exam_config', 'app/exam/website');
        $this->_data['system_name'] = isset($exam_config['system_name']) 
            ? $exam_config['system_name'] 
            : (C('webconfig')['site_name'] . '上机考试系统');
    }
}
