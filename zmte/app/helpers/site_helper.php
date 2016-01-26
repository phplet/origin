<?php if ( ! defined('BASEPATH')) exit();
/**
 * 提示跳转页面
 * @author	qcchen <qcchen@qq.com>
 *
 * @access	public
 * @param	string
 * @return	void
 */
if ( ! function_exists('message'))
{
    function message($message, $uri = null, $msg_type = 'notice', $redirect_time = 5)
    {
        $CI =& get_instance();

        //如果是ajax请求，则返回json数据
        if ($CI->input->is_ajax_request())
        {
            if ($msg_type == 'success')
            {
                output_json(CODE_SUCCESS, $message, array(), $uri);
            }
            else
            {
                output_json(CODE_ERROR, $message, array(), $uri);
            }
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
        if ($uri || $referer)
        {
            if ($uri !== false)
            {
                if (empty($uri))
                {
                    $uri = 'javascript:history.go(-1);';
                }
                else
                {
                    if (substr($uri, 0, 10) != 'javascript' && substr($uri, 0, 4) != 'http')
                    {
                        $uri = site_url($uri);
                    }

                    if (substr($uri, 0, 10) == 'javascript' )
                    {
                        $uri =$referer;
                    }
                }
            }
        }
        else
        {
            $uri = 'javascript:history.go(-1);';
        }

        $data['msg_type'] = $msg_type;
        $data['message'] = $message;
        $data['url_forward'] = $uri;
        $data['redirect_time'] = $redirect_time * 1000;
        
        $content = $CI->load->view('common/message', $data, TRUE);
        ob_end_clean();
        echo $content;
        exit;
    }
}

if ( ! function_exists('C'))
{
	function C($item, $file = '')
    {
        static $static = array();
        static $loaded = array();

        if (isset($static[$item]))
        {
            return $static[$item];
        }

		$CI =& get_instance();
        if ($file && ! isset($loaded[$file]))
        {
            $CI->config->load($file);
            $loaded[$file] = TRUE;
        }
        $arr = explode('/', $item);

        foreach ($arr as $k => $v)
        {
            if ($k == 0)
            {
                $value = $CI->config->item($arr[0]);
            }
            else
            {
                if (isset($value[$v]))
                {
                    $value = $value[$v];
                }
                else
                {
                    $value = FALSE;
                    break;
                }
            }
        }
        $static[$item] = $value;
        return $value;
	}
}

/**
 * 后台分页函数
 * @author	qcchen <qcchen@qq.com>
 *
 * @access	public
 * @param	string
 * @return	string
 */
if ( ! function_exists('multipage'))
{
    function multipage($num, $perpage, $curpage, $mpurl, $anchor = '', $ext='') {
        $multipage = '';
        $mpurl .= strpos($mpurl, '?')!==false ? '&amp;' : '?';
        if($num) {
            $page = 10;
            $offset = 5;
            $pages = @ceil($num / $perpage);
            if($page > $pages) {
                $from = 1;
                $to = $pages;
            } else {
                $from = $curpage - $offset;
                $to = $curpage + $page - $offset - 1;
                if($from < 1) {
                    $to = $curpage + 1 - $from;
                    $from = 1;
                    if(($to - $from) < $page && ($to - $from) < $pages) {
                        $to = $page;
                    }
                } elseif($to > $pages) {
                    $from = $curpage - $pages + $to;
                    $to = $pages;
                    if(($to - $from) < $page && ($to - $from) < $pages) {
                        $from = $pages - $page + 1;
                    }
                }
            }
            $multipage = ($curpage - $offset > 1 && $pages > $page ? '<a href="'.$mpurl.'page=1'.$anchor.'" class="p_redirect">&lt;&lt;</a>' : '').($curpage > 1 ? '<a href="'.$mpurl.'page='.($curpage - 1).$anchor.'" class="p_redirect">&lt;</a>' : '');
            for($i = $from; $i <= $to; $i++) {
                $multipage .= $i == $curpage ? '<span class="p_curpage">'.$i.'</span>' : '<a href="'.$mpurl.'page='.$i.$anchor.'" class="p_num">'.$i.'</a>';
            }
            $multipage .= ($curpage < $pages ? '<a href="'.$mpurl.'page='.($curpage + 1).$anchor.'" class="p_redirect">&gt;</a>' : '').($to < $pages ? '<a href="'.$mpurl.'page='.$pages.$anchor.'"class="p_redirect">&gt;&gt;</a>' : '');
            if ($ext) $num = $num.'-['.$ext.']';
            $multipage = $multipage ? '<div class="p_bar"><span class="p_info">共'.$num.' 条记录</span><span class="p_info">'.$perpage.'条/页</span>'.$multipage.'</div>' : '';
        }
        return $multipage;
    }
}

/**
 * 按年级获取学校年段
 * @author	qcchen <qcchen@qq.com>
 *
 * @access	public
 * @param	int
 * @return	int
 */
if ( ! function_exists('get_grade_period'))
{
    function get_grade_period($grade_id = 0)
    {
        switch($grade_id)
        {
            case 1:
            case 2:
            case 3:
            case 4:
            case 5:
            case 6:
                return 1;
            case 7:
            case 8:
            case 9:
                return 2;
            case 10:
            case 11:
            case 12:
                return 3;
            default :
                return FALSE;
        }
    }
}

if ( ! function_exists('pr'))
{
    function pr($arr, $is_die = false)
    {
        echo '<pre>';
        if (is_array($arr) OR is_object($arr))
        {
            print_r($arr);
        }
        else
        {
            var_dump($arr);
        }
        echo '</pre>';
        if ($is_die) exit;
    }
}

if ( ! function_exists('my_md5'))
{
    function my_md5($str)
    {
        return md5('zeming'.$str.'new-steps.com');
    }
}

/**
 * 自动生成密码：
 * 	密码规则：密码必须同时包含数字、字母、符号，区分大小写， 长度为6~20个字符
 * @param $length 需要生成的密码长度
 */
if ( ! function_exists('auto_general_password'))
{
    function auto_general_password($length = 6)
    {
    	$length = $length < 6 ? 6 : $length;
    	$length = $length > 20 ? 20 : $length;

		// 字母随机种子
		$chars_letter = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
		'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
		't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
		'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
		'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
		);

		// 数字随机种子
		$chars_number = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

		// 符号随机种子
		$chars_symbol = array('!', '@','#', '$', '%', '^', '&', '*', '(', ')', '-', '_',
		'[', ']', '{', '}', '<', '>', '~', '`', '+', '=', ',',
		'.', ';', ':', '/', '?', '|');

		// 在 字母、数字、符号随机种子 中随机取 $length 个数组元素键名
		/*
		 * 字母占比 ：40%
		 * 数字占比： 40%
		 * 符号占比：20%
		 */
		$keys_letter = array_keys($chars_letter);
		$keys_number = array_keys($chars_number);
		$keys_symbol = array_keys($chars_symbol);

		$length_letter = round($length*0.4);
		$length_number = round($length*0.4);
		$length_symbol = $length - ($length_letter + $length_number);

		$password = '';

		//生成字母部分
		for($i = 0; $i < $length_letter; $i++)
		{
			$password .= $chars_letter[array_rand($keys_letter)];
		}

		//生成数字部分
		for($i = 0; $i < $length_number; $i++)
		{
			$password .= $chars_number[array_rand($keys_number)];
		}

		//生成符号部分
		for($i = 0; $i < $length_symbol; $i++)
		{
			$password .= $chars_symbol[array_rand($keys_symbol)];
		}

		return str_shuffle($password);
	}
}


