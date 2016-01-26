<?php 
/**
 * InvigilateModel
 * @file    Invigilate.php
 * @author  BJP
 * @final   2015-06-17
 */
class InvigilateModel
{
    /**
     * 通过 id 获取监考人员帐号 单条 记录
     * 
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
     * 
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
     * 通过 条件 获取监考人员帐号 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_invigilator_list($query, $page = 1, 
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
                            $where[] = "invigilator_id IN ({$tmpStr})";
                        } 
                        else 
                        {
                            $where[] = 'invigilator_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'invigilator_email':
                        if (is_array($val)) 
                        {
                            foreach ($val as $k => $v) 
                            {
                                $where[] = "invigilator_email {$k} ?";
                                $bind[] = $v;
                            }
                        } 
                        else 
                        {
                            /*
                            $where[] = 'invigilator_email = ?';
                            $bind[] = $val;
                             */

                            $where[] = 'invigilator_email LIKE ?';
                            $bind[] = '%'.$val.'%';
                        }
                        break;
                    case 'invigilator_name':
                        if (is_array($val)) 
                        {
                            foreach ($val as $k => $v) 
                            {
                                $where[] = "invigilator_name {$k} ?";
                                $bind[] = $v;
                            }
                        } 
                        else 
                        {
                            /*
                            $where[] = 'invigilator_name = ?';
                            $bind[] = $val;
                             */
                            $where[] = 'invigilator_name LIKE ?';
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
                            $where[] = "invigilator_flag IN ({$tmpStr})";
                        } 
                        else 
                        {
                            $where[] = 'invigilator_flag = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'invigilator_addtime':
                        if (is_array($val)) 
                        {
                            foreach ($val as $k => $v)
                            {
                                $where[] = "invigilator_addtime {$k} ?";
                                $bind[] = $v;
                            }
                        } 
                        else 
                        {
                            $where[] = 'invigilator_addtime = ?';
                            $bind[] = $val;
                        }
                        break;
                    case 'invigilator_updatetime':
                        if (is_array($val)) 
                        {
                            foreach ($val as $k => $v) 
                            {
                                $where[] = "invigilator_updatetime {$k} ?";
                                $bind[] = $v;
                            }
                        } 
                        else 
                        {
                            $where[] = 'invigilator_updatetime = ?';
                            $bind[] = $val;
                        }
                        break;
                    default:
                    }
                }
            }
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';

            /*
            $group_by = 'group by invigilator_id';
             */
            $group_by = '';
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = "LIMIT {$per_page} OFFSET {$start}";
            }
            $sql = <<<EOT
SELECT {$select_what} FROM rd_invigilator 
{$where} {$group_by} {$order_by} {$limit}
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
     * @param integer $place_id 当前考场
     * @param boolean $filter_assigned 是否过滤已分配的监考人员
     */
    public static function count_invigilator_lists($query) 
    {
        $result = self::get_invigilator_list($query, null, null, null, 
            'COUNT(*) AS total');
        return count($result) ? $result[0]['total'] : 0;
    }

    /**
     * 插入监考人员信息
     * 
     * @param array $data
     * @return boolean
     */
    public static function insert($data)
    {
        $affected_rows = Fn::db()->insert('rd_invigilator', $data);
        return $affected_rows > 0;
    }

    /**
     * 更新监考人员信息
     *
     * @param mixed $id
     * @param array $data
     * @return boolean
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
                Fn::db()->update('rd_invigilator', $data, 'invigilator_id = ' . $id);
            }
           
            return true;
        } 
        catch (Exception $e) 
        {
            return false;
        }
    }
    
    /**
     * 删除监考人员信息(彻底删除/回收站)
     *
     * @param mixed $id : 监考人员id
     * @param bool $force : 是否强制删除
     * @return mixed
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
                    $idstr = implode(',', $id);
                    Fn::db()->delete('rd_invigilator', "invigilator_id IN ($idstr)");
                    Fn::db()->delete('rd_exam_place_invigilator', "invigilator_id IN ($idstr)");
                } 
                else 
                {
                    Fn::db()->delete('rd_invigilator', 'invigilator_id = ?', $id);
                    Fn::db()->delete('rd_exam_place_invigilator', 'invigilator_id = ?', $id);
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
     * 重置监考人员密码
     *
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
                'invigilator_password' => $new_password);

            Fn::db()->update('rd_invigilator', $update_data, 'invigilator_id = '. $invigilator_id);
            return true;
        } 
        catch (Exception $e) 
        {
            return false;
        }
    }
}
