<?php if ( ! defined('BASEPATH')) exit();

class Comparison_info extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 列表展示
    public function index($type_id = 0)
    {    	    	
    	if ( ! $this->check_power('comparison_list,comparison_manage')) return;
    	
    	if ($type_id = intval($type_id)) {
    		$type_detail = ComparisonTypeModel::get_comparison_type_by_id($type_id);    		
    	}
    	if (empty($type_detail)) {
    		message('对比信息分类不存在！', site_url('admin/comparison_type/index'));
    	}
    	
        $size   = C('admin_page_size');
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $order_by = 'cmp_info_year DESC';
        $select_what = 'cmp_info_id,cmp_info_year,cmp_info_score,updatetime,addtime';
		
        // 查询条件
        $condition = $param = $search = array();
        $condition['cmp_type_id'] = $type_id;
        
        // 获取数据
        $total = ComparisonInfoModel::get_comparison_info_count($condition);
        $list   = array();
        if ($total) {
            $list = ComparisonInfoModel::get_comparison_info_list($condition, $page, $size, $order_by, $select_what);
        }
        
        $data['type_detail'] = &$type_detail;
        $data['list'] = &$list;
        $data['search']= &$search;

        // 分页
        $purl = site_url('admin/comparison_info/index/'.$type_id) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);
		
		// 学科、类型
        $data['subjects'] = CpUserModel::get_allowed_subjects();        
        $data['class_list'] = ClassModel::get_class_list();
        $data['priv_manage'] = $this->check_power('comparison_manage', FALSE);

        // 模版
        $this->load->view('comparison_info/index', $data);
    }
    
    // 添加页面
    public function add($type_id)
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	if ($type_id = intval($type_id)) {
    		$type_detail = ComparisonTypeModel::get_comparison_type_by_id($type_id);
    	}
    	if (empty($type_detail)) {
    		message('对比信息分类不存在！', site_url('admin/comparison_type/index'));
    	} else {
    		$type_detail['class_name'] = ClassModel::get_question_class_by_id($type_detail['class_id'], 'class_name');
    		$type_detail['grade_name'] = C('grades/'.$type_detail['grade_id']);
    		$type_detail['subject_name'] = C('subject/'.$type_detail['subject_id']);
    	}
    	$db = Fn::db();
    	$res = $db->fetchRow('SELECT group_concat(DISTINCT subject_id) as subject_ids FROM rd_subject_category_subject');
    	$type_detail['subject_ids'] = explode(',',$res['subject_ids']);
    	$info_detail = array(
    		'cmp_info_id'    => '',
    		'cmp_type_id'	 => $type_id,
    		'cmp_info_year'  => '',
    	    'cmp_info_score' => '',
    	);
    	
        // 一级知识点
		$knowledge_list = KnowledgeModel::get_knowledge_list($type_detail['subject_id'], 0, false);
		ksort($knowledge_list);
		
		//信息提取方式
		$group_type_list = array();
		if ($type_detail['subject_id'] == 3)
		{
                    $group_type_list = GroupTypeModel::get_group_type_list(
                                    0, $type_detail['subject_id'], false);
		    ksort($group_type_list);
		}
		
		//方法策略
		$method_tactic_list = SubjectCategoryModel::get_method_tactic_by_subject_id($type_detail['subject_id']);
		ksort($method_tactic_list);
		
    	$data['act'] = 'add';
    	$data['type_detail'] 	= &$type_detail;
    	$data['info_detail'] 	= &$info_detail;
    	$data['items'] 		 	= array();
    	$data['items2'] 		= array();
    	$data['external_items'] = array();
    	$data['item_difficulties'] = array();
    	$data['item_external_difficulties'] = array();
    	$data['item_method_tactics'] = array();
    	$data['external_method_tactics'] = array();
    	$data['knowledge_list'] = &$knowledge_list;
    	$data['group_type_list'] = &$group_type_list;
    	$data['method_tactic_list'] = &$method_tactic_list;
    	$data['q_types'] = C('q_type');//试题题型
    	$data['difficulty_types'] = array('2' => '高', '1' => '中', '0' => '低');//试题题型
    	
        // 模版
        $this->load->view('comparison_info/edit', $data);
    }

    // 编辑页面
    public function edit($id = 0)
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
        $id = intval($id);        
        //$sql = "select * from rd_comparison_info where cmp_info_id=3";
        //$res = mysql_query($sql, $this->db->conn_id);
        //pr(mysql_fetch_array($res));        
        $id && $info_detail = ComparisonInfoModel::get_comparison_info_by_id($id);
        if (empty($info_detail))
            message('信息不存在');
        $info_detail['cmp_extraction_ratio'] = json_decode($info_detail['cmp_extraction_ratio'],true);        
        
        $type_detail = ComparisonTypeModel::get_comparison_type_by_id($info_detail['cmp_type_id']);
		//$this->load->library('Fn');
		$db = Fn::db();
		$res = $db->fetchCol('SELECT DISTINCT subject_id FROM rd_subject_category_subject');
		$type_detail['subject_ids'] = $res;

		//$result = $this->db->query('SELECT group_concat(DISTINCT subject_id) as subject_ids FROM {pre}subject_category_subject')->row_array();
		//$type_detail['subject_ids'] = explode(',', $result['subject_ids']);


        if (empty($type_detail)) {
        	message('分类信息不存在');
        } else {
        	$type_detail['class_name'] = ClassModel::get_question_class_by_id($type_detail['class_id'], 'class_name');
        	$type_detail['grade_name'] = C('grades/'.$type_detail['grade_id']);
        	$type_detail['subject_name'] = C('subject/'.$type_detail['subject_id']);
        }
        
        $items = ComparisonInfoModel::get_comparison_items($id, TRUE);
        $items2 = ComparisonInfoModel::get_comparison_items2($id, TRUE);
        
        //外部知识点与对比信息关系
        $external_items = ComparisonInfoModel::get_comparison_items_external($id, TRUE);
        
        // 一级知识点
		$knowledge_list = KnowledgeModel::get_knowledge_list($type_detail['subject_id'], 0, false);
		ksort($knowledge_list);
		
		//信息提取方式
		$group_type_list = array();
		if ($type_detail['subject_id'] == 3)
		{
                    $group_type_list = GroupTypeModel::get_group_type_list(
                                    0, $type_detail['subject_id'], false);
		    ksort($group_type_list);
		}

		//对比项《难易度》
		$item_difficulties = ComparisonInfoModel::get_comparison_items_difficutly($id, TRUE);
		
		//对比项《外部题型 难易度》
		$item_external_difficulties = ComparisonInfoModel::get_comparison_items_external_difficutly($id, TRUE);
		
		//方法策略
		$method_tactic_list = SubjectCategoryModel::get_method_tactic_by_subject_id($type_detail['subject_id']);
		ksort($method_tactic_list);
		
		//对比项《方法策略》
		$item_method_tactics = ComparisonInfoModel::get_comparison_items_method_tactic($id, TRUE);
		
		//对比项《外部方法策略》
		$external_method_tactics = ComparisonInfoModel::get_comparison_items_external_method_tactic($id, TRUE);
		
        $data['act'] = 'edit';
    	$data['type_detail'] 	= &$type_detail;
    	$data['info_detail'] 	= &$info_detail;
    	$data['items'] 		 	= &$items;
    	$data['items2'] 		= &$items2;
    	$data['external_items'] = &$external_items;
    	$data['knowledge_list'] = &$knowledge_list;
    	$data['group_type_list'] = &$group_type_list;
    	$data['item_difficulties'] = &$item_difficulties;
    	$data['item_external_difficulties'] = &$item_external_difficulties;
    	$data['method_tactic_list'] = &$method_tactic_list;
    	$data['item_method_tactics'] = &$item_method_tactics;
    	$data['external_method_tactics'] = &$external_method_tactics;
    	$data['q_types'] = C('q_type');//试题题型
    	$data['difficulty_types'] = array('2' => '高', '1' => '中', '0' => '低');//试题题型

        // 模版
        $this->load->view('comparison_info/edit', $data);
    }
    
    // 保存(添加、更新)
    public function save()
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	$act = $this->input->post('act');
    	$act = $act=='add' ? 'add' : 'edit';    	
    	$id  = null;
    	
    	if ($act == 'edit') 
    	{   
    		// check old info
    		$id = (int)$this->input->post('id');
    		$id && $old_info = ComparisonInfoModel::get_comparison_info_by_id($id);
    		if (empty($old_info))
    			message('信息不存在');
    	}
    	
    	// check type info
    	$type_id = (int)$this->input->post('type_id');
    	$type_id && $type_info = ComparisonTypeModel::get_comparison_type_by_id($type_id);
    	if (empty($type_info) OR $act == 'edit' && $old_info['cmp_type_id']!=$type_info['cmp_type_id'])
    	{
    		message('信息分类不存在');
    	}
    	
    	$subject_id = intval($this->input->post('subject_id'));
    	
    	// init input data
    	$info = array(
    		'cmp_type_id'    => $type_id,
    		'cmp_info_year'  => (int)$this->input->post('cmp_info_year'),
    		'cmp_info_score' => (int)$this->input->post('cmp_info_score'),
    	);    	
    	
    	$knowledge_ids = $this->input->post('knowledge_ids'); //知识点
    	$percents = $this->input->post('percents');//知识点占比
    	$group_type_ids = $this->input->post('group_type_ids');//信息提取方式
    	$group_type_percents = $this->input->post('group_type_percents');//信息提取方式占比
    	$extraction_ratio = $this->input->post('extraction_ratio');//知识点和信息提取方式比例

    	// validate input data
    	$message = array();
    	if ($info['cmp_info_year']<1990 OR $info['cmp_info_year']>date('Y')) {
    		$message[] = '请正确填写考试年份';
    	} else {
    		$tmp_id = ComparisonInfoModel::get_comparison_info_by_year($type_id, $info['cmp_info_year'], 'cmp_info_id');
    		if ($tmp_id && $tmp_id!=$id) {
    			message('年份 '.$info['cmp_info_year'].' 信息已存在，不能重复添加');
    		}
    	}

    	if ($info['cmp_info_score']<0 OR $info['cmp_info_score']>1000) {
    		$message[] = '请正确填写考试分数';
    	}
    	
    	if (empty($knowledge_ids) OR ! is_array($knowledge_ids) OR empty($percents) OR ! is_array($percents)) {
    		$knowledge_ids = array();
    	}
    	
    	/*
    	 * 知识点对比项
    	 */
    	$items = array();
    	$total_percent = 100;
    	$current_percent = 0;
		foreach ( $knowledge_ids as $knowledge_id ) {
			// filter invalid data
			if (! $knowledge_id = intval( $knowledge_id )) continue;
			$percent = isset($percents[$knowledge_id]) ? intval($percents[$knowledge_id]) : 0;
			if ($percent < 0 OR $percent > 100) continue;
			if (isset ($items[$knowledge_id])) continue;
			
			$current_percent += $percent;
			if ($current_percent > $total_percent) {
 				$message[] = '一级知识点占比总和不能超过' . $total_percent;
				break;
			}
			
			// valid data
			$items [$knowledge_id] = array (
					'cmp_info_id' => $id,
					'item_knowledge_id' => $knowledge_id,
					'item_percent' => $percent
			);
		}
		
		//外部知识点对比项
		$external_knowledge_names = $this->input->post('external_knowledge_names');
		$external_percents = $this->input->post('external_percents');
		if (empty($external_knowledge_names) OR ! is_array($external_knowledge_names) OR empty($external_percents) OR ! is_array($external_percents)) {
			$external_knowledge_names = array();
		}
		 
		$external_items = array();
		foreach ( $external_knowledge_names as $k=>$item ) {
			// filter invalid data
			$item = trim($item);
			if ($item == '' || 
				!isset($external_percents[$k]) || 
				!($percent = intval($external_percents[$k]))) continue;
			
			//过滤与内部一级知识点重复的新增知识点
			$count_result = $this->db->query("select count(*) as count from {pre}knowledge where knowledge_name='{$item}' and subject_id=$subject_id")->row_array();
			if ($count_result['count']) {
				$message[] = '新增知识点名称（<font color="red">' . $item . '</font>）不能与已有一级知识点重复';
				break;
			}
			
			if ($percent < 0 OR $percent > $total_percent) continue;
			if (isset ($external_items[$item])) continue;
			
			$current_percent += $percent;
			if ($current_percent > $total_percent) {
				$message[] = '一级知识点占比总和不能超过' . $total_percent;
				break;
			}
				
			// valid data
			$external_items [$item] = array (
					'external_knowledge_name' => $item,
					'item_percent' => $percent
			);
		}
		
		//控制知识点占比总和是否等于 100
		if ($current_percent != $total_percent) {
			$message[] = '知识点占比总和必须等于' . $total_percent;
		}
		
		
		$items2 = array();
		if ($subject_id == 3)
		{
		    /*
		     * 信息提取方式对比项
		     */
    		$total_percent = 100;
    		$current_percent = 0;
    		foreach ( $group_type_ids as $gr_id ) {
    		    if (! $gr_id = intval( $gr_id )) continue;
    		    $percent = isset($group_type_percents[$gr_id]) ? intval($group_type_percents[$gr_id]) : 0;
    		    if ($percent < 0 OR $percent > 100) continue;
    		    if (isset ($items2[$gr_id])) continue;
    		     
    		    $current_percent += $percent;
    		    if ($current_percent > $total_percent) {
    		        $message[] = '信息提取方式占比总和不能超过' . $total_percent;
    		        break;
    		    }
    		     
    		    // valid data
    		    $items2[$gr_id] = array (
    		            'cmp_info_id' => $id,
    		            'item_group_type_id' => $gr_id,
    		            'item_percent' => $percent
    		    );
    		}
    		//控制信息提取方式占比总和是否等于 100
    		if ($current_percent != $total_percent) {
    		    $message[] = '信息提取方式占比总和必须等于' . $total_percent;
    		}
    		
    		/*
    		 * 知识点和信息提取方式比例
    		 */
    		$total_percent = 100;
    		$current_percent = 0;
    		foreach ( $extraction_ratio as $ratio ) {
    		    if ($ratio < 0 || $ratio > 100) 
    		    {
    		        $message[] = '知识点和信息提取方式比例范围[0-100]';
    		    }
    		    $current_percent += intval($ratio);
    		    if ($current_percent > $total_percent) {
    		        $message[] = '知识点和信息提取方式比例总和不能超过' . $total_percent;
    		        break;
    		    }
    		}
    		
    		//控制知识点和信息提取方式比例总和是否等于 100
    		if ($current_percent != $total_percent) {
    		    $message[] = '知识点和信息提取方式比例总和必须等于' . $total_percent;
    		}
		}
		//知识点和信息提取方式比例
		$info['cmp_extraction_ratio'] = json_encode($extraction_ratio);
		
		//试题题型对比项（难易度）
		$difficulty_percent = $this->input->post('difficulty_percent');
		$question_amount = $this->input->post('question_amount');

		$q_types = C('q_type');//试题题型
		$diff_types = array('0', '1', '2');//难易度程度（0:低, 1：中, 2：高）
		$item_difficulties = array();
		$total_difficulty_percent = 100;
		foreach ($q_types as $q_type=>$q_type_name) {
			$c_difficulty_percent = 0;
			$has_error = false;
			$tmp_difficulty_percents = array();
			foreach ($diff_types as $diff_type) {
				if ($c_difficulty_percent > $total_difficulty_percent) {
					$message[] = '题型:' . $q_type_name . '的难易度占比总和不能超过' . $total_difficulty_percent;
					$has_error = true;
					break;
				}
				
				if (!is_array($difficulty_percent) || !isset($difficulty_percent[$q_type]) || !isset($difficulty_percent[$q_type][$diff_type])) {
					continue;
				}
				
				$diff_percent = intval($difficulty_percent[$q_type][$diff_type]);
				$c_difficulty_percent += $diff_percent;
				if ($diff_percent < 0 || $diff_percent > $total_difficulty_percent) continue;
				$tmp_difficulty_percents[] = $diff_percent;
			}
			
			//题目数量
			$tmp_question_amount = 0;
			if (!(!is_array($question_amount) || !isset($question_amount[$q_type]))) {
				$tmp_question_amount = intval($question_amount[$q_type]);
			}
			
			if ($tmp_question_amount > 0 && $c_difficulty_percent != $total_difficulty_percent) {
				$message[] = '题型:' . $q_type_name . '的难易度占比总和 必须等于' . $total_difficulty_percent;
				$has_error = true;
			}
			
			if ($has_error) {
				break;
			}
			
			$item_difficulties[] = array(
									'q_type' => $q_type, 
									'difficulty_percent' => implode(',', $tmp_difficulty_percents),
									'question_amount' => $tmp_question_amount,
			);
			
		}
		
		//试题题型对比项（外部题型 难易度）
		$e_difficulty_name = $this->input->post('e_difficulty_name');
		$e_question_amount = $this->input->post('e_question_amount');
		$external_difficulties = array();
		
		!is_array($e_difficulty_name) && $e_difficulty_name = array();
		!is_array($e_question_amount) && $e_question_amount = array();
		if (count($e_difficulty_name)) {
			$t_q_types = C('q_type');
			foreach ($e_difficulty_name as $k=>$v) {
				$v = trim($v);
				if ($v == '' || !isset($e_question_amount[$k])) {
					continue;
				}
				if (in_array($v, $t_q_types)) {
					$message[] = '新增题型名称（<font color="red">' . $v . '</font>）不能与已有题型名称重复';
					continue;
				}
				
				$e_q_amount = intval($e_question_amount[$k]);
				if ($e_q_amount <= 0) {
					continue;
				}
				
				$external_difficulties[$v] = array(
								'name' => $v,
								'question_amount' => $e_q_amount,
				);
			}
		}
		
		//对比项（方法策略）
		$method_tactic_ids = $this->input->post('method_tactic_ids');
		$percents = $this->input->post('method_tactic_percents');
		
		!is_array($method_tactic_ids) && $method_tactic_ids = array();
		!is_array($percents) && $percents = array();
		
		$item_method_tactics = array();
		$total_percent = 100;
		$current_percent = 0;
		foreach ( $method_tactic_ids as $method_tactic_id ) {
			// filter invalid data
			if (! $method_tactic_id = intval( $method_tactic_id )) continue;
			$percent = isset($percents[$method_tactic_id]) ? intval($percents[$method_tactic_id]) : 0;
			if ($percent < 0 OR $percent > 100) continue;
			if (isset ($item_method_tactics[$method_tactic_id])) continue;
				
			$current_percent += $percent;
			if ($current_percent > $total_percent) {
				$message[] = '方法策略占比总和不能超过' . $total_percent;
				break;
			}
				
			// valid data
			$item_method_tactics [$method_tactic_id] = array (
					'cmp_info_id' => $id,
					'method_tactic_id' => $method_tactic_id,
					'percent' => $percent
			);
		}
		
		//对比项（外部方法策略）
		$external_method_tactic_names = $this->input->post('e_method_tactic_names');
		$external_method_tactic_percents = $this->input->post('e_method_tactic_percents');
		
		!is_array($external_method_tactic_names) && $external_method_tactic_names = array();
		!is_array($external_method_tactic_percents) && $external_method_tactic_percents = array();
			
		$external_method_tactics = array();
		foreach ( $external_method_tactic_names as $k=>$item ) {
			// filter invalid data
			$item = trim($item);
			if ($item == '' ||
				!isset($external_method_tactic_percents[$k]) ||
				!($percent = intval($external_method_tactic_percents[$k]))) continue;
				
			//过滤与内部方法策略重复的新增方法策略
			$count_result = $this->db->query("select count(*) as count from {pre}method_tactic where name='{$item}'")->row_array();
			if ($count_result['count']) {
				$message[] = '新增方法策略名称（<font color="red">' . $item . '</font>）不能与已有方法策略重复';
				break;
			}
			
			if ($percent < 0 OR $percent > 100) continue;
			if (isset ($external_method_tactics[$item])) continue;
				
			$current_percent += $percent;
			if ($current_percent > $total_percent) {
				$message[] = '方法策略和外部方法策略占比总和不能超过' . $total_percent;
				break;
			}
		
			// valid data
			$external_method_tactics [$item] = array (
					'name' => $item,
					'percent' => $percent
			);
		}
		
		//控制方法策略占比总和是否等于 100	
		$db = Fn::db();
		$res = $db->fetchRow('SELECT group_concat(DISTINCT subject_id) as subject_ids FROM rd_subject_category_subject');
		$subject_ids = explode(',',$res['subject_ids']);

		if ($current_percent != $total_percent && in_array($subject_id,$subject_ids)) {
			$message[] = '方法策略和外部方法策略占比总和必须等于' . $total_percent;
		}
		
		if (empty ( $items ) && empty ( $external_items ) 
			&& empty ( $item_difficulties ) && empty ( $external_difficulties )
			&& empty ( $item_method_tactics ) && empty ( $external_method_tactics )
			) {
			$message [] = '请填写知识点对比项';
		}
    	 	
    	if ($message) {
    		message(implode('<br/>', $message));
    	}
    	//pr($items,1);

    	// process
    	$res = FALSE;
    	if ($act == 'add')
    	{
    		$actname = '添加';
    		$data = array(
    				'items' => $items,
    		        'items2' => $items2,
    				'external_items' => $external_items,
    				'item_difficulties' => $item_difficulties,
    				'external_difficulties' => $external_difficulties,
    				'item_method_tactics' => $item_method_tactics,
    				'external_method_tactics' => $external_method_tactics,
    		);
                $insert_id = 0;
    		$res = ComparisonInfoModel::insert($info, $insert_id, $data);
    		if ($res)
    			$id = $insert_id;
    	}
    	else
    	{
    		$actname = '编辑';
    		$data = array(
    				'items' => $items,
    		        'items2' => $items2,
    				'external_items' => $external_items,
    				'item_difficulties' => $item_difficulties,
    				'external_difficulties' => $external_difficulties,
    				'item_method_tactics' => $item_method_tactics,
    				'external_method_tactics' => $external_method_tactics,
    		);
    		
    		$res = ComparisonInfoModel::update($id, $info, $data);
    	}
    	
    	if ($res)
    	{
    		admin_log($act, 'comparison_info', $id);    	
        	message('对比信息'.$actname.'成功', 'admin/comparison_info/index/'.$type_id); 
    	}
    	else
    	{
    		//message('对比信息'.$actname.'失败');
    	}
    }
    
    // 操作：禁用0、启用1、删除2
    public function do_action($id=0, $act='')
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
        $id = intval($id);
        $act = trim($act);
        
        $id && $type_id = ComparisonInfoModel::get_comparison_info_by_id($id, 'cmp_type_id');
        if (empty($type_id)) message('信息不存在');
		
        $res = FALSE;
        switch ($act)
        {
        	case 'delete':
        		$res = ComparisonInfoModel::delete($id);
        		$action = 'delete';
        		break;
        	default: 
        		break;
        }
        
        
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url))
        	$back_url = 'admin/comparison_info/index'.$type_id;

        if ($res){
        	if ($action)
        		admin_log($action, 'comparison_info', $id);
        	message('信息删除成功', $back_url);
        } else {
        	message('信息删除失败', $back_url);        	
        }   
    }
    
    // 批量操作
    public function do_batch($act = '')
    {
    	if ( ! $this->check_power('comparison_manage')) return;
    	
    	$act = trim($act);
    	$type_id = (int)$this->input->post('type_id');
        $ids = $this->input->post('ids');
        
        $res = FALSE;
        if ($ids && is_array($ids))
        {
        	$ids = my_intval($ids);

        	switch ($act)
        	{        		
		        case 'delete':
		        case 'remove':
        			$res = ComparisonInfoModel::delete($ids);
        			admin_log('delete', 'comparison_info', implode(',', $ids));
		        	break;
		        default: 
		        	break;	
        	}
        	if ($res)
            	message('批量操作成功！', 'admin/comparison_info/index/'.$type_id);
        	else
        		message('批量操作失败！', 'admin/comparison_info/index/'.$type_id);
        }
        else
        {
            message('请选择要删除的信息');
        }
    }
    
    // 检查年份是否已存在（ajax）
    public function ajax_check_year()
    {
    	$id 	 = (int)$this->input->post('id');
    	$type_id = (int)$this->input->post('type_id');
    	$year 	 = (int)$this->input->post('cmp_info_year');
    	
    	$tmp_id = ComparisonInfoModel::get_comparison_info_by_year($type_id, $year, 'cmp_info_id');

    	echo $tmp_id && $tmp_id!=$id ? 'false' : 'true';
    	
    }

}
