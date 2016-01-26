<?php if ( ! defined('BASEPATH')) exit();
class Exam_place extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 场次列表、搜索
     *
     * @return  void
     */
    public function index($exam_pid=0)
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;

        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();

        $exam_pid = intval($exam_pid);
        if ($exam_pid)
        {
            $parent = ExamModel::get_exam($exam_pid, '*');
        }
        if (empty($parent) OR $parent['exam_pid'] > 0)
        {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }

        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();
        $this->check_exam_manager_power($parent['creator_id'], $managers);

        $where[] = "p.exam_pid=$exam_pid";

        if ($search['keyword'] = trim($this->input->get('keyword')))
        {
            $escape_keyword = $this->db->escape_like_str($search['keyword']);
            $where[] = "p.place_name LIKE '%".$escape_keyword."%'";
            $param[] = "keyword=".urlencode($search['keyword']);
        }

        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';

        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_place p $where";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        // 读取数据
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list   = array();
        if ($total)
        {
            $sql = "SELECT p.*,s.school_name,COUNT(ps.uid) student_num, sc.schcls_name
                    FROM {pre}exam_place p
                    LEFT JOIN {pre}school s ON p.school_id=s.school_id
                    LEFT JOIN {pre}exam_place_student ps ON p.place_id=ps.place_id
                    LEFT JOIN t_school_class sc ON sc.schcls_id = p.place_schclsid
                    $where GROUP BY p.place_id ORDER BY p.place_id ASC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                $row['start_time'] = $row['start_time'] ? date('Y-m-d H:i', $row['start_time']) : '';
                $row['end_time'] = $row['end_time'] ? date('Y-m-d H:i', $row['end_time']) : '';

                //检查该考场是否有关联信息
                $row['has_relate_info'] = ExamPlaceModel::has_place_relate_info($row['place_id']);
                $list[] = $row;
            }
        }

        // 分页
        $purl = site_url('admin/exam_place/index/'.$exam_pid) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);
		
		//外部考试
		if ($parent['exam_ticket_maprule'])
		{
		    $parent['max_end_time'] = ExamPlaceModel::get_exam_place($exam_pid, 'MAX(end_time) AS end_time');
		}

        $data['search'] = $search;
        $data['parent'] = $parent;
        $data['list']   = $list;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);

        $data['has_preview_manage'] = false;
        $data['is_super_user'] = $this->is_super_user();
        
        if ($this->is_super_user() || $parent['creator_id'] == $this->session->userdata('admin_id'))
        {
            $data['has_preview_manage'] = true;
        }

        $this->demo_exams($data);

        // 模版
        $this->load->view('exam_place/index', $data);
    }

    private function demo_exams(&$data)
    {
        $exam_config = C('demo_exam_config', 'app/demo/website');
        $demo_exams = array();
        foreach ($exam_config as $item)
        {
            $demo_exams[] = $item['exam_pid'];
        }

        $data['demo_exams'] = $demo_exams;
    }

    /**
     * 添加考场
     *
     * @return  void
     */
    public function add($exam_pid=0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $exam_pid = intval($exam_pid);
        if ($exam_pid)
        {
            $parent = ExamModel::get_exam($exam_pid, 'exam_id,exam_name,exam_pid,grade_id,creator_id,managers,exam_isfree');
        }
        
        if (empty($parent) OR $parent['exam_pid']>0)
        {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }

        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();
        $this->check_exam_manager_power($parent['creator_id'], $managers);

        $grade_period = get_grade_period($parent['grade_id']);
        $query = $this->db->select('s.school_id,s.school_name,count(u.uid) nums')->from('school s')->join('student u','s.school_id=u.school_id')->like('s.grade_period',$grade_period)->where(array('u.is_delete'=>0))->group_by('s.school_id')->get();
        $schools = $query->result_array();

        $place = array(
            'place_id'    => '',
            'place_name'  => '',
            'exam_pid'    => $parent['exam_id'],
            'place_index' => '',
            'school_id'   => 0,
            'place_schclsid' => 0,
            'address'     => '',
            'ip'          => '',
            'start_time'  => '',
            'end_time'    => '',
        );
        
        if ($schools)
        {
            $school_ids = array();
            foreach ($schools as $item)
            {
                $school_ids[] = $item['school_id'];
            }
            
            $param['schcls_schid'] = implode(',', $school_ids);
            
            $data['class'] = SchoolModel::schoolClassList("*", $param, 1, time());
        }

        $data['act']     = 'add';
        $data['parent']  = &$parent;
        $data['place']   = &$place;
        $data['schools'] = &$schools;

        // 模版
        $this->load->view('exam_place/edit', $data);
    }

    /**
     * 编辑考场信息
     *
     * @return  void
     */
    public function edit($id = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $id = intval($id);
        $id && $place = ExamPlaceModel::get_place($id);

        if (empty($place)) {
            message('考场不存在');
            return;
        }

        $parent = ExamModel::get_exam($place['exam_pid'], 'exam_id,exam_name,exam_pid,grade_id,creator_id,managers,exam_isfree');
        
        if (empty($parent) OR $parent['exam_pid']>0) {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }

        $managers = $parent['managers'] ? json_decode($parent['managers'] , true) : array();
        $this->check_exam_manager_power($parent['creator_id'], $managers);

        $place['start_time'] = date('Y-m-d H:i', $place['start_time']);
        $place['end_time']   = date('Y-m-d H:i', $place['end_time']);
        $place['exam_time_custom']   = json_decode($place['exam_time_custom'], true);

        $grade_period = get_grade_period($parent['grade_id']);
        $query = $this->db->select('s.school_id,s.school_name,count(u.uid) nums')->from('school s')->join('student u','s.school_id=u.school_id')->like('s.grade_period',$grade_period)->where(array('u.is_delete'=>0))->group_by('s.school_id')->get();
        $schools = $query->result_array();
        
        if ($schools)
        {
            $school_ids = array();
            foreach ($schools as $item)
            {
                $school_ids[] = $item['school_id'];
            }
        
            $param['schcls_schid'] = implode(',', $school_ids);
        
            $data['class'] = SchoolModel::schoolClassList("*", $param, 1, time());
        }

        $data['act']     = 'edit';
        $data['place']   = &$place;
        $data['parent']  = &$parent;
        $data['schools'] = &$schools;

        // 模版
        $this->load->view('exam_place/edit', $data);
    }

    /**
     * 添加/编辑处理页面
     *
     * @return  void
     */
    public function update()
    {
    	if (!$this->check_power('exam_manage')) return;

        $act = $this->input->post('act');
        $act = $act == 'add' ? $act : 'edit';
        $exam_pid = (int)$this->input->post('exam_pid');
        $exam_pid && $exam_parent = ExamModel::get_exam($exam_pid, 'exam_id,exam_pid,creator_id,managers,exam_isfree');
        if (empty($exam_parent) OR $exam_parent['exam_pid']>0)
        {
            message('考试期次不存在', 'admin/exam/index');
            return;
        }

        $managers = $exam_parent['managers'] ? json_decode($exam_parent['managers'] , true) : array();
        $this->check_exam_manager_power($exam_parent['creator_id'], $managers);

        if ($act == 'edit')
        {
            $place_id = (int)$this->input->post('place_id');
            $place_id && $old_place = ExamPlaceModel::get_place($place_id);
            if (empty($old_place) OR $old_place['exam_pid'] != $exam_pid)
            {
                message('考场信息不存在', 'admin/exam_place/index/'.$exam_pid);
                return;
            }
        }

        // 题目基本信息
        $place['exam_pid']    = $exam_pid;
        $place['place_name']  = html_escape(trim($this->input->post('place_name')));
        $place['place_index'] = 1;//(int)$this->input->post('place_index');
        $place['school_id']   = (int)$this->input->post('school_id');
        $place['place_schclsid']   = (int)$this->input->post('place_schclsid');
        $place['address']     = html_escape(trim($this->input->post('address')));
        $place['ip']          = html_escape(trim($this->input->post('ip')));
        $place['start_time']  = strtotime($this->input->post('start_time'));
        $place['end_time']    = strtotime($this->input->post('end_time'));
        $exam_time_custom = $this->input->post('exam_time_custom');

        $message = array();
        if (empty($place['place_name']))
        {
            $message[] = '请填写考场名称';
        }

        if (empty($place['place_index']))
        {
            //$message[] = '请选择场次序号';
        }

        if ($exam_parent['exam_isfree'] == '0' 
            && empty($place['school_id']))
        {
            $message[] = '请选择学校';
        }

        if ($exam_parent['exam_isfree'] == '0' 
            && empty($place['address']))
        {
            $message[] = '请填写考场地址';
        }

        if ($place['ip'])
        {
            $ips = array_unique(explode("\n", $place['ip']));
            $err = false;
            foreach ($ips as $ip)
            {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
                {
                    $err = true;
                }
            }

            if ($err)
            {
                $message[] = '输入的考场IP中存在错误IP地址';
            }
            else
            {
                $place['ip'] = "," . implode(',', $ips) . ",";
            }
        }

        /* 自定义学科时间说明 */
        if (!empty($exam_time_custom)) {

            $exam_time_custom_arr = array();

            /* 分割字符串 */
            $lines = array_unique(explode("\n", $exam_time_custom));

            if (count($lines >= 1)) {
                foreach ($lines as $key => $line) {

                    /* 去除空白行 */
                    if (empty($line) || strlen($line) < 1) {
                        continue;
                    }
                    /* 去除无用行 */
                    if (!strpos($line, ':') && !strpos($line, '：')) {
                        continue;
                    }

                    /* 替换空格及冒号 */
                    $search = array('：', '　');
                    $replace = array(':', ' ');
                    $current_line = str_replace($search, $replace, trim($line));

                    if (empty($current_line)) {
                        continue;
                    }

                    $tmp_arr = array_unique(explode(":", trim($current_line), 2));
                    $exam_time_custom_arr[trim($tmp_arr[0])] = trim($tmp_arr[1]);
                }

                if (!empty($exam_time_custom_arr)) {
                    $place['exam_time_custom'] = json_encode($exam_time_custom_arr);
                } else {
                    $message[] = '自定义学科时间说明格式错误！';
                }
            }
        } else {
            $place['exam_time_custom'] = '';
        }

        if (empty($place['start_time']))
        {
            $message[] = '请填写开始时间';
        }

        if (empty($place['end_time']))
        {
            $message[] = '请填写结束时间';
        }

        if ($place['start_time'] && $place['end_time'] && $place['start_time'] >= $place['end_time'])
        {
            $message[] = '结束时间必须大于开始时间。';
        }
        
        if (ExamPlaceModel::checkPlaceNameIsRepeat(
            $exam_pid, $place_id, $place['place_name']))
        {
            $message[] = '考场名称已经存在。';
        }

        if ($message)
        {
            message(implode('<br/>', $message), null, 10);
            return;
        }

        if ($act == 'add')
        {
            $this->db->insert('exam_place', $place);
            $place_id = $this->db->insert_id();
            admin_log('add', 'exam_place', $place_id);
        }
        else
        {
            $this->db->update('exam_place', $place, array('place_id'=>$place_id));
            admin_log('edit', 'exam_place', $place_id);
        }

        message('考场'.($act=='add'?'添加':'修改').'成功', 'admin/exam_place/index/'.$exam_pid);
    }

    /**
     * 删除考场
     *
     * @return  void
     */
    public function delete($id = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

        $back_url = empty($_SERVER['HTTP_REFERER']) ? 'admin/exam/index' : $_SERVER['HTTP_REFERER'];
        $return = ExamPlaceModel::delete($id);
        if ( $return === true )
        {
            message('删除成功', $back_url);
        }
        else
        {
            switch($return)
            {
                case -1: $message = '考场不存在'; break;
                default: $message = '删除失败'; break;
            }
            message($message, $back_url);
        }
    }

    /**
     * 获取考试期次列表
     *
     * @param number $exam_id
     * @return void
     */
    public function ajax_get_exams($exam_pid = 0)
    {
    	$exams = ExamModel::get_exam_list(array('exam_pid' => '0', 'status' => array('1', '2')), false, false, null, 'exam_id, exam_name');

    	$output = array();
    	$output[] = '<option value="0">--请选择考试期次--</option>';
    	foreach ($exams as $exam) {
    		$t_exam_pid = $exam['exam_id'];
    		$exam_name = $exam['exam_name'];
    		$output[] = "<option value='{$t_exam_pid}'" . ($exam_pid == $t_exam_pid ? 'selected="selected"' : '') . ">{$exam_name}</option>";
    	}

    	echo implode('', $output);
    	die;
    }

    /**
     * 根据考试期次获取考场信息
     *
     * @param number $exam_pid
     * @return void
     */
    public function ajax_get_exam_place($exam_pid = 0, $place_id = 0)
    {
    	$exam_pid = intval($exam_pid);
    	if (!$exam_pid) {
    		die;
    	}

    	$query = array(
    				'exam_pid' => $exam_pid,
//     				'start_time' => array('<=' => time()),//已经开始的
    	);

    	$select_what = 'place_id, place_name, start_time, end_time';
    	$order_by = 'end_time asc';
    	$places = ExamPlaceModel::get_exam_place_list($query, false, false, $order_by, $select_what);

    	$now = time();
    	$output = array();
    	$output[] = '<option value="0">--请选择考试场次--</option>';
    	foreach ($places as $place) {
    		$place_name = $place['place_name'];
    		$t_place_id = $place['place_id'];
    		$start_time = $place['start_time'];
    		$end_time = $place['end_time'];

    		$status = '';
    		if ($now < $start_time) {
    			$status = '(未开始)';
    		} elseif($now >= $start_time && $now < $end_time) {
    			$status = '(进行中)';
    		} elseif($now >= $end_time) {
    			$status = '(已结束)';
    		}

    		$output[] = "<option value='{$t_place_id}'" . ($place_id == $t_place_id ? 'selected="selected"' : '') . ">{$place_name}{$status}</option>";
    	}

    	echo implode('', $output);
    	die;
    }
    
    /**
     * 检查考场 配置信息
     *
     * @return  void
     */
    public function init_place_student($place_id = 0)
    {
        $place_id = intval($place_id);
        $place_id && $place = ExamPlaceModel::get_place($place_id);
        if (empty($place))
        {
            message('考场不存在');
            return;
        }
    
        $stu_ids = ExamPlaceModel::placeStudentList($place_id);
        if (!$stu_ids)
        {
            message('考场没有考生，初始化失败');
            return;
        }
        
        $db = Fn::db();
        $param['place_id'] = $place_id;
        $param['uid_data'] = json_encode($stu_ids);
        $param['status'] = 0;
        $param['c_time'] = time();
        $param['u_time'] = time();
        $db->insert('rd_cron_task_place_student_paper', $param);
        if ($db->lastInsertId('rd_cron_task_place_student_paper', 'id'))
        {
            message('考生考试信息初始化已加入计划任务');
        }
        else
        {
            message('考生考试信息初始化失败，请重试');
        }
    }

    /**
     * 检查考场 配置信息
     *
     * @return  void
     */
    public function check_status($place_id = 0)
    {
    	if ( ! $this->check_power('exam_manage')) return;

    	$place_id = intval($place_id);
    	$place_id && $place = ExamPlaceModel::get_place($place_id);
    	if (empty($place))
    	{
    		message('考场不存在');
    		return;
    	}

    	$message = array();
/*
    	try {
    		$this->db->trans_start();
    		$sql = "DELETE FROM {pre}exam_subject_paper WHERE paper_id NOT IN(SELECT paper_id from {pre}exam_paper )";
    		$this->db->query($sql);

    		$this->db->trans_complete();

    	} catch(Exception $e) {
    		$this->db->trans_complete();

    	}
*/

    	/*
    	 * todo:
    	 * 	检查是否已选择学科
    	 *  检查学科是否添加试卷
    	 *
    	 *  检查是否已添加考生
    	 *  检查是否已添加监考人员
    	 */

    	//检查是否已选择学科
    	$result = $this->db->query("select count(*) as count from {pre}exam_place_subject where place_id={$place_id} and exam_pid = {$place['exam_pid']}")->result_array();
    	if (!$result[0]['count']) {
    		$message[] = '未选择学科(<font color="red">请确认下该考场所在的考试期次下是否已添加 学科</font>)';
    	} else {
	    	$result = $this->db->query("select count(*) as count from {pre}exam_place_subject where place_id={$place_id} and exam_pid = {$place['exam_pid']}")->result_array();
	    	$result2 = $this->db->query("select count(distinct(eps.exam_id)) as count from {pre}exam_place_subject eps, {pre}exam_subject_paper esp where eps.place_id={$place_id} and eps.exam_id=esp.exam_id and eps.exam_pid = {$place['exam_pid']}")->result_array();
	    	if ($result[0]['count'] > $result2[0]['count']) {
	    		$message[] = '有学科未选择试卷';
	    	}
	    		$result = $this->db->query("select count(*) as count,eps.subject_id,esp.paper_id from {pre}exam_place_subject eps, {pre}exam_subject_paper esp where eps.place_id={$place_id}
	    		and eps.exam_pid = {$place['exam_pid']} and eps.exam_id=esp.exam_id and esp.paper_id not in(SELECT paper_id from {pre}exam_paper) group by esp.paper_id")->result_array();
				$subjects = C('subject');
				foreach($result as $key => $val)
				{
					$message[] = '学科['.$subjects[$val['subject_id']].']试卷ID【'.$val['paper_id'].'】不存在';
				}



    	}

    	//检查是否已添加考生
    	$result = $this->db->query("select count(*) as count from {pre}exam_place_student where place_id={$place_id}")->result_array();
    	if (!$result[0]['count']) {
    		$message[] = '未添加考生';
    	}

    	//检查是否已添加监考人员
    	$result = $this->db->query("select count(*) as count from {pre}exam_place_invigilator where place_id={$place_id}")->result_array();
    	if (!$result[0]['count']) {
    		$message[] = '未添加监考人员';
    	}

    	if (count($message)) {
    		message('<strong>检测到该考场有以下异常：<br/></strong>' . implode('<br/>', $message));
    	}

    	message('恭喜您，该考场配置信息均正常 :)');
    }

    /**
     * 判断当前用户是否有权限修改考试期次
     * @param   int     $creator_id    考试期次创建者
     * @param   array   $managers      考试期次管理员列
     */
    private function check_exam_manager_power($creator_id, $managers)
    {
        if (!$this->is_super_user())
        {
            $admin_id = $this->session->userdata('admin_id');
            if ($admin_id != $creator_id
            && !in_array($admin_id, $managers))
            {
                message('您没有权限对此记录进行操作！');
            }
        }
    }
}
