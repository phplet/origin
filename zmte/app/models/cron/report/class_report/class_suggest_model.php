<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-班级-诊断及建议
 * @author TCG
 * @final 2015-10-12
 */
class Class_suggest_model extends CI_Model 
{
    private static $_db;
    private static $_data;
    
    public function __construct()
    {
        parent::__construct();
        
        self::$_db = Fn::db();
        
        $this->load->model('cron/report/class_report/class_common_model');
    }
	
    /**
     * 各分数段人数比例分布情况
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $schcls_id 班级ID 
     */
    public function module_score_proportion($rule_id = 0, $exam_id = 0, $schcls_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $schcls_id = intval($schcls_id);
        if (!$rule_id || !$exam_id || !$schcls_id)
        {
            return array();
        }
        
        $sql = "SELECT total_score FROM rd_exam 
                WHERE exam_id = $exam_id";
        
        $total_score = self::$_db->fetchOne($sql);
        if ($total_score <= 0)
        {
            return array();
        }
        
        $class = $this->class_common_model->get_class_info($schcls_id);
        
        if (!isset(self::$_data['school_rank'][$exam_id][$class['school_id']]))
        {
            $sql = "SELECT a.uid, rank, test_score
                    FROM rd_summary_region_student_rank a
                    left join rd_student s on s.uid = a.uid
                    WHERE exam_id = $exam_id AND region_id = ?
                    AND is_school = 1 AND is_class = 0
                    ORDER BY rank ASC";
            $grade_rank = self::$_db->fetchAssoc($sql, array($class['school_id']));
            self::$_data['school_rank'][$exam_id][$class['school_id']] = $grade_rank;
        }
        else 
        {
            $grade_rank = self::$_data['school_rank'][$exam_id][$class['school_id']];
        }
        
        if (!$grade_rank)
        {
            return array();
        }
        
        $student_num = count($grade_rank);
        
        if (!isset(self::$_data['class_uid'][$exam_id][$class['schcls_id']]))
        {
            $sql = "SELECT uid FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = ?
                    AND is_school = 0 AND is_class = 1
                    ORDER BY rank ASC";
            $class_uid = self::$_db->fetchCol($sql, array($class['schcls_id']));
            self::$_data['class_uid'][$exam_id][$class['schcls_id']] = $class_uid;
        }
        else
        {
            $class_uid= self::$_data['class_uid'][$exam_id][$class['schcls_id']];
        }
        
        if (!$class_uid)
        {
            return array();
        }
        
        //分段比例
	    $proportion = $this->class_common_model->get_rule_distribution_proportion($rule_id);
        //分段临界排名
        $ranks = array();
        //分段临界分数
        $scores = array();
        foreach ($proportion as $name => $rate)
        {
            $ranks[] = $student_num * $rate / 100;
            $scores[] = 0;
        }
        
        //分数间隔
        $step_score = 5;
        //x轴取点数量
        $point_num = ceil($total_score / $step_score);
        
        $data = array();
        
        $data['grd_students'] = $student_num;
        $data['cls_students'] = count($class_uid);
        
        $flash_data = array();
        $prev_score = 0;
        for ($i = 1; $i <= $point_num; $i++)
        {
            $score = $i * $step_score;
            $score = $score > $total_score ? $total_score : $score;
            
            if (!$grade_rank)
            {
                break;
            }
        
            foreach ($grade_rank as $uid => $item)
            {
                if (!(($prev_score < $item['test_score'] && $item['test_score'] <= $score)
                    || ($i == 1 && $item['test_score'] < 1)))
                {
                    continue;
                }
                
                unset($grade_rank[$uid]);
                
                if (!isset($flash_data['grd_num'][$score]))
                {
                    $flash_data['grd_num'][$score] = 0;
                }
                $flash_data['grd_num'][$score]++;
                
                $prev_rank = 0;
                foreach ($ranks as $k => $rank)
                {
                    if ($prev_rank < $item['rank'] 
                        && $item['rank'] <= $rank)
                    {
                        if (!isset($data['cls_num_' . $k]))
                        {
                            $data['grd_num_' . $k] = 0;
                        }
                        
                        $data['grd_num_' . $k]++;
                        
                        if (!$scores[$k] || $scores[$k] > $item['test_score'])
                        {
                            $scores[$k] = round($item['test_score'], 1);
                        }
                        
                        if (in_array($uid, $class_uid))
                        {
                            if (!isset($flash_data['cls_num'][$score]))
                            {
                                $flash_data['cls_num'][$score] = 0;
                            }
                            $flash_data['cls_num'][$score]++;
                            
                            if (!isset($data['cls_num_' . $k]))
                            {
                                $data['cls_num_' . $k] = 0;
                            }
                            
                            $data['cls_num_' . $k]++;
                        }
                        
                        break;
                    }
                    
                    $prev_rank = $rank;
                }
            }
            
            $prev_score = $score;
        }
        
        $f_data = array();
        $fields = array();
        for ($i = 1; $i <= $point_num; $i++)
        {
            $score = $i * $step_score;
            $score = $score > $total_score ? $total_score : $score;
        
            $fields[] = $score;
            if (!isset($flash_data['cls_num'][$score]))
            {
                $f_data[0]['name'] = '本班';
                $f_data[0]['data'][] = 0;
            }
            else
            {
                $f_data[0]['name'] = '本班';
                $f_data[0]['data'][] = round($flash_data['cls_num'][$score] / $data['cls_students'] * 100);
            }
        
            if (!isset($flash_data['grd_num'][$score]))
            {
                $f_data[1]['name'] = '全校';
                $f_data[1]['data'][] = 0;
            }
            else
            {
                $f_data[1]['name'] = '全校';
                $f_data[1]['data'][] = round($flash_data['grd_num'][$score] / $student_num * 100);
            }
        }

        return array(
            'data' => $data,
            'fields' => $fields,
            'flash_data' => $f_data,
            'scores' => $scores,
            'proportion' => $proportion,
            'step_score' => $step_score
        );
    }
    
