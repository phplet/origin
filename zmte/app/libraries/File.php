<?php
abstract class Cache_Abstract
{
    /**
     * @description 获取标识为$key的缓存数据
     * @param string $key
     */
    abstract public function load($key);

    /**
     * @description 以标识$key保存$data到缓存,有效时间为$lifetime秒
     *              如果为null,则取Fn::config()->cache->xxx->lifetime配置项值
     * @param string $key
     * @param mixed  $data
     * @param int $lifetime
    */
    abstract public function save($key, $data, $lifetime = null);

    /**
     * @description 删除标识为$key的缓存数据
     * @param string $key
    */
    abstract public function remove($key);

    /**
     * @description 删除标识为$path的所有缓存数据
     * @param string $path
    */
    abstract public function clear($path = null);
}

/**
 * @description File缓存
 * @author      TCG
 * @final       2015-08-07
 */
final class Cache_File extends Cache_Abstract
{
    /**
     * 缓存文件存放目录
     */
    private static $cache_dir = null;

    /**
     * 缓存文件默认有效期（秒）
     */
    private static $lifetime = null;

    public function __construct()//{{{
    {
        if (self::$cache_dir == null)
        {
            self::$cache_dir = APPPATH . "../cache/filecache/";
            self::$lifetime = 24*3*3600;
        }
    }//}}}

    /**
     * 获取标识为$key的缓存数据
     * @param string $key 文件缓存标识 如：a 或者 a/b
     * @return mixed list<map<string, variant>>
     */
    public function load($key)//{{{
    {
        if (!self::validate_key($key))
        {
            return false;
        }

        $cache_file = self::get_cache_path($key);
        if (!is_file($cache_file))
        {
            return false;
        }

        $data = include($cache_file);
        return $data;
    }//}}}

    /**
     * @description 以标识$key保存$data到缓存,有效时间为$lifetime秒
     * @param   string  $key        文件缓存标识 如：a 或者 a/b
     * @param   mixed   $data       缓存数据
     * @param   int     $lifetime   缓存有效时间
     * @return  mixed|bool
     */
    public function save($key, $data, $lifetime = null)//{{{
    {
        if (!$key || !self::validate_key($key))
        {
            return false;
        }

        $lifetime = $lifetime ? $lifetime : self::$lifetime;

        $cache_file = self::get_cache_path($key);

        $now = date('Y-m-d H:i:s');
        $cache_data =<<<EOT
<?php
/**
 * @Created By YAF Cache_File
 * @Time:{$now}
 */
EOT;
        $cache_data .= self::get_expire_condition(intval($lifetime));
        $cache_data .= "\r\nreturn unserialize('"
            . serialize($data) .  "', true);\r\n";


        return file_put_contents($cache_file, $cache_data, LOCK_EX);
    }//}}}

    /**
     * @description 删除标识为$key的缓存数据
     * @param string $key 文件缓存标识 如：a 或者 a/b
     * @return    bool
     */
    public function remove($key)//{{{
    {
        if (!self::validate_key($key))
        {
            return false;
        }

        $cache_file = self::get_cache_path($key);
        if (is_file($cache_file))
        {
            return @unlink($cache_file);
        }

        return false;
    }//}}}

    /**
     * @description 删除标识为$path的所有缓存数据
     * @param string $path 如：a 或者 a/b/c
     * @return    bool
     */
    public function clear($path = null)//{{{
    {
        if ($path && !self::validate_key($path))
        {
            return false;
        }

        $cache_dir = self::$cache_dir . $path;

        if (!is_dir($cache_dir))
        {
            return false;
        }

        self::rm_dir($cache_dir);
        return true;
    }//}}}

    /**
     * @description 过期时间
     * @param number $lifetime
     * @return string
     */
    private static function get_expire_condition($lifetime = 0)//{{{
    {
        if (!$lifetime)
        {
            return '';
        }
        $code = <<<EOT

if (filemtime(__FILE__) + {$lifetime}  < time())
{
    return '';
}
EOT;
        return $code;
    }//}}}

    /**
     * @description 缓存路径
     * @param string $key
     * @return string
     */
    private static function get_cache_path($key)//{{{
    {
        $file_name = end(explode('/', $key));
        $dir = self::$cache_dir . $key;

        if (!is_dir($dir))
        {
            mkdir($dir, 0777, true);

        }

        return $dir . '/' . $file_name . '.cache';
    }//}}}

    /**
     * @description 删除缓存目录及文件
     * @param string $dir
     * @return mixed boolen
     */
    private static function rm_dir($dir)//{{{
    {
        $ret_val = false;
        if (is_dir($dir))
        {
            $d = @dir($dir);

            if ($d)
            {
                while (false !== ($entry = $d->read()))
                {
                    if ($entry != '.' && $entry != '..')
                    {
                        $entry = $dir . '/' . $entry;

                        if (is_dir($entry))
                        {
                            self::rm_dir($entry);

                        }
                        else
                        {
                            @unlink($entry);

                        }
                    }
                }
                $d->close();
            }

            @rmdir($dir);
            //system("rm -rf {$dir}");
        }
        else
        {
            $ret_val = @unlink($dir);
        }

        return $ret_val;
    }//}}}

    /**
     * 验证$key是否合法
     * 只能包含英文字母、数组、下划线、斜杠或反斜杠
     */
    private static function validate_key($key)//{{{
    {
        if (preg_match('/^[a-zA-Z_0-9\\\|\/]*$/', $key))
        {
            return true;
        }
        return false;
    }//}}}
}
