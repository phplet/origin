<?php if ( ! defined('BASEPATH')) exit();
class Interview_question extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 试题列表
    public function index($mode = '')        
    {
        if ( ! $this->check_power('interview_question_list,interview_question_manage')) return; 

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $periods    = C('grade_period');
        $langs      = C('interview_lang');
        $types      = C('interview_type');

        $mode = $mode=='trash' ? $mode : '';
        
        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();

        if ($mode == 'trash')
            $where[] = "q.is_delete=1";
        else
            $where[] = "q.is_delete=0";

        // 录入人员
        if ($search['admin_id'] = intval($this->input->get('admin_id')))
        {
            $where[] = "q.admin_id='$search[admin_id]'";
            $param[] = "admin_id=$search[admin_id]";
        }
        
        // 操作时间
        $begin_time = $this->input->get('begin_time');
        $end_time   = $this->input->get('end_time');
        if ($btime = (int)strtotime($begin_time))
        {
            $search['begin_time'] = $begin_time;
            $where[] = "q.addtime >= $btime";
            $param[] = "begin_time=$begin_time";
        }
        else
        {
            $search['begin_time'] = '';
        }
        if ($etime = (int)strtotime($end_time))
        {
            $etime += 86400;
            $search['end_time'] = $end_time;
            $where[] = "q.addtime < $etime";
            $param[] = "end_time=$end_time";
        }
        else
        {
            $search['end_time'] = '';
        }

        // 语言
        if ($search['lang'] = intval($this->input->get('lang')))
        {
            $where[] = "q.lang='$search[lang]'";
            $param[] = "lang=$search[lang]";
        }

        // 分类(考点)
        if ($search['interview_type'] = intval($this->input->get('interview_type')))
        {
            $where[] = "q.interview_type='$search[interview_type]'";
            $param[] = "interview_type=$search[interview_type]";
        }

        // 年段
        $search['period'] = $this->input->get('period');        
        if ($search['period'] && is_array($search['period']))
        {
            foreach ($search['period'] as $pid)
            {
                $pid = intval($pid);
                $where[] = "grade_period LIKE '%$pid%'";
                $param[] = "period[]=$pid";
            }
        }
        else
        {
            $search['period'] = array();
        }

       
        // 类型
        $search['class_id'] = $this->input->get('class_id');      
 
        if ($search['class_id'] && is_array($search['class_id']))
        {
            foreach ($search['class_id'] as $cid)
            {
                $cid = intval($cid);
                $where[] = "class_id LIKE '%,$cid,%'";
                $param[] = "class_id[]=$cid";
            }
        }
        else
        {
            $search['class_id'] = array();
        }

        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $where[] = "q.content LIKE '%".$escape_keyword."%'";
            $param[] = "keyword=".urlencode($search['keyword']);
        }
        
        $where = $where ? implode(' AND ', $where) : ' 1 ';
        
        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}interview_question q WHERE $where";
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
            $sql = "SELECT q.*,a.admin_user,a.realname FROM {pre}interview_question q 
                    LEFT JOIN {pre}admin a ON q.admin_id=a.admin_id
                    WHERE $where ORDER BY q.id DESC LIMIT $offset,$size";
            
            
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
                $list[] = $row;
            }
        }
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/interview_question/index/'.$mode) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        $data['mode']        = $mode;
        $data['search']      = $search;
        $data['periods']     = $periods;
        $data['langs']       = $langs;
        $data['types']       = $types;
        $data['class_list']  = $class_list;
        $data['priv_delete'] = $this->check_power('interview_question_delete', FALSE);
        $data['priv_manage'] = $this->check_power('interview_question_manage', FALSE);
        
        $query = $this->db->select('admin_id,admin_user,realname')->get_where('admin', array('is_delete'=>0));
        
        $admin_list = $query->result_array();
        $data['admin_list'] = $admin_list;

        // 模版
        $this->load->view('interview_question/index', $data);
    }

    // 添加试题表单页面
    public function add()
    {
        if ( ! $this->check_power('interview_question_manage')) return;

        $row = array(
            'grade_period'    => '',
            'class_id'        => '',
            'lang'            => 1,
            'interview_type'  => 0,
            'content'         => '',
            'student_content' => '',
        );
        
        $data['act']          = 'add';
        $data['row']          = $row;
        $data['relate_group'] = intval($this->input->get('group'));
        $data['periods']      = C('grade_period');
        $data['langs']        = C('interview_lang');
        $data['types']        = C('interview_type');
        $data['back_url']     = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''; 

        // 类型
        $period_class = ClassModel::grade_period_class();
        $data['period_class'] = $period_class;
        $data['relate_class'] = array();

        // 模版
        $this->load->view('interview_question/edit', $data);
    }

    // 编辑试题表单页面
    public function edit($id=0)
    {
        if ( ! $this->check_power('interview_question_manage')) return;
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row))
        {
            message('试题不存在');
            return;
        }

        $data['act']      = 'edit';
        $data['row']      = $row;
        $data['periods']  = C('grade_period');
        $data['langs']    = C('interview_lang');
        $data['types']    = C('interview_type');
        $data['back_url'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''; 

        // 类型
        $period_class = ClassModel::grade_period_class();
        $data['period_class'] = $period_class;
        
        // 关联类型
        $query = $this->db->get_where('interview_relate_class', array('ques_id'=>$id));
        $relate_class = array();
        foreach ($query->result_array() as $arr)
        {
            $relate_class[$arr['grade_period']][$arr['class_id']] = $arr['id'];
        }
        $data['relate_class'] = $relate_class;

        // 模版
        $this->load->view('interview_question/edit', $data);
    }

    // 修改试题处理
    public function update()
    {
        if ( ! $this->check_power('interview_question_manage')) return;
        
        $id = intval($this->input->post('id'));
        $act = trim($this->input->post('act'));
        if ($act == 'edit')
        {            
            $id && $row = InterviewQuestionModel::get_question($id);
            if (empty($row))
            {
                message('试题不存在');
                return;
            }
        }

        // 题目基本信息        
        $class_ids              = $this->input->post('class_id');
        $row['interview_type']  = intval($this->input->post('interview_type'));
        $row['lang']            = intval($this->input->post('lang'));
        $row['content']         = trim($this->input->post('content'));
        $row['student_content'] = trim($this->input->post('student_content'));

        // 试题信息验证
        $message = array();
        if (empty($class_ids) || ! is_array($class_ids))
        {
            $message[] = '请选择试题类型';
        }
        else
        {
            // 年级段对应类型
            $period_class = ClassModel::grade_period_class();

            $relate_class = array();
            $periods = array();
            $ques_cids = array();
            foreach ($class_ids as $class)
            {
                list($period, $cid) = explode('-', $class);
                if ( ! isset($period_class[$period]) OR ! isset($period_class[$period][$cid])) continue;
                $relate = array(
                    'ques_id'      => $id,
                    'grade_period' => $period,
                    'class_id'     => $cid,
                );
                $periods[$period] = $period;
                $ques_cids[$cid]  = $cid;
                $relate_class[]   = $relate;
            }
            if (empty($ques_cids))
            {
                $message[] = '请选择试题类型11';
            }
            else
            {
                sort($ques_cids, SORT_NUMERIC);
                sort($periods, SORT_NUMERIC);
                $row['class_id']     = ','.implode(',',$ques_cids).',';
                $row['grade_period'] = ','.implode(',',$periods).',';
            }            
        }

        if (empty($row['lang']))
        {
            $message[] = '请选择语言';
        }
        if (empty($row['interview_type']))
        {
            $message[] = '请选择考点';
        }
        if (empty($row['content']))
        {
            $message[] = '请填写试题内容';
        }
        
        if ($message)
        {
            message(implode('<br>', $message), null, null, 10);
            return;
        }

        if ($act == 'edit')
        {
            $old_relates = array();
            $query = $this->db->get_where('interview_relate_class', array('ques_id'=>$id));
            foreach ($query->result_array() as $arr)
            {
                $old_relates[$arr['grade_period'].'-'.$arr['class_id']] = $arr['id'];
            }

            $this->db->trans_start();
            // 更新试题信息
            $this->db->update('interview_question', $row, array('id'=>$id));
            // 更新关联类型
            foreach ($relate_class as $k => $relate)
            {
                if (isset($old_relates[$relate['grade_period'].'-'.$relate['class_id']]))
                {                    
                    $this->db->update('interview_relate_class', $relate, array('id'=>$old_relates[$relate['grade_period'].'-'.$relate['class_id']]));
                    unset($old_relates[$relate['grade_period'].'-'.$relate['class_id']]);
                    unset($relate_class[$k]);
                }
            }
            if ($relate_class)
            {
                $this->db->insert_batch('interview_relate_class', $relate_class);
            }
            if ($old_relates)
            {
                $this->db->where_in('id', $old_relates)->delete('interview_relate_class');
            }

            $this->db->trans_complete();
            admin_log('edit', 'interview_question', $id);
        }
        else
        {
            // 关联分组
            if ($relate_group = intval($this->input->post('relate_group')))
            {
                $query = $this->db->get_where('relate_group', array('group_id'=>$relate_group));
                $group = $query->row_array();
                if (empty($group))
                    $relate_group = 0;
                $row['group_id'] = $relate_group;
            }
            
            $row['admin_id'] = $this->session->userdata('admin_id');
            $row['addtime'] = time();

            $this->db->trans_start();
            $this->db->insert('interview_question', $row);
            $id = $this->db->insert_id();
            if ($relate_class)
            {
                foreach ($relate_class as &$relate)
                {
                    $relate['ques_id'] = $id;
                }
                $this->db->insert_batch('interview_relate_class', $relate_class);
            }
            $this->db->trans_complete();
            admin_log('add', 'interview_question', $this->db->insert_id());
        }
        
        $back_url = trim($this->input->post('back_url'));
        if (empty($back_url) OR strpos($back_url, 'menu')!== false)
        {
            $back_url = 'admin/interview_question/index';
        }
        message('面试试题编辑成功', $back_url);
    }
    
    // 删除单个试题
    public function delete($id=0)
    {
        if ( ! $this->check_power('interview_question_delete')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index' : $_SERVER['HTTP_REFERER'];

        $return = $this->_delete($id);
        if ( $return === true )
            $message = '删除成功';
        else
        {
            switch($return)
            {
                case -1: $message = '试题不存在'; break;
                default: $message = '删除失败'; break;
            }             
        }
        message($message, $back_url);   
    }

    public function restore($id=0)
    {
        if ( ! $this->check_power('interview_question_trash')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index/trash':$_SERVER['HTTP_REFERER'];

        $return = $this->_restore($id);
        if ( $return === true )
            $message = '还原成功';
        else
        {
            switch($return)
            {
                case -1: $message = '试题不存在'; break;
                default: $message = '还原失败'; break;
            }             
        }
        message($message, $back_url);   
    }

    public function remove($id=0)
    {
        if ( ! $this->check_power('interview_question_trash')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index/trash' : $_SERVER['HTTP_REFERER'];

        $return = $this->_remove($id);
        if ( $return === true )
            $message = '移除成功';
        else
        {
            switch($return)
            {
                case -1: $message = '试题不存在'; break;
                default: $message = '移除失败'; break;
            }             
        }
        message($message, $back_url);   
    }
    
    // 批量删除
    public function batch_delete()
    {
        if ( ! $this->check_power('interview_question_delete')) return;
        $ids = $this->input->post('ids');
        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index' : $_SERVER['HTTP_REFERER'];

        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要删除的项目！');
            return;
        }
        $success = $fail = 0;
        foreach ($ids as $id)
        {
            if ($this->_delete($id) === true)
                $success++;
            else
                $fail++;
        }
        message('批量操作完成，成功删除：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    public function batch_restore()
    {
        if ( ! $this->check_power('interview_question_trash')) return;
        $ids = $this->input->post('ids');
        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index/trash' : $_SERVER['HTTP_REFERER'];

        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要还原的项目！');
            return;
        }
        $success = $fail = 0;
        foreach ($ids as $id)
        {
            if ($this->_restore($id) === true)
                $success++;
            else
                $fail++;
        }
        message('批量操作完成，成功还原：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    public function batch_remove()
    {
        if ( ! $this->check_power('interview_question_trash')) return;
        $ids = $this->input->post('ids');
        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index/trash' : $_SERVER['HTTP_REFERER'];

        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要移除的项目！');
            return;
        }
        $success = $fail = 0;
        foreach ($ids as $id)
        {
            if ($this->_remove($id) === true)
                $success++;
            else
                $fail++;
        }
        message('批量操作完成，成功移除：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    // 批量关联
    public function batch_relate()
    {
        if ( ! $this->check_power('interview_question_manage')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index' : $_SERVER['HTTP_REFERER'];

        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要关联的项目！');
            return;
        }
        // 检查被关联试题
        $relate_ques_id = intval($this->input->post('relate_ques_id'));
        $relate_ques_id && $relate_ques = QuestionModel::get_question($relate_ques_id);
        if (empty($relate_ques))
        {
            message('被关联试题不存在。');
            return;
        }
        // 如果被关联试题无分组，则：创建分组，并把该试题加入关联
        $group_id = $relate_ques['group_id'];
        if (empty($group_id))
        {
            $this->db->insert('relate_group', array('group_name'=>$relate_ques_id));
            $group_id = $this->db->insert_id();
            $this->db->update('question', array('group_id'=>$group_id), array('ques_id'=>$relate_ques_id));
        }
        
        $success = $fail = 0;
        foreach ($ids as $id)
        {
            $num = $this->_relate($id, $group_id);
           if ($num > 0)
               $success += $num;
           else
               $fail++;
        }
        message('批量操作完成，成功关联：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    // 批量取消相关试题
    public function batch_unrelate()
    {
        if ( ! $this->check_power('interview_question_manage')) return;
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/interview_question/index' : $_SERVER['HTTP_REFERER'];

        $success = $fail = 0;
        foreach ($ids as $id)
        {
            $num = $this->_unrelate($id);
            if ($num > 0)
                $success += $num;
            else
                $fail++;
        }

        message('批量操作完成，成功取消关联：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    // 执行删除操作
    private function _delete($id)
    {
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row)) 
        {          
            return -1;
        }

        // 删除试题
        try
        {
            $this->db->update('interview_question', array('is_delete'=>1), array('id'=>$id));
            admin_log('delete', 'interview_question', $id);
            return TRUE;
        } 
        catch(Exception $e)
        {
            return FALSE;
        }        
    }

    // 执行删除操作
    private function _restore($id)
    {
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row)) 
        {          
            return -1;
        }

        // 删除试题
        try
        {
            $this->db->update('interview_question', array('is_delete'=>0), array('id'=>$id));
            admin_log('restore', 'interview_question', $id);
            return TRUE;
        } 
        catch(Exception $e)
        {
            return FALSE;
        }        
    }

    // 执行删除操作
    private function _remove($id)
    {
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row)) 
        {          
            return -1;
        }

        // 删除试题
        try
        {
            $this->db->trans_start();
            $this->db->delete('interview_question', array('id'=>$id));
            $this->db->delete('interview_relate_class', array('ques_id'=>$id));
            $this->db->trans_complete();
            admin_log('remove', 'interview_question', $id);
            if ($row['group_id'])
            {
                $this->_clear_relate_group($row['group_id']);
            }
            return TRUE;
        } 
        catch(Exception $e)
        {
            return FALSE;
        }        
    }

    // 执行关联操作
    private function _relate($id, $group_id)
    {
        $id = intval($id);
        $group_id = intval($group_id);

        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row)) 
        {          
            return -1;
        }

        $this->db->update('interview_question', array('group_id'=>$group_id), array('id'=>$id));
        
        // 如果该试题原相关组下，已经没有相关试题，则删除分组
        if ($row['group_id'] && $row['group_id'] != $group_id)
        {
            $this->_clear_relate_group($row['group_id']);            
        }
        admin_log('relate', 'intview_question', $id.'，分组:'.$group_id);
        return 1;
    }

    // 执行取消关联
    private function _unrelate($id)
    {
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row)) 
        {          
            return -1;
        }

        if (empty($row['group_id']))
        {
            return -2;
        }

        $this->db->update('interview_question', array('group_id'=>0), array('id'=>$id));
        
        $this->_clear_relate_group($row['group_id']);
        admin_log('unrelate', 'interview_question', $id);
        return 1;
    }

    // 清理分组（删除没有关联试题的分组）
    private function _clear_relate_group($group_id)
    {
        $group_id = intval($group_id);
        if (empty($group_id)) return;

        $query = $this->db->select('ques_id')->get_where('question', array('group_id'=>$group_id));
        if ( ! $query->num_rows())
        {
            $query2 = $this->db->select('id')->get_where('interview_question', array('group_id'=>$group_id));
            if ( ! $query2->num_rows())
            {
                $this->db->delete('relate_group', array('group_id'=>$group_id));
            }
        }
    }  

    // 预览
    public function preview($id = 0)
    {
        if ( ! $this->check_power('interview_question_list, interview_question_manage')) return;
        $id = intval($id);
        $id && $row = InterviewQuestionModel::get_question($id);
        if (empty($row))
        {
            message('试题不存在', 'admin/interview_question/index');
            return;
        }

        // 类型
        $class_list = ClassModel::get_class_list();

        $periods = C('grade_period');
        $langs   = C('interview_lang');
        $types   = C('interview_type');
        
        $class_names = array();
        $query = $this->db->get_where('interview_relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $arr)
        {
            $class_names[$arr['grade_period']][$arr['class_id']] = isset($class_list[$arr['class_id']]) ? $class_list[$arr['class_id']]['class_name'] : '';
        }
        $data['class_names'] = $class_names;
        $data['periods'] = $periods;

        $row['language'] = isset($langs[$row['lang']]) ? $langs[$row['lang']] : '';
        $row['type_name'] = isset($types[$row['interview_type']]['type_name']) ? $types[$row['interview_type']]['type_name'] : '';
        $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
        $row['content'] = str_replace("\r\n", '<br/>', $row['content']);
        $row['student_content'] = str_replace("\r\n", '<br/>', $row['student_content']);
        $data['row']     = $row;

        // 模版
        $this->load->view('interview_question/preview', $data);
    }
}
