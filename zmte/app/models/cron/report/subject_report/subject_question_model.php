<?php
if (!defined('BASEPATH')) exit();

/**
 * 测评报告-学科-试题分析及与之对应的评价
 * @author TCG
 * @final 2015-07-21
 */
class Subject_question_model extends CI_Model
{
    private static $_db;
    private static $_data;
    
    public function __construct()
    {
        parent::__construct();
        
        self::$_db = Fn::db();
        
        $this->load->model('cron/report/subject_report/common_model');
    }
    
    /**
     * 获取 试题分析和评价
     * 
     * @param number $rule_id  评估规则ID
     * @param number $exam_id  考试学科
     * @param number $uid      考生ID
     */
    public function module_all($rule_id = 0, $exam_id = 0, $uid = 0)
    {
        $rule_id = intval($rule_id);
        $exam_id = intval($exam_id);
        $uid = intval($uid);
        if (!$rule_id || !$exam_id || !$uid)
        {
            return array();
        }
        
        // 列字段
        $fields = array(
                '总分',
                '得分' 
        );
        
        // 数据
        $data = array();
        
        // 数据
        $flash_data = array();
        
        // 分析信息
        $desc_data = array();
        
        // 获取该考生所考到的试卷题目
        $sql = "SELECT etpq.ques_id, etpq.etp_id, etp.paper_id
        	    FROM rd_exam_test_paper_question etpq
        	    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
        	    WHERE etp.exam_id={$exam_id} AND etp.uid={$uid} AND etp.etp_flag=2
        	    ";
        
        $result = self::$_db->fetchRow($sql);
        if (empty($result['ques_id']))
        {
            return array();
        }
        
        $paper_id = $result['paper_id'];
        $etp_id = $result['etp_id'];
        $ques_ids = @explode(',', trim($result['ques_id']));
        if (!is_array($ques_ids) || !$ques_ids)
        {
            return array();
        }
        
        // 对比等级(总体)
        $comparison_levels = $this->common_model->get_rule_comparison_level($rule_id);
        if (!$comparison_levels)
        {
            return array();
        }
        
        // 获取该学生所在区域
        $student = $this->common_model->get_student_info($uid);
        
        $t_ques_id = implode(',', $ques_ids);
        
        // 获取这些题目的得分
        $sql = "SELECT ques_id, SUM(full_score) AS full_score, SUM(test_score) AS test_score
                FROM rd_exam_test_result
                WHERE etp_id={$etp_id}
                GROUP BY ques_id
                ORDER BY etr_id ASC
                ";
        $ques_scores = self::$_db->fetchAssoc($sql);
        
        // 获取这些题目的难易度
        $sql = "SELECT rc.ques_id,rc.difficulty
                FROM rd_relate_class rc
                LEFT JOIN rd_exam e ON rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                AND rc.subject_type=e.subject_type
                WHERE e.exam_id={$exam_id} AND rc.ques_id IN({$t_ques_id})
                ";
        $ques_difficulties = self::$_db->fetchPairs($sql);
        
        //考试区域试题平均分
        $sql = "SELECT CONCAT(ques_id,'_',region_id,'_',is_school,'_',is_class),avg_score 
                FROM rd_summary_region_question 
                WHERE exam_id={$exam_id} AND ques_id IN({$t_ques_id}) AND is_class = 0
                GROUP BY exam_id,region_id,ques_id,is_school,is_class";
        $ques_avg_score = self::$_db->fetchPairs($sql);
        
        $auto_key = 0;
        foreach ($ques_ids as $ques_id)
        {
            $auto_key++;
            
            $ques_score = isset($ques_scores[$ques_id]) ? $ques_scores[$ques_id] : array(
                    'full_score' => 0,
                    'test_score' => 0 
            );
            
            $tmp_arr = array(
                    '总分' => $ques_score['full_score'],
                    '得分' => $ques_score['test_score'] 
            );
            
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
                        $region_id = $student['province'];
                        $cl_name = $student['region_' . $student['province']];
                        break;
                    case '2': // 市
                        $region_id = $student['city'];
                        $cl_name = $student['region_' . $student['city']];
                        break;
                    case '3': // 县区
                        $region_id = $student['area'];
                        $cl_name = $student['region_' . $student['area']];
                        break;
                    case '100': // 学校
                        $region_id = $student['school_id'];
                        $cl_name = $student['school_name'];
                        $is_school = 1;
                        break;
                    default:
                        break;
                }
                
                // 总体平均分
                $region_k = $ques_id . '_' . $region_id . '_' . $is_school.'_'.$is_class;
                $avg_score = $ques_avg_score[$region_k];
                $avg_score = $avg_score > $ques_score['full_score'] 
                                ? $ques_score['full_score'] : $avg_score;
                
                $k = $cl_name . '平均分';
                $fields[] = $k;
                $tmp_arr[$k] = $avg_score;
            }
            
            $k = "第 {$auto_key} 题";
            
            $data[$k] = $tmp_arr;
            
            $q_diffculty = isset($ques_difficulties[$ques_id]) ? $ques_difficulties[$ques_id] : 0;
            
            // 对应评价
            $tmp_desc_arr = array(
                    'id' => $k,
                    'full_score' => $ques_score['full_score'],
                    'test_score' => $ques_score['test_score'],
                    'expect_score' => round($ques_score['full_score'] * $ques_difficulties[$ques_id] / 100, 2),
                    'difficulty' => $q_diffculty ? $this->common_model->convert_question_difficulty($q_diffculty) : '--',
                    //'knowledge' => isset($relate_knowledges[$ques_id]) ? $relate_knowledges[$ques_id] : array(),
                    'difficulty_val' => $q_diffculty
            );
            
            $desc_data[] = $tmp_desc_arr;
        }
        
        return array(
                'fields' => array_values(array_unique($fields)),
                'data' => $data,
                'desc_data' => $desc_data
        );
    }
}