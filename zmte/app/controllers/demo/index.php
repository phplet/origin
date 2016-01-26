<?php !defined('BASEPATH') && exit();

/**
 * 机考主页面
 * @author TCG
 * @create 2013-12-02
 */
class Index extends Demo_Controller
{
    public function __construct()
    {
        parent::__construct();

        $segment = $this->uri->rsegment(2);
        if ($this->session->userdata('demo_exam_uid') && $segment != 'check_login')
        	redirect('demo/test/index');

        $this->load->model('demo/exam_model');
    }

    /**
     * 机考首页
     */
    public function index()
    {
        $grade = intval($this->input->get('grade'));
    	$subject_id = intval($this->input->get('subject_id'));
    	redirect('demo/index/login?subject_id='.$subject_id.'&grade='.$grade.'');
    }

    /**
     * 登录页面
     */
    public function login()
    {
    	$this->load->model('demo/report/general_model');

    	$data = $this->_data;
    	$data['page_title'] = '考生登录';

    	$data['current_exam'] = $this->exam_model->get_session_current_exam(true);
        $data['grades'] = C('grades');

    	$this->load->view('index/login', $data);
    }

    /**
     * 学生登录检查
     */
    public function check_login()
    {
    	//获取当前
    	$current_exam = $this->exam_model->get_session_current_exam(true);

    	$exam_ticket = trim($this->input->post('exam_ticket'));
    	$password = $this->input->post('password');

    	if (!strlen($exam_ticket)) {
    		output_json(CODE_ERROR, '请输入正确的准考证号.');
    	}
    	
    	if (!is_email($exam_ticket)
    	   && !is_idcard($exam_ticket)
    	   && !is_numeric($exam_ticket))
    	{
    	    output_json ( CODE_ERROR, '请输入合法的登陆帐号.' );
    	}

    	if (!strlen($password)) {
    		output_json(CODE_ERROR, '密码不能为空.');
    	}

    	//检查帐号密码是否正确
    	$this->load->model('demo/student_model');
    	$student = $this->student_model->is_valid_student($exam_ticket, $password);
    	if (!$student) {
    		output_json(CODE_ERROR, '登陆帐号或密码不正确，请检查.');
    	}

    	$place_id = $current_exam['place_id'];
    	$user_id = $student['uid'];

    	//设置考生考卷信息
    	$place_id = $current_exam['place_id'];
    	$uid = $student['uid'];

    	$this->load->model('demo/exam_test_paper_model');
    	$test_paper_model = $this->exam_test_paper_model;
    	//设定考生考卷
    	/**
    	 * 需要事先判断 本场考试 是否已经分配考生试卷
    	 */
    	$test_papers = $test_paper_model->get_stduent_test_paper($place_id, $uid, 'etp_flag,etp_id', null);

    	if (!count($test_papers)) {
	    	$insert_ids = $test_paper_model->set_student_test_paper($place_id, $uid);

	    	//设置考试记录
	    	if ($insert_ids === false) {
	    		output_json(CODE_ERROR, '抱歉，该学科未分配样卷.', array(), 'demo/index/login');
	    	}

	    	if (count($insert_ids)) {
		    	$this->session->set_userdata(array('etp_id' => implode(',', $insert_ids)));
	    	}

    	} else {


    		$etp_flag = $test_papers[0]['etp_flag'];
    		if ($etp_flag < 0) {
    			output_json(CODE_ERROR, '很遗憾，您在本场考试中有作弊行为，无法继续考试.', array(), 'demo/index/login');
    		} elseif($etp_flag > 0) {
    			//用于生成测评报告标识
    			$all_userdata = $this->session->all_userdata();
    			$report_mark = $all_userdata['exam_pid'] . '_' . $all_userdata['subject_id'] . '_' . $uid . '_' . $all_userdata['exam_id'];
    			$this->session->set_userdata('report_mark', $report_mark);

    			output_json(CODE_SUCCESS, '抱歉，您已经交卷了, 将为您跳转到您的测评报告.', array(), 'setTimeout(function () {window.location.href="'.site_url('demo/test/report/?act=get').'";}, 3000);');
	    		//message('抱歉，您已经交卷了, 将为您跳转到您的测评报告.', 'demo/test/report?act=get');
    		}
    	}

    	//添加考场在考人员统计
    	//检查考生是否已经登录过
    	$this->load->model('demo/student_log_stat_model');
    	try {
    		$this->student_log_stat_model->set_exam_place_member($current_exam['exam_id'], $current_exam['place_id'], $user_id);
    	} catch(Exception $e) {
    		output_json(CODE_ERROR, $e->getMessage());
    	}

    	//==================登录成功操作========================
    	//考生登录成功，将考生信息保存在session
    	$student['demo_exam_uid'] = $student['uid'];

    	//补齐当前考生的 学校 & 年级信息
    	$this->load->model('demo/school_model');
    	$school = $this->school_model->get_school_by_id($student['school_id']);
    	$student['school_name'] = count($school) ? $school['school_name'] : '--';

    	//获取年级信息
    	$grade_id = $student['grade_id'];
    	$grades = C('grades');
    	$student['grade_name'] = isset($grades[$grade_id]) ? $grades[$grade_id] : '--';

    	//设置考生的会话
    	$this->student_model->set_exam_student_session($student);

    	//判断该考生是否有离开考试界面嫌疑
    	$this->load->model('demo/student_log_stat_model', 'log_stat_model');
    	if ($this->log_stat_model->has_beyond_active_time($current_exam['exam_id'], $current_exam['place_id'], $uid)) {
	    	//机考日志
	    	demo_exam_log(EXAM_LOG_RELOGIN_AFTER_LEAVE_TEST_PAGE);
    		$this->log_stat_model->set_exam_place_student_active_status($current_exam['exam_id'], $current_exam['place_id'], $uid);
    	} else {
	    	//机考日志
	    	demo_exam_log(EXAM_LOG_LOGIN, array('ip' => $this->input->ip_address()));
    	}

    	output_json(CODE_SUCCESS);
    }
}
