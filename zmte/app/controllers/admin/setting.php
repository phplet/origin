<?php if ( ! defined('BASEPATH')) exit();
class Setting extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if ( ! $this->check_power('system_config')) return;
        
        if ($this->input->post('dosubmit'))
        {
            $setting = array(
                'site_name' => trim($this->input->post('site_name')),
                'template' => trim($this->input->post('template')),
                'signup_title' => trim($this->input->post('signup_title')),
            );
            foreach ($setting as $key => $val)
            {
                $item = array(
                    'valname'  => $key,
                    'valvalue' => $val,
                );
                $this->db->replace('config', $item);
            }
            $this->_write_cache();
            message('修改成功', 'admin/setting/index');
        }
        else
        {
            $setting = array();
            $query = $this->db->get('config');
            foreach ($query->result_array() as $row)
            {
                $setting[$row['valname']] = $row['valvalue'];
            }
            
            $data['setting'] = $setting;
            $data['tpl_list'] = $this->_get_templates();

            // 模版
            $this->load->view('setting/index', $data);
        }
    }

    private function _write_cache()
    {
        $setting = array();
        $query = $this->db->get('config');
        foreach ($query->result_array() as $row)
        {
            $setting[$row['valname']] = $row['valvalue'];
        }

        $cache_file = APPPATH.'config/app/webconfig.php';
        $this->load->helper('file');

        $cache = "<?php if ( ! defined('BASEPATH')) die;\r\n".'$config[\'webconfig\'] = ' . var_export($setting, TRUE) . ";\r\n";
        write_file($cache_file, $cache);

        $cache_file = APPPATH.'config/app/webconfig.sample.php';

        $cache = "<?php if ( ! defined('BASEPATH')) die;\r\n".'$config[\'webconfig\'] = ' . var_export($setting, TRUE) . ";\r\n";
        write_file($cache_file, $cache);
    }
    
    // 获取前台可用模板
    private function _get_templates()
    {
        /* 获得可用的模版 */
        $available_templates = array();

        $template_dir = APPPATH . 'views/student';
        $dh = @opendir($template_dir);
        while ($file = @readdir($dh))
        {
            if ($file != '.' && $file != '..' && is_dir($template_dir. '/' . $file) && $file != '.svn')
            {
                $tpl = array(
                    'code' => $file,
                    'screenshot' => $template_dir.'/'.$file.'/images/screenshot.png'
                );
                $available_templates[] = $tpl;
            }
        }
        @closedir($template_dir);

        return $available_templates;
    }
    


}
