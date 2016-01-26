<?php if ( ! defined('BASEPATH')) exit();
class Place_invigilator extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    // 场次监考人员列表
    public function index($place_id=0)        
    {
    	if ( ! $this->check_power('exam_list,exam_manage')) return;
    	
        // 查询条件
        $where  = array();
        $param  = array();
        $search = array();

        $place_id = intval($place_id);
        if ($place_id)
        {
            $query = $this->db->select('p.place_id,p.place_name,p.address,e.exam_id,e.exam_name,sch.school_id,sch.school_name')->from('exam_place p')->join('exam e', 'p.exam_pid=e.exam_id')->join('school sch', 'p.school_id=sch.school_id')->where('p.place_id', $place_id)->get();
            $place = $query->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在', 'admin/exam/index');
            return;
        }

        //控制考场只能在未开始考试操作
        $place['no_start'] = ExamPlaceModel::place_is_no_start($place_id);

        $where[] = "ps.place_id=$place_id";

        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';

        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}exam_place_invigilator ps $where";
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
            $sql = "SELECT ps.id, i.invigilator_email,i.invigilator_name,i.invigilator_memo, i.invigilator_flag
                    FROM {pre}exam_place_invigilator ps
                    LEFT JOIN {pre}invigilator i ON ps.invigilator_id=i.invigilator_id
                    $where LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row)
            {
                $list[] = $row;
            }
        }
        
        // 分页
        $purl = site_url('admin/place_invigilator/index/'.$place_id) . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);

        $data['place']  = &$place;
        $data['list']   = &$list;
        $data['priv_manage'] = $this->check_power('exam_manage', FALSE);
        

        // 模版
        $this->load->view('place_invigilator/index', $data);
    }

    /**
     * 添加 考场 监考人员
     */
    public function insert()
    {
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $place_id = (int)$this->input->post('place_id');
        if ($place_id)
        {
            $query = $this->db->select('p.*')->from('exam_place p')->join('exam e','p.exam_pid=e.exam_id')->join('school sch','p.school_id=sch.school_id')->where(array('p.place_id'=>$place_id))->get();
            $place = $query->row_array();
        }
        if (empty($place))
        {
            message('考场信息不存在', 'admin/exam/index');
            return;
        }

        $ids = $this->input->post('id');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }
        
        //控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if (!$no_start) {
        	message('该考场正在考试或已结束，无法做此操作');
        }

        $ids = my_intval($ids);

        $inserts = array();
        
        //获取未被分配考场的监考人员
        /*
         *  同一个监考人员不能同时出现在 相同时段的考场中
         */
        $place['start_time'] = $place['start_time'] + 1;
        $place['end_time'] = $place['end_time'] - 1;
        $sql = "SELECT i.invigilator_id FROM {pre}invigilator i 
                WHERE i.invigilator_flag=1 AND i.invigilator_id IN(".my_implode($ids).")
                AND NOT EXISTS(SELECT ps.invigilator_id FROM {pre}exam_place_invigilator ps,{pre}exam_place p WHERE ps.place_id=p.place_id AND ps.invigilator_id=i.invigilator_id and ((p.start_time >= $place[start_time] and p.start_time <= $place[end_time]) OR (p.end_time >= $place[start_time] and p.end_time <= $place[end_time]) OR (p.start_time <= $place[start_time] and p.end_time >= $place[end_time])))";
        
		//已经分配有考场的监考人员列表
		$failed_ids = array();
		$success_ids = array();
        $result = $this->db->query($sql)->result_array();
        foreach ($result as $row)
        {
            $inserts[] = array(
                'place_id' 		 => $place_id,
                'invigilator_id' => $row['invigilator_id'],
            );
            $success_ids[] = $row['invigilator_id'];
        }
        
        $failed_ids = array_diff($ids, $success_ids);
        
        $res = 0;
        if ($inserts)
        {
            // 关闭错误信息，防止 unique index 冲突出错
            $this->db->db_debug = false;
            $this->db->insert_batch('exam_place_invigilator', $inserts);
            $res = $this->db->affected_rows();
        }
        
        $back_url = 'admin/place_invigilator/index/'.$place_id;
        if ($res < 1) {
            message('监考人员添加失败', $back_url);
        } else {
        	$failed_i_names = array();
        	foreach ($failed_ids as $id) {
        		$i_name = ExamInvigilatorModel::get_invigilator_by_id($id, 'name');
        		if ($i_name) {
        			$failed_i_names[] = $i_name;
        		}
        	}
        	$msg = array('监考人员添加成功');
        	if (count($failed_i_names)) {
        		$msg[] =  '<hr/>以下监考人员已经分配了考场:<br/>' . implode('<br/>', $failed_i_names);
        	}
            message(implode('', $msg), $back_url);
        }
    }

    // 批量删除 考场监考人员 信息
    public function batch_delete()
    {   
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $ids = $this->input->post('ids');
        $place_id = (int)$this->input->post('place_id');
        if (empty($ids) OR ! is_array($ids))
        {
            message('请至少选择一项');
            return;
        }
        
        $place = ExamPlaceModel::get_place($place_id);
        if (!count($place))
        {
        	message('考场信息不存在');
        	return;
        }
        
        //控制考场只能在未开始考试操作
        $no_start = ExamPlaceModel::place_is_no_start($place_id);
        if (!$no_start) {
        	message('该考场正在考试或已结束，无法做此操作');
        }

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url) && $place_id)
        {
            $back_url = 'admin/place_invigilator/index/'.$place_id;
        }

        $this->db->where_in('id', $ids)->delete('exam_place_invigilator');
        
        message('删除成功', $back_url);
    }
    
    // 删除 考场监考人员 信息
    public function delete($id = 0)
    {    
    	if ( ! $this->check_power('exam_manage')) return;
    	
        $id = intval($id);
        $place_id = (int)$this->input->post('place_id');
        if (!$id)
        {
            message('该条信息不存在');
            return;
        }
        
        $place = ExamPlaceModel::get_place($place_id);
        if (!count($place))
        {
        	message('考场信息不存在');
        	return;
        }
        
        //控制考场只能在未开始考试操作
        //$no_start = ExamPlaceModel::place_is_no_start($place_id);
        //if (!$no_start) {
        //	message('该考场正在考试或已结束，无法做此操作');
        //}

        $back_url = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
        if (empty($back_url) && $place_id)
        {
            $back_url = 'admin/place_invigilator/index/'.$place_id;
        }

        $this->db->where('id', $id)->delete('exam_place_invigilator');
       
        message('删除成功', $back_url);
    }
}
