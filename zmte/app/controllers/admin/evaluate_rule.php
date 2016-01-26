<?php if ( ! defined('BASEPATH')) exit();

/**
 *
 * 评估管理--评估规则 控制器
 * @author TCG
 * @final 2015-07-18
 *
 */
class Evaluate_rule extends A_Controller
{
    /**
     * 实现父类构造函数，载入模型
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 评估规则页面
     *
     * @return  void
     */
    public function index()
    {
    	if (!$this->check_power('evaluate_rule_list,evaluate_rule_manage')) return;

        // 查询条件
        $query  = array();
        $param  = array();
        $search = array();

        $page = intval($this->input->get('page'));
        $page = $page ? $page : 1;

        $per_page = intval($this->input->get('per_page'));
        $per_page = $per_page ? $per_page : 15;
        $selectWhat = null;

        //拼接查询条件
        $query_exam_pid = $this->input->get('exam_pid');
        if ($query_exam_pid)  {
            $query['exam_pid'] = trim($query_exam_pid);
            $search['exam_pid'] = $query_exam_pid;
            $param[] = "exam_pid={$query_exam_pid}";
        } else {
        	$search['exam_pid'] = '';
        }

        $query_place_id = $this->input->get('place_id');
        if ($query_place_id)  {
            $query['place_id'] = trim($query_place_id);
            $search['place_id'] = $query_place_id;
            $param[] = "place_id={$query_place_id}";
        } else {
        	$search['place_id'] = '';
        }

        $query_subject_id = $this->input->get('subject_id');
        if ($query_subject_id)  {
            $query['subject_id'] = trim($query_subject_id);
            $search['subject_id'] = $query_subject_id;
            $param[] = "subject_id={$query_subject_id}";
        } else {
        	$search['subject_id'] = '';
        }

        $query_is_trash = (int)$this->input->get('trash');
        if ($query_is_trash)  {
            $query['is_delete'] = '1';
            $search['trash'] = $query_is_trash;
            $param[] = "trash={$query_is_trash}";
        } else {
            $query['is_delete'] = '0';
        }

        // 按照时间排序,最新的在最前边
        $order = 'id desc';

        $result = EvaluateRuleModel::get_evaluate_rule_list($query, $page, $per_page, $order, $selectWhat);


        //附加信息
        $list = array();
        $this->load->model('cron/report/task_report_model');
        foreach ($result as &$item) {
        	$exam_pid = $item['exam_pid'];
        	$place_id = $item['place_id'];
        	$subject_id = $item['subject_id'];

        	$exam_name = ExamModel::get_exam($exam_pid, 'exam_name');
        	$item['exam_name'] = !count($exam_name) ? '--' : $exam_name;

        	$place_name = ExamPlaceModel::get_place($place_id, 'place_name');
        	$item['place_name'] = !($place_name && count($place_name)) ? ($place_id == 0 ? '所有考场' : '--') : $place_name;
        	$item['subject_name'] = $subject_id > 0 ? SubjectModel::get_subject($subject_id, 'subject_name') : '总结';

        	//关联一级知识点数量
        	$item['count_knowledges'] = EvaluateRuleModel::count_knowledge_lists(array('er_id' => $item['id']), 'count(distinct(knowledge_id))');

        	//检查该规则的生成报告状态
        	$task = $this->task_report_model->get_task($item['id']);
        	$item['task_status'] = isset($task['status']) ? $task['status'] : '-1';

        	//检查考试期次是否生成成绩
        	//if ($subject_id == 0)
        	//{
        	    $exam_result = $this->db->select('status')->get_where('cron_task_exam_result', array('exam_pid'=>$exam_pid))->row_array();
        	    if ($exam_result && $exam_result['status'] >= 1)
        	    {
        	        $item['is_exam_result'] = true;
        	    }
        	//}
        	
        	//检查是否已生html页面
    	    $exist_html = $this->db->select('id')
    	           ->get_where('rd_convert2pdf', array('rule_id'=>$item['id'], 'html_status'=>1))
    	           ->row_array();
    	    if ($exist_html)
    	    {
    	        $item['is_exist_html'] = true;
    	    }

            /* 面试报告状态 1.未关联评分标准不显示生成面试结果按钮 2.正在处理状态 3.生成完毕，显示查看生成报告按钮*/
            /* rd_evaluation_standard_exam 评分标准关联考试期次 1 */
            $sql = "select id from {pre}evaluation_standard_exam where exam_id={$exam_pid}";
            $interview_result = $this->db->query($sql)->row_array();

            if ($interview_result && !empty($interview_result)) {
                $item['is_interview'] = true;

                /* rd_cron_interview_task_report 报告生成结果 1:正在处理 2:部分处理完成 3:全部处理完成 */
                $sql = "select status from {pre}cron_interview_task_report where rule_id={$item['id']}";
                $interview_task_status = $this->db->query($sql)->row_array();
                $item['interview_task_status'] = isset($interview_task_status['status']) ? $interview_task_status['status'] : '-1';
            }

        	//添加管理员信息
        	$item['admin_name'] = CpUserModel::get_cpuser($item['admin_id'],'realname');

        	$list[] = $item;
        }

        // 分页
        $purl = site_url('admin/evaluate_rule/index/') . (count($param) ? '?'.implode('&',$param) : '');
        $total = EvaluateRuleModel::count_lists($query);
		$data['pagination'] = multipage($total, $per_page, $page, $purl);

        $data['detail'] = &$search;
        $data['list']   = &$list;
        $data['comparison_levels'] = C('evaluate_comparison_level');
        $data['priv_manage'] = $this->check_power('evaluate_rule_manage', FALSE);
        $data['has_report_command_priv'] = $this->check_power_new('report_command_index', FALSE);
        
        // 模版
        $this->load->view('evaluate_rule/index', $data);
    }

    /**
     * 添加评估规则
     *
     * @param int $copy_id 需要复制的评估规则id
     * @return void
     */
    public function add($copy_id=0)
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;

    	if($copy_id > 0)
    	{
    		$query = array('id' => $copy_id);
    		$detail = EvaluateRuleModel::get_evaluate_rule_list($query);
    		if (!$copy_id || !count($detail)) {
    			message('不存在评估规则信息');
    			return;
    		}

    		$detail = $detail[0];
    		$detail['is_reportting'] = false;

    		//获取关联知识点
    		$query = array('er_id' => $copy_id);
    		$knowledge_list = EvaluateRuleModel::get_evaluate_knowledge_list($query, null, null, null, null, true);

    		//按照 等级 归档
    		$tmp_knowledge_list = array();
    		foreach ($knowledge_list as $k=>$v) {
    			$subject_id = $k;
    			foreach ($v as $i) {
    				$knowledge_id = $i['knowledge_id'];
    				$tmp_knowledge_list['s_' . $subject_id]['k_' . $knowledge_id]['l_' . $i['level']] = array('comment' => $i['comment'], 'suggest' => $i['suggest']);
    			}
    		}

    		//获取关联 方法策略
    		$query = array('er_id' => $copy_id);
    		$method_tactic_list = EvaluateRuleModel::get_evaluate_method_tactic_list($query, null, null, null, null, true);

    		$method_tactic_ids = array();
    		foreach ($method_tactic_list as $val)
    		{
    			foreach ($val as $item)
    			{
    				$method_tactic_ids[] = $item['method_tactic_id'];
    			}
    		}
    		$method_tactic_ids = array_filter($method_tactic_ids);
    		$method_tactic_ids = array_unique($method_tactic_ids);
    		$method_tactic_ids = count($method_tactic_ids) ? $method_tactic_ids : array(0);
    		$method_tactic_ids = implode(',', $method_tactic_ids);

    		$subject_category = $this->db->query("select sc.id as subject_category_id,mt.id as method_tactic_id, mt.name, scs.subject_id from {pre}method_tactic mt, {pre}subject_category_subject scs,{pre}subject_category sc where mt.subject_category_id=scs.subject_category_id and sc.id=scs.subject_category_id and mt.id in ($method_tactic_ids)")->result_array();

    		//按照 等级 归档
    		$tmp_method_tactic_list = array();
    		foreach ($method_tactic_list as $k=>$v) {
    		    $subject_id = $k;
    		    foreach ($v as $i) {
    		    	$method_tactic_id = $i['method_tactic_id'];
    		    	foreach ($subject_category as $item)
    		    	{
    		    		if ($method_tactic_id == $item['method_tactic_id'])
    		    		{
    		    			$tmp_method_tactic_list['sc_' . $item['subject_category_id']]['method_tactic_' . $method_tactic_id]['l_' . $i['level']] = array('comment' => $i['comment'], 'suggest' => $i['suggest']);
    		    			continue;
    		    		}else{
    		    			$tmp_method_tactic_list['s_' . $subject_id]['method_tactic_' . $method_tactic_id]['l_' . $i['level']] = array('comment' => $i['comment'], 'suggest' => $i['suggest']);
    		    		}
    		    	}
    		    }
    		 }

    		 //获取关联信息提取方式
    		 $query = array('er_id' => $copy_id);
    		 $group_type_list = EvaluateRuleModel::get_evaluate_group_type_list($query, null, null, null, null, true);

    		 //按照 等级 归档
    		 $tmp_group_type_list = array();
    		 foreach ($group_type_list as $k=>$v) {
    		     $subject_id = $k;
    		     foreach ($v as $i) {
    		         $gr_id = $i['group_type_id'];
    		         $tmp_group_type_list['s_' . $subject_id]['gr_' . $gr_id]['l_' . $i['level']] = array('comment' => $i['comment'], 'suggest' => $i['suggest']);
    		     }
    		 }

    		//获取单人模式下被搜索考生
    		$student = array();
    		if ($detail['generate_mode'] == '1') {
    			$student = StudentModel::get_student($detail['generate_uid']);
    		}

    		$detail['generate_u_keyword'] = count($student) ? $student['email'] : '';

    		//获取当前期次
    		$exam = ExamModel::get_exam($detail['exam_pid'], 'exam_id, exam_name');
    		$detail['exam_name'] = count($exam) ? $exam['exam_name'] : '该考试期次已经被删除';

    		//获取当前考场
    		$place = ExamPlaceModel::get_place($detail['place_id'], 'place_id, place_name');
    		$detail['place_name'] = is_array($place) && count($place) ? $place['place_name'] : ($detail['place_id'] == 0 ? '所有考场' : '该考场已经被删除');

    		//外部对比信息
    		$comparison_info = unserialize($detail['comparison_info']);
    		$comparison_info = is_array($comparison_info) ? $comparison_info : array();
    		$detail['comparison_info'] = $comparison_info;

    		$this->session->set_userdata(array('comparison_info'=>$detail['comparison_info']));

    		$data['copy_id'] = $copy_id;
    		$data['detail'] = &$detail;
    		$data['knowledge_list'] = &$tmp_knowledge_list;
    		$data['method_tactic_list'] = &$tmp_method_tactic_list;
    		$data['group_type_list'] = &$tmp_group_type_list;
    		$data['comparison_types'] = $this->_get_comparison_info();

    		$data['comparison_levels'] = C('evaluate_comparison_level');
    	}
    	else
    	{
	    	$detail = array(
                'id' => '',
                'name' => '',
                'exam_pid' => '',
                'place_id' => '',
                'subject_id' => '',
                'knowledge' => '',
                'comparison_level' => '',
                'generate_mode' => '1',
                'generate_u_keyword' => '',
                'comparison_info' => array(),
                'is_reportting' => false,
                'generate_subject_report' => 1,
	    	    'generate_transcript' => 0,
	    	    'generate_class_report' => 0,
	    	    'generate_teacher_report' => 0,
	    	);
	    	$this->session->set_userdata(array('comparison_info'=>''));
	    	$data['detail'] = $detail;
    		$data['knowledge_list'] = array();
    		$data['comparison_types'] = $this->_get_comparison_info();
    	}

