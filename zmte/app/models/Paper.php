<?php
/**
 * 自助组卷模型 PaperModel
 * @file    Paper.php
 * @author  BJP
 * @final   2015-06-22
 */
class PaperModel
{
    /**
     * 获取某条试卷信息
     *
     * @param   int     $id 试卷ID(paper_id)
     * @param   string  $item 字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   失败返回false, 成功返回关联数组
     */
    public static function get_paper_by_id($id = 0, $item = '*')
    {
        if ($id == 0) 
        {
            return FALSE;
        }
        $sql = "SELECT $item FROM rd_exam_paper WHERE paper_id = $id";
        return Fn::db()->fetchRow($sql);
    }

    /**
     * 获取试卷信息(分页)
     *
     * @param   string $item  字段列表(多个字段用逗号分割，默认取全部字段)
     * @param   array  $param 查询条件
     * @param   int $start 起始条
     * @param   int $number 数量
     * @return  mixed 失败返回false, 成功返回关联数组
     */
    public static function get_papers($item = '*', $param, $start = 0, 
        $number = 10)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $sql = "SELECT $item FROM rd_exam_paper";
        if ($where_sql)
        {
           $sql .= " WHERE $where_sql";
        }
        $sql .= " ORDER BY paper_id DESC";
        $sql .= " LIMIT $number OFFSET $start";
        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 获取试卷信息
     *
     * @param   string $item  字段列表(多个字段用逗号分割，默认取全部字段)
     * @param   array  $param 查询条件
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_all_papers($item = '*', $param)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $sql = <<<EOT
SELECT {$item} FROM rd_exam_paper
EOT;
        if ($where_sql)
        {
            $sql .= " WHERE " . $where_sql;
        }
        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 获取试卷数量
     *
     * @param   array  $param 查询条件
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function count_papers($param)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $sql = <<<EOT
SELECT COUNT(*) FROM rd_exam_paper
EOT;
        if ($where_sql)
        {
            $sql .= ' WHERE ' . $where_sql;
        }
        return Fn::db()->fetchOne($sql, $bind);
    }

    /**
     * 插入试卷信息
     *
     * @param array $paper 试卷信息
     * @return 成功返回paper_id, 失败返回false
     */
    public static function insert_paper($paper)
    {
        $db = Fn::db();
        $v = $db->insert('rd_exam_paper', $paper);
        if ($v)
        {
            return $db->lastInsertId('rd_exam_paper', 'paper_id');
        }
        else
        {
            return false;
        }
    }

    /**
     * 更新试卷信息
     *
     * @param array $paper_id 试卷ID
     * @param array $paper 试卷信息
     * @return boolen 成功返回true, 失败返回false
     */
    public static function update_paper($paper_id, $paper)
    {
        $v = Fn::db()->update('rd_exam_paper', $paper, "paper_id = $paper_id");
        return $v > 0;
    }
    
    /**
     * 更新试卷试题
     * @param array $paper_id 试卷ID
     * @return boolen 成功返回true, 失败返回false
     */
    public static function update_paper_question($paper_id)
    {
        /* 更新外部新试卷信息 */
        $paper_id = (int)$paper_id;
        if (!$paper_id)
        {
            return false;
        }
        
        /* 查询试卷信息 */
        $paper = self::get_paper_by_id($paper_id, 'exam_id,paper_id,admin_id,question_sort');
        if (!$paper)
        {
            return false;
        }
        
        $exam = ExamModel::get_exam($paper['exam_id'], 'exam_id,exam_pid,grade_id,class_id');
        if (!$exam)
        {
            return false;
        }
        
        $db = Fn::db();
        
        /* 判定是否为外部试卷 */
        if ($paper['admin_id'] > 0) 
        {
            $questions = json_decode($paper['question_sort'], true);
            $question_difficulty = array();
            $qtype_ques_num = array_fill(0, count(C('qtype')), '0');
        
            if (!$db->beginTransaction())
            {
                return false;
            }
            
            /* 清除exam_question原有信息 */
            $db->delete('rd_exam_question', 'exam_id =? AND paper_id = ?', 
                array($paper['exam_id'], $paper_id));
        
            if (count($questions) > 0) 
            {
                foreach ($questions as $ques_id) 
                {
                    $sql = "SELECT q.ques_id,q.type,rc.difficulty FROM
                            rd_question q 
                            LEFT JOIN rd_relate_class rc ON q.ques_id=rc.ques_id
                            WHERE q.ques_id={$ques_id} AND rc.grade_id={$exam['grade_id']} 
                            AND rc.class_id={$exam['class_id']}";
                    $question = $db->fetchRow($sql);
                    if (empty($question)) 
                    {
                        $db->rollBack();
                        throw new Exception('当前试卷中存在不属于当前考试期次年级的试题！请检查试题！');
                    }
        
                    /* 补全exam_question信息 */
                    $data = array();
                    $data['paper_id'] = $paper_id;
                    $data['exam_id'] = $paper['exam_id'];
                    $data['ques_id'] = $ques_id;
                    $db->insert('rd_exam_question', $data);
        
                    /* 试题难易度 */
                    $question_difficulty[] = $question['difficulty'];
                    /* 各个类型试题数量 */
                    $qtype_ques_num[$question['type']]++;
                }
            }
        
            /* 补全exam_pager信息 */
            $data = array();
            $data['exam_id'] = $paper['exam_id'];
            $data['difficulty'] = array_sum($question_difficulty) / count($question_difficulty);
            $data['qtype_ques_num'] = implode(',', $qtype_ques_num);
            PaperModel::update_paper($paper_id, $data);
            
            $flag = $db->commit();
            if (!$flag)
            {
                $db->rollBack();
            }
            
            //如果是mini测试卷,则更新试卷统计
            if (ExamModel::is_mini_test($exam['exam_pid']))
            {
                $sql = "SELECT exam_pid FROM rd_exam_subject_paper 
                        WHERE paper_id = $paper_id AND exam_id = " . $exam['exam_id'];
                if (Fn::db()->fetchOne($sql))
                {
                    SummaryModel::summary_paper($exam['exam_pid'], 0, $paper_id, true);
                }
            }
            
            return $flag;
        }
    }

    /**
     * 添加试题与试卷关联
     *
     * @param array $relate 试卷与试题对应二维数组
     * @return boolen 成功返回true, 失败返回false
     */
    public static function add_paper_relate($relate)
    {
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            foreach ($relate as $v)
            {
                $db->insert('rd_exam_question', $v);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }

    /**
     * 删除试卷信息
     *
     * @param array $paper_id 试卷ID
     * @return 成功返回true, 失败返回false
     */
    public static function delete_paper($paper_id)
    {
        $v = Fn::db()->delete('rd_exam_paper', "paper_id = $paper_id");
        return $v > 0;
    }


    /**
     * 移除试题与试卷关联
     *
     * @param array $paper_id 试卷ID
     * @return boolen 成功返回true, 失败返回false
     */
    public static function delete_paper_relate($paper_id)
    {
        $v = Fn::db()->delete('rd_exam_question', "paper_id = $paper_id");
        return $v > 0;
    }
}
