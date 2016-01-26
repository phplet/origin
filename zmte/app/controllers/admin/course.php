<?php if ( ! defined('BASEPATH')) exit();
class Course extends A_Controller
{
    public function __construct()//{{{
    {
        parent::__construct();
        /*
        require_once (APPPATH.'config/app/admin/recycle.php');
        if ($_FILES)
        {
            $config['upload_path'] = _UPLOAD_ROOT_PATH_.'uploads/course/'.date('Ymd').'/';
            $config['allowed_types'] = 'gif|jpg|jpeg|png';
            $config['max_size'] = '1024';
            $config['max_width'] = '2000';
            $config['max_height'] = '2000';
            $config['encrypt_name'] = TRUE;
            $this->load->library('upload', $config);
        }
         */
   }//}}}

    /**
     * 课程首页,指向课程列表页面admin/course/corslist
     */
    public function index()
    {
        $this->corslist();
    }

    /**
     * 课程列表页面
     */
    public function corslist()
    {
        Fn::ajax_call($this, 'removeCORS');
        $param = array();
        if (isset($_GET['page']))
        {
            $page = intval($_GET['page']);
            if ($page < 1)
            {
                $page = 1;
            }
        }
        else
        {
            $page = 1;
        }
        
        $param['cors_name'] = $this->input->get('cors_name');
        $param['ti_name'] = $this->input->get('ti_name');
        $param['cors_cmid'] = $this->input->get('cors_cmid');
        $param['grade_id_str']=$this->input->get('grade_id_str');
        $param['subject_id_str']=$this->input->get('subject_id_str');
        $param['class_id_str']=$this->input->get('class_id_str');

        
        $data = array();
        $data['param'] = $param;
        $data['mode_list'] = CourseModel::courseModeList();
        $data['cors_list'] = 
            CourseModel::courseList('*', $param, $page);
        $data['cors_list_count'] = CourseModel::courseListCount($param);


        $cors_id_arr = array();
        if (!empty($data['cors_list']))
        {
            foreach ($data['cors_list'] as $v)
            {
                $cors_id_arr[] = $v['cors_id'];
            }
        }
        $cors_id_str = implode(',', $cors_id_arr);
        unset($cors_id_arr);

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class ORDER BY sort_order
EOT;
        $data['classid_map'] = Fn::db()->fetchPairs($sql);

        if ($cors_id_str)
        {
            $data['cors_gradeid'] = 
                CourseModel::courseGradeIDPairs($cors_id_str);
            $data['cors_subjectid'] = 
                CourseModel::courseSubjectIDPairs($cors_id_str);
            $data['cors_classid'] = 
                CourseModel::courseClassIDPairs($cors_id_str);
        }
        else
        {
            $data['cors_gradeid'] = array();
            $data['cors_subjectid'] = array();
            $data['cors_classid'] = array();
        }
        $this->load->view('course/corslist', $data);
    }

    /**
     * 选择课程校区列表页面
     */
    public function selcorscampuslist()
    {
        $param = array();
        if (isset($_GET['page']))
        {
            $page = intval($_GET['page']);
            if ($page < 1)
            {
                $page = 1;
            }
        }
        else
        {
            $page = 1;
        }
        
        $param['cors_name'] = $this->input->get('cors_name');
        $param['ti_name'] = $this->input->get('ti_name');
        $param['cors_cmid'] = $this->input->get('cors_cmid');
        $param['grade_id_str']=$this->input->get('grade_id_str');
        $param['subject_id_str']=$this->input->get('subject_id_str');
        $param['class_id_str']=$this->input->get('class_id_str');

        $data = array();
        $data['param'] = $param;
        $data['mode_list']=CourseModel::courseModeList();
        $data['cors_list'] = 
            CourseModel::courseList('*', $param, $page);
        $data['cors_list_count'] = 
            CourseModel::courseListCount($param);

        $cors_id_arr = array();
        if (!empty($data['cors_list']))
        {
            foreach ($data['cors_list'] as $v)
            {
                $cors_id_arr[] = $v['cors_id'];
            }
        }
        $cors_id_str = implode(',', $cors_id_arr);
        unset($cors_id_arr);

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class ORDER BY sort_order
EOT;
        $data['classid_map'] = Fn::db()->fetchPairs($sql);

        if ($cors_id_str)
        {
            $data['cors_gradeid'] = 
                CourseModel::courseGradeIDPairs($cors_id_str);
            $data['cors_subjectid'] = 
                CourseModel::courseSubjectIDPairs($cors_id_str);
            $data['cors_classid'] = 
                CourseModel::courseClassIDPairs($cors_id_str);
        }
        else
        {
            $data['cors_gradeid'] = array();
            $data['cors_subjectid'] = array();
            $data['cors_classid'] = array();
        }

        if ($cors_id_str)
        {
            $cors_campus_list = CourseModel::courseCampusList('*', 
                array('cc_corsid' => $cors_id_str));
        }
        else
        {
            $cors_campus_list = array();
        }
        $cors_campus_map = array();
        foreach ($cors_campus_list as $v)
        {
            if (!isset($cors_campus_map[$v['cc_corsid']]))
            {
                $cors_campus_map[$v['cc_corsid']] = array();
            }
            $cors_campus_map[$v['cc_corsid']][] = $v;
        }
        unset($cors_campus_list);
        $data['cors_campus_map'] = $cors_campus_map;
        $this->load->view('course/selcorscampuslist', $data);
    }