/**
 * 管理员邮箱认证码生成/解密
 *
 * @access	public
 * @param	string
 * @return	int
 */
if ( ! function_exists('admin_email_hash'))
{
	function admin_email_hash ($operation, $key, $validate_time = 0)
	{
		if ($operation == 'encode')
		{
			$admin_id = intval($key);
			$CI =& get_instance();
			//$CI->load->model('admin/cpuser_model');
			$addtime = CpUserModel::get_cpuser($admin_id, 'addtime');
			$hash = substr(md5($admin_id . C('hash_code') . $addtime), 16, 8);

			return base64_encode($admin_id . ',' . $hash .','. time());
		}
		else
		{
			$hash = base64_decode(trim($key));
			$row = explode(',', $hash);
			if (count($row) != 3)
			{
				return FALSE;
			}
			$admin_id  = intval($row[0]);
			$salt = trim($row[1]);
			$time = intval($row[2]);

			if ($admin_id <= 0 || $validate_time && (time()-$time>$validate_time))
			{
				return FALSE;
			}

			$CI =& get_instance();
			$query = $CI->db->select('addtime')->get_where('admin', array('admin_id'=>$admin_id));
			$row = $query->row_array();

			$pre_salt = substr(md5($admin_id . C('hash_code') . $row['addtime']), 16, 8);
			if ($pre_salt == $salt)
			{
				return $admin_id;
			}
			else
			{
				return FALSE;
			}
		}
	}
}

