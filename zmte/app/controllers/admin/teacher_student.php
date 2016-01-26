<?php if ( ! defined('BASEPATH')) exit();
class Teacher_student extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index($exam_pid = 0)
    {
        if (!$exam_pid)
        {
            message('请指定考试期次！');
        }
        
        $exam = ExamModel::get_exam_by_id($exam_pid);
        if (!$exam)
        {
            message('考试期次不存在！');
        }
        
        $data = array();
        
        $cond_param['exam_pid'] = $exam_pid;
        $cond_param['ct_name'] = trim($this->input->get('ct_name'));
        $cond_param['stu_name'] = trim($this->input->get('stu_name'));
        $cond_param['subject_id'] = intval($this->input->get('subject_id'));
        
        $page = intval($this->input->get('page'));
        $page = $page ? $page : 1;
        $perpage = C('default_perpage_num');
        $total = TeacherStudentModel::teacherStudentListCount($cond_param);
        if ($total)
        {
            $data['list'] = TeacherStudentModel::teacherStudentList('*', $cond_param, $page, $perpage);
        }
        
        $purl = site_url('admin/teacher_student/index/'.$exam_pid) . ($cond_param ? '?'.implode('&',$cond_param) : '');
        $data['pagination'] = multipage($total, $perpage, $page, $purl);
        $data['search'] = $cond_param;
        $data['exam'] = $exam;
        $data['subject'] = C('subject');
        
        $this->load->view('teacher_student/index', $data);
    }
    
    /**
     * 导入
     */
    public function import($exam_pid = 0)
    {
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel',
                'file/import_teacher_stundent_template.xlsx',
                '师生关联模板.xlsx');
            exit();
        }
        
        if (!$exam_pid)
        {
            message('参数错误');
        }
        
        $data = array();
        while (isset($_FILES['file']))
        {
            $param = $_POST;
            $col_char = array();
            $rows = Excel::readSimpleUploadFile2($_FILES['file']);
            if (!is_array($rows))
            {
                $data['error'] = $rows;
                break;
            }
            
            $subject_map = array_flip(C('subject'));
            $db = Fn::db();
            
            $exam_ticket_maprule = ExamModel::get_exam($exam_pid, 'exam_ticket_maprule');
            
            $sql = "SELECT subject_id, exam_id FROM rd_exam
                    WHERE exam_pid = $exam_pid";
            $subject_exam = $db->fetchPairs($sql);
            
            if (!is_array($subject_exam))
            {
                $data['error'] = '考试期次没有考试学科';
                break;
            }
            
            $exam_subjectid = array_keys($subject_exam);
            $list = array();
            $subject_key = array();
            
            foreach ($rows as $k => $row)
            {
                if ($k == 0)
                {
                    for ($i = 2; $i <= count($row); $i++)
                    {
                        $subject_id = $subject_map[str_replace("'", "", trim($row[$i]))];
                        if ($subject_id 
                            && in_array($subject_id, $exam_subjectid))
                        {
                            $subject_key[$i] = $subject_id;
                        }
                    }
                }
                else 
                {
                    $student = array();
                    for ($i = 1; $i <= count($row); $i++)
                    {
                        if ($i == 1)
                        {
                            $exam_ticket = trim($row[$i]);
                            if (!$exam_ticket)
                            {
                                break;
                            }
                            
                            $exam_ticket = exam_ticket_maprule_encode($exam_ticket, $exam_ticket_maprule);
                            
                            $sql = "SELECT uid, school_id FROM rd_student
                                    WHERE exam_ticket = '$exam_ticket'";
                            $student = $db->fetchRow($sql);
                            if (!$student)
                            {
                                break;
                            }
                            
                            $list[$student['uid']]['uid'] = $student['uid'];
                        }
                        else 
                        {
                            $ct_name = str_replace("'", "", trim($row[$i]));
                            if (!$ct_name)
                            {
                                continue;
                            }
                            
                            $sql = "SELECT ct_id FROM t_cteacher 
                                    LEFT JOIN t_cteacher_school ON scht_ctid = ct_id
                                    WHERE scht_schid = {$student['school_id']} 
                                    AND ct_name = '$ct_name'";
                            $ct_id = $db->fetchOne($sql);
                            if (!$ct_id)
                            {
                                continue;
                            }
                            
                            $list[$student['uid']]['teacher'][$subject_key[$i]] = $ct_id;
                        }
                    }
                }
            }
            
            try
            {
                if (!$db->beginTransaction())
                {
                    throw new Exception('开始导入事务处理失败');
                }
            
                $insert = 0;
            
                // 导入教师
                foreach ($list as $uid => $row)
                {
                    foreach ($row['teacher'] as $subject_id => $ct_id)
                    {
                        $bind = array(
                            'tstu_ctid'    => $ct_id,
                            'tstu_stuid'   => $uid,
                            'tstu_exampid' => $exam_pid,
                            'tstu_examid'  => $subject_exam[$subject_id],
                            'tstu_subjectid'=>$subject_id,
                        );
                        
                        TeacherStudentModel::addTeacherStudent($bind);
                        
                        $insert++;
                    }
                }
            
                if ($db->commit())
                {
                    $data['success'] = <<<EOT
导入Excel文件({$_FILES['file']['name']})成功,共插入{$insert}条师生记录
EOT;
                    admin_log('import', 'teacher_student', $data['success']);
                }
                else
                {
                    $err = $db->errorInfo()[2];
                    $db->rollBack();
                    throw new Exception($err);
                }
            }
            catch (Exception $e)
            {
                $data['error'] = $e->getMessage();
            }
            
            break;
        }
            
        $data['exam_pid'] = $exam_pid;
        $data['param'] = $param;
        $this->load->view('teacher_student/import', $data);
    }
}