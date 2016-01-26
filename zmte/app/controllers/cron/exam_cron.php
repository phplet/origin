<?php !defined('BASEPATH') && exit();

/**
 * cron
 * @author TCG
 * @create 2013-12-02
 */
class Exam_cron extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        require_once (APPPATH.'cron/exam_clear.php');
        require_once (APPPATH.'cron/exam.php');
    }
    
    /**
     * @see cron/exam.php/cal_test_result_score()
     */
    public function cal_test_score()
    {
    	try {
    		$schedule = new Exam();
    		$schedule->cal_test_result_score();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }
    
    /**
     * @see cron/exam_clear.php/clear_closed_exam_place_tmp_data()
     */
    public function clear_closed_exam_place_tmp_data()
    {
    	try {
	    	$schedule = new Exam_clear();
			$schedule->clear_closed_exam_place_tmp_data();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }
    
    /**
     * @see cron/exam_clear.php/listen_testting_log_leave_test_page()
     */
    public function listen_testting_log_leave_test_page()
    {
    	try {
	    	$schedule = new Exam_clear();
			$schedule->listen_testting_log_leave_test_page();
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }
}