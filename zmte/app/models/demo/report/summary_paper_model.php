<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 汇总数据 试卷相关
 * @author TCG
 * @final 2015-07-17
 */
class Summary_paper_model extends CI_Model 
{
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * 执行 试卷 相关所有关联脚本
	 * @return boolean
	 */
	public function do_all()
	{
		$this->summary_paper_difficulty();
		$this->summary_paper_knowledge();
		$this->summary_paper_method_tactic();
		$this->summary_paper_group_type();
	}
	
	/**
	 * 关联 难易度和题型 
	 */
	public function summary_paper_difficulty()
	{
		$insert_data = array();
		$update_data = array();
		
		$sql = "select eq.paper_id,eq.ques_id,rc.difficulty,q.type
				from {pre}exam_question eq
				left join {pre}exam e on eq.exam_id=e.exam_id
				left join {pre}relate_class rc on eq.ques_id=rc.ques_id and rc.grade_id=e.grade_id and rc.class_id=e.class_id and rc.subject_type=e.subject_type
				left join {pre}question q on eq.ques_id=q.ques_id
				";
		
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		$data = array();
		foreach ($result as $row)
		{
			$paper_id = $row['paper_id'];
			$ques_id = $row['ques_id'];
			$difficulty = $row['difficulty'];
			$type = (int)$row['type'];
			
			if (!isset($data[$paper_id])) $data[$paper_id] = array(); 
			
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
			
			if ($difficulty >= 0 && $difficulty < 30) {
				//难易度（高）
				$data[$paper_id][$type]['high_q_amount']++;
				$data[$paper_id][$type]['high_ques_id'][] = $ques_id;
				
			} else if ($difficulty >= 30 && $difficulty <= 60) {
				//难易度（中）
				$data[$paper_id][$type]['mid_q_amount']++;
				$data[$paper_id][$type]['mid_ques_id'][] = $ques_id;
				
			} else if ($difficulty > 60 && $difficulty <= 100) {
				//难易度（低）
				$data[$paper_id][$type]['low_q_amount']++;
				$data[$paper_id][$type]['low_ques_id'][] = $ques_id;
			}
		}
		unset($result);
		
		// 计算百分比
		if (count($data))
		{
			foreach ($data as $paper_id=>&$paper)
			{
				foreach ($paper as $type=>&$item) {
					$total_amount = $item['low_q_amount'] + $item['mid_q_amount'] + $item['high_q_amount'];
					
					$item['low_percent'] = $item['low_q_amount']*100/$total_amount;
					$item['mid_percent'] = $item['mid_q_amount']*100/$total_amount;
					$item['high_percent'] = $item['high_q_amount']*100/$total_amount;
					
					$item['low_ques_id'] = implode(',', array_unique($item['low_ques_id']));
					$item['mid_ques_id'] = implode(',', array_unique($item['mid_ques_id']));
					$item['high_ques_id'] = implode(',', array_unique($item['high_ques_id']));
				}
			}
		}
		
		//获取老的汇总数据
// 		$old_result = $this->db->query("select id, paper_id, q_type from {pre}summary_paper_difficulty")->result_array();
// 		$old_ids = array();
// 		foreach ($old_result as $row)
// 		{
// 			$k = $row['paper_id']."_".$row['q_type'];
// 			$old_ids[$k] = $row['id'];
// 		}
// 		unset($old_result);
		
//		pr($data,1);
		foreach ($data as $paper_id=>&$paper)
		{
			foreach ($paper as $q_type=>$item)
			{
			    //获取老的汇总数据
			    $old_result = $this->db->select('id, paper_id, q_type')
			    ->get_where('summary_paper_difficulty',array('paper_id'=>$paper_id,'q_type'=>$q_type))->result_array();
			    $old_ids = array();
			    foreach ($old_result as $row)
	    		{
	    			$k = $row['paper_id']."_".$row['q_type'];
	    			$old_ids[$k] = $row['id'];
	    		}
	    		unset($old_result);
			    
				$k = "{$paper_id}_{$q_type}";
				if (isset($old_ids[$k])) {
					
					unset($item['paper_id']);
					unset($item['q_type']);
					
					$item['id'] = $old_ids[$k];
					$update_data[] = $item;
					
					unset($old_ids[$k]);
				} else {
					$item['ctime'] = time();
					unset($item['id']);
					$insert_data[] = $item;
				}
				
				if (count($insert_data)) {
				    $this->db->insert_batch('summary_paper_difficulty', $insert_data);
				}
				
				if (count($update_data)) {
				    $this->db->update_batch('summary_paper_difficulty', $update_data, 'id');
				}
				
				if (count($old_ids)) {
				    $this->db->where_in('id', array_values($old_ids))->delete('summary_paper_difficulty');
				}
				
				$insert_data = array();
				$update_data = array();
				$old_ids = array();
			}
		}
		
		unset($data);
	}
	
