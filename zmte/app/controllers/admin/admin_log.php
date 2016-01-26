<?php if ( ! defined('BASEPATH')) exit();
class Admin_log extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @description    管理员操作日志列表
     * @author                
     * @final                 
     * @param int $admin_id 管理员id
     * @param date $begin_time 开始时间
     * @param date $end_time 结束时间
     */
    public function index()
    {
        if ( ! $this->check_power('admin_log_list')) return;

        $cpusers = CpUserModel::get_cpuser_list();
        
        // 查询条件
        $where  = array();  
        $param  = array();
        $search = array();
        if ($search['admin_id'] = intval($this->input->get('admin_id')))
        {
            $where[] = "admin_id='$search[admin_id]'";
            $param[] = "admin_id=$search[admin_id]";
        }
        // 操作时间
        $begin_time = $this->input->get('begin_time');
        $end_time   = $this->input->get('end_time');
        if ($btime = (int)strtotime($begin_time))
        {
            $search['begin_time'] = $begin_time;
            $where[] = "log_time >= $btime";
            $param[] = "begin_time=$begin_time";
        }
        if ($etime = (int)strtotime($end_time))
        {
            $etime += 86400;
            $search['end_time'] = $end_time;
            $where[] = "log_time < $etime";
            $param[] = "end_time=$end_time";
        }
        
        $where = $where ? implode(' AND ', $where) : ' 1 ';

       	/*
       	 * 统计当前数据条数
       	 */
        $sql = "SELECT COUNT(id) nums FROM {pre}admin_log WHERE $where";
        $res = $this->db->query($sql);
        $row = $res->row_array();
        $total = $row['nums'];

        /*
		 * 分页读取数据列表，并处理相关数据
         */
        $size   = 15;
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;
        $list = array();
        if ($total)
        {
            $sql = "SELECT *
                    FROM {pre}admin_log 
                    WHERE $where ORDER BY id DESC LIMIT $offset,$size";
            $res = $this->db->query($sql);
            foreach ($res->result_array() as $row) 
            {
                $row['admin_user'] = isset($cpusers[$row['admin_id']]['admin_user']) ? $cpusers[$row['admin_id']]['admin_user'] : '';
                $row['log_time'] = date('Y-m-d H:i:s', $row['log_time']);
                $list[] = $row;
            }
        }
        $data['list'] = $list;

        // 分页
        $purl = site_url('admin/admin_log/index') . ($param ? '?'.implode('&',$param) : '');
		$data['pagination'] = multipage($total, $size, $page, $purl);
        $data['cpusers'] = $cpusers;
        $data['search'] = $search;

        // 模版
        $this->load->view('admin_log/index', $data);
    }
    
    /**
     * @description    批量删除管理员操作日志
     * @author
     * @final
     * @param array $ids 管理员id
     */
    public function batch_delete()
    {
        if ( ! $this->check_power('admin_log_manage')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {        
            $this->db->where_in('id', $ids)->delete('admin_log');
            admin_log('delete', 'admin_log', implode(',', $ids));
            message('日志删除成功！', 'admin/admin_log/index');
        }
        else
        {
            message('请选择要删除的日志');
        }
    }
    
    /**
     * @description    按日期清除日志管理员操作日志
     * @author
     * @final
     * @param string $deadline 清除管理员日志的期限
     */
    public function clear_log($deadline = 0)
    {
        if ( ! $this->check_power('admin_log_manage')) return;

        $deadline = trim($deadline);
        $where    = array();
        switch ($deadline)
        {
            case 'week':
                $where = array('log_time <' => time()-86400*7);
                $mtype = '一个星期前';
                break;
            case 'month':
                $where = array('log_time <' => time()-86400*30);
                $mtype = '一个月前';
                break;
            case 'all':
                $mtype = '全部';
                break;
            default:
                message('链接错误，删除失败');
                return;

        }
        try
        {
            if (empty($where))
            {
                $this->db->truncate('admin_log');                
            }
            else
            {
                $this->db->where($where)->delete('admin_log');
            }
            admin_log('delete', 'admin_log', $mtype);
            message('日志删除成功！', 'admin/admin_log/index');
        }
        catch(Exception $e)
        {
            message('日志清除失败：'.$e->getMessage());
        }
    }
}
