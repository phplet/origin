<?php
//======================全局处理方法 start==========================//
/**
 todo:
 	对cookie, post, get, files全局变量进行过滤
 */
$magic_quotes_gpc = get_magic_quotes_gpc();
@extract(daddslashes($_COOKIE));
@extract(daddslashes($_POST));
@extract(daddslashes($_GET));
if(!$magic_quotes_gpc) {
	$_FILES = daddslashes($_FILES);
}
function daddslashes($string, $force = 0) {
	if(!$GLOBALS['magic_quotes_gpc'] || $force) {
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = daddslashes($val, $force);
			}
		} else {
			$string = addslashes($string);
		}
	}
	return $string;
}
//=======================全局处理方法 end===========================//

$gets = $_GET;

//公共方法集合
$do_actions = array(
		'now' => 'get_now',
		'time_diff' => 'get_time_diff',
);

//检查参数
if (!isset($gets['act']) || !isset($do_actions[$gets['act']]) || !function_exists($do_actions[$gets['act']])) {
	die;
}

$func = $do_actions[$gets['act']];
unset($gets['act']);
$params = $gets;

$func($params);

/**
 * 返回系统当前时间
 */
function get_now()
{
	echo time();
	die;
} 

/**
 * 返回 某个时间 与 系统当前时间的相隔秒数
 * @return int 秒数
 */
function get_time_diff($params)
{
	$now = time();
	$time_start = isset($params['time']) ? $params['time'] : time();
	if (stripos($time_start, ":") === false) {
		if (is_int($time_start)) {
			echo $time_start - $now;
			return;
		} else {
			return;
		}
	} else {
		echo strtotime($time_start) - time();
		die;
	}
} 




