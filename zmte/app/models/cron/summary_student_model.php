<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 考生相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_student_model extends CI_Model 
{
	private static $_db;
	
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
	}
	
	private $_exam_student_papers = array();
	private $_data = array();
	private $_data_student_test_question = array();

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
		
		if (!$this->_data)
		{
			return false;
		}
		
		$this->summary_student_difficulty($exam_pid);
		$this->summary_student_knowledge($exam_pid);
		$this->summary_student_method_tactic($exam_pid);
		$this->summary_student_subject_method_tactic($exam_pid);
		$this->summary_student_group_type($exam_pid);
		
		unset($this->_data);
		unset($this->_exam_student_papers);
		unset($this->_data_student_test_question);
		
		return true;
	}
	
	function _init_exam_data($exam_pid)
	{
	    /*
	     * 学生考试的试题信息
	    */
	    
        $stmt = self::$_db->query("SELECT exam_id,paper_id,uid,ques_id,sub_ques_id,full_score,test_score "
                . "FROM rd_exam_test_result WHERE exam_pid = $exam_pid");
	    while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
	    {
	        if ($item['sub_ques_id'] > 0)
	        {
	            $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['sub_ques_id']}";
	            $this->_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
	             
	            $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
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
	            $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
	             
	            $this->_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
	        }
	    }

	    /*
	     * 获取方法策略关联试题的试题得分及试题难易度
	     */
	    $sql = "SELECT etr.exam_id,paper_id,etr.uid,
	               SUM(etr.test_score) as test_score,
	               rc.difficulty,etr.ques_id,sub_ques_id
        	    FROM rd_exam_test_result etr
        	    LEFT JOIN rd_exam e ON etr.exam_id=e.exam_id
        	    LEFT JOIN rd_relate_class rc ON etr.ques_id=rc.ques_id
        	    AND rc.grade_id=e.grade_id AND rc.class_id=e.class_id
        	    AND rc.subject_type=e.subject_type
        	    WHERE etr.exam_pid=$exam_pid
        	    GROUP BY etr.etp_id,etr.ques_id,etr.sub_ques_id
        	    ";
        //$sql = "SELECT * FROM v_method_tactic_relate_question_difficulty WHERE exam_pid=$exam_pid";
	    $stmt = self::$_db->query($sql);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if ($item['sub_ques_id'] > 0)
            {
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['sub_ques_id']}";
                $this->_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
                 
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
                if (isset($this->_data_student_test_question[$k]))
                {
                    $this->_data_student_test_question[$k]['test_score'] += $item['test_score'];
                }
                else
                {
                    $this->_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
                }
            }
            else
            {
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
                 
                $this->_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
            }
        }
	    
	    unset($stmt);
	}
	
	/**
	 * 关联 难易度和题型 
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_difficulty($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!$papers || empty($papers['paper'])) 
		{
			return false;
		}
		
		/*
		 * 获取这些试卷关联的难易度和题型 from->rd_summary_paper_difficulty
		 */
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		$time = time();
		
		//该期次考试的所有试卷ID
		$sql = "SELECT paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id 
				FROM rd_summary_paper_difficulty 
				WHERE paper_id IN($all_paper_ids)
			    ";
		$query = self::$_db->query($sql);
		
		$paper_data = array();
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    $paper_data[$item['paper_id']][] = array(
		        'q_type'  => $item['q_type'],
		        'ques_id' => array(
		            'low' => trim($item['low_ques_id']),
		            'mid' => trim($item['mid_ques_id']),
		            'high' => trim($item['high_ques_id']),
		        )
		    );
		}
		
		unset($query);
		
		if (!$paper_data)
		{
			return false;
		}
		
		$data = array();
		foreach ($student_papers as $exam_id => $paper) 
		{
			//获取该区域的难易度和题型统计信息
			foreach ($paper as $paper_id => $u_ids) 
			{
			    if (empty($paper_data[$paper_id]) || !$u_ids) 
			    {
			        continue;
			    }
			    
				$paper_info = $paper_data[$paper_id];
				
				foreach ($paper_info as $u_paper) 
				{
					//获取难易度和题型关联试题的试题得分
					$q_type = $u_paper['q_type'];
					$ques_ids = $u_paper['ques_id'];
					$test_scores = array();
					
					foreach ($ques_ids as $key => $ques_id)
					{
					    //统计试题得分
					    if ($ques_id == '')
					    {
					        $test_scores[$key] = array();
					        continue;
					    }
					    
					    $t_ques_ids = explode(',', $ques_id);
					    
					    foreach ($u_ids as $uid)
					    {
					        $test_score = 0;
					        foreach ($t_ques_ids as $ques_id)
					        {
					            $k = $exam_id . "_" . $paper_id . "_" . $uid . "_" . $ques_id;
					    
					            $test_score += $this->_data[$k]['test_score'];
					        }
					        
					        $test_scores[$key][$uid] = $test_score;
					    }
					}
					
					foreach ($u_ids as $uid)
					{
					    $low_test_score = isset($test_scores['low'][$uid]) ? $test_scores['low'][$uid] : 0;
					    $mid_test_score = isset($test_scores['mid'][$uid]) ? $test_scores['mid'][$uid] : 0;
					    $high_test_score = isset($test_scores['high'][$uid]) ? $test_scores['high'][$uid] : 0;
					    
					    $data = array(
					            'exam_pid' => $exam_pid,
					            'exam_id'  => $exam_id,
					            'paper_id' => $paper_id,
					            'uid'      => $uid,
					            'q_type'   => $q_type,
					            'low_test_score'  => $low_test_score,
					            'mid_test_score'  => $mid_test_score,
					            'high_test_score' => $high_test_score,
					            'ctime' => $time
					    );
					    
					    self::$_db->replace('rd_summary_student_difficulty', $data);
					}
				}
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    self::$_db->delete('rd_summary_student_difficulty', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($paper_data);
		unset($papers);
		unset($student_papers);
		unset($paper_info);
	}
	
	/**
	 * 关联 一级知识点
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_knowledge($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!$papers || empty($papers['paper'])) 
		{
			return false;
		}
		
		/*
		 * 获取这些试卷关联的知识点 from->rd_summary_paper_knowledge
		 */

		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
		$sql = "SELECT paper_id, knowledge_id, is_parent, ques_id, know_process_ques_id
				FROM rd_summary_paper_knowledge 
				WHERE paper_id IN($all_paper_ids)
			";
		$query = self::$_db->query($sql);
		
		$paper_data = array();
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    $paper_data[$item['paper_id']][] = array(
		        'knowledge_id' 	=> $item['knowledge_id'],
		        'ques_id' 		=> $item['ques_id'],
		        'know_process_ques_id' => $item['know_process_ques_id'],
		        'is_parent' 	=> $item['is_parent']
		    );
		}
		
		unset($query);
		
		if (!$paper_data)
		{
			return false;
		}
		
		$time = time();
		$data = array();
		foreach ($student_papers as $exam_id => $paper) 
		{
			//获取该区域的知识点统计信息
			foreach ($paper as $paper_id => $u_ids) 
			{
			    if (empty($paper_data[$paper_id]) || !$u_ids) 
			    {
			        continue;
			    }
			        
				$paper_info = $paper_data[$paper_id];
				
				foreach ($paper_info as $u_paper) 
				{
				    $ques_ids = trim($u_paper['ques_id']);
				    if ($ques_ids == '') 
				    {
				        continue;
				    }
				    
					//获取知识点关联试题的试题得分
					$knowledge_id = $u_paper['knowledge_id'];
					$know_process_ques_ids = $u_paper['know_process_ques_id'] ? json_decode($u_paper['know_process_ques_id'], true) : array();
					$know_process_ques_ids = !is_array($know_process_ques_ids) ? array() : $know_process_ques_ids;
					$is_parent = $u_paper['is_parent'];
					
					$total_scores = array();
					$test_scores = array();
					
					$t_ques_ids = explode(',', $ques_ids);
					foreach ($u_ids as $uid)
					{
					    $total_score = 0;
					    $test_score = 0;
				        foreach ($t_ques_ids as $ques_id)
				        {
				            $k = $exam_id . "_" . $paper_id . "_" . $uid . "_" . $ques_id;
				    
				            $total_score += $this->_data[$k]['full_score'];
				            $test_score += $this->_data[$k]['test_score'];
				        }
				        
				        $total_scores[$uid] = $total_score;
				        $test_scores[$uid] = $test_score;
					}
					
					//统计答对的认知过程关联试题
					$kp_right_ques_ids = array();
					if ($know_process_ques_ids)
					{
						foreach ($know_process_ques_ids as $know_process => $kp_ques_ids) 
						{
						    foreach ($u_ids as $uid)
						    {
						        foreach ($kp_ques_ids as $kp_ques_id)
						        {
						            $k = $exam_id."_".$paper_id."_".$uid."_".$kp_ques_id;
						            
						            if ($this->_data[$k]['full_score'] == $this->_data[$k]['test_score'])
						            {
						                $kp_right_ques_ids[$uid][$know_process][] = $kp_ques_id;
						            }
						        }
						    }
					    }
					}
					
					foreach ($u_ids as $uid)
					{
					    $total_score = isset($total_scores[$uid]) ? $total_scores[$uid] : 0;
					    $test_score = isset($test_scores[$uid]) ? $test_scores[$uid] : 0;
					    $kp_right_ques_id = isset($kp_right_ques_ids[$uid]) ? $kp_right_ques_ids[$uid] : array();
					    
					    $data = array(
					            'exam_pid'	=> $exam_pid,
					            'exam_id' 	=> $exam_id,
					            'paper_id' 	=> $paper_id,
					            'uid' 		=> $uid,
					            'knowledge_id' => $knowledge_id,
					            'know_process_ques_id' => json_encode($kp_right_ques_id),
					            'total_score'   => $total_score,
					            'test_score' 	=> $test_score,
					            'is_parent' 	=> $is_parent,
					            'ctime'         => $time
					    );
					    
					    self::$_db->replace('rd_summary_student_knowledge', $data);
					}
				}
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    self::$_db->delete('rd_summary_student_knowledge', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($paper_data);
		unset($papers);
		unset($student_papers);
		unset($paper_info);
		unset($know_process_ques_ids);
	}
	
	/**
	 * 关联 方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!$papers || empty($papers['paper'])) 
		{
			return false;
		}
		
		/*
		 * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
		 */
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
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
		
		$time = time();
		$data = array();
		foreach ($student_papers as $exam_id => $paper) 
		{
			//获取该区域的方法策略统计信息
			foreach ($paper as $paper_id => $u_ids) 
			{
			    if (empty($paper_data[$paper_id]) || !$u_ids) 
			    {
			        continue;
			    }
					
				$paper_info = $paper_data[$paper_id];
				
				foreach ($paper_info as $u_paper) 
				{
					//获取方法策略关联试题的试题得分
					$method_tactic_id = $u_paper['method_tactic_id'];
					$ques_ids = trim($u_paper['ques_id']);
					
					if ($ques_ids == '')
					{
					    continue;
					}
					
					$t_ques_ids = explode(',', $ques_ids);
					foreach ($u_ids as $uid)
					{
					    $test_score = 0;
					    foreach ($t_ques_ids as $ques_id)
					    {
					        $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
					        
					        $test_score += $this->_data[$k]['test_score'];
					    }
					    
					    $data = array(
					            'exam_pid'	=> $exam_pid,
					            'exam_id' 	=> $exam_id,
					            'paper_id' 	=> $paper_id,
					            'uid' 		=> $uid,
					            'method_tactic_id'	=> $method_tactic_id,
					            'test_score' 	=> $test_score,
					            'ctime' => $time
					    );
					    
					    self::$_db->replace('rd_summary_student_method_tactic', $data);
					}
				}
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    self::$_db->delete('rd_summary_student_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($paper_data);
		unset($papers);
		unset($student_papers);
		unset($paper_info);
	}
	
	/**
	 * 关联 考生-学科-方法策略
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_subject_method_tactic($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return false;
		}
		
		//获取 考生的  的考试试卷
		$papers = $this->_get_exam_student_papers($exam_pid);
		if (!$papers || empty($papers['paper'])) 
		{
			return false;
		}
		
		/*
		 * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
		 */
	
		//循环每个学科进行统计
		$student_papers = $papers['student_paper'];
		$all_paper_ids = implode(',', $papers['paper']);
		
		//该期次考试的所有试卷ID
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
		
		$time = time();
		$data = array();
		foreach ($student_papers as $exam_id => $paper) 
		{
			//获取该区域的方法策略统计信息
			foreach ($paper as $paper_id => $u_ids) 
			{
			    if (empty($paper_data[$paper_id]) || !$u_ids) 
			    {
			        continue;
			    }
					
				$paper_info = $paper_data[$paper_id];
				
				foreach ($paper_info as $u_paper) 
				{
					//获取方法策略关联试题的试题得分
					$method_tactic_id = $u_paper['method_tactic_id'];
					$ques_ids = trim($u_paper['ques_id']);
					
					if ($ques_ids == '') 
					{
					    continue;
					}
					
					$t_ques_ids = explode(',', $ques_ids);
					$ques_num = count($t_ques_ids);
					foreach ($u_ids as $uid)
					{
					    $u_test_score = 0;
					    	
					    foreach ($t_ques_ids as $ques_id)
					    {
					        $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
					        
					        $test_score = $this->_data_student_test_question[$k]['test_score'];
					        $difficulty = $this->_data_student_test_question[$k]['difficulty'];
					        
					        if ($test_score > 0)
					        {
					            $u_test_score += (100 - $difficulty);
					        }
					        else
					        {
					            $u_test_score += (0 - $difficulty);
					        }
					    }
					    	
					    $data = array(
					            'exam_pid' => $exam_pid,
					            'exam_id'  => $exam_id,
					            'paper_id' => $paper_id,
					            'method_tactic_id' => $method_tactic_id,
					            'uid'      => $uid,
					            '`usage`'    => $u_test_score/$ques_num,
					            'ctime'    => $time
					    );
					    
					    self::$_db->replace('rd_summary_student_subject_method_tactic', $data);
					}
				}
			}
		}
		
		if ($data) 
		{
			//清除多余的数据
		    self::$_db->delete('rd_summary_student_subject_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
		}
		
		unset($data);
		unset($paper_data);
		unset($papers);
		unset($student_papers);
		unset($paper_info);
	}
	
	/**
	 * 关联 信息提取方式
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_student_group_type($exam_pid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid) 
	    {
	        return false;
	    }
	
	    //获取 考生的  的考试试卷
	    $papers = $this->_get_exam_student_papers($exam_pid);
	    if (!$papers || empty($papers['paper'])) 
	    {
	        return false;
	    }
	
	    /*
	     * 获取这些试卷关联的信息提取方式 from->rd_summary_paper_group_type
	    */
	    //循环每个学科进行统计
	    $student_papers = $papers['student_paper'];
	    $all_paper_ids = implode(',', $papers['paper']);
	
	    //该期次考试的所有试卷ID
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
    	
    	$time = time();
    	$data = array();
    	foreach ($student_papers as $exam_id => $paper)
    	{
        	//获取该区域的信息提取方式统计信息
        	foreach ($paper as $paper_id => $u_ids)
        	{
        	    if (empty($paper_data[$paper_id]) || !$u_ids)
        	    {
        	        continue;
        	    }
                		
            	$paper_info = $paper_data[$paper_id];
            	
            	foreach ($paper_info as $u_paper)
            	{
            	    //获取信息提取方式关联试题的试题得分
            	    $group_type_id = $u_paper['group_type_id'];
            	    $ques_ids = trim($u_paper['ques_id']);
            	    $is_parent = $u_paper['is_parent'];
            	    
            	    if ($ques_ids == '' || $group_type_id < 1) 
            	    {
            	        continue;
            	    }
            	    
            	    $t_ques_ids = explode(',', $ques_ids);
            	    foreach ($u_ids as $uid)
            	    {
            	        $test_score = 0;
            	        foreach ($t_ques_ids as $ques_id)
            	        {
            	            $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
            	            $test_score += $this->_data[$k]['test_score'];
            	        }
            	        	
            	        $data = array(
            	                'exam_pid'	=> $exam_pid,
            	                'exam_id' 	=> $exam_id,
            	                'paper_id' 	=> $paper_id,
            	                'uid' 		=> $uid,
            	                'group_type_id'	=> $group_type_id,
            	                'test_score' 	=> $test_score,
            	                'is_parent' 	=> $is_parent,
            	                'ctime' => $time
            	        );
            	        
            	        self::$_db->replace('rd_summary_student_group_type', $data);
            	    }
    	        }
	        }
    	}
    	
	    if ($data) 
	    {
	        //清除多余的数据
	        self::$_db->delete('rd_summary_student_group_type', "exam_pid = $exam_pid AND ctime < $time");
	    }
	    
	    unset($data);
	    unset($paper_data);
	    unset($papers);
	    unset($student_papers);
	    unset($paper_info);
	}
	
	//=====================公共方法===============================================================
	/**
	 * 获取某期考试 按照考生分类
	 */
	private function _get_exam_student_papers($exam_pid = 0)
	{
		if ($this->_exam_student_papers)
		{
			return $this->_exam_student_papers;
		}
		
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) 
		{
			return array();
		}
		
		$data = array();
		
		/*
		 * 前提：已生成成绩
		 */
		$sql = "SELECT exam_id, paper_id, uid, subject_id
				FROM rd_exam_test_paper
				WHERE exam_pid = $exam_pid AND etp_flag = 2
				";
		
		$query = self::$_db->query($sql);
		$paper_ids = array();
		
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    $data[$item['exam_id']][$item['paper_id']][] = $item['uid'];
		    $paper_ids[] = $item['paper_id'];
		}
		
		$data = array('paper' => array_unique($paper_ids), 'student_paper' => $data);
		
		$this->_exam_student_papers = $data;
		
		return $this->_exam_student_papers;
	}
}
