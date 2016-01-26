<?php if ( ! defined('BASEPATH')) exit();
/**
 *
 * MINI测报告管理-MINI测报告
 * @author tcg
 * @final 2015-07-26
 *
 */
class demo_paper extends A_Controller
{
	public function __construct()
	{
		parent::__construct();
		
		if ( ! $this->check_power('demo_paper_report,demo_paper_list')) return;
	}
	
	/**
	 * MINI测报告列表
	 */
	public function index()
	{
	    
		$dir = $this->_get_cache_root_path() . "/html/demo_report";
		//echo $dir;
		$files = $this->my_scandir($dir);
		
		$count = 0;
		
		$uids = intval($this->input->get('uid'));
		$size   = 15;
		$page   = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval($_GET['page']) : 1;
		$offset = ($page - 1) * $size;
		
		$search = array();
		$param = array();
		$search['uid'] = $uids;
		if ($uids > 0)
		{
			$param[] = "uid=".$search['uid'];
		}
		
		$data = array();
		$students = array();
		$grades = C('grades');
		$subjects = C('subject');
		if ($files) 
		{
			foreach ($files as $exam_pid => $student)
			{
				if ($student)
				{
					foreach ($student as $uid => $report_html)
					{
						if($uids && $uids != $uid)
						{
							continue;
						}
						
						if ($exam_pid > 0)
						{
							$sql = "select e.exam_id, e.subject_id,etp.ctime,e.grade_id from {pre}exam e 
							        left join {pre}exam_test_paper etp on e.exam_id = etp.exam_id 
							        where e.exam_pid = $exam_pid and etp.uid = $uid 
                                    order by etp.ctime desc";
							$result = $this->db->query($sql)->result_array();
							foreach ($result as $item)
							{
							    if (!file_exists("$dir/$exam_pid/$uid/{$item['subject_id']}.html"))
							    {
							        continue;
							    }
							    
							    if (!isset($students[$uid]))
							    {
							        //学生信息
							        $sql = "select s.uid, s.last_name, s.first_name, 
							        s.exam_ticket, s.grade_id, sch.school_name
							        from {pre}student s
							        left join {pre}school sch on s.school_id = sch.school_id
							        where uid = {$uid}";
							        $students[$uid] = $this->db->query($sql)->row_array();
							        
							        $count++;
							    }
							    
							    $students[$uid]['grade'] = $grades[$students[$uid]['grade_id']];
							    
							    $sql1 = "select MAX(etp.ctime) as maxctime from {pre}exam e left join {pre}exam_test_paper etp on e.exam_id = etp.exam_id where e.exam_pid=" . $exam_pid ." and etp.uid = ".$uid."  ";
							    $result1 = $this->db->query($sql1)->row_array();
							    	
							    
							    $students[$uid]['ctime'] = $result1['maxctime'];
							    
							    $students[$uid]['exam_pid'] = $exam_pid;
							    
							    $students[$uid]['grade'] = $grades[$students[$uid]['grade_id']];
							    //$students[$uid]['ctime'] = $item['ctime'];

								$students[$uid]['exam'][$exam_pid][$item['exam_id']]['exam_id'] = $item['exam_id'];
								$students[$uid]['exam'][$exam_pid][$item['exam_id']]['name'] = $grades[$item['grade_id']] . $subjects[$item['subject_id']];
								$students[$uid]['exam'][$exam_pid][$item['exam_id']]['subject_id'] = $item['subject_id'];
								$students[$uid]['exam'][$exam_pid][$item['exam_id']]['ctime'] = date('Y-m-d H:i', $item['ctime']);
							}
						}
					}
				}
			}
			
			//krsort($students);
			//print_r($students);
			
			uasort($students, 'cmp');
			$data['student'] = array_slice($students,$offset,$size);
		}
		
		$data['search'] = $search;
		$data['priv_delete'] = $this->check_power('demo_report_delete', false);
		
		// 分页
		$purl = site_url('admin/demo_paper/index') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($count, $size, $page, $purl);
		
		$this->load->view('demo_paper/index', $data);
	}

	/**
	 * MINI测报告展示页面
	 */
	function show_report()
	{
		$uid = intval($this->input->get('uid'));
		$exam_pid = intval($this->input->get('exam_pid'));
		$subject_id= intval($this->input->get('subject_id'));
		
		if(!$subject_id || !$uid || !$exam_pid)
		{
			message('获取MINI测报告失败');
		}
		
		$this->load->model('demo/report/general_model');
		$content = $this->general_model->get_html_content($exam_pid, $subject_id, $uid);
		if ($content)
		{
			echo $content;
		}
		else
		{
			message('获取MINI测报告失败');
		}
	}
	
