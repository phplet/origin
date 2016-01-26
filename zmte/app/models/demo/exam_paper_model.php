<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-试卷数据模块
 *
 * @author qcchen
 * @final 2013-12-05
 */
class Exam_paper_model extends CI_Model
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'exam_paper';
	private static $_table_paper_question = 'exam_question';
	private static $_table_question = 'question';
	private static $_table_relate_class = 'relate_class';

    /**
     * 构造函数，初始化
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取一个试卷信息
     *
     * @param   int     试卷ID(paper_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_paper_by_id($id = 0, $item = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $this->db->select($item);
        }
        $query = $this->db->get_where(self::$_table_name, array('paper_id' => $id));
        $row   =  $query->row_array();

        if (is_string($item) && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }


    public function get_paper_question_ids($paper_id)
    {
    	$list = array();
    	$query = $this->db->select('ques_id')->get_where(self::$_table_paper_question, array('paper_id'=>$paper_id));
    	foreach ($query->result_array() as $row)
    	{
    		$list[] = $row['ques_id'];
    	}
    	return $list;
    }

    /**
     * 获取一个试卷试题分组列表 （机考左侧试题导航）
     *
     * @param   int     试卷ID(paper_id)
     * @return  array
     */
    public function get_paper_question_detail($paper_id)
    {
    	static $results = array();
    	if (isset($results[$paper_id])) return $results[$paper_id];

    	// get exam info
    	$exam_id = $this->get_paper_by_id($paper_id, 'exam_id');

    	$exam = $this->db
    				 ->select('exam_id, subject_id, grade_id, class_id, total_score, qtype_score')
    				 ->get_where('exam', array('exam_id' => $exam_id), 1)->row_array();

    	if (empty($exam)) return false;


    	$exam['qtype_score_arr'] = explode(',', $exam['qtype_score']);

        if ($exam['subject_id'] == 3)
    	{
    	    $groups = array(
    	            1 => array('group_name'=>C('qtype/1'), 'group_score' => 0, 'list' => array()),
    	            4 => array('group_name'=>C('qtype/4'), 'group_score' => 0, 'list' => array()),
    	            0 => array('group_name'=>C('qtype/0'), 'group_score' => 0, 'list' => array()),
    	            5 => array('group_name'=>C('qtype/5'), 'group_score' => 0, 'list' => array()),
    	            6 => array('group_name'=>C('qtype/6'), 'group_score' => 0, 'list' => array()),
    	            7 => array('group_name'=>C('qtype/7'), 'group_score' => 0, 'list' => array()),
    	            2 => array('group_name'=>C('qtype/2'), 'group_score' => 0, 'list' => array()),
    	            3 => array('group_name'=>C('qtype/3'), 'group_score' => 0, 'list' => array()),
                    8 => array('group_name'=>C('qtype/8'), 'group_score' => 0, 'list' => array()),
                    9 => array('group_name'=>C('qtype/9'), 'group_score' => 0, 'list' => array()),
    	    );
    	}
    	else
    	{
    	    $groups = array(
    	            1 => array('group_name'=>C('qtype/1'), 'group_score' => 0, 'list' => array()),
    	            2 => array('group_name'=>C('qtype/2'), 'group_score' => 0, 'list' => array()),
    	            3 => array('group_name'=>C('qtype/3'), 'group_score' => 0, 'list' => array()),
    	            0 => array('group_name'=>C('qtype/0'), 'group_score' => 0, 'list' => array())
    	    );
    	}

    	// 题组试题总的分值系数
    	$total_score_factor = 0;

    	$sql = "SELECT q.ques_id,q.type,q.title,q.score_factor,rc.difficulty
		    	FROM {pre}exam_question eq
		    	LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
		    	LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id='$exam[grade_id]' AND rc.class_id='$exam[class_id]'
		    	WHERE eq.paper_id=$paper_id ORDER BY rc.difficulty DESC,q.ques_id ASC";

    	$result = $this->db->query($sql)->result_array();
    	foreach ($result as $row)
    	{
    		unset($row['title']);

    		if ($row['type'] > 0)
    		{
    			$row['score'] = $exam['qtype_score_arr'][$row['type']-1];
    			$groups[$row['type']]['group_score'] += $row['score'];
    		}
    		else
    		{
    			$total_score_factor += $row['score_factor'];
    		}
    		$groups[$row['type']]['list'][$row['ques_id']] = $row;
    	}

    	// 计算题组分值，以及试题序号
    	$index = 0;
    	$groups[0]['group_score'] = $exam['total_score'] - $groups[1]['group_score'] - $groups[2]['group_score'] - $groups[3]['group_score'];

    	if ($exam['subject_id'] == 3)
    	{
    	    $groups[0]['group_score'] = $groups[0]['group_score'] - $groups[4]['group_score'] - $groups[5]['group_score'] - $groups[6]['group_score'] - $groups[7]['group_score'] - $groups[8]['group_score'] - $groups[9]['group_score'];
    	}

    	$left_score = $groups[0]['group_score'];
    	foreach ($groups as $qtype => &$group)
    	{
    		if (empty($group['list'])) unset($groups[$qtype]);

    		$count_list = count($group['list']);
    		foreach ($group['list'] as $key => &$ques)
    		{
    			$ques['index'] = ++$index;
    			if ($total_score_factor && $qtype == 0)
    			{
    				if ($key == $count_list-1) {
    					$ques['score'] = $left_score;
    				} else {
    					$ques['score'] = round($groups[0]['group_score'] * $ques['score_factor']/$total_score_factor);
    					$left_score -= $ques['score'];
    				}
    			}
    		}
    	}

    	$results[$paper_id] = $groups;

    	return $groups;
    }






    /**
     * 获取一个试卷试题分组列表 （机考左侧试题导航）
     * @param   int     试卷ID(paper_id)
     * @return  array
     */
    public function get_paper_question_detail_p($paper_id, $userdata = array())
    {
        $domain=C('memcache_pre');
        $this->load->driver('cache');
        $cache_time = 3 * 24 * 60 * 60;
        static $results = array();
        if (isset($results[$paper_id])) return $results[$paper_id];



        $exam_id = $this->cache->file->get('get_paper_question_detail_p_exam_id_'.$paper_id);

        if( !$exam_id)
        {
            $exam_id = $this->get_paper_by_id($paper_id, 'exam_id');
            $this->cache->file->save('get_paper_question_detail_p_exam_id_'.$paper_id, $exam_id, $cache_time);
        }



        $exam = $this->cache->file->get('get_paper_question_detail_p_exam_'.$exam_id);

        if( !$exam)
        {
              $exam = $this->db
             ->select('exam_id, subject_id, grade_id, class_id, total_score, qtype_score')
           ->get_where('exam', array('exam_id' => $exam_id), 1)->row_array();
            $this->cache->file->save('get_paper_question_detail_p_exam_'.$exam_id, $exam, $cache_time);
        }


        if (empty($exam)) return false;

        $exam['qtype_score_arr'] = explode(',', $exam['qtype_score']);

        if ($exam['subject_id'] == 3)
        {
            $groups = array(
                    1 => array('group_name'=>C('qtype/1'), 'group_score' => 0, 'list' => array()),
                    4 => array('group_name'=>C('qtype/4'), 'group_score' => 0, 'list' => array()),
                    0 => array('group_name'=>C('qtype/0'), 'group_score' => 0, 'list' => array()),
                    5 => array('group_name'=>C('qtype/5'), 'group_score' => 0, 'list' => array()),
                    6 => array('group_name'=>C('qtype/6'), 'group_score' => 0, 'list' => array()),
                    7 => array('group_name'=>C('qtype/7'), 'group_score' => 0, 'list' => array()),
                    2 => array('group_name'=>C('qtype/2'), 'group_score' => 0, 'list' => array()),
                    3 => array('group_name'=>C('qtype/3'), 'group_score' => 0, 'list' => array()),
                    8 => array('group_name'=>C('qtype/8'), 'group_score' => 0, 'list' => array()),
                    9 => array('group_name'=>C('qtype/9'), 'group_score' => 0, 'list' => array()),
            );
        }
        else
        {
            $groups = array(
                    1 => array('group_name'=>C('qtype/1'), 'group_score' => 0, 'list' => array()),
                    2 => array('group_name'=>C('qtype/2'), 'group_score' => 0, 'list' => array()),
                    3 => array('group_name'=>C('qtype/3'), 'group_score' => 0, 'list' => array()),
                    0 => array('group_name'=>C('qtype/0'), 'group_score' => 0, 'list' => array())
            );
        }

        // 题组试题总的分值系数
        $total_score_factor = 0;

        //文件缓存
        $userdata = $userdata ? $userdata : $this->session->userdata;


        $result = $this->cache->file->get('get_paper_question_detail_p_'.$exam[grade_id].'_'.$exam[class_id].'_'.$paper_id);

        if(!$result)
        {
            $sql = "SELECT q.ques_id,q.type,q.title,q.score_factor,rc.difficulty
            FROM {pre}exam_question eq
            LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
            LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id='$exam[grade_id]' AND rc.class_id='$exam[class_id]'
            WHERE eq.paper_id=$paper_id  ORDER BY rc.difficulty DESC,q.ques_id ASC";

            $result = $this->db->query($sql)->result_array();
           $this->cache->file->save('get_paper_question_detail_p_'.$exam[grade_id].'_'.$exam[class_id].'_'.$paper_id, $result, $cache_time);
        }


    //end

         //查询用户所有答案
        $new_result = array();
        $sql = "SELECT count(etr.etr_id) as 'count',etr.ques_id from {pre}exam_test_result etr
        where  etr.paper_id={$paper_id} and etr.uid={$userdata['uid']} and exam_id={$exam_id} group by etr.ques_id " ;
        $result1 = $this->db->query($sql)->result_array();
        foreach ( $result1 as $row1)
        {
            $new_result[$row1['ques_id']]=$row1['count'];
        }

        //查询用户所有答案未做的
        $new_result_undo = array();
        $sql = "SELECT count(etr.etr_id) as 'count',etr.ques_id from {pre}exam_test_result etr
        where  etr.paper_id={$paper_id} and etr.uid={$userdata['uid']} and exam_id={$exam_id} and answer ='' group by etr.ques_id ";
        $result2 = $this->db->query($sql)->result_array();
        foreach ( $result2 as $row2)
        {
            $new_result_undo[$row2['ques_id']]=$row2['count'];
        }


        foreach ($result as $row)
        {
            unset($row['title']);
            if( $new_result[$row['ques_id']]>1 )
            {
                  if( $new_result_undo[$row['ques_id']] > 0
                   &&$new_result_undo[$row['ques_id']] < $new_result[$row['ques_id']] )
                        $row['do_or_undo']='nodone';
                  elseif( $new_result_undo[$row['ques_id']] == $new_result[$row['ques_id']] )
                      $row['do_or_undo']='undone';
                  else
                    $row['do_or_undo']='done';
            }

            elseif( $new_result[$row['ques_id']] ==1)
            {
                if( $new_result_undo[$row['ques_id']] > 0 )
                    $row['do_or_undo']='q_i_undo';
                else
                    $row['do_or_undo']='done';
            }
            else
            {
                $row['do_or_undo']='q_i_undo';
            }

            if ($row['type'] > 0)
            {
                $row['score'] = isset($exam['qtype_score_arr'][$row['type']-1]) ?
                                        $exam['qtype_score_arr'][$row['type']-1] : 0;
                $groups[$row['type']]['group_score'] += $row['score'];
           }
           else
            {
                $total_score_factor += $row['score_factor'];
            }
            $groups[$row['type']]['list'][$row['ques_id']] = $row;
    }

    // 计算题组分值，以及试题序号
            $index = 0;
    	    $groups[0]['group_score'] = $exam['total_score'] - $groups[1]['group_score'] - $groups[2]['group_score'] - $groups[3]['group_score'] ;

    	    if ($exam['subject_id'] == 3)
        	{
        	    $groups[0]['group_score'] = $groups[0]['group_score'] - $groups[4]['group_score'] - $groups[5]['group_score'] - $groups[6]['group_score'] - $groups[7]['group_score'] - $groups[8]['group_score'] - $groups[9]['group_score'];
            }

           $left_score = $groups[0]['group_score'];

            foreach ($groups as $qtype => &$group)
            {
                if (empty($group['list'])) unset($groups[$qtype]);

                $count_list = count($group['list']);
                foreach ($group['list'] as $key => &$ques)
                {
                    $ques['index'] = ++$index;
                    if ($total_score_factor && $qtype == 0)
                    {
                        if ($key == $count_list-1)
                        {
                            $ques['score'] = $left_score;
                        }
                        else
                         {
                                $ques['score'] = round($groups[0]['group_score'] * $ques['score_factor']/$total_score_factor);
                                $left_score -= $ques['score'];
                        }
                     }
                }
            }

            $results[$paper_id] = $groups;

        	return $groups;
        }







    /**
     * 获取一个试题在试卷中的序号、分值等信息
     *
     * @param   int     试卷ID(paper_id)
     * @param 	int		试题ID
     * @param	int		试题类型
     * @return  array
     */
    public function get_paper_question_info($paper_id, $ques_id, $qtype = null)
    {
    	if ( ! isset($qtype)) {
    		$this->load->model('exam/question_model');
    		$qtype = $this->question_model->get_question_by_id($ques_id, 'qtype');
    	}
    	$list = $this->get_paper_question_detail($paper_id);
    	if (isset($list[$qtype]['list'][$ques_id])) {
    		return $list[$qtype]['list'][$ques_id];
    	} else {
    		return array();
    	}
    }




}

/* End of file exam_model.php */
/* Location: ./application/models/exam_model.php */