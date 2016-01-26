<?php if ( ! defined('BASEPATH')) exit();
class Student extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_once (APPPATH.'config/app/admin/recycle.php');

        if ($_FILES)
        {
            $config['upload_path'] = _UPLOAD_ROOT_PATH_.'uploads/student/'.date('Ymd').'/';
            $config['allowed_types'] = 'gif|jpg|jpeg|png';
            $config['max_size'] = '1024';
            $config['max_width'] = '2000';
            $config['max_height'] = '2000';
            $config['encrypt_name'] = TRUE;
            $this->load->library('upload', $config);
        }
    }

    /*
     * 批量删除学生
    */
    public function del_batch()
    {
        $this->db->delete('student', array('source_from'=>2));
        message('删除完毕','/admin/student/index');
    }

    /*
     * 批量添加学生
     */
    public function test()
    {
        $uid=intval($this->input->get('uid'));

        if($uid>0)
        {
            $sql="SELECT COUNT(*) AS count FROM  rd_student WHERE uid='$uid' ";
            $res = $this->db->query($sql)->row_array();
            if($res['count']>0)
            {

               $sql="call myProc_student($uid)";
               if($this->db->query($sql))
               {
                 message('生成完毕','/admin/student/index');
               }
            }
            else
            {
                message('用户编号非法');
            }
        }
        else
        {
            message('用户编号非法');
        }
    }


    /**
     * @description 学生管理列表
     * @author
     * @final
     * @param int $mode 是否回收站
     * @param int $province 省
     * @param int $city 市
     * @param int $area 区/县
     * @param int $school_id 学校
     * @param int $grade_id 年级
     * @param int $keyword 关键字
     */


    public function index($mode = '')
    {
    	if ( ! $this->check_power('student_list,student_manage')) return;

        // 查询条件
        $where = array();
        $param = array();
        $search = array();
        $query = array();
        $data = array();
        $mode = $mode=='trash' ? 'trash' : '';
        if ($mode == 'trash')
        {
            if ( ! $this->check_power('student_list, student_trash')) return;

            $query['is_delete'] = 1;
        }
        else
        {
            if ( ! $this->check_power('student_list, student_manage')) return;
            $query['is_delete'] = 0;
        }

        $grades = C('grades');

        if ($search['province'] = intval($this->input->get('province')))
        {

            $param[] = "province={$search['province']}";
            $query['province'] = intval($search['province']);
        }
        if ($search['city'] = intval($this->input->get('city')))
        {

            $param[] = "city={$search['city']}";
            $query['city'] = intval($search['city']);
        }
        if ($search['area'] = intval($this->input->get('area')))
        {

            $param[] = "area={$search['area']}";
            $query['area'] = intval($search['area']);
        }
        if ($search['school_id'] = intval($this->input->get('school_id')))
        {

            $param[] = "school_id={$search['school_id']}";
            $query['school_id'] = intval($search['school_id']);
        }
        if ($search['grade_id'] = intval($this->input->get('grade_id')))
        {

            $param[] = "grade_id={$search['grade_id']}";
            $query['grade_id'] = intval($search['grade_id']);
        }

        $search['from'] = intval($this->input->get('from'));
        if ($search['from'] > 0)
        {

            $param[] = "from={$search['from']}";
            $query['source_from'] = intval($search['from']);
        }

        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);

            $param[] = "keyword=".urlencode($search['keyword']);
            $query['keyword'] = "CONCAT(last_name,first_name,idcard) LIKE '%".$escape_keyword."%'";
        }

        if ($search['exam_ticket'] = trim($this->input->get('exam_ticket')))
        {

            $param[] = "exam_ticket={$search['exam_ticket']}";
            $query['exam_ticket'] = $search['exam_ticket'];
        }

        if ($search['email'] = trim($this->input->get('email')))
        {

            $param[] = "email={$search['email']}";
            $query['email'] = $search['email'];
        }

        if ($search['mobile'] = trim($this->input->get('mobile')))
        {

            $param[] = "mobile={$search['mobile']}";
            $query['mobile'] = $search['mobile'];
        }

        if ($uid = intval($this->input->get('uid')))
        {
            $search['uid'] = $uid;
            $param = array("uid=$uid");
            $query = array();
            $query['uid'] = $search['uid'];
        }




        $res = CommonModel::get_list($query,'rd_student','count(*) AS count');



        /*
         * 统计所有学生数量
         */


       $total = $res[0]['count'];

               /*
         *分页读取数据列表，并处理相关数据
        */
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $res = CommonModel::get_list($query,'v_student','*', $page, $size);


            foreach ($res as $row)
            {
                $row['grade'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '未选择';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

                //获取回收站信息
                if ($mode == 'trash')
                {
                	$recycle = RecycleModel::get_recycle_list(array('type' => RECYCLE_STUDENT, 'obj_id' => $row['uid']), null, null, 'ctime asc');
                	$row['recycle'] = $recycle;
                }
                else
                {
                	$row['recycle'] = array();
                }

                $list[] = $row;
            }
        }
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/student/index/'.$mode) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        // 使用Yaf样式的RegionModel代替
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($search['province'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($search['city'], FALSE, 3);
        $data['grades'] = $grades;
        $data['from'] = C('student_source');
        $data['search'] = $search;
        $data['schools'] = array();
        $data['mode'] = $mode;
        $data['priv_delete'] = $this->check_power('student_trash', FALSE);
        $data['priv_manage'] = $this->check_power('student_manage', FALSE);

        // 模版
        $this->load->view('student/index', $data);
    }





    public function account($mode = '')
    {

        if ( ! $this->check_power('student_list,student_manage')) return;

        // 查询条件
        $where = array();
        $param = array();
        $search = array();
        $query = array();
        $data = array();
        $mode = $mode=='trash' ? 'trash' : '';
        if ($mode == 'trash')
        {
            if ( ! $this->check_power('student_list, student_trash')) return;

            $query['is_delete'] = 1;
        }
        else
        {
            if ( ! $this->check_power('student_list, student_manage')) return;
            $query['is_delete'] = 0;
        }

        $grades = C('grades');

        if ($search['province'] = intval($this->input->get('province')))
        {

            $param[] = "province={$search['province']}";
            $query['province'] = intval($search['province']);
        }
        if ($search['city'] = intval($this->input->get('city')))
            {

            $param[] = "city={$search['city']}";
            $query['city'] = intval($search['city']);
            }
            if ($search['area'] = intval($this->input->get('area')))
            {

                     $param[] = "area={$search['area']}";
                     $query['area'] = intval($search['area']);
            }
            if ($search['school_id'] = intval($this->input->get('school_id')))
            {

                    $param[] = "school_id={$search['school_id']}";
                    $query['school_id'] = intval($search['school_id']);
                    }
                    if ($search['grade_id'] = intval($this->input->get('grade_id')))
                    {

                        $param[] = "grade_id={$search['grade_id']}";
                        $query['grade_id'] = intval($search['grade_id']);
    }

    $search['from'] = intval($this->input->get('from'));
    if ($search['from'] > 0)
    {

    $param[] = "from={$search['from']}";
        $query['source_from'] = intval($search['from']);
    }

        if ($search['keyword'] = trim($this->input->get('keyword')))
            {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);

            $param[] = "keyword=".urlencode($search['keyword']);
            $query['keyword'] = "CONCAT(s.last_name,s.first_name,s.idcard) LIKE '%".$escape_keyword."%'";
            }

        if ($search['exam_ticket'] = trim($this->input->get('exam_ticket')))
                    {

                    $param[] = "exam_ticket={$search['exam_ticket']}";
                    $query['exam_ticket'] = $search['exam_ticket'];
                    }

                    if ($search['email'] = trim($this->input->get('email')))
                    {

                    $param[] = "email={$search['email']}";
                    $query['email'] = $search['email'];
                    }

                    if ($search['mobile'] = trim($this->input->get('mobile')))
                    {

                    $param[] = "mobile={$search['mobile']}";
                    $query['mobile'] = $search['mobile'];
                    }

                    if ($uid = intval($this->input->get('uid')))
                    {
                    $search['uid'] = $uid;
                    $param = array("uid=$uid");
                    $query = array();
                    $query['uid'] = $search['uid'];
                    }



                    $res = CommonModel::get_list($query,'rd_student','count(*) AS count');


        /*
                * 统计所有学生数量
                */


                $total = $res[0]['count'];

                /*
                *分页读取数据列表，并处理相关数据
                 */
                $size   = 15;
                $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
                $offset = ($page - 1) * $size;
                $list = array();
                if ($total)
                    {
                    $res = CommonModel::get_list($query,'v_student','*', $page, $size);


                    foreach ($res as $row)
                    {
                        $row['grade'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '未选择';
                        $row['addtime'] = date('Y-m-d H:i', $row['addtime']);

                        //获取回收站信息
                        if ($mode == 'trash')
                        {
                            $recycle = RecycleModel::get_recycle_list(array('type' => RECYCLE_STUDENT, 'obj_id' => $row['uid']), null, null, 'ctime asc');
                            $row['recycle'] = $recycle;
                        }
                        else
                        {
                            $row['recycle'] = array();
                        }

                        $list[] = $row;
                    }
                }
                $data['list'] = $list;

                // 分页
                $purl = site_url('admin/student/account/') . ($param ? '?'.implode('&',$param) : '');
                $data['pagination'] = multipage($total, $size, $page, $purl);

                // 使用Yaf样式RegionModel代替
                $data['province_list'] = RegionModel::get_regions(1);
                $data['city_list'] = RegionModel::get_regions($search['province'], FALSE, 2);
                $data['area_list'] = RegionModel::get_regions($search['city'], FALSE, 3);
                $data['grades'] = $grades;
                $data['from'] = C('student_source');
                $data['search'] = $search;
                $data['schools'] = array();
                $data['mode'] = $mode;
                $data['priv_delete'] = $this->check_power('student_trash', FALSE);
                $data['priv_manage'] = $this->check_power('student_manage', FALSE);

                // 模版
                $this->load->view('student/account', $data);

    }







    /**
     * @description 添加学生信息表单页面
     * @author
     * @final
     */
    public function add_batch()
    {
        $this->load->view('student/edit_batch');
    }

    /**
     * @description 添加学生信息表单页面
     * @author
     * @final
     */
    public function add()
    {
        if ( ! $this->check_power('student_manage')) return;

        Fn::ajax_call($this, 'baseFetchTIList', 'baseFetchCORSList', 'baseFetchCTeacherList');

        $student = array(
            'first_name' => '',
            'last_name'  => '',
            'sex'        => 1,
            'birthday'   => '',
            'idcard'     => '',
            'grade_id'   => '',
            'school_id'  => '',
            'school_name'  => '',
            'address'    => '',
            'zipcode'    => '',
            'mobile'     => '',
            'picture'    => '',
            'sbs_stunumtype' => array(),
            'sbclassid_classid' => array(),
            'no_tiid' => 1,
        );
        $data['act'] = 'add';
        $data['student'] = $student;
        $data['score_ranks'] = array();
        $data['school_list'] = array();
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
        /* 初始化公共数据 */
        $data['grades']         = C('grades');
        $data['grade_periods']  = C('grade_periods');
        $data['subject_list']   = C('subject');
        $data['awards_types']   = C('awards_type');
        $data['awards_levels']  = C('awards_level');
        $data['family_bg_list'] = C('family_bg');
        $data['subjects']     = C('subject');
        $data['specs']     = $specs;
        $data['ranks']     = array('1'=>'A','2'=>'B','3'=>'C','4'=>'E');

 
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = array();//RegionModel::get_regions($student['sb_addr_provid'], FALSE, 2);
        $data['area_list'] = array();//RegionModel::get_regions($student['sb_addr_cityid'], FALSE, 3);

        // 班级类型
        $data['stunumtype_list'] = CourseModel::courseStuNumTypeList();
        // 考试类型
        $data['class_list'] = ClassModel::get_class_list($student['grade_id']);
        // 培训机构类型
        $data['tit_list'] = TrainingInstitutionModel::trainingInstitutionTypeList();
        // 课程授课模式
        $data['cm_list'] = CourseModel::courseModeList();

        // 模版
        $this->load->view('student/edit', $data);
    }


    public function baseFetchTIListFunc($param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'uid', 'ti_typeid', 'ti_provid', 'ti_cityid',
            'ti_areaid', 'ti_name');

        $uid = $param['uid'];
        unset($param['uid']);
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
        $param = Func::param_copy($param, 'grade_id', 'cors_tiid', 'cors_name', 'cors_cmid');

        $grade_id = $param['grade_id'];
        unset($param['grade_id']);

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
        $param = Func::param_copy($param, 'grade_id', 'cteacher_name', 'cors_id');

        $grade_id = $param['grade_id'];
        unset($param['grade_id']);
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

    /**
     * @description 编辑学生信息表单页面
     * @author
     * @final
     * @param int $uid 学生id
     */
    public function edit($uid)
    {
        if ( ! $this->check_power('student_manage')) return;


        Fn::ajax_call($this, 'baseFetchTIList', 'baseFetchCORSList', 'baseFetchCTeacherList');

        /*
         * 基本信息
         */
        $uid = intval($uid);
        $student = StudentModel::get_student($uid);
        $school_id =array();
        $volunteer =array();
        
        if (empty($student))
        {
            message('信息不存在');
            return;
        }

        $school = SchoolModel::schoolInfo($student['school_id'], 'school_id, school_name');
        $student['school_name'] = $school['school_name'];

        $student['birthday'] = date('Y-m-d', $student['birthday']);
        
        if ($uid)
        {
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


        $data['student'] = $student;
        $data['act']     = 'edit';

        /*
         * 学校列表
         * 根据当前学生的年级获取拥有该年级的所有学校
         */
        $grade_period = get_grade_period($student['grade_id']);
        $query = $this->db->select('school_id,school_name')->like('grade_period', $grade_period)->get('school');
        $data['school_list'] = $query->result_array();
        $data['school_list'] || $data['school_list'] = array();

       	/*
         * 成绩排名
         */
        $sql = <<<EOT
SELECT * FROM rd_student_ranking WHERE uid = ? ORDER BY grade_id ASC
EOT;
        $data['score_ranks'] = Fn::db()->fetchAll($sql, $uid);

        /*
         * 竞赛成绩
         */
        $sql = <<<EOT
SELECT * FROM rd_student_awards WHERE uid = ?
EOT;
        $rows = Fn::db()->fetchAll($sql, $uid);
        $awards_list = array();
        foreach ($rows as $row)
        {
            $awards_list[$row['typeid']][] = $row;
        }
        $data['awards_list'] = $awards_list;

        /*
         * 社会实践
         */
        $sql = <<<EOT
SELECT * FROM rd_student_practice WHERE uid = ?
EOT;
        $data['practice'] = Fn::db()->fetchRow($sql, $uid);

        /*
         * 学生意愿
         */
        $sql = <<<EOT
SELECT * FROM rd_student_wish WHERE uid = ?
EOT;
        $data['student_wish'] = Fn::db()->fetchRow($sql, $uid);

        /*
         * 家长意愿
         */
        $sql = <<<EOT
SELECT * FROM rd_student_parent_wish WHERE uid = ?
EOT;
        $data['parent_wish'] = Fn::db()->fetchRow($sql, $uid);
        if (empty($data['parent_wish']))
        {
            // 初始化，防止模版notice错误
            $data['parent_wish'] = array(
                'family_bg' => '',
                'upmethod'  => '',
                'wish'      => '',
            );
        }
        $school_id_arr=array(); 
       $swv = Fn::db()->fetchOne("SELECT volunteer FROM rd_student_wish WHERE uid = $uid");
       $school_id_arr=json_decode($swv);
       if ($swv)
       {
          foreach ( $school_id_arr as $k => $v)
          {
              $school_id[$k]=$v;
              if ($v!=0)
              {
                  $volunteer[$k] = Fn::db()->fetchOne("SELECT school_name FROM rd_school WHERE school_id = $v");
              }
          }
       } 
        //选考学考
        $sql = <<<EOT
SELECT * FROM rd_xuekao_xuankao WHERE uid = ?
EOT;
        $data['xuekao_xuankao'] = Fn::db()->fetchRow($sql, $uid);
        // 公共数据
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
        $data['grades']         = C('grades');
        $data['grade_periods']  = C('grade_periods');
        $data['subject_list']   = C('subject');
        $data['awards_types']   = C('awards_type');
        $data['awards_levels']  = C('awards_level');
        $data['family_bg_list'] = C('family_bg');
        $data['subjects']     = C('subject');
        $data['specs']     = $specs;
        $data['ranks']     = array('1'=>'A','2'=>'B','3'=>'C','4'=>'E');
        $data['school_id']     = $school_id;
        $data['volunteer']     = $volunteer; 
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
        $this->load->view('student/edit', $data);
    }

    /**
     * @description 编辑学生信息保存数据库
     * @author
     * @final
     * @param $act 编辑类型[新增/更新]
     * @param int $uid 学生id
     */
    public function update()
    {
        if ( ! $this->check_power('student_manage')) return;

        $tab = $this->input->post('tab_name');
        $act = $this->input->post('act')=='add' ? 'add' : 'edit';

        if ($act == 'add')
        {
            $student['password']   = trim($this->input->post('password'));
            $student['source_from'] = 1; //后台添加
            $tab = '';
        }
        else
        {
            $uid = intval($this->input->post('uid'));
            $old_student = StudentModel::get_student($uid);
            $student['uid'] = $uid;

            if (empty($old_student))
            {
                message('学生信息不存在！');
                return;
            }
        }

        if ($old_student['email'] != trim($this->input->post('email')))
        {
        	$student['email_validate'] = 0;
        }

	$student['email']      = trim($this->input->post('email'));
        $student['first_name'] = trim($this->input->post('first_name'));
        $student['last_name']  = trim($this->input->post('last_name'));
        $student['birthday']  = strtotime($this->input->post('birthday'));
        $student['idcard']    = trim($this->input->post('idcard'));
        $student['sex']       = intval($this->input->post('sex'))==1 ? 1 : 2;
        //$student['province']  = intval($this->input->post('province'));
        //$student['city']      = intval($this->input->post('city'));
        //$student['area']      = intval($this->input->post('area'));
        $student['grade_id']  = intval($this->input->post('grade_id'));
        $student['school_id'] = intval($this->input->post('school_id_0'));
        $student['address']   = trim($this->input->post('address'));
        $student['zipcode']   = trim($this->input->post('zipcode'));
        $student['mobile']    = trim($this->input->post('mobile'));
        $school_id_arr = $this->input->post('school_id');
        $volunteer_arr = $this->input->post('volunteer');
        if (empty($volunteer_arr[a]))
        {
            $school_id_arr[a]='';
        }

        if ($tab == 'awards' || $tab == '')
        {
            if ((intval($school_id_arr[a])==0)&&($student['grade_id']==6)&&($act=='edit'))
            {
                $school_id_arr[a]=-1;
                if ($act == 'edit')
                {
                    message('第一志愿不能为空', 'admin/student/edit/' . $uid);
                }
                else
                {
                    message('第一志愿不能为空');
                }
            }
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


        $extends = array();
        $msg = $this->_check_student($student, $extends, $act, $tab);

        if ($msg)
        {
            $tmp_msg = array();

            foreach ($msg as $k => $item) {
                    $k = $k + 1;
                    $tmp_msg[] = "{$k}、{$item}";
            }
            if ($act == 'edit')
            {
                message(implode("\n", $tmp_msg), 'admin/student/edit/' . $uid);
            }
            else
            {
                message(implode("\n", $tmp_msg));
            }
        }

        /*
         * 补充学生的所在区域
         * 根据学生所选的学校更新学生所在地区
         */
        $school = SchoolModel::schoolInfo($student['school_id'], 'province,city,area');
        $student['province'] = isset($school['province']) ? $school['province'] : 0;
        $student['city'] = isset($school['city']) ? $school['city'] : 0;
        $student['area'] = isset($school['area']) ? $school['area'] : 0;

        if ($act == 'add')
        {
         
            $result = StudentModel::add($student, $extends);
            
        }
        else
        {
            $result = StudentModel::update($student, $extends, $tab);
        }
 
        if ($tab == 'awards' || $tab == 'wish' || $tab == '')
        {
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
       
            if ($act=='add')
            {
            }
            else
            {
                 $db=Fn::db();
                 $swv = $db->fetchRow("SELECT volunteer FROM rd_student_wish WHERE uid = $uid");
                 if ($swv)
                 {
                    $db->update('rd_student_wish', $student_wish,"uid=$uid"); 
                 }
                else
                {
                    $db->insert('rd_student_wish', $student_wish);
                }
            }
        }
        
        if ($result['success'] == TRUE)
        {
            if ($act == 'add')
            {
                $msg = '学生信息添加成功';
                admin_log('add', 'student', $result['uid']);
                $uid = $result['uid'];
            }
            else
            {
                $msg = '学生信息修改成功';
                admin_log('edit', 'student', $uid);
                // 如果上传新图片，则删除旧图片文件
                if ( ! empty($student['picture']) && $old_student['picture'] && is_file(_UPLOAD_ROOT_PATH_.$old_student['picture']))
                {
                    @unlink(_UPLOAD_ROOT_PATH_.$old_studnet['picture']);
                }
            }

            if ($tab == 'base' || $tab == '')
            {
                //=========================
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
                //==========================

                $db = Fn::db();
                $bOk = false;
                try
                {
                    if ($db->beginTransaction())
                    {
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
                            message($msg . '   ' . $err, 'admin/student/edit/' . $uid, 'success');
                        }
                    }
                    if (!$bOk)
                    {
                        message($msg . '   保存学习概况信息事务处理失败', 'admin/student/edit/' . $uid, 'success');
                    }
                    else
                    {
                        message($msg . '   保存学习概况信息成功', 'admin/student/edit/' . $uid, 'success');
                    }
                }
                catch (Exception $e)
                {
                    message($msg . '   ' . $e->getMessage(), 'admin/student/edit/' . $uid, 'success');
                }
            }
            message($msg, 'admin/student/edit/' . $uid);
        }
        else
        {
            message($result['msg'], 'admin/student/edit/' . $uid);
        }
    }

    /**
     * @description 预览学生信息
     * @author
     * @final
     * @param int $uid 学生id
     */
    public function preview($uid = 0)
    {
    	if ( ! $this->check_power('student_list,student_manage')) return;

        $uid = intval($uid);

        /*
         * 基本信息
         */
        $uid && $student = StudentModel::get_student($uid);
        if (! $student)
        {
            message('学生信息不存在。');
            return;
        }

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

        /*
         * 成绩排名
         */
        $sql = <<<EOT
SELECT * FROM rd_student_ranking WHERE uid = ? ORDER BY grade_id ASC
EOT;
        $score_ranks = $db->fetchAll($sql, $uid);

        /*
         * 竞赛成绩
         */
        $sql = <<<EOT
SELECT * FROM rd_student_awards WHERE uid = ?
EOT;
        $rows = $db->fetchAll($sql, $uid);
        $awards_list = array();
        foreach ($rows as $row)
        {
            $awards_list[$row['typeid']][] = $row;
        }


        /*
         * 社会实践
         */
        $sql = <<<EOT
SELECT * FROM rd_student_practice WHERE uid = ?
EOT;
        $practice = $db->fetchRow($sql, $uid);

        /*
         * 学生意愿
         */
        $sql = <<<EOT
SELECT * FROM rd_student_wish WHERE uid = ?
EOT;
        $student_wish = $db->fetchRow($sql, $uid);

        /*
         * 家长意愿
         */
        $sql = <<<EOT
SELECT * FROM rd_student_parent_wish WHERE uid = ?
EOT;
        $parent_wish = $db->fetchRow($sql, $uid);

        // 学考选考
        $sql = <<<EOT
SELECT * FROM rd_xuekao_xuankao WHERE uid = ?
EOT;
        $xuekao_xuankao = $db->fetchRow($sql, $uid);

        /*
         * 处理完善学生信息
         */
        $grades = C('grades');
        $student['birthday'] = date('Y-m-d', $student['birthday']);
        $row1 = SchoolModel::schoolInfo($student['school_id'],'school_name');
        $student['school_name'] = $row1['school_name'];
        $student['grade_name'] = $grades[$student['grade_id']];
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
       $school_id_arr=array(); 
       $swv = Fn::db()->fetchOne("SELECT volunteer FROM rd_student_wish WHERE uid = $uid");
       $school_id_arr=json_decode($swv);
       if ($swv)
       {
          foreach ( $school_id_arr as $k => $v)
          {
              $school_id[$k]=$v;
              if ($v!=0)
              {
                  $volunteer[$k] = Fn::db()->fetchOne("SELECT school_name FROM rd_school WHERE school_id = $v");
              }
          }
       } 
        
        $data['volunteer'] = $volunteer;
        $data['subjects']        = C('subject');
        $data['specs']     = $specs;
        $data['uid']           = $uid;
        $data['grades']        = $grades;
        $data['student']       = $student;
        $data['score_ranks']   = $score_ranks;
        $data['awards_list']   = $awards_list;
        $data['practice']      = $practice;
        $data['student_wish']  = $student_wish;
        $data['parent_wish']   = $parent_wish;
        $data['xuekao_xuankao']   = $xuekao_xuankao;
        $data['subject_list']  = C('subject');
        $data['awards_levels'] = C('awards_level');

        $data['stunumtype_list'] = CourseModel::courseStuNumTypeList();
        $data['class_list'] = ClassModel::get_class_list($student['grade_id']);

        // 模版
        $this->load->view('student/preview', $data);
    }

    /**
     * @description 删除学生信息
     * @author
     * @final
     * @param int $uid 学生id
     */
    public function delete($uid=0)
    {
        if ( ! $this->check_power('student_delete')) return;

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/student/index';

        $uid = intval($uid);
        $uid && $row = StudentModel::get_student($uid);
        if (empty($uid))
        {
            message('学生信息不存在', $back_url);
            return;
        }

        recycle_log_check($uid);

        try
        {
            $this->db->update('student', array('is_delete'=>1), array('uid'=>$uid));
            admin_log('delete', 'student', $uid);

            recycle_log(RECYCLE_STUDENT, $uid);

            message('删除成功', $back_url, 'success');
        }
        catch(Exception $e)
        {
            message('删除失败', $back_url);
        }
    }

    /**
     * @description 批量删除学生信息
     * @author
     * @final
     * @param array $ids 学生id
     */
    public function batch_delete()
    {
        if ( ! $this->check_power('student_delete')) return;
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/student/index';

        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要删除的项目！');
            return;
        }
        try
        {
            $this->db->where_in('uid', $ids)->update('student', array('is_delete'=>1));
            admin_log('delete', 'student', implode(',',$ids));
            message('批量删除成功', $back_url);
        }
        catch(Exception $e)
        {
            message('批量删除失败', $back_url);
        }
    }

    /**
     * @description 从回收站中还原学生信息
     * @author
     * @final
     * @param int $uid 学生id
     */
    public function restore($uid=0)
    {
        if ( ! $this->check_power('student_trash')) return;

        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/student/index/trash';

        $uid = intval($uid);
        $uid && $row = StudentModel::get_student($uid);
        if (empty($uid))
        {
            message('学生信息不存在', $back_url);
            return;
        }
        try
        {
            $this->db->update('student', array('is_delete'=>0), array('uid'=>$uid));
            admin_log('restore', 'student', $uid);
            message('还原成功', $back_url);
        }
        catch(Exception $e)
        {
            message('还原失败', $back_url);
        }
    }

    /**
     * @description 从回收站中批量还原学生信息
     * @author
     * @final
     * @param int $ids 学生id
     */
    public function batch_restore()
    {
        if ( ! $this->check_power('student_trash')) return;
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/student/index/trash';

        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要还原的项目！');
            return;
        }
        try
        {
            $this->db->where_in('uid', $ids)->update('student', array('is_delete'=>0));
            admin_log('restore', 'student', implode(',',$ids));
            message('批量还原成功', $back_url);
        }
        catch(Exception $e)
        {
            message('批量还原失败', $back_url);
        }
    }

    /**
     * @description 从回收站中移除学生信息
     * @author
     * @final
     * @param int $ids 学生id
     */
    public function remove($uid)
    {
        if ( ! $this->check_power('student_trash')) return;
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/student/index/trash';

        $uid = intval($uid);
        $uid && $row = StudentModel::get_student($uid);
        if (empty($uid))
        {
            message('学生信息不存在', $back_url);
            return;
        }

        try
        {
            // 基本信息
            // 成绩排名
            // 竞赛获奖
            // 社会实践
            // 学生意愿
            // 家长意愿
            $tables = array('student', 'student_ranking', 'student_awards', 'student_practice', 'student_wish', 'student_parent_wish');
            $this->db->delete($tables, array('uid'=>$uid));
            // 删除图片
            if ($row['picture'] && is_file(_UPLOAD_ROOT_PATH_.$row['picture']))
            {
                @unlink(_UPLOAD_ROOT_PATH_.$row['picture']);
            }
            admin_log('remove', 'student', $uid);
            message('移除成功', $back_url);
        }
        catch(Exception $e)
        {
            message('移除失败:'.$e->getMessage(), $back_url);
        }
    }

    /**
     * @description 从回收站中批量移除学生信息
     * @author
     * @final
     * @param int $ids 学生id
     */
    public function batch_remove()
    {
        if ( ! $this->check_power('student_trash')) return;
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要删除的项目！');
            return;
        }

        try
        {
            // 图片
            $query = $this->db->select('picture')->where_in('uid', $ids)->where('picture >', '')->get('student');
            $pictures = $query->result_array();

            // 基本信息
            // 成绩排名
            // 竞赛获奖
            // 社会实践
            // 学生意愿
            // 家长意愿
            $tables = array('student', 'student_ranking', 'student_awards', 'student_practice', 'student_wish', 'student_parent_wish');
            $this->db->where_in('uid', $ids)->delete($tables);

            // 删除图片
            foreach ($pictures as $row)
            {
                if ($row['picture'] && is_file(_UPLOAD_ROOT_PATH_.$row['picture']))
                {
                    @unlink(_UPLOAD_ROOT_PATH_.$row['picture']);
                }
            }
            admin_log('remove', 'student', implode(',', $ids));
            message('批量移除成功', $back_url);
        }
        catch(Exception $e)
        {
            message('批量移除失败:'.$e->getMessage(), $back_url);
        }
    }

    /**
     * @description 检查输入的学生信息完整性
     * @author
     * @final
     * @param array $student 学生基本信息
     * @param array $extends 学生扩展信息
     * @param string $type 动作类型
     * @return multitype:string unknown NULL
     */
    private function _check_student(&$student, &$extends, $type = 'add', $tab = '')
    {
        $message = array();
        /*
         * 检查基本信息
         */
        if ($type == 'add')
        {
            if ( empty($student['email']) ) $message[] = '请填写Email地址';
            if ( !is_email($student['email'])) $message[] = 'Email格式错误';
            if (empty($student['password'])) $message[] = '请设置初始密码';

            $password = $student['password'];
            if (is_string($passwd_msg = is_password($password))) {
            	$message[] = $passwd_msg;
            }
            if ($password !== $this->input->post('password_confirm'))
            {
                $message[] = '两次密码输入不一致！';
            }

            $student['password'] = my_md5($student['password']);

			/*
            // 检查email是否已注册
            $query = $this->db->select('uid')->get_where('student',array('email'=>$student['email']), 1);
            if ($query->num_rows())
            {
                $message[] = 'Email地址已被注册';
            }
			*/
        }

        if ($tab == 'basic' || $tab == '')
        {
            if (empty($student['first_name']) OR empty($student['last_name'])) $message[] = '请填写学生姓名';
            if (empty($student['birthday'])) $message[] = '请填写出生日期';
            if (empty($student['idcard'])) $message[] = '请填写身份证号码';
            if ($student['grade_id']<1 OR $student['grade_id']>12) $message[] = '请选择就读年级';
            if (empty($student['school_id'])) $message[] = '请选择就读学校';
            //if (empty($student['address'])) $message[] = '请填写家庭地址';
            //if (empty($student['zipcode'])) $message[] = '请填写邮编';
            //if (empty($student['mobile'])) $message[] = '请填写手机号码';

            if (isset($_FILES['picture']) && $_FILES['picture']['name'])
            {
                if ($this->upload->do_upload('picture'))
                {
                    $student['picture'] =  $this->upload->data('file_relative_path');
                }
                else
                {
                    $message[] = $this->upload->display_errors();
                }
            }

            /* 存在错误，则删除已上传图片 */
            if ($message && isset($student['picture']) && is_file(_UPLOAD_ROOT_PATH_.$student['picture']))
            {
                @unlink(_UPLOAD_ROOT_PATH_.$student['picture']);
            }
        }

        if ($tab == 'awards' || $tab == '')
        {
        /*
         * 年级排名
         */
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
        if ($student['grade_id'] == 6 && empty($score_ranking)&&($type !='add'))
        {
            $message[] = '请填写年级排名';
        }
        else
        {
        	$start_grade = $student['grade_id']<3 ? 1 : $student['grade_id']-1;
            for ($i=$student['grade_id']; $i>$start_grade; $i--)
            {
                if (( ! isset($score_ranking[$i-1]))&&($student['grade_id']==6)&&($type !='add'))
                {
                    $message[] = '请填写最近两年年级成绩排名';
                    break;
                }
            }
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

         if ($student['uid'])
        {
            $old_ranking = $old_awards = $old_xuekao_xuankao = array();
            $query = $this->db->select('id,grade_id')->get_where('student_ranking', array('uid'=>$student['uid']));
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
           
            /*if($old_xuekao_xuankao)
            {
                $this->db->update('xuekao_xuankao', $update_xuekao_xuankao, array('id'=>$old_xuekao_xuankao['id']));
            }
            else
            {
                $update_xuekao_xuankao['uid'] = $uid;

                $this->db->insert('xuekao_xuankao', $update_xuekao_xuankao);
            }*/

            // 更新获奖情况
            $new_awards_list = array();
            foreach ($awards_list as $typeid => $type_list)
            {
                $new_awards_list = array_merge($new_awards_list, $type_list);
            }
            $awards_list = $new_awards_list;

            foreach ($awards_list as $k => &$awards)
            {
                $awards['uid'] = $student['uid'];
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
        }
        }

        if ($tab == 'practice' || $tab == '')
        {
        /*
         * 社会实践
         */
        $practice_keys = array('investigate','art','environment','work','other');
        $practice_arr = $this->input->post('practice', TRUE);
        foreach ($practice_keys as $key)
        {
            $practice[$key] = isset($practice_arr[$key]) ? html_escape(trim($practice_arr[$key])) : '';
        }
        }
        if ($tab == 'wish' || $tab == '')
        {
        /*
         * 学生意愿
        $student_wish_arr = $this->input->post('student_wish', TRUE);
        if ( ! isset($student_wish_arr['upmethod']))
            $student_wish_arr['upmethod'] = '其他';
        if ( ! isset($student_wish_arr['upmethod_other']))
            $student_wish_arr['upmethod_other'] = '';

        if (empty($student_wish_arr['upmethod']) OR empty($student_wish_arr['wish']) OR $student_wish_arr['upmethod']=='其他' && empty($student_wish_arr['upmethod_other']))
        {
            $message[] = '请填写学生意愿中的升学途经和发展意愿';
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


         */
        }


        if ($tab == 'pwish' || $tab == '')
        {

         /* 家长意愿
        $parent_wish_arr = $this->input->post('parent_wish', TRUE);
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
            $message[] = '请完整填写家长意愿';
        }
        else
        {
            $parent_wish = array(
                'family_bg' => implode(',',my_intval($parent_wish_arr['family_bg'])),
                'other_bg'  => trim($parent_wish_arr['other_bg']),
                'wish'      => isset($parent_wish_arr['wish']) ? html_escape($parent_wish_arr['wish']) : '',
                'upmethod'  => html_escape($parent_wish_arr['upmethod']=='其他' ?
                                $parent_wish_arr['upmethod_other'] : $parent_wish_arr['upmethod']),
            );
        }
        $parent_wish[family_bg_qt] = serialize($parent_wish_arr['family_bg_qt']);*/
        //选考学考
        $is_in_class =  $this->input->post('is_in_class');
        $subject_class = serialize(/*array_filter */( $this->input->post('subject_class') ) );
        $subject_in = serialize(/*array_filter */( $this->input->post('subject_in') ) );
        $subject_not = serialize(/*array_filter */( $this->input->post('subject_not') ) );
        $subject_first = $this->input->post('subject_first');
        /*$subject_first[shijian] = array_filter($subject_first[shijian]);
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
        }

        if (empty($message))
        {
            $extends = array(
                'practice'      => &$practice,
                'awards_list'   => &$awards_list,
                'score_ranking' => &$score_ranking,
                'student_wish'  => &$student_wish,
                //'parent_wish'   => &$parent_wish,
                //'xuekao_xuankao'   => &$update_xuekao_xuankao,
            );
        }

        return  $message;
    }

    /**
     * @description 加载重置密码模板
     * @author
     * @final
     * @param int $id 学生id
     */
    public function load_reset_password($id = 0)
    {
    	if ( ! $this->check_power('student_manage')) return;
    	$id = intval($id);
    	if (!$id) {
    		message('不存在该学生.');
    	}

    	$student = StudentModel::get_student($id);
    	if (!count($student)) {
    		message('不存在该学生.');
    	}

    	$data['uid'] = $id;
    	$this->load->view('student/reset_password', $data);
    }

    /**
     * @description 重置密码
     * @author
     * @final
     * @param int $uid 学生id
     * @param string $new_password 新密码
     * @param string $confirm_password 重复密码
     */
    public function reset_password()
    {
    	if ( ! $this->check_power('student_manage')) return;

    	$new_password = $this->input->post('new_password');
    	$new_confirm_password = $this->input->post('confirm_password');
    	$uid = intval($this->input->post('uid'));

    	if (is_string($passwd_msg = is_password($new_password))) {
    		output_json(CODE_ERROR, $passwd_msg);
    	}

    	if (!strlen(trim($new_confirm_password))) {
    		output_json(CODE_ERROR, '确认密码不能为空.');
    	}

    	if ($new_confirm_password != $new_password) {
    		output_json(CODE_ERROR, '两次密码输入不一致.');
    	}

    	//检查是否存在该学生
    	$passwd = StudentModel::get_student($uid, 'password');
    	if (!count($passwd)) {
    		output_json(CODE_ERROR, '不存在该学生.');
    	}

    	//修改学生密码
    	$flag = StudentModel::reset_password($uid, my_md5($new_password));
    	if (!$flag) {
    		output_json(CODE_ERROR, '密码修改失败，请重试');
    	}

    	output_json(CODE_SUCCESS, '密码修改成功.');
    }

	/**
     * 学生报名信息核对
	 */
	public function info_check()
	{
		if ($this->input->post('grade_id'))
		{
			$this->load->library('PHPExcel');
			$this->load->library('PHPExcel/IOFactory');

			$grade_id = intval($this->input->post('grade_id'));
			$school_id = intval($this->input->post('school_id'));
			if (!$grade_id)
			{
			    message('请选择年级');
			}

			if (!$school_id)
			{
			    message('请选择学校');
			}

			$file = $_FILES['infos']['name'];
			$desc = '../../cache/excel/' .$file;
			$tmp_name = $_FILES['infos']['tmp_name'];
			$extend = strrchr($file, '.');
			if (!in_array($extend, array('.xlsx', '.xls')))
			{
				message('文件类型不合法');
			}

            if (!move_uploaded_file($tmp_name, $desc))
            {
                message('文件移动失败，请联系管理员');
            }

			$reader = new PHPExcel_Reader_Excel2007();
			$reader_type = $extend == '.xlsx' ? 'Excel2007':'Excel5';
			$obj = new IOFactory();
			$objreader = $obj::createReader($reader_type)->load($desc);

			$sheet = $objreader->getSheet(0);
			$allRow = $sheet->getHighestRow();

			$file2 = $file. '信息核对反馈表';

			$objexcel = new PHPExcel();
			$objexcel->setActiveSheetIndex(0)->setCellValue('A1', '姓名');
			$objexcel->setActiveSheetIndex(0)->setCellValue('B1', '学号');
			$objexcel->setActiveSheetIndex(0)->setCellValue('C1', '年级');
			$objexcel->setActiveSheetIndex(0)->setCellValue('D1', '准考证号');
			$objexcel->setActiveSheetIndex(0)->setCellValue('E1', '报名');
			$objexcel->getActiveSheet()->setTitle($file2);
			$objwriter = IOFactory::createWriter($objexcel,$reader_type);

			for ($i = 2; $i <= $allRow; $i++)
			{
				$arr = array();
				$student_name = $objreader->getActiveSheet()->getCell('A'. $i)->getValue();
				$student_number = $objreader->getActiveSheet()->getCell('B'. $i)->getValue();
				$student_grade = $objreader->getActiveSheet()->getCell('C' .$i)->getValue();
                $space = "\xe3\x80\x80";
                $student_name = str_ireplace($space, "", $student_name);


				$count = 0;
				$objexcel->setActiveSheetIndex(0)->setCellValue('A' .$i , $student_name);
				$objexcel->setActiveSheetIndex(0)->setCellValue('B' .$i , $student_number);
				$objexcel->setActiveSheetIndex(0)->setCellValue('C' .$i , $student_grade);

				$student_search = array(

					'school_id' => $school_id,
					'grade_id' => $grade_id,
					'keyword' => "CONCAT(last_name,first_name) LIKE '%" . trim($student_name) . "%'",
				);


                $student_list = CommonModel::get_list($student_search,'rd_student','*');
				$number = count($student_list);

				if ($number)
				{
					if ($number > 1)
					{
						//2代表错误，一个学校一个年纪有多个重名学生情况
						$objexcel->setActiveSheetIndex(0)->setCellValue('D' .$i , '报名出错了(重名情况)');
						$objexcel->setActiveSheetIndex(0)->setCellValue('E' .$i , 2);
					}
					else
					{
						$student_info = array_shift($student_list);
						$idcard = $student_info['idcard'];
						$objexcel->setActiveSheetIndex(0)->setCellValue('D' .$i , $student_info['exam_ticket']);
						$objexcel->setActiveSheetIndex(0)->setCellValue('E' .$i , 1);
					}
				}
				else
				{
					$objexcel->setActiveSheetIndex(0)->setCellValue('D' .$i , '未报名');
					$objexcel->setActiveSheetIndex(0)->setCellValue('E' .$i , 0);
				}
			}

			$objwriter->save($desc);
			$data = array('url' => $desc, 'act' => 'update', 'name' => $file);
		}
		else
		{
			$grades = C('grades');
			$data = array('act' => 'add', 'grades' => $grades);
		}

		$this->load->view('/student/info_check', $data);
	}

	/**
	 * 自动下载核对信息表处理
	 */
	public function down()
	{
		$name = $this->input->post('filename');
        //$filename = _UPLOAD_ROOT_PATH_ .$name;
		$filename =  '../../cache/excel/' .$name;
		header('Content-Type:application/vnd.ms-excel');
		header('Content-Disposition:attachment;filename=' .$name);
		ob_start();
		readfile($filename);
		flush();
		exit;
	}
}
