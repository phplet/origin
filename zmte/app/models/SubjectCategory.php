<?php
/**
 * SubjectCategoryModel
 * @file    SubjectCategory.php
 * @author  BJP
 * @final   2015-06-22
 */
class SubjectCategoryModel
{
    //=============subject_category 学科分类================

    // 读取一个信息
    public static function get_subject_category($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_subject_category WHERE id = {$id}
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
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_subject_category_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = '*')
    {
        try
        {
            $where = array();
            $bind = array();
            
            if (is_array($query) && count($query))
            {
                foreach ($query as $key=>$val)
                {
                    switch ($key) {
                        case 'id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;                    
                    }
                }
            }
        
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';
            $group_by = '';
            
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            $sql = "SELECT {$select_what} FROM rd_subject_category {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     *
     * 通过 条件 获取 学科分类 条数
     * @param array $query
     */
    public static function count_subject_category_lists($query)
    {
        $result = self::get_subject_category_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
    
    //=================method_tactic 方法策略===================
    
    // 读取一个信息
    public static function get_method_tactic($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_method_tactic WHERE id = {$id}
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
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     *
     */
    public static function get_method_tactic_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = '*')
    {
        try
        {
            $where = array();
            $bind = array();
    
            if (is_array($query) && count($query))
            {
                foreach ($query as $key => $val)
                {
                    switch ($key)
                    {
                        case 'id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'subject_category_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "subject_category_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'subject_category_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
          
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';
            $group_by = '';
    
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
    
            $sql = "SELECT {$select_what} FROM rd_method_tactic {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     *
     * 通过 条件 获取 方法策略 条数
     * @param array $query
     */
    public static function count_method_tactic_lists($query)
    {
        $result = self::get_method_tactic_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
    
    /*
     *  获取某个学科关联的 方法策略
     */
    public static function get_method_tactic_by_subject_id($subject_id = 0)
    {
        $subject_id = intval($subject_id);
        $data = array();
        if (!$subject_id)
        {
            return $data;
        }        
        
        $sql = <<<EOT
SELECT mt.id, mt.name FROM rd_method_tactic mt, rd_subject_category_subject scs
WHERE scs.subject_id = {$subject_id} 
AND mt.subject_category_id = scs.subject_category_id
EOT;
        $data = Fn::db()->fetchAll($sql);
        $list = array();
        foreach ($data as $item) 
        {
            $list[$item['id']] = $item;    
        }
        return $list;
    }
    
    //================subject_category_subject 方法策略 -- 学科组合=======
    
    /**
     * 获取方法策略 对应的学科
     * @param number $subject_category_id
     * @param boolean $load_subject 是否加载科目信息
     * @return boolean
     */
    public static function get_subject_category_subjects(
        $subject_category_id = 0, $load_subject = false, 
        $group_by_subject = false)
    {
        $subject_category_id = intval($subject_category_id);
        if (!$subject_category_id)
        {
            return FALSE;
        }
        
        $sql = <<<EOT
SELECT * FROM rd_subject_category_subject 
WHERE subject_category_id = {$subject_category_id}
EOT;
        $result = Fn::db()->fetchAll($sql);
        
        if (!$load_subject)
        {
            return $result;
        }
        
        $tmp_result = array();
        foreach ($result as &$row)
        {
            $row['subject_name'] = C('subject/' . $row['subject_id']);
            $group_by_subject && $tmp_result[$row['subject_id']] = $row;
        }
        
        if ($group_by_subject)
        {
            return $tmp_result;
        }
        else
        {
            return $result;
        }
    }
    
    /**
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     *
     */
    public static function get_subject_category_subject_list($query, 
        $page = 1, $per_page = 20, $order_by = null, $select_what = '*')
    {
        try
        {
            $where = array();
            $bind = array();
    
            if (is_array($query) && count($query))
            {
                foreach ($query as $key => $val)
                {
                    switch ($key)
                    {
                        case 'subject_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k=>$v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "subject_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'subject_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'subject_category_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k=>$v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "subject_category_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'subject_category_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
             
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';
            $group_by = '';
    
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
    
            $sql = "SELECT {$select_what} FROM rd_subject_category_subject {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 获取未被添加过的学科 
     */
    public static function get_unadded_subjects($subject_category_id = 0)
    {
        $subject_category_id = intval($subject_category_id);
        $sql = "SELECT DISTINCT(subject_id) AS subject_id FROM rd_subject_category_subject";
        if ($subject_category_id)
        {
            $sql .= " WHERE subject_category_id != {$subject_category_id}"; 
        }
        
        $result = Fn::db()->fetchAll($sql);
        $sc_subjects = array();
        foreach ($result as $v)
        {
            $sc_subjects[] = $v['subject_id'];
        }
        
        $subjects = C('subject');
        $diff_subjects = array_diff(array_keys($subjects), $sc_subjects);
        $tmp_subjects = array();
        
        if (count($diff_subjects))
        {
            foreach($diff_subjects as $subject_id)
            {
                $tmp_subjects[$subject_id] = C('subject/'.$subject_id);
            }
        }
        
        return $tmp_subjects;
    }
    
}
