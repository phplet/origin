<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-班级-三维模块
 * @author TCG
 * @final 2015-10-12
 */
class Class_three_dimensional_model extends CI_Model 
{
    private static $_db;
    private static $_data;
    private static $_paper_ids;
    private static $_exam_test_scores;
    
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
		
		$this->load->model('cron/report/class_report/class_common_model');
	}
	
	/**
	 * 知识点 模块
	 * @param  number  $rule_id        评估规则ID
	 * @param  number  $exam_id        考试学科
	 * @param  number  $schcls_id      班级ID
	 * @param  array   $knowledge_ids  限定知识点
	 */
	public function module_knowledge($rule_id = 0, $exam_id = 0, $schcls_id = 0, $knowledge_ids = array())
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$rule_id || !$exam_id || !$schcls_id)
		{
			return array();
		}
		
		//列字段
		$fields = array('班级平均得分率(%)','年级平均得分率(%)','期望得分率(%)');
		
		//数据
		$data = array();
		
		//排序
		$field_sort = array();
		
		//flash 数据
		$flash_data = array('field' => array(), 'data' => array());
		
		//对比等级(总体)
		if (!$knowledge_ids)
		{
    		$comparison_levels = $this->class_common_model->get_rule_comparison_level($rule_id);
    		if (!$comparison_levels) 
    		{
    			return array();		
    		}
    		sort($comparison_levels);
		}
		
		//获取该班级所在区域
		$class = $this->class_common_model->get_class_info($schcls_id);
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
		//获取 本学科 知识点信息
		if (empty(self::$_data['knowledge'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(k.id) AS knowledge_id, k.knowledge_name, spk.ques_id, spk.paper_id
        		    FROM rd_summary_paper_knowledge spk
        		    LEFT JOIN rd_knowledge k ON spk.knowledge_id=k.id
        		    WHERE spk.paper_id={$paper_id} AND spk.is_parent=1
        		    ORDER BY spk.knowledge_id ASC
        		    ";
		    
		    self::$_data['knowledge'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
		}
		
		//获取各区域知识点得分率
	    if (empty(self::$_data['knowledge_percent'][$exam_id][$paper_id]))
	    {
	        $sql = "SELECT CONCAT(knowledge_id,'_',region_id,'_',is_school,'_',is_class),
    		        (test_score/total_score) AS percent
    		        FROM rd_summary_region_knowledge
    		        WHERE exam_id={$exam_id} AND paper_id={$paper_id} AND is_parent = 1
    		        ";
	        self::$_data['knowledge_percent'][$exam_id][$paper_id] = self::$_db->fetchPairs($sql);
	    }
	    
	    $knowledge_percent = self::$_data['knowledge_percent'][$exam_id][$paper_id];
		
		$result = self::$_data['knowledge'][$exam_id][$paper_id];
		
		foreach($result as $item)
		{
		    if ($knowledge_ids 
		        && !in_array($item['knowledge_id'], $knowledge_ids))
		    {
		    	continue;
		    }
		    
			//该知识点关联试题
			$ques_id = $item['ques_id'];
			$knowledge_id = $item['knowledge_id'];
			$knowledge_name = $item['knowledge_name'];
			$paper_id = $item['paper_id'];

			if ($knowledge_name == '' 
			    || !$ques_id)
			{
				continue;
			}
			
			//获取该知识点总分
			$total_score = 0;
			if (empty(self::$_data['knowledge_scores'][$exam_id][$paper_id][$knowledge_id]))
			{
			    $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                GROUP BY etp_id
        			    ";
			    $total_score = self::$_db->fetchOne($sql);
			    self::$_data['knowledge_scores'][$exam_id][$paper_id][$knowledge_id] = $total_score;
			}
			else 
			{
			    $total_score = self::$_data['knowledge_scores'][$exam_id][$paper_id][$knowledge_id];
			}
			
			//获取该知识点期望得分
			$expect_score = 0;
			if ($total_score > 0) 
			{
			    if (empty(self::$_data['knowledge_expect_scores'][$exam_id][$paper_id][$knowledge_id]))
    			{
    			    $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
    			            LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
    			            LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id 
    			                 AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
    			            WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
    			            GROUP BY etp_id";
    			    $expect_score = self::$_db->fetchOne($sql); 
    			    self::$_data['knowledge_expect_scores'][$exam_id][$paper_id][$knowledge_id] = $expect_score;
    			}
    			else
    			{
    			    $expect_score = self::$_data['knowledge_expect_scores'][$exam_id][$paper_id][$knowledge_id];
    			}
		    }
		    
			//获取班级该知识点得分率
			$region_k = $knowledge_id .'_' . $schcls_id . '_0_1';
            $percent = round($knowledge_percent[$region_k] * 100);
			$data[$knowledge_name][] = $percent;
			
			//年级知识点得分率
			$region_k = $knowledge_id .'_' . $class['school_id'] . '_1_0';
			$data[$knowledge_name][] = round($knowledge_percent[$region_k] * 100);
			
			//获取知识点期望得分率
			$expect_percent = $total_score > 0 
			     ? round(($expect_score/$total_score)*100) : 0;
			$data[$knowledge_name][] = $expect_percent;
			
			$field_sort[$knowledge_name] = $percent - $expect_percent;
			
			//如果是对比报告，不需要计算区域对比信息
			if ($knowledge_ids)
			{
			    continue;
			}
			
			//总体得分
			foreach ($comparison_levels as $comparison_level)
			{
				$cl_name = '';//总体名称
				$region_id = 1;
				$is_school = 0;
				$is_class = 0;
				
				switch ($comparison_level)
				{
				    case '-1'://所有考试人员
				        $cl_name = '所有考试人员';
				        break;
					case '0'://国家
						$cl_name = '全国';
						break;
					case '1'://省份
						$region_id = $class['province'];
						$cl_name = $class['region_'.$class['province']];
						break;
					case '2'://市
					    $region_id = $class['city'];
						$cl_name = $class['region_'.$class['city']];
						break;
					case '3'://县区
					    $region_id = $class['area'];
						$cl_name = $class['region_'.$class['area']];
						break;
					case '100'://学校
					    $region_id = $class['school_id'];
						$cl_name = $class['school_name'];
						$is_school = 1;
						break;
					default:
						break;
				}
				
				//总体得分率
				$region_k = $knowledge_id .'_' . $region_id . '_' . $is_school . '_' . $is_class;
				$overall_percent = round($knowledge_percent[$region_k] * 100);
				
				//班级的得分率 - 总体得分率
				$k = "本班-{$cl_name}平均";
				$flash_data['field'][] = $k;
				$tmp_percent = $percent - $overall_percent;
				$flash_data['data'][$knowledge_name][$k] = $tmp_percent;
			}
		}
		
		$flash_data['field'] = array_values(array_unique($flash_data['field']));
		
		$tmp_data = $data;
		$tmp_data2 = $flash_data;
		if (count($field_sort) > 1)
		{
		    arsort($field_sort);
		    $tmp_data = array();
		    foreach ($field_sort as $k_name => $val)
		    {
		        $tmp_data[$k_name] = $data[$k_name];
		    }
		    
	        $tmp_data2 = array();
	        $tmp_data2['field'] = $flash_data['field'];
	        foreach ($field_sort as $k_name => $val)
	        {
	            $tmp_data2['data'][$k_name] = $flash_data['data'][$k_name];
	        }
		}
		
		$return_data = array(
		    'fields' 		=> array_values(array_unique($fields)),
		    'data' 			=> $tmp_data,
		    'flash_data'	=> $tmp_data2,
		);
		
		return $return_data;
	}
	
	/**
	 * 方法策略 模块
 	 * @param  number  $rule_id       评估规则ID
	 * @param  number  $exam_id       考试学科
	 * @param  number  $schcls_id           考生ID
	 * @param  array   $method_tactic_ids 限定方法策略
	 */
	public function module_method_tactic($rule_id = 0, $exam_id = 0, $schcls_id = 0, $method_tactic_ids = array())
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$rule_id || !$exam_id || !$schcls_id)
		{
			return array();
		}
		
		//列字段
		$fields = array('班级平均得分率(%)','年级平均得分率(%)','期望得分率(%)');
		
		//数据
		$data = array();
		
		//排序
		$field_sort = array();
		
		//flash 数据
		$flash_data = array('field' => array(), 'data' => array());
		
		//对比等级(总体)
		if (!$method_tactic_ids)
		{
		    $comparison_levels = $this->class_common_model->get_rule_comparison_level($rule_id);
		    if (!$comparison_levels)
		    {
		        return array();
		    }
		    sort($comparison_levels);
		}
		
		//获取该班级所在区域
		$class = $this->class_common_model->get_class_info($schcls_id);
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
		//获取 本学科 方法策略信息
		if (empty(self::$_data['method_tactic'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(mt.id) AS id, mt.name, spmt.ques_id
        		    FROM rd_summary_paper_method_tactic spmt
        		    LEFT JOIN rd_method_tactic mt ON spmt.method_tactic_id=mt.id
        		    WHERE spmt.paper_id = $paper_id
        		    ORDER BY mt.id ASC
        		    ";
		    
		    self::$_data['method_tactic'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
		}
		
		//获取各区域方法策略得分率
	    if (empty(self::$_data['mt_percent'][$exam_id][$paper_id]))
	    {
	        $sql = "SELECT CONCAT(method_tactic_id,'_',region_id,'_',is_school,'_',is_class),
    		        (test_score/total_score) AS percent
    		        FROM rd_summary_region_method_tactic
    		        WHERE exam_id={$exam_id} AND paper_id={$paper_id}
    		        ";
	    
	        self::$_data['mt_percent'][$exam_id][$paper_id] = self::$_db->fetchPairs($sql);
	    }
	    
	    $mt_percent = self::$_data['mt_percent'][$exam_id][$paper_id];
		
		$result = self::$_data['method_tactic'][$exam_id][$paper_id];

		foreach($result as $item)
		{
			if (!$item['id']) continue;
			
			if ($method_tactic_ids && !in_array($item['id'] ,$method_tactic_ids))
			{
				continue;
			}
			
			//该方法策略关联试题
			$ques_id = $item['ques_id'];
			$mt_name = $item['name'];
			$method_tactic_id = $item['id'];
			
			if (!$ques_id)
			{
			    continue;
			}
			
			//获取该方法策略总分
			$total_score = 0;
		    if (empty(self::$_data['mt_scores'][$exam_id][$paper_id][$method_tactic_id]))
		    {
		        $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                GROUP BY etp_id";
		        
		        $total_score = self::$_db->fetchOne($sql);
		        self::$_data['mt_scores'][$exam_id][$paper_id][$method_tactic_id] = $total_score;
		    }
		    else
		    {
		        $total_score = self::$_data['mt_scores'][$exam_id][$paper_id][$method_tactic_id];
		    }
		    
			//获取该方法策略 班级平均得分率 年级平均得分 期望得分
			$expect_score = 0;
			if ($total_score > 0)
			{
			    $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
			            LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
			            LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id 
			                 AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
			            WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
			            GROUP BY etp_id";
			    $expect_score = self::$_db->fetchOne($sql);
			}
			
			$region_k = $method_tactic_id . '_' . $schcls_id . '_0_1';
			$percent = round($mt_percent[$region_k] * 100);
			$data[$mt_name][] = $percent;
			
			//获取该方法策略 年级平均得分率
			$region_k = $method_tactic_id . '_' . $class['school_id'] . '_1_0';
			$data[$mt_name][] = round($mt_percent[$region_k] * 100);
			
			$expect_percent = $total_score > 0
			     ? round(($expect_score/$total_score)*100) : 0;
			$expect_percent = $expect_percent > 100 ? 100 : $expect_percent;
			$data[$mt_name][] = $expect_percent;
			
			$field_sort[$mt_name] = $percent - $expect_percent;
			
			if ($method_tactic_ids)
			{
			    continue;
			}
			
			foreach ($comparison_levels as $comparison_level)
			{
				$cl_name = '';//总体名称
				$region_id = 1;
				$is_school = 0;
				$is_class = 0;
				
				switch ($comparison_level)
				{
				    case '-1'://所有考试人员
				        $cl_name = '所有考试人员';
				        break;
					case '0'://国家
						$cl_name = '全国';
						break;
					case '1'://省份
						$region_id = $class['province'];
						$cl_name = $class['region_'.$class['province']];
						break;
					case '2'://市
						$region_id = $class['city'];
						$cl_name = $class['region_'.$class['city']];
						break;
					case '3'://县区
						$region_id = $class['area'];
						$cl_name = $class['region_'.$class['area']];
						break;
					case '100'://学校
						$region_id = $class['school_id'];
						$cl_name = $class['school_name'];
						$is_school = 1;
						break;
					default:
						break;
				}
				
				//总体得分率
				$region_k = $method_tactic_id . '_' . $region_id . '_' . $is_school . '_' . $is_class;
				$overall_percent = round($mt_percent[$region_k] * 100);
				
				//本班的得分率 - 总体得分率
				$k = "本班-{$cl_name}平均";
				$flash_data['field'][] = $k;
				$tmp_percent = $percent - $overall_percent;
				$flash_data['data'][$mt_name][$k] = $tmp_percent;
			}
		}
		
		$flash_data['field'] = array_values(array_unique($flash_data['field']));
		
		$tmp_data = $data;
		$tmp_data2 = $flash_data;
		if (count($field_sort) > 1)
		{
		    arsort($field_sort);
		    $tmp_data = array();
		    foreach ($field_sort as $mt_name => $val)
		    {
		        $tmp_data[$mt_name] = $data[$mt_name];
		    }
		    
		    $tmp_data2 = array();
		    $tmp_data2['field'] = $flash_data['field'];
		    foreach ($field_sort as $mt_name => $val)
		    {
		        $tmp_data2['data'][$mt_name] = $flash_data['data'][$mt_name];
		    }
		}
		
		$all_data = array(
					'fields' 		=> array_values(array_unique($fields)), 
					'data' 			=> $tmp_data,
					'flash_data'	=> $tmp_data2,
		);
		
		return $all_data;
	}
	
	/**
	 * 难易度和题型 模块
	 * @param  number   $rule_id 评估规则ID
	 * @param  number   $exam_id 考试学科
	 * @param  number   $schcls_id 考生ID
	 * @param  array    $qtypes  限定题型
	 */
	public function module_difficulty($rule_id = 0, $exam_id = 0, $schcls_id = 0, $qtypes = array())
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$rule_id || !$exam_id || !$schcls_id)
		{
			return array();
		}
		
		//列字段
		$fields = array('总分','班级平均得分','班级平均得分率(%)','全校平均得分率(%)','期望得分率(%)');
		
		//数据
		$data = array();
		
		//获取该班级所在区域
		$class = $this->class_common_model->get_class_info($schcls_id);
		
		//获取班级学生id
		$class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
		if (!$class_uids)
		{
		    return array();
		}
		$cls_uid_str = implode(',', $class_uids);
		$cls_uid_num = count($class_uids);
		
		//获取年级学生id
		$grade_uids = $this->class_common_model->get_grade_student_list($class['school_id'], $exam_id);
		if (!$grade_uids)
		{
		    return array();
		}
		$grd_uid_str = implode(',', $grade_uids);
		$grd_uid_num = count($grade_uids);
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
		$subject_id = $this->_get_exam_item($exam_id);
		
		//获取 本学科 题型难易度 信息
		if (empty(self::$_data['difficulty'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(q_type) AS q_type, low_ques_id,
        		    mid_ques_id, high_ques_id
        		    FROM rd_summary_paper_difficulty
        		    WHERE paper_id = $paper_id
        		    ";
		    
			self::$_data['difficulty'][$exam_id][$paper_id] = self::$_db->fetchAssoc($sql);
		}
		
		$result = self::$_data['difficulty'][$exam_id][$paper_id];

		$q_types = C('q_type');
		$d_types = array('low' => '低', 'mid' => '中', 'high' => '高');
		foreach($result as $item)
		{
		    if ($item['q_type'] === '')
		    {
		        continue;
		    }
		    
		    if ($subject_id != 3 
		        && in_array($item['q_type'], array(4,5,6,7,8,9,12,13)))
		    {
		        continue;
		    }
		    
		    if ($qtypes && !in_array($item['q_type'], $qtypes))
		    {
		        continue;
		    }
		    
			//该题型难易度关联试题
			$low_ques_id = $item['low_ques_id'];
			$mid_ques_id = $item['mid_ques_id'];
			$high_ques_id = $item['high_ques_id'];
			
			$q_type = trim($item['q_type']);
			
			$tmp_arr = array('low' => $low_ques_id, 'mid' => $mid_ques_id, 'high' => $high_ques_id);
			foreach ($tmp_arr as $key => $ques_id)
			{
				if ($ques_id) 
				{
				    //获取该题型难易度总分
				    $total_score = 0;
				    
    				if (empty(self::$_data['difficulty_scores'][$exam_id][$paper_id][$q_type][$key]))
    				{
    				    $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			                WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                        GROUP BY etp_id";
    				    
    				    $total_score = self::$_db->fetchOne($sql);
    				    self::$_data['difficulty_scores'][$exam_id][$paper_id][$q_type][$key] = $total_score;
    				}
    				else
    				{
    				    $total_score = self::$_data['difficulty_scores'][$exam_id][$paper_id][$q_type][$key];
    				}
    				
    				//获取该题型难易度班级平均得分、年级平均得分和期望平均得分
    				$cls_avg_score = 0;
    				$grd_avg_score = 0;
    				$expect_score = 0;
    				if ($total_score > 0)
    				{
    				    $sql = "SELECT SUM({$key}_test_score)
            				    FROM rd_summary_student_difficulty
            				    WHERE exam_id={$exam_id} AND paper_id={$paper_id}
            				    AND uid IN ({$cls_uid_str}) AND q_type={$q_type}";
    				    $cls_avg_score = self::$_db->fetchOne($sql);
    				    $cls_avg_score = $cls_avg_score / $cls_uid_num;
    				
    				    if ($class_uids == $grade_uids)
    				    {
    				        $grd_avg_score = $cls_avg_score;
    				    }
    				    else
    				    {
    				        $sql = "SELECT SUM({$key}_test_score)
            				        FROM rd_summary_student_difficulty
            				        WHERE exam_id={$exam_id} AND paper_id={$paper_id}
            				        AND uid IN ({$grd_uid_str}) AND q_type={$q_type}";
    				        $grd_avg_score = self::$_db->fetchOne($sql);
    				        $grd_avg_score = $grd_avg_score / $grd_uid_num;
    				    }
    				
    				    $sql = "SELECT SUM(expect_score) FROM rd_summary_paper_question
    				            WHERE paper_id = $paper_id AND ques_id IN ($ques_id)";
    				    $expect_score = self::$_db->fetchOne($sql);
    				}
    				
    				$data[$q_type][$d_types[$key]][] = round($total_score);
    				$data[$q_type][$d_types[$key]][] = round($cls_avg_score);
    				$data[$q_type][$d_types[$key]][] = $total_score > 0
    				    ? round($cls_avg_score / $total_score * 100) : 0;
    				$data[$q_type][$d_types[$key]][] = $total_score > 0
    				    ? round($grd_avg_score / $total_score * 100) : 0;
    				$data[$q_type][$d_types[$key]][] = $total_score > 0
    				    ? round($expect_score / $total_score * 100) : 0;
				}
				else 
				{
				    $data[$q_type][$d_types[$key]][] = '-';
				    $data[$q_type][$d_types[$key]][] = '-';
				    $data[$q_type][$d_types[$key]][] = '-';
				    $data[$q_type][$d_types[$key]][] = '-';
				    $data[$q_type][$d_types[$key]][] = '-';
				}
			}
		}
		
	    if ($subject_id != 3)
		{
		    $types = array('1','2','3','0','10','14','15','11');
		}
		else
		{
		    $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
		}
		
		$tmp_data = array();
		foreach ($types as $type)
		{
		    if (isset($data[$type]))
		    {
		        $q_type_name = trim($q_types[$type]);
		        $tmp_data[$q_type_name] = $data[$type];
		    }
		}
		
		$data = $tmp_data;
		
		return array(
					'fields' 	=> array_values(array_unique($fields)), 
					'data' 		=> $data,
		);
	}
	
	/**
	 * 信息提取方式 模块
	 * @param number $rule_id      评估规则ID
	 * @param number $exam_id      考试学科
	 * @param number $schcls_id          考生ID
	 * @param array  $group_type_ids 限制信息提取方式
	 */
	public function module_group_type($rule_id = 0, $exam_id = 0, $schcls_id = 0, $group_type_ids = array())
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	
	    //列字段
	    $fields = array('班级平均得分率(%)','年级平均得分率(%)','期望得分率(%)');
	
	    //数据
	    $data = array();
	    
	    //排序
	    $field_sort = array();
	
	    //flash 数据
	    $flash_data = array('field' => array(), 'data' => array());
	
	    //对比等级(总体)
	    if (!$group_type_ids)
	    {
	        $comparison_levels = $this->class_common_model->get_rule_comparison_level($rule_id);
	        if (!$comparison_levels)
	        {
	            return array();
	        }
	        sort($comparison_levels);
	    }
	
	    //获取该班级所在区域
		$class = $this->class_common_model->get_class_info($schcls_id);
	    
	    $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
	    if (!$paper_id)
	    {
	        return array();
	    }
	
	    //获取 本学科 信息提取方式信息
	    if (empty(self::$_data['group_type'][$exam_id][$paper_id]))
	    {
	        $sql = "SELECT DISTINCT(gt.id) AS gt_id, gt.group_type_name,
        	        spgt.ques_id, spgt.paper_id, spgt.is_parent
        	        FROM rd_summary_paper_group_type spgt
        	        LEFT JOIN rd_group_type gt ON spgt.group_type_id=gt.id
        	        WHERE spgt.paper_id={$paper_id}
        	        ORDER BY gt.id ASC
        	        ";
	        
	        self::$_data['group_type'][$exam_id][$paper_id] = self::$_db->fetchAll($sql);
	    }
	    
	    //获取各区域信息提取方式得分率
        if (empty(self::$_data['gt_percent'][$exam_id][$paper_id]))
        {
            $sql = "SELECT CONCAT(group_type_id,'_',region_id,'_',is_school,'_',is_class),
                    (test_score/total_score) AS percent
                    FROM rd_summary_region_group_type
                    WHERE exam_id={$exam_id} AND paper_id={$paper_id}
                    ";
        
            self::$_data['gt_percent'][$exam_id][$paper_id] = self::$_db->fetchPairs($sql);
        }
         
        $gt_percent = self::$_data['gt_percent'][$exam_id][$paper_id];
	    
	    $result = self::$_data['group_type'][$exam_id][$paper_id];
	    
	    foreach($result as $item)
	    {
	        if ($group_type_ids 
	            && !in_array($item['gt_id'] ,$group_type_ids))
	        {
	        	continue;
	        }
	        
	        if ($group_type_ids && !$item['is_parent'])
	        {
	            continue;
	        }
	        
	        //该信息提取方式关联试题
	        $ques_id = $item['ques_id'];
	        $gt_id = $item['gt_id'];
	        $gt_name = $item['group_type_name'];
	        $paper_id = $item['paper_id'];
	
	        if ($gt_name == '' || !$ques_id)
	        {
	            continue;
	        }
	        	
	        //获取该信息提取方式总分
	        $total_score = 0;
	        if (empty(self::$_data['gt_scores'][$exam_id][$paper_id][$gt_id]))
	        {
	            $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                GROUP BY etp_id
        	            ";
	            
	            $total_score = self::$_db->fetchOne($sql);
	            self::$_data['gt_scores'][$exam_id][$paper_id][$gt_id] = $total_score;
	        }
	        else 
	        {
	            $total_score = self::$_data['gt_scores'][$exam_id][$paper_id][$gt_id];
	        }
	        
    	    //获取该信息提取方式 班级平均得分率
    	    $region_k = $gt_id . '_' . $schcls_id . "_0_1";
    	    $percent = round($gt_percent[$region_k] * 100);
    	    
    	    if ($item['is_parent'])
    	    {
    	        //获取该信息提取方式期望得分
    	        $expect_score = 0;
    	        if ($total_score > 0)
    	        {
    	            $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
    			            LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
    			            LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id 
    			             AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
    			            WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
    			            GROUP BY etp_id";
    	            $expect_score = self::$_db->fetchOne($sql);
    	        }
    	        
    	        $data[$gt_name][] = $percent;
    	        
    	        $region_k = $gt_id . '_' . $class['school_id'] . "_1_0";
    	        $data[$gt_name][] = round($gt_percent[$region_k] * 100);
    	        
    	        $expect_percent = $total_score > 0 
    	           ? round(($expect_score/$total_score)*100) : 0;
    	        $data[$gt_name][] = $expect_percent;
    	        
    	        $field_sort[$gt_name] = $percent - $expect_percent;
    	    }
    	    else
    	    {
    	        //总体得分
    	        foreach ($comparison_levels as $comparison_level)
    	        {
    	            $cl_name = '';//总体名称
    	            $region_id = 1;
    	            $is_school = 0;
    	            $is_class = 0;
    	            
    	            switch ($comparison_level)
    	            {
    	                case '-1'://所有考试人员
    	                    $cl_name = '所有考试人员';
    	                    break;
    	            	case '0'://国家
    	            	    $cl_name = '全国';
    	            	    break;
    	            	case '1'://省份
    	            	    $region_id = $class['province'];
    	            	    $cl_name = $class['region_'.$class['province']];
    	            	    break;
    	            	case '2'://市
    	            	    $region_id = $class['city'];
    	            	    $cl_name = $class['region_'.$class['city']];
    	            	    break;
    	            	case '3'://县区
    	            	    $region_id = $class['area'];
    	            	    $cl_name = $class['region_'.$class['area']];
    	            	    break;
    	            	case '100'://学校
    	            	    $region_id = $class['school_id'];
    	            	    $cl_name = $class['school_name'];
    	            	    $is_school = 1;
    	            	    break;
    	            	default:
    	            	    break;
    	            }
    	            
    	            //总体得分率
    	            $region_k = $gt_id . "_" . $region_id . '_' . $is_school . '_' . $is_class;
    	            $overall_percent = round($gt_percent[$region_k] *100);
    	            $overall_percent > 100 && $overall_percent = 100;
    	             
    	            //班级的得分率 - 总体得分率
    	            $k = "本班-{$cl_name}平均";
    	            $flash_data['field'][] = $k;
    	            $tmp_percent = $percent - $overall_percent;
    	            $flash_data['data'][$gt_name][$k] = $tmp_percent;
    	        }
    	    }
	    }
	
	    $flash_data['field'] = array_values(array_unique($flash_data['field']));
	    
	    $tmp_data = $data;
	    if (count($field_sort) >　1)
	    {
	        arsort($field_sort);
	        $tmp_data = array();
	        foreach ($field_sort as $gt_name => $val)
	        {
	            $tmp_data[$gt_name] = $data[$gt_name];
	        }
	    }
	    
	    $all_data = array(
	        'fields' 		=> array_values(array_unique($fields)),
	        'data' 			=> $tmp_data,
	        'flash_data'	=> $flash_data,
	    );
	    
		return $all_data;
	}
	
	/**
	 * 获取 本期考试信息
	 * @param number $exam_id 考试ID
	 */
	private function _get_exam_item($exam_id = 0, $item = 'subject_id')
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
	 * 知识点掌握情况对比表
	 * @param  number  $rule_id    评估规则ID
	 * @param  number  $exam_id    考试学科
	 * @param  number  $schcls_id        考生ID
	 * @return array   $data
	 */
	public function module_contrast_knowledge($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $schcls_id = intval($schcls_id);
        if (!$rule_id || !$exam_id || !$schcls_id)
        {
            return array();
        }
        
        //对比考试id
        $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
        if (!$contrast_exam_id)
        {
        	return array();
        }
        
        $class = $this->class_common_model->get_class_info($schcls_id);
        
        $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
        if (!$class_uids)
        {
            return array();
        }
        $cls_uid_str = implode(',', $class_uids);
        
        //当前考试班级的试卷id
        $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
        //上次考试班级的试卷id
        $contrast_paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $contrast_exam_id);
        
        //当前考试知识点
        $sql = "SELECT ssk.knowledge_id, knowledge_name, ROUND(SUM(test_score) / COUNT(uid), 2) AS test_score, COUNT(uid)
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 1 AND exam_id = $exam_id AND uid IN ($cls_uid_str)
                GROUP BY ssk.knowledge_id, uid";
        $curr_knowledge_testscore = self::$_db->fetchAssoc($sql);
        if (!$curr_knowledge_testscore)
        {
            return array();
        }
        
        $curr_knowledge_id = array_keys($curr_knowledge_testscore);
        
        //对比考试知识点
        $sql = "SELECT knowledge_id, knowledge_name, ROUND(SUM(test_score) / COUNT(uid), 2) AS test_score 
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 1 AND exam_id = $contrast_exam_id AND uid IN ($cls_uid_str)
                AND knowledge_id IN (" . implode(',', $curr_knowledge_id) . ")
                GROUP BY ssk.knowledge_id, uid";
        $contrast_knowledge_testscore = self::$_db->fetchAssoc($sql);
        if (!$contrast_knowledge_testscore)
        {
        	return array();
        }
        
        $contrast_knowledge_count = count($contrast_knowledge_testscore);
        $table_knowledge_count = 8;
        if ($contrast_knowledge_count > $table_knowledge_count)
        {
            $table_knowledge_count = ceil($contrast_knowledge_count / 2);
        }
        
        $new_knowledge = array_diff($curr_knowledge_id, array_keys($contrast_knowledge_testscore));
        
        //知识点各个分数
        $knowledge_score = array();
        
        //排序
        $field_sort = array();
        $field_sort_percent = array();
        
        //知识点期望总分
        $tmp_data = array();
        foreach ($contrast_knowledge_testscore as $k_id => $item)
        {
            //本次考试得分
            $curr_testscore = $curr_knowledge_testscore[$k_id]['test_score'];
            //上次考试得分
            $contrast_testscore = $item['test_score'];
            
            //本次考试知识点期望得分
            if (empty(self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_knowledge
                        WHERE paper_id = $paper_id AND knowledge_id = $k_id";
                $ques_id = self::$_db->fetchOne($sql);
                
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
			            LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
			            LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id 
			             AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
			            WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
			            GROUP BY etp_id";
                $curr_totalscore = self::$_db->fetchOne($sql);
                self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id];
            }
            
            //上次考试知识点期望得分
            if (empty(self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_knowledge
                        WHERE paper_id = $contrast_paper_id AND knowledge_id = $k_id";
                $ques_id = self::$_db->fetchOne($sql);
                
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id
                        AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
                        WHERE etr.exam_id = $contrast_exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
                        GROUP BY etp_id";
                
                $contrast_totalscore = self::$_db->fetchOne($sql);
                self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id] = $contrast_totalscore;
            }
            else 
            {
                $contrast_totalscore = self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id];
            }
            
            $curr_percent = 0;
            if ($curr_totalscore)
            {
                 $curr_percent = round($curr_testscore / $curr_totalscore, 2);
                 $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $knowledge_score[$item['knowledge_name']][1] = $curr_percent;
            
            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                 $contrast_percent = round($contrast_testscore / $contrast_totalscore, 2);
                 $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
            }
            $knowledge_score[$item['knowledge_name']][2] = $contrast_percent;
            
            $field_sort[$item['knowledge_name']] = $curr_percent - $contrast_percent;
            if ($field_sort[$item['knowledge_name']] == 0)
            {
                $field_sort_percent[$item['knowledge_name']] = $curr_percent;
            }
        }
        
	   //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else
                {
                    $sort[$key] = $val;
                }
            }
        
            $field_sort = $sort;
        }
        
        $i = 1;
        $k = 0;
        $knowledge_scores = array();
        foreach ($field_sort as $k_name => $val)
        {
            if ($i > $table_knowledge_count)
            {
                $i = 1;
                $k = 1;
            }
            
            $knowledge_scores[$k][0][] = $k_name;
            $knowledge_scores[$k][1][] = $knowledge_score[$k_name][1];
            $knowledge_scores[$k][2][] = $knowledge_score[$k_name][2];
            
            $i++;
        }
        
        $data = array();
        
        if ($new_knowledge)
        {
        	$data['new_knowledge'] = $this->module_knowledge($rule_id, $exam_id, $schcls_id, $new_knowledge);
        }
        
        $data['contrast_knowledge'] = $knowledge_scores;

        return $data;
	}
	
	/**
	 * 难易度和题型 模块
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 考生ID
	 */
	public function module_contrast_difficulty($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    //获取该班级所在区域
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	    
	    //当前考试
	    $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score, 
	            ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score, 
	            ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
        	    FROM rd_summary_student_difficulty
        	    WHERE exam_id = $exam_id AND uid IN ($cls_uid_str)
	            GROUP BY q_type, uid";
	    $curr_qtype_testscore = self::$_db->fetchAssoc($sql);
	    if (!$curr_qtype_testscore)
	    {
	        return array();
	    }
	    
	    $curr_qtype = array_keys($curr_qtype_testscore);
	    
	    //对比考试
	    $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score, 
	            ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score, 
	            ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
        	    FROM rd_summary_student_difficulty
        	    WHERE exam_id = $contrast_exam_id AND uid IN ($cls_uid_str)
                GROUP BY q_type, uid";
	    $contrast_qtype_testscore = self::$_db->fetchAssoc($sql);
	    if (!$contrast_qtype_testscore)
	    {
            return array();
	    }
	    
	    $new_qtype = array_diff($curr_qtype, 
	        array_keys($contrast_qtype_testscore));
	    
		//数据
		$data = array();
		
		$paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
		if (!$paper_id)
		{
			return array();
		}
		
		//上次考试班级的试卷id
		$contrast_paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $contrast_exam_id);
		if (!$contrast_paper_id)
		{
		    return array();
		}
		
		$subject_id = $this->_get_exam_item($exam_id);
		
		//获取 本学科 题型难易度 信息
		if (empty(self::$_data['difficulty'][$exam_id][$paper_id]))
		{
		    $sql = "SELECT DISTINCT(q_type) AS q_type, low_ques_id,
        		    mid_ques_id, high_ques_id
        		    FROM rd_summary_paper_difficulty
        		    WHERE paper_id = $paper_id
        		    ";
		    
			self::$_data['difficulty'][$exam_id][$paper_id] = self::$_db->fetchAssoc($sql);
		}
		
		$curr_paper = self::$_data['difficulty'][$exam_id][$paper_id];
		
		//获取 上次考试 题型难易度 信息
		if (empty(self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id]))
		{
		    $sql = "SELECT DISTINCT(q_type) AS q_type, low_ques_id,
        		    mid_ques_id, high_ques_id
        		    FROM rd_summary_paper_difficulty
        		    WHERE paper_id = $contrast_paper_id
        		    ";
		
		    self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id] = self::$_db->fetchAssoc($sql);
		}
		
		$contrast_paper = self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id];
		
		$q_types = C('q_type');
		$d_types = array('low' => '低难度', 'mid' => '中难度', 'high' => '高难度');
		
	    if ($subject_id != 3)
		{
		    $types = array('1','2','3','0','10','14','15','11');
		}
		else
		{
		    $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
		}
		
		foreach ($types as $qtype)
		{
		    if (in_array($qtype, $new_qtype))
		    {
		        continue;
		    }
		    
		    if ($subject_id != 3
		        && in_array($qtype, array(4,5,6,7,8,9,12,13)))
		    {
		        continue;
		    }

    		$item = $curr_paper[$qtype];
    		$item2 = $contrast_paper[$qtype];
		    if (!$item || !$item2)
		    {
		        continue;
		    }
		    
			//当期考试 题型难易度关联试题
			$low_ques_id = $item['low_ques_id'];
			$mid_ques_id = $item['mid_ques_id'];
			$high_ques_id = $item['high_ques_id'];
			
			$row = array();
			$row[0][] = $q_types[$qtype];
			$row[1][] = '本班本次考试';
			
			$tmp_arr = array('low' => $low_ques_id, 'mid' => $mid_ques_id, 'high' => $high_ques_id);
			foreach ($tmp_arr as $key => $ques_id)
			{
			    $percent = '';
				if ($ques_id) 
				{
				    //获取该题型难易度总分
				    $total_score = 0;
				    
    				if (empty(self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key]))
    				{
    				    $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			                WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                        GROUP BY etp_id";
    				    
    				    $total_score = self::$_db->fetchOne($sql);
    				    self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key] = $total_score;
    				}
    				else
    				{
    				    $total_score = self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key];
    				}
    				
    				//获取该题型难易度 得分
    				$test_score = $curr_qtype_testscore[$qtype][$key . '_test_score'];
    				
    				//计算得分率(得分/总分)
    				$percent = $total_score > 0 ? round($test_score / $total_score * 100) : 0;
    				$percent = $percent > 100 ? 100 : $percent;
    				$percent = $percent < 0 ? 0 : $percent;
				}
				
				$row[0][] = $d_types[$key];
				$row[1][] = $percent;
			}
			
			//对比考试 
			
			//该题型难易度关联试题
			$low_ques_id = $item2['low_ques_id'];
			$mid_ques_id = $item2['mid_ques_id'];
			$high_ques_id = $item2['high_ques_id'];
			$tmp_arr = array('low' => $low_ques_id, 'mid' => $mid_ques_id, 'high' => $high_ques_id);
			
			$row[2][] = '本班上次考试';
			foreach ($tmp_arr as $key => $ques_id)
			{
			    //获取该题型难易度总分
			    $total_score = 0;
			    $percent = '';
			    if ($ques_id)
			    {
    				if (empty(self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key]))
    				{
    				    $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			                WHERE exam_id={$contrast_exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                        GROUP BY etp_id";

    				    $total_score = self::$_db->fetchOne($sql);
    				    self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key] = $total_score;
    				}
    				else
    				{
    				    $total_score = self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key];
    				}
    				
    				//获取该题型难易度 得分
    				$test_score = $contrast_qtype_testscore[$qtype][$key . '_test_score'];
    					
    				//计算得分率(得分/总分)
    				$percent = $total_score > 0 ? round($test_score / $total_score * 100) : 0;
    				$percent = $percent > 100 ? 100 : $percent;
    				$percent = $percent < 0 ? 0 : $percent;
			    }
			
			    $row[2][] = $percent;
			}
			
			$data['contrast_qtype'][] = $row;
		}
		
		if ($new_qtype)
		{
		    $data['new_qtype'] = $this->module_difficulty($rule_id, $exam_id, $schcls_id, $new_qtype);
		}
		
	    return $data;
	}
	
	/**
	 * 方法策略掌握情况对比表
	 * @param  number  $rule_id    评估规则ID
	 * @param  number  $exam_id    考试学科
	 * @param  number  $schcls_id        考生ID
	 * @return array   $data
	 */
	public function module_contrast_method_tactic($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	    
	    //当前考试班级的试卷id
	    $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
	    //上次考试班级的试卷id
	    $contrast_paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $contrast_exam_id);
	    
	    //当前考试方法策略
	    $sql = "SELECT ssmt.method_tactic_id, name, 
	            ROUND(SUM(test_score)/COUNT(uid), 2) AS test_score
        	    FROM rd_summary_student_method_tactic ssmt
        	    LEFT JOIN rd_method_tactic mt ON mt.id = ssmt.method_tactic_id
        	    WHERE exam_id = $exam_id AND uid IN ($cls_uid_str)
	            GROUP BY ssmt.method_tactic_id, uid";
	    $curr_mt_testscore = self::$_db->fetchAssoc($sql);
	    if (!$curr_mt_testscore)
	    {
	        return array();
	    }
	    
	    $curr_mt_id = array_keys($curr_mt_testscore);
	    
	    //对比考试方法策略
	    $sql = "SELECT ssmt.method_tactic_id, name, 
	            ROUND(SUM(test_score)/COUNT(uid), 2) AS test_score
        	    FROM rd_summary_student_method_tactic ssmt
        	    LEFT JOIN rd_method_tactic mt ON mt.id = ssmt.method_tactic_id
        	    WHERE exam_id = $contrast_exam_id AND uid IN ($cls_uid_str)
        	    AND ssmt.method_tactic_id IN (" . implode(',', $curr_mt_id) . ")
        	    GROUP BY ssmt.method_tactic_id, uid";
	    $contrast_mt_testscore = self::$_db->fetchAssoc($sql);
	    if (!$contrast_mt_testscore)
	    {
            return array();
	    }
	    
	    $contrast_mt_count = count($contrast_mt_testscore);
        $table_mt_count = 8;
        if ($contrast_mt_count > $table_mt_count)
        {
            $table_mt_count = ceil($contrast_mt_count / 2);
	    }
	    
	    $new_method_tactic = array_diff($curr_mt_id, array_keys($contrast_mt_testscore));
	    
	    //方法策略各个分数
	    $mt_score = array();
	    
	    //排序
	    $field_sort = array();
	    $field_sort_percent = array();
	    
        //方法策略期望总分
        $tmp_data = array();
        foreach ($contrast_mt_testscore as $mt_id => $item)
        {
	        //本次考试得分
	        $curr_testscore = $curr_mt_testscore[$mt_id]['test_score'];
	    
	        //上次考试得分
	        $contrast_testscore = $item['test_score'];
	    
	        //本次考试方法策略期望得分
	        if (empty(self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_method_tactic
        	            WHERE paper_id = $paper_id AND method_tactic_id = $mt_id";
                $ques_id = self::$_db->fetchOne($sql);
                
	            $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
			            LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
			            LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id 
			             AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
			            WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
			            GROUP BY etp_id";
	            $curr_totalscore = self::$_db->fetchOne($sql);
	            
	            self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id];
            }
    
            //上次考试方法策略期望得分
            if (empty(self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_method_tactic
                        WHERE paper_id = $contrast_paper_id AND method_tactic_id = $mt_id";
                $ques_id = self::$_db->fetchOne($sql);
                
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id
                        AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
                        WHERE etr.exam_id = $contrast_exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
                        GROUP BY etp_id";
                
                $contrast_totalscore = self::$_db->fetchOne($sql);
                
                self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id] = $contrast_totalscore;
            }
            else
            {
                $contrast_totalscore = self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id];
            }
    
            $curr_percent = 0;
            if ($curr_totalscore)
            {
                 $curr_percent = round($curr_testscore / $curr_totalscore * 100);
                 $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $mt_score[$item['name']][1] = $curr_percent;
    
            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                $contrast_percent = round($contrast_testscore / $contrast_totalscore * 100);
                $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
	        }
            $mt_score[$item['name']][2] = $contrast_percent;
            
            $field_sort[$item['name']] = $curr_percent - $contrast_percent;
            if ($field_sort[$item['name']] == 0)
            {
                $field_sort_percent[$item['name']] = $curr_percent;
            }
        }
        
        //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else 
                {
                    $sort[$key] = $val;
                }
            }
            
            $field_sort = $sort;
        }
        
        $i = 1;
        $k = 0;
        $mt_scores = array();
        foreach ($field_sort as $mt_name => $val)
        {
            if ($i > $table_mt_count)
            {
                $i = 1;
                $k = 1;
            }
        
            $mt_scores[$k][0][] = $mt_name;
            $mt_scores[$k][1][] = $mt_score[$mt_name][1];
            $mt_scores[$k][2][] = $mt_score[$mt_name][2];
            
            $i++;
        }
    
        $data = array();
        
        if ($new_method_tactic)
        {
            $data['new_method_tactic'] = $this->module_method_tactic($rule_id, $exam_id, $schcls_id, $new_method_tactic);
        }
    
        $data['contrast_method_tactic'] = $mt_scores;
        
        return $data;
	}
	
	/**
	 * 信息提取方式掌握情况对比表
	 * @param  number  $rule_id    评估规则ID
	 * @param  number  $exam_id    考试学科
	 * @param  number  $schcls_id        考生ID
	 * @return array   $data
	 */
	public function module_contrast_group_type($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	     
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    $class = $this->class_common_model->get_class_info($schcls_id);
	     
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	     
	    //当前考试班级的试卷id
	    $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
	    //上次考试班级的试卷id
	    $contrast_paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $contrast_exam_id);
	     
	    //当前考试信息提取方式
	    $sql = "SELECT ssgt.group_type_id, group_type_name, 
	            ROUND(SUM(test_score)/COUNT(uid), 2) AS test_score
        	    FROM rd_summary_student_group_type ssgt
        	    LEFT JOIN rd_group_type gt ON gt.id = ssgt.group_type_id
        	    WHERE ssgt.is_parent = 1 AND exam_id = $exam_id 
	            AND uid IN ($cls_uid_str)
	            GROUP BY ssgt.group_type_id, uid";
	    $curr_gt_testscore = self::$_db->fetchAssoc($sql);
	    if (!$curr_gt_testscore)
	    {
	        return array();
	    }
	     
	    $curr_gt_id = array_keys($curr_gt_testscore);
	     
	    //对比考试信息提取方式
	    $sql = "SELECT ssgt.group_type_id, group_type_name,
	            ROUND(SUM(test_score)/COUNT(uid), 2) AS test_score
        	    FROM rd_summary_student_group_type ssgt
        	    LEFT JOIN rd_group_type gt ON gt.id = ssgt.group_type_id
        	    WHERE ssgt.is_parent = 1 AND exam_id = $contrast_exam_id 
        	    AND uid IN ($cls_uid_str)
        	    AND ssgt.group_type_id IN (" . implode(',', $curr_gt_id) . ")
        	    GROUP BY ssgt.group_type_id, uid";
	    $contrast_gt_testscore = self::$_db->fetchAssoc($sql);
	    if (!$contrast_gt_testscore)
	    {
	        return array();
	    }
	         
        $contrast_gt_count = count($contrast_gt_testscore);
        $table_gt_count = 8;
        if ($contrast_gt_count > $table_gt_count)
        {
            $table_gt_count = ceil($contrast_gt_count / 2);
        }
	             
	    $new_group_type = array_diff($curr_gt_id, array_keys($contrast_gt_testscore));
	             
        //信息提取方式各个分数
        $gt_score = array();
        
        //排序
        $field_sort = array();
        $field_sort_percent = array();
         
        //信息提取方式期望总分
        $tmp_data = array();
        foreach ($contrast_gt_testscore as $gt_id => $item)
        {
            //本次考试得分
            $curr_testscore = $curr_gt_testscore[$gt_id]['test_score'];
            	
            //上次考试得分
            $contrast_testscore = $item['test_score'];
            	
            //本次考试信息提取方式期望得分
            if (empty(self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_group_type
    	                WHERE paper_id = $paper_id AND group_type_id = $gt_id";
                $ques_id = self::$_db->fetchOne($sql);
                
	            $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id
                        AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
                        WHERE etr.exam_id = $exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
                        GROUP BY etp_id";
                $curr_totalscore = self::$_db->fetchOne($sql);
                 
                self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id];
            }
	             
	            //上次考试信息提取方式期望得分
            if (empty(self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_group_type
	    	            WHERE paper_id = $contrast_paper_id AND group_type_id = $gt_id";
                $ques_id = self::$_db->fetchOne($sql);
                
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.grade_id = e.grade_id AND rc.class_id = e.class_id
                        AND rc.ques_id = etr.ques_id AND e.subject_type = rc.subject_type
                        WHERE etr.exam_id = $contrast_exam_id AND (etr.ques_id IN ($ques_id) OR etr.sub_ques_id IN ($ques_id))
                        GROUP BY etp_id";
                
                $contrast_totalscore = self::$_db->fetchOne($sql);
            
                self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id] = $contrast_totalscore;
            }
            else
            {
                $contrast_totalscore = self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id];
            }

            $gt_name = $item['group_type_name'];

            $curr_percent = 0;
            if ($curr_totalscore)
            {
                $curr_percent = round($curr_testscore / $curr_totalscore * 100);
                $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $gt_score[$gt_name][1] = $curr_percent;

            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                $contrast_percent = round($contrast_testscore / $contrast_totalscore * 100);
                $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
            }
            $gt_score[$gt_name][2] = $contrast_percent;
	    	  
            $field_sort[$gt_name] = $curr_percent - $contrast_percent;
            if ($field_sort[$gt_name] == 0)
            {
                $field_sort_percent[$gt_name] = $curr_percent;
            }
        }
        
        //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else
                {
                    $sort[$key] = $val;
                }
            }
        
            $field_sort = $sort;
        }
        
        $i = 1;
        $k = 0;
        $gt_scores = array();
        foreach ($field_sort as $gt_name => $val)
        {
            if ($i > $table_gt_count)
            {
                $i = 1;
                $k = 1;
            }
        
            $gt_scores[$k][0][] = $gt_name;
            $gt_scores[$k][1][] = $gt_score[$gt_name][1];
            $gt_scores[$k][2][] = $gt_score[$gt_name][2];
        
            $i++;
        }
	    
	    $data = array();
	    	                     
	    if ($new_group_type)
	    {
	    	$data['new_group_type'] = $this->module_group_type($rule_id, $exam_id, $schcls_id, $new_group_type);
	    }

	    $data['contrast_group_type'] = $gt_scores;
	    	                     
	    return $data;
	}
}