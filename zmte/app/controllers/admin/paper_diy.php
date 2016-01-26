<?php

// +------------------------------------------------------------------------------------------
// | Author: TCG <TCG_love@163.com>
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 自助组卷 Dates: 2015-01-27
// +------------------------------------------------------------------------------------------

if (!defined('BASEPATH')) exit();

class Paper_diy extends A_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* 权限认证 */
        if (!$this->check_power('paper_diy')) {
            message('没有权限！');
        };
    }

    /**
     * 试卷列表
     *
     * @return void
     **/
    public function index()
    {
        /* 搜索 */
        $param = array();
        $search = array();

        /* 搜索条件 */
        if (isset($_GET['begin_time']) && !empty($_GET['begin_time'])) {
            $param['addtime >='] = strtotime($_GET['begin_time'].'00:00:59');
            $search['begin_time'] = $_GET['begin_time'];
        }

        if (isset($_GET['end_time']) && !empty($_GET['end_time'])) {
            $param['addtime <='] = strtotime($_GET['end_time'].'23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }

        if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
            $param['paper_name like'] = "%" . $_GET['keyword'] . "%";
            $search['keyword'] = $_GET['keyword'];
        }

        /* 如果存在试卷ID 清除其他条件 */
        if (isset($_GET['paper_id']) && !empty($_GET['paper_id'])) {
            $param = array();
            $param['paper_id'] = intval($_GET['paper_id']);
            $search['paper_id'] = $_GET['paper_id'];
        }

        /* 如果存在试卷ID 清除其他条件 */
        if (isset($_GET['subject_id']) && !empty($_GET['subject_id'])) {

            $param['subject_id'] = intval($_GET['subject_id']);
            $search['subject_id'] = $_GET['subject_id'];
        }


        /* 默认过滤条件 只能看到自己的试卷 */
        if(!$this->session->userdata('is_super'))
        {
            $admin_info = $this->session->all_userdata();

            if (empty($admin_info['admin_id'])) {
                message('获取管理员数据失败，请从新登陆后重试！');
            }

            $param['admin_id'] = $admin_info['admin_id'];
        }
        else 
        {
            $param['admin_id >'] = '0';
        }
        /* 分页 */
        $number = 15;
        $total = PaperModel::count_papers($param);

        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $start = ($page - 1) * $number;
        $purl = site_url('admin/paper_diy/index') . ($param ? '?'.implode('&',$param) : '');
        /* 试卷数据 */
        $papers = PaperModel::get_papers('paper_id,exam_id,paper_name,addtime,admin_id,ques_num,exam_id,subject_id', $param, $start, $number);
        
        foreach ($papers as $key => $paper) 
        {
            if ($exam_pid = ExamModel::get_exam($paper['exam_id'], 'exam_pid'))
            {
                $papers[$key]['is_mini_test'] = ExamModel::is_mini_test($exam_pid);
            }
            
            $papers[$key]['has_tested'] = $this->is_super_user() ? false : ExamPlaceSubjectModel::exam_subject_has_test_action($paper['exam_id']);
            
            if ($paper['admin_id'] > 0) 
            {
                $sql = "select admin_id,admin_user,realname from {pre}admin where admin_id={$paper['admin_id']}";
                $papers[$key]['admin_info'] = $this->db->query($sql)->row_array();
            } else {
                $papers[$key]['admin_info']['realname'] = '系统';
            }
        }

        $subject_type   = CpUserModel::get_allowed_subjects();

        $data = array();
        $data['papers'] = $papers;
        $data['pagination'] = multipage($total, $number, $page, $purl);
        $data['search'] = $search;
        $data['subject_type'] = $subject_type;
        $this->load->view('paper_diy/list', $data);
    }

    /**
     * 添加试卷
     *
     * @return void
     **/
    public function add_paper()
    {
        /* 学科 */
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper_diy/index';
        
        $data['exam_list'] = $this->u_exam_list();
        $data['type'] = 'add';
        $data['back_url'] = $back_url;
        $this->load->view('paper_diy/edit', $data);
    }

    /**
     * 编辑试卷
     *
     * @return void
     **/
    public function edit_paper($id)
    {
        /* 学科 */
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper_diy/index';
        
        $paper = PaperModel::get_paper_by_id($id);
        $admin_info = $this->session->all_userdata();
        if ($paper['admin_id'] != $admin_info['admin_id']
            && !$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }

        if (empty($paper)) 
        {
            message('参数错误，请重试！');
        }

        $data['exam_list'] = $this->u_exam_list();
        $data['paper'] = $paper;
        $data['back_url'] = $back_url;
        $data['type'] = 'edit';
        $this->load->view('paper_diy/edit', $data);
    }


    /**
     * 更新试卷
     *
     * @return void
     **/
    public function update_paper()
    {
        $post = $this->input->post();

        /* 当前用户id */
        $admin_info = $this->session->all_userdata();

        if (empty($admin_info['admin_id'])) 
        {
            message('获取管理员数据失败，请从新登陆后重试！');
        }
        
        $back_url = isset($post['back_url']) ? $post['back_url'] : site_url('admin/paper_diy/index');
        
        $row = array();
        if ($post['type'] == 'add') 
        {
            $exam_subject = explode('_', $post['exam_subject']);
            $row['exam_id'] = (int) $exam_subject[0];
            $row['subject_id'] = (int) $exam_subject[1];
            
            $row['paper_name'] = $post['title'];
            $row['ques_num'] = 0;
            $row['qtype_ques_num'] = '';
            $row['difficulty'] = 0;
            $row['is_delete'] = 0;
            $row['uptime'] = time();
            $row['addtime'] = time();
            $row['admin_id'] = $admin_info['admin_id'];

            $row['question_score'] = 0;   //数据表非空字段初始化
            $row['question_sort'] = 0;

            $rst = PaperModel::insert_paper($row);

            if ($rst) {
                message('添加成功 !', $back_url);
            } else {
                message('添加失败！请重试！');
            }

        } else {
            $paper_id = $post['paper_id'];
            $paper = PaperModel::get_paper_by_id($paper_id);
            /* 验证试题是否可以修改 */

            if ($paper['admin_id'] != $admin_info['admin_id'] 
                && !$admin_info['is_super']) 
            {
                message('没有当前试卷的编辑权限！', $back_url);
            }

            $row['paper_name'] = $post['title'];
            $row['uptime'] = time();

            $rst = PaperModel::update_paper($paper_id, $row);

            if ($rst) {
                message('修改成功！', $back_url);
            } else {
                message('修改失败！请重试！');
            }
        }
    }

    /**
     * @description 批量删除试卷
     * @author
     * @final
     * @param int $ids 试卷id
     * @param int $exam_id 考试期次id
     */
    public function delete_batch()
    {
        $admin_info = $this->session->all_userdata();
        /* 录入人员 只能查看自己录入的题目 管理员可以看到所有题目 */
        $admin_id = $this->session->userdata('admin_id');
        if (!$admin_id) {
            message('获取管理员数据失败，请从新登陆后重试！');
        }
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }
        //print_r($ids) ;
        $tmp_ids = array();
        $count_success = 0;
        $count_fail = 0;
        foreach ($ids as $id) {
            $paper = PaperModel::get_paper_by_id($id);
            if (QuestionModel::paper_has_test_action($id)
            ||$paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super']
            ||empty($paper) ) {
                $count_fail++;
            } else {
                $count_success++;
                $tmp_ids[] = $id;
            }
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper_diy/index';
        if (count($tmp_ids)>0)
        {

            $this->db->where_in('paper_id', $tmp_ids)->delete('exam_paper');

            admin_log('delete', 'exam_paper', implode(',', $tmp_ids));
        }
        
        message("批量操作完成,成功删除:".$count_success."个，失败:".$count_fail."个 （可能原因：这些试卷已经被考生考过 或者 正在被考中 或者试卷不存在 或者没有权限)。", $back_url);
    }

    /**
     * 删除试卷
     *
     * @return void
     **/
    public function remove_paper($paper_id)
    {
        $paper = PaperModel::get_paper_by_id($paper_id);

        if (empty($paper)) {
            message('未查询到当前试卷，请重试！');
        }
        $admin_info = $this->session->all_userdata();
        if ($paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }

        /* 录入人员 只能查看自己录入的题目 管理员可以看到所有题目 */
        $admin_id = $this->session->userdata('admin_id');

        if (!$admin_id) {
            message('获取管理员数据失败，请从新登陆后重试！');
        }


        //判断试卷是否正在考试或者已经考试结束
        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($paper['exam_id']);
        if($has_tested)
        {
            message('试卷已经在考试中！');
        }
        /* 删除试卷信息 */
        $rst = PaperModel::delete_paper($paper_id);
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper_diy/index';
        if (!$rst) {
            message('删除试卷失败！请重试！');
        } else {
            message('删除试卷信息成功！', $back_url);
        }
    }

    /**
     * 预览试卷
     *
     * @return void
     **/
    public function detail2($paper_id)
    {

        $paper = PaperModel::get_paper_by_id($paper_id);

        if (empty($paper)) {
            message('未查询到当前试卷，请重试！');
        }
        $admin_info = $this->session->all_userdata();
        if ($paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }
        $questions_arr = json_decode($paper['question_sort'], true);
        $questions_score = json_decode($paper['question_score'], true);


        if (empty($questions_arr)) {
            echo "当前试卷暂无试题！";exit;
        }

        /* 查询试题信息 */
        $sql = "SELECT ques_id,type,title,picture,answer FROM {pre}question WHERE ques_id in (". implode(',', $questions_arr) .")";
        $questions_tmp = $this->db->query($sql)->result_array();
        $sort = array();

        /* 重新排序 */
        foreach ($questions_arr as $v) {
            foreach ($questions_tmp as $value) {
                if ($v == $value['ques_id']) {
                    $sort[] = $value;
                }
            }
        }

        $questions = array();
        $tmp_index = 0;

        foreach ($sort as $key=>$row) {
            $row['title'] = $this->_format_question_content($row['title'] , in_array($row['type'],array(3,9)));
            $row['score'] = $questions_score[$row['ques_id']][0];
            switch($row['type']){
                case 0:

                    $row['children'] = QuestionModel::get_children($row['ques_id']);

                    foreach ($row['children'] as &$child) {
                        $child['title'] = $this->_format_question_content($child['title'] , in_array($child['type'],array(3,9)));
                    }

                    break;
                case 1:
                case 2:
                case 7:
                    $row['options'] = QuestionModel::get_options($row['ques_id']);

                    break;
                case 3:
                case 9:
                    $row['answer'] = explode("\n", $row['answer']);
                    break;
                case 4:
                case 5:
                case 6:
                case 8:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    break;
                default;
            }

            if ($key <= 0) {
                $questions[$tmp_index]['type'] = $row['type'];
                $questions[$tmp_index]['questions'][] = $row;
                $questions[$tmp_index]['scores'] += $questions_score[$row['ques_id']][0];
            } else {
                if ($row['type'] == $sort[$key-1]['type']) {
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += $questions_score[$row['ques_id']][0];
                } else {
                    $tmp_index++;
                    $questions[$tmp_index]['type'] = $row['type'];
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += $questions_score[$row['ques_id']][0];
                }
            }
        }

        $data = array();
        $subject = C('subject');
        $data['subject'] = $subject;
        $data['qtypes'] = C('qtype');
        $data['questions'] = $questions;
        $data['paper'] = $paper;
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十');
        $data['back_url'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : site_url('admin/paper_diy/index');
        $paper_detail = ExamPaperModel::detail_sg($paper_id, 1, 1);

        $data['paper'] = $paper_detail;

        /* 是否有权限查看试卷列表 */
        $is_exam_paper_list = $this->check_power('exam_list', false);
        $data['is_exam_paper_list'] = $is_exam_paper_list;

        $this->load->view('paper_diy/detail2', $data);
    }
    /**
     * 预览试卷
     *
     * @return void
     **/
    public function detail($paper_id)
    {

        $paper = PaperModel::get_paper_by_id($paper_id);

        if (empty($paper)) {
            message('未查询到当前试卷，请重试！');
        }
        $admin_info = $this->session->all_userdata();
        if ($paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }
        $questions_arr = json_decode($paper['question_sort'], true);
        $questions_score = json_decode($paper['question_score'], true);

        if (empty($questions_arr)) {
            echo "当前试卷暂无试题！";exit;
        }

        /* 查询试题信息 */
        $sql = "SELECT ques_id,type,title,picture,answer FROM {pre}question 
                WHERE ques_id in (". implode(',', $questions_arr) .")";
        $questions_tmp = $this->db->query($sql)->result_array();
        $sort = array();

        /* 重新排序 */
        foreach ($questions_arr as $v) {
            foreach ($questions_tmp as $value) {
                if ($v == $value['ques_id']) {
                    $sort[] = $value;
                }
            }
        }

        $questions = array();
        $tmp_index = 0;

        foreach ($sort as $key=>$row) {
            $row['title'] = $this->_format_question_content($row['title'] , in_array($row['type'],array(3,9)));
            $row['score'] = array_sum($questions_score[$row['ques_id']]);
            /*
            switch($row['type']){
                case 0:
                case 15:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);

                    foreach ($row['children'] as &$child) {
                        $child['title'] = $this->_format_question_content($child['title'] , in_array($child['type'],array(3,9)));
                    }

                    break;
                case 1:
                case 2:
                case 7:
                    $row['options'] = QuestionModel::get_options($row['ques_id']);

                    break;
                case 3:
                case 9:
                    $row['answer'] = explode("\n", $row['answer']);
                    break;
                case 4:
                case 5:
                case 6:
                case 8:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    break;
                default;
            }
            */

            if ($key <= 0) {
                $questions[$tmp_index]['type'] = $row['type'];
                $questions[$tmp_index]['questions'][] = $row;
                $questions[$tmp_index]['scores'] += array_sum($questions_score[$row['ques_id']]);
            } else {
                if ($row['type'] == $sort[$key-1]['type']) {
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += array_sum($questions_score[$row['ques_id']]);
                } else {
                    $tmp_index++;
                    $questions[$tmp_index]['type'] = $row['type'];
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += array_sum($questions_score[$row['ques_id']]);
                }
            }
        }
        $data = array();
        $subject = C('subject');
        $data['subject'] = $subject;
        $data['qtypes'] = C('qtype');
        $data['questions'] = $questions;
        $data['paper'] = $paper;
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十', '十一', '十二', '十三', '十四');
        $data['back_url'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : site_url('admin/paper_diy/index');
        $paper_detail = ExamPaperModel::detail_sg($paper_id, 1, 1);

        $data['paper'] = $paper_detail;

        /* 是否有权限查看试卷列表 */
        $is_exam_paper_list = $this->check_power('exam_list', false);
        $data['is_exam_paper_list'] = $is_exam_paper_list;

        $this->load->view('paper_diy/detail', $data);
    }

    /**
     * 预览试卷
     *
     * @return void
     **/
    public function preview_paper($paper_id)
    {
        $paper = PaperModel::get_paper_by_id($paper_id);

        if (empty($paper)) {
            message('未查询到当前试卷，请重试！');
        }
        
        /*
        $admin_info = $this->session->all_userdata();
        if ($paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }
        */
        
        $questions_arr = json_decode($paper['question_sort'], true);
        $questions_score = json_decode($paper['question_score'], true);


        if (empty($questions_arr)) {
            echo "当前试卷暂无试题！";exit;
        }

        /* 查询试题信息 */
        $sql = "SELECT ques_id,type,title,picture,answer FROM {pre}question 
                WHERE ques_id in (". implode(',', $questions_arr) .")";

        $questions_tmp = $this->db->query($sql)->result_array();

        $sort = array();

        /* 重新排序 */
        foreach ($questions_arr as $v) {
            foreach ($questions_tmp as $value) 
            {
                if ($v == $value['ques_id']) {
                    $sort[] = $value;
                }
            }
        }
        $questions = array();
        $tmp_index = 0;

        foreach ($sort as $key => $row) {
            if(in_array($row['type'],array(3,9)))
            {
                $row['title'] = $this->_format_question_content($row['title'] , 1);
            }

            $row['score'] = array_sum($questions_score[$row['ques_id']]);

            switch($row['type']){
                case 0:
                case 12:
                case 13:
                case 15:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    foreach ($row['children'] as &$child) {
                        $child['title'] = $this->_format_question_content($child['title'] , in_array($child['type'],array(3,9)));
                    }

                    break;
                case 1:
                case 2:
                case 7:
                case 14:
                    $row['options'] = QuestionModel::get_options($row['ques_id']);
                    break;
                case 3:
                case 9:
                    $row['answer'] = explode("\n", $row['answer']);
                    break;
                case 4:
                case 5:
                case 6:
                case 8:
                case 10:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    break;
                default:;
            }

            if ($key <= 0) 
            {
                $questions[$tmp_index]['type'] = $row['type'];
                $questions[$tmp_index]['questions'][] = $row;
                $questions[$tmp_index]['scores'] += $row['score'];
            } 
            else 
            {
                if ($row['type'] == $sort[$key-1]['type']) 
                {
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += $row['score'];
                } 
                else 
                {
                    $tmp_index++;
                    $questions[$tmp_index]['type'] = $row['type'];
                    $questions[$tmp_index]['questions'][] = $row;
                    $questions[$tmp_index]['scores'] += $row['score'];
                }
            }
        }
        
        $data = array();
        $subject = C('subject');
        $data['subject'] = $subject;
        $data['qtypes'] = C('qtype');
        $data['questions'] = $questions;
        $data['question_score'] = $questions_score;
        $data['paper'] = $paper;
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十', '十一', '十二', '十三', '十四');

        $this->load->view('paper_diy/preview', $data);
    }

    /**
     * 试卷中试题管理
     *
     * @return void
     **/
    public function question_manage($paper_id)
    {
        /* 查询试卷信息 */
        $paper = PaperModel::get_paper_by_id($paper_id);
        $admin_info = $this->session->all_userdata();
        if (empty($paper)) {
            message('参数错误，请重试！');
        }
        if ($paper['admin_id']!=$admin_info['admin_id']&&!$admin_info['is_super'])
        {
            message('你没有该试卷的权限！');
        }
        /* 查询试题信息 调取试题相关信息 */
        $questions = array();
        $qtypes = C('qtype');
        $subjects = C('subject');

        $question_sort = json_decode($paper['question_sort'], true);
        $question_score = json_decode($paper['question_score'], true);

        if (!empty($question_sort)) {
            foreach ($question_sort as $key => &$value) {
/*
                $sql = "SELECT count(*) as 'c' FROM rd_exam_question WHERE ques_id=?
                         AND paper_id=?";
                $res = Fn::db()->fetchOne($sql,array($value,$paper_id));
                if(!$res['c'])
                {
                    unset($value);
                    continue;
                }
                */

                $questions[$key] = QuestionModel::get_question($value);
                $questions[$key]['title'] = strip_tags($questions[$key]['title']);
                $questions[$key]['qtype'] = isset($qtypes[$questions[$key]['type']]) ? $qtypes[$questions[$key]['type']] : '';
                $questions[$key]['subject_name'] = isset($subjects[$questions[$key]['subject_id']]) ? $subjects[$questions[$key]['subject_id']] : '';
                $questions[$key]['addtime'] = date('Y-m-d H:i', $questions[$key]['addtime']);
                
                $questions[$key]['score'] = $question_score[$value];
                
                /* 获取试题添加者信息 */
                $admin_id = $questions[$key]['admin_id'];
                $sql = "select admin_id,admin_user,realname from {pre}admin where admin_id={$admin_id}";
                $questions[$key]['admin_info'] = $this->db->query($sql)->row_array();
            }
        } else {
            $questions = array();
        }

        $data = array();
        $data['paper_id'] = $paper_id;
        $data['paper'] = $paper;
        $data['questions'] = $questions;
        $data['has_tested'] = $this->is_super_user() ? false : ExamPlaceSubjectModel::exam_subject_has_test_action($paper['exam_id']);

        $this->load->view('paper_diy/question_manage', $data);
    }

    /**
     * 选择试题
     *
     * @return void
     **/
    public function select_question($paper_id = 0)
    {
        /* 查询条件 */
        $where  = array();
        $param = array();
        
        /* 过滤题组子题目，在题组页面管理 */
        $where[] = "q.parent_id=0";

        /* 未禁用试题 */
        $where[] = "q.is_delete<>1";

        /* 纸质考试试题 */
        //$where[] = "q.test_way IN (1, 2, 3)";

        /* 录入人员 只能查看自己录入的题目 管理员可以看到所有题目 */
        $admin_id = $this->session->userdata('admin_id');

        if (!$admin_id) {
            message('获取管理员数据失败，请从新登陆后重试！');
        }
        //$where[] = "q.admin_id='{$admin_id}'";
        //限制只能查看自己创建的试题
        if (!$this->is_super_user()
            && !CpUserModel::is_action_type_all('question', 'r')
            && !CpUserModel::is_action_type_subject('question', 'r')
            && CpUserModel::is_action_type_self('question', 'r')) {

            $where[] = "q.admin_id='{$admin_id}'";
        }

        else
        {
             $where[] = "q.admin_id>0";
        }

        //限制只能查看所属学科
        $c_subject_id = array();
        if (!$this->is_super_user()
            && !CpUserModel::is_action_type_all('question', 'r')
            && CpUserModel::is_action_type_subject('question', 'r'))
        {
            $c_subject_id = rtrim($this->session->userdata('subject_id'), ',');
            if ($c_subject_id != '')
            {

                $c_subject_id = explode(',',$c_subject_id);
                $c_subject_id = array_values(array_filter($c_subject_id));
                $c_subject_id = implode(',',$c_subject_id);
                $where[] = "q.subject_id in($c_subject_id)";
            }
        }

        //限制只能查看所属年级
        $c_grade_id = array();
        if (!$this->is_super_user()&& !$this->is_all_grade_user())
        {
            $c_grade_id = rtrim($this->session->userdata('grade_id'), ',');
            if ($c_grade_id != '')
            {
                $c_grade_id = explode(',',$c_grade_id);
                $c_grade_id = array_values(array_filter($c_grade_id));
                $c_grade_id = implode(',',$c_grade_id);
                $where_3    = " grade_id in($c_grade_id)" ;
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
        $c_class_id = array();
        if (!$this->is_super_user()&& !$this->is_all_q_type_user())
        {
            $c_q_type_id = rtrim($this->session->userdata('q_type_id'), ',');
            if ($c_q_type_id != '')
            {
                $c_q_type_id = explode(',',$c_q_type_id);
                $c_q_type_id = array_values(array_filter($c_q_type_id));
                $c_class_id    = $c_q_type_id;
                $c_q_type_id = implode(',',$c_q_type_id);
                $where_4     = " class_id in ($c_q_type_id)";
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
        
        if ($paper_id)
        {
            $paper = ExamPaperModel::get_paper_by_id($paper_id);
            
            $where[] = "q.subject_id = {$paper['subject_id']}";
            $param[] = "subject_id={$paper['subject_id']}";
            $data['subject_id'] = $paper['subject_id'];
            
            if ($paper['exam_id'])
            {
                $exam = ExamModel::get_exam($paper['exam_id'], 'grade_id, class_id');
                list($grade_id, $class_id) = array_values($exam);
                $where[] = "q.start_grade <= $grade_id AND q.end_grade >= $grade_id";
                $param[] = "grade_id=$grade_id";
                $data['grade_id'] = $exam['grade_id'];
                
                $where[] = "q.class_id LIKE '%,$class_id,%'";
                $param[] = "class_id=$class_id";
                $data['class_id'] = $exam['class_id'];
            }
        }
        else 
        {
            if ($subject_id = $this->input->get('subject_id'))
            {
                $where[] = "q.subject_id = $subject_id";
                $param[] = "subject_id=$subject_id";
            }
            
            if ($grade_id = $this->input->get('grade_id'))
            {
                $where[] = "q.start_grade <= $grade_id AND q.end_grade >= $grade_id";
                $param[] = "grade_id=$grade_id";
            }
            
            if ($class_id = $this->input->get('class_id'))
            {
                $where[] = "q.class_id LIKE '%,$class_id,%'";
                $param[] = "class_id=$class_id";
            }
        }
        
        $type = $this->input->get('type');
        if (strlen($type) > 0 && $type >= 0)
        {
            $type    = intval($type);
            $where[] = "q.type = $type";
            $param[] = "type=$type";
        }
        
        if ($is_original = $this->input->get('is_original'))
        {
            $where[] = "q.is_original = $is_original";
            $param[] = "is_original=$is_original";
        }
        
        if (Validate::isJoinedIntStr($this->input->get('ques_id')))
        {
            $search_quesid = $this->input->get('ques_id');
            $where[] = "q.ques_id IN ($search_quesid)";
            $param[] = "ques_id=$search_quesid";
        }
        
        if ($remark = $this->input->get('remark'))
        {
            $where[] = "q.remark LIKE '%$remark%'";
            $param[] = "remark=$remark";
        }

        $where = $where ? ' WHERE '.implode(' AND ', $where) : ' 1 ';

        /* 统计数量 */
        $nums = QuestionModel::get_question_nums($where);
        $total = $nums['total'];

        /* 读取数据 */
        $size   = 15;
        $page   = $this->input->get('page') ? intval($this->input->get('page')) : 1;
        $offset = ($page - 1) * $size;
        $list = array();

        /* 学科 */
        $qtypes = C('q_type');
        /* 试题类型 */
        $subjects = C('subject');

        if ($total) {
            $sql = "SELECT q.*,a.admin_user,a.realname FROM {pre}question q
                    LEFT JOIN {pre}admin a ON a.admin_id=q.admin_id
                    $where ORDER BY q.ques_id DESC LIMIT $offset,$size";

            $res = $this->db->query($sql);
            
            foreach ($res->result_array() as $row) 
            {
                $row['title'] = strip_tags($row['title']);
                $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
                $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

                $list[] = $row;
            }
        }

        $data['list'] = &$list;
        
        $data['q_class'] = $this->db->get_where('question_class')->result_array();
        $data['c_subject_id'] = $c_subject_id ? explode(',',$c_subject_id) : array();
        $data['c_class_id'] = $c_class_id;
        $data['c_grade_id'] = $c_grade_id ? explode(',',$c_grade_id) : array();
        $data['search_data'] = $_GET;

        /* 分页 */
        $purl = site_url('admin/paper_diy/select_question/'. ($param ? '?'.implode('&',$param) : ''));
        $data['pagination'] = multipage($total, $size, $page, $purl, '', $nums['relate_num']);

        /* 模版 */
        $this->load->view('paper_diy/question_list', $data);
    }

    /**
     * 添加试题
     *
     * @return void
     **/
    public function update_question()
    {
        $post = $this->input->post();
        $paper_id = (int)$post['paper_id'];

        /* exam_paper 试卷对应排序 */
        /* 限定用户只能更新跟自己相关的数据 */
        $admin_id = $this->session->userdata('admin_id');

        if (!$admin_id) {
            message('获取管理员数据失败，请从新登陆后重试！');
        }

        /* 获取试卷信息 */
        $paper = PaperModel::get_paper_by_id($paper_id);

        if (!$paper) {
            message('获取试卷数据失败，请重试！');
        }

        /* 用户可以编辑自己的试卷 管理员可以查看所有试卷 */
        if ($paper['admin_id'] != $admin_id&&!$this->session->userdata('is_super')) {
            message('没有当前试卷编辑权限！');
        }
        
        if (!$this->is_super_user() && ExamPlaceSubjectModel::exam_subject_has_test_action($paper['exam_id']))
        {
            message('当前试卷已进行考试，不可以修改更新！');
        }

        /* 计算排序 */
        $questions = $this->sort_question($post['ques_ids'], $post['sort']);

        $question_score = $this->sort_question($post['score'], $post['sort']);

        if (!$questions) {
            $questions = array();
        }

        /* 更新试卷信息 */
        $data = array();
        $data['question_sort'] = json_encode($questions);

        $data['question_score'] = json_encode($post['score']);

        $qtype_ques_num = array_fill(0, count(C('q_type')), '0');
        
        $this->db->trans_start();
        
        $this->db->delete('exam_question',
                array('paper_id'=>$paper_id));
        $question_difficulty = array();
        if (count($questions) > 0) 
        {
            foreach ($questions as $ques_id) 
            {
                $sql = "select q.ques_id,q.type,AVG(rc.difficulty) as difficulty from
                    {pre}question q left join {pre}relate_class rc on q.ques_id=rc.ques_id
                    where q.ques_id={$ques_id}  group by q.ques_id";
                $question = $this->db->query($sql)->row_array();

                if (empty($question)) 
                {
                    $this->db->trans_rollback();
                    message('当前试卷中存在不属于当前考试期次年级的试题！请检查试题！');
                    exit;
                }

                $data1 = array();
                $data1['paper_id'] = $paper_id;
                $data1['exam_id'] = $paper['exam_id'];
                $data1['ques_id'] = $ques_id;
                $this->db->insert('exam_question', $data1);
    
                /* 各个类型试题数量 */
                $qtype_ques_num[$question['type']]++;
                /* 试题难易度 */
                $question_difficulty[] = $question['difficulty'];
            }
        }
        /* 更新试题数量 */
        $data['ques_num'] = count($questions);
        $data['qtype_ques_num'] = implode(',', $qtype_ques_num);
        $data['difficulty'] = array_sum($question_difficulty)/count($question_difficulty);
        
        PaperModel::update_paper($paper_id, $data);
        
        $rst = $this->db->trans_commit();
        if (!$rst) 
        {
            message('更新试卷信息失败！请重试！');
        } 
        else 
        {
            $sql = "SELECT exam_pid FROM rd_exam_subject_paper 
                    WHERE paper_id = $paper_id";
            $exam_pid = Fn::db()->fetchOne($sql);
            if ($exam_pid && ExamModel::is_mini_test($exam_pid))
            {
                PaperModel::update_paper_question($paper_id);
            }
            
            message('更新试卷信息成功！', site_url('admin/paper_diy/question_manage/' . $paper_id));
        }
    }

    /**
     * 对试题进行排序
     *
     * @param array $questions 需要排序的试题
     * @param array $sort 排序的序列号
     * @return mixed 参数错误或排序失败返回false,成功返回排序后的数组
     **/
    private function sort_question ($questions, $sort)
    {
        if (empty($questions) || empty($sort)) {
            return false;
        }

        /* 判断数量是否对应 */
        if (count($questions) != count($sort)) {
            return false;
        }


        /* 合并数组 */
        $arr = array_combine($questions, $sort);

        if (count($arr) <= 0 ) {
            return false;
        }

        /* 创建二维数组，对相同序列号进行默认排序 */
        $tmp = array();

        foreach ($arr as $key => $value) {
            if (empty($value)) {
                $tmp[0][] = $key;
            } else {
                $tmp[$value][] = $key;
            }
        }

        /* 排序 */
        if (!ksort($tmp)) {
            return false;
        }

        $arr_sort = array();

        /* 5.3及以上版本 */
        /* array_walk($tmp, function($value, $key) use (&$arr_sort) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $arr_sort[] = $v;
                }
            } else {
                $arr_sort[] = $value;
            }
        }); */

        foreach ($tmp as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $arr_sort[] = $v;
                }
            } else {
                $arr_sort[] = $value;
            }
        }

        return $arr_sort;
    }

    /**
     * 格式化试题内容
     *
     * @param   integer     试题ID
     * @param   string      试题内容
     * @param   boolean     是否转换填空项
     * @return  void
     */
    private function _format_question_content($content, $replace_inputs = FALSE)
    {
        $content = str_replace("\n", '<br/>', $content);
        if ($replace_inputs) {
            $regex = "/（[\s\d|&nbsp;]*）/";
            $input = "__________";
            $content = preg_replace($regex, $input, $content);
        }
        $content = preg_replace("/<p>[\s\d| | |&nbsp;|<br\/>]*<\/p>/", '', $content);

        return $content;
    }

    function is_all_subject_user()
    {
        //$c_subject_id = $this->session->userdata('subject_id');
        //return $c_subject_id == '-1';

        $action = $this->session->userdata('action');
        $is_all_subject = false;
        foreach ($action as $power)
        {
            if ($power['subject_id'] == -1)
            {
                $is_all_subject = true;
                break;
            }
        }

        return $is_all_subject;
    }
    
    /**
     * 当前用户可以参与的考试期次
     */
    private function u_exam_list()
    {
        $exam_list = ExamModel::get_exam_list_all(array('exam_pid > ' => 0), 'exam_id, exam_name, subject_id, managers, creator_id');
        
        $data = array();
        $admin_id = $this->session->userdata('admin_id');
        $is_super = self::is_super_user();
        
        foreach ($exam_list as $item)
        {
            $managers = $item['managers'];
            $creator_id = $item['creator_id'];
            unset($item['managers']);
            unset($item['creator_id']);
            
            if ($is_super || $creator_id == $admin_id
                || ($managers && in_array($admin_id, json_decode($managers, true))))
            {
                $data[] = $item;
            }
        }
        
        return $exam_list;
    }
}
