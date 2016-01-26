<?php
/**
 * 回收站--数据层   RecycleModel
 * @file    Recycle.php
 * @author  BJP
 * @final   2015-06-19
 */
class RecycleModel
{
    /**
     * 
     * 通过 条件 获取 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     * 
     */
    public static function get_recycle_list($query, $page = 1, $per_page = 20, 
        $order_by = null, $select_what = '*')
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
                        case 'type':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "type {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'type = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'obj_id':
                            if (is_array($val))
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "data {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'data = ?';
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
                $limit = " LIMIT {$start}, {$per_page}";
            }
            
            $sql = "SELECT {$select_what} FROM rd_recycle {$where} {$order_by} {$group_by} {$limit}";
            //echo $sql;die;
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
    public static function add($type, $obj_id, $reason)
    {
        $data = array(
                    'type' => $type,
                    'data' => $obj_id,
                    'reason' => $reason,
                    'ctime' => date('Y-m-d H:i:s'),
        );
        
        $res = Fn::db()->insert('rd_recycle', $data);
        return $res > 0;
    }

    /**
     * 
     * 通过 条件 获取条数
     * @param array $query
     */
    public static function count_lists($query) 
    {
        $result = self::get_recycle_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }
}
