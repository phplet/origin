<?php if ( ! defined('BASEPATH')) exit();
/**
 * 试题难易度管理
 * @author TCG
 * @create 2015-08-10
 */
class Relate_class extends A_Controller
{
	public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
    	if ( ! $this->check_power('question_difficulty_list')) return;

    	$page = intval($this->input->get('page'));
    	$per_page = intval($this->input->get('per_page'));

    	$id = intval($this->input->get('id'));
    	$ques_id = intval($this->input->get('ques_id'));
    	$grade_id = intval($this->input->get('grade_id'));
    	$class_id = $this->input->get('class_id');
    	$subject_type = $this->input->get('subject_type');
    	$from_question_list = intval($this->input->get('from_question_list'));

    	$difficulty_start = intval($this->input->get('difficulty_start'));
    	$difficulty_end = intval($this->input->get('difficulty_end'));

    	$copy_difficulty_start = intval($this->input->get('copy_difficulty_start'));
    	$copy_difficulty_end = intval($this->input->get('copy_difficulty_end'));

    	// 查询条件
    	$query = array();
    	$param  = array();
		$search = array(
			'id' => '',
			'ques_id' => '',
			'grade_id' => '',
			'subject_type' => '',
			'class_id' => array(),
			'difficulty_start' => '',
			'difficulty_end' => '',
			'copy_difficulty_start' => '',
			'copy_difficulty_end' => '',
		);

		$grades 	= C('grades');
		$subjects   = CpUserModel::get_allowed_subjects();
		$qtypes     = C('qtype');
		$subject_types = C('subject_type');
		$class_list = ClassModel::get_class_list();

		$data['grades'] = $grades;
		$data['subjects'] = $subjects;
		$data['qtypes'] = $qtypes;
		$data['subject_types'] = $subject_types;
    	$data['class_list'] = $class_list;

    	if ($id) {
    		$query['id'] = $id;
    		$param[] = "id={$id}";
    		$search['id'] = $id;
    	}

    	if ($ques_id) {
    		$query['ques_id'] = $ques_id;
    		$param[] = "ques_id={$ques_id}";
    		$search['ques_id'] = $ques_id;
    	}

    	if ($grade_id) {
    		$query['grade_id'] = $grade_id;
    		$param[] = "grade_id={$grade_id}";
    		$search['grade_id'] = $grade_id;
    	}

    	if (is_numeric($subject_type)) {
    		$query['subject_type'] = $subject_type;
    		$param[] = "subject_type={$subject_type}";
    		$search['subject_type'] = $subject_type;
    	}

    	if (is_array($class_id)) {
	    	$class_id = my_intval($class_id);
    		if (count($class_id) == 1)
	    		$query['class_id'] = $class_id[0];
    		else
	    		$query['class_id'] = $class_id;

    		$param[] = implode('&class_id[]=',$search['class_id']);
    		$search['class_id'] = $class_id;
    	}

    	if ($difficulty_start) {
    		$query['difficulty']['>='] = $difficulty_start;
    		$param[] = "difficulty_start={$difficulty_start}";
    		$search['difficulty_start'] = $difficulty_start;
    	}

    	if ($difficulty_end) {
    		$query['difficulty']['<='] = $difficulty_end;
    		$param[] = "difficulty_end={$difficulty_end}";
    		$search['difficulty_end'] = $difficulty_end;
    	}

    	if ($copy_difficulty_start) {
    		$query['copy_difficulty']['>='] = $copy_difficulty_start;
    		$param[] = "copy_difficulty_start={$copy_difficulty_start}";
    		$search['copy_difficulty_start'] = $copy_difficulty_start;
    	}

    	if ($copy_difficulty_end) {
    		$query['copy_difficulty']['<='] = $copy_difficulty_end;
    		$param[] = "copy_difficulty_end={$copy_difficulty_end}";
    		$search['copy_difficulty_end'] = $copy_difficulty_end;
    	}

    	if ($from_question_list) {
    		$param[] = "from_question_list={$from_question_list}";
    		$search['from_question_list'] = $from_question_list;
    	} else {
    		$search['from_question_list'] = '0';
    	}


     //限制只能查看所属学科
        if (!$this->is_super_user()
            && !$this->is_all_subject_user()
            && !CpUserModel::is_action_type_all('question', 'r')
            && CpUserModel::is_action_type_subject('question', 'r'))
        {
            $c_subject_id = rtrim($this->session->userdata('subject_id'), ',');
            if($c_subject_id!='')
            {
                $c_subject_id=explode(',',$c_subject_id);
                $c_subject_id = array_values(array_filter($c_subject_id));
                $c_subject_id = implode(',',$c_subject_id);
                $where0 = "  q.subject_id in($c_subject_id)";
            }
            else
            {
                $where0=" 1=1";
            }

       	}
       	else
       	{
       	    $where0=" 1=1";
       	}