	/**
	 * 删除MINI测报告
	 */
	function remove_report()
	{
	    if ( ! $this->check_power('demo_paper_report,demo_report_delete')) return;
	    
	    if ($this->input->post('type') == 'del')
	    {
	        $data = $this->input->post('ids');
	        if (!$data)
	        {
	            message('删除MINI测报告失败');
	        }
	        
	        $this->db->trans_start();
	        $result = true;
	        foreach ($data as $item)
	        {
	            if (!$result)
	            {
	                break;
	            }
	            
	            $id = explode('_', $item);
	            if ($result)
	            {
	               $result = $this->db->delete('exam_test_result',
	                    array('exam_pid'=>$id[0], 'uid'=>$id[1]));
	            }
	            if ($result)
	            {
	                $result = $this->db->delete('exam_test_paper',
	                        array('exam_pid'=>$id[0], 'uid'=>$id[1]));
	            }
	        }
	        
	        if ($result)
	        {
	            $this->db->trans_complete();
	        
	            foreach ($data as $item)
	            {
	                $id = explode('_', $item);
	                $document_dir = $this->_get_cache_root_path() .
	                                "/html/demo_report/{$id[0]}/";
	                $dir = $document_dir . $id[1];
	                self::rm_dir($dir);
	            }
	            
	            admin_log('delete', 'demo_paper_report');
	                 
                message('删除MINI测报告成功', 'admin/demo_paper/index');
	        }
	        else
	        {
	           $this->db->trans_rollback();
	        
	           message('删除MINI测报告失败');
	        }
	    }
	    else
	    {
	        $uid = intval($this->input->get('uid'));
	        $exam_pid = intval($this->input->get('exam_pid'));
	        $exam_id = intval($this->input->get('exam_id'));
	        $subject_id= intval($this->input->get('subject_id'));
	         
	        if(!$uid || !$exam_pid || !$exam_id)
	        {
	            message('删除MINI测报告失败');
	        }
	         
	        $this->db->trans_start();
	         
	        $result = $this->db->delete('exam_test_result', 
	                array('exam_id'=>$exam_id, 'uid'=>$uid));
	        if ($result)
	        {
	            $result = $this->db->delete('exam_test_paper', 
	                    array('exam_id'=>$exam_id, 'uid'=>$uid));
	        }
	         
	        if ($result)
	        {
	            $this->db->trans_complete();
	             
	            $document_dir = $this->_get_cache_root_path() . 
	                            "/html/demo_report/$exam_pid/";
                $dir = $document_dir . $uid;
                if ($subject_id)
                {
                    $dir .= "/$subject_id.html";
                }
                self::rm_dir($dir);
                
                admin_log('delete', 'demo_paper_report');
	        
	            message('删除MINI测报告成功', 'admin/demo_paper/index');
	        }
	        else
	        {
	            $this->db->trans_rollback();
	             
	            message('删除MINI测报告失败');
	        }
	    }
	}
    
    /**
     * 获取项目根目录
     */
    private function _get_cache_root_path()
    {
    	return realpath(dirname(APPPATH)) . '/cache';
    }
    
    /**
     * 读取文件夹
     */
    private function my_scandir($dir)
    {
    	$files = array();
    	if(is_dir($dir))
    	{
    		if($handle = opendir($dir))
    		{
    			while(($file = readdir($handle)) !== false)
    			{
    				if($file != "." && $file != "..")
    				{
    					if(is_dir($dir . "/" . $file))
    					{
    						$files[$file] = $this->my_scandir($dir ."/". $file);
    					}
    					else
    					{   
    					    //$files[] = $file;
    					    
    						$files[filemtime($dir."/".$file)] = $file;
    					}
    				}
    			}
    			closedir($handle);
    		}
    	}
    	
    	krsort($files);
    	
    	//print_r($files);
    	//exit;
    	//end
    	return $files;
    }
    
    private static function rm_dir($dir)//{{{
    {
        $ret_val = false;
        if (is_dir($dir))
        {
            $d = @dir($dir);
            if ($d)
            {
                while (false !== ($entry = $d->read()))
                {
                    if ($entry != '.' && $entry != '..')
                    {
                        $entry = $dir . '/' . $entry;
                        if (is_dir($entry))
                        {
                            self::rm_dir($entry);
                        }
                        else
                        {
                            @unlink($entry);
                        }
                    }
                }
                $d->close();
            }
    
            if ($is_del)
            {
                @rmdir($dir);
            }
        }
        else
        {
            $ret_val = @unlink($dir);
        }
    
        return $ret_val;
    }
}