        $data['mode'] = 'add';
        
        // 模版
        $this->load->view('evaluate_rule/add', $data);
    }

    /**
     * 保存添加记录
     *
     * @return  void
     */
    public function save()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;

        $er_id = (int)$this->input->post('id');
        $evaluate_rule = EvaluateRuleModel::get_evaluate_rule_list(array('id' => $er_id));
        if ($er_id > 0 && !count($evaluate_rule))
        {
            message('该评估规则不存在', 'admin/evaluate_rule/index');
            return;
        }

        if ($er_id > 0 && $this->rule_is_reportting($er_id))
        {
            message('该评估规则正在生成报告，无法操作', 'admin/evaluate_rule/index');
            return;
        }

        $er_id && $evaluate_rule = $evaluate_rule[0];

        $name = trim($this->input->post('name'));
        $exam_pid = intval($this->input->post('exam_pid'));
        $contrast_exam_pid = intval($this->input->post('contrast_exam_pid'));//对比考试期次
        $generate_subject_report = intval($this->input->post('generate_subject_report'));//是否生成学科报告
        $generate_transcript = intval($this->input->post('generate_transcript'));//是否生成学生成绩单
        $generate_class_report = intval($this->input->post('generate_class_report'));//是否生成班级报告
        $generate_teacher_report = intval($this->input->post('generate_teacher_report'));//是否生成教师报告
        $place_id = trim($this->input->post('place_id'));
        $subject_id = $this->input->post('subject_id');
        $comparison_level = $this->input->post('comparison_level');//对比等级(地域)
        $subject_percent = $this->input->post('subject_percent');//学科->综合->各学科占比
        $generate_mode = intval($this->input->post('generate_mode'));//生成模式
        $generate_u_keyword = trim($this->input->post('generate_u_keyword'));//单人模式下的生成uid

        if ($name == '') {
            message('规则名称不能为空');
            return;
        }

        if (!$exam_pid || !count(ExamModel::get_exam($exam_pid))) {
            message('请选择考试期次');
            return;
        }

        if ($place_id == '' || ($place_id > 0 && !count(ExamPlaceModel::get_place($place_id)))) {
            message('请选择考试场次');
            return;
        }

        if ($subject_id == '' || (intval($subject_id) > 0 && !count(SubjectModel::get_subject($subject_id)))) {
            message('请选择考试科目');
            return;
        }
        
        if ($exam_pid == $contrast_exam_pid)
        {
            message('同一考试期次不能进行对比');
            return;
        }
        
        $distribution_proportion = null;
        if ($generate_class_report 
            || $generate_teacher_report)
        {
            $proportion_name = $this->input->post('distribution_proportion_name');
            $proportion      = $this->input->post('distribution_proportion');
            if (array_filter($proportion_name)
                && array_filter($proportion))
            {
                if (array_unique(array_filter($proportion_name)) != $proportion_name
                    || array_unique(array_filter($proportion)) != $proportion)
                {
                    message('分布比例填写不符合要求，请重新填写！');
                    return;
                }
                
                if (min($proportion) <= 0
                    || max($proportion) != 100)
                {
                    message('分布比例临界值取值范围为(0, 100]左开右闭的区间的整数值！');
                    return;
                }
                
                $proportions = array();
                foreach ($proportion_name as $i => $val)
                {
                    if (!Validate::isNotEmpty($val)
                        || !Validate::isInt($proportion[$i])
                        || $proportion[$i] <= 0
                        || $proportion[$i] > 100)
                    {
                        message('分布比例填写不符合要求，请重新填写！');
                        return;
                    }
                
                    $proportions[trim($val)] = round($proportion[$i]);
                }
                
                if (array_unique($proportions) != $proportions)
                {
                    message('分布比例填写不符合要求，请重新填写！');
                    return;
                }
                
                asort($proportions);
                $distribution_proportion = json_encode($proportions);
            }
        }

        //对比等级
        if (!is_array($comparison_level) || !count($comparison_level)) {
        	message('请选择对比等级');
        	return;
        }
        
        if (!$generate_subject_report 
            && !$generate_transcript
            && !$generate_class_report
            && !$generate_teacher_report)
        {
            message('请至少选择一个类型的报告进行生成工作');
            return;
        }

        $query_arr = array('name' => $name);
        if ($er_id) {
        	$query_arr['id !='] = $er_id;
        }

        $query = $this->db->select('id')->get_where('evaluate_rule', $query_arr);
        if ($query->num_rows())
        {
        	message('该规则名称已存在');
        	return;
        }

        $query_arr = array();
        if (!$er_id) {
	        $query_arr = array(
	        			'exam_pid' => $exam_pid,
	        			'place_id' => $place_id,
	        			'subject_id' => $subject_id,
	        );
        } else {
        	if ($evaluate_rule['exam_pid'] != $exam_pid ||
        		$evaluate_rule['place_id'] != $place_id ||
        		$evaluate_rule['subject_id'] != $subject_id) {
		        $query_arr = array(
		        			'exam_pid' => $exam_pid,
		        			'place_id' => $place_id,
		        			'subject_id' => $subject_id,
		        );
        	}
        }

        if (count($query_arr)) {
	        $query = $this->db->select('id')->get_where('evaluate_rule', $query_arr);
	        if ($query->num_rows())
	        {
	        	message('该期次->考场->学科已存在');
	        	return;
	        }
        }

        //生成模式
        $generate_uid = 0;
        if ($generate_mode == 1) {
        	$student = array();
        	if (stripos($generate_u_keyword, '@') === false) {
        		$student = StudentModel::get_student_by_exam_ticket($generate_u_keyword);
        	} else {
        		$student = StudentModel::get_student_by_email($generate_u_keyword);
        	}

        	if (!count($student)) {
	        	message('单人模式下 被搜索学生不存在,请检查.');
	        	return;
        	}

        	$generate_uid = $student['uid'];

        	//检查该考生是否在本场考试中
        	if (!$place_id) {
        		$sql = "select count(eps.id) as count
        				from {pre}exam_place_student eps, {pre}exam_place p
        				where eps.place_id=p.place_id and p.exam_pid={$exam_pid} and eps.uid={$generate_uid}";
	        	$result = $this->db->query($sql)->result_array();
        	} else {
        		$sql = "select count(eps.id) as count
        				from {pre}exam_place_student eps, {pre}exam_place p
        				where eps.place_id=p.place_id and eps.place_id={$place_id} and eps.uid={$generate_uid}";
	        	$result = $this->db->query($sql)->result_array();
        	}

        	if (!$result[0]['count']) {
	        	message('该考生未参加当前所选考场中的考试.');
	        	return;
        	}

        	//检查该学生是否作弊
        	$sql = "select count(*) as count from {pre}exam_test_paper where exam_pid={$exam_pid} and uid={$generate_uid} and etp_flag=-1";
        	$result = $this->db->query($sql)->row_array();
        	if ($result['count'] > 0)
        	{
        		message('该考生在本场考试中有作弊行为，无法生成报告');
        		return;
        	}
        }

        //学科->综合->各学科占比检查
        $tmp_subject_percent = array();
        $current_subject_percent = 0;
        $total_subject_percent = 100;
        $subject_id = intval($subject_id);
        if ($subject_id == 0) {
	        if (!is_array($subject_percent) || !count($subject_percent)) {
	        	message('学科->综合->各学科占比总和必须为 100');
	        	return;
	        }

	        foreach ($subject_percent as $k=>$item) {
	        	if (!is_numeric($item) || $item < 0) {
	        		$subject_name = C('subject/'.$k);
		        	message("学科->综合->{$subject_name}占比必须为 [0-100]");
		        	break;
	        	}

	        	$current_subject_percent += $item;
	        	$tmp_subject_percent[] = "{$k}:{$item}";
	        }

	        if ($current_subject_percent != $total_subject_percent) {
	        	message('学科->综合->各学科占比总和必须为 100');
	        	return;
	        }
        }

        $tmp_comparison_level = array();
        foreach ($comparison_level as $item) {
        	$tmp_comparison_level[] = $item;
        }
        $comparison_level = array_unique($tmp_comparison_level);

        //拼接更新数据
        $rule_data = array(
				'name' => $name,
        		'exam_pid' => $exam_pid,
                'contrast_exam_pid' => $contrast_exam_pid,
                'generate_subject_report' => $generate_subject_report,
                'generate_transcript' => $generate_transcript,
                'generate_class_report' => $generate_class_report,
                'generate_teacher_report' => $generate_teacher_report,
                'distribution_proportion' => $distribution_proportion,
        		'place_id' => $place_id,
        		'subject_id' => $subject_id,
        		'comparison_level' => implode(',', $comparison_level),
        		'generate_mode' => $generate_mode,
        		'generate_uid' => $generate_uid,
        		'subject_percent' => implode(',', $tmp_subject_percent),
        );

        //关联一级知识点
        $knowledge_ids = $this->input->post('knowledge_ids');
        $levels = $this->input->post('levels');
        $comments = $this->input->post('comments');
        //$suggests = $this->input->post('suggests');

        !is_array($knowledge_ids) && $knowledge_ids = array();
        !is_array($levels) && $levels = array();
        !is_array($comments) && $comments = array();
//         !is_array($suggests) && $suggests = array();

        $knowledge_data = array();
        foreach ($knowledge_ids as $s_id=>$knowledge) {
        	foreach ($knowledge as $knowledge_id) {
				if (!isset($levels[$s_id][$knowledge_id])
					|| !isset($comments[$s_id][$knowledge_id])
					/*|| !isset($suggests[$s_id][$knowledge_id])*/
	        	) {
					continue;
				}

				$tmp_comments = array();
				foreach ($comments[$s_id][$knowledge_id] as $k=>$item) {
					$knowledge_data[] = array(
							'subject_id' => $s_id,
							'knowledge_id' => $knowledge_id,
							'comment' => trim($item),
							'suggest' => '',//$suggests[$s_id][$knowledge_id][$k],
							'level' => intval($levels[$s_id][$knowledge_id][$k]),
					);
				}
        	}
        }

        //关联方法策略
        $method_tactic_ids = $this->input->post('method_tactic_ids');
        $method_tactic_levels = $this->input->post('method_tactic_levels');
        $method_tactic_comments = $this->input->post('method_tactic_comments');
