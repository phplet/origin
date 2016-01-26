<?php
/**
 * 阅卷相关数据及与测评数据对应关系model
 */
class ZmossModel
{
    /**
     * 阅卷考试期次列表
     * 获取符合$cond_param查询条件所有考试期次
     * 返回查询结果集/数量，默认按exam_id倒序排序
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          int|string  exam_pid 若有效且为数字按=查询,若形如1,2,3按IN()查询
     *          string      order_by    若有效按指定条件排序，即ORDER BY $order_by
     * @return  array   list<map<string, variant>>
     */
    public static function examlist($field = "*", 
        $cond_param = null, $page = null, $perpage = null)
    {
        $bind = array();
        $where = array();
        
        if (is_string($cond_param))
        {
            $where[] = $cond_param;
        }
        else if (is_array($cond_param))
        {
            if (Validate::isInt($cond_param['exam_pid']))
            {
                $where[] = 'exam_pid = ?';
                $bind[] = $cond_param['exam_pid'];
            }
            else if (Validate::isJoinedIntStr($cond_param['exam_pid']))
            {
                $where[] = 'exam_pid IN (' . $cond_param['exam_pid'] . ')';
            }
        }
        
        $field = $field ? $field : '*';
        
        $sql = "SELECT $field FROM t_exam";
        
        if (!empty($where))
        {
            $sql .= ' WHERE (' .implode(') AND (', $where). ')';
        }
        
        if (isset($cond_param['order_by']))
        {
            $sql .= ' ORDER BY '.$cond_param['order_by'];
        }
        else
        {
            $sql .= ' ORDER BY exam_id DESC';
        }
        
        $db = Fn::db_pg();
        
        if ($page)
        {
            $perpage = $perpage ? $perpage : C('default_perpage_num');
            $sql = $db->limitPage($sql, $page, $perpage);
        }
        
        return $db->fetchAssoc($sql, $bind);
    }
    
    /**
     * 阅卷考试信息
     * @param   int     $exam_id   考试id
     * @return  array   map<string, variant>
     */
    public static function examInfo($exam_id)
    {
        if (!$exam_id
            || !Validate::isInt($exam_id))
        {
            return array();
        }
        
        $sql = "SELECT * FROM t_exam WHERE exam_id = ?";
        return Fn::db_pg()->fetchRow($sql, array($exam_id));
    }
    
    /**
     * 阅卷考试试题
     * @param   int     $exam_id   考试id
     * @return  array   list<map<string, variant>>
     */
    public static function examQuestionList($exam_id)
    {
        if (!$exam_id
            || !Validate::isInt($exam_id))
        {
            return array();
        }
        
        $db = Fn::db_pg();
        
        $sql = "SELECT * FROM t_question 
                WHERE ques_examid = ? AND ques_pid = 0
                ORDER BY ques_no ASC";
        $questionlist = $db->fetchAssoc($sql, array($exam_id));
        if ($questionlist)
        {
            $ques_id_str = implode(',', array_keys($questionlist));
            
            $sql = "SELECT * FROM t_question
                    WHERE ques_pid IN ($ques_id_str)
                    ORDER BY ques_no ASC";
            $stmt = $db->query($sql);
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                $questionlist[$item['ques_pid']]['child'][] = $item;
            }
        }
        
