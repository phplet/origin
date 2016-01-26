<?php 
/**
 * 考试相关计算统计(补齐未做试题、计算试题得分、计算试卷得分、统计考试期次试题答题状况、更新考试试题分数)
 * @author   TCG
 * @final    2015-01-26 09:30
 */
class ExamstatModel
{
    private static $_qchildren;//考试试题子题
    private static $_option;   //试题选项
    private static $_qtype;    //试题类型
    
    /**
     * 获取需要计算考试结果的考试期次
     */
    public static function examList()
    {
        $sql = "SELECT id, exam_pid FROM rd_cron_task_exam_result 
                WHERE status = 0 LIMIT 5";
        return Fn::db()->fetchAll($sql);
    }
    
    /**
     * 更新考试结果计算的状态
     * @param   int|array $id        需要更新的考试记录
     * @param   int       $status    更新的状态
     * @return  boolean
     */
    public static function updateCalculateExamResultStatus($id, $status)
    {
        if (!$id || (!is_array($id) && !Validate::isInt($id)))
        {
            return false;
        }
        
        if (is_array($id))
        {
            $where = "id IN (" . implode(',', $id) . ")";
        }
        else
        {
            $where = "id = $id";
        }
        
        return Fn::db()->update("rd_cron_task_exam_result", 
                array('status'=> intval($status)), $where);
    }
    
    /**
     * 补全学生考试未分配试卷
     */
    public static function completionExamTestPaper($exam_pid)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $exam = ExamModel::get_exam($exam_pid);
        if (!$exam)
        {
            return false;
        }
        
        $sql = "SELECT esp.exam_id, esp.paper_id, ep.question_sort, esp.subject_id 
                FROM rd_exam_paper ep
                LEFT JOIN rd_exam_subject_paper esp ON esp.paper_id = ep.paper_id
                WHERE esp.exam_pid = $exam_pid";
        $stmt = $db->query($sql);
        $paperlist = array();
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if (!$item['question_sort'])
            {
                $sql = "SELECT q.ques_id, q.type FROM rd_exam_question eq
                        LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
                        LEFT JOIN rd_relate_class rc ON rc.ques_id=q.ques_id
                        AND rc.grade_id={$exam['grade_id']} AND rc.class_id={$exam['class_id']}
                        WHERE eq.paper_id={$item['paper_id']}
                        ORDER BY rc.difficulty DESC,q.ques_id ASC";
                $result = $db->fetchPairs($sql);
                if ($item['subject_id'] == 3)
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
                    foreach ($result as $ques_id => $q_type)
                    {
                        if ($q_type != $type)
                        {
                            continue;
                        }
                        
                        unset($result[$ques_id]);
                        $paper_array[] = $ques_id;
                    }
                }
                
