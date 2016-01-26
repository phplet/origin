<?php if ( ! defined('BASEPATH')) exit();
/**
 * 方法策略 -> 方法策略
 * @author qfb
 */
class Method_tactic extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index($subject_category_id = 0)
    {


        if ( ! $this->check_power('subject_category_list, subject_category_manage')) return;
        
        $subject_category_id = intval($subject_category_id);
        $subject_category_id && $subject_category = SubjectCategoryModel::get_subject_category($subject_category_id);
        if (empty($subject_category)) {
        	message('学科分类不存在');
        }
        
        $param  = array();
        
        $page = intval($this->input->get('page'));
        $per_page = intval($this->input->get('per_page'));
        $page = $page ? $page : 1;
        $page = 0;
        $per_page = $per_page ? $per_page : 20;

        $query = array('subject_category_id' => $subject_category_id);
        $order_by = 'ctime asc';
        $select_what = '*';
        
        $list = SubjectCategoryModel::get_method_tactic_list($query, $page, $per_page, $order_by, $select_what);
        foreach ($list as &$row) {
        	$row['has_relate_info'] = $this->_has_relate_info($row['id']);
        }
        
        $data['subject_category'] = $subject_category;
        $data['subject_category_id'] = $subject_category_id;
        $data['list']        = $list;
        $data['priv_manage'] = $this->check_power('subject_category_manage', FALSE);
        $data['priv_delete'] = $this->check_power('subject_category_delete', FALSE);

        // 分页
        $purl = site_url('admin/method_tactic/index/' . $subject_category_id . '/') . (count($param) ? '?'.implode('&',$param) : '');
        $total = SubjectCategoryModel::count_method_tactic_lists($query);
		$data['pagination'] = multipage($total, $per_page, $page, $purl);
    	
        // 模版
        $this->load->view('method_tactic/index', $data);
    }
    
    // 添加页面
    public function add($subject_category_id = 0)
    {        
        if ( ! $this->check_power('subject_category_manage')) return;   
         
    	$subject_category_id = intval($subject_category_id);
        $subject_category_id && $subject_category = SubjectCategoryModel::get_subject_category($subject_category_id);
        if (empty($subject_category)) {
        	message('学科分类不存在');
        }
        
        $detail = array(
        				'id' => '',
        				'name' => '',
        );
        
        $data['detail'] = $detail;
        $data['subject_category_id'] = $subject_category_id;
        $data['subject_category'] = $subject_category;
        $data['act'] = 'add';
        
        // 模版
        $this->load->view('method_tactic/edit', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
        if ( ! $this->check_power('subject_category_manage')) return;

        $id = intval($id);
        $id && $detail = SubjectCategoryModel::get_method_tactic($id);
        if (empty($detail))
        {
            message('方法策略不存在');
            return;
        }
        
        $subject_category = SubjectCategoryModel::get_subject_category($detail['subject_category_id']);

        $data['detail'] = $detail;
        $data['subject_category'] = $subject_category;
        $data['act'] = 'edit';

        // 模版
        $this->load->view('method_tactic/edit', $data);
    }
    
    // 添加操作
    public function save()
    {
        if ( ! $this->check_power('subject_category_manage')) return;
        
        $act = trim($this->input->post('act'));
        
        $id = intval($this->input->post('id'));
    	$act == 'edit' && $id && $detail = SubjectCategoryModel::get_method_tactic($id);
        if ($act == 'edit' && empty($detail))
        {
            message('方法策略不存在');
            return;
        }
        
        $subject_category_id = intval($this->input->post('subject_category_id'));
    	$subject_category_id = intval($subject_category_id);
        $subject_category_id && $subject_category = SubjectCategoryModel::get_subject_category($subject_category_id);
        if (empty($subject_category)) {
        	message('学科分类不存在');
        }
        
        $name = trim($this->input->post('name'));
        if ($name == '')
        {
            message('请填写方法策略名称！');
            return;
        }
        
        if ($act == 'edit') {
	        $query = $this->db->select('id')->get_where('method_tactic', array('name'=>$name,'id <>'=>$id,'subject_category_id'=>$subject_category_id));
	        if ($query->num_rows())
	        {
	        	message('该学科下方法策略名称已经存在！');
	        }
        } else {
	        $query = $this->db->select('id')->get_where('method_tactic', array('name'=>$name,'subject_category_id'=>$subject_category_id));
	        if ($query->num_rows())
	        {
	        	message('该学科下方法策略名称已经存在！');
	        }
        }
        $data = array(
        			'name' => $name,
        			'subject_category_id' => $subject_category_id,
        );
        $subject_data = array();
        if ($act == 'add') {
        	$data['ctime'] = date('Y-m-d H:i:s');
	        $rel = $this->db->insert('method_tactic', $data);
	        if (!$rel) {
	        	message('方法策略添加失败');
	        }
        } else {
	        $rel = $this->db->update('method_tactic', $data, array('id' => $id));
	        if (!$rel) {
	        	message('方法策略修改失败');
	        } 

        }
        if ($act == 'edit') 
      		 message('方法策略修改成功', 'admin/method_tactic/index/' . $subject_category_id);
        else 
        	message('方法策略添加成功', 'admin/method_tactic/index/' . $subject_category_id);
    }
    
    // 删除
    public function delete($id)
    {
        if ( ! $this->check_power('subject_category_delete')) return;
        
        $id = intval($id);
        $id && $row = SubjectCategoryModel::get_method_tactic($id);
        if (empty($row))
        {
        	message('方法策略不存在');
        	return;
        }
        
        if ($this->_has_relate_info($id)) {
        	message('该方法策略有关联的其他信息，无法删除');
        }

        $query1 = $this->db->get_where('subject_category', array('id' => $row['subject_category_id']), 1);
        $row1 =  $query1->row_array();
        $subject_category_id=$row1['id'];

        $this->db->delete('method_tactic', array('id'=>$id));
        
        message('方法策略删除成功', 'admin/method_tactic/index/' . $subject_category_id);        
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
	            $this->db->where_in('id', $tmp_ids)->delete('method_tactic');
        	}
            
            message('方法策略删除成功，成功：' . $count_success . ' 条, 失败：' . $count_fail . ' 条','javascript');
        }
        else
        {
            message('请选择要删除的方法策略');
        }
    }
    
    /*
     * 检查是否有与方法策略关联的信息
     * @note:
     * 	包括： 方法策略 
     */
    private function _has_relate_info($id) 
    {
    	//判断该方法策略是否  
    	$result = $this->db->query("select count(*) as count from {pre}relate_method_tactic where method_tactic_id={$id}")->row_array();
    	
    	return $result['count'] > 0;
    }
}
