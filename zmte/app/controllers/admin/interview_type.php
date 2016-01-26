<?php if ( ! defined('BASEPATH')) exit();
class Interview_type extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index()
    {
        if ( ! $this->check_power('interview_type_manage')) return;

        $data['list'] = InterviewTypeModel::get_type_list();

        // 模版
        $this->load->view('interview_type/index', $data);
    }
    
    // 添加页面
    public function add()
    {        
        if ( ! $this->check_power('interview_type_manage')) return;        
        
        $data['list'] = InterviewTypeModel::get_type_list();
        // 模版
        $this->load->view('interview_type/add', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
        if ( ! $this->check_power('interview_type_manage')) return;

        $id = intval($id);
        $id && $row = InterviewTypeModel::get_type($id);
        if (empty($row))
        {
            message('分类不存在');
            return;
        }
        if (empty($row['pid']))
        {
            $query = $this->db->get_where('interview_type', array('pid'=>$id));
            $row['children'] = $query->num_rows();        
        }

        $data['row'] = $row;

        $data['list'] = InterviewTypeModel::get_type_list();

        // 模版
        $this->load->view('interview_type/edit', $data);
    }
    
    // 添加操作
    public function create()
    {
        if ( ! $this->check_power('interview_type_manage')) return;
       
        
        $data['type_name'] = trim($this->input->post('type_name'));

        if (empty($data['type_name']))
        {
            message('请填写分类名称！');
            return;
        }
        if ($pid = intval($this->input->post('pid')))
        {
            $parent = InterviewTypeModel::get_type($pid);
            if (empty($parent))
            {
                message('上级分类不存在！');
                return;
            }
            if ($parent['pid'])
            {
                message('您只能设置二级分类。');
                return;
            }
            $data['pid'] = $pid;
        }


        $this->db->insert('interview_type', $data); 
        admin_log('add', 'interview_type', $this->db->insert_id());
        InterviewTypeModel::update_cache();        
        message('分类添加成功', 'admin/interview_type/index');
    }

    // 更新操作
    public function update()
    {
        if ( ! $this->check_power('interview_type_manage')) return;

        $type_id = intval($this->input->post('type_id'));
        $pid = intval($this->input->post('pid'));
        $data['type_name'] = trim($this->input->post('type_name'));

        if (empty($data['type_name']))
        {
            message('请填写分类名称！');
            return;
        }

        $row = InterviewTypeModel::get_type($type_id);
        if (empty($row))
        {
            message('分类不存在');
            return;
        }

        if ($pid)
        {
            $parent = InterviewTypeModel::get_type($pid);
            if (empty($parent))
            {
                message('上级分类不存在');
                return;
            }
            if ($parent['pid'])
            {
                message('您最多只能设置二级分类');
                return;
            }
            $query = $this->db->get_where('interview_type', array('pid'=>$type_id));
            if ($query->num_rows())
            {
                message('该分类下还存在字分类，您最多只能设置二级分类。');
            }
        }
        $data['pid'] = $pid;

        $this->db->update('interview_type', $data, array('type_id' => $type_id));
        admin_log('edit', 'interview_type', $type_id);
        InterviewTypeModel::update_cache();
        message('分类修改成功', 'admin/interview_type/index');      
    }
    
    // 删除
    public function delete($id)
    {
        if ( ! $this->check_power('interview_type_manage')) return;
        
        $id = intval($id);
        $this->db->delete('interview_type', array('type_id'=>$id));
        admin_log('delete', 'interview_type', $id);
        InterviewTypeModel::update_cache();
        message('分类删除成功', 'admin/interview_type/index/');        
    }
    
    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('interview_type_manage')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            $this->db->where_in('type_id', $ids)->delete('interview_type');
            admin_log('delete', 'interview_type', implode(',', $ids));
            InterviewTypeModel::update_cache();
            message('分类删除成功！', 'admin/interview_type/index');
        }
        else
        {
            message('请选择要删除的分类');
        }
    }

}
