<?php if (!defined('BASEPATH')) exit();
/**
 *
 * 评估模板管理--评估模板 控制器
 * @author tcg
 * @final 2015-08-31
 *
 */
class Evaluate_template extends A_Controller
{
    private static $template_type_name;
    /**
     * 实现父类构造函数，载入模型
     */
    public function __construct()
    {
        parent::__construct();

        if ( ! $this->check_power('evaluate_template_manage')) return;

        self::$template_type_name = array(
            '0' => '机考总结',
            '1' => '机考学科',
            '4' => '机考班级',
            '6' => '机考教师',
            '2' => '面试学科',
            '3' => '面试总结',
            '5' => '选学选考'
        );
    }

    /**
     * 评估模板页面
     *
     * @return  void
     */
    public function index($template_type = 0)
    {
        $page = intval($this->input->get('page'));
        $page = $page ? $page : 1;

        $per_page = intval($this->input->get('per_page'));
        $per_page = $per_page ? $per_page : 15;

        $param = array('template_type'=>$template_type);

        /* 模板类型 0:综合 1:学科 2:面试 3:面试综合 */
        $data['list'] = EvaluateTemplateModel::get_evaluate_template_list(
                $param, $page, $per_page);

        $data['subject'] = C('subject');
        $data['template_type'] = $template_type;

        $this->session->set_userdata($param);

        // 分页
        $purl = site_url("admin/evaluate_template/index/$template_type");
        $total = EvaluateTemplateModel::get_evaluate_template_list_count($param);
        $data['pagination'] = multipage($total, $per_page, $page, $purl);

        $data['template_type_name'] = self::$template_type_name;

        $this->load->view('evaluate_template/index', $data);
    }

    /**
     * 编辑模板
     */
    public function edit($template_id = 0)
    {
        if ($template_id)
        {
            $data['info'] = EvaluateTemplateModel::get_evaluate_template_info(
                    $template_id);
        }

        $data['template_type'] = (int)$this->session->userdata('template_type');

        $data['subject'] = C('subject');

        $data['template_type_name'] = self::$template_type_name;
        $data['module'] = EvaluateModuleModel::get_evaluation_module_info(0, $data['template_type']);

        $this->load->view('evaluate_template/edit', $data);
    }

    /**
     * 设置模板
     */
    public function update()
    {
        $param = array();

        $template_id = intval($this->input->post('template_id'));

        $param['template_name'] = trim($this->input->post('template_name'));
        if (empty($param['template_name']))
        {
            message('请输入模板名称');
        }

        $param['template_subjectid'] = $this->input->post('template_subjectid');
        if ($param['template_subjectid'])
        {
            $subjectid_str = implode(',', $param['template_subjectid']);
            $param['template_subjectid'] = "," . $subjectid_str . ",";
        }

        $sel_module = $this->input->post('template_module');


        if (empty($sel_module))
        {
            message('请勾选模块');
        }

        foreach ($sel_module as $parent_moduleid => $val)
        {
            $i = 1;
            foreach ($val['children'] as $item)
            {
                if (!empty($item['module_id']))
                {
                    if ($i == 1)
                    {
                        $param['module'][] = array(
                                'template_id'=>$template_id,
                                'template_module_sort'=> $val['sort'] ? $val['sort'] : $val['module_sort'],
                                'template_module_id'=>$parent_moduleid
                        );

                        $i++;
                    }

                    $param['module'][] = array(
                            'template_id'=>$template_id,
                            'template_module_sort'=> $item['sort'] ? $item['sort'] : $item['module_sort'],
                            'template_module_id'=>$item['module_id']
                    );
                }
            }
        }

        $template_type = (int)$this->session->userdata('template_type');

        if ($template_id > 0)
        {
            $res = EvaluateTemplateModel::update_evaluate_template(
                    $param, $template_id);

            $message = "修改模板";

            $res && admin_log('edit', 'evaluate_template', $template_id);
        }
        else
        {
            $param['template_type'] = $template_type;

            $res = EvaluateTemplateModel::add_evaluate_template($param);

            $message = "新增模板";

            $res && admin_log('add', 'evaluate_template', $res);
        }

        message($message . ($res ? '成功' : '失败'),
                    "/admin/evaluate_template/index/$template_type");
    }

    /**
     * 删除模板
     */
    public function delete($template_id = 0)
    {
        if (!$template_id)
        {
            $template_id = implode(',', $this->input->post('ids'));
        }

        $template_type = (int)$this->session->userdata('template_type');

        if (!$template_id)
        {
            message('请选择需要删除的模板');
        }

        $back_url = "/admin/evaluate_template/index/" . $template_type;

        if (EvaluateTemplateModel::remove_evaluate_template($template_id))
        {
            admin_log('delete', 'evaluate_template', $template_id);

            message('删除模板成功', $back_url);
        }
        else
        {
            message('删除模板失败', $back_url);
        }
    }
}
