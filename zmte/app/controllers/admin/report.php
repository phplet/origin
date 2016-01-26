<?php if ( ! defined('BASEPATH')) exit();

/**
 *
 * 评估管理-测评报告
 * @author TCG
 * @final 2015-08-18
 *
 */
class Report extends A_Controller
{
    private static $_data_subject = array();
    
    public function __construct()
    {
        parent::__construct();
        $this->load->model('cron/cron_task_email_model');
    }

    /**
     * 报告列表
     */
    public function index($rule_id = 0)
    {
    	$rule_id = intval($rule_id);
    	$rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
    	if (empty($rule))
    	{
    		message('不存在该评估规则');
    	}

    	$uid = intval($this->input->get('uid'));
    	$name = trim($this->input->get('name'));
    	$db = Fn::db();

    	//获取考试期次信息
    	$rule['exam_name'] = ExamModel::get_exam($rule['exam_pid'], 'exam_name');

    	//获取该期次所考到的科目
    	$sql = "SELECT exam_pid, exam_id, subject_id 
    	       FROM rd_exam WHERE exam_pid = " . $rule['exam_pid'];
    	$result = $db->fetchAll($sql);
    	$subjects = array(0 => '总结');
    	foreach ($result as $item)
    	{
    		$subjects[$item['subject_id']] = $this->_subject_name($item['exam_pid'], $item['subject_id']);
    	}
    	
    	/*
    	 * 分页读取数据列表，并处理相关数据
    	*/
    	$size   = 16;
    	$page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
    	$search = array();
    	$search['uid'] = $uid;
    	$search['name'] = $name;
    	$search_str = "uid={$uid}&name={$name}";

    	if ($uid > 0)
    	{
    	    $sql = "SELECT COUNT(*) FROM rd_convert2pdf 
    	            WHERE rule_id = $rule_id AND uid = $uid";
    	    
            $sql2= "SELECT c.uid, s.fullname, s.email, s.email_validate, s.school_name,
                    c.target_id, c.source_url, c.source_path, c.html_status, c.is_success
            	    FROM rd_convert2pdf c
            	    LEFT JOIN v_student s ON s.uid = c.uid
    	            WHERE rule_id = $rule_id AND c.uid = $uid";
    	}
    	else if ($name)
    	{
    	    $sql = "SELECT COUNT(*) FROM rd_convert2pdf c
    	            LEFT JOIN v_rd_student s ON s.uid = c.uid
    	            WHERE rule_id = $rule_id AND c.uid > 0
    	            AND s.fullname LIKE '%$name%'";
    	    
    	    $sql2 = "SELECT c.uid, s.fullname, s.email, s.email_validate, s.school_name,
                    c.target_id, c.source_url, c.source_path, c.html_status, c.is_success
            	    FROM rd_convert2pdf c
            	    LEFT JOIN v_student s ON s.uid = c.uid
            	    WHERE rule_id = $rule_id AND c.uid > 0 
    	            AND s.fullname LIKE '%$name%'";
    	}
    	else
    	{
    	    $sql = "SELECT COUNT(*) FROM rd_convert2pdf
    	            WHERE rule_id = $rule_id AND uid > 0";
    	    $sql2= "SELECT c.uid, s.fullname, s.email, s.email_validate, s.school_name,
                    c.target_id, c.source_url, c.source_path, c.html_status, c.is_success
            	    FROM rd_convert2pdf c
            	    LEFT JOIN v_student s ON s.uid = c.uid
            	    WHERE rule_id = $rule_id AND c.uid > 0";
    	}
    	
    	$total = $db->fetchOne($sql);
    	if ($total)
    	{
    	    $sql2 .= " ORDER BY c.uid ASC";
    	    $sql = $db->limitPage($sql2, $page, $size);
    	    $list = $db->fetchAll($sql);
    	    foreach ($list as &$item)
    	    {
    	        $target_ids = explode('_', $item['target_id']);
    	        //检查pdf是否已生成
    	        if ($item['is_success']
    	            && file_exists(C('html2pdf_path') . '/' . $item['source_path']))
    	        {
    	            $item['pdf_ready'] = 1;
    	        }
    	        
    	        //学科报告
    	        if ($item['is_success']
    	            && count($target_ids) == 3
    	            && file_exists(realpath(dirname(APPPATH)) . "/cache/zip/report/{$rule_id}/{$item['uid']}.zip"))
    	        {
    	            $item['zip_ready'] = 1;
    	        }
    	    }
    	    
    	    unset($item);
    	    
    	    $data['list'] = $list;
    	}

	    // 分页
	    $purl = site_url("admin/report/index/{$rule_id}?" . $search_str);
	    $data['pagination'] = multipage($total, $size, $page, $purl);
    	
    	if (!$uid && !$name)
    	{
        	$sql = "SELECT target_id,html_status,source_url,source_path,exam_pid,is_success
        	       FROM rd_convert2pdf 
        	       WHERE rule_id = $rule_id AND uid = 0 AND html_status = 1";
        	$target_ids = Fn::db()->fetchAll($sql);
        	$class_report = array();
        	$teacher_report = array();
        	$ct_ids = array();
        	$schcls_ids = array();
        	foreach ($target_ids as $key => $item)
        	{
        	    $array = explode('_', $item['target_id']);
        	    unset($class_id);
        	    unset($ct_id);
        	    if (end($array) == 1)
        	    {
        	        list($rule_id, $class_id, $subject_id) = $array;
        	        
        	        if (!$class_id || ($rule['subject_id'] && $rule['subject_id'] != $subject_id))
        	        {
        	            continue;
        	        }
        	        
        	        $schcls_ids[] = $class_id;
        	        	
        	        $name = $this->_subject_name(
        	            $item['exam_pid'], $subject_id);
        	        	
        	        if (!isset($class_report[$class_id]))
        	        {
        	            $class_report[$class_id] = array(
        	                'id' => $item['id'],
        	                'target_id' => $item['target_id'],
        	                'schcls_id' => $class_id,
        	            );
        	        }
        	        	
        	        if ($item['html_status'] == 1)
        	        {
        	            $class_report[$class_id]['html_path'][$name] =  $item['source_url'];
        	        }
        	        	
        	        if ($item['is_success']
        	            && file_exists(C('html2pdf_path') . '/' . $item['source_path']))
        	        {
        	            $class_report[$class_id]['pdf_path'][$name] = true;
        	        }
        	        else
        	        {
        	            $class_report[$class_id]['pdf_path'][$name] = false;
        	        }
        	    }
        	    else if (end($array) == 2)
        	    {
        	        list($rule_id, $ct_id, $subject_id) = $array;
        	        
        	        if (!$ct_id || ($rule['subject_id'] && $rule['subject_id'] != $subject_id))
        	        {
        	            continue;
        	        }
        	        
        	        $ct_ids[] = $ct_id;
        	        
        	        $name = $this->_subject_name(
        	            $item['exam_pid'], $subject_id);
        	        	
        	        if (!isset($ct_id[$ct_id]))
        	        {
        	            $teacher_report[$ct_id] = array(
        	                'id' => $item['id'],
        	                'target_id' => $item['target_id'],
        	                'ct_id' => $ct_id
        	            );
        	        }
        	        	
        	        if ($item['html_status'] == 1)
        	        {
        	            $teacher_report[$ct_id]['html_path'][$name] =  $item['source_url'];
        	        }
        	        	
        	        if ($item['is_success']
        	            && file_exists(C('html2pdf_path') . '/' . $item['source_path']))
        	        {
        	            $teacher_report[$ct_id]['pdf_path'][$name] = true;
        	        }
        	        else
        	        {
        	            $teacher_report[$ct_id]['pdf_path'][$name] = false;
        	        }
        	    }
        	}
        	
        	if ($schcls_ids)
        	{
        	    $sql = "SELECT schcls_id,schcls_name,school_name FROM t_school_class
                        LEFT JOIN rd_school ON school_id = schcls_schid
                        WHERE schcls_id IN (" . implode(',', $schcls_ids) . ")";
        	    $data['class'] = Fn::db()->fetchAssoc($sql);
        	}
        	
        	if ($ct_ids)
        	{
        	    $sql = "SELECT ct_id,ct_name,school_name FROM t_cteacher_school
                        LEFT JOIN rd_school ON school_id = scht_schid
        	            LEFT JOIN t_cteacher ON ct_id = scht_ctid
                        WHERE ct_id IN (" . implode(',', $ct_ids) . ")";
        	    $data['teacher'] = Fn::db()->fetchAssoc($sql);
        	}
        	
        	$data['class_report'] = $class_report;
        	$data['teacher_report'] = $teacher_report;
    	}
    	
    	$data['rule'] = $rule;
    	$data['search'] = $search;
    	$data['subjects'] = $subjects;

    	$this->load->view('report/index', $data);
    }

