<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-学科-诊断及建议
 * @author TCG
 * @final 2015-07-21
 */
class Subject_suggest_model extends CI_Model 
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
	 * 总体水平等级和排名
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_id 考试学科
	 * @param number $uid 考生ID
	 * @note:
	 * 	返回数据格式：
	 * 	$data = array(
					'total' => '总人数', 
					'summary' => array(
									array('name' => '总体名称', 'total' => '人数'),
							),
					'win_percent' => '80', 
		);
	 */
	public function module_summary($rule_id = 0, $exam_id = 0, $uid = 0)
	{
		$rule_id = intval($rule_id);
		$exam_id = intval($exam_id);
		$uid = intval($uid);
		if (!$rule_id || !$exam_id || !$uid)
		{
			return array();
		}
		
		//对比等级(总体)
		$comparison_levels = $this->common_model->get_rule_comparison_level($rule_id);
		if (!$comparison_levels)
		{
			return array();		
		}

		//获取该学生所在区域
		$student = $this->common_model->get_student_info($uid);
		
		//数据
		/*
		 * 待获取数据：
		 * 	参考总人数
		 *  总体人数
		 *  比xx%的学生更出色： xx%=（总体人数 - 自己的排名） / 总体人数
		 */
		$data = array();
		
		$total = 0;
		$summary = array();
		$win_percent = 0;
		
		//获取区域考试总人数
		if (empty(self::$_data['region_totals'][$exam_id]))
		{
		    $sql = "SELECT CONCAT(region_id,'_',is_school,'_',is_class), 
		            COUNT(DISTINCT(uid)) AS total 
		            FROM rd_summary_region_student_rank 
		            WHERE exam_id={$exam_id}
		            GROUP BY exam_id, region_id, is_school, is_class";
		    
		    self::$_data['region_totals'][$exam_id] = self::$_db->fetchPairs($sql);
		}
		
		$data['total'] = self::$_data['region_totals'][$exam_id]['1_0_0'];
		
		//获取总体的参考人数
		foreach ($comparison_levels as $comparison_level)
		{
		    if ($comparison_level == -1)
		    {
		    	continue;
		    }
		    
			$cl_name = '';//总体名称
			$region_id = 1;
			$is_school = 0;
			$is_class = 0;
			
			switch ($comparison_level)
			{
				case '1'://省份
					$region_id = $student['province'];
					$cl_name = $student['region_'.$student['province']];
					break;
				case '2'://市
					$region_id = $student['city'];
					$cl_name = $student['region_'.$student['city']];
					break;
				case '3'://县区
					$region_id = $student['area'];
					$cl_name = $student['region_'.$student['area']];
					break;
				case '100'://学校
					$region_id = $student['school_id'];
					$cl_name = $student['school_name'];
					$is_school = 1;
					break;
				case '0'://国家
				    $region_id = 1;
				default:
					break;
			}
			
			//总体人数
			$region_k = $region_id . '_' . $is_school . '_' . $is_class;
			$total = self::$_data['region_totals'][$exam_id][$region_k];
			
			$k = $cl_name . '人数';
			$fields[] = $k;
			$summary[$k] = $total;
		}
		$data['summary'] = $summary;
		
		//获取我在本期考试中的排名
		$sql = "SELECT rank, test_score FROM rd_summary_region_student_rank WHERE exam_id={$exam_id} 
		        AND region_id=1 AND is_school=0 AND uid={$uid}";
		@list($rank, $test_score) = array_values(self::$_db->fetchRow($sql));

		$percent_tmp = $data['total'] <= 0 ? 0 : (($data['total'] - $rank + 1)/$data['total']*100);
		
		/* 手动消除100%排名 */
		if ( 99.5 <= $percent_tmp && $percent_tmp < 100)
		{
			$win_percent = 99;
		} 
		else 
		{
			$win_percent = $percent_tmp > 100 ? 100 : round($percent_tmp);
			$win_percent = $win_percent < 0 ? 0 : $win_percent;
		}
		
		$data['win_percent'] = $win_percent;

		//判断该学生是否为最后一名
		if (empty(self::$_data['last_rank'][$exam_id]))
		{
		    $sql = "SELECT MAX(rank) FROM rd_summary_region_student_rank
		            WHERE exam_id={$exam_id} AND region_id=1 AND is_school=0";
		    
		    self::$_data['last_rank'][$exam_id] = self::$_db->fetchOne($sql);
		}
		
		$last_rank = self::$_data['last_rank'][$exam_id];
		
		$data['is_last_rank'] = (($last_rank == $rank && $rank > 1) || $test_score == 0);
		
		$data['level'] = $this->common_model->convert_percent_level($win_percent);; 

		return $data;
	}
	
	/**
	 * 强弱点分布情况
	 * @param number $rule_id 规则id（无实际意义）
	 * @param number $exam_id 考试学科
	 * @param number $uid 考生ID
	 */
	public function module_application_situation($rule_id = 0, $exam_id = 0, $uid = 0, $is_constrast = false)
	{
		$exam_id = intval($exam_id);
		$uid = intval($uid);
		if (!$exam_id || !$uid)
		{
			return array();
		}
		
		//知识点名称对应id
		$knowledges = array();
		
		$data = array();
		
		//获取该学生所考到试题关联的答对 二级知识点 & 认知过程
		$sql = "SELECT ssk.knowledge_id, k.knowledge_name, spk.know_process_ques_id, 
		        ssk.know_process_ques_id AS right_know_process_ques_id
				FROM rd_summary_student_knowledge ssk
				LEFT JOIN rd_summary_paper_knowledge spk ON ssk.paper_id=spk.paper_id AND ssk.knowledge_id=spk.knowledge_id
				LEFT JOIN rd_knowledge k ON ssk.knowledge_id=k.id
				WHERE ssk.exam_id={$exam_id} AND ssk.uid={$uid} AND ssk.is_parent=0
				";
		$query = self::$_db->query($sql);
		
		//排序
		$field_sort = array();
		
		$know_processes = C('know_process');
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
			$know_process_ques_id = json_decode($item['know_process_ques_id'], true);//该二级知识点关联的 认知过程 试题
			$right_know_process_ques_id = json_decode($item['right_know_process_ques_id'], true);//考生答对情况
			$knowledge_name = $item['knowledge_name'];
			
			$knowledges[$knowledge_name] = $item['knowledge_id'];
			
			$know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
			$right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();
			
			//遍历认知过程，计算强弱点分布情况
			/*
			 * X = (答对题数/总题数)*100
			 */
			$t_strength = 0;
			$t_num = 0;
			foreach ($know_processes as $know_process => $kp_name) 
			{
				$all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
				$right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;
				
				$strength = !$all_questions ? '-1' : round(($right_questions/$all_questions)*100);
				
				$data[$knowledge_name]['kp_'.$know_process] = $strength;
				
				if ($strength > -1)
				{
				    $t_strength += $strength;
				    $t_num++;
				}
			}
			
			$field_sort[$knowledge_name] = round($t_strength / $t_num, 1);
		}
		
		if (!$is_constrast)
		{
		    $application_situation = array();
		
		    arsort($field_sort);

		    foreach ($field_sort as $key => $val)
		    {
		        $application_situation[$key] = $data[$key];
		    }
		
		    $data = $application_situation;
		}
		
		//对比考试id
		$contrast_exam_id = $this->common_model->contrast_exam_id($rule_id, $exam_id);
		
		//对比考试数据
		if ($contrast_exam_id && !$is_constrast) 
		{
		    if (empty(self::$_data['contrast_application_situation'][$rule_id][$exam_id]))
		    {
		        $contrast_exam = $this->module_application_situation($rule_id, $contrast_exam_id, $uid, true);
		        self::$_data['contrast_application_situation'][$rule_id][$exam_id] = $contrast_exam;
		    }
		    else
		    {
		        $contrast_exam = self::$_data['contrast_application_situation'][$rule_id][$exam_id];
		    }
		    
		    //排序
		    $field_sort = array();
		    //本次考试与上次考试有相同的知识点认知过程的知识点
		    $field_array = array();
		    
		    $contrast_exam = $contrast_exam['application_situation_percent'];
		    
		    //计算排序值
		    foreach ($contrast_exam as $key => $item)
		    {
		        if (!isset($data[$key]))
		        {
		            continue;
		        }
		        
	            $percent = 0;
	            $num = 0;
	            foreach ($data[$key] as $k => $val)
	            {
	                if ($val == -1
	                    || $item[$k] == -1)
	                {
	                    continue;
	                }
	                
                    $percent += $val;
                    $percent -= $item[$k];
                    $num++;
	            }
	            
                if ($num > 0)
                {
                    $field_array[] = $key;
                    $field_sort[$key] = round($percent / $num);
                }
		    }
		    
		    arsort($field_sort);
		    
		    $contrast_data = array();
		    foreach ($field_sort as $key => $val)
		    {
		        $contrast_data[$key][0] = array($key, '记忆(%)' , '理解(%)', '应用(%)');
		        $contrast_data[$key][1] = array(
		            '本次考试', 
		            $data[$key]['kp_1'], 
		            $data[$key]['kp_2'], 
		            $data[$key]['kp_3']
		        );
		        $contrast_data[$key][2] = array(
		            '上次考试', 
		            $contrast_exam[$key]['kp_1'], 
		            $contrast_exam[$key]['kp_2'], 
		            $contrast_exam[$key]['kp_3']
		        );
		        
		        if (in_array($key, $field_array))
		        {
		            unset($data[$key]);
		        }
		    }
		    
		    $return_data['contrast_data'] = $contrast_data;
		}
		
		$return_data['application_situation_percent'] = $data;
		$return_data['application_situation'] = $this->_convert_application_situation($data, ($contrast_exam_id || $is_constrast));
		$return_data['knowledges'] = $knowledges;
		
		return $return_data;
	}
	
	/**
	 * 为分布规则附加评语
	 */
	private function _convert_application_situation(&$data, $is_contrast = false)
	{
		foreach ($data as $key => $item)
		{
			$comment = '';
			$tmp_arr = array();
			foreach ($item as $kp => $strength)
			{
				list($name, $current_kp) = explode('_', $kp);
				
				$prev_strength = $current_kp == 1 ? 0 : $item['kp_'.($current_kp-1)];
				$first_strength = $item['kp_1'];
				$last_strength = $item['kp_3'];
				
				$comment = $this->_level_diff_note($first_strength, $prev_strength, $strength, $last_strength, $current_kp, $comment);
				$tmp_arr[$kp] = $this->_convert_strength_to_level($strength, $current_kp, $is_contrast);
			}
			
			$data[$key] = array_merge($item, $tmp_arr);
			$data[$key]['comment'] = $comment;
		}
		
		return $data;	
	}
	
	/**
	 * 将强弱分布值 转化为等级
	 * @param int $strength
	 * @param int $know_process
	 */
	private function _convert_strength_to_level($strength, $know_process, $is_contrast = false)
	{
		if ($strength < 0) return '0'; 
			
		if ($is_contrast) 
		{
		    //注意事项：电池分为5个小格子，代表5中程度。一格点亮到5格电量分别表示掌握的五个程度：0≤X＜20,20≤X＜40,40≤X≤60,60＜X≤80，80＜X≤100
		    $level = '';
		    
		    if ($strength >= 0 && $strength < 20)
		    {
		        $level = 1;
		    }
		    else if ($strength >= 20 && $strength < 40)
		    {
		        $level = 2;
		    }
		    else if ($strength >= 40 && $strength <= 60)
		    {
		        $level = 3;
		    }
		    else if ($strength > 60 && $strength <= 80)
		    {
		        $level = 4;
		    }
		    else if ($strength > 80 && $strength <= 100)
		    {
		        $level = 5;
		    }
		}
		else
		{
    		/*
    		 * level:
    		 * 	1:苗
    		 *  2：树
    		 *  3：果树
    		 */
    		$level = '';
    		switch ($know_process)
    		{
    			case '1'://记忆
    				if ($strength < 60) 
    				{
    					$level = '1';
    				}
    				elseif ($strength >= 60 && $strength <= 80) 
    				{
    					$level = '2';
    				}
    				else 
    				{
    					$level = '3';
    				}
    				break;
    			case '2'://理解
    				if ($strength < 40)
    				{
    					$level = '1';
    				}
    				elseif ($strength >= 40 && $strength <= 70)
    				{
    					$level = '2';
    				}
    				else
    				{
    					$level = '3';
    				}
    				break;
    			case '3'://应用
    				if ($strength < 20)
    				{
    					$level = '1';
    				}
    				elseif ($strength >= 20 && $strength <= 60)
    				{
    					$level = '2';
    				}
    				else
    				{
    					$level = '3';
    				}
    				break;
    			default:
    				break;
    		}	
		}

		return $level;
	}
	
	/**
	 * 计算强弱点相差等级的评语
	 * @param number $first_strength
	 * @param number $prev_strength
	 * @param number $current_strength
	 * @param number $last_strength
	 * @param number $current_kp
	 * @param string $current_note
	 * @return string
	 */
	private function _level_diff_note($first_strength = 0, $prev_strength = 0, $current_strength = 0, $last_strength = 0, $current_kp, $current_note = '')
	{
		if ($current_strength < 0)
		{
			return $current_note;
		}
		
		$first_level = $this->_convert_strength_to_level($first_strength, 1);
		$prev_level = $this->_convert_strength_to_level($prev_strength, $current_kp-1);
		$current_level = $this->_convert_strength_to_level($current_strength, $current_kp);
		$last_level = $this->_convert_strength_to_level($last_strength, 3);
		
		//评语
		$comments = array(
						//认知过程(记忆)
						'1' => array(//等级 评语（1：苗，2：树，3：果树）
									'1' => '基础有待夯实',
									'2' => '基础有待进一步提高',
									'3' => '基础扎实',
								),
						//认知过程(理解)
						'2' => array(//等级 评语
									'1' => '尚未理解',
									'2' => '部分理解',
									'3' => '理解深刻',
								),
						//认知过程(应用)
						'3' => array(//等级 评语
									'1' => '缺乏应用',
									'2' => '能简单应用',
									'3' => '能灵活应用',
								),
		);
		
		if ($current_kp == '1') 
		{
			return $comments[$current_kp][$current_level];
		}
		
	    if ($prev_level)
		{
		    $level_diff = $current_level - $prev_level;
		}
		else 
		{
		    $level_diff = $current_level - $first_level;
		}
		
		$comment = $comments[$current_kp][$current_level];

		if (strlen($current_note) > 0) 
		{
			/* 添加 “但” 字转折 */
			if ($level_diff >= 1) 
			{
				$current_note .= '，但' . $comment;
			} 
			else 
			{
				$current_note .= '，' . $comment;
			}
		} 
		else 
		{
			$current_note .= $comment;
		}

		/* 两个 “但” 字，前一个转换为 “虽” */
		if (substr_count($current_note, '但') >= 2) 
		{
			$current_note = substr_replace($current_note, '虽', strpos($current_note, '但'), strlen('但'));
		}
		
		return $current_note;
	}
	
	/**
	 * 目标匹配度 XX%
	 * note:
	 *   xx%=考生的学科总得分/期望得分（考生考的试卷每题期望得分累加）
	 *
	 * @param Number $rule_id   评估规则id
	 * @param number $exam_id   考试学科
	 * @param number $uid       学生id
	 * @param array  $desc_data 试题信息
	 */
	public function module_match_percent($rule_id = 0, $exam_id = 0, $uid = 0, $desc_data)
	{
	    $rule_id = intval($rule_id);
	    $exam_id = intval($exam_id);
	    $uid = intval($uid);
	    if (!$rule_id || !$exam_id || !$uid || !$desc_data)
	    {
	        return array();
	    }
	    
	    $match_percent = array();
	    
	    //学科总分/期望得分
	    if (isset(self::$_data['match_percent'][$exam_id]))
	    {
	        $data = self::$_data['match_percent'][$exam_id];
	    }
	
	    //获取该考生 本学科总得分
	    $test_scores = array('实际得分', 'low_test_score' => 0, 'mid_test_score' => 0, 'high_test_score' => 0);
	    
	    if (empty($data))
	    {
	        //期望得分
	        $expect_score = array('期望得分','low_expect_score' => 0,'mid_expect_score' => 0,'high_expect_score' => 0);
	        
	        //总分
	        $total_score = array('总分','low_total_score' => 0,'mid_total_score' => 0,'high_total_score' => 0);
	    }
	    
	    foreach ($desc_data as $key => &$val)
	    {
	        if (0 <= $val['difficulty_val'] && $val['difficulty_val'] < 30)
	        {
	            $test_scores['high_test_score'] += $val['test_score'];
	            
	            if (empty($data))
	            {
    	            $expect_score['high_expect_score'] += $val['full_score'] * $val['difficulty_val'] / 100;
    	            $total_score['high_total_score'] += $val['full_score'];
	            }
	        }
	
	        if (30 <= $val['difficulty_val'] && $val['difficulty_val'] <= 60)
	        {
	            $test_scores['mid_test_score'] += $val['test_score'];
	            
	            if (empty($data))
	            {
    	            $expect_score['mid_expect_score'] += $val['full_score'] * $val['difficulty_val'] / 100;
    	            $total_score['mid_total_score'] += $val['full_score'];
    	        }
	        }
	        if (60 < $val['difficulty_val'] && $val['difficulty_val'] <= 100)
	        {
	            $test_scores['low_test_score'] += $val['test_score'];
	            
	            if (empty($data))
	            {
    	            $expect_score['low_expect_score'] += $val['full_score'] * $val['difficulty_val'] / 100;
    	            $total_score['low_total_score'] += $val['full_score'];
	            }
	        }
	    }
	    
	    $test_scores['low_test_score'] = round($test_scores['low_test_score']);
	    $test_scores['mid_test_score'] = round($test_scores['mid_test_score']);
	    $test_scores['high_test_score'] = round($test_scores['high_test_score']);
	    
	    $test_score = $test_scores['low_test_score'] + $test_scores['mid_test_score'] + $test_scores['high_test_score'];
	    $test_scores['total_test_score'] = $test_score;
	    
	    if (empty($data))
	    {
    	    $expect_score['low_expect_score'] = round($expect_score['low_expect_score']);
    	    $expect_score['mid_expect_score'] = round($expect_score['mid_expect_score']);
    	    $expect_score['high_expect_score'] = round($expect_score['high_expect_score']);
    	    
    	    $total_expect_score = $expect_score['low_expect_score'] + $expect_score['mid_expect_score'] + $expect_score['high_expect_score'];
    	    $expect_score['total_expect_score'] = $total_expect_score;
    	    
    	    $total_score['low_total_score'] = round($total_score['low_total_score']);
    	    $total_score['mid_total_score'] = round($total_score['mid_total_score']);
    	    $total_score['high_total_score'] = round($total_score['high_total_score']);
    	
    	    if (empty(self::$_data['exam_total_scores'][$exam_id]))
    	    {
    	        $sql = "SELECT total_score FROM rd_exam WHERE exam_id = " . $exam_id;
    	        self::$_data['exam_total_scores'][$exam_id] = self::$_db->fetchOne($sql);
    	    }
    	    
    	    $total_score['total_score'] = self::$_data['exam_total_scores'][$exam_id];
    	    
    	    if ($total_score['total_score'] < $total_score['low_total_score'])
    	    {
    	        $total_score['low_total_score'] = $total_score['total_score'];
    	    }
    	    
    	    if ($total_score['total_score'] < $total_score['mid_total_score'])
    	    {
    	        $total_score['mid_total_score'] = $total_score['total_score'];
    	    }
    	    
    	    if ($total_score['total_score'] < $total_score['high_total_score'])
    	    {
    	        $total_score['high_total_score'] = $total_score['total_score'];
    	    }
	    }
	    else 
	    {
	        $total_score = $data['total_score'];
	        $expect_score = $data['expect_score'];
	        $total_expect_score = $expect_score['total_expect_score'];
	    }
	    
	    if (empty(self::$_data['match_percent'][$exam_id]))
	    {
	        self::$_data['match_percent'][$exam_id] = array(
	        	'total_score' => $total_score,
	            'expect_score' => $expect_score
	        );
	    }
	
	    $match_percent['data'][] = array('难易度','低','中','高','合计');
	    $match_percent['data'][] = $test_scores;
	    $match_percent['data'][] = $expect_score;
	    $match_percent['data'][] = $total_score;
	    
	    //对比考试id
	    $contrast_exam_id = $this->common_model->contrast_exam_id($rule_id, $exam_id);
	    //对比考试试卷id
	    $contrast_exam_id && $contrast_paper_id = $this->common_model->get_student_exam_paper($uid, $contrast_exam_id);
	    
	    //对比上次考试期次
	    if ($contrast_exam_id && $contrast_paper_id)
        {
            $sql = "SELECT COUNT(DISTINCT(uid)) FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND region_id = 1";
            $total = (int) self::$_db->fetchOne($sql);
            
            //获取我在本期考试中的排名
            $sql = "SELECT rank, test_score FROM rd_summary_region_student_rank 
                    WHERE exam_id={$exam_id}
                    AND region_id=1 AND is_school=0 AND uid={$uid}";
            @list($rank, $test_score) = array_values(self::$_db->fetchRow($sql));
            
            $percent_tmp = ($total <= 0 || $test_score <= 0)  ? 0 : 
                (($total - $rank + 1) / $total * 100);
            
            if (99.5 <= $percent_tmp && $percent_tmp < 100)
            {
                $win_percent = 99;
            }
            else
            {
                $win_percent = $percent_tmp > 100 ? 100 : round($percent_tmp);
                $win_percent = $win_percent < 0 ? 0 : $win_percent;
            }
            
            $old_match_percent = $match_percent;
            
            $match_percent['win_percent'] = $win_percent;
            $match_percent = $this->_contrast_match_percent($contrast_exam_id, $contrast_paper_id, $uid, $match_percent);
            $match_percent['old_match_percent'] = $old_match_percent;
            $match_percent['contrast_exam_id'] = $contrast_exam_id;
        }
        
        $percent = $total_expect_score <= 0 ? 0 : round(($test_score/$total_expect_score)*100);
        $match_percent['percent'] = $percent > 100 ? 100 : $percent;
        
	    return $match_percent;
	}
	
	/**
	 * 对比考试期次目标匹配度
	 * 
	 * @param int $contrast_exam_id
	 * @param int $contrast_paper_id
	 * @param int $uid
	 * @param array $match_percent
	 * @return array|Ambigous <string, number>
	 */
	private function _contrast_match_percent($contrast_exam_id, $contrast_paper_id, $uid, $match_percent)
	{
	    $contrast_exam_id = intval($contrast_exam_id);
	    $contrast_paper_id = intval($contrast_paper_id);
	    $uid = intval($uid);
	    if (!$contrast_exam_id || !$contrast_paper_id || !$uid || !$match_percent)
	    {
	        return $match_percent;
	    }
	    
	    $sql = "SELECT COUNT(DISTINCT(uid)) FROM rd_summary_region_student_rank
                WHERE exam_id = $contrast_exam_id AND region_id = 1";
	    $total = (int) self::$_db->fetchOne($sql);
	    
	    //获取我在上期考试中的排名
	    $sql = "SELECT rank, test_score FROM rd_summary_region_student_rank
	            WHERE exam_id={$contrast_exam_id}
                AND region_id=1 AND is_school=0 AND uid={$uid}";
	    @list($rank, $test_score) = array_values(self::$_db->fetchRow($sql));
	    
	    $percent_tmp = ($total <= 0 || $test_score <= 0)  ? 0 :
	           (($total - $rank + 1)/ $total * 100);
	    
	    if (99.5 <= $percent_tmp && $percent_tmp < 100)
	    {
	        $win_percent = 99;
	    }
	    else
	    {
	        $win_percent = $percent_tmp > 100 ? 100 : round($percent_tmp);
	        $win_percent = $win_percent < 0 ? 0 : $win_percent;
	    }
	    
	    $match_percents = array();
	    $match_percents['data'][] = array('得分率','低难度','中难度','高难度', '优胜率');
	    
	    //本次考试各个难度得分率
	    $match_percents['data'][1][] = '本次考试';
	    if ($match_percent['data'][3]['low_total_score'])
	    {
	        $percent = round($match_percent['data'][1]['low_test_score']/$match_percent['data'][3]['low_total_score']*100);
	        $match_percents['data'][1][] = $percent > 100 ? 100 : $percent;
	    }
	    else
	    {
	        $match_percents['data'][1][] = -1;
	    }
	    
	    if ($match_percent['data'][3]['mid_total_score'])
	    {
	        $percent = round($match_percent['data'][1]['mid_test_score']/$match_percent['data'][3]['mid_total_score']*100);
	        $match_percents['data'][1][] = $percent > 100 ? 100 : $percent;
	    }
	    else
	    {
	        $match_percents['data'][1][] = -1;
	    }
	    
	    if ($match_percent['data'][3]['high_total_score'])
	    {
	        $percent = round($match_percent['data'][1]['high_test_score']/$match_percent['data'][3]['high_total_score']*100);
	        $match_percents['data'][1][] = $percent > 100 ? 100 : $percent;
	    }
	    else
	    {
	        $match_percents['data'][1][] = -1;
	    }
	    
	    $match_percents['data'][1][] = $match_percent['win_percent'];
	    
	    //上次考试各个难度得分率
	    $match_percents['data'][2][] = '上次考试';
	    
	    //对比考试各个难度试题总分
	    if (empty(self::$_data['contrast_ques_total_score'][$rule_id][$contrast_exam_id][$contrast_paper_id]))
	    {
	        $sql = "SELECT q_type, low_ques_id, mid_ques_id, high_ques_id
        	        FROM rd_summary_paper_difficulty
        	        WHERE paper_id = $contrast_paper_id";
	        $stmt = self::$_db->query($sql);
	        $ques_ids = array();
	        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
	        {
    	        if ($item['low_ques_id'])
    	        {
	                    $ques_ids[0] .= ',' . $item['low_ques_id'];
	            }
	    
	            if ($item['mid_ques_id'])
	            {
                    $ques_ids[1] .= ',' . $item['mid_ques_id'];
                }
	    
                if ($item['high_ques_id'])
                {
                    $ques_ids[2] .= ',' . $item['high_ques_id'];
                }
            }
	    
            $total_score = array();
            ksort($ques_ids);
            foreach ($ques_ids as $key => $ques_id)
            {
                $ques_id = substr($ques_id, 1);
                
                $sql = "SELECT SUM(full_score) FROM rd_exam_test_result
    			        WHERE exam_id={$contrast_exam_id} AND (ques_id IN({$ques_id}) OR sub_ques_id IN({$ques_id}))
		                AND uid = $uid
        			    ";
                $total_score[] = self::$_db->fetchOne($sql);
            }
            
            self::$_data['contrast_ques_total_score'][$rule_id][$contrast_exam_id][$contrast_paper_id] = $total_score;
        }
        else
        {
            $total_score = self::$_data['contrast_ques_total_score'][$rule_id][$contrast_exam_id][$contrast_paper_id];
        }
        
	    $sql = "SELECT SUM(low_test_score) low_test_score, SUM(mid_test_score) mid_test_score,
	           SUM(high_test_score) high_test_score FROM rd_summary_student_difficulty
	           WHERE exam_id = $contrast_exam_id AND paper_id = $contrast_paper_id AND uid = $uid";
	    $test_score = self::$_db->fetchRow($sql);
	    
	    $i = 0;
        foreach ($test_score as $key => $val)
        {
            $percent = $total_score[$i] ? round($val/$total_score[$i]*100) : '-1';
            $match_percents['data'][2][] = $percent > 100 ? 100 : $percent;
            $i++;
	    }
	    
	    $match_percents['data'][2][] = $win_percent;
	    
	    return $match_percents;
	}
	
	/**
	 * 目标匹配度 XX%
	 * note:
	 *   xx%=班级的学科总得分/期望得分（班级考的试卷每题期望得分累加）
	 *
	 * @param number $exam_id   考试学科
	 * @param number $uid    学生id
	 */
	public function module_technology_match_percent($exam_pid = 0, $uid = 0)
	{
	    $exam_pid = intval($exam_pid);
	    $uid = intval($uid);
	    if (!$exam_pid || !$uid)
	    {
	        return array();
	    }
	     
	    $match_percent = array();
	     
	    $student = $this->common_model->get_student_info($uid);
	     
	    // 获取该班级所考到的试卷题目
	    $sql = "SELECT etpq.ques_id
        	    FROM rd_exam_test_paper_question etpq
        	    LEFT JOIN rd_exam_test_paper etp ON etpq.etp_id=etp.etp_id
        	    WHERE etp.exam_pid={$exam_pid} AND etp.uid={$uid} AND etp.etp_flag=2
        	    AND subject_id IN (12, 18)
        	    ";
	    $ques_id = implode(',', self::$_db->fetchCol($sql));
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
        	    WHERE e.exam_id={$exam_pid} AND rc.ques_id IN({$ques_id})
        	    ";
	    $ques_difficulties = self::$_db->fetchPairs($sql);
	     
	    //本次考试试题得分情况
	    $sql = "SELECT ques_id,SUM(full_score) AS full_score, SUM(test_score) AS test_score
        	    FROM rd_exam_test_result
        	    WHERE exam_pid = $exam_pid AND uid = $uid
        	    AND ques_id IN ($ques_id) 
	            GROUP BY ques_id";
	    $exam_score = self::$_db->fetchAssoc($sql);
	     
	    $data = array(
	        array('难易度', '低', '中', '高', '合计'),
	        array('实际得分 ', -1, -1, -1, 0),
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
	         
	        $full_score = 0;
	        $test_score = 0;
	        if (isset($exam_score[$ques_id]))
	        {
	            $full_score = round($exam_score[$ques_id]['full_score'], 2);
	            $test_score = round($exam_score[$ques_id]['test_score'], 2);
	        }
	         
	        $d_level = $this->common_model->convert_question_difficulty($q_diffculty);
	         
	        $expect_score = $full_score * $q_diffculty / 100;
	         
	        $k = $level[$d_level];
	         
	        if ($data[1][$k] == -1)
	        {
	            $data[1][$k] = 0;
	        }
	        $data[1][$k] += $test_score;
	        $data[1][4] += $test_score;
	
	        if ($data[2][$k] == -1)
	        {
	            $data[2][$k] = 0;
	        }
	        $data[2][$k] += $expect_score;
	        $data[2][4] += $expect_score;
	
	        if ($data[3][$k] == -1)
	        {
	            $data[3][$k] = 0;
	        }
	        $data[3][$k] += $full_score;
	        $data[3][4] += $full_score;
	    }
	    
	    return array(
	        'data' => $data,
	        'percent' => round(end($data[1]) / end($data[2]) * 100)
	    );
	}
}
