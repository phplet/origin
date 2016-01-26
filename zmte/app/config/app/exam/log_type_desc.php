<?php if ( ! defined('BASEPATH')) exit();

/**
 * 机考考生行为类型描述
 * @var string
 */
class Log_type_desc
{
	public static $log_aliases = array(
				'login' => EXAM_LOG_LOGIN,
				'submit' => EXAM_LOG_SUBMIT,
				'cheat' => EXAM_LOG_CHEAT,
				'chang_ip_login' => EXAM_LOG_RELOGIN_IN_TESTTING,
				'window_blur' => EXAM_LOG_WINDOW_BLUR,
				'window_blur_long_time' => EXAM_LOG_WINDOW_BLUR_LONG_TIME,
	            'out_student' => EXAM_LOG_OUT_STUDENT,
	            'in_student' => EXAM_LOG_IN_STUDENT
	);
	
	public static $logs = array(
				EXAM_LOG_LOGIN => '登录',
				EXAM_LOG_SUBMIT => '交卷',
				EXAM_LOG_CHEAT => '作弊',
				EXAM_LOG_RELOGIN_IN_TESTTING => '考试中尝试重新登录',
// 				EXAM_LOG_SUBMIT_FORCE => '由于作弊，强行交卷',
	            EXAM_LOG_OUT_STUDENT=> '被踢出',
	            EXAM_LOG_IN_STUDENT=> '恢复',
				EXAM_LOG_WINDOW_BLUR => '离开考试窗口',
				EXAM_LOG_WINDOW_BLUR_LONG_TIME => '离开考试窗口 > 1分钟',
				EXAM_LOG_LEAVE_TEST_PAGE => '关闭答题中考试界面',
				EXAM_LOG_RELOGIN_AFTER_LEAVE_TEST_PAGE => '异常关闭并重新登录',
	);
	
	//异常行为
	public static $important_logs = array(
				EXAM_LOG_CHEAT => '#F00000',
				EXAM_LOG_RELOGIN_IN_TESTTING => '#1F10FF',
				EXAM_LOG_WINDOW_BLUR => '#1F10FF',
				EXAM_LOG_WINDOW_BLUR_LONG_TIME => '#1F10FF',
				EXAM_LOG_LEAVE_TEST_PAGE => '#1F10FF',
				EXAM_LOG_RELOGIN_AFTER_LEAVE_TEST_PAGE => '#1F10FF',
	            EXAM_LOG_OUT_STUDENT => '#1F10FF',
	           EXAM_LOG_IN_STUDENT => '#1F10FF',
	);
	
	/**
	 * 考试行为描述
	 * @param int $type
	 * @return string
	 */
	public static function get_log_type_desc($type)
	{
		$logs = self::$logs;
		if (!isset($logs[$type])) {
			return '--';
		}
		
		$log = $logs[$type];
		if (self::is_important_log_type($type)) {
			$color = self::$important_logs[$type];
			$log = '<font color="' . $color . '">' . $log . '</font>';
		}
		
		return $log;
	}
	
	/**
	 * 是否为异常行为
	 * @param int $type
	 */
	public static function is_important_log_type($type)
	{
		$important_logs = self::$important_logs;
		return isset($important_logs[$type]);
	}
	
	/**
	 * 获取考生日志别名
	 * @param int $type
	 */
	public static function get_log_alia($type)
	{
		$log_aliases = self::$log_aliases;
		return isset($log_aliases[$type]) ? $log_aliases[$type] : false;
	}
	
	
}
