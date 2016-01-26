<?php if ( ! defined('BASEPATH')) exit();
/**
 * 
 * 测评报告 --生成报告模板->html
 * @author TCG
 * @final 2015-07-21
 */
class General_template_model extends CI_Model 
{
    private static $_uid;
    private static $_exam_pid;
    private static $_rule_id;    
    private static $_exam_id;
    private static $_data = array();

	public function __construct()
	{
		parent::__construct();
		
		$this->load->model('cron/cron_task_email_model');
		$this->load->model('cron/report/task_report_model');
		//$this->load->model('admin/evaluate_rule_model');
	}

	/**
	 * 生成测评报告模板->html
	 * 
	 */
	public function general_to_html()
	{
		//$this->load->model('admin/evaluate_template_model', 'et_model');
		$this->load->model('cron/report/subject_comparison_info_model', 'sci_model');
		$this->load->model('cron/report/subject_three_dimensional_model', 'std_model');
		$this->load->model('cron/report/subject_question_model', 'sq_model');
		$this->load->model('cron/report/subject_suggest_model', 'ss_model');
		$this->load->model('cron/report/complex_model', 'c_model');
		
		//获取待处理的评估规则
		$rule_ids = $this->task_report_model->get_doing_tasks();
		if (!count($rule_ids))
		{
			return false;
		}
		
		$convert2pdf_data = array();
		$now = time();
		foreach ($rule_ids as $item)
		{
			$status = $item['status'];
			$rule_id = $item['rule_id'];
			$ctime = $item['ctime'];
			
			//获取该规则信息
			$rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
			if (!count($rule) || $rule['is_delete'])
			{
				continue;
			}
			
			$template_module = array();
			if ($item['template_id'])
			{
			    $template_data = json_decode($item['template_id'], true);
			    foreach ($template_data as $template_subject => $template_id)
			    {
			        $template_module[$template_subject] = EvaluateTemplateModel::get_evaluate_template_info($template_id);
			    }
			}

			//考试期次
			$exam_pid = $rule['exam_pid'];
			
			//检查该期考试的所有考场都已经结束
			$sql = "select count(*) as count from {pre}exam_place 
			        where end_time >= {$now} and exam_pid={$exam_pid}";
			$result = $this->db->query($sql)->row_array();
			if ($result['count'])
			{
				continue;
			}
			
			//生成学科(0:各学科总结)
			$g_subject_id = $rule['subject_id'];
			
			//生成模式
			$generate_mode = $rule['generate_mode'];
			
			//生成考生
			$generate_uid = $rule['generate_uid'];
			
			//待生成报告的考生
			$uids = array();
			
			if ($generate_mode == '1') 
			{
				//单人模式
				
				//检查该学生是否作弊
				$sql = "select count(*) as count from {pre}exam_test_paper 
				        where exam_pid={$exam_pid} and uid={$generate_uid} and etp_flag=-1";
				$result = $this->db->query($sql)->row_array();
				if ($result['count'] > 0)
				{
					continue;
				}
				
				$uids[] = $generate_uid;
			}
			else 
			{
				//批量模式
				
				//获取未作弊的考生
				/*
				 * 已生成成绩
				 */
				$sql = "select distinct(uid) as uid from {pre}exam_test_paper 
				        where exam_pid={$exam_pid} and etp_flag=2";
				$result = $this->db->query($sql)->result_array();
				
				foreach ($result as $item)
				{
					$uids[] = $item['uid'];
				}
			}
			
			if (!count($uids))
			{
			    continue;
			}
			
			//获取该期考试的考试学科
			$exam_ids = array();
			$sql = "select subject_id, exam_id from {pre}exam where exam_pid={$exam_pid}";
			$result = $this->db->query($sql)->result_array();
			$subject_exams = array();
			$exam_subjects = array();
			foreach ($result as $item) 
			{
				$subject_exams[$item['subject_id']] = $item['exam_id'];
				$exam_subjects[$item['exam_id']] = $item['subject_id'];
			}
			
			if ($g_subject_id == 0)
			{
				//总结
				$exam_ids = array_values($subject_exams);
				$exam_ids[] = 0;
			} 
			else
			{
				//单学科
				if (!isset($subject_exams[$g_subject_id]))
				{
					continue;
				}
				
				$exam_ids[] = $subject_exams[$g_subject_id];
			} 
			
			if (!count($exam_ids))
			{
				continue;
			}
			
			//将该规则的计划任务设置为 处理中
			$status == '0' && $this->task_report_model->set_task_doing($rule_id);

			//保存该规则对应的考生
			$this->_save_student($rule_id, $uids, $exam_ids);
			
			//获取生成报告的考试名称
			$sql = "select exam_name from {pre}exam where exam_id={$exam_pid}";
			$examInfo = $this->db->query($sql)->row_array();
			
			//开始生成 模板
			foreach ($uids as $uid)
			{
				//获取生成报告的考生信息
				$sql = "select first_name,last_name from {pre}student where uid={$uid}";
				$studentInfo = $this->db->query($sql)->row_array();

				foreach ($exam_ids as $exam_id)
				{
					$data = array();

					$data['studentName'] = $studentInfo['last_name'] . $studentInfo['first_name'];
					$data['ctime'] = $ctime;
					$data['examName'] = $examInfo['exam_name'];

					$t_subject_id = $exam_id == '0' ? '0' : $exam_subjects[$exam_id];
					$subject_name = $exam_id == '0' ? '总结' : C('subject/'.$exam_subjects[$exam_id]);

					if ($exam_id == 0)
					{				
						//总结学科
						$data['subject_name'] = $subject_name;
						$data['t_subject_id'] = $t_subject_id;

						//考场,考试时间
						$sql = "select ep.place_id,ep.place_name,ep.start_time,ep.end_time 
						        from {pre}exam_place ep
						        LEFT JOIN {pre}exam_test_paper etp ON ep.place_id = etp.place_id
						        where etp.exam_id={$exam_ids[0]} and etp.uid={$uid}";
						        
						$result = $this->db->query($sql)->row_array();

						if (!$result) {
						    continue;
						}
						
						$data['placeName'] = $result['place_name'];
						$data['startTime'] = date('Y年m月d日',$result['start_time']);
						$data['endTime'] = date('Y年m月d日',$result['end_time']);
						
						if (!empty($template_module[$t_subject_id]['module']) 
						    && !$template_module[$t_subject_id]['template_type'])
						//if (false)
						{
						    						    
						    $data['know_process'] = C('know_process');
						    $data['subject'] = C('subject');
						    
						    $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
						    
						    $output = $this->load->view('report/complex_module/c', $data, true);
						    $g_sort = 0;
						    foreach ($template_module[$t_subject_id]['module'] as $module)
						    {
						        if (empty($module['children']))
						        {
						            continue;
						        }
						         
						        $c_sort = 0;
						        foreach ($module['children'] as $value)
						        {
						            if ($value['module_type'])
						            {
						                continue;
						            }
						            
						            $module_code = trim($value['module_code']);
						            $module_pcode = current(explode('_', $module_code));
						             
						            $_module_model = $module_pcode . "_model";
						            $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
						            
						            if (!is_object($this->$_module_model)
						              || !method_exists($this->$_module_model, $_module_func))
						            {
						                continue;
						            }
						            
					                $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_pid, $uid, $include_subject);
						             
						            if (!empty($data[$module_code]))
						            {
						                if ($c_sort == 0)
						                {
						                    $g_sort++;
						                
						                    $data['g_sort'] = $g_sort;
						                    $data['parent_module_name'] = $module['module_name'];
						                }
						                
						                $c_sort++;
						                 
						                $data['c_sort'] = $c_sort;
						                
						                $data['module_name'] = $value['module_name'];
						                 
						                $output .= $this->load->view(
						                        "report/complex_module/$module_code",
						                        $data, true);
						            }
						        }
						    }

						   $output .= $this->load->view('report/complex_module/footer', $data, true);
						}
						else
						{
						    //总结方法策略运用情况
						    //$data['mothod_tactic'] = $this->c_model->module_method_tactic($rule_id, $exam_pid, $uid);
						    //pr($data['mothod_tactic'],1);
						     
						    //总结各学科在总体的相对位置
						    $data['subject_relate_position'] = $this->c_model->module_subject_relative_position($rule_id, $exam_pid, $uid);
						    // 				    	pr($data['subject_relate_position']);
						     
						    //总结 匹配度 XX%
						    $data['match_percent'] = $this->c_model->module_match_percent($rule_id, $exam_pid, $uid);
						    // 				    	pr($data['match_percent']);
						    
						    $output = $this->load->view('report/complex', $data, true);
						}
					} else {
					    //单学科
					    //学科名称
					    $data['subject_name'] = $subject_name;
					    $data['t_subject_id'] = $t_subject_id;
					    
						//考场,考试时间
						$sql = "select ep.place_id,ep.place_name,ep.start_time,ep.end_time 
						        from {pre}exam_place ep
						        LEFT JOIN {pre}exam_test_paper etp ON ep.place_id = etp.place_id
						        where etp.exam_id={$exam_id} and etp.uid={$uid}";
						        
						$result = $this->db->query($sql)->row_array();

						if (!$result) {
						    continue;
						}
						
						$data['placeName'] = $result['place_name'];
						$data['startTime'] = date('Y年m月d日',$result['start_time']);
						$data['endTime'] = date('Y年m月d日',$result['end_time']);

						//考试时长
						$data['timeInterval'] = $this->timeDiff($result['start_time'],$result['end_time']);

						//开始考试时间
						$sql = "select ctime from {pre}exam_logs where exam_id={$exam_pid} 
						        and uid={$uid} and place_id=" . intval($result['place_id']) . " and type=1";
						$startTime = $this->db->query($sql)->row_array();
						if (!empty($startTime['ctime']))
						{
						    $startTime['ctime'] = strtotime($startTime['ctime']);
						    if ($result['start_time'] > $startTime['ctime'])
						    {
						        $startTime['ctime'] = $result['start_time'];
						    }
						}
						else
						{
							$startTime['ctime'] = $result['start_time'];
						}
						
						
						$data['examStartTime'] = date('H:i',$startTime['ctime']);

						//结束考试时间
						$sql = "select ctime from {pre}exam_logs where exam_id={$exam_pid} and uid={$uid} 
						        and place_id=" . intval($result['place_id']) . "  and type=2";
						$endTime = $this->db->query($sql)->row_array();
						if (empty($endTime['ctime']))
						{
							$endTime['ctime'] = $result['end_time'];
						}
						else
						{
						    $endTime['ctime'] = strtotime($endTime['ctime']);
						}
						$data['examEndTime'] = date('H:i', $endTime['ctime']);

						//考试用时
						$data['examTimeInterval'] = $this->timeDiff($startTime['ctime'], $endTime['ctime']);
						if (intval($data['examTimeInterval']) > intval($data['timeInterval']))
						{
						    $data['examTimeInterval'] = $data['timeInterval'];
						}
						
						//根据模板生成测评报告
						//pr($template_module,1);
						if (!empty($template_module[$t_subject_id]['module'])
					        && $template_module[$t_subject_id]['template_type'])
						{
						    $data['template_id'] = $template_module[$t_subject_id]['template_id'];
						    
						    $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
						    if ($include_subject && !in_array($t_subject_id, $include_subject))
						    {
						        continue;
						    }
						    
						    $output = $this->load->view('report/subject_module/subject', $data, true);
						    $g_sort = 0;
						    foreach ($template_module[$t_subject_id]['module'] as $module)
						    {
					            if (empty($module['children']))
					            {
					                continue;
					            }
					            
					            $c_sort = 0;
					            foreach ($module['children'] as $value)
					            {
					                if (!$value['module_type'])
					                {
					                    continue;
					                }
					                
					                $module_code = trim($value['module_code']);
					                $module_pcode = current(explode('_', $module_code));
					                
					                if ($value['module_subjectid'])
					                {
					                    $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
    					                if ($module_subjectids
    						              && !in_array($t_subject_id, $module_subjectids))
    						            {
    						                continue;
    						            }
					                }
					                
					                $_module_model = $module_pcode . "_model";
					                $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
					                if (!is_object($this->$_module_model)
					                   || !method_exists($this->$_module_model, $_module_func))
					                {
					                    continue;
					                }
					                
					                if ($module_code == 'sci_exam_info')
					                {
					                   $data['sci_exam_info'] = $this->sci_model->module_exam_info($exam_pid, $t_subject_id);// 考试说明
					                }
					                else if ($module_code == 'ss_match_percent')
					                {
					                    if (!isset($data['sq_all']))
					                    {
					                        $data['sq_all'] = $this->sq_model->module_all($rule_id, $exam_id, $uid);
					                    }
					                    
					                    $data[$module_code] = $this->$_module_model->$_module_func($exam_id, $data['sq_all']['desc_data']);
					                }
					                else 
					                {
					                    if (!isset($data[$module_code]))
					                    {
					                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid);
					                    }
					                }
					                
					                if (!empty($data[$module_code]))
					                {
					                    //显示一级模块
					                    if ($c_sort == 0)
					                    {
					                        $g_sort++;
					                         
					                        if ($module_pcode == 'sci'
					                                && !isset($data['sci_knowledge']))
					                        {
					                            $data['sci_knowledge'] = $this->sci_model->module_knowledge($rule_id, $exam_id, $uid);
					                        }
					                         
					                        $data['g_sort'] = $g_sort;
					                        $data['parent_module_name'] = $module['module_name'];
					                    
					                        $output .= $this->load->view(
					                                "report/subject_module/$module_pcode/$module_pcode",
					                                $data, true);
					                    }
					                    
					                    $c_sort++;
					                    
					                    $data['c_sort'] = $c_sort;
					                    $data['module_name'] = $value['module_name'];
					                    
					                    $output .= $this->load->view(
					                            "report/subject_module/$module_pcode/$module_code",
					                            $data, true);
					                }
					            }
						    }
						    
						    $output .= $this->load->view('report/subject_module/footer', $data, true);
						} else {
						    //旧的生成报告逻辑
						    
    						//考试信息对比
    					  	$data['sci_knowledge'] = $this->sci_model->module_knowledge($rule_id, $exam_id, $uid);//知识点 
    
    					  	if ($t_subject_id == 3)
    					  	{
    					  	    $data['sci_group_type'] = $this->sci_model->module_group_type($rule_id, $exam_id, $uid);//信息提取方式
    					  	    //pr($data['sci_group_type'],1);
    					  	    
    					  	    $data['sci_extraction_ratio'] = $this->sci_model->module_extraction_ratio($rule_id, $exam_id, $uid);//知识点和信息提取方式比例
    					  	}
    					  	else
    					  	{
    					  	    $data['sci_group_type'] = array();
    					  	    $data['sci_extraction_ratio'] = array();
    					  	}
    					  	
    				     	$data['sci_method_tactic'] = $this->sci_model->module_method_tactic($rule_id, $exam_id, $uid);//方法策略 
    					  	
    					  	//题型和难易度覆盖的对比
    				     	$data['sci_difficulty'] = $this->sci_model->module_difficulty($rule_id, $exam_id, $uid);// 题型难易度 
    					  	
    				     	$data['sci_exam_info'] = $this->sci_model->exam_info($exam_pid, $t_subject_id);// 考试说明
    				     	
    						//三维模块
    				     	$data['std_knowledge'] = $this->std_model->module_knowledge($rule_id, $exam_id, $uid);//知识点 
    
    				     	$data['std_method_tactic'] = $this->std_model->module_method_tactic($rule_id, $exam_id, $uid);// 方法策略  
    
    				     	if ($t_subject_id == 3)
    				     	{
    				     	    $data['std_group_type'] = $this->std_model->module_group_type($rule_id, $exam_id, $uid);//信息提取方式
    				     	}
    				     	else
    				     	{
    				     	    $data['std_group_type'] = array();//信息提取方式
    				     	}
    
    				     	$data['std_difficulty'] = $this->std_model->module_difficulty($rule_id, $exam_id, $uid);//试题难易度
    				
     				     	//试题分析及与之对应的评价
    				     	$data['sq_all'] = $this->sq_model->get_all($rule_id, $exam_id, $uid);//知识点
    				     	
     				     	//诊断及建议
    				     	$data['ss_summary'] = $this->ss_model->module_summary($rule_id, $exam_id, $uid);//总体水平等级和排名
    				     	$data['ss_application_situation'] = $this->ss_model->module_application_situation($exam_id, $uid);//总体水平等级和排名 
    				     	$data['ss_match_percent'] = $this->ss_model->target_match_percent($exam_id, $data['sq_all']['desc_data']);//目标匹配度

    				     	$data['template_id'] = 0;
    				     	$output = $this->load->view('report/subject', $data, true);
						}
					}
					
					//保存html模板
					$subject_id = isset($exam_subjects[$exam_id]) ? $exam_subjects[$exam_id] : 0;
					$res = $this->_put_html_content($rule_id, $subject_id, $uid, $output);
					if ($res)
					{
						$now = time();
						$k = urlencode(base64_encode("{$rule_id}-{$uid}-{$subject_id}"));
						$source_url = C('public_host_name') . "/report/{$k}.html";
						$source_path = "zeming/report/{$rule_id}/{$uid}/{$subject_name}.pdf";
						$target_id = "{$rule_id}_{$uid}_{$subject_id}";
						$convert2pdf_data[$target_id] = array(
								'type' 			=> '1',
								'source_url' 	=> $source_url,
								'source_path' 	=> $source_path,
								'ctime' 		=> $now,
								'mtime' 		=> $now,
								'target_id' 	=> $target_id,
						);
						
						//事先创建好保存pdf目录
// 						$this->_mk_pdf_dir($rule_id, $uid);
						
						//事先创建好保存zip目录
						$this->_mk_zip_dir($rule_id);
						
						//保存 待生成pdf 数据
						$this->_save_convert2pdf_data($convert2pdf_data);
						unset($convert2pdf_data);
					}
				}				
			}
		}
		
		//保存 待生成pdf 数据
