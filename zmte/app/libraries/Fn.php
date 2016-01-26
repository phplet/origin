<?php
final class Fn
{
    private static $_ajax_func_suffix = 'Func';
    private static $_db = null;
    private static $_db_pg = null;

    public static function sess()
    {
        return CI_Controller::get_instance()->session;
    }

    public static function db()//{{{
    {
        if (self::$_db) {
            return self::$_db;
        }

        $dir = dirname(__FILE__);
        include_once($dir . '/PDO/DB.php');
        include($dir . '/../config/config.db.php');

        $cfg = $db[$db['active_group']];
        $dsn = 'mysql:host=' . $cfg['hostname'] . ';dbname=' . $cfg['database'];
        if (isset($cfg['port']))
        {
            $dsn .= ';port=' . $cfg['port'];
        }

        self::$_db = new PDO_DB($dsn, $cfg['username'], $cfg['password']);

        if (in_array(substr($dsn, 0, 6),array('mysql:', 'pgsql:')))
        {
            self::$_db->query("SET NAMES 'utf8'");
        }

        return self::$_db;
    }//}}}
    
    public static function db_pg()//{{{
    {
        if (self::$_db_pg) {
            return self::$_db_pg;
        }
    
        $dir = dirname(__FILE__);
        include_once($dir . '/PDO/DB.php');
        include($dir . '/../config/config.db.php');
    
        $cfg = $db['zmoss'];
        $dsn = 'pgsql:host=' . $cfg['hostname'] . ';dbname=' . $cfg['database'];
        if (isset($cfg['port']))
        {
            $dsn .= ';port=' . $cfg['port'];
        }
    
        self::$_db_pg = new PDO_DB($dsn, $cfg['username'], $cfg['password']);
    
        if (in_array(substr($dsn, 0, 6),array('mysql:', 'pgsql:')))
        {
            self::$_db_pg->query("SET NAMES 'utf8'");
        }
    
        return self::$_db_pg;
    }//}}}

    public static function factory($adapter)
    {
        $dir = dirname(__FILE__);
        include_once($dir . '/File.php');
        $class_name = "Cache_" . ucwords(strtolower($adapter));
        if (class_exists($class_name))
        {
            return new $class_name();
        }
        return false;
    }

    public static function getParams()
    {
        return array_merge($_GET, $_POST);
    }

    public static function getParam($key)
    {
        if (isset($_POST[$key]))
        {
            return $_POST[$key];
        }
        else if (isset($_GET[$key]))
        {
            return $_GET[$key];
        }
        else
        {
            return null;
        }
    }

    /**
     * ajax方法调用,使用方法:  Fn::ajax_call($controller, 'method1', 'method2', 
     *     'method3');
     *  it will call $controller->method1Func(), $controller->method2Func()...
     */
    public static function ajax_call(CI_Controller $obj)//{{{
    {
        $args = func_get_args();
		
        if (count($args) < 2)
        {
            throw new Exception('Error arguments count must not less than 2');
        }
        array_shift($args);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'
            && isset($_POST['ajax_call']) 
            && $_POST['ajax_call'] === 'true'
            && isset($_POST['function']) 
            && in_array($_POST['function'], $args))
        {
            $func = $_POST['function'] . self::$_ajax_func_suffix;
            if (method_exists($obj, $func))
            {
                $ajax_response = call_user_func_array(array($obj, $func), 
                    is_array($_POST['arguments']) 
                    ? $_POST['arguments'] : array());
                if ($ajax_response instanceof AjaxResponse)
                {
                    header('Content-Type:application/json;charset=UTF-8');
                    echo($ajax_response->__toString());
                }
                exit();
            }
            else
            {
                $ajax_response = new AjaxResponse();
                $ajax_response->alert('Not exist ajax method: ' 
                    . $_POST['function']);
                header('Content-Type:application/json;charset=UTF-8');
                echo($ajax_response->__toString());
                exit();
            }
        }
        $str = '';
        foreach ($args as $v)
        {
            $str .= "function ajax_$v(){ fnAjaxCall(location.href, '$v', arguments);}\n";
        }
        $obj->_ajaxScript = $str;
    }//}}}

    public static function paginator(/*int*/$count, 
            /*string*/$page_key = 'page', 
            /*string*/$perpage_key = 'perpage')//{{{
    {
        $page = self::getParam($page_key);
        $perpage = self::getParam($perpage_key);
        if ($perpage === null)
        {
            $perpage = C('default_perpage_num');
        }
        $paginator = new Paginator($count, $page, $perpage, $page_key, 
            $perpage_key);
        return $paginator->toArray();
    }//}}}
    
    /**
     * 判断所属等级
     * @param   int     $num
     * @return  $level
     */
    public function judgmentBelongsLevel($num)
    {
        $num = intval($num);
        
        if ($num > 75)
        {
            return 1;
        }
        else if ($num >= 50 && $num <= 75)
        {
            return 2;
        }
        else 
        {
            return 3;
        }
    }
}
