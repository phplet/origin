<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-班级-考试信息对比
 * @author TCG
 * @final 2015-10-12
 */
class Class_comparison_info_model extends CI_Model 
{
    private static $_db;
    private static $_data = array();
    private static $_paper_ids = array();
    
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
		
		$this->load->model('cron/report/class_report/class_common_model');
	}
	
	/**
	 * 知识点 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_knowledge($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$rule_id || !$exam_id || !$schcls_id)
		{
			return array();
		}

		//本考试学科一级知识点
		$exam_name = $this->_get_exam_item($exam_id, 'exam_name');
		if ($exam_name == '')
		{
			return array();
		}
		
		//外部对比信息
		$comparison_data = $this->get_comparison_item_knowledge($rule_id, $exam_id);
		$exams = $comparison_data['exams'];
		$knowledges = $comparison_data['knowledge'];
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
        if (empty(self::$_data['knowledge'][$exam_id][$paper_id]))
        {
            //获取 本学科 知识点信息
            $sql = "SELECT DISTINCT(k.knowledge_name) AS knowledge_name, spk.percent
                    FROM rd_summary_paper_knowledge spk
                    LEFT JOIN rd_knowledge k on spk.knowledge_id=k.id
                    WHERE spk.paper_id = $paper_id AND spk.is_parent=1
                    ORDER BY spk.knowledge_id ASC
                    ";
            self::$_data['knowledge'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
        }
		
		$result = self::$_data['knowledge'][$exam_id][$paper_id];
		
		foreach($result as $item)
		{
			$knowledge_name = $item['knowledge_name'];
			$percent = $item['percent'];
			
			if ($knowledge_name == '')
			{
				continue;
			}
			
			//收集内部知识点
			$knowledges['internal'][] = $knowledge_name;
			
			$tmp_arr = array(
                $knowledge_name => $percent
			);
			
			foreach ($tmp_arr as $t_k => $t_v)
			{
				$exams[$exam_name][$t_k] = $t_v;
			}
		}
		
		//过滤重复知识点
		$knowledges['internal'] = array_values(array_unique($knowledges['internal']));
		$knowledges['external'] = array_values(array_unique($knowledges['external']));

		// 去除内部为零的知识点
		foreach ($knowledges['internal'] as $key => $value) 
		{

			$off = false;

			foreach ($exams as $index => $object) 
			{
				if (isset($object[$value]) && $object[$value] > 0)
				{
					$off = true;
				}
			}

			if (!$off)
			{
				foreach ($exams as $index => $object)
				{
					unset($exams[$index][$value]);
				}

				unset($knowledges['internal'][$key]);
			}
		}

		// 去除外部为零的知识点
		foreach ($knowledges['external'] as $key => $value) 
		{

			$off = false;

			foreach ($exams as $index => $object)
			{
				if (isset($object[$value]) && $object[$value] > 0)
				{
					$off = true;
				}
			}

			if (!$off)
			{
				foreach ($exams as $index => $object)
				{
					unset($exams[$index][$value]);
				}

				unset($knowledges['external'][$key]);
			}
		}
		
		return array('knowledge' => $knowledges, 'exams' => $exams);
	}
	
	/**
	 * 题组类型（信息提取方式） 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_group_type($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	
	    //本考试学科考试名称
	    $exam_name = $this->_get_exam_item($exam_id, 'exam_name');
	    if ($exam_name == '')
	    {
	        return array();
	    }
	
	    //外部对比信息
	    $comparison_data = $this->get_comparison_item_group_type($rule_id, $exam_id);
	    $exams = $comparison_data['exams'];
	    $group_types = $comparison_data['group_type'];
	    
	    $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
	    if (!$paper_id)
	    {
	        return array();
	    }
	
	    //获取 本学科 信息提取方式信息
	    if (empty(self::$_data['group_type'][$exam_id][$paper_id]))
	    {
	        $sql = "SELECT DISTINCT(gt.group_type_name) AS group_type_name, spgt.percent
            	    FROM rd_summary_paper_group_type spgt
            	    LEFT JOIN rd_group_type gt ON spgt.group_type_id=gt.id
            	    WHERE spgt.paper_id = $paper_id AND spgt.is_parent=1
            	    ORDER BY spgt.group_type_id ASC
            	    ";
	        
	        self::$_data['group_type'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
	    }
	    
	    $result = self::$_data['group_type'][$exam_id][$paper_id];
	    
	    foreach($result as $item)
	    {
	        $group_type_name = $item['group_type_name'];
	        $percent = $item['percent'];
	        	
	        if ($group_type_name == '')
	        {
	            continue;
	        }
	        	
	        //收集内部信息提取方式
	        $group_types['internal'][] = $group_type_name;
	        	
	        $tmp_arr = array(
	                $group_type_name => $percent
	        );
	        foreach ($tmp_arr as $t_k => $t_v)
	        {
	            $exams[$exam_name][$t_k] = $t_v;
	        }
	    }
	
	    //过滤重复信息提取方式
	    $group_types['internal'] = array_values(array_unique($group_types['internal']));
	    //$group_types['external'] = array_values(array_unique($group_types['external']));
	
	    return array('group_type' => $group_types, 'exams' => $exams);
	}
	
	/**
	 * 方法策略 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_method_tactic($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$rule_id || !$exam_id || !$schcls_id)
		{
			return array();
		}

		//本考试学科一级知识点
		$exam_name = $this->_get_exam_item($exam_id, 'exam_name');
		if ($exam_name == '')
		{
			return array();
		}
		
		//外部对比信息
		$comparison_data = $this->get_comparison_item_method_tactic($rule_id, $exam_id);
		$exams = $comparison_data['exams'];
		$method_tactics = $comparison_data['method_tactic'];
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
		//获取 本学科 方法策略信息
		if (empty(self::$_data['method_tactic'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(mt.name) AS name, spmt.percent
        		    FROM rd_summary_paper_method_tactic spmt
        		    LEFT JOIN rd_method_tactic mt ON spmt.method_tactic_id=mt.id
        		    WHERE spmt.paper_id = $paper_id
        		    ";
		    self::$_data['method_tactic'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
		}
		
		$result = self::$_data['method_tactic'][$exam_id][$paper_id];
		
		foreach($result as $item)
		{
			$mt_name = $item['name'];
			$percent = $item['percent'];

			if ($mt_name == '' || !$percent)
			{
				continue;
			}
			
			//收集内部知识点
			$method_tactics['internal'][] = $mt_name;
			
			$tmp_arr = array(
				$mt_name => $percent
			);
			
			foreach ($tmp_arr as $t_k => $t_v)
			{
				$exams[$exam_name][$t_k] = $t_v;
			}
		}
		
		//过滤重复方法策略
		$method_tactics['internal'] = array_values(array_unique($method_tactics['internal']));
		$method_tactics['external'] = array_values(array_unique($method_tactics['external']));
		
		return array('method_tactic' => $method_tactics, 'exams' => $exams);
	}
	
	/**
	 * 难易度和题型 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_difficulty($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);

		if (!$rule_id || !$exam_id || !$schcls_id) {
			return array();
		}

		//本考试学科一级知识点
		$exam_name = $this->_get_exam_item($exam_id, 'exam_name');
		if ($exam_name == '')
		{
			return array();
		}
		
		//外部对比信息
		$comparison_data = $this->get_comparison_item_difficulty($rule_id, $exam_id);
		$exams = $comparison_data['exams'];
		$difficulties = $comparison_data['difficulty'];
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
		    return array();
		}
		
		$subject_id = $this->_get_exam_item($exam_id, 'subject_id');
		
		//获取 本学科 题型难易度 信息
		if (empty(self::$_data['difficulty'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(spd.q_type) AS q_type, spd.low_percent,
        		    spd.mid_percent, spd.high_percent, spd.low_q_amount, 
        		    spd.mid_q_amount, spd.high_q_amount
        		    FROM rd_summary_paper_difficulty spd
        		    WHERE spd.paper_id = $paper_id
        		    ";
		    self::$_data['difficulty'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
		}
		
		$result = self::$_data['difficulty'][$exam_id][$paper_id];

		$q_types = C('q_type');

		foreach($result as $item)
		{
		    if ($item['q_type'] === '')
		    {
		        continue;
		    }
		    
		    if ($subject_id != 3 && in_array($item['q_type'],array(4,5,6,7,8,9,12,13)))
		    {
		        continue;
		    }

		    $q_type_name = $q_types[$item['q_type']];
			$low_percent = $item['low_percent'];
			$mid_percent = $item['mid_percent'];
			$high_percent = $item['high_percent'];
			$question_amount = $item['low_q_amount'] + $item['mid_q_amount'] + $item['high_q_amount'];
			
			$tmp_arr = array(
							$q_type_name . '：高(%)' => $high_percent,
							$q_type_name . '：中(%)' => $mid_percent,
							$q_type_name . '：低(%)' => $low_percent,
							$q_type_name . '（题数）' => $question_amount,
						);

			//收集内部 题型难易度
			$difficulties['internal'][] = $q_type_name . '：高(%)';
        	$difficulties['internal'][] = $q_type_name . '：中(%)';
        	$difficulties['internal'][] = $q_type_name . '：低(%)';
        	$difficulties['internal'][] = $q_type_name . '（题数）';

			foreach ($tmp_arr as $t_k=>$t_v)
			{
				$exams[$exam_name][$t_k] = $t_v;
			}
		}

		//过滤重复 题型难易度
		$difficulties['internal'] = array_values(array_unique($difficulties['internal']));
		$difficulties['external'] = array_values(array_unique($difficulties['external']));

		//=======去除题目数量为零题型的对比======
		foreach ($difficulties['internal'] as $k => $v) 
		{
			$off = false;

			if (strstr($v, '题数'))
			{
				foreach ($exams as $object) 
				{
					if (isset($object[$v]) && $object[$v] > 0)
					{
						$off = true;
					}
				}

				if (!$off)
				{
					//内部
					unset($difficulties['internal'][$k-3]);
					unset($difficulties['internal'][$k-2]);
					unset($difficulties['internal'][$k-1]);
					unset($difficulties['internal'][$k]);
				}
			}
		}

		foreach ($difficulties['external'] as $k => $v) 
		{
			$off = false;

			if (strstr($v, '题数'))
			{
				foreach ($exams as $object) 
				{
					if (isset($object[$v]) && $object[$v] > 0)
					{
						$off = true;
					}
				}

				if (!$off)
				{
					//内部
					unset($difficulties['external'][$k-3]);
					unset($difficulties['external'][$k-2]);
					unset($difficulties['external'][$k-1]);
					unset($difficulties['external'][$k]);
				}
			}
		}
		
		return array('difficulty' => $difficulties, 'exams' => $exams);
	}
	
	/**
	 * 考试期次说明
	 * @param number $exam_pid 考试期次
	 */
	public function module_exam_info($exam_pid = 0, $subject_id = 0)
	{
	    $exam_pid = intval($exam_pid);
		if (!$exam_pid)
		{
			return '';
		}
		
		if (isset(self::$_data['exam_introduce'][$exam_pid][$subject_id]))
		{
			return self::$_data['exam_introduce'][$exam_pid][$subject_id];
		}
		
		if ($subject_id > 0)
		{
		    $introduce = self::$_db->fetchOne("SELECT introduce FROM rd_exam 
		                  WHERE exam_pid=$exam_pid AND subject_id = $subject_id");
		}
		else 
		{
		    $introduce = self::$_db->fetchOne("SELECT introduce FROM rd_exam
		            WHERE exam_id=$exam_pid");
		}
		
		self::$_data['exam_info'][$exam_pid][$subject_id] = $introduce;
		
		return $introduce; 
	}
	
	
	//=========================对比信息=================================//
	/**
	 * 获取 本期考试名称
	 * @param number $exam_id 考试ID
	 */
	private function _get_exam_item($exam_id = 0, $item = 'exam_name')
	{
		$exam_id = intval($exam_id);
		if (!$exam_id)
		{
			return '';
		}
		
		if (isset(self::$_data['exam'][$exam_id][$item]))
		{
			return self::$_data['exam'][$exam_id][$item];
		}
		
		$sql = "SELECT {$item} FROM rd_exam WHERE exam_id = {$exam_id}";
		self::$_data['exam'][$exam_id][$item] = self::$_db->fetchOne($sql);
		
		return self::$_data['exam'][$exam_id][$item];
	}
	
	/**
	 * 获取 评估规则的 相关信息
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科ID
	 * @param number $cmp_info_id 对比信息ID
	 * @param string $item 获取字段
	 * @return multitype:|Ambigous <multitype:, unknown>
	 */
	private function _get_rule_item($rule_id = 0, $exam_id = 0, $item = 'comparison_info')
	{
		$rule_id = intval($rule_id);
		if (!$rule_id)
		{
			return array();
		}
		
		if (isset(self::$_data['rule'][$rule_id][$exam_id][$item]))
		{
			return self::$_data['rule'][$rule_id][$exam_id][$item];
		}		    
		
		$sql = "SELECT {$item} FROM rd_evaluate_rule WHERE id={$rule_id}";
		$item_value = self::$_db->fetchOne($sql);
		
		if ($item == 'comparison_info') 
		{
			$comparison_info = unserialize($item_value);
			$comparison_info = is_array($comparison_info) ? $comparison_info : array();
			
			if (!$comparison_info)
			{
				return array();
			} 
			
			$exam_id = intval($exam_id);
			$sql = "SELECT subject_id FROM rd_exam WHERE exam_id={$exam_id}";
			$subject_id = self::$_db->fetchOne($sql);
			if (!$subject_id)
			{
				return array();
			}
			
			$sql = "SELECT cmp_type_id FROM rd_comparison_type 
			        WHERE cmp_type_id IN (". implode(',', array_keys($comparison_info)) .") 
			        AND subject_id={$subject_id}";
			$result = self::$_db->fetchCol($sql);
			
			//过滤出该学科对应的对比信息
			$data = array();
			foreach ($result as $cmp_type_id)
			{
				$data[$cmp_type_id] = $comparison_info[$cmp_type_id];
			}
			
			self::$_data['rule'][$rule_id][$exam_id][$item] = $data;
			
			return $data;
		}
		else 
		{
		    self::$_data['rule'][$rule_id][$exam_id][$item] = $item_value;
		    
			return $item_value;
		}
	}
	
	/**
	 * 获取对比项--知识点模块(一级知识点 && 外部知识点)
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 */
	public function get_comparison_item_knowledge($rule_id = 0, $exam_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		if (!$rule_id || !$exam_id) 
		{
			return array();
		}
		
		if (isset(self::$_data['comparison_knowledge'][$rule_id][$exam_id]))
		{
			return self::$_data['comparison_knowledge'][$rule_id][$exam_id];
		}
		
		//知识点(内部知识点 + 外部知识点)
		$knowledges = array('internal' => array(), 'external' => array());
		
		//考试信息
		$exams = array();
		$data = array();
		
		//外部对比 一级知识点
		$comparison_info = $this->_get_rule_item($rule_id, $exam_id, 'comparison_info');
		if ($comparison_info)
		{
			$cmp_type_ids = implode(',', array_keys($comparison_info));
			$cmp_info_ids = array();
			foreach ($comparison_info as $cmp_type_id => $info_ids) 
			{
				$cmp_info_ids = array_merge($cmp_info_ids, array_values($info_ids));
			}
			
			$cmp_info_ids = implode(',', array_unique($cmp_info_ids));
			
			/*
			 * 获取对比信息类型 名称
			 */
			$sql = "SELECT cmp_type_id, cmp_type_name FROM rd_comparison_type 
			        WHERE cmp_type_id IN($cmp_type_ids)";
			$cmp_type_names = self::$_db->fetchPairs($sql);
			
			/*
			 * 获取对比信息 年份
			 */
			$sql = "SELECT cmp_info_id, cmp_info_year FROM rd_comparison_info 
			        WHERE cmp_info_id IN($cmp_info_ids)";
			$cmp_info_years = self::$_db->fetchPairs($sql);
			
			//获取对比信息 关联一级知识点
			$sql = "SELECT distinct(k.id) AS knowledge_id, k.knowledge_name 
					FROM rd_knowledge k
					LEFT JOIN rd_comparison_item ci ON k.id=ci.item_knowledge_id
					WHERE ci.cmp_info_id IN($cmp_info_ids)";
			$cmp_knowledges = self::$_db->fetchPairs($sql);
			
			foreach ($comparison_info as $cmp_type_id => $cmp_info_ids) 
			{
				$cmp_type_name = isset($cmp_type_names[$cmp_type_id]) ? $cmp_type_names[$cmp_type_id] : '';
				if ($cmp_type_name == '') 
				{
					continue;
				}
				
        		if ($cmp_info_ids)
        		{
        			$cmp_info_ids = implode(',', $cmp_info_ids);
        			
        			//内部知识点
	        		$sql = "SELECT cmp_info_id, item_knowledge_id, item_percent 
	        				FROM rd_comparison_item  
	        				WHERE cmp_info_id IN($cmp_info_ids)
	        				";
	        		$query = self::$_db->query($sql);
	        		
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
	        		{
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$item_knowledge_id = $row['item_knowledge_id'];
	        			$item_percent = $row['item_percent'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id]) 
	        			    || empty($cmp_knowledges[$item_knowledge_id])) 
	        			{
	        				continue;
	        			}
	        			
	        			//收集内部知识点
	        			$knowledge_name = $cmp_knowledges[$item_knowledge_id];
	        			$knowledges['internal'][] = $knowledge_name;
	        			
	        			$year = $cmp_info_years[$cmp_info_id];
	        			/* $exam_name = "{$year} {$cmp_type_name}"; */
	        			$exam_name = "{$cmp_type_name}";
	        			
	        			$tmp_arr = array($knowledge_name => $item_percent);
	        			foreach ($tmp_arr as $t_k => $t_v)
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        		}
	        		
	        		//新增知识点
	        		$sql = "SELECT cmp_info_id, external_knowledge_name, item_percent 
	        				FROM rd_comparison_item_external  
	        				WHERE cmp_info_id IN($cmp_info_ids)
	        				";
        			$query = self::$_db->query($sql);
        			
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
	        		{
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$external_knowledge_name = $row['external_knowledge_name'];
	        			$item_percent = $row['item_percent'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id])) 
	        			{
	        				continue;
	        			}
	        			
	        			//收集新增知识点
	        			$knowledges['external'][] = $external_knowledge_name;
	        			
	        			$year = $cmp_info_years[$cmp_info_id];
	        			
	        			$exam_name = "{$cmp_type_name}";
	        			$tmp_arr = array($external_knowledge_name => $item_percent);
	        			foreach ($tmp_arr as $t_k=>$t_v)
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        		}
        		}
        	}
		}

		//过滤重复知识点
		$knowledges['internal'] = array_values(array_unique($knowledges['internal']));
		$knowledges['external'] = array_values(array_unique($knowledges['external']));
		
		$data = array('knowledge' => $knowledges, 'exams' => $exams);
		
		self::$_data['comparison_knowledge'][$rule_id][$exam_id] = $data;
		
		return $data;
	}
	
	/**
	 * 获取对比项--题组类型（信息提取方式）模块(一级题组类型 && 外部题组类型)
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 */
	public function get_comparison_item_group_type($rule_id = 0, $exam_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    if (!$rule_id || !$exam_id)
	    {
	        return array();
	    }
	    
	    if (isset(self::$_data['comparison_group_type'][$rule_id][$exam_id]))
	    {
	    	return self::$_data['comparison_group_type'][$rule_id][$exam_id];
	    }
	
	    //题组类型（信息提取方式）(内部  + 外部)
	    $group_types = array('internal' => array(), 'external' => array());
	
	    //考试信息
	    $exams = array();
	    $data = array();
	
	    //外部对比 一级题组类型（信息提取方式）
	    $comparison_info = $this->_get_rule_item($rule_id, $exam_id, 'comparison_info');
	    if (count($comparison_info))
	    {
	        $cmp_type_ids = implode(',', array_keys($comparison_info));
	        $cmp_info_ids = array();
	        foreach ($comparison_info as $cmp_type_id => $info_ids)
	        {
	            $cmp_info_ids = array_merge($cmp_info_ids, array_values($info_ids));
	        }
	        	
	        $cmp_info_ids = implode(',', array_unique($cmp_info_ids));
	        	
	        /*
	         * 获取对比信息类型 名称
	        */
	        $sql = "SELECT cmp_type_id, cmp_type_name FROM rd_comparison_type 
	                WHERE cmp_type_id IN($cmp_type_ids)";
	        $cmp_type_names = self::$_db->fetchPairs($sql);
	        	
	        /*
	         * 获取对比信息 年份
	        */
	        $sql = "SELECT cmp_info_id, cmp_info_year FROM rd_comparison_info 
	                WHERE cmp_info_id IN($cmp_info_ids)";
	        $cmp_info_years = self::$_db->fetchPairs($sql);
	        	
	        //获取对比信息 关联一级题组类型（信息提取方式）
	        $sql = "SELECT distinct(gt.id) AS group_type_id, gt.group_type_name
        	        FROM rd_group_type gt
        	        LEFT JOIN rd_comparison_item2 ci2 ON gt.id=ci2.item_group_type_id
        	        WHERE ci2.cmp_info_id IN($cmp_info_ids)";
	        $cmp_group_types = self::$_db->fetchPairs($sql);
	        
	        foreach ($comparison_info as $cmp_type_id=>$cmp_info_ids)
	        {
    	        $cmp_type_name = isset($cmp_type_names[$cmp_type_id]) ? $cmp_type_names[$cmp_type_id] : '';
                if ($cmp_type_name == '')
                {
                    continue;
                }
    
                if (count($cmp_info_ids))
                {
                    $cmp_info_ids = implode(',', $cmp_info_ids);
                     
                    //内部题组类型（信息提取方式）
                    $sql = "SELECT cmp_info_id, item_group_type_id, item_percent
                            FROM rd_comparison_item2
                            WHERE cmp_info_id IN($cmp_info_ids)
                            ";
                    $query = self::$_db->query($sql);
        			
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
                    {
                        $cmp_info_id = $row['cmp_info_id'];
                        $item_group_type_id = $row['item_group_type_id'];
                        $item_percent = $row['item_percent'];
                        if (empty($cmp_info_years[$cmp_info_id]) 
                            || empty($cmp_group_types[$item_group_type_id]))
                        {
                            continue;
                        }
            
                        //收集内部题组类型（信息提取方式）
                        $group_type_name = $cmp_group_types[$item_group_type_id];
                        $group_types['internal'][] = $group_type_name;
            
                        $year = $cmp_info_years[$cmp_info_id];
                        $exam_name = "{$cmp_type_name}";
            
                        $tmp_arr = array($group_type_name => $item_percent);
                        foreach ($tmp_arr as $t_k => $t_v)
                        {
            	           $exams[$exam_name][$t_k] = $t_v;
            	        }
                    }
                }
    	    }
	    }
	    
        //过滤重复题组类型（信息提取方式）
        $group_types['internal'] = array_values(array_unique($group_types['internal']));
        //$group_types['external'] = array_values(array_unique($group_types['external']));

        $data = array('group_type' => $group_types, 'exams' => $exams);
        self::$_data['comparison_group_type'][$rule_id][$exam_id] = $data;
        
        return $data;
    }
	    
    /**
     * 获取对比项--知识点和题组类型（信息提取方式）模块(一级题组类型 && 外部题组类型)
     * @param number $rule_id 评估规则ID
     * @param number $exam_id 考试学科
     */
    public function get_comparison_item_extraction_ratio($rule_id = 0, $exam_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        if (!$rule_id || !$exam_id)
        {
            return array();
        }
        
        if (isset(self::$_data['comparison_extraction_ratio'][$rule_id][$exam_id]))
        {
            return self::$_data['comparison_extraction_ratio'][$rule_id][$exam_id];
        }
        
        $extraction_ratios = array('internal' => array());
        $extraction_ratio_name = array('知识点','信息提取方式');
        
        //考试信息
        $exams = array();
        $data = array();
        
        //外部对比
        $comparison_info = $this->_get_rule_item($rule_id, $exam_id, 'comparison_info');
        if ($comparison_info)
        {
            $cmp_type_ids = implode(',', array_keys($comparison_info));
            $cmp_info_ids = array();
            foreach ($comparison_info as $cmp_type_id => $info_ids)
            {
                $cmp_info_ids = array_merge($cmp_info_ids, array_values($info_ids));
            }
            	
            $cmp_info_ids = implode(',', array_unique($cmp_info_ids));
        
            /*
             * 获取对比信息类型 名称
            */
            $sql = "SELECT cmp_type_id, cmp_type_name FROM rd_comparison_type 
                    WHERE cmp_type_id IN($cmp_type_ids)";
            $cmp_type_names = self::$_db->fetchPairs($sql);
        
            /*
             * 获取对比信息 年份
            */
            $sql = "SELECT cmp_info_id,cmp_type_id,cmp_info_year,cmp_extraction_ratio 
                    FROM rd_comparison_info WHERE cmp_info_id IN($cmp_info_ids)";
            $cmp_info_years = self::$_db->fetchAssoc($sql);
        
            foreach ($comparison_info as $cmp_type_id => $cmp_info_ids)
            {
               $cmp_type_name = isset($cmp_type_names[$cmp_type_id]) ? $cmp_type_names[$cmp_type_id] : '';
               if ($cmp_type_name == '')
               {
                   continue;
               }
        
               foreach ($cmp_info_ids as $cmp_info_id)
               {
                   if (isset($cmp_info_years[$cmp_info_id]))
                   {
                       $cmp_info = $cmp_info_years[$cmp_info_id];
                       
                       $cmp_extraction_ratio = json_decode($cmp_info['cmp_extraction_ratio'], true);
                       if ($cmp_extraction_ratio)
                       {
                           foreach ($cmp_extraction_ratio as $key => $val)
                           {
                               $_name = $extraction_ratio_name[$key-1];
                               $extraction_ratios['internal'][] = $extraction_ratio_name[$key-1];
                               
                               $exam_name = "{$cmp_info['cmp_info_year']} {$cmp_type_name}";
                               
                               $tmp_arr = array($_name => $val);
                               foreach ($tmp_arr as $t_k => $t_v)
                               {
                                   $exams[$exam_name][$t_k] = $t_v;
                               }
                           }
                       }
                   }
               }
           }
        }
        
        //过滤重复题组类型（信息提取方式）
        $extraction_ratios['internal'] = array_values(array_unique($extraction_ratios['internal']));
        $data = array('extraction_ratio' => $extraction_ratios, 'exams' => $exams);
        self::$_data['comparison_extraction_ratio'][$rule_id][$exam_id] = $data;
        
        return $data;
    }
	
	/**
	 * 获取对比项--方法策略 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 */
	public function get_comparison_item_method_tactic($rule_id = 0, $exam_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		if (!$rule_id || !$exam_id) 
		{
			return array();		
		}
		
		if (isset(self::$_data['comparison_method_tactic'][$rule_id][$exam_id]))
		{
			return self::$_data['comparison_method_tactic'][$rule_id][$exam_id];
		}
		
		//知识点(内部知识点 + 外部知识点)
		$method_tactics = array('internal' => array(), 'external' => array());
		
		//考试信息
		$exams = array();
		$data = array();
		
		//外部对比 方法策略
		$comparison_info = $this->_get_rule_item($rule_id, $exam_id, 'comparison_info');
		if ($comparison_info)
		{
			$cmp_type_ids = implode(',', array_keys($comparison_info));
			$cmp_info_ids = array();
			foreach ($comparison_info as $cmp_type_id => $info_ids) 
			{
				$cmp_info_ids = array_merge($cmp_info_ids, array_values($info_ids));
			}
			
			$cmp_info_ids = implode(',', array_unique($cmp_info_ids));
			
			/*
			 * 获取对比信息类型 名称
			 */
			$sql = "SELECT cmp_type_id, cmp_type_name FROM rd_comparison_type
			         WHERE cmp_type_id IN($cmp_type_ids)";
			$cmp_type_names = self::$_db->fetchPairs($sql);
			
			/*
			 * 获取对比信息 年份
			*/
			$sql = "SELECT cmp_info_id, cmp_info_year FROM rd_comparison_info 
			        WHERE cmp_info_id in($cmp_info_ids)";
			$cmp_info_years = self::$_db->fetchPairs($sql);
			
			//获取对比信息 关联方法策略
			$sql = "SELECT distinct(mt.id) AS id, mt.name 
					FROM rd_method_tactic mt
					LEFT JOIN rd_comparison_item_method_tactic cimt ON mt.id=cimt.method_tactic_id
					WHERE cimt.cmp_info_id in($cmp_info_ids)";
			$cmp_method_tactics = self::$_db->fetchPairs($sql);
			
			foreach ($comparison_info as $cmp_type_id => $cmp_info_ids) 
			{
				$cmp_type_name = isset($cmp_type_names[$cmp_type_id]) ? $cmp_type_names[$cmp_type_id] : '';
				if ($cmp_type_name == '') 
				{
					continue;
				}
				
        		if ($cmp_info_ids)
        		{
        			$cmp_info_ids = implode(',', $cmp_info_ids);
        			
        			//内部方法策略
	        		$sql = "SELECT cmp_info_id, method_tactic_id, percent 
	        				FROM rd_comparison_item_method_tactic  
	        				WHERE cmp_info_id IN($cmp_info_ids)
	        				";
	        		$query = self::$_db->query($sql);
	        		
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
	        		{
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$method_tactic_id = $row['method_tactic_id'];
	        			$item_percent = $row['percent'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id]) 
	        			    || empty($cmp_method_tactics[$method_tactic_id])
			                || !$item_percent) 
	        			{
	        				continue;
	        			}
	        			
	        			//收集内部知识点
	        			$mt_name = $cmp_method_tactics[$method_tactic_id];
	        			$method_tactics['internal'][] = $mt_name;
	        			
	        			$year = $cmp_info_years[$cmp_info_id];
	        			$exam_name = "{$cmp_type_name}";
	        			
	        			$tmp_arr = array($mt_name => $item_percent);
	        			foreach ($tmp_arr as $t_k => $t_v)
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        		}
	        		
	        		//新增 方法策略
	        		$sql = "SELECT cmp_info_id, name, percent 
	        				FROM rd_comparison_item_external_method_tactic  
	        				WHERE cmp_info_id IN($cmp_info_ids)
	        				";
        			$query = self::$_db->query($sql);
        			
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
	        		{
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$external_name = $row['name'];
	        			$item_percent = $row['percent'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id])) 
	        			{
	        				continue;
	        			}
	        			
	        			//收集新增知识点
	        			$method_tactics['external'][] = $external_name;
	        			
	        			$year = $cmp_info_years[$cmp_info_id];
	        			
	        			$exam_name = "{$cmp_type_name}";
	        			$tmp_arr = array($external_name => $item_percent);
	        			foreach ($tmp_arr as $t_k => $t_v)
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        		}
        		}
        	}
		}

		//过滤重复知识点
		$method_tactics['internal'] = array_values(array_unique($method_tactics['internal']));
		$method_tactics['external'] = array_values(array_unique($method_tactics['external']));
		
		$data = array('method_tactic' => $method_tactics, 'exams' => $exams);
		self::$_data['comparison_method_tactic'][$rule_id][$exam_id] = $data;
		
		return $data;
	}
	
	/**
	 * 获取对比项--题型难易度 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 */
	public function get_comparison_item_difficulty($rule_id = 0, $exam_id = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		if (!$rule_id || !$exam_id) 
		{
			return array();		
		}
		
		if (isset(self::$_data['comparison_difficulty'][$rule_id][$exam_id]))
		{
		    return self::$_data['comparison_difficulty'][$rule_id][$exam_id];
		}
		
		//题型难易度
		$difficulties = array('internal' => array(), 'external' => array());
		
		//考试信息
		$exams = array();
		$data = array();
		
		//外部对比 方法策略
		$comparison_info = $this->_get_rule_item($rule_id, $exam_id, 'comparison_info');
		if ($comparison_info)
		{
			$cmp_type_ids = implode(',', array_keys($comparison_info));
			$cmp_info_ids = array();
			foreach ($comparison_info as $cmp_type_id => $info_ids) 
			{
				$cmp_info_ids = array_merge($cmp_info_ids, array_values($info_ids));
			}
			
			$cmp_info_ids = implode(',', array_unique($cmp_info_ids));
			
			/*
			 * 获取对比信息类型 名称
			 */
			$sql = "SELECT cmp_type_id, cmp_type_name FROM rd_comparison_type 
			        WHERE cmp_type_id IN($cmp_type_ids)";
			$cmp_type_names = self::$_db->fetchPairs($sql);
			
			/*
			 * 获取对比信息 年份
			*/
			$sql = "SELECT cmp_info_id, cmp_info_year FROM rd_comparison_info 
			        WHERE cmp_info_id IN($cmp_info_ids)";
			$cmp_info_years = self::$_db->fetchPairs($sql);
			
	        //考试学科
			$subject_id = $this->_get_exam_item($exam_id, 'subject_id');
			
			foreach ($comparison_info as $cmp_type_id=>$cmp_info_ids) 
			{
				$cmp_type_name = isset($cmp_type_names[$cmp_type_id]) ? $cmp_type_names[$cmp_type_id] : '';
				if ($cmp_type_name == '') 
				{
					continue;
				}
				
        		if (count($cmp_info_ids))
        		{
        			$cmp_info_ids = implode(',', $cmp_info_ids);
        			
        			//内部题型难易度
	        		$sql = "SELECT cmp_info_id, q_type, difficulty_percent, question_amount
	        				FROM {pre}comparison_item_difficulty  
	        				WHERE cmp_info_id IN($cmp_info_ids) ";
	        		
	        		$result = $this->db->query($sql)->result_array();

	        		/* 试题排序 */
        		    if ($subject_id == 3) {
			            $sort_types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
			        } else {
			            $sort_types = array('1','2','3','0','10','14','15','11');
			        }

			        $sort_result = array();

			        foreach ($sort_types as $types) {
			        	foreach ($result as $row) {
			        		if ($row['q_type'] == $types) {
			        			$sort_result[$types] = $row;
			        		}
			        	}
			        }

	        		foreach ($sort_result as $row) {

	        		    if ($row['q_type'] === '') {
	        		        continue;
	        		    }
	        		    
	        		    if ($subject_id != 3 && in_array($row['q_type'],array(4,5,6,7,8))) {
	        		        continue;
	        		    }
	        		    
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$q_type = $row['q_type'];
	        			$item_percent = $row['difficulty_percent'];
	        			$question_amount = $row['question_amount'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id])) 
	        			{
	        				continue;
	        			}
	        			
	        			@list($low_percent, $mid_percent, $high_percent) = @explode(',', $item_percent);
	        			$low_percent = is_null($low_percent) ? 0 : $low_percent;
	        			$mid_percent = is_null($mid_percent) ? 0 : $mid_percent;
	        			$high_percent = is_null($high_percent) ? 0 : $high_percent;
	        			
	        			//收集内部题型难易度
	        			$year = $cmp_info_years[$cmp_info_id];
	        			$exam_name = "{$cmp_type_name}";
	        			$q_type_name = C('q_type/'.$q_type);
	        			
	        			$tmp_arr = array(
	        					$q_type_name . '：高(%)' => $high_percent,
	        					$q_type_name . '：中(%)' => $mid_percent,
	        					$q_type_name . '：低(%)' => $low_percent,
	        					$q_type_name . '（题数）' => $question_amount,
	        			);
	        			
	        			foreach ($tmp_arr as $t_k => $t_v) 
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        			
	        			$difficulties['internal'][] = $q_type_name . '：高(%)';
	        			$difficulties['internal'][] = $q_type_name . '：中(%)';
	        			$difficulties['internal'][] = $q_type_name . '：低(%)';
	        			$difficulties['internal'][] = $q_type_name . '（题数）';
	        		}
	        		
	        		//新增 方法策略
	        		$sql = "SELECT cmp_info_id, name, question_amount 
	        				FROM rd_comparison_item_external_difficulty  
	        				WHERE cmp_info_id IN($cmp_info_ids)
	        				";
	        		
        			$query = self::$_db->query($sql);
	        		
	        		while ($row = $query->fetch(PDO::FETCH_ASSOC))
	        		{
	        			$cmp_info_id = $row['cmp_info_id'];
	        			$external_name = $row['name'];
	        			$question_amount = $row['question_amount'];
	        			
	        			if (empty($cmp_info_years[$cmp_info_id])) 
	        			{
	        				continue;
	        			}
	        			
	        			//收集新增题型
	        			$difficulties['external'][] = $external_name . '（题数）';
	        			
	        			$year = $cmp_info_years[$cmp_info_id];
	        			
	        			$exam_name = "{$cmp_type_name}";
	        			$tmp_arr = array($external_name . '（题数）' => $question_amount);
	        			foreach ($tmp_arr as $t_k => $t_v)
	        			{
	        				$exams[$exam_name][$t_k] = $t_v;
	        			}
	        		}
        		}
        	}
		}

		//过滤重复题型难易度
		$difficulties['internal'] = array_values(array_unique($difficulties['internal']));
		$difficulties['external'] = array_values(array_unique($difficulties['external']));
		
		$data = array('difficulty' => $difficulties, 'exams' => $exams);
		self::$_data['comparison_difficulty'][$rule_id][$exam_id] = $data;
		
		return $data;
	}
}