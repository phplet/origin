<?php
if (!defined('BASEPATH'))
{
    exit();
}
    
/**
 * 学生考试答案录入
 *
 * @author Administrator
 *        
 */
class Exam_answer_entry extends A_Controller
{
    
    // 答案信息
    var $test_answer = array();
    // 期次下学科列表
    var $exam_list = array();
    // 期次下学科详细信息
    var $exam_info = array();
    public function __construct()
    {
        parent::__construct();
        
        if (!$this->check_power('exam_answer_entry_manage'))
            return;
    }
    
    /**
     * 考试答案录入
     */
    public function index()
    {
        $data = array();
        $data['exam_list'] = $this->db->select('exam_id,exam_name')->from('exam')->where('status IN (1,2)')->order_by('exam_id DESC')->get()->result_array();
        
        $this->load->view('exam_student_result/exam_answer_entry', $data);
    }
    
    /**
     * 考试答题卡
     */
    public function exam_sheet()
    {
        if ($this->input->get('is_submit'))
        {
            $msg = '所有考试答案已提交完成，你可以继续录入其他学生的答案';
            $this->_notice($msg, 1);
        }
        
        $place_id = (int)$this->input->get('place_id');
        
        $exam_pid = intval($this->input->get('exam_pid'));
        
        $exam_ticket = trim($this->input->get('exam_ticket'));
        
        if (!$exam_pid)
        {
            $this->_notice('请选择考试期次');
        }
        
        if (!$exam_ticket)
        {
            $this->_notice('请输入准考证号');
        }
        
        if (!is_numeric($exam_ticket))
        {
            $this->_notice('请输入正确的准考证号');
        }
        
        $data = array();
        
        $student = $this->db->select('uid,last_name,first_name')->from('student')->where(array(
                'exam_ticket' => $exam_ticket 
        ))->get()->row_array();
        if (!$student)
        {
            $this->_notice('准考证号不存在');
        }
        
        $uid = $student['uid'];
        
        // 考试期次->考场
        
        $place = $this->db->select('place_id,place_name,start_time,end_time')->get_where('exam_place', array(
                'exam_pid' => $exam_pid 
        ))->result_array();
        if (!$place)
        {
            $this->_notice('该考试期次没有分配考场');
        }
        
        $places = array();
        foreach ($place as $item)
        {
            if ($item['start_time'] > time())
            {
                $this->_notice('该场考试还没有开始，不能录入考试答案');
            }
            
            if ($item['end_time'] > time())
            {
                $this->_notice('该场考试还没有结束，不能录入考试答案');
            }
            
            $places[$item['place_id']] = $item['place_name'];
        }
        
        // 考试期次->学生分配到的考场
        $place_ids = array();
        $student_place = $this->db->select('place_id')->from('exam_place_student')->where('uid', $uid)->where_in('place_id', array_keys($places))->get()->result_array();
        
        if (!$student_place)
        {
            $this->_notice('该学生不在考试期次分配的考场中');
        }
        
        foreach ($student_place as $key => $item)
        {
            $place_ids[] = $item['place_id'];
            $student_place[$key]['place_name'] = $places[$item['place_id']];
        }
        
        $student_exam_list = $this->db->select('exam_id,etp_flag,place_id')->from('exam_test_paper')->where(array(
                'exam_pid' => $exam_pid,
                'uid' => $uid 
        ))->get()->result_array();
        
        $not_submit_exam = array();
        
        if ($student_exam_list)
        {
            // 判断是否已提交所有试卷
            foreach ($student_exam_list as $item)
            {
                if ($item['etp_flag'] == 0)
                {
                    $not_submit_exam[] = $item['exam_id'];
                }
            }
        }
        
        if (!$not_submit_exam && $student_exam_list)
        {
            $this->_notice('系统已经记录过该考生此次考试的所有答题记录');
        }
        
        if (empty($place_ids))
        {
            foreach ($student_place as $key => $item)
            {
                $place_ids[] = $item['place_id'];
            }
        }
        
        // 考场分配的学科
        
        $where_str = "place_id " . (count($place_ids) > 1 ? " IN (" . implode(',', $place_ids) . ")" : " = $place_ids[0]");
        $where_str .= " AND exam_pid = '$exam_pid' ";
        $place_subject = $this->db->get_where('exam_place_subject', $where_str)->result_array();
        if (!$place_subject)
        {
            $this->_notice('该学生所在考场未分配考试学科');
        }
        
        foreach ($place_ids as $place_id)
        {
            // 初始化学生分配试卷，避免因分配学生试卷不及时导致学生没有分配到试卷而不能录题
            $this->_init_set_student_paper($place_id, $uid);
            
            // 考生第一次进入初始化
            $this->_init_exam($uid, $exam_pid, $place_id);
        }
        
        if (!$this->exam_info)
        {
            $this->_notice('该学生所在考场未分配试卷');
        }
        
        $exam_id = (int)$this->input->get('exam_id');
        if (!$exam_id)
        {
            $exam_id = current(array_keys($this->exam_info));
        }
        
        // 答题卡部分
        $data['group'] = $this->_sheet($uid, $exam_pid, $exam_id);
        $data['qtypes'] = C('qtype');
        $data['student'] = $student;
        $data['student_place'] = $student_place;
        $data['place_subject'] = $place_subject;
        $data['place_id'] = $place_id;
        $data['subject'] = C('subject');
        $data['exam_info'] = $this->exam_info;
        
        $data['exam_id'] = $exam_id;
        $data['exam_pid'] = $exam_pid;
        $data['exam_ticket'] = $exam_ticket;
        // 如果没有学生没有分配试卷 ，则为录入考场下全部学科
        $data['allow_exam'] = $is_no_exam_paper ? array_intersect(array_keys($this->exam_info), $not_submit_exam) : array_keys($this->exam_info);
        $data['exam_answer'] = $this->test_answer;
        $data['is_enter_all_sheet'] = $this->_is_enter_all_sheet($uid, $exam_id);
        
        $this->load->view('exam_student_result/exam_sheet', $data);
    }
    
