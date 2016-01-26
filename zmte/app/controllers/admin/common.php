<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Common extends A_Controller {

	/**
	 * 公共页面接口
	 *
	 */
	public function index()
	{
		exit('Directory access is forbidden.');
	}

    // ajax加载学校列表
    public function schools() 
    {
        $schools = array();
        $this->db->select('school_id,school_name');
        if ($keyword = trim($this->input->post('keyword')))
        {            
            $this->db->like('school_name', $keyword);
        }
        if ($grade_id = intval($this->input->post('grade_id')))
        {
            $grade_period = get_grade_period($grade_id);
            if ($grade_period) 
            {
                $this->db->like('grade_period', $grade_period);
            }
        }
        
        if ($province = intval($this->input->post('province')))
        {
            //$grade_period = get_grade_period($grade_id);
            if ($province)
            {
                $this->db->where('province', $province);
            }
        }
        
        if ($city = intval($this->input->post('city')))
        {
           // $grade_period = get_grade_period($grade_id);
            if ($city)
            {
                $this->db->where('city', $city);
            }
        }
        
        if ($area = intval($this->input->post('area')))
        {
            //$grade_period = get_grade_period($grade_id);
            if ($area)
            {
                $this->db->where('area', $area);
            }
        }
        
        //end
        
        $query = $this->db->get('school');
        $schools = $query->result_array();
        echo json_encode($schools);
    }

    public function knowledge()
    {
        $pid = trim($this->input->post('pid'));
        $list = array();
        if (strlen($pid))
        {
            $subject_id = intval($this->input->post('subject_id'));
            $pid = intval($pid);

            $list = KnowledgeModel::get_knowledge_list($subject_id, $pid, 1);
            usort($list, 'cmp_knowledge');
        }
        echo json_encode($list);
    }

    public function knowledge_all_bak()
    {
        $subject_id = intval($this->input->post('subject_id'));

        $list = KnowledgeModel::get_knowledge_list($subject_id, 0, 0);        
        foreach ($list as $key => $val)
        {
            $children = KnowledgeModel::get_knowledge_list($subject_id, $val['id'], 1);
            if ($children)
            {
                sort($children);
                $list[$key]['children'] = $children;
            }
            else
            {
                unset($list[$key]);
            }
        }
        sort($list);
        //pr($list);
        echo json_encode($list);
    }

    public function knowledge_all()
    {
        $subject_id = intval($this->input->post('subject_id'));
        if (Validate::isInt($subject_id))
        {
            $relate_subject_id = SubjectModel::get_subject($subject_id, 'relate_subject_id');
            if ($relate_subject_id)
            {
                $subject_id .= ",{$relate_subject_id}";
            }
        }

        $list = KnowledgeModel::get_knowledge_list($subject_id, 0, 1);        
        foreach ($list as $key => $val)
        {
            $children = KnowledgeModel::get_knowledge_list($subject_id, $val['id'], 1);
            if ($children)
            {
                usort($children, 'cmp_knowledge');
                $list[$key]['children'] = $children;
            }
            else
            {
                unset($list[$key]);
            }
        }
        usort($list, 'cmp_knowledge');
        echo json_encode($list);
    }

    public function knowledge_select()
    {
        $subject_id = $this->input->post('subject_id');
        if (Validate::isInt($subject_id))
        {
            $relate_subject_id = SubjectModel::get_subject($subject_id, 'relate_subject_id');
            if ($relate_subject_id)
            {
                $subject_id .= ",{$relate_subject_id}";
            }
        }
        
        $knowledge_ids = ','.trim($this->input->post('knowledge_ids')).',';
        $is_question_mode = intval($this->input->post('is_question_mode'));
        $list = array();
        
        $list = KnowledgeModel::get_knowledge_list($subject_id, 0, 1);
        foreach ($list as $key => $val)
        {
            //pr($val,1);
            $children = KnowledgeModel::get_knowledge_list($val['subject_id'], $val['id'], 1);
            if ($children)
            {
                usort($children, 'cmp_knowledge');
                $list[$key]['children'] = $children;
            }
            else
            {
                unset($list[$key]);
            }
        }
        
        //根据知识点ID获取对应的认知过程
        $know_processes = array();
        if ($is_question_mode) {
        	$know_process_ids = trim($this->input->post('know_process'));
	        $data['know_process'] = C('know_process');
	        $data['know_process_ids'] = my_intval((Array) json_decode($know_process_ids));
        }
        
        usort($list, 'cmp_knowledge');
        $data['is_know_process'] = !in_array($subject_id, array(13, 14, 15, 16));
        $data['list'] = $list;
        $data['knowledge_ids'] = $knowledge_ids;
        
        if ($is_question_mode && !in_array($subject_id, array(13, 14, 15, 16))) {
	        $this->load->view('question/knowledge_select_with_know_process', $data);
        } else {
	        $this->load->view('question/knowledge_select', $data);
        }
    }

    public function knowledge_init()
    {
        $knowledge_ids = trim($this->input->post('knowledge_ids'));
        $subject_id = $this->input->post('subject_id');
        if (Validate::isInt($subject_id))
        {
            $relate_subject_id = SubjectModel::get_subject($subject_id, 'relate_subject_id');
            if ($relate_subject_id)
            {
                $subject_id .= ",{$relate_subject_id}";
            }
        }
        
        if ($subject_id && $knowledge_ids) {
            $knowledge_ids = explode(',', $knowledge_ids);

            $list = KnowledgeModel::get_knowledge_list($subject_id, 0, 1);        
            foreach ($list as $key => $val)
            {
                if (empty($knowledge_ids)) {
                    unset($list[$key]);
                    continue;
                }
                
                $new_children = array();
                $children = KnowledgeModel::get_knowledge_list($val['subject_id'], $val['id'], 1);
                foreach ($knowledge_ids as $k => $v)
                {
                    if (isset($children[$v])) 
                    {
                        $new_children[] = $children[$v];
                        unset($knowledge_ids[$k]);
                    }
                }
                
                if ($new_children)
                {
                    usort($new_children, 'cmp_knowledge');
                    $list[$key]['children'] = $new_children;
                }
                else
                {
                    unset($list[$key]);
                }
            }
            usort($list, 'cmp_knowledge');
        } else {
            $list = array();
        }

        echo json_encode($list);
    }
    
    public function group_type_all()
    {
        $subject_id = intval($this->input->post('subject_id'));
    
        // 使用Yaf样式GroupTypeModel代替
        $list = GroupTypeModel::get_group_type_list($subject_id, 0, 1);
        foreach ($list as $key => $val)
        {
            $children = GroupTypeModel::get_group_type_list(
                $val['id'], $subject_id);
            if ($children)
            {
                $list[$key]['children'] = $children;
            }
            else
            {
                unset($list[$key]);
            }
        }
        echo json_encode(array_values($list));
    }
    
    public function group_type_select()
    {
        $subject_id = intval($this->input->post('subject_id'));
        $group_type_ids = ','.trim($this->input->post('group_type_ids')).',';
    
        // 使用Yaf样式GroupTypeModel代替
        $list = GroupTypeModel::get_group_type_list(0, $subject_id);
        foreach ($list as $key => $val)
        {
            $children = GroupTypeModel::get_group_type_list(
                        $val['id'], $subject_id);
            if ($children)
            {
                $list[$key]['children'] = $children;
            }
            else
            {
                unset($list[$key]);
            }
        }
    
        $data['list'] = array_values($list);
        $data['group_type_ids'] = $group_type_ids;
    
        $this->load->view('question/group_type_select', $data);
    }
    
    
    public function group_type_init()
    {
        $group_type_ids = trim($this->input->post('group_type_ids'));
        $subject_id = intval($this->input->post('subject_id'));
        if ($subject_id && $group_type_ids) {
            $group_type_ids = explode(',', $group_type_ids);
    
            // 使用Yaf样式GroupTypeModel代替
            $list = GroupTypeModel::get_group_type_list(0, $subject_id);
            foreach ($list as $key => $val)
            {
                if (empty($group_type_ids)) {
                    unset($list[$key]);
                    continue;
                }
                $new_children = array();
                $children = GroupTypeModel::get_group_type_list(
                            $val['id'], $subject_id);
                foreach ($group_type_ids as $k => $v)
                {
                    if (isset($children[$v]))
                    {
                        $new_children[] = $children[$v];
                        unset($group_type_ids[$k]);
                    }
                }
                if ($new_children)
                {
                    $list[$key]['children'] = $new_children;
                }
                else
                {
                    unset($list[$key]);
                }
            }
        } else {
            $list = array();
        }
    
        echo json_encode(array_values($list));
    }
    
	/**
	 * 选择 方法策略
	 */
    public function method_tactic_select()
    {
    	$subject_id = explode(',', $this->input->post('subject_id'));
    	$method_tactic_ids = ','.trim($this->input->post('method_tactic_ids')).',';
    
    	//获取学科分类
    	$data = array();
    	$query = array('subject_id' => $subject_id);
    	$subject_categories = SubjectCategoryModel::get_subject_category_subject_list($query, false, false, null, 'subject_category_id');
    	foreach ($subject_categories as $val)
    	{
    		$subject_category_id = $val['subject_category_id'];
    		$subject_category_name = SubjectCategoryModel::get_subject_category($subject_category_id, 'name');
    		$method_tactic_list = SubjectCategoryModel::get_method_tactic_list(array('subject_category_id' => $subject_category_id), false, false, null, 'id,name');
    		if (count($method_tactic_list))
    		{
    			$data[$subject_category_id]['name'] = $subject_category_name;
    			$data[$subject_category_id]['method_tactics'] = $method_tactic_list;
    		}
    	}
    	
    	$data['list'] = $data;
    	$data['method_tactic_ids'] = $method_tactic_ids;
    	$this->load->view('question/method_tactic_select', $data);
    }
    
    /**
     * 初始化 方法策略
     */
    public function method_tactic_init()
    {
    	$method_tactic_ids = trim($this->input->post('method_tactic_ids'));
    	$subject_id = intval($this->input->post('subject_id'));
    	$data = array();
    	if ($subject_id && $method_tactic_ids) {
    		//获取学科分类
	    	$data = array();
	    	$query = array('subject_id' => $subject_id);
	    	$list = $this->db->query("select sc.name as subject_category_name, mt.name as name, mt.id, mt.subject_category_id from {pre}method_tactic mt, {pre}subject_category sc where mt.subject_category_id=sc.id and mt.id in($method_tactic_ids)")->result_array();
	    	foreach ($list as $val)
	    	{
	    		!isset($data[$val['subject_category_id']]['name']) && $data[$val['subject_category_id']]['name'] = $val['subject_category_name'];
	    		$data[$val['subject_category_id']]['method_tactics'][] = array('id' => $val['id'], 'name' => $val['name']);
	    	}
    	}
    	
    	echo json_encode($data);
    }

    public function question_class()
    {
        $start_grade = intval($this->input->post('start_grade'));
        $end_grade   = intval($this->input->post('end_grade'));
        $grade_id    = intval($this->input->post('grade_id'));
        $list = array();
        if ($start_grade && $end_grade)
        {
            $list = ClassModel::get_grade_area_class($start_grade, $end_grade);
        }
        elseif ($grade_id)
        {
            $list = ClassModel::get_class_list($grade_id); 
            sort($list);
        }
        echo json_encode($list);
    }

    public function interview_type()
    {
        $pid = intval($this->input->post('pid'));
        $list = InterviewTypeModel::get_children($pid);
        sort($list);
        echo json_encode($list);
    }

    public function skill()
    {
        $subject_id = intval($this->input->post('subject_id'));
        // 使用Yaf样式SkillModel代替
        $list = SkillModel::get_skills($subject_id);
        sort($list);
        echo json_encode($list);
    }

    // 验证码
    public function seccode()
    {
        // todo
    }

    public function latex()
    {
        $this->load->view('common/latex');
    }
    
    // 面试题使用情况历史记录
    public function interview_history()
    {
        $ques_id = intval($this->input->post('ques_id'));
        $list = array();
        if ($ques_id) 
        {
            $sql = "SELECT r.rule_time,r.rule_name FROM {pre}interview_rule_question rq 
                    LEFT JOIN {pre}interview_rule r ON rq.rule_id=r.rule_id
                    WHERE rq.ques_id=$ques_id";
            $res = $this->db->query($sql);
            foreach($res->result_array() as $row)
            {
                $item['time'] = date('Y-m-d', $row['rule_time']);
                $item['name'] = $row['rule_name'];
                $list[] = $item;
            }
        }
        echo json_encode($list);
    }

    // 统计试题数量:组题规则ajax
    public function question_count()
    {
        $result = array(
            'error' => '',
            'count' => array(),
            'group_count' => array(),
        );
        
        $type = intval($this->input->post('type'));
        $subject_id = intval($this->input->post('subject_id'));
        $grade_id = intval($this->input->post('grade_id'));
        $class_id = intval($this->input->post('class_id'));
        $subject_type = intval($this->input->post('subject_type'));
        $is_original = intval($this->input->post('is_original'));
        if (empty($subject_id) || empty($grade_id) || empty($class_id))
        {
            $result['error'] = '请选择学科、年级、类型';
            die(json_encode($result));
        }

        if ($grade_id < 11 || $subject_id > 3 || !in_array($class_id, array(2,3)))
        {
            $subject_type = '-1';
        }
        
        // 范围知识点
        $knowledge_ids = trim($this->input->post('knowledge_ids'));
        $knowledge_ids = explode(',', $knowledge_ids);
        $knowledge_id_arr = array();
        foreach ($knowledge_ids as $id)
        {
            $id = intval($id);
            $id && array_push($knowledge_id_arr, $id);
        }
        if ($type > 0 && empty($knowledge_id_arr))
        {
            $result['error'] = '请选择知识点范围';
            die(json_encode($result));
        }

        // 重点知识点
        $rule_knowledge = intval($this->input->post('rule_knowledge'));
        $children_ids = array();
        if ($type == 2)
        {
            if ($rule_knowledge)
            {
                $pid = KnowledgeModel::get_knowledge($rule_knowledge, 'pid');
                if ($pid === false) 
                {
                    $rule_knowledge = 0;
                }
                else
                {
                    if ($pid == 0)
                    {
                        // 一级知识点
                        $children = KnowledgeModel::get_knowledge_list(0, $rule_knowledge, 0);
                        foreach ($knowledge_id_arr as $kid)
                        {
                            if (isset($children[$kid]))
                            {
                                $children_ids[] = $kid;
                            }
                        }
                        
                    }
                    else
                    {
                        // 二级知识点
                        if ( ! in_array($rule_knowledge, $knowledge_id_arr)) $rule_knowledge = 0;
                    }
                }
            }
            if (empty($rule_knowledge))
            {
                $result['error'] = '请选择重点知识点';
                die(json_encode($result));
            }
        }
        
        $where = array();
        $where[] = "q.is_delete<>1 AND q.parent_id=0";
        $where[] = "q.subject_id=$subject_id";
        $where[] = "q.start_grade<=$grade_id AND q.end_grade>=$grade_id";
		$where[] = "q.ques_id IN (SELECT distinct ques_id FROM {pre}relate_class 
		           WHERE grade_id=$grade_id AND class_id=$class_id AND subject_type=$subject_type)";
		if ($is_original > 0)
		{
		    $where[] = "q.is_original=$is_original";
		}

		//认知过程
        $know_process = intval($this->input->post('know_process'));
         //die(json_encode( $know_process));
		$know_process_sql = !$know_process ? '' : "and know_process={$know_process}";
        if ($type)
        {
            // 范围知识点 $knowledge_id_arr
//             $except_ids = array();
//             $query = $this->db->select('id')->get_where('knowledge', array('subject_id'=>$subject_id,'pid >'=>'0'));
//             foreach ($query->result_array() as $row)
//             {
//                 if ( !in_array($row['id'], $knowledge_id_arr))
//                     $except_ids[] = $row['id'];
//             }
            
            if ($type == 2)
            {
                if ($children_ids)
                {
                    $where[] = "q.ques_id IN (SELECT ques_id FROM {pre}relate_knowledge 
                            where knowledge_id IN(".my_implode($children_ids).") {$know_process_sql} and is_child=0)";
                }
                else
                {
                    $where[] = "q.ques_id IN (SELECT ques_id FROM {pre}relate_knowledge 
                    where knowledge_id=$rule_knowledge {$know_process_sql} and is_child=0)";
                }
            }
            else
            {
                $where[] = "q.ques_id IN (SELECT ques_id FROM {pre}relate_knowledge 
                        where knowledge_id IN(".implode(',', $knowledge_id_arr).") {$know_process_sql} and is_child=0)";
            }
          //  if ($except_ids)
           // {
             //   $where[] = "q.ques_id NOT IN (SELECT ques_id FROM {pre}relate_knowledge 
                      //  where knowledge_id IN(".implode(',', $except_ids).") {$know_process_sql} and is_child=0)";
          //  }
        }

        if ($type < 2)
        {
            // 总数量
            $sql = "SELECT COUNT(ques_id) nums FROM {pre}question q WHERE ".implode(' AND ', $where);
            $query = $this->db->query($sql);
            $row = $query->row_array();
            $result['count'][0] = $row['nums'];
            
            //--------------------------------------------//
            // 统计关联试题数
            // = 无分组试题数 + 分组数量
            //--------------------------------------------//
            $result['group_count'][0] = 0;
            // 独立试题数（无分组）
            $sql2 = "SELECT COUNT(q.ques_id) nums FROM {pre}question q 
                    WHERE ".implode(' AND ', $where)."  AND q.group_id=0";
            $query = $this->db->query($sql2);
            foreach ($query->result_array() as $row)
            {
                $result['group_count'][0] = $row['nums'];
            }
            // 不同分组数
            $sql2 = "SELECT COUNT(distinct q.group_id) nums FROM {pre}question q 
                    WHERE ".implode(' AND ', $where)."  AND q.group_id>0";
            $query = $this->db->query($sql2);
            foreach ($query->result_array() as $row)
            {
                $result['group_count'][0] += $row['nums'];
            }
            die(json_encode($result));
        }
        
        // 重点知识点统计：按题型、难易度区间分组
        $areas = C('difficulty_area');
        if ($subject_id == 3)
        {
            $result['count'] = array(0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0);
            $result['group_count'] = array(0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0);
        }
        else
        {
            $result['count'] = array(0,0,0, 0,0,0, 0,0,0, 0,0,0);
            $result['group_count'] = array(0,0,0, 0,0,0, 0,0,0, 0,0,0);
        }

        foreach ($areas as $k => $area)
        {
            $new_where = $where;
            $new_where[] = "qd.difficulty between $area[0] AND $area[1]";
            $sql = "SELECT q.type, COUNT(q.ques_id) nums FROM {pre}question q 
                     LEFT JOIN {pre}relate_class qd ON q.ques_id=qd.ques_id AND qd.grade_id=$grade_id AND qd.class_id=$class_id
                     WHERE ".implode(' AND ', $new_where)." GROUP BY q.type";

            //echo $sql ;die;

            $query = $this->db->query($sql);
            foreach ($query->result_array() as $row)
            {
                if ($row['type'] > 9)
                {
                	continue;
                }
                
                $result['count'][$row['type']*3 + $k] = $row['nums'];
            }
            // 独立试题数
            $sql2 = "SELECT q.type,COUNT(q.ques_id) nums FROM {pre}question q 
                     LEFT JOIN {pre}relate_class qd ON q.ques_id=qd.ques_id AND qd.grade_id=$grade_id AND qd.class_id=$class_id
                    WHERE ".implode(' AND ', $new_where)." AND q.group_id=0 GROUP BY q.type";
            $query = $this->db->query($sql2);
            foreach ($query->result_array() as $row)
            {
                if ($row['type'] > 9)
                {
                    continue;
                }
                
                $result['group_count'][$row['type']*3 + $k] = $row['nums'];
            }
            // 分组试题数
            $sql2 = "SELECT q.type, COUNT(distinct q.group_id) nums FROM {pre}question q 
                     LEFT JOIN {pre}relate_class qd ON q.ques_id=qd.ques_id AND qd.grade_id=$grade_id AND qd.class_id=$class_id
                    WHERE ".implode(' AND ', $new_where)." AND q.group_id>0 GROUP BY q.type";
            $query = $this->db->query($sql2);
            foreach ($query->result_array() as $row)
            {
                if ($row['type'] > 9)
                {
                    continue;
                }
                
                $result['group_count'][$row['type']*3 + $k] += $row['nums'];
            }
        }
        
     // echo $this->db->last_query();die;
        die(json_encode($result));
    }
    
    /**
     * 统计试题数量:组题限制ajax
     */ 
    public function qtype_count_init()
    {
        $type = intval($this->input->post('type'));
        $subject_id = intval($this->input->post('subject_id'));
        $grade_id = intval($this->input->post('grade_id'));
        $class_id = intval($this->input->post('class_id'));
        $subject_type = intval($this->input->post('subject_type'));
        $difficulty = intval($this->input->post('difficulty_limit'));
        $word_limit_min = intval($this->input->post('word_limit_num_min'));
        $word_limit_max = intval($this->input->post('word_limit_num_max'));
        $children_num = intval($this->input->post('children_limit_num'));
    
        $where = array();
        $where[] = "q.is_delete=0 AND q.parent_id=0";
        $where[] = "q.subject_id=$subject_id";
        $where[] = "q.type=$type";
        $where[] = "rc.grade_id=$grade_id AND rc.class_id=$class_id";
        
        /*
        if ( $subject_id == 3 && in_array($class_id,array(2, 3)) )
        {
            $where[] = "rc.subject_type = " . $subject_type;
        }
        */
        if ($word_limit_min > 0)
        {
            $where[] = "q.word_num > " . ($word_limit_min - 1);
        }
        if ($word_limit_max > 0)
        {
            $where[] = "q.word_num < " . ($word_limit_max + 1);
        }
        if ($children_num > 0)
        {
            $where[] = "q.children_num = " . $children_num;
        }
        
        if ($difficulty > 0)
        {
            $difficulty_area = C('difficulty_area/'.($difficulty - 1));
            $start_diffficulty = $difficulty_area[0];
            $end_diffficulty = $difficulty_area[1];
            $where[] = "rc.difficulty > $start_diffficulty AND rc.difficulty < $end_diffficulty";
        }
        $where = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(0) as nums  FROM {pre}question q LEFT JOIN {pre}relate_class rc ON q.ques_id = rc.ques_id WHERE $where";
        
        $result = $this->db->query($sql)->row_array();
        die(json_encode($result));
    }
    
    // ajax加载学校列表
    public function school_list()
    {
        $where = array();
        if ($area_id = intval($this->input->get('area_id')))
        {
            $where['area_id'] = $area_id;
        }
        
        if ($keyword = trim($this->input->get('keyword')))
        {
            $where['keyword'] = $keyword;
        }

        if ($grade_id = intval($this->input->get('grade_id')))
        {
            $grade_period = get_grade_period($grade_id);
            if ($grade_period)
            {
                $where['grade_period'] = $grade_period;
            }
        }
        
        
        $schools = SchoolModel::search_school($where);
        echo json_encode($schools);
    }
    
    public function regions()
    {
        // 使用Yaf样式的RegionModel代替
    	$p_id = $this->input->get('p_id');
        $p_id = $p_id ? $p_id : 1;
    	$regions = RegionModel::get_regions($p_id);
    	echo json_encode($regions);
    }
}
