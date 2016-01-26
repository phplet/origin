<?php if ( ! defined('BASEPATH')) exit();
class Interview_rule extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 试题列表
    public function index()        
    {
        if ( ! $this->check_power('interview_rule_manage')) return; 

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $periods    = C('grade_period');
        $langs      = C('interview_lang');
        $types      = C('interview_type');
        
                
        $search = $param = array();

        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}interview_rule";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];
        
        // 读取数据
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();
        if ($total)
        {
            $sql = "SELECT * FROM {pre}interview_rule
                    ORDER BY rule_id DESC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                $row['class_name'] = isset($class_list[$row['class_id']]['class_name']) ? $class_list[$row['class_id']]['class_name'] : '';
                $row['period_name'] = isset($periods[$row['grade_period']]) ? $periods[$row['grade_period']] : '';
                $row['language'] = isset($langs[$row['lang']]) ? $langs[$row['lang']] : '';
                
                if (strpos($row['extend_type'],',')!==false)
                {
                    $arr_types = explode(',', $row['extend_type']);
                    $arr_typename = array();
                    foreach ($arr_types as $tid)
                    {
                        $arr_typename[] = isset($types[$tid]['type_name']) ? $types[$tid]['type_name'] : '';
                    }
                    $row['type_name'] = implode(',', $arr_typename);
                }
                else
                {
                    $row['type_name'] = isset($types[$row['type_id']]['type_name']) ? $types[$row['type_id']]['type_name'] : '';
                }

                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $list[] = $row;
            }
        }
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/interview_rule/index') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        //$data['search']      = $search;
        $data['periods']     = $periods;
        $data['langs']       = $langs;
        $data['types']       = $types;
        $data['class_list']  = $class_list;

        // 模版
        $this->load->view('interview_rule/index', $data);
    }

    // 添加规则
    public function add()
    {
        if ( ! $this->check_power('interview_rule_manage')) return; 

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $periods    = C('grade_period');
        $langs      = C('interview_lang');
        $types      = C('interview_type');
        
        $data['periods']     = $periods;
        $data['langs']       = $langs;
        $data['types']       = $types;
        $data['class_list']  = $class_list;

        // 模版
        $this->load->view('interview_rule/add', $data);
    }

    // 修改
    public function edit($id = 0)
    {
        if ( ! $this->check_power('interview_rule_manage')) return;

        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $row = $query->row_array();
        }
        if (empty($row))
        {
            message('规则不存在');
            return;
        }
        if ($row['status'])
        {
            message('改规则已生成题目，不能修改。');
            return;
        }

        $data['row']     = $row;
        $data['periods'] = C('grade_period');
        $data['langs']   = C('interview_lang');
        $data['types']   = C('interview_type');

        // 类型
        $class_list = ClassModel::get_class_list();
        $data['class_list'] = $class_list;

        // 模版
        $this->load->view('interview_rule/edit', $data);
    }

    // 添加、修改处理
    public function update()
    {
        if ( ! $this->check_power('interview_rule_manage')) return;

        $act = trim($this->input->post('act'));
        if ($act == 'edit')
        {
            if($id = intval($this->input->post('id')))
            {
                $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
                $old = $query->row_array();
            }
            if (empty($old))
            {
                message('规则不存在');
                return;
            }
            if ($old['status'])
            {
                message('改规则已生成题目，不能修改。');
                return;
            }
        }

        // 题目基本信息        
        $row['class_id']        = intval($this->input->post('class_id'));
        $row['grade_period']    = intval($this->input->post('period'));
        $row['type_id']         = intval($this->input->post('interview_type'));
        $row['lang']            = intval($this->input->post('lang'));
        $row['ques_num']        = intval($this->input->post('ques_num'));
        $row['rule_name']       = trim($this->input->post('rule_name'));

        if ($row['lang'] == 2)
            $row['type_id'] = $row['extend_type'] = 0;
        else
        {
            $extend_type = $this->input->post('extend_type');
            if ($extend_type && is_array($extend_type))
            {
                $extend_type = sort(my_intval($extend_type));
                $row['extend_type'] = implode(',', $extend_type);
            }
            else
            {
                $row['extend_type'] = $row['type_id'];
            }
        }
        //pr($row,1);

        // 试题信息验证
        $message = array();
        if (empty($row['rule_name']))
        {
            $message[] = '请填写规则名称';
        }
        if (empty($row['grade_period']))
        {
            $message[] = '请选择年段';
        }
        if (empty($row['class_id']))
        {
            $message[] = '请选择类型';
        }
        if (empty($row['lang']))
        {
            $message[] = '请选择语言';
        }
        if ($row['lang']==1 && empty($row['type_id']))
        {
            $message[] = '请选择考点';
        }
        if (empty($row['ques_num']))
        {
            $message[] = '请填写试题数量';
        }
        
        if ($message)
        {
            message(implode('<br>', $message), null, 10);
            return;
        }

        if ($act == 'edit')
        {
            $this->db->update('interview_rule', $row, array('rule_id'=>$id));
            admin_log('edit', 'interview_rule', $id);
        }
        else
        {
            $row['addtime'] = time();
            $this->db->insert('interview_rule', $row);
            admin_log('add', 'interview_rule', $this->db->insert_id());
        }
        
        message('面试题组题规则编辑成功', 'admin/interview_rule/index');
    }
    
    // 按规则查找面试题/生成试题
    public function search_question($id = 0, $generate = 0)
    {
        if ( ! $this->check_power('interview_rule_manage')) return;
        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule))
        {
            message('规则不存在');
            return;
        }
        if ($rule['status'])
        {
            // 已生成试题，跳到试题列表
            redirect('admin/interview_rule/questions/'.$id);
        }
        
        $where = $param = array();
        // 年段
        if ($rule['grade_period'])
        {
            $where[] = "q.grade_period LIKE '%,".$rule['grade_period'].",%'";
        }
        // 类型
        if ($rule['class_id'])
        {
            $where[] = "q.class_id LIKE '%,".$rule['class_id'].",%'";
        }
        if ($rule['lang'])
        {
            $where[] = "q.lang='$rule[lang]'";
        }
        // 考点
        if ($rule['type_id'] && $rule['extend_type'])
        {
            $type_children = InterviewTypeModel::get_children($rule['type_id']);
            if ($type_children) $type_children = array_keys($type_children);

            if ($rule['type_id'] == $rule['extend_type'])
            {
                $type_ids = array($rule['type_id']);
                if ($type_children)
                {
                    $type_ids = $type_ids + $type_children;
                }
            }
            else
            {
                $type_ids = $type_children;
                if ($rule['extend_type'] == implode(',',$type_children))
                {
                    $type_ids = $rule['type_id'] + $type_ids;
                }                
            }
            if (count($type_ids) == 1)
            {
                $where[] = "q.interview_type = '$rule[type_id]'";
            }
            else
            {
                $where[] = "q.interview_type IN (".implode(',', $type_ids).")";
            }
        }
        if ($rule['exam_id'])
        {
            // todo
            // 排除和机考题目关联的笔试题
        }
        
        $where = $where ? implode(' AND ', $where) : '1';
        $sql = "SELECT id FROM {pre}interview_question q WHERE $where LIMIT $rule[ques_num]";
        $query = $this->db->query($sql);
        $total = $query->num_rows();

        if ($generate)
        {
            if (empty($total))
            {
                message('暂无匹配试题！');
                return;
            }
            $sql = "SELECT q.id,count(rk.id) use_count, max(r.rule_time) max_time FROM {pre}interview_question q
                    LEFT JOIN {pre}interview_rule_question rk ON q.id=rk.ques_id
                    LEFT JOIN {pre}interview_rule r ON rk.rule_id=r.rule_id
                    WHERE $where 
                    GROUP BY q.id
                    ORDER BY use_count, q.id DESC, max_time LIMIT $total";
            $res = $this->db->query($sql);
            $records = array();
            foreach ($res->result_array() as $row)
            {
                $record = array(
                    'rule_id' => $id,
                    'ques_id' => $row['id']
                );
                $records[] = $record;
            }
            if ($records)
            {
                try {
                    $this->db->trans_start();
                    $this->db->insert_batch('interview_rule_question', $records);
                    $update = array(
                        'status' => 1,
                        'rule_time' => time()
                    );
                    $this->db->update('interview_rule', $update, array('rule_id'=>$id));
                    $this->db->trans_complete();
                    admin_log('generate', 'interview_rule_question', $id);
                    message('试题导出成功', 'admin/interview_rule_question/rule/'.$id);
                } 
                catch(Exception $e) 
                {
                    message('试题导出失败');
                    return;
                }
            }
            if (empty($total))
            {
                message('暂无匹配试题！');
                return;
            }
        }
        else
        {
            $size   = 15;
            $totalpage = ceil($total/$size);

            $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
            $page   = $page>$totalpage ? $totalpage : $page;
            $offset = ($page - 1) * $size;
            $limit = $page == $totalpage ? ($total - $offset) : $size;

            // 加载分类数据
            $class_list = ClassModel::get_class_list();
            $periods    = C('grade_period');
            $langs      = C('interview_lang');
            $types      = C('interview_type');

            $list   = array();
            if ($total)
            {
                $sql = "SELECT q.*,count(rk.id) use_count, max(r.rule_time) max_time FROM {pre}interview_question q
                        LEFT JOIN {pre}interview_rule_question rk ON q.id=rk.ques_id
                        LEFT JOIN {pre}interview_rule r ON rk.rule_id=r.rule_id
                        WHERE $where 
                        GROUP BY q.id
                        ORDER BY use_count, q.id DESC, max_time LIMIT $offset, $limit";
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
            $purl = site_url('admin/interview_rule/search_question/'.$id) . ($param ? '?'.implode('&',$param) : '');
            $data['pagination'] = multipage($total, $size, $page, $purl);

            // 模版
            $this->load->view('interview_rule/search_question', $data);
        }
    }

    // 删除
    public function delete($id=0)
    {
        if ( ! $this->check_power('interview_rule_delete')) return;
        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule))
        {
            message('规则不存在');
            return;
        }
        if ($rule['status'])
        {
            message('该规则下存在关联试题');
            return;
        }
        try
        {
            $this->db->delete(array('interview_rule','interview_rule_question'), array('rule_id'=>$id));
            message('规则删除成功', 'admin/interview_rule/index');
        }
        catch(Exception $e)
        {
            message('规则删除失败：'.$e->getMessage());
        }
    }
    
    // 重置规则，清空试题记录
    public function reset_rule($id = 0)
    {
        if ( ! $this->check_power('interview_rule_delete')) return;
        if($id = intval($id))
        {
            $query = $this->db->get_where('interview_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule))
        {
            message('规则不存在');
            return;
        }
        
        try
        {
            $this->db->trans_start();
            $this->db->delete('interview_rule_question', array('rule_id'=>$id));
            $update = array('status' => 0, 'rule_time' => 0);
            $this->db->update('interview_rule', $update, array('rule_id'=>$id));
            $this->db->trans_complete();
            admin_log('reset', 'interview_rule', $id);
            message('操作成功', 'admin/interview_rule/index');
        }
        catch(Exception $e)
        {
            message('操作失败:'.$e->getMessage());
        }
    }
}
