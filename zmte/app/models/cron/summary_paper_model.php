<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 试卷相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_paper_model extends CI_Model 
{
    private $_exam_pid = 0;
    private static $_db;
    
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
	}
	
	/**
	 * 执行 试卷 相关所有关联脚本
	 * @return boolean
	 */
	public function do_all($exam_pid)
	{
	    $exam_pid = intval($exam_pid);
	    if (!$exam_pid)
	    {
	        return false;
	    }
	    
	    $this->_exam_pid = $exam_pid;
	    
		$this->summary_paper_difficulty();
		$this->summary_paper_knowledge();
		$this->summary_paper_method_tactic();
		$this->summary_paper_group_type();
		$this->summary_paper_question();
		
		return true;
	}
	
	/**
	 * 关联 难易度和题型 
	 */
	public function summary_paper_difficulty()
	{
	    if (!$this->_exam_pid)
	    {
	        return false;
	    }
	    
	    $exam_pid = $this->_exam_pid;
	    
		$sql = "SELECT * FROM v_summary_paper_difficulty
		        WHERE exam_pid = $exam_pid
				";
		
		$query = self::$_db->query($sql);
		
		$data = array();
		
		while ($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$paper_id = $row['paper_id'];
			$ques_id = $row['ques_id'];
			$difficulty = $row['difficulty'];
			$type = (int)$row['type'];
			
			if (!isset($data[$paper_id]))
			{
			    $data[$paper_id] = array();
			}
			
			if (!isset($data[$paper_id][$type])) {
				$data[$paper_id][$type] = array(
						'paper_id'		=> $paper_id,
						'q_type'		=> $type,
						 
						//试题数
						'low_q_amount' 	=> '0',	
						'mid_q_amount' 	=> '0',	
						'high_q_amount' => '0',
						
						//占比
						'low_percent' => '0',
						'mid_percent' => '0',
						'high_percent'=> '0',
							
						//关联试题ID
						'low_ques_id' 	=> array(),	
						'mid_ques_id' 	=> array(),	
						'high_ques_id' 	=> array(),	
				);
			}
			
			if ($difficulty >= 0 && $difficulty < 30)
			{
				//难易度（高）
				$data[$paper_id][$type]['high_q_amount']++;
				$data[$paper_id][$type]['high_ques_id'][] = $ques_id;
				
			}
			else if ($difficulty >= 30 && $difficulty <= 60) 
			{
				//难易度（中）
				$data[$paper_id][$type]['mid_q_amount']++;
				$data[$paper_id][$type]['mid_ques_id'][] = $ques_id;
				
			}
			else if ($difficulty > 60 && $difficulty <= 100)
			{
				//难易度（低）
				$data[$paper_id][$type]['low_q_amount']++;
				$data[$paper_id][$type]['low_ques_id'][] = $ques_id;
			}
		}
		
		unset($query);
		
		$time = time();
		
		// 计算百分比
		if ($data)
		{
			foreach ($data as $paper_id => $paper)
			{
				foreach ($paper as $type => $item) 
				{
					$total_amount = $item['low_q_amount'] + $item['mid_q_amount'] + $item['high_q_amount'];
					
					$item['low_percent']  = $item['low_q_amount'] * 100 / $total_amount;
					$item['mid_percent']  = $item['mid_q_amount'] * 100 / $total_amount;
					$item['high_percent'] = $item['high_q_amount'] * 100 / $total_amount;
					
					$item['low_ques_id']  = implode(',', array_unique($item['low_ques_id']));
					$item['mid_ques_id']  = implode(',', array_unique($item['mid_ques_id']));
					$item['high_ques_id'] = implode(',', array_unique($item['high_ques_id']));
					
					$item['ctime'] = $time;
					
					self::$_db->replace('rd_summary_paper_difficulty', $item);
				}
				
				//清除多余的数据
				self::$_db->delete('rd_summary_paper_difficulty', "paper_id = $paper_id AND ctime < $time");
			}
		}
		
		unset($data);
	}
	
	/**
	 * 关联 一级知识点
	 * @note:
	 * 	关联表： rd_summary_paper_knowledge
	 */
	public function summary_paper_knowledge()
	{
	    if (!$this->_exam_pid)
	    {
		    return false;
	    }
	    
	    $exam_pid = $this->_exam_pid;
	    
        $sql = "SELECT * FROM v_summary_paper_knowledge 
                WHERE exam_pid = $exam_pid
               ";
        $query = self::$_db->query($sql);
        
        //知识点总计出现次数
        $knowledge_count = array();
        
        //一级知识点试题
        $parent_knowledges_ques = array();
        
        //二级知识点试题及知识点认知过程对应的试题
        $child_knowledges_ques = array();
        $child_knowledges_process = array();
        
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
        	$paper_id = $row['paper_id'];

        	if (!isset($knowledge_count[$paper_id]))
        	{
        	    $knowledge_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
        	}
        	
        	//一级知识点
        	$k = $paper_id . "_" . $row['pid'];
        	
        	//判断是否已经循环过此试卷中对应一级知识点下的试题
        	if (!isset($parent_knowledges_ques[$k])
                || !in_array($row['ques_id'], $parent_knowledges_ques[$k]))
        	{
        	    $knowledge_count[$paper_id]['parent']++;
        	}
        	
        	$parent_knowledges_ques[$k][] = $row['ques_id'];
        	
        	//二级知识点
    	    $knowledge_count[$paper_id]['child']++;
        	$child_knowledges_ques[$k][$row['id']][] = $row['ques_id'];
            if ($row['know_process'] > 0)
            {
                $child_knowledges_process[$k][$row['id']][$row['know_process']][] = $row['ques_id'];
            }
        }
        
        unset($query);
        
        $time = time();
        
        // 计算百分比
        if ($parent_knowledges_ques)
        {
        	foreach ($parent_knowledges_ques as $key => $ques_ids)
        	{
        		list ($paper_id, $p_k_id) = explode('_', $key);
        		
        		if ($p_k_id < 1) continue;
        		
        		//一级知识点
        		$ques_ids = array_unique($ques_ids);
        		$q_mount = count($ques_ids);
        		
        		$percent = $q_mount * 100 / $knowledge_count[$paper_id]['parent'];
        		 
        		$insert_data = array(
        		    'paper_id'	   => $paper_id,
        		    'knowledge_id' => $p_k_id,
        		    'q_amount'     => $q_mount,
        		    'percent'      => $percent,
        		    'ques_id'      => implode(",", $ques_ids),
        		    'know_process_ques_id' => '0',
        		    'is_parent'    => '1',
        		    'ctime'        => $time
        		);
        		
        		self::$_db->replace('rd_summary_paper_knowledge', $insert_data);
        		
        		if (isset($child_knowledges_ques[$key])
        		        && $children = $child_knowledges_ques[$key])
        		{
        			foreach ($children as $k_id => $child_ques_ids)
        			{
        				if ($k_id < 1) continue;
        				
        				$child_ques_ids = array_unique($child_ques_ids);
        				$q_mount = count($child_ques_ids);
	                    
	                    $percent = $q_mount * 100 / $knowledge_count[$paper_id]['child'];
	                    $know_process_ques_id = isset($child_knowledges_process[$key][$k_id]) 
	                                               ? json_encode($child_knowledges_process[$key][$k_id]) : 0;
	                    
	                    $insert_data = array(
	                            'paper_id'		=> $paper_id,
	                            'knowledge_id'	=> $k_id,
	                            'q_amount'		=> $q_mount,
	                            'percent'  		=> $percent,
	                            'ques_id' 		=> implode(",", $child_ques_ids) ,
	                            'know_process_ques_id' => $know_process_ques_id,
	                            'is_parent' 	=> '0',
	                            'ctime' 		=> $time
	                    );
	                    
	                    self::$_db->replace('rd_summary_paper_knowledge', $insert_data);
        			}
        		}
        	
        		//清除多余的数据
        		self::$_db->delete('rd_summary_paper_knowledge', "paper_id = $paper_id AND ctime < $time");
        	}
        }
        
        unset($knowledge_count);
        unset($parent_knowledges_ques);
        unset($child_knowledges_process);
        unset($child_knowledges_ques);
	}
	
	/**
	 * 关联 方法策略
	 * @note:
	 * 	关联表： rd_summary_paper_method_tactic
	 */
	public function summary_paper_method_tactic()
	{
	    if (!$this->_exam_pid)
	    {
	        return false;
	    }
	     
	    $exam_pid = $this->_exam_pid;
	     
		
		$sql = "SELECT * FROM v_summary_paper_method_tactic
                WHERE exam_pid = $exam_pid
		        ";
		$query = self::$_db->query($sql);
		
		//方法策略总计出现次数
		$method_tactic_count = array();
		
		//方法策略试题
		$method_tactics = array();
		
		while ($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$paper_id = $row['paper_id'];
			$method_tactic_id = intval($row['method_tactic_id']);
			
			if (!$method_tactic_id)
			{
			    continue;
			}
			
			if (!isset($method_tactic_count[$paper_id]))
			{
			    $method_tactic_count[$paper_id] = 0;
			}
			
			$method_tactic_count[$paper_id]++;
			
			
			if (!isset($method_tactics[$paper_id][$method_tactic_id]))
			{
				$method_tactics[$paper_id][$method_tactic_id] = array(
						'ques_id' => array()
				);
			} 
			
			$method_tactics[$paper_id][$method_tactic_id]['ques_id'][] = $row['ques_id'];
		}
		
		$time = time();
		
		// 计算百分比
		if (count($method_tactics))
		{
			foreach ($method_tactics as $paper_id => $paper)
			{
				foreach ($paper as $method_tactic_id => $item) 
				{
				    $item['ques_id'] = array_unique($item['ques_id']);
				    $item['num']     = count($item['ques_id']);
					$item['percent'] = $item['num'] * 100/ $method_tactic_count[$paper_id];
					
					$insert_data = array(
					        'paper_id'	        => $paper_id,
					        'method_tactic_id'	=> $method_tactic_id,
					        'q_amount'	        => $item['num'],
					        'percent'           => $item['percent'],
					        'ques_id'           => implode(',', $item['ques_id']) ,
					        'ctime'             => $time
					);
					
					$this->db->replace('rd_summary_paper_method_tactic', $insert_data);
				}
				
				//清除多余的数据
				$this->db->delete('rd_summary_paper_method_tactic', "paper_id = $paper_id AND ctime < $time");
			}
		}
		
		unset($method_tactic_count);
		unset($method_tactics);
	}
	/**
	 * 关联 一级题组类型（信息提取方式）
	 * @note:
	 * 	关联表： rd_summary_paper_group_type
	 */
	public function summary_paper_group_type()
	{
	    if (!$this->_exam_pid)
	    {
	        return false;
	    }
	     
	    $exam_pid = $this->_exam_pid;
	     
	    $sql = "SELECT * FROM v_summary_paper_group_type
        	    WHERE exam_pid = $exam_pid
        	   ";
	
	    $query = self::$_db->query($sql);
	
	    //题组类型（信息提取方式）总计出现次数
	    $group_type_count = array();
	     
	    //一级信息提取方式
	    $parent_group_type_ques = array();
	     
	    //二级信息提取方式
	    $child_group_type_ques = array();
	     
	    while ($row = $query->fetch(PDO::FETCH_ASSOC))
	    {
            $paper_id = $row['paper_id'];
	
            if (!isset($group_type_count[$paper_id]))
            {
                $group_type_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
            }
            
            // 一级题组类型（信息提取方式）
            $k = $paper_id . "_" . $row['pid'];
             
            if (!isset($parent_group_type_ques[$k])
                || !in_array($row['ques_id'], $parent_group_type_ques[$k]))
            {
                $group_type_count[$paper_id]['parent']++;
            }
                 
            $parent_group_type_ques[$k][] = $row['ques_id'];
            
            // 二级题组类型（信息提取方式）
            $group_type_count[$paper_id]['child']++;
            $child_group_type_ques[$k][$row['id']][] = $row['ques_id'];
	    }
	     
	    $time = time();
	
	    // 计算百分比
	    if ($parent_group_type_ques)
	    {
	        foreach ($parent_group_type_ques as $key => $ques_ids)
	        {
	            list($paper_id, $p_gt_id) = explode('_', $key);
	             
	            if ($p_gt_id < 1)
	            {
	                continue;
	            }
	             
	            $ques_ids = array_unique($ques_ids);
	            $q_mount  = count($ques_ids);
	            $percent  = $q_mount * 100 / $group_type_count[$paper_id]['parent'];
	
	            $insert_data = array(
	                'paper_id'	    => $paper_id,
	                'group_type_id'	=> $p_gt_id,
	                'q_amount'	    => $q_mount,
	                'percent'  	    => $percent,
	                'ques_id' 	    => implode(',', $ques_ids) ,
	                'is_parent'     => '1',
	                'ctime'         => $time
	            );
	
	            self::$_db->replace('rd_summary_paper_group_type', $insert_data);
	
	            if (isset($child_group_type_ques[$key])
	                    && $children = $child_group_type_ques[$key])
	            {
	                foreach ($children as $gt_id => $child_ques_ids)
	                {
	                    $child_ques_ids = array_unique($child_ques_ids);
	                    $q_amount       = count($child_ques_ids);
	                    $percent        = $q_amount * 100 / $group_type_count[$paper_id]['child'];
	
	                    $insert_data = array(
	                        'paper_id'		=> $paper_id,
	                        'group_type_id'	=> $gt_id,
	                        'q_amount'		=> $q_amount,
	                        'percent'  		=> $percent,
	                        'ques_id' 		=> implode(',', $child_ques_ids) ,
	                        'is_parent' 	=> '0',
	                        'ctime' 		=> $time
	                    );
	
	                    self::$_db->replace('rd_summary_paper_group_type', $insert_data);
	                }
	            }
	
	            //清除多余的数据
	            self::$_db->delete('rd_summary_paper_group_type', "paper_id = $paper_id AND ctime < $time");
	        }
	    }
	     
	    unset($group_type_count);
	    unset($parent_group_type_ques);
	    unset($child_group_type_ques);
	}
	
	//===================================关联试题===================================================//
	
	/**
	 * 关联 试题
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_paper_question()
	{
        $exam_pid = intval($this->_exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $questions = array();
        
        $sql = "SELECT * FROM v_summary_paper_question
                WHERE exam_pid={$exam_pid} AND test_score IS NOT NULL";
        
        $result = self::$_db->fetchAll($sql);
        
        //提取试题列表
        $ques_ids = array();
        foreach ($result as $item)
        {
            $ques_ids[] = $item['ques_id'];
        }
	
        //获取题组部分
        $parent_ques = array();
        if ($ques_ids)
        {
            $t_ques_id = implode(',', $ques_ids);
            $sql = "SELECT parent_id, COUNT(parent_id) AS `count`
                    FROM rd_question
                    WHERE parent_id IN($t_ques_id) AND is_delete = 0
                    GROUP BY parent_id
                    ";
        
            $parent_ques = self::$_db->fetchPairs($sql);
        }
        
        $time = time();

        foreach ($result as $item)
        {
            $test_score = $item['test_score'];
            $difficulty = $item['difficulty'];
            if (is_null($test_score) || is_null($difficulty))
            {
                continue;
            }
            	
            if (isset($parent_ques[$item['ques_id']]))
            {
                $item['expect_score'] = (round(($parent_ques[$item['ques_id']] * $test_score)) * $difficulty) / 100;
            }
            else
            {
                $item['expect_score'] = ($test_score * $difficulty)/100;
            }
            	
            unset($item['type']);
            unset($item['test_score']);
            unset($item['difficulty']);
            	
            $item['exam_pid'] = $exam_pid;
            $item['ctime'] = $time;
            $item['mtime'] = $time;
            	
            self::$_db->replace('rd_summary_paper_question', $item);
        }
        
        //清除多余的数据
        self::$_db->delete('rd_summary_paper_question', "exam_pid = $exam_pid AND mtime < $time");
        
        unset($result);
        unset($parent_ques);
    }
}