//      $method_tactic_suggests = $this->input->post('method_tactic_suggests');

        !is_array($method_tactic_ids) && $method_tactic_ids = array();
        !is_array($method_tactic_levels) && $method_tactic_levels = array();
        !is_array($method_tactic_comments) && $method_tactic_comments = array();
//      !is_array($method_tactic_suggests) && $method_tactic_suggests = array();

        //该考场所有的考试学科
        $subject = $this->db->query("select distinct(s.subject_id) as subject_id from {pre}exam e, {pre}subject s where e.exam_pid={$exam_pid} and e.subject_id=s.subject_id")->result_array();
        $subject_ids = array();
        foreach ($subject as $item)
        {
        	$subject_ids[] = $item['subject_id'];
        }

        $method_tactic_data = array();

        //该分类下的所有学科
        $subjects = array();
        foreach ($method_tactic_ids as $s_id=>$method_tactic) {

        	if(!is_numeric($s_id) && !isset($subjects[$s_id]))
        	{
        		$subject_category_id = (int)end(explode("_",$s_id));
        		$subjects[$s_id] = $this->db->query("select subject_id from {pre}subject_category_subject where subject_category_id = {$subject_category_id}")->result_array();
        	}

        	foreach ($method_tactic as $method_tactic_id) {
        		if (!isset($method_tactic_levels[$s_id][$method_tactic_id])
        		|| !isset($method_tactic_comments[$s_id][$method_tactic_id])
        		/*|| !isset($method_tactic_suggests[$s_id][$method_tactic_id])*/
        		) {
        			continue;
        		}

        		$tmp_comments = array();
        		foreach ($method_tactic_comments[$s_id][$method_tactic_id] as $k=>$item) {
        			if (!is_numeric($s_id))
        			{
        				foreach ($subjects[$s_id] as $subject)
        				{
        					if (in_array($subject['subject_id'],$subject_ids))
        					{
        						$method_tactic_data[] = array(
        								'subject_id' => $subject['subject_id'],
        								'method_tactic_id' => $method_tactic_id,
        								'comment' => trim($item),
        								'suggest' => '',//$method_tactic_suggests[$s_id][$method_tactic_id][$k],
        								'level' => intval($method_tactic_levels[$s_id][$method_tactic_id][$k]),
        						);
        					}
        				}
        			}
        			else
        			{
        				$method_tactic_data[] = array(
        						'subject_id' => $s_id,
        						'method_tactic_id' => $method_tactic_id,
        						'comment' => trim($item),
        						'suggest' => '',//$method_tactic_suggests[$s_id][$method_tactic_id][$k],
        						'level' => intval($method_tactic_levels[$s_id][$method_tactic_id][$k]),
        				);
        			}
        		}
        	}
        }

        //关联一级信息提取方式
        $group_type_ids = $this->input->post('group_type_ids');
        $group_type_levels = $this->input->post('group_type_levels');
        $group_type_comments = $this->input->post('group_type_comments');
        //$group_type_suggests = $this->input->post('group_type_suggests');

        !is_array($group_type_ids) && $group_type_ids = array();
        !is_array($group_type_levels) && $levels = array();
        !is_array($group_type_comments) && $group_type_comments = array();
        //!is_array($group_type_suggests) && $group_type_suggests = array();

        $group_type_data = array();
        foreach ($group_type_ids as $s_id=>$group_type) {
            foreach ($group_type as $gr_id) {
                if (!isset($group_type_levels[$s_id][$gr_id])
                || !isset($group_type_comments[$s_id][$gr_id])
                /*|| !isset($group_type_suggests[$s_id][$gr_id])*/
                ) {
                    continue;
                }

                $tmp_comments = array();
                foreach ($group_type_comments[$s_id][$gr_id] as $k=>$item) {
                    $group_type_data[] = array(
                            'subject_id' => $s_id,
                            'group_type_id' => $gr_id,
                            'comment' => trim($item),
                            'suggest' => '',//$group_type_suggests[$s_id][$gr_id][$k],
                            'level' => intval($group_type_levels[$s_id][$gr_id][$k]),
                    );
                }
            }
        }

        //外部对比信息
        $comparison_info = $this->input->post('comparison_info');
        $tmp_comparison_info = array();
        /*
        if (!is_array($comparison_info) || !count($comparison_info))
        {
        	message('请选择外部对比信息');
        }
        */

        foreach ($comparison_info as $item)
        {
        	@list($cmp_type_id, $cmp_info_id) = @explode('_', $item);
        	if (is_null($cmp_type_id) || is_null($cmp_info_id) || !intval($cmp_type_id) || !intval($cmp_info_id))
        	{
        		continue;
        	}

        	$tmp_comparison_info[intval($cmp_type_id)][] = intval($cmp_info_id);
        }

        /*
        if (!count($tmp_comparison_info))
        {
        	message('请选择外部对比信息');
        }
        */

        $rule_data['comparison_info'] = serialize($tmp_comparison_info);

        $res = false;
        if ($er_id <= 0)
        {
            /**
             * 添加时间
             */
            $rule_data['addtime'] = time();

            $res = EvaluateRuleModel::insert($rule_data, $knowledge_data, $method_tactic_data, $group_type_data);

        } else {

            //更新
            $res = EvaluateRuleModel::update($er_id, $rule_data, $knowledge_data, $method_tactic_data, $group_type_data);
        }

        $back_url = 'admin/evaluate_rule/index';
        if (!$res) {
            message('保存失败', $back_url);
        } else {
        	if ($er_id <= 0)
        	{
        		admin_log('add', 'evaluate_rule');
        	}
        	else
        	{
        		admin_log('edit', 'evaluate_rule', $er_id);
        	}
        	$this->session->set_userdata(array('comparison_info'=>''));
        	
            message(' 保存成功', $back_url, 'success');
        }
    }

	/**
	 * 编辑信息
	 *
	 * @param int $id
	 * @return void
	 */
	public function edit($id) {
		if (! $this->check_power ( 'evaluate_rule_manage' ))
			return;

		$data = array ();

		$query = array (
				'id' => $id
		);
		$detail = EvaluateRuleModel::get_evaluate_rule_list ( $query );
		if (! $id || ! count ( $detail )) {
			message ( '不存在评估规则信息' );
			return;
		}

		$detail = $detail [0];
		$detail ['is_reportting'] = $this->rule_is_reportting ( $id );

		// 获取关联知识点
		$query = array (
				'er_id' => $id
		);
		$knowledge_list = EvaluateRuleModel::get_evaluate_knowledge_list ( $query, null, null, null, null, true );

		// 按照 等级 归档
		$tmp_knowledge_list = array ();
		foreach ( $knowledge_list as $k => $v ) {
			$subject_id = $k;
			foreach ( $v as $i ) {
				$knowledge_id = $i ['knowledge_id'];
				$tmp_knowledge_list ['s_' . $subject_id] ['k_' . $knowledge_id] ['l_' . $i ['level']] = array (
						'comment' => $i ['comment'],
						'suggest' => $i ['suggest']
				);
			}
		}

		// 获取关联 方法策略
		$query = array (
				'er_id' => $id
		);
		$method_tactic_list = EvaluateRuleModel::get_evaluate_method_tactic_list ( $query, null, null, null, null, true );

		$method_tactic_ids = array ();
		foreach ( $method_tactic_list as $val ) {
			foreach ( $val as $item ) {
				$method_tactic_ids [] = $item ['method_tactic_id'];
			}
		}
		$method_tactic_ids = array_filter ( $method_tactic_ids );
		$method_tactic_ids = array_unique ( $method_tactic_ids );
		$method_tactic_ids = count ( $method_tactic_ids ) ? $method_tactic_ids : array (
				0
		);
		$method_tactic_ids = implode ( ',', $method_tactic_ids );

		$subject_category = $this->db->query ( "select sc.id as subject_category_id,mt.id as method_tactic_id, mt.name, scs.subject_id from {pre}method_tactic mt, {pre}subject_category_subject scs,{pre}subject_category sc where mt.subject_category_id=scs.subject_category_id and sc.id=scs.subject_category_id and mt.id in ($method_tactic_ids)" )->result_array ();

		// 按照 等级 归档
		$tmp_method_tactic_list = array ();
		foreach ( $method_tactic_list as $k => $v ) {
			$subject_id = $k;
			foreach ( $v as $i ) {
				$method_tactic_id = $i ['method_tactic_id'];
				foreach ( $subject_category as $item ) {
					if ($method_tactic_id == $item ['method_tactic_id']) {
						$tmp_method_tactic_list ['sc_' . $item ['subject_category_id']] ['method_tactic_' . $method_tactic_id] ['l_' . $i ['level']] = array (
								'comment' => $i ['comment'],
								'suggest' => $i ['suggest']
						);
						continue;
					} else {
						$tmp_method_tactic_list ['s_' . $subject_id] ['method_tactic_' . $method_tactic_id] ['l_' . $i ['level']] = array (
								'comment' => $i ['comment'],
								'suggest' => $i ['suggest']
						);
					}
				}
			}
		}

		// 获取关联信息提取方式
		$query = array (
				'er_id' => $id
		);
		$group_type_list = EvaluateRuleModel::get_evaluate_group_type_list ( $query, null, null, null, null, true );

		// 按照 等级 归档
		$tmp_group_type_list = array ();
		foreach ( $group_type_list as $k => $v ) {
			$subject_id = $k;
			foreach ( $v as $i ) {
				$gr_id = $i ['group_type_id'];
				$tmp_group_type_list ['s_' . $subject_id] ['gr_' . $gr_id] ['l_' . $i ['level']] = array (
						'comment' => $i ['comment'],
						'suggest' => $i ['suggest']
				);
			}
		}

		// 获取单人模式下被搜索考生
		$student = array ();
		if ($detail ['generate_mode'] == '1') {
			$student = StudentModel::get_student ( $detail ['generate_uid'] );
		}

		$detail ['generate_u_keyword'] = count ( $student ) ? $student ['email'] : '';

		// 获取当前期次
		$exam = ExamModel::get_exam ( $detail ['exam_pid'], 'exam_id, exam_name' );
		$detail ['exam_name'] = count ( $exam ) ? $exam ['exam_name'] : '该考试期次已经被删除';

		// 获取当前考场
		$place = ExamPlaceModel::get_place ( $detail ['place_id'], 'place_id, place_name' );
		$detail ['place_name'] = is_array ( $place ) && count ( $place ) ? $place ['place_name'] : ($detail ['place_id'] == 0 ? '所有考场' : '该考场已经被删除');

		// 外部对比信息
		$comparison_info = unserialize ( $detail ['comparison_info'] );
		$comparison_info = is_array ( $comparison_info ) ? $comparison_info : array ();
		$detail ['comparison_info'] = $comparison_info;
		$this->session->set_userdata ( array (
				'comparison_info' => $detail ['comparison_info']
		) );
		$data ['detail'] = &$detail;
		$data ['knowledge_list'] = &$tmp_knowledge_list;
		$data ['method_tactic_list'] = &$tmp_method_tactic_list;
		$data ['group_type_list'] = &$tmp_group_type_list;
        $data['comparison_types'] = $this->_get_comparison_info();

        $data['mode'] = 'edit';
        $data['comparison_levels'] = C('evaluate_comparison_level');

        $this->load->view('evaluate_rule/add', $data);
    }

    /**
     * 删除彻底记录
     *
     * @return  void
     */
    public function batch_delete()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;

        $ids = $this->input->get_post('id');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url))
        {
            $back_url = 'admin/evaluate_rule/index';
        }

        EvaluateRuleModel::delete($ids, true);
        admin_log('remove', 'evaluate_rule', implode('、', $ids));

        message('删除成功', $back_url);
    }

    /**
     * 禁用/回收站
     *
     * @return void
     */
    public function do_action()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;

        $id = $this->input->get_post('id');
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url) && ! $id)
        {
            $back_url = 'admin/evaluate_rule/index';
        }

        $act = $this->input->get_post('act');
        $res = TRUE;
        if ($act == '1') {
            //回收站
            $res = EvaluateRuleModel::delete($id);
            admin_log('delete', 'evaluate_rule', (is_array($id) ? implode('、', $id) : $id));

        } elseif ($act == '2') {
            //还原
            $res = EvaluateRuleModel::update($id, array('is_delete' => '0'));
            admin_log('restore', 'evaluate_rule', (is_array($id) ? implode('、', $id) : $id));
        }

        if ($res) {
            message('操作成功', $back_url);
        } else {
            message('操作失败', $back_url);
        }
    }




    /**
     * pdf生成状态
     *
     * @return void
     */
    public function pdf_status()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;


    	$this -> db ->trans_start();


    	$id = $this->input->get_post('id');
		//pdf总数
    	$sql = "SELECT COUNT(id) as c FROM {pre}convert2pdf where rule_id = '$id' and status=1 and is_success=1";
    	$res = $this->db->query($sql)->row_array();
    	$count0=$res['c'];

    	//学生总数
    	$sql = "SELECT COUNT(distinct uid) as c FROM {pre}convert2pdf where rule_id = '$id' ";
    	$res = $this->db->query($sql)->row_array();
    	$count1=$res['c'];

    	//已成功生成的PDF数
    	$sql = "SELECT COUNT(1) as c FROM {pre}convert2pdf where rule_id = '$id'  and num=1 and status=1 and is_success=1";
    	$res = $this->db->query($sql)->row_array();
    	$count2=$res['c'];

    	//生成成功的PDF中num>1的数量
    	$sql = "SELECT COUNT(1) as c FROM {pre}convert2pdf where rule_id = '$id' and num>1 and status=1 and is_success=1";
    	$res = $this->db->query($sql)->row_array();
    	$count3=$res['c'];
    	$this -> db ->trans_complete();

    	$data = array();
    	$data['count0']=$count0;
    	$data['count1']=$count1;
    	$data['count2']=$count2;
    	$data['count3']=$count3;
    	$data['id']=$id;
    	$this->load->view('evaluate_rule/pdf_status',$data);
    }




    /**
     * pdf任务分配
     *
     * @return void
     */
    public function pdf_ip_c()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;

    	$proc_id = $this->input->get_post('proc_id');
        $proc_ids = explode(',', $proc_id);
    	//$count = count($proc_ids);


    	foreach ($proc_ids as $v)
    	{
    		if(intval($v)==false)
    		{
    			message('分配失败，请检查输入的整数正确性','javascript');
    			die;
    		}
    	}
    	// $proc_id_str
    	$proc_id_str = $this->input->get_post('proc_id');
    	$proc_id_arr = array();
    	foreach (explode(',', $proc_id_str) as $k => $v)
    	{
    	    $v = trim($v);
    	    if (is_numeric($v) && intval($v) == $v && $v > 0)
    	    {
    	        $proc_id_arr[] = $v;
    	    }
    	}
    	$proc_id_arr = array_values(array_unique($proc_id_arr));

    	$proc_num = count($proc_id_arr);

    	$this -> db ->trans_start();
    	$rule_id = $this->input->get_post('id');


    	$sql = <<<EOT
