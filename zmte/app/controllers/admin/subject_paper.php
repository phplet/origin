<?php if ( ! defined('BASEPATH')) exit();
/**
 * 期次科目 试卷 控制器
 * @author TCG
 * @create 2015-08-24
 *
 */
class Subject_paper extends A_Controller
{
    private static $_table_name = 'cron_task_place_student_paper';

    public function __construct()
    {
        parent::__construct();

        $this->load->model('cron/cron_place_student_paper_model', 'cron_place_student_paper_model');

    }

    /**
     * @description 期次科目试卷列表
     * @author
     * @final
     * @param int $exam_id 考试期次id
     */
    public function index($exam_id=0)
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;
    	$paper_diy = ($this->check_power('paper_diy',false)&&!$this->is_super_user())?1:0;
        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();
        /*
        try {
            $this->db->trans_start();
        $sql = "DELETE FROM {pre}exam_subject_paper WHERE paper_id NOT IN(SELECT paper_id from {pre}exam_paper )";
        $this->db->query($sql);

        $this->db->trans_complete();

        } catch(Exception $e) {
            $this->db->trans_complete();

        }
        */

        $exam_id = intval($exam_id);
        if ($exam_id) {
            $query = $this->db->select('e.exam_name,e.exam_id,e.exam_pid,s.subject_name')
                ->from('exam e')->join('subject s',  's.subject_id=e.subject_id')
                ->where(array('exam_id' => $exam_id, 'exam_pid >' => 0))->get();
            $exam = $query->row_array();
        }
        if (empty($exam)) {
            message('考试科目信息不存在', 'admin/exam/index');
            return;
        }

        $p_exam_name = ExamModel::get_exam($exam['exam_pid'], 'exam_name');
        $exam['p_exam_name'] = $p_exam_name;

