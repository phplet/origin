<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
$config['redis']['hostname'] = '127.0.0.1';
$config['redis']['port'] = '6379';  
//这些前缀可以设置不同的值，根据实际需要
$config['redis']['admin'] = 'pro_admin:';
$config['redis']['exam'] = 'pro_exam:';
$config['redis']['demo'] = 'pro_demo:';