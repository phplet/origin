<?php if ( ! defined('BASEPATH')) exit();
class Account extends A_Controller 
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




    /**
     * ription 学生管理列表
     * 
     * @author
     *
     * @final
     *
     * @param int $mode
     *            是否回收站
     * @param int $province
     *            省
     * @param int $city
     *            市
     * @param int $area
     *            区/县
     * @param int $school_id
     *            学校
     * @param int $grade_id
     *            年级
     * @param int $keyword
     *            关键字
     */
    public function index($mode = '')
    {
        if (! $this->check_power('account_manage')) return;
        // 查询条件
        $where = array();
        $param = array();
        $search = array();
        $query = array();
        $data = array();
        $query['is_delete'] = 0;
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
            $param[] = "keyword=" . urlencode($search['keyword']);
            $query['keyword'] = "CONCAT(last_name,first_name,idcard) LIKE '%" .
                     $escape_keyword . "%'";
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
            $param[] = "uid={$search['uid']}";
            $query = array();
            $query['uid'] = $search['uid'];
        }
        /*
         * 统计所有学生数量
         */
        $res = CommonModel::get_list($query, 'rd_student', 
                'count(*) AS count');
        $total = $res[0]['count'];
        /*
         * 分页读取数据列表，并处理相关数据
         */
        $size = 15;
        $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                $_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $res = CommonModel::get_list($query, 'v_student', '*', 
                    $page, $size);
            foreach ($res as $row)
            {
                $row['grade'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '未选择';
                $list[] = $row;
            }
        }
        $data['list'] = $list;
        // 分页
        $purl = site_url('admin/account/index/') .
                 ($param ? '?' . implode('&', $param) : '');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['province_list'] = RegionModel::get_regions(1);
        $data['city_list'] = RegionModel::get_regions(
                $search['province'], FALSE, 2);
        $data['area_list'] = RegionModel::get_regions($search['city'], 
                FALSE, 3);
        $data['grades'] = $grades;
        $data['from'] = C('student_source');
        $data['search'] = $search;
        $data['schools'] = array();
        $data['mode'] = $mode;
        $data['priv_delete'] = $this->check_power('student_trash', FALSE);
        $data['priv_manage'] = $this->check_power('account_manage', FALSE);
        // 模版
        $this->load->view('account/index', $data);
    }

    
    /**
     * 编辑学生帐号信息表单页面
     * 
     * @param int $uid
     *            学生id
     */
    public function edit($uid)
    {
        if (! $this->check_power('statistics_manage')) return;
        if (! $this->check_power('account_manage')) return;
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
        $data['act'] = 'edit';
        $query = array();
        $search = array();
        $query['tr_uid'] = $uid;
        $query['tr_type'] = 2;
        
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $query['s_time'] = strtotime($_GET['begin_time'] . '00:00:59');
            $search['begin_time'] = $_GET['begin_time'];
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $query['e_time'] = strtotime($_GET['end_time'] . '23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }
        
        $query['order_by'] = "tr_createtime DESC";
        
        // 公共数据
        $total = TransactionRecordModel::transactionRecordListCount($query);
        
        /*
         * 分页读取数据列表，并处理相关数据
         */
        $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                $_GET['page']) : 1;
        $perpage = C('default_perpage_num');
        $list = array();
        if ($total)
        {
            $list = TransactionRecordModel::transactionRecordList('*', $query, $page, $perpage);
        }
        
        $data['list'] = $list;
        $data['uid'] = $uid;
        $data['search'] = $search;
        
        // 分页
        $purl = site_url('admin/account/edit/' . $uid) . ($search ? '?' . implode('&',$search) : '');
        $data['pagination'] = multipage($total, $perpage, $page, $purl);
        
        // 模版
        $this->load->view('account/edit', $data);
    }

    /**
     * 按用户查询产品交易信息
     * @param int $uid
     *            学生id
     */
    public function transaction($uid)
    {
        if (! $this->check_power('statistics_manage')) return;
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
            $query['s_time'] = strtotime($_GET['begin_time'] . '00:00:59');
            $search['begin_time'] = $_GET['begin_time'];
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $query['e_time'] = strtotime($_GET['end_time'] . '23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }
        
        $query['order_by'] = "tr_createtime DESC";
        
        // 公共数据
        $total = TransactionRecordModel::transactionRecordListCount($query);
        /*
         * 分页读取数据列表，并处理相关数据
         */
        $perpage = C('default_perpage_num');
        $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                $_GET['page']) : 1;
        $list = array();
        if ($total)
        {
            $list = TransactionRecordModel::transactionRecordList('*', $query, $page, $perpage);
        }
        
        $data['list'] = $list;
        $data['uid'] = $uid;
        $data['search'] = $search;
        
        // 分页
        $purl = site_url('admin/account/transaction/' . $uid);
        $data['pagination'] = multipage($total, $perpage, $page, $purl);
        // 模版
        $this->load->view('account/transaction', $data);
    }
    
    
    /**
     * @按产品查询交易数据信息
     * @param int $p_id 产品id
     */
    public function transactionp($p_id)
    {
        if (! $this->check_power('statistics_manage')) return;

        $p_id = intval($p_id);
        $student = CommonModel::get_product_list($p_id);
        if (empty($student))
        {
            message('信息不存在');
            return;
        }
        $admin = $this->session->userdata('admin_id');
        $managers = explode(',',$student['p_managers']);
        if(!in_array($admin, $managers)&&!$this->is_super_user())
        {
            message('没有管理权限');
            return;
        }
        /*
         * 基本信息
         */
        $p_id = intval($p_id);
        $student = CommonModel::get_product_list($p_id);
        if (empty($student))
        {
            message('信息不存在');
            return;
        }
        $data = array();
        $data['p_id'] = $p_id;
        $data['p_name'] = $student[p_name];
        $query = array();
        $query['p_id'] = $p_id;
        $query['pt_type'] = 0;
        /* 搜索条件 */
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $query['pt_u_time >='] = strtotime($_GET['begin_time'] . '00:00:59');
            $search['begin_time'] = $_GET['begin_time'];
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $query['pt_u_time <='] = strtotime($_GET['end_time'] . '23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }
        // 公共数据
        $res = CommonModel::get_list($query, 'v_trans_log', 
                'count(pt_id) as pt_id_count,sum(pt_money)*(-1) as pt_money_count');
        $data['p_id_count'] = $res[0]['pt_id_count'];
        $data['p_money_count'] = (!empty($res[0]['pt_money_count'])) ? $res[0]['pt_money_count'] :0;
        $total = $res[0]['pt_id_count'];
        /*
         * 分页读取数据列表，并处理相关数据
         */
        $size = 10;
        $page = isset($_GET['page']) && intval($_GET['page']) > 1 ? intval(
                $_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $res = CommonModel::get_list($query, 'v_trans_log', 
                    'pt_id,p_name,pc_name,end_time,start_time,a_name,pt_money', 
                    $page, $size);
            foreach ($res as $row)
            {
                $row['start_time'] = ($row['start_time'] > 0) ? date(
                        'Y-m-d H:i:s', $row['start_time']) : date('Y-m-d H:i:s', 
                        time());
                $row['end_time'] = ($row['end_time'] > 0) ? date('Y-m-d H:i:s', 
                        $row['end_time']) : date('Y-m-d H:i:s', time());
                $row['p_name'] = (! empty($row['p_name'])) ? $row['p_name'] : '充值';
                $row['pt_money'] = (! empty($row['pt_money'])) ? $row['pt_money']*(-1) : '0';
                $row['pc_name'] = (! empty($row['pc_name'])) ? $row['pc_name'] : '充值';
                $list[] = $row;
            }
        }
        $data['list'] = $list;
        // 分页
        $purl = site_url('admin/account/transactionp/' . $p_id);
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['search'] = $search;
        // 模版
        $this->load->view('account/transactionp', $data);
    }

    /**
     * 产品交易数据信息
     */
    public function transactionw()
    {
        if (! $this->check_power('statistics_manage')) return;
        $search = array();
        $where = array();
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $where[] = 'tr_createtime >= ' . strtotime(
                    $_GET['begin_time'] . '00:00:00');
            $search['begin_time'] = $_GET['begin_time'];
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $where[] = 'tr_createtime <= ' . strtotime(
                    $_GET['end_time'] . '23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }
        if (empty($where))
        {
            $sql_where = '';
        }
        else
        {
            $sql_where = ' AND ' . implode(' AND ', $where);
        }
        
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1)
        {
            $page = 1;
        }
        $perpage = C('default_perpage_num');
        $offset = ($page - 1) * $perpage;

        $sql = <<<EOT
SELECT b.p_id, b.p_name, b.p_price,  b.p_price_pushcourse, c.pc_id, c.pc_name , 
    SUM(tr_trade_amount) AS tr_sum, COUNT(tr_id) AS tr_num
FROM rd_product b
LEFT JOIN rd_product_category c ON b.pc_id = c.pc_id
LEFT JOIN t_transaction_record a ON a.tr_pid = b.p_id AND a.tr_type = 3 {$sql_where}
GROUP BY b.p_id
ORDER BY b.p_id DESC
LIMIT {$perpage} OFFSET {$offset}
EOT;

        $db = Fn::db();
        $total = $db->fetchOne("SELECT COUNT(*) AS cnt FROM rd_product");
        if ($total)
        {
            $list = $db->fetchAll($sql);
        }
        
        $data['list'] = $list;
        $data['search'] = $search;

        // 分页
        $purl = site_url('admin/account/transactionw/');
        $data['pagination'] = multipage($total, $perpage, $page, $purl);
        $data['search'] = $search;
        // 模版
        $this->load->view('account/transactionw', $data);
    }

   /**
     *@description 产品交易明细信息
     */
    public function transactiond()
    {
        if (! $this->check_power('statistics_manage')) return;

        $search = array();
        $where = array();
        if (isset($_GET['p_id']) && ! empty($_GET['p_id']))
        {
            $where[] = 'tr_pid = ' . intval($_GET['p_id']);
            $search['p_id'] = $_GET['p_id'];
        }
        
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $where[] = 'tr_createtime >= ' . strtotime($_GET['begin_time'] . '00:00:00');
            $search['begin_time'] = $_GET['begin_time'];
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $where[] = 'tr_createtime <= ' . strtotime($_GET['end_time'] . '23:59:59');
            $search['end_time'] = $_GET['end_time'];
        }
        if (empty($where))
        {
            $sql_where = '';
        }
        else
        {
            $sql_where = ' AND ' . implode(' AND ', $where);
        }

        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1)
        {
            $page = 1;
        }
        $perpage = C('default_perpage_num');
        $offset = ($page - 1) * $perpage;


        $sql = <<<EOT
