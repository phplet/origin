<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class MY_Email extends CI_Email {

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
     * Set Email Subject
     *
     * @access    public
     * @param     string
     * @return    void
     */
    function subject($subject)
    {
        $subject = '=?'. $this->charset .'?B?'. base64_encode($subject) .'?=';
        $this->_set_header('Subject', $subject);
    }
}
/* End of file Email.php */
/* Location: ./system/libraries/Email.php */
