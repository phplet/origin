<?php
/**
 * 题目类型模块ClassModel
 * @file    Class.php
 * @author  BJP
 * @final   2015-06-17
 */
class ClassModel
{
    /**
     * 按ID读取考试类型
     * @param   int     类型ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_question_class_by_id($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_question_class WHERE class_id = {$id}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }
    
    public static function get_class_by_id($id = 0, $item = '*')
    {
    	return self::get_question_class_by_id($id, $item);
    }
    
    /**
     * 获取当前登录用户可操作的年级列表
     */
    public static function get_allowed_class($q = 1)
    {
        $session = Fn::sess()->all_userdata();
        $is_super_user = $session['is_super'];
        if ($q == 1)
        {
            $choise = !$is_super_user && !self::is_action_type_all('question', 'r');
        }
        else
        {
            $choise = !$is_super_user && !self::is_action_type_all('question', 'w');
        }
        
        if ($choise)
        {
            $grades = array();
            foreach ($session['action'] as $power)
            {
        
                if ($power['q_type_id'] == -1)
                {
                    $grades = array();
                }
                foreach (explode(',', $power['q_type_id']) as $val)
                {
                    if ($val)
                    {
                        $grades[] = $val;
                    }
                }
            }
        }
        else
        {
            $grades = array();
        }
        return $grades;
    }
    
    /**
     * 按年级获取相应类型列表
     * @param   int     年级ID，0则取全部类型
     * @return  array   返回以类型ID为键值的数组
     */
    public static function get_class_list($grade_id = 0)
    {
        $grade_id = intval($grade_id);        
        $sql = 'SELECT * FROM rd_question_class';
        if ($grade_id)
        {
            $sql .= " WHERE start_grade <= $grade_id AND end_grade >= $grade_id";
        }
        $sql .= " ORDER BY class_id ASC";
        $list = array();
        $rows = Fn::db()->fetchAll($sql);
        foreach ($rows as $row)
        {
            $list[$row['class_id']] = $row;
        }
        return $list;
    }

    /**
     * 按年级区间读取列表
     *          
     * @param   int     开始年级ID
     * @param   int     结束年级ID
     * @return  array
     */
    public static function get_grade_area_class($start_grade = 0, 
        $end_grade = 0)
    {
        $start_grade = intval($start_grade);
        $end_grade   = intval($end_grade);
        
        $list = array();
        if ($start_grade && $end_grade)
        {
            $sql = <<<EOT
SELECT * FROM rd_question_class
WHERE start_grade <= {$end_grade} AND end_grade >= {$start_grade}
EOT;
            $list = Fn::db()->fetchAll($sql);
        }
        return $list;
    }

    /**
     * 获取每个年级的关联类型数组
     *          
     * @return  array
     */
    public static function all_grade_class()
    {
        $list = array();
        for ($i = 1; $i <= 12; $i++)
        {
            $list[$i] = self::get_class_list($i);
        }
        return $list;
    }

    /**
     * 获取每个年级段的关联类型数组
     *          
     * @return  array
     */
    public static function grade_period_class()
    {
        $list = array();
        $grades = array('1' => 6, '2' => 9, '3' => 12);
        for($i = 1; $i <= 3; $i++)
        {
            $list[$i] = self::get_class_list($grades[$i]);
        }
        return $list;
    }

    
    //判断权限范围 -- 查看所有
    public static function is_action_type_all($segment, $item, $subject_id = 0)
    {
        //return $this->get_action_type_segment($segment, $item) == '3';
        $action = Fn::sess()->userdata('action');
    
        $is_action_type_all = false;
        foreach ($action as $power)
        {
            $action_type = self::separate_action_type_1($power['action_type']);
    
            if ($subject_id > 0)
            {
                if ($power['subject_id'] == -1
                    || in_array($subject_id, explode(',', $power['subject_id'])))
                {
                    if (!empty($action_type[$segment][$item])
                        && $action_type[$segment][$item] == 3)
                    {
                        $is_action_type_all = true;
                        break;
                    }
                }
            }
            else
            {
                if (!empty($action_type[$segment][$item])
                    && $action_type[$segment][$item] == 3)
                {
                    $is_action_type_all = true;
                    break;
                }
            }
        }
        return $is_action_type_all;
    }
    
    //解析操作权限范围 property
    private static function separate_action_type_1($action_type)
    {
        $action_type = @unserialize($action_type);
        return is_array($action_type) ? $action_type : array();
    }
}
