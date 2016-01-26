<?php !defined('BASEPATH') && exit();

/**
 * 考试汇总数据 同步
 * @author TCG
 * @create 2015-07-26
 */
class Summary extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /*
     * 根据考试期次生成相关的统计信息
     * @note:
     * 	同步时间：30分/每天
     */
    private function do_summary()
    {
        set_time_limit(0);
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
        
        $exam_list = $this->cer_model->get_task_exam_list(1, NULL, 1);

        if (!$exam_list)
        {
            return false;
        }
        
        exit;
        $this->load->model('cron/summary_paper_model');
        $this->load->model('cron/summary_region_model');
        $this->load->model('cron/summary_student_model');
        
        foreach ($exam_list as $item)
        {
            $this->cer_model->set_task_exam_result_status(array('status'=>3), $item['id']);
            
            $result = $this->summary_paper_model->do_all($item['exam_pid']);
            if ($result)
            {
                $result = $this->summary_region_model->do_all($item['exam_pid']);
            }
            
            if ($result)
            {
                $result = $this->summary_student_model->do_all($item['exam_pid']);
            }
            
            $this->cer_model->set_task_exam_result_status(array('status'=>($result ? 2 : 1)), $item['id']);
        }
    }
    
    /*
     * 根据考试期次生成试卷相关的统计信息
    * @note:
    * 	同步时间：30分/每天
    */
    public function summary_paper()
    {
        set_time_limit(0);
        
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
    
        $exam_list = $this->cer_model->get_task_exam_list(1, NULL, 5);
    
        if (!$exam_list)
        {
            return false;
        }
    
        foreach ($exam_list as $item)
        {
            $this->cer_model->set_task_exam_result_status(array('status' => 100), $item['id']);
            
            $result = SummaryModel::summary_paper($item['exam_pid'], null, null, true);
            
            $this->cer_model->set_task_exam_result_status(array('status'=>($result ? 2 : 1)), $item['id']);
        }
    }
        
    /*
     * 根据考试期次生成地区相关的统计信息
    * @note:
    * 	同步时间：30分/每天
    */
    public function summary_region()
    {
        set_time_limit(0);
        
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
        
        $exam_list = $this->cer_model->get_task_exam_list(2, NULL, 5);
        
        if (!$exam_list)
        {
            return false;
        }
        
        foreach ($exam_list as $item)
        {
            $this->cer_model->set_task_exam_result_status(array('status'=>3), $item['id']);
            
            $result = SummaryModel::summary_region($item['exam_pid']);
            
            if (!$result)
            {
                $this->cer_model->set_task_exam_result_status(array('status'=>2), $item['id']);
            }
        }
    }
        
    /*
     * 根据考试期次生成学生相关的统计信息
    * @note:
    * 	同步时间：30分/每天
    */
    public function summary_student()
    {
        set_time_limit(0);
        
        $this->load->model('cron/cron_exam_result_model', 'cer_model');
    
        $exam_list = $this->cer_model->get_task_exam_list(3, NULL, 5);
    
        if (!$exam_list)
        {
            return false;
        }
    
        foreach ($exam_list as $item)
        {

            $this->cer_model->set_task_exam_result_status(array('status'=>100), $item['id']);
            
            $result = SummaryModel::summary_student($item['exam_pid']);
            
            $this->cer_model->set_task_exam_result_status(array('status'=> ($result ? 4 : 3)), $item['id']);
        }
    }
    
    /**
     * @property
     * 
     * 生成试卷关联的分数（样卷对应的开始期次）
     * @note:
     * 	同步条件：
     * 		当样卷有变更时执行，需要手动执行
     * 	执行方式：php /home/wwwroot/zmte/html/cron/index.php cron summary summary_demo_paper_question
     */
    private function summary_demo_paper_question()
    {
    	//样卷期次ID
    	$exam_pid = 1;
    	try {
    		$this->load->model('cron/summary_paper_model');
    		$this->summary_paper_model->summary_paper_question($exam_pid);
    	} catch (Exception $e) {
    		//echo $e->getMessage();
    	}
    }
}
