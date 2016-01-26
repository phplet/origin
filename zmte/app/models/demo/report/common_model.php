<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-公共方法
 * @author TCG
 * @final 2015-07-21
 */
class Common_model extends CI_Model 
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
	 * 获取 当前考生的 所在区域
	 * @param int $uid
	 * @return boolean
	 */
	public function get_student_info($uid)
	{
	    if (isset(self::$_data['student'][$uid]))
	    {
	    	return self::$_data['student'][$uid];
	    }
	    
	    self::$_data['student'] = array();
	    
		//获取该考生所在区域
		$sql = "SELECT sch.province, sch.city, sch.area, sch.school_id, school_name 
		        FROM rd_student s
		        LEFT JOIN rd_school sch ON s.school_id = sch.school_id
		        WHERE uid={$uid}";
		$student = self::$_db->fetchRow($sql);
	
		//获取该学生所在区域名称
		$region_ids = implode(',', array($student['province'], $student['city'], $student['area']));
		$sql = "SELECT region_id, region_name FROM rd_region 
		          WHERE region_id IN ($region_ids)";
		$query = self::$_db->query($sql);
		while ($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$student['region_'.$row['region_id']] = $row['region_name'];
		}
		
		self::$_data['student'][$uid] = $student;
	
		return $student;
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
	 * 学生考试的paper_id
	 */
	public function get_student_exam_paper($uid, $exam_id)
	{
	    if (empty(self::$_paper_ids[$uid][$exam_id]))
	    {
	        self::$_paper_ids = array();
	
	        $sql = "SELECT paper_id FROM rd_exam_test_paper
	               WHERE exam_id={$exam_id} AND uid={$uid} AND etp_flag=2";
	
	        self::$_paper_ids[$uid][$exam_id] = self::$_db->fetchOne($sql);
	    }
	
	    return self::$_paper_ids[$uid][$exam_id];
	}
}