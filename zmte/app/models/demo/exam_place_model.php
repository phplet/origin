<?php if ( ! defined('BASEPATH')) exit();

/**
 * 机考-考场数据模块
 * 
 * @author qcchen
 * @final 2013-12-04
 */
class Exam_place_model extends CI_Model 
{
	/**
	 * 表名
	 * @var string
	 */
	// 考场
	private static $_table_name = 'exam_place';
	// 考场科目
	private static $_table_exam_place_subject = 'exam_place_subject';
	// 考试科目所考到的试卷
	private static $_table_exam_subject_paper = 'exam_subject_paper';
	// 考场学生
	private static $_table_exam_place_student = 'exam_place_student';	
	
	/**
	 * construct
	 * 
	 */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取一个考场信息
     * 
     * @param 	int 		$place_id   考场ID
     * @param 	string 		$items  	需要获取的字段，默认获取全部，多个字段用逗号分割
     * @return 	mixed 					查询多字度，返回数组。单个字段直接返回字段值。
     */
    public function get_exam_place_by_id($place_id = 0, $items = NULL)
    {
        if ($place_id == 0)
        {
            return FALSE;
        }
        if ($items)
        {
            $this->db->select($items);
        }
        $query = $this->db->get_where(self::$_table_name, array('place_id' => $place_id));
        $row =  $query->row_array();
        if ($items && isset($row[$items]))
            return $row[$items];
        else
            return $row;
    }

  	/**
  	 * 按条件获取考场列表
  	 *
  	 * @param   array		查询条件
  	 * @param	string		排序
  	 * @param	int			获取数据数量
  	 * @param	string/array	获取字段
  	 * @return  array
  	 */
  	public function get_exam_place_list($condition = array(), $order_by=NULL, $limit=NULL, $select_items=NULL)
  	{
  		// selects
  		if (is_array($select_items))
  			$select_items = implode(',', $select_items);
  		if ($select_items)
  			$this->db->select($select_items);

  		// conditions
  		if ($condition && is_array($condition))
  		{  			
  			foreach ($condition as $key => $val)
  			{
  				switch ($key)
  				{
  					case 'place_id' :
  					case 'exam_pid' :
  					case 'school_id' :
  					case 'ip' :
  						$this->db->where($key, $val);
  						break; 
  					case 'period_time' :
  						//-------------notice---------------------------//
  						// find exam_place between the period_time
  						// start_time - A <= time() <= end_time + B
  						// => $val = array(time()+A, time()-B);
  						// A: wait time before start testing
  						// B: wait time after end testing 
  						//----------------------------------------------//
  						if ( ! is_array($val)) {
  							$val = explode(',', $val);
  						}
  						if (count($val) < 1) continue;
  						$this->db->where('start_time <=', $val[0]);
  						$this->db->where('end_time >=', $val[1]);
  						break;
  					default:
  						break;
  				}
  			}
  		}
  		
  		// order by
  		if ($order_by)
  			$this->db->order_by($order_by);
  		 
  		// limit
  		if ($limit)
  			$this->db->limit($limit);
  		 
  		// get data
  		$query = $this->db->get(self::$_table_name);
  		return $query->result_array();
  	}
  	 

  	/**
  	 * 检查 学生 是否在一个考场名单中
  	 *
  	 * @param	int			考试场次id(place_id)
  	 * @param	int			学生id(uid)
  	 * @return  boolean
  	 */
  	public function check_exam_place_student($place_id, $uid)
  	{
  		$query = $this->db->select('id')->get_where(self::$_table_exam_place_student, array('place_id'=>$place_id, 'uid'=>$uid), 1);
  		return $query->num_rows()>0;
  	}
  	
  	/**
  	 * 检查 考场的 考生列表
  	 *
  	 * @param	int			考试场次id(place_id)
  	 * @param	boolean	 	是否加载考生的信息
  	 * @return  boolean
  	 */
  	public function get_exam_place_student_list($place_id, $load_student = false)
  	{
  		$query = $this->db->select('uid')->get_where(self::$_table_exam_place_student, array('place_id'=>$place_id));
  		$result = $query->result_array();
  		$data = array();
  		if ($load_student === true) {
  			$this->load->model('demo/student_model');
  			foreach ($result as $item) {
  				$uid = $item['uid'];
  				$student = $this->student_model->get_student_by_id($uid,  'first_name, last_name, exam_ticket');
  				$truename = count($student) ? ($student['last_name'] . $student['first_name']) : '--';
  				$ticket = count($student) ? $student['exam_ticket'] : '--';
  				
  				$data[] = array(
  								'uid'		=> $uid,
  								'truename' 	=> $truename, 
  								'ticket' 	=> $ticket, 
  				);	
  			}
  			
  			return $data;
  		} else {
  			return $result;
  		}
  	}
  	
  	/**
  	 * 获取 某个考场的 考生人数
  	 *
  	 * @param	int			考试场次id(place_id)
  	 * @return  boolean
  	 */
  	public function count_exam_place_students($place_id)
  	{
  		$query = $this->db->select('count(*) as total')->get_where(self::$_table_exam_place_student, array('place_id'=>$place_id));
  		$result = $query->result_array();
  		
  		return $result[0]['total'];
  	}
  	
  	/**
  	 * 按 考试场次 获取该场次考试科目
  	 *
  	 * @param	int			考试场次id(place_id)
  	 * @return  array
  	 */
	public function get_exam_place_subject($place_id)
	{
		$list = array();
		$query = $this->db->select('subject_id,exam_pid,exam_id')->get_where(self::$_table_exam_place_subject, array('place_id'=>$place_id));
		
		foreach ($query->result_array() as $val)
		{
			$list[] = array(
				'exam_pid' => $val['exam_pid'],
				'exam_id' => $val['exam_id'],
				'subject_id' => $val['subject_id'], 
				'subject_name' => C('subject/'.$val['subject_id']),
			);
		}
		return $list;
	}
	
	/**
	 * 按 考试场次 获取该场次考试备选试卷
	 *
	 * @param	int			考试场次id(place_id)
	 * @param	int			学科id(subject_id)
	 * @return  array
	 */
	public function get_exam_place_paper($place_id, $subject_id=NULL)
	{
		$list = array();
		
		//获取考场考试科目
		$this->db->select('exam_id');
		$this->db->where('place_id', $place_id);
		if ($subject_id)
			$this->db->where('subject_id', $subject_id);
		
		$exam_ids = array();
		$result = $this->db->get(self::$_table_exam_place_subject)->result_array();
		foreach ($result as $item) {
			$exam_ids[] = $item['exam_id'];
		}
		
		$this->db->select('paper_id,subject_id');
		$this->db->where_in('exam_id', $exam_ids);
		$query = $this->db->get(self::$_table_exam_subject_paper);
		
		return $query->result_array();		
	}
}

/* End of file exam_place_model.php */
/* Location: ./application/models/demo/exam_place_model.php */