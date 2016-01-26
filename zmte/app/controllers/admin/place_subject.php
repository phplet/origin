<?php if ( ! defined('BASEPATH')) exit();
/**
 *
 * 考试场次 所考 学科 控制器
 * @author TCG
 * @final 2015-08-24
 *
 */
class Place_subject extends A_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->model('cron/cron_place_student_paper_model', 'cron_place_student_paper_model');
    }

    /**
     * 考场添加学科
     *
     * @param int $exam_id 考试期次ID
     * @param int $place_id 考场ID 
     * @return void
     */
    public function index($exam_id = 0, $place_id = 0) {

        if(!$this->check_power('exam_list,exam_manage')) return;

        if (empty($exam_id) || empty($place_id)) {
            message('参数错误！');
        }

        $query = "select * from {pre}exam_place where place_id={$place_id}";
        $place = $this->db->query($query)->row_array();

        if (!$place) {
            message('未查询到考场信息！');
        }

        $query = "select * from {pre}exam where exam_id={$exam_id}";
        $exam = $this->db->query($query)->row_array();

        if (!$exam) {
            message('未查询到考场信息！');
        }

        //获取考试期次下的学科
        $exams = $this->db->query("select exam_id,subject_id from {pre}exam where rd_exam.exam_pid={$exam_id}")->result_array();

        if (empty($exams)) {
            message('该考试期次不存在学科！');
        }
            
        //控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        $place['no_start'] = $no_start;

        $query = array(
            'exam_pid' => $exam_id,
            'place_id' => $place_id,
        );
        $result = ExamPlaceSubjectModel::get_exam_place_subject_list($query);

        $old_subjects = array();
        foreach ($result as $key => $value) {
            $old_subjects[$value['subject_id']] = $value;
        }

        $data = array();
        $data['exam'] = $exam;
        $data['place'] = $place;
        //$data['allow_subject'] = CpUserModel::get_allowed_subjects();
        $data['subjects'] = $exams;
        $data['subjects_name'] = C('subject');
        $data['old_subjects'] = $old_subjects;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

        // 模版
        $this->load->view('place_subject/index', $data);
    }
    
    /*
     * 删除--保存添加记录
    */
    public function update() {
    	if (!$this->check_power('exam_manage')) return;
    	
		$exam_pid = $this->input->post('exam_pid');
		$place_id = $this->input->post('place_id');
		$subjects = $this->input->post('subjects');

        $this->db->trans_start();

        /* 删除考试期次下考场原有学科 */
        $map = array();
        $map['exam_pid'] = $exam_pid;
        $map['place_id'] = $place_id;
    	$this->db->delete('exam_place_subject', $map);
    	
    	foreach ($subjects as $v) {
            $data = array();
            $temp = explode('_', $v);
            $data['exam_pid'] = $exam_pid;
            $data['place_id'] = $place_id;
            $data['exam_id'] = $temp[1];
            $data['subject_id'] = $temp[0];

    		$this->db->insert('exam_place_subject', $data);
    	}

        $this->db->trans_complete();
    	
    	$bak_url = 'admin/place_subject/index/' . $exam_pid. '/' . $place_id;

        if ($this->db->trans_status() === FALSE) {
            message('学科添加失败！', $bak_url);
        } else {
            message('学科添加成功！', $bak_url);
        }
    }  
}
