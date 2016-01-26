<?php if ( ! defined('BASEPATH')) exit();
class Production_category extends A_Controller 
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
     * @description 产品分类管理列表             
     * @param int $pc_id 产品分类id
     * @param int $keyword 关键字
     */
    
    public function index($mode = '')        
    {
		
    	if ( ! $this->check_power_new('production_category_index')) return;
    	
        // 查询条件
        $where = array();    
        $param = array();
        $search = array();
        $query = array();
        $data = array();

       
        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $param[] = "keyword=".urlencode($search['keyword']);
            $query['keyword'] = "pc_name LIKE '%".$escape_keyword."%'";
        }

        
      
        if ($search['pc_id'] = intval($this->input->get('pc_id')))
        {
            $query = array();
            $param[] = "pc_id={$search['pc_id']}";
            $query['pc_id'] = intval($search['pc_id']);
        }
    
        
        /*
         * 统计所有学生数量
         */
        $res = CommonModel::get_list($query,'rd_product_category','count(*) AS count');
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
            $res = CommonModel::get_list($query,'rd_product_category','*', $page, $size, 'pc_id desc');
            foreach ($res as $row) 
            {            
                $row['p_c_time'] = ($row['p_c_time']>0) ? date('Y-m-d H:i:s',$row['p_c_time']) :  date('Y-m-d H:i:s',time());
                $query = array();
                $query['pc_id'] = $row['pc_id'];
                $res = CommonModel::get_list($query,'v_trans_log_count','pt_id_count');
                $row['p_id_count'] = $res[0]['pt_id_count'];
                $list[] = $row;
            }
        }
        $data['list'] = $list;
        
        // 分页
        $purl = site_url('admin/production_category/index/') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

     
        $data['search'] = $search;
        // 模版
        $this->load->view('production_category/index', $data);
    }

    /**
     * ription 添加产品分类信息表单页面
     * 
     * @author
     *
     * @final
     *
     */
    public function add()
    {
        if (! $this->check_power_new('production_category_add')) return;
        $data = array();
        $data['act'] = 'add';
        // 模版
        $this->load->view('production_category/edit', $data);
    }

    /**
     * ription 编辑产品分类信息表单页面
     * 
     * @author
     *
     * @final
     *
     * @param int $pc_id
     *            产品分类id
     */
    public function edit($pc_id)
    {
        if (! $this->check_power_new('production_category_edit')) return;
        /*
         * 基本信息
         */
        $pc_id = intval($pc_id);
        $production_category = CommonModel::get_product_category_list(
                $pc_id);
        if (empty($production_category))
        {
            message('产品类别不存在');
            return;
        }
        $data = array();
        $data['pc'] = $production_category;
        $data['act'] = 'edit';
        $this->load->view('production_category/edit', $data);
    }

    /**
     * ription 编辑产品分类信息保存数据库
     * 
     * @author
     *
     * @final
     *
     * @param $act 编辑类型[新增/更新]            
     * @param int $pc_id
     *            产品分类id
     */
    public function update()
    {
        $act = $this->input->post('act') == 'add' ? 'add' : 'edit';
        $production_category = array(
            'pc_name' => trim($this->input->post('pc_name')),
            'pc_memo' => trim($this->input->post('pc_memo')));
        if ($production_category['pc_name'] == '')
        {
            message('产品分类名称不可为空！');
            return;
        }
        if ($act == 'add')
        {
            if (! $this->check_power_new('production_category_add')) return;

            $sql = <<<EOT
SELECT pc_id FROM rd_product_category WHERE pc_name = ?
EOT;
            $row = Fn::db()->fetchRow($sql, $production_category['pc_name']);
            if ($row)
            {
                message('产品分类"' . $production_category['pc_name'] 
                    . '"已存在,请换一个其它名称！');
                return;
            }
        }
        else
        {
            if (! $this->check_power_new('production_category_edit')) return;

            $pc_id = intval($this->input->post('pc_id'));

            $sql = <<<EOT
SELECT pc_name FROM rd_product_category WHERE pc_id = {$pc_id}
EOT;
            $row = Fn::db()->fetchRow($sql);
            if (!$row)
            {
                message('产品分类信息不存在！');
                return;
            }

            $sql = <<<EOT
SELECT pc_id, pc_name FROM rd_product_category 
WHERE pc_id <> {$pc_id} AND pc_name = ?
EOT;
            $row = Fn::db()->fetchRow($sql, $production_category['pc_name']);
            if ($row)
            {
                message('产品分类"' . $production_category['pc_name'] 
                    . '"已存在,请换一个其它名称！');
                return;
            }
            $production_category['pc_id'] = $pc_id;
        }
        $where = "pc_id=$pc_id";
        /*
         * 补充学生的所在区域 根据学生所选的学校更新学生所在地区
         */
        if ($act == 'add')
        {
            $result = Fn::db()->insert('rd_product_category', 
                    $production_category);
            if ($result)
            {
                $pc_id = Fn::db()->lastInsertId('rd_product_category', 'pc_id');
            }
        }
        else
        {
            $result = Fn::db()->update('rd_product_category', 
                    $production_category, $where);
        }
        if ($result > 0)
        {
            if ($act == 'add')
            {
                $msg = '产品分类信息添加成功';
                admin_log('add', 'production_category', $pc_id);
            }
            else
            {
                $msg = '产品分类信息修改成功';
                admin_log('edit', 'production_category', $pc_id);
            }
            message($msg, 'admin/production_category/index', 'success');
        }
        else
        {
            if ($act == 'add')
            {
                $msg = '产品分类信息添加失败';
                admin_log('add', 'product_category', 
                    $production_category['pc_name']);
            }
            else
            {
                $msg = '产品分类信息修改失败';
                admin_log('edit', 'product_category', $pc_id);
            }
            message($msg, 'admin/production_category/index', 'fail');
        }
    }
 
    /**
     * @description 删除产品分类信息
     * @author
     * @final
     * @param int $pc_id 产品分类id
     */
    public function delete($pc_id=0)
    {
        if ( ! $this->check_power_new('production_category_delete')) return;
        
        $back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin/product_category/index';

        $pc_id = intval($pc_id);
        $pc_ids = CommonModel::get_product_category($pc_id);
        if (empty($pc_ids))
        {
            message('产品分类信息不存在', $back_url);
            return;
        }
        $query = array();
        $query['pc_id'] = $pc_id;
        $res = CommonModel::get_list($query,'v_trans_log','count(pt_id) as pt_id_count');
        $pt_id_count = $res[0]['pt_id_count'];
       
        if ($pt_id_count>0)
        {
            message('产品分类存在交易记录', $back_url);
            return;
        }
        try
        { 
            Fn::db()->delete('rd_product_category', "pc_id=$pc_id");
            admin_log('delete', 'product_category', $pc_id);     
            message('删除成功', $back_url, 'success');
        }
        catch(Exception $e)
        {
            message('删除失败', $back_url);
        }
    }

    /**
     * @description 批量删除产品分类
     * @author
     * @final
     * @param array $ids 产品分类id
     */
    
    public function batch_delete()
    {
        if ( ! $this->check_power_new('production_category_batch_delete')) return;
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
    
            $pc_id = intval($id);
            $bool = true;
            
            $pc_ids = CommonModel::get_product_category($pc_id);
            if (empty($pc_ids))
            {
                $bool = false;
            }
            $query = array();
            $data = array();
            $query['pc_id'] = $pc_id;
            $res = CommonModel::get_list($query,'v_trans_log','count(pt_id) as pt_id_count');
            $pt_id_count = $res[0]['pt_id_count'];
           
          
            if ($pt_id_count>0)
            {
                $bool = false;
            }

            if (!$bool) {
                $fail++;
                continue;
            }
            $num = Fn::db()->delete('rd_product_category', "pc_id=$pc_id");
            admin_log('delete', 'product_category', $pc_id);
          
    
            if ($num > 0)
                $success += $num;
            else
                $fail++;
        }
        
        message('批量操作完成，成功删除：'.$success.' 个，失败：'.$fail.' 个。', 'javascript');
    
    }
}
