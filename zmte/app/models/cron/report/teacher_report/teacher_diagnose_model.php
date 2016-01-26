<?php if ( ! defined('BASEPATH')) exit();
/**
 * 测评报告-教师-诊断
 * @author  TCG
 * @final   2015-11-17
 */
class Teacher_diagnose_model extends CI_Model 
{
    private static $_db;
    private static $_data;
    
    public function __construct()
    {
        parent::__construct();
        
        self::$_db = Fn::db();
        
        $this->load->model('cron/report/teacher_report/teacher_common_model');
    }
    
    /**
     * 各分数段人数比例分布情况
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_score_proportion($rule_id = 0, $exam_id = 0, $teacher_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id)
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
    
        $teacher = $this->teacher_common_model->get_teacher_info($teacher_id);
        
        if (!$teacher)
        {
            return array();
        }
        
        //教师所授课的学生
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
        
        if (!isset(self::$_data['school_rank'][$exam_id][$teacher['school_id']]))
        {
            $sql = "SELECT uid, rank, test_score
                    FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = ?
                    AND is_school = 1 AND is_class = 0
                    ORDER BY rank ASC";
            $grade_rank = self::$_db->fetchAssoc($sql, array($teacher['school_id']));
            self::$_data['school_rank'][$exam_id][$teacher['school_id']] = $grade_rank;
        }
        else
        {
            $grade_rank = self::$_data['school_rank'][$exam_id][$teacher['school_id']];
        }
    
        if (!$grade_rank)
        {
            return array();
        }
    
        $student_num = count($grade_rank);
        
        //分段比例
        $proportion = $this->teacher_common_model->get_rule_distribution_proportion($rule_id);
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
        $data['cls_students'] = count($stu_ids);
        
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
        
                        if (in_array($uid, $stu_ids))
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
                $f_data[0]['name'] = '任教学生';
                $f_data[0]['data'][] = 0;
            }
            else
            {
                $f_data[0]['name'] = '任教学生';
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
     * 各分数段排名系数表
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_rank_factor($rule_id = 0, $exam_id = 0, $teacher_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id)
        {
            return array();
        }
         
        $teacher = $this->teacher_common_model->get_teacher_info($teacher_id);
        if (!$teacher)
        {
            return array();
        }
        
        //教师所授课的学生
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
         
        if (!isset(self::$_data['school_rank'][$exam_id][$teacher['school_id']]))
        {
            $sql = "SELECT uid, rank, test_score
                    FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = ?
                    AND is_school = 1 AND is_class = 0
                    ORDER BY rank ASC";
            $grade_rank = self::$_db->fetchAssoc($sql, array($teacher['school_id']));
            self::$_data['school_rank'][$exam_id][$teacher['school_id']] = $grade_rank;
        }
        else
        {
            $grade_rank = self::$_data['school_rank'][$exam_id][$teacher['school_id']];
        }
         
        if (!$grade_rank)
        {
            return array();
        }
         
        $student_num = count($grade_rank);
         
        if (!isset(self::$_data['teacher_class_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid, region_id FROM rd_summary_region_student_rank srsr
                    LEFT JOIN rd_exam_place_student eps ON eps.uid = srsr.uid
                    LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id 
                        AND srsr.region_id = ep.place_schclsid
                    LEFT JOIN t_school_class ON schcls_id = region_id
                    WHERE exam_id = $exam_id AND srsr.uid IN (".implode(',', $stu_ids) . ")
                    AND is_school = 0 AND is_class = 1
                    ORDER BY schcls_name ASC";
            $cls_uid = self::$_db->fetchPairs($sql);
            self::$_data['teacher_class_uid'][$exam_id][$teacher_id] = $cls_uid;
        }
        else
        {
            $cls_uid = self::$_data['teacher_class_uid'][$exam_id][$teacher_id];
        }
        
        $cls_array = array();
        $cls_name = array();
        if ($cls_uid)
        {
            $cls_ids = array();
            foreach ($cls_uid as $uid => $cls_id)
            {
                $cls_array[$cls_id][] = $uid;
                $cls_ids[$cls_id] = $cls_id;
            }
            
            $sql = "SELECT schcls_id,schcls_name FROM t_school_class
                    WHERE schcls_id IN (".implode(',', $cls_ids).")";
            $cls_name = self::$_db->fetchPairs($sql);
        }
        else
        {
            $cls_array[0] = $stu_ids;
            $cls_name[0] = '任教学生';
        }
        
        //分段比例
        $proportion = $this->teacher_common_model->get_rule_distribution_proportion($rule_id);
        //分段临界排名
        $ranks = array();
        //分段临界分数
        $scores = array();
        foreach ($proportion as $name => $rate)
        {
            $ranks[] = $student_num * $rate / 100;
        }
         
        $all_data = array();
        $school_data = array();
        foreach ($cls_array as $cls_id => $uids)
        {
            $data = array();
            $data['排名系数'] = array_values(array_keys($proportion));
            $data['排名系数'][] = '总排名';
            $max_k = count($data['排名系数']) - 1;
             
            if (!$school_data)
            {
                $grade_arr = array();
            }
            
            $teacher_arr = array();
        
            foreach ($grade_rank as $uid => $item)
            {
                if (!$school_data)
                {
                    $grade_arr[$max_k]['rank'] += $item['rank'];
                    $grade_arr[$max_k]['num']++;
                }
                
                $prev_rank = 0;
                foreach ($ranks as $k => $rank)
                {
                    if ($prev_rank < $item['rank']
                        && $item['rank'] <= $rank)
                    {
                        if (!$school_data)
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
                        }
                
                        if (in_array($uid, $uids))
                        {
                            $teacher_arr[$max_k]['rank'] += $item['rank'];
                            $teacher_arr[$max_k]['num']++;
                
                            if (!isset($teacher_arr[$k]))
                            {
                                $teacher_arr[$k] = array(
                                    'rank' => 0,
                                    'num'  => 0,
                                );
                            }
                            $teacher_arr[$k]['rank'] += $item['rank'];
                            $teacher_arr[$k]['num']++;
                        }
                
                        break;
                    }
                
                    $prev_rank = $rank;
                }
            }
            
            ksort($grade_arr);
            ksort($teacher_arr);
            
            $grade_data = array();
            $teacher_data = array();
            for ($i = 0; $i <= $max_k; $i++)
            {
                $teacher_data[] = $teacher_arr[$i]['num'] ? round($teacher_arr[$i]['rank']/$teacher_arr[$i]['num']) : '';
                
                if (!$school_data)
                {
                    $grade_data[] = $grade_arr[$i]['num'] ? round($grade_arr[$i]['rank']/$grade_arr[$i]['num']) : '';
                }
            }
            
            if (!$school_data)
            {
                $school_data = $grade_data;
            }
            
            $data[$cls_name[$cls_id]] = $teacher_data;
            $data['全校'] = $school_data;
            
            $all_data[$cls_id] = $data;
        }
        
        return array(
            'data' => $all_data,
            'proportion' => $proportion,
        );
    }
    
    /**
     * 目标匹配度 XX%
     * note:
     *   xx%=教师的学科总得分/期望得分（教师考的试卷每题期望得分累加）
     *
     * @param Number $rule_id   评估规则id
     * @param number $exam_id   考试学科
     * @param number $teacher_id       教师id
     */
    public function module_match_percent($rule_id = 0, $exam_id = 0, $teacher_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id)
        {
            return array();
        }
         
