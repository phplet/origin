<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 评估管理--评估模块分类控制器
 * @author TCG
 * @final 2015-08-05
 *
 */
class Evaluate_module extends A_Controller 
{
    private static $module_type_name;
    /**
     * 实现父类构造函数，载入模型
     */
    public function __construct()
    {
        parent::__construct();
        
        if ( ! $this->check_power('evaluate_template_manage')) return;
        
        self::$module_type_name = array(
                '0' => '机考总结',
                '1' => '机考学科',
                '4' => '机考班级',
                '6' => '机考教师',
                '2' => '面试学科',
                '3' => '面试总结',
                '5' => '选学选考'
        );
    }

    /**
     * 评估模块分类页面
     *
     * @return  void
     */
    public function index()        
    {
        $module_type = (int)$this->session->userdata('template_type');
        
        $res = EvaluateModuleModel::parent_module_list(0, $module_type);
        if ($res)
        {
            foreach ($res as &$item)
            {
                $item['children'] = EvaluateModuleModel::parent_module_list($item['module_id'], $module_type);
            }
        }
        
        $data = array();
        
        $data['list'] = $res;
        $data['module_type'] = $module_type;
        $data['module_type_name'] = self::$module_type_name;
        $data['subject'] = C('subject');
        
        $this->load->view('evaluate_module/index', $data);
    }
    
    //添加评估模块分类
    public function add()
    {
        $date = array();
        $date['subjects'] = C('subject');
        $date['module_type'] = (int)$this->session->userdata('template_type');
        $date['module_type_name'] = self::$module_type_name;
        
        $this->load->view('evaluate_module/add', $date);
    }
    
    //编辑评估模块分类
    public function edit($edit)
    {
        $module_info = EvaluateModuleModel::evaluate_module_info($edit);
   
        $date['module_info'] = $module_info;
        
        $module_type = (int)$this->session->userdata('template_type');
        $date['module_type'] = $module_type;
        $date['module_type_name'] = self::$module_type_name;
        
        $this->load->view('evaluate_module/add',$date);
    }
    
    //保存评估模块分类
    public function save()
    {
        $module_sort = trim($this->input->post('module_sort'));
        $module_name = trim($this->input->post('module_name'));
        $mod = trim($this->input->post('mod'));
        $edit_id = intval($this->input->post('edit_id'));
        $status = trim($this->input->post('status'));

        if ($module_sort == '')
        {
            message('模块分类序号不能为空');
            return;
        }
        
        if ($module_name == '')
        {
            message('模块分类名称不能为空');
            return;
        }
        
        if ($edit_id)
        {
            if($status == '')
            {
                message('状态不能为空');
                return;
            }
        }
        
        $module_type = (int)$this->session->userdata('template_type');
        
        if (EvaluateModuleModel::exist_module_1($module_name, $edit_id, null, $module_type))
        {
            message('模块分类名称已存在');
            return;
        }
        
        $module_date = array();
        
        if ($edit_id)
        {
            $module_date['module_sort'] = $module_sort;
            $module_date['module_name'] = $module_name;
            $module_date['status'] = $status;
        }
        else 
        {
            $module_date['module_name'] = $module_name;
            $module_date['parent_moduleid'] = 0;
            $module_date['module_sort'] = $module_sort;
            $module_date['module_type'] = $module_type;
        }
       
        
        if ($edit_id)
        {
            //编辑模块分类
            $flag = EvaluateModuleModel::update($module_date, $edit_id);
            
            if ($flag)
            {
                admin_log($mod, 'evaluate_module', $edit_id );
                message('模块分类信息更新成功', 'admin/evaluate_module/index');
            }
            else
            {
                message('模块分类信息更新失败', 'admin/evaluate_module/index');
            }
        }
        else 
        {
            //添加模块分类
            $id = EvaluateModuleModel::insert($module_date);
            
            if ($id)
            {
                admin_log($mod, 'evaluate_module', $id);
                message('添加模块分类成功', 'admin/evaluate_module/index');
            }
            else
            {
                message('添加模块分类失败');
            }
        }
    }
    
