<?php if ( ! defined('BASEPATH')) exit();

// +------------------------------------------------------------------------------------------
// | Author: TCG
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 评分项管理控制器  Dates: 2015-08-23
// +------------------------------------------------------------------------------------------

class Evaluation_option extends A_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* 权限检查 */
        if (!$this->check_power('evaluation_option_manage')) return;
    }

    /**
     * 列表页面
     *
     * @return void
     */
    public function index()
    {
        /** 搜索 */
        $search = array();
        $search['status != '] = '-1';

        $get = $this->input->get();

        if (!empty($get['begin_time'])) {
            $search['createtime >='] = strtotime($get['begin_time'] . "00:00:01");
        }

        if (!empty($get['end_time'])) {
            $search['createtime <='] = strtotime($get['end_time'] . "23:59:59");
        }

        if (!empty($get['keyword'])) {
            $search['title like'] = '%' . trim($get['keyword']) . '%';
        }

        $data = array();

        /** 分页连接 */
        $total = EvaluationOptionModel::get_count(array('status != ' => '-1'));
        $current_page = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $per_page = 15;
        $purl = current_url();
        $data['pagination'] = multipage($total['count'], 15, $current_page, $purl);

        /** 获取数据 */
        $limit = ($per_page * ($current_page -1)) . ',' . $per_page;
        $data['options'] = EvaluationOptionModel::get_options($search, '*', $limit);
        $data['search'] = $get;

        $this->load->view('interview/evaluation_option_index', $data);
    }

    /**
     * 设置本条数据可用
     *
     * @return void
     **/
    public function enabled($id)
    {
        if (empty($id)) {
            message('参数错误！ID不能为空！', 'admin/evaluation_option/index');
        }

        $result = EvaluationOptionModel::set_status((int)$id, 1);

        if ($result) {
            message('评分项已启用！', 'admin/evaluation_option/index');
        } else {
            message('修改失败! 请重试！', 'admin/evaluation_option/index');
        }
    }

    /**
     * 设置本条数据不可用
     *
     * @return void
     **/
    public function disabled($id)
    {
        if (empty($id)) {
            message('参数错误！ID不能为空！', 'admin/evaluation_option/index');
        }

        $result = EvaluationOptionModel::set_status((int)$id, 0);

        if ($result) {
            message('评分项已禁用！', 'admin/evaluation_option/index');
        } else {
            message('修改失败! 请重试！', 'admin/evaluation_option/index');
        }
    }

    /**
     * 新增页面
     *
     * @return void
     */
    public function add()
    {
        $this->load->view('interview/evaluation_option_add');
    }

    /**
     * 更新页面
     *
     * @return void
     */
    public function update($id)
    {
        if (!is_numeric($id)) {
            message('参数错误！', 'admin/evaluation_option/index');
        }

        $data = array();
        $data['row'] = EvaluationOptionModel::get_one($id);

        $this->load->view('interview/evaluation_option_update', $data);
    }

    /**
     * 新增处理页面
     *
     * @return void
     */
    public function add_save()
    {
        $data = $this->input->post();

        /** 验证 */
        /** 标题 */
        if (empty($data['title'])) {
            message('标题不能为空！');
        }
        /** 总分 */
        if (empty($data['score']) || !is_numeric($data['score']) || $data['score'] <=0) {
            message('总分不能为空,且必须为大于0的数字！');
        }

        $data['createtime'] = time();

        $result = EvaluationOptionModel::add($data);

        if ($result) {
            message('评分项添加成功', 'admin/evaluation_option/index');
        } else {
            message('评分项添加失败！请重新尝试！', 'admin/evaluation_option/index');
        }
    }

    /**
     * 更新处理界面
     *
     * @return void
     */
    public function update_save()
    {
        $data = $this->input->post();

        /** 验证 */
        if (empty($data['id'])) {
            message('参数错误！');
        }
        /** 标题 */
        if (empty($data['title'])) {
            message('标题不能为空！');
        }
        /** 总分 */
        if (empty($data['score']) || !is_numeric($data['score'])) {
            message('总分不能为空,且只能为数字！');
        }
        
        $data['createtime'] = time();

        $id = $data['id'];
        unset($data['id']);

        $result = EvaluationOptionModel::update($id, $data);

        if ($result) {
            message('评分项修改成功', 'admin/evaluation_option/index');
        } else {
            message('评分项修改失败！请重新尝试！', 'admin/evaluation_option/index');
        }
    }

    /**
     * 删除
     *
     * @return void
     */
    public function delete($id)
    {
        /** 验证 */
        if (empty($id)) {
            message('参数错误！');
        }

        $result = EvaluationOptionModel::recycle($id);

        if ($result) {
            message('评分项删除成功', 'admin/evaluation_option/index');
        } else {
            message('评分项删除失败！请重新尝试！', 'admin/evaluation_option/index');
        }
    }

}
