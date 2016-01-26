<?php

// +------------------------------------------------------------------------------------------
// | Author: TCG <TCG_love@163.com> 
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 教师管理（允许下载评估报告）  Dates: 2015-03-02
// +------------------------------------------------------------------------------------------

if (!defined('BASEPATH')) exit();

class Teacher_download extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
        require_once (APPPATH.'config/app/admin/recycle.php');
    }

    /**
     * 监考人员 列表
     *
     * @return  void
     */
    public function index()        
    {
    	if (!$this->check_power('teacher_download_manage')) return;
    	
        // 查询条件
        $query  = array();
        $param  = array();
        $search = array();
        
        $page = (int) $this->input->get_post('page');
        $page = $page ? $page : 1;

        $per_page = (int) $this->input->get_post('per_page');
        $per_page = $per_page ? $per_page : 10;

        $order_bys = array(
        					'email' => 'email',
        					'name' => 'name',
        					'memo' => 'memo',
        					'time' => 'addtime',
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
            $query['email'] = trim($query_email);
            $search['email'] = $query_email;
            $param[] = "email={$query_email}";
        }

        $query_name = $this->input->get_post('name');
       
        if ($query_name)  {
            $query['name'] = trim($query_name);
            $search['name'] = $query_name;
            $param[] = "name={$query_name}";
        }

        $query_flag = $this->input->get_post('flag');
        if ($query_flag)  {
            $query['flag'] = is_string($query_flag) ? (int) $query_flag : $query_flag;
            $search['flag'] = $query_flag;
            $param[] = "flag={$query_flag}";
        }

        $query_begin_time = $this->input->get_post('begin_time');
        if ($query_begin_time)  {
            $query['addtime'] = array('>=' => strtotime($query_begin_time));
            $search['begin_time'] = $query_begin_time;
            $param[] = "begin_time={$query_begin_time}";
        }

        $query_end_time = $this->input->get_post('end_time');
        if ($query_end_time)  {
            if (! isset($query['addtime'])) {
                 $query['addtime'] = array();
            }
            
            $query['addtime']['<='] = strtotime($query_end_time);
            $search['end_time'] = $query_end_time;
            $param[] = "end_time={$query_end_time}";
        }  
        
	    $result = TeacherDownloadModel::get_list($query, $page, $per_page, $order_by, $selectWhat);
        
        // 分页
        $purl = site_url('admin/teacher_download/index/') . (count($param) ? '?'.implode('&',$param) : '');

        $total = TeacherDownloadModel::count_lists($query);

		$data['pagination'] = multipage($total, $per_page, $page, $purl);

        $data['search'] = &$search;
        $data['list']   = &$result;
        
        //排序地址
        unset($param['order=']);
        unset($param['order_type=']);
        
        $order_url = site_url('admin/teacher_download/index/') . (count($param) ? '?'.implode('&',$param) : '');
        $data['order_url'] = $order_url;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);
    	
        // 模版
        $this->load->view('teacher_download/index', $data);
    }

    /**
     * 考试期次列表
     *
     * @return boolean 成功返回true 失败返回false
     */
    public function select_exams()
    {
        if (!$this->check_power('teacher_download_manage')) exit;

        $class_list = ClassModel::get_class_list();
        $grades     = C('grades');
        $states     = C('exam_status');

        $sql = "SELECT COUNT(*) nums FROM {pre}exam WHERE exam_pid=0;";
        $row = $this->db->query($sql)->row_array();
        $total = $row['nums'];

        if ($total > 0) {
            $size   = 15;
            $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
            $offset = ($page - 1) * $size;
            /* 分页 */
            $purl = site_url('admin/teacher_download/select_exams/'.$pid);
            $data['pagination'] = multipage($total, $size, $page, $purl);

            $list = array();
            $sql = "SELECT * FROM {pre}exam WHERE exam_pid=0 ORDER BY exam_id desc LIMIT $offset,$size";
            $res = $this->db->query($sql);

            foreach ($res->result_array() as $row) {
                $row['class_name'] = isset($class_list[$row['class_id']]['class_name'])
                                         ? $class_list[$row['class_id']]['class_name'] : '';
                $row['grade_name'] = isset($grades[$row['grade_id']]) ? $grades[$row['grade_id']] : '';
                $row['addtime'] = date('Y-m-d H:i', $row['addtime']);
                $row['state'] = $states[$row['status']];

                $list[$row['exam_id']] = $row;
            }

            $data['list'] = $list;
        }

        $this->load->view("teacher_download/exam_list", $data);
    }

    /**
     * 场次试卷添加操作页面
     *
     * @return  void
     */
    public function add()
    {
    	if (!$this->check_power('teacher_download_manage')) return;

        /* 学科信息 */
        $subjects = C('subject');
        /* 添加总结报告 */
        $subjects[0] = '总结';

        $data  = array();
        $data['mode'] = 'add';
        $data['subjects'] = $subjects;
        
        /* 模版 */
        $this->load->view('teacher_download/add', $data);
    }

    /**
     * 保存添加记录
     *
     * @return  void
     */
    public function save()
    {
    	if (!$this->check_power('teacher_download_manage')) return;
    	
        $id = (int)$this->input->post('id');
        
        $teacher = TeacherDownloadModel::get_by_id($id, 'id');

        if ($id > 0 && !count($teacher))
        {
            message('该教师帐号不存在', 'admin/teacher_download/index');
            return;
        }
        
        $email = trim($this->input->post('email'));
        $password = $this->input->post('password');
        $password_confirm =$this->input->post('password_confirm');
        $name = trim($this->input->post('name'));
        $memo = trim($this->input->post('memo'));
        $flag = $this->input->post('flag');
        $telephone = $this->input->post('telephone');  
        $cellphone = $this->input->post('cellphone');
        $exams = $this->input->post('exam_id');
        $subjects = $this->input->post('subjects');
        
        if (empty($email)) {
            message('邮箱地址不能为空');
            exit;
        } 

        if (empty($name)) {
            message('姓名/名称 不能为空');
            exit;
        } 

        if (empty($memo)) {
            message('所在单位 不能为空');
            exit;
        }

        if (empty($cellphone)) {
            message('手机号码 不能为空');
            exit;
        }

        if ($id <= 0) {
        	if (is_string($passwd_msg = is_password($password))) {
        		message($passwd_msg);
        		return;
        	}

            if ($password != $password_confirm) {
                message('两次密码输入不一致');
                return;
            }
        }        

        //检查用户名是否重复
        $queryArr = array(
            'email' => $email        
        );

        if ($id > 0) {
            $queryArr['id != '] = $id;
        }
        $query = $this->db->select('id')->get_where('teacher_download', $queryArr);
        if ($query->num_rows()) {
            message('该邮箱地址已存在！');
            return;
        }

        $flag = ! $flag ? '1' : $flag;
        $flag = $flag >= 1 ? 1 : $flag;
        $res = 0;

        if ($id <= 0){
            $insert_data = array(
                    'email' => $email,
                    'password' => my_md5($password),
                    'name' => $name,
                    'memo' => $memo,
                    'flag' => '1',
                    'addtime' => time(),
                    'updatetime' => time(),
                    'telephone' => $telephone,
                    'cellphone' => $cellphone,
                    'relate_exam' => json_encode($exams),
                    'subjects' => json_encode($subjects)
            );
            $res = TeacherDownloadModel::insert($insert_data);
        } else {
            //更新
            $update_data = array(
                    'email' => $email,
                    'name' => $name,
                    'memo' => $memo,
                    'updatetime' => time(),
                    'telephone' => $telephone,
                    'cellphone' => $cellphone,
                    'relate_exam' => json_encode($exams),
                    'subjects' => json_encode($subjects)
            );
            $res = TeacherDownloadModel::update($id, $update_data);
        }

        $back_url = "admin/teacher_download/index";

        if ($res < 1) 
        {
            message('保存失败', $back_url);
        } 
        else 
        {
            if ($id > 0) 
            {
                admin_log('edit', 'teacher_download', $id);
            } 
            else 
            {
                admin_log('add', 'teacher_download');
            }

            message('保存成功', $back_url);
        }
    }

    /**
     * 编辑信息
     *
     * @return  void
     */
    public function edit($id)
    {
    	if (!$this->check_power('teacher_download_manage')) return;
         
        $query = array('id' => $id);
        $detail = TeacherDownloadModel::get_by_id($id);

        if (!$id || !count($detail)) {
            message('不存在该人员信息');
            return;
        }

        /* 考試期次 */
        $exams = json_decode($detail['relate_exam'], true);

        if (!empty($exams) && count($exams) > 0) {
            foreach ($exams as $value) {
                $sql = "SELECT exam_id,exam_name FROM {pre}exam WHERE exam_id={$value}";
                $row = $this->db->query($sql)->row_array();           
                $detail['exams'][] = $row;
            }
        }

        $subjects = C('subject');
        /* 添加总结报告 */
        $subjects[0] = '总结';

        $data = array();
        $data['subjects'] = $subjects;
        $data['old_subjects'] = json_decode($detail['subjects'], true);
        $data['detail'] = $detail;
        $data['mode'] = 'edit';

        $this->load->view('teacher_download/add', $data);
    }

    /**
     * 删除彻底记录
     *
     * @return  void
     */
    public function delete()
    {     
        if (!$this->check_power('teacher_download_manage')) exit;
        
        $id = $this->input->get('id');

        if (empty($id)) {
            message('ID不能为空！'); exit;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        if (empty($back_url))
        {
            $back_url = 'admin/teacher_download/index';
        }

        TeacherDownloadModel::delete($id, true);
        
        message('删除成功!', $back_url);
    }

    /**
     * 删除彻底记录
     *
     * @return  void
     */
    public function batch_delete()
    {     
    	if (!$this->check_power('teacher_download_manage')) return;
    	
        $ids = $this->input->post('id');

        if (empty($ids) OR !is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        if (empty($back_url))
        {
            $back_url = 'admin/teacher_download/index';
        }

        TeacherDownloadModel::delete($ids, true);
        
        message('删除成功', $back_url);
    }

    /**
     * 禁用/回收站
     *
     * @return  void
     */
    public function do_action()
    {       
    	if (!$this->check_power('teacher_download_manage')) return;
    	
        $id = $this->input->get_post('id');
       
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];

        $act = $this->input->get_post('act');

        $res = TRUE;

        if ($act == '0') 
        {
            //启用
            $res = TeacherDownloadModel::update($id, array('flag' => '1'));

        } 
        elseif ($act == '1') 
        {
            //禁用
            $res = TeacherDownloadModel::update($id, array('flag' => '0'));
        } 
        elseif ($act == '2') 
        {
            //回收站            
        	recycle_log_check($id);
        	
            $res = TeacherDownloadModel::delete($id);

            $log_ids = is_string($id) ? $id : implode(', ', $id);

            admin_log('delete', 'teacher_download', $id);
            
            recycle_log(RECYCLE_EXAM_INVIGILATOR, $id);

        } 
        elseif ($act == '3') 
        {
            //还原         
            $res = TeacherDownloadModel::update($id, array('flag' => '1'));

            $log_ids = is_string($id) ? $id : implode(', ', $id);

            admin_log('restore', 'teacher_download', $id);
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
    	if (!$this->check_power('teacher_download_manage')) return;
    	
    	$id = intval($id);

    	if (!$id) 
        {
    		message('不存在该监考人员.');
    	}
    	
    	$teacher_download = TeacherDownloadModel::get_by_id($id);

    	if (!count($teacher_download)) 
        {
    		message('不存在该监考人员.');
    	}
    	
    	$data['uid'] = $id;

    	$this->load->view('teacher_download/reset_password', $data);
    }
    
    /**
     * 重置密码
     *
     * @return  void
     */
    public function reset_password()
    {
    	if (!$this->check_power('teacher_download_manage')) return;
    	
    	$new_password = $this->input->post('new_password');
    	$new_confirm_password = $this->input->post('confirm_password');
    	$id = intval($this->input->post('uid'));
    	
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
    	$passwd = TeacherDownloadModel::get_by_id($id, 'password');

    	if (!count($passwd)) 
        {
    		output_json(CODE_ERROR, '不存在该监考人员.');
    	}
    	
    	//检查帐号密码是否正确
    	$flag = TeacherDownloadModel::reset_password($id, my_md5($new_password));

    	if (!$flag) 
        {
    		output_json(CODE_ERROR, '密码修改失败，请重试');
    	}
    	 
    	output_json(CODE_SUCCESS, '密码修改成功.');
    }
}
