<?php
if ( ! defined('BASEPATH')) exit();
/**
 * 信息提取方式管理
 */
class Group_type extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 信息提取方式列表
     * @param   int     $pid = 0            父信息提取方式ID
     * @param   int     $_GET['subject']    学科ID
     */
    public function index($pid = 0)
    {
        if ( ! $this->check_power('group_type_list, group_type_manage')) return;
        $pid = intval($pid);
        if ($pid)
        {
            $parent = GroupTypeModel::get_group_type($pid);
            if (empty($parent))
            {
                message('信息提取方式不存在');
                return;
            }
        }
        else
        {
            $parent = array(
                    'id' => 0,
                    'pid' => 0,
                    'group_type_name' => '一级信息提取方式',
            );
        }
        
        $grades     = C('grades');
        $subject_id = intval($this->input->get('subject'));
        $subjects   = CpUserModel::get_allowed_subjects();
        if (count($subjects) == '1') {
            $tmp_subjects = array_keys($subjects);
            $subject_id = $tmp_subjects[0];
        }

        $list = GroupTypeModel::get_group_type_list($pid, $subject_id);

        // NOTICE: 这里可以使用建视图LEFT JOIN来处理
        foreach ($list as &$val)
        {
            $val['subject_name'] = isset($subjects[$val['subject_id']]) ? $subjects[$val['subject_id']] : '';
        }
        
        $data['list']       = $list;
        $data['parent']     = $parent;
        $data['subject_id'] = $subject_id;
        $data['subjects']   = $subjects;
        $data['priv_manage']  = $this->check_power('group_type_manage', FALSE);
        $data['priv_delete']  = $this->check_power('group_type_delete', FALSE);


        // 模版
        $this->load->view('group_type/index', $data);
    }

    /**
     * 添加信息提取方式页面
     * @param   int     $pid = 0
     * @see     update()
     */
    public function add($pid = 0)
    {
        if ( ! $this->check_power('group_type_manage')) return;
        $pid = intval($pid);
        $subjects = CpUserModel::get_allowed_subjects();

        if ($pid)
        {
            $parent = GroupTypeModel::get_group_type($pid);
            if (empty($parent))
            {
                message('信息提取方式不存在');
                return;
            }
        }
        else
        {
            $parent = array(
                    'id' => 0,
                    'group_type_name' => '一级信息提取方式',
                    'subject_id' => 0,
            );
        }

        $data['subjects'] = $subjects;
        $data['pid'] = $pid;
        $data['parent'] = $parent;

        // 模版
        $this->load->view('group_type/add', $data);
    }

    /**
     * 编辑信息提取方式
     * @param   int     $id = 0
     * @see     update()
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('group_type_manage')) return;

        $id = intval($id);
        $id && $row = GroupTypeModel::get_group_type($id);
        if (empty($row))
        {
            message('信息提取方式不存在');
            return;
        }
        if ($row['pid'])
        {
            $parent = GroupTypeModel::get_group_type($row['pid']);
            $data['parent'] = $parent;
        }

        if ( ! QuestionModel::check_subject_power($row['subject_id'], 'w')) return;

        $data['row']      = $row;
        $data['subjects'] = CpUserModel::get_allowed_subjects();

        // 模版
        $this->load->view('group_type/edit', $data);
    }

    // 添加/编辑操作
    public function update()
    {
        if ( ! $this->check_power('group_type_manage')) return;
         
        $id                     = intval($this->input->post('id'));
        $data['group_type_name'] = trim($this->input->post('group_type_name'));
        $data['subject_id']     = intval($this->input->post('subject_id'));
        $data['pid']            = intval($this->input->post('pid'));
        
        

        if (empty($data['group_type_name']))
        {
            message('请填写信息提取方式名称！');
            return;
        }
        
        if (empty($data['subject_id']))
        {
            message('请选择所属学科');
            return;
        }

        if ( ! QuestionModel::check_subject_power($data['subject_id'], 'w')) return;

        if ($data['pid'])
        {
            $parent = GroupTypeModel::get_group_type($data['pid']);
            if (empty($parent))
            {
                message('上级信息提取方式不存在');
                return;
            }
            if ($parent['pid'])
            {
                message('只能设置二级信息提取方式');
                return;
            }
            // 如果学科不同，自动变为上级分类学科
            if ($parent['subject_id'] != $data['subject_id'])
            {
                $data['subject_id'] = $parent['subject_id'];
            }
        }

        if ($id)
        {
            
            if ($this->db->get_where('group_type',
                    array('group_type_name'=>$data['group_type_name'], 'id <>'=>$id, 'pid'=>$data['pid']))->row_array())
            {
                message('信息提取方式名称已经存在！');
                return;
            }
            
            // 编辑
            $this->db->update('group_type', $data, array('id'=>$id));
            
            // 如果修改的是一级信息提取方式，并且修改了学科。则同步相应二级知识点学科
            $old_subject_id = intval($this->input->post('old_subject_id'));
            if ($data['pid'] == 0 && $old_subject_id != $data['subject_id'])
            {
                $child['subject_id'] = $data['subject_id'];
                $this->db->update('group_type', $child, array('pid'=>$id));
            }
            admin_log('edit', 'group_type', $id);
        }
        else
        {
            if ($this->db->get_where('group_type',
                    array('group_type_name'=>$data['group_type_name'], 'pid'=>$data['pid']))->row_array())
            {
                message('信息提取方式名称已经存在！');
                return;
            }
            
            // 添加
            $this->db->insert('group_type', $data);
            admin_log('add', 'group_type', $this->db->insert_id());
        }
        message('信息提取方式编辑成功', 'admin/group_type/index/'.$data['pid']);
    }


    // 删除
    public function delete($id=0)
    {
        if ( ! $this->check_power('group_type_delete')) return;

        $result = $this->_delete($id);
        if ($result === TRUE)
            $msg = '信息提取方式删除成功';
        else
        {
            switch($result)
            {
            	case -1: $msg = '信息提取方式不存在';break;
            	case -2: $msg = '信息提取方式还存在下级分类';break;
            	case -3: $msg = '信息提取方式还存在关联试题';break;
            	case -4: $msg = '您没有权限';break;
            	default: $msg = '信息提取方式删除失败';
            }
        }
        $back_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'admin/group_type/index/';
        message($msg, $back_url);
    }

    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('group_type_delete')) return;

        $back_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'admin/group_type/index/';

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {
            $success = $fail = 0;
            foreach ($ids as $id)
            {
                $result = $this->_delete($id);
                if ($result === TRUE)
                {
                    $success++;
                }
                else
                {
                    switch($result)
                    {
                        case -1: 
                            message('信息提取方式不存在');
                            break;
                        case -2: 
                            message('信息提取方式还存在下级分类');
                            break;
                        case -3: 
                            message('信息提取方式还存在关联试题');
                            break;
                        case -4: 
                            message('您没有权限');
                            break;
                        default:;
                    }
                    
                    $fail++;
                }
            }
            
            message('批量删除完成。成功删除：'.$success.'个，失败：'.$fail, $back_url);
        }
        else
        {
            message('请选择要删除的记录', $back_url);
        }
    }

    // 删除
    private function _delete($id)
    {
        $id = intval($id);

        $item = GroupTypeModel::get_group_type($id);
        if (empty($item))
        {
            // 知识点不存在
            return -1;
        }

        $query = $this->db->select('count(*) num')->get_where('group_type', array('pid'=>$id));
        $row = $query->row_array();
        if ($row['num'])
        {
            //该信息提取方式还存在下级分类;
            return -2;
        }

        $query = $this->db->select('count(*) num')->get_where('relate_group_type',array('group_type_id'=>$id, 'is_child'=>0));
        $row = $query->row_array();
        if ($row['num'])
        {
            //该信息提取方式还存在关联试题;
            return -3;
        }

        if ( ! QuestionModel::check_subject_power($item['subject_id'], 'w', false))
        {
            //没有权限
            return -4;
        }

        try
        {
            $this->db->delete('group_type', array('id'=>$id));
            admin_log('delete', 'group_type', $id);
        }
        catch(Exception $e)
        {
            return FALSE;
        }
        return TRUE;
    }
}
