<?php if (!defined('BASEPATH')) exit();
/**
 * 课程授课教师相关管理
 */
class CTeacher extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 授课教师列表
     */
    public function index()
    {
        $this->ctlist();
    }

    /**
     * 培训教师列表
     */
    public function ctlist()
    {
        Fn::ajax_call($this, 'removeCT');

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

        $param['ct_name'] = $this->input->get('ct_name');
        $param['ct_contact'] = $this->input->get('ct_contact');
        $param['grade_id_str'] = $this->input->get('grade_id_str');
        $param['subject_id_str'] = $this->input->get('subject_id_str');

        $data = array();
        $data['param'] = $param;
        $data['ct_list'] = CTeacherModel::CTeacherList('*', $param, $page);
        $ct_id_arr = array();
        if (!empty($data['ct_list']))
        {
            foreach ($data['ct_list'] as $v)
            {
                $ct_id_arr[] = $v['ct_id'];
            }
        }
        $ct_id_str = implode(',', $ct_id_arr);
        unset($ct_id_arr);

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $data['ct_list_count'] = CTeacherModel::CTeacherListCount($param);
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
        $this->load->view('cteacher/ctlist', $data);
    }

    /**
     * 选择培训教师列表
     * @param   int     multisel    GET参数,为1表示多选,否则表示单选
     */
    public function selctlist()
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

        $param['ct_name'] = $this->input->get('ct_name');
        $param['ct_contact'] = $this->input->get('ct_contact');
        $param['grade_id_str'] = $this->input->get('grade_id_str');
        $param['subject_id_str'] = $this->input->get('subject_id_str');

        $data = array();
        $data['param'] = $param;
        $data['ct_list'] = CTeacherModel::CTeacherList('*', $param, $page);
        $ct_id_arr = array();
        if (!empty($data['ct_list']))
        {
            foreach ($data['ct_list'] as $v)
            {
                $ct_id_arr[] = $v['ct_id'];
            }
        }
        $ct_id_str = implode(',', $ct_id_arr);
        unset($ct_id_arr);

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $data['ct_list_count'] = CTeacherModel::CTeacherListCount($param);
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
        $this->load->view('cteacher/selctlist', $data);
    }

    /**
     * 查看授课教师页面
     * @param   int     $ct_id  ID
     */
    public function ctinfo($ct_id)
    {
        Fn::ajax_call($this, 'removeCT');
        $data = array();
        $data['ct_info'] = CTeacherModel::CTeacherInfo($ct_id);
        if (empty($data['ct_info']))
        {
            message('查询无记录', 'admin/cteacher/ctlist');
        }
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

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        $data['subject_map'][0] = '[全部学科]';
        $data['grade_map'][0] = '[全部年级]';

        $sql = <<<EOT
SELECT a.cc_id, a.tc_name, b.ti_name, b.cors_name
FROM v_course_campus a
LEFT JOIN v_course b on a.cc_corsid = b.cors_id
WHERE a.cc_id IN (
    SELECT cct_ccid FROM t_course_campus_teacher WHERE cct_ctid = {$ct_id})
EOT;
        $data['cteacher_cclist'] = Fn::db()->fetchAll($sql);
        $this->load->view('cteacher/ctinfo', $data);
    }

    /**
     * 删除授课教师AJAX方法
     * @param   string      $ct_id_str  形如1,2,3的ID列表字符串
     */
    public function removeCTFunc($ct_id_str)
    {
        $resp = new AjaxResponse();
        if (!$this->check_power_new('cteacher_removect', false))
        {
            $resp->alert('您没有权限执行该功能');
            return $resp;
        }
        try
        {
            CTeacherModel::removeCTeacher($ct_id_str);
            admin_log('delete', 'cteacher', "ct_id: $ct_id_str");
            $resp->call('location.reload');
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    /**
     * 新增授课教师页面
     */
    public function addctinfo()
    {
        $this->setctinfo(0);
    }

    /**
     * 新增/编辑授课教师页面
     * @param   int     $ct_id  授课教师ID,若为0表新增,否则表编辑
     */
    public function setctinfo($ct_id)
    {
        Fn::ajax_call($this, 'setCT');
        $ct_id = intval($ct_id);
        $data = array();
        if ($ct_id)
        {
            $data['ct_info'] = 
                CTeacherModel::CTeacherInfo($ct_id);
            if (empty($data['ct_info']))
            {
                message('查询无记录', 'admin/cteacher/ctlist');
            }
        }
        else
        {
            $data['ct_info'] = array('ct_id' => 0, 'ct_flag' => time());
        }

        $data['subject_map'] = C('subject');
        $data['grade_map'] = C('grades');
        //$data['subject_map'][0] = '[全部学科]';
        //$data['grade_map'][0] = '[全部年级]';

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

        if ($ct_id)
        {
            $sql = <<<EOT
SELECT a.cc_id, a.tc_name, b.ti_name, b.cors_name
FROM v_course_campus a
LEFT JOIN v_course b on a.cc_corsid = b.cors_id
WHERE a.cc_id IN (
    SELECT cct_ccid FROM t_course_campus_teacher WHERE cct_ctid = {$ct_id})
EOT;
            $data['cteacher_cclist'] = Fn::db()->fetchAll($sql);
        }
        $this->load->view('cteacher/setctinfo', $data);
    }

    /**
     * 编辑授课教师AJAX方法
     * @param   array   $param  map<stirng,variant>类型的参数
     *                  int     ct_id   教师ID,若为0表新增
     *                  string  ct_name 名称
     *                  string  ctc_contact 联系方式
     *                  string  subject_id_str  形如1,3,4样式的学科ID列表
     *                  string  grade_id_str    形如1,3,4样式的年级ID列表
     *                  int     ct_flag     状态,-1已删,0禁用,1启用,大于1待审
     */
    public function setCTFunc($param)
    {
        $resp = new AjaxResponse();
        $param = Func::param_copy($param, 
            'ct_id', 'ct_name', 'ct_contact', 'subject_id_str', 
            'grade_id_str', 'ct_flag', 'cct_ccid_str', 'ct_memo');

        if (!Validate::isInt($param['ct_id']) || $param['ct_id'] < 0)
        {
            $reps->alert('教师ID不正确');
            return $resp;
        }
        if ($param['ct_name'] == '')
        {
            $resp->alert('教师名称不正确');
            return $resp;
        }
        if ($param['ct_contact'] == '')
        {
            $param['ct_contact'] = NULL;
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
        if ($param['cct_ccid_str'] == '')
        {
            $param['cct_ccid_list'] = array();
        }
        else if (!Validate::isJoinedIntStr($param['cct_ccid_str']))
        {
            $resp->alert('所选课程不正确');
            return $resp;
        }
        else
        {
            $param['cct_ccid_list'] = explode(',', $param['cct_ccid_str']);
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
                CTeacherModel::setCTeacher($param);
                admin_log('edit', 'cteacher', "ct_id: " . $param['ct_id']);
            }
            else
            {
                $param['ct_id'] = 
                    CTeacherModel::addCTeacher($param);
                admin_log('add', 'cteacher', "ct_id: " . $param['ct_id']);
            }
            $resp->redirect('/admin/cteacher/ctinfo/' . $param['ct_id']);
        }
        catch (Exception $e)
        {
            $resp->alert($e->getMessage());
        }
        return $resp;
    }

    //======================================================================
    /**
     * 导入授课教师记录(从excel文件中),
     */
    public function import_cteacher_excel()
    {
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel', 
                'file/import_cteacher_template.xlsx', 
                '授课教师导入模板.xlsx');
            exit();
        }

        $data = array();
        while (isset($_FILES['file']))
        {
            $param = $_POST;
            $title = array(
                '姓名',
                '年级',
                '学科',
                '联系方式',
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


                ////////////
                //   姓名0 年级1　学科2　联系方式3 简介4
                //////////////////
                
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

                // 联系方式
                if ($row[3] == '')
                {
                    $row[3] = NULL;
                }
                else if (mb_strlen($row[3], 'UTF-8') > 255)
                {
                    $data['error'] = $col_char[3] . ($k + 2) 
                        . ' - "联系方式"内容太长了,不可超过255个字符';
                    break;
                }

                // 简介
                if ($row[4] == '')
                {
                    $row[4] = NULL;
                }

                $ct_list[] = array(
                        'index' => $k + 2,
                        'ct_name' => $row[0],
                        'ct_contact' => $row[3],
                        'ct_memo' => $row[4],
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
                        'ct_contact' => $row['ct_contact'],
                        'ct_memo' => $row['ct_memo'],
                        'ct_flag' => $param['ct_flag'])
                    );
                    $ct_id = $db->lastInsertId('t_cteacher', 'ct_id');
                        
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
        $this->load->view('cteacher/import_cteacher_excel', $data);
    }
}
