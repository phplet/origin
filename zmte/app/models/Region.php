<?php 
/**
 * RegionModel
 * @file    Region.php
 * @author  BJP
 * @final   2015-06-16
 */
class RegionModel
{
    /**
     * 读取一个地区数据
     */
    public static function get_region($id = 0)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT * FROM rd_region WHERE region_id = ? LIMIT 1
EOT;
        $row = Fn::db()->fetchRow($sql, $id);
        return $row;
    }
    
    /**
     * 读取一个地区的下级地区
     */
    public static function get_regions($pid, 
        $show_all = FALSE, $region_type = NULL)
    {
        $sql = <<<EOT
SELECT * FROM rd_region WHERE parent_id = ?
EOT;
        $bind = array($pid);
        if ($show_all == FALSE)
        {
            $sql .= ' AND status = ?';
            $bind[] = 1;
        }
        if (isset($region_type))
        {
            $sql .= ' AND region_type = ?';
            $bind[] = $region_type;
        }
        $rows = Fn::db()->fetchAll($sql, $bind);
        return $rows;
    }
    
    /**
     * 读取从顶级地区到该地区的数据链
     */
    public static function get_region_parents($id)
    {
        $parents = array();
        while ($id && $row = self::get_region($id))
        {
            array_unshift($parents, $row);
            $id = $row['parent_id'];
        }
        return $parents;
    }
    
    /**
     * 把地区id（省，市，区）转化成地区名，用$sep间隔
     */
    public static function get_region_text($ids, $sep = ' ')
    {
        $ids_str = implode(',', $ids);
        $sql = <<<EOT
SELECT region_name FROM rd_region 
WHERE region_id IN ({$ids_str}) ORDER BY region_type
EOT;
        $text_arr = Fn::db()->fetchCol($sql);
        return implode($sep, $text_arr);
    }
    
    /**
     * 获取所有地区
     */
    public static function get_all_region($where = '', $bind = array())
    {
        $sql = <<<EOT
SELECT * FROM rd_region
EOT;
        if ($where)
        {
            $sql .= ' WHERE '. $where;
        }
        $rows = Fn::db()->fetchAll($sql, $bind);
    	return $rows;
    }
     
    /**
     * 根据父ID读取地区
     */
    public static function get_dft_son_regions($pid = 0)
    {
        if ($pid == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT * FROM rd_region WHERE parent_id = {$pid}
EOT;
        $rows = Fn::db()->fetchAll($sql);
    	return $rows;
    }
    
    /**
     * 根据地域深度读取地区
     */
    public static function get_regions_by_depth($region_type = 0)
    {
        $sql = <<<EOT
SELECT region_id FROM rd_region WHERE region_type = ?
EOT;
    	$rows = Fn::db()->fetchAll($sql, $region_type);
    	return $rows;
    }
}