        $match_percent = array();
         
        $teacher = $this->teacher_common_model->get_teacher_info($teacher_id);
        if (!$teacher)
        {
            return array();
        }
        
        //教师所授课的学生
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
         
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
    
        // 获取该教师所考到的试卷题目
        $sql = "SELECT etpq.ques_id
                FROM rd_exam_test_paper_question etpq
                LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
                WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
                ";
        $ques_id = self::$_db->fetchOne($sql);
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
        $ques_difficulties = self::$_data['question_difficulty'][$paper_id];
        if (!$ques_difficulties)
        {
            $sql = "SELECT rc.ques_id,rc.difficulty
                    FROM rd_relate_class rc
                    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                    AND rc.subject_type=e.subject_type
                    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
                    ";
            $ques_difficulties = self::$_db->fetchPairs($sql);
            self::$_data['question_difficulty'][$paper_id] = $ques_difficulties;
        }
         
        //本次考试教师任课学生试题得分情况
        $sql = "SELECT ques_id, SUM(full_score) AS full_score, 
                (SUM(test_score) / COUNT(*)) AS avg_score
                FROM rd_exam_test_result
                WHERE exam_id = $exam_id AND paper_id = $paper_id 
                AND uid IN (" . implode(',', $stu_ids) . ")
                AND ques_id IN ($ques_id)
                GROUP BY ques_id";
        $stu_score = self::$_db->fetchAssoc($sql);
         
