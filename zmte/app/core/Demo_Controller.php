<?php
/**
 * 机考 演示 控制器类 exam demo
 */
class Demo_Controller extends MY_Controller
{
    /**
     * controller->view 打包数据
     * @var array
     */
    protected $_data = array();
    private   $_grade;

    function __construct()
    {
        parent::__construct();
        defined('__HTML_URL__') || define('__HTML_URL__', base_url());
        defined('__GLOBAL_HTML_URL__') || define('__GLOBAL_HTML_URL__', C('global_source_host'));
        $this->load->set_template(APPPATH.'views/demo');

        //引入考生日志类型
        require_once (APPPATH.'config/app/demo/log_type_constants.php');
        
        //设置机考系统 别名
        $this->set_exam_system_name();
        
        //检查权限
        $segment_controller = $this->uri->rsegment(1);
        $segment_action = $this->uri->rsegment(2);
        if ($segment_controller == 'test' && $segment_action == 'ping')
        {
            return;
        }
        else
        {
            $this->check_permission();
        }
    }

    private function _set_grade()
    {
        if (isset($_GET['grade']))
        {
            $this->_grade = intval($this->input->get('grade'));
            $this->session->set_userdata(array('grade' => $this->_grade));
        }
        else
        {
            $this->_grade = (int) $this->session->userdata('grade');
        }
    }

    function check_permission()
    {
        $this->load->model('demo/exam_model');
        $exam_config = C('demo_exam_config/' . $this->_grade, 'app/demo/website');
        if (empty($exam_config))
        {
            $exam_config = current(C('demo_exam_config', 'app/demo/website'));
        }
        
        //检查是否存在学科
        $subject_id = intval($this->input->get('subject_id'));
        $session_subject_id = intval($this->session->userdata('subject_id'));
        $session_grade_id = intval($this->session->userdata('grade_id'));
        $grade_id = intval($this->input->get('grade'));
        
        if (($subject_id && $session_subject_id && $subject_id != $session_subject_id)
                || ($session_grade_id && $grade_id && $grade_id != $session_grade_id))
        {
            $this->exam_model->destory_exam_session();
            $this->session->unset_userdata(array('report_mark' => ''));
        }
        
        $this->_set_grade();
        $current_exam = $this->exam_model->get_session_current_exam();
        if ((!isset($current_exam['exam_pid']) && (!$subject_id || !isset($exam_config['subject_id'][$subject_id]) ) )
                 || (isset($current_exam['exam_pid']) && $subject_id && !isset($exam_config['subject_id'][$subject_id])))
        {
            $this->show_error();
        }
        $subject_id = !$subject_id ? intval($this->session->userdata('subject_id')) : $subject_id;
        $subject_id && $exam = $this->exam_model->get_exam_by_subject_id($exam_config['exam_pid'], $subject_id, 'exam_id,exam_name');
        $exam_id = isset($exam['exam_id']) ? $exam['exam_id'] : 0;
        if ((!isset($current_exam['exam_pid'])) || ($exam_id > 0 && $current_exam['exam_id'] != $exam_id))
        {
            $session_data = array(
                        'exam_pid'      => $exam_config['exam_pid'],
                        'place_id'      => $exam_config['place_id'],
                        'exam_name'     => $exam_config['exam_name'],
                        'introduce'     => $exam_config['introduce'],
                        'student_notice'=> $exam_config['student_notice'],
                        'exam_type'     => $exam_config['exam_type'],
                        'subject_name'  => $exam_config['subject_id'][$subject_id],
                        'subject_id'    => $subject_id,
                        'exam_id'       => $exam_id,
            );
            $this->session->set_userdata($session_data);
        }
        $this->exam_model->check_exam_status($subject_id);
        $this->session->set_userdata(array('grade_id' => $exam_config['grade_id']));
        
        //设置考生session失效时间
        $current_exam = $this->exam_model->get_session_current_exam();
        $segment_action = $this->uri->rsegment(2);
        if ($current_exam && $segment_action == 'check_login')
        {
            $this->session->sess_expiration = 10800;//3小时
        }
    }
    
    /**
     * 显示错误
     */
    function show_error()
    {
        //清除所有关联会话
        $this->load->model('demo/exam_model');
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
        if (isset($_GET['grade']))
        {
            $this->_grade = intval($this->input->get('grade'));
        }
        else
        {
            $this->_grade = (int) $this->session->userdata('grade');
        }

        $this->load->model('demo/exam_model');
        $exam_config = C('demo_exam_config/' . $this->_grade, 'app/demo/website');

        if (empty($exam_config))
        {
            $exam_config = current(C('demo_exam_config', 'app/demo/website'));
        }
        $sb = $this->exam_model->get_session_current_exam();
        $this->_data['subject_name'] = $sb['subject_name'];
        $this->_data['system_name'] = isset($exam_config['system_name'])
                ? $exam_config['system_name'] : (C('webconfig')['site_name'] . '上机考试系统');
    }
}
