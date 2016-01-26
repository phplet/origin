<?php !defined('BASEPATH') && exit();

/**
 * 考生考试 界面
 * @author TCG
 * @create 2013-12-02
 */
class Test extends Demo_Controller
{
    public function __construct()
    {
        parent::__construct();
    	// 后台登录验证处理
    	$segment = $this->uri->rsegment(2);
    	if ( $segment !== 'login' && $segment !== 'check_login' && $segment !== 'report')
    	{
    		/** 检查登陆 */
    		$exam_session = $this->session->userdata('demo_exam_uid');
    		if (!$exam_session) {
    			redirect('demo/index/login');
    		}
    	}
    }

    /**
     * 考生
     */
    public function index()
    {
        ignore_user_abort(); //即使Client断开(如关掉浏览器)，PHP脚本也可以继续执行.
        set_time_limit(0);
    	$data = $this->_data;
    	$data['page_title'] = '考试界面';

    	//考生信息
    	$userdata = $this->session->userdata;
    	$student_info = array(
    			'picture'		=> __IMG_ROOT_URL__ . $userdata['picture'],
    			'truename' 		=> $userdata['last_name'] . $userdata['first_name'],//姓名
    			'exam_ticket'	=> $userdata['exam_ticket'],//准考证号
    			'school_name'	=> $userdata['school_name'],
    			'grade_name'	=> $userdata['grade_name'],
    	);

    	//考试期数
    	$current_exam = $this->exam_model->get_session_current_exam(true);

    	//获取试卷试题总数
    	$this->load->model('demo/exam_test_paper_model');
    	$this->load->model('demo/exam_paper_model');

    	$test_paper_model = $this->exam_test_paper_model;
    	$paper_model = $this->exam_paper_model;

    	$paper_items = array(
    				 'paper_id',
		             'exam_id',
		             'paper_name',
		             'ques_num',
		             'qtype_ques_num',
    	);
    	$paper_info = array();
    	$paper_ques_infos = array();
    	$test_papers = $test_paper_model->get_stduent_test_paper($current_exam['place_id'], $userdata['uid']);

    	$etp_ids = array();
    	foreach ($test_papers as $paper) {
    		$paper_id = $paper['paper_id'];
    		$tmp_paper = $paper_model->get_paper_by_id($paper_id, $paper_items);
    		if (is_array($tmp_paper) && count($tmp_paper)) {
    			//附加试卷总分属性
    			$tmp_paper['full_score'] = $paper['full_score'];
    			$tmp_paper['subject_id'] = $paper['subject_id'];
    			$tmp_paper['etp_id'] = $paper['etp_id'];

				if (!in_array($paper['etp_id'], $etp_ids)) {
					array_push($etp_ids, $paper['etp_id']);
				}
    			//获取试卷 试题信息
    			$paper_questions = $paper_model->get_paper_question_detail_p($paper_id);
    			$tmp_paper['question_list'] = array_values($this->_filter_paper_question_detail($paper_questions));
    			$paper_info[$tmp_paper['subject_id']] = $tmp_paper;

    			//提取考试试题
    			$ques_ids = array();
    			$tmp_ques_list = $tmp_paper['question_list'];
    			foreach ($tmp_ques_list as $row) {
    				$tmp_ques_ids = array_keys($row['list']);
    				foreach ($tmp_ques_ids as $t_id) {
    					@list($q, $ques_id) = @explode('_', $t_id);
    					if (is_null($ques_id)) continue;

    					$ques_ids[] = $ques_id;
    				}
    			}

    			$paper_ques_infos[$tmp_paper['etp_id']] = array('etp_id' => $tmp_paper['etp_id'], 'ques_id' => implode(',', $ques_ids));
    		}
    	}

    	//批量插入考试试卷试题
    	$insert_data = array();
    	$update_data = array();

    	$etp_ids = array_keys($paper_ques_infos);
    	$etp_id_str = implode(',', $etp_ids);
    	$etp_id_where = count($etp_ids) == '1' ? "etp_id=$etp_ids[0]" : "etp_id in (" . ($etp_id_str ? $etp_id_str : 0) . ")";

    	$old_paper_questions = $this->db->query("select id, etp_id from {pre}exam_test_paper_question where $etp_id_where")->result_array();
    	$paper_question_ids = array();
    	foreach ($old_paper_questions as $row) {
    		$paper_question_ids[$row['etp_id']] = $row['id'];
    	}

    	foreach ($paper_ques_infos as $etp_id=>$val) {
    		if (isset($paper_question_ids[$etp_id])) {
    			$val['id'] = $paper_question_ids[$etp_id];
    			$update_data[] = $val;
    		} else {
    			$insert_data[] = $val;
    		}
    	}
    	if (count($insert_data)) {
    		$this->db->insert_batch('exam_test_paper_question', $insert_data);
    	}

    	if (count($update_data)) {
    		$this->db->update_batch('exam_test_paper_question', $update_data, 'id');
    	}

    	//检查是否要考的科目都已经备选了试卷
    	$subjects = explode ( ',', $current_exam ['subject_id']);
    	$subjects = array_filter($subjects);

    	$paper_subjects = array_keys($paper_info);
    	$subjects = array_filter($paper_subjects);

    	$subjects = array_diff($subjects, $paper_subjects);
    	$subjects = array_filter($subjects);

    	if (count($subjects)) {
    		$subject_names = array();
    		foreach ($subjects as $subject) {
    			$subject_names[] = C("subject/{$subject}");
    		}
    		message('您所考的科目有以下科目未分配试卷：' . implode(', ', $subject_names));
    	}


    	//将考生的考试记录保存到session中
    	$this->exam_model->set_test_paper_sessoin($etp_ids);

    	$data['student_info'] = $student_info;
    	$data['current_exam'] = $current_exam;
    	$data['paper_info'] = $paper_info;

    	//获取考生离开界面的次数记录
    	$data['count_window_blur'] = $this->_get_window_blur();

    	$this->load->view('test/index', $data);
    }

