<?php if ( ! defined('BASEPATH')) exit();

/**
 * 机考考生行为
 * @var string
 */
define('EXAM_LOG_LOGIN', 	'1');//登录
define('EXAM_LOG_SUBMIT', 	'2');//交卷
define('EXAM_LOG_CHEAT', 	'3');//作弊
define('EXAM_LOG_RELOGIN_IN_TESTTING', 	'4');//考试中尝试重新登录
// define('EXAM_LOG_SUBMIT_FORCE', 	'5');//强行交卷
define('EXAM_LOG_WINDOW_BLUR', 	'6');//考生离开当前考试窗口(考试页面活跃，焦点离开)
define('EXAM_LOG_LEAVE_TEST_PAGE', 	'7');//考试关闭考试窗口（未交卷）
define('EXAM_LOG_RELOGIN_AFTER_LEAVE_TEST_PAGE', 	'8');//上一次关闭考试窗口后再次登录
define('EXAM_LOG_WINDOW_BLUR_LONG_TIME', 	'9');//离开考试界面1分钟以上
define('EXAM_LOG_OUT_STUDENT', 	'10');//被踢出
define('EXAM_LOG_IN_STUDENT', 	'11');//恢复
