<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * Memcache Class
 *
 * @package        CodeIgniter
 * @subpackage    Libraries
 * @category    Memcaches
 * @author        ExpressionEngine Dev Team
 * @link        http://codeigniter.com/user_guide/libraries/Memcaches.html
 */
class CI_Memcache {

    var $host;

    var $port;

    var $timeout;

    var $mc;


    public function __construct( $memcache_config = array())
    {
        log_message('debug', "Memcache Class Initialized");

        $this->CI =& get_instance();

        $r = $this->CI->config->load('memcache');

        if( !$memcache_config ) {
            $memcache_config = $this->CI->config->item('memcache');
        }

        if( $memcache_config ) {

            $this->memcache_config = $memcache_config;


            return true;
        }

        return false;

        log_message('debug', "Memcache successfully run");
    }



    /**
     * init memcache
     * @return Memcache
     */
    public function init()
    {
        $key = $this->host . '-' . $this->port;
        if( !$this->mc[$key] ) {
            $memObj   =   new Memcache();
            $count = count( $this->memcache_config );


            foreach( $this->memcache_config as $key => $val)
            {
                $memObj->addServer( $val['host'], $val['port'], true, $val['timeout'], $val['weight'], 15, true);
            }

            return $this->mc[$key] = $memObj;
        } else {
            return $this->mc[$key];
        }
    }

}