<?php if ( ! defined('BASEPATH')) exit();
/*
| -------------------------------------------------------------------
| 机考临时数据清理任务
| -------------------------------------------------------------------
| 
*/
class Exam_clear extends CI_Controller
{
	private static $CI = null;
	private static $models = array();
	
	//关联的表
	private static $_tables = array(
			'exam' 			=> "exam",				
			'exam_place' 	=> "exam_place",				
			'test_paper' 	=> "exam_test_paper",				
			'test_result' 	=> "exam_test_result",				
			'paper' 		=> "exam_paper",				
			'place_student' => "exam_place_student",				
			'question' 		=> "question",				
			'relate_class' 	=> "relate_class",				
			'question_class'=> "question_class",				
			'exam_question_stat' => "exam_question_stat",		
			'student' 		=> "student",	
			'exam_log' 		=> "exam_logs",	
				
	);
	
	public function __construct($models = null)
    {	$redis_config = C('redis', 'redis');
    	$CI = & get_instance();
    	self::$CI = $CI;
    	self::$models = $models;
    	$this->pre= $redis_config['exam'];
    }
    
    /**
     * 清理已结束的考试的考试中临时数据
     * note:
     * 	  已结束条件：考试期次的考场已经结束 60分钟
     * todo:
     *   remove from redis:
     *   	step1:统计在考考生，key：exam:place:student:testting-exam_id:place_id
     *      step2:考生最后一次活跃时间 key: exam:place:student:active:-{$exam_id}:{$place_id}:{$uid}
     *      
     */
    public function clear_closed_exam_place_tmp_data()
    {
    	$key1 = $this->pre.'exam:place:student:testting';
    	
    	$redis = $this->_get_redis();
    	
    	//获取已结束考场
    	$closed_exams = $this->_get_closed_exam_place(60);
    	
    	$CI = self::$CI;
    	$_tables = self::$_tables;
    	$t_place_student = $_tables['place_student'];
    	$t_test_paper = $_tables['test_paper'];
    	$t_exam_log = $_tables['exam_log'];
    	
    	$CI->load->model('exam/student_log_stat_model', 'log_stat_model');
    	
    	foreach ($closed_exams as $exam) {
			$exam_pid = $exam['exam_pid'];    		
			$place_id = $exam['place_id'];

			//step1
			$redis->del("{$key1}-{$exam_pid}:{$place_id}");
			
			//step2
			$students = $CI->db->query("select ps.uid from {pre}{$t_place_student} ps, {pre}{$t_test_paper} tp where tp.place_id={$place_id} and tp.exam_pid={$exam_pid} and tp.etp_flag=0 and ps.place_id=tp.place_id and ps.uid=tp.uid")->result_array();
			if (!count($students)) {
				continue;
			}
			
			$uids = array();
			foreach ($students as $student) {
				$uids[] = $student['uid'];
			}
			
			$CI->log_stat_model->remove_student_last_active_time($exam_pid, $place_id, $uids);
    	}
    }
    
