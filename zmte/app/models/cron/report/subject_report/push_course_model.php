<?php
if (!defined('BASEPATH')) exit();

/**
 * 测评报告-学科-推送课程
 * @author TCG
 * @final 2015-08-28
 */
class Push_course_model extends CI_Model
{
    private static $_db;
    private static $_data;
    
    public function __construct()
    {
        parent::__construct();
    
        self::$_db = Fn::db();
        
        $this->load->model('cron/report/subject_report/common_model');
    }
    
    /**
     * 按照综合排序推送课程
     * @param   int     $exam_id    考试ID
     * @param   int     $uid        学生uid
     * @param   int     $subject_id 学科ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $place_id   考场id
     * @return  mixed   list<map<string, variant>>类型数据
     */
    public function module_comprehensive($exam_id, $uid, $subject_id, $exam_pid, $place_id = NULL)
    {
        if (!CoursePushModel::isAcceptPush($uid, $exam_pid, $place_id))
        {
        	return false;
        }
        
    	$paper_id = $this->common_model->get_student_exam_paper($uid, $exam_id);
    	
    	$data['courses'] = CoursePushModel::comprehensivePush($uid, $subject_id, $paper_id);
    	if (!$data['courses'])
    	{
    	    return array();
    	}
    	
    	$data['address'] = self::student_base_address($uid);
    	
    	self::perfect_data($data);
    	
    	return $data;
    }
    
    /**
     * 按照机构声誉推送课程
     * @param   int     $exam_id    考试ID
     * @param   int     $uid        学生uid
     * @param   int     $subject_id 学科ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $place_id   考场id
     * @return  mixed   list<map<string, variant>>类型数据
     */
    public function module_reputation($exam_id, $uid, $subject_id, $exam_pid, $place_id = NULL)
    {
        if (!CoursePushModel::isAcceptPush($uid, $exam_pid, $place_id))
        {
            return false;
        }
        
        $paper_id = $this->common_model->get_student_exam_paper($uid, $exam_id);
         
        $data['courses'] = CoursePushModel::reputationPush($uid, $subject_id, $paper_id);
        if (!$data['courses'])
        {
            return array();
        }
        
        $data['address'] = self::student_base_address($uid);
         
        self::perfect_data($data);
        
        return $data;    
    }
    
    /**
     * 按照知识点符合度推送课程
     * @param   int     $exam_id    考试ID
     * @param   int     $uid        学生uid
     * @param   int     $subject_id 学科ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $place_id   考场id
     * @return  mixed   list<map<string, variant>>类型数据
     */
    public function module_knowledge($exam_id, $uid, $subject_id, $exam_pid, $place_id = NULL)
    {
        if (!CoursePushModel::isAcceptPush($uid, $exam_pid, $place_id))
        {
            return false;
        }
        
        $paper_id = $this->common_model->get_student_exam_paper($uid, $exam_id);
        
        $data['courses'] = CoursePushModel::knowledgePush($uid, $subject_id, $paper_id);
        if (!$data['courses'])
        {
            return array();
        }
        
        $data['address'] = self::student_base_address($uid);
        
        self::perfect_data($data);
        
        return $data;
    }
    
    /**
     * 按照课程价格推送课程
     * @param   int     $exam_id    考试ID
     * @param   int     $uid        学生uid
     * @param   int     $subject_id 学科ID
     * @param   int     $exam_pid   考试期次
     * @param   int     $place_id   考场id
     * @return  mixed   list<map<string, variant>>类型数据
     */
    public function module_price($exam_id, $uid, $subject_id, $exam_pid, $place_id = NULL)
    {
        if (!CoursePushModel::isAcceptPush($uid, $exam_pid, $place_id))
        {
            return false;
        }
        
        $paper_id = $this->common_model->get_student_exam_paper($uid, $exam_id);
         
        $data['courses'] = CoursePushModel::pricePush($uid, $subject_id, $paper_id);
        if (!$data['courses'])
        {
            return array();
        }
        
        $data['address'] = self::student_base_address($uid);
        
        self::perfect_data($data);
        
        return $data;
    }
    
    /**
     * 完善课程推送数据
     * @param array $data
     */
    private function perfect_data(&$data)
    {
        if (!$data)
        {
            return $data;
        }
        
        $cors_ids = array();
        $cc_ids = array();
        foreach ($data['courses'] as $item)
        {
            if ($item['cors_cmid'] == 1)
            {
                $cors_ids[] = $item['cors_id'];
                $cc_ids[] = $item['cc_id'];
            }
        }
         
        $cors_ids = array_unique($cors_ids);
         
        //获取一对一课程授课范围(年纪、学科)
        if ($cors_ids)
        {
            $data['course_range'] = CoursePushModel::courseRange($cors_ids);
        }
         
        //获取一对一课程授课老师
        if ($cc_ids)
        {
            $data['course_teacher'] = CoursePushModel::courseTeacher($cc_ids);
        }
        
        //获取授课模式
        $data['cors_stunumtype'] = CourseModel::courseStuNumTypePairs();
    }
    
    /**
     * 学生地区
     * @param   int     $uid    学生UID
     * @return  array   map<string, variant>类型数据
     */
    private static function student_base_address($uid)
    {
    	if (!empty(self::$_data['region'][$uid]))
    	{
    		return self::$_data['region'][$uid];
    	}
    	
    	self::$_data['region'] = array();
    	
    	$sql = "SELECT region_id, region_name FROM rd_region r
    	        LEFT JOIN t_student_base sb ON sb.sb_addr_provid = r.region_id 
    	           OR sb.sb_addr_cityid = r.region_id OR sb.sb_addr_cityid = r.parent_id
    	        WHERE sb_uid = $uid";
    	
    	self::$_data['region'][$uid] = self::$_db->fetchPairs($sql);
    	
    	return self::$_data['region'][$uid];
    }
}