<?php !defined('BASEPATH') && exit();

/**
 * 邮箱同步
 * @author TCG
 * @create 2015-08-26
 */
class Email extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /*
     * 批量发送邮件
     * @note:
     * 	同步时间：18~22 30 分/每天 
     */
    public function send_email()
    {
    	try {
    		$time_start = time();
    		echo "start.\n";
    		$this->load->model('cron/cron_task_email_model', 'email_model');
    		$this->email_model->do_send();
    		echo "end.\n";
    		echo "cost " . (time()-$time_start) . " s.\n";
    	} catch (Exception $e) {
    		//echo $e->getMessage();
    	}
    }
    
}