    /**
     * 监听正在进行中的考试 考生是否自行关闭考试窗口
     * note:
     * todo:
     *   如果考生上一次活跃时间 超过了 5分钟 and 未交卷 and 未作弊，则将该考生从在考列表中移除，并添加日志
     *   关联key: exam:place:student:active:-{$exam_id}:{$place_id}:{$uid}
     *   关联log_type: EXAM_LOG_LEAVE_TEST_PAGE
     *      
     */
    public function listen_testting_log_leave_test_page()
    {
    	$key1 = $this->pre.'exam:place:student:active';
    	$redis = $this->_get_redis();
    	
    	$CI = self::$CI;
    	$_tables = self::$_tables;
    	$t_place_student = $_tables['place_student'];
    	$t_test_paper = $_tables['test_paper'];
    	$t_exam_log = $_tables['exam_log'];
    	
    	//获取进行中考场
    	$t_testting_exams = $this->_get_testting_exam_place();
    	
    	$CI->load->model('exam/student_log_stat_model', 'log_stat_model');
    	require_once (APPPATH.'config/app/exam/log_type_constants.php');
    	
    	$exam_log_insert_data = array();
    	foreach ($t_testting_exams as $exam) {
			$exam_pid = $exam['exam_pid'];    		
			$place_id = $exam['place_id'];
			
			//获取考场考生
			$students = $CI->db->query("select ps.uid, tp.etp_id from {pre}{$t_place_student} ps, {pre}{$t_test_paper} tp where tp.place_id={$place_id} and tp.exam_pid={$exam_pid} and tp.etp_flag=0 and ps.place_id=tp.place_id and ps.uid=tp.uid")->result_array();
			if (!count($students)) {
				continue;
			}
			
			$tmp_data = array();
			foreach ($students as $student) {
				$uid = $student['uid'];
				if ($CI->log_stat_model->has_beyond_active_time($exam_pid, $place_id, $uid)) {
					$etp_id = $student['etp_id'];
					
					//如果已经存在日志的时间 >= 比该学生最新活跃时间，则不新增日志
					$log_type = EXAM_LOG_LEAVE_TEST_PAGE;
					$last_active_time = $CI->log_stat_model->get_student_last_active_time($exam_pid, $place_id, $uid);
					$result = $CI->db->query("select count(*) as count from {pre}{$t_exam_log} where exam_id={$exam_pid} and place_id={$place_id} and uid={$uid} and type={$log_type} and ctime>='{$last_active_time}'")->row();
					if (!$result->count) {
						$tmp_key = "{$exam_pid}-{$place_id}-{$uid}-{$log_type}";
						if (isset($tmp_data[$tmp_key])) {
							$exam_log_insert_data[$tmp_key]['etp_id'] = implode(',', array($exam_log_insert_data[$tmp_key]['etp_id'], $etp_id)); 
							continue;
						}
						$tmp_data[$tmp_key] = 1;
						$exam_log_insert_data[$tmp_key] = array(
										'uid' 	   => $uid, 
										'exam_id'  => $exam_pid, 
										'place_id' => $place_id,
										'etp_id'   => $etp_id,
										'ctime'    => date('Y-m-d H:i:s'),
										'type'	   => $log_type
						);
					}
				}
			}	
    	}
    	
    	//添加机考日志
    	if (count($exam_log_insert_data)) {
    		try {
    			$CI->db->insert_batch($t_exam_log, $exam_log_insert_data);
    			
    			//将考生从在考学生列表中移除
    			foreach ($exam_log_insert_data as $item) {
    				$CI->log_stat_model->remove_exam_place_member($item['exam_id'], $item['place_id'], $item['uid']);
    			}
    			
    		} catch (Exception $e) {
    			throw new Exception('添加机考日志失败');
    		}
    	}
    }
    
    /**
     * 获取已结束考场
     * @param $time_limit 结束时间超过的分钟
     */
    protected function _get_closed_exam_place($time_limit = 60) 
    {
    	$CI = self::$CI;
    	$time_limited = time() - $time_limit*60;
    	 
    	$_tables = self::$_tables;
    	 
    	$t_exam_place = $_tables['exam_place'];
    	 
    	$exams = $CI->db->query("select exam_pid, place_id from {pre}{$t_exam_place} where end_time<={$time_limited}")->result_array();
    	
    	return $exams;
    }
    
    /**
     * 获取进行中的考场
     * @param number $time_start_limit 考试开始前n分钟
     * @param number $time_end_limit 考试结束后n分钟
     * @return unknown
     */
    protected function _get_testting_exam_place($time_start_limit = 0, $time_end_limit = 20) 
    {
    	$CI = self::$CI;
    	$time_min = time() + $time_start_limit*60;//考试起始时间点
    	$time_max = time() - $time_end_limit*60;  //考试终结时间点
    	 
    	$_tables = self::$_tables;
    	 
    	$t_exam_place = $_tables['exam_place'];
    	 
    	$exams = $CI->db->query("select exam_pid, place_id from {pre}{$t_exam_place} where start_time <= {$time_min} and end_time >= {$time_max}")->result_array();
    	
    	return $exams;
    }
    
    /**
     * 获取redis
     */
    public function _get_redis()
    {
    	self::$CI->load->add_package_path(APPPATH  . "third_party/");
    	self::$CI->load->library("myredis", "", 'redis');
    
    	return self::$CI->redis->get_redis();
    }
}