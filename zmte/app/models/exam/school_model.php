<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-学校数据模块
 *
 * @author qcchen
 * @final 2013-12-04
 */
class School_model extends CI_Model 
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'school';
	
	/**
	 * construct
	 */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 按 id 获取一个学校信息
     *
     * @param   int     学校ID(school_id)
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_school_by_id($id = 0, $item = NULL)
    {
        if ($id == 0)
        {
            return FALSE;
        }
        
        if ($item)
            $this->db->select($item);
        $query = $this->db->get_where('school', array('school_id' => $id), 1);
        $school =  $query->row_array();
        
        if ($item && isset($school[$item]))
            return $school[$item];
        else
            return $school;
    }
    

}
/* End of file school_model.php */
/* Location: ./application/models/exam/school_model.php */