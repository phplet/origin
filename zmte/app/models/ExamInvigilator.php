<?php
/**
 * 考试系统--监考人员帐号管理--数据层
 * ExaminvigilatorModel
 * @file    ExamInvigilator.php
 * @author  BJP
 * @final   2015-06-17
 */
class ExamInvigilatorModel
{
    /**
     * 
     * 通过 id 获取监考人员帐号 单条 记录
     * @param $id  
     * @param $item 待提取字段信息
     * @return mixed
     */
    public static function get_invigilator_by_id($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_invigilator WHERE invigilator_id = {$id}
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
     * 通过 email 获取监考人员帐号 单条 记录
     * @param $email 
     * @param $item
     * @return mixed
     */
    public static function get_invigilator_by_email($email = '', $item = '*')
    {
        if ($email === '')
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_invigilator WHERE invigilator_email = ?
EOT;
        $row = Fn::db()->fetchRow($sql, $email);
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
     * 
     * 通过 条件 获取监考人员帐号 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     * @param integer $place_id 当前考场
     * @param boolean $filter_assigned 是否过滤已分配的监考人员
     * 
     */
    public static function get_invigilator_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = '*', 
        $place_id = 0, $filter_assigned = false)
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
                    case 'invigilator_id':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "i.invigilator_id IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'i.invigilator_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'invigilator_email':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "i.invigilator_email {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            //$where[] = 'i.invigilator_email = ?';
                            //$bind[] = $val;
                            $where[] = 'invigilator_email like ?';
                            $bind[] = '%'.$val.'%';
                        }
                        break;
                    case 'invigilator_name':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "i.invigilator_name {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            // $where[] = 'i.invigilator_name = ?';
                            // $bind[] = $val;
                            $where[] = 'invigilator_name like ?';
                            $bind[] = '%'.$val.'%';
                        }
                        break;
                    case 'invigilator_flag':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "i.invigilator_flag IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'i.invigilator_flag = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'invigilator_addtime':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "i.invigilator_addtime {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            $where[] = 'i.invigilator_addtime = ?';
                            $bind[] = $val;
                        }
                        break;
                    case 'invigilator_updatetime':
                        if (is_array($val))
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "i.invigilator_updatetime {$k} ?";
                                $bind[] = $v;
                            }
                        }
                        else
                        {
                            $where[] = 'i.invigilator_updatetime = ?';
                            $bind[] = $val;
                        
                        }
                        break;
                    default:;
                    }
                }
            }
            $where = count($where) ? (" WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY i.' . $order_by : '';
            $group_by = ' GROUP BY i.invigilator_id';
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            //获取考场
            $place_id = intval($place_id);
            $table_name = 'rd_invigilator';
            if ($place_id > 0)
            {
                //$this->load->model('admin/exam_place_model');
                $place = ExamPlaceModel::get_place($place_id);
                $place['start_time'] = $place['start_time'] + 1;
                $place['end_time'] = $place['end_time'] - 1;
                $sub_sql = <<<EOT
SELECT DISTINCT(invigilator_id) 
FROM rd_exam_place_invigilator epi, rd_exam_place p 
WHERE (
(p.start_time >= {$place['start_time']} AND p.start_time <= {$place['end_time']}) 
OR 
(p.end_time >= {$place['start_time']} AND p.end_time <= {$place['end_time']})
OR
(p.start_time <= {$place['start_time']} AND p.end_time >= {$place['end_time']})
) 
AND p.place_id=epi.place_id
EOT;
                if ($filter_assigned)
                {
                    $sql = <<<EOT
SELECT {$select_what} FROM {$table_name} i 
{$where} AND invigilator_id NOT IN ({$sub_sql}) 
{$group_by} {$order_by} {$limit}
EOT;
                }
                else
                {
                    $sql = <<<EOT
SELECT {$select_what} FROM {$table_name} i, rd_exam_place_invigilator epi 
{$where} AND i.invigilator_id = epi.invigilator_id 
AND i.invigilator_id IN ({$sub_sql}) {$group_by} {$order_by} {$limit}
EOT;
                }
            }
            else
            {
                $sql = <<<EOT
SELECT {$select_what} FROM {$table_name} i 
{$where} {$group_by} {$order_by} {$limit}
EOT;
            }
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
    	}
    }

    /**
     * 插入监考人员信息
     * array $data
     * return boolean
     */
    public static function insert($data)
    {
        // 关闭错误信息，防止 unique index 冲突出错
        try
        {
            $res = Fn::db()->insert('rd_invigilator', $data);
        }
        catch (Exception $e)
        {
            $res = 0;
        }
        return $res > 0;
    }

    /**
     * 更新监考人员信息
     * mixed $id
     * array $data
     * return boolean
     */
    public static function update($id, $data)
    {
        try
        {
            $data['invigilator_updatetime'] = time();
            if (is_array($id))
            {
                Fn::db()->update('rd_invigilator', $data, 'invigilator_id IN (' . implode(',', $id) . ')');
            }
            else
            {
                Fn::db()->update('rd_invigilator', $data, 'invigilator_id = ' . intval($id));
            }
            return true;
        }
        catch(Exception $e)
        {
            return false;
        }
    }
    
    /**
     * 删除监考人员信息(彻底删除/回收站)
     * mixed $id : 监考人员id
     * bool $force : 是否强制删除
     * return mixed
     */
    public static function delete($id, $force = false)
    {
        try
        {
            //彻底删除
            if ($force === TRUE)
            {
                if (is_array($id))
                {
                    Fn::db()->delete('rd_invigilator', 'invigilator_id IN ('. implode(',', $id) . ')');
                    Fn::db()->delete('rd_exam_place_invigilator', 'invigilator_id IN ('. implode(',', $id) . ')');
                }
                else
                {
                    Fn::db()->delete('rd_invigilator', 'invigilator_id = '. $id);
                    Fn::db()->delete('rd_exam_place_invigilator', 'invigilator_id = '. $id);
                }
            }
            else
            {
                self::update($id, array('invigilator_flag' => '-1'));
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
     * @param integer $place_id 当前考场
     * @param boolean $filter_assigned 是否过滤已分配的监考人员
     */
    public static function count_invigilator_lists($query, $place_id = 0, 
        $filter_assigned = false) 
    {
        $result = self::get_invigilator_list($query, null, null, null, 
            'COUNT(*) AS total', $place_id, $filter_assigned);

        //return count($result) ? $result[0]['total'] : 0;
        return count($result) ? count($result) : 0;
    }
    
    /**
     * 重置监考人员密码
     * @param string $invigilator_id
     * @param string $new_password
     * @return boolean
     */
    public static function reset_invigilator_password($invigilator_id, $new_password)
    {
    	$invigilator_id = intval($invigilator_id);
        if ($invigilator_id <= 0)
        {
            return false;
    	}
        try
        {
            $update_data = array(
                'invigilator_updatetime' => time(),
                'invigilator_password' => $new_password,
            );
            Fn::db()->update('rd_invigilator', $update_data, 
                'invigilator_id = ' . $invigilator_id);
            return true;
        }
        catch(Exception $e)
        {
            return false;
    	}
    }
}
