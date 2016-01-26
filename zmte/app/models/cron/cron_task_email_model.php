<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 计划任务--处理发送邮件
 * @author TCG
 * @final 2015-07-31
 */
class Cron_task_email_model extends CI_Model 
{
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * 获取单条 信息
	 */
	public function get_task_email($type = 0, $target_id = '', $item = NULL)
	{
		if ($item) $this->db->select($item);
		
		$query = array(
					'type' 		=> $type,
					'target_id' => $target_id,
		);
		$query = $this->db->get_where('cron_task_send_email', $query);
		$task = $query->row_array();
		if ($item && isset($task[$item]))
			return $task[$item];
		else
			return $task;
	}
	
	/**
	 * 获取 待发送邮件
	 */
	public function get_undo_emails()
	{
		$sql = "select id, type, email, title, content, attache from {pre}cron_task_send_email where status<2 and title != ''";
		return $this->db->query($sql)->result_array();
	}
	
	/**
	 * 将已发成功邮件 设置为 发送失败
	 */
	public function set_statu_failed($ids = array())
	{
		if (!is_array($ids) || !count($ids))
		{
			return false;
		}
		
		$data = array(
					'status' 		=> '0',
					'is_success' 	=> '0',
					'mtime' 		=> date('Y-m-d H:i:s'),
		);
		
		return $this->db->where_in('id', $ids)->update('cron_task_send_email', $data);
	}
	/**
	 * 将已发成功邮件 设置为 已发成功
	 */
	public function set_statu_success($ids = array())
	{
		if (!is_array($ids) || !count($ids))
		{
			return false;
		}
		
		$data = array(
					'status' 		=> '2',
					'is_success' 	=> '1',
					'mtime' 		=> date('Y-m-d H:i:s'),
		);
		return $this->db->where_in('id', $ids)->update('cron_task_send_email', $data);
	}
	
	/**
	 * 添加 发邮件任务
	 */
	public function insert($data)
	{
		$now = date('Y-m-d H:i:s');
		
		$task_email = $this->get_task_email($data['type'], $data['target_id']);
		if (!count($task_email))
		{
			$data['ctime'] = $now;
			$data['mtime'] = $now;
			
			$this->db->insert('cron_task_send_email', $data);
			
			return $this->db->insert_id() > 0;
		}
		else
		{
			$data['mtime'] = $now;
			
			$id = $task_email['id'];
			
			unset($data['type']);
			unset($data['target_id']);
			
			$res = $this->db->where('id', $id)->update('cron_task_send_email', $data);
			
			return $res;
		}
	}
	
	/**
	 * 批量 添加 发邮件任务
	 */
	public function insert_batch($data)
	{
		$now = date('Y-m-d H:i:s');
		$insert_data = array();
		foreach ($data as $item)
		{
			$task_email = $this->get_task_email($item['type'], $item['target_id'], 'id, status');
			if (!count($task_email))
			{
				$item['ctime'] = $now;
				$item['mtime'] = $now;
				
				$insert_data[] = $item;
			}
		}
		if (count($insert_data))
		{
			$this->db->insert_batch('cron_task_send_email', $insert_data);
			
			return $this->db->insert_id() > 0;
		}
		
		return true;
	}
	
	/**
	 * 批量发邮件任务脚本
	 */
	public function do_send()
	{
		//获取待发邮件
		$emails = $this->get_undo_emails();
		
		//发送成功
		$success_data = array();
		$fail_data = array();
		
		foreach ($emails as $item)
		{
			$id = $item['id'];
			$type = $item['type'];//类型 
			$email = $item['email'];//邮箱接收者
			$title = trim($item['title']);//邮件标题
			$content = trim($item['content']);//邮件内容
			$attache = trim($item['attache']);//附件
			
			if ($title == '' || !is_email($email)) 
			{
				continue;
			} 
			
			//补充attache地址
			$attache_file = '';
			$cache_path = realpath(dirname(APPPATH)) . '/cache';
			if ($attache != '')
			{
				if (file_exists($attache)) 
				{
					$attache_file = $attache;
				}
				else 
				{
					switch ($type)
					{
						case '1':
							$attache_file = $cache_path . '/zip/' . $attache;
							break;
						default:
							break;
					}
				}
			}
			
			if ($res = send_email($title, $content, $email, $attache_file))
			{
				$success_data[] = $id;
			}
			else 
			{
				$fail_data[] = $id;
			}
		}
		
		if (count($success_data))
		{
			$this->set_statu_success($success_data);
		}
		
		if (count($fail_data))
		{
			$this->set_statu_failed($fail_data);
		}
	}
}