    /**
     * 将试卷试题的分类重组
     */
    protected function _filter_paper_question_detail($paper_questions)
    {
    	//格式化$groups key 内容
    	$tmp_groups = array();
    	$count_groups = count($paper_questions);
    	foreach ($paper_questions as $key=>$item) {
    	    $item ['type'] = $key;
            // if ($key == 0) {
            // $key = $count_groups;
            // }
		    foreach ($item['list'] as $k=>$v)
		    {
		    	$item['list']["q_{$k}"] = $v;
		    	unset($item['list'][$k]);
		    }
    		$tmp_groups[$key] = $item;
    		unset($paper_questions[$key]);
    	}

    	return $tmp_groups;
    }

    /**
     * 问题详情
     */
    public function question_detail()
    {
    	$data = $this->_data;

    	$etp_id = intval($this->input->post('etp_id'));
    	$data['is_single'] = intval($this->input->post('is_single'));//是否为单条模式(0：不是， 1：是)
    	$data['is_first'] = intval($this->input->post('is_first'));//是否请求第一条记录(0：不是， 1：是)

    	//参数检查
    	if (!$etp_id) {
	    	output_json(CODE_ERROR, '参数非法');
    	}

    	//试题ID，多题时用,隔开
    	$question_id = trim($this->input->post('question_id'));
    	$question_ids = @explode(',', $question_id);
    	if (!is_array($question_ids) || !count($question_ids)) {
	    	output_json(CODE_ERROR, '该试题不存在');
    	}

    	$this->load->model('demo/exam_test_result_model');
    	$test_result_model = $this->exam_test_result_model;
    	$questions = array();
    	foreach ($question_ids as $question_id) {
	    	$question = $test_result_model->get_test_question($etp_id, $question_id);
	    	if (is_array($question) && count($question)) {
	    		$questions[] = $question;
	    	}
    	}

    	//将问题进行按题型归档
    	/*
    	 * array(
    	 * 		'1' => '单选',
    	 *      '2' => '不定项',
    	 *      '3' => '填空',
    	 *      '0' => '题组'
    	 * )
    	 */
    	$tmp_questions = array();
    	foreach ($questions as $question) {
    		$tmp_questions[$question['type']][] = $question;
    	}
    	unset($questions);

    	//arsort($tmp_questions);

    	//将题组放最后
//     	if (isset($tmp_questions[0])) {
// 	    	$first_question = $tmp_questions[0];
// 	    	unset($tmp_questions[0]);
// 	    	$tmp_questions[0] = $first_question;
//     	}
//     	print_r($tmp_questions);
//     	die;


    	$data['questions'] = $tmp_questions;
    	$data['qtypes'] = C('qtype');
    	$data['etp_id'] = $etp_id;

    	output_json(CODE_SUCCESS, 'success', $this->load->view('test/question_detail', $data, true));
    }