       	//限制只能查看所属年级
       	if (!$this->is_super_user()&& !$this->is_all_grade_user())
       	{
       	    $c_grade_id = rtrim($this->session->userdata('grade_id'), ',');
       	    if( $c_grade_id!='')
       	    {
       	        $c_grade_id=explode(',',$c_grade_id);
       	        $c_grade_id = array_values(array_filter($c_grade_id));
       	        $c_grade_id = implode(',',$c_grade_id);
       	        $where1=" rc.grade_id in ($c_grade_id)";

       	    }
       	    else
       	    {
       	        $where1=" 1=1";
       	    }

       	}
       	else
       	{
       	    $where1=" 1=1";
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
       	        $where2=" rc.class_id in ($c_q_type_id)";

       	    }
       	    else
       	    {
       	        $where2=" 1=1";
       	    }
       	}
       	else
       	{
       	    $where2=" 1=1";
       	}
       	$where = $where0.' and ('.$where1.' and '.$where2.')';


    	$select_what = 'rc.*';
    	$page = $page <= 0 ? 1 : $page;
    	$per_page = $per_page <= 0 ? 10 : $per_page;

    	$list = array();
    	if (!is_null($query)) {
    		$list = RelateClassModel::get_relate_class_list($query, $page, $per_page, 'ques_id desc', $select_what,$group_by = null,$where);
    	}



    	//获取试题信息
    	$tmp_list = array();

    	foreach ($list as $k => $item)
		{
    		$question = QuestionModel::get_question($item['ques_id'], 'title, subject_id, admin_id, addtime, type, is_parent');

    		if (!count($question))
			{
    			continue;
    		}

    		$admin = $this->db->query("SELECT a.realname FROM {pre}admin a where a.admin_id={$question['admin_id']}")->result_array();
    		$question['admin_name'] = count($admin) ? $admin[0]['realname'] : '--';
    		$question['subject_name'] = $subjects[$question['subject_id']];
    		$item['grade_name'] = $grades[$item['grade_id']];
    		$item['subject_type_name'] = $subject_types[$item['subject_type']];
    		$item['addtime'] = date('Y-m-d H:i:s', $question['addtime']);
    		$question['type'] = $qtypes[$question['type']];

    		//获取试题类型
    		$item['class_name'] = isset($class_list[$item['class_id']]) ? $class_list[$item['class_id']]['class_name'] : '--';

			//学科/试题相关权限
			$has_question_power =  QuestionModel::check_question_power($item['ques_id'], 'w', false);
			$has_subject_power = true;

    		$item['has_edit_power'] = $has_question_power && $has_subject_power;

    		$tmp_list[$k] = array_merge($item, $question);
    	}

    	$data['list'] = &$tmp_list;
    	$data['search'] = &$search;
    	$data['flags'] = &$flags;

		//难易度更新全新
    	$data['priv_manage'] = $this->check_power('update_system_difficulty', FALSE);

        $data['priv_question_manage']  = $this->check_power('question_manage', FALSE);

    	// 分页
    	$purl = site_url('admin/relate_class/index/') . (count($param) ? '?'.implode('&',$param) : '');
    	$total = RelateClassModel::count_list($query,$where);
    	$data['pagination'] = multipage($total, $per_page, $page, $purl);

    	$this->load->view('relate_class/index', $data);
    }

    /**
     * 更新试题难易度
     * 字段：
     * 	rd_relate_class->difficulty
     */
    public function recover_difficulty()
    {
    	if ( ! $this->check_power('update_system_difficulty')) return;
    	$id = intval($this->input->get('id'));
    	if (!$id) {
    		message('不存在该条试题关联信息.');
    	}

    	if (!RelateClassModel::recover_default_difficulty($id)) {
    		message('恢复默认难易度失败.');
    	}

   		message('恢复默认难易度成功.','javascript');
    }

	/**
	 * 更新试题难易度
	 * 字段：
	 * 	rd_relate_class->difficulty
	 */
	public function update_difficulty()
	{
		if ( ! $this->check_power('update_system_difficulty')) return;
		$id = intval($this->input->post('id'));
		$difficulty = floatval($this->input->post('difficulty'));

		if (!$id) {
			message('不存在该条试题关联信息.');
		}

		if ($difficulty < 1 || $difficulty > 100) {
			message('请填写正确的难易度，难易度区间为 1-100.');
		}

		if (!RelateClassModel::manual_update_question_difficulty($id, $difficulty)) {
			message('难易度更新失败.');
		}

		message('难易度更新成功.','javascript');
	}

	/**
	 * 更新系统试题难易度
	 * 字段：
	 * 	rd_relate_class->difficulty
	 */
	public function update_global_difficulty()
	{
		if ( ! $this->check_power('update_system_difficulty')) return;
		try {
                    RelateClassModel::system_cal_question_difficulty();
		} catch (Exception $e) {
			output_json(CODE_ERROR, '更新失败');
		}

		output_json(CODE_SUCCESS, '更新成功');
	}

	/**
	 * 一键恢复 试题初始难易度
	 * 字段：
	 * 	rd_relate_class->difficulty
	 */
	public function recover_default_difficulty()
	{
		if ( ! $this->check_power('update_system_difficulty')) return;
		$res = RelateClassModel::recover_difficulty();
		if (!$res) {
			output_json(CODE_ERROR, '更新失败');
		}

		output_json(CODE_SUCCESS, '更新成功');
	}
}
