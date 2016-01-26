<?php
class AlipayConfig
{
    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public static function config()
    {
        $alipay_config = array();
        include(dirname(__FILE__) . '/alipaydirect/alipay.config.php');
        return $alipay_config;
    }
}
