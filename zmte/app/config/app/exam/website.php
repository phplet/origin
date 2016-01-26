<?php if ( ! defined('BASEPATH')) exit();?>
<?php
//机考全局信息配置
$config['exam_config'] = array (
		//机考系统别名
		'system_name'		=> '新步伐在线上机考试系统',

		//开始时间段配置
		'time_period_limit' => array(
				//等待过程(开考前start ~ end 分钟)
				'wait'	=> array(
								'start' => '60',
								'end' 	=> '30',
						),

				//开考前登录(开考前30 分钟)
				'login'	=> '30',

				//交卷后时间段(交卷后10 分钟)
				'submit'=> '10',

				//监考人员允许操作时间段
				'invigilator'=> array(
						'before_start' => '60', //考前时间(分钟)
						'after_end' => '0'//考后时间(分钟)
				),
		),

		//倒计时刷新时间(单位：秒)
		'refresh_time' => array(
				'submit_success' => '5'//交卷成功页
		)
);
