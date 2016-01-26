<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-学生数据模块
 *
 * @author qcchen
 * @final 2013-12-04
 */
class Student_model extends CI_Model 
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'student';
	
	/**
	 * construct
	 */	
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 按 id 获取一个学生信息
     *
     * @param   int     学生ID(uid)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_student_by_id($id, $item = NULL)
    {
    	return $this->_get_student_by_unique('uid', $id, $item);
    }
    
    /**
     * 按  Email 获取一个学生信息
     */
    public function get_student_by_email($email, $item = NULL)
    {
    	return $this->_get_student_by_unique('email', $email, $item);
    }
    
    /**
     * 按  准考证(exam_ticket)  获取一个学生信息
     */
    public function get_student_by_exam_ticket($exam_ticket, $item = NULL)
    {
    	return $this->_get_student_by_unique('exam_ticket', $exam_ticket, $item);
    }
    
    /**
     * 检查 学生的  准考证(exam_ticket)/邮箱(email) 和 登录密码是否正确
     * @param string $keyword
     * @param string $password
     * @return boolean|array
     */
    public function is_valid_student($keyword, $password = null)
    {
    	$items = array(
    					'uid',
    					'email',
    					'first_name',
    					'last_name',
    					'exam_ticket',
    					'grade_id',
    					'sex',
    					'picture',
    					'school_id',
    					'password',
    	);
    	$this->db->select($items);
        if (is_email($keyword))
        {
    	    //验证 邮箱
    	    $query = $this->db->get_where(self::$_table_name, array('email' => $keyword), 1);
    	}
    	else if (is_idcard($keyword))
    	{
    	    //验证 邮箱
    	    $query = $this->db->get_where(self::$_table_name, array('idcard' => $keyword), 1);
    	}
        else if (is_numeric($keyword)) {
    	    //验证 准考证
    	    $query = $this->db->get_where(self::$_table_name, array('exam_ticket' => $keyword), 1);
    	}
    	else
	    {
	        return false;
	    }
    	
    	$row =  $query->row_array();
    	if (!count($row)) {
    		return false;
    	}
    	
    	if (is_null($password)) {
    		return $row;
    	}
    	
    	if ($row['password'] != my_md5($password)) {
	    	return false;
    	}
    	
		unset($row['password']);
		return $row;
    }
    
    /**
     * 修改学生登录密码
     *
     * @param   int  	学生id
     * @param   string 	新密码（已加密）
     * @return  boolean
     */
    public function reset_password($uid, $password)
    {
    	return $this->_update($uid, array('password' => my_md5($password)));
    }

    /**
     * 按 UNIQUE/PRIMARY KEY 获取一个学生信息
     *
     * @param   string  字段名称
     * @param   string  字段值
     * @param	string	需要获取的字段值
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    private function _get_student_by_unique($key, $val, $item = NULL)
    {
        if ($item)
            $this->db->select($item);
        
        $query = $this->db->get_where(self::$_table_name, array($key => $val), 1);
        $row =  $query->row_array();
        if ($item && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }
    
    /**
     * 修改学生信息
     *
     * @param   int  	学生id
     * @param   array   更新字段数组
     * @return  boolean
     */
    private function _update($uid, $data)
    {
    	return $this->db->update(self::$_table_name, $data, array('uid'=>$uid));
    }
    
    /**
     * 设置考生的 会话
     */
    function set_exam_student_session($student)
    {
    	$unset_items = array(
    					'exam_uid'		=> $student['uid'],
    					'exam_ticket'	=> $student['exam_ticket'],
    					'school_name' 	=> $student['school_name'],
    					'grade_name' 	=> $student['grade_name'],
    					'uid' 			=> $student['uid'],
    					'first_name'	=> $student['first_name'],
    					'last_name'		=> $student['last_name'],
    					'picture'		=> $student['last_name'],
    					'school_id'		=> $student['school_id'],
    			);
    	
    	$this->session->set_userdata($unset_items);
    }
    
    /**
     * 清除考生的 会话
     */
    function destory_exam_student_session($clear_window_blur = true)
    {
    	//将该考生从当前考场中退出
    	$this->load->model('exam/student_log_stat_model', 'log_stat_model');
    	$this->log_stat_model->remove_exam_place_member();
    	
    	//清除考生在考试期间离开考试界面记录
    	if ($clear_window_blur) {
	    	$this->log_stat_model->remove_student_window_blur_count();
    	}
    	
    	//清除考生的活跃时间
    	$this->log_stat_model->remove_student_last_active_time();
    	
    	$unset_items = array(
    					'exam_uid'		=> '',
		    			'exam_ticket'	=> '',
		    			'school_name' 	=> '',
		    			'grade_name' 	=> '',
		    			'uid' 			=> '',
    					'first_name'	=> '',
    					'last_name'		=> '',
    					'picture'		=> '',
    					'school_id'		=> '',
    			);
    	
    	$this->session->unset_userdata($unset_items);
    }
}

/* End of file student_model.php */
/* Location: ./application/models/exam/student_model.php */