DELETE FROM rd_convert2pdf_uidprocmap WHERE rule_id = {$rule_id}
EOT;

    	$this->db->query($sql);

    	$sql = <<<EOT
INSERT INTO rd_convert2pdf_uidprocmap(rule_id, uid, proc_idx)
SELECT {$rule_id} AS rule_id, a.uid,
    ((@rank := @rank + 1) MOD {$proc_num}) AS proc_idx
FROM (SELECT DISTINCT uid FROM rd_convert2pdf WHERE rule_id = {$rule_id}) a,
    (SELECT @rank := -1) b
ORDER BY uid
EOT;
    	$this->db->query($sql);

foreach ($proc_id_arr as $proc_idx => $proc_id)
{
    $sql = <<<EOT
    	    UPDATE rd_convert2pdf_uidprocmap SET proc_id = {$proc_id}
WHERE rule_id = {$rule_id} AND proc_idx = {$proc_idx}
EOT;
    $this->db->query($sql);
}


$sql = <<<EOT
UPDATE rd_convert2pdf a, rd_convert2pdf_uidprocmap b
SET  a.proc_id = b.proc_id
WHERE a.rule_id = {$rule_id} AND b.rule_id = {$rule_id} AND a.uid = b.uid
EOT;
$this->db->query($sql);

	if($this -> db ->trans_complete())
    	{
    		message('分配成功','javascript');
    		die;
    	}
    	else
    	{
    		message('分配失败','javascript');
    		die;
    	}


    }


    /**
     * PDF生成检查处理
     *
     * @return void
     */
    public function pdf_check_c()
    {
    	if (!$this->check_power('evaluate_rule_manage')) return;
    	$data = array();
    	$id = $this->input->get_post('id');


	    	$this -> db ->trans_start();

	    	//设置处理失败的状态
    		$sql = "UPDATE {pre}convert2pdf SET status=0,num=0,is_success=0 WHERE  status=1 and num>1 and is_success=1 and rule_id='{$id}'";
    		$this->db->query($sql);
    		if($this -> db ->trans_complete())
    		{
    			message('处理成功','javascript');
    			die;
    		}
    		else
    		{
    			message('处理失败','javascript');
    			die;
    		}

    }

    /**
     * 获取已启用的考试期次
     *
     * @return  void
     */
    public function get_exams()
    {
    	$query = array(
//     					'status' => '1',
    					'exam_pid' => '0'
    	);

        $this->db->order_by('exam_id', 'desc');
    	$exams = $this->db->select("exam_id, exam_name")->get_where('exam', $query)->result_array();

    	output_json(CODE_SUCCESS, '', $exams);
    }

    /**
     * 获取某个期次下的所有考场
     *
     * @return  void
     */
    public function get_exam_places()
    {
    	$exam_pid = intval($this->input->get('exam_pid'));
    	if (!$exam_pid) {
	    	output_json(CODE_SUCCESS, '', array());
    	}

    	$query = array(
    					'exam_pid' => $exam_pid
    	);

    	$exam_places = $this->db->select("place_id, place_name, address")->get_where('exam_place', $query)->result_array();

    	$data = array();
    	foreach ($exam_places as $exam_place) {
    		$data[] = array(
    						'place_id'   => $exam_place['place_id'],
    						'place_name' => $exam_place['place_name'] . '(地点:' . $exam_place['address'] . ')',
    		);
    	}

    	output_json(CODE_SUCCESS, '', $data);
    }

    /**
     * 获取某个考场下的考试科目
     *
     * @return  void
     */
    public function get_exam_place_subjects()
    {
    	$exam_pid = intval($this->input->get('exam_pid'));
    	$place_id = trim($this->input->get('place_id'));

    	if (!$exam_pid || $place_id == '') {
	    	output_json(CODE_SUCCESS, '', array());
    	}
    	
    	//$where_str = " AND s.subject_id NOT IN (13, 14, 15, 16)";

    	if (intval($place_id) > 0) {
    		$sql = "select distinct(s.subject_name) as subject_name, s.subject_id as subject_id 
    				from {pre}exam_place_subject eps, {pre}subject s 
    		        where eps.subject_id=s.subject_id  and eps.exam_pid={$exam_pid} 
    		        and eps.place_id={$place_id}";
    	} else {
    		$sql = "select distinct(s.subject_name) as subject_name, s.subject_id as subject_id 
    		        from {pre}exam e, {pre}subject s 
    		        where e.exam_pid={$exam_pid} and e.subject_id=s.subject_id";
    	}
    	
    	//$sql .= $where_str;

    	$data = $this->db->query($sql)->result_array();


    	output_json(CODE_SUCCESS, '', $data);
    }

    /**
     * 获取某个考场下的考试科目所考到的试卷涉及到的一级知识点
     *
     * @return  void
     */
    public function get_exam_place_subject_knowledge()
    {
    	$place_id = trim($this->input->get('place_id'));
    	$subject_id = trim($this->input->get('subject_id'));
    	if ($place_id == '' || $subject_id == '') {
	    	output_json(CODE_SUCCESS, '', array());
    	}

    	//获取考试科目
    	$result = $this->db->query("select exam_id from {pre}exam_place_subject where place_id in($place_id) and subject_id in ($subject_id)")->result_array();
    	$exam_ids = array();
    	foreach ($result as $item) {
    		$exam_ids[] = $item['exam_id'];
    	}
    	$exam_id = implode(',', $exam_ids);
    	$exam_id = $exam_id == '' ? '0' : $exam_id;
    	$sql = array(
    			"select distinct(q.knowledge), q.type, q.ques_id",
				"from {pre}exam_subject_paper esp",
				"inner join {pre}exam_question eq on eq.paper_id=esp.paper_id",
				"inner join {pre}question q on (q.ques_id=eq.ques_id OR q.parent_id=eq.ques_id)",
				"where esp.exam_id in ({$exam_id})",
    	);

    	$result = $this->db->query(implode(' ', $sql))->result_array();
    	$knowledge_ids = array();
    	//收集题组试题
    	$group_ids = array();
    	foreach ($result as $item) {
    		//if (in_array($item['type'],array(0, 4, 5, 6))) $group_ids[] = $item['ques_id'];
    		$knowledge = explode(',', $item['knowledge']);
    		if (!is_array($knowledge) || !count($knowledge)) {
    			continue;
    		}

    		foreach ($knowledge as $item) {
	    		$knowledge_ids[] = $item;
    		}
    	}

    	//获取题组的知识点
    	/*
    	if (count($group_ids))
    	{
	    	$sql = "select knowledge from {pre}question where parent_id in(".implode(',', $group_ids).")";
	    	$result = $this->db->query($sql)->result_array();
	    	foreach ($result as $item) {
	    		$knowledge = explode(',', $item['knowledge']);
	    		if (!is_array($knowledge) || !count($knowledge)) {
	    			continue;
	    		}

	    		foreach ($knowledge as $v) {
	    			$knowledge_ids[] = $v;
	    		}
	    	}
    	}
    	*/

    	$knowledge_ids = array_filter($knowledge_ids);
    	$knowledge_ids = array_unique($knowledge_ids);
    	$knowledge_ids = count($knowledge_ids) ? $knowledge_ids : array(0);

    	//提取一级知识点
    	$data = $this->db->select('pid,knowledge_name,subject_id')->where_in('id', $knowledge_ids)->get('knowledge')->result_array();
    	$list = array();
    	foreach ($data as $item) {
    		$knowledge_name = $this->db->select('knowledge_name')->where('id', $item['pid'])->get('knowledge')->result_array();
    		$list[$item['subject_id']][$item['pid']] = array('subject_name' => C('subject/' . $item['subject_id']), 'id' => $item['pid'], 'knowledge_name' => $knowledge_name[0]['knowledge_name']);
    	}

    	output_json(CODE_SUCCESS, '', $list);
    }

    /**
     * 获取某个考场下的考试科目所考到的试卷涉及到的 方法策略
     *
     * @return  void
     */
    public function get_exam_place_subject_method_tactic()
    {
    	$place_id = trim($this->input->get('place_id'));
    	$subject_id = trim($this->input->get('subject_id'));
    	if ($place_id == '' || $subject_id == '') {
	    	output_json(CODE_SUCCESS, '', array());
    	}
    	//获取考试科目
    	$result = $this->db->query("select exam_id from {pre}exam_place_subject 
    	    where place_id in($place_id) and subject_id in ($subject_id)")->result_array();
    	$exam_ids = array();
    	foreach ($result as $item) {
    		$exam_ids[] = $item['exam_id'];
    	}
    	$exam_id = implode(',', $exam_ids);
    	$exam_id = $exam_id == '' ? '0' : $exam_id;
    	$sql = array(
    			"select distinct(q.method_tactic), q.type, q.ques_id",
				"from {pre}exam_subject_paper esp",
				"inner join {pre}exam_question eq on eq.paper_id=esp.paper_id",
				"inner join {pre}question q on (q.ques_id=eq.ques_id OR q.parent_id=eq.ques_id)",
				"where esp.exam_id in ({$exam_id})",
    	);

    	$result = $this->db->query(implode(' ', $sql))->result_array();
    	$method_tactic_ids = array();
    	//收集题组试题
    	$group_ids = array();
    	foreach ($result as $item) {
    		//if (in_array($item['type'],array(0, 4, 5, 6))) $group_ids[] = $item['ques_id'];
    		$method_tactic = explode(',', $item['method_tactic']);
    		if (!is_array($method_tactic) || !count($method_tactic)) {
    			continue;
    		}

    		foreach ($method_tactic as $val) {
	    		$method_tactic_ids[] = $val;
    		}
    	}

    	//获取题组的方法策略
    	/*
    	if (count($group_ids))
    	{
    		$sql = "select method_tactic from {pre}question where parent_id in(".implode(',', $group_ids).")";
    		$result = $this->db->query($sql)->result_array();
    		foreach ($result as $item) {
    			$method_tactic = explode(',', $item['method_tactic']);
    			if (!is_array($method_tactic) || !count($method_tactic)) {
    				continue;
    			}

    			foreach ($method_tactic as $v) {
    				$method_tactic_ids[] = $v;
    			}
    		}
    	}
    	*/

    	$method_tactic_ids = array_filter($method_tactic_ids);
    	$method_tactic_ids = array_unique($method_tactic_ids);
    	$method_tactic_ids = count($method_tactic_ids) ? $method_tactic_ids : array(0);

    	//提取方法策略
    	$method_tactic_ids = implode(',', $method_tactic_ids);
    	/*
     	$result = $this->db->query("select mt.id, mt.name, scs.subject_id
               from {pre}method_tactic mt, {pre}subject_category_subject scs
               where mt.subject_category_id=scs.subject_category_id and mt.id in ($method_tactic_ids) and scs.subject_id in ($subject_id)")->result_array();
    	$data = array();
    	foreach ($result as $item) {
    		$data[$item['subject_id']][] = array('id' => $item['id'], 'name' => $item['name']);
    	}
    	*/

    	$subject = $this->db->query("select distinct(subject_id) from {pre}exam_place_subject where place_id in($place_id)")->result_array();
    	$subject_ids = array();
    	foreach ($subject as $item)
    	{
    		$subject_ids[] = $item['subject_id'];
    	}

    	$sql = "select sc.id as subject_category_id,mt.id, mt.name, scs.subject_id
    	       from {pre}method_tactic mt, {pre}subject_category_subject scs,{pre}subject_category sc
    	       where mt.subject_category_id=scs.subject_category_id and sc.id=scs.subject_category_id and mt.id in ($method_tactic_ids) and scs.subject_id in ($subject_id)";
        $subject_category = $this->db->query($sql)->result_array();

    	$data = array();

    	foreach ($subject_category as $key => $val)
    	{
    		$subject_category_id = $val['subject_category_id'];
    		$subject_category_subject = $this->db->query("select subject_id from {pre}subject_category_subject where subject_category_id = {$subject_category_id}")->result_array();
    		$tmp_subject_name = array();
    		if ($subject_category_subject)
    		{
    			foreach ($subject_category_subject as $subject_id)
    			{
    				if(in_array($subject_id['subject_id'],$subject_ids))
    				{
    					$tmp_subject_name[] = C('subject/' . $subject_id['subject_id']);
    				}
    			}
    		}
    		$now_subject_category = array('subject_name'=>implode("、",$tmp_subject_name),'id'=>$val['id'],'name'=>$val['name'],'subject_category_id'=>$subject_category_id);
    		$data["sc_".$subject_category_id][$val['id']] = $now_subject_category;
    	}

    	output_json(CODE_SUCCESS, '', $data);
    }

    /**
     * 获取某个考场下的考试科目所考到的试卷涉及到的 信息提取方式
     *
     * @return  void
     */
    public function get_exam_place_subject_group_type()
    {
    	$place_id = trim($this->input->get('place_id'));
    	$subject_id = trim($this->input->get('subject_id'));
    	if ($place_id == '' || $subject_id == '' || $subject_id != 3) {
	    	output_json(CODE_SUCCESS, '', array());
    	}

    	//获取考试科目
    	$result = $this->db->query("select exam_id from {pre}exam_place_subject where place_id in($place_id) and subject_id in ($subject_id)")->result_array();
    	$exam_ids = array();
    	foreach ($result as $item) {
    		$exam_ids[] = $item['exam_id'];
    	}
    	$exam_id = implode(',', $exam_ids);
    	$exam_id = $exam_id == '' ? '0' : $exam_id;
    	$sql = array(
    			"select distinct(q.group_type), q.type, q.ques_id",
				"from {pre}exam_subject_paper esp",
				"inner join {pre}exam_question eq on eq.paper_id=esp.paper_id",
				"inner join {pre}question q on (q.ques_id=eq.ques_id OR q.parent_id=eq.ques_id)",
				"where esp.exam_id in ({$exam_id})",
    	);

    	$result = $this->db->query(implode(' ', $sql))->result_array();
    	$group_type_ids = array();
    	//收集题组试题
    	$group_ids = array();
    	foreach ($result as $item) {
    		//if (in_array($item['type'], array(0, 4, 5, 6))) $group_ids[] = $item['ques_id'];
    		$group_type = explode(',', $item['group_type']);
    		if (!is_array($group_type) || !count($group_type)) {
    			continue;
    		}

    		foreach ($group_type as $item) {
	    		$group_type_ids[] = $item;
    		}
    	}

    	//获取题组的信息提取方式
    	/*
    	if (count($group_ids))
    	{
	    	$sql = "select ques_id, group_type from {pre}question where parent_id in(".implode(',', $group_ids).")";
	    	$result = $this->db->query($sql)->result_array();
	    	foreach ($result as $item) {
	    		$group_type = explode(',', $item['group_type']);
	    		if (!is_array($group_type) || !count($group_type)) {
	    			continue;
	    		}

	    		foreach ($group_type as $v) {
	    			$group_type_ids[] = $v;
	    		}
	    	}
    	}
    	*/

    	$group_type_ids = array_filter($group_type_ids);
    	$group_type_ids = array_unique($group_type_ids);
    	$group_type_ids = count($group_type_ids) ? $group_type_ids : array(0);


    	//提取信息提取方式
    	$data = $this->db->select('pid,group_type_name,subject_id')->where_in('id', $group_type_ids)->get('group_type')->result_array();
    	$list = array();
    	foreach ($data as $item) {
    		$group_type_name = $this->db->select('group_type_name')->where('id', $item['pid'])->get('group_type')->row_array();
    		$list[$item['subject_id']][$item['pid']] = array('subject_name' => C('subject/' . $item['subject_id']), 'id' => $item['pid'], 'group_type_name' => $group_type_name['group_type_name']);
    	}

    	output_json(CODE_SUCCESS, '', $list);
    }

    /**
     * 获取某期考试 所考到的所有考场 按照地域分类，从高->低
     * 关联表：rd_region rd_exam_place rd_school
     *
     * @return  void
     */
    public function get_exam_areas()
    {
    	$exam_pid = intval($this->input->get('exam_pid'));
    	$place_id = $this->input->get('place_id');
    	if (!$exam_pid || $place_id == '') {
	    	output_json(CODE_SUCCESS, '', array());
    	}

    	if (!$place_id) {
	    	$result = $this->db->query("select s.* from {pre}school s, {pre}exam_place p where p.exam_pid={$exam_pid} and p.school_id=s.school_id group by school_id")->result_array();
    	} else {
	    	$result = $this->db->query("select s.* from {pre}school s, {pre}exam_place p where p.exam_pid={$exam_pid} and p.place_id={$place_id} and p.school_id=s.school_id group by school_id")->result_array();
    	}

    	//按照地域级别深度 进行归档
    	/*
    	 * 依次是 国家->省->地市->区县
    	 */
    	$region_ids = array();
    	foreach ($result as $item) {
    		$region_ids[] = $item['province'];
    		$region_ids[] = $item['city'];
    		$region_ids[] = $item['area'];
    	}

    	$data = array();

    	//获取地域名称
    	$region_ids = array_unique($region_ids);
    	if (count($region_ids))
    	{
	    	$region_ids = implode(', ', $region_ids);
	    	$region_result = $this->db->query("select region_type from {pre}region where region_id in($region_ids) group by region_type order by region_type desc limit 0,10")->result_array();
	    	$region_types = array_merge(C('region_type'), array('100' => '学校'));
	    	foreach ($region_result as $val) {
	    		$data[$val['region_type']] = $region_types[$val['region_type']];
	    	}
    	}

    	$data['-1'] = '所有考试人员';
    	$data['0'] = '国家';
    	$data['100'] = '学校';

    	ksort($data);

    	$output = array();

    	//拼接已有的对比等级
    	$get_comparison_level = urldecode($this->input->get('comparison_level'));
    	$comparison_level = explode(',', $get_comparison_level);
    	if ($get_comparison_level == '') {
    		$comparison_level = array_filter($comparison_level);
    	}
    	$count = (!is_array($comparison_level) || !count($comparison_level)) ? 1 : count($comparison_level);

    	for ($i = 0; $i < $count; $i++) {
    		$output[] = '<p class="c_l_p" style="margin-bottom:5px;">';
    		$output[] = "	<select name='comparison_level[]' class='select_comparison_level'>";
    		$output[] = "		<option value=''>== 请选择 ==</option>";
    		foreach ($data as $k=>$v) {
    			if (isset($comparison_level[$i]) && $k == $comparison_level[$i]) {
		    		$output[] = "<option selected='selected' value='{$k}'>{$v}</option>";
    			} else {
		    		$output[] = "<option value='{$k}'>{$v}</option>";
    			}
    		}
    		$output[] = "	</select>";
    		if ($i == 0) {
	    		$output[] = '<input class="add" type="button" value="添加" style="margin-left:10px;" id="btn_add_area"/>';
	    		$output[] = '<input class="remove" type="button" value="删除" style="margin-left:10px;display:none;" onclick="$(this).parent().remove();"/>';
    		} else {
	    		$output[] = '<input class="remove" type="button" value="删除" style="margin-left:10px;" onclick="$(this).parent().remove();"/>';
    		}
    		$output[] = "</p>";
    	}


    	output_json(CODE_SUCCESS, '', implode('', $output));
    }


    /**
     * 添加评估规则信息获得对应的对比信息数据
     *
     */
    function get_comparison_info()
    {
    	$comparison_info = $this->session->userdata('comparison_info');
        $tmp_comparison_info = array();

        if(isset($comparison_info))
        {
            $comparison_info = is_array($comparison_info) ? $comparison_info : array();
            $tmp_comparison_info = array();

            if (count($comparison_info))
            {
                foreach ($comparison_info as $cmp_type_id=>$info)
                {
                    foreach ($info as $cmp_info_id)
                    {
                        $tmp_comparison_info[] = $cmp_type_id . '_' . $cmp_info_id;
                    }
                }
            }
        }

        $subject_id = intval($this->input->get('subject_id'));
        $place_id = intval($this->input->get('place_id'));
        $exam_pid = intval($this->input->get('exam_pid'));

        //获得其次考场下的学科id
        $sql = "SELECT b.subject_id FROM {pre}exam_place a";
        $sql .= " LEFT JOIN {pre}exam b";
        $sql .= ' ON a.exam_pid = b.exam_pid';
        $sql .= ' WHERE a.exam_pid = ' . $exam_pid;

        if ($place_id)
        {
            $sql .= ' AND a.place_id = ' . $place_id;
        }

        $result = $this->db->query($sql)->result_array();
        $subject_ids = array();

        foreach($result as $val)
        {
           $subject_ids[] = $val['subject_id'] ;
        }

        if($subject_id >= 0)
        {
            $sql = "SELECT ct.cmp_type_id, ct.cmp_type_name, ci.cmp_info_id, ci.cmp_info_year
                FROM {pre}comparison_type ct
                LEFT JOIN {pre}comparison_info ci ON ct.cmp_type_id=ci.cmp_type_id
                WHERE ct.cmp_type_flag=1";

            if($subject_id)
            {
                $sql .= " AND ct.subject_id = {$subject_id} " ;
            }
            else
            {
                //选择总结
                $subject_id_str = implode(',', $subject_ids);
                $sql .= " AND ct.subject_id IN ({$subject_id_str})" ;
            }

            $sql .= " ORDER BY  ct.cmp_type_id DESC, ci.cmp_info_year ASC";

            $result = $this->db->query($sql)->result_array();
            $data = array();

            foreach ($result as $item)
            {
                $data[$item['cmp_type_id']]['id'] = $item['cmp_type_id'];
                $data[$item['cmp_type_id']]['name'] = $item['cmp_type_name'];

                if (!$item['cmp_info_id'] || !$item['cmp_info_year'])
                {
                    continue;
                }
                $data[$item['cmp_type_id']]['info'][] = array(
                    'id' => $item['cmp_info_id'],
                    'year' => $item['cmp_info_year'],
                );

            }

            if (count($data))
            {
                //将没有对比信息的 对比类型过滤
                foreach ($data as $k=>$item)
                {
                    if (!isset($item['info']) || !count($item['info']))
                    {
                        unset($data[$k]);
                    }
                }
            }

            $output = array();
            $output[]='<div class="box"><ul>';

            foreach ($data as $comparison_type)
            {

                $cmp_type_id = $comparison_type['id'];
                $cmp_type_name = $comparison_type['name'];
                $output[] = '<li>';
                $output[] ="<span class='title'> {$comparison_type['name']}：</span>";
                $output[] = '<span>';

                foreach ($comparison_type['info'] as $info)
                {
                    $k = $cmp_type_id . '_' . $info['id'];
                    $output[] = "<input type='checkbox'";

                    $output[] ="name='comparison_info[]' class='comparison_info' value='{$k}' ";
                    if(in_array($k, $tmp_comparison_info))
                        $output[] ="checked=checked";
                    else
                        $output[] ="";

                    $output[] =" id='cmp_{$k}'/>";
                    $output[] = "<label for='cmp_{$k}'>{$info['year']}年</label>&nbsp;&nbsp;";
                }

                $output[] = '</span>';
                $output[] = '</li>';
            }

            $output[] = '</ul>';
            $output[] = '</div>';

            output_json(CODE_SUCCESS, '', implode('', $output));
        }
    }

    /**
     * 获取某期考试 所考到的所有考场 按照地域分类，从高->低
     * 关联表：rd_region rd_exam_place rd_school
     *
     * @return  void
     */
    private function get_exam_areas_bak()
    {
        $exam_pid = intval($this->input->get('exam_pid'));
        $place_id = $this->input->get('place_id');
        if (!$exam_pid || $place_id == '') {
            output_json(CODE_SUCCESS, '', array());
        }

        if (!$place_id) {
            $result = $this->db->query("select s.* from {pre}school s, {pre}exam_place p where p.exam_pid={$exam_pid} and p.school_id=s.school_id group by school_id")->result_array();
        } else {
            $result = $this->db->query("select s.* from {pre}school s, {pre}exam_place p where p.exam_pid={$exam_pid} and p.place_id={$place_id} and p.school_id=s.school_id group by school_id")->result_array();
        }

        //按照地域级别深度 进行归档
        /*
         * 依次是 国家->省->地市->区县
         */
        $region_ids = array();
        foreach ($result as $item) {
            $region_ids[] = $item['province'];
            $region_ids[] = $item['city'];
            $region_ids[] = $item['area'];
        }

        $region_ids = array_unique($region_ids);

        //获取地域名称
        $region_ids = implode(', ', $region_ids);
        $region_result = $this->db->query("select region_id, region_name, region_type, parent_id from {pre}region where region_id in($region_ids)")->result_array();
        $regions = array();
        $addresses = array();
    	$parent_regions = array();
    	foreach ($region_result as $val) {
    		$regions[$val['region_id']] = array('name' => $val['region_name'], 'pid' => $val['parent_id']);
    		$addresses[] = array($val['region_id'], $val['region_name'], $val['parent_id']);
    		$parent_regions[$val['parent_id']][] = $val['region_id'];
    	}

    	$data = array(
    		'region' => array(
				    		array(
				    				'region_type' => '0',
				    				'id' => 'select_country',
				    				'depth' => '0',
				    				'name' => 'comparison_level_region',
				    				'onchange' => "region.changed(this, 1, 'select_province{depth}');region.changed(document.getElementById('select_province{depth}'), 1, 'select_city{depth}');region.changed(document.getElementById('select_city{depth}'), 1, 'select_area{depth}');region.changed(document.getElementById('select_area{depth}'), 1, 'select_school{depth}');",
				    				'data' => array(
				    								array(
									    				'name' => '--请选择国家--',
									    				'region_id' => '0'
				    						 		),
				    								array(
									    				'name' => '中国',
									    				'region_id' => '1'
				    						 		),
				    						)
				    		)
    				)
    	);

    	//当前对比等级
    	$comparison_level = urldecode($this->input->get('comparison_level'));
    	$comparison_level = (Array)json_decode($comparison_level);
    	$tmp_c_l = array();
    	foreach ($comparison_level as $val) {
    		$tmp_c_l[] = (Array)$val;
    	}
    	$comparison_level = is_array($tmp_c_l) ? $tmp_c_l : array();

    	$cl_region = isset($comparison_level[0]['region']) ? $comparison_level[0]['region'] : '';
    	$cl_school = isset($comparison_level[0]['school']) ? $comparison_level[0]['school'] : 0;

    	$cl_country = isset($cl_region[0]) ? $cl_region[0] : 0;
    	$cl_province = isset($cl_region[1]) ? $cl_region[1] : 0;
    	$cl_city = isset($cl_region[2]) ? $cl_region[2] : 0;
    	$cl_area = isset($cl_region[3]) ? $cl_region[3] : 0;

    	$provinces = array(array('name' => '--请选择省--', 'region_id' => '0', 'pid' => '0'));
    	$cities = array(array('name' => '--请选择市--', 'region_id' => '0', 'pid' => '0'));
    	$areas = array(array('name' => '--请选择区县--', 'region_id' => '0', 'pid' => '0'));
    	$schools = array(array('name' => '--请选择学校--', 'school_id' => '0', 'pid' => '0'));
    	foreach ($result as $item) {
    		$cl_country && $provinces[] = array('name' => isset($regions[$item['province']]) ? $regions[$item['province']]['name'] : '--', 'region_id' => $item['province'], 'pid' => isset($regions[$item['province']]) ? $regions[$item['province']]['pid'] : '0');
    		$cities[] = array('name' => isset($regions[$item['city']]) ? $regions[$item['city']]['name'] : '--', 'region_id' => $item['city'], 'pid' => isset($regions[$item['city']]) ? $regions[$item['city']]['pid'] : '0');
    		$areas[] = array('name' => isset($regions[$item['area']]) ? $regions[$item['area']]['name'] : '--', 'region_id' => $item['area'], 'pid' => isset($regions[$item['area']]) ? $regions[$item['area']]['pid'] : '0');
    		$schools[] = array('name' => $item['school_name'], 'school_id' => $item['school_id'], 'pid' => $item['area']);

    		//id需要加1000 为了区分区域
    		$addresses[] = array((100000+$item['school_id']), $item['school_name'], $item['area']);
    	}

    	//地域
    	$data['region'][] = array(
    	    'region_type' => '1',
    	    'id' => 'select_province',
    	    'depth' => '1',
    	    'name' => 'comparison_level_region',
    	    'onchange' => "region.changed(this, 2, 'select_city{depth}');region.changed(document.getElementById('select_city{depth}'), 2, 'select_area{depth}');region.changed(document.getElementById('select_area{depth}'), 2, 'select_school{depth}');",
    	    'data' => $provinces
    	);
    	$data['region'][] = array(
    	    'region_type' => '2',
    	    'id' => 'select_city',
    	    'depth' => '2',
    	    'name' => 'comparison_level_region',
    	    'onchange' => "region.changed(this, 3, 'select_area{depth}');region.changed(document.getElementById('select_area{depth}'), 3, 'select_school{depth}');",
    	    'data' => $cities
    	);
    	$data['region'][] = array(
    	    'region_type' => '3',
    	    'id' => 'select_area',
    	    'depth' => '3',
    	    'name' => 'comparison_level_region',
    	    'onchange' => "region.changed(this, 4, 'select_school{depth}');", 'data' => $areas);

    	//学校
    	$data['school'] = array('id' => 'select_school', 'depth' => '4', 'name' => 'comparison_level_school', 'data' => $schools);

    	//所有地区缓存
    	$data['address'] = $addresses;

    	output_json(CODE_SUCCESS, '', $data);
    }

    //========================外部对比信息=============================//

    /**
     * 获取外部对比信息
     *
     * @return  void
     */
    private function _get_comparison_info()
    {
		 $sql = "select ct.cmp_type_id, ct.cmp_type_name, ci.cmp_info_id, ci.cmp_info_year
		 		 from {pre}comparison_type ct
		 		 left join {pre}comparison_info ci on ct.cmp_type_id=ci.cmp_type_id
		 		 where ct.cmp_type_flag=1
		 		 order by ct.cmp_type_id desc, ci.cmp_info_year asc
		 		";

		 $result = $this->db->query($sql)->result_array();
		 $data = array();

		 foreach ($result as $item)
		 {
		 	$data[$item['cmp_type_id']]['id'] = $item['cmp_type_id'];
		 	$data[$item['cmp_type_id']]['name'] = $item['cmp_type_name'];
		 	if (!$item['cmp_info_id'] || !$item['cmp_info_year'])
		 	{
		 		continue;
		 	}
		 	$data[$item['cmp_type_id']]['info'][] = array(
										 				'id' => $item['cmp_info_id'],
										 				'year' => $item['cmp_info_year'],
		 											);

		 }

		 if (count($data))
		 {
			 //将没有对比信息的 对比类型过滤
			 foreach ($data as $k=>$item)
			 {
			 	if (!isset($item['info']) || !count($item['info']))
			 	{
			 		unset($data[$k]);
			 	}
			 }
		 }

		 return $data;
    }

    /**
     * 生成测评报告
     *
     * @return  void
     */
    public function general($rule_id = 0)
    {

        if (!$this->check_power('evaluate_rule_manage')) return;

    	$rule_id = intval($rule_id);

    	$template_ids = $this->input->post('template_id');
    	
    	$rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
    	if (empty($rule))
    	{
    	    message('不存在该评估规则');
    	}

    	if (!$template_ids 
    	    && ($rule['generate_subject_report'] 
    	        || $rule['generate_class_report']
    	        || $rule['generate_teacher_report']))
    	{
    	    redirect('/admin/evaluate_rule/general_template/' . $rule_id);
    	}
        
    	$this->load->model('cron/report/task_report_model');
    	$task = $this->task_report_model->get_task($rule_id);
    	if (isset($task['status']) && ($task['status'] == '0' || $task['status'] == '1'))
    	{
    		message('该规则报告正在处理中，请耐心等待.');
    	}

    	$subject = C('subject');

    	//评估规则中包含的学科
    	$rule_subject = array();
    	$message = array();

    	if ($rule['subject_id'] > 0)
    	{

    	    if ($rule['generate_subject_report']
    	        && (!isset($template_ids[$rule['subject_id']])
    	        || $template_ids[$rule['subject_id']] < 1))
    	    {
    	        $message[] = '请选择学生' . $subject[$rule['subject_id']] .'学科报告模板';
    	    }
    	    
    	    if ($rule['generate_class_report']
	            && (!isset($template_ids['cls_' . $rule['subject_id']])
	                || $template_ids['cls_' . $rule['subject_id']] < 1))
    	    {
    	        $message[] = '请选择班级' . $subject[$rule['subject_id']] .'学科报告模板';
    	    }
    	    
    	    if ($rule['generate_teacher_report']
    	        && (!isset($template_ids['teacher_' . $rule['subject_id']])
    	            || $template_ids['teacher_' . $rule['subject_id']] < 1))
    	    {
    	        $message[] = '请选择教师' . $subject[$rule['subject_id']] .'学科报告模板';
    	    }
    	}
    	else
    	{
    	    if ($rule['generate_subject_report']
    	        && !isset($template_ids[0]))
	        {
	            $message[] = '请选择学生总结报告模板';
	        }

    	    $rule_subjects = explode(',', $rule['subject_percent']);
    	    foreach ($rule_subjects as $val)
    	    {
    	        $t_subject_id = current(explode(':', $val));

    	        if ($rule['generate_subject_report']
    	            && (!isset($template_ids[$t_subject_id])
    	               || $template_ids[$t_subject_id] < 1))
    	        {
    	            $message[] = '请选择学生' . $subject[$t_subject_id] .'学科报告模板';
    	        }
    	        
    	        if ($rule['generate_class_report']
    	            && (!isset($template_ids['cls_' . $t_subject_id])
    	                || $template_ids['cls_' . $t_subject_id] < 1))
    	        {
    	            $message[] = '请选择班级' . $subject[$t_subject_id] .'学科报告模板';
    	        }
    	        
    	        if ($rule['generate_teacher_report']
    	            && (!isset($template_ids['teacher_' . $t_subject_id])
    	                || $template_ids['teacher_' . $t_subject_id] < 1))
    	        {
    	            $message[] = '请选择教师' . $subject[$t_subject_id] .'学科报告模板';
    	        }
    	    }
    	}
    	
    	if ($message)
    	{
    	    message(implode('<br/>', $message));
    	}
    	
    	foreach ($template_ids as $subject_id => $template_id)
    	{
    	    $template_info = EvaluateTemplateModel::get_evaluate_template_info($template_id, false);
    	    if (!$template_info)
    	    {
    	        message('模板不存在');
    	    }

    	    if (Validate::isInt($subject_id))
    	    {
    	        if ($subject_id > 0)
    	        {
    	            if ($template_info['template_subjectid']
    	                && strpos($template_info['template_subjectid'], ",{$subject_id},") === false)
    	            {
    	                message("{$subject[$subject_id]}选择的模板不适用于生成学生{$subject[$subject_id]}学科报告");
    	            }
    	        }
    	        else
    	        {
    	            if (!in_array($template_info['template_type'], array(0, 5)))
    	            {
    	                message("该模板不适用于生成考试总结报告");
    	            }
    	        }
    	    }
    	    else if (strpos($subject_id, 'cls_') !== false)
    	    {
    	        $subject_id = str_ireplace('cls_', '', $subject_id);
    	        if ($template_info['template_subjectid']
    	            && strpos($template_info['template_subjectid'], ",{$subject_id},") === false)
    	        {
    	            message("{$subject[$subject_id]}选择的模板不适用于生成班级{$subject[$subject_id]}学科报告");
    	        }
    	    }
    	    else if (strpos($subject_id, 'teacher_'))
    	    {
    	        $subject_id = str_ireplace('teacher_', '', $subject_id);
    	        if ($template_info['template_subjectid']
    	            && strpos($template_info['template_subjectid'], ",{$subject_id},") === false)
    	        {
    	            message("{$subject[$subject_id]}选择的模板不适用于生成教师{$subject[$subject_id]}学科报告");
    	        }
    	    }
    	}
    	
    	$template_ids = $template_ids ? $template_ids : array();

    	//添加一条计划任务
    	$res = $this->task_report_model->insert($rule_id, json_encode($template_ids));

    	if ($res)
    	{
    		$rule_admin = array(
				'rule_id' => $rule_id,
				'admin_id' => $this->session->userdata('admin_id'),
    		);
    		$this->db->replace('evaluate_rule_admin', $rule_admin);

    		admin_log('generate', 'evaluate_rule', $rule_id);
    		message('已成功将该规则添加到生成报告队列中，请耐心等待.', '/admin/evaluate_rule/index');
    	}
    	else
    	{
    		message('操作失败，请重试.');
    	}
    }

    /**
     * 生成报告选择模板
     */
    public function general_template($rule_id = 0)
    {  if (!$this->check_power('evaluate_rule_manage')) return;

        $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
    	if (empty($rule))
    	{
    		message('不存在该评估规则');
    	}

    	if ($rule['generate_subject_report'])
    	{
    	    $data['template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
    	        array('template_type'=>($rule['subject_id'] ? 1 : '0,5')));
    	    
    	    if ($rule['subject_id'] == 0)
    	    {
    	        $data['subject_template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
    	            array('template_type'=>'1'));
    	    }
    	}
    	
    	if ($rule['generate_class_report'])
    	{
    	    $data['class_template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
    	        array('template_type'=>4));
    	}
    	
    	if ($rule['generate_teacher_report'])
    	{
    	    $data['teacher_template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
    	        array('template_type'=>6));
    	}

        $data['subjects'] = C('subject');
        $data['rule'] = $rule;

        $this->load->view('evaluate_rule/general_template', $data);
    }

    /**
     * 生成面试报告模板选择界面
     *
     * @author TCG 20154-08-22
     * @return void
     */
    public function interview_general_template($rule_id = 0)
    {
        if (!$this->check_power('evaluate_rule_manage')) return;

        $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);

        if (empty($rule))
        {
            message('不存在该评估规则');
        }

        $data['subject_template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
                    array('template_type' => 2));

        $data['template_list'] = EvaluateTemplateModel::get_evaluate_template_list(
                array('template_type' => 3));

        $data['subjects'] = C('subject');
        $data['rule'] = $rule;

        $this->load->view('evaluate_rule/interview_general_template', $data);
    }

    /**
     * 生成面试报告
     *
     * @author TCG 2015-08-22
     * @return void
     */
    public function interview_general($rule_id = 0)
    {
        if (!$this->check_power('evaluate_rule_manage')) return;

        $rule_id = intval($rule_id);

        $rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);

        if (empty($rule))
        {
            message('不存在该评估规则');
        }

        $this->load->model('cron/report/interview_task_report_model', 'task_report_model');

        $task = $this->task_report_model->get_task($rule_id);

        if (isset($task['status']) && ($task['status'] == '0' || $task['status'] == '1'))
        {
            message('该规则报告正在处理中，请耐心等待.');
        }

        $template_id = $this->input->post('template_id');

        if (empty($template_id))
        {
            message('请选择面试报告模板');
        }

        $templates = array();

        if (is_array($template_id)) {
            foreach ($template_id as $key => $value) {

                if (empty($value)) {
                    continue;
                }

                $template_info = EvaluateTemplateModel::get_evaluate_template_info($value, false);

                if (!$template_info)
                {
                    message('模板不存在!');
                }

                $templates[$key] = $value;
                /* @todo 验证模板类型 */
            }
        } else {
            message('模板参数错误，请从新选择模板！');
        }

        //添加一条计划任务
        $res = $this->task_report_model->insert($rule_id, json_encode($templates, JSON_FORCE_OBJECT));

        if ($res)
        {
            $rule_admin = array(
                'rule_id' => $rule_id,
                'admin_id' => $this->session->userdata('admin_id'),
            );

            $this->db->replace('evaluate_rule_admin', $rule_admin);

            admin_log('generate', 'evaluate_rule', $rule_id);

            message('已成功将该规则添加到生成报告队列中，报告将在第二天生成，请耐心等待.', '/admin/evaluate_rule/index');
        }
        else
        {
            message('操作失败，请重试.');
        }
    }



    /**
     * 检查该评估规则 是否正在 生成报告
     *
     * @return  void
     */
    private function rule_is_reportting($rule_id)
    {
    	$this->load->model('cron/report/task_report_model');
    	$task = $this->task_report_model->get_task($rule_id);

    	return isset($task['status']) && $task['status'] < 3;
    }

	/**
	 * 获取考试排名
	 */
	public function get_exam_rank() 
	{
	    $rule_id = $this->input->get ( 'rule_id' );
	    $exam_pid = $this->input->get ( 'exam_pid' );
	    $subject_id = $this->input->get ( 'subject_id' );
	    
	    $sql ="SELECT place_id FROM {pre}evaluate_rule
	           WHERE id='{$rule_id}'";
	    $res = $this->db->query($sql)->row_array();
	    
	    $place_idd = $res['place_id'];
		if (!$this->input->post()) 
		{
			$data = array ();
			if($place_idd == 0)
			{
                $sql = "SELECT place_id,place_name FROM {pre}exam_place 
                        WHERE exam_pid='{$exam_pid}'";
			}
			else
			{
                $sql = "SELECT place_id,place_name FROM {pre}exam_place 
                        WHERE place_id='{$place_idd}'";
			}
			
			$data['places'] = $this->db->query ($sql)->result_array();
			$data['exam_pid'] = $exam_pid;
			$data['rule_id'] = $rule_id;
			$data['subject_id'] = $subject_id;
			
			$this->load->view ( 'evaluate_rule/exam_rank', $data );
		} 
		else 
		{
		    $place_id = $this->input->post('place_id');
		    
			if ($place_id == 0) 
			{
			    $where_sql = '';
				if($place_idd > 0)
				{
					$where_sql = "AND etp.place_id={$place_idd}";
				}
				
				$place_name ="所有考场";
			} 
			else 
			{
				$where_sql = "AND etp.place_id=$place_id";
				
				$sql = "SELECT place_name FROM {pre}exam_place 
				        WHERE place_id='{$place_id}'";
				$res = $this->db->query($sql)->row_array();
				
				$place_name = $res['place_name'];
			}
			
			$exam = $this->db->select ( 'exam_name' )->get_where ( 'exam', array (
					'exam_id' => $exam_pid
			) )->row_array ();
			if (empty ( $exam )) {
				message ( '考试期次不存在' );
			}

			$exam_name = $exam ['exam_name'];
			
			if ($subject_id > 0)
			{
		        $where_sql .= " AND etp.subject_id = $subject_id";
		        
		        $sql = "select distinct(etp.uid) as uid, last_name, first_name, exam_ticket, exam_ticket_maprule, etp.test_score
		                  from {pre}exam_test_paper etp
		                  LEFT JOIN rd_student s ON s.uid = etp.uid
		                  LEFT JOIN rd_exam e ON e.exam_id = etp.exam_pid
		                  where etp.exam_pid={$exam_pid} AND etp_flag = 2 $where_sql
		                  ORDER BY etp.test_score DESC";
		        $result = $this->db->query ( $sql )->result_array ();
		        $data = array ();
		        $subject = C ( 'subject' );
		        foreach ( $result as $item ) 
		        {
	                $data [$item ['uid']] ['姓名'] = $item ['last_name'] . $item ['first_name'];
	                $data [$item ['uid']] ['准考证号'] = exam_ticket_maprule_decode( $item['exam_ticket'], $item['exam_ticket_maprule'] );
	                $data [$item ['uid']] ['总分'] = $item['test_score'];
		        }
		        
		        $list = array ();
		        foreach ( $data as $uid => $item ) {
		            $list [$item ['总分']] [] = $item;
		        }
		        
		        $rank = 1;
		        foreach ($list as $key => &$item ) 
		        {
		            $list [$key] = array_values ( $this->arraySort ( $item, '总分', 'desc' ) );
		        
		            $pre_total_score = 0;
		            foreach ( $item as $index => &$val ) {
		                if ($val ['总分'] == 0) {
		                    $val = array_merge ( array (
		                        '排名' => ''
		                    ), $val );
		                    continue;
		                }
		        
		                if ($val ['总分'] == $pre_total_score) {
		                    $val = array_merge ( array (
		                        '排名' => $item [$index - 1] ['排名']
		                    ), $val );
		                } else {
		                    $val = array_merge ( array (
		                        '排名' => $rank
		                    ), $val );
		                }
		        
		                $pre_total_score = $val ['总分'];
		        
		                $rank ++;
		            }
		        }
		        
		        // 作弊的人员
		        $sql = "select distinct(etp.uid) as uid, last_name, first_name,exam_ticket,exam_ticket_maprule
        		        from {pre}exam_test_paper etp
        		        LEFT JOIN rd_student s ON s.uid = etp.uid
        		        LEFT JOIN rd_exam e ON e.exam_id = etp.exam_pid
        		        where etp.exam_pid={$exam_pid} and etp_flag = -1 $where_sql";
		        $result = $this->db->query ( $sql )->result_array ();
		        foreach ( $result as $info ) 
		        {
		            $data = array ();
		            $data ['排名'] = '';
		            $data ['姓名'] = $info ['last_name'] . $info ['first_name'];
		            $data ['准考证号'] = exam_ticket_maprule_decode ( $info ['exam_ticket'], $info ['exam_ticket_maprule'] );
		            $data ['说明'] = '考试结果作废';
		        
		            $list [0] [] = $data;
		        }
		        
		        $exam_name .= '-' . $subject[$subject_id];
			}
			else 
			{
    			$this->load->model ( 'cron/report/subject_report/complex_model', 'c_model' );
    
    			$sql = "select distinct(etp.uid) as uid, last_name, first_name,exam_ticket,exam_ticket_maprule
                		from {pre}exam_test_paper etp
                		LEFT JOIN rd_student s ON s.uid = etp.uid
                		LEFT JOIN rd_exam e ON e.exam_id = etp.exam_pid
                		where etp.exam_pid={$exam_pid} AND etp_flag = 2 $where_sql";
    			$result = $this->db->query ( $sql )->result_array ();
    
    			$no_result = array ();
    			$data = array ();
    			$subject = C ( 'subject' );
    			foreach ( $result as $item ) {
    				$t_data = $this->c_model->module_match_percent ( $rule_id, $exam_pid, $item ['uid'] );
    				if (! empty ( $t_data ['data'] )) {
    					$exam_subject = $t_data ['data'] [0];
    					array_shift ( $exam_subject );
    
    					$subject_score = $t_data ['data'] [4];
    					array_shift ( $subject_score );
    
    					$total_percent_score = array_pop ( $t_data ['data'] [5] );
    
    					$data [$item ['uid']] ['姓名'] = $item ['last_name'] . $item ['first_name'];
    				    $data [$item ['uid']] ['准考证号'] = exam_ticket_maprule_decode( $item['exam_ticket'], $item['exam_ticket_maprule'] );
    					$data [$item ['uid']] ['权重总分'] = $total_percent_score;
    					$data [$item ['uid']] ['总分'] = array_pop ( $subject_score );
    
    					foreach ( $subject as $sub ) {
    						if (in_array ( $sub, $exam_subject )) {
    							$key = array_search ( $sub, $exam_subject );
    
    							$data [$item ['uid']] [$sub] = $subject_score [$key];
    						}
    					}
    				} else {
    					message ( '抱歉，没有生成考试成绩，无法获取考试排名' );
    				}
    			}
    
    			$data = $this->arraySort ( $data, '权重总分', 'desc' );
    
    			$list = array ();
    			foreach ( $data as $uid => $item ) {
    				$list [$item ['权重总分']] [] = $item;
    			}
    
    			$rank = 1;
    			foreach ( $list as $key => &$item ) {
    				$list [$key] = array_values ( $this->arraySort ( $item, '总分', 'desc' ) );
    
    				$pre_total_score = 0;
    				foreach ( $item as $index => &$val ) {
    					if ($val ['总分'] == 0) {
    						$val = array_merge ( array (
    								'排名' => ''
    						), $val );
    						continue;
    					}
    
    					if ($val ['总分'] == $pre_total_score) {
    						$val = array_merge ( array (
    								'排名' => $item [$index - 1] ['排名']
    						), $val );
    					} else {
    						$val = array_merge ( array (
    								'排名' => $rank
    						), $val );
    					}
    
    					$pre_total_score = $val ['总分'];
    
    					$rank ++;
    				}
    			}
    			// pr($list,1);
    
    			if ($no_result) {
    				$list [0] [] = $no_result;
    			}
    
    			// 作弊的人员
    			$sql = "select distinct(etp.uid) as uid, last_name, first_name,exam_ticket,exam_ticket_maprule
        					from {pre}exam_test_paper etp
        					LEFT JOIN rd_student s ON s.uid = etp.uid
        					LEFT JOIN rd_exam e ON e.exam_id = etp.exam_pid
        					where etp.exam_pid={$exam_pid} and etp_flag = -1 $where_sql";
    			$result = $this->db->query ( $sql )->result_array ();
    			foreach ( $result as $info ) {
    				$data = array ();
    
    				$data ['排名'] = '';
    				$data ['姓名'] = $info ['last_name'] . $info ['first_name'];
    				$data ['准考证号'] = exam_ticket_maprule_decode ( $info ['exam_ticket'], $info ['exam_ticket_maprule'] );
    				$data ['说明'] = '考试结果作废';
    
    				$list [0] [] = $data;
    			}
    		}
    		
    		$i = 0;
    		
    		header ( "Content-type:application/vnd.ms-excel" );
    		header ( "Content-Disposition:attachment;filename={$exam_name}排名.xls" );
    		echo  "\t"."\t"."\t".mb_convert_encoding ( "期次:".$exam_name, "GBK", "UTF-8" ). "\n";
    		echo "\t"."\t"."\t". mb_convert_encoding ( "场次:".$place_name, "GBK", "UTF-8" ). "\n";
    		echo "\n";
    		
    		foreach ( $list as $value )
    		{
    		    foreach ( $value as $items )
    		    {
    		        if ($i == 0)
    		        {
    		            $title = array_keys ( $items );
    		            foreach ( $title as $info )
    		            {
    		                echo mb_convert_encoding ( $info, "GBK", "UTF-8" ) . "\t";
    		            }
    		
    		            $i ++;
    		        }
    		
    		        if (! $items) {
    		            continue;
    		        }
    		
    		        echo "\n";
    		
    		        foreach ( $items as $vals )
    		        {
    		            if (is_numeric ( $vals ))
    		            {
    		                echo  ( $vals ) . "\t";
    		            }
    		            else
    		            {
    		                echo mb_convert_encoding ( $vals, "GBK", "UTF-8" ) . "\t";
    		            }
    		        }
    		    }
    		}
		}
	}

    private function arraySort($arr, $keys, $type = 'asc')
    {
        $keysvalue = $new_array = array();

        foreach ($arr as $k => $v){
            $keysvalue[$k] = $v[$keys];
        }

        $type == 'asc' ? asort($keysvalue) : arsort($keysvalue);

        reset($keysvalue);

        foreach ($keysvalue as $k => $v) {
            $new_array[$k] = $arr[$k];
        }

        return $new_array;
    }
}
