<?php if ( ! defined('BASEPATH')) exit();

class Exam_student_result extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 学生成绩列表
     *
     * @param  int  $exam_id
     * @return  void
     */
    public function index($exam_pid=0)        
    {   
    	if (!$this->check_power('exam_list,exam_manage')) return;
    	
    	$page = intval($this->input->get('page'));
    	$per_page = intval($this->input->get('per_page'));
    	$exam_pid = intval($this->input->get('exam_pid'));
    	$flag = $this->input->get('flag');
    	$place_id = intval($this->input->get('place_id'));
    	$school_id = intval($this->input->get('school_id'));
    	$ticket = trim($this->input->get('ticket'));
    	$score_start = trim($this->input->get('score_start'));
    	$score_end = trim($this->input->get('score_end'));
    	$subject_id = intval($this->input->get('subject_id'));
    	$keyword = trim($this->input->get('keyword'));
    	
        //var_dump($place_id);die;
        // 查询条件
        $query = array();
        $param  = array();
        $search = array(
            'exam_pid' => '',
            'place_id' => '',
            'flag' => '-2',
            'keyword' => '',
            'subject_id' => '',
            'school_id' => '',
            'school_name' => '',
            'ticket' => '',
            'score_start' => '',
            'score_end' => '',
        );
        $flags = array(
            '-1' => '结果作废',
            '0'  => '未生成分数(考试中)',
            '1'  => '已交卷(未统计结果)',
            '2'  => '已生成分数'
        );

    	//考试期次
    	$exam_pid = !$exam_pid ? 0 : $exam_pid; 
    	if ($exam_pid) {
			$query['exam_pid'] = $exam_pid;
			$param[] = "exam_pid={$exam_pid}";
			$search['exam_pid'] = $exam_pid;
    	}
    	
    	//状态
    	if ($flag === false) {
			
    	} else {
    	    if ($flag > -2)
    	    {
    	        $query['etp_flag'] = $flag;
    	        $param[] = "flag={$flag}";
    	        $search['flag'] = $flag;
    	    }
    	}
    	/*
    	if (isset($query['etp_flag']) && $query['etp_flag'] == '2') {
    		$query['etp_flag'] = array('2', '-1');
    	}
    	*/
    	
    	//考场
    	if ($place_id) {
			$query['place_id'] = $place_id;
			$param[] = "place_id={$place_id}";
			$search['place_id'] = $place_id;
    	}
    	
    	//学科
    	if ($subject_id) {
    	    $query['subject_id'] = $subject_id;
    	    $param[] = "subject_id={$subject_id}";
    	    $search['subject_id'] = $subject_id;
    	}
    	
    	//学科
    	if ($keyword) {
    	    
    	    $escape_keyword = $this->db->escape_like_str($keyword);
    	    $where = "fullname LIKE '%".$escape_keyword."%'";
    	    $sql = "SELECT group_concat(uid) as uid FROM v_rd_student s WHERE $where ";
    	    $res = $this->db->query($sql)->row_array();
            
    	    $uids = explode(',',$res['uid']);
            $query['uid'] = $uids;

    	    $search['keyword'] = $keyword;
    	    
    	    $param[] = "keyword=".urlencode($search['keyword']);
        }

    	//得分查询
		if ($score_start != '') {
			$query['test_score']['>='] = $score_start;
			$param[] = "score_start={$score_start}";
			$search['score_start'] = $score_start;
		}
		
		if ($score_end != '') {
			$query['test_score']['<='] = $score_end;
			$param[] = "score_end={$score_end}";
			$search['score_end'] = $score_end;
		}
    	
    	//获取该学校下所有的学生
    	if ($school_id) {
			$param[] = "school_id={$school_id}";
			$search['school_id'] = $school_id;
			
                $row = SchoolModel::schoolInfo($school_id, 'school_name');
                if ($row)
                { 
    		    $school_name = trim($row['school_name']);
                }
                else
                {
                    $school_name = '';
                }
    		if (!count($school_name)) {
    			$query = null;
				$search['school_name'] = $school_id;
    		} else {
				$students = StudentModel::get_student_list(array('school_id' => $school_id), false, false, null, 'uid');
				if (count($students)) {
					$uids = array();
					foreach ($students as $student) {
						$uids[] = $student['uid'];
					}
					
					$query['uid'] = $uids;
				} else {
					$query = null;
				}
				$search['school_name'] = $school_name;
    		}
    	}

    	//准考证号
    	if ($ticket) {
    		if (stripos($ticket, '@') === false) {
				$uid = StudentModel::get_student_by_exam_ticket($ticket, 'uid');
    		} else {
				$uid = StudentModel::get_student_by_email($ticket, 'uid');
    		}
			if (count($uid)) {
				$query['uid'] = $uid;
			} else {
				$query = null;
			}
			$param[] = "ticket={$ticket}";
			$search['ticket'] = $ticket;
    	}
    	
    	$select_what = '*';
    	$page = $page <= 0 ? 1 : $page; 
    	$per_page = $per_page <= 0 ? 10 : $per_page; 
    	
    	$list = array();
    	
    	if (!is_null($query)) {
    		$list = ExamTestPaperModel::get_test_paper_list($query, $page, $per_page, 'subject_id ASC,etp_id ASC', $select_what);
    	}
    	
    	//获取学生信息
    	$grades = C('grades');
    	$subjects= C('subject');
    	$data['subjects'] = $subjects;

    	$tmp_list = array();
    	foreach ($list as $k => $item) {
            $student = StudentModel::get_student($item['uid'], 'first_name, last_name, school_id, exam_ticket, grade_id');

            if (!count($student)) {
                $tmp_list[$k] = array_merge($item, array(
                    'truename'=> '--', 
                    'school_name'=> '--', 
                    'grade_name' => '--', 
                    'subject_name' => '--'
                ));
                continue;
            }

            $student['truename'] = $student['last_name'] . $student['first_name'];

            //获取学生学校信息
            $row = SchoolModel::schoolInfo($student['school_id'], 'school_name');
            if ($row)
            {
                $school_name = trim($row['school_name']);
            }
            else
            {
                $school_name = '';
            }
            $student['school_name'] = count($school_name) ? $school_name : '--';

            //获取该学生所在的年级
            $student['grade_name'] = isset($grades[$student['grade_id']]) ? $grades[$student['grade_id']] : '--';

    		//获取科目
    		$subject_name = SubjectModel::get_subject($item['subject_id'], 'subject_name');
    		$student['subject_name'] = count($subject_name) ? $subject_name : '--';
    		
    		//获取考试试卷信息
    		$paper_name = ExamPaperModel::get_paper($item['paper_id'], 'paper_name');
    		$item['paper_name'] = count($paper_name) ? $paper_name : '--';
    		
    		//获取作废记录
    		$etp_invalid_record = ExamTestPaperModel::get_etp_invalid_record($item['etp_id']);
    		if (!$etp_invalid_record) {
    			$item['invalid_record'] = false;
    			$item['invalid_record_note'] = '';
    		} else {
    			$item['invalid_record'] = true;
    			$item['invalid_record_note'] = $etp_invalid_record['note'];
    		}
    		
    		$tmp_list[$k] = array_merge($item, $student);
    	}
    	
		$data['list'] = &$tmp_list;
		$data['search'] = &$search;
		$data['flags'] = &$flags;
		
		// 分页
		$purl = site_url('admin/exam_student_result/index/') . (count($param) ? '?'.implode('&',$param) : '');
		$total = ExamTestPaperModel::count_list($query);
		$data['pagination'] = multipage($total, $per_page, $page, $purl);
		$data['priv_manage'] = $this->check_power('exam_manage', FALSE);
		
    	$this->load->view('exam_student_result/index', $data);
    }

    /**
     * 学生答题详情
     *
     * @author TCG
     * @param int $uid 用户ID
     * @param int $etp_id 考场-试卷-学生关联表
     * @return void
     */
    public function detail ($uid = 0, $etp_id = 0)
    {
        $sql = "select subject_id,exam_id from {pre}exam_test_paper where etp_id='$uid' ";
        $res = $this->db->query($sql)->row_array();
        
        $subject_id = $res['subject_id'];
        $exam_id = $res['exam_id'];
      

 
       $exam = $this->db->select('exam_id, subject_id, grade_id, class_id, total_score, qtype_score')
                    ->get_where('exam', array('exam_id' => $exam_id), 1)->row_array();
        
        $sql = "SELECT  q.type,etr.ques_id,etr.answer,etr.ques_subindex,etr.full_score,etr.test_score,etr.sub_ques_id 
                from {pre}exam_test_result etr
                LEFT JOIN {pre}relate_class rc ON rc.ques_id=etr.ques_id AND rc.grade_id='$exam[grade_id]' AND rc.class_id='$exam[class_id]'
                LEFT JOIn {pre}question q ON etr.ques_id = q.ques_id 
                where etp_id = ? and uid = ? order by rc.difficulty DESC,etr.ques_id ASC, q.sort ASC, etr.sub_ques_id";

        $query = $this->db->query($sql,array($uid, $etp_id));
        
        $sql = "SELECT paper_id FROM {pre}exam_test_paper WHERE etp_id='{$uid}' ";
        $res = $this->db->query($sql)->row_array();
        $paper_id = $res['paper_id'];
       
        $paper = PaperModel::get_paper_by_id($paper_id);
       
        
        $questions_arr = json_decode($paper['question_sort'], true);
        $questions_score = json_decode($paper['question_score'], true);
        
        if ($query->num_rows() > 0)
        {
            $sort = array();
            
            /* 重新排序 */
            if(is_array($questions_arr))
            {
                foreach ($questions_arr as $v) {
                    foreach ($query->result_array() as $value) {
                        if ($v == $value['ques_id']) {
                             
                            $sort[] = $value;
                        }
                    }
                }
            }
            else 
            {
                $sort = $query->result_array();
            }
  
            $result = array();
            $result1 = array();
            foreach ($sort as $key => $row)
            {
                if ($row['sub_ques_id'] > 0)
                {
                    $result[$row['ques_id']][$row['sub_ques_id']]['answer'] = $row['answer'];
                    $result[$row['ques_id']][$row['sub_ques_id']]['full_score'] = $row['full_score'];
                    $result[$row['ques_id']][$row['sub_ques_id']]['test_score'] = $row['test_score'];
                }
                else 
                {
                    $result[$row['ques_id']]['answer'] = $row['answer'];   
                    $result[$row['ques_id']]['full_score'] = $row['full_score'];
                    $result[$row['ques_id']]['test_score'] = $row['test_score'];
                }
                
            }
            
           
            // 试题类型
            foreach ($result as $ques_id => $value)
            {
                $question['type'] = QuestionModel::get_question($ques_id, 'type');
               
                $result[$ques_id]['type'] = $question['type'];
               
                if (in_array($question['type'], array(1,2,3,7,9,11,10,14)))
                {
                    if (in_array($question['type'], array(1, 2, 7, 14)))
                    {
                        $answer = explode(',', $value['answer']);
                        $tmp_answer = array();
                        foreach ($answer as $k => $v)
                        {
                            if (!$v)
                            {
                                continue;   
                            }
                            
                            $option = QuestionModel::get_option($v);
                            if ($option)
                            {
                                $tmp_answer[$k] = '<span>' . $option['option_name'] . '</span>';
                                if ($option['picture'])
                                {
                                    $tmp_answer[$k] .= '<br/><img src="' . __IMG_ROOT_URL__ . $option['picture'] . '" />';
                                }
                            }
                        }
                        
                        $result[$ques_id]['answer'] = $tmp_answer;
                    }
                    else if (in_array($question['type'], array(3, 9)))
                    {
                        $result[$ques_id]['answer'] = explode("\n", $value['answer']);
                    }
                }
                else
                {
                    foreach ($value as $sub_ques_id => $item)
                    {
                        $sub_question['type'] = QuestionModel::get_question($sub_ques_id, 'type');
                        if (in_array($sub_question['type'], array(1, 2)))
                        {
                            $answer = explode(',', $item['answer']);
                            $tmp_answer = array();
                            foreach ($answer as $k => $v)
                            {
                                if (!$v)
                                {
                                    continue;   
                                }
                                
                                $option = QuestionModel::get_option($v);
                                
                                if ($option)
                                {
                                    $tmp_answer[$k] = '<span>' . $option['option_name'] . '</span>';
                                    if ($option['picture'])
                                    {
                                        $tmp_answer[$k] .= '<br/><img src="' . __IMG_ROOT_URL__ . $option['picture'] . '" />';
                                    }
                                }
                            }
                            
                            $result[$ques_id][$sub_ques_id]['answer'] = $tmp_answer;
                        }
                        else if ($sub_question['type'] == 3)
                        {
                            $result[$ques_id][$sub_ques_id]['answer'] = explode("\n", $item['answer']);
                        }
                    }
                }
                
                $groups[$question['type']]['list'][$ques_id] =  $result[$ques_id];
            }

           
            $data['result'] = $groups;
        }
        else
        {
            die('暂无考生考试信息！');
        }
        
        $data['group_index'] = array( '一','二', '三', '四',  '五', '六', '七', '八', '九', '十', '十一', '十二', '十三', '十四');
        
        $this->load->view('exam_student_result/detail', $data);
    }
    
    
    /**
     * 生成某一期考试的考生成绩
     *
     * @param int $exam_pid
     * @return void
     */
    public function generate($exam_pid = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		message('不存在该考试期次.');
    	}
    	
    	$exam = ExamModel::get_exam($exam_pid, 'exam_id, status');
    	if (!count($exam)) {
    		message('不存在该考试期次.');
    	}
    	
    	if (!$exam['status']) {
    	    message('该考试期次未被启用，无法生成考试成绩.');
    	}
    	
    	//获取当前考试期次下考场信息
    	
    	$place_time = ExamPlaceModel::get_exam_place($exam_pid,'MAX(end_time) as end_time');
       /*
        $place_id=array();
        foreach ($place_time as $val)
       {
           $place_id[] = $val['place_id'];
           
       }
      
       //查询日志信息是否存在已生成的考场信息
       $success=0;
       $fail=0;
     
       foreach ($place_id as &$v)
       {
           $log_info = "生成考生成绩(".$v.")";
           
           $row = $this->admin_log_model->get_admin_log($log_info);
           
           if($row['id']>0)
           {
             $fail++;
             unset($v);
           }
          else 
             $success++;
       }
       */
      //end
    	
    	if( $place_time['end_time'] > time())
    	{
    	    message('目前没有成绩可以生成.');
    	}
    	
       //end 
    	
    	
    	//如果日志信息不存在考场信息，且该考场未生成考试成绩
    	
    	$this->load->model('cron/cron_exam_result_model', 'cer_model');
    	
    	$result = $this->cer_model->insert(array('exam_pid'=>$exam_pid));
    	
    	if ($result)
    	{
    	    message("生成考生成绩操作已加入定时任务中，请耐心等候...");
    	}
    	else
    	{
    	    message("生成考生成绩操作加入定时任务失败，请重新执行...");
    	}
    	
    	exit;