    /**
     * 考生提交考试
     */
    public function submit()
    {
        //获取提交问题 json格式
        /*
         * post_data格式：
         *      array(
         * 	    'uid' => '考生ID',
         * 	    'place_id' => '考试ID',
         * 	    'paper_data'=> array(
         * 	array(
         *          'paper_id' => '试卷ID',
         * 	    'etp_id'   => '当前考试记录ID',
         * 	    'question' => array(
         * 	        array(
         * 	            'etr_id'  => '问题结果ID',
         * 	            'answer'  => '答案',
         * 		)
         * 	)
         * ),
         * ...
         * )
         * )
         */
		$post_data = $this->input->post('post_data');
		$post_data = (Array) @json_decode($post_data);
                if (!is_array($post_data) || !count($post_data))
                {
	    	    output_json(CODE_ERROR, '参数非法');
		}

		//检查学生的合法性
		$exam_uid = $this->session->userdata('demo_exam_uid');
		$post_uid = isset($post_data['uid']) ? intval($post_data['uid']) : 0;
		if (!$exam_uid || !$post_uid || $exam_uid != $post_uid) {
	    	output_json(CODE_ERROR, '您不是合法的考生');
		}

		//检查 当前考试是否合法
		$post_place_id = isset($post_data['place_id']) ? intval($post_data['place_id']) : 0;
		$current_exam = $this->exam_model->get_session_current_exam(true);
		if (!$post_place_id || $current_exam['place_id'] != $post_place_id) {
	    	output_json(CODE_ERROR, '不存在当前考试');
		}

		//检查试卷是否合法
		$post_paper_data = isset($post_data['paper_data']) ? $post_data['paper_data'] : array();
		if (!count($post_paper_data)) {
	    	output_json(CODE_ERROR, '非法试题参数');
		}
		$this->load->model('demo/exam_test_paper_model');
		$this->load->model('demo/exam_test_result_model');

		$test_paper_model = $this->exam_test_paper_model;

		//批量更新 考试结果
		$etp_ids = array();
		//$etp_ids = array_filter ( array_values ( explode ( ',', $this->session->userdata ( 'etp_id' ) ) ) );


		$update_data = array();
		foreach ($post_paper_data as $paper_data) {
			$paper_data = (Array) $paper_data;
			if (!isset($paper_data['paper_id'])
				|| !isset($paper_data['etp_id'])
				|| !isset($paper_data['question'])/*
			    || !count($paper_data['question'])*/) {

				output_json(CODE_ERROR, '非法试题参数');
			}

			//检查是否存在当前考试记录
			$current_etp_uid = $test_paper_model->get_test_paper_by_id($paper_data['etp_id'], 'uid');
			if ($current_etp_uid != $post_uid) {
				output_json(CODE_ERROR, '非法考试记录');
			}

			$paper_id = $paper_data['paper_id'];
			$etp_id = $paper_data['etp_id'];
			$questions = $paper_data['question'];

			foreach ($questions as $q) {
				$q = (Array) $q;
				$etr_id = intval($q['etr_id']);
				if (!$etr_id) {
					unset($update_data);
// 					output_json(CODE_ERROR, '非法试题参数');
					continue;
				}

				$update_data[] = array(
					'etr_id'  => $etr_id,
					'answer'  => $q['answer'],
				);

			}

		$etp_ids[] = $etp_id;
		}

		if (count($update_data)) {
			if (!$this->exam_test_result_model->update_batch($update_data)) {
	    		output_json(CODE_ERROR, '抱歉，交卷失败.');
			}
		}

		/* todo
		 * 将该学生该场考试状态标记为 已考
		 * 清除考生会话信息
		 */
		//查看该考生是否已经作弊
		$is_cheat = isset($post_data['is_c']) ? intval($post_data['is_c']) : 0;
		if ($is_cheat > 0) {
			$test_paper_model->update_student_test_status($etp_ids, '-1');
			$this->session->set_userdata(array('has_cheated' => '1'));

			//作弊日志
			demo_exam_log(EXAM_LOG_CHEAT, array('ip' => $this->input->ip_address()));

		} else {
			$test_paper_model->update_student_test_status($etp_ids);

			//交卷日志
			demo_exam_log(EXAM_LOG_SUBMIT, array('time' => date('Y-m-d H:i:s')));
		}

		$this->load->model('demo/student_model');

		//用于生成测评报告标识
		$all_userdata = $this->session->all_userdata();
		$report_mark = $all_userdata['exam_pid'] . '_' . $all_userdata['subject_id'] . '_' . $all_userdata['demo_exam_uid'] . '_' . $all_userdata['exam_id'];
		$this->session->set_userdata('report_mark', $report_mark);

		$this->student_model->destory_exam_student_session();

		output_json(CODE_SUCCESS, '交卷成功^_^');
    }


