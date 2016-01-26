<?php if (!defined('BASEPATH')) exit();
/**
 * 培训机构/校区相关
 */
class TrainingInstitution extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 培训机构列表
     */
    public function index()
    {
        $this->tilist();
    }

    /**
     * 培训机构列表
     */
    public function tilist()
    {
        Fn::ajax_call($this, 'removeTI');

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

        $param['ti_name'] = $this->input->get('ti_name');
        $param['ti_provid'] = intval($this->input->get('ti_provid'));
        $param['ti_cityid'] = intval($this->input->get('ti_cityid'));
        $param['ti_areaid'] = intval($this->input->get('ti_areaid'));

        $data = array();
        $data['param'] = $param;
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($param['ti_provid'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($param['ti_cityid'], FALSE, 3);
        $data['ti_list'] = 
            TrainingInstitutionModel::trainingInstitutionList('*', $param, $page);
        $data['ti_list_count'] = 
            TrainingInstitutionModel::trainingInstitutionListCount($param);
        $this->load->view('traininginstitution/tilist', $data);
    }

    /**
     * 选择培训机构列表
     * @param   int     multisel    GET参数,若为1表多选,否则表单选
     */
    public function seltilist()
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

        $param['ti_name'] = $this->input->get('ti_name');
        $param['ti_provid'] = intval($this->input->get('ti_provid'));
        $param['ti_cityid'] = intval($this->input->get('ti_cityid'));
        $param['ti_areaid'] = intval($this->input->get('ti_areaid'));

        $data = array();
        $data['param'] = $param;
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($param['ti_provid'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($param['ti_cityid'], FALSE, 3);

        $data['ti_list'] = 
            TrainingInstitutionModel::trainingInstitutionList('*', $param, $page);
        $data['ti_list_count'] = 
            TrainingInstitutionModel::trainingInstitutionListCount($param);
        $this->load->view('traininginstitution/seltilist', $data);
    }

    /**
     * 查看培训机构信息页面
     * @param   int     $ti_id  培训机构ID
     */
    public function tiinfo($ti_id)
    {
        Fn::ajax_call($this, 'removeTI');
        $data = array();
        $data['ti_info'] = 
            TrainingInstitutionModel::trainingInstitutionInfo($ti_id);
        if (empty($data['ti_info']))
        {
            message('查询无记录', '/admin/traininginstitution/tilist');
        }
        $this->load->view('traininginstitution/tiinfo', $data);
    }

    /**
     * 删除培训机构AJAX方法
     * @param   string  $ti_id_str  形如1,3,4样式的ID列表字符串
     */
    public function removeTIFunc($ti_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('traininginstitution_removeti', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            TrainingInstitutionModel::removeTrainingInstitution($ti_id_str);
            admin_log('delete', 'traininginstitution', "ti_id: $ti_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增培训机构表单
     */
    public function addtiinfo()
    {
        $this->settiinfo(0);
    }

    /**
     * 新增/编辑培训机构表单页面
     * @param   int     $ti_id  培训机构ID,若为0表新增,否则表编辑
     */
    public function settiinfo($ti_id)
    {
        Fn::ajax_call($this, 'setTI');
        $ti_id = intval($ti_id);
        $data = array();
        if ($ti_id)
        {
            $data['ti_info'] = 
                TrainingInstitutionModel::trainingInstitutionInfo($ti_id);
            if (empty($data['ti_info']))
            {
                message('查询无记录', '/admin/traininginstitution/tilist');
            }
        }
        else
        {
            $data['ti_info'] = array('ti_id' => 0);
        }
        $data['tit_list'] = 
            TrainingInstitutionModel::trainingInstitutionTypeList();
        $data['tipt_list'] = 
            TrainingInstitutionModel::trainingInstitutionPriTypeList();
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($data['ti_info']['ti_provid'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($data['ti_info']['ti_cityid'], FALSE, 3);
        $this->load->view('traininginstitution/settiinfo', $data);
    }

    /**
     * 新增/编辑培训机构AJAX方法
     * @param   array   $param  参数
     *              int     ti_id       培训机构ID,若为0表新增,否则表编辑
     *              string  ti_name     培训机构名称
     *              int     ti_typeid   培训机构类型
     *              int     ti_flag     状态,-1已删 0禁用 1启用 大于1待审
     *              int     ti_priid    优先级类型ID
     *              int     ti_provid   所属省
     *              int     ti_cityid   所属市
     *              int     ti_areaid   所属区
     *              string  ti_addr     地址
     *              string  ti_url      网址
     *              int     ti_stumax   一学年上课人数
     *              int     ti_reputation   荣誉值
     *              int     ti_cooperation  合作度
     *              int     ti_cooperation_addfreqday   合作度增加频率(天数,
     *                                      0表示自动增加,其它表示自动按N天增加)
     *              int     ti_cooperation_addinc       自动增加每次增加值
     *              int     ti_cooperation_addenddate   自动增加截止日期,为空
     *                                                  表示永不截止
     */
    public function setTIFunc($param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 
            'ti_id', 'ti_name', 'ti_typeid', 'ti_flag', 'ti_priid', 'ti_provid',
            'ti_cityid', 'ti_areaid', 'ti_addr', 'ti_url', 'ti_stumax',
            'ti_reputation', 'ti_cooperation', 'ti_cooperation_addfreqday',
            'ti_cooperation_addinc', 'ti_cooperation_addenddate');

        if ($param['ti_name'] == '')
        {
            $resp->alert('培训机构名称不可为空');
            return $resp;
        }
        if (!Validate::isInt($param['ti_typeid'])
            || $param['ti_typeid'] < 1)
        {
            $reps->alert('请选择培训机构类型');
            return $resp;
        }
        if (!Validate::isInt($param['ti_priid']))
        {
            $resp->alert('请选择培训机构优先级');
            return $resp;
        }
        if (!Validate::isInt($param['ti_provid'])
            || $param['ti_provid'] < 1)
        {
            $resp->alert('请选择培训机构所在省');
            return $resp;
        }
        if (!Validate::isInt($param['ti_stumax'])
            || $param['ti_stumax'] < 1)
        {
            $resp->alert('请写每学年学员人数');
            return $resp;
        }
        if (!Validate::isInt($param['ti_reputation']))
        {
            $resp->alert('请填写声誉值');
            return $resp;
        }
        if (!Validate::isInt($param['ti_cooperation']))
        {
            $resp->alert('请填写合作度');
            return $resp;
        }
        if ($param['ti_cooperation_addinc'] == '')
        {
            $param['ti_cooperation_addinc'] = 0;
        }
        if (!Validate::isInt($param['ti_cooperation_addinc']))
        {
            $resp->alert('请填写合作度增加规则');
            return $resp;
        }
        if ($param['ti_cooperation_addfreqday'] == '')
        {
            $param['ti_cooperation_addfreqday'] = 0;
        }
        if (!Validate::isInt($param['ti_cooperation_addfreqday']))
        {
            $resp->alert('请填写合作度增加规则');
            return $resp;
        }
        if ($param['ti_cooperation_addinc']
            && $param['ti_cooperation_addfreqday'])
        {
            if (strlen($param['ti_cooperation_addenddate']) > 0)
            {
                if (!Validate::isDate($param['ti_cooperation_addenddate']))
                {
                    $resp->alert('请填写合作度增加规则持续时间');
                    return $resp;
                }
                if (strcmp(date('Y-m-d'), $param['ti_cooperation_addenddate'])
                    >= 0)
                {
                    $resp->alert('请填写合作度增加规则持续时间');
                    return $resp;
                }
            }
        }
        if (empty($param['ti_cooperation_addenddate']))
        {
            $param['ti_cooperation_addenddate'] = NULL;
        }
        try
        {
            if ($param['ti_id'])
            {
                TrainingInstitutionModel::setTrainingInstitution($param);
                admin_log('edit', 'traininginstitution', "ti_id: " . $param['ti_id']);
            }
            else
            {
                $param['ti_id'] = 
                    TrainingInstitutionModel::addTrainingInstitution($param);
                admin_log('add', 'traininginstitution', "ti_id: " . $param['ti_id']);
            }
            $resp->redirect('/admin/traininginstitution/tiinfo/' . $param['ti_id']);
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }


    //=======================================================================
    /**
     * 培训机构类型列表
     */
    public function titlist()
    {
        Fn::ajax_call($this, 'removeTIT', 'setTIT');

        $data = array();
        $data['tit_list'] = 
            TrainingInstitutionModel::trainingInstitutionTypeList();
        $this->load->view('traininginstitution/titlist', $data);
    }

    /**
     * 培训机构类型删除AJAX方法
     * @param   string      $tit_id_str 形如1,3,4样式的ID列表
     */
    public function removeTITFunc($tit_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('traininginstitution_removetit', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            TrainingInstitutionModel::removeTrainingInstitutionType($tit_id_str);
            admin_log('delete', '', "培训机构类型tit_id: $tit_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增培训机构类型层对话框DIV
     */
    public function addtitinfo()
    {
        $this->settitinfo(0);
    }

    /**
     * 新增/编辑培训机构类型层对话框DIV
     * @param   int     $tit_id     培训机构类型ID,若为0表新增
     */
    public function settitinfo($tit_id)
    {
        $tit_id = intval($tit_id);
        $data = array();
        $data['tit_id'] = $tit_id;
        if ($tit_id)
        {
            $data['tit_info'] = 
                TrainingInstitutionModel::trainingInstitutionTypeInfo($tit_id);
        }
        else
        {
            $data['tit_info'] = array('tit_id' => '', 'tit_name' => '');
        }
        $this->load->view('traininginstitution/settitinfodlg', $data);
    }

    /**
     * 新增/编辑培训机构类型AJAX方法
     * @param   int     $tit_id     旧ID,若为0表新增
     * @param   array   $param      新参数
     *                  int     tit_id      新ID
     *                  string  tit_name    新名称
     */
    public function setTITFunc($tit_id, $param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'tit_id', 'tit_name');

        if ($tit_id)
        {
            if (!$this->check_power_new('traininginstitution_settitinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }
        else
        {
            if (!$this->check_power_new('traininginstitution_addtitinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }


        if (!Validate::isInt($param['tit_id']))
        {
            $resp->alert('培训机构类型ID必须为整数');
            return $resp;
        }
        if ($param['tit_name'] == '')
        {
            $resp->alert('培训机构类型名称不可为空');
            return $resp;
        }
        try
        {
            if ($tit_id)
            {
                TrainingInstitutionModel::setTrainingInstitutionType($tit_id, $param);
                admin_log('edit', '', "培训机构类型tit_id: " . $param['tit_id']);
            }
            else
            {
                TrainingInstitutionModel::addTrainingInstitutionType($param);
                admin_log('add', '', "培训机构类型tit_id: " . $param['tit_id']);
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
     * 培训机构优先类型列表
     */
    public function tiptlist()
    {
        Fn::ajax_call($this, 'removeTIPT', 'setTIPT');

        $data = array();
        $data['tipt_list'] = 
            TrainingInstitutionModel::trainingInstitutionPriTypeList();
        $this->load->view('traininginstitution/tiptlist', $data);
    }

    /**
     * 删除培训机构优先类别AJAX方法
     * @param   string  $tipt_id_str    形如1,3,4样式的ID列表
     */
    public function removeTIPTFunc($tipt_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('traininginstitution_removetipt', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            TrainingInstitutionModel::removeTrainingInstitutionPriType($tipt_id_str);
            admin_log('delete', '', "培训机构优先类别tipt_id: $tipt_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增培训机构优先类型层对话框DIV
     */
    public function addtiptinfo()
    {
        $this->settiptinfo(0);
    }

    /**
     * 新增编辑培训机构优先类型层对话框DIV
     * @param   int     $tipt_id    培训机构优先类型,若为0表新增,否则表编辑
     */
    public function settiptinfo($tipt_id)
    {
        $tipt_id = intval($tipt_id);
        $data = array();
        $data['tipt_id'] = $tipt_id;
        if ($tipt_id)
        {
            $data['tipt_info'] = 
                TrainingInstitutionModel::trainingInstitutionPriTypeInfo($tipt_id);
        }
        else
        {
            $data['tipt_info'] = array('tipt_id' => '', 'tipt_name' => '');
        }
        $this->load->view('traininginstitution/settiptinfodlg', $data);
    }

    /**
     * 新增/编辑培训机构优先类型AJAX方法
     * @param   int     $tipt_id    旧ID,0表新增,否则表编辑
     * @param   array   $param      map<string, variant>类型新参数
     *                   int    tipt_id     新ID
     *                   string tipt_name   新名称
     */
    public function setTIPTFunc($tipt_id, $param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 'tipt_id', 'tipt_name');

        if ($tipt_id)
        {
            if (!$this->check_power_new('traininginstitution_settiptinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }
        else
        {
            if (!$this->check_power_new('traininginstitution_addtiptinfo', false))
            {
                $resp->alert('您没有权限执行该功能');
                return $resp;
            }
        }


        if (!Validate::isInt($param['tipt_id']))
        {
            $resp->alert('培训机构优先类型ID必须为整数');
            return $resp;
        }
        if ($param['tipt_name'] == '')
        {
            $resp->alert('培训机构优先类型名称不可为空');
            return $resp;
        }
        try
        {
            if ($tipt_id)
            {
                TrainingInstitutionModel::setTrainingInstitutionPriType($tipt_id, $param);
                admin_log('edit', '', "培训机构优先类别tipt_id: " . $param['tipt_id']);
            }
            else
            {
                TrainingInstitutionModel::addTrainingInstitutionPriType($param);
                admin_log('add', '', "培训机构优先类别tipt_id: " . $param['tipt_id']);
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
     * 培训机构校区列表
     * @param   int     $ti_id  = NULL  培训机构ID,若为NULL表示查询所有,否则
     *                                  表示只查询该机构ID所指校区
     */
    public function tclist($ti_id = NULL)
    {
        Fn::ajax_call($this, 'removeTC');

        $param = array();
        $ti_id = intval($ti_id);
        if ($ti_id)
        {
            $ti_info = TrainingInstitutionModel::trainingInstitutionInfo($ti_id);
            if ($ti_info)
            {
                $param['tc_tiid'] = $ti_id;
            }
        }
        else
        {
            $ti_info = array();
        }
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
        $param['order_by'] = 'tc_tiid, tc_provid, tc_cityid, tc_areaid, tc_id';

        $data = array();
        $data['ti_info'] = $ti_info;
        $data['tc_list'] = 
            TrainingInstitutionModel::trainingCampusList('*', $param, $page);
        $data['tc_list_count'] = 
            TrainingInstitutionModel::trainingCampusListCount($param);
        $this->load->view('traininginstitution/tclist', $data);
    }

    /**
     * 选择培训机构校区列表
     * @param   int     $ti_id  = NULL  培训机构ID,若为NULL表示查询所有,否则
     *                                  表示只查询该机构ID所指校区
     * @param   int     multisel         GET参数,若为1表多选,否则表单选
     */
    public function seltclist($ti_id = NULL)
    {
        $param = array();
        $ti_id = intval($ti_id);
        if ($ti_id)
        {
            $ti_info = TrainingInstitutionModel::trainingInstitutionInfo($ti_id);
            if ($ti_info)
            {
                $param['tc_tiid'] = $ti_id;
            }
        }
        else
        {
            $ti_info = array();
        }
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
        $param['order_by'] = 'tc_tiid, tc_provid, tc_cityid, tc_areaid, tc_id';
        
        $tc_id = Fn::getParam('tc_id');
        if (Validate::isJoinedIntStr($tc_id))
        {
            $param['tc_id'] = $tc_id;
        }

        $data = array();
        $data['ti_info'] = $ti_info;
        $data['tc_list'] = 
            TrainingInstitutionModel::trainingCampusList('*', $param, $page);
        $data['tc_list_count'] = 
            TrainingInstitutionModel::trainingCampusListCount($param);
        $this->load->view('traininginstitution/seltclist', $data);
    }

    /**
     * 查看校区信息页面
     * @param   int     $tc_id      校区ID
     */
    public function tcinfo($tc_id)
    {
        Fn::ajax_call($this, 'removeTC');
        $data = array();
        $data['tc_info'] = 
            TrainingInstitutionModel::trainingCampusInfo($tc_id);
        if (empty($data['tc_info']))
        {
            message('查询无记录', '/admin/traininginstitution/tilist');
        }
        $this->load->view('traininginstitution/tcinfo', $data);
    }

    /**
     * 删除校区AJAX方法
     * @param   string  $tc_id_str  形如1,3,4样式的ID列表字符串
     */
    public function removeTCFunc($tc_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('traininginstitution_removetc', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            TrainingInstitutionModel::removeTrainingCampus($tc_id_str);
            admin_log('delete', 'trainingcampus', "tc_id: $tc_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增校区表单页面
     * @param   int     $ti_id  所属培训机构ID
     */
    public function addtcinfo($ti_id)
    {
        Fn::ajax_call($this, 'setTC');
        $ti_id = intval($ti_id);
        $data = array();
        if ($ti_id)
        {
            $ti_info = 
                TrainingInstitutionModel::trainingInstitutionInfo($ti_id);
        }
        if (empty($ti_info))
        {
            message('查询无记录', '/admin/traininginstitution/tilist');
        }
        $tc_info = array('tc_id' => 0, 
            'tc_flag' => time(), 
            'tc_tiid' => $ti_id,
            'tc_provid' => 0, 
            'tc_cityid' => 0, 
            'tc_areaid' => 0);
        $data['tc_info'] = array_merge($tc_info, $ti_info);
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = array();
        $data['area_list'] = array();
        //$data['city_list'] = RegionModel::get_regions($data['ti_info']['tc_provid'], FALSE, 2);
        //$data['area_list'] = RegionModel::get_regions($data['ti_info']['tc_cityid'], FALSE, 3);
        $this->load->view('traininginstitution/settcinfo', $data);
    }

    /**
     * 编辑校区表单页面
     * @param   int     $tc_id  要编辑的校区ID
     */
    public function settcinfo($tc_id)
    {
        Fn::ajax_call($this, 'setTC');
        $tc_id = intval($tc_id);
        $data = array();
        if ($tc_id)
        {
            $data['tc_info'] = 
                TrainingInstitutionModel::trainingCampusInfo($tc_id);
        }
        if (empty($data['tc_info']))
        {
            message('查询无记录', '/admin/traininginstitution/tilist');
        }
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions($data['tc_info']['tc_provid'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($data['tc_info']['tc_cityid'], FALSE, 3);
        $this->load->view('traininginstitution/settcinfo', $data);
    }

    /**
     * 新增/编辑校区AJAX方法
     * @param   array   $param  map<string,variant>类型参数
     *                  int     tc_id   ID,若为0表新增
     *                  int     tc_tiid 所属培训机构ID
     *                  string  tc_name 名称
     *                  int     tc_flag 状态,-1已删 0禁用 1启用 大于1待审
     *                  int     tc_environ  环境指数
     *                  int     tc_provid   地址省
     *                  int     tc_cityid   地址市
     *                  int     tc_areaid   地址区县
     *                  string  tc_ctcaddr  联系地址
     *                  string  tc_ctcperson    联系人
     *                  string  tc_ctcphone     联系电话
     */
    public function setTCFunc($param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 
            'tc_id', 'tc_name', 'tc_tiid', 'tc_flag', 'tc_environ', 'tc_provid',
            'tc_cityid', 'tc_areaid', 'tc_ctcaddr', 'tc_ctcperson', 'tc_ctcphone');

        if ($param['tc_name'] == '')
        {
            $resp->alert('培训校区名称不可为空');
            return $resp;
        }
        if (!Validate::isInt($param['tc_provid'])
            || $param['tc_provid'] < 1)
        {
            $resp->alert('请选择校区所在省');
            return $resp;
        }
        if ($param['tc_ctcaddr'] == '')
        {
            $resp->alert('请填写联系地址');
            return $resp;
        }
        if ($param['tc_ctcperson'] == '')
        {
            $param['tc_ctcperson'] = NULL;
        }
        if ($param['tc_ctcphone'] == '')
        {
            $resp->alert('请填写联系电话');
            return $resp;
        }
        if (!Validate::isInt($param['tc_environ']))
        {
            $reps->alert('请填写环境指数');
            return $resp;
        }

        try
        {
            if ($param['tc_id'])
            {
                unset($param['tc_tiid']);
                TrainingInstitutionModel::setTrainingCampus($param);
                admin_log('edit', 'trainingcampus', "tc_id: " . $param['tc_id']);
            }
            else
            {
                $param['tc_id'] = 
                    TrainingInstitutionModel::addTrainingCampus($param);
                admin_log('add', 'trainingcampus', "tc_id: " . $param['tc_id']);
            }
            $resp->redirect('/admin/traininginstitution/tcinfo/' . $param['tc_id']);
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    //======================================================================
    /**
     * 导入机构和校区记录(从excel文件中),
     * 注意: 目前$ti_id参数未启用
     *
     * @param   int     $ti_id = NULL   默认将校区全导入到该机构ID下
     */
    public function import_titc_excel($ti_id = NULL)
    {
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel', 
                'file/import_training_campus_template.xls', 
                '培训机构及校区导入模板.xls');
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
                '机构名称',
                '机构类型',
                '机构省份',
                '机构城市',
                '机构区县',
                '机构地址',
                '优先级',
                '学员人数/年',
                '机构网址',
                '校区名称',
                '校区省份',
                '校区城市',
                '校区区县',
                '校区地址',
                '联系人',
                '联系电话');
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

            $tit_map = array_flip(array_map("trim", 
                TrainingInstitutionModel::trainingInstitutionTypePairs()));
            $tipt_map = array_flip(array_map("trim", 
                TrainingInstitutionModel::trainingInstitutionPriTypePairs()));

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
            $ti_arr = array(); //ti_arr[ti_name] => ti_list[i]
            $tc_arr = array(); //tc_arr[ti_name][tc_name] => tc_list[i]
            $ti_list = array();
            $tc_list = array();
            foreach ($rows as $k => $row)
            {
                // 机构名称
                if (mb_strlen($row[0], 'UTF-8') > 60)
                {
                    $data['error'] = $col_char[0] . ($k + 2) 
                        . ' - "机构名称"内容太长了,不可超过60个字符';
                    break;
                }
                $row['ti_name'] = $row[0];
                // 机构类型
                if ($row[1] == '')
                {
                    $data['error'] = $col_char[1] . ($k + 2) 
                        . ' - "机构类型"内容不可为空';
                    break;
                }
                // 机构类型
                if (!isset($tit_map[$row[1]]))
                {
                    $data['error'] = $col_char[1] . ($k + 2)
                        . ' - "机构类型"不正确';
                    break;
                }
                $row['ti_typeid'] = $tit_map[$row[1]];

                if ($row[2] == '')
                {
                    if ($param['ti_provid_required'])
                    {
                        $data['error'] = $col_char[2] . ($k + 2)
                            . ' - "机构省份"不可为空';
                        break;
                    }
                    else
                    {
                        $row['ti_provid'] = 0;
                    }
                }
                else
                {
                    if (!isset($regionname_map[$row[2]]))
                    {
                        $data['error'] = $col_char[2] . ($k + 2)
                            . ' - "机构省份"不存在';
                        break;
                    }
                    $row['ti_provid'] = $regionname_map[$row[2]];
                }
                $row['ti_cityid'] = 0;
                $row['ti_areaid'] = 0;
                if (!empty($region_map[$regionname_map[$row[2]]]['children']))
                {
                    // 验证市
                    if ($row[3] == '')
                    {
                        if ($param['ti_cityid_required'])
                        {
                            $data['error'] = $col_char[3] . ($k + 2)
                                . ' - "机构城市"不可为空';
                            break;
                        }
                        else
                        {
                            $row['ti_cityid'] = 0;
                        }
                    
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[2] . '_' . $row[3]]))
                        {
                            $data['error'] = $col_char[3] . ($k + 2)
                                . ' - "机构城市"不存在';
                            break;
                        }
                        $row['ti_cityid'] = $regionname_map[$row[2] . '_' . $row[3]];
                    }

                    // 验证区县
                    if ($row[4] == '')
                    {
                        if ($param['ti_areaid_required'])
                        {
                            $data['error'] = $col_char[4] . ($k + 2)
                                . ' - "机构区县"不可为空';
                            break;
                        }
                        else
                        {
                            $row['ti_areaid'] = 0;
                        }
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[2] . '_' . $row[3] . '_' . $row[4]]))
                        {
                            $data['error'] = $col_char[4] . ($k + 2)
                                . ' - "机构区县"不存在';
                            break;
                        }
                        $row['ti_areaid'] = $regionname_map[$row[2] . '_' . $row[3] . '_' . $row[4]];
                    }
                }

                // 机构地址
                if ($row[5] == '')
                {
                    if ($param['ti_addr_required'])
                    {
                        $data['error'] = $col_char[5] . ($k + 2)
                            . ' - "机构地址"不可为空';
                        break;
                    }
                }
                if (mb_strlen($row[5], 'UTF-8') > 255)
                {
                    $data['error'] = $col_char[5] . ($k + 2)
                        . ' - "机构地址"内容太长了';
                    break;
                }
                $row['ti_addr'] = $row[5];

                // 优先级
                if ($row[6] == '')
                {
                    if ($param['ti_priid_required'])
                    {
                        $data['error'] = $col_char[6] . ($k + 2)
                            . ' - "优先级"不能为空';
                        break;
                    }
                    else
                    {
                        $row[6] = '一般';
                    }
                }
                if (!isset($tipt_map[$row[6]]))
                {
                    $data['error'] = $col_char[6] . ($k + 2)
                        . ' - "优先级"不正确';
                    break;
                }
                $row['ti_priid'] = $tipt_map[$row[6]];

                if ($row[7] == '')
                {
                    if ($param['ti_stumax_required'])
                    {
                        $data['error'] = $col_char[6] . ($k + 2)
                            . ' - "学员人数/年"不能为空';
                        break;
                    }
                    else
                    {
                        $row[7] = '0';
                    }
                }
                // 学员人数/年
                if (!Validate::isInt($row[7]) || $row[7] < 0)
                {
                    $data['error'] = $col_char[7] . ($k + 2)
                        . ' - "学员人数/年"必须为正整数';
                    break;
                }
                $row['ti_stumax'] = $row[7];

                // 机构网址
                if (mb_strlen($row[8], 'UTF-8') > 512)
                {
                    $data['error'] = $col_char[8] . ($k + 2)
                        . ' - "机构网址"内容太长了';
                    break;
                }
                $row['ti_url'] = $row[8];

                // 校区名称
                if ($row[9] == '')
                {
                    $data['error'] = $col_char[9] . ($k + 2)
                        . ' - "校区名称"不可为空';
                    break;
                }
                if (mb_strlen($row[9], 'UTF-8') > 60)
                {
                    $data['error'] = $col_char[9] . ($k + 2)
                        . ' - "校区名称"太长了';
                    break;
                }
                $row['tc_name'] = $row[9];

                if ($row[10] == '')
                {
                    if ($param['tc_provid_required'])
                    {
                        $data['error'] = $col_char[10] . ($k + 2)
                            . ' - "校区省份"不可为空';
                        break;
                    }
                    else
                    {
                        $row['tc_provid'] = 0;
                    }
                }
                else
                {
                    if (!isset($regionname_map[$row[10]]))
                    {
                        $data['error'] = $col_char[10] . ($k + 2)
                            . ' - "校区省份"不存在';
                        break;
                    }
                    $row['tc_provid'] = $regionname_map[$row[10]];
                }
                $row['tc_cityid'] = 0;
                $row['tc_areaid'] = 0;
                if (!empty($region_map[$regionname_map[$row[10]]]['children']))
                {
                    // 验证市
                    if ($row[11] == '')
                    {
                        if ($param['tc_cityid_required'])
                        {
                            $data['error'] = $col_char[11] . ($k + 2)
                                . ' - "校区城市"不可为空';
                            break;
                        }
                        else
                        {
                            $row['tc_cityid'] = 0;
                        }
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[10] . '_' . $row[11]]))
                        {
                            $data['error'] = $col_char[11] . ($k + 2)
                                . ' - "校区城市"不存在';
                            break;
                        }
                        $row['tc_cityid'] = $regionname_map[$row[10] . '_' . $row[11]];
                    }

                    // 验证区县
                    if ($row[12] == '')
                    {
                        if ($param['tc_areaid_required'])
                        {
                            $data['error'] = $col_char[12] . ($k + 2)
                                . ' - "校区区县"不可为空';
                            break;
                        }
                        else
                        {
                            $row[12] = 0;
                        }
                    }
                    else
                    {
                        if (!isset($regionname_map[$row[10] . '_' . $row[11] . '_' . $row[12]]))
                        {
                            $data['error'] = $col_char[12] . ($k + 2)
                                . ' - "校区区县"不存在';
                            break;
                        }
                        $row['tc_areaid'] = $regionname_map[$row[10] . '_' . $row[11] . '_' . $row[12]];
                    }
                }

                // 校区地址
                if ($row[13] == '')
                {
                    if ($param['tc_ctcaddr_required'])
                    {
                        $data['error'] = $col_char[13] . ($k + 2)
                            . ' - "校区地址"不可为空';
                        break;
                    }
                }
                if (mb_strlen($row[13], 'UTF-8') > 255)
                {
                    $data['error'] = $col_char[13] . ($k + 2)
                        . ' - "校区地址"太长了';
                    break;
                }
                $row['tc_ctcaddr'] = $row[13];

                // 联系人
                if (mb_strlen($row[14], 'UTF-8') > 60)
                {
                    $data['error'] = $col_char[14] . ($k + 2)
                        . ' - "联系人"太长了';
                    break;
                }
                $row['tc_ctcperson'] = $row[14];

                // 联系电话
                if ($row['15'] == '')
                {
                    if ($param['tc_ctcphone_required'])
                    {
                        $data['error'] = $col_char[15] . ($k + 2)
                            . ' - "联系电话"不能为空';
                        break;
                    }
                }
                if (mb_strlen($row[15], 'UTF-8') > 120)
                {
                    $data['error'] = $col_char[15] . ($k + 2)
                        . ' - "联系电话"太长了';
                    break;
                }
                $row['tc_ctcphone'] = $row[15];

                if (!isset($ti_arr[$row[0]]))
                {
                    $ti_arr[$row[0]] = count($ti_list);
                    $ti_list[] = array(
                        'index' => $k + 2,
                        'ti_id' => 0,
                        'ti_name' => $row[0],
                        'ti_typeid' => $row['ti_typeid'],
                        'ti_provid' => $row['ti_provid'],
                        'ti_cityid' => $row['ti_cityid'],
                        'ti_areaid' => $row['ti_areaid'],
                        'ti_addr' => $row[5],
                        'ti_priid' => $row['ti_priid'],
                        'ti_stumax' => $row[7],
                        'ti_url' => $row[8]);
                }
                if (!isset($tc_arr[$row[0]]))
                {
                    $tc_arr[$row[0]] = array();
                }
                if (isset($tc_arr[$row[0]][$row[9]]))
                {
                    $data['error'] = $col_char[9] . ($k + 2)
                        . ' - "校区名称"同一机构内有重复';
                    break;
                }
                $tc_arr[$row[0]][$row[9]] = count($tc_list);
                $tc_list[] = array(
                    'index' => $k + 2,
                    'ti_name' => $row[0],
                    'tc_name' => $row[9],
                    'tc_provid' => $row['tc_provid'],
                    'tc_cityid' => $row['tc_cityid'],
                    'tc_areaid' => $row['tc_areaid'],
                    'tc_ctcaddr' => $row[13],
                    'tc_ctcperson' => $row[14],
                    'tc_ctcphone' => $row[15]);
            }
            if (isset($data['error']))
            {
                break;
            }

            unset($region_map);
            unset($regionname_map);
            unset($tit_map);
            unset($tipt_map);
            unset($rows);
            // 这里开始导入
            try
            {
                $db = Fn::db();
                if (!$db->beginTransaction())
                {
                    throw new Exception('开始导入事务处理失败');
                }

                $time = time();
                $adduid = Fn::sess()->userdata('admin_id');

                // 导入机构
                $sql1 = <<<EOT
SELECT ti_id FROM t_training_institution WHERE ti_name = ?
EOT;
                $sql2 = <<<EOT
SELECT tc_id FROM t_training_campus WHERE tc_tiid = ? AND tc_name = ?
EOT;
                $sql3 = <<<EOT
UPDATE t_training_institution SET ti_campusnum = ti_campusnum + 1 
WHERE ti_id = 
EOT;
                $tc_update = 0;
                $tc_insert = 0;
                $ti_update = 0;
                $ti_insert = 0;
                foreach ($ti_list as $k => $row)
                {
                    $ti_id = $db->fetchOne($sql1, array($row['ti_name']));
                    if ($ti_id)
                    {
                        $ti_list[$k]['ti_id'] = $ti_id;
                        // update
                        $row2 = array();
                        if ($param['same_tiname_update_ti_typeid'])
                        {
                            $row2['ti_typeid'] = $row['ti_typeid'];
                        }
                        if ($param['same_tiname_update_ti_region']
                            && intval($row['ti_provid']) != 0)
                        {
                            $row2['ti_provid'] = $row['ti_provid'];
                            $row2['ti_cityid'] = $row['ti_provid'];
                            $row2['ti_areaid'] = $row['ti_areaid'];
                        }
                        if ($param['same_tiname_update_ti_addr']
                            && $row['ti_addr'] != '')
                        {
                            $row2['ti_addr'] = $row['ti_addr'];
                        }
                        if ($param['same_tiname_update_ti_priid'])
                        {
                            $row2['ti_priid'] = $row['ti_priid'];
                        }
                        if ($param['same_tiname_update_ti_stumax']
                            && intval($row['ti_stumax']) != '0')
                        {
                            $row2['ti_stumax'] = $row['ti_stumax'];
                        }
                        if ($param['same_tiname_update_ti_url']
                            && $row['ti_url'] != '')
                        {
                            $row2['ti_url'] = $row['ti_url'];
                        }
                        if ($row2)
                        {
                            $db->update('t_training_institution', $row2,
                                'ti_id = ' . $ti_id);
                            $ti_update++;
                        }
                    }
                    else
                    {
                        // insert
                        unset($row['index']);
                        unset($row['ti_id']);
                        $row['ti_flag'] = $time;
                        if ($row['ti_addr'] == '')
                        {
                            $row['ti_addr'] = NULL;
                        }
                        if ($row['ti_url'] == '')
                        {
                            $row['ti_url'] = NULL;
                        }
                        $row['ti_addtime'] = date('Y-m-d H:i:s', $time);
                        $row['ti_adduid'] = $adduid;

                        $db->insert('t_training_institution', $row);
                        $ti_list[$k]['ti_id'] = $db->lastInsertId(
                            't_training_institution', 'ti_id');
                        $ti_insert++;
                    }
                }

                // 导入校区
                foreach ($tc_list as $k => $row)
                {
                    $ti_id = $ti_list[$ti_arr[$row['ti_name']]]['ti_id'];
                    $tc_list[$k]['ti_id'] = $ti_id;

                    $tc_id = $db->fetchOne($sql2, array(
                        $ti_id, $row['tc_name']));
                    if ($tc_id)
                    {
                        // update
                        $tc_list[$k]['tc_id'] = $tc_id;

                        $row2 = array();
                        if ($param['same_tcname_update_tc_region']
                            && intval($row['tc_provid']) != 0)
                        {
                            $row2['tc_provid'] = $row['tc_provid'];
                            $row2['tc_cityid'] = $row['tc_provid'];
                            $row2['tc_areaid'] = $row['tc_areaid'];
                        }
                        if ($param['same_tcname_update_tc_ctcaddr']
                            && $row['tc_ctcaddr'] != '')
                        {
                            $row2['tc_ctcaddr'] = $row['tc_ctcaddr'];
                        }
                        if ($param['same_tcname_update_tc_ctcperson']

                            && $row['tc_ctcperson'] != '')
                        {
                            $row2['tc_ctcperson'] = $row['tc_ctcperson'];
                        }
                        if ($param['same_tcname_update_tc_ctcphone']
                            && $row['tc_ctcphone'] != '')
                        {
                            $row2['tc_ctcphone'] = $row['tc_ctcphone'];
                        }
                        if ($row2)
                        {
                            $db->update('t_training_campus', $row2,
                                'tc_id = ' . $tc_id);
                            $tc_update++;
                        }
                    }
                    else
                    {
                        // insert
                        unset($row['index']);
                        unset($row['ti_name']);
                        $row['tc_tiid'] = $ti_id;
                        $row['tc_flag'] = $time;
                        $row['tc_environ'] = 3;
                        $row['tc_addtime'] = date('Y-m-d H:i:s', $time);
                        $row['tc_adduid'] = $adduid;
                        if ($row['tc_ctcperson'] == '')
                        {
                            $row['tc_ctcperson'] = NULL;
                        }
                        $db->insert('t_training_campus', $row);
                        $tc_list[$k]['tc_id'] = $db->lastInsertId(
                            't_training_campus', 'tc_id');
                        $db->exec($sql3 . $ti_id);
                        $tc_insert++;
                    }
                }

                if ($db->commit())
                {
                    $data['success'] = <<<EOT
导入Excel文件({$_FILES['file']['name']})成功,共更新{$ti_update}条机构记录, 插入{$ti_insert}条机构记录, 更新{$tc_update}条校区记录, 插入{$tc_insert}条校区记录
EOT;
                    admin_log('import', '', "培训机构和校区 " . $data['success']);
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
                'ti_provid_required' => 1,  // 机构省份必填
                'ti_cityid_required' => 2,  // 机构城市必填
                'ti_areaid_required' => 3,  // 机构区县必填
                'ti_addr_required' => 1,    // 机构地址必填
                'ti_priid_required' => 1,   // 优先级必填
                'ti_stumax_required' => 1,  // 学生人数必填
                'tc_provid_required' => 1,  // 校区省份必填
                'tc_cityid_required' => 2,  // 校区城市必填
                'tc_areaid_required' => 3,  // 校区区县必填
                'tc_ctcaddr_required' => 1,     // 校区地址必填
                'tc_ctcphone_required' => 1,    // 联系电话必填
                'same_tiname_update_ti_typeid' => 1,    // 同名机构更新类型
                'same_tiname_update_ti_region' => 1,    // 同名机构更新地区
                'same_tiname_update_ti_addr' => 1,      // 同名机构更新地址
                'same_tiname_update_ti_priid' => 1,     // 同名机构更新优先级
                'same_tiname_update_ti_stumax' => 1,    // 同名机构更新学生人数
                'same_tiname_update_ti_url' => 1,       // 同名机构更新网址
                'same_tcname_update_tc_region' => 1,    // 同名校区更新地区
                'same_tcname_update_tc_ctcaddr' => 1,   // 同名校区更新地址
                'same_tcname_update_tc_ctcperson' => 1, // 同名校区更新联系人
                'same_tcname_update_tc_ctcphone' => 1); // 同名校区更新联系电话
        }
        $data['param'] = $param;
        $this->load->view('traininginstitution/import_titc_excel', $data);
    }
}
