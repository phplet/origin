<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 扩展 Loader 类
 */
class MY_Loader extends CI_Loader
{
	function __construct()
	{
		parent::__construct();
	}
	
	/*
	 * 设置前端视图路径
	 * 
	 * $template : 模板名
	 */
	function set_template($template)
	{
        $this->_ci_view_paths = array($template . '/' => TRUE);
	}
	
}

/* End of file MY_Loader.php */
/* Location: ./application/core/MY_Loader.php */