// 		if (count($convert2pdf_data))
// 		{
// 			$this->_save_convert2pdf_data($convert2pdf_data);
// 		}
	}

	/**
	 * 生成zip压缩包
	 */
	public function general_zip()
	{
		//获取待处理的评估规则
		$rule_ids = $this->task_report_model->get_doing_tasks(array(1,2));
		if (!count($rule_ids))
		{
			return false;
		}
	
		$insert_data = array();
		$update_data = array();
		$data = array();
	
		//开启日志记录
		$this->config->set_item('log_threshold', '3');
	
		$pdf_path = C('html2pdf_path');
		foreach ($rule_ids as $item)
		{
			$rule_id = $item['rule_id'];
	
			//获取该规则关联的考生
			$result = $this->db->query("select exam_ids, uids from {pre}evaluate_student where rule_id={$rule_id}")->row_array();
			if (!count($result))
			{
				continue;
			}
			$uids = explode(',', $result['uids']);
			$exam_ids = explode(',', $result['exam_ids']);
			$exam_ids = array_filter($exam_ids);
			if (!count($uids) || !count($exam_ids))
			{
				continue;
			}
			
			//获取该期考试对应的考试名称
			$sql = "select subject_id, exam_id from {pre}exam where exam_id in (".trim($result['exam_ids'],',').")";
			$exam_subjects = array();
			$result = $this->db->query($sql)->result_array();
			foreach ($result as $item)
			{
// 			    if ($item['subject_id'] == 11)
// 			    {
// 			        continue;
// 			    }
			    
				$exam_subjects[$item['exam_id']] = C('subject/'.$item['subject_id']);
			}
			
			foreach ($uids as $uid)
			{
				//压缩前提：所有学科的pdf均已生成
				$all_done = true;
				$zip_dir = $pdf_path . "/zeming/report/{$rule_id}/{$uid}";
				foreach ($exam_ids as $exam_id)
				{
				    if (!isset($exam_subjects[$exam_id]))
				    {
				        continue;
				    }
				    
					$subject_name = $exam_subjects[$exam_id];
					
					$file_path = $zip_dir."/{$subject_name}.pdf";
					if (!file_exists($file_path))
					{
						$all_done = false;
					}
				}
				
				if ($all_done)
				{
					//开始打包
					$save_file = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}.zip";
					$info = $this->_zip($save_file, $zip_dir, $zip_dir);
					if ($info != true)
					{
						//压缩失败，保存日志
						$msg = array(
								"保存路径：{$save_file}",
								"压缩文件/目录：{$zip_dir}",
								"失败原因：{$info}"
						);
						log_message('info', implode("\n------------\n", $msg));
					}
				}
			}
		}
	}
	
	/**
	 * 检查 测评报告生成情况
	 */
	public function check_evaluate_stat()
	{
		//获取待处理的评估规则
		$rule_ids = $this->task_report_model->get_doing_tasks(array(1,2));
		if (!count($rule_ids))
		{
			return false;
		}
		
		$insert_data = array();
		$update_data = array();
		$data = array();
		
		$pdf_path = C('html2pdf_path');
		foreach ($rule_ids as $item)
		{
			$rule_id = $item['rule_id'];
			$finish_part = false;//pdf 是否都已经处理完成 
			$zip_all_ready = true;//zip 是否都已经处理完成
			
			//获取该规则关联的考生
			$result = $this->db->query("select exam_ids, uids from {pre}evaluate_student where rule_id={$rule_id}")->row_array();
			if (!count($result))
			{
				continue;
			}
			$uids = explode(',', $result['uids']);
			$exam_ids = explode(',', $result['exam_ids']);
			$exam_ids = array_filter($exam_ids);
			if (!count($uids) || !count($exam_ids))
			{
				continue;
			}
			
			//获取该期考试对应的考试名称
			$sql = "select subject_id, exam_id from {pre}exam where exam_id in (".trim($result['exam_ids'],',').")";
			$exam_subjects = array();
			$result = $this->db->query($sql)->result_array();
			foreach ($result as $item)
			{
// 			    if ($item['subject_id'] == 11)
// 			    {
// 			        continue;
// 			    }
			    
				$exam_subjects[$item['exam_id']] = C('subject/'.$item['subject_id']);				
			}
			
			//获取该规则是否有包含总结所有学科
			$sql = "select id, subject_id from {pre}evaluate_rule where id={$rule_id}";
			$result = $this->db->query($sql)->row_array();
			$has_complex_subject = false;
			if (count($result) && !$result['subject_id'])
			{
				$has_complex_subject = true;
				$exam_subjects[0] = '总结';
				$exam_ids[] = 0;
			}
			
			foreach ($uids as $uid)
			{
				$tmp_arr = array();
				
				//检查该考生的 pdf 生成情况
				$exam_stat = array();
				$pdf_ready = true;
					
				foreach ($exam_ids as $exam_id)
				{
					if (!isset($exam_subjects[$exam_id])) continue;
					
					//pdf 保存路径
					$subject_name = $exam_subjects[$exam_id];
					$path = "{$pdf_path}/zeming/report/{$rule_id}/{$uid}/{$subject_name}.pdf";
					if (file_exists($path))
					{
						$finish_part = true;
						$exam_stat[$exam_id] = 1;
					}		
					else 
					{
						$pdf_ready = false;
						$exam_stat[$exam_id] = 0;
					}			
				}
				
				$tmp_arr['rule_id'] = $rule_id;
				$tmp_arr['uid'] = $uid;
				$tmp_arr['exam_ids'] = implode(',', $exam_ids);
				$tmp_arr['pdf_ready'] = $pdf_ready ? 1 : 0;
				
				$pdf_ready_exam = array();
				foreach ($exam_stat as $k=>$v)
				{
					$pdf_ready_exam[] = "{$k}-{$v}";
				}
				$tmp_arr['pdf_ready_exam'] = implode(',', $pdf_ready_exam);
					
				//检查该考生的 zip 生成情况		
				$zip_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}.zip";
				if (file_exists($zip_path))
				{
					$tmp_arr['zip_ready'] = 1;
				}
				else 
				{
					$zip_all_ready = false;
					$tmp_arr['zip_ready'] = 0;
				}
				
				$data[] = $tmp_arr;
			}
			
			if ($zip_all_ready)
			{
				//已完成
				$this->task_report_model->set_task_done($rule_id);
				
				//给管理员发邮件
				$sql = "select a.email,a.realname,a.admin_id from {pre}admin a, {pre}evaluate_rule_admin ara where a.admin_id=ara.admin_id and ara.rule_id={$rule_id}";
				$admin = $this->db->query($sql)->row_array();
				$email = $admin['email'];
				if (is_email($email))
				{
					$rule_data = EvaluateRuleModel::get_evaluate_rule($rule_id, 'id,name');
					$rule_name = $rule_data['name'];
					$email_tpl = C('email_template/report_general_notice');
					$hostname = C('cron_admin_host');
					$email_content = $this->load->view($email_tpl['tpl'], array('rule' => $rule_data, 'admin' => $admin, 'hostname' => $hostname), true);
					$email_data = array(
							'type' 		=> 2,
							'target_id' => "{$rule_id}-{$uid}",
							'email' 	=> $email,
							'title' 	=> str_ireplace('{subject}', $rule_name, $email_tpl['subject']),
							'content' 	=> $email_content,
					);
					 
					$this->cron_task_email_model->insert($email_data);
				}
				
			} elseif ($finish_part && !$zip_all_ready) {
				//部分完成
				$this->task_report_model->set_task_part_done($rule_id);
			}
		}
		
		//获取老的数据
		$old_result = $this->db->query("select id, rule_id, uid from {pre}evaluate_student_stat")->result_array();
		$old_ids = array();
		foreach ($old_result as $row)
		{
			$k = $row['rule_id']."_".$row['uid'];
			$old_ids[$k] = $row['id'];
		}
		
		foreach ($data as $item)
		{
			$k = $item['rule_id']."_".$item['uid'];
			if (isset($old_ids[$k])) {
				$item['id'] = $old_ids[$k];
				$item['mtime'] = date('Y-m-d H:i:s'); 
				$update_data[] = $item;
				unset($old_ids[$k]);
			} else {
				$item['ctime'] = date('Y-m-d H:i:s'); 
				$item['mtime'] = date('Y-m-d H:i:s'); 
				$insert_data[] = $item;
			}
		}
		
		if (count($insert_data)) {
			$this->db->insert_batch('evaluate_student_stat', $insert_data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('evaluate_student_stat', $update_data, 'id');
		}
		
		if (count($old_ids)) {
			$this->db->where_in('id', array_values($old_ids))->delete('summary_paper_difficulty');
		}
	}

	/**
	 * 生成面试模板->html
	 *
	 * @return void
	 */
	public function general_interview_to_html()
	{
		/* global */
		//$this->load->model('admin/interview_question_model');
		//$this->load->model('admin/interview_result_model');
		$this->load->model('cron/report/interview_task_report_model', 'task_model');
		//$this->load->model('admin/evaluate_template_model', 'et_model');
		//$this->load->model('admin/evaluation_option_model');

		/* config */
		$subject_names = C('subject');

		/**************************** 1.获取数据 **************************/
		/* 任务->评估规则(->模板信息)->考试期次(->评估规则)->考生->面试结果 */

		/* 获取待处理的评估规则 */
		$rule_ids = $this->task_model->get_doing_tasks();

		if (!count($rule_ids))
		{
			return false;
		}

		foreach ($rule_ids as $item) {

			$rule_id = $item['rule_id'];
			$ctime = $item['ctime'];
			$convert2pdf_data = array();
			
			/* 评估规则 */
			$rule = EvaluateRuleModel::get_evaluate_rule($rule_id);

			if (!count($rule) || $rule['is_delete'])
			{
				continue;
			}

			/* 考试期次 */
			$exam_id = $rule['exam_pid'];
			$sql = "select exam_name from {pre}exam where exam_id={$exam_id}";
			$exam_info = $this->db->query($sql)->row_array();

			if (empty($exam_info)) {
				continue;
			}

			/* 评分标准 */
			$sql = "select * from 
				{pre}evaluation_standard as es 
				left join {pre}evaluation_standard_exam as ese on es.id=ese.standard_id 
				where ese.exam_id={$exam_id};";
			$standard = $this->db->query($sql)->row_array();

			if (!count($standard)) {
				continue;
			}

			$options = json_decode($standard['options'], true);
			$weights = json_decode($standard['weight'], true);
			$level = json_decode($standard['level'], true);

			/* 将该规则的计划任务设置为 处理中 */
			$item['status'] == '0' && $this->task_model->set_task_doing($rule_id);
		
			/* 模板信息 */
			$template_module = array();
			/* 是否生成总结报告 */
			$is_summary = false;

			if ($item['template_id'])
			{
			    $template_data = json_decode($item['template_id'], true);

			    foreach ($template_data as $key => $value) {

			    	if (in_array($key, array(0, '0'), true)) {
			    		$is_summary = true;
			    	}

			    	$template_module[$key] = EvaluateTemplateModel::get_evaluate_template_info($value);
			    }

			} else {
				$message = "[" . date('Y-m-d H:i:s') . "]" . " 规则(" . $rule_id . ")模板信息不存在！" ;
				log_message('error', $message);
				continue;
			}

			/* 所有考生(考生信息) */
			$students = array();
			$sql = "select distinct r.student_id,s.uid,s.first_name,s.last_name,sh.school_name from 
				{pre}interview_result as r left join {pre}student as s on r.student_id=s.uid 
				left join {pre}school as sh on s.school_id=sh.school_id 
				where exam_id={$exam_id}";
			$students = $this->db->query($sql)->result_array();

			if (count($students) <= 0) {
				continue;
			}

			/* 所有总结报告 */
			$summary_array = array();

                        /* 实际权重得分排名 */
                        $actual_resutl_ranking = array();
                        
			/* 循环学生生成报告 */
                        foreach ($students as $student) {

                                /* 如果系统中没有当前考生信息，跳过报告生成 */
                                if (empty($student['student_id'])) {
                                    continue;
                                }

				/* 总结 （0 为总结报告） 以模板为准，如果模板中含有总结模板，则生成总结报告*/
				if ($is_summary) {

					/* global */
					$data = array();
					$actual_score = 0;
					
					/* 1.各科面试得分状况 uid */
					$total = array();

					foreach ($options as $subject_id => $option) {

						/* a.各个学科评分项总分 stanard -> option */
						$option_str = implode(',', $option);
						$option_sql = "select sum(score) as sum from {pre}evaluation_option where id in ({$option_str})";
						$option_result = $this->db->query($option_sql)->row_array();
						$total[$subject_id]['option_score'] = $option_result['sum'];

						/* b.权重 standard */
						$total[$subject_id]['weight'] = $weights[$subject_id];

						/* c.实际得分 uid,exam_id ->interview_result*/
						$score_sql = "select sum(scroe) as sum from {pre}interview_result 
							where exam_id={$exam_id} and student_id={$student['uid']} and subject_id={$subject_id}";
                                                
                                                $score_result = $this->db->query($score_sql)->row_array();
						$total[$subject_id]['score'] = $score_result['sum'];

						/* d.实际权重得分 保留小数点后两位 */
						$total[$subject_id]['actual'] = round(($total[$subject_id]['score'] * $total[$subject_id]['weight'] / 100), 2);
						/* 实际总得分 */
						$actual_score += $total[$subject_id]['actual'];
					}

					$data['total'] = $total;

					/* 2.获奖情况 */
					/* uid -> ruidabei_result */
					$ruidabei_sql = "select awards,score,subject,ranks,grade from {pre}ruidabei_result 
						where exam_id={$exam_id} and student_id={$student['uid']}";
					$ruidabei_result = $this->db->query($ruidabei_sql)->result_array();
					$data['ruidabei_result'] = $ruidabei_result;

					/* 3.学习与竞赛成绩 */
					/* 排名 uid-> student_ranking */
					$ranking_sql = "select grade_id,ranking,totalnum from {pre}student_ranking where uid={$student['uid']}";
					$ranking_result = $this->db->query($ranking_sql)->result_array();
					$data['ranking_result'] = $ranking_result;

					/* 获奖 uid->awards */
					$awards_sql = "select typeid,subject,awards,grade,other_name,other_desc from {pre}student_awards
						where uid={$student['uid']}";
					$awards_result = $this->db->query($awards_sql)->result_array();
					$data['awards_result'] = $awards_result;

					/* 考生信息 */
					/* a.姓名 */
					$data['student_id'] = $student['uid'];
					$data['school_name'] = $student['school_name'];
					$data['student_name'] = ($student['last_name'] . $student['first_name']);
                                        $data['actual_score'] = $actual_score;
					$data['examName'] = $exam_info['exam_name'];

					$summary_array[] = $data;
                                        $actual_result_ranking[$student['uid']] = $actual_score;
				}

				/* 单学科 以评分标准中设置的学科为准，如果只有此学科模板，评分标准中没有选中此学科，则不生成报告*/
				foreach ($options as $key => $value) {

					$option_ids = $value;
					$subject_id = $key;

					/* 科目 */
					if (!isset($template_module[$subject_id])) {
						continue;
					}

					$option_arr = array();

					foreach ($option_ids as $k => $v) {
						$option_arr[$k + 1] = EvaluationOptionModel::get_one($v, 'id,title,score');
					}

					/* 平均分 */
					$sql = "select option_index,avg(scroe) as avg from {pre}interview_result 
						where exam_id={$exam_id} and subject_id={$subject_id} group by option_index;";
					$score_avg = $this->db->query($sql)->result_array();

					/* 评分项索引 */
					$option_index = array();
					$avg = array();
					$avg_index = array();
					$total = array();

					foreach ($score_avg as $k => $v) {
						$option_index[] = $option_arr[$v['option_index']]['title'];
						$avg[] = intval($v['avg']);
						$avg_index[$v['option_index']] = intval($v['avg']);
						$total[] = intval($option_arr[$v['option_index']]['score']);
					}


					$student_id = $student['student_id'];

					/* 每项评价分 */
					$query = "select id,option_index,scroe from {pre}interview_result where 
					student_id={$student_id} and exam_id={$exam_id} and subject_id={$subject_id} order by option_index";
					$grade = $this->db->query($query)->result_array();

					if (!count($grade)) {
						continue;
					}

					/**************************** 2.生成模板 ****************************/
					$data = array();
					$data['studentName'] = $student['last_name'] . $student['first_name'];
					$data['ctime'] = $ctime;
					$data['examName'] = $exam_info['exam_name'];
					$data['grade'] = $grade;
					$data['score_avg'] = $score_avg;
					$data['option_index'] = $option_index;
					$data['avg'] = $avg;
					$data['avg_index'] = $avg_index;
					$data['total'] = $total;
					$data['options'] = $option_arr;
					$data['template_module'] = $template_module[$subject_id];

					/* 得分分数据处理 */
					$scroes = array();
					foreach ($grade as $k => $v) {
						$scroes[] = intval($v['scroe']);
					}
					$data['scroes'] = $scroes;
					$data['subject_name'] = $subject_names[$subject_id];

					$output = $this->load->view('report/interview_module/layout', $data, true);

					/**************************** 3.写入html文件 ****************************/
					$html_file_name = $subject_id;
					$uid = $student['uid'];
					$res = $this->_put_interview_html_content($rule_id, $html_file_name, $uid, $output);

					/**************************** 4.写入生成pdf任务 ****************************/
					if ($res) {
						$pdf_file_name = $subject_names[$subject_id] . "面试";
						$now = time();
						$unique = urlencode(base64_encode("{$rule_id}-{$uid}-{$html_file_name}"));
						$source_url = C('public_host_name') . "/interview_report/{$unique}.html";
						$source_path = "zeming/interview_report/{$rule_id}/{$uid}/{$pdf_file_name}.pdf";
						$target_id = "{$rule_id}_{$uid}_{$html_file_name}";
						$convert2pdf_data[$target_id] = array(
								'type' 			=> '1',
								'source_url' 	=> $source_url,
								'source_path' 	=> $source_path,
								'ctime' 		=> $now,
								'mtime' 		=> $now,
								'target_id' 	=> $target_id,
						);
						
						//事先创建好保存zip目录
						$this->_mk_interview_zip_dir($rule_id);
					}

				}

			}

			/* 计算排名 (忽略分数相同的情况，如果分数相同，将按照添加到考试期次中的顺序排序)*/
                        $actual_ranking_result = usort($summary_array, function($a, $b){
                            if ($a['actual_score'] == $b['actual_score']) {
                                return 0;
                            }

                            return ($a['actual_score'] > $b['actual_score']) ?  -1 : 1;
                        });

			$count_student = count($summary_array);
			$level_index = array_values($level);
			$level_name = array_keys($level);
                        $summary_array = array_values($summary_array);

			if ( $count_student > 0 ) {
				foreach ($summary_array as $summary_index => $summary) {
					/* 分级 */
					$increment = 0;
					foreach ($level_index as $key => $level_percent) {
						$level_min = ceil($count_student * ($increment) / 100);
						$level_max = ceil($count_student * ($level_percent + $increment) / 100);
						$increment += $level_percent;

						if ($summary_index >= $level_min && $summary_index < $level_max) {
							$summary['level_name'] = $level_name[$key];
						}
					}

					/* 生成模板 */
					$output = $this->load->view('report/interview_summary_module/layout', $summary, true);
                                        
					/* 写入html */
					$html_file_name = '0';
					$uid = $summary['student_id'];
					$res = $this->_put_interview_html_content($rule_id, $html_file_name, $uid, $output);

					/* 写入pdf任务 */
					if ($res) {
						$pdf_file_name = "面试总结";
						$now = time();
						$unique = urlencode(base64_encode("{$rule_id}-{$uid}-{$html_file_name}"));
						$source_url = C('public_host_name') . "/interview_report/{$unique}.html";
						$source_path = "zeming/interview_report/{$rule_id}/{$uid}/{$pdf_file_name}.pdf";
						$target_id = "{$rule_id}_{$uid}_{$html_file_name}";
						$convert2pdf_data[$target_id] = array(
								'type' 			=> '1',
								'source_url' 	=> $source_url,
								'source_path' 	=> $source_path,
								'ctime' 		=> $now,
								'mtime' 		=> $now,
								'target_id' 	=> $target_id,
						);
						
						//事先创建好保存zip目录
						$this->_mk_interview_zip_dir($rule_id);
					}	
				}
			}

			/* ====================================================== */

			/* 保存 待生成pdf 数据 */
			if (count($convert2pdf_data))
			{
				$this->_save_convert2pdf_data($convert2pdf_data);
			}
		}
	}

		/**
	 * 生成面试zip压缩包
	 *
	 * @return void
	 */
	public function general_interview_zip()
	{
		$this->load->model('cron/report/interview_task_report_model', 'task_model');

		//获取待处理的评估规则
		$rule_ids = $this->task_model->get_doing_tasks(array(1,2));

		if (!count($rule_ids)) {
			return false;
		}
	
		$insert_data = array();
		$update_data = array();
		$data = array();
	
		//开启日志记录
		$this->config->set_item('log_threshold', '3');
	
		$pdf_path = C('html2pdf_path');

		foreach ($rule_ids as $item) {

			$rule_id = $item['rule_id'];
	
			/* 考试期次 */
			$rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
			$exam_id = $rule['exam_pid'];

			$students = array();
			$sql = "select distinct student_id as uid from {pre}interview_result where exam_id={$exam_id}";
			$students = $this->db->query($sql)->result_array();

			if (!count($students)) {
			    continue;
			}

			foreach ($students as $student) {
                                $uid = $student['uid'];

                                if (empty($uid)) {
                                    continue;
                                }

				$zip_dir = $pdf_path . "/zeming/interview_report/{$rule_id}/{$uid}";
				$file_path = $pdf_path . "/zeming/interview_report/{$rule_id}/{$uid}";

				if (file_exists($file_path)) {
					/* 开始打包 */
					$save_file = $this->_get_cache_root_path() . "/zip/interview_report/{$rule_id}/{$uid}.zip";

					$info = $this->_zip($save_file, $file_path, $zip_dir);

					if ($info != true)
					{
						//压缩失败，保存日志
						$msg = array(
							"保存路径：{$save_file}",
							"压缩文件/目录：{$zip_dir}",
							"失败原因：{$info}"
						);
						log_message('info', implode("\n------------\n", $msg));
					}
				}
			}
		}
	}

		/**
	 * 检查 面试报告生成情况
	 *
	 * @return void
	 */
	public function check_interview_stat()
	{
		/* global */
		//$this->load->model('admin/evaluate_rule_model');
		$subject_names = C('subject');

		//获取待处理的评估规则
		$this->load->model('cron/report/interview_task_report_model', 'task_model');
		$rule_ids = $this->task_model->get_doing_tasks(array(1,2));

		if (!count($rule_ids)) {
			return false;
		}
		
		$pdf_path = C('html2pdf_path');

		foreach ($rule_ids as $item) {

			//获取该规则信息
			$rule = EvaluateRuleModel::get_evaluate_rule($item['rule_id']);

			if (!count($rule) || $rule['is_delete'])
			{
				continue;
			}

			//考试期次
			$exam_id = $rule['exam_pid'];
			$rule_id = $item['rule_id'];
			$finish_part = false;//pdf 是否开始处理
			$zip_all_ready = true;//zip 是否都已经处理完成

			/* 评分项 期次->评分标准关联->评分标准->学科->评分项*/
			$sql = "select es.options from {pre}evaluation_standard as es left join {pre}evaluation_standard_exam as ese on es.id=ese.standard_id where ese.exam_id={$exam_id};";
			$option_str = $this->db->query($sql)->row_array();
            $options = json_decode($option_str['options']);
			$subjects = array_keys((array)$options);

			if (!count($option_str)) {
				continue;
			}

			if (!count($subjects)) {
				continue;
			}
			
			//获取该规则关联的考生
			$result = $this->db->query("select exam_ids, uids from {pre}evaluate_student where rule_id={$rule_id}")->row_array();

			if (!count($result)) {
				continue;
			}

			$uids = explode(',', $result['uids']);

			if (!count($uids)) {
				continue;
			}

			foreach ($uids as $uid) {

				foreach ($subjects as $subject) {
					//检查该考生的 pdf 生成情况
					$file_name = $subject_names[$subject];
					$path = "{$pdf_path}/zeming/interview_report/{$rule_id}/{$uid}/{$file_name}.pdf";

					if (file_exists($path)) {
						$finish_part = true;
					}
				}

				//检查该考生的 zip 生成情况		
				$zip_path = $this->_get_cache_root_path() . "/zip/interview_report/{$rule_id}/{$uid}.zip";

				if (!file_exists($zip_path)) {
					$zip_all_ready = false;
				}
			}
			
			if ($zip_all_ready) {
				//已完成 3
				$this->task_model->set_task_done($rule_id);
			} elseif ($finish_part && !$zip_all_ready) {
				//部分完成 2
				$this->task_model->set_task_part_done($rule_id);
			}
		}
	}
	
	
	/**
	 * 生成html文件
	 * @note:
	 * 	保存路径：cache/html/{$rule_id}/{uid}/{$subject_id}.html
	 */
	private function _put_html_content($rule_id, $subject_id, $uid, $html)
	{
		$html = chr(0xEF).chr(0xBB).chr(0xBF).$html;
		
		$file_path = $this->_get_cache_root_path() . "/html/report/{$rule_id}/{$uid}";
		if (!is_dir($file_path))
		{
			mkdir($file_path, '0777', true);
		}
		
		$res = file_put_contents($file_path."/{$subject_id}.html", $html);
		
		return $res > 0;
	}

	/**
	 * 生成面试html文件
	 * @note:
	 * 	保存路径：cache/html/interview_report/{$rule_id}/{uid}/{$subject_id}.html
	 */
	private function _put_interview_html_content($rule_id, $subject_id, $uid, $html)
	{
		$html = chr(0xEF).chr(0xBB).chr(0xBF).$html;
		
		$file_path = $this->_get_cache_root_path() . "/html/interview_report/{$rule_id}/{$uid}";
		if (!is_dir($file_path))
		{
			mkdir($file_path, '0777', true);
		}
		
		$res = file_put_contents($file_path."/{$subject_id}.html", $html);
		
		return $res > 0;
	}
	
	/**
	 * 创建保存pdf目录
	 * @note:
	 * 	保存路径：cache/pdf/{$rule_id}/{$uid}/
	 * 
	 * @notice
	 * 	 目前pdf文件保存在 /home/HTML2PDFServer/
	 */
	private function _mk_pdf_dir($rule_id, $uid)
	{
		try 
		{
			$file_path = $this->_get_cache_root_path() . "/pdf/report/{$rule_id}/{$uid}";
			if (!is_dir($file_path))
			{
				@mkdir($file_path, '0777', true);
			}
		} 
		catch(Exception $e)
		{
// 			echo $e->getMsg();	
		}
	}
	
	/**
	 * 创建保存zip目录
	 * @note:
	 * 	保存路径：cache/zip/{$rule_id}/
	 */
	private function _mk_zip_dir($rule_id)
	{
		try 
		{
			$file_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}";
			if (!is_dir($file_path))
			{
				@mkdir($file_path, '0777', true);
			}
		} 
		catch(Exception $e)
		{
// 			echo $e->getMsg();	
		}
	}

		/**
	 * 创建保存zip目录
	 * @note:
	 * 	保存路径：cache/zip/{$rule_id}/
	 */
	private function _mk_interview_zip_dir($rule_id)
	{
		try 
		{
			$file_path = $this->_get_cache_root_path() . "/zip/interview_report/{$rule_id}";
			if (!is_dir($file_path))
			{
				@mkdir($file_path, '0777', true);
			}
		} 
		catch(Exception $e)
		{
// 			echo $e->getMsg();	
		}
	}
	
	/**
	 * 获取项目根目录
	 */
	private function _get_cache_root_path()
	{
		return realpath(dirname(APPPATH)) . '/cache';
	}
	
	/**
	 * 保存 待生成pdf 数据
	 */
	private function _save_convert2pdf_data($data)
	{
		if (!count($data))
		{
			return false;
		}
		
		$target_ids = array();
		foreach ($data as $item)
		{
			$target_ids[] = '"'.$item['target_id'].'"';
		}
		
		//获取旧的数据
		$t_target_ids = implode(',', $target_ids);
		$sql = "select id, target_id, num, status,is_success from {pre}convert2pdf where target_id in ({$t_target_ids})";
		$result = $this->db->query($sql)->result_array();
		
		$update_data = array();
		foreach ($result as $item)
		{
			$id = $item['id'];
			$target_id = $item['target_id'];
			$num = $item['num'];
			$status = $item['status'];
			$is_success = $item['is_success'];
			
			if ($status == '0' || ($is_success == '0' && $num >= 10))
			{
				$update_data[] = array(
								'id'			=> $id,
								'source_url' 	=> $data[$target_id]['source_url'],
								'source_path' 	=> $data[$target_id]['source_path'],
								'status' 		=> '0',
								'num' 			=> '0',
				);
			}
			
			unset($data[$target_id]);
		}
		
		if (count($data)) {
			$this->db->insert_batch('convert2pdf', $data);
		}
		
		if (count($update_data)) {
			$this->db->update_batch('convert2pdf', $update_data, 'id');
		}
	}
	
	/**
	 * 保存该规则关联的考生
	 * @param int $rule_id
	 * @param array $uids
	 * @param array $exam_ids
	 */
	private function _save_student($rule_id, $uids, $exam_ids)
	{
		$rule_id = intval($rule_id);
		if (!$rule_id)
		{
			return false;
		}
		
		$data = array('rule_id' => $rule_id, 'uids' => implode(',', $uids), 'exam_ids' => implode(',', $exam_ids));
		
		$this->db->replace('evaluate_student', $data);
	}
	
	/**
	 * 压缩文件
	 * @param string $save_file 压缩后保存的压缩文件名
	 * @param string $zip_dir 待压缩文件或路径
	 * @param string $remove_path 保存文件 移除路径部分
	 * @note:
	 * 	 参数：
	 * 		$zip_dir 为路径时： $files = dirname(BASEPATH).'/temp/';
	 *      $zip_dir 为多个文件时： 
	 *      			//$files = array('mystuff/ad.gif','mystuff/alcon.doc','mystuff/alcon.xls');
						//$files = 'mystuff/ad.gif,mystuff/alcon.doc,mystuff/alcon.xls';
	 */
	private function _zip($save_file, $zip_file, $remove_path)
	{
		require_once APPPATH.'libraries/Pclzip.php';
		
		$archive = new PclZip($save_file);
		
		//将文件进行压缩
		$res = $archive->create($zip_file, PCLZIP_OPT_REMOVE_PATH, $remove_path);
		 	
// 		pr($res,1);
		return $res == '0' ? ($archive->errorInfo(true)) : true;
	} 
	
	/**
	 * 获取 考试学科名称
	 * @param unknown $exam_ids
	 */
	private function _get_exam_names($exam_ids = array())
	{
		if (!count($exam_ids))
		{
			return array();
		}
		
		$exam_ids = implode(',', $exam_ids);
		$sql = "select subject_id, exam_id from {pre}exam where exam_id in ($exam_ids)";
		$result = $this->db->query($sql)->result_array();
		$data = array();
		$subjects = C('subject');
		foreach ($result as $item)
		{
			$exam_id = $item['exam_id'];
			$subject_id = $item['subject_id'];
			
			$data[$exam_id] = isset($subjects[$subject_id]) ? $subjects[$subject_id] : 'xx'; 
		}
		
		return $data;
	}

	/**
	 * 计算时间差
	 *
	 * @param string $startTime unix时间戳
	 * @param string $entTime unix时间戳
	 * @return  string
	 */
	private function timeDiff($startTime, $endTime)
	{	
		$str = '';
		$timeDiff = abs($endTime - $startTime);
		$minute = ceil($timeDiff/60);

		$str = $minute.'分钟';

		// if ($minute <= 60)
		// {
		// 	$str = $minute.'分';
		// }
		// else
		// {
		// 	$hour = floor($minute/60);
		// 	$minute = floor($minute%60);

		// 	if ($hour <= 24)
		// 	{
		// 		$str = $hour.'小时'.$minute.'分';
		// 	}
		// 	else
		// 	{
		// 		$day = floor($hour/24);
		// 		$hour = floor($hour%24);

		// 		$str = $day.'天'.$hour.'小时'.$minute.'分';
		// 	}
		// }

		return $str;

	}
	
    /**
     * 生成测评报告模板->html
     */
    public function general_to_html2()
    {
        //获取待处理的评估规则
        $rule_ids = $this->db->get_where('rd_cron_task_report', 
                        array('status'=>0))->result_array();
        if (!count($rule_ids))
        {
            return false;
        }
        
        $convert2pdf_data = array();
        foreach ($rule_ids as $item)
        {
            $status = $item['status'];
            $rule_id = $item['rule_id'];
            $ctime = $item['ctime'];
            	
            //获取该规则信息
            $rule = $this->db->get_where('rd_evaluate_rule', 
                        array('id' => $rule_id))->row_array();
            if (!$rule || $rule['is_delete'])
            {
                continue;
            }
           
            //考试期次
            $exam_pid = $rule['exam_pid'];
            
            //检查该期考试的所有考场都已经结束
            if ($this->_check_exam_status($exam_pid))
            {
               continue;
            }
        	
            //生成学科(0:各学科总结)
            $g_subject_id = $rule['subject_id'];
            
            //生成模式
            $generate_mode = $rule['generate_mode'];
            //单人模式下需要生成报告的考生
            $generate_uid = $rule['generate_uid'];
            
            //待生成报告的考生
            $uids = $this->_evaluate_student($exam_pid, $generate_mode, $generate_uid);
            if (!count($uids))
            {
               continue;
            }
        	
            //获取该期考试的考试学科
            $exam_ids = array();
            $subject_exams = array();
            $exam_subjects = array();
            $this->_exam_subject($exam_pid, $g_subject_id, $exam_ids, $subject_exams, $exam_subjects);
            if (!count($exam_ids))
            {
                continue;
            }
            
            //当前评估规则和考试期次
            self::$_rule_id = $rule_id;
            self::$_exam_pid = $exam_pid;
            
            $template_module = $this->_evaluate_template($item['template_id']);//评估模板
        	
            //将该规则的计划任务设置为 处理中
            if ($status == '0')
            {
                $this->db->update('rd_cron_task_report',
                        array('status'=>1), "rule_id = " . $rule_id);
            }
            
            //保存该规则对应的考生
            $this->_save_student($rule_id, $uids, $exam_ids);
          
            //获取生成报告的考试名称
            $exam_name = $this->_exam_name($exam_pid);
            
            //学生姓名
            $student_list = $this->_student_name($uids);
            
            //开始生成 模板
            foreach ($uids as $uid)
            {
                self::$_uid = $uid;
                
                foreach ($exam_ids as $exam_id)
                {
                    self::$_exam_id = $exam_id;
                    
                    self::$_data = array();
            
                    self::$_data['studentName'] = $student_list[$uid];
                    self::$_data['ctime'] = $ctime;
                    self::$_data['examName'] = $exam_name;
                    self::$_data['t_subject_id'] = $exam_id == '0' ? '0' : $exam_subjects[$exam_id];
                    self::$_data['subject_name'] = $exam_id == '0' ? '总结' : C('subject/'.$exam_subjects[$exam_id]);
            
                    if ($exam_id == 0)
                    {   //总结学科
                        $output = $this->_general_summary_html($template_module);
                    }
                    else
                    {   //单学科
                        if (!$this->_exam_time()) //考场,考试时间
                        {
                            continue;
                        }
                        
                        $output = $this->_general_subject_html($template_module);
					}
			
					//保存html模板
					$subject_id = isset($exam_subjects[$exam_id]) ? $exam_subjects[$exam_id] : 0;
					
					$res = $this->_put_html_content($rule_id, $subject_id, $uid, $output);
					
					if ($res)
					{
						$now = time();
						$k = urlencode(base64_encode("{$rule_id}-{$uid}-{$subject_id}"));
						$source_url = C('public_host_name') . "/report/{$k}.html";
						$source_path = "zeming/report/{$rule_id}/{$uid}/{$subject_name}.pdf";
						$target_id = "{$rule_id}_{$uid}_{$subject_id}";
						$convert2pdf_data[$target_id] = array(
								'type' 			=> '1',
								'source_url' 	=> $source_url,
								'source_path' 	=> $source_path,
								'ctime' 		=> $now,
								'mtime' 		=> $now,
								'target_id' 	=> $target_id,
						);

						//事先创建好保存zip目录
						$this->_mk_zip_dir($rule_id);

						//保存 待生成pdf 数据
						$this->_save_convert2pdf_data($convert2pdf_data);
						unset($convert2pdf_data);
					}
				}
			}
        }
    }
    
    /**
     * 获取需要生产报告的学生
     * @param    int    $exam_pid       考试期次id
     * @param    int    $generate_mode  报告生成模式
     * @param    int    $generate_uid   单人模式生成报告的UID
     * @return   array  $uids
     */
    private function _evaluate_student($exam_pid, $generate_mode = 0, $generate_uid = 0)
    {
        $uids = array();
        
        if ($generate_mode == '1')
        {   //单人模式
            //检查该学生是否作弊
            $result = $this->db->select('count(*) as count')->get_where('exam_test_paper',
                    array('exam_pid'=>$exam_pid, 'uid'=>$generate_uid, 'etp_flag'=>-1))->row_array();
            if (!$result['count'])
            {
                $uids[] = $generate_uid;
            }
        }
        else
        {   //批量模式
            //获取已生成成绩未作弊的考生
            $result = $this->db->select('distinct(uid)')->get_where('exam_test_paper',
                    array('exam_pid'=>$exam_pid, 'etp_flag'=>2))->result_array();
            foreach ($result as $item)
            {
                $uids[] = $item['uid'];
            }
        }
        
        return $uids;
    }
    
    /**
     * 考试的学科
     * @param   int    $exam_pid     考试期次id
     * @param   int    $g_subject_id 规则所对应的学科
     * @param   array  $exam_ids     考试学科
     * @param   array  $subject_exams 学科对应的考试
     * @param   array  $exam_subjects 考试对应的学科  
     */
    private function _exam_subject($exam_pid, $g_subject_id, &$exam_ids, &$subject_exams, &$exam_subjects)
    {
        $result = $this->db->select('subject_id, exam_id')->get_where('exam',
                array('exam_pid'=>$exam_pid))->result_array();
        foreach ($result as $item)
        {
            $subject_exams[$item['subject_id']] = $item['exam_id'];
            $exam_subjects[$item['exam_id']] = $item['subject_id'];
        }
         
        if ($g_subject_id == 0)
        {
            //总结
            $exam_ids = array_values($subject_exams);
            $exam_ids[] = 0;
        }
        else
        {
            //单学科
            if (!isset($subject_exams[$g_subject_id]))
            {
                continue;
            }
        
            $exam_ids[] = $subject_exams[$g_subject_id];
        }
    }
    
    /**
     * 生成学科报告
     * @param array     $template_module
     * @return string
     */
    private function _general_subject_html($template_module)
    {
        $this->load->model('cron/report/subject_comparison_info_model', 'sci_model');
        $this->load->model('cron/report/subject_three_dimensional_model', 'std_model');
        $this->load->model('cron/report/subject_question_model', 'sq_model');
        $this->load->model('cron/report/subject_suggest_model', 'ss_model');
        
        $data = self::$_data;
        
        $t_subject_id = $data['t_subject_id'];
        $rule_id = self::$_rule_id;
        $exam_pid = self::$_exam_pid;
        $exam_id = self::$_exam_id;
        $uid = self::$_uid;
        $output = '';
         
        if (!empty($template_module[$t_subject_id]['module'])
            && $template_module[$t_subject_id]['template_type'] == 1)
        {
            $data['template_id'] = $template_module[$t_subject_id]['template_id'];
        
            $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
            if ($include_subject && !in_array($t_subject_id, $include_subject))
            {
                continue;
            }
        
            $output = $this->load->view('report/subject_module/subject', $data, true);
            $g_sort = 0;
            foreach ($template_module[$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }
         
                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if ($value['module_type'] != 1)
                    {
                        continue;
                    }
                             
                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));
                 
                    if ($value['module_subjectid'])
                    {
                        $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                        if ($module_subjectids
                            && !in_array($t_subject_id, $module_subjectids))
                        {
                            continue;
                        }
                    }
        
                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
        
                    if ($module_code == 'sci_exam_info')
                    {
                        $data['sci_exam_info'] = $this->sci_model->module_exam_info($exam_pid, $t_subject_id);// 考试说明
                    }
                    else if ($module_code == 'ss_match_percent')
                    {
                        if (!isset($data['sq_all']))
                        {
                            $data['sq_all'] = $this->sq_model->module_all($rule_id, $exam_id, $uid);
                        }
                    
                        $data[$module_code] = $this->$_module_model->$_module_func($exam_id, $data['sq_all']['desc_data']);
                    }
                    
                    if (!isset($data[$module_code]))
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid);
                    }
                         
                    if (!empty($data[$module_code]))
                    {
                        //显示一级模块
                        if ($c_sort == 0)
                        {
                            $g_sort++;
                    
                            if ($module_pcode == 'sci'
                                && !isset($data['sci_knowledge']))
                            {
                                $data['sci_knowledge'] = $this->sci_model->module_knowledge($rule_id, $exam_id, $uid);
                            }
                    
                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];
                             
                            $output .= $this->load->view("report/subject_module/$module_pcode/$module_pcode", $data, true);
                        }
                     
                        $c_sort++;
                         
                        $data['c_sort'] = $c_sort;
                        $data['module_name'] = $value['module_name'];
                         
                        $output .= $this->load->view("report/subject_module/$module_pcode/$module_code", $data, true);
                    }
                }
            }
                    
            $output .= $this->load->view('report/subject_module/footer', $data, true);
        }
        
        return $output;
    }
    
    /**
     * 生成学科总结报告
     * @param array     $template_module
     * @return string
     */
    private function _general_summary_html($template_module)
    {
        $this->load->model('cron/report/complex_model', 'c_model');
        
        $data = self::$_data;
        
        $t_subject_id = $data['t_subject_id'];
        $output = '';
        
        if (!empty($template_module[$t_subject_id]['module'])
            && !$template_module[$t_subject_id]['template_type'])
        {
        
            $data['know_process'] = C('know_process');
            $data['subject'] = C('subject');
        
            $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
        
            $output = $this->load->view('report/complex_module/c', $data, true);
            
            $g_sort = 0;
            foreach ($template_module[$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }
                 
                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if ($value['module_type'])
                    {
                        continue;
                    }
        
                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));
                     
                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
        
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
        
                    $data[$module_code] = $this->$_module_model->$_module_func(self::$_rule_id, 
                                            self::$_exam_pid, self::$_uid, $include_subject);
                     
                    if (!empty($data[$module_code]))
                    {
                        if ($c_sort == 0)
                        {
                            $g_sort++;
        
                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];
                        }
        
                        $c_sort++;
                         
                        $data['c_sort'] = $c_sort;
        
                        $data['module_name'] = $value['module_name'];
                         
                        $output .= $this->load->view("report/complex_module/$module_code", $data, true);
                    }
                }
            }
            
            $output .= $this->load->view('report/complex_module/footer', $data, true);
        }
        
        return $output;
    }
    
    /**
     * 检查考试是否已结束
     * @param   int     $exam_pid   考试期次id
     * @return  boolean
     */
    private function _check_exam_status($exam_pid)
    {
        $result = $this->db->select('count(*) as count')->get_where('exam_place',
                array('end_time >='=>time() , 'exam_pid'=>$exam_pid))->row_array();
        
        return $result['count'] > 0;
    }
    
    /**
     * 生成报告的考试名称
     * @param   int     $exam_pid   考试期次id
     * @return  string  考试名称
     */
    private function _exam_name($exam_pid)
    {
        $exam = $this->db->select('exam_name')->get_where('exam', 
                        array('exam_id'=>$exam_pid))->row_array();
        return $exam['exam_name'];
    }
    
    /**
     * 考试考场时间
     * @return boolean  true|false
     */
    private function _exam_time()
    {
        $exam_id = self::$_exam_id;
        $uid = self::$_uid;
        $exam_pid = self::$_exam_pid;
        
        $sql = "select ep.place_id,ep.place_name,ep.start_time,ep.end_time
                from {pre}exam_place ep
                LEFT JOIN {pre}exam_test_paper etp ON ep.place_id = etp.place_id
                where etp.exam_id={$exam_id} and etp.uid={$uid}";
        $result = $this->db->query($sql)->row_array();
        if (!$result)
        {
            return false;
        }
        
        self::$_data['placeName'] = $result['place_name'];
        self::$_data['startTime'] = date('Y年m月d日',$result['start_time']);
        
        //考试时长
        self::$_data['timeInterval'] = $this->timeDiff($result['start_time'], $result['end_time']);
        
        //开始考试时间
        $sql = "select ctime from {pre}exam_logs where exam_id={$exam_pid}
                and uid={$uid} and place_id=" . intval($result['place_id']) . " and type=1";
        $startTime = $this->db->query($sql)->row_array();
        if (!empty($startTime['ctime']))
        {
            $startTime['ctime'] = strtotime($startTime['ctime']);
            if ($result['start_time'] > $startTime['ctime'])
            {
                $startTime['ctime'] = $result['start_time'];
            }
        }
        else
        {
            $startTime['ctime'] = $result['start_time'];
        }
        self::$_data['examStartTime'] = date('H:i',$startTime['ctime']);
        
        //结束考试时间
        $sql = "select ctime from {pre}exam_logs where exam_id={$exam_pid} and uid={$uid}
                and place_id=" . intval($result['place_id']) . "  and type=2";
        $endTime = $this->db->query($sql)->row_array();
        if (empty($endTime['ctime']))
        {
            $endTime['ctime'] = $result['end_time'];
        }
        else
        {
            $endTime['ctime'] = strtotime($endTime['ctime']);
        }
        self::$_data['examEndTime'] = date('H:i', $endTime['ctime']);
        
        //考试用时
        self::$_data['examTimeInterval'] = $this->timeDiff($startTime['ctime'], $endTime['ctime']);
        if (intval(self::$_data['examTimeInterval']) > intval(self::$_data['timeInterval']))
        {
            self::$_data['examTimeInterval'] = self::$_data['timeInterval'];
        }
        
        return true;
    }
    
    /**
     * 评估报告模板信息
     * @param   string     $template_id    评估模板ID JSON串
     * @return  array      $template_module
     */
    private function _evaluate_template($template_id)
    {
        $template_module = array();
        if ($template_id)
        {
            $template_data = json_decode($template_id, true);
            foreach ($template_data as $template_subject => $t_id)
            {
                $template_module[$template_subject] = $this->_evaluate_template_info($t_id);
            }
        }
        
        return $template_module;
    }
    
    /**
     * 获取评估模板详情
     * @param  int   $template_id   评估模板id
     * @return array $template      模板详情
     */
    private function _evaluate_template_info($template_id)
    {
        if (!$template_id)
        {
            return array();
        }
    
        $template = $this->db->get_where('evaluate_template',
                array('template_id'=>$template_id))->row_array();
        if ($template)
        {
            $sql = "SELECT * FROM {pre}evaluate_template_module etm
                    LEFT JOIN {pre}evaluate_module em ON etm.template_module_id = em.module_id
                    WHERE template_id = $template_id AND status = 1
                    ORDER BY template_module_sort ASC";
            $module = $this->db->query($sql)->result_array();
            $module_list = array();
            foreach ($module as $item)
            {
                if ($item['parent_moduleid'] == 0)
                {
                    $module_list[] = $item;
                }
            }
    
            foreach ($module as $item)
            {
                foreach ($module_list as &$val)
                {
                    if ($item['parent_moduleid'] == $val['module_id'])
                    {
                        $val['children'][$item['module_id']] = $item;
                    }
                }
            }
    
            $template['module'] = $module_list;
        }
    
        return $template;
    }
    
    /**
     * 学生姓名
     * @param   array   $uids  生成报告的学生id
     * @return  array   $student_list
     */
    private function _student_name($uids)
    {
        $student = $this->db->select("uid, last_name, first_name")->from('student')
                        ->where_in('uid', $uids)->group_by('uid')->get()->result_array();
        $student_list = array();
        foreach ($student as $stu)
        {
            $student_list[$stu['uid']] = $stu['last_name'] . $stu['first_name'];
        }
        
        return $student_list;
    }
}