//     	/*if ($success>0&&count($place_id)>0)
//     	{*/
//     	    try {
//     	        require_once (APPPATH.'cron/exam.php');
    	    
//     	        $exam_cron = new Exam();
//     	        try {
//     	            /*
//     	             //开启事务
//     	            $this->db->trans_start();
    	             
//     	            $exam_cron->fill_unanswer_questions($exam_pid);//先补齐考生未做的题目
//     	            $exam_cron->cal_test_result_score($exam_pid);//计算考生试题分数
//     	            $exam_cron->cal_test_paper_score($exam_pid);//计算考生试卷分数
    	             
//     	            //提交事务
//     	            $this->db->trans_complete();
//     	            */
    	             
//     	            $exam_cron->cal_test_score($exam_pid);
    	             
//     	        } catch(Exception $e) {
//     	            //$this->db->trans_complete();
//     	            throw new Exception('更新 考生试卷 得分失败，更新字段：' . $t_test_paper . '->test_score, Error:' . $e->getMessage());
//     	        }
    	    
//     	        /*
//     	        foreach ($place_id as $v)
//     	        {
//     	            admin_log('generate', 'exam_student_result', $v);
//     	        }
//     	        */
//     	        message("考生成绩生成成功.");
    	        
//     	        //message("考生成绩生成,成功（".$success."）个,失败（".$fail."）个");
    	         
