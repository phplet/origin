<?php
/**
 * 职业兴趣管理Controller
 * @author TCG
 * @final  2015-10-22 09:30 
 */
class Vocational_interest extends A_Controller
{
    //职业兴趣
    protected static $pr_subjectid = 15;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        $param['pr_subjectid'] = self::$pr_subjectid;
        $param['knowledge_name'] = trim($this->input->get('knowledge_name'));
        $param['pr_knowledgeid'] = implode(',', $this->input->get('pr_knowledgeid'));
        
        $size   = C('default_perpage_num');
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        $total = ProfessionRelatedModel::professionRelatedListCount($param);
        if ($total)
        {
            $data['list'] = ProfessionRelatedModel::professionRelatedList($param, $page, $size);
            $profession_ids = array();
            foreach ($data['list'] as &$item)
            {
                $item['pr_professionid'] = json_decode($item['pr_professionid'], true);
                $profession_ids = array_merge($profession_ids, $item['pr_professionid']);
            }
            
            $profession_id_str = implode(',', array_unique($profession_ids));
            
            $data['profession'] = ProfessionModel::professionInfo($profession_id_str);
        }
        
        // 分页
        $purl = site_url('admin/vocational_interest/index/') . ($param ? '?'.implode('&',$param) : '');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['search'] = $param;
        $data['priv_edit'] = $this->check_power_new('vocational_interest_edit', FALSE);
        $data['priv_delete'] = $this->check_power_new('vocational_interest_remove', FALSE);
        
        $this->load->view('vocational_interest/index', $data);
    }
    
    public function edit($pr_id = null)
    {
        if ($pr_id)
        {
            $data['info'] = ProfessionRelatedModel::professionRelatedInfo($pr_id);
        }
        else 
        {
            $data['knowledge_list'] = KnowledgeModel::get_knowledge_children_list(self::$pr_subjectid);
            $data['pr_knowledgeids'] = ProfessionRelatedModel::professionRelatedKnowledgeid(self::$pr_subjectid);
        }
        
        $data['profession'] = ProfessionModel::professionList(null, null, 1000);
        
        $this->load->view('vocational_interest/edit', $data);
    }
    
    public function save()
    {
        $param['pr_id'] = intval($this->input->post('pr_id'));
        $param['pr_subjectid'] = self::$pr_subjectid;
        $param['pr_knowledgeid'] = intval($this->input->post('pr_knowledgeid'));
        $param['pr_explain'] = htmlspecialchars(trim($this->input->post('pr_explain')));
        $param['pr_professionid'] = $this->input->post('pr_professionid');
        
        try 
        {
            if ($param['pr_id'])
            {
                $result = ProfessionRelatedModel::setProfessionRelated($param);
                admin_log('edit', 'vocational_interest', $param['pr_id']);
            }
            else 
            {
                $result = ProfessionRelatedModel::addProfessionRelated($param);
                admin_log('add', 'vocational_interest', $result);
            }
            
            message(($param['pr_id'] ? '编辑' : '新增') . ($result ? '成功' : '失败'), 
                '/admin/vocational_interest/index');
        }
        catch (Exception $e)
        {
            message('职业兴趣' . $e->getMessage());
        }
    }
    
    public function remove($pr_id)
    {
        if (!$pr_id 
            && $pr_id = $this->input->get('pr_ids'))
        {
            $pr_id = implode(',', $pr_id);
        }
        
        if (!Validate::isJoinedIntStr($pr_id))
        {
            message('请选择需要删除的职业兴趣');
        }
        
        if (ProfessionRelatedModel::removeProfessionRelated($pr_id))
        {
            admin_log('delete', 'vocational_interest', $pr_id);
            message('删除成功', '/admin/vocational_interest/index');
        }
        else
        {
            message('删除失败', '/admin/vocational_interest/index');
        }
    }
}