	/**
	 * 关联 一级知识点
	 * @note:
	 * 	关联表： {pre}summary_paper_knowledge
	 */
	public function summary_paper_knowledge()
	{
		$insert_data = array();
		$update_data = array();
		
		$knowledges = array();
        $pknowledge_ques = array();
        
        //非题组
        $sql = "SELECT eq.paper_id, rk.ques_id,k.id,k.pid,rk.know_process FROM 
        		 {pre}exam_question eq 
        		 LEFT JOIN {pre}question q on eq.ques_id=q.ques_id
                 LEFT JOIN {pre}relate_knowledge rk ON eq.ques_id=rk.ques_id 
                 LEFT JOIN {pre}knowledge k ON rk.knowledge_id=k.id
                 LEFT JOIN {pre}knowledge kp ON k.pid=kp.id
                 WHERE q.type IN (1,2,3,7,9) AND k.id > 0 AND k.pid > 0";
        
        $query = $this->db->query($sql);
        $result = $query->result_array();
        
        //题组
        $sql = "SELECT eq.paper_id,rk.ques_id,k.id,k.pid,rk.know_process FROM
        		(
        			select q.ques_id,eq.paper_id
        			from {pre}question q, {pre}exam_question eq
        			where q.parent_id=eq.ques_id
        		) eq LEFT JOIN {pre}relate_knowledge rk ON rk.ques_id=eq.ques_id
	        	LEFT JOIN {pre}knowledge k ON rk.knowledge_id=k.id
	        	LEFT JOIN {pre}knowledge kp ON k.pid=kp.id
                WHERE k.id > 0 AND k.pid > 0
	         ";
		
        $query = $this->db->query($sql);
        $group_result = $query->result_array();
        foreach ($group_result as $row) {
        	if (!$row['ques_id']) {
        		continue;
        	}
        	$result[] = $row;
        }
        unset($group_result);
        
        //知识点总计出现次数
        $knowledge_count = array();
        foreach ($result as $row)
        {
        	$paper_id = $row['paper_id'];
        	
        	if(!isset($knowledge_count[$paper_id])) $knowledge_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
        	
            // 一级知识点
            if ( ! isset($knowledges[$paper_id][$row['pid']]))
            {
				$knowledge_count[$paper_id]['parent']++;
                $knowledges[$paper_id][$row['pid']] = array(
                    'id'       => $row['pid'],
                    'num'      => 1,
                    'ques_id'  => array(),
                    'percent'  => '0.00',
                    'children' => array(),
                );                
            }
            elseif( ! isset($pknowledge_ques[$paper_id][$row['pid']][$row['ques_id']]))
            {
				$knowledge_count[$paper_id]['parent']++;
                $knowledges[$paper_id][$row['pid']]['num']++;
            }
            $pknowledge_ques[$paper_id][$row['pid']][$row['ques_id']] = 1;
            
            // 二级知识点
            if ( ! isset($knowledges[$paper_id][$row['pid']]['children'][$row['id']]))
            {
                $child = array(
                    'id'      => $row['id'],
                    'num'     => 1,
					'ques_id' => array(),
                    'percent' => '0.00',
                );
                $knowledges[$paper_id][$row['pid']]['children'][$row['id']] = $child;
				$knowledge_count[$paper_id]['child']++;
            }
            else
            {
				$knowledge_count[$paper_id]['child']++;
                $knowledges[$paper_id][$row['pid']]['children'][$row['id']]['num']++;
            }
            
            $knowledges[$paper_id][$row['pid']]['ques_id'][] = $row['ques_id'];
            $knowledges[$paper_id][$row['pid']]['children'][$row['id']]['ques_id'][] = $row['ques_id'];
            $row['know_process'] > 0 && $knowledges[$paper_id][$row['pid']]['children'][$row['id']]['know_process_ques_id'][$row['know_process']][] = $row['ques_id'];
        }
        unset($pknowledge_ques);
        unset($result);
        
        // 计算百分比
        if (count($knowledges))
        {
            foreach ($knowledges as $paper_id=>&$paper)
            {
            	foreach ($paper as $p_k_id=>&$parent) {
            	    
            	    if ($p_k_id < 1) continue;
            	    
            		//一级知识点
	                $parent['percent'] = $parent['num']*100/$knowledge_count[$paper_id]['parent'];
	                $parent['ques_id'] = array_unique($parent['ques_id']);
	                
	                //二级知识点
	                foreach ($parent['children'] as $k_id=>&$child)
	                {
	                    if ($k_id < 1) continue;
	                    
	                    $child['percent'] = $child['num']*100/$knowledge_count[$paper_id]['child'];
	                    $child['ques_id'] = array_unique($child['ques_id']);
	                }
                }
            }
        }
        unset($knowledge_count);
        
        //获取老的汇总数据
//         $old_result = $this->db->query("select id, paper_id, knowledge_id from {pre}summary_paper_knowledge")->result_array();
//         $old_ids = array();
//         foreach ($old_result as $row) 
//         {
//         	$k = $row['paper_id']."_".$row['knowledge_id'];
//         	$old_ids[$k] = $row['id'];
//         }
//         unset($old_result);
        
        foreach ($knowledges as $paper_id=>&$paper) 
        {
        	foreach ($paper as $p_k_id=>&$parent) 
        	{
        	    //获取老的汇总数据
        	    $old_result = $this->db->select('id, paper_id, knowledge_id')
        	       ->get_where('summary_paper_knowledge',array('paper_id'=>$paper_id,'knowledge_id'=>$p_k_id))->result_array();
        	    $old_ids = array();
        	    foreach ($old_result as $row)
        	    {
        	        $k = $row['paper_id']."_".$row['knowledge_id'];
        	        $old_ids[$k] = $row['id'];
        	    }
        	    unset($old_result);
        	    
        		//一级知识点
        		$k = "{$paper_id}_{$p_k_id}";
        		if (isset($old_ids[$k])) {
        			$update_data[] = array(
        					'id' 		=> $old_ids[$k],
        					'q_amount'	=> $parent['num'],
        					'percent'  	=> $parent['percent'],
        					'ques_id' 	=> implode(',', $parent['ques_id']),
        					'is_parent' => '1',
        			);
        			unset($old_ids[$k]);
        		} else {
        			$insert_data[] = array(
        					'paper_id'	=> $paper_id,
        					'knowledge_id'	=> $p_k_id,
        					'q_amount'	=> $parent['num'],
        					'percent'  	=> $parent['percent'],
        					'ques_id' 	=> implode(',', $parent['ques_id']),
        					'know_process_ques_id' 	=> '',
        					'is_parent' => '1',
        					'ctime' => time(),
        			);
        		}
        		
        		$old_id = $old_ids;
        		$old_ids = array();
        		
        		//二级知识点
        		foreach ($parent['children'] as $k_id=>&$child)
        		{
        		    //获取老的汇总数据
        		    $old_result = $this->db->select('id, paper_id, knowledge_id')
        		    ->get_where('summary_paper_knowledge',array('paper_id'=>$paper_id,'knowledge_id'=>$k_id))->result_array();
        		    foreach ($old_result as $row)
        		    {
        		        $k = $row['paper_id']."_".$row['knowledge_id'];
        		        $old_ids[$k] = $row['id'];
        		    }
        		    unset($old_result);
        		    
	        		$k = "{$paper_id}_{$k_id}";
	        		if (isset($old_ids[$k])) {
	        			$update_data[] = array(
	        					'id' 		=> $old_ids[$k],
	        					'q_amount'	=> $child['num'],
	        					'percent'  	=> $child['percent'],
	        					'ques_id' 	=> implode(',', $child['ques_id']),
	        					'know_process_ques_id' => isset($child['know_process_ques_id']) ? serialize($child['know_process_ques_id']) : '',
	        					'is_parent' => '0',
	        			);
	        			unset($old_ids[$k]);
	        		} else {
	        			$insert_data[] = array(
	        					'paper_id'		=> $paper_id,
	        					'knowledge_id'	=> $k_id,
	        					'q_amount'		=> $child['num'],
	        					'percent'  		=> $child['percent'],
	        					'ques_id' 		=> implode(',', $child['ques_id']),
	        					'know_process_ques_id' => isset($child['know_process_ques_id']) ? serialize($child['know_process_ques_id']) : '',
	        					'is_parent' 	=> '0',
	        					'ctime' 		=> time(),
	        			);
	        		}
	        		
	        		$old_ids = array();
        		}
        		
        		$old_id = array_merge($old_id, $old_ids);
        		
        		if (count($insert_data)) {
        		    $this->db->insert_batch('summary_paper_knowledge', $insert_data);
        		}
        		
        		if (count($update_data)) {
        		    $this->db->update_batch('summary_paper_knowledge', $update_data, 'id');
        		}
        		
        		if (count($old_id)) {
        		    $this->db->where_in('id', array_values($old_id))->delete('summary_paper_knowledge');
        		}
        		
        		$insert_data = array();
        		$update_data = array();
        		$old_id = array();
        	}
        }
        
        unset($knowledges);
	}
	
