<?php if ( ! defined('BASEPATH')) exit();
class Exam_question extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 试卷试题列表已加入期次
     *
     * @param int $paper_id 试卷id
     * @param int $qtype 题型
     * @param int $method_tactic_id 方法策略
     * @param int $kid 知识点
     * @return void
     */
    public function index($paper_id=0)
    {
    	if ( ! $this->check_power('exam_list,exam_manage,paper_diy')) return;

        $paper_id = intval($paper_id);

        $paper = ExamPaperModel::detail($paper_id, 1, 1);
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        /*
         * 考试期次
         */
        $exam = ExamModel::get_exam($paper['exam_id'], 'exam_pid,grade_id,class_id');

        $search = $where = $param = array();
        $where[] = "eq.paper_id=$paper_id";
        $qtypes = C('qtype');
        $qtype = $this->input->get('qtype');
        if (strlen($qtype))
        {
            $qtype = abs(intval($qtype));
            $where['qtype'] = "q.type=$qtype";
            $param[] = "qtype=$qtype";
        }
        $search['qtype'] = &$qtype;

        /** is_original */
        $is_original = $this->input->get('is_original');
        if (in_array($is_original, array(1, 2)))
        {
            $where['is_original'] = "q.is_original=$is_original";
            $param[] = "is_original=$is_original";
        }
        $search['is_original'] = &$is_original;
        //方法逻辑
        $method_tactic_id = intval($this->input->get('method_tactic_id'));
        if ($method_tactic_id)
        {

            $where['method_tactic_id'] =  "eq.ques_id IN (SELECT DISTINCT eq.ques_id FROM {pre}exam_question eq, {pre}relate_method_tactic rmt WHERE eq.ques_id=rmt.ques_id AND eq.paper_id=$paper_id AND rmt.method_tactic_id=$method_tactic_id)";
            $param[] = "method_tactic_id=$method_tactic_id";
        }
        $search['method_tactic_id'] = &$method_tactic_id;

        //知识点
        if ($kid = intval($this->input->get('kid')))
        {
            $pkid = KnowledgeModel::get_knowledge($kid, 'pid');
            if ($pkid !== false)
            {
                if ($pkid == 0)
                {
                    // 一级知识点
                    $k_children = KnowledgeModel::get_knowledge_list(0, $kid, 0);

                        $where['knowledge'] =  "eq.ques_id IN (
                        SELECT DISTINCT eq.ques_id FROM {pre}exam_question eq
                        LEFT JOIN {pre}relate_knowledge rk ON rk.ques_id = eq.ques_id
                        WHERE eq.paper_id=$paper_id AND rk.knowledge_id IN (".my_implode(array_keys($k_children)).")
                    	AND rk.is_child=0)";
                }
                else
                {
                    // 二级知识点
                        $where['knowledge'] =  "eq.ques_id IN (
                        SELECT DISTINCT eq.ques_id FROM {pre}exam_question eq
                        LEFT JOIN {pre}relate_knowledge rk ON rk.ques_id = eq.ques_id
                        WHERE eq.paper_id=$paper_id AND rk.knowledge_id=$kid AND rk.is_child=0)";
                }
                $param[] = "kid=$kid";
            }
        }
        $search['kid'] = &$kid;

        /* 信息提取方式 */
        if ($gid = intval($this->input->get('group_type_id'))) {
            $pgid = GroupTypeModel::get_group_type($gid, 'pid');
            if ($pgid !== false)
            {
                if ($pgid == 0)
                {
                        $g_children = GroupTypeModel::get_group_type_list($gid, 0);
                        $where['group_type'] =  "eq.ques_id IN (
                        SELECT DISTINCT eq.ques_id FROM {pre}exam_question eq
                        LEFT JOIN {pre}relate_group_type rk ON rk.ques_id = eq.ques_id
                        WHERE eq.paper_id=$paper_id AND rk.group_type_id IN (".my_implode(array_keys($g_children)).")
                        AND rk.is_child=0)";
                }
                else
                {
                         $where['group_type'] =  "eq.ques_id IN (
                         SELECT DISTINCT eq.ques_id FROM {pre}exam_question eq
                         LEFT JOIN {pre}relate_group_type rk ON rk.ques_id = eq.ques_id
                         WHERE eq.paper_id={$paper_id} AND rk.group_type_id={$gid} AND rk.is_child=0)";
                }
                $param[] = "group_type_id=$gid";
            }
        }

        $search['group_type_id'] = &$gid;



            if (isset($where['qtype']) || isset($where['is_original']))
            {
                $sql = "SELECT COUNT(*) nums FROM {pre}exam_question eq, {pre}question q
                         WHERE eq.ques_id=q.ques_id AND ".implode(' AND ', $where);
            }
            else
            {
                $sql = "SELECT COUNT(*) nums FROM {pre}exam_question eq WHERE ".implode(' AND ', $where);
            }

            $query = $this->db->query($sql);
            $row = $query->row_array();


            $total = $row['nums'];


        $size   = 150;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {

                $sql = "SELECT eq.id,q.ques_id,q.title,q.type,q.group_id,q.addtime,rc.difficulty
                FROM {pre}exam_question eq
                LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
                LEFT JOIN {pre}relate_class rc ON rc.ques_id=eq.ques_id AND rc.grade_id=$exam[grade_id] AND rc.class_id=$exam[class_id]
                WHERE ".implode(' AND ', $where)."
                LIMIT $offset,$size";


            $res = $this->db->query($sql);

            foreach ($res->result_array() as $row)
            {
                $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['has_edit_power'] = QuestionModel::check_question_power($row['ques_id'], 'w', false);
                $row['difficulty'] =round($row['difficulty'],2);
                $list[] = $row;
            }
        }

        // 分页
        $purl = site_url('admin/exam_question/index/'.$paper_id.'/') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        // 按题型分组统计数量
        $qtype_nums = array(0,0,0,0);
        foreach (explode(',',$paper['qtype_ques_num']) as $k => $v)
        {
            $qtype_nums[$k] = $v;
        }
        
        

        //判断该试题已经被考试过 或 正在被考,MINI测放开改权限
        $paper['be_tested'] = false;
        if (!ExamModel::is_mini_test($exam['exam_pid']))
        {
            $paper['be_tested'] = QuestionModel::paper_has_test_action($paper_id);
        }

        $data['search']   = $search;
        $data['qtypes']  = $qtypes;
        $data['paper']   = $paper;
        $data['list']    = $list;
        $data['qtype_nums'] = $qtype_nums;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

        /* 是否有权限查看试卷列表 */
        $is_exam_paper_list = $this->check_power('exam_list', false);
        $data['is_exam_paper_list'] = $is_exam_paper_list;

        // 模版
        $this->load->view('exam_question/index', $data);
    }




    /**
     * 试卷试题列表未加入期次
     *
     * @param int $paper_id 试卷id
     * @param int $qtype 题型
     * @param int $method_tactic_id 方法策略
     * @param int $kid 知识点
     * @return void
     */

    public function index2($paper_id=0)
    {
        if ( ! $this->check_power('exam_list,exam_manage,paper_diy')) return;

        $paper_id = intval($paper_id);
        $paper = ExamPaperModel::get_paper ( $paper_id );
        if(!empty($paper['question_sort']))
        {
            $paper = ExamPaperModel::detail_sg($paper_id, 1, 1);
        }

        $paper['ques_ids'] = json_decode($paper['question_sort'], true);
        $ques_ids_str = implode(',', $paper['ques_ids']);
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        /*
         * 考试期次
        */
        $exam = ExamModel::get_exam($paper['exam_id'], 'exam_pid,grade_id,class_id');

        $search = $where = $param = array();
        if(!empty($paper['question_sort']))
        {
            $where[] = "1=1";
        }

        $qtypes = C('qtype');
        $qtype = $this->input->get('qtype');
        if (strlen($qtype))
        {
            $qtype = abs(intval($qtype));
            $where['qtype'] = "q.type=$qtype";
            $param[] = "qtype=$qtype";
        }
        $search['qtype'] = &$qtype;

        /** is_original */
        $is_original = $this->input->get('is_original');
        if (in_array($is_original, array(1, 2)))
        {
            $where['is_original'] = "q.is_original=$is_original";
            $param[] = "is_original=$is_original";
        }
        $search['is_original'] = &$is_original;

        //方法逻辑
        $method_tactic_id = intval($this->input->get('method_tactic_id'));
        if ($method_tactic_id)
        {
                $where['method_tactic_id'] =  "q.ques_id IN (SELECT
                DISTINCT rmt.ques_id  FROM {pre}relate_method_tactic rmt
                WHERE rmt.ques_id in($ques_ids_str)
                AND rmt.method_tactic_id=$method_tactic_id)";
                $param[] = "method_tactic_id=$method_tactic_id";
        }
        $search['method_tactic_id'] = &$method_tactic_id;

        //知识点
        if ($kid = intval($this->input->get('kid')))
        {
            $pkid = KnowledgeModel::get_knowledge($kid, 'pid');
            if ($pkid !== false)
            {
                if ($pkid == 0)
                {
                    // 一级知识点
                    $k_children = KnowledgeModel::get_knowledge_list(0, $kid, 0);
                        $where['knowledge'] =  "q.ques_id IN (
                        SELECT DISTINCT rk.ques_id FROM  {pre}relate_knowledge rk
                        WHERE rk.ques_id in($ques_ids_str) AND rk.knowledge_id
                        IN (".my_implode(array_keys($k_children)).")
                    	AND rk.is_child=0)";
                }
                else
               {
                    // 二级知识点
                    if(!empty($paper['question_sort']))
                    {
                        $where['knowledge'] =  "q.ques_id IN
                        (SELECT DISTINCT rk.ques_id FROM  {pre}relate_knowledge
                        rk WHERE  rk.ques_id in($ques_ids_str) AND
                        rk.knowledge_id=$kid AND rk.is_child=0)";
                    }

               }
               $param[] = "kid=$kid";
          }
       }
       $search['kid'] = &$kid;

        /* 信息提取方式 */
       if ($gid = intval($this->input->get('group_type_id')))
       {
           $pgid = GroupTypeModel::get_group_type($gid, 'pid');
           if ($pgid !== false)
           {
              if ($pgid == 0)
              {
                  $g_children = GroupTypeModel::get_group_type_list($gid, 0);
                  if(!empty($paper['question_sort']))
                  {
                       $where['group_type'] =  "q.ques_id IN ( SELECT DISTINCT
                       rk.ques_id FROM  {pre}relate_group_type rk WHERE
                       rk.ques_id in($ques_ids_str) AND rk.group_type_id
                       IN (".my_implode(array_keys($g_children)).") AND rk.is_child=0)";
                  }
               }
               else
               {

                   if(!empty($paper['question_sort']))
                   {
                        $where['group_type'] =  "q.ques_id IN (
                                            SELECT DISTINCT rk.ques_id FROM  {pre}relate_group_type rk
                                            WHERE rk.ques_id in($ques_ids_str)
                                            AND rk.group_type_id={$gid} AND rk.is_child=0)";
                   }

               }

               $param[] = "group_type_id=$gid";
           }
      }
      $search['group_type_id'] = &$gid;
     if(!empty($paper['question_sort']))
     {
         $total = count($paper['question_sort']);
     }


     $size   = 150;
     $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
     $offset = ($page - 1) * $size;
     $list = array();
     if ($total)
     {

        if($paper['exam_id'])
        {
            $sql = "SELECT q.ques_id,q.title,q.type,q.group_id,q.addtime,
            (SELECT rc.difficulty FROM {pre}relate_class rc where rc.ques_id=q.ques_id and rc.class_id=$exam[class_id] and rc.grade_id=$exam[grade_id]) AS difficulty
            FROM {pre}question q WHERE q.ques_id in($ques_ids_str)
            AND ".implode(' AND ', $where)." LIMIT $offset,$size";
        }
        else
        {
            $sql = "SELECT q.ques_id,q.title,q.type,q.group_id,q.addtime,
            (SELECT AVG(rc.difficulty) FROM {pre}relate_class rc where rc.ques_id=q.ques_id ) AS difficulty
            FROM {pre}question q WHERE q.ques_id in($ques_ids_str)
            AND ".implode(' AND ', $where)." LIMIT $offset,$size";
        }

         $res = $this->db->query($sql);
         foreach ($res->result_array() as $row)
         {
             $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
             $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
             $row['has_edit_power'] = QuestionModel::check_question_power($row['ques_id'], 'w', false);
             $row['difficulty'] =round($row['difficulty'],2);
             $list[] = $row;
         }
    }

     // 分页
     $purl = site_url('admin/exam_question/index2/'.$paper_id.'/') . ($param ? '?'.implode('&',$param) : '');
     $data['pagination'] = multipage($total, $size, $page, $purl);
     // 按题型分组统计数量
     $qtype_nums = array(0,0,0,0);
     foreach (explode(',',$paper['qtype_ques_num']) as $k => $v)
     {
        $qtype_nums[$k] = $v;
     }

            //判断该试题已经被考试过 或 正在被考
            $paper['be_tested'] = false;
            if (!ExamModel::is_mini_test($exam['exam_pid']))
            {
                $paper['be_tested'] = QuestionModel::paper_has_test_action($paper_id);
            }

            $data['search']   = $search;
            $data['qtypes']  = $qtypes;
            $data['paper']   = $paper;
            $data['list']    = $list;
            $data['qtype_nums'] = $qtype_nums;
            $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

            /* 是否有权限查看试卷列表 */
            $is_exam_paper_list = $this->check_power('exam_list', false);
            $data['is_exam_paper_list'] = $is_exam_paper_list;

            // 模版
            $this->load->view('exam_question/index2', $data);
    }




    /**
     * 试卷试题列表未加入期次
     *
     * @param int $paper_id 试卷id
     * @param int $qtype 题型
     * @param int $method_tactic_id 方法策略
     * @param int $kid 知识点
     * @return void
     */

    public function index3($paper_id=0)
    {
        if ( ! $this->check_power('exam_list,exam_manage,paper_diy')) return;

        $paper_id = intval($paper_id);
        $paper = ExamPaperModel::get_paper ( $paper_id );
        if(!empty($paper['question_sort']))
        {
            $paper = ExamPaperModel::detail_sg($paper_id, 1, 1);
        }

        $paper['ques_ids'] = json_decode($paper['question_sort'], true);
        $ques_ids_str = implode(',', $paper['ques_ids']);
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        /*
         * 考试期次
        */
        $exam = ExamModel::get_exam($paper['exam_id'], 'exam_pid,grade_id,class_id');

        $search = $where = $param = array();
        if(!empty($paper['question_sort']))
        {
            $where[] = "1=1";
        }

        $qtypes = C('qtype');
        $qtype = $this->input->get('qtype');
        if (strlen($qtype))
        {
            $qtype = abs(intval($qtype));
            $where['qtype'] = "q.type=$qtype";
            $param[] = "qtype=$qtype";
        }
        $search['qtype'] = &$qtype;

        /** is_original */
        $is_original = $this->input->get('is_original');
        if (in_array($is_original, array(1, 2)))
        {
            $where['is_original'] = "q.is_original=$is_original";
            $param[] = "is_original=$is_original";
        }
        $search['is_original'] = &$is_original;

        //方法逻辑
        $method_tactic_id = intval($this->input->get('method_tactic_id'));
        if ($method_tactic_id)
        {
            $where['method_tactic_id'] =  "q.ques_id IN (SELECT
            DISTINCT rmt.ques_id  FROM {pre}relate_method_tactic rmt
            WHERE rmt.ques_id in($ques_ids_str)
            AND rmt.method_tactic_id=$method_tactic_id)";
            $param[] = "method_tactic_id=$method_tactic_id";
        }
        $search['method_tactic_id'] = &$method_tactic_id;

        //知识点
        if ($kid = intval($this->input->get('kid')))
        {
            $pkid = KnowledgeModel::get_knowledge($kid, 'pid');
            if ($pkid !== false)
            {

                if ($pkid == 0)
                {
                // 一级知识点
                $k_children = KnowledgeModel::get_knowledge_list(0, $kid, 0);
                $where['knowledge'] =  "q.ques_id IN (
                    SELECT DISTINCT rk.ques_id FROM  {pre}relate_knowledge rk
                    WHERE rk.ques_id in($ques_ids_str) AND rk.knowledge_id
                    IN (".my_implode(array_keys($k_children)).") AND rk.is_child=0)";
                }
                else
                {
                   // 二级知识点
                    if(!empty($paper['question_sort']))
                     {
                          $where['knowledge'] =  "q.ques_id IN (SELECT DISTINCT rk.ques_id FROM  {pre}relate_knowledge
                            rk WHERE  rk.ques_id in($ques_ids_str) AND rk.knowledge_id=$kid AND rk.is_child=0)";
                     }

                 }
                 $param[] = "kid=$kid";
              }
          }
          $search['kid'] = &$kid;

                /* 信息提取方式 */
          if ($gid = intval($this->input->get('group_type_id')))
           {
                $pgid = GroupTypeModel::get_group_type($gid, 'pid');
                if ($pgid !== false)
                {
                        if ($pgid == 0)
                        {
                            $g_children = GroupTypeModel::get_group_type_list($gid, 0);
                            if(!empty($paper['question_sort']))
                            {
                                $where['group_type'] =  "q.ques_id IN ( SELECT DISTINCT
                                        rk.ques_id FROM  {pre}relate_group_type rk WHERE
                                        rk.ques_id in($ques_ids_str) AND rk.group_type_id
                                        IN (".my_implode(array_keys($g_children)).") AND rk.is_child=0)";
                             }
                        }
                        else
                        {

                           if(!empty($paper['question_sort']))
                           {
                                $where['group_type'] =  "q.ques_id IN (
                                        SELECT DISTINCT rk.ques_id FROM  {pre}relate_group_type rk
                                        WHERE rk.ques_id in($ques_ids_str)
                                        AND rk.group_type_id={$gid} AND rk.is_child=0)";
                            }
                          }

                          $param[] = "group_type_id=$gid";
                   }
                 }
                 $search['group_type_id'] = &$gid;
                 if(!empty($paper['question_sort']))
                 {
                    $total = count($paper['question_sort']);
                 }
                  $size   = 150;
                  $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
                  $offset = ($page - 1) * $size;
                  $list = array();
                  if ($total)
                  {
                            $sql = "SELECT q.ques_id,q.title,q.type,q.group_id,q.addtime,
                            (SELECT AVG(rc.difficulty) FROM {pre}relate_class rc where rc.ques_id=q.ques_id) AS difficulty
                             FROM {pre}question q WHERE q.ques_id in($ques_ids_str)
                            AND ".implode(' AND ', $where)." LIMIT $offset,$size";
                            $res = $this->db->query($sql);
                            foreach ($res->result_array() as $row)
                            {
                                $row['qtype'] = isset($qtypes[$row['type']]) ? $qtypes[$row['type']] : '';
                                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                                $row['has_edit_power'] = QuestionModel::check_question_power($row['ques_id'], 'w', false);
                                $row['difficulty'] =round($row['difficulty'],2);
                                $list[] = $row;
                             }
                   }

     // 分页
                 $purl = site_url('admin/exam_question/index3/'.$paper_id.'/') . ($param ? '?'.implode('&',$param) : '');
                 $data['pagination'] = multipage($total, $size, $page, $purl);
                 // 按题型分组统计数量
                 $qtype_nums = array(0,0,0,0);
                 foreach (explode(',',$paper['qtype_ques_num']) as $k => $v)
                 {
                     $qtype_nums[$k] = $v;
                 }

                //判断该试题已经被考试过 或 正在被考
                $paper['be_tested'] = false;
                if (!ExamModel::is_mini_test($exam['exam_pid']))
                {
                    $paper['be_tested'] = QuestionModel::paper_has_test_action($paper_id);
                }
                 
                 $data['search']   = $search;
                 $data['qtypes']  = $qtypes;
                 $data['paper']   = $paper;
                 $data['list']    = $list;
                 $data['qtype_nums'] = $qtype_nums;
                 $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

                /* 是否有权限查看试卷列表 */
                $is_exam_paper_list = $this->check_power('exam_list', false);
                $data['is_exam_paper_list'] = $is_exam_paper_list;

                // 模版
                $this->load->view('exam_question/index3', $data);
        }




    /**
     * 试卷加入试题
     *
     * @param int $paper_id 试卷id
     * @return void
     */
    public function add($paper_id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $paper_id = intval($paper_id);
        $paper = ExamPaperModel::get_paper($paper_id);

        $data['paper'] = $paper;
        $data['exam'] = $this->db->select('subject_id')->get_where('exam', array('exam_id' => $paper['exam_id']))->row_array();

        $this->check_total_score($paper_id);

        // 模版
        $this->load->view('exam_question/add', $data);
    }

    /**
     * 试卷加入试题
     *
     * @param int $paper_id 试卷id
     * @param int $ques_ids 试题id
     * @return void
     */
    public function insert()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $paper_id = intval($this->input->post('paper_id'));
        $ques_ids = trim($this->input->post('ques_ids'));
        if (empty($ques_ids))
        {
            message('请选择要添加的试题');return;
        }

        $paper = ExamPaperModel::detail($paper_id);
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }
        $exam = ExamModel::get_exam($paper['exam_id'], 'exam_pid,subject_id,grade_id,class_id');
        if (empty($exam)) {
            message('考试不存在');return;
        }

        //判断该试卷已经被考试过 或 正在被考
        $is_mini_test = ExamModel::is_mini_test($exam['exam_pid']);
        if (!$is_mini_test)
        {
            $be_tested = QuestionModel::paper_has_test_action($paper_id);
            if ($be_tested) 
            {
                message('该试卷已经被考生考过 或者 正在被考， 无法修改');
            }
        }

        $ques_ids = my_intval(explode(',', $ques_ids));

        $this->check_total_score($paper_id, $ques_ids);

        $sql = "SELECT q.ques_id,q.group_id FROM {pre}question q,{pre}relate_class rc
                WHERE q.ques_id=rc.ques_id AND q.subject_id=$exam[subject_id] AND rc.grade_id=$exam[grade_id]
                AND rc.class_id=$exam[class_id] AND q.ques_id IN (".my_implode($ques_ids).")";
        $query = $this->db->query($sql);
        $inserts = array();
        $fail = $success =0;
        $msg = array();
        foreach ($query->result_array() as $row)
        {
            if (in_array($row['ques_id'], $paper['ques_ids'])
                OR $row['group_id'] && in_array($row['group_id'], $paper['group_ids']))
            {
                $fail++;
                $msg[]='该试卷同一试题已存在或者同一分组中的试题已存在！';
                continue;
            }
            $success++;
            $item = array(
                'paper_id' => $paper['paper_id'],
                'exam_id' => $paper['exam_id'],
                'ques_id' => $row['ques_id']
            );
            $inserts[] = $item;
            $paper['ques_ids'][] = $row['ques_id'];
            if ($row['group_id'])
                $paper['group_ids'][] = $row['group_id'];
        }
        
        if ($inserts)
        {
            $this->db->insert_batch('exam_question', $inserts);
            ExamPaperModel::renew($paper_id);
            
            if ($is_mini_test)
            {
                SummaryModel::summary_paper($exam['exam_pid'], 0, $paper_id, true);
            }
        }
        $msg = '成功'.$success.'个,失败'.$fail.'个失败原因:<br>'.implode('<br>',$msg);
        message('试卷试题添加成功'.$msg, 'admin/exam_question/index/'.$paper_id);
    }

    /**
     * 删除试卷中的试题
     *
     * @param int $id 试卷中的试题id
     * @return void
     */
    public function delete($id = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        if($id = intval($id))
        {
            $query = $this->db->get_where('exam_question', array('id'=>$id));
            $item = $query->row_array();
        }
        if (empty($item))
        {
            message('试题不存在');
            return;
        }
        
        $paper = PaperModel::get_paper_by_id($item['paper_id']);
        if (!$paper)
        {
            message('试卷不存在');
            return;
        }
        
        $exam_pid = ExamModel::get_exam($paper['exam_id'], 'exam_pid');

        //判断该试题已经被考试过 或 正在被考
        $is_mini_test = ExamModel::is_mini_test($exam_pid);
        if (!$is_mini_test)
        {
            $be_tested = QuestionModel::paper_question_has_been_tested($item['paper_id'], $item['ques_id']);
            if ($be_tested) 
            {
                message('该试题已经被考生考过 或者 正在被考， 无法删除');
            }
        }

        $res = $this->db->delete('exam_question', array('id'=>$id));
        if ($res)
        {
            ExamPaperModel::renew($item['paper_id']);
            
            if ($is_mini_test)
            {
                SummaryModel::summary_paper($exam_pid, 0, $paper['paper_id'], true);
            }
            
            message('题目删除成功', 'admin/exam_question/index/'.$item['paper_id']);
        }
        else
        {
            message('题目删除失败', 'admin/exam_question/index/'.$item['paper_id']);
        }
    }

    /**
     * 批量删除试卷中的试题
     *
     * @param int $paper_id 试卷id
     * @param int $ids 试卷中的试题id
     * @return void
     */
    public function batch_delete()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $paper_id = (int)$this->input->post('paper_id');
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }
        
        $paper = PaperModel::get_paper_by_id($paper_id);
        if (!$paper)
        {
            message('试卷不存在');
            return;
        }
        
        $exam_pid = ExamModel::get_exam($paper['exam_id'], 'exam_pid');
        $is_mini_test = ExamModel::is_mini_test($exam_pid);

        //判断该试卷已经被考试过 或 正在被考
        $count_fail = 0;
        $count_success = 0;
        $tmp_ids = array();
        foreach ($ids as $ques_id)
        {
            if (!$is_mini_test)
            {
                $be_tested = QuestionModel::paper_question_has_been_tested($paper_id, $ques_id);
                if ($be_tested) 
                {
                    $count_fail++;
                    continue;
                }
            }
	        
	        $tmp_ids[] = $ques_id;
        	$count_success++;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/exam_question/index/'.$paper_id;

        $this->db->where_in('id', $tmp_ids)->delete('exam_question');
        ExamPaperModel::renew($paper_id);
        if ($is_mini_test && $count_success)
        {
            SummaryModel::summary_paper($exam_pid, 0, $paper_id, true);
        }

        message("批量操作完成(成功 {$count_success}个，失败 {$count_fail} 个)", $back_url);
    }

    /**
     * 添加试卷试题--搜索试题
     *
     * @param int $paper_id 试卷id
     * @param int $qtype 题型
     * @param int $start_difficulty 难易度
     * @param int $end_difficulty 难易度
     * @param int $keyword 关键字
     * @param int $que_id 试题ID
     * @param int $page 分页
     * @return void
     */
    public function search()
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;

        $result = array('error'=>'', 'list' => array());

        $paper_id         = intval($this->input->post('paper_id'));
        $qtype            = intval($this->input->post('qtype'));
        $start_difficulty = intval($this->input->post('start_difficulty'));
        $end_difficulty   = intval($this->input->post('end_difficulty'));
        $keyword          = trim($this->input->post('keyword'));
        $que_id           = intval($this->input->post('que_id'));
        $page             = intval($this->input->post('page'));
        $is_original      = intval($this->input->post('is_original'));
        $page             = $page<1 ? 1 : $page;
        $size = 15;
        $offset = ($page-1) * $size;

        $result['next_page'] = $page+1;
        $result['prev_page'] = $page>1 ? $page-1 : '';

        $query = $this->db->select('exam_id')->get_where('exam_paper', array('paper_id'=>$paper_id), 1);
        $paper = $query->row_array();
        if (empty($paper))
        {
            $result['error'] = '试卷不存在';
            die(json_encode($result));
        }
        $exam = ExamModel::get_exam($paper['exam_id'], 'subject_id,grade_id,class_id');
        if (empty($exam)) {
            $result['error'] = '考试不存在';
            die(json_encode($result));
        }

        //根据试题ID搜索时，其他附带条件都作废
        if ($que_id > 0) {
        	$where[] = "q.ques_id = {$que_id}";
        } else {
	        $where[] = "q.is_delete<>1 AND q.parent_id=0";
	        $where[] = "q.type=$qtype";
	        $where[] = "q.subject_id=$exam[subject_id]";
            if ($is_original > 0)
            {
                $where[] = "q.is_original = {$is_original}";
            }
	        if ($start_difficulty && $end_difficulty) {
	            $where[] = "rc.difficulty BETWEEN $start_difficulty AND $end_difficulty";
	        } elseif ($start_difficulty) {
	            $where[] = "rc.difficulty >= $start_difficulty";
	        } elseif ($end_difficulty) {
	            $where[] = "rc.difficulty <= $end_difficulty";
	        }
	        // 排除原试题
	        $where[] = "q.ques_id NOT IN (SELECT ques_id FROM {pre}exam_question WHERE paper_id=$paper_id)";

	        // 排除原分组
	        $where[] = "q.group_id NOT IN (SELECT group_id FROM {pre}exam_question eq,{pre}question q WHERE eq.ques_id=q.ques_id AND q.group_id>0)";

	        //搜索关键字
	        if ($keyword != '') {
		        $where[] = "(q.title like '%{$keyword}%')";
	        }
        }

        $sql = "SELECT q.ques_id,q.group_id,q.title,rc.difficulty FROM {pre}question q
                 RIGHT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id=$exam[grade_id] AND rc.class_id=$exam[class_id]
                WHERE ".implode(' AND ', $where)." ORDER BY q.ques_id DESC LIMIT $offset, $size";

        $query = $this->db->query($sql);
        foreach ($query->result_array() as $row)
        {
        	$title = mb_substr($row['title'], 0, 300);
        	if ($keyword != '') {
	        	$title = str_ireplace($keyword, "<font color='red'>{$keyword}</font>", $title);
        	}

            $row['title'] = $title;
            $result['list'][] = $row;
        }
        die(json_encode($result));
    }

    /**
     * 判断试卷试题是否已超过总分
     */
    private function check_total_score($paper_id, $ques_ids = array())
    {
        $count = $this->db->select("q.type, count(q.ques_id) as count")->from('question q')
                ->join('exam_question eq', "q.ques_id = eq.ques_id")
                ->where('eq.paper_id', $paper_id)
                ->group_by('q.type')->get()->result_array();

        $exam = $this->db->select("e.total_score, e.qtype_score")->from('exam_paper ep')
                ->join('exam e', "e.exam_id = ep.exam_id")
                ->where('ep.paper_id', $paper_id)->get()->row_array();
        if (!$exam)
        {
            message('考试信息不存在');
        }

        $total_score = $exam['total_score'];
        $qtype_score = explode(",", $exam['qtype_score']);
        $has_group = 0;

        foreach ($count as $item)
        {
            if ($item['type'] == 0)
            {
                $has_group += $item['count'];
                continue;
            }

            $total_score -= $qtype_score[$item['type'] - 1] * $item['count'];
        }

        if (($has_group && $total_score <= $has_group)
            || (!$has_group && $total_score <= 0))
        {
            message("该试卷不能再添加试题了！");
        }

        if ($ques_ids)
        {
            $in_has_group = 0;
            $count = $this->db->select("type, count(ques_id) as count")
                    ->from('question')->where_in('ques_id', $ques_ids)
                    ->group_by('type')->get()->result_array();
            foreach ($count as $item)
            {
                if ($item['type'] == 0)
                {
                    $in_has_group += $item['count'];
                    continue;
                }

                $total_score -= $qtype_score[$item['type'] - 1] * $item['count'];
            }

            if ($has_group)
            {
                if ($total_score < $has_group + $in_has_group)
                {
                    message("试卷加入试题之后的总分已超过试卷总分！");
                }
            }
            else
            {
                if (($in_has_group && $total_score < $in_has_group)
                        || (!$in_has_group && $total_score < 0))
                {
                    message("试卷加入试题之后的总分已超过试卷总分！");
                }
            }
        }
    }
}