    /**
     * 相同知识点强弱点分布对比
	 * @param number $rule_id 规则id
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID 
     */
    public function module_contrast_knowledge($rule_id = 0, $exam_id = 0, $schcls_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $schcls_id = intval($schcls_id);
        if (!$rule_id || !$exam_id || !$schcls_id)
        {
            return array();
        }
        
        //对比考试id
        $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
        if (!$contrast_exam_id)
        {
        	return array();
        }
        
        $class = $this->class_common_model->get_class_info($schcls_id);
        
        $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
        if (!$class_uids)
        {
            return array();
        }
        $cls_uid_str = implode(',', $class_uids);
        
        //当前考试知识点
        $sql = "SELECT ssk.knowledge_id
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 0 AND exam_id = $exam_id AND uid IN ($cls_uid_str)
                ";
        $curr_knowledge = self::$_db->fetchCol($sql);
        if (!$curr_knowledge)
        {
            return array();
        }
        
        //对比考试知识点
        $sql = "SELECT ssk.knowledge_id
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 0 AND exam_id = $contrast_exam_id AND uid IN ($cls_uid_str)
                AND knowledge_id IN (" . implode(',', $curr_knowledge) . ")
                ";
        $contrast_knowledge = self::$_db->fetchCol($sql);
        if (!$contrast_knowledge)
        {
        	return array();
        }
        
        $new_knowledge = array_diff($curr_knowledge, $contrast_knowledge);
        
        $data = array();
        
        //获取该班级所考到试题关联的答对 二级知识点 & 认知过程
        $sql = "SELECT ssk.uid, ssk.knowledge_id, k.knowledge_name, spk.know_process_ques_id,
                ssk.know_process_ques_id AS right_know_process_ques_id
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_summary_paper_knowledge spk ON ssk.paper_id=spk.paper_id AND ssk.knowledge_id=spk.knowledge_id
                LEFT JOIN rd_knowledge k ON ssk.knowledge_id=k.id
                WHERE ssk.exam_id = ? AND ssk.uid IN ($cls_uid_str) 
                AND ssk.is_parent=0 AND ssk.knowledge_id IN (" . implode(',', $contrast_knowledge) . ")
                ";
        $query = self::$_db->query($sql, array($exam_id));
        $query2 = self::$_db->query($sql, array($contrast_exam_id));
        
        $know_processes = C('know_process');
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $know_process_ques_id = json_decode($item['know_process_ques_id'], true);//该二级知识点关联的 认知过程 试题
            $right_know_process_ques_id = json_decode($item['right_know_process_ques_id'], true);//班级答对情况
            $knowledge_name = $item['knowledge_name'];
            	
            $know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
            $right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();
            	
            foreach ($know_processes as $know_process => $kp_name)
            {
                $all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
                $right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;
        
                if ($all_questions)
                {
                    $data[$knowledge_name][0][0] = '本班本次考试';
                    $data[$knowledge_name][0][$know_process]['total_question'] += $all_questions;
                    $data[$knowledge_name][0][$know_process]['right_question'] += $right_questions;
                }
                else
                {
                    $data[$knowledge_name][0][0] = '本班本次考试';
                    $data[$knowledge_name][0][$know_process] = '-1';
                    $data[$knowledge_name][0][$know_process] = '-1';
                }
            }
        }
        
        while ($item = $query2->fetch(PDO::FETCH_ASSOC))
        {
            $know_process_ques_id = json_decode($item['know_process_ques_id'], true);//该二级知识点关联的 认知过程 试题
            $right_know_process_ques_id = json_decode($item['right_know_process_ques_id'], true);//班级答对情况
            $knowledge_name = $item['knowledge_name'];
             
            $know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
            $right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();
             
            foreach ($know_processes as $know_process => $kp_name)
            {
                $all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
                $right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;
        
                if ($all_questions)
                {
                    $data[$knowledge_name][1][0] = '本班上次考试';
                    $data[$knowledge_name][1][$know_process]['total_question'] += $all_questions;
                    $data[$knowledge_name][1][$know_process]['right_question'] += $right_questions;
                }
                else
                {
                    $data[$knowledge_name][1][0] = '本班上次考试';
                    $data[$knowledge_name][1][$know_process] = '-1';
                    $data[$knowledge_name][1][$know_process] = '-1';
                }
            }
        }
        