    /**
     * 删除课程ajax方法
     * @param   string  $cors_id_str    形如1,2,3样式的ID列表
     */
    public function removeCORSFunc($cors_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('course_removecors', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            CourseModel::removeCourse($cors_id_str);
            admin_log('delete', 'course', "cors_id: $cors_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增课程及编辑课程方法
     * 新增时只添加主表t_course;编辑时同时编辑子表t_course_开头的子表
     * @param   array   $param  map<string, variant>类型的参数
     *          int     cors_id     课程ID,若为0则表新增;否则表编辑
     *          string  cors_name   课程名称
     *          int     cors_tiid   课程所属培训机构.编辑时不可修改
     *          int     cors_cmid   授课模式,编辑时不可修改
     *          int     cors_stunumtype 授课班级类型
     *          int     cors_flag   状态,-1已删 0禁用 1启用 大于1表待审
     *          string  cors_url    网址
     *          string  grade_id_str    形如1,2,3样式的年级ID列表,0表所有年级
     *          string  subject_id_str  形如1,2,3样式的学科ID列表,0表所有学科
     *          string  class_id_str    形如1,2,3样式的考试类型ID列表
     *          map<int,int> kid_knprocid_pairs 知识点列表,键为知识点ID,值为
     *                                  认知类型
     *          string  cors_memo       备注
     *          list<map<string, variant>>  cc_list 课程校区子表数据
     *                  int     cc_id       课程校区子表ID,若为0表示新增
     *                  int     cc_corsid   所属课程ID
     *                  int     cc_tcid     若为空则表示为一对一课程;否则表示
     *                                      班级授课所属的校区ID
     *                  int     cc_ctfid    教师来源ID
     *                  string  cc_classtime 上课时间
     *                  date    cc_begindate    开课日期
     *                  date    cc_enddate      结束日期
     *                  int     cc_startanytime 是否随时开课,一对一时该值方可
     *                                          为1(此时cc_begindate、cc_enddate
     *                                          为空),否则必须为0
     *                  int     cc_hours    课时
     *                  double  cc_price    课程费用
     *                  int     cc_provid   上课地址省
     *                  int     cc_cityid   上课地址市
     *                  int     cc_areaid   上课地址区
     *                  string  cc_addr     上课地址明细
     *                  string  cc_ctcperson    联系人
     *                  string  cc_ctcphone     联系电话
     */
    public function setCorsFunc($param)
    {
        $resp = new AjaxResponse();
        $cors_id = $param['cors_id'] = intval($param['cors_id']);
        $param = Func::param_copy($param, 'cors_id', 'cors_name', 'cors_tiid',
            'cors_cmid', 'cors_stunumtype', 'cors_flag', 'cors_url',
            'grade_id_str', 'subject_id_str', 'class_id_str', 
            'kid_knprocid_pairs', 'cors_memo', 'cc_list', 'kid_all');
        if ($param['cors_id'] == 0)
        {
            unset($param['cors_id']);
            unset($param['cc_list']);
            unset($param['cors_memo']);
            if (!isset($param['kid_knprocid_pairs']))
            {
                $param['kid_knprocid_pairs'] = array();
            }
        }
        else
        {
            if ($param['cors_memo'] == '')
            {
                $param['cors_memo'] = NULL;
            }
        }
        if (isset($param['kid_knprocid_pairs']))
        {
            if ($param['kid_all'] == '1')
            {
                $param['kid_knprocid_pairs'] = array(0 => 0);
            }
        }
        else if ($param['kid_all'] == '1')
        {
            $param['kid_knprocid_pairs'] = array(0 => 0);
        }
        unset($param['kid_all']);
        if ($param['cors_name'] == '')
        {
            $resp->alert('课程名称不可为空');
            return $resp;
        }
        if (!Validate::isInt($param['cors_tiid']))
        {
            $resp->alert('所属培训机构不可为空');
            return $resp;
        }
        if (!Validate::isInt($param['cors_cmid'])
            || $param['cors_cmid'] < 1)
        {
            $resp->alert('授课模式必须选择');
            return $resp;
        }
        if (!Validate::isInt($param['cors_stunumtype']) 
            || $param['cors_stunumtype'] < 1)
        {
            $resp->alert('班级类型必须选择');
            return $resp;
        }
        if ($param['cors_cmid'] == 1 && $param['cors_stunumtype'] != 1
            || $param['cors_cmid'] != 1 && $param['cors_stunumtype'] == 1)
        {
            $resp->alert('授课模式和班级类型不匹配');
            return $resp;
        }
        if (!Validate::isInt($param['cors_flag']))
        {
            $resp->alert('状态标志必须选择');
            return $resp;
        }
        if ($param['cors_url'] == '')
        {
            $param['cors_url'] = NULL;
        }
        if (!Validate::isJoinedIntStr($param['grade_id_str']))
        {
            $resp->alert('年级必须选择');
            return $resp;
        }
        if (!Validate::isJoinedIntStr($param['subject_id_str']))
        {
            $resp->alert('学科必须选择');
            return $resp;
        }
        if (isset($param['class_id_str']) && $param['class_id_str'] != ''
            && !Validate::isJoinedIntStr($param['subject_id_str']))
        {
            $resp->alert('考试类型不正确');
            return $resp;
        }

        $param['gradeid_list'] = explode(',', $param['grade_id_str']);
        $param['subjectid_list'] = explode(',', $param['subject_id_str']);
        if (isset($param['class_id_str']) && $param['class_id_str'] != '')
        {
            $param['classid_list'] = explode(',', $param['class_id_str']);
        }

        if ($cors_id)
        {
            // 编辑功能
            $campus_num = count($param['cc_list']);
            if ($campus_num < 1)
            {
                $resp->alert('请至少添加一个校区');
                return $resp;
            }

            $err = NULL;
            $tcid_arr = array();
            for ($i = 0; $i < $campus_num; $i++)
            {
                $param['cc_list'][$i] = Func::param_copy($param['cc_list'][$i],
                    'cc_id', 'cc_corsid', 'cc_tcid', 'cc_ctfid', 'cc_classtime',
                    'cc_begindate', 'cc_enddate', 'cc_startanytime', 'cc_hours',
                    'cc_price', 'cc_provid', 'cc_cityid', 'cc_areaid', 
                    'cc_addr', 'cc_ctcperson', 'cc_ctcphone', 'cct_ctid_str');
                $param['cc_list'][$i]['cc_corsid'] = $cors_id;
                $err_pre = '';
                if ($param['cors_cmid'] != 1)
                {
                    $err_pre = '第' . ($i + 1) . '个校区';
                    if (!Validate::isInt($param['cc_list'][$i]['cc_tcid']))
                    {
                        $err = $err_pre . '没有选择具体的培训校区';
                        break;
                    }
                    $param['cc_list'][$i]['cc_tcid'] = 
                        intval($param['cc_list'][$i]['cc_tcid']);
                    if ($param['cc_list'][$i]['cc_tcid'])
                    {
                        $tcid_arr[] = intval($param['cc_list'][$i]['cc_tcid']);
                    }
                }
                else
                {
                    $err_pre = '第' . ($i + 1) . '个校区';
                    if ($param['cc_list'][$i]['cc_tcid'] == '')
                    {
                        $param['cc_list'][$i]['cc_tcid'] = NULL;
                    }
                    else 
                    {
                        if (!Validate::isInt($param['cc_list'][$i]['cc_tcid']))
                        {
                            $err = $err_pre . '没有选择具体的培训校区';
                            break;
                        }
                        $param['cc_list'][$i]['cc_tcid'] = 
                            intval($param['cc_list'][$i]['cc_tcid']);
                        if ($param['cc_list'][$i]['cc_tcid'])
                        {
                            $tcid_arr[] = intval($param['cc_list'][$i]['cc_tcid']);
                        }
                    }
                }
                if (!isset($param['cc_list'][$i]['cct_ctid_str']))
                {
                    $param['cc_list'][$i]['cct_ctid_str'] = '';
                }
                /*
                if ($param['cc_list'][$i]['cct_ctid_str'] == '')
                {
                    $err = $err_pre . '请添加授课教师';
                    break;
                }
                 */
                if (strlen($param['cc_list'][$i]['cct_ctid_str']) > 0)
                {
                    if (!Validate::isJoinedIntStr(
                        $param['cc_list'][$i]['cct_ctid_str']))
                    {
                        $err = $err_pre . '请添加正确的授课教师';
                        break;
                    }
                    $param['cc_list'][$i]['teacherid_list'] = explode(',', 
                        $param['cc_list'][$i]['cct_ctid_str']);
                }
                else
                {
                    $param['cc_list'][$i]['teacherid_list'] = array();
                }
                unset($param['cc_list'][$i]['cct_ctid_str']);

                if (!Validate::isInt($param['cc_list'][$i]['cc_ctfid']))
                {
                    $err = $err_pre . '请选择教师来源';
                    break;
                }
                if ($param['cc_list'][$i]['cc_classtime'] == '')
                {
                    $err = $err_pre . '请填写课程时间';
                    break;
                }
                if ($param['cc_list'][$i]['cc_startanytime'] == '1')
                {
                    $param['cc_list'][$i]['cc_startanytime'] = 1;
                }
                else
                {
                    $param['cc_list'][$i]['cc_startanytime'] = 0;
                    if ($param['cc_list'][$i]['cc_begindate'] == '')
                    {
                        $err = $err_pre . '请填写课程周期开课日期';
                        break;
                    }
                    if (!Validate::isDate($param['cc_list'][$i]['cc_begindate']))
                    {
                        $err = $err_pre . '请填写正确的课程周期开课日期';
                        break;
                    }
                    if ($param['cc_list'][$i]['cc_enddate'] == '')
                    {
                        $err = $err_pre . '请填写课程周期结束日期';
                        break;
                    }
                    if (!Validate::isDate($param['cc_list'][$i]['cc_enddate']))
                    {
                        $err = $err_pre . '请填写正确的课程周期结束日期';
                        break;
                    }
                    if (strcmp($param['cc_list'][$i]['cc_enddate'], 
                                $param['cc_list'][$i]['cc_begindate']) < 0)
                    {
                        $err = $err_pre . '课程结束日期应大于开始日期';
                        break;
                    }
                }
                if (!Validate::isInt($param['cc_list'][$i]['cc_hours']))
                {
                    $err = $err_pre . '请填写课时';
                    break;
                }
                if (!is_numeric($param['cc_list'][$i]['cc_price']))
                {
                    $err = $err_pre . '请填写课程收费';
                    break;
                }
                if ($param['cc_list'][$i]['cc_provid'] == '0')
                {
                    $err = $err_pre . '请选择上课地址所在省市区';
                    break;
                }
                if ($param['cc_list'][$i]['cc_addr'] == '')
                {
                    $err = $err_pre . '请填写上课地址';
                    break;
                }
            }
            if ($err)
            {
                $resp->alert($err);
                return $resp;
            }
            if (count($tcid_arr) > 0)
            {
                if (count($tcid_arr) != count(array_unique($tcid_arr)))
                {
                    $resp->alert('校区列表中有相同的机构校区,请检查');
                    return $resp;
                }
            }
        }
        
        try
        {
            if ($cors_id)
            {
                $db = Fn::db();

                $cc_id_arr = array();
                $rows = CourseModel::courseCampusList("cc_id", 
                    array('cc_corsid' => $cors_id));
                if ($rows)
                {
                    foreach ($rows as $row)
                    {
                        $cc_id_arr[] = intval($row['cc_id']);
                    }
                }

                $bOk = false;
                if (!$db->beginTransaction())
                {
                    throw new Exception('启动存储过程失败');
                }

                $set_cc_id_arr = array();
                try
                {
                    CourseModel::setCourse($param, false);
                    foreach ($param['cc_list'] as $row)
                    {
                        $row['cc_id'] = intval($row['cc_id']);
                        if ($row['cc_id'])
                        {
                            CourseModel::setCourseCampus($row, false);
                            $set_cc_id_arr[] = $row['cc_id'];
                        }
                        else
                        {
                            CourseModel::addCourseCampus($row, false);
                        }
                    }
                    $remove_cc_id_arr = array_diff($cc_id_arr, $set_cc_id_arr);
                    if ($remove_cc_id_arr)
                    {
                        CourseModel::removeCourseCampus(implode(',', $remove_cc_id_arr), false);
                    }
                    $bOk = $db->commit();
                    if (!$bOk)
                    {
                        $db->rollBack();
                    }
                }
                catch (Exception $e)
                {
                    $db->rollBack();
                    throw $e;
                }

                if ($bOk)
                {
                    admin_log('edit', 'course', "cors_id: $cors_id");
                    $resp->redirect(site_url('admin/course/corsinfo/' . $cors_id));
                }
                else
                {
                    $resp->alert('执行存储过程失败');
                }
            }
            else
            {
                // 新增
                $cors_id = CourseModel::addCourse($param);
                admin_log('add', 'course', "cors_id: $cors_id");
                $resp->redirect(site_url('admin/course/setcorsinfo/' . $cors_id));
            }
        }
        catch (Exception $e2)
        {
            $resp->alert($e2->getMessage());
        }
        return $resp;
    }

    /**
     * 新增课程表单页面(只有主表没有子表)
     */
    public function addcorsinfo()
    {
        Fn::ajax_call($this, 'setCORS');
        $data = array();
        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class ORDER BY sort_order
EOT;
        $data['classid_map'] = Fn::db()->fetchPairs($sql);
        $data['cm_list'] = CourseModel::courseModeList();
        $data['csnt_list'] = CourseModel::courseStuNumTypeList();
        $this->load->view('course/addcorsinfo', $data);
    }

    /**
     * 课程编辑中某个校区编辑表单(DIV层嵌入),供课程编辑页面嵌入和动态加载
     * @param   int     $cors_id    课程ID
     * @param   int     $k          顺序号(课程编辑中校区列表按顺序号从0增加)
     */
    public function setcorsinfo_campus($cors_id, $k)
    {
        $data = array();
        $data['k'] = $k;
        $data['ctf_list'] = CourseModel::courseTeachfromList();
        $data['cc_info'] = array();
        $data['cc_info'][$k] = array(
            'cc_id' => 0,
            'cc_corsid' => $cors_id,
            'cc_tcid' => '',
            'cc_ctfid' => '',
            'tc_name' => '新校区' . ($k + 1),
            'cc_classtime' => '',
            'cc_begindate' => '',
            'cc_enddate' => '',
            'cc_startanytime' => '0',
            'cc_hours' => '',
            'cc_price' => '',
            'cc_provid' => 0,
            'cc_cityid' => 0,
            'cc_areaid' => 0,
            'cc_addr' => '',
            'cc_ctcperson' => '',
            'cc_ctcphone' => '');
        $data['city_list'] = array();
        $data['area_list'] = array();
        $data['province_list'] = RegionModel::get_regions(1);
        $this->load->view('course/setcorsinfo_campus', $data);
    }

    /**
     * 编辑课程信息表单页面
     * @param   int     $cors_id    课程ID
     */
    public function setcorsinfo($cors_id)
    {
        Fn::ajax_call($this, 'setCORS');
        $data = array();
        
        $data['cors_region'] = CourseModel::courseRegion($cors_id);
        $data['cors_info'] = CourseModel::courseInfo(intval($cors_id));
        if (empty($data['cors_info']))
        {
            message('找不到对应记录', site_url('admin/course/corslist'));
        }
        $cors_id = $data['cors_info']['cors_id'];
        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');

        $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class ORDER BY sort_order
EOT;
        $data['classid_map'] = Fn::db()->fetchPairs($sql);

        $data['cm_list'] = CourseModel::courseModeList();
        $data['csnt_list'] = CourseModel::courseStuNumTypeList();

        $data['cors_gradeid'] = CourseModel::courseGradeIDPairs($cors_id);
        $data['cors_subjectid'] = CourseModel::courseSubjectIDPairs($cors_id);
        $data['cors_classid'] = CourseModel::courseClassIDPairs($cors_id);
        $data['cors_kid'] = CourseModel::courseKnowledgePairs($cors_id);
        $data['kid_all'] = false;
        $know_processes = array();
        if (!empty($data['cors_kid']))
        {
            foreach ($data['cors_kid'] as $item)
            {
                foreach ($item as $kid => $row)
                {
                    if ($kid > 0)
                    {
                        $know_processes[$kid] = array(
                            'kp' => $row['ck_knprocid'],
                            'name' => C('know_process/'.$row['ck_knprocid'])
                        );
                    }
                    else
                    {
                        $data['kid_all'] = true;
                    }
                }
            }
        }
        
        $data['know_processes'] = $know_processes;

        $data['ctf_list'] = CourseModel::courseTeachfromList();


        $data['cc_info'] = CourseModel::courseCampusList('*', 
            array('cc_corsid' => $cors_id));
        if (empty($data['cc_info']))
        {
            $data['cc_info'] = array();
            $data['cc_info'][0] = array(
                'cc_id' => 0,
                'cc_corsid' => $cors_id,
                'cc_tcid' => '',
                'cc_ctfid' => '',
                'tc_name' => '新校区1',
                'cc_classtime' => '',
                'cc_begindate' => '',
                'cc_enddate' => '',
                'cc_startanytime' => '0',
                'cc_hours' => '',
                'cc_price' => '',
                'cc_provid' => 0,
                'cc_cityid' => 0,
                'cc_areaid' => 0,
                'cc_addr' => '',
                'cc_ctcperson' => '',
                'cc_ctcphone' => '');
            $data['cteacher_list'] = array();
        }
        else
        {
            $cc_id_arr = array();
            $tc_ids = array();
            foreach ($data['cc_info'] as $row)
            {
                $cc_id_arr[] = $row['cc_id'];
                $tc_ids[] = $row['cc_tcid'];
            }
            $data['cteacher_list'] = CourseModel::courseCampusTeacherPairs(
                implode(',', $cc_id_arr));
            $data['cc_tcid'] = implode(',', $tc_ids);
        }
        
        $data['city_list'] = array();
        $data['area_list'] = array();
        foreach ($data['cc_info'] as &$cc_info)
        {
            if ($cc_info['cc_provid'] > 0 
                && !isset($data['city_list'][$cc_info['cc_provid']]))
            {
                $data['city_list'][$cc_info['cc_provid']] = 
                    RegionModel::get_regions($cc_info['cc_provid'], FALSE, 2);
            }
            if ($cc_info['cc_cityid'] > 0 
                && !isset($data['area_list'][$cc_info['cc_cityid']]))
            {
                $data['area_list'][$cc_info['cc_cityid']] = 
                    RegionModel::get_regions($cc_info['cc_cityid'], FALSE, 3);
            }
        }
        $data['province_list'] = RegionModel::get_regions(1);
        $this->load->view('course/setcorsinfo', $data);
    }

    /**
     * 查看课程信息表单页面
     * @param   int     $cors_id    课程id
     */
    public function corsinfo($cors_id)
    {
        $data = array();
        
        $data['cors_region'] = CourseModel::courseRegion($cors_id);
        $data['cors_info'] = CourseModel::courseInfo(intval($cors_id));
        if (empty($data['cors_info']))
        {
            message('找不到对应记录', site_url('admin/course/corslist'));
        }
        $cors_id = $data['cors_info']['cors_id'];
        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class ORDER BY sort_order
EOT;
        $data['classid_map'] = Fn::db()->fetchPairs($sql);

        $data['cors_gradeid'] = CourseModel::courseGradeIDPairs($cors_id);
        $data['cors_subjectid'] = CourseModel::courseSubjectIDPairs($cors_id);
        $data['cors_classid'] = CourseModel::courseClassIDPairs($cors_id);
        $data['cors_kid'] = CourseModel::courseKnowledgePairs($cors_id);
        $data['kid_all'] = false;
        $know_processes = array();
        if (!empty($data['cors_kid']))
        {
            foreach ($data['cors_kid'] as $item)
            {
                foreach ($item as $kid => $row)
                {
                    if ($kid > 0)
                    {
                        $know_processes[$kid] = array(
                            'kp' => $row['ck_knprocid'],
                            'name' => C('know_process/'.$row['ck_knprocid'])
                        );
                    }
                    else
                    {
                        $data['kid_all'] = true;
                    }
                }
            }
        }
        
        $data['know_processes'] = $know_processes;

        $data['cc_info'] = CourseModel::courseCampusList('*', 
            array('cc_corsid' => $cors_id));
        if (empty($data['cc_info']))
        {
            $data['cc_info'] = array();
            if ($data['cors_info']['cors_cmid'] == 1)
            {
                $data['cc_info'][0] = array(
                    'cc_id' => 0,
                    'cc_corsid' => $cors_id,
                    'cc_tcid' => '',
                    'cc_ctfid' => '',
                    'ctf_name' => '',
                    'tc_name' => '',
                    'cc_classtime' => '',
                    'cc_begindate' => '',
                    'cc_enddate' => '',
                    'cc_startanytime' => '0',
                    'cc_hours' => '',
                    'cc_price' => '',
                    'cc_provid' => 0,
                    'cc_provname' => '',
                    'cc_cityid' => 0,
                    'cc_cityname' => '',
                    'cc_areaid' => 0,
                    'cc_areaname' => '',
                    'cc_addr' => '',
                    'cc_ctcperson' => '',
                    'cc_ctcphone' => '');
            }
            $data['cteacher_list'] = array();
        }
        else
        {
            $cc_id_arr = array();
            foreach ($data['cc_info'] as $row)
            {
                $cc_id_arr[] = $row['cc_id'];
            }
            $data['cteacher_list'] = CourseModel::courseCampusTeacherPairs(
                implode(',', $cc_id_arr));
        }

        $this->load->view('course/corsinfo', $data);
    }
    
    //=======================================================================
    /**
     * 培训课程授课模式列表
     */
    public function cmlist()
    {
        Fn::ajax_call($this, 'removeCM', 'setCM');

        $data = array();
        $data['cm_list'] = CourseModel::courseModeList();
        $this->load->view('course/cmlist', $data);
    }

    /** 
     * 删除课程授课模式AJAX方法
     * @param   string  $cm_id_str  形如1,2,3样式的ID列表
     *
     */
    public function removeCMFunc($cm_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('course_removecm', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }

        try
        {
            CourseModel::removeCourseMode($cm_id_str);
            admin_log('delete', '', "授课模式 cm_id: $cm_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增授课模式对话框DIV层
     */
    public function addcminfo()
    {
        $this->setcminfo(0);
    }

    /**
     * 新增/编辑授课模式对话框DIV层
     * @param   int $cm_id  授课模式ID,若为0表示新增,否则表示编辑
     */
    public function setcminfo($cm_id)
    {
        $cm_id = intval($cm_id);
        $data = array();
        $data['cm_id'] = $cm_id;
        if ($cm_id)
        {
            $data['cm_info'] = 
                CourseModel::courseModeInfo($cm_id);
        }
        else
        {
            $data['cm_info'] = array('cm_id' => '', 'cm_name' => '');
        }
        $this->load->view('course/setcminfodlg', $data);
    }

    /**
     * 新增/编辑授课模式AJAX方法
     * @param   int     $cm_id  旧授课模式ID,若为0表新增,否则表编辑
     * @param   array   $param  map<string,variant>类型的新授课模式参数
     *                  int     cm_id   新授课模式ID
     *                  string  cm_name 新授课模式名称
     */
    public function setCMFunc($cm_id, $param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'cm_id', 'cm_name');

        if ($cm_id)
        {
            if (!$this->check_power_new('course_setcminfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }
        else
        {
            if (!$this->check_power_new('course_addcminfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }

        if (!Validate::isInt($param['cm_id']))
        {
            $resp->alert('培训课程授课模式ID必须为整数');
            return $resp;
        }
        if ($param['cm_name'] == '')
        {
            $resp->alert('培训课程授课模式名称不可为空');
            return $resp;
        }
        try
        {
            if ($cm_id)
            {
                CourseModel::setCourseMode($cm_id, $param);
                admin_log('edit', '', "授课模式 cm_id: " . $param['cm_id']);
            }
            else
            {
                $cm_id = CourseModel::addCourseMode($param);
                admin_log('add', '', "授课模式 cm_id: " . $param['cm_id']);
            }
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }


    //=======================================================================
    /**
     * 培训课程授课人数类别列表
     */
    public function csntlist()
    {
        Fn::ajax_call($this, 'removeCSNT', 'setCSNT');

        $data = array();
        $data['csnt_list'] = CourseModel::courseStuNumTypeList();
        $this->load->view('course/csntlist', $data);
    }

    /**
     * 删除课程授课人数类别AJAX方法
     * @param   string  $csnt_id_str    形如1,2,3样式的ID列表字符串
     */
    public function removeCSNTFunc($csnt_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('course_removecsnt', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            CourseModel::removeCourseStuNumType($csnt_id_str);
            admin_log('delete', '', "授课人数类别 csnt_id: $csnt_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增授课人数类别层对话框DIV
     */
    public function addcsntinfo()
    {
        $this->setcsntinfo(0);
    }

    /**
     * 新增/编辑授课人数类别层对话框DIV
     * @param   int     $csnt_id    若为0表示新增,否则表示编辑
     */
    public function setcsntinfo($csnt_id)
    {
        $csnt_id = intval($csnt_id);
        $data = array();
        $data['csnt_id'] = $csnt_id;
        if ($csnt_id)
        {
            $data['csnt_info'] = 
                CourseModel::courseStuNumTypeInfo($csnt_id);
        }
        else
        {
            $data['csnt_info'] = array('csnt_id' => '', 
                'csnt_name' => '', 'csnt_memo');
        }
        $this->load->view('course/setcsntinfodlg', $data);
    }

    /**
     * 新增/编辑授课人数类别AJAX方法
     * @param   int     $csnt_id    旧ID,若为0表新增,否则表编辑
     * @param   array   $param  新属性
     *                  int     csnt_id     新ID
     *                  string  csnt_name   新名称
     *                  string  csnt_memo   新备注
     */
    public function setCSNTFunc($csnt_id, $param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'csnt_id', 'csnt_name', 'csnt_memo');
        if ($csnt_id)
        {
            if (!$this->check_power_new('course_setcsntinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }
        else
        {
            if (!$this->check_power_new('course_addcsntinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }


        if (!Validate::isInt($param['csnt_id']))
        {
            $resp->alert('培训课程授课人数类别ID必须为整数');
            return $resp;
        }
        if ($param['csnt_name'] == '')
        {
            $resp->alert('培训课程授课人数类别名称不可为空');
            return $resp;
        }
        if (isset($param['csnt_memo']))
        {
            if ($param['csnt_memo'] == '')
            {
                $param['csnt_memo'] = NULL;
            }
        }
        try
        {
            if ($csnt_id)
            {
                CourseModel::setCourseStuNumType($csnt_id, $param);
                admin_log('edit', '', "授课人数类别 csnt_id: " . $param['csnt_id']);
            }
            else
            {
                CourseModel::addCourseStuNumType($param);
                admin_log('add', '', "授课人数类别 csnt_id: " . $param['csnt_id']);
            }
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }


    //=======================================================================
    /**
     * 培训课程教师来源列表
     */
    public function ctflist()
    {
        Fn::ajax_call($this, 'removeCTF', 'setCTF');

        $data = array();
        $data['ctf_list'] = CourseModel::courseTeachfromList();
        $this->load->view('course/ctflist', $data);
    }

    /**
     * 删除授课教师来源AJAX方法
     * @param   string  $ctf_id_str 形如1,3,4的ID列表
     */
    public function removeCTFFunc($ctf_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('course_removectf', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            CourseModel::removeCourseTeachfrom($ctf_id_str);
            admin_log('delete', '', "授课教师来源 ctf_id: $ctf_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增授课教师来源层对话框DIV
     */
    public function addctfinfo()
    {
        $this->setctfinfo(0);
    }

    /**
     * 新增/编辑授课教师来源层对话框DIV
     * @param   int     $ctf_id     若为0表新增,否则表编辑
     */
    public function setctfinfo($ctf_id)
    {
        $ctf_id = intval($ctf_id);
        $data = array();
        $data['ctf_id'] = $ctf_id;
        if ($ctf_id)
        {
            $data['ctf_info'] = 
                CourseModel::courseTeachfromInfo($ctf_id);
        }
        else
        {
            $data['ctf_info'] = array('ctf_id' => '', 'ctf_name' => '');
        }
        $this->load->view('course/setctfinfodlg', $data);
    }

    /**
     * 新增/编辑授课教师来源AJAX方法
     * @param   int     $ctf_id     旧ID,若为0表新增,否则表编辑
     * @param   array   $param      map<string,varaint>类型的新属性
     *                  int     ctf_id      新ID
     *                  string  ctf_name    新名称  
     */
    public function setCTFFunc($ctf_id, $param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'ctf_id', 'ctf_name');

        if ($ctf_id)
        {
            if (!$this->check_power_new('course_setctfinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }
        else
        {
            if (!$this->check_power_new('course_addctfinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }

        if (!Validate::isInt($param['ctf_id']))
        {
            $resp->alert('培训课程授课教师来源ID必须为整数');
            return $resp;
        }
        if ($param['ctf_name'] == '')
        {
            $resp->alert('培训课程授课教师来源名称不可为空');
            return $resp;
        }
        try
        {
            if ($ctf_id)
            {
                CourseModel::setCourseTeachfrom($ctf_id, $param);
                admin_log('edit', '', "授课教师来源 ctf_id: " . $param['ctf_id']);
            }
            else
            {
                CourseModel::addCourseTeachfrom($param);
                admin_log('add', '', "授课教师来源 ctf_id: " . $param['ctf_id']);
            }
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    //======================================================================
    /**
     * 导入课程记录(从excel文件中),
     * 注意: 目前$cors_id参数未启用
     *
     * @param   int     $cors_id = NULL   默认将课程全导入到该课程ID下
     */
    public function import_cors_excel($cors_id = NULL)
    {
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel', 
                'file/import_course_template.xls', 
                '培训课程导入模板.xls');
            exit();
        }

        $data = array();
        while (isset($_FILES['file']))
        {
            $param = $_POST;
            $err_map = array(
                UPLOAD_ERR_OK => '没有错误发生，文件上传成功',
                UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
                UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败');
            
            if ($_FILES['file']['error'] !== 0)
            {
                $data['error'] = $err_map[$_FILES['file']['error']];
                break;
            }
            if (strpos($_FILES['file']['type'], 'excel') === false)
            {
                $mime = mime_content_type($_FILES['file']['tmp_name']);
                if (!in_array($mime, array('application/vnd.ms-excel', 
                    'application/vnd.ms-office', 
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')))
                {
                    $data['error'] = "您上传的不是Excel文件($mime)";
                    break;
                }
            }
            // 开始处理excel

            $excel = PHPExcel_IOFactory::load($_FILES['file']['tmp_name']);
            $sheet = $excel->getSheet(0);
            $row_num = $sheet->getHighestRow();
            $col_num = $sheet->getHighestColumn();

            $title = array(
                '课程名称',
                '来源机构',
                '授课模式',
                '年级',
                '学科',
                '类型',
                '授课教师',
                '教师来源',
                '课程时间',
                '课程周期',
                '上课校区',
                '上课省份',
                '上课城市',
                '上课区县',
                '上课地址',
                '收费',
                '上课课时',
                '联系人',
                '联系电话',
                '课程简介',
                '网址');
            $col_num = base_convert($col_num, 36, 10);
            if ($col_num < count($title))
            {
                $data['error'] = 'Excel列数验证未通过';
                break;
            }
            $col_num = count($title);
            $col_char = array();
            for ($j = 0; $j < $col_num; $j++)
            {
                $col_char[$j] = strtoupper(base_convert(10 + $j, 10, 36));
                if ($title[$j]
                    !== trim($sheet->getCell($col_char[$j] . '1')->getValue()))
                {
                    $data['error'] = $col_char[$j] . '列标题不符';
                    break;
                }
            }
            if (isset($data['error']))
            {
                break;
            }

            $rows = array();
            for ($i = 2; $i <= $row_num; $i++)
            {
                $rows[$i - 2] = array();
                for ($j = 0; $j < $col_num; $j++)
                {
                    $rows[$i - 2][$j] = trim($sheet->getCell(
                        $col_char[$j] . $i)->getValue());
                }
                if ($rows[$i - 2][0] == '')
                {
                    unset($rows[$i - 2]);
                    break;
                }
            }
            unset($sheet);
            unset($excel);

            if (empty($rows))
            {
                $data['error'] = 'Excel文件工作表中没有任何要导入的记录';
                break;
            }

            $cm_map = array_flip(array_map("trim", 
                                        CourseModel::courseModePairs()));
            $grade_map = array_flip(C('grades'));
            $subject_map = array_flip(C('subject'));
            $ctf_map = array_flip(array_map("trim",
                                        CourseModel::courseTeachfromPairs()));
            $sql = <<<EOT
SELECT class_id, class_name FROM rd_question_class
EOT;
            $class_map = Fn::db()->fetchPairs($sql);
            if (!is_array($class_map))
            {
                $class_map = array();
            }
            else
            {
                $class_map = array_flip(array_map("trim", $class_map));
            }

            $sql = <<<EOT
SELECT * FROM rd_region WHERE region_id > 1 ORDER BY region_type
EOT;
            $region_list = Fn::db()->fetchAll($sql);
            // 以region_id为键以行记录为值,同时加个children,以保存直接子项
            $region_map = array();
            // 以名字加'_'连接起来为键,以region_id为值
            $regionname_map = array();
            foreach ($region_list as $v)
            {
                $region_map[$v['region_id']] = $v;
                $region_map[$v['region_id']]['children'] = array();
                if ($v['region_type'] == 1)
                {
                    $regionname_map[trim($v['region_name'])] = $v['region_id'];
                }
                else if ($v['region_type'] == 2)
                {
                    $region_map[$v['parent_id']]['children'][] = $v['region_id'];
                    $regionname_map[
                        trim($region_map[$v['parent_id']]['region_name'])
                        . '_' . 
                        trim($v['region_name'])] = $v['region_id'];
                }
                else if ($v['region_type'] == 3)
                {
                    $region_map[$v['parent_id']]['children'][] = $v['region_id'];
                    $regionname_map[
                        trim($region_map[$region_map[$v['parent_id']]['parent_id']]['region_name'])
                        . '_' . 
                        trim($region_map[$v['parent_id']]['region_name'])
                        . '_' .
                        trim($v['region_name'])] = $v['region_id'];
                }
            }
            unset($region_list);

            $row_num = count($rows);


            $cors_arr = array(); //cors_arr[cors_name] => cors_list[i]
            $cc_arr = array();   //cc_arr[ti_name][tc_name] => cc_list[i]
            $cors_list = array();
            $cc_list = array();
            foreach ($rows as $k => $row)
            {
                // 课程名称
                if ($row[0] == '')
                {
                    $data['error'] == $col_char[0] . ($k + 2)
                        . ' - "课程名称"不可为空';
                    break;
                }
                if (mb_strlen($row[0], 'UTF-8') > 100)
                {
                    $data['error'] = $col_char[0] . ($k + 2) 
                        . ' - "课程名称"内容太长了,不可超过100个字符';
                    break;
                }


                // 来源机构
                if ($row[1] == '')
                {
                    $data['error'] = $col_char[1] . ($k + 2) 
                        . ' - "来源机构"不可为空';
                    break;
                }
                if (mb_strlen($row[1], 'UTF-8') > 60)
                {
                    $data['error'] = $col_char[1] . ($k + 2) 
                        . ' - "来源机构"内容太长了,不可超过60个字符';
                    break;
                }

                // 授课模式
                if ($row[2] == '')
                {
                    $data['error'] = $col_char[2] . ($k + 2)
                        . ' - "授课模式"不能为空';
                    break;
                }
                if (!isset($cm_map[$row[2]]))
                {
                    $data['error'] = $col_char[2] . ($k + 2)
                        . ' - "授课模式"不正确';
                    break;
                }
                $row['cors_cmid'] = $cm_map[$row[2]];

                ////////////
                //   年级3　学科4　类型5 授课教师6 教师来源7 课程时间8
                //   课程周期9 上课校区10
                //////////////////
                
                // 年级
                if ($row[3] == '')
                {
                    $data['error'] = $col_char[3] . ($k + 2)
                        . ' - "年级"不能为空';
                    break;
                }
                $row['cors_gradeid'] = array();
                $arr = explode(',', $row[3]);
                foreach ($arr as $v)
                {
                    $v = trim($v);
                    if (isset($grade_map[$v]))
                    {
                        $row['cors_gradeid'][] = $grade_map[$v];
                    }
                }
                if (count($arr) != count($row['cors_gradeid']))
                {
                    $data['error'] = $col_char[3] . ($k + 2)
                        . ' - "年级"里有不正确的选项';
                    break;
                }
                if (empty($row['cors_gradeid']))
                {
                    $data['error'] = $col_char[3] . ($k + 2)
                        . ' - "年级"不能为空';
                    break;
                }
                $row['cors_gradeid'] = array_unique($row['cors_gradeid']);
                if ($row['cors_cmid'] != 1 && count($row['cors_gradeid']) > 1)
                {
                    $data['error'] = $col_char[3] . ($k + 2)
                        . ' - 一对一课程"年级"只能有一个';
                    break;
                }
                
                // 学科
                if ($row[4] == '')
                {
                    $data['error'] = $col_char[4] . ($k + 2)
                        . ' - "学科"不能为空';
                    break;
                }
                $row['cors_subjectid'] = array();
                $arr = explode(',', $row[4]);
                foreach ($arr as $v)
                {
                    $v = trim($v);
                    if (isset($subject_map[$v]))
                    {
                        $row['cors_subjectid'][] = $subject_map[$v];
                    }
                }
                if (count($arr) != count($row['cors_subjectid']))
                {
                    $data['error'] = $col_char[4] . ($k + 2)
                        . ' - "学科"里有不正确的选项空';
                    break;
                }

                if (empty($row['cors_subjectid']))
                {
                    $data['error'] = $col_char[4] . ($k + 2)
                        . ' - "学科"不能为空';
                    break;
                }

                $row['cors_subjectid'] = array_unique($row['cors_subjectid']);
                if ($row['cors_cmid'] != 1 
                    && count($row['cors_subjectid']) > 1)
                {
                    $data['error'] = $col_char[3] . ($k + 2)
                        . ' - 一对一课程"学科"只能有一个';
                    break;
                }
                
                // 类型
                if ($row[5] == '')
                {
                    if ($param['cors_classid_required'])
                    {
                        $data['error'] = $col_char[5] . ($k + 2)
                            . ' - "类型"不能为空';
                        break;
                    }
                    $row['cors_classid'] = array();
                }
                else
                {
                    $row['cors_classid'] = array();
                    $arr = explode(',', $row[5]);
                    foreach ($arr as $v)
                    {
                        $v = trim($v);
                        if (isset($class_map[$v]))
                        {
                            $row['cors_classid'][] = $class_map[$v];
                        }
                    }
                    if (count($arr) != count($row['cors_classid']))
                    {
                        $data['error'] = $col_char[5] . ($k + 2)
                            . ' - "类型"里有不正确的选项空';
                        break;
                    }
                    if (empty($row['cors_classid']))
                    {
                        if ($param['cors_classid_required'])
                        {
                            $data['error'] = $col_char[5] . ($k + 2)
                                . ' - "类型"不能为空';
                            break;
                        }
                    }
                    $row['cors_classid'] = array_unique($row['cors_classid']);
                }

                // TODO 需要验证
                $row['cc_teachers'] = $row[6];

                // 教师来源
                if ($row[7] == '')
                {
                    $data['error'] = $col_char[7] . ($k + 2)
                        . ' - "教师来源"不能为空';
                    break;
                }
                if (!isset($ctf_map[$row[7]]))
                {
                    $data['error'] = $col_char[7] . ($k + 2)
                        . ' - "教师来源"不正确';
                    break;
                }
                $row['cc_ctfid'] = $ctf_map[$row[7]];

                // 课程时间
                if ($row[8] == '')
                {
                    if ($param['cc_classtime_required'])
                    {
                        $data['error'] = $col_char[8] . ($k + 2)
                            . ' - "课程时间"不能为空';
                        break;
                    }
                }
                if (mb_strlen($row[8], 'UTF-8') > 255)
                {
                    $data['error'] = $col_char[8] . ($k + 2) 
                        . ' - "课程时间"内容太长了,不可超过255个字符';
                    break;
                }

                // 课程周期
                if ($row[9] == '')
                {
                    // 任意时间开课
                    $row['cc_startanytime'] = 1;
                    $row['cc_begindate'] = NULL;
                    $row['cc_enddate'] = NULL;
                }
                else
                {
                    $row['cc_startanytime'] = 0;
                    if (strlen($row[9]) < 21)
                    {
                        $data['error'] = $col_char[9] . ($k + 2)
                            . ' - "课程周期"格式不正确,应为"2015-02-04x2015-08-01",其中x可为一个或多个字符串';
                        break;
                    }
                    $d1 = substr($row[9], 0, 10);
                    $d2 = substr($row[9], -10, 10);
                    if (!Validate::isDate($d1) || !Validate::isDate($d2))
                    {
                        $data['error'] = $col_char[9] . ($k + 2)
                            . ' - "课程周期"格式不正确,应为"2015-02-04x2015-08-01",其中x可为一个或多个字符串';
                        break;
                    }
                    if ($d1 > $d2)
                    {
                        $data['error'] = $col_char[9] . ($k + 2)
                            . ' - "课程周期"开始日期不能大于结束日期';
                        break;
                    }
                    $row['cc_begindate'] = $d1;
                    $row['cc_enddate'] = $d2;
                }

                // 上课校区
                if ($row['cors_cmid'] == 1)
                {
                    // 一对一

                    // 上课校区
                    if ($row[10] == '')
                    {
                        $row['tc_name'] = NULL;
                    }
                    else 
                    {
                        if (mb_strlen($row[10], 'UTF-8') > 60)
                        {
                            $data['error'] = $col_char[10] . ($k + 2)
                                . ' - "上课校区"太长了';
                            break;
                        }
                        $row['tc_name'] = $row[10];
                    }
                }
                else
                {
                    // 上课校区
                    if ($row[10] == '')
                    {
                        $data['error'] = $col_char[10] . ($k + 2)
                            . ' - "上课校区"不可为空';
                        break;
                    }
                    if (mb_strlen($row[10], 'UTF-8') > 60)
                    {
                        $data['error'] = $col_char[10] . ($k + 2)
                            . ' - "上课校区"太长了';
                        break;
                    }
                    $row['tc_name'] = $row[10];
                }

                // 上课省份
                if ($row[11] == '')
                {
                    if ($param['cc_provid_required'])
                    {
                        $data['error'] = $col_char[11] . ($k + 2)
                            . ' - "上课省份"不可为空';
                        break;
                    }
                    else
                    {
                        $row['cc_provid'] = 0;
                    }
                }
                else
                {
                    if (!isset($regionname_map[$row[11]]))
                    {
                        $data['error'] = $col_char[11] . ($k + 2)
                            . ' - "上课省份"不存在';
                        break;
                    }
                    $row['cc_provid'] = $regionname_map[$row[11]];
                }
                $row['cc_cityid'] = 0;
                $row['cc_areaid'] = 0;
                if (!empty($region_map[$regionname_map[$row[11]]]['children']))
                {
                    // 验证市
                    if ($row[12] == '')
                    {
                        if ($param['cc_cityid_required'])
                        {
                            $data['error'] = $col_char[12] . ($k + 2)
                                . ' - "上课城市"不可为空';
                            break;
                        }
                        else
                        {
                            $row['cc_cityid'] = 0;
                        }
                    
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[11] . '_' . $row[12]]))
                        {
                            $data['error'] = $col_char[12] . ($k + 2)
                                . ' - "上课城市"不存在';
                            break;
                        }
                        $row['cc_cityid'] = $regionname_map[$row[11] . '_' . $row[12]];
                    }

                    // 验证区县
                    if ($row[13] == '')
                    {
                        if ($param['cc_areaid_required'])
                        {
                            $data['error'] = $col_char[13] . ($k + 2)
                                . ' - "上课区县"不可为空';
                            break;
                        }
                        else
                        {
                            $row['cc_areaid'] = 0;
                        }
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[11] . '_' . $row[12] . '_' . $row[13]]))
                        {
                            $data['error'] = $col_char[13] . ($k + 2)
                                . ' - "上课区县"不存在';
                            break;
                        }
                        $row['cc_areaid'] = $regionname_map[$row[11] . '_' . $row[12] . '_' . $row[13]];
                    }
                }

                // 上课地址
                if ($row[14] == '')
                {
                    if ($param['cc_addr_required'])
                    {
                        $data['error'] = $col_char[14] . ($k + 2)
                            . ' - "上课地址"不可为空';
                        break;
                    }
                }
                if (mb_strlen($row[14], 'UTF-8') > 255)
                {
                    $data['error'] = $col_char[14] . ($k + 2)
                        . ' - "上课地址"内容太长了';
                    break;
                }

                // 收费
                if ($row[15] == '')
                {
                    if ($param['cc_price_required'])
                    {
                        $data['error'] = $col_char[15] . ($k + 2)
                            . ' - "收费"不能为空';
                        break;
                    }
                    else
                    {
                        $row[15] = '0.00';
                    }
                }
                if (!is_numeric($row[15]) || $row[15] < 0)
                {
                    $data['error'] = $col_char[15] . ($k + 2)
                        . ' - "收费"必须为非负数';
                    break;
                }
                $row[15] = bcadd($row[15], '0.00', 2);


                // 上课课时
                if ($row[16] == '')
                {
                    if ($param['cc_hours_required'])
                    {
                        $data['error'] = $col_char[16] . ($k + 2)
                            . ' - "上课课时"不能为空';
                        break;
                    }
                    else
                    {
                        $row[16] = '0';
                    }
                }
                if (!Validate::isInt($row[16]) || $row[16] < 0)
                {
                    $data['error'] = $col_char[16] . ($k + 2)
                        . ' - "上课课时"必须为非负整数';
                    break;
                }


                // 联系人
                if ($row[17] == '')
                {
                    if ($param['cc_ctcperson_required'])
                    {
                        $data['error'] = $col_char[17] . ($k + 2)
                            . ' - "联系人"不可为空';
                        break;
                    }
                }
                if (mb_strlen($row[17], 'UTF-8') > 60)
                {
                    $data['error'] = $col_char[17] . ($k + 2)
                        . ' - "联系人"太长了';
                    break;
                }

                // 联系电话
                if ($row['18'] == '')
                {
                    if ($param['cc_ctcphone_required'])
                    {
                        $data['error'] = $col_char[18] . ($k + 2)
                            . ' - "联系电话"不能为空';
                        break;
                    }
                }
                if (mb_strlen($row[18], 'UTF-8') > 120)
                {
                    $data['error'] = $col_char[18] . ($k + 2)
                        . ' - "联系电话"太长了';
                    break;
                }

                // 课程简介
                if ($row[19] == '')
                {
                    if ($param['cors_memo_required'])
                    {
                        $data['error'] = $col_char[19] . ($k + 2)
                            . ' - "课程简介"不能为空';
                        break;
                    }
                }
                if (mb_strlen($row[19], 'UTF-8') > 65535)
                {
                    $data['error'] = $col_char[19] . ($k + 2)
                        . ' - "课程简介"太长了';
                    break;
                }

                // 网址
                if ($row[20] == '')
                {
                    if ($param['cors_url_required'])
                    {
                        $data['error'] = $col_char[20] . ($k + 2)
                            . ' - "网址"不能为空';
                        break;
                    }
                }
                if (mb_strlen($row[20], 'UTF-8') > 512)
                {
                    $data['error'] = $col_char[20] . ($k + 2)
                        . ' - "网址"内容太长了';
                    break;
                }

                $key = <<<EOT
{$row[0]}/{$row[1]}/{$row[2]}/{$row[3]}/{$row[4]}
EOT;
                if (!isset($cors_arr[$key]))
                {
                    $cors_arr[$key] = count($cors_list);
                    $cors_list[] = array(
                        'key' => $key,
                        'index' => $k + 2,
                        'cors_id' => 0,
                        'cors_name' => $row[0],
                        'ti_name' => $row[1],
                        'cors_cmid' => $row['cors_cmid'],
                        'cors_gradeid' => $row['cors_gradeid'],
                        'cors_subjectid' => $row['cors_subjectid'],
                        'cors_classid' => $row['cors_classid'],
                        'cors_memo' => $row[19],
                        'cors_url' => $row[20]);
                }
                if (!isset($cc_arr[$key]))
                {
                    $cc_arr[$key] = array();
                }
                $cc_arr[$key][] = count($cc_list);
                $cc_list[] = array(
                    'key' => $key,
                    'index' => $k + 2,
                    'tc_name' => $row['tc_name'],
                    'cc_teachers' => $row['cc_teachers'],
                    'cc_ctfid' => $row['cc_ctfid'],
                    'cc_classtime' => $row[8],
                    'cc_startanytime' => $row['cc_startanytime'],
                    'cc_begindate' => $row['cc_begindate'],
                    'cc_enddate' => $row['cc_enddate'],
                    'cc_provid' => $row['cc_provid'],
                    'cc_cityid' => $row['cc_cityid'],
                    'cc_areaid' => $row['cc_areaid'],
                    'cc_price' => $row[15],
                    'cc_hours' => $row[16],
                    'cc_addr' => $row[14],
                    'cc_ctcperson' => $row[17],
                    'cc_ctcphone' => $row[18]);
            }
            if (isset($data['error']))
            {
                break;
            }

            unset($region_map);
            unset($regionname_map);
            unset($cm_map);
            unset($grade_map);
            unset($subject_map);
            unset($ctf_map);
            unset($class_map);
            unset($rows);
            // 这里开始导入
            
            
            $db = Fn::db();

            // 所属机构
            $sql1 = <<<EOT
SELECT ti_id FROM t_training_institution WHERE ti_name = ?
EOT;
            $sql2 = <<<EOT
SELECT tc_tiid, tc_id, tc_name FROM t_training_campus WHERE tc_tiid IN 
EOT;
            $sql3 = <<<EOT
UPDATE t_training_institution SET ti_campusnum = ti_campusnum + 1 
WHERE ti_id = 
EOT;
            $sql4 = <<<EOT
SELECT ct_id FROM t_cteacher WHERE ct_name = ? AND ct_contact IS NULL
EOT;
            $ti_id_arr = array();
            foreach ($cors_list as $k => $row)
            {
                $ti_id = $db->fetchOne($sql1, array($row['ti_name']));
                if ($ti_id)
                {
                    $ti_id_arr[] = $ti_id;
                    $cors_list[$k]['cors_tiid'] = $ti_id;
                }
                else
                {
                    $data['error'] = $col_char[1] . $row['index']
                        . ' - "来源机构"不存在';
                    break;
                }
            }
            if (isset($data['error']))
            {
                break;
            }
            $ti_id_arr = array_unique($ti_id_arr);

            $tclist = $db->fetchAll($sql2 . '(' . implode(',', $ti_id_arr) . ')');
            $ti_tc_map = array();
            foreach ($tclist as $v)
            {
                if (!isset($ti_tc_map[$v['tc_tiid']]))
                {
                    $ti_tc_map[$v['tc_tiid']] = array();
                }
                $ti_tc_map[$v['tc_tiid']][trim($v['tc_name'])] = $v['tc_id'];
            }
            unset($tclist);

            foreach ($cc_list as $k => $row)
            {
                $ti_id = $cors_list[$cors_arr[$row['key']]]['cors_tiid'];
                $cc_list[$k]['ti_id'] = $ti_id;
                if (is_null($row['tc_name']))
                {
                    $cc_list[$k]['cc_tcid'] = NULL; 
                }
                else
                {
                    if (isset($ti_tc_map[$ti_id]))
                    {
                        if (isset($ti_tc_map[$ti_id][$row['tc_name']]))
                        {
                            $cc_list[$k]['cc_tcid'] = 
                                $ti_tc_map[$ti_id][$row['tc_name']];
                        }
                        else
                        {
                            // 不存在
                            if ($param['non_exist_tcname_action'] == '0')
                            {
                                $data['error'] = $col_char[10] . $row['index']
                                    . ' - "上课校区"不存在';
                                break;
                            }
                            $cc_list[$k]['cc_tcid'] = 0;
                        }
                    }
                    else
                    {
                        //  不存在
                        if ($param['non_exist_tcname_action'] == '0')
                        {
                            $data['error'] = $col_char[10] . $row['index']
                                . ' - "上课校区"不存在';
                            break;
                        }
                        $cc_list[$k]['cc_tcid'] = 0;
                    }
                }
            }
            if (isset($data['error']))
            {
                break;
            }
            /* 不需要验证一对一课程是否只能有一个校区了
            foreach ($cors_arr as $key => $index)
            {
                if ($cors_list[$index]['cors_cmid'] == 1)
                {
                    if (count($cc_arr[$key]) > 1)
                    {
                        $data['error'] = $col_char[10] 
                            . $cc_list[$cc_arr[$key][1]]['index']
                            . ' - 该行为一对一课程，只能有一条课程校区';
                        break;
                    }
                }
            }
            if (isset($data['error']))
            {
                break;
            }
             */

            try
            {
                $time = time();
                $adduid = Fn::sess()->userdata('admin_id');
                if (!$db->beginTransaction())
                {
                    throw new Exception('开始导入事务处理失败');
                }


                $cors_insert = 0;
                $tc_insert = 0;
                $cc_insert = 0;
                $ct_insert = 0;

                // 导入课程
                foreach ($cors_list as $k => $row)
                {
                    // insert
                    $row2 = array();
                    $row2['cors_cmid'] = $row['cors_cmid'];
                    $row2['cors_name'] = $row['cors_name'];
                    $row2['cors_flag'] = $time;
                    $row2['cors_tiid'] = $row['cors_tiid'];
                    if ($row['cors_cmid'] == 1)
                    {
                        $row2['cors_stunumtype'] = 1;
                    }
                    else
                    {
                        $row2['cors_stunumtype'] = 2;
                    }
                    $row2['cors_url'] = $row['cors_url'];
                    $row2['cors_memo'] = $row['cors_memo'];
                    $row2['cors_addtime'] = date('Y-m-d H:i:s', $time);
                    $row2['cors_adduid'] = $adduid;
                    $row2['cors_lastmodify'] = date('Y-m-d H:i:s', $time);

                    $db->insert('t_course', $row2);
                    $cors_list[$k]['cors_id'] = $cors_id = 
                        $db->lastInsertId('t_course', 'cors_id');
                    if (empty($row['cors_gradeid']))
                    {
                        $db->insert('t_course_gradeid', 
                            array('cg_corsid' => $cors_id, 
                            'cg_gradeid' => 0));
                    }
                    else
                    {
                        foreach ($row['cors_gradeid'] as $v)
                        {
                            $db->insert('t_course_gradeid',
                                array('cg_corsid' => $cors_id,
                                    'cg_gradeid' => $v));
                        }
                    }
                    if (empty($row['cors_subjectid']))
                    {
                        $db->insert('t_course_subjectid', 
                            array('cs_corsid' => $cors_id, 
                            'cs_subjectid' => 0));
                    }
                    else
                    {
                        foreach ($row['cors_subjectid'] as $v)
                        {
                            $db->insert('t_course_subjectid',
                                array('cs_corsid' => $cors_id,
                                    'cs_subjectid' => $v));
                        }
                    }
                    if (!empty($row['cors_classid']))
                    {
                        foreach ($row['cors_classid'] as $v)
                        {
                            $db->insert('t_course_classid',
                                array('cci_corsid' => $cors_id,
                                    'cci_classid' => $v));
                        }
                    }
                    if ($row['cors_cmid'] != 1)
                    {
                        $db->insert('t_course_knowledge',
                            array('ck_corsid' => $cors_id,
                            'ck_kid' => 0,
                            'ck_knprocid' => 0));
                    }
                    $cors_insert++;
                }

                // 导入校区
                foreach ($cc_list as $k => $row)
                {
                    $key = $row['key'];
                    $row['cc_corsid'] = $cors_id = 
                        $cors_list[$cors_arr[$key]]['cors_id'];
                    $ti_id = $row['ti_id'];

                    if (is_null($row['cc_tcid']))
                    {
                        // 不要加
                    }
                    else 
                    {
                        if ($row['cc_tcid'] == 0)
                        {
                            if (isset($ti_tc_map[$ti_id][$row['tc_name']]))
                            {
                                $row['cc_tcid'] = 
                                    $ti_tc_map[$ti_id][$row['tc_name']];
                            }
                        }
                        if ($row['cc_tcid'] == 0)
                        {
                            // 自动增加
                            $row2 = array();
                            $row2['tc_name'] = $row['tc_name'];
                            $row2['tc_tiid'] = $ti_id;
                            $row2['tc_provid'] = $row['cc_provid'];
                            $row2['tc_cityid'] = $row['cc_cityid'];
                            $row2['tc_areaid'] = $row['cc_areaid'];
                            $row2['tc_flag'] = $time;
                            $row2['tc_ctcaddr'] = $row['cc_addr'];
                            $row2['tc_ctcperson'] = $row['cc_ctcperson'];
                            $row2['tc_ctcphone'] = $row['cc_ctcphone'];
                            $row2['tc_environ'] = 3;
                            $row2['tc_addtime'] = date('Y-m-d H:i:s', $time);
                            $row2['tc_adduid'] = $adduid;
                            $db->insert('t_training_campus', $row2);
                            $ti_tc_map[$ti_id][$row['tc_name']] = 
                                $row['cc_tcid'] = $db->lastInsertId(
                                't_training_campus', 'tc_id');
                            $tc_insert++;
                            $db->exec($sql3 . $ti_id);
                        }
                    }

                    $cc_teachers = $row['cc_teachers'];
                    unset($row['cc_teachers']);
                    unset($row['key']);
                    unset($row['index']);
                    unset($row['tc_name']);
                    unset($row['ti_id']);

                    
                    $db->insert('t_course_campus', $row);
                    $cc_id = $db->lastInsertId('t_course_campus', 'cc_id');

                    $cc_insert++;
                    if ($cc_teachers == '')
                    {
                        continue;
                    }
                    $cc_teachers_arr = explode(',', $cc_teachers);
                    $cc_teachers_arr = array_unique($cc_teachers_arr);
                    foreach ($cc_teachers_arr as $ctname)
                    {
                        if ($ctname == '')
                        {
                            continue;
                        }
                        $ct_id = $db->fetchOne($sql4, array($ctname));
                        if (!$ct_id)
                        {
                            $db->insert('t_cteacher',
                                array('ct_name' => $ctname, 
                                'ct_flag' => $time));
                            $ct_id = $db->lastInsertId('t_cteacher', 'ct_id');
                            $ct_insert++;
                            $row3 = $cors_list[$cors_arr[$key]];
                            if (empty($row3['cors_gradeid']))
                            {
                                $db->insert('t_cteacher_gradeid', 
                                    array('ctg_ctid' => $ct_id, 
                                    'ctg_gradeid' => 0));
                            }
                            else
                            {
                                foreach ($row3['cors_gradeid'] as $v)
                                {
                                    $db->insert('t_cteacher_gradeid',
                                        array('ctg_ctid' => $ct_id,
                                            'ctg_gradeid' => $v));
                                }
                            }
                            if (empty($row3['cors_subjectid']))
                            {
                                $db->insert('t_cteacher_subjectid', 
                                    array('cts_ctid' => $ct_id, 
                                    'cts_subjectid' => 0));
                            }
                            else
                            {
                                foreach ($row3['cors_subjectid'] as $v)
                                {
                                    $db->insert('t_cteacher_subjectid',
                                        array('cts_ctid' => $ct_id,
                                            'cts_subjectid' => $v));
                                }
                            }
                        }
                        $db->insert('t_course_campus_teacher',
                            array('cct_ccid' => $cc_id,
                            'cct_ctid' => $ct_id));
                    }
                }

                if ($db->commit())
                {
                    $data['success'] = <<<EOT
导入Excel文件({$_FILES['file']['name']})成功,共插入{$cors_insert}条课程记录, 插入{$tc_insert}条机构校区记录, 插入{$cc_insert}条课程校区记录, 插入{$ct_insert}条教师记录
EOT;
                    admin_log('add', 'course', $data['success']);
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

        if (!isset($_FILES['file']))
        {
            $param = array(
                'cors_classid_required' => 1,   // 类型必填
                'cc_classtime_required' => 1,   // 课程时间必填
                'cc_provid_required' => 1,      // 上课省份必填
                'cc_cityid_required' => 2,      // 上课城市必填
                'cc_areaid_required' => 3,      // 上课区县必填
                'cc_addr_required' => 1,        // 上课地址必填
                'cc_price_required' => 1,       // 收费必填
                'cc_hours_required' => 1,       // 上课课时必填
                'cc_ctcperson_required' => 1,   // 联系人必填
                'cc_ctcphone_required' => 1,    // 联系电话必填
                'cors_memo_required' => 1,      // 课程简介必填
                'cors_url_required' => 1,       // 网址必填
                'non_exist_tcname_action' => 0  // 不存在的校区报错停止
            );
        }
        $data['param'] = $param;
        $this->load->view('course/import_cors_excel', $data);
    }
}