        //检查该学科考试状态
        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);
        $exam['has_tested'] = $has_tested;

        $where[] = "sp.exam_id=$exam_id";

        $search['subject_id'] = (int)$this->input->get('subject_id');
        if ($search['subject_id']) {
            $where[] = "sp.subject_id=$search[subject_id]";
            $param[] = "subject_id=".$search['subject_id'];
        }
        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';
       
        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_subject_paper sp $where";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        // 读取数据
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();

        if ($total) {
            $sql = "SELECT sp.*,p.paper_name,p.ques_num,p.qtype_ques_num,p.difficulty,p.is_delete,sub.subject_name,p.question_sort,
            p.admin_id FROM {pre}exam_subject_paper sp
                     LEFT JOIN {pre}subject sub ON sp.subject_id=sub.subject_id
                     LEFT JOIN {pre}exam_paper p ON sp.paper_id=p.paper_id
                    $where LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row) { 
                $row['paper_diy'] = (($this->check_power('paper_diy',false) && $this->session->userdata('admin_id')==$row['admin_id'])||$this->is_super_user())?1:0;
                
                $row['qtype_ques_num'] = explode(',', $row['qtype_ques_num']);
                
                $list[] = $row;
            }
        }

        // 分页
        $purl = site_url('admin/subject_paper/index/'.$exam_id) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        // 学科分组
        $sql = "SELECT sp.subject_id, sub.subject_name, count(sp.id) nums FROM {pre}exam_subject_paper sp,{pre}subject sub WHERE sp.subject_id=sub.subject_id AND sp.exam_id=$exam_id GROUP BY subject_id";
        $query = $this->db->query($sql);
        $data['subject_list'] = $query->result_array();
        $data['paper_diy'] = $paper_diy;
        $data['search'] = &$search;
        $data['exam']  = &$exam;
        $data['list']   = &$list;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

        // 模版
        $this->load->view('subject_paper/index', $data);
    }

    /**
     * @description 期次科目试卷添加操作页面
     * @author
     * @final
     * @param int $exam_id 考试期次id
     */
    public function add($exam_id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = intval($exam_id);
        $exam_id && $exam = ExamModel::get_exam($exam_id, 'exam_name,exam_id,exam_pid,subject_id');
        if (empty($exam)) {
            message('考试期次科目不存在', 'admin/exam/index');
            return;
        }

        //样卷，跳到样卷选择
        if ($exam['exam_pid'] == 1) {
        	//redirect('admin/subject_paper/add_demo/'.$exam_id);
        }

        //检查该学科考试状态
        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);
        if ($has_tested) {
			message('该期次科目已经被考生考过 或者 正在被考中,因此无法操作');
        }

        $subjects = CpUserModel::get_allowed_subjects();
        $exam['subject_name'] = $subjects[$exam['subject_id']];
        $subject_id = $exam['subject_id'];

        /* 加入外部试卷支持 */
        $paper_list = array();

        
        if(($this->check_power('paper_diy',false) 
            && !$this->is_super_user())) 
        {
         
              $sql = "SELECT COUNT(*) nums FROM {pre}exam_paper p
                      WHERE (p.exam_id=$exam_id OR (p.admin_id={$this->session->userdata('admin_id')} AND p.exam_id is null)) 
                      AND p.is_delete=0 and p.ques_num>0
                      AND NOT EXISTS(SELECT paper_id FROM {pre}exam_subject_paper
                      WHERE exam_id=$exam_id AND paper_id=p.paper_id)";
        } 
        else 
        { 
            $sql = "SELECT COUNT(*) nums FROM {pre}exam_paper p
                    WHERE (p.exam_id=$exam_id) AND p.is_delete=0 and p.ques_num>0
                    AND NOT EXISTS(SELECT paper_id FROM {pre}exam_subject_paper
                    WHERE exam_id=$exam_id AND paper_id=p.paper_id)";
        }
        $query = $this->db->query($sql);
        $row = $query->row_array();
        $total = $row['nums'];
        $size   = 30;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        if ($total) {
            
            if(($this->check_power('paper_diy',false)&&!$this->is_super_user())) {
                 
                $sql = "SELECT * FROM {pre}exam_paper p
                WHERE (p.exam_id=$exam_id OR (p.admin_id={$this->session->userdata('admin_id')}) AND p.exam_id is null) 
                AND p.is_delete=0 and p.ques_num>0
                AND NOT EXISTS(SELECT paper_id FROM {pre}exam_subject_paper
                WHERE exam_id=$exam_id AND paper_id=p.paper_id) LIMIT $offset,$size";
            } else {
                $sql = "SELECT * FROM {pre}exam_paper p
                WHERE (p.exam_id=$exam_id) AND p.is_delete=0 and p.ques_num>0
                AND NOT EXISTS(SELECT paper_id FROM {pre}exam_subject_paper
                WHERE exam_id=$exam_id AND paper_id=p.paper_id) LIMIT $offset,$size";
            }
           
            $query = $this->db->query($sql);
            foreach ($query->result_array() as $row) { 
                $row['qtype_ques_num'] = explode(',', $row['qtype_ques_num']);
                $paper_list[] = $row;
            }
        }
        // 分页
        $purl = site_url('admin/subject_paper/add/'.$exam_id);
        $data['pagination'] = multipage($total, $size, $page, $purl);

        $data['exam']        = &$exam;
        $data['paper_list']  = &$paper_list;

        // 模版
        $this->load->view('subject_paper/add', $data);
    }

    /**
     * @description 添加期次科目试卷
     * @author
     * @final
     * @param int $exam_id 考试期次id
     * @param array $ids 试卷id
     */
    public function insert()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = (int)$this->input->post('exam_id');
        $exam_id && $exam = ExamModel::get_exam($exam_id, 'exam_id,exam_pid,grade_id,class_id');

        if (empty($exam)) {
            message('考试期次科目不存在', 'admin/exam/index');
            return;
        }

        $ids = $this->input->post('ids');

        if (empty($ids) OR ! is_array($ids)) {
            message('请至少选择一项');
            return;
        }

        //检查该学科考试状态

        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);

        if ($has_tested) {
        	message('该期次科目已经被考生考过 或者 正在被考中,因此无法操作');
        }

        $ids = my_intval($ids);

        /* ------------------------------- */
        /* 补全外部新试卷信息 */

        foreach ($ids as $paper_id) {
            /* 查询试卷信息 */
            $paper = PaperModel::get_paper_by_id($paper_id, 'paper_id,admin_id,question_sort');
            /* 判定是否为外部试卷 */
            if ($paper['admin_id'] > 0) {
                $questions = json_decode($paper['question_sort'], true);
                $qtype_ques_num = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
                $question_difficulty = array();

                $this->db->trans_start();
                $sql = "DELETE FROM rd_exam_question WHERE paper_id={$paper_id} 
                        AND exam_id={$exam_id}";
                Fn::db()->query($sql);
              
                if (count($questions) > 0) {
                    foreach ($questions as $ques_id) {
                        /* 补全exam_question信息 */
                        
                        $data = array();
                        $data['paper_id'] = $paper_id;
                        $data['exam_id'] = $exam_id;
                        $data['ques_id'] = $ques_id;
                        $this->db->insert('exam_question', $data);

                        $sql = "select q.ques_id,q.type,rc.difficulty from
                            {pre}question q left join {pre}relate_class rc on q.ques_id=rc.ques_id
                            where q.ques_id={$ques_id} and rc.grade_id={$exam['grade_id']} and rc.class_id={$exam['class_id']}";
                        $question = $this->db->query($sql)->row_array();

                        if (empty($question)) {
                            
                            $this->db->trans_rollback();
                            message('当前试卷中存在不属于当前考试期次年级的试题！请检查试题！');
                            exit;
                        }
                        /* 各个类型试题数量 */
                        $qtype_ques_num[$question['type']]++;
                        /* 试题难易度 */
                        $question_difficulty[] = $question['difficulty'];
                    }
                }

                /* 补全exam_pager信息 */
                $data = array();
                $data['exam_id'] = $exam_id;
                $data['qtype_ques_num'] = implode(',', $qtype_ques_num);
                $data['difficulty'] = array_sum($question_difficulty)/count($question_difficulty);
                PaperModel::update_paper($paper_id, $data);

                $this->db->trans_complete();
            }
        }

        /* -------------------------------------- */
        $inserts = array();

        $sql = "SELECT p.paper_id,e.subject_id FROM {pre}exam_paper p,{pre}exam e
            WHERE p.exam_id=e.exam_id AND p.paper_id IN (".my_implode($ids).")
            AND p.is_delete=0 AND p.exam_id=$exam_id";

        $query = $this->db->query($sql);
        foreach ($query->result_array() as $row) {
            $inserts[] = array(
                'exam_pid' => $exam['exam_pid'],
            	'exam_id'  => $exam['exam_id'],
                'paper_id' => $row['paper_id'],
                'subject_id' => $row['subject_id']
            );
        }

        $res = 0;
        if ($inserts) {
            // 关闭错误信息，防止 unique index 冲突出错
            $this->db->db_debug = false;
            $this->db->insert_batch('exam_subject_paper', $inserts);
            $res = $this->db->affected_rows();
        }

        $back_url = 'admin/subject_paper/add/'.$exam_id;
        if ($res < 1)
            message('试卷添加失败', $back_url);
        else
            message('试卷添加成功', $back_url);
    }

    /**
     * 更新试卷信息
     *
     * @param int $exam_id 考试期次id
     * @param int $paper_id 试卷id
     * @return void
     */
    public function update_question_info($exam_id, $paper_id)
    {
        if (!$this->check_power('exam_manage')) exit;

        $exam_id = (int)$exam_id;
        $exam_id && $exam = ExamModel::get_exam($exam_id, 'exam_id,exam_pid,grade_id,class_id');

        if (empty($exam)) {
            message('考试期次科目不存在', 'admin/exam/index'); exit;
        }
        
        $is_mini_test = ExamModel::is_mini_test($exam['exam_pid']);

        //检查该学科考试状态
        $has_tested = false;
        if (!$is_mini_test)
        {
            $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);
        }
        
        if ($has_tested) 
        {
            message('该期次科目已经被考生考过 或者 正在被考中,因此无法操作');exit;
        }

        /* 更新外部新试卷信息 */
        $paper_id = (int)$paper_id;

        /* 查询试卷信息 */
        $paper = PaperModel::get_paper_by_id($paper_id, 'exam_id,paper_id,admin_id,question_sort');

        if (empty($paper) or $exam_id != $paper['exam_id']) 
        {
            message('试卷不在当前考试期次中！');exit;
        }

        /* 判定是否为外部试卷 */
        if ($paper['admin_id'] > 0) 
        {
            $rst = PaperModel::update_paper_question($paper_id);
            if (!$rst)
            {
                message('更新试卷失败');
            }
            else
            {
                message('更新试卷成功', 'javascript');
            }
        } else {
            message('当前试卷不属于手工组卷，取消操作！');
        }
    }

    /**
     * @description 删除期次科目试卷
     * @author
     * @final
     * @param int $exam_id 考试期次id
     * @param array $ids 试卷id
     */
    public function batch_delete()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $ids = $this->input->post('ids');
        $exam_id = (int)$this->input->post('exam_id');
        if (empty($ids) OR ! is_array($ids)) {
            message('请至少选择一项');
            return;
        }

        //检查该学科考试状态
        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);
        if ($has_tested) {
        	message('该期次科目已经被考生考过 或者 正在被考中,因此无法操作');
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url) && $exam_id) {
            $back_url = 'admin/subject_paper/'.$exam_id;
        }

        $id_string = implode( ',',array_filter( array_values($ids) ) ) ;
        $sql0 =  "  select group_concat( distinct paper_id) as paper_ids from {pre}exam_subject_paper where id in($id_string)";

        try {
                $this->db->trans_start();

                $paper_ids = $this->db->query($sql0)->row_array();
                $paper_ids = $paper_ids['paper_ids'];

                //查询学生已分配的试卷
                $sql = "select group_concat(uid) as uids,place_id from {pre}exam_test_paper where paper_id in( $paper_ids )  group by place_id";
                $res = $this->db->query($sql)->result_array();
                //插入计划任务
                foreach($res as $row) {
                    $insert_data = array( 'place_id'=>$row['place_id'], 'uid_data'=>json_encode(explode(',',$row['uids'])));
                    $this->cron_place_student_paper_model->insert($insert_data);
                }

                //删除学生试卷试题
                 $sql1 =  "select group_concat(distinct etp_id) etp_ids from {pre}exam_test_paper where   paper_id  in ( $paper_ids )";

                 $etp_ids = $this->db->query($sql1)->row_array();
                 $etp_ids = $paper_ids['etp_ids'];

                 $sql = "delete from {pre}exam_test_paper_question where etp_id in( $etp_ids ) ";
                 $this->db->query($sql );

                //删除学生试卷
                 $sql = "delete from {pre}exam_test_paper where paper_id in( $paper_ids) ";
                 $this->db->query($sql );


                 //更新试卷归属
                 $sql = "update  {pre}exam_paper set exam_id=0 where paper_id in( $paper_ids) and question_sort<>''";
                 $this->db->query($sql );

                 //恢复试卷难易度
                 $pp=explode(',',$paper_ids);
                 foreach($pp as $val) {
                     /* 获取试卷信息 */
                     $paper = PaperModel::get_paper_by_id($val);
                     $question_difficulty =array();
                     if($paper['paper_id']) {
                         $quetion_sort = json_decode($paper['question_sort'], true);
                         if(is_array($quetion_sort)) {
                             foreach ($quetion_sort as $ques_id) {
                                 $sql = "select q.ques_id,q.type,AVG(rc.difficulty) as difficulty from
                                 {pre}question q left join {pre}relate_class rc on q.ques_id=rc.ques_id
                                 where q.ques_id={$ques_id}  group by q.ques_id" ;
                                 $question = $this->db->query($sql)->row_array();
                                 /* 试题难易度 */
                                 $question_difficulty[] = $question['difficulty'];
                                 }
                                 $difficulty= array_sum($question_difficulty)/count($question_difficulty);
                                 if($difficulty) {
                                     $sql = "update  {pre}exam_paper set difficulty={$difficulty} where paper_id in( $val) ";
                                     $this->db->query($sql );
                                 }
                                 //删除试卷试题
                                // $sql = "delete from {pre}exam_question where paper_id in( $val)  ";
                                // $this->db->query($sql );


                         }

                     }

                 }
               //删除学科试卷
               $this->db->where_in('id', $ids)->delete('exam_subject_paper');

              $this->db->trans_complete();
             message('删除成功', $back_url);
        } catch(Exception $e) {
             $this->db->trans_complete();
            message('删除失败', $back_url);
        }
    }

    /**
     * @description 添加期次科目样卷
     * @author
     * @final
     * @param int $exam_id 考试期次id
     * @param array $ids 试卷id
     */
    public function add_demo($exam_id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = intval($exam_id);
        $exam_id && $exam = ExamModel::get_exam($exam_id, 'exam_name,exam_id,exam_pid,subject_id');
        if (empty($exam)) {
            message('考试期次科目不存在', 'admin/exam/index');
            return;
        }

        //样卷，跳到样卷选择
        if ($exam['exam_pid'] > 1) {
        	redirect('admin/subject_paper/add/'.$exam_id);
        }

        //检查该学科考试状态
        $has_tested = ExamPlaceSubjectModel::exam_subject_has_test_action($exam_id);
        if ($has_tested) {
			message('该期次科目已经被考生考过 或者 正在被考中,因此无法操作');
        }

        $subject_id = $exam['subject_id'];
        $subjects = CpUserModel::get_allowed_subjects();
        $exam['subject_name'] = $subjects[$subject_id];
        $demo_subject_papers = C('demo_subject_paper', 'app/admin/exam/demo');


        $paper_list = array();
        $size   = 30;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        $total = 0;
        if (isset($demo_subject_papers[$subject_id]) && count($demo_subject_papers[$subject_id])) {
        	$total = count($demo_subject_papers[$subject_id]);
            $sql = "SELECT * FROM {pre}exam_paper p WHERE p.exam_id={$exam_id} AND p.is_delete=0 AND p.paper_id in(".implode(',', $demo_subject_papers[$subject_id]).") LIMIT $offset,$size";
            $query = $this->db->query($sql);
            foreach ($query->result_array() as $row) {
                $row['qtype_ques_num'] = explode(',', $row['qtype_ques_num']);
                $paper_list[] = $row;
            }
        }

        $data['exam']        = &$exam;
        $data['paper_list']  = &$paper_list;

        // 分页
        $purl = site_url('admin/subject_paper/add_demo/'.$exam_id);
        $data['pagination'] = multipage($total, $size, $page, $purl);

        // 模版
        $this->load->view('subject_paper/add', $data);
    }
}