	/**
	 * 关联 方法策略
	 * @note:
	 * 	关联表： {pre}summary_paper_method_tactic
	 */
	public function summary_paper_method_tactic()
	{
		$insert_data = array();
		$update_data = array();
		
		$method_tactics = array();
		
		//非题组
		$sql = "SELECT eq.paper_id,rmt.ques_id,rmt.method_tactic_id FROM
        		 {pre}exam_question eq
        		 LEFT JOIN {pre}question q on eq.ques_id=q.ques_id
                 LEFT JOIN {pre}relate_method_tactic rmt ON eq.ques_id=rmt.ques_id
                 WHERE q.type IN (1,2,3,7)";
		
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		//题组
		$sql = "SELECT eq.paper_id,rmt.ques_id,rmt.method_tactic_id FROM
	        	(
        			select q.ques_id,eq.paper_id
        			from {pre}question q, {pre}exam_question eq
        			where q.parent_id=eq.ques_id
        		) eq LEFT JOIN {pre}relate_method_tactic rmt 
				ON rmt.ques_id=eq.ques_id 
	         ";
		
		$query = $this->db->query($sql);
		$group_result = $query->result_array();
		
		foreach ($group_result as $row) {
			if (!$row['ques_id']) {
				continue;
			}
			$result[] = $row;
		}
		unset($group_result);
		
		//方法策略总计出现次数
		$method_tactic_count = array();
		foreach ($result as $row)
		{
			$paper_id = $row['paper_id'];
			$method_tactic_id = intval($row['method_tactic_id']);
			
			if(!isset($method_tactic_count[$paper_id])) $method_tactic_count[$paper_id] = 0;
			$method_tactic_count[$paper_id]++;
			
			if (!$method_tactic_id) {
				continue;
			}
			 
			if ( ! isset($method_tactics[$paper_id][$method_tactic_id]))
			{
				$method_tactics[$paper_id][$method_tactic_id] = array(
						'method_tactic_id'  => $method_tactic_id,
						'num'      			=> 0,
						'ques_id'  			=> array(),
						'percent'  			=> '0.00',
				);
			} 
			
			$method_tactics[$paper_id][$method_tactic_id]['num']++;
			$method_tactics[$paper_id][$method_tactic_id]['ques_id'][] = $row['ques_id'];
		}
		unset($result);
		
		// 计算百分比
		if (count($method_tactics))
		{
			foreach ($method_tactics as $paper_id=>&$paper)
			{
				foreach ($paper as &$item) {
					$item['percent'] = $item['num']*100/$method_tactic_count[$paper_id];
					$item['ques_id'] = array_unique($item['ques_id']);
				}
			}
		}
		unset($method_tactic_count);
		
		//获取老的汇总数据
// 		$old_result = $this->db->query("select id, paper_id, method_tactic_id from {pre}summary_paper_method_tactic")->result_array();
// 		$old_ids = array();
// 		foreach ($old_result as $row)
// 		{
// 			$k = $row['paper_id']."_".$row['method_tactic_id'];
// 			$old_ids[$k] = $row['id'];
// 		}
// 		unset($old_result);
		
		foreach ($method_tactics as $paper_id=>&$paper)
		{
			foreach ($paper as &$item)
			{
			    $mt_id = $item['method_tactic_id'];
			    
			    //获取老的汇总数据
			    $old_result = $this->db->select('id, paper_id, method_tactic_id')
			    ->get_where('summary_paper_method_tactic',array('paper_id'=>$paper_id,'method_tactic_id'=>$mt_id))
			    ->result_array();
			    $old_ids = array();
			    foreach ($old_result as $row)
			    {
			        $k = $row['paper_id']."_".$row['method_tactic_id'];
			        $old_ids[$k] = $row['id'];
			    }
			    unset($old_result);
				
				$k = "{$paper_id}_{$mt_id}";
				if (isset($old_ids[$k])) {
					$update_data[] = array(
							'id' 		=> $old_ids[$k],
							'q_amount'	=> $item['num'],
							'percent'  	=> $item['percent'],
							'ques_id' 	=> implode(',', $item['ques_id']),
					);
					unset($old_ids[$k]);
				} else {
					$insert_data[] = array(
							'paper_id'	=> $paper_id,
							'method_tactic_id'	=> $mt_id,
							'q_amount'	=> $item['num'],
							'percent'  	=> $item['percent'],
							'ques_id' 	=> implode(',', $item['ques_id']),
							'ctime' => time(),
					);
				}
				
				if (count($insert_data)) {
				    $this->db->insert_batch('summary_paper_method_tactic', $insert_data);
				}
				
				if (count($update_data)) {
				    $this->db->update_batch('summary_paper_method_tactic', $update_data, 'id');
				}
				
				if (count($old_ids)) {
				    $this->db->where_in('id', array_values($old_ids))->delete('summary_paper_method_tactic');
				}
				
				$insert_data = array();
				$update_data = array();;
				$old_ids = array();
			}
		}
		
		unset($method_tactics);
	}
	
