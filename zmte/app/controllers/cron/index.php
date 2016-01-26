<?php !defined('BASEPATH') && exit();

/**
 * cron
 * @author TCG
 * @create 2013-12-02
 */
class Index extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        $this->load->library('cron_schedule');
    }
   
    


    public function student_question_score()
    {
        $file_path = realpath(dirname(APPPATH)) . "/cache/";
        
        $this->db->trans_start();
        
        $start_time = microtime(true);
        
        $sql = "select a.* from tmp_table9700 a where a.s=0  limit 10000 ";
        
        $res = $this->db->query($sql)->result_array();
        
        $ps1 = array();
        
        foreach ($res as $key=>$value)
        {
            if ($value['v'] < 4)
            {
                continue;
            }
            $place_id = $value['place_id'];
            
            $subject_id = $value['subject_id'];
            //sql文件名称
            $p_s = $file_path . date('Ymd').'_' . $place_id . '_' . $subject_id .
                     '.text';
            //更新处理状态为2
            $sql = "UPDATE tmp_table9700 SET s=2  WHERE v='{$value['v']}' and place_id={$place_id} AND subject_id={$subject_id}";

            $this->db->query($sql);
            
            // 获取题号
            if (! $ps1[$place_id][$subject_id][1])
            {
                $sql = "SELECT k FROM tmp_table9700 WHERE v=1 and place_id='{$place_id}'
                    and subject_id='{$subject_id}'";
                
                $res = $this->db->query($sql)->row_array();
                
                $kk = $res['k'];
                
                $kkk = json_decode(base64_decode($kk), true);
                
                $ps1[$place_id][$subject_id][1] = $kkk;
                
            }
            else
            {
                $kkk = $ps1[$place_id][$subject_id][1];
            }
            
            // 获取分数
            if (! $ps1[$place_id][$subject_id][3])
            {
                
                $sql = "SELECT k FROM tmp_table9700 WHERE v=3 and place_id='{$place_id}'
            and subject_id='{$subject_id}'";
                
                $res = $this->db->query($sql)->row_array();
                
                $tt = $res['k'];
                
                $ttt = json_decode(base64_decode($tt), true);
                
                $ps1[$place_id][$subject_id][3] = $ttt;
                
            }
            else
            {
                $ttt = $ps1[$place_id][$subject_id][3];
            }
            
            // 获取名称
            $value_a = json_decode(base64_decode($value['k']), true);
            
            $uid = $value['uid'];
            
            $sql = "SELECT etp_id FROM {pre}exam_test_paper WHERE subject_id = '$subject_id'
            AND place_id = '{$place_id}' AND uid = '{$uid}'";
            
            $res = $this->db->query($sql)->row_array();
            
            $etp_id = $res['etp_id'];
            /**
             * 判断数据是否已存在数据库中
             */
            if (! $etp_id)
            {
                continue;
            }
            
            foreach ($value_a as $k=>$v)
            {
                if ($k < 5) continue;
                
                if ($k == 5)
                {
                    $sql = "UPDATE rd_exam_test_paper SET test_score = '{$v}',etp_flag=2 WHERE etp_id={$etp_id}";
                    file_put_contents($p_s, $sql . ';' . PHP_EOL, FILE_APPEND);
                    //$this->db->query($sql);
                }
                
                if ($k > 7)
                {
                    if ($v == 0)
                    {
                        $sql = "SELECT etr.etr_id FROM {pre}exam_test_result etr where IF(etr.sub_ques_id>0,etr.sub_ques_id,etr.ques_id)= '{$kkk[$k]}'
            AND  etr.etp_id={$etp_id}";
                        
                        $res = $this->db->query($sql)->row_array();
                        
                        $etr_id = $res['etr_id'];
                        
                        if (! $etr_id)
                        {
                            continue;
                        }
                        
                        $sql = "UPDATE rd_exam_test_result etr SET etr.test_score='{$v}',etr.full_score='{$ttt[$k]}' WHERE   etr.etr_id={$etr_id}";

                        file_put_contents($p_s, $sql . ';' . PHP_EOL, 
                                FILE_APPEND);
                        //$this->db->query($sql);
                    }
                }
             }
             
            $sql = "UPDATE tmp_table9700 SET s=1  WHERE v='{$value['v']}' and place_id={$place_id} AND subject_id={$subject_id}";
            
            $this->db->query($sql);
        }
        
        $this->db->trans_complete();
    }
        
        
        

    
    
    public function index()
    {
    	//$this->load->model('admin/question_model');
    	$this->load->model('exam/exam_paper_model');
    	$question_model = new QuestionModel();

    	$models = array(
    				'question_model' => $question_model,
    				'exam_paper_model' => $this->exam_paper_model,
    	);
    	
        $this->cron_schedule->dispatch($models);
    }
    
    /**
     * 测试生成考试成绩
     */
    public function general_test_score($exam_pid = 0)
    {
    	require_once (APPPATH.'cron/exam.php');
    	
    	$exam_cron = new Exam();
    	try {
    		$exam_cron->cal_test_score($exam_pid);
    	} catch(Exception $e) {
    		throw new Exception('更新 考生试卷 得分失败，更新字段：' . $t_test_paper . '->test_score, Error:' . $e->getMessage());
    	}
    }
    
    /**
     * 生成样卷考试成绩
     */
    function demo_report(){
    	//$this->load->model('admin/exam_test_paper_model', 'etp_model');
    	$this->load->model('demo/report/general_model');
    	
    	$result = ExamTestPaperModel::get_test_paper_list(array('exam_pid'=>1),'','',NULL,'exam_pid,exam_id,uid,subject_id');
    	if(!empty($result)){
    		$unset_data = array('report_mark');
    		foreach ($result as $key=>$val){
    			$report_mark = $val['exam_pid'] . '_' . $val['subject_id'] . '_' . $val['uid'] . '_' . $val['exam_id']."<br>";
    			
    			$this->session->set_userdata('report_mark', $report_mark);
    			
    			$this->general_model->general_report();
    			
    			$this->session->unset_userdata($unset_data);
    		}
    	}
    }
    
    /**
     * 测试生成汇总数据
     */
    public function test()
    {
    	$exam_pid = 18;
    	$this->load->model('cron/summary_paper_model', 'paper_model');
    	$this->paper_model->summary_paper_knowledge();//试卷关联知识点
    	$this->paper_model->summary_paper_method_tactic();//试卷关联方法策略
    	$this->paper_model->summary_paper_difficulty();//试卷关联试题难易度
    	$this->paper_model->summary_paper_question($exam_pid);//试卷关联试题
    	
    	$this->load->model('cron/summary_region_model', 'region_model');
    	$this->region_model->summary_region_knowledge($exam_pid);//地域关联一级知识点
    	$this->region_model->summary_region_method_tactic($exam_pid);//地域关联方法策略
    	$this->region_model->summary_region_difficulty($exam_pid);//地域关联题型难易度
    	$this->region_model->summary_region_question($exam_pid);//地域关联试题
    	$this->region_model->summary_region_student_rank($exam_pid);//地域关联考生排名
    	$this->region_model->summary_region_subject($exam_pid);//地域关联考试人数
    	
    	$this->load->model('cron/summary_student_model', 'student_model');
    	$this->student_model->summary_student_knowledge($exam_pid);//考生关联一级知识点
    	$this->student_model->summary_student_method_tactic($exam_pid);//考生关联方法策略
    	$this->student_model->summary_student_difficulty($exam_pid);//考生关联题型难易度
    	$this->student_model->summary_student_subject_method_tactic($exam_pid);//考生-学科-方法策略
    }
    
    /**
     * 生成pdf数据 测试
     */
    public function test_general()
    {
    	$rule_id = 2;
    	$exam_pid = 18;
    	$exam_id = 19;
    	$uid = 21;
    	
    	//========================对比信息========================//
//     	$this->load->model('cron/report/subject_comparison_info_model', 'sci_model');
//     	$data = $this->sci_model->module_knowledge($rule_id, $exam_id, $uid);//生成 知识点 模块 数据
//     	$data = $this->sci_model->module_method_tactic($rule_id, $exam_id, $uid);//生成 方法策略 模块 数据
//     	$data = $this->sci_model->module_difficulty($rule_id, $exam_id, $uid);//生成 题型难易度 模块 数据

    	
//     	$this->load->model('cron/report/subject_three_dimensional_model', 'std_model');
//     	$data = $this->std_model->module_knowledge($rule_id, $exam_id, $uid);//生成 知识点 模块 数据
//     	$data = $this->std_model->module_method_tactic($rule_id, $exam_id, $uid);//生成 方法策略  模块 数据
//     	$data = $this->std_model->module_difficulty($rule_id, $exam_id, $uid);//生成 试题难易度  模块 数据

//     	$this->load->model('cron/report/subject_question_model', 'sq_model');
//     	$data = $this->sq_model->get_all($rule_id, $exam_id, $uid);//生成 知识点 模块 数据

    	//$this->load->model('cron/report/subject_suggest_model', 'ss_model');
//     	$data = $this->ss_model->module_summary($rule_id, $exam_id, $uid);//生成 总体水平等级和排名  数据
//     	$data = $this->ss_model->module_application_situation($exam_id, $uid);//生成 总体水平等级和排名  数据
//     	$data = $this->ss_model->target_match_percent($exam_id, $uid);//生成 目标匹配度  数据

    	//
    	$this->load->model('cron/report/complex_model', 'c_model');
//     	$data = $this->c_model->module_method_tactic($exam_pid, $uid);//各学科综合的方法策略运用情况
//     	$data = $this->c_model->module_subject_relative_position($rule_id, $exam_pid, $uid);//各学科在总体的相对位置
    	
    	$data = $this->c_model->module_level_percent ( $rule_id, $exam_pid, $uid );//匹配度 XX%
    	pr($data, 1);
    }
    
    public function question_word_num()
    {
    	$questions = $this->db->get_where('question')->result_array();
    	$update_data = array();
    	foreach ($questions as $key => $question)
    	{
    	    $word_num = str_word_count(str_replace(array("&nbsp;",'&#39;'),array('',''),
    	            strip_tags(htmlspecialchars_decode($question['title']))));
    	    $update_data[$key]['word_num'] = $word_num;
    	    $update_data[$key]['ques_id'] = $question['ques_id'];
    	}
    	
    	$update_data && $this->db->update_batch('question', $update_data, 'ques_id');
    } 
}
