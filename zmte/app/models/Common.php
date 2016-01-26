<?php
/**
 * CommonModel
 * @file    Common.php
 * @author  BJP
 * @final   2015-06-18
 */
class CommonModel
{
    /**
     * 获取学生的信息
     * @param number $uid
     * @param string $item
     * @return boolean|unknown
     */
    public static function get_student($uid = 0, $item = '*')
    {
    	return self::_get_student_by_unique('uid', $uid, $item);
    }

    /**
     * 按  Email 获取一个学生信息
     */
    public static function get_student_by_email($email, $item = '*')
    {
    	return self::_get_student_by_unique('email', $email, $item);
    }
    
    /**
     * 按  准考证(exam_ticket)  获取一个学生信息
     */
    public static function get_student_by_exam_ticket($exam_ticket, $item = '*')
    {
    	return self::_get_student_by_unique('exam_ticket', $exam_ticket, $item);
    }
    
    /**
     * 按 UNIQUE/PRIMARY KEY 获取一个学生信息
     *
     * @param   string  字段名称
     * @param   string  字段值
     * @param   string  需要获取的字段值
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    private static function _get_student_by_unique($key, $val, $item = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$item} FROM rd_student WHERE {$key} = ? LIMIT 1
EOT;
        $row = Fn::db()->fetchRow($sql, $val);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }//}}}
    
    /**
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     * @param string $table_name
     */
    public static function get_list($query, $table_name, $select_what = null, 
        $page = 1, $per_page = 20, $order_by = null, $group_by = null)
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
                    case 'keyword':
                        $where[] = $val;
                        break;
                    default:
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "$key IN ({$tmpStr})";
                        }
                        else
                        {
                            if (strstr($key, '>='))
                            {
                                $key = trim(str_replace('>=', '', $key));
                                $where[] = "$key >= ?";
                            }
                            else if(strstr($key, '<='))
                            {
                                $key = trim(str_replace('<=', '', $key));
                                $where[] = "$key <= ?";
                            }
                            else if(strstr($key, '<'))
                            {
                                $key = trim(str_replace('<', '', $key));
                                $where[] = "$key < ?";
                            }
                            
                            else if(strstr($key, '>'))
                            {
                                $key = trim(str_replace('>', '', $key));
                                $where[] = "$key > ?";
                            }
                            else 
                            {
                                $where[] = "$key = ?";
                            }
                            $bind[] = $val;
                        }
                    }
                }
            }
            
            $select_what = is_string($select_what) ? (array) $select_what : $select_what;
            $select_what = count($select_what) ? implode(', ', $select_what) : '*';

            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? 'ORDER BY ' . $order_by : '';
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }

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

    /*
     * @description 获取管理员列表
     */
    public static function get_admins($act)
    {
        $admin_id = Fn::sess()->userdata('admin_id');
        if($act == 'add')
        {
            $sql = <<<EOT
SELECT admin_id,admin_user FROM rd_admin
WHERE admin_id <> {$admin_id} AND is_delete = 0
EOT;
        }
        else 
        {
            $sql = <<<EOT
SELECT admin_id, admin_user FROM rd_admin WHERE is_delete = 0
EOT;
        }
        $res = Fn::db()->fetchPairs($sql);
        return $res;
    }
    
    
    /*
     * @description 获取产品类别列表
    */
    public static function get_pc_ids()
    {
        $sql = "SELECT pc_id, pc_name FROM rd_product_category";
        $res = Fn::db()->fetchPairs($sql);
        return $res;
    }
    
    /*
     * @description 获取产品数量
     * @param int $p_id
     *        产品id
    */
    public static function get_product($p_id)
    {
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_product WHERE p_id = {$p_id}
EOT;
        $res = Fn::db()->fetchOne($sql);
        return $res;
    }
    
    /*
     * @description 获取产品列表
    * @param int $p_id
    *        产品id
    */
    public static function get_product_list($p_id)
    {
        $sql = <<<EOT
SELECT * FROM v_production WHERE p_id = {$p_id}
EOT;
        $res = Fn::db()->fetchRow($sql);
        return $res;
    }
    
    /**
     * @description 获取产品分类数量
     * @param int $pc_id
     *        产品id
     */
    public static function get_product_category($pc_id)
    {
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_product_category WHERE pc_id = {$pc_id}
EOT;
        $res = Fn::db()->fetchOne($sql);
        return $res;
    }
    
    /*
     * @description 获取产品分类列表
    * @param int $pc_id
    *        产品id
    */
    public static function get_product_category_list($pc_id)
    {
        $sql = <<<EOT
SELECT * FROM rd_product_category WHERE pc_id = {$pc_id}
EOT;
        $res = Fn::db()->fetchRow($sql);
        return $res;
    }
    
   /*
    * @description 获取产品交易记录数量
    * @param int $p_id
    *        产品id
    * @param int $uid
    *        用户id
    * @param int $place_id
    *        考场id
    * @param int $exam_pid
    *        期次id    
    */
    public static function get_product_trans($p_id, $uid)
    {
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM t_transaction_record
WHERE tr_pid = {$p_id} AND tr_uid = $uid
EOT;
        $res = Fn::db()->fetchOne($sql);
        return $res;
    }
    
    /*
     * @description 获取期次信息
     * @param string $act
     *        add/edit
     */
    public static function get_exam_ids($act)
    {
        $time = time();
        
        $sql = <<<EOT
SELECT exam_id, exam_name, place_id FROM v_exam_place 
WHERE start_time >= {$time}
EOT;
        $res = Fn::db()->fetchAll($sql);
        
        $result = array();
        foreach ($res as $v)
        {
            if (self::check_place_status($v['place_id'], $v['exam_id'], $act))
            {
                $result[$v['exam_id']] = $v['exam_name'];
            }
        }
        return $result;
    }
    
    /*
     * @description 检查考场状态
     * @param string $act
     *        add/edit
     * @param int $exam_pid
     *        期次id
     * @param int $place_id
     *        考场id
     */
    public static function check_place_status($place_id, $exam_pid, $act)
    {
        if ($act == 'add')
        {
            $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_product WHERE exam_pid = {$exam_pid}
EOT;
            $result = Fn::db()->fetchAll($sql);
            if ($result[0]['cnt'])
            {
                return false;
            }
        }
       
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_place_subject 
WHERE place_id = {$place_id} AND exam_pid = {$exam_pid}
EOT;
        $result = Fn::db()->fetchAll($sql);
        if (!$result[0]['cnt']) {
            $message[] = '未选择学科(<font color="red">请确认下该考场所在的考试期次下是否已添加 学科</font>)';
            return false;
        }
        else
        {
            $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_place_subject 
WHERE place_id = {$place_id} AND exam_pid = {$exam_pid}
EOT;
            $result = Fn::db()->fetchAll($sql);
            $sql = <<<EOT
SELECT COUNT(DISTINCT(eps.exam_id)) AS cnt 
FROM rd_exam_place_subject eps, rd_exam_subject_paper esp 
WHERE eps.place_id = {$place_id} AND eps.exam_id = esp.exam_id 
AND eps.exam_pid = {$exam_pid}
EOT;
            $result2 = Fn::db()->fetchAll($sql);
            if ($result[0]['cnt'] > $result2[0]['cnt']) {
                $message[] = '有学科未选择试卷';
                return false;
            }
            $sql = <<<EOT
SELECT COUNT(*) AS cnt, eps.subject_id, esp.paper_id 
FROM rd_exam_place_subject eps, rd_exam_subject_paper esp 
WHERE eps.place_id = {$place_id} AND eps.exam_pid = {$exam_pid} 
AND eps.exam_id = esp.exam_id AND esp.paper_id NOT IN (
SELECT paper_id FROM rd_exam_paper) 
GROUP BY esp.paper_id
EOT;
            $result = Fn::db()->fetchAll($sql);
            $subjects = C('subject');
            foreach($result as $key => $val)
            {
                $message[] = '学科['.$subjects[$val['subject_id']].']试卷ID【'.$val['paper_id'].'】不存在';
                
                return false;
                break;
            }
        }
        return true;
    } 
    
    /**
     * 重置学生帐号
     * @param int $uid
     * @param int $new_account
     * @return boolean
     */
    public static function reset_account($uid, $new_account)
    {
        $uid = intval($uid);
        if ($uid <= 0)
        {
            return false;
        }
    
        try
        {
            $update_data = array('account' => $new_account);
            Fn::db()->update('rd_student', $update_data, 'uid = ' . $uid);
            return true;
        }
        catch(Exception $e)
        {
            return false;
        }
    }

    /**
     * 重置学生帐号状态
     * @param int $uid
     * @param int $new_account
     * @return boolean
     */
    public static function reset_status($uid, $new_account)
    {
        $uid = intval($uid);
        if ($uid <= 0)
        {
            return false;
        }
    
        try
        {
            $update_data = array(
                    'account_status' => $new_account,
            );
            
            return Fn::db()->update('rd_student', $update_data, 'uid = ' . $uid);
        }
        catch(Exception $e)
        {
            return false;
        }
    }
}
