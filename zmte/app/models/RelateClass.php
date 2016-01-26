<?php
/**
 * 试题关联属性--数据层 RelateClassModel
 * @file    RelateClass.php
 * @author  BJP
 * @final   2015-06-19
 */
class RelateClassModel
{
    /**
     * 通过 记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_relate_class_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = '*', 
        $group_by = null, $where_1)
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
                                $where[] = "rc.id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'rc.id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'ques_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "rc.ques_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'rc.ques_id = ?';
                                $bind[] = intval($val);
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
                                $where[] = "rc.grade_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'rc.grade_id = ?';
                                $bind[] = intval($val);
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
                                $where[] = "rc.class_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'rc.class_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'subject_type':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "rc.subject_type IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'rc.subject_type = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'difficulty':
                            if (is_array($val))
                            {
                                foreach ($val as $k=>$v)
                                {
                                    $where[] = "rc.difficulty {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'rc.difficulty = ?';
                                $bind[] = $val;
                            }
                            break;
                        case 'copy_difficulty':
                            if (is_array($val))
                            {
                                foreach ($val as $k=>$v)
                                {
                                    $where[] = "rc.copy_difficulty {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'rc.copy_difficulty = ?';
                                $bind[] = $val;
                            }
                            break;
                        default:
                            break;                    
                    }
                }
            }
            
            //$select_what = is_string($select_what) ? (array) $select_what : $select_what;
            //$select_what = count($select_what) ? implode(', ', $select_what) : '*';
            
            $where = count($where) ? ("WHERE " . implode(' AND ', $where)) : '';
     
            if ($where <> '')
            {
                $where = $where . ' AND ' . $where_1;
            }
            else 
            {
                $where = ' WHERE  ' . $where_1;
            }
            
            $order_by = !is_null($order_by) ? 'ORDER BY rc.' . $order_by : '';
            $group_by = !is_null($group_by) ? $group_by : '';
            
            $limit = '';
            $page = intval($page);
            if ($page > 0) {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            if ($where != '')
            {
                $sql = <<<EOT
SELECT {$select_what} FROM rd_relate_class rc, rd_question q
{$where} AND q.ques_id = rc.ques_id {$order_by} {$group_by} {$limit}
EOT;
            }
            else 
            {
                $sql = <<<EOT
SELECT {$select_what} FROM rd_relate_class rc, rd_question q
WHERE q.ques_id = rc.ques_id {$order_by} {$group_by} {$limit}
EOT;
            }
       
            /*
            //根据不同权限获取
            $this->load->model('admin/cpuser_model');
            $is_super_user = $this->session->userdata('is_super');
            if (!$is_super_user && $this->cpuser_model->is_action_type_self('question', 'r')) {
                $c_uid = $this->session->userdata('admin_id');
                $where = $where == '' ? 'where ' : $where . '  and ';
                $sql = "SELECT {$select_what} FROM rd_relate_class rc, {pre}question q
                         {$where} q.ques_id=rc.ques_id and q.admin_id={$c_uid}  
                         {$order_by} {$group_by} {$limit}";
                
            } else if(!$is_super_user && $this->cpuser_model->is_action_type_subject('question', 'r')) {
                $c_subject_id = $this->session->userdata('subject_id');
                $where = $where == '' ? 'where ' : $where . '  and ';
                $sql = "SELECT {$select_what} FROM rd_relate_class rc, {pre}question q 
                        {$where}  q.ques_id=rc.ques_id and q.subject_id in ({$c_subject_id})
                        {$order_by} {$group_by} {$limit}";
                
            } else {
                $sql = "SELECT {$select_what} FROM rd_relate_class rc 
                        {$where} {$order_by} {$group_by} {$limit}";
                
            }
     
            */
            
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
    public static function count_list($query, $where)
    {
        $result = self::get_relate_class_list($query, null, null, null, 
            'COUNT(*) AS total',null,$where);
        return count($result) ? $result[0]['total'] : 0;
    }

    /**
     * 更新数据
     */
    public static function update($id, $update_data)
    {
        try
        {
            $res = Fn::db()->update('rd_relate_class', $update_data, 'id = ' . intval($id));
            if (!$res)
            {
                return false;
            }
        }
        catch (Exception $e)
        { 
            return false;
        }
        return true;
    }
    
