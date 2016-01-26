<?php if ( ! defined('BASEPATH')) exit();
class Interview_rule_question extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()        
    {
        exit();
    }
    
    // 按规则查找面试题/生成试题
    public function rule($id = 0)
    {
        if ( ! $this->check_power('interview_rule_manage')) return;
        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule))
        {
            message('组题规则不存在', 'admin/interview_rule/index');
            return;
        }
        if (empty($rule['status']))
        {
            message('试题未生成', 'admin/interview_rule/index');
            return;
        }
        
        $where = $param = array();

        $where[] = "rq.rule_id='$id'";
        
        $where = $where ? ' WHERE '. implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) nums FROM {pre}interview_rule_question rq $where";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $periods    = C('grade_period');
        $langs      = C('interview_lang');
        $types      = C('interview_type');

        $list   = array();
        if ($total)
        {
            $sql = "SELECT rq.id rqid,q.*,count(rk.id) use_count, max(r.rule_time) max_time 
                    FROM {pre}interview_rule_question rq 
                     LEFT JOIN {pre}interview_question q ON rq.ques_id=q.id
                     LEFT JOIN {pre}interview_rule_question rk ON q.id=rk.ques_id
                     LEFT JOIN {pre}interview_rule r ON rk.rule_id=r.rule_id
                    $where
                    group by rq.ques_id
                    ORDER BY rq.id ASC LIMIT $offset, $size";   
            /*
            $sql = "SELECT q.* FROM {pre}interview_rule_question rq 
                     LEFT JOIN {pre}interview_question q ON rq.ques_id=q.id
                    $where
                    ORDER BY rq.id ASC LIMIT $offset, $size";  */      
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                // 类型
                $row_cids = explode(',', trim($row['class_id'], ','));
                $row_cname = array();
                foreach ($row_cids as $cid)
                {
                    $row_cname[] = isset($class_list[$cid]['class_name']) ? $class_list[$cid]['class_name'] : '';
                }
                // 年段
                $row_pids = explode(',', trim($row['grade_period'], ','));
                $row_pname = array();
                foreach ($row_pids as $pid)
                {
                    $row_pname[] = isset($periods[$pid]) ? $periods[$pid] : '';
                }

                $row['class_name'] = implode(',', $row_cname);
                $row['period_name'] = implode(',', $row_pname);
                $row['language'] = isset($langs[$row['lang']]) ? $langs[$row['lang']] : '';
                $row['type_name'] = isset($types[$row['interview_type']]['type_name']) ? $types[$row['interview_type']]['type_name'] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['max_time'] = $row['max_time'] ? date('Y-m-d H:i', $row['max_time']) : '---';
                $list[] = $row;
            }
        }
        $data['list'] = $list;
        $data['rule'] = $rule;

        // 分页
        $purl = site_url('admin/interview_rule_question/rule/'.$id) . ($param ? '?'.implode('&',$param) : '');
        $data['pagination'] = multipage($total, $size, $page, $purl);

        // 模版
        $this->load->view('interview_rule_question/rule', $data);        
    }
    
    // 打印试题
    public function print_question($id = 0, $mode = 0)
    {
        if ( ! $this->check_power('interview_rule_manage')) return;
        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule))
        {
            message('组题规则不存在', 'admin/interview_rule/index');
            return;
        }
        if (empty($rule['status']))
        {
            message('试题未生成', 'admin/interview_rule/index');
            return;
        }

        $types = C('interview_type');
        if (strpos($rule['extend_type'],',')!==false)
        {
            $arr_types = explode(',', $row['extend_type']);
            $arr_typename = array();
            foreach ($arr_types as $tid)
            {
                $arr_typename[] = isset($types[$tid]['type_name']) ? $types[$tid]['type_name'] : '';
            }
            $rule['type_name'] = implode(',', $arr_typename);
        }
        else
        {
            $rule['type_name'] = isset($types[$rule['type_id']]['type_name']) ? $types[$rule['type_id']]['type_name'] : '';
        }
        
        // 试题列表
        $index = 0;
        $list  = array();
        $sql = "SELECT q.id,q.content,q.student_content
                FROM {pre}interview_rule_question rq 
                 LEFT JOIN {pre}interview_question q ON rq.ques_id=q.id
                WHERE rq.rule_id='$id'
                ORDER BY rq.id ASC"; 
        $res = $this->db->query($sql);
        foreach ($res->result_array() as $row)
        {
            $row['index'] = ++$index;
            $list[] = $row;
        }

        $data['list'] = $list;
        $data['rule'] = $rule;

        $tpl = $mode ? 'print_student' : 'print';
        // 模版
        $this->load->view('interview_rule_question/'.$tpl, $data);
    }

    // 删除
    public function delete($id=0)
    {
        if ( ! $this->check_power('interview_rule_manage')) return;
        
        if ($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule_question', array('id'=>$id));
            $row = $query->row_array();
        }
        if (empty($row))
        {
            message('记录不存在');
            return;
        }
        
        $back_url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] 
                                             : 'admin/interview_rule_question/rule/'.$row['rule_id'];
        try
        {
            $this->db->delete('interview_rule_question', array('id'=>$id));
            message('删除成功', $back_url);
        }
        catch(Exception $e)
        {
            message('删除失败：'.$e->getMessage());
        }
    }

    public function batch_delete()
    {
        if ( ! $this->check_power('interview_rule_manage')) return;

        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }
        $rule_id = intval($this->input->post('rule_id'));
        try
        {
            $this->db->where_in('id', $ids)->delete('interview_rule_question');
            message('删除成功', 'admin/interview_rule_question/rule/'.$rule_id);
        }
        catch(Exception $e)
        {
            message('删除失败：'.$e->getMessage());
        }

        
    }
}
