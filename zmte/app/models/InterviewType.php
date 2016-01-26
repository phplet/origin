<?php
/**
 * 面试题分类模块InterviewTypeModel
 * @file    InterviewType.php
 * @author  BJP
 * @final   2015-06-16
 */
class InterviewTypeModel
{ 
    /**
     * 按ID读取面试类型
     *          
     * @param   int     类型ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_type($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_interview_type WHERE type_id = {$id}
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

    /**
     * 按PID读取考试类型列表
     *          
     * @param   int     类型PID
     * @return  array
     */
    public static function get_children($pid = 0)
    {
        $pid = intval($pid);
        $sql = <<<EOT
SELECT * FROM rd_interview_type WHERE pid = {$pid} ORDER BY type_id ASC
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $list = array();
        foreach ($rows as $row)
        {
            $list[$row['type_id']] = $row;
        }
        return $list;
    }
    
    /**
     * 考试类型列表
     *          
     * @return  array
     */
    public static function get_type_list()
    {
        $sql = <<<EOT
SELECT * FROM rd_interview_type ORDER BY pid ASC
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $list = array();
        foreach ($rows as $row)
        {
            if ($row['pid'])
                $list[$row['pid']]['children'][$row['type_id']] = $row;
            else
                $list[$row['type_id']] = $row;
        }
        // 分级排序
        $result = array();
        foreach ($list as $row)
        {
            $result[$row['type_id']] = $row;
            if ( ! empty($row['children']))
            {
                $result = $result + $row['children'];
                unset($result[$row['type_id']]['children']);
            }
        }
        return $result;
    }
    
    /**
     * 更新配置文件缓存
     *          
     * @return  void
     */
    public static function update_cache()
    {
        $cache_file = APPPATH.'config/app/setting.php';
        $list = self::get_type_list();
        $file_content = file_get_contents($cache_file);

        $cache = "\r\n".'$config[\'interview_type\'] = ' . var_export($list, TRUE) . ";\r\n";

        $new_content = preg_replace('/(start:interview_type)(.*)(end:interview_type)/s', '\1'.$cache.'// \3', $file_content);
        file_put_contents($cache_file, $new_content);
    }

    // 学科列表，按key=val形式的数组
    public static function type_key_val()
    {
        static $list = NULL;
        if (isset($list))
        {
            return $list;
        }

        $list = array();
        $res = self::get_type_list();
        foreach ($res as $row)
        {
            $list[$row['type_id']] = $row['type_name'];
        }
        return $list;
    }
    
    // 更新配置文件缓存
    public static function _update_cache()
    {
        $cache_file = APPPATH.'config/app/setting.php';
        $list = self::type_key_val();
        $file_content = file_get_contents($cache_file);

        $cache = "\r\n".'$config[\'interview_type\'] = ' . var_export($list, TRUE) . ";\r\n";

        $new_content = preg_replace('/(start:interview_type)(.*)(end:interview_type)/s', '\1'.$cache.'// \3', $file_content);
        file_put_contents($cache_file, $new_content);
    }
}
