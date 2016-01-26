<?php
/**
 * 管理员操作日志模块 AdminLogModel
 * @file    AdminLog.php
 * @author  BJP
 * @final   2015-06-23
 */
class AdminLogModel
{
    /**
     * 添加管理员操作日志
     *          
     * @param   string $action     操作类型
     * @param   string $content    操作对象
     * @param   string $log_info   操作信息
     */
    public static function add($action, $content, $log_info = '')
    {
        $CI = &get_instance();
        $CI->lang->load('admin/admin_log');
        $action  = $action  ? $CI->lang->line('log_'.$action)  : '';
        $content = $content ? $CI->lang->line('log_'.$content) : '';
        $op = $action . $content;
        if ($op)
        {
            $log_info = $log_info ? $op . "($log_info)" : $op;
        }
        $log = array(
            'admin_id'   => Fn::sess()->userdata('admin_id'),
            'log_info'   => $log_info,
            'ip_address' => $CI->input->ip_address(),
            'log_time'   => time()
        );
        Fn::db()->insert('rd_admin_log', $log);
    }
    
    /*
     * 获取管理员操作日志信息
     * @param string $log_info
     */
    public static function get_admin_log($log_info = '', $item = '*')
    {
        if ($log_info == '')
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_admin_log WHERE log_info = ?
EOT;
        $row = Fn::db()->fetchRow($sql, $log_info);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }
}