    /**
     * 面试报告列表
     *
     * @return void
     **/
    public function interview($rule_id = 0)
    {
        $rule_id = intval($rule_id);
        $rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);

        if (empty($rule))
        {
            message('不存在该评估规则');
        }

        //获取考试期次信息
        $exam_id = $rule['exam_pid'];
        $rule['exam_name'] = ExamModel::get_exam($exam_id, 'exam_name');

        /* 学科 */
        $sql = "select es.options from {pre}evaluation_standard as es
        left join {pre}evaluation_standard_exam as ese on es.id=ese.standard_id
        where ese.exam_id={$exam_id};";
        $option_str = $this->db->query($sql)->row_array();
        $options = json_decode($option_str['options']);
        $subjects = array_keys((array)$options);
        $subject_names = C('subject');
        $name = trim($this->input->get('name'));
        $uid = intval($this->input->get('uid'));



            $pdf_path = C('html2pdf_path');
            //获取该规则关联的考生
            $sql = "select uids from {pre}evaluate_student where rule_id={$rule_id}";
            $result = $this->db->query($sql)->row_array();
            $uids = isset($result['uids']) ? trim($result['uids']) : '';


            /*
             * 分页读取数据列表，并处理相关数据
            */
            $size   = 15;
            $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
            $total = 0;
            if ($uids)
            {
                $uids = explode(',', $uids);
                $total = count($uids);
                $page_uids = array_chunk($uids, $size);
            }