        //本次考试年级试题得分情况
        $grade_score = self::$_data['grade_score'][$exam_id][$teacher['school_id']];
        if (!$grade_score)
        {
            $sql = "SELECT ques_id, ROUND(total_score / student_amount) AS full_score, avg_score
                    FROM rd_summary_region_question
                    WHERE exam_id = $exam_id AND region_id = {$teacher['school_id']}
                    AND is_school = 1 AND is_class = 0 AND ques_id IN ($ques_id)";
            $grade_score = self::$_db->fetchAssoc($sql);
            self::$_data['grade_score'][$exam_id][$teacher['school_id']] = $grade_score;
        }
        
        $data = self::$_data['old_match_percent'][$exam_id][$teacher['school_id']];
        $is_old = false;
        if (!$data)
        {
            $data = array(
                array('难易度', '低', '中', '高', '合计'),
                array('任教学生平均分', -1, -1, -1, 0),
                array('年级平均分', -1, -1, -1, 0),
                array('期望得分', -1, -1, -1, 0),
                array('总分', -1, -1, -1, 0),
            );
        }
        else
        {
            //是否存在上一次数据
            $is_old = true;
            $data[1] = array('任教学生平均分', -1, -1, -1, 0);
        }
        
        $stu_nums = COUNT($stu_ids);
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
            if (isset($stu_score[$ques_id]))
            {
                $cls_full_score = $stu_score[$ques_id]['full_score'] / $stu_nums;
                $cls_test_score = $stu_score[$ques_id]['avg_score'];
            }
            
            if (isset($grade_score[$ques_id]))
            {
                $grd_test_score = $grade_score[$ques_id]['avg_score'];
            }
            
            $d_level = $this->teacher_common_model->convert_question_difficulty($q_diffculty);
             
            $k = $level[$d_level];
             
            if ($data[1][$k] == -1)
            {
                $data[1][$k] = 0;
            }
            $data[1][$k] += $cls_test_score;
            $data[1][4] += $cls_test_score;
            
            //有旧数据则跳过后续计算
            if ($is_old)
            {
                continue;
            }
            
