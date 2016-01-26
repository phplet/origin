<?php if ( ! defined('BASEPATH')) exit();
/**
 * 计划任务--生成考试期次学生的成绩
 * @author tcg
 * @final 2015-07-24
 */
class Cron_exam_result_model extends CI_Model 
{
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * 获取任务状态为$status的信息
	 */
	public function get_task_exam_list($status = 0, $item = NULL, $limit = NULL)
	{
		if ($item) $this->db->select($item);
		if ($limit > 0) $this->db->limit($limit);
		
        $query = array(
                'status' => $status ? $status : 0
        );
		
		$query = $this->db->get_where('cron_task_exam_result', $query);
		
		return $query->result_array();
	}
	
	/**
	 * 设置计划任务执行结果
	 */
	public function set_task_exam_result_status($param, $id)
	{
		if (!$param || !$id)
		{
			return false;
		}
		
		return $this->db->update('cron_task_exam_result', $param, "id = $id");
	}
	
	/**
	 * 添加 生成考试期次学生的成绩任务
	 */
	public function insert($data)
	{
	    if (empty($data['exam_pid']))
	    {
		    return false;
	    }
	    
	    $data['c_time'] = time();
	    $data['status'] = 0;
	    
	    $exam_ticket_maprule = ExamModel::get_exam($data['exam_pid'], 'exam_ticket_maprule');
	    if ($exam_ticket_maprule > 0)
	    {
	        $data['status'] = 1;
	    }
			
		return Fn::db()->replace('rd_cron_task_exam_result', $data);
	}
}
