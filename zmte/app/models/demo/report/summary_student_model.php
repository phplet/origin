<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 考生相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_student_model extends CI_Model 
{
	
	public function __construct()
	{
		parent::__construct();
	}
	
	private $_exam_student_papers = array();
	private $_uid = 0;

	/**
	 * 执行 地区 相关所有关联脚本
	 * @param number $exam_pid
	 * @return boolean
	 */
	public function do_all($exam_pid = 0, $uid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		$uid = intval($uid);
		$this->uid = intval($uid);
	
		$uid <= 0 && $this->summary_student_difficulty($exam_pid);
		$this->summary_student_knowledge($exam_pid);
		$uid <= 0 && $this->summary_student_group_type($exam_pid);
		$uid <= 0 && $this->summary_student_method_tactic($exam_pid);
		$uid <= 0 && $this->summary_student_subject_method_tactic($exam_pid);
	}
	
	/**
	 * 关联 难易度和题型 
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_difficulty($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!count($papers) || !count($papers['paper'])) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的难易度和题型 from->{pre}summary_paper_difficulty
		 */
		$insert_data = array();
		$update_data = array();
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
		$sql = "select paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id 
				from {pre}summary_paper_difficulty 
				where paper_id in($all_paper_ids)
			";
		$paper_result = $this->db->query($sql)->result_array();
		$paper_data = array();
		foreach ($paper_result as $item) {
			$paper_data[$item['paper_id']][] = array(
							'q_type' 		=> $item['q_type'], 
							'ques_id' 		=> array(
													'low' => trim($item['low_ques_id']),
													'mid' => trim($item['mid_ques_id']),
													'high' => trim($item['high_ques_id']),
												) 
			);
		}
		
		$data = array();
		foreach ($student_papers as $exam_id=>&$paper) 
		{
			//获取该区域的难易度和题型统计信息
			foreach ($paper as $uid=>&$u_paper_ids) 
			{
				foreach ($u_paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $u_paper) 
					{
						//获取难易度和题型关联试题的试题得分
						$q_type = $u_paper['q_type'];
						$ques_ids = $u_paper['ques_id'];
						$test_scores = array();
						foreach ($ques_ids as $key=>$ques_id)
						{
							//将试题拆分为 题组 和 非题组
							$test_score = 0;
							
							if ($ques_id == '') 
							{
								$test_scores[$key] = $test_score;
								continue;
							}
							
							//题组部分
							$sql = "select ques_id
									from {pre}question
									where ques_id in($ques_id) and parent_id>0
									";
							
							$result = $this->db->query($sql)->result_array();
							$group_ques_id = array();
							foreach ($result as $v) {
								$group_ques_id[] = $v['ques_id'];
							}
							if (count($group_ques_id)) {
								$t_group_ques_id = implode(',', $group_ques_id); 
								$sql = "select sum(test_score) as test_score
										from {pre}exam_test_result
										where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and sub_ques_id in($t_group_ques_id)
										";
								
								$result = $this->db->query($sql)->row_array();
								$test_score += $result['test_score'];
							}
							
							//非题组部分
							$t_ques_id = array_diff(explode(',', $ques_id), $group_ques_id);
							if (count($t_ques_id)) {
								$t_ques_id = implode(',', $t_ques_id);
								$sql = "select sum(test_score) as test_score
										from {pre}exam_test_result
										where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and ques_id in($t_ques_id)
										";
								
								$result = $this->db->query($sql)->row_array();
								$test_score += $result['test_score'];
							}
							
							$test_scores[$key] = $test_score;
						}
						
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'uid' 		=> $uid,
										'q_type'	=> $q_type,
										'low_test_score'	=> $test_scores['low'],
										'mid_test_score'	=> $test_scores['mid'],
										'high_test_score'	=> $test_scores['high'],
						);
					}
				}
			}
		}
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$tmp_sql = $this->uid > 0 ? " and uid=$this->uid " : '';
		$old_result = $this->db->query("select id, exam_id, paper_id, uid, q_type from {pre}summary_student_difficulty where exam_pid=$exam_pid {$tmp_sql}")->result_array();
		
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['q_type'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['q_type'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 				=> $old_ids[$k],
					'low_test_score'	=> $row['low_test_score'],
					'mid_test_score'	=> $row['mid_test_score'],
					'high_test_score'	=> $row['high_test_score'],
				);
				
				unset($old_ids[$k]);
			}
			else 
			{
				$row['ctime'] = time();
				$insert_data[] = $row;
			}
			
			unset($data[$k]);
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('summary_student_difficulty', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_student_difficulty', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_student_difficulty');
		}
	}
	
	/**
	 * 关联 一级知识点
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_knowledge($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!count($papers) || !count($papers['paper'])) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的知识点 from->{pre}summary_paper_knowledge
		 */
		$insert_data = array();
		$update_data = array();

		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
		$sql = "select paper_id, knowledge_id, is_parent, ques_id, know_process_ques_id
				from {pre}summary_paper_knowledge 
				where paper_id in($all_paper_ids)
			";
		$paper_result = $this->db->query($sql)->result_array();
		$paper_data = array();
		foreach ($paper_result as $item) {
			$paper_data[$item['paper_id']][] = array(
							'knowledge_id' 	=> $item['knowledge_id'], 
							'ques_id' 		=> $item['ques_id'], 
							'know_process_ques_id' => $item['know_process_ques_id'], 
							'is_parent' 	=> $item['is_parent']
			);
		}
		
		$data = array();
		foreach ($student_papers as $exam_id=>&$paper) 
		{
			//获取该区域的知识点统计信息
			foreach ($paper as $uid=>&$u_paper_ids) 
			{
				foreach ($u_paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $u_paper) 
					{
						//获取知识点关联试题的试题得分
						$knowledge_id = $u_paper['knowledge_id'];
						$ques_ids = trim($u_paper['ques_id']);
						$know_process_ques_ids = !is_null(json_decode($u_paper['know_process_ques_id'], true)) ? json_decode($u_paper['know_process_ques_id'], true) : unserialize($u_paper['know_process_ques_id']);
						$know_process_ques_ids = !is_array($know_process_ques_ids) ? array() : $know_process_ques_ids;
						$is_parent = $u_paper['is_parent'];
						
						if ($ques_ids == '') continue;
						
						//将试题拆分为 题组 和 非题组
						$test_score = 0;
						
						//题组部分
						$sql = "select ques_id
								from {pre}question
								where ques_id in($ques_ids) and parent_id>0
								";
						
						$result = $this->db->query($sql)->result_array();
						$group_ques_id = array();
						foreach ($result as $v) {
							$group_ques_id[] = $v['ques_id'];
						}
						if (count($group_ques_id)) {
							$t_group_ques_id = implode(',', $group_ques_id); 
							$sql = "select sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and sub_ques_id in($t_group_ques_id)
									";
							
							$result = $this->db->query($sql)->row_array();
							$test_score += $result['test_score'];
						}
						
						//非题组部分
						$t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
						$t_nogroup_ques_id = $t_ques_id;
						if (count($t_ques_id)) {
							$t_ques_id = implode(',', $t_ques_id);
							$sql = "select sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and ques_id in($t_ques_id)
									";
							
							$result = $this->db->query($sql)->row_array();
							$test_score += $result['test_score'];
						}
						
						//统计答对的认知过程关联试题
						$kp_right_ques_ids = array();
						if (count($know_process_ques_ids))
						{
							foreach ($know_process_ques_ids as $know_process=>&$kp_ques_ids) 
							{
								if (!isset($kp_right_ques_ids[$know_process])) $kp_right_ques_ids[$know_process] = array();
								 
								//题组
								$t_g_ques_ids = implode(',', array_intersect($kp_ques_ids, $group_ques_id));
								if ($t_g_ques_ids) 
								{
									$sql = "select sub_ques_id
											from {pre}exam_test_result
											where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and test_score=full_score and full_score > 0 and sub_ques_id in($t_g_ques_ids)
											";
										
									$result = $this->db->query($sql)->result_array();
									foreach ($result as $val) 
									{
										$kp_right_ques_ids[$know_process][] = $val['sub_ques_id'];
									}
								}
								
								//非题组
								$t_ng_ques_ids = implode(',', array_intersect($kp_ques_ids, $t_nogroup_ques_id));
								if ($t_ng_ques_ids)
								{
									$sql = "select ques_id
											from {pre}exam_test_result
											where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and test_score=full_score and full_score > 0 and ques_id in($t_ng_ques_ids)
											";
										
									$result = $this->db->query($sql)->result_array();
									foreach ($result as $val) 
									{
										$kp_right_ques_ids[$know_process][] = $val['ques_id'];
									}
								}
							}
						}
						
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'uid' 		=> $uid,
										'knowledge_id'	=> $knowledge_id,
										'know_process_ques_id'	=> $kp_right_ques_ids,
										'test_score' 	=> $test_score,
										'is_parent' 	=> $is_parent,
						);
					}
				}
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$tmp_sql = $this->uid > 0 ? " and uid=$this->uid " : '';
		$old_result = $this->db->query("select id, exam_id, paper_id, uid, knowledge_id from {pre}summary_student_knowledge where exam_pid=$exam_pid {$tmp_sql}")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['knowledge_id'];
			$old_ids[$k] = $row['id'];
		}
		
		unset($row);
		
		foreach ($data as $k=>$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['knowledge_id'];
			if (isset($old_ids[$k])) 
			{
				$update_data[$k] = array(
					'id' 			=> $old_ids[$k],
					'test_score' 	=> $row['test_score'],
					'know_process_ques_id' 	=> serialize($row['know_process_ques_id']),
				);
				
				unset($old_ids[$k]);
			}
			else 
			{
				$row['ctime'] = time();
				$row['know_process_ques_id'] = serialize($row['know_process_ques_id']);
				$insert_data[$k] = $row;
			}
			
			unset($data[$k]);
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('summary_student_knowledge', array_values($insert_data));
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_student_knowledge', array_values($update_data), 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_student_knowledge');
		}
	}
	
	/**
	 * 关联 方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!count($papers) || !count($papers['paper'])) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的方法策略 from->{pre}summary_paper_method_tactic
		 */
		$insert_data = array();
		$update_data = array();
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
		$sql = "select paper_id, method_tactic_id, ques_id 
				from {pre}summary_paper_method_tactic 
				where paper_id in($all_paper_ids)
			";
		$paper_result = $this->db->query($sql)->result_array();
		$paper_data = array();
		foreach ($paper_result as $item) {
			$paper_data[$item['paper_id']][] = array(
							'method_tactic_id' 	=> $item['method_tactic_id'], 
							'ques_id' 		=> $item['ques_id'], 
			);
		}
		
		$data = array();
		foreach ($student_papers as $exam_id=>&$paper) 
		{
			//获取该区域的方法策略统计信息
			foreach ($paper as $uid=>&$u_paper_ids) 
			{
				foreach ($u_paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $u_paper) 
					{
						//获取方法策略关联试题的试题得分
						$method_tactic_id = $u_paper['method_tactic_id'];
						$ques_ids = trim($u_paper['ques_id']);
						
						if ($ques_ids == '') continue;
						
						//将试题拆分为 题组 和 非题组
						$test_score = 0;
						
						//题组部分
						$sql = "select ques_id
								from {pre}question
								where ques_id in($ques_ids) and parent_id>0
								";
						
						$result = $this->db->query($sql)->result_array();
						$group_ques_id = array();
						foreach ($result as $v) {
							$group_ques_id[] = $v['ques_id'];
						}
						if (count($group_ques_id)) {
							$t_group_ques_id = implode(',', $group_ques_id); 
							$sql = "select sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and sub_ques_id in($t_group_ques_id)
									";
							
							$result = $this->db->query($sql)->row_array();
							$test_score += $result['test_score'];
						}
						
						//非题组部分
						$t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
						if (count($t_ques_id)) {
							$t_ques_id = implode(',', $t_ques_id);
							$sql = "select sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and ques_id in($t_ques_id)
									";
							
							$result = $this->db->query($sql)->row_array();
							$test_score += $result['test_score'];
						}
						
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'uid' 		=> $uid,
										'method_tactic_id'	=> $method_tactic_id,
										'test_score' 	=> $test_score,
						);
					}
				}
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$tmp_sql = $this->uid > 0 ? " and uid=$this->uid " : '';
		$old_result = $this->db->query("select id, exam_id, paper_id, uid, method_tactic_id from {pre}summary_student_method_tactic where exam_pid=$exam_pid {$tmp_sql}")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['method_tactic_id'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['method_tactic_id'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 			=> $old_ids[$k],
					'test_score' 	=> $row['test_score'],
				);
				
				unset($old_ids[$k]);
			}
			else 
			{
				$row['ctime'] = time();
				$insert_data[] = $row;
			}
			
			unset($data[$k]);
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('summary_student_method_tactic', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_student_method_tactic', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_student_method_tactic');
		}
	}
	
	/**
	 * 关联 考生-学科-方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_subject_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!count($papers) || !count($papers['paper'])) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的方法策略 from->{pre}summary_paper_method_tactic
		 */
		$insert_data = array();
		$update_data = array();
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
		$sql = "select paper_id, method_tactic_id, ques_id 
				from {pre}summary_paper_method_tactic 
				where paper_id in($all_paper_ids)
			";
		$paper_result = $this->db->query($sql)->result_array();
		$paper_data = array();
		foreach ($paper_result as $item) {
			$paper_data[$item['paper_id']][] = array(
							'method_tactic_id' 	=> $item['method_tactic_id'], 
							'ques_id' 		=> $item['ques_id'], 
			);
		}
		
		$data = array();
		foreach ($student_papers as $exam_id=>&$paper) 
		{
			//获取该区域的方法策略统计信息
			foreach ($paper as $uid=>&$u_paper_ids) 
			{
				foreach ($u_paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $u_paper) 
					{
						//获取方法策略关联试题的试题得分
						$method_tactic_id = $u_paper['method_tactic_id'];
						$ques_ids = trim($u_paper['ques_id']);
						
						if ($ques_ids == '') continue;
						
						//将试题拆分为 题组 和 非题组
						$test_score = 0;
						
						//题组部分
						$sql = "select ques_id,parent_id
								from {pre}question
								where ques_id in($ques_ids) and parent_id>0
								";
						
						$result = $this->db->query($sql)->result_array();
						$group_ques_id = array();
						foreach ($result as $v) {
							$group_ques_id[] = $v['ques_id'];
						}
						if (count($group_ques_id)) {
							/*
							 * 题组子题的难易度用题干的难易度来算
							 */
							$t_group_ques_id = implode(',', $group_ques_id); 
							$sql = "select etr.test_score, rc.difficulty,etr.ques_id
									from {pre}exam_test_result etr
									left join {pre}exam e on etr.exam_id=e.exam_id
									left join {pre}relate_class rc on etr.ques_id=rc.ques_id and rc.grade_id=e.grade_id and rc.class_id=e.class_id and rc.subject_type=e.subject_type	
									where etr.exam_id=$exam_id and etr.paper_id=$paper_id and etr.uid=$uid and etr.sub_ques_id in($t_group_ques_id)
									";
							
							$result = $this->db->query($sql)->result_array();
							foreach ($result as $val) 
							{
								if ($val['test_score'] > 0) 
								{
									$test_score += (100-$val['difficulty']);
								} 
								else 
								{
									$test_score += (0-$val['difficulty']);
								}
							}
						}
						
						//非题组部分
						$t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
						if (count($t_ques_id)) {
							$t_ques_id = implode(',', $t_ques_id);
							$sql = "select etr.test_score, rc.difficulty
									from {pre}exam_test_result etr
									left join {pre}exam e on etr.exam_id=e.exam_id
									left join {pre}relate_class rc on etr.ques_id=rc.ques_id and rc.grade_id=e.grade_id and rc.class_id=e.class_id and rc.subject_type=e.subject_type	
									where etr.exam_id=$exam_id and etr.paper_id=$paper_id and etr.uid=$uid and etr.ques_id in($t_ques_id)
									";
							
							$result = $this->db->query($sql)->result_array();
							foreach ($result as $val) 
							{
								if ($val['test_score'] > 0) 
								{
									$test_score += (100-$val['difficulty']);
								} 
								else 
								{
									$test_score += (0-$val['difficulty']);
								}
							}
						}
						
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'uid' 		=> $uid,
										'method_tactic_id'	=> $method_tactic_id,
										'usage' 	=> $test_score/count(explode(',', $ques_ids)),
						);
					}
				}
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$tmp_sql = $this->uid > 0 ? " and uid=$this->uid " : '';
		$old_result = $this->db->query("select id, exam_id, paper_id, uid, method_tactic_id from {pre}summary_student_subject_method_tactic where exam_pid=$exam_pid {$tmp_sql}")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['method_tactic_id'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['method_tactic_id'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 			=> $old_ids[$k],
					'usage' 		=> $row['usage'],
				);
				
				unset($old_ids[$k]);
			}
			else 
			{
				$row['ctime'] = time();
				$insert_data[] = $row;
			}
			
			unset($data[$k]);
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('summary_student_subject_method_tactic', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_student_subject_method_tactic', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_student_subject_method_tactic');
		}
	}
	
	/**
	 * 关联 信息提取方式
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_group_type($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid) {
	        return false;
	    }
	
	    //获取 考生的  的考试试卷
	    $papers = $this->_get_exam_student_papers($exam_pid);
	    if (!count($papers) || !count($papers['paper'])) {
	        return false;
	    }
	
	    /*
	     * 获取这些试卷关联的信息提取方式 from->{pre}summary_paper_group_type
	    */
	    $insert_data = array();
	    $update_data = array();
	
	    //循环每个学科进行统计
	    $student_papers = $papers['student_paper'];
	    $all_paper_ids = implode(',', $papers['paper']);
	
	    //该期次考试的所有试卷ID
	    $sql = "select paper_id, group_type_id, is_parent, ques_id
	    from {pre}summary_paper_group_type
	    where paper_id in($all_paper_ids)
	    ";
	    $paper_result = $this->db->query($sql)->result_array();
	    if (empty($paper_result))
	    {
	    	return false;
	    }
	    $paper_data = array();
	    foreach ($paper_result as $item) 
	    {
    	    $paper_data[$item['paper_id']][] = array(
	            'group_type_id' => $item['group_type_id'],
				'ques_id' 		=> $item['ques_id'],
				'is_parent' 	=> $item['is_parent']
    	    );
    	}
    	
    	$data = array();
    	foreach ($student_papers as $exam_id=>&$paper)
    	{
        	//获取该区域的信息提取方式统计信息
        	foreach ($paper as $uid=>&$u_paper_ids)
        	{
            	foreach ($u_paper_ids as &$paper_id)
            	{
                    if (!isset($paper_data[$paper_id])) continue;
                		
                	$paper_info = $paper_data[$paper_id];
                	foreach ($paper_info as $u_paper)
                	{
                	    //获取信息提取方式关联试题的试题得分
                	    $group_type_id = $u_paper['group_type_id'];
                	    $ques_ids = trim($u_paper['ques_id']);
                	    $is_parent = $u_paper['is_parent'];
                	
                	    if ($ques_ids == '' || $group_type_id < 1) continue;
                	
                	    //将试题拆分为 题组 和 非题组
                	    $test_score = 0;
                	
                    	//题组部分
                    	$sql = "select ques_id
                    	from {pre}question
                    	where ques_id in($ques_ids) and parent_id>0
                    	";
                	
                    	$result = $this->db->query($sql)->result_array();
                    	$group_ques_id = array();
                    	foreach ($result as $v) 
                    	{
                    	   $group_ques_id[] = $v['ques_id'];
                    	}
                    	
                    	if (count($group_ques_id)) 
                    	{
                        	$t_group_ques_id = implode(',', $group_ques_id);
                        	$sql = "select sum(test_score) as test_score
                        	from {pre}exam_test_result
                        	where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and sub_ques_id in($t_group_ques_id)
                        	";
                        		
                        	$result = $this->db->query($sql)->row_array();
                        	$test_score += $result['test_score'];
    	                 }
    	
                    	 //非题组部分
                    	 $t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
                    	 $t_nogroup_ques_id = $t_ques_id;
                    	 if (count($t_ques_id)) 
                    	 {
                        	 $t_ques_id = implode(',', $t_ques_id);
                        	 $sql = "select sum(test_score) as test_score
                        	 from {pre}exam_test_result
                        	 where exam_id=$exam_id and paper_id=$paper_id and uid=$uid and ques_id in($t_ques_id)
                        	 ";
                        		
                        	 $result = $this->db->query($sql)->row_array();
                        	 $test_score += $result['test_score'];
                         }
    	
                	     $data[] = array(
                	        'exam_pid'	=> $exam_pid,
                	        'exam_id' 	=> $exam_id,
                	        'paper_id' 	=> $paper_id,
                	        'uid' 		=> $uid,
                	        'group_type_id'	=> $group_type_id,
                	        'test_score' 	=> $test_score,
                	        'is_parent' 	=> $is_parent,
                	     );
        	        }
    	        }
    	    }
    	}
    	
	    if (!count($data)) {
	        return false;
	    }
    	
	    //获取老的汇总数据
	    $tmp_sql = $this->uid > 0 ? " and uid=$this->uid " : '';
	    $old_result = $this->db->query("select id, exam_id, paper_id, uid, group_type_id from {pre}summary_student_group_type where exam_pid=$exam_pid {$tmp_sql}")->result_array();
	    $old_ids = array();
		foreach ($old_result as $row)
	    {
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['group_type_id'];
			$old_ids[$k] = $row['id'];
    	}
    	
        foreach ($data as $k=>$row)
    	{
    	    $k = $row['exam_id']."_".$row['paper_id']."_".$row['uid']."_".$row['group_type_id'];
    	    if (isset($old_ids[$k]))
    	    {
    	        $update_data[$k] = array(
    	        'id' 			=> $old_ids[$k],
    	        'test_score' 	=> $row['test_score'],
    	        );
    	
    	        unset($old_ids[$k]);
    		}
    	    else
    	    {
    		    $row['ctime'] = time();
    			$insert_data[$k] = $row;
    	    }
    	        	
    	    unset($data[$k]);
        }
    	
        if (count($insert_data)) {
            $this->db->insert_batch('summary_student_group_type', array_values($insert_data));
        }
	
		if (count($update_data)) {
		    $this->db->update_batch('summary_student_group_type', array_values($update_data), 'id');
        }

        if (count($old_ids)) {
            $this->db->where_in('id', array_values($old_ids))->delete('summary_student_group_type');
        }
	}
	
	//=====================公共方法===============================================================
	/**
	 * 获取某期考试 按照考生分类
	 */
	private function _get_exam_student_papers($exam_pid = 0)
	{
		if (count($this->_exam_student_papers))
		{
			return $this->_exam_student_papers;
		}
		
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return array();
		}
		
		$data = array();
		
		$tmp_sql = $this->uid > 0 ? " and uid=$this->uid" : ''; 
		
		/*
		 * 前提：已生成成绩
		 */
		$sql = "select exam_id, paper_id, uid , subject_id
				from {pre}exam_test_paper
				where exam_pid=$exam_pid and etp_flag=2 {$tmp_sql}
				";
		
		$result = $this->db->query($sql)->result_array();
		$paper_ids = array();
		foreach ($result as $item)
		{
			$data[$item['exam_id']][$item['uid']][] = $item['paper_id'];
			$paper_ids[] = $item['paper_id'];
		}
		
		$data = array('paper' => array_unique($paper_ids), 'student_paper' => $data);
		$this->_exam_student_papers = $data;
		
		return $this->_exam_student_papers;
	}
}
