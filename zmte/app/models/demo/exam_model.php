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
     * 根据考试期次 和 学科获取对应的考试科目信息
     *
     * @param   int     考试期次ID(exam_pid)
     * @param   int     学科ID(subject_id)
     */
    public function get_exam_by_subject_id($exam_pid = 0, $subject_id = 0 , $select_items = NULL)
    {
        if ($select_items)
        {
            $this->db->select($select_items);
        }
        $query = $this->db->get_where(self::$_table_name, array('exam_pid' => $exam_pid, 'subject_id' => $subject_id), 1);
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
    function get_session_current_exam($required = false)
    {
    	//从cookie中获取考试信息
    	$exam = $this->session->all_userdata();
    	if ($required === true) {
    		if (!count($exam)) {
    			message('发生异常了，可能原因：<p>您的浏览器未启用cookie，请先启用</p>', 'demo/index/login');
    		}
    	}

    	$data = array();
    	if (isset($exam['exam_pid']))
    	{
    		$data = array(
	    				'exam_pid' 		=> $exam['exam_pid'],
		    			'place_id' 		=> $exam['place_id'],
		    			'exam_name' 	=> $exam['exam_name'],
		    			'introduce' 	=> $exam['introduce'],
		    			'student_notice'=> $exam['student_notice'],
		    			'exam_type' 	=> $exam['exam_type'],
		    			'subject_name' 	=> $exam['subject_name'],
		    			'subject_id' 	=> $exam['subject_id'],
		    			'exam_id' 		=> $exam['exam_id'],
    		            'grade_id'      => $exam['grade_id']
			    	);
    	}

    	if (isset($exam['uid']))
    	{
    		$data['uid'] = $exam['uid'];
    	}

    	return $data;
    }

    /**
     * 检查考试状态
     * 1、是否已结束
     * 2、是否学生已交卷
     */
    function check_exam_status($subject_id)
    {
    	$segment_controller = $this->uri->rsegment(1);
    	$segment_action = $this->uri->rsegment(2);

    	//如果考生已经交卷，转到成功，提交成功等待页
    	$report_mark = $this->session->userdata('report_mark');
    	if ($report_mark != '' && ($segment_controller != 'test' && $segment_action != 'report')) {
    		redirect('demo/test/report?act=doing');
    	}

    	$current_exam = $this->get_session_current_exam();
    	if (!isset($current_exam['exam_pid']))
    	{
    		redirect('demo/index/login?subject_id='.$subject_id);
    	}

    	//检查该考生是否已经交卷
    	$this->load->model('demo/exam_test_paper_model');
    	$uid = $this->session->userdata('demo_exam_uid');
    	$student_test_status = $this->exam_test_paper_model->get_student_test_status($current_exam['place_id'], $uid);
    	if ($student_test_status !== false && $student_test_status != 0 && ($segment_controller != 'index' && $segment_action != 'login')) {
    		//清除考生会话信息
    		$this->load->model('demo/student_model');
    		$this->student_model->destory_exam_student_session();

	    	//作弊行为
    		if ($student_test_status < 0) {
	    		message('很遗憾，您在本场考试中有作弊行为，无法继续考试.', 'demo/index/login');
    		} else {
    			if ($this->session->userdata('report_mark'))
    			{
		    		message('抱歉，您已经交卷了.', 'demo/test/report?act=get');
    			}
    			else
    			{
		    		message('抱歉，您已经交卷了.', 'demo/index/login?subject_id='.$subject_id);
    			}
    		}
    	} else {
    		//判断该考生是否有离开考试界面嫌疑
    		$this->load->model('demo/student_log_stat_model', 'log_stat_model');
			if ($this->log_stat_model->has_beyond_active_time($current_exam['exam_id'], $current_exam['place_id'], $uid)) {
				//添加考生日志,如果系统已经添加了该日志记录，则跳过
				$log_type = EXAM_LOG_LEAVE_TEST_PAGE;
				$exam_pid = $current_exam['exam_id'];
				$place_id = $current_exam['place_id'];
				$last_active_time = $this->log_stat_model->get_student_last_active_time($current_exam['exam_id'], $current_exam['place_id'], $uid);
				$result = $this->db->query("select count(*) as count from {pre}exam_logs where exam_id={$exam_pid} and place_id={$place_id} and uid={$uid} and type={$log_type} and ctime>='{$last_active_time}'")->row();
				if (!$result->count) {
					demo_exam_log($log_type);
				}
			}
    	}
    }

    /**
     * 考试交卷成功 重定向
     */
    function _redirect_test_success($subject_id)
    {
    	$data = array();

    	//页面倒计时刷新时间
    	$exam_config = C('exam_config', 'app/demo/website');
    	$data['current_exam'] = $this->get_session_current_exam(true);

    	//是否有作弊行为
    	$has_cheated = $this->session->userdata('has_cheated');
    	$data['has_cheated'] = $has_cheated;

    	$data['page_title'] = $has_cheated ? '很遗憾，您在本场考试中有作弊行为，无法继续考试.' : '恭喜您，交卷成功';

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

    	set_cookie('zeming_exam_demo', '', time()-1, '', '/');

    	//其他相关session
    	$unset_other_items = array(
    			'has_cheated'		=> '',
    			'etp_ids'			=> '',
    	);

    	$this->session->unset_userdata($unset_other_items);

    	//清除考生会话信息
    	$this->load->model('demo/student_model');
    	$this->student_model->destory_exam_student_session();
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

}
/* End of file exam_model.php */
/* Location: ./application/models/demo/exam_model.php */
