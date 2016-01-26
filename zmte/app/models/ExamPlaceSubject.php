<?php
/**
 * 考试场次 所考 学科--数据层 ExamPlaceSubjectModel
 * @file    ExamPlaceSubject.php
 * @author  BJP
 * @final   2015-06-22
 */
class ExamPlaceSubjectModel
{
    /**
     * 
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_exam_place_subject_list($query, $page = null, 
        $per_page = null, $order_by = null, $select_what = '*')
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
                        case 'place_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
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
            
            $sql = "SELECT {$select_what} FROM rd_exam_place_subject {$where} {$order_by} {$group_by} {$limit}";
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
     * array $data
     * return boolean
     */
    public static function insert($data)
    {
        $res = Fn::db()->insert('rd_exam_place_subject', $data);
        return $res > 0;
    }
    
    /**
     * 删除信息
     * mixed $id : 记录id
     * return mixed
     */
    public static function delete($id)
    {
        try
        {
            if (is_array($id))
            {
                $ids = implode(',', $id);
                Fn::db()->delete('rd_exam_place_subject', "id IN ($ids)");
            }
            else
            {
                Fn::db()->delete('rd_exam_place_subject', "id = $id");
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
     * 通过 条件 获取条数
     * @param array $query
     */
    public static function count_lists($query) 
    {
        $result = self::get_exam_place_subject_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
    

    /**
     * 检查某期考试的学科是否已经被考过并且考场考试已经结束
     */
     public static function exam_subject_has_be_tested($exam_id = 0)
     {
         $now = time();
         $exam_id = intval($exam_id);
         if (!$exam_id)
         {
             return false;
         }
         $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_exam_test_paper etp, rd_exam_place ep
WHERE etp.exam_id = {$exam_id} AND etp.place_id = ep.place_id 
AND ep.end_time < {$now}
EOT;
         $test_result = Fn::db()->fetchAll($sql);
         return $test_result[0]['count'] > 0;
    }
    
    /**
     * 检查某期考试的学科是否有关联 正在进行中的考场
     */
     public static function exam_subject_has_being_tested($exam_id = 0)
     {
         $exam_id = intval($exam_id);
         if (!$exam_id)
         {
             return false;
         }
        
         $now = time();
         $sql = <<<EOT
SELECT COUNT(eps.place_id) AS count 
FROM rd_exam_place_subject eps, rd_exam_place ep
WHERE eps.exam_id = {$exam_id} AND eps.place_id = ep.place_id 
AND ep.start_time <= {$now} AND ep.end_time >= {$now}
EOT;
         $test_result = Fn::db()->fetchAll($sql);
         return $test_result[0]['count'] > 0;
    }
    

    /**
     * 检查 某期次的 考试学科 被测试状态:
     *     1、已被考过
     *  2、正在考中
     */
    public static function exam_subject_has_test_action($exam_id)
    {
        return self::exam_subject_has_be_tested($exam_id) || self::exam_subject_has_being_tested($exam_id);
    }
}
