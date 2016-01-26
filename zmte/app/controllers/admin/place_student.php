<?php
if ( ! defined('BASEPATH'))
    exit();

class Place_student extends A_Controller
{

    private static $_table_exam_subject_paper = 'exam_subject_paper';

    public function __construct( )
    {
        parent::__construct();
        require_once (APPPATH . 'config/app/exam/log_type_desc.php');
    }

    // 场次考生列表
    public function index($place_id = 0, $page_size = '')
    {
        if ( ! $this ->check_power('exam_list,exam_manage'))
            return;
        $keyword = trim($this -> input ->get('keyword'));
        $ticket = trim($this -> input ->get('ticket'));
        $page_size = intval($page_size);

        // 查询条件
        $where = array();
        $param = array();
        $search = array();

        $place_id = intval($place_id);
        if ($place_id)
        {
            $query = $this -> db ->select(
                    'p.place_id,p.place_name,p.address,e.exam_id,e.exam_name') ->from(
                    'exam_place p') ->join('exam e' , 'p.exam_pid=e.exam_id') ->where(
                    'p.place_id' , $place_id) ->get();
            $place = $query ->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在' , 'admin/exam/index');
            return;
        }

        // 控制考场只能在未开始考试操作
        $place['no_start'] = ExamPlaceModel::place_is_no_start(
                $place_id);

        $where[] = "ps.place_id=$place_id";

        if ($keyword)
        {
            $escape_keyword = $this -> db ->escape_like_str($keyword);
            $where[] = "CONCAT(u.last_name,u.first_name) LIKE '%" .
                     $escape_keyword . "%'";
        }

        if ($ticket)
        {
            $where[] = "u.exam_ticket = $ticket";
        }
        $where = $where ? ' WHERE ' . implode(' AND ' , $where):'';

        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_place_student ps
        		LEFT JOIN {pre}student u ON ps.uid=u.uid
                LEFT JOIN {pre}school sch ON u.school_id=sch.school_id
                $where";
        $res = $this -> db ->query($sql);
        $row = $res ->row_array();
        $total = $row['nums'];

        // 读取数据
        $size = $page_size > 0 ? $page_size:15;
        $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                $_GET['page']):1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $sql = "SELECT ps.*, u.email,u.first_name,u.last_name,u.exam_ticket,u.sex,sch.school_name
                    FROM {pre}exam_place_student ps
                     LEFT JOIN {pre}student u ON ps.uid=u.uid
                     LEFT JOIN {pre}school sch ON u.school_id=sch.school_id
                    $where LIMIT $offset,$size";
            $res = $this -> db ->query($sql);
            foreach ($res ->result_array() as $row)
            {
                $list[] = $row;
            }
        }

        // 分页
        $purl = site_url('admin/place_student/index/' . $place_id) .
                         ($param ? '?' . implode('&' , $param):'');
        $data['pagination'] = multipage($total , $size , $page , $purl);

        $data['place'] = &$place;
        $data['list'] = &$list;
        $data['place_id'] = &$place_id;
        $data['page_size'] = $page_size > 0 ? $page_size:'';
        $data['priv_manage'] = $this ->check_power('exam_manage' , FALSE);

        // 模版
        $this -> load ->view('place_student/index' , $data);
    }

    // 场次试卷添加操作页面
    public function add($method = 'manual', $place_id = 0, $page_size = '')
    {
        if ( ! $this ->check_power('exam_manage'))
            return;

        $param = array();

        $place_id = intval($place_id);
        if ($place_id)
        {
            $query = $this -> db ->select(
                    'p.*,e.exam_id,e.exam_name,e.exam_pid,e.grade_id') ->from(
                    'exam_place p') ->join('exam e' , 'p.exam_pid=e.exam_id')
                    ->where( array('p.place_id'=>$place_id)) ->get();
            $place = $query ->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在' , 'admin/exam/index');
            return;
        }
        
        $subjects = ExamPlaceModel::get_exam_place_subject($place_id);
        if (empty($subjects))
        {
            message('考场学科信息不存在' , 'admin/exam/index');
            return FALSE;
        }

        foreach ($subjects as $row)
        {
            $query = $this -> db ->select('paper_id,exam_pid') ->where(
                    array('exam_id'=>$row['exam_id'])) ->order_by('rand()') ->get(
                    self::$_table_exam_subject_paper , 1);
            $arr = $query ->row_array();
            if ( ! isset($arr['paper_id']))
            {
                message('考场学科试卷信息不存在' , 'admin/exam/index');
                return FALSE;
            }
        }

        // 控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if ( ! $no_start)
        {
            message('该考场正在考试或已结束，无法做此操作');
        }

        // 是否为本考场所在的学校的考生
        $school_id = $place['school_id'];
        if ($school_id)
        {
            $query_school_id = intval($this -> input ->post('school_id'));
            if ($query_school_id)
            {
                $school_id = $query_school_id;
            }
            $param[] = "school_id=" . $school_id;
        }

        // 该考场所考到的学科
        $subject_ids = array();
        $query = $this -> db ->select('subject_id') ->from('exam_place_subject') ->where(
                array('place_id'=>$place['place_id'])) ->get();
        $subjects = $query ->result_array();

        $subject_ids = array();
        foreach ($subjects as $subject)
        {
            $subject_ids[] = $subject['subject_id'];
        }
        $subject_ids = count($subject_ids) ? implode(',' , $subject_ids):'""';

        $place['start_time'] = $place['start_time'] + 1;
        $place['end_time'] = $place['end_time'] - 1;
        // 不存在这些状态：已参加正在进行、已参加还未开始、已参加相同学科
        $not_exists_sql = "SELECT uid FROM {pre}exam_place_student ps,{pre}exam_place p WHERE ps.place_id=p.place_id AND p.place_index=$place[place_index] AND ps.uid=u.uid AND (((p.start_time >= $place[start_time] and p.start_time <= $place[end_time]) OR (p.end_time >= $place[start_time] and p.end_time <= $place[end_time]) OR (p.start_time <= $place[start_time] and p.end_time >= $place[end_time])) OR p.place_id IN(select distinct(place_id) from {pre}exam_place_subject eps where eps.subject_id in($subject_ids) and eps.exam_pid=$place[exam_id]) )";

        $tmp_sql = '';
        $data['student_name'] = $this -> input ->post('student_name');
        if ($data['student_name'])
        {
            $student_name = "'" .
                             implode("','" ,
                                    array_unique(
                                            array_filter(
                                                    explode("\n" ,
                                                            $data['student_name'])))) .
                             "'";
            $tmp_sql = " AND u.uid IN (SELECT uid FROM v_rd_student WHERE fullname IN ($student_name))";
        }

        // 统计还未安排考场的学生数量（以学校作为一个考点单位）
        $sql = "SELECT COUNT(*) nums FROM {pre}student u
                WHERE u.school_id=$school_id AND u.grade_id=$place[grade_id] AND u.is_delete=0
                AND NOT EXISTS($not_exists_sql) {$tmp_sql}";
        $row = $this -> db ->query($sql) ->row_array();
        $total = $row['nums'];
        if ($total == 0)
        {
            // message('所有学生都已经分配考场', 'admin/place_student/index/'.$place_id);
            // return;
        }

        $data['page_size'] = $page_size;

        if ($method == 'auto')
        {
            // 自动模式添加
            $tpl = 'place_student/add_auto';
        }
        else
        {
            // 手工模式添加
            $tpl = 'place_student/add_manual';

            $size = $page_size ? $page_size:15;
            $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                    $_GET['page']):1;
            $offset = ($page - 1) * $size;
            $list = array();

            // 获取所有学校里未被分配过考场的学生
            $sql = "SELECT u.uid,u.first_name,u.last_name,u.email,u.exam_ticket,u.sex,sch.school_name
                    FROM {pre}student u,{pre}school sch
                    WHERE u.school_id=sch.school_id " . ($school_id ? "AND u.school_id=$school_id" : ''). 
                    " AND u.grade_id=$place[grade_id] AND u.is_delete=0
                    AND NOT EXISTS($not_exists_sql) {$tmp_sql} LIMIT $offset,$size";

            $query = $this -> db ->query($sql);

            foreach ($query ->result_array() as $row)
            {
                $list[] = $row;
            }

            // 分页
            $purl = site_url(
                    'admin/place_student/add/manual/' . $place_id . '/' . $size .
                     ($param ? '?' . implode('&' , $param):''));
            $data['pagination'] = multipage($total , $size , $page , $purl);

            $data['list'] = &$list;
        }

        $school = SchoolModel::schoolInfo($school_id);

        $data['total'] = $total;
        $data['place'] = &$place;
        $data['school'] = &$school;

        // 模版
        $this -> load ->view($tpl , $data);
    }

    // 自动分配考生
    public function dispatch( )
    {
        if ( ! $this ->check_power('exam_manage'))
            return;

        $place_id = intval($this -> input ->post('place_id'));
        $number = intval($this -> input ->post('number'));
        if ($number < 1)
        {
            message('请填写要分配的人数');
            return;
        }
        if ($place_id)
        {
            $query = $this -> db ->select(
                    'p.*,e.exam_name,e.grade_id,sch.school_name') ->from(
                    'exam_place p') ->join('exam e' , 'p.exam_pid=e.exam_id') ->join(
                    'school sch' , 'p.school_id=sch.school_id') ->where(
                    array('p.place_id'=>$place_id)) ->get();
            $place = $query ->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在' , 'admin/exam/index');
            return;
        }

        // 控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if ( ! $no_start)
        {
            message('该考场正在考试或已结束，无法做此操作');
        }

        // 分配考生
        $list = array();
        $sql = "SELECT u.uid FROM {pre}student u
                WHERE u.school_id=$place[school_id] AND u.grade_id=$place[grade_id] AND u.is_delete=0
                AND NOT EXISTS(SELECT uid FROM {pre}exam_place_student ps,{pre}exam_place p WHERE ps.place_id=p.place_id AND p.school_id=$place[school_id] AND p.place_index=$place[place_index] AND ps.uid=u.uid)
                LIMIT 0, $number";
        $query = $this -> db ->query($sql);
        foreach ($query ->result_array() as $row)
        {
            $row['place_id'] = $place_id;
            $list[] = $row;
        }
        if ($list)
        {
            $this -> db ->insert_batch('exam_place_student' , $list);
        }
        message('分配完成' , 'admin/place_student/index/' . $place_id);
    }

    public function insert( )
    {
        if ( ! $this ->check_power('exam_manage'))
            return;

        $place_id = (int)$this -> input ->post('place_id');
        if ($place_id)
        {
            $query = $this -> db ->select(
                    'p.*,e.exam_name,e.exam_id,e.exam_pid,e.grade_id,sch.school_name') ->from(
                    'exam_place p') ->join('exam e' , 'p.exam_pid=e.exam_id') ->join(
                    'school sch' , 'p.school_id=sch.school_id') ->where(
                    array('p.place_id'=>$place_id)) ->get();
            $place = $query ->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在' , 'admin/exam/index');
            return;
        }

        $ids = $this -> input ->post('ids');
        if (empty($ids) or  ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        // 控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if ( ! $no_start)
        {
            message('该考场正在考试或已结束，无法做此操作');
        }

        $ids = my_intval($ids);
        $school_id = (int)$this -> input ->post('school_id');

        $inserts = array();

        // 该考场所考到的学科
        $subject_ids = array();
        $query = $this -> db ->select('subject_id') ->from('exam_place_subject') ->where(
                array('place_id'=>$place['place_id'])) ->get();
        $subjects = $query ->row_array();
        $subject_ids = array();
        foreach ($subjects as $subject)
        {
            $subject_ids[] = $subject['subject_id'];
        }
        $subject_ids = count($subject_ids) ? implode(',' , $subject_ids):'""';

        $place['start_time'] = $place['start_time'] + 1;
        $place['end_time'] = $place['end_time'] - 1;
        $not_exists_sql = "SELECT uid FROM {pre}exam_place_student ps,{pre}exam_place p WHERE ps.place_id=p.place_id AND p.place_index=$place[place_index] AND ps.uid=u.uid AND (((p.start_time >= $place[start_time] and p.start_time <= $place[end_time]) OR (p.end_time >= $place[start_time] and p.end_time <= $place[end_time]) OR (p.start_time <= $place[start_time] and p.end_time >= $place[end_time])) OR p.place_id IN(select distinct(place_id) from {pre}exam_place_subject eps where eps.subject_id in($subject_ids) and eps.exam_pid=$place[exam_id]))";

        $sql = "SELECT u.uid FROM {pre}student u
                WHERE u.school_id=$school_id AND u.grade_id=$place[grade_id] AND u.is_delete=0 AND u.uid IN(" .
             my_implode($ids) . ")
                AND NOT EXISTS($not_exists_sql)";
        $query = $this -> db ->query($sql);

        foreach ($query ->result_array() as $row)
        {
            $inserts[] = array('place_id'=>$place_id, 'uid'=>$row['uid']);
        }
        // 添加学生时分配试卷
        $this -> load ->model('cron/cron_place_student_paper_model' ,
                'cron_place_student_paper_model');
        $uids = array();
        foreach ($inserts as $key=>$val)
        {
            $uids[] = $val['uid'];
            $place_id = $val['place_id'];
        }
        $insert_data = array(
                'place_id'=>$place_id,
                'uid_data'=>json_encode($uids));
        $this -> cron_place_student_paper_model ->insert($insert_data);
        // end


        $res = 0;
        if ($inserts)
        {
            // 关闭错误信息，防止 unique index 冲突出错
            $this -> db -> db_debug = false;
            $this -> db ->insert_batch('exam_place_student' , $inserts);
            $res = $this -> db ->affected_rows();
        }

        $back_url = 'admin/place_student/add/manual/' . $place_id;
        if ($res < 1)
            message('考生添加失败' , $back_url);
        else
            message('考生添加成功' , $back_url);
    }

    // 删除考生信息
    public function batch_delete( )
    {
        if ( ! $this ->check_power('exam_manage'))
            return;

        $ids = $this -> input ->post('ids');
        $place_id = (int)$this -> input ->post('place_id');
        if (empty($ids) or  ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        $place = ExamPlaceModel::get_place($place_id);
        if ( ! count($place))
        {
            message('考场信息不存在');
            return;
        }

        // 控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if ( ! $no_start)
        {
            message('该考场正在考试或已结束，无法做此操作');
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '':$_SERVER['HTTP_REFERER'];
        if (empty($back_url) && $place_id)
        {
            $back_url = 'admin/place_student/' . $place_id;
        }
        // 删除考试已分配的试卷
        $ids_array = array_filter(array_values($ids));
        $ids_string = implode(',' , $ids_array);
        $sql = "select group_concat(uid) as uids from {pre}exam_place_student where id in($ids_string)";
        $res = $this -> db ->query($sql) ->row_array();
        $uids = $res['uids'];

        // 查询已分配的试卷编号组合
        $sql = "select  group_concat(etp_id) as etp_ids from {pre}exam_test_paper where uid in($uids) and place_id='$place_id' ";
        $res = $this -> db ->query($sql) ->row_array();
        $etp_ids = $res['etp_ids'];

        if ($etp_ids)
        {
            // 删除试卷题目
            $sql = "delete from {pre}exam_test_paper_question where etp_id in($etp_ids)";
            $this -> db ->query($sql);

            // 删除试卷答案
            $sql = "delete from {pre}exam_test_result where etp_id in($etp_ids)";
            $this -> db ->query($sql);
        }

        // 删除试卷
        $sql = "delete from {pre}exam_test_paper where uid in($uids) and place_id=$place_id";
        $this -> db ->query($sql);

        // end
        $this -> db ->where_in('id' , $ids) ->delete(
                'exam_place_student');

        message('删除成功' , $back_url);
    }

    public function load_out_student( )
    {
        $data['uid'] = trim($this -> input ->get('uid'));
        $data['place_id'] = intval($this -> input ->get('place_id'));
        $data['exam_id'] = intval($this -> input ->get('exam_id'));
        $this -> load ->view('place_student/out_student' , $data);
    }

    /*
     * 加载 踢出考生
     */
    public function out_student_save( )
    {
        $exam_ticket = trim($this -> input ->post('account'));
        $password = $this -> input ->post('password');
        $place_id = intval($this -> input ->post('place_id'));
        $exam_id = intval($this -> input ->post('exam_id'));

        $txt_student_tichu = intval($this -> input ->post('txt_student_tichu'));

        if ( ! strlen($password))
        {
            output_json(CODE_ERROR , '理由不能为空.');
        }

        if ( ! strlen($txt_student_tichu))
        {
            output_json(CODE_ERROR , '状态不能为空.');
        }

        // 检查帐号密码是否正确
        $this -> load ->model(APPPATH . 'models/exam/student_model');
        $student = $this -> student_model ->is_valid_student($exam_ticket);

        if ( ! $student)
        {
            output_json(CODE_ERROR , '该考生不存在.');
        }

        $user_id = $student['uid'];

        // 重置考生密码
        try
        {

            if ($txt_student_tichu == '1')
                $action = 'out_student';
            else
                $action = 'in_student';

            if ($action && $log_type = Log_type_desc::get_log_alia($action))
            {

                $log_content = $password;

                exam_log_1($log_type , $log_content , $user_id , $place_id ,
                        $exam_id);
            }

            $session_data = array(
                    'exam_ticket_out'=>$exam_ticket,
                    'password_out'=>$password,
                    'txt_student_tichu'=>$txt_student_tichu);

            $this -> session ->set_userdata($session_data);

            ExamPlaceModel::out_exam_place_student($place_id , $user_id ,
                    $password , $txt_student_tichu);

            if ($txt_student_tichu == 1)
                output_json(CODE_SUCCESS ,
                        '<p></p><p>踢出成功, 该考生考试信息为：</p><p><strong>准考证号：</strong>' .
                             $exam_ticket . ' </p>');
            else
                output_json(CODE_SUCCESS ,
                        '<p></p><p>恢复成功, 该考生考试信息为：</p><p><strong>准考证号：</strong>' .
                         $exam_ticket . ' </p>');
        }
        catch (Exception $e)
        {
            output_json(CODE_ERROR ,
                    '<p></p><p>踢出失败，请重试(如多次出现类似情况，请联系系统管理员)</p>');
        }
    }

    /* +-------------------------------------- 分层测试 ---------------------------------+ */

    /**
     *  导入页面
     *
     * @return void
     */
    public function import_hierarchy($exam_id, $place_id)
    {
        $data = array();
        $data['place_id'] = $place_id;
        $data['exam_id'] = $exam_id;
        $data['subjects'] = C('subject');
        $data['place_subjects'] = ExamPlaceSubjectModel::get_exam_place_subject_list(array('place_id'=>$place_id));
        $this->load->view('place_student/import_hierarchy', $data);
    }

    /**
     *  导入动作
     *
     * @return void
     */
    public function import_save_hierarchy()
    {
        $post = $this->input->post();

        /* 上传文件 */
        $config['upload_path'] = '../../cache/excel/';
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024 * 10; // 单位kb
        $config['overwrite'] = false;
        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('file')) {
            $error = $this->upload->display_errors();
            echo $error;exit;
        } else {

            /* 实时输出导入结果 */
            ob_end_flush();
            set_time_limit(0);

            $upload_data = $this->upload->data();

            /* 读取excel */
            $this->load->library('PHPExcel');
            $this->load->library('PHPExcel/IOFactory');
            $inputFileType = IOFactory::identify($upload_data['file_relative_path']);
            $objReader = IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($upload_data['file_relative_path']);
            $data = $objPHPExcel->getSheet(0)->toArray();

            /* 期次 考场 学科 */
            $subject_id = $this->input->post('subject_id');
            $place_id = $this->input->post('place_id');
            $exam_pid = $this->input->post('exam_id');

            /* 学科 => 考试期次 */
            $sql = "select exam_id from {pre}exam where exam_pid={$exam_pid} and subject_id={$subject_id}";
            $exam_subject = $this->db->query($sql)->row_array();

            if (empty($exam_subject) || !isset($exam_subject['exam_id'])) {
                echo '当前考试期次下未查询到此学科<hr/>';
                exit;
            }

            $exam_id  = $exam_subject['exam_id'];

            /* 试卷信息 */
            $sql = "select * from {pre}exam_paper where exam_id={$exam_id} and subject_id={$subject_id}";
            $paper = $this->db->query($sql)->row_array();

            if (empty($paper) || !isset($paper['paper_id'])) {
                echo '未查询到当前试卷信息<hr/>';;
                exit;
            }

            /* 计时开始 */
            $start_time = microtime(true);

            /* 导入规则 */
            $ignore_line = array(0);
            $map_column = array(
                    'address' => 0,
                    'number' => 1,
                    'name' => 2,
                    'telephone' => 3,
                    'school' => 4,
                    'grade' => 5,
                    'class' => 6,
                    'paper' => 7,
                    'score_total' => 8,
                    'question_1' => 9,
                    'question_2' => 10,
                    'question_3' => 11,
                    'question_4' => 12,
                    'question_5' => 13,
                    'question_6' => 14,
                    'question_7' => 15,
                    'question_8' => 16,
                    'question_9' => 17,
                    'question_10' => 18,
                    'question_11' => 19,
                    'question_12' => 20,
                    'question_13' => 21,
                    'question_14' => 22,
                    'question_15' => 23,
                    'question_16' => 24,
                    'question_17' => 25,
                );

            /* 循环插入数据 */
            foreach ($data as $key => $value) {
                if (in_array($key, $ignore_line)) {
                    echo '跳过第' . ($key + 1) . '行<hr/>';
                    flush();
                    continue;
                }

                $number = $value[$map_column['number']];
                $name = $value[$map_column['name']];

                /* 核对学生信息 1.是否存在 2.姓名是否对应 3.是否在当前考试期次下*/
                $sql = "select uid,concat(last_name, first_name) as name from {pre}student where external_account='{$number}'";
                $student = $this->db->query($sql)->row_array();

                if (empty($student) || !isset($student['uid'])) {
                    echo '未查询到考生信息，在第' . ($key + 1) . '行<hr/>';
                    flush();
                    continue;
                }

                if ($student['name'] != $name) {
                    echo '查询到的姓名与表格中不符，在第' . ($key + 1) . '行<hr/>';
                    flush();
                    continue;
                }

                $uid = $student['uid'];
                $sql = "select id from {pre}exam_place_student where uid='{$uid}' and place_id='{$place_id}'";
                $student_place = $this->db->query($sql)->row_array();

                if (empty($student_place) || !isset($student_place['id'])) {
                    echo '考生未在当前考试期次中，在第' . ($key + 1) . '行<hr/>';
                    flush();
                    continue;
                }

                /* 试卷分配 */
                $sql = "select * from {pre}exam_test_paper where exam_id={$exam_id} and place_id={$place_id} and subject_id={$subject_id} and uid={$uid}";
                $exam_test_paper = $this->db->query($sql)->row_array();

                if (empty($exam_test_paper) || !isset($exam_test_paper['etp_id'])) {
                    echo '当前考生未分配试卷在第' . ($key + 1) . '行<hr/>';
                    flush();
                    continue;
                }

                $question_sort = json_decode($paper['question_sort'], true);
                $question_score = json_decode($paper['question_score'], true);

                /* 插入成绩 */
                foreach ($question_sort as $k => $v) {
                    /* 补全数据 */
                    $data = array();
                    $data['etp_id'] = $exam_test_paper['etp_id'];
                    $data['exam_pid'] = $exam_test_paper['exam_pid'];
                    $data['exam_id'] = $exam_test_paper['exam_id'];
                    $data['uid'] = $uid;
                    $data['paper_id'] = $exam_test_paper['paper_id'];
                    $data['ques_id'] = $v;
                    $data['ques_index'] = $k + 1;
                    $data['full_score'] = $question_score[$v][0];
                    $data['test_score'] = $value[$map_column['question_' . ($k + 1)]];

                    $map = $data;
                    unset($map['full_score']);
                    unset($map['test_score']);

                    $etr = $this->db->where($map)->select()->get('{pre}exam_test_result')->row_array();

                    if (!empty($etr) && isset($etr['etr_id'])) {
                        /* 更新数据 */
                        $rst = $this->db->where('etr_id', $etr['etr_id'])->update('{pre}exam_test_result', $data);
                    } else {
                        /* 写入数据库 */
                        $rst = $this->db->insert('{pre}exam_test_result', $data);
                    }

                    if ($rst) {
                        echo '<span color="green">成绩导入成功！，在第' . ($key + 1) . '行，第' . $k . '题<span><hr/>';
                        flush();
                    } else {
                        echo '成绩导入失败！，在第' . ($key + 1) . '行，第' . $k . '<hr/>';
                        flush();
                    }
                }
            }

            $end_time = microtime(true);
            $execute_time = $end_time - $start_time;
            echo '导入成功！用时：' . $execute_time . 's';
            flush();
        }
    }

    /* +-------------------------------------- 分层测试结束 ---------------------------------+ */

    /**
     * desription 批量导入学生分数
     * @author
     * @final
     */
    public function import($exam_id)
    {
        $data = array();
        $data['exam_id'] = $exam_id;
        $data['subjects'] = C('subject');
        $data['place_subjects'] = ExamPlaceSubjectModel::get_exam_place_subject_list(
            array('exam_pid'=>$exam_id), 1, time(), null, 'DISTINCT(subject_id) AS subject_id');
        $this -> load ->view('place_student/import', $data);
    }

    public function import_save()
    {
        $post = $this->input->post();
        
        $subject_id = $this->input->post('subject_id');
        if (!Validate::isInt($subject_id))
        {
            message('请选择导入成绩的学科');
        }
        
        $exam_id = $this->input->post('exam_id');
        $exam = ExamModel::get_exam($exam_id, 'exam_pid, exam_ticket_maprule');
        if (!$exam || $exam['exam_pid'])
        {
            message('考试期次不存在，无法导入学生成绩！');
        }
        
        $exam_ticket_maprule = $exam['exam_ticket_maprule'];
    
        /* 上传文件 */
        $config['upload_path'] = '../../cache/excel/';
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024 * 10; // 位kb
        $config['overwrite'] = false;
        $this->load->library('upload', $config);
    
        if (!$this->upload->do_upload('file')) 
        {
            echo "传入参数不正确";
            die();
        } 
        else 
        {
            /* 实时输出导入结果 */
            ob_end_flush();
            set_time_limit(0);
            $start_time = microtime(true);
            $upload_data = $this->upload->data();
    
            /* 读取excel */
            $this->load->library('PHPExcel');
            $this->load->library('PHPExcel/IOFactory');
            $inputFileType = IOFactory::identify($upload_data['file_relative_path']);
            $objReader = IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($upload_data['file_relative_path']);
            $data = $objPHPExcel->getSheet(0)->toArray();
            
            @unlink($upload_data['file_relative_path']);
    
            $db = Fn::db();
            
            $bok = false;
            $start_time = microtime(true);
            if ($db->beginTransaction())
            {
                $sql = "CREATE TABLE IF NOT EXISTS tmp_table9700 (
                 		v int(10) NOT NULL,
                		k text NOT NULL,
            			s tinyint NOT NULL default 0,
                		place_id int(10) NOT NULL,
                        subject_id int(10) NOT NULL,
                		uid int(10) NOT NULL default 0)
                        ";
                $db->query($sql);
                
                $sql = "SELECT place_name, place_id 
                        FROM rd_exam_place
                        WHERE exam_pid = $exam_id";
                $place_ids = $db->fetchPairs($sql);
                
                $table_header = array();//表头信息
                $insert_header = array();//已插入表头的考场
                
                foreach ($data as $key => $value)
                {
                    if ($key > 3)
                    {
                        $place_name = trim($value[4]);
                        $place_id = $place_ids[$place_name];
                        if (!$place_id)
                        {
                            echo "未查询到当前考场信息，在第" . ($key + 1) . "行:" . $place_name . '<br/>';
                            continue;
                        }
                        
                        //插入每个考场的表头信息
                        if (!in_array($place_id, $insert_header))
                        {
                            $insert_header[] = $place_id;
                            foreach ($table_header as $bind)
                            {
                                $bind['place_id'] = $place_id;
                                $db->replace('tmp_table9700', $bind);
                            }
                        }
                        
                        $student_ticket = exam_ticket_maprule_encode(
                            str_replace("'", '', trim($value[0])), $exam_ticket_maprule);
                        
                        $sql = "SELECT s.uid FROM rd_student s
                                LEFT JOIN rd_exam_place_student eps ON eps.uid = s.uid
                                WHERE s.exam_ticket = '{$student_ticket}' 
                                AND eps.place_id='{$place_id}'";
                        $uid = $db->fetchOne($sql);
                        if (!$uid)
                        {
                            echo "未查询到当前用户数据，在第" . ($key + 1) . "行:" . $value[1] . '<br/>';
                            continue;
                        }
                        
                        $bind = array(
                            'v' => $key,
                            'k' => json_encode($value),
                            's' => 0,
                            'place_id'   => $place_id,
                            'subject_id' => $subject_id,
                            'uid'        => $uid,
                        );
                        
                        $db->replace('tmp_table9700', $bind);
                    }
                    else if (in_array($key, array(1, 3)))
                    {
                        $table_header[] = array(
                            'v' => $key,
                            'k' => json_encode($value),
                            's' => 0,
                            'place_id'   => '',
                            'subject_id' => $subject_id,
                            'uid'        => 0,
                        );
                    }
                }
                
                $bok = $db->commit();
                if (!$bok)
                {
                    $db->rollBack();
                }
            }
    
            $end_time = microtime(true);
            $execute_time = $end_time - $start_time;
            if (!empty($data))
            {
                echo '文件载入成功！执行时间：' . sprintf('%.4f', $execute_time) . 's' .
                    '<hr/>';
                flush();
            }
            
            $end_time = microtime(true);
            $execute_time = $end_time - $start_time;
            if ($bok)
            {
                echo '导入成功！用时：' . $execute_time . 's';
            }
            else
            {
                echo '导入失败！用时：' . $execute_time . 's';
            }
            flush();
        }
    }

    /**
     * 批量导入学生
     * @param   int    $place_id   考场id
     * @author  TCG
     * @final   2015-03-02
     */
    public function import_student($place_id)
    {
        if (!$place_id)
        {
            message('考场不存在');
        }
        
        if ($this->db->get_where('exam_place', array('start_time <='=>time(), 
                'place_id'=>$place_id))->row_array())
        {
            message('该考场正在考试或已结束，无法做此操作', '/admin/place_student/index/16');
        }
        
        $data = array();
        $data['place_id'] = $place_id;
        $data['grade'] = C('grades');

        $data['exam'] = $this->db->select('e.exam_name,ep.place_name,ep.address,
                        e.exam_id,e.grade_id,ep.school_id,s.school_name')
                        ->from('rd_exam e')
                        ->join('rd_exam_place ep', "e.exam_id=ep.exam_pid", 'left')
                        ->join('rd_school s', "s.school_id=ep.school_id", 'left')
                        ->where('place_id', $place_id)->get()->row_array();

        $this->load->view('place_student/import_student', $data);
    }

    /**
     * 根据excel导入学生并将导入的学生加入考场中
     */
    public function import_student_save()
    {
        set_time_limit(0);
        
        $place_id = intval($this->input->post('place_id'));
        if (!$place_id)
        {
            message('考场不存在');
        }
        
        if ($this->db->get_where('exam_place', array('start_time <='=>time(),
                'place_id'=>$place_id))->row_array())
        {
            message('该考场正在考试或已结束，无法做此操作', '/admin/place_student/index/'. $place_id);
        }
        
        $message = array();
        
        $school_id = intval($this->input->post('school_id'));
        if (!$school_id)
        {
            $message[] = '考场地址有错误';
        }
        
        $start_line = intval($this->input->post('start_line'));
        if ($start_line < 1)
        {
            $message[] = '请输入学生信息在Excel文件开始的行';
        }
        
        $fullname_column = intval($this->input->post('fullname_column'));
        if ($fullname_column < 1)
        {
            $message[] = '请输入姓名在Excel文件的列';
        }
        
        $exam_ticket_column = intval($this->input->post('exam_ticket_column'));
        if ($exam_ticket_column < 1)
        {
            $message[] = '请输入准考证号在Excel文件的列';
        }
        
        if ($fullname_column && $exam_ticket_column 
            && $fullname_column == $exam_ticket_column)
        {
            $message[] = '姓名和准考证号在Excel文件中不能为同一列';
        }

        if (!$_FILES['file'])
        {
            $message[] = '请选择导入的Excel文件';
        }
        
        $grade_id = intval($this->input->post('grade_id'));
        $mobile_column = intval($this->input->post('mobile_column'));
        $school_column = intval($this->input->post('school_column'));
        $auto_set_paper = intval($this->input->post('auto_set_paper'));
        $import_tables = array_filter(explode(',', $this->input->post('import_table')));
        
        $schools = array();
        $school_names = $this->input->post('school_key');
        if ($school_names)
        {
            $school_ids = $this->input->post('school_ids');
            
            foreach ($school_names as $key => $name)
            {
                $name = str_replace(' ', '', $name);
                $sch_id = isset($school_ids[$key]) ? intval($school_ids[$key]) : 0;
                if ($sch_id > 0)
                {
                    $schools[$name] = $sch_id;
                }
                else
                {
                    $message[] = $name . "对应的学校ID不能为空";
                }
            }
        }
        
        if ($message)
        {
            message(implode('<br>', $message));
        }
        
        /**
         * 上传文件
         */
        $upload_path = '../../cache/excel/';
        $file_name = microtime(true) . '.' . end(explode('.', $_FILES['file']['name']));
        $upload_file = $upload_path . $file_name;
        if (!is_dir($upload_path))
        {
            mkdir($upload_path, '0777', true);
        }

        if (!@move_uploaded_file($_FILES['file']['tmp_name'], $upload_file))
        {
            message('导入文件失败，请重新导入！');
        }
        else 
        {
            $exam = $this->db->from('rd_exam e')
                    ->join('rd_exam_place ep', "e.exam_id=ep.exam_pid", 'left')
                    ->where('place_id', $place_id)->get()->row_array();
            
            $grade_id = $grade_id ? $grade_id : $exam['grade_id'];
    
            if (!$school_column)
            {
                $school = $this->db->get_where('school',
                        array('school_id'=>$school_id))->row_array();
            }
    
            $place_student = $this->db->get_where('rd_exam_place_student', 
                    array('place_id'=>$place_id))->result_array();
            $place_uids = array();
            foreach ($place_student as $val)
            {
                $place_uids[] = $val['uid'];
            }
                
            $uids = array(); //未加入考场的学生
            //导入结果信息统计
            $stat = array(
                'total' => 0,
                'success' => 0,
                'fail' => 0,
                'exist' => 0
            );
            
            /**
             * 读取excel
            */
            $this->load->library('PHPExcel');
            $this->load->library('PHPExcel/IOFactory');
            $inputFileType = IOFactory::identify($upload_file);
            $objReader = IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($upload_file);
            
            $sheetcount = $objPHPExcel->getSheetCount();
            
            for ($i = 0; $i < $sheetcount; $i++)
            {
                if ($import_tables && !in_array($i+1, $import_tables))
                {
                	continue;
                }
                
                $list = array_filter($objPHPExcel->getSheet($i)->toArray());
                if (!empty($list))
                {
                    $line_count = count($list);
                    for ($j = $start_line - 1; $j < $line_count; $j++)
                    {
                        $list[$j] = array_filter($list[$j]);
                        if (empty($list[$j]))
                        {
                            continue;
                        }
                        
                        $student_name = str_replace(' ', '', $list[$j][$fullname_column - 1]);
                        $external_exam_ticket = trim($list[$j][$exam_ticket_column - 1]);
                        
                        if (!$student_name || !$external_exam_ticket)
                        {
                            continue;
                        }
                        
                        $stat['total']++;
                        
                        if (empty($student_name))
                        {
                            $message['fail']['student_name'][] = $external_exam_ticket;
                            $stat['fail']++;
                            
                            continue;
                        }
                        
                        if (empty($external_exam_ticket))
                        {
                            $message['fail']['exam_ticket'][] = $student_name;
                            $stat['fail']++;
                            
                            continue;
                        }
                        
                        $exam_ticket = exam_ticket_maprule_encode($external_exam_ticket, $exam['exam_ticket_maprule']);
                        if (!is_numeric($exam_ticket))
                        {
                            $message['fail']['exam_ticket_error'][] = $student_name . "-" . $external_exam_ticket;
                            $stat['fail']++;
                            
                            continue;
                        }
    
                        //判断准考证号是否已注册
                        if ($tmp_student = $this->db->select('uid')->from('student')
                                           ->where('exam_ticket', $exam_ticket)->get()->row_array())
                        {
                            $message['exist'][] = $student_name . "-" . $external_exam_ticket;
                            
                            $stat['exist']++;
                            
                            if (!in_array($tmp_student['uid'], $place_uids))
                            {
                                $this->db->replace('exam_place_student', 
                                        array('place_id'=>$place_id, 'uid'=>$tmp_student['uid']));
                                
                                $uids[] = $tmp_student['uid'];
                            }
                        }
                        else
                        {
                            $mobile = '';
                            if ($mobile_column && is_phone($list[$j][$mobile_column-1]))
                            {
                                $mobile = $list[$j][$mobile_column-1];
                            }
                            
                            if ($school_column && $schools)
                            {
                                $sch_name = str_replace(' ', '', $list[$j][$school_column-1]);
                            	$school_id = intval($schools[$sch_name]);
                            	if (!isset($school_info[$school_id]))
                            	{
                            	    $school_info[$school_id] = $this->db->get_where('school',
                            	            array('school_id'=>$school_id))->row_array();
                            	}
                            	
                            	$school = $school_info[$school_id];
                            	if (!$school)
                            	{
                            	    message('学校“'.$sch_name.'”信息不存在，请设置学校对应的学校ID！');
                            	}
                            }
                            
                            $insert_data = array(
                                    'email' => $exam_ticket . "@mail.exam.new-steps.com",
                                    'first_name' => mb_substr($student_name, 1, strlen($student_name), 'utf-8'),
                                    'last_name' => mb_substr($student_name, 0, 1, 'utf-8'),
                                    'exam_ticket' => $exam_ticket,
                                    'external_account' => $external_exam_ticket,
                                    'maprule'  => $exam['exam_ticket_maprule'],
                                    'password' => my_md5(($exam['exam_ticket_maprule'] ? $external_exam_ticket : '123456')),
                                    'mobile'   => $mobile,
                                    'grade_id' => $grade_id,
                                    'province' => $school['province'],
                                    'city' => $school['city'],
                                    'area' => $school['area'],
                                    'school_id' => $school_id,
                                    'source_from' => '2',
                                    'addtime' => time(),
                            );
                            
                            $this->db->insert('student', $insert_data);
                            $uid = $this->db->insert_id();
    
                            if ($uid)
                            {
                                $stat['success']++;
                                
                                $this->db->replace('exam_place_student', 
                                        array('place_id'=>$place_id, 'uid'=>$uid));
                                
                                $uids[] = $uid;
                            }
                            else
                            {
                                $stat['fail']++;
                                
                                $message['fail']['insert_fail'][] = $student_name . "-" . $external_exam_ticket;// . '(' . $this->db->last_query() . ')';
                            }
                        }
                    }
                }
            }
            
            //新加入考场的学生加入分配试卷计划任务中
            if ($auto_set_paper && $uids)
            {
                $insert_data = array();
                
                $insert_data['place_id'] = $place_id;
                $insert_data['uid_data'] = json_encode($uids);
                $insert_data['status'] = 0;
                $insert_data['c_time'] = time();
                $insert_data['u_time'] = time();
                $this->db->insert('cron_task_place_student_paper', $insert_data);
            }
            
            @unlink($upload_file);
            
            $data = array();
            $data['place_id'] = $place_id;
            $data['message'] = $message;
            $data['stat'] = $stat;
            $this->load->view('place_student/import_student_result', $data);
        }
    }
}
