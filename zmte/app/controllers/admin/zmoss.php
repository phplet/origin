<?php if ( ! defined('BASEPATH')) exit();
/**
 * 阅卷相关controller
 */
class Zmoss extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        Fn::ajax_call($this, 'removeExamRelate', 'setExamRelateFlag');
        
        $data = array();
        
        $cond_param = array(
            'er_exampid' => 0
        );
        
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();
        
        $total = ZmossModel::examRelatelistCount($cond_param);
        if ($total)
        {
            $list = ZmossModel::examRelatelist($cond_param, $page, $size);
            $er_exampid = array();
            foreach ($list as $k => $val)
            {
                $er_exampid[] = $val['er_examid'];
                
                unset($list[$k]);
                $list[$val['er_examid']] = $val;
            }
            $cond_param['er_exampid'] = implode(',', $er_exampid);
            
            $list2 = ZmossModel::examRelatelist($cond_param);
            $zmoss_examid = array();
            foreach ($list2 as $item)
            {
                $list[$item['er_exampid']]['child'][] = $item;
                $zmoss_examid[] = $list[$item['er_exampid']]['er_zmoss_examid'];
            }
            
            $zmoss_examid = array_unique($zmoss_examid);
            
            $data['examrelatelist'] = ZmossModel::examlist('*', array('exam_id' => implode(',', $zmoss_examid)));
        }
        
        $data['list'] = $list;
        $data['admin_list'] = CpUserModel::get_cpuser_list();
        
        // 分页
        $purl = site_url('admin/zmoss/index/');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['er_flag'] = array('未同步', '进行中', '同步失败', '同步成功');
        
        $data['is_setexamrelate_priv'] = $this->check_power_new('zmoss_setexamrelate', false);
        $data['is_setexamrelatequestion_priv'] = $this->check_power_new('zmoss_setexamrelatequestion', false);
        $data['is_sync_result_priv'] = $this->check_power_new('zmoss_sync', false);
        $data['is_remove_priv'] = $this->check_power_new('zmoss_remove', false);
        $data['is_showresult_priv'] = $this->check_power_new('exam_index', false);
        
        // 模版
        $this->load->view('zmoss/index', $data);
    }
    
    public function setexamrelate($er_exampid = 0)
    {
        $er_exampid = intval($er_exampid);
        
        if ($er_exampid > 0)
        {
            $exam = ExamModel::get_exam($er_exampid);
            if ($exam['exam_ticket_maprule'] < 1)
            {
                message('该考试不是外部纸质考试，无法对应考试关系！');
            }
            
            if ($exam['exam_pid'] > 0)
            {
                redirect('/admin/zmoss/setexamrelate/' . $exam['exam_pid']);
                exit;
            }
            
            $data['exam'] = $exam;
            $data['examrelate'] = ZmossModel::examRelateInfo($er_exampid);
            $data['examrelatelist'] = ZmossModel::examRelateZmossExamList($er_exampid);
            
            $examlist2 = ExamModel::get_exam_list(array('exam_pid' => $er_exampid), null, null, 'subject_id ASC');
        }
        else
        {
            $cond_param = array(
                'er_exampid' => 0,
            );
            $list = ZmossModel::examRelatelist($cond_param, 1, time());
            $examplist = array_keys($list);
            
            $examlist = ExamModel::get_exam_list_all(array('exam_pid' => 0, 'exam_ticket_maprule  >' => '0'));
            $examlist2 = array();
            if ($examlist)
            {
                $exam_pid = array();
                foreach ($examlist as $k => $item)
                {
                    if (in_array($item['exam_id'], $examplist))
                    {
                        unset($examlist[$k]);
                        continue;
                    }
                    
                    $exam_pid[] = $item['exam_id'];
                }
            
                $list = ExamModel::get_exam_list(array('exam_pid' => $exam_pid), null, null, 'subject_id ASC');
                foreach ($list as $item)
                {
                    $examlist2[$item['exam_pid']][] = $item;
                }
                unset($list);
            }
        }
        
        $where = "exam_pid = 0";
        $er_zmoss_examid = ZmossModel::examRelateZmossExamList();
        if ($er_exampid > 0)
        {
            unset($er_zmoss_examid[$er_exampid]);
        }
        
        if ($er_zmoss_examid)
        {
            $where .= " AND exam_id NOT IN (" . implode(',', $er_zmoss_examid) . ")";
        }
        
        $zmossexamlist = ZmossModel::examlist("*", $where);
        
        $zmossexamlist2 = array();
        if ($zmossexamlist)
        {
            $list = ZmossModel::examlist("*", 
                array('exam_pid' => implode(',', array_keys($zmossexamlist))));
            foreach ($list as $item)
            {
                $zmossexamlist2[$item['exam_pid']][] = $item;
            }
            unset($list);
        }
        
        $data['examlist'] = $examlist;
        $data['examlist2'] = $examlist2;
        $data['zmossexamlist'] = $zmossexamlist;
        $data['zmossexamlist2'] = $zmossexamlist2;
        $data['subject'] = C('subject');
        
        // 模版
        $this->load->view('zmoss/setexamrelate', $data);
    }
    
    public function saveexamrelate()
    {
        $er_examid = Fn::getParam('er_exampid');
        $param['er_examid'] = $er_examid;
        $param['er_zmoss_examid'] = Fn::getParam('er_zmoss_exampid');
        
        if (!Validate::isInt($param['er_examid'])
            || $param['er_examid'] <= 0
            || !Validate::isInt($param['er_zmoss_examid'])
            || $param['er_zmoss_examid'] <= 0)
        {
            message('请设置考试对应关系！');
        }
        
        $exam_relate = Fn::getParam('exam_relate');
        /*
        if (count(array_filter($exam_relate)) 
            != count(array_unique(array_filter($exam_relate))))
        {
            message('考试对应关系只能是一对一！');
        }
        */
        
        $exam_id = Fn::getParam('er_examid');
        
        try 
        {
            $data[] = $param;
                
            $insert = array();
            
            foreach ($exam_relate as $k => $er_zmoss_examid)
            {
                if ($er_zmoss_examid < 1 
                    || !$exam_id[$k])
                {
                    continue;
                }
                
                $param = array(
                    'er_examid'  => $exam_id[$k],
                    'er_zmoss_examid' => $er_zmoss_examid,
                    'er_exampid' => $er_examid
                );
                
                $k = implode('_', $param);
                if (in_array($k,$insert))
                {
                    continue;
                }
                
                $insert[] = $k;
                
                $data[] = $param;
            }
            
            $message = "考试对应关系设置";
            if (ZmossModel::setExamRelate($data))
            {
                $message .= "成功！";
                
                admin_log('set', 'exam_relate', $er_examid);
            }
            else
            {
                $message .= "失败！";
            }
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();
        }
        
        message($message, '/admin/zmoss/setexamrelate/' . $er_examid);
    }
    
    public function removeExamRelateFunc($er_examid)
    {
        $resp = new AjaxResponse();
        
        if (!$this->check_power_new('zmoss_remove', false))
        {
            $resp->alert('您没有权限！');
            return $resp;
        }
        
        if (!Validate::isInt($er_examid)
            || $er_examid <= 0)
        {
            $resp->alert('请指定需要删除对应关系的考试期次！');
            return $resp;
        }
        
        if (ExamModel::get_exam($er_examid, 'exam_pid'))
        {
            $resp->alert('请指定需要删除对应关系的考试期次！');
            return $resp;
        }
        
        try 
        {
            if (ZmossModel::removeExamRelate($er_examid))
            {
                admin_log('delete', 'exam_relate', $er_examid);
                
                $resp->alert('删除考试期次的对应关系成功！');
                $resp->refresh();
            }
            else
            {
                $resp->alert('删除考试期次的对应关系失败！');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        
        return $resp;
    }
    
    public function setExamRelateFlagFunc($er_examid, $er_zmoss_examid, $er_flag = 0)
    {
        $resp = new AjaxResponse();
        
        if (!$this->check_power_new('zmoss_sync', false))
        {
            $resp->alert('您没有权限！');
            return $resp;
        }
    
        if (!Validate::isInt($er_examid)
            || $er_examid <= 0
            || !Validate::isInt($er_zmoss_examid)
            || $er_zmoss_examid <= 0)
        {
            $resp->alert('请指定需要同步成绩的考试学科');
            return $resp;
        }
        
        $er_flag = intval($er_flag);
        if (!in_array($er_flag, array(0, 1, 2, 3)))
        {
            $resp->alert('请设置合理的状态');
            return $resp;
        }
    
        if (!ExamModel::get_exam($er_examid, 'exam_pid'))
        {
            $resp->alert('请指定需要同步成绩的考试学科！');
            return $resp;
        }
    
        try
        {
            if (ZmossModel::setExamRelateFlag($er_examid, $er_zmoss_examid, $er_flag))
            {
                admin_log('set', 'sync_result', $er_examid .'-'. $er_zmoss_examid);
                
                $resp->alert('同步成绩已加入计划任务列表中，请稍后查看同步结果！');
                $resp->refresh();
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
    
        return $resp;
    }
    
    public function setexamrelatequestion($erq_examid, $erq_zmoss_examid)
    {
        $erq_examid = intval($erq_examid);
        $erq_zmoss_examid = intval($erq_zmoss_examid);
        
        $paper_id = intval(Fn::getParam('er_paperid'));
        
        $exam = ExamModel::get_exam($erq_examid);
        if (!$exam['exam_pid'])
        {
            message('请指定需要设置试题对应的考试学科！');
        }
        
        $examrelate = ZmossModel::examRelateInfo($erq_examid, $erq_zmoss_examid);
        if (!$examrelate)
        {
            message('此考试学科没有对应关系，无法设置试题关系！');
        }
        
        $zmossquestion = ZmossModel::examQuestionList($examrelate['er_zmoss_examid']);
        if (!$zmossquestion)
        {
            message('此考试学科对应阅卷系统的考试没有试题，无法设置试题关系！');
        }
        
        $paperlist = ExamPaperModel::examSubjectPaperList($erq_examid);
        if (!$paperlist)
        {
            message('此考试学科没有设置考试试卷，无法设置试题关系！');
        }
        
        if ($paper_id)
        {
            $paper = $paperlist[$paper_id];
        }
        
        if (!$paper)
        {
            $paper = current($paperlist);
        }
        
        /*
        $paperquestion = ExamPaperModel::examPaperQuestion(
            $paper['paper_id'], 'q.ques_id, q.parent_id, q.type, IFNULL(pq.type, q.type) AS p_type', 
            'AND q.is_parent = 0', 'ORDER BY p_type ASC, q.parent_id ASC, q.ques_id ASC');
        */
        $question = json_decode($paper['question_sort'], true);
        $paperquestion = array();
        foreach ($question as $k => $ques_id)
        {
            $info = QuestionModel::get_question($ques_id);
            $list = QuestionModel::get_children($ques_id);
            if ($list)
            {
                foreach ($list as $key => $val)
                {
                    $paperquestion[$val['ques_id']] = array(
                        'ques_id' => $val['ques_id'],
                        'type' => $info['type'],
                        'parent_id' => $ques_id
                    );
                }
            }
            else
            {
                
                $paperquestion[$ques_id] = array(
                    'ques_id' => $ques_id,
                    'type' => $info['type'],
                );
            }
        }
        
        $examrelatequestion = ZmossModel::examRelateQuestionInfo($erq_examid, $erq_zmoss_examid, $paper['paper_id']);
        $erq_relate_data = array();
        if ($examrelatequestion)
        {
            $erq_relate_data = json_decode($examrelatequestion['erq_relate_data'], true);
        }
        
        $data['examrelatequestion'] = $erq_relate_data;
        $data['examrelate'] = $examrelate;
        $data['paperquestion'] = $paperquestion;
        $data['zmossquestion'] = $zmossquestion;
        $data['qtype'] = C('qtype');
        $data['paperlist'] = $paperlist;
        
        $this->load->view('zmoss/setexamrelatequestion', $data);
    }
    
    public function saveexamrelatequestion()
    {
        $param = Func::param_copy($_POST, 'erq_examid', 
            'erq_paperid', 'erq_zmoss_examid', 'erq_relate_data');
        
        if (!Validate::isInt($param['erq_examid'])
            || $param['erq_examid'] <= 0
            || !Validate::isInt($param['erq_zmoss_examid'])
            || $param['erq_zmoss_examid'] <= 0)
        {
            message('请确认考试学科对应关系！');
        }
        
        if (!Validate::isInt($param['erq_paperid'])
            || $param['erq_paperid'] <= 0)
        {
            message('测评考试学科试卷不可为空！');
        }
        
        $erq_relate_data = $param['erq_relate_data'];
        if ($erq_relate_data != array_filter($param['erq_relate_data'])
            || count($erq_relate_data) != count(array_unique($erq_relate_data)))
        {
            message('试题对应关系只能是一对一且不可为空！');
        }
        $param['erq_relate_data'] = json_encode($param['erq_relate_data']);
        
        try 
        {
            $message = "试题对应关系设置";
            if (ZmossModel::setExamRelateQuestion($param))
            {
                admin_log('set', 'exam_relate_question', $param['erq_examid']);
                $message .= "成功！";
            }
            else
            {
                $message .= "失败！";
            }
        }
        catch (Exception $e)
        {
            $message = $e->getMessage();
        }
        
        message($message, '/admin/zmoss/setexamrelatequestion/' . $param['erq_examid'].'/'. $param['erq_zmoss_examid'] . '/?er_paperid=' . $param['erq_paperid']);
    }
}