            $search = array();
            $search['uid'] = $uid;
            $search['name'] = $name;

            if ($search['uid'] > 0)
            {
                $uids = $uid;
            }
            else
            {
                if ($search['name'] != '')
                {
                    $sql = "select group_concat(s.uid) as uids from {pre}student s where concat(s.last_name, s.first_name) like '%".$name."%' " ;
                    $student = $this->db->query($sql)->row_array();
                    if ($student['uids'])
                    {
                        $uids = array_intersect($uids, explode(',', $student['uids']));
                        $uids = implode(',', $uids);
                    }
                    else
                    {
                        $uids = 0;
                    }
                }
            }

            if (!$search['uid'] && !$search['name'])
            {
                $uids = isset($page_uids[$page-1]) ? implode(',', $page_uids[$page-1]) : '';
                // 分页
                $purl = site_url("admin/report/index/$rule_id");

                $data['pagination'] = multipage($total, $size, $page, $purl);
            }

            $students = array();
            if ($uids != '')
            {
                $sql = "select s.uid, s.last_name, s.first_name, s.email, s.email_validate, sch.school_name
                from {pre}student s
                left join {pre}school sch on s.school_id=sch.school_id
                where uid in({$uids})  and uid in(select uids from {pre}evaluate_student where rule_id={$rule_id})";

                $students = $this->db->query($sql)->result_array();


            foreach ($students as &$student)
            {
                $uid = $student['uid'];

                foreach ($subjects as $subject_id) {
                    /* pdf生成状况 */
                    $file_name = $subject_names[$subject_id];
                    $path = "{$pdf_path}/zeming/interview_report/{$rule_id}/{$uid}/{$file_name}.pdf";

                    if (file_exists($path)){
                        $student['interview'][$subject_id]['pdf_status'] = true;
                    } else {
                        $student['interview'][$subject_id]['pdf_status'] = false;
                    }
                }

                /* zip 生成状况 */
                $zip_path = realpath(dirname(APPPATH)) . '/cache' . "/zip/interview_report/{$rule_id}/{$uid}.zip";

                if (file_exists($zip_path)){
                    $student['zip_status'] = true;
                } else {
                    $tudent['zip_status'] = false;
                }

            }
        }

