<?php
/**
 * 计划任务--数据层 CronScheduleModel
 * @file    CronSchedule.php
 * @author  BJP
 * @final   2015-06-19
 */
class CronScheduleModel
{
    /**
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_cron_schedule_list($query, $page = 1, 
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
                    switch ($key)
                    {
                        case 'job_code':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "job_code IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'job_code = ?';
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
                        case 'created_at':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "created_at {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'created_at = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'scheduled_at':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "scheduled_at {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'scheduled_at = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'executed_at':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "executed_at {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'executed_at = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'finished_at':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "finished_at {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'finished_at = ?';
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
            if ($page > 0) {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            $sql = "SELECT {$select_what} FROM rd_cron_schedule {$where} {$order_by} {$group_by} {$limit}";
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
        $result = self::get_cron_schedule_list($query, null, null, null, 
            'COUNT(*) AS total');
        
        return count($result) ? $result[0]['total'] : 0;
    }
}
