<?php
/**
 * 后端控制器类 admin
 */
class A_Controller extends MY_Controller
{
    function __construct()
    {
        parent::__construct();
        $segment_app =$this->uri->segment(1);
        $segment_controller =$this->uri->segment(2);
        $segment_action =$this->uri->segment(3);
        $haystack = array();
        $haystack = array('admin/index/index',
            'admin/index/main',
            'admin/index/menu',
            'admin/index/top',
            'admin/index/logout');
        
        // 后台登录验证处理
        defined('_ADMIN_UPLOAD_ROOT_PATH_') || define('_ADMIN_UPLOAD_ROOT_PATH_', ROOTPATH . C('admin_upload_root_path'));
        defined('__HTML_URL__') || define('__HTML_URL__', base_url());
        $this->load->set_template(APPPATH.'views/admin');

        if (!in_array($segment_action, array('login', 'check_login', 'resetpwd')))
        {
            /**
             * 检查登陆
             */
            $session = $this->session->userdata;

            // 如果是修改自己的密码,总是允许
            if ($segment_action == 'editpwd')
            {
                if (!$session['admin_id'])
                {

                    redirect('admin/index/login');
                }
                return;
            }

            $aca = $segment_app . '/' . $segment_controller . '/' .
                             $segment_action;
            if (!empty($segment_app) && !empty($segment_controller)
                    && !empty($segment_action))
            {
                if (!in_array($aca, $haystack))
                {
                    if ($segment_controller != 'common')
                    {
                        //期次管理
                        if (in_array($segment_controller,
                                        array(
                                                'subject_paper',
                                                //'paper',
                                                'place_student',
                                                'exam_place',
                                                'exam_student_result',
                                                'kyxm',
                                                'place_subject',
                                                'place_invigilator',
                                                'exam_paper',
                                                //'exam_question'
                                        )))
                        {
                            if (!$this->check_power_new('exam_index'))
                            {
                                return;
                            }
                        }

                        else if (in_array($segment_controller.'_'.$segment_action, array('question_update_demo_question')))
                        {
                            if (!$this->check_power_new('exam_index'))
                            {
                                return;
                            }
                        }
                        //更新=添加编辑
                        else if (in_array($segment_controller . '_' . $segment_action,
                                        array(
                                                'production_update',
                                                'production_category_update',
                                                'invigilate_save',
                                                'teacher_download_save',
                                                'question_update',
                                                'subject_category_save',
                                                'knowledge_update',
                                                'group_type_update',
                                                'exam_update',
                                                'exam_rule_update',
                                                'comparison_type_save',
                                                'comparison_info_save',
                                                'evaluate_rule_save',
                                                'evaluate_module_save',
                                                'demo_config_update',
                                                'interview_question_update',
                                                'interview_rule_update',
                                                'student_update'
                                        )))
                        {
                            if (!$this->check_power_new(
                                            $segment_controller . '_edit',
                                            $segment_controller . '_add'))
                            {
                                return;
                            }
                        }
                        elseif (in_array($segment_controller . '_' . $segment_action,
                                        array(
                                                'paper_diy_update_paper'
                                        )))
                        {
                            if (!$this->check_power_new(
                                            $segment_controller . '_add_paper',
                                            $segment_controller . '_edit_paper'))
                            {
                                return;
                            }
                        }
                        //更新=添加编辑
                        elseif (in_array($segment_controller . '_' . $segment_action,
                                        array(
                                                'evaluate_template_set_evaluate_template'
                                        )))
                        {
                            if (!$this->check_power_new(
                                            $segment_controller . '_add',
                                            $segment_controller . '_edit'))
                            {
                                return;
                            }
                        }
                        //更新=添加编辑
                        elseif (in_array($segment_controller . '_' . $segment_action,
                                        array(
                                                'evaluate_module_save_module'
                                        )))
                        {
                            if (!$this->check_power_new(
                                            $segment_controller . '_add_module',
                                            $segment_controller . '_edit_module'))
                            {
                                return;
                            }
                        }
                        else
                        {
                            if (! $this->check_power_new(
                                            $segment_controller . '_' .
                                            $segment_action))
                            {
                                return;
                            }
                        }
                    }
                }
            }

            if (!$session['admin_id'])
            {

                redirect('admin/index/login');
            }
        }
    }

    /**
     * 检查是否具有权限
     * $power_name : 权限名称
     * $return     : 返回值处理
     */
    function check_power($action_list = '', $output = TRUE, $is_ajax = FALSE)
    {
        return true;
    }

