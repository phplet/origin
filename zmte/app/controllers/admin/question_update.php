<?php

// +------------------------------------------------------------------------------------------ 
// | Author: TCG <TCG_love@163.com> 
// +------------------------------------------------------------------------------------------ 
// | There is no true,no evil,no light,there is only power. 
// +------------------------------------------------------------------------------------------ 
// | Description: 试卷更新 Dates: 2015-02-02
// +------------------------------------------------------------------------------------------ 

if (!defined('BASEPATH')) exit();

class Question_update extends A_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* 试卷模型 */
    }

    /**
     * 更新试题图片url
     *
     * @return void
     * @author 
     **/
    public function url_update($page = 1)
    {
        $sql = "select count(*) as count from {pre}question;";
        $count = $this->db->query($sql)->row_array();

        /* 分页计算 */
        $row_count = 100;
        $total_page = ceil($count['count'] / $row_count);

        if ($page > $total_page) {
            echo "更新完成!";exit;
        }

        /* 每次更新1000条 */
        $offset = ($page-1) * $row_count;
        $sql = "select ques_id,title from {pre}question limit " . $offset . ", " . $row_count;
        $questions = $this->db->query($sql)->result_array();

        foreach ($questions as $key => $value) {

            if (empty($value['ques_id'])) {
                continue;
            }
            /* 替换 */
            $reg = "/(?<=\<img\ssrc\=\"http\:\/\/).*(?=\/js\/third_party\/ueditor\/php)/Uis";
            $new_http_host = $_SERVER['HTTP_HOST'];

            $data = array();
            $data['title'] = preg_replace($reg, $new_http_host, $value['title']);
            $rst = $this->db->where('ques_id', $value['ques_id'])->update('question', $data);

            if (!$rst) {
                echo "更新失败，错误试题号：" . $value['ques_id'];
                exit;
            }
        }

        message('第' . $offset . '条至' . ($offset + $row_count) . '条更新成功！(更新时间较长，请耐心等待！页面将在3秒后自动跳转，请勿点击！)', site_url('admin/question_update/url_update/' . ++$page));
    }
}
