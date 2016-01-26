<?php
/**
 * 考试系统--考生考试试卷--数据层ExamTestPaperModel
 * @file    ExamTestPaper.php
 * @author  BJP
 * @final   2015-06-19
 */
class ExamTestPaperModel
{
    /**
     * 获取学生考卷
     * @param    int            考试场次id(place_id)
     * @param    int            学生id(uid)
     * @param    string        自定义获取字段
     * @return  array
     */
    public static function get_stduent_test_paper($place_id, $uid, 
        $select_items = null, $flag = '0')
    {
        $where = array(
            "uid = $uid",
            "place_id = $place_id"
        );
        if (!is_null($flag))
        {
            if (is_numeric($flag))
            {
                $where[] = "etp_flag = $flag";
            }
            else if(is_array($flag))
            {
                $where[] = 'etp_flag ' . $flag[0] . ' ' . $flag[1];
            }
        }
        $where_sql = implode(' AND ', $where);

        $select_item = is_null($select_items) ? 'etp_id, paper_id, etp_flag, full_score, subject_id' : $select_items;
        $sql = <<<EOT
SELECT {$select_item} FROM rd_exam_test_paper WHERE {$where_sql}
EOT;
        $rows = Fn::db()->fetchAll($sql);
        return $rows;
    }


    /**
     * 获取学生考卷
     *
     * @param    int            考试场次id(place_id)
     * @param    int            学生id(uid)
     * @param    string        自定义获取字段
     * @return  array
     */
    public static function get_student_test_papers($place_id, $uid, 
        $select_items = null, $flag = '0',$exam_pid)
    {

        $where = array(
                "uid = $uid",
                "place_id = $place_id",
                "exam_pid = $exam_pid"
        );
        if (!is_null($flag))
        {
            if (is_numeric($flag))
            {
                $where[] = "etp_flag = $flag";
            }
            else if(is_array($flag))
            {
                $where[] = 'etp_flag ' . $flag[0]  . ' ' . $flag[1];
            }
        }

        $where_sql = implode(' AND ', $where);
        $select_item = is_null($select_items) ? 'etp_id, paper_id, etp_flag, full_score, subject_id' : $select_items;

        $sql = <<<EOT
SELECT {$select_item} FROM rd_exam_test_paper WHERE {$where_sql}
EOT;
        $rows = Fn::db()->fetchAll($sql);
        return $rows;
    }
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
    public static function get_test_paper_list($query, $page = 1, 
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
                                $where[] = "etp_id IN ({$tmpStr})";
                            } else {
                                $where[] = 'etp_id = ?';
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
                        case 'uid':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "uid IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'uid = ?';
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
                        case 'paper_id':
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "paper_id IN ({$tmpStr})";
                            }
                            else
                            {
                                $where[] = 'paper_id = ?';
                                $bind[] = intval($val);
                            }
                            break;
                        case 'subject_id' :
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
                        case 'etp_flag' :
                            if (is_array($val))
                            {
                                $tmpStr = array();
                                foreach ($val as $k => $v)
                                {
                                    $tmpStr[] = '?';
                                    $bind[] = intval($v);
                                }
                                $tmpStr = implode(', ', $tmpStr);
                                $where[] = "etp_flag IN ({$tmpStr})";
                            }
                            else
                            {
                                if(intval($val) == '-1')
                                {
                                    $where[] = ' (etp_flag = -1  OR etp_id IN (SELECT etp_id FROM rd_exam_test_paper_invalid_record))';

                                }
                                else if(intval($val) == '2')
                                {
                                    $where[] = ' (etp_flag = 2 AND etp_id NOT IN (SELECT etp_id FROM rd_exam_test_paper_invalid_record ))';

                                }
                                else
                                {
                                    $where[] = 'etp_flag = ?';
                                    $bind[] = intval($val);
                               }
                            }
                            break;
                        case 'test_score':
                            if (is_array($val))
                            {
                                foreach ($val as $k=>$v)
                                {
                                    $where[] = "test_score {$k} ?";
                                    $bind[] = $v;
                                }
                            }
                            else
                            {
                                $where[] = 'test_score = ?';
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

            $sql = "SELECT {$select_what} FROM rd_exam_test_paper {$where} {$order_by} {$group_by} {$limit}";

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

    //==================成绩作废理由==============================
    /**
     *
     * 通过 单条 记录
     * @param $id
     * @param $item 待提取字段信息
     * @return mixed
     */
    public static function get_etp_invalid_record($etp_id = 0, $item = '*')
    {
        if ($etp_id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_exam_test_paper_invalid_record WHERE etp_id = {$etp_id}
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
     * 添加数据
     * @param array $data
     * @return boolean
     */
    public static function insert_invalid_record($data)
    {
        $res = Fn::db()->insert('rd_exam_test_paper_invalid_record', $data);
        return $res > 0;
    }

    /**
     * 更新数据
     * mixed $etp_id
     * array $data

     * return boolean
     */
    public static function update_invalid_record($etp_id, $data)
    {
        try
        {
            $etp_id = intval($etp_id);
            if (is_array($etp_id))
            {
                Fn::db()->update('rd_exam_test_paper_invalid_record', $data,
                    "etp_id = $ept_id");
            }
            else
            {
                Fn::db()->update('rd_exam_test_paper_invalid_record', $data,
                    "etp_id = $etp_id");
            }
            return true;
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    /**
     * 删除数据
     * mixed $etp_id
     * array $data

     * return boolean
     */
    public static function delete_invalid_record($etp_id)
    {
        try
        {
            $etp_id = intval($etp_id);
            if (is_array($etp_id))
            {
                Fn::db()->delete('rd_exam_test_paper_invalid_record',
                    "etp_id = $etp_id");
            }
            else
            {
                Fn::db()->delete('rd_exam_test_paper_invalid_record',
                    "etp_id = $etp_id");
            }
            return true;
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    /**
     * 获取一个考卷信息
     *
     * @param   int     考卷ID(etp_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_test_paper_by_id($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }

        $sql = <<<EOT
SELECT {$item} FROM rd_exam_test_paper WHERE etp_id = {$id}
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
     * 设定学生考卷（每科考试随机选一份试卷）
     *
     * @param    int            考试场次id(place_id)
     * @param    int            学生id
     * @return  array
     */
    public static function set_student_test_paper($place_id, $uid, 
        $is_trans = true)
    {
        $subjects = ExamPlaceModel::get_exam_place_subject($place_id);

        if (empty($subjects)) return FALSE;

        $test_papers = array();
        $no_paper_subjects = false;
        foreach ($subjects as $row)
        {
            $sql = <<<EOT
SELECT paper_id, exam_pid FROM rd_exam_subject_paper WHERE exam_id = ?
ORDER BY RAND() LIMIT 1
EOT;

            $arr = Fn::db()->fetchRow($sql, $row['exam_id']);
            if (!isset($arr['paper_id']))
            {
                $no_paper_subjects = true;
                break;
            }

            $exam = ExamModel::get_exam_by_id($row['exam_id'], 'total_score, class_id, grade_id');
            $total_score = $exam['total_score'];
            $class_id = $exam['class_id'];
            $grade_id = $exam['grade_id'];

            $test_papers[] = array(
                    'exam_pid'   => $row['exam_pid'],
                    'exam_id'     => $row['exam_id'],
                    'uid'         => $uid,
                    'paper_id'     => $arr['paper_id'],
                    'place_id'   => $place_id,
                    'subject_id' => $row['subject_id'],
                    'full_score' => $total_score,
                    'test_score' => '0.00',
                    'etp_flag'      => 0,
                    'ctime'      => time()
            );
        }

        if ($no_paper_subjects) {
            return false;
        }

        $db = Fn::db();
        if ($is_trans)
        {
            $db->beginTransaction();
        }

        // save
        $insert_ids = array();
        foreach ($test_papers as $val)
        {
            $sql = "SELECT etp_id FROM rd_exam_test_paper
                    WHERE exam_pid = {$val['exam_pid']} AND exam_id = {$val['exam_id']}
                    AND uid = {$val['uid']} AND paper_id = {$val['paper_id']}
                    AND place_id = {$val['place_id']} AND subject_id = {$val['subject_id']} ";
            $res1 = $db->fetchRow($sql);
            if(isset($res1['etp_id']) && $res1['etp_id'] > 0)
            {
                $res = $db->update('rd_exam_test_paper', $val, 
                    'etp_id = '.  $res1['etp_id']);
                $etp_id = $res1['etp_id'];
            }
            else
            {
                $res = $db->insert('rd_exam_test_paper', $val);
                //添加试卷试题
                $etp_id = $db->lastInsertId('rd_exam_test_paper', 'etp_id');
            }

            if ($etp_id)
            {
                $etp_id_where =  "etp_id = $etp_id" ;
                $db->delete('rd_exam_test_paper_question', $etp_id_where);

                $sql = "SELECT q.ques_id,q.type
                        FROM rd_exam_question eq
                        LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
                        LEFT JOIN rd_relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id=$grade_id
                        AND rc.class_id=$class_id WHERE eq.paper_id={$val['paper_id']}
                        ORDER BY rc.difficulty DESC,q.ques_id ASC";

                $result = $db->fetchAll($sql);
                if ($val['subject_id'] == 3)
                {
                    $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
                }
                else
                {
                    $types = array('1','2','3','0','10','14','15','11');
                }

                $paper_array = array();

                foreach ($types as $type)
                {
                    foreach ($result as $key => $row)
                    {
                        if ($row['type'] != $type)
                        {
                            continue;
                        }

                        $paper_array[] = $row['ques_id'];
                        unset($result[$key]);
                    }
                }

                $ques_ids = implode(',',$paper_array);

                $res = $db->insert('rd_exam_test_paper_question', 
                    array('etp_id' => $etp_id, 'ques_id' => $ques_ids));
            }

            if (!$res)
            {
                break;
            }
        }

        if ($is_trans && $res)
        {
            $res = $db->commit();
            if (!$res)
            {
                $db->rollBack();
            }
        }
        else if ($is_trans && !$res)
        {
            $db->rollBack();
            return false;
        }
        return $res;
    }
}
