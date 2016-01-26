<?php if ( ! defined('BASEPATH')) exit();
/**
 * 计划任务--考试期次考场学生分配试卷
 * @author tcg
 * @final 2015-07-24
 */
class Cron_place_student_paper_model extends CI_Model
{
    private static $_db;
    private static $_qchildren;//考试试题子题
    private static $_option;   //试题选项
    private static $_qtype;    //试题类型
    private static $_paper_ques = array(); //试卷试题
    private static $_place_exams = array(); //考场考试

	public function __construct()
	{
		parent::__construct();

		self::$_db = Fn::db();
	}

	/**
	 * 获取单条 信息
	 */
	public function get_task_place_student_paper_list()
	{
	    //提前一天计算学生考试记录
		$start_time = time() + 24*3600;
		$end_time = time();

		$sql = "SELECT epsp.id, epsp.place_id, uid_data, exam_pid
		        FROM rd_cron_task_place_student_paper epsp
		        LEFT JOIN rd_exam_place ep ON ep.place_id = epsp.place_id
		        WHERE status IN (0, 1)
		        AND start_time <= $start_time AND end_time >$end_time
		        ORDER BY status ASC
		        LIMIT 5";

		return self::$_db->fetchAll($sql);
	}

	/**
	 * 设置计划任务执行结果
	 */
	public function set_task_exam_test_paper_question_status($param, $id)
	{
	    if (!$param || !$id)
	    {
	        return false;
	    }

	    $where = '';
	    if (is_array($id))
	    {
	        $where .= "id IN (" . implode(',', $id) . ")";
	    }
	    else
	    {
	        $where .= "id = $id";
	    }

	    $where .= " AND status < 2";

	    return self::$_db->update('rd_exam_test_paper_question', $param, $where);
	}

	/**
	 * 设置计划任务执行结果
	 */
	public function set_task_exam_result_status($param, $id)
	{
		if (!$param || !$id)
		{
			return false;
		}

		$where = '';
		if (is_array($id))
		{
		    $where .= "id IN (" . implode(',', $id) . ")";
		}
		else
		{
		    $where .= "id = $id";
		}

		$where .= " AND status < 2";

		return self::$_db->update('rd_cron_task_place_student_paper', $param, $where);
	}

	/**
	 * 添加任务
	 */
	public function insert($data)
	{
	    if (empty($data['place_id']))
	    {
		    return false;
	    }

	    if (empty($data['uid_data']))
	    {
	        return false;
	    }

	    $data['c_time'] = time();
	    $data['u_time'] = time();
	    $data['status'] = 0;

		self::$_db->insert('rd_cron_task_place_student_paper', $data);

		return self::$_db->lastInsertId('rd_cron_task_place_student_paper', 'id');
	}