	/**
	 * 关联 一级题组类型（信息提取方式）
	 * @note:
	 * 	关联表： {pre}summary_paper_group_type
	 */
	public function summary_paper_group_type()
	{
	    $insert_data = array();
	    $update_data = array();
	
	    $group_types = array();
	    $pgroup_type_ques = array();
	
	    //匹配题和题组
	    $sql = "SELECT eq.paper_id, rgr.ques_id,gr.id,gr.pid FROM
        		(
        			select q.ques_id,eq.paper_id
        			from {pre}question q, {pre}exam_question eq
        			where q.parent_id=eq.ques_id
        		) eq LEFT JOIN {pre}relate_group_type rgr ON eq.ques_id=rgr.ques_id
                 LEFT JOIN {pre}group_type gr ON rgr.group_type_id=gr.id
                 LEFT JOIN {pre}group_type grp ON gr.pid=grp.id where gr.id > 0
	         ";
	
	    $query = $this->db->query($sql);
	    $result = $query->result_array();
	
	    //题组类型（信息提取方式）总计出现次数
	    $group_type_count = array();
	    foreach ($result as $row)
	    {
	        $paper_id = $row['paper_id'];
	         
	        if(!isset($group_type_count[$paper_id])) $group_type_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
	         
	        // 一级题组类型（信息提取方式）
	        if ( ! isset($group_types[$paper_id][$row['pid']]))
	        {
	            $group_type_count[$paper_id]['parent']++;
	            $group_types[$paper_id][$row['pid']] = array(
	                    'id'       => $row['pid'],
	                    'num'      => 1,
	                    'ques_id'  => array(),
	                    'percent'  => '0.00',
	                    'children' => array(),
	            );
	        }
	        elseif( ! isset($pgroup_type_ques[$paper_id][$row['pid']][$row['ques_id']]))
	        {
	            $group_type_count[$paper_id]['parent']++;
	            $group_types[$paper_id][$row['pid']]['num']++;
	        }
	        $pgroup_type_ques[$paper_id][$row['pid']][$row['ques_id']] = 1;
	
	        // 二级题组类型（信息提取方式）
	        if ( ! isset($group_types[$paper_id][$row['pid']]['children'][$row['id']]))
	        {
	            $child = array(
	                    'id'      => $row['id'],
	                    'num'     => 1,
	                    'ques_id' => array(),
	                    'percent' => '0.00',
	            );
	            $group_types[$paper_id][$row['pid']]['children'][$row['id']] = $child;
	            $group_type_count[$paper_id]['child']++;
	        }
	        else
	        {
	            $group_type_count[$paper_id]['child']++;
	            $group_types[$paper_id][$row['pid']]['children'][$row['id']]['num']++;
	        }
	
	        $group_types[$paper_id][$row['pid']]['ques_id'][] = $row['ques_id'];
	        $group_types[$paper_id][$row['pid']]['children'][$row['id']]['ques_id'][] = $row['ques_id'];
	    }
	    
	    unset($pgroup_type_ques);
	    unset($result);
	
	    // 计算百分比
	    if (count($group_types))
	    {
	        foreach ($group_types as $paper_id=>&$paper)
	        {
	            foreach ($paper as $p_k_id=>&$parent) {
	                //一级题组类型（信息提取方式）
	                $parent['percent'] = $parent['num']*100/$group_type_count[$paper_id]['parent'];
	                $parent['ques_id'] = array_unique($parent['ques_id']);
	                 
	                //二级题组类型（信息提取方式）
	                foreach ($parent['children'] as $k_id=>&$child)
	                {
	                    $child['percent'] = $child['num']*100/$group_type_count[$paper_id]['child'];
	                    $child['ques_id'] = array_unique($child['ques_id']);
	                }
	            }
	        }
	    }
	    unset($group_type_count);
	
	    //获取老的汇总数据
// 	    $old_result = $this->db->query("select id, paper_id, group_type_id from {pre}summary_paper_group_type")->result_array();
// 	    $old_ids = array();
// 	    foreach ($old_result as $row)
// 	    {
// 	        $k = $row['paper_id']."_".$row['group_type_id'];
// 	        $old_ids[$k] = $row['id'];
// 	    }
// 	    unset($old_result);
	
	    foreach ($group_types as $paper_id=>&$paper)
	    {
	        foreach ($paper as $p_k_id=>&$parent)
	        {
	            //获取老的汇总数据
	            $old_result = $this->db->select('id, paper_id, group_type_id')
	            ->get_where('summary_paper_group_type',array('paper_id'=>$paper_id,'group_type_id'=>$p_k_id))
	            ->result_array();
	            $old_ids = array();
	            foreach ($old_result as $row)
	            {
	                $k = $row['paper_id']."_".$row['group_type_id'];
	                $old_ids[$k] = $row['id'];
	            }
	            unset($old_result);
	            
	            //一级题组类型（信息提取方式）
	            $k = "{$paper_id}_{$p_k_id}";
	            if (isset($old_ids[$k])) {
	                $update_data[] = array(
	                        'id' 		=> $old_ids[$k],
	                        'q_amount'	=> $parent['num'],
	                        'percent'  	=> $parent['percent'],
	                        'ques_id' 	=> implode(',', $parent['ques_id']),
	                        'is_parent' => '1',
	                );
	                unset($old_ids[$k]);
	            } else {
	                $insert_data[] = array(
	                        'paper_id'	=> $paper_id,
	                        'group_type_id'	=> $p_k_id,
	                        'q_amount'	=> $parent['num'],
	                        'percent'  	=> $parent['percent'],
	                        'ques_id' 	=> implode(',', $parent['ques_id']),
	                        'is_parent' => '1',
	                        'ctime' => time(),
	                );
	            }
	            
	            $old_id = $old_ids;
	            $old_ids = array();
	             
	            //二级题组类型（信息提取方式）
	            foreach ($parent['children'] as $k_id=>&$child)
	            {
	                //获取老的汇总数据
	                $old_result = $this->db->select('id, paper_id, group_type_id')
	                ->get_where('summary_paper_group_type',array('paper_id'=>$paper_id,'group_type_id'=>$k_id))
	                ->result_array();
	                foreach ($old_result as $row)
	                {
	                    $k = $row['paper_id']."_".$row['group_type_id'];
	                    $old_ids[$k] = $row['id'];
	                }
	                unset($old_result);
	                
	                $k = "{$paper_id}_{$k_id}";
	                if (isset($old_ids[$k])) {
	                    $update_data[] = array(
	                            'id' 		=> $old_ids[$k],
	                            'q_amount'	=> $child['num'],
	                            'percent'  	=> $child['percent'],
	                            'ques_id' 	=> implode(',', $child['ques_id']),
	                            'is_parent' => '0',
	                    );
	                    unset($old_ids[$k]);
	                } else {
	                    $insert_data[] = array(
	                            'paper_id'		=> $paper_id,
	                            'group_type_id'	=> $k_id,
	                            'q_amount'		=> $child['num'],
	                            'percent'  		=> $child['percent'],
	                            'ques_id' 		=> implode(',', $child['ques_id']),
	                            'is_parent' 	=> '0',
	                            'ctime' 		=> time(),
	                    );
	                }
	                
	                $old_ids = array();
	            }
	            
	            $old_id = array_merge($old_id, $old_ids);
	            
	            if (count($insert_data)) {
	                $this->db->insert_batch('summary_paper_group_type', $insert_data);
	            }
	            
	            if (count($update_data)) {
	                $this->db->update_batch('summary_paper_group_type', $update_data, 'id');
	            }
	            
	            if (count($old_id)) {
	                $this->db->where_in('id', array_values($old_id))->delete('summary_paper_group_type');
	            }
	            
	            $insert_data = array();
	            $update_data = array();
	            $old_id = array();
	        }
	    }
	
	    unset($group_types);
	}
	
