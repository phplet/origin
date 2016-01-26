<?php !defined('BASEPATH') && exit();

/**
 * 生成考试期次下学生成绩
 * @author tcg
 * @create 2015-07-24
 */
class Exam_result extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
   
    public function get_exam_student_result()
    {
        set_time_limit(0);
        
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
        
        $exam_list = $this->cer_model->get_task_exam_list();
        
        if ($exam_list)
        {
            foreach ($exam_list as $item)
            {
                $result = ExamstatModel::calExamResults($item['exam_pid']);
                if ($result)
                {
                    $this->cer_model->set_task_exam_result_status(array('status'=>1), $item['id']);
                }
            }
        }
    }
    
    public function get_outside_exam_student_result()
    {
        set_time_limit(0);
    
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
    
        $exam_list = $this->cer_model->get_task_exam_list();
    
        if ($exam_list)
        {
            foreach ($exam_list as $item)
            {
                $result = ExamstatModel::calOutsideExamResults($item['exam_pid']);
                if ($result)
                {
                    $this->cer_model->set_task_exam_result_status(array('status'=>1), $item['id']);
                }
            }
        }
    }
}
