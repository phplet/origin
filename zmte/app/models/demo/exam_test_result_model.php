<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-学生考试答题记录
 *
 * @author qcchen
 * @final 2013-12-07
 */
class Exam_test_result_model extends CI_Model 
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'exam_test_result';

    /**
     * 构造函数，初始化
     *          
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取考题详情
     * - 题组同时返回子题列表
     * - 若无答题记录，则插入记录
     * - 若有记录，则把答案返回
     *
     * @param   int   		考卷id
     * @param   int  		试题id
     * @return  array
     */
    public function get_test_question($etp_id, $ques_id)
    {
    	$this->load->model('demo/exam_test_paper_model');
    	$test_paper = $this->exam_test_paper_model->get_test_paper_by_id($etp_id, 'etp_id,exam_pid,exam_id,uid,place_id,paper_id,etp_flag');
    	if (empty($test_paper) OR $test_paper['etp_flag']!=0) {
    		// 考卷不存在，已提交、或已作废
    		return false;
    	}    
    	
    	$this->load->model('demo/question_model');
    	$question = $this->question_model->get_question_detail($ques_id);

    	$q_type = $question['type'];
    	if ($q_type == 0) {
    		$ques_ids = array_keys($question['children']);
    		array_push($ques_ids, $ques_id);
    	} else {
    		$ques_ids = $ques_id;
    	}
    	// 获取答题明细
    	$condition = array(
    		'etp_id' => $etp_id,
    		'ques_id' => $ques_ids
    	);
    	$results = $this->get_test_result_list($condition, 'etr_id, ques_id,answer,option_order,ques_subindex');
    	$result_list = array();
    	foreach ($results as $row) {
    		$result_list[$row['ques_id']][] = $row;
    	}
    	unset($results);
    	
    	$this->load->model('demo/exam_paper_model');
    	$paper_detail = $this->exam_paper_model->get_paper_question_detail($test_paper['paper_id']);
    	
		if (!isset($paper_detail[$q_type]['list'][$ques_id])) {
			return false;
		}
    	$ques_info = $paper_detail[$q_type]['list'][$ques_id];
    	// 记录初始化
    	$data = array(
    			'etp_id' 		=> $etp_id,
    			'exam_pid' 		=> $test_paper['exam_pid'],
    			'exam_id' 		=> $test_paper['exam_id'],
    			'uid' 			=> $test_paper['uid'],
    			'paper_id' 		=> $test_paper['paper_id'],
    			'ques_id' 		=> $question['ques_id'],
    			'ques_index' 	=> $ques_info['index'],
    			'ques_subindex' => 0,
    			'option_order' 	=> '',
    			'answer' 		=> '',
    			'full_score' 	=> $ques_info['score'],
    			'test_score' 	=> 0, 
    	);
    	
        if ( ! isset($result_list[$question['ques_id']])) {
        	// 记录不存在，先插入数据
        	/*
        	 * 选择题
        	 */
        	if ($q_type == 1 OR $q_type == 2 OR $q_type == 7) {
        		shuffle($question['options']);
        		$data['option_order'] = implode(',',array_keys($question['options']));

	        	//插入考生每个考题记录
	    		$etr_id = $this->insert($data);
	    		$question['etr_id'] = $etr_id;
	    		$question['answer'] = '';
	    		
        	} elseif ($q_type == '3' || $q_type == '9') {
        		//填空题
	    		$etr_id = $this->insert($data);
	    		$question['etr_id'] = $etr_id;
	    		$question['answer'] = '';
        		
        	} elseif (in_array($q_type,array(0,4,5,6,8))) {
	    		// 题组子题    		
	    		$data['option_order'] = '';
    			$sub_index = 1;
    			$left_score = $ques_info['score'];
    			$count = 0;
    			$key_caches = array();
    			$insert_data = array();
    			
    			foreach ($question['children'] as $key=> &$children) {
    				$data['ques_subindex'] = $sub_index;
    				$data['sub_ques_id'] = $children['ques_id'];
    				if (in_array($children['type'],array(1,2,5,6)) && $children['options']) {
    					shuffle($children['options']);
    					$data['option_order'] = implode(',',array_keys($children['options']));
    				} else {
    					$data['option_order'] = '';
    				}
    				if ($sub_index < count($question['children'])) {
    					$data['full_score'] = round($ques_info['score']/count($question['children']), 2);
    					$left_score -= $data['full_score'];
    				} else {
    					$data['full_score'] = $left_score;
    				}

    				//$insert_data[] = $data;
    				//$key_caches[$count] = $key;
    				//$count++;

    				$children['etr_id'] = $this->insert($data);

    				$children['answer'] = '';

    				$sub_index++;
    			}
    			
    			/*
    			$insert_return = $this->insert_batch($insert_data);
    			$total_affected_rows = $this->db->affected_rows();
    			if ($insert_return && $total_affected_rows == $count) {
					$first_insert_id = $this->db->insert_id() - 1;
					for ($i = 0; $i < $count; $i++) {
						$incr = $count + 1;
						$question['children'][$key_caches[$i]]['etr_id'] = $first_insert_id + $incr;
					}
    			}*/
    		}
    	} else {
    		// 记录已存在
    		/*
    		 * 补齐试题答案
    		 */
    		$record = &$result_list[$question['ques_id']]; 
    		if (in_array($q_type,array(0,4,5,6,8))) {
	    		$result_subidx_etrids = $this->_get_test_result_subindex_etrid($result_list);
    			$sub_index = 1;
    			foreach ($question['children'] as $key=> &$children) {
    				$record = &$result_list[$children['ques_id']];
    				$data['sub_index'] = $sub_index;
    				$children['etr_id'] = isset($result_subidx_etrids[$sub_index]) ? $result_subidx_etrids[$sub_index]['etr_id'] : '0';
    				
    				//附加答案
    				$tmp_answer = isset($result_subidx_etrids[$sub_index]) ? $result_subidx_etrids[$sub_index]['answer'] : '';
    				$tmp_answer = $this->_filter_answer($tmp_answer, $children['type'], $children['type'] == '3' ? $children['title'] : '');
    				$children['answer'] = $tmp_answer;
    				
    			    if (in_array($children['type'],array(1,2,5,6))) {
	    				// 选择题，重新排序选项
	    				$children['options'] = $this->_resort_option_order($children['options'], $record['option_order']);
    				}
    				
    				$sub_index++;
    			}  	
    		} elseif ($q_type == 3  || $q_type == 9) {
    			// 填空题，设置答案
    			$record = $record[0];
    			$question['etr_id'] = $record['etr_id'];
    			$question['answer'] = $this->_filter_answer($record['answer'], $q_type, $question['title']);
    		} else {
    			// 选择题，重新排序选项
    			$record = $record[0];
    			$question['etr_id'] = $record['etr_id'];
    			$question['options'] = $this->_resort_option_order($question['options'], $record['option_order']);
    			$question['answer'] = $this->_filter_answer($record['answer'], $q_type);
    		}
    	}
    	
    	return $question;
    }
		
    /**
     * 获取考试结果列表
     *          
     * @param   array   		查询条件
     * @param   string/array  	选择字段列表
     * @return  array
     */
    public function get_test_result_list($condition=array(), $select_items = NULL)
    {
    	if (is_array($select_items)) $select_items = implode(',', $select_items);
        if ($select_items)
            $this->db->select($select_items);
        
        if ($condition && is_array($condition))
        {
        	foreach ($condition as $key => $val)
        	{
        		switch ($key)
        		{
        			case 'etp_id' :
        			case 'exam_pid' :
        			case 'exam_id' :
        			case 'uid' :
        			case 'paper_id':
        				$this->db->where($key, $val);
        			case 'ques_id' :
        				if (is_array($val))
        					$this->db->where_in($key, $val);
        				else
        					$this->db->where($key, $val);
        				break;
        			case 'ques_index' :
        				// $val = array('>', 0) OR array('=', 0)
        				if (is_array($val)) {
        					$this->db->where('ques_index '.$val[0], $val[1]);
        				} else {
        					$this->db->where($key, $val);
        				}        					
        				break;
        			default: break;
        		}
        	}
        }
        
        $query = $this->db->get(self::$_table_name); 
		return $query->result_array();
    }
    
    /**
     * 添加答题记录
     *
     * @param   array   		数据数组
     * @return  boolean
     */
    public function insert($data)
    {
        try {
    		$this->db->db_debug = FALSE;
    		$res = $this->db->insert(self::$_table_name, $data);
    		if ($res) {
    			return $this->db->insert_id();
    		} else {    			
    			log_message('error', 'mysql error:'.$this->db->_error_message());
    			throw New Exception($this->db->_error_message());
    		}
    	} catch (Exception $e) {
    		return FALSE;
    	}
    }
    
    /**
     * 批量添加答题记录
     *
     * @param   array   数据数组
     * @return  boolean
     */
    public function insert_batch($data)
    {
        try {
    		$this->db->db_debug = FALSE;
    		$res = $this->db->insert_batch(self::$_table_name, $data);
    		if ($res) {
    			return true;
    		} else {    			
    			log_message('error', 'mysql error:'.$this->db->_error_message());
    			throw New Exception($this->db->_error_message());
    		}
    	} catch (Exception $e) {
    		return FALSE;
    	}
    }
    
    /**
     * 单条修改答题记录
     *
     * @param	int				答题记录ID
     * @param   array   		数据数组
     * @return  boolean
     */
    public function update($etr_id, $data)
    {
        try {
    		if (is_array($etr_id)) {
    			$this->db->where_in('etr_id', $etr_id)->update(self::$_table_name, $data);
    		} else {
    			$this->db->where('etr_id', $etr_id)->update(self::$_table_name, $data);
    		}
    		return TRUE;
    	} catch(Exception $e) {
    		return FALSE;
    	}
    }
    
    /**
     * 多条更新答题记录
     *
     * @param   array   更新数据
     * @return  boolean
     */
    public function update_batch($update_data)
    {
        try {
        	$this->db->trans_start();
        	
//         	// 关闭错误信息
//         	$this->db->db_debug = FALSE;
        	
    		$this->db->update_batch(self::$_table_name, $update_data, 'etr_id');
    		$this->db->trans_complete();
    		return TRUE;
    	} catch(Exception $e) {
    		$this->db->trans_complete();
    		return FALSE;
    	}
    }
    
    /**
     * 按选项id顺序($option_order)重新排序选项数组($options)
     *
     * @param	array			选择数组
     * @param   string   		选择id顺序（逗号分隔）
     * @return  array
     */
    private function _resort_option_order($options, $option_order) 
    {
    	// 选项排序
    	$new_options = array();
    	$opt_order = explode(',', $option_order);
    	foreach ($opt_order as $opt_id) {
    		if (isset($options[$opt_id])) {
    			$new_options[$opt_id] = $options[$opt_id];
    			unset($options[$opt_id]);
    		}
    	}
    	if ($options) {
    		$new_options += $options;
    	}
    	return $new_options;    	
    }
    
    /**
     * 设置填空题输入框中，考生的答案
     *
     * @param   string   		问题id
     * @param   string   		填空题
     * @param   string   		选择id顺序（逗号分隔）
     * @return  array
     */
    private function _set_input_answer(&$content, $answer)
    {
//     	$answers = explode("\n", $record['answer']);
//     	preg_match_all('/(<input [^>]*?\/>)/s', $content, $matchs);
//     	foreach ($matchs[0] as $match) {
    		
//     	}
    	
    	return $answer;
    }

    /*
     * 提取 题组中sub_index 与 etr_id 对应关系
     */
    private function _get_test_result_subindex_etrid(&$test_result)
    {
    	$tmp_data = array();
    	foreach ($test_result as $v) {
    		foreach ($v as $item) {
	    		$tmp_data[$item['ques_subindex']] = array(
	    						'etr_id' => $item['etr_id'],
	    						'answer' => $item['answer'],
	    		); 
    		}
    	}
    	
    	return $tmp_data;
    }
    
   /**
    * 处理answer内容
    * @param string $content
    * @param string $answer
    * @param integer $type
    * @return mixed
    */
    private function _filter_answer($answer, $type, $content = '')
    {
   		switch ($type) {
			case '1'://单选
				break;
			case '2'://不定项
    			$answer = explode(',', $answer);
    			break;
    		case '3'://填空
    		case '9': // 连词成句
    			$answer = explode("\n", $answer);
    			$answer = $this->_set_input_answer($content, $answer);
				break;
			default :
				break;
		}
		
		return $answer;
    }
}

/* End of file exam_test_result_model.php */
/* Location: ./application/models/demo/exam_test_result_model.php */
