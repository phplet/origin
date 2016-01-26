<?php if ( ! defined('BASEPATH')) exit();

class Comparison_type extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index($mode = '')
    {
    	if ( ! $this->check_power('comparison_list,comparison_manage')) return;
    	
        $size   = C('admin_page_size');
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $order_by = 'cmp_type_id DESC';
        $select_what = 'cmp_type_id,cmp_type_name,grade_id,class_id,subject_id,cmp_type_flag,updatetime,addtime';
		
        $query = $param = $search = array();
        
        // 查询条件
        $mode = $mode=='trash' ? 'trash' : '';
        $search['type_flag'] = $this->input->get('type_flag');
        if ($mode == 'trash') {
        	$query['cmp_type_flag'] = -1;
        } else {
        	if (strlen($search['type_flag'])) {
        		$query['cmp_type_flag'] = intval($search['type_flag']); 
        	} else {
        		$query['cmp_type_flag'][] = array('>', -1);
        	}
        }
        if ($search['grade_id'] = (int)$this->input->get('grade_id')) {
        	$param[] = "grade_id=".$search['grade_id'];
        	$query['grade_id'] = $search['grade_id'];
        }
        if ($search['class_id'] = (int)$this->input->get('class_id')) {
            $param[] = "class_id=".$search['class_id'];
            $query['class_id'] = $search['class_id'];
        }
        if ($search['subject_id'] = (int)$this->input->get('subject_id')) {
            $param[] = "subject_id=".$search['subject_id'];
            $query['subject_id'] = $search['subject_id'];
        }
       
        if ($search['keyword'] = trim($this->input->get('keyword')))  {            
            $param[] = "keyword=".urlencode($search['keyword']);
            $query['keyword'] = $search['keyword'];
        }
		
        // 获取数据
        $total = ComparisonTypeModel::get_comparison_type_count($query);
        $list   = array();
        if ($total) {
            $list = ComparisonTypeModel::get_comparison_type_list($query, $page, $size, $order_by, $select_what);
        }

        $data['mode'] = &$mode;
        $data['list'] = &$list;
        $data['search']= &$search;

        // 分页
        $purl = site_url('admin/comparison_type/index/') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);
		
		// 学科、类型
		$data['grades'] = C('grades');
        $data['subjects'] = CpUserModel::get_allowed_subjects();
        $data['class_list'] = ClassModel::get_class_list();
        $data['priv_manage'] = $this->check_power('comparison_manage', FALSE);

        // 模版
        $this->load->view('comparison_type/index', $data);
    }
    
    // 添加页面
    public function add()
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	$detail = array(
    		'cmp_type_id' => '',
    		'cmp_type_name' => '',
    		'grade_id' => '',
    	    'class_id' => '',
    		'subject_id' => '',
    		'introduce' => '',
    		'cmp_type_flag' => 1,
    	);
    	
    	$data['act'] = 'add';
    	$data['detail'] = &$detail;
    	
    	// 学科、类型
    	$data['grades'] = C('grades');
    	$data['subjects'] = CpUserModel::get_allowed_subjects();
    	$data['class_list'] = ClassModel::get_class_list();
    	
        // 模版
        $this->load->view('comparison_type/edit', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
        $id = intval($id);
        $id && $detail = ComparisonTypeModel::get_comparison_type_by_id($id);
        if (empty($detail))
        {
            message('分类不存在');
        }
		
        $data['act'] = 'edit';
        $data['detail'] = &$detail;

        // 学科、类型
        $data['grades'] = C('grades');
        $data['subjects'] = CpUserModel::get_allowed_subjects();
        $data['class_list'] = ClassModel::get_class_list();
        
        // 模版
        $this->load->view('comparison_type/edit', $data);
    }
    
    // 保存(添加、更新)
    public function save()
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	$act = $this->input->post('act');
    	$act = $act=='add' ? 'add' : 'edit';    	
    	$id  = null;
    	
    	if ($act == 'edit') 
    	{   
    		$id = (int)$this->input->post('id');
    		$id && $old_info = ComparisonTypeModel::get_comparison_type_by_id($id);
    		if (empty($old_info))
    			message('分类信息不存在');
    	}
    	
    	$data = array(
    		'cmp_type_name' => html_escape(trim($this->input->post('type_name'))),
    		'grade_id' => (int)$this->input->post('grade_id'),
    		'class_id' => (int)$this->input->post('class_id'),
    		'subject_id' => (int)$this->input->post('subject_id'),
    		'introduce' => html_escape($this->input->post('introduce')),
    	);
    	
    	$message = array();
    	if (empty($data['cmp_type_name']))
        {
            message('请填写分类名称');
            return;
        }
        else {
            $tid = ComparisonTypeModel::get_comparison_type_by_name($data['cmp_type_name'], 'cmp_type_id');
            if ($tid && $tid != $id) 
            {
                message('分类名称已存在');
                return;
    		}
    	}

        if (empty($data['grade_id']))
        {
            message('请选择考试年级');
            return;
        }
            
    	if (empty($data['class_id'])){
    		$message[] = '请选择考试类型';
            message('请选择考试类型');
            return;
        }

        if (empty($data['subject_id']))
        {
            message('请选择考试学科');
            return;
        }
    	
        if ($this->input->post('type_flag') !== FALSE) 
        {
    		$data['cmp_type_flag'] = $this->input->post('type_flag') ? 1 : 0;    		
    	}
    	
    	$res = FALSE;
    	if ($act == 'add')
    	{
    		$actname = '添加';
                $insert_id = 0;
    		$res = ComparisonTypeModel::insert($data, $insert_id);
    		if ($res) {
    			$id = $insert_id;
    		}
    	}
    	else 
    	{
    		$actname = '编辑';
    		$res = ComparisonTypeModel::update($id, $data);
    	}
    	
    	if ($res)
    	{
    		admin_log($act, 'comparison_type', $id);    	
        	message('分类'.$actname.'成功', 'admin/comparison_type/index'); 
    	}
    	else
    	{
    		message('分类'.$actname.'失败');
    	}
    }
    
    // 操作：禁用0、启用1、删除2
    public function do_action($id=0, $act='')
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
        $id = intval($id);
        $act = trim($act);
        if (empty($id)) message('参数错误');
		
        $res = FALSE;
        switch ($act)
        {
        	case 'on':
        	case 'restore':
        		$res = ComparisonTypeModel::update($id, array('cmp_type_flag'=>1));
        		break;
        	case 'off':
        		$res = ComparisonTypeModel::update($id, array('cmp_type_flag'=>0));
        		break;
        	case 'delete':        		
        		$res = ComparisonTypeModel::update($id, array('cmp_type_flag'=>-1));
        		break;
        	case 'remove':
        		$res = ComparisonTypeModel::delete($id);
        		$action = 'remove';
        		break;
        	default: 
        		break;
        }
        
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url))
        	$back_url = 'admin/comparison_type/index';

        if ($res){
        	if ($action)
        		admin_log($action, 'comparison_type', $id);
        	message('分类操作成功', $back_url);
        } else {
        	message('分类操作失败', $back_url);        	
        }   
    }
    
    // 批量操作
    public function do_batch($act = '')
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	$act = trim($act);
        $ids = $this->input->post('ids');
        
        $res = FALSE;
        if ($ids && is_array($ids))
        {
        	$ids = my_intval($ids);
        	switch ($act)
        	{        		
        		case 'on': 
        		case 'restore' :
        			$res = ComparisonTypeModel::update($ids, array('cmp_type_flag'=>1));   
        			break; 
		        case 'off':
		        	$res = ComparisonTypeModel::update($ids, array('cmp_type_flag'=>0));
		        	break;
		        case 'delete':
		        	$res = ComparisonTypeModel::update($ids, array('cmp_type_flag'=>-1));
		        	break;
		        case 'remove':
        			$res = ComparisonTypeModel::delete($ids);
        			admin_log('remove', 'comparison_type', implode(',', $ids));
		        	break;
		        default: 
		        	break;	
        	}
        	if ($res)
            	message('批量操作成功！', 'admin/comparison_type/index');
        	else
        		message('分类操作失败！', 'admin/comparison_type/index');
        }
        else
        {
            message('请选择要删除的分类');
        }
    }

}
