<?php
/**
 * 学生成绩单model
 */
class StudentTranscriptModel
{
    private static $_data = array();
    
    /**
     * @param   int     $rule_id
     * @param   int     $exam_id
     * @param   int     $stu_id
     * @return  mixed
     */
    public static function studentTranscriptInfo($rule_id, $exam_id, $stu_id)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $stu_id  = intval($stu_id);
        if (!$rule_id || !$exam_id || !$stu_id)
        {
            return array();
        }
        
        //考试信息
        $exam = self::$_data['exam_info'][$exam_id];
        if (!$exam)
        {
            $exam = ExamModel::get_exam($exam_id);
            if (!$exam)
            {
                return array();
            }
            
            self::$_data['exam_info'][$exam_id] = $exam;
        }
        
        //学生信息
        $stu_info = StudentModel::get_student($stu_id,
            'school_id,last_name,first_name,external_account,exam_ticket');
        if (!$stu_info)
        {
            return array();
        }
        
        $db = Fn::db();
        
        $data = array();
        
        $subject_id = $exam['subject_id'];
        $exam_pid = $exam['exam_pid'];
        $subject_name = C('subject/' . $subject_id);
        
        if (!isset(self::$_data['school_rank'][$exam_id][$stu_info['school_id']]))
        {
            $sql = "SELECT uid, rank
                    FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = ?
                    AND is_school = 1 AND is_class = 0
                    ORDER BY rank ASC";
            $grade_rank = $db->fetchPairs($sql, array($stu_info['school_id']));
            self::$_data['school_rank'][$exam_id][$stu_info['school_id']] = $grade_rank;
        }
        else
        {
            $grade_rank = self::$_data['school_rank'][$exam_id][$stu_info['school_id']];
        }
        
        $sql = "SELECT a.etp_id, paper_id, test_score, ques_id FROM rd_exam_test_paper a
                LEFT JOIN rd_exam_test_paper_question b ON a.etp_id = b.etp_id
                WHERE uid = ? AND exam_id = ?";
        $bind = array($stu_id, $exam_id);
        $etp = $db->fetchRow($sql, $bind);
        if (!$etp)
        {
            return array();
        }
        
        $proportion = self::$_data['distribution_proportion'][$rule_id];
        if (!$proportion)
        {
            $proportion = json_decode(EvaluateRuleModel::get_evaluate_rule($rule_id, 'distribution_proportion'), true);
            if (!$proportion)
            {
                $proportion = array(
                    '高分段' => 27,
                    '中分段' => 73,
                    '低分段' => 100,
                );
            }
            $proportion = array_values($proportion);
        
            self::$_data['distribution_proportion'][$rule_id] = $proportion;
        }
        
        $level_results = 'A';
        $prev_rank = 0;
        $stu_num = count($grade_rank);
        $stu_rank = $grade_rank[$stu_id];
        foreach ($proportion as $k => $rate)
        {
            $rank = $stu_num * $rate / 100;
            if ($prev_rank < $stu_rank && $stu_rank <= $rank)
            {
                break;
            }
            
            $level_results++;
            $prev_rank = $rank;
        }
        
        $sch_name = self::$_data['school_info'][$stu_info['school_id']];
        if (!$sch_name)
        {
            $school = SchoolModel::schoolInfo($stu_info['school_id'], 'school_name');
            $sch_name = $school['school_name'];
            self::$_data['school_info'][$stu_info['school_id']] = $sch_name;
        }
        
        //成绩信息
        $data['results'] = array(
            'exam_name'    => $exam['exam_name'],
            'stu_fullname' => $stu_info['last_name'] . $stu_info['first_name'],
            'stu_schname'  => $sch_name,
            'subject_name' => $subject_name,
            'exam_ticket'  => ($stu_info['external_account'] ? $stu_info['external_account'] : $stu_info['exam_ticket']),
            'test_score'   => $etp['test_score'],
            'level_results'=> $level_results
        );
        
