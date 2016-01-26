<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 考试系统--监考人员--数据层
 * @author TCG
 * @final 2013-11-13
 */
class Exam_invigilator_model extends CI_Model 
{
	/**
	 * 表名 
	 * @var string
	 */
	private static $_table_name = 'invigilator';
	private static $_table_invigilator_place = 'exam_place_invigilator';
	
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 
     * 通过 id 获取监考人员帐号 单条 记录
     * @param $id  
     * @param $item 待提取字段信息
     * @return mixed
     */
    public function get_invigilator_by_id($id = 0, $item = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $this->db->select($item);
        }
        $query = $this->db->get_where(self::$_table_name, array('invigilator_id' => $id));
        $row =  $query->row_array();
        if ($item && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }

    /**
     * 
     * 通过 email 获取监考人员帐号 单条 记录
     * @param $email 
     * @param $item
     * @return mixed
     */
    public function get_invigilator_by_email_pwd($email, $password, $item = NULL)
    {
        if ($item)
        {
            $this->db->select($item);
        }
        $query = $this->db->get_where(self::$_table_name, array('invigilator_email' => $email, 'invigilator_password' => $password));
        $row =  $query->row_array();
        if ($item && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }

    /**
     * 
     * 通过 条件 获取监考人员帐号 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     * 
     */
    public function get_invigilator_list($query, $page = 1, $per_page = 20, $order_by = null, $select_what = null)
    {
		try {
	    	$where = array();
	    	$bind = array();
	    	
            if (is_array($query) && count($query)) {
	    		foreach ($query as $key=>$val) {
	    			switch ($key) {
	    				case 'invigilator_id':
	    					if (is_array($val)) {
	    						$tmpStr = array();
	    						foreach ($val as $k=>$v) {
	    							$tmpStr[] = '?';
	    							$bind[] = intval($v);
	    						}
	    						$tmpStr = implode(', ', $tmpStr);
    							$where[] = "invigilator_id in ({$tmpStr})";
	    					} else {
		    					$where[] = 'invigilator_id = ?';
		    					$bind[] = intval($val);
	    					}
	    					break;
	    				case 'invigilator_email':
	    					if (is_array($val)) {
	    						foreach ($val as $k=>$v) {
	    							$where[] = "invigilator_email {$k} ?";
	    							$bind[] = $v;
	    						}
	    					} else {
		    					$where[] = 'invigilator_email = ?';
		    					$bind[] = $val;
	    					}
	    					break;
	    				case 'password':
	    					$where[] = 'invigilator_password = ?';
	    					$bind[] = $val;
	    					break;
                        case 'invigilator_name':
                            if (is_array($val)) {
                                foreach ($val as $k=>$v) {
                                    $where[] = "invigilator_name {$k} ?";
                                    $bind[] = $v;
                                }
                            } else {
                                $where[] = 'invigilator_name = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'invigilator_flag':
                            if (is_array($val)) {
                                $tmpStr = array();
                                foreach ($val as $k=>$v) {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }

                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "invigilator_flag in ({$tmpStr})";
                            } else {
                                $where[] = 'invigilator_flag = ?';
                                $bind[] = intval($val);
                            }

                            break;
	    				case 'invigilator_addtime':
	    					if (is_array($val)) {
	    						foreach ($val as $k=>$v) {
	    							$where[] = "invigilator_addtime {$k} ?";
	    							$bind[] = $v;
	    						}
	    					} else {
		    					$where[] = 'invigilator_addtime = ?';
		    					$bind[] = $val;
	    					}
	    					break;
	    				case 'invigilator_updatetime':
	    					if (is_array($val)) {
	    						foreach ($val as $k=>$v) {
	    							$where[] = "invigilator_updatetime {$k} ?";
	    							$bind[] = $v;
	    						}
	    					} else {
		    					$where[] = 'invigilator_updatetime = ?';
		    					$bind[] = $val;
	    					}
	    					break;
	    				default:
	    					break;    				
	    			}
	    		}
	    	}
	    
	    	$select_what = is_string($select_what) ? (array) $select_what : $select_what;
	    	$select_what = count($select_what) ? implode(', ', $select_what) : '*';
	    	
            $where = count($where) ? ("where " . implode(' and ', $where)) : '';
	    	$order_by = !is_null($order_by) ? 'order by ' . $order_by : '';
	    	$group_by = '';
	    	
	    	$limit = '';
	    	$page = intval($page);
	    	if ($page > 0) {
	    		$per_page = intval($per_page);
		    	$start = ($page - 1) * $per_page;
				$limit = " limit {$start}, {$per_page}";
	    	}
	    	
	    	$table_name = $this->db->dbprefix(self::$_table_name);
	    	$sql = "SELECT {$select_what} FROM " . $table_name . " {$where} {$order_by} {$group_by} {$limit}";
			//echo $sql;die;
			$query = $this->db->query($sql, $bind);
			$data = $query->result_array();
            			
			return $data;
			
    	} catch (Exception $e) {
//    		echo $e->getMessage();die;
    		throw new Exception($e->getMessage());
    	}
    }

    /**
     * 
     * 通过 条件 获取条数
     * @param array $query
     */
    public function count_invigilator_lists($query) 
    {
        $result = $this->get_invigilator_list($query, null, null, null, 'count(*) as total');
        
        return count($result) ? $result[0]['total'] : 0;
    }
    
   /**
    * 重置监考人员密码 
    * @param string $invigilator_id
    * @param string $new_password
    * @return boolean
    */ 
    public function reset_invigilator_password($invigilator_id, $new_password) 
    {
    	$invigilator_id = intval($invigilator_id);
    	if ($invigilator_id <= 0) {
    		return false;
    	}
    	
    	try {
    		$update_data = array(
    					'invigilator_updatetime' => time(),
    					'invigilator_password' => $new_password,
    		);
			$this->db->where('invigilator_id', $invigilator_id)->update(self::$_table_name, $update_data);
    		 
    		return true;
    	} catch(Exception $e) {
    		return false;
    	}
    } 
    
    //============================监考人员会话相关业务方法===============================//
    /**
     * 判断监考人员是否已经登录
     */
    public function check_invigilator_is_login()
    {
    	if (!$this->session->userdata('exam_i_uid')) {
    		message('请先登录.', 'demo/invigilate/login/');
    	}
    }
    
    /**
     * 检查该监考人员是否在当前考场中
     */
    public function check_exist_current_place_invigilator()
    {
    	$this->load->model('demo/exam_model');
    	$this->load->model('demo/exam_place_model');
    	$this->load->model('demo/school_model');
    	
    	$school_model = $this->school_model;
    	$exam_model = $this->exam_model;
    	$exam_place_model = $this->exam_place_model;
    	
    	//检查已登录监考人员 的场次是否已过期
    	$invigilator_id = $this->session->userdata('exam_i_uid');
    	$i_place_id = $this->session->userdata('exam_i_place_id');
    	$i_place_end_time = $this->session->userdata('exam_i_place_end_time');
    	
    	$segment_action = $this->uri->rsegment(2);
    	$except_actions = array('login', 'check_login', 'logout');
    	if (!in_array($segment_action, $except_actions) && 
    		$invigilator_id && $i_place_id && $i_place_end_time) {
    		if (time() >= $i_place_end_time) {
    			$this->destory_invigilator_session();
    			message('对不起，您监考的场次已经结束，有问题请联系系统管理员.', 'demo/invigilate/login');
    		}
    	}
    	
    	//获取考场信息
    	$exam_config = C('exam_config', 'app/demo/website');
    	 
    	//考试等待
    	$time_start = $exam_config['time_period_limit']['invigilator']['before_start'];
    	$time_end = $exam_config['time_period_limit']['invigilator']['after_end'];
    	 
    	$time_min = time() + $time_start*60;//考试起始时间点
    	$time_max = time() - $time_end*60;  //考试终结时间点
    	 
    	$where = array(
    			'ip'	  	  => $this->input->ip_address(),
    			'period_time' => array($time_min, $time_max)
    	);
    	 
    	$select_what = array(
    			'exam_pid',
    			'place_id',
    			'place_name',
    			'start_time',
    			'end_time',
    			'address',
    			'school_id'
    	);
    	
    	if ($invigilator_id && $i_place_id) {
	    	$places = $exam_place_model->get_exam_place_list(array('place_id' => $i_place_id), false, false, $select_what);
    	} else {
	    	$order_by = 'start_time asc';
	    	$places = $exam_place_model->get_exam_place_list($where, $order_by, false, $select_what);
    	}
    	if (!count($places)) {
    		message('对不起，系统当前时间段没有找到相应的考试呢.', 'demo/invigilate/login');
    	}
    	
    	//根据exam_pid归档
    	$exam_places = array();
    	$exam_subjects = array();
    	foreach ($places as $place) {
    		//补齐考场下的考试学科
    		$subjects = array();
    		$tmp_subject = $exam_place_model->get_exam_place_subject($place['place_id']);
    		if (is_array($tmp_subject) && count($tmp_subject)) {
    			foreach ($tmp_subject as $sub) {
    				//按考场ID归档
    				$place_id = $place['place_id'];
    				$sub['place_id'] = $place['place_id'];
    				$exam_subjects[$place['exam_pid']][$place_id][] = $sub;
    			}
    		}
    		
    		//获取该考场下的考生人数
    		$student_amount = $exam_place_model->count_exam_place_students($place['place_id']);
    		$place['student_amount'] = $student_amount;
    		
    		//允许监考人员操作时间范围
    		$place['c_start_time'] = $place['start_time'] - $time_start*60;
    		$place['c_end_time'] = $place['end_time'] + $time_end*60;
    		
    		//补充考生允许的活动范围(考试等待)
    		$time_start_minutes = $exam_config['time_period_limit']['wait']['start'];
    		$place['student_time_start'] = $place['start_time'] - 60*$time_start_minutes;
    		
    		//获取该考场所在的学校
    		$school = $school_model->get_school_by_id($place['school_id'], 'school_name');
    		$place['school_name'] = $school;
    		
    		$exam_places[$place['exam_pid']][] = $place;
    	}
    	 
    	//获取期次信息
    	$select_what = array(
    			'exam_id',
    			'exam_name',
    			'introduce',
    			'exam_type',
    			'invigilate_notice',
    			'status',
    	);
    	$exam_ids = array_keys($exam_places);
    	$exam_data = array();
    	foreach ($exam_ids as $id) {
    		$exam = $exam_model->get_exam_by_id($id, $select_what);
    		if (count($exam) && $exam['status'] == '1') {
    			$exam['subject'] = isset($exam_subjects[$id]) ? $exam_subjects[$id] : array();
    			$exam['place'] = $exam_places[$id];
    	
    			$exam_data[] = $exam;
    		}
    	}
    	
    	if (!count($exam_data)) {
    		message('对不起，系统当前时间段没有找到相应的考试呢.', 'demo/invigilate/login');
    	}
    	
		return $exam_data;
	}
	
    /**
     * 验证是否为合法的监考人员身份
     * @param string $email
     * @param string $password
     */
    public function is_validate_invigilator($email, $password)
    {
		$invigilator = $this->get_invigilator_by_email_pwd($email, $password, 'invigilator_id, invigilator_name, invigilator_flag');
		if (!count($invigilator)) {
			throw new Exception('用户名或密码错误.');
		}
		if ($invigilator['invigilator_flag'] <= 0) {
			throw new Exception('该监考人员帐号已被管理员禁用或删除.');
		}
		
		return $invigilator;
    }
    
    /**
     * 设置监考人员登录session
     */
    public function set_invigilator_place_session($invigilator, $place)
    {
    	if (!is_array($invigilator) || !count($invigilator) || !count($place)) {
    		return false;
    	}
    	
    	$session_userdata = array(
    			'exam_i_uid' => $invigilator['invigilator_id'],
    			'exam_i_uname' => $invigilator['invigilator_name'],
    			'exam_i_exam_id' => $place['exam_pid'],
    			'exam_i_place_id' => $place['place_id'],
    			'exam_i_place_start_time' => $place['start_time'],
    			'exam_i_place_end_time' => $place['end_time'],
    			'exam_i_place_ip' => $place['ip'],
    	);
    	
    	$this->session->set_userdata($session_userdata);
    }
    
    /**
     * 清除监考人员登录session
     */
    public function destory_invigilator_session()
    {
    	$unset_userdata = array(
    			'exam_i_uid' 	=> '',
    			'exam_i_uname' 	=> '',
    			'exam_i_exam_id' => '',
    			'exam_i_place_id' => '',
    			'exam_i_place_start_time' => '',
    			'exam_i_place_end_time' => '',
    			'exam_i_place_ip' => '',
    	);
    	
    	$this->session->unset_userdata($unset_userdata);
    }
    
    //============================监考人员 与考场关系 相关业务方法===============================//
	/**
	 * 获取监考人员所在的考场
	 * @param integer $place_id
	 * @param integer $invigilator_id
	 */
    public function get_exam_place_invigilator($place_id, $invigilator_id = null, $select_what = null)
    {
    	$place_id = intval($place_id);
    	if ($place_id <= 0) {
    		return array();
    	}
    	
    	$where = array(
    			'place_id' => $place_id 	
    	);
    	
    	$invigilator_id = intval($invigilator_id);
    	if ($invigilator_id > 0) {
    		$where['invigilator_id'] = $invigilator_id;
    	}
    	
    	$select_what = is_null($select_what) ? '*' : $select_what;
    	$this->db->select($select_what);
    	
    	$query = $this->db->get_where(self::$_table_invigilator_place, $where);
    	
    	return $query->row_array();
    }

    /**
     * 判断某个监考老师是否在某个考场中
     * @param integer $place_id
     * @param integer $invigilator_id
     */
    public function is_validate_exam_place_invigilator($place_id, $invigilator_id)
    {
    	$place_invigilator = $this->get_exam_place_invigilator($place_id, $invigilator_id);
    	if (!count($place_invigilator)) {
    		return false;
    	}
    	
    	return true;
    }
}
/* End of file exam_invigilator_model.php */
/* Location: ./application/models/exam_invigilator_model.php */
