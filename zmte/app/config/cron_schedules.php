<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * crontab任务计划配置
 */
/*
 | crontab 任务配置
 $cron_schedule['job_code'] = array(
 		'name'		=> '任务描述',
		'schedule'  => array(
				'config_path' => '',            // cron表达式的标识 用于在配置文件或数据库中获取表达式 直接指定时为空
				'cron_expr'   => ''  			// 直接指定cron表达式 在配置文件或数据库中获取表达式为空
		),
		'run'       => array(
				'filepath'  => 'cron',          // 文件所在的目录 相对于APPPATH
				'filename'  => 'Myclass.php',   // 文件名
				'class'     => 'MyClass',       // 类名 如果只是简单函数 可为空
				'function'  => 'clear_log',     // 要执行的函数
				'params'    => array()          // 需要传递的参数
		)
);
*/
//计算考生成绩
$cron_schedule['cal_test_score'] = array(
		'name'		=> '计算考生成绩',
		'schedule'  => array(
				'config_path' => '',            
				'cron_expr'   => '*/20 9-22 * * 1-5'  
		),
		'run'       => array(
				'filepath'  => 'cron',        
				'filename'  => 'exam.php',   	
				'class'     => 'Exam',
				'function'  => 'cal_test_score',     
				'params'    => array()         
		)
);





