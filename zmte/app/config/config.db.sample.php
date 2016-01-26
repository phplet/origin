<?php
/*
 * 选择数据库
 */
$db['active_group'] = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
$db['active_record'] = TRUE;

/*** 开发环境连接数据库 ***/
$db['development']['hostname'] = '192.168.11.252';
$db['development']['username'] = 'zeming';
$db['development']['password'] = '123456';
$db['development']['database'] = 'zmte_development';
$db['development']['dbdriver'] = 'mysql';
$db['development']['dbprefix'] = 'rd_';
$db['development']['pconnect'] = TRUE;
$db['development']['db_debug'] = TRUE;
$db['development']['cache_on'] = FALSE;
$db['development']['cachedir'] = '';
$db['development']['char_set'] = 'utf8';
$db['development']['dbcollat'] = 'utf8_general_ci';
$db['development']['swap_pre'] = '{pre}';
$db['development']['autoinit'] = TRUE;
$db['development']['stricton'] = FALSE;
/*** 开发环境连接数据库 ***/

/*** 测试环境连接数据库 ***/
$db['testing']['hostname'] = '192.168.11.252';
$db['testing']['username'] = 'zeming';
$db['testing']['password'] = '123456';
$db['testing']['database'] = 'zmte_testing';
$db['testing']['dbdriver'] = 'mysql';
$db['testing']['dbprefix'] = 'rd_';
$db['testing']['pconnect'] = FALSE;
$db['testing']['db_debug'] = TRUE;
$db['testing']['cache_on'] = FALSE;
$db['testing']['cachedir'] = '';
$db['testing']['char_set'] = 'utf8';
$db['testing']['dbcollat'] = 'utf8_general_ci';
$db['testing']['swap_pre'] = '{pre}';
$db['testing']['autoinit'] = TRUE;
$db['testing']['stricton'] = FALSE;
/*** 测试环境连接数据库 ***/

/*** 正式环境连接数据库 ***/
$db['production']['hostname'] = '192.168.1.121';
$db['production']['username'] = 'zeming';
$db['production']['password'] = '123456';
$db['production']['database'] = 'zmte_production';
$db['production']['dbdriver'] = 'mysql';
$db['production']['dbprefix'] = 'rd_';
$db['production']['pconnect'] = TRUE;
$db['production']['db_debug'] = FALSE;
$db['production']['cache_on'] = FALSE;
$db['production']['cachedir'] = '';
$db['production']['char_set'] = 'utf8';
$db['production']['dbcollat'] = 'utf8_general_ci';
$db['production']['swap_pre'] = '{pre}';
$db['production']['autoinit'] = TRUE;
$db['production']['stricton'] = FALSE;
/*** 正式环境连接数据库 ***/

/*** 阅卷系统数据库 ***/
$db['zmoss']['hostname'] = '192.168.1.5';
$db['zmoss']['username'] = 'zmoss';
$db['zmoss']['password'] = '123456';
$db['zmoss']['database'] = 'zmoss';
$db['zmoss']['dbdriver'] = 'pgsql';;
/*** 阅卷系统数据库 ***/