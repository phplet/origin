<?php if ( ! defined('BASEPATH')) exit();

class Question_external extends A_Controller
{
    /* 构造函数 */
    public function __construct()
    {
        parent::__construct();

        require_once (APPPATH.'config/app/admin/recycle.php');

        if ($_FILES) {
            $config['upload_path']   = _UPLOAD_ROOT_PATH_.'uploads/question/'.date('Ym').'/';
            $config['allowed_types'] = 'gif|jpg|jpeg|png';
            $config['max_size']      = '500';
            $config['max_width']     = '800';
            $config['max_height']    = '500';
            $config['encrypt_name']  = TRUE;
            $this->load->library('upload', $config);
        }

        /* 权限检测 */
        if (!$this->check_power('question_external_manage')) {
            message('没有操作权限!');
        }
    }

    /* 检测当前用户是否具备编辑权限 */
    private function examine_permission($ques_id)
    {
        if (!empty($ques_id)) {
            $question = QuestionModel::get_question($ques_id, 'ques_id,admin_id');

            /* 用户信息 */
            $admin_info = $this->session->all_userdata();

            if (empty($admin_info['admin_id'])) {
                message('获取管理员数据失败，请从新登陆后重试！');
            }

            if ($question['admin_id'] != $admin_info['admin_id']) {
                message('没有当前的试题操作权限！(error:02)');
            }

        } else {
            message('没有当前的试题操作权限！(error:01)');
        }
    }

    /* -----------------试题-------------------------------- */

    // 试题列表
    public function index($mode='')
    {
        // 加载分类数据
        $class_list = ClassModel::get_class_list();

        //限制只能查看所属学科
        $subjects   = CpUserModel::get_allowed_subjects();
        $grades     = C('grades');
        $qtypes     = C('qtype');
        $knowledge_list = KnowledgeModel::get_knowledge_list();

        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();
        $where[] = "q.parent_id=0"; // 过滤题组子题目，在题组页面管理
        $mode = $mode=='trash' ? 'trash' : '';

        if ($mode == 'trash')
        {
            $where[] = "q.is_delete = 1";
        }
        else
        {
            $where[] = "q.is_delete <> 1";
        }

        /* 用户信息 */
        $admin_info = $this->session->all_userdata();

        if (empty($admin_info['admin_id'])) {
            message('获取管理员数据失败，请从新登陆后重试！');
        } else {
            $where[] = "q.admin_id=$admin_info[admin_id]";
            $param[] = "admin_id=$admin_info[admin_id]";
            $search['admin_id'] = $admin_info['admin_id'];
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
        // 分组
        if ($search['group_id'] = intval($this->input->get('group_id')))
        {
            $where[] = "q.group_id=$search[group_id]";
            $param[] = "group_id=$search[group_id]";
        }
        // 题型
        $search['type'] = $this->input->get('qtype');
        if (strlen($search['type']) > 0)
        {
            $search['type'] = intval($search['type']);
            $where[] = "q.type=$search[type]";
            $param[] = "qtype=$search[type]";
        }
        // 考试方式
        $search['test_way'] = $this->input->get('test_way');
        if (strlen($search['test_way']) > 0)
        {
            $search['test_way'] = intval($search['test_way']);
            $where[] = "q.test_way IN ($search[test_way], 3)";
            $param[] = "test_way=$search[test_way]";
        }
        
        // 审核
        $search['check'] = $this->input->get('check');
        if (strlen($search['check']) > 0)
        {
            $search['check'] = intval($search['check']);
            $where[] = "q.check=$search[check]";
            $param[] = "check=$search[check]";
        }


        if ($search['subject_id'] = intval($this->input->get('subject_id')))
        {
            $where[] = "q.subject_id=$search[subject_id]";
            $param[] = "subject_id=$search[subject_id]";
            $knowledge_parents = KnowledgeModel::get_knowledge_list($search['subject_id'],0);
        }

        //信息提取方式
        if ($search['group_type_id'] = intval($this->input->get('group_type_id')))
        {
            $where[] = "q.group_type LIKE '%," . $search['group_type_id'] . ",%'";
            $param[] = "group_type_id=" . $search['group_type_id'];
        }

        if ($search['group_type_pid'] = intval($this->input->get('group_type_pid')))
        {
            $param[] = "group_type_pid=" . $search['group_type_pid'];
            if (isset($group_type_list[$search['group_type_pid']]['childlist'])
            && !$search['group_type_id'])
            {
                $group_type = $group_type_list[$search['group_type_pid']]['childlist'];
                $tmp_str = '';
                foreach ($group_type as $item)
                {
                    if ($tmp_str)
                    {
                        $tmp_str .=  " OR q.group_type LIKE '%," . $item['id'] . ",%'";
                    }
                    else
                    {
                        $tmp_str .=  "q.group_type LIKE '%," . $item['id'] . ",%'";
                    }
                }
                $where[] = "(" . $tmp_str . ")";
            }
        }

        // 题目类型
        $search['is_original'] = $this->input->get('is_original');
        $search['exam_year'] = $this->input->get('exam_year');
        $search['remark'] = $this->input->get('remark');

        if ($search['is_original'] > 0)
        {
            $search['is_original'] = intval($search['is_original']);
            $where[] = "q.is_original=$search[is_original]";
            $param[] = "is_original=$search[is_original]";

            //真题年份
            if($search['is_original'] == 2 && strlen($search['exam_year']) > 0)
            {
                $search['exam_year'] = intval($search['exam_year']);
                $where[] = "q.exam_year='$search[exam_year]'";
                $param[] = "exam_year=$search[exam_year]";
            }

            // 真题备注关键词
            if($search['is_original'] == 2 && strlen($search['remark']) > 0)
            {
                $search['remark'] = $search['remark'];
                $where[] = "q.remark like '%".$search['remark']."%'";
                $param[] = "remark=$search[remark]";
            }
        }

        //标签
        if ($search['tags'] = $this->input->get('tags'))
        {
            $tmp_item = explode('-', $search['tags']);
            if (strlen($search['type']) > 0)
            {
                $where[] = "q.tags = " . $tmp_item[1];
                $param[] = "tags=" . $search['tags'];
            }
            else
            {
                $where[] = "q.type = " . $tmp_item[0] . " AND q.tags = " . $tmp_item[1];
                $param[] = "tags=" . $search['tags'];
            }
        }

       	$where[] = "q.ques_id IN (SELECT DISTINCT ques_id FROM rd_relate_class) ";

       	if ($search['subject_id_str'] = $this->input->get('subject_str')) {
       	    $param[] = implode('&subject_str[]=', $search['subject_id_str']);
       	    $search['subject_id_str'] = implode(',', $search['subject_id_str']);
       	    $where[] = "q.subject_id_str = '," . $search['subject_id_str'] . ",'";
       	}

        //-----------------------------------------//
        // 年级区间、试题类型、难易度区间、文理科
        //-----------------------------------------//
        $search['start_grade']      = intval($this->input->get('start_grade'));
        $search['end_grade']        = intval($this->input->get('end_grade'));
        $search['class_id']         = $this->input->get('class_id');
        $search['start_difficulty'] = floatval($this->input->get('start_difficulty'));
        $search['end_difficulty']   = floatval($this->input->get('end_difficulty'));
        $search['subject_type']     = trim($this->input->get('subject_type'));

        if(is_array($search['class_id']))
            $search['class_id'] = my_intval($search['class_id']);
        else
            $search['class_id'] = array();

        if ($search['class_id'] OR $search['start_difficulty'] OR $search['end_difficulty'] OR is_numeric($search['subject_type']))
        {
            $class_where = array();
            if ($search['end_grade'])
            {
                $class_where[] = "grade_id BETWEEN $search[start_grade] AND $search[end_grade]";
            }
            elseif ($search['start_grade'])
            {
                $class_where[] = "grade_id >= $search[start_grade]";
            }
            if ($search['class_id'])
            {
                if (count($search['class_id']) == 1)
                    $class_where[] = "class_id='".$search['class_id'][0]."'";
                else
                    $class_where[] = "class_id IN (".my_implode($search['class_id']).")";
            }
            //文理科
            if (is_numeric($search['subject_type']))
            {
                $class_where[] = "subject_type='".$search['subject_type']."'";
            }
            if ($search['end_difficulty'])
            {
                $class_where[] = "difficulty BETWEEN $search[start_difficulty] AND $search[end_difficulty]";
            }
            elseif ($search['start_difficulty'])
            {
                $class_where[] = "difficulty >= $search[start_difficulty]";
            }
            if ($class_where)
            {
                $where[] = "q.ques_id IN (SELECT DISTINCT ques_id FROM {pre}relate_class WHERE ".implode(' AND ', $class_where).")";
            }
        }
        elseif ($search['start_grade'] && $search['end_grade'])
        {
            if ($search['start_grade'] <= $search['end_grade'])
            {
                $where[] = "q.start_grade <= $search[end_grade] AND q.end_grade>= $search[start_grade]";
            }

        }
        elseif ($search['start_grade'])
        {
            $where[] = "q.start_grade <= $search[start_grade] AND q.end_grade>= $search[start_grade]";
        }
        elseif ($search['end_grade'])
        {
            $where[] = "q.start_grade <= $search[end_grade] AND q.end_grade>= $search[end_grade]";
        }
        // url参数
        if ($search['start_grade'])
            $param[] = "start_grade=".$search['start_grade'];
        else
            $search['start_grade'] = '';

        if ($search['end_grade'])
            $param[] = "end_grade=".$search['end_grade'];
        else
            $search['end_grade'] = '';

        if ($search['class_id'])
            $param[] = "class_id[]=".implode('&class_id[]=',$search['class_id']);

        if (is_numeric($search['subject_type']))
            $param[] = "subject_type=".$search['subject_type'];

        if ($search['start_difficulty'])
            $param[] = "start_difficulty=".$search['start_difficulty'];
        else
            $search['start_difficulty'] = '';

        if ($search['end_difficulty'])
            $param[] = "end_difficulty=".$search['end_difficulty'];
        else
            $search['end_difficulty'] = '';

        // 试题技能
        $search['skill_id'] = $this->input->get('skill_id');
        if ($search['skill_id'] && is_array($search['skill_id']))
        {
            foreach ($search['skill_id'] as $sid)
            {
                $sid = intval($sid);
                $where[] = "q.skill LIKE '%,$sid,%'";
                $param[] = "skill_id[]=$sid";
            }
        }
        else
        {
            $search['skill_id'] = array();
        }

        // 试题方法策略
        $method_tactic_ids = trim($this->input->get('method_tactic_ids'));
        $method_tactic_arr = my_intval(explode(',', $method_tactic_ids));
        $search['method_tactic_ids'] = implode(',', $method_tactic_arr);
        if ($search['method_tactic_ids']) {
        	$param[] = "method_tactic_ids=".$search['method_tactic_ids'];
        	$where[] = "exists(select ques_id from {pre}relate_method_tactic rmt WHERE q.ques_id=rmt.ques_id AND rmt.method_tactic_id IN ({$search['method_tactic_ids']}) and rmt.is_child=0)";
        }

        // 试题知识点
        $knowledge_ids = trim($this->input->get('knowledge_ids'));
        $knowledge_arr = my_intval(explode(',', $knowledge_ids));

        $search['knowledge_ids'] = implode(',', $knowledge_arr);

        $know_processes = $this->input->get('know_process');
        $search['know_process'] = my_intval($know_processes);
        if ($search['knowledge_ids']) {
        	$param[] = "knowledge_ids=".$search['knowledge_ids'];
        	if ($search['know_process'])
        	{
        		$tmp_know_process = implode(',',$search['know_process']);
	        	$param[] = "know_process[]=".implode('&know_process[]=',$search['know_process']);
	        	$where[] = "exists(select ques_id from {pre}relate_knowledge rk WHERE q.ques_id=rk.ques_id AND rk.knowledge_id IN ({$search['knowledge_ids']}) AND rk.know_process IN ({$tmp_know_process}) AND rk.is_child=0)";
        	}
        	else
        	{
	        	$where[] = "exists(select ques_id from {pre}relate_knowledge rk WHERE q.ques_id=rk.ques_id AND rk.knowledge_id IN ({$search['knowledge_ids']}) AND rk.is_child=0)";

        	}
        }
        else
        {
        	if ($search['know_process'])
        	{
        		$tmp_know_process = implode(',',$search['know_process']);
        		$param[] = "know_process[]=".implode('&know_process[]=',$search['know_process']);
        		$where[] = "exists(select ques_id from {pre}relate_knowledge rk WHERE q.ques_id=rk.ques_id AND rk.know_process IN ({$tmp_know_process}) AND rk.is_child=0)";
        	}
        }

        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $where[] = "q.title LIKE '%".$escape_keyword."%'";
            $param[] = "keyword=".urlencode($search['keyword']);
        }

        if ($ques_id = intval($this->input->get('ques_id')))
        {


	        $search['ques_id'] = $ques_id;
            $where[] = "q.ques_id={$ques_id}";
            $param[] = "ques_id={$ques_id}";
        }

        $where = $where ? ' WHERE '.implode(' AND ', $where) : ' 1 ';
        //echo $where;


        // 统计数量
        $nums = QuestionModel::get_question_nums($where);



        $total = $nums['total'];

        // 读取数据
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $sql = "SELECT q.*,a.admin_user,a.realname FROM {pre}question q
                    LEFT JOIN {pre}admin a ON a.admin_id=q.admin_id
                    $where ORDER BY q.ques_id DESC LIMIT $offset,$size";
            $res = $this->db->query($sql);


            foreach ($res->result_array() as $row)
            {
                $row_cids = explode(',', trim($row['class_id'], ','));
                $row_cname = array();
                foreach ($row_cids as $cid)
                {
                    $row_cname[] = isset($class_list[$cid]['class_name']) ? $class_list[$cid]['class_name'] : '';
                }

                if($row['exam_year']==0)$row['exam_year']='';

                if($row['related']==0)$row['related']='';

                $row['class_name'] = implode(',', $row_cname);
                $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
                $row['start_grade'] = isset($grades[$row['start_grade']]) ? $grades[$row['start_grade']] : '';
                $row['end_grade'] = isset($grades[$row['end_grade']]) ? $grades[$row['end_grade']] : '';
                $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

                //判断该试题已经被考试过 或 正在被考
                $row['be_tested'] = QuestionModel::question_has_test_action($row['ques_id']);
                //判断试题已经被考过
                $row['be_tested_1'] = QuestionModel::question_has_be_tested($row['ques_id']);

                $row['has_edit_power'] = true;

                /*
                 * 检查是否有关联信息
                 * 非题组：关联技能
                 * 题组：题干关联技能、知识点，子题必须要全部添加知识点
                 */

                $no_relate_info = false;
                $q_type = $row['type'];
                if ($q_type > 0)
                {
                	//非题组
                	$no_relate_info = $row['skill'] == '';
                }
                else
                {
                	//题组
					//判断是否所有子题都已添加知识点
					$tmp_ques_id = $row['ques_id'];
					$child_questions = $this->db->query("select count(*) as `count` from {pre}question where parent_id={$tmp_ques_id}")->row_array();
					$count_child = $child_questions['count'];

					$child_count_result = $this->db->query("select count(*) as `count` from {pre}question where parent_id={$tmp_ques_id} and knowledge != ''")->row_array();
					$tmp_count = $child_count_result['count'];

					if ($count_child == $tmp_count && $row['skill'] == '' && $row['knowledge'] == '')
					{
						$no_relate_info = true;
					}
                }

                $row['no_relate_info'] = $no_relate_info;

                //获取回收站信息
                if ($mode == 'trash')
                {
                	$recycle = RecycleModel::get_recycle_list(array('type' => RECYCLE_QUESTION, 'obj_id' => $row['ques_id']), null, null, 'ctime asc');
                	$row['recycle'] = $recycle;
                }
                else
                {
                	$row['recycle'] = array();
                }

                $list[] = $row;
            }
        }

        $data['list'] = &$list;