    /**
     * 检查是否具有权限
     * $power_name : 权限名称
     * $return     : 返回值处理
     */
    function check_power_new($action_list = '', $output = TRUE, $is_ajax = FALSE)
    {
        if (empty($action_list)) return TRUE;
        $session = $this->session->all_userdata();

        $priv = FALSE;
        if($session['is_super'])
        {
            $priv = TRUE;
        }
        else
        {
            if ( !is_array($action_list))
            {
                $action_list = explode(',', $action_list);
            }
            $action_lists = array();
            if (isset($session['action']) && is_array($session['action']))
            {
                foreach ($session['action'] as $power)
                {
                    $action_lists[] = explode(',',$power['action_list']);
                    //if (in_array($action,explode(',',$power['action_list'])))
                    //{
                    //  $priv = TRUE;
                    //  break;
                    //}
                }
            }

            foreach ($action_list as $action)
            {
                foreach ($action_lists as $power)
                {

                    //$action_lists[] = $power['action_list'];
                    if (in_array($action,$power))
                    {

                        $priv = TRUE;
                        break;
                    }
                }
            }

        }

        if ($output && $priv == FALSE)
        {
            if ($is_ajax == TRUE)
            {
                echo '您没有权限！';
            }
            else
            {
                message('您没有权限！');
            }
        }

        return $priv;
    }

    //判断当前管理员是否为 超级管理员
    function is_super_user()
    {
        return $this->session->userdata('is_super');
    }

    //判断当前管理员所在学科是否为所有学科
    function is_all_subject_user()
    {
        $action = $this->session->userdata('action');
        $is_all_subject = false;
        foreach ($action as $power)
        {
            if ($power['subject_id'] == -1)
            {
                $is_all_subject = true;
                break;
            }
        }
        return $is_all_subject;
    }

    //判断当前管理员是否管理所有年级
    function is_all_grade_user()
    {
        $action = $this->session->userdata('action');
        $is_all_grade = false;
        foreach ($action as $power)
        {
            if ($power['grade_id'] == -1)
            {
                $is_all_grade = true;
                break;
            }
        }
        return $is_all_grade;
    }

    //判断当前管理员是否管理所有题型
    function is_all_q_type_user()
    {
        $action = $this->session->userdata('action');
        $is_all_q_type = false;
        foreach ($action as $power)
        {
            if ($power['q_type_id'] == -1)
            {
                $is_all_q_type = true;
                break;
            }
        }
        return $is_all_q_type;
    }

    //判断当前管理员是否为指定学科
    function is_subject_user($subject_id)
    {
        if ($this->is_super_user())
        {
            return true;
        }

        if ($this->is_all_subject_user())
        {
            return true;
        }

        $is_subject = false;
        $action = $this->session->userdata('action');
        foreach ($action as $power)
        {
            if (in_array($subject_id, explode(',', $power['subject_id'])))
            {
                $is_subject = true;
                break;
            }
        }
        return $is_subject;
    }

    //判断当前管理员是否为指定题目的审核权限
    public function has_question_check ( $question )
    {
        $checked = false;
        if($this ->is_super_user())
        {
            $checked = true;
            return $checked;
        }
        if (!$this->is_super_user() && $this ->is_all_q_type_user()
            && $this ->is_all_grade_user() && $this ->is_all_subject_user())
        {
            $checked = true;
            return $checked;
        }
        //所属类型
        $class_id = explode(',' , $question['class_id']);
        $class_id = array_values(array_filter($class_id));
        $q_type_id=$this->session->userdata('q_type_id');
        $q_type_id=explode(',',$q_type_id);
        $q_type_id = array_values(array_filter($q_type_id));
        $check3 = false;
        if ($this->is_all_q_type_user())
        {
            $check3 = true;
        }
        else
        {
           foreach ($q_type_id as $val)
           {
               if (in_array($val, $class_id))
               {
                   $check3=true;
                   break;
               }
           }
        }
        
        //所属学科
        $subject_id=$this->session->userdata('subject_id');
        $subject_id=explode(',',$subject_id);
        $subject_id = array_values(array_filter($subject_id));
        $check1=false;

        if($this->is_all_subject_user()||in_array($question['subject_id'], $subject_id))
        {
            $check1=true;
        }

        //所属年级
        $grade_id = $this->session->userdata('grade_id');
        $grade_id = explode(',',$grade_id);
        $grade_id = array_values(array_filter($grade_id));
        $check2 = false;
        if ($this->is_all_grade_user())
        {
            $check2 = true;
        }
        else
        {
            foreach ($grade_id as $val)
            {
                if ($val >= $question['start_grade'] && $val <= $question['end_grade'])
                {
                    $check2 = true;
                    break;
                }
            }
        }
        $checked = $check1 && $check2 && $check3;
        return $checked;
    }
}
