<?php !defined('BASEPATH') && exit();

/**
 * cron
 * @author TCG
 * @create 2015-11-13
 */
class Cal_import_result extends Cron_Controller
{
    public function calculate()
    {
        $db = Fn::db();
        
        $sql = "SELECT * FROM tmp_table9700 
                WHERE uid = 0 AND s = 0";
        $stmt = $db->query($sql);
        
        $place_list = array();
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            $place_list[$item['place_id']][$item['subject_id']][] = $item;
        }
        
        if ($place_list && $db->beginTransaction())
        {
            $start = time();
            
            foreach ($place_list as $place_id => $data_subject)
            {
                $sql = "SELECT COUNT(*) FROM rd_exam_test_paper 
                        WHERE place_id = ?";
                if (!$db->fetchOne($sql, array($place_id)))
                {
                    continue;
                }
                
                foreach ($data_subject as $subject_id => $item)
                {
                    //每次执行2分钟
                    if (time() - $start >= 120)
                    {
                        break 2;
                    }
                    
                    $ques_id = array_slice(json_decode($item[0]['k'], true), 5);
                    $full_score = array_slice(json_decode($item[1]['k'], true), 5);
                    
                    if (!$ques_id || !$full_score)
                    {
                        continue;
                    }
                    
                    //修改状态为正在处理中
                    $db->update('tmp_table9700', array('s' => 3), 
                        'place_id = ? AND subject_id = ? AND uid = 0',
                         array($place_id, $place_id));
                    
                    $sql = "SELECT * FROM tmp_table9700 
                            WHERE place_id = ? AND subject_id = ? 
                            AND s = 0 AND uid > 0 limit 10000";
                    $stmt = $db->query($sql, array($place_id, $subject_id));
                    while ($val = $stmt->fetch(PDO_DB::FETCH_ASSOC))
                    {
                        $data = array_slice(json_decode($val['k'], true), 5);
                        
                        $sql = "SELECT etp_id FROM rd_exam_test_paper
                                WHERE place_id = ? AND uid = ? AND subject_id = ?";
                        $etp_id = $db->fetchOne($sql, array(
                            $place_id, $val['uid'], $subject_id));
                        
                        if (!$etp_id)
                        {
                            continue;
                        }
                        
                        foreach ($ques_id as $k => $q_id)
                        {
                            if (!$q_id 
                                || !Validate::isInt($q_id)
                                || !$full_score[$k])
                            {
                                continue;
                            }
                            
                            $bind = array(
                                'full_score' => $full_score[$k],
                                'test_score' => ($data[$k] > $full_score[$k] ? $full_score[$k] : $data[$k]),
                            );
                            
                            $db->update('rd_exam_test_result', $bind,
                                'etp_id = ? AND (ques_id = ? OR sub_ques_id = ?)',
                                array($etp_id, $q_id, $q_id));
                        }
                    }
                    
                    //修改正在进行状态为计算已完成
                    $db->update('tmp_table9700', array('s'=>1),
                        'place_id = ? AND subject_id = ?', 
                        array($place_id, $subject_id));
                }
            }
            
            if (!$db->commit())
            {
                $db->rollBack();
            }
        } 
        
        //计算已经更新完成的所有考试的学生成绩
        $sql = "SELECT DISTINCT(a.exam_pid) FROM rd_exam_place a
                LEFT JOIN tmp_table9700 b ON a.place_id = b.place_id
                WHERE uid = 0 AND s IN (0, 3)
                ";
        $exam_pids = $db->fetchCol($sql);
        
        if ($db->beginTransaction())
        {
            $sql = "SELECT DISTINCT(a.exam_pid) FROM rd_exam_place a
                    LEFT JOIN tmp_table9700 b ON a.place_id = b.place_id
                    WHERE uid = 0 AND s = 1
                    ";
            $exam_pids2 = $db->fetchCol($sql);
            
            foreach ($exam_pids2 as $exam_pid)
            {
                if ($exam_pids 
                    && in_array($exam_pid, $exam_pids))
                {
                    continue;
                }
                
                $sql = "UPDATE tmp_table9700 SET s = 2 
                        WHERE place_id IN (
                            SELECT place_id FROM rd_exam_place 
                            WHERE exam_pid = $exam_pid
                        )";
                $db->query($sql);
            
                //所有考场均已完成成绩导入计算
                ExamstatModel::calculatePaperScore($exam_pid);    //计算试卷得分
                ExamstatModel::updateQuestionScore($exam_pid, 0, true);//更新试题分数
                ExamstatModel::updateExamQuestionstat($exam_pid); //更新试卷试题答题情况
                
                $bind = array(
                    'exam_pid' => $exam_pid,
                    'status'   => 1,
                    'c_time'   => time(),
                );
                $db->replace('rd_cron_task_exam_result', $bind);
            }
        }
        
        if (!$db->commit())
        {
            $db->rollBack();
        }
    }
}