	/**
	 * 设定学生考卷（每科考试随机选一份试卷）
	 *
	 * @param	int			考试场次id(place_id)
	 * @param	int			学生id
	 * @return  array
	 */
	public function init_test_paper($place_id, $uid)
	{
	    $sql = "SELECT COUNT(*) FROM rd_exam_place_student
	           WHERE place_id = ? AND uid = ?";
	    if (!self::$_db->fetchOne($sql, array($place_id, $uid)))
	    {
	        return false;
	    }
	    
	    if (!isset(self::$_place_exams[$place_id]))
	    {
	        $sql = "SELECT `e`.`exam_id`, `eps`.`subject_id`, `e`.`exam_pid`,
	                `e`.`total_score`, `e`.`class_id`, `e`.`grade_id`
	                FROM (`rd_exam_place_subject` eps)
	                LEFT JOIN `rd_exam` e ON `e`.`exam_id` = `eps`.`exam_id`
	                WHERE `eps`.`place_id` = $place_id";
	        $subjects = self::$_db->fetchAssoc($sql);

	        self::$_place_exams[$place_id] = $subjects;
	    }

	    $subjects = self::$_place_exams[$place_id];
	    if (empty($subjects))
	    {
	        return false;
	    }

	    $test_papers = array();
	    $no_paper_subjects = false;
	    
	    $paper_ids = array();
	    foreach ($subjects as $exam_id => $row)
	    {
	        $sql = "SELECT `paper_id` FROM (`rd_exam_subject_paper`)
	                WHERE `exam_id` = $exam_id ORDER BY rand()";

	        $paper_id = self::$_db->fetchOne($sql);
	        if (!$paper_id)
	        {
	            $no_paper_subjects = true;
	            break;
	        }
	        
	        $paper_ids[] = $paper_id;

	        $class_id = $row['class_id'];
	        $grade_id = $row['grade_id'];

	        $test_papers[] = array(
	                'exam_pid'   => $row['exam_pid'],
	                'exam_id'	 => $row['exam_id'],
	                'uid'		 => $uid,
	                'paper_id'	 => $paper_id,
	                'place_id'   => $place_id,
	                'subject_id' => $row['subject_id'],
	                'full_score' => $row['total_score'],
	                'test_score' => '0.00',
	                'etp_flag' 	 => 0,
	                'ctime'      => time()
	        );
	    }

	    if ($no_paper_subjects)
	    {
	        return false;
	    }
	    
	    //手工组卷试卷试题
	    $sql = "SELECT paper_id, question_sort FROM rd_exam_paper
	           WHERE paper_id IN (" . implode(',', $paper_ids) . ")
	           AND admin_id > 0";
	    $paper_question = self::$_db->fetchPairs($sql);

        if (!self::$_db->beginTransaction())
        {
            return false;
        }

	    // save
	    foreach ($test_papers as $val)
	    {
	        $paper_id = $val['paper_id'];
	        $exam_pid = $val['exam_pid'];
	        $exam_id = $val['exam_id'];
	        $place_id = $val['place_id'];
	        $subject_id = $val['subject_id'];
	        $uid = $val['uid'];

	        $sql = "SELECT etp_id FROM rd_exam_test_paper
        	        WHERE exam_pid = $exam_pid AND exam_id = $exam_id
	                AND place_id = $place_id AND subject_id = $subject_id
        	        AND uid = $uid
        	        ";
	        $etp_id = self::$_db->fetchOne($sql);

	        //已分配试卷，无需重复分配
	        if ($etp_id > 0)
	        {
	            $res = true;
	            continue;
            }
            else
            {
                $res = self::$_db->insert('rd_exam_test_paper', $val);
                //添加试卷试题
                $etp_id = self::$_db->lastInsertId('rd_exam_test_paper', 'etp_id');
            }

            if ($res && $etp_id)
            {
                if (isset($paper_question[$paper_id]))
                {
                    $ques_ids = implode(',', json_decode($paper_question[$paper_id], true));
                }
                else 
                {
                    if (!isset(self::$_paper_ques[$paper_id]))
                    {
                        $sql = "SELECT q.ques_id,q.type FROM rd_exam_question eq
                                LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
                                LEFT JOIN rd_relate_class rc ON rc.ques_id=q.ques_id
                                AND rc.grade_id=$grade_id AND rc.class_id=$class_id
                                WHERE eq.paper_id=$paper_id
                                ORDER BY rc.difficulty DESC,q.ques_id ASC";
    
                        $result = self::$_db->fetchPairs($sql);
    
                        if ($subject_id == 3)
                        {
                            $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
                        }
                        else
                        {
                            $types = array('1','2','3','0','10','14','15','11');
                        }
            
                        $paper_array = array();
    
                        foreach ($types as $type)
                        {
                            foreach ($result as $ques_id => $q_type)
                            {
                                if ($q_type != $type)
                                {
                                    continue;
                                }
    
                                $paper_array[] = $ques_id;
                            }
                        }
    
                        unset($result);
    
                        self::$_paper_ques[$paper_id] = implode(',', $paper_array);
                    }
    
                    $ques_ids = self::$_paper_ques[$paper_id];
                }

                $res = self::$_db->insert('rd_exam_test_paper_question',
                        array('etp_id' => $etp_id, 'ques_id'=>$ques_ids));
            }

            if (!$res)
            {
                break;
            }
        }

        return self::$_db->commit();
    }