        // 分页
        $purl = site_url('admin/question_external/index/'.$mode) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl, '', $nums['relate_num']);

        if ($search['group_id'] && $list)
        {
            $row = array_pop($list);
            $data['relate_ques_id'] = $row['ques_id'];
        }

        $data['mode']       = $mode;
        $data['search']     = $search;
        $data['grades']     = $grades;
        $data['subjects']   = $subjects;
        $data['qtypes']     = $qtypes;
        $data['q_tags']     = C('q_tags');
        $data['class_list'] = $class_list;
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class'] = array();
        $data['knowledge_list'] = $knowledge_list;
        $data['priv_delete'] = true;
        $data['priv_trash']  = true;
        $data['priv_manage']  = true;
	   $query = $this->db->select('admin_id,admin_user,realname')->get_where('admin', array('is_delete'=>0));
       $data['admin_list'] = $query->result_array();

        //文理科
        $data['subject_types'] = C('subject_type');

        $data['all_subjects'] = C('subject');

        //认知过程
        $data['know_processes'] = C('know_process');
        $data['is_english_admin'] = true;

        // 模版
        $this->load->view('question_external/index', $data);
    }

    /**
     * 试题详情页面
     *
     * @return void
     */
    public function preview($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $row = QuestionModel::get_question($id);

        if (empty($row)) {
            message('试题不存在', 'admin/question_external/index');
            return;
        }

        if ($row['type'] == 9)
        {
            redirect('admin/question_external/showSentence/'.$id);
        }

        if ($row['is_parent'] || $row['parent_id'])
        {
            if ($row['type'] == 4)
            {
                redirect('admin/question_external/cloze/'.$id);
            }
            elseif ($row['type'] == 5)
            {
                redirect('admin/question_external/match/'.$id);
            }
            elseif ($row['type'] == 6)
            {
                redirect('admin/question_external/diction/'.$id);
            }
            elseif ($row['type'] == 8)
            {
                redirect('admin/question_external/blank/'.$id);
            }
            else
            {
                redirect('admin/question_external/group/'.$id);
            }
        }

        // 分类数据
        $class_list = ClassModel::get_class_list();
        $grades           = C('grades');
        $subjects         = CpUserModel::get_allowed_subjects();
        $subject_types    = C('subject_type');

        // 类型、学科属性（文理科）、难易度
        $show_subject_type = $row['subject_id']<= 3 && $row['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $arr)
        {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }
        $data['class_names'] = $class_names;

        // 年段
        $row['start_grade'] = isset($grades[$row['start_grade']]) ? $grades[$row['start_grade']] : '';
        $row['end_grade'] = isset($grades[$row['end_grade']]) ? $grades[$row['end_grade']] : '';

        // 学科
        $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';

        //关联知识点 认知过程
        $know_processes = array();
        $tmp_knowledge_ids = explode(',', $row['knowledge']);
        $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
        $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
        if ($tmp_knowledge_ids != '') {
            $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
            foreach ($result as $item) {
                $know_processes[$item['knowledge_id']] = C('know_process/'.$item['know_process']);
            }
        }

        //关联方法策略
        $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $id)->get();
        $method_tactic_arr = array();
        foreach($query->result_array() as $arr)
        {
            $method_tactic_arr[] = $arr['name'];
        }

        $method_tactic_arr = array_filter($method_tactic_arr);
        $row['method_tactic'] = implode(',', $method_tactic_arr);
        $row['count_subject_method_tactics'] = $this->_count_subject_method_tactics($row['subject_id']);

        // 知识点
        $query = $this->db->select('k.knowledge_name, rk.knowledge_id')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $id)->get();
        $knowledge_arr = array();
        foreach($query->result_array() as $arr)
        {
            $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . (isset($know_processes[$arr['knowledge_id']]) ? $know_processes[$arr['knowledge_id']] : '--') . '）';
        }
        $row['knowledge'] = implode(',', $knowledge_arr);

        // 技能
        $query = $this->db->select('s.skill_name')->from('relate_skill rs')->join('skill s','rs.skill_id=s.id','left')->where('rs.ques_id', $id)->get();
        $skill_arr = array();
        foreach($query->result_array() as $arr)
        {
            $skill_arr[] = $arr['skill_name'];
        }
        $row['skill'] = implode(',', $skill_arr);
        $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

        $row['title'] = str_replace("\r\n", '<br/>', $row['title']);
        //$row['title'] = str_replace(" ", '&nbsp;', $row['title']);

        if (in_array($row['type'],array(3)))
        {
            $row['answer'] = explode("\n", $row['answer']); //str_replace("\n", '<br/>', $row['answer']);
        }
        else
        {
            $row['answer'] = explode(',', $row['answer']);
        }

        /** ---------------- 真题 ----------------- */
        if ($row['is_original'] == 2)
        {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$id}")->result_array();

            if (count($relateds) > 0)
            {
                $row['relateds'] = $relateds;
            }
        }
        elseif ($row['related'])
        {
            $related = $row['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $row['relateds'] = $relateds;
            }
        }

        $data['row']     = $row;
        $data['grades'] = $grades;

        $q_tags = C('q_tags');
        if (isset($q_tags[$row['type']]))
        {
            $data['q_tag'] = $q_tags[$row['type']][$row['tags']];
        }
        else
        {
            $data['q_tag'] = '';
        }

        // options
        $options = array();
        $query = $this->db->get_where('option', array('ques_id'=>$id));
        foreach ($query->result_array() as $arr)
        {
            $arr['is_answer'] = in_array($arr['option_id'],$row['answer']);
            $options[] = $arr;
        }
        $data['options'] = $options;
        $data['qtype'] = C('qtype');

        $data['priv_manage'] = true;

        // 模版
        $this->load->view('question_external/preview', $data);
    }

    // 添加试题表单页面
    public function add ($relate_ques_id=0)
    {
        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id        = intval($this->input->get('copy'));
        $type           = intval($this->input->get('type'));
        $type           = $type ? $type : 1;

        if ($type == 9)
        {
            redirect('admin/question_external/addSentence/?copy='.$copy_id);
        }

        $relate_class  = array();

        if ($copy_id)
        {
            $this->examine_permission($copy_id);

            $question = QuestionModel::get_question($copy_id);

            if ($question) {
                $question['type'] = $type;
                $question['p_type'] = $type;
            }

            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));

            foreach ($query->result_array() as $row) {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question)) {
            $question = array(
                'type'        => $type,
                'p_type'      => $type,
                'class_id'    => '',
                'skill'       => '',
                'knowledge'   => '',
                'method_tactic' => '',
                'subject_id'  => ($type == 7 ? 3 : ''),
                'subject_id_str'  => '',
                'subject_type'=> 0,
                'start_grade' => '',
                'end_grade'   => '',
                'title'       => '',
                'answer'      => '',
                'count_subject_method_tactics' => '0',
                'is_original' => '',
                'exam_year' => '',
                'remark' => '',
                'related' => '',
            );
        }

        $data['act']             = 'add';
        $data['options']         = array();
        $data['question']        = $question;
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades']          = C('grades');

        $data['know_processes']  = array();

        $data['subjects']        =  CpUserModel::get_allowed_subjects($q=2);
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = &$relate_class;
        $data['back_url']        = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // 模版
        if ($type == 7) {
            $this->load->view('question_external/edit_translation', $data);
        } else {
            $this->load->view('question_external/edit', $data);
        }
    }

    // 编辑试题表单页面
    public function edit($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);

        if ($question['related']==0)$question['related']='';
        if ($question['exam_year']==0)$question['exam_year']='';

        if (empty($question)) {
            message('试题不存在');
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        //跳转到相应编辑页面
        if ($question['type'] == 0)
        {
        	if ($question['parent_id'] == 0)
        	    redirect('admin/question_external/edit_group/'.$id);
        	else
        	    redirect('admin/question_external/edit_question_group/'.$id);
        }
        elseif ($question['type'] == 4)
        {
            if ($question['parent_id'] == 0)
                redirect('admin/question_external/edit_cloze/'.$id);
            else
                redirect('admin/question_external/edit_cloze_question/'.$id);
        }
        elseif ($question['type'] == 5)
        {
            if ($question['parent_id'] == 0)
                redirect('admin/question_external/edit_match/'.$id);
            else
                redirect('admin/question_external/edit_match_question/'.$id);
        }
        elseif ($question['type'] == 8)
        {
            if ($question['parent_id'] == 0)
                redirect('admin/question_external/edit_blank/'.$id);
            else
                redirect('admin/question_external/edit_blank_question/'.$id);
        }
        elseif ($question['type'] == 6)
        {
            if ($question['parent_id'] == 0)
                redirect('admin/question_external/edit_diction/'.$id);
            else
                redirect('admin/question_external/edit_diction_question/'.$id);
        }
        elseif ($question['type'] == 9)
        {
            redirect('admin/question_external/editSentence/'.$id);
        }

        //以下情况无法操作试题：
		/**
		*	1、已被考过的试题
		*	2、有关联正在进行的考试 试题->试卷->学科
        */

        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
			message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $query = $this->db->get_where('option', array('ques_id'=>$id));
        $options = $query->result_array();
        $data['options'] = &$options;

        // 试题类型
        $relate_class = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }
        $data['relate_class'] = &$relate_class;

        //获取关联知识点对应的认知过程
        $subject_ids = array();
        $know_processes = array();
        if ($id && $question['knowledge']) {
        	$tmp_knowledge_ids = explode(',', $question['knowledge']);
        	$tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
        	$tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            $result = $this->db
                ->query("select knowledge_id, know_process from {pre}relate_knowledge
                where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")
                ->result_array();

            foreach ($result as $item) {
                $know_processes[$item['knowledge_id']] = array(
                    'kp' => $item['know_process'],
                    'name' => C('know_process/'.$item['know_process']),
                );
            }
        }

        //试题学科 关联的方法策略数
        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($question['subject_id']);

        ($question['type'] == 7) && $question['p_type'] = $question['type'];

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['know_processes']  = $know_processes;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects($q=2);
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['back_url']        = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // 模版
        if ($question['type'] == 7)
        {
            $this->load->view('question_external/edit_translation', $data);
        }
        else
        {
            $this->load->view('question_external/edit', $data);
        }
    }

    // 添加/编辑试题处理页面
    public function update()
    {
        $subject_id = intval($this->input->post('subject_id'));
        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';

        if ($act == 'edit') {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question)) {
                message('试题不存在');
                return;
            }

	        /**
			*	1、已被考过的试题
			*	2、有关联正在进行的考试 试题->试卷->学科
			 */
	        $be_tested = QuestionModel::question_has_test_action($ques_id);
	        if ($be_tested) {
				message('该试题已经被考生考过 或者 正在被考， 无法操作');
	        }
        }

        // 题目基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['type']         = intval($this->input->post('qtype'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['tags']         = intval($this->input->post('q_tag'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']      = $this->input->post('simulation');

        if ($question['subject_id'] == 11)
        {
            $question['subject_id_str'] = ',' . implode(',', $this->input->post('subject_str')) . ',';
        }
        else
        {
            $question['subject_id_str'] = ',' . $question['subject_id'] . ',';
        }

        $extends = array(
            'skill_ids'      => &$skill_ids,
            'method_tactic_ids' => &$method_tactic_ids,
            'knowledge_ids'  => &$knowledge_ids,
            'know_process'   => &$know_process,
            'class_ids'      => &$class_ids,
            'difficulty'     => &$difficulty,
            'subject_types'  => &$subject_types
        );

        $message = $this->_check_question($question, $extends);

        if ($message) {
            message(implode('<br/>', $message), null, null, 10);
            exit;
        }

        // 选择题选项检查
        if ($question['type'] < 3 || $question['type'] == 7)
        {
            $options = $this->input->post('option');
            $answer = $this->input->post('answer');

            $opt_result = $this->_check_options($question['type'], $options, $answer);

          /*  if ($opt_result['msg'])
            {
                message(implode('<br>', $opt_result['msg']), null, null, 10);
                return;
            }
            */
        }
        else
        {
            $opt_result = array(
                'options' => array(),
                'answer' => 0,
            );
        }

        if ($act == 'add')
        {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $question['group_id']   = $old_question['group_id'];
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }




        //$message = $this->_check_question($question, $extends);
        $url = site_url('/admin/question_external/edit/'.$ques_result['ques_id']);

        if ($message)  {
            message(implode('<br/>', $message), $url, null, 10);
            exit;
        }

        // 选择题选项检查
        //if ($question['type'] < 3 || $question['type'] == 7)
      //  {
            //$options = $this->input->post('option');
            //$answer = $this->input->post('answer');

           // $opt_result = $this->_check_options($question['type'], $options, $answer);

            if ($opt_result['msg'])
            {
                message(implode('<br>', $opt_result['msg']), $url, null, 10);
                return;
            }
       // }
       // else
      //  {
       //     $opt_result = array(
           //         'options' => array(),
            //        'answer' => 0,
           // );
     //   }




        if ($ques_result['success'] == TRUE)
        {
            $data['relate_ques_id'] = $ques_result['ques_id'];
            $data['relate_group']   = isset($ques_result['group_id']) ? $ques_result['group_id'] : 0;

            // 模版
            $this->load->view('question_external/success', $data);
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    /* -----------------题组-------------------------------- */

    // 题组
    public function group($parent_id = 0)
    {
        // 读取题干信息
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在！');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

        $grades   = C('grades');
        $qtypes   = C('qtype');
        $subjects = CpUserModel::get_allowed_subjects();
        $subject_types    = C('subject_type');

        // 类型、学科属性（文理科）、难易度
        $class_list = ClassModel::get_class_list();
        $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));
        foreach ($query->result_array() as $arr)
        {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }
        $parent['class_names'] = $class_names;

        // 技能
        $skill_ids = explode(',', trim($parent['skill'], ','));
        $arr_tmp = array();
        if ($skill_ids)
        {
            $query = $this->db->select('skill_name')->where_in('id', $skill_ids)->get('skill');
            foreach ($query->result_array() as $row)
            {
                $arr_tmp[] = $row['skill_name'];
            }
        }
        $parent['skill_name'] = implode(',', $arr_tmp);

        //关联方法策略
        /*
        $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $parent_id)->get();
        $method_tactic_arr = array();
        foreach($query->result_array() as $arr)
        {
        	$method_tactic_arr[] = $arr['name'];
        }

        $method_tactic_arr = array_filter($method_tactic_arr);
        $parent['method_tactic_name'] = implode(',', $method_tactic_arr);
        */
        $parent['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        // 知识点
        /*
        $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $parent_id)->get();
        $knowledge_arr = array();
        $tmp_knowledge = array();
        $result = $query->result_array();
        foreach($result as $arr)
        {
        	if (!isset($tmp_knowledge[$arr['knowledge_id']]))
        	{
        		$tmp_knowledge[$arr['knowledge_id']] = array('name' => $arr['knowledge_name'], 'know_process' => array());
        	}
        	$tmp_knowledge[$arr['knowledge_id']]['know_process'][] = $arr['know_process'];
        }

        foreach($tmp_knowledge as $arr)
        {
        	$tmp_know_process = array();
        	foreach ($arr['know_process'] as $row) {
        		$tmp_know_process[] = C('know_process/'.$row);
        	}
        	$knowledge_arr[] = $arr['name'] . '（认知过程：' . implode('、', $tmp_know_process) . '）';
        }
        $parent['knowledge_name'] = implode(',', $knowledge_arr);
        */
        // 知识点
        $knowledge_ids = explode(',', trim($parent['knowledge'], ','));
        $arr_tmp = array();
        if ($knowledge_ids)
        {
        	$query = $this->db->select('knowledge_name')->where_in('id', $knowledge_ids)->get('knowledge');
        	foreach ($query->result_array() as $row)
        	{
        		$arr_tmp[] = $row['knowledge_name'];
        	}
        }
        $parent['knowledge_name'] = implode(',', $arr_tmp);

        $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
        $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
        $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

        $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
        $parent['title'] = str_replace("\n", '<br/>', $parent['title']);
        //$parent['title'] = str_replace(" ", '&nbsp;', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2)
        {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related'])
        {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }

        $cpusers = CpUserModel::get_cpuser_list();

        // 读取子题信息
        $query = $this->db->select('ques_id,type,title,picture,answer,knowledge,method_tactic,group_type,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
        $list = $query->result_array();
        foreach ($list as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();

            foreach ($row['options'] as $key => $arr)
            {
                $row['options'][$key]['is_answer'] = in_array($arr['option_id'],explode(',',$row['answer']));
            }

            // 知识点
            $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
            $knowledge_arr = array();
            foreach($query->result_array() as $arr)
            {
            	$knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
            }
            $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';

            //方法策略
            $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $row['ques_id'])->get();
            $method_tactic_arr = array();
            foreach($query->result_array() as $arr)
            {
            	$method_tactic_arr[] = $arr['name'];
            }
            $method_tactic_arr = array_filter($method_tactic_arr);
            $row['method_tactic_name'] = implode(',', $method_tactic_arr);

            if ($row['group_type'])
            {
                $query = $this->db->select('gt.group_type_name')->from('relate_group_type rgt')->join('group_type gt','rgt.group_type_id=gt.id','left')->where('rgt.ques_id', $row['ques_id'])->get();
                $group_type_arr = array();
                foreach($query->result_array() as $arr)
                {
                    $group_type_arr[] = $arr['group_type_name'];
                }
                $group_type_arr = array_filter($group_type_arr);
                $row['group_type_name'] = implode(',', $group_type_arr);
            }
        }

        // 读取子题信息(已删除)
        $query = $this->db->select('ques_id,type,title,picture,answer,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
        $list2 = $query->result_array();
        foreach ($list2 as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();
        }

        $data['qtypes'] = $qtypes;
        $data['grades'] = $grades;
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['list2']  = $list2;
        $data['priv_manage'] = true;
        $data['priv_delete'] = true;

        // 模版
        $this->load->view('question_external/group', $data);
    }

    // 添加题组
    public function add_group($relate_ques_id = 0)
    {
        $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id        = intval($this->input->get('copy'));

        $be_tested = QuestionModel::question_has_test_action($copy_id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_class   = array();

        if ($copy_id) {
            $this->examine_permission($copy_id);
            $question = QuestionModel::get_question($copy_id);
            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));

            foreach ($query->result_array() as $row) {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question))
        {
            $question = array(
                'class_id'    => '',
                'skill'       => '',
                'knowledge'   => '',
                'subject_id'  => '',
                'subject_id_str'  => '',
                'start_grade' => '',
                'end_grade'   => '',
                'title'       => '',
                'score_factor' => '',
                'is_original' => '',
                'exam_year' => '',
                'remark' => '',
                'related' => '',
                'test_way' => '',
            );
        }

        $data['act']             = 'add';
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = $relate_class;
        $data['question']        = $question;

        // 模版
        $this->load->view('question_external/edit_group', $data);
    }

    // 编辑题组
    public function edit_group($id = 0)
    {
        $id = intval($id);
        $id && $question = QuestionModel::get_question($id);
        if($question['related']==0)$question['related']='';
        if($question['exam_year']==0)$question['exam_year']='';
        if (empty($question))
        {
            message('题组不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是题组，做相应跳转
        if ($question['type'] > 0 || $question['parent_id'])
        {
            redirect('admin/question_external/edit/'.$id);
        }

        // 试题类型
        $relate_class = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }
        $data['relate_class'] = $relate_class;

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();

        // 模版
        $this->load->view('question_external/edit_group', $data);
    }

    // 题组题干添加操作
    public function update_group()
    {
        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';
        if ($act == 'edit')
        {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question)) {
                message('题组不存在');
                return;
            }

            $be_tested = QuestionModel::question_has_test_action($ques_id);
            if ($be_tested) {
            	message('该试题已经被考生考过 或者 正在被考， 无法操作');
            }
        }

        // 题目基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['type']         = 0;
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['score_factor'] = intval($this->input->post('score_factor'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));
        $question['test_way']    = intval($this->input->post('test_way'));

        if ($question['subject_id'] == 11)
        {
            $question['subject_id_str'] = ',' . implode(',', $this->input->post('subject_str')) . ',';
        }
        else
        {
            $question['subject_id_str'] = ',' . $question['subject_id'] . ',';
        }

        $extends = array(
            'difficulty'     => &$difficulty,
            'class_ids'      => &$class_ids,
            'skill_ids'      => &$skill_ids,
        	'knowledge_ids'  => &$knowledge_ids,
            'subject_types'  => &$subject_types,
        );

        $message = $this->_check_question($question, $extends, false, false);



        if ($act == 'add')
        {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add_group($question, $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $ques_result = QuestionModel::update_group($question, $extends);
        }
        $url =site_url('/admin/question_external/edit_group/'.$ques_result['ques_id']);

        if ($message) {
            message(implode('<br/>', $message), $url, null, 10);
            exit;
        }

        if ($ques_result['success'] == TRUE)
        {
            message('题组编辑成功。', 'admin/question_external/group/'.$ques_result['ques_id']);
            return;
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    /* -----------------题组子题-------------------------------- */
    public function add_group_question($parent_id = 0)
    {
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent']) {
            message('题组不存在');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;

        $question = array(
            'type'        => 1,
            'title'       => '',
            'answer'      => '',
            'parent_id'   => $parent_id,
            'knowledge'   => '',
            'method_tactic' => '',
            'subject_id' => $parent['subject_id'],
            'subject_id_str'=>$parent['subject_id_str'],
            'group_type' => 0,
            'is_primary' => $is_primary,
        );

        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        $data['act']       = 'add';
        $data['question']  = $question;
        $data['know_processes']  = array();

        // 模版
        $this->load->view('question_external/edit_group_question', $data);
    }

    public function edit_group_question($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是题组子题，做相应跳转
        if (!$question['parent_id'])
        {
            redirect('admin/question_external/edit/'.$id);
        }

        // 试题选项
        $query = $this->db->get_where('option', array('ques_id'=>$id));

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id) {
        	$tmp_knowledge_ids = explode(',', $question['knowledge']);
        	$tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
        	$tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
        	if ($tmp_knowledge_ids != '') {
	        	$result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
	        	foreach ($result as $item) {
	        		$know_processes[$item['knowledge_id']] = array(
	        				'kp' => $item['know_process'],
	        				'name' => C('know_process/'.$item['know_process']),
	        		);
	        	}
        	}
        }

        //试题学科 关联的方法策略数
        $parent = QuestionModel::get_question($question['parent_id']);
        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        $question['subject_id'] = $parent['subject_id'];
        $question['subject_id_str'] = $parent['subject_id_str'];

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;
        $question['is_primary'] = $is_primary;

        $data['act']      = 'edit';
        $data['options']  = $query->result_array();
        $data['question'] = $question;
        $data['know_processes'] = $know_processes;

        // 模版
        $this->load->view('question_external/edit_group_question', $data);
    }

    /**
     * 题组子题保存
     */
    public function update_group_question()
    {
        $act =  $this->input->post('act');
        $act = $act=='add' ? $act : 'edit';
        $parent_id = intval($this->input->post('parent_id'));
        $ques_id   = intval($this->input->post('ques_id'));

        if ($act == 'edit')
        {
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question) OR $old_question['parent_id'] != $parent_id) {
                message('题组试题不存在', 'javascript');
                return;
            }
        }
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在', 'javascript');
            return;
        }

        //判断题组题干是否还有关联技能，知识点
        if ($parent['skill'] != '' || $parent['knowledge'] != '') {
        	message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
        	return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
        }

        // 题目基本信息
        $question['type']    = intval($this->input->post('qtype'));
        $question['title']   = trim($this->input->post('title'));

        //检查知识点、认知过程
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');

        $extends = array(
        		'skill_ids'      => array(),
        		'knowledge_ids'  => $knowledge_ids,
        		'group_id'       => 0,
        		'relate_ques_id' => 0,
        		'relate_class'   => array(),
        		'method_tactic_ids' => $method_tactic_ids,
        		'know_process'   => $know_process,
        );

        if (!empty($parent) && $parent['subject_id'] == 3)
        {
            $group_type_ids = $this->input->post('group_type_id');
            $extends['group_type_ids'] = $group_type_ids;
            $extends['subject_id'] = $parent['subject_id'];
        }

        $message = $this->_check_group_question($question, $extends);
        if ($message)
        {
        	message(implode('<br>', $message), null, null, 10);
        }

        // 试题信息验证
        $message = array();

        if (empty($question['type'])) {
            $message[] = '请选择题型';
        } else {
            $question['type'] = $question['type']>3 ? 3 : $question['type'];
        }

        if ($question['type'] == 3)
        {
            $answer = trim($this->input->post('input_answer'));
            if (empty($answer))
            {
                $message[] = '请填写填空题答案';
            }
            else
            {
                $new_lines = array();
                $lines = explode("\n", $answer);
                foreach ($lines as $line)
                {
                    $line = trim($line);
                    if (strlen($line)>0)
                    {
                        $new_lines[] = $line;
                    }
                }
                $question['answer'] = implode("\n", $new_lines);
            }
            $options = array();
            $answer = '';
        }


        if ($question['type']  < 3)
        {
            $options        = $this->input->post('option');
            $answer         = $this->input->post('answer');
            $opt_result     = $this->_check_options($question['type'], $options, $answer);

        }

        if ($act == 'add')
        {
            $question['parent_id'] = $parent_id;
            $question['admin_id']  = $this->session->userdata('admin_id');
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }

        $url = site_url('/admin/question_external/edit_group_question/'.$ques_result['ques_id']);

        if ($message) {
            message(implode('<br>', $message), $url, null, 10);
            exit;
        }

        if ($opt_result['msg']) {
            message(implode('<br>', $opt_result['msg']), $url, null, 10);
            return;
        }

        if ($ques_result['success'] == TRUE) {
            message('试题编辑成功', 'admin/question_external/group/'.$parent_id);
        } else {
            message($ques_result['msg']);
        }
    }

    // 验证 题组子题 试题信息有效性
    private function _check_group_question(&$question, &$extends, $need_know_process = true)
    {
    	// 试题信息验证
    	$message = array();

    	//关联技能
        if (is_array($extends['skill_ids']) && count($extends['skill_ids']))
		{
			$extends['skill_ids'] = my_intval($extends['skill_ids']);
    		sort($extends['skill_ids'], SORT_NUMERIC);
    		$question['skill'] = ','.implode(',',$extends['skill_ids']).',';
    	} else {
    		$extends['skill_ids'] = array();
    		$question['skill'] = '';
		}

    	//方法策略, 选填项
		if (is_array($extends['method_tactic_ids']) && count($extends['method_tactic_ids']))
		{
			$extends['method_tactic_ids'] = my_intval($extends['method_tactic_ids']);
    		sort($extends['method_tactic_ids'], SORT_NUMERIC);
    		$question['method_tactic'] = ','.implode(',',$extends['method_tactic_ids']).',';
    	} else {
    		$extends['method_tactic_ids'] = array();
    		$question['method_tactic'] = '';
    	}

    	//如果信息提取方式不为空，知识点为非必须，若信息提取方式为空，则必须选择知识点
    	if (empty($extends['group_type_ids']))
    	{
    	    if (empty($extends['knowledge_ids']) || ! is_array($extends['knowledge_ids']))
    	    {
    	        $message[] = '请选择知识点';
    	    }
    	    else
    	    {
    	        //检查认知过程
    	        if ($need_know_process) {
    	            foreach ($extends['knowledge_ids'] as $knowledge_id) {
    	                if (!isset($extends['know_process'][$knowledge_id])
    	                   || !intval($extends['know_process'][$knowledge_id])) {
    	                    $message[] = '已勾选的知识点必须选择 认知过程';
    	                   // break;
    	                }
    	            }
    	        }

    	        $extends['knowledge_ids'] = my_intval($extends['knowledge_ids']);
    	        sort($extends['knowledge_ids'], SORT_NUMERIC);
    	        $question['knowledge'] = ','.implode(',',$extends['knowledge_ids']).',';
    	    }
    	}
    	else
    	{
    	    if (!empty($extends['knowledge_ids']) && is_array($extends['knowledge_ids']))
    	    {
    	        //检查认知过程
    	        if ($need_know_process) {
    	            foreach ($extends['knowledge_ids'] as $knowledge_id) {
    	                if (!isset($extends['know_process'][$knowledge_id])
    	                   || !intval($extends['know_process'][$knowledge_id]))
    	                {
    	                    $message[] = '已勾选的知识点必须选择 认知过程';
    	                   // break;
    	                }
    	            }
    	        }
    	        else
    	        {
    	            $extends['knowledge_ids'] = array();
    	            $question['knowledge'] = '';
    	        }

    	        $extends['knowledge_ids'] = my_intval($extends['knowledge_ids']);
    	        sort($extends['knowledge_ids'], SORT_NUMERIC);
    	        $question['knowledge'] = ','.implode(',',$extends['knowledge_ids']).',';
    	    }

    	    if (!is_array($extends['group_type_ids']))
    	    {
    	        $message[] = '请选择信息提取方式';
    	    }
    	    else
    	    {
    	        $extends['group_type_ids'] = my_intval($extends['group_type_ids']);
    	        sort($extends['group_type_ids'], SORT_NUMERIC);
    	        $question['group_type'] = ','.implode(',',$extends['group_type_ids']).',';
    	    }
    	}

    	if (isset($question['sort']))
    	{
    		if (empty($question['sort']))
    		{
    		    $message[] = '请输入序号';
    		}
    	}

    	return $message;
	}

    /* ----------------- 完型填空 -------------------------------- */
	/**
	 * 完形填空
	 */
	public function cloze($parent_id = 0)
	{
	    // 读取题干信息
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('完形填空不存在！');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

	    $grades   = C('grades');
	    $qtypes   = C('qtype');
	    $subjects = CpUserModel::get_allowed_subjects();
	    $subject_types    = C('subject_type');

	    // 类型、学科属性（文理科）、难易度
	    $class_list = ClassModel::get_class_list();
	    $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
	    $class_names = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));
	    foreach ($query->result_array() as $arr)
	    {
	        $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
	        $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
	    }
	    $parent['class_names'] = $class_names;

	    // 技能
	    $skill_ids = explode(',', trim($parent['skill'], ','));
	    $arr_tmp = array();
	    if ($skill_ids)
	    {
	        $query = $this->db->select('skill_name')->where_in('id', $skill_ids)->get('skill');
	        foreach ($query->result_array() as $row)
	        {
	            $arr_tmp[] = $row['skill_name'];
	        }
	    }
	    $parent['skill_name'] = implode(',', $arr_tmp);

	    //关联方法策略
	    $parent['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

	    // 知识点
	    $knowledge_ids = explode(',', trim($parent['knowledge'], ','));
	    $arr_tmp = array();
	    if ($knowledge_ids)
	    {
	    $query = $this->db->select('knowledge_name')->where_in('id', $knowledge_ids)->get('knowledge');
	    foreach ($query->result_array() as $row)
	    {
	    $arr_tmp[] = $row['knowledge_name'];
	    }
	    }
	    $parent['knowledge_name'] = implode(',', $arr_tmp);

	    $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
	    $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
	    $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

        $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);

        $parent['title'] = str_replace("\n", "<br/>", $parent['title']);
        $parent['title'] = str_replace("&nbsp;", " ", $parent['title']);
        //echo $parent['title'];
        //die;
        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2)
        {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related'])
        {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }

        $cpusers = CpUserModel::get_cpuser_list();

	    // 读取子题信息
	    $query = $this->db->select('ques_id,type,title,picture,answer,knowledge,method_tactic,admin_id,addtime')->order_by('sort','ASC')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
	    $list = $query->result_array();
        foreach ($list as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
	        $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
	        $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
	        $row['options'] = $query->result_array();

	        // 知识点
	        $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
	        $knowledge_arr = array();
            foreach($query->result_array() as $arr)
	        {
            	$knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
	        }

	        $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';

	            //方法策略
	            $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $row['ques_id'])->get();
	            $method_tactic_arr = array();
	            foreach($query->result_array() as $arr)
                {
	               $method_tactic_arr[] = $arr['name'];
	            }

	            $method_tactic_arr = array_filter($method_tactic_arr);
	            $row['method_tactic_name'] = implode(',', $method_tactic_arr);
	     }

        // 读取子题信息(已删除)
        $query = $this->db->select('ques_id,type,title,picture,answer,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
        $list2 = $query->result_array();
        foreach ($list2 as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();
        }

        $data['qtypes'] = $qtypes;
        $data['grades'] = $grades;
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['list2']  = $list2;
        $data['priv_manage'] = true;
        $data['priv_delete'] = true;

	    // 模版
        $this->load->view('question_external/cloze', $data);
	}

	/**
	 * 添加完形填空
	 */
	public function add_cloze($relate_ques_id = 0)
	{
	    $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_ques_id = intval($relate_ques_id);
	    $relate_group   = intval($this->input->get('group'));
	    $copy_id        = intval($this->input->get('copy'));

	    $be_tested = QuestionModel::question_has_test_action($copy_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_class   = array();

	    if ($copy_id)
	    {
            $this->examine_permission($copy_id);
	        $question = QuestionModel::get_question($copy_id);
	        // 试题类型
	        $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
	        foreach ($query->result_array() as $row)
	        {
	            $relate_class[$row['grade_id']][$row['class_id']] = $row;
	        }
	    }

	    if (empty($question))
	    {
	        $question = array(
	                'class_id'    => '',
	                'skill'       => '',
	                'knowledge'   => '',
	                'subject_id'  => '',
	                'start_grade' => '',
	                'end_grade'   => '',
	                'title'       => '',
	                'score_factor' => '',
                    'is_original' => '',
                    'exam_year' => '',
                    'remark' => '',
                    'related' => '',
	        );
	    }

	    $data['act']             = 'add';
	    $data['relate_ques_id']  = $relate_ques_id;
	    $data['relate_group']    = $relate_group;
	    $data['grades']          = C('grades');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();
	    $data['relate_class']    = $relate_class;
	    $data['question']        = $question;

	    // 模版
	    $this->load->view('question_external/edit_cloze', $data);
	}

	// 编辑完形填空
	public function edit_cloze($id = 0)
	{
	    $id = intval($id);
	    $id && $question = QuestionModel::get_question($id);
	    if($question['related']==0)$question['related']='';
	    if($question['exam_year']==0)$question['exam_year']='';
	    if (empty($question))
	    {
	        message('完形填空不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是完形填空，做相应跳转
	    if ($question['type'] != 4 || $question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

	    // 试题类型
	    $relate_class = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
	    foreach ($query->result_array() as $row)
	    {
	        $relate_class[$row['grade_id']][$row['class_id']] = $row;
	    }
	    $data['relate_class'] = $relate_class;

	    $data['act']             = 'edit';
	    $data['question']        = $question;
	    $data['grades']          = C('grades');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();

	    // 模版
	    $this->load->view('question_external/edit_cloze', $data);
	}

	/**
	 * 完形填空题干添加操作
	 */
	public function update_colze()
	{
	    $act = $this->input->post('act');
	    $act = $act == 'add' ? $act : 'edit';
	    if ($act == 'edit')
	    {
	        $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);

	        if (empty($old_question)) {
	            message('完形填空题不存在','javascript');
	            return;
	        }

	        $be_tested = QuestionModel::question_has_test_action($ques_id);
	        if ($be_tested) {
	            message('该试题已经被考生考过 或者 正在被考， 无法操作','javascript');
	        }
	    }

	    // 完形填空基本信息
	    $class_ids           = $this->input->post('class_id');
	    $skill_ids           = $this->input->post('skill_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $difficulty          = $this->input->post('difficulty');
	    $subject_types       = $this->input->post('subject_type');
	    $question['type']         = 4;
        $question['test_way']     = array_sum($this->input->post('test_way'));
	    $question['subject_id']   = intval($this->input->post('subject_id'));
	    $question['start_grade']  = intval($this->input->post('start_grade'));
	    $question['end_grade']    = intval($this->input->post('end_grade'));
	    $question['title']        = trim($this->input->post('title'));
	    $question['score_factor'] = intval($this->input->post('score_factor'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));
	    $extends = array(
	            'difficulty'     => &$difficulty,
	            'class_ids'      => &$class_ids,
	            'skill_ids'      => &$skill_ids,
	            'knowledge_ids'  => &$knowledge_ids,
	            'subject_types'  => &$subject_types,
	    );

	    $message = $this->_check_question($question, $extends, false, false);

	    if ($act == 'add')
	    {
	        $extends['group_id']        = intval($this->input->post('relate_group'));
	        $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
	        $ques_result = QuestionModel::add_group($question, $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $ques_result = QuestionModel::update_group($question, $extends);
	    }
	    $url = site_url('/admin/question_external/edit_cloze/'.$ques_result['ques_id']);

	    if ($message) {
	        message(implode('<br/>', $message), $url, null, 10);
	        exit;
	    }

	    if ($ques_result['success'] == TRUE) {
	        message('完形填空编辑成功。', 'admin/question_external/cloze/'.$ques_result['ques_id']);
	        return;
	    } else {
	        message($ques_result['msg']);
	        return;
	    }
	}

	// 添加完形填空子题
	public function add_cloze_question($parent_id = 0)
	{
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);

	    if (empty($parent) OR ! $parent['is_parent']) {
	        message('完形填空题不存在');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $question = array(
	            'type'        => 1,
	            'p_type'      => 4,
	            'title'       => '',
	            'answer'      => '',
	            'parent_id'   => $parent_id,
	            'knowledge'   => '',
	            'method_tactic' => '',
	            'subject_id' => $parent['subject_id'],
	            'sort' => $parent['children_num'] + 1,
	    );

	    $data['act'] = 'add';
	    $data['question'] = $question;
	    $data['know_processes'] = array();

	    // 模版
	    $this->load->view('question_external/edit_cloze_question', $data);
	}

	public function edit_cloze_question($id = 0)
	{
	    $id = intval($id);
        $this->examine_permission($id);
	    $id && $question = QuestionModel::get_question($id);
	    if (empty($question))
	    {
	        message('试题不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是完形填空子题，做相应跳转
	    if (!$question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

	    // 试题选项
	    $query = $this->db->get_where('option', array('ques_id'=>$id));

	    //获取关联知识点对应的认知过程
	    $know_processes = array();
	    if ($id) {
	        $tmp_knowledge_ids = explode(',', $question['knowledge']);
	        $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
	        $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
	        if ($tmp_knowledge_ids != '') {
	            $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
	            foreach ($result as $item) {
	                $know_processes[$item['knowledge_id']] = array(
	                        'kp' => $item['know_process'],
	                        'name' => C('know_process/'.$item['know_process']),
	                );
	            }
	        }
	    }

	    //试题学科 关联的方法策略数
	    $parent = QuestionModel::get_question($question['parent_id']);
	    $question['subject_id'] = $parent['subject_id'];
	    $question['p_type'] = 4;

	    $data['act']      = 'edit';
	    $data['options']  = $query->result_array();
	    $data['question'] = $question;
	    $data['know_processes'] = $know_processes;

	    // 模版
	    $this->load->view('question_external/edit_cloze_question', $data);
	}

	/**
	 * 完形子题保存
	 */
	public function update_cloze_question()
	{
	    $act =  $this->input->post('act');
	    $act = $act=='add' ? $act : 'edit';
	    $parent_id = intval($this->input->post('parent_id'));
	    $ques_id   = intval($this->input->post('ques_id'));

	    if ($act == 'edit') {
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);
	        if (empty($old_question) OR $old_question['parent_id'] != $parent_id) {
	            message('完形填空试题不存在', 'javascript');
	            return;
	        }
	    }
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('完形填空不存在', 'javascript');
	        return;
	    }

	    //判断题组题干是否还有关联技能，知识点
	    if ($parent['skill'] != '' || $parent['knowledge'] != '') {
	        message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
	    }

	    // 题目基本信息
	    $question['type']    = intval($this->input->post('qtype'));
	    $question['title']   = trim($this->input->post('title'));
	    $question['sort']    = intval($this->input->post('sort'));

	    //检查知识点、认知过程
	    $method_tactic_ids   = $this->input->post('method_tactic_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $know_process        = $this->input->post('know_process');

	    $extends = array(
	            'skill_ids'      => array(),
	            'knowledge_ids'  => $knowledge_ids,
	            'group_id'       => 0,
	            'relate_ques_id' => 0,
	            'relate_class'   => array(),
	            'method_tactic_ids' => $method_tactic_ids,
	            'know_process'   => $know_process,
	    );

	    $message = $this->_check_group_question($question, $extends);

	    // 试题信息验证
	    $message1 = array();

	    if (empty($question['type'])) {
	        $message1[] = '请选择题型';
	    } else {
	        $question['type'] = $question['type']>3 ? 3 : $question['type'];
	    }

	    if ($question['type'] == 3)
	    {
	        $answer = trim($this->input->post('input_answer'));
	        if (empty($answer))
	        {
	            $message1[] = '请填写填空题答案';
	        }
	        else
	        {
	            $new_lines = array();
	            $lines = explode("\n", $answer);
	            foreach ($lines as $line)
	            {
	                $line = trim($line);
	                if (strlen($line)>0)
	                {
	                    $new_lines[] = $line;
	                }
	            }
	            $question['answer'] = implode("\n", $new_lines);
	        }
	        $options = array();
	        $answer = '';
	    }



	    if ($question['type']  < 3)
	    {
	        $options        = $this->input->post('option');
	        $answer         = $this->input->post('answer');
	        $opt_result     = $this->_check_options($question['type'], $options, $answer);

	    }

	    if ($act == 'add')
	    {
	        $question['parent_id'] = $parent_id;
	        $question['admin_id']  = $this->session->userdata('admin_id');
	        $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $extends['old_opt_ids'] = $this->input->post('old_opt_id');
	        $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }

	    $url = site_url('/amin/question_external/edit_cloze_question/'.$ques_result['ques_id']);
	    if ($message)
	    {
	        message(implode('<br>', $message), $url, null, 10);
	        return;
	    }

	    if ($message1) {
	        message(implode('<br>', $message1), null, null, 10);
	        exit;
	    }

	    if ($opt_result['msg'])
	    {
	        message(implode('<br>', $opt_result['msg']), null, null, 10);
	        return;
	    }

	    if ($ques_result['success'] == TRUE)
	    {
	        //更新排序
	        $where_data = array(
	                'parent_id'=>$parent_id,
	                'is_delete'=>0
	        );
	        $child_list = $this->db->select()->order_by('sort ASC')->get_where('question',$where_data)->result_array();
	        if (count($child_list) > 1)
	        {
	        	$ques_id = $ques_result['ques_id'];
	        	$sort = 0;
	        	$now_sort = $question['sort'];
	        	foreach ($child_list as $ques)
	        	{
	        	    if ($ques['ques_id'] == $ques_id)
	        	    {
	        	    	continue;
	        	    }

	        	    $sort++;
	        	    ($now_sort == $sort) && $sort++;

	        	    $this->db->update('question',array('sort'=>$sort), 'ques_id = ' . $ques['ques_id']);
	        	}
	        }

	        message('试题编辑成功', 'admin/question_external/cloze/'.$parent_id);
	    }
	    else
	    {
	        message($ques_result['msg']);
	    }
	}

    /* ----------------- 匹配题 -------------------------------- */
	/**
	 * 匹配题
	 */
	public function match($parent_id = 0)
	{
	    // 读取题干信息
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('匹配题不存在！');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

	    $grades   = C('grades');
	    $qtypes   = C('qtype');
	    $subjects = CpUserModel::get_allowed_subjects();
	    $subject_types    = C('subject_type');
	    $group_type = GroupTypeModel::get_group_type_list(0, $parent['subject_id'], false, true);

	    // 类型、学科属性（文理科）、难易度
	    $class_list = ClassModel::get_class_list();
	    $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
	    $class_names = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));
	    foreach ($query->result_array() as $arr)
	    {
	        $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
	        $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
	    }
	    $parent['class_names'] = $class_names;

	    $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
	    $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
	    $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

	    $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
	    $parent['title'] = str_replace("\n", '<br/>', $parent['title']);
	    //$parent['title'] = str_replace(" ", '&nbsp;', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2)
        {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related'])
        {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }

	    $cpusers = CpUserModel::get_cpuser_list();

	    // 读取子题信息
	    $query = $this->db->select()->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
	    $list = $query->result_array();
	    foreach ($list as &$row)
	    {
	        $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
	        $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
	        $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
	        $row['options'] = $query->result_array();

            /** 知识点 */
            $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
            $knowledge_arr = array();
            foreach($query->result_array() as $arr)
            {
                $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
            }
            $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : false;

            /** 信息提取方式 */
            if ($row['group_type'])
            {
                $query = $this->db->select('gt.group_type_name')->from('relate_group_type rgt')->join('group_type gt','rgt.group_type_id=gt.id','left')->where('rgt.ques_id', $row['ques_id'])->get();
                $group_type_arr = array();
                foreach($query->result_array() as $arr)
                {
                    $group_type_arr[] = $arr['group_type_name'];
                }
                $group_type_arr = array_filter($group_type_arr);
                $row['group_type_name'] = implode(',', $group_type_arr);
            }
	    }

	    // 读取子题信息(已删除)
	    $query = $this->db->select()->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
	    $list2 = $query->result_array();
	    foreach ($list2 as &$row)
	    {
	        $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
	        $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);

	        /** 知识点 */
	        $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
	        $knowledge_arr = array();
	        foreach($query->result_array() as $arr)
	        {
	            $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
	        }
	        $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : false;

	        /** 信息提取方式 */
	        if ($row['group_type'])
	        {
	            $query = $this->db->select('gt.group_type_name')->from('relate_group_type rgt')->join('group_type gt','rgt.group_type_id=gt.id','left')->where('rgt.ques_id', $row['ques_id'])->get();
	            $group_type_arr = array();
	            foreach($query->result_array() as $arr)
	            {
	                $group_type_arr[] = $arr['group_type_name'];
	            }
	            $group_type_arr = array_filter($group_type_arr);
	            $row['group_type_name'] = implode(',', $group_type_arr);
	        }
	    }

	    $data['qtypes'] = $qtypes;
	    $data['grades'] = $grades;
	    $data['q_tags'] = C('q_tags/5');
	    $data['parent'] = $parent;
	    $data['list']   = $list;
	    $data['list2']  = $list2;
	    $data['group_type'] = $group_type;
	    $data['priv_manage'] = true;
	    $data['priv_delete'] = true;

	    // 模版
	    $this->load->view('question_external/match', $data);
	}

	/**
	 * 添加匹配题
	 */
	public function add_match($relate_ques_id = 0)
	{
	    $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_ques_id = intval($relate_ques_id);
	    $relate_group   = intval($this->input->get('group'));
	    $copy_id        = intval($this->input->get('copy'));

	    $be_tested = QuestionModel::question_has_test_action($copy_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_class   = array();

	    if ($copy_id) {
            $this->examine_permission($copy_id);
	        $question = QuestionModel::get_question($copy_id);
	        // 试题类型
	        $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
	        foreach ($query->result_array() as $row)
	        {
	            $relate_class[$row['grade_id']][$row['class_id']] = $row;
	        }
	    }

	    if (empty($question))
	    {
	        $question = array(
	                'class_id'    => '',
	                'subject_id'  => '',
	                'start_grade' => '',
	                'end_grade'   => '',
	                'title'       => '',
	                'score_factor' => '',
	                'tags' => '',
                    'is_original' => '',
                    'exam_year' => '',
                    'remark' => '',
                    'related' => '',
	        );
	    }

	    $data['act']             = 'add';
	    $data['relate_ques_id']  = $relate_ques_id;
	    $data['relate_group']    = $relate_group;
	    $data['grades']          = C('grades');
	    $data['q_tags']          = C('q_tags/5');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();
	    $data['relate_class']    = $relate_class;
	    $data['question']        = $question;

	    // 模版
	    $this->load->view('question_external/edit_match', $data);
	}

	// 编辑匹配题
	public function edit_match($id = 0)
	{
	    $id = intval($id);
        $this->examine_permission($id);
	    $id && $question = QuestionModel::get_question($id);

	    if($question['related']==0)$question['related']='';
	    if($question['exam_year']==0)$question['exam_year']='';
	    if (empty($question))
	    {
	        message('完形填空不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是完形填空，做相应跳转
	    if ($question['type'] != 5 || $question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

	    // 试题类型
	    $relate_class = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
	    foreach ($query->result_array() as $row)
	    {
	        $relate_class[$row['grade_id']][$row['class_id']] = $row;
	    }
	    $data['relate_class'] = $relate_class;

	    $data['act']             = 'edit';
	    $data['question']        = $question;
	    $data['grades']          = C('grades');
	    $data['q_tags']          = C('q_tags/5');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();

	    // 模版
	    $this->load->view('question_external/edit_match', $data);
	}

	/**
	 * 匹配题题干添加操作
	 */
	public function update_match()
	{
	    $act = $this->input->post('act');
	    $act = $act == 'add' ? $act : 'edit';

	    if ($act == 'edit') {
	        $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);

	        if (empty($old_question)) {
	            message('匹配题不存在','javascript');
	            return;
	        }

	        $be_tested = QuestionModel::question_has_test_action($ques_id);

	        if ($be_tested) {
	            message('该试题已经被考生考过 或者 正在被考， 无法操作','javascript');
	        }
	    }

	    // 匹配题基本信息
	    $class_ids           = $this->input->post('class_id');
	    $skill_ids           = $this->input->post('skill_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $difficulty          = $this->input->post('difficulty');
	    $subject_types       = $this->input->post('subject_type');
	    $question['type']         = 5;
        $question['test_way']     = array_sum($this->input->post('test_way'));
	    $question['subject_id']   = intval($this->input->post('subject_id'));
	    $question['start_grade']  = intval($this->input->post('start_grade'));
	    $question['end_grade']    = intval($this->input->post('end_grade'));
	    $question['tags']         = intval($this->input->post('q_tag'));
	    $question['title']        = trim($this->input->post('title'));
	    $question['options']      = trim($this->input->post('options'));
	    //$question['score_factor'] = intval($this->input->post('score_factor'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));

	    $extends = array(
	            'difficulty'     => &$difficulty,
	            'class_ids'      => &$class_ids,
	            'skill_ids'      => &$skill_ids,
	            'knowledge_ids'  => &$knowledge_ids,
	            'subject_types'  => &$subject_types,
	    );

	    $message = $this->_check_question($question, $extends, false, false);


	    if ($act == 'add')
	    {
	        $extends['group_id']        = intval($this->input->post('relate_group'));
	        $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
	        $ques_result = QuestionModel::add_group($question, $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $ques_result = QuestionModel::update_group($question, $extends);
	    }
	    $url = site_url('/admin/question_external/edit_match/'.$ques_result['ques_id']);

	    if ($message) {
	        message(implode('<br/>', $message), $url, null, 10);
	        exit;
	    }

	    if ($ques_result['success'] == TRUE) {
	        message('匹配题编辑成功。', 'admin/question_external/match/'.$ques_result['ques_id']);
	        return;
	    } else {
	        message($ques_result['msg']);
	        return;
	    }
	}

	// 添加匹配题子题
	public function add_match_question($parent_id = 0)
	{
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);

	    if (empty($parent) OR ! $parent['is_parent']) {
	        message('匹配题不存在');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;

	    $question = array(
	            'type'        => 3,
	            'p_type'      => 5,
	            'title'       => '',
	            'answer'      => '',
	            'parent_id'   => $parent_id,
	            'subject_id' => $parent['subject_id'],
	            'group_type' => '',
                'knowledge'   => '',
                'is_primary' => $is_primary,
	    );

	    $data['act']       = 'add';
	    $data['question']  = $question;
	    $data['know_processes']  = array();

	    // 模版
	    $this->load->view('question_external/edit_match_question', $data);
	}

	public function edit_match_question($id = 0)
	{
	    $id = intval($id);
        $this->examine_permission($id);
	    $id && $question = QuestionModel::get_question($id);

	    if (empty($question))
	    {
	        message('试题不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是匹配题子题，做相应跳转
	    if ($question['type'] != 5 || !$question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id) {
            $tmp_knowledge_ids = explode(',', $question['knowledge']);
            $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
            $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            if ($tmp_knowledge_ids != '') {
                $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
                foreach ($result as $item) {
                    $know_processes[$item['knowledge_id']] = array(
                            'kp' => $item['know_process'],
                            'name' => C('know_process/'.$item['know_process']),
                    );
                }
            }
        }

	    //试题学科 关联的方法策略数
	    $parent = QuestionModel::get_question($question['parent_id']);
	    $question['subject_id'] = $parent['subject_id'];

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;
        $question['is_primary'] = $is_primary;
        $data['know_processes'] = $know_processes;

	    $data['act']      = 'edit';
	    $data['question'] = $question;

	    // 模版
	    $this->load->view('question_external/edit_match_question', $data);
	}

	/**
	 * 匹配题子题保存
	 */
	public function update_match_question()
	{
	    $act =  $this->input->post('act');
	    $act = $act=='add' ? $act : 'edit';
	    $parent_id = intval($this->input->post('parent_id'));
	    $ques_id   = intval($this->input->post('ques_id'));

	    if ($act == 'edit') {
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);

	        if (empty($old_question) OR $old_question['parent_id'] != $parent_id) {
	            message('匹配题不存在', 'javascript');
	            return;
	        }
	    }
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) || !$parent['is_parent'])
	    {
	        message('匹配题不存在', 'javascript');
	        return;
	    }

	    //判断题组题干是否还有关联技能，知识点
	    if ($parent['skill'] != '' || $parent['knowledge'] != '') {
	        message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
	    }

	    // 题目基本信息
	    $question['type']    = intval($this->input->post('qtype'));
	    $question['title']   = '';

	    //检查知识点、认知过程
	    $method_tactic_ids   = $this->input->post('method_tactic_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $know_process        = $this->input->post('know_process');

	    $extends = array(
	            'skill_ids'      => array(),
	            'knowledge_ids'  => $knowledge_ids,
	            'group_id'       => 0,
	            'relate_ques_id' => 0,
	            'relate_class'   => array(),
	            'method_tactic_ids' => $method_tactic_ids,
	            'know_process'   => $know_process,
	    );

	    if (!empty($parent) && $parent['subject_id'] == 3)
	    {
	        $group_type_ids = $this->input->post('group_type_id');
	        $extends['group_type_ids'] = $group_type_ids;
	        $extends['subject_id'] = $parent['subject_id'];
	    }

	    $message = $this->_check_group_question($question, $extends, false);
	    if ($message)
	    {
	        message(implode('<br>', $message), null, null, 10);
	    }

	    $question['type'] = 5;

	    // 试题信息验证
	    $message = array();
        $answer = trim($this->input->post('input_answer'));
        if (empty($answer))
        {
            $message[] = '请填写匹配题答案';
        }
        else
        {
            $new_lines = array();
            $lines = explode("\n", $answer);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if (strlen($line)>0)
                {
                    $new_lines[] = $line;
                }
            }
            $question['answer'] = implode("\n", $new_lines);
        }


	    if ($act == 'add')
	    {
	        $question['parent_id'] = $parent_id;
	        $question['admin_id']  = $this->session->userdata('admin_id');
	        $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $extends['old_opt_ids'] = $this->input->post('old_opt_id');
	        $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }
        $url = site_url('/admin/question_external/edit_match_question/'.$ques_result['ques_id']);
	    if ($message)
	    {
	        message(implode('<br>', $message), $url, null, 10);
	        return;
	    }
	    if ($ques_result['success'] == TRUE)
	    {
	        message('试题编辑成功', 'admin/question_external/match/'.$parent_id);
	    }
	    else
	    {
	        message($ques_result['msg']);
	    }
	}

    /* ----------------- 选词填空 -------------------------------- */
	/**
	 * 选词填空
	 */
	public function diction($parent_id = 0)
	{
	    // 读取题干信息
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('选词填空题不存在！');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

	    $grades   = C('grades');
	    $qtypes   = C('qtype');
	    $subjects = CpUserModel::get_allowed_subjects();
	    $subject_types    = C('subject_type');

	    // 类型、学科属性（文理科）、难易度
	    $class_list = ClassModel::get_class_list();
	    $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
	    $class_names = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));
	    foreach ($query->result_array() as $arr)
	    {
	        $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
	        $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name'])
	                                            ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']'
	                                             : '';
	    }
	    $parent['class_names'] = $class_names;


	    // 知识点
	    $knowledge_ids = explode(',', trim($parent['knowledge'], ','));
	    $arr_tmp = array();
	    if ($knowledge_ids)
	    {
	        $query = $this->db->select('knowledge_name')
	                          ->where_in('id', $knowledge_ids)
	                          ->get('knowledge');

	        foreach ($query->result_array() as $row)
	        {
	            $arr_tmp[] = $row['knowledge_name'];
	        }
	    }
	    $parent['knowledge_name'] = implode(',', $arr_tmp);

	    $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
	    $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
	    $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

	    $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
	    $parent['title'] = str_replace("\n", '<br/>', $parent['title']);
	    //$parent['title'] = str_replace(" ", '&nbsp;', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2)
        {
           /*
            $relateds = $this->db->query("select ques_id from {pre}question where related={$id}")->result_array();
            */

            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();
            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related'])
        {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }

	    $cpusers = CpUserModel::get_cpuser_list();

	    // 读取子题信息
	    $query = $this->db->select('ques_id,type,title,picture,answer,knowledge,method_tactic,admin_id,addtime')
	                  ->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
	    $list = $query->result_array();
	    foreach ($list as &$row)
	    {
	        $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
	        $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);

	        // 知识点
	        $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')
	                          ->from('relate_knowledge rk')
	                          ->join('knowledge k','rk.knowledge_id=k.id','left')
	                          ->where_in('rk.ques_id', $row['ques_id'])
	                          ->get();

	        $knowledge_arr = array();
	        foreach($query->result_array() as $arr)
	        {
	            $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
	        }

	        $row['knowledge_name'] = count($knowledge_arr)
	                                ? implode(',', $knowledge_arr)
	                                : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';
	    }

	    // 读取子题信息(已删除)
	    $query = $this->db->select('ques_id,type,title,picture,answer,admin_id,addtime')
	                       ->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
	    $list2 = $query->result_array();
	    foreach ($list2 as &$row)
	    {
	        $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
	        $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);

	        // 知识点
	        $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')
	                          ->from('relate_knowledge rk')
	                          ->join('knowledge k','rk.knowledge_id=k.id','left')
	                          ->where_in('rk.ques_id', $row['ques_id'])
	                          ->get();

	        $knowledge_arr = array();
	        foreach($query->result_array() as $arr)
	        {
	            $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
	        }

	        $row['knowledge_name'] = count($knowledge_arr)
	                                 ? implode(',', $knowledge_arr)
	                                 : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';
	    }

	    $data['qtypes'] = $qtypes;
	    $data['grades'] = $grades;
	    $data['q_tags']          = C('q_tags/' . $parent['type']);
	    $data['parent'] = $parent;
	    $data['list']   = $list;
	    $data['list2']  = $list2;

	    // 模版
	    $this->load->view('question_external/diction', $data);
	}

	/**
	 * 添加选词填空题
	 */
	public function add_diction($relate_ques_id = 0)
	{
	    $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_ques_id = intval($relate_ques_id);
	    $relate_group   = intval($this->input->get('group'));
	    $copy_id        = intval($this->input->get('copy'));

	    $be_tested = QuestionModel::question_has_test_action($copy_id);

	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $relate_class   = array();

	    if ($copy_id) {
            $this->examine_permission($copy_id);
	        $question = QuestionModel::get_question($copy_id);
	        // 试题类型
	        $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
	        foreach ($query->result_array() as $row)
	        {
	            $relate_class[$row['grade_id']][$row['class_id']] = $row;
	        }
	    }

	    if (empty($question))
	    {
	        $question = array(
	                'type'        => 3,
	                'p_type'      => 6,
	                'class_id'    => '',
	                'skill'       => '',
	                'knowledge'   => '',
	                'subject_id'  => '',
	                'start_grade' => '',
	                'end_grade'   => '',
	                'title'       => '',
	                'tags'        => '',
                    'is_original' => '',
                    'exam_year' => '',
                    'remark' => '',
                    'related' => '',
	        );
	    }

	    $data['act']             = 'add';
	    $data['relate_ques_id']  = $relate_ques_id;
	    $data['relate_group']    = $relate_group;
	    $data['grades']          = C('grades');
	    $data['q_tags']          = C('q_tags/6');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();
	    $data['relate_class']    = $relate_class;
	    $data['question']        = $question;

	    // 模版
	    $this->load->view('question_external/edit_diction', $data);
	}

	// 编辑选词填空题
	public function edit_diction($id = 0)
	{
	    $id = intval($id);
        $this->examine_permission($id);
	    $id && $question = QuestionModel::get_question($id);
	    if($question['related']==0)$question['related']='';
	    if($question['exam_year']==0)$question['exam_year']='';
	    if (empty($question))
	    {
	        message('选词填空题不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是选词填空，做相应跳转
	    if ($question['type'] != 6 || $question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

	    // 试题类型
	    $relate_class = array();
	    $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
	    foreach ($query->result_array() as $row)
	    {
	        $relate_class[$row['grade_id']][$row['class_id']] = $row;
	    }
	    $data['relate_class'] = $relate_class;

	    $data['act']             = 'edit';
	    $data['question']        = $question;
	    $data['grades']          = C('grades');
	    $data['q_tags']          = C('q_tags/6');
	    $data['subjects']        = CpUserModel::get_allowed_subjects();
	    $data['subject_types']   = C('subject_type');
	    $data['all_grade_class'] = ClassModel::all_grade_class();

	    // 模版
	    $this->load->view('question_external/edit_diction', $data);
	}

	/**
	 * 选词填空题干添加操作
	 */
	public function update_diction()
	{
	    $act = $this->input->post('act');
	    $act = $act == 'add' ? $act : 'edit';

	    if ($act == 'edit') {
	        $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);

	        if (empty($old_question)) {
	            message('选词填空题不存在','javascript');
	            return;
	        }

	        $be_tested = QuestionModel::question_has_test_action($ques_id);

	        if ($be_tested) {
	            message('该试题已经被考生考过 或者 正在被考， 无法操作','javascript');
	        }
	    }

	    // 选词填空基本信息
	    $class_ids           = $this->input->post('class_id');
	    $skill_ids           = $this->input->post('skill_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $difficulty          = $this->input->post('difficulty');
	    $subject_types       = $this->input->post('subject_type');
	    $question['type']         = 6;
        $question['test_way']     = array_sum($this->input->post('test_way'));
	    $question['subject_id']   = intval($this->input->post('subject_id'));
	    $question['start_grade']  = intval($this->input->post('start_grade'));
	    $question['end_grade']    = intval($this->input->post('end_grade'));
	    $question['tags']         = intval($this->input->post('q_tag'));
	    $question['title']        = trim($this->input->post('title'));
	    $question['options']      = trim($this->input->post('options'));
	    //$question['score_factor'] = intval($this->input->post('score_factor'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));

	    $extends = array(
	            'difficulty'     => &$difficulty,
	            'class_ids'      => &$class_ids,
	            'skill_ids'      => &$skill_ids,
	            'knowledge_ids'  => &$knowledge_ids,
	            'subject_types'  => &$subject_types,
	    );

	    $message = $this->_check_question($question, $extends, false, false);



	    if ($act == 'add')
	    {
	        $extends['group_id']        = intval($this->input->post('relate_group'));
	        $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
	        $ques_result = QuestionModel::add_group($question, $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $ques_result = QuestionModel::update_group($question, $extends);
	    }
	    $url = site_url('/admin/question_external/edit_diction/'.$ques_result['ques_id']);
	    if ($message)
	    {
	        message(implode('<br/>', $message), $url, null, 10);
	        return;
	    }

	    if ($ques_result['success'] == TRUE)
	    {
	        message('选词填空编辑成功。', 'admin/question_external/diction/'.$ques_result['ques_id']);
	        return;
	    }
	    else
	    {
	        message($ques_result['msg']);
	        return;
	    }
	}

	/**
	 * 添加选词填空子题
	 */
	public function add_diction_question($parent_id = 0)
	{
	    $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('选词填空题不存在');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    $question = array(
	            'type'        => 6,
	            'title'       => '',
	            'answer'      => '',
	            'parent_id'   => $parent_id,
	            'knowledge'   => '',
	            'method_tactic' => '',
	            'subject_id' => $parent['subject_id'],
	    );

	    $data['act']       = 'add';
	    $data['question']  = $question;
	    $data['know_processes']  = array();

	    // 模版
	    $this->load->view('question_external/edit_diction_question', $data);
	}

	/**
	 * 编辑选词填空子题
	 */
	public function edit_diction_question($id = 0)
	{
	    $id = intval($id);
        $this->examine_permission($id);
	    $id && $question = QuestionModel::get_question($id);
	    if (empty($question))
	    {
	        message('试题不存在');
	        return;
	    }

	    //判断该试题已经被考试过 或 正在被考
	    $be_tested = QuestionModel::question_has_test_action($id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作');
	    }

	    // 不是选词填空子题，做相应跳转
	    if ($question['type'] != 6 || !$question['parent_id'])
	    {
	        redirect('admin/question_external/edit/'.$id);
	    }

	    //获取关联知识点对应的认知过程
	    $know_processes = array();
	    if ($id) {
	        $tmp_knowledge_ids = explode(',', $question['knowledge']);
	        $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
	        $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
	        if ($tmp_knowledge_ids != '') {
	            $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
	            foreach ($result as $item) {
	                $know_processes[$item['knowledge_id']] = array(
	                        'kp' => $item['know_process'],
	                        'name' => C('know_process/'.$item['know_process']),
	                );
	            }
	        }
	    }

	    //试题学科 关联的方法策略数
	    $parent = QuestionModel::get_question($question['parent_id']);
	    $question['subject_id'] = $parent['subject_id'];

	    $data['act']      = 'edit';
	    $data['question'] = $question;
	    $data['know_processes'] = $know_processes;

	    // 模版
	    $this->load->view('question_external/edit_diction_question', $data);
	}

	/**
	 * 选词填空子题保存
	 */
	public function update_diction_question()
	{
	    $act =  $this->input->post('act');
	    $act = $act=='add' ? $act : 'edit';
	    $parent_id = intval($this->input->post('parent_id'));
	    $ques_id   = intval($this->input->post('ques_id'));

	    if ($act == 'edit') {
            $this->examine_permission($ques_id);
	        $ques_id && $old_question = QuestionModel::get_question($ques_id);

	        if (empty($old_question) OR $old_question['parent_id'] != $parent_id) {
	            message('选词填空试题不存在', 'javascript');
	            return;
	        }
	    }
	    $parent_id && $parent = QuestionModel::get_question($parent_id);
	    if (empty($parent) OR ! $parent['is_parent'])
	    {
	        message('选词填空题不存在', 'javascript');
	        return;
	    }

	    //判断题组题干是否还有关联技能，知识点
	    if ($parent['skill'] != '' || $parent['knowledge'] != '') {
	        message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
	        return;
	    }

	    $be_tested = QuestionModel::question_has_test_action($parent_id);
	    if ($be_tested) {
	        message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
	    }

	    // 题目基本信息
	    $question['type']    = 6;
	    $question['title']   = trim($this->input->post('title'));

	    //检查知识点、认知过程
	    $method_tactic_ids   = $this->input->post('method_tactic_id');
	    $knowledge_ids       = $this->input->post('knowledge_id');
	    $know_process        = $this->input->post('know_process');

	    $extends = array(
	            'skill_ids'      => array(),
	            'knowledge_ids'  => $knowledge_ids,
	            'group_id'       => 0,
	            'relate_ques_id' => 0,
	            'relate_class'   => array(),
	            'method_tactic_ids' => $method_tactic_ids,
	            'know_process'   => $know_process,
	    );

	    $message = $this->_check_group_question($question, $extends);


	    // 试题信息验证
	    $message1 = array();

	    $answer = trim($this->input->post('input_answer'));
        if (empty($answer))
        {
            $message1[] = '请填写选词填空题答案';
        }
        else
        {
            $new_lines = array();
            $lines = explode("\n", $answer);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if (strlen($line)>0)
                {
                    $new_lines[] = $line;
                }
            }
            $question['answer'] = implode("\n", $new_lines);
        }

	    if ($act == 'add')
	    {
	        $question['parent_id'] = $parent_id;
	        $question['admin_id']  = $this->session->userdata('admin_id');
	        $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }
	    else
	    {
	        $question['ques_id']    = $ques_id;
	        $extends['old_opt_ids'] = $this->input->post('old_opt_id');
	        $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
	    }
        $url = site_url('/admin/question_external/edit_diction_question/'.$ques_result['ques_id']);
	    if ($message)
	    {
	        message(implode('<br>', $message), $url, null, 10);
	        return;
	    }
	    if ($message1)
	    {
	        // 删除已上传试题图片

	        message(implode('<br>', $message1), $url, null, 10);
	        return;
	    }

	    if ($ques_result['success'] == TRUE)
	    {
	        message('试题编辑成功', 'admin/question_external/diction/'.$parent_id);
	    }
	    else
	    {
	        message($ques_result['msg']);
	    }
	}

    /* ----------------------- 阅读填空 ------------------------------------- */

    /**
     * 添加阅读填空
     *
     * @return  void
     */
    public function add_blank ($relate_ques_id=0)
    {
        $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id        = intval($this->input->get('copy'));

        $be_tested = QuestionModel::question_has_test_action($copy_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_class   = array();

        if ($copy_id) {
            $this->examine_permission($copy_id);
            $question = QuestionModel::get_question($copy_id);
            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
            foreach ($query->result_array() as $row)
            {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question))
        {
            $question = array(
                    'class_id'    => '',
                    'subject_id'  => '',
                    'start_grade' => '',
                    'end_grade'   => '',
                    'title'       => '',
                    'score_factor' => '',
                    'tags' => '',
                    'is_original' => '',
                    'exam_year' => '',
                    'remark' => '',
                    'related' => '',
            );
        }

        $data['act']             = 'add';
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades']          = C('grades');
        $data['q_tags']          = C('q_tags/8');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = $relate_class;
        $data['question']        = $question;

        // 模版
        $this->load->view('question_external/edit_blank', $data);
    }

    /**
     * 阅读填空
     *
     * @return  void
     */
    public function blank ($parent_id = 0)
    {

        // 读取题干信息
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('阅读填空题不存在！');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

        $grades   = C('grades');
        $qtypes   = C('qtype');
        $subjects = CpUserModel::get_allowed_subjects();
        $subject_types    = C('subject_type');
        $group_type = GroupTypeModel::get_group_type_list(0, $parent['subject_id'], false, true);

        // 类型、学科属性（文理科）、难易度
        $class_list = ClassModel::get_class_list();
        $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));
        foreach ($query->result_array() as $arr)
        {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }
        $parent['class_names'] = $class_names;

        $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
        $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
        $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

        $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
        $parent['title'] = str_replace("\n", '<br/>', $parent['title']);
        //$parent['title'] = str_replace(" ", '&nbsp;', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2)
        {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related'])
        {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $parent['relateds'] = $relateds;
            }
        }

        $cpusers = CpUserModel::get_cpuser_list();

        // 读取子题信息
        $query = $this->db->select()->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
        $list = $query->result_array();
        foreach ($list as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();

            // 知识点
            $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
            $knowledge_arr = array();
            foreach($query->result_array() as $arr)
            {
                $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
            }
            $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';
        }

        // 读取子题信息(已删除)
        $query = $this->db->select()->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
        $list2 = $query->result_array();
        foreach ($list2 as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
        }

        $data['qtypes'] = $qtypes;
        $data['grades'] = $grades;
        $data['q_tags'] = C('q_tags/8');
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['list2']  = $list2;
        $data['group_type'] = $group_type;
        $data['priv_manage'] = true;
        $data['priv_delete'] = true;

        // 模版
        $this->load->view('question_external/blank', $data);
    }

    /**
     * 编辑阅读填空
     *
     * @return  void
     */
    public function edit_blank ($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);

        if (empty($question)) {
            message('阅读填空不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是完形填空，做相应跳转
        if ($question['type'] != 8 || $question['parent_id'])
        {
            redirect('admin/question_external/edit/'.$id);
        }

        // 试题类型
        $relate_class = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }
        $data['relate_class'] = $relate_class;

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['grades']          = C('grades');
        $data['q_tags']          = C('q_tags/8');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();

        // 模版
        $this->load->view('question_external/edit_blank', $data);
    }

    /**
     * 阅读填空添加题干
     *
     * @return  void
     */
    public function update_blank ()
    {
        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';

        if ($act == 'edit') {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question)) {
                message('阅读填空题不存在');
                return;
            }

            $be_tested = QuestionModel::question_has_test_action($ques_id);
            if ($be_tested) {
                message('该试题已经被考生考过 或者 正在被考， 无法操作');
            }
        }

        // 阅读填空题基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['type']         = 8;
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));

        $extends = array(
                'difficulty'     => &$difficulty,
                'class_ids'      => &$class_ids,
                'skill_ids'      => &$skill_ids,
                'knowledge_ids'  => &$knowledge_ids,
                'subject_types'  => &$subject_types,
        );

        $message = $this->_check_question($question, $extends, false, false);



        if ($act == 'add')
        {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add_group($question, $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $ques_result = QuestionModel::update_group($question, $extends);
        }
        $url = site_url('/admin/question_external/edit_blank/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br/>', $message), $url, null, 10);
            return;
        }


        if ($ques_result['success'] == TRUE)
        {
            message('阅读填空题编辑成功。', 'admin/question_external/blank/'.$ques_result['ques_id']);
            return;
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    /**
     * 添加阅读填空子题
     *
     * @return  void
     */
    public function add_blank_question ($parent_id = 0)
    {
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent']) {
            message('阅读填空题不存在');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $question = array(
                'type'        => 3,
                'p_type'      => 8,
                'title'       => '',
                'answer'      => '',
                'parent_id'   => $parent_id,
                'subject_id' => $parent['subject_id'],
                'group_type' => '',
                'knowledge' => '',
        );

        $data['act']       = 'add';
        $data['question']  = $question;
        $data['know_processes']  = array();

        // 模版
        $this->load->view('question_external/edit_blank_question', $data);
    }

    /**
     * 编辑阅读填空子题
     *
     * @return  void
     */
    public function edit_blank_question ($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是完形填空子题，做相应跳转
        if (!$question['parent_id'])
        {
            redirect('admin/question_external/edit/'.$id);
        }

        // 试题选项
        $query = $this->db->get_where('option', array('ques_id'=>$id));

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id) {
            $tmp_knowledge_ids = explode(',', $question['knowledge']);
            $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
            $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            if ($tmp_knowledge_ids != '') {
                $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
                foreach ($result as $item) {
                    $know_processes[$item['knowledge_id']] = array(
                            'kp' => $item['know_process'],
                            'name' => C('know_process/'.$item['know_process']),
                    );
                }
            }
        }

        //试题学科 关联的方法策略数
        $parent = QuestionModel::get_question($question['parent_id']);
        $question['subject_id'] = $parent['subject_id'];
        $question['p_type'] = 8;

        $data['act']      = 'edit';
        $data['options']  = $query->result_array();
        $data['question'] = $question;
        $data['know_processes'] = $know_processes;

        // 模版
        $this->load->view('question_external/edit_blank_question', $data);
    }

    /**
     * 阅读填空子题保存
     *
     * @return  void
     */
    public function update_blank_question ()
    {
        $act =  $this->input->post('act');
        $act = $act=='add' ? $act : 'edit';
        $parent_id = intval($this->input->post('parent_id'));
        $ques_id   = intval($this->input->post('ques_id'));

        if ($act == 'edit') {
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question) OR $old_question['parent_id'] != $parent_id) {
                message('阅读填空题不存在', 'javascript');
                return;
            }
        }
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) || !$parent['is_parent'])
        {
            message('阅读填空题不存在',  'javascript');
            return;
        }

        //判断题组题干是否还有关联技能，知识点
        if ($parent['skill'] != '' || $parent['knowledge'] != '') {
            message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题',  'javascript');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作',  'javascript');
        }

        // 题目基本信息
        $question['type']    = intval($this->input->post('qtype'));
        $question['title']   = '';

        //检查知识点、认知过程
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');

        $extends = array(
                'skill_ids'      => array(),
                'knowledge_ids'  => $knowledge_ids,
                'group_id'       => 0,
                'relate_ques_id' => 0,
                'relate_class'   => array(),
                'method_tactic_ids' => $method_tactic_ids,
                'know_process'   => $know_process,
        );

        if (!empty($parent) && $parent['subject_id'] == 3)
        {
            $group_type_ids = $this->input->post('group_type_id');
            $extends['group_type_ids'] = $group_type_ids;
            $extends['subject_id'] = $parent['subject_id'];
        }

        $message = $this->_check_group_question($question, $extends, false);

        $question['type'] = 8;

        // 试题信息验证
        $message1 = array();
        $answer = trim($this->input->post('input_answer'));
        if (empty($answer))
        {
            $message1[] = '请填写阅读填空题答案';
        }
        else
        {
            $new_lines = array();
            $lines = explode("\n", $answer);
            foreach ($lines as $line)
            {
                $line = trim($line);
                if (strlen($line)>0)
                {
                    $new_lines[] = $line;
                }
            }
            $question['answer'] = implode("\n", $new_lines);
        }



        if ($act == 'add')
        {
            $question['parent_id'] = $parent_id;
            $question['admin_id']  = $this->session->userdata('admin_id');
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        $url = site_url('/admin/question_external/edit_blank_question/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br>', $message), $url, null, 10);
            return;
        }

        if ($message1)
        {
            message(implode('<br>', $message1), $url, null, 10);
            return;
        }

        if ($ques_result['success'] == TRUE)
        {
            message('试题编辑成功', 'admin/question_external/blank/'.$parent_id);
        }
        else
        {
            message($ques_result['msg']);
        }
    }

    /* ----------------------- 连词成句 ------------------------------------- */

    /**
     * 添加连词成句
     *
     * @author TCG
     * @return void
     */
    public function addSentence ($relate_ques_id = 0)
    {
        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id = intval($this->input->get('copy'));
        $type = 9;

        $relate_class   = array();

        if ($copy_id) {
            $this->examine_permission($copy_id);
            $question = QuestionModel::get_question($copy_id);
            $question['type'] = $type;

            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
            foreach ($query->result_array() as $row)
            {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question))
        {
            $question = array(
                'type'        => $type,
                'p_type'  => $type,
                'class_id'    => '',
                'skill'       => '',
                'knowledge'   => '',
                'method_tactic' => '',
                'subject_id'  => '',
                'subject_type'=> 0,
                'start_grade' => '',
                'end_grade'   => '',
                'title'       => '',
                'answer'      => '',
                'count_subject_method_tactics' => '0',
                'is_original' => '',
                'exam_year' => '',
                'remark' => '',
                'related' => '',
            );
        }

        $data['act']             = 'add';
        $data['options']         = array();
        $data['question']        = $question;
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades'] = array(
            '1' => '一年级',
            '2' => '二年级',
            '3' => '三年级',
            '4' => '四年级',
            '5' => '五年级',
            '6' => '六年级',
        );
        $data['know_processes']  = array();
        $data['subjects']        = array(3 => '英语');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = &$relate_class;
        $data['back_url']        = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';


        $this->load->view('question_external/edit_sentence', $data);
    }

    /**
     * 编辑连词成句
     *
     * @author TCG
     * @return void
     */
    public function editSentence ($id)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);

        if (empty($question))
        {
            message('试题不存在');
        }

        if (!$this->is_subject_user($question['subject_id'])) return ;


        //以下情况无法操作试题：
        /**
        *   1、已被考过的试题
        *   2、有关联正在进行的考试 试题->试卷->学科
         */
        $be_tested = QuestionModel::question_has_test_action($id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 试题类型
        $relate_class = array();

        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));

        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }

        $data['relate_class'] = &$relate_class;

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id && $question['knowledge']) {
            $tmp_knowledge_ids = explode(',', $question['knowledge']);
            $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
            $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
            foreach ($result as $item) {
                $know_processes[$item['knowledge_id']] = array(
                            'kp' => $item['know_process'],
                            'name' => C('know_process/'.$item['know_process']),
                );
            }
        }

        //试题学科 关联的方法策略数
        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($question['subject_id']);

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['know_processes']  = $know_processes;
        $data['grades'] = array(
            '1' => '一年级',
            '2' => '二年级',
            '3' => '三年级',
            '4' => '四年级',
            '5' => '五年级',
            '6' => '六年级',
        );
        $data['subjects']        = array(3 => '英语');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['back_url']        = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        $this->load->view('question_external/edit_sentence', $data);
    }

    /**
     * 更新连词成句数据
     *
     * @author TCG
     * @return void
     */
    public function updateSentence ()
    {
        $subject_id = intval($this->input->post('subject_id'));

        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';

        if ($act == 'edit') {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);

            if (empty($old_question)) {
                message('试题不存在','javascript');
                return;
            }

            /**
            *   1、已被考过的试题
            *   2、有关联正在进行的考试 试题->试卷->学科
             */
            $be_tested = QuestionModel::question_has_test_action($ques_id);

            if ($be_tested) {
                message('该试题已经被考生考过 或者 正在被考， 无法操作','javascript');
            }
        }

        // 题目基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['type']         = intval($this->input->post('qtype'));
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['tags']         = intval($this->input->post('q_tag'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['simulation']    = trim($this->input->post('simulation'));
        $extends = array(
            'skill_ids'      => &$skill_ids,
            'method_tactic_ids' => &$method_tactic_ids,
            'knowledge_ids'  => &$knowledge_ids,
            'know_process'   => &$know_process,
            'class_ids'      => &$class_ids,
            'difficulty'     => &$difficulty,
            'subject_types'  => &$subject_types
        );

        $message = $this->_check_question($question, $extends);



        // 填空
        $question['answer'] = trim($this->input->post('input_answer'));



        if ($act == 'add')
        {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $question['group_id']   = $old_question['group_id'];
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        $url = site_url('/admin/question_external/editSentence/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br/>', $message), $url, null, 10);
            return;
        }
        // 填空

        if (empty($question['answer']))
        {
            message('试题答案不能为空！',$url);
        }


        if ($ques_result['success'] == TRUE)
        {
            $data['relate_ques_id'] = $ques_result['ques_id'];
            $data['relate_group']   = isset($ques_result['group_id']) ? $ques_result['group_id'] : 0;

            // 模版
            $this->load->view('question_external/success', $data);
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    /**
     * 预览连词成句
     *
     * @author TCG
     * @return void
     */
    public function showSentence ($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $row = QuestionModel::get_question($id);

        if (empty($row)) {
            message('试题不存在', 'admin/question_external/index');
            return;
        }

        // 分类数据
        $class_list = ClassModel::get_class_list();
        $grades           = C('grades');
        $subjects         = CpUserModel::get_allowed_subjects();;
        $subject_types    = C('subject_type');

        // 类型、学科属性（文理科）、难易度
        $show_subject_type = $row['subject_id']<= 3 && $row['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $arr)
        {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }
        $data['class_names'] = $class_names;

        // 年段
        $row['start_grade'] = isset($grades[$row['start_grade']]) ? $grades[$row['start_grade']] : '';
        $row['end_grade'] = isset($grades[$row['end_grade']]) ? $grades[$row['end_grade']] : '';

        // 学科
        $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';

        //关联知识点 认知过程
        $know_processes = array();
        $tmp_knowledge_ids = explode(',', $row['knowledge']);
        $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
        $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
        if ($tmp_knowledge_ids != '') {
            $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
            foreach ($result as $item) {
                $know_processes[$item['knowledge_id']] = C('know_process/'.$item['know_process']);
            }
        }

        //关联方法策略
        $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $id)->get();
        $method_tactic_arr = array();
        foreach($query->result_array() as $arr)
        {
            $method_tactic_arr[] = $arr['name'];
        }

        $method_tactic_arr = array_filter($method_tactic_arr);
        $row['method_tactic'] = implode(',', $method_tactic_arr);
        $row['count_subject_method_tactics'] = $this->_count_subject_method_tactics($row['subject_id']);

        // 知识点
        $query = $this->db->select('k.knowledge_name, rk.knowledge_id')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $id)->get();
        $knowledge_arr = array();
        foreach($query->result_array() as $arr)
        {
            $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . (isset($know_processes[$arr['knowledge_id']]) ? $know_processes[$arr['knowledge_id']] : '--') . '）';
        }
        $row['knowledge'] = implode(',', $knowledge_arr);

        // 技能
        $query = $this->db->select('s.skill_name')->from('relate_skill rs')->join('skill s','rs.skill_id=s.id','left')->where('rs.ques_id', $id)->get();
        $skill_arr = array();
        foreach($query->result_array() as $arr)
        {
            $skill_arr[] = $arr['skill_name'];
        }
        $row['skill'] = implode(',', $skill_arr);
        $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

        $row['title'] = str_replace("\r\n", '<br/>', $row['title']);

        $row['answer'] = explode("\n", $row['answer']); //str_replace("\n", '<br/>', $row['answer']);

        /** ---------------- 真题 ----------------- */

        if ($row['is_original'] == 2)
        {

            $relateds = $this->db->query("select ques_id from {pre}question where related={$id}")->result_array();

            {
                $row['relateds'] = $relateds;
            }
        }


        if ($row['related'])
        {
            $related = $row['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0)
            {
                $row['relateds'] = $relateds;
            }
        }

        $data['row']     = $row;
        $data['grades'] = $grades;

        $q_tags = C('q_tags');
        if (isset($q_tags[$row['type']]))
        {
            $data['q_tag'] = $q_tags[$row['type']][$row['tags']];
        }
        else
        {
            $data['q_tag'] = '';
        }

        $data['qtype'] = C('qtype');

        $data['priv_manage'] = true;

        $this->load->view('question_external/sentence', $data);
    }
    
    /**
     * 更新mini测试卷试题为“MINI测真题”
     */
    public function update_demo_question($exam_pid = 0)
    {
        if (!$exam_pid || !$this->db->get_where('rd_demo_exam_config', array('dec_exam_pid'=>$exam_pid)))
        {
            message('MINI测不存在');
        }
        
        $list = $this->db->select('ques_id,ctime')->from('rd_exam_test_result etr')
                ->join('exam_test_paper etp', "etp.etp_id = etr.etp_id", "left")
                ->where('etr.exam_pid', $exam_pid)
                ->group_by('ques_id')->get()->result_array();
        foreach ($list as $item)
        {
            $bind = array(
                'is_original' => 2,
                'exam_year'   => date('Y', $item['ctime']),
                'remark'      => C('webconfig')['site_name'] . 'MINI测试真题'
            );
            
            $this->db->update('question', $bind, "ques_id = " . $item['ques_id']);
        }
        
        message('MINI测试题更新为真题成功');
    }

    /* ----------------------- end 连词成句 -------------------- */

    /* ++++++++++++++++++++++++++ 听力题 ++++++++++++++++++++++++++++ */
    /**
     * 听力题-预览
     *
     * @return void
    */
    public function listening($parent_id = 0)
    {
        // 读取题干信息
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent']) {
            message('听力题不存在！');exit;
        }

        //判断该试题已经被考试过 或 正在被考
        $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

        $grades   = C('grades');
        $qtypes   = C('qtype');
        $subjects = CpUserModel::get_allowed_subjects();
        $subject_types    = C('subject_type');

        // 类型、学科属性（文理科）、难易度
        $class_list = ClassModel::get_class_list();
        $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));

        foreach ($query->result_array() as $arr) {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }

        $parent['class_names'] = $class_names;

        // 技能
        $skill_ids = explode(',', trim($parent['skill'], ','));
        $arr_tmp = array();
        if ($skill_ids)
        {
            $query = $this->db->select('skill_name')->where_in('id', $skill_ids)->get('skill');
            foreach ($query->result_array() as $row)
            {
                $arr_tmp[] = $row['skill_name'];
            }
        }

        $parent['skill_name'] = implode(',', $arr_tmp);
        $parent['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        // 知识点
        $knowledge_ids = explode(',', trim($parent['knowledge'], ','));
        $arr_tmp = array();
        if ($knowledge_ids)
        {
            $query = $this->db->select('knowledge_name')->where_in('id', $knowledge_ids)->get('knowledge');
            foreach ($query->result_array() as $row)
            {
                $arr_tmp[] = $row['knowledge_name'];
            }
        }
        $parent['knowledge_name'] = implode(',', $arr_tmp);

        $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
        $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
        $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

        $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
        $parent['title'] = str_replace("\n", '<br/>', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2) {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0) {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related']) {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0) {
                $parent['relateds'] = $relateds;
            }
        }

        $cpusers = CpUserModel::get_cpuser_list();

        // 读取子题信息
        $query = $this->db->select('ques_id,type,title,picture,answer,knowledge,method_tactic,group_type,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
        $list = $query->result_array();
        foreach ($list as &$row) {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();

            foreach ($row['options'] as $key => $arr)
            {
                $row['options'][$key]['is_answer'] = in_array($arr['option_id'],explode(',',$row['answer']));
            }

            // 知识点
            $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
            $knowledge_arr = array();
            foreach($query->result_array() as $arr)
            {
                $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
            }
            $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';

            //方法策略
            $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $row['ques_id'])->get();
            $method_tactic_arr = array();
            foreach($query->result_array() as $arr)
            {
                $method_tactic_arr[] = $arr['name'];
            }
            $method_tactic_arr = array_filter($method_tactic_arr);
            $row['method_tactic_name'] = implode(',', $method_tactic_arr);

            if ($row['group_type'])
            {
                $query = $this->db->select('gt.group_type_name')->from('relate_group_type rgt')->join('group_type gt','rgt.group_type_id=gt.id','left')->where('rgt.ques_id', $row['ques_id'])->get();
                $group_type_arr = array();
                foreach($query->result_array() as $arr)
                {
                    $group_type_arr[] = $arr['group_type_name'];
                }
                $group_type_arr = array_filter($group_type_arr);
                $row['group_type_name'] = implode(',', $group_type_arr);
            }
        }

        // 读取子题信息(已删除)
        $query = $this->db->select('ques_id,type,title,picture,answer,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
        $list2 = $query->result_array();
        foreach ($list2 as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();
        }

        $data['qtypes'] = $qtypes;
        $data['grades'] = $grades;
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['list2']  = $list2;

        // 模版
        $this->load->view('question_external/listening', $data);
    }

    /**
     * 听力题-添加
     *
     * @return void
    */
    public function add_listening($relate_ques_id = 0)
    {
        $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id        = intval($this->input->get('copy'));

        $be_tested = QuestionModel::question_has_test_action($copy_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_class   = array();

        if ($copy_id)
        {
            $this->examine_permission($copy_id);
            $question = QuestionModel::get_question($copy_id);
            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
            foreach ($query->result_array() as $row)
            {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question))
        {
            $question = array(
                'class_id'    => '',
                'skill'       => '',
                'knowledge'   => '',
                'subject_id'  => '',
                'subject_id_str'  => '',
                'start_grade' => '',
                'end_grade'   => '',
                'title'       => '',
                'score_factor' => '',
                'is_original' => '',
                'exam_year' => '',
                'remark' => '',
                'related' => '',
            );
        }

        $data['act']             = 'add';
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = $relate_class;
        $data['question']        = $question;

        // 模版
        $this->load->view('question_external/edit_listening', $data);
    }

    /**
     * 听力题-编辑
     *
     * @return void
    */
    public function edit_listening($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if($question['related']==0)$question['related']='';
        if($question['exam_year']==0)$question['exam_year']='';
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 试题类型
        $relate_class = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }
        $data['relate_class'] = $relate_class;

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();

        // 模版
        $this->load->view('question_external/edit_listening', $data);
    }

    // 题组题干添加操作
    public function update_listening()
    {
        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';
        if ($act == 'edit')
        {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);
            if (empty($old_question))
            {
                message('题组不存在');
                return;
            }

            $be_tested = QuestionModel::question_has_test_action($ques_id);
            if ($be_tested) {
                message('该试题已经被考生考过 或者 正在被考， 无法操作');
            }
        }

        // 题目基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['type']         = 12;
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['score_factor'] = intval($this->input->post('score_factor'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));

        if ($question['subject_id'] == 11)
        {
            $question['subject_id_str'] = ',' . implode(',', $this->input->post('subject_str')) . ',';
        }
        else
        {
            $question['subject_id_str'] = ',' . $question['subject_id'] . ',';
        }

        $extends = array(
            'difficulty'     => &$difficulty,
            'class_ids'      => &$class_ids,
            'skill_ids'      => &$skill_ids,
            'knowledge_ids'  => &$knowledge_ids,
            'subject_types'  => &$subject_types,
        );

        $message = $this->_check_question($question, $extends, false, false);

        if ($act == 'add')
        {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add_group($question, $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $ques_result = QuestionModel::update_group($question, $extends);
        }
        $url =site_url('/admin/question_external/edit_listening/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br/>', $message), $url, null, 10);
            return;
        }

        if ($ques_result['success'] == TRUE)
        {
            message('试题编辑成功。', 'admin/question_external/listening/'.$ques_result['ques_id']);
            return;
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    public function add_listening_question($parent_id = 0)
    {
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;

        $question = array(
            'type'        => 1,
            'title'       => '',
            'answer'      => '',
            'parent_id'   => $parent_id,
            'knowledge'   => '',
            'method_tactic' => '',
            'subject_id' => $parent['subject_id'],
            'subject_id_str'=>$parent['subject_id_str'],
            'group_type' => 0,
            'is_primary' => $is_primary,
        );

        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        $data['act']       = 'add';
        $data['question']  = $question;
        $data['know_processes']  = array();

        // 模版
        $this->load->view('question_external/edit_listening_question', $data);
    }

    public function edit_listening_question($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是题组子题，做相应跳转
        if (!$question['parent_id'])
        {
            redirect('admin/question/edit/'.$id);
        }

        // 试题选项
        $query = $this->db->get_where('option', array('ques_id'=>$id));

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id) {
            $tmp_knowledge_ids = explode(',', $question['knowledge']);
            $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
            $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            if ($tmp_knowledge_ids != '') {
                $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
                foreach ($result as $item) {
                    $know_processes[$item['knowledge_id']] = array(
                            'kp' => $item['know_process'],
                            'name' => C('know_process/'.$item['know_process']),
                    );
                }
            }
        }

        //试题学科 关联的方法策略数
        $parent = QuestionModel::get_question($question['parent_id']);
        $question['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        $question['subject_id'] = $parent['subject_id'];
        $question['subject_id_str'] = $parent['subject_id_str'];

        // 小学
        $is_primary = $parent['end_grade'] <= 6 ? true : false;
        $question['is_primary'] = $is_primary;

        $data['act']      = 'edit';
        $data['options']  = $query->result_array();
        $data['question'] = $question;
        $data['know_processes'] = $know_processes;

        // 模版
        $this->load->view('question_external/edit_listening_question', $data);
    }

    /**
     * 题组子题保存
     */
    public function update_listening_question()
    {
        $act =  $this->input->post('act');
        $act = $act=='add' ? $act : 'edit';
        $parent_id = intval($this->input->post('parent_id'));
        $ques_id   = intval($this->input->post('ques_id'));

        if ($act == 'edit')
        {
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);
            if (empty($old_question) OR $old_question['parent_id'] != $parent_id)
            {
                message('题组试题不存在', 'javascript');
                return;
            }
        }
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在', 'javascript');
            return;
        }

        //判断题组题干是否还有关联技能，知识点
        if ($parent['skill'] != '' || $parent['knowledge'] != '') {
            message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
        }

        // 题目基本信息
        $question['type']    = intval($this->input->post('qtype'));
        $question['title']   = trim($this->input->post('title'));

        //检查知识点、认知过程
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');

        $extends = array(
                'skill_ids'      => array(),
                'knowledge_ids'  => $knowledge_ids,
                'group_id'       => 0,
                'relate_ques_id' => 0,
                'relate_class'   => array(),
                'method_tactic_ids' => $method_tactic_ids,
                'know_process'   => $know_process,
        );

        if (!empty($parent) && $parent['subject_id'] == 3)
        {
            $group_type_ids = $this->input->post('group_type_id');
            $extends['group_type_ids'] = $group_type_ids;
            $extends['subject_id'] = $parent['subject_id'];
        }

        $message = $this->_check_group_question($question, $extends);
        if ($message)
        {
            message(implode('<br>', $message), null, null, 10);
        }

        // 试题信息验证
        $message = array();
        if (empty($question['type']))
        {
            $message[] = '请选择题型';
        }

        if ($question['type'] == 3)
        {
            $answer = trim($this->input->post('input_answer'));
            if (empty($answer))
            {
                $message[] = '请填写填空题答案';
            }
            else
            {
                $new_lines = array();
                $lines = explode("\n", $answer);
                foreach ($lines as $line)
                {
                    $line = trim($line);
                    if (strlen($line)>0)
                    {
                        $new_lines[] = $line;
                    }
                }
                $question['answer'] = implode("\n", $new_lines);
            }
            $options = array();
            $answer = '';
        }


        if ($question['type']  < 3)
        {
            $options        = $this->input->post('option');
            $answer         = $this->input->post('answer');
            $opt_result     = $this->_check_options($question['type'], $options, $answer);

        }

        if ($act == 'add')
        {
            $question['parent_id'] = $parent_id;
            $question['admin_id']  = $this->session->userdata('admin_id');
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }

        $url = site_url('/admin/question_external/edit_group_question/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br>', $message), $url, null, 10);
            return;
        }
        if ($opt_result['msg'])
        {
            message(implode('<br>', $opt_result['msg']), $url, null, 10);
            return;
        }
        if ($ques_result['success'] == TRUE)
        {
            message('试题编辑成功', 'admin/question_external/listening/'.$parent_id);
        }
        else
        {
            message($ques_result['msg']);
        }
    }
    /* ++++++++++++++++++++++++++++ end 听力题 ++++++++++++++++++++++ */

    /* ++++++++++++++++++++++++++ 改错题 ++++++++++++++++++++++++++++ */

    /**
     * 听力题-预览
     *
     * @return void
    */
    public function correct($parent_id = 0)
    {
        // 读取题干信息
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent']) {
            message('改错题不存在！');exit;
        }

        if (!QuestionModel::check_question_power($parent_id, 'r')) return;

        //判断该试题已经被考试过 或 正在被考
        $parent['be_tested'] = QuestionModel::question_has_test_action($parent_id);

        $grades   = C('grades');
        $qtypes   = C('qtype');
        $subjects = CpUserModel::get_allowed_subjects();
        $subject_types    = C('subject_type');

        // 类型、学科属性（文理科）、难易度
        $class_list = ClassModel::get_class_list();
        $show_subject_type = $parent['subject_id']<= 3 && $parent['end_grade']>=11;
        $class_names = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$parent_id));

        foreach ($query->result_array() as $arr) {
            $subject_type = $show_subject_type ? ' , '.$subject_types[$arr['subject_type']] : '';
            $class_names[$arr['grade_id']][] = isset($class_list[$arr['class_id']]['class_name']) ? $class_list[$arr['class_id']]['class_name'].'[难易度:'.$arr['difficulty'].$subject_type.']' : '';
        }

        $parent['class_names'] = $class_names;

        // 技能
        $skill_ids = explode(',', trim($parent['skill'], ','));
        $arr_tmp = array();
        if ($skill_ids)
        {
            $query = $this->db->select('skill_name')->where_in('id', $skill_ids)->get('skill');
            foreach ($query->result_array() as $row)
            {
                $arr_tmp[] = $row['skill_name'];
            }
        }

        $parent['skill_name'] = implode(',', $arr_tmp);
        $parent['count_subject_method_tactics'] = $this->_count_subject_method_tactics($parent['subject_id']);

        // 知识点
        $knowledge_ids = explode(',', trim($parent['knowledge'], ','));
        $arr_tmp = array();
        if ($knowledge_ids)
        {
            $query = $this->db->select('knowledge_name')->where_in('id', $knowledge_ids)->get('knowledge');
            foreach ($query->result_array() as $row)
            {
                $arr_tmp[] = $row['knowledge_name'];
            }
        }
        $parent['knowledge_name'] = implode(',', $arr_tmp);

        $parent['start_grade'] = isset($grades[$parent['start_grade']]) ? $grades[$parent['start_grade']] : '';
        $parent['end_grade'] = isset($grades[$parent['end_grade']]) ? $grades[$parent['end_grade']] : '';
        $parent['subject_name'] = isset($subjects[$parent['subject_id']]) ? $subjects[$parent['subject_id']] : '';

        $parent['addtime'] = date('Y-m-d H:i', $parent['addtime']);
        $parent['title'] = str_replace("\n", '<br/>', $parent['title']);

        /** ---------------- 真题 ----------------- */
        if ($parent['is_original'] == 2) {
            $relateds = $this->db->query("select ques_id from {pre}question where related={$parent_id}")->result_array();

            if (count($relateds) > 0) {
                $parent['relateds'] = $relateds;
            }
        }
        elseif ($parent['related']) {
            $related = $parent['related'];
            $relateds = $this->db->query("select ques_id from {pre}question where related={$related}")->result_array();

            if (count($relateds) > 0) {
                $parent['relateds'] = $relateds;
            }
        }

        $cpusers = CpUserModel::get_cpuser_list();

        // 读取子题信息
        $query = $this->db->select('ques_id,type,title,picture,answer,knowledge,method_tactic,group_type,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>0));
        $list = $query->result_array();
        foreach ($list as &$row) {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();

            foreach ($row['options'] as $key => $arr)
            {
                $row['options'][$key]['is_answer'] = in_array($arr['option_id'],explode(',',$row['answer']));
            }

            // 知识点
            $query = $this->db->select('k.knowledge_name, rk.knowledge_id, rk.know_process')->from('relate_knowledge rk')->join('knowledge k','rk.knowledge_id=k.id','left')->where_in('rk.ques_id', $row['ques_id'])->get();
            $knowledge_arr = array();
            foreach($query->result_array() as $arr)
            {
                $knowledge_arr[] = $arr['knowledge_name'] . '（认知过程：' . C('know_process/'.$arr['know_process']) . '）';
            }
            $row['knowledge_name'] = count($knowledge_arr) ? implode(',', $knowledge_arr) : '<font style="font-size:15px;color:red;">该子题还未添加知识点</font>';

            //方法策略
            $query = $this->db->select('mt.name')->from('relate_method_tactic rmt')->join('method_tactic mt','rmt.method_tactic_id=mt.id','left')->where('rmt.ques_id', $row['ques_id'])->get();
            $method_tactic_arr = array();
            foreach($query->result_array() as $arr)
            {
                $method_tactic_arr[] = $arr['name'];
            }
            $method_tactic_arr = array_filter($method_tactic_arr);
            $row['method_tactic_name'] = implode(',', $method_tactic_arr);

            if ($row['group_type'])
            {
                $query = $this->db->select('gt.group_type_name')->from('relate_group_type rgt')->join('group_type gt','rgt.group_type_id=gt.id','left')->where('rgt.ques_id', $row['ques_id'])->get();
                $group_type_arr = array();
                foreach($query->result_array() as $arr)
                {
                    $group_type_arr[] = $arr['group_type_name'];
                }
                $group_type_arr = array_filter($group_type_arr);
                $row['group_type_name'] = implode(',', $group_type_arr);
            }
        }

        // 读取子题信息(已删除)
        $query = $this->db->select('ques_id,type,title,picture,answer,admin_id,addtime')->get_where('question', array('parent_id'=>$parent_id, 'is_delete'=>1));
        $list2 = $query->result_array();
        foreach ($list2 as &$row)
        {
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname'])?$cpusers[$row['admin_id']]['realname']:'';
            $row['addtime'] = date('Y-m-d H:i:s', $row['addtime']);
            $query = $this->db->get_where('option', array('ques_id'=>$row['ques_id']));
            $row['options'] = $query->result_array();
        }

        $data['qtypes'] = $qtypes;
        $data['grades'] = $grades;
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['list2']  = $list2;

        // 模版
        $this->load->view('question_external/correct', $data);
    }

    /**
     * 听力题-添加
     *
     * @return void
    */
    public function add_correct($relate_ques_id = 0)
    {
        $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_ques_id = intval($relate_ques_id);
        $relate_group   = intval($this->input->get('group'));
        $copy_id        = intval($this->input->get('copy'));

        $be_tested = QuestionModel::question_has_test_action($copy_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $relate_class   = array();

        if ($copy_id)
        {
            $this->examine_permission($copy_id);
            $question = QuestionModel::get_question($copy_id);
            // 试题类型
            $query = $this->db->get_where('relate_class', array('ques_id'=>$copy_id));
            foreach ($query->result_array() as $row)
            {
                $relate_class[$row['grade_id']][$row['class_id']] = $row;
            }
        }

        if (empty($question))
        {
            $question = array(
                'class_id'    => '',
                'skill'       => '',
                'knowledge'   => '',
                'subject_id'  => '',
                'subject_id_str'  => '',
                'start_grade' => '',
                'end_grade'   => '',
                'title'       => '',
                'score_factor' => '',
                'is_original' => '',
                'exam_year' => '',
                'remark' => '',
                'related' => '',
            );
        }

        $data['act']             = 'add';
        $data['relate_ques_id']  = $relate_ques_id;
        $data['relate_group']    = $relate_group;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();
        $data['relate_class']    = $relate_class;
        $data['question']        = $question;

        // 模版
        $this->load->view('question_external/edit_correct', $data);
    }

    /**
     * 听力题-编辑
     *
     * @return void
    */
    public function edit_correct($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if($question['related']==0)$question['related']='';
        if($question['exam_year']==0)$question['exam_year']='';
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 试题类型
        $relate_class = array();
        $query = $this->db->get_where('relate_class', array('ques_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $relate_class[$row['grade_id']][$row['class_id']] = $row;
        }
        $data['relate_class'] = $relate_class;

        $data['act']             = 'edit';
        $data['question']        = $question;
        $data['grades']          = C('grades');
        $data['subjects']        = CpUserModel::get_allowed_subjects();
        $data['all_subjects']    = C('subject');
        $data['subject_types']   = C('subject_type');
        $data['all_grade_class'] = ClassModel::all_grade_class();

        // 模版
        $this->load->view('question_external/edit_correct', $data);
    }

    // 题组题干添加操作
    public function update_correct()
    {
        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';
        if ($act == 'edit')
        {
            $ques_id = $this->input->post('ques_id');
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);
            if (empty($old_question))
            {
                message('改错题不存在');
                return;
            }

            $be_tested = QuestionModel::question_has_test_action($ques_id);
            if ($be_tested) {
                message('该试题已经被考生考过 或者 正在被考， 无法操作');
            }
        }

        // 题目基本信息
        $class_ids           = $this->input->post('class_id');
        $skill_ids           = $this->input->post('skill_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $difficulty          = $this->input->post('difficulty');
        $subject_types       = $this->input->post('subject_type');
        $question['type']         = 13;
        $question['test_way']     = array_sum($this->input->post('test_way'));
        $question['subject_id']   = intval($this->input->post('subject_id'));
        $question['start_grade']  = intval($this->input->post('start_grade'));
        $question['end_grade']    = intval($this->input->post('end_grade'));
        $question['title']        = trim($this->input->post('title'));
        $question['is_original']  = intval($this->input->post('is_original'));
        $question['exam_year']    = intval($this->input->post('exam_year'));
        $question['remark']       = trim($this->input->post('remark'));
        $question['related']      = intval($this->input->post('related'));
        $question['simulation']    = trim($this->input->post('simulation'));

        if ($question['subject_id'] == 11) {
            $question['subject_id_str'] = ',' . implode(',', $this->input->post('subject_str')) . ',';
        } else {
            $question['subject_id_str'] = ',' . $question['subject_id'] . ',';
        }

        $extends = array(
            'difficulty'     => &$difficulty,
            'class_ids'      => &$class_ids,
            'skill_ids'      => &$skill_ids,
            'knowledge_ids'  => &$knowledge_ids,
            'subject_types'  => &$subject_types,
            'score_factor' => '',
        );

        $message = $this->_check_question($question, $extends, false, false);

        if ($act == 'add') {
            $extends['group_id']        = intval($this->input->post('relate_group'));
            $extends['relate_ques_id']  = intval($this->input->post('relate_ques_id'));
            $ques_result = QuestionModel::add_group($question, $extends);
        } else {
            $question['ques_id']    = $ques_id;
            $ques_result = QuestionModel::update_group($question, $extends);
        }

        $url =site_url('/admin/question_external/edit_correct/'.$ques_result['ques_id']);
        if ($message)
        {
            message(implode('<br/>', $message), $url, null, 10);
            return;
        }

        if ($ques_result['success'] == TRUE)
        {
            message('试题编辑成功。', 'admin/question_external/correct/'.$ques_result['ques_id']);
            return;
        }
        else
        {
            message($ques_result['msg']);
            return;
        }
    }

    public function add_correct_question($parent_id = 0)
    {
        $parent_id = intval($parent_id);
        $this->examine_permission($parent_id);
        $parent_id && $parent = QuestionModel::get_question($parent_id);

        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);

        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $question = array(
                'type'        => 6,
                'title'       => '',
                'answer'      => '',
                'parent_id'   => $parent_id,
                'knowledge'   => '',
                'method_tactic' => '',
                'subject_id' => $parent['subject_id'],
        );

        $data['act']       = 'add';
        $data['question']  = $question;
        $data['know_processes']  = array();

        // 模版
        $this->load->view('question_external/edit_correct_question', $data);
    }

    public function edit_correct_question($id = 0)
    {
        $id = intval($id);
        $this->examine_permission($id);
        $id && $question = QuestionModel::get_question($id);
        if (empty($question))
        {
            message('试题不存在');
            return;
        }

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 不是题组子题，做相应跳转
        if (!$question['parent_id'])
        {
            redirect('admin/question_external/edit/'.$id);
        }

        // 试题选项
        $query = $this->db->get_where('option', array('ques_id'=>$id));

        //获取关联知识点对应的认知过程
        $know_processes = array();
        if ($id) {
            $tmp_knowledge_ids = explode(',', $question['knowledge']);
            $tmp_knowledge_ids = array_filter($tmp_knowledge_ids);
            $tmp_knowledge_ids = implode(',', $tmp_knowledge_ids);
            if ($tmp_knowledge_ids != '') {
                $result = $this->db->query("select knowledge_id, know_process from {pre}relate_knowledge where ques_id={$id} and knowledge_id in ($tmp_knowledge_ids)")->result_array();
                foreach ($result as $item) {
                    $know_processes[$item['knowledge_id']] = array(
                            'kp' => $item['know_process'],
                            'name' => C('know_process/'.$item['know_process']),
                    );
                }
            }
        }

        //试题学科 关联的方法策略数
        $parent = QuestionModel::get_question($question['parent_id']);
        $question['subject_id'] = $parent['subject_id'];
        $question['subject_id_str'] = $parent['subject_id_str'];

        $data['act']      = 'edit';
        $data['options']  = $query->result_array();
        $data['question'] = $question;
        $data['know_processes'] = $know_processes;

        // 模版
        $this->load->view('question_external/edit_correct_question', $data);
    }

    /**
     * 题组子题保存
     */
    public function update_correct_question()
    {
        $act =  $this->input->post('act');
        $act = $act=='add' ? $act : 'edit';
        $parent_id = intval($this->input->post('parent_id'));
        $ques_id   = intval($this->input->post('ques_id'));

        if ($act == 'edit')
        {
            $this->examine_permission($ques_id);
            $ques_id && $old_question = QuestionModel::get_question($ques_id);
            if (empty($old_question) OR $old_question['parent_id'] != $parent_id)
            {
                message('题组试题不存在', 'javascript');
                return;
            }
        }
        $parent_id && $parent = QuestionModel::get_question($parent_id);
        if (empty($parent) OR ! $parent['is_parent'])
        {
            message('题组不存在', 'javascript');
            return;
        }

        //判断题组题干是否还有关联技能，知识点
        if ($parent['knowledge'] != '') {
            message('该题关联的题干还有未取消的技能或者知识点,请先取消再编辑该题', 'javascript');
            return;
        }

        $be_tested = QuestionModel::question_has_test_action($parent_id);
        if ($be_tested) {
            message('该试题已经被考生考过 或者 正在被考， 无法操作', 'javascript');
        }

        // 题目基本信息
        $question['type']    = intval($this->input->post('qtype'));
        $question['title']   = trim($this->input->post('title'));

        //检查知识点、认知过程
        $method_tactic_ids   = $this->input->post('method_tactic_id');
        $knowledge_ids       = $this->input->post('knowledge_id');
        $know_process        = $this->input->post('know_process');

        $extends = array(
                'skill_ids'      => array(),
                'knowledge_ids'  => $knowledge_ids,
                'group_id'       => 0,
                'relate_ques_id' => 0,
                'relate_class'   => array(),
                'method_tactic_ids' => $method_tactic_ids,
                'know_process'   => $know_process,
        );

        if (!empty($parent) && $parent['subject_id'] == 3)
        {
            $group_type_ids = $this->input->post('group_type_id');
            $extends['group_type_ids'] = $group_type_ids;
            $extends['subject_id'] = $parent['subject_id'];
        }

        $message = $this->_check_group_question($question, $extends);
        if ($message)
        {
            message(implode('<br>', $message), null, null, 10);
        }

        // 试题信息验证
        $message = array();
        if (empty($question['type']))
        {
            $message[] = '请选择题型';
        }

        $answer = trim($this->input->post('input_answer'));

        if (empty($answer)) {
            $message[] = '请填写填空题答案';
        } else {
            $question['answer'] = $answer;
        }

        if ($act == 'add')
        {
            $question['parent_id'] = $parent_id;
            $question['admin_id']  = $this->session->userdata('admin_id');
            $ques_result = QuestionModel::add($question, $opt_result['options'], $opt_result['answer'], $extends);
        }
        else
        {
            $question['ques_id']    = $ques_id;
            $extends['old_opt_ids'] = $this->input->post('old_opt_id');
            $ques_result = QuestionModel::update($question, $opt_result['options'], $opt_result['answer'], $extends);
        }

        $url = site_url('/admin/question_external/edit_correct_question/'.$ques_result['ques_id']);

        if ($message)
        {
            message(implode('<br>', $message), $url, null, 10);
            return;
        }

        if ($opt_result['msg'])
        {
            message(implode('<br>', $opt_result['msg']), $url, null, 10);
            return;
        }
        if ($ques_result['success'] == TRUE)
        {
            message('试题编辑成功', 'admin/question_external/correct/'.$parent_id);
        }
        else
        {
            message($ques_result['msg']);
        }
    }
    /* ++++++++++++++++++++++++++++ end 改错题 ++++++++++++++++++++++ */

    //-----------------试题操作--------------------------------//
    /**
     * 设置试题状态
     */
	public function set_ques_status()
	{
        $ques_id = $this->input->get('ques_id');
        $this->examine_permission($ques_id);
        $ques_id && $old_question = QuestionModel::get_question($ques_id);

        if (empty($old_question)) {
            return;
        }

        $status = (int) $this->input->get('status');

        $result = $this->db->update('question', array('is_delete' => $status), 'ques_id = ' . $ques_id);
        echo json_encode(array('status'=>$result));
	}

    // 删除单个试题
    public function delete($id = 0)
    {
        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        recycle_log_check($id);

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';

        $this->examine_permission($id);
        $return = QuestionModel::delete($id);

        if ( $return === true )
        {
        	recycle_log(RECYCLE_QUESTION, $id);
            message('删除成功', $back_url, 'success');
        }
        else
        {
            switch($return)
            {
                case -1: $message = '试题不存在'; break;
                //case -2: $message = '该题组下还存在子题，不能删除。';break;
                default: $message = '删除失败'; break;
            }
            message($message, $back_url);
        }
    }


    // 审核单个试题
    public function check($id = 0)
    {
        $question = QuestionModel::get_question($id);
        $check = intval($this->input->post('check'));

        if ($check ==1 ) {
           $check_1 = 0;
           $liyo = 'unexamine';
        } else {
           $check_1=1;
           $liyo = 'examine';
        }

        //判断该试题已经被考试过 或 正在被考
        $sql="update {pre}question set `check`='$check_1' where ques_id='$id'  and   `check`<>'$check_1' and is_original=1";
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';
        $return = $this->db->query($sql);

        if ($return === true) {
            admin_log($liyo, 'question', $id);
            message('操作成功', $back_url, 'success');
        } else {
            $message = '操作失败';
            message($message, $back_url);
        }
    }

    // 更新试题类型
    public function update_original($id = 0)
    {
        $this->examine_permission($id);

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_be_tested($id);

        if (!$be_tested) {
            message('该试题未被考生考过， 无法操作');
        }

      	$difficulty = intval($this->input->post('difficulty'));
		$q_id = intval($this->input->post('id'));
		$original_v = intval($this->input->post('original_v'));


        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';

        if($original_v==1) {
            $sql ="update {pre}question set exam_year='$difficulty',is_original=2  where ques_id='$q_id' ";
            $liyo = 'old_exam';
        } else {
            $sql ="update {pre}question set related='$difficulty',is_original=1  where ques_id='$q_id' ";
            $liyo = 'original';

        }

        $return = $this->db->query($sql);

        if ( $return === true )
        {
            admin_log($liyo, 'question', $q_id);
           // recycle_log_1(RECYCLE_QUESTION, $id, $liyo);
            message('设置成功', $back_url, 'success');
        }
        else
        {

            message('设置失败', $back_url);
        }
    }

    // 批量删除
    public function batch_delete()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要删除的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';

        $success = $fail = 0;
        foreach ($ids as $id)
        {

        	//判断该试题已经被考试过 或 正在被考
        	$be_tested = QuestionModel::question_has_test_action($id);
            $this->examine_permission($id);

        	if ($be_tested) {
        		$fail++;
        		continue;
        	}

            if (QuestionModel::delete($id) === true)
                $success++;
            else
                $fail++;
        }
        message('批量操作完成，成功删除：'.$success.' 个，失败：'.$fail.' 个（可能原因：试题已经被考试过 或 正在被考 或 删除失败）。', $back_url);
    }

    // 移除
    public function remove($id = 0)
    {
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index/trash';

        //判断该试题已经被考试过 或 正在被考
        $be_tested = QuestionModel::question_has_test_action($id);

        if ($be_tested) {
        	message('该试题已经被考生考过 或者 正在被考， 无法操作');
        }

        $this->examine_permission($id);
        $return = QuestionModel::remove($id);

        if ( $return === true ) {
            message('删除成功', $back_url);
        } else {
            switch($return)
            {
                case -1: $message = '试题不存在'; break;
                case -2: $message = '试题不在回收站';break;
                default: $message = '删除失败'; break;
            }
            message($message, $back_url);
        }
    }

    // 批量删除
    public function batch_remove()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要删除的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index/trash';

        $success = $fail = 0;
        foreach ($ids as $id)
        {
            $this->examine_permission($id);
        	//判断该试题已经被考试过 或 正在被考
        	$be_tested = QuestionModel::question_has_test_action($id);

        	if ($be_tested) {
        		$fail++;
        		continue;
        	}

            if (QuestionModel::remove($id) === true)
                $success++;
            else
                $fail++;
        }
        message('批量操作完成，成功删除：'.$success.' 个，失败：'.$fail.' 个(可能原因：试题已经被考生考过 或者 正在被考 或者 删除失败)。', $back_url);
    }

    // 还原
    public function restore($id = 0)
    {
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index/trash';

        $this->examine_permission($id);
        $return = QuestionModel::restore($id);

        if ( $return === true ) {
            message('还原成功', $back_url);
        } else {
            switch($return) {
                case -1: 
                    $message = '试题不存在'; 
                    break;
                default: 
                    $message = '还原失败'; 
                    break;
            }

            message($message, $back_url);
        }
    }

    // 批量还原
    public function batch_restore()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要操作的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index/trash';

        $success = $fail = 0;

        foreach ($ids as $id) {
            $this->examine_permission($id);

            if (QuestionModel::restore($id) === true)
                $success++;
            else
                $fail++;
        }

        message('批量操作完成，成功还原：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    // 批量相关
    public function batch_relate()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
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

        $be_tested = QuestionModel::question_has_test_action($relate_ques_id);

        if ($be_tested) {
        	message('被关联试题已经被考生考过 或者 正在被考， 无法操作');
        }

        // 如果被关联试题无分组，则：创建分组，并把该试题加入关联
        $group_id = $relate_ques['group_id'];

        if (empty($group_id)) {
            $this->db->insert('relate_group', array('group_name'=>$relate_ques_id));
            $group_id = $this->db->insert_id();
            $this->db->update('question', array('group_id'=>$group_id), array('ques_id'=>$relate_ques_id));
        }

        $success = $fail = 0;
        foreach ($ids as $id)
        {
            $this->examine_permission($id);
            $num = QuestionModel::relate($id, $group_id);

            if ($num > 0)
                $success += $num;
            else
                $fail++;
        }
        
        $back_url ='admin/relate_group/group/'.$group_id;
        message('批量操作完成，成功关联：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    // 批量取消相关试题
    public function batch_unrelate()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要操作的项目！');
            return;
        }

        $success = $fail = 0;

        foreach ($ids as $id) {
            $this->examine_permission($id);
        	$be_tested = QuestionModel::question_has_test_action($id);

        	if ($be_tested) {
        		$fail++;
        		continue;
        	}

            $num = QuestionModel::unrelate($id);

            if ($num > 0)
                $success += $num;
            else
                $fail++;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';
        message('批量操作完成，成功取消关联：'.$success.' 个，失败：'.$fail.' 个(可能原因：试题已被考过 或者 正在被考 或者 关联失败 或者 没权限)。', $back_url);
    }

    // 批量审核
    public function batch_shenhe()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要审核的题目！');
            return;
        }

        $success = $fail = 0;

        foreach ($ids as $id) {
            $this->examine_permission($id);
            $be_tested = QuestionModel::question_has_test_action($id);
            $question = QuestionModel::get_question($id);

            if($be_tested||!$this->has_question_check($question)||$question['is_original']!=1||$question['check']!=0) {
                $fail++;
                continue;
            }

            $sql="update {pre}question set `check`=1 where ques_id='$id' ";
            $return = $this->db->query($sql);

            if ( $return === true ) {    $liyo = 'examine';
                 admin_log($liyo, 'question', $id);
                 $success++;
            }
            else
                $fail++;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';

        message('批量操作完成，成功审核：'.$success.' 个，失败：'.$fail.' 个。', $back_url);

    }

    // 批量取消审核
    public function batch_unshenhe()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请选择要取消审核的题目！');
            return;
        }

        $success = $fail = 0;

        foreach ($ids as $id) {
            $this->examine_permission($id);
            $be_tested = QuestionModel::question_has_test_action($id);
            $question = QuestionModel::get_question($id);

            if($be_tested||!$this->has_question_check($question)||$question['is_original']!=1||$question['check']!=1) {
                $fail++;
                continue;
            }

            $sql="update {pre}question set `check`=0 where ques_id='$id'";
            $return = $this->db->query($sql);

            if ( $return === true ) {    
                $liyo = 'unexamine';
                admin_log($liyo, 'question', $id);
                $success++;
            }
            else
                $fail++;
        }

             $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_external/index';

            message('批量操作完成，成功取消审核：'.$success.' 个，失败：'.$fail.' 个。', $back_url);

    }

    // 验证选项有效性
    private function _check_options($type, $options, $answer)
    {
        $result = array('options' => array(), 'answer'=>'', 'msg'=>array());
        $msg = array();
        if (empty($type)) $result['msg'][] = '请选择题型';
        if (empty($options) OR ! is_array($options)) $result['msg'][] = '请填写选项信息';
        if ($result['msg'])
        {
            return $result;
        }

        $new_options = array();

        // 各选项组内容
        $old_opt_picture = $this->input->post('old_opt_picture');
        foreach ($options as $k => $v)
        {
        	$v = strtr($v,array("<p>" => "", "</p>" => ""));//过虑选项内容中的<p></p>
            $option = array(
                'ques_id'     => 0,
                'option_name' => trim($v),
            );

            if ($_FILES['option_file']['name'][$k])
            {
                $_FILES['option_picture'] = array(
                    'name'     => & $_FILES['option_file']['name'][$k],
                    'type'     => & $_FILES['option_file']['type'][$k],
                    'tmp_name' => & $_FILES['option_file']['tmp_name'][$k],
                    'error'    => & $_FILES['option_file']['error'][$k],
                    'size'     => & $_FILES['option_file']['size'][$k]
                );
                if ($this->upload->do_upload('option_picture'))
                {
                    $option['picture'] =  $this->upload->data('file_relative_path');
                }
                else
                {
                    $result['msg'][] = $this->upload->display_errors() . '(选项：'.($k+1).')';
                    //break;
                }
            }

            // 选项无效
            if ( strlen($option['option_name'])==0 && empty($option['picture']) && empty($old_opt_picture[$k]))
            {
                continue;
            }

            $new_options[$k] = $option;
        }

        // 检查选项组是否有效（个数，答案）
        $opt_count = count($new_options);
        if ($type == 1 || $type == 7)
        {
            $answer = intval($answer);
            if ($opt_count < 3)
            {
                $result['msg'][] = '请确认有效选项个数。';
            }
            elseif ( ! isset($new_options[$answer]))
            {
                $result['msg'][] =  '请选择有效答案。';
            }
        }
        else
        {
            if ( ! is_array($answer) ) $answer = (array)$answer;
            if ($opt_count<6 && $opt_count>10)
            {
                $result['msg'][] = '请确认有效选项个数。';
            }
            else
            {
                foreach ($answer as $i => $v)
                {
                    // 去除无效答案
                    if ( ! isset($new_options[$v]))
                        unset($answer[$i]);
                }
                if (empty($answer))
                    $result['msg'][] = '请选择有效答案。';
            }
        }
        $result['options'] = & $new_options;
        $result['answer']  = & $answer;
        return $result;
    }

    // 验证试题信息有效性
    private function _check_question(&$question, &$extends, $need_know_process = true, $need_knowledge = true)
    {
        // 试题信息验证
        $message = array();

        /** 试题类型数组最大键名 */
        $max = max(array_keys(C('q_type')));

        if (!isset($question['type']) || $question['type'] === '' || $question['type'] > $max)
        {
            $message[] = '请选择题型';
        }

        $question['end_grade'] = $question['end_grade']>12 ? 12 : $question['end_grade'];

        if (empty($question['subject_id']))
        {
            $message[] = '请选择学科';
        }

        if ($question['subject_id'] == 11
            && !$question['subject_id_str'])
        {
            $message[] = '请选择综合的学科';
        }

        if (empty($question['start_grade']) OR empty($question['end_grade']))
        {
            $message[] = '请选择年级区间';
        }
        elseif ($question['start_grade'] > $question['end_grade'])
        {
            $message[] = '开始年级不能比结束年级高';
        }
        if (empty($extends['class_ids']) || ! is_array($extends['class_ids']))
        {
            $message[] = '请选择试题类型';
        }
        else
        {
            $class_ids      = &$extends['class_ids'];
            $subject_types  = &$extends['subject_types'];
            $difficulty     = &$extends['difficulty'];

            $relate_class   = array();
            $question_class = array();

            for($i=$question['start_grade']; $i<=$question['end_grade']; $i++)
            {
                if (empty($class_ids[$i]) OR !is_array($class_ids[$i]))
                {
                    if ($i==$question['start_grade'] || $i==$question['end_grade'])
                    {
                        $message[] = '请选择首尾年级相应的试题类型';
                        break;
                    }
                    else
                    {
                        continue;
                    }
                }

                foreach ($class_ids[$i] as $cid)
                {
                    $cid = intval($cid);
                    $question_class[$cid] = $cid;

                    //文理科属性只有 高二~高三 的语、数、外有，即grade_id >= 11 and subject_id > 3
                    if ($i >= 11) {
                    	if ($question['subject_id'] > 3) {
		                    $subject_type = '-1';
                    	} else {
		                    $subject_type = isset($subject_types[$i][$cid]) ? $subject_types[$i][$cid] : '0';
                    	}
                    } else {
                    	$subject_type = '-1';
                    }

                    $cid_diff = isset($difficulty[$i][$cid]) ? floatval($difficulty[$i][$cid]) : 0;
                    if ($cid_diff < 1 OR $cid_diff>100)
                    {
                        $message[] = '请填写选中类型对应的难易度';
                        break 2;
                    }
                    $relate = array(
                        'grade_id'     => $i,
                        'class_id'     => $cid,
                        'subject_type' => $subject_type,
                        'difficulty'   => $cid_diff,
                        'copy_difficulty' => $cid_diff,
                    );
                    $relate_class[] = $relate;
                }
            }
            sort($question_class, SORT_NUMERIC);
            $question['class_id'] = ','.implode(',', $question_class).',';
            $extends['relate_class'] = $relate_class;
            unset($extends['class_ids'], $extends['subject_types'], $extends['difficulty']);
        }

        //后续将技能移除，目前先不作限制
        /*
        if (empty($extends['skill_ids']) || ! is_array($extends['skill_ids']))
        {
            $message[] = '请选择技能';
        }*/
        if (!empty($extends['skill_ids']) && is_array($extends['skill_ids']) && count($extends['skill_ids']))
        {
            $extends['skill_ids'] = my_intval($extends['skill_ids']);
            sort($extends['skill_ids'], SORT_NUMERIC);
            $question['skill'] = ','.implode(',',$extends['skill_ids']).',';
        } else {
        	$extends['skill_ids'] = array();
            $question['skill'] = '';
        }

        //方法策略, 选填项
        if (!empty($extends['method_tactic_ids']) && is_array($extends['method_tactic_ids']) && count($extends['method_tactic_ids']))
        {
        	$extends['method_tactic_ids'] = my_intval($extends['method_tactic_ids']);
            sort($extends['method_tactic_ids'], SORT_NUMERIC);
            $question['method_tactic'] = ','.implode(',',$extends['method_tactic_ids']).',';
        } else {
    		$extends['method_tactic_ids'] = array();
    		$question['method_tactic'] = '';
    	}

        if ($need_knowledge) {
	        if (empty($extends['knowledge_ids']) || ! is_array($extends['knowledge_ids']))
	        {
	            $message[] = '请选择知识点';
	        }
	        else
	        {
	        	//检查认知过程
	        	if ($need_know_process) {
		        	foreach ($extends['knowledge_ids'] as $knowledge_id) {
		        		if (!isset($extends['know_process'][$knowledge_id]) || !intval($extends['know_process'][$knowledge_id])) {
		        			$message[] = '已勾选的知识点必须选择 认知过程';
		        			break;
		        		}
		        	}
	        	}

	            $extends['knowledge_ids'] = my_intval($extends['knowledge_ids']);
	            sort($extends['knowledge_ids'], SORT_NUMERIC);
	            $question['knowledge'] = ','.implode(',',$extends['knowledge_ids']).',';
	        }
        } else {
        	if (is_array($extends['knowledge_ids']) && count($extends['knowledge_ids']))
        	{
        		$extends['knowledge_ids'] = my_intval($extends['knowledge_ids']);
        		sort($extends['knowledge_ids'], SORT_NUMERIC);
        		$question['knowledge'] = ','.implode(',',$extends['knowledge_ids']).',';
        	} else {
        		$extends['knowledge_ids'] = array();
        		$question['knowledge'] = '';
        	}
        }

        if (in_array($question['type'], array(5, 6)))
        {
            if (!empty($question['tags']))
            {
                $q_tags = C('q_tags/' . $question['type']);
                if (!in_array($question['tags'], array_keys($q_tags)))
                {
                    $message[] = '标签不存在';
                }
            }
            else
            {
                $message[] = '请选择标签';
            }
        }

        //翻译题 翻译类型
        if ($question['type'] == 7)
        {
            $translation = $this->input->post('translation');

            if (empty($translation))
            {
                $message[] = '请选择翻译类型';
            }
            else
            {
                $question['translation'] = intval($translation);
            }
        }

        if (isset($extends['group_type_ids']) && empty($extends['group_type_ids']))
        {
            $message[] = '请选择信息提取方式';
        }
        else if(!empty($extends['group_type_ids']))
        {
            $extends['group_type_ids'] = my_intval($extends['group_type_ids']);
            sort($extends['group_type_ids'], SORT_NUMERIC);
            $question['group_type'] = ','.implode(',',$extends['group_type_ids']).',';
        }

        if (isset($question['score_factor']) && ($question['score_factor'] < 1 OR $question['score_factor'] > 10))
        {
            $message[] = '请正确填写分值系数（1-10）';
        }

        if (strlen($question['title']) == 0)
        {
            $message[] = '请填写试题内容';
        }
        else
        {
            if (isset($question['options']))
            {
                if (strlen($question['options']) == 0)
                {
                    if ($question['type'] == 5)
                    {
                        $message[] = '请填写试题选项';
                    }
                    elseif ($question['type'] == 6)
                    {
                        $message[] = '请填写试题选词';
                    }
                }
                else
                {
                    $question['title'] .= "&nbsp;<br/>&nbsp;" . $question['options'];
                }
                unset($question['options']);
            }
        }

        if ($question['type'] == 3)
        {
            $answer = trim($this->input->post('input_answer'));
            if (strlen($answer) == 0)
            {
                $message[] = '请填写填空题答案';
            }
            else
            {
                $new_lines = array();
                $lines = explode("\n", $answer);
                foreach ($lines as $line)
                {
                    $line = trim($line);
                    if (strlen($line)>0)
                    {
                        $new_lines[] = $line;
                    }
                }
                $question['answer'] = implode("\n", $new_lines);
            }
        }

        return $message;
    }

    // 删除试题/选项图片
    public function delete_pic($type='question', $id=0)
    {
        $id = intval($id);
        $type = $type=='question' ? $type : 'option';
        $result = array('type'=>$type, 'id'=>$id, 'success' => true, 'msg'=>'');

        if (empty($id)) {
            $result['msg'] = '图片不存在';
            die(json_encode($result));
        }

        if ($type == 'question')
        {
            $picture = QuestionModel::get_question($id, 'picture');

            if (empty($picture)) {
                $result['msg'] = '图片不存在';
            } else {
                $result['success'] = TRUE;
                $this->db->update('question', array('picture'=>''), array('ques_id'=>$id));
                if (is_file(_UPLOAD_ROOT_PATH_.$picture))
                {
                    @unlink(_UPLOAD_ROOT_PATH_.$picture);
                }
            }
        }
        else
        {
            $query = $this->db->get_where('option', array('option_id'=>$id), 1, 0);
            $option = $query->row_array();
            if (empty($option))
            {
                $result['msg'] = '选项不存在';
            }
            else
            {
                if (empty($option['picture']))
                {
                    $result['msg'] = '图片不存在';
                }
                else
                {
                    $result['success'] = TRUE;
                    $this->db->update('option', array('picture'=>''), array('option_id'=>$id));
                    if (is_file(_UPLOAD_ROOT_PATH_.$option['picture']))
                    {
                        @unlink(_UPLOAD_ROOT_PATH_.$option['picture']);
                    }
                }
            }
        }
        die(json_encode($result));
    }

    /**
     * 检查某个学科是否存在 方法策略
     */
    public function count_method_tactics()
    {
    	$subject_id = intval($this->input->get('subject_id'));

    	die($this->_count_subject_method_tactics($subject_id));
    }

    /**
     * 获取某个学科的 关联方法策略数
     */
    private function _count_subject_method_tactics($subject_id = 0)
    {
    	$subject_id = intval($subject_id);

    	if (!$subject_id) return "0";

    	if ($subject_id == 11)
    	{
    	    return 1;
    	}

    	$result = $this->db->query("select count(*) as count from {pre}method_tactic mt, {pre}subject_category_subject scs
    	                            where mt.subject_category_id=scs.subject_category_id and scs.subject_id={$subject_id}")
    	                ->row_array();
    	return $result['count'];
    }

    /* ----------------- 真题路径验证 ---------------------- */
    public function validate_related ()
    {
        $related = $this->input->post('related');

        $question = QuestionModel::get_question($related);

        $result = ($question && $question['is_original'] == '2') ? 'true' : 'false';

        die($result);
    }

}