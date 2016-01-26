<?php if ( ! defined('BASEPATH')) exit();
class Cpuser extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @description    管理员列表
     * @author                
     * @final                 
     * @param int $trash 是否回收站
     * @param int $group_id
     */
    public function index()
    {   
    	$trash = intval($this->input->get('trash'));
    	$role = intval($this->input->get('role'));
    	
    	if ($trash)
    	{
    		if ( ! $this->check_power('admin_trash')) return;
    		
    		$this->session->set_userdata(array('admin_trash' => $trash));
    	}
    	else
    	{
    	    $this->session->unset_userdata('admin_trash');
    	    
    		if ( ! $this->check_power('cpuser_manage,cpuser_role,cpuser_delete,admin_log_list,admin_log_manage,admin_log_trash')) return;
    	}
        
        // 查询条件
        $where = array();    
       // $where_role = array();
        $param = array();
        $search = array();
       
        $search['trash'] = $trash;
        if ($trash)
        {
            $where[] = "is_delete=1";
            $param[] = "trash=1";
        }
        else
        {
            $where[] = "is_delete=0";
        }
        
        if ($search['group_id'] = intval($this->input->get('group_id')))
        {
            $where[] = "group_id='$search[group_id]'";
            $param[] = "group_id=$search[group_id]";            
        }
        $where_role='';
        if ($role>0)
        {
            $where_role = " AND admin_id in (SELECT DISTINCT admin_id FROM {pre}admin_role  WHERE  role_id='$role')";
            $param[] = "role=$role";
        }
        
        $where = $where ? implode(' AND ', $where) : ' 1 ';
        
        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}admin WHERE $where  $where_role";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        /*
		 *分页读取数据列表，并处理相关数据
         */
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        if ($total)
        {
            $sql = "SELECT * FROM {pre}admin 
                    WHERE $where $where_role ORDER BY admin_id ASC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            $list = array();
            foreach ($res->result_array() as $row) 
            {
                $row['last_login'] = $row['last_login'] ? date('Y-m-d H:i', $row['last_login']) : '未登录';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['subject_name'] = $row['subject_id'] == '-1' ? '全部学科' : C('subject/' . $row['subject_id']);
                $list[] = $row;
            }
        }
        
        $data['list']        = $list;
        $data['search']      = $search;
        $data['priv_delete'] = $this->check_power('cpuser_delete', FALSE);
        $data['priv_role']   = $this->check_power('cpuser_role', FALSE);
        $data['priv_import_cpuser']   = $this->check_power('import_cpuser', FALSE);

        // 分页
        $purl = site_url('admin/cpuser/index') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);
        
        // 模版
        $this->load->view('cpuser/index', $data);
    }
    
    /**
     * @description  添加管理员页面
     * @author
     * @final
     */
    public function add()
    {
        if ( ! $this->check_power('cpuser_manage')) return;
        
        /*
         * 获取学科
         */
        //$data['subjects'] = C('subject'); 
        
        /*
         * 获取角色列表
         */
        $data['role_list'] = RoleModel::get_role_list();
        
        // 模版
        $this->load->view('cpuser/add', $data);
    }

    /**
     * @description  编辑管理员页面
     * @author
     * @final
     * @param int $id 管理员id
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('cpuser_manage')) return;

        $id = intval($id);
        $id && $cpuser = CpUserModel::get_cpuser($id);
        if (empty($cpuser))
        {
            message('管理员不存在');
            return;
        }
        //$data['subjects'] = C('subject');
        
        $data['role_list']=RoleModel::get_role_list();
        
        /*
         * 获取管理员的角色节点
         */
        $old_data = $this->db->query("select role_id from {pre}admin_role where admin_id=".$id)->result_array();
    	$tmp_data = array();
    	foreach ($old_data as $val) {
    		$tmp_data[] = $val['role_id'];
    	}
    	$data['roleIds'] = $tmp_data;
    	
        $data['cpuser'] = $cpuser;
        $data['is_cpuser'] = $cpuser['admin_id'] == $this->session->userdata('admin_id');

        // 模版
        $this->load->view('cpuser/edit', $data);
    }
    
   /**
     * @description  添加管理员保持数据库操作
     * @author
     * @final
     * @param string $admin_user 用户名
     * @param string $password 密码
     * @param string $password_confirm 确认密码
     * @param string $email 邮箱
     * @param string $realname 真实姓名
     * @param array  $roleIds 角色
     */
    public function create()
    {
        if ( ! $this->check_power('cpuser_manage')) return;

        $data['admin_user'] = trim($this->input->post('admin_user'));
        
        if (empty($data['admin_user']) OR ! $this->input->post('password'))
        {
            message('用户名,密码不能为空！');
            return;
        }
        if (is_string($passwd_msg = is_password($this->input->post('password')))) {
        	message($passwd_msg);
        	return;
        }
        if ($this->input->post('password') !== $this->input->post('password_confirm'))
        {
            message('两次密码输入不一致，请确认。');
            return;
        }
        
        $realname = trim($this->input->post('realname'));
        if ($realname == '') {
            message('请填写真实姓名');
            return;
        }
        
        /*$subject_id = intval($this->input->post('subject_id'));
        if ($subject_id == '0') {
            message('请选择学科');
            return;
        }
         */
        
        $email = trim($this->input->post('email'));
        if (!is_email($email)) {
            message('请填写正确的邮箱');
            return;
        }
        
        $query = $this->db->select('admin_id')->get_where('admin', array('admin_user'=>$data['admin_user'],'is_delete'=>0));
        if ($query->num_rows())
        {
            message('用户名在管理员列表中已存在！');
            return;
        }
        
        $query = $this->db->select('admin_id')->get_where('admin', array('admin_user'=>$data['admin_user'],'is_delete'=>1));
        if ($query->num_rows())
        {
            message('用户名在管理员回收站中已存在！');
            return;
        }


        $query = $this->db->select('admin_id')->get_where('admin', array('email'=>$email));
        if ($query->num_rows())
        {
        	message('该邮箱已存在！');
        	return;
        }

        $data['action_list'] = '';
        $data['realname'] = $realname;
        $data['email'] = $email;
        $data['password'] = my_md5($this->input->post('password'));
        $data['addtime'] = time();
        $data['subject_id'] = 0;//$subject_id; 学科已转到权限管理里面去了
        $data['last_ip'] = '0.0.0.0';

        $db = Fn::db();
        if ($db->beginTransaction())
        {
            if ($db->insert('rd_admin', $data))
            {
                //插入角色
                $insert_id = $db->lastInsertId('rd_admin', 'admin_id');
                $roleIds = $this->input->post('roleIds');
                if (!empty($roleIds))
                {
                    foreach ($roleIds as $val)
                    {
                        $db->insert('rd_admin_role', 
                            array('admin_id' => $insert_id ,
                                'role_id' => $val));
                    }
                }
                if ($db->commit())
                {
                    admin_log('add', 'cpuser', $insert_id);
                    message('管理员添加成功', 'admin/cpuser/index');
                }
                else
                {
                    $err = $db->errorInfo()[2];
                    $db->rollBack();
                    message('管理员添加失败   ' . $err);
                }
            }
            else
            {
                $db->rollBack();
                message('管理员添加失败   ' . $db->errorInfo()[2]);
            }
        }
        else
        {
            message('管理员添加失败.开启事务处理失败');
        }
    }

    /**
     * @description  更新管理员保存数据库操作页面
     * @author
     * @final
     * @param int $admin_id 管理员id
     * @param string $admin_user 用户名
     * @param string $password 密码
     * @param string $password_confirm 确认密码
     * @param string $email 邮箱
     * @param string $realname 真实姓名
     * @param array  $roleIds 角色
     */
    public function update()
    {
        $this->check_power('cpuser_manage');
        $session = $this->session->userdata;
        //var_dump($session);die;
        if ( ! $session['is_super'])
        {
            message('您没有权限！');
            return;
        }
       // print_r($this->input->post());
       // die;
        $admin_id = intval($this->input->post('admin_id'));
        $is_super_self = $admin_id == $this->session->userdata('admin_id');
        $data['admin_user'] = trim($this->input->post('admin_user'));
        if (empty($data['admin_user']))
        {
            message('用户名不能为空！');
            return;
        }
        if ($this->input->post('password'))
        {        
        	if (is_string($passwd_msg = is_password($this->input->post('password')))) {
        		message($passwd_msg);
        	}
            if ($this->input->post('password') !== $this->input->post('password_confirm'))
            {
                message('两次密码输入不一致，请确认。');
            }
            $data['password'] = my_md5($this->input->post('password'));
        }
        
        $realname = trim($this->input->post('realname'));
        if ($realname == '') {
        	message('请填写真实姓名');
        	return;
        }
        
        /* $subject_id = intval($this->input->post('subject_id'));
        if (!$is_super_self && $subject_id == '0') {
        	message('请选择学科');
        	return;
        } */

        $email = trim($this->input->post('email'));
        if (!$is_super_self && !is_email($email)) {
        	message('请填写正确的邮箱');
        	return;
        }

        $query = $this->db->select('admin_id')->get_where('admin', array('admin_user'=>$data['admin_user'],'admin_id <>'=>$admin_id));
        if ($query->num_rows())
        {
            message('用户名已存在！');
        }
        
        $query = $this->db->select('admin_id')->get_where('admin', array('email'=>$email,'admin_id <>'=>$admin_id));
        if ($query->num_rows())
        {
        	message('该邮箱已存在！');
        	return;
        }

        $data['realname'] = $realname;
        $data['email'] = $email;
        //$data['subject_id'] = $subject_id;
        
        $roleIds = $this->input->post('roleIds');
        if(!empty($roleIds)){
        	//删除不存在的角色，插入新的
        	$delete_data = array();//不存在
        	$insert_data = array();//插入新的
        	$old_data = $this->db->query("select role_id from {pre}admin_role where admin_id=".$admin_id)->result_array();
	        $tmp_data = array();
	    	foreach ($old_data as $val) {
	    		$tmp_data[] = $val['role_id'];
	    	}
        	foreach ($tmp_data as $val) {
        		if (!in_array($val, $roleIds)) {
        			$delete_data[] = $val;
        		}
        	}
        	if (!empty($delete_data)){
        		$this->db->where_in('role_id', $delete_data)->delete('admin_role', array('admin_id'=>$admin_id));
        	}
        	foreach ($roleIds as $val) {
        		if (!in_array($val, $tmp_data)) {
        			$insert_data[]=array('admin_id' => $admin_id ,'role_id' => $val);
        		}
        	}
        	if (!empty($insert_data)){
        		$this->db->insert_batch('admin_role', $insert_data);
        	}
        }else{
        	$this->db->delete('admin_role', array('admin_id'=>$admin_id));
        }
        
        $this->db->update('admin', $data, array('admin_id' => $admin_id));
        admin_log('relate', 'cpuser', $admin_id);
        $trash = $this->session->userdata('admin_trash');
        message('修改成功', 'admin/cpuser/index' . ($trash ? "?trash=$trash" : ''));      
    }
    
   /**
     * @description  删除管理员
     * @author
     * @final
     * @param int $id 管理员id
     */
    public function delete($id = 0)
    {
        if ( ! $this->check_power('cpuser_delete')) return;
        
        $id = intval($id);
        $cpuser = CpUserModel::get_cpuser($id);

        if ( ! $this->session->userdata('is_founder') && $cpuser['is_super'])
        {
            message('您没有权限！');
            return;
        }
        if ($id == $this->session->userdata('admin_id'))
        {
            message('您不能删除自己！');
            return;
        }

        if ($cpuser['is_delete'])
        {
            $this->db->delete('admin', array('admin_id'=>$id));
            $this->db->delete('admin_role', array('admin_id'=>$id));
            admin_log('remove', 'cpuser', $id);
        }
        else
        {
            $this->db->update('admin', array('is_delete'=>1), array('admin_id'=>$id));
            admin_log('delete', 'cpuser', $id);
        }
        
        $trash = $this->session->userdata('admin_trash');
        
        message('删除成功', 'admin/cpuser/index/' . ($trash ? "?trash=$trash" : ''));        
    }

    /**
     * @description  还原管理员
     * @author
     * @final
     * @param int $id 管理员id
     */
    public function restore($id = 0)
    {
        if ( ! $this->check_power('cpuser_delete')) return;

        $id = intval($id);
        $cpuser = CpUserModel::get_cpuser($id);
        if (empty($cpuser))
        {
            message('管理员不存在');
            return; 
        }

        $this->db->update('admin', array('is_delete'=>0), array('admin_id'=>$id));
        admin_log('restore', 'cpuser', $id);

        message('还原成功', 'admin/cpuser/index/');
    }
    
    /**
     * @description  权限设置
     * @author
     * @final
     * @param int $id 管理员id
     */
    public function priv($id = 0)
    {
        if ( ! $this->check_power('cpuser_role')) return;
        
        $id = intval($id);
        $id && $cpuser = CpUserModel::get_cpuser($id);
        if ( ! $cpuser)
        {
            message('用户不存在');
            return;
        }
        if ($cpuser['admin_id']==$this->session->userdata('admin_id'))
        {
            message('您不能修改自己的权限');
        }
        if ($cpuser['is_super'])
        {
            message('该用户已经是超级管理员');
            return;
        }
        
        if ($this->input->post('dosubmit'))
        {
            $privs = $this->input->post('priv');
            if (empty($privs)) $privs = array();
            $arr = array();
            //pr($privs,1);
            foreach ($privs as $val)
            {
                $arr[] = implode(',', (array)$val);
            }
            
            //题库读写权限
            $r_action_type = intval($this->input->post('r_action_type'));
            $w_action_type = intval($this->input->post('w_action_type'));
            
            $r_action_type = ($r_action_type < 1 || $r_action_type > 3) ? 1 : $r_action_type;
            $w_action_type = ($w_action_type < 1 || $w_action_type > 3) ? 1 : $w_action_type;
            
            $action_type = array('question' => array('r' => $r_action_type, 'w' => $w_action_type));
            
            $this->db->update('admin', array('action_list'=>implode(',',$arr), 'action_type'=>serialize($action_type)), array('admin_id'=>$id));
            admin_log('edit', 'admin_priv', $id);
            message('权限设置成功', 'admin/cpuser/priv/'.$id);
        }
        else
        {
        	$data['action_type'] = array(
        			'1' => '自己创建',
        			'2' => '所在学科',
        			'3' => '所有学科',
        	);
            $cpuser['privs'] = explode(',', $cpuser['action_list']);
            $data['roles']   = C('roles', 'app/admin/roles');
            
            $action_type = @unserialize($cpuser['action_type']);
            $cpuser['action_type'] = is_array($action_type) ? $action_type : array();
            $data['user'] = $cpuser;
            
            // 模版
            $this->load->view('cpuser/priv', $data);
        }
    }

    /**
     * @description  修改管理员
     * @author
     * @final
     * @param string $realname 管理员真实姓名
     * @param string $email 邮箱
     * @param string $password 密码
     * @param string $password_confirm 确认密码
     * 
     */
    public function editpwd()
    {
        $admin_id = $this->session->userdata('admin_id');
        if ($admin_id == 1)
        {
            message('您没有修改自己密码权限');
            return;
        }
        if ($this->input->post('realname'))
        {
            $data['realname'] = trim($this->input->post('realname'));
            $data['email'] = trim($this->input->post('email'));
            if ($this->input->post('password'))
            {
            	if (is_string($passwd_msg = is_password($this->input->post('password')))) 
                {
            		 message($passwd_msg);
                     return;
            	}
                if ($this->input->post('password') !== $this->input->post('password_confirm'))
                {
                    message('两次密码输入不一致！');
                    return;
                }
                $data['password'] = my_md5($this->input->post('password'));
            }
            $this->db->update('admin', $data, array('admin_id'=>$admin_id));
            message('修改成功！');
        }
        else
        {
            $data['cpuser'] = CpUserModel::get_cpuser($admin_id);
            // 模版
            $this->load->view('cpuser/editpwd', $data);
        }
    }

    /**
     * @description  加载重置密码模板
     * @author
     * @final
     * @param int $id 管理员id
     */
    public function load_reset_password($id = 0)
    {
    	$admin_id = intval($id);
    	if (!$admin_id) {
    		message('不存在该管理员.');
    	}
    
    	$cpuser = CpUserModel::get_cpuser($admin_id);
    	if (!count($cpuser)) {
    		message('不存在该管理员.');
    	}
    
    	$data['uid'] = $id;
    	$this->load->view('cpuser/reset_password', $data);
    }
    
    /**
     * @description  重置密码
     * @author
     * @final
     * @param int $uid 管理员id
     * @param string $password 密码
     * @param string $password_confirm 确认密码
     */
    public function reset_password()
    {
    	$new_password = $this->input->post('new_password');
    	$new_confirm_password = $this->input->post('confirm_password');
    	$admin_id = intval($this->input->post('uid'));
    	 
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
    	$passwd = CpUserModel::get_cpuser($admin_id, 'password');
    	if (!count($passwd)) {
    		output_json(CODE_ERROR, '不存在该管理员.');
    	}
    
    	//检查帐号密码是否正确
    	$flag = $this->db->update('admin', array('password' => my_md5($new_password)), array('admin_id'=>$admin_id));
    	if (!$flag) {
    		output_json(CODE_ERROR, '密码修改失败，请重试');
    	}
    
    	output_json(CODE_SUCCESS, '密码修改成功.');
    }
    
    /**
     * @description  批量导入管理员
     * @author
     * @final
     */
    public function import()
    {
    	if ( ! $this->check_power('import_cpuser')) return;
        if ($_GET['dl'] == '1')
        {
            Func::dumpFile('application/vnd.ms-excel', 
                'file/import_adminuser_template.xlsx', 
                '后台管理员导入模板.xlsx');
            exit();
        }

    	$this->load->view('cpuser/import');
    }
    
    /**
     * @description  保存导入管理员[关联表:rd_admin]
     * @author
     * @final
     * @param file $_FIFLES['file'] excel文件
     */
    public function import_save()
    {
    	if ( ! $this->check_power('import_cpuser')) return;
    	
    	if (!$_FILES) message('请选择要上传的附件'); 
    	
    	/**
    	 * todo:
    	 * 	检查附件合法性
    	 *  解析附件(数据的合法性)
    	 *  组装插入数据(包括随机生成密码和帐号)
    	 *  插入或更新
    	 *  发送插入成功的帐号邮箱 进行修改密码提示 & 提示未插入成功的帐号 or 已经存在的帐号
    	 */
    	if ($_FILES)
    	{
    		$config['upload_path']   = realpath(_ADMIN_UPLOAD_ROOT_PATH_).'/cpuser/new_account/';
    		$config['allowed_types'] = 'xlsx|xls';//文件格式 xlsx|xls|csv|txt
    		$config['max_size']      = '1024';//文件大小
    		$config['file_name']  	 = date('Ymd(His)');//文件命名
    		//$this->load->library('upload', $config);
    	}
        else
        {
            message('没有上传文件');
        }

        if (isset($_FILES['file']))
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
                message($err_map[$_FILES['file']['error']]);
            }
            if (strpos($_FILES['file']['type'], 'excel') === false)
            {
                $mime = mime_content_type($_FILES['file']['tmp_name']);
                if (!in_array($mime, array('application/vnd.ms-excel', 
                    'application/vnd.ms-office', 
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')))
                {
                    message("您上传的不是Excel文件($mime)");
                }
            }
        }
        else
        {
            message('没有上传文件');
        }
    	
    	// 试题图片
            /*
		if ($this->upload->do_upload('file'))
		{
			$filepath = $this->upload->data('file_relative_path', 'admin_file');
		}
		else
		{
			message($this->upload->display_errors()); 
		}
             */
    	
        $filepath = $_FILES['file']['tmp_name'];

    	//解析附件(数据的合法性)
    	$data = array();
    	$data = $this->_validate_file_excel($filepath);
    	
    	$this->_message($data, '上传失败, 可能原因：上传的文件格式不正确,请重试.');
    	
    	//组装插入数据(包括随机生成密码和帐号)
    	$data = $this->_general_data($data['data']);


    	$this->_message($data, '上传失败, 可能原因：文件数据有问题.');
    	
    	//插入或更新
    	$data = $this->_insert_data($data['data']);
    	//发送插入成功的帐号邮箱 进行修改密码提示
    	//插入成功
    	$success_data = $data['success'];
    	$mail_data = $this->_mail($success_data);
    	
    	$exist_data = $data['exist'];//已经存在
    	$fail_data = $data['fail'];//插入失败
    	
    	//输出错误信息
    	$this->_message_error($success_data, $fail_data, $exist_data, $mail_data);
    }
    
    /**
     * @description 输出信息
     * @param array $data
     * @param string $default_msg
     */
    private function _message($data, $default_msg = '')
    {
    	if (!isset($data['code']) || $data['code'] == '-1') {
    		if (isset($data['msg']) && count($data['msg'])) {
    			$msg = is_array($data['msg']) ? implode('<br/>', $data['msg']) : $data['msg'];
    			message($msg);
    		} else {
    			message($default_msg);
    		}
    	}
    }
    
   /**
    * @description 获取导入成功的管理员
    * @param array $success_data
    * @return mixed list<map<string, variant>>类型
    */
    private function _mail(&$success_data = array())
    {
    	if (!is_array($success_data) || !count($success_data)) {
    		return false;
    	}
    	
    	//获取插入成功的人员列表
    	$emails = array_keys($success_data);
    	$email_sql = array();
    	foreach($emails as $item) {
    		$email_sql[] = "'$item'";
    	}
    	$email_sql = implode(', ', $email_sql);
    	$limit = count($emails);
    	
    	$admin_ids = array();
    	$old_emails = $this->db->query("select admin_id,email from {pre}admin where email in($email_sql) limit 0, $limit")->result_array();
    	foreach ($old_emails as $row) {
    		$success_data[$row['email']]['admin_id'] = $row['admin_id'];
    	}
    	
    	return $success_data;
    }
    
    /**
     * @description 批量处理发送邮件
     * @param string $mail_data 发送邮件的JSON数据串
     */
    public function batch_send_email()
    {
    	if ( ! $this->check_power('import_cpuser')) return;
    	
    	$mail_data = json_decode(urldecode($this->input->post('mail_data')));
    	$current = intval($this->input->post('current'));
    	
    	if (!is_array($mail_data) || !count($mail_data)) {
    		output_json(CODE_SUCCESS, '');
    	}
    	
    	// 循环发送邮件
    	$success_deal = array();
    	$count_deal = 0;
    	$deal_limit = 5;
    	$email_tpl = C('email_template/import_cpuser_success');
    	foreach ($mail_data as $k=>$row) {
    		$row = (Array) $row;
    		$mail = array(
    				'admin'	=> $row,
    				'hash'  => admin_email_hash('encode', $row['admin_id']),
    		);
    		
    		send_email($email_tpl['subject'], $this->load->view($email_tpl['tpl'], $mail, TRUE), $row['email']);
    		$count_deal++;
    		$current++;
    		
    		$success_deal[] = "<p>姓名：" . $row['realname'] . "， 邮箱：" . $row['email'] . "， 角色：" . $row['role_name'] . "</p>";
    		unset($mail_data[$k]);
    		
    		if ($count_deal >= $deal_limit) {
    			break;
    		}
    	}
    	
    	if (!count($mail_data)) {
    		output_json(CODE_SUCCESS, 'done', array('mail_data' => array(), 'deal_limit' => $deal_limit, 'current' => $current, 'now' => time(), 'success_deal' => implode('', $success_deal)));
    	}
    	
    	output_json(CODE_SUCCESS, '', array('mail_data' => urlencode(json_encode(array_values($mail_data))), 'deal_limit' => $deal_limit, 'current' => $current-1, 'now' => time(), 'success_deal' => implode('', $success_deal)));
    }
    
   /**
    * @description 输出错误信息
    * @param array $success_data 插入成功的
    * @param array $fail_data 插入失败的
    * @param array $exist_data 已经存在的
    */
    private function _message_error($success_data = array(), $fail_data = array(), $exist_data = array(), $mail_data = array())
    {
    	$msg = array();
    	if (count($success_data)) {
    		$msg[] = "<strong style='color:green;'>以下帐号导入成功(共 " . count($success_data) . " 个)：</strong>";
    		foreach ($success_data as $val) {
    			$msg[] = "<font style='color:green;'>-->姓名：" . $val['realname'] ."， 邮箱：" . $val['email'] . '， 角色：' . $val['role_name'] . '</font>';
    		}
    	}
    	if (count($fail_data)) {
    		$msg[] = "以下帐号导入失败(共 " . count($fail_data) . " 个)：";
    		foreach ($fail_data as $val) {
    			$msg[] = "-->姓名：" . $val['realname'] ."， 邮箱：" . $val['email'] . '， 角色：' . $val['role_name'];
    		}
    	}
    	if (count($exist_data)) {
    		$msg[] = "<strong style='color:blue;'>以下帐号已经存在,导入失败(共 " . count($exist_data) . " 个)：</strong>";
    		foreach ($exist_data as $val) {
    			$msg[] = "<font style='color:blue;'>-->姓名：" . $val['realname'] ."， 邮箱：" . $val['email'] . '， 角色：' . $val['role_name'] . '</font>';
    		}
    	}
    	if (count($success_data) && count($mail_data)) {
    		output_json(CODE_SUCCESS, implode('<br/>', $msg), array('mail_data' => urlencode(json_encode(array_values($mail_data))), 'total' => count($mail_data), 'deal_limit' => 5, 'now' => time()));
    	} else {
	    	$data = array('code' => CODE_ERROR, 'msg' => $msg);
	    	$this->_message($data);
    	}
    }
    
    /**
     * @description 删除文件
     * @param string $filename 待删除文件
     */
    private function _remove_file($filename)
    {
    	@unlink($filename);
    }
    
    /**
     * 处理excel数据
     * @param string $filename 待处理文件
     * @return mixed list<map<string, variant>>类型
     */
    private function _validate_file_excel($filename)
    {
    	//加载PHPExcel类
    	$this->load->library('PHPExcel');
    	$this->load->library('PHPExcel/IOFactory');
    	
    	/**  Identify the type of $inputFileName  **/
    	$inputFileType = IOFactory::identify($filename);
    	
    	/**  Create a new Reader of the type that has been identified  **/
    	$objReader = IOFactory::createReader($inputFileType);
    	/**  Load $inputFileName to a PHPExcel Object  **/
    	$objPHPExcel = $objReader->load($filename);
    	
    	$sheet = $objPHPExcel->getSheet(0);
    	$highestRow = $sheet->getHighestRow(); // 取得总行数
    	$highestColumn = $sheet->getHighestColumn(); // 取得总列数

    	$code = CODE_SUCCESS;
    	$msg = array();
    	$data = array();
    	$cache_data = array();
    	$empty_data = array();
    	$invalid_data = array();
    	$repeat_data = array();//重复邮箱
    	$invalidate_subject_data = array();//无效学科
    	
    	//判断表头是否正确
    	if (trim($objPHPExcel->getActiveSheet()->getCell("A1")->getValue()) != '姓名' 
    		|| trim($objPHPExcel->getActiveSheet()->getCell("B1")->getValue()) != '邮箱'
    		|| trim($objPHPExcel->getActiveSheet()->getCell("C1")->getValue()) != '角色')
    	{
            return array('code' => CODE_ERROR, 'msg' => '表头字段不正确，必须为：姓名   邮箱  角色 (先后顺序不能跌倒)');
    	}
    	
    	//忽略表头
    	//$subjects = $this->_get_reversed_subjects();
        $roles = Fn::db()->fetchPairs("SELECT role_id, role_name FROM rd_role");
        if ($roles)
        {
            $roles = array_flip(array_map("trim", $roles));
        }
        else
        {
            $roles = array();
        }
    	for($i = 2;$i <= $highestRow; $i++)
    	{
    		$realname = trim($objPHPExcel->getActiveSheet()->getCell("A".$i)->getValue());//第一列姓名
    		$email = trim($objPHPExcel->getActiveSheet()->getCell("B".$i)->getValue());//第二列邮箱
    		$role_name = trim($objPHPExcel->getActiveSheet()->getCell("C".$i)->getValue());//第三列角色
    		
    		//跳过全空行
                if ($realname == '' && $email == '' && $role_name == '')
                {
    		    continue;
    		}
    		
    		//不能为空验证
                if ($realname == '' || $email == '' || $role_name == '')
                {
                    $empty_data[] = array('line_number' => $i);	
    		}
    		
    		//角色验证
    		$role_id_arr = array();
                $role_name_arr = array_unique(array_map("trim", explode(',', $role_name)));
                $role_name_arr2 = array();
                foreach ($role_name_arr as $v)
                {
                    if ($v == '')
                    {
                        continue;
                    }
                    $role_name_arr2[] = $v;
                }
                unset($role_name_arr);
                if (empty($role_name_arr2))
                {
    		    $invalidate_subject_data[] = array('email' => $email, 'line_number' => $i, 'role_name' => $role_name);	
                }
                else
                {
                    foreach ($role_name_arr2 as $v)
                    {
                        if (!isset($roles[$v]))
                        {
    		            $invalidate_subject_data[] = array('email' => $email, 'line_number' => $i, 'role_name' => $role_name);	
                            break;
                        }
                        $role_id_arr[] = $roles[$v];
                    }
                }
    		
    		//重复邮箱验证
    		if (isset($cache_data[$email])) {
    			!isset($repeat_data[$email]) && $repeat_data[$email] = array('current_line' => '', 'count' => '0', 'line_numbers' => '');
    			$repeat_data[$email]['current_line'] = $cache_data[$email]['line_number'];
    			$repeat_data[$email]['count']++;
    			$repeat_data[$email]['line_numbers'][] = $i;
    		}
    		
    		//邮件合法性验证
    		if (!is_email($email)) {
				$invalid_data[] = array('email' => $email, 'line_number' => $i);	
    			continue;
    		} 
    		
			$cache_data[$email] = array('line_number' => $i);	
			
    		$data[$email] = array('realname' => $realname, 'email' => $email, 'role_id' => $role_id_arr, 'role_name' => $role_name);
    	}
    	
    	if (count($empty_data) || count($invalid_data) || count($repeat_data) || count($invalidate_subject_data)) {
    		$code = CODE_ERROR; 
    		$msg[] = '导入失败，可能原因：<br/>';
    		if (count($empty_data)) {
	    		$msg[] = '<strong>以下行有未填信息，请调整后再试：</strong>';
	    		foreach ($invalid_data as $k=>$v) {
	    			$msg[] = '--》行号：' . $v['line_number'];
	    		}
	    		$msg[] = '<hr/>';
    		}
    		if (count($invalid_data)) {
	    		$msg[] = '<strong>以下邮箱格式不正确，请调整后再试：</strong>';
	    		foreach ($invalid_data as $k=>$v) {
	    			$msg[] = '--》行号：' . $v['line_number'] . ', 邮箱：' . $v['email'];
	    		}
	    		$msg[] = '<hr/>';
    		}
    		if (count($repeat_data)) {
	    		$msg[] = '<strong>以下邮箱格式有重复，请调整后再试：</strong>';
	    		foreach ($repeat_data as $k=>$v) {
	    			$msg[] = '--》与当前邮箱重复，当前行号：' . $v['current_line'] . ', 当前邮箱：' . $k . '，共有' . $v['count'] . '个邮箱与之重复，分别在行：' . implode(', ', $v['line_numbers']);
	    		}
	    		$msg[] = '<hr/>';
    		}
    		if (count($invalidate_subject_data)) {
	    		$msg[] = '<strong>以下帐号角色不正确(不存在以下角色, 请调整后再试：</strong>';
	    		foreach ($invalidate_subject_data as $k=>$v) {
	    			$msg[] = '--》行号：' . $v['line_number'] . ', 邮箱：' . $v['email'] . ', 角色：' . $v['role_name'];
	    		}
	    		$msg[] = '<hr/>';
    		}
    		
    		//删除附件
    		//$this->_remove_file($filename);
    	}
    	
    	$return_data = array(
    						'code' 	=> $code, 
    						'msg' 	=> $msg, 
    						'data' 	=> $data
    	);
    	return $return_data;
    }
    
    /**
     * @description 将学科按照subject_name=>subject_id归档
     * @return mixed list<map<string, variant>>类型
     */
    private function _get_reversed_subjects()
    {
    	$tmp_subjects = array();
    	$subjects = C('subject');
    	foreach ($subjects as $k=>$v)
    	{
    		$tmp_subjects[$v] = $k;
    	}

    	return $tmp_subjects;
    }
    
    /**
     * @description 组装插入数据(包括随机生成密码和帐号)
     * @param array $data 待处理用户数据
     */
    private function _general_data($data)
    {
    	/**
    	 * todo:
    	 * 	根据表rd_admin将补齐以下字段：
    	 * 		admin_user
    	 * 		password
    	 * 		addtime
    	 * 		last_ip
    	 */
    	$admin_user_interval = 5000;
    	$admin_user_prefix = 'zeming_import_';
    	$now = time();


    	//获取批量导入的管理员列表(按照用户名降序排)

        $sql = <<<EOT
select admin_user from rd_admin where `from`=2 order by admin_user desc limit 0,1
EOT;
    	$max_admin_user = Fn::db()->fetchRow($sql);
    	$max_admin_user = count($max_admin_user) ? $max_admin_user['admin_user'] : 0;
    	$admin_user_rand_min = intval($max_admin_user) + 10;
    	$admin_user_rand_max = $admin_user_rand_min + $admin_user_interval;
    	

    	$code = CODE_SUCCESS;
    	$msg = array();
    	foreach ($data as &$item) {
    		$item['admin_user'] = $admin_user_prefix . mt_rand($admin_user_rand_min, $admin_user_rand_max);
	    	$item['action_list'] = '';//$action_list;
	    	$item['action_type'] = '';//$action_type;
	    	
	    	$password = auto_general_password();
	    	$item['prototype_password'] = $password;
	    	$item['password'] = my_md5($password);
	    	
	    	$item['addtime'] = $now;
	    	$item['from'] = '2';
	    	$item['last_ip'] = '0.0.0.0';
    	}

    	return array('code' => $code, 'msg' => '', 'data' => $data);
    }
    
    /**
     * @description 插入或更新
     * @param array $data 待处理用户数据
     */
    private function _insert_data($data)
    {
    	if (!count($data)) {
    		return false;
    	}
    	
    	/**
    	 * todo :
    	 *	获取待入库所有邮箱，并查询数据库是否存在这些邮箱
    	 *  过滤已经存在的邮箱
    	 *  插入不在的邮箱帐号
    	 */
    	
    	$exist_data = array();//已存在
    	$success_data = array();//插入成功
    	$fail_data = array();//失败
    	
    	$emails = array_keys($data);
    	$email_sql = array();
    	foreach($emails as $item) {
    		$email_sql[] = '?';//"'$item'";
    	} 
    	$email_sql = implode(', ', $email_sql);
    	$limit = count($emails);
    	
    	$tmp_emails = Fn::db()->fetchCol("SELECT email FROM rd_admin WHERE email IN ($email_sql) LIMIT 0, $limit", $emails);
    	
    	$insert_data = array();
        foreach ($data as $k=>$val)
        {
            if (in_array($k, $tmp_emails))
            {
                $exist_data[] = $val;
                continue;
    	    }
    		
            $success_data[$val['email']]['realname'] = $val['realname'];
            $success_data[$val['email']]['email'] = $val['email'];
            //$success_data[$val['email']]['subject_name'] = C('subject/' . $val['subject_id']);
            $success_data[$val['email']]['admin_user'] = $val['admin_user'];
            $success_data[$val['email']]['role_name'] = $val['role_name'];
            $success_data[$val['email']]['prototype_password'] = $val['prototype_password'];
    		
            unset($val['prototype_password']);
    	    $insert_data[] = $val;
    	}
    	
        $admin_id_succ = array();
    	$success = false;
        if (count($insert_data))
        {
            try
            {
                $db = Fn::db();
                if ($db->beginTransaction())
                {
                    foreach ($insert_data as $row)
                    {
                        $role_id_arr = $row['role_id'];
                        unset($row['role_name']);
                        unset($row['role_id']);
                        $row['`from`'] = $row['from'];
                        unset($row['from']);
                        $row['subject_id'] = -1;
                        $db->insert('rd_admin', $row);
                        $admin_id = $db->lastInsertId('rd_admin', 'admin_id');
                        $admin_id_succ[] = $admin_id;
                        foreach ($role_id_arr as $role_id)
                        {
                            $db->insert('rd_admin_role', array('role_id' => $role_id,
                                'admin_id' => $admin_id));
                        }
                    }
                    $success = $db->commit();
                    if (!$success)
                    {
                        $db->rollBack();
                    }
                }
            }
            catch (Exception $e)
            {
                print_r($e->getMessage());
            }
    	}
    	
        if ($success)
        {
            admin_log('import', '', '批量导入管理员(ID: ' . implode(',', $admin_id_succ) . ')');
    	    return array('success' => $success_data, 'fail' => array(), 'exist' => $exist_data);
        }
        else
        {
    	    return array('success' => array(), 'fail' => $fail_data, 'exist' => $exist_data);
    	}
    }
}
