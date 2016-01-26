<?php
/**
 * 生成报告流程控制及命令Model
 */
class ReportCommandModel
{
    /**
     * 获取考试期次是否加入生成成绩任务表
     */
    public static function cronTaskExamResultInfo($exam_pid)
    {
        if (!$exam_pid)
        {
            return array();
        }
        
        $sql = "SELECT * FROM rd_cron_task_exam_result
                WHERE exam_pid = $exam_pid";
        return Fn::db()->fetchRow($sql);
    }
    
    /**
     * 获取当前期次下已加入生成报告任务的评估规则
     */
    public static function cronTaskReportLists($exam_pid)
    {
        if (!$exam_pid)
        {
            return array();
        }
        
        $sql = "SELECT ctr.rule_id, er.name FROM rd_cron_task_report ctr
                LEFT JOIN rd_evaluate_rule er on er.id = ctr.rule_id
                WHERE exam_pid = $exam_pid";
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 重新生成考试记录
     */
    public static function cronTaskPlaceStudentPaper($exam_pid)
    {
        if (!$exam_pid)
        {
            throw new Exception('请指定需要重新生成考试记录的考试期次！');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT place_id FROM rd_exam_place 
                WHERE exam_pid = $exam_pid";
        $place_ids = $db->fetchCol($sql);
        if (!$place_ids)
        {
            throw new Exception('考试期次没有创建考场，无法生成考试记录！');
        }
        
        $place_id_str = implode(',', $place_ids);
        
        $sql = "SELECT place_id, uid FROM rd_exam_place_student
                WHERE place_id IN ($place_id_str)";
        $place_stuids = array();
        $stmt = $db->query($sql);
        while ($v = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $place_stuids[$v['place_id']][] = $v[uid];
        }
        
        if (!$place_stuids)
        {
            throw new Exception('考试期次考场没分配学生，无法生成考试记录！');
        }
        
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->delete('rd_cron_task_place_student_paper', 
                "place_id IN ($place_id_str)");
            
            $bind = array(
                'start_time' => time() + 3600,
                'end_time'   => time() + 10800
            );
            $db->update('rd_exam_place', $bind , 
                "place_id IN ($place_id_str)");
            
            foreach ($place_stuids as $place_id => $uids)
            {
                $param = array(
                    'place_id' => $place_id,
                    'status'   => 0,
                    'uid_data' => json_encode($uids),
                    'c_time'   => time(),
                    'u_time'   => time()
                );
                
                $db->insert('rd_cron_task_place_student_paper', $param);
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
     * 修改重新计算考试成绩任务
     */
    public static function setCronTaskExamResultStatus($exam_pid)
    {
        if (!Validate::isInt($exam_pid)
            || $exam_pid <= 0)
        {
            throw new Exception('请指定考试期次！');
        }
        
        if (ExamModel::get_exam($exam_pid, 'exam_ticket_maprule'))
        {
            //外部考试
            $db = Fn::db();
            $bOk = false;
            if ($db->beginTransaction())
            {
                $db->delete('rd_cron_task_exam_result', 
                    'exam_pid = ' . $exam_pid);
                $bind = array(
                    'er_flag' => 1
                );
                $db->update('t_exam_relate', $bind, 
                    'er_flag = 3 AND er_exampid = ' . $exam_pid);
                
                $sql = "UPDATE tmp_table9700 SET s = 0 
                        WHERE place_id IN (
                            SELECT place_id FROM rd_exam_place 
                            WHERE exam_pid = {$exam_pid}
                        )";
                $db->query($sql);
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
            }
            
            return $bOk;
        }
        else
        {
            //机考
            $param = array(
                'exam_pid' => $exam_pid,
                'status'   => 0,
                'c_time'   => time(),
            );
            return Fn::db()->replace('rd_cron_task_exam_result', $param);
        }
    }
    
    /**
     * 修改重新计算考试成绩任务
     */
    public static function updateCronTaskExamResultStatus($exam_pid)
    {
        if (!Validate::isInt($exam_pid)
            || $exam_pid <= 0)
        {
            throw new Exception('请指定考试期次！');
        }
        
        $param = array(
            'exam_pid' => $exam_pid,
            'status'   => 1,
            'c_time'   => time(),
        );
        return Fn::db()->replace('rd_cron_task_exam_result', $param);
    }
    
    /**
     * 删除计划任务中指定的评估规则
     */
    public static function removeCronTaskReport($rule_id)
    {
        if (!Validate::isJoinedIntStr($rule_id))
        {
            throw new Exception('请指定评估规则！');
        }
        
        $bOk = false;
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            $db->delete('rd_convert2pdf', "rule_id IN ($rule_id)");
            $db->delete('rd_cron_task_report', "rule_id IN ($rule_id)");
            $db->delete('rd_evaluate_student_stat', "rule_id IN ($rule_id)");
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        
        return $bOk;
    }
    
    /**
     * 设置评估规则生成html的状态
     */
    public static function setConvert2pdfStatus($param)
    {
        $param = Func::param_copy($param, 'rule_id', 
            'html_status', 'is_success', 'status', 'num');
        
        if (!Validate::isInt($param['rule_id'])
            || $param['rule_id'] <= 0)
        {
            throw new Exception('请指定评估规则！');
        }
        
        return Fn::db()->update('rd_convert2pdf', 
            $param, "rule_id = " . $param['rule_id']);
    }
}