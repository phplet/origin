<?php if ( ! defined('BASEPATH')) exit();
class Exam extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 考试期次列表
     *
     * @param int $pid
     * @param date $begin_time 开始时间
     * @param date $end_time 结束时间
     * @param string $keyword 关键字
     * @return void
     */
    public function index($pid = 0)
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $grades     = C('grades');
        $subjects   = C('subject');
        $states     = C('exam_status');
        $subject_types = C('subject_type');

        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();

        $pid = intval($pid);
        $where[] = "e.exam_pid=$pid";
        if ($pid) {
            $parent = ExamModel::get_exam($pid);
            if (empty($parent)) {
                message('考试期次不存在', 'admin/exam/index');
                return;
            }
            $data['parent'] = $parent;
        }

        // 时间
        $begin_time = $this->input->get('begin_time');
        $end_time   = $this->input->get('end_time');
        if ($btime = (int)strtotime($begin_time)) {
            $search['begin_time'] = $begin_time;
            $where[] = "e.addtime >= $btime";
            $param[] = "begin_time=$begin_time";
        } else {
            $search['begin_time'] = '';
        }
        if ($etime = (int)strtotime($end_time)) {
            $etime += 86400;
            $search['end_time'] = $end_time;
            $where[] = "e.addtime < $etime";
            $param[] = "end_time=$end_time";
        } else {
            $search['end_time'] = '';
        }

        if ($search['keyword'] = trim($this->input->get('keyword'))) {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $where[] = "e.exam_name LIKE '%".$escape_keyword."%'";
            $param[] = "keyword=".urlencode($search['keyword']);
        }
        
        if (!$this->is_super_user())
        {
            $admin_id = intval($this->session->userdata('admin_id'));
            $where[] = "((exam_id IN (SELECT DISTINCT(exam_pid) 
                        FROM rd_exam_managers WHERE admin_id = $admin_id)) 
                        OR creator_id = $admin_id)";
        }

        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';
        
        /*
         * 所有考试期次数量统计
         */
        $sql = "SELECT COUNT(*) nums FROM {pre}exam e $where";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        //已公布成绩的期次
        $exam_result_publish = array();
        $query = $this->db->select('exam_pid')->from('exam_result_publish')->get();
        foreach ($query->result_array() as $item) {
            $exam_result_publish[] = $item['exam_pid'];
        }
        /*
         * 分页获取考试期次，并处理考试期次数据
         */
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();
        if ($total) {
            $sql = "SELECT * FROM {pre}exam e $where ORDER BY e.exam_id desc LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row) {
                $row['class_name'] = isset($class_list[$row['class_id']]['class_name'])
                                         ? $class_list[$row['class_id']]['class_name'] : '';
                $row['grade_name'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '';
                $row['subject'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['state'] = $states[$row['status']];
                $row['subject_type'] = $subject_types[$row['subject_type']];
                $row['managers'] = $row['managers'] ? json_decode($row['managers'], true) : array();

                if (in_array($row['exam_id'], $exam_result_publish)) {
                    $row['is_publish'] = true;
                }

                $list[$row['exam_id']] = $row;
            }
        }

        /*
         * 查询考试期次下安排的科目考试
         */
        if ($list) {
            $query = $this->db->where_in('exam_pid', array_keys($list))->order_by('exam_index','asc')->order_by('subject_id','asc')->get('exam');
            foreach ($query->result_array() as $row) {
                $row['class_name'] = isset($class_list[$row['class_id']]['class_name'])
                                         ? $class_list[$row['class_id']]['class_name'] : '';
                $row['grade_name'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '';
                $row['subject'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['state'] = $states[$row['status']];
                $row['subject_type'] = $subject_types[$row['subject_type']];

                //检查该学科考试状态
                $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($row['exam_id']);
                $row['has_tested'] = $has_tested;

                $list[$row['exam_pid']]['has_tested'] = $has_tested;
                $list[$row['exam_pid']]['subjects'][] = $row;
            }
        }

        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/exam/index/'.$pid) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        $data['search']      = $search;

        $tpl = $pid ? 'exam/index_subject' : 'exam/index';

        $data['login_admin_id'] = $this->session->userdata('admin_id');
        $data['is_super_user'] = $this->is_super_user();
        $admin_list = $this->db->select('admin_id,realname')->get_where('admin',
                                    array('is_delete'=>0))->result_array();
        foreach ($admin_list as $item) {
            $data['admin_list'][$item['admin_id']] = $item['realname'];
        }

        $this->demo_exams($data);

        // 模版
        $this->load->view($tpl, $data);
    }

    private function demo_exams(&$data)
    {
        $exam_config = C('demo_exam_config', 'app/demo/website');
        $demo_exams = array();
        foreach ($exam_config as $item) {
            $demo_exams[] = $item['exam_pid'];
        }
        $data['demo_exams'] = $demo_exams;
    }

    /**
     * 添加考试期次
     *
     * @return void
     */
    public function add()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam = array(
            'exam_id'    => '',
            'exam_name'  => '',
            //'subject_id' => '',
            'grade_id'   => '',
            'class_id'   => '',
            'exam_ticket_maprule' => 0,
            //'exam_minute' => '',
            'introduce'  => '',
            'student_notice' => '',
            'invigilate_notice' => '',
            'exam_type' => '1',
            'status' => '1',
            'managers' => array()
        );

        $data['act'] = 'add';
        $data['exam'] = &$exam;

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $data['class_list'] = $class_list;
        $data['subjects']   = CpUserModel::get_allowed_subjects();
        $data['grades']     = C('grades');
        $data['exam_status']= C('exam_status');
        $data['referer']    = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        //管理员列表
        $data['admin_list'] = $this->db->select('admin_id,realname')->get_where('admin',
                                    array('is_delete'=>0))->result_array();
        $data['admin_list2'] = array(1, $this->session->userdata('admin_id'));

        $data['exam_ticket_maprule'] = C('exam_ticket_maprule');

        // 模版
        $this->load->view('exam/edit', $data);
    }

    /**
     * 编辑考试期次
     *
     * @param int $id 考试期次id
     * @return void
     */
    public function edit($id = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);
        $id && $exam = ExamModel::get_exam($id);
        if (empty($exam)) {
            message('考试不存在');
        }
        $exam['managers'] = json_decode($exam['managers'], true);
        $this->check_exam_manager_power($exam['creator_id']);
        $data['exam'] = &$exam;
        $data['act']  = 'edit';
        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        
        $data['class_list'] = $class_list;
        $data['subjects']   = CpUserModel::get_allowed_subjects();
        $data['grades']     = C('grades');
        $data['exam_status']= C('exam_status');
        $data['subject_types']= C('subject_type');
        $data['referer']    = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        //学生姓名 --- 编辑 -- 开始
        $student_exam_array = ExamStudentListModel::select_exam_id(intval($id));
        if (empty($student_exam_array)) {
            $data['student_name'] = "";
        } else {
            foreach ($student_exam_array as $item) {
                $data['student_name'][] = $item['student_name'] . "," . $item['student_ticket'];
            }
            $data['student_name'] = implode("\n", $data['student_name']);
        }
        //学生姓名 --- 编辑 -- 结束
        //管理员列表
        $data['admin_list'] = $this->db->select('admin_id,realname')->get_where('admin',
                                array('is_delete'=>0))->result_array();
        $data['admin_list2'] = array(1, $exam['creator_id']);
        $data['creator'] = $this->db->select('realname')->get_where('admin',
                             array('admin_id'=>$exam['creator_id']))->row_array();
        $data['exam_ticket_maprule'] = C('exam_ticket_maprule');
        // 模版
        $this->load->view('exam/edit', $data);
    }

    /**
     * 考试期次，添加/编辑处理页面[入库操作]
     *
     * @param int $exam_id 考试期次id
     * @param int $exam_name 考试名称
     * @param int $introduce 考试介绍
     * @param int $class_id 类型id
     * @param int $grade_id 年级id
     * @param int $student_notice 考生须知
     * @param int $invigilate_notice 监考人员须知
     * @param int $status 状态
     * @param int $exam_type 考试类型（1：单题模式，2：全篇模式）
     * @return void
     */
    public function update()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = intval($this->input->post('exam_id'));
        if ($exam_id > 0) {
            $old_exam = ExamModel::get_exam($exam_id);
            if (empty($old_exam) OR $old_exam['exam_pid'] > 0) {
                message('考试期次不存在');
                return;
            }
            //$managers = $old_exam['managers'] ? json_decode($old_exam['managers'] , true) : array();
            $this->check_exam_manager_power($old_exam['creator_id']);
        } else {
            $exam['creator_id'] = intval($this->session->userdata('admin_id'));
        }
        // 题目基本信息
        $exam['exam_name']    = html_escape(trim($this->input->post('exam_name')));
        $exam['introduce']    = html_escape(trim($this->input->post('introduce')));
        $exam['class_id']     = intval($this->input->post('class_id'));
        $exam['grade_id']     = intval($this->input->post('grade_id'));
        $exam['exam_ticket_maprule']     = intval($this->input->post('exam_ticket_maprule'));
        $exam['cheat_number'] = intval($this->input->post('cheat_num'));
        $exam['kickornot']    = intval($this->input->post('kick'));
        $exam['student_notice'] = html_escape(trim($this->input->post('student_notice')));
        $exam['invigilate_notice'] = html_escape(trim($this->input->post('invigilate_notice')));
        $exam_type	= intval($this->input->post('exam_type'));
        $exam['status'] 	= intval($this->input->post('status'));
        $exam['cheat_number']  = intval($this->input->post('cheat_num'));
        $exam['kickornot']     = intval($this->input->post('kick'));
        $managers = $this->input->post('managers');
        $exam['managers']     = json_encode($managers ? $managers : array());
        $exam_type = !$exam_type ? 1 : $exam_type;
        $exam['exam_type'] = $exam_type;
        $exam['exam_isfree'] = intval($this->input->post('exam_isfree'));
        
        //学生姓名 --- 编辑 -- 开始
        $student_exam = array();
        $student_exam_array = array();
        $exam_student = array();
        $message = array();
        if (empty($exam['exam_name'])) {
            $message[] = '请填写考试名称';
        }
        if (empty($exam['grade_id'])) {
            $message[] = '请选择年级';
        }
        if (empty($exam['class_id'])) {
            $message[] = '请选择类型';
        }
        if ($message) {
            message(implode('<br/>', $message));
        }
        $student_name = html_escape(trim($this->input->post('student_name')));
        if(!empty($student_name)) {
            $student_name = trim($student_name);
            if(strpos($student_name, ",") === false){
                message("请使用英文‘,’符号");
            }
            $student_list = array_unique(explode("\n", $student_name));
            foreach ($student_list as $value) {
                if(empty($value)) {
                    continue;
                }
                @list($student_name, $student_ticket) = explode(',', $value);
                $student_name = trim($student_name);
                $student_ticket = trim($student_ticket);
                if (!$student_name || !$student_ticket) {
                    message('考试学生信息不完整，请输入用逗号连接的学生姓名和准考证号！');
                }
                $student_exam_array["exam_pid"] = $exam_id;
                $student_exam_array["student_name"] = trim($student_name);
                $student_exam_array["student_ticket"] = trim($student_ticket);
                $student_exam_array["uid"] = 0;
                $exam_student[] = $student_exam_array;
            }
        }
        $this->db->trans_start();
        if ($exam_id > 0) {
            $this->db->update('exam', $exam, array('exam_id'=>$exam_id));
            // 同步更新考试学科的年级、类型
            $exam_child = array(
                    'exam_name' => $exam['exam_name'],
                    'grade_id'  => $exam['grade_id'],
                    'class_id'  => $exam['class_id']
            );
            $this->db->update('exam', $exam_child, array('exam_pid' => $exam_id));
            $msg = '修改';
            admin_log('edit', 'exam', $exam_id);
        } else {
            $exam['addtime'] = time();
            $this->db->insert('exam', $exam);
            $exam_id = $this->db->insert_id();

            if ($exam_student) {
                foreach ($exam_student as &$item) {
                    $item['exam_pid'] = $exam_id;
                }
            }
            $msg = '添加';
            admin_log('add', 'exam', $exam_id);
        }
        if ($exam_student) {
            $tips = ExamStudentListModel::is_exist(intval($exam_id));
            if (!$tips) {
                ExamStudentListModel::insert($exam_student);
            } else {
                ExamStudentListModel::update($exam_student);
            }
        } else {
            $this->db->delete('exam_student_list', 'exam_pid = ' . $exam_id);
            $this->db->delete('exam_relate_student', 'exam_pid = ' . $exam_id);
        }
        
        if ($managers)
        {
            $exam_id && $this->db->delete('rd_exam_managers', 
                'exam_pid = ' . $exam_id);
            $managers[] = isset($old_exam) ? 
                $old_exam['creator_id'] : $exam['creator_id'];
            $managers = array_unique($managers);
            foreach ($managers as $uid)
            { 
                $bind = array(
                    'exam_pid' => $exam_id,
                    'admin_id' => $uid
                );
                
                $this->db->insert('rd_exam_managers', $bind);
            }
        }
        
        $result = $this->db->trans_complete();
        
        $back_url = $this->input->post('referer');
        $back_url || $back_url = 'admin/exam/index/';
        message('考试期次' . $msg. ($result ? '成功' : '失败'), $back_url);
    }
    /**
     * 添加考试学科
     *
     * @param int $pid 考试期次id
     * @return void
     */
    public function add_subject($pid=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $pid && $parent = ExamModel::get_exam($pid, 'exam_id,exam_name,exam_pid,grade_id,class_id,creator_id,managers');
        if (empty($parent)) {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }
        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();
        $this->check_exam_manager_power($parent['creator_id'], $managers);
        $class_list = ClassModel::get_class_list();
        $grades = C('grades');
        $parent['grade_name'] = isset($grades[$parent['grade_id']]) ? $grades[$parent['grade_id']] : '';
        $parent['class_name'] = isset($class_list[$parent['class_id']]['class_name']) ? $class_list[$parent['class_id']]['class_name'] : '';
        $exam = array(
            'exam_id'     => '',
            'subject_id'  => '',
            'total_score' => '',
            'qtype_score' => array('','','','','','','','',''),
            'introduce'   => '',
            'subject_type' => '',
        );

        $data['act']      = 'add';
        $data['exam']     = $exam;
        $data['parent']   = $parent;
        $data['subjects'] = CpUserModel::get_allowed_subjects();
        $data['subject_types']= C('subject_type');
        $data['referer']  = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        $this->load->view('exam/edit_subject', $data);
    }

    /**
     * 编辑考试学科
     *
     * @param int $id 考试学科id
     * @return void
     */
    public function edit_subject($id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);
        $id && $exam = ExamModel::get_exam($id);
        if (empty($exam) OR $exam['exam_pid']==0) {
            message('考试学科不存在', 'admin/exam/index');
            return;
        }
        $parent = ExamModel::get_exam($exam['exam_pid'], 'exam_id,exam_name,exam_pid,grade_id,class_id,creator_id,managers');
        if (empty($parent)) {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }
        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();
        $this->check_exam_manager_power($parent['creator_id'], $managers, $exam['creator_id']);

        $class_list = ClassModel::get_class_list();
        $grades = C('grades');

        $parent['grade_name'] = isset($grades[$parent['grade_id']]) ? $grades[$parent['grade_id']] : '';
        $parent['class_name'] = isset($class_list[$parent['class_id']]['class_name']) ? $class_list[$parent['class_id']]['class_name'] : '';


        $exam['qtype_score'] = explode(',', $exam['qtype_score']);

        for($i = 0; $i < 9; $i++) {
            if (!isset($exam['qtype_score'][$i])) {
                $exam['qtype_score'][$i] = '';
            }
        }
        $data['act']      = 'edit';
        $data['exam']     = $exam;
        $data['parent']   = $parent;
        $data['subjects'] = CpUserModel::get_allowed_subjects();
        $data['subject_types'] = C('subject_type');
        $data['referer']  = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        $this->load->view('exam/edit_subject', $data);
    }

    /**
     * 编辑考试学科
     *
     * @param int $exam_pid 考试期次id
     * @param int $subject_id 学科id
     * @param int $total_score 总分
     * @param int $qtype_score 题型分值(非题组), "单选,不定项,填空"
     * @return void
    */
    public function update_subject()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';
        $pid = intval($this->input->post('exam_pid'));
        $pid && $parent = ExamModel::get_exam($pid, 'exam_id,exam_name,exam_pid,grade_id,class_id,creator_id,managers,student_notice,invigilate_notice');
        if (empty($parent) OR $parent['exam_pid'] > 0) {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }
        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();

        $exam_creator_id = 0;
        if ($act == 'edit') {
            $exam_id = $this->input->post('exam_id');
            $exam_id && $old_exam = ExamModel::get_exam($exam_id, 'exam_id,exam_pid');
            if (empty($old_exam) OR $old_exam['exam_pid'] != $parent['exam_id']) {
                message('考试学科不存在');
                return;
            }

            $exam_creator_id = $old_exam['creator_id'];
        }

        $this->check_exam_manager_power($parent['creator_id'], $managers, $exam_creator_id);

        // 题目基本信息
        $exam['subject_id']  = intval($this->input->post('subject_id'));
        $exam['total_score'] = floatval($this->input->post('total_score'));
        $qtype_score         = (array)$this->input->post('qtype_score');
        
        //$exam['exam_minute']  = intval($this->input->post('exam_minute'));

        $message = array();
        if (empty($exam['subject_id'])) {
            $message[] = '请选择学科';
        } else {
            $query = $this->db->select('exam_id')->get_where('exam', array('exam_pid'=>$parent['exam_id'], 'subject_id'=>$exam['subject_id']));
            $row = $query->row_array();
            if ($row) {
                if ($act=='add' OR $act=='edit' && $row['exam_id']!=$old_exam['exam_id']) {
                    $message[] = '学科已存在，不能重复添加';
                }
            }
        }
        if (empty($exam['total_score'])) {
            $message[] = '请填写试卷总分';
        }

        $qtypes = C('qtype');
        $scores = array();
        $allow_qtype = array_keys($qtypes);
        if (($exam['subject_id'] != 3))
        {
            $allow_qtype = array(1, 2, 3, 14);
        }
        
        foreach ($qtypes as $qtype => $qtype_name)
        {
            if ($qtype < 1)
            {
                continue;
            }
            
            if (!in_array($qtype, $allow_qtype))
            {
                $scores[] = 0;
                continue;
            }
            
            $k = $qtype - 1;
            if (isset($qtype_score[$k]))
            {
                if (empty($qtype_score[$k]) 
                    || $qtype_score[$k] < 0 
                    || $qtype_score[$k] > 1000)
                {
                    $message[] = '请正确填写题型分值（'.$qtypes[$qtype].'）';
                }
                else
                {
                    $scores[] = floatval($qtype_score[$k]);
                }
            }
            else 
            {
                $scores[] = 0;
            }
        }
        
        $exam['qtype_score'] = implode(',', $scores);

        if ($message) {
            message(implode('<br/>', $message), null, null, 10);
            return;
        }

        $exam['exam_pid']    = $parent['exam_id'];
        $exam['exam_name']   = $parent['exam_name'];
        $exam['grade_id']    = $parent['grade_id'];
        $exam['class_id']    = $parent['class_id'];
        
        $exam['student_notice']    = $parent['student_notice'];
        $exam['invigilate_notice']    = $parent['invigilate_notice'];
        $exam['introduce']   = html_escape(trim($this->input->post('introduce')));
        $subject_type = $this->input->post('subject_type');
        if (is_numeric($subject_type)) {
	        $exam['subject_type'] = $subject_type;
        }
        
        if ($act == 'add') {
            $exam['addtime'] = time();
            $exam['creator_id'] = intval($this->session->userdata('admin_id'));
            // 排序
            $query = $this->db->select('max(exam_index) max_index')->get_where('exam', array('exam_pid'=>$parent['exam_id']));
            $row = $query->row_array();
            if ($row)
                $exam['exam_index'] = $row['max_index']+1;
            else
                $exam['exam_index'] = 1;

            $this->db->insert('exam', $exam);
            $exam_id = $this->db->insert_id();
            admin_log('add', 'exam', $exam_id);
            $back_url = $this->input->post('referer');
            $back_url || $back_url = 'admin/exam/index/';
            message('考试学科添加成功', $back_url);
        } else {
            $this->db->update('exam', $exam, array('exam_id'=>$exam_id));
            admin_log('edit', 'exam', $exam_id);
            $back_url = $this->input->post('referer');
            $back_url || $back_url = 'admin/exam/index/';
            message('考试学科编辑成功', $back_url);
        }
    }
    /**
     * 删除考试期次
     *
     * @param int $id 考试期次id
     * @return void
     */
    public function delete($id = 0, $force = false)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/exam/index' : $_SERVER['HTTP_REFERER'];
        $return = ExamModel::delete($id, $force);
        if ( $return === true ) {
            message('删除成功', $back_url);
        } else {
            switch($return) {
                case -1: $message = '考试期次不存在'; break;
                case -2: $message = '该期次下，还存在考试学科，不能删除。';break;
                case -3: $message = '该考试学科下，已经生成试卷，不能删除。';break;
                case -4: $message = '该考试需要删除考场下学科，不能删除。';break;
                case -5: $message = '该考试学科已经在考试中，不能删除。';break;
                default: $message = '删除失败'; break;
            }
            message($message, $back_url);
        }
    }

    /**
     * 调整考试学科顺序
     *
     * @param int $id 学科id
     * @return void
     */
    public function edit_index($id=0, $type='forward')
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam = ExamModel::get_exam($id, 'exam_pid,exam_index');
        if ($exam === false OR $exam['exam_pid']==0) {
            message('考试学科不存在');
            return;
        }

        if ($exam['exam_index'] < 1) {
            ExamModel::resort_index($exam['exam_pid']);
        } else {
            if ($type == 'forward') {
                if ($exam['exam_index'] > 1) {
                    $prev_where = array('exam_pid'=>$exam['exam_pid'], 'exam_index'=>$exam['exam_index']-1);
                    $query = $this->db->select('exam_id')->get_where('exam', $prev_where, 1);
                    $prev = $query->row_array();
                    if ($prev) {
                        $this->db->update('exam', array('exam_index'=>$exam['exam_index']-1), array('exam_id'=>$id));
                        $this->db->update('exam', array('exam_index'=>$exam['exam_index']), array('exam_id'=>$prev['exam_id']));
                    } else {
                        ExamModel::resort_index($exam['exam_pid']);
                    }
                }
            } else {
                $next_where = array('exam_pid'=>$exam['exam_pid'], 'exam_index'=>$exam['exam_index']+1);
                $query = $this->db->select('exam_id')->get_where('exam', $next_where, 1);
                $next = $query->row_array();
                if ($next) {
                    $this->db->update('exam', array('exam_index'=>$exam['exam_index']+1), array('exam_id'=>$id));
                    $this->db->update('exam', array('exam_index'=>$exam['exam_index']), array('exam_id'=>$next['exam_id']));
                }
            }
        }
        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/exam/index/' : $_SERVER['HTTP_REFERER'];
        redirect($back_url);
    }

    /**
     * 考试期次标记为已考
     *
     * @param int $exam_id 期次id
     * @return void
     */
    public function change_status($exam_id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

    	$exam_id = intval($exam_id);
    	if ($exam_id <= 0) {
    		message('考试期次不存在.', 'admin/exam/index');
    	}

    	$exam = ExamModel::get_exam($exam_id, 'exam_pid, status');
    	if (!count($exam) || $exam['exam_pid'] != '0') {
    		message('考试期次不存在.', 'admin/exam/index');
    	}
    	if ($exam['status'] == '1') {
    		message('该考试期次已经为 已考状态..', 'admin/exam/index');
    	}

    	if (ExamModel::update(array('status' => 1), array('exam_id' => $exam_id))) {
    		message('标记成功.', 'admin/exam/index');
    	} else {
    		message('标记失败.', 'admin/exam/index');
    	}
    }

    /**
     * 一键更新试题缓存
     *
     * @author TCG
     * @param int $exam_id 考试期次科目id
     * @return mixed 返回成功及失败提示
     */
    public function update_questions_cache ($exam_id)
    {
         $exam_id = (int)$exam_id;
         $exam = Fn::db()->fetchRow(
                         "SELECT exam_id, exam_pid, subject_id, grade_id, class_id, total_score, qtype_score FROM rd_exam WHERE exam_id=?",
                         $exam_id);
         if (empty($exam)) 
         {
             return false;
         }
         
         $is_mini_test = ExamModel::is_mini_test($exam['exam_pid']);
         /** 载入缓存功能 */
         if ($is_mini_test)
         {
             $this->load->driver('cache');
             /** 缓存时间 单位second 默认缓存30天 */
             $cache_time = 24 * 3600 * 20;
         }
         else 
         {
             $cache = Fn::factory('File');
         }
         
         /** 写入缓存 */
         $is_mini_test && $this->cache->file->save('get_paper_question_detail_p_exam_'.$exam_id, $exam, $cache_time);

         $sql = "SELECT paper_id FROM rd_exam_paper WHERE exam_id={$exam_id}";
         $rows = Fn::db()->fetchAll($sql);

         foreach ($rows as $row) {
             
             /** 写入缓存 */
             $is_mini_test && $this->cache->file->save('get_paper_question_detail_p_exam_id_'.$row['paper_id'], $exam_id, $cache_time);
             
             $question_sort = Fn::db()->fetchOne('SELECT question_sort FROM rd_exam_paper
                WHERE paper_id = ?', array($row[paper_id]));
             $question_sort = json_decode($question_sort, true);
             
             $sql = <<<EOT
    SELECT q.ques_id,q.type,q.title,q.score_factor,rc.difficulty
    FROM rd_exam_question eq
    LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
    LEFT JOIN rd_relate_class rc ON rc.ques_id = q.ques_id AND rc.grade_id = ? AND rc.class_id = ?
    WHERE eq.paper_id = ? ORDER BY rc.difficulty DESC,q.ques_id ASC
EOT;
            if ($question_sort)
            {
                $res = Fn::db()->fetchAssoc($sql,
                    array(
                        $exam['grade_id'],
                        $exam['class_id'],
                        $row['paper_id']
                    ));
                $sort = explode(',', $question_sort);
                $result =  array();
                foreach ($question_sort as $ques_id)
                {
                    $result[] = $res[$ques_id];
                    unset($res[$ques_id]);
                }
             }
             else
             {
                 $result = Fn::db()->fetchAll($sql,
                     array(
                         $exam[grade_id],
                         $exam[class_id],
                         $row[paper_id]
                     ));
             }
             
             $key = 'get_paper_question_detail_p_' .
                             $exam[grade_id] . '_' .
                             $exam[class_id] . '_' . $row[paper_id];
             !$is_mini_test && $cache->save('/zmexam/' . $key, $result);
             
             /** 写入缓存 */
             $is_mini_test && $this->cache->file->save($key, $result, $cache_time);
         }

         //$domain=C('memcache_pre');
        /** 获取考试期次所有试题 */
        $sql = "SELECT ques_id FROM {pre}exam_question WHERE exam_id={$exam_id}";
        $query = $this->db->query($sql);
        $rows = $query->result_array();

        /** 生成缓存 (执行时间可能过长) */
        if (count($rows) > 0 && !empty($rows)) {

            $questions = array();

            /** 获取试题数据 */
            foreach ($rows as $key => $value) {
                $question = QuestionModel::get_question($value['ques_id'], 'ques_id, type, title, picture, parent_id,subject_id');

                if (!$question) {
                    continue;
                }

                /** 题组 */
                if (in_array($question['type'],array(0,4,5,6,8))) {

                    $pid = $question['ques_id'];
                    $sql = "SELECT ques_id,type,title,picture,parent_id,subject_id FROM {pre}question WHERE parent_id={$pid}";
                    $query = $this->db->query($sql);
                    $group_rows = $query->result_array();

                    /** 写入题组子题 */
                    $question['children'] = $group_rows;

                    /** 子题加入缓存列表 */
                    $questions = array_merge($questions, $group_rows);
                }

                $questions[] = $question;
            }

            foreach ($questions as $key => &$question) {
                /** 样式修正 */
                $question['title'] && $this->_format_question_content($question['ques_id'], $question['title'], in_array($question['type'], array(3, 9)));

                /** 获取试题选项数据(选择题) */
                if (in_array($question['type'],array(1,2,7,14))) {
                    $question['options'] = QuestionModel::get_options($question['ques_id']);
                }

                /** 获取题组子题选项 */
                if (in_array($question['type'],array(0,4,5,6,8)) && !empty($question['children'])) {

                    foreach ($question['children'] as $k => &$v) {

                        $v['title'] && $this->_format_question_content($v['ques_id'], $v['title'], $v['type']==3);

                        $question['children'][$k]['options'] = QuestionModel::get_options($v['ques_id']);
                    }
                }
                
                !$is_mini_test && $cache->save('/zmexam/question_' . $question['ques_id'], $question);
                
                /** 写入缓存 */
                $is_mini_test && $this->cache->file->save($question['ques_id'], $question, $cache_time);
            }

            /** 成功提示页面 */
            message('生成成功.', 'admin/exam/index');
        } else {
            /** 失败提示页面 */
            message('生成失败！！！请尝试从新输入！', 'admin/exam/index');
        }

        /** $this->output->enable_profiler(TRUE); */
    }

    public function exam_result_publish()
    {
        $type = (int)$this->input->get('type');
        $exam_pid = (int)$this->input->get('exam_pid');
        if ($exam_pid < 1) {
            message((!$type ? '取消' : '') . '公布成绩失败');
        }

        if ($type) {
            if (!$this->db->get_where('exam_result_publish', array('exam_pid'=>$exam_pid))->row_array()) {
                $this->db->insert('exam_result_publish', array('exam_pid'=>$exam_pid, 'c_time'=>time()));
            }
        } else {
            $this->db->delete('exam_result_publish', 'exam_pid = ' . $exam_pid);
        }

        message((!$type ? '取消' : '') . '公布成绩成功', 'admin/exam/index');
    }

    /**
     * 判断当前用户是否有权限修改考试期次
     * @param   int     $creator_id    考试期次创建者
     * @param   array   $managers      考试期次管理员列
     * @param   int     $exam_creator_id 考试学科创建者
     */
    private function check_exam_manager_power($creator_id, $managers = array(), $exam_creator_id = 0)
    {
        if (!$this->is_super_user()) {
            $admin_id = $this->session->userdata('admin_id');
            if ($exam_creator_id > 0) {
                if ($admin_id != $creator_id
                    && $admin_id != $exam_creator_id) {
                    message('您没有权限对此记录进行操作！');
                }
            } else {
                if ($admin_id != $creator_id
                    && !in_array($admin_id, $managers)) {
                    message('您没有权限对此记录进行操作！');
                }
            }
        }
    }

    /**
     * 格式化试题内容
     *
     * @param   integer     试题ID
     * @param   string      试题内容
     * @param   boolean     是否转换填空项
     * @return  void
     */
    private function _format_question_content(&$ques_id, &$content, $replace_inputs = FALSE)
    {
        $content = str_replace("\n", '<br/>', $content);
        if ($replace_inputs) {
            //过滤&nbsp;
            //$content = preg_replace("/（(.*)）/e", 'str_replace("&nbsp;", "", "（\1）")', $content);

            $regex = "/（[\s\d|&nbsp;]*）/";
            $input = "&nbsp;<input{nbsp}type=\"text\"{nbsp}ques_id=\"{$ques_id}\"{nbsp}name=\"answer_{$ques_id}[]\"{nbsp}class=\"input_answer{nbsp}sub_undo{nbsp}type_3\"{nbsp}/>";
            $content = preg_replace($regex, $input, $content);
        }
        //$content = str_replace(" ", '&nbsp;', $content);
        $content = str_replace("{nbsp}", ' ', $content);

        return $content;
    }
    
    /**
     * 更新自由测报告数据结构
     */
    public function renew_free_exam_report()
    {
        return;
        set_time_limit(0);
        $db = Fn::db();
        try
        {
            $db->fetchRow("SELECT sfe_jsondata FROM t_student_free_exam LIMIT 1");
            echo('已经执行过了');
            return;
        }
        catch (Exception $e)
        {
            print('开始执行...');
        }

        $db->exec("ALTER TABLE t_student_free_exam 
                                CHANGE sfe_data sfe_jsondata LONGTEXT");
        $db->exec("ALTER TABLE t_student_free_exam 
                                ADD COLUMN sfe_data LONGBLOB");
        $sql = "SELECT sfe_uid, sfe_exampid, sfe_placeid, 
                    sfe_jsondata AS sfe_data 
                FROM t_student_free_exam 
                WHERE sfe_report_status = 2 AND sfe_subjectid IS NULL";
        $rows = $db->fetchAll($sql);
        foreach ($rows as $item)
        {
            $report = json_decode($item['sfe_data'], true);
            $subject_id = array_keys($report);
            
            $param = array(
                    'sfe_subjectid' => implode(',', $subject_id),
                    'sfe_data' => gzencode($item['sfe_data'], 9)
            );
            unset($item['sfe_data']);
            $db->update('t_student_free_exam', $param,
                'sfe_uid = ? AND sfe_exampid = ? AND sfe_placeid = ?',
                array_values($item));
         }
         echo('执行完毕');
    }
    
    /**
     * 更新自由测报告数据结构
     */
    public function renew_exam_managers()
    {
        $sql = "SELECT exam_id, creator_id, managers FROM rd_exam 
                WHERE exam_pid = 0";
        $stmt = Fn::db()->query($sql);
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC)) 
        {
            $managers = json_decode($item['managers'], true);
            $managers[] = $item['creator_id'];
            if ($managers)
            {
                $this->db->delete('rd_exam_managers',
                    'exam_pid = ' . $item['exam_id']);
                $managers = array_unique($managers);
                foreach ($managers as $uid)
                {
                    $bind = array(
                        'exam_pid' => $item['exam_id'],
                        'admin_id' => $uid
                    );
            
                    $this->db->insert('rd_exam_managers', $bind);
                }
            }
        }
    }

    /**
     * 期次考试成绩下载
     */
    public function  down_exam()
    {
        $db=Fn::db();
        $now=time();
        $sql =<<<EOT
SELECT a.exam_id ,a.exam_name
FROM rd_exam a
LEFT JOIN rd_exam_place b ON a.exam_id = b.exam_pid
WHERE a.exam_pid = 0 AND b.end_time < $now AND exam_isfree =0
order by b.end_time DESC 
EOT;
        $exam_list=$db->fetchAssoc($sql);
        $data['exam_list']=$exam_list;
        $this->load->view('exam/down_exam', $data); 
    }

    /**
     * 期次考试成绩下载
     */
    public function  down_examresult()
    {
        $db = Fn::db();
        
        $exam_pid_arr = array();
        $exam_pid_arr = $this->input->post('exam_ids');
        if (!$exam_pid_arr)
        {
            message('未选择考试现在期次','admin/exam/down_exam');
        }
        
        $exam_pid = implode(',',$exam_pid_arr);
        $sql=<<<EOT
SELECT distinct exam_id,subject_id
FROM rd_exam_place_subject
WHERE exam_pid IN ($exam_pid) 
EOT;
        $exam_subject = $db->fetchPairs($sql);
        $subject_arr = array_unique(array_values($exam_subject));
        sort($subject_arr);

        $sql=<<<EOT
SELECT exam_name 
FROM rd_exam
WHERE exam_id IN ({$exam_pid})
EOT;
        $exam_name_arr= $db->fetchCol($sql);
        $exam_name_str = implode('、',$exam_name_arr);

        $sql =<<<EOT
SELECT etp.uid,last_name, first_name,exam_ticket, exam_ticket_maprule, etp.test_score,etp.exam_id,ep.place_name,etp.subject_id,etp.place_id 
FROM rd_exam_test_paper etp 
LEFT JOIN rd_student s ON s.uid = etp.uid 
LEFT JOIN rd_exam e ON e.exam_id = etp.exam_pid
LEFT JOIN rd_exam_place ep ON ep.place_id=etp.place_id 
WHERE etp.exam_pid IN ({$exam_pid}) AND etp_flag=2
order by etp.place_id ,etp.subject_id ASC 
EOT;
        $result = $db->fetchAll($sql);

        $sql=<<<EOT
SELECT distinct uid
FROM rd_exam_test_paper
WHERE exam_pid IN ({$exam_pid}) AND etp_flag=2
EOT;
        $uid_arr=$db->fetchCol($sql);
        $uids = implode(',',$uid_arr);
        
        $sql =<<<EOT
select s.uid,sch.school_name
from rd_student s
LEFT JOIN rd_school sch ON sch.school_id = s.school_id
WHERE s.uid in ({$uids})
EOT;
        $uid_school = $db->fetchPairs($sql); 
    
        $sql=<<<EOT
SELECT ep.place_id , sc.schcls_name
FROM rd_exam_place ep
LEFT JOIN t_school_class sc ON ep.place_schclsid = sc.schcls_id
EOT;
        $place_schcls = $db->fetchPairs($sql);
        $data = array();
        $subject = C('subject');
        foreach ($subject_arr as $val)
        {
            $subject_name[] = $subject[$val];
        }
        $subject_name_str =implode('、',$subject_name); 
        
        foreach ($result as $item)
        {
            $data[$item['uid']]['姓名'] = $data[$item['uid']]['姓名']? $data[$item['uid']]['姓名']: $item ['last_name'] . $item ['first_name'];
            $data [$item ['uid']] ['准考证号'] = $data [$item ['uid']] ['准考证号']?$data [$item ['uid']] ['准考证号']:exam_ticket_maprule_decode( $item['exam_ticket'], $item['exam_ticket_maprule'] );
            $data[$item['uid']]['学校'] = $data[$item['uid']]['学校']?$data[$item['uid']]['学校']:$uid_school[$item['uid']];
            if ($place_schcls[$item['place_id']])
            {
                $data[$item['uid']]['班级'] =  $data[$item['uid']]['班级']? $data[$item['uid']]['班级']:$place_schcls[$item['place_id']];
            }
            else
            {
                $data[$item['uid']]['班级'] =  $data[$item['uid']]['班级']? $data[$item['uid']]['班级']:$item['place_name'];
            }
                
            foreach ($subject_arr as $val)
            {
                if (!$data[$item['uid']][$subject[$val]])
                {
                    $data[$item['uid']][$subject[$val]]=0;
                }
            }
            $data[$item['uid']][$subject[$item['subject_id']]]=$item['test_score'];
        }
        
        $list = array ();
        foreach ( $data as $uid => $item ) {
	    $list [$item ['总分']] [] = $item;
	}
		        
        header ( "Content-type:application/vnd.ms-excel" );
        header ( "Content-Disposition:attachment;filename={$exam_pid}期次成绩表.xls" );
        echo  "\t"."\t"."\t".mb_convert_encoding ( "期次:".$exam_name_str, "GBK", "UTF-8" ). "\n";
    	echo "\t"."\t"."\t". mb_convert_encoding ( "科目:".$subject_name_str, "GBK", "UTF-8" ). "\n";
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

