<?php
/**
 * 生成报告流程控制及命令控制器
 */
class Report_command extends A_Controller 
{
    /**
     * 控制界面首页
     */
    public function index($exam_pid = 0)
    {
        Fn::ajax_call($this, 'regenerateExamRecord',
            'regenerateExamResults', 'endPlaceExam',
            'regenerateSummaryReportData',
            'regenerateReport', 'removeCronTaskReport');
        
        $exam_pid = intval($exam_pid);
        
        $param['exam_pid'] = 0;
        $param['exam_isfree'] = 0;
        $examlist = ExamModel::get_exam_list_all($param);
        
        $exam = array();
        if ($exam_pid > 0)
        {
            $exam = ExamModel::get_exam($exam_pid);
        }
        if (!$exam)
        {
            $exam = current($examlist);
        }
        $db = Fn::db();
        
        /////////////////////////考试记录是否生成/////////////
        $sql = "SELECT uid_data FROM rd_cron_task_place_student_paper ctps
                LEFT JOIN rd_exam_place  ep ON ep.place_id = ctps.place_id
                WHERE ep.exam_pid ={$exam['exam_id']} AND ctps.status=2";
        $uid_arr = $db->fetchCol($sql);
        $paper_count = 0;
        foreach ($uid_arr as $item)
        {
            $paper_count+=count(json_decode($item));
        }
        $sql ="SELECT COUNT(*) FROM rd_exam_place_student eps
               LEFT JOIN rd_exam_place ep ON ep.place_id = eps.place_id
               WHERE ep.exam_pid = {$exam['exam_id']}";
        $student_count = $db->fetchOne($sql);
        if ($paper_count ==$student_count)
        {
            $data['paper_status'] = true;//考试记录是否完全生成
        }
        else
        {
            $data['paper_status'] = false;
        }
        /////////////////////////////////////////
        $sql ="SELECT status FROM rd_cron_task_exam_result 
            WHERE exam_pid = {$exam['exam_id']}";
        $data['cter_status'] = $db->fetchOne($sql);
        //////////////////////////////////////////
        $sql = "SELECT DISTINCT(status) FROM rd_cron_task_report ctr 
                LEFT JOIN rd_evaluate_rule er ON er.id = ctr.rule_id 
                WHERE exam_pid =  {$exam['exam_id']}";
        $data['ctr_status'] = $db->fetchCol($sql);
        ////////////////////////////////////////
        $data['exam'] = $exam;
        $data['demo_exam'] = $this->demo_exam_list();
        $data['examlist'] = $examlist;
        $data['place'] = ExamPlaceModel::get_exam_place($exam_pid, 'MAX(end_time) as end_time');
        $data['crontaskexamresult'] = ReportCommandModel::cronTaskExamResultInfo($exam['exam_id']);
        $data['evaluerulelist'] = ReportCommandModel::cronTaskReportLists($exam['exam_id']);
        
        $this->load->view('report_command/index', $data);
    }
    
    /**
     * 获取免费体检的考试期次
     */
    private function demo_exam_list()
    {
        $exam_config = C('demo_exam_config', 'app/demo/website');
        
        $demo_exams = array();
        foreach ($exam_config as $item) 
        {
            $demo_exams[] = $item['exam_pid'];
        }
        
        return $demo_exams;
    }
    
    /**
     * 重新生成考试记录
     * @param   int     $exam_pid
     * @return  AjaxResponse
     */
    public function regenerateExamRecordFunc($exam_pid)
    {
        $resp = new AjaxResponse();
        
        try
        {
            if (ReportCommandModel::cronTaskPlaceStudentPaper($exam_pid))
            {
                $resp->alert('重新生成考试记录已加入计划任务中，请耐心等待或执行相关php命令，待记录重新生成后，请结束所有的考试。');
                $resp->refresh();
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        
        return $resp;
    }
    
    /**
     * 重新生成考试成绩
     * @param   int     $exam_pid
     * @return  AjaxResponse
     */
    public function regenerateExamResultsFunc($exam_pid)
    {
        $resp = new AjaxResponse();
        
        $place_time = ExamPlaceModel::get_exam_place($exam_pid, 'MAX(end_time) as end_time');
        if ($place_time['end_time'] > time())
        {
            $resp->alert('考试还未结束，无法重新生成考试成绩。');
            return $resp;
        }
    
        try
        {
            if (ReportCommandModel::setCronTaskExamResultStatus($exam_pid))
            {
                $resp->alert('重新生成考试成绩已加入计划任务中，请耐心等待或执行相关php命令。');
                $resp->refresh();
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
    
        return $resp;
    }

    /**
     * 结束考试期次下所有的考试
     * @param   int     $exam_pid
     * @return  AjaxResponse
     */
    public function endPlaceExamFunc($exam_pid)
    {
        $resp = new AjaxResponse();
        
        try
        {
            $param = array(
                'exam_pid' => $exam_pid,
                'end_time' => time(),
            );
            
            if (ExamPlaceModel::setExamPlace($param))
            {
                $resp->alert('考试期次下所有的考试均已结束，请进行后续相关操作。');
                $resp->refresh();
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        
        return $resp;
    }
    
    /**
     * 重新统计报告数据
     * @param   int     $exam_pid
     * @return  AjaxResponse
     */
    public function regenerateSummaryReportDataFunc($exam_pid)
    {
        $resp = new AjaxResponse();
    
        $place_time = ExamPlaceModel::get_exam_place($exam_pid, 'MAX(end_time) as end_time');
        if ($place_time['end_time'] > time())
        {
            $resp->alert('考试还未结束，无法重新统计报告数据。');
            return $resp;
        }
    
        try
        {
            if (ReportCommandModel::updateCronTaskExamResultStatus($exam_pid))
            {
                $resp->alert('重新统计报告数据已加入计划任务中，请耐心等待或执行相关php命令。');
                $resp->refresh();
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
    
        return $resp;
    }
    
    /**
     * 移除生成报告任务
     * @param   mixed   $rule_id_str
     * @return  AjaxResponse
     */
    public function removeCronTaskReportFunc($rule_id_str)
    {
        $resp = new AjaxResponse();
        
        try
        {
            if (ReportCommandModel::removeCronTaskReport($rule_id_str))
            {
                $resp->alert('生成报告任务移除成功。');
                $resp->refresh();
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
    
        return $resp;
    }
    
    /**
     * 重新生成报告
     * @param   mixed   $rule_id_str
     * @return  AjaxResponse
     */
    public function regenerateReportFunc($rule_id_str)
    {
        $resp = new AjaxResponse();
        
        $rule_ids = explode(',', $rule_id_str);
        if (!$rule_ids)
        {
            $resp->alert('请指定需要重新生成报告的评估规则。');
            return $resp;
        }
        
        try
        {
            $flag = true;
            foreach ($rule_ids as $rule_id)
            {
                $param = array(
                    'rule_id' => $rule_id,
                    'html_status' => 0,
                    'status' => 0,
                    'is_success' => 0,
                    'num' => 0,
                );
                $flag = ReportCommandModel::setConvert2pdfStatus($param);
            }
            
            if ($flag)
            {
                $resp->alert('重新生成报告已加入计划任务中，请耐心等待或执行相关php命令。');
                $resp->call('fnCloseDialog', 'id_regeneratereport_dlg');
            }
            else
            {
                $resp->alert('操作失败，请重试。');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
    
        return $resp;
    }
}
