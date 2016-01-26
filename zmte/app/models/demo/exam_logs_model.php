<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 考试系统--考生日志--数据层
 * @author TCG
 * @final 2013-12-26
 */
class Exam_logs_model extends CI_Model 
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'exam_demo_logs';
	
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * 通过 条件 机考日志 记录列表
	 * @param array $query
	 * @param integer $page
	 * @param integer $per_page
	 * @param string $order_by
	 * @param string $select_what
	 */
	public function get_exam_log_list($query, $page = 1, $per_page = 20, $order_by = null, $select_what = null, $load_student = false)
	{
		try {
			$where = array();
			$bind = array();
	
			if (is_array($query) && count($query)) {
				foreach ($query as $key=>$val) {
					switch ($key) {
						case 'exam_id':
							if (is_array($val)) {
								$tmpStr = array();
								foreach ($val as $k=>$v) {
									$tmpStr[] = '?';
									$bind[] = intval($v);
								}
								$tmpStr = implode(', ', $tmpStr);
								$where[] = "exam_id in ({$tmpStr})";
							} else {
								$where[] = 'exam_id = ?';
								$bind[] = intval($val);
							}
							break;
						case 'log_id':
							if (is_array($val)) {
								foreach ($val as $k=>$v) {
									$where[] = "log_id {$k} ?";
									$bind[] = $v;
								}
							} else {
								$where[] = 'log_id = ?';
								$bind[] = $val;
							}
							break;
						case 'place_id':
							if (is_array($val)) {
								foreach ($val as $k=>$v) {
									$where[] = "place_id {$k} ?";
									$bind[] = $v;
								}
							} else {
								$where[] = 'place_id = ?';
								$bind[] = $val;
							}
							break;
						case 'etp_id':
							$where[] = 'etp_id = ?';
							$bind[] = $val;
							break;
						case 'uid':
							if (is_array($val)) {
								foreach ($val as $k=>$v) {
									$where[] = "uid {$k} ?";
									$bind[] = $v;
								}
							} else {
								$where[] = 'uid = ?';
								$bind[] = $val;
							}
							break;
						case 'type':
							if (is_array($val)) {
								$tmpStr = array();
								foreach ($val as $k=>$v) {
									$tmpStr[] = '?';
									$bind[] = intval($v);
								}
	
								$tmpStr = implode(', ', $tmpStr);
								$where[] = "type in ({$tmpStr})";
							} else {
								$where[] = 'type = ?';
								$bind[] = intval($val);
							}
	
							break;
						case 'ctime':
							if (is_array($val)) {
								foreach ($val as $k=>$v) {
									$where[] = "ctime {$k} ?";
									$bind[] = $v;
								}
							} else {
								$where[] = 'ctime = ?';
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
	
			if (count($data) && $load_student) {
				$this->load->model('demo/student_model');
				foreach ($data as $k => $v) {
					if (isset($v['uid'])) {
						$student = $this->student_model->get_student_by_id($v['uid'],  'first_name, last_name,exam_ticket');
						$data[$k]['truename'] = count($student) ? ($student['last_name'] . $student['first_name']) : '--';
						$data[$k]['exam_ticket'] = count($student) ? $student['exam_ticket'] : '--';
					}
				}
			}
			 
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
	public function count_exam_log_lists($query)
	{
		$result = $this->get_exam_log_list($query, null, null, null, 'count(*) as total');
	
		return count($result) ? $result[0]['total'] : 0;
	}
	
	/**
	 * 插入考生日志信息
	 * array $data
	  
	 * return boolean
	 */
	public function insert($type, $content = null, $uid = null)
	{
		// 关闭错误信息，防止 unique index 冲突出错
		$this->db->db_debug = false;
		
		$data = array('type' => $type);
		
		//补齐信息
		$this->load->model('demo/exam_model');
		$current_exam = $this->exam_model->get_session_current_exam(true);
		$data['exam_id'] = $current_exam['exam_id'];
		$data['place_id'] = $current_exam['place_id'];
		
		if (is_null($uid)) {
			$uid = intval($this->session->userdata('demo_exam_uid'));
			if (!$uid) {
				return false;
			}
		} else {
			$uid = intval($uid);
			if ($uid <= 0) {
				return false;
			}
		}
		
		$data['uid'] = $uid;
		
		$etp_id = $this->session->userdata('etp_ids');
		$etp_id && $data['etp_id'] = $etp_id;
		
		if (is_null($content)) {
			require_once (APPPATH.'config/app/demo/log_type_desc.php');
			$data['content'] = Log_type_desc::get_log_type_desc($data['type']);
		} else {
			$content = is_array($content) ? json_encode($content) : $content;
			$data['content'] = $content;
		}
		
		$data['ctime'] = date('Y-m-d H:i:s');
		
		$this->db->insert(self::$_table_name, $data);
		$res = $this->db->affected_rows();
	
		return $res > 0;
	}
}