                $item['question_sort'] = implode(',', $paper_array);
            }
            else
            {
                $item['question_sort'] = implode(',', json_decode($item['question_sort'], true));
            }
            
            $paperlist[$item['exam_id']][$item['paper_id']] = $item['question_sort'];
        }
        
        if (!$paperlist)
        {
            return false;
        }
        
        $sql = "SELECT eps2.exam_id, eps2.subject_id, e.total_score, eps.place_id, eps.uid 
                FROM rd_exam_place_student eps
                LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id
                LEFT JOIN rd_exam_place_subject eps2 ON eps2.place_id = eps.place_id
                LEFT JOIN rd_exam e ON e.exam_id = eps2.exam_id
                WHERE ep.exam_pid = $exam_pid AND NOT EXISTS (
                    SELECT uid FROM rd_exam_test_paper etp
                    WHERE etp.uid = eps.uid AND etp.place_id = etp.place_id
                )";
        $stmt = $db->query($sql);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $paper = $paperlist[$item['exam_id']];
            if (!$paper)
            {
                continue;
            }
            
            $paper_ids = array_keys($paper);
            $paper_id = $paper_ids[rand(1, count($paper_ids)) - 1];
            $param = array(
	                'exam_pid'   => $exam_pid,
	                'exam_id'	 => $item['exam_id'],
	                'uid'		 => $item['uid'],
	                'paper_id'	 => $paper_id,
	                'place_id'   => $item['place_id'],
	                'subject_id' => $item['subject_id'],
	                'full_score' => $item['total_score'],
	                'test_score' => '0.00',
	                'etp_flag' 	 => 0,
	                'ctime'      => time()
	        );
            
            $db->insert('rd_exam_test_paper', $param);
            $etp_id = $db->lastInsertId('rd_exam_test_paper', 'etp_id');
            
            $db->insert('rd_exam_test_paper_question',
                array('etp_id' => $etp_id, 'ques_id'=>$paper[$paper_id]));
        }
        
        return $etp_id ? true : false;
    }
    
    /**
     * 补全学生考试未做的题
     * @param   int     $exam_pid    考试期次
     * @param   int     $uid         学生uid
     * @return  boolean
     */ 
    public static function completionExamQuestion($exam_pid, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        //获取考试中学生未做的试题
        $sql = "SELECT etp.exam_id, etp.etp_id, etp.uid, etp.paper_id, eq.ques_id
                FROM rd_exam_test_paper etp
                LEFT JOIN rd_exam_question eq ON eq.paper_id = etp.paper_id
                WHERE etp.exam_pid = $exam_pid
                " . ($uid ? " AND etp.uid = $uid" : '') . "
                AND NOT EXISTS 
                (
                    SELECT etr.ques_id FROM rd_exam_test_result etr 
                    WHERE etr.etp_id = etp.etp_id AND eq.ques_id = etr.ques_id
                )
                ";
        
        $data = $db->fetchAll($sql);
        if (!$data)
        {
            return false;
        }
        
        $paper_id = array();
        $ques_ids = array();
        foreach ($data as $item)
        {
            if (!in_array($item['paper_id'], $paper_id))
            {
                $paper_id[] = $item['paper_id'];
            }
            
            if (!in_array($item['ques_id'], $ques_ids))
            {
                $ques_ids[] = $item['ques_id'];
            }
        }
        
        if (!$paper_id || !$ques_ids)
        {
            return false;
        }
        
    	$paper_detail = self::paperDetail($paper_id);  //试卷信息
    	self::question($ques_ids);    //试题信息
    	if (!$paper_detail)
    	{
    	    return false;
    	}
    	
    	foreach ($data as $item) 
    	{
    	    	
    	    @list($exam_id, $etp_id, $uid, $paper_id, $ques_id) = array_values($item);
    	    
    	    $q_type = self::$_qtype[$ques_id];
    	    $ques_info = $paper_detail[$paper_id][$q_type][$ques_id];
    	    if (!$ques_info)
    	    {
    	        continue;
    	    }
    	    
            $tmp_data = array(
                    'etp_id' 		=> $etp_id,
                    'exam_pid' 		=> $exam_pid,
                    'exam_id' 		=> $exam_id,
                    'uid' 			=> $uid,
                    'paper_id' 		=> $paper_id,
                    'ques_id' 		=> $ques_id,
                    'ques_index' 	=> $ques_info['ques_index'],
                    'sub_ques_id'   => 0, 
                    'ques_subindex' => 0,
                    'option_order' 	=> '',
                    'answer' 		=> '',
                    'full_score' 	=> $ques_info['full_score'],
                    'test_score' 	=> 0,
            );
    	    
            //非题组
            if (in_array($q_type, array(1, 2, 3, 7, 9, 11, 14)))
            {
                if (!empty(self::$_option[$ques_id]))
                {
                    shuffle(self::$_option[$ques_id]);
                    $tmp_data['option_order'] = implode(',', array_keys(self::$_option[$ques_id]));
                }
                
                $db->replace("rd_exam_test_result", $tmp_data);
            }
            //解答题特殊处理
            else if ($q_type == 10)
            {
                if (isset(self::$_qchildren[$ques_id])
                    && self::$_qchildren[$ques_id])
                {
                    $question_score = $paper_detail[$paper_id]['question_score'];
                    $sub_index = 1;
                    foreach (self::$_qchildren[$ques_id] as $k => $sub_ques_id)
                    {
                        $tmp_data['ques_subindex'] = $sub_index;
                        $tmp_data['sub_ques_id'] = $sub_ques_id;
                        $tmp_data['full_score'] = $question_score[$ques_id][$k];
                        $insert_data[] = $tmp_data;
            
                        $sub_index++;
                    }
                }
                else
                {
                    $insert_data[] = $tmp_data;
                }
            }
            elseif (in_array($q_type, array(0, 4, 5, 6, 8, 12, 13)))
            {
                // 题组子题
                $children = self::$_qchildren[$ques_id];
                if (!$children)
                {
                    continue;
                }
                
                $sub_index = 1;
                $left_score = $ques_info['full_score'];
                $child_num = count($children);
                
                foreach ($children as $sub_ques_id)
                {
                    $tmp_data['ques_subindex'] = $sub_index;
                    $tmp_data['sub_ques_id'] = $sub_ques_id;
                    
                    if (!empty(self::$_option[$sub_ques_id]))
                    {
                        shuffle(self::$_option[$sub_ques_id]);
                        $tmp_data['option_order'] = implode(',', array_keys(self::$_option[$sub_ques_id]));
                    }
                    else
                    {
                        $tmp_data['option_order'] = '';
                    }
            
                    if ($sub_index < $child_num)
                    {
                        $tmp_data['full_score'] = round($ques_info['full_score']/$child_num, 2);
                        $left_score -= $tmp_data['full_score'];
                    }
                    else
                    {
                        $tmp_data['full_score'] = $left_score;
                    }
            
                    $sub_index++;
                    	
                    $db->replace("rd_exam_test_result", $tmp_data);
                }
            }
    	}
    }
    
    /**
     * 计算试题得分
     * @param   int     $exam_pid    考试期次
     * @param   int     $uid         学生UID
     * @return  void|boolean
     */
    public static function calculateQuestionScore($exam_pid, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = "SELECT  etr.etr_id, etr.ques_id, etr.answer, etr.sub_ques_id,
                etr.full_score, q.answer as q_answer, q.type
                FROM rd_exam_test_result etr
                LEFT JOIN rd_question q ON q.ques_id = etr.ques_id
                WHERE etr.exam_pid = $exam_pid";
        
        if ($uid)
        {
            $sql .= " AND etr.uid = $uid";
        }
         
        //计算试题得分
        $data = $db->fetchAll($sql);
        if (!$data)
        {
            return false;
        }
        
        //获取题组子题的信息
        $sub_ques_ids = array();
        //单选不定分
        $qtype_14 = array(); 
        foreach ($data as $item)
        {
            if ($item['sub_ques_id'] > 0)
            {
                $sub_ques_ids[] = $item['sub_ques_id'];
            }
            
            //单选不定分
            if ($item['type'] == 14)
            {
                $qtype_14[] = $item['ques_id'];
            }
        }
         
        $sub_ques_answer = array();
        if ($sub_ques_ids)
        {
            $sub_ques_id = implode(",", array_unique($sub_ques_ids));
            
            $sql = "SELECT ques_id, answer, type FROM rd_question 
                    WHERE ques_id IN ($sub_ques_id)";
            $sub_ques_answer = $db->fetchAssoc($sql);
        }
        
        //单选不定分
        $score_coefficient = array();
        if ($qtype_14)
        {
            $sql = "SELECT option_id, score_coefficient FROM rd_option
                    WHERE ques_id IN (" . implode(',', $qtype_14) . ")";
            $score_coefficient = $db->fetchPairs($sql);
        }
        
        foreach ($data as $item)
        {
            if (strlen($item['answer']) < 1)
            {
                continue;
            }
            
            if ($item['sub_ques_id'] > 0)
            {
                $sub_ques = $sub_ques_answer[$item['sub_ques_id']];
                if (strlen($sub_ques['answer']) < 1)
                {
                    continue;
                }
                
                $q_answer = trim($sub_ques['answer']);
                $type = $sub_ques['type'];
            }
            else
            {
                if ($item['type'] != 14 
                    && strlen($item['q_answer']) < 1)
                {
                    continue;
                }
                
                $q_answer = trim($item['q_answer']);
                $type = $item['type'];
            }
            
            $r_answer = trim($item['answer']);
            $full_score = $item['full_score'];
            
            if ($type == 14)
            {
                if (isset($score_coefficient[$r_answer])
                    && $score_coefficient[$r_answer] > 0)
                {
                    $test_score = round($full_score * $score_coefficient[$r_answer] / 100 , 2);
                }
                else 
                {
                    $test_score = 0;
                }
            }
            else 
            {
                $test_score = self::calculateScore($type, $full_score, $r_answer, $q_answer);
            }
                
            if ($test_score <= 0)
            {
                continue;
            }
            
            $bind = array("test_score" => $test_score);
            $result = $db->update("rd_exam_test_result", $bind, 
                    "etr_id = ?", array($item['etr_id']));
        }
    }
    
    /**
     * 计算试卷得分
     * @param   int     $exam_pid    考试期次
     * @param   int     $uid         学生UID
     * @return  void|boolean
     */
    public static function calculatePaperScore($exam_pid, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = fn::db();
        
        //更新未作弊考生的成绩
        $sql = "UPDATE rd_exam_test_paper etp
                SET etp.test_score = 
                (
                    IFNULL((SELECT SUM(test_score) FROM rd_exam_test_result etr 
                    WHERE etp.etp_id = etr.etp_id), 0)
                ), 
                etp.etp_flag = 2
                WHERE etp.exam_pid = $exam_pid AND etp.etp_flag >= 0";
        
        if ($uid)
        {
            $sql .= " AND etp.uid = $uid";
        }
        
        $db->query($sql);
         
        //更新作弊考生的成绩
        $sql = "UPDATE rd_exam_test_paper etp
                SET etp.test_score = 
                (
                    IFNULL((SELECT SUM(test_score) FROM rd_exam_test_result etr 
                    WHERE etp.etp_id = etr.etp_id), 0)
                ), 
                etp.etp_flag = -1
                WHERE etp.exam_pid = $exam_pid AND etp.etp_flag < 0";
        
        if ($uid)
        {
            $sql .= " AND etp.uid = $uid";
        }
        
        $db->query($sql);
        
        //修复考试总分大于试卷总分的考试记录
        $sql = "UPDATE rd_exam_test_paper SET test_score = full_score 
                WHERE exam_pid = $exam_pid AND test_score > full_score";
        
        $db->query($sql);
    }
    
    /**
     * 更新试卷试题分数
     * @param   int     $exam_pid    考试期次
     * @param   int     $uid         学生UID
     * @param   bool    $reset       是否重新统计试题分数
     * @return  void|boolean
     */
    public static function updateQuestionScore($exam_pid, $uid = 0, $reset = false)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = fn::db();
        
        if (!$reset)
        {
            $sql = "SELECT paper_id FROM rd_exam_test_paper etp
            WHERE exam_pid = $exam_pid " . ($uid ? "AND uid = $uid" : '') . "
                AND paper_id NOT IN
                (
                    SELECT paper_id FROM rd_exam_question_score eqs
                    WHERE eqs.exam_pid = etp.exam_pid
                )
                ";
            
            if (!$paper_ids = $db->fetchCol($sql))
            {
                return true;
            }
        }
        
        $sql = "SELECT exam_id, paper_id, ques_id, full_score
                FROM rd_exam_test_result
                WHERE exam_pid = $exam_pid AND full_score > 0
                ";
        
        if ($uid)
        {
            $sql .= " AND uid = $uid";
        }
        
        if ($paper_ids)
        {
            $sql .= " AND paper_id IN (" . implode(',', $paper_ids) . ")";
        }
        
        $sql .= " GROUP BY exam_id, paper_id, ques_id";
        
        $list = $db->fetchAll($sql);
        foreach ($list as $item)
        {
            $bind = array(
                'exam_pid' => $exam_pid,
                'exam_id'  => $item['exam_id'],
                'paper_id' => $item['paper_id'],
                'ques_id'  => $item['ques_id'],
                'test_score' => $item['full_score']
            );
            
            $db->replace("rd_exam_question_score", $bind);
        }
    }
    
    /**
     * 更新考试期次试卷试题答题情况
     * @param   int     $exam_pid    考试期次
     * @return  void|boolean
     */
    public static function updateExamQuestionstat($exam_pid)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = fn::db();
        
        //参加考试的人数
        $sql = "SELECT exam_id , COUNT(*) total_student 
                FROM rd_exam_test_paper
                WHERE exam_pid = $exam_pid GROUP BY exam_id";
        $exam_student = $db->fetchAssoc($sql);
        
        //考试信息
        $sql = "SELECT exam_id, grade_id, class_id, subject_type 
                FROM rd_exam 
                WHERE exam_pid = $exam_pid";
        $exam = $db->fetchAssoc($sql);
        
        //答题人数
        $sql = "SELECT ques_id, exam_id, COUNT(*) student_amount 
                FROM rd_exam_test_result 
                WHERE exam_pid = $exam_pid 
                GROUP BY ques_id";
        $student_count = $db->fetchAssoc($sql);
        
        //答对题人数
        $sql = "SELECT ques_id,SUM(full_score) AS full_score, 
                SUM(test_score) AS test_score 
                FROM rd_exam_test_result 
                WHERE exam_pid = $exam_pid 
                GROUP BY exam_id, uid, ques_id";
        $stmt = $db->query($sql);
        $right_count = array();
        while ($val = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if ($val['full_score'] = $val['test_score'])
            {
                if (!isset($right_count[$val['ques_id']]['right_amount']))
                {
                    $right_count[$val['ques_id']]['right_amount'] = 0;
                }
                
                $right_count[$val['ques_id']]['right_amount']++;
            }
        }
        
        $db->delete("rd_exam_question_stat", "exam_pid = ?", array($exam_pid));
        
        foreach ($student_count as $item)
        {
            $ques_id = $item['ques_id'];
            $exam_id = $item['exam_id'];
            
            $student_amount = $item['student_amount'];
            if ($student_amount > $exam_student[$exam_id]['total_student'])
            {
                $student_amount = $exam_student[$exam_id]['total_student'];
            }
            
            $right_amount = isset($right_count[$ques_id]) ? $right_count[$ques_id]['right_amount'] : 0;
            if ($right_amount > $student_amount)
            {
                $right_amount = $student_amount;
            }
            
            $bind = array(
                    'exam_pid' 		=> $exam_pid,
                    'exam_id' 		=> $item['exam_id'],
                    'ques_id' 		=> $item['ques_id'],
                    'grade_id' 		=> $exam[$exam_id]['grade_id'],
                    'class_id' 		=> $exam[$exam_id]['class_id'],
                    'subject_type' 	=> $exam[$exam_id]['subject_type'],
                    'student_amount'=> $student_amount,
                    'right_amount' 	=> $right_amount,
                    'ctime' 		=> date('Y-m-d H:i:s'),
                    'mtime' 		=> date('Y-m-d H:i:s'),
            );
            
            $db->insert("rd_exam_question_stat", $bind);
        }
    }
    
    /**
     * 计算试题得分
     * @param   int         $type       试题类型
     * @param   int         $full_score 试题满分
     * @param   int|string  $r_answer   考生答案
     * @param   int|string  $q_answer   试题答案
     * @return  int         $test_score 试题得分
     */
    private static function calculateScore($type, $full_score, $r_answer, $q_answer)
    {
        $test_score = 0;
        
        if (in_array($type, array(1, 7, 9))
            && $r_answer == $q_answer)
        {
            $test_score = $full_score;
        }
        else if ($type == 2)
        {
            $r_answer = explode(',', $r_answer);
            $q_answer = explode(',', $q_answer);
            
            sort($r_answer);
            sort($q_answer);
        
            if ($r_answer == $q_answer)
            {
                $test_score = $full_score;
            }
            else if (!array_diff($r_answer, $q_answer))
            {
                //答对部分选项,按照答对部分与总分占比 来给分
                $count_r_answer = count($r_answer);
                $count_q_answer = count($q_answer);
                $test_score = round($count_r_answer * $full_score / $count_q_answer, 1);
            }
        }
        else if (in_array($type, array(3, 5, 6, 8)))
        {
            $r_answer = explode("\n", $r_answer);
            $q_answer = explode("\n", $q_answer);
        
            if ($r_answer === $q_answer)
            {
                $test_score = $full_score;
            }
            else
            {
                $count_q_answer = count($q_answer);
                $right_answer_num = 0;
                foreach ($q_answer as $k => $v)
                {
                    if (isset($r_answer[$k]) 
                        && trim($r_answer[$k]) === trim($v))
                    {
                        $right_answer_num++;
                    }
                }
                
                $test_score = round($right_answer_num * $full_score / $count_q_answer, 2);
            }
        }
        
        return $test_score;
    }
    
    /**
     * 试题信息
     * @param   int|array   $ques_ids    试题id
     * @return  void
     */
    private static function question($ques_ids)
    {
        if (!$ques_ids)
        {
            return array();
        }
        
        $db = fn::db();
        $ques_id_str = implode(',', $ques_ids);
        
        $sql = "SELECT ques_id, type FROM rd_question 
                WHERE ques_id IN ($ques_id_str)";
        $question_list = $db->fetchAll($sql);
        
        $question = array();
        
        foreach ($question_list as $row)
        {
            
            self::$_qtype[$row['ques_id']] =$row['type'];
            
            $rows = array();
            if (in_array($row['type'], array(0,4,5,6,8,10)))
            {
                // 题组
                self::questionChildren($row['ques_id']);
            }
            elseif (in_array($row['type'], array(1,2,7,14)))
            {
                // 选择题
                self::$_option[$row['ques_id']] = self::questionOption($row['ques_id']);
            }
        }
    }
    
    /**
     * 读取题组子题列表
     *
     * @param   int     $ques_id    试题id
     * @return  void
     */
    private static function questionChildren($ques_id)
    {
        $sql = "SELECT ques_id, type FROM rd_question 
                WHERE parent_id = ? AND is_delete = 0 
                ORDER BY sort ASC,ques_id ASC";
        $children = fn::db()->fetchAll($sql, array($ques_id));
        foreach ($children as &$row)
        {
            self::$_qtype[$row['ques_id']] = $row['type'];
            self::$_qchildren[$ques_id][] = $row['ques_id'];
            
            if (in_array($row['type'],array(1,2)))
            {
                self::$_option[$row['ques_id']] = self::questionOption($row['ques_id']);
            }
        }
    }
    
    /**
     * 读取试题选项列表
     *
     * @param   int    试题id
     * @return  array
     */
    private static function questionOption($ques_id)
    {
    	$sql = "SELECT option_id FROM rd_option where ques_id = ?";
    	return fn::db()->fetchAll($sql, array($ques_id));
    }
    
    /**
     * 试卷详细信息
     * @param   int|array   $paper_id    试卷id
     * @return  array       $paper_detail
     */
    private static function paperDetail($paper_id)
    {
        if (!$paper_id)
        {
            return array();
        }
        
        $db = fn::db();
        
        $paper_id_str = implode(',', $paper_id);
        
        $sql = "SELECT e.exam_id, esp.paper_id, e.subject_id, e.grade_id,
                e.class_id, e.total_score, e.qtype_score, ep.question_score
                FROM rd_exam e 
                LEFT JOIN rd_exam_subject_paper esp ON e.exam_id = esp.exam_id 
                LEFT JOIN rd_exam_paper ep ON ep.paper_id = esp.paper_id
                WHERE esp.paper_id IN ($paper_id_str)";
        $exam = $db->fetchAll($sql);
        
        if (!$exam)
        {
             return array();
        }
        
        $grade_id = $exam[0]['grade_id'];
        $class_id = $exam[0]['class_id'];
        
        $sql = "SELECT eq.paper_id, q.ques_id, q.type, q.score_factor
                FROM rd_exam_question eq
                LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
                LEFT JOIN rd_relate_class rc ON rc.ques_id = q.ques_id 
                          AND rc.grade_id=$grade_id AND rc.class_id=$class_id
                WHERE eq.paper_id IN ($paper_id_str) 
                ORDER BY rc.difficulty DESC,q.ques_id ASC";
        
        $paper = $db->fetchAll($sql);
        
        $exam_paper_question = array();  //考试试卷试题
        foreach ($paper as $item)
        {
            $exam_paper_question[$item['paper_id']][$item['ques_id']] = $item;
        }
        
        $paper_detail = array();
        
        foreach ($exam as $item)
        {
            if ($item['subject_id'] == 3)
            {
                $groups = array(
                        1 => array(), 
                        4 => array(), 
                        0 => array(), 
                        5 => array(), 
                        6 => array(), 
                        7 => array(), 
                        2 => array(), 
                        3 => array(), 
                        8 => array(), 
                        9 => array(),
                        14 => array(),
                );
            }
            else
            {
                $groups = array(
                        1 => array(), 
                        2 => array(), 
                        3 => array(), 
                        0 => array(),
                        14 => array(),
                );
            }
            
            $question_score = @json_decode($item['question_score'], true);
            $qtype_score = explode(',', $item['qtype_score']);
            $index = 1;
            $tmp_data = $exam_paper_question[$item['paper_id']];
            $total_score_factor = 0;// 题组试题总的分值系数
            $total_score = $item['total_score']; //试卷总分
            
            foreach ($groups as $type => &$group)
            {
                foreach ($tmp_data as $ques_id => $val)
                {
                    if ($val['type'] == $type)
                    {
                        $group[$ques_id]['ques_index'] = $index++;
                        
                        if ($val['type'] > 0)
                        {
                            if (isset($question_score[$ques_id])
                                && $question_score[$ques_id])
                            {
                                $group[$ques_id]['full_score'] = array_sum($question_score[$ques_id]);
                                $total_score -= $group[$ques_id]['full_score'];
                            }
                            else 
                            {
                                $total_score -= $qtype_score[$val['type'] - 1];
                                $group[$ques_id]['full_score'] = $qtype_score[$val['type'] - 1];
                            }
                        }
                        
                        if ($type == 0)
                        {
                            $total_score_factor += $val['score_factor'];
                        }
                    }
                }
            }
            
            $groups = array_filter($groups);
            if (!empty($groups[0]))
            {
                foreach ($groups[0] as $ques_id => &$list)
                {
                    $list['full_score'] = round($total_score * $tmp_data[$ques_id]['score_factor'] / $total_score_factor);
                }
            }
            
            $paper_detail[$item['paper_id']] = $groups;
            $paper_detail[$item['paper_id']]['question_score'] = $question_score;
        }
        
        return $paper_detail;
    }
    
    /**
     * 计算考试成绩
     * @param   int     $exam_pid
     * @param   int     $uid
     * @return  boolean true|false
     */
    public static function calExamResults($exam_pid)
    {
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            self::completionExamTestPaper($exam_pid);//补全学生考试试卷
            self::completionExamQuestion($exam_pid); //补全试题
            self::calculateQuestionScore($exam_pid); //计算试题得分
            self::calculatePaperScore($exam_pid);    //计算试卷得分
            self::updateQuestionScore($exam_pid, 0, true);//更新试题分数
            self::updateExamQuestionstat($exam_pid); //更新试卷试题答题情况
        }
        
        $result = $db->commit();
        if (!$result)
        {
            $db->rollBack();
        }
        
        return $result;
    }
    
    /**
     * 计算外部考试成绩
     * @param   int     $exam_pid
     * @param   int     $uid
     * @return  boolean true|false
     */
    public static function calOutsideExamResults($exam_pid)
    {
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            self::calculatePaperScore($exam_pid);    //计算试卷得分
            self::updateQuestionScore($exam_pid, 0, true);//更新试题分数
            self::updateExamQuestionstat($exam_pid); //更新试卷试题答题情况
        }
    
        $result = $db->commit();
        if (!$result)
        {
            $db->rollBack();
        }
    
        return $result;
    }
    
    /**
     * 计算学生考试成绩
     * @param   int     $exam_pid
     * @param   int     $uid
     * @return  boolean true|false
     */
    public static function calStudentResults($exam_pid, $uid)
    {
        $db = Fn::db();
        
        $result = false;
        if ($db->beginTransaction())
        {
            self::completionExamQuestion($exam_pid, $uid); //补全试题
            self::calculateQuestionScore($exam_pid, $uid); //计算试题得分
            self::calculatePaperScore($exam_pid, $uid);    //计算试卷得分
            self::updateQuestionScore($exam_pid, $uid);    //更新试题分数
                
            //更新试卷试题答题情况(可加入考试期次后台手动更新或者加入到计划任务，每天晚上更新一次即可)
            //self::updateExamQuestionstat($exam_pid);  
        
            $result = $db->commit();
            if (!$result)
            {
                $db->rollBack();
            }
        }
    
        return $result;
    }
}