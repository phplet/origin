<?php
class Index extends A_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function index()
	{
		$this->load->view('index/index');
	}
	
	function main()
	{
		//系统信息
		$server['time'] = date('Y-m-d H:i:s', time());
		$server['upfile'] = (ini_get('file_uploads')) ? '允许 ' . ini_get('upload_max_filesize') : '关闭';
		$server['register_globals'] = (ini_get('register_globals')) ? '允许' : '关闭';
		$server['safe_mode'] = (ini_get('safe_mode')) ? '允许' : '关闭';
		$server['software'] = $_SERVER['SERVER_SOFTWARE'];
		$server['phpver'] = phpversion();
		$server['mysqlver'] = $this->db->version();

		$s = function_exists('gd_info') ? gd_info() : '<span class="font_1"><strong>Not Support</strong></span>';
		$server['gd'] = is_array($s) ? ($s['GD Version']) : $s;

		$data['server'] = $server;
		$this->load->view('index/main', $data);
	}
	
	public function zip()
	{
	    $dir = BASEPATH . '../81/';
	    $zip_dir = BASEPATH . '../81_zip/';
	    
	    $d = @dir($dir);
	    $files = array();
	    if ($d)
	    {
	        while (false !== ($entry = $d->read()))
	        {
	            if ($entry != '.' && $entry != '..')
	            {
	               $files[] = $entry;
	            }
	        }
	        $d->close();
	    }
	    
	    require_once APPPATH.'libraries/Pclzip.php';
	    
	    foreach ($files as $file)
	    {
	        $zip = new PclZip($zip_dir . $file . '.zip');
	        
	        $zip->create($dir . $file, PCLZIP_OPT_REMOVE_ALL_PATH);
	        
	    }
	}

	function menu( $tab='' )
	{
		$modules = C('menu', 'app/admin/menu');
		//print_r($modules);
		//die;
		$purview = C('purview');

		$menus   = array();
		$this->lang->load('admin/menus');

		foreach ($modules AS $key => $val)
		{
			$menus[$key]['label'] = $this->lang->line($key);
			if (is_array($val))
			{
				foreach ($val AS $k => $v)
				{
					if ( isset($purview[$k]))
					{
						if (is_array($purview[$k]))
						{
							$boole = FALSE;
							foreach ($purview[$k] as $action)
							{
								$boole = $boole || $this->check_power_new($action, FALSE);
							}

							if (!$boole)
							{
								continue;
							}

						}
						else
						{
							if (! $this->check_power_new($purview[$k], FALSE))
							{
								continue;
							}
						}
					}

					$menus[$key]['children'][$k]['label']  = $this->lang->line($k);
					$menus[$key]['children'][$k]['action'] = $v;
				}
			}
			else
			{
				$menus[$key]['action'] = $val;
			}

			// 如果children的子元素长度为0则删除该组
			if(empty($menus[$key]['children']))
			{
				unset($menus[$key]);
			}
		}
		//pr($menus,1);

		$showmenu = '';
		$items = 0;
		foreach ($menus as $folder)
		{
			$closeli = $items > 5 ? ' class="closed"' : '';
			$showmenu .= "<li{$closeli}>\r\n";
			$showmenu .= "\t<span class=\"folder\">$folder[label]</span>\r\n";
			$showmenu .= "\t\t<ul>\r\n";
			foreach($folder['children'] as $file) {
				$showmenu .= "\t\t\t".'<li><span class="file"><a href="'.site_url($file['action']).'" target="main">'.$file['label'].'</a></span></li>'."\r\n";
			}
			$showmenu .= "\t\t</ul>\r\n";
			$showmenu .= "</li>\r\n";
			$items++;
		}
		$data['showmenu'] = $showmenu;
		$this->load->view('index/menu', $data);
	}

	function top()
	{
		$modules = C('menu_nav', 'app/admin/menu');

		$purview = C('purview');
		//print_r($purview);
		$menu_nav   = array();
		$this->lang->load('admin/menus');
		foreach ($modules as $k => $v)
		{
			if ($this->check_power($v, FALSE))
			{
				$menu_nav[$k]['label'] = $this->lang->line($k);
				$menu_nav[$k]['action'] = $v;
			}
		}


		$menuNav = '';
		foreach($menu_nav as $tab => $value) {
			$menuNav .= "\t".'<li class="unselected"><a href="'.site_url($value['action']).'" onfocus="this.blur()" target="main">'.$value['label'].'</a></li>'."\r\n";
		}
		$data['menuNav'] = $menuNav;

		$data['exam_manage'] = $this->check_power('exam_manage',false);


		$this->load->view('index/top', $data);
	}

	function login()
	{
		if ($this->session->userdata('admin_id'))
			redirect('admin/index');

		$this->load->view('index/login');
	}

	function logout()
	{
		$this->session->sess_destroy();
		redirect('admin/index/login');
	}

	function check_login()
	{
		$username = $this->input->post('admin_name', TRUE);
		$password = $this->input->post('admin_password', TRUE);

		if (empty($username) || empty($password))
		{
			message('用户名或密码不能为空！');
			return;
		}
		$this->db->where('admin_user', $username);
		$this->db->where('password', my_md5($password));
		$this->db->trans_start();
		$query = $this->db->get('admin');
		if ($query->num_rows())
		{
			$user = $query->row_array();
			if ($user['is_delete'])
			{
				message('该帐号已被屏蔽');
				return;
			}

			if($user['is_super'] != 1)
			{
				//重组subject_id,action_list,action_type
				$sql = "SELECT b.role_id,b.subject_id,b.grade_id,b.q_type_id,b.action_list,b.action_type FROM {pre}admin_role a
					left join {pre}role b on a.role_id=b.role_id where a.admin_id=".$user['admin_id'];

				$res = $this->db->query($sql)->result_array();

				unset($user['action_list']);
				unset($user['action_type']);

				$temp_subject_id	= array();
				$admin_subject_id	= array();
				$temp_grade_id	= array();
				$admin_grade_id	= array();
				$temp_q_type_id	= array();
				$admin_q_type_id	= array();

				$temp_action_list	= array();
				$temp_action_type	= array();

				foreach ($res as $row)
				{
					if ( isset($row['subject_id']) )
					{
						$admin_subject_id[] = $row['subject_id'];
						$temp_subject_id[$row['role_id']][]  = $row['subject_id'];
					}

					if ( isset($row['grade_id']) )
					{
					    $admin_grade_id[] = $row['grade_id'];
					    $temp_grade_id[$row['role_id']][]  = $row['grade_id'];
					}

					if ( isset($row['q_type_id']) )
					{
					    $admin_q_type_id[] = $row['q_type_id'];
					    $temp_q_type_id[$row['role_id']][]  = $row['q_type_id'];
					}


					$temp_action_list[$row['role_id']][] = $row['action_list'];
					$temp_action_type[$row['role_id']] = @unserialize($row['action_type']);
				}

				if (in_array('-1', $admin_subject_id)){
					$user['subject_id'] = '-1';
				}
				else
				{
					$user['subject_id'] = implode(',', array_unique(explode(',' , implode(",", $admin_subject_id))));
				}

				if (in_array('-1', $admin_grade_id)){
				    $user['grade_id'] = '-1';
				}
				else
				{
				    $user['grade_id'] = implode(',', array_unique(explode(',' , implode(",", $admin_grade_id))));
				}


				if (in_array('-1', $admin_q_type_id)){
				    $user['q_type_id'] = '-1';
				}
				else
				{
				    $user['q_type_id'] = implode(',', array_unique(explode(',' , implode(",", $admin_q_type_id))));
				}



				foreach ($temp_subject_id as $key => $val)
				{
					if (in_array('-1',$val)){
						$user['action'][$key]['subject_id'] = '-1';
					}
					else
					{
						$user['action'][$key]['subject_id'] = implode(',', array_unique(explode(',' , implode(",", $val))));
					}
				}

				foreach ($temp_grade_id as $key => $val)
				{
				    if (in_array('-1',$val)){
				        $user['action'][$key]['grade_id'] = '-1';
				    }
				    else
				    {
				        $user['action'][$key]['grade_id'] = implode(',', array_unique(explode(',' , implode(",", $val))));
				    }
				}


				foreach ($temp_q_type_id as $key => $val)
				{
				    if (in_array('-1',$val)){
				        $user['action'][$key]['q_type_id'] = '-1';
				    }
				    else
				    {
				        $user['action'][$key]['q_type_id'] = implode(',', array_unique(explode(',' , implode(",", $val))));
				    }
				}




				foreach ($temp_action_list as $key => $val)
				{
					$user['action'][$key]['action_list'] = implode(',', array_unique(explode(',' , implode(",", $val))));
				}

				foreach($temp_action_type as $key => $val){

					$tmep_r = array();
					$tmep_w = array();

					$tmep_r[] = $val['question']['r'];
					$tmep_w[] = $val['question']['w'];

					$user['action'][$key]['action_type'] = @serialize(array('question'=>array('r'=>max($tmep_r),'w'=>max($tmep_w))));
				}
			}

			$data['last_login'] = time();
			$data['last_ip'] = $this->input->ip_address();
			$this->db->update('admin', $data, array('admin_id'=>$user['admin_id']));
			if ($this->db->trans_complete())
			{
			    $this->session->set_userdata($user);
			    redirect('admin/index/index');
			}
			else
			{
			    message('登录失败，请尝试重新登录');
			}
		}
		else
		{
			message('用户名或密码不正确！');
		}
	}


	/**
	 * 题库管理员密码重置
	 * from 后台管理员批量导入
	 */
	public function resetpwd()
	{
		$hash = $this->input->get('code');
		$admin_id = admin_email_hash('decode', $hash, 1800);
		$admin_id && $admin = CpUserModel::get_cpuser($admin_id);
		if ( ! $admin)
		{
			message('重置链接已失效，请重新提交申请', 'admin/index/login');
		}

		if ($this->input->post('act') == 'submit')
		{
			$password = $this->input->post('password');
			$newpwd_confirm = $this->input->post('password_confirm');

			if (is_string($passwd_msg = is_password($password))) {
				message($passwd_msg);
			}
			if ( $password != $newpwd_confirm )
			{
				message('您两次输入密码不一致，返回请确认！');
			}

			$this->db->update('admin', array('password'=>my_md5($password)), array('admin_id'=>$admin_id));
			message('您的新密码已设置成功.', 'admin/index/login', 'success');
		}
		else
		{
			// 模版
			$this->load->view('cpuser/resetpwd', array('hash'=>$hash));
		}
	}

	/**
	 * 清除系统缓存
	 */
	public function clean()
	{
	    $this->load->driver('cache');
	    $this->cache->file->clean();
	    
	    Fn::factory('File')->clear('/zmexam/');
	   // $this->mc->flush();
	    message('清除成功');
	}
}
