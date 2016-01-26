<?php if ( ! defined('BASEPATH')) exit();
/**
 * 选学选考报告
 * @author TCG
 * @final 2015-10-27 11:40
 */
class Choose_learn_choose_exam_model extends CI_Model 
{
    private static $_db;
    private static $_data;
    
    public function __construct()
    {
    	parent::__construct();
    	
    	self::$_db = Fn::db();
    	self::$_data = array(); 
    	
    	$this->load->model('cron/report/subject_report/common_model');
    }
    
    /**
     * 学科兴趣排行
     * @param   int     $rule_id    评估规则ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $uid        考生ID
     * @return  array   list<map<string, variant>>  
     */
    public function module_subject_interest($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        //学科兴趣
        $subject_id = 13;
        
        $k = "subject_interest_{$exam_pid}_{$subject_id}_{$uid}";
        
        if (!isset(self::$_data[$k]))
        {
            $sql = "SELECT ssk.*, k.knowledge_name FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                LEFT JOIN rd_exam e ON e.exam_id = ssk.exam_id 
                WHERE ssk.exam_pid = $exam_pid AND e.subject_id = $subject_id 
                AND uid = $uid AND ssk.is_parent = 0
                ORDER BY ssk.test_score DESC";
        
            self::$_data[$k] = self::$_db->fetchAll($sql);
        }
        
        return self::$_data[$k];
    }
    
    /**
     * 选考科目模拟等级及赋分
     * 
     * @param   int     $rule_id    评估规则ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $uid        考生ID
     * @return  array 
     */
    public function module_level_assign_points($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $rule_id = intval($rule_id);
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$rule_id || !$exam_pid || !$uid)
        {
            return array();
        }
        
        $data = array(
            'subject' => array(),         //学科
            'score' => array(),           //学科得分
            'academic_level' => array(),  //模拟学业水平等级
            'assign_level' => array(),    //模拟选考科目等级
            'assign_points' => array()    //模拟选考科目赋分
        );
        
        //学生各科考试成绩
        $bind = array($uid, $exam_pid);
        $sql = "SELECT subject_id, exam_id, full_score, test_score 
                FROM rd_exam_test_paper WHERE uid = ? AND exam_pid = ?
                AND subject_id NOT IN (1, 2, 3, 11, 13, 14, 15, 16) 
                ORDER BY test_score DESC";
        
        $subject_scores = self::$_db->fetchAssoc($sql, $bind);
        
        //考试学科成绩
        $exam_scores = array();
        //学科对应的考试
        $subject_exams = array();
        
        //学科之和*10（(12+18)*10）
        $subject_scores[300] = array(
            'subject_id' => 300,
            'exam_id'    => 0,
            'full_score' => 0,
            'test_score' => 0,
        );
        
        foreach ($subject_scores as $subject_id => &$val)
        {
            if (in_array($subject_id, array(12, 18)))
            {
                $subject_scores[300]['exam_id'] = $val['exam_id'];
                $subject_scores[300]['full_score'] += $val['full_score'];
                $subject_scores[300]['test_score'] += $val['test_score'];
                unset($subject_scores[$subject_id]);
            }
        }
        
        foreach ($subject_scores as $subject_id => $val)
        {
            $subject_exams[$subject_id] = $val['exam_id'];
            
            $test_score = round($val['test_score']);
            $pass_score = floor($val['full_score'] * 4 / 7); //（注：4/7，满分70分，40分及格）
            if ($test_score >= $pass_score)
            {
                //如果考生该科目及格，则学业水平等级、模拟选考科目等级及赋分需要进一步计算，避免后续判断，先默认为'-'
                $exam_scores[$subject_id]['pass_score'] = $pass_score;//当前科目及格分数线
                $exam_scores[$subject_id]['test_score'] = $test_score;//当前科目考生考试分数
                //$exam_scores[$val['subject_id']]['full_score'] = $val['full_score'];//当前科目考试满分
                
                $data['academic_level'][$subject_id] = "-";
                $data['assign_level'][$subject_id] = "-";
                $data['assign_points'][$subject_id] = "-";
            }
            else 
            {
                //如果考生该科目不及格，则学业水平等级为“E”，模拟选考科目等级及赋分为空（不赋值）
                $data['academic_level'][$subject_id] = "E";
                $data['assign_level'][$subject_id] = "-";
                $data['assign_points'][$subject_id] = "-";
            }
            
            $data['score'][$subject_id] = $test_score; //考生考试卷面真实得分
        }
        
