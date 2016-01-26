<?php
! defined ( 'BASEPATH' ) && exit ();

/**
 * 考生考试
 * 界面
 *
 * @author
 *         TCG
 *
 *         @create
 *         2015-08-02
 */
class Test extends Exam_Controller
{

    public function __construct()
    {
        parent::__construct ();
        // 后台登录验证处理
        $segment = $this->uri->rsegment ( 2 );
        if ($segment !== 'login' && $segment !== 'check_login')
        {
            /**
             * 检查登陆
             */
            $exam_session = $this->session->userdata ( 'exam_uid' );

            if (! $exam_session)
            {
                redirect ( 'exam/index/login' );
            }
        }
        // $this->output->enable_profiler(TRUE);
    }

    /**
     * 考生
     */
    public function index()
    {
        $data = $this->_data;
        $data ['page_title'] = '考试界面';
        $this->load->driver ( 'cache' );
        $cache_time = 3 * 24 * 60 * 60;
        // 考生信息
        $userdata = $this->session->userdata;

        $student_info = array ('picture' => __IMG_ROOT_URL__ . $userdata ['picture'],'truename' => $userdata ['last_name'] . $userdata ['first_name'],        // 姓名
        'exam_ticket' => $userdata ['exam_ticket'],        // 准考证号
        'school_name' => $userdata ['school_name'],'grade_name' => $userdata ['grade_name'] );

        // 考试期数
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );

        // 获取试卷试题总数
        $this->load->model ( 'exam/exam_test_paper_model' );
        $this->load->model ( 'exam/exam_paper_model' );

        $test_paper_model = $this->exam_test_paper_model;
        $paper_model = $this->exam_paper_model;

        $paper_items = array ('paper_id','exam_id','paper_name','ques_num','qtype_ques_num' );
        $paper_info = array ();
        $paper_ques_infos = array ();

        // 缓存

        // $test_papers
        // =
        // $test_paper_model->get_stduent_test_paper($current_exam['place_id'],
        // $userdata['uid']);

        $test_papers = $this->cache->file->get ( 'get_stduent_test_paper_' . $current_exam ['place_id'] . '_' . $userdata ['uid'] );
        if (! $test_papers)
        {
            $test_papers = $test_paper_model->get_stduent_test_paper ( $current_exam ['place_id'], $userdata ['uid'] );
            $this->cache->file->save ( 'get_stduent_test_paper_' . $current_exam ['place_id'] . '_' . $userdata ['uid'], $test_papers, $cache_time );
        }
        // end

        $etp_ids = array ();

        foreach ( $test_papers as $paper )
        {
            $paper_id = $paper ['paper_id'];

            // 缓存

            // $tmp_paper
            // =
            // $paper_model->get_paper_by_id($paper_id,
            // $paper_items);

            $tmp_paper = $this->cache->file->get ( 'tmp_paper_' . $paper_id . '_' . md5 ( json_encode ( $paper_items ) ) );

            if (! $tmp_paper)
            {
                $tmp_paper = $paper_model->get_paper_by_id ( $paper_id, $paper_items );
                $this->cache->file->save ( 'tmp_paper_' . $paper_id . '_' . md5 ( json_encode ( $paper_items ) ), $tmp_paper, $cache_time );
            }

            // end

            if (is_array ( $tmp_paper ) && count ( $tmp_paper ))
            {
                // 附加试卷总分属性
                $tmp_paper ['full_score'] = $this->exam_model->get_exam_by_id ( $tmp_paper ['exam_id'], 'total_score' );
                ;

                $tmp_paper ['subject_id'] = $paper ['subject_id'];
                $tmp_paper ['etp_id'] = $paper ['etp_id'];

                if (! in_array ( $paper ['etp_id'], $etp_ids ))
                {
                    array_push ( $etp_ids, $paper ['etp_id'] );
                }
                // 获取试卷
                // 试题信息
                $paper_questions = $paper_model->get_paper_question_detail_p ( $paper_id );

                // print_r($paper_questions);
                $tmp_paper ['question_list'] = array_values ( $this->_filter_paper_question_detail ( $paper_questions ) );

                $paper_info [$tmp_paper ['subject_id']] = $tmp_paper;

                // 提取考试试题
                $ques_ids = array ();
                $tmp_ques_list = $tmp_paper ['question_list'];
                foreach ( $tmp_ques_list as $row )
                {
                    $tmp_ques_ids = array_keys ( $row ['list'] );
                    foreach ( $tmp_ques_ids as $t_id )
                    {
                        @list ( $q, $ques_id ) = @explode ( '_', $t_id );
                        if (is_null ( $ques_id ))
                            continue;

                        $ques_ids [] = $ques_id;
                    }
                }

                $paper_ques_infos [$tmp_paper ['etp_id']] = array ('etp_id' => $tmp_paper ['etp_id'],'ques_id' => implode ( ',', $ques_ids ) );
            }
        }

