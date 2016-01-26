<?php
/**
 * 考生临时日志监控
 * @author TCG
 * @create 2013-12-26
 */
class Student_log_stat_model extends CI_Model {
	
	private $redis_model = null;
	public function __construct() 
	{
		parent::__construct();
		$redis_config = C('redis', 'redis');
		$this->set_redis();
	}
	
	public function set_redis()
	{
		$this->load->add_package_path(APPPATH  . "third_party/");
		$this->load->library("myredis", "", 'redis');
	
		$this->redis_model = $this->redis->get_redis();

		$this->pre= $redis_config['exam'];
	}
	
	/**
	 * 添加考场在考人员统计
	 * @return mixed
	 */
	public function set_exam_place_member($exam_id = 0, $place_id = 0, $uid = 0)
	{
		$exam_id = intval($exam_id);
		$place_id = intval($place_id);
		$uid = intval($uid);
		
		if ($uid <= 0) {
			return false;
		}
		
		/**
		 * source: redis
		 * key: exam:place:student:testting-exam_id:place_id
		 * type: sets
		 * value : uid
		 */	
		
		$redis = $this->redis_model;
		
		$key = $this->pre."exam:place:student:testting-{$exam_id}:{$place_id}";
		$val = $uid;
		
		if ($redis->sismember($key, $uid)) {
			//添加日志
//			exam_log(EXAM_LOG_RELOGIN_IN_TESTTING, null, $uid);
//			throw new Exception('抱歉，该考生已经在考试中.');
		} else {
			$redis->sadd($key, $uid);
		}
		
		
		return true;
	}
	
	/**
	 * 移除考场在考人员
	 */
	public function remove_exam_place_member($exam_id = 0, $place_id = 0, $uid = 0)
	{
		$exam_id = intval($exam_id);
		$place_id = intval($place_id);
		$uid = intval($uid);
		if ($this->session->userdata('exam_uid') || ($exam_id && $place_id && $uid)) {
			if (!($exam_id && $place_id && $uid)) {
				$current_exam = $this->exam_model->get_cookie_current_exam(true);
				$exam_id = $current_exam['exam_id'];
				$place_id = $current_exam['place_id'];
				$uid = $this->session->userdata('exam_uid');
			}
			
			/**
			 * source: redis
			 * key: exam:place:student:testting-exam_id:place_id
			 * type: sets
			 * value : uid
			 */
			
			$redis = $this->redis_model;
			$key = $this->pre."exam:place:student:testting-{$exam_id}:{$place_id}";
			
			$redis->srem($key, $uid);
		}
	}
	
	/**
	 * 统计在考考生数量
	 */
	public function count_exam_place_members($exam_id, $place_id)
	{
		$redis = $this->redis_model;
		$key = $this->pre."exam:place:student:testting-{$exam_id}:{$place_id}";
			
		return $redis->scard($key);
	}
	
	/**
	 * 保存考生在考试期间的 活跃时间
	 */
	public function set_exam_place_student_active_status($exam_id, $place_id, $uid)
	{
		$redis = $this->redis_model;
		$key = $this->pre."exam:place:student:active-{$exam_id}:{$place_id}:{$uid}";
			
		$val = date('Y-m-d H:i:s');
		$redis->set($key, $val);
	}
	
	/**
	 * 获取考生最后一次活跃时间
	 */
	public function get_student_last_active_time($exam_id, $place_id, $uid)
	{
		$redis = $this->redis_model;
		$key = $this->pre."exam:place:student:active-{$exam_id}:{$place_id}:{$uid}";
			
		$val = $redis->get($key);
		if (!$val) {
			return null;
		}
		
		return $val;
	}
	
	/**
	 * 移除某考生最后一次活跃时间记录
	 */
	public function remove_student_last_active_time($exam_id = 0, $place_id = 0, $uid = 0)
	{
		$redis = $this->redis_model;
		
		$exam_id = intval($exam_id);
		$place_id = intval($place_id);
		if ($this->session->userdata('exam_uid') || ($exam_id && $place_id && $uid)) {
			if (!($exam_id && $place_id && $uid)) {
				$current_exam = $this->exam_model->get_cookie_current_exam(true);
				$exam_id = $current_exam['exam_id'];
				$place_id = $current_exam['place_id'];
				$uid = $this->session->userdata('exam_uid');
			}
		}
		
		$uids = is_numeric($uid) ? array($uid) : $uid;
		$keys = array();
		foreach ($uids as $uid) {
			$keys[] = $this->pre."exam:place:student:active-{$exam_id}:{$place_id}:{$uid}";
		}
			
		$val = $redis->del(implode(' ', $keys));
	}
	
	/**
	 * 判断某个考生最后一次活跃时间 已经超过 限定时间
	 * 预定为下线
	 */
	public function has_beyond_active_time($exam_id, $place_id, $uid)
	{
		$time_limit = 1/3;//10秒
		$last_active_time = $this->get_student_last_active_time($exam_id, $place_id, $uid);
		if (is_null($last_active_time)) {
			return false;
		}
		return (strtotime($last_active_time) + $time_limit*60) < time();
	}
	
	
	//================离开界面次数统计===============
	/**
	 * 保存考生在考试期间的 离开界面的次数(window_blur)
	 */
	public function set_student_window_blur_count($exam_id, $place_id, $uid)
	{
		$redis = $this->redis_model;
		$key = $this->pre."exam:place:student:window_blur-{$exam_id}:{$place_id}";
			
		$redis->zincrby($key, 1, $uid);
	}
	
	/**
	 * 获取 考生在考试期间的 离开界面的次数(window_blur)
	 */
	public function get_student_window_blur_count($exam_id, $place_id, $uid)
	{
		$redis = $this->redis_model;
		$key = $this->pre."exam:place:student:window_blur-{$exam_id}:{$place_id}";
			
		$val = $redis->zscore($key, $uid);
		if (!$val) {
			return 0;
		}
	
		return intval($val);
	}
	
	/**
	 * 移除 考生在考试期间的 离开界面的次数(window_blur)
	 */
	public function remove_student_window_blur_count($exam_id = 0, $place_id = 0, $uid = 0)
	{
		$redis = $this->redis_model;
		
		$exam_id = intval($exam_id);
		$place_id = intval($place_id);
		$uid = intval($uid);
		if ($this->session->userdata('exam_uid') || ($exam_id && $place_id && $uid)) {
			if (!($exam_id && $place_id && $uid)) {
				$current_exam = $this->exam_model->get_cookie_current_exam(true);
				$exam_id = $current_exam['exam_id'];
				$place_id = $current_exam['place_id'];
				$uid = $this->session->userdata('exam_uid');
			}
				
			$key = $this->pre."exam:place:student:window_blur-{$exam_id}:{$place_id}";
			$redis->zrem($key, $uid);
		}
	}
} 