    // 操作：禁用0、启用1、删除2
    public function do_action($id = 0, $act = '')
    {
        $id = intval($id);
        $act = trim($act);
        if (empty($id)) message('参数错误');
		
        $res = FALSE;
        switch ($act)
        {
        	case 'on':
        		$res = EvaluateModuleModel::update(array('status'=>1), $id);
        		break;
        	case 'off':
        		$res = EvaluateModuleModel::update(array('status'=>0), $id);
        		break;
        	case 'del':
        		$res = EvaluateModuleModel::delete($id);
        		$action = 'remove';
        		break;
        	default: 
        		break;
        }
        
        if ($res)
        {
            if ($action)
            {
                admin_log($action, 'evaluate_module',$id);
            }
            
    	    message('操作成功');
        } else {
        	message('操作失败');        	
        } 
    }
    
    //添加评估模板
    public function add_module($parent_moduleid)
    {
        $data = array();
        $mod = 'add';
        
        $data['subjects'] = C('subject') ;
        $data['parent_moduleid'] = $parent_moduleid;
    
        $module_type = (int)$this->session->userdata('template_type');
        $data['module_type'] = $module_type;
        $data['module_type_name'] = self::$module_type_name;
    
        $this->load->view('evaluate_module/add_module', $data);
    }
    
    //编辑评估模块
    public function edit_module($edit_id)
    {
        $module_info = EvaluateModuleModel::evaluate_module_info($edit_id);
        $data['parent_moduleid'] = $module_info['parent_moduleid'];
        $data['subjects'] = C('subject');
        $data['module_info'] = $module_info;
    
        $module_type = (int)$this->session->userdata('template_type');
        $data['module_type'] = $module_type;
        $data['module_type_name'] = self::$module_type_name;
    
        $this->load->view('evaluate_module/add_module',$data);
    }
    
    //保存评估模块
    public function save_module()
    {
        $module_sort = intval($this->input->post('module_sort'));
        $module_name = trim($this->input->post('module_name'));
        $module_code = trim($this->input->post('module_code'));
        $module_subjects = $this->input->post('module_subjects');
        $parent_moduleid = intval($this->input->post('parent_moduleid'));
        $edit_id = intval($this->input->post('edit_id'));
        $status = intval($this->input->post('status'));
        $module_subjectid = '';
    
        if ($module_subjects)
        {
            $module_subjectid = ',' . implode(',', $module_subjects) . ',';
        }
    
        if ($module_name == '')
        {
            message('模块名称不能为空');
            return;
        }
    
        if (!$edit_id && $module_code == '')
        {
            message('模块编码不能为空');
        }
        
        $module_type = (int)$this->session->userdata('template_type');
    
        if (EvaluateModuleModel::exist_module($module_name, $edit_id, $parent_moduleid, $module_code, $module_type))
        {
            message('模块名称或模块编码已存在');
        }
    
        $module_date = array();
        if ($edit_id)
        {
            $module_date['module_sort'] = $module_sort;
            $module_date['module_name'] = $module_name;
            $module_date['module_subjectid'] = $module_subjectid;
            $module_date['status'] = (int) $status;
        }
        else 
        {
            $module_date['module_name'] = $module_name;
            $module_date['module_subjectid'] = $module_subjectid;
            $module_date['module_code'] = $module_code;
            $module_date['module_name'] = $module_name;
            $module_date['module_name'] = $module_name;
            $module_date['module_sort'] = $module_sort;
            $module_date['parent_moduleid'] = $parent_moduleid;
            $module_date['module_type'] = $module_type;;
        }
        
        if ($edit_id)
        {
            //编辑模块信息
            $flag = EvaluateModuleModel::update($module_date, $edit_id);
            if ($flag)
            {
                $mod = '编辑模块信息成功';
                admin_log($mod, 'module_manage', $edit_id);
                message('模块信息更新成功' , '/admin/evaluate_module/index');
            }
            else
            {
                message('模块信息更新失败' , '/admin/evaluate_module/index');
            }
        }
        else 
        {
            //添加模块信息
            $id = EvaluateModuleModel::insert($module_date);
            if ($id)
            {
                admin_log($mod, 'module_manage', $id);
                message('模块添加成功' , '/admin/evaluate_module/index');
            }
            else
            {
                message('模块添加失败' , '/admin/evaluate_module/index');
            }
        }
    }
}
