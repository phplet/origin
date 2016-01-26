<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_static_source
{
	const STATIC_PATH = '';

	/**
	 * css 资源引用整合
	 * @param mixed $css_path
	 */
	public function css($css_path, $compress = 'AUTO', $cache_time = 10)
	{
		try {
			$css_path = is_string($css_path) ? array($css_path) : $css_path;
			$css_base_path = FCPATH . 'css';

			$output = array();
			$paths = array();
			foreach ($css_path as $path) {
				$file_path = $css_base_path . '/' . $path;
				$file_path = stripos($file_path, '.css') !== false ? $file_path  : ($file_path . '.css');
				if (!file_exists($file_path)) {
					echo 'Notice: Can not find the css path: ' . $file_path . ', please check.';
					break;
				}

				$link_path = base_url() . 'css/' . $path;

				$paths[] = $path;
				$output[$file_path] = "<link rel='stylesheet' href='{$link_path}'/>";
			}

			$static_source_output =& load_class('Static_source_output', 'core');
            if ($compress === 'AUTO')
            {
                $compress = defined('ENVIRONMENT') && ENVIRONMENT == 'production';
            }
			if ($compress === TRUE)
			{
				if (!($compress_file_path = $static_source_output->_display_file_cache(implode(':', $paths), 'css')))
				{
					//尝试写入缓存
					$arr = array();
					$file_paths = array_keys($output);
					foreach ($file_paths as $file_path)
					{
						$content = file_get_contents($file_path);
						$arr[] = str_replace(array("@CHARSET", '"UTF-8";', '"gbk";', '"gb2312";'), '', $content);
					}

					$static_source_output->cache($cache_time);
					$static_source_output->_write_file_cache(implode('', $arr), implode(':', $paths), 'css');

					//首次先输出原型
					echo implode("\n", $output);
				}
				else
				{
					//$site_url = host_url();
                    $site_url = dirname(dirname(base_url()));
					echo "<link rel='stylesheet' href='{$site_url}/static_source_mini.php?t=css&k={$compress_file_path}'/>";
				}
			}
			else
			{
				echo implode("\n", $output);
			}

		} catch (Exception $e) {
			//do nothing
		}
	}

	/**
	 * js 资源引用整合
	 * @param mixed $js_path
	 */
	public function js($js_path, $compress = false, $cache_time = 10)
	{
		try {
			$js_path = is_string($js_path) ? array($js_path) : $js_path;
			$js_base_path = FCPATH . 'js';

			$output = array();
			$paths = array();
			foreach ($js_path as $path) {
				$file_path = $js_base_path . '/' . $path;
				$file_path = stripos($file_path, '.js') !== false ? $file_path  : ($file_path . '.js');
				if (!file_exists($file_path)) {
					echo 'Notice: Can not find the js path: ' . $file_path . ', please check.';
					break;
				}
				$compress = defined('ENVIRONMENT') && (ENVIRONMENT == 'production');
				$link_path = base_url() . 'js/' . $path;
                /*
				if($compress==false)
				    $link_path = base_url() . 'js/' . $path.'?'.time();
				else
				    $link_path = base_url() . 'js/' . $path;
				    */
				$paths[] = $path;
				$output[$file_path] = "<script language='javascript' type='text/javascript' charset='utf-8' src='{$link_path}'></script>";
			}

			$static_source_output =& load_class('Static_source_output', 'core');
            if ($compress === 'AUTO')
            {
                $compress = defined('ENVIRONMENT') && (ENVIRONMENT == 'production');

                /*
                 * 由于直接合并文件，会被有注释一行终止执行，先放开，不压缩
                 */
                $compress = FALSE;
            }
			if ($compress === TRUE)
			{
				if (!($compress_file_path = $static_source_output->_display_file_cache(implode(':', $paths), 'js')))
				{
					//尝试写入缓存
					$arr = array();
					$file_paths = array_keys($output);
					foreach ($file_paths as $file_path)
					{
						$content = file_get_contents($file_path);
						$arr[] = str_replace(array("@CHARSET", '"UTF-8";', '"gbk";', '"gb2312";'), '', $content);
					}

					$static_source_output->cache($cache_time);
					$static_source_output->_write_file_cache(implode('', $arr), implode(':', $paths), 'js');

					//首次先输出原型
					echo implode("\n", $output);
				}
				else
				{
					$site_url = dirname(dirname(base_url()));
					echo "<script language='javascript' type='text/javascript' charset='utf-8' src='{$site_url}/static_source_mini.php?t=js&k={$compress_file_path}'></script>";
				}
			}
			else
			{
				echo implode("\n", $output);
			}
		} catch (Exception $e) {
			//do nothing
		}
	}

	/**
	 * js 全局共享资源引用整合
	 * @param mixed $js_path
	 * @param string $type (js || css)
	 */
	public function global_source($source_path, $type = 'js')
	{
		try {
			$source_path = is_string($source_path) ? array($source_path) : $source_path;

			$cfg =& load_class('Config', 'core');
			$global_source_host = $cfg->item('global_source_host');

			$output = array();
			foreach ($source_path as $path) {
				$file_path = $global_source_host . '/' . ltrim($path, '/');
				if ($type == 'js') {
					$output[] = "<script language='javascript' type='text/javascript' charset='utf-8' src='{$file_path}'></script>";
				} elseif ($type == 'css') {
					$output[] = "<link rel='stylesheet' href='{$file_path}'/>";
				}
			}

			echo implode("\n", $output);

		} catch (Exception $e) {
		//do nothing
		}
	}
}