<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-学生考卷主表数据模块
 *
 * @author qcchen
 * @final 2013-12-06
 */
class Exam_test_paper_model extends CI_Model
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'exam_test_paper';
	//考场学生
	private static $_table_exam_place_student  = 'exam_place_student';
	// 考场科目
	private static $_table_exam_place_subject = 'exam_place_subject';
	// 考试科目所考到的试卷
	private static $_table_exam_subject_paper = 'exam_subject_paper';
	// 学生答题明细表
	private static $_table_exam_test_result = 'exam_test_result';
	// 试题表
	private static $_table_question = 'question';

    /**
     * 构造函数，初始化
     *
     */
    public function __construct()
    {
        parent::__construct();
       // $this->load->driver('cache');
    }

    /**
     * 获取一个考卷信息
     *
     * @param   int     考卷ID(etp_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_test_paper_by_id($id = 0, $item = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $this->db->select($item);
        }
        $query = $this->db->get_where(self::$_table_name, array('etp_id' => $id));
        $row   =  $query->row_array();

        if ($item && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }

    /**
     * 设定学生考卷（每科考试随机选一份试卷）
     *
     * @param	int			考试场次id(place_id)
     * @param	int			学生id
     * @return  array
     */
    public function set_student_test_paper ( $place_id , $uid )
    {
        $this ->load ->model('exam/exam_place_model');
        $subjects = $this ->exam_place_model ->get_exam_place_subject($place_id);

        if(empty($subjects))
            return FALSE;

        $test_papers = array();
        $no_paper_subjects = false;
        $this ->load ->model('exam/exam_paper_model');
        $this ->load ->model('exam/exam_model');
        foreach($subjects as $row)
        {
            $query = $this ->db ->select('paper_id,exam_pid') ->where(
                    array('exam_id'=>$row['exam_id'])) ->order_by('rand()') ->get(
                    self::$_table_exam_subject_paper , 1);
            $arr = $query ->row_array();
            if( ! isset($arr['paper_id']))
            {
                $no_paper_subjects = true;
                break;
            }

            $exam = $this->exam_model->get_exam_by_id($row['exam_id'], 'total_score, class_id, grade_id');
            $total_score = $exam['total_score'];
            $class_id = $exam['class_id'];
            $grade_id = $exam['grade_id'];

            $test_papers[] = array('exam_pid'=>$row['exam_pid'],
                                'exam_id'=>$row['exam_id'], 'uid'=>$uid,
                                'paper_id'=>$arr['paper_id'],
                                'place_id'=>$place_id,
                                'subject_id'=>$row['subject_id'],
                                'full_score'=>$total_score, 'test_score'=>'0.00',
                                'etp_flag'=>0, 'ctime'=>time());
        }

        if($no_paper_subjects)
        {
            return false;
        }

        // save
        $insert_ids = array();
        foreach($test_papers as $val)
        {
            try
            {
                // 关闭错误信息，防止 unique index 冲突出错
                $this ->db ->db_debug = FALSE;
                $query = $this ->db ->select('etp_id')->where(array(
                            'exam_pid'=>$val['exam_pid'],
                            'exam_id'=>$val['exam_id'],
                            'uid'=>$val['uid'],
                            'paper_id'=>$val['paper_id'],
                            'place_id'=>$val['place_id'],
                            'subject_id'=>$val['subject_id']
                            ))->get(self::$_table_name , 1);
                $res1 = $query ->row_array();

                if(!$res1['etp_id'])
                {

                    //echo 'ok';die;
                    $res = $this ->db ->insert(self::$_table_name , $val);

                    if( ! $res)
                    {
                        log_message('error' ,
                                'mysql error:' . $this ->db ->_error_message());
                    }
                    else
                    {
                        $insert_ids[] = $res;
                        if($res)
                        {
                            $etp_id_where = "etp_id = $res";
                            //$this->db->query("delete from {pre}exam_test_result WHERE $etp_id_where");
                            $this ->db ->query("delete from {pre}exam_test_paper_question WHERE $etp_id_where");

                            $sql = "SELECT q.ques_id,q.type
    				    FROM {pre}exam_question eq
    				    LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
    				    LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id=$grade_id
    				    AND rc.class_id=$class_id WHERE eq.paper_id={$val['paper_id']}
    				    ORDER BY rc.difficulty DESC,q.ques_id ASC";

                            $result = $this ->db ->query($sql) ->result_array();
                            if($val['subject_id'] == 3)
                            {
                                $types = array('1', '4', '0', '5', '6', '7', '2', '3', '8', '9');
                            }
                            else
                            {
                                $types = array('1', '2', '3', '0');
                            }

                            $paper_array = array();

                            foreach($types as $type)
                            {
                                foreach($result as $key =>$row)
                                {
                                    if($row['type'] != $type)
                                    {
                                        continue;
                                    }
                                    $paper_array[] = $row['ques_id'];
                                    unset($result[$key]);
                                }
                            }

                            $ques_ids = implode(',' , $paper_array);

                            $this ->db ->insert('exam_test_paper_question' ,
                                    array('etp_id'=>$res, 'ques_id'=>$ques_ids,
                                        'c_time'=>time(), 'u_time'=>time()));
                        }

                    }

                 }
            }
            catch(Exception $e)
            {
                //do nothing
            }
        }

        return $insert_ids;
    }

    /**
     * 获取学生所在考场
     *
  	 * @param	int			考试场次id(place_id)
  	 * @param	int			学生id(uid)
  	 * @param	string		自定义获取字段
     * @return  array
     */
    public function get_stduent_test_place($place_id, $uid, $select_items = null, $flag = '0')
    {
    	$where = array(
    					'uid' 		=> $uid,
    					'place_id' 	=> $place_id,
    	);
    	if (!is_null($flag)) {
    		if (is_numeric($flag)) {
	    		$where['flag'] = $flag;
    		} elseif(is_array($flag)) {
	    		$where['flag ' . $flag[0]] = $flag[1];
    		}
    	}
    	$select_item = is_null($select_items) ? 'flag, why' : $select_items;
		$query = $this->db->select($select_item)->get_where(self::$_table_exam_place_student, $where);
    	return $query->result_array();
    }



    /**
     * 获取学生考卷
     *
     * @param	int			考试场次id(place_id)
     * @param	int			学生id(uid)
     * @param	string		自定义获取字段
     * @return  array
     */
    public function get_stduent_test_paper($place_id, $uid, $select_items = null, $flag = '0')
    {




            $where = array(
                    'uid' 		=> $uid,
                    'place_id' 	=> $place_id,
            );
            if (!is_null($flag)) {
                if (is_numeric($flag)) {
                    $where['etp_flag'] = $flag;
                } elseif(is_array($flag)) {
                    $where['etp_flag ' . $flag[0]] = $flag[1];
                }
            }

            $select_item = is_null($select_items) ? 'etp_id, paper_id, etp_flag, full_score, subject_id' : $select_items;
            $query = $this->db->select($select_item)->get_where(self::$_table_name, $where);



            return $query->result_array();


    }



    /**
     * 获取学生当前考试状态：false(未分配试卷) -1（试卷作废）  0（考试进行中，未交卷） 1（已交卷，未出成成绩） 2（已交卷，已出成绩）
     *
     * @param	int			考试场次id(place_id)
     * @param	int			学生id(uid)
     * @return  mixed
     */
    public function get_student_test_status($place_id, $uid)
    {

        $test_papers = $this->get_stduent_test_paper($place_id, $uid, 'etp_flag', null);

    	if (empty($test_papers)) return false;

    	return $test_papers[0]['etp_flag'];
    }


    /**
     * 获取学生当前考试状态： 0（正常） 1（被踢出）
     *
     * @param	int			考试场次id(place_id)
     * @param	int			学生id(uid)
     * @return  mixed
     */
    public function get_student_place_status($place_id, $uid)
    {
        $test_papers = $this->get_stduent_test_place($place_id, $uid, 'flag,why', null);
        if (empty($test_papers)) return false;

        return array('flag'=>$test_papers[0]['flag'],'why'=>$test_papers[0]['why']);
    }


    /**
     * 更新考生的考试记录
     *
     * @param	mixed				考试记录ID
     * @return  boolean
     */
    public function update_student_test_status($etp_id, $etp_flag = '1')
    {
    	return $this->update($etp_id, array('etp_flag' => $etp_flag));
    }


    /**
     * 单条修改记录
     *
     * @param	mixed			考试记录ID
     * @param   array   		更新数据
     * @return  boolean
     */
    public function update($etp_id, $data)
    {
    	try {
    		if (is_array($etp_id)) {
    			$this->db->where_in('etp_id', $etp_id)->update(self::$_table_name, $data);
    		} else {
    			$this->db->where('etp_id', $etp_id)->update(self::$_table_name, $data);
    		}
    		return TRUE;
    	} catch(Exception $e) {
    		return FALSE;
    	}
    }

    /**
     * 获取学生考卷答题记录
     *  返回格式： $list[ques_id] = $status;
     *  $status : 0(未解答)，1（部分解答），2（已解答）。无记录表示试题未查看
     *
     * @param	int			学生考卷id(exam_test_paper)
     * @param	boolean		是否包含题组子题状态
     * @return  array
     */
    public function get_test_paper_record($etp_id, $include_children_ques = FALSE)
    {
    	$list = array();

    	// 题组中已解答的子题数量，用于判断题组是否解答完成
    	$group_ques_complete_num = array();
    	// 题组子题总数
    	$group_ques_total_num = array();

    	//$this->load->model('exam/exam_test_result');
    	//$query = $this->exam_test_result->get_test_result_list(array('etp_id'=>$etp_id), 'ques_id,ques_index,ques_subindex,answer');

    	// 获取试卷解答记录
    	$this->db->select('etr.ques_id, etr.ques_index, etr.answer, q.type, q.parent_id, q.children_num, q.answer ques_answer');
    	$this->db->from(self::$_table_exam_test_result . ' etr');
    	$this->db->join(self::$_table_question . ' q', 'etr.ques_id=q.ques_id');
    	$this->db->where('etr.etp_id', $etp_id);
    	$query = $this->db->get();

    	foreach ($query->result_array() as $row)
    	{
    		if ($row['type'] == 0) {
    			// 题组
    			if ( ! isset($list[$row['ques_id']])) {
    				$list[$row['ques_id']] = 0;
    				$group_ques_complete_num[$row['ques_id']] = 0;
    				$group_ques_total_num[$row['ques_id']] = $row['children_num'];
    			}
    		} else {
    			if (empty($row['answer'])) {
    				// 未解答
    				$list[$row['ques_id']] = 0;
    			} else {
    				if ($row['type'] < 3) {
    					// 选择题, 解答即算完成
    					$list[$row['ques_id']] = 2;
    				} else {
    					// 填空题, 判断解答个数是否和答案个数相同
    					if (count(explode("\n", trim($row['answer']))) < count(explode("\n", trim($row['ques_answer'])))) {
    						$list[$row['ques_id']] = 1;
    					} else {
    						$list[$row['ques_id']] = 2;
    					}
    				}
    			}
    			if ($row['parent_id'] > 0) {
    				// 初始化题组状态
    				if ( ! isset($list[$row['parent_id']]) ) {
    					$list[$row['parent_id']] = 0;
    					$group_ques_complete_num[$row['parent_id']] = 0;
    				}

    				// 如果题组状态已经是部分解答，则跳过判断
    				if ($list[$row['parent_id']] == 1) continue;

    				switch ($list[$row['ques_id']]) {
    					case 0 :
    						if ($group_ques_complete_num[$row['parent_id']] > 0) {
	    						$list[$row['parent_id']] = 1;
	    						unset($group_ques_complete_num[$row['parent_id']]);
    						}
    						break;
    					case 1 :
    						$list[$row['parent_id']] = 1;
    						unset($group_ques_complete_num[$row['parent_id']]);
    						break;
    					case 2 :
    						$group_ques_complete_num[$row['parent_id']]++;
    						break;
    					default:
    						break;
    				}

    				// 是否不包含题组子题状态
    				if ( ! $include_children_ques)
    					unset($list[$row['ques_id']]);
    			}
    		}
    	}

    	// 设置剩余题组状态
    	foreach ($ques_group_complete_num as $ques_id => $num)
    	{
    		if ($num == 0) continue;
    		$list[$ques_id] = $group_ques_total_num[$ques_id]==$num ? 2 : 1;
    	}

    	return $list;
    }

}

/* End of file exam_test_paper_model.php */
/* Location: ./application/models/exam/exam_test_paper_model.php */