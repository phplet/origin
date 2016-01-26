<?php if ( ! defined('BASEPATH')) exit();
class Relate_group extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // 关联组列表
    public function index()
    {
        exit();
    }

    // 关联组管理
    public function group($group_id = 0)
    {
        if ( ! $this->check_power('question_manage')) return;

        $group_id = intval($group_id);
        if ($group_id)
        {
            $query = $this->db->get_where('relate_group', array('group_id'=>$group_id));
            $group = $query->row_array();
        }
        
        if (empty($group))
        {
           $site_url=site_url('/admin/question/index');
            header("location: $site_url");
           // message('关联分组不存在。','/admin/question/index');
            return;
        }

        $cpusers = CpUserModel::get_cpuser_list();

        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $subjects   = CpUserModel::get_allowed_subjects();
        $grades     = C('grades');
        $qtypes     = C('qtype');


        $periods    = C('grade_period');
        $langs      = C('interview_lang');
        $types      = C('interview_type');

        $list_ques = $list_interview = array();
        $query = $this->db->get_where('question', array('group_id'=>$group_id,'parent_id'=>0,'is_delete'=>0));
        foreach ($query->result_array() as $row)
        {
            $row_cids = explode(',', trim($row['class_id'], ','));
            $row_cname = array();
            foreach ($row_cids as $cid)
            {
                $row_cname[] = isset($class_list[$cid]['class_name']) ? $class_list[$cid]['class_name'] : '';
            }
            $row['class_name'] = implode(',', $row_cname);
            $row['start_grade'] = isset($grades[$row['start_grade']]) ? $grades[$row['start_grade']] : '';
            $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
            $row['end_grade'] = isset($grades[$row['end_grade']]) ? $grades[$row['end_grade']] : '';
            $row['subject_name'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';
            $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname']) ? $cpusers[$row['admin_id']]['realname'] : '';
            $row['has_edit_power'] = QuestionModel::check_question_power($row['ques_id'], 'w', false);

            //判断该试题已经被考试过 或 正在被考
            $row['be_tested'] = QuestionModel::question_has_test_action($row['ques_id']);

            $list_ques[] = $row;
        }

        $query2 = $this->db->get_where('interview_question', array('group_id'=>$group_id,'is_delete'=>0));
        foreach ($query2->result_array() as $row)
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
            $row['cpuser'] = isset($cpusers[$row['admin_id']]['realname']) ? $cpusers[$row['admin_id']]['realname'] : '';
            $list_interview[] = $row;
        }

        $priv = array(
            'delete_question' => $this->check_power('question_delete', FALSE),
            'delete_interview_question' => $this->check_power('invterview_question_delete', FALSE)
        );

        $data['group_id'] = $group_id;
        $data['list_ques'] = $list_ques;
        $data['list_interview'] = $list_interview;
        $data['priv'] = $priv;

        $data['has_edit_power'] = QuestionModel::check_question_power($group['group_name'], 'w', false);
        $data['has_interview_question_manage'] = $this->check_power('interview_question_manage', false);

        // 模版
        $this->load->view('relate_group/group', $data);
    }
}