        if ($exam_scores)
        {
            //当前考生在所有参加考试的人员中的排名
            $sql = "SELECT e.subject_id, a.rank 
                    FROM rd_summary_region_student_rank a
                    LEFT JOIN rd_exam e ON e.exam_id=a.exam_id
                    WHERE uid = $uid AND subject_id NOT IN (1, 2, 3, 11)
                    GROUP BY a.exam_id";
            
            $exam_ranks = self::$_db->fetchPairs($sql);
            
            //学业水平等级对应的考试排名情况
            $level_student_rank = $this->level_student_rank($exam_pid);
            
            //计算模拟学业水平等级
            foreach ($level_student_rank as $subject_id => $val)
            {
                //如果当前科目不需要计算模拟学业水平等级，则跳过
                if (empty($exam_scores[$subject_id]))
                {
                    continue;
                }
                
                foreach ($val as $level => $item)
                {
                    //如果考生该科目不及格，则学业水平等级为“E”
                    $test_score = $exam_scores[$subject_id]['test_score'];
                    $pass_score = $exam_scores[$subject_id]['pass_score'];
                    if ($test_score < $pass_score)
                    {
                        $data['academic_level'][$subject_id] = "E";
                        break;
                    }
                    
                    //如果考生的排名在当前等级对应的排名区间内，则模拟学业水平等级即为当前值
                    $rank = $exam_ranks[$subject_id];
                    if ($item[0] <= $rank && $rank <= $item[1])
                    {
                        $data['academic_level'][$subject_id] = $level;
                        
                        break;
                    }
                }
            }
            
            //对模拟学业水平等级排序
            $academic_level = array('A+','A','B+','B','B-','C+','C','C-','D+','D','E');
            $sort_level = array();
            foreach ($academic_level as $level)
            {
                foreach ($data['academic_level'] as $subject_id => $subject_level)
                {
                    if ($subject_level == $level)
                    {
                        $sort_level[$subject_id] = $subject_level;
                    }
                }
            }
            $data['academic_level'] = $sort_level;
            
            //计算各科及格的人数
            $exam_qualified_count = array();
            foreach ($exam_scores as $subject_id => $item)
            {
                $exam_id = $subject_exams[$subject_id];
                $pass_score = $item['pass_score'];
                
                $exam_qualified_count[$subject_id] = $this->exam_qualified_count($exam_id, $subject_id, $pass_score, $level_student_rank);
            }
            
            /*
             * 考生成绩按等级赋分，以当次考合格成绩为前提，不合格成绩不赋分，
             * 起点赋分为40分，满分为100分，共21个等级，每个等级分差为3分。
             *
             * 等级所占人数比例
             */
            $grade_proportion= array(
                    1, 2, 3, 4, 5, 6, 7, 8, 7, 7, 7, 7, 7, 7, 6, 5, 4, 3, 2, 1, 1
            );
            
            //根据各科及格人数计算当前考生的模拟选考科目等级及赋分
            foreach ($exam_qualified_count as $subject_id => $count)
            {
                $accumulate_number = 0;
                $rank = $exam_ranks[$subject_id];
                
                foreach ($grade_proportion as $level => $percent)
                {
                    $left_rank = $accumulate_number + 1;//模拟选考科目等级对应的起始排名
                    
                    $number = ceil($count * $percent / 100); //当前模拟选考科目等级人数
                    $accumulate_number += $number;
                    
                    $right_rank = $accumulate_number;//模拟选考科目等级对应的结束排名
                     
                    //如果考生的排名在当前等级对应的排名区间内，则模拟选考科目等级及赋分即为当前值
                    if ($left_rank <= $rank && $rank <= $right_rank)
                    {
                        $data['assign_level'][$subject_id] = $level + 1;
                        $data['assign_points'][$subject_id] = 100 - $level * 3;
                        break;
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * 考试及格人数统计
     * @param      int     $exam_id        考试id
     * @param      int     $subject_id     考试对应的学科
     * @param      int     $pass_score     考试及格分数
     * @param      array   $level_student_rank 学业等级对应的学生排名
     * @return     array
     */
    private function exam_qualified_count($exam_id, $subject_id, 
                       $pass_score, $level_student_rank)
    {
        if (empty(self::$_data['exam_qualified_count'][$exam_id]))
        {
            $level_d_min_rank = $level_student_rank[$subject_id]['D'][0];
            $level_d_max_rank = $level_student_rank[$subject_id]['D'][1];
            
            $sql = "SELECT test_score FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = 1
                    AND rank BETWEEN $level_d_min_rank AND $level_d_max_rank
                    ORDER BY rank DESC";
            $test_score = self::$_db->fetchOne($sql);
             
            if ($test_score >= $pass_score)
            {
                $sql = "SELECT COUNT(*) AS count FROM rd_summary_region_student_rank
                        WHERE exam_id = $exam_id AND region_id = 1 AND rank <= $level_d_max_rank";
                $exam_qualified_count = self::$_db->fetchOne($sql);
            }
            else
            {
                $sql = "SELECT COUNT(*) AS count FROM rd_exam_test_paper
                        WHERE exam_id = $exam_id AND test_score >= $pass_score";
                $exam_qualified_count = self::$_db->fetchOne($sql);
            }
            
            self::$_data['exam_qualified_count'][$exam_id] = $exam_qualified_count;
        }
        else 
        {
            $exam_qualified_count = self::$_data['exam_qualified_count'][$exam_id];
        }
        
        return $exam_qualified_count;
    }
    
    /**
     * 学业水平等级对应的考试排名情况
     * @param  int     $exam_pid   考试期次id
     */
    private function level_student_rank($exam_pid)
    {
        if (empty(self::$_data['level_student_rank'][$exam_pid]))
        {
            //考试人数
            $sql = "SELECT subject_id, COUNT(*) AS number 
                    FROM rd_exam_test_paper
                    WHERE exam_pid = $exam_pid AND subject_id NOT IN (1, 2, 3, 11)
                    GROUP BY exam_id";
            $query = self::$_db->query($sql);
             
            /*
             * 模拟学业水平等级及所占比例
             */
            $academic_level = array(
                    'A+' => 5,
                    'A' => 10,
                    'B+' => 10,
                    'B' => 10,
                    'B-' => 10,
                    'C+' => 10,
                    'C' => 10,
                    'C-' => 10,
                    'D+' => 10,
                    'D' => 10,
                    'E' => 5
            );
             
            while ($item = $query->fetch(PDO::FETCH_ASSOC))
            {
                $accumulate_number = 0;
            
                foreach ($academic_level as $level => $percent)
                {
                    $number = ceil($item['number'] * $percent / 100);
                     
                    $level_student_rank[$item['subject_id']][$level][] = $accumulate_number + 1;
                     
                    $accumulate_number += $number;
                     
                    $level_student_rank[$item['subject_id']][$level][] = $accumulate_number;
                }
            }
            
            self::$_data['level_student_rank'][$exam_pid] = $level_student_rank;
        }
        else 
        {
            $level_student_rank = self::$_data['level_student_rank'][$exam_pid];
        }
        
        return $level_student_rank;
    }
    
    /**
     * 学习风格类型
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array   
     */
    public function module_learn_style($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        //学习风格
        $subject_id = 14;
        
        //学习风格满分
        $total_score = 11;
        
        $data = array(
            'data' => array(),
            'flash_data' => array()
        );
        
        $data['flash_data']['fields'] = array();
        $data['flash_data']['data'] = array(
            array(
                'name' => '',
                'data' => array()
            ),
            array(
                'name' => '',
                'data' => array()
            )
        );
        
        $attr_list = array();
        if (!isset(self::$_data['learn_style']))
        {
            $sql = "SELECT CONCAT(learnstyle_knowledgeid, '_', lsattr_value) AS k,
                    lsattr_name, lsattr_define, lsattr_advice
                    FROM t_learn_style_attribute
                    LEFT JOIN t_learn_style ON learnstyle_id = lsattr_learnstyleid";
            self::$_data['learn_style'] = self::$_db->fetchAssoc($sql);
        }
        $attr_list = self::$_data['learn_style'];
        
        $sql = "SELECT ssk.*, k.knowledge_name FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                LEFT JOIN rd_exam e ON e.exam_id = ssk.exam_id 
                WHERE ssk.exam_pid = $exam_pid AND e.subject_id = $subject_id 
                AND uid = $uid AND ssk.is_parent = 0
                ORDER BY ssk.knowledge_id DESC
                ";
        $stmt = self::$_db->query($sql);
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            //具象型/图像型/主动型/渐进型 反向
            $score = $total_score - $item['test_score'];
            if ($item['test_score'] > $score)
            {
                $v = 2;
                
                $lsattr_name2 =  trim($attr_list[$item['knowledge_id'] ."_1"]['lsattr_name']);
                $lsattr_name = trim($attr_list[$item['knowledge_id'] ."_2"]['lsattr_name']);
                
                $data['flash_data']['data'][0]['data'][$lsattr_name2] = - (int)$item['test_score'];
                $data['flash_data']['data'][1]['data'][$lsattr_name] = (int)$score;
                
                $data['flash_data']['fields'][0][] = $lsattr_name;
                $data['flash_data']['fields'][1][] = $lsattr_name2;
            }
            //抽象型/文字性/反思型/整体型 正向
            else 
            {
                $v = 1;
                
                $lsattr_name = trim($attr_list[$item['knowledge_id'] ."_1"]['lsattr_name']);
                $lsattr_name2 =  trim($attr_list[$item['knowledge_id'] ."_2"]['lsattr_name']);
                
                $data['flash_data']['data'][0]['data'][$lsattr_name2] = - (int)$item['test_score'];
                $data['flash_data']['data'][1]['data'][$lsattr_name] = (int)$score;
                
                $data['flash_data']['fields'][0][] = $lsattr_name2;
                $data['flash_data']['fields'][1][] = $lsattr_name;
            }
            
            $data['data'][] = array(
                $item['knowledge_name'],
                $lsattr_name,
                $attr_list[$item['knowledge_id'] ."_{$v}"]['lsattr_define'],
                $attr_list[$item['knowledge_id'] ."_{$v}"]['lsattr_advice'],
            );
        }
        
        if (!$data['data'])
        {
            return array();
        }
        
        krsort($data['data']);
        
        $data['flash_data']['data'][0]['data'] = array_values($data['flash_data']['data'][0]['data']);
        $data['flash_data']['data'][1]['data'] = array_values($data['flash_data']['data'][1]['data']);
        
        return $data;
    }
    
    /**
     * 综合选科雷达图
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array
     */
    public function module_comprehensive_selection($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        $exam = array();
        
        $data = array();
        
        $subject = C('subject');
        unset($subject[12]);
        unset($subject[18]);
        $subject['300'] = '技术';
        
        //学业成绩
        $sql = "SELECT etp.exam_id, etp.paper_id, etp.subject_id, 
                etp.full_score, etp.test_score, ep.difficulty
                FROM rd_exam_test_paper etp
                LEFT JOIN rd_exam_paper ep ON ep.paper_id = etp.paper_id
                WHERE exam_pid = $exam_pid AND etp.uid = $uid";
        $stmt = self::$_db->query($sql);
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            if (!in_array($item['subject_id'], 
                array(1, 2, 3, 10, 11, 13, 14, 15, 16)))
            {
                $except_score = $item['full_score'] * $item['difficulty'] / 100;
                
                if (in_array($item['subject_id'], array(12, 18)))
                {
                    if (!isset($data[0]['技术']))
                    {
                        $data[0]['技术'] = array(
                            'full_score' => 0,
                            'test_score' => 0,
                            'except_score' => 0,
                        );
                    }
                    
                    $exam['300'] = 300;
                    
                    $data[0]['技术']['full_score'] += $item['full_score'];
                    $data[0]['技术']['test_score'] += $item['test_score'];
                    $data[0]['技术']['except_score'] += $except_score;
                }
                else
                {
                    $v = round(($item['test_score'] - $except_score) / $item['full_score'] * 3.4 + 1, 1);
                    $v = $v > 2 ? 2 : ($v < 0 ? 0 : $v);
                    
                    $data[0][$subject[$item['subject_id']]] = $v;
                    
                    $exam[$item['subject_id']] = $item['subject_id'];
                }
            }
        }
        
        if (isset($data[0]['技术']))
        {
            $v = round(($data[0]['技术']['test_score'] - $data[0]['技术']['except_score']) 
                / $data[0]['技术']['full_score'] * 3.4 + 1, 1);
            $v = $v > 2 ? 2 : ($v < 0 ? 0 : $v);
            
            $data[0]['技术'] = $v;
        }
        
        if (!$exam)
        {
            return array();
        }
        
        //学科兴趣
        $subject_interest = array();
        $k =  $k = "subject_interest_{$exam_pid}_13_{$uid}";
        if (!isset(self::$_data[$k]))
        {
            $subject_interest = $this->module_subject_interest($rule_id, $exam_pid, $uid);
        }
        else 
        {
            $subject_interest = self::$_data[$k];
        }
        
        if (!$subject_interest)
        {
            return array();
        }
        
        foreach ($subject_interest as $item)
        {
            $data[2][$item['knowledge_name']] = round($item['test_score'] / $item['total_score'] * 2, 1);
        }
        
        //学科四维及关联职业
        $sql = "SELECT * FROM t_subject_dimension";
        $subject_dimension = self::$_db->fetchAssoc($sql);
        if (!$subject_dimension)
        {
            return array();
        }
        
        //职业信息
        $profession_id = array();
        $subject_profession = array();
        foreach ($subject_dimension as $item)
        {
            $subject_profession[$item['subd_subjectid']] = json_decode($item['subd_professionid'], true);
            $profession_id = array_merge($profession_id, 
                $subject_profession[$item['subd_subjectid']]);
        }
        $profession_id = array_unique($profession_id);
        $sql = "SELECT * FROM t_profession 
                WHERE profession_id IN (" . implode(',', $profession_id) . ")";
        $profession = self::$_db->fetchAssoc($sql);
        
        //学习风格
        $attr_list = array();
        if (!isset(self::$_data['learn_style']))
        {
            $sql = "SELECT CONCAT(learnstyle_knowledgeid, '_', lsattr_value) AS k,
                    lsattr_name, lsattr_define, lsattr_advice
                    FROM t_learn_style_attribute
                    LEFT JOIN t_learn_style ON learnstyle_id = lsattr_learnstyleid";
            self::$_data['learn_style'] = self::$_db->fetchAssoc($sql);
        }
        $attr_list = self::$_data['learn_style'];
        
        //本次考试学生考试风格得分
        $sql = "SELECT ssk.*, k.knowledge_name FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                LEFT JOIN rd_exam e ON e.exam_id = ssk.exam_id
                WHERE ssk.exam_pid = $exam_pid AND e.subject_id = 14
                AND uid = $uid AND ssk.is_parent = 0
                ";
        $stmt = self::$_db->query($sql);
        
        $dimension = array();
        $new_dimension = array();
        $learn_style = array('感知' => 0, '输入' => 0, '加工' => 0, '理解' => 0);
        while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC))
        {
            foreach ($subject as $subject_id => $val)
            {
                if (in_array($subject_id, 
                    array(1, 2, 3, 10, 11, 13, 14, 15, 16))
                    || !in_array($subject_id, $exam))
                {
                    continue;
                }
                
                if (!isset($new_dimension[$subject_id]))
                {
                    $new_dimension[$subject_id] = $learn_style;
                }
                
                $dimension[$subject_id] = explode(',', $subject_dimension[$subject_id]['subd_value']);
                
                $new_dimension[$subject_id][$item['knowledge_name']] = 11 - 2 * $item['test_score'];
            }
        }
        
        foreach ($new_dimension as $sub_id => $item)
        {
            $tmp_val = 0;
            foreach (array_values($item) as $k => $v)
            {
                $tmp_val += ($v - $dimension[$sub_id][$k]) * ($v - $dimension[$sub_id][$k]);
            }
            
            if ($tmp_val > 0)
            {
                $v = round(2 - sqrt($tmp_val) / 16, 1);
                $data[1][$subject[$sub_id]] = $v > 2 ? 2 : ($v < 0 ? 0 : $v);
            }
            else 
            {
                $data[1][$subject[$sub_id]] = 0;
            }
        }
        
        $sort = array();
        $lack_subject = array();
        
        //综合推荐及排序
        foreach ($data[1] as $subject_name => $item)
        {
            $val = $item * 2;
            $val2 = 2;
            if (!is_null($data[0][$subject_name]))
            {
                $val += $data[0][$subject_name] * 3;
                $val2 += 3;
            }
            else
            {
                $lack_subject[] = $subject_name;
            }
            
            if (!is_null($data[2][$subject_name]))
            {
                $val += $data[2][$subject_name];
                $val2 += 1;
            }
            
            $sort[$subject_name] = round($val / $val2, 1);
        }
        
        $data[3] = $sort;
        
        arsort($sort);
        
        $flash_data = array('fields' => array(), 'data' => array());
        $level = array('学业水平', '学习风格', '学科兴趣', '综合推荐');
        
        foreach ($data as $key => &$item)
        {
            $arr = array();
            foreach ($sort as $subject_name => $val)
            {
                if (in_array($subject_name, $lack_subject))
                {
                    continue;
                }
                
                $arr[$subject_name] = isset($item[$subject_name]) ? $item[$subject_name] : null;
            }
            
            if ($lack_subject)
            {
                foreach ($lack_subject as $k_name)
                {
                    $arr[$k_name] = isset($item[$k_name]) ? $item[$k_name] : null;
                }
            }
            
            $item = $arr;
            
            $flash_data['data'][]= array( 
                'name' => $level[$key],
                'data' => array_values($arr)
            );
        }
        
        //综合推荐学科
        $tmp_data = array_values($data[3]);
        $recommended_subject = array();
        $subject_related_profession = array();
        if ($tmp_data[2] > $tmp_data[3])
        {
            $recommended_subject = array_splice(array_keys($data[3]), 0, 3);
        }
        else
        {
            //综合比较
            $compare_subject = array();
            $i = 0;
            foreach ($data[3] as $k_name => $val)
            {
                if ($val > $tmp_data[3])
                {
                    $recommended_subject[$k_name] = $k_name;
                }
                else if ($val == $tmp_data[3])
                {
                    $compare_subject[] = $k_name;
                }
            }
            
            //学业成绩比较
            $sort = array();
            $tmp = $data[0];
            arsort($tmp);
            foreach ($tmp as $k_name => $val)
            {
                if (in_array($k_name, $compare_subject) 
                    && !is_null($val))
                {
                    $sort[$k_name] = $val;
                }
            }
            
            arsort($sort);
            $sort2 = array_values($sort);
            $count = 3 - count($recommended_subject);
            if ($sort2[$count-1] > $sort2[$count])
            {
                $recommended_subject = array_merge($recommended_subject, 
                    array_slice(array_keys($sort), 0, $count));
            }
            else 
            {
                foreach ($tmp as $k_name => $val)
                {
                    if (!in_array($k_name, $compare_subject) 
                        || is_null($val))
                    {
                        continue;
                    }
                    
                    if ($val > $sort2[$count])
                    {
                        $recommended_subject[$k_name] = $k_name;
                    }
                }
            }
            
            $count = 3 - count($recommended_subject);
            //学习风格比较
            if ($count > 0)
            {
                $sort = array();
                $tmp = $data[1];
                arsort($tmp);
                foreach ($tmp as $k_name => $val)
                {
                    if (in_array($k_name, $compare_subject)
                        && !is_null($val))
                    {
                        $sort[$k_name] = $val;
                    }
                }
                arsort($sort);
                $sort2 = array_values($sort);
                if ($sort2[$count-1] > $sort2[$count])
                {
                    $recommended_subject = array_merge($recommended_subject,
                        array_slice(array_keys($sort), 0, $count));
                }
                else
                {
                    $compare_subject = array();
                    foreach ($tmp as $k_name => $val)
                    {
                        if (!in_array($k_name, $compare_subject)
                            || is_null($val))
                        {
                            continue;
                        }
                        
                        if ($val > $sort2[$count])
                        {
                            $recommended_subject[] = $k_name;
                        }
                    }
                }
            }
            
            $count = 3 - count($recommended_subject);
            //学习兴趣比较
            if ($count > 0)
            {
                $sort = array();
                $tmp = $data[2];
                arsort($tmp);
                foreach ($tmp as $k_name => $val)
                {
                    if (in_array($k_name, $compare_subject)
                        && !is_null($val))
                    {
                        $sort[$k_name] = $val;
                    }
                }
            
                arsort($sort);
                $sort2 = array_values($sort);
                if ($sort2[$count-1] > $sort2[$count])
                {
                    $recommended_subject = array_merge($recommended_subject,
                        array_slice(array_keys($sort), 0, $count));
                }
                else
                {
                    foreach ($tmp as $k_name => $val)
                    {
                        if (!in_array($k_name, $compare_subject)
                            || is_null($val))
                        {
                            continue;
                        }
                        
                        if ($val > $sort2[$count])
                        {
                            $recommended_subject[] = $k_name;
                        }
                    }
                }
            }
            
            if (3 - count($recommended_subject) > 0)
            {
                $recommended_subject = array_splice(array_keys($data[3]), 0, 3);
            }
        }
        
        $subject2 = array_flip($subject);
        foreach ($recommended_subject as $sub_name)
        {
            foreach ($subject_profession[$subject2[$sub_name]] as $profession_id)
            {
                $subject_related_profession[$profession_id] = $profession[$profession_id];
            }
        }
        
        $flash_data['fields'] = array_keys($data[3]);
        
        return array(
            'flash_data' => $flash_data,
            'recommended_subject' => array_values($recommended_subject),
            'subject_related_profession' => $subject_related_profession,
        );
    }
    
