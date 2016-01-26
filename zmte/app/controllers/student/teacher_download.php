<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// +------------------------------------------------------------------------------------------ 
// | Author: TCG <TCG_love@163.com> 
// +------------------------------------------------------------------------------------------ 
// | There is no true,no evil,no light,there is only power. 
// +------------------------------------------------------------------------------------------ 
// | Description: 教师下载评估报告 Dates:  2015-03-06
// +------------------------------------------------------------------------------------------ 

class Teacher_download extends S_Controller
{
    /**
     * 构造函数
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->check_access();
    }

    /**
     * 权限检测
     *
     * @return void
     */
    private function check_access()
    {
        $teacher = $this->session->userdata('teacher');

        $action = $this->uri->segment(3);

        if (empty($action) || !in_array($action, array('login', 'login_deal', 'logout'))) {
            if (!isset($teacher['id']) || $teacher['id'] <= 0) {
                message('没有权限，请登录后操作！', "student/teacher_download/login");
            }
        }
    }

    /**
     * 登陆界面
     *
     * @return void
     */
    public function login()
    {
        $this->load->view('teacher_download/login');
    }

    /**
     * 登陆验证
     *
     * @return void
     */
    public function login_deal()
    {
        $ticket = trim($this->input->post('ticket'));
        $password = trim($this->input->post('password', TRUE));
    
        if (empty($ticket) || empty($password)) {
            message('帐号或密码不能为空！');
        }

        $map = array();
        $map['email'] = $ticket;
        $map['flag'] = '1';
        $query = $this->db->where($map)->get('teacher_download');
        
        if ($query->num_rows()) {
            $user = $query->row_array();

            if ($user['password'] != my_md5($password)) {
                message('帐号或密码错误！');
            }

            $this->session->set_userdata('teacher', $user);

            redirect("student/teacher_download/index");
        } else {
            message('用户不存在！');
        }
    }

    /**
     * 退出登录
     *
     * @return void
     */
    public function logout()
    {
        $this->session->sess_destroy();

        message('退出成功！', "student/teacher_download/login");
    }

    /**
     * 通用模板(首页)
     *
     * @return void
     */
    public function index()
    {
        $this->load->view('teacher_download/index');
    }

    /**
     * 通用模板(顶部)
     *
     * @return void
     */
    public function top()
    {
        $this->load->view('teacher_download/top');
    }

    /**
     * 通用模板(左侧)
     *
     * @return void
     */
    public function left()
    {
        $this->load->view('teacher_download/left');
    }

    /**
     * 教师信息
     *
     * @return void
     */
    public function teacher_info()
    {
        /* 教师信息 */
        $teacher = $this->session->userdata('teacher');

        if (empty($teacher['id'])) {
            message('当前教师不存在！', "student/teacher_download/login");
        }

        /* 学科 */
        $teacher_subject = json_decode($teacher['subjects'], true);

        /* 考试期次 */
        $exams = array();
        $exams_arr = json_decode($teacher['relate_exam'], true);

        if (is_array($exams_arr) && count($exams_arr) > 0) {
            foreach ($exams_arr as $id) {
                $sql = "SELECT exam_id,exam_name FROM {pre}exam WHERE exam_id={$id}";
                $exams[] = $this->db->query($sql)->row_array();
            }
        }

        $data = array();
        $subjects = C('subject');
        /* 加入总结报告 */
        $subjects[0] = '总结';
        $data['subject'] = $subjects;
        $data['teacher_subject'] = $teacher_subject;
        $data['teacher'] = $teacher;
        $data['exams'] = $exams;

        $this->load->view('teacher_download/teacher_info', $data);
    }

    /**
     * 重置密码界面
     *
     * @return void
     */
    public function reset_password()
    {
        $this->load->view('teacher_download/reset_password');   
    }