    /**
     * 考生提交考试
     */
    public function submitp()
    {
        //获取提交问题 json格式
        /*
        * post_data格式：
        * 		array(
                * 			'uid' => '考生ID',
                * 			'place_id' => '考试ID',
                * 			'paper_data'=> array(
                        * 								array(
                                * 								'paper_id' => '试卷ID',
                                * 								'etp_id'   => '当前考试记录ID',
                                * 								'question' => array(
                                        * 												array(
                                                * 													'etr_id'  => '问题结果ID',
                                                * 													'answer'  => '答案',
                                                * 												)
        * 								)
        * 								),
        * 								...
        * 							)
		 * 		)
        */

        $post_data = $this->input->post('post_data');
        $post_data = (Array) @json_decode($post_data);

        if (!is_array($post_data) || !count($post_data)) {
	    	output_json(CODE_ERROR, '参数非法');
        }

        //检查学生的合法性
        $exam_uid = $this->session->userdata('demo_exam_uid');
        $post_uid = isset($post_data['uid']) ? intval($post_data['uid']) : 0;

        if (!$exam_uid || !$post_uid || $exam_uid != $post_uid) {
            output_json(CODE_ERROR, '您不是合法的考生');
        }

        //检查 当前考试是否合法
        $post_place_id = isset($post_data['place_id']) ? intval($post_data['place_id']) : 0;
        $current_exam = $this->exam_model->get_session_current_exam(true);

        if (!$post_place_id || $current_exam['place_id'] != $post_place_id) {
            output_json(CODE_ERROR, '不存在当前考试');
        }

		//检查试卷是否合法
		$post_paper_data = isset($post_data['paper_data']) ? $post_data['paper_data'] : array();

    	if (!count($post_paper_data)) {
    		output_json(CODE_ERROR, '非法试题参数');
        }

        $this->load->model('demo/exam_test_paper_model');
        $this->load->model('demo/exam_test_result_model');
        $test_paper_model = $this->exam_test_paper_model;

		//批量更新 考试结果
    	$etp_ids = array();
    	$update_data = array();

    	foreach ($post_paper_data as $paper_data) {
    		$paper_data = (Array) $paper_data;

    		if (!isset($paper_data['paper_id']) || !isset($paper_data['etp_id']) || !isset($paper_data['question'])) {
                output_json(CODE_ERROR, '非法试题参数');
    		}

    		//检查是否存在当前考试记录
			$current_etp_uid = $test_paper_model->get_test_paper_by_id($paper_data['etp_id'], 'uid');

    		if ($current_etp_uid != $post_uid) {
				output_json(CODE_ERROR, '非法考试记录');
    		}

    		$paper_id = $paper_data['paper_id'];
    		$etp_id = $paper_data['etp_id'];
    		$questions = $paper_data['question'];

    		foreach ($questions as $q) {
                $q = (Array)$q;
                $etr_id = intval($q['etr_id']);

                if (! $etr_id) {
                    unset ($update_data);
                    continue;
                }

                $update_data [] = array('etr_id' => $etr_id, 'answer' => $q ['answer']);
            }

            $etp_ids[] = $etp_id;
        }

        if (count($update_data)) {
            if (!$this->exam_test_result_model->update_batch($update_data)) {
                output_json ( CODE_ERROR, '提交失败，请联系监考老师' );
            }
        }

    	output_json(CODE_SUCCESS, '');
    }

