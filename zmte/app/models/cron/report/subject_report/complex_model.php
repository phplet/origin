<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告-综合-诊断及建议
 * @author TCG
 * @final 2017-07-21
 */
class Complex_model extends CI_Model 
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
     * 综合各学科低于试卷难度水平答对率和所有考试人员的答对率对比
     *
     * @param number $exam_id
     *            考试学科
     * @param number $uid
     *            考生ID
     * @author Zh
     */
    public function module_level_percent($rule_id = 0, $exam_pid = 0, $uid = 0, $include_subject = array())
    {
        $exam_pid = intval($exam_pid);
        $uid = intval($uid);
        if (!$exam_pid || !$uid)
        {
            return array();
        }
        
        if (!empty($include_subject)) 
        {
            $include_subject_str = " AND subject_id IN (".implode(',', $include_subject).")";
        }
        else 
        {
            $include_subject_str = " ";
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
	 * 总结各学科的方法策略运用情况
	 * @param number $exam_id 考试学科
	 * @param number $uid 考生ID
	 */
	public function module_method_tactic($rule_id = 0, $exam_pid = 0, $uid = 0)
	{
		$exam_pid = intval($exam_pid);
		$uid = intval($uid);
		if (!$exam_pid || !$uid)
		{
			return array();
		}
		
		$data = array();
		
		/*
		 * 获取该考生的各学科 方法策略运用情况
		 */
		$sql = "SELECT * FROM v_student_subject_usage_method_tactic_situation
				WHERE exam_pid={$exam_pid} AND uid={$uid}";
		
		$query = self::$_db->query($sql);
		
		$mt_subjects = array();
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
			$subject_name = $item['subject_name'];
			$mt_name = $item['name'];
			$usage = $item['usage'];
			
		    $data['subjects'][] = $item['subject_name'];
			$data['method_tactics'][] = $mt_name;
			$mt_subjects[$mt_name][$item['subject_name']] = $usage;
			$data['data'][$item['subject_name']][$mt_name] = round($usage);
		}
		
		if ($data)
		{
			$data['subjects'] = array_values(array_unique($data['subjects']));
			$data['method_tactics'] = array_values(array_unique($data['method_tactics']));
			
			//补齐flash数据
			foreach ($data['method_tactics'] as $mt_name)
			{
				foreach ($data['data'] as $subject_name => $item)
				{
					if (empty($item[$mt_name]))
					{
						$data['data'][$subject_name][$mt_name] = 'null';
					}
				}
			}
			
			//将数据进行排序
			asort($data['method_tactics'], SORT_STRING);
			foreach ($data['data'] as $subject_name => $item)
			{
				ksort($item, SORT_STRING);
				$data['data'][$subject_name] = $item;
			}
			
			ksort($mt_subjects, SORT_STRING);
			
			$data['method_tactics'] = array_values($data['method_tactics']);
			
			
			$data['comment'] = $this->_convert_usage_comment($mt_subjects);
		}
		
		return $data;
	}
	
	/*
	 * 将方法策略运用情况 进行 评价
	 */
	private function _convert_usage_comment(&$mt_subjects)
	{
		$comments = array();
		$comment_case1 = array();
		$comment_case2 = array();
		$comment_case3 = array();
		foreach ($mt_subjects as $mt_name => $item)
		{
			//case1: 是否都小于0
			$all_less_than_0 = true;
			
			//case2: 有大于0，并且小于0，而且差距>=10
			$case2_data = array();
			 
			//case3: 都向上，柱距>=20
			$case3_data = array(); 
			//for case3
			$all_more_than_0 = true;
			
			$tmp_item = array_values($item);
			arsort($tmp_item);
			$tmp_item = array_values($tmp_item);
			
			$max_usage = $tmp_item[0];
			foreach ($item as $subject_name=>$usage) 
			{
				//case1
				if ($usage > 0)
				{
					$all_less_than_0 = false;
				}
				else
				{
				    $all_more_than_0 = false;
				}
				
				//case2
				if ($usage >= 0)
				{
					$case2_data['big'][$subject_name] = $usage;
				}
				else 
				{
					$case2_data['small'][$subject_name] = $usage;
				}
				
				//case 3
				if (abs($max_usage - $usage) >= 20)
				{
					$case3_data[] = $subject_name;
				}
			}
			
			//所有方法策略柱子向下，即<=0
			if ($all_less_than_0)
			{
				$comment_case1[] = $mt_name;
				unset($mt_subjects[$mt_name]);
			}
			
			//方法策略柱子有上有下并且柱子的差距>=10
			if (isset($case2_data['small']) && isset($case2_data['big']) )
			{
				arsort($case2_data['big']);//将大于0 的降序排，取最大值
				asort($case2_data['small']);//将小于0 的升序排，取最小值
				
				$tmp_big = array_values($case2_data['big']);
				$tmp_small = array_values($case2_data['small']);
				
				$max = $tmp_big[0];
				$min = $tmp_small[0];
				
				if (($max - $min) >= 10)
				{
					$big_s_name = array_keys($case2_data['big']);
					$small_s_name = array_keys($case2_data['small']);
					
					foreach ($small_s_name as $subj_name)
					{
					    foreach ($big_s_name as $big_subj_name)
					    {
					        $comment_case2[$subj_name][$big_subj_name][] = $mt_name;
					    }
					}
					unset($mt_subjects[$mt_name]);
				}
			}
			
			//都向上，柱距>=20
			if ($all_more_than_0 && count($case3_data))
			{
			    foreach ($case3_data as $s_names)
			    {
			        $comment_case3[$s_names][] = $mt_name;
			        unset($mt_subjects[$mt_name]);
			    }
				
			}
		}
		
		//case 1
		if ($comment_case1)
		{
		    $c1 = array();
		    foreach ($comment_case1 as $item)
		    {
		        $c1[] = "“{$item}”";
		    }
		    ($c1) && $comments[] = implode('、', $c1) . '有待加强。';
		}
		
		//case 2
		if ($comment_case2)
		{
		    $comment_case2_str = '';
		    foreach ($comment_case2 as $s_s_name => $b_s_name)
		    {
		        $b_s_name = array_unique($b_s_name);
		    	foreach ($b_s_name as $b_name => $mt)
		    	{
		    	    $mt_str = implode('、',$mt);
		    	    $comment_case2_str .= "在{$b_name}学科中已经能恰当应用{$mt_str}，";
		    	}
		    	$comment_case2_str .= "希望能把这个能力也迁移到{$s_s_name}学科上；";
		    }
		    
		    if ($comment_case2_str)
		    {
		        $comment_case2_str = rtrim($comment_case2_str,"；") . "。";
		        $comments[] = $comment_case2_str;
		    }
		}
		
		//case 3
		if ($comment_case3)
		{
		    $comment_case3_str = '';
		    foreach ($comment_case3 as $key=>$item1)
		    {
		        $comment_case3_str .= "把" . implode('、', $item1) . "迁移到{$key}学科上，";
		    }
		    
		    if ($comment_case3_str)
		    {
		        $comment_case3_str = "如果可以{$comment_case3_str}你将会取得更优异的成绩。";
		        $comments[] = $comment_case3_str;
		    }
		}
		
		//其他情况
		if (count($mt_subjects))
		{
			$s_names = array();
			foreach ($mt_subjects as $mt_name=>$item)
			{
				foreach ($item as $k=>$v)
				{
					$s_names[$k][] = $mt_name;
				}
			}
			
			$other_str = "";
			foreach ($s_names as $s_name=>$val)
			{
				$other_str .= "将". implode('、', $val) . "巧妙地运用在{$s_name}学科中，";
			}
			if ($other_str)
			{
			    $other_str = '你已经能够' . $other_str . '希望你在以后的学习中再接再厉。';
			    $comments[] = $other_str;
			}
			
		}
		
		return $comments;
	}
	
	/**
	 * 旧的各学科在总体的相对位置
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_pid 考试期次
	 * @param number $uid 考生ID
	 */
	public function module_subject_relative_position2($rule_id = 0, $exam_pid = 0, $uid = 0)
	{
	    return $this->module_subject_relative_position($rule_id, $exam_pid, $uid);
	}
	
	/**
	 * 各学科在总体的相对位置
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_pid 考试期次
	 * @param number $uid 考生ID
	 * @param array  $include_subject 学科信息
	 */
	public function module_subject_relative_position($rule_id = 0, $exam_pid = 0, $uid = 0, $include_subject = array())
	{
		$rule_id = intval($rule_id);
		$exam_pid = intval($exam_pid);
		$uid = intval($uid);
		if (!$rule_id || !$exam_pid || !$uid)
		{
			return array();
		}
		
		$data = array();
		
		//对比等级(总体)
	    $comparison_levels = $this->common_model->get_rule_comparison_level($rule_id);
	    if (!$comparison_levels)
	    {
	        return array();
	    }
		
		//获取该学生所在区域
		$student = $this->common_model->get_student_info($uid);
		
		//获取该期次所考到的所有学科
		$all_subject = C('subject');
		
		$subjects = array();
		$exam_subjects = array();
		
		if (empty(self::$_data['exam_subject'][$exam_pid]))
		{
		    $sql = "SELECT subject_id, exam_id FROM rd_exam
        		    WHERE exam_pid={$exam_pid}
        		    GROUP BY exam_id
        		    ORDER BY subject_id ASC
        		    ";
		    
		    self::$_data['exam_subject'][$exam_pid] = self::$_db->fetchAll($sql);
		}
		
		$result = self::$_data['exam_subject'][$exam_pid];
		
		foreach ($result as $item)
		{
		    if ($include_subject 
		          && !in_array($item['subject_id'], $include_subject))
		    {
		        continue;
		    }
		    
			$subjects[$item['subject_id']] = $all_subject[$item['subject_id']];
			$exam_subjects[$item['exam_id']] = $item['subject_id'];
		}
		
		$data['subjects'] = array_values($subjects);
				
		//总体得分
		$is_all_last_rank = true;//该考生是否排在最后一名
		$is_all_zero_score = true;//是否全考零分
		$is_only_one_tested = true;//是否只有一个人考试
		
		if (empty(self::$_data['total_ranks'][$exam_pid]))
		{
		    $sql = "SELECT CONCAT(exam_id,'_',region_id), COUNT(uid) AS total 
		            FROM rd_summary_region_student_rank 
		            WHERE exam_pid=$exam_pid
                    GROUP BY exam_id, region_id";
		    
		    self::$_data['total_ranks'][$exam_pid] = self::$_db->fetchPairs($sql);
		}
		
		$total_ranks = self::$_data['total_ranks'][$exam_pid];
		
		if ($include_subject)
		{
		    $comparison_levels = array(-1);
		}
		else
		{
		    sort($comparison_levels);
		}
		
	    foreach ($comparison_levels as $comparison_level)
	    {
	        $cl_name = '';//总体名称
	        $region_id = 1;
	        
	        switch ($comparison_level)
	        {
	            case '-1'://所有考试人员
	                $cl_name = "所有考试人员";
	                break;
	            case '0'://国家
	                $cl_name = '全国';
	                break;
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
	                break;
	            default:
	                break;
	        }
	        
			//总体人数
			$summary = array();
			foreach ($exam_subjects as $exam_id => $val)
			{
			    if (isset($total_ranks[$exam_id . '_' . $region_id]))
			    {
			        $summary[$exam_id] = $total_ranks[$exam_id . '_' . $region_id];
			    }
			}
			
			//获取每个学科最后一名
			if (empty(self::$_data['last_rank'][$exam_pid][$region_id]))
			{
                $sql = "SELECT exam_id, MAX(rank) AS rank FROM rd_summary_region_student_rank
                        WHERE exam_pid=$exam_pid AND region_id = $region_id GROUP BY exam_id";
                
                self::$_data['last_rank'][$exam_pid][$region_id] = self::$_db->fetchPairs($sql);
			}
			
			$last_ranks = self::$_data['last_rank'][$exam_pid][$region_id];
			
			
			//我在总体中的排名
			$sql = "SELECT exam_id, rank, test_score FROM rd_summary_region_student_rank 
			        WHERE exam_pid = $exam_pid AND region_id = $region_id AND uid = $uid";
			$query = self::$_db->query($sql);
			
			while ($item = $query->fetch(PDO::FETCH_ASSOC))
			{
				$rank = $item['rank'];
				$test_score = $item['test_score'];
				
				if ($test_score > 0)
				{
					$is_all_zero_score = false;
				}
				
				if ($rank != $last_ranks[$item['exam_id']])
				{
					$is_all_last_rank = false;
				}
				
				/*
				 * 计算相对位置 xx = (总体人数-自己排名+1)/总体人数
				 */
				$exam_id = $item['exam_id'];
				
				if (empty($subjects[$exam_subjects[$exam_id]]))
				{
				    continue;
				}
				
				$percent = 0;
				if ($test_score > 0 && !empty($summary[$exam_id])) 
				{
					$is_only_one_tested = false;
					
					$total = $summary[$exam_id];
					$percent = round(($total - $rank + 1) / $total, 2);
					$percent = $percent > 1 ? 1 : $percent;
				}
				
				$data['data'][$cl_name][$subjects[$exam_subjects[$exam_id]]] = $percent;
			}
		}
		
		//只有一个人考
		$tip = '很遗憾您发挥得不太理想。在本次考试中，每门学科的排名都为最后。请调整状态，继续努力！';
		$show_tip = false;
		if ($is_only_one_tested && $is_all_zero_score)
		{
			$show_tip = true;
		}
		
		if ($is_all_last_rank)
        {
        	$show_tip = true;
        }
		
		if (isset($data['data'][$cl_name]))
		{
		    $data['subjects'] = array_keys($data['data'][$cl_name]);
		}
		
		//x选3
		if ($include_subject && !empty($data['data']) && !$show_tip)
		{
		    arsort($data['data'][$cl_name]);
			
			$data['subjects'] = array_keys($data['data'][$cl_name]);
			
		    $advantage_subject = array();//比较有优势的学科
		    $compare_subject = array(); //需要比较的学科
		    $t_data = array_filter($data['data'][$cl_name]);
		    
		    
		    if (count($t_data) < 4)
		    {
		        $advantage_subject = array_keys($t_data);
		    }
		    else
		    {
		        $t_data = array_values($t_data);
		        
		        //若排第三的学科与第四的学科值不相等时，取前三条即可
		        if ($t_data[2] != $t_data[3])
		        {
		            $advantage_subject = array_keys(array_slice($data['data'][$cl_name], 0, 3));
		        }
		        else
		        {
		            $subject = array_flip($subjects);
		            
		            //若排名第三的学科与第四的学科值相等时，取值比排第三的学科大的学科
		            foreach ($data['data'][$cl_name] as $key => $val)
		            {
		                if ($t_data[2] < $val)
		                {
		                    $advantage_subject[] = $key;
		                }
		                
		                if ($t_data[2] == $val)
		                {
		                    $compare_subject[] = $subject[$key];
		                }
		            }
		        }
		    }
		    
		    if (count($advantage_subject) < 3 && count($t_data) > 3)
		    {
		        $subject_list = C('subject');
		        $know_process = C('know_process');
		        
		        krsort($know_process);
		        
		        foreach ($know_process as $kp => $know_process_name)
		        {
		            $diff_num = 3 - count($advantage_subject);
		            if ($diff_num <= 0)
		            {
		                break;
		            }
		            
		            $kp_subject = array(); //认知过程得分率
		            $kp_diff = array();//认知过程得分率对应学科下某个人与所有参加考试的得分率的差值
		            
		            foreach ($compare_subject as $subject_id)
		            {
		                //排除不需要比较的学科
		                if (in_array($subject_list[$subject_id], $advantage_subject))
		                {
		                    continue;
		                }
		                
		                //统计所有参加考试的成绩有效的人知识点认知过程得分率
		                if (empty(self::$_data['know_process_percent'][$exam_pid][$subject_id][$kp]))
		                {
		                    $sql = "SELECT SUM(etr.full_score) total_score,
        		                    SUM(etr.test_score) test_score FROM rd_relate_knowledge rk
        		                    LEFT JOIN rd_exam_test_result etr ON rk.ques_id = etr.ques_id
        		                    LEFT JOIN rd_exam e ON etr.exam_id = e.exam_id
        		                    LEFT JOIN rd_exam_test_paper etp ON etp.exam_id = e.exam_id AND etp.uid = etr.uid
        		                    WHERE e.subject_id = $subject_id AND rk.know_process = $kp
        		                    AND e.exam_pid = $exam_pid AND etp.etp_flag = 2";
		                    
		                    $count = self::$_db->fetchRow($sql);
		                    $percent = 0;
		                    if ($count && $count['total_score'] > 0)
		                    {
		                        $percent = round($count['test_score'] / $count['total_score'] * 100, 2);
		                        if ($percent > 100)
		                        {
		                            $percent = 100;
		                        }
		                    }
		                    
		                    self::$_data['know_process_percent'][$exam_pid][$subject_id][$kp] = $percent;
		                }
		                else 
		                {
		                    $percent = self::$_data['know_process_percent'][$exam_pid][$subject_id][$kp];
		                }
		                
		                $kp_subject[$subject_id][0] = $percent;
		                
		                //统计某个人知识点认知过程得分率
		                $sql = "SELECT SUM(etr.full_score) total_score, SUM(etr.test_score) test_score 
		                        FROM rd_relate_knowledge rk
    		                    LEFT JOIN rd_exam_test_result etr ON rk.ques_id = etr.ques_id
    		                    LEFT JOIN rd_exam e ON etr.exam_id = e.exam_id
    		                    LEFT JOIN rd_exam_test_paper etp ON etp.exam_id = e.exam_id AND etp.uid = etr.uid
    		                    WHERE e.subject_id = $subject_id AND rk.know_process = $kp
    		                    AND e.exam_pid = $exam_pid AND etr.uid = $uid AND etp.etp_flag = 2";
		                $count = self::$_db->fetchRow($sql);
		                $percent2 = 0;
		                if ($count && $count['total_score'] > 0)
		                {
		                    $percent2 = round($count['test_score'] / $count['total_score'] * 100, 2);
		                    if ($percent2 > 100)
		                    {
		                        $percent2 = 100;
		                    }
		                }
		                $kp_subject[$subject_id][$uid] = $percent2;
		                
                        $kp_diff[$subject_id] = $percent2 - $percent;
		            }
		            
		            $data['know_process'][$kp] = $kp_subject;
		            
		            arsort($kp_diff);
		            
		            $t_kp_diff = array();
		            
		            foreach ($kp_diff as $s_id => $val)
		            {
		                if (in_array($subject_list[$s_id], $advantage_subject))
		                {
		                    continue; 
		                }
		                
		                $t_kp_diff[] = array('subject_id' => $s_id, 'diff' => $val);
		            }
		            
		            //比较的学科中第$diff_num-1的值不等于$diff_num的值，取前$diff_num条即可
	                if ((isset($t_kp_diff[$diff_num-1])
	                    && $t_kp_diff[$diff_num-1]['diff'] != $t_kp_diff[$diff_num]['diff'])
		                || count($t_kp_diff) == 1)
	                {
	                    $sc_subject = array_slice($t_kp_diff, 0, $diff_num);
	                    foreach ($sc_subject as $val)
	                    {
	                        $advantage_subject[] = $subject_list[$val['subject_id']];
	                    }
	                }
	                //比较的学科中第$diff_num-1的值等于$diff_num的值，取值比$diff_num-1的学科大的学科
	                else if (isset($t_kp_diff[$diff_num-1])
	                    && $t_kp_diff[$diff_num-1]['diff'] == $t_kp_diff[$diff_num]['diff'])
	                {
	                    foreach ($t_kp_diff as $val)
	                    {
	                        if ($t_kp_diff[$diff_num-1] < $val['diff'])
	                        {
	                            $advantage_subject[] = $subject_list[$val['subject_id']];
	                        }
	                    }
	                }
		        }
		    }
		    
		    //如果还是不能区分比较有优势的学科，则取与排第三相等的所有学科
		    if (count($t_data) > 3 && count($advantage_subject) < 3 && $data['data'][$cl_name] > 3)
		    {
		        foreach ($data['data'][$cl_name] as $key => $val)
		        {
		            if ($val == $t_data[2] 
		                && !in_array($key, $advantage_subject))
		            {
		                $advantage_subject[] = $key;
		            }
		        }
		    }
		    
		    $data['advantage_subject'] = $advantage_subject;
		}
		
		return $show_tip ? $tip : $data;
	}
	
	/**
	 * 匹配度 XX%
	 * note:
	 * 	 XX% = 我的得分/总体期望（按照评估规则 学科比重算）
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_pid 考试期次
	 * @param number $uid 考生ID
	 */
	public function module_match_percent($rule_id = 0, $exam_pid = 0, $uid = 0)
	{
		$rule_id = intval($rule_id);
		$exam_pid = intval($exam_pid);
		$uid = intval($uid);
		if (!$rule_id || !$exam_pid || !$uid)
		{
			return 0;
		}
		
		$match_percent = 0;//匹配度
		$my_scores = 0;//我的得分
		$total_expect_scores = 0;//总体期望 
		
		$data = array();
		
		//获取评估规则的学科权重
		if (empty(self::$_data['subject_percent'][$rule_id]))
		{
		    $sql = "SELECT subject_percent FROM rd_evaluate_rule WHERE id={$rule_id}";
		    self::$_data['subject_percent'][$rule_id] = self::$_db->fetchOne($sql);
		}
		
		$subject_percent = self::$_data['subject_percent'][$rule_id];
		
		if ($subject_percent == '')
		{
			return 0;
		}

		$subject_percent = explode(',', $subject_percent);
		$subject_percents = array();
		foreach ($subject_percent as $item)
		{
			@list($subject_id, $percent) = @explode(':', $item);
			if (is_null($subject_id) || is_null($percent)) 
			{
				continue;
			}
			
			$subject_percents[$subject_id] = $percent;
		}
		
		if (!$subject_percents)
		{
			return array();
		}
		
		//根据学科ID获取对应的考试学科ID
		$subject_ids = implode(',', array_keys($subject_percents));
		$sql = "SELECT DISTINCT(exam_id) AS exam_id, subject_id,full_score,test_score
				FROM rd_exam_test_paper
				WHERE exam_pid={$exam_pid} AND uid={$uid} AND subject_id IN($subject_ids)
		        ORDER BY subject_id";

		$query = self::$_db->query($sql);
		
		$subject = array(
		        'subject_name' => array('各学科名'),
		        'total_score' => array('总分'),
		        'subject_percent' => array('权重（%）'),
		        'expect_percent_score' => array('权重得分'),
		        'test_score' => array('实际得分'),
		        'real_percent_score' => array('实际权重得分')
		);
		
		$total_score = array(
		        'total_score' => 0,
		        'total_percent' => 0,
		        'total_expect_percent_score' => 0,
		        'total_test_score' => 0,
		        'total_real_percent_score' => 0,
		);
		
		$subject_count = array();//系统保存的总分、权重、权重得分的数据
		if (isset(self::$_data['complex_match_percent'][$exam_pid]))
		{
			$subject_count = self::$_data['complex_match_percent'][$exam_pid];
		}
		
		//统计总分、权重、权重得分、实际得分、实际权重得分

		$subjects = C('subject');
		while ($item = $query->fetch(PDO::FETCH_ASSOC))
		{
		    if (!$subject_count)
		    {
		        $subject['subject_name'][] = $subjects[$item['subject_id']];
		        $subject['total_score'][]  = round($item['full_score']);
		        $subject['subject_percent'][] = $subject_percents[$item['subject_id']];
		        	
		        $total_score['total_score'] += round($item['full_score']);
		        	
		        $expect_percent_score = round($item['full_score'] * $subject_percents[$item['subject_id']] / 100);
		        $subject['expect_percent_score'][] = $expect_percent_score;
		        $total_expect_scores += $expect_percent_score;
		        	
		        $total_score['total_percent'] += $subject_percents[$item['subject_id']];
		        $total_score['total_expect_percent_score'] = $total_expect_scores;
		    }
			
			$t_test_score = round($item['test_score']);
			$subject['test_score'][] = $t_test_score;
			$total_score['total_test_score'] += $t_test_score;
			
			$real_percent_rescore = round($item['test_score'] * $subject_percents[$item['subject_id']] / 100);
			$subject['real_percent_score'][] = $real_percent_rescore;
			
			$my_scores += $real_percent_rescore;
			
			$total_score['total_real_percent_score'] = $my_scores;
		}
		
		//总分、权重、权重得分、实际权重得分合计
		if ($subject_count)
		{
		    $subject['subject_name'] = $subject_count['subject_name'];
		    $subject['total_score'] = $subject_count['total_score'];
		    $subject['subject_percent'] = $subject_count['subject_percent'];
		    $subject['expect_percent_score'] = $subject_count['expect_percent_score'];
		    
		    $total_expect_scores = end($subject_count['expect_percent_score']);
		}
		else 
		{
		    $subject['subject_name'][] = '合计';
		    $subject['total_score'][] = $total_score['total_score'];
		    $subject['subject_percent'][] = $total_score['total_percent'];
		    $subject['expect_percent_score'][] = $total_score['total_expect_percent_score'];
		    
		    self::$_data['complex_match_percent'][$exam_pid] = $subject;
		}
		
		$subject['test_score'][] = $total_score['total_test_score'];
		$subject['real_percent_score'][] = $total_score['total_real_percent_score'];

		$match_percent = $total_expect_scores <= 0 ? 0 : round(($my_scores / $total_expect_scores) * 100);
		$data['match_percent'] = $match_percent > 100 ? '100' : $match_percent;
		$data['data'][] = $subject['subject_name'];
		$data['data'][] = $subject['total_score'];
		$data['data'][] = $subject['subject_percent'];
		$data['data'][] = $subject['expect_percent_score'];
		$data['data'][] = $subject['test_score'];
		$data['data'][] = $subject['real_percent_score'];
	
		return $data;
	}
	
	/**
	 * 选考科目模拟等级及赋分
	 * 
	 * @param number $rule_id 评估规则ID
	 * @param number $exam_pid 考试期次
	 * @param number $uid 考生ID
	 * @return array 
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
	    $sql = "SELECT exam_id, subject_id, full_score, test_score 
	            FROM rd_exam_test_paper WHERE uid = ? AND exam_pid = ?
	            AND subject_id NOT IN (1, 2, 3, 11) 
	            ORDER BY test_score DESC";
	    
	    $query = self::$_db->query($sql, $bind);
	    
	    //考试学科成绩
	    $exam_scores = array();
	    //学科对应的考试
	    $subject_exams = array();
	    
	    while ($val = $query->fetch(PDO::FETCH_ASSOC))
	    {
	        $subject_exams[$val['subject_id']] = $val['exam_id'];
	        
	        $test_score = round($val['test_score']);
	        $pass_score = floor($val['full_score'] * 4 / 7); //（注：4/7，满分70分，40分及格）
	        if ($test_score >= $pass_score)
	        {
	            //如果考生该科目及格，则学业水平等级、模拟选考科目等级及赋分需要进一步计算，避免后续判断，先默认为'-'
	            $exam_scores[$val['subject_id']]['pass_score'] = $pass_score;//当前科目及格分数线
	            $exam_scores[$val['subject_id']]['test_score'] = $test_score;//当前科目考生考试分数
	            //$exam_scores[$val['subject_id']]['full_score'] = $val['full_score'];//当前科目考试满分
	            
	            $data['academic_level'][$val['subject_id']] = "-";
	            $data['assign_level'][$val['subject_id']] = "-";
	            $data['assign_points'][$val['subject_id']] = "-";
	        }
	        else 
	        {
	            //如果考生该科目不及格，则学业水平等级为“E”，模拟选考科目等级及赋分为空（不赋值）
	            $data['academic_level'][$val['subject_id']] = "E";
	            $data['assign_level'][$val['subject_id']] = "-";
	            $data['assign_points'][$val['subject_id']] = "-";
	        }
	        
	        $data['score'][$val['subject_id']] = $test_score; //考生考试卷面真实得分
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
	                    /*
	                    $full_score = $exam_scores[$subject_id]['full_score'];
	                    if ($level == "A+" && $test_score < $full_score)
	                    {
	                        $data['academic_level'][$subject_id] = 'A';
	                    }
	                    else
	                    {
	                    )
	                        $data['academic_level'][$subject_id] = $level;
	                    }
	                    */
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
}