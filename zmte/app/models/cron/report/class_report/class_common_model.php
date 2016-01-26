<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-公共方法
 * @author TCG
 * @final 2015-07-21
 */
class Class_common_model extends CI_Model 
{
    private static $_db;
    private static $_data;
    private static $_paper_ids;
    
	public function __construct()
	{
		parent::__construct();
		
		self::$_db = Fn::db();
	}
	
	/**
	 * 将百分比进行 按 5等分，分级 100/5
	 */
	public function convert_percent_level($percent)
	{
		$level = '0';
		$percent = floatval($percent);
		if ($percent >= 0 && $percent <= 20)
		{
			$level = 1;
		} 
		elseif ($percent > 20 && $percent <= 40)
		{
			$level = 2;
		}
		elseif ($percent > 40 && $percent <= 60)
		{
			$level = 3;
		}
		elseif ($percent > 60 && $percent <= 80)
		{
			$level = 4;
		}
		elseif ($percent > 80)
		{
			$level = 5;
		}
		
		return $level;
	}
	
	/**
	 * 获取评估规则的 对比等级
	 * @param int $rule_id
	 * @return boolean
	 */
	public function get_rule_comparison_level($rule_id)
	{
		//获取该组题规则的对比等级
		
	    if (isset(self::$_data['comparison_level'][$rule_id]))
	    {
            return self::$_data['comparison_level'][$rule_id];
	    }
	    
		$sql = "SELECT comparison_level FROM rd_evaluate_rule WHERE id={$rule_id}";
		$comparison_level = explode(',', self::$_db->fetchOne($sql));
		if (!$comparison_level)
		{
			return array();
		}
		
		//将对比等级 从小到大 排
		$comparison_levels = array_reverse($comparison_level);
		self::$_data['comparison_level'][$rule_id] = $comparison_levels;
		
		return $comparison_levels;
	}
	
	/**
	 * 获取评估规则的 分布比例
	 * @param int $rule_id
	 * @return boolean
	 */
	public function get_rule_distribution_proportion($rule_id)
	{
	    $proportion = self::$_data['distribution_proportion'][$rule_id];
	    if (!$proportion)
	    {
	        $proportion = json_decode(EvaluateRuleModel::get_evaluate_rule($rule_id, 'distribution_proportion'), true);
	        if (!$proportion)
	        {
	            $proportion = array(
	                '高分段' => 27,
	                '中分段' => 73,
	                '低分段' => 100,
	            );
	        }
	    
	        self::$_data['distribution_proportion'][$rule_id] = $proportion;
	    }
	    
	    return $proportion;
	}
	
	/**
	 * 获取 当前班级的信息
	 * @param int $uid
	 * @return array
	 */
	public function get_class_info($schcls_id)
	{
	    if (isset(self::$_data['class'][$schcls_id]))
	    {
	    	return self::$_data['class'][$schcls_id];
	    }
	    
	    self::$_data['class'] = array();
	    
		//获取该班级所在区域
	    $sql = "SELECT sch.province, sch.city, sch.area, 
	           sch.school_id, sch.school_name, sc.schcls_id, sc.schcls_name
		        FROM t_school_class sc
		        LEFT JOIN rd_school sch ON sc.schcls_schid = sch.school_id
		        WHERE schcls_id = {$schcls_id}";
		$class = self::$_db->fetchRow($sql);
	
		//获取该学生所在区域名称
		$region_ids = implode(',', array($class['province'], $class['city'], $class['area']));
		$sql = "SELECT region_id, region_name FROM rd_region 
		          WHERE region_id IN ($region_ids)";
		$query = self::$_db->query($sql);
		while ($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$class['region_'.$row['region_id']] = $row['region_name'];
		}
		
		self::$_data['class'][$schcls_id] = $class;
	
		return $class;
	}
	
	/**
	 * 将试题难易度 转为 难，中，易 描述
	 * @param int $difficulty  难易度值
	 * @return string
	 */
	public function convert_question_difficulty($difficulty)
	{
		$output = '';
		$difficulty_area = C('difficulty_area');
		$difficulty_desc = array('高', '中', '低');
		foreach ($difficulty_area as $key=>$area) 
		{
		    if ($area[0] <= $difficulty && $difficulty <= $area[1])
			{
				$output = $difficulty_desc[$key];
			}
		}
		return $output;
	}
	
