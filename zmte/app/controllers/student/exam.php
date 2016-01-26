<?php
class Exam extends S_Controller
{

    private $_uinfo = array();

    /**
     * 构造函数,初始化用户信息
     */
    public function __construct() // {{{
    {
        parent::__construct();
        $this->_uinfo = StudentModel::studentLoginUInfo();
    }
 // }}}
    
    public function index()
    {
        Fn::ajax_call($this, 'logout', 'login');
        $data = array();
        $data['page_title'] = '测评中心';
        $data['uinfo'] = $this->_uinfo;
        
        if ($this->_uinfo['uid'])
        {
            $data['student'] = StudentModel::get_student($this->_uinfo['uid']);
        }
        
        $sql = <<<EOT
SELECT * FROM rd_product_category
EOT;
        $data['pclist'] = Fn::db()->fetchAll($sql);
        $this->load->view('exam/index', $data);
    }

    public function loginFunc($param)
    {
        return StudentModel::studentAjaxLogin($param);
    }

    public function logoutFunc($url = NULL)
    {
        return StudentModel::studentAjaxLogout($url);
    }

    public function mini()
    {
        Fn::ajax_call($this, 'logout', 'login');
        $data = array();
        $data['uinfo'] = $this->_uinfo;
        $this->load->view('exam/mini', $data);
    }

    public function examlist($pc_id)
    {
        Fn::ajax_call($this, 'logout', 'login');
        
        $pc_id = intval($pc_id);
        $data = array();
        $data['page_title'] = '测评中心';
        $data['uinfo'] = $this->_uinfo;
        $data['grade_id'] = intval(isset($_GET['grade_id']) ? $_GET['grade_id'] : 0);
        $data['subject_id'] = intval($_GET['subject_id']);
        if ($this->_uinfo['uid'])
        {
            $data['student'] = StudentModel::get_student($this->_uinfo['uid']);
            if (!isset($_GET['grade_id']))
            {
                $data['grade_id'] = $data['student']['grade_id'];
            }
        }
        
        $sql = <<<EOT
SELECT * FROM rd_product_category
EOT;
        $data['pclist'] = Fn::db()->fetchAll($sql);
        
        $page = intval($_GET['page']);
        if ($page < 1)
        {
            $page = 1;
        }
        
        $param = array(
            'pc_id' => $pc_id,
            'p_status' => 1,
            'exam_status' => 1,
            'place_available' => 1
        );
        
        if ($this->_uinfo['uid'])
        {
            $param['stu_uid'] = $this->_uinfo['uid'];
        }
        
        if ($data['grade_id'] > 0)
        {
            $param['grade_id'] = $data['grade_id'];
        }
        
        if ($data['subject_id'] > 0)
        {
            $param['subject_id'] = $data['subject_id'];
        }
        
        $_GET['perpage'] = 10;
        $data['examlist'] = ProductModel::productExamPlaceSubjectPaperList('*', 
                                $param, $page, $_GET['perpage']);
        $data['examlist_count'] = ProductModel::productExamPlaceSubjectPaperListCount($param);
        {
            $p2 = $param;
            unset($p2['stu_uid']);
            unset($p2['subject_id']);
            unset($p2['grade_id']);
            $p2['order_by'] = 'subject_id';
            $data['examlist_subjectlist'] = ProductModel::productExamPlaceSubjectPaperList('DISTINCT subject_id', $p2);

            $p3 = $param;
            unset($p3['stu_uid']);
            unset($p3['subject_id']);
            unset($p3['grade_id']);
            $examlist_gradelist = ProductModel::productExamPlaceSubjectPaperList('DISTINCT grade_id', $p3);
            $data['examlist_gradelist'] = array();
            foreach ($examlist_gradelist as $item)
            {
                $data['examlist_gradelist'][] = $item['grade_id'];
            }
            rsort($data['examlist_gradelist']);
        }
        $data['pc_id'] = $pc_id;
        $data['grades'] = C('grades');
        $data['subject'] = C('subject');
        
        if ($this->_uinfo['uid'])
        {
            $exam_pid_arr = array();
            if (! empty($data['examlist']))
            {
                foreach ($data['examlist'] as $v)
                {
                    $exam_pid_arr[] = $v['exam_pid'];
                }
                
                $exam_pid_arr = array_unique($exam_pid_arr);
                $exam_pid_str = implode(',', $exam_pid_arr);
                
                $uid = $this->_uinfo['uid'];
                $sql = <<<EOT
SELECT DISTINCT(e.exam_id)
FROM rd_exam e
JOIN rd_exam_result_publish erp ON e.exam_id = erp.exam_pid
JOIN rd_exam_place ep ON e.exam_id = ep.exam_pid
JOIN rd_exam_place_student eps ON ep.place_id = eps.place_id
WHERE eps.uid = {$uid} AND e.exam_id IN ({$exam_pid_str})
EOT;
                $exam_pid_published = Fn::db()->fetchCol($sql);
                
                $sql = <<<EOT
SELECT sfe_placeid, sfe_exampid, sfe_report_status 
FROM t_student_free_exam
WHERE sfe_uid = {$uid} AND sfe_exampid IN ({$exam_pid_str})
EOT;
                $free_exam_list = Fn::db()->fetchAssoc($sql);
                foreach ($free_exam_list as $row)
                {
                    $exam_pid_published[] = $row['sfe_exampid'];
                }
                $exam_pid_published = array_unique($exam_pid_published);
            }
        }
        
        if (! is_array($exam_pid_published))
        {
            $exam_pid_published = array();
        }
        
        if (!is_array($free_exam_list))
        {
            $free_exam_list = array();
        }
        $data['exam_pid_published'] = $exam_pid_published;
        $data['free_exam_list'] = $free_exam_list;
        $this->load->view('exam/examlist', $data);
    }

