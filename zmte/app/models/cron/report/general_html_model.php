<?php if ( ! defined('BASEPATH')) exit();
/**
 *
 * 测评报告 --生成报告模板->html
 * @author tcg
 * @final 2015-02-03
 */
class General_html_model extends CI_Model
{
    private static $_db;
    private static $_uid; //学生id
    private static $_stu_id; //学生id
    private static $_schcls_id; //班级id
    private static $_ct_id; //教师id
    private static $_exam_pid; //考试期次id
    private static $_rule_id;  //评估规则id
    private static $_place_id; //考场id
    private static $_exam_id;  //考试id
    private static $_data = array(); //html模板数据
    private static $_data_free_exam_time = array(); //自由考试时间
    private static $_template = array(); //评估模板
    private static $_studnet_name = array();//学生姓名
    private static $_class_name = array();//班级名称
    private static $_teacher_name = array();//教师姓名
    private static $_rule = array(); //评估规则
    private static $_subject_exams = array(); //考试期次下学科对应的考试id
    private static $_technology_data = array(); //技术生成报告数据

    public function __construct()
    {
    	parent::__construct();
    	
    	self::$_db = Fn::db();
    }

    /**
     * 根据评估规则生成需要生成html的记录
     */
    public function general_html_record()
    {
        $bOk = false;
        if (!self::$_db->beginTransaction())
        {
            return $bOk;
        }
        
        //获取待处理的评估规则
        $sql = "SELECT rule_id, status FROM rd_cron_task_report 
                WHERE status = 0";
        $query = self::$_db->query($sql);       

        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $status = $item['status'];
            $rule_id = $item['rule_id'];

            //获取该规则信息
            $rule = $this->_rule($rule_id);
            if (!$rule) 
            {
                continue;
            }
            
            //考试期次
            $exam_pid = $rule['exam_pid'];

            //当前评估规则和考试期次
            self::$_rule_id = $rule_id;
            self::$_exam_pid = $exam_pid;
            self::$_place_id = $rule['place_id'];

            //检查该期考试的所有考场都已经结束
            if ($this->_check_exam_status())
            {
                continue;
            }
            
            //是否生成班级报告
            $schcls_ids = array();
            if ($rule['generate_class_report'] == 1)
            {
                $schcls_ids = $this->_place_class($exam_pid, $rule['place_id']);
            }
            
            $ct_ids = array();
            if ($rule['generate_teacher_report'] == 1)
            {
                $ct_ids = $this->_teacher($exam_pid);
            }
            
            //生成学科(0:各学科总结)
            $g_subject_id = $rule['subject_id'];

            //生成模式
            $generate_mode = $rule['generate_mode'];
            //单人模式下需要生成报告的考生
            $generate_uid = $rule['generate_uid'];

            //待生成报告的考生
            $uids = array();
            if ($rule['generate_subject_report'] == 1 
                || $rule['generate_transcript'] == 1)
            {
                $uids = $this->_evaluate_student($generate_mode, $generate_uid);
                if (!$uids)
                {
                    continue;
                }
            }

            //获取该期考试的考试学科
            $exam_ids = array();
            $exam_subjects = array();
            $this->_exam_subject($g_subject_id, $exam_ids, $exam_subjects);
            if (!$exam_ids)
            {
                continue;
            }

            //将该规则的计划任务设置为 处理中
            if ($status == '0')
            {
                self::$_db->update('rd_cron_task_report', array('status' => 1), "rule_id=" . $rule_id);
            }
            
            if ($rule['generate_subject_report'] == 1)
            {
                sort($uids);
                
                //保存该规则对应的考生
                $this->_save_student($uids, $exam_ids);
                
                foreach ($uids as $uid)
                {
                    foreach ($exam_ids as $exam_id)
                    {
                        $now = time();
                        $subject_id = $exam_id ? $exam_subjects[$exam_id] : 0;
                        $subject_name = $exam_id ? $this->_subject_name($exam_id, $subject_id) : '总结';
                
                        $k = urlencode(base64_encode("{$rule_id}-{$uid}-{$subject_id}"));
                        $source_url = C('public_host_name') . "/report/{$k}.html";
                        $source_path = "zeming/report/{$rule_id}/{$uid}/{$subject_name}.pdf";
                        $target_id = "{$rule_id}_{$uid}_{$subject_id}";
                
                        $convert2pdf_data = array(
                            'type'          => '1',
                            'source_url' 	=> $source_url,
                            'html_status'   => 0,
                            'source_path' 	=> $source_path,
                            'ctime' 		=> $now,
                            'mtime' 		=> $now,
                            'target_id' 	=> $target_id,
                            'uid'           => $uid,
                            'rule_id'       => $rule_id,
                            'exam_pid'      => $exam_pid
                        );
                
                        self::$_db->replace('rd_convert2pdf', $convert2pdf_data);
                        unset($convert2pdf_data);
                    }
                }
            }
            
            //学生成绩单
            if ($rule['generate_transcript'] == 1)
            {
                sort($uids);
                
                foreach ($uids as $uid)
                {
                    foreach ($exam_ids as $exam_id)
                    {
                        $now = time();
                        $subject_id = $exam_id ? $exam_subjects[$exam_id] : 0;
                        if (!$subject_id)
                        {
                            continue;
                        }
                        
                        $subject_name = $exam_id ? $this->_subject_name($exam_id, $subject_id) : '总结';
            
                        $target_id = "{$rule_id}_{$uid}_{$subject_id}_3";
                        $k = urlencode(base64_encode("{$rule_id}-{$uid}-{$subject_id}-3"));
                        $source_url = C('public_host_name') . "/report/{$k}.html";
                        $source_path = "zeming/report/{$rule_id}/transcript_{$uid}/{$subject_name}.pdf";
            
                        $convert2pdf_data = array(
                            'type'          => '1',
                            'source_url' 	=> $source_url,
                            'html_status'   => 0,
                            'source_path' 	=> $source_path,
                            'ctime' 		=> $now,
                            'mtime' 		=> $now,
                            'target_id' 	=> $target_id,
                            'uid'           => $uid,
                            'rule_id'       => $rule_id,
                            'exam_pid'      => $exam_pid
                        );
            
                        self::$_db->replace('rd_convert2pdf', $convert2pdf_data);
                        unset($convert2pdf_data);
                    }
                }
            }
            
            if ($schcls_ids)
            {
                foreach ($schcls_ids as $schcls_id)
                {
                    if (!$schcls_id)
                    {
                        continue;
                    }
                
                    foreach ($exam_ids as $exam_id)
                    {
                        if (!$exam_id)
                        {
                            continue;
                        }
                
                        $now = time();
                        $subject_id = $exam_subjects[$exam_id];
                        $subject_name = $this->_subject_name($exam_id, $subject_id);
                
                        $k = urlencode(base64_encode("{$rule_id}-{$schcls_id}-{$subject_id}-1"));
                        $source_url = C('public_host_name') . "/report/{$k}.html";
                        $source_path = "zeming/report/{$rule_id}/class_{$schcls_id}/{$subject_name}.pdf";
                        $target_id = "{$rule_id}_{$schcls_id}_{$subject_id}_1";
                
                        $convert2pdf_data = array(
                            'type'          => '1',
                            'source_url' 	=> $source_url,
                            'html_status'   => '0',
                            'source_path' 	=> $source_path,
                            'ctime' 		=> $now,
                            'mtime' 		=> $now,
                            'target_id' 	=> $target_id,
                            'uid'           => 0,
                            'rule_id'       => $rule_id,
                            'exam_pid'      => $exam_pid
                        );
                
                        self::$_db->replace('rd_convert2pdf', $convert2pdf_data);
                        unset($convert2pdf_data);
                    }
                }
            }
            
            //教师报告记录
            if (!$ct_ids)
            {
                continue;
            }
            
            $sql = "SELECT tstu_ctid, tstu_examid FROM t_teacher_student 
                    WHERE tstu_exampid = " . $exam_pid;
            $stmt = self::$_db->query($sql);
            $teacher_exam = array();
            while ($val = $stmt->fetch(PDO_DB::FETCH_ASSOC))
            {
                $teacher_exam[$val['tstu_ctid']][] = $val['tstu_examid'];
            }
            
            foreach ($ct_ids as $ct_id)
            {
                if (!$ct_id)
                {
                    continue;   
                }
                
                foreach ($exam_ids as $exam_id)
                {
                    if (!$exam_id 
                        || !in_array($exam_id, $teacher_exam[$ct_id]))
                    {
                        continue;
                    }
            
                    $now = time();
                    $subject_id = $exam_subjects[$exam_id];
                    $subject_name = $this->_subject_name($exam_id, $subject_id);
            
                    $k = urlencode(base64_encode("{$rule_id}-{$ct_id}-{$subject_id}-2"));
                    $source_url = C('public_host_name') . "/report/{$k}.html";
                    $source_path = "zeming/report/{$rule_id}/teacher_{$ct_id}/{$subject_name}.pdf";
                    $target_id = "{$rule_id}_{$ct_id}_{$subject_id}_2";
            
                    $convert2pdf_data = array(
                        'type'          => '1',
                        'source_url' 	=> $source_url,
                        'html_status'   => '0',
                        'source_path' 	=> $source_path,
                        'ctime' 		=> $now,
                        'mtime' 		=> $now,
                        'target_id' 	=> $target_id,
                        'uid'           => 0,
                        'rule_id'       => $rule_id,
                        'exam_pid'      => $exam_pid
                    );
            
                    self::$_db->replace('rd_convert2pdf', $convert2pdf_data);
                    unset($convert2pdf_data);
                }
            }
        }
        
