<?php
/**
 * 考试期次模块 ExamModel
 * @file    Exam.php
 * @author  BJP
 * @final   2015-07-01
 */
class ExamModel
{
    /**
     * 获取一个考试信息
     *          
     * @param   int     考试期次ID(exam_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_exam($id = 0, $item = NULL)
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
        $sql .= ' FROM rd_exam WHERE exam_id = ' . $id;
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

    // 二试报名与测评关联
    public static function get_exam_select($id)
    {
        $sql = <<<EOT
SELECT * FROM rd_exam WHERE exam_id = {$id} AND exam_pid = 0 AND status = 1
EOT;
        return Fn::db()->fetchRow($sql);
    }

    /**
     * 获取所有考试期次
     *
     * @param string $fields 获取的字段
     * @return void
     */
    public static function get_exam_list_all($param, $fields = '*')
    {
        $sql = <<<EOT
SELECT {$fields} FROM rd_exam
EOT;
        PDO_DB::build_where($param, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= ' WHERE ' . $where_sql;
        }
        $sql .= ' ORDER BY addtime DESC';
        return Fn::db()->fetchAll($sql, $bind);
    }
    
    /**
     *
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     *
     */
    public static function get_exam_list($query, $page = 1, $per_page = 20, 
        $order_by = null, $select_what = null)
    {
        try
        { 
            $where = array();
            $bind = array();

            if (is_array($query) && count($query))
            {
                foreach ($query as $key=>$val)
                {
                    switch ($key)
                    {
                        case 'exam_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
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
                                $bind[] = $val;
                            }
                            break;
                        case 'subject_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
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
                                $bind[] = $val;
                            }
                            break;
                        case 'grade_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "grade_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'grade_id = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'class_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "class_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'class_id = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'status':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "status IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'status = ?';
                                $bind[] = $val;
                            }
                            break;
                    }
                }
            }
          
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';
    
            $where = count($where) ? (" WHERE " . implode(' AND ', $where)) : '';
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
    
            $sql = "SELECT {$select_what} FROM rd_exam {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 删除一个考试信息
     *          
     * @param   int     考试期次ID(exam_id)
     * @param   bool    是否强制删除（同时删除下级分类、试卷等）
     * @return  mixed   TURE成功，其他负值代表不同失败类型
     */
    public static function delete($id, $force)
    {
        $id = intval($id);
        $id && $row = self::get_exam($id, 'exam_id,exam_pid,subject_id');
        if (empty($row)) 
        {          
            return -1; // 考试不存在
        }
         
        if ($force == false)
        {
            if ($row['exam_pid'] == 0)
            {
                $row2 = Fn::db()->fetchRow("SELECT exam_id FROM rd_exam WHERE exam_pid = $id LIMIT 1");
                if ($row2)
                {
                    return -2; // 存在下级分类（考试学科）
                }
            }
            else
            {
                $row3 = Fn::db()->fetchRow("SELECT paper_id FROM rd_exam_paper WHERE exam_id = {$id} LIMIT 1");
                if ($row3)
                {
                    return -3; // 已生成试卷
                }
                $now=time();
                $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_place_subject eps
JOIN rd_exam_place ep ON eps.place_id = ep.place_id WHERE eps.subject_id  = {$row['subject_id']} AND eps.exam_id = {$id} AND ep.start_time > {$now}
EOT;
                $row4 = Fn::db()->fetchRow($sql);
                if ($row4['cnt'] > 0)
                {
                    return -4; // 未参加考试，存在
                }
               
                $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_place_subject eps
JOIN rd_exam_place ep ON eps.place_id = ep.place_id WHERE eps.subject_id = {$row['subject_id']} AND eps.exam_id = {$id} AND ep.start_time <= {$now}
EOT;
                $row5 = Fn::db()->fetchRow($sql);
                
                if ($row5['cnt'] > 0)
                {
                    return -5; // 已经参加考试，存在
                }
            }
        }
        // todo:
        // 强制删除，还需要删除相应的关联数据
        if (Fn::db()->delete('rd_exam', "exam_id = $id"))
        {
            admin_log('delete', 'exam', $id);
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 重新排序一个考试期次下的学科
     * @param   int     考试期次父ID(exam_pid)
     * @return  void
     */
    public static function resort_index($pid)
    {
        $sql = <<<EOT
SELECT exam_id, exam_index FROM rd_exam WHERE exam_pid = {$pid} ORDER BY exam_index, exam_id DESC
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $index = 1;
        $updates = array();
        foreach ($rows as $row)
        {
            if ($row['exam_index'] != $index) 
            {
                $updates[] = array('exam_id'=> $row['exam_id'], 'exam_index'=>$index);
            }
            $index++;
        }

        if ($updates)
        {
            $bOk = false;
            $db = Fn::db();
            if ($db->beginTransaction())
            {
                foreach ($updates as $row)
                {
                    $db->update('rd_exam', array('exam_index' => $row['exam_index']), 'exam_id = ' . $row['exam_id']);
                }
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
            }
            return $bOk;
        }
        return true;
    }
    
    /**
     * 更新考试信息
     * array $data
     * mixed $where
     * return boolean
     */
    public static function update($data, $where)
    {
        PDO_DB::build_where($where, $where_sql, $bind);
        return Fn::db()->update('rd_exam', $data, $where_sql, $bind) > 0;
    }
    
    
    /**
     * 按 考试期次id 获取一个考试信息
     *
     * @param   int     考试期次ID(exam_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_exam_by_id($id = 0, $select_items = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
    
        if ($select_items)
        {
            $sql = 'SELECT ' . $select_items;
        }
        else
        {
            $sql = 'SELECT *';
        }
    
        $sql .= " FROM rd_exam WHERE exam_id = $id LIMIT 1";
        $row = Fn::db()->fetchRow($sql);
        if (is_string($select_items) && $select_items && isset($row[$select_items]))
        {
            return $row[$select_items];
        }
        else
        {
            return $row;
        }
    }
    
    /**
     * 检查是否是mini测
     * @param   int     $exam_pid   考试期次
     * @return  bool    true|false
     */
    public static function is_mini_test($exam_pid)
    {
        if (!$exam_pid)
        {
            return false;
        }
    
        $mini_list = C('demo_exam_config', 'app/demo/website');
        $is_mini = false;
        if ($mini_list)
        {
            foreach ($mini_list as $item)
            {
                if ($item['exam_pid'] == $exam_pid)
                {
                    $is_mini = true;
                    break;
                }
            }
        }
    
        return $is_mini;
    }
}
