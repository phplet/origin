<?php !defined('BASEPATH') && exit();

/**
 * 测评报告相关 同步
 * @author TCG
 * @create 2015-08-26
 */
class Evaluate_rule extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function test_general()
    {
        $rule_id = 2;
        $exam_pid = 26;
        $exam_id = 11;
        $uid = 95;
         
        $this->load->model('cron/report/complex_model', 'c_model');

        $data = $this->c_model->module_level_percent ( $rule_id, $exam_pid, $uid ); //匹配度 XX%
    
        $this->load->view ( 'report/subject', $data);
    }

    /*
     * 生成测评报告模板
     * @note:
     * 	同步时间：18~22 30 分/每天 
     */
    public function general_html()
    {
/*         ini_set( 'display_errors', 'On' );
        error_reporting(-1);
        $this->load->model('cron/report/general_template_model');
        $this->general_template_model->general_to_html(); */

    	try {
    		$this->load->model('cron/report/general_template_model');
    		$this->general_template_model->general_to_html();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }
    
    /*
     * 生成测评报告模板
    * @note:
    * 	同步时间：18~22 30 分/每天
    */
    public function general_html2()
    {
        set_time_limit(0);

        $this->load->model('cron/report/general_html_model');
        
        $this->general_html_model->general_to_html();
    }

    public function general_html_demo($exam_subject_id)
    {
        set_time_limit(0);
        ini_set('display_errors', 'On');
        error_reporting(0);

        $this->load->model('cron/report/general_html_model');
        
        $this->general_html_model->general_to_html_demo($exam_subject_id);
    }
    
    /*
     * 生成测评报告模板
    * @note:
    * 	同步时间：18~22 30 分/每天
    */
    public function general_html3()
    {
        sleep(1);
        
        set_time_limit(0);
        
        $this->load->model('cron/report/general_html_model');
        $this->general_html_model->general_to_html();
    }
    
    /*
     * 生成测评报告模板
    * @note:
    * 	同步时间：18~22 30 分/每天
    */
    public function general_html_record()
    {
        set_time_limit(0);
        
        /* ini_set('display_errors', 'On' );
        error_reporting(-1);

        $this->load->model('cron/report/general_html_model');
        $this->general_html_model->general_html_record(); */

        try {
            $this->load->model('cron/report/general_html_model');
            $this->general_html_model->general_html_record();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * 生成面试报告
     *
     * @return mixed 失败返回错误信息，成功返回空
     */
    public function general_interview_html()
    {
        error_reporting(E_ALL);
        ini_set( 'display_errors', 'On' );
        $this->load->model('cron/report/general_template_model');
        $this->general_template_model->general_interview_to_html();

/*         try {
            $this->load->model('cron/report/general_template_model');
            $this->general_template_model->general_interview_to_html();
        } catch (Exception $e) {
            echo $e->getMessage();
        } */
    }
    
    /*
     * 生成zip文件
     * @note:
     * 	同步时间：18~22 30 分/每天 
     */
    public function general_zip()
    {
    	try {
    		$this->load->model('cron/report/general_template_model');
    		$this->general_template_model->general_zip();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }

    /**
     * 生成面试报告zip文件
     *
     * @return mixed 失败返回错误信息，成功返回空
     */
    public function general_interview_zip()
    {
        try {
            $this->load->model('cron/report/general_template_model');
            $this->general_template_model->general_interview_zip();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    
    /*
     * 检查 测评报告生成情况
     * @note:
     * 	同步时间：18~22 30 分/每天 
     */
    public function check_stat()
    {
    	try {
    		$this->load->model('cron/report/general_template_model');
    		$this->general_template_model->check_evaluate_stat();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }

    /**
     * 检查 面试报告生成情况
     * @return mixed 失败返回错误信息，成功返回空
     */
    public function check_interview_stat()
    {
        try {
            $this->load->model('cron/report/general_template_model');
            $this->general_template_model->check_interview_stat();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    
    public function gen_report_data()
    {
        set_time_limit(0);
        
        $this->load->model('cron/report_data_model');
        $this->report_data_model->evaluate_rule(5);
    }
    
    
    /**
     * 自由考试机考调用服务接口
     */
    public function service_report_data()
    {
        set_time_limit(0);
        
        $secretkey = $this->input->get('secretkey');
        $params = json_decode(Func::strDecode($secretkey), true);
        if (empty($params))
        {
            output_json('fail', '数据传输有误！');
        }
        
        $this->load->model('cron/report/general_html_model');
        
        try 
        {
            if ($this->general_html_model->general_free_exam_html($params))
            {
                output_json('success');
            }
            else
            {
                output_json('fail', '评估报告生成错误，请联系管理员！');
            }
        }
        catch (Exception $e)
        {
            output_json('fail', $e->getMessage());
        }
    }
}