        return $questionlist;
    }
    
    /**
     * 测评考试对应阅卷系统考试列表
     * 获取符合$cond_param查询条件所有考试期次
     * 返回查询结果集/数量，默认按exam_id倒序排序
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          int|string  er_exampid  若有效且为数字按=查询,若形如1,2,3按IN()查询
     *          string      order_by    若有效按指定条件排序，即ORDER BY $order_by
     * @return  array   list<map<string, variant>>
     */
    public static function examRelatelist($cond_param = null, $page = null, $perpage = null)
    {
        $bind = array();
        $where = array();
        
        if (is_string($cond_param))
        {
            $where[] = $cond_param;
        }
        else if (is_array($cond_param))
        {
            if (Validate::isInt($cond_param['er_exampid']))
            {
                $where[] = 'er_exampid = ?';
                $bind[] = $cond_param['er_exampid'];
            }
            else if (Validate::isJoinedIntStr($cond_param['er_exampid']))
            {
                $where[] = 'er_exampid IN (' . $cond_param['er_exampid'] . ')';
            }
        }
        
        $sql = "SELECT er.*, e.exam_name, e.subject_id FROM t_exam_relate er
                LEFT JOIN rd_exam e ON e.exam_id = er.er_examid";
        
        if (!empty($where))
        {
            $sql .= ' WHERE (' .implode(') AND (', $where). ')';
        }
        
        if (isset($cond_param['order_by']))
        {
            $sql .= ' ORDER BY '.$cond_param['order_by'];
        }
        else
        {
            $sql .= ' ORDER BY subject_id ASC,exam_id DESC';
        }
        
        $db = Fn::db();
        
        if ($page)
        {
            $perpage = $perpage ? $perpage : C('default_perpage_num');
            $sql = $db->limitPage($sql, $page, $perpage);
        }
        
        return $db->fetchAll($sql, $bind);
    }
    
    public static function examRelatelistCount($cond_param = null)
    {
        $bind = array();
        $where = array();
    
        if (is_string($cond_param))
        {
            $where[] = $cond_param;
        }
        else if (is_array($cond_param))
        {
            if (Validate::isInt($cond_param['er_exampid']))
            {
                $where[] = 'er_exampid = ?';
                $bind[] = $cond_param['er_exampid'];
            }
            else if (Validate::isJoinedIntStr($cond_param['er_exampid']))
            {
                $where[] = 'er_exampid IN (' . $cond_param['er_exampid'] . ')';
            }
        }
    
        $sql = "SELECT COUNT(*) FROM t_exam_relate";
    
        if (!empty($where))
        {
            $sql .= ' WHERE (' .implode(') AND (', $where). ')';
        }
        
        return Fn::db()->fetchOne($sql, $bind);
    }
    
    /**
     * 考试对应关系
     * @param   int     $er_examid     测评考试id
     * @param   int     $er_zmoss_examid 阅卷考试id
     * @return  array   map<string, variant>
     */
    public static function examRelateInfo($er_examid, $er_zmoss_examid = null)
    {
        if (!$er_examid
            || !Validate::isInt($er_examid))
        {
            return array();
        }
    
        $db = Fn::db();
    
        $sql = "SELECT * FROM t_exam_relate
                WHERE er_examid = ?";
        if ($er_zmoss_examid)
        {
            $sql .= " AND er_zmoss_examid = $er_zmoss_examid";
        }
        return $db->fetchRow($sql, array($er_examid));
    }
    
    /**
     * 考试试题对应关系
     * @param   int     $erq_examid     测评考试id
     * @param   int     $erq_paperid    试卷id
     * @param   int     $erq_zmoss_examid 阅卷考试id
     * @return  array   map<string, variant>
     */
    public static function examRelateQuestionInfo($erq_examid, $erq_zmoss_examid, $erq_paperid = null)
    {
        if (!$erq_examid
            || !Validate::isInt($erq_examid)
            || !$erq_zmoss_examid
            || !Validate::isInt($erq_zmoss_examid))
        {
            return array();
        }
        
        $db = Fn::db();
        
        $sql = "SELECT * FROM t_exam_relate_question
                WHERE erq_examid = ? AND erq_zmoss_examid = ?";
        if ($erq_paperid > 0)
        {
            $sql .= " AND erq_paperid = $erq_paperid";
            
            return $db->fetchRow($sql, array($erq_examid, $erq_zmoss_examid));
        }
        else 
        {
            return $db->fetchAll($sql, array($erq_examid, $erq_zmoss_examid));
        }
    }
    
    /**
     * 设置考试对应关系
     * 若$param为array，则为map<string, variant>，参数如下：
     *          int     er_examid   测评考试id
     *          int     er_zmoss_examid 阅卷考试id
     *          int     er_exampid  测评考试期次
     * @return  bool    true|false
     */
    public static function setExamRelate($param)
    {
        if (!$param
            || !is_array($param))
        {
            return false;
        }
        
        $db = Fn::db();
        
        if (!$db->beginTransaction())
        {
            return false;
        }
        
        $sql = "DELETE FROM t_exam_relate
                    WHERE er_exampid = ? OR er_examid = ?";
        $db->query($sql, array($param[0]['er_examid'], $param[0]['er_examid']));
        
        $exam_ids = array();
        foreach ($param as $bind)
        {
            $bind = Func::param_copy($bind, 'er_examid',
                'er_zmoss_examid', 'er_exampid');
            
            if (!Validate::isInt($bind['er_examid'])
                || $bind['er_examid'] <= 0)
            {
                throw new Exception('请指定测评考试学科');
            }
            
            if (!Validate::isInt($bind['er_zmoss_examid'])
                || $bind['er_zmoss_examid'] <= 0)
            {
                throw new Exception('请指定阅卷系统对应考试学科');
            }
            
            $bind['er_exampid'] = $bind['er_exampid'] ? $bind['er_exampid'] : 0;
            $bind['er_adminid'] = Fn::sess()->userdata('admin_id');
            $bind['er_addtime'] = time();
            
            $exam_ids[$bind['er_zmoss_examid']] = $bind['er_examid'];
            
            $db->replace('t_exam_relate', $bind);
        }
        
        if ($exam_ids)
        {
            $exam_id_str = implode(',', array_values($exam_ids));
            
            $sql = "SELECT erq_examid, erq_zmoss_examid 
                    FROM t_exam_relate_question
                    WHERE erq_examid IN ($exam_id_str)";
            $stmt = $db->query($sql);
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                if (!isset($exam_ids[$item['erq_zmoss_examid']])
                    || $exam_ids[$item['erq_zmoss_examid']] != $item['erq_examid'])
                {
                    $sql = "DELETE FROM t_exam_relate_question 
                            WHERE erq_examid = ? AND erq_zmoss_examid = ?";
                    $db->query($sql, array($item['erq_examid'], $item['erq_zmoss_examid']));
                }
            }
        }
        
        $flag = $db->commit();
        if (!$flag)
        {
            $db->rollBack();
        }
        
        return $flag;
    }
    
    /**
     * 删除期次对应关系
     * @param   int     $er_examid
     * @return  bool    true|false
     */
    public static function removeExamRelate($er_examid)
    {
        if (!$er_examid
            || !Validate::isInt($er_examid))
        {
            return false;
        }
        
        $db = Fn::db();
        
        $flag = false;
        if ($db->beginTransaction())
        {
            $sql = "DELETE FROM t_exam_relate_question
                    WHERE erq_examid IN (
                        SELECT er_examid FROM t_exam_relate
                        WHERE er_exampid = $er_examid
                    )";
            $db->query($sql);
    
            $sql = "DELETE FROM t_exam_relate
                    WHERE er_exampid = $er_examid
                        OR er_examid = $er_examid
                    ";
            $db->query($sql);
        
            $flag = $db->commit();
            if (!$flag)
            {
                $db->rollBack();
            }
        }
        
        return $flag;
    }
    
    /**
     * 设置考试试题对应关系
     * 若$param为array，则为map<string, variant>，参数如下：
     *          int     erq_examid   测评考试id
     *          int     erq_paperid   测评考试试卷id
     *          int     er_zmoss_examid 阅卷考试id
     *          string  erq_relate_data 试题对应关系
     * @return  bool    true|false
     */
    public static function setExamRelateQuestion($param)
    {
        $param = Func::param_copy($param, 'erq_examid',
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
            throw new Exception('测评考试学科试卷不可为空！');
        }
        
        if (!Validate::isNotEmpty($param['erq_relate_data']))
        {
            throw new Exception('试题对应关系不可为空！');
        }
        
        $param['erq_adminid'] = Fn::sess()->userdata('admin_id');
        $param['erq_addtime'] = time();
        
        return Fn::db()->replace('t_exam_relate_question', $param);
    }
    
    /**
     * 获取已对应测评考试期次的阅卷考试期次
     */
    public static function examRelateZmossExamList($er_exampid = 0)
    {
        $sql = "SELECT er_examid, er_zmoss_examid
                FROM t_exam_relate
                WHERE er_exampid = ?";
        
        if ($er_exampid > 0)
        {
            return Fn::db()->fetchAll($sql, array($er_exampid));
        }
        else 
        {
            return Fn::db()->fetchPairs($sql, array($er_exampid));
        }
    }
    
    /**
     * 设置考试成绩同步状态
     * @param   int     $er_examid  考试id
     * @param   int     $er_flag    同步状态
     */
    public static function setExamRelateFlag($er_examid, $er_zmoss_examid, $er_flag)
    {
        if (!Validate::isInt($er_examid)
            || $er_examid <= 0
            || !Validate::isInt($er_zmoss_examid)
            || $er_zmoss_examid <= 0)
        {
            throw new Exception('请指定需要同步成绩的考试学科');
        }
        
        $er_flag = intval($er_flag);
        if (!in_array($er_flag, array(0, 1, 2, 3)))
        {
            throw new Exception('请设置合理的状态');
        }
        
        if (!ExamModel::get_exam($er_examid, 'exam_pid'))
        {
            throw new Exception('请指定需要同步成绩的考试学科！');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT COUNT(*) FROM t_exam_relate_question 
                WHERE erq_examid = ? AND erq_zmoss_examid = ?";
        if (!$db->fetchOne($sql, 
            array($er_examid, $er_zmoss_examid)))
        {
            throw new Exception('当前考试学科还未设置试题对应关系，无法同步成绩！');
        }
        
        return $db->update('t_exam_relate', 
            array('er_flag' => $er_flag), 
            'er_examid = ? AND er_zmoss_examid = ?', 
            array($er_examid, $er_zmoss_examid));
    }
    
    /**
     * 将阅卷系统成绩同步到测评系统中
     * @param   int     $er_examid    考试id
     * @param   int     $er_zmoss_examid 阅卷考试id
     * @return  bool    true|false
     */
    public static function syncZmossExamResults($er_examid, $er_zmoss_examid)
    {
        if (!Validate::isInt($er_examid)
            || $er_examid <= 0
            || !Validate::isInt($er_zmoss_examid)
            || $er_zmoss_examid <= 0)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $examrelate = self::examRelateInfo($er_examid, $er_zmoss_examid);
        if (!$examrelate || !$examrelate['er_exampid'])
        {
            return false;
        }
        
        $examrelatequestion = self::examRelateQuestionInfo($er_examid, $er_zmoss_examid);
        if (!$examrelatequestion)
        {
            return false;
        }
        
        $sql = "SELECT COUNT(*) FROM rd_exam_test_result
                WHERE exam_id = ?";
        if (!$db->fetchOne($sql, array($er_examid)))
        {
            return false;
        }
        
        $questionrelate = array();
        foreach ($examrelatequestion as $item)
        {
            $questionrelate[$item['erq_paperid']] = json_decode($item['erq_relate_data'], true);
        }
        
        $psql = Fn::db_pg();
        
        $zmoss_examid = $examrelate['er_zmoss_examid'];
        
        //检查考试阅卷是否已完成
        $sql = "SELECT exam_id FROM t_exam
                WHERE exam_id = ? AND exam_flag = 6";
        if (!$psql->fetchRow($sql, array($zmoss_examid)))
        {
            return false;
        }
        
        //查询所有学生客观题得分
        $sql = "SELECT stu_exam_ticket, soqa_quesid, soqa_testscore, ques_fullscore
                FROM t_student
                LEFT JOIN v_student_objective_question_answer ON soqa_stuid = stu_id
                WHERE soqa_examid = ? AND soqa_flag = 1";
        $stmt = $psql->query($sql, array($zmoss_examid));
        $list = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $list[trim($row['stu_exam_ticket'])][$row['soqa_quesid']] = array(
                'full_score' => $row['ques_fullscore'],
                'test_score' => $row['soqa_testscore'],
            );
        }
        
        if (!$list)
        {
            return false;
        }
        
        //查询所有学生主观题得分
        $sql = "SELECT stu_exam_ticket, ques_id, ques_fullscore, etsq_testscore
                FROM t_student
                LEFT JOIN t_evaluation_task_student_question ON etsq_stuid = stu_id
                LEFT JOIN t_question ON ques_id = etsq_quesid
                WHERE ques_examid = ?";
        $stmt = $psql->query($sql, array($zmoss_examid));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $list[trim($row['stu_exam_ticket'])][$row['ques_id']] = array(
                'full_score' => $row['ques_fullscore'],
                'test_score' => $row['etsq_testscore'],
            );
        }
        
        if (!$db->beginTransaction())
        {
            return false;
        }
        
        $sql = "SELECT etp.etp_id, etp.paper_id, s.external_account, etp.uid
                FROM rd_exam_test_paper etp
                LEFT JOIN rd_student s ON s.uid = etp.uid
                WHERE etp.exam_id = ?";
        $stmt = $db->query($sql, array($er_examid));
        
        $uids = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            @list($etp_id, $paper_id, $exam_ticket, $uid) = array_values($row);
            
            $uids[] = $uid;
            
            $exam_ticket = trim($exam_ticket);
            
            $ques_score = $list[$exam_ticket];
            if (!$ques_score)
            {
                continue;
            }
            
            $question = $questionrelate[$paper_id];
            if (!$question)
            {
                continue;
            }
            
            foreach ($question as $ques_id => $zmoss_quesid)
            {
                $bind = array(
                    'full_score' => $ques_score[$zmoss_quesid]['full_score'],
                    'test_score' => $ques_score[$zmoss_quesid]['test_score'],
                );
                
                $db->update('rd_exam_test_result', $bind,
                    'etp_id = ? AND (ques_id = ? OR sub_ques_id = ?)',
                    array($etp_id, $ques_id, $ques_id));
            }
        }
        
        if ($uids)
        {
            foreach ($uids as $uid)
            {
                ExamstatModel::calculatePaperScore($examrelate['er_exampid'], $uid);    //计算试卷得分
            }
        }
        
        $sql = "SELECT COUNT(*) FROM t_exam_relate
                WHERE er_exampid = ? AND er_flag < 3";
        if (!$db->fetchOne($sql, array($examrelate['er_exampid'])))
        {
            //所有考场均已完成成绩导入计算
            ExamstatModel::updateQuestionScore($examrelate['er_exampid'], 0, true);//更新试题分数
            ExamstatModel::updateExamQuestionstat($examrelate['er_exampid']); //更新试卷试题答题情况
        
            $bind = array(
                'exam_pid' => $examrelate['er_exampid'],
                'status'   => 1,
                'c_time'   => time()
            );
            $db->replace('rd_cron_task_exam_result', $bind);
        }
        
        $flag = $db->commit();
        if (!$flag)
        {
            $db->rollBack();
        }
        
        return $flag;
    }
    
    /**
     * 执行考试成绩同步
     */
    public static function initSyncZmossExamResults()
    {
        $db = Fn::db();
        
        $sql = "SELECT er_examid, er_zmoss_examid FROM t_exam_relate
                WHERE er_flag > 0 AND er_flag < 3 AND er_exampid > 0 limit 5";
        $list = $db->fetchAll($sql);
        if (!$list)
        {
            return false;
        }
        
        foreach ($list as $item)
        {
            $db->update('t_exam_relate', array('er_flag' => 100),
                'er_examid = ? AND er_zmoss_examid = ?',
                array($item['er_examid'], $item['er_zmoss_examid']));
        }
        
        foreach ($list as $item) 
        {
            $flag = self::syncZmossExamResults($item['er_examid'], $item['er_zmoss_examid']);
            if ($flag)
            {
                $bind = array('er_flag' => 3);
            }
            else
            {
                $bind = array('er_flag' => 2);
            }
            
            $db->update('t_exam_relate', $bind, 
                'er_examid = ? AND er_zmoss_examid = ?', 
                array($item['er_examid'], $item['er_zmoss_examid']));
        }
    }
}