            $expect_score = round($cls_full_score * $q_diffculty / 100, 2);
    
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
            $data[4][4] += $cls_full_score;
        }
        
        if (!$is_old)
        {
            self::$_data['old_match_percent'][$exam_id][$teacher['school_id']] = $data;
        }
    
        return array(
            'data' => $data,
            'percent' => round(end($data[1]) / end($data[3]) * 100)
        );
    }

    
    /**
     * 学生成绩分层对比
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_hierarchy($rule_id = 0, $exam_id = 0, $teacher_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id)
        {
            return array();
        }
         
        //对比考试id
        $contrast_exam_id = $this->teacher_common_model->contrast_exam_id($rule_id, $exam_id);
        if (!$contrast_exam_id)
        {
            return array();
        }
         
        $teacher = $this->teacher_common_model->get_teacher_info($teacher_id);

        $subject_id = $this->teacher_common_model->get_exam_item($exam_id);
        $subject_name = C('subject/' . $subject_id);
         
        $exam_ids = array($exam_id, $contrast_exam_id);
         
        $all_data = array();
        
        //分段比例
        $proportion = $this->teacher_common_model->get_rule_distribution_proportion($rule_id);
        $names = array_keys($proportion);
         
        foreach ($exam_ids as $exam_id)
        {
            //教师所授课的学生
            $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
            if (!$stu_ids)
            {
                return array();
            }
             
            if (!isset(self::$_data['school_rank'][$exam_id][$teacher['school_id']]))
            {
                $sql = "SELECT uid, rank, test_score
                        FROM rd_summary_region_student_rank
                        WHERE exam_id = $exam_id AND region_id = ?
                        AND is_school = 1 AND is_class = 0
                        ORDER BY rank ASC";
                $grade_rank = self::$_db->fetchAssoc($sql, array($teacher['school_id']));
                self::$_data['school_rank'][$exam_id][$teacher['school_id']] = $grade_rank;
            }
            else
            {
                $grade_rank = self::$_data['school_rank'][$exam_id][$teacher['school_id']];
            }
            
            if (!$grade_rank)
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
    	    
    	    $cls_students = count($stu_ids);
            foreach ($stu_ids as $uid)
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
            
            $exam_name = $this->teacher_common_model->get_exam_item($exam_id, 'exam_name');
            
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
     * 各分数段排名系数对比表
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_rank_factor($rule_id = 0, $exam_id = 0, $teacher_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id)
        {
            return array();
        }
        
        $contrast_exam_id = $this->teacher_common_model->contrast_exam_id($rule_id, $exam_id);
        if (!$contrast_exam_id)
        {
            return array();
        }
         
        $teacher = $this->teacher_common_model->get_teacher_info($teacher_id);
        if (!$teacher)
        {
            return array();
        }
        
        $exams = array($exam_id, $contrast_exam_id);
    
        $all_data = array();
        
        //分段比例
        $proportion = $this->teacher_common_model->get_rule_distribution_proportion($rule_id);
        $names = array_keys($proportion);
        
        $fields = array('排名系数');
        $fields = array_merge($fields, $names);
        $fields[] = '总排名';
        $max_k = count($fields) - 2;
        
        foreach ($exams as $exam_id)
        {
            //教师所授课的学生
            $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
            if (!$stu_ids)
            {
                return array();
            }
             
            if (!isset(self::$_data['school_rank'][$exam_id][$teacher['school_id']]))
            {
                $sql = "SELECT uid, rank, test_score
                        FROM rd_summary_region_student_rank
                        WHERE exam_id = $exam_id AND region_id = ?
                        AND is_school = 1 AND is_class = 0
                        ORDER BY rank ASC";
                $grade_rank = self::$_db->fetchAssoc($sql, array($teacher['school_id']));
                self::$_data['school_rank'][$exam_id][$teacher['school_id']] = $grade_rank;
            }
            else
            {
                $grade_rank = self::$_data['school_rank'][$exam_id][$teacher['school_id']];
            }
             
            if (!$grade_rank)
            {
                return array();
            }
             
            $student_num = count($grade_rank);
             
            $data = array();
            
            //分段临界排名
            $ranks = array();
            foreach ($proportion as $name => $rate)
            {
                $ranks[] = $student_num * $rate / 100;
            }
            
            $grade_arr = array();
            $teacher_arr = array();
             
            $grade_arr[$max_k] = array(
                'rank' => 0,
                'num'  => 0,
            );
            $teacher_arr[$max_k] = array(
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
            
                        if (in_array($uid, $stu_ids))
                        {
                            $teacher_arr[$max_k]['rank'] += $item['rank'];
                            $teacher_arr[$max_k]['num']++;
            
                            if (!isset($teacher_arr[$k]))
                            {
                                $teacher_arr[$k] = array(
                                    'rank' => 0,
                                    'num'  => 0,
                                );
                            }
                            $teacher_arr[$k]['rank'] += $item['rank'];
                            $teacher_arr[$k]['num']++;
                        }
            
                        break;
                    }
            
                    $prev_rank = $rank;
                }
            }
            
            ksort($grade_arr);
            ksort($teacher_arr);
            
            $teacher_data = array();
            $grade_data = array();
            for ($i = 0; $i <= $max_k; $i++)
            {
                $teacher_data[] = $teacher_arr[$i]['num'] ? round($teacher_arr[$i]['rank']/$teacher_arr[$i]['num']) : '';
                $grade_data[] = $grade_arr[$i]['num'] ? round($grade_arr[$i]['rank']/$grade_arr[$i]['num']) : '';
            }
            
            $data['所有任教学生'] = $teacher_data;
            $data['全校'] = $grade_data;
            $all_data[] = $data;
        }
         
        return array(
            'data' => $all_data,
            'fields' => $fields,
            'proportion' => $proportion
        );
    }
}