<?php !defined('BASEPATH') && exit();

/**
 * 考生考试 界面
 * @author TCG
 * @create 2013-12-02
 */
class Log extends Exam_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        require_once (APPPATH.'config/app/exam/log_type_desc.php');
    }
    
    /**
      * 考生日志记录
      */
    public function push() 
    {
    	$action = $this->input->post('act');
    	$data = $this->input->post('data');
    	if ($action && $log_type = Log_type_desc::get_log_alia($action)) {
    		$log_content = array('time' => date('Y-m-d H:i:s'));
    		$data = (Array) json_decode($data);
    		if (is_array($data) && count($data)) {
    			switch ($log_type) {
    				case EXAM_LOG_WINDOW_BLUR:
    					if (isset($data['count'])) {
	    					$log_content = '离开考试界面 ' . $data['count'] . ' 次';
    					}
    					break;
    				case EXAM_LOG_WINDOW_BLUR_LONG_TIME:
    					if (isset($data['time'])) {
    						$min = ceil($data['time']/60);
    						if ($min >= 2) {
		    					$log_content = '离开考试界面  2 分钟以上';
    						} else {
		    					$log_content = '离开考试界面 ' . $min . ' 分钟';
    						}
    					}
    					break;
    			} 
    		}
    		//$sql="select sleep(10)";
    		//$this->db->query($sql);
    		exam_log($log_type, $log_content);
    		die;
    	}
    }

    public function  student()
    {
        $userdata = $this->session->userdata;
        
        if($userdata['exam_ticket_out']>0&&$userdata['txt_student_tichu']==1){
        output_json(CODE_SUCCESS, 'success','fail');die;}
        else {
        output_json(CODE_SUCCESS, 'success','success');die;}
    }

}