        //试题得分
        //计算学校总体试卷试题得分率
        $level_percent = self::$_data['school_question_level_percent'][$etp['paper_id']][$stu_info['school_id']];
        if (!$level_percent)
        {
            $sql = "SELECT ques_id, ROUND(test_score / total_score * 100) AS percent
                    FROM rd_summary_region_question
                    WHERE exam_id = $exam_id AND region_id = {$stu_info['school_id']}
                    AND is_school = 1";
            $stmt = $db->query($sql);
            while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
            {
                self::calLevelPercent($item['ques_id'], $item['percent'], $level_percent);
            }
            
            self::$_data['school_level_percent'][$etp['paper_id']][$stu_info['school_id']] = $level_percent;
        }
        
        //计算本次学生试题得分率
        $sql = "SELECT ques_id, ROUND(SUM(test_score) / SUM(full_score) * 100) AS percent
                FROM rd_exam_test_result WHERE etp_id = ?
                GROUP BY ques_id";
        $stu_percent = $db->fetchPairs($sql, array($etp['etp_id']));
        
        //计算学生考试试题得分率对应等级
        $ques_ids = explode(',', $etp['ques_id']);
        $data['question'] = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
            5 => array()
        );
        
        foreach ($ques_ids as $index => $ques_id)
        {
            $percent = $stu_percent[$ques_id] > 100 ? 100 : (int) $stu_percent[$ques_id];
            foreach ($level_percent[$ques_id] as $level => $v)
            {
                if ($v[0] <= $percent && $percent <= $v[1])
                {
                    $data['question'][$level][] = $index + 1;
                    break;
                }
            }
        }
        
        ksort($data['question']);
        
        //知识点
        $level_percent = self::$_data['school_knowledge_level_percent'][$etp['paper_id']][$stu_info['school_id']];
        if (!$level_percent)
        {
            $sql = "SELECT knowledge_id, ROUND(test_score / total_score * 100) AS percent
                    FROM rd_summary_region_knowledge
                    WHERE exam_id = $exam_id AND region_id = {$stu_info['school_id']}
                    AND is_school = 1 AND is_parent = 0";
            $stmt = $db->query($sql);
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                self::calLevelPercent($item['knowledge_id'], $item['percent'], $level_percent);
            }
        
            self::$_data['school_knowledge_level_percent'][$etp['paper_id']][$stu_info['school_id']] = $level_percent;
        }
        
        //计算本次学生知识点得分率
        $sql = "SELECT knowledge_id, knowledge_name, ROUND(test_score / total_score * 100) AS percent
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE paper_id = ? AND uid = ? AND is_parent = 0";
        $stu_percent = $db->fetchAssoc($sql, array($etp['paper_id'], $stu_id));
        
        $data['knowledge'] = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
            5 => array()
        );
        
        foreach ($level_percent as $knowledge_id => $levels)
        {
            $percent = $stu_percent[$knowledge_id]['percent'] > 100 
                ? 100 : (int) $stu_percent[$knowledge_id]['percent'];
            foreach ($levels as $level => $v)
            {
                if ($v[0] <= $percent && $percent <= $v[1])
                {
                    $data['knowledge'][$level][] = $stu_percent[$knowledge_id]['knowledge_name'];
                    break;
                }
            }
        }
        
        ksort($data['knowledge']);
        
        //方法策略
        $sql = "SELECT DISTINCT(subject_id) FROM rd_subject_category_subject";
        $subject_ids = $db->fetchCol($sql);
        if (in_array($subject_id, $subject_ids))
        {
            $level_percent = self::$_data['school_method_tactic_level_percent'][$etp['paper_id']][$stu_info['school_id']];
            if (!$level_percent)
            {
                $sql = "SELECT method_tactic_id, ROUND(test_score / total_score * 100) AS percent
                        FROM rd_summary_region_method_tactic
                        WHERE exam_id = $exam_id AND region_id = {$stu_info['school_id']}
                        AND is_school = 1";
                $stmt = $db->query($sql);;
                while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
                {
                    self::calLevelPercent($item['method_tactic_id'], $item['percent'], $level_percent);
                }
            
                self::$_data['school_method_tactic_level_percent'][$etp['paper_id']][$stu_info['school_id']] = $level_percent;
            }
            
            //计算本次学生方法策略得分率
            $sql = "SELECT method_tactic_id, name, ROUND(test_score / total_score * 100) AS percent
                    FROM rd_summary_student_method_tactic ssmt
                    LEFT JOIN rd_method_tactic mt ON mt.id = ssmt.method_tactic_id
                    WHERE paper_id = ? AND uid = ?";
            $stu_percent = $db->fetchAssoc($sql, array($etp['paper_id'], $stu_id));
            
            $data['method_tactic'] = array(
                1 => array(),
                2 => array(),
                3 => array(),
                4 => array(),
                5 => array()
            );
            
            foreach ($level_percent as $method_tactic_id => $levels)
            {
                $percent = $stu_percent[$method_tactic_id]['percent'] > 100 
                    ? 100 : (int) $stu_percent[$method_tactic_id]['percent'];
                foreach ($levels as $level => $v)
                {
                    if ($v[0] <= $percent && $percent <= $v[1])
                    {
                        $data['method_tactic'][$level][] = $stu_percent[$method_tactic_id]['name'];
                        break;
                    }
                }
            }
            
            ksort($data['method_tactic']);
        }
        //信息提取
        else if ($subject_id == 3)
        {
            $level_percent = self::$_data['school_group_type_level_percent'][$etp['paper_id']][$stu_info['school_id']];
            if (!$level_percent)
            {
                $sql = "SELECT group_type_id, ROUND(test_score / total_score * 100) AS percent
                        FROM rd_summary_region_group_type
                        WHERE exam_id = $exam_id AND region_id = {$stu_info['school_id']}
                        AND is_school = 1 AND is_parent = 0";
                $stmt = $db->query($sql);;
                while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
                {
                    self::calLevelPercent($item['group_type_id'], $item['percent'], $level_percent);
                }
            
                self::$_data['school_group_type_level_percent'][$etp['paper_id']][$stu_info['school_id']] = $level_percent;
            }
            
            //计算本次学生信息提取方式得分率
            $sql = "SELECT group_type_id, group_type_name, ROUND(test_score / total_score * 100) AS percent
                    FROM rd_summary_student_group_type ssgt
                    LEFT JOIN rd_group_type gt ON gt.id = ssgt.group_type_id
                    WHERE paper_id = ? AND uid = ? AND is_parent = 0";
            $stu_percent = $db->fetchAssoc($sql, array($etp['paper_id'], $stu_id));
            $data['group_type'] = array(
                1 => array(),
                2 => array(),
                3 => array(),
                4 => array(),
                5 => array()
            );
            
            foreach ($level_percent as $group_type_id => $levels)
            {
                $percent = $stu_percent[$group_type_id]['percent'] > 100 
                    ? 100 : (int) $stu_percent[$group_type_id]['percent'];
                foreach ($levels as $level => $v)
                {
                    if ($v[0] <= $percent && $percent <= $v[1])
                    {
                        $data['group_type'][$level][] = $stu_percent[$group_type_id]['group_type_name'];
                        break;
                    }
                }
            }
            
            ksort($data['group_type']);
        }
        
        return $data;
    }
    
    /**
     * 计算对应等级百分比区间
     * @param   int     $item_id
     * @param   int     $percent
     * @param   array   $level_percent
     */
    public static function calLevelPercent($item_id, $percent, &$level_percent)
    {
        $left3 = $percent - 10;
        $left3 = $left3 < 0 ? 0 : $left3;
        $right3 = $percent + 10;
        $right3 = $right3 > 100 ? 100 : $right3;
        
        //第1等级
        $left = $right3 + 22;
        if ($left <= 100)
        {
            $level_percent[$item_id][1] = array($left, 100);
        }
        
        //第2等级
        $left = $right3 + 1;
        if ($left <= 100)
        {
            $right = $left + 20;
            $right = $right > 100 ? 100 : $right;
            $level_percent[$item_id][2] = array($left, $right);
        }
        
        //第3等级
        $level_percent[$item_id][3] = array($left3, $right3);
        
        //第4等级
        $right = $left3 - 1;
        if ($right >= 0)
        {
            $left = $right - 20;
            $left = $left < 0 ? 0 : $left;
            $level_percent[$item_id][4] = array($left, $right);
        }
        
        //第5等级
        $right = $left3 - 22;
        if ($right >= 0)
        {
            $level_percent[$item_id][5] = array(0, $right);
        }
    }
}