	/**
	 * 评估规则对比考试id
	 */
	public function contrast_exam_id($rule_id = 0, $exam_id = 0)
	{
	    $contrast_exam_id = 0;
	
	    //对比考试id
	    if (empty(self::$_data['contrast_exam_id'][$rule_id][$exam_id]))
	    {
	        $sql = "SELECT e.exam_id FROM rd_exam e
        	        LEFT JOIN rd_evaluate_rule er ON e.exam_pid = er.contrast_exam_pid
        	        LEFT JOIN rd_exam e2 ON e.subject_id = e2.subject_id
        	        WHERE er.id = $rule_id AND e2.exam_id = $exam_id";
	        $contrast_exam_id = self::$_db->fetchOne($sql);
	
	        self::$_data['contrast_exam_id'][$rule_id][$exam_id] = $contrast_exam_id;
	    }
	    else
	    {
	        $contrast_exam_id = self::$_data['contrast_exam_id'][$rule_id][$exam_id];
	    }
	
	    return $contrast_exam_id;
	}
	
	/**
	 * 班级考试的paper_id
	 */
	public function get_class_exam_paper($schcls_id, $exam_id)
	{
	    if (empty(self::$_paper_ids[$schcls_id][$exam_id]))
	    {
	        self::$_paper_ids = array();
	
	        $sql = "SELECT etp.paper_id FROM rd_exam_test_paper etp
	               LEFT JOIN rd_exam_place ep ON ep.exam_pid = etp.exam_pid
	               WHERE etp.exam_id={$exam_id} AND place_schclsid={$schcls_id} 
	               AND etp_flag=2";
	
	        self::$_paper_ids[$schcls_id][$exam_id] = self::$_db->fetchOne($sql);
	    }
	
	    return self::$_paper_ids[$schcls_id][$exam_id];
	}
	
	/**
	 * 获取考试班级学生
	 */
	public function get_class_student_list($schcls_id, $exam_id)
	{
	    if (!$schcls_id || !$exam_id)
	    {
	        return array();
	    }
	    
	    if (isset(self::$_data['class_student'][$exam_id][$schcls_id]))
	    {
	        return self::$_data['class_student'][$exam_id][$schcls_id];
	    }
	     
	    self::$_data['class_student'] = array();
	    
	    $sql = "SELECT eps.uid FROM rd_exam_place_student eps
        	    LEFT JOIN rd_student s ON s.uid = eps.uid
        	    LEFT JOIN rd_exam_place ep ON ep.place_id = eps.place_id
        	    LEFT JOIN rd_exam e ON e.exam_pid = ep.exam_pid
        	    WHERE e.exam_id = ? AND place_schclsid = ?
        	    AND s.uid > 0";
	    $class_student = self::$_db->fetchCol($sql, array($exam_id, $schcls_id));
	    
	    self::$_data['class_student'][$exam_id][$schcls_id] = $class_student;
	    
	    return $class_student;
	}
	
	/**
	 * 获取考试年级学生
	 */
	public function get_grade_student_list($sch_id, $exam_id)
	{
	    if (!$sch_id || !$exam_id)
	    {
	        return array();
	    }
	    
	    if (isset(self::$_data['grade_student'][$exam_id][$sch_id]))
	    {
	        return self::$_data['grade_student'][$exam_id][$sch_id];
	    }
	    
	    self::$_data['grade_student'] = array();
	     
	    $sql = "SELECT eps.uid FROM rd_exam_place_student eps
                LEFT JOIN rd_student s ON s.uid = eps.uid
                LEFT JOIN rd_exam_place ep ON ep.place_id = eps.place_id 
                LEFT JOIN rd_exam e ON e.exam_pid = ep.exam_pid 
                WHERE e.exam_id = ? AND s.school_id = ?";
	    $grade_student = self::$_db->fetchCol($sql, array($exam_id, $sch_id));
	    
	    self::$_data['grade_student'][$exam_id][$sch_id] = $grade_student;
	    
	    return $grade_student;
	}
	
	/**
	 * 获取 本期考试信息
	 * @param number $exam_id 考试ID
	 */
	public function get_exam_item($exam_id = 0, $item = 'subject_id')
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
	    $exam = self::$_db->fetchRow($sql);
	    
	    if (isset($exam[$item]))
        {
            self::$_data['exam'][$exam_id][$item] = $exam[$item];
            return $exam[$item];
        }
        else 
        {
            self::$_data['exam'][$exam_id][$item] = $exam;
            return $exam;
        }
	}
}