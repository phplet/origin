<?php !defined('BASEPATH') && exit();

/**
 * 监考人员 管理界面
 * @author TCG
 * @create 2015-08-02
 */
class Invigilate extends Exam_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_once (APPPATH.'config/app/exam/log_type_desc.php');
    }

    /**
     * 监考首页
     */
    public function index()
    {
    	require_once (APPPATH.'config/app/exam/log_type_desc.php');
    	
    	$data = $this->_data;
    	$data['page_title'] = '监考人员监控平台';
    	$data['current_exam'] = $data['exams'][0];
    	
    	//获取操作日志类型
    	$log_types = Log_type_desc::$logs;
    	$data['log_types'] = $log_types;
    	
    	//获取当前场次考生数
    	$this->load->model('exam/exam_place_model');
    	$place_id = $this->session->userdata('exam_i_place_id');
    	$students = $this->exam_place_model->get_exam_place_student_list($place_id, true);
    	
    	$data['students'] = $students;
    	
    	//将考生按照拼音首字母归档
    	$pinyin_students = array();
    	$tmp_students = array();
    	foreach ($students as $student) {
    		$first_letter = string_to_pinyin($student['truename']);
    		$first_char = substr($first_letter, 0, 1);

    		$student['py'] = $first_letter;
    		$student['uid'] = $student['uid'];
    		$pinyin_students[$first_char][] = $student;

    		$tmp_students[] = $student;
    	}
    	
    	$data['pinyin_students'] = $pinyin_students;
    	$data['students'] = $tmp_students;
    	
    	$this->load->view('invigilate/index', $data);
    }
    
    /**
     * 监考人员 登录页面
     */
    public function login()
    {
    	$data = $this->_data;
    	$data['page_title'] = '监考人员登录';
    	
    	$this->load->view('invigilate/login', $data);
    }
    
    /**
     * 检查登陆
     */
    public function check_login()
    {
    	$email = trim($this->input->post('email'));
    	$password = $this->input->post('password');
    	if (!strlen($email)) {
    		output_json(CODE_ERROR, '请输入正确的邮箱地址.');
    	}
    	
    	if (!strlen($password)) {
    		output_json(CODE_ERROR, '请输入正确的密码.');
    	}

    	//检查帐号密码是否正确
    	$this->load->model('exam/exam_invigilator_model');
    	$invigilator_model = $this->exam_invigilator_model;
    	try {
	    	$invigilator = $invigilator_model->is_validate_invigilator($email, my_md5($password));
    	} catch (Exception $e) {
    		output_json(CODE_ERROR, $e->getMessage());
    	}
    	
    	//检查期次信息
    	$this->load->model('exam/exam_model');
    	$this->load->model('exam/exam_place_model');
    	$exam_model = $this->exam_model;
    	$place_model = $this->exam_place_model;
    	
    	$exam_id = intval($this->input->post('exam_id'));
    	if (!$exam_id) {
    		output_json(CODE_ERROR, '该考试期次不存在.');
    	}
    	
    	$exam = $exam_model->get_exam_by_id($exam_id);
    	if (!count($exam) || $exam['exam_pid'] > 0) {
    		output_json(CODE_ERROR, '该考试期次不存在.');
    	}
    	
    	$place_id = intval($this->input->post('place_id'));
    	if (!$place_id) {
    		output_json(CODE_ERROR, '该考场不存在.');
    	}
    	 
    	$place = $place_model->get_exam_place_by_id($place_id, 'place_id, exam_pid, start_time, end_time, ip');
    	
    	if (!count($place)) {
    		output_json(CODE_ERROR, '该考场不存在.');
    	}
    	
    	if ($place['exam_pid'] != $exam_id) {
    		output_json(CODE_ERROR, '该考场不在该期考试中.');
    	}

		if ($place['ip'])
		{
			if (!in_array($this->input->ip_address(), array_filter(explode(',',$place['ip'])))) 
			{
				output_json(CODE_ERROR, '非法考场地址.');
			}
		}

    	if (time() >= $place['end_time']) {
    		output_json(CODE_ERROR, '该考场的考试已经结束');
    	}
    	
    	if (!$invigilator_model->is_validate_exam_place_invigilator($place_id, $invigilator['invigilator_id'])) {
    		output_json(CODE_ERROR, '您不在当前考场中，有问题请联系系统管理员.');
    	} 
    	
    	//登陆成功，设置session
    	$this->exam_invigilator_model->set_invigilator_place_session($invigilator, $place);
    	
    	output_json(CODE_SUCCESS, '登陆成功');
    }
    
    /**
     * 退出登陆
     */
    public function logout()
    {
    	$act = $this->input->get('act');
    	$this->load->model('exam/exam_invigilator_model');
    	
    	//清除用户session
    	$this->exam_invigilator_model->destory_invigilator_session();
    	
    	if ($act && $act == 'force') {
    		redirect('exam/invigilate/login');
    	}
    	
    	message('退出成功', 'exam/invigilate/login');
    }
    
    /*
     * 重置监考人员密码
    */
    public function load_reset_password()
    {
    	$data = $this->_data;
    	
    	$data['uid'] = $this->session->userdata('exam_i_uid');
    	$this->load->view('invigilate/reset_password', $data);
    }
    
    /**
     * 重置监考人员密码
     */
    public function reset_password()
    {
    	$password = $this->input->post('old_password');
    	$new_password = $this->input->post('new_password');
    	$new_confirm_password = $this->input->post('confirm_password');
    	$uid = intval($this->input->post('uid'));
    	 
    	if (!strlen(trim($password))) {
    		output_json(CODE_ERROR, '旧密码不能为空.');
    	}
    	
    	if (is_string($passwd_msg = is_password($new_password))) {
    		output_json(CODE_ERROR, $passwd_msg);
    	}
    	
    	if (!strlen(trim($new_confirm_password))) {
    		output_json(CODE_ERROR, '确认密码不能为空.');
    	}
    	 
    	if ($new_confirm_password != $new_password) {
    		output_json(CODE_ERROR, '两次密码输入不一致.');
    	}
    	
		$invigilator_id = $this->session->userdata('exam_i_uid');
    	if ($uid <= 0 || $uid != $invigilator_id) {
    		output_json(CODE_ERROR, '不存在该监考人员.');
    	}
    	
    	$this->load->model('exam/exam_invigilator_model');
    	
    	//检查旧密码是否正确
    	$invigilater_passwd = $this->exam_invigilator_model->get_invigilator_by_id($uid, 'invigilator_password');
    	if (!count($invigilater_passwd)) {
    		output_json(CODE_ERROR, '不存在该监考人员.');
    	}
    	
    	if ($invigilater_passwd != my_md5($password)) {
    		output_json(CODE_ERROR, '旧密码不正确，请核实.');
    	}
    	
    	//检查帐号密码是否正确
    	$flag = $this->exam_invigilator_model->reset_invigilator_password($invigilator_id, my_md5($new_password));
    	if (!$flag) {
    		output_json(CODE_ERROR, '密码修改失败，请重试(如多次出现类似情况，请联系系统管理员)');
    	}
    	 
    	output_json(CODE_SUCCESS, '密码修改成功，请重新登录.');
    }
    
    /*
     * 加载 修改考生密码
     */
    public function load_chang_s_pwd()
    {
    	$data = $this->_data;
    	 
    	$this->load->view('invigilate/change_student_password', $data);
    }
    
    /*
    * 加载 踢出考生
    */
    public function load_out_student()
    {
        $data = $this->_data;
    
        $this->load->view('invigilate/out_student', $data);
    }
    
    
    
    /*
     * 加载 踢出考生
    */
    public function out_student_save()
    {
        
    	$exam_ticket = trim($this->input->post('account'));
    	$password = $this->input->post('password');
    	
    	$txt_student_tichu = $this->input->post('txt_student_tichu');
    	
  
    	if (!strlen($exam_ticket)) {
    		output_json(CODE_ERROR, '请输入正确的准考证号.');
    	}
    	
    
    	
    	if (!strlen($password)) {
    		output_json(CODE_ERROR, '理由不能为空.');
    	}
    	

    	if (!strlen($txt_student_tichu)) {
    	    output_json(CODE_ERROR, '状态不能为空.');
    	}
    	 

    	//检查帐号密码是否正确
    	$this->load->model('exam/student_model');
    	$student = $this->student_model->is_valid_student($exam_ticket);
    	if (!$student) {
    		output_json(CODE_ERROR, '该考生不存在.');
    	}
    	
    	//判断该考生是否在当前考场中
    	$this->load->model('exam/exam_place_model');
    	$exam_place_model = $this->exam_place_model;
    	$place_id = $this->session->userdata('exam_i_place_id');
    	$exam_id = $this->session->userdata('exam_i_exam_id');
    	
    	$user_id = $student['uid'];
    	if (!$exam_place_model->check_exam_place_student($place_id, $user_id)) {
			output_json(CODE_ERROR, '很抱歉，该考生不在本场考试中，有问题请联系系统管理员.');
    	}
    	
    	
    	


    	
    	//重置考生密码
    	try {
    	    
    	    if($txt_student_tichu==1)
    	    $action = 'out_student';
    	    else
    	    $action = 'in_student';
    	    
  
    	    if ($action && $log_type = Log_type_desc::get_log_alia($action)) {

    	        $log_content = $password;
    	        
    	        
    	    exam_log_1($log_type, $log_content,$user_id,$place_id,$exam_id);
    	    
    	    }
    	    
    	    
    	    $session_data = array(
    	            'exam_ticket_out' 		=> $exam_ticket,
    	            'password_out' 		=> $password,
    	            'txt_student_tichu' 		=> $txt_student_tichu
    	       
    	    );
    	    
    	    
    	    $this->session->set_userdata($session_data);
    		$exam_place_model->out_exam_place_student($place_id, $user_id, $password, $txt_student_tichu);

    		if($txt_student_tichu==1)
    		     output_json(CODE_SUCCESS, '踢出成功, 该考生考试信息为：<p><strong>准考证号：</strong>' . $exam_ticket . ' </p>');
    		else 
    		    output_json(CODE_SUCCESS, '恢复成功, 该考生考试信息为：<p><strong>准考证号：</strong>' . $exam_ticket . ' </p>');
    	    
    	} catch (Exception  $e) {
    		output_json(CODE_ERROR, '踢出失败，请重试(如多次出现类似情况，请联系系统管理员)');
    	}
    	
    }
    
    
    
    /**
     * 修改考生密码
     */
    public function reset_student_password()
    {
   		$exam_ticket = trim($this->input->post('account'));
    	$password = $this->input->post('password');
    	$confirm_password = $this->input->post('confirm_password');
    	
    	if (!strlen($exam_ticket)) {
    		output_json(CODE_ERROR, '请输入正确的准考证号.');
    	}
    	
    	if (is_string($passwd_msg = is_password($password))) {
    		output_json(CODE_ERROR, $passwd_msg);
    	}
    	
    	if (!strlen($confirm_password)) {
    		output_json(CODE_ERROR, '确认密码不能为空.');
    	}
    	
    	if ($confirm_password != $password) {
    		output_json(CODE_ERROR, '两次密码不一致.');
    	}

    	//检查帐号密码是否正确
    	$this->load->model('exam/student_model');
    	$student = $this->student_model->is_valid_student($exam_ticket);
    	if (!$student) {
    		output_json(CODE_ERROR, '该考生不存在.');
    	}
    	
    	//判断该考生是否在当前考场中
    	$this->load->model('exam/exam_place_model');
    	$exam_place_model = $this->exam_place_model;
    	$place_id = $this->session->userdata('exam_i_place_id');
    	$user_id = $student['uid'];
    	if (!$exam_place_model->check_exam_place_student($place_id, $user_id)) {
			output_json(CODE_ERROR, '很抱歉，该考生不在本场考试中，有问题请联系系统管理员.');
    	}
    	
    	//重置考生密码
    	try {
    		$this->student_model->reset_password($user_id, $password);
    		output_json(CODE_SUCCESS, '修改成功, 该考生考试信息为：<p><strong>准考证号：</strong>' . $exam_ticket . ' </p><p><strong>新密码为：</strong> ' . $password . '  </p><font color="red">请记下该考生新密码, 以防丢失.</font>');
    	} catch (Exception  $e) {
    		output_json(CODE_ERROR, '密码修改失败，请重试(如多次出现类似情况，请联系系统管理员)');
    	}
    }
    
    //====================监控数据获取=============================
    /**
     * 获取考试监控数据
     */
    public function ajax_test_logs()
    {
    	$data = $this->_data;
    	$exam_id = $this->session->userdata('exam_i_exam_id');
    	$place_id = $this->session->userdata('exam_i_place_id');
    	
    	$this->load->model('exam/exam_logs_model');
    	$this->load->model('exam/student_model');
    	$exam_logs_model = $this->exam_logs_model;
    	$student_model = $this->student_model;

    	$page = intval($this->input->post('page'));
    	$per_page = intval($this->input->post('per_page'));
    	$time_start = $this->input->post('t_start');
    	$time_end = $this->input->post('t_end');
    	$uid = intval($this->input->post('uid'));
    	$ticket = $this->input->post('ticket');//准考证号
    	$log_type = intval($this->input->post('log_type'));//事件类型
    	$show_important = $this->input->post('show_important');//是否显示重要事件
    	$last_log_id = intval($this->input->post('last_id'));//最新的log_id
    	$is_ajax = intval($this->input->post('is_ajax'));//是否为异步
    	$is_mini_mode = intval($this->input->post('mini_mode'));//是否为后台模式
    	
    	$page = $page <= 0 ? 1 : $page;
    	$per_page = $per_page <= 0 ? 20 : $per_page;
    	
    	$query = array(
    				'exam_id' => $exam_id,
    				'place_id' => $place_id
    	);
    	
    	$time_start && $query['ctime'][">="] = $time_start;
    	$time_end && $query['ctime']["<="] = $time_end;
    	$last_log_id > 0 && $query['log_id'] = array(">" => $last_log_id);
    	
    	if ($log_type <= 0) {
	    	//重要事件
	    	$important_events = array(EXAM_LOG_CHEAT, EXAM_LOG_RELOGIN_IN_TESTTING);
	    	$show_important == '1' && $query['type'] = $important_events;
    	} else {
    		$query['type'] = $log_type;
    	}
    	
    	//准考证条件优先
    	if (!is_numeric($ticket)) {
    		if ($uid > 0) {
	    		$uid > 0 && $query['uid'] = intval($uid);
    		} elseif($ticket && !is_numeric($ticket)) {
    			$query['uid'] = 0;
    		}
    	} else {
    		//根据准考证获取考生id
    		$ticket = trim($ticket);
    		$student = $student_model->get_student_by_exam_ticket($ticket, 'uid');
    		if (!$student) {
    			$query['uid'] = 0;
    		} else {
    			$query['uid'] = $student;
    		}
    	}
    	
    	if ($is_mini_mode == '1') {
    		$count_logs = $exam_logs_model->count_exam_log_lists($query);
    		$last_id = 0;
    		$json_data = '';
    	} else {
	    	$select_what = array('log_id', 'uid', 'ctime', 'type', 'content');
	    	$logs = $exam_logs_model->get_exam_log_list($query, $page, $per_page, 'log_id desc', $select_what, true);
	    	$data['logs'] = $logs;
	    	
	    	$tmp_last_id = count($logs) ? $logs[0]['log_id'] : 0;
	    	$last_id = $last_log_id > $tmp_last_id ? $last_log_id : $tmp_last_id;
	    	$data['is_ajax'] = $is_ajax;
	    	
	    	//当条件中有传考生准考证号或者uid时，没有日志数据需要判断该学生是否在当前考场中
	    	$tip_msg = null;
	    	if (!count($logs) && isset($query['uid'])) {
		    	$this->load->model('exam/exam_place_model');
		    	$is_valide_student = $this->exam_place_model->check_exam_place_student($query['place_id'], $query['uid']);
		    	if ($is_valide_student) {
		    		$tip_msg = ':) 该考生是当前考场考生，但貌似还没有动静.';
		    	} else {
		    		$tip_msg = ':) 该考生不在当前考场中.';
		    	}
	    	}
	    	
	    	$data['tip_msg'] = $tip_msg;
	    	
	    	$json_data = $this->load->view('invigilate/ajax_logs', $data, true);
	    	$count_logs = count($logs);
    	}
    	
    	if (isset($query['log_id'])) {unset($query['log_id']);}
    	
    	$total = $exam_logs_model->count_exam_log_lists($query);//总条数
    	$pages = ceil($total/$per_page);
    	$data = array(
    					'content' => $json_data, 
    					'last_id' => $last_id,
    					'page' 	  => $page,
    					'pageCount'=> $pages,//页数
    					'current_count' => $count_logs//当前页条数
    	);
    	
    	output_json(CODE_SUCCESS, '', $data);
    }
    
    /**
     * 获取考生的日志
     */
    public function student_logs()
    {
    	$this->load->model('exam/exam_logs_model');
    	$this->load->model('exam/student_model');
    	$exam_logs_model = $this->exam_logs_model;
    	$student_model = $this->student_model;
    
    	$uid = intval($this->input->get('uid'));
    	$query = array(
    			'exam_id' => $this->session->userdata('exam_i_exam_id'),
    			'place_id' => $this->session->userdata('exam_i_place_id')
    	);
    	 
    	$query['uid'] = intval($uid);
    	 
    	$select_what = array('ctime', 'type', 'content');
    	 
    	$logs = $exam_logs_model->get_exam_log_list($query, false, false, 'ctime desc', $select_what);
    	$data['logs'] = $logs;
    	 
    	$json_data = $this->load->view('invigilate/ajax_student_logs', $data, true);
    	 
    	$data = array(
    			'content' => $json_data,
    			'total'	  => count($logs)//总条数
    	);
    	 
    	output_json(CODE_SUCCESS, '', $data);
    }
    
    /**
     * 获取考场统计信息
     */
    public function statics()
    {
    	$data = array();
    	if ($this->session->userdata('exam_i_uid')) {
	    	$data_type = $this->input->get('data_type');
	    	
	    	$exam_id =  $this->session->userdata('exam_i_exam_id');
	    	$place_id = $this->session->userdata('exam_i_place_id');
	    	
	    	//将该考生从当前考场中退出
	    	$this->load->model('exam/student_log_stat_model', 'log_stat_model');
	    	
	    	$data_types = @explode(',', $data_type);
	    	if (is_array($data_types) && count($data_types)) {
		    	foreach ($data_types as $type) {
			    	if ($type == 'online') {
			    		//在考人数
			    		$count = $this->log_stat_model->count_exam_place_members($exam_id, $place_id);
			    		$data['online'] = $count ? $count : 0;
			    	}
			    	
			    	if ($type == 'submit') {
			    		//交卷人数
			    		$result = $this->db->query("select count(distinct(`uid`)) as count from {pre}exam_test_paper where exam_pid={$exam_id} and place_id={$place_id} and etp_flag <> 0")->result_array();
			    		$data['submit'] = $result[0]['count'];
			    	}
		    	}
	    	}
    	}
    	
    	output_json(CODE_SUCCESS, 'ok', $data);
    } 
}
    
