<?php
/**
 * 前端学生注册控制器类 student
 */
class S_Controller extends MY_Controller
{
    function __construct()
    {
        parent::__construct();
        defined('__HTML_URL__') || define('__HTML_URL__', base_url());
        $this->load->set_template(APPPATH.'views/student');
    }
}