//     	    } catch(Exception $e) {
//     	        message('考生成绩生成失败，请重试.');
//     	    }
    	/*}*/
    	//不存在考场信息
    /*	else 
    	    message('目前没有成绩可以生成.');
*/
    }
    
    /**
     * 将学生成绩视为作废
     *
     * @return  void
     */
    public function set_invalid() 
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
    	$etp_id = intval($this->input->post('etp_id'));
    	$note = trim($this->input->post('note'));
    	if (!$etp_id || !ExamTestPaperModel::count_list(array('etp_id' => $etp_id))) {
    		message('不存在该考试记录.','javascript');
    	}
    	
    	if ($note == '') {
    		message('作废理由不能为空.','javascript');
    	}
    	
    	$row = ExamTestPaperModel::get_etp_invalid_record($etp_id);
    	if (count($row)) {
            ExamTestPaperModel::update_invalid_record($etp_id, array('note' => $note));
    	} else {
            ExamTestPaperModel::insert_invalid_record(array('note' => $note, 'etp_id' => $etp_id));
    	}
    	
    	message('操作成功','javascript');
    }
    
    /**
     * 将学生成绩 取消 视为作废
     *
     * @return  void
     */
    public function remove_invalid() 
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
    	$etp_id = intval($this->input->get_post('etp_id'));
    	if (!$etp_id || !ExamTestPaperModel::count_list(array('etp_id' => $etp_id))) {
    		message('不存在该考试记录.','javascript');
    	}
    	 
    	$row = ExamTestPaperModel::get_etp_invalid_record($etp_id);
    	if (!count($row)) {
    		message('作废记录不存在.','javascript');
    	} else {
            ExamTestPaperModel::delete_invalid_record($etp_id);
    	}
    	 
    	message('操作成功','javascript');
    }
}
