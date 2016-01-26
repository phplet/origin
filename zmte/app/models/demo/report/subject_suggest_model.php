<?php if ( ! defined('BASEPATH')) exit();
/**
 *
 * 测评报告-学科-诊断及建议
 * @author TCG
 * @final 2015-07-21
 */
class Subject_suggest_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();

		$this->load->model('demo/report/common_model');
	}

	/**
	 * 总体水平等级和排名
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
	public function module_summary($exam_id = 0, $uid = 0)
	{
		$exam_id = intval($exam_id);
		$uid = intval($uid);
		if (!$exam_id || !$uid)
		{
			return array();
		}

		//对比等级(总体：国家)
		$comparison_levels = array('0');

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

		//获取考试总人数
		$sql = "select count(distinct(uid)) as total 
		          from {pre}summary_region_student_rank 
		          where exam_id={$exam_id} and region_id=1 and is_school=0";
		$result = $this->db->query($sql)->row_array();
		$total = $result['total'];
		$data['total'] = $total;

		//获取总体的参考人数
		$sql = "select count(distinct(uid)) as total 
		      from {pre}summary_region_student_rank 
		      where exam_id={$exam_id}";
		foreach ($comparison_levels as $comparison_level)
		{
			$tmp_sql = '';
			$cl_name = '';//总体名称
			switch ($comparison_level)
			{
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

				case '0'://国家
				    $tmp_sql = $sql;
				default:
					break;
			}

			if ($tmp_sql == '')
			{
				continue;
			}

			//总体人数
			$result = $this->db->query($tmp_sql)->row_array();
			$total = $result['total'];

			$k = $cl_name . ' 人数';
			$fields[] = $k;
			$summary[$k] = $total;
		}
		
		$data['summary'] = $summary;
		
		//获取我在本期考试中的排名
		$sql = "select rank from {pre}summary_region_student_rank 
		          where exam_id={$exam_id} and region_id=1 
		          and is_school=0 and uid={$uid}";
		$result = $this->db->query($sql)->row_array();
		$rank = isset($result['rank']) ? $result['rank'] : 0;
		$win_percent = $data['total'] <= 0 ? 100 : round((($data['total'] - $rank + 1)/$data['total'])*100);
		$data['win_percent'] = $win_percent;

		//判断该学生是否为最后一名
		$sql = "select max(rank) as last_rank 
		          from {pre}summary_region_student_rank 
		          where exam_id={$exam_id} and region_id=1 and is_school=0";
		$result = $this->db->query($sql)->row_array();
		$last_rank = $result['last_rank'];
		$data['is_last_rank'] = $last_rank == $rank && $rank > 1;

		$data['level'] = $level = $this->common_model->convert_percent_level($win_percent);;

		return $data;
	}

	/**
	 * 强弱点分布情况
	 * @param number $exam_id 考试学科
	 * @param number $uid 考生ID
	 */
	public function module_application_situation($exam_id = 0, $uid = 0)
	{
		$exam_id = intval($exam_id);
		$uid = intval($uid);
		if (!$exam_id || !$uid)
		{
			return array();
		}

		$data = array();

		//获取该学生所考到试题关联的答对 二级知识点 & 认知过程
		$sql = "select ssk.knowledge_id, k.knowledge_name, ssk.know_process_ques_id as right_know_process_ques_id, spk.know_process_ques_id
				from {pre}summary_student_knowledge ssk
				left join {pre}summary_paper_knowledge spk on ssk.paper_id=spk.paper_id and ssk.knowledge_id=spk.knowledge_id
				left join {pre}knowledge k on ssk.knowledge_id=k.id
				where ssk.exam_id={$exam_id} and ssk.uid={$uid} and ssk.is_parent=0 order by k.pid,k.id asc
				";

		$result = $this->db->query($sql)->result_array();

		$know_processes = C('know_process');

		foreach ($result as $item) {
			$knowledge_id = $item['knowledge_id'];
			/* 兼容json与序列化 */
			$know_process_ques_id = !is_null(json_decode($item['know_process_ques_id'], true)) ? json_decode($item['know_process_ques_id'], true) : unserialize($item['know_process_ques_id']);//该二级知识点关联的 认知过程 试题
			$right_know_process_ques_id = !is_null(json_decode($item['right_know_process_ques_id'], true)) ? json_decode($item['right_know_process_ques_id'], true) : unserialize($item['right_know_process_ques_id']);//考生答对情况
			$knowledge_name = $item['knowledge_name'];

			$know_process_ques_id = is_array($know_process_ques_id) ? $know_process_ques_id : array();
			$right_know_process_ques_id = is_array($right_know_process_ques_id) ? $right_know_process_ques_id : array();

			//遍历认知过程，计算强弱点分布情况
			/*
			 * X = (答对题数/总题数)*100
			 */
			foreach ($know_processes as $know_process=>$kp_name)
			{
				$all_questions = isset($know_process_ques_id[$know_process]) ? count($know_process_ques_id[$know_process]) : 0;
				$right_questions = isset($right_know_process_ques_id[$know_process]) ? count($right_know_process_ques_id[$know_process]) : 0;

				$strength = !$all_questions ? '-1' : round(($right_questions/$all_questions)*100);

				$data[$knowledge_name]['kp_'.$know_process] = $strength;
			}
		}

		return $this->_convert_application_situation($data);
	}

	/**
	 * 为分布规则附加评语
	 */
	private function _convert_application_situation(&$data)
	{
// 		pr($data,1);
		foreach ($data as $key=>$item)
		{
			$comment = '';
			$tmp_arr = array();
			foreach ($item as $kp=>$strength)
			{
				list($name, $current_kp) = explode('_', $kp);
				$prev_strength = $current_kp == 1 ? 0 : $item['kp_'.($current_kp-1)];
				$first_strength = $item['kp_1'];
				$last_strength = $item['kp_3'];
				$comment = $this->_level_diff_note($first_strength, $prev_strength, $strength, $last_strength, $current_kp, $comment);
				$tmp_arr[$kp] = $this->_convert_strength_to_level($strength, $current_kp);
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
	private function _convert_strength_to_level($strength, $know_process)
	{
		if ($strength < 0) return '0';

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

		$level_diff = abs($current_level - $prev_level);
		$comment = $comments[$current_kp][$current_level];

	switch ($level_diff)
		{
			case '0':
				if ($current_kp == '3' || ($last_strength < 0 && $current_kp == '2'))
				{
					$current_note .= '，并且' . $comment;
				}
				else
				{
					if ($current_kp == '2' && $first_strength < 0)
					{
						$current_note .= $comment;
					}
					else
					{
						$current_note .= '，' . $comment;
					}
				}
				break;
			case '1':
				if (($current_kp == '2' && $first_strength < 0) || ($current_kp == '3' && $first_strength < 0 && $prev_strength < 0))
				{
					$current_note .= $comment;
				}
				else
				{
					$current_note .= '，' . $comment;
				}
				break;
			case '2':
				if ($current_kp == 2 && $first_strength < 0)
				{
					$current_note .= $comment;
				}
				else
				{
					if ($current_kp == 3 && $first_strength < 0 && $prev_strength < 0)
					{
						$current_note .= $comment;
					}
					else
					{
						if (stripos($current_note, '但') === false)
						{
							$current_note .= '，但' . $comment;
						}
						else
						{
							$current_note = str_ireplace('但', '却', $current_note);
							$current_note .= '，但' . $comment;
						}
					}
				}
				break;
			case '3':
				if ($current_kp == 2)
				{
					$current_note .= $comment;
				}
				elseif($current_kp == 3)
				{
					if ($first_strength < 0)
					{
						$current_note .= $comment;
					}
					else
					{
						$level_diff = abs($current_level - $first_level);
						switch ($level_diff)
						{
							case '0':
								$current_note .= '，并且' . $comment;
								break;
							case '1':
								$current_note .= '，' . $comment;
								break;
							case '2':
								$current_note .= '，但' . $comment;
								break;
						}
					}
				}
			default:
				break;
		}

		return $current_note;
	}

}