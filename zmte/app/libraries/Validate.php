<?php
/**
 * 功能 常用验证功能
 * @file    Validate.php
 * @author  BJP
 * @final   2015-06-30
 */
final class Validate
{
    /**
     * 验证是否为数字，英语字母
     * @param   string  $str    被验证的参数
     * @return  bool
     */
    public static function isAlnum($str)
    {
        if (preg_match("/^[a-zA-Z0-9]+$/u", $str))
        {
            return true;
        }
        return false;
    }

    /**
     * 验证是否只由英语字母构成
     * @param string $str
     * @return bool
     */
    public static function isAlpha($str)
    {
        if (preg_match("/^[a-zA-Z]+$/u",$str))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$str是否是符合$format所指格式的日期字符串
     * @param   string  $str        需要验证的日期字符串
     * @param   string  $format     验证规则
     * @return  bool
     */
    public static function isDate($str, $format = 'Y-m-d')
    {
        /*
         * 暂时先不处理此处
        $date_param = array(
                            'a', 'A', 'd', 'D', 'h', 'H', 'g',
                            'G', 'j', 'l', 'm', 'n', 'M', 's',
                            'S', 't', 'U', 'w', 'Y', 'y', 'z',
                            );
        $t_format = preg_replace("/[^a-zA-Z]/","", $format);
        for ($i = 0; $i < strlen($t_format); $i++ )
        {
            if (!in_array($t_format[$i], $date_param))
            {
                return false;
            }
        }
        
         */
        $t = strtotime($str);
        if ($t === false)
        {
            return false;
        }
        $format_date = date($format, $t);
        if ($format_date == $str)
        {
             return true;
        }
       
        return false;
    }

    /**
     * 校验$str是否是符合电子邮箱格式的字符串
     * @param string $str
     * @return bool
     */
    public static function isEmailAddress($str)
    {
        if (filter_var($str, FILTER_VALIDATE_EMAIL))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$value是否是符合十六进制数字（或字符串）
     * @param string $value
     * @return bool
     */
    public static function isHex($value)
    {
        if (gettype($value) != 'string')
        {
            return false;
        }
        if (preg_match("/^0[xX][0-9a-fA-F]+$/u", $value))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$ip是否是正确的IPv4字符串
     * @param string $value
     * @return bool
     */
    public static function isIPv4($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$ip是否是正确的IPv6字符串，返回正确与否
     * @param string $value
     * @return bool
     */
    public static function isIPv6($ip)
    {
        if (filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$value是否为空
     * @param mixed $value
     * @return bool
     */
    public static function isNotEmpty($value)
    {   
        if (!empty($value) && trim($value) != '')
        {
            return true;
        }

        return false;
    }

    /**
     * 校验$value是否为数字
     * @param mixed $value
     * @return bool
     */
    public static function isDigits($value)
    {
        if (is_numeric($value))
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$value是否为浮点数
     * @param mixed $value
     * @return bool
     */
    public static function isFloat($value)
    {
        if (is_float($value))
        {
            return true;
        }
        if (is_numeric($value) && strpos($value, '.') !== false)
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$value是否为整数
     * @param mixed $value
     * @return bool
     */
    public static function isInt($value)
    {
        if (is_int($value))
        {
            return true;
        }
        if (is_numeric($value) && strpos($value, '.') === false)
        {
            return true;
        }
        return false;
    }

    /**
     * 校验$value是否是大于$min_value
     * @param  mixed    $value 
     * @param  mixed    $min_value
     * @param  integer   $scale                  小数精度（位数）
     * @return  bool
     */
    public static function isGreaterThan($value, $min_value, $scale = null)
    {
        if ($scale == null)
        {
            $scale = self::get_scale($value, $min_value);
        }
        
        return bccomp($value, $min_value, $scale) > 0;
    }

    /**
     * 校验$value是否是大于等于$min_value
     * @param  mixed    $value
     * @param  mixed    $min_value
     * @param  integer  $scale     小数精度（位数）
     * @return bool
     */
    public static function isGreaterEqual($value,  $min_value, $scale = null)
    {
        if ($scale == null)
        {
            $scale = self::get_scale($value, $min_value);
        }
        
        return  bccomp($value, $min_value, $scale) >= 0;
    }

    /**
     * 校验$value是否是小于$max_value
     * @param  mixed    $value
     * @param  mixed    $max_value
     * @param  integer  $scale      小数精度（位数）
     * @return bool
     */
    public static function isLessThan($value,  $max_value, $scale = null)
    {
        if ($scale == null)
        {
            $scale = self::get_scale($value, $max_value);
        }
        
        return bccomp($value, $max_value, $scale) < 0;
    }

    /**
     * 校验$value是否是小于等于$max_value
     * @param  mixed    $value
     * @param  mixed    $max_value
     * @param  integer  $scale       小数精度（位数）
     * @return bool
     */
    public static function isLessEqual($value,  $max_value, $scale = null)
    {
        if ($scale == null)
        {
            $scale = self::get_scale($value, $max_value);
        }
        
        return bccomp($value, $max_value, $scale) <= 0;
    }

    /**
     * 校验$value是否符合正则表达式$pattern
     * @param mixed $value
     * @param mixed $pattern 正则表达式
     * @return bool
     */
    public static function isRegex($value, $pattern)
    {
        if (is_string($pattern) && trim($pattern) != '')
        {
            if (preg_match($pattern, $value))
            {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 校验$value是否在最小值$min和最大值$max之间（包含边界）, 如果是小数比较, 参数$scale不能为空
     * @param  mixed    $value
     * @param  mixed    $min 
     * @param  mixed    $max
     * @param  integer  $scale 小数精度（位数）
     * @return bool
     */
    public static function isBetween($value, $min, $max, $scale = null)
    {
        if ($scale == null)
        {
            $scale1 = self::get_scale($value, $min);
            $scale2 = self::get_scale($value, $max);
            
            if (bccomp($scale1, $scale2, 0) < 0)
            {
               $scale = $scale2;
            }
            else
            {
                $scale = $scale1;
            }
        }
        
        if (bccomp($max, $min, $scale) < 0)
        {
            return false;
        }
        
        if (bccomp($min, $value, $scale) > 0 || bccomp($value, $max, $scale) > 0)
        {
            return false;
        }
        
        return true;
    }

    /**
     * 校验$value是否在$container里
     * @param mixed $value
     * @param array $container
     * @return bool
     */
    public static function inArray($value, $container)
    {
        if (!is_array($container))
        {
            return false;
        }
        return in_array($value, $container);
    }

    /**
     * 校验$str的长度是否在$min和$max之间, 如果$max为null，则不检验长度上限
     * @param  string  $str
     * @param  mixed   $min
     * @param  mixed   $max
     * @param  string  $encoding  字符集
     * @return bool
     */
    public static function isStringLength($str, $min, $max = null, $encoding = 'UTF-8')
    {
        $len = mb_strlen($str, $encoding);
        return $max == null ? ($len >= $min) : ($len >= $min && $len <= $max);
    }
    
    /**
     * 比较两个数值的精度并返回精度大的值的精度值
     * @param  mixed   $value
     * @param  mixed   $value2
     * @return mixed
     */
    private static function get_scale($value, $value2)
    {
        $scale  = Func::getDigitPrecision($value);
        $scale2 = Func::getDigitPrecision($value2);
        return $scale > $scale2 ? $scale : $scale2;
    }

    /**
     * 校验$str是否是用$splitter指定字符将若干个正整数连接起来的字符串
     * @param   string  $str            要校验的字符串
     * @param   string  $splitter = ',' 分隔符
     * @return  bool
     */
    public static function isJoinedIntStr($str, $splitter = ',')
    {
        if (preg_match('/^\d+(' . $splitter . '\d+)*$/u', $str))
        {
            return true;
        }
        return false;
    }
}