        //排序
        $field_sort = array();
        foreach ($data as $k_name => &$item)
        {
            $t_strength = 0;
            $t_num = 0;
            //遍历认知过程，计算强弱点分布情况
            /*
             * X = (答对题数/总题数)*100
             */
            $is_delete = true;
            foreach ($know_processes as $know_process => $kp_name)
            {
                if (is_array($item[0][$know_process])
                    && is_array($item[1][$know_process]))
                {
                    $is_delete = false;
                }
                
                //班级知识点强弱分布
                if (is_array($item[0][$know_process]))
                {
                    $t_num ++;
                    $total = $item[0][$know_process]['total_question'];
                    $right = $item[0][$know_process]['right_question'];
                    $item[0][$know_process] = round($right / $total * 100);
                }
        
                //全校知识点强弱分布
                if (is_array($item[1][$know_process]))
                {
                    $total = $item[1][$know_process]['total_question'];
                    $right = $item[1][$know_process]['right_question'];
                    $item[1][$know_process] = round($right / $total * 100);
                    
                    if ($item[0][$know_process] > -1)
                    {
                        $t_strength += ($item[0][$know_process] - $item[1][$know_process]);
                    }
                }
            }
            
            if ($is_delete)
            {
                unset($data[$k_name]);
            }
            else
            {
                $field_sort[$k_name] = $t_num > 0 ? round($t_strength / $t_num, 1) : 0;
            }
        }
        
        $tmp_arr = array();
        
        arsort($field_sort);
        
        foreach ($field_sort as $key => $val)
        {
            $tmp_arr[$key] = $data[$key];
        }
        
        $data = $tmp_arr;
        