SELECT p_name, pc_name, tr_uid, exam_ticket, CONCAT(last_name, first_name) AS fullname, email, 
p_price, p_price_pushcourse, tr_trade_amount, tr_createtime 
FROM v_transaction_record a
LEFT JOIN rd_student b ON a.tr_uid = b.uid
WHERE tr_type = 3 AND tr_pid IS NOT NULL {$sql_where}
ORDER BY tr_id DESC
LIMIT {$perpage} OFFSET {$offset}
EOT;

        $sql_cnt = <<<EOT
SELECT COUNT(*) AS cnt
FROM v_transaction_record a
WHERE tr_type = 3 AND tr_pid IS NOT NULL {$sql_where}
EOT;

        $db = Fn::db();
        $total = $db->fetchOne($sql_cnt);
        if ($total)
        {
            $list = $db->fetchAll($sql);
        }

        $data['list'] = $list;
        // 分页
        $purl = site_url('admin/account/transactiond/');
        $data['pagination'] = multipage($total, $perpage, $page, $purl);
        $data['search'] = $search;
        // 模版
        $this->load->view('account/transactiond', $data);
    } 

    /*
     * @description 导出产品交易报表
     * @param int begin_time 开始时间
     * @paramint end_time 结束时间
     */
    public function report() // {{{
    {
        if (! $this->check_power('statistics_manage')) return;
        $where = array();
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $title = $_GET['begin_time'] ;
            $where[] = 'tr_createtime >= ' . strtotime(
                    $_GET['begin_time'] . '00:00:00');
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $title .= '~' . $_GET['end_time'];
            $where[] = 'tr_createtime <= ' . strtotime(
                    $_GET['end_time'] . '23:59:59');
        }
        if (empty($where))
        {
            $sql_where = '';
        }
        else
        {
            $sql_where = ' AND ' . implode(' AND ', $where);
        }
        
        $sql = <<<EOT
