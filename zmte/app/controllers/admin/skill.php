<?php if ( ! defined('BASEPATH')) exit();
class Skill extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index($subject_id = 0)
    {
        if ( ! $this->check_power('skill_list, skill_manage')) return;
        
        $subject_id = intval($subject_id);
        
        $subjects   = CpUserModel::get_allowed_subjects();
        if (count($subjects) == '1') {
        	$tmp_subjects = array_keys($subjects);
        	$subject_id = $tmp_subjects[0];
        }
        
        $list = SkillModel::get_skills($subject_id);
        foreach ($list as &$row)
        {
            $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
        }
        $data['subject_id'] = $subject_id;
        $data['list']       = $list;
        $data['subjects']   = $subjects;
        $data['priv_manage'] = $this->check_power('skill_manage', FALSE);
        $data['priv_delete'] = $this->check_power('skill_delete', FALSE);

        // 模版
        $this->load->view('skill/index', $data);
    }
    
    // 添加页面
    public function add()
    {        
        if ( ! $this->check_power('skill_manage')) return;        
        
        $data['subjects']   = CpUserModel::get_allowed_subjects();
        
        // 模版
        $this->load->view('skill/add');
    }

    // 编辑页面
    public function edit($id = 0)
    {
        if ( ! $this->check_power('skill_manage')) return;

        $id = intval($id);
        $id && $row = SkillModel::get_skill($id);
        if (empty($row))
        {
            message('技能不存在');
            return;
        }
        
        if ( ! QuestionModel::check_subject_power($row['subject_id'], 'w')) return;

        $data['row']       = $row;
        $data['subjects']  = CpUserModel::get_allowed_subjects();

        // 模版
        $this->load->view('skill/edit', $data);
    }
    
    // 添加操作
    public function create()
    {
        if ( ! $this->check_power('skill_manage')) return;
       
        $data['skill_name'] = trim($this->input->post('skill_name'));
        $data['subject_id'] = intval($this->input->post('subject_id'));

        if (empty($data['skill_name']))
        {
            message('请填写技能名称！');
            return;
        }
        if (empty($data['subject_id']))
        {
            message('请选择关联学科');
            return;
        }
        
        if ( ! QuestionModel::check_subject_power($data['subject_id'], 'w')) return;
        
        $this->db->insert('skill', $data);  
        admin_log('add', 'skill', $this->db->insert_id());
        message('技能添加成功', 'admin/skill/index');
    }

    // 更新操作
    public function update()
    {
        if ( ! $this->check_power('skill_manage')) return;

        $id = intval($this->input->post('id'));
        $data['skill_name'] = trim($this->input->post('skill_name'));
        $data['subject_id'] = intval($this->input->post('subject_id'));

        if (empty($data['skill_name']))
        {
            message('请填写技能名称！');
            return;
        }
        if (empty($data['subject_id']))
        {
            message('请选择关联学科');
            return;
        }
        
        if ( ! QuestionModel::check_subject_power($data['subject_id'], 'w')) return;
        
        $this->db->update('skill', $data, array('id' => $id));
        admin_log('edit', 'skill', $id);
        message('技能修改成功', 'admin/skill/index');      
    }
    
    // 删除
    public function delete($id)
    {
        if ( ! $this->check_power('skill_delete')) return;
        
        $id = intval($id);
        $id && $row = SkillModel::get_skill($id);
        if (empty($row))
        {
        	message('技能不存在');
        	return;
        }
        
        if ( ! QuestionModel::check_subject_power($row['subject_id'], 'w')) return;
        
        $this->db->delete('skill', array('id'=>$id));
        admin_log('delete', 'skill', $id);
        message('技能删除成功', 'admin/skill/index/');        
    }
    
    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('skill_delete')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            $this->db->where_in('id', $ids)->delete('skill');
            //echo $this->db->last_query();
            admin_log('delete', 'skill', implode(',', $ids));
            message('技能删除成功！', 'admin/skill/index');
        }
        else
        {
            message('请选择要删除的技能');
        }
    }

}
