<?php
/**
 * 考试系统--考生考试试卷 答题记录--数据层 ExamTestResultModel
 * @file    ExamTestResult.php
 * @author  BJP
 * @final   2015-06-19
 */
class ExamTestResultModel
{
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
    public static function get_test_result_list($query, $page = 1, 
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
                            $where[] = "etr_id IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'etr_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'etp_id':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "etp_id IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'etp_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'exam_pid':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "exam_pid {$k} ?";
                                $bind[] = $v;
                            }
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
                            foreach ($val as $k => $v)
                            {
                                $where[] = "exam_id {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            $where[] = 'exam_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'uid':
                        if (is_array($val))
                        {
                            foreach ($val as $k=>$v)
                            {
                                $where[] = "uid {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            $where[] = 'uid = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'paper_id':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "paper_id {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            $where[] = 'paper_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
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
                            $bind[] = intval($val);
                        }
                        break;
                    default:;
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
            
            $table_name = 'rd_exam_test_result';
            $sql = <<<EOT
SELECT {$select_what} FROM {$table_name}
{$where} {$order_by} {$group_by} {$limit}
EOT;
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
     * 通过 条件 获取条数
     * @param array $query
     */
    public static function count_list($query) 
    {
        $result = self::get_test_paper_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
}
