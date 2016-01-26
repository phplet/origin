<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 扩展  Output 类
 */
class MY_Static_source_output extends CI_Output
{
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Write a Cache File
	 *
	 * @access	public
	 * @param 	string
	 * @param 	string
	 * @return	void
	 */
	function _write_file_cache($output, $url, $path_section)
	{
		$CFG =& load_class('Config', 'core');
		$path = $CFG->item($path_section, 'static_cache_path');
		$path = $path ? $path : '';
	
		$cache_path = ($path == '') ? (ROOTPATH.'cache/') : (ROOTPATH.'cache/'.$path.'/');
		if ( ! is_dir($cache_path) OR ! is_really_writable($cache_path))
		{
			log_message('error', "Unable to write cache file: ".$cache_path);
			return;
		}
	
		$cache_path .= md5($url);
	
		if ( ! $fp = @fopen($cache_path, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			log_message('error', "Unable to write cache file: ".$cache_path);
			return;
		}
	
		$expire = time() + ($this->cache_expiration * 60);
	
		if (flock($fp, LOCK_EX))
		{
			fwrite($fp, $expire.'TS--->'.$output);
			flock($fp, LOCK_UN);
		}
		else
		{
			log_message('error', "Unable to secure a file lock for file at: ".$cache_path);
			return;
		}
		fclose($fp);
		@chmod($cache_path, FILE_WRITE_MODE);
	
		log_message('debug', "Cache file written: ".$cache_path);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Update/serve a cached file
	 *
	 * @access	public
	 * @param 	string
	 * @param 	string
	 * @return	mixed
	 */
	function _display_file_cache($url, $path_section)
	{
		$CFG =& load_class('Config', 'core');
		$filepath = $CFG->item($path_section, 'static_cache_path');
		$linkpath = ($filepath == '' || FALSE === $filepath) ? ('cache/') : ('cache/'.$filepath.'/');
		$filepath = ($filepath == '' || FALSE === $filepath) ? (ROOTPATH.'cache/') : (ROOTPATH.'cache/'.$filepath.'/');

		$linkpath .= md5($url);
		$filepath .= md5($url);
		if ( ! @file_exists($filepath))
		{
			return FALSE;
		}
	
		if ( ! $fp = @fopen($filepath, FOPEN_READ))
		{
			return FALSE;
		}
	
		flock($fp, LOCK_SH);
	
		$cache = '';
		if (filesize($filepath) > 0)
		{
			$cache = fread($fp, filesize($filepath));
		}
	
		flock($fp, LOCK_UN);
		fclose($fp);
	
		// Strip out the embedded timestamp
		if ( ! preg_match("/(\d+TS--->)/", $cache, $match))
		{
			return FALSE;
		}
		
		// Has the file expired? If so we'll delete it.
		if (time() >= trim(str_replace('TS--->', '', $match['1'])))
		{
			if (is_really_writable($filepath))
			{
				@unlink($filepath);
				log_message('debug', "Cache file has expired. File deleted");
				return FALSE;
			}
		}
		
		return urlencode($linkpath);
	}
	
	
}

/* End of file MY_Static_source_output.php */
/* Location: ./application/core/MY_Static_source_output.php */