        // 批量插入考试试卷试题
        /*
         *
         * $insert_data
         * =
         * array();
         * $update_data
         * =
         * array();
         */
        $etp_ids = array_keys ( $paper_ques_infos );
        /*
         *
         * $etp_id_str
         * =
         * implode(',',
         * $etp_ids);
         * $etp_id_where
         * =
         * count($etp_ids)
         * ==
         * '1'
         * ?
         * "etp_id=$etp_ids[0]"
         * :
         * "etp_id
         * in
         * ("
         * .
         * ($etp_id_str
         * ?
         * $etp_id_str
         * :
         * 0)
         * .
         * ")";
         * $old_paper_questions
         * =
         * $this->db->query("select
         * id,
         * etp_id
         * from
         * {pre}exam_test_paper_question
         * where
         * $etp_id_where")->result_array();
         * $paper_question_ids
         * =
         * array();
         * foreach
         * ($old_paper_questions
         * as
         * $row)
         * {
         * $paper_question_ids[$row['etp_id']]
         * =
         * $row['id'];
         * }
         * foreach
         * ($paper_ques_infos
         * as
         * $etp_id=>$val)
         * {
         * if
         * (isset($paper_question_ids[$etp_id]))
         * {
         * $val['id']
         * =
         * $paper_question_ids[$etp_id];
         * $update_data[]
         * =
         * $val;
         * }
         * else
         * {
         * $insert_data[]
         * =
         * $val;
         * }
         * }
         * if
         * (count($insert_data))
         * {
         * $this->db->insert_batch('exam_test_paper_question',
         * $insert_data);
         * }
         * if
         * (count($update_data))
         * {
         * $this->db->update_batch('exam_test_paper_question',
         * $update_data,
         * 'id');
         * }
         */
        // 检查是否要考的科目都已经备选了试卷
        $subjects = explode ( ',', $current_exam ['subject_id'] );
        $subjects = array_filter ( $subjects );

        $paper_subjects = array_keys ( $paper_info );
        $subjects = array_filter ( $paper_subjects );

        $subjects = array_diff ( $subjects, $paper_subjects );
        $subjects = array_filter ( $subjects );

        if (count ( $subjects ))
        {
            $subject_names = array ();
            foreach ( $subjects as $subject )
            {
                $subject_names [] = C ( "subject/{$subject}" );
            }
            message ( '您所考的科目有以下科目未分配试卷：' . implode ( ', ', $subject_names ) );
        }

        // 将考生的考试记录保存到session中
        $this->exam_model->set_test_paper_sessoin ( $etp_ids );

        $data ['student_info'] = $student_info;
        $data ['current_exam'] = $current_exam;
        $data ['paper_info'] = $paper_info;

        // 获取考生离开界面的次数记录
        $data ['count_window_blur'] = $this->_get_window_blur ();