// 检查并自动创建目录
if ( ! function_exists('mk_dir'))
{
    function mk_dir($path) {
        $path = str_replace('\\', '/', $path);
        $arr = explode('/', $path);
        $cpath = '';
        foreach ($arr as $v) {
            if ( ! $v OR $v=='.' OR $v=='..') continue;
            $cpath = $cpath . '/' . $v;
            if ( ! file_exists($cpath)) {
                mkdir($cpath, '755');
            }
        }
        return is_dir($path);
    }
}

// 生成准考证号码
if ( ! function_exists('gen_exam_ticket'))
{
    function gen_exam_ticket($sex, $school_id, $uid)
    {
        // 性别 1 位
        // 学校 5 位
        // 网站ID 5位
        return sprintf($sex.'%05d%05d', $school_id, $uid);
    }
}

/**
* 对数组元素整形化
*
* @access	public
* @param	mixed
* @return	mixed
*/
if ( ! function_exists('my_intval'))
{
	function my_intval($var)
	{
		if (is_array($var))
		{
			return array_map('my_intval', $var);
		}
		else
		{
			return intval($var);
		}
	}
}

/**
* 复制文件，自动创建文件夹
*
* @access	public
* @param	string
* @return	boolean
*/
if ( ! function_exists('my_copy'))
{
    function my_copy($source, $target, $delete_source = FALSE)
    {
        if ( ! is_file(_UPLOAD_ROOT_PATH_.$source)) return FALSE;
        $dir = dirname($target);
        if ( ! is_dir(_UPLOAD_ROOT_PATH_.$dir))
        {
            if ( ! mk_dir($dir))
                return FALSE;
        }
        if ($delete_source)
            return @rename(_UPLOAD_ROOT_PATH_.$source, _UPLOAD_ROOT_PATH_.$target);
        else
            return @copy(_UPLOAD_ROOT_PATH_.$source, _UPLOAD_ROOT_PATH_.$target);
    }
}


if ( ! function_exists('is_idcard'))
{
    function is_idcard($str)
    {
        if (
          preg_match("/^[1-9]\d{5}[1-2]\d{3}((0[1-9])|(1[0-2]))((0[1-9])|([1-2][0-9])|(3[0-1]))\d{4}$/",$str)
        ||preg_match("/^[1-9]\d{5}[1-2]\d{3}((0[1-9])|(1[0-2]))((0[1-9])|([1-2][0-9])|(3[0-1]))\d{3}[xX]$/",$str)
        ||preg_match("/^[1-9]\d{7}((0[1-9])|(1[0-2]))((0[1-9])|([1-2][0-9])|3[0-1])\d{3}$/",$str))
            return true;
        else
        return false;
    }
}

if ( ! function_exists('is_phone'))
{
    function is_phone($str)
    {
        $pattern = "/^(13|15|18)\d{9}$/";
        return preg_match($pattern,$str);
    }
}

if ( ! function_exists('is_email'))
{
    function is_email($str)
    {
        return strlen($str) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $str);
    }
}


/**
* 邮箱认证码生成/解密
*
* @access	public
* @param	string
* @return	int
*/
if ( ! function_exists('email_hash'))
{
    function email_hash ($operation, $key, $validate_time = 0)
    {
        if ($operation == 'encode')
        {
            $uid = intval($key);
            $CI =& get_instance();
            $addtime = StudentModel::get_student($uid, 'addtime');
            $hash = substr(md5($uid . C('hash_code') . $addtime), 16, 8);
            $hash_code = base64_encode($uid . ',' . $hash .','. time());
            $sql = "SELECT COUNT(1) as 'count' FROM {pre}user_resetpassword WHERE uid='$uid' ";
            $row = $CI->db->query($sql)->row_array();
            if ($row['count'])
            {
                $expiretime = time() + 1800;
                $sql ="update  {pre}user_resetpassword  set hash = '$hash_code',expiretime = '$expiretime'
                    WHERE uid = '$uid' ";
                $CI->db->query($sql);
            }
            else
            {
                $expiretime = time() + 1800;
                $sql ="INSERT INTO   {pre}user_resetpassword  ( hash, uid, expiretime ) values( '$hash_code','$uid', '$expiretime')" ;
                $CI->db->query($sql);

            }
            return $hash_code;

        }
        else
        {
            $CI =& get_instance();
            $hash_code = trim($key);
            $now_time = time();
            $hash = base64_decode(trim($key));
            $row = explode(',', $hash);
            if (count($row) != 3)
            {
                return FALSE;
            }
            $uid  = intval($row[0]);
            $salt = trim($row[1]);
            $time = intval($row[2]);

            $sql = <<<EOT
SELECT uid FROM rd_user_resetpassword 
WHERE uid = ? AND expiretime >= ? AND hash = ?
EOT;
            $row = Fn::db()->fetchRow($sql, array($uid, $now_time, $hash_code));
            if(empty($row))
            {
                return FALSE;
            }

            if ($uid <= 0 || $validate_time && (time()-$time>$validate_time))
            {
                return FALSE;
            }

            $sql = <<<EOT
SELECT addtime FROM rd_student WHERE uid = {$uid}
EOT;
            $row = Fn::db()->fetchRow($sql);
            $pre_salt = substr(md5($uid . C('hash_code') . $row['addtime']), 16, 8);
            if ($pre_salt == $salt)
            {
                return $uid;
            }
            else
            {
                return FALSE;
            }
        }
    }
}

