<?php
/**
 * 评估管理--评估规则 --数据层 EvaluateRuleModel
 * @file    EvaluateRule.php
 * @author  BJP
 * @final   2015-06-22
 */
class EvaluateRuleModel
{
    /**
     * 获取单条评估规则
     */
    public static function get_evaluate_rule($id=0, $item=NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $sql = 'SELECT ' . $item;
        }
        else
        {
            $sql = 'SELECT *';
        }
        $sql .= ' FROM rd_evaluate_rule WHERE id = ' . $id;
        $rule = Fn::db()->fetchRow($sql);
        if ($item && isset($rule[$item]))
        {
            return $rule[$item];
        }
        else
        {
            return $rule;        
        }
    }
    
    /**
     * 获取评估规则  记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_evaluate_rule_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = null)
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
                        case 'exam_pid':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "exam_pid IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'exam_pid = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'exam_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k=>$v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "exam_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'exam_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'place_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k=>$v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "place_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'place_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
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
                        case 'is_delete':
                            if (is_array($val))
                            {
                                foreach ($val as $k=>$v)
                                {
                                    $where[] = "is_delete {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'is_delete = ?';
                                $bind[] = $val;
                            }
                            break;
                        default:
                            break;                    
                    }
                }
            }
        
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';
            
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';
            $group_by = '';
            
            $limit = '';
            $page = intval($page);
            if ($page > 0) {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            $sql = "SELECT {$select_what} FROM rd_evaluate_rule {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * 插入信息
     * @param array $rule_data
     * @param array $knowledge_data
     * @param array $method_tactic_data
     * @param array $group_type_data
     * @return boolean
     */
    public static function insert($rule_data, $knowledge_data, 
        $method_tactic_data = array(), $group_type_data = array())
    {
        $bOk = false;
        $db = Fn::db();
        if (!$db->beginTransaction())
        {
            return $bOk;
        }
        try
        {
            $rule_data['admin_id'] = Fn::sess()->userdata('admin_id');
            if ($db->insert('rd_evaluate_rule', $rule_data))
            {
                $inserted_id = $db->lastInsertId('rd_evaluate_rule', 'id');
                
                //知识点
                foreach ($knowledge_data as &$item)
                {
                    $item['er_id'] = $inserted_id;
                    $db->insert('rd_evaluate_knowledge', $item);
                }
                
                //方法策略
                if (is_array($method_tactic_data) && count($method_tactic_data))
                {
                    foreach ($method_tactic_data as &$item)
                    {
                        $item['er_id'] = $inserted_id;
                        $db->insert('rd_evaluate_method_tactic', $item);
                    }
                }
                
                //信息提取方式
                if (is_array($group_type_data) && count($group_type_data))
                {
                    foreach ($group_type_data as &$item)
                    {
                        $item['er_id'] = $inserted_id;
                        $db->insert('rd_evaluate_group_type', $item);
                    }
                }
            }
            
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        catch(Exception $e)
        {
        }
        return $bOk;
    }

    /**
     * 更新信息
     * @param int $id
     * @param array $rule_data
     * @param array $knowledge_data
     * @param array $method_tactic_data
     * @param array $group_type_data
     * @return boolean
     */
    public static function update($id, $rule_data, $knowledge_data = array(), 
        $method_tactic_data = array(), $group_type_data = array())
    {
        $db = Fn::db();
        try
        {
            if (is_array($id))
            {
                $db->update('rd_evaluate_rule', $rule_data, 
                    'id IN (' . implode(',', $id) . ')');
            }
            else
            {
                $db->update('rd_evaluate_rule', $rule_data,
                    'id = ' . intval($id));
            }
            
            //一级知识点
            $knowledge_table = 'rd_evaluate_knowledge';
            
            //获取该规则关联的知识点
            $insert_data = array();
            $old_knowledges = self::get_evaluate_rule_knowledge($id, true);
            $delete_ids = array();
           
            foreach ($knowledge_data as $item)
            {
                $subject_id = $item['subject_id'];
                $knowledge_id = $item['knowledge_id'];
                $level = $item['level'];
                if (isset($old_knowledges[$subject_id][$knowledge_id][$level]))
                {
                    // update
                    $update_what = array(
                            'comment' => $item['comment'],
                            'suggest' => $item['suggest'],
                    );
                    $update_where = array(
                            "er_id = $id",
                            "subject_id = $subject_id",
                            "knowledge_id = $knowledge_id",
                            "level = $level"
                    );
                        
                    $db->update($knowledge_table, $update_what, 
                        implode(" AND ", $update_where));
                     unset($old_knowledges[$subject_id][$knowledge_id][$level]);
                }
                else
                {
                    // insert
                    $item['er_id'] = $id;
                    $insert_data[] = $item;
                }
            }
            if (count($insert_data))
            {
                foreach ($insert_data as $v)
                {
                    $db->insert($knowledge_table, $v);
                }
            }
            
            $old_knowledges = array_filter($old_knowledges);
            if ($old_knowledges)
            {
                // delete
                foreach ($old_knowledges as $k => $item)
                {
                    $subject_id = $k;
                    foreach ($item as $knowledge_id => $sub_item)
                    {
                        $levels = array_keys($sub_item);
                        if (!count($levels))
                        {
                            continue;
                        }
                        $where11 = array("er_id = $id",
                            "subject_id = $subject_id",
                            "knowledge_id = $knowledge_id",
                            "level IN (" . implode(',', $levels) . ")");
                        $db->delete($knowledge_table, implode(' AND ',$where11));
                    }
                }
            }
            
            //方法策略
            if ($method_tactic_data)
            {
                $method_tactic_table = 'rd_evaluate_method_tactic';
                
                $insert_data = array();
                $old_method_tactics = self::get_evaluate_rule_method_tactic($id, true);
                foreach ($method_tactic_data as $item)
                {
                    $subject_id = $item['subject_id'];
                    $method_tactic_id = $item['method_tactic_id'];
                    $level = $item['level'];
                    if (isset($old_method_tactics[$subject_id][$method_tactic_id][$level])) {
                        // update
                        $update_what = array(
                                'comment' => $item['comment'],
                                'suggest' => $item['suggest'],
                        );
                        $update_where = array(
                                "er_id = $id",
                                "subject_id = $subject_id",
                                "method_tactic_id = $method_tactic_id",
                                "level = $level"
                        );
                
                        $db->update($method_tactic_table, $update_what, 
                            implode(' AND  ',  $update_where));
                        unset($old_method_tactics[$subject_id][$method_tactic_id][$level]);
                    }
                    else
                    {
                        // insert
                        $item['er_id'] = $id;
                        $insert_data[] = $item;
                    }
                }
                
                if (count($insert_data))
                {
                    foreach ($insert_data as $v)
                    {
                        $db->insert($method_tactic_table, $v);
                    }
                }
                
                $old_method_tactics = array_filter($old_method_tactics);
                if ($old_method_tactics)
                {
                    // delete
                    foreach ($old_method_tactics as $k => $item)
                    {
                        $subject_id = $k;
                        foreach ($item as $method_tactic_id => $sub_item)
                        {
                            $levels = array_keys($sub_item);
                            if (!count($levels))
                            {
                                continue;
                            }
                            $where11 = array("er_id = $id",
                                "subject_id = $subject_id",
                                "method_tactic_id = $method_tactic_id",
                                "level IN (" . implode(',', $levels) . ")");
                            $db->delete($method_tactic_table, implode(' AND ',
                                $where11));
                        }
                    }
                }
            }
            
            //信息提取方式
            if ($group_type_data)
            {
                $group_type_table = 'rd_evaluate_group_type';
                
                $insert_data = array();
                $old_group_types = self::get_evaluate_rule_group_type($id, true);
                foreach ($group_type_data as $item)
                {
                    $subject_id = $item['subject_id'];
                    $group_type_id = $item['group_type_id'];
                    $level = $item['level'];
                    if (isset($old_group_types[$subject_id][$group_type_id][$level]))
                    {
                        // update
                        $update_what = array(
                                'comment' => $item['comment'],
                                'suggest' => $item['suggest'],
                        );
                        $update_where = <<<EOT
er_id = {$id} AND subject_id = {$subject_id} AND
group_type_id = {$group_type_id} AND level = {$level}
EOT;
                        $db->update($group_type_table, $update_what, $update_where);
                         
                        unset($old_group_types[$subject_id][$group_type_id][$level]);
                    }
                    else
                    {
                        // insert
                        $item['er_id'] = $id;
                        $insert_data[] = $item;
                    }
                }
                
                if (count($insert_data))
                {
                    foreach ($insert_data as $v)
                    {
                        $db->insert($group_type_table, $v);
                    }
                }
                
                $old_group_types = array_filter($old_group_types);
                if ($old_group_types)
                {
                    // delete
                    foreach ($old_group_types as $k=>$item)
                    {
                        $subject_id = $k;
                        foreach ($item as $group_type_id=>$sub_item)
                        {
                            $levels = array_keys($sub_item);
                            if (!count($levels))
                            {
                                continue;
                            }
                            $levels_ids = implode(',', $levels);
                            $where11 = <<<EOT
er_id = {$id} AND subject_id = {$subject_id} 
AND group_type_id = {$group_type_id} AND level IN {$levels_ids}
EOT;
                            $db->delete($group_type_table, $where11);
                        }
                    }
                }
            }
            return true;
        }
        catch(Exception $e)
        {
            return false;
        }
    }
    
    /**
     * 删除信息(彻底删除/回收站)
     * mixed $id : id
     * bool $force : 是否强制删除
     * return mixed
     */
    public static function delete($id, $force = false)
    {
        $db = Fn::db();
        try
        {
            //彻底删除
            if ($force === TRUE)
            {
                if (is_array($id))
                {
                    $ids = implode(',', $id);
                    $db->delete('rd_evaluate_rule', "id IN ($ids)");
                    //删除关联表数据
                    $db->delete('rd_evaluate_knowledge', "er_id IN ($ids)");
                    $db->delete('rd_evaluate_method_tactic', "er_id IN ($ids)");
                }
                else
                {
                    $db->delete('rd_evaluate_rule', "id = $id");
                    //删除关联表数据
                    $db->delete('rd_evaluate_knowledge', "er_id = $id");
                    $db->delete('rd_evaluate_method_tactic', "er_id = $id");
                }
            }
            else
            {
                self::update($id, array('is_delete' => '1'));
            }
            
            return true;
            
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    /**
     * 
     * 通过 条件 获取 评估规则 条数
     * @param array $query
     */
    public static function count_lists($query) 
    {
        $result = self::get_evaluate_rule_list($query, null, null, null, 
            'COUNT(*) AS total');
        
        return count($result) ? $result[0]['total'] : 0;
    }
    
    //=========================一级知识点===============================

    /**
     *
     * 获取评估规则关联知识点 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     *
     */
    public static function get_evaluate_knowledge_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = null, 
        $group_by_knowledge = false)
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
                        case 'er_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "er_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'er_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'knowledge_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "knowledge_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'knowledge_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'level':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "level IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'level = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
          
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';
    
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
    
            $sql = "SELECT {$select_what} FROM rd_evaluate_knowledge {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
                
            if (!$group_by_knowledge)
            {
                return $data;
            }
                
            //按照knowledge_id归档
            $list = array();
            foreach ($data as $item)
            {
                $list[$item['subject_id']][] = $item;
            }
    
            return $list;
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 
     * 通过 条件 获取 评估规则 关联的一级知识点  条数
     * @param array $query
     * @param array $what
     */
    public static function count_knowledge_lists($query, $what = null) 
    {
        $what = is_null($what) ? 'COUNT(*)' : $what;
        $result = self::get_evaluate_knowledge_list($query, null, null, null, 
            "{$what} AS total");
        
        return count($result) ? $result[0]['total'] : 0;
    }
    
    /**
     * 根据 er_id获取知识点列表
     * @param int $er_id
     * @param string $use_unique_key
     * @return array
     */
    public static function get_evaluate_rule_knowledge($er_id, 
        $use_unique_key = false) 
    {
        if (!$er_id = intval($er_id))
        {
            return array();
        }
        $list = Fn::db()->fetchAll("SELECT * FROM rd_evaluate_knowledge "
            . "WHERE er_id = $er_id");
        if ($use_unique_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['subject_id']][$val['knowledge_id']][$val['level']] = $val;
            }
            $list = &$new_list;
        }
        
        return $list;
    }
    
    //=======================方法策略 evaluate_method_tactic=========================//
    /**
     *
     * 获取评估规则关联 方法策略 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     *
     */
    public static function get_evaluate_method_tactic_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = null, 
        $group_by_method_tactic = false)
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
                        case 'er_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "er_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'er_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'method_tactic_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "method_tactic_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'method_tactic_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'level':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "level IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'level = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
             
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';
    
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
    
            $sql = "SELECT {$select_what} FROM rd_evaluate_method_tactic {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
             
            if (!$group_by_method_tactic)
            {
                return $data;
            }
             
            //按照method_tactic_id归档
            $list = array();
            foreach ($data as $item)
            {
                $list[$item['subject_id']][] = $item;
            }
    
            return $list;
             
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     *
     * 获取评估规则关联信息提取方式 记录列表
     * @param array   $query
     * @param integer $page
     * @param integer $per_page
     * @param string  $order_by
     * @param string  $select_what
     */
    public static function get_evaluate_group_type_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = null, 
        $group_by_group_type = false)
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
                        case 'er_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "er_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'er_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'group_type_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "group_type_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'group_type_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'level':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "level IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'level = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
          
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';
    
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
    
            $sql = "SELECT {$select_what} FROM rd_evaluate_group_type {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
                
            if (!$group_by_group_type)
            {
                return $data;
            }
                
            //按照knowledge_id归档
            $list = array();
            foreach ($data as $item)
            {
                $list[$item['subject_id']][] = $item;
            }
    
            return $list;
                
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 
     * 通过 条件 获取 评估规则 关联的方法策略  条数
     * @param array $query
     * @param array $what
     */
    public static function count_method_tactic_lists($query, $what = null) 
    {
        $what = is_null($what) ? 'COUNT(*)' : $what;
        $result = self::get_evaluate_method_tactic_list($query, null, null, null, "{$what} AS total");
        
        return count($result) ? $result[0]['total'] : 0;
    }
    
    /**
     * 根据 er_id获取方法策略列表
     * @param int $er_id
     * @param string $use_unique_key
     * @return array
     */
    public static function get_evaluate_rule_method_tactic($er_id, 
        $use_unique_key = false)
    {
        if (!$er_id = intval($er_id))
        {
            return array();
        }
        $list = Fn::db()->fetchAll("SELECT * FROM rd_evaluate_method_tactic "
            . "WHERE er_id = $er_id");
        if ($use_unique_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['subject_id']][$val['method_tactic_id']][$val['level']] = $val;
            }
            $list = &$new_list;
        }
         
        return $list;
    }
    
    /**
     * 根据 er_id获取信息提取方式列表
     * @param int $er_id
     * @param string $use_unique_key
     * @return array
     */
    public static function get_evaluate_rule_group_type($er_id, 
        $use_unique_key = false)
    {
        if (!$er_id = intval($er_id))
        {
            return array();
        }
        $list = Fn::db()->fetchAll("SELECT * FROM rd_evaluate_group_type "
            . "WHERE er_id = $er_id");
        if ($use_unique_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['subject_id']][$val['group_type_id']][$val['level']] = $val;
            }
            $list = &$new_list;
        }
    
        return $list;
    }
}
