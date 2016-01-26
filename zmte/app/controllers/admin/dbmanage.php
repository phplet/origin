<?php if ( ! defined('BASEPATH')) exit();
/**
 * 数据库备份及恢复
 */
class Dbmanage extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 数据库备份
     */
    public function backup()
    {
        set_time_limit(0);
        
        $data = array();
        
        if ($_POST)
        {
            $backup_dir = BASEPATH . '../cache/backup/';
            if (!is_dir($backup_dir))
            {
                mkdir($backup_dir, '0777', true);
            }
            
            include(dirname(__FILE__) . '/../../config/config.db.php');
            $cfg = $db[$db['active_group']];
            
            $is_export_student = intval($this->input->post('is_export_student'));
            
            $sql_file = DbmanageModel::backupTables($cfg['database'], $backup_dir, array('*'), $is_export_student);
            
            if (file_exists($backup_dir . $sql_file))
            {
                require_once APPPATH.'libraries/Pclzip.php';
                $save_file = $backup_dir . "/zmte_database.zip";
                if (is_file($save_file))
                {
                    @unlink($save_file);
                }
		
                $archive = new PclZip($save_file);

        		//将文件进行压缩
        		$archive->create($backup_dir . $sql_file, PCLZIP_OPT_REMOVE_ALL_PATH);
        		
        		@unlink($backup_dir . $sql_file);
        		
        		Func::dumpFile('application/zip', $save_file, 'zmte_database_' . date('YmdHis') . '.zip');
        		
        		@unlink($save_file);
        		
        		redirect('/admin/dbmanage/backup');
            }
            else 
            {
                message('数据库备份失败，请稍后重试！');
            }
        }
        else
        {
            $this->load->view('dbmanage/backup', $data);
        }
    }
    
    /**
     * 数据库还原
     */
    public function restore()
    {
        set_time_limit(0);
        
        $data = array();
        
        if ($_POST)
        {
            if (!$_FILES['db_file'])
            {
                message('请上传文件！');
            }
            
            /**
             * 上传文件
             */
            $upload_path = BASEPATH . '/../cache/backup/';
            
            $txt = strtolower(end(explode('.', $_FILES['db_file']['name'])));
            
            if (!in_array($txt, array('sql', 'zip')))
            {
                message('数据文件格式不符合要求！');
            }
            
            $file_name = microtime(true) . '.' . $txt;
            $upload_file = $upload_path . $file_name;
            if (!is_dir($upload_path))
            {
                mkdir($upload_path, '0777', true);
            }
            
            if (!@move_uploaded_file($_FILES['db_file']['tmp_name'], $upload_file))
            {
                message('文件处理失败，请重新上传文件！');
            }
            else
            {
                if ($txt == 'zip')
                {
                    require_once APPPATH.'libraries/Pclzip.php';
                    
                    $archive = new PclZip($upload_file);
                    
                    $list = $archive->extract(PCLZIP_OPT_BY_NAME, $upload_file . "zmte.sql", PCLZIP_OPT_EXTRACT_AS_STRING);
                    
                    pr($list,1);
                }
            }
        }
        
        $this->load->view('dbmanage/restore', $data);
    }
}