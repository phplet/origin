<?php if ( ! defined('BASEPATH')) exit();

class Question_audit extends A_Controller
{
    /**
     * 构造函数
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        /* 权限检测 */
        if (!$this->check_power('question_examine')) exit;
    }

    /**
     * 试题列表
     *
     * @return void
     */
    public function index()
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
        $where[] = "q.parent_id=0"; // 过滤题组子题目，在题组页面管
        $where[] = "q.is_delete <> 1";

        /* 真题不需要审核 */
        $where[] = "q.is_original <> 2";

        // 录入人员
        if ($search['admin_id'] = intval($this->input->get('admin_id')))
        {
            $where[] = "q.admin_id=$search[admin_id]";
            $param[] = "admin_id=$search[admin_id]";
        }

        //限制只能查看自己创建的试题
       	if (!$this->is_super_user()
       	    && !CpUserModel::is_action_type_all('question', 'r')
       	    && !CpUserModel::is_action_type_subject('question', 'r')
       	    && CpUserModel::is_action_type_self('question', 'r')) {
       		$c_uid = $this->session->userdata('admin_id');
       		$search['admin_id'] = $c_uid;
        	$param[] = "admin_id=$c_uid";
        	$where[] = "q.admin_id='$c_uid'";
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

        //限制只能查看所属学科
        if (!$this->is_super_user()
            && !$this->is_all_subject_user()
            && !CpUserModel::is_action_type_all('question', 'r')
            && CpUserModel::is_action_type_subject('question', 'r'))
        {
            $c_subject_id = rtrim($this->session->userdata('subject_id'), ',');
            if( $c_subject_id!='')
            {

                $c_subject_id=explode(',',$c_subject_id);
                $c_subject_id = array_values(array_filter($c_subject_id));
                $c_subject_id = implode(',',$c_subject_id);
                $where[] = "q.subject_id in($c_subject_id)";
            }

       	}

       	//限制只能查看所属年级
       	if (!$this->is_super_user()&& !$this->is_all_grade_user())
       	{
       	    $c_grade_id = rtrim($this->session->userdata('grade_id'), ',');
       	    if($c_grade_id!='')
       	    {
       	        $c_grade_id=explode(',',$c_grade_id);
       	        $c_grade_id = array_values(array_filter($c_grade_id));
       	        $c_grade_id = implode(',',$c_grade_id);
       	        $where_3  =" grade_id in($c_grade_id)" ;
       	    }
       	    else
       	    {
       	        $where_3='1=1';
       	    }
       	}
       	else
       	{
       	    $where_3='1=1';
       	}

       	//限制只能查看所属类型
       	if (!$this->is_super_user()&& !$this->is_all_q_type_user())
       	{
       	    $c_q_type_id = rtrim($this->session->userdata('q_type_id'), ',');
            if($c_q_type_id!='')
       	    {

       	        $c_q_type_id=explode(',',$c_q_type_id);
       	        $c_q_type_id = array_values(array_filter($c_q_type_id));
       	        $c_q_type_id = implode(',',$c_q_type_id);
       	        $where_4 = "class_id in ($c_q_type_id)";

       	    }
       	    else
       	    {
       	        $where_4='1=1';
       	    }
       	}
       	else
       	{
       	    $where_4='1=1';
       	}

       	if ($where_3 != '1=1' || $where_4 != '1=1')
       	{
       		$where[] = "q.ques_id IN (SELECT DISTINCT ques_id FROM rd_relate_class WHERE $where_3 AND $where_4) ";
       	}

       	if ($search['subject_id_str'] = $this->input->get('subject_str'))
       	{
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
                $row['has_edit_power'] = QuestionModel::check_question_power($row['ques_id'], 'w', false);
                $row['recycle'] = array();

                $list[] = $row;
            }
        }

        $data['list'] = &$list;

        // 分页
        $purl = site_url('admin/question_audit/index/'.$mode) . ($param ? '?'.implode('&',$param) : '');
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
        $data['priv_delete'] = $this->check_power('question_delete', FALSE);
        $data['priv_trash']  = $this->check_power('question_trash', FALSE);
        $data['priv_manage']  = $this->check_power('question_manage', FALSE);
        if (!$this->is_super_user() && !$this->is_all_subject_user() && CpUserModel::is_action_type_all('question', 'r'))
        {
            $query = $this->db->select('admin_id,admin_user,realname')->get_where('admin', array('is_delete'=>0));
        }
        else if(!$this->is_super_user() && !$this->is_all_subject_user()
        && CpUserModel::is_action_type_subject('question', 'r')) {
                $c_subject_id = $this->session->userdata('subject_id');
                $c_subject_id=explode(',',$c_subject_id);
	            $c_subject_id = array_values(array_filter($c_subject_id));
                $where_11=array();
	            foreach ( $c_subject_id as $val)
	            {
	                $where_11[]="find_in_set($val,b.subject_id)";
	            }
	            $where_12 = implode(' or ', $where_11);

            $sql  =  "SELECT admin_id,admin_user,realname  FROM {pre}admin
            WHERE admin_id in(select admin_id from {pre}admin_role a,{pre}role b
             WHERE a.role_id=b.role_id and ($where_12))  and is_delete=0 ";

            $query=$this->db->query($sql );

            /*
            $query = $this->db->select('admin_id,admin_user,realname')->where('subject_id', $c_subject_id)->get_where('admin', array('is_delete'=>0));
            */
        }
        else if (!$this->is_super_user() && !$this->is_all_subject_user()
        && CpUserModel::is_action_type_self('question', 'r')) {
	        $c_uid = $this->session->userdata('admin_id');
	        $query = $this->db->select('admin_id,admin_user,realname')->where('admin_id', $c_uid)->get_where('admin');
        }
        else {
	        $query = $this->db->select('admin_id,admin_user,realname')->get_where('admin', array('is_delete'=>0));
        }


        $data['admin_list'] = $query->result_array();

        //文理科
        $data['subject_types'] = C('subject_type');

        $data['all_subjects'] = C('subject');

        //认知过程
        $data['know_processes'] = C('know_process');

        if ($this->is_super_user()
            || $this->is_all_subject_user()
            || $this->is_subject_user(3)
            || CpUserModel::is_action_type_all('question','w'))
        {
            $data['is_english_admin'] = true;
        }

        // 模版
        $this->load->view('question_audit/index', $data);
    }

    /**
     * 审核单个试题页面
     *
     * @return void
     */
    public function audit($ques_id = 0)
    {
        if (empty($ques_id)) {
            $message = '试题参数错误，操作失败';
        }

        $question = QuestionModel::get_question($ques_id);

        $data = array();
        $data['ques_id'] = $ques_id;
        $data['question'] = $question;

        $this->load->view('question_audit/audit', $data);
    }

    /**
     * 审核单个试题动作
     *
     * @return void
     */
    public function do_audit()
    {
        $data = array();
        $log = array();
        $check = intval($this->input->post('check'));
        $ques_id = $this->input->post('ques_id');
        $comment = $this->input->post('comment');

        if (empty($check)) {
            $data['status'] = 0;
            $data['msg'] = '数据错误，请重试！';
        }

        $sql="update {pre}question set `check`={$check} where ques_id='{$ques_id}' and is_original<>2";
        $return = $this->db->query($sql);

        if ($return) {
            if ($check == 1) {
                $log['flag'] = 1;
                $log['ques_id'] = $ques_id;
            } else {
                $log['flag'] = -1;
                $log['ques_id'] = $ques_id;
                $log['comment'] = $comment;
            }

            $this->write_log($log);

            $data['status'] = 1;
            $data['msg'] = '审核成功！';
        } else {
            $data['status'] = 0;
            $data['msg'] = '审核失败，请重试！';
        }

        echo json_encode($data);exit;
    }

    /**
     * 查看审核日志
     *
     * @return void
     */
    public function log($ques_id)
    {
        $sql = "SELECT * FROM {pre}question_audit WHERE ques_id={$ques_id} ORDER BY id DESC LIMIT 100";
        $result = $this->db->query($sql)->result_array();

        if (empty($result)) {
            $result = array();
        }

        $data = array();
        $data['logs'] = $result;
        $this->load->view('question_audit/audit_log', $data);
    }

    /**
     * 写入日志
     *
     * @return void
     */
    public function write_log($log)
    {
        $log['time'] = time();
        $log['admin_id'] = $this->session->userdata('admin_id');
        $log['admin_name'] = $this->session->userdata('realname');

        $keys = array_keys($log);
        $values = array_values($log);

        @array_walk($keys, function(&$val){ $val = "`" . $val . "`"; });
        @array_walk($values, function(&$val){ $val = "'" . $val . "'"; });

        $keys_str = implode(',', $keys);
        $values_str = implode(',', $values);

        $sql="INSERT INTO {pre}question_audit ({$keys_str}) VALUES ({$values_str})";

        return $this->db->query($sql);
    }

    /**
     * 批量审核
     *
     * @return void
    */
    public function batch_audit()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR !is_array($ids)) {
            message('请选择要审核的题目！');
        }

        $success = $fail = 0;

        foreach ($ids as $id) {
            $be_tested = QuestionModel::question_has_test_action($id);
            $question = QuestionModel::get_question($id);

            if ($be_tested || !$this->has_question_check($question) || $question['is_original']!=1 || $question['check']!=0) {
                $fail++;
                $test++;
                continue;
            }

            $sql="update {pre}question set `check`=1 where ques_id='$id' ";
            $return = $this->db->query($sql);

            if ( $return === true ) {    
                $liyo = 'examine';
                admin_log($liyo, 'question', $id);
                $success++;
            } else {
                $fail++;
            } 
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_audit/index';
        message('批量操作完成，成功审核：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }

    /**
     * 批量取消审核
     *
     * @return void
    */
    public function batch_unshenhe()
    {
        $ids = $this->input->post('ids');

        if (empty($ids) OR !is_array($ids)) {
            message('请选择要取消审核的题目！');
        }

        $success = $fail = 0;

        foreach ($ids as $id) {
            $be_tested = QuestionModel::question_has_test_action($id);
            $question = QuestionModel::get_question($id);

            if ($be_tested || !$this->has_question_check($question) || $question['is_original']!=1 || $question['check']!=1) {
                $fail++;
                continue;
            }

            $sql="update {pre}question set `check`=0 where ques_id='$id'";
            $return = $this->db->query($sql);

            if ($return === true) {    
                $liyo = 'unexamine';
                admin_log($liyo, 'question', $id);
                $success++;
            } else {
                $fail++;
            }        
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/question_audit/index';
        message('批量操作完成，成功取消审核：'.$success.' 个，失败：'.$fail.' 个。', $back_url);
    }
}
