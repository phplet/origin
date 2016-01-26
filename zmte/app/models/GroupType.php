<?php
/**
 * 信息提取方式模块
 * GroupTypeModel
 * @file    GroupType.php
 * @author BJP
 * @final 2015-06-16
 */
class GroupTypeModel
{
    /**
     * 按ID读取信息提取方式
     *          
     * @param   int     信息提取方式ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_group_type($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }        
        
        static $result = array();
        
        $hash = $id. '-' . $item;
        if (isset($result[$hash]))
        {
            return $result[$hash];
        }

        $sql = <<<EOT
SELECT {$item} FROM rd_group_type WHERE id = {$id}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            $result[$hash] = $row[$item];
        }
        else
        {
            $result[$hash] = $row;
        }
        
        return $result[$hash];
    }

    /**
     * 按学科ID，父ID，读取信息提取方式列表
     *          
     * @param   int       学科ID
     * @param   int       上级ID
     * @return  array
     */
    public static function get_group_type_list($pid = 0, $subject_id = 0, 
            $relate_num = TRUE, $relate_child = false)
    {
        static $result = array();
        $hash = $subject_id . '-'. $pid;
        if (isset($result[$hash]))
        {
            return $result[$hash];
        }
        $list = array();
        $sql = <<<EOT
SELECT * FROM rd_group_type WHERE pid = {$pid}
EOT;
        if ($subject_id)
        {
            $sql .= " AND subject_id = $subject_id";
        }
        $rows = Fn::db()->fetchAll($sql);
        foreach($rows as $row)
        {
            $list[$row['id']] = $row;
        }
        
        if ($relate_num)
        {
            foreach($list as &$val)
            {
                $val = self::get_group_type_num($val);
            }
        }
        
        if ($relate_child)
        {
            foreach($list as &$val)
            {
                $val['childlist'] = array_values(
                    self::get_group_type_list($val['id'], 
                        $val['subject_id'], false));
            }
        }
        
        $result[$hash] = $list;
        return $result[$hash];
    }
    
    /**
     * 计算信息提取方式子级数量，返回信息提取方式信息数组
     *
     * @param   mixed      信息提取方式数组/信息提取方式id
     * @return  array
     */
    public static function get_group_type_num($group_type = array())
    {
        if (! is_array($group_type) && $group_type)
        {
            $group_type = self::get_group_type(intval($group_type));
        }
        if (empty($group_type))
        {
            return array();
        }
    
        $id = $group_type['id'];
        $pid = $group_type['pid'];
    
        // 如果是一级知识点，获取下级知识点id
        if ($pid == 0)
        {
            $ids = self::get_group_type_list($id);
            $ids = array_keys($ids);
            $group_type['children'] = count($ids);
        }
        return $group_type;
    }
}
