<?php if ( ! defined('BASEPATH')) exit();
/**
 * 后台管理->题库及分类管理->方法策略分类
 * @author qfb
 */
class Subject_category extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index()
    {
        if ( ! $this->check_power('subject_category_list, subject_category_manage')) return;
        
        $param  = array();
        
        $page = intval($this->input->get('page'));
        $per_page = intval($this->input->get('per_page'));
        $page = $page ? $page : 1;
        $page = 0;
        $per_page = $per_page ? $per_page : 20;

        $query = array();
        $order_by = 'ctime asc';
        $select_what = '*';
        
        $list = SubjectCategoryModel::get_subject_category_list($query, $page, $per_page, $order_by, $select_what);
        foreach ($list as $k=>&$row) {

        	$subjects = SubjectCategoryModel::get_subject_category_subjects($row['id'], true);
        	$tmp_subject = array();
        	foreach ($subjects as $subject) {
        		$tmp_subject[] = $subject['subject_name'];
        	}
        	$row['subject'] = trim(implode('、', $tmp_subject),'、');
        	
        	$row['has_relate_info'] = $this->_has_relate_info($row['id']);
        }
        
        $data['list']       = $list;
        $data['priv_manage'] = $this->check_power('subject_category_manage', FALSE);
        $data['priv_delete'] = $this->check_power('subject_category_delete', FALSE);

        // 分页
        $purl = site_url('admin/subject_category/index/') . (count($param) ? '?'.implode('&',$param) : '');
        $total = SubjectCategoryModel::count_subject_category_lists($query);
		$data['pagination'] = multipage($total, $per_page, $page, $purl);
    	
        // 模版
        $this->load->view('subject_category/index', $data);
    }
    
    // 添加页面
    public function add()
    {        
        if ( ! $this->check_power('subject_category_manage')) return;        
        
        $detail = array(
        				'id' => '',
        				'name' => '',
        				'subjects' => array(array('subject_id' => '0'))
        );
        
        $data['detail'] = $detail;
        $data['act'] = 'add';
        $data['subjects'] = SubjectCategoryModel::get_unadded_subjects();
        
        // 模版
        $this->load->view('subject_category/edit', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
        if ( ! $this->check_power('subject_category_manage')) return;

        $id = intval($id);
        $id && $detail = SubjectCategoryModel::get_subject_category($id);
        if (empty($detail))
        {
            message('方法策略分类不存在');
            return;
        }
        
        //获取关联学科
        $detail['subjects'] = SubjectCategoryModel::get_subject_category_subjects($id, false, true);

        $data['detail'] = $detail;
        $data['act'] = 'edit';
        $data['subjects'] = SubjectCategoryModel::get_unadded_subjects($id);

        // 模版
        $this->load->view('subject_category/edit', $data);
    }
    
    // 添加操作
    public function save()
    {
        if ( ! $this->check_power('subject_category_manage')) return;
        
        $act = trim($this->input->post('act'));
        
        $id = intval($this->input->post('id'));
    	$act == 'edit' && $id && $detail = SubjectCategoryModel::get_subject_category($id);
        if ($act == 'edit' && empty($detail))
        {
            message('方法策略分类不存在');
            return;
        }
        
        $name = trim($this->input->post('name'));
        if ($name == '')
        {
            message('请填写方法策略分类名称！');
            return;
        }

        if ($act == 'edit') {
            
        	
        	
        	$subject_id = $this->input->post('subject_id');
        	
        	$subject_id =array_filter(array_unique($subject_id));
        	$subject_id_str=implode(',',$subject_id);

        
        	$subject_category_name=array();
        	$result = $this->db->query("select b.name from {pre}subject_category_subject a
                                                        left join   {pre}subject_category  b on a.subject_category_id=b.id
        	                                            where  a.subject_id in ($subject_id_str) and a.subject_category_id<>'$id' ")->result_array();
        	if($result)
        	{
        	
        	    foreach ($result  as $row)
        	    {
        	         $subject_category_name[]=$row['name '];
        	    }

        	}

        	if (in_array($name, $subject_category_name))
        	{
        		message('该方法策略分类名称已经存在！');
        	}
        	
        	
        } else {

            $subject_id = $this->input->post('subject_id');
             
            $subject_id =array_filter(array_unique($subject_id));
            $subject_id_str=implode(',',$subject_id);
            
 
            $result = $this->db->query("select b.name from {pre}subject_category_subject a
                                                         left join   {pre}subject_category  b on a.subject_category_id=b.id
                                                         where  a.subject_id  in ($subject_id_str)  ")->result_array();
            $subject_category_name=array();
            if($result)
            {           
                foreach ($result  as $row)
                {
                    $subject_category_name[]=$row['name '];
                }
             
            }
            
        	if (in_array($name, $subject_category_name))
        	{
        		message('该方法策略分类名称已经存在！');
        	}
        }
        
        $subject_id = $this->input->post('subject_id');
        if (!is_array($subject_id) || !count($subject_id)) {
        	message('请选择学科');
        }
        
        $subject_id =array_filter(array_unique($subject_id));
        
        


        $data = array(
        		'name' => $name,
        );
        $subject_data = array();
        if ($act == 'add') {
        	$data['ctime'] = date('Y-m-d H:i:s');
        	$rel = $this->db->insert('subject_category', $data);
        	if ($rel) {
        		$inserted_id = $this->db->insert_id();
        		foreach ($subject_id as $v) {
        			$subject_data[] = array(
        					'subject_category_id' => $inserted_id,
        					'subject_id' => $v,
        			);
        		}
        		$this->db->insert_batch('subject_category_subject', $subject_data);
        		admin_log('add', 'subject_category', $inserted_id);
        	} else {
        		message('方法策略分类添加失败');
        	}
        } else {
        	$rel = $this->db->update('subject_category', $data, array('id' => $id));
        	if ($rel) {
	        	$this->db->trans_start();
	        	
        		$insert_data = array();
        		$query = array('subject_category_id' => $id);
        		$old_subject_categories = SubjectCategoryModel::get_subject_category_subjects($id, true, true);
        		foreach ($subject_id as $v) {
        			if(!isset($old_subject_categories[$v]))
        			{
        				$insert_data[] = array(
        						'subject_category_id' => $id,
        						'subject_id' => $v,
        				);
        			}
        			unset($old_subject_categories[$v]);
        		}
        
        		if (count($insert_data)) {
        			$this->db->insert_batch('subject_category_subject', $insert_data);
        		}
        
        		$delete_ids = array_keys($old_subject_categories);
        		if (count($delete_ids)) {
        			$this->db->where('subject_category_id', $id)->where_in('subject_id', $delete_ids)->delete('subject_category_subject');
        		}
        		
        		admin_log('edit', 'subject_category', $id);
        		
	        	$this->db->trans_complete();
        	} else {
        		message('方法策略分类修改失败');
        	}
        }
         if ($act == 'add')
             message('方法策略分类添加成功', 'admin/subject_category/index');
         else
             message('方法策略分类修改成功', 'admin/subject_category/index');
    }
    
    // 删除
    public function delete($id)
    {
        if ( ! $this->check_power('subject_category_delete')) return;
        
        $id = intval($id);
        $id && $row = SubjectCategoryModel::get_subject_category($id);
        if (empty($row))
        {
        	message('方法策略分类不存在');
        	return;
        }
        
        if ($this->_has_relate_info($id)) {
        	message('该方法策略分类有关联的方法策略分类，无法删除');
        }
        
        $this->db->delete('subject_category', array('id'=>$id));
        $this->db->delete('subject_category_subject', array('subject_category_id'=>$id));


        admin_log('delete', 'subject_category', $id);
        message('方法策略分类删除成功', 'admin/subject_category/index/');        
    }
    
    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('subject_category_delete')) return;

        $ids = (array)$this->input->post('ids');
        $count_success = 0;
        $count_fail = 0;
        if ($ids)
        {
        	$tmp_ids = array();
        	foreach ($ids as $id) {
        		if ($this->_has_relate_info($id)) {
        			$count_fail++;
        		} else {
        			$tmp_ids[] = $id;
        			$count_success++;
        		}
        	}
        	        
        	if (count($tmp_ids)) {
                    $this->db->where_in('id', $tmp_ids)->delete('subject_category');


    	        $this->db->where_in('subject_category_id', $tmp_ids)->delete('subject_category_subject');
    	        
	            admin_log('delete', 'subject_category', implode(',', $tmp_ids));
        	}
        	
        	message('方法策略分类删除成功，成功：' . $count_success . ' 条, 失败：' . $count_fail . ' 条', 'admin/subject_category/index');
        }
        else
        {
            message('请选择要删除的方法策略分类');
        }
    }
    
    /*
     * 检查是否有与方法策略分类关联的信息
     * @note:
     * 	包括： 方法策略分类 
     */
    private function _has_relate_info($id) 
    {
    	$id = intval($id);
    	if (!$id) {
    		return false;
    	}
    	
    	$query = array(
					'subject_category_id' => $id    				
    	);
    	
    	$count_method_tactics = SubjectCategoryModel::count_method_tactic_lists($query);
    	if ($count_method_tactics > 0) {
    		return true;
    	} 
    		
    	return false;
    }
}