	//===================================关联试题===================================================//
	
	/**
	 * 关联 试题
	 * @param number $exam_pid 考试期次ID
	 */
	public function summary_paper_question($exam_pid = 0)
	{
		$exam_pid = intval($exam_pid);
		if (!$exam_pid) {
			return false;
		}
		
		$insert_data = array();
		$update_data = array();
		
		$questions = array();
		
		$sql = "select eq.paper_id,eq.ques_id,q.type,e.subject_id,e.exam_id,eqs.test_score,rc.difficulty
				from {pre}exam_question eq
				left join {pre}exam e on eq.exam_id=e.exam_id
				left join {pre}relate_class rc on eq.ques_id=rc.ques_id and rc.grade_id=e.grade_id and rc.class_id=e.class_id
				left join {pre}question q on eq.ques_id=q.ques_id
				left join {pre}exam_question_score eqs on eqs.paper_id=eq.paper_id and eqs.ques_id=eq.ques_id
				where e.exam_pid={$exam_pid}
				";
		
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		//获取老的汇总数据
		$old_result = $this->db->query("select id, exam_id, paper_id, ques_id from {pre}summary_paper_question where exam_pid={$exam_pid}")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['exam_id']."_".$row['paper_id']."_".$row['ques_id'];
			$old_ids[$k] = $row['id'];
		}
		
