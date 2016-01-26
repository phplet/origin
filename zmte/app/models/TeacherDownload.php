<?php
/**
 * 评估报告教师模型 TeacherDownloadModel
 * @file    TeacherDownload.php
 * @author  BJP
 * @final   2015-06-26
 */
class TeacherDownloadModel
{
    /**
     * 通过id获取评估报告下载人员帐号单条记录
     * 
     * @param $id  
     * @param $item 待提取字段信息
     * @return mixed
     */
    public static function get_by_id($id = false, $item = '*')
    {
        if ($id === false)
        {
            return FALSE;
        }

        $sql = <<<EOT
SELECT {$item} FROM rd_teacher_download WHERE id = {$id}
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
     * 通过email获取报告下载人员帐号单条记录
     * 
     * @param $email 
     * @param $item
     * @return mixed
     */
    public static function get_by_email($email = '', $item = '*')
    {
        if ($email === '')
        {
            return FALSE;
        }

        $sql = <<<EOT
SELECT {$item} FROM rd_teacher_download WHERE email = ?
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
     * 通过条件报告下载人员帐号记录列表
     * 
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     * 
     */
    public static function get_list($query, $page = 1, $per_page = 20, 
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
                        case 'email':
                            if (is_array($val)) 
                            {
                                foreach ($val as $k => $v) 
                                {
                                    $where[] = "email {$k} ?";
                                    $bind[] = $v;
                                }
                            } 
                            else 
                            {
                                $where[] = 'email LIKE ?';
                                $bind[] = '%'.$val.'%';
                            }
                            break;
                        case 'name':
                            if (is_array($val)) 
                            {
                                foreach ($val as $k => $v) 
                                {
                                    $where[] = "name {$k} ?";
                                    $bind[] = $v;
                                }
                            } 
                            else 
                            {
                                $where[] = 'name LIKE ?';
                                $bind[] = '%'.$val.'%';
                            }
                            break;
                        case 'flag':
                            if (is_array($val)) 
                            {
                                $tmpStr = array();

                                foreach ($val as $k => $v) 
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }

                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "flag IN ({$tmpStr})";
                            } 
                            else 
                            {
                                $where[] = 'flag = ?';
                                $bind[] = intval($val);
                            }

                            break;
                        case 'addtime':
                            if (is_array($val)) 
                            {
                                foreach ($val as $k => $v)
                                {
                                    $where[] = "addtime {$k} ?";
                                    $bind[] = $v;
                                }
                            } 
                            else 
                            {
                                $where[] = 'addtime = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'updatetime':
                            if (is_array($val)) 
                            {
                                foreach ($val as $k => $v) 
                                {
                                    $where[] = "updatetime {$k} ?";
                                    $bind[] = $v;
                                }
                            } 
                            else 
                            {
                                $where[] = 'updatetime = ?';
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
            $group_by='';
            $limit = '';
            $page = intval($page);

            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }

            $sql = "SELECT {$select_what} FROM rd_teacher_download {$where} {$group_by} {$order_by} {$limit}";
            
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
    public static function count_lists($query) 
    {
        $result = self::get_list($query, null, null, null, 
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
        $res = Fn::db()->insert('rd_teacher_download', $data);
        return $res > 0;
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
            $data['updatetime'] = time();

            if (is_array($id)) 
            {
                Fn::db()->update('rd_teacher_download', $data, "id IN ("
                    . implode(',', $id) . ")");
            } 
            else 
            {
                Fn::db()->update('rd_teacher_download', $data,
                    "id = " . intval($id));
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
                    Fn::db()->delete('rd_teacher_download', 
                        "id IN (" . implode(',', $id) . ")");
                } 
                else 
                {
                    Fn::db()->delete('rd_teacher_download', "id = $id");
                }
            }
            else 
            {
                Fn::db()->update('rd_teacher_download', 
                    array('flag' => '-1'), "id = $id");
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
     * @param string $id
     * @param string $new_password
     * @return boolean
     */
    public static function reset_password($id, $new_password)
    {
        $id = intval($id);

        if ($id <= 0) 
        {
            return false;
        }
         
        try 
        {
            $update_data = array(
                'updatetime' => time(),
                'password' => $new_password,
            );

            Fn::db()->update('rd_teacher_download', $update_data,
                "id = $id");
            return true;
        }
        catch (Exception $e) 
        {
            return false;
        }
    }
}
