<?php if ( ! defined('BASEPATH')) exit();
class School extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @description 学校列表
     * @author
     * @final
     * @param int $province 省
     * @param int $city 市
     * @param int $area 区/县
     * @param int $school_id 学校
     * @param int $grade_period 学校类别
     * @param int $keyword 关键字
     */
    public function index()
    {
        Fn::ajax_call($this, 'removeSchool');
        $grade_periods = $this->config->item('grade_period');        
        $param = array(
            'province' => $this->input->get('province'),
            'city' => $this->input->get('city'),
            'area' => $this->input->get('area'),
            'grade_period' => $this->input->get('grade_period'),
            'keyword' => $this->input->get('keyword'),
            'order_by' => 'school_id');
        if (!is_array($param['grade_period']))
        {
            $param['grade_period'] = array();
        }

        $total = SchoolModel::schoolListCount($param);

        $size = 15;
        $page = 1;
        if (isset($_GET['page']) && intval($_GET['page']) > 1)
        {
            $page = intval($_GET['page']);
        }
        $offset = ($page - 1) * $size;

        $data = array();
        if ($total)
        {
            $rows = SchoolModel::schoolList('*', $param, $page, $size);
            foreach ($rows as &$row) 
            {
                $row['periods'] = explode(',', $row['grade_period']);
                $row['period']  = array();
                foreach ($row['periods'] as $period)
                {
                    $row['period'][] = isset($grade_periods[$period]) 
                        ? $grade_periods[$period] : '';
                }
                $row['period'] = implode(',', $row['period']);
            }
            $data['list'] = &$rows;
        }
        else
        {
            $data['list'] = array();
        }

        //是否异步选择
        $is_ajax = $this->input->get('is_ajax') ? TRUE : FALSE;
        $data['is_ajax'] = $is_ajax;
        $param['is_ajax'] = $is_ajax;

        $get = $_GET;
        unset($get['page']);
        $url = site_url('admin/school/index').'?'.http_build_query($get);
        $data['pagination'] = multipage($total, $size, $page, $url);
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($param['province'], 
            FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($param['city'], 
            FALSE, 3);
        $data['grade_periods'] = $grade_periods;
        $data['param'] = $param;
        
        $data['has_class_manage_priv'] = $this->check_power_new(
            'school_editclass,school_updateclass,school_deleteclass', false);
        
        $data['has_teacher_manage_priv'] = $this->check_power_new(
            'school_teacherlist,school_editteacher,school_importteacher,school_deleteteacher', false);

        // 模版
        $this->load->view('school/index', $data);
    }
    
    /**
     * @description 添加学校
     * @author
     * @final
     */
    public function add()
    {        
        if ( ! $this->check_power('school_manage')) return;        
                
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
        $referer_duibi=site_url('admin/school/create');
        if($referer==$referer_duibi)
        {
            $this->load->helper('cookie');
            $data['detail']= json_decode(get_cookie('school_save'), true);
            $data['detail']['grade_period']=implode(',',  $data['detail']['grade_period']);
        }
        else 
        {
            if(isset($data['detail']))
                unset($data['detail']); 
        }  
       
        //end
        // 使用Yaf样式RegionModel代替
        /* 初始化地区信息 */
        $data['province_list'] = RegionModel::get_regions(1);
        if($referer==$referer_duibi)
        {
            $data['city_list'] = RegionModel::get_regions(
                $data['detail']['province'], FALSE, 2);
            $data['area_list'] = RegionModel::get_regions(
                $data['detail']['city'], FALSE, 3);
        }
        /* 初始化学校类型 */
        $data['grade_periods'] = $this->config->item('grade_period');
        $this->load->view('school/add', $data);
    }

    /**
     * @description 编辑学校
     * @author
     * @final
     * @param int $id 学校id
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('school_manage')) return;

        $id = intval($id);
        $id && $school = SchoolModel::schoolInfo($id);
        if (empty($school))
        {
            message('学校不存在');
            return;
        }
        
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list']     = RegionModel::get_regions($school['province']);
        $data['area_list']     = RegionModel::get_regions($school['city']);
        $data['grade_periods'] = $this->config->item('grade_period');
        $data['school']        = $school;

        // 模版
        $this->load->view('school/edit', $data);
    }
    
    /**
     * @description 添加学校保存数据库
     * @author
     * @final
     * @param int $province 学校所在省
     * @param int $city 学校所在市
     * @param int $area 学校所在区县
     * @param int $school_name 学校名称
     * @param int $grade_period 学校类型
     * @param int $status 学校状态
     */
    public function create()
    {
        if ( ! $this->check_power('school_manage')) return;
        //print_r($this->input->post());exit;
        $data['province'] = intval($_POST['province']);
        $data['city'] = intval($_POST['city']);
        $data['area'] = intval($_POST['area']);        
        $data['school_name'] = trim($this->input->post('school_name'));
        $data['grade_period'] = $this->input->post('grade_period');
        $data['school_property'] = intval($this->input->post('school_property')) ? 1 : 0;
        $data['status'] = intval($this->input->post('status')) ? 1 : 0;
        
        $this->load->helper('cookie');
       
        set_cookie('school_save',json_encode($this->input->post()),3600,'','/');
        
        foreach ($data as $k => $v)
        {
            if ($k != 'status' && $k != 'school_property' && empty($v))
            {
                message('请填写完整学校信息！','/admin/school/add');
                return;
            }
        }
        $data['grade_period'] = implode(',', my_intval($data['grade_period']));

        
        $queryArr = array(
                'school_name' => $data['school_name']
        );
        
        if ($invigilator_id > 0)
        {
            $queryArr['invigilator_id != '] = $invigilator_id;
        }
        
        $query = $this->db->select('school_id')->get_where('school', $queryArr);
        
        if ($query->num_rows())
        {
            message('该学校已存在！','/admin/school/add');
            return;
        }
        //end
        
        $this->db->insert('school', $data);  
        admin_log('add', 'school', $this->db->insert_id());
        message('学校添加成功');
    }

    /**
     * @description 更新学校保存数据库
     * @author
     * @final
     * @param int $school_id 学校id
     * @param int $province 学校所在省
     * @param int $city 学校所在市
     * @param int $area 学校所在区县
     * @param int $school_name 学校名称
     * @param int $grade_period 学校类型
     * @param int $status 学校状态
     */
    public function update()
    {
        if ( ! $this->check_power('school_manage')) return;

        $school_id = intval($_POST['school_id']);
        $data['province'] = intval($_POST['province']);
        $data['city'] = intval($_POST['city']);
        $data['area'] = intval($_POST['area']);
        $data['grade_period'] = $this->input->post('grade_period');
        $data['school_name'] = trim($this->input->post('school_name'));
        $data['school_property'] = intval($this->input->post('school_property')) ? 1 : 0;
        $data['status'] = intval($this->input->post('status')) ? 1 : 0;
        foreach ($data as $k => $v)
        {
            if ($k != 'status' && $k != 'school_property' && empty($v))
            {
                message('请填写完整学校信息！');
                return;
            }
        }
        $data['grade_period'] = implode(',', my_intval($data['grade_period']));

        $this->db->update('school', $data, array('school_id' => $school_id));
        admin_log('edit', 'school', $school_id);
        message('学校修改成功.');
    }
    
    public function removeSchoolFunc($school_id_str)
    {
        $resp = new AjaxResponse();
        try
        {
            if (!$this->check_power_new('school_delete', false))
            {
                $resp->alert;('您没有删除权限');
                return $resp;
            }
            
            if (SchoolModel::removeSchool($school_id_str))
            {
                admin_log('delete', 'school', $school_id_str);
                $resp->alert('删除成功');
                $resp->refresh();
            }
            else
            {
                $resp->alert('删除失败');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * TODO 该方法已废弃
     * @description 删除学校
     * @author
     * @final
     * @param int $school_id 学校id
     */
    public function delete($id)
    {
        if ( ! $this->check_power('school_manage')) return;
        
        $id = intval($id);
        $this->db->delete('school', array('school_id'=>$id));
        admin_log('delete', 'school', $id);
        message('学校删除成功', 'admin/school/index/');        
    }
    
    /**
     * TODO 该方法已废弃
     * @description 批量删除学校
     * @author
     * @final
     * @param array|int $ids 学校id
     */
    public function delete_batch()
    {
        if ( ! $this->check_power('school_manage')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            $this->db->where_in('school_id', $ids)->delete('school');
            admin_log('delete', 'school', implode(',', $ids));
            message('学校删除成功！', 'admin/school/index');
        }
        else
        {
            message('请选择要删除的学校');
        }
    }

    /**
     * 学校班级列表
     */
    public function classlist($schcls_schid = 0)
    {
        Fn::ajax_call($this, 'removeClass');
        
        if (!Validate::isInt($schcls_schid)
            || $schcls_schid <= 0)
        {
            return;
        }
        
        $param['schcls_schid'] = $schcls_schid;
        $param['schcls_name'] = Fn::getParam('schcls_name');
        
        $total = SchoolModel::schoolClassListCount($param);

        $size = 15;
        $page = 1;
        if (isset($_GET['page']) && intval($_GET['page']) > 1)
        {
            $page = intval($_GET['page']);
        }
        $offset = ($page - 1) * $size;
        
        $schclass_list = SchoolModel::schoolClassList('*', $param, $page, $size);
        
        $get = $_GET;
        unset($get['page']);
        $url = site_url('admin/school/classlist/' . $schcls_schid).'?'.http_build_query($get);
        $data['pagination'] = multipage($total, $size, $page, $url);
        $data['list'] = $schclass_list;
        $data['param'] = $param;
        
        // 模版
        $this->load->view('school/classlist', $data);
    }
    
    public function removeClassFunc($schcls_id_str)
    {
        $resp = new AjaxResponse();
        
        try
        {
            if (!$this->check_power_new('school_deleteclass', false))
            {
                $resp->alert('您没有删除权限');
                return $resp;
            }
            
            if (SchoolModel::removeSchoolClass($schcls_id_str))
            {
                admin_log('delete', 'school_class', $schcls_id_str);
                $resp->alert('删除成功');
                $resp->refresh();
            }
            else
            {
                $resp->alert('删除失败');
            }
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        
        return $resp;
    }
    
    /**
     * 新增/编辑班级
     */
    public function editclass($schcls_schid = 0, $schcls_id = 0)
    {
        if (!$schcls_schid && !$schcls_id)
        {
            message('参数错误');
        }
        
        $data = array();
        $data['schcls_schid'] = $schcls_schid;
        $data['schcls_id'] = $schcls_id;
        if ($schcls_id)
        {
            $data['class'] = SchoolModel::schoolClassInfo($schcls_id);
        }
        
        // 模版
        $this->load->view('school/editclass', $data);
    }
    
    /**
     * 保存新增/编辑班级数据
     */
    public function updateclass()
    {
        $schcls_schid = Fn::getParam('schcls_schid');
        $schcls_id = Fn::getParam('schcls_id');
        
        if (!$schcls_schid && !$schcls_id)
        {
            message('参数错误');
        }
        
        $schcls_name = Fn::getParam('schcls_name');
        if (!$schcls_name)
        {
            message('班级名称不能为空');
        }
        
        $param = array();
        $param['schcls_name'] = trim($schcls_name);
        
        try 
        {
            //编辑
            if ($schcls_id)
            {
                $class = SchoolModel::schoolClassInfo($schcls_id);
                if (!$class)
                {
                    message('班级不存在');
                }
                $schcls_schid = $class['schcls_schid'];
                
                $param['schcls_id'] = $schcls_id;
                $result = SchoolModel::setSchoolClass($param);
                if ($result)
                {
                    admin_log('edit', 'school_class', $schcls_id);
                }
            }
            else
            {
                $param['schcls_schid'] = $schcls_schid;
                $result = SchoolModel::addSchoolClass($param);
                if ($result)
                {
                    admin_log('edit', 'school_class', $result);
                }
            }
            
            message($result ? '操作成功' : '操作失败', 
                '/admin/school/classlist/'.$schcls_schid);
        }
        catch (Exception $e)
        {
            message($e->getMessage());
        }
    }
    
    /**
     * 学校教师列表
     */
    public function teacherlist($sch_id)
    {
        Fn::ajax_call($this, 'removeSchoolTeacher');
    
        if (!Validate::isInt($sch_id)
            || $sch_id <= 0)
        {
            return;
        }
        
        $school = SchoolModel::schoolInfo($sch_id);
        if (!$school)
        {
            return;
        }
    
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
        
        $param ['scht_schid'] = $sch_id;
        $param['ct_name'] = trim($this->input->get('ct_name'));
        $param['grade_id_str'] = $this->input->get('grade_id_str');
        $param['subject_id_str'] = $this->input->get('subject_id_str');

        $data = array();
        $data['school'] = $school;
        $data['param'] = $param;
        
        $total = SchoolModel::schoolTeacherListCount($param);
        
        if ($total)
        {
            $data['list'] = SchoolModel::schoolTeacherList('*', $param, $page);
            
            $ct_id_str = implode(',', array_keys($data['list']));
            
            if ($ct_id_str)
            {
                $data['cteacher_gradeid'] =
                    CTeacherModel::CTeacherGradeIDPairs($ct_id_str);
                $data['cteacher_subjectid'] =
                    CTeacherModel::CTeacherSubjectIDPairs($ct_id_str);
            }
            else
            {
                $data['cteacher_gradeid'] = array();
                $data['cteacher_subjectid'] = array();
            }
            
            $get = $_GET;
            unset($get['page']);
            $url = site_url('admin/school/teacherlist/' . $sch_id).'?'.http_build_query($get);
            $data['pagination'] = multipage($total , 
                C('default_perpage_num'), $page, $url);
        }
        
        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        
        // 模版
        $this->load->view('school/teacherlist', $data);
    }
    
    /**
     * 新增/编辑授课教师页面
     * @param   int     $scht_schid 学校ID
     * @param   int     $ct_id  授课教师ID,若为0表新增,否则表编辑
     */
    public function editteacher($scht_schid = 0, $ct_id = 0)
    {
        if (!$scht_schid)
        {
            message('参数错误');
        }
    
        $data = array();
        $data['scht_schid'] = $scht_schid;
        
        Fn::ajax_call($this, 'setSchoolTeacher');
        
        $ct_id = intval($ct_id);
        if ($ct_id)
        {
            $data['ct_info'] = CTeacherModel::CTeacherInfo($ct_id);
            if (empty($data['ct_info']))
            {
                message('查询无记录', 'admin/school/teacherlist/' . $scht_schid);
            }
        }
        else
        {
            $data['ct_info'] = array('ct_id' => 0, 'ct_flag' => time());
        }
    
        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
    
        $v = CTeacherModel::CTeacherGradeIDPairs($ct_id);
        if (isset($v[$ct_id]))
        {
            $v = $v[$ct_id];
        }
        else
        {
            $v = array();
        }
        $data['cteacher_gradeid'] = $v;
    
        $v = CTeacherModel::CTeacherSubjectIDPairs($ct_id);
        if (isset($v[$ct_id]))
        {
            $v = $v[$ct_id];
        }
        else
        {
            $v = array();
        }
        $data['cteacher_subjectid'] = $v;
    
        // 模版
        $this->load->view('school/editteacher', $data);
    }
    
    /**
     * 删除授课教师AJAX方法
     * @param   string      $ct_id_str  形如1,2,3的ID列表字符串
     */
    public function removeSchoolTeacherFunc($ct_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('school_deleteteacher', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        
        try
        {
            SchoolModel::removeSchoolTeacher($ct_id_str);
            admin_log('delete', 'school_teacher', "ct_id: $ct_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }
    
    /**
     * 编辑授课教师AJAX方法
     * @param   array   $param  map<stirng,variant>类型的参数
     *                  int     scht_schid  学校id
     *                  int     ct_id   教师ID,若为0表新增
     *                  string  ct_name 名称
     *                  string  subject_id_str  形如1,3,4样式的学科ID列表
     *                  string  grade_id_str    形如1,3,4样式的年级ID列表
     *                  int     ct_flag     状态,-1已删,0禁用,1启用,大于1待审
     */
    public function setSchoolTeacherFunc($param)
    {
        $resp = new AjaxResponse();
        
        if (!$this->check_power_new('school_editteacher', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        
        $param = Func::param_copy($param, 'scht_schid',
            'ct_id', 'ct_name', 'ct_contact', 'subject_id_str',
            'grade_id_str', 'ct_flag', 'cct_ccid_str', 'ct_memo');
        
        if (!Validate::isInt($param['scht_schid']) 
            || $param['scht_schid'] <= 0)
        {
            $resp->alert('教师所属学校不正确');
            return $resp;
        }
    
        if (!Validate::isInt($param['ct_id']) || $param['ct_id'] < 0)
        {
            $resp->alert('教师ID不正确');
            return $resp;
        }
        if ($param['ct_name'] == '')
        {
            $resp->alert('教师名称不正确');
            return $resp;
        }
        
        if (!Validate::isJoinedIntStr($param['grade_id_str']))
        {
            $resp->alert('所选年级不正确');
            return $resp;
        }
        
        if (!Validate::isJoinedIntStr($param['subject_id_str']))
        {
            $resp->alert('所选学科不正确');
            return $resp;
        }
        
        $param['subjectid_list'] = array_unique(explode(',', $param['subject_id_str']));
        $param['gradeid_list'] = array_unique(explode(',', $param['grade_id_str']));
        if (count($param['gradeid_list']) == count(C('grades')))
        {
            $param['gradeid_list'] = array(0);
        }
    
        try
        {
            if ($param['ct_id'])
            {
                SchoolModel::setSchoolTeacher($param);
                admin_log('edit', 'school_teacher', "ct_id: " . $param['ct_id']);
            }
            else
            {
                $param['ct_id'] = SchoolModel::addSchoolTeacher($param);
                admin_log('add', 'school_teacher', "ct_id: " . $param['ct_id']);
            }
            
            $resp->redirect('/admin/school/teacherlist/' . $param['scht_schid']);
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }
    
    //======================================================================
    /**
     * 导入教师记录(从excel文件中),
     */
    public function importteacher($sch_id = 0)
    {
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel', 
                'file/import_school_teacher_template.xlsx', 
                '教师导入模板.xlsx');
            exit();
        }
        
        if (!$sch_id || !SchoolModel::schoolInfo($sch_id))
        {
            message('学校不存在，无法导入教师！');
        }

        $data = array();
        $data['sch_id'] = $sch_id;
        
        while (isset($_FILES['file']))
        {
            $param = $_POST;
            $title = array(
                '姓名',
                '年级',
                '学科',
                '简介');
            $col_char = array();
            $rows = Excel::readSimpleUploadFile($_FILES['file'], $title, $col_char);
            if (!is_array($rows))
            {
                $data['error'] = $rows;
                break;
            }

            $grade_map = array_flip(C('grades'));
            $subject_map = array_flip(C('subject'));

            $ct_list = array();
            foreach ($rows as $k => $row)
            {
                
                //////////////////////////////
                //   姓名0 年级1　学科2　 简介3
                //////////////////////////////
                
                // 姓名
                if ($row[0] == '')
                {
                    $data['error'] == $col_char[0] . ($k + 2)
                        . ' - "姓名"不可为空';
                    break;
                }
                if (mb_strlen($row[0], 'UTF-8') > 30)
                {
                    $data['error'] = $col_char[0] . ($k + 2) 
                        . ' - "姓名"内容太长了,不可超过30个字符';
                    break;
                }

                // 年级
                if ($row[1] == '')
                {
                    $data['error'] = $col_char[1] . ($k + 2)
                        . ' - "年级"不能为空';
                    break;
                }
                $row[1] = str_replace(array('，', ' ', '　', '、', "\r\n", "\r", "\n"), ',', $row[1]);
                $row['ctg_gradeid'] = array();
                $arr = explode(',', $row[1]);
                foreach ($arr as $v)
                {
                    $v = trim($v);
                    if ($v == '')
                    {
                        continue;
                    }
                    if (isset($grade_map[$v]))
                    {
                        $row['ctg_gradeid'][] = $grade_map[$v];
                    }
                    else
                    {
                        $data['error'] = $col_char[1] . ($k + 2)
                            . ' - "年级"里有不正确的选项';
                        break;
                    }
                }
                if (isset($data['error']))
                {
                    break;
                }
                if (empty($row['ctg_gradeid']))
                {
                    $data['error'] = $col_char[1] . ($k + 2)
                        . ' - "年级"不能为空';
                    break;
                }
                $row['ctg_gradeid'] = array_unique($row['ctg_gradeid']);
                
                // 学科
                if ($row[2] == '')
                {
                    $data['error'] = $col_char[2] . ($k + 2)
                        . ' - "学科"不能为空';
                    break;
                }
                $row[2] = str_replace(array('，', ' ', '　', '、', "\r\n", "\r", "\n"), ',', $row[2]);
                $row['cts_subjectid'] = array();
                $arr = explode(',', $row[2]);
                foreach ($arr as $v)
                {
                    $v = trim($v);
                    if ($v == '')
                    {
                        continue;
                    }
                    if (isset($subject_map[$v]))
                    {
                        $row['cts_subjectid'][] = $subject_map[$v];
                    }
                    else
                    {
                        $data['error'] = $col_char[2] . ($k + 2)
                            . ' - "学科"里有不正确的选项空';
                        break;
                    }
                }
                if (isset($data['error']))
                {
                    break;
                }
                if (empty($row['cts_subjectid']))
                {
                    $data['error'] = $col_char[2] . ($k + 2)
                        . ' - "学科"不能为空';
                    break;
                }

                $row['cts_subjectid'] = array_unique($row['cts_subjectid']);

                // 简介
                if ($row[3] == '')
                {
                    $row[3] = NULL;
                }

                $ct_list[] = array(
                        'index' => $k + 2,
                        'ct_name' => $row[0],
                        'ct_memo' => $row[3],
                        'ctg_gradeid' => $row['ctg_gradeid'],
                        'cts_subjectid' => $row['cts_subjectid']
                    );
            }
            if (isset($data['error']))
            {
                break;
            }

            unset($grade_map);
            unset($subject_map);
            unset($rows);
            // 这里开始导入
            
            $db = Fn::db();
            try
            {
                $time = time();
                $adduid = Fn::sess()->userdata('admin_id');
                if (!$db->beginTransaction())
                {
                    throw new Exception('开始导入事务处理失败');
                }

                $ct_insert = 0;

                // 导入教师
                foreach ($ct_list as $k => $row)
                {
                    // insert
                    $db->insert('t_cteacher', array(
                        'ct_name' => $row['ct_name'],
                        'ct_memo' => $row['ct_memo'],
                        'ct_flag' => $param['ct_flag'])
                    );
                    $ct_id = $db->lastInsertId('t_cteacher', 'ct_id');
                    
                    $bind = array(
                        'scht_schid' => $sch_id,
                        'scht_ctid'  => $ct_id
                    );
                    $db->insert('t_cteacher_school', $bind);
                    
                    foreach ($row['ctg_gradeid'] as $v)
                    {
                        $db->insert('t_cteacher_gradeid',
                            array('ctg_ctid' => $ct_id,
                                'ctg_gradeid' => $v));
                    }
                    
                    foreach ($row['cts_subjectid'] as $v)
                    {
                        $db->insert('t_cteacher_subjectid',
                            array('cts_ctid' => $ct_id,
                                'cts_subjectid' => $v));
                    }
                    
                    $ct_insert++;
                }

                if ($db->commit())
                {
                    $data['success'] = <<<EOT
导入Excel文件({$_FILES['file']['name']})成功,共插入{$ct_insert}条教师记录
EOT;
                    admin_log('add', 'cteacher', $data['success']);
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
                'ct_flag' => time()  // 默认状态为未审
            );
        }
        
        $data['param'] = $param;
        $this->load->view('school/importteacher', $data);
    }
}
