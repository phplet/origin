<?php
/**
 * 系统维护
 */
if (false)
{
    require_once BASEPATH . '/core/Common.php';
    
    //设置网站重新开启时间
    $date = '2015-08-31 23:00:00';

    //格式化开启时间
    $date_str = date('Y年m月d日 H:i', strtotime($date));

    //格式化开启时间的毫秒数
    $system_date = strtotime($date) * 1000;

    //系统维护界面图片地址
    $search = array('admin', 'student', 'demo', 'public', 'exam');
    $replace = array('s', 's', 's', 's', 's');

    $http_host = get_http_host();
    $server_port = get_server_port();

    $http_url = str_replace($search, $replace, 'http://' . $http_host);
    
    if ($server_port != 80)
    {
        $http_url .= ':' . $server_port;
    }

    include("../common/main.php");
    exit;
}

