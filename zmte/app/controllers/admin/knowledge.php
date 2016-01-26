<?php if ( ! defined('BASEPATH')) exit();
class Knowledge extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 知识点列表
     * @param   int     $pid = 0            父知识点ID
     * @param   int     $_GET['subject']    学科ID
     */
    public function index($pid = 0)
    {
        if ( ! $this->check_power('knowledge_list, knowledge_manage')) return;
        $pid = intval($pid);
        if ($pid)
        {
            $parent = KnowledgeModel::get_knowledge($pid);
            if (empty($parent))
            {
                message('知识点不存在');
                return;
            }            
        }
        else
        {
            $parent = array(
                'id' => 0,
                'pid' => 0,
                'knowledge_name' => '一级知识点',
            );
        }
        $grades     = C('grades');
        $subject_id = intval($this->input->get('subject'));
        $subjects   = CpUserModel::get_allowed_subjects();
        if (count($subjects) == '1') {
        	$tmp_subjects = array_keys($subjects);
        	$subject_id = $tmp_subjects[0];
        }
        
        $list = KnowledgeModel::get_knowledge_list($subject_id, $pid);

        // NOTICE: 这里可以使用建视图LEFT JOIN来处理
        foreach ($list as &$val)
        {
            $val['subject_name'] = isset($subjects[$val['subject_id']]) ? $subjects[$val['subject_id']] : '';
        }
        $data['list']       = $list;
        $data['parent']     = $parent;
        $data['subject_id'] = $subject_id;
        $data['subjects']   = $subjects;
        $data['priv_manage']  = $this->check_power('knowledge_manage', FALSE);
        $data['priv_delete']  = $this->check_power('knowledge_delete', FALSE);


        // 模版
        $this->load->view('knowledge/index', $data);
    }
    
    /**
     * 添加知识点页面
     * @param   int     $pid = 0
     * @see     update()
     */
    public function add($pid = 0)
    {        
        if ( ! $this->check_power('knowledge_manage')) return;
        $pid = intval($pid);
        $subjects = CpUserModel::get_allowed_subjects();

        if ($pid)
        {
            $parent = KnowledgeModel::get_knowledge($pid);
            if (empty($parent))
            {
                message('知识点不存在');
                return;
            }            
        }
        else
        {
            $parent = array(
                'id' => 0,
                'knowledge_name' => '一级知识点',
                'subject_id' => 0,
            );
        }

        $data['subjects'] = $subjects;
        $data['pid'] = $pid;
        $data['parent'] = $parent;

        // 模版
        $this->load->view('knowledge/add', $data);
    }

    /**
     * 编辑知识点
     * @param   int     $id = 0
     * @see     update()
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('knowledge_manage')) return;

        $id = intval($id);
        $id && $row = KnowledgeModel::get_knowledge($id);
        if (empty($row))
        {
            message('知识点不存在');
            return;
        }
        if ($row['pid'])
        {
            $parent = KnowledgeModel::get_knowledge($row['pid']);
            $data['parent'] = $parent;
        }
        
        //if ( ! QuestionModel::check_subject_power($row['subject_id'], 'w')) return;

        $data['row']      = $row;
        $data['subjects'] = CpUserModel::get_allowed_subjects();

        // 模版
        $this->load->view('knowledge/edit', $data);
    }
    
    // 添加/编辑操作
    public function update()
    {
        if ( ! $this->check_power('knowledge_manage')) return;
       
        $id                     = intval($this->input->post('id'));
        $data['knowledge_name'] = trim($this->input->post('knowledge_name'));
        $data['subject_id']     = intval($this->input->post('subject_id'));
        $data['pid']            = intval($this->input->post('pid'));

        if (empty($data['knowledge_name']))
        {
            message('请填写知识点名称！');
            return;
        }
        if (empty($data['subject_id']))
        {
            message('请选择所属学科');
            return;
        }
        
        //if ( ! QuestionModel::check_subject_power($data['subject_id'], 'w')) return;

        if ($data['pid'])
        {
            $parent = KnowledgeModel::get_knowledge($data['pid']);
            if (empty($parent))
            {
                message('上级知识点不存在');
                return;
            }
            if ($parent['pid'])
            {
                message('只能设置二级知识点');
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
            // 编辑
            //
            $psk = KnowledgeModel::get_knowledge_psk($data['pid'], $data['subject_id'], $data['knowledge_name'],$id);
            
            if(!$psk)

                $this->db->update('knowledge', $data, array('id'=>$id));
            else
                message('同学科同级知识点不能重名');


            // 如果修改的是一级知识点，并且修改了学科。则同步相应二级知识点学科
            $old_subject_id = intval($this->input->post('old_subject_id'));
            if ($data['pid'] == 0 && $old_subject_id != $data['subject_id'])
            {
                $child['subject_id'] = $data['subject_id'];
                $this->db->update('knowledge', $child, array('pid'=>$id));
            }
            admin_log('edit', 'knowledge', $id);
            $tishi='知识点修改成功';
        }
        else
        {
            // 添加
            //
            
            $psk = KnowledgeModel::get_knowledge_psk($data['pid'], $data['subject_id'], $data['knowledge_name'],0);
            
            if(!$psk)
                $this->db->insert('knowledge', $data);
            else
                message('同学科同级知识点不能重名');

            admin_log('add', 'knowledge', $this->db->insert_id());
            $tishi='知识点添加成功';
        }
        
        message($tishi, 'admin/knowledge/index/'.$data['pid']);
    }

    
    // 删除
    public function delete($id=0)
    {
        if ( ! $this->check_power('knowledge_delete')) return;

        $result = $this->_delete($id);
        if ($result === TRUE)
            $msg = '知识点删除成功';
        else
        {
            switch($result)
            {
                case -1: $msg = '知识点不存在';break;
                case -2: $msg = '该知识点还存在下级分类';break;
                case -3: $msg = '该知识点还存在关联试题';break;
                case -4: $msg = '您没有权限';break;
                default: $msg = '知识点删除失败';
            }
        }
        $back_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'admin/knowledge/index/'; 
        message($msg, $back_url);        
    }
    
    // 批量删除
    public function delete_batch()
    {
        if ( ! $this->check_power('knowledge_delete')) return;

        $back_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : 'admin/knowledge/index/'; 

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {
            $success = $fail = 0;
            foreach ($ids as $id)
            {
                $result = $this->_delete($id);
                if ($result === TRUE)
                    $success++;
                else
                    $fail++;
            }
            message('批量删除完成。成功删除：'.$success.'个，失败：'.$fail, $back_url);
        }
        else
        {
            message('请选择要删除的技能', $back_url);
        }
    }

    // 删除
    private function _delete($id)
    {
        $id = intval($id);

        $item = KnowledgeModel::get_knowledge($id);
        if (empty($item))
        {
            // 知识点不存在
            return -1;
        }
        
        $query = $this->db->select('count(*) num')->get_where('knowledge', array('pid'=>$id));
        $row = $query->row_array();
        if ($row['num'])
        {
            //该知识点还存在下级分类;
            return -2;
        }

        $query = $this->db->select('count(*) num')->get_where('relate_knowledge',array('knowledge_id'=>$id, 'is_child'=>0));
        $row = $query->row_array();
        if ($row['num'])
        {
            //该知识点还存在关联试题;
            return -3;
        }
        
        /*
        if ( ! QuestionModel::check_subject_power($item['subject_id'], 'w', false)) 
        {
			//没有权限        	
            return -4;
        }
        */
        
        try
        {
            $this->db->delete('knowledge', array('id'=>$id));
            admin_log('delete', 'knowledge', $id);
        }
        catch(Exception $e)
        {
            return FALSE;
        }
        return TRUE;
    }


    /**
     * 知识点同步
     * @param   int     $pid = 0            父知识点ID
     * @param   int     $_GET['subject_id']    学科ID
     * @param   string  $_GET['subject_name']   学科名称
     */
    public function synclist()
    {
        header('Content-Type:text/javascript;charset=UTF-8');
        $data = array();
        while (true)
        {
            $subject_id = intval($this->input->get('subject_id'));
            $subject_name = trim($this->input->get('subject_name')); 
            $subject_list = C('subject');
            if (!$subject_id && $subject_name != '')
            {
                $subject_id = array_search($subject_name, $subject_list);
            }
            if (!isset($subject_list[$subject_id]))
            {
                $data['error'] = '错误的学科参数';
                break;
            }
            $subject_name = $subject_list[$subject_id];

            $subjects   = CpUserModel::get_allowed_subjects();
            if (!isset($subjects[$subject_id]))
            {
                $data['error'] = '您没有指定学科权限';
                break;
            }
            $data['subject_id'] = $subject_id;
            $data['subject_name'] = $subject_name;

            $sql = <<<EOT
SELECT id AS k_id, knowledge_name AS k_name, pid AS k_pid 
FROM rd_knowledge 
WHERE subject_id = {$subject_id}
ORDER BY pid
EOT;
            $data['ktree_list'] = Fn::db()->fetchAll($sql);
            break;
        }
        echo('var zmte_ktree = ');
        echo(json_encode($data));
        exit();
    }
}
