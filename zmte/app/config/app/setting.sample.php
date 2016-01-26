<?php if ( ! defined('BASEPATH')) exit();

$config['hash_code'] = 'zeming@new-steps';

$config['admin_page_size'] = 15;

//学生来源
$config['student_source'] = array(
    //'0' => '系统内置', 
    '1' => '后台添加',
    '2' => '后台批量导入',
    '3' => '普通注册',
    '4' => '竞赛考试'
);

// 年级段
$config['grade_period'] = array(
    '1' => '小学',
    '2' => '初中',
    '3' => '高中',
);

// 年级
$config['grades'] = array(
    '1' => '一年级',
    '2' => '二年级',
    '3' => '三年级',
    '4' => '四年级',
    '5' => '五年级',
    '6' => '六年级',

    '7' => '初一',
    '8' => '初二',
    '9' => '初三',

    '10' => '高一',
    '11' => '高二',
    '12' => '高三',
);

// -- 学科，以下内容(start -> end)由程序自动维护，请勿修改备注的开始结束标识 --- //
// start:subject_cache
$config['subject'] = array (
  1 => '语文',
  2 => '数学',
  3 => '英语',
  4 => '物理',
  5 => '化学',
  6 => '生物',
  7 => '政治',
  8 => '历史',
  9 => '地理',
  10 => '科学',
  11 => '综合',
  12 => '通用技术',
  13 => '学科兴趣',
  14 => '学习风格',
  15 => '职业兴趣',
  16 => '职业能力倾向',
  18 => '信息技术',
  19 => '思想品德',
  20 => '社会与历史',
);
// end:subject_cache

// 试题文理科属性
$config['subject_type'] = array(
    '-1' => '', '0' => '文理通用', '1' => '文科', '2' => '理科'
);

//试题题型 
$config['q_type'] = array(
    '0' => '题组', 
    '1' => '单选', 
    '2' => '不定项', 
    '3' => '填空', 
    '4' => '完形填空', 
    '5' => '匹配题', 
    '6' => '选词填空', 
    '7' => '翻译题' ,
    '8' => '阅读填空',
    '9' => '连词成句',
    '10' => '解答题',
    '11' => '作文',
    '12' => '听力',
    '13' => '改错',
    '14' => '单选不定分',
    '15' => '组合题',
);

//考试方式
$config['test_way'] = array(
		'1' => '机考',
		'2' => '纸质',
);

// 考纲
$config['qtype'] = array(
    '0' => '题组', 
    '1' => '单选', 
    '2' => '不定项', 
    '3' => '填空', 
    '4' => '完形填空', 
    '5' => '匹配题', 
    '6' => '选词填空', 
    '7' => '翻译题',
    '8' => '阅读填空',
    '9' => '连词成句',
    '10' => '解答题',
    '11' => '作文',
    '12' => '听力',
    '13' => '改错',
    '14' => '单选不定分',
    '15' => '组合题',
  );

/* 题组类型试题 */
$config['q_type_group'] = array('0', '4', '5', '6', '8', '12', '13', '15');

/**
 * 难易度区间
 * 为避免数据两端判断分段取小数点后四位
 */
$config['difficulty_area'] = array(
    array(0, 29.9999), array(29.9999, 60.0001), array(60.0001, 100)
);

//题型标签
$config['q_tags'] = array(
    '5' => array(
        '1' => '6选5', 
        '2' => '5选5'
    ),
    '6' => array(
        '1' => '10选10',
        '2' => '12选10',
        '3' => '15选10'
    )
);
//10选10” “12选10” “15选10”

// 奖项类型
$config['awards_type'] = array(
    '1' => '挑战杯',
    '2' => '华杯赛'    
);

// 奖项级别
$config['awards_level'] = array(
    '1' => array('1' => '一等奖', '2' => '二等奖', '3' => '三等奖'),
    '2' => array('1' => '一等奖', '2' => '二等奖', '3' => '三等奖'),
    '3' => array('1' => '一等奖', '2' => '二等奖', '3' => '三等奖'),
);


// 家庭背景
$config['family_bg'] = array(
    '1' => '国家与社会管理者',
    '2' => '经理人员',
    '3' => '私营企业主',
    '4' => '专业技术人员（教师、医生等）',
    '5' => '办事人员',
    '6' => '个体工商户',
    '7' => '商业服务业员工',
    '8' => '产业工人',
    '9' => '农业劳动者',
    '10' => '城市无业、失业半失业者',
    '99' => '其他',
);

// 语言
$config['interview_lang'] = array(
    '1' => '汉语',
    '2' => '英语',
);