    /**
     * 插入学生答题卡
     */
    public function insert_student_sheet()
    {
        $exam_pid = intval($this->input->post('exam_pid'));
        $exam_ticket = trim($this->input->post('exam_ticket'));
        
        $exam_id = intval($this->input->post('exam_id'));
        $uid = intval($this->input->post('uid'));
        $paper_id = intval($this->input->post('paper_id'));
        $answer = $this->input->post('answer');
        
        // 更新答题记录
        $this->_update_student_exam_test_result($exam_id, $paper_id, $uid, $answer);
        
        if ($this->input->post('submit_all_sheet'))
        {
            $this->db->update('exam_test_paper', array(
                    'etp_flag' => 1 
            ), "exam_pid = $exam_pid AND uid = $uid");
            
            redirect(site_url("/admin/exam_answer_entry/exam_sheet/?is_submit=1"));
        }
        else
        {
            $this->test_answer;
            
            $next_exam_id = trim($this->input->post('next_exam_id'));
            
            $next_exam_id_array = explode('|', $next_exam_id);
            
            $next_exam_id = $next_exam_id_array[1];
            $next_place_id = $next_exam_id_array[0];
            
            if ($next_exam_id == $exam_id)
            {
                $next_exam_id = $this->_get_next_exam($uid, $exam_id);
            }
            
            $get_param = "?exam_pid=$exam_pid&exam_ticket=$exam_ticket&exam_id=$next_exam_id&place_id=$next_place_id";
            redirect(site_url("/admin/exam_answer_entry/exam_sheet/$get_param"));
        }
    }
    
