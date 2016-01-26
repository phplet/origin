<?php if ( ! defined('BASEPATH')) exit();
class Cron_schedule extends A_Controller 
{
	private static $_cron_schedules = array();
    public function __construct()
    {
        parent::__construct();
        if ( ! $this->check_power('cron_schedule_list')) return; 
        
        $this->_require_cron_schedule();
    }
    
    /**
     * 引入 计划列表
     */
    protected function _require_cron_schedule()
    {
    	if (defined('ENVIRONMENT') AND is_file(APPPATH.'config/'.ENVIRONMENT.'/cron_schedules.php')) {
    		require_once (APPPATH.'config/'.ENVIRONMENT.'/cron_schedules.php');
    	} elseif (is_file(APPPATH.'config/cron_schedules.php')) {
    		require_once(APPPATH.'config/cron_schedules.php');
    	}
    	
    	self::$_cron_schedules = $cron_schedule;
    }

    // 学生成绩列表
    public function index($exam_id=0)        
    {   
    	$page = intval($this->input->get('page'));
    	$per_page = intval($this->input->get('per_page'));
    	
    	$job_code = trim($this->input->get('job_code'));
    	$statu = $this->input->get('statu');
    	$time_start = $this->input->get('time_start'); 
    	$time_end = $this->input->get('time_end'); 
    	$time_type = intval($this->input->get('time_type'));
    	
    	// 查询条件
    	$query = array();
    	$param  = array();
    	$search = array(
    				'job_code' => '',
    				'statu' => '',
    				'time_start' => '',
    				'time_end' => '',
    				'time_type' => '',
    	);
    	
    	$status = array(
    			'pending' => '待命中',
    			'running' => '正在执行',
    			'success' => '执行成功',
    			'missed'  => '丢失',
    			'error'   => '错误'
    	);
    	$time_types = array(
    			'1' => '添加时间',
    			'2' => '计划时间',
    			'3' => '执行时间',
    			'4' => '完成时间',
    	);
    	
    	if ($job_code) {
			$query['job_code'] = $job_code;
			$param[] = "job_code={$job_code}";
			$search['job_code'] = $job_code;
    	}
    	
    	if ($statu) {
			$query['status'] = $statu;
			$param[] = "statu={$statu}";
			$search['statu'] = $statu;
    	}
    	
    	$order_by = 'executed_at desc';
    	if ($time_type) {
			$param[] = "time_type={$time_type}";
			$search['time_type'] = $time_type;
			
			if ($time_start) {
				$param[] = "time_start={$time_start}";
				$search['time_start'] = $time_start;
				switch ($time_type) {
					case '1':
						$query['created_at']['>='] = $time_start;
						$order_by = 'created_at desc';
						break;
					case '2':
						$query['scheduled_at']['>='] = $time_start;
						$order_by = 'scheduled_at desc';
						break;
					case '3':
						$query['executed_at']['>='] = $time_start;
						$order_by = 'executed_at desc';
						break;
					case '4':
						$query['finished_at']['>='] = $time_start;
						$order_by = 'finished_at desc';
						break;
					default:
						break;
				}
			} 
			
			if ($time_end) {
				$param[] = "time_end={$time_end}";
				$search['time_end'] = $time_end;
				switch ($time_type) {
					case '1':
						$query['created_at']['<='] = $time_end;
						break;
					case '2':
						$query['scheduled_at']['<='] = $time_end;
						break;
					case '3':
						$query['executed_at']['<='] = $time_end;
						break;
					case '4':
						$query['finished_at']['<='] = $time_end;
						break;
					default:
						break;
				}
			} 
    	}
    	
    	$select_what = '*';
    	$page = $page <= 0 ? 1 : $page; 
    	$per_page = $per_page <= 0 ? 10 : $per_page; 
    	
    	
		$list = CronScheduleModel::get_cron_schedule_list($query, $page, $per_page, $order_by, $select_what);
		
		$data['list'] = &$list;
		$data['search'] = &$search;
		$data['status'] = &$status;
		$data['time_types'] = &$time_types;
		$data['cron_schedules'] = self::$_cron_schedules;
		
		// 分页
		$purl = site_url('admin/cron_schedule/index/') . (count($param) ? '?'.implode('&',$param) : '');
		$total = CronScheduleModel::count_list($query);
		$data['pagination'] = multipage($total, $per_page, $page, $purl);
		
    	$this->load->view('cron_schedule/index', $data);
    }

}
