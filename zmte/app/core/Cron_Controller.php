<?php
/*
 * crontab控制器类
 */
class Cron_Controller extends MY_Controller
{
    function __construct()
    {
        // this controller can only be called from the command line
        //if (!$this->input->is_cli_request()) show_error('Direct access is not allowed');
        
        parent::__construct();
        defined('__HTML_URL__') || define('__HTML_URL__', base_url());
        defined('__GLOBAL_HTML_URL__') || define('__GLOBAL_HTML_URL__', C('global_source_host'));
        $this->load->set_template(APPPATH.'views/cron');
    }
}