		//提取试题列表
		$ques_ids = array();
		foreach ($result as $item)
		{
			$ques_ids[] = $item['ques_id'];
		}
		
		//获取题组部分
		$parent_ques = array();
		if (count($ques_ids)) 
		{
			$t_ques_id = implode(',', $ques_ids);
			$sql = "select count(parent_id) as `count`, parent_id
					from {pre}question
					where parent_id in($t_ques_id) AND is_delete = 0
					group by parent_id
					";
	
			$count_result = $this->db->query($sql)->result_array();
			foreach ($count_result as &$item) 
			{
				$parent_ques[$item['parent_id']] = $item['count'];
			}
		}
		
		foreach ($result as &$item)
		{
			$test_score = $item['test_score'];
			$difficulty = $item['difficulty'];
			if (is_null($test_score) || is_null($difficulty)) 
			{
				continue;
			}
			
			if (isset($parent_ques[$item['ques_id']]))
			{
			    $item['expect_score'] = (round(($parent_ques[$item['ques_id']]*$test_score))*$difficulty)/100;
			}
			else 
			{
				$item['expect_score'] = ($test_score*$difficulty)/100;
			}
				
			$k = $item['exam_id']."_".$item['paper_id']."_".$item['ques_id'];
			if (isset($old_ids[$k])) {
				unset($item['exam_id']);
				unset($item['subject_id']);
				unset($item['paper_id']);
				unset($item['ques_id']);
				unset($item['type']);
				unset($item['test_score']);
				unset($item['difficulty']);
					
				$item['id'] = $old_ids[$k];
				$item['mtime'] = time();
				
				$update_data[] = $item;
					
				unset($old_ids[$k]);
			} else {
				unset($item['type']);
				unset($item['test_score']);
				unset($item['difficulty']);
				
				$item['exam_pid'] = $exam_pid;
				$item['ctime'] = time();
				$item['mtime'] = time();
				
				$insert_data[] = $item;
			}
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('summary_paper_question', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('summary_paper_question', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_paper_question');
		}
	}
}
