<?php
/**
 * PHPUnit测试类
 * @author  BJP
 * @final   2015-08-21
 */
class PU
{
    /**
     * PHPUnit测试函数入口,在PU_TEST常量定义时才有效,无返回值，测试结果直接输出
     * @param   string  $testClassName
     * @final   2015-08-21
     */
    public static function TEST(string $testClassName)
    {
        if (!defined('PU_TEST'))
        {
            return;
        }
        $testClassFilePath = $testClassName;
        if (!file_exists($testClassFilePath))
        {
            if (strtolower(substr($testClassFilePath, -4, 4)) != '.php')
            {
                $testClassFilePath .= '.php';
            }
            $testClassFilePath = PU_TEST_PATH . $testClassFilePath;
            if (!file_exists($testClassFilePath))
            {
                die('file not exist: ' . $testClassFilePath);
            }
        }
        ob_clean();
        header('Content-Type: text/plain; charset=UTF-8');
        include_once('PHPUnit/Autoload.php');
        $cmd = new PHPUnit_TextUI_Command;
        $argv = array('phpunit', $testClassFilePath);
        $cmd->run($argv);
        exit();
    }
}
