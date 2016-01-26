<?php if ( ! defined('BASEPATH')) exit();
class Question_class extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index()
    {
        if ( ! $this->check_power('class_list, class_manage')) return;
        $grades = C('grades');
        $list = ClassModel::get_class_list();
        foreach ($list as &$row)
        {
            $row['start_grade_name'] = isset($grades[$row['start_grade']]) ? $grades[$row['start_grade']] : '';
            $row['end_grade_name'] = isset($grades[$row['end_grade']]) ? $grades[$row['end_grade']] : '';
        }

        $data['grades'] = $grades;
        $data['list']   = $list;
        $data['priv_manage'] = $this->check_power('class_manage', FALSE);
        $data['priv_delete'] = $this->check_power('class_delete', FALSE);

        // 模版
        $this->load->view('question_class/index', $data);
    }
    
    /**
     * 添加页面,提交时POST到admin/questin_class/create
     * @see create()
     */
    public function add()
    {        
        if ( ! $this->check_power('class_manage')) return;        
        
        // 模版
        $this->load->view('question_class/add');
    }

    /**
     * 编辑页面,提交时POST到admin/question_class/update
     * @see udpate()
     * 
     * @param   int     $id     题目类型ID
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('class_manage')) return;

        $id = intval($id);
        $id && $row = ClassModel::get_question_class_by_id($id);
        if (empty($row))
        {
            message('题目类型不存在');
            return;
        }

        $data['row']       = $row;

        // 模版
        $this->load->view('question_class/edit', $data);
    }
    
    /**
     * 添加操作
     * @see add()
     *
     * @param   string  $_POST['class_name']    题目类型名称
     * @param   int     $_POST['start_grade']   开始年级
     * @param   int     $_POST['end_grade']     结束年级
     */
    public function create()
    {
        if ( ! $this->check_power('class_manage')) return;
       
        $data['class_name']  = trim($this->input->post('class_name'));
        $data['start_grade'] = intval($this->input->post('start_grade'));
        $data['end_grade']   = intval($this->input->post('end_grade'));

        if (empty($data['class_name']) 
            || empty($data['start_grade']) 
            || empty($data['end_grade']))
        {
            message('请完整填写类型信息！');
            return;
        }
        
        if ($data['start_grade'] > $data['end_grade'])
        {
            message('开始年级不能大于结束年级！');
            return;
        }
        
        if ($this->db->get_where('question_class',
                array('class_name'=>$data['class_name']))->row_array())
        {
            message('题目类型名称已经存在！');
            return;
        }

        $this->db->insert('question_class', $data);  
        admin_log('add', 'question_class', $this->db->insert_id());
        message('类型添加成功', 'admin/question_class/index');
    }

    /**
     * 更新题目类型为$class_id的信息
     * @see edit()
     *
     * @param   int     $_POST['class_id']      题目类型ID
     * @param   string  $_POST['class_name']    题目类型名称
     * @param   int     $_POST['start_grade']   开始年级
     * @param   int     $_POST['end_grade']     结束年级
     */
    public function update()
    {
        if ( ! $this->check_power('class_manage')) return;

        $class_id = intval($this->input->post('class_id'));
        $data['class_name']  = trim($this->input->post('class_name'));
        $data['start_grade'] = intval($this->input->post('start_grade'));
        $data['end_grade']   = intval($this->input->post('end_grade'));
        if (empty($data['class_name']) 
            || empty($data['start_grade']) 
            || empty($data['end_grade']))
        {
            message('请完整填写类型信息！');
            return;
        }
        
        if ($data['start_grade'] > $data['end_grade'])
        {
            message('开始年级不能大于结束年级！');
            return;
        }
        
        if ($this->db->get_where('question_class',
                array('class_name'=>$data['class_name'], 'class_id <>'=>$class_id))->row_array())
        {
            message('题目类型名称已经存在！');
            return;
        }

        $this->db->update('question_class', $data, array('class_id' => $class_id));
        admin_log('edit', 'question_class', $class_id);
        message('类型修改成功', 'admin/question_class/index');      
    }
    
    /**
     * 删除指定题目类型ID的数据
     * @param   int $id
     */
    public function delete($id)
    {
        if ( ! $this->check_power('class_delete')) return;
        
        $id = intval($id);
        $sql = "SELECT ques_id FROM {pre}question 
                WHERE class_id LIKE '%,$id,%'";
        if ($this->db->query($sql)->result())
        {
            message('该题目类型存在关联试题，不能删除', 
                    'admin/question_class/index');
        }
        
        $this->db->delete('question_class', array('class_id'=>$id));
        admin_log('delete', 'question_class', $id);
        message('类型删除成功', 'admin/question_class/index');        
    }
    
    /**
     * 批量删除$_POST['ids']里指定的题目类型
     * @param   array   $_POST['ids']   list<int>类型
     */
    public function delete_batch()
    {
        if ( ! $this->check_power('class_delete')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            foreach ($ids as $id)
            {
                $sql = "SELECT ques_id FROM {pre}question 
                        WHERE class_id LIKE '%,$id,%'";
                if ($this->db->query($sql)->result())
                {
                    message('该题目类型存在关联试题，不能删除', 
                            'admin/question_class/index');
                }
            }
            
            $this->db->where_in('class_id', $ids)->delete('question_class');
            admin_log('delete', 'question_class', implode(',', $ids));
            message('类型批量删除成功！', 'admin/question_class/index');
        }
        else
        {
            message('请选择要删除的类型');
        }
    }
    
    /**
     * 根据指定年级返回对应的题目类型
     * @param   int $_POST['grade_id']  年级ID
     * @return  string  json_encode(list<map<string, variant>>) 类型的数据集
     */
    public function ajax_class_list()
    {
    	$grade_id = (int)$this->input->get_post('grade_id');
    	
    	$list = array();
    	if ($grade_id)
    		$list = ClassModel::get_class_list($grade_id);
    	
    	$result = array();
    	foreach ($list as $val)
    	{
    		$result[] = array('class_id' => $val['class_id'], 'class_name' => $val['class_name']);
    	}
    	
    	echo json_encode($result);  	
    }
}
