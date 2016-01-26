<?php
/**
 * common functions
 * @author  BJP
 */
final class Func
{
    /**
     * 对字符串进行加密
     * @param   string $str 要加密的字符串
     * @return  string      返回加密后的字符串
     */
    public static function strEncode($str)
    {
        return base64_encode(bin2hex($str));
    }

    /**
     * 对加密字符串进行解密
     * @param   string $str 已被加密的字符串
     * @return  string      返回解密后的字符串
     */
    public static function strDecode($str)
    {
        return pack('H*', base64_decode($str));
    }

    /**
     * 加密某一个对象为字符串
     * @param   array       $param      要加密的变量
     * @param   string      $hashcode   加密密码
     * @param   string      $method     加密算法
     * @return  string                  返回加密后的字符串
     */
    public static function encrypt($param, $hashcode, $method = 'DES-CBC')
    {
        return openssl_encrypt(json_encode($param), $method, $hashcode);

    }

    /**
     * 解密某一个对象（从字符串）
     * @param   string      $data       要解密的字符串
     * @param   string      $hashcode   加密密码
     * @param   string      $method     加密算法
     * @return  array|bool              返回解密后的数组,若失败则返回false
     */
    public static function decrypt($data, $hashcode, $method = 'DES-CBC')
    {
        $data2 = openssl_decrypt($data, $method, $hashcode);
        if ($data2 === false)
        {
            return false;
        }
        return json_decode($data2, true);
    }