    /**
     * 非难题答对率
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array   
     */
    public function module_easy_correct_ratio($rule_id = 0, $exam_pid = 0, $uid = 0, $include_subject = array())
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        if (!empty($include_subject))
        {
            foreach ($include_subject as $k => $v)
            {
                if (in_array($v, array(13, 14, 15, 16)))
                {
                    unset($include_subject[$k]);
                }
            }
            
            $include_subject_str = " AND subject_id IN (".implode(',', $include_subject).")";
        }
        else
        {
            $include_subject_str = " AND subject_id NOT IN (13, 14, 15, 16)";
        }
    
        if (empty(self::$_data['level_percent'][$exam_pid]))
        {
            $sql = "SELECT * FROM v_complex_level_percent
                    WHERE exam_pid = $exam_pid $include_subject_str
                    ORDER BY subject_id
                    ";
    
            self::$_data['level_percent'][$exam_pid] = self::$_db->fetchAll($sql);
        }
    
        $data = self::$_data['level_percent'][$exam_pid];
        
        $subject_count_percent_p = array(); //个人学科难易度答对率
        $subject_count_percent = array(); //所有考试人员难易度答对率
        $subject_count_percents = array(); //当前保存的所有考试人员难易度答对率
    
        if (isset(self::$_data['subject_count_percent'][$exam_pid]))
        {
            $subject_count_percents = self::$_data['subject_count_percent'][$exam_pid];
        }
    
