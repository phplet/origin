<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class MY_Upload extends CI_Upload
{
    /**
     * Constructor
     *
     * @access	public
     */
    public function __construct($props = array())
    {
        parent::__construct($props);
    }

    /**
     * Finalized Data Array
     *
     * Returns an associative array containing all of the information
     * related to the upload, allowing the developer easy access in one array.
     *
     * @return	array
     */
    public function data($item = '', $upload_type = 'static_source')
    {
        //上传资源类型
        $upload_types = array(
            //图片等公共资源
            'static_source' => end(explode('images/', $this->upload_path.$this->file_name)),
    
            //后台附件
            'admin_file' => str_replace(_ADMIN_UPLOAD_ROOT_PATH_,'',$this->upload_path.$this->file_name),
        );
        $data = array (
                'file_name'			=> $this->file_name,
                'file_type'			=> $this->file_type,
                'file_path'			=> $this->upload_path,
                'full_path'			=> $this->upload_path.$this->file_name,
                'raw_name'			=> str_replace($this->file_ext, '', $this->file_name),
                'orig_name'			=> $this->orig_name,
                'client_name'		=> $this->client_name,
                'file_ext'			=> $this->file_ext,
                'file_size'			=> $this->file_size,
                'is_image'			=> $this->is_image(),
                'image_width'		=> $this->image_width,
                'image_height'		=> $this->image_height,
                'image_type'		=> $this->image_type,
                'image_size_str'	=> $this->image_size_str,
                'file_relative_path' => $upload_types[$upload_type],
            );
        if ($item)
        {
            return $data[$item];
        }
        else 
        {
            return $data;
        }
    }

    /**
     * Validate Upload Path
     *
     * Verifies that it is a valid upload path with proper permissions.
     *
     *
     * @return	bool
     */
    public function validate_upload_path()
    {
        if ($this->upload_path == '')
        {
                $this->set_error('upload_no_filepath');
                return FALSE;
        }
        
        if ( ! @is_dir($this->upload_path))
        {
            // 文件夹不存在的话，自动创建
            mk_dir($this->upload_path);
            $this->upload_path = str_replace("\\", "/", @realpath($this->upload_path));
            //$this->set_error('upload_no_filepath');
            //return FALSE;
        }
        if (!is_really_writable($this->upload_path))
        {
            $this->set_error('upload_not_writable');
            return FALSE;
        }
        $this->upload_path = preg_replace("/(.+?)\/*$/", "\\1/",  $this->upload_path);
        return TRUE;
    }
    // --------------------------------------------------------------------
}