SELECT b.p_id, b.p_name, b.p_price,  b.p_price_pushcourse, c.pc_id, c.pc_name , 
    SUM(tr_trade_amount) AS tr_sum, COUNT(tr_id) AS tr_num
FROM rd_product b
LEFT JOIN rd_product_category c ON b.pc_id = c.pc_id
LEFT JOIN t_transaction_record a ON a.tr_pid = b.p_id AND a.tr_type = 3 {$sql_where}
GROUP BY b.p_id
ORDER BY b.p_id DESC
EOT;

        $db = Fn::db();
        $list = $db->fetchAll($sql);
        $data['list'] = $list;

        if (!empty($title))
        {
            $title .= '产品交易数据';            
        }
        else
        {
            $title .= '全部产品交易数据';
        }
        $excel_model = new ExcelModel();
        $data['header'] = $excel_model->getExcelHeader($title, 6, count($list) + 1);
        $data['footer'] = $excel_model->getExcelFooter();
        Func::dumpExcel($title . '.xls');
        $this->load->view('account/report_excel', $data);
    }//}}}

   /*
     * @description 导出产品交易报表
     * @param int begin_time 开始时间
     * @paramint end_time 结束时间
     */
    public function detail_report() // {{{
    {
        if (! $this->check_power('statistics_manage')) return;
        $where = array();
        if (isset($_GET['p_id']) && ! empty($_GET['p_id']))
        {
            $where[] = 'tr_pid = ' . intval($_GET['p_id']);
        }
        
        if (isset($_GET['begin_time']) && ! empty($_GET['begin_time']))
        {
            $title = $_GET['begin_time'] ;
            $where[] = 'tr_createtime >= ' . strtotime($_GET['begin_time'] . '00:00:00');
        }
        if (isset($_GET['end_time']) && ! empty($_GET['end_time']))
        {
            $title .= '~' . $_GET['end_time'] ;
            $where[] = 'tr_createtime <= ' . strtotime($_GET['end_time'] . '23:59:59');
        }
        if (empty($where))
        {
            $sql_where = '';
        }
        else
        {
            $sql_where = ' AND ' . implode(' AND ', $where);
        }

        $sql = <<<EOT
SELECT p_name, pc_name, tr_uid, exam_ticket, CONCAT(last_name, first_name) AS fullname, email, 
p_price, p_price_pushcourse, tr_trade_amount, tr_createtime 
FROM v_transaction_record a
LEFT JOIN rd_student b ON a.tr_uid = b.uid
WHERE tr_type = 3 AND tr_pid IS NOT NULL {$sql_where}
ORDER BY tr_id DESC
EOT;

        $db = Fn::db();
        $list = $db->fetchAll($sql);
        $data['list'] = $list;

        if (!empty($title))
        {
            $title .= '产品交易明细数据';            
        }
        else
        {
            $title .= '全部产品交易明细数据';
        }

        $excel_model = new ExcelModel();
        $data['header'] = $excel_model->getExcelHeader($title, 6, count($list) + 1);
        $data['footer'] = $excel_model->getExcelFooter();
        Func::dumpExcel($title . '.xls');
        $this->load->view('account/detail_report_excel', $data);
    }//}}}
  
    
    /**
     * @description 用户帐号充值
     * @param int $id 学生id
     */
    public function load_reset_account($id = 0)
    {
        if (! $this->check_power('statistics_manage')) return;
    	if ( ! $this->check_power('account_manage')) return;
    	$id = intval($id);
    	if (!$id) {
    		message('不存在该学生.');
    	}
    	 
    	$student = CommonModel::get_student($id);
    	if (!count($student)) {
    		message('不存在该学生.');
    	}
    	 
    	$data['uid'] = $id;
    	$this->load->view('account/reset_account', $data);
    }

    /**
     * ription 用户帐号充值处理
     * 
     * @param int $account_in_out
     *            增加/减少
     * @param int $txt_account
     *            数量
     * @param int $uid
     *            用户id
     * @param string $tex_memo
     *            备注
     */
    public function reset_account()
    {
        if (! $this->check_power('account_manage')) return;
        
        $account_in_out = intval($this->input->post('account_in_out'));
        $txt_account = intval($this->input->post('txt_account'));
        
        $uid = intval($this->input->post('uid'));
        $tex_memo = $this->input->post('tex_memo');
        
        // 检查是否存在该学生
        $account = CommonModel::get_student($uid, 
                'account,account_status');
        
        if (!$account)
        {
            output_json(CODE_ERROR, '不存在该学生.');
        }
        elseif ($account['account_status'])
        {
            output_json(CODE_ERROR, '学生帐号已被冻结');
        }
        
        $account = $account['account'];
        $vc = C('virtual_currency');
        if ($account_in_out == 2)
        {
            $account = $account - $txt_account;
            if ($account < 0)
            {
                output_json(CODE_ERROR, '学生帐号余额不足');
            }
            
            $txt_account = - $txt_account;
        }
        else
        {
            $account += $txt_account;
        }
        
        $insert_data = array(
                'tr_uid' => $uid,
                'tr_type' => 2,
                'tr_flag' => 1,
                'tr_comment' => $tex_memo,
                'tr_money'   => $account,
                'tr_trade_amount' => $txt_account,
                'tr_adminid' => $this->session->userdata('admin_id'),
        );
        
        $db = Fn::db();
        
        if ($db->beginTransaction())
        {
            TransactionRecordModel::addTransactionRecord($insert_data);
            
            // 修改学生帐号资金
            CommonModel::reset_account($uid, $account);
            
            $flag = $db->commit();
            if (!$flag)
            {
                $db->rollBack();
                output_json(CODE_ERROR, '帐号充值失败，请重试');
            }
            
            output_json(CODE_SUCCESS, '帐号充值成功.');
        }
        
        output_json(CODE_ERROR, '帐号充值失败，请重试');
    }

    /**
     * ription 用户帐号状态修改
     *
     * @param int $status
     *            增加/减少
     *            
     * @param int $uid
     *            用户id
     */
    public function reset_account_status()
    {
        if (! $this->check_power('account_manage')) return;
        $status = intval($this->input->post('status'));
        $uid = intval($this->input->post('uid'));
        // 检查是否存在该学生
        $account = CommonModel::get_student($uid, 
                'account,account_status');
        if (! count($account['account']))
        {
            output_json(CODE_ERROR, '不存在该学生.');
        }
        // 修改学生密码
        $flag = CommonModel::reset_status($uid, $status);
        if (! $flag)
        {
            output_json(CODE_ERROR, '帐号修改失败，请重试');
        }
        output_json(CODE_SUCCESS, '帐号修改成功.');
    }
    
    

}