        //所有考试人员
        $subject_count_all = array();
        $subject_count_yes = array();
    
        //当前考生
        $subject_count_all_p = array();
        $subject_count_yes_p = array();
    
        foreach ($data as $item)
        {
            if (in_array($item['subject_id'], array(12, 18)))
            {
                $item['subject_id'] = 300;
            }
            
            if (!$subject_count_percents)
            {
                if (empty($subject_count_all[$item['subject_id']]))
                {
                    $subject_count_all[$item['subject_id']] = 0;
                }
                $subject_count_all[$item['subject_id']]++;
    
                if (empty($subject_count_yes[$item['subject_id']]))
                {
                    $subject_count_yes[$item['subject_id']] = 0;
                }
    
                if ($item['full_score'] == $item['test_score'])
                {
                    $subject_count_yes[$item['subject_id']]++;
                }
            }
    
            if ($item['uid'] == $uid)
            {
                if (empty($subject_count_all_p[$item['subject_id']]))
                {
                    $subject_count_all_p[$item['subject_id']] = 0;
                }
                $subject_count_all_p[$item['subject_id']]++;
    
                if (empty($subject_count_yes_p[$item['subject_id']]))
                {
                    $subject_count_yes_p[$item['subject_id']] = 0;
                }
    
                if ($item['full_score'] == $item['test_score'])
                {
                    $subject_count_yes_p[$item['subject_id']]++;
                }
            }
        }
    
