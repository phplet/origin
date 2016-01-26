<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

/*
|--------------------------------------------------------------------------
| 可用挂载点
|--------------------------------------------------------------------------
|   pre_system
|    系统执行的早期调用.仅仅在benchmark 和 hooks 类 加载完毕的时候. 没有执行路由或者其它的过程.
|   pre_controller
|    在调用你的任何控制器之前调用.此时所用的基础类,路由选择和安全性检查都已完成.
|   post_controller_constructor
|    在你的控制器实例化之后,任何方法调用之前调用.
|   post_controller
|    在你的控制器完全运行之后调用.
|   display_override
|    覆盖 _display()函数, 用来在系统执行末尾向web浏览器发送最终页面.这允许你用自己的方法来显示.
|    注意，你需要通过 $this->CI =& get_instance() 引用 CI 超级对象，然后这样的最终数据可以通过
|    调用 $this->CI->output->get_output() 来获得。
|   cache_override
|    可以让你调用自己的函数来取代output类中的_display_cache() 函数.这可以让你使用自己的缓存显示方法
|   post_system
|    在最终着色页面发送到浏览器之后,浏览器接收完最终数据的系统执行末尾调用
*/

$config['pre_controller'];

/* End of file hooks.php */
/* Location: ./application/config/hooks.php */