<?php
header("Content-type: text/html; charset=utf-8");

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

/**
 * 获取文件内容
 */
function get_file($file)
{
	$opts = array(
			'http' => array(
					'method'	=> "GET",
					'timeout'	=> 5,//单位秒
			)
	);
	
	$content = file_get_contents($file, false, stream_context_create($opts));
	
	return $content === false ? '' : $content; 
}