    public function freereport($sfe_uid, $exam_pid, $place_id, $subject_id)
    {
        $uid = $this->_uinfo['uid'];
        if (!$uid)
        {
            redirect('student/exam/index');
        }
        if ($uid != $sfe_uid)
        {
            message('非法的请求', site_url('student/exam/examresult'));
        }
        $sql = <<<EOT
SELECT * FROM t_student_free_exam
WHERE sfe_uid = {$uid} AND sfe_exampid = {$exam_pid} AND sfe_placeid = {$place_id}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if (!$row)
        {
            message('非法的请求', site_url('student/exam/examresult'));
        }
        if (C('sfe_data_gz'))
        {
            $data = json_decode(gzdecode($row['sfe_data']), true);
        }
        else
        {
            $data = json_decode($row['sfe_data'], true);
        }
        if (!isset($data[$subject_id]))
        {
            message('非法的学科请求', site_url('student/exam/examresult'));
        }
        header('Content-type:text/html;charset=UTF-8');
        echo ($data[$subject_id]);
        exit();
    }

    /**
     * 获取显示产品考试须知
     * 
     * @param int $exam_id
     *            考试期次ID
     * @param int $p_id
     *            产品ID
     */
    public function product_notice($exam_id, $p_id)
    {
        $data = array();
        $data['exam_info'] = Fn::db()->fetchRow('SELECT exam_name, student_notice 
            FROM rd_exam WHERE exam_id = ' . intval($exam_id));
        $data['p_info'] = Fn::db()->fetchRow('SELECT p_notice FROM rd_product 
            WHERE p_id = ' . intval($p_id));
        $this->load->view('exam/product_notice', $data);
    }

    /*
     * 获取产品信息进行产品前置检查
     * @param int $exam_id 期次ID
     * @param int $p_id 产品ID
     * @param int $place_id 考场ID
     * @param int $start_test 若为1则立即跳转到考试界面
     */
    public function product_prefixcheck_for_zmexam($uid, $exam_id)
    {
        $db = Fn::db();
        
        $exam_id = intval($exam_id);
        $uid = intval($uid);
        
        $sql = <<<EOT
SELECT p_id, p_prefixinfo FROM rd_product WHERE exam_pid = {$exam_id}
EOT;
        $p_info = $db->fetchRow($sql);
        if (empty($p_info))
        {
            die();
        }
        
        // 基本信息
        $student = $db->fetchRow("SELECT uid FROM rd_student WHERE uid = $uid");
        if (empty($student))
        {
            die();
        }
        
        // 下面开始检查哪些产品前置项没有过
        $prefixinfo_check = StudentModel::registerItemCompleteCheck(
            $uid, $p_info['p_prefixinfo']);
        $bShowCheck = false;
        foreach ($prefixinfo_check as $v)
        {
            if (! $v)
            {
                $bShowCheck = true;
                break;
            }
        }
        header('Access-Control-Allow-Origin: *');
        header('Content-Type:text/plain;charset=UTF-8');
        if ($bShowCheck)
        {
            $product_prefixinfo = C('product_prefixinfo');
            $msg = <<<EOT
同学你好，为保证该场测试的测评报告的全面准确性，需要你后续登录学生个人中心完善
EOT;
            $msg_arr = array();
            foreach ($prefixinfo_check as $k => $v)
            {
                if (! $v)
                {
                    $url = site_url('student/profile/' . str_replace(array(
                        'base',
                        'score',
                        'practice',
                        'selfwish',
                        'parentwish'
                    ), array(
                        'base',
                        'awards',
                        'practice',
                        'wish',
                        'pwish'
                    ), $k));
                    $msg_arr[] = '<b><font color="red">' . $product_prefixinfo[$k] . '</font></b>';
                }
            }
            $msg .= implode('、', $msg_arr) . '。';
            echo (strip_tags($msg));
        }
        else
        {
            echo ('success');
        }
        exit();
    }

    /*
     * 获取产品信息进行产品前置检查
     * @param int $exam_id 期次ID
     * @param int $p_id 产品ID
     * @param int $place_id 考场ID
     * @param int $start_test 若为1则立即跳转到考试界面
     */
    public function product_prefixcheck($exam_id, $p_id, $place_id = NULL, $start_test = NULL)
    {
        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['exam_id'] = $exam_id;
        $data['p_id'] = $p_id;
        $data['place_id'] = $place_id;
        $data['start_test'] = $start_test;
        if ($uid)
        {
            $db = Fn::db();
            // 基本信息
            $student = StudentModel::get_student($uid);
            $data['student'] = $student;
            
            $sql = <<<EOT
SELECT a.*, b.exam_isfree
FROM v_production a 
LEFT JOIN rd_exam b ON a.exam_pid = b.exam_id
WHERE a.p_id = {$p_id}
EOT;
            $data['product_info'] = Fn::db()->fetchRow($sql);
            
            // 下面开始检查哪些产品前置项没有过
            $prefixinfo = $data['product_info']['p_prefixinfo'];
            $data['prefixinfo_check'] = StudentModel::registerItemCompleteCheck($uid, $prefixinfo);
            $bShowCheck = false;
            foreach ($data['prefixinfo_check'] as $v)
            {
                if (! $v)
                {
                    $bShowCheck = true;
                    break;
                }
            }
            $data['product_prefixinfo'] = C('product_prefixinfo');
            $data['show_check'] = $bShowCheck;
        }
        $this->load->view('exam/place_prefixcheck', $data);
    }

    /*
     * 获取期次下考场信息
     * @param int $exam_id 期次ID
     * @param int $p_id 产品ID
     * @param int $place_id 场次ID
     */
    public function examplace($exam_id, $p_id, $place_id = NULL)
    {
        $uid = $this->_uinfo['uid'];
        $data = array();
        if ($uid)
        {
            $db = Fn::db();
            // 基本信息
            $student = StudentModel::get_student($uid);
            $data['student'] = $student;
            $time = time();
            $sql = <<<EOT
SELECT a.*, b.exam_isfree 
FROM rd_exam_place a LEFT JOIN rd_exam b ON a.exam_pid = b.exam_id
WHERE a.exam_pid = {$exam_id} AND a.ip = '' 
    AND ((b.exam_isfree = 0 AND a.start_time >= {$time}) OR b.exam_isfree = 1)
EOT;
            if ($place_id)
            {
                $sql .= " AND place_id = $place_id";
            }

            $exam_place_list = array();
            $rows = $db->fetchAll($sql);
            foreach ($rows as $v)
            {
                if ($this->check_place_status($v['place_id'], $exam_id, $uid))
                {
                    $v['duration_minute'] = ($v['end_time'] - $v['start_time']) / 60;
                    $v['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                    $v['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                    $exam_place_list[] = $v;
                }
            }
            $data['exam_place_list'] = $exam_place_list;
            
            $sql = <<<EOT
SELECT * FROM rd_exam WHERE exam_id = {$exam_id}
EOT;
            $data['exam_info'] = $db->fetchRow($sql);
            $data['exam_id'] = $exam_id;
            $data['p_id'] = $p_id;
            
            $sql = <<<EOT
SELECT * FROM v_production WHERE p_id = {$p_id}
EOT;
            $data['product_info'] = Fn::db()->fetchRow($sql);
            
            /*
             * 产品前置检查放到报名后
             * // 下面开始检查哪些产品前置项没有过
             * $prefixinfo = $data['product_info']['p_prefixinfo'];
             * $data['prefixinfo_check'] =
             * StudentModel::registerItemCompleteCheck($uid, $prefixinfo);
             * $bShowCheck = false;
             * foreach ($data['prefixinfo_check'] as $v)
             * {
             * if (!$v)
             * {
             * $bShowCheck = true;
             * break;
             * }
             * }
             * $data['product_prefixinfo'] = C('product_prefixinfo');
             * if ($bShowCheck)
             * {
             * $this->load->view('exam/place_prefixcheck', $data);
             * }
             * else
             */
            {
                $this->load->view('exam/examplace', $data);
            }
        }
        else
        {
            $this->load->view('exam/examplace', $data);
        }
    }

    /**
     * TODO 应该将该段去除掉并替换成新方式
     * ription 检查当前考场信息
     * 
     * @param int $exam_pid
     *            期次id
     * @param int $place_id
     *            场次id
     * @param int $uid
     *            用户id
     * @return bool 成功/失败
     */
    private function check_place_status($place_id, $exam_pid, $uid)
    {
        $result = Fn::db()->fetchRow("select count(*) as count from rd_product p
                    LEFT JOIN t_transaction_record tr ON tr.tr_pid = p.p_id
                    where p.exam_pid = '$exam_pid' and tr.tr_uid='$uid'");
        if ($result['count'])
        {
            return false;
        }
        
        $result = Fn::db()->fetchRow("select count(*) as count from rd_exam_place_subject 
            where place_id={$place_id} and exam_pid = $exam_pid");
        
        if (! $result['count'])
        {
            $message[] = '未选择学科(<font color="red">请确认下该考场所在的考试期次下是否已添加 学科</font>)';
            
            return false;
        }
        else
        {
            $result = Fn::db()->fetchRow(
                "select count(*) as count from rd_exam_place_subject 
                where place_id={$place_id} and exam_pid =  $exam_pid");
            
            $result2 = Fn::db()->fetchRow(
                "select count(distinct(eps.exam_id)) as count 
                from rd_exam_place_subject eps, rd_exam_subject_paper esp 
                where eps.place_id={$place_id} and eps.exam_id=esp.exam_id and eps.exam_pid = $exam_pid");
            if ($result['count'] > $result2['count'])
            {
                $message[] = '有学科未选择试卷';
                return false;
            }
            
            $result = Fn::db()->fetchAll(
                "select count(*) as count,eps.subject_id,esp.paper_id 
                from rd_exam_place_subject eps, rd_exam_subject_paper esp 
                where eps.place_id={$place_id}
                and eps.exam_pid = {$exam_pid} and eps.exam_id=esp.exam_id 
                and esp.paper_id not in(SELECT paper_id from rd_exam_paper ep 
                    left join rd_exam e on e.exam_id = ep.exam_id
                    where e.exam_pid = $exam_pid) 
                group by esp.paper_id");
            
            $subjects = C('subject');
            foreach ($result as $key => $val)
            {
                $message[] = '学科[' . $subjects[$val['subject_id']] . ']试卷ID【' . 
                $val['paper_id'] . '】不存在';
                return false;
                break;
            }
        }
        return true;
    }

    /**
     * 考试成绩查询
     */
    public function examresult()
    {
        Fn::ajax_call($this, 'logout', 'login');
        
        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['page_title'] = '考试成绩';
        $data['uinfo'] = $this->_uinfo;
        if (! $uid)
        {
            redirect('student/exam/index');
        }
        else
        {
            $data['student'] = StudentModel::get_student($this->_uinfo['uid']);
            
            // 检查学生信息是否完善
            // $check_message = $this->check_perfect_student();
            
            if ($check_message)
            {
                $data['check_message'] = $check_message;
            }
            else
            {
                $sql = <<<EOT
SELECT DISTINCT(e.exam_id), e.exam_name
FROM rd_exam e
JOIN rd_exam_result_publish erp ON e.exam_id = erp.exam_pid
JOIN rd_exam_place ep ON e.exam_id = ep.exam_pid
JOIN rd_exam_place_student eps ON ep.place_id = eps.place_id
WHERE eps.uid = {$uid}
EOT;
                $exam_list = Fn::db()->fetchAssoc($sql);
                if (! empty($exam_list))
                {
                    $exam_pid_str = implode(',', array_keys($exam_list));
                    $sql = <<<EOT
SELECT exam_pid, id, subject_id FROM rd_evaluate_rule 
WHERE exam_pid IN ({$exam_pid_str})
ORDER BY subject_id
EOT;
                    $rule_list = Fn::db()->fetchAll($sql);
                    foreach ($rule_list as $v)
                    {
                        $source_path = $v['id'] . "/$uid.zip";
                        $filepath = realpath(dirname(APPPATH)) . "/cache/zip/report/" . $source_path;
                        if (! file_exists($filepath))
                        {
                            //continue;
                        }
                        
                        if (! isset($exam_list[$v['exam_pid']]['list']))
                        {
                            $exam_list[$v['exam_pid']]['list'] = array();
                        }
                        
                        $v['subject_name'] = $this->_subject_name($uid, $v['exam_pid'], $v['subject_id']);
                        
                        $exam_list[$v['exam_pid']]['list'][] = $v;
                    }
                    asort($exam_list);
                }
                $data['exam_result_list'] = array_values($exam_list);
                $data['subject'] = C('subject');
                $data['subject'][0] = '总结';
                
                if (C('sfe_data_gz'))
                {
                    $sql = <<<EOT
SELECT sfe_uid, sfe_exampid, sfe_placeid, sfe_starttime, sfe_endtime, 
    sfe_report_status, sfe_subjectid,
    b.exam_name, c.place_name
FROM t_student_free_exam a
LEFT JOIN rd_exam b ON a.sfe_exampid = b.exam_id
LEFT JOIN rd_exam_place c ON a.sfe_placeid = c.place_id
WHERE sfe_uid = {$uid}
ORDER BY sfe_exampid DESC, sfe_placeid ASC
EOT;
                    $rows = Fn::db()->fetchAll($sql);
                    $exam_free_list = array();
                    $exam_free_map = array();
                    foreach ($rows as $row)
                    {
                        $exam_pid = $row['sfe_exampid'];
                        $exam_free_list[] = $exam_pid;
                        if (!isset($exam_free_map[$exam_pid]))
                        {
                            $exam_free_map[$exam_pid] = array();
                        }
                        $row['subject'] = explode(',', trim($row['sfe_subjectid']));
                        $exam_free_map[$exam_pid][] = $row;
                    }
                    $data['exam_free_list'] = array_unique($exam_free_list);
                    $data['exam_free_map'] = $exam_free_map;

                }
                else
                {
                    $sql = <<<EOT
SELECT sfe_uid, sfe_exampid, sfe_placeid, sfe_starttime, sfe_endtime, 
    sfe_report_status, sfe_data,
    b.exam_name, c.place_name
FROM t_student_free_exam a
LEFT JOIN rd_exam b ON a.sfe_exampid = b.exam_id
LEFT JOIN rd_exam_place c ON a.sfe_placeid = c.place_id
WHERE sfe_uid = {$uid}
ORDER BY sfe_exampid DESC, sfe_placeid ASC
EOT;
                    $rows = Fn::db()->fetchAll($sql);
                    $exam_free_list = array();
                    $exam_free_map = array();
                    foreach ($rows as $row)
                    {
                        $exam_pid = $row['sfe_exampid'];
                        $exam_free_list[] = $exam_pid;
                        if (!isset($exam_free_map[$exam_pid]))
                        {
                            $exam_free_map[$exam_pid] = array();
                        }
                        $v = json_decode($row['sfe_data'], true);
                        $row['subject'] = array_keys($v);
                        $exam_free_map[$exam_pid][] = $row;
                    }
                    $data['exam_free_list'] = array_unique($exam_free_list);
                    $data['exam_free_map'] = $exam_free_map;
                }
            }
        }
        $this->load->view('exam/examresult', $data);
    }

    /**
     * 检查学生是否已完善信息
     */
    private function check_perfect_student()
    {
        $uid = $this->_uinfo['uid'];
        if (! $uid)
        {
            // 登录失效
            redirect('student/index/login');
        }
        
        // 学生基本信息
        $student = $this->db->where('uid', $uid)
            ->get('rd_student')
            ->row_array();
        
        $basic = true;
        if (! is_email($student['email']) || ! is_phone($student['mobile']))
        {
            $basic = false;
        }
        
        if ($basic)
        {
            $fileds = array(
                'last_name',
                'first_name',
                'idcard',
                'sex',
                'birthday',
                'school_id',
                'grade_id'
            );
            
            foreach ($fileds as $filed)
            {
                if (! $student[$filed])
                {
                    $basic = false;
                    break;
                }
            }
        }
        
        $message = array();
        $uri = '';
        
        if (! $basic)
        {
            $message[] = '<a href="' . site_url('student/profile/basic') . '" target="_blank">请完善你的基本信息</a>';
            $uri = 'student/profile/basic';
        }
        
        // 学生学习成绩
        if (! $this->db->select('id')
            ->from('rd_student_ranking')
            ->where('uid', $uid)
            ->get()
            ->row_array())
        {
            $message[] = '<a href="' . site_url('student/profile/awards') . '" target="_blank">请填写你的学习成绩</a>';
            ! $uri && $uri = 'student/profile/awards';
        }
        
        // 学生发展意愿
        if (! $this->db->select('id')
            ->from('rd_student_wish')
            ->where('uid', $uid)
            ->get()
            ->row_array())
        {
            $message[] = '<a href="' . site_url('student/profile/wish') . '" target="_blank">请填写你自己的发展意愿</a>';
            ! $uri && $uri = 'student/profile/wish';
        }
        
        // 学生家长意愿
        if (! $this->db->select('id')
            ->from('rd_student_parent_wish')
            ->where('uid', $uid)
            ->get()
            ->row_array())
        {
            $message[] = '<a href="' . site_url('student/profile/pwish') . '" target="_blank">请填写完整家长意愿</a>';
            ! $uri && $uri = 'student/profile/pwish';
        }
        
        if ($message)
        {
            return array(
                'message' => implode('<br/>', $message),
                'url' => $uri
            );
        }
        else
        {
            return false;
        }
    }
    
    /**
     * 学科名称
     */
    private function _subject_name($uid, $exam_pid, $subject_id)
    {
        $subject_name = C('subject/'.$subject_id);
    
        //综合
        if ($subject_id == 11)
        {
            $sql = "SELECT DISTINCT(subject_id_str)
            FROM rd_exam_test_paper etp
            LEFT JOIN rd_exam_question epq ON epq.paper_id = etp.paper_id
            LEFT JOIN rd_question q ON q.ques_id = epq.ques_id
            WHERE etp.exam_pid = $exam_pid AND etp.uid = $uid
            AND etp.subject_id = $subject_id";
            $subject_id_strs = Fn::db()->fetchCol($sql);
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
}
