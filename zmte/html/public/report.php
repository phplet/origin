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
	
	$arr = explode('-', $code);
	$id = '';
	if (count($arr) == 4 
	    && end($arr) == 1)
	{
	    list($rule_id, $id, $subject_id) = $arr;
	    $id = 'class_' . $id;
	}
	else if (count($arr) == 4 
	    && end($arr) == 2)
	{
	    list($rule_id, $id, $subject_id) = $arr;
	    $id = 'teacher_' . $id;
	}
	else if (count($arr) == 4
	    && end($arr) == 3)
	{
	    list($rule_id, $id, $subject_id) = $arr;
	    $id = 'transcript_' . $id;
	}
	else 
	{
	    list($rule_id, $id, $subject_id) = $arr;
	}
	
	$rule_id = !is_null($rule_id) ? intval($rule_id) : 0;

	if(!is_null($subject_id)) 
	{
		$subject_id = intval($subject_id);
	} 
	else 
	{
		$subject_id = 0;
	}

	if (!$rule_id || !$id)
	{
		die('404 not found');
	}
	
	$cache_path = realpath('../../') . '/cache/html/report';
	$file_name = "{$cache_path}/{$rule_id}/{$id}/{$subject_id}.html";

	if (!file_exists($file_name))
	{
		die('404 not found');		
	}
	
	echo get_file($file_name);
}