<!DOCTYPE html>
<html lang="en">
<head>
<title>404 Page Not Found</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">

::selection{ background-color: #E13300; color: white; }
::moz-selection{ background-color: #E13300; color: white; }
::webkit-selection{ background-color: #E13300; color: white; }

body {
	background-color: #fff;
	margin: 40px;
	font: 13px/20px normal Helvetica, Arial, sans-serif;
	color: #4F5155;
}

a {
	color: #003399;
	background-color: transparent;
	font-weight: normal;
	text-decoration:none;
}

a:hover{
	text-decoration:underline;
}

h1 {
	color: #444;
	background-color: transparent;
	border-bottom:1px dotted #ccc;
	font-size: 19px;
	font-weight: normal;
	margin: 0 0 14px 0;
	padding: 14px 15px 10px 15px;
}

code {
	font-family: Consolas, Monaco, Courier New, Courier, monospace;
	font-size: 12px;
	background-color: #f9f9f9;
	border: 1px solid #D0D0D0;
	color: #002166;
	display: block;
	margin: 14px 0 14px 0;
	padding: 12px 10px 12px 10px;
}

#container {
	margin: 10px;
	border: 1px solid #D0D0D0;
	-webkit-box-shadow: 0 0 8px #D0D0D0;
}

p {
	margin: 12px 15px 12px 15px;
}

hr {
	border:none;
	border-bottom:1px dotted #ccc;
}

</style>
</head>
<body>
	<?php $is_production_mode = defined('ENVIRONMENT') && ENVIRONMENT === 'production';?>
	<div id="container">
		<h1><?php echo $is_production_mode ? '哦哦~貌似发生错误了，^_^' : $heading; ?></h1>
		<p><?php echo $is_production_mode ? '抱歉，你所需要找的内容不在这里，也许你要到其他页面看看。' : $message; ?></p>
		<hr/>
		<p><a href="javascript:history.back();">返回上一页</a></p>
	</div>
</body>
</html>