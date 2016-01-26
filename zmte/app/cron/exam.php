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
	private static $_sub_question_answer = array();
	
	//考试期次考到的试题统计
	static $exam_question_stats = array();
	
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
	
    		//提交事务
    		return $CI->db->trans_complete();
    		
    	} catch (Exception $e) {
	    	echo $e->getMessage();
	    		 
	    	$CI->db->trans_rollback();
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
    public function cal_test_result_score($exam_pid, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if ($exam_pid < 1)
        {
            return false;
        }
        
    	$CI = self::$CI;
    	
    	//获取所有的考试期次
    	$exams = $this->_get_exams($exam_pid);
    	
    	$sql = "SELECT etr.exam_pid, etr.exam_id, etr.uid, etr.etr_id, etr.ques_id, etr.answer, 
    	        etr.sub_ques_id, etr.full_score, etr.ques_subindex, q.answer as q_answer, q.type
    	        FROM rd_exam_test_result etr 
    	        LEFT JOIN rd_question q ON q.ques_id = etr.ques_id
    	        WHERE etr.exam_pid = $exam_pid " . ($uid > 0 ? " AND etr.uid = $uid" : '');
    	
    	//计算试题得分
    	$query = $CI->db->query($sql);
    	
    	//获取题组子题的信息
    	$sub_ques_ids = array();
    	foreach ($query->result_array() as $item)
    	{
    	    if ($item['sub_ques_id'] > 0)
    	    {
    	        $sub_ques_ids[] = $item['sub_ques_id'];
    	    }
    	}
    	
    	if ($sub_ques_ids)
    	{
    	    $sub_ques_ids = array_unique($sub_ques_ids);
    	     
    	    $sub_question = $CI->db->select('ques_id, answer, type')->from('rd_question')
    	                       ->where_in('ques_id', $sub_ques_ids)->get()->result_array();
    	    $sub_ques_answer = array();
    	    foreach ($sub_question as $ques)
    	    {
    	        $sub_ques_answer[$ques['ques_id']] = array('answer'=>$ques['answer'], 'type'=>$ques['type']);
    	    }
    	    
    	    self::$_sub_question_answer = $sub_ques_answer;
    	}
    	
    	$exam_result_data = array();
    	foreach ($query->result_array() as $row)
    	{
    	    //单选和翻译题
    	    if (in_array($row['type'], array(1, 7))) 
    	    {
    	       $this->_cal_radio_test_score($exams, $row);
    	    }
    	    //不定项
    	    elseif ($row['type'] == 2)
    	    {
    	        $this->_cal_undefined_term_test_score($exams, $row);
    	    }
    	    //填空题和连词成句
    	    elseif (in_array($row['type'], array(3, 9)))
    	    {
    	        $this->_cal_completion_test_score($exams, $row);
    	    }
    	    //题组
    	    elseif (in_array($row['type'], array(0, 4, 5, 6, 8)))
    	    {
    	        $this->_cal_group_test_score($exams, $row);
    	    }
    	}
    	
    	//更新考试期次中题组考到的试题统计信息
    	if ($uid < 1)
    	{
    	    $sql = "SELECT exam_id,etr.ques_id,full_score,SUM(test_score) as get_score
    	            FROM rd_exam_test_result etr
    	            LEFT JOIN rd_question q ON q.ques_id = etr.ques_id
    	            WHERE exam_pid = $exam_pid AND q.type IN (0,4,5,6,8)
    	            GROUP BY exam_id,uid,ques_id";
    	    $query = $CI->db->query($sql);
    	    foreach ($query->result_array() as $item)
    	    {
    	        $exam_id = $item['exam_id'];
    	        $e_k = "{$exam_pid}_{$exam_id}";
    	        if (!isset($exams[$e_k]))
    	        {
    	            continue;
    	        }
    	        $exam = $exams[$e_k];
    	        
    	        $ques_id = $item['ques_id'];
    	        $grade_id = $exam['grade_id'];
    	        $class_id = $exam['class_id'];
    	        $subject_type = $exam['subject_type'];
    	        
    	        $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
    	        
    	        if (!isset(self::$exam_question_stats[$stat_k]))
    	        {
    	            self::$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
    	        }
    	        
    	        self::$exam_question_stats[$stat_k]['student_amount']++;
    	        
    	        if ($item['full_score'] == $item['get_score'])
    	        {
    	            self::$exam_question_stats[$stat_k]['right_amount']++;
    	        }
    	    }
    	    
    	    $this->_update_exam_question_stat($exam_pid, self::$exam_question_stats);
    	}
    }
    
    /**
     * 计算单选和翻译题试题得分
     */
    private function _cal_radio_test_score($exams, $row)
    {
        if (!in_array($row['type'], array(1, 7)))
        {
            return false;
        }
        
        $CI = self::$CI;
        
        //考生试题得分更新数据
        $update_data = array();
        
        $exam_pid = $row['exam_pid'];
        $exam_id = $row['exam_id'];
        $etr_id = $row['etr_id'];
        $ques_id = $row['ques_id'];
        $r_answer = $row['answer'];
        $full_score = $row['full_score'];
        $q_answer = $row['q_answer'];
        
        $e_k = "{$exam_pid}_{$exam_id}";
        if (!isset($exams[$e_k])) {
            return false;
        }
        $exam = $exams[$e_k];
        
        $subject_type = $exam['subject_type'];
        $grade_id = $exam['grade_id'];
        $class_id = $exam['class_id'];
        
        $stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
        
        if (!isset(self::$exam_question_stats[$stat_k])) {
            self::$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
        }
        self::$exam_question_stats[$stat_k]['student_amount']++;
        
        if (!$r_answer || !$q_answer)
        {
            return false;
        }
        
        if ($r_answer == $q_answer) {
            $update_data = array(
                    'test_score'  	=> $full_score,
            );
             
            self::$exam_question_stats[$stat_k]['right_amount']++;
        }
        
        //更新分数
        if ($update_data) {
	    	$CI->db->update("exam_test_result", $update_data, "etr_id = $etr_id");
    	}
    }
    
    /**
     * 计算不定项得分
     */
    private function _cal_undefined_term_test_score($exams, $row)
    {
        if ($row['type'] != 2)
        {
            return false;
        }
        
        $CI = self::$CI;
    
        //考生试题得分更新数据
        $update_data = array();
    
        $exam_pid = $row['exam_pid'];
		$exam_id = $row['exam_id'];
		$etr_id = $row['etr_id'];
		$ques_id = $row['ques_id'];
		$r_answer = trim($row['answer']);
		$q_answer = trim($row['q_answer']);
		$full_score = $row['full_score'];
		
		$e_k = "{$exam_pid}_{$exam_id}";
		if (!isset($exams[$e_k])) {
			return false;
		}
		
		$exam = $exams[$e_k];
		$subject_type = $exam['subject_type'];
		$grade_id = $exam['grade_id'];
		$class_id = $exam['class_id'];
	
		$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
		if (!isset(self::$exam_question_stats[$stat_k])) {
			self::$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
		}
		self::$exam_question_stats[$stat_k]['student_amount']++;
		
		if ($r_answer == '' || $q_answer == '') {
			return false;
		}
		
		$r_answer = explode(',', $r_answer);
		$q_answer = explode(',', $q_answer);
		
		sort($r_answer);
		sort($q_answer);
		
		if ($r_answer == $q_answer) 
		{
			//全部答对
			$update_data = array(
					'test_score'  	=> $full_score,
			);
			self::$exam_question_stats[$stat_k]['right_amount']++;
		}
		else
		{
			//答对部分选项,按照答对部分与总分占比 来给分
			if (!count(array_diff($r_answer, $q_answer)))
			{
			    $count_r_answer = count($r_answer);
			    $count_q_answer = count($q_answer);
			    
				$test_score = round($count_r_answer*$full_score/$count_q_answer, 2);
				$update_data = array(
						'test_score' => $test_score,
				);
			}
		} 
    
        //更新分数
        if ($update_data) {
	    	$CI->db->update("exam_test_result", $update_data, "etr_id = $etr_id");
    	}
    }
    
    /**
     * 计算 填空 得分
     */
    private function _cal_completion_test_score($exams, $row)
    {
        if ($row['type'] != 3)
        {
            return false;
        }
    
        $CI = self::$CI;
    
        //考生试题得分更新数
        $update_data = array();
        
        $exam_pid = $row['exam_pid'];
		$exam_id = $row['exam_id'];
		$etr_id = $row['etr_id'];
		$ques_id = $row['ques_id'];
		$r_answer = trim($row['answer']);
		$q_answer = trim($row['q_answer']);
		$full_score = $row['full_score'];

		$e_k = "{$exam_pid}_{$exam_id}";
		if (!isset($exams[$e_k])) {
			return false;
		}
		
		$exam = $exams[$e_k];
		$subject_type = $exam['subject_type'];
		$grade_id = $exam['grade_id'];
		$class_id = $exam['class_id'];

		$stat_k = "{$exam_pid}_{$exam_id}_{$ques_id}_{$grade_id}_{$class_id}_{$subject_type}";
		if (!isset(self::$exam_question_stats[$stat_k])) {
			self::$exam_question_stats[$stat_k] = array('student_amount' => 0, 'right_amount' => 0);
		}
		self::$exam_question_stats[$stat_k]['student_amount']++;

		if ($r_answer == '' || $q_answer == '') {
			return false;
		}

		$r_answer = explode("\n", $r_answer);
		$q_answer = explode("\n", $q_answer);

		if ($r_answer === $q_answer)
		{
		    $update_data = array(
		            'test_score'  	=> $full_score,
		    );
		    
		    self::$exam_question_stats[$stat_k]['right_amount']++;
		}
		else 
		{
		    $count_q_answer = count($q_answer);
		    
		    $right_answer = 0;
		    foreach ($q_answer as $k => $v) {
		        if (isset($r_answer[$k]) && trim($r_answer[$k]) === trim($v)) {
		            $right_answer++;
		        }
		    }
		    
		    $get_score = round($right_answer*$full_score/$count_q_answer, 2);
		    
		    if ($get_score > 0)
		    {
		        $update_data = array(
		                'test_score' => $get_score,
		        );
		    }
		    
		    if ($get_score == $full_score) {
		        self::$exam_question_stats[$stat_k]['right_amount']++;
		    }
		}
		
		//更新分数
		if ($update_data) {
		    $CI->db->update("exam_test_result", $update_data, "etr_id = $etr_id");
		}
    }
    
 
	/**
	 * 计算 题组 得分
	 */
    private function _cal_group_test_score($exams, $row)
    {
        if (!in_array($row['type'], array(0, 4, 5, 6, 8)))
        {
            return false;
        }
        
        $CI = self::$CI;
        
        //考生试题得分更新数
        $update_data = array();
        
        $etr_id = $row['etr_id'];
        $sub_ques_id = $row['sub_ques_id'];
        $r_answer = trim($row['answer']);
        $full_score = $row['full_score'];
        
        //获取题组的子题信息
        $child_result = self::$_sub_question_answer[$sub_ques_id];
    	
    	$q_answer = $child_result['answer'];
    	$type = $child_result['type'] ? $child_result['type'] : $row['type'];
    	
    	if (!$r_answer || !$q_answer)
    	{
    	    return false;
    	}
    	
		if ($type == '1') {
			//单选
			if ($r_answer == $q_answer) {
				$update_data = array(
					'test_score'  => $full_score,
				);
			}
		} elseif ($type == '2') {
			//不定项
			$r_answer = explode(',', $r_answer);
			$q_answer = explode(',', $q_answer);

			asort($r_answer);
			asort($q_answer);

			$count_r_answer = count($r_answer);
			$count_q_answer = count($q_answer);
			if ($count_r_answer == $count_q_answer 
			    && !count(array_diff($q_answer, $r_answer))) {
				//全部答对
				$update_data = array(
				    'test_score' => $full_score,
				);
			} else {
				//答对部分选项,按照答对部分与总分占比 来给分
				if (!count(array_diff($r_answer, $q_answer))) {
					$test_score = round($count_r_answer*$full_score/$count_q_answer, 2);
					$update_data = array(
						'test_score' => $test_score,
					);
				}
			}
		} elseif (in_array($type, array(3, 5, 6, 8))) {
			//填空
			$r_answer = explode("\n", $r_answer);
			$q_answer = explode("\n", $q_answer);
			
			if ($r_answer === $q_answer)
			{
			    $update_data = array(
			            'test_score'  	=> $full_score,
			    );
			}
			else
			{
    			$count_q_answer = count($q_answer);
    			$right_answer = 0;
    			
    			foreach ($q_answer as $k=>$v) {
    				if (isset($r_answer[$k]) && trim($r_answer[$k]) === trim($v)) {
    					$right_answer++;
    				}
    			}
    			
    			$get_score = round(($right_answer*$full_score/$count_q_answer), 2);
    			if ($get_score > 0)
    			{
    			    $update_data = array(
    		            'test_score' => $get_score,
    			    );
    			}
			}
		}
		
		//更新分数
		if ($update_data) {
		    $CI->db->update("exam_test_result", $update_data, "etr_id = $etr_id");
		}
    }
   
    /**
     * 将考生未答的试题填充到rd_exam_test_result表中
     * note:
     * 	关联表：
     * 		rd_exam rd_exam_place rd_exam_test_result rd_exam_test_paper rd_exam_question
     */
    public function fill_unanswer_questions($exam_pid, $uid = 0)
    {
    	$exam_pid = intval($exam_pid);
    	if ($exam_pid < 1)
    	{
    	    return false;
    	}
    	
    	$CI = self::$CI;
    	
    	//本次考试中所有考试信息
    	$sql = "SELECT a.exam_pid,a.exam_id, a.etp_id, a.uid, a.paper_id, b.ques_id
    	        FROM rd_exam_test_paper a 
    	        LEFT JOIN rd_exam_test_paper_question b ON a.etp_id = b.etp_id 
    	        WHERE a.exam_pid = $exam_pid
    	        " . ($uid > 0 ? " AND a.uid = $uid" : '');
    	$result = $CI->db->query($sql)->result_array();
    	if (!$result)
    	{
    	    return false;
    	}
    	
    	$paper_ids = array();
    	$paper_details = array();
    	$CI->load->model('exam/exam_paper_model');
    	
    	//考试信息
    	$exam_data = array();
    	foreach ($result as $item)
    	{
    	    $paper_id = $item['paper_id'];
    	    
    	    if (!isset($paper_details[$paper_id]))
    	    {
    	        $paper_details[$paper_id] = $CI->exam_paper_model->get_paper_question_detail($item['paper_id']);
    	    }
    		
    		$ques_id = explode(',', $item['ques_id']);
    		foreach ($ques_id as $q_id)
    		{
    		    if ($q_id > 0)
    		    {
    		        $exam_data[] = "{$item['exam_id']}_{$item['etp_id']}_{$item['uid']}_{$item['paper_id']}_{$q_id}";
    		    }
    		}
    	}
    	
    	//本次考试所有学生答题情况
    	$sql = "SELECT exam_pid,exam_id,etp_id,uid,paper_id,ques_id 
    	        FROM rd_exam_test_result WHERE exam_pid = $exam_pid
    	       " . ($uid > 0 ? " AND uid = $uid" : '');
    	$exam_test_result = $CI->db->query($sql)->result_array();
    	
    	//本次考试期次考试未答的试题
    	$data = array();
    	if (!$exam_test_result)
    	{
    	    $data = $exam_data;
    	}
    	else
    	{
    	    //考试已答试题
    	    $test_data = array();
    	    foreach ($exam_test_result as $item)
    	    {
    	        $test_data[] = "{$item['exam_id']}_{$item['etp_id']}_{$item['uid']}_{$item['paper_id']}_{$item['ques_id']}";
    	    }
    	    
    	    $data = array_diff($exam_data , $test_data);
    	}
    	
    	if (!$data)
    	{
    	    return false;
    	}
    	
    	$insert_data = array();
    	$tmp_question = array();
    	
    	$CI->load->model('exam/question_model');
    	$question_model = $CI->question_model;
    	
    	foreach ($data as $item) {
    	    
    	    $val = explode('_', $item);
    	    
    	    @list($exam_id,$etp_id, $uid, $paper_id, $ques_id) = $val;
    		
    	    if (isset($tmp_question[$ques_id]))
    	    {
    	        $question = $tmp_question[$ques_id];
    	    }
    	    else 
    	    {
    	        $question = $question_model->get_question_detail($ques_id);
    	        
    	        $tmp_question[$ques_id] = $question;
    	    }
    		
    		$q_type = $question['type'];
    		$ques_info = $paper_details[$paper_id][$q_type]['list'][$ques_id];
    		$tmp_data = array(
    			'etp_id' 		=> $etp_id,
    			'exam_pid' 		=> $exam_pid,
    			'exam_id' 		=> $exam_id,
    			'uid' 			=> $uid,
    			'paper_id' 		=> $paper_id,
    			'ques_id' 		=> $ques_id,
    			'ques_index' 	=> $ques_info['index'],
    			'ques_subindex' => 0,
    			'option_order' 	=> '',
    			'answer' 		=> '',
    			'full_score' 	=> $ques_info['score'],
    			'test_score' 	=> 0,
			);
    		
    		//非题组
    		if (in_array($q_type,array(1,2,3,7,9))) {
    			if (isset($question['options']) && count($question['options']))
    			{
	    			shuffle($question['options']);
	    			$tmp_data['option_order'] = implode(',',array_keys($question['options']));
    			}
    			
	    		$tmp_data['sub_ques_id'] = 0;
    			$insert_data[] = $tmp_data;
    		} elseif (in_array($q_type,array(0,4,5,6,8))) {
	    		// 题组子题
	    		$tmp_data['option_order'] = '';
				$sub_index = 1;
	    		$left_score = $ques_info['score'];
    			foreach ($question['children'] as $key=> &$children) {
	    			//$tmp_data['ques_index'] = 0;
	    			$tmp_data['ques_subindex'] = $sub_index;
	    			$tmp_data['sub_ques_id'] = $children['ques_id'];
	    			if ($children['type'] != 3 && $children['options']) {
		    			shuffle($children['options']);
		    			$tmp_data['option_order'] = implode(',', array_keys($children['options']));
	    			} else {
	    				$tmp_data['option_order'] = '';
    				}
    				
					if ($sub_index < count($question['children'])) {
    					$tmp_data['full_score'] = round($ques_info['score']/count($question['children']), 2);
    					$left_score -= $tmp_data['full_score'];
					} else {
						$tmp_data['full_score'] = $left_score;
					}
    		
					$sub_index++;
					
					$insert_data[] = $tmp_data;
				}
			}
    	}
    	
    	if (count($insert_data)) {
    		try {
    			$res = $CI->db->insert_batch('exam_test_result', $insert_data);
    			if ($res) {
    				return true;
    			} else {
    				throw New Exception($this->db->_error_message());
    			}
    		} catch (Exception $e) {
    			return FALSE;
    		}
    	}
    }
    
    /**
     * 获取所有考试期次数据
     */
    protected function _get_exams($exam_pid) 
    {
    	$CI = self::$CI;
    	
    	$_tables = self::$_tables;
    	
    	$t_exam = $_tables['exam'];
    	
    	$data = array();
		$exams = $CI->db->query("select exam_pid, exam_id, grade_id, class_id, subject_type from {pre}{$t_exam} where exam_pid = $exam_pid")->result_array();

		foreach ($exams as $exam) {
			$data[$exam['exam_pid'] . '_' . $exam['exam_id']] = $exam;
		}
		
		return $data;
    }
    
    /**
     * 更新 考试期次考到的试题统计信息
     */
    protected function _update_exam_question_stat($exam_pid, $data)
    {
    	if (!$exam_pid || !count($data)) {
    		return;
    	}
    	
    	$CI = self::$CI;
    	 
    	$_tables = self::$_tables;
    	$t_exam_question_stat = $_tables['exam_question_stat'];
    	
    	$insert_data = array();
    	$update_data = array();
    	
    	$query = $CI->db->query("SELECT id,exam_pid,exam_id,ques_id,grade_id,class_id,subject_type 
	             FROM {pre}{$t_exam_question_stat} where exam_pid={$exam_pid}");
    	foreach ($query->result_array() as $item)
    	{
    	    $stat_k = "{$item['exam_pid']}_{$item['exam_id']}_{$item['ques_id']}_{$item['grade_id']}_{$item['class_id']}_{$item['subject_type']}";
    	    if (isset($data[$stat_k]))
    	    {
    	        $update_data[] = array(
    	                'id'	  			=> $item['id'],
    	                'mtime' 			=> date('Y-m-d H:i:s'),
    	                'student_amount' 	=> $data[$stat_k]['student_amount'],
    	                'right_amount' 		=> $data[$stat_k]['right_amount'],
    	        );
    	        
    	        unset($data[$stat_k]);
    	    }
    	}
    	
    	if ($data)
    	{
    	    foreach ($data as $k => $v) {
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
    	            'mtime' 	    => date('Y-m-d H:i:s'),
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
    public function cal_test_paper_score($exam_pid, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if ($exam_pid < 1)
        {
            return false;
        }
        
    	$CI = self::$CI;
    	
    	$_tables = self::$_tables;
    	$t_exam_place = $_tables['exam_place'];
    	$t_test_paper = $_tables['test_paper'];
    	$t_test_result = $_tables['test_result'];
    		
		//更新未作弊考生的成绩
    	$sql = "UPDATE {pre}{$t_test_paper} tp
				SET tp.test_score=IFNULL((SELECT SUM(test_score) FROM {pre}{$t_test_result} tr WHERE tp.etp_id=tr.etp_id),1), tp.etp_flag=2 
				WHERE tp.exam_pid = $exam_pid AND tp.etp_flag >= 0" . ($uid > 0 ? " AND tp.uid = $uid" : '');
    	$CI->db->query($sql);
    	
		//更新作弊考生的成绩
    	$sql = "UPDATE {pre}{$t_test_paper} tp
				SET tp.test_score=IFNULL((SELECT SUM(test_score) FROM {pre}{$t_test_result} tr WHERE tp.etp_id=tr.etp_id),0), tp.etp_flag=-1
				WHERE tp.exam_pid = $exam_pid AND tp.etp_flag < 0" . ($uid > 0 ? " AND tp.uid = $uid" : '');
    	$CI->db->query($sql);
	    	
    	//修复考试总分大于试卷总分的考试记录
    	$sql = "UPDATE {pre}{$t_test_paper} SET test_score=full_score WHERE test_score > full_score";
    	$CI->db->query($sql);
    }
    
    /**
     * 更新考试试卷试题的分数
     * @see admin/relate_class_model.php system_cal_question_difficulty()
     */
    public function update_exam_question_score($exam_pid = 0)
    {
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		return false;
    	}
    	
    	$insert_data = array();
    	$update_data = array();
    	
    	//获取老的数据
    	$CI = self::$CI;
    	
    	$old_result = $CI->db->query("select id, exam_id, paper_id, ques_id from {pre}exam_question_score where exam_pid={$exam_pid}")->result_array();
    	$old_data = array();
    	foreach ($old_result as $row) {
    		$k = $row['exam_id']."_".$row['paper_id']."_".$row['ques_id'];
    		$old_data[$k] = $row['id'];
    	}
    	
    	$sql = "select exam_id, paper_id, ques_id, full_score
				from rd_exam_test_result 
				where exam_pid={$exam_pid} 
				group by exam_id, paper_id, ques_id";
    	
    	$result = $CI->db->query($sql)->result_array();
    	foreach ($result as &$row)
    	{
    		$k = $row['exam_id']."_".$row['paper_id']."_".$row['ques_id'];
    		if (isset($old_data[$k])) {
    			$update_data[] = array(
    					'id'			=> $old_data[$k],
    					'test_score'	=> $row['full_score'],
    			);
    			unset($old_data[$k]);
    		} else {
    			$insert_data[] = array(
    					'exam_pid'	=> $exam_pid,
    					'exam_id'	=> $row['exam_id'],
    					'paper_id'	=> $row['paper_id'],
    					'ques_id'	=> $row['ques_id'],
    					'test_score'=> $row['full_score'],
    			);
    		}
    	}
    	
    	if (count($insert_data)) {
    		$CI->db->insert_batch('exam_question_score', $insert_data);
    	}
    	
    	if (count($update_data)) {
    		$CI->db->update_batch('exam_question_score', $update_data, 'id');
    	}
    	
    	if (count($old_data)) {
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