        /*
         * 计算个人各学科的高于平均水平各学科的答对百分比
         */
        $subjects = C('subject');
        $subjects['300'] = '技术';
        foreach ($subject_count_all_p as $key => $val )
        {
            $percent = round(($subject_count_yes_p[$key] / $val) * 100);
            $subject_count_percent_p[$subjects[$key]] = $percent > 100 ? 100 : $percent;
    
            if (!$subject_count_percents)
            {
                $percent = round(($subject_count_yes[$key] / $subject_count_all[$key]) * 100);
                $subject_count_percent[$subjects[$key]] = $percent > 100 ? 100 : $percent;
            }
        }
    
        if (!$subject_count_percents)
        {
            self::$_data['subject_count_percent'][$exam_pid] = $subject_count_percent;
        }
        else
        {
            $subject_count_percent = $subject_count_percents;
        }
    
        return array(
            'subject_count_percent_p' => $subject_count_percent_p,
            'subject_count_percent' => $subject_count_percent
        );
    }
    
    /**
     * 职业兴趣
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array
     */
    public function module_vocational_interest($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
    
        //职业兴趣
        $subject_id = 15;
        
        $sql = "SELECT ssk.knowledge_id, ssk.test_score, k.knowledge_name, pr.*
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                LEFT JOIN rd_exam e ON e.exam_id = ssk.exam_id
                LEFT JOIN t_profession_related pr 
                    ON pr.pr_knowledgeid = ssk.knowledge_id AND pr.pr_subjectid = e.subject_id
                WHERE ssk.exam_pid = $exam_pid AND e.subject_id = $subject_id
                AND ssk.uid = $uid AND ssk.is_parent = 0
                ORDER BY ssk.test_score DESC";
        $list = self::$_db->fetchAll($sql);
        if (!$list)
        {
            return array();
        }
        
        //默认取前3条
        if (count($list) > 3)
        {
            if ($list[2]['test_score'] > $list[3]['test_score'])
            {
                $data = array_slice($list, 0, 3);
            }
            else
            {
                foreach ($list as $val)
                {
                    if ($val['test_score'] >= $list[3]['test_score'])
                    {
                        $data[] = $val;
                    }
                }
            }
        }
        else
        {
            $data = $list;
        }
        
        if (count($data) > 5)
        {
            $data = array_slice($data, 0, 5);
        }
        
        $profession_id = array();
        foreach ($data as &$item)
        {
            $item['pr_professionid'] = json_decode($item['pr_professionid'], true);
            $profession_id = array_merge($profession_id, $item['pr_professionid']);
        }
        
        $profession_id = array_unique($profession_id);
        $sql = "SELECT * FROM t_profession
                WHERE profession_id IN (" . implode(',', $profession_id) . ")";
        $profession = self::$_db->fetchAssoc($sql);
        
        return array(
            'data' => $data,
            'profession' => $profession
        );
    }
    
    /**
     * 职业能力倾向
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array
     */
    public function module_vocational_aptitude($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
    
        //职业能力倾向
        $subject_id = 16;
        
        $sql = "SELECT ssk.knowledge_id, ROUND(ssk.test_score/5, 1) AS test_score, 
                k.knowledge_name, pr.*
                FROM rd_summary_student_knowledge ssk
                LEFT JOIN rd_knowledge k ON k.id = ssk.knowledge_id
                LEFT JOIN rd_exam e ON e.exam_id = ssk.exam_id
                LEFT JOIN t_profession_related pr 
                    ON pr.pr_knowledgeid = ssk.knowledge_id AND pr.pr_subjectid = e.subject_id
                WHERE ssk.exam_pid = $exam_pid AND e.subject_id = $subject_id
                AND ssk.uid = $uid AND ssk.is_parent = 0
                ORDER BY test_score DESC";
        $list = self::$_db->fetchAll($sql);
        if (!$list)
        {
            return array();
        }
        
        //默认取前3条
        if (count($list) > 3)
        {
            if ($list[2]['test_score'] > $list[3]['test_score'])
            {
                $data = array_slice($list, 0, 3);
            }
            else
            {
                foreach ($list as $val)
                {
                    if ($val['test_score'] >= $list[3]['test_score'])
                    {
                        $data[] = $val;
                    }
                }
            }
        }
        else
        {
            $data = $list;
        }
        
        if (count($data) > 5)
        {
            $data = array_slice($data, 0, 5);
        }
        
        $profession_id = array();
        foreach ($data as &$item)
        {
            $item['pr_professionid'] = json_decode($item['pr_professionid'], true);
            $profession_id = array_merge($profession_id, $item['pr_professionid']);
        }
        
        $profession_id = array_unique($profession_id);
        $sql = "SELECT * FROM t_profession
                WHERE profession_id IN (" . implode(',', $profession_id) . ")";
        $profession = self::$_db->fetchAssoc($sql);
        
        return array(
            'data' => $data,
            'profession' => $profession
        );
    }
    
    /**
     * 职业推荐
     * @param   int     $rule_id    评估规则id
     * @param   int     $exam_pid   考试期次id
     * @param   int     $uid        考生id
     * @return  array
     */
    public function module_profession_recommendation($rule_id = 0, $exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        return true;
    }
}