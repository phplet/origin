<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Register extends S_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // 默认首页
    public function index()
    {
        StudentModel::studentAjaxLogout();
        redirect('student/profile/basic');
    }
}
