<?php if ( ! defined('BASEPATH')) exit();

class Production extends A_Controller
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
        $this->db->delete('student', array('is_auto'=>1));
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
            $sql="SELECT COUNT(*) AS count FROM  {pre}student WHERE uid='$uid' ";
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
     * ription 产品信息列表
     * 
     * @param int $admin_id
     *            管理员id
     * @param int $p_id
     *            产品id
     * @param int $pc_id
     *            产品分类id
     * @param string $keyword
     *            关键词
     */
    public function index($mode = '')
    {
        if (! $this->check_power_new('production_index')) return;
        // 查询条件
        $where = array();
        $param = array();
        $search = array();
        $query = array();
        $data = array();
        if ($search['admin_id'] = intval($this->input->get('admin_id')))
        {
            $param[] = "admin_id={$search['admin_id']}";
            $query['admin_id'] = intval($search['admin_id']);
        }
        
        if ($search['pc_id'] = intval($this->input->get('pc_id')))
        {
            $param[] = "pc_id={$search['pc_id']}";
            $query['pc_id'] = intval($search['pc_id']);
        }
        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $param[] = "keyword=" . urlencode($search['keyword']);
            $query['keyword'] = "CONCAT(p_name,pc_name,user_name) LIKE '%" .
                     $escape_keyword . "%'";
        }
        if ($search['p_id'] = intval($this->input->get('p_id')))
        {
            $query = array();
            $param[] = "p_id={$search['p_id']}";
            $query['p_id'] = intval($search['p_id']);
        }

        /*
         * 统计所有学生数量
         */
        $res = CommonModel::get_list($query, 'v_production', 
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
            $res = CommonModel::get_list($query, 'v_production', '*', 
                    $page, $size);
            foreach ($res as $row)
            {
                $row['p_c_time'] = ($row['p_c_time'] > 0) ? date('Y-m-d H:i:s', 
                        $row['p_c_time']) : date('Y-m-d H:i:s', time());
                $query = array();
                $query['p_id'] = $row['p_id'];
                $res = CommonModel::get_list($query, 
                        'v_trans_log_count', 'pt_id_count');
                $row['pt_id_count'] = $res[0]['pt_id_count'];
                $list[] = $row;
            }
        }
        $data['list'] = $list;
        // 分页
        $purl = site_url('admin/production/index/') .
                 ($param ? '?' . implode('&', $param) : '');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['admin_ids'] = CommonModel::get_admins('edit');
        $data['pc_ids'] = CommonModel::get_pc_ids();
        $data['search'] = $search;
        // 模版
        $this->load->view('production/index', $data);
    }


    
    /**
     * @description 添加产品信息表单页面
     */
    public function add()
    {
        if ( ! $this->check_power_new('production_add')) return;
        $data = array();
        $data['act']     = 'add';
        $data['admin_ids'] = CommonModel::get_admins('add');
        $data['admin_id'] = $this->session->userdata('admin_id');
        $data['pc_ids'] = CommonModel::get_pc_ids();
        $data['exam_ids'] = CommonModel::get_exam_ids('add');

        // 模版
        $this->load->view('production/edit', $data);
    }

    /**
     * ription 编辑产品信息表单页面
     * 
     * @param int $p_id
     *            产品id
     */
    public function edit($p_id)
    {
        if (! $this->check_power_new('production_edit')) return;
        /*
         * 基本信息
         */
        $p_id = intval($p_id);
        $pinfo = CommonModel::get_product_list($p_id);
        if (empty($pinfo))
        {
            message('信息不存在');
            return;
        }
        $admin = $this->session->userdata('admin_id');
        /* 不再需要该字段来限制权限了
        $managers = explode(',',$pinfo['p_managers']);
        if(!in_array($admin, $managers)&&!$this->is_super_user())
        {
            message('没有管理权限');
            return;
        }
         */
        $data = array();
        $data['pro'] = $pinfo;
        $data['act'] = 'edit';
        $data['admin_ids'] = CommonModel::get_admins('edit');
        $data['admin_id'] = $admin;
        $data['pc_ids'] = CommonModel::get_pc_ids();
        $data['exam_ids'] = CommonModel::get_exam_ids('edit')+array($pinfo['exam_pid']=>$pinfo['exam_name']);
        $this->load->view('production/edit', $data);
    }

    /**
     * ription 编辑产品信息保存数据库
     * 
     * @param $act 编辑类型[新增/更新]            
     * @param string $p_name
     *            产品名称
     * @param int $admin_id
     *            管理员id
     * @param int $pc_id
     *            产品分类id
     * @param int $exam_id
     *            期次id
     * @param int $p_status
     *            产品状态
     * @param int $p_money
     *            产品定价
     * @param   int $p_money_pushcourse
     * @param array $p_managers
     *            产品管理员
     * @param string $p_notice
     *            产品备注
     */
    public function update()
    {
        $act = $this->input->post('act') == 'add' ? 'add' : 'edit';
        $product = array();
        
        if ($act == 'add')
        {
            if (! $this->check_power_new('production_add'))
            {
                return;
            }

            $product['p_c_time'] = time();
            $product['p_admin'] = intval($this->session->userdata('admin_id'));
            /*
            if (is_array($this->input->post('p_managers')))
            {
                $product['p_managers'] = implode(',', $this->input->post('p_managers')).','.intval($this->session->userdata('admin_id'));
            }
            else 
             */
            {
                $product['p_managers'] = intval($this->session->userdata('admin_id'));
            }
        }
        else
        {
            if (! $this->check_power_new('production_edit'))
            {
                return;
            }

            $p_id = intval($this->input->post('p_id'));
            $old_product = CommonModel::get_product_list($p_id);
            if (empty($old_product))
            {
                message('产品信息不存在！');
                return;
            }
            /*
            $admin = $this->session->userdata('admin_id');
            $managers = explode(',',$old_product['p_managers']);
            if (!in_array($admin, $managers)&&!$this->is_super_user())
            {
                message('没有管理权限');
                return;
            }
             */
            $product['p_id'] = trim($this->input->post('p_id'));
            
            /*
            if(is_array($this->input->post('p_managers')))
            {
                $product['p_managers'] = implode(',', $this->input->post('p_managers'));
            }
            else
            {
                $product['p_managers'] = $old_product[p_admin];
            }
             */
        }
        $product['p_name'] = trim($this->input->post('p_name'));
        $product['pc_id'] = intval($this->input->post('pc_id'));
        $product['exam_pid'] = intval($this->input->post('exam_id'));
        $ip =ExamPlaceModel::get_exam_place($product['exam_pid'],'ip');
        if ($ip)
        {
                message('所选考试期次有IP限制，不可选为产品');
                return;
        }
        $product['p_status'] = intval($this->input->post('p_status'));
        $product['p_price'] = intval($this->input->post('p_money'));
        $product['p_price_pushcourse'] = intval($this->input->post('p_money_pushcourse'));
        $p_prefixinfo = $this->input->post('p_prefixinfo');
        if (!is_array($p_prefixinfo))
        {
            $p_prefixinfo = array();
        }
        $product['p_prefixinfo'] = implode(',', $p_prefixinfo);

        if (!Validate::isInt($product['p_price_pushcourse']) || $product['p_price_pushcourse'] < 0)
        {
            message('不推送课程价时价格不正确，必须为非负整数');
            return;
        }
        if (!Validate::isInt($product['p_price']) || $product['p_price'] < 0)
        {
            message('不推送课程价时价格不正确，必须为非负整数');
            return;
        }
        
        $product['p_notice'] = trim($this->input->post('p_notice'));
        $where = "p_id=$p_id";
        /*
         * 补充学生的所在区域 根据学生所选的学校更新学生所在地区
         */
        $this->db->trans_start();
        if ($act == 'add')
        {
            $result = Fn::db()->insert('rd_product', $product);
        }
        else
        {
            $result = Fn::db()->update('rd_product', $product, $where);
        }
        $this->db->trans_complete();
        if ($result>=0)
        {
            if ($act == 'add')
            {
                $msg = '产品信息添加成功';
                admin_log('add', 'product', $result['p_id']);
            }
            else
            {
                $msg = '产品信息修改成功';
                admin_log('edit', 'product', $p_id);
            }
            message($msg, 'admin/production/index', 'success');
        }
        else
        {
            if ($act == 'add')
            {
                $msg = '产品信息添加失败';
                admin_log('add', 'product', $result['p_id']);
            }
            else
            {
                $msg = '产品信息修改失败';
                admin_log('edit', 'product', $p_id);
            }
            message($msg, 'admin/production/index', 'fail');
        }
    }

    /**
     * ription 删除产品信息
     * 
     * @author
     *
     * @final
     *
     * @param int $p_id
     *            产品id
     */
    public function delete($p_id = 0)
    {
        if (! $this->check_power_new('production_del')) return;
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/product/index';
        $p_id = intval($p_id);
        $p_ids = CommonModel::get_product_list($p_id);
        if (empty($p_ids))
        {
            message('产品信息不存在', $back_url);
            return;
        }
        $admin = $this->session->userdata('admin_id');
        /*
        $managers = explode(',',$p_ids['p_managers']);
        if(!in_array($admin, $managers)&&!$this->is_super_user())
        {
            message('没有管理权限');
            return;
        }
         */
        $query = array();
        $query['p_id'] = $p_id;
        $res = CommonModel::get_list($query, 'v_trans_log_count', 
                'pt_id_count');
        $pt_id_count = $res[0]['pt_id_count'];
        if ($pt_id_count > 0)
        {
            message('产品存在交易记录', $back_url);
            return;
        }
        try
        {
            Fn::db()->delete('rd_product', "p_id=$p_id");
            admin_log('delete', 'product', $p_id);
            message('删除成功', $back_url, 'success');
        }
        catch (Exception $e)
        {
            message('删除失败', $back_url);
        }
    }

    
    /**
     * @description 批量删除产品信息
     * @author
     * @final
     * @param array $ids 产品id
     */

    public function batch_delete()
    {
        if ( ! $this->check_power_new('production_batch_delete')) return;
        $ids = $this->input->post('ids');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请选择要删除的项目！');
            return;
        }
        // 检查被关联试题
      
       
        $success = $fail = 0;
        foreach ($ids as $id)
        {
            
            $p_id = intval($id);
            $p_ids = CommonModel::get_product_list($p_id);
            $bool = true;
            if (empty($p_ids))
            {
                $bool = false;
               
            }
            $admin = $this->session->userdata('admin_id');
            /*
            $managers = explode(',',$p_ids['p_managers']);
            if(!in_array($admin, $managers)&&!$this->check_power_new('production_del'))
            {
                $bool = false;
            }
             */
            $query = array();
            $query['p_id'] = $p_id;
            $res = CommonModel::get_list($query, 'v_trans_log_count',
                    'pt_id_count');
            $pt_id_count = $res[0]['pt_id_count'];
            if ($pt_id_count > 0)
            {
                $bool = false;
            }
            
            
            if (!$bool) {
                $fail++;
                continue;
            }
    
            $num = Fn::db()->delete('rd_product', "p_id=$p_id");
            admin_log('delete', 'product', $p_id);
          
            if ($num > 0)
                $success += $num;
            else
                $fail++;
        }
        
        $back_url ='admin/prodution/index/';
        message('批量操作完成，成功删除：'.$success.' 个，失败：'.$fail.' 个。', 'javascript');
    }
}
