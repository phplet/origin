<?php if ( ! defined('BASEPATH')) exit();
class Exam_rule extends A_Controller
{
    public function __construct() {
        parent::__construct();
        if ( ! $this->check_power('exam_rule_manage')) return;
    }

    /**
     * 组题规则列表
     *
     * @param int $mode 是否回收站
     * @param int $subject 学科
     * @param int $grade 年级
     * @param int $class 类型
     * @param int $keyword 关键字
     * @return voidw
     */
    public function index($mode='') {
        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $grades     = C('grades');
        $subjects   = CpUserModel::get_allowed_subjects();

        $search = $where = $param = array();

        $mode = $mode=='trash' ? 'trash' : '';
        $where[] = "is_delete=".($mode=='trash'?1:0);
        if ($search['subject'] = intval($this->input->get('subject')))
        {
            $where[] = "subject_id='$search[subject]'";
            $param[] = "subject=$search[subject]";
        }

        if (count($subjects) == '1') {
        	$tmp_subjects = array_keys($subjects);
        	$subject_id = $tmp_subjects[0];

        	$where[] = "subject_id='$subject_id'";
        	$param[] = "subject=$subject_id";
        }

        if ($search['grade'] = intval($this->input->get('grade')))
        {
            $where[] = "grade_id='$search[grade]'";
            $param[] = "grade=$search[grade]";
        }
        if ($search['class'] = intval($this->input->get('class')))
        {
            $where[] = "class_id='$search[class]'";
            $param[] = "class=$search[class]";
        }
        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $where[] = "rule_name LIKE '%".$escape_keyword."%'";
            $param[] = "keyword=".urlencode($search['keyword']);
        }
        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';

        /*
         * 统计所有组题规则数量
         */
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_rule".$where;
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        /*
         * 分页获取组题规则，并处理组题规则数据
         */
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();
        if ($total)
        {
            $sql = "SELECT * FROM {pre}exam_rule $where
                    ORDER BY rule_id DESC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                $row['class_name'] = isset($class_list[$row['class_id']]['class_name']) ? $class_list[$row['class_id']]['class_name'] : '';
                $row['grade_name'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '';
                $row['subject'] = isset($subjects[$row['subject_id']]) ? $subjects[$row['subject_id']] : '';

                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $list[] = $row;
            }
        }
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/exam_rule/index/'.$mode) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        $data['mode']        = $mode;
        $data['search']      = $search;
        $data['grades']      = $grades;
        $data['subjects']    = $subjects;
        $data['class_list']  = $class_list;