    /**
     * 转到生成报告
     */
    public function report()
    {
    	$this->load->model('demo/report/general_model');
    	$act = $this->input->get_post('act');
    	$userdata = $this->session->all_userdata();

    	if (isset($userdata['report_mark']) && stripos($userdata['report_mark'], '_') !== false)
    	{
    		list($exam_pid, $subject_id, $uid) = explode('_', $userdata['report_mark']);

    		if ($act == 'get')
    		{
    			//查询报告
	    		$content = $this->general_model->get_html_content($exam_pid, $subject_id, $uid);
	    		if ($content === false)
	    		{
	    			//判断是否作弊
	    			$sql = "select count(*) as count from {pre}exam_test_paper 
	    			        where exam_pid={$exam_pid} and subject_id={$subject_id} and uid={$uid} and etp_flag=-1";
	    			$result = $this->db->query($sql)->row_array();
	    			if ($result['count'])
	    			{
		    			message('抱歉，您在该场考试中作弊，无法生成报告.');
	    			}
	    			else
	    			{
		    			message('抱歉，找不到您的测试报告.');
	    			}
	    		}
	    		else
	    		{
	    			echo $content;
	    			die;
	    		}
    		}
    		elseif($act == 'doing')
    		{

    			//生成报告
    			$sql = "select etp_flag from {pre}exam_test_paper 
    			         where exam_pid={$exam_pid} and subject_id={$subject_id} and uid={$uid}";
    			$result = $this->db->query($sql)->row_array();
    			if (!isset($result['etp_flag']))
    			{
    				message('找不到考试记录.');
    			}

    			if ($result['etp_flag'] == '0')
    			{
    				message('当前科目正在考试，无法生成报告.');
    			}

    			if ($result['etp_flag'] == '-1')
    			{
    				message('当前科目有作弊行为，无法生成报告.');
    			}

    			$data['subject_name'] = C('subject/'.$subject_id);
    			$this->load->view('report/doing', $data);
    		}
    		elseif($act == 'general')
    		{
				//生成报告
    			$sql = "select etp_flag from {pre}exam_test_paper 
    			         where exam_pid={$exam_pid} and subject_id={$subject_id} and uid={$uid}";
				$result = $this->db->query($sql)->row_array();
				if (!isset($result['etp_flag']))
				{
    				message('找不到考试记录.');
				}

				if ($result['etp_flag'] == '0')
    			{
    				message('当前科目正在考试，无法生成报告.');
    			}

				if ($result['etp_flag'] == '-1')
    			{
    				message('当前科目有作弊行为，无法生成报告.');
    			}

    			if (!$this->general_model->general_report())
    			{
    				output_json(CODE_ERROR, '抱歉，报告生成失败');
    			}
    			else
    			{
    				output_json(CODE_SUCCESS);
//     				redirect('demo/test/report/?act=get');
    			}
    		}
    	}
    	else
    	{
    		message('请先参与测试.');
    	}
    }

    /**
     * auto ping
     */
    public function ping()
    {
    	$this->load->model('demo/exam_model');
    	$current_exam = $this->exam_model->get_session_current_exam(true);
    	if ($uid = $this->session->userdata('demo_exam_uid')) {
    		$this->load->model('demo/student_log_stat_model', 'log_stat_model');
    		$this->log_stat_model->set_exam_place_student_active_status($current_exam['exam_id'], $current_exam['place_id'], $uid);

    		header('ping:1');
    		die;
    	}
    }

    /**
     * 考生考试期间 离开考试界面 次数保存
     */
    public function set_window_blur()
    {
    	if ($uid = $this->session->userdata('demo_exam_uid')) {
	    	$this->load->model('demo/exam_model');
	    	$current_exam = $this->exam_model->get_session_current_exam(true);

	    	$this->load->model('demo/student_log_stat_model', 'stat_model');
    		$this->stat_model->set_student_window_blur_count($current_exam['exam_id'], $current_exam['place_id'], $uid);

    		output_json(CODE_SUCCESS, 'ok');
    	}
    }

    /**
     * 获取 考生考试期间 离开考试界面 次数
     */
    protected function _get_window_blur()
    {
    	$this->load->model('demo/exam_model');
    	$current_exam = $this->exam_model->get_session_current_exam(true);
    	$count = 0;
    	if ($uid = $this->session->userdata('demo_exam_uid')) {
	    	$this->load->model('demo/student_log_stat_model', 'stat_model');
    		$count = $this->stat_model->get_student_window_blur_count($current_exam['exam_id'], $current_exam['place_id'], $uid);
    	}

    	return $count;
    }
}

