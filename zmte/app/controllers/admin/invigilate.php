<?php

// +------------------------------------------------------------------------------------------
// | Author: TCG
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 监考教师管理  Dates: 2015-08-29  
// +------------------------------------------------------------------------------------------

if (!defined('BASEPATH')) exit();

class Invigilate extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();

        // 回收
        require_once (APPPATH.'config/app/admin/recycle.php');
    }

    /**
     * 监考人员 列表
     *
     * @return  void
     */
    public function index()        
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
        // 查询条件
        $query  = array();
        $param  = array();
        $search = array();
        
        $page = (int) $this->input->get_post('page');
        $page = $page ? $page : 1;

        $per_page = (int) $this->input->get_post('per_page');
        $per_page = $per_page ? $per_page : 10;

        $order_bys = array(
            'email' => 'invigilator_email',
            'name' => 'invigilator_name',
            'memo' => 'invigilator_memo',
            'time' => 'invigilator_addtime',
        );

        $order = $this->input->get_post('order');
        !$order && $order = 'time';
        $search['order'] = $order;
        $param[] = "order={$order}";
        
        $order_type = $this->input->get_post('order_type');
        !$order_type && $order_type = 'desc';
        $search['order_type'] = $order_type;
        $param[] = "order_type={$order_type}";
        
        $order_by = $order_bys[$order] . ' ' . $order_type;
        $selectWhat = '*';

        //拼接查询条件
        $query_email = $this->input->get_post('email');
        if ($query_email)  {
            $query['invigilator_email'] = trim($query_email);
            $search['email'] = $query_email;
            $param[] = "email={$query_email}";
        }

        $query_name = $this->input->get_post('name');
       
        if ($query_name)  {
            $query['invigilator_name'] = trim($query_name);
            $search['name'] = $query_name;
            $param[] = "name={$query_name}";
        }

        $query_flag = $this->input->get_post('flag');
        if ($query_flag)  {
            $query['invigilator_flag'] = is_string($query_flag) ? (int) $query_flag : $query_flag;
            $search['flag'] = $query_flag;
            $param[] = "flag={$query_flag}";
        }

        $query_begin_time = $this->input->get_post('begin_time');
        if ($query_begin_time)  {
            $query['invigilator_addtime'] = array('>=' => strtotime($query_begin_time));
            $search['begin_time'] = $query_begin_time;
            $param[] = "begin_time={$query_begin_time}";
        }

        $query_end_time = $this->input->get_post('end_time');
        if ($query_end_time)  {
            if (! isset($query['invigilator_addtime'])) {
                 $query['invigilator_addtime'] = array();
            }
            
            $query['invigilator_addtime']['<='] = strtotime($query_end_time);
            $search['end_time'] = $query_end_time;
            $param[] = "end_time={$query_end_time}";
        }  
        
        $result = InvigilateModel::get_invigilator_list($query, $page, 
            $per_page, $order_by, $selectWhat);
        
        // 分页
        $purl = site_url('admin/invigilate/index/') . 
            (count($param) ? '?'.implode('&',$param) : '');

        $total = InvigilateModel::count_invigilator_lists($query);
        $data['pagination'] = multipage($total, $per_page, $page, $purl);

        $data['search'] = &$search;
        $data['list']   = &$result;
        
        //排序地址
        unset($param['order=']);
        unset($param['order_type=']);
        
        $order_url = site_url('admin/invigilate/index/') . (count($param) ? '?'.implode('&',$param) : '');
        $data['order_url'] = $order_url;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);
    	
        // 模版
        $this->load->view('invigilate/index', $data);
    }

    /**
     * 场次试卷添加操作页面
     *
     * @return  void
     */
    public function add()
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
        $data  = array();

        $data['mode'] = 'add';
        
       //$this->load->helper('cookie');
    //   $data['detail']= json_decode(get_cookie('invigilator_save'), true);
       // $data['detail']['email']=$email;

        // 模版
        $this->load->view('invigilate/add', $data);
    }

    /**
     * 保存添加记录
     *
     * @return  void
     */
    public function save()
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
        $invigilator_id = (int)$this->input->post('id');
        
        $invigilator = InvigilateModel::get_invigilator_by_id($invigilator_id, 'invigilator_id');

        if ($invigilator_id > 0 && !count($invigilator))
        {
            message('该监考人员帐号不存在', 'admin/invigilate/index');
            return;
        }
        
        $email = trim($this->input->post('invigilator_email'));
       
        $password = $this->input->post('password');
        
        $password_confirm =$this->input->post('password_confirm');
        
        $name = trim($this->input->post('invigilator_name'));
        
        $memo = trim($this->input->post('invigilator_memo'));
        
        $flag = $this->input->post('flag');
        
        $telephone = $this->input->post('telephone');  
        
        $cellphone = $this->input->post('cellphone'); 
        
       // $this->load->helper('cookie');    
     //   set_cookie('invigilator_save',json_encode($this->input->post()),3600,'','/');
        
        if (empty($email)) 
        {
            message('邮箱地址不能为空');
            exit;
        } 

        if (empty($name)) 
        {
            message('姓名/名称 不能为空');
            exit;
        } 

        if (empty($memo)) 
        {
            message('所在单位 不能为空');
            exit;
        }

        if (empty($cellphone)) 
        {
            message('手机号码 不能为空');
            exit;
        }

        if ($invigilator_id <= 0) 
        {
        	if (is_string($passwd_msg = is_password($password))) 
            {
        		message($passwd_msg);
        		return;
        	}

            if ($password != $password_confirm) 
            {
                message('两次密码输入不一致');
                return;
            }
        }        

        //检查用户名是否重复
        $queryArr = array(
            'invigilator_email' => $email        
        );

        if ($invigilator_id > 0) 
        {
            $queryArr['invigilator_id != '] = $invigilator_id;
        }

        $query = $this->db->select('invigilator_id')->get_where('invigilator', $queryArr);

        if ($query->num_rows())
        {
            message('该邮箱地址已存在！');
            return;
        }

        $flag = ! $flag ? '1' : $flag;
        $flag = $flag >= 1 ? 1 : $flag;
        
        $res = 0;

        if ($invigilator_id <= 0)
        {
            $insert_data = array(
                    'invigilator_email' => $email,
                    'invigilator_password' => my_md5($password),
                    'invigilator_name' => $name,
                    'invigilator_memo' => $memo,
                    'invigilator_flag' => '1',
                    'invigilator_addtime' => time(),
                    'invigilator_updatetime' => time(),
                    'telephone' => $telephone,
                    'cellphone' => $cellphone
            );
            $res = InvigilateModel::insert($insert_data);

        } 
        else 
        {
            //更新
            $update_data = array(
                    'invigilator_email' => $email,
                    'invigilator_name' => $name,
                    'invigilator_memo' => $memo,
                    'telephone' => $telephone,
                    'invigilator_updatetime' => time(),
                    'cellphone' => $cellphone
            );

            $res = InvigilateModel::update($invigilator_id, $update_data);
        }

        $back_url = "admin/invigilate/index";

        if ($res < 1) 
        {
            message('监考人员 保存失败', $back_url);
        } 
        else 
        {
            if ($invigilator_id > 0) 
            {
                admin_log('edit', 'invigilate', $invigilator_id);
            } 
            else 
            {
                admin_log('add', 'invigilate');
            }

            message('监考人员 保存成功', $back_url);
        }
    }

    /**
     * 编辑信息
     *
     * @return  void
     */
    public function edit($id)
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
        $data = array();
         
        $query = array('invigilator_id' => $id);
        $detail = InvigilateModel::get_invigilator_by_id($id);

        if (!$id || !count($detail)) 
        {
            message('不存在该监考人员信息');
            return;
        }

        $data['detail'] = $detail;
        $data['mode'] = 'edit';   

        $this->load->view('invigilate/add', $data);
    }

    /**
     * 删除彻底记录
     *
     * @return  void
     */
    public function batch_delete()
    {     
    	if (!$this->check_power('invigilate_manage')) return;
    	
        $ids = $this->input->post('id');

        if (empty($ids) OR ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        if (empty($back_url))
        {
            $back_url = 'admin/invigilate/index';
        }

        InvigilateModel::delete($ids, true);
        
        message('删除成功', $back_url);
    }

    /**
     * 禁用/回收站
     *
     * @return  void
     */
    public function do_action()
    {       
    	if (!$this->check_power('invigilate_manage')) return;
    	
        $id = $this->input->get_post('id');
       
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        $act = $this->input->get_post('act');

        $res = TRUE;

        if ($act == '0') 
        {
            //启用
            $res = InvigilateModel::update($id, array('invigilator_flag' => '1'));

        } 
        elseif ($act == '1') 
        {
            //禁用
            $res = InvigilateModel::update($id, array('invigilator_flag' => '0'));
        } 
        elseif ($act == '2') 
        {
            //回收站            
        	recycle_log_check($id);
        	
            $res = InvigilateModel::delete($id);

            $log_ids = is_string($id) ? $id : implode(', ', $id);

            admin_log('delete', 'invigilate', $id);
            
            recycle_log(RECYCLE_EXAM_INVIGILATOR, $id);

        } 
        elseif ($act == '3') 
        {
            //还原         
            $res = InvigilateModel::update($id, array('invigilator_flag' => '1'));

            $log_ids = is_string($id) ? $id : implode(', ', $id);

            admin_log('restore', 'invigilate', $id);
        }
        
        if ($res) 
        {
            message('操作成功', $back_url, 'success');
        } 
        else 
        {
            message('操作失败', $back_url);
        }
    }
    
    /**
     * 加载重置密码模板
     *
     * @return  void
     */
    public function load_reset_password($id = 0)
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
    	$id = intval($id);

    	if (!$id) 
        {
    		message('不存在该监考人员.');
    	}
    	
    	$invigilate = InvigilateModel::get_invigilator_by_id($id);

    	if (!count($invigilate)) 
        {
    		message('不存在该监考人员.');
    	}
    	
    	$data['uid'] = $id;

    	$this->load->view('invigilate/reset_password', $data);
    }
    
    /**
     * 重置密码
     *
     * @return  void
     */
    public function reset_password()
    {
    	if (!$this->check_power('invigilate_manage')) return;
    	
    	$new_password = $this->input->post('new_password');
    	$new_confirm_password = $this->input->post('confirm_password');
    	$invigilator_id = intval($this->input->post('uid'));
    	
    	if (is_string($passwd_msg = is_password($new_password))) {
    		output_json(CODE_ERROR, $passwd_msg);
    	}
    	
    	if (!strlen(trim($new_confirm_password))) {
    		output_json(CODE_ERROR, '确认密码不能为空.');
    	}
    	 
    	if ($new_confirm_password != $new_password) {
    		output_json(CODE_ERROR, '两次密码输入不一致.');
    	}
    	
    	//检查旧密码是否正确
    	$invigilater_passwd = InvigilateModel::get_invigilator_by_id($invigilator_id, 'invigilator_password');

    	if (!count($invigilater_passwd)) 
        {
    		output_json(CODE_ERROR, '不存在该监考人员.');
    	}
    	
    	//检查帐号密码是否正确
    	$flag = InvigilateModel::reset_invigilator_password($invigilator_id, my_md5($new_password));

    	if (!$flag) 
        {
    		output_json(CODE_ERROR, '密码修改失败，请重试');
    	}
    	 
    	output_json(CODE_SUCCESS, '密码修改成功.');
    }
}
