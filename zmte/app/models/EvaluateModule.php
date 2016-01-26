<?php
/**
 * 评估管理--评估模块管理--数据层 EvaluateModuleModel
 * @file    EvaluateModule.php
 * @author  BJP
 * @final   2015-07-03
 */
class EvaluateModuleModel
{
    /**
     * 获取某个模块分类及子模块信息
     */
    public static function get_evaluation_module_info($module_id = 0, 
        $module_type = 0)
    {
        $sql = <<<EOT
SELECT * FROM rd_evaluate_module
WHERE parent_moduleid = 0 AND module_type = {$module_type} AND status = 1
EOT;
        if ($module_id > 0)
        {
            $sql .= " AND module_id = $module_id";
        }

        $sql .= " ORDER BY module_sort ASC";

        $module = Fn::db()->fetchAll($sql);
        if ($module)
        {
            foreach ($module as &$item)
            {
                $sql = "SELECT * FROM rd_evaluate_module
                    WHERE parent_moduleid = " . $item['module_id'] . " AND status = 1
                    ORDER BY module_sort ASC";
                $item['children'] = Fn::db()->fetchAll($sql);
            }

            if ($module_id > 0)
            {
                $module = current($module);
            }
        }
        return $module ? $module : array();
    }

    /**
     * 插入模块数据$module_date
     * @param    array    $module_date    模块数据
     * @return   bool     成功返回true，否则返回false
     */
    public static function insert($module_date)
    {
        try
        {
            if (!$module_date['module_sort'])
            {
                $module_date['module_sort'] = 1;
            }

            if (empty($module_date['module_code']))
            {
                $module_date['module_code'] = "";
            }

            $module_date['date_create'] = time();
            $module_date['date_modify'] = time();
            !isset($module_date['status']) && $module_date['status'] = 1;
            $res = Fn::db()->insert('rd_evaluate_module', $module_date);
            if ($res)
            {
                return Fn::db()->lastInsertId('rd_evaluate_module', 'module_id');
            }
            else
            {
                log_message('error', 'mysql error:'.Fn::db()->errorInfo()[2]);
                throw New Exception(Fn::db()->errorInfo()[2]);
            }
        }
        catch (Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * 查询顶级节点模块数据
     * @param    int    $parent_moduleid    模块父节点
     * @return   array  返回满足条件的模块列表
     */
    public static function parent_module_list($parent_moduleid = 0, 
        $module_type = 0)
    {
        $sql = <<<EOT
SELECT * FROM rd_evaluate_module
WHERE parent_moduleid = {$parent_moduleid} AND module_type = {$module_type}
ORDER BY module_sort ASC
EOT;
        return Fn::db()->fetchAll($sql);
    }

    /**
     * 根据模块ID查询模块ID指定的一条模块数据
     * @param    int    $module_id    模块ID
     */
    public static function evaluate_module_info($id)
    {
        if (!isset($id))
        {
            return null;
        }
        $sql = "SELECT * FROM rd_evaluate_module WHERE module_id={$id}";
        return Fn::db()->fetchRow($sql);
    }
    
    /**
     * 验证节点是否已存在
     * @param    string    $module_name    模块名称
     * @param    string    $module_code    模块编码
     * @return   bool      成功返回true，否则返回false
     */
    public static function exist_module($module_name, $module_id, 
        $parent_moduleid = 0, $module_code, $module_type = 0)
    {
        if (!$module_name && !$module_code)
        {
            return false;
        }
        
        $tmp_sql = 'module_type = ' . $module_type;
        if ($module_name)
        {
            $tmp_sql .= " AND module_name='{$module_name}'";
        }

        if ($module_code !== '')
        {
            $tmp_sql .=  $tmp_sql ? " OR module_code = '{$module_code}'" : "module_code = '{$module_code}'";
        }

        $tmp_sql = $tmp_sql ? "({$tmp_sql})" : '';

        $sql = "SELECT module_id FROM rd_evaluate_module WHERE {$tmp_sql} AND module_id <> $module_id";

        if ($parent_moduleid)
        {
            $sql .= " AND (parent_moduleid = $parent_moduleid OR module_id = $parent_moduleid)";
        }
        
        return !empty(Fn::db()->fetchRow($sql));
    }

    public static function exist_module_1($module_name, $module_id, 
        $parent_moduleid = 0, $module_type = 0)
    {
        $sql = "SELECT module_id FROM rd_evaluate_module
                WHERE (module_name='{$module_name}' ) 
                AND module_id <> $module_id";

        if ($parent_moduleid)
        {
            $sql .= " AND (parent_moduleid = $parent_moduleid OR module_id = $parent_moduleid)";
        }
        
        $sql .= " AND module_type = $module_type";
        
        return !empty(Fn::db()->fetchRow($sql));
    }

    /**
     * 更新模块信息
     * array    $data      要更新的模块数据
     * mixed    $module_id 模块ID
     * return   boolean    成功返回true，否则返回false
     */
    public function update($date, $module_id)
    {
        $v = Fn::db()->update('rd_evaluate_module', $date, "module_id = " . intval($module_id));
        return $v > 0;
    }

    /**
     * 删除$module_id指定的模块数据
     * @param    int    $module_id
     * @return   bool   成功返回true，否则返回false
     */
    public static function delete($module_id)
    {
        try
        {
            $sql = "SELECT parent_moduleid FROM rd_evaluate_module WHERE module_id={$module_id}";
            $row = Fn::db()->fetchRow($sql);
            $parent_moduleid = $row['parent_moduleid'];

            if ($parent_moduleid == 0)
            {
                $sql1 = "SELECT count(*) AS cnt FROM rd_evaluate_module WHERE parent_moduleid={$module_id}";
                $res= Fn::db()->fetchRow($sql);
                $count = $res['cnt'];
            }

            if (isset($count) && $count != 0)
            {
                message('不能删除，该分类下还有模块');
                return 'stop';
            }
            else
            {
                $v = Fn::db()->delete('rd_evaluate_module', 'module_id = ' . $module_id);
                return $v > 0;
            }
        }
        catch (Exception $e)
        {
            return false;
        }
    }
}
