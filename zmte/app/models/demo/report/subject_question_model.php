<?php if ( ! defined('BASEPATH')) exit();
/**
 *
 * 测评报告-学科-试题分析及与之对应的评价
 * @author TCG
 * @final 2015-07-21
 */
class Subject_question_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();

		$this->load->model('demo/report/common_model');
	}

	/**
	 * 获取 试题分析和评价
	 * @param number $exam_id 考试学科
	 * @param number $uid 考生ID
	 */
	public function get_all($exam_id = 0, $uid = 0)
	{
		$exam_id = intval($exam_id);
		$uid = intval($uid);
		if (!$exam_id || !$uid)
		{
			return array();
		}

		//列字段
		$fields = array('总分','得分');

		//数据
		$data = array();

		//数据
		$flash_data = array();

		//分析信息
		$desc_data = array();

		//获取该考生所考到的试卷题目
		$sql = "select etpq.ques_id, etpq.etp_id, etp.paper_id
				from {pre}exam_test_paper_question etpq
				left join {pre}exam_test_paper etp on etpq.etp_id=etp.etp_id
				where etp.exam_id={$exam_id} and etp.uid={$uid} and etp.etp_flag=2
				";

		$result = $this->db->query($sql)->row_array();
		if (!isset($result['ques_id']))
		{
			return array();
		}

		$paper_id = $result['paper_id'];
		$etp_id = $result['etp_id'];
		$paper_questions = $result['ques_id'];
		$ques_ids = @explode(',', $paper_questions);
		if (!is_array($ques_ids) || !count($ques_ids))
		{
			return array();
		}

		//对比等级(总体：国家)
		$comparison_levels = array('0');

		//获取该学生所在区域
		$student = $this->common_model->get_student_info($uid);

		$t_ques_id = implode(',', $ques_ids);

		//获取这些题目的得分
		$sql = "select ques_id, sum(full_score) as full_score, sum(test_score) as test_score
				from {pre}exam_test_result
				where etp_id={$etp_id} and ques_id in({$t_ques_id})
				group by ques_id
				";
		$result = $this->db->query($sql)->result_array();
		$ques_scores = array();
		foreach ($result as $item)
		{
			$ques_scores[$item['ques_id']] = array('full_score' => $item['full_score'], 'test_score' => $item['test_score']);
		}

		//获取这些题目的期望得分
		$sql = "select ques_id, expect_score
				from {pre}summary_paper_question
				where exam_id={$exam_id} and paper_id={$paper_id} and ques_id in({$t_ques_id})
				";
		$result = $this->db->query($sql)->result_array();
		$ques_expect_scores = array();
		foreach ($result as $item)
		{
			$ques_expect_scores[$item['ques_id']] = $item['expect_score'];
		}

		//获取这些题目的难易度
		$sql = "select rc.ques_id,rc.difficulty
				from {pre}relate_class rc
				left join {pre}exam e on rc.grade_id=e.grade_id and rc.class_id=e.class_id
				where e.exam_id={$exam_id} and rc.ques_id in({$t_ques_id})
				";
		$result = $this->db->query($sql)->result_array();
		$ques_difficulties = array();

		foreach ($result as $item) {
			$ques_difficulties[$item['ques_id']] = $item['difficulty'];
		}

		//试题关联二级知识点
		$relate_knowledges = array();

		/*
		 * 将这些题拆分为题组 和 非题组
		 */
		//题组部分
		$t_q_ids = implode(',', $ques_ids);
		$sql = "select ques_id, parent_id
				from {pre}question
				where parent_id in($t_q_ids)
				";

		$result = $this->db->query($sql)->result_array();

		$group_ques_id = array();
		$parent_ques_id = array();
		foreach ($result as $v)
		{
			$group_ques_id[] = $v['ques_id'];
			$parent_ques_id[$v['ques_id']] = $v['parent_id'];
		}

		//非题组
		$ug_ques_id = array_diff($ques_ids, $group_ques_id);
		foreach ($ug_ques_id as $val)
		{
			$group_ques_id[] = $val;
		}

		$t_sql = implode(',', array_unique($group_ques_id));
		$sql = "select rk.ques_id, k.knowledge_name, rk.know_process
				from {pre}relate_knowledge rk
				left join {pre}knowledge k on rk.knowledge_id=k.id
				where ques_id in($t_sql) and k.pid > 0 ";
		$result = $this->db->query($sql)->result_array();

		foreach ($result as $val)
		{
			$q_id = $val['ques_id'];
			$knowledge_name = $val['knowledge_name'];
			$know_process = $val['know_process'];
			$know_process_name = C('know_process/'.$know_process);

			if (isset($parent_ques_id[$q_id]) && $parent_ques_id[$q_id] > 0 && $knowledge_name != '')
			{
				$relate_knowledges[$parent_ques_id[$q_id]][$knowledge_name.'_'.$know_process_name] = array('knowledge' => $knowledge_name, 'know_process' => ($know_process_name == '' ? '--' : $know_process_name));
			}
			else
			{
				if ($knowledge_name != '')
				{
					$relate_knowledges[$q_id][$knowledge_name.'_'.$know_process_name] = array('knowledge' => $knowledge_name, 'know_process' => ($know_process_name == '' ? '--' : $know_process_name));
				}
			}
		}

		$auto_key = 0;
		foreach ($ques_ids as $ques_id)
		{
			$auto_key++;

			$ques_score = isset($ques_scores[$ques_id]) ? $ques_scores[$ques_id] : array('full_score' => 0, 'test_score' => 0);
			$tmp_arr = array(
							'总分' => $ques_score['full_score'],
							'得分' => $ques_score['test_score'],
			);

			//获取总体平均分
			$sql = "select avg_score from {pre}summary_region_question 
			         where exam_id={$exam_id} and ques_id={$ques_id}";
			foreach ($comparison_levels as $comparison_level)
			{
				$tmp_sql = '';
				$cl_name = '';//总体名称
				switch ($comparison_level)
				{
					case '0'://国家
						$tmp_sql = $sql . ' and region_id=1 and is_school=0';
						$cl_name = '全国';
						break;
					case '1'://省份
						$tmp_sql = $sql . ' and region_id=' . $student['province'] . ' and is_school=0';
						$cl_name = $student['region_'.$student['province']];
						break;
					case '2'://市
						$tmp_sql = $sql . ' and region_id=' . $student['city'] . ' and is_school=0';
						$cl_name = $student['region_'.$student['city']];
						break;
					case '3'://县区
						$tmp_sql = $sql . ' and region_id=' . $student['area'] . ' and is_school=0';
						$cl_name = $student['region_'.$student['area']];
						break;
					/*
					case '4'://街道/道路
						$tmp_sql = $sql . ' and region_id=' . $student[''] . ' and is_school=0';
						$cl_name = $student['region_'.$student['']];
						break;
					*/
					case '100'://学校
						$tmp_sql = $sql . ' and region_id=' . $student['school_id'] . ' and is_school=1';
						$cl_name = $student['school_name'];
						break;
					default:
						break;
				}

				if ($tmp_sql == '')
				{
					continue;
				}

				//总体平均分
				$result = $this->db->query($tmp_sql)->row_array();
				$avg_score = isset($result['avg_score']) ? $result['avg_score'] : 0;

				$k = $cl_name . '平均分';
				$fields[] = $k;
				$tmp_arr[$k] = $avg_score;
			}

			$k = "第 {$auto_key} 题";

			$data[$k] = $tmp_arr;

			//对应评价
			$tmp_desc_arr = array(
							'id'		 	=> $k,
							'full_score' 	=> $ques_score['full_score'],
							'test_score' 	=> $ques_score['test_score'],
							'expect_score' 	=> isset($ques_expect_scores[$ques_id]) ? $ques_expect_scores[$ques_id] : 0,
							'difficulty' 	=> isset($ques_difficulties[$ques_id]) ? $this->common_model->convert_question_difficulty($ques_difficulties[$ques_id]) : '--',
							'knowledge' 	=> isset($relate_knowledges[$ques_id]) ? $relate_knowledges[$ques_id] : array(),
			);
			
			if ((!$tmp_desc_arr['expect_score'] 
			    || $tmp_desc_arr['expect_score'] > $tmp_desc_arr['full_score']) 
			    && isset($ques_difficulties[$ques_id]))
			{
			    $difficulty = $ques_difficulties[$ques_id];
			    $tmp_desc_arr['expect_score'] = round($ques_score['full_score'] * $difficulty  / 100, 2);
			}

			$desc_data[] = $tmp_desc_arr;
		}
		
		return array(
					'fields' 		=> array_unique($fields),
					'data' 			=> $data,
					'desc_data'		=> $desc_data,
		);
	}
}