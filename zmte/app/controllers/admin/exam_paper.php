<?php if ( ! defined('BASEPATH')) exit();
class Exam_paper extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index($exam_pid = 0, $place_id = 0)
    {
        if (!$this->check_power('exam_list,exam_manage') || !$exam_pid || !$place_id)
        {
            return;
        }

        $exam = ExamModel::get_exam($exam_pid, 'exam_id, exam_name, grade_id, class_id, creator_id');
        if (empty($exam))
        {
            message('考试期次不存在');
        }

        if (!$this->is_super_user() && $exam['creator_id'] != $this->session->userdata('admin_id'))
        {
            return;
        }

        $place = $this->db->select('place_id, place_name, address')->get_where('exam_place', array('place_id'=>$place_id))->row_array();

        $data = array();
        $exam_paper = array();

        $subject = C('subject');

        $query = $this->db->select('esp.paper_id,esp.subject_id')->from('exam e')
                    ->join('exam_subject_paper esp', "e.exam_id = esp.exam_id", "LEFT")
                    ->join('exam_place_subject eps', "e.exam_id = eps.exam_id", "LEFT")
                    ->where('e.exam_pid', $exam_pid)->where('eps.place_id', $place_id)->order_by('esp.subject_id')->get();
        foreach ($query->result_array() as $item)
        {
            if (!$item['paper_id'])
            {
                message($subject[$item['subject_id']] . "学科还未分配试卷");
            }

            $exam_paper[$item['subject_id']][] = $item['paper_id'];
        }

        if (empty($exam_paper))
        {
            message('该考场没有分配学科');
        }

        $data['exam'] = $exam;

        $data['place_id'] = $place_id;
        $data['place'] = $place;
        $data['paper'] = $exam_paper;
        $data['subject'] = $subject;
        $data['grade'] = C('grades');

        $this->load->view('exam_paper/index', $data);
    }


    public function score_input($exam_pid = 0, $place_id = 0)
    {
    	if (!$this->check_power('exam_list,exam_manage') || !$exam_pid || !$place_id)
    	{
    		return;
    	}

    	$exam = ExamModel::get_exam($exam_pid, 'exam_id, exam_name, grade_id, class_id, creator_id');
    	if (empty($exam))
    	{
    		message('考试期次不存在');
    	}

    	if (!$this->is_super_user() && $exam['creator_id'] != $this->session->userdata('admin_id'))
    	{
    		return;
    	}

    	$place = $this->db->select('place_id, place_name, address')->get_where('exam_place', array('place_id'=>$place_id))->row_array();

    	$data = array();
    	$exam_paper = array();

    	$subject = C('subject');

    	$query = $this->db->select('esp.paper_id,esp.subject_id')->from('exam e')
    	->join('exam_subject_paper esp', "e.exam_id = esp.exam_id", "LEFT")
    	->join('exam_place_subject eps', "e.exam_id = eps.exam_id", "LEFT")
    	->where('e.exam_pid', $exam_pid)->where('eps.place_id', $place_id)->order_by('esp.subject_id')->get();
    	foreach ($query->result_array() as $item)
    	{
    		if (!$item['paper_id'])
    		{
    			message($subject[$item['subject_id']] . "学科还未分配试卷");
    		}

    		$exam_paper[$item['subject_id']][] = $item['paper_id'];
    	}

    	if (empty($exam_paper))
    	{
    		message('该考场没有分配学科');
    	}

    	$data['exam'] = $exam;
    	$data['exam_pid'] = $exam_pid;
    	$data['place_id'] = $place_id;
    	$data['place'] = $place;
    	$data['paper'] = $exam_paper;
    	$data['subject'] = $subject;
    	$data['grade'] = C('grades');

    	$this->load->view('exam_paper/score_input', $data);
    }

    /**
     * 考试期次试卷预览
     *
     * @param   int     $exam_pid     考试期次
     * @param   int     $place_id     考场
     * @param   string  $paper_id     学科对应的试卷，多学科有“,”分开（如1_1212,2_1213,3_1214...）
     * @param   string  $paper_sort   学科试卷的顺序，多学科有“,”分开（如1_1,2_2,4_3,5_4...）
     * @return void
     */
    public function preview()
    {

        if (!$this->check_power('exam_list,exam_manage'))
        {
            return;
        }

        $exam_pid = intval($this->input->get('exam_pid'));
        $place_id = intval($this->input->get('place_id'));
        $paper_id_str = trim($this->input->get('paper_id'));
        $paper_sort_str = trim($this->input->get('paper_sort'));
        if (!$exam_pid || !$paper_id_str || !$place_id)
        {
              message('试卷参数错误');
        }

        $paper_ids = array();
        if ($paper_id_str)
        {
            $tmp = array_filter(explode(",", $paper_id_str));
            foreach ($tmp as $item)
            {
                $val = explode("_", $item);
                if ($val != array_filter($val))
                {
                    message('试卷参数错误');
                }

                $paper_ids[$val[0]] = $val[1];
            }
        }
        else
        {
            message('试卷参数错误');
        }

        $paper_sort = array();
        if ($paper_sort_str)
        {
            $tmp = explode(",", $paper_sort_str);
            foreach ($tmp as $item)
            {
                $val = explode("_", $item);
                $paper_sort[$val[0]] = $val[1];
            }
        }

        if ($paper_sort && $paper_sort != array_filter($paper_sort))
        {
            message('学科排序数字必须大于0');
        }

        $paper = array();
        $subjects = array();
        if ($paper_sort)
        {
            foreach ($paper_sort as $subject_id => $sort)
            {
                if(!$paper_ids[$subject_id])
                {
                    continue;
                }
                $subjects[] = $subject_id;
                $paper[$sort][$subject_id] = $paper_ids[$subject_id];
            }

            ksort($paper);
        }
        else
        {
            $paper[1] = $paper_ids;
        }

        $exam = ExamModel::get_exam($exam_pid, 'exam_id, exam_name, student_notice, grade_id, class_id, creator_id');
        if (empty($exam))
        {
            message('考试期次不存在');
        }

        if (!$this->is_super_user() && $exam['creator_id'] != $this->session->userdata('admin_id'))
        {
            return;
        }
        $subjects = implode(',', $subjects);
        $place = $this->db->select('ep.start_time, ep.end_time, sum(e.total_score) total_score')->from('exam_place ep')
                 ->join('exam_place_subject eps', 'eps.place_id = ep.place_id', "LEFT")
                 ->join('exam e', 'e.exam_id = eps.exam_id', "LEFT")->where('ep.place_id', $place_id)->where_in('eps.subject_id', $subjects)->get()->row_array();

        $data = array();
        $subject = C('subject');

        foreach ($paper as $item)
        {
            foreach ($item as $subject_id => $paper_id)
            {
                $paper_info = $this->_paper($paper_id);
                if (!$paper_info)
                {
                    message($subject[$subject_id] . '学科试卷信息不存在' );
                }

                $exam_paper[] = $paper_info;
            }
        }

        if (empty($exam_paper))
        {
            message('试卷信息不存在');
        }

        $data['exam'] = $exam;
        $data['place'] = $place;
        $data['exam_paper'] = $exam_paper;
        $data['subject'] = $subject;
        $data['qtypes'] = C('qtype');
        $data['grade'] = C('grades');
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十');

        $this->load->view('exam_paper/preview', $data);
    }

    /**
     * 考试期次试卷答案预览
     *
     * @param   int     $exam_pid     考试期次
     * @param   int     $place_id     考场
     * @param   string  $paper_id     学科对应的试卷，多学科有“,”分开（如1_1212,2_1213,3_1214...）
     * @param   string  $paper_sort   学科试卷的顺序，多学科有“,”分开（如1_1,2_2,4_3,5_4...）
     * @return void
     */
    public function preview_answer()
    {

        if (!$this->check_power('exam_list,exam_manage'))
        {
            return;
        }

        $exam_pid = intval($this->input->get('exam_pid'));
        $place_id = intval($this->input->get('place_id'));
        $paper_id_str = trim($this->input->get('paper_id'));
        $paper_sort_str = trim($this->input->get('paper_sort'));
        if (!$exam_pid || !$paper_id_str || !$place_id)
        {
            return;
        }

        $paper_ids = array();
        if ($paper_id_str)
        {
            $tmp = array_filter(explode(",", $paper_id_str));
            foreach ($tmp as $item)
            {
                $val = explode("_", $item);
                if ($val != array_filter($val))
                {
                    message('试卷参数错误');
                }

                $paper_ids[$val[0]] = $val[1];
            }
        }
        else
        {
            message('试卷参数错误');
        }

        $paper_sort = array();
        if ($paper_sort_str)
        {
            $tmp = explode(",", $paper_sort_str);
            foreach ($tmp as $item)
            {
                $val = explode("_", $item);
                $paper_sort[$val[0]] = $val[1];
            }
        }

        if ($paper_sort && $paper_sort != array_filter($paper_sort))
        {
            message('学科排序数字必须大于0');
        }

        $paper = array();
        if ($paper_sort)
        {
            foreach ($paper_sort as $subject_id => $sort)
            {
                $paper[$sort][$subject_id] = $paper_ids[$subject_id];
            }

            ksort($paper);
        }
        else
        {
            $paper[1] = $paper_ids;
        }

        $exam = ExamModel::get_exam($exam_pid, 'exam_id, exam_name, student_notice, grade_id, class_id, creator_id');
        if (empty($exam))
        {
            message('考试期次不存在');
        }

        if (!$this->is_super_user() && $exam['creator_id'] != $this->session->userdata('admin_id'))
        {
            return;
        }

        $place = $this->db->select('ep.start_time, ep.end_time, sum(e.total_score) total_score')->from('exam_place ep')
                    ->join('exam_place_subject eps', 'eps.place_id = ep.place_id', "LEFT")
                    ->join('exam e', 'e.exam_id = eps.exam_id', "LEFT")->where('ep.place_id', $place_id)->get()->row_array();

        $data = array();
        $subject = C('subject');

        foreach ($paper as $item)
        {
            foreach ($item as $subject_id => $paper_id)
            {
                $paper_info = $this->_paper($paper_id);
                if (!$paper_info)
                {
                    message($subject[$subject_id] . '学科试卷信息不存在' );
                }

                $exam_paper[] = $paper_info;
            }
        }

        if (empty($exam_paper))
        {
            message('试卷信息不存在');
        }

        $data['exam'] = $exam;
        $data['place'] = $place;
        $data['exam_paper'] = $exam_paper;
        $data['subject'] = $subject;
        $data['qtypes'] = C('qtype');
        $data['grade'] = C('grades');
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十');

        $this->load->view('exam_paper/preview_answer', $data);
    }

    /**
     * 获取试卷信息
     * @param    int    $paper_id
     * @return   array
     */
    private function _paper($paper_id)
    {
        if (!$paper_id)
        {
            return array();
        }

        $paper = ExamPaperModel::get_paper($paper_id);
        if (empty($paper))
        {
           return array();
        }

        $exam = ExamModel::get_exam($paper['exam_id'], 'grade_id, class_id, subject_id, qtype_score, total_score');
        if (empty($exam))
        {
            return array();
        }

        // 分数计算
        $score = array();
        // 题目数量
        $question_num = explode(',', $paper['qtype_ques_num']);
        // 题型分数
        $qtype_score = explode(',', $exam['qtype_score']);
        // 总分 排除题组
        $total_score = 0;

        foreach ($qtype_score as $key => $value)
        {
            $score[$key+1]['score'] = $value;
            $score[$key+1]['num'] = isset($question_num[$key+1]) ? $question_num[$key+1] : 0;
            $score[$key+1]['total_score'] = $score[$key+1]['score'] * $score[$key+1]['num'];
            $total_score += $score[$key+1]['score'] * $score[$key+1]['num'];
        }

        // 题组总分
        $total_0 = $exam['total_score'] - $total_score;

        $data = array();
        $data['exam'] = $exam;
        $data['score'] = $score;
        $data['total_0'] = $total_0;

        if ($exam['subject_id'] == 3)
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
        }
        else
        {
            $group = array(
                1 => array(),
                2 => array(),
                3 => array(),
                0 => array()
            );
        }

        /** 题组分值系数总和 */
        $sql = "SELECT sum(score_factor) as sum FROM {pre}question q
                LEFT JOIN {pre}exam_question eq ON eq.ques_id=q.ques_id
                LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id
                          AND rc.grade_id = {$exam['grade_id']} AND rc.class_id = {$exam['class_id']}
                WHERE eq.paper_id = $paper_id and q.type=0";

        $query = $this->db->query($sql);

        $sum_score_factor = $query->row_array();

        $sql = "SELECT q.ques_id,q.type,q.title,q.picture,q.answer,q.score_factor,q.children_num,rc.difficulty
                FROM {pre}exam_question eq
                LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
                LEFT JOIN {pre}relate_class rc ON rc.ques_id = q.ques_id AND rc.grade_id = {$exam['grade_id']}
                          AND rc.class_id = {$exam['class_id']}
                WHERE eq.paper_id = $paper_id ORDER BY rc.difficulty DESC,q.ques_id ASC";

        $query = $this->db->query($sql);

        foreach ($query->result_array() as $row)
        {
            $row['title'] = $this->_format_question_content($row['title'] , in_array($row['type'],array(3,9)));
            switch($row['type']){
                case 0:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    foreach ($row['children'] as &$child)
                    {
                        $child['title'] = $this->_format_question_content($child['title'] , in_array($child['type'],array(3,9)));
                    }
                    // 分值系数
                    if ($sum_score_factor > 0)
                    {
                        $row['total_score'] = round($total_0 * $row['score_factor'] / $sum_score_factor['sum'], 2);
                        $row['score'] = round($row['total_score'] / $row['children_num'], 2);
                    }
                    else
                    {
                        $row['total_score'] = 0;
                        $row['score'] = 0;
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

            $group[$row['type']][] = $row;
        }

        $paper_info = array();

        $types = array_keys($group);

        foreach ($types as $type)
        {
            $paper_info[$type] = isset($group[$type]) ? $group[$type] : array();
        }

        $data['group'] = array_filter($paper_info);

        return $data;
    }

    /**
     * 格式化试题内容
     *
     * @param   integer		试题ID
     * @param   string		试题内容
     * @param	boolean		是否转换填空项
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
}
