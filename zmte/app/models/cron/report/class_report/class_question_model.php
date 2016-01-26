<?php
if (!defined('BASEPATH')) exit();

/**
 * 测评报告-班级-试题分析及与之对应的评价
 * @author TCG
 * @final 2015-10-12
 */
class Class_question_model extends CI_Model
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
     * 获取 试题分析和评价
     * 
     * @param number $rule_id  评估规则ID
     * @param number $exam_id  考试学科
     * @param number $schcls_id      班级ID
     */
    public function module_all($rule_id = 0, $exam_id = 0, $schcls_id = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $schcls_id = intval($schcls_id);
        if (!$rule_id || !$exam_id || !$schcls_id)
        {
            return array();
        }
        
        // 列字段
        $fields = array('题号', '难易度', '分值', '平均得分', '期望得分');
        
        // 数据
        $data = array();
        
        // 数据
        $flash_data = array('field' => array(), 'data' => array());
        
        // 分析信息
        $desc_data = array();
        
        //获取该班级所在区域
        $class = $this->class_common_model->get_class_info($schcls_id);
        
        $paper_id = $this->class_common_model->get_class_exam_paper($schcls_id, $exam_id);
        if (!$paper_id)
        {
            return array();
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
        
        // 对比等级(总体)
        $comparison_levels = $this->class_common_model->get_rule_comparison_level($rule_id);
        if (!$comparison_levels)
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
        
        //考试区域试题平均分
        $sql = "SELECT CONCAT(ques_id,'_',region_id,'_',is_school,'_',is_class),avg_score 
                FROM rd_summary_region_question 
                WHERE exam_id={$exam_id} AND ques_id IN({$ques_id}) AND is_class = 0
                ";
        $ques_avg_score = self::$_db->fetchPairs($sql);
               
        //本次考试班级试题得分情况
        $sql = "SELECT ques_id, ROUND(total_score/student_amount, 2) AS full_score, avg_score
                FROM rd_summary_region_question
                WHERE exam_id={$exam_id} AND region_id = $schcls_id
                AND ques_id IN({$ques_id}) AND is_school = 0 AND is_class = 1
                ";
        $ques_score = self::$_db->fetchAssoc($sql);
        
        $auto_key = 0;
        $max_score = 0;
        foreach ($ques_ids as $ques_id)
        {
            $auto_key++;
            
            
            $full_score = 0;
            $test_score = 0;
            
            if (isset($ques_score[$ques_id]))
            {
                $full_score = $ques_score[$ques_id]['full_score'];
                $test_score = round($ques_score[$ques_id]['avg_score'], 2);
            }
            
            $q_diffculty = isset($ques_difficulties[$ques_id]) ? $ques_difficulties[$ques_id] : 0;
            $difficulty_level = $q_diffculty > 0 ?$this->class_common_model->convert_question_difficulty($q_diffculty) : '--';
            
            $k = "第 {$auto_key} 题";
            $data[$k][] = $difficulty_level;
            $data[$k][] = $full_score;
            $data[$k][] = $test_score;
            $expect_score = round($full_score * $q_diffculty / 100, 2);
            
            $data[$k][] = $expect_score;
            
            $key = '本班平均分';
            $flash_data['field'][] = $k;
            $flash_data['data'][$key][$k] = $test_score;
            
            if ($max_score < $test_score)
            {
                $max_score = $test_score;
            }
            
            // 获取总体平均分
            foreach ($comparison_levels as $comparison_level)
            {
                $cl_name = ''; // 总体名称
                $avg_score = 0;
                $region_id = 0;
                $is_school = 0;
                $is_class = 0;
                
                switch ($comparison_level)
                {
                    case '-1':
                        $cl_name = '所有考试人员';
                        $region_id = 1;
                        break;
                    case '0': // 国家
                        $region_id = 1;
                        $cl_name = '全国';
                        break;
                    case '1': // 省份
                        $region_id = $class['province'];
                        $cl_name = $class['region_' . $class['province']];
                        break;
                    case '2': // 市
                        $region_id = $class['city'];
                        $cl_name = $class['region_' . $class['city']];
                        break;
                    case '3': // 县区
                        $region_id = $class['area'];
                        $cl_name = $class['region_' . $class['area']];
                        break;
                    case '100': // 学校
                        $region_id = $class['school_id'];
                        $cl_name = $class['school_name'];
                        $is_school = 1;
                        break;
                    default:
                        break;
                }
                
                // 总体平均分
                $region_k = $ques_id . '_' . $region_id . '_' . $is_school . '_' . $is_class;
                $avg_score = $ques_avg_score[$region_k];
                if ($max_score < $avg_score)
                {
                    $max_score = $avg_score;
                }
                
                $key = $cl_name . '平均分';
                $flash_data['data'][$key][$k]= round($avg_score, 2);
            }

            // 对应评价
            $tmp_desc_arr = array(
                    'id' => $k,
                    'full_score' => $full_score,
                    'test_score' => $test_score,
                    'expect_score' => $expect_score,
                    'difficulty' => $difficulty_level,
                    'difficulty_val' => $q_diffculty
            );

            $desc_data[] = $tmp_desc_arr;
        }
        
        $flash_data['field'] = array_values(array_unique($flash_data['field']));
        
        return array(
                'fields' => $fields,
                'data' => $data,
                'max_score' => $max_score,
                'flash_data' => $flash_data,
                'desc_data' => $desc_data
        );
    }
}