        $data['subject_names'] = $subject_names;
        $rule['students'] = $students;
        $data['rule'] = $rule;
        $data['search'] = $search;

        $this->load->view('report/interview', $data);
    }

    /**
     * 下载资源
     */
    public function down_file()
    {
    	/* 资源类型(1:pdf, 2:zip, 3:interview_pdf, 4:interview_zip) */
    	$type = $this->input->get('type');

        if ($type == 1) {
                $target_id = $this->input->get('target_id');
                
                $sql = "SELECT source_path FROM rd_convert2pdf 
                        WHERE target_id = '$target_id'";
                $source_path = Fn::db()->fetchOne($sql);
                $filepath = C('html2pdf_path') . "/{$source_path}";
                if (!file_exists($filepath))
                {
                    echo '文件不存在';
                    die;
                }
                
                $name = end(explode('/', $source_path));
                
                $target_ids = explode('_', $target_id);
                if (count($target_ids) == 4
                    && in_array(end($target_ids), array(1, 2)))
                {
                    if (end($target_ids) == 1)
                    {
                        $data = SchoolModel::schoolClassInfo($target_ids[1], 'schcls_name AS name');
                    }
                    else if (end($target_ids) == 2)
                    {
                        $data = CTeacherModel::CTeacherInfo($target_ids[1], 'ct_name as name');
                    }
                    
                    $name = $data['name'] . '-' . $name;
                }
                else 
                {
                    $student = StudentModel::get_student($target_ids[1], 'last_name, first_name');
                    $name = $student['last_name'] . $student['first_name'] . '-' . $name;
                }
                
                Func::dumpFile('application/pdf', $filepath, $name);
        } else if ($type == 2) {
                $source_path = urldecode($this->input->get('source_path'));
                $name = $this->input->get('name');
                $filepath = realpath(dirname(APPPATH)) . "/cache/zip/report/" . $source_path;

                if (!file_exists($filepath))
                {
                    echo '文件不存在';
                    die;
                }
                Func::dumpFile('application/zip', $filepath, $name);
        } else if ($type == 3) {
                $target_id = $this->input->get('target_id');
                $data = explode('_', $target_id);
                $subject_id = end($data);
                $name = ($subject_id ? C("subject/$subject_id") : '总结') . "面试.pdf";
                
                $source_path =  "zeming/interview_report/" . $data[0] . "/" . $data[1] . "/{$name}";
                $filepath = C('html2pdf_path') . "/{$source_path}";

                if (!file_exists($filepath))
                {
                    echo '文件不存在';
                    die;
                }
                
                if (count($data) == 4)
                {
                    if (end($data) == 1)
                    {
                        $name = SchoolModel::schoolClassInfo($data[1], 'schcls_name AS name');
                    }
                    else if (end($data) == 2)
                    {
                        $name = CTeacherModel::CTeacherInfo($data[1], 'ct_name as name');
                    }
                
                    $name = $name['name'] . '-' . $name;
                }
                else
                {
                    $student = StudentModel::get_student($data[1], 'last_name, first_name');
                    $name = $student['last_name'] . $student['first_name'] . '-' . $name;
                }

                Func::dumpFile('application/pdf', $filepath, $name);
        } else if ($type == 4) {
                $source_path = urldecode($this->input->get('source_path'));
                $name = $this->input->get('name');
                $filepath = realpath(dirname(APPPATH)) . "/cache/zip/interview_report/" . $source_path;

                if (!file_exists($filepath))
                {
                    echo '文件不存在';
                    die;
                }

                Func::dumpFile('application/zip', $filepath, $name);
                /*
                header("Cache-Control: public");
                header("Content-Description: File Transfer");
                header('Content-disposition: attachment; filename='.$name); //文件名
                header("Content-Type: application/zip"); //zip格式的
                header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
                header('Content-Length: '. filesize($filepath)); //告诉浏览器，文件大小

                @readfile($filepath);
                 */
        } else {
                echo '文件不存在！';exit;
        }
    }

    /**
     * 下载学生成绩单
     */
    public function down_transcript($rule_id = 0)
    {
        $rule_id = intval($rule_id);
        $rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
        if (empty($rule))
        {
            message('不存在该评估规则');
        }
        
        $place_id = (int) $this->input->get('place_id');
        if ($rule['place_id'] > 0)
        {
            $place_id = $rule['place_id'];
        }
        
        if ($place_id > 0)
        {
            $save_file = realpath(dirname(APPPATH)) . "/cache/transcript/" . $place_id . '.zip';
            if (!file_exists($save_file))
            {
                $dir_name = realpath(dirname(APPPATH)) . "/cache/transcript/" . $place_id;
                if (!is_dir($dir_name))
                {
                    @mkdir($dir_name, 0777, true);
                }
                
                $pdf_dir = C('html2pdf_path') . '/zeming/report/';
                $pdf_ready = false;
                
                $ulist = ExamPlaceModel::placeStudentInfoList($place_id);
                foreach ($ulist as $uid => $item)
                {
                    $dir = $pdf_dir . "{$rule_id}/transcript_{$uid}";
                    if (!is_dir($dir))
                    {
                        continue;
                    }
                    
                    $pdf_ready = true;
                
                    $student_name = $item['last_name'] . $item['first_name'];
                    $student_name .= '_' . (trim($item['external_account']) ? trim($item['external_account']) : trim($item['exam_ticket']));
                
                    $f = @dir($dir);
                    if ($f)
                    {
                        while (false !== ($entry = $f->read()))
                        {
                            if ($entry != '.' && $entry != '..')
                            {
                                @copy($dir . '/' . $entry , $dir_name. '/' . $student_name . '_' . $entry);
                            }
                        }
                    }
                }
                
                if ($pdf_ready)
                {
                    require_once APPPATH.'libraries/Pclzip.php';
                    $archive = new PclZip($save_file);
                    //将文件进行压缩
                    $archive->create($dir_name, PCLZIP_OPT_REMOVE_PATH, realpath(dirname(APPPATH)) . "/cache/transcript");
                    $this->rm_dir($dir_name);
                }
                else 
                {
                    message('学生成绩报告单PDF文件还未生成，无法下载！', '/admin/report/down_transcript/' . $rule_id);
                }
            }
            
            if (file_exists($save_file))
            {
                $exam_name = ExamModel::get_exam($rule['exam_pid'], 'exam_name');
                $place = ExamPlaceModel::get_place($place_id, 'place_name,place_schclsid');
                $name = $place['place_name'];
                if ($place['place_schclsid'])
                {
                    $name = SchoolModel::schoolClassInfo($place['place_schclsid'], 'schcls_name');
                }
                
                $subject_name = '';
                if ($rule['subject_id'] > 0)
                {
                    $subject_name = $this->_subject_name($rule['exam_pid'], $rule['subject_id']);
                }
                
                $filename = $exam_name . $name . $subject_name . '成绩报告单';
                
                Func::dumpFile('application/zip', $save_file, $filename . '.zip');
            }
        }
        
        $data['rule'] = $rule;
        $cond_param = array('exam_pid' => $rule['exam_pid']);
        if ($rule['place_id'] > 0)
        {
            $cond_param['place_id'] = $rule['place_id'];
        }
        
        $data['places'] = ExamPlaceModel::get_exam_place_list($cond_param, 1, time(), null, 'place_id, place_name');
        
        $this->load->view('report/down_transcript', $data);
    }
    
    /**
     * 下载班级报告
     */
    public function down_class_report($rule_id = 0)
    {
        $rule_id = intval($rule_id);
        $rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
        if (empty($rule))
        {
            message('不存在该评估规则');
        }
        
        $save_file = realpath(dirname(APPPATH)) . "/cache/down_class_report/" . $rule_id . '.zip';
        if (!file_exists($save_file))
        {
            $place_id = $rule['place_id'];
            $schcls_ids = array();
            if ($place_id > 0)
            {
                $place = ExamPlaceModel::get_place($place_id);
                if ($place['place_schclsid'])
                {
                    $schcls_ids = array($place['place_schclsid']);
                }
            }
            else
            {
                $placelist = ExamPlaceModel::get_exam_place_list(array('exam_pid'=>$rule['exam_pid']), 1, time());
                foreach ($placelist as $item)
                {
                    if ($item['place_schclsid'])
                    {
                        $schcls_ids[] = $item['place_schclsid'];
                    }
                }
            }
            
            if (!$schcls_ids)
            {
                message('当前评估规则没有关联班级，无法下载班级报告！');
            }
            
            $cls_list = SchoolModel::schoolClassListInfo(implode(',', $schcls_ids));
            
            $dir_name = realpath(dirname(APPPATH)) . "/cache/down_class_report/" . $rule_id;
            if (!is_dir($dir_name))
            {
                @mkdir($dir_name, 0777, true);
            }

            $pdf_dir = C('html2pdf_path') . '/zeming/report/';
            $pdf_ready = false;

            foreach ($schcls_ids as $cls_id)
            {
                $dir = $pdf_dir . "{$rule_id}/class_{$cls_id}";
                if (!is_dir($dir))
                {
                    continue;
                }

                $pdf_ready = true;

                $cls_name = $cls_list[$cls_id]['schcls_name'];

                $f = @dir($dir);
                if ($f)
                {
                    while (false !== ($entry = $f->read()))
                    {
                        if ($entry != '.' && $entry != '..')
                        {
                            @copy($dir . '/' . $entry , $dir_name. '/' . $cls_name . '_' . $entry);
                        }
                    }
                }
            }

            if ($pdf_ready)
            {
                require_once APPPATH.'libraries/Pclzip.php';
                $archive = new PclZip($save_file);
                //将文件进行压缩
                $archive->create($dir_name, PCLZIP_OPT_REMOVE_PATH, realpath(dirname(APPPATH)) . "/cache/down_class_report");
                $this->rm_dir($dir_name);
            }
            else
            {
                message('班级报告PDF文件还未生成，无法下载！');
            }
        }

        if (file_exists($save_file))
        {
            $exam_name = ExamModel::get_exam($rule['exam_pid'], 'exam_name');
            $subject_name = '';
            if ($rule['subject_id'] > 0)
            {
                $subject_name = $this->_subject_name($rule['exam_pid'], $rule['subject_id']);
            }
            $filename = $exam_name . $subject_name . '班级报告';
            
            Func::dumpFile('application/zip', $save_file, $filename . '.zip');
        }
    }
    
    /**
     * 下载班级报告
     */
    public function down_teacher_report($rule_id = 0)
    {
        $rule_id = intval($rule_id);
        $rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
        if (empty($rule))
        {
            message('不存在该评估规则');
        }
    
        $save_file = realpath(dirname(APPPATH)) . "/cache/down_teacher_report/" . $rule_id . '.zip';
        if (!file_exists($save_file))
        {
            $teacher_list = TeacherStudentModel::examTeacherList($rule['exam_pid']);
            if (!$teacher_list)
            {
                message('当前评估规则没有关联教师，无法下载教师报告！');
            }
    
            $dir_name = realpath(dirname(APPPATH)) . "/cache/down_teacher_report/" . $rule_id;
            if (!is_dir($dir_name))
            {
                @mkdir($dir_name, 0777, true);
            }
    
            $pdf_dir = C('html2pdf_path') . '/zeming/report/';
            $pdf_ready = false;
    
            foreach ($teacher_list as $ct_id => $item)
            {
                $dir = $pdf_dir . "{$rule_id}/teacher_{$ct_id}";
                if (!is_dir($dir))
                {
                    continue;
                }
    
                $pdf_ready = true;
    
                $teacher_name = $item['ct_name'];
    
                $f = @dir($dir);
                if ($f)
                {
                    while (false !== ($entry = $f->read()))
                    {
                        if ($entry != '.' && $entry != '..')
                        {
                            @copy($dir . '/' . $entry , $dir_name. '/' . $teacher_name . '_' . $entry);
                        }
                    }
                }
            }
    
            if ($pdf_ready)
            {
                require_once APPPATH.'libraries/Pclzip.php';
                $archive = new PclZip($save_file);
                //将文件进行压缩
                $archive->create($dir_name, PCLZIP_OPT_REMOVE_PATH, realpath(dirname(APPPATH)) . "/cache/down_teacher_report");
                $this->rm_dir($dir_name);
            }
            else
            {
                message('教师报告PDF文件还未生成，无法下载！');
            }
        }
    
        if (file_exists($save_file))
        {
            $exam_name = ExamModel::get_exam($rule['exam_pid'], 'exam_name');
            $subject_name = '';
            if ($rule['subject_id'] > 0)
            {
                $subject_name = $this->_subject_name($rule['exam_pid'], $rule['subject_id']);
            }
            $filename = $exam_name . $subject_name . '教师报告';
    
            Func::dumpFile('application/zip', $save_file, $filename . '.zip');
        }
    }
    
    /**
     * 邮件通知
     */
    public function mail($rule_id = 0, $uid = 0)
    {
    	$rule_id = intval($rule_id);
    	$uid = intval($uid);

    	$rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
    	if (empty($rule))
    	{
    		message('不存在该评估规则');
    	}

    	$uid && $student = StudentModel::get_student($uid, 'uid, email, last_name, first_name');
    	if (empty($student))
    	{
    		message('不存在该考生');
    	}

    	//获取考试期次信息
    	$rule['exam_name'] = ExamModel::get_exam($rule['exam_pid'], 'exam_name');

    	//获取该期次所考到的科目
    	$exam_subjects = array();
    	$exam_names = array();
    	$sql = "select exam_id, subject_id from {pre}exam where exam_pid=" . $rule['exam_pid'];
    	$result = $this->db->query($sql)->result_array();
    	foreach ($result as $item)
    	{
    		$exam_names[$item['exam_id']] = C('subject/'.$item['subject_id']);
    	}
    	$rule['exams'] = $exam_names;

    	$email = $student['email'];
    	if (!is_email($email))
    	{
    		message('该考生的邮件地址格式不正确，请校对后再试');
    	}

    	$email_tpl = C('email_template/send_zip');
    	$email_content = $this->load->view($email_tpl['tpl'], array('rule' => $rule, 'student' => $student), true);
		$data = array(
						'type' 		=> 1,
						'target_id' => "{$rule_id}-{$uid}",
						'email' 	=> $email,
						'title' 	=> $email_tpl['subject'],
						'content' 	=> $email_content,
						'attache' 	=> "report/{$rule_id}/{$uid}.zip",
		);

    	$res = $this->cron_task_email_model->insert($data);
    	if ($res)
    	{
    		message('已成功将该考生加入通知队列中，等待系统处理中...');
    	}
    	else
    	{
    		message('操作失败，请重试.');
    	}
    }

    /**
     * 邮件通知
     */
    public function batch_mail($rule_id = 0)
    {
    	$rule_id = intval($rule_id);
    	$rule_id && $rule = EvaluateRuleModel::get_evaluate_rule($rule_id);
    	if (empty($rule))
    	{
    		message('不存在该评估规则');
    	}

    	$uid = $this->input->post('uid');
    	$send_all = intval($this->input->post('send_all'));

    	if (!$send_all && (!is_array($uid) || !count($uid)))
    	{
    		message('请选择要通知的考生');
    	}

    	$uids = array();
    	if ($send_all)
    	{
    		$sql = "select uids from {pre}evaluate_student where rule_id={$rule_id}";
    		$result = $this->db->query($sql)->row_array();
    		$uids = isset($result['uids']) ? trim($result['uids']) : '';

    		$uids = explode(',', $uids);
    	}
    	else
    	{
    		$uids = $uid;
    	}

    	//获取考试期次信息
    	$rule['exam_name'] = ExamModel::get_exam($rule['exam_pid'], 'exam_name');

    	//获取该期次所考到的科目
    	$exam_subjects = array();
    	$exam_names = array();
    	$sql = "select exam_id, subject_id from {pre}exam where exam_pid=" . $rule['exam_pid'];
    	$result = $this->db->query($sql)->result_array();
    	foreach ($result as $item)
    	{
    		$exam_names[$item['exam_id']] = C('subject/'.$item['subject_id']);
    	}
    	$rule['exams'] = $exam_names;

    	//获取未生成zip的考生
    	$sql = "select uid from {pre}evaluate_student_stat where rule_id={$rule_id} and zip_ready=0 and uid in(".implode(',', $uids).")";
    	$result = $this->db->query($sql)->result_array();
    	$no_zip_ready_uids = array();
    	foreach ($result as $item)
    	{
    		$no_zip_ready_uids[] = $item['uid'];
    	}

    	$data = array();
    	$fails = array();
    	$email_tpl = C('email_template/send_zip');
    	foreach ($uids as $uid)
    	{
	    	$uid && $student = StudentModel::get_student($uid, 'uid, email, last_name, first_name');
	    	if (empty($student))
	    	{
	    		$fails[] = 'uid:' . $uid . '【原因：考生不存在】';
	    		continue;
	    	}
	    	if (in_array($uid, $no_zip_ready_uids))
	    	{
	    		$fails[] = 'uid:' . $uid . '【原因：未生成zip压缩包】';
	    		continue;
	    	}

	    	$email = $student['email'];
	    	if (!is_email($email))
	    	{
	    		$fails[] = 'uid:' . $uid . '->email:' . $email . '【原因：邮件地址格式不正确】';
	    		continue;
	    	}

	    	$email_content = $this->load->view($email_tpl['tpl'], array('rule' => $rule, 'student' => $student), true);
	    	$data[] = array(
	    			'type' 		=> 1,
	    			'target_id' => "{$rule_id}-{$uid}",
	    			'email' 	=> $email,
	    			'title' 	=> $email_tpl['subject'],
	    			'content' 	=> $email_content,
	    			'attache' 	=> "report/{$rule_id}/{$uid}.zip",
	    	);
    	}

    	$res = $this->cron_task_email_model->insert_batch($data);
    	if ($res)
    	{
    		if (count($fails))
    		{
	    		message('已成功将部分该考生加入通知队列中，等待系统处理中，以下考生出现异常：<br/>' . implode('<hr/>', $fails));
    		}
    		else
    		{
	    		message('已成功将该考生加入通知队列中，等待系统处理中');
    		}
    	}
    	else
    	{
    		message('操作失败，请重试.');
    	}
    }
    
    /**
     * @description 删除目录及文件
     * @param string $dir
     * @return mixed boolen
     */
    private function rm_dir($dir)
    {
        $ret_val = false;
        if (is_dir($dir))
        {
            $d = @dir($dir);
            if ($d)
            {
                while (false !== ($entry = $d->read()))
                {
                    if ($entry != '.' && $entry != '..')
                    {
                        $entry = $dir . '/' . $entry;
                        if (is_dir($entry))
                        {
                            $this->rm_dir($entry);
                        }
                        else
                        {
                            @unlink($entry);
                        }
                    }
                }
                $d->close();
            }
    
            @rmdir($dir);
        }
        else
        {
            $ret_val = @unlink($dir);
        }
    
        return $ret_val;
    }
    
    /**
     * 学科名称
     */
    private function _subject_name($exam_pid, $subject_id)
    {
        if ($subject_id > 0)
        {
            $subject_name = C('subject/'.$subject_id);
        }
        else 
        {
            $subject_name = '总结';
        }
        
        //综合
        if ($subject_id == 11)
        {
            if (!isset(self::$_data_subject[$exam_pid][$subject_id]))
            {
                $sql = "SELECT DISTINCT(subject_id_str) 
                        FROM rd_summary_paper_question spq
                        LEFT JOIN rd_question q ON q.ques_id = spq.ques_id 
                        WHERE spq.exam_pid = $exam_pid AND spq.subject_id = $subject_id";
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
                
                self::$_data_subject[$exam_pid][$subject_id] = $subject_name;
            }
            else 
            {
                $subject_name = self::$_data_subject[$exam_pid][$subject_id];
            }
        }
        
        return $subject_name;
    }
}
