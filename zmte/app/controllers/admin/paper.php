<?php if ( ! defined('BASEPATH')) exit();
class Paper extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    //判断当前管理员是否为 超级管理员
    function is_super_user()
    {
        return $this->session->userdata('is_super');
    }

    /**
     * @description 试卷列表
     * @author
     * @final
     * @param int $exam_id 考试期次id
     * @param string $mode 是否回收站
     * @param int $paper_id 试卷id
     */
    public function index($exam_id=0, $mode='')
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;


        $where = $param = array();

        if ($exam_id = intval($exam_id))
        {
            $query = $this->db->select('e.*,s.subject_name')->from('exam e')->join('subject s','e.subject_id=s.subject_id')->where(array('e.exam_id'=>$exam_id))->get();
            $exam = $query->row_array();
        }

        if (empty($exam))
        {
            message('考试期次不存在', 'admin/paper_diy/index');
            return;
        }

        $where[] = "p.exam_id=$exam_id";

        // 模式
        $mode = $mode=='trash' ? 'trash' : '';
        if ($mode == 'trash')
            $where[] = "p.is_delete=1";
        else
            $where[] = "p.is_delete=0";

        $paper_id = ($this->input->get('paper_id'));
        if (intval($paper_id)) {
        	$where[] = "p.paper_id={$paper_id}";
        }
        /*
        if (!$this->is_super_user()&&$this->check_power('paper_diy'))
        {
            $admin_info = $this->session->all_userdata();
            $where[] = "p.admin_id={$admin_info['admin_id']}";
        }
        */

        //$param['admin_id'] = $admin_info['admin_id'];

        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_paper p".$where;
        $query = $this->db->query($sql);
        $row = $query->row_array();
        $total = $row['nums'];







        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $sql = "SELECT p.* FROM {pre}exam_paper p
                     $where ORDER BY p.paper_id DESC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                $row['qtype_ques_num'] = explode(',', $row['qtype_ques_num']);
                $row['uptime'] = date('Y-m-d H:i', $row['uptime']);
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

                $row['be_tested'] = QuestionModel::paper_has_test_action($row['paper_id']);

                $list[] = $row;
            }
        }


        // 分页
        $purl = site_url('admin/paper/index/'.$exam_id.'/'.$mode);
		$data['pagination'] = multipage($total, $size, $page, $purl);

        $data['paper_id'] = $paper_id;
        $data['exam_id'] = $exam_id;
        $data['mode']    = $mode;
        $data['exam']    = $exam;
        $data['list']    = $list;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);


        // 模版
        $this->load->view('paper/index', $data);
    }

    /**
     * @description 编辑试卷
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function edit($id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);

        $query = $this->db->get_where('exam_paper', array('paper_id'=>$id), 1);
        $paper = $query->row_array();
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$paper['exam_id'];

        $data['paper'] = $paper;
        $data['back_url'] = $back_url;

        // 模版
        $this->load->view('paper/edit', $data);
    }

    public function update_s($paper_id)
    {
        if ( ! $this->check_power('exam_manage')) return;

         $paper_scores = $this->input->post();
         //var_dump($parent,$child);die;
         //查询试卷信息
         $id = intval($paper_id);
         $query = $this->db->get_where('exam_paper', array('paper_id'=>$id), 1);
         $old = $query->row_array();
         if (empty($old))
         {
             message('试卷不存在');
             return;
         }
         //查询期次信息
         $query = $this->db->get_where('exam', array('exam_id'=>$old['exam_id']), 1);
         $exam = $query->row_array();
         if (empty($exam))
         {
             message('期次不存在');
             return;
         }


         // 分数计算
         $score = array();
         // 题目数量
         $question_num = explode(',', $old['qtype_ques_num']);
         // 题型分数
         $qtype_score = explode(',', $exam['qtype_score']);
         // 总分 排除题组
         $total_score = 0;

         foreach ($qtype_score as $key => $value)
         {
             $score[$key+1]['score'] = round($value,2);
             $score[$key+1]['num'] = isset($question_num[$key+1]) ? $question_num[$key+1] : 0;
             $score[$key+1]['total_score'] = $score[$key+1]['score'] * $score[$key+1]['num'];
             $total_score += $score[$key+1]['score'] * $score[$key+1]['num'];
         }

         // 题组总分
         $total_0 = $exam['total_score'] - $total_score;




         $parent = array();
         $child = array();
         $type_score = array();
         foreach ($paper_scores['parent'] as $k => $v)
         {
           $total += round($v['score'][0],2);

           $type_score[$v['type'][0]]+=round($v['score'][0],2);

           if(is_array($v['children'])){

               $parent[$k] = round($v['score'][0],2);

               foreach ($v['children'] as $kk => $vv )
               {
                    $child[$k] += $vv['score'][0];
               }
            }
          }
          //var_dump($parent,$child);die;

          foreach ($parent as $k => $v){

              if(round($child[$k],2)<>round($v,2)){
                 message('试题id['.$k.']分数不正确,总分'.$v.'子题总分'.$child[$k]);
                 return;
              }

          }

         //print_r($type_score);die;
          $q_type = C('q_type');

          //print_r($score);die;
          foreach ($type_score as $k=>$v)
          {
              if($v<>$score[$k]['total_score']&&$k<>0)
              {
                  message('试题'.$q_type[$k].'总分不正确');
              }
              if($v<>$total_0&&$k==0)
              {
                  message('试题题组总分不正确');
              }
          }

          if($exam['total_score']<>$total)
          {
              message('试卷分数不正确');
              return;
          }



        $paper['paper_score'] = serialize($paper_scores);


        $res = $this->db->update('exam_paper', $paper, array('paper_id'=>$id), 1);
        if ($res)
        {
            admin_log('edit', 'exam_paper', $id);
            message('修改成功', $back_url);
        }
        else
        {
            message('修改失败');
        }
    }


    /**
     * @description 编辑试卷
     * @author
     * @final
     * @param int $paper_id 试卷id
     * @param string $paper_name 试卷名称
     */
    public function update()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($this->input->post('paper_id'));
        $query = $this->db->get_where('exam_paper', array('paper_id'=>$id), 1);
        $old = $query->row_array();
        if (empty($old))
        {
            message('试卷不存在');
            return;
        }
        $back_url = $this->input->post('back_url');

        $paper['paper_name'] = trim($this->input->post('paper_name'));
        if (empty($paper['paper_name']))
        {
            message('请填写试卷名称');
            return;
        }

        $res = $this->db->update('exam_paper', $paper, array('paper_id'=>$id), 1);
        if ($res)
        {
            admin_log('edit', 'exam_paper', $id);
            message('修改成功', $back_url);
        }
        else
        {
            message('修改失败');
        }
    }

    /**
     * @description 试卷详情
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function detail($id=0)
    {
        $paper_detail = ExamPaperModel::detail($id, 1, 1);

        if (empty($paper_detail)) {
            message('试卷不存在','javascript');
            return;
        }
        $data['back_url'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : site_url('admin/subject_paper/index/'.$paper_detail['exam_id']);
        $data['paper'] = $paper_detail;

        // 模版
        $this->load->view('paper/detail', $data);
    }

    /**
     * @description 试卷对比
     * @author
     * @final
     * @param int $id1 试卷id
     * @param int $id2 试卷id
     */
    public function compare($id1=0, $id2=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $ids = $this->input->post('ids');
        if (! $id1=intval($id1) OR ! $id2=intval($id2))
        {
            message('请选择两个试卷对比');
            return;
        }
        $paper1 = ExamPaperModel::detail($id1, 1, 1);
        $paper2 = ExamPaperModel::detail($id2, 1, 1);
        if (empty($paper1) OR empty($paper2))
        {
            message('试卷不存在');
            return;
        }

        // 相同试题数、分组数
        $paper1['ques_intersect'] = count(array_intersect($paper1['ques_ids'], $paper2['ques_ids']));
        $paper1['group_intersect'] = count(array_intersect($paper1['group_ids'], $paper2['group_ids']));

        $data['paper1'] = $paper1;
        $data['paper2'] = $paper2;

        // 模版
        $this->load->view('paper/compare', $data);
    }




    /**
     * @description 试卷预览分数修改
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function preview($id=0)
    {
        if ( ! $this->check_power('exam_list,exam_manage')) return;

        $id = intval($id);
        $paper = ExamPaperModel::get_paper($id);

        if (empty($paper))
        {
            message('试卷不存在','javascript');
            return;
        }

        $exam = ExamModel::get_exam($paper['exam_id'], 'grade_id,class_id,subject_id,qtype_score,total_score');

        if (empty($exam))
        {
            message('考试期次不存在');
            return;
        }

        // 分数计算
        $score = array();
        // 题目数量
        $question_num = explode(',', $paper['qtype_ques_num']);
        // 题型分数
        $qtype_score = explode(',', $exam['qtype_score']);
        // 总分 排除题组
        $total_score = 0;

        foreach ($qtype_score as $key => $value)
        {
            $score[$key+1]['score'] = round($value,2);
            $score[$key+1]['num'] = isset($question_num[$key+1]) ? $question_num[$key+1] : 0;
            $score[$key+1]['total_score'] = $score[$key+1]['score'] * $score[$key+1]['num'];
            $total_score += $score[$key+1]['score'] * $score[$key+1]['num'];
        }

        // 题组总分
        $total_0 = $exam['total_score'] - $total_score;

        $data['score'] = $score;
        $data['total_0'] = $total_0;

        if ($exam['subject_id'] == 3)
        {
            $group = array(
                    1 => array(), 0 => array(), 4 => array(), 5 => array() ,8 => array() , 3 => array(), 7 => array(), 6 => array(), 2 => array(), 9 => array(), 14 => array()
            );
        }
        else
        {
            $group = array(
                    1 => array(), 2 => array(), 3 => array(), 0 => array(), 14 => array()
            );
        }

        $paper = ExamPaperModel::get_paper_by_id($id);
        $questions_arr = json_decode($paper['question_sort'], true);


        /** 题组分值系数总和 */
        $sql = "SELECT sum(score_factor) as sum FROM {pre}question q
        LEFT JOIN {pre}exam_question eq ON eq.ques_id=q.ques_id
        LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id='$exam[grade_id]' AND rc.class_id='$exam[class_id]'
        WHERE eq.paper_id=$id and q.type=0";

        $query = $this->db->query($sql);

        $sum_score_factor = $query->row_array();

        $sql = "SELECT q.ques_id,q.type,q.title,q.picture,q.answer,q.score_factor,q.children_num,rc.difficulty
        FROM {pre}exam_question eq
        LEFT JOIN {pre}question q ON eq.ques_id=q.ques_id
        LEFT JOIN {pre}relate_class rc ON rc.ques_id=q.ques_id AND rc.grade_id='$exam[grade_id]' AND rc.class_id='$exam[class_id]'
        WHERE eq.paper_id=$id ORDER BY rc.difficulty DESC,q.ques_id ASC";

        $query = $this->db->query($sql);
        $questions_tmp = $query->result_array();

        /* 重新排序 */
        $sort=array();

        if (!is_array($questions_arr)) {
            $sort = $questions_tmp;
        } else {
            foreach ($questions_arr as $v) {
                foreach ($questions_tmp as $value) {
                    if ($v == $value['ques_id']) {
                        $sort[$v] = $value;
                    }
                }
            }
        }

        foreach ($sort as $row) {
            $row['title'] = str_replace("\r\n", '<br/>', $row['title']);
            //$row['title'] = str_replace(" ", '&nbsp;', $row['title']);
            switch($row['type']){
                case 0:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);

                    // 分值系数
                    if ($sum_score_factor > 0)
                    {
                        $row['total_score'] = round($total_0 * $row['score_factor'] / $sum_score_factor['sum'], 2);
                        $row['score'] = round($row['total_score'] / $row['children_num'], 2);
                        $row['children_score'] = round($row['total_score'] / $row['children_num'], 2);
                    }
                    else
                    {
                        $row['total_score'] = 0;
                        $row['score'] = 0;
                    }
                    break;
                case 2:
                    //$row['answer'] = explode(',', $row['answer']);
                case 1:
                    $row['options'] = QuestionModel::get_options($row['ques_id']);
                    break;
                case 3:
                    $row['answer'] = explode("\n", $row['answer']);
                    break;
                case 4:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    $row['children_score'] = round($score[$row['type']]['score']/$row['children_num'],2);
                    break;
                case 5:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    $row['children_score'] = round($score[$row['type']]['score']/$row['children_num'],2);
                    break;
                case 6:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    $row['children_score'] = round($score[$row['type']]['score']/$row['children_num'],2);
                    break;
                case 7:
                    $row['options'] = QuestionModel::get_options($row['ques_id']);
                    break;
                case 8:
                    $row['children'] = QuestionModel::get_children($row['ques_id']);
                    $row['children_score'] = round($score[$row['type']]['score']/$row['children_num'],2);
                    break;
                case 9:
                    $row['answer'] = explode("\n", $row['answer']);
                    break;

                default : break;
            }

            $group[$row['type']][] = $row;
        }

        $tmp_arr = array();

        if ($exam['subject_id'] == 3) {
            $types = array('12','1','0','5','4','8','3','15','11','7','6','2','9','10','13','14');
        }
        else
        {
            $types = array('1','2','3','0','10','14','15','11');
        }

        foreach ($types as $type) {
            $tmp_arr[$type] = isset($group[$type]) ? $group[$type] : array();
        }
        $data['paper'] = $paper;


        $data['qtypes'] = C('qtype');
        $data['qtypes'] = C('qtype');
        $data['group']  = $tmp_arr;

        // 模版
        $this->load->view('paper/preview', $data);
    }
    /**
     * @description 删除试卷
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function delete($id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);

        $query = $this->db->select('exam_id')->get_where('exam_paper', array('paper_id'=>$id), 1);
        $paper = $query->row_array();
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        if (QuestionModel::paper_has_test_action($id)) {
        	message('该试卷已经被考生考过 或者 正在被考中,因此无法操作');
        	return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$paper['exam_id'];

        $this->db->limit(1,0)->update('exam_paper', array('is_delete'=>1), array('paper_id'=>$id));

        admin_log('delete', 'exam_paper', $id);

        message('删除成功', $back_url);
    }

    /**
     * @description 批量删除试卷
     * @author
     * @final
     * @param int $ids 试卷id
     * @param int $exam_id 考试期次id
     */
    public function batch_delete()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = (int)$this->input->post('exam_id');
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }
        //print_r($ids) ;
        $tmp_ids = array();
        $count_success = 0;
        $count_fail = 0;
        foreach ($ids as $id) {
        	if (QuestionModel::paper_has_test_action($id)) {
        		$count_fail++;
        	} else {
        		$count_success++;
	        	$tmp_ids[] = $id;
        	}
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$exam_id;
        if (count($tmp_ids)>0)
        {

            $this->db->where_in('paper_id', $tmp_ids)->update('exam_paper', array('is_delete'=>1));

            admin_log('delete', 'exam_paper', implode(',', $tmp_ids));
        }

        message("批量操作完成,成功删除:".$count_success."个，失败:".$count_fail."个 （可能原因：这些试卷已经被考生考过 或者 正在被考中)。", $back_url);
    }

    /**
     * @description 还原删除的试卷
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function restore($id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);

        $query = $this->db->select('exam_id')->get_where('exam_paper', array('paper_id'=>$id), 1);
        $paper = $query->row_array();
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$paper['exam_id'];

        $this->db->limit(1,0)->update('exam_paper', array('is_delete'=>0), array('paper_id'=>$id));

        admin_log('restore', 'exam_paper', $id);
        message('还原成功', $back_url);
    }

    /**
     * @description 批量还原删除的试卷
     * @author
     * @final
     * @param int $ids 试卷id
     * @param int $exam_id 考试期次id
     */
    public function batch_restore()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = (int)$this->input->post('exam_id');
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$exam_id;

        $this->db->where_in('paper_id', $ids)->update('exam_paper', array('is_delete'=>0));

        admin_log('restore', 'exam_paper', implode(',', $ids));

        message('批量操作完成。', $back_url);
    }

    /**
     * @description 删除回收站的试卷
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function remove($id=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);

        $query = $this->db->select('exam_id')->get_where('exam_paper', array('paper_id'=>$id), 1);
        $paper = $query->row_array();
        if (empty($paper))
        {
            message('试卷不存在');
            return;
        }

        if (QuestionModel::paper_has_test_action($id)) {
        	message('该试卷已经被考生考过 或者 正在被考中,因此无法操作');
        	return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$paper['exam_id'];

        if ($this->_remove($id))
            message('移除成功', $back_url);
        else
            message('移除失败', $back_url);
    }

    /**
     * @description 批量删除回收站的试卷
     * @author
     * @final
     * @param int $ids 试卷id
     * @param int $exam_id 考试期次id
     */
    public function batch_remove()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = (int)$this->input->post('exam_id');
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$exam_id;

        $success = $fail = 0;

        foreach ($ids as $id)
        {
            if ( ! $id = intval($id)) continue;
            if (QuestionModel::paper_has_test_action($id)) {
            	$fail++;
            	continue;
            }

            $res = $this->_remove($id);
            if ($res)
                $success++;
            else
                $fail++;
        }

        $this->db->where_in('paper_id', $ids)->update('exam_paper', array('is_delete'=>0));

        message('批量操作完成。成功'.$success.'个，失败'.$fail.'个。', $back_url);
    }

    /**
     * @description 删除回收站的试卷
     * @author
     * @final
     * @param int $id 试卷id
     */
    private function _remove($id)
    {
        $res = $this->db->delete(array('exam_paper', 'exam_question'), array('paper_id'=>$id));
        admin_log('remove', 'exam_paper', $id);
        return true;

        // 一次删除多个表，返回值是空。
        if ($res)
        {
            admin_log('remove', 'exam_paper', $id);
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @description 批量更新试卷
     * @author
     * @final
     * @param int $id 试卷id
     * @param int $exam_id 考试期次id
     */
    public function batch_renew()
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_id = (int)$this->input->post('exam_id');
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要操作的项目！');
            return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$exam_id;

        $success = $fail = 0;
        foreach ($ids as $id)
        {
            if ( ! $id = intval($id)) continue;

            if (QuestionModel::paper_has_test_action($id)) {
            	$fail++;
            	continue;
            }

            $res = ExamPaperModel::renew($id);
            if ($res)
                $success++;
            else
                $fail++;
        }

        message('批量操作完成。成功'.$success.'个，失败'.$fail.'个。', $back_url);
    }

    /**
     * @description 更新试卷统计
     * @author
     * @final
     * @param int $id 试卷id
     */
    public function renew($id=0)
    {
        $id = intval($id);

        $exam_id = ExamPaperModel::get_paper($id, 'exam_id');
        if (empty($exam_id))
        {
            message('试卷不存在');
            return;
        }

        if (QuestionModel::paper_has_test_action($id)) {
        	message('该试卷已经被考生考过 或者 正在被考中,因此无法操作');
        	return;
        }

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/paper/index/'.$exam_id;

        if (ExamPaperModel::renew($id))
        {
            message('更新成功', $back_url);
        }
        else
        {
            message('更新失败');
        }
    }


}