        // 模版
        $this->load->view('exam_rule/index', $data);
    }

    //----------------规则编辑-----------------------------------------//

    /**
     * 添加组题规则
     *
     * @return void
     */
    public function add() {

        $exam_rule_update_add = array();
        $exam_rule_update_add = $this->session->userdata('exam_rule_update_add');

        // 初始化
        $rule = array(
            'rule_name'     => isset($exam_rule_update_add['rule_name'] ) ? $exam_rule_update_add['rule_name'] : '',
            'subject_id'    =>  isset($exam_rule_update_add['subject_id']) ? $exam_rule_update_add['subject_id'] : '',
            'grade_id'      =>  isset($exam_rule_update_add['grade_id']) ? $exam_rule_update_add['grade_id'] : '',
            'class_id'      =>  isset($exam_rule_update_add['class_id']) ? $exam_rule_update_add['class_id'] : '',
            'subject_type'  =>  isset($exam_rule_update_add['subject_type']) ? $exam_rule_update_add['subject_type'] : '',
            'knowledge_ids' =>  isset($exam_rule_update_add['knowledge_ids']) ? $exam_rule_update_add['knowledge_ids'] : '',
            'difficulty'    =>  isset($exam_rule_update_add['difficulty']) ? $exam_rule_update_add['difficulty'] : '',
            'ques_num'      =>  isset($exam_rule_update_add['ques_num']) ? $exam_rule_update_add['ques_num'] : '',
            'is_original'   => isset($exam_rule_update_add['is_original']) ? $exam_rule_update_add['is_original'] : '',
            'test_way'      => isset($exam_rule_update_add['test_way']) ? $exam_rule_update_add['test_way'] : '',
        );

        //装配数据

        $insert_data=array();

        $ques_children_num_4 = $exam_rule_update_add['ques_children_num_4'];
        if ($rule['subject_id'] == 3)
        {
            //完形填空
            if ($ques_children_num_4 > 0)
            {
                $insert_data[] = array(
                        'exam_rule_id' => '',
                        'subject_id' => $rule['subject_id'],
                        'qtype' => 4,
                        'children_num' => $ques_children_num_4,
                        'word_num_min' => 0,
                        'word_num_max' => 0,
                        'ques_num' => 0,
                        'difficulty_level' => 0,
                        'tags' => 0,
                        'translation_c_e' => 0,
                        'translation_e_c' => 0,
                );

            }

            // 翻译题

            $translation_c_e = $exam_rule_update_add['translation_c_e'];
            $translation_e_c = $exam_rule_update_add['translation_e_c'];
            if ($translation_c_e > 0 || $translation_e_c > 0)
            {
                $insert_data[] = array(
                        'exam_rule_id' =>'',
                        'subject_id' => $rule['subject_id'],
                        'qtype' => 7,
                        'children_num' => 0,
                        'word_num_min' => 0,
                        'word_num_max' => 0,
                        'ques_num' => 0,
                        'difficulty_level' => 0,
                        'tags' => 0,
                        'translation_c_e' => $translation_c_e,
                        'translation_e_c' => $translation_e_c,
                );

            }

            //题组

            $ques_limit_num = $exam_rule_update_add['ques_limit_num'];
            $word_limit_num_min = $exam_rule_update_add['word_limit_num_min'];
            $word_limit_num_max = $exam_rule_update_add['word_limit_num_max'];
            $difficulty_limit = $exam_rule_update_add['difficulty_limit'];
            $children_limit_num = $exam_rule_update_add['children_limit_num'];

            if ($ques_limit_num){
                foreach ($ques_limit_num as $key => $num)
                {
                    if ($num < 1) continue;
                    $item = array(
                            'exam_rule_id' => '',
                            'subject_id' => $rule['subject_id'],
                            'qtype' => 0,
                            'children_num' => (int)$children_limit_num[$key],
                            'word_num_min' => (int)$word_limit_num_min[$key],
                            'word_num_max' => (int)$word_limit_num_max[$key],
                            'ques_num' => $num,
                            'difficulty_level' => (int)$difficulty_limit[$key],
                            'tags' => 0,
                            'translation_c_e' => 0,
                            'translation_e_c' => 0,
                    );

                    if (!in_array($item,$insert_data))
                    {
                        $insert_data[] = $item;
                    }else
                    {
                        foreach ($insert_data as &$val)
                        {
                            if ($val == $item)
                            {
                                $val['ques_num'] += 1;
                            }
                        }
                    }
                }


            }

            //匹配题和选词填空题
            $ques_tags = $exam_rule_update_add['ques_tags'];
            if ($ques_tags)
            {
                foreach ($ques_tags as $key => $tags)
                {
                    $insert_data[] = array(
                            'exam_rule_id' => '',
                            'subject_id' => $rule['subject_id'],
                            'qtype' => $key,
                            'children_num' => 0,
                            'word_num_min' => 0,
                            'word_num_max' => 0,
                            'ques_num' => 0,
                            'difficulty_level' => 0,
                            'tags' => $tags,
                            'translation_c_e' => 0,
                            'translation_e_c' => 0,
                    );

                }
            }
        }
    //end

        /*
         * 读取知识点名称，以及相应试题数量
        */

        $knowledge_list = array();
        $knowledge_parents = array();
        $rule_knowledge = array();
        $knowledge_ids = array();
        $knowledge_ids  = explode(',', $exam_rule_update_add['knowledge_ids']);
        $sub_rules = array();
        $knowledge_qnum=array();
        $know_process=array();

        foreach ($knowledge_ids as $kid)
        {

            $knowledge = KnowledgeModel::get_konwledge_ques_num($kid);
            $knowledge_list[$kid] = $knowledge;
            if ($knowledge['pid'] && ! isset($knowledge_parents[$knowledge['pid']]))
            {
                $knowledge_parents[$knowledge['pid']] = KnowledgeModel::get_konwledge_ques_num($knowledge['pid']);
            }
        }

                /*
                 * 重点知识点
                */
                $knowledge_qnum =  $exam_rule_update_add['knowledge_qnum'];
                $know_process =  $exam_rule_update_add['know_process'];
                $rule_knowledge =  $exam_rule_update_add['rule_knowledge'];

                $sub_rules = array();
                if(is_array($rule_knowledge))
                {
                    foreach ($rule_knowledge as $k=> $val)
                    {
                        if($k>0)
                        {
                            $row['pid'] = isset($knowledge_list[$val]['pid'])
                            ? $knowledge_list[$val]['pid'] : 0;
                            $row['nums']  = $knowledge_qnum[$k];
                            $row['know_process']  = $know_process[$k];
                            $row['knowledge_id']  =     $rule_knowledge[$k]>0?$rule_knowledge[$k]:$exam_rule_update_add['parent_knowledge'][$k];

                            $sub_rules[] = $row;
                        }
                    }
                }

                usort($knowledge_list, 'cmp_knowledge');
                foreach ($knowledge_list as $val)
                {
                    $knowledge_parents[$val['pid']]['next'][] = $val;
                }
                usort($knowledge_parents, 'cmp_knowledge');
                unset($knowledge_list);


                /** 组题规则限制 */


        $rule_limits = $insert_data;

        if ($rule_limits)
        {
        	foreach ($rule_limits as $key => $item)
        	{
        	    $rule_limit[$item['qtype']][] = $item;
        	}
        }




        // 加载分类数据
        $class_list = ClassModel::get_class_list();

        $data['act']           = 'add';
        $data['rule']          = $rule;
        $data['sub_rules']     = $sub_rules;
        $data['subjects']      = CpUserModel::get_allowed_subjects();
        $data['all_subjects']  = C('subject');
        $data['grades']        = C('grades');
        $data['subject_types'] = C('subject_type');
        $data['class_list']    = $class_list;
        $data['knowledge_list']= $knowledge_parents;
        $data['know_process']  = C('know_process');
        $data['q_tags']        = C('q_tags');
        $data['rule_limit']    = $rule_limit;
        // 模版
        $this->load->view('exam_rule/edit', $data);
    }

    /**
     * @description 修改组题规则
     *
     * @param int $id 规则id
     * @return void
     */
    public function edit($id = 0) {
        if($id = intval($id))
        {
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
            $rule['is_original'] = explode( ',', $rule['is_original'] );

        }
        if (empty($rule))
        {
            message('规则不存在');
            return;
        }

        $rule['ques_num'] = explode(',', $rule['ques_num']);

        /** 组题规则限制 */
        $rule_limits = $this->db->get_where('exam_rule_qtype_limit', array('exam_rule_id'=>$id))->result_array();


        $rule_limit = array();
        if ($rule_limits)
        {
        	foreach ($rule_limits as $key => $item)
        	{
        	    $rule_limit[$item['qtype']][] = $item;
        	}
        }

        /*
         * 读取知识点名称，以及相应试题数量
         */
        $knowledge_list = array();
        $knowledge_parents = array();
        $knowledge_ids = explode(',', $rule['knowledge_ids']);
        foreach ($knowledge_ids as $kid)
        {
            $knowledge = KnowledgeModel::get_konwledge_ques_num($kid);
            $knowledge_list[$kid] = $knowledge;
            if ($knowledge['pid'] && ! isset($knowledge_parents[$knowledge['pid']]))
            {
                $knowledge_parents[$knowledge['pid']] = KnowledgeModel::get_konwledge_ques_num($knowledge['pid']);
            }
        }

        if ($rule['subject_id'] == 11)
        {
            $sql = "SELECT distinct(subject_id) FROM {pre}knowledge WHERE id IN ({$rule['knowledge_ids']})";

            foreach ($this->db->query($sql)->result_array() as $item)
            {
                $data['subject_ids'][] = $item['subject_id'];
            }
        }

        /*
         * 重点知识点
         */
        $sub_rules = array();
        $query = $this->db->get_where('exam_rule_knowledge', array('rule_id'=>$id));
        foreach ($query->result_array() as $row)
        {
            $row['pid'] = isset($knowledge_list[$row['knowledge_id']]['pid'])
                              ? $knowledge_list[$row['knowledge_id']]['pid'] : 0;
            $row['nums'] = explode(',', $row['setting']);
            $sub_rules[] = $row;
        }

        usort($knowledge_list, 'cmp_knowledge');
        foreach ($knowledge_list as $val)
        {
            $knowledge_parents[$val['pid']]['next'][] = $val;
        }
        usort($knowledge_parents, 'cmp_knowledge');
        unset($knowledge_list);
        // 加载分类数据
        $class_list = ClassModel::get_class_list();
        $grades     = C('grades');
        $subjects   = CpUserModel::get_allowed_subjects();

        $data['act']           = 'edit';
        $data['rule']          = $rule;
        $data['rule_limit']    = $rule_limit;
        $data['sub_rules']     = $sub_rules;
        $data['subjects']      = CpUserModel::get_allowed_subjects();
        $data['all_subjects']  = C('subject');
        $data['grades']        = C('grades');
        $data['subject_types'] = C('subject_type');
        $data['class_list']    = $class_list;

        $data['knowledge_list']= $knowledge_parents;
        $data['know_process']  = C('know_process');
        $data['q_tags']        = C('q_tags');

        // 模版
        $this->load->view('exam_rule/edit', $data);
    }

    /**
     * 添加、修改组题规则[入库操作]
     *
     * @param int $id 规则id
     * @param string $rule_name 规则名称
     * @param int $subject_id 学科
     * @param int $class_id 类型
     * @param int $grade_id 年级
     * @param int $subject_type 学科类型:0文理,1文科,2理科
     * @param int $difficulty 平均难易度
     * @param int $ques_num 题型试题数:题组、单选、不定项、填空。以逗号分隔
     * @param int $knowledge_id 范围知识点
     * @param int $parent_knowledge 重点知识--父级知识点id
     * @param int $rule_knowledge 重点知识--二级知识点id
     * @param int $know_process 认知过程
     * @param int $knowledge_qnum 重点知识试题数
     * @return void
     */
    public function update() {
		$act = $this->input->post('act')=='add' ? 'add' : 'edit';
        if($act=='add')
      	$this->session->set_userdata(array('exam_rule_update_add'=>$this->input->post()));
        if ($act == 'edit') {
        	if ($id = intval($this->input->post('id'))) {
            	$query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
                $old = $query->row_array();
            }
            if (empty($old)) {
                message('规则不存在');
                return;
            }
        }
        /*
         * 题目基本信息
         */
        $rule['rule_name']    = trim($this->input->post('rule_name'));
        $rule['subject_id']   = intval($this->input->post('subject_id'));
        $rule['class_id']     = intval($this->input->post('class_id'));
        $rule['test_way']     = intval($this->input->post('test_way'));
        $rule['grade_id']     = intval($this->input->post('grade_id'));
        $subject_type         = $this->input->post('subject_type');
        $rule['subject_type'] = $subject_type === false ? '-1' : intval($subject_type);
        $rule['difficulty']   = intval($this->input->post('difficulty'));
        $rule['is_original']  = implode(',', array_filter(array_values( $this->input->post('is_original') ) ));
        $ques_num             = $this->input->post('ques_num');//题目数量组合
        $knowledge_id_arr     = $this->input->post('knowledge_id');//知识点范围
        $parent_knowledge     = $this->input->post('parent_knowledge');//一级知识点
        $rule_knowledge       = $this->input->post('rule_knowledge');//二级知识点
        $know_process         = $this->input->post('know_process');//认知过程
        $knowledge_qnum       = $this->input->post('knowledge_qnum');//重点知识点数量
        
        //修改之前先判断各题型重点知识点题目数量和各题型总题目数量之间的大小
        $sum1 = 0; $sum2 = 0; $sum3 = 0; $sum4 = 0; $sum5 = 0; $sum6 = 0; $sum7 = 0; $sum8 = 0; $sum9 = 0; $sum10 = 0;
        foreach ($knowledge_qnum as $k => $v) {
        	foreach ($v as $k2 => $v2) {
        		if ($k2 <= 2) {
        			$sum0 += $v2;
        		}
        		elseif ($k2 <= 5) {
        			$sum1 += $v2;
        		}
        		elseif ($k2 <= 8) {
        			$sum2 += $v2;
        		}
        		elseif ($k2 <= 11) {
        			$sum3 += $v2;
        		}
        		elseif ($k2 <= 14) {
        			$sum4 += $v2;
        		}
        		elseif ($k2 <= 17) {
        			$sum5 += $v2;
        		}
        		elseif ($k2 <= 20) {
        			$sum6 += $v2;
        		}
        		elseif ($k2 <= 23) {
        			$sum7 += $v2;
        		}
        		elseif ($k2 <= 26) {
        			$sum8 += $v2;
        		}
        		elseif ($k2 <= 29) {
        			$sum9 += $v2;
        		}
        	}
        }
       $qtype = C('qtype');
       foreach ($ques_num as $k => $v) {
       	if ($k == 0) {
        	if ($v < $sum0) {
        	message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
        }
       }
        if ($k == 1) {
       		if ($v < $sum1) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 2) {
       	    if ($v < $sum2) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 3) {
       	    if ($v < $sum3) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 4) {
       	    if ($v < $sum4) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 5) {
       	    if ($v < $sum5) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 6) {
       	    if ($v < $sum6) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 7) {
       	    if ($v < $sum7) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 8) {
       	    if ($v < $sum8) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
        if ($k == 9) {
       	    if ($v < $sum9) {
       		message($qtype[$k].'的总题目数量不能小于'.$qtype[$k].'重点知识点题目数量', 'admin/exam_rule/index');
       	}
       }
       }

       
        if ($rule['subject_id'] == 3)
        {
            //完形填空限制
        	$ques_children_num_4 = (int)$this->input->post('ques_children_num_4');
        	//难易度
        	$difficulty_limit = $this->input->post('difficulty_limit');
        	$difficulty_limit && $difficulty_limit = array_values($difficulty_limit);
        	//单词数限制
        	$word_limit_num_min = $this->input->post('word_limit_num_min');
        	$word_limit_num_min && $word_limit_num_min = array_values($word_limit_num_min);
        	$word_limit_num_max = $this->input->post('word_limit_num_max');
        	$word_limit_num_max && $word_limit_num_max = array_values($word_limit_num_max);
        	//子题数
        	$children_limit_num = $this->input->post('children_limit_num');
        	$children_limit_num && $children_limit_num = array_values($children_limit_num);
        	//所需子题数
        	$ques_limit_num = $this->input->post('ques_limit_num');
        	$ques_limit_num && $ques_limit_num = array_values($ques_limit_num);

        	//匹配题和选词填空标签
        	$ques_tags = $this->input->post('ques_tags');

            // 翻译题数量限制
            $translation_c_e = (int)$this->input->post('translation_c_e');
            $translation_e_c = (int)$this->input->post('translation_e_c');
        }

        /*
         * 试题信息验证
         */
        $message = array();
        if (empty($rule['rule_name'])) {
            $message[] = '请填写规则名称';
        }
        if (empty($rule['subject_id'])) {
            $message[] = '请选择学科';
        }
        if (empty($rule['grade_id'])) {
            $message[] = '请选择年级';
        }
        if (empty($rule['class_id'])) {
            $message[] = '请选择类型';
        }
        if (empty($rule['test_way'])) {
        	$message[] = '请选择考试方式';
        }
        if (empty($rule['difficulty'])) {
            $message[] = '请填写平均难易度';
        }

        if ($rule['subject_id'] > 3 || $rule['grade_id'] < 11 || ! in_array($rule['class_id'],array(2,3))) {
            $rule['subject_type'] = '-1';

        } else {
        	if ($rule['subject_type'] == '-1') {
        		$rule['subject_type'] = 0;
        	}
        }

        if (empty($ques_num) OR ! is_array($ques_num)) {
            $message[] = '请填写试题数量';
        } else {
            $ques_num = my_intval($ques_num);
            $question_num = 0;
            foreach ($ques_num as $type => $q_num) {
                if ($q_num == 0 && isset($ques_tags[$type])) {
                    unset($ques_tags[$type]);
                }

                if ($rule['subject_id'] != 3 && $type > 3) {
                    $q_num = 0;
                    $ques_num[$type] = 0;
                }

                    $question_num += $q_num;
            }

                if ($question_num == 0) {
                    $message[] = '请填写试题数量';
            }

            $rule['ques_num'] = implode(',', $ques_num);
        }

        if (empty($knowledge_id_arr) OR ! is_array($knowledge_id_arr)) {
            $message[] = '请选择知识点范围';
        } else {
            $knowledge_id_arr = my_intval($knowledge_id_arr);
            sort($knowledge_id_arr);
            $rule['knowledge_ids'] = implode(',', $knowledge_id_arr);
        }

        if (! is_array($rule_knowledge) OR ! is_array($knowledge_qnum)) {
            $knowledge_qnum = $knowledge_qnum = array();
        } else {
            /*
             * 去掉array第一条，第一条为用于js的规则demo
             */
            array_shift($rule_knowledge);
            array_shift($knowledge_qnum);
            array_shift($parent_knowledge);
            array_shift($know_process);
        }

        if ($message) {
            message(implode('<br/>', $message), $this->input->post('act')=='add' ? '/admin/exam_rule/add' : '', null, 10);
            return;
        }

        /*
         * 重点知识点
         */
        $tmp_know_process = array();
        $knowledge_rules = array();
        foreach ($rule_knowledge as $k => $v) {
            $v = intval($v);
            /*
             * 判断重点知识点是否在所选知识点范围内
             */
            if ($v) {
                if ( !in_array($v, $knowledge_id_arr))  continue;
            } else {
                $v = intval($parent_knowledge[$k]);
            }

            /*
             * 试题数、认知过程数据处理判断
             */
            if (empty($v) OR !isset($knowledge_qnum[$k]) OR !is_array($knowledge_qnum[$k])
	            OR !isset($know_process[$k]) OR !intval($know_process[$k])
	            OR (isset($tmp_know_process[$v]) && in_array(my_intval($knowledge_qnum[$k]), $tmp_know_process[$v])))
            {
                continue;
            }

            $tmp_know_process[$v][] = intval($know_process[$k]);
            $knowledge_qnum[$k] = my_intval($knowledge_qnum[$k]);

            if (array_sum($knowledge_qnum[$k]) == 0) continue;
            $item = array(
                'knowledge_id' => $v,
                'know_process' => intval($know_process[$k]),
                'setting' => implode(',', $knowledge_qnum[$k])
            );

            $knowledge_rules[$v.'_'.$item['know_process']] = $item;
        }

        try
        {
            if ($act == 'edit') {
                /*
                 * 修改之前的知识点处理，之前有的知识点直接覆盖；没有的需要新增；之前有的现在没有的需要删除。
                 */
                $old_sub_rules = array();
                $query = $this->db->select('id,knowledge_id,know_process')->get_where('exam_rule_knowledge', array('rule_id'=>$id));
                foreach ($query->result_array() as $arr) {
                    $old_sub_rules[$arr['knowledge_id'].'_'.$arr['know_process']] = $arr['id'];
                }

                $this->db->trans_start();
                $this->db->update('exam_rule', $rule, array('rule_id'=>$id));

                /*
                 * 修改之前存在的知识点
                 */
                foreach ($knowledge_rules as $k => &$val) {
                    $val['rule_id'] = $id;
                    if (isset($old_sub_rules[$k])) {
                        $this->db->update('exam_rule_knowledge', $val, array('id'=>$old_sub_rules[$k]));
                        unset($old_sub_rules[$k]);
                        unset($knowledge_rules[$k]);
                    }
                }

                /*
                 * 新增之前没有的知识点
                 */
                if ($knowledge_rules) {
                    $this->db->insert_batch('exam_rule_knowledge', $knowledge_rules);
                }

                /*
                 * 直接删除之前有现在没有的知识点
                */
                if ($old_sub_rules) {
                    $this->db->where_in('id', $old_sub_rules)->delete('exam_rule_knowledge');
                }

                /*
                 * 英语题组和完形填空限制
                 */
                $this->db->delete('exam_rule_qtype_limit','exam_rule_id = ' . $id);

                if ($rule['subject_id'] == 3) {
                    //完形填空
                    if ($ques_children_num_4 > 0) {
                        $insert_data = array(
                        	'exam_rule_id' => $id,
                            'subject_id' => $rule['subject_id'],
                            'qtype' => 4,
                            'children_num' => $ques_children_num_4,
                            'word_num_min' => 0,
                            'word_num_max' => 0,
                            'ques_num' => 0,
                            'difficulty_level' => 0,
                            'tags' => 0,
                            'translation_c_e' => 0,
                            'translation_e_c' => 0,
                        );
                    	$this->db->insert('exam_rule_qtype_limit',$insert_data);
                    }

                    // 翻译题
                    if ($translation_c_e > 0 || $translation_e_c > 0) {
                        $insert_data = array(
                            'exam_rule_id' => $id,
                            'subject_id' => $rule['subject_id'],
                            'qtype' => 7,
                            'children_num' => 0,
                            'word_num_min' => 0,
                            'word_num_max' => 0,
                            'ques_num' => 0,
                            'difficulty_level' => 0,
                            'tags' => 0,
                            'translation_c_e' => $translation_c_e,
                            'translation_e_c' => $translation_e_c,
                        );
                        $this->db->insert('exam_rule_qtype_limit',$insert_data);
                    }

                    //题组
                    $insert_data = array();
                    if ($ques_limit_num) {
                        foreach ($ques_limit_num as $key => $num) {
                            if ($num < 1) continue;
                            $item = array(
                                    'exam_rule_id' => $id,
                                    'subject_id' => $rule['subject_id'],
                                    'qtype' => 0,
                                    'children_num' => (int)$children_limit_num[$key],
                                    'word_num_min' => (int)$word_limit_num_min[$key],
                                    'word_num_max' => (int)$word_limit_num_max[$key],
                                    'ques_num' => $num,
                                    'difficulty_level' => (int)$difficulty_limit[$key],
                                    'tags' => 0,
                                    'translation_c_e' => 0,
                                    'translation_e_c' => 0,
                            );

                            if (!in_array($item,$insert_data)) {
                                $insert_data[] = $item;
                            } else {
                            	foreach ($insert_data as &$val) {
                            		if ($val == $item) {
                            		    $val['ques_num'] += 1;
                            		}
                            	}
                            }
                        }

                        $insert_data && $this->db->insert_batch('exam_rule_qtype_limit', $insert_data);
                    }

                    //匹配题和选词填空题
                    if ($ques_tags) {
                        foreach ($ques_tags as $key => $tags) {
                            $insert_data = array(
                                    'exam_rule_id' => $id,
                                    'subject_id' => $rule['subject_id'],
                                    'qtype' => $key,
                                    'children_num' => 0,
                                    'word_num_min' => 0,
                                    'word_num_max' => 0,
                                    'ques_num' => 0,
                                    'difficulty_level' => 0,
                                    'tags' => $tags,
                                    'translation_c_e' => 0,
                                    'translation_e_c' => 0,
                            );
                            $this->db->insert('exam_rule_qtype_limit',$insert_data);
                        }
                    }
                }

                $this->db->trans_complete();

                admin_log('edit', 'exam_rule', $id);
                message('组题规则编辑成功', 'admin/exam_rule/index');
            } else {
                $rule['addtime'] = time();
                $this->db->trans_start();
                $res = $this->db->insert('exam_rule', $rule);
                if (empty($res)) {
                    throw new Exception('规则添加失败');
                }
                $id = $this->db->insert_id();
                foreach ($knowledge_rules as $k => &$val) {
                    $val['rule_id'] = $id;
                }
                if ($knowledge_rules) {
                    $this->db->insert_batch('exam_rule_knowledge', $knowledge_rules);
                }

                /*
                 * 英语题组和完形填空限制
                 */
                if ($rule['subject_id'] == 3) {
                    //完形填空
                    if ($ques_children_num_4 > 0) {
                        $insert_data = array(
                        	'exam_rule_id' => $id,
                            'subject_id' => $rule['subject_id'],
                            'qtype' => 4,
                            'children_num' => $ques_children_num_4,
                            'word_num_min' => 0,
                            'word_num_max' => 0,
                            'ques_num' => 0,
                            'difficulty_level' => 0,
                            'tags' => 0,
                            'translation_c_e' => 0,
                            'translation_e_c' => 0,
                        );
                    	$this->db->insert('exam_rule_qtype_limit',$insert_data);
                    }

                    // 翻译题
                    if ($translation_c_e > 0 || $translation_e_c > 0) {
                        $insert_data = array(
                            'exam_rule_id' => $id,
                            'subject_id' => $rule['subject_id'],
                            'qtype' => 7,
                            'children_num' => 0,
                            'word_num_min' => 0,
                            'word_num_max' => 0,
                            'ques_num' => 0,
                            'difficulty_level' => 0,
                            'tags' => 0,
                            'translation_c_e' => $translation_c_e,
                            'translation_e_c' => $translation_e_c,
                        );
                        $this->db->insert('exam_rule_qtype_limit',$insert_data);
                    }

                    //题组
                    $insert_data = array();
                    if ($ques_limit_num) {
                        foreach ($ques_limit_num as $key => $num) {
                            if ($num < 1) continue;
                            $item = array(
                                    'exam_rule_id' => $id,
                                    'subject_id' => $rule['subject_id'],
                                    'qtype' => 0,
                                    'children_num' => (int)$children_limit_num[$key],
                                    'word_num_min' => (int)$word_limit_num_min[$key],
                                    'word_num_max' => (int)$word_limit_num_max[$key],
                                    'ques_num' => $num,
                                    'difficulty_level' => (int)$difficulty_limit[$key],
                                    'tags' => 0,
                                    'translation_c_e' => 0,
                                    'translation_e_c' => 0,
                            );

                            if (!in_array($item,$insert_data)) {
                                $insert_data[] = $item;
                            } else {
                            	foreach ($insert_data as &$val) 
                            		if ($val == $item)
                            		{
                            		    $val['ques_num'] += 1;
                            		}
                            	}
                            }
                        }
                        $insert_data && $this->db->insert_batch('exam_rule_qtype_limit', $insert_data);
                    }

                    //匹配题和选词填空题
                    if ($ques_tags) {
                        foreach ($ques_tags as $key => $tags) {
                            $insert_data = array(
                                    'exam_rule_id' => $id,
                                    'subject_id' => $rule['subject_id'],
                                    'qtype' => $key,
                                    'children_num' => 0,
                                    'word_num_min' => 0,
                                    'word_num_max' => 0,
                                    'ques_num' => 0,
                                    'difficulty_level' => 0,
                                    'tags' => $tags,
                                    'translation_c_e' => 0,
                                    'translation_e_c' => 0,

                            );
                            $this->db->insert('exam_rule_qtype_limit',$insert_data);
                        }
                    }
                }

                $this->db->trans_complete();

                admin_log('add', 'exam_rule', $id);
                $this->session->unset_userdata(array('exam_rule_update_add' => ''));
                message('组题规则添加成功', 'admin/exam_rule/index');
            } catch(Exception $e) {
            message($e->getMessage());
        }
    }

    private function array_unique_fb($array2D) {
        foreach ($array2D as $v) {
            $v = join(',',$v);              //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
            $temp[] = $v;
        }

        $temp = array_unique($temp);      //去掉重复的字符串,也就是重复的一维数组
        foreach ($temp as $k => $v) {
            $temp[$k] = explode(',',$v);       //再将拆开的数组重新组装
        }
        return $temp;
    }
    /**
     * 生成试题
     *
     * @param int $id 组题规则id
     * @param int $exam_id 考试期次id
     * @param string $method 试卷生成方式
     * @return void
     */
    public function generate($id = 0) {
         if ($id = intval($id)) {
        	//如果规则ID存在，则根据规则ID获得规则信息（包括该规则ID下面的学科ID）
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }

        if ($this->input->post()) {
            $result = array('success' => false, 'msg' => '', '-1');

            if (empty($rule)) {
                $result['msg'] = '规则不存在';
                die(json_encode($result));
            }

            /*
             * 检查提交数据是否有效
             */
            //接收判断表单传过来的考试期次ID和生成方式
            $exam_id = intval($this->input->post('exam_id'));
            $method = $this->input->post('method')=='new' ? 'generate' : 'generate_rand';
            if ($exam_id) {
            	//如果考试期次存在，则根据考试期次得到考试信息
                $exam = ExamModel::get_exam($exam_id);
            }
            //如果考试信息为空，或是考试信息中的父ID为0，或是考试信息中字段status为1
            if (empty($exam) || $exam['status'] || $exam['exam_pid']==0) {
                $result['msg'] = '请选择考试期次';
                die(json_encode($result));
            } else { 
            	//如果考试信息中的学科ID不等于规则信息中的学科ID   或则考试信息中的年级ID不等于规则信息中的年级ID
                if ($exam['subject_id']!=$rule['subject_id'] || $exam['grade_id']!=$rule['grade_id'] || $exam['class_id']!=$rule['class_id']) {
                    $result['msg'] = '组题规则和考试期次不匹配（学科、年级或类型）';
                    die(json_encode($result));
                }
            }
            //将规则信息中的知识点ID以逗号分隔成数组，该数组作为一个字段添加到规则信息数组中
            $rule['knowledge_id_arr'] = explode(',', $rule['knowledge_ids']);
            //将规则信息中每个题型的题目数量以逗号分隔成数组，该数组作为一个字段添加到规则信息数组中
            $rule['ques_num'] = explode(',', $rule['ques_num']);
            //至少保证有4种题型,不够的设置其知识点数量为0
            if (count($rule['ques_num']) < 4) {
                for($i=count($rule['ques_num']); $i<4; $i++) {
                    $rule['ques_num'][$i] = 0;
                }
            }

            /**
             * 试卷分数判断
             */
            // 实际总分(排除题组)
            $total_score = 0;
            //将该卷子中每种题型的单个分数以逗号分隔成数组，循环数组，获得每种题型的分数
            foreach (explode(',',$exam['qtype_score']) as $key => $value) {
            //将每种题型的数量乘以每种题型的单个分数，累加起来获得总分数。(排除题组，所以要从$k+1开始)
                $total_score += ($value * $rule['ques_num'][$key+1]);
            }
            
            $total_0 = $exam['total_score'] - $total_score;

            // 是否存在题组
            if ($total_0 <= 0 && $rule['ques_num'][0] > 0) {
                $result['msg'] = '题组的总分必须大于0分！';
                die(json_encode($result));
            }

            if ($total_0 < 0) {
                $result['msg'] = '总分大于设定分值！';
                die(json_encode($result));
            }

            /*
             * 读取重点知识点
             */
            $sub_rules = array();
            
            $query = $this->db->get_where('exam_rule_knowledge', array('rule_id'=>$id));
            //根据生成试卷时传过来的规则ID获得关联数组中有关知识点的信息。循环信息数组。
            foreach ($query->result_array() as $row) {
            	//将setting字段用逗号分隔成数组加到每个信息数组中
                $row['nums'] = explode(',', $row['setting']);

                $row['pid'] = KnowledgeModel::get_knowledge($row['knowledge_id'], 'pid');

                // 一级知识点规则
                if ($row['pid'] == 0) {
                    $children = KnowledgeModel::get_knowledge_list(0, $row['knowledge_id'], 0);
                    $children_ids = array_keys($children);
                    $row['children'] = array_intersect($children_ids, $rule['knowledge_id_arr']);
                }

                $sub_rules[] = $row;
            }

            /*
             * 生成试卷
             */
            $res = ExamPaperModel::$method($exam, $rule, $sub_rules);
            if ($res['success'] == true) {
                $result['success'] = TRUE;
                $result['msg'] = '试卷生成成功。';
            } else {
            	if ($res['code'] == '-2') {
            		//存在一样的试卷，无法再生成
	                $result['code'] = '-2';
            	}

                $result['msg'] = '试卷生成失败：'.$res['msg'];
            }
            die(json_encode($result));
        } else {
            if (empty($rule)) {
                message('规则不存在');
                return;
            }

            $nowtime = time();
            $where = array(
                'p.status'     => 1,
                'e.exam_pid >' => 0,
                'e.subject_id' => $rule['subject_id'],
                'e.grade_id'   => $rule['grade_id'],
                'e.class_id'   => $rule['class_id'],
            );

            $where1 = "(ep.start_time > $nowtime or ep.end_time < $nowtime)";

            //没有分配考场的考试
            $query = $this->db->select('e.exam_id,e.exam_pid,p.exam_name,e.subject_id')->from('exam e')
                    ->join('exam p','e.exam_pid = p.exam_id', "LEFT")
                    ->where($where)->order_by('exam_id DESC')->group_by('exam_id')->get();
            $data['exam_list0'] = $query->result_array();

           foreach ($data['exam_list0'] as $key => $item) {
                if ($this->db->get_where('exam_place', array('exam_pid' => $item['exam_pid']))->row_array()&&
                $this->db->get_where('exam_place_subject', array('exam_pid' => $item['exam_pid'],'subject_id'=>$item['subject_id'],'exam_id'=>$item['exam_id']))->row_array()
                ) {
                  unset($data['exam_list0'][$key]);
               }
         }

            //已分配考场还没有进行的考试或已结束的考试
            $query = $this->db->select('e.exam_id,e.exam_pid,p.exam_name,e.subject_id')->from('exam e')
                    ->join('exam p','e.exam_pid = p.exam_id', "LEFT")
                    ->join('exam_place ep', 'ep.exam_pid = e.exam_pid', "LEFT")
                    ->where($where)->where($where1)->group_by('e.exam_id')->order_by('exam_id DESC')->get();

            $data['exam_list1'] = $query->result_array();

           $data['exam_list'] =  $this->array_unique_fb(array_merge($data['exam_list0'] , $data['exam_list1']));

            arsort($data['exam_list'] );

            $data['rule'] = $rule;
            $data['subject'] = C('subject');

            // 模版
            $this->load->view('exam_rule/generate', $data);
        }
    }

    /**
     * 生成试卷
     *
     * @param int $rule_id 组题规则id
     * @param int $exam_id 考试期次id
     * @param string $method 试卷生成方式
     * @return void
     */
    public function gen($exam_id = 0, $rule_id=0, $method='new') {
        if($rule_id = intval($rule_id)) {
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$rule_id));
            $rule = $query->row_array();
        }
        if (empty($rule)) {
            message('规则不存在');
            return;
        }

        /*
         * 检查提交数据是否有效
         */
        $exam_id = intval($exam_id);
        $method = $method=='new' ? 'generate' : 'generate_rand';
        if ($exam_id) {
            $exam = ExamModel::get_exam($exam_id);
        }
        if (empty($exam) || $exam['status']) {
            die('请选择考试期次.');
        } else {
            if ($exam['subject_id']!=$rule['subject_id'] || $exam['grade_id']!=$rule['grade_id'] || $exam['class_id']!=$rule['class_id']) {
                die('组题规则和考试期次不匹配（学科、年级或类型）');
            }
        }

        $rule['knowledge_id_arr'] = explode(',', $rule['knowledge_ids']);
        $rule['ques_num'] = explode(',', $rule['ques_num']);
        if (count($rule['ques_num']) < 4) {
            for($i=count($rule['ques_num']); $i<4; $i++) {
                $rule['ques_num'][$i] = 0;
            }
        }

        /* 读取重点知识点 */
        $sub_rules = array();
        $query = $this->db->get_where('exam_rule_knowledge', array('rule_id'=>$rule_id));
        foreach ($query->result_array() as $row) {
            $row['nums'] = explode(',', $row['setting']);
            $row['pid'] = KnowledgeModel::get_knowledge($row['knowledge_id'], 'pid');
            // 一级知识点规则
            if ($row['pid'] == 0) {
                $children = KnowledgeModel::get_knowledge_list(0, $row['knowledge_id'], 0);
                $children_ids = array_keys($children);
                $row['children'] = array_intersect($children_ids, $rule['knowledge_id_arr']);
            }
            $sub_rules[$row['knowledge_id']] = $row;
        }

        $res = ExamPaperModel::$method($exam, $rule, $sub_rules);
        if ($res['success'] == true)
            die('试卷生成成功');
        else
            die($res['msg']);
    }

    /**
     * 删除组题规则放入回收站
     *
     * @param int $id 组题规则id
     * @return void
     */
    public function delete($id = 0) {
        if($id = intval($id)) {
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule)) {
            message('规则不存在');
            return;
        }

        $res = $this->db->update('exam_rule', array('is_delete'=>1), array('rule_id'=>$id));
        if ($res) {
            admin_log('delete', 'exam_rule', $id);
            message('规则删除成功', 'admin/exam_rule/index');
        } else {
            message('规则删除失败');
        }
    }

    /**
     * 还原回收站中的组题规则
     *
     * @param int $id 组题规则id
     * @return void
     */
    public function restore($id = 0) {
        if($id = intval($id)) {
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule)) {
            message('规则不存在');
            return;
        }

        $res = $this->db->update('exam_rule', array('is_delete'=>0), array('rule_id'=>$id));
        if ($res) {
            admin_log('restore', 'exam_rule', $id);
            message('规则还原成功', 'admin/exam_rule/index');
        } else {
            message('规则还原失败');
        }
    }

    /**
     * 删除回收站中的组题规则
     *
     * @param int $id 组题规则id
     * @return void
     */
    public function remove($id=0) {
        if($id = intval($id)) {
            $query = $this->db->get_where('exam_rule', array('rule_id'=>$id));
            $rule = $query->row_array();
        }
        if (empty($rule)) {
            message('规则不存在');
            return;
        }

        $this->db->trans_start();

        $res = $this->db->delete(array('exam_rule','exam_rule_knowledge'), array('rule_id'=>$id));
        if ($res) {
            $res = $this->db->delete('exam_rule_qtype_limit', "exam_rule_id = $id");
        }

        if ($res) {
            $this->db->trans_complete();
            admin_log('remove', 'exam_rule', $id);
            message('规则移除成功', 'admin/exam_rule/index');
        } else {
            $this->db->trans_rollback();
            message('规则移除失败');
        }
    }
}