    /**
     * 学生考试试题分配
     * @param   int     $exam_pid    考试期次
     * @param   int     $place_id    考试考场
     * @param   array   $uids        考场学生
     * @return  boolean
     */
    public function init_test_question($exam_pid, $place_id, $uids)
    {
        $exam_pid = intval($exam_pid);
        $place_id = intval($place_id);
        if (!$exam_pid || !$place_id)
        {
            return false;
        }

        $where = '';
        if ($uids)
        {
            $uid_str = implode(',', $uids);
            $where = "AND etp.uid IN ($uid_str)";
        }
        
        //删除试卷中不存在的试题考试记录
        $sql = "DELETE FROM rd_exam_test_result
                WHERE exam_pid = $exam_pid 
                AND NOT EXISTS (
                    SELECT eq.ques_id
                    FROM rd_exam_test_paper etp
                    LEFT JOIN rd_exam_question eq ON eq.paper_id = etp.paper_id
                    WHERE etp.exam_pid = $exam_pid AND etp.place_id = $place_id
                )";
        self::$_db->query($sql);

        //获取考试中学生分配到的试卷试题
        $sql = "SELECT etp.exam_pid, etp.exam_id, etp.etp_id, etp.uid, etp.paper_id, eq.ques_id
                FROM rd_exam_test_paper etp
                LEFT JOIN rd_exam_question eq ON eq.paper_id = etp.paper_id
                WHERE etp.exam_pid = $exam_pid AND etp.place_id = $place_id
                {$where} AND NOT EXISTS
                (
                    SELECT etr.ques_id FROM rd_exam_test_result etr
                    WHERE etr.etp_id = etp.etp_id AND eq.ques_id = etr.ques_id
                )";

        $query = self::$_db->query($sql);

        $paper_id = array();
        $ques_ids = array();
        $data = array();

        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_id[] = $item['paper_id'];
            $ques_ids[] = $item['ques_id'];
            $data[]     = $item;
        }

        if (!$paper_id || !$ques_ids)
        {
            return false;
        }

        $paper_id = array_unique($paper_id);
        $ques_ids = array_unique($ques_ids);

        $paper_detail = $this->paperDetail($paper_id);  //试卷信息
        //print_r($paper_detail);die;
        if (!$paper_detail)
        {
            return false;
        }

        $this->question($ques_ids);    //试题信息

        $insert_data = array();

