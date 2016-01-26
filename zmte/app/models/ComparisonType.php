<?php
/**
 * 外部对比信息分类模块 ComparisonTypeModel
 * @file    ComparisonType.php
 * @author  BJP
 * @final   2015-06-18
 */
class ComparisonTypeModel
{
    /**
     * 按 id 读取对比信息分类 单条信息
     *          
     * @param   int     类型ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_comparison_type_by_id($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_comparison_type WHERE cmp_type_id = {$id}
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
     * 按名称读取单条信息
     *          
     * @param   string  分类名称
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_comparison_type_by_name($name = '', $item = '*')
    {
        if (empty($name))
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_comparison_type WHERE cmp_type_name = ?
EOT;
        $row = Fn::db()->fetchRow($sql, $name);
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
     * 通过条件获取分类列表
     *          
     * @param   array      查询条件
     * @param   int        当前页数
     * @param   int        每页个数
     * @param   string     排序
     * @param   string     选择的字段值，逗号分割
     * @return  array
     */
    public static function get_comparison_type_list($condition = array(), 
        $page = 1, $page_size = 15, $order_by = null, $select_what = '*')
    {
        $sql = <<<EOT
SELECT {$select_what} FROM rd_comparison_type 
EOT;
        $where = array();
        $bind = array();
        if (is_array($condition) AND $condition)
        {
            foreach ($condition as $key => $val)
            {
                switch($key) 
                {
                    case 'grade_id' :
                    case 'class_id' :
                    case 'subject_id' :                    
                        $where[] = "$key = ?";
                        $bind[] = $val;
                        break;
                    case 'cmp_type_flag' :
                    	if (is_array($val)){
                            foreach ($val as $v)
                            {
                                $where[] = "cmp_type_flag {$v[0]} ?";
                                $bind[] = $v[1];
                            }
                        }
                        else
                        {
                            $where[] = "$key = ?";
                            $bind[] = $val;
                    	}
                    	break;
                    case 'keyword' :
                        $where[] = "$key LIKE ?";
                        $bind[] = '%'. $val . '%';
                        break;
                    default : break;
                }
            }
        }

        if ($where)
        {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($order_by)
        {
            $sql .= " ORDER BY $order_by";
        }

        $page = intval($page);
        if ($page > 0)
        {
            $page_size = intval($page_size);
            $start = ($page - 1) * $page_size;
            $sql .= " LIMIT $page_size OFFSET $start";
        }
        $rows = Fn::db()->fetchAll($sql, $bind);
        return $rows;
    }

    /**
     * 通过条件获取分类个数
     *          
     * @param   array      查询条件
     * @return  int
     */
    public static function get_comparison_type_count($condition = array())
    {
        $result = self::get_comparison_type_list($condition, null, null, 
            null, 'COUNT(*) AS total');
        return $result ? $result[0]['total'] : 0;
    }
    
    /**
     * 更新分类信息
     *
     * @param   array/int     分类id
     * @param   array		     更新字段数组		  
     * @return  boolean
     */
    public static function update($id, $data)
    {
        try
        {
            $data['updatetime'] = time();
            if (is_array($id))
            {
                Fn::db()->update('rd_comparison_type', $data, "cmp_type_id IN (" . implode(',', $id) . ")");
            }
            else
            {
                Fn::db()->update('rd_comparison_type', $data, "cmp_type_id = $id");
            }
            return TRUE;
        }
        catch (Exception $e)
        {
            return FALSE;
    	}
    }
    
    /**
     * 插入分类信息
     *
     * @param   array		     字段数组
     * @return  boolean
     */
    public static function insert($data, &$insert_id)
    {   
        try
        {
            $db = Fn::db();
            $data['updatetime'] = time();
            $data['addtime'] = time();
            $res = $db->insert('rd_comparison_type', $data);
            if ($res)
            {
                $insert_id = $db->lastInsertId('rd_comparison_type', 'cmp_type_id');
                return TRUE;
            }
            else
            {    			
                log_message('error', 'mysql error:'.$db->errorInfo()[2]);
            }
        }
        catch (Exception $e)
        {
            return FALSE;
    	}
    }

    /**
     * 删除分类信息，及其下属信息条目
     *
     * @param   int/array        分类id
     * @return  boolean
     */
    public static function delete($id)
    {
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $tableInfo = $this->db->dbprefix('rd_comparison_info');
            $tableItem = $this->db->dbprefix('rd_comparison_item');

            if (is_array($id))
            {
                    $db->delete('rd_comparison_item', "cmp_info_id IN (SELECT cmp_info_id FROM rd_comparison_info WHERE cmp_type_id IN (" . my_implode($id) . "))");
                    $db->delete('rd_comparison_info', "cmp_type_id = $id");
                    $db->delete('rd_comparison_type', "cmp_type_id = $id");
            }
            else
            {

                    $db->delete('rd_comparison_item', "cmp_info_id IN (SELECT cmp_info_id FROM rd_comparison_info WHERE cmp_type_id = $id)");
                    $db->delete('rd_comparison_info', "cmp_type_id = $id");
                    $db->delete('rd_comparison_type', "cmp_type_id = $id");
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
    	}
        return $bOk;
    }
}
