<?php if ( ! defined('BASEPATH')) exit();
/*
| -------------------------------------------------------------------
| 机考后台任务计划
| -------------------------------------------------------------------
| 
*/
class Exam extends CI_Controller
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
			'question' 		=> "question",				
			'relate_class' 	=> "relate_class",				
			'question_class'=> "question_class",				
			'exam_question_stat' => "exam_question_stat",		

			'student' => "student",		
	);
    public function __construct($models = array())
    {
    	$CI = & get_instance();
    	self::$CI = $CI;
    	self::$models = $models;
    }
    
    /**
     * 计算考生的成绩
     * todo:
     * 	计算考生 的试题得分cal_test_result_score
     *  计算考生的试卷得分 cal_test_paper_score
     */
    public function cal_test_score($exam_pid = 0, $uid = 0) 
    {
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		return false;
    	}
    	
    	try {
    		$CI = self::$CI;
    		
    		//开启事务
    		$CI->db->trans_start();
    		 
    		//先补齐考生未做的题目
	    	$this->fill_unanswer_questions($exam_pid, $uid);
                
	    	//计算试题得分
	    	$this->cal_test_result_score($exam_pid, $uid);

	    	//计算试卷得分
	    	$this->cal_test_paper_score($exam_pid, $uid);

	    	//更新考试试题的分数
	    	$this->update_exam_question_score($exam_pid);

	    	//更新试题动态难易度(全局)
	    	//$this->cal_question_difficulty();
	
	    	//===================计算 汇总数据 (start)=============================//
	    	$CI = self::$CI;
	    	
	    	$CI->load->model('demo/report/summary_paper_model', 'cron_paper_model');
	    	$CI->load->model('demo/report/summary_region_model', 'cron_region_model');
	    	$CI->load->model('demo/report/summary_student_model', 'cron_student_model');
	    	
	    	/*
	    	 * todo list:
	    	 * 	1、更新 地区关联
	    	 * 	2、更新 考生关联
	    	 */
	    	
	    	$paper_model = $CI->cron_paper_model;
	    	$region_model = $CI->cron_region_model;
	    	$student_model = $CI->cron_student_model;
	    	
	    	$paper_model->summary_paper_question($exam_pid);

	    	$region_model->do_all($exam_pid, $uid);

	    	$student_model->do_all($exam_pid, $uid);

	    	//===================计算 汇总数据 (end)=============================//
    		 
    		//提交事务
    		return $CI->db->trans_complete();
        }
        catch (Exception $e)
        {
            echo $e->getMessage();
            $CI->db->trans_complete();
    	}
    }
    
    /**
     * 计算考生 的试题得分
     * 关联表：rd_exam_test_result rd_exam_question rd_exam_test_paper exam_question_stat 
     * 更新步骤：
     * 过滤记录：
     * 	已作弊的不列入条件
     * 
     * 1、计算更新rd_exam_test_result表中的test_score字段（根据题型分别计算，单选，不定项，填空，题组）
     * 2、根据rd_exam_test_result表中的test_score计算表rd_exam_test_paper表中的test_score字段
     * 3、同步更新表 exam_question_stat 数据
     * 
     * 更新条件：
     * 	考场的考试已经结束,并已经结束了 0.5 小时
     */
    public function cal_test_result_score($exam_pid = 0, $uid = 0)
    {
    	$CI = self::$CI;
    	
    	//计算更新rd_exam_test_result表中的test_score字段
    	/**
    	 * todo:
    	 * 	计算 单选题 得分
    	 * 	计算 不定项题 得分
    	 * 	计算 填空 得分
    	 * 	计算 题组 得分
    	 */
    	$_tables = self::$_tables;
    	$t_exam = $_tables['exam'];
    	$t_exam_place = $_tables['exam_place'];
    	$t_test_paper = $_tables['test_paper'];
    	$t_test_result = $_tables['test_result'];
    	$t_paper = $_tables['paper'];
    	$t_question = $_tables['question'];
    	$t_relate_class = $_tables['relate_class'];
    	$t_exam_question_stat = $_tables['exam_question_stat'];
    	
    	//考试期次考到的试题统计
    	static $exam_question_stats = array();
    	
    	//考生试题得分更新数据
    	static $update_data = array();    	
    	
    	//获取所有的考试期次
    	$exams = $this->_get_exams();
    	
    	$end_time = time() - 60;
    	$exam_pid = intval($exam_pid);
    	$uid = intval($uid);
    	if ($exam_pid > 0) {
    		$tmp_sql = $uid > 0 ? " and etr.uid={$uid} " : '';
	    	$sql = array(
	    			"select c.place_id, c.uid, c.etr_id, c.ques_id, c.r_answer, c.full_score, c.exam_pid, c.exam_id, c.q_answer, c.ques_subindex",
	    			"from ((select ep.place_id from {pre}{$t_exam_place} ep where ep.end_time <= {$end_time} and exam_pid={$exam_pid}) a inner join (",
	    			"	select etr.exam_id, etr.uid,  etp.place_id, etr.etr_id, etr.ques_id, etr.answer as r_answer, etr.ques_subindex, etr.full_score, etr.exam_pid, q.answer as q_answer",
	    			"	from {pre}{$t_test_result} etr,  {pre}{$t_question} q, {pre}{$t_test_paper} etp",
	    			"	where etr.exam_pid={$exam_pid} {$tmp_sql} and etr.ques_id=q.ques_id and q.type={type} and etr.etp_id=etp.etp_id and etp.etp_flag >= 0",
	    			") c on a.place_id=c.place_id)"
	    	);
    	} else {
    		$tmp_sql = $uid > 0 ? " etr.uid={$uid} and " : '';
	    	$sql = array(
	    			"select c.place_id, c.uid, c.etr_id, c.ques_id, c.r_answer, c.full_score, c.exam_pid, c.exam_id, c.q_answer, c.ques_subindex",
	    			"from ((select ep.place_id from {pre}{$t_exam_place} ep where ep.end_time <= {$end_time}) a inner join (",
	    			"	select etr.exam_id, etr.uid, etp.place_id, etr.etr_id, etr.ques_id, etr.answer as r_answer, etr.ques_subindex, etr.full_score, etr.exam_pid, q.answer as q_answer",
	    			"	from {pre}{$t_test_result} etr,  {pre}{$t_question} q, {pre}{$t_test_paper} etp",
	    			"	where {$tmp_sql} etr.ques_id=q.ques_id and q.type={type} and etr.etp_id=etp.etp_id and etp.etp_flag >= 0",
	    			") c on a.place_id=c.place_id)"
	    	);
    	}
    	
    	//计算 单选 得分
    	$query = $CI->db->query(str_ireplace('{type}', '1', implode(' ', $sql)));
    	foreach ($query->result_array() as $row)
    	{
    		$exam_pid = $row['exam_pid'];
    		$exam_id = $row['exam_id'];
    		$exam_id = $row['exam_id'];
    		$etr_id = $row['etr_id'];
    		$ques_id = $row['ques_id'];
    		$r_answer = $row['r_answer'];
    		$full_score = $row['full_score'];
    		$q_answer = $row['q_answer'];
    		
    		$e_k = "{$exam_pid}_{$exam_id}";
    		if (!isset($exams[$e_k])) {
    			continue;
    		}
    		$exam = $exams[$e_k];
    		$subject_type = $exam['subject_type'];
    		$grade_id = $exam['grade_id'];
    		$class_id = $exam['class_id'];
    		
    		//获取试题关联属性
    		$relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    		if (!count($relate_class)) {
    			continue;
    		}
    		
    		$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    		if (!isset($exam_question_stats[$stat_k])) {
    			$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    		}
    		$exam_question_stats[$stat_k]['student_amount']++;
    		
    		if ($r_answer == '' || $q_answer == '') {
    			continue;
    		}
    		
    		if ($r_answer == $q_answer) {
	    		$update_data[] = array(
	    				'etr_id'  		=> $etr_id,
	    				'test_score'  	=> $full_score,
	    		);
	    		
	    		$exam_question_stats[$stat_k]['right_amount']++;
    		}
    	}
    	
    	//计算 不定项题 得分
    	$query = $CI->db->query(str_ireplace('{type}', '2', implode(' ', $sql)));
    	foreach ($query->result_array() as $row)
    	{
    		$exam_pid = $row['exam_pid'];
    		$exam_id = $row['exam_id'];
    		$etr_id = $row['etr_id'];
    		$ques_id = $row['ques_id'];
    		$r_answer = trim($row['r_answer']);
    		$q_answer = trim($row['q_answer']);
    		$full_score = $row['full_score'];
    		
    		$e_k = "{$exam_pid}_{$exam_id}";
    		if (!isset($exams[$e_k])) {
    			continue;
    		}
    		$exam = $exams[$e_k];
    		$subject_type = $exam['subject_type'];
    		$grade_id = $exam['grade_id'];
    		$class_id = $exam['class_id'];
    	
    		//获取试题关联属性
    		$relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    		if (!count($relate_class)) {
    			continue;
    		}
    		
    		$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    		if (!isset($exam_question_stats[$stat_k])) {
    			$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    		}
    		$exam_question_stats[$stat_k]['student_amount']++;
    		
    		if ($r_answer == '' || $q_answer == '') {
    			continue;
    		}
    		
    		$r_answer = explode(',', $r_answer);
    		$q_answer = explode(',', $q_answer);
    		
    		asort($r_answer);
    		asort($q_answer);
    		
    		$count_r_answer = count($r_answer);
    		$count_q_answer = count($q_answer);
    		if ($count_r_answer == $count_q_answer && !count(array_diff($q_answer, $r_answer))) {
    			//全部答对
    			$update_data[] = array(
    					'etr_id'  		=> $etr_id,
    					'test_score'  	=> $full_score,
    			);
    			$exam_question_stats[$stat_k]['right_amount']++;
    		} else {
    			//答对部分选项,按照答对部分与总分占比 来给分
    			if (!count(array_diff($r_answer, $q_answer))) {
    				$test_score = ($count_r_answer/$count_q_answer)*$full_score;
    				$update_data[] = array(
    						'etr_id'  		=> $etr_id,
    						'test_score'  	=> $test_score,
    				);
    			}
    		} 
    	}
    	
    	//计算 填空 得分
    	$query = $CI->db->query(str_ireplace('{type}', '3', implode(' ', $sql)));
    	foreach ($query->result_array() as $row)
    	{
    		$exam_pid = $row['exam_pid'];
    		$exam_id = $row['exam_id'];
    		$etr_id = $row['etr_id'];
    		$ques_id = $row['ques_id'];
    		$r_answer = trim($row['r_answer']);
    		$q_answer = trim($row['q_answer']);
    		$full_score = $row['full_score'];
    		
    		$e_k = "{$exam_pid}_{$exam_id}";
    		if (!isset($exams[$e_k])) {
    			continue;
    		}
    		$exam = $exams[$e_k];
    		$subject_type = $exam['subject_type'];
    		$grade_id = $exam['grade_id'];
    		$class_id = $exam['class_id'];
    		 
    		//获取试题关联属性
    		$relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    		if (!count($relate_class)) {
    			continue;
    		}
    		
    		$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    		if (!isset($exam_question_stats[$stat_k])) {
    			$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    		}
    		$exam_question_stats[$stat_k]['student_amount']++;
    		
    		if ($r_answer == '' || $q_answer == '') {
    			continue;
    		}
    	
    		$r_answer = explode("\n", $r_answer);
    		$q_answer = explode("\n", $q_answer);
    		
    		$count_q_answer = count($q_answer);
    		
    		$per_score = $full_score/$count_q_answer;//每个填空位置分数
    		$get_score = 0;
    		foreach ($q_answer as $k=>$v) {
    			if (isset($r_answer[$k]) && trim($r_answer[$k]) === trim($v)) {
    				$get_score+=$per_score;
    			}
    		}
    	
			$update_data[] = array(
    					'etr_id'  		=> $etr_id,
    					'test_score'  	=> $get_score,
			);
			
			if ($get_score == $full_score) {
				$exam_question_stats[$stat_k]['right_amount']++;
			}
    	}
    	
    	
    	//计算 题组 得分----start
    	
    	//获取答题记录中题组记录
    	$query = $CI->db->query(str_ireplace('{type}', '0', implode(' ', $sql)) . ' order by c.etr_id asc');
    	$result = $query->result_array();
    	if (count($result)) {
	    	$ques_ids = array();
	    	foreach ($result as $row) {
	    		if (!in_array($row['ques_id'], $ques_ids)) {
		    		$ques_ids[] = $row['ques_id'];
	    		}
	    	}
	    	
	    	//获取题组的子题
	    	$ques_ids = array_filter($ques_ids);
	    	$parent_ids = implode(', ', $ques_ids);
	    	$query = $CI->db->query("select ques_id, parent_id, answer, type from {pre}{$t_question} where parent_id in ({$parent_ids}) AND is_delete = 0 order by ques_id asc");
	    	$child_result = $query->result_array();
	    	
	    	//将原题目 子题按照parent_id进行归档
	    	$tmp_child_result = array();
	    	foreach ($child_result as $val) {
	    		$tmp_child_result[$val['parent_id']][] = $val;
	    	}
	    	
	    	//将答题记录 按照子题的ques_id归档
	    	$tmp_result = array();
	    	foreach ($result as $val) {
    			$tmp_result[$val['ques_id']][] = $val;
	    	}
	    	
	    	if (count($tmp_result) && count($tmp_child_result)) {
	    		foreach ($tmp_result as $key=>$row)
	    		{
	    			if (!isset($tmp_child_result[$key])) {
	    				continue;
	    			}
	    			
	    			$child_result = $tmp_child_result[$key];
	    			$tmp_exam_pid = null;
	    			$tmp_exam_id = null;
	    			$count_child_result = count($child_result);
	    			$u_counts = array();
	    			$u_count_rights = array();
	    			foreach ($row as $key2=>$v) {
	    			    
	    				$key2 = $key2 > ($count_child_result - 1) ? $key2%$count_child_result : $key2;
	    				if (!isset($child_result[$key2])) {
	    					continue;
	    				}
	    				
	    				$chlid = $child_result[$key2];
	    				$type = $chlid['type'];
	    				
	    				$exam_pid = $v['exam_pid'];
	    				$exam_id = $v['exam_id'];
		    			$etr_id = $v['etr_id'];
		    			
		    			$ques_id = $v['ques_id'];
		    			$c_ques_id = $chlid['ques_id'];
		    			$r_answer = trim($v['r_answer']);
		    			$q_answer = trim($chlid['answer']);
		    			$full_score = $v['full_score'];
		    			$uid = $v['uid'];
		    			
		    			$tmp_exam_pid = $exam_pid;
		    			$tmp_exam_id = $exam_id;
		    			
		    			if (!isset($u_counts[$uid])) {
		    				$u_counts[$uid] = 0;
		    			}
		    			if (!isset($u_count_rights[$uid])) {
		    				$u_count_rights[$uid] = 0;
		    			}
		    			
	    				$u_counts[$uid]++;
		    			if ($r_answer == '' || $q_answer == '') {
		    				continue;
		    			}
		    			
	    				if ($type == '1') {
	    					//单选	
	    					if ($r_answer == $q_answer) {
	    						$update_data[] = array(
	    								'etr_id'  => $etr_id,
	    								'test_score'  => $full_score,
	    						);
	    						$u_count_rights[$uid]++;
	    					}
	    				} elseif ($type == '2') {
	    					//不定项
	    					$r_answer = explode(',', $r_answer);
	    					$q_answer = explode(',', $q_answer);
	    					
	    					asort($r_answer);
	    					asort($q_answer);
	    					
	    					$count_r_answer = count($r_answer);
	    					$count_q_answer = count($q_answer);
	    					if ($count_r_answer == $count_q_answer && !count(array_diff($q_answer, $r_answer))) {
	    						//全部答对
	    						$update_data[] = array(
	    								'etr_id'  		=> $etr_id,
	    								'test_score'  	=> $full_score,
	    						);
	    						$u_count_rights[$uid]++;
	    					} else {
	    						//答对部分选项,按照答对部分与总分占比 来给分
	    						if (!count(array_diff($r_answer, $q_answer))) {
	    							$test_score = ($count_r_answer/$count_q_answer)*$full_score;
	    							$update_data[] = array(
	    									'etr_id'  		=> $etr_id,
	    									'test_score'  	=> $test_score,
	    							);
	    						}
	    					}
	    				} elseif ($type == '3') {
	    					//填空
	    					$r_answer = explode("\n", $r_answer);
	    					$q_answer = explode("\n", $q_answer);
	    					
	    					$count_q_answer = count($q_answer);
	    					
	    					$per_score = $full_score/$count_q_answer;//每个填空位置分数
	    					$get_score = 0;
	    					foreach ($q_answer as $k=>$v) {
	    						if (isset($r_answer[$k]) && trim($r_answer[$k]) == trim($v)) {
	    							$get_score+=$per_score;
	    						}
	    					}
	    					 
    						$update_data[] = array(
    								'etr_id'  		=> $etr_id,
    								'test_score'  	=> $get_score,
    						);
    						$u_count_rights[$uid]++;
	    				}
	    			}
	    			
	    			if (is_null($tmp_exam_pid) || is_null($tmp_exam_id)) {
	    				continue;
	    			}
	    			
	    			$ques_id = $key;
	    			$e_k = "{$tmp_exam_pid}_{$tmp_exam_id}";
	    			if (!isset($exams[$e_k])) {
	    				continue;
	    			}
	    			$exam = $exams[$e_k];
	    			$subject_type = $exam['subject_type'];
	    			$grade_id = $exam['grade_id'];
	    			$class_id = $exam['class_id'];
	    			 
	    			//获取试题关联属性
	    			$relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
	    			if (!count($relate_class)) {
	    				continue;
	    			}
	    			 
	    			//题组答对统计
	    			$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
	    			if (!isset($exam_question_stats[$stat_k])) {
	    				$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
	    			}
	    			$exam_question_stats[$stat_k]['student_amount'] = count(array_keys($u_counts));
	    			$count_right = 0;
	    			if (count($u_count_rights)) {
		    			foreach ($u_count_rights as $k=>$item) {
		    				if ($item == $u_counts[$k]) {
		    					$count_right++;
		    				}
		    			}
	    			}
	    			$exam_question_stats[$stat_k]['right_amount'] = $count_right;
	    		}
	    	}
    	}
    	//计算 题组 得分----end
    	
    	/**
    	 * 英语特殊题型
    	 */
    	//计算 翻译题 得分
    	$query = $CI->db->query(str_ireplace('{type}', '7', implode(' ', $sql)));
    	foreach ($query->result_array() as $row)
    	{
    	    $exam_pid = $row['exam_pid'];
    	    $exam_id = $row['exam_id'];
    	    $exam_id = $row['exam_id'];
    	    $etr_id = $row['etr_id'];
    	    $ques_id = $row['ques_id'];
    	    $r_answer = $row['r_answer'];
    	    $full_score = $row['full_score'];
    	    $q_answer = $row['q_answer'];
    	
    	    $e_k = "{$exam_pid}_{$exam_id}";
    	    if (!isset($exams[$e_k])) {
    	        continue;
    	    }
    	    $exam = $exams[$e_k];
    	    $subject_type = $exam['subject_type'];
    	    $grade_id = $exam['grade_id'];
    	    $class_id = $exam['class_id'];
    	
    	    //获取试题关联属性
    	    $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    	    if (!count($relate_class)) {
    	        continue;
    	    }
    	
    	    $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    	    if (!isset($exam_question_stats[$stat_k])) {
    	        $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    	    }
    	    $exam_question_stats[$stat_k]['student_amount']++;
    	
    	    if ($r_answer == '' || $q_answer == '') {
    	        continue;
    	    }
    	
    	    if ($r_answer == $q_answer) {
    	        $update_data[] = array(
    	                'etr_id'  		=> $etr_id,
    	                'test_score'  	=> $full_score,
    	        );
    	         
    	        $exam_question_stats[$stat_k]['right_amount']++;
    	    }
    	}
    	
    	//计算 完形填空 得分----start
    	//获取答题记录中题组记录
    	$query = $CI->db->query(str_ireplace('{type}', '4', implode(' ', $sql)) . ' order by c.etr_id asc');
    	$result = $query->result_array();
    	if (count($result)) {
    	    $ques_ids = array();
    	    foreach ($result as $row) {
    	        if (!in_array($row['ques_id'], $ques_ids)) {
    	            $ques_ids[] = $row['ques_id'];
    	        }
    	    }
    	
    	    //获取完形填空的子题
    	    $ques_ids = array_filter($ques_ids);
    	    $parent_ids = implode(', ', $ques_ids);
    	    $query = $CI->db->query("select ques_id, parent_id, answer, type from {pre}{$t_question} where parent_id in ({$parent_ids}) AND is_delete = 0 order by ques_id asc");
    	    $child_result = $query->result_array();
    	
    	    //将原题目 子题按照parent_id进行归档
    	    $tmp_child_result = array();
    	    foreach ($child_result as $val) {
    	        $tmp_child_result[$val['parent_id']][] = $val;
    	    }
    	
    	    //将答题记录 按照子题的ques_id归档
    	    $tmp_result = array();
    	    foreach ($result as $val) {
    	        $tmp_result[$val['ques_id']][] = $val;
    	    }
    	
    	    if (count($tmp_result) && count($tmp_child_result)) {
    	        foreach ($tmp_result as $key=>$row)
    	        {
    	            if (!isset($tmp_child_result[$key])) {
    	                continue;
    	            }
    	
    	            $child_result = $tmp_child_result[$key];
    	            $tmp_exam_pid = null;
    	            $tmp_exam_id = null;
    	            $count_child_result = count($child_result);
    	            $u_counts = array();
    	            $u_count_rights = array();
    	            foreach ($row as $key2=>$v) {
    	                $key2 = $key2 > ($count_child_result - 1) ? $key2%$count_child_result : $key2;
    	                 
    	                if (!isset($child_result[$key2])) {
    	                    continue;
    	                }
    	                 
    	                $chlid = $child_result[$key2];
    	                $type = $chlid['type'];
    	                 
    	                $exam_pid = $v['exam_pid'];
    	                $exam_id = $v['exam_id'];
    	                $etr_id = $v['etr_id'];
    	                $ques_id = $v['ques_id'];
    	                $c_ques_id = $chlid['ques_id'];
    	                $r_answer = trim($v['r_answer']);
    	                $q_answer = trim($chlid['answer']);
    	                $full_score = $v['full_score'];
    	                $uid = $v['uid'];
    	                 
    	                $tmp_exam_pid = $exam_pid;
    	                $tmp_exam_id = $exam_id;
    	                 
    	                if (!isset($u_counts[$uid])) {
    	                    $u_counts[$uid] = 0;
    	                }
    	                if (!isset($u_count_rights[$uid])) {
    	                    $u_count_rights[$uid] = 0;
    	                }
    	                 
    	                $u_counts[$uid]++;
    	                if ($r_answer == '' || $q_answer == '') {
    	                    continue;
    	                }
    	                 
    	                if ($type == '1') {
    	                    //单选
    	                    if ($r_answer == $q_answer) {
    	                        $update_data[] = array(
    	                                'etr_id'  => $etr_id,
    	                                'test_score'  => $full_score,
    	                        );
    	                        $u_count_rights[$uid]++;
    	                    }
    	                } 
    	            }
    	
    	            if (is_null($tmp_exam_pid) || is_null($tmp_exam_id)) {
    	                continue;
    	            }
    	
    	            $ques_id = $key;
    	            $e_k = "{$tmp_exam_pid}_{$tmp_exam_id}";
    	            if (!isset($exams[$e_k])) {
    	                continue;
    	            }
    	            $exam = $exams[$e_k];
    	            $subject_type = $exam['subject_type'];
    	            $grade_id = $exam['grade_id'];
    	            $class_id = $exam['class_id'];
    	             
    	            //获取试题关联属性
    	            $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    	            if (!count($relate_class)) {
    	                continue;
    	            }
    	             
    	            //题组答对统计
    	            $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    	            if (!isset($exam_question_stats[$stat_k])) {
    	                $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    	            }
    	            $exam_question_stats[$stat_k]['student_amount'] = count(array_keys($u_counts));
    	            $count_right = 0;
    	            if (count($u_count_rights)) {
    	                foreach ($u_count_rights as $k=>$item) {
    	                    if ($item == $u_counts[$k]) {
    	                        $count_right++;
    	                    }
    	                }
    	            }
    	            $exam_question_stats[$stat_k]['right_amount'] = $count_right;
    	        }
    	    }
    	}
    	//计算 完形填空 得分----end
    	
    	//计算 匹配题 得分----start
    	//获取答题记录中题组记录
    	$query = $CI->db->query(str_ireplace('{type}', '5', implode(' ', $sql)) . ' order by c.etr_id asc');
    	$result = $query->result_array();
    	if (count($result)) {
    	    $ques_ids = array();
    	    foreach ($result as $row) {
    	        if (!in_array($row['ques_id'], $ques_ids)) {
    	            $ques_ids[] = $row['ques_id'];
    	        }
    	    }
    	
    	    //获取匹配题的子题
    	    $ques_ids = array_filter($ques_ids);
    	    $parent_ids = implode(', ', $ques_ids);
    	    $query = $CI->db->query("select ques_id, parent_id, answer, type from {pre}{$t_question} where parent_id in ({$parent_ids}) AND is_delete = 0 order by ques_id asc");
    	    $child_result = $query->result_array();
    	
    	    //将原题目 子题按照parent_id进行归档
    	    $tmp_child_result = array();
    	    foreach ($child_result as $val) {
    	        $tmp_child_result[$val['parent_id']][] = $val;
    	    }
    	
    	    //将答题记录 按照子题的ques_id归档
    	    $tmp_result = array();
    	    foreach ($result as $val) {
    	        $tmp_result[$val['ques_id']][] = $val;
    	    }
    	
    	    if (count($tmp_result) && count($tmp_child_result)) {
    	        foreach ($tmp_result as $key=>$row)
    	        {
    	            if (!isset($tmp_child_result[$key])) {
    	                continue;
    	            }
    	
    	            $child_result = $tmp_child_result[$key];
    	            $tmp_exam_pid = null;
    	            $tmp_exam_id = null;
    	            $count_child_result = count($child_result);
    	            $u_counts = array();
    	            $u_count_rights = array();
    	            foreach ($row as $key2=>$v) {
    	                $key2 = $key2 > ($count_child_result - 1) ? $key2%$count_child_result : $key2;
    	                 
    	                if (!isset($child_result[$key2])) {
    	                    continue;
    	                }
    	                 
    	                $chlid = $child_result[$key2];
    	                $type = $chlid['type'];
    	                 
    	                $exam_pid = $v['exam_pid'];
    	                $exam_id = $v['exam_id'];
    	                $etr_id = $v['etr_id'];
    	                $ques_id = $v['ques_id'];
    	                $c_ques_id = $chlid['ques_id'];
    	                $r_answer = trim($v['r_answer']);
    	                $q_answer = trim($chlid['answer']);
    	                $full_score = $v['full_score'];
    	                $uid = $v['uid'];
    	                 
    	                $tmp_exam_pid = $exam_pid;
    	                $tmp_exam_id = $exam_id;
    	                 
    	                if (!isset($u_counts[$uid])) {
    	                    $u_counts[$uid] = 0;
    	                }
    	                if (!isset($u_count_rights[$uid])) {
    	                    $u_count_rights[$uid] = 0;
    	                }
    	                 
    	                $u_counts[$uid]++;
    	                if ($r_answer == '' || $q_answer == '') {
    	                    continue;
    	                }
    	                 
    	                if ($type == '5') {
    	                    //填空
    	                    $r_answer = explode("\n", $r_answer);
    	                    $q_answer = explode("\n", $q_answer);
    	
    	                    $count_q_answer = count($q_answer);
    	
    	                    $per_score = $full_score/$count_q_answer;//每个填空位置分数
    	                    $get_score = 0;
    	                    foreach ($q_answer as $k=>$v) {
    	                        if (isset($r_answer[$k]) && trim($r_answer[$k]) == trim($v)) {
    	                            $get_score+=$per_score;
    	                        }
    	                    }
    	                    	
    	                    $update_data[] = array(
    	                            'etr_id'  		=> $etr_id,
    	                            'test_score'  	=> $get_score,
    	                    );
    	                    $u_count_rights[$uid]++;
    	                }
    	            }
    	
    	            if (is_null($tmp_exam_pid) || is_null($tmp_exam_id)) {
    	                continue;
    	            }
    	
    	            $ques_id = $key;
    	            $e_k = "{$tmp_exam_pid}_{$tmp_exam_id}";
    	            if (!isset($exams[$e_k])) {
    	                continue;
    	            }
    	            $exam = $exams[$e_k];
    	            $subject_type = $exam['subject_type'];
    	            $grade_id = $exam['grade_id'];
    	            $class_id = $exam['class_id'];
    	             
    	            //获取试题关联属性
    	            $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    	            if (!count($relate_class)) {
    	                continue;
    	            }
    	             
    	            //题组答对统计
    	            $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    	            if (!isset($exam_question_stats[$stat_k])) {
    	                $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    	            }
    	            $exam_question_stats[$stat_k]['student_amount'] = count(array_keys($u_counts));
    	            $count_right = 0;
    	            if (count($u_count_rights)) {
    	                foreach ($u_count_rights as $k=>$item) {
    	                    if ($item == $u_counts[$k]) {
    	                        $count_right++;
    	                    }
    	                }
    	            }
    	            $exam_question_stats[$stat_k]['right_amount'] = $count_right;
    	        }
    	    }
    	}
    	//计算 匹配题 得分----end

        //计算 阅读填空题 得分----start
        //获取答题记录中题组记录
        $query = $CI->db->query(str_ireplace('{type}', '8', implode(' ', $sql)) . ' order by c.etr_id asc');
        $result = $query->result_array();
        if (count($result)) {
            $ques_ids = array();
            foreach ($result as $row) {
                if (!in_array($row['ques_id'], $ques_ids)) {
                    $ques_ids[] = $row['ques_id'];
                }
            }
        
            //获取阅读填空题的子题
            $ques_ids = array_filter($ques_ids);
            $parent_ids = implode(', ', $ques_ids);
            $query = $CI->db->query("select ques_id, parent_id, answer, type from {pre}{$t_question} where parent_id in ({$parent_ids}) AND is_delete = 0 order by ques_id asc");
            $child_result = $query->result_array();
        
            //将原题目 子题按照parent_id进行归档
            $tmp_child_result = array();
            foreach ($child_result as $val) {
                $tmp_child_result[$val['parent_id']][] = $val;
            }
        
            //将答题记录 按照子题的ques_id归档
            $tmp_result = array();
            foreach ($result as $val) {
                $tmp_result[$val['ques_id']][] = $val;
            }
        
            if (count($tmp_result) && count($tmp_child_result)) {
                foreach ($tmp_result as $key=>$row)
                {
                    if (!isset($tmp_child_result[$key])) {
                        continue;
                    }
        
                    $child_result = $tmp_child_result[$key];
                    $tmp_exam_pid = null;
                    $tmp_exam_id = null;
                    $count_child_result = count($child_result);
                    $u_counts = array();
                    $u_count_rights = array();
                    foreach ($row as $key2=>$v) {
                        $key2 = $key2 > ($count_child_result - 1) ? $key2%$count_child_result : $key2;
                         
                        if (!isset($child_result[$key2])) {
                            continue;
                        }
                         
                        $chlid = $child_result[$key2];
                        $type = $chlid['type'];
                         
                        $exam_pid = $v['exam_pid'];
                        $exam_id = $v['exam_id'];
                        $etr_id = $v['etr_id'];
                        $ques_id = $v['ques_id'];
                        $c_ques_id = $chlid['ques_id'];
                        $r_answer = trim($v['r_answer']);
                        $q_answer = trim($chlid['answer']);
                        $full_score = $v['full_score'];
                        $uid = $v['uid'];
                         
                        $tmp_exam_pid = $exam_pid;
                        $tmp_exam_id = $exam_id;
                         
                        if (!isset($u_counts[$uid])) {
                            $u_counts[$uid] = 0;
                        }
                        if (!isset($u_count_rights[$uid])) {
                            $u_count_rights[$uid] = 0;
                        }
                         
                        $u_counts[$uid]++;
                        if ($r_answer == '' || $q_answer == '') {
                            continue;
                        }
                         
                        if ($type == '8') {
                            //填空
                            $r_answer = explode("\n", $r_answer);
                            $q_answer = explode("\n", $q_answer);
        
                            $count_q_answer = count($q_answer);
        
                            $per_score = $full_score/$count_q_answer;//每个填空位置分数
                            $get_score = 0;
                            foreach ($q_answer as $k=>$v) {
                                if (isset($r_answer[$k]) && trim($r_answer[$k]) == trim($v)) {
                                    $get_score+=$per_score;
                                }
                            }
                                
                            $update_data[] = array(
                                    'etr_id'        => $etr_id,
                                    'test_score'    => $get_score,
                            );
                            $u_count_rights[$uid]++;
                        }
                    }
        
                    if (is_null($tmp_exam_pid) || is_null($tmp_exam_id)) {
                        continue;
                    }
        
                    $ques_id = $key;
                    $e_k = "{$tmp_exam_pid}_{$tmp_exam_id}";
                    if (!isset($exams[$e_k])) {
                        continue;
                    }
                    $exam = $exams[$e_k];
                    $subject_type = $exam['subject_type'];
                    $grade_id = $exam['grade_id'];
                    $class_id = $exam['class_id'];
                     
                    //获取试题关联属性
                    $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
                    if (!count($relate_class)) {
                        continue;
                    }
                     
                    //题组答对统计
                    $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
                    if (!isset($exam_question_stats[$stat_k])) {
                        $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
                    }
                    $exam_question_stats[$stat_k]['student_amount'] = count(array_keys($u_counts));
                    $count_right = 0;
                    if (count($u_count_rights)) {
                        foreach ($u_count_rights as $k=>$item) {
                            if ($item == $u_counts[$k]) {
                                $count_right++;
                            }
                        }
                    }
                    $exam_question_stats[$stat_k]['right_amount'] = $count_right;
                }
            }
        }
        //计算 阅读填空题 得分----end
    	
    	//计算 选词填空 得分----start
    	//获取答题记录中题组记录
    	$query = $CI->db->query(str_ireplace('{type}', '6', implode(' ', $sql)) . ' order by c.etr_id asc');
    	$result = $query->result_array();
    	if (count($result)) {
    	    $ques_ids = array();
    	    foreach ($result as $row) {
    	        if (!in_array($row['ques_id'], $ques_ids)) {
    	            $ques_ids[] = $row['ques_id'];
    	        }
    	    }
    	     
    	    //获取匹配题的子题
    	    $ques_ids = array_filter($ques_ids);
    	    $parent_ids = implode(', ', $ques_ids);
    	    $query = $CI->db->query("select ques_id, parent_id, answer, type from {pre}{$t_question} where parent_id in ({$parent_ids}) AND is_delete = 0 order by ques_id asc");
    	    $child_result = $query->result_array();
    	     
    	    //将原题目 子题按照parent_id进行归档
    	    $tmp_child_result = array();
    	    foreach ($child_result as $val) {
    	        $tmp_child_result[$val['parent_id']][] = $val;
    	    }
    	     
    	    //将答题记录 按照子题的ques_id归档
    	    $tmp_result = array();
    	    foreach ($result as $val) {
    	        $tmp_result[$val['ques_id']][] = $val;
    	    }
    	     
    	    if (count($tmp_result) && count($tmp_child_result)) {
    	        foreach ($tmp_result as $key=>$row)
    	        {
    	            if (!isset($tmp_child_result[$key])) {
    	                continue;
    	            }
    	             
    	            $child_result = $tmp_child_result[$key];
    	            $tmp_exam_pid = null;
    	            $tmp_exam_id = null;
    	            $count_child_result = count($child_result);
    	            $u_counts = array();
    	            $u_count_rights = array();
    	            foreach ($row as $key2=>$v) {
    	                $key2 = $key2 > ($count_child_result - 1) ? $key2%$count_child_result : $key2;
    	
    	                if (!isset($child_result[$key2])) {
    	                    continue;
    	                }
    	
    	                $chlid = $child_result[$key2];
    	                $type = $chlid['type'];
    	
    	                $exam_pid = $v['exam_pid'];
    	                $exam_id = $v['exam_id'];
    	                $etr_id = $v['etr_id'];
    	                $ques_id = $v['ques_id'];
    	                $c_ques_id = $chlid['ques_id'];
    	                $r_answer = trim($v['r_answer']);
    	                $q_answer = trim($chlid['answer']);
    	                $full_score = $v['full_score'];
    	                $uid = $v['uid'];
    	
    	                $tmp_exam_pid = $exam_pid;
    	                $tmp_exam_id = $exam_id;
    	
    	                if (!isset($u_counts[$uid])) {
    	                    $u_counts[$uid] = 0;
    	                }
    	                if (!isset($u_count_rights[$uid])) {
    	                    $u_count_rights[$uid] = 0;
    	                }
    	
    	                $u_counts[$uid]++;
    	                if ($r_answer == '' || $q_answer == '') {
    	                    continue;
    	                }
    	
    	                if ($type == '6') {
    	                    //填空
    	                    $r_answer = explode("\n", $r_answer);
    	                    $q_answer = explode("\n", $q_answer);
    	                     
    	                    $count_q_answer = count($q_answer);
    	                     
    	                    $per_score = $full_score/$count_q_answer;//每个填空位置分数
    	                    $get_score = 0;
    	                    foreach ($q_answer as $k=>$v) {
    	                        if (isset($r_answer[$k]) && trim($r_answer[$k]) == trim($v)) {
    	                            $get_score+=$per_score;
    	                        }
    	                    }
    	
    	                    $update_data[] = array(
    	                            'etr_id'  		=> $etr_id,
    	                            'test_score'  	=> $get_score,
    	                    );
    	                    $u_count_rights[$uid]++;
    	                }
    	            }
    	             
    	            if (is_null($tmp_exam_pid) || is_null($tmp_exam_id)) {
    	                continue;
    	            }
    	             
    	            $ques_id = $key;
    	            $e_k = "{$tmp_exam_pid}_{$tmp_exam_id}";
    	            if (!isset($exams[$e_k])) {
    	                continue;
    	            }
    	            $exam = $exams[$e_k];
    	            $subject_type = $exam['subject_type'];
    	            $grade_id = $exam['grade_id'];
    	            $class_id = $exam['class_id'];
    	
    	            //获取试题关联属性
    	            $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    	            if (!count($relate_class)) {
    	                continue;
    	            }
    	
    	            //题组答对统计
    	            $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    	            if (!isset($exam_question_stats[$stat_k])) {
    	                $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    	            }
    	            $exam_question_stats[$stat_k]['student_amount'] = count(array_keys($u_counts));
    	            $count_right = 0;
    	            if (count($u_count_rights)) {
    	                foreach ($u_count_rights as $k=>$item) {
    	                    if ($item == $u_counts[$k]) {
    	                        $count_right++;
    	                    }
    	                }
    	            }
    	            $exam_question_stats[$stat_k]['right_amount'] = $count_right;
    	        }
    	    }
    	}
    	//计算 选词填空 得分----end

        /**
         * 计算连词成句得分
         */
        $query = $CI->db->query(str_ireplace('{type}', '9', implode(' ', $sql)));

        foreach ($query->result_array() as $row)
        {
            $exam_pid = $row['exam_pid'];
            $exam_id = $row['exam_id'];
            $etr_id = $row['etr_id'];
            $ques_id = $row['ques_id'];
            $r_answer = trim($row['r_answer']);
            $q_answer = trim($row['q_answer']);
            $full_score = $row['full_score'];
            
            $e_k = "{$exam_pid}_{$exam_id}";

            if (!isset($exams[$e_k])) {
                continue;
            }

            $exam = $exams[$e_k];
            $subject_type = $exam['subject_type'];
            $grade_id = $exam['grade_id'];
            $class_id = $exam['class_id'];
             
            //获取试题关联属性
            $relate_class = $CI->db->query("select id from {pre}{$t_relate_class} where ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
            if (!count($relate_class)) {
                continue;
            }
            
            $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";

            if (!isset($exam_question_stats[$stat_k])) {
                $exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
            }

            $exam_question_stats[$stat_k]['student_amount']++;
            
            if ($r_answer == '' || $q_answer == '') {
                continue;
            }
        
            $r_answer = explode("\n", $r_answer);
            $q_answer = explode("\n", $q_answer);
            
            $count_q_answer = count($q_answer);
            
            $per_score = $full_score/$count_q_answer;//每个填空位置分数

            $get_score = 0;

            foreach ($q_answer as $k=>$v) {
                if (isset($r_answer[$k]) && trim($r_answer[$k]) === trim($v)) {
                    $get_score+=$per_score;
                }
            }
        
            $update_data[] = array(
                        'etr_id'        => $etr_id,
                        'test_score'    => $get_score,
            );
            
            if ($get_score == $full_score) {
                $exam_question_stats[$stat_k]['right_amount']++;
            }
        }
    	
    	if (count($update_data)) {
	    	try {
		    	//更新分数
		    	$CI->db->update_batch($t_test_result, $update_data, 'etr_id');
		    	
		    	//更新考试试题相关统计
		    	$this->_update_exam_question_stat($exam_question_stats);
		    	
	    	} catch (Exception $e) {
	//     		echo $e->getMessage();
	    		
	    		throw new Exception('更新 考生试题 得分失败，更新字段：' . $t_test_result . '->test_score, Error:' . $e->getMessage());
	    	} 
    	}
    }
    	
    /**
     * 将考生未答的试题填充到rd_exam_test_result表中
     * note:
     * 	关联表：
     * 		rd_exam rd_exam_place rd_exam_test_result rd_exam_test_paper rd_exam_question
     */
    public function fill_unanswer_questions($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
    
        //获取某期考生未答的试题
        $end_time = time() - 60;
        $sql_where1 = $uid > 0 ? " AND etp.uid = {$uid} " : '';
        if ($exam_pid > 0)
        {
            $sql = <<<EOT
SELECT DISTINCT b.exam_pid, b.exam_id, b.etp_id, b.uid, b.paper_id, 
    b.ques_id, s.ques_id AS etr_ques_id 
FROM (
        (SELECT eq.ques_id, etp.etp_id, etp.exam_pid, etp.exam_id, etp.uid, 
            etp.paper_id
         FROM rd_exam_test_paper etp, rd_exam_question eq, rd_exam_place ep
         WHERE etp.paper_id = eq.paper_id AND etp.place_id = ep.place_id 
            AND ep.end_time <= {$end_time} AND etp.exam_pid = {$exam_pid} 
            {$sql_where1}
        ) b LEFT JOIN (
        SELECT d.ques_id, d.exam_id, d.uid, d.paper_id
        FROM (
                (SELECT ep.place_id FROM rd_exam_place ep 
                WHERE ep.end_time <= {$end_time} AND ep.exam_pid = {$exam_pid}
                ) c 
                INNER JOIN (
                SELECT etp.place_id, etr.ques_id,etr.exam_id, etr.uid, 
                    etr.paper_id
                FROM rd_exam_test_result etr, rd_question q, 
                    rd_exam_test_paper etp 
                WHERE etr.exam_pid = {$exam_pid} AND etr.ques_id = q.ques_id 
                    AND etr.etp_id = etp.etp_id {$sql_where1}
                ) d ON c.place_id = d.place_id
            )
         ) s ON s.exam_id = b.exam_id AND s.uid = b.uid 
        AND s.paper_id = b.paper_id AND s.ques_id = b.ques_id
)
EOT;
        }
        else
        {
            $sql = <<<EOT
SELECT DISTINCT b.exam_pid, b.exam_id, b.etp_id, b.uid, b.paper_id, b.ques_id, 
    s.ques_id AS etr_ques_id 
FROM (
    (SELECT eq.ques_id, etp.etp_id, etp.exam_pid, etp.exam_id, etp.uid, 
        etp.paper_id
    FROM rd_exam_test_paper etp, rd_exam_question eq, rd_exam_place ep
    WHERE etp.paper_id = eq.paper_id AND etp.place_id = ep.place_id AND 
        ep.end_time <= {$end_time} {$sql_where1}
    ) b LEFT JOIN (
    SELECT d.ques_id,d.exam_id, d.uid, d.paper_id
    FROM (
        (SELECT ep.place_id FROM rd_exam_place ep 
        WHERE ep.end_time <= {$end_time}
        ) c INNER JOIN (
        SELECT etp.place_id, etr.ques_id,etr.exam_id, etr.uid, etr.paper_id
        FROM rd_exam_test_result etr, rd_question q, rd_exam_test_paper etp 
        WHERE etr.ques_id = q.ques_id AND etr.etp_id = etp.etp_id {$sql_where1}
        ) d ON c.place_id = d.place_id)
    ) s ON s.exam_id = b.exam_id AND s.uid = b.uid AND s.paper_id = b.paper_id 
    AND s.ques_id = b.ques_id
)
EOT;
        }
        $CI = self::$CI;
        $result = $CI->db->query($sql)->result_array();
        $paper_ids = array();
        foreach ($result as $item)
        {
            $paper_ids[$item['paper_id']] = 0;
    	}
    	$paper_ids = array_keys($paper_ids);
    	$paper_details = array();
    	$CI->load->model('exam/exam_paper_model');
        foreach ($paper_ids as $paper_id)
        {
            $paper_details[$paper_id] = 
                $CI->exam_paper_model->get_paper_question_detail($paper_id);
    	}
    	
    	$insert_data = array();
    	
    	$CI->load->model('exam/question_model');
    	$question_model = $CI->question_model;
    	
        foreach ($result as $item)
        {
            if (intval($item['etr_ques_id']) > 0)
            {
                continue;
            }
            $ques_id = $item['ques_id'];
            $paper_id = $item['paper_id'];
            $question = $question_model->get_question_detail($ques_id);
            $q_type = $question['type'];
            $ques_info = $paper_details[$paper_id][$q_type]['list'][$ques_id];
            $tmp_data = array(
                'etp_id'    => $item['etp_id'],
                'exam_pid'  => $item['exam_pid'],
                'exam_id'   => $item['exam_id'],
                'uid'       => $item['uid'],
                'paper_id'  => $paper_id,
                'ques_id'   => $ques_id,
                'ques_index'=> $ques_info['index'],
                'ques_subindex' => 0,
                'option_order' 	=> '',
                'answer'        => '',
                'full_score'    => $ques_info['score'],
                'test_score'    => 0
            );
            
            //非题组
            if (in_array($q_type,array(1,2,3,7)))
            {
                if (isset($question['options']) && count($question['options']))
                {
                    if (is_array($question['options']))
                    {
                        shuffle($question['options']);
                        $tmp_data['option_order'] = 
                            implode(',',array_keys($question['options']));
                    }
                    else
                    {
                        $tmp_data['option_order'] = '';
                    }
                }

                $tmp_data['sub_ques_id'] = 0;
                $insert_data[] = $tmp_data;
            }
            else if (in_array($q_type,array(0,4,5,6,8)))
            {
                // 题组子题
                $tmp_data['option_order'] = '';
                $sub_index = 1;
                $left_score = $ques_info['score'];
                foreach ($question['children'] as $key=> &$children)
                {
                    $tmp_data['ques_index'] = 0;
                    $tmp_data['ques_subindex'] = $sub_index;
                    $tmp_data['sub_ques_id'] = $children['ques_id'];
                    if ($children['type'] != 3)
                    {
                        if (is_array($children['options']))
                        {
                            shuffle($children['options']);
                            $tmp_data['option_order'] = 
                                implode(',', array_keys($children['options']));
                        }
                        else
                        {
                            $tmp_data['option_order'] = '';
                        }
                    }
                    else
                    {
                        $tmp_data['option_order'] = '';
                    }
                    if ($sub_index < count($question['children']))
                    {
                        $tmp_data['full_score'] = round(
                            $ques_info['score']/count($question['children']), 2);
                        $left_score -= $tmp_data['full_score'];
                    }
                    else
                    {
                        $tmp_data['full_score'] = $left_score;
                    }
                    $sub_index++;
                    $insert_data[] = $tmp_data;
                }
            }
    	}
    	
        if (count($insert_data))
        {
            try
            {
                $res = $CI->db->insert_batch('exam_test_result', $insert_data);
                if ($res)
                {
                    return true;
                }
                else
                {
                    throw New Exception($this->db->_error_message());
                }
            }
            catch (Exception $e)
            {
                return FALSE;
            }
    	}
    }

    /**
     * 获取所有考试期次数据
     */
    protected function _get_exams() 
    {
    	$CI = self::$CI;
    	
    	$_tables = self::$_tables;
    	
    	$t_exam = $_tables['exam'];
    	
    	$data = array();
		$exams = $CI->db->query("select exam_pid, exam_id, grade_id, class_id, subject_type from {pre}{$t_exam} where exam_pid>0")->result_array();

		foreach ($exams as $exam) {
			$data[$exam['exam_pid'] . '_' . $exam['exam_id']] = $exam;
		}
		
		return $data;
    }
    
    /**
     * 更新 考试期次考到的试题统计信息
     */
    protected function _update_exam_question_stat($data)
    {
    	if (!count($data)) {
    		return;
    	}
    	
    	$CI = self::$CI;
    	 
    	$_tables = self::$_tables;
    	$t_exam_question_stat = $_tables['exam_question_stat'];
    	
    	$ids = array();
    	foreach ($data as $k=>$v) {
    		list($exam_pid, $exam_id, $ques_id, $grade_id, $class_id, $subject_type) = explode('_', $k);
    		$query = $CI->db->query("select id from {pre}{$t_exam_question_stat} where exam_pid={$exam_pid} and exam_id={$exam_id} and ques_id={$ques_id} and grade_id={$grade_id} and class_id={$class_id} and subject_type={$subject_type}");
    		$result = $query->result_array();
    		if (empty($result)) {
    			continue;
    		}
    		foreach ($result as $item) {
	    		$ids[$k] = $item['id'];
    		}
    	}
    	
    	$insert_data = array();
    	$update_data = array();

    	foreach ($data as $k=>$v) {
    		if (isset($ids[$k])) {
	    		$update_data[] = array(
	    			'id'	  			=> $ids[$k],
		    		'mtime' 			=> date('Y-m-d H:i:s'),
		    		'student_amount' 	=> $v['student_amount'],
		    		'right_amount' 		=> $v['right_amount'],
	    		);
    		} else {
    			list($exam_pid, $exam_id, $ques_id, $grade_id, $class_id, $subject_type) = explode('_', $k);
	    		$insert_data[] = array(
		    		'exam_pid' 		=> $exam_pid,
		    		'exam_id' 		=> $exam_id,
		    		'ques_id' 		=> $ques_id,
		    		'grade_id' 		=> $grade_id,
		    		'class_id' 		=> $class_id,
		    		'subject_type' 	=> $subject_type,
		    		'student_amount'=> $v['student_amount'],
	    			'right_amount' 	=> $v['right_amount'],
	    		    'mtime' 		=> date('Y-m-d H:i:s'),
		    		'ctime' 		=> date('Y-m-d H:i:s'),
	    		);
    		}
    	}
    	
    	if (count($insert_data)) {
	    	$CI->db->insert_batch($t_exam_question_stat, $insert_data);
    	}
    	
    	if (count($update_data)) {
	    	$CI->db->update_batch($t_exam_question_stat, $update_data, 'id');
    	}
    }
    
    /**
     * 计算考生的试卷得分
     */
    public function cal_test_paper_score($exam_pid = 0, $uid = 0)
    {
    	$CI = self::$CI;
    	
    	$_tables = self::$_tables;
    	$t_exam_place = $_tables['exam_place'];
    	$t_test_paper = $_tables['test_paper'];
    	$t_test_result = $_tables['test_result'];
    	
    	$exam_pid = intval($exam_pid);
    	$exam_pid_where = '';
        if ($exam_pid > 0)
        {
            $exam_pid_where .= " tp.exam_pid = {$exam_pid} AND ";
    	}
    	
    	$uid = intval($uid);
        if ($uid > 0)
        {
            $exam_pid_where .= " tp.uid = {$uid} AND ";
    	}
    	
        try
        {
    		$end_time = time() - 60;
    		
    		//更新未作弊考生的成绩
                $sql = <<<EOT
SELECT GROUP_CONCAT(DISTINCT tp.etp_id) AS etp_id_str FROM  rd_{$t_test_paper} tp, 
rd_{$t_exam_place} ep, rd_{$t_test_result} tr
WHERE {$exam_pid_where} tp.etp_id = tr.etp_id AND tp.place_id = ep.place_id
 AND ep.end_time <= {$end_time} AND tp.etp_flag >= 0
EOT;
                $etp_id_str = $CI->db->query($sql)->row_array()['etp_id_str'];
                if ($etp_id_str)
                {
                    $etp_id_arr = explode(',', $etp_id_str);
                    $etp_id_str = implode(',', array_filter($etp_id_arr));
                    
                    $sql = <<<EOT
SELECT etp_id, SUM(test_score) AS tscore 
FROM rd_exam_test_result 
WHERE etp_id IN ({$etp_id_str}) 
GROUP BY etp_id
EOT;
                    $score = array();
                    foreach ($CI->db->query($sql)->result_array() as $row)
                    {
                        $score[$row['etp_id']] = $row['tscore'];
                    }
                    foreach ($etp_id_arr as $etp_id)
                    {
                        $tscore = 0;
                        if (isset($score[$etp_id]))
                        {
                            $tscore = $score[$etp_id];
                        }

                        $sql = <<<EOT
UPDATE rd_exam_test_paper SET test_score = {$tscore}, etp_flag = 2
WHERE etp_id = {$etp_id}
EOT;
                        $CI->db->query($sql);
                    }
                }
	    	
    		//更新作弊考生的成绩
                $sql = <<<EOT
SELECT GROUP_CONCAT(DISTINCT tp.etp_id) AS etp_id_str FROM  rd_{$t_test_paper} tp, 
rd_{$t_exam_place} ep, rd_{$t_test_result} tr
WHERE {$exam_pid_where} tp.etp_id = tr.etp_id AND tp.place_id = ep.place_id
 AND ep.end_time <= {$end_time} AND tp.etp_flag < 0
EOT;
                $etp_id_str = $CI->db->query($sql)->row_array()['etp_id_str'];
                if ($etp_id_str)
                {
                    $etp_id_arr = explode(',', $etp_id_str);

                    $score = array();
                    foreach ($CI->db->query($sql)->result_array() as $row)
                    {
                        $score[$row['etp_id']] = $row['tscore'];
                    }
                    foreach ($etp_id_arr as $etp_id)
                    {
                        $tscore = 0;
                        if (isset($score[$etp_id]))
                        {
                            $tscore = $score[$etp_id];
                        }
                        $sql = <<<EOT
UPDATE rd_exam_test_paper SET test_score = {$tscore}, etp_flag = -1
WHERE etp_id = {$etp_id}
EOT;
                        $CI->db->query($sql);
                    }
                }
	    	
	    	//修复考试总分大于试卷总分的考试记录
                $sql = <<<EOT
UPDATE rd_{$t_test_paper} SET test_score = full_score WHERE test_score > full_score
EOT;
	    	$CI->db->query($sql);
        }
        catch(Exception $e)
        {
            throw new Exception('更新 考生试卷 得分失败，更新字段：' 
                . $t_test_paper . '->test_score, Error:' . $e->getMessage());
    	}
    }
    
    /**
     * 更新考试试卷试题的分数
     * @see admin/relate_class_model.php system_cal_question_difficulty()
     */
    public function update_exam_question_score($exam_pid = 0)
    {
    	$exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
    	}
    	
    	$insert_data = array();
    	$update_data = array();
    	
    	//获取老的数据
    	$CI = self::$CI;
    	
        $sql = <<<EOT
SELECT CONCAT(exam_id, '_', paper_id, '_', ques_id) AS k_str, id
FROM rd_exam_question_score WHERE exam_pid = {$exam_pid}
EOT;
    	$old_data = Fn::db()->fetchPairs($sql);

        $sql = <<<EOT
SELECT exam_id, paper_id, ques_id, full_score,
    CONCAT(exam_id, '_', paper_id, '_', ques_id) AS k_str
FROM rd_exam_test_result 
WHERE exam_pid = {$exam_pid} 
GROUP BY exam_id, paper_id, ques_id
EOT;
    	$result = Fn::db()->fetchAll($sql);
    	foreach ($result as &$row)
    	{
            $k = $row['k_str'];
            if (isset($old_data[$k]))
            {
                $update_data[] = array(
                    'id'        => $old_data[$k],
                    'test_score'=> $row['full_score']
                );
                unset($old_data[$k]);
            }
            else
            {
                $insert_data[] = array(
                    'exam_pid'	=> $exam_pid,
                    'exam_id'	=> $row['exam_id'],
                    'paper_id'	=> $row['paper_id'],
                    'ques_id'	=> $row['ques_id'],
                    'test_score'=> $row['full_score']
                );
            }
        }
    	
        if (count($insert_data))
        {
            $CI->db->insert_batch('exam_question_score', $insert_data);
    	}
    	
        if (count($update_data))
        {
    	    $CI->db->update_batch('exam_question_score', $update_data, 'id');
    	}
    	
        if (count($old_data))
        {
    	    $CI->db->where_in('id', array_values($old_data))->delete('exam_question_score');
    	}
    }
    
    /**
     * 系统计算 试题 难易度
     * @see admin/relate_class_model.php system_cal_question_difficulty()
     */
    public function cal_question_difficulty()
    {
    	//$time_start = microtime(true);
    	//$CI = self::$CI;
    	//$CI->load->model('admin/relate_class_model');
        RelateClassModel::system_cal_question_difficulty();
//     	echo 'costs' . (microtime(true) - $time_start) . ' s';
    }
}