        foreach ($data as $item)
        {
            @list($exam_pid, $exam_id, $etp_id, $uid, $paper_id, $ques_id) = array_values($item);

            $q_type = self::$_qtype[$ques_id];
            $ques_info = $paper_detail[$paper_id][$q_type][$ques_id];
            $paper_score = $paper_detail[$paper_id]['paper_score'];
            $question_score = $paper_detail[$paper_id]['question_score'];

            //print_r($paper_score);die;

            if (!$ques_info)
            {
                continue;
            }

            $tmp_data = array(
                'etp_id' 		=> $etp_id,
                'exam_pid' 		=> $exam_pid,
                'exam_id' 		=> $exam_id,
                'uid' 			=> $uid,
                'paper_id' 		=> $paper_id,
                'ques_id' 		=> $ques_id,
                'ques_index' 	=> $ques_info['ques_index'],
                'sub_ques_id'   => 0,
                'ques_subindex' => 0,
                'option_order' 	=> '',
                'answer' 		=> '',
                'full_score' 	=> isset($question_score[$ques_id][0]) ? $question_score[$ques_id][0] : $ques_info['full_score'],
                'test_score' 	=> 0,
            );
            $qtype_score = explode(',', $item['qtype_score']);
            //非题组
            if (in_array($q_type, array(1, 2, 3, 7, 9, 11, 14)))
            {
                if (!empty(self::$_option[$ques_id]))
                {
                    shuffle(self::$_option[$ques_id]);
                    $tmp_data['option_order'] = implode(',', array_keys(self::$_option[$ques_id]));
                }

                //self::$_db->insert("rd_exam_test_result", $tmp_data);
                $insert_data[] = $tmp_data;
            }
            //解答题、组合题特殊处理
            else if (in_array($q_type, array(10, 15)))
            {
                //有子题
                if (isset(self::$_qchildren[$ques_id])
                    && self::$_qchildren[$ques_id])
                {
                    $sub_index = 1;
                    foreach (self::$_qchildren[$ques_id] as $k => $sub_ques_id)
                    {
                        $tmp_data['ques_subindex'] = $sub_index;
                        $tmp_data['sub_ques_id'] = $sub_ques_id;
                        $tmp_data['full_score'] = $question_score[$ques_id][$k];
                        $insert_data[] = $tmp_data;
                        $sub_index++;
                    }
                }
                else 
                {
                    $insert_data[] = $tmp_data;
                }
            }
            else if (in_array($q_type, array(0, 4, 5, 6, 8, 12, 13)))
            {
                // 题组子题
                $children = self::$_qchildren[$ques_id];
                if (!$children)
                {
                    continue;
                }

                $sub_index = 1;
                $left_score = $ques_info['full_score'];
                $child_num = count($children);

                foreach ($children as $sub_ques_id)
                {
                    $tmp_data['ques_subindex'] = $sub_index;
                    $tmp_data['sub_ques_id'] = $sub_ques_id;

                    if (!empty(self::$_option[$sub_ques_id]))
                    {
                        shuffle(self::$_option[$sub_ques_id]);
                        $tmp_data['option_order'] = implode(',', array_keys(self::$_option[$sub_ques_id]));
                    }
                    else
                    {
                        $tmp_data['option_order'] = '';
                    }

                    if ($sub_index < $child_num)
                    {
                        $tmp_data['full_score'] = $paper_score[parent][$ques_id][children][$sub_ques_id][score][0]?$paper_score[parent][$ques_id][children][$sub_ques_id][score][0]:round($ques_info['full_score']/$child_num, 2);
                        $left_score -= $tmp_data['full_score'];
                    }
                    else
                    {
                        $tmp_data['full_score'] = $paper_score[parent][$ques_id][children][$sub_ques_id][score][0]?$paper_score[parent][$ques_id][children][$sub_ques_id][score][0]:$left_score;
                    }

                    $sub_index++;

                    $insert_data[] = $tmp_data;
                    //self::$_db->insert("rd_exam_test_result", $tmp_data);
                }
            }
        }
        
        if (self::$_db->beginTransaction())
        {
            foreach ($insert_data as $bind)
            {
                self::$_db->replace("rd_exam_test_result", $bind);
            }
        }
        
        $flag = self::$_db->commit();
        if (!$flag)
        {
            self::$_db->rollBack();
        }
        
