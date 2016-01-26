<?php
/**
 * 考试系统--试题相关统计--数据层 QuestionStatModel
 * @file    QuestionStat.php
 * @author  BJP
 * @final   2015-06-22
 */
class QuestionStatModel
{
    /**
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_stat_list($query, $page = 1, $per_page = 20, 
        $order_by = null, $select_what = '*')
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
                        case 'ques_id':
                            if (is_array($val))
                            {
                                foreach ($val as $k=>$v)
                                {
                                    $where[] = "ques_id {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'ques_id = ?';
                                $bind[] = $val;
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
            
            $sql = "SELECT {$select_what} FROM rd_question_stat {$where} {$order_by} {$group_by} {$limit}";
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 批量插入数据
     * array $data
     * return boolean
     */
    public static function insert_batch($data)
    {
        $db = Fn::db();
        if (!$db->beginTransaction())
        {
            return false;
        }
        $bOk = false;
        try
        {
            foreach ($data as $v)
            {
                $db->insert('rd_question_stat', $v);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            
            if ($bOk)
            {
                return true;
            }
            else
            {                
                log_message('error', 'mysql error:'. $db->errorInfo()[2]);
            }
        }
        catch (Exception $e)
        {
        }
        return $bOk;
    }

    /**
     * 批量更新数据
     *
     * @param   array   更新数据
     * @return  boolean
     */
    public static function update_batch($update_data)
    {
        $bOk = false;
        $db = Fn::db();
        if (!$db->beginTransaction())
        {
            return $bOk;
        }
        try
        {
            foreach ($update_data as $v)
            {
                $id = $v['id'];
                unset($v['id']);
                $db->update('rd_question_stat', $v, 'id = ' . $id);
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
     * 
     * 通过 条件 获取条数
     * @param array $query
     */
    public static function count_stat_list($query) 
    {
        $result = self::get_stat_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
}