    /**
     * 输出(供下载用)二进制文件
     * @param   string  $mime   MIME类型
     * @param   string  $file   文件路径
     * @param   string  $attach 指定文件名
     * @return  void
     */
    public static function dumpFile($mime, $file, $attach)
    {
        if (is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE"))
            || is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "Edge")))
        {
            $attach = iconv("UTF-8", "GBK", $attach);
        }
        ob_clean();
        header("Content-Type:$mime");
        header("Content-Disposition:attachment;filename=\"$attach\"");
        if (is_string($file) && file_exists($file))
        {
            @readfile($file);
            exit();
        }
    }

    /**
     * 输出HTML格式的EXCEL文件
     * @param   string $attach  指定附件文件名
     */
    public static function dumpExcel($attach)
    {
        if (is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE"))
            || is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "Edge")))
        {
            $attach = iconv("UTF-8", "GBK", $attach);
        }
        ob_clean();
        header("Content-Type:application/ms-excel");
        header("Content-Disposition:attachment;filename=\"$attach\"");
    }

    /**
     * 发送电子邮件
     * @param array $to_array   格式为array[]=array('name'=>'','mail'=>'')
     * @param string $subject   主题
     * @param string $content   内容
     * @param array  $attachment_array  附件列表array[]=array('file' => '', 'name' => '')
     * @param bool $html        是否是HTML邮件
     * @param array $from       格式为array('name'=>'','mail'=>'')，如果为空，
     *                          则取默认值
     * @return  mxied           如果返回值为null表示成功,否则表示失败信息
     */
    /*
    public static function sendmail($to_array, $subject = '', $content = '',
        $attachment_array = array(), $html = false, $from = array())
    {
        include_once(dirname(__FILE__) . '/PHPMailer/class.phpmailer.php');
        $mail = new PHPMailer();

        $cfg = Yaf_Application::app()->getConfig()->sendmail;
        $mail->Mailer = strtolower($cfg->mailer);
        if ($mail->Mailer == 'smtp')
        {
            include_once(dirname(__FILE__) . '/PHPMailer/class.smtp.php');
            $mail->isSMTP();
            $mail->Host = $cfg->smtp->server;
            $mail->SMTPSecure = strtolower($cfg->smtp->ssl);
            $mail->AuthType = strtoupper($cfg->smtp->auth);
            $mail->SMTPAuth = true;
            $mail->Username = $cfg->smtp->username;
            $mail->Password = $cfg->smtp->password;
            if (is_int($cfg->smtp->port))
            {
                $mail->Port = $cfg->smtp->port;
            }
        }
        if (empty($from))
        {
            $mail->From = $cfg->from->mail;
            $mail->FromName = $cfg->from->name;
        }
        else
        {
            $mail->From = $from['mail'];
            $mail->FromName = $from['name'];
        }

        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';
        $mail->isHTML($html);

        $mail->Body = $content;
        if ($html)
        {
            $mail->AltBody = strip_tags($content);
        }

        foreach ($attachment_array as &$att)
        {
            $mail->addAttachment($att['file'], $att['name']);
        }

        foreach ($to_array as &$item)
        {
            $mail->addAddress($item['mail'], $item['name']);
        }

        if (!$mail->send())
        {
            return $mail->ErrorInfo;
        }
        return null;
    }
     */

    /**
     * 对字符串进行去除两端空格及HTML特殊标签转换操作并输出
     * @param   string $strInfo     要操作的原始字符串
     * @param   bool $is_nl2br = false  是否要将换行符转换为<br/>
     * @return  void
     */
    public static function echo_s($strInfo, $is_nl2br = false)
    { 
        if ($is_nl2br)
        {
            echo(nl2br(htmlspecialchars(trim($strInfo))));
        }
        else
        {
            echo(htmlspecialchars(trim($strInfo)));
        }
    }


    public static function dtStr($obj, $format = null)
    {
        $str = $obj;
        if (is_string($obj))
        {
            if ($format == null)
            {
                $fmt = "Y-m-d H:i:s";
            }
            else
            {
                $fmt = $format;
            }
            $str = date($fmt, strtotime($obj));
        }
        else if (is_object($obj))
        {
            $className = get_class($obj);
            if ($className == 'DateTime')
            {
                if ($format == null)
                {
                    $str = $obj->format('Y-m-d H:i:s');
                }
                else
                {
                    $str = $obj->format($format);
                }
            }
            else if ($className == 'Date')
            {
                if ($format == null)
                {
                    $str = $obj->format('Y-m-d');
                }
                else
                {
                    $str = $obj->format($format);
                }
            }
            else if ($className == 'Time')
            {
                if ($format == null)
                {
                    $str = $obj->format('H:i:s');
                }
                else
                {
                    $str = $obj->format($format);
                }
            }
        }
        return $str;
    }

    /**
     * 对字符串进行去除两端空格及HTML特殊标签转换操作并输出
     * 如转换完后为空字符串，则返回&nbsp;
     * @param   string $strInfo     要操作的原始字符串
     * @param   bool $is_nl2br = false  是否要将换行符转换为<br/>
     * @return  void
     */
    public static function echo_ss($strInfo, $is_nl2br = false)
    {
        if ($is_nl2br)
        {
            $str = nl2br(htmlspecialchars(trim($strInfo)));
        }
        else
        {
            $str = htmlspecialchars(trim($strInfo));
        }
        if (strlen($str) < 1)
        {
            $str = "&nbsp;";
        }
        echo($str);
    }

    /**
     * 将货币金额值转换成为大写形式
     * @param   string $value   货币金额（小写、数字）
     * @return  string          返回货币金额的大写
     */
    public static function currencyToString($value)
    {
        $str = bcadd($value, '0.00', 2);

        $str2 = '仟佰拾万仟佰拾亿仟佰拾万仟佰拾元';
        $str3 = '零壹贰叁肆伍陆柒捌玖';
        $cnt = mb_strlen($str3);
        $str3arr = array();
        for ($i = 0; $i < $cnt; $i++)
        {
            $str3arr[] = mb_substr($str3, $i, 1);
        }
        $len2 = strlen($str) - 3;
        $str2 = mb_substr($str2, mb_strlen($str2) - $len2);

        $str4 = '';
        for ($i = 0; $i < $len2; $i++)
        {
            $j = intval(substr($str, $i, 1));
            $word_unit = mb_substr($str2, $i, 1);
            if (in_array($word_unit, array('亿', '万')))
            {
                if ($j == 0)
                {
                    $str4 .= $word_unit;
                }
                else
                {
                    $str4 .= $str3arr[$j] . $word_unit;
                }
            }
            else
            {
                if ($j == 0)
                {
                    $str4 .= '零';
                }
                else
                {
                    $str4 .= $str3arr[$j] . $word_unit;
                }
            }
        }
        while (($pos = mb_strpos($str4, '零零')) !== false)
        {
            $str4 = mb_substr($str4, 0, $pos) . mb_substr($str4, $pos + 1);
        }
        if (mb_substr($str4, -1, 1) == '零')
        {
            if (mb_strlen($str4) > 1)
            {
                $str4 = mb_substr($str4, 0, mb_strlen($str4) - 1) . '元';
            }
            else
            {
                $str4 .= '元';
            }
        }

        if (substr($str, -2, 2) == '00')
        {
            $str4 .= '整';
        }
        else if (substr($str, -1, 1) == '0')
        {
            // 零分
            $j = intval(substr($str, -2, 1));
            $str4 .= $str3arr[$j] . '角整';
        }
        else if (substr($str, -2, 1) == '0')
        {
            // 零角
            $j = intval(substr($str, -1, 1));
            $str4 .= '零' . $str3arr[$j] . '分';
        }
        else
        {
            $j = intval(substr($str, -2, 1));
            $str4 .= $str3arr[$j] . '角';
            $j = intval(substr($str, -1, 1));
            $str4 .= $str3arr[$j] . '分';
        }
        return $str4;
    }

    /**
     * 将map<string, string>对象转换成可查看的字符串，比如：
     * key1 => val1
     * key2 => val2
     * key3 => val3
     * 该方法主要用于调试
     * @param   map<stirng, string> $param  参数
     * @return  string  返回可查看的参数信息
     */
    public static function map2str($param)
    {
        $str = '';
        foreach ($param as $key => $value)
        {
            $obj = $value;
            $v = '';
            if (is_object($obj))
            {
                $className = get_class($obj);
                if ($className == 'DateTime')
                {
                    $v = $obj->format('Y-m-d H:i:s');
                }
                else if ($className == 'Date')
                {
                    $v = $obj->format('Y-m-d');
                }
                else if ($className == 'Time')
                {
                    $v = $obj->format('H:i:s');
                }
            }
            else
            {
                $v = $value;
            }

            $str .= $key . ' => ' . $v . "\n";
        }
        return $str;
    }

    /**
     * 批量将对象值由一种字符编码转换为另一种字符编码
     * @param   string $charset_from    源字符编码
     * @param   string $charset_to      目的字符编码
     * @param   mixed  &$data           数据对象
     * return   void
     */
    public static function iconv_r($charset_from, $charset_to, &$data)
    {
        if (is_array($data) || is_object($data))
        {
            foreach ($data as &$value)
            {
                self::iconv_r($charset_from, $charset_to, $value);
            }
        }
        else if (is_string($data))
        {
            $data = iconv($charset_from, $charset_to, $data);
        }
    }

    /**
     * 判定数字字符串的精度
     * @param   string $value   数字字符串
     * @return  int             返回数字精度
     */
    public static function getDigitPrecision($value)
    {
        $v = trim($value);
        $pos = strpos($v, ".");
        if ($pos === false)
        {
            return 0;
        }
        $len = strlen($v) - $pos - 1;
        return $len;
    }

    /**
     * 按照指定精度比较两个数的大小（字符串形式）,返回大者
     * @param   string  $arg1   比较数1
     * @param   string  $arg2   比较数2
     * @param   int     $prec   精度(小数位数)
     * @return  string  返回两者中的最大值
     */
    public static function bcmax($arg1, $arg2, $prec)
    {
        return bccomp($arg1, $arg2, $prec) > 0 ? $arg1 : $arg2;
    }

    /**
     * 按照指定精度比较两个数的大小（字符串形式）,返回小者
     * @param   string  $arg1   比较数1
     * @param   string  $arg2   比较数2
     * @param   int     $prec   精度(小数位数)
     * @return  string  返回两者中的最小值
     */
    public static function bcmin($arg1, $arg2, $prec)
    {
        return bccomp($arg1, $arg2, $prec) < 0 ? $arg1 : $arg2;
    }

    /**
     * 按照指定精度返回以千分位表示的数字
    public static function number_format($v, $prec = 2)
    {
        return number_format($v, 2, '.', ',');
    }
     */
    /**
    * 时长，返回中文描述
    * @param int seconds 秒
    * @return string 例如：1天1时1分1秒
    */
    public static function longtime($seconds)
    {
    	if (!is_numeric($seconds)) return '';
    	$value  = $seconds;
    	$result = '';
    	
    	$day = (int)($value / (3600 * 24));
    	$value = $value - $day * 3600 * 24;
    	$hou = (int)($value / 3600);
    	$value = $value - $hou * 3600;
    	$min = (int)($value / 60);
    	$sec = $value - $min * 60;
    	
    	if ($day) $result .= $day . '天';
    	if ($hou) $result .= $hou . '时';
    	if ($min) $result .= $min . '分';
    	if ($sec) $result .= $sec . '秒';
    	
    	return $result;
    }
    
    /**
     * 获取客户端IP地址
     * @return  string  返回IP地址
     */
    public static function get_client_ip()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP']))
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s',
            $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
        {
            foreach ($matches[0] as $xip)
            {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip))
                {
                    $ip = $xip;
                    break;
                }
            }
        }
        return $ip;
    }

    public static function get_http_host()
    {
        $server_name = isset($_SERVER['HTTP_X_FORWARDED_HOST']) 
            ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
        return $server_name;
    }

    public static function get_server_name()
    {
        $server_name = isset($_SERVER['HTTP_X_FORWARDED_SERVER']) 
            ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['SERVER_NAME'];
        return $server_name;
    }

    public static function get_server_port()
    {
        $server_name = isset($_SERVER['HTTP_X_FORWARDED_HOST']) 
            ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
        $server_info = explode(":",rtrim(trim($server_name), '\/'));
        return isset($server_info[1]) ? $server_info[1] : $_SERVER['SERVER_PORT'];
    }

    /**
     * 获取唯一的GUID字符串
     * @return  string  返回36位的GUID字符串
     */
    public static function guid()
    {
        if (function_exists('com_create_guid'))
        {
            return com_create_guid();
        }
        else
        {
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                    .substr($charid, 0, 8).$hyphen
                    .substr($charid, 8, 4).$hyphen
                    .substr($charid,12, 4).$hyphen
                    .substr($charid,16, 4).$hyphen
                    .substr($charid,20,12)
                    .chr(125);// "}"
            return $uuid;
        }
    }

    /**
     * @TODO 替换掉原来至今使用$_SERVER['HTTP_HOST']
     * 获取当前网址中除路径外的前缀(包含schema、host、port等)
     * @return  string  返回除路径外的网址前缀
     */
    public static function urlprefix()
    {
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')
        {
            $urlprefix = 'https://';
        }
        else
        {
            $urlprefix = 'http://';
        }

        //$urlprefix .= $_SERVER['HTTP_HOST'];
        $urlprefix .= self::get_http_host();

        return $urlprefix;
    }

    /**
     * 根据第二个及其之后的参数所示将第一个参数$param中有的字段值复制到一个新
     * 数组中返回，并且若对应值为字符串，则自动去掉前后空白字符
     * @param   array   $param
     * @param   string  $key1
     * @param   string  $key2
     * ......
     * @return  bool|array  如果参数不正确则返回false,否则返回复制后的新数组
     */
    public static function param_copy(array $param)
    {
        $num = func_num_args();

        if ($num < 2)
        {
            return false;
        }

        $key_list = array();
        $bOk = true;
        for ($i = 1; $i < $num; $i++)
        {
            $key = func_get_arg($i);

            if (!is_string($key))
            {
                $bOk = false;
                break;
            }
            $key_list[] = $key;
        }

        if (!$bOk)
        {
            return false;
        }
        $param2 = array();
        foreach ($key_list as $key)
        {
            if (isset($param[$key]))
            {
                if (is_string($param[$key]))
                {
                    $param2[$key] = trim($param[$key]);
                }
                else
                {
                    $param2[$key] = $param[$key];
                }
            }
        }

        return $param2;
    }

    /**
     * 算出时间差
     * @param date $date
     * @return string
     * @author zh
     */
    
    public static function formatTime($date) {
        $str = '';
        $timer = strtotime($date);
        $diff = $_SERVER['REQUEST_TIME'] - $timer;
        $day = floor($diff / 86400);
        $free = $diff % 86400;
        if($day > 0) {
            return $day."天前";
        }else{
            if($free>0){
                $hour = floor($free / 3600);
                $free = $free % 3600;
                if($hour>0){
                    return $hour."小时前";
                }else{
                    if($free>0){
                        $min = floor($free / 60);
                        $free = $free % 60;
                        if($min>0){
                            return $min."分钟前";
                        }else{
                            if($free>0){
                                return $free."秒前";
                            }else{
                                return '刚刚';
                            }
                        }
                    }else{
                        return '刚刚';
                    }
                }
            }else{
                return '刚刚';
            }
        }
    }
    
    /**
     * 生成订单号
     * @return string
     */
    public static function buildOrderNo()
    {
        return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
}
