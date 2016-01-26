<? if (!defined('BASEPATH')) exit('No direct script access allowed');
class Profile extends S_Controller
{
    private $_uinfo = array();
    
    public function __construct()
    {
        parent::__construct();
        $this->_uinfo = StudentModel::studentLoginUInfo();
    }

    public function loginFunc($param)
    {
        return StudentModel::studentAjaxLogin($param);
    }

    public function logoutFunc($url = NULL)
    {
        return StudentModel::studentAjaxLogout($url);
    }

    public function emailValidateFunc()
    {
        $resp = new AjaxResponse();
        if (!$this->_uinfo['uid'])
        {   
            $resp->alert('您还没有登录，请先登录');
            $resp->redirect(site_url('student/index/login'));
            return $resp;
        }


        // 发送邮件
        $email_tpl = C('email_template/validate');
        $mail = array(
                'student' => $this->_uinfo,
                'hash'    => email_hash('encode', $this->_uinfo['uid']),
        );

        send_email($email_tpl['subject'],$this->load->view(
            $email_tpl['tpl'], $mail, TRUE), $this->_uinfo['email']);

        $resp->alert('系统已发出邮件,请在半小时内到您的注册邮箱查收验证邮件');
        return $resp;
    }
    
    // 个人中心首页
    public function index()
    {
        Fn::ajax_call($this, 'logout', 'login', 'emailValidate');
        $uid = $this->_uinfo['uid'];

        if (!$this->_uinfo['uid'])
        {   
            redirect('student/index/login');
        }
        
        $data = array();
        $data['uinfo'] = $this->_uinfo;
        $i=0;

         if ($uid)
        {
            // 已注册用户
            $student = StudentModel::get_student($uid);
            $prefix = StudentModel::registerItemCompleteCheck($uid,'base');//,score,practice,selfwish,parentwish');
        }

        foreach ($prefix as $k => $v)
        {
            if ($v)
            {
                $i=$i+1;
            }
        }

        $data['prefix'] = $i*100;
        $data['student'] = $student; 
        $this->load->view('profile/index', $data);
    }

    // 基本信息页面
    public function basic()
    {
        $uid = $this->_uinfo['uid'];
        if ($uid)
        {
            Fn::ajax_call($this, 'login', 'logout', 'baseFetchTIList', 
                'baseFetchCORSList', 'baseFetchCTeacherList');
        }
        else
        {
            Fn::ajax_call($this, 'logout', 'login');
            $score_ranks = $this->session->userdata('score_ranks');
        }

        $data = array();
        $data['uinfo'] = $this->_uinfo;
        if ($uid)
        {
            // 已注册用户
            $student = StudentModel::get_student($uid);
            $student_fullname=trim($student['last_name']).trim($student['first_name']);
            $query = $this->db->get_where('student_ranking', array('uid'=>$uid));
            $score_ranks = $query->result_array();
            
            if (isset($student['school_id']) 
                && !empty($student['school_id']))
            {
                $school_info = SchoolModel::schoolInfo(
                    $student['school_id'], 'school_name');
            }
            $student['school_name'] = isset($school_info['school_name']) 
                ? $school_info['school_name'] : '';

            $action  = 'renew';

            /************* COPY FROM base() START**************************/
            $db = Fn::db();
            $sbinfo = $db->fetchRow("SELECT * FROM t_student_base WHERE sb_uid = $uid");
            if ($sbinfo)
            {
                $student = array_merge($student, $sbinfo);
            }
            $sql = <<<EOT
SELECT sbs_stunumtype FROM t_student_base_stunumtype WHERE sbs_uid = {$uid}
EOT;
            $student['sbs_stunumtype'] = $db->fetchCol($sql);
            $sql = <<<EOT
SELECT sbclassid_classid FROM t_student_base_classid WHERE sbclassid_uid = {$uid}
EOT;
            $student['sbclassid_classid'] = $db->fetchCol($sql);

            $sql = <<<EOT
SELECT a.*, 
b.ti_id, b.ti_name, b.ti_typeid, b.ti_flag, b.ti_provid, b.ti_cityid, b.ti_areaid,  
c.cors_id, c.cors_cmid, c.cors_name, c.cors_flag, c.cors_tiid, c.cors_stunumtype
FROM t_student_base_course a
LEFT JOIN v_training_institution b ON a.sbc_tiid = b.ti_id
LEFT JOIN v_course c ON a.sbc_corsid = c.cors_id
WHERE a.sbc_uid = {$uid} AND a.sbc_idx = 0
EOT;
            $sbcinfo = $db->fetchRow($sql);
            if (is_array($sbcinfo))
            {
                $student = array_merge($student, $sbcinfo);
                $student['no_tiid'] = 0;
            }
            else
            {
                $student['no_tiid'] = 1;
            }
            ////////////////// FOR INIT VIEW DATA //////////////////////////

            $data['province_list'] = RegionModel::get_regions(1);
            $data['city_list'] = RegionModel::get_regions($student['sb_addr_provid'], 
                FALSE, 2);
            $data['area_list'] = RegionModel::get_regions($student['sb_addr_cityid'], 
                FALSE, 3);

            // 班级类型
            $data['stunumtype_list'] = CourseModel::courseStuNumTypeList();
            // 考试类型
            $data['class_list'] = ClassModel::get_class_list($student['grade_id']);
            // 培训机构类型
            $data['tit_list'] = TrainingInstitutionModel::trainingInstitutionTypeList();
            // 课程授课模式
            $data['cm_list'] = CourseModel::courseModeList();


            /*************** COPY FROM base() END **************************/
        }
        else
        {
            $student = $this->session->userdata('student');
            $score_ranks = $this->session->userdata('score_ranks');
            if ($student === FALSE)
            {
                // 未保存过，初始化数据
                $student = array(
                    'full_name'   => '',
                    'sex'         => 0,
                    'birthday'    => '',
                    'idcard'      => '',
                    'province'    => 0,
                    'city'        => 0,
                    'area'        => 0,
                    'grade_id'    => 0,
                    'mobile'      => '',
                    'email'       => '',
                    'picture'     => '',
                );
                $action  = 'add';
            }
            else
            {
                $action  = 'edit';
            }
        }

        $dl = $_GET['dl'];

        if ((!$score_ranks)&&($student['grade_id']==6)&& ($dl !=1))
        {
            message('如你参加小升初测试，请先完善"小升初必填(学生成绩)"页面内容', 'student/profile/awards?dl=1');
        }

        if ($student['birthday']) $student['birthday'] = date('Y-m-d', $student['birthday']);
        $data['student'] = $student;
        $data['student_fullname'] = $student_fullname;
        $data['uid']     = $uid;
        $data['action']  = $action;
        $this->load->view('profile/basic', $data);
    }

    // 基本信息提交保存
    public function basic_save()
    {
        $uid    = $this->_uinfo['uid'];
        $action = $this->input->post('action');

        if ($uid)
        {
            $old = StudentModel::get_student($uid);
            if (empty($old))
            {
                StudentModel::studentAjaxLogout();
                message('信息不存在', 'student/index/login');
            }
        }

        $message = array();

        $student_fullname   = trim($this->input->post('full_name'));
        $len = mb_strlen($student_fullname,'utf-8');
        $student['last_name']   = mb_substr($student_fullname,0,1,'utf-8');
        $student['first_name']   = mb_substr($student_fullname,1,$len-1,'utf-8');
        $student['sex']       = intval($this->input->post('sex'))==1 ? 1 : 2;
        $student['birthday']  = strtotime($this->input->post('birthday'));
        $student['idcard']    = trim($this->input->post('idcard'));
        $student['external_account'] = trim($this->input->post('student_ticket'));
        $student['email'] = trim($this->input->post('email'));
        $student['grade_id']  = intval($this->input->post('grade_id'));
        $student['school_id'] = intval($this->input->post('school_id'));
        $student['school_name'] = trim($this->input->post('school_name'));
        $student['mobile']    = trim($this->input->post('mobile'));

        if (empty($student['email']) OR !is_email($student['email']))
        {
            $message[] = '请正确填写Email地址';
        }

        if (!$uid)
        {
            $password = trim($this->input->post('password'));
            $password_confirm = trim($this->input->post('password_confirm'));
            if ($action == 'add')
            {
                if (is_string($passwd_msg = is_password($password))) {
                    $message[] = $passwd_msg;
                }
                else
                {
                    $student['password']  = $password;
                }
            }
            elseif (strlen($password) > 0)
            {
                $student['password']  = $password;
            }

            if (isset($student['password']) && $password!==$password_confirm)
            {
                $message[] = '两次密码输入不一致！';
            }
        }


        if (empty($student['first_name']) || empty($student['last_name']))
        {
            $message[] = '请填写姓名';
        }

        if (empty($student['birthday']))
        {
            $message[] = '请填写出生日期';
        }

        if (empty($student['idcard']) || !is_idcard($student['idcard']))
        {
            message('请正确填写身份证号码!');
        }

        if ($student['grade_id']<1 OR $student['grade_id']>12)
        {
            $message[] = '请选择就读年级';
        }

        if (empty($student['school_id']))
        {
            $message[] = '请选择就读学校';
        }


        if (strlen($student['mobile']) > 0 && !is_phone($student['mobile']))
        {
            $message[] = '请正确填写手机号码';
        }

        // 检查email是否已注册
        $tmp_student = $this->db->select('uid, email_validate')->get_where('student',array('email'=>$student['email']))->row_array();
        if ($tmp_student && $tmp_student['uid'] != $uid)
        {
            $message[] = '该Email地址已被注册！';
        }

        //检查身份证否已注册
        $student_idcard = $this->db->select('uid')->get_where('student', array('idcard' => $student['idcard']))->row_array();
        if ($student_idcard && $student_idcard['uid'] != $uid)
        {
            $message[] = '该身份证号码已被注册';
        }

        if ($_FILES['picture']['name'])
        {
            if ($uid)
            {
                $config['upload_path'] = _UPLOAD_ROOT_PATH_.'uploads/student/'.date('Ym').'/';
            }
            else
            {
                $config['upload_path'] = _UPLOAD_ROOT_PATH_.'uploads/student/temp/'.date('Ym').'/';
            }
            $config['allowed_types'] = 'gif|jpg';
            $config['max_size'] = '1024';
            $config['max_width'] = '2000';
            $config['max_height'] = '2000';
            $config['encrypt_name'] = TRUE;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('picture'))
            {
                $student['picture'] =  $this->upload->data('file_relative_path');
            }
            else
            {
                $msg = array(
                    "头像图片限制：",
                    "1、图片大小小于 1M",
                    "2、尺寸不超过2000 x 2000像素",
                    "3、图片格式为 jpg 或 gif",
                );
                $message[] = $this->upload->display_errors() 
                    . '<hr/><font style="font-weight:bold;font-size:12px;">' 
                    . implode('</br>', $msg) . '</font><hr/>';
            }
        }

        if ($message)
        {
            if (!empty($student['picture']))
            {
                @unlink(_UPLOAD_ROOT_PATH_.$student['picture']);
            }

            message(implode('<br/>', $message));
        }

        /*************** COPY FROM base_save() START ***********************/
        if ($uid)
        {
            $student2 = array();
            $student2['grade_id']  = intval($this->input->post('grade_id'));
            //$student2['address']   = trim($this->input->post('address'));
            $student2['zipcode']   = trim($this->input->post('zipcode'));

            $sbinfo = array();
            $sbinfo['sb_addr_provid'] = intval($this->input->post('sb_addr_provid'));
            $sbinfo['sb_addr_cityid'] = intval($this->input->post('sb_addr_cityid'));
            $sbinfo['sb_addr_areaid'] = intval($this->input->post('sb_addr_areaid'));
            $sbinfo['sb_addr_desc'] = trim($this->input->post('sb_addr_desc'));

            // 培训机构、培训课程、授课教师
            $sbcinfo = array();
            $sbcinfo['no_tiid'] = intval($this->input->post('no_tiid'));
            $sbcinfo['sbc_tiid'] = intval($this->input->post('sbc_tiid'));
            $sbcinfo['ti_name']   = trim($this->input->post('ti_name'));
            $sbcinfo['sbc_corsid'] = intval($this->input->post('sbc_corsid'));
            $sbcinfo['cors_cmid'] = intval($this->input->post('cors_cmid'));
            $sbcinfo['cors_name']   = trim($this->input->post('cors_name'));
            $sbcinfo['sbc_teachers']   = trim($this->input->post('sbc_teachers'));

            $sbs_stunumtype = $this->input->post('sbs_stunumtype');
            if (!is_array($sbs_stunumtype))
            {
                $sbs_stunumtype = array();
            }

            $sbclassid_classid = $this->input->post('sbclassid_classid');
            if (!is_array($sbclassid_classid))
            {
                $sbclassid_classid = array();
            }

            /*if (empty($student2['address']))
            {
                $message[] = '请填写家庭地址';
            }*/
            if (empty($student2['zipcode']))
            {
                $message[] = '请填写邮编';
            }
            if ($sbinfo['sb_addr_provid'] == 0)
            {
                $message[] = '请填写家庭所在省市';
            }
            if ($sbinfo['sb_addr_desc'] == '')
            {
                $message[] = '请填写家庭住址';
            }
            if (empty($sbcinfo['no_tiid']))
            {
                if ($sbcinfo['ti_name'] == '')
                {
                    $message[] = '请填写培训机构';
                }
                if ($sbcinfo['cors_name'] == '')
                {
                    $message[] = '请填写培训课程';
                }
                if ($sbcinfo['sbc_teachers'] == '')
                {
                    $message[] = '请填写授课教师';
                }
            }
            if (empty($sbs_stunumtype))
            {
                $message[] = '请选择可接受授课模式';
            }
            if (empty($sbclassid_classid))
            {
                $message[] = '请选择希望辅导难度';
            }
        
            if ($message)
            {
                message(implode('<br/>', $message));
            }
        }
        /*************** COPY FROM base() END ******************************/

        if ($uid)
        {
            // 在用户修改信息时才发,注册不发邮件
            if (empty($tmp_student) || !$tmp_student['email_validate'])
            {
                $student['email_validate'] = 0;
                // 发送邮件
                $email_tpl = C('email_template/register');
                $mail = array(
                        'student' => $student,
                        'hash'    => email_hash('encode', $uid),
                );

                send_email($email_tpl['subject'],$this->load->view(
                    $email_tpl['tpl'], $mail, TRUE), $student['email']);
            }
        }

        if ($uid)
        {
            unset($student['password']);
            if (isset($student['external_account'])) unset($student['external_account']);

            // 已注册，更新数据库
            if (isset($student['school_name'])) unset($student['school_name']);
            unset($student['uid']);
            Fn::db()->update('rd_student', $student, 'uid = ' . $uid);
            if (!empty($student['picture']) && $old['picture'])
            {
                @unlink(_UPLOAD_ROOT_PATH_.$old['picture']);
            }
            StudentModel::studentUpdateSession();

            /****************** COPY FROM base_save() START ****************/
            unset($student2['grade_id']);
            // 已注册，更新数据库
            $db = Fn::db();
            $bOk = false;
            try
            {
                if ($db->beginTransaction())
                {
                    $db->update('rd_student', $student2, "uid = $uid");
                    $db->delete('t_student_base', "sb_uid = $uid");
                    $sbinfo['sb_uid'] = $uid;
                    $db->insert('t_student_base', $sbinfo);

                    $db->delete('t_student_base_classid', "sbclassid_uid = $uid");
                    foreach ($sbclassid_classid as $v)
                    {
                        $db->insert('t_student_base_classid', 
                            array('sbclassid_uid' => $uid,
                            'sbclassid_classid' => $v));
                    }

                    $db->delete('t_student_base_stunumtype', "sbs_uid = $uid");
                    foreach ($sbs_stunumtype as $v)
                    {
                        $db->insert('t_student_base_stunumtype', 
                            array('sbs_uid' => $uid,
                            'sbs_stunumtype' => $v));
                    }

                    $db->delete('t_student_base_course', 'sbc_uid = ' . $uid);
                    if (empty($sbcinfo['no_tiid']))
                    {
                        $now_time = time();
                        if (!$sbcinfo['sbc_tiid'])
                        {
                            $row = array(
                                'ti_name' => $sbcinfo['ti_name'],
                                'ti_typeid' => 1,// 培训学校
                                'ti_flag' => $now_time,
                                'ti_priid' => 0,
                                'ti_provid' => $sbinfo['sb_addr_provid'],
                                'ti_cityid' => $sbinfo['sb_addr_cityid'],
                                'ti_areaid' => $sbinfo['sb_addr_areaid'],
                                'ti_addtime' => date('Y-m-d H:i:s', $now_time),
                                'ti_adduid' => 1);

                            $db->insert('t_training_institution', $row);
                            $ti_id = $db->lastInsertId(
                                't_training_institution', 'ti_id');
                            $sbcinfo['sbc_tiid'] = $ti_id;
                        }
                        if (!$sbcinfo['sbc_corsid'])
                        {
                            if ($sbcinfo['cors_cmid'] != 1)
                            {
                                $sbcinfo['cors_cmid']  = 2;
                            }
                            $row = array(
                                'cors_name' => $sbcinfo['cors_name'],
                                'cors_cmid' => $sbcinfo['cors_cmid'],
                                'cors_flag' => $now_time,
                                'cors_tiid' => $sbcinfo['sbc_tiid'],
                                'cors_stunumtype' => $sbcinfo['cors_cmid'],
                                'cors_addtime' => date('Y-m-d H:i:s', $now_time),
                                'cors_adduid' => 1,
                                'cors_lastmodify' => date('Y-m-d H:i:s', $now_time));
                            $db->insert('t_course', $row);
                            $cors_id = $db->lastInsertId('t_course', 'cors_id');
                            $sbcinfo['sbc_corsid'] = $cors_id;
                        }
                        $db->insert('t_student_base_course', 
                            array(
                            'sbc_uid' => $uid,
                            'sbc_idx' => 0,
                            'sbc_tiid' => $sbcinfo['sbc_tiid'],
                            'sbc_corsid' => $sbcinfo['sbc_corsid'],
                            'sbc_teachers' => $sbcinfo['sbc_teachers']));
                    }

                    $bOk = $db->commit();
                    if (!$bOk)
                    {
                        $err = $db->errorInfo()[2];
                        $db->rollBack();
                        message('学习概况保存失败(' . $err . ')');
                    }
                }
                if (!$bOk)
                {
                    message('学习概况保存失败(执行事务处理失败)');
                }
            }
            catch (Exception $e)
            {
                message('学习概况保存失败(' . $e->getMessage() . ')');
            }
            /*************** COPY FROM base_save() END ********************/
        }
        else
        {
            isset($student['password']) && $student['password'] = my_md5($student['password']);
            if (!isset($student['picture'])) $student['picture'] = '';
            $old = $this->session->userdata('student');
            if ($old)
            {
                if (empty($student['password']))
                {
                    $student['password'] = $old['password'];
                }
                if (!empty($old['picture']))
                {
                    if (empty($student['picture']))
                    {
                        $student['picture'] = $old['picture'];
                    }
                    else
                    {
                        @unlink(_UPLOAD_ROOT_PATH_.$old['picture']);
                    }
                }
            }
            // 未注册，更新session
            $this->session->set_userdata(array('student'=>$student));
        }

