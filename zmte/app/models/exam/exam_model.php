<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-考试期次数据模块
 *
 * @author qcchen
 * @final 2013-12-04
 */
class Exam_model extends CI_Model
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'exam';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 按 考试期次id 获取一个考试信息
     *
     * @param   int     考试期次ID(exam_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_exam_by_id($id = 0, $select_items = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }

        if ($select_items)
        {
            $this->db->select($select_items);
        }

        $query = $this->db->get_where(self::$_table_name, array('exam_id' => $id), 1);
        $row =  $query->row_array();
        if (is_string($select_items) && $select_items && isset($row[$select_items]))
            return $row[$select_items];
        else
            return $row;
    }

    /**
     * 获取当前考试期次
     *
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  array
     */
    public function get_current_exam($select_items = NULL)
    {
		$result = $this->get_exam_list(array('exam_pid'=>0, 'status'=>1), 'exam_id ASC', 1, $select_items);
		if ($result)
			$result = $result[0];
		return $result;
    }

    /**
     * 按条件获取考试期次列表
     *
     * @param   array		查询条件
     * @param	string		排序
     * @param	int			获取数据数量
     * @param	string/array	获取字段
     * @return  array
     */
    public function get_exam_list($condition = array(), $order_by=NULL, $limit=NULL, $select_items=NULL)
    {
    	// selects
    	if (is_array($select_items))
    		$select_items = implode(',', $select_items);
    	if ($select_items)
    		$this->db->select($select_items);

    	if ($condition && is_array($condition))
    	{
    		foreach ($condition as $key => $val)
    		{
    			switch ($key)
    			{
	    			case 'exam_pid' :
	    			case 'subject_id' :
	    			case 'grade_id' :
	    			case 'class_id' :
	    			case 'status' :
	    				$this->db->where($key, $val);
	    				break;
	    			default:
	    				break;
    			}
    		}
    	}

    	// order by
    	if ($order_by)
    		$this->db->order_by($order_by);

    	// limit
    	if ($limit)
    		$this->db->limit($limit);

    	// get data
    	$query = $this->db->get(self::$_table_name);
    	return $query->result_array();
    }


    /**************************业务方法**********************************/

    /**
     * 获取当前进行中考试
     */
    function get_cookie_current_exam($required = false)
    {
    	//从cookie中获取考试信息
    	$cookie_exam = $this->session->userdata('zeming_exam_test');


    	if ($required === true) {
    		if (!$cookie_exam) {
    			message('发生异常了，可能原因：<p>1、未选择考场信息;</p><p>2、您的浏览器禁止了cookie使用（请联系网管）;</p>', 'exam/index/index');
    		}
    	}

    	$cookie_exam = trim($cookie_exam, '"');

    	$exam = (Array) json_decode($cookie_exam);


/*
    	if (isset($exam['place_id']))
    	{

        	$this->load->model('exam/exam_place_model');
        	$exam_info = $this->exam_place_model->get_exam_place_by_id($exam['place_id'], 'place_id, end_time');
        	$exam_config = C('exam_config', 'app/exam/website');

        	$time_end = $exam_config['time_period_limit']['submit'];
        	$submit_time_period = $time_end*60;
        	$exam_info['finish_time'] = date('Y-m-d H:i:s', $exam_info['end_time'] +$submit_time_period);

        	$exam_info['end_time'] = date('Y-m-d H:i:s', $exam_info['end_time']);


        	//检查 当前时间段  是否存在 考试
        	//获取考场信息

        	/** 是否需要更新考场时间 */
/*
        	if ($exam_info['end_time'] != $exam['end_time']) {
        	    /** 更新cookie信息 */
    	/*
        	    $exam['end_time'] = $exam_info['end_time'];
        	    $exam['finish_time'] = $exam_info['finish_time'];
        	}

    	}
    	*/

    	//检查是否存在学科，如果没有学科，则重新拉取
    	if (isset($exam['place_id']) && (!isset($exam['subject_id']) || trim($exam['subject_id']) == '')) {



    		$subject_names = array();
    		$subject_ids = array();
    		$this->load->model('exam/exam_place_model');
    		$tmp_subject = $this->exam_place_model->get_exam_place_subject($exam['place_id']);

    		foreach ($tmp_subject as $sub) {
    			$subject_ids[] = $sub['subject_id'];
    			$subject_names[] = $sub['subject_name'];
    		}

    		$exam['subject_id'] = implode(',', $subject_ids);
    		$exam['subject_name'] = implode(',', $subject_names);

    		$exam['student_notice'] = $this->get_exam_by_id($exam['exam_pid'], 'student_notice');

    	//	$expire = strtotime($exam['finish_time']) - time();
    		//$domain=C('cookie_domain');
    		$this->session->set_userdata('zeming_exam_test', json_encode($exam));


    	}

    	return $exam;
    }

    /**
     * 检查考试状态
     * 1、是否已结束
     * 2、是否学生已交卷
     */
    function check_exam_status()
    {
    	$segment_controller = $this->uri->rsegment(1);
    	$segment_action = $this->uri->rsegment(2);
    	//如果考生已经交卷，转到成功，提交成功等待页
    	if ($this->is_valid_test_submit_session() && ($segment_controller != 'test' && $segment_action != 'success')) {
    		$this->_redirect_test_success();
    	}

    	//必须先选择考场
    	$current_exam = $this->get_cookie_current_exam();
    	if (!$current_exam && ($segment_controller != 'index' && $segment_action != 'index')) {
    		redirect('exam/index/index');
    	}


    	if (!$current_exam && !($uid = $this->session->userdata('exam_uid'))) {

    		return false;
    	}

    	//检查本场考试是否已经结束（本场考试结束时间 + 缓冲时间）
    	$finish_time = strtotime($current_exam['finish_time']);
    	if (!$finish_time || $finish_time < time()) {
    		//清除所有关联会话
    		$this->destory_exam_session();

    		message('抱歉，本场考试已经结束.', 'exam/index/index');
    	}

    	//检查本场考试是否已经结束(本场考试结束时间)
    	$end_at = strtotime($current_exam['end_time']);
    	if ($end_at < time() && ($segment_controller != 'index' && $segment_action != 'login')) {
    		//预留 30秒钟交卷
    		if (time() <= ($end_at + 30) && ($segment_controller == 'test' && $segment_action == 'submit')) {
    			return false;
    		}

    		//清除考生会话信息
    		$this->load->model('exam/student_model');
    		$this->student_model->destory_exam_student_session();

    		message('抱歉，本场考试已经结束.', 'exam/index/login');
    	}

    	//检查该考生是否已经交卷
    	$this->load->model('exam/exam_test_paper_model');

    	$uid = $this->session->userdata('exam_uid');

    	if($uid>0)
    	{
    	        $student_place_status = $this->exam_test_paper_model->get_student_place_status($current_exam['place_id'], $uid);
    	        if($student_place_status['flag'] !== false && $student_place_status['flag'] != 0 && ($segment_controller != 'index' && $segment_action != 'login'))
    	        {
    	    //踢出行为
    		        if ($student_place_status['flag'] == 1)
    		        {
	    		        message("抱歉，您在该场考试中有".$student_place_status['why']."行为被踢出，本次考试无效.", 'exam/index/login');
    		        }

    	       }

    	        $student_test_status = $this->exam_test_paper_model->get_student_test_status($current_exam['place_id'], $uid);
               if ($student_test_status !== false && $student_test_status != 0
                       && ($segment_controller != 'index' && $segment_action != 'login'))
    	         {

            		//清除考生会话信息
            		$this->load->model('exam/student_model');
            		$this->student_model->destory_exam_student_session();
        	    	//作弊行为
            		if ($student_test_status < 0)
            		{
        	    		message('抱歉，您在该场考试中有作弊行为，本次考试无效.', 'exam/index/login');
            		}
            		else
            		{
        	    		message('抱歉，您已经交卷了.', 'exam/index/login');
            		}
    	        }
    	        else
    	        {
    		        //判断该考生是否有离开考试界面嫌疑
            	     if($uid)
            	     {
                    		 $this->load->model('exam/student_log_stat_model', 'log_stat_model');
                			 if ($this->log_stat_model->has_beyond_active_time($current_exam['exam_id'], $current_exam['place_id'], $uid))
                			 {
                				//添加考生日志,如果系统已经添加了该日志记录，则跳过
                				$log_type = EXAM_LOG_LEAVE_TEST_PAGE;
                				$exam_pid = $current_exam['exam_id'];
                				$place_id = $current_exam['place_id'];
                				$last_active_time = $this->log_stat_model->get_student_last_active_time($current_exam['exam_id'], $current_exam['place_id'], $uid);
                				$result = $this->db->query("select count(log_id) as count from {pre}exam_logs where exam_id={$exam_pid} and place_id={$place_id} and uid={$uid} and type={$log_type} and ctime>='{$last_active_time}'")->row();
                				if (!$result->count)
                				{
                					exam_log($log_type);
                				}
                			}

            	      }
    	        }

    	}
    }

    /**
     * 考试交卷成功 重定向
     */
    function _redirect_test_success()
    {
    	if (!$this->is_valid_test_submit_session()) {
    		redirect('exam/index/index');
    	}

    	$data = array();

    	//页面倒计时刷新时间
    	$exam_config = C('exam_config', 'app/exam/website');
    	//     	$data['refresh'] = $exam_config['refresh_time']['submit_success'];
    	/** $data['end_time'] = $this->session->userdata('last_exam_end_time'); */

    	$data['system_name'] = isset($exam_config['system_name'])
    	? $exam_config['system_name'] : (C('webconfig')['site_name'] .  '上机考试系统');
    	$data['current_exam'] = $this->get_cookie_current_exam(true);

        /** 修正考试时间 */
        $this->load->model('exam/exam_place_model');
        $exam_info = $this->exam_place_model->get_exam_place_by_id($palce_id, 'place_id, end_time');
        $data['end_time'] = date('Y-m-d H:i:s', $exam_info['end_time']);

    	//是否有作弊行为
    	$has_cheated = $this->session->userdata('has_cheated');
    	$data['has_cheated'] = $has_cheated;

    	$data['page_title'] = $has_cheated ? '您在本场考试中作弊，有任何问题请联系监考老师' : '恭喜您，交卷成功';

    	echo $this->load->view('test/submit_success', $data, true);
    	exit();
    }

    /**
     * 清除机考所有会话
     */
    function destory_exam_session()
    {
    	//清除考试cookie信息
    	$this->load->helper('cookie');

    	set_cookie('zeming_exam_test', '', time()-1, '', '/');

    	//其他相关session
    	$unset_other_items = array(
    			'has_cheated'		=> '',
    			'etp_ids'			=> '',
    	);

    	$this->session->unset_userdata($unset_other_items);

    	//清除考生会话信息
    	$this->load->model('exam/student_model');
    	$this->student_model->destory_exam_student_session();
    }

    /**
     * 设置交卷成功session
     */
    function set_test_submit_session()
    {
    	//设置考生交卷标志
    	$current_exam = $this->get_cookie_current_exam(true);
    	$this->session->set_userdata(array('last_exam_end_time' => $current_exam['end_time']));
    }

    /**
     * 将考生的考试记录保存到session中
     */
    function set_test_paper_sessoin($etp_ids)
    {
		if (!(is_array($etp_ids) && count($etp_ids))) {
			return false;
		}
    	//设置考生交卷标志
    	$etp_ids = is_array($etp_ids) ? implode(',', $etp_ids) : $etp_ids;
    	$this->session->set_userdata(array('etp_ids' => $etp_ids));
    }

    /**
     * 设置交卷成功session
     */
    function is_valid_test_submit_session()
    {
    	//设置考生交卷标志
    	$last_exam_end_time = $this->session->userdata('last_exam_end_time');
    	if (!$last_exam_end_time) {
    		return false;
    	}

    	if (time() >= strtotime($last_exam_end_time)) {
    		$this->session->unset_userdata('last_exam_end_time');
    		return false;
    	}

    	return true;
    }
}
/* End of file exam_model.php */
/* Location: ./application/models/exam/exam_model.php */