if ( ! function_exists('admin_log'))
{
    function admin_log($action, $content, $log_info='')
    {
        //$CI =& get_instance();
        //$CI->load->model('admin/admin_log_model');
        AdminLogModel::add($action, $content, $log_info);
    }
}

if ( ! function_exists('cmp_knowledge'))
{
    function cmp_knowledge ($a, $b)
    {
        if ($a['ques_num'] == $b['ques_num'])
        {
            if ($a['ques_num'] == $b['ques_num'])
            {
                return 0;
            }
            else
            {
                return ($a['relate_ques_num'] > $b['relate_ques_num']) ? -1 : 1;
            }
        }
        else
        {
            return ($a['ques_num'] > $b['ques_num']) ? -1 : 1;
        }

    }
}

if ( ! function_exists('cmp_by_num_desc'))
{
    function cmp_by_num_desc ($a, $b)
    {
        if ($a['num'] == $b['num'])
        {
            return 0;
        }
        else
        {
            return ($a['num'] > $b['num']) ? -1 : 1;
        }
    }
}

if ( ! function_exists('my_implode'))
{
    function my_implode($array=array())
    {
        return "'" . implode("','", $array) . "'";
    }
}

if ( ! function_exists('host_url'))
{
	function host_url()
	{
		$host_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
		$host_url .= '://'. get_http_host();

		return $host_url;
	}
}

if ( ! function_exists('static_global'))
{
	function static_global($source_paths = array(), $type = 'js')
	{
		$static_source =& load_class('static_source', 'utils');
        $static_source->global_source($source_paths, $type);
	}
}

if ( ! function_exists('static_js'))
{
	function static_js($js_path, $compress = 'AUTO', $cache_time = 10)
	{
		$static_source =& load_class('static_source', 'utils');
        $static_source->js($js_path, $compress, $cache_time);
	}
}

if ( ! function_exists('static_css'))
{
	function static_css($css_path, $compress = 'AUTO', $cache_time = 10)
	{
		$static_source =& load_class('static_source', 'utils');
        $static_source->css($css_path, $compress, $cache_time);
	}
}

/**
 * send email
 * @param	string			email titile
 * @param	string			email content
 * @param	string 			email address to send
 * @return	boolean
 */
if (!function_exists('send_email'))
{
    function send_email($subject, $content, $mail_to, $attache_file = null)
    {
        $CI =& get_instance();
        $CI->load->library('email');

        $cfg = C('sendmail');
        $CI->email->from($cfg['from_mail'], $cfg['from_name']);
        $CI->email->to($mail_to);

        $CI->email->subject($subject);
        $CI->email->message($content);
        if (file_exists($attache_file))
        {
            $CI->email->set_mailtype('html');
            $CI->email->attach($attache_file);
        }

        $result = $CI->email->send();
        $CI->email->clear(true);
        return $result;
    }
}

/**
 * 输出json数据
 */
if ( ! function_exists('output_json'))
{
	/**
	 * 输出 json 格式
	 * @param integer $code
	 * @param string $msg
	 * @param array $data
	 * @param string $callback 处理回调
	 */
	function output_json($code, $msg = '', $data = array(), $callback = null)
	{
		$json_data['code'] = $code;
		if ($msg != '') {
			$json_data['msg'] = $msg;
		}

		if (count($data)) {
			$json_data['data'] = $data;
		}

		if (!is_null($callback)) {
			$json_data['callback'] = $callback;
		}

		$json_data = json_encode($json_data);

		echo "{$json_data}";
		exit;
	}
}

