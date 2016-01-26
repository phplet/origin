<?php
/**
 * 
 * 考试系统 审核人员管理 控制器
 * @author TCG
 * @final 2013-11-13
 *
 */

if ( ! defined('BASEPATH')) exit();

class Exam_invigilator extends A_Controller 
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
    	if ( ! $this->check_power('exam_list,exam_manage')) return;
    	
        // 查询条件
        $query  = array();
        $param  = array();
        $search = array();
        
        $place_id = $this->input->get_post('place_id');
        if ($place_id)  {
        	$db_query = $this->db->select('p.place_id,p.place_name,p.address,e.exam_id,e.exam_name,sch.school_id,sch.school_name')->from('exam_place p')->join('exam e', 'p.exam_pid=e.exam_id')->join('school sch', 'p.school_id=sch.school_id')->where('p.place_id', $place_id)->get();
        	$place = $db_query->row_array();
        }
        if (empty($place))
        {
        	message('考场信息不存在');
        }

        //控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if (!$no_start) {
        	message('该考场正在考试或已结束，无法做此操作');
        }
         
        $data['place'] = $place;
        
        $search['place_id'] = $place_id;
        $param[] = "place_id={$place_id}";

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
        $query_is_trash = (int)$this->input->get_post('trash');
        if ($query_is_trash)  {
            $query['invigilator_flag'] = '-1';
            $search['trash'] = $query_is_trash;
            $param[] = "trash={$query_is_trash}";
        } else {
        	$query['invigilator_flag'] = array('0', '1');
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
        
        //查看已分配
        $has_assigned = intval($this->input->get_post('has_assigned'));
        if (!$query_is_trash) {
	        if ($has_assigned) {
	        	$search['has_assigned'] = $has_assigned;
	        	$param[] = "has_assigned={$has_assigned}";
		        $result = ExamInvigilatorModel::get_invigilator_list($query, $page, $per_page, $order_by, $selectWhat, $place_id);
	        } else {
		        $result = ExamInvigilatorModel::get_invigilator_list($query, $page, $per_page, $order_by, $selectWhat, $place_id, true);
	        }
        } else {
	        $result = ExamInvigilatorModel::get_invigilator_list($query, $page, $per_page, $order_by, $selectWhat);
        }
        
        $tmp_result = array();
        if (count($result)) {
        	foreach ($result as $v) {
        		if ($query_is_trash) {
        			$recycle = RecycleModel::get_recycle_list(array('type' => RECYCLE_EXAM_INVIGILATOR, 'obj_id' => $v['invigilator_id']), null, null, 'ctime asc');
        			$v['recycle'] = $recycle;
        		} else {
        			$v['recycle'] = array();
        		} 
        		
        		$tmp_result[] = $v;
        	}
        }
        
        // 分页
        $purl = site_url('admin/exam_invigilator/index/') . (count($param) ? '?'.implode('&',$param) : '');
        if ($has_assigned) {
        	$total = ExamInvigilatorModel::count_invigilator_lists($query, $place_id);
        } else {
        	$total = ExamInvigilatorModel::count_invigilator_lists($query, $place_id, true);
        }
        
		$data['pagination'] = multipage($total, $per_page, $page, $purl);

        $data['search'] = &$search;
        $data['list']   = &$tmp_result;
        
        //排序地址
        unset($param['order=']);
        unset($param['order_type=']);
        
        $order_url = site_url('admin/exam_invigilator/index/') . (count($param) ? '?'.implode('&',$param) : '');
        $data['order_url'] = $order_url;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);
    	
        // 模版
        $this->load->view('exam_invigilator/index', $data);
    }

    /**
     * 场次试卷添加操作页面
     *
     * @return  void
     */
    public function add()
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $data  = array();

        $data['mode'] = 'add';
        
        $data['place_id'] = intval($this->input->get('place_id'));

        // 模版
        $this->load->view('exam_invigilator/add', $data);
    }

    /**
     * 保存添加记录
     *
     * @return  void
     */
    public function save()
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $invigilator_id = (int)$this->input->post('id');
        
        $invigilator = ExamInvigilatorModel::get_invigilator_by_id($invigilator_id, 'invigilator_id');
        if ($invigilator_id > 0 && !count($invigilator))
        {
            message('该监考人员帐号不存在', 'admin/exam_invigilator/index');
            return;
        }

        $email = trim($this->input->post('email'));
        $password = $this->input->post('password');
        $password_confirm = (int)$this->input->post('password_confirm');
        $name = trim($this->input->post('name'));
        $memo = trim($this->input->post('memo'));
        $flag = $this->input->post('flag'); 
        $cellphone = trim($this->input->post('cellphone'));
        $telephone = trim($this->input->post('telephone'));       

        if ($email == '') {
            message('邮箱地址不能为空');
            return;
        } 

        if ($name == '') {
            message('姓名/名称 不能为空');
            return;
        } 

        if ($memo == '') {
            message('所在单位 不能为空');
            return;
        }

        if ($invigilator_id <= 0) {
        	if (is_string($passwd_msg = is_password($password))) {
        		message($passwd_msg);
        		return;
        	}

            if ($password != $password_confirm) {
                message('两次密码输入不一致');
                return;
            }
        }        
		
        if ($cellphone == '')
        {
        	message('手机号码不能为空');
        	return;
        }
        //检查用户名是否重复
        $queryArr = array(
            'invigilator_email' => $email        
        );

        if ($invigilator_id > 0) {
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
            		'cellphone' => $cellphone,
            		'telephone' => $telephone
            );

            $res = ExamInvigilatorModel::insert($insert_data);

        } else {
            //更新
            $update_data = array(
                    'invigilator_email' => $email,
                    'invigilator_name' => $name,
                    'invigilator_memo' => $memo,
                    /*'invigilator_flag' => $flag,*/
            );

            $res = ExamInvigilatorModel::update($invigilator_id, $update_data);
        }
        
        $place_id = intval($this->input->post('place_id'));
        $back_url = 'admin/exam_invigilator/index/?place_id=' . $place_id;
        if ($res < 1) {
            message('监考人员 保存失败', $back_url);
        } else {
            if ($invigilator_id > 0) {
                admin_log('edit', 'exam_invigilator', $invigilator_id);
            } else {
                admin_log('add', 'exam_invigilator');
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
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $data = array();
         
        $query = array('invigilator_id' => $id);
        $detail = ExamInvigilatorModel::get_invigilator_by_id($id);
        if (!$id || !count($detail)) {
            message('不存在该监考人员信息');
            return;
        }

        $data['place_id'] = $this->input->get('place_id');
        $data['detail'] = $detail;
        $data['mode'] = 'edit';   

        $this->load->view('exam_invigilator/add', $data);
    }

    /**
     * 删除彻底记录
     *
     * @return  void
     */
    public function batch_delete()
    {     
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $ids = $this->input->post('id');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url))
        {
            $back_url = 'admin/exam_invigilator/index';
        }

        ExamInvigilatorModel::delete($ids, true);
        
        message('删除成功', $back_url);
    }

    /**
     * 禁用/回收站
     *
     * @return  void
     */
    public function do_action()
    {       
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $id = $this->input->get_post('id');
        $place_id = intval($this->input->get_post('place_id'));
       
        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url))
        {
            $back_url = 'admin/exam_invigilator/index/?place_id=' . $place_id;
        }

        $act = $this->input->get_post('act');
        $res = TRUE;
        if ($act == '0') {
            //启用
            $res = ExamInvigilatorModel::update($id, array('invigilator_flag' => '1'));

        } elseif ($act == '1') {
            //禁用
            $res = ExamInvigilatorModel::update($id, array('invigilator_flag' => '0'));

        } elseif ($act == '2') {
            //回收站            
        	recycle_log_check($id);
        	
            $res = ExamInvigilatorModel::delete($id);

            $log_ids = is_string($id) ? $id : implode(', ', $id);
            admin_log('delete', 'exam_invigilator', $id);
            
            recycle_log(RECYCLE_EXAM_INVIGILATOR, $id);

        } elseif ($act == '3') {
            //还原         
            $res = ExamInvigilatorModel::update($id, array('invigilator_flag' => '1'));

            $log_ids = is_string($id) ? $id : implode(', ', $id);
            admin_log('restore', 'exam_invigilator', $id);
        }
        
        if ($res) {
            message('操作成功', $back_url, 'success');
        } else {
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
    	if ( ! $this->check_power('exam_manage')) return;
    	
    	$id = intval($id);
    	if (!$id) {
    		message('不存在该监考人员.');
    	}
    	
    	$invigilate = ExamInvigilatorModel::get_invigilator_by_id($id);
    	if (!count($invigilate)) {
    		message('不存在该监考人员.');
    	}
    	
    	$data['uid'] = $id;
    	$this->load->view('exam_invigilator/reset_password', $data);
    }
    
    /**
     * 重置密码
     *
     * @return  void
     */
    public function reset_password()
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
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
    	$invigilater_passwd = ExamInvigilatorModel::get_invigilator_by_id($invigilator_id, 'invigilator_password');
    	if (!count($invigilater_passwd)) {
    		output_json(CODE_ERROR, '不存在该监考人员.');
    	}
    	
    	//检查帐号密码是否正确
    	$flag = ExamInvigilatorModel::reset_invigilator_password($invigilator_id, my_md5($new_password));
    	if (!$flag) {
    		output_json(CODE_ERROR, '密码修改失败，请重试');
    	}
    	 
    	output_json(CODE_SUCCESS, '密码修改成功.');
    }
}