// -- 面试分类，以下内容(start -> end)由程序自动维护，请勿修改备注的开始结束标识 --- //
// start:interview_type
$config['interview_type'] = array (
  1 => 
  array (
    'type_id' => '1',
    'type_name' => '观点想法',
    'pid' => '0',
  ),
  2 => 
  array (
    'type_id' => '2',
    'type_name' => '知识面',
    'pid' => '0',
  ),
  5 => 
  array (
    'type_id' => '5',
    'type_name' => '语文',
    'pid' => '2',
  ),
  6 => 
  array (
    'type_id' => '6',
    'type_name' => '数学',
    'pid' => '2',
  ),
  7 => 
  array (
    'type_id' => '7',
    'type_name' => '物理',
    'pid' => '2',
  ),
  8 => 
  array (
    'type_id' => '8',
    'type_name' => '化学',
    'pid' => '2',
  ),
  3 => 
  array (
    'type_id' => '3',
    'type_name' => '解决问题的能力',
    'pid' => '0',
  ),
);
// end:interview_type

$config['email_template'] = array(
    'register' => array(
        'subject' => '【温馨提示】请验证您的邮箱',
        'tpl'     => 'mails/register.phtml',
    ),
    'validate' => array(
        'subject' => '【温馨提示】请验证您的邮箱',
        'tpl'     => 'mails/validate.phtml',
    ),
    'reset_password' => array(
        'subject' => '【温馨提示】重置密码',
        'tpl'     => 'mails/reset.phtml',
    ),
		
	//=====admin====
    'import_cpuser_success' => array(
        'subject' => '【温馨提示】重置密码',
        'tpl'     => 'cpuser/mails/import_success.phtml',
    ),
		
	//=====report====
    'send_zip' => array(
        'subject' => '【重要信息】您的测评报告已经生成,请查收',
        'tpl'     => 'report/mails/send_zip.phtml',
    ),
	'report_general_notice' => array(
			'subject' => "【系统消息】测评报告{subject}已经生成",
			'tpl'     => 'mails/report_general_notice.phtml',
	),
);

// --机考--考试状态
$config['exam_status'] = array(
		'0' => '不启用', 
		'1' => '启用',
		'2' => '已结束', 
);

// -- 评估管理 --
//=评测规则--等级
$config['evaluate_comparison_level'] = array (
		1 => '学校',
		2 => '地市',
		3 => '省份',
);

//=地区 不同级别深度 别名
$config['region_type'] = array (
		0 => '国家',
		1 => '省份',
		2 => '市',
		3 => '区县',
		4 => '街道/道路',
);

// --认知过程--
$config['know_process'] = array (
		1 => '记忆',
		2 => '理解',
		3 => '应用',
);

$config['lifetime']=1800;
$config['cache_dir']="D:/20150411/zmexam/temp/";

//系统版本号
//$ip = get_server_addr();
$config['version'] = 'v2.2.6-build20160119';

//公司名称
$config['company_shortname'] = '择明教育科技';
$config['company_fullname'] = '杭州择明教育科技有限公司';

//考试联盟
$config['exam_ticket_maprule'] = array(
    0 => array('title' => '默认'),
    1 => array('title' => '考试联盟'),
    2 => array('title' => '杭州市临平城东中学'),
    3 => array('title' => '杭州风帆中学'),
    4 => array('title' => '20151225余杭区九月联考')
);

//虚拟货币名称
$config['virtual_currency'] = array(
    'name' => '择明通宝',
    'unit' => '个',
    'fullname' => '个择明通宝');

//发送电子邮件
$config['sendmail'] = array(
    'from_mail' => 'service@new-steps.com',
    'from_name' => '新步伐在线测评');

// 产品前置数据
$config['product_prefixinfo'] = array(
    'base' => '学习概况',
    'score' => '学习成绩',
    /*
    'practice' => '社会实践情况',
    'selfwish' => '学生意愿',
    'parentwish' => '家长意愿'*/
);

// 如果有该选项,则简化注册
$config['register_simple'] = true;

// 默认每页显示记录数
$config['default_perpage_num'] = 15;

// 是否启用paycharge_enable 充值功能
$config['paycharge_enable'] = false;

//交易类型
$config['trade_type'] = array(
    '1' => '支付宝充值',
    '2' => '系统充值',
    '3' => '购买产品',
    '4' => '择明通宝转账',
);

// 自由测报告是否采用gz压缩（保存入sfe_data字段）同时将学科写入sfe_subjectid字段
$config['sfe_data_gz'] = false;

// 外部调用登录用户验证配置项
$config['loginverify'] = array(
    'zmcat' => array(
        'name' => '新步伐在线学习',
        'hashcode' => 'new-steps.com2015', 
        'urlprefix' => 'http://zmcat.new-steps.com',
        'fromid' => 1),
    'zmexam' => array(
        'name' => '新步伐在线机考',
        'hashcode' => 'new-steps.com2015', 
        'urlprefix' => 'http://exam.new-steps.com:8080',
        'fromid' => 'zmexam')
);

// 是否开启学习网继续学习功能
$config['zmcat_studyplus_enabled'] = false;


// 是否启用统计代码
$config['tongji_script'] = <<<EOT
<script type="text/javascript">
var _hmt = _hmt || [];
(function() {
      var hm = document.createElement("script");
        hm.src = "//hm.baidu.com/hm.js?c6984a9138972ada343eabba3384e4a5";
        var s = document.getElementsByTagName("script")[0]; 
          s.parentNode.insertBefore(hm, s);
})();
</script>
EOT;