        $bOk = self::$_db->commit();
        if (!$bOk)
        {
            self::$_db->rollBack();
        }
        
        return $bOk;
    }

    /**
     * 生成测评报告模板->html
     */
    public function general_to_html()
    {
        //获取待生成html的记录
        $sql = "SELECT target_id FROM rd_convert2pdf 
                WHERE html_status = 0
                AND exam_pid = (
                    SELECT MIN(exam_pid) FROM rd_convert2pdf 
                    WHERE html_status = 0
                )";
        $target_ids = self::$_db->fetchCol($sql);
        if (!$target_ids) 
        {
            return false;
        }
        
        $zmcat_studyplus_enabled = C('zmcat_studyplus_enabled');

        self::$_db->update('rd_convert2pdf', array('html_status'=>time()), 
                "target_id IN ('" . implode("','", $target_ids) . "')");
        
        foreach ($target_ids as $target_id) 
        {
            $target_array = explode('_', $target_id);
            
            unset($uid);        //学生
            unset($stu_id);     //学生
            unset($schcls_id);  //班级
            unset($ct_id);      //教师
            
            self::$_uid = 0;
            self::$_ct_id = 0;
            self::$_schcls_id = 0;
            self::$_stu_id = 0;
            
            if (count($target_array) == 4
                && end($target_array) == 1)
            {
                @list($rule_id, $schcls_id, $subject_id) = $target_array;
            }
            else if (count($target_array) == 4
                && end($target_array) == 2)
            {
                @list($rule_id, $ct_id, $subject_id) = $target_array;
            }
            else if (count($target_array) == 4
                && end($target_array) == 3)
            {
                @list($rule_id, $stu_id, $subject_id) = $target_array;
            }
            else
            {
                @list($rule_id, $uid, $subject_id) = $target_array;
            }
            
            //评估规则
            $rule = $this->_rule($rule_id);
            if (!$rule) 
            {
                continue;
            }
            
            if ((!$rule['generate_subject_report'] && $uid)
                || (!$rule['generate_transcript'] && $stu_id)
                || (!$rule['generate_class_report'] && $schcls_id)
                || (!$rule['generate_teacher_report'] && $ct_id))
            {
                continue;
            }

            //评估模板
            $this->_evaluate_template($rule_id);
            if (empty(self::$_template[$rule_id]) 
                && ($uid || $schcls_id || $ct_id)) 
            {
                continue;
            }

            $exam_id = 0;
            if ($subject_id > 0 
                && !($exam_id = $this->_subject_exams($rule['exam_pid'], $subject_id))) 
            {
                continue;
            }
            
            self::$_data = array();

            //班级名称
            if (isset($schcls_id))
            {
                self::$_schcls_id = $schcls_id;
                if (empty(self::$_class_name[$schcls_id]))
                {
                    $this->_class_name($schcls_id);
                }
                self::$_data['className'] = self::$_class_name[$schcls_id];
            }
            //教师姓名
            else if (isset($ct_id))
            {
                self::$_ct_id = $ct_id;
                if (empty(self::$_teacher_name[$ct_id]))
                {
                    $this->_teacher_name($ct_id);
                }
                self::$_data['teacherName'] = self::$_teacher_name[$ct_id];
            }
            //学生姓名
            else if (isset($stu_id))
            {
                self::$_stu_id = $stu_id;
                if (empty(self::$_studnet_name[$stu_id]))
                {
                    $this->_student_name($stu_id);
                }
                self::$_data['studentName'] = self::$_studnet_name[$stu_id];
            }
            //学生姓名
            else 
            {
                self::$_uid = $uid;
                if (empty(self::$_studnet_name[$uid]))
                {
                    $this->_student_name($uid);
                }
                self::$_data['studentName'] = self::$_studnet_name[$uid];
            }
            
            //开始生成html静态页面
            self::$_rule_id = $rule_id;
            self::$_exam_pid = $rule['exam_pid'];
            self::$_exam_id = $exam_id;
            self::$_place_id = $rule['place_id'];

            self::$_data['no_paging'] = false;
            self::$_data['ctime'] = date('Y-m-d H:i:s');
            self::$_data['examName'] = $rule['exam_name'];
            self::$_data['grade_id'] = $rule['grade_id'];
            self::$_data['t_subject_id'] = $subject_id;
            self::$_data['subject_name'] = $subject_id ? C('subject/' . $subject_id) : '总结';
            self::$_data['zmcat_studyplus_enabled'] = $zmcat_studyplus_enabled;
            
            self::$_data['is_contrast'] = $rule['contrast_exam_pid'];
            
            $output = '';
            
            if ($subject_id == 0) 
            {
                if (!$this->_exam_time_complex()) 
                {
                    continue;
                }
                
                $output = $this->_general_summary_html();
            } 
            else 
            {
                if (!$stu_id && !$this->_exam_time()) 
                {
                    continue;
                }
                
                self::$_data['subject_name'] = $this->_subject_name($exam_id, $subject_id);
                
                if (isset($schcls_id))
                {
                    if (in_array($subject_id , array(12, 18)))
                    {
                        self::$_data['rule_id'] = $rule_id;
                        self::$_data['exam_pid'] = $rule['exam_pid'];
                        self::$_data['exam_id'] = $exam_id;
                        self::$_technology_data['class'][$schcls_id][$subject_id] = self::$_data;
                    
                        continue;
                    }
                    else
                    {
                        $output = $this->_general_class_html();
                    }
                }
                else if (isset($ct_id))
                {
                    $output = $this->_general_teacher_html();
                }
                else if (isset($uid))
                {
                    self::$_place_id = NULL;
                    if (in_array($subject_id , array(12, 18)))
                    {
                        self::$_data['rule_id'] = $rule_id;
                        self::$_data['exam_pid'] = $rule['exam_pid'];
                        self::$_data['exam_id'] = $exam_id;
                        self::$_data['place_id'] = $rule['place_id'];
                        self::$_technology_data['student'][$uid][$subject_id] = self::$_data;
                        
                        continue;
                    }
                    else
                    {
                        $output = $this->_general_subject_html();
                    }
                } 
                else if (isset($stu_id))
                {
                    $output = $this->_general_transcript_html();
                }
            }
            
            $result = false;
            if ($output)
            {
                //保存html模板
                if (isset($schcls_id))
                {
                    $result = $this->_put_html_content($rule_id, $subject_id, 'class_' . $schcls_id, $output);
                }
                else if (isset($ct_id))
                {
                    $result = $this->_put_html_content($rule_id, $subject_id, 'teacher_' . $ct_id, $output);
                }
                else if (isset($uid))
                {
                    $result = $this->_put_html_content($rule_id, $subject_id, $uid, $output);
                }
                else if (isset($stu_id))
                {
                    $result = $this->_put_html_content($rule_id, $subject_id, 'transcript_' . $stu_id, $output);
                }
            }
            
            self::$_db->update('rd_convert2pdf', array('html_status'=>($result ? 1 : 0)),
                "target_id = '$target_id'");
        }
        
        if (self::$_technology_data)
        {
            if (isset(self::$_technology_data['student']))
            {
                $this->_general_technology_subject_html();
            }
            
            if (isset(self::$_technology_data['class']))
            {
                $this->_general_technology_class_html();
            }
            
            self::$_technology_data = array();
        }

        self::$_data = array();
        self::$_rule = array();
        self::$_studnet_name = array();
        self::$_subject_exams = array();
        self::$_template = array();
    }

    /**
     * 生成测评报告模板->html
     */
    public function general_to_html_demo($exam_subject_id = 3)
    {
        //获取待生成html的记录
        $sql = "SELECT target_id FROM rd_convert2pdf WHERE html_status = 0 limit 1000";
        $list = self::$_db->fetchAssoc($sql);

        if (!$list) {
            return false;
        }

        $target_ids = array_keys($list);
        unset($list);
        
        /* self::$_db->update('rd_convert2pdf', array('html_status'=>time()), 
                "target_id IN ('" . implode("','", $target_ids) . "')"); */
        
        foreach ($target_ids as $target_id) {

            @list($rule_id, $uid, $subject_id) = explode('_', $target_id);
            
            //评估规则
            $rule = $this->_rule($rule_id);

            if (!$rule) {
                continue;
            }

            //评估模板
            $this->_evaluate_template($rule_id);

            if (empty(self::$_template[$rule_id])) {
                continue;
            }

            $exam_id = 0;

            if ($subject_id > 0 && !($exam_id = $this->_subject_exams($rule['exam_pid'], $subject_id))) {
                continue;
            }

            //学生姓名
            if (empty(self::$_studnet_name[$uid])) {
                $this->_student_name($uid);
            }
            
            //开始生成html静态页面
            self::$_rule_id = $rule_id;
            self::$_exam_pid = $rule['exam_pid'];
            self::$_uid = $uid;
            self::$_exam_id = $exam_id;
            self::$_place_id = $rule['place_id'];

            self::$_data = array();

            self::$_data['studentName'] = self::$_studnet_name[$uid];
            self::$_data['ctime'] = date('Y-m-d H:i:s');
            self::$_data['examName'] = $rule['exam_name'];
            self::$_data['t_subject_id'] = $subject_id;
            self::$_data['subject_name'] = $subject_id == '0' ? '总结' : C('subject/'.$subject_id);

            if ($subject_id == 0) {
                if (!$this->_exam_time_complex()) {
                    continue;
                }

                $output = $this->_general_summary_html();
            } else {
                if (!$this->_exam_time()) {
                    continue;
                }

                $output = $this->_general_subject_html();
            }

            if ($subject_id == $exam_subject_id) {
                echo $output;exit;
            }
        }
    
    }

    /**
     * 获取评估规则
     * @param   int  $rule_id
     * @return  multitype:
     */
    private function _rule($rule_id)
    {
        //获取该规则信息
        if (!isset(self::$_rule[$rule_id])) {
            $sql = "SELECT er.exam_pid, e.exam_name, er.place_id, e.grade_id,
                   er.generate_subject_report, er.generate_transcript,
                   er.generate_class_report, er.generate_teacher_report,
                   contrast_exam_pid , er.subject_id
                   FROM rd_evaluate_rule er
                   LEFT JOIN rd_exam e ON e.exam_id = er.exam_pid 
                   LEFT JOIN rd_cron_task_exam_result cter ON cter.exam_pid = e.exam_id
                   WHERE er.id = {$rule_id} AND er.is_delete = 0 AND cter.status = 4";

            $rule = self::$_db->fetchRow($sql);
            self::$_rule[$rule_id] = $rule;
        }

        $rule = self::$_rule[$rule_id];

        return $rule;
    }
    
    /**
     * 学科名称
     */
    private function _subject_name($exam_id, $subject_id)
    {
        $subject_name = C('subject/'.$subject_id);
        
        //综合
        if ($subject_id == 11)
        {
            $sql = "SELECT DISTINCT(subject_id_str) 
                    FROM rd_summary_paper_question spq
                    LEFT JOIN rd_question q ON q.ques_id = spq.ques_id 
                    WHERE spq.exam_id = $exam_id AND spq.subject_id = $subject_id";
            $subject_id_strs = self::$_db->fetchCol($sql);
            $subject_name = array();
            if ($subject_id_strs)
            {
                $subject_map = C('subject');
                
                foreach ($subject_id_strs as $subject_id_str)
                {
                    if (!$subject_id_str)
                    {
                        continue;
                    }
                    
                    $subject_ids = explode(',', trim($subject_id_str, ','));
                    sort($subject_ids);
                    foreach ($subject_ids as $subject_id)
                    {
                        if ($subject_id == 11)
                        {
                            continue;
                        }
                        
                        $subject_name[$subject_id] = $subject_map[$subject_id];
                    }
                }
                
                $subject_name = array_filter($subject_name);
                if (count($subject_name) > 2)
                {
                    $end_subject = array_pop($subject_name);
                    $subject_name = implode('、', array_filter($subject_name)) . '和' . $end_subject;
                }
                else
                {
                    $subject_name = implode('、', array_filter($subject_name));
                }
            }
            
            if (!$subject_name)
            {
                $subject_name = C('subject/11');
            }
        }
        
        return $subject_name;
    }
    
    /**
     * 考场班级
     * @param   int     $exam_pid
     * @param   int     $place_id
     * @return  array   $schcls_ids
     */
    private function _place_class($exam_pid, $place_id)
    {
        if (!$exam_pid)
        {
            return array();
        }
        
        $sql = "SELECT place_schclsid FROM rd_exam_place
                WHERE exam_pid = $exam_pid";
        if ($place_id > 0)
        {
            $sql .= " AND place_id = $place_id";
        }

        return self::$_db->fetchCol($sql);
    }
    
    /**
     * 学生任课教师
     * @param   int     $exam_pid
     * @param   int     $place_id
     * @return  array   $ct_ids
     */
    private function _teacher($exam_pid)
    {
        if (!$exam_pid)
        {
            return array();
        }
    
        $sql = "SELECT DISTINCT(tstu_ctid) FROM t_teacher_student
                WHERE tstu_exampid = $exam_pid";
        
        return self::$_db->fetchCol($sql);
    }

    /**
     * 获取需要生产报告的学生
     * @param    int    $generate_mode  报告生成模式
     * @param    int    $generate_uid   单人模式生成报告的UID
     * @return   array  $uids
     */
    private function _evaluate_student($generate_mode = 0, $generate_uid = 0)
    {
        $uids = array();

        if ($generate_mode == '1')
        {   //单人模式
            //检查该学生是否作弊
            $sql = "SELECT COUNT(*) FROM rd_exam_test_paper WHERE uid = $generate_uid
                    AND etp_flag = -1 AND exam_pid = " . self::$_exam_pid;
            
            if (!self::$_db->fetchOne($sql))
            {
                $uids[] = $generate_uid;
            }
        }
        else
        {   //批量模式
            //获取已生成成绩未作弊的考生
            $band = array(self::$_exam_pid, 2);
            
            $sql = "SELECT DISTINCT(uid) FROM rd_exam_test_paper WHERE
                    exam_pid = ? AND etp_flag = ?";
            
            if (self::$_place_id > 0)
            {
                $band[] = self::$_place_id;
                $sql .= " AND place_id = ?";
            }
            
            $uids = array_keys(self::$_db->fetchAssoc($sql, $band));
        }

        return $uids;
    }

    /**
     * 考试学科对应的exam_id
     * @param   int    $exam_pid     考试期次id
     * @param   int    $subject_id   学科
     * @return  void
     */
    private function _subject_exams($exam_pid, $subject_id)
    {
        if (empty(self::$_subject_exams["{$exam_pid}_{$subject_id}"]))
        {
            $sql = "SELECT exam_id FROM rd_exam WHERE exam_pid=$exam_pid AND subject_id=$subject_id";
            $exam_id = self::$_db->fetchOne($sql);
            if (!$exam_id)
            {
                return false;
            }

            self::$_subject_exams["{$exam_pid}_{$subject_id}"] = $exam_id;
        }

        $exam_id = (int) self::$_subject_exams["{$exam_pid}_{$subject_id}"];

        if ($exam_id < 1) {
            return false;
        }

        return $exam_id;
    }

    /**
     * 考试的学科
     * @param   int    $exam_pid      考试期次id
     * @param   int    $g_subject_id  规则所对应的学科
     * @param   array  $exam_ids      考试学科
     * @param   array  $exam_subjects 考试id对应的学科
     */
    private function _exam_subject($g_subject_id, &$exam_ids, &$exam_subjects)
    {
        if (self::$_place_id > 0)
        {
            $sql = "SELECT subject_id, exam_id FROM rd_exam_place_subject 
                    WHERE place_id = " . self::$_place_id;
        }
        else
        {
            $sql = "SELECT subject_id, exam_id FROM rd_exam 
                    WHERE exam_pid = " . self::$_exam_pid;
        }
        
        $query = self::$_db->query($sql);

        $subject_exams = array();
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            if (in_array($item['subject_id'], array(13, 14, 15, 16)))
            {
                continue;
            }
            
            $subject_exams[$item['subject_id']] = $item['exam_id'];
            $exam_subjects[$item['exam_id']] = $item['subject_id'];
        }
        
        if ($g_subject_id == 0)
        {
            //总结
            $exam_ids = array_values($subject_exams);
            $exam_ids[] = 0;
        }
        else
        {
            //单学科
            if (empty($subject_exams[$g_subject_id]))
            {
                return false;
            }

            $exam_ids[] = $subject_exams[$g_subject_id];
        }
    }

    /**
     * 生成学科报告
     * @param array     $template_module
     * @return string
     */
    private function _general_subject_html()
    {
        $this->load->model('cron/report/subject_report/subject_comparison_info_model', 'sci_model');
        $this->load->model('cron/report/subject_report/subject_three_dimensional_model', 'std_model');
        $this->load->model('cron/report/subject_report/subject_question_model', 'sq_model');
        $this->load->model('cron/report/subject_report/subject_suggest_model', 'ss_model');
        $this->load->model('cron/report/subject_report/push_course_model', 'pc_model');

        $data = self::$_data;

        $t_subject_id = $data['t_subject_id'];
        $rule_id = self::$_rule_id;
        $exam_pid = self::$_exam_pid;
        $exam_id = self::$_exam_id;
        $place_id = self::$_place_id;
        $uid = self::$_uid;
        $output = '';

        $template_module = self::$_template[$rule_id];

        if (!empty($template_module[$t_subject_id]['module'])
            && $template_module[$t_subject_id]['template_type'] == 1)
        {
            $data['template_id'] = $template_module[$t_subject_id]['template_type'];

            $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
            if ($include_subject && !in_array($t_subject_id, $include_subject))
            {
                continue;
            }

            
            $output = $this->load->view('report/subject_module/subject', $data, true);
            
            $g_sort = 0;
            foreach ($template_module[$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }

                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if ($value['module_type'] != 1)
                    {
                        continue;
                    }

                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));

                    if ($value['module_subjectid'])
                    {
                        $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                        if ($module_subjectids
                            && !in_array($t_subject_id, $module_subjectids))
                        {
                            continue;
                        }
                    }

                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
                    
                    if ($module_code == 'sci_exam_info')
                    {
                        $data['sci_exam_info'] = $this->sci_model->module_exam_info($exam_pid, $t_subject_id);// 考试说明
                    }
                    else if ($module_code == 'ss_match_percent')
                    {
                        if (empty($data['sq_all']))
                        {
                            $data['sq_all'] = $this->sq_model->module_all($rule_id, $exam_id, $uid);
                        }

                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid, $data['sq_all']['desc_data']);
                    }
                    else if ($module_pcode == 'pc')
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($exam_id, $uid, $t_subject_id, $exam_pid, $place_id);
                    }
                    else
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid);
                    }
                    
                    if (!empty($data[$module_code]))
                    {
                        //显示一级模块
                        if ($c_sort == 0)
                        {
                            $g_sort++;

                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];

                            $output .= $this->load->view("report/subject_module/$module_pcode/$module_pcode", $data, true);
                        }

                        $c_sort++;

                        $data['c_sort'] = $c_sort;
                        $data['module_name'] = $value['module_name'];

                        $output .= $this->load->view("report/subject_module/$module_pcode/$module_code", $data, true);
                    }
                }
            }
            
            $output .= $this->load->view('report/subject_module/footer', $data, true);
        }

        return $output;
    }
    
    /**
     * 生成学科报告
     * @param array     $template_module
     * @return string
     */
    private function _general_transcript_html()
    {
        $rule_id = self::$_rule_id;
        $exam_id = self::$_exam_id;
        $stu_id = self::$_stu_id;
        
        $data = StudentTranscriptModel::studentTranscriptInfo($rule_id, $exam_id, $stu_id);
        
        $output = '';
        if ($data)
        {
            $output = $this->load->view('report/subject_transcript/transcript', $data, true);
        }
        
        return $output;
    }
    
    /**
     * 生成技术学科报告
     * @param array     $template_module
     * @return string
     */
    private function _general_technology_subject_html($is_wirte_file = true, &$html_data)
    {
        $this->load->model('cron/report/subject_report/subject_comparison_info_model', 'sci_model');
        $this->load->model('cron/report/subject_report/subject_three_dimensional_model', 'std_model');
        $this->load->model('cron/report/subject_report/subject_question_model', 'sq_model');
        $this->load->model('cron/report/subject_report/subject_suggest_model', 'ss_model');
        $this->load->model('cron/report/subject_report/push_course_model', 'pc_model');
        
        foreach (self::$_technology_data['student'] as $uid => $all_data)
        {
            if (!$all_data)
            {
                continue;
            }
            
            krsort($all_data);
            
            $subject_ids = array_keys($all_data);
            $tmp_data = array_values($all_data);
        
            $output = '';
            $is_merge = false;
            if (count($all_data) > 1)
            {
                $is_merge = true;
                $output .= $this->load->view('report/subject_module/technology', $tmp_data[0], true);
            }
            else 
            {
                $output .= $this->load->view('report/subject_module/subject', $tmp_data[0], true);
            }
            
            $match_percent = array();
            
            foreach ($tmp_data as $k => $data)
            {
                if ($is_merge)
                {
                    $output .= $this->load->view('report/subject_module/technology_subject', $data, true);
                }
                
                $t_subject_id = $data['t_subject_id'];
                $rule_id = $data['rule_id'];
                $exam_pid = $data['exam_pid'];
                $exam_id = $data['exam_id'];
                $place_id = $data['place_id'];
                
                $template_module = self::$_template[$rule_id];
                
                if (!empty($template_module[$t_subject_id]['module'])
                    && $template_module[$t_subject_id]['template_type'] == 1)
                {
                    $data['template_id'] = $template_module[$t_subject_id]['template_type'];
                
                    $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
                    if ($include_subject && !in_array($t_subject_id, $include_subject))
                    {
                        continue;
                    }
                
                    $g_sort = 0;
                    foreach ($template_module[$t_subject_id]['module'] as $module)
                    {
                        if (empty($module['children']))
                        {
                            continue;
                        }
                
                        $c_sort = 0;
                        foreach ($module['children'] as $value)
                        {
                            if ($value['module_type'] != 1)
                            {
                                continue;
                            }
                
                            $module_code = trim($value['module_code']);
                            $module_pcode = current(explode('_', $module_code));
                
                            if ($value['module_subjectid'])
                            {
                                $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                                if ($module_subjectids
                                    && !in_array($t_subject_id, $module_subjectids))
                                {
                                    continue;
                                }
                            }
                
                            $_module_model = $module_pcode . "_model";
                            $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                            if (!is_object($this->$_module_model)
                                || !method_exists($this->$_module_model, $_module_func))
                            {
                                continue;
                            }
                
                            if ($module_code == 'sci_exam_info')
                            {
                                $data['sci_exam_info'] = $this->sci_model->module_exam_info($exam_pid, $t_subject_id);// 考试说明
                            }
                            else if ($module_code == 'ss_match_percent')
                            {
                                if (empty($data['sq_all']))
                                {
                                    $data['sq_all'] = $this->sq_model->module_all($rule_id, $exam_id, $uid);
                                }
                
                                $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid, $data['sq_all']['desc_data']);
                                $match_percent[] = $data[$module_code];
                            }
                            else if ($module_pcode == 'pc')
                            {
                                $data[$module_code] = $this->$_module_model->$_module_func($exam_id, $uid, $t_subject_id, $exam_pid, $place_id);
                            }
                
                            if (empty($data[$module_code]))
                            {
                                $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $uid);
                            }
                
                            if (!empty($data[$module_code]))
                            {
                                //显示一级模块
                                if ($c_sort == 0)
                                {
                                    $g_sort++;
                
                                    $data['g_sort'] = $g_sort;
                                    $data['parent_module_name'] = $module['module_name'];
                
                                    $output .= $this->load->view("report/subject_module/$module_pcode/$module_pcode", $data, true);
                                }
                
                                $c_sort++;
                
                                $data['c_sort'] = $c_sort;
                                $data['module_name'] = $value['module_name'];
                
                                $output .= $this->load->view("report/subject_module/$module_pcode/$module_code", $data, true);
                            }
                        }
                    }
                }
            }
            
            if ($is_merge && $match_percent)
            {
                $t_data = array();
                foreach ($match_percent as $item)
                {
                    unset($item['percent']);
                    foreach ($item['data'] as $key => $val)
                    {
                        if (!$key)
                        {
                            $t_data[$key] = $val;
                            continue;
                        }
                        
                        foreach ($val as $k => $v)
                        {
                            if (!$k)
                            {
                                $t_data[$key][$k] = $v;
                            }
                            else 
                            {
                                $t_data[$key][$k] += $v;
                            }
                        }
                    }
                }
                
                $data['ss_technology_match_percent'] = array(
                    'data' => $t_data,
                    'percent' => round(end($t_data[1]) / end($t_data[2]) * 100)
                );
                
                $output .= $this->load->view("report/subject_module/ss/ss_technology_match_percent", $data, true);
            }
            
            $output .= $this->load->view('report/subject_module/footer', $data, true);
            if ($is_wirte_file)
            {
                foreach ($subject_ids as $subject_id)
                {
                    $rule_id = $all_data[$subject_id]['rule_id'];
                    $result = $this->_put_html_content($rule_id, $subject_id, $uid, $output);
                    $target_id = $rule_id . '_' . $uid . '_' . $subject_id;
                    self::$_db->update('rd_convert2pdf', array('html_status'=>($result ? 1 : 0)),
                        "target_id = '$target_id'");
                }
            }
            else
            {
                //默认通用技术作键值
                $html_data[12] = $output;
            }
        }
        
        unset($all_data);
        unset($tmp_data);
        unset($data);
    }
    
    /**
     * 生成班级报告
     * @param array     $template_module
     * @return string
     */
    private function _general_class_html()
    {
        $this->load->model('cron/report/class_report/class_comparison_info_model', 'clsci_model');
        $this->load->model('cron/report/class_report/class_three_dimensional_model', 'clstd_model');
        $this->load->model('cron/report/class_report/class_question_model', 'clsq_model');
        $this->load->model('cron/report/class_report/class_suggest_model', 'clss_model');
    
        $data = self::$_data;
    
        $t_subject_id = $data['t_subject_id'];
        $rule_id = self::$_rule_id;
        $exam_pid = self::$_exam_pid;
        $exam_id = self::$_exam_id;
        $place_id = self::$_place_id;
        $schcls_id = self::$_schcls_id;
        $output = '';
        
        $template_module = self::$_template[$rule_id];
    
        if (!empty($template_module['cls_'.$t_subject_id]['module'])
            && $template_module['cls_'.$t_subject_id]['template_type'] == 4)
        {
            $data['template_id'] = $template_module['cls_'.$t_subject_id]['template_type'];

            $include_subject = array_filter(explode(',', $template_module['cls_'.$t_subject_id]['template_subjectid']));
            if ($include_subject && !in_array($t_subject_id, $include_subject))
            {
                continue;
            }
    
            $output = $this->load->view('report/class_module/class', $data, true);
            $g_sort = 0;
            foreach ($template_module['cls_'.$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }
    
                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if ($value['module_type'] != 4)
                    {
                        continue;
                    }
    
                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));
    
                    if ($value['module_subjectid'])
                    {
                        $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                        if ($module_subjectids
                            && !in_array($t_subject_id, $module_subjectids))
                        {
                            continue;
                        }
                    }
    
                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
    
                    if ($module_code == 'clsci_exam_info')
                    {
                        $data['clsci_exam_info'] = $this->$_module_model->$_module_func($exam_pid, $t_subject_id);// 考试说明
                    }
                    else if ($module_code == 'clss_new_application_situation')
                    {
                        if (!isset($data['clss_contrast_knowledge']))
                        {
                            $data['clss_contrast_knowledge'] = $this->$_module_model->module_contrast_knowledge($rule_id, $exam_id, $schcls_id);
                        }
                        
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $schcls_id, $data['clss_contrast_knowledge']['new_knowledge']);
                    }
                    else
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $schcls_id);
                    }
                    
                    if (!empty($data[$module_code]))
                    {
                        //显示一级模块
                        if ($c_sort == 0)
                        {
                            $g_sort++;
    
                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];
    
                            $output .= $this->load->view("report/class_module/$module_pcode/$module_pcode", $data, true);
                        }
    
                        $c_sort++;
    
                        $data['c_sort'] = $c_sort;
                        $data['module_name'] = $value['module_name'];
    
                        $output .= $this->load->view("report/class_module/$module_pcode/$module_code", $data, true);
                    }
                }
            }
    
            $output .= $this->load->view('report/class_module/footer', $data, true);
        }
        
        return $output;
    }

    /**
     * 生成技术班级报告
     * @param array     $template_module
     * @return string
     */
    private function _general_technology_class_html()
    {
        $this->load->model('cron/report/class_report/class_comparison_info_model', 'clsci_model');
        $this->load->model('cron/report/class_report/class_three_dimensional_model', 'clstd_model');
        $this->load->model('cron/report/class_report/class_question_model', 'clsq_model');
        $this->load->model('cron/report/class_report/class_suggest_model', 'clss_model');
    
        foreach (self::$_technology_data['class'] as $schcls_id => $all_data)
        {
            if (!$all_data)
            {
                continue;
            }
    
            krsort($all_data);
    
            $subject_ids = array_keys($all_data);
            $tmp_data = array_values($all_data);
    
            $output = '';
            $is_merge = false;
            if (count($all_data) > 1)
            {
                $is_merge = true;
                $output .= $this->load->view('report/class_module/technology', $tmp_data[0], true);
            }
            else
            {
                $output .= $this->load->view('report/class_module/class', $tmp_data[0], true);
            }
            
            $match_percent = array();
    
            foreach ($tmp_data as $k => $data)
            {
                if ($is_merge)
                {
                    $output .= $this->load->view('report/class_module/technology_subject', $data, true);
                }
    
                $t_subject_id = $data['t_subject_id'];
                $rule_id = $data['rule_id'];
                $exam_pid = $data['exam_pid'];
                $exam_id = $data['exam_id'];
    
                $template_module = self::$_template[$rule_id];
    
                if (!empty($template_module['cls_'.$t_subject_id]['module'])
                    && $template_module['cls_'.$t_subject_id]['template_type'] == 4)
                {
                    $data['template_id'] = $template_module['cls_'.$t_subject_id]['template_type'];
    
                    $include_subject = array_filter(explode(',', $template_module['cls_'.$t_subject_id]['template_subjectid']));
                    if ($include_subject && !in_array($t_subject_id, $include_subject))
                    {
                        continue;
                    }
    
                    $output = $this->load->view('report/class_module/class', $data, true);
                    $g_sort = 0;
                    foreach ($template_module['cls_'.$t_subject_id]['module'] as $module)
                    {
                        if (empty($module['children']))
                        {
                            continue;
                        }
    
                        $c_sort = 0;
                        foreach ($module['children'] as $value)
                        {
                            if ($value['module_type'] != 4)
                            {
                                continue;
                            }
    
                            $module_code = trim($value['module_code']);
                            $module_pcode = current(explode('_', $module_code));
    
                            if ($value['module_subjectid'])
                            {
                                $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                                if ($module_subjectids
                                    && !in_array($t_subject_id, $module_subjectids))
                                {
                                    continue;
                                }
                            }
    
                            $_module_model = $module_pcode . "_model";
                            $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                            if (!is_object($this->$_module_model)
                                || !method_exists($this->$_module_model, $_module_func))
                            {
                                continue;
                            }
    
                            if ($module_code == 'clsci_exam_info')
                            {
                                $data['clsci_exam_info'] = $this->$_module_model->$_module_func($exam_pid, $t_subject_id);// 考试说明
                            }
                            else if ($module_code == 'clss_new_application_situation')
                            {
                                if (!isset($data['clss_contrast_knowledge']))
                                {
                                    $data['clss_contrast_knowledge'] = $this->$_module_model->module_contrast_knowledge($rule_id, $exam_id, $schcls_id);
                                }
    
                                $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $schcls_id, $data['clss_contrast_knowledge']['new_knowledge']);
                            }
                            else if ($module_code == 'clss_contrast_odds')
                            {
                                if (!isset($data['clss_contrast_difficulty']))
                                {
                                    $data['clss_contrast_difficulty'] = $this->$_module_model->module_contrast_difficulty($rule_id, $exam_id, $schcls_id);
                                }
    
                                $data[$module_code] = $this->$_module_model->$_module_func($data['clss_contrast_difficulty']);
                            }
                            
                            if (empty($data[$module_code]))
                            {
                                $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $schcls_id);
                            }
                            
                            if ($module_code == 'clss_match_percent')
                            {
                                $match_percent[] = $data[$module_code];
                            }
    
                            if (!empty($data[$module_code]))
                            {
                                //显示一级模块
                                if ($c_sort == 0)
                                {
                                    $g_sort++;
    
                                    $data['g_sort'] = $g_sort;
                                    $data['parent_module_name'] = $module['module_name'];
    
                                    $output .= $this->load->view("report/class_module/$module_pcode/$module_pcode", $data, true);
                                }
    
                                $c_sort++;
    
                                $data['c_sort'] = $c_sort;
                                $data['module_name'] = $value['module_name'];
    
                                $output .= $this->load->view("report/class_module/$module_pcode/$module_code", $data, true);
                            }
                        }
                    }
                }
            }
            
            if ($is_merge && $match_percent)
            {
                $t_data = array();
                foreach ($match_percent as $item)
                {
                    unset($item['percent']);
                    foreach ($item['data'] as $key => $val)
                    {
                        if (!$key)
                        {
                            $t_data[$key] = $val;
                            continue;
                        }
            
                        foreach ($val as $k => $v)
                        {
                            if (!$k)
                            {
                                $t_data[$key][$k] = $v;
                            }
                            else
                            {
                                $t_data[$key][$k] += $v;
                            }
                        }
                    }
                }
            
                $data['clss_technology_match_percent'] = array(
                    'data' => $t_data,
                    'percent' => round(end($t_data[1]) / end($t_data[2]) * 100)
                );
            
                $output .= $this->load->view("report/subject_module/clss/clss_technology_match_percent", $data, true);
            }
    
            $output .= $this->load->view('report/class_module/footer', $data, true);
    
            foreach ($subject_ids as $subject_id)
            {
                $rule_id = $all_data[$subject_id]['rule_id'];
                $result = $this->_put_html_content($rule_id, $subject_id, 'class_' . $schcls_id, $output);
                $target_id = $rule_id . '_' . $schcls_id . '_' . $subject_id . '_1';
                self::$_db->update('rd_convert2pdf', array('html_status'=>($result ? 1 : 0)),
                    "target_id = '$target_id'");
            }
        }
        
        unset($all_data);
        unset($tmp_data);
        unset($data);
    }
    
    /**
     * 生成班级报告
     * @param array     $template_module
     * @return string
     */
    private function _general_teacher_html()
    {
        $this->load->model('cron/report/teacher_report/teacher_comparison_info_model', 'tci_model');
        $this->load->model('cron/report/teacher_report/teacher_diagnose_model', 'ttd_model');
        $this->load->model('cron/report/teacher_report/teacher_suggest_model', 'ts_model');
        
        $data = self::$_data;
    
        $t_subject_id = $data['t_subject_id'];
        $rule_id = self::$_rule_id;
        $exam_pid = self::$_exam_pid;
        $exam_id = self::$_exam_id;
        $place_id = self::$_place_id;
        $teacher_id = self::$_ct_id;
        $output = '';
    
        $template_module = self::$_template[$rule_id];
    
        if (!empty($template_module['teacher_'.$t_subject_id]['module'])
            && $template_module['teacher_'.$t_subject_id]['template_type'] == 6)
        {
            $data['template_id'] = $template_module['teacher_'.$t_subject_id]['template_type'];
    
            $include_subject = array_filter(explode(',', $template_module['teacher_'.$t_subject_id]['template_subjectid']));
            if ($include_subject && !in_array($t_subject_id, $include_subject))
            {
                continue;
            }
    
            $output = $this->load->view('report/teacher_module/teacher', $data, true);
            $g_sort = 0;
            foreach ($template_module['teacher_'.$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }
    
                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if ($value['module_type'] != 6)
                    {
                        continue;
                    }
    
                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));
    
                    if ($value['module_subjectid'])
                    {
                        $module_subjectids = array_filter(explode(',', $value['module_subjectid']));
                        if ($module_subjectids
                            && !in_array($t_subject_id, $module_subjectids))
                        {
                            continue;
                        }
                    }
    
                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
    
                    if ($module_code == 'tci_exam_info')
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($exam_pid, $t_subject_id);// 考试说明
                    }
                    else if ($module_code == 'ts_new_knowledge'
                       && !empty($data['ts_contrast_knowledge']['new_knowledge']))
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $teacher_id, $data['ts_contrast_knowledge']['new_knowledge']);
                    }
                    else if ($module_code == 'ts_new_method_tactic'
                        && !empty($data['ts_contrast_method_tactic']['new_method_tactic']))
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $teacher_id, $data['ts_contrast_method_tactic']['new_method_tactic']);
                    }
                    else if ($module_code == 'ts_new_group_type'
                            && !empty($data['ts_contrast_group_type']['new_group_type']))
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $teacher_id, $data['ts_contrast_group_type']['new_group_type']);
                    }
                    else
                    {
                        $data[$module_code] = $this->$_module_model->$_module_func($rule_id, $exam_id, $teacher_id);
                    }
    
                    if (!empty($data[$module_code]))
                    {
                        //显示一级模块
                        if ($c_sort == 0)
                        {
                            $g_sort++;
    
                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];
    
                            $output .= $this->load->view("report/teacher_module/common_module_name", $data, true);
                        }
    
                        $c_sort++;
    
                        $data['c_sort'] = $c_sort;
                        $data['module_name'] = $value['module_name'];
    
                        $output .= $this->load->view("report/teacher_module/$module_pcode/$module_code", $data, true);
                    }
                }
            }
    
            $output .= $this->load->view('report/teacher_module/footer', $data, true);
        }
    
        return $output;
    }

    /**
     * 生成学科总结报告
     * @param  array     $template_module
     * @return string
     */
    private function _general_summary_html()
    {
        $this->load->model('cron/report/subject_report/complex_model', 'c_model');
        $this->load->model('cron/report/subject_report/choose_learn_choose_exam_model', 'clce_model');

        $data = self::$_data;

        $t_subject_id = $data['t_subject_id'];
        $rule_id = self::$_rule_id;
        $output = '';
        $template_module = self::$_template[$rule_id];
        $template_type = $template_module[$t_subject_id]['template_type'];
        //选学选考-初中
        if ($template_type == 5
            && 7 <= $data['grade_id']
            && $data['grade_id'] <= 9)
        {
            $data['is_junior_high'] = true;
        }

        if (!empty($template_module[$t_subject_id]['module'])
            && in_array($template_type, array(0, 5)))
        {

            $data['know_process'] = C('know_process');
            $data['subject'] = C('subject');

            $include_subject = array_filter(explode(',', $template_module[$t_subject_id]['template_subjectid']));
            if ($template_type == 5)
            {
                $output = $this->load->view('report/choose_learn_choose_exam_module/header', $data, true);
            }
            else 
            {
                $output = $this->load->view('report/complex_module/c', $data, true);
            }

            $g_sort = 0;
            foreach ($template_module[$t_subject_id]['module'] as $module)
            {
                if (empty($module['children']))
                {
                    continue;
                }

                $c_sort = 0;
                foreach ($module['children'] as $value)
                {
                    if (!in_array($value['module_type'], array(0, 5)))
                    {
                        continue;
                    }

                    $module_code = trim($value['module_code']);
                    $module_pcode = current(explode('_', $module_code));

                    $_module_model = $module_pcode . "_model";
                    $_module_func = "module" . str_replace($module_pcode . "_", "_", $module_code);
                    if (!is_object($this->$_module_model)
                        || !method_exists($this->$_module_model, $_module_func))
                    {
                        continue;
                    }
                    
                    $data[$module_code] = $this->$_module_model->$_module_func(self::$_rule_id,
                                            self::$_exam_pid, self::$_uid, $include_subject);
                    
                    if (!empty($data[$module_code]))
                    {
                        if ($c_sort == 0)
                        {
                            $g_sort++;

                            $data['g_sort'] = $g_sort;
                            $data['parent_module_name'] = $module['module_name'];
                            if ($template_type == 5)
                            {
                                $output .= $this->load->view("report/choose_learn_choose_exam_module/$module_pcode", $data, true);
                            }
                        }

                        $c_sort++;

                        $data['c_sort'] = $c_sort;

                        $data['module_name'] = $value['module_name'];

                        if ($template_type == 5)
                        {
                            $output .= $this->load->view("report/choose_learn_choose_exam_module/$module_code", $data, true);
                        }
                        else
                        {
                            $output .= $this->load->view("report/complex_module/$module_code", $data, true);
                        }
                    }
                }
            }
            
            if ($template_type == 5)
            {
                $output .= $this->load->view('report/choose_learn_choose_exam_module/footer', $data, true);
            }
            else
            {
                $output .= $this->load->view("report/complex_module/footer", $data, true);
            }
        }

        return $output;
    }

    /**
     * 检查考试是否已结束
     * @return  boolean
     */
    private function _check_exam_status()
    {
        $sql = "SELECT COUNT(*) FROM rd_exam_place 
                WHERE exam_pid = " . self::$_exam_pid . " AND end_time >= " . time();

        return self::$_db->fetchOne($sql);
    }

    private function _exam_time_complex()
    {
        $uid = self::$_uid;
        $exam_pid = self::$_exam_pid;
        $place_id = self::$_place_id;

        /* 判断是否指定考场 */
        if ($place_id && $place_id > 0) {
            $sql = "SELECT start_time,end_time
                    FROM rd_exam_place WHERE place_id={$place_id}";

            $result = self::$_db->fetchRow($sql);
        } else {
            /* 查询所有学科 */
            $sql = "SELECT exam_id FROM rd_exam WHERE exam_pid=$exam_pid";
            $exam_id = self::$_db->fetchCol($sql);

            if (!$exam_id) 
            {
                return false;
            }

            $exam_id_str = implode($exam_id, ',');

            /* 取默认第一个学科 */
            $sql = "SELECT min(ep.start_time) as start_time,max(ep.end_time) as end_time
                    FROM rd_exam_place ep
                    LEFT JOIN rd_exam_test_paper etp ON ep.place_id = etp.place_id
                    WHERE etp.exam_id IN ({$exam_id_str}) AND etp.uid={$uid}";
            $result = self::$_db->fetchRow($sql);
        }

        if (!$result) 
        {
            return false;
        }

        self::$_data['startTime'] = date('Y年m月d日', $result['start_time']);
        self::$_data['endTime'] = date('Y年m月d日', $result['end_time']);
        return true;
    }

    /**
     * 考试考场时间
     * @return boolean  true|false
     */
    private function _exam_time()
    {
        $exam_id = self::$_exam_id;
        $uid = self::$_uid;
        $exam_pid = self::$_exam_pid;

        $sql = "SELECT ep.place_id,ep.place_name,ep.start_time,ep.end_time,ep.exam_time_custom
                FROM rd_exam_place ep
                LEFT JOIN rd_exam_test_paper etp ON ep.place_id = etp.place_id
                WHERE etp.exam_id={$exam_id}";
        if ($uid)
        {
            $sql .= " AND etp.uid={$uid}";
        }
        $result = self::$_db->fetchRow($sql);
        if (!$result) 
        {
            return false;
        }

        /* 自定义考试时间 */
        $exam_time_custom_array = json_decode($result['exam_time_custom'], true);
        
        if (isset($exam_time_custom_array[self::$_data['subject_name']])) 
        {
            self::$_data['exam_time_custom'] = $exam_time_custom_array[self::$_data['subject_name']];
        }

        self::$_data['placeName'] = $result['place_name'];
        
        self::$_data['startTime'] = date('Y年m月d日', $result['start_time']);
        self::$_data['endTime'] = date('Y年m月d日', $result['end_time']);

        if (!$uid)
        {
            self::$_data['examStartTime'] = date('H：i', $result['start_time']);
            self::$_data['examEndTime'] = date('H：i', $result['end_time']);
            self::$_data['examTimeInterval'] = $this->timeDiff($result['start_time'], $result['end_time']);
            return true;
        }
        
        //考试时长
        self::$_data['timeInterval'] = $this->timeDiff($result['start_time'], $result['end_time']);

        //开始考试时间
        $sql = "SELECT ctime FROM rd_exam_logs WHERE exam_id={$exam_pid}
                AND uid={$uid} AND place_id=" . intval($result['place_id']) . " AND type=1";
        $startTime = self::$_db->fetchOne($sql);
        if (!empty($startTime))
        {
            $startTime = strtotime($startTime);
            if ($result['start_time'] > $startTime)
            {
                $startTime = $result['start_time'];
            }
        }
        else
        {
            $startTime = $result['start_time'];
        }
        self::$_data['examStartTime'] = date('H：i', $startTime);

        //结束考试时间
        $sql = "SELECT ctime FROM rd_exam_logs WHERE exam_id={$exam_pid} AND uid={$uid}
                AND place_id=" . intval($result['place_id']) . " AND type=2";
        $endTime = self::$_db->fetchOne($sql);
        if (empty($endTime))
        {
            $endTime = $result['end_time'];
        }
        else
        {
            $endTime = strtotime($endTime);
        }
        self::$_data['examEndTime'] = date('H：i', $endTime);

        //考试用时
        self::$_data['examTimeInterval'] = $this->timeDiff($startTime, $endTime);
        if (intval(self::$_data['examTimeInterval']) > intval(self::$_data['timeInterval']))
        {
            self::$_data['examTimeInterval'] = self::$_data['timeInterval'];
        }

        return true;
    }

    /**
     * 评估报告模板信息
     * @param   string     $rule_id    评估规则id
     * @return  array      $template_module
     */
    private function _evaluate_template($rule_id)
    {
        if (empty(self::$_template[$rule_id]))
        {
            $sql = "SELECT template_id FROM rd_cron_task_report WHERE rule_id = $rule_id";
            $template_id = self::$_db->fetchOne($sql);
            if ($template_id)
            {
                $template_data = json_decode($template_id, true);
                foreach ($template_data as $template_subject => $t_id)
                {
                    $template_module[$template_subject] = $this->_evaluate_template_info($t_id);
                }
            }
            
            self::$_template[$rule_id] = $template_module;
        }
    }

    /**
     * 获取评估模板详情
     * @param  int   $template_id   评估模板id
     * @return array $template      模板详情
     */
    private function _evaluate_template_info($template_id)
    {
        if (!$template_id)
        {
            return array();
        }

        $sql = "SELECT template_subjectid, template_type 
                FROM rd_evaluate_template 
                WHERE template_id=$template_id";
        $template = self::$_db->fetchRow($sql);
        
        if ($template)
        {
            $sql = "SELECT module_id, module_name, parent_moduleid, module_subjectid,
                    module_code,module_type
                    FROM rd_evaluate_template_module etm
                    LEFT JOIN rd_evaluate_module em ON etm.template_module_id = em.module_id
                    WHERE template_id = $template_id AND status = 1
                    ORDER BY template_module_sort ASC, module_id ASC";
            $module = self::$_db->fetchAll($sql);
            $module_list = array();
            foreach ($module as $item)
            {
                if ($item['parent_moduleid'] == 0)
                {
                    $module_list[] = $item;
                }
            }

            foreach ($module as $item)
            {
                foreach ($module_list as &$val)
                {
                    if ($item['parent_moduleid'] == $val['module_id'])
                    {
                        $val['children'][$item['module_id']] = $item;
                    }
                }
            }

            $template['module'] = $module_list;
        }
        
        return $template;
    }
    
    /**
     * 班级名称
     * @param   int   $schcls_id  生成报告班级id
     */
    private function _class_name($schcls_id)
    {
        $sql = "SELECT CONCAT(sch.school_name, schcls_name) FROM t_school_class 
                LEFT JOIN rd_school sch ON sch.school_id = schcls_schid
                WHERE schcls_id = $schcls_id";
        self::$_class_name[$schcls_id] = self::$_db->fetchOne($sql);
    }
    
    /**
     * 教师名称
     * @param   int   $ct_id  教师id
     */
    private function _teacher_name($ct_id)
    {
        $sql = "SELECT school_name, ct_name FROM t_cteacher
                LEFT JOIN t_cteacher_school ON scht_ctid = ct_id
                LEFT JOIN rd_school s ON scht_schid = s.school_id
                WHERE ct_id = $ct_id";
        self::$_teacher_name[$ct_id] = self::$_db->fetchRow($sql);
    }

    /**
     * 学生姓名
     * @param   int   $uids  生成报告的学生id
     * @return  array   $student_list
     */
    private function _student_name($uid)
    {
        if (isset(self::$_studnet_name[$uid]))
        {
            return self::$_studnet_name[$uid];
        }
        
        $sql = "SELECT CONCAT(last_name,first_name) 
                FROM rd_student WHERE uid = $uid";
        return self::$_studnet_name[$uid] = self::$_db->fetchOne($sql);
    }

    /**
     * 生成html文件
     * @note:
     * 	保存路径：cache/html/{$rule_id}/{uid}/{$subject_id}.html
     */
    private function _put_html_content($rule_id, $subject_id, $uid, $html)
    {
        $html = chr(0xEF).chr(0xBB).chr(0xBF).$html;

        $file_path = realpath(dirname(APPPATH)) . "/cache/html/report/{$rule_id}/{$uid}";
        if (!is_dir($file_path))
        {
            mkdir($file_path, '0777', true);
        }

        $res = file_put_contents($file_path."/{$subject_id}.html", $html);

        return $res > 0;
    }

    /**
     * 保存该规则关联的考生
     * @param int $rule_id
     * @param array $uids
     * @param array $exam_ids
     */
    private function _save_student($uids, $exam_ids)
    {
        $rule_id = intval(self::$_rule_id);
        if (!$rule_id)
        {
            return false;
        }

        $data = array(
                'rule_id' => $rule_id, 
                'uids' => implode(',', $uids), 
                'exam_ids' => implode(',', $exam_ids)
        );

        self::$_db->replace('rd_evaluate_student', $data);
    }

    /**
     * 计算时间差
     *
     * @param string $startTime unix时间戳
     * @param string $entTime unix时间戳
     * @return  string
     */
    private function timeDiff($startTime, $endTime)
    {
        $str = '';
        $timeDiff = abs($endTime - $startTime);
        $minute = ceil($timeDiff/60);

        $str = $minute.'分钟';

        return $str;
    }
    
    /**
     * 生成自由考试报告数据接口
     */
    public function general_free_exam_html($param = array())
    {
        if (empty($param) 
            || empty($param['exam_pid'])
            || empty($param['uid'])
            || empty($param['place_id']))
        {
            throw new Exception('数据传输有误！');
        }
        
        list($uid, $exam_pid, $place_id) = array_values($param);
        
        $sql = "SELECT sfe_uid FROM t_student_free_exam 
                WHERE sfe_exampid = ? AND sfe_uid = ? AND sfe_placeid = ?
                AND sfe_report_status = 2 AND sfe_data IS NOT NULL";
        if (self::$_db->fetchOne($sql, array($exam_pid, $uid, $place_id)))
        {
            return true;
        }
        
        $sql = "SELECT exam_id, subject_id FROM rd_exam_test_paper
                WHERE uid = ? AND exam_pid = ? AND place_id = ?
                ORDER BY subject_id ASC";
        $exam = self::$_db->fetchPairs($sql, array($uid, $exam_pid, $place_id));
        if (!$exam)
        {
            throw new Exception('你没有参加此次考试的记录，请重新参与考试！');
        }
        
        $sql = "SELECT id FROM rd_evaluate_rule 
                WHERE exam_pid = $exam_pid";
        $rule_id = (int) self::$_db->fetchOne($sql);
        
        //评估规则
        $sql = "SELECT er.exam_pid, e.exam_name, er.place_id, e.grade_id
                FROM rd_evaluate_rule er
                LEFT JOIN rd_exam e ON e.exam_id = er.exam_pid 
                WHERE er.id = {$rule_id} AND er.is_delete = 0";
        $rule = self::$_db->fetchRow($sql);
        if (!$rule) 
        {
            throw new Exception('考试没有对应的评估报告生成规则，请联系管理员！');
        }
        
        //评估模板
        $this->_evaluate_template($rule_id);
        
        if (empty(self::$_template[$rule_id])) 
        {
            throw new Exception('考试报告没有对应的生成模板，请联系管理员！');
        }
        
        if (isset(self::$_template[$rule_id][0]))
        {
            $sql = "SELECT exam_id FROM rd_exam_place_subject eps
                    LEFT JOIN t_student_free_exam sfe ON sfe.sfe_placeid = eps.place_id
                    WHERE sfe.sfe_exampid = ? AND sfe_uid = ?
                    AND sfe_report_status = 2";
            $report_exam = self::$_db->fetchCol($sql, array($exam_pid, $uid));
            
            $sql = "SELECT ep.exam_id FROM rd_exam_place_subject ep
                    LEFT JOIN rd_exam_place_student eps ON ep.place_id = eps.place_id
                    WHERE exam_pid = $exam_pid AND eps.uid = $uid";
            $all_exam = self::$_db->fetchCol($sql);            
            
            if ($report_exam)
            {
                if (!array_diff(array_diff($all_exam, $report_exam), array_keys($exam)))
                {
                    $exam[$exam_pid] = 0;
                }
            }
            else 
            {
                if (!array_diff($all_exam, array_keys($exam)))
                {
                    $exam[$exam_pid] = 0;
                }
            }
        }
        
        $student_name = $this->_student_name($uid);
        
        $html_data = array();
        
        $quality_test = false;
        
        self::$_rule_id = $rule_id;
        self::$_exam_pid = $exam_pid;
        self::$_uid = $uid;
        self::$_place_id = $place_id;
        
        $report_subject = array();
        $zmcat_studyplus_enabled = C('zmcat_studyplus_enabled');
        
        foreach ($exam as $exam_id => $subject_id)
        {
            if (in_array($subject_id, array(13, 14, 15, 16)))
            {
                $quality_test = true;
                continue;
            }
            
            $report_subject[] = $subject_id;
            
            self::$_data = array();
            
            self::$_data['no_paging'] = true;
            self::$_data['studentName'] = $student_name;
            self::$_data['ctime'] = date('Y-m-d H:i:s');
            self::$_data['examName'] = $rule['exam_name'];
            self::$_data['t_subject_id'] = $subject_id;
            self::$_data['grade_id'] = $rule['grade_id'];
            self::$_data['zmcat_studyplus_enabled'] = $zmcat_studyplus_enabled;
            
            //开始生成学科html静态页面
            if ($subject_id > 0)
            {
                self::$_exam_id = $exam_id;
                self::$_data['subject_name'] = C('subject/'.$subject_id);
                
                $this->_free_exam_time($uid, $exam_pid, $place_id);
                
                //技术报告特殊处理
                if (in_array($subject_id , array(12, 18)))
                {
                    self::$_data['rule_id'] = $rule_id;
                    self::$_data['exam_pid'] = $rule['exam_pid'];
                    self::$_data['exam_id'] = $exam_id;
                    self::$_data['place_id'] = $rule['place_id'];
                    self::$_technology_data['student'][$uid][$subject_id] = self::$_data;
                
                    continue;
                }
                else 
                {
                    $html_data[$subject_id] = $this->_general_subject_html();
                }
            }
            //开始生成总结html静态页面
            else 
            {
                $this->_free_exam_time_complex();
                
                $html_data[$subject_id] = $this->_general_summary_html();
            }
        }
        
        if (isset(self::$_technology_data['student'])
            && self::$_technology_data['student'])
        {
            $this->_general_technology_subject_html(false, $html_data);
        }
        
        if ($html_data || $quality_test)
        {
            if (C('sfe_data_gz'))
            {
                $bind = array(
                    'sfe_report_status' => 2,
                    'sfe_subjectid'     => $report_subject ? implode(',', $report_subject) : '',
                    'sfe_data'        => gzencode(json_encode($html_data), 9)
                );
            }
            else
            {
                $bind = array(
                    'sfe_report_status' => 2,
                    'sfe_data'          => json_encode($html_data)
                );
            }
            
            $where = 'sfe_exampid = ? AND sfe_uid = ? AND sfe_placeid = ?';
            $where_bind = array($exam_pid, $uid, $place_id);
            self::$_db->update('t_student_free_exam', $bind, $where, $where_bind);
            
            return true;
        }
        else 
        {
            return false;
        }
    }
    
    private function _free_exam_time_complex()
    {
        $uid = self::$_uid;
        $exam_pid = self::$_exam_pid;
    
        $sql = "SELECT MIN(sfe_starttime) AS start_time,MAX(sfe_endtime) AS end_time
                FROM t_student_free_exam
                WHERE sfe_uid = $uid AND sfe_exampid = $exam_pid";
        $result = self::$_db->fetchRow($sql);
        if (!$result)
        {
            return false;
        }
    
        self::$_data['startTime'] = date('Y年m月d日', $result['start_time']);
        self::$_data['endTime'] = date('Y年m月d日', $result['end_time']);
    
        return true;
    }
    
    /**
     * 自由考试考场时间
     * @return boolean  true|false
     */
    private function _free_exam_time($uid, $exam_pid, $place_id)
    {
        if (self::$_data_free_exam_time)
        {
            self::$_data = array_merge(self::$_data, self::$_data_free_exam_time);
        }
        
        $sql = "SELECT place_name FROM rd_exam_place 
                WHERE place_id = $place_id";
        self::$_data_free_exam_time['placeName'] = self::$_db->fetchOne($sql);
    
        $sql = "SELECT sfe_starttime, sfe_endtime 
                FROM t_student_free_exam 
                WHERE sfe_uid = ? AND sfe_exampid = ? AND sfe_placeid = ?";
        $time = self::$_db->fetchRow($sql, array($uid, $exam_pid, $place_id));
    
        self::$_data_free_exam_time['startTime'] = date('Y年m月d日', $time['sfe_starttime']);
        self::$_data_free_exam_time['endTime'] = date('Y年m月d日', $time['sfe_endtime']);
        
        //考试时长
        self::$_data_free_exam_time['timeInterval'] = $this->timeDiff($time['sfe_starttime'], $time['sfe_endtime']);
    
        //开始考试时间
        $sql = "SELECT ctime FROM rd_exam_logs 
                WHERE exam_id={$exam_pid} AND uid = {$uid} 
                AND place_id= $place_id AND type=1";
        $startTime = self::$_db->fetchOne($sql);
        if ($startTime)
        {
            $startTime = strtotime($startTime);
            if ($time['sfe_starttime'] > $startTime
                || $startTime > $time['sfe_endtime'])
            {
                $startTime = $time['sfe_starttime'];
            }
        }
        else
        {
            $startTime = $time['sfe_starttime'];
        }
        
        self::$_data_free_exam_time['examStartTime'] = date('H:i', $startTime);
    
        //结束考试时间
        $sql = "SELECT ctime FROM rd_exam_logs 
                WHERE exam_id = {$exam_pid} AND uid = {$uid}
                AND place_id = $place_id AND type=2";
        $endTime = self::$_db->fetchOne($sql);
        if ($endTime)
        {
            $endTime = strtotime($endTime);
            
            if ($endTime < $time['sfe_starttime'])
            {
                $endTime = $time['sfe_endtime'];
            }
        }
        else
        {
            $endTime = $time['sfe_endtime'];
        }
        
        self::$_data_free_exam_time['examEndTime'] = date('H:i', $endTime);
    
        //考试用时
        self::$_data_free_exam_time['examTimeInterval'] = $this->timeDiff($startTime, $endTime);
        
        if (intval(self::$_data_free_exam_time['examTimeInterval']) > intval(self::$_data_free_exam_time['timeInterval']))
        {
            self::$_data_free_exam_time['examTimeInterval'] = self::$_data_free_exam_time['timeInterval'];
        }
        
        self::$_data = array_merge(self::$_data, self::$_data_free_exam_time);
    
        return true;
    }
}