    /**
     * 更新答题记录
     *
     * @param int $exam_id            
     * @param int $paper_id            
     * @param int $uid            
     * @param array $answer            
     * @return boolean
     */
    private function _update_student_exam_test_result($exam_id, $paper_id, $uid, $answer)
    {
        $sses_answer = $this->test_answer;
        
        if ($answer == $sses_answer)
        {
            return false;
        }
        
        $where = "exam_id = $exam_id AND paper_id = $paper_id AND uid = $uid";
        
        foreach ($answer as $q_type => $ques_answer)
        {
            if (!empty($sses_answer[$q_type]) && $ques_answer == $sses_answer[$q_type])
            {
                continue;
            }
            
            if (in_array($q_type, array(
                    1,
                    2,
                    3,
                    7 
            )))
            {
                foreach ($ques_answer as $ques_id => $item)
                {
                    if (!empty($sses_answer[$q_type][$ques_id]) && $item == $sses_answer[$q_type][$ques_id])
                    {
                        continue;
                    }
                    
                    $param = array();
                    
                    // 单选 翻译
                    if (in_array($q_type, array(
                            1,
                            7 
                    )))
                    {
                        $param = array(
                                'answer' => $item 
                        );
                    }
                    // 不定项
                    else if ($q_type == 2)
                    {
                        $param = array(
                                'answer' => implode(',', $item) 
                        );
                    }
                    else if ($q_type == 3)
                    {
                        $param = array(
                                'answer' => implode("\n", $item) 
                        );
                    }
                    
                    $param && $this->db->update('exam_test_result', $param, $where . " AND ques_id = $ques_id");
                }
            }
            // 题组 完型填空
            else if (in_array($q_type, array(
                    0,
                    4 
            )))
            {
                foreach ($ques_answer as $ques_id => $children)
                {
                    foreach ($children as $child_qtype => $childlist)
                    {
                        foreach ($childlist as $sub_ques_id => $item)
                        {
                            if (!empty($sses_answer[$q_type][$ques_id][$child_qtype][$sub_ques_id]) && $item == $sses_answer[$q_type][$ques_id][$child_qtype][$sub_ques_id])
                            {
                                continue;
                            }
                            
                            $param = array();
                            // 单选
                            if ($child_qtype == 1)
                            {
                                $param = array(
                                        'answer' => $item 
                                );
                            }
                            // 不定项
                            else if ($child_qtype == 2)
                            {
                                $param = array(
                                        'answer' => implode(',', $item) 
                                );
                            }
                            // 填空
                            else if ($child_qtype == 3)
                            {
                                $param = array(
                                        'answer' => implode("\n", $item) 
                                );
                            }
                            
                            $param && $this->db->update('exam_test_result', $param, $where . " AND sub_ques_id = $sub_ques_id");
                        }
                    }
                }
            }
            // 匹配题 阅读填空 选词填空
            else if (in_array($q_type, array(
                    5,
                    6,
                    8 
            )))
            {
                foreach ($ques_answer as $children)
                {
                    foreach ($children as $child_qtype => $childlist)
                    {
                        foreach ($childlist as $sub_ques_id => $item)
                        {
                            if (!empty($sses_answer[$q_type][$ques_id][$child_qtype][$sub_ques_id]) && $item == $sses_answer[$q_type][$ques_id][$child_qtype][$sub_ques_id])
                            {
                                continue;
                            }
                            
                            $param = array(
                                    'answer' => $item 
                            );
                            
                            $this->db->update('exam_test_result', $param, $where . " AND sub_ques_id = $sub_ques_id");
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 获取下一个考试的id
     *
     * @param int $uid            
     * @param int $exam_id            
     * @return Ambigous <number, unknown>
     */
    private function _get_next_exam($uid, $exam_id)
    {
        $next_exam_id = 0;
        
        if (count($this->exam_info) > 1)
        {
            $exam = array_keys($this->exam_info);
            $is_curr_exam_id = false;
            foreach ($exam as $curr_exam_id)
            {
                if ($is_curr_exam_id)
                {
                    $next_exam_id = $curr_exam_id;
                }
                
                $is_curr_exam_id = ($curr_exam_id == $exam_id);
            }
        }
        
        return $next_exam_id;
    }
    
    /**
     * 初始化学生考试数据
     *
     * @param int $uid            
     * @param int $exam_pid            
     * @param int $place_id            
     */
    private function _init_exam($uid, $exam_pid, $place_id)
    {
        $this->load->model('exam/exam_paper_model');
        $this->load->model('exam/exam_test_result_model');
        
        $paper_model = $this->exam_paper_model;
        $test_result_model = $this->exam_test_result_model;
        
        $paper_info = array();
        $paper_ques_infos = array();
        $test_papers = ExamTestPaperModel::get_student_test_papers($place_id, $uid, 'etp_id, paper_id, etp_flag, full_score, exam_id, subject_id', 0, $exam_pid);
        
        if (!$test_papers)
        {
            return false;
        }
        
        // $exam_info = array ();
        
        foreach ($test_papers as $paper)
        {
            
            $paper_id = $paper['paper_id'];
            $subject_id = $paper['subject_id'];
            $etp_id = $paper['etp_id'];
            $exam_id = $paper['exam_id'];
            
            $this->exam_info[$paper['exam_id']]['exam_id'] = $exam_id;
            $this->exam_info[$paper['exam_id']]['etp_id'] = $etp_id;
            $this->exam_info[$paper['exam_id']]['paper_id'] = $paper_id;
            $this->exam_info[$paper['exam_id']]['subject_id'] = $subject_id;
            
            // $test_answer = array ();
            
            // 获取试卷 试题信息
            $paper_questions = $paper_model->get_paper_question_detail_p($paper_id, array(
                    'uid' => $uid 
            ));
            
            $tmp_ques_list = array_values($this->_filter_paper_question_detail($paper_questions));
            
            // 提取考试试题答案
            $ques_ids = array();
            foreach ($tmp_ques_list as $row)
            {
                $tmp_ques_ids = array_keys($row['list']);
                foreach ($tmp_ques_ids as $t_id)
                {
                    @list($q, $ques_id) = @explode('_', $t_id);
                    if (is_null($ques_id))
                    {
                        continue;
                    }
                    
                    // 插入或修改学生答题记录
                    $ques = $test_result_model->get_test_question($etp_id, $ques_id);
                    if (in_array($ques['type'], array(
                            1,
                            2,
                            3,
                            7 
                    )))
                    {
                        $this->test_answer[$ques['type']][$ques['ques_id']] = $ques['answer'];
                    }
                    else if (in_array($ques['type'], array(
                            0,
                            4,
                            5,
                            6,
                            8 
                    )))
                    {
                        foreach ($ques['children'] as $sub_ques_id => $child)
                        {
                            $this->test_answer[$ques['type']][$ques['ques_id']][$child['type']][$sub_ques_id] = $child['answer'];
                        }
                    }
                }
            }
        }
        
        $query = $this->db->select('exam_id,class_id,grade_id')->from('exam')->where('exam_pid', $exam_pid)->get();
        
        foreach ($query->result_array() as $item)
        {
            $this->exam_list[$item['exam_id']] = $item;
        }
        
        // 多考场 考试组合
        
        if ($this->exam_info)
        {
            foreach ($this->exam_info as $exam)
            {
                $this->exam_info[$exam['exam_id']] = $exam;
            }
        }
    }
    
    /**
     * 学生对应考试答题卡
     *
     * @param int $uid            
     * @param int $exam_id            
     * @param int $exam_pid            
     * @return multitype:Ambigous <>
     */
    private function _sheet($uid, $exam_pid, $exam_id)
    {
        if ($this->exam_info[$exam_id]['subject_id'] == 3)
        {
            $group = array(
                    1 => array(),
                    4 => array(),
                    0 => array(),
                    5 => array(),
                    6 => array(),
                    7 => array(),
                    2 => array(),
                    3 => array(),
                    8 => array(),
                    9 => array() 
            );
            
            $types = array(
                    '1',
                    '4',
                    '0',
                    '5',
                    '6',
                    '7',
                    '2',
                    '3',
                    '8',
                    '9' 
            );
        }
        else
        {
            $group = array(
                    3 => array(),
                    1 => array(),
                    2 => array(),
                    0 => array() 
            );
            
            $types = array(
                    '1',
                    '2',
                    '3',
                    '0' 
            );
        }
        
        $grade_id = (int)$this->exam_list[$exam_id]['grade_id'];
        $class_id = (int)$this->exam_list[$exam_id]['class_id'];
        $paper_id = (int)$this->exam_info[$exam_id]['paper_id'];
        
        $sql = "SELECT q.ques_id,q.type,q.answer FROM {pre}exam_question eq
                LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
                LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id
                AND rc.grade_id=$grade_id
                AND rc.class_id=$class_id
                WHERE eq.paper_id=$paper_id
                ORDER BY rc.difficulty DESC,q.ques_id ASC";
        
        $question = $this->db->query($sql)->result_array();
        
        if (!$question)
        {
            $this->_notice('此学科未分配试卷');
        }
        
        foreach ($question as $row)
        {
            switch ($row['type'])
            {
                case 0:
                case 4:
                case 5:
                case 6:
                case 8:
                    $row['children'] = QuestionModel::get_children((int)$row['ques_id']);
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
                default:
                    break;
            }
            
            $group[$row['type']][] = $row;
        }
        
        $tmp_arr = array();
        foreach ($types as $type)
        {
            if (!empty($group[$type]))
            {
                $tmp_arr[$type] = $group[$type];
            }
        }
        
        return $tmp_arr;
    }
    
    /**
     * 判断是否填写完全部的答题卡
     */
    private function _is_enter_all_sheet($uid, $exam_id)
    {
        $is_enter_all_sheet = true;
        
        $exam_info = $this->exam_info;
        
        if (count($exam_info) > 1)
        {
            foreach ($exam_info as $e_id => $item)
            {
                if ($e_id == $exam_id)
                {
                    continue;
                }
                
                if (!$this->test_answer)
                {
                    $is_enter_all_sheet = false;
                    break;
                }
            }
        }
        
        return $is_enter_all_sheet;
    }
    
    /**
     * 初始化学生试卷
     */
    private function _init_set_student_paper($place_id, $uid)
    {
        ExamTestPaperModel::set_student_test_paper($place_id, $uid);
    }
    
    /**
     * 将试卷试题的分类重组
     */
    private function _filter_paper_question_detail($paper_questions)
    {
        // 格式化$groups key 内容
        $tmp_groups = array();
        foreach ($paper_questions as $key => $item)
        {
            foreach ($item['list'] as $k => $v)
            {
                $item['list']["q_{$k}"] = $v;
                unset($item['list'][$k]);
            }
            
            $tmp_groups[$key] = $item;
            unset($paper_questions[$key]);
        }
        
        return $tmp_groups;
    }
    private function _notice($msg, $msg_type = NULL)
    {
        $data['message'] = $msg;
        $data['msg_type'] = $msg_type;
        $content = $this->load->view('exam_student_result/notice', $data, true);
        ob_end_clean();
        echo $content;
        exit();
    }
}
