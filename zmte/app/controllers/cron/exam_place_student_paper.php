<?php !defined('BASEPATH') && exit();
/**
 * 考试期次下考场分配试卷
 * @author tcg
 * @create 2015-08-06
 */
class Exam_place_student_paper extends Cron_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 考试前2小时给参加考试的学生分配试卷及试题
     * @return boolean
     */
    public function init_distribution_paper()
    {
    	set_time_limit(0);
    	
        $this->load->model('cron/cron_place_student_paper_model', 'init_model');

        $place_list = $this->init_model->get_task_place_student_paper_list();

        if (!$place_list)
        {
            return false;
        }
        
        $db = Fn::db();

        $ids = array();
        $place_uids = array();
        $place_ids = array();
        
        foreach ($place_list as $place)
        {
            $ids[] = $place['id'];
            $place_id = $place['place_id'];
            $uids = json_decode($place['uid_data'], true);
            $exam_pid = $place['exam_pid'];

            if (!isset($place_uids[$place_id]))
            {
                $sql = "SELECT uid FROM rd_exam_place_student 
                        WHERE place_id = $place_id";
                $place_uids[$place_id] = $db->fetchCol($sql);
            }
            $place_student = $place_uids[$place_id];
            
            $uids = array_intersect($uids, $place_student);
            
            foreach ($uids as $uid)
            {
                //给学生分配试卷
                $result = $this->init_model->init_test_paper($place_id, $uid);
                
                if ($result)
                {
                    $place_ids[$exam_pid][$place_id][] = $uid;
                }
            }
        }
        
        if ($result)
        {
            $param = array('status' => 2, 'u_time' => time());
        }
        else
        {
            $param = array('status' => 1, 'u_time' => time());
        }
        
        $this->init_model->set_task_exam_result_status($param, $ids);

        //给考场学生分配试题
        if ($place_ids)
        {
            foreach ($place_ids as $exam_pid => $place)
            {
                foreach ($place as $place_id => $uids)
                {
                    $uids = array_unique($uids);
                    $this->init_model->init_test_question($exam_pid, $place_id, $uids);
                }
            }
        }
    }

    /**
     * 手动给参加考试的学生分配试卷及试题
     *
     * @return void
     */
    public function init_distribution_paper_manual()
    {
        set_time_limit(0);
        $db = Fn::db();
        $this->load->model('cron/cron_place_student_paper_model', 'init_model');

        /* 手动指定考场 */
        /* $places = array(57,58,59,60); */
        $places = array(60);
        $place_list = array();

        /* 获取考场学生 */
        foreach ($places as $key => $value) {
            $sql = "SELECT uid FROM rd_exam_place_student WHERE place_id='{$value}'";
            $place_list[$value]['uids'] = $db->fetchCol($sql);
        }

        $place_uids = array();
        $place_ids = array();
        
        foreach ($place_list as $place_id => $place) {
            $exam_pid = $place['exam_pid'];
            
            foreach ($place['uids'] as $uid) {
                //给学生分配试卷
                $result = $this->init_model->init_test_paper($place_id, $uid);
                
                if ($result) {
                    $place_ids[$exam_pid][$place_id][] = $uid;
                }
            }
        }

        //给考场学生分配试题
        if ($place_ids) {
            foreach ($place_ids as $exam_pid => $place) {
                foreach ($place as $place_id => $uids) {
                    $uids = array_unique($uids);
                    $this->init_model->init_test_question($exam_pid, $place_id, $uids);
                }
            }
        }
    }
}