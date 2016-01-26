<?php
error_reporting(0);

$t = isset($_GET['t']) ? $_GET['t'] : 'css';
if ($t == 'css')
{
	header('Content-type: text/css');
}
else 
{
	header('Content-type: text/javascript');
}

ob_start("compress");
function compress($buffer) {
	/* remove comments */
	$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
	
	/* remove tabs, spaces, newlines, etc. */
	$buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);
	
	return $buffer;
}

//设置输出头缓存(单位:秒)
$cache = false;

$keyword = isset($_GET['k']) ? $_GET['k'] : '';
$keyword = strip_tags($keyword);
$keyword = urldecode($keyword);
if (!file_exists($keyword)) 
{
	echo '404, file not found.';
}
else
{
	if ($cache)
	{
		$interval = 3600;
		$now = time();
		header ("Cache-Control: max-age=$now");
		header ("Expires: " . gmdate ("r", ($now + $interval)));
	}
	
	$cache_content = file_get_contents($keyword);
	if ( ! preg_match("/(\d+TS--->)/", $cache_content, $match))
	{
		echo '';
	}
	else
	{
		echo (str_replace($match['0'], '', $cache_content));
	}
}

ob_end_flush();

