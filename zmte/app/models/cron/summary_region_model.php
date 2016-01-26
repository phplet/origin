<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 地区相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_region_model extends CI_Model 
{
    private static $_db;
    
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
	}
	
	private  $_exam_data = array();
	private  $_exam_paper_data = array();
	private  $_exam_student_data = array();
	private  $_exam_paper_ids = array();
	private  $_data = array();
	private  $_data_question = array();
	private  $_data_student_test_score = array();
	
	/**
	 * 执行 地区 相关所有关联脚本
	 * @param number $exam_pid
	 * @return boolean
	 */
	public function do_all($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}

		$this->_init_exam_data($exam_pid);
        
		if (!$this->_data
            || !$this->_data_student_test_score)
		{ 
			return  false;
		}
		
 		$this->summary_region_difficulty($exam_pid);
 		$this->summary_region_knowledge($exam_pid);
 		$this->summary_region_method_tactic($exam_pid);
 		$this->summary_region_group_type($exam_pid);
		$this->summary_region_question($exam_pid);
		$this->summary_region_student_rank($exam_pid);
		$this->summary_region_subject($exam_pid);
		
		unset($this->_exam_data);
		unset($this->_exam_paper_data);
		unset($this->_exam_student_data);
		unset($this->_exam_paper_ids);
		unset($this->_data);
		unset($this->_data_question);
		unset($this->_data_student_test_score);
		
		return true;
	}
	
	function _init_exam_data($exam_pid)
	{
	    /*
	     * 学生考试的试题信息
	     */
        $stmt = self::$_db->query("SELECT exam_id,paper_id,uid,ques_id,sub_ques_id,full_score,test_score "
                 . " FROM rd_exam_test_result WHERE exam_pid = $exam_pid");
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if ($item['sub_ques_id'] > 0)
            {
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['sub_ques_id']}";
                $this->_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
                 
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['ques_id']}";
                if (isset($this->_data[$k]))
                {
                    $this->_data[$k]['full_score'] += $item['full_score'];
                    $this->_data[$k]['test_score'] += $item['test_score'];
                }
                else
                {
                    $this->_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
                }
            }
            else
            {
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['ques_id']}";
                 
                $this->_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
            }
        }
        
	    /*
	     * 参加考试的学生考试得分情况
	     */
	    $sql = "SELECT exam_id, test_score, uid FROM rd_exam_test_paper
        	    WHERE exam_pid = $exam_pid
        	    GROUP BY exam_id,uid
        	    ORDER BY test_score DESC";
	    $stmt = self::$_db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
           $this->_data_student_test_score[$row['exam_id']][$row['uid']] = $row['test_score'];
        }
	    
	    unset($stmt);
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
		if (!$exams) 
		{
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!count($papers))
		{
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students))
		{
			return false;
		}
		
		if (!$this->_exam_paper_ids)
		{
		    return false;
		}
		
		/*
		 * 获取这些试卷关联的题型难易度 from->rd_summary_paper_difficulty
		*/
		//所有该学科所有试卷ID
		$all_paper_ids = implode(',', $this->_exam_paper_ids);
		
		$sql = "SELECT paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id
        		FROM rd_summary_paper_difficulty
        		WHERE paper_id IN ($all_paper_ids)";
		$query = self::$_db->query($sql);
		
		$paper_data = array();
		while ($item = $query->fetch(PDO::FETCH_ASSOC)) {
    		$paper_data[$item['paper_id']][] = array(
		        'q_type' => $item['q_type'],
		        'ques_id' => array(
        		                'low' => trim($item['low_ques_id']),
        		                'mid' => trim($item['mid_ques_id']),
        		                'high' => trim($item['high_ques_id'])
		                     )
    		);
		}
		
		unset($query);
		
		if (!$paper_data)
		{
			return false;
		}
		
		//循环每个学科进行统计
		$time = time();
		$data = array();
		$tmp_data = array();
		
		foreach ($exams as $exam_id) 
		{
			if (empty($papers[$exam_id]))
			{
				continue;
			} 
				
			//遍历试卷
			$tmp_papers = $papers[$exam_id];
			
			//获取该区域的题型难易度统计信息
			foreach ($tmp_papers as $paper_id => $regions) 
			{
			    if (empty($paper_data[$paper_id])) continue;
			    
			    //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
    			        
    			        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
    			        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
    			        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
    					
    					$paper_info = $paper_data[$paper_id];
    					foreach ($paper_info as $paper) 
    					{
    						//获取题型难易度关联试题的总分和试题得分
    						$q_type = $paper['q_type'];
    						
    						$k = $exam_id."_".$paper_id."_".$region_id."_".$is_school."_".$is_class."_".$q_type;
    						if (in_array($k, $tmp_data))
    						{
    							continue;
    						}
    						
    						$ques_ids = $paper['ques_id'];
    						$total_scores = array();
    						$test_scores = array();
    						foreach ($ques_ids as $key => $ques_id) 
    						{
    							$total_score = 0;
    							$test_score = 0;
    							
    							if ($ques_id == '') 
    							{
    								$total_scores[$key] = $total_score;
    								$test_scores[$key] = $test_score;
    								continue;
    							}
    							
    							$t_ques_ids = explode(',', $ques_id);
    							
    						    foreach ($uids as $uid)
    						    {
    						        foreach ($t_ques_ids as $ques_id)
    						        {
    						            $tmp_k = $exam_id . "_" . $uid . "_" . $ques_id;
    						            
    							        $total_score += $this->_data[$tmp_k]['full_score'];
    							        $test_score  += $this->_data[$tmp_k]['test_score'];
    							    }
    							}
    							
    							$total_scores[$key] = $total_score;
    							$test_scores[$key] = $test_score;
    						}
    						
    						$data = array(
    							'exam_pid'	=> $exam_pid,
    							'exam_id' 	=> $exam_id,
    							'paper_id' 	=> $paper_id,
    							'region_id' => $region_id,
    							'is_school' => $is_school,
    						    'is_class'  => $is_class,
    							'q_type'	=> $q_type,
    							'low_total_score'	=> $total_scores['low'],
    							'mid_total_score'	=> $total_scores['mid'],
    							'high_total_score'	=> $total_scores['high'],
    							'low_test_score'	=> $test_scores['low'],
    							'mid_test_score'	=> $test_scores['mid'],
    							'high_test_score'	=> $test_scores['high'],
    							'low_ques_id'		=> $ques_ids['low'] ?  $ques_ids['low']  : '0',
    							'mid_ques_id'		=> $ques_ids['mid'] ?  $ques_ids['mid']   : '0',
    							'high_ques_id'		=> $ques_ids['high'] ?  $ques_ids['high']   : '0',
    						    'ctime' => $time
    						);
    						
    						$this->db->replace('rd_summary_region_difficulty', $data);
    						
    						$tmp_data[] = $k;
    					}
    				}
			    }
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    $this->db->delete('rd_summary_region_difficulty', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($tmp_data);
		unset($paper_data);
		unset($data);
		unset($papers);
		unset($exams);
		unset($exam_students);
	}
	
	/**
	 * 关联 一级知识点
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_knowledge($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid)
		{
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!$exams)
		{
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!$papers)
		{
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!$exam_students) 
		{
			return false;
		}
		
		if (!$this->_exam_paper_ids)
		{
		    return false;
		}
		
		/*
		 * 获取这些试卷关联的知识点 from->rd_summary_paper_knowledge
		*/
		//所有该学科所有试卷ID
		$all_paper_ids = implode(',', $this->_exam_paper_ids);
		$sql = "SELECT paper_id, knowledge_id, is_parent, ques_id 
				FROM rd_summary_paper_knowledge 
				WHERE paper_id IN($all_paper_ids)";
		$query = self::$_db->query($sql);

		$paper_data = array();
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
			$paper_data[$item['paper_id']][] = array(
					'knowledge_id' 	=> $item['knowledge_id'], 
					'ques_id' 		=> $item['ques_id'], 
					'is_parent' 	=> $item['is_parent']
			);
		}
		
		unset($query);
		
		if (!$paper_data)
		{
			return false;
		}
		
		//循环每个学科进行统计
		$data = array();
		$time = time();
		
		foreach ($exams as $exam_id) 
		{
			if (empty($papers[$exam_id])) 
			{
				continue;
			} 
				
			//遍历试卷
			$tmp_papers = $papers[$exam_id];
			
			//获取该区域的知识点统计信息
			foreach ($tmp_papers as $paper_id => $regions) 
			{
			    if (empty($paper_data[$paper_id])) continue;
			    
			    //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
    					
    				    $is_school = stripos($region_id, 'school') === false ? '0' : '1';
    				    $is_class = stripos($region_id, 'class') === false ? '0' : '1';
    				    $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
    				    
    					$paper_info = $paper_data[$paper_id];
    					foreach ($paper_info as $paper) 
    					{
    					    //获取知识点关联试题的总分和试题得分
    					    $knowledge_id = $paper['knowledge_id'];
    					    $ques_ids = trim($paper['ques_id']);
    					    $is_parent = $paper['is_parent'];
    					    
    						if ($ques_ids == '') continue;
    						
    					    $total_score = 0;
    					    $test_score = 0;
    					    
    					    $t_ques_ids = explode(',', $ques_ids);
    					    foreach ($uids as $uid)
    					    {
    					        foreach ($t_ques_ids as $ques_id)
    					        {
    					            $k = $exam_id . "_" . $uid . "_" . $ques_id;
    					            
    					            $total_score += $this->_data[$k]['full_score'];
    					            $test_score  += $this->_data[$k]['test_score'];
    					        }
    					    }
    						
    						$data = array(
    								'exam_pid'	=> $exam_pid,
    								'exam_id' 	=> $exam_id,
    								'paper_id' 	=> $paper_id,
    								'region_id' => $region_id,
    								'is_school' => $is_school,
    						        'is_class'  => $is_class,
    								'knowledge_id'	=> $knowledge_id,
    								'ques_id'		=> $ques_ids ,
    								'total_score' 	=> $total_score,
    								'test_score' 	=> $test_score,
    								'is_parent' 	=> $is_parent,
    						        'ctime'         => $time
    						);
    						
    						$this->db->replace('rd_summary_region_knowledge', $data);
    					}
    				}
			    }
			}
		}
		
		if ($data)
		{
		    //清除多余的数据
		    $this->db->delete('rd_summary_region_knowledge', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($paper_data);
		unset($data);
		unset($papers);
		unset($exams);
		unset($exam_students);
	}
	
	/**
	 * 关联 方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid)
		{
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!$exams) 
		{
			return false;
		}
		
		//获取 按照地域归档 的考试试卷
		$papers = $this->_get_exam_papers($exam_pid);
		if (!$papers) 
		{
			return false;
		}

		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!$exam_students) 
		{
			return false;
		}
		
		if (!$this->_exam_paper_ids)
		{
		    return false;
		}
		
		/*
		 * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
		 */
		//所有该学科所有试卷ID
		$all_paper_ids = implode(',', $this->_exam_paper_ids);
		$sql = "SELECT paper_id, method_tactic_id, ques_id
        		FROM rd_summary_paper_method_tactic
        		WHERE paper_id IN($all_paper_ids)
        		";
		$query = self::$_db->query($sql);
		
		$paper_data = array();
		while ($item = $query->fetch(PDO::FETCH_ASSOC)) 
		{
		     $paper_data[$item['paper_id']][] = array(
		        'method_tactic_id' 	=> $item['method_tactic_id'],
		        'ques_id' 		=> $item['ques_id'],
			 );
		}
		
		unset($query);
		
		if (!$paper_data)
		{
			return false;
		}
		
		//循环每个学科进行统计
		$data = array();
		$time = time();
		foreach ($exams as $exam_id) 
		{
			if (empty($papers[$exam_id])) 
			{
				continue;
			} 
				
			//遍历区域
			$tmp_papers = $papers[$exam_id];
			
			//获取该区域的方法策略统计信息
			foreach ($tmp_papers as $paper_id => $regions) 
			{
			    if (empty($paper_data[$paper_id])) continue;
			    
			    //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
    			        
    			        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
    			        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
    			        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
    					
    					$paper_info = $paper_data[$paper_id];
    					foreach ($paper_info as $paper) 
    					{
    						//获取方法策略关联试题的总分和试题得分
    						$method_tactic_id = $paper['method_tactic_id'];
    						$ques_ids = trim($paper['ques_id']);
    						
    						if ($ques_ids == '') continue;
    						
    						$total_score = 0;
    						$test_score = 0;
    						
    						$t_ques_ids = explode(',', $ques_ids);
    						foreach ($uids as $uid)
    						{
    						    foreach ($t_ques_ids as $ques_id)
    						    {
    						        $k = $exam_id . "_" . $uid . "_" . $ques_id;
    						         
    						        $total_score += $this->_data[$k]['full_score'];
    						        $test_score  += $this->_data[$k]['test_score'];
    						    }
    						}
    						
    						$data = array(
    							'exam_pid'	=> $exam_pid,
    							'exam_id' 	=> $exam_id,
    							'paper_id' 	=> $paper_id,
    							'region_id' => $region_id,
    							'is_school' => $is_school,
    						    'is_class'  => $is_class,
    							'method_tactic_id'	=> $method_tactic_id,
    							'ques_id'		=>  $ques_ids ,
    							'total_score' 	=> $total_score,
    							'test_score' 	=> $test_score,
    						    'ctime'         => $time
    						);
    						
    						$this->db->replace('rd_summary_region_method_tactic', $data);
    					}
    				}
			    }
			}
		}
		
		if ($data) 
		{
		    //清除多余的数据
		    $this->db->delete('rd_summary_region_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($paper_data);
		unset($data);
		unset($papers);
		unset($exams);
		unset($exam_students);
	}
	
	/**
	 * 关联 试题
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_question($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!$exams)
		{
			return false;
		}
		
		//获取本期考试考到的试题
		$exam_questions = $this->_get_exam_ques($exam_pid);
		if (!$exam_questions) 
		{
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students))
		{
		    return false;
		}
		
		/*
		 * 获取这些试题的：试题总分、答该题总人数、平均分
		*/
		$data = array();
		$time = time();
		
		foreach ($exams as $exam_id)
		{
			if (empty($exam_questions[$exam_id]))
			{
				continue;
			}
		
			//遍历区域
			$tmp_questions = $exam_questions[$exam_id];
			
			//获取该区域的统计信息
			foreach ($tmp_questions as $region_id => $ques_ids)
			{
			    $uids = $exam_students[$exam_id][$region_id];
			    if (!$uids)
			    {
			    	continue;
			    }
			    
			    $is_school = stripos($region_id, 'school') === false ? '0' : '1';
			    $is_class = stripos($region_id, 'class') === false ? '0' : '1';
			    $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
			    
	            foreach ($ques_ids as $ques_id)
	            {
	                $total_score = 0;
	                $test_score  = 0;
	                $student_amount = 0;
	                
	                foreach ($uids as $uid)
	                {
	                    $k = "{$exam_id}_{$uid}_{$ques_id}";
	                	if (isset($this->_data[$k]))
	                	{
	                	    $total_score += $this->_data[$k]['full_score'];
	                	    $test_score  += $this->_data[$k]['test_score'];
	                	    $student_amount++;
	                	}
	                }
	                
	                $data = array(
	                        'exam_pid'	        => $exam_pid,
	                        'exam_id' 	        => $exam_id,
	                        'ques_id' 	        => $ques_id,
	                        'region_id'         => $region_id,
	                        'is_school'         => $is_school,
	                        'is_class'          => $is_class,
	                        'total_score'		=> $total_score,
	                        'test_score'		=> $test_score,
	                        'student_amount' 	=> $student_amount,
	                        'avg_score' 		=> ($student_amount ? $test_score/$student_amount : 0),
	                        'ctime'             => $time
	                );
	                
	                $this->db->replace('rd_summary_region_question', $data);
	            }
			}
		}
		
		if ($data)
		{
			//清除多余的数据
		    $this->db->delete('rd_summary_region_question', "exam_pid = $exam_pid AND ctime < $time");
		}

		unset($data);
		unset($exam_questions);
		unset($exams);
		unset($tmp_questions);
	}
	
	/**
	 * 学生排名
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_student_rank($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!$exams) 
		{
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!count($exam_students)) 
		{
			return false;
		}
		
		/*
		 * 对这些考生进行得分排名
		*/
		
		//循环每个学科进行统计
		$data = array();
		$time = time();
		foreach ($exams as $exam_id)
		{
			if (empty($exam_students[$exam_id])) 
			{
				continue;
			}
		
			//遍历区域
			$tmp_students = $exam_students[$exam_id];
		
			//获取该区域的统计信息
			foreach ($tmp_students as $region_id => $uids)
			{
			    if (!$uids || empty($this->_data_student_test_score[$exam_id])) 
			    {
			        continue;
			    }
			    
			    $tmp_data = $this->_data_student_test_score[$exam_id];
			    
			    $is_school = stripos($region_id, 'school') === false ? '0' : '1';
			    $is_class = stripos($region_id, 'class') === false ? '0' : '1';
			    $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
			    
			    $tmp_tank = 0;
			    $rank = 0;
			    
			    //对这些学生进行得分排名
			    foreach ($tmp_data as $uid => $test_score)
			    {
			        if (!in_array($uid, $uids))
			        {
			            continue;
			        }
			        
			        $tmp_tank++;
			        
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
			        	
			        $data = array(
			                'exam_pid'	=> $exam_pid,
			                'exam_id' 	=> $exam_id,
			                'region_id' => $region_id,
			                'is_school' => $is_school,
			                'is_class'  => $is_class,
			                'uid' 		=> $uid,
			                'rank'		=> $rank,
			                'test_score'=> $test_score,
			                'ctime'     => $time
			        );
			        
			        $this->db->replace('rd_summary_region_student_rank', $data);
			    }

				unset($old_test_score);
			}
		}
		
		if ($data)
		{
			//清除多余的数据
		    $this->db->delete('rd_summary_region_student_rank', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($exams);
		unset($exam_students);
		unset($tmp_data);
	}
	
	/**
	 * 学科
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_subject($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取考试学科
		$exams = $this->_get_test_exams($exam_pid);
		if (!$exams) 
		{
			return false;
		}
		
		//获取参与本期考试的所有考生
		$exam_students = $this->_get_exam_students($exam_pid);
		if (!$exam_students) 
		{
			return false;
		}
		
		//循环每个学科进行统计
		$data = array();
		$time = time();
		foreach ($exams as $exam_id)
		{
			if (empty($exam_students[$exam_id]))
			{
				continue;
			}
		
			//遍历区域
			$tmp_students = $exam_students[$exam_id];
		
			//获取该区域的统计信息
			foreach ($tmp_students as $region_id => $uids)
			{
				$is_school = stripos($region_id, 'school') === false ? '0' : '1';
				$is_class = stripos($region_id, 'class') === false ? '0' : '1';
				$region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
				
				$data = array(
						'exam_pid'	=> $exam_pid,
						'exam_id' 	=> $exam_id,
						'region_id' => $region_id,
						'is_school' => $is_school,
				        'is_class'  => $is_class,
						'student_amount' => count($uids),
				        'ctime'     => $time
				);
				
				$this->db->replace('rd_summary_region_subject', $data);
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    $this->db->delete('rd_summary_region_subject', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($exams);
		unset($exam_students);
		unset($tmp_students);
	}
	
	/**
	 * 关联 信息提取方式
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_region_group_type($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid) 
	    {
	        return false;
	    }
	
	    //获取考试学科
	    $exams = $this->_get_test_exams($exam_pid);
	    if (!$exams) 
	    {
	        return false;
	    }
	
	    //获取 按照地域归档 的考试试卷
	    $papers = $this->_get_exam_papers($exam_pid);
	    if (!$papers) 
	    {
	        return false;
	    }
	
	    //获取参与本期考试的所有考生
	    $exam_students = $this->_get_exam_students($exam_pid);
	    if (!$exam_students)
	    {
	        return false;
	    }
	
	    if (!$this->_exam_paper_ids)
	    {
	        return false;
	    }
	    
	    /*
	     * 获取这些试卷关联的信息提取方式 from->rd_summary_paper_group_type
	    */
	    //所有该学科所有试卷ID
	    $all_paper_ids = implode(',', $this->_exam_paper_ids);
	    
	    $sql = "SELECT paper_id, group_type_id, is_parent, ques_id
        	    FROM rd_summary_paper_group_type
        	    WHERE paper_id IN($all_paper_ids)
        	    ";
	    $query = self::$_db->query($sql);
	    
	    $paper_data = array();
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'group_type_id' => $item['group_type_id'],
                'ques_id' 		=> $item['ques_id'],
                'is_parent' 	=> $item['is_parent']
            );
        }
        
        unset($query);
        
        if (!$paper_data)
        {
        	return false;
        }
	    
	    //循环每个学科进行统计
	    $data = array();
	    $time = time();
	    foreach ($exams as $exam_id)
	    {
	        if (empty($papers[$exam_id])) 
	        {
	            continue;
	        }
	
	        //遍历区域
	        $tmp_papers = $papers[$exam_id];
	        	
    	    //获取该区域的信息提取方式统计信息
    	    foreach ($tmp_papers as $paper_id => $regions)
    	    {
    	        if (empty($paper_data[$paper_id])) continue;
    	        
    	        //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
        	            
        	            $is_school = stripos($region_id, 'school') === false ? '0' : '1';
        	            $is_class = stripos($region_id, 'class') === false ? '0' : '1';
        	            $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
            	        
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
                	        
                	        $t_ques_ids = explode(',', $ques_ids);
                	        foreach ($uids as $uid)
                	        {
                	            foreach ($t_ques_ids as $ques_id)
                	            {
                	                $k = $exam_id . "_" . $uid . "_" . $ques_id;
                	                 
                	                $total_score += $this->_data[$k]['full_score'];
                	                $test_score  += $this->_data[$k]['test_score'];
                	            }
                	        }
                	
                            $data = array(
                                'exam_pid'	=> $exam_pid,
                                'exam_id' 	=> $exam_id,
                                'paper_id' 	=> $paper_id,
                                'region_id' => $region_id,
                                'is_school' => $is_school,
                                'is_class'  => $is_class,
                                'group_type_id'	=> $group_type_id,
                                'ques_id'		=>  $ques_ids ,
                                'total_score' 	=> $total_score,
                                'test_score' 	=> $test_score,
                                'is_parent' 	=> $is_parent,
                                'ctime'         => $time
                            );
                            
                            $this->db->replace('rd_summary_region_group_type', $data);
                        }
                    }
    	        }
        	}
    	}
	
    	if ($data) 
    	{
    	    //清除多余的数据
    	    $this->db->delete('rd_summary_region_group_type', "exam_pid = $exam_pid AND ctime < $time");
    	}
    	
    	unset($paper_data);
    	unset($data);
    	unset($papers);
    	unset($exams);
    	unset($exam_students);
	}         
	
	
	//=====================公共方法===============================================================
	/**
	 * 获取某期考试 按照地域分类，从高->低
	 * note:
	 * 		关联表： rd_exam_place rd_school
	 */
	private function _get_exam_papers($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid)
	    {
	        return array();
	    }
	    
		if ($this->_exam_paper_data)
		{
			return $this->_exam_paper_data;
		}
		
		$sql = "SELECT * FROM v_summary_region_exam_paper
        		WHERE exam_pid={$exam_pid}
        		";
		
		$query = self::$_db->query($sql);
		$data = array();
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    $this->_exam_paper_ids[] = $item['paper_id'];
		    
		    $arr = array(
                0 => 1,
                1 => $item['province'],
                2 => $item['city'],
                3 => $item['area'],
                4 => 'school' . $item['school_id'],
            );
            
            if ($item['place_schclsid'] > 0)
            {
                $arr[5] = 'class' . $item['place_schclsid'];
            }
            
            $data[$item['exam_id']][$item['paper_id']][] = $arr;
		}
		
	    $this->_exam_paper_data = $data;
		
		return $data;
    }
    
	/**
	 * 获取某期考试 按照地域 归档的考生id
	 * note:
	 * 		关联表：  rd_exam_place rd_school 
	 */
	private function _get_exam_students($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid)
	    {
	        return array();
	    }
	    
		if ($this->_exam_student_data)
		{
			return $this->_exam_student_data;
		}
		
		$data = array();
		
		$sql = "SELECT eps.uid, ep.place_schclsid FROM rd_exam_place ep
                LEFT JOIN rd_exam_place_student eps ON eps.place_id = ep.place_id
		        WHERE exam_pid = $exam_pid AND ep.place_schclsid > 0";
		$uid_class = self::$_db->fetchPairs($sql);
		 
		$sql = "SELECT * FROM v_summary_region_exam_student
        		WHERE exam_pid={$exam_pid}
        		";
		$query = self::$_db->query($sql);
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    $data[$item['exam_id']][1][] = $item['uid'];
		    $data[$item['exam_id']][$item['province']][] = $item['uid'];
		    $data[$item['exam_id']][$item['city']][] = $item['uid'];
		    $data[$item['exam_id']][$item['area']][] = $item['uid'];
		    $data[$item['exam_id']]['school' . $item['school_id']][] = $item['uid'];
		    
		    if (isset($uid_class[$item['uid']]) 
		        && $uid_class[$item['uid']] > 0)
		    {
		        $data[$item['exam_id']]['class' . $uid_class[$item['uid']]][] = $item['uid'];
		    }
		}
		
	    $this->_exam_student_data = $data;
		
		return $data;
    }
    
	/**
	 * 获取某期考试 按照地域 归档的试题id
	 * note:
	 * 		关联表：  rd_exam_place rd_school 
	 */
	private function _get_exam_ques($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return array();
		}
		
		$data = array();
		
		//获取按区域归档的考试试卷
		$paper_data = $this->_get_exam_papers($exam_pid);
		if (!$paper_data) 
		{
			return array();
		}
		
		$t_paper_id_str = implode(',', $this->_exam_paper_ids);
		
        $sql = "SELECT DISTINCT(ques_id) AS ques_id, exam_id, paper_id
                FROM rd_exam_question_score
                WHERE exam_pid=$exam_pid AND paper_id IN ($t_paper_id_str)";
        $query = self::$_db->query($sql);
        
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $exam_id = $row['exam_id'];
            $paper_id = $row['paper_id'];
        	foreach ($paper_data[$exam_id][$paper_id] as $region)
        	{
        	    foreach ($region as $region_id)
        	    {
        	        if (isset($data[$exam_id][$region_id]) 
        	                && in_array($row['ques_id'], $data[$exam_id][$region_id]))
        	        {
        	        	continue;
        	        }
        	        
        	        $data[$exam_id][$region_id][] = $row['ques_id'];
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
    	if (!empty($this->_exam_data[$exam_pid]))
    	{
    		return $this->_exam_data[$exam_pid];
    	}
    	
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		return array();
    	}
    	
    	$sql = "SELECT exam_id FROM rd_exam WHERE exam_pid=$exam_pid";
    	$this->_exam_data[$exam_pid] = self::$_db->fetchCol($sql);
    	
    	return $this->_exam_data[$exam_pid];
    } 
}