/**
 * 将数字转为 字母
 */
if ( ! function_exists('format_numeric_to_letter'))
{
	/**
	 * 输出 将数字转为 字母 格式
	 * @param $numeric_str 待转数字
	 */
	function format_numeric_to_letter($numeric_str, $to_upper = true)
	{
		$numeric_str = intval($numeric_str);
		if ($numeric_str < 1 || $numeric_str > 26) {
			return  '';
		}

		$letters = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');

		$str = $letters[$numeric_str-1];

		return $to_upper ? strtoupper($str) : $str;
	}
}

/**
 * 判断当前浏览器是否合法
 * 只支持 ：纯IE系列， chrome， is_safari， opera
 */
if ( ! function_exists('control_agent_version'))
{
	function control_agent_version()
	{
		if (ENVIRONMENT == 'development') {
			//return;
		}
		$user_agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
		$is_real_ie = strpos($user_agent, "msie") && !strpos($user_agent, ".net");
		$is_chrome = strpos($user_agent, "chrome");
// 		$is_safari = strpos($user_agent, "safari");
// 		$is_opera = strpos($user_agent, "opera");
// 		$is_firefox = strpos($user_agent, "firefox");

		if (!($is_real_ie || $is_chrome)) {
			message('系统不支持当前浏览器， 建议使用以下浏览器打开：<div style="text-align:left;padding-left:20px;font-weight:normal;"><p>IE:<img src="/images/icon_ie.png"/><font size="2">版本 >=7.0</font></p><p>chrome:<img src="/images/icon_chrome.png"/><font size="2">没有安装chrome? <a href="http://www.google.com/intl/zh-CN/chrome/" target="_blank">马上安装</a></font></p></div>', false);
		}
	}
}

/**
 * 机考考生日志
 */
if ( ! function_exists('exam_log'))
{
	function exam_log($action, $content = null, $uid = null)
	{
		$CI =& get_instance();
		$CI->load->model('exam/exam_logs_model');
		$CI->exam_logs_model->insert($action, $content, $uid);
	}
}

/**
 * 机考考生其他日志
 */
if ( ! function_exists('exam_log_1'))
{
    function exam_log_1($action, $content = null, $uid = null, $place_id =null, $exam_id = null)
    {
        $CI =& get_instance();
        $CI->load->model('exam/exam_logs_model');
        $CI->exam_logs_model->insert_1($action, $content, $uid, $place_id, $exam_id);
    }
}


/**
 * 机考考生日志(演示)
 */
if ( ! function_exists('demo_exam_log'))
{
	function demo_exam_log($action, $content = null, $uid = null)
	{
		$CI =& get_instance();
		$CI->load->model('demo/exam_logs_model');
		$CI->exam_logs_model->insert($action, $content, $uid);
	}
}

/**
 * 回收站参数检查
 */
if ( ! function_exists('recycle_log_check'))
{
	function recycle_log_check($id = 0)
	{
		$CI =& get_instance();

		$reason = trim($CI->input->get_post('reason'));
		$q_id = intval($CI->input->get_post('id'));
		$ids = $CI->input->get_post('ids');

		if ($reason == '') {
			message('请填写 删除理由');
		}

		if (!intval($id) && !$q_id && (!is_array($ids) || !count($ids))) {
			message('请选择要删除的记录');
		}
	}
}

/**
 * 回收站
 */
if ( ! function_exists('recycle_log'))
{
	function recycle_log($type, $obj_id)
	{
		$CI =& get_instance();
		//$CI->load->model('admin/recycle_model');
		$reason = trim($CI->input->get_post('reason'));
		RecycleModel::add($type, $obj_id, $reason);
	}
}

/**
 * 回收站
 */
if ( ! function_exists('recycle_log_1'))
{
    function recycle_log_1($type, $obj_id, $reason)
    {
        $CI =& get_instance();
        //$CI->load->model('admin/recycle_model');
       // $reason = trim($CI->input->get_post('reason'));
        RecycleModel::add($type, $obj_id, $reason);
    }
}



