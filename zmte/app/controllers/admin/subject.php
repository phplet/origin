<?php if ( ! defined('BASEPATH')) exit();

class Subject extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index()
    {
        if ( ! $this->check_power('subject_list,subject_manage')) return;

        $data['list'] = SubjectModel::get_subjects();
        $data['priv_manage'] = $this->check_power('subject_manage', FALSE);
        $data['priv_delete'] = $this->check_power('subject_delete', FALSE);

        // 模版
        $this->load->view('subject/index', $data);
    }
    
    // 添加页面
    public function add()
    {        
        if ( ! $this->check_power('subject_manage')) return;     
        
        $data['subjectlist'] = SubjectModel::get_subjects();
        
        // 模版
        $this->load->view('subject/add', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
        if ( ! $this->check_power('subject_manage')) return;

        $id = intval($id);
        $id && $subject = SubjectModel::get_subject($id);
        if (empty($subject))
        {
            message('学校不存在');
            return;
        }
        
        $data['grade_periods'] = C('grade_period');
        $data['subject']       = $subject;
        $data['subjectlist']   = SubjectModel::get_subjects();

        // 模版
        $this->load->view('subject/edit', $data);
    }
    
    // 添加操作
    public function create()
    {
        if ( ! $this->check_power('subject_manage')) return;
       
        $data['subject_name'] = trim($this->input->post('subject_name'));
        $data['grade_period'] = intval($this->input->post('grade_period'));
        $data['relate_subject_id'] = implode(',', $this->input->post('relate_subject_id'));

        if (empty($data['subject_name']))
        {
            message('请填写学科名称！');
            return;
        }
        
        if ($this->db->get_where('subject', 
                array('subject_name'=>$data['subject_name']))->row_array())
        {
            message('学科名称已经存在！');
            return;
        }

        $this->db->insert('subject', $data);
        admin_log('add', 'subject', $this->db->insert_id());
        SubjectModel::update_cache();
        message('学科添加成功', 'admin/subject/index');
    }

    // 更新操作
    public function update()
    {
        if ( ! $this->check_power('subject_manage')) return;

        $subject_id = intval($this->input->post('subject_id'));
        $data['subject_name'] = trim($this->input->post('subject_name'));
        $data['grade_period'] = intval($this->input->post('grade_period'));
        $data['relate_subject_id'] = implode(',', $this->input->post('relate_subject_id'));

        if (empty($data['subject_name']))
        {
            message('请填写学科名称！');
            return;
        }
        
        if ($this->db->get_where('subject',
                array('subject_name'=>$data['subject_name'], 'subject_id <>'=>$subject_id))->row_array())
        {
            message('学科名称已经存在！');
            return;
        }

        $this->db->update('subject', $data, array('subject_id' => $subject_id));
        admin_log('edit', 'subject', $subject_id);
        SubjectModel::update_cache();
        message('学科修改成功', 'admin/subject/index');      
    }
    
    // 删除
    public function delete($id)
    {
        if ( ! $this->check_power('subject_delete')) return;
        
        $id = intval($id);
        
        $check_table = array(
            'question',
            'evaluate_group_type',
            'evaluate_knowledge',
            'evaluate_method_tactic',
            'evaluate_rule',
            'exam',
            'exam_place_subject',
            'exam_rule',
            'exam_rule_qtype_limit',
            'exam_subject_paper',
            'exam_test_paper',
            'group_type',
            'knowledge',
            'role',
            'subject_category_subject',
            'summary_paper_question',
        );
        
        foreach ($check_table as $table)
        {
            if ($this->db->select("subject_id")
                ->get_where($table, array('subject_id'=>$id))->row_array())
            {
                message('学科存在其他关联记录，不可删除！', 'admin/subject/index/');
            } 
        }
        
        if ($this->db->select("subject_id")
            ->like('subject_id_str', ",$id,")
            ->get('question')->row_array())
        {
            message('学科存在其他关联记录，不可删除！', 'admin/subject/index/');
        }
        
        $this->db->delete('subject', array('subject_id'=>$id));
        admin_log('delete', 'subject', $id);
        SubjectModel::update_cache();
        message('学科删除成功', 'admin/subject/index/');        
    }
    
    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('subject_delete')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            $check_table = array(
                'question',
                'evaluate_group_type',
                'evaluate_knowledge',
                'evaluate_method_tactic',
                'evaluate_rule',
                'exam',
                'exam_place_subject',
                'exam_rule',
                'exam_rule_qtype_limit',
                'exam_subject_paper',
                'exam_test_paper',
                'group_type',
                'knowledge',
                'role',
                'subject_category_subject',
                'summary_paper_question',
            );
            
            foreach ($check_table as $table)
            {
                if ($this->db->select("subject_id")
                    ->where_in('subject_id', $ids)->get($table)->row_array())
                {
                    message('学科存在其他关联记录，不可删除！', 'admin/subject/index/');
                }
            }
            
            foreach ($ids as $id)
            {
                if ($this->db->select("subject_id")
                    ->like('subject_id_str', ",$id,")
                    ->get('question')->row_array())
                {
                    message('学科存在其他关联记录，不可删除！', 'admin/subject/index/');
                }
            }
            
            $this->db->where_in('subject_id', $ids)->delete('subject');
            SubjectModel::update_cache();
            //echo $this->db->last_query();
            admin_log('delete', 'subject', implode(',', $ids));
            message('学科删除成功！', 'admin/subject/index');
        }
        else
        {
            message('请选择要删除的学科');
        }
    }
  
    public function subject_dimension_list()
    {
        $data['list'] = SubjectModel::subjectDimensionList();
        if ($data['list'])
        {
            $profession_ids = array();
            foreach ($data['list'] as &$item)
            {
                $item['subd_professionid'] = json_decode($item['subd_professionid'], true);
                $profession_ids = array_merge($profession_ids, $item['subd_professionid']);
            }
            
            $profession_id_str = implode(',', array_unique($profession_ids));
            
            $data['profession'] = ProfessionModel::professionInfo($profession_id_str);
        }
        
        $data['subject'] = C('subject');
        $data['priv_manage'] = $this->check_power('subject_edit_subject_dimension', FALSE);
        $data['priv_delete'] = $this->check_power('subject_remove_subject_dimension', FALSE);
        
        $this->load->view('subject/subject_dimension_list', $data);     
    }

    public function edit_subject_dimension($subd_subjectid = 0)
    {
        $data = array();
        if ($subd_subjectid)
        {
            $data['info'] = SubjectModel::subjectDimensionInfo($subd_subjectid);
        }
        
        $data['subject_list'] = C('subject');
        $data['profession'] = ProfessionModel::professionList(null, null, 1000);
        
        $this->load->view('subject/edit_subject_dimension' , $data);
    }

    public function save_subject_dimension()
    {
        $param['subd_subjectid'] = intval($this->input->post('subd_subjectid'));
        $param['subd_value'] = $this->input->post('subd_value');
        $param['subd_professionid'] = $this->input->post('subd_professionid');
        
        try
        {
            if (SubjectModel::subjectDimensionInfo($param['subd_subjectid']))
            {
                $result = SubjectModel::setSubjectDimension($param);
                admin_log('edit', 'subject_dimension', $param['subd_subjectid']);
            }
            else
            {
                $result = SubjectModel::addSubjectDimension($param);
                admin_log('add', 'subject_dimension', $param['subd_subjectid']);
            }
        
            message('操作' . ($result ? '成功' : '失败'),
                '/admin/subject/subject_dimension_list');
        }
        catch (Exception $e)
        {
            message( $e->getMessage());
        }
    }

    public function remove_subject_dimension($subd_subjectid)
    {
        if (!Validate::isJoinedIntStr($subd_subjectid))
        {
            message('请选择需要删除的学科四维');
        }
        
        if (SubjectModel::removeSubjectDimension($subd_subjectid))
        {
            admin_log('delete', 'subject_dimension', $subd_subjectid);
            message('删除成功', '/admin/subject/subject_dimension_list');
        }
        else
        {
            message('删除失败', '/admin/subject/subject_dimension_list');
        }
    }       
}
