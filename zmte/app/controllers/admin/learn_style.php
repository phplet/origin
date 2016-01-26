<?php
/**
 * 学习风格管理Controller
 * @author TCG
 * @final  2015-10-22 09:30 
 */
class Learn_style extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        $size   = C('default_perpage_num');
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        $total = LearnStyleModel::learnStyleListCount();
        if ($total)
        {
            $data['list'] = LearnStyleModel::learnStyleList();
        }
        
        // 分页
        $purl = site_url('admin/learn_style/index/');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['priv_edit'] = $this->check_power_new('learn_style_edit', FALSE);
        $data['priv_delete'] = $this->check_power_new('learn_style_remove', FALSE);
        $data['priv_attr_manage'] = $this->check_power_new('learn_style_attribute_list', FALSE);
        
        $this->load->view('learn_style/index', $data);
    }
    
    public function edit($learnstyle_id = null)
    {
        if ($learnstyle_id)
        {
            $data['info'] = LearnStyleModel::learnStyleInfo($learnstyle_id);
        }
        else 
        {
            $data['knowledge_list'] = KnowledgeModel::get_knowledge_children_list(14);
            $data['learnstyle_knowledgeids'] = LearnStyleModel::learnStyleKnowledgeid();
        }
        
        $this->load->view('learn_style/edit', $data);
    }
    
    public function save()
    {
        $param['learnstyle_id'] = intval($this->input->post('learnstyle_id'));
        $param['learnstyle_knowledgeid'] = intval($this->input->post('learnstyle_knowledgeid'));
        $param['learnstyle_explain'] = htmlspecialchars(trim($this->input->post('learnstyle_explain')));
        
        try 
        {
            if ($param['learnstyle_id'])
            {
                $result = LearnStyleModel::setLearnStyle($param);
                admin_log('edit', 'learn_style', $param['learnstyle_id']);
            }
            else 
            {
                $result = LearnStyleModel::addLearnStyle($param);
                admin_log('add', 'learn_style', $result);
            }
            
            message(($param['learnstyle_id'] ? '编辑' : '新增') . ($result ? '成功' : '失败'), 
                '/admin/learn_style/index');
        }
        catch (Exception $e)
        {
            message($e->getMessage());
        }
    }
    
    public function remove($learnstyle_id)
    {
        if (!$learnstyle_id 
            && $learnstyle_id = $this->input->get('learnstyle_ids'))
        {
            $learnstyle_id = implode(',', $learnstyle_id);
        }
        
        if (!Validate::isJoinedIntStr($learnstyle_id))
        {
            message('请选择需要删除的内化过程');
        }
        
        if (LearnStyleModel::removeLearnStyle($learnstyle_id))
        {
            admin_log('delete', 'learn_style', $learnstyle_id);
            message('删除成功', '/admin/learn_style/index');
        }
        else
        {
            message('删除失败', '/admin/learn_style/index');
        }
    }
    
    public function attribute_list($learnstyle_id = null)
    {
        if (!Validate::isInt($learnstyle_id)
            || $learnstyle_id <= 0)
        {
            return;
        }
        
        $data['learnstyle'] = LearnStyleModel::learnStyleInfo($learnstyle_id);
        if (!$data['learnstyle'])
        {
            message('学习风格不存在');
        }
        
        $data['list'] = LearnStyleModel::learnStyleAttributeList($learnstyle_id);
        
        $data['priv_edit'] = $this->check_power_new('learn_style_edit_attribute', FALSE);
        $data['priv_delete'] = $this->check_power_new('learn_style_remove_attribute', FALSE);
        
        $this->load->view('learn_style/attribute_list', $data);
    }
    
    public function edit_attribute($lsattr_learnstyleid = null, $lsattr_value = null)
    {
        if (!Validate::isInt($lsattr_learnstyleid)
            || $lsattr_learnstyleid <= 0)
        {
            return ;
        }
        
        $data['learnstyle'] = LearnStyleModel::learnStyleInfo($lsattr_learnstyleid);
        if (!$data['learnstyle'])
        {
            message('学习风格不存在');
        }
        
        if (in_array($lsattr_value, array(1, 2)))
        {
            $data['info'] = LearnStyleModel::learnStyleAttributeInfo(
                $lsattr_learnstyleid, $lsattr_value);
        }
        
        $this->load->view('learn_style/edit_attribute', $data);
    }
    
    public function save_attribute()
    {
        $param['lsattr_learnstyleid'] = intval($this->input->post('lsattr_learnstyleid'));
        $param['lsattr_value'] = intval($this->input->post('lsattr_value'));
        $param['lsattr_name'] = htmlspecialchars(trim($this->input->post('lsattr_name')));
        $param['lsattr_define'] = htmlspecialchars(trim($this->input->post('lsattr_define')));
        $param['lsattr_advice'] = htmlspecialchars(trim($this->input->post('lsattr_advice')));
        
        try
        {
            if (LearnStyleModel::learnStyleAttributeInfo(
                $param['lsattr_learnstyleid'], $param['lsattr_value']))
            {
                $result = LearnStyleModel::setLearnStyleAttribute($param);
                admin_log('edit', 'learn_style_attribute', 
                    $param['lsattr_learnstyleid'] . '-' . $param['lsattr_value']);
            }
            else
            {
                $result = LearnStyleModel::addLearnStyleAttribute($param);
                admin_log('add', 'learn_style_attribute', 
                    $param['lsattr_learnstyleid'] . '-' . $param['lsattr_value']);
            }
        
            message('操作' . ($result ? '成功' : '失败'),
                '/admin/learn_style/attribute_list/' . $param['lsattr_learnstyleid']);
        }
        catch (Exception $e)
        {
            message($e->getMessage());
        }
    }
    
    public function remove_attribute($lsattr_learnstyleid, $lsattr_value)
    {
        if (!Validate::isInt($lsattr_learnstyleid)
            || $lsattr_learnstyleid <= 0
            || !in_array($lsattr_value, array(1, 2)))
        {
            return ;
        }
    
        if (LearnStyleModel::removeLearnStyleAttribute(
            $lsattr_learnstyleid, $lsattr_value))
        {
            admin_log('delete', 'learn_style_attribute', 
                $lsattr_learnstyleid . '-' . $lsattr_value);
            message('删除成功', '/admin/learn_style/attribute_list/' . $lsattr_learnstyleid);
        }
        else
        {
            message('删除失败', '/admin/learn_style/attribute_list/' . $lsattr_learnstyleid);
        }
    }
}