    /**
     * 重置密码处理
     *
     * @return void
     */
    public function reset_password_deal()
    {
        /* 教师信息 */
        $teacher = $this->session->userdata('teacher');

        if (!$teacher) {
            message('会话已失效，请重新提交', 'student/teacher_download/login');
        }

        $old_password = $this->input->post('old_password');
        $new_password = $this->input->post('new_password');
        $repeat_password = $this->input->post('repeat_password');

        if (my_md5($old_password) != $teacher['password']) {
            message('密码错误！请重试！');
        }

        if (is_string($passwd_msg = is_password($new_password))) {
            message($passwd_msg);
        }

        if ($new_password != $repeat_password) {
            message('您两次输入密码不一致！请重试！');
        }

        $rst = $this->db->update('teacher_download', array('password'=>my_md5($new_password)), array('id'=>$teacher['id']));

        message('您的新密码已设置成功，重新登陆后生效', 'student/teacher_download/reset_password', 'success');

    }

    /**
     * 评估报告下载页面
     *
     * @return void
     */
    public function report($error = null)
    {
        /* 教师信息 */
        $teacher = $this->session->userdata('teacher');

        /* 考试期次 */
        $exams = array();
        $exams_arr = json_decode($teacher['relate_exam'], true);

        if (is_array($exams_arr) && count($exams_arr) > 0) {
            foreach ($exams_arr as $id) {
                $sql = "SELECT exam_id,exam_name FROM {pre}exam WHERE exam_id={$id}";
                $exams[] = $this->db->query($sql)->row_array();
            }
        }

        $data = array();
        $data['exams'] = $exams;

        if (!is_null($error)) {
            $data['error'] = $error;
        }

        $this->load->view('teacher_download/report', $data);
    }