if ( ! function_exists('is_password'))
{
	function is_password($password, $is_strong = false)
	{
	    $password = trim($password);
	    
	    if ($is_strong)
	    {
            $num = 0;
    	    if(preg_match('/[0-9]/', $password)) {
    	  		$num++;
    	  	}
    	  	
    	    if(preg_match('/[a-z]/', $password) || preg_match('/[A-Z]/', $password)) {
    	  		$num++;
    	  	}
    	  	
    	    if(preg_match('/[^A-Za-z0-9]/', $password)) {
    	  		$num++;
    	  	}
    	  	
    	  	if($num > 2 && (strlen($password) >= 6 && strlen($password) <= 20))
    	  	{
    		    return true;
    	    }
    	    else
    	    {
    		    return '密码必须同时包含数字、字母、符号，区分大小写， 长度为6~20个字符';
    	    }
	    }
	    else
	    {
	        if(strlen($password) >= 6 && strlen($password) <= 20)
	        {
	            return true;
	        }
	        else
	        {
	            return '密码长度必须为6~20个字符';
	        }
	    }
	}
}

if ( ! function_exists('mkdirs'))
{
	function mkdirs($dir)
	{
		if(!is_dir($dir))
		{
			if(!mkdirs(dirname($dir)))
			{
				return false;
			}
			if(!mkdir($dir, 0777))
			{
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists('cmp'))
{
    function cmp($a, $b){
        if ($a["ctime"] == $b["ctime"]) {
            return 0;
        }
        return ($a["ctime"] > $b["ctime"]) ? -1 : 1;
    }

}

/**
 * 输出(供下载用)二进制文件
 * @author      白建平
 * @param       $mime   MIME类型
 * @param       $file   文件路径
 * @param       $attach 指定文件名
 */
if ( ! function_exists('dumpFile'))
{
    function dumpFile($mime, $file, $attach)
    {
        if (is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")))
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
}


/**
 * 输出HTML格式的EXCEL文件
 * @param   string $attach  指定附件文件名
 */
if (!function_exists('dumpExcel'))
{
    function dumpExcel($attach)
    {
        if (is_numeric(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")))
        {
            $attach = iconv("UTF-8", "GBK", $attach);
        }
        ob_clean();
        header("Content-Type:application/ms-excel");
        header("Content-Disposition:attachment;filename=\"$attach\"");
    }
}
    
/**
 * 准考证转换为系统准考证号规则
 * @param     string   $external_exam_ticket   外部准考证号
 * @param     int      $rule                   转换规则
 * @return    string   转换后的准考证号
 */
function exam_ticket_maprule_encode($external_exam_ticket, $rule)
{
    $rule = intval($rule);
    if ($rule > 0)
    {
        $func = "encode_" . $rule;
        if (function_exists($func))
        {
            return $func($external_exam_ticket);
        }
        $len = strlen($external_exam_ticket);
        if ($len > 19)
        {
            die('Error external exam ticket length(must less than 19): ' . $external_exam_ticket);
        }
        if ($len >= 18)
        {
            return $external_exam_ticket;
        }
        /*
        if (substr($external_exam_ticket, 0, 2) == '90')
        {
            return (900 + $rule) . $external_exam_ticket;
        }
         */
        if ($len > 9)
        {
            die('Error external exam ticket length(must less than 9): ' . $external_exam_ticket);
        }
        if ($rule < 10)
        {
            return (900 + $rule) . $external_exam_ticket;
        }
        return (91000000 + $rule) . $external_exam_ticket;
    }
    else
    {
       return $external_exam_ticket;
    }
}

/**
 * 系统准考证转换为外部准考证号规则
 * @param     string   $internal_exam_ticket   内部准考证号
 * @param     int      $rule                  转换规则
 * @return    string   转换后的准考证号
 */
function exam_ticket_maprule_decode($internal_exam_ticket, $rule)
{
    $rule = intval($rule);
    if ($rule > 0)
    {
        $func = "decode_" . $rule;
        if (function_exists($func))
        {
	    return $func($internal_exam_ticket);
        }
        $len = strlen($internal_exam_ticket);
        if ($len >= 18)
        {
            return $internal_exam_ticket;
        }
        $l2 = substr($internal_exam_ticket, 0, 2);
        if ($l2 == '90')
        {
            return substr($internal_exam_ticket, 3);
        }
        else if ($l2 == '91')
        {
            return substr($internal_exam_ticket, 8);
        }
        else
        {
            die('Error internal exam ticket: ' . $internal_exam_ticket);
        }
    }
    else
    {
	return $internal_exam_ticket;
    }
}
/* End of file site_helper.php */
/* Location: application/helpers/site_helper.php */
