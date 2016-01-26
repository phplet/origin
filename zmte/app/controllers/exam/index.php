<?php
! defined ( 'BASEPATH' ) && exit ();

/**
 * 机考主页面
 *
 * @author TCG
 *         @create 2013-12-02
 */
class Index extends Exam_Controller
{

    public function __construct()
    {
        parent::__construct ();

        $segment = $this->uri->rsegment ( 2 );




        if ($this->session->userdata ( 'exam_uid' ) && $segment != 'check_login')
        {

        redirect ( 'exam/test/index/');
        }

        $this->load->model ( 'exam/exam_model' );
        // $this->output->enable_profiler(TRUE);
    }

    /**
     * 机考首页
     */
    public function index()
    {



        $data = $this->_data;
        $data ['page_title'] = '机考等待';
        $data ['cookie_current_exam'] = $this->exam_model->get_cookie_current_exam ();

        $this->load->view ( 'index/index', $data );
    }

    /**
     * 登录页面
     */
    public function login()
    {
        $data = $this->_data;
        $data ['page_title'] = '考生登录';

        $data ['current_exam'] = $this->exam_model->get_cookie_current_exam ( true );

        $data ['ref'] = $this->input->get ( 'ref' );

        $this->load->view ( 'index/login', $data );
    }

    /**
     * 学生登录检查
     */
    public function check_login()
    {
        // 获取当前
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );
        $exam_ticket = trim ( $this->input->post ( 'exam_ticket' ) );
        $password = $this->input->post ( 'password' );

        if (! strlen ( $exam_ticket ))
        {
            output_json ( CODE_ERROR, '请输入登陆帐号.' );
        }

        if (!is_email($exam_ticket)
            && !is_idcard($exam_ticket)
            && !is_numeric($exam_ticket))
        {
            output_json ( CODE_ERROR, '请输入合法的登陆帐号.' );
        }

        if (! strlen ( $password ))
        {
            output_json ( CODE_ERROR, '登陆密码不能为空.' );
        }

        // 检查帐号密码是否正确
        $this->load->model ( 'exam/student_model' );
        $student = $this->student_model->is_valid_student ( $exam_ticket, $password );
        if (!$student)
        {
            output_json ( CODE_ERROR, '登陆帐号或密码不正确，请检查.' );
        }

        // 检查学生是否在当前考场中
        $this->load->model ( 'exam/exam_place_model' );
        $exam_place_model = $this->exam_place_model;
        $place_id = $current_exam ['place_id'];
        $user_id = $student ['uid'];
        if (! $exam_place_model->check_exam_place_student ( $place_id, $user_id ))
        {
            output_json ( CODE_ERROR, '很抱歉，您不在本场考试中，有问题请联系监考老师.' );
        }

        // 设置考生考卷信息
        $place_id = $current_exam ['place_id'];
        $uid = $student ['uid'];

        $this->load->model ( 'exam/exam_test_paper_model' );
        $this->load->model ( 'exam/exam_place_model' );

        $test_paper_model = $this->exam_test_paper_model;

        // 设定考生考卷
        /**
         * 需要事先判断 本场考试 是否已经分配考生试卷
         */
        $test_papers = $test_paper_model->get_stduent_test_paper ( $place_id, $uid, 'etp_flag,etp_id', null );

         $place_subjects = $this->exam_place_model->get_exam_place_subject($place_id);


        if (count ( $test_papers )<>count($place_subjects))
        {
            $insert_ids = $test_paper_model->set_student_test_paper ( $place_id, $uid );


            // 设置考试记录
            if ($insert_ids === false)
            {
                message ( '抱歉，该场考试有科目未分配试卷.', 'exam/index/login' );
            }

            if (count ( $insert_ids ))
            {
                $this->session->set_userdata ( array ('etp_id' => implode ( ',', $insert_ids )
                ) );
            }
        }
        else
        {
            $insert_ids1 =array();
            foreach ( $test_papers as $item )
            {
                $etp_flag = $item ['etp_flag'];
                if ($etp_flag < 0)
                {
                    message ( '抱歉，您在该场考试中有作弊行为，本次考试无效.', 'exam/index/login' );
                }
                elseif ($etp_flag > 0)
                {
                    message ( '抱歉，您已经交卷了.', 'exam/index/login' );
                }
                $insert_ids1[]=$item['etp_id'];

            }

            $this->session->set_userdata ( array ('etp_id' => implode ( ',', $insert_ids1 )));
        }

        // 添加考场在考人员统计
        // 检查考生是否已经登录过
        $this->load->model ( 'exam/student_log_stat_model' );
        try
        {
            $this->student_log_stat_model->set_exam_place_member ( $current_exam ['exam_id'], $current_exam ['place_id'], $user_id );
        }
        catch ( Exception $e )
        {
            output_json ( CODE_ERROR, $e->getMessage () );
        }

        // ==================登录成功操作========================
        // 考生登录成功，将考生信息保存在session
        $student ['exam_uid'] = $student ['uid'];

        // 补齐当前考生的 学校 & 年级信息
        $this->load->model ( 'exam/school_model' );
        $school = $this->school_model->get_school_by_id ( $student ['school_id'] );
        $student ['school_name'] = count ( $school ) ? $school ['school_name'] : '--';

        // 获取年级信息
        $grade_id = $student ['grade_id'];
        $grades = C ( 'grades' );
        $student ['grade_name'] = isset ( $grades [$grade_id] ) ? $grades [$grade_id] : '--';

        // 设置考生的会话
        $this->student_model->set_exam_student_session ( $student );

        // 判断该考生是否有离开考试界面嫌疑
        $this->load->model ( 'exam/student_log_stat_model', 'log_stat_model' );

        // 如果考试未开始,将考生的活跃时间清零, 如果考生已经在某个当前考场中，移除
        if (strtotime ( $current_exam ['start_time'] ) >= time ())
        {
            $this->log_stat_model->remove_student_last_active_time ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );
            $this->log_stat_model->remove_exam_place_member ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );
        }

        if ($this->log_stat_model->has_beyond_active_time ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid ))
        {
            // 机考日志
            exam_log ( EXAM_LOG_RELOGIN_AFTER_LEAVE_TEST_PAGE );
            $this->log_stat_model->set_exam_place_student_active_status ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );
        }
        else
        {
            // 机考日志
            exam_log ( EXAM_LOG_LOGIN, array ('ip' => $this->input->ip_address ()
            ) );
        }

        output_json ( CODE_SUCCESS );
    }

    /**
     * 登录页面
     */
    public function end()
    {
         $data = $this->_data;
        $data ['current_exam'] = $this->exam_model->get_cookie_current_exam ( true );

        $this->load->view ( 'index/end', $data );
    }


    /**
     * 登录页面
     */
    public function exam_url()
    {
        // $data = $this->_data;
        $hidden_place = $this->input->post ( 'hidden_place' );
        $this->session->set_userdata('zeming_exam_test',$hidden_place);
     //   $data ['current_exam'] = $this->exam_model->get_cookie_current_exam ( true );

      //  $this->load->view ( 'index/end', $data );
    }
}

