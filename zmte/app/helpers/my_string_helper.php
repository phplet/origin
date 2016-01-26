<?php if ( ! defined('BASEPATH')) exit();
/**
 * 获得汉字拼音首字母
 */
if ( ! function_exists('string_to_pinyin'))
{
	function string_to_pinyin($str)
	{
		$string_util =& load_class('string_to_pinyin', 'utils');
        return $string_util->getInitials($str);
	}
}

/* End of file site_helper.php */
/* Location: application/helpers/my_string_helper.php */