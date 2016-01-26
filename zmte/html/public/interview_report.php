<?php

include_once '../../config.php';

/*
 * 公共接口 - 测评报告
 */
require_once 'common.php';

$gets = $_GET;

//公共方法集合
$do_actions = array(
		'get_html' => 'get_report_cache_html',
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
 * 访问 测评报告静态文件
 */
function get_report_cache_html($params)
{
	$code = isset($params['code']) ? trim($params['code']) : '';

	if ($code == '')
	{
		die('404 not found');
	}

	$code = urldecode(base64_decode($code));
	
	list($rule_id, $uid, $subject_id) = explode('-', $code);
	
	$rule_id = !is_null($rule_id) ? intval($rule_id) : 0;

	if(!is_null($subject_id)) {
		$subject_id = intval($subject_id);
	} else {
		$subject_id = 0;
	}

	$uid = !is_null($uid) ? intval($uid) : 0;
	
	if (!$rule_id || !$uid)
	{
		die('404 not found');
	}
	
	$cache_path = realpath('../../') . '/cache/html/interview_report';
	$file_name = "{$cache_path}/{$rule_id}/{$uid}/{$subject_id}.html";

	if (!file_exists($file_name))
	{
		die('404 not found');		
	}
	
	echo get_file($file_name);
}