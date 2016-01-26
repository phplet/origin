<?php !defined('BASEPATH') && exit();

/**
 * 同步阅卷系统考试成绩
 * @author TCG
 * @create 2015-12-18
 */
class Synczmossexamresult extends Cron_Controller
{
    public function sync()
    {
        ZmossModel::initSyncZmossExamResults();
    }
}