    /**
     * 恢复默认难易度
     * @param $id 
     */
    public static function recover_default_difficulty($id)
    {
        $sql = <<<EOT
UPDATE rd_relate_class rc SET difficulty = rc.copy_difficulty WHERE id= {$id}
EOT;
        $res = Fn::db()->exec($sql);
        return $res > 0;
    }
    
    /**
     * 系统更新 考试期次 试题难易度
     * 关联表：
     *     rd_exam_question_stat rd_relate_class 
     * 更新字段：
     *     rd_relate_class->difficulty
     */
    public static function system_cal_question_difficulty()
    {
        //获取答对率（某题被答对数/答题总人数）
        $sql = array();
        $sql[] = <<<EOT
SELECT rc.id, rc.difficulty, rc.copy_difficulty, 
SUM(eqs.student_amount) AS sum_student, 
SUM(eqs.right_amount)/SUM(eqs.student_amount) AS right_percent
EOT;
        $sql[] = <<<EOT
FROM rd_exam_question_stat eqs, rd_relate_class rc
EOT;
        $sql[] = <<<EOT
WHERE rc.ques_id = eqs.ques_id AND rc.grade_id = eqs.grade_id AND rc.class_id = eqs.class_id AND rc.subject_type = eqs.subject_type
EOT;
        $sql[] = <<<EOT
GROUP BY rc.id
EOT;
        $db = Fn::db();
        
        $stats = $db->fetchAll(implode(' ', $sql));
        
        $update_data = array();
        foreach ($stats as $stat)
        {
            $id = $stat['id'];
            $copy_difficulty = $stat['copy_difficulty'];
            $sum_student = $stat['sum_student'];
            $right_percent = $stat['right_percent'] * 100;
    
            //计算新的difficulty
            $a = 0;
            if ($sum_student >= 10 && $sum_student < 200) $a = 1;
            if ($sum_student >= 200 && $sum_student < 2100) $a = 10;
            if ($sum_student >= 2100 && $sum_student < 3100) $a = 20;
            if ($sum_student >= 3100 && $sum_student < 3600) $a = 30;
            if ($sum_student >= 3600 && $sum_student < 4000) $a = 40;
            if ($sum_student >= 4000 && $sum_student < 4700) $a = 50;
            if ($sum_student >= 4700 && $sum_student < 5900) $a = 60;
            if ($sum_student >= 5900 && $sum_student < 8100) $a = 70;
            if ($sum_student >= 8100 && $sum_student < 12000) $a = 80;
            if ($sum_student >= 12000 && $sum_student < 20000) $a = 90;
            if ($sum_student >= 20000) $a = 99;
                
            $new_difficulty = round(($right_percent * $a + $copy_difficulty * (100 - $a)) / 100, 1);
    
            $update_data[] = array(
                    'id'            => $id,
                    'difficulty'    => $new_difficulty
            );
        }
    
        if (count($update_data)) {
            // 开启事务
            $db->beginTransaction();
            foreach ($update_data as $data)
            {
                $db->update('rd_relate_class', 
                    array('difficulty' => $data['difficulty']), 
                    "id = " . $data['id']);
            }
            
            //结束事务
            $db->commit();
        }
    }
    
    /**
     * 一键恢复 试题初始难易度
     * 关联表：
     *     rd_exam_question_stat rd_relate_class 
     * 更新字段：
     *     rd_relate_class->difficulty
     * 流程：
     *     将copy_difficulty->difficulty
     */
    public static function recover_difficulty()
    {
        try
        {
            // 开启事务
            $res = Fn::db()->exec("UPDATE rd_relate_class SET difficulty=copy_difficulty");
            if (!$res)
            {
                return false;
            }
        }
        catch(Exception $e)
        {
            return false;
        }
        return true;
    }
    
    /**
     * 手动更新试题难易度
     * 关联表:
     *     rd_relate_class
     * 关联字段
     *     difficulty
     */
    public static function manual_update_question_difficulty($id, $difficulty)
    {
        try
        {
            $res = Fn::db()->update('relate_class', array('difficulty' => $difficulty), "id = $id");
            if (!$res)
            {
                throw new Exception('更新难易度失败');
            }
        }
        catch (Exception $e)
        {
            return false;
        }
        
        return true;
    }
}
