<?php
/**
 * 将原来的基于predis的接口改为使用php_redis扩展,两者接口一致
 * @author  BJP
 * @final   2015-07-07
 */
class Myredis
{
    /**
     * redis对象实例
     * @var redis object
     */
    private $redis_instance = null;
    
    public function __construct() 
    {
        /*
        require_once 'predis/autoload.php';
        $redis_config = C('redis', 'redis');
        try
        {
            $redis = new Predis\Client(
                "tcp://{$redis_config['hostname']}:{$redis_config['port']}");
            $this->redis_instance = $redis;
        }
        catch (Exception $e)
        {
            echo "cannot connect to Redis server". PHP_EOL;
            echo $e->getMessage();
        }
         */
        $redis_config = C('redis', 'redis');
        try
        {
            $redis = new Redis();
            $redis->pconnect($redis_config['hostname'], 
                $redis_config['port']);
            $this->redis_instance = $redis; 
        }
        catch (Exception $e)
        {
            echo "cannot connect to Redis server". PHP_EOL;
            echo $e->getMessage();
        }
    }
    
    /**
     * 返回redis实例
     * @return \Predis\Client
     */
    public function get_redis() 
    {
        return $this->redis_instance;
    }
}
