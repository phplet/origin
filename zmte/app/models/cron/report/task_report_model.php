<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 计划任务-生成测评
 * @author TCG
 * @final 2015-07-22
 */
class Task_report_model extends CI_Model 
{
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * 获取处理任务
	 */
	public function get_task($rule_id=0, $item=NULL)
	{
		if ($rule_id == 0)
		{
			return FALSE;
		}
		if ($item) $this->db->select($item);
		$query = $this->db->get_where('cron_task_report', array('rule_id' => $rule_id));
		$task = $query->row_array();
		if ($item && isset($task[$item]))
			return $task[$item];
		else
			return $task;
	}
	
	/**
	 * 获取待处理任务
	 */
	public function get_undo_tasks()
	{
		$sql = "select rule_id from {pre}cron_task_report where status=0";
		$result = $this->db->query($sql)->result_array();
		
		return $result;
	}
	
	/**
	 * 获取 处理中的任务
	 */
	public function get_doing_tasks($status = 0)
	{
		$sql = "select status, rule_id, template_id, ctime from {pre}cron_task_report";
		if (is_array($status) && $status)
		{
		    $sql .= " WHERE status IN (" . implode(',', $status) . ")";
		}
		else if ($status >= 0 && $status <= 3)
		{
		    $sql .= " WHERE status = $status";
		}
		
		return $this->db->query($sql)->result_array();
	}
	
	/**
	 * 将某个任务标记为 未处理
	 */
	public function set_task_undo($rule_id)
	{
		$res = $this->db->update('cron_task_report', array('status'=>'0', 'mtime'=>date('Y-m-d H:i:s')), array('rule_id'=>$rule_id));
		return $res > 0;
	}
	
	/**
	 * 将某个任务标记为 处理中
	 */
	public function set_task_doing($rule_id)
	{
		$res = $this->db->update('cron_task_report', array('status'=>'1', 'mtime'=>date('Y-m-d H:i:s')), array('rule_id'=>$rule_id));
		return $res > 0;
	}
	
	/**
	 * 将某个任务标记为 部分处理完成
	 */
	public function set_task_part_done($rule_id)
	{
		$res = $this->db->update('cron_task_report', array('status'=>'2', 'mtime'=>date('Y-m-d H:i:s')), array('rule_id'=>$rule_id));
		return $res > 0;
	}
	
	/**
	 * 将某个任务标记为 全部处理完成
	 */
	public function set_task_done($rule_id)
	{
		$res = $this->db->update('cron_task_report', array('status'=>'3', 'mtime'=>date('Y-m-d H:i:s')), array('rule_id'=>$rule_id));
		return $res > 0;
	}
	
	/**
	 * 添加
	 */
	public function insert($rule_id = 0, $template_id = 0)
	{
		$rule_id = intval($rule_id);
		$template_id = $template_id;
		if (!$rule_id || !$template_id)
		{
			return false;
		}
		
		$task = $this->get_task($rule_id);
		if (count($task) && $task['status'] != '1')
		{
			$this->set_task_undo($rule_id);
			return true;
		}
		
		$data = array(
					'rule_id' => $rule_id,
					'status' => '0',
		            'template_id' => $template_id,
					'ctime' => date('Y-m-d H:i:s'),
					'mtime' => date('Y-m-d H:i:s'),
		);
		$this->db->insert('cron_task_report', $data);
		
		return $this->db->insert_id() > 0;
	}
}