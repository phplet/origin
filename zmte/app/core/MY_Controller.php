<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

spl_autoload_register(function($className)
{
    $l3 = strtoupper(substr($className, 0, 3));
    if ($l3 == 'CI_' || $l3 == 'MY_')
    {
        return;
    }
    if (strtolower(substr($className, -6)) == '_model')
    {
        // CI_Model
        return;
    }

    if (strtolower(substr($className, -11)) == '_controller')
    {
        $file = APPPATH . 'core/' . $className . '.php';
        if (file_exists($file))
        {
            include_once($file);
        }
        else
        {
            throw new Exception(
                "can not find class '$className' -> $file");
        }
        return;
    }

    $class = str_replace('_', '/', $className);
    if (strtolower(substr($class, -5)) == 'model')
    {
        // Yaf Model   A_B_CModel -> A/B/C.php
        $file = APPPATH . 'models/'
            . substr($class, 0, strlen($class) - 5) . '.php';
        if (file_exists($file))
        {
            include_once($file);
        }
        else
        {
            throw new Exception(
                "can not find class '$className' -> $file");
        }
    }
    else
    {
        $file = APPPATH . 'libraries/'. $class . '.php';
        if (file_exists($file))
        {
            include_once($file);
        }
        else
        {
            throw new Exception(
                "can not find class '$className' -> $file");
        }
    }
});


/**
 * 扩展 Controller 类
 */
class MY_Controller extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        header('Content-type:text/html; charset=utf-8');
        date_default_timezone_set('Asia/Shanghai');
        
        defined('_UPLOAD_ROOT_PATH_') || define('_UPLOAD_ROOT_PATH_', C('upload_root_path'));
        defined('__IMG_ROOT_URL__') || define('__IMG_ROOT_URL__', C('global_source_host').'/images/');
    }
}
