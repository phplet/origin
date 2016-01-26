<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 地区相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_region_model extends CI_Model 
{
	public function __construct()
	{
		parent::__construct();
	}
	
	private  $_exam_data = array();
	private  $_exam_paper_data = array();
	private  $_exam_student_data = array();
	
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
		$this->uid = intval($uid);
	
		$uid <= 0 && $this->summary_region_difficulty($exam_pid);
		$uid <= 0 && $this->summary_region_knowledge($exam_pid);
		$uid <= 0 && $this->summary_region_method_tactic($exam_pid);
		$uid <= 0 && $this->summary_region_group_type($exam_pid);
		$this->summary_region_question($exam_pid);
		$this->summary_region_student_rank($exam_pid);
		$uid <= 0 && $this->summary_region_subject($exam_pid);
	}
	
	/**
	 * 关联 难易度和题型 
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_difficulty($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!count($papers)) {
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的题型难易度 from->{pre}summary_paper_difficulty
		 */
		$insert_data = array();
		$update_data = array();

		//循环每个学科进行统计
		$data = array();
		$tmp_data = array();
		foreach ($exams as $exam_id) 
		{
			if (!isset($papers[$exam_id])) {
				continue;
			} 
				
			//遍历区域
			$tmp_papers = $papers[$exam_id];
			
			//所有该学科所有试卷ID
			$all_paper_ids = implode(',', $tmp_papers[1]);
			$sql = "select paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id 
					from {pre}summary_paper_difficulty 
					where paper_id in($all_paper_ids)
				";
			$paper_result = $this->db->query($sql)->result_array();
			$paper_data = array();
			foreach ($paper_result as $item) {
				$paper_data[$item['paper_id']][] = array(
								'q_type' 	=> $item['q_type'], 
								'ques_id' 	=> array(
													'low' => trim($item['low_ques_id']),
													'mid' => trim($item['mid_ques_id']),
													'high' => trim($item['high_ques_id']),
												) 
				);
			}
			
			//获取该区域的题型难易度统计信息
			foreach ($tmp_papers as $region_id=>&$paper_ids) 
			{
				$uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
				if (!count($uids))
				{
					continue;
				}
				$uids = implode(',', $uids);
				
				foreach ($paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $paper) 
					{
						//获取题型难易度关联试题的总分和试题得分
						$q_type = $paper['q_type'];
						
						$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
						$t_region_id = str_ireplace('school', '', $region_id);
						
						$k = $exam_id."_".$paper_id."_".$t_region_id."_".$is_schoool."_".$q_type;
						if (in_array($k, $tmp_data))
						{
							continue;
						}
						
						$ques_ids = $paper['ques_id'];
						$total_scores = array();
						$test_scores = array();
						foreach ($ques_ids as $key=>$ques_id) 
						{
							//将试题拆分为 题组 和 非题组
							$total_score = 0;
							$test_score = 0;
							
							if ($ques_id == '') 
							{
								$total_scores[$key] = $total_score;
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
								$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
										from {pre}exam_test_result
										where exam_id=$exam_id and paper_id=$paper_id and sub_ques_id in($t_group_ques_id) and uid in ({$uids})
										";
								
								$result = $this->db->query($sql)->row_array();
								$total_score += $result['total_score'];
								$test_score += $result['test_score'];
							}
							
							//非题组部分
							$t_ques_id = array_diff(explode(',', $ques_id), $group_ques_id);
							if (count($t_ques_id)) {
								$t_ques_id = implode(',', $t_ques_id);
								$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
										from {pre}exam_test_result
										where exam_id=$exam_id and paper_id=$paper_id and ques_id in($t_ques_id) and uid in ({$uids})
										";
								
								$result = $this->db->query($sql)->row_array();
								$total_score += $result['total_score'];
								$test_score += $result['test_score'];
							}
							
							$total_scores[$key] = $total_score;
							$test_scores[$key] = $test_score;
						}
						
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'region_id' => str_ireplace('school', '', $region_id),
										'is_school' => $is_schoool,
										'q_type'	=> $q_type,
										'low_total_score'	=> $total_scores['low'],
										'mid_total_score'	=> $total_scores['mid'],
										'high_total_score'	=> $total_scores['high'],
										'low_test_score'	=> $test_scores['low'],
										'mid_test_score'	=> $test_scores['mid'],
										'high_test_score'	=> $test_scores['high'],
										'low_ques_id'		=> $ques_ids['low'],
										'mid_ques_id'		=> $ques_ids['mid'],
										'high_ques_id'		=> $ques_ids['high'],
						);
						
						$tmp_data[] = $k;
					}
				}
			}
		}
