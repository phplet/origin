<?php if ( ! defined('BASEPATH')) exit();
/**
 * 测评报告-教师-诊断
 * @author  TCG
 * @final   2015-11-17
 */
class Teacher_suggest_model extends CI_Model 
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
     * 夯实知识点
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_knowledge($rule_id = 0, $exam_id = 0, $teacher_id = 0, $knowledge_ids = array())
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
        
        $stuid_str = implode(',', $stu_ids);
        
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
        
        // 获取该教师所考到的试卷题目
        if (!isset(self::$_data['paper_question'][$paper_id]))
        {
            $sql = "SELECT etpq.ques_id
                    FROM rd_exam_test_paper_question etpq
                    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
                    WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
                    ";
            $ques_id = trim(self::$_db->fetchOne($sql));
            self::$_data['paper_question'][$paper_id] = $ques_id;
        }
        else 
        {
            $ques_id = self::$_data['paper_question'][$paper_id];
        }
        $ques_ids = @explode(',', trim($ques_id));
        if (!$ques_id)
        {
            return array();
        }
         
        // 获取这些题目的难易度
        if (!isset(self::$_data['ques_difficulties'][$paper_id]))
        {
            $sql = "SELECT rc.ques_id,rc.difficulty
                    FROM rd_relate_class rc
                    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                        AND rc.subject_type=e.subject_type
                    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
                    ";
            $ques_difficulties = self::$_db->fetchPairs($sql);
            self::$_data['ques_difficulties'][$paper_id] = $ques_difficulties;
        }
        else
        {
            $ques_difficulties = self::$_data['ques_difficulties'][$paper_id];
        }
        
        //学校学生
        if (!isset(self::$_data['school_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid FROM rd_summary_region_student_rank srsr
                    WHERE exam_id = $exam_id AND is_school = 1
                    AND region_id = {$teacher['school_id']}";
            $school_uid = self::$_db->fetchCol($sql);
            self::$_data['school_uid'][$exam_id][$teacher_id] = $school_uid;
        }
        else
        {
            $school_uid = self::$_data['school_uid'][$exam_id][$teacher_id];
        }
        
        if (!$school_uid)
        {
            return array();
        }
        
        //教师任课学生
        if (!isset(self::$_data['teacher_class_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid, region_id FROM rd_summary_region_student_rank srsr
                    LEFT JOIN rd_exam_place_student eps ON eps.uid = srsr.uid
                    LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id
                    AND srsr.region_id = ep.place_schclsid
                    WHERE exam_id = $exam_id AND is_class = 1
                    AND srsr.uid IN ({$stuid_str})";
            $cls_uid = self::$_db->fetchPairs($sql);
            self::$_data['teacher_class_uid'][$exam_id][$teacher_id] = $cls_uid;
        }
        else
        {
            $cls_uid = self::$_data['teacher_class_uid'][$exam_id][$teacher_id];
        }
        
        //教师任课班级及班级名称
        $cls_array = array();
        $cls_name = array();
        if ($cls_uid)
        {
            if (self::$_data['clsid_uid'][$exam_id][$teacher_id])
            {
                $cls_array = self::$_data['clsid_uid'][$exam_id][$teacher_id][0];
                $cls_name = self::$_data['clsid_uid'][$exam_id][$teacher_id][1];
            }
            else
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
                unset($cls_ids);
                
                self::$_data['clsid_uid'][$exam_id][$teacher_id] = array(
                    $cls_array,
                    $cls_name
                );
            }
        }
        else
        {
            $cls_array[0] = $stu_ids;
            $cls_name[0] = '任教学生';
        }
        
        //考试情况
        $ques_data = array();
        $ques_except = array();
        if (!isset(self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']]))
        {
            $sql = "SELECT uid, ques_id, sub_ques_id, full_score, test_score
                    FROM rd_exam_test_result
                    WHERE exam_id = $exam_id
                    AND uid IN (" . implode(',', $school_uid) . ")
                    AND ques_id IN ({$ques_id})";
            $query = self::$_db->query($sql);
            while ($v = $query->fetch(PDO_DB::FETCH_ASSOC))
            {
                $q_id = $v['ques_id'];
                if ($v['sub_ques_id'] > 0)
                {
                    $q_id = $v['sub_ques_id'];
                }
                
                if (!isset($ques_except[$q_id]))
                {
                    $difficulty = $ques_difficulties[$v['ques_id']];
                    $ques_except[$q_id] = array(
                        'full_score' => $v['full_score'],
                        'except_score' => $v['full_score'] * $difficulty / 100,
                    );
                }
               
                unset($v['ques_id']);
                unset($v['sub_ques_id']);
                
                $uid = $v['uid'];
                unset($v['uid']);
                
                $ques_data[$q_id][$uid] = $v;
            }
            
            self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][0] = $ques_data;
            self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][1] = $ques_except;
        }
        else 
        {
            $ques_data = self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][0];
            $ques_except = self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][1];
        }
        
        //试卷知识点及认知过程
        $sql = "SELECT knowledge_id, knowledge_name, ques_id, know_process_ques_id 
                FROM rd_summary_paper_knowledge spk
                LEFT JOIN rd_knowledge k ON k.id = spk.knowledge_id
                WHERE spk.is_parent = 0 AND paper_id = $paper_id";
        if ($knowledge_ids)
        {
            $sql .= " AND k.pid IN (" . implode(',', $knowledge_ids) . ")";
        }
        $stmt = self::$_db->query($sql);
        
        $kp_name = array('', '记忆', '理解', '应用');
        $data = array();
        
        //全部比期望低
        $sort = array();
        //部分比期望低
        $sort2 = array();
        //符合期望的知识点
        $qualified_knowledge = array();
       
        while ($v = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            $kid = $v['knowledge_id'];
            
            $data[$kid][0]['知识点'] = '知识点';
            
            $diff_except = array();
            
            $know_process_ques_id = json_decode($v['know_process_ques_id'],true);
            foreach ($know_process_ques_id as $kp => $kp_quesids)
            {
                if (!$kp_quesids || !in_array($kp, array(1, 2, 3)))
                {
                    continue;
                }
                
                $data[$kid][0]['认知过程'] = '认知过程';
                $data[$kid][$kp][] = $v['knowledge_name'];
                $data[$kid][$kp][] = $kp_name[$kp];
                
                $tmp_data = array();
                foreach ($kp_quesids as $kp_quesid)
                {
                    //班级
                    foreach ($cls_array as $cls_id => $cls_uids)
                    {
                        $total_score = 0;
                        $test_score = 0;
                        
                        foreach ($cls_uids as $cls_uid)
                        {
                            $total_score += $ques_data[$kp_quesid][$cls_uid]['full_score'];
                            $test_score += $ques_data[$kp_quesid][$cls_uid]['test_score'];
                        }
                        
                        $tmp_data['cls'][$cls_id]['total_score'] += $total_score;
                        $tmp_data['cls'][$cls_id]['test_score']  += $test_score;
                    }
                    
                    //学校
                    $total_score = 0;
                    $test_score = 0;
                    foreach ($school_uid as $sch_uid)
                    {
                        $total_score += $ques_data[$kp_quesid][$sch_uid]['full_score'];
                        $test_score += $ques_data[$kp_quesid][$sch_uid]['test_score'];
                    }
                    $tmp_data['sch']['total_score'] += $total_score;
                    $tmp_data['sch']['test_score']  += $test_score;
                    
                    //期望
                    $tmp_data['except']['total_score'] += $ques_except[$kp_quesid]['full_score'];
                    $tmp_data['except']['except_score'] += $ques_except[$kp_quesid]['except_score'];
                }
                
                //期望得分率
                $except_percent = round($tmp_data['except']['except_score'] / $tmp_data['except']['total_score'] * 100);
                $except_percent = $except_percent > 100 ? 100 : $except_percent;
                
                //班级
                foreach ($tmp_data['cls'] as $cls_id => $val)
                {
                    $data[$kid][0][$cls_name[$cls_id]] = $cls_name[$cls_id];
                    
                    $percent = round($val['test_score'] / $val['total_score'] * 100);
                    $percent = $percent > 100 ? 100 : $percent;
                    
                    $diff = $percent - $except_percent;
                    $data[$kid][$kp][] = $diff >= 0 ? $percent : -($percent ? $percent : 0.1);
                    $diff_except[] = $diff;
                }
                
                //学校
                $data[$kid][0]['全校'] = '全校';
                $percent = round($tmp_data['sch']['test_score'] / $tmp_data['sch']['total_score'] * 100);
                $data[$kid][$kp][] = $percent > 100 ? 100 : $percent;
                
                //期望
                $data[$kid][0]['期望'] = '期望';
                $data[$kid][$kp][] = $except_percent;
            }
            
            //-1-全部不合格  0-部分合格  1-全部合格
            $is_qualified = 1;
            sort($diff_except);
            foreach ($diff_except as $val)
            {
                if ($val < 0)
                {
                    $is_qualified = -1;
                }
                else if ($val >= 0 && $is_qualified == -1)
                {
                    $is_qualified = 0;
                    break;
                }
            }
            
            if ($is_qualified == 1)
            {
                $qualified_knowledge[] = $v['knowledge_name'];
            }
            else if ($is_qualified == -1)
            {
                $sort[$kid] = array_sum($diff_except);
            }
            else
            {
                $sort2[$kid] = array_sum($diff_except);
            }
        }
        
        asort($sort);
        asort($sort2);
        
        $all_data = array();
        
        foreach ($sort as $kid => $val)
        {
            $all_data[] = array_values($data[$kid]);
        }
        
        //全部得分率都低于期望值记录条数不够数
        if (count($all_data) < 3 && $sort2)
        {
            $count = 3 - $count;
            $sort3 = array_values($sort2);
            if ($sort3[$count-1] > $sort3[$count])
            {
                $kids = array_slice(array_keys($sort2), 0, $count);
                foreach ($kids as $kid)
                {
                    $all_data[] = $data[$kid];
                }
            }
            else 
            {
                $diff = $sort3[$count-1];
                foreach ($sort2 as $kid => $val)
                {
                    if ($val >= $diff)
                    {
                        $all_data[] = $data[$kid];
                    }
                }
            }
        }
        
        return array(
            'qualified_knowledge' => $qualified_knowledge,
            'data' => $all_data,
        );
    }
    
    /**
     * 方法策略运用
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_method_tactic($rule_id = 0, $exam_id = 0, $teacher_id = 0, $method_tactic_ids = array())
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
    
        $stuid_str = implode(',', $stu_ids);
    
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
    
        // 获取该教师所考到的试卷题目
        if (!isset(self::$_data['paper_question'][$paper_id]))
        {
            $sql = "SELECT etpq.ques_id
                    FROM rd_exam_test_paper_question etpq
                    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
                    WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
                    ";
            $ques_id = trim(self::$_db->fetchOne($sql));
            self::$_data['paper_question'][$paper_id] = $ques_id;
        }
        else 
        {
            $ques_id = self::$_data['paper_question'][$paper_id];
        }
        
        $ques_ids = @explode(',', $ques_id);
        if (!$ques_id)
        {
            return array();
        }
         
        // 获取这些题目的难易度
        if (!isset(self::$_data['ques_difficulties'][$paper_id]))
        {
            $sql = "SELECT rc.ques_id,rc.difficulty
                    FROM rd_relate_class rc
                    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                        AND rc.subject_type=e.subject_type
                    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
                    ";
            $ques_difficulties = self::$_db->fetchPairs($sql);
            self::$_data['ques_difficulties'][$paper_id] = $ques_difficulties;
        }
        else
        {
            $ques_difficulties = self::$_data['ques_difficulties'][$paper_id];
        }
        
        //学校学生
        if (!isset(self::$_data['school_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid FROM rd_summary_region_student_rank srsr
                    WHERE exam_id = $exam_id AND is_school = 1
                    AND region_id = {$teacher['school_id']}";
            $school_uid = self::$_db->fetchCol($sql);
            self::$_data['school_uid'][$exam_id][$teacher_id] = $school_uid;
        }
        else
        {
            $school_uid = self::$_data['school_uid'][$exam_id][$teacher_id];
        }
        
        if (!$school_uid)
        {
            return array();
        }
        
        //教师任课学生
        if (!isset(self::$_data['teacher_class_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid, region_id FROM rd_summary_region_student_rank srsr
                    LEFT JOIN rd_exam_place_student eps ON eps.uid = srsr.uid
                    LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id
                    AND srsr.region_id = ep.place_schclsid
                    WHERE exam_id = $exam_id AND is_class = 1
                    AND srsr.uid IN ({$stuid_str})";
            $cls_uid = self::$_db->fetchPairs($sql);
            self::$_data['teacher_class_uid'][$exam_id][$teacher_id] = $cls_uid;
        }
        else
        {
            $cls_uid = self::$_data['teacher_class_uid'][$exam_id][$teacher_id];
        }
        
        //教师任课班级及班级名称
        $cls_array = array();
        $cls_name = array();
        if ($cls_uid)
        {
            if (self::$_data['clsid_uid'][$exam_id][$teacher_id])
            {
                $cls_array = self::$_data['clsid_uid'][$exam_id][$teacher_id][0];
                $cls_name = self::$_data['clsid_uid'][$exam_id][$teacher_id][1];
            }
            else
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
                unset($cls_ids);
                
                self::$_data['clsid_uid'][$exam_id][$teacher_id] = array(
                    $cls_array,
                    $cls_name
                );
            }
        }
        else
        {
            $cls_array[0] = $stu_ids;
            $cls_name[0] = '任教学生';
        }
        
        //考试情况
        $ques_data = array();
        $ques_except = array();
        if (!isset(self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']]))
        {
            $sql = "SELECT uid, ques_id, sub_ques_id, full_score, test_score
                    FROM rd_exam_test_result
                    WHERE exam_id = $exam_id
                    AND uid IN (" . implode(',', $school_uid) . ")
                    AND ques_id IN ({$ques_id})";
            $query = self::$_db->query($sql);
            while ($v = $query->fetch(PDO_DB::FETCH_ASSOC))
            {
                $q_id = $v['ques_id'];
                if ($v['sub_ques_id'] > 0)
                {
                    $q_id = $v['sub_ques_id'];
                }
                
                if (!isset($ques_except[$q_id]))
                {
                    $difficulty = $ques_difficulties[$v['ques_id']];
                    $ques_except[$q_id] = array(
                        'full_score' => $v['full_score'],
                        'except_score' => $v['full_score'] * $difficulty / 100,
                    );
                }
                
                unset($v['ques_id']);
                unset($v['sub_ques_id']);
                
                $uid = $v['uid'];
                unset($v['uid']);
                
                $ques_data[$q_id][$uid] = $v;
            }
            
            self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][0] = $ques_data;
            self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][1] = $ques_except;
        }
        else 
        {
            $ques_data = self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][0];
            $ques_except = self::$_data['exam_ques_data'][$exam_id][$teacher['school_id']][1];
        }
        
        //试卷方法策略
        $sql = "SELECT method_tactic_id, mt.name, ques_id
                FROM rd_summary_paper_method_tactic spmt
                LEFT JOIN rd_method_tactic mt ON mt.id = spmt.method_tactic_id
                WHERE  paper_id = $paper_id";
        if ($method_tactic_ids)
        {
            $sql .= " AND method_tactic_id IN (" . implode(',', $method_tactic_ids) . ")";
        }
        $stmt = self::$_db->query($sql);
    
        $data = array();
    
        //全部比期望低
        $sort = array();
        //部分比期望低
        $sort2 = array();
        //符合期望的方法策略
        $qualified_method_tactic = array();
         
        while ($v = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            $mt_id = $v['method_tactic_id'];
            $ques_ids = explode(',', $v['ques_id']);
            if (!$ques_ids)
            {
                continue;  
            }
            
            $data[$mt_id][0][] = '方法策略';
            
            $diff_except = array();
    
            $data[$mt_id][1][] = $v['name'];

            $tmp_data = array();
            foreach ($ques_ids as $ques_id)
            {
                //班级
                foreach ($cls_array as $cls_id => $cls_uids)
                {
                    $total_score = 0;
                    $test_score = 0;

                    foreach ($cls_uids as $cls_uid)
                    {
                        $total_score += $ques_data[$ques_id][$cls_uid]['full_score'];
                        $test_score += $ques_data[$ques_id][$cls_uid]['test_score'];
                    }

                    $tmp_data['cls'][$cls_id]['total_score'] += $total_score;
                    $tmp_data['cls'][$cls_id]['test_score']  += $test_score;
                }

                //学校
                $total_score = 0;
                $test_score = 0;
                foreach ($school_uid as $sch_uid)
                {
                    $total_score += $ques_data[$ques_id][$sch_uid]['full_score'];
                    $test_score += $ques_data[$ques_id][$sch_uid]['test_score'];
                }
                $tmp_data['sch']['total_score'] += $total_score;
                $tmp_data['sch']['test_score']  += $test_score;

                //期望
                $tmp_data['except']['total_score'] += $ques_except[$ques_id]['full_score'];
                $tmp_data['except']['except_score'] += $ques_except[$ques_id]['except_score'];
            }
    
            //期望得分率
            $except_percent = round($tmp_data['except']['except_score'] / $tmp_data['except']['total_score'] * 100);
            $except_percent = $except_percent > 100 ? 100 : $except_percent;

            //班级
            foreach ($tmp_data['cls'] as $cls_id => $val)
            {
                $data[$mt_id][0][] = $cls_name[$cls_id];
                
                $percent = round($val['test_score'] / $val['total_score'] * 100);
                $percent = $percent > 100 ? 100 : $percent;

                $diff = $percent - $except_percent;
                $data[$mt_id][1][] = $diff >= 0 ? $percent : -($percent ? $percent : 0.1);
                $diff_except[] = $diff;
            }

            //学校
            $data[$mt_id][0][] = '全校';
            $percent = round($tmp_data['sch']['test_score'] / $tmp_data['sch']['total_score'] * 100);
            $data[$mt_id][1][] = $percent > 100 ? 100 : $percent;

            //期望
            $data[$mt_id][0][] = '期望';
            $data[$mt_id][1][] = $except_percent;
    
            //-1-全部不合格  0-部分合格  1-全部合格
            $is_qualified = 1;
            sort($diff_except);
            foreach ($diff_except as $val)
            {
                if ($val < 0)
                {
                    $is_qualified = -1;
                }
                else if ($val >= 0 && $is_qualified == -1)
                {
                    $is_qualified = 0;
                    break;
                }
            }
    
            if ($is_qualified == 1)
            {
                $qualified_method_tactic[] = $v['name'];
            }
            else if ($is_qualified == -1)
            {
                $sort[$mt_id] = array_sum($diff_except);
            }
            else
            {
                $sort2[$mt_id] = array_sum($diff_except);
            }
        }
    
        asort($sort);
        asort($sort2);
        
        $all_data = array();
    
        foreach ($sort as $mt_id => $val)
        {
            $all_data[] = $data[$mt_id];
        }
    
        //全部得分率都低于期望值记录条数不够数
        if (count($all_data) < 3 && $sort2)
        {
            $count = 3 - $count;
            $sort3 = array_values($sort2);
            if ($sort3[$count-1] > $sort3[$count])
            {
                $mt_ids = array_slice(array_keys($sort2), 0, $count);
                foreach ($mt_ids as $mt_id)
                {
                    $all_data[] = $data[$mt_id];
                }
            }
            else
            {
                $diff = $sort3[$count-1];
                foreach ($sort2 as $mt_id => $val)
                {
                    if ($val >= $diff)
                    {
                        $all_data[] = $data[$mt_id];
                    }
                }
            }
        }
        
        return array(
            'qualified_method_tactic' => $qualified_method_tactic,
            'data' => $all_data,
        );
    }
    
    /**
     * 方法策略运用
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_group_type($rule_id = 0, $exam_id = 0, $teacher_id = 0, $group_type_ids = array())
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
    
        $stuid_str = implode(',', $stu_ids);
    
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
    
        // 获取该教师所考到的试卷题目
        if (!isset(self::$_data['paper_question'][$paper_id]))
        {
            $sql = "SELECT etpq.ques_id
                    FROM rd_exam_test_paper_question etpq
                    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
                    WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
                    ";
            $ques_id = trim(self::$_db->fetchOne($sql));
            self::$_data['paper_question'][$paper_id] = $ques_id;
        }
        else
        {
            $ques_id = self::$_data['paper_question'][$paper_id];
        }
    
        $ques_ids = @explode(',', $ques_id);
        if (!$ques_id)
        {
            return array();
        }
         
        // 获取这些题目的难易度
        if (!isset(self::$_data['ques_difficulties'][$paper_id]))
        {
            $sql = "SELECT rc.ques_id,rc.difficulty
                    FROM rd_relate_class rc
                    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                    AND rc.subject_type=e.subject_type
                    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
                    ";
            $ques_difficulties = self::$_db->fetchPairs($sql);
            self::$_data['ques_difficulties'][$paper_id] = $ques_difficulties;
        }
        else
        {
            $ques_difficulties = self::$_data['ques_difficulties'][$paper_id];
        }
        
        //获取试题子题的相对难易度
        if (!isset(self::$_data['difficulty_ratio'][$paper_id]))
        {
            $sql = "SELECT ques_id, difficulty_ratio
                    FROM rd_question
                    WHERE parent_id IN ({$ques_id})
                    ";
            $difficulty_ratio = self::$_db->fetchPairs($sql);
            self::$_data['difficulty_ratio'][$paper_id] = $difficulty_ratio;
        }
        else
        {
            $difficulty_ratio = self::$_data['difficulty_ratio'][$paper_id];
        }
        
        //学校学生
        if (!isset(self::$_data['school_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid FROM rd_summary_region_student_rank srsr
                    WHERE exam_id = $exam_id AND is_school = 1
                    AND region_id = {$teacher['school_id']}";
            $school_uid = self::$_db->fetchCol($sql);
            self::$_data['school_uid'][$exam_id][$teacher_id] = $school_uid;
        }
        else
        {
            $school_uid = self::$_data['school_uid'][$exam_id][$teacher_id];
        }
    
        if (!$school_uid)
        {
            return array();
        }
    
        //教师任课学生
        if (!isset(self::$_data['teacher_class_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid, region_id FROM rd_summary_region_student_rank srsr
                    LEFT JOIN rd_exam_place_student eps ON eps.uid = srsr.uid
                    LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id
                    AND srsr.region_id = ep.place_schclsid
                    WHERE exam_id = $exam_id AND is_class = 1
                    AND srsr.uid IN ({$stuid_str})";
            $cls_uid = self::$_db->fetchPairs($sql);
            self::$_data['teacher_class_uid'][$exam_id][$teacher_id] = $cls_uid;
        }
        else
        {
            $cls_uid = self::$_data['teacher_class_uid'][$exam_id][$teacher_id];
        }
    
        //教师任课班级及班级名称
        $cls_array = array();
        $cls_name = array();
        if ($cls_uid)
        {
            if (self::$_data['clsid_uid'][$exam_id][$teacher_id])
            {
                $cls_array = self::$_data['clsid_uid'][$exam_id][$teacher_id][0];
                $cls_name = self::$_data['clsid_uid'][$exam_id][$teacher_id][1];
            }
            else
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
                unset($cls_ids);
    
                self::$_data['clsid_uid'][$exam_id][$teacher_id] = array(
                    $cls_array,
                    $cls_name
                );
            }
        }
        else
        {
            $cls_array[0] = $stu_ids;
            $cls_name[0] = '任教学生';
        }
    
        //考试情况
        $ques_data = array();
        $ques_except = array();
        if (!isset(self::$_data['exam_question_group_type_data'][$exam_id][$teacher['school_id']]))
        {
            $sql = "SELECT uid, ques_id, sub_ques_id, full_score, test_score
                    FROM rd_exam_test_result
                    WHERE exam_id = $exam_id
                    AND uid IN (" . implode(',', $school_uid) . ")
                    AND ques_id IN ({$ques_id})";
            $query = self::$_db->query($sql);
            while ($v = $query->fetch(PDO_DB::FETCH_ASSOC))
            {
                $q_id = $v['ques_id'];
                if ($v['sub_ques_id'] > 0)
                {
                    $q_id = $v['sub_ques_id'];
                }
    
                if (!isset($ques_except[$q_id]))
                {
                    $difficulty = $ques_difficulties[$v['ques_id']];
                    if ($v['sub_ques_id'] > 0
                        && isset($difficulty_ratio[$q_id])
                        && $difficulty_ratio[$q_id] > 0
                        && $difficulty_ratio[$q_id] <= 2)
                    {
                        $difficulty = $difficulty * $difficulty_ratio[$q_id];
                        $difficulty = $difficulty > 100 ? $ques_difficulties[$v['ques_id']] : $difficulty;
                    }
                    
                    $ques_except[$q_id] = array(
                        'full_score' => $v['full_score'],
                        'except_score' => $v['full_score'] * $difficulty / 100,
                    );
                }
    
                unset($v['ques_id']);
                unset($v['sub_ques_id']);
    
                $uid = $v['uid'];
                unset($v['uid']);
    
                $ques_data[$q_id][$uid] = $v;
            }
    
            self::$_data['exam_question_group_type_data'][$exam_id][$teacher['school_id']][0] = $ques_data;
            self::$_data['exam_question_group_type_data'][$exam_id][$teacher['school_id']][1] = $ques_except;
        }
        else
        {
            $ques_data = self::$_data['exam_question_group_type_data'][$exam_id][$teacher['school_id']][0];
            $ques_except = self::$_data['exam_question_group_type_data'][$exam_id][$teacher['school_id']][1];
        }
    
        //试卷信息提取方式
        $sql = "SELECT group_type_id, gt.group_type_name AS name, ques_id
                FROM rd_summary_paper_group_type spgt
                LEFT JOIN rd_group_type gt ON gt.id = spgt.group_type_id
                WHERE  paper_id = $paper_id AND is_parent = 0";
        if ($group_type_ids)
        {
            $sql .= " AND group_type_id IN (" . implode(',', $group_type_ids) . ")";
        }
        
        $stmt = self::$_db->query($sql);
    
        $data = array();
    
        //全部比期望低
        $sort = array();
        //部分比期望低
        $sort2 = array();
        //符合期望的方法策略
        $qualified_group_type = array();
         
        while ($v = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            $gt_id = $v['group_type_id'];
            $ques_ids = explode(',', $v['ques_id']);
            if (!$ques_ids)
            {
                continue;
            }
    
            $data[$gt_id][0][] = '信息提取方式';
    
            $diff_except = array();
    
            $data[$gt_id][1][] = $v['name'];
    
            $tmp_data = array();
            foreach ($ques_ids as $ques_id)
            {
                //班级
                foreach ($cls_array as $cls_id => $cls_uids)
                {
                    $total_score = 0;
                    $test_score = 0;
    
                    foreach ($cls_uids as $cls_uid)
                    {
                        $total_score += $ques_data[$ques_id][$cls_uid]['full_score'];
                        $test_score += $ques_data[$ques_id][$cls_uid]['test_score'];
                    }
    
                    $tmp_data['cls'][$cls_id]['total_score'] += $total_score;
                    $tmp_data['cls'][$cls_id]['test_score']  += $test_score;
                }
    
                //学校
                $total_score = 0;
                $test_score = 0;
                foreach ($school_uid as $sch_uid)
                {
                    $total_score += $ques_data[$ques_id][$sch_uid]['full_score'];
                    $test_score += $ques_data[$ques_id][$sch_uid]['test_score'];
                }
                $tmp_data['sch']['total_score'] += $total_score;
                $tmp_data['sch']['test_score']  += $test_score;
    
                //期望
                $tmp_data['except']['total_score'] += $ques_except[$ques_id]['full_score'];
                $tmp_data['except']['except_score'] += $ques_except[$ques_id]['except_score'];
            }
    
            //期望得分率
            $except_percent = round($tmp_data['except']['except_score'] / $tmp_data['except']['total_score'] * 100);
            $except_percent = $except_percent > 100 ? 100 : $except_percent;
    
            //班级
            foreach ($tmp_data['cls'] as $cls_id => $val)
            {
                $data[$gt_id][0][] = $cls_name[$cls_id];
    
                $percent = round($val['test_score'] / $val['total_score'] * 100);
                $percent = $percent > 100 ? 100 : $percent;
    
                $diff = $percent - $except_percent;
                $data[$gt_id][1][] = $diff >= 0 ? $percent : -($percent ? $percent : 0.1);
                $diff_except[] = $diff;
            }
    
            //学校
            $data[$gt_id][0][] = '全校';
            $percent = round($tmp_data['sch']['test_score'] / $tmp_data['sch']['total_score'] * 100);
            $data[$gt_id][1][] = $percent > 100 ? 100 : $percent;
    
            //期望
            $data[$gt_id][0][] = '期望';
            $data[$gt_id][1][] = $except_percent;
    
            //-1-全部不合格  0-部分合格  1-全部合格
            $is_qualified = 1;
            sort($diff_except);
            foreach ($diff_except as $val)
            {
                if ($val < 0)
                {
                    $is_qualified = -1;
                }
                else if ($val >= 0 && $is_qualified == -1)
                {
                    $is_qualified = 0;
                    break;
                }
            }
    
            if ($is_qualified == 1)
            {
                $qualified_group_type[] = $v['name'];
            }
            else if ($is_qualified == -1)
            {
                $sort[$gt_id] = array_sum($diff_except);
            }
            else
            {
                $sort2[$gt_id] = array_sum($diff_except);
            }
        }
    
        asort($sort);
        asort($sort2);
    
        $all_data = array();
    
        foreach ($sort as $gt_id => $val)
        {
            $all_data[] = $data[$gt_id];
        }
    
        //全部得分率都低于期望值记录条数不够数
        if (count($all_data) < 3 && $sort2)
        {
            $count = 3 - $count;
            $sort3 = array_values($sort2);
            if ($sort3[$count-1] > $sort3[$count])
            {
                $gt_ids = array_slice(array_keys($sort2), 0, $count);
                foreach ($gt_ids as $gt_id)
                {
                    $all_data[] = $data[$gt_id];
                }
            }
            else
            {
                $diff = $sort3[$count-1];
                foreach ($sort2 as $gt_id => $val)
                {
                    if ($val >= $diff)
                    {
                        $all_data[] = $data[$gt_id];
                    }
                }
            }
        }
    
        return array(
            'qualified_group_type' => $qualified_group_type,
            'data' => $all_data,
        );
    }
    
    /**
     * 题型难易度得分率及总结
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_difficulty($rule_id = 0, $exam_id = 0, $teacher_id = 0)
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
    
        $stuid_str = implode(',', $stu_ids);
    
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        if (!$paper_id)
        {
            return array();
        }
        
        // 获取该教师所考到的试卷题目
        if (!isset(self::$_data['paper_question'][$paper_id]))
        {
            $sql = "SELECT etpq.ques_id
                    FROM rd_exam_test_paper_question etpq
                    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
                    WHERE etp.exam_id={$exam_id} AND etp.paper_id={$paper_id} AND etp.etp_flag=2
                    ";
            $ques_id = trim(self::$_db->fetchOne($sql));
            self::$_data['paper_question'][$paper_id] = $ques_id;
        }
        else
        {
            $ques_id = self::$_data['paper_question'][$paper_id];
        }
        
        $ques_ids = @explode(',', $ques_id);
        if (!$ques_id)
        {
            return array();
        }
        
        // 获取这些题目的难易度
        if (!isset(self::$_data['ques_difficulties'][$paper_id]))
        {
            $sql = "SELECT rc.ques_id,rc.difficulty
                    FROM rd_relate_class rc
                    LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                    AND rc.subject_type=e.subject_type
                    WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$ques_id})
                    ";
            $ques_difficulties = self::$_db->fetchPairs($sql);
            self::$_data['ques_difficulties'][$paper_id] = $ques_difficulties;
        }
        else
        {
            $ques_difficulties = self::$_data['ques_difficulties'][$paper_id];
        }
         
        //学校学生
        if (!isset(self::$_data['school_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid FROM rd_summary_region_student_rank srsr
                    WHERE exam_id = $exam_id AND is_school = 1
                    AND region_id = {$teacher['school_id']}";
            $school_uid = self::$_db->fetchCol($sql);
            self::$_data['school_uid'][$exam_id][$teacher_id] = $school_uid;
        }
        else
        {
            $school_uid = self::$_data['school_uid'][$exam_id][$teacher_id];
        }
        
        if (!$school_uid)
        {
            return array();
        }
        
        //教师任课学生
        if (!isset(self::$_data['teacher_class_uid'][$exam_id][$teacher_id]))
        {
            $sql = "SELECT srsr.uid, region_id FROM rd_summary_region_student_rank srsr
                    LEFT JOIN rd_exam_place_student eps ON eps.uid = srsr.uid
                    LEFT JOIN rd_exam_place ep ON eps.place_id = ep.place_id
                    AND srsr.region_id = ep.place_schclsid
                    WHERE exam_id = $exam_id AND is_class = 1
                    AND srsr.uid IN ({$stuid_str})";
            $cls_uid = self::$_db->fetchPairs($sql);
            self::$_data['teacher_class_uid'][$exam_id][$teacher_id] = $cls_uid;
        }
        else
        {
            $cls_uid = self::$_data['teacher_class_uid'][$exam_id][$teacher_id];
        }
        
        //教师任课班级及班级名称
        $cls_array = array();
        $cls_name = array();
        if ($cls_uid)
        {
            if (self::$_data['clsid_uid'][$exam_id][$teacher_id])
            {
                $cls_array = self::$_data['clsid_uid'][$exam_id][$teacher_id][0];
                $cls_name = self::$_data['clsid_uid'][$exam_id][$teacher_id][1];
            }
            else
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
                unset($cls_ids);
                
                self::$_data['clsid_uid'][$exam_id][$teacher_id] = array(
                    $cls_array,
                    $cls_name
                );
            }
        }
        else
        {
            $cls_array[0] = $stu_ids;
            $cls_name[0] = '任教学生';
        }
        
        //考试情况
        $ques_data = array();
        $sql = "SELECT uid, ques_id, SUM(full_score) AS full_score, 
                SUM(test_score) AS test_score
                FROM rd_exam_test_result
                WHERE exam_id = $exam_id
                AND uid IN (" . implode(',', $school_uid) . ")
                AND ques_id IN ({$ques_id})
                GROUP BY uid, ques_id";
        $query = self::$_db->query($sql);
        $ques_except = array();
        while ($v = $query->fetch(PDO_DB::FETCH_ASSOC))
        {
            $q_id = $v['ques_id'];
            $uid = $v['uid'];
            unset($v['ques_id']);
            unset($v['uid']);
            
            $ques_data[$q_id][$uid] = $v;
            
            $difficulty = $ques_difficulties[$q_id];
            $ques_except[$q_id] = array(
                'full_score' => $v['full_score'],
                'except_score' => $v['full_score'] * $difficulty / 100,
            );
        }
        unset($q_id);
        
        //试卷方法策略
        $sql = "SELECT q_type, low_ques_id, mid_ques_id, high_ques_id
                FROM rd_summary_paper_difficulty
                WHERE paper_id = $paper_id";
        $stmt = self::$_db->query($sql);
        
        $data = array();
        
        $qtype_map = C('qtype');
        
        $level = array('low' => '低', 'mid' => '中', 'high' => '高');
        //全部比期望低
        $sort = array();
        //部分比期望低
        $sort2 = array();
        //符合期望的题型难易度
        $qualified_difficulty = array();
        
        //难易度总结
        $summary_data = array();
        
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            $qtype = $item['q_type'];
            
            foreach ($level as $k => $v)
            {
                $diff_except = array();
                
                $ques_ids = array_filter(explode(',', $item[$k . '_ques_id']));
                if (!$ques_ids)
                {
                    continue;
                }
                
                $key = $qtype . '_' . $k;
                
                $data[$key][0][] = '题型';
                $data[$key][0][] = '难易度';
                $data[$key][1][] = $qtype_map[$qtype];
                $data[$key][1][] = $v;
                
                $tmp_data = array();
                
                foreach ($ques_ids as $ques_id)
                {
                    //班级
                    foreach ($cls_array as $cls_id => $cls_uids)
                    {
                        $total_score = 0;
                        $test_score = 0;
    
                        foreach ($cls_uids as $cls_uid)
                        {
                            $total_score += $ques_data[$ques_id][$cls_uid]['full_score'];
                            $test_score += $ques_data[$ques_id][$cls_uid]['test_score'];
                        }
    
                        $tmp_data['cls'][$cls_id]['total_score'] += $total_score;
                        $tmp_data['cls'][$cls_id]['test_score']  += $test_score;
                    }
    
                    //学校
                    $total_score = 0;
                    $test_score = 0;
                    foreach ($school_uid as $sch_uid)
                    {
                        $total_score += $ques_data[$ques_id][$sch_uid]['full_score'];
                        $test_score += $ques_data[$ques_id][$sch_uid]['test_score'];
                    }
                    $tmp_data['sch']['total_score'] += $total_score;
                    $tmp_data['sch']['test_score']  += $test_score;
    
                    //期望
                    $tmp_data['except']['total_score'] += $ques_except[$ques_id]['full_score'];
                    $tmp_data['except']['except_score'] += $ques_except[$ques_id]['except_score'];
                }
        
                //期望得分率
                $except_percent = round($tmp_data['except']['except_score'] / $tmp_data['except']['total_score'] * 100);
                $except_percent = $except_percent > 100 ? 100 : $except_percent;
    
                //班级
                foreach ($tmp_data['cls'] as $cls_id => $val)
                {
                    $data[$key][0][] = $cls_name[$cls_id];
                    
                    $percent = round($val['test_score'] / $val['total_score'] * 100);
                    $percent = $percent > 100 ? 100 : $percent;
    
                    $diff = $percent - $except_percent;
                    $data[$key][1][] = $diff >= 0 ? $percent : -($percent ? $percent : 0.1);
                    $diff_except[] = $diff;
                    
                    $summary_data[0][$cls_name[$cls_id]] = $cls_name[$cls_id];
                    
                    $summary_data[$k][$cls_id]['total_score'] += $val['total_score'];
                    $summary_data[$k][$cls_id]['test_score'] += $val['test_score'];
                }
    
                //学校
                $data[$key][0][] = '全校';
                $percent = round($tmp_data['sch']['test_score'] / $tmp_data['sch']['total_score'] * 100);
                $data[$key][1][] = $percent > 100 ? 100 : $percent;
                
                $summary_data[0]['全校'] = '全校';
                $summary_data[$k]['sch']['total_score'] += $tmp_data['sch']['total_score'];
                $summary_data[$k]['sch']['test_score'] += $tmp_data['sch']['test_score'];
    
                //期望
                $data[$key][0][] = '期望';
                $data[$key][1][] = $except_percent;
                
                $summary_data[0]['期望'] = '期望';
                $summary_data[$k]['except']['total_score'] += $tmp_data['except']['total_score'];
                $summary_data[$k]['except']['test_score'] += $tmp_data['except']['except_score'];
                
                //-1-全部不合格  0-部分合格  1-全部合格
                $is_qualified = 1;
                sort($diff_except);
                foreach ($diff_except as $val)
                {
                    if ($val < 0)
                    {
                        $is_qualified = -1;
                    }
                    else if ($val >= 0 && $is_qualified == -1)
                    {
                        $is_qualified = 0;
                        break;
                    }
                }
                
                if ($is_qualified == 1)
                {
                    $qualified_difficulty[$qtype_map[$qtype]][] = $v;
                }
                else if ($is_qualified == -1)
                {
                    $sort[$key] = array_sum($diff_except);
                }
                else
                {
                    $sort2[$key] = array_sum($diff_except);
                }
            }
        }
        
        asort($sort);
        asort($sort2);
        
        $all_data = array();
    
        foreach ($sort as $k => $val)
        {
            $all_data[] = $data[$k];
        }
    
        //全部得分率都低于期望值记录条数不够数
        if (count($all_data) < 3 && $sort2)
        {
            $count = 3 - $count;
            $sort3 = array_values($sort2);
            if ($sort3[$count-1] > $sort3[$count])
            {
                $keys = array_slice(array_keys($sort2), 0, $count);
                foreach ($keys as $k)
                {
                    $all_data[] = $data[$k];
                }
            }
            else
            {
                $diff = $sort3[$count-1];
                foreach ($sort2 as $k => $val)
                {
                    if ($val >= $diff)
                    {
                        $all_data[] = $data[$k];
                    }
                }
            }
        }
        
        $data = array();
        foreach ($summary_data as $k => $item)
        {
            if (!$k)
            {
                $data[] = array_values($item);
            }
            else 
            {
                foreach ($item as $key => $v)
                {
                    $data[$k][$key] = round($v['test_score'] / $v['total_score'] * 100);
                }
            }
        }
        
        return array(
            'qualified_difficulty' => $qualified_difficulty,
            'data' => $all_data,
            'summary_date' => $data,
        );
    }

    /**
     * 相同知识模块对比
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_knowledge($rule_id = 0, $exam_id = 0, $teacher_id = 0)
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
        
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
        $stu_id_str = implode(',', $stu_ids);
        
        $stu_ids2 = $this->teacher_common_model->get_teacher_student($teacher_id, $contrast_exam_id);
        if (!$stu_ids2)
        {
            return array();
        }
        $stu_id_str2 = implode(',', $stu_ids2);
        
        //当前考试学生的试卷id
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        //上次考试学生的试卷id
        $contrast_paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $contrast_exam_id);
        
        //当前考试知识点
        $sql = "SELECT ssk.knowledge_id, knowledge_name, 
                ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 1 AND exam_id = $exam_id AND uid IN ($stu_id_str)
                GROUP BY ssk.knowledge_id";
        $curr_knowledge_testscore = self::$_db->fetchAssoc($sql);
        if (!$curr_knowledge_testscore)
        {
            return array();
        }
        
        $curr_knowledge_id = array_keys($curr_knowledge_testscore);
        
        //对比考试知识点
        $sql = "SELECT ssk.knowledge_id, knowledge_name, ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                WHERE is_parent = 1 AND exam_id = $contrast_exam_id AND uid IN ($stu_id_str2)
                AND knowledge_id IN (" . implode(',', $curr_knowledge_id) . ")
                GROUP BY ssk.knowledge_id";
        $contrast_knowledge_testscore = self::$_db->fetchAssoc($sql);
        if (!$contrast_knowledge_testscore)
        {
            return array();
        }
        
        $contrast_knowledge_count = count($contrast_knowledge_testscore);
        $table_knowledge_count = 8;
        if ($contrast_knowledge_count > $table_knowledge_count)
        {
            $table_knowledge_count = ceil($contrast_knowledge_count / 2);
        }
        
        $new_knowledge = array_diff($curr_knowledge_id, array_keys($contrast_knowledge_testscore));
        
        //知识点各个分数
        $knowledge_score = array();
        
        //排序
        $field_sort = array();
        $field_sort_percent = array();
        
        //知识点期望总分
        $tmp_data = array();
        foreach ($contrast_knowledge_testscore as $k_id => $item)
        {
            //本次考试得分
            $curr_testscore = $curr_knowledge_testscore[$k_id]['test_score'];
        
            //上次考试得分
            $contrast_testscore = $item['test_score'];
        
            //本次考试知识点总分
            if (empty(self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_knowledge
                        WHERE paper_id = $paper_id AND knowledge_id = $k_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.ques_id = etr.ques_id AND rc.grade_id = e.grade_id 
                            AND rc.class_id = e.class_id AND rc.subject_type = e.subject_type
                        WHERE etr.exam_id={$exam_id} AND (etr.ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $curr_totalscore = self::$_db->fetchOne($sql);
                self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['knowledge_totalscore'][$exam_id][$paper_id][$k_id];
            }
        
            //上次考试知识点总分
            if (empty(self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_knowledge
                        WHERE paper_id = $contrast_paper_id AND knowledge_id = $k_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score * difficulty / 100) FROM rd_exam_test_result etr
                        LEFT JOIN rd_exam e ON e.exam_id = etr.exam_id
                        LEFT JOIN rd_relate_class rc ON rc.ques_id = etr.ques_id AND rc.grade_id = e.grade_id 
                            AND rc.class_id = e.class_id AND rc.subject_type = e.subject_type
                        WHERE etr.exam_id={$contrast_exam_id} AND (etr.ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $contrast_totalscore = self::$_db->fetchOne($sql);
                self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id] = $contrast_totalscore;
            }
            else
            {
                $contrast_totalscore = self::$_data['knowledge_totalscore'][$contrast_exam_id][$contrast_paper_id][$k_id];
            }
        
            $curr_percent = 0;
            if ($curr_totalscore)
            {
                $curr_percent = round($curr_testscore / $curr_totalscore, 2);
                $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $knowledge_score[$item['knowledge_name']][1] = $curr_percent;
        
            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                $contrast_percent = round($contrast_testscore / $contrast_totalscore, 2);
                $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
            }
            $knowledge_score[$item['knowledge_name']][2] = $contrast_percent;
        
            $field_sort[$item['knowledge_name']] = $curr_percent - $contrast_percent;
            if ($field_sort[$item['knowledge_name']] == 0)
            {
                $field_sort_percent[$item['knowledge_name']] = $curr_percent;
            }
        }
        
        //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else
                {
                    $sort[$key] = $val;
                }
            }
        
            $field_sort = $sort;
        }
        
        $i = 1;
        $k = 0;
        $knowledge_scores = array();
        foreach ($field_sort as $k_name => $val)
        {
            if ($i > $table_knowledge_count)
            {
                $i = 1;
                $k = 1;
            }
        
            $knowledge_scores[$k][0][] = $k_name;
            $knowledge_scores[$k][1][] = $knowledge_score[$k_name][1];
            $knowledge_scores[$k][2][] = $knowledge_score[$k_name][2];
        
            $i++;
        }
        
        $data = array();
        
        if ($new_knowledge)
        {
            $data['new_knowledge'] = $new_knowledge;
        }
        
        $data['contrast_knowledge'] = $knowledge_scores;
        
        return $data;
    }
    
    /**
     * 夯实新知识点
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_new_knowledge($rule_id = 0, $exam_id = 0, $teacher_id = 0, $knowledge_ids = array())
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id || !$knowledge_ids)
        {
            return array();
        }
        
        return $this->module_knowledge($rule_id, $exam_id, $teacher_id, $knowledge_ids);
    }

    /**
     * 相同方法策略模块对比
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_method_tactic($rule_id = 0, $exam_id = 0, $teacher_id = 0)
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
    
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
        $stu_id_str = implode(',', $stu_ids);
        
        $stu_ids2 = $this->teacher_common_model->get_teacher_student($teacher_id, $contrast_exam_id);
        if (!$stu_ids2)
        {
            return array();
        }
        $stu_id_str2 = implode(',', $stu_ids2);
    
        //当前考试学生的试卷id
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        //上次考试学生的试卷id
        $contrast_paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $contrast_exam_id);
        
        //当前考试方法策略
        $sql = "SELECT ssmt.method_tactic_id, name, 
                ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_method_tactic ssmt
                LEFT JOIN rd_method_tactic mt ON mt.id = ssmt.method_tactic_id
                WHERE exam_id = $exam_id AND uid IN ($stu_id_str)
                GROUP BY ssmt.method_tactic_id";
        $curr_mt_testscore = self::$_db->fetchAssoc($sql);
        if (!$curr_mt_testscore)
        {
            return array();
        }
         
        $curr_mt_id = array_keys($curr_mt_testscore);
         
        //对比考试方法策略
        $sql = "SELECT ssmt.method_tactic_id, name, 
                ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_method_tactic ssmt
                LEFT JOIN rd_method_tactic mt ON mt.id = ssmt.method_tactic_id
                WHERE exam_id = $contrast_exam_id AND uid IN ($stu_id_str2)
                AND ssmt.method_tactic_id IN (" . implode(',', $curr_mt_id) . ")
                GROUP BY ssmt.method_tactic_id";
        $contrast_mt_testscore = self::$_db->fetchAssoc($sql);
        if (!$contrast_mt_testscore)
        {
            return array();
        }
         
        $contrast_mt_count = count($contrast_mt_testscore);
        $table_mt_count = 8;
        if ($contrast_mt_count > $table_mt_count)
        {
            $table_mt_count = ceil($contrast_mt_count / 2);
        }
         
        $new_method_tactic = array_diff($curr_mt_id, array_keys($contrast_mt_testscore));
         
        //方法策略各个分数
        $mt_score = array();
         
        //排序
        $field_sort = array();
        $field_sort_percent = array();
         
        //方法策略期望总分
        $tmp_data = array();
        foreach ($contrast_mt_testscore as $mt_id => $item)
        {
            //本次考试得分
            $curr_testscore = $curr_mt_testscore[$mt_id]['test_score'];
             
            //上次考试得分
            $contrast_testscore = $item['test_score'];
             
            //本次考试方法策略期望得分
            if (empty(self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_method_tactic
                        WHERE paper_id = $paper_id AND method_tactic_id = $mt_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
                        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $curr_totalscore = self::$_db->fetchOne($sql);
                 
                self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['method_tactic_totalscore'][$exam_id][$paper_id][$mt_id];
            }
        
            //上次考试方法策略期望得分
            if (empty(self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_method_tactic
                        WHERE paper_id = $contrast_paper_id AND method_tactic_id = $mt_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
                        WHERE exam_id={$contrast_exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $contrast_totalscore = self::$_db->fetchOne($sql);
        
                self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id] = $contrast_totalscore;
            }
            else
            {
                $contrast_totalscore = self::$_data['method_tactic_totalscore'][$contrast_exam_id][$contrast_paper_id][$mt_id];
            }
        
            $curr_percent = 0;
            if ($curr_totalscore)
            {
                $curr_percent = round($curr_testscore / $curr_totalscore * 100);
                $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $mt_score[$item['name']][1] = $curr_percent;
        
            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                $contrast_percent = round($contrast_testscore / $contrast_totalscore * 100);
                $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
            }
            $mt_score[$item['name']][2] = $contrast_percent;
        
            $field_sort[$item['name']] = $curr_percent - $contrast_percent;
            if ($field_sort[$item['name']] == 0)
            {
                $field_sort_percent[$item['name']] = $curr_percent;
            }
        }
        
        //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else
                {
                    $sort[$key] = $val;
                }
            }
        
            $field_sort = $sort;
        }
        
        $i = 1;
        $k = 0;
        $mt_scores = array();
        foreach ($field_sort as $mt_name => $val)
        {
            if ($i > $table_mt_count)
            {
                $i = 1;
                $k = 1;
            }
        
            $mt_scores[$k][0][] = $mt_name;
            $mt_scores[$k][1][] = $mt_score[$mt_name][1];
            $mt_scores[$k][2][] = $mt_score[$mt_name][2];
        
            $i++;
        }
        
        $data = array();
        
        if ($new_method_tactic)
        {
            $data['new_method_tactic'] = $new_method_tactic;
        }
        
        $data['contrast_method_tactic'] = $mt_scores;
        
        return $data;
        
    }

    /**
     * 夯实新方法策略
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_new_method_tactic($rule_id = 0, $exam_id = 0, $teacher_id = 0, $method_tactic_ids = array())
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id || !$method_tactic_ids)
        {
            return array();
        }
    
        return $this->module_method_tactic($rule_id, $exam_id, $teacher_id, $method_tactic_ids);
    }
    
    /**
     * 相同知识模块对比
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_difficulty($rule_id = 0, $exam_id = 0, $teacher_id = 0)
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
    
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
        $stu_id_str = implode(',', $stu_ids);
    
        $stu_ids2 = $this->teacher_common_model->get_teacher_student($teacher_id, $contrast_exam_id);
        if (!$stu_ids2)
        {
            return array();
        }
        $stu_id_str2 = implode(',', $stu_ids2);
    
        //当前考试学生的试卷id
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        //上次考试学生的试卷id
        $contrast_paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $contrast_exam_id);
        
        //当前考试
        $sql = "SELECT q_type, SUM(low_test_score) / COUNT(*) AS low_test_score,
                SUM(mid_test_score) / COUNT(*) AS mid_test_score,
                SUM(high_test_score) / COUNT(*) AS high_test_score
                FROM rd_summary_student_difficulty
                WHERE exam_id = $exam_id AND uid IN ($stu_id_str)
                GROUP BY q_type";
        $curr_qtype_testscore = self::$_db->fetchAssoc($sql);
        if (!$curr_qtype_testscore)
        {
            return array();
        }
         
        $curr_qtype = array_keys($curr_qtype_testscore);
         
        //对比考试
        $sql = "SELECT q_type, SUM(low_test_score) / COUNT(*) AS low_test_score,
                SUM(mid_test_score) / COUNT(*) AS mid_test_score,
                SUM(high_test_score) / COUNT(*) AS high_test_score
                FROM rd_summary_student_difficulty
                WHERE exam_id = $contrast_exam_id AND uid IN ($stu_id_str2)
                GROUP BY q_type";
        $contrast_qtype_testscore = self::$_db->fetchAssoc($sql);
        if (!$contrast_qtype_testscore)
        {
            return array();
        }
        
        $new_qtype = array_diff($curr_qtype,
            array_keys($contrast_qtype_testscore));
         
        //数据
        $data = array();
        
        $subject_id = $this->teacher_common_model->get_exam_item($exam_id);
        
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
        
        $curr_paper = self::$_data['difficulty'][$exam_id][$paper_id];
        
        //获取 上次考试 题型难易度 信息
        if (empty(self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id]))
        {
            $sql = "SELECT DISTINCT(q_type) AS q_type, low_ques_id,
                    mid_ques_id, high_ques_id
                    FROM rd_summary_paper_difficulty
                    WHERE paper_id = $contrast_paper_id
                    ";
        
            self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id] = self::$_db->fetchAssoc($sql);
        }
        
        $contrast_paper =  self::$_data['difficulty'][$contrast_exam_id][$contrast_paper_id];
        
        $q_types = C('q_type');
        $d_types = array('low' => '低难度', 'mid' => '中难度', 'high' => '高难度');
        
        if ($subject_id != 3)
		{
		    $types = array('1','2','3','0','10','14','15','11');
		}
		else
		{
		    $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
		}
        
        foreach ($types as $qtype)
        {
            if (in_array($qtype, $new_qtype))
            {
                continue;
            }
        
            if ($subject_id != 3
                && in_array($qtype, array(4,5,6,7,8,9,12,13)))
            {
                continue;
            }
        
            $item = $curr_paper[$qtype];
            $item2 = $contrast_paper[$qtype];
            if (!$item || !$item2)
            {
                continue;
            }
        
            //当期考试 题型难易度关联试题
            $low_ques_id = $item['low_ques_id'];
            $mid_ques_id = $item['mid_ques_id'];
            $high_ques_id = $item['high_ques_id'];
            	
            $row = array();
            $row[0][] = $q_types[$qtype];
            $row[1][] = '所有任教学生本次';
            	
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
                                AND uid = " . current($stu_ids);
                        $total_score = self::$_db->fetchOne($sql);
                        self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key] = $total_score;
                    }
                    else
                    {
                        $total_score = self::$_data['difficulty_scores'][$exam_id][$paper_id][$qtype][$key];
                    }
        
                    //获取该题型难易度 得分
                    $test_score = $curr_qtype_testscore[$qtype][$key . '_test_score'];
        
                    //计算得分率(得分/总分)
                    $percent = $total_score > 0 ? round($test_score / $total_score * 100) : 0;
                    $percent = $percent > 100 ? 100 : $percent;
                    $percent = $percent < 0 ? 0 : $percent;
                }
        
                $row[0][] = $d_types[$key];
                $row[1][] = $percent;
            }
            	
            //对比考试
            	
            //该题型难易度关联试题
            $low_ques_id = $item2['low_ques_id'];
            $mid_ques_id = $item2['mid_ques_id'];
            $high_ques_id = $item2['high_ques_id'];
            $tmp_arr = array('low' => $low_ques_id, 'mid' => $mid_ques_id, 'high' => $high_ques_id);
            	
            $row[2][] = '所有任教学生上次';
            foreach ($tmp_arr as $key => $ques_id)
            {
                //获取该题型难易度总分
                $total_score = 0;
                $percent = '';
                if ($ques_id)
                {
                    if (empty(self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key]))
                    {
                        $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
                                WHERE exam_id={$contrast_exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                                AND uid = " . current($stu_ids2);
        
                        $total_score = self::$_db->fetchOne($sql);
                        self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key] = $total_score;
                    }
                    else
                    {
                        $total_score = self::$_data['difficulty_scores'][$contrast_exam_id][$contrast_paper_id][$qtype][$key];
                    }
        
                    //获取该题型难易度 得分
                    $test_score = $contrast_qtype_testscore[$qtype][$key . '_test_score'];
                    	
                    //计算得分率(得分/总分)
                    $percent = $total_score > 0 ? round($test_score / $total_score * 100) : 0;
                    $percent = $percent > 100 ? 100 : $percent;
                    $percent = $percent < 0 ? 0 : $percent;
                }
                	
                $row[2][] = $percent;
            }
            	
            $data['contrast_qtype'][] = $row;
        }
        
        return $data;
    }

    /**
     * 相同信息提取方式模块对比
     * @param int $rule_id 评估规则ID
     * @param int $exam_id 考试学科
     * @param int $teacher_id 教师ID
     */
    public function module_contrast_group_type($rule_id = 0, $exam_id = 0, $teacher_id = 0)
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
    
        $stu_ids = $this->teacher_common_model->get_teacher_student($teacher_id, $exam_id);
        if (!$stu_ids)
        {
            return array();
        }
        $stu_id_str = implode(',', $stu_ids);
    
        $stu_ids2 = $this->teacher_common_model->get_teacher_student($teacher_id, $contrast_exam_id);
        if (!$stu_ids2)
        {
            return array();
        }
        $stu_id_str2 = implode(',', $stu_ids2);
    
        //当前考试学生的试卷id
        $paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $exam_id);
        //上次考试学生的试卷id
        $contrast_paper_id = $this->teacher_common_model->get_teacher_exam_paper($teacher_id, $contrast_exam_id);
    
        //当前考信息提取方式模块
        $sql = "SELECT ssgt.group_type_id, group_type_name AS name,
                ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_group_type ssgt
                LEFT JOIN rd_group_type gt ON gt.id = ssgt.group_type_id
                WHERE exam_id = $exam_id AND uid IN ($stu_id_str)
                AND ssgt.is_parent = 0
                GROUP BY ssgt.group_type_id";
        $curr_gt_testscore = self::$_db->fetchAssoc($sql);
        if (!$curr_gt_testscore)
        {
            return array();
        }
         
        $curr_gt_id = array_keys($curr_gt_testscore);
             
        //对比信息提取方式模块
        $sql = "SELECT ssgt.group_type_id, group_type_name AS name,
                ROUND(SUM(test_score)/COUNT(*), 2) AS test_score
                FROM rd_summary_student_group_type ssgt
                LEFT JOIN rd_group_type gt ON gt.id = ssgt.group_type_id
                WHERE exam_id = $contrast_exam_id AND uid IN ($stu_id_str2)
                AND ssgt.group_type_id IN (" . implode(',', $curr_gt_id) . ")
                AND ssgt.is_parent = 0
                GROUP BY ssgt.group_type_id";
        $contrast_gt_testscore = self::$_db->fetchAssoc($sql);
        if (!$contrast_gt_testscore)
        {
            return array();
        }
                     
        $contrast_gt_count = count($contrast_gt_testscore);
        $table_gt_count = 8;
        if ($contrast_gt_count > $table_gt_count)
        {
            $table_mt_count = ceil($contrast_gt_count / 2);
        }
         
        $new_group_type = array_diff($curr_gt_id, array_keys($contrast_gt_testscore));
         
        //信息提取方式各个分数
        $gt_score = array();
         
        //排序
        $field_sort = array();
        $field_sort_percent = array();
         
        //信息提取方式期望总分
        $tmp_data = array();
        foreach ($contrast_gt_testscore as $gt_id => $item)
        {
            //本次考试得分
            $curr_testscore = $curr_gt_testscore[$gt_id]['test_score'];
             
            //上次考试得分
            $contrast_testscore = $item['test_score'];
             
            //本次考试信息提取方式总分
            if (empty(self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_group_type
                        WHERE paper_id = $paper_id AND group_type_id = $gt_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
                        WHERE exam_id={$exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $curr_totalscore = self::$_db->fetchOne($sql);
                 
                self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id] = $curr_totalscore;
            }
            else
            {
                $curr_totalscore = self::$_data['group_type_totalscore'][$exam_id][$paper_id][$gt_id];
            }
        
            //上次考试信息提取方式总分
            if (empty(self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id]))
            {
                $sql = "SELECT ques_id FROM rd_summary_paper_group_type
                        WHERE paper_id = $contrast_paper_id AND group_type_id = $gt_id";
                $ques_id = self::$_db->fetchOne($sql);
        
                $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
                        WHERE exam_id={$contrast_exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
                        GROUP BY etp_id
                        ";
                $contrast_totalscore = self::$_db->fetchOne($sql);
        
                self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id] = $contrast_totalscore;
            }
            else
            {
                $contrast_totalscore = self::$_data['group_type_totalscore'][$contrast_exam_id][$contrast_paper_id][$gt_id];
            }
        
            $curr_percent = 0;
            if ($curr_totalscore)
            {
                $curr_percent = round($curr_testscore / $curr_totalscore * 100, 2);
                $curr_percent = $curr_percent > 100 ? 100 : $curr_percent;
            }
            $gt_score[$item['name']][1] = $curr_percent;
        
            $contrast_percent = 0;
            if ($contrast_totalscore)
            {
                $contrast_percent = round($contrast_testscore / $contrast_totalscore * 100, 2);
                $contrast_percent = $contrast_percent > 100 ? 100 : $contrast_percent;
            }
            $gt_score[$item['name']][2] = $contrast_percent;
        
            $field_sort[$item['name']] = $curr_percent - $contrast_percent;
            if ($field_sort[$item['name']] == 0)
            {
                $field_sort_percent[$item['name']] = $curr_percent;
            }
        }
    
        //对数据进行排序计算
        arsort($field_sort);
        if (count($field_sort_percent) > 1)
        {
            arsort($field_sort_percent);
            $sort = array();
            foreach ($field_sort as $key => $val)
            {
                if ($val == 0 && !isset($sort[$key]))
                {
                    foreach ($field_sort_percent as $k => $v)
                    {
                        $sort[$k] = $val;
                    }
                }
                else
                {
                    $sort[$key] = $val;
                }
            }
    
            $field_sort = $sort;
        }
    
        $i = 1;
        $k = 0;
        $gt_scores = array();
        foreach ($field_sort as $gt_name => $val)
        {
            if ($i > $table_gt_count)
            {
                $i = 1;
                $k = 1;
            }
        
            $gt_scores[$k][0][] = $gt_name;
            $gt_scores[$k][1][] = $gt_score[$gt_name][1];
            $gt_scores[$k][2][] = $gt_score[$gt_name][2];
    
            $i++;
        }
        
        $data = array();
        
        if ($new_group_type)
        {
            $data['new_group_type'] = $new_group_type;
        }
        
        $data['contrast_group_type'] = $gt_scores;
    
        return $data;
    }
    
    /**
    * 夯实新方法策略
    * @param int $rule_id 评估规则ID
    * @param int $exam_id 考试学科
    * @param int $teacher_id 教师ID
    */
    public function module_new_group_type($rule_id = 0, $exam_id = 0, $teacher_id = 0, $group_type_ids = array())
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $teacher_id = intval($teacher_id);
        if (!$rule_id || !$exam_id || !$teacher_id || !$group_type_ids)
        {
            return array();
        }
    
        return $this->module_group_type($rule_id, $exam_id, $teacher_id, $group_type_ids);
    }
}