    /**
     * 评估报告下载处理
     *
     * @return void
     */
    public function report_deal()
    {
        $post = $this->input->post();

        /** 上传文件 */
        $config['upload_path'] = '../../cache/excel/';
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024 * 10; #单位kb
        $config['overwrite'] = false;

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('file')) {
            $this->report($this->upload->display_errors());
        } else {

            /* 教师信息 */
            $teacher = $this->session->userdata('teacher');
            $upload_data = $this->upload->data();
            $message = array();
            $exam_id = $post['exam_id'];

            if (empty($exam_id)) {
                message('未查询到当前考试期次信息！请联系管理员！');
            }

            /* 考试期次信息 */
            $sql = "SELECT * FROM {pre}exam WHERE exam_id={$exam_id}";
            $exam = $this->db->query($sql)->row_array();

            if (empty($exam)) {
                message('未查询到当前考试期次信息！请联系管理员！');
            }

            /* 当前考试期次下的学科 */
            $sql = "SELECT exam_id,subject_id FROM {pre}exam WHERE exam_pid={$exam_id}";
            $subjects_exam_tmp = $this->db->query($sql)->result_array();

            if (empty($subjects_exam_tmp)) {
                message('当前考试期次下不存在学科！请联系管理员！');
            }

            $subjects_exam = array();

            foreach ($subjects_exam_tmp as $key => $value) {
                $subjects_exam[$value['subject_id']] = $value;
            }

            /* 学科 */
            $subjects_tmp = json_decode($teacher['subjects'], true);

            if (empty($subjects_tmp) ||count($subjects_tmp) <= 0) {
                message('当前帐号暂未选择学科，请联系管理员！');
            }

            $subjects = array();
            /* 有报告的学科 */
            foreach ($subjects_tmp as $key => $value) {
                if (isset($subjects_exam[$value]) || (is_numeric($value) && $value == 0)) {
                    $subjects[$value]['is_exist'] = true; 
                } else {
                    $subjects[$value]['is_exist'] = false; 
                }
            }

            /* 考试期次对应学生 考试期次-> 评估规则 -> 考生*/
            $sql = "SELECT er.id AS rule_id,es.uids from {pre}evaluate_student AS es LEFT JOIN {pre}evaluate_rule AS er ON es.rule_id=er.id WHERE er.exam_pid={$exam_id}";
            $row = $this->db->query($sql)->row_array();

            if (empty($row)) {
                message('获取当前考试期次下考生信息失败，请联系管理员！');
            }

            $rule_id = $row['rule_id'];
            $uids = explode(',', $row['uids']);

            if (empty($uids) ||count($uids) <= 0) {
                message('当前考试期次下不存在考生！');
            }
            
            /** 读取excel */
            $this->load->library('PHPExcel');
            $this->load->library('PHPExcel/IOFactory');
            $inputFileType = IOFactory::identify($upload_data['file_relative_path']);
            $objReader = IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($upload_data['file_relative_path']);
            $data = $objPHPExcel->getSheet(0)->toArray();

            if (empty($data) || count($data) <= 1) {
                message('表格数据错误，请下载表格模板从新填写！');
            }

            $students = array();

            foreach ($data as $key => $value) {

                if ($key < 1) {
                    continue;
                }

                if (empty($value[0])) {
                    continue;
                }

                /* 准考证号转换为uid */
                $ticket = $value[0];
                /* 准考证类型 */
                /* if ($exam['exam_ticket_maprule'] > 0) {
                    $sql = "SELECT uid,student_name AS name FROM {pre}exam_student_list WHERE student_ticket={$ticket}";
                    
                } else { */
                $sql = "SELECT uid,concat(last_name,first_name) AS name FROM {pre}student WHERE exam_ticket={$ticket}";
                /* } */
                $row = $this->db->query($sql)->row_array();
                $uid = $row['uid'];

                $students[$key]['ticket'] = $ticket;

                if (!empty($uid) && $uid > 0 && in_array($uid, $uids)) {
                    $students[$key]['uid'] = $uid;
                    $students[$key]['name'] = $row['name'];
                } else {
                    $students[$key]['uid'] = false;
                }
            }

            $data = array();
            $data['students'] = $students;
            $data['subjects'] = $subjects;
            $subject_sys = C('subject');
            /* 加入总结报告 */
            $subject_sys[0] = '总结';
            $data['subject_sys'] = $subject_sys;
            $data['rule_id'] = $rule_id;

            $this->load->view('teacher_download/report_list', $data);
        }
    }

    /**
     * 获取项目跟目录
     *
     * @return string
     */
    private function _get_cache_root_path()
    {
        return realpath(dirname(APPPATH)) . '/cache';
    }

    /**
     * 单科下载
     *
     * @return void
     */
    public function download_subject($rule_id, $uid, $subject_id)
    {
        if (empty($rule_id) || empty($uid)) {
            message('参数错误，请重试！', 'student/teacher_download/report');
        }

        /* 查找zip文件 cache文件夹路径 + 考试期次ID + 考生ID*/
        $zip_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}.zip";
        /* 临时文件夹，存放解压文件 */
        $tmp_dir = $this->_get_cache_root_path() . "/zip/report/" . $rule_id . "/tmp";
        
        if (!file_exists($zip_path)) {
            message('文件不存在，请重试！', 'student/teacher_download/report');
        }

        /* 读取学科 */
        $subjects = C('subject');
        /* 加入总结报告 */
        $subjects[0] = '总结';

        if (!isset($subjects[$subject_id])) {
            message('当前科目不存在，请重试！', 'student/teacher_download/report');
        }

        $subject = $subjects[$subject_id];
        $file_name = $subject . '.pdf';
        /* 文件名编码转换 */
        $file_name = mb_convert_encoding($file_name, 'gb2312', 'UTF-8');
        /* 读取文件下载 */
        require_once APPPATH.'libraries/Pclzip.php';
        $archive = new PclZip($zip_path);
        $rst = $archive->extract(PCLZIP_OPT_PATH, $tmp_dir, PCLZIP_OPT_BY_NAME, $file_name);

        if ($rst == 0) {
            log_message('error', $archive->errorInfo(true));
            die("文件解压错误！");
        } else {
            $file = $rst[0]['filename'];
            $this->download_file($file);
            $this->delete_dir($tmp_dir);
        }
    }

    /**
     * 全科下载
     *
     * @return void
     */
    public function download_subject_all($rule_id, $uid)
    {
        if (empty($rule_id) || empty($uid)) {
            message('参数错误，请重试！', 'student/teacher_download/report');
        }

        /* 查找zip文件 cache文件夹路径 + 考试期次ID + 考生ID*/
        $zip_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}.zip";
        
        if (!file_exists($zip_path)) {
            message('文件不存在，请重试！', 'student/teacher_download/report');
        }

        /* 当前考试期次下所有学科 */
        $subjects_exam = array();
        $sql = "SELECT e.subject_id FROM {pre}exam AS e LEFT JOIN  {pre}evaluate_rule AS er  ON e.exam_pid=er.exam_pid WHERE er.id={$rule_id}";
        $subjects_tmp = $this->db->query($sql)->result_array();

        if (empty($subjects_tmp)) {
            message('当前考试期次下不存在学科！', 'student/teacher_download/report');
        }

        foreach ($subjects_tmp as $key => $value) {
            $subjects_exam[] = $value['subject_id'];
        }

        /* 当前用户可管理学科 */
        $teacher = $this->session->userdata('teacher');
        $subjects_teacher = json_decode($teacher['subjects'], true);
        /* 读取学科 */
        $subjects = C('subject');
        /* 加入总结报告 */
        $subjects[0] = '总结';
        $subjects_exam[] = 0;

        $rst_diff = array_diff($subjects_teacher, $subjects_exam) || array_diff($subjects_exam, $subjects_teacher);

        /* 判断是否为所有学科 */
        if (empty($rst_diff)) {
            /* 直接下载zip */
            $this->download_file($zip_path);
        } else {
            /* 临时文件夹，存放解压文件 */
            $tmp_dir = $this->_get_cache_root_path() . "/zip/report/" . $rule_id . "/tmp";
            /* 解压zip */
            require_once APPPATH.'libraries/Pclzip.php';
            $archive = new PclZip($zip_path);
            $rst = $archive->extract(PCLZIP_OPT_PATH, $tmp_dir);

            if ($rst == 0) {
                log_message('error', $archive->errorInfo(true));
                die("文件解压错误！");
            } else {
                $save_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}_t.zip";
                $file_tmp = array();

                /* 选中所属学科，从新生成zip */
                foreach ($subjects_teacher as $key => $value) {
                    if (!in_array($value, $subjects_exam) && $value > 0) {
                        continue;
                    }

                    $file_tmp[] = $tmp_dir . '/' . $subjects[$value] . '.pdf';
                }

                $zip = new PclZip($save_path);
                $file_tmp_zip = $zip->create(implode(',', $file_tmp), PCLZIP_OPT_REMOVE_PATH, $tmp_dir);

                /* 下载 */
                $this->download_file($save_path);
                /* 清除解压包 */
                $this->delete_dir($tmp_dir);
                /* 清除临时zip包 */
                @unlink($save_path);
            }
        }
    }

    /**
     * 全部下载（学科）
     *
     * @return void
     */
    public function download_all_subject()
    {
        $rule_id = $this->input->post('rule_id');
        $uids = $this->input->post('uids');

        if (empty($rule_id) || empty($uids) || count($uids) <= 0) {
            message('参数错误，请重试！', 'student/teacher_download/report');
        }

        $zip_path_arr = array();

        foreach ($uids as $uid) {
            /* 查找zip文件 cache文件夹路径 + 考试期次ID + 考生ID*/
            $zip_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/{$uid}.zip";
            
            if (!file_exists($zip_path)) {
                message('文件不存在，请重试！', 'student/teacher_download/report');
            }

            $zip_path_arr[$uid] = $zip_path;
        }

        /* 当前考试期次下所有学科 */
        $subjects_exam = array();
        $sql = "SELECT e.subject_id FROM {pre}exam AS e LEFT JOIN  {pre}evaluate_rule AS er  ON e.exam_pid=er.exam_pid WHERE er.id={$rule_id}";
        $subjects_tmp = $this->db->query($sql)->result_array();

        if (empty($subjects_tmp)) {
            message('当前考试期次下不存在学科！', 'student/teacher_download/report');
        }

        foreach ($subjects_tmp as $key => $value) {
            $subjects_exam[] = $value['subject_id'];
        }

        /* 当前用户可管理学科 */
        $teacher = $this->session->userdata('teacher');
        $subjects_teacher = json_decode($teacher['subjects'], true);
        /* 读取学科 */
        $subjects = C('subject');
        /* 加入总结报告 */
        $subjects[0] = '总结';
        $subjects_exam[] = 0;
        require_once APPPATH.'libraries/Pclzip.php';

        $rst_diff = array_diff($subjects_teacher, $subjects_exam) || array_diff($subjects_exam, $subjects_teacher);

        /* 判断是否为所有学科 */
        if (empty($rst_diff)) {
            $save_path = $this->_get_cache_root_path() . "/zip/report/" . $rule_id . "/all.zip";
            $zip_all = new pclZip($save_path);
            $file_all_tmp = $zip_all->create(implode($zip_path_arr, ','), PCLZIP_OPT_REMOVE_PATH, $this->_get_cache_root_path() . "/zip/report/{$rule_id}");
            /* 打包所有zip */
            $this->download_file($save_path);
            /* 清除临时zip包 */
            @unlink($save_path);
        } else {
            /* 临时文件夹，存放解压文件 */
            $tmp_dir = $this->_get_cache_root_path() . "/zip/report/" . $rule_id . "/tmp";

            foreach ($zip_path_arr as $uid => $zip_path) {
                /* 解压zip */
                $archive = new PclZip($zip_path);
                $rst = $archive->extract(PCLZIP_OPT_PATH, $tmp_dir . '/' . $uid);
            }

            if ($rst == 0) {
                log_message('error', $archive->errorInfo(true));
                die("文件解压错误！");
            } else {

                $save_path = $this->_get_cache_root_path() . "/zip/report/{$rule_id}/all.zip";

                $file_tmp = array();

                foreach ($zip_path_arr as $uid => $zip_path) {
                    /* 选中所属学科，从新生成zip */
                    foreach ($subjects_teacher as $key => $value) {
                        if (!in_array($value, $subjects_exam) && $value > 0) {
                            continue;
                        }
                        
                        $file_tmp[] = $tmp_dir . '/' . $uid . '/' . $subjects[$value] . '.pdf';
                    }
                }

                $zip = new PclZip($save_path);
                $file_tmp_zip = $zip->create(implode(',', $file_tmp), PCLZIP_OPT_REMOVE_PATH, $tmp_dir);

                /* 下载 */
                $this->download_file($save_path);
                /* 清除解压包 */
                $this->delete_dir($tmp_dir);
                /* 清除临时zip包 */
                @unlink($save_path);
            }
        }
    }

    /**
     * 删除临时目录
     *
     * @return void
     */
    private function delete_dir($path)
    {
        $this->load->helper('file');
        delete_files($path, true);
    }

    /**
     * 下载文件
     *
     * @return void
     */
    private function download_file($file)
    {
        if (!file_exists($file)) {
            exit('文件不存在，请重试！(2)');
        }

        $filename = basename($file);
        $ua = $_SERVER["HTTP_USER_AGENT"];
        /* 处理中文文件名 */
        $encoded_filename = rawurlencode($filename);
     
        header("Content-type: application/octet-stream");

        if (preg_match("/MSIE/", $ua) || preg_match("/Edge/", $ua))
        {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header("Content-Disposition: attachment; filename*=\"utf8''" . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
     
        header("Content-Length: ". filesize($file));
        readfile($file);
    }
}