// 		pr($data,1);
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, paper_id, region_id, is_school, q_type from {pre}summary_region_difficulty where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['q_type'];
			$old_ids[$k] = $row['id'];
		}
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['q_type'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 				=> $old_ids[$k],
					'low_total_score'	=> $row['low_total_score'],
					'mid_total_score'	=> $row['mid_total_score'],
					'high_total_score'	=> $row['high_total_score'],
					'low_test_score'	=> $row['low_test_score'],
					'mid_test_score'	=> $row['mid_test_score'],
					'high_test_score'	=> $row['high_test_score'],
					'low_ques_id'		=> $row['low_ques_id'],
					'mid_ques_id'		=> $row['mid_ques_id'],
					'high_ques_id'		=> $row['high_ques_id'],
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
// 		pr($insert_data,1);
		if (count($insert_data)) {
			$this->db->insert_batch('summary_region_difficulty', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_difficulty', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_difficulty');
		}
	}
	
	/**
	 * 关联 一级知识点
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_knowledge($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!count($papers)) {
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的知识点 from->{pre}summary_paper_knowledge
		 */
		$insert_data = array();
		$update_data = array();

		//循环每个学科进行统计
		$data = array();
		foreach ($exams as $exam_id) 
		{
			if (!isset($papers[$exam_id])) {
				continue;
			} 
				
			//遍历区域
			$tmp_papers = $papers[$exam_id];
			
			//所有该学科所有试卷ID
			$all_paper_ids = implode(',', $tmp_papers[1]);
			$sql = "select paper_id, knowledge_id, is_parent, ques_id 
					from {pre}summary_paper_knowledge 
					where paper_id in($all_paper_ids)
				";
			$paper_result = $this->db->query($sql)->result_array();
			$paper_data = array();
			foreach ($paper_result as $item) {
				$paper_data[$item['paper_id']][] = array(
								'knowledge_id' 	=> $item['knowledge_id'], 
								'ques_id' 		=> $item['ques_id'], 
								'is_parent' 	=> $item['is_parent']
				);
			}
			
			//获取该区域的知识点统计信息
			foreach ($tmp_papers as $region_id=>&$paper_ids) 
			{
				$uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
				if (!count($uids))
				{
					continue;
				}
				$uids = implode(',', $uids);
				
				foreach ($paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $paper) 
					{
						//获取知识点关联试题的总分和试题得分
						$knowledge_id = $paper['knowledge_id'];
						$ques_ids = trim($paper['ques_id']);
						$is_parent = $paper['is_parent'];
						
						if ($ques_ids == '') continue;
						
						//将试题拆分为 题组 和 非题组
						$total_score = 0;
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
							$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and sub_ques_id in($t_group_ques_id) and uid in ({$uids})
									";
							
							$result = $this->db->query($sql)->row_array();
							$total_score += $result['total_score'];
							$test_score += $result['test_score'];
						}
						
						//非题组部分
						$t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
						if (count($t_ques_id)) {
							$t_ques_id = implode(',', $t_ques_id);
							$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and ques_id in($t_ques_id) and uid in ({$uids})
									";
							
							$result = $this->db->query($sql)->row_array();
							$total_score += $result['total_score'];
							$test_score += $result['test_score'];
						}
						
						$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'region_id' => str_ireplace('school', '', $region_id),
										'is_school' => $is_schoool,
										'knowledge_id'	=> $knowledge_id,
										'ques_id'		=> $ques_ids,
										'total_score' 	=> $total_score,
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
		$old_result = $this->db->query("select id, exam_id, paper_id, region_id, is_school, knowledge_id from {pre}summary_region_knowledge where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['knowledge_id'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['knowledge_id'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 			=> $old_ids[$k],
					'ques_id' 		=> $row['ques_id'],
					'total_score' 	=> $row['total_score'],
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
			$this->db->insert_batch('summary_region_knowledge', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_knowledge', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_knowledge');
		}
	}
	
	/**
	 * 关联 方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!count($papers)) {
			return false;
		}

		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 获取这些试卷关联的知识点 from->{pre}summary_paper_method_tactic
		 */
		$insert_data = array();
		$update_data = array();

		//循环每个学科进行统计
		$data = array();
		foreach ($exams as $exam_id) 
		{
			if (!isset($papers[$exam_id])) {
				continue;
			} 
				
			//遍历区域
			$tmp_papers = $papers[$exam_id];
			
			//所有该学科所有试卷ID
			$all_paper_ids = implode(',', $tmp_papers[1]);
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
			
			//获取该区域的方法策略统计信息
			foreach ($tmp_papers as $region_id=>&$paper_ids) 
			{
				$uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
				if (!count($uids))
				{
					continue;
				}
				$uids = implode(',', $uids);
				
				foreach ($paper_ids as &$paper_id) 
				{
					if (!isset($paper_data[$paper_id])) continue;
					
					$paper_info = $paper_data[$paper_id];
					foreach ($paper_info as $paper) 
					{
						//获取方法策略关联试题的总分和试题得分
						$method_tactic_id = $paper['method_tactic_id'];
						$ques_ids = trim($paper['ques_id']);
						
						if ($ques_ids == '') continue;
						
						//将试题拆分为 题组 和 非题组
						$total_score = 0;
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
							$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and sub_ques_id in($t_group_ques_id) and uid in ({$uids})
									";
							
							$result = $this->db->query($sql)->row_array();
							$total_score += $result['total_score'];
							$test_score += $result['test_score'];
						}
						
						//非题组部分
						$t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
						if (count($t_ques_id)) {
							$t_ques_id = implode(',', $t_ques_id);
							$sql = "select sum(full_score) as total_score, sum(test_score) as test_score
									from {pre}exam_test_result
									where exam_id=$exam_id and paper_id=$paper_id and ques_id in($t_ques_id) and uid in ({$uids})
									";
							
							$result = $this->db->query($sql)->row_array();
							$total_score += $result['total_score'];
							$test_score += $result['test_score'];
						}
						
						$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
						$data[] = array(
										'exam_pid'	=> $exam_pid,
										'exam_id' 	=> $exam_id,
										'paper_id' 	=> $paper_id,
										'region_id' => str_ireplace('school', '', $region_id),
										'is_school' => $is_schoool,
										'method_tactic_id'	=> $method_tactic_id,
										'ques_id'		=> $ques_ids,
										'total_score' 	=> $total_score,
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
		$old_result = $this->db->query("select id, exam_id, paper_id, region_id, is_school, method_tactic_id from {pre}summary_region_method_tactic where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['method_tactic_id'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row) 
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['method_tactic_id'];
			if (isset($old_ids[$k])) 
			{
				$update_data[] = array(
					'id' 			=> $old_ids[$k],
					'ques_id' 		=> $row['ques_id'],
					'total_score' 	=> $row['total_score'],
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
			$this->db->insert_batch('summary_region_method_tactic', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_method_tactic', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_method_tactic');
		}
	}
	
	/**
	 * 关联 试题
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_question($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		//获取本期考试考到的试题
		$exam_questions = $this->_get_exam_ques($exam_pid);
		if (!count($exam_questions)) {
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 获取这些试题的：试题总分、答该题总人数、平均分
		*/
		$insert_data = array();
		$update_data = array();
		
		//循环每个学科进行统计
		$data = array();
		foreach ($exams as $exam_id)
		{
			if (!isset($exam_questions[$exam_id])) {
				continue;
			}
		
			//遍历区域
			$tmp_questions = $exam_questions[$exam_id];
				
			//获取该区域的统计信息
			foreach ($tmp_questions as $region_id=>&$ques_ids)
			{
				$uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
				if (!count($uids))
				{
					continue;
				}
				$uids = implode(',', $uids);
				
				foreach ($ques_ids as &$ques_id)
				{
					$total_score = 0;
					$test_score = 0;
					$student_amount = 0;
					
					//获取试题总分总得分、考该题人数
					/*
					 * 过滤作弊的成绩
					 */
					$sql = "select sum(etr.full_score) as total_score, sum(etr.test_score) as test_score, count(distinct(etr.uid)) as student_amount 
							from {pre}exam_test_result etr, {pre}exam_test_paper etp 
							where etr.etp_id=etp.etp_id and etr.exam_id=$exam_id and etr.ques_id=$ques_id and etp.uid in ({$uids})
							";
					$result = $this->db->query($sql)->row_array();
					$total_score = $result['total_score'];
					$test_score = $result['test_score'];
					$student_amount = $result['student_amount'];
					
					$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
					$data[] = array(
							'exam_pid'	=> $exam_pid,
							'exam_id' 	=> $exam_id,
							'ques_id' 	=> $ques_id,
							'region_id' => str_ireplace('school', '', $region_id),
							'is_school' => $is_schoool,
							'total_score'		=> $total_score,
							'test_score'		=> $test_score,
							'student_amount' 	=> $student_amount,
							'avg_score' 		=> $test_score/$student_amount,
					);
				}
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, ques_id, region_id, is_school from {pre}summary_region_question where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['ques_id']."_".$row['region_id']."_".$row['is_school'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row)
		{
			$k = $row['exam_id']."_".$row['ques_id']."_".$row['region_id']."_".$row['is_school'];
			if (isset($old_ids[$k]))
			{
				$update_data[] = array(
						'id' 			=> $old_ids[$k],
						'total_score' 	=> $row['total_score'],
						'test_score' 	=> $row['test_score'],
						'student_amount'=> $row['student_amount'],
						'avg_score' 	=> $row['avg_score'],
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
		
// 		pr($insert_data,1);
		if (count($insert_data)) {
			$this->db->insert_batch('summary_region_question', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_question', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_question');
		}
	}
	
	/**
	 * 学生排名
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_student_rank($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 对这些考生进行得分排名
		*/
		$insert_data = array();
		$update_data = array();
		
		//循环每个学科进行统计
		$data = array();
		foreach ($exams as $exam_id)
		{
			if (!isset($exam_students[$exam_id])) {
				continue;
			}
		
			//遍历区域
			$tmp_students = $exam_students[$exam_id];
		
			//获取该区域的统计信息
			foreach ($tmp_students as $region_id=>&$uids)
			{
				//对这些学生进行得分排名
				/*
				 * 过滤作弊的成绩
				*/
				$t_uids = implode(',', $uids);
				$sql = "select sum(test_score) as test_score, uid
						from {pre}exam_test_paper
						where exam_id=$exam_id and uid in($t_uids)
						group by uid
						order by test_score desc
						";
				
				$result = $this->db->query($sql)->result_array();
				
				$tmp_arr = array();
				
				//计算排名
				/*
				 * 相同 成绩的为同一个名次
				 * uid		score 		rank
				 * 1		100			1
				 * 2		95			2
				 * 3		95			2
				 * 4		90			4
				 * 5		85			5
				 */
				$tmp_tank = 0;
				$rank = 0;
				foreach ($result as $item)
				{
					$tmp_tank++;
					$test_score = $item['test_score'];
					if (!isset($old_test_score) || $test_score != $old_test_score) 
					{
						if ($tmp_tank != $rank) 
						{
							$rank = $tmp_tank;
						}
						else 
						{
							$rank++;
						}
					}
					
					$old_test_score = $test_score;
					
					$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
					$data[] = array(
							'exam_pid'	=> $exam_pid,
							'exam_id' 	=> $exam_id,
							'region_id' => str_ireplace('school', '', $region_id),
							'is_school' => $is_schoool,
							'uid' 		=> $item['uid'],
							'rank'		=> $rank,
							'test_score'=> $test_score,
					);
				}
				
				unset($old_test_score);
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, uid, region_id, is_school from {pre}summary_region_student_rank where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['region_id']."_".$row['is_school']."_".$row['uid'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row)
		{
			$k = $row['exam_id']."_".$row['region_id']."_".$row['is_school']."_".$row['uid'];
			if (isset($old_ids[$k]))
			{
				$update_data[] = array(
								'id' 			=> $old_ids[$k],
								'test_score' 	=> $row['test_score'],
								'rank' 			=> $row['rank'],
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
		
// 		pr($insert_data,1);
		if (count($insert_data)) {
			$this->db->insert_batch('summary_region_student_rank', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_student_rank', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_student_rank');
		}
	}
	
	/**
	 * 学科
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_subject($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!count($exams)) {
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) {
			return false;
		}
		
		/*
		 * 对这些考生进行得分排名
		*/
		$insert_data = array();
		$update_data = array();
		
		//循环每个学科进行统计
		$data = array();
		foreach ($exams as $exam_id)
		{
			if (!isset($exam_students[$exam_id])) {
				continue;
			}
		
			//遍历区域
			$tmp_students = $exam_students[$exam_id];
		
			//获取该区域的统计信息
			foreach ($tmp_students as $region_id=>&$uids)
			{
				$is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
				$data[] = array(
						'exam_pid'	=> $exam_pid,
						'exam_id' 	=> $exam_id,
						'region_id' => str_ireplace('school', '', $region_id),
						'is_school' => $is_schoool,
						'student_amount' => count($uids),
				);
			}
		}
		
		if (!count($data)) {
			return false;
		}
		
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, region_id, is_school from {pre}summary_region_subject where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['region_id']."_".$row['is_school'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $k=>&$row)
		{
			$k = $row['exam_id']."_".$row['region_id']."_".$row['is_school'];
			if (isset($old_ids[$k]))
			{
				$update_data[] = array(
								'id' 				=> $old_ids[$k],
								'student_amount' 	=> $row['student_amount'],
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
		
		//pr($insert_data,1);
		if (count($insert_data)) {
			$this->db->insert_batch('summary_region_subject', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_region_subject', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_subject');
		}
	}
	
	/**
	 * 关联 信息提取方式
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_group_type($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid) {
	        return false;
	    }
	
	    //获取考试学科
	    $exams = $this->_get_test_exams($exam_pid);
	    if (!count($exams)) {
	        return false;
	    }
	
	    //获取 按照地域归档 的考试试卷
	    $papers = $this->_get_exam_papers($exam_pid);
	    if (!count($papers)) {
	        return false;
	    }
	
	    //获取参与本期考试的所有考生
	    $exam_students = $this->_get_exam_students($exam_pid);
	    if (!count($exam_students)) {
	        return false;
	    }
	
	    /*
	     * 获取这些试卷关联的信息提取方式 from->{pre}summary_paper_group_type
	    */
	    $insert_data = array();
	    $update_data = array();
	
	    //循环每个学科进行统计
	    $data = array();
	    foreach ($exams as $exam_id)
	    {
	        if (!isset($papers[$exam_id])) {
	            continue;
	        }
	
	        //遍历区域
	        $tmp_papers = $papers[$exam_id];
	        	
	        //所有该学科所有试卷ID
	        $all_paper_ids = implode(',', $tmp_papers[1]);
	        $sql = "select paper_id, group_type_id, is_parent, ques_id
	        from {pre}summary_paper_group_type
	        where paper_id in($all_paper_ids)
	        ";
	        $paper_result = $this->db->query($sql)->result_array();
	        if (empty($paper_result))
	        {
	        	continue;
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
	    	
    	    //获取该区域的信息提取方式统计信息
    	    foreach ($tmp_papers as $region_id=>&$paper_ids)
    	    {
        	    $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
        	    if (!count($uids))
        	    {
        	       continue;
        	    }
        	    $uids = implode(',', $uids);
        	
        	    foreach ($paper_ids as &$paper_id)
        	    {
            	    if (!isset($paper_data[$paper_id])) continue;
            	    	
            	    $paper_info = $paper_data[$paper_id];
                    foreach ($paper_info as $paper)
            		{
                        //获取知识点关联试题的总分和试题得分
            			$group_type_id = $paper['group_type_id'];
            	        $ques_ids = trim($paper['ques_id']);
            	        $is_parent = $paper['is_parent'];
            
            	        if ($ques_ids == '' || $group_type_id < 1) continue;
            
            	        //将试题拆分为 题组 和 非题组
            	        $total_score = 0;
            	        $test_score = 0;
            
            	        //题组部分
            	        $sql = "select ques_id
            	        from {pre}question
            	        where ques_id in($ques_ids) and parent_id > 0
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
            				$sql = "select sum(full_score) as total_score, sum(test_score) as test_score 
            				from {pre}exam_test_result
            				where exam_id=$exam_id and paper_id=$paper_id and sub_ques_id in($t_group_ques_id) 
            				and uid in ({$uids})
            				";
            							                	
                            $result = $this->db->query($sql)->row_array();
                            $total_score += $result['total_score'];
                            $test_score += $result['test_score'];
                        }
            	
            		    //非题组部分
            	        $t_ques_id = array_diff(explode(',', $ques_ids), $group_ques_id);
            			if (count($t_ques_id)) {
            			    $t_ques_id = implode(',', $t_ques_id);
            			    $sql = "select sum(full_score) as total_score, sum(test_score) as test_score
            				from {pre}exam_test_result
            				where exam_id=$exam_id and paper_id=$paper_id and ques_id in($t_ques_id) 
            				and uid in ({$uids})
            				";
            							                        	
                            $result = $this->db->query($sql)->row_array();
                            $total_score += $result['total_score'];
                            $test_score += $result['test_score'];
            	        }
            	
                        $is_schoool = stripos($region_id, 'school') === false ? '0' : '1';
                        $data[] = array(
                            'exam_pid'	=> $exam_pid,
                            'exam_id' 	=> $exam_id,
                            'paper_id' 	=> $paper_id,
                            'region_id' => str_ireplace('school', '', $region_id),
                            'is_school' => $is_schoool,
                            'group_type_id'	=> $group_type_id,
                            'ques_id'		=> $ques_ids,
                            'total_score' 	=> $total_score,
                            'test_score' 	=> $test_score,
                            'is_parent' 	=> $is_parent,
                        );
                    }
                }
        	}
    	}
	
    	if (!count($data)) 
    	{
    	   return false;
    	}
	
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, paper_id, region_id, is_school, group_type_id from {pre}summary_region_group_type where exam_pid=$exam_pid")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['group_type_id'];
			$old_ids[$k] = $row['id'];
		}

		foreach ($data as $k=>&$row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['region_id']."_".$row['is_school']."_".$row['group_type_id'];
			if (isset($old_ids[$k]))
			{
				$update_data[] = array(
					'id' 			=> $old_ids[$k],
					'ques_id' 		=> $row['ques_id'],
					'total_score' 	=> $row['total_score'],
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
			$this->db->insert_batch('summary_region_group_type', $insert_data);
		}

		if (count($update_data)) {
			$this->db->update_batch('summary_region_group_type', $update_data, 'id');
		}

		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_region_group_type');
		}
	}         
	
	
	//=====================公共方法===============================================================
	/**
	 * 获取某期考试 按照地域分类，从高->低
	 * note:
	 * 		关联表： {pre}exam_place {pre}school
	 */
	private function _get_exam_papers($exam_pid = 0)
	{
		if (count($this->_exam_paper_data))
		{
			return $this->_exam_paper_data;
		}
		
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return array();
		}
		
		$data = array();
		 
		$sql = "SELECT distinct s.school_id, s.province, s.city, s.area
        		FROM rd_school s
        		LEFT JOIN rd_student stu ON stu.school_id = s.school_id
        		LEFT JOIN rd_exam_test_paper etp ON stu.uid=etp.uid
        		WHERE etp.exam_pid={$exam_pid}
        		";
		$result = $this->db->query($sql)->result_array();
		
		if (!$this->uid && !count($result))
		{
			return array();
		}
		 
		//按照地域级别深度 进行归档
		/*
		* 依次是 国家->省->地市->区县->学校
		*/
		$countries = array();
		$provinces = array();
		$cities = array();
		$areas = array();
		$schools = array();
		foreach ($result as $item) {
			$provinces[] = $item['province'];
			$cities[] = $item['city'];
			$areas[] = $item['area'];
			$schools[] = $item['school_id'];
		}
		
		//获取按地域归档考生试卷
		/*
		 * 前提：已生成成绩
		 */
		$sql = "select etp.exam_id, etp.paper_id {what} 
				from {pre}exam_test_paper etp
				left join {pre}student s on etp.uid=s.uid
				where etp.exam_pid=$exam_pid
				";
		
		//机考演示 考生只生成 全国的统计
		if ($this->uid > 0) 
		{
			$tmp_arr = array(
							'0'	=> array(), 
			);
		}
		else 
		{
			$tmp_arr = array(
							'0'	=> array(), 
							'1' => $provinces, 
							'2' => $cities, 
							'3' => $areas, 
							'4' => $schools, 
			);
		}
		
		foreach ($tmp_arr as $k=>$item) 
		{
			$ids = implode(',', $item);
			$select_column = '';
			$where_sql = '';
			switch ($k)
			{
				case '1':
					$select_column = ', s.province';
					$where_sql = " and s.province in($ids)";
					break;
				case '2':
					$select_column = ', s.city';
					$where_sql = " and s.city in($ids)";
					break;
				case '3':
					$select_column = ', s.area';
					$where_sql = " and s.area in($ids)";
					break;
				case '4':
					$select_column = ', s.school_id';
					$where_sql = " and s.school_id in($ids)";
					break;
				default:
					break;
			}
			
			$tmp_sql = str_ireplace('{what}', $select_column, $sql);
			$result = $this->db->query("$tmp_sql $where_sql")->result_array();
			
			foreach ($result as $row) 
			{
				$paper_id = $row['paper_id'];
				switch ($k)
				{
					case '0':
						if (!isset($data[$row['exam_id']][1]) || !in_array($paper_id, $data[$row['exam_id']][1]))
						{
							$data[$row['exam_id']][1][] = $paper_id;
						}
						break;
					case '1':
						if (!isset($data[$row['exam_id']][$row['province']]) || !in_array($paper_id, $data[$row['exam_id']][$row['province']]))
						{
							$data[$row['exam_id']][$row['province']][] = $paper_id;
						}
						break;
					case '2':
						if (!isset($data[$row['exam_id']][$row['city']]) || !in_array($paper_id, $data[$row['exam_id']][$row['city']]))
						{
							$data[$row['exam_id']][$row['city']][] = $paper_id;
						}
						break;
					case '3':
						if (!isset($data[$row['exam_id']][$row['area']]) || !in_array($paper_id, $data[$row['exam_id']][$row['area']]))
						{
							$data[$row['exam_id']][$row['area']][] = $paper_id;
						}
						break;
					case '4':
						$school_id = 'school' . $row['school_id'];
						if (!isset($data[$row['exam_id']][$school_id]) || !in_array($paper_id, $data[$row['exam_id']][$school_id]))
						{
							$data[$row['exam_id']][$school_id][] = $paper_id;
						}
						break;
					default:
						break;
				}
			}
		}
		
		$this->_exam_paper_data = $data;
		
		return $data;
    }
    
	/**
	 * 获取某期考试 按照地域 归档的考生id
	 * note:
	 * 		关联表：  {pre}exam_place {pre}school 
	 */
	private function _get_exam_students($exam_pid = 0)
	{
		if (count($this->_exam_student_data))
		{
			return $this->_exam_student_data;
		}
		
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return array();
		}
		
		$data = array();
		 
		$sql = "SELECT distinct s.school_id, s.province, s.city, s.area
        		FROM rd_school s
        		LEFT JOIN rd_student stu ON stu.school_id = s.school_id
        		LEFT JOIN rd_exam_test_paper etp ON stu.uid = etp.uid
        		WHERE etp.exam_pid={$exam_pid}
        		";
		
		$result = $this->db->query($sql)->result_array();

		if (!$this->uid && !count($result))
		{
			return array();
		}
		 
		//按照地域级别深度 进行归档
		/*
		* 依次是 国家->省->地市->区县->学校
		*/
		$countries = array();
		$provinces = array();
		$cities = array();
		$areas = array();
		$schools = array();
		foreach ($result as $item) {
			$provinces[] = $item['province'];
			$cities[] = $item['city'];
			$areas[] = $item['area'];
			$schools[] = $item['school_id'];
		}
		
		//获取按地域归档考生试卷
		$sql = "select etp.exam_id, etp.uid {what} 
				from {pre}exam_test_paper etp
				left join {pre}student s on etp.uid=s.uid
				where etp.exam_pid=$exam_pid
				";
		
		//机考演示 考生只生成 全国的统计
		if ($this->uid > 0)
		{
			$tmp_arr = array(
					'0'	=> array(),
			);
		}
		else
		{
			$tmp_arr = array(
					'0'	=> array(),
					'1' => $provinces,
					'2' => $cities,
					'3' => $areas,
					'4' => $schools,
			);
		}
		
		foreach ($tmp_arr as $k=>$item) 
		{
			$ids = implode(',', $item);
			$select_column = '';
			$where_sql = '';
			switch ($k)
			{
				case '1':
					$select_column = ', s.province';
					$where_sql = " and s.province in($ids)";
					break;
				case '2':
					$select_column = ', s.city';
					$where_sql = " and s.city in($ids)";
					break;
				case '3':
					$select_column = ', s.area';
					$where_sql = " and s.area in($ids)";
					break;
				case '4':
					$select_column = ', s.school_id';
					$where_sql = " and s.school_id in($ids)";
					break;
				default:
					break;
			}
			
			$tmp_sql = str_ireplace('{what}', $select_column, $sql);
			$result = $this->db->query("$tmp_sql $where_sql group by etp.exam_id, etp.uid")->result_array();
			
			foreach ($result as $row) 
			{
				$t_uid = $row['uid'];
				switch ($k)
				{
					case '0':
						if (!isset($data[$row['exam_id']][1]) || !in_array($t_uid, $data[$row['exam_id']][1]))
						{
							$data[$row['exam_id']][1][] = $t_uid;
						}
						break;
					case '1':
						if (!isset($data[$row['exam_id']][$row['province']]) || !in_array($t_uid, $data[$row['exam_id']][$row['province']]))
						{
							$data[$row['exam_id']][$row['province']][] = $t_uid;
						}
						break;
					case '2':
						if (!isset($data[$row['exam_id']][$row['city']]) || !in_array($t_uid, $data[$row['exam_id']][$row['city']]))
						{
							$data[$row['exam_id']][$row['city']][] = $t_uid;
						}
						break;
					case '3':
						if (!isset($data[$row['exam_id']][$row['area']]) || !in_array($t_uid, $data[$row['exam_id']][$row['area']]))
						{
							$data[$row['exam_id']][$row['area']][] = $t_uid;
						}
						break;
					case '4':
						$school_id = 'school' . $row['school_id'];
						if (!isset($data[$row['exam_id']][$school_id]) || !in_array($t_uid, $data[$row['exam_id']][$school_id]))
						{
							$data[$row['exam_id']][$school_id][] = $t_uid;
						}
						break;
					default:
						break;
				}
			}
		}
		
		$this->_exam_student_data = $data;
		
		return $data;
    }
    
	/**
	 * 获取某期考试 按照地域 归档的试题id
	 * note:
	 * 		关联表：  {pre}exam_place {pre}school 
	 */
	private function _get_exam_ques($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return array();
		}
		
		$data = array();
		
		//获取按区域归档的考试试卷
		$paper_data = $this->_get_exam_papers($exam_pid);
		if (!count($paper_data)) {
			return array();
		}
		
		//根据考试试卷获取参考试题
		foreach ($paper_data as $exam_id=>&$region_paper) 
		{
			//遍历区域
			foreach ($region_paper as $region_id=>&$paper_ids) 
			{
				if (!count($paper_ids)) continue;
				
				//获取该试卷涉及的试题
				$ques_ids = array();

				/*
				 * 此处不将题组拆分为子题
				 */
				$paper_ids = implode(',', $paper_ids);
				$sql = "select distinct(ques_id) as ques_id from {pre}exam_question_score where exam_pid=$exam_pid and exam_id=$exam_id and paper_id in($paper_ids)";
				$result = $this->db->query($sql)->result_array();
				foreach ($result as $item) 
				{
					$data[$exam_id][$region_id][] = $item['ques_id'];
				}
			}
		}
		
		return $data;
    }
    
    /**
     * 获取某考试期次下考试学科
     */
    private function _get_test_exams($exam_pid = 0)
    {
    	if (count($this->_exam_data))
    	{
    		return $this->_exam_data;
    	}
    	
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		return array();
    	}
    	
    	$sql = "select exam_id from {pre}exam where exam_pid=$exam_pid";
    	
    	$result = $this->db->query($sql)->result_array();
    	$data = array();
    	foreach ($result as $item) {
    		$data[] = $item['exam_id'];
    	}
    	
    	$this->_exam_data = $data;
    	
    	return $data;
    } 
}