        return array(
            'data' => $data,
            'new_knowledge' => $new_knowledge
        );
    }
	
	/**
	 * 强弱点分布情况
	 * @param number $rule_id 规则id（无实际意义）
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_application_situation($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
		$exam_id = intval($exam_id);
		$schcls_id = intval($schcls_id);
		if (!$exam_id || !$schcls_id)
		{
			return array();
		}
		
		//获取该班级所在区域
		$class = $this->class_common_model->get_class_info($schcls_id);
		//获取班级学生id
		$class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
		if (!$class_uids)
		{
		    return array();
		}
		$cls_uid_str = implode(',', $class_uids);
		
		//获取年级学生id
		$grade_uids = $this->class_common_model->get_grade_student_list($class['school_id'], $exam_id);
		if (!$grade_uids)
		{
		    return array();
		}
		$grd_uid_str = implode(',', $grade_uids);
		
		$data = array();
		
		//获取该年级所考到试题关联的答对 二级知识点 & 认知过程
		$sql = "SELECT ssk.uid, ssk.knowledge_id, k.knowledge_name, spk.know_process_ques_id, 
		        ssk.know_process_ques_id AS right_know_process_ques_id
				FROM rd_summary_student_knowledge ssk
				LEFT JOIN rd_summary_paper_knowledge spk ON ssk.paper_id=spk.paper_id AND ssk.knowledge_id=spk.knowledge_id
				LEFT JOIN rd_knowledge k ON ssk.knowledge_id=k.id
				WHERE ssk.exam_id={$exam_id} AND ssk.uid IN ($grd_uid_str) AND ssk.is_parent=0
				";
		$query = self::$_db->query($sql);
		
		$know_processes = C('know_process');
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
			$know_process_ques_id = json_decode($item['know_process_ques_id'], true);//该二级知识点关联的 认知过程 试题
			$right_know_process_ques_id = json_decode($item['right_know_process_ques_id'], true);//班级答对情况
			$knowledge_name = $item['knowledge_name'];
			
			$know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
			$right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();
			
			foreach ($know_processes as $know_process => $kp_name) 
			{
				$all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
				$right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;
				
				if ($all_questions)
				{
				    if (!isset($data[$knowledge_name][0]['kp_'.$know_process]['total_question']))
				    {
				        $data[$knowledge_name][0]['kp_'.$know_process]['total_question'] = 0;
				    }
				    
				    if (!isset($data[$knowledge_name][0]['kp_'.$know_process]['right_question']))
				    {
				        $data[$knowledge_name][0]['kp_'.$know_process]['right_question'] = 0;
				    }
				    
				    if (!isset($data[$knowledge_name][1]['kp_'.$know_process]['total_question']))
				    {
				        $data[$knowledge_name][1]['kp_'.$know_process]['total_question'] = 0;
				    }
				    
				    if (!isset($data[$knowledge_name][1]['kp_'.$know_process]['right_question']))
				    {
				        $data[$knowledge_name][1]['kp_'.$know_process]['right_question'] = 0;
				    }
				    
				    if (in_array($item['uid'], $class_uids))
				    {
				        $data[$knowledge_name][0]['kp_'.$know_process]['total_question'] += $all_questions;
				        $data[$knowledge_name][0]['kp_'.$know_process]['right_question'] += $right_questions;
				    }
				    
				    $data[$knowledge_name][1]['kp_'.$know_process]['total_question'] += $all_questions;
				    $data[$knowledge_name][1]['kp_'.$know_process]['right_question'] += $right_questions;
				}
				else
				{
				    $data[$knowledge_name][0]['kp_'.$know_process] = '-1';
				    $data[$knowledge_name][1]['kp_'.$know_process] = '-1';
				}
			}
		}
		
		//排序
		$field_sort = array();
		foreach ($data as $k_name => &$item)
		{
		    $t_strength = 0;
			$t_num = 0;
			
			//遍历认知过程，计算强弱点分布情况
			/*
			 * X = (答对题数/总题数)*100
			 */
		    foreach ($know_processes as $know_process => $kp_name)
		    {
		        //班级知识点强弱分布
    		    if (is_array($item[0]['kp_' . $know_process]))
    		    {
    		        $t_num ++;
    		        
    		        $total = $item[0]['kp_' . $know_process]['total_question'];
    		        $right = $item[0]['kp_' . $know_process]['right_question'];
    		        $item[0]['kp_' . $know_process] = round($right / $total * 100);
    		    }
    		    
    		    //全校知识点强弱分布
    		    if (is_array($item[1]['kp_' . $know_process]))
    		    {
    		        $total = $item[1]['kp_' . $know_process]['total_question'];
    		        $right = $item[1]['kp_' . $know_process]['right_question'];
    		        $item[1]['kp_' . $know_process] = round($right / $total * 100);
    		        
    		        $t_strength += ($item[0]['kp_' . $know_process] - $item[1]['kp_' . $know_process]);
    		    }
		    }
		    
		    $field_sort[$k_name] = $t_num > 0 ? round($t_strength / $t_num, 1) : 0;
		}
		
	    $application_situation = array();
	
	    arsort($field_sort);
	
	    foreach ($field_sort as $key => $val)
	    {
	        $application_situation[$key] = $data[$key];
	    }
	
	    $data = $application_situation;
		
		return array('data' => $data);
	}
	
	/**
	 * 新增知识点强弱点分布情况
	 * @param number $rule_id 规则id（无实际意义）
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_new_application_situation($rule_id = 0, $exam_id = 0, $schcls_id = 0, $knowledge_ids = array())
	{
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$exam_id || !$schcls_id || !$knowledge_ids)
	    {
	        return array();
	    }
	    
	    //获取该班级所在区域
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    //获取班级学生id
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	
	    //获取年级学生id
	    $grade_uids = $this->class_common_model->get_grade_student_list($class['school_id'], $exam_id);
	    if (!$grade_uids)
	    {
	        return array();
	    }
	    $grd_uid_str = implode(',', $grade_uids);
	
	    $data = array();
	
	    //获取该年级所考到试题关联的答对 二级知识点 & 认知过程
	    $sql = "SELECT ssk.uid, ssk.knowledge_id, k.knowledge_name, spk.know_process_ques_id,
        	    ssk.know_process_ques_id AS right_know_process_ques_id
        	    FROM rd_summary_student_knowledge ssk
        	    LEFT JOIN rd_summary_paper_knowledge spk ON ssk.paper_id=spk.paper_id AND ssk.knowledge_id=spk.knowledge_id
        	    LEFT JOIN rd_knowledge k ON ssk.knowledge_id=k.id
        	    WHERE ssk.exam_id={$exam_id} AND ssk.uid IN ($grd_uid_str) AND ssk.is_parent=0
        	    AND ssk.knowledge_id IN (" . implode(',', $knowledge_ids) . ")";
	    $query = self::$_db->query($sql);
	
	    $know_processes = C('know_process');
	    while ($item = $query->fetch(PDO::FETCH_ASSOC))
	    {
	        $know_process_ques_id = json_decode($item['know_process_ques_id'], true);//该二级知识点关联的 认知过程 试题
	        $right_know_process_ques_id = json_decode($item['right_know_process_ques_id'], true);//班级答对情况
	        $knowledge_name = $item['knowledge_name'];
	        	
	        $know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
	        $right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();
	        	
	        foreach ($know_processes as $know_process => $kp_name)
	        {
	            $all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
	            $right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;
	
	            if ($all_questions)
	            {
	                if (in_array($item['uid'], $class_uids))
	                {
	                    $data[$knowledge_name][0]['kp_'.$know_process]['total_question'] += $all_questions;
	                    $data[$knowledge_name][0]['kp_'.$know_process]['right_question'] += $right_questions;
	                }
	
	                $data[$knowledge_name][1]['kp_'.$know_process]['total_question'] += $all_questions;
	                $data[$knowledge_name][1]['kp_'.$know_process]['right_question'] += $right_questions;
	            }
	            else
	            {
	                $data[$knowledge_name][0]['kp_'.$know_process] = '-1';
	                $data[$knowledge_name][1]['kp_'.$know_process] = '-1';
	            }
	        }
	    }
	
	    //排序
	    $field_sort = array();
	    foreach ($data as $k_name => &$item)
	    {
	        $t_strength = 0;
	        $t_num = 0;
	        
	        //遍历认知过程，计算强弱点分布情况
	        /*
	         * X = (答对题数/总题数)*100
	         */
	        foreach ($know_processes as $know_process => $kp_name)
	        {
	            //班级知识点强弱分布
	            if (is_array($item[0]['kp_' . $know_process]))
	            {
	                $t_num ++;
	
	                $total = $item[0]['kp_' . $know_process]['total_question'];
	                $right = $item[0]['kp_' . $know_process]['right_question'];
	                $item[0]['kp_' . $know_process] = round($right / $total * 100);
	            }
	
	            //全校知识点强弱分布
	            if (is_array($item[1]['kp_' . $know_process]))
	            {
	                $total = $item[1]['kp_' . $know_process]['total_question'];
	                $right = $item[1]['kp_' . $know_process]['right_question'];
	                $item[1]['kp_' . $know_process] = round($right / $total * 100);
	
	                $t_strength += ($item[0]['kp_' . $know_process] - $item[1]['kp_' . $know_process]);
	            }
	        }
	
	        $field_sort[$k_name] = $t_num > 0 ? round($t_strength / $t_num, 1) : 0;
	    }
	
        $application_situation = array();

        arsort($field_sort);

        foreach ($field_sort as $key => $val)
        {
            $application_situation[$key] = $data[$key];
        }

        $data = $application_situation;
	
	    return array('data' => $data);
	}
	
	/**
	 * 目标匹配度 XX%
	 * note:
	 *   xx%=班级的学科总得分/期望得分（班级考的试卷每题期望得分累加）
	 *
	 * @param Number $rule_id   评估规则id
	 * @param number $exam_id   考试学科
	 * @param number $schcls_id 班级id
	 */
	public function module_match_percent($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    $match_percent = array();
	    
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    $total_score = ExamModel::get_exam_by_id($exam_id, 'total_score');
	    
        $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
        
        if (!isset(self::$_data['paper_question_score'][$exam_id][$paper_id]))
        {
            $paper = PaperModel::get_paper_by_id($paper_id);
            $score = json_decode($paper['question_score'], true);
            
            self::$_data['paper_question_score'][$exam_id][$paper_id] = $score;
        }
        else
        {
            $score = self::$_data['paper_question_score'][$exam_id][$paper_id];
        }
        
        // 获取该班级所考到的试卷题目
        $sql = "SELECT etpq.ques_id
        	    FROM rd_exam_test_paper_question etpq
        	    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
        	    WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
        	    ";
        $ques_id = trim(self::$_db->fetchOne($sql));
        if (!$ques_id)
        {
            return array();
        }
        
        $ques_ids = @explode(',', $ques_id);
        if (!is_array($ques_ids) || !$ques_ids)
        {
            return array();
        }
	    
	    // 获取这些题目的难易度
	    $sql = "SELECT rc.ques_id,rc.difficulty
        	    FROM rd_relate_class rc
        	    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
        	    AND rc.subject_type=e.subject_type
        	    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
        	    ";
	    $ques_difficulties = self::$_db->fetchPairs($sql);
	    
	    //本次考试班级试题得分情况
	    $sql = "SELECT ques_id, ROUND(total_score / student_amount) AS full_score, avg_score
        	    FROM rd_summary_region_question
        	    WHERE exam_id = $exam_id AND region_id = $schcls_id
        	    AND is_school = 0 AND is_class = 1 AND ques_id IN ($ques_id)";
	    $class_score = self::$_db->fetchAssoc($sql);
	    
	    //本次考试年级试题得分情况
	    $sql = "SELECT ques_id, ROUND(total_score / student_amount) AS full_score, avg_score
        	    FROM rd_summary_region_question
        	    WHERE exam_id = $exam_id AND region_id = {$class['school_id']}
        	    AND is_school = 1 AND is_class = 0 AND ques_id IN ($ques_id)";
	    $grade_score = self::$_db->fetchAssoc($sql);
	    
	    $data = array(
	        array('难易度', '低', '中', '高', '合计'),
	        array('本班平均分', -1, -1, -1, 0),
	        array('年级平均分', -1, -1, -1, 0),
	        array('期望得分', -1, -1, -1, 0),
	        array('总分', -1, -1, -1, 0),
	    );
	    
	    $level = array('低' => 1, '中' => 2, '高' => 3);
	    
	    foreach ($ques_ids as $ques_id)
	    {
	        if (!isset($ques_difficulties[$ques_id]))
	        {
	            continue;
	        }
	        
	        $q_diffculty = $ques_difficulties[$ques_id];
	        
	        $cls_full_score = 0;
	        $cls_test_score = 0;
	        $grd_test_score = 0;
	        if (isset($class_score[$ques_id]))
	        {
	            $cls_full_score = isset($score[$ques_id]) ? array_sum($score[$ques_id]) : $class_score[$ques_id]['full_score'];
	            $cls_test_score = $class_score[$ques_id]['avg_score'];
	        }
	        
	        if (isset($grade_score[$ques_id]))
	        {
	            $grd_test_score = $grade_score[$ques_id]['avg_score'];
	        }
	    
	        $d_level = $this->class_common_model->convert_question_difficulty($q_diffculty);
	    
	        $expect_score = $cls_full_score * $q_diffculty / 100;
	        
	        $k = $level[$d_level];
	        
            if ($data[1][$k] == -1)
            {
                $data[1][$k] = 0;
            }
            $data[1][$k] += $cls_test_score;
            $data[1][4] += $cls_test_score;
            
            if ($data[2][$k] == -1)
            {
                $data[2][$k] = 0;
            }
            $data[2][$k] += $grd_test_score;
            $data[2][4] += $grd_test_score;
            
            if ($data[3][$k] == -1)
            {
                $data[3][$k] = 0;
            }
            $data[3][$k] += $expect_score;
            $data[3][4] += $expect_score;
            
            if ($data[4][$k] == -1)
            {
                $data[4][$k] = 0;
            }
            $data[4][$k] += $cls_full_score;
	    }
	    $data[4][4] = $total_score;
	    
	    return array(
	        'data' => $data,
	        'percent' => round(end($data[1]) / end($data[3]) * 100)
	    );
	}
	
	/**
	 * 各难易度得分率对比
	 * @param number $rule_id 规则id
	 * @param number $exam_id 考试学科
	 * @param number $schcls_id 班级ID
	 */
	public function module_contrast_difficulty($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
	    if (!$paper_id)
	    {
	        return array();
	    }
	    
	    //上次考试班级的试卷id
	    $contrast_paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $contrast_exam_id);
	    if (!$contrast_paper_id)
	    {
	        return array();
	    }
	    
	    //获取该班级所在区域
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	    
	    $grade_uids = $this->class_common_model->get_grade_student_list($class['school_id'], $exam_id);
	    if (!$grade_uids)
	    {
	        return array();
	    }
	    $grd_uid_str = implode(',', $grade_uids);
	    
	    //班级当前考试
	    $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score, 
	            ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score, 
	            ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
        	    FROM rd_summary_student_difficulty
        	    WHERE exam_id = $exam_id AND uid IN ($cls_uid_str)
	            GROUP BY q_type";
	    $curr_qtype_testscore = self::$_db->fetchAssoc($sql);
	    if (!$curr_qtype_testscore)
	    {
	        return array();
	    }
	    
	    //对比考试
	    $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score,
        	    ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score,
        	    ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
        	    FROM rd_summary_student_difficulty
        	    WHERE exam_id = $contrast_exam_id AND uid IN ($cls_uid_str)
        	    GROUP BY q_type";
	    $contrast_qtype_testscore = self::$_db->fetchAssoc($sql);
	    if (!$contrast_qtype_testscore)
	    {
	        return array();
	    }
	    
	    if ($grade_uids == $class_uids)
        {
            $curr_qtype_testscore2 = $curr_qtype_testscore;
            $contrast_qtype_testscore2 = $contrast_qtype_testscore;
        }
        else 
        {
            //年级当前考试
            $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score,
                    ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score,
                    ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
                    FROM rd_summary_student_difficulty
                    WHERE exam_id = $exam_id AND uid IN ($grd_uid_str)
                    GROUP BY q_type";
            $curr_qtype_testscore2 = self::$_db->fetchAssoc($sql);
            if (!$curr_qtype_testscore2)
            {
                return array();
            }
            
            //对比考试
            $sql = "SELECT q_type, ROUND(SUM(low_test_score) / COUNT(uid), 2) AS low_test_score,
                    ROUND(SUM(mid_test_score) / COUNT(uid), 2) AS mid_test_score,
                    ROUND(SUM(high_test_score) / COUNT(uid), 2) AS high_test_score
                    FROM rd_summary_student_difficulty
                    WHERE exam_id = $contrast_exam_id AND uid IN ($grd_uid_str)
                    GROUP BY q_type";
            $contrast_qtype_testscore2 = self::$_db->fetchAssoc($sql);
            if (!$contrast_qtype_testscore2)
            {
                return array();
            }
        }
	    
		//数据
		$data = array();
		$data[] = array('得分率', '低难度(%)', '中难度(%)', '高难度(%)');
		
		//本班本次考试
		$data[1] = array('本班本次考试', -1, -1, -1);
		$this->_cal_difficulty_percent($data[1], $exam_id, $paper_id, $curr_qtype_testscore);
		
		//本班上次考试
		$data[2] = array('本班上次考试', -1, -1, -1);
		$this->_cal_difficulty_percent($data[2], $contrast_exam_id, $contrast_paper_id, $contrast_qtype_testscore);
		
		//年级本次考试
		$data[3] = array('年级本次考试', -1, -1, -1);
		$this->_cal_difficulty_percent($data[3], $exam_id, $paper_id, $curr_qtype_testscore2);
		
		//年级上次考试
		$data[4] = array('年级上次考试', -1, -1, -1);
		$this->_cal_difficulty_percent($data[4], $contrast_exam_id, $contrast_paper_id, $contrast_qtype_testscore2);
		
		return array('data' => $data);
	}
	
	private function _cal_difficulty_percent(&$row, $exam_id, $paper_id, $qtype_testscore)
	{
	    //获取 本学科 题型难易度 信息
	    if (empty(self::$_data['difficulty'][$exam_id][$paper_id]))
	    {
	        $sql = "SELECT DISTINCT(q_type) AS q_type, low_ques_id,
        	        mid_ques_id, high_ques_id
        	        FROM rd_summary_paper_difficulty
        	        WHERE paper_id = $paper_id
        	        ";
	        self::$_data['difficulty'][$exam_id][$paper_id] = self::$_db->fetchAssoc($sql);
	    }
	    
	    $paper = self::$_data['difficulty'][$exam_id][$paper_id];
	    $level = array('low' => 1, 'mid' => 2, 'high' => 3);
	    
	    foreach ($paper as $qtype => $item)
	    {
	        //当期考试 题型难易度关联试题
	        $low_ques_id = $item['low_ques_id'];
	        $mid_ques_id = $item['mid_ques_id'];
	        $high_ques_id = $item['high_ques_id'];
	         
	        $tmp_arr = array('low' => $low_ques_id, 'mid' => $mid_ques_id, 'high' => $high_ques_id);
	        foreach ($tmp_arr as $key => $ques_id)
	        {
	            if ($ques_id)
	            {
	                //获取该题型难易度总分
	                $total_score = 0;
	    
	                if (empty(self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key]))
	                {
	                    $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
            			        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
        		                GROUP BY etp_id
                	            ";
	    
	                    $total_score = self::$_db->fetchOne($sql);
	                    self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key] = $total_score;
	                }
	                else
	                {
	                    $total_score = self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key];
	                }
	    
	                //获取该题型难易度 得分
	                $test_score = $qtype_testscore[$qtype][$key . '_test_score'];
	    
	                if ($row[$level[$key]] == -1)
	                {
	                    $row[$level[$key]] = array('total_score' => 0, 'test_score' => 0);
	                }
	    
	                $row[$level[$key]]['total_score'] += $total_score;
	                $row[$level[$key]]['test_score'] += $test_score;
	            }
	        }
	    }
	    
	    foreach ($row as &$item)
	    {
	        if (is_array($item))
	        {
	            $item = $item['total_score'] ? round($item['test_score'] / $item['total_score'] * 100) : 0;
	        }
	    }
	}
	
	/**
	 * 平均优胜率
	 * @param array $data 各难易度得分率对比信息数据
	 */
	public function module_contrast_odds($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    $class_uids = $this->class_common_model->get_class_student_list($schcls_id, $exam_id);
	    if (!$class_uids)
	    {
	        return array();
	    }
	    $cls_uid_str = implode(',', $class_uids);
	    
	    if (!isset(self::$_data['exam_students'][$exam_id]))
	    {
	        $sql = "SELECT COUNT(*) AS total
        	        FROM rd_summary_region_student_rank
        	        WHERE exam_id={$exam_id} AND region_id = 1
        	        AND is_school = 0 AND is_class = 0
        	        ";
	        $total = self::$_db->fetchOne($sql);
	        self::$_data['exam_students'][$exam_id] = $total;
	    }
	    else
	    {
	        $total = self::$_data['exam_students'][$exam_id];
	    }
	    
	    if (!$total)
	    {
	        return array();
	    }
	    
	    if (!isset(self::$_data['exam_students'][$contrast_exam_id]))
	    {
	        $sql = "SELECT COUNT(*) AS total
        	        FROM rd_summary_region_student_rank
        	        WHERE exam_id={$contrast_exam_id} AND region_id = 1
        	        AND is_school = 0 AND is_class = 0
        	        ";
	        $total2 = self::$_db->fetchOne($sql);
	        self::$_data['exam_students'][$contrast_exam_id] = $total2;
	    }
	    else
	    {
	        $total2 = self::$_data['exam_students'][$contrast_exam_id];
	    }

	    if (!$total2)
	    {
	        return array();
	    }
	    
	    $sql = "SELECT SUM(rank) / COUNT(uid) AS avg_rank 
	           FROM rd_summary_region_student_rank 
	           WHERE exam_id = $exam_id AND region_id = 1
	           AND uid IN ($cls_uid_str)";
	    $avg_rank = self::$_db->fetchOne($sql);
	    $win_percent = round(($total - $avg_rank + 1) / $total * 100);
	    
	    $sql = "SELECT SUM(rank) / COUNT(uid) AS avg_rank
        	    FROM rd_summary_region_student_rank
        	    WHERE exam_id = $contrast_exam_id AND region_id = 1
        	    AND uid IN ($cls_uid_str)";
	    $avg_rank2 = self::$_db->fetchOne($sql);
	    $win_percent2 = round(($total2 - $avg_rank2 + 1) / $total2 * 100);
	    
	    return array('odds' => round($win_percent - $win_percent2));
	}
	
	/**
	 * 学生成绩分层对比
	 * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $schcls_id 班级ID 
	 */
	public function module_contrast_hierarchy($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    //对比考试id
	    $contrast_exam_id = $this->class_common_model->contrast_exam_id($rule_id, $exam_id);
	    if (!$contrast_exam_id)
	    {
	        return array();
	    }
	    
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    
	    $subject_id = $this->class_common_model->get_exam_item($exam_id);
	    $subject_name = C('subject/' . $subject_id);
	    
	    $exam_ids = array($exam_id, $contrast_exam_id);
	    
	    $all_data = array();
	    
	    //分段比例
	    $proportion = $this->class_common_model->get_rule_distribution_proportion($rule_id);
	    $names = array_keys($proportion);
	    
	    foreach ($exam_ids as $exam_id)
	    {
	        if (!isset(self::$_data['school_rank'][$exam_id][$class['school_id']]))
	        {
	            $sql = "SELECT uid, rank, test_score
        	            FROM rd_summary_region_student_rank
        	            WHERE exam_id = $exam_id AND region_id = ?
        	            AND is_school = 1 AND is_class = 0
	                    ORDER BY rank ASC";
	            $grade_rank = self::$_db->fetchAssoc($sql, array($class['school_id']));
	            self::$_data['school_rank'][$exam_id][$class['school_id']] = $grade_rank;
	        }
	        else
	        {
	            $grade_rank = self::$_data['school_rank'][$exam_id][$class['school_id']];
	        }
	        
	        if (!$grade_rank)
	        {
	            return array();
	        }
	        
	        if (!isset(self::$_data['class_uid'][$exam_id][$class['schcls_id']]))
	        {
	            $sql = "SELECT uid FROM rd_summary_region_student_rank
        	            WHERE exam_id = $exam_id AND region_id = ?
        	            AND is_school = 0 AND is_class = 1
	                    ORDER BY rank ASC";
	            $class_uid = self::$_db->fetchCol($sql, array($class['schcls_id']));
	            self::$_data['class_uid'][$exam_id][$class['schcls_id']] = $class_uid;
	        }
	        else
	        {
	            $class_uid = self::$_data['class_uid'][$exam_id][$class['schcls_id']];
	        }
	        
	        if (!$class_uid)
	        {
	            return array();
	        }
	        
    	    $grd_students = count($grade_rank);
    	    
    	    //分段临界排名
            $ranks = array();
            foreach ($proportion as $name => $rate)
            {
                $ranks[] = $grd_students * $rate / 100;
            }
    	    
    	    $data = array();
    	    
    	    $cls_students = count($class_uid);
            foreach ($class_uid as $uid)
            {
                $prev_rank = 0;
                foreach ($ranks as $k => $rank)
                {
                    if ($prev_rank < $grade_rank[$uid]['rank'] 
                        && $grade_rank[$uid]['rank'] <= $rank)
                    {
                        $data['cls_num_' . $k]++;
                        break;
                    }
                }
            }
            
            $exam_name = $this->class_common_model->get_exam_item($exam_id, 'exam_name');
            
            $k = $exam_name . " " . $subject_name;
            
            $length = count($ranks);
            for ($i = 0; $i < $length; $i++)
            {
                $all_data[$k][$names[$i]] = round($data['cls_num_' . $i] / $cls_students * 100);
            }
	    }
	    
	    return array(
	        'flash_data' => $all_data,
	        'proportion' => $proportion
	    );
	}
	
	/**
	 * 各分数段排名系数表
	 * @param int $rule_id 评估规则ID
	 * @param int $exam_id 考试学科
	 * @param int $schcls_id 班级ID
	 */
	public function module_rank_factor($rule_id = 0, $exam_id = 0, $schcls_id = 0)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $schcls_id = intval($schcls_id);
	    if (!$rule_id || !$exam_id || !$schcls_id)
	    {
	        return array();
	    }
	    
	    $class = $this->class_common_model->get_class_info($schcls_id);
	    
	    if (!isset(self::$_data['school_rank'][$exam_id][$class['school_id']]))
	    {
	        $sql = "SELECT uid, rank, test_score
        	        FROM rd_summary_region_student_rank
        	        WHERE exam_id = $exam_id AND region_id = ?
        	        AND is_school = 1 AND is_class = 0
	                ORDER BY rank ASC";
	        $grade_rank = self::$_db->fetchAssoc($sql, array($class['school_id']));
	        self::$_data['school_rank'][$exam_id][$class['school_id']] = $grade_rank;
	    }
	    else
	    {
	        $grade_rank = self::$_data['school_rank'][$exam_id][$class['school_id']];
	    }
	    
	    if (!$grade_rank)
	    {
	        return array();
	    }
	    
	    $student_num = count($grade_rank);
	    
	    if (!isset(self::$_data['class_uid'][$exam_id][$class['schcls_id']]))
	    {
	        $sql = "SELECT uid FROM rd_summary_region_student_rank
        	        WHERE exam_id = $exam_id AND region_id = ?
        	        AND is_school = 0 AND is_class = 1";
	        $class_uid = self::$_db->fetchCol($sql, array($class['schcls_id']));
	        self::$_data['class_uid'][$exam_id][$class['schcls_id']] = $class_uid;
	    }
	    else
	    {
	        $class_uid = self::$_data['class_uid'][$exam_id][$class['schcls_id']];
	    }
	    
	    if (!$class_uid)
	    {
	        return array();
	    }
	    
	    //分段比例
	    $proportion = $this->class_common_model->get_rule_distribution_proportion($rule_id);
	    //分段临界排名
        $ranks = array();
        foreach ($proportion as $name => $rate)
        {
            $ranks[] = $student_num * $rate / 100;
        }
        
	    $data = array();
	    $data[0] = array_values(array_keys($proportion));
	    $data[0][] = '总排名';
	    $max_k = count($data[0]) - 1;
	    
	    $grade_arr = array();
	    $class_arr = array();
	    
	    $grade_arr[$max_k] = array(
	        'rank' => 0,
	        'num'  => 0,
	    );
	    $class_arr[$max_k] = array(
	        'rank' => 0,
	        'num'  => 0,
	    );
	
        foreach ($grade_rank as $uid => $item)
        {
            $grade_arr[$max_k]['rank'] += $item['rank'];
            $grade_arr[$max_k]['num']++;
            
            $prev_rank = 0;
            foreach ($ranks as $k => $rank)
            {
                if ($prev_rank < $item['rank']
                    && $item['rank'] <= $rank)
                {
                    if (!isset($grade_arr[$k]))
                    {
                        $grade_arr[$k] = array(
                            'rank' => 0,
                            'num'  => 0,
                        );
                    }
                    $grade_arr[$k]['rank'] += $item['rank'];
                    $grade_arr[$k]['num']++;
                    
                    if (in_array($uid, $class_uid))
                    {
                        $class_arr[$max_k]['rank'] += $item['rank'];
                        $class_arr[$max_k]['num']++;
                        
                        if (!isset($class_arr[$k]))
                        {
                            $class_arr[$k] = array(
                                'rank' => 0,
                                'num'  => 0,
                            );
                        }
                        $class_arr[$k]['rank'] += $item['rank'];
                        $class_arr[$k]['num']++;
                    }
                    
                    break;
                }
                
                $prev_rank = $rank;
            }
        }
        
        ksort($grade_arr);
        ksort($class_arr);
        
        $class_data = array();
        $grade_data = array();
        for ($i = 0; $i <= $max_k; $i++)
        {
            $class_data[] = $class_arr[$i]['num'] ? round($class_arr[$i]['rank']/$class_arr[$i]['num']) : '';
            $grade_data[] = $grade_arr[$i]['num'] ? round($grade_arr[$i]['rank']/$grade_arr[$i]['num']) : '';
        }
        
        $data[] = $class_data;
        $data[] = $grade_data;
	    
        return array(
            'data' => $data,
            'proportion' => $proportion,
        );
	}
}