        if (!$uid && C('register_simple'))
        {
            $this->session->set_userdata('complete', 1);
            redirect('student/profile/submit_simple');
        }
        else if ($uid OR $this->session->userdata('complete'))
        {
            message('基本信息和学习概况修改成功', 'student/profile/preview', 'success');
        }
        else
        {
            redirect('student/profile/preview');
            //redirect('student/profile/base');
        }
    }

    public function baseFetchTIListFunc($param)
    {
        $resp = new AjaxResponse();
        if (!$this->_uinfo['uid'])
        {
            $resp->redirect(site_url('student/index/login'));
            return $resp;
        }

        $param = Func::param_copy($param, 'ti_typeid', 'ti_provid', 'ti_cityid',
            'ti_areaid', 'ti_name');
        if ($param['ti_provid'] == 0)
        {
            $resp->alert('请选择省');
            return $resp;
        }
        if ($param['ti_cityid'] == 0)
        {
            unset($param['ti_cityid']);
        }
        if ($param['ti_areaid'] == 0)
        {
            unset($param['ti_areaid']);
        }
        if (empty($param['ti_typeid']))
        {
            unset($param['ti_typeid']);
        }
        try
        {
            $ti_list = TrainingInstitutionModel::trainingInstitutionList(
               'ti_id,ti_name', $param);
            $resp->call('fnSetTIListDiv', $ti_list);
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    public function baseFetchCORSListFunc($param)
    {
        $resp = new AjaxResponse();
        if (!$this->_uinfo['uid'])
        {
            $resp->redirect(site_url('student/index/login'));
            return $resp;
        }

        $param = Func::param_copy($param, 'cors_tiid', 'cors_name', 'cors_cmid');
        if (empty($param['cors_tiid']))
        {
            $resp->alert('请先选择培训机构');
            return $resp;
        }
        if ($param['cors_name'] == '')
        {
            unset($param['cors_name']);
        }
        if ($param['cors_cmid'] == '')
        {
            unset($param['cors_cmid']);
        }
        try
        {
            $cors_list = CourseModel::courseList('cors_id,cors_name,cors_cmid', $param);
            if (!empty($cors_list))
            {
                $cors_id_arr = array();
                foreach ($cors_list as $v)
                {
                    $cors_id_arr[] = $v['cors_id'];
                }
                $cors_id_str = implode(',', $cors_id_arr);

                $uid = $this->_uinfo['uid'];
                if ($uid)
                {
                    $student = StudentModel::get_student($uid);
                }
                else
                {
                    $student = $this->session->userdata('student');
                }
                $grade_id = $student['grade_id'];


                $sql = <<<EOT
SELECT DISTINCT cg_corsid FROM t_course_gradeid 
WHERE cg_corsid IN ({$cors_id_str}) AND (cg_gradeid = 0 OR cg_gradeid = {$grade_id})
EOT;
                $cors_id2_arr = Fn::db()->fetchCol($sql);
                if (!empty($cors_id2_arr))
                {
                    $cors_id2_str = implode(',', $cors_id2_arr);
                    $sql = <<<EOT
SELECT cors_id, cors_name, cors_cmid FROM t_course WHERE cors_id IN ({$cors_id2_str})
EOT;
                    $cors_list2 = Fn::db()->fetchAll($sql);
                    $resp->call('fnSetCORSListDiv', $cors_list2);
                }
                else
                {
                    $resp->call('fnSetCORSListDiv', $cors_list);
                }
            }
            else
            {
                $resp->call('fnSetCORSListDiv', array());
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    public function baseFetchCTeacherListFunc($param)
    {
        $resp = new AjaxResponse();
        if (!$this->_uinfo['uid'])
        {
            $resp->redirect(site_url('student/index/login'));
            return $resp;
        }

        $param = Func::param_copy($param, 'cteacher_name', 'cors_id');
        if (empty($param['cors_id']))
        {
            $resp->alert('请选择培训课程');
            return $resp;
        }
        if (empty($param['cteacher_name']))
        {
            unset($param['cteacher_name']);
        }
        try
        {
            $cors_id = $param['cors_id'];
            $sql = <<<EOT
SELECT ct_id, ct_name FROM v_course_campus_teacher WHERE cct_ccid IN (
    SELECT cc_id FROM t_course_campus WHERE cc_corsid = {$cors_id}
)
EOT;
            $ct_map = Fn::db()->fetchPairs($sql);
            if (!empty($ct_map))
            {
                $ct_id_list = array_keys($ct_map);
                $ct_id_str = implode(',', $ct_id_list);

                $uid = $this->_uinfo['uid'];
                if ($uid)
                {
                    $student = StudentModel::get_student($uid);
                }
                else
                {
                    $student = $this->session->userdata('student');
                }
                $grade_id = $student['grade_id'];
                $sql = <<<EOT
SELECT DISTINCT ctg_ctid FROM t_cteacher_gradeid 
WHERE ctg_ctid IN ({$ct_id_str}) AND (ctg_gradeid = 0 OR ctg_gradeid = {$grade_id})
EOT;
                $ct_id2 = Fn::db()->fetchCol($sql);
                $ct_list = array();
                foreach ($ct_map as $k => $v)
                {
                    if (in_array($k, $ct_id2))
                    {
                        $ct_list[] = array('ct_id' => $k, 'ct_name' => $v);
                    }
                }
                $resp->call('fnSetCTeacherListDiv', $ct_list);
            }
            else
            {
                $resp->call('fnSetCTeacherListDiv', array());
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    // 学习概况页面
    public function base()
    {
        Fn::ajax_call($this, 'login', 'logout', 'baseFetchTIList', 
            'baseFetchCORSList', 'baseFetchCTeacherList');

        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            // 已注册用户
            $student = StudentModel::get_student($uid);
            $action  = 'renew';

            /*
            if (isset($student['school_id']) 
                && !empty($student['school_id']))
            {
                $school_info = SchoolModel::schoolInfo(
                    $student['school_id'], 'school_name');
            }
            $student['school_name'] = isset($school_info['school_name']) 
                ? $school_info['school_name'] : '';
             */

            $db = Fn::db();
            $sbinfo = $db->fetchRow("SELECT * FROM t_student_base WHERE sb_uid = $uid");
            if ($sbinfo)
            {
                $student = array_merge($student, $sbinfo);
            }
            $sql = <<<EOT
SELECT sbs_stunumtype FROM t_student_base_stunumtype WHERE sbs_uid = {$uid}
EOT;
            $student['sbs_stunumtype'] = $db->fetchCol($sql);
            $sql = <<<EOT
SELECT sbclassid_classid FROM t_student_base_classid WHERE sbclassid_uid = {$uid}
EOT;
            $student['sbclassid_classid'] = $db->fetchCol($sql);

            $sql = <<<EOT
SELECT a.*, 
b.ti_id, b.ti_name, b.ti_typeid, b.ti_flag, b.ti_provid, b.ti_cityid, b.ti_areaid,  
c.cors_id, c.cors_cmid, c.cors_name, c.cors_flag, c.cors_tiid, c.cors_stunumtype
FROM t_student_base_course a
LEFT JOIN v_training_institution b ON a.sbc_tiid = b.ti_id
LEFT JOIN v_course c ON a.sbc_corsid = c.cors_id
WHERE a.sbc_uid = {$uid} AND a.sbc_idx = 0
EOT;
            $sbcinfo = $db->fetchRow($sql);
            if (is_array($sbcinfo))
            {
                $student = array_merge($student, $sbcinfo);
                $student['no_tiid'] = 0;
            }
            else
            {
                $student['no_tiid'] = 1;
            }
        }
        else
        {
            $student = $this->session->userdata('student');
            if (!$student)
            {
                message('请先填写基本信息!', 'student/profile/basic');
            }
            $grade_id = $student['grade_id'];
            $student = $this->session->userdata('student_base');
            if (!is_array($student))
            {
                $student['grade_id'] = $grade_id;
            }
            if (!isset($student['sbs_stunumtype'])
                || !is_array($student['sbs_stunumtype']))
            {
                $student['sbs_stunumtype'] = array();
            }
            if (!isset($student['sbclassid_classid'])
                || !is_array($student['sbclassid_classid']))
            {
                $student['sbclassid_classid'] = array();
            }
        }

        $data['uid'] = $uid;
        $data['student'] = $student;
        //$data['school_list'] = SchoolModel::get_schools();


        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($student['sb_addr_provid'], 
            FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($student['sb_addr_cityid'], 
            FALSE, 3);

        // 班级类型
        $data['stunumtype_list'] = CourseModel::courseStuNumTypeList();
        // 考试类型
        $data['class_list'] = ClassModel::get_class_list($student['grade_id']);
        // 培训机构类型
        $data['tit_list'] = TrainingInstitutionModel::trainingInstitutionTypeList();
        // 课程授课模式
        $data['cm_list'] = CourseModel::courseModeList();
        // 模版
        $this->load->view('profile/base', $data);
    }

    // 学习概况提交保存
    public function base_save()
    {
        $uid    = $this->_uinfo['uid'];

        if (empty($_POST))
        {
            redirect('student/profile/base');
        }

        //$action = $this->input->post('action');
        
        if ($uid)
        {
            $student = StudentModel::get_student($uid);
            if (empty($student))
            {
                StudentModel::studentAjaxLogout();
                redirect('student/index/login');
            }
        }
        else
        {
            $student = $this->session->userdata('student');
            if (empty($student))
            {
                redirect('student/profile/basic');
            }
        }

        $message = array();
        
        $student = array();
        $student['grade_id']  = intval($this->input->post('grade_id'));
        $student['address']   = trim($this->input->post('address'));
        $student['zipcode']   = trim($this->input->post('zipcode'));

        $sbinfo = array();
        $sbinfo['sb_addr_provid'] = intval($this->input->post('sb_addr_provid'));
        $sbinfo['sb_addr_cityid'] = intval($this->input->post('sb_addr_cityid'));
        $sbinfo['sb_addr_areaid'] = intval($this->input->post('sb_addr_areaid'));
        $sbinfo['sb_addr_desc'] = trim($this->input->post('sb_addr_desc'));

        // 培训机构、培训课程、授课教师
        $sbcinfo = array();
        $sbcinfo['no_tiid'] = intval($this->input->post('no_tiid'));
        $sbcinfo['sbc_tiid'] = intval($this->input->post('sbc_tiid'));
        $sbcinfo['ti_name']   = trim($this->input->post('ti_name'));
        $sbcinfo['sbc_corsid'] = intval($this->input->post('sbc_corsid'));
        $sbcinfo['cors_cmid'] = intval($this->input->post('cors_cmid'));
        $sbcinfo['cors_name']   = trim($this->input->post('cors_name'));
        $sbcinfo['sbc_teachers']   = trim($this->input->post('sbc_teachers'));

        $sbs_stunumtype = $this->input->post('sbs_stunumtype');
        if (!is_array($sbs_stunumtype))
        {
            $sbs_stunumtype = array();
        }

        $sbclassid_classid = $this->input->post('sbclassid_classid');
        if (!is_array($sbclassid_classid))
        {
            $sbclassid_classid = array();
        }

        if (empty($student['address']))
        {
            $message[] = '请填写家庭地址';
        }
        if (empty($student['zipcode']))
        {
            $message[] = '请填写邮编';
        }
        if ($sbinfo['sb_addr_provid'] == 0)
        {
            $message[] = '请填写家庭所在省市';
        }
        if ($sbinfo['sb_addr_desc'] == '')
        {
            $message[] = '请填写家庭住址';
        }
        if (empty($sbcinfo['no_tiid']))
        {
            if ($sbcinfo['ti_name'] == '')
            {
                $message[] = '请填写培训机构';
            }
            if ($sbcinfo['cors_name'] == '')
            {
                $message[] = '请填写培训课程';
            }
            if ($sbcinfo['sbc_teachers'] == '')
            {
                $message[] = '请填写授课教师';
            }
        }
        if (empty($sbs_stunumtype))
        {
            $message[] = '请选择可接受授课模式';
        }
        if (empty($sbclassid_classid))
        {
            $message[] = '请选择希望辅导难度';
        }
    
        if ($message)
        {
            message(implode('<br/>', $message));
        }

        if ($uid)
        {
            unset($student['grade_id']);
            // 已注册，更新数据库
            $db = Fn::db();
            $bOk = false;
            try
            {
                if ($db->beginTransaction())
                {
                    $db->update('rd_student', $student, "uid = $uid");
                    $db->delete('t_student_base', "sb_uid = $uid");
                    $sbinfo['sb_uid'] = $uid;
                    $db->insert('t_student_base', $sbinfo);

                    $db->delete('t_student_base_classid', "sbclassid_uid = $uid");
                    foreach ($sbclassid_classid as $v)
                    {
                        $db->insert('t_student_base_classid', 
                            array('sbclassid_uid' => $uid,
                            'sbclassid_classid' => $v));
                    }

                    $db->delete('t_student_base_stunumtype', "sbs_uid = $uid");
                    foreach ($sbs_stunumtype as $v)
                    {
                        $db->insert('t_student_base_stunumtype', 
                            array('sbs_uid' => $uid,
                            'sbs_stunumtype' => $v));
                    }

                    $db->delete('t_student_base_course', 'sbc_uid = ' . $uid);
                    if (empty($sbcinfo['no_tiid']))
                    {
                        $now_time = time();
                        if (!$sbcinfo['sbc_tiid'])
                        {
                            $row = array(
                                'ti_name' => $sbcinfo['ti_name'],
                                'ti_typeid' => 1,// 培训学校
                                'ti_flag' => $now_time,
                                'ti_priid' => 0,
                                'ti_provid' => $sbinfo['sb_addr_provid'],
                                'ti_cityid' => $sbinfo['sb_addr_cityid'],
                                'ti_areaid' => $sbinfo['sb_addr_areaid'],
                                'ti_addtime' => date('Y-m-d H:i:s', $now_time),
                                'ti_adduid' => 1);

                            $db->insert('t_training_institution', $row);
                            $ti_id = $db->lastInsertId(
                                't_training_institution', 'ti_id');
                            $sbcinfo['sbc_tiid'] = $ti_id;
                        }
                        if (!$sbcinfo['sbc_corsid'])
                        {
                            if ($sbcinfo['cors_cmid'] != 1)
                            {
                                $sbcinfo['cors_cmid']  = 2;
                            }
                            $row = array(
                                'cors_name' => $sbcinfo['cors_name'],
                                'cors_cmid' => $sbcinfo['cors_cmid'],
                                'cors_flag' => $now_time,
                                'cors_tiid' => $sbcinfo['sbc_tiid'],
                                'cors_stunumtype' => $sbcinfo['cors_cmid'],
                                'cors_addtime' => date('Y-m-d H:i:s', $now_time),
                                'cors_adduid' => 1,
                                'cors_lastmodify' => date('Y-m-d H:i:s', $now_time));
                            $db->insert('t_course', $row);
                            $cors_id = $db->lastInsertId('t_course', 'cors_id');
                            $sbcinfo['sbc_corsid'] = $cors_id;
                        }
                        $db->insert('t_student_base_course', 
                            array(
                            'sbc_uid' => $uid,
                            'sbc_idx' => 0,
                            'sbc_tiid' => $sbcinfo['sbc_tiid'],
                            'sbc_corsid' => $sbcinfo['sbc_corsid'],
                            'sbc_teachers' => $sbcinfo['sbc_teachers']));
                    }

                    $bOk = $db->commit();
                    if (!$bOk)
                    {
                        $err = $db->errorInfo()[2];
                        $db->rollBack();
                        message($err);
                    }
                }
                if (!$bOk)
                {
                    message('执行事务处理失败');
                }
            }
            catch (Exception $e)
            {
                message($e->getMessage());
            }
        }
        else
        {
            $this->session->set_userdata(
                array('student_base'=> 
                    array_merge($student, $sbinfo, $sbcinfo,
                        array('sbclassid_classid' => $sbclassid_classid,
                            'sbs_stunumtype' => $sbs_stunumtype))));
        }

        if ($uid OR $this->session->userdata('complete'))
        {
            message('学习概况修改成功', 'student/profile/preview', 'success');
        }
        else
        {
            redirect('student/profile/preview');
            //redirect('student/profile/awards');
        }
    }

    // 学习成绩页面
    public function awards()
    {
        Fn::ajax_call($this, 'login', 'logout');

        $uid = $this->_uinfo['uid'];

        if (!$uid)
        {
            redirect('student/index/login');
        }

        $data = array();
        $data['uinfo'] = $this->_uinfo;
        $i = 1;
        $school_id =array();
        $volunteer =array();

        if ($uid)
        {
            // 已注册用户
            $student = StudentModel::get_student($uid);


            //选考学考
            /*$query = $this->db->get_where('xuekao_xuankao', array('uid'=>$uid));
            $xuekao_xuankao = $query->row_array();*/


            // 成绩排名
            $this->db->order_by('grade_id ASC');
            $query = $this->db->get_where('student_ranking', array('uid'=>$uid));
            $score_ranks = $query->result_array();

            // 竞赛成绩
            $query = $this->db->get_where('student_awards', array('uid'=>$uid));
            $awards_list = array();
            foreach ($query->result_array() as $row)
            {
                $awards_list[$row['typeid']][] = $row;
            }

            $action  = 'renew';
             /************* COPY FROM base() START**************************/
            $db = Fn::db();
            $sbinfo = $db->fetchRow("SELECT * FROM t_student_base WHERE sb_uid = $uid");
            if ($sbinfo)
            {
                $student = array_merge($student, $sbinfo);
            }
            $sql = <<<EOT
SELECT sbs_stunumtype FROM t_student_base_stunumtype WHERE sbs_uid = {$uid}
EOT;
            $student['sbs_stunumtype'] = $db->fetchCol($sql);
            $sql = <<<EOT
SELECT sbclassid_classid FROM t_student_base_classid WHERE sbclassid_uid = {$uid}
EOT;
            $student['sbclassid_classid'] = $db->fetchCol($sql);

            $sql = <<<EOT
SELECT a.*, 
b.ti_id, b.ti_name, b.ti_typeid, b.ti_flag, b.ti_provid, b.ti_cityid, b.ti_areaid,  
c.cors_id, c.cors_cmid, c.cors_name, c.cors_flag, c.cors_tiid, c.cors_stunumtype
FROM t_student_base_course a
LEFT JOIN v_training_institution b ON a.sbc_tiid = b.ti_id
LEFT JOIN v_course c ON a.sbc_corsid = c.cors_id
WHERE a.sbc_uid = {$uid} AND a.sbc_idx = 0
EOT;
            $sbcinfo = $db->fetchRow($sql);
            if (is_array($sbcinfo))
            {
                $student = array_merge($student, $sbcinfo);
                $student['no_tiid'] = 0;
            }
            else
            {
                $student['no_tiid'] = 1;
            }
            ////////////////// FOR INIT VIEW DATA //////////////////////////

            $data['province_list'] = RegionModel::get_regions(1);
            $data['city_list'] = RegionModel::get_regions($student['sb_addr_provid'], 
                FALSE, 2);
            $data['area_list'] = RegionModel::get_regions($student['sb_addr_cityid'], 
                FALSE, 3);

            /*************** COPY FROM base() END **************************/
            $swv = $db->fetchOne("SELECT volunteer FROM rd_student_wish WHERE uid = $uid");
            $school_id_arr=json_decode($swv);
           

            if ($swv)
            {
                foreach ($school_id_arr as $k => $v)
                {
                    $school_id[$k]=$v;
                    if ($v!=0)
                    {
                        $volunteer[$k] = $db->fetchOne("SELECT school_name FROM rd_school WHERE school_id = $v");
                    }
                }
            } 
        }
        else
        {
            $student = $this->session->userdata('student');
            $score_ranks = $this->session->userdata('score_ranks');
            $awards_list = $this->session->userdata('awards_list');
            //$xuekao_xuankao = $this->session->userdata('xuekao_xuankao');
        }

        $dl =$_GET['dl'];

        if ((!$score_ranks)&&($student['grade_id']==6)&& $dl !=1)
        {
            message('如你参加小升初测试，请先完善"学生成绩"页面内容','/student/profile/awards?dl=1');
        } 
        //$data['xuekao_xuankao']     = $xuekao_xuankao;

        $data['student']     = $student;
        $data['school_id']     = $school_id;
        $data['volunteer']     = $volunteer;
        $data['score_ranks'] = $score_ranks;
        $data['awards_list'] = $awards_list;
        $data['uid']         = $uid;
        $data['subjects']     = C('subject');
        $data['ranks']     = array('1'=>'A','2'=>'B','3'=>'C','4'=>'E');

        // 模版
        $this->load->view('profile/awards', $data);
    }

   ///划分等级 
    private function _get_grade(array &$list)
    {
        $rank_arr =  array_values($list);
        rsort($rank_arr);
        $size = count($rank_arr);
        if ($size < 5)
        {
            $rank_arr = array_unique($rank_arr);
            foreach ($list as $key =>$val)
            {
                if ($val >= $rank_arr[0] && $rank_arr[0])
                {
                    $new_list[$key] = 1;
                }
                else if ($val >= $rank_arr[1]&& $rank_arr[1])
                {
                    $new_list[$key] = 2;
                }
                else if ($val >= $rank_arr[2] && $rank_arr[2])
                {
                    $new_list[$key] = 3;
                }
                else if ($val >= $rank_arr[3] && $rank_arr[3])
                {
                    $new_list[$key] = 4;
                }
                else if ($val >= $rank_arr[4] && $rank_arr[4])
                {
                    $new_list[$key] = 5;
                }
            }
        }
        else
        {
            foreach ($list as $key =>$val)
            {
                if (($val>= $rank_arr[ceil($size*0.2-1)])&&($rank_arr[ceil($size*0.2-1)]))
                {
                    $new_list[$key] = 1;
                }
                else  if (($val>= $rank_arr[ceil($size*0.4-1)])&&($rank_arr[ceil($size*0.4-1)]))
                {
                    $new_list[$key] = 2;
                }
                else  if (($val>= $rank_arr[ceil($size*0.6-1)])&&($rank_arr[ceil($size*0.6-1)]))
                {
                    $new_list[$key] = 3;
                }
                else  if (($val>= $rank_arr[ceil($size*0.8-1)])&&($rank_arr[ceil($size*0.8-1)]))
                {
                    $new_list[$key] = 4;
                }
                else 
                {
                    $new_list[$key] = 5;
                }

            }
        
        }
        return $new_list;
        
    }
    // 错题集  
    public function errorlist()
    {
        Fn::ajax_call($this, 'login', 'logout');
        
        $uid = $this->_uinfo['uid'];//用户id
        $data = array();//视图显示数据
        $current_exam = array();//当前考试期次信息
        $db = Fn::db();

        $search = array();
        $query = array();
        $mode = $this->input->get('search');
        $search['exam_pid'] = $this->input->get('search_exam_pid'); 
        $search['exam_id'] = $this->input->get('search_exam_id'); 
        $query['knowledge'] = intval($this->input->get('search_knowledge_id'));
        $query['qtype'] = $this->input->get('search_qtype_id');
        $query['difficulty'] = intval($this->input->get('search_difficulty_id'));
        $query['method_tactic'] = intval($this->input->get('search_method_tactic_id'));
        $query['group_type'] = intval($this->input->get('search_group_type_id'));

        if (!$uid)
        {
            redirect('student/index/login');
        }
        
        if ($query['knowledge'])
        {
            $current_exam['knowledge'] = $query['knowledge'];
        }
        
        if ($query['difficulty'])
        {
            $current_exam['difficulty'] = $query['difficulty'];
        }

        if ($query['method_tactic'])
        {
            $current_exam['method_tactic'] = $query['method_tactic'];
        }
        
        if ($query['group_type'])
        {
            $current_exam['group_type'] = $query['group_type'];
        }

        if (trim($query['qtype'])=='')
        {
        }
        else
        {
            $current_exam['qtype'] = $query['qtype'];
        }
        
        // 不显示能力测试那些
        $sql = <<<EOT
SELECT DISTINCT a.exam_pid, b.exam_name
FROM rd_exam_test_result a
LEFT JOIN rd_exam b ON a.exam_pid = b.exam_id  
LEFT JOIN rd_exam c ON a.exam_id = c.exam_id
WHERE (a.test_score < a.full_score) AND uid = {$uid} 
AND a.exam_pid NOT IN (SELECT dec_exam_pid FROM rd_demo_exam_config)
AND c.subject_id NOT IN (13, 14, 15, 16)
ORDER BY b.addtime DESC
EOT;

        $exam_pid_arr=$db->fetchAll($sql);//获取所有错题期次
        $data['exam_pid_arr'] =$exam_pid_arr;//所有错题期次
        
        if ($exam_pid_arr)
        {
            $current_exam['exam_pid'] = $search['exam_pid'] ? $search['exam_pid']:$exam_pid_arr[0]['exam_pid'];//当前期次
            $sql = <<<EOT
SELECT  DISTINCT b.subject_id,a.exam_id
FROM rd_exam_test_result a
LEFT JOIN rd_exam b On a.exam_id = b.exam_id  
WHERE (test_score < full_score) AND a.uid = {$uid} AND a.exam_pid ={$current_exam['exam_pid']}
ORDER BY b.subject_id ASC 
EOT;
            $exam_subject_arrs = $db->fetchAssoc($sql);//右侧学科及期次信息
            if ($exam_subject_arrs[10])
            {
                unset($exam_subject_arrs[4]);
                unset($exam_subject_arrs[22]);//学科分开不显示
            }

            if ($exam_subject_arrs[21])
            {
                unset($exam_subject_arrs[20]);
                unset($exam_subject_arrs[19]);//分开学科不显示
            }///过滤
            $exam_subject_arr = array_values($exam_subject_arrs);//所有期次下学科id
            $current_exam['exam_id'] = $search['exam_id'] ?$search['exam_id']:$exam_subject_arr[0]['exam_id'];
            $data['exam_subject_arr'] = $exam_subject_arr;//所有错题学科
        }
       
        $sql2 = <<<EOT
SELECT * FROM rd_exam WHERE exam_id = ? 
EOT;
       $exam_id = $current_exam['exam_id'];
       $exam_info = $db->fetchRow($sql2,$exam_id);//当前期次信息
       $stu_info = StudentModel::get_student($uid);//学生信息

            if ($exam_id)
            {
                $sql =<<<EOT
SELECT place_id FROM rd_exam_test_paper WHERE exam_id ={$current_exam['exam_id']} AND exam_pid = {$current_exam['exam_pid']} AND uid={$uid}
EOT;
                $place_id = $db->fetchOne($sql);//当前考场id

                if ($place_id)
                { 
                    $sql =<<<EOT
SELECT * FROM rd_exam_place WHERE place_id = {$place_id} 
EOT;
                    $exam_place_info = $db->fetchRow($sql);//当前考场信息
                
                }
                    $sql =<<<EOT
SELECT * FROM rd_exam WHERE exam_id = {$current_exam['exam_pid']} 
EOT;
                $exam_pid_info = $db->fetchRow($sql);//父期次信息

                if ($exam_pid_info['exam_isfree']==1)
                {
                    $sql =<<<EOT
SELECT sfe_starttime FROM t_student_free_exam WHERE sfe_placeid = {$place_id}  AND sfe_uid = {$uid} ORDER BY sfe_starttime DESC
EOT;
                    $current_exam['do_time'] =$db->fetchOne($sql);//自由考时间
                }
                else
                {
                    $current_exam['do_time'] = $exam_place_info['start_time'];//非自由考做题时间
                }
                
                if ($ques_qtype_arr)
                {
                    foreach ($ques_qtype_arr as $val)
                    {
                        $subject_qtype_id []=$val; 
                    }
                }
            }
            //通过当前学科，获取目前所在期次，若未选择学科，则当前所在期次为父期次下的所有学科	
            if ($exam_id)
            {
                $sql = <<<EOT
SELECT  DISTINCT a.ques_id FROM rd_exam_test_result a 
LEFT JOIN rd_question b ON a.ques_id = b.ques_id
WHERE (test_score < full_score) AND uid = $uid AND exam_id = $exam_id AND type<>12
EOT;
                $ques_id_arr = $db->fetchCol($sql);
                $ques_ids = implode(',',$ques_id_arr);//获取所有错题id
            }

            if ($ques_ids)
            {
                 $sql =<<<EOT
SELECT DISTINCT ques_id FROM rd_question  WHERE (ques_id IN ($ques_ids) OR parent_id IN ($ques_ids)) AND type <>12 
EOT;
                 $all_ques_arr=$db->fetchCol($sql);
                 $all_ques_str=implode(',',$all_ques_arr);//获取所有错题及子题id
            }
            ////////当前期次下所有错题方法策略列表///////////////////////////////////
            if ($exam_info['subject_id']!=3)
            {
                if ($all_ques_str)
                {
                    $sql =<<<EOT
SELECT DISTINCT a.method_tactic_id ,b.name FROM rd_relate_method_tactic a 
LEFT JOIN rd_method_tactic b ON a.method_tactic_id = b.id 
WHERE  a.ques_id IN ({$all_ques_str})
EOT;
                    $method_tactic_arr = $db->fetchPairs($sql);
                }
                if ($method_tactic_arr)
                {
                    $data['method_tactic_arr'] = $method_tactic_arr;//存放方法策略列表
                }
                else
                {
                    $data['method_tactic_arr'] = array();
                }
            }

            ////获取当前期次下所有错题信息提取方式列表////////
            if ($exam_info['subject_id']==3)
            {
                if ($all_ques_str)
                {
                    $sql =<<<EOT
SELECT DISTINCT a.group_type_id ,b.group_type_name FROM rd_relate_group_type a 
LEFT JOIN rd_group_type b ON a.group_type_id = b.id 
WHERE  a.ques_id IN ({$all_ques_str})
EOT;
                    $group_type_arr = $db->fetchPairs($sql);
                }
                if ($group_type_arr)
                {
                    $data['group_type_arr'] = $group_type_arr;//存放信息提取方式列表
                }
                else
                {
                    $data['group_type_arr'] = array();
                }
            }
            ////////////////获取当前期次下所有错题知识点列表///////////////////////////////////////////////////////// 
                if ($all_ques_str)
                {
                    $sql =<<<EOT
SELECT DISTINCT a.knowledge_id ,b.knowledge_name FROM rd_relate_knowledge a 
LEFT JOIN rd_knowledge b ON a.knowledge_id = b.id 
WHERE  a.ques_id IN ({$all_ques_str})
EOT;
                    $knowledge_name_arr = $db->fetchPairs($sql);
                    if ($knowledge_name_arr)
                    {
                        $data['knowledge_name_id'] = $knowledge_name_arr;//存放知识点列表
                    }
                    else
                    {
                        $data['knowledge_name_id'] = array();
                    }
                }
            ///获取当前期次下所有错题题型列表/////////////////
            $qtype = C('qtype');
            if ($ques_ids)
            {
                if ($exam_info['subject_id']==3)
                {
                    $sql =<<<EOT
SELECT  DISTINCT type FROM rd_question WHERE ques_id in ({$ques_ids}) ORDER BY FIELD(type,12,1,0,5,4,8,3,15,11,7,6,2,9,10,13,14)  
EOT;
                }
                else
                {
                    $sql =<<<EOT
SELECT  DISTINCT type FROM rd_question WHERE ques_id in ({$ques_ids}) ORDER BY FIELD(type,1,2,3,0,10,14,15,11)  
EOT;
                }   
                $ques_qtype_arr = $db->fetchCol($sql);
            }
            
            if ($ques_qtype_arr)
            {
                foreach ($ques_qtype_arr as $val)
                {
                    $subject_qtype_id[]=$val; 
                }
            }
            if ($subject_qtype_id)
            {
                $data['subject_qtype_id'] =array_unique($subject_qtype_id);//存放题型列表
            }
            else
            {
                $data['subject_qtype_id'] = array();
            }
            
            if ($exam_info['subject_id']==3)
            {
                 if ($ques_ids)
                {
                    $sql = <<<EOT
SELECT * FROM rd_question WHERE  ques_id in ($ques_ids) AND type <>12 ORDER BY FIELD(type,12,1,0,5,4,8,3,15,11,7,6,2,9,10,13,14)  
EOT;
                    $ques_list = $db->fetchAll($sql);//英语题型按此排序
                }
            }
            else
            {
                
                if ($ques_ids)
                {
	        //获取所有错题信息（含不定项，题组，单选） 	
                    $sql = <<<EOT
SELECT * FROM rd_question WHERE  ques_id in ($ques_ids) AND type <>12 ORDER BY FIELD(type,1,2,3,0,10,14,15,11)  
EOT;
                    $ques_list = $db->fetchAll($sql);
                }
            }
            ////////////////////////////////////////////////////////////////////////////////////// /
            $filter_ques_list = array();
            if (in_array($exam_info['subject_id'],array(2,4,5,6,10,11,3)))//含信息提取方式和方法策略的题型
            {
                if ($exam_info['subject_id']==3)
                {
                    $sql=<<<EOT
SELECT rgt.group_type_id, gt.group_type_name,
COUNT(rgt.group_type_id) AS gt_errors
FROM rd_exam_test_result etr
LEFT JOIN rd_relate_group_type rgt ON rgt.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
LEFT JOIN rd_group_type gt ON gt.id = rgt.group_type_id
WHERE etr.test_score < etr.full_score AND rgt.group_type_id > 0 AND uid = {$uid}
GROUP BY etr.uid,rgt.group_type_id 
EOT;
                   $gerrlist = $db->fetchAll($sql);//汇总当前用户所有错题的信息提取方式统计
                  
                    $gerrlist_map = array();

                    $gts_id = array();

                    foreach ($gerrlist as $idx => $val)
                    {
                        $gerrlist_map[$val['group_type_id']] = $idx;//排序用
                        $gts_id[$val['group_type_id']] = $val['gt_errors'];
                    }
                    
                    $gt_id_arr = $this->_get_grade($gts_id);//获取信息提取方式等级
                    
                    ///生成错误知识点与试题的对应关系
                    $errg_ques_map = array();
                    foreach ($ques_list as $idx =>$val)
                    {
                        if ($val['knowledge'])
                    {
                        $group_type_arr = array_filter(explode(',',$val['group_type']));
                    }
                    else
                    {
                         $sql=<<<EOT
SELECT group_type
FROM rd_question
WHERE parent_id = {$val['ques_id']}
EOT;
                        $group_type_arrs = $db->fetchCol($sql);
                        $group_type_str = implode(',',$group_type_arrs);
                        $group_type_arr = array_unique(array_filter(explode(',',$group_type_str)));
                    }
                    
                    foreach ($group_type_arr as $gt_id)
                    {
                        $errg_ques_map[$gt_id][] = $idx;//用于信息提取方式排序
                    }
                }
                ////对信息提取方式获取错误数量以进行排序
            
                $errg_errcnt_map = array();
                foreach (array_keys($errg_ques_map) as $gt_id)
                {
                    $errg_errcnt_map[$gt_id] = $gerrlist[$gerrlist_map[$gt_id]]['gt_errors'];
                }
            
                arsort($errg_errcnt_map,SORT_NUMERIC);
            
                //显示排序后的试题
                foreach ($errg_errcnt_map as $gt_id =>$errcnt)
                {
                    foreach ($errg_ques_map[$gt_id] as $ques_idx)
                    {
                        if (isset($ques_list[$ques_idx]))
                        {
                            $filter_ques_list[] = $ques_list[$ques_idx];
                            unset($ques_list[$ques_idx]);
                        }
                    }
                }
            }
            else
            {
                ///方式策略////////
             $sql=<<<EOT
SELECT rmt.method_tactic_id, mt.`name`,
COUNT(rmt.method_tactic_id) AS mt_errors
FROM rd_exam_test_result etr
LEFT JOIN rd_relate_method_tactic rmt ON rmt.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
LEFT JOIN rd_method_tactic mt ON mt.id = rmt.method_tactic_id
LEFT JOIN rd_subject_category_subject scs ON scs.subject_category_id = mt.subject_category_id
WHERE etr.test_score < etr.full_score AND rmt.method_tactic_id > 0 AND uid = {$uid} AND scs.subject_id = {$exam_info['subject_id']} 
GROUP BY etr.uid, rmt.method_tactic_id 
EOT;
            $merrlist = $db->fetchAll($sql);//汇总错题方法策略对应数量
            $merrlist_map = array();
            foreach ($merrlist as $idx => $val)
            {
                $merrlist_map[$val['method_tactic_id']] = $idx;//用户排序
                $mes_id[$val['method_tactic_id']] = $val['mt_errors'];
            }
            ///生成错误知识点与试题的对应关系
            $mt_id_arr = $this->_get_grade($mes_id);//获取错题方法策略等级
            $errg_ques_map = array();
            foreach ($ques_list as $idx =>$val)
            {
                if ($val['knowledge'])
                {
                    if ($val['method_tactic'])
                    {
                        $method_tactic_arr = array_filter(explode(',',$val['method_tactic']));
                    }
                    else
                    {
                        $method_tactic_type_arr = array();
                    }
                }
                else
                {
                     $sql=<<<EOT
SELECT method_tactic
FROM rd_question
WHERE parent_id = {$val['ques_id']}
EOT;
                    $method_tactic_arrs = $db->fetchCol($sql);
                    if ($method_tactic_arrs)
                    {
                        $method_tactic_str = implode(',',$method_tactic_arrs);
                        $method_tactic_type_arr = array_unique(array_filter(explode(',',$method_tactic_str)));
                    }
                    else
                    {
                         $method_tactic_type_arr = array();
                    }
                }
                foreach ($method_tactic_arr as $mt_id)
                {
                    $errm_ques_map[$mt_id][] = $idx;
                }
            }
            ////对方法策略进行排序
            
            $errm_errcnt_map = array();
            foreach (array_keys($errm_ques_map) as $mt_id)
            {
                $errm_errcnt_map[$mt_id] = $merrlist[$merrlist_map[$mt_id]]['mt_errors'];
            }
            
            arsort($errm_errcnt_map,SORT_NUMERIC);
            
            //显示排序后的试题
            foreach ($errm_errcnt_map as $mt_id =>$errcnt)
            {
                foreach ($errm_ques_map[$mt_id] as $ques_idx)
                {
                    if (isset($ques_list[$ques_idx]))
                    {
                        $filter_ques_list[] = $ques_list[$ques_idx];
                        unset($ques_list[$ques_idx]);
                    }
                }
            }
            
            }
            }

            if (count($ques_list)>0)
            {
                foreach ($ques_list as $val)
                {
                    $filter_ques_list[] = $val;
                }
            }
             //////////////////////////处理知识点排序///////////////////////////////////////////////////
            if ($exam_info['subject_id']==21)
            {
                $exam_info['subject_id'] = '19,20';
            }

            $sql=<<<EOT
SELECT rk.knowledge_id, k.knowledge_name,
COUNT(rk.knowledge_id) AS knowledge_errors
FROM rd_exam_test_result etr
LEFT JOIN rd_relate_knowledge rk ON rk.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
LEFT JOIN rd_knowledge k ON k.id = rk.knowledge_id
WHERE etr.test_score < etr.full_score AND rk.knowledge_id > 0 AND uid = {$uid} AND k.subject_id  IN ({$exam_info['subject_id']}) 
GROUP BY etr.uid, rk.knowledge_id
EOT;
            $kerrlist = $db->fetchAll($sql);//汇总错题知识点数量
            $ks_id  = array();
            $kerrlist_map = array();

            foreach ($kerrlist as $idx => $val)
            {
                $kerrlist_map[$val['knowledge_id']] = $idx;//排序
                $ks_id[$val['knowledge_id']] =$val['knowledge_errors'];
            }

            $k_id_arr = $this->_get_grade($ks_id);
            ///生成错误知识点与试题的对应关系
            $errk_ques_map = array();
            foreach ($filter_ques_list as $idx =>$val)
            {
                if ($val['knowledge'])
                {
                    $knowledge_arr = array_filter(explode(',',$val['knowledge']));
                }
                else
                {
                     $sql=<<<EOT
SELECT knowledge
FROM rd_question
WHERE parent_id = {$val['ques_id']}
EOT;
                     $knowledge_arrs = $db->fetchCol($sql);
                     $knowledge_arr_str = implode(',',$knowledge_arrs);
                    $knowledge_arr = array_unique(array_filter(explode(',',$knowledge_arr_str)));
                }

                foreach ($knowledge_arr as $k_id)
                {
                    $errk_ques_map[$k_id][] = $idx;
                }
            }
            ////对错误知识点获取错误数量以进行排序
            
            $errk_errcnt_map = array();
            foreach (array_keys($errk_ques_map) as $k_id)
            {
                $errk_errcnt_map[$k_id] = $kerrlist[$kerrlist_map[$k_id]]['knowledge_errors'];
            }
            
            arsort($errk_errcnt_map,SORT_NUMERIC);
            $error_ques_list = array();
            //显示排序后的试题
            foreach ($errk_errcnt_map as $k_id =>$errcnt)
            {
                foreach ($errk_ques_map[$k_id] as $ques_idx)
                {
                    if (isset($filter_ques_list[$ques_idx]))
                    {
                        $error_ques_list[] = $filter_ques_list[$ques_idx];
                        unset($filter_ques_list[$ques_idx]);
                    }
                }
            }
            if (count($filter_ques_list)>0)
            {
                foreach ($filter_ques_list as $val)
                {
                    $error_ques_list[] = $val;
                }
            }
            /////////////////////////////////////////////////////// 
            if ($error_ques_list)
            {
                foreach ($error_ques_list as $key =>$value)
                {
                    //过滤知识点，知识点查询
                    if ($query['knowledge'])
                    {
                        if (in_array($value['type'],array(4,0,5,6,10,8,13,15)))
                        {
                            $sql = <<<EOT
SELECT DISTINCT knowledge FROM rd_question WHERE parent_id ={$value['ques_id']}
EOT;
                            $sub_knowledge = $db->fetchCol($sql);
                           
                            if ($sub_knowledge)
                            {
                                $sub_knowledge_ids = implode(',',$sub_knowledge);
                                $sub_knowledge_arrs = array_unique(explode(',',$sub_knowledge_ids));
                                if (!in_array($query['knowledge'],$sub_knowledge_arrs))
                                {
                                    unset($error_ques_list[$key]);
                                }
                            }
                            else
                            {
                                 unset($error_ques_list[$key]);
                            }
                        }
                        else
                        {
                            if (false === strpos($value['knowledge'], ',' . $query['knowledge'] . ','))
                            {
                                unset($error_ques_list[$key]);
                            }
                        } 
                    }
                    
                    //过滤题型，题型查询
                    if (trim($query['qtype'])=='')
                    {
                    }
                    else
                    {
                        if (!($query['qtype']===$value['type']))
                        {
                            unset($error_ques_list[$key]);
                        }
                    }
                   //过滤难易度，难易度查询
                    if ($query['difficulty'])
                    {
                        $sql = <<<EOT
SELECT difficulty FROM rd_relate_class WHERE class_id={$exam_info['class_id']} AND grade_id ={$exam_info['grade_id']} AND subject_type ={$exam_info['subject_type']}  AND ques_id ={$value['ques_id']}
EOT;
                        $ques_difficulty = $db->fetchOne($sql);
                        if ($query['difficulty']==3)
                        {
                            if (($ques_difficulty >=30)||($ques_difficulty<0))
                            {
                                unset($error_ques_list[$key]);
                             }
                        }
                        if ($query['difficulty']==2)
                        {
                            if (($ques_difficulty <30)||($ques_difficulty>60))
                            {
                                unset($error_ques_list[$key]);
                            }
                        }
                
                        $ques_difficulty =$db->fetchOne($sql);
                        if ($query['difficulty']==1)
                        {
                            if (($ques_difficulty >100)||($ques_difficulty<=60))
                            {
                                unset($error_ques_list[$key]);
                            }
                        }
                    }

                    ////过滤方法策略方式，方法策略查询
                    if (in_array($value['subject_id'],array(2,4,5,6,10,11)))
                    {
                        if ($query['method_tactic'])
                        {
                            if (in_array($value['type'],array(0,10,15)))
                            {
                                $sql = <<<EOT
SELECT DISTINCT method_tactic FROM rd_question WHERE parent_id ={$value['ques_id']}
EOT;
                                $sub_method_tactic = $db->fetchCol($sql);
                                if ($sub_method_tactic)
                                {
                                     $sub_method_tactic_ids = implode(',',$sub_method_tactic);
                                     $sub_method_tactic_arrs = array_unique(explode(',',$sub_method_tactic_ids));
                                     if (!in_array($query['method_tactic'],$sub_method_tactic_arrs))
                                     {
                                          unset($error_ques_list[$key]);
                                     }
                                }
                                else
                                {
                                    unset($error_ques_list[$key]);
                                }
                            }
                            else
                            {   
                                if (false === strpos($value['method_tactic'], ',' . $query['method_tactic'] . ','))
                                {
                                    unset($error_ques_list[$key]);
                                 }
                            }
                        } 
                    }
                    //////过滤信息提取方式，信息提取方式查询
                    if ($exam_info['subject_id']==3)
                    {
                        if ($query['group_type'])
                        {
                            if (in_array($value['type'],array(0,5)))
                            {
                                $sql = <<<EOT
SELECT DISTINCT group_type FROM rd_question WHERE parent_id ={$value['ques_id']}
EOT;
                                $sub_group_type = $db->fetchCol($sql);
              
                                if (!$sub_group_type)
                                {
                                    unset($error_ques_list[$key]);
                                }
                                else
                                {
                                    $sub_group_type_ids = implode(',',$sub_group_type);
                                    $sub_group_type_arrs = array_unique(explode(',',$sub_group_type_ids));
                                    if (!in_array($query['group_type'],$sub_group_type_arrs))
                                    {
                                        unset($error_ques_list[$key]);
                                    }
                                }
                            }
                            else
                            {   
                                if (false === strpos($value['group_type'], ',' . $query['group_type'] . ','))
                                {
                                    unset($error_ques_list[$key]);
                                 }
                            }
                        } 
                    }

                }
                }
	    $sub_ques_id_arr = array();
	    $nosub_ques_id_arr = array();
	    $match_ques_id_arr = array();
            $error_ques_list =$error_ques_list;

            if ($error_ques_list)
            {
	        foreach ($error_ques_list as $val)
		{
		    if (in_array($val['type'] ,array(1,2,3,7,9,11)))
                    {
                        if ($search['exam_id'])
                        {
                            $exam_info = $db->fetchRow($sql2,$search['exam_id']);
                        }
			$nosub_ques_id_arr[] = $val['ques_id'];//不定项及单选试题id数组
		    }
                    else if (in_array($val['type'],array(0,4,5,6,8,10,13,15)))
                    {
		        if (!(in_array($val['ques_id'],$sub_ques_id_arr)))
			{
			    $sub_ques_id_arr[] = $val['ques_id'];//获取题组试题id数组
			}
                    }
		}
            }

	    //获取当前用户该期次下，不定项及单选的答案
	    if ($nosub_ques_id_arr)
	    {
	    	$nosub_ques_ids = implode(',' , $nosub_ques_id_arr);
		$sql = <<<EOT
SELECT ques_id,answer FROM rd_exam_test_result WHERE ques_id in ($nosub_ques_ids) AND uid = $uid AND exam_id =$exam_id
EOT;
		$nosub_ques_answer = $db->fetchPairs($sql);
            }
            
            if ($error_ques_list)
            {
                foreach ($error_ques_list as $key =>$val)
	        {
                    if ($val['answer'])
                    {
                        $val['answer'] =explode(',',$val['answer']);
                    }
                    ///////获取当前试题知识点信息////
                     if ($val['knowledge'])
                    {
                        $sql =<<<EOT
SELECT  k.knowledge_name,rk.knowledge_id,rk.know_process
FROM rd_relate_knowledge rk
LEFT JOIN rd_knowledge k ON rk.knowledge_id = k.id
WHERE rk.ques_id = {$val['ques_id']}
EOT;
                        $knowledge_know_process=$db->fetchAll($sql);
                    }
                     else
                    {
                         $sql =<<<EOT
SELECT  k.knowledge_name,rk.knowledge_id,rk.know_process
FROM rd_relate_knowledge rk
LEFT JOIN rd_knowledge k ON rk.knowledge_id = k.id
LEFT JOIN rd_question q ON q.ques_id = rk.ques_id
WHERE parent_id = {$val['ques_id']}
EOT;
                         $knowledge_know_process=$db->fetchAll($sql);
                    }
                    if ($knowledge_know_process )
                    {
                        foreach ($knowledge_know_process as $item)
                        {
                            if ($datas[$item['knowledge_id']]['know_process'])
                            {
                                if (!in_array($item['know_process'],$datas[$item['knowledge_id']]['know_process']))
                                {
                                    if ($item['know_process']==1)
                                    {
                                        $datas[$item['knowledge_id']]['know_process'][]= '记忆';
                                    }
                                    else if ($item['know_process']==2)
                                    {
                                        $datas[$item['knowledge_id']]['know_process'][]= '理解';
                                    }
                                    else if ($item['know_process']==3)
                                    {
                                        $datas[$item['knowledge_id']]['know_process'][]= '应用';
                                    }
                                    
                                    $datas[$item['knowledge_id']]['knowledge_name']= $item['knowledge_name'];
                                    $datas[$item['knowledge_id']]['knowledge_grade']= $k_id_arr[$item['knowledge_id']];
                                    $datas[$item['knowledge_id']]['knowledge_grade']= $item['knowledge_id'];
                                }
                            }
                            else
                            {
                                if ($item['know_process']==1)
                                {
                                    $datas[$item['knowledge_id']]['know_process'][]= '记忆';
                                }
                                else if ($item['know_process']==2)
                                {
                                     $datas[$item['knowledge_id']]['know_process'][]= '理解';
                                }
                                else if ($item['know_process']==3)
                                {
                                     $datas[$item['knowledge_id']]['know_process'][]= '应用';
                                }

                                $datas[$item['knowledge_id']]['knowledge_name']= $item['knowledge_name'];
                                $datas[$item['knowledge_id']]['knowledge_grade']= $k_id_arr[$item['knowledge_id']];
                                $datas[$item['knowledge_id']]['knowledge_id']= $item['knowledge_id'];
                                $k_grade[] = $k_id_arr[$item['knowledge_id']] ;
                            }
                        }
                        if ($k_grade)
                        {
                            $k_grade = array_unique(array_filter($k_grade));
                            sort($k_grade);
                            foreach ($k_grade as $g)
                            {
                                foreach($datas as $k=>$v)
                                {
                                    if ($g == $v['knowledge_grade'])
                                    {
                                        $datak[]= $datas[$k];
                                        unset($datas[$k]);
                                    }
                                }
                            }
                        }
                        $error_ques_list[$key]['knowledge_know_process'] = $datak;
                        unset($datak);  
                    }
//////////////////////////获取当前试题信息提取方式信息////////////////////////////////////////////////////////////
                    if ($exam_info['subject_id']==3)
                    {
                    if ($val['knowledge'])
                    {
                        $sql =<<<EOT
SELECT  gt.id , gt.group_type_name
FROM rd_relate_group_type rgt
LEFT JOIN rd_group_type gt ON rgt.group_type_id = gt.id
WHERE rgt.ques_id = {$val['ques_id']}
EOT;
                        $group_type_name = $db->fetchAll($sql);
                    }
                     else
                    {
                         $sql =<<<EOT
SELECT  gt.id , gt.group_type_name
FROM rd_relate_group_type rgt
LEFT JOIN rd_group_type gt ON rgt.group_type_id = gt.id
LEFT JOIN rd_question q ON q.ques_id = rgt.ques_id
WHERE q.parent_id = {$val['ques_id']}
EOT;
                         $group_type_name = $db->fetchAll($sql);
                    }
                    if ($group_type_name)
                    {
                        foreach ($group_type_name as $item)
                        {
                            if (!$datag[$item['id']])
                            {
                                $datag[$item['id']]['group_type_name']= $item['group_type_name'];
                                $datag[$item['id']]['group_grade']= $gt_id_arr[$item['id']];
                                $gt_grade[] = $gt_id_arr[$item['id']] ;
                            }
                        }
                        if ($gt_grade)
                        {
                            $gt_grade = array_unique(array_filter($gt_grade));
                            sort($gt_grade);
                            foreach ($gt_grade as $g)
                            {
                                foreach($datag as $k=>$v)
                                {
                                    if ($g == $v['group_grade'])
                                    {
                                        $datat[]= $datag[$k];
                                        unset($datag[$k]);
                                    }
                                }
                            }
                        }
                        $error_ques_list[$key]['group_type_name'] = $datat;
                        unset($datat);  
                    }
                    }

                    ////////////////////////////////获取当前试题方法策略信息///////////////
                    if (in_array($exam_info['subject_id'],array(2,4,5,6,10,11,3)))
                    {
                    if ($val['knowledge'])
                    {
                        //$knowledge_id = substr($val['knowledge'],1,strlen($val['knowledge'])-2);
                        $sql =<<<EOT
SELECT  mt.id , mt.name
FROM rd_relate_method_tactic rmt
LEFT JOIN rd_method_tactic mt ON rmt.method_tactic_id = mt.id
WHERE rmt.ques_id = {$val['ques_id']}
EOT;
                        $method_tactic_name = $db->fetchAll($sql);
                    }
                     else
                    {
                         $sql =<<<EOT
SELECT  mt.id , mt.name
FROM rd_relate_method_tactic rmt
LEFT JOIN rd_method_tactic mt ON rmt.method_tactic_id = mt.id
LEFT JOIN rd_question q ON q.ques_id = rmt.ques_id
WHERE q.parent_id = {$val['ques_id']}
EOT;
                         $method_tactic_name = $db->fetchAll($sql);
                    }
                    if ($method_tactic_name)
                    {
                        foreach ($method_tactic_name as $item)
                        {
                            if (!$datam[$item['id']])
                            {
                                $datam[$item['id']]['method_name']= $item['name'];
                                $datam[$item['id']]['method_grade']= $mt_id_arr[$item['id']];
                                $mt_grade[] = $mt_id_arr[$item['id']] ;
                            }
                        }
                        if ($mt_grade)
                        {
                            $mt_grade = array_unique(array_filter($mt_grade));
                            sort($mt_grade);
                            foreach ($mt_grade as $g)
                            {
                                foreach($datam as $k=>$v)
                                {
                                    if ($g == $v['method_grade'])
                                    {
                                        $datan[]= $datam[$k];
                                        unset($datam[$k]);
                                    }
                                }
                            }
                        }
                        $error_ques_list[$key]['method_tactic_name'] = $datan;
                        unset($datan);  
                    }
                    }

                    ///////////////处理单选//////////////////////////////////////////////// 
                    if (in_array($val['ques_id'],$nosub_ques_id_arr))
                    {
                        if (in_array($val['type'],array(1,2,7)))
                        {
                            $sql=<<<EOT
SELECT * FROM rd_option WHERE ques_id = {$val['ques_id']}
EOT;
                            $nosub_option =$db->fetchAll($sql);
                            foreach ($nosub_option as $kk=>$arr)
                            {
                                $arr['is_answer'] = in_array($arr['option_id'],$val['answer']);
                                $nosub_option[$kk]['is_answer'] = $arr['is_answer'];
                                $nosub_ques_answer_arr = explode(',',$nosub_ques_answer[$val['ques_id']]);
                                if (in_array($arr['option_id'],$nosub_ques_answer_arr))
                                {
                                   $error_ques_list[$key]['error_answer'][] = $arr['option_name']; 
                                   if ($arr['picture'])
                                   {
                                        $error_ques_list[$key]['error_picture'][] = $arr['picture']; 
                                    }
                                }
                            }
                            $error_ques_list[$key]['option'] = $nosub_option;
                        }
                    }
	            //获取当前用户该期次下，题组正确答案
		    if (in_array($val['ques_id'],$sub_ques_id_arr))
		    {
                        /////获取所有子题信息
                        $sql = <<<EOT
SELECT * FROM rd_question WHERE parent_id = {$val['ques_id']} ORDER BY ques_id ASC 
EOT;
                        $arrs['sub_ques_list'] = $db->fetchAll($sql);
                        if (!$arrs['sub_ques_list'])
                        {

                        } 
                        else
                        {
                            foreach ($arrs['sub_ques_list'] as $k =>$v)
		            {
                                $sql = <<<EOT
SELECT answer FROM rd_exam_test_result WHERE sub_ques_id = ? AND uid= {$uid} AND exam_id ={$exam_id}
EOT;
                                $sub_error_answer = $db->fetchOne($sql,$v['ques_id']);
                                ///处理题组里面的选择题 
                                if (in_array($v['type'],array(1,2)))
                                { 
                                     $sql = <<<EOT
SELECT * FROM rd_option WHERE ques_id = {$v['ques_id']} ORDER BY option_id ASC
EOT;
                                    $option=$db->fetchAll($sql);
                                    foreach ($option as $kk =>$vv)
                                    {
                                         $sub_answer=explode(',',$v['answer']);
                                        $option[$kk]['is_answer'] = in_array($vv['option_id'],$sub_answer);
                                        $sub_ques_answer_arr = explode(',',$sub_error_answer);
                                        if ($sub_ques_answer_arr)
                                        {
                                            if (in_array($vv['option_id'],$sub_ques_answer_arr))
                                            {
                                                $arrs['sub_ques_list'][$k]['error_answer'][] = $vv['option_name']; 
                                                if ($vv['picture'])
                                                {
                                                    $arrs['sub_ques_list'][$k]['error_picture'][] = $vv['picture'];
                                                }
                                            }
                                         }
                                     }
                                    $arrs['sub_ques_list'][$k]['option'] = $option; 
                                 }
                           
                                 if (in_array($v['type'],array(3,6,5,10)))
                                 {
                                    $arrs['sub_ques_list'][$k]['error_answer'] = $sub_error_answer;   
                                 }
                            
                            }
                            $error_ques_list[$key]['sub_ques_list'] = $arrs['sub_ques_list'];
                        }
                    }
                    
                    if(in_array($val['ques_id'],$match_ques_id_arr))
                    {
                         $sql = <<<EOT
SELECT * FROM rd_question WHERE parent_id = {$val['ques_id']} ORDER BY ques_id ASC 
EOT;
                         $match_ques_list =$db->fetchAll($sql);
                         $error_ques_list[$key]['match_ques_list']=$match_ques_list;
                    }
                }
                
            }
        $data['uinfo'] = $this->_uinfo;
        $data['current_exam'] = $current_exam;
        $data['stu_info'] = $stu_info;
        $data['exam_info'] = $exam_info;
        $data['exam_pid_info'] = $exam_pid_info;
        $data['sub_ques_answer'] = $sub_ques_answers;
        $data['nosub_ques_answer'] = $nosub_ques_answer;
        $data['subject_id'] = $subject_id;
        $data['error_ques_list'] = $error_ques_list;
        $data['error_ques_count'] = $error_ques_count;

        $this->load->view('profile/errorlist', $data);
    }
    // 学习成绩提交保存
    public function awards_save()
    {
        $uid = $this->_uinfo['uid'];

        if (empty($_POST))
        {
            redirect('student/profile/awards');
        }

        if ($uid)
        {
            $student = StudentModel::get_student($uid);
            if (empty($student))
            {
                StudentModel::studentAjaxLogout();
                redirect('student/index/login');
            }
        }
        else
        {
            $student = $this->session->userdata('student');
            if (empty($student))
            {
                redirect('student/practice/basic');
            }
        }

        $message = array();
        //---------------------------------------------------//
        // 年级排名
        //---------------------------------------------------//
        // 数据初始化
        $score_ranking = array();
        $score_rank_arr = $this->input->post('score_rank');
        if ( ! isset($score_rank_arr['grade_id']) OR ! is_array($score_rank_arr['grade_id']))
        {
            $score_rank_arr['grade_id'] = array();
        }
        // 整理数据
        foreach ($score_rank_arr['grade_id'] as $k => $v)
        {
            $rank['grade_id'] = abs(intval($v));
            $rank['ranking']  = isset($score_rank_arr['ranking'][$k]) ? abs(intval($score_rank_arr['ranking'][$k])) : 0;
            $rank['totalnum'] = isset($score_rank_arr['totalnum'][$k])? abs(intval($score_rank_arr['totalnum'][$k])): 0;
            // 无效数据，跳过
            if (empty($rank['grade_id']) OR empty($rank['ranking']) OR empty($rank['totalnum'])
                OR $rank['grade_id']>$student['grade_id'] OR $rank['ranking']>$rank['totalnum'])
            {
                continue;
            }
            $score_ranking[$rank['grade_id']] = $rank;
        }

        // 检查是否完整填写
        if ($student['grade_id'] > 1 && empty($score_ranking))
        {
            $message[] = '请填写年级排名';
        }
        else
        {
            $start_grade = $student['grade_id'] < 3 ? 1 : $student['grade_id']-1;
            for ($i=$student['grade_id']; $i>=$start_grade; $i--)
            {
                if (!isset($score_ranking[$i]))
                {
                    $message[] = '请填写最近三年年级成绩排名' . $start_grade;
                    break;
                }
            }
        }

        if ($message)
        {
            message(implode('<br/>', $message));
        }

        //---------------------------------------------------//
        // 获奖情况
        //---------------------------------------------------//
        $awards_list = array();
        $awards_subject_arr = (array)$this->input->post('awards_subject');
        $awards_awards_arr  = $this->input->post('awards_awards');
        $awards_grade_arr   = $this->input->post('awards_grade');
        $awards_ids_arr     = $this->input->post('awards_ids');
        foreach ($awards_subject_arr as $type_id => $subject_arr)
        {
            if (empty($type_id) OR ! is_array($subject_arr)) continue;
            $awards_arr = isset($awards_awards_arr[$type_id]) ? $awards_awards_arr[$type_id] : array();
            $grade_arr  = isset($awards_grade_arr[$type_id])  ? $awards_grade_arr[$type_id]  : array();
            $ids_arr    = isset($awards_ids_arr[$type_id])    ? $awards_ids_arr[$type_id]    : array();

            foreach ($subject_arr as $k => $v)
            {
                $awards = array(
                    'id'         => isset($ids_arr[$k]) ? intval($ids_arr[$k]) : 0,
                    'uid'        => 0,
                    'typeid'     => $type_id,
                    'subject'    => intval($v),
                    'awards'     => isset($awards_arr[$k]) ? intval($awards_arr[$k]) : 0,
                    'grade'      => isset($grade_arr[$k]) ? intval($grade_arr[$k]) : 0,
                    'other_name' => '',
                    'other_desc' => '',
                );
                if (empty($awards['subject']) OR empty($awards['awards']) OR empty($awards['grade']) OR ($awards['grade'] <= 12 && $awards['grade']>$student['grade_id']))
                {
                    continue;
                }
                $awards_list[$type_id][] = $awards;
            }
        }

        //---------------------------------------------------//
        // 其他获奖情况
        //---------------------------------------------------//
        $awards_other = $this->input->post('awards_other', TRUE);
        
        if ( ! isset($awards_other['name']) OR ! is_array($awards_other['name']))
        {
            $awards_other['name'] = array();
        }
        foreach ($awards_other['name'] as $k => $other_name)
        {
            $other_name = trim($other_name);
            $other_id   = isset($awards_other['id'][$k]) ? intval($awards_other['id'][$k]) : 0;
            $other_desc = isset($awards_other['desc'][$k]) ? trim($awards_other['desc'][$k]) : '';
            if (empty($other_name) OR empty($other_desc))
            {
                continue;
            }
            $awards = array(
                'id'         => $other_id,
                'uid'        => 0,
                'typeid'     => 0,
                'subject'    => 0,
                'awards'     => 0,
                'grade'      => 0,
                'other_name' => $other_name,
                'other_desc' => $other_desc
            );
            $awards_list[0][] = html_escape($awards);
        }

        $school_id_arr = $this->input->post('school_id');
        $volunteer_arr = $this->input->post('volunteer');
        if (empty($volunteer_arr[a]))
        {
            $school_id_arr[a]='';
        }

        if (intval($school_id_arr[a])==0)
        {
            message('第一志愿不能为空');
        }
        
        if (empty($volunteer_arr[b]))
        {
            $school_id_arr[b]='';
        }
       
        if (empty($volunteer_arr[c]))
        {
            $school_id_arr[c]='';
        }
        $volunteer = json_encode($school_id_arr);//将升学志愿学校id转换为json值
        //选考学考
        //$is_in_class =  $this->input->post('is_in_class');
        //$subject_class = serialize(/* array_filter*/ ( $this->input->post('subject_class') ) );
        //$subject_in = serialize( /*array_filter */( $this->input->post('subject_in') ) );
        //$subject_not = serialize( /*array_filter */( $this->input->post('subject_not') ) );
        /*$subject_first = $this->input->post('subject_first');
        $subject_first[shijian] = array_filter($subject_first[shijian]);
        $subject_first[fenshu] = array_filter($subject_first[fenshu]);
        $subject_first[subject_id] = array_filter($subject_first[subject_id]);
        $subject_first = serialize($subject_first);



        $subject_two =  $this->input->post('subject_two');
        $subject_two[shijian] = array_filter($subject_two[shijian]);
        $subject_two[fenshu] = array_filter($subject_two[fenshu]);
        $subject_two[subject_id] = array_filter($subject_two[subject_id]);
        $subject_two = serialize($subject_two);


        $subject_finish = $this->input->post('subject_finish') ;
        $subject_finish[rank] = array_filter($subject_finish[rank]);
        $subject_finish[subject_id] = array_filter($subject_finish[subject_id]);

        $subject_finish = serialize($subject_finish);


        $update_xuekao_xuankao = array();
        $update_xuekao_xuankao = array(
                'is_in_class'=>$is_in_class,
                'subject_class'=>$subject_class,
                'subject_in'=>$subject_in,
                'subject_not'=>$subject_not,
                'subject_first'=>$subject_first,
                'subject_two'=>$subject_two,
                'subject_finish'=>$subject_finish,
            );*/

        if ($uid)
        {
            $old_ranking = $old_awards = $old_xuekao_xuankao = array();
            $query = $this->db->select('id,grade_id')->get_where('student_ranking', array('uid'=>$uid));
            foreach ($query->result_array() as $row)
            {
                $old_ranking[$row['grade_id']] = $row['id'];
            }
            $query = $this->db->select('id')->get_where('student_awards', array('uid'=>$uid));
            foreach ($query->result_array() as $row)
            {
                $old_awards[$row['id']] = $row['id'];
            }
            //$old_xuekao_xuankao = $this->db->select('id')->get_where('xuekao_xuankao', array('uid'=>$uid))->row_array();

            $db = Fn::db();
            $db->beginTransaction();
            try
            {
            /*if($old_xuekao_xuankao)
            {
                $this->db->update('xuekao_xuankao', $update_xuekao_xuankao, array('id'=>$old_xuekao_xuankao['id']));
            }
            else
            {
                $update_xuekao_xuankao['uid'] = $uid;

                $this->db->insert('xuekao_xuankao', $update_xuekao_xuankao);
            }*/


            // 更新成绩排名
            foreach ($score_ranking as $grade_id => &$rank)
            {
                $rank['uid'] = $uid;
                if (isset($old_ranking[$grade_id]))
                {
                    $v = $rank;
                    unset($v['id']);
                    $db->update('rd_student_ranking', $v, "id = " . $old_ranking[$grade_id]);
                    unset($old_ranking[$grade_id]);
                    unset($score_ranking[$grade_id]);
                }
            }
            if ($score_ranking)
            {
                foreach ($score_ranking as $v)
                {
                    $db->insert('rd_student_ranking', $v);
                }
            }
            if ($old_ranking)
            {
                $db->delete('rd_student_ranking', 
                    'id IN (' . implode(',', $old_ranking) . ')');
            }

            // 更新获奖情况
            $new_awards_list = array();
            foreach ($awards_list as $typeid => $type_list)
            {
                $new_awards_list = array_merge($new_awards_list, $type_list);
            }
            $awards_list = $new_awards_list;

            foreach ($awards_list as $k => &$awards)
            {
                $awards['uid'] = $uid;
                if ($awards['id'] && isset($old_awards[$awards['id']]))
                {
                    $v = $awards;
                    unset($v['id']);
                    $db->update('rd_student_awards', $v, 'id = '. $old_awards[$awards['id']]);
                    unset($old_awards[$awards['id']]);
                    unset($awards_list[$k]);
                }
                else
                {
                    unset($awards['id']);
                }
            }
            if ($awards_list)
            {
                foreach ($awards_list as $v)
                {
                    $db->insert('rd_student_awards', $v);
                }
            }
            if ($old_awards)
            {
                $db->delete('rd_student_awards', 'id IN (' . implode(',', $old_awards) . ')');
            }

          
            if ($volunteer)
            {
                $student_wish  = array(
                        'uid'      => $uid,    
                        'music'    => '',
                        'sport'    => '',
                        'painting' => '',
                        'other'    => '',
                        'wish'     => '',
                        'upmethod' => '',
                        'volunteer'=>$volunteer);
            }
            else
            {
                 $message[] = '第一志愿不能为空';
            }

            $swv = $db->fetchRow("SELECT volunteer FROM rd_student_wish WHERE uid = $uid");
           
            if ($swv)
            {
                 $db->update('rd_student_wish', $student_wish,"uid=$uid"); 
            }
            else
            {
                 $db->insert('rd_student_wish', $student_wish);
            }
            $db->commit();
            }
            catch (Exception $e)
            {
                $db->rollBack();
                message('学生成绩修改失败' . $e->getMessage());
            }
        }
        else
        {
            // 未注册用户，设置session
            $this->session->set_userdata(array('score_ranks'=>$score_ranking, 'awards_list'=>$awards_list/*, 'xuekao_xuankao'=>$update_xuekao_xuankao*/));
        }

        if ($uid OR $this->session->userdata('complete'))
        {
            message('学习成绩修改成功', 'student/profile/awards?dl=1', 'success');
        }
        else
        {
            redirect('student/profile/basic');
        }
    }

    // 社会实践
    public function practice()
    {
        Fn::ajax_call($this, 'login', 'logout');

        $uid = $this->_uinfo['uid'];
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            // 已注册用户
            $query = $this->db->get_where('student_practice', array('uid'=>$uid));
            $practice = $query->row_array();

            $action  = 'renew';
        }
        else
        {
            $practice = $this->session->userdata('practice');
        }
        if (empty($practice))
        {
            $practice = array(
                'investigate' => '',
                'art'         => '',
                'environment' => '',
                'work'        => '',
                'other'       => '',
            );
        }

        $data['practice'] = $practice;
        $data['uid']      = $uid;

        // 模版
        $this->load->view('profile/practice', $data);
    }

    public function practice_save()
    {
        $uid = $this->_uinfo['uid'];

        $practice_arr = $this->input->post('practice', TRUE);
        if (empty($practice_arr))
        {
            // 没有提交数据，自动跳转表单页面
            redirect('student/profile/practice');
        }

        $practice_keys = array('investigate','art','environment','work','other');
        foreach ($practice_keys as $key)
        {
            $practice[$key] = isset($practice_arr[$key]) ? html_escape($practice_arr[$key]) : '';
        }

        if ($uid)
        {
            $practice['uid'] = $uid;
            $this->db->replace('student_practice', $practice);
        }
        else
        {
            $this->session->set_userdata(array('practice' => $practice));
        }

        if ($uid OR $this->session->userdata('complete'))
        { 
            message('社会实践情况修改成功', 'student/profile/preview', 'success');
        }
        else
        {
            redirect('student/profile/wish');
        }
    }

    // 学生意愿
    public function wish()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }
        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            // 已注册用户
            $query = $this->db->get_where('student_wish', array('uid'=>$uid));
            $student_wish = $query->row_array();
        }
        else
        {
            $student_wish = $this->session->userdata('student_wish');
        }
        $specs = array(
                '0'=>'哲学',
                '1'=>'经济学',
                '2'=>'法学',
                '3'=>'教育学',
                '4'=>'文学',
                '5'=>'历史学',
                '6'=>'理学',
                '7'=>'工学',
                '8'=>'农学',
                '9'=>'医学',
                '10'=>'军事学',
                '11'=>'管理学',
                '12'=>'艺术类',
                '13'=>'不清楚'

        );

        $data['student_wish'] = $student_wish;
        $data['subjects'] = $subjects;
        $data['specs'] = $specs;

        $data['uid']          = $uid;

        // 模版
        $this->load->view('profile/wish', $data);
    }

    public function wish_save()
    {
        $uid = $this->_uinfo['uid'];

        $student_wish_arr = $this->input->post('student_wish', TRUE);
        if (empty($student_wish_arr))
        {
            redirect('student/profile/wish');
        }
        if ( ! isset($student_wish_arr['upmethod']))
        {
            $student_wish_arr['upmethod'] = '其他';
        }
        if ( ! isset($student_wish_arr['upmethod_other']))
        {
            $student_wish_arr['upmethod_other'] = '';
        }

        if (empty($student_wish_arr['upmethod']) OR empty($student_wish_arr['wish']) OR $student_wish_arr['upmethod']=='其他' && empty($student_wish_arr['upmethod_other']))
        {
            message('请填写升学途经和发展意愿');
        }
        else
        {
            $student_wish  = array(
                'music'    => isset($student_wish_arr['music']) ? $student_wish_arr['music'] : '',
                'sport'    => isset($student_wish_arr['sport']) ? $student_wish_arr['sport'] : '',
                'painting' => isset($student_wish_arr['painting']) ? $student_wish_arr['painting'] : '',
                'other'    => isset($student_wish_arr['other']) ? $student_wish_arr['other'] : '',
                'wish'     => isset($student_wish_arr['wish']) ? trim($student_wish_arr['wish']) : '',
                'upmethod' => $student_wish_arr['upmethod']=='其他' ?
                               trim($student_wish_arr['upmethod_other']) : $student_wish_arr['upmethod'],
            );
            $student_wish = html_escape($student_wish);
        }

        $student_wish[spec] = isset($student_wish_arr['spec']) ? serialize($student_wish_arr['spec']) : '';
        if ($uid)
        {
            $student_wish['uid'] = $uid;
            $this->db->replace('student_wish', $student_wish);
        }
        else
        {
            $this->session->set_userdata('student_wish', $student_wish);
        }

        if ($uid OR $this->session->userdata('complete'))
        {
            message('学生意愿改成功', 'student/profliel/preview', 'success');
        }
        else
        {
            redirect('student/proflie/pwish');
        }
    }

    // 家长意愿
    public function pwish()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            // 已注册用户
            $query = $this->db->get_where('student_parent_wish', array('uid'=>$uid));
            $parent_wish = $query->row_array();
        }
        else
        {
            $parent_wish = $this->session->userdata('parent_wish');
        }

        if (empty($parent_wish))
        {
            $parent_wish = array(
                'family_bg' => '',
                'upmethod'  => '',
                'wish'      => '',
            );
        }

        $data['parent_wish']  = $parent_wish;
        $data['uid']          = $uid;

        // 模版
        $this->load->view('profile/pwish', $data);
    }

    public function pwish_save()
    {
    	//是否点击上一步触发
    	$go_prev = intval($this->input->get_post('go_prev'));

        $uid = $this->_uinfo['uid'];
        $parent_wish_arr = $this->input->post('parent_wish', TRUE);


        if (empty($parent_wish_arr))
        {
            redirect('student/profile/pwish');
        }

        if ( ! isset($parent_wish_arr['family_bg']) OR ! is_array($parent_wish_arr['family_bg']))
            $parent_wish_arr['family_bg'] = array();
        if ( ! isset($parent_wish_arr['upmethod']))
            $parent_wish_arr['upmethod'] = '其他';
        if ( ! isset($parent_wish_arr['upmethod_other']))
            $parent_wish_arr['upmethod_other'] = '';
        if ( ! isset($parent_wish_arr['other_bg']) OR !in_array(99, $parent_wish_arr['family_bg']))
            $parent_wish_arr['other_bg'] = '';

        if (empty($parent_wish_arr['family_bg']) OR empty($parent_wish_arr['upmethod'])
            OR empty($parent_wish_arr['wish'])
            OR in_array(99, $parent_wish_arr['family_bg']) && empty($parent_wish_arr['other_bg'])
            OR $parent_wish_arr['upmethod']=='其他' && empty($parent_wish_arr['upmethod_other']))
        {
            if (!$go_prev)
            {
                message('请完整填写家长意愿');
            }
        }
        else
        {
            $parent_wish = array(
                'family_bg' => implode(',',my_intval($parent_wish_arr['family_bg'])),
                'other_bg'  => html_escape(trim($parent_wish_arr['other_bg'])),
                'wish'      => isset($parent_wish_arr['wish']) ? html_escape($parent_wish_arr['wish']) : '',
                'upmethod'  => html_escape($parent_wish_arr['upmethod']=='其他' ?
                                $parent_wish_arr['upmethod_other'] : $parent_wish_arr['upmethod']),
            );
        }
        $parent_wish['family_bg_qt'] = serialize($parent_wish_arr['family_bg_qt']);
        if ($uid)
        {
            $parent_wish['uid'] = $uid;
            $this->db->replace('student_parent_wish', $parent_wish);
        }
        else
        {
            $this->session->set_userdata('parent_wish', $parent_wish);
        }

        if ($go_prev)
        {
            redirect('student/profile/wish');
        }

        if ($uid OR $this->session->userdata('complete'))
        {
            message('家长意愿修改成功', 'student/profile/preview', 'success');
        }
        else
        {
            redirect('student/profile/preview');
        }
    }

    // 预览信息
    public function preview()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            // 基本信息
            $student = StudentModel::get_student($uid);

            // 学习概况
            $db = Fn::db();
            $sbinfo = $db->fetchRow("SELECT * FROM t_student_base WHERE sb_uid = $uid");
            if (is_array($sbinfo))
            {
                $student = array_merge($student, $sbinfo);
            }
            $sql = <<<EOT
SELECT sbs_stunumtype FROM t_student_base_stunumtype WHERE sbs_uid = {$uid}
EOT;
            $student['sbs_stunumtype'] = $db->fetchCol($sql);
            $sql = <<<EOT
SELECT sbclassid_classid FROM t_student_base_classid WHERE sbclassid_uid = {$uid}
EOT;
            $student['sbclassid_classid'] = $db->fetchCol($sql);


            // 培训机构、课程、授课教师
            $sql = <<<EOT
SELECT a.*, 
b.ti_id, b.ti_name, b.ti_typeid, b.ti_flag, b.ti_provid, b.ti_cityid, b.ti_areaid,  
c.cors_id, c.cors_cmid, c.cors_name, c.cors_flag, c.cors_tiid, c.cors_stunumtype
FROM t_student_base_course a
LEFT JOIN v_training_institution b ON a.sbc_tiid = b.ti_id
LEFT JOIN v_course c ON a.sbc_corsid = c.cors_id
WHERE a.sbc_uid = {$uid} AND a.sbc_idx = 0
EOT;
            $sbcinfo = $db->fetchRow($sql);
            if (is_array($sbcinfo))
            {
                $student = array_merge($student, $sbcinfo);
                $student['no_tiid'] = 0;
            }
            else
            {
                $student['no_tiid'] = 1;
            }

            // 成绩排名
            $this->db->order_by('grade_id ASC');
            $query = $this->db->get_where('student_ranking', array('uid'=>$uid));
            $score_ranks = $query->result_array();

            $start_grade = $student['grade_id']<3 ? 1 : $student['grade_id']-2;
            $grades = array();
            for ($i=$student['grade_id']; $i>=$start_grade; $i--)
            {
                $grades[] = $i;

            }


            /******TODO    暂时去除掉 ****************************************
            foreach ($score_ranks as $v){
                if ( ! in_array($v[grade_id], $grades))
                {
                    message('请填写最近三年年级成绩排名', 'student/profile/awards');
                    //$message[] = '请填写最近三年年级成绩排名';
                    break;
                }
            }
            /****************************************************************/




            // 竞赛成绩
            $query = $this->db->get_where('student_awards', array('uid'=>$uid));
            $awards_list = array();
            foreach ($query->result_array() as $row)
            {
                $awards_list[$row['typeid']][] = $row;
            }

            // 社会实践
            $query = $this->db->get_where('student_practice', array('uid'=>$uid));
            $practice = $query->row_array();

            // 学生意愿
            $query = $this->db->get_where('student_wish', array('uid'=>$uid));
            $student_wish = $query->row_array();

            // 家长意愿
            $query = $this->db->get_where('student_parent_wish', array('uid'=>$uid));
            $parent_wish = $query->row_array();

            // 学考选考
            $query = $this->db->get_where('xuekao_xuankao', array('uid'=>$uid));
            $xuekao_xuankao = $query->row_array();
            $subject_first = unserialize($xuekao_xuankao[subject_first]);
            /***  TODO      暂时去除掉 **************************************
            if ( ($student['grade_id'] == 11||$student['grade_id'] == 12) && !$subject_first[subject_id]&&!$subject_first[fenshu]&&! $subject_first[shijian] )
            {
                message('第一次参加的选考科目及成绩', 'student/profile/awards');
            }
            /**************************************************************/

        }
        else
        {
            if ( ! $student = $this->session->userdata('student'))
            {
                message('请填写基本信息！', 'student/profile/basic');
            }


            if (!$sbinfo = $this->session->userdata('student_base'))
            {
                message('请填写学习概况！', 'student/profile/base');
            }

            if (is_array($sbinfo))
            {
                $student = array_merge($student, $sbinfo);
            }
            if (!isset($student['sbs_stunumtype'])
                || !is_array($student['sbs_stunumtype']))
            {
                $student['sbs_stunumtype'] = array();
            }
            if (!isset($student['sbclassid_classid'])
                || !is_array($student['sbclassid_classid']))
            {
                $student['sbclassid_classid'] = array();
            }


            $score_ranks = $this->session->userdata('score_ranks');
            $start_grade = $student['grade_id']<3 ? 1 : $student['grade_id']-2;
            $grades = array();
            for ($i=$student['grade_id']; $i>=$start_grade; $i--)
            {
                $grades[] = $i;

            }

            foreach ($score_ranks as $v)
            {
                if (! in_array($v[grade_id], $grades))
                {
                    message('请填写最近三年年级成绩排名', 'student/profile/awards');
                    // $message[] = '请填写最近三年年级成绩排名';
                    break;
                }
            }

            $awards_list = $this->session->userdata('awards_list');
            $xuekao_xuankao = $this->session->userdata('xuekao_xuankao');
            $subject_first = unserialize($xuekao_xuankao[subject_first]);


            if ( $student['grade_id'] > 1 && ! $score_ranks )
            {
                message('请填写学习成绩', 'student/profile/awards');
            }
            if ( ($student['grade_id'] == 11||$student['grade_id'] == 12) && !$subject_first[subject_id]&&!$subject_first[fenshu]&&! $subject_first[shijian] )
            {
                message('第一次参加的选考科目及成绩', 'student/profile/awards');
            }

            if ( ! $practice = $this->session->userdata('practice'))
            {
                message('请填写社会实践情况！', 'student/profile/practice');
            }
            if ( ! $student_wish = $this->session->userdata('student_wish'))
            {
                message('请填写学生意愿！', 'student/profile/wish');
            }
            if ( ! $parent_wish = $this->session->userdata('parent_wish'))
            {
                message('请填写家长意愿！', 'student/profile/pwish');
            }
            $this->session->set_userdata('complete', 1);
        }

        $grades = C('grades');
        // 使用Yaf样式RegionModel代替
        //$this->load->model('admin/region_model');
        //$this->load->model('admin/school_model');
        $student['birthday'] = date('Y-m-d', $student['birthday']);
        //$student['region_text'] = $this->region_model->get_region_text(array($student['province'],$student['city'],$student['area']));
        $row1 = SchoolModel::schoolInfo($student['school_id'],'school_name');
        $student['school_name'] = $row1['school_name'];
        $student['grade_name'] = isset($grades[$student['grade_id']]) ? $grades[$student['grade_id']] : '';

        $data['uid']           = $uid;
        $data['grades']        = $grades;
        $specs = array(
                '0'=>'哲学',
                '1'=>'经济学',
                '2'=>'法学',
                '3'=>'教育学',
                '4'=>'文学',
                '5'=>'历史学',
                '6'=>'理学',
                '7'=>'工学',
                '8'=>'农学',
                '9'=>'医学',
                '10'=>'军事学',
                '11'=>'管理学',
                '12'=>'艺术类',
                '13'=>'不清楚'

        );

        $data['subjects']        = C('subject');
        $data['specs']     = $specs;
        $data['xuekao_xuankao']        = $xuekao_xuankao;
        $data['student']       = $student;
        $data['score_ranks']   = $score_ranks;
        $data['awards_list']   = $awards_list;
        $data['practice']      = $practice;
        $data['student_wish']  = $student_wish;
        $data['parent_wish']   = $parent_wish;
        $data['ranks']     = array('1'=>'A','2'=>'B','3'=>'C','4'=>'E');
        $data['subject_list']  = C('subject');
        $data['awards_levels'] = C('awards_level');

        $data['stunumtype_list'] = CourseModel::courseStuNumTypeList();
        $data['class_list'] = ClassModel::get_class_list($student['grade_id']);
        // 模版
        $this->load->view('profile/preview', $data);
    }
    
    /**
     * 按用户查询交易信息
     */
    public function transaction()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;

        if ($uid)
        {
            /*
             * 基本信息
             */
            $uid = intval($uid);
            $student = CommonModel::get_student($uid);
            if (empty($student))
            {
                message('信息不存在');
                return;
            }
            $data['student'] = $student;
            $query = array();
            $search = array();
            $query['tr_uid'] = $uid;
            if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
            {
                $query['s_time'] = strtotime(
                        $_GET['begin_time'] . '00:00:59');
                $search['begin_time'] = $_GET['begin_time'];
            }
            if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
            {
                $query['e_time'] = strtotime(
                        $_GET['end_time'] . '23:59:59');
                $search['end_time'] = $_GET['end_time'];
            }
            
            $query['order_by'] = "tr_createtime DESC";
            
            // 公共数据
            $total = TransactionRecordModel::transactionRecordListCount($query);
            /*
             * 分页读取数据列表，并处理相关数据
             */
            $size = 12;
            $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                    $_GET['page']) : 1;
            
            $list = array();
            if ($total)
            {
                $list = TransactionRecordModel::transactionRecordList('*', $query, $page, $size);
            }
            
            $data['list'] = $list;
            $data['search'] = $search;
            
            // 分页
            $purl = site_url('student/profile/transaction/');
            $data['pagination'] = multipage($total, $size, $page, $purl);
            
            // 模版
            $this->load->view('profile/transaction', $data);
        }
    }

    /**
     * 测试报名
     * @param int $exam     期次id
     * @param int $place    场次id, 多个ID用英文逗号分隔开
     * @param int $p_id     产品id
     * @return json 成功/失败
     */
    public function place_in()
    {
        $exam = intval($this->input->post('exam'));
        $place = $this->input->post('place');
        $uid = $this->session->userdata('uid');
        $p_id = intval($this->input->post('p_id'));
        $force = intval($this->input->post('force'));
        $b_pushcourse = intval($this->input->post('b_pushcourse'));

        if (!Validate::isJoinedIntStr($place))
        {
            output_json(CODE_ERROR, '报名失败,考场不正确');
        }

        // 检查是否存在该学生
        $account = StudentModel::get_student($uid,
                'account,account_status');
        if (!count($account['account']))
        {
            output_json(CODE_ERROR, '报名失败,不存在该学生.');
        }
        else if ($account['account_status'])
        {
            output_json(CODE_ERROR, '报名失败,学生帐号已被冻结');
        }
        if (CommonModel::get_product_trans($p_id, $uid, $place, $exam))
        {
            output_json(CODE_ERROR, '报名失败,已报名该产品');
        }
        $res = CommonModel::get_product_list($p_id);
        if (!$res)
        {
            output_json(CODE_ERROR, '报名失败,产品不存在');
        }
        else
        {
            $price = $b_pushcourse ? $res['p_price_pushcourse'] : $res['p_price'];
            $pc_id = $res['pc_id'];
        }
        $account = $account['account'];
        $account1 = $account - $price;
        if ($account1 < 0)
        {
            output_json(CODE_ERROR, '帐号余额不足');
        }

        $inserts = array();

        $error = array();
        $code = CODE_ERROR;
        $place_id_arr = array_unique(explode(',', $place));
        $place_id_arr2 = array();
        foreach ($place_id_arr as $place_id)
        {
            if ($place_id)
            {
                $query = $this->db->select(
                        'p.*,e.exam_name,e.exam_id,e.exam_pid,e.grade_id')//,sch.school_name')
                    ->from('exam_place p')
                    ->join('exam e', 'p.exam_pid=e.exam_id')
                    //->join('school sch', 'p.school_id=sch.school_id')
                    ->where(array(
                        'p.place_id'=>$place_id))
                    ->get();
                $place = $query->row_array();
            }
            else
            {
                continue;
            }
            if (empty($place))
            {
                $error[] = "考场[$place_id]信息不存在";
                //output_json(CODE_ERROR, '考场信息不存在');
                continue;
            }
            $ids = $uid;
            // 控制考场只能在未开始考试操作
            $no_start = ExamPlaceModel::place_is_no_start($place_id);
            if (!$no_start)
            {
                $error[] = "考场[$place_id]正确考试或已结束,无法报名";
                continue;
                //output_json(CODE_ERROR, '该考场正在考试或已结束，无法做此操作');
            }
            // $ids = my_intval($ids);
            // $school_id = (int)$this -> input ->post('school_id');
            // 该考场所考到的学科
            $subject_ids = array();
            $query = $this->db->select('subject_id')
                ->from('exam_place_subject')
                ->where(array(
                    'place_id'=>$place['place_id']))
                ->get();
            $subjects = $query->result_array();
            $subject_ids = array();
            foreach ($subjects as $subject)
            {
                $subject_ids[] = $subject['subject_id'];
            }
            $subject_ids = count($subject_ids) ? implode(',', $subject_ids) : '""';
            $place['start_time'] = $place['start_time'] + 1;
            $place['end_time'] = $place['end_time'] - 1;
            if ($force == 0)
            {
                $sql = "SELECT count(u.uid) FROM rd_student u
                WHERE  u.grade_id=$place[grade_id] AND u.is_delete=0 AND u.uid =$ids";
                $query = Fn::db()->fetchOne($sql);
                if ($query == 0)
                {
                    $error[] = "考场[$place_id]您的年级不符合要求";
                    $code = -2;
                    continue;
                    //output_json('-2', '你的年级不符合要求');
                }
            }
            $not_exists_sql = <<<EOT
SELECT uid 
FROM rd_exam_place_student ps, rd_exam_place p, rd_exam e
WHERE e.exam_isfree = 0
    AND ps.place_id = p.place_id 
    AND p.place_index = {$place['place_index']} 
    AND ps.uid = u.uid 
    AND p.exam_pid = e.exam_id
    AND 
    (
        (
            (p.start_time >= {$place['start_time']} 
                AND p.start_time <= {$place['end_time']}
            ) OR (
                p.end_time >= {$place['start_time']} 
                AND p.end_time <= {$place['end_time']}
            ) OR (
                p.start_time <= {$place['start_time']} 
                AND p.end_time >= {$place['end_time']}
            )
        ) 
        OR p.place_id IN (
            SELECT DISTINCT(place_id) FROM rd_exam_place_subject eps 
            WHERE eps.subject_id IN ({$subject_ids}) 
                AND eps.exam_pid = {$place['exam_id']}
        )
    )
EOT;
            $sql = <<<EOT
SELECT u.uid FROM rd_student u 
WHERE u.is_delete = 0 AND u.uid = {$ids} AND NOT EXISTS({$not_exists_sql})
EOT;
            $tmp_inserts = array();
            $query = $this->db->query($sql);
            foreach ($query->result_array() as $row)
            {
                $vrow = array('place_id' => $place_id, 'uid' => $row['uid']);
                $tmp_inserts[] = $vrow;
                $inserts[] = $vrow;
            }
            
            if (empty($tmp_inserts))
            {
                $error[] = "考场[$place_id]时间段内您已经参加了相同时间段其它考试";
                continue;
                //output_json(CODE_ERROR, '你已经参加相同时间段其他考试');
            }
            $place_id_arr2[] = $place_id;
        }

        if (!empty($error) || empty($inserts))
        {
            output_json($code, "报名失败\n" . implode("\n", $error));
        }

            
        $vc = C('virtual_currency');
        $pt_log = $account . $vc['name'] . '--' . $account1 . $vc['name'];
        $txt_account = - $price;

        $db = Fn::db();
        $flag = false;
        if ($db->beginTransaction())
        {
            foreach ($inserts as $val)
            {
                $db->replace('rd_exam_place_student', $val);
            }

            $param = array(
                'tr_uid' => $uid,
                'tr_type' => 3, //购买产品
                'tr_pid'  => $p_id,
                'tr_flag' => 1,
                'tr_money'   => $account1,
                'tr_trade_amount' => $txt_account,
                'tr_comment' => $pt_log,
            );
            $number = TransactionRecordModel::addTransactionRecord($param);
            
            if ($b_pushcourse)
            {
                $now = date('Y-m-d H:i:s');
                foreach ($place_id_arr2 as $place_id)
                {
                    $db->insert('t_course_push', array('cp_stuuid' => $uid,
                        'cp_exampid' => $exam, 
                        'cp_examplaceid' => $place_id,
                        'cp_addtime' => $now));
                }
            }
            $sql = <<<EOT
UPDATE rd_student SET account = account - {$price} WHERE uid = {$uid}
EOT;
            $db->exec($sql);
            $flag = $db->commit();
            if (!$flag)
            {
                $db->rollBack();
                $error[] = "考场[$place_id]报名失败";
            }
        }
        if (!$flag)
        {
            output_json(CODE_ERROR, "报名失败\n" . implode("\n", $error));
        }
        else
        {
            output_json(CODE_SUCCESS, '报名成功.');
        }
    }

    /**
     * 充值
     */
    public function pay()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        if (!C('paycharge_enable')) message('您没有权限访问该功能');
       
        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;
       
        if ($uid)
        {
            /*
             * 基本信息
            */
            $uid = intval($uid);
            $student = CommonModel::get_student($uid);
            if (empty($student))
            {
                message('信息不存在');
                return;
            }
            $data['student'] = $student;
        }
        $this->load->view('profile/pay.phtml', $data);
    }

    /**
     * 充值
     */
    public function paying()
    {
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        if (!C('paycharge_enable')) message('您没有权限访问该功能');
        
        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['uinfo'] = $this->_uinfo;
        
        $account = trim($this->input->post('begin_time'));
        if (!is_numeric($account))
        {
            message('请输入正确的数字');
        }
        $account2 = bcadd($account, 0.0, 1);
        if (bccomp($account2, $account, 6) != 0)
        {
            message('请输入正确的最多保留一位小数的数字');
        }
        if (bccomp($account2, '0.0', 1) <= 0)
        {
            message('请输入正确的大于零的数字');
        }
        $account = $account2;
        $insert_array = array();
        $insert_array = array();
        if ($uid)
        {
            /*
             * 基本信息
            */
            $uid = intval($uid);
            $student = CommonModel::get_student($uid);
            if (empty($student))
            {
                message('信息不存在');
                return;
            }
            
            $tr_amount = $account * 10;
            $param = array(
                'tr_uid' => $uid,
                'tr_type' => 1,
                'tr_flag' => 0,
                'tr_money'   => $student['account'] + $tr_amount,
                'tr_cash'    => $account,
                'tr_trade_amount' => $tr_amount,
                'tr_comment' => '支付宝充值',
            );
            $number = TransactionRecordModel::addTransactionRecord($param);

            $html_text = StudentAlipayModel::paying($number, $account);
            $data = array('html_text' => $html_text);
            $this->load->view('profile/paying', $data);
        }
        else
        {
            $this->load->view('profile/paying');
        }
    }

    /**
     * 功能：支付宝页面跳转同步通知页面
     */
    public function return_url()
    {
        $uid = $this->_uinfo['uid'];
        $url = site_url('student/profile/transaction');
        if (!$uid)
        {
            message("验证失败(会话失效)", site_url('student/index/index'));
        }
        
        $bOk = StudentAlipayModel::paying_return($msg);
        message($msg, $url, $bOk ? 'success' : 'notice');
    }

    /**
     * 功能：支付宝页面跳转异步通知页面
     */
    public function notify_url()
    {
        StudentAlipayModel::paying_notify();
    }

    public function submit_simple()
    {
        Fn::ajax_call($this, 'logout');
        if (!$this->session->userdata('complete'))
        {
            // 或未填写完整信息。跳转到基本信息页面
            redirect('student/profile/basic');
        }

        // 读取sesseion数据
        $student      = $this->session->userdata('student');
        $student['source_from'] = 3; //普通注册

        // 检查email是否已注册
        $query = $this->db->select('uid')->get_where('student',array('email'=>$student['email']), 1);
        if ($query->num_rows())
        {
            message('Email地址已被注册！');
        }

        // 如果上传图片，转移图片
        if ($student['picture'])
        {
            $new_picture = 'uploads/student/'.date('Ym').'/'.basename($student['picture']);
            if (my_copy($student['picture'], $new_picture, TRUE))
            {
                $student['picture'] = $new_picture;
            }
        }

        //补充学生的所在区域
        //$student['school_id'] = 0;
        $student['province'] = 0;
        $student['city'] = 0;
        $student['area'] = 0;

        //将学校名称移除
        if (isset($student['school_name'])) unset($student['school_name']);
        if (isset($student['external_account']))
        {
            $student_ticket = $student['external_account'];
        }

        $result = StudentModel::add($student);
        if ($result['success'] == false)
        {
            message($result['msg']);
        }
        $uid = $result['uid'];
        $exam_ticket = $result['exam_ticket'];

        StudentModel::studentAjaxLogin(array('ticket' => $exam_ticket, 'password' => $student['password']), true);
        $this->_uinfo = StudentModel::studentLoginUInfo();

        // 清除其他session
        $unset_items = array('student' => '', 'score_ranks' => '','awards_list'=>'', 'practice' => '', 'student_wish' => '', 'parent_wish' => '','xuekao_xuankao' => '', 'complete' => '');
        $this->session->unset_userdata($unset_items);

        // 发送邮件
        $email_tpl = C('email_template/register');
        $mail = array(
        	'student' => $student,
        	'hash'    => email_hash('encode', $uid),
        );

        send_email($email_tpl['subject'], $this->load->view($email_tpl['tpl'], $mail, TRUE), $student['email']);

        // 成功信息显示
        $data['student']      = $student;
        $data['exam_ticket']  = $result['exam_ticket'];
        $line_width = array(1,2,3,4,4,5,5,6,7,8);
        $data['line_width'] = $line_width;
        $data['uinfo'] = $this->_uinfo;
        // 模版
        $this->session->set_userdata('complete', 0);
        $this->load->view('profile/success', $data);
    }

    // 提交报名信息
    public function submit()
    {
        Fn::ajax_call($this, 'logout');
        $uid = $this->_uinfo['uid'];
        if ($uid OR  ! $this->session->userdata('complete'))
        {
            // 已注册，或未填写完整信息。跳转到报名信息复核页面
            redirect('student/profile/preview');
        }

        // 读取sesseion数据
        $student      = $this->session->userdata('student');
        $score_ranks  = $this->session->userdata('score_ranks');
        $awards_list  = $this->session->userdata('awards_list');
        $practice     = $this->session->userdata('practice');
        $student_wish = $this->session->userdata('student_wish');
        $parent_wish  = $this->session->userdata('parent_wish');
        $xuekao_xuankao  = $this->session->userdata('xuekao_xuankao');
        $student['source_from'] = 3; //普通注册

        // 检查email是否已注册
        $query = $this->db->select('uid')->get_where('student',array('email'=>$student['email']), 1);
        if ($query->num_rows())
        {
            message('Email地址已被注册！');
        }

        // 如果上传图片，转移图片
        if ($student['picture'])
        {
            $new_picture = 'uploads/student/'.date('Ym').'/'.basename($student['picture']);
            if (my_copy($student['picture'], $new_picture, TRUE))
            {
                $student['picture'] = $new_picture;
            }
        }

        if (!is_array($awards_list)) $awards_list = array();
        $new_awards_list = array();
        foreach ($awards_list as $type_id => $type_list)
        {
            $new_awards_list = array_merge($new_awards_list, $type_list);
        }

        $extends = array(
            'score_ranking' => &$score_ranks,
            'awards_list'   => &$new_awards_list,
            'practice'      => &$practice,
            'student_wish'  => &$student_wish,
            'parent_wish'   => &$parent_wish,
            'xuekao_xuankao'   => &$xuekao_xuankao,
        );

        //补充学生的所在区域
        $school_id = $student['school_id'];
        if ($school_id)
        {
            $school = Fn::db()->fetchRow("select province,city,area from rd_school where school_id=$school_id");
        }
        else
        {
            $school = array();
        }
        $student['province'] = isset($school['province']) ? $school['province'] : 0;
        $student['city'] = isset($school['city']) ? $school['city'] : 0;
        $student['area'] = isset($school['area']) ? $school['area'] : 0;

        //将学校名称移除
        if (isset($student['school_name'])) unset($student['school_name']);
        if (isset($student['external_account']))
        {
            $student_ticket = $student['external_account'];
        }

        $result = StudentModel::add($student, $extends);
        if ($result['success'] == false)
        {
            message($result['msg']);
        }
        $uid = $result['uid'];
        $exam_ticket = $result['exam_ticket'];

        {
            $student_base = $this->session->userdata('student_base');

            $student1 = array();
            //$student1['school_id'] = $student_base['school_id'];
            $student1['address']   = $student_base['address'];
            $student1['zipcode']   = $student_base['zipcode'];

            $sbinfo = array();
            $sbinfo['sb_addr_provid'] = $student_base['sb_addr_provid'];
            $sbinfo['sb_addr_cityid'] = $student_base['sb_addr_cityid'];
            $sbinfo['sb_addr_areaid'] = $student_base['sb_addr_areaid'];
            $sbinfo['sb_addr_desc'] = $student_base['sb_addr_desc'];

            // 培训机构、培训课程、授课教师
            $sbcinfo = array();
            $sbcinfo['no_tiid'] = $student_base['no_tiid'];
            $sbcinfo['sbc_tiid'] = $student_base['sbc_tiid'];
            $sbcinfo['ti_name']   = $student_base['ti_name'];
            $sbcinfo['sbc_corsid'] = $student_base['sbc_corsid'];
            $sbcinfo['cors_cmid'] = $student_base['cors_cmid'];
            $sbcinfo['cors_name']   = $student_base['cors_name'];
            $sbcinfo['sbc_teachers']   = $student_base['sbc_teachers'];

            $sbs_stunumtype = $student_base['sbs_stunumtype'];
            if (!is_array($sbs_stunumtype))
            {
                $sbs_stunumtype = array();
            }

            $sbclassid_classid = $student_base['sbclassid_classid'];
            if (!is_array($sbclassid_classid))
            {
                $sbclassid_classid = array();
            }

            $db = Fn::db();
            $bOk = false;
            try
            {
                if ($db->beginTransaction())
                {
                    $db->update('rd_student', $student1, "uid = $uid");
                    //$db->delete('t_student_base', "sb_uid = $uid");
                    $sbinfo['sb_uid'] = $uid;
                    $db->insert('t_student_base', $sbinfo);

                    //$db->delete('t_student_base_classid', "sbclassid_uid = $uid");
                    foreach ($sbclassid_classid as $v)
                    {
                        $db->insert('t_student_base_classid', 
                            array('sbclassid_uid' => $uid,
                            'sbclassid_classid' => $v));
                    }

                    //$db->delete('t_student_base_stunumtype', "sbs_uid = $uid");
                    foreach ($sbs_stunumtype as $v)
                    {
                        $db->insert('t_student_base_stunumtype', 
                            array('sbs_uid' => $uid,
                            'sbs_stunumtype' => $v));
                    }

                    //$db->delete('t_student_base_course', 'sbc_uid = ' . $uid);
                    if (empty($sbcinfo['no_tiid']))
                    {
                        $now_time = time();
                        if (!$sbcinfo['sbc_tiid'])
                        {
                            $row = array(
                                'ti_name' => $sbcinfo['ti_name'],
                                'ti_typeid' => 1,// 培训学校
                                'ti_flag' => $now_time,
                                'ti_priid' => 0,
                                'ti_provid' => $sbinfo['sb_addr_provid'],
                                'ti_cityid' => $sbinfo['sb_addr_cityid'],
                                'ti_areaid' => $sbinfo['sb_addr_areaid'],
                                'ti_addtime' => date('Y-m-d H:i:s', $now_time),
                                'ti_adduid' => 1);

                            $db->insert('t_training_institution', $row);
                            $ti_id = $db->lastInsertId(
                                't_training_institution', 'ti_id');
                            $sbcinfo['sbc_tiid'] = $ti_id;
                        }
                        if (!$sbcinfo['sbc_corsid'])
                        {
                            if ($sbcinfo['cors_cmid'] != 1)
                            {
                                $sbcinfo['cors_cmid']  = 2;
                            }
                            $row = array(
                                'cors_name' => $sbcinfo['cors_name'],
                                'cors_cmid' => $sbcinfo['cors_cmid'],
                                'cors_flag' => $now_time,
                                'cors_tiid' => $sbcinfo['sbc_tiid'],
                                'cors_stunumtype' => $sbcinfo['cors_cmid'],
                                'cors_addtime' => date('Y-m-d H:i:s', $now_time),
                                'cors_adduid' => 1);
                            $db->insert('t_course', $row);
                            $cors_id = $db->lastInsertId('t_course', 'cors_id');
                            $sbcinfo['sbc_corsid'] = $cors_id;
                        }
                        $db->insert('t_student_base_course', 
                            array(
                            'sbc_uid' => $uid,
                            'sbc_idx' => 0,
                            'sbc_tiid' => $sbcinfo['sbc_tiid'],
                            'sbc_corsid' => $sbcinfo['sbc_corsid'],
                            'sbc_teachers' => $sbcinfo['sbc_teachers']));
                    }

                    $bOk = $db->commit();
                    if (!$bOk)
                    {
                        $err = $db->errorInfo()[2];
                        $db->rollBack();
                        //message($err);
                    }
                }
                if (!$bOk)
                {
                    //message('执行事务处理失败');
                }
            }
            catch (Exception $e)
            {
                //message($e->getMessage());
            }
        }

        StudentModel::studentAjaxLogin(array('ticket' => $exam_ticket, 'password' => $student['password']), true);
        // 清除其他session
        $unset_items = array('student' => '', 'student_base', 'score_ranks' => '','awards_list'=>'', 'practice' => '', 'student_wish' => '', 'parent_wish' => '','xuekao_xuankao' => '', 'complete' => '');
        $this->session->unset_userdata($unset_items);

        // 发送邮件
        $email_tpl = C('email_template/register');
        $mail = array(
        	'student' => $student,
        	'hash'    => email_hash('encode', $uid),
        );

        send_email($email_tpl['subject'], $this->load->view($email_tpl['tpl'], $mail, TRUE), $student['email']);

        // 成功信息显示
        $data['student']      = $student;
        $data['exam_ticket']  = $result['exam_ticket'];
        $line_width = array(1,2,3,4,4,5,5,6,7,8);
        $data['line_width'] = $line_width;
        $this->_uinfo = StudentModel::studentLoginUInfo();
        $data['uinfo'] = $this->_uinfo;
        // 模版
        $this->load->view('profile/success', $data);
    }

    /**
     * 检查邮箱是否已经存在
     */
    public function check_email_exists()
    {
    	$bool = 0;
    	$uid = $this->_uinfo['uid'];
    	$email = trim($this->input->post('email'));

        $student = $this->db->select('uid')->get_where('student',array('email'=>$email))->row_array();
        if ($student && $student['uid'] != $uid)
        {
            $bool = 1;
        }

    	output_json(CODE_SUCCESS, 'ok', $bool);
    }

    /**
     * 检查idcard是否已注册
     */
    public function check_idcard_exists()
    {
        $bool = 0;

        $uid = $this->_uinfo['uid'];
        $idcard = trim($this->input->post('idcard'));

        $student = $this->db->select('uid')->get_where('student',array('idcard'=>$idcard))->row_array();
        if ($student && $student['uid'] != $uid)
        {
            $bool = 1;
        }

        output_json(CODE_SUCCESS, 'ok', $bool);
    }

    /**
     * 生成省市区+学校联动的源数据为JS文件.
     */
    public function generate_schools()
    { //TODO
    	$school_info = $region_info = $school_names = $region_names = $region_parents = $region_depths = $region_schools = $grade_schools = array();


    	$school_info = SchoolModel::get_v_schools();
    	foreach ($school_info as $k => $v){
    		$school_names[$v['school_id']] = $v['school_name'];//1. 学校id=>名称关系
    		$region_schools[$v['school_id']] = $v['area'];//2. 学校与所属区县的关系
    		$grade_schools[2][] = $v['school_id'];  //3. 年级段（小初高1,32,）与学校的关系
    		$grade_schools[3][] = $v['school_id'];  //年级段（小初高1,2,3）与学校的关系
    	}

    	$region_info = RegionModel::get_all_region();
    	foreach ($region_info as $k2 => $v2){
    		$region_names[$v2['region_id']] = $v2['region_name'];//4. 地域id=>名称关系
    		$region_parents[$v2['region_id']] = $v2['parent_id'];//5. 地域每级的关系
    	}

    	//6. 地域深度
    	$region_array1 = $region_array2 = $region_array3 = $region_depths = array();
    	$region_array1 = RegionModel::get_regions_by_depth(1);//省
    	$region_array2 = RegionModel::get_regions_by_depth(2);//市
    	$region_array3 = RegionModel::get_regions_by_depth(3);//区、县

        foreach($region_array1 as $v3)
        {
            $region_depths[1][] = $v3['region_id'];
    	}
        foreach($region_array2 as $v4)
        {
            $region_depths[2][] = $v4['region_id'];
    	}
        foreach($region_array3 as $v5)
        {
            $region_depths[3][] = $v5['region_id'];
    	}

    	$str_1 = "var school_names = " . "'" . json_encode($school_names) . "';\n";
    	$str_2 = "var region_schools = " . "'" . json_encode($region_schools) . "';\n";
    	$str_3 = "var grade_schools = " . "'" . json_encode($grade_schools) . "';\n";
    	$str_4 = "var region_names = " . "'" . json_encode($region_names) . "';\n";
    	$str_5 = "var region_parents = " . "'" . json_encode($region_parents) . "';\n";
    	$str_6 = "var region_depths = " . "'" . json_encode($region_depths) . "';\n";
    	file_put_contents(FCPATH.'js/schools_data.js',$str_1.$str_2.$str_3.$str_4.$str_5.$str_6);
    }

    public function examresult()
    {
        Fn::ajax_call($this, 'logout', 'login');


        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $uid = $this->_uinfo['uid'];
        $data = array();
        $data['page_title'] = '考试成绩';
        $data['uinfo'] = $this->_uinfo;
        
        $data['student'] = StudentModel::get_student($this->_uinfo['uid']);

        //检查学生信息是否完善
        //$check_message = $this->check_perfect_student();

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
            if (!empty($exam_list))
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
                    $source_path = $v['id'] ."/$uid.zip";
                    $filepath = realpath(dirname(APPPATH)) . "/cache/zip/report/" . $source_path;
                    if (!file_exists($filepath))
                    {
                        //continue;
                    }

                    if (!isset($exam_list[$v['exam_pid']]['list']))
                    {
                        $exam_list[$v['exam_pid']]['list'] = array();
                    }
                    
                    $v['subject_name'] = $this->_subject_name($uid, $v['exam_pid'], $v['subject_id']);
                    
                    $exam_list[$v['exam_pid']]['list'][] = $v;
                }
                asort($exam_list);
            }
            
            $data['exam_result_list'] =  array_values($exam_list);
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
        $this->load->view('profile/examresult', $data);
    }

    /**
     * 修改密码
     */
    public function editpwd()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if (!$this->_uinfo['uid'])
        {
            redirect('student/index/login');
        }

        $data = array();
        $data['uinfo'] = $this->_uinfo;

        $uid = $this->_uinfo['uid'];
        if ($oldpwd = $this->input->post('oldpwd'))
        {
            $newpwd = $this->input->post('newpwd');
            $newpwd_confirm = $this->input->post('newpwd_confirm');

            if (is_string($passwd_msg = is_password($newpwd))) {
            	message($passwd_msg);
            }
            if ( $newpwd != $newpwd_confirm )
            {
                message('新密码两次输入不一致！');
            }

            $query = $this->db->select('password')->get_where('student', array('uid'=>$uid));
            $user = $query->row_array();
            if ($user['password'] !== my_md5($oldpwd))
            {
                message('原密码错误！');
            }

            $this->db->update('student', array('password'=>my_md5($newpwd)), array('uid'=>$uid));
            message('密码修改成功！', 'student/profile/preview', 'success');
        }
        else
        {
            $this->load->view('profile/editpwd', $data);
        }
    }


            
            
    /**
     * 检查学生是否在考试期次中
     */
    private function _check_exam_student_list(array &$student)
    {
        $exam_pid = $this->session->userdata('reg_student_exam_pid');
        if ($exam_pid > 0)
        {
            $exam = ExamModel::get_exam_select($exam_pid);

            if (empty($exam))
            {
                message('考试期次不存在', '/');
            }

            if ($exam['status'] != 1)
            {
                message('该考试期次还没有启动报名', '/');
            }


            $student_name = $student['last_name'] . $student['first_name'];
            $student_ticket = trim($student['external_account']);

            //判断是否存在考试期次
            $uid = $this->session->userdata('uid');
            if (!$uid)
            {
                $row = $this->db->get_where('exam_student_list',
                        array('exam_pid'=> $exam_pid, 'student_name'=>$student_name, 'student_ticket'=>$student_ticket))
                        ->row_array();

                if (!$row)
                {
                    message('你不在此次考试期次中，无法报名！');
                }

                if ($row['uid'] > 0 && $this->db->select('uid')->get_where('student', array('uid'=>$row['uid']))->row_array())
                {
                    message('您已报名该考试了，无需重复报名！');
                }
            }
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