        $this->load->view ( 'test/index', $data );
    }

    /**
     * 将试卷试题的分类重组
     */
    protected function _filter_paper_question_detail($paper_questions)
    {
        // 格式化$groups
        // key
        // 内容
        $tmp_groups = array ();
        $count_groups = count ( $paper_questions );
        foreach ( $paper_questions as $key => $item )
        {
            $item ['type'] = $key;
            // if
            // ($key
            // ==
            // 0)
            // {
            // $key
            // =
            // $count_groups;
            // }
            foreach ( $item ['list'] as $k => $v )
            {
                $item ['list'] ["q_{$k}"] = $v;
                unset ( $item ['list'] [$k] );
            }
            $tmp_groups [$key] = $item;
            unset ( $paper_questions [$key] );
        }

        return $tmp_groups;
    }

    /**
     * 问题详情
     */
    public function question_detail()
    {
        $data = $this->_data;

        $etp_id = intval ( $this->input->post ( 'etp_id' ) );
        $data ['is_single'] = intval ( $this->input->post ( 'is_single' ) ); // 是否为单条模式(0：不是，
                                                                             // 1：是)
        $data ['is_first'] = intval ( $this->input->post ( 'is_first' ) ); // 是否请求第一条记录(0：不是，
                                                                           // 1：是)

        // 参数检查
        if (! $etp_id)
        {
            output_json ( CODE_ERROR, '参数非法' );
        }

        // 试题ID，多题时用,隔开
        $question_id = trim ( $this->input->post ( 'question_id' ) );
        $question_ids = @explode ( ',', $question_id );
        if (! is_array ( $question_ids ) || ! count ( $question_ids ))
        {
            output_json ( CODE_ERROR, '该试题不存在' );
        }

        $this->load->model ( 'exam/exam_test_result_model' );
        $test_result_model = $this->exam_test_result_model;
        $questions = array ();
        foreach ( $question_ids as $question_id )
        {

            $question = $test_result_model->get_test_question ( $etp_id, $question_id );
            if (is_array ( $question ) && count ( $question ))
            {
                $questions [] = $question;
            }
        }

        // 将问题进行按题型归档
        /*
         *
         * array(
         * '1'
         * =>
         * '单选',
         * '2'
         * =>
         * '不定项',
         * '3'
         * =>
         * '填空',
         * '0'
         * =>
         * '题组'
         * )
         */
        $tmp_questions = array ();
        foreach ( $questions as $question )
        {
            $tmp_questions [$question ['type']] [] = $question;
        }
        unset ( $questions );

        arsort ( $tmp_questions );

        // 将题组放最后
        if (isset ( $tmp_questions [0] ))
        {
            $first_question = $tmp_questions [0];
            unset ( $tmp_questions [0] );
            $tmp_questions [0] = $first_question;
        }
        // print_r($tmp_questions);
        // die;

        $this->load->model ( 'exam/exam_test_paper_model' );
        $test_paper1 = $this->exam_test_paper_model->get_test_paper_by_id ( $etp_id, 'subject_id' );

        $subject_session = array ('subject_id' => $test_paper1 );

        $this->session->set_userdata ( $subject_session );

        $data ['questions'] = $tmp_questions;
        $data ['qtypes'] = C ( 'qtype' );
        $data ['etp_id'] = $etp_id;
        //

        output_json ( CODE_SUCCESS, 'success', $this->load->view ( 'test/question_detail', $data, true ) );
    }

    /**
     * 考生提交考试
     */
    public function submit()
    {
        // 获取提交问题
        // json格式
        /*
         *
         * post_data格式：
         * array(
         * 'uid'
         * =>
         * '考生ID',
         * 'place_id'
         * =>
         * '考试ID',
         * 'paper_data'=>
         * array(
         * array(
         * 'paper_id'
         * =>
         * '试卷ID',
         * 'etp_id'
         * =>
         * '当前考试记录ID',
         * 'question'
         * =>
         * array(
         * array(
         * 'etr_id'
         * =>
         * '问题结果ID',
         * 'answer'
         * =>
         * '答案',
         * )
         * )
         * ),
         * ...
         * )
         * )
         */
        $post_data = $this->input->post ( 'post_data' );
        $post_data = ( array ) @json_decode ( $post_data );

        if (! is_array ( $post_data ) || ! count ( $post_data ))
        {
            output_json ( CODE_ERROR, '参数非法' );
        }

        // 检查学生的合法性
        $exam_uid = $this->session->userdata ( 'exam_uid' );
        $post_uid = isset ( $post_data ['uid'] ) ? intval ( $post_data ['uid'] ) : 0;
        if (! $exam_uid || ! $post_uid || $exam_uid != $post_uid)
        {
            output_json ( CODE_ERROR, '您不是合法的考生' );
        }

        // 检查
        // 当前考试是否合法
        $post_place_id = isset ( $post_data ['place_id'] ) ? intval ( $post_data ['place_id'] ) : 0;
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );
        if (! $post_place_id || $current_exam ['place_id'] != $post_place_id)
        {
            output_json ( CODE_ERROR, '不存在当前考试' );
        }

        // 检查试卷是否合法
        $post_paper_data = isset ( $post_data ['paper_data'] ) ? $post_data ['paper_data'] : array ();
        if (! count ( $post_paper_data ))
        {
            output_json ( CODE_ERROR, '非法试题参数' );
        }
        $this->load->model ( 'exam/exam_test_paper_model' );
        $this->load->model ( 'exam/exam_test_result_model' );

        $test_paper_model = $this->exam_test_paper_model;

        // 批量更新
        // 考试结果
        $etp_ids = array_filter ( array_values ( explode ( ',', $this->session->userdata ( 'etp_id' ) ) ) );

        $update_data = array ();
        foreach ( $post_paper_data as $paper_data )
        {
            $paper_data = ( array ) $paper_data;
            if (! isset ( $paper_data ['paper_id'] ) || ! isset ( $paper_data ['etp_id'] ) || ! isset ( $paper_data ['question'] )/*
			    || !count($paper_data['question'])*/) {

                output_json ( CODE_ERROR, '非法试题参数' );
            }

            // 检查是否存在当前考试记录
            $current_etp_uid = $test_paper_model->get_test_paper_by_id ( $paper_data ['etp_id'], 'uid' );
            if ($current_etp_uid != $post_uid)
            {
                output_json ( CODE_ERROR, '非法考试记录' );
            }

            $paper_id = $paper_data ['paper_id'];
            $etp_id = $paper_data ['etp_id'];
            $questions = $paper_data ['question'];

            foreach ( $questions as $q )
            {
                $q = ( array ) $q;
                $etr_id = intval ( $q ['etr_id'] );
                if (! $etr_id)
                {
                    unset ( $update_data );
                    // output_json(CODE_ERROR,
                    // '非法试题参数');
                    continue;
                }

                $update_data [] = array ('etr_id' => $etr_id,'answer' => $q ['answer'] );
            }

            // $etp_ids
        // []
        // =
        // $etp_id;
        }

        if (count ( $update_data ))
        {
            if (! $this->exam_test_result_model->update_batch ( $update_data, 'id' ))
            {
                output_json ( CODE_ERROR, '交卷失败，请联系监考老师' );
            }
        }

        /*
         *
         * todo
         * 将该学生该场考试状态标记为
         * 已考
         * 清除考生会话信息
         */
        // 查看该考生是否已经作弊
        $is_cheat = isset ( $post_data ['is_c'] ) ? intval ( $post_data ['is_c'] ) : 0;
        if ($is_cheat > 0)
        {
            $test_paper_model->update_student_test_status ( $etp_ids, '-1' );
            $this->session->set_userdata ( array ('has_cheated' => '1' ) );

            // 作弊日志
            exam_log ( EXAM_LOG_CHEAT, array ('ip' => $this->input->ip_address () ) );
        }
        else
        {
            $test_paper_model->update_student_test_status ( $etp_ids );

            // 交卷日志
            exam_log ( EXAM_LOG_SUBMIT, array ('time' => date ( 'Y-m-d H:i:s' ) ) );
        }

        $this->load->model ( 'exam/student_model' );
        $this->student_model->destory_exam_student_session ();

        // 设置考生交卷标志
        $this->exam_model->set_test_submit_session ();
        // session_destroy();
        output_json ( CODE_SUCCESS, '交卷成功' );
    }

    /**
     * 考生提交考试(单个题目)
     */
    public function submitp()
    {
        // 获取提交问题
        // json格式
        /*
         *
         * post_data格式：
         * array(
         * 'uid'
         * =>
         * '考生ID',
         * 'place_id'
         * =>
         * '考试ID',
         * 'paper_data'=>
         * array(
         * array(
         * 'paper_id'
         * =>
         * '试卷ID',
         * 'etp_id'
         * =>
         * '当前考试记录ID',
         * 'question'
         * =>
         * array(
         * array(
         * 'etr_id'
         * =>
         * '问题结果ID',
         * 'answer'
         * =>
         * '答案',
         * )
         * )
         * ),
         * ...
         * )
         * )
         */
        $post_data = $this->input->post ( 'post_data' );
        $post_data = ( array ) @json_decode ( $post_data );
        if (! is_array ( $post_data ) || ! count ( $post_data ))
        {
            output_json ( CODE_ERROR, '参数非法' );
        }

        // 检查学生的合法性
        $exam_uid = $this->session->userdata ( 'exam_uid' );
        $post_uid = isset ( $post_data ['uid'] ) ? intval ( $post_data ['uid'] ) : 0;
        if (! $exam_uid || ! $post_uid || $exam_uid != $post_uid)
        {
            output_json ( CODE_ERROR, '您不是合法的考生' );
        }

        // 检查
        // 当前考试是否合法
        $post_place_id = isset ( $post_data ['place_id'] ) ? intval ( $post_data ['place_id'] ) : 0;
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );
        if (! $post_place_id || $current_exam ['place_id'] != $post_place_id)
        {
            output_json ( CODE_ERROR, '不存在当前考试' );
        }

        // 检查试卷是否合法
        $post_paper_data = isset ( $post_data ['paper_data'] ) ? $post_data ['paper_data'] : array ();
        if (! count ( $post_paper_data ))
        {
            output_json ( CODE_ERROR, '非法试题参数' );
        }
        $this->load->model ( 'exam/exam_test_paper_model' );
        $this->load->model ( 'exam/exam_test_result_model' );

        $test_paper_model = $this->exam_test_paper_model;

        // 批量更新
        // 考试结果
        $etp_ids = array ();
        $update_data = array ();
        foreach ( $post_paper_data as $paper_data )
        {
            $paper_data = ( array ) $paper_data;
            if (! isset ( $paper_data ['paper_id'] ) || ! isset ( $paper_data ['etp_id'] ) || ! isset ( $paper_data ['question'] )/*
    		|| !count($paper_data['question'])*/) {

                output_json ( CODE_ERROR, '非法试题参数' );
            }

            // 检查是否存在当前考试记录
            $current_etp_uid = $test_paper_model->get_test_paper_by_id ( $paper_data ['etp_id'], 'uid' );
            if ($current_etp_uid != $post_uid)
            {
                output_json ( CODE_ERROR, '非法考试记录' );
            }

            $paper_id = $paper_data ['paper_id'];
            $etp_id = $paper_data ['etp_id'];
            $questions = $paper_data ['question'];

            foreach ( $questions as $q )
            {
                $q = ( array ) $q;
                $etr_id = intval ( $q ['etr_id'] );
                if (! $etr_id)
                {
                    unset ( $update_data );
                    // output_json(CODE_ERROR,
                    // '非法试题参数');
                    continue;
                }

                $update_data [] = array ('etr_id' => $etr_id,'answer' => $q ['answer'] );
            }

            $etp_ids [] = $etp_id;
        }

        if (count ( $update_data ))
        {
            if (! $this->exam_test_result_model->update_batch ( $update_data, 'id' ))
            {
                output_json ( CODE_ERROR, '提交失败，请联系监考老师' );
            }
        }

        /*
         *
         * todo
         * 将该学生该场考试状态标记为
         * 已考
         * 清除考生会话信息
         */
        // 查看该考生是否已经作弊

        // $this->load->model('exam/student_model');
        // $this->student_model->destory_exam_student_session();

        // 设置考生交卷标志
        // $this->exam_model->set_test_submit_session();
        // $this->session->set_userdate('etp_ids_1',
        // $etp_ids);
        // $test_paper_model->update_student_test_status
        // (
        // $etp_ids
        // );
        output_json ( CODE_SUCCESS, '' );
    }

    /**
     * auto
     * ping
     */
    public function ping()
    {
        $this->load->model ( 'exam/exam_model' );
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );
        if ($uid = $this->session->userdata ( 'exam_uid' ))
        {
            $this->load->model ( 'exam/student_log_stat_model', 'log_stat_model' );
            $this->log_stat_model->set_exam_place_student_active_status ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );
            header ( 'ping:1' );
            die ();
        }
    }

    /**
     * 考生考试期间
     * 离开考试界面
     * 次数保存
     */
    public function set_window_blur()
    {
        if ($uid = $this->session->userdata ( 'exam_uid' ))
        {
            $this->load->model ( 'exam/exam_model' );
            $current_exam = $this->exam_model->get_cookie_current_exam ( true );

            $this->load->model ( 'exam/student_log_stat_model', 'stat_model' );
            $this->stat_model->set_student_window_blur_count ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );

            output_json ( CODE_SUCCESS, 'ok' );
        }
    }

    /**
     * 获取
     * 考生考试期间
     * 离开考试界面
     * 次数
     */
    protected function _get_window_blur()
    {
        $this->load->model ( 'exam/exam_model' );
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );
        $count = 0;
        if ($uid = $this->session->userdata ( 'exam_uid' ))
        {
            $this->load->model ( 'exam/student_log_stat_model', 'stat_model' );
            $count = $this->stat_model->get_student_window_blur_count ( $current_exam ['exam_id'], $current_exam ['place_id'], $uid );
        }

        return $count;
    }

    /**
     * 返回当前考场信息
     *
     * @author
     *         TCG
     * @param int $place_id
     *            考场ID
     * @return mixed
     *         失败返回false,
     *         成功返回考场信息
     */
    public function exam_place_info($place_id = null)
    {
        $current_exam = $this->exam_model->get_cookie_current_exam ( true );

        if (is_null ( $place_id ))
        {

            if (! $current_exam)
            {
                return false;
                exit ();
            }

            $palce_id = $current_exam ['place_id'];
        }

        /**
         *
         * @todo
         *       读取考试缓存
         */

        /**
         * 读取考场信息
         */
        $this->load->model ( 'exam/exam_place_model' );
        $exam_info = $this->exam_place_model->get_exam_place_by_id ( $palce_id, 'place_id, end_time' );
        $exam_config = C ( 'exam_config', 'app/exam/website' );

        $time_end = $exam_config ['time_period_limit'] ['submit'];
        $submit_time_period = $time_end * 60;
        $exam_info ['finish_time'] = date ( 'Y-m-d H:i:s', $exam_info ['end_time'] + $submit_time_period );

        $exam_info ['end_time'] = date ( 'Y-m-d H:i:s', $exam_info ['end_time'] );

        // 检查
        // 当前时间段
        // 是否存在
        // 考试
        // 获取考场信息

        /**
         * 是否需要更新考场时间
         */
        if ($exam_info ['end_time'] != $current_exam ['end_time'])
        {

            /**
             * 更新cookie信息
             */
            $cookie = json_decode ( $this->session->userdata ( 'zeming_exam_test' ), true );

            $cookie ['end_time'] = $exam_info ['end_time'];
            $cookie ['finish_time'] = $exam_info ['finish_time'];
            $this->session->set_userdata ( 'zeming_exam_test', json_encode ( $cookie ) );

            echo json_encode ( $exam_info );
            exit ();
        }
        else
        {
            return false;
            exit ();
        }
    }
}