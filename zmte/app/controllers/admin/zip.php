<?php if ( ! defined('BASEPATH')) exit();
class Zip extends A_Controller 
{
    public function __construct()
    {
        parent::__construct();
    }	
	
    // 列表展示
    public function index()
    {
    	$startime=time();
    	require_once APPPATH.'libraries/Pclzip.php';
		
		$zipname = "/temp/temp300.zip"; //压缩包存储路径和名称
		$archive = new PclZip($zipname);	//实例化这个PclZip类
		//要打包的文件或文件夹可为数组或一个字串中，用逗号分隔
		$files = array('mystuff/ad.gif','mystuff/alcon.doc','mystuff/alcon.xls','mystuff');
		//$files = 'mystuff/ad.gif,mystuff/alcon.doc,mystuff/alcon.xls';
		$v_list = $archive->create($files); //将文件进行压缩
		//$v_list = $archive->add('副本.php');
		//$v_list = $archive->create('pclzip',PCLZIP_OPT_REMOVE_PATH, "pclzip");
		if ($v_list == 0)
		{
			die("Error : ".$archive->errorInfo(true));  //如果有误，提示错误信息。
		}
		echo time()-$startime;
		
    }
    
    
    
}