        return $flag;
    }

    /**
     * 试卷详细信息
     * @param   int|array   $paper_id    试卷id
     * @return  array       $paper_detail
     */
    private function paperDetail($paper_id)
    {
        if (!$paper_id)
        {
            return array();
        }

        $paper_id_str = implode(',', $paper_id);

        $sql = "SELECT eq.paper_id, q.ques_id, q.type, q.score_factor
                FROM rd_exam_question eq
                LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
                LEFT JOIN rd_exam e ON e.exam_id = eq.exam_id
                LEFT JOIN rd_relate_class rc ON rc.ques_id = q.ques_id
                AND rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                WHERE eq.paper_id IN ($paper_id_str)
                ORDER BY rc.difficulty DESC,q.ques_id ASC";

        $query = self::$_db->query($sql);

        $exam_paper_question = array();  //考试试卷试题
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $exam_paper_question[$item['paper_id']][$item['ques_id']] = $item;
        }

        if (!$exam_paper_question)
        {
        	return array();
        }

        $sql = "SELECT e.exam_id, esp.paper_id, e.subject_id, e.grade_id, ep.paper_score,
                e.class_id, e.total_score, e.qtype_score, ep.question_score
                FROM rd_exam e
                LEFT JOIN rd_exam_subject_paper esp ON e.exam_id = esp.exam_id
                LEFT JOIN rd_exam_paper ep on ep.paper_id = esp.paper_id
                WHERE esp.paper_id IN ($paper_id_str)";
        $query= self::$_db->query($sql);

        $paper_detail = array();

        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            if ($item['subject_id'] == 3)
            {
                $groups = array(
                    1 => array(),
                    4 => array(),
                    0 => array(),
                    5 => array(),
                    6 => array(),
                    7 => array(),
                    2 => array(),
                    3 => array(),
                    8 => array(),
                    9 => array(),
                    10 => array(),
                    11 => array(),
                    12 => array(),
                    13 => array(),
                    14 => array(),
                    15 => array(),
                );
            }
            else
            {
                $groups = array(
                    1 => array(),
                    2 => array(),
                    3 => array(),
                    0 => array(),
                    10 => array(),
                    11 => array(),
                    14 => array(),
                    15 => array(),
                );
            }

            $question_score = @json_decode($item['question_score'], true);
            $qtype_score = explode(',', $item['qtype_score']);
            $index = 1;
            $tmp_data = $exam_paper_question[$item['paper_id']];
            $total_score_factor = 0;// 题组试题总的分值系数
            $total_score = $item['total_score']; //试卷总分
            $paper_score = unserialize($item['paper_score']);

            foreach ($groups as $type => &$group)
            {
                foreach ($tmp_data as $ques_id => $val)
                {
                    if ($val['type'] == $type)
                    {
                        $group[$ques_id]['ques_index'] = $index++;

                        if ($val['type'] > 0)
                        {
                            if (isset($question_score[$ques_id])
                                && $question_score[$ques_id])
                            {
                                $group[$ques_id]['full_score'] = array_sum($question_score[$ques_id]);
                                $total_score -= $group[$ques_id]['full_score'];
                            }
                            else
                            {
                                $total_score -= $qtype_score[$val['type'] - 1];
                                $group[$ques_id]['full_score'] = $qtype_score[$val['type'] - 1];
                            }
                        }

                        if ($type == 0)
                        {
                            $total_score_factor += $val['score_factor'];
                        }
                    }
                }
            }

            $groups = array_filter($groups);
            if (!empty($groups[0]))
            {
                foreach ($groups[0] as $ques_id => &$list)
                {
                    $list['full_score'] = $paper_score[parent][$ques_id][score][0]?$paper_score[parent][$ques_id][score][0]:round($total_score * $tmp_data[$ques_id]['score_factor'] / $total_score_factor);
                }
            }

            $paper_detail[$item['paper_id']] = $groups;
            $paper_detail[$item['paper_id']]['paper_score'] = $paper_score;
            $paper_detail[$item['paper_id']]['question_score'] = $question_score;
        }

        return $paper_detail;
    }

    /**
     * 试题信息
     * @param   int|array   $ques_ids    试题id
     * @return  void
     */
    private function question($ques_ids)
    {
        if (!$ques_ids)
        {
            return array();
        }

        $ques_id_str = implode(',', $ques_ids);

        $sql = "SELECT ques_id, type FROM rd_question
                WHERE ques_id IN ($ques_id_str)";
        $query = self::$_db->query($sql);

        $question = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            self::$_qtype[$row['ques_id']] =$row['type'];

            if (in_array($row['type'], array(0,4,5,6,8,10,12,13,15)))
            {
                // 题组
                $this->questionChildren($row['ques_id']);
            }
            elseif (in_array($row['type'], array(1,2,7,14)))
            {
                // 选择题
                self::$_option[$row['ques_id']] = $this->questionOption($row['ques_id']);
            }
        }
    }

    /**
    * 读取题组子题列表
    *
    * @param   int     $ques_id    试题id
    * @return  void
    */
    private function questionChildren($ques_id)
    {
        $sql = "SELECT ques_id, type FROM rd_question
                WHERE parent_id = $ques_id AND is_delete = 0
                ORDER BY sort ASC,ques_id ASC";
        $query = self::$_db->query($sql);
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            self::$_qtype[$row['ques_id']] = $row['type'];
            self::$_qchildren[$ques_id][] = $row['ques_id'];

            if (in_array($row['type'],array(1,2)))
            {
                self::$_option[$row['ques_id']] = $this->questionOption($row['ques_id']);
            }
        }
    }

    /**
     * 读取试题选项列表
     *
     * @param   int    试题id
     * @return  array
     */
    private function questionOption($ques_id)
    {
    	$sql = "SELECT option_id FROM rd_option
    	       WHERE ques_id = $ques_id";
    	return self::$_db->fetchAll($sql);
    }
}
