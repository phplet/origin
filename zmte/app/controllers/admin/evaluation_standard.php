<?php if ( ! defined('BASEPATH')) exit();

// +------------------------------------------------------------------------------------------
// | Author: TCG
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 评分标准管理控制器  Dates: 2015-08-24
// +------------------------------------------------------------------------------------------

class Evaluation_standard extends A_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* 权限检查 */
        if (!$this->check_power('evaluation_standard_manage')) return;
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
            $search['create_time >='] = strtotime($get['begin_time'] . "00:00:00");
        }

        if (!empty($get['end_time'])) {
            $search['create_time <='] = strtotime($get['end_time'] . "23:59:59");
        }

        if (!empty($get['keyword'])) {
            $search['title like'] = '%' . trim($get['keyword']) . '%';
        }

        $data = array();

        /** 分页连接 */
        $total = EvaluationStandardModel::get_count(array('status != ' => '-1'));
        $current_page = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $per_page = 15;
        $purl = current_url();
        $data['pagination'] = multipage($total['count'], 15, $current_page, $purl);

        /** 获取数据 */
        $limit = ($per_page * ($current_page -1)) . ',' . $per_page;
        $data['list'] = EvaluationStandardModel::get_multiterm($search, '*', $limit);
        $data['search'] = $get;

        $this->load->view('interview/evaluation_standard_index', $data);
    }

    /**
     * 设置本条数据可用
     *
     * @return void
     **/
    public function enabled($id)
    {
        if (empty($id)) {
            message('参数错误！ID不能为空！', 'admin/evaluation_standard/index');
        }

        $result = EvaluationStandardModel::set_status((int)$id, 1);

        if ($result) {
            message('评分标准已启用！', 'admin/evaluation_standard/index');
        } else {
            message('修改失败! 请重试！', 'admin/evaluation_standard/index');
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
            message('参数错误！ID不能为空！', 'admin/evaluation_standard/index');
        }

        $result = EvaluationStandardModel::set_status((int)$id, 0);

        if ($result) {
            message('评分标准已禁用！', 'admin/evaluation_standard/index');
        } else {
            message('修改失败! 请重试！', 'admin/evaluation_standard/index');
        }
    }

    /**
     * 考试期次列表
     *
     * @return void
     */
    public function exam_place_list()
    {
        $data = array();
        $data['list'] = ExamModel::get_exam_list_all(array('exam_pid' => '0', 'status' => '1'), 'exam_id,exam_name');

        /* 查询已经有评分标准的考试期次 */

        foreach ($data['list'] as $key => $value) {
            $flag = EvaluationStandardExamModel::get_standard_by_exam($value['exam_id']);
            if ($flag) {
                $data['list'][$key]['flag'] = true;
            } else {
                $data['list'][$key]['flag'] = false;
            }
        }

        $this->load->view('interview/exam_place_list', $data);
    }

    /**
     * 评分项列表
     *
     * @return void
     */
    public function evaluation_option_list()
    {
        $subject_id = intval($this->input->post('subject_id'));
        $data = array();
        $data['subject_id'] = $subject_id;
        $data['list'] = EvaluationOptionModel::get_options_all(array('status' => '1'), 'id,title');

        $this->load->view('interview/evaluation_option_list', $data);
    }

    /**
     * 新增页面
     *
     * @return void
     */
    public function add()
    {
        $data['subject'] = C('subject');
        $this->load->view('interview/evaluation_standard_add', $data);
    }

    /**
     * 更新页面
     *
     * @return void
     */
    public function update($id)
    {
        if (!is_numeric($id)) {
            message('参数错误！', 'admin/evaluation_standard/index');
        }

        $data = array();
        $data['row'] = EvaluationStandardModel::get_one($id);
        if (!$data['row'])
        {
            message('评分标准不存在！', 'admin/evaluation_standard/index');
        }
        
        $data['row']['subject_id'] = explode(',', $data['row']['subject_id']);
        $data['row']['weight'] = json_decode($data['row']['weight'], true);
        $data['row']['level'] = json_decode($data['row']['level'], true);

        /** 评分项信息 */
        $option_ids = json_decode($data['row']['options'], true);
        if ($option_ids)
        {
            foreach ($option_ids as $subject_id => $value)
            {
                foreach ($value as $option_id)
                {
                    $data['options'][$subject_id][] = EvaluationOptionModel::get_one($option_id, 'id,title');
                }
            }
        }
        else
        {
            $data['options'] = array();
        }

        /** 考试期次信息 */
        $data['exams'] = array();
        $exam_ids = EvaluationStandardExamModel::get_exam_by_standard($data['row']['id']);
        if (count($exam_ids) >= 1) {
            foreach ($exam_ids as $key => $value) 
            {
                $data['exams'][] = ExamModel::get_exam($value['exam_id'], 'exam_id,exam_name');
            }
        }
        
        $data['subject'] = C('subject');

        $this->load->view('interview/evaluation_standard_update', $data);
    }

    /**
     * 新增处理页面
     *
     * @return void
     */
    public function add_save()
    {
        $post = $this->input->post();
        
        /** 验证 */
        /** 标题 */
        if (empty($post['title'])) {
            message('标题不能为空！');
        }

        /** 考试期次 */
        if (count($post['exam_id']) < 1) {
            message('请选择考试期次！');
        }
        
        /** 学科 */
        if (count($post['subject_id']) < 1) {
            message('请选择学科！');
        }
        
        /** 学科权重 */
        
        $post['weight'] = array_filter($post['weight']);
        if (empty($post['weight']))
        {
            message('请填写学科权重！');
        }
        
        $tip_subject = array();
        $subject_weight = array();
        $subject_total_percent = 0;
        foreach ($post['subject_id'] as $subject_id)
        {
            if (empty($post['weight'][$subject_id]))
            {
                $tip_subject[] = C("subject/$subject_id");
            }
            else 
            {
                $subject_weight[$subject_id] = $post['weight'][$subject_id];
                $subject_total_percent += $post['weight'][$subject_id];
            }
        }
        
        if ($tip_subject)
        {
            message("请填写 [". implode('、', $tip_subject) ."] 学科权重");
        }
        
        if ($subject_total_percent != 100)
        {
            message('所有学科的权重占比总和必须等于 100');
        }
        
        /** 评分项 */
        if (count($post['option_id']) < 1) {
            message('请选择评分项！');
        }
        
        foreach ($post['subject_id'] as $subject_id)
        {
            if (empty($post['option_id'][$subject_id]))
            {
                $tip_subject[] = C("subject/$subject_id");
            }
        }
        
        if ($tip_subject)
        {
            message("请选择 [". implode('、', $tip_subject) ."] 学科评分项");
        }

        /* 排序 */
        foreach ($post['sort'] as $subject_id => $option_sort) {
            $sort = array();
            foreach ($option_sort as $key => $k_sort)
            {
                if (empty($k_sort)) {
                    $sort[0][] = $post['option_id'][$subject_id][$key];
                } else{
                    $sort[$k_sort][] = $post['option_id'][$subject_id][$key];
                }
            }
            
            ksort($sort);
            
            $options = array();
            foreach ($sort as $index => $val)
            {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $options[] = $v;
                    }
                }
            }
            
            $post['option_id'][$subject_id] = $options;
        }

        /** 等级及等级所占比例  */
        if (empty($post['level']))
        {
            message("请输入等级名称");
        }
        
        if (empty($post['level_percent']))
        {
            message("请输入等级所占百分比");
        }
        
        $tips = array();
        $levels = array();
        foreach ($post['level'] as $key => $level)
        {
            if (!$level)
            {
                $tips[1] = '请输入等级名称';
            }
            
            if (empty($post['level_percent'][$key]))
            {
                $tips[2] = '请输入等级所占百分比';
            }
            
            if (!$tips)
            {
                $levels[$level] = $post['level_percent'][$key];
            }
        }
        
        if ($tips)
        {
            message(implode("<br>", $tips));
        }
        
        $data = array();
       
        /** 格式转换 */
        $data['title'] = trim($post['title']);
        $data['subject_id'] = implode(',', $post['subject_id']);
        $data['weight'] = json_encode($post['weight']);
        $data['options'] = json_encode($post['option_id']);
        $data['level'] = json_encode($levels);
        $data['status'] = $post['status'];
        $data['create_time'] = time();

        /** 写入评分标准 */
        $standard_id = EvaluationStandardModel::add($data);

        if (!$standard_id) {
            message('评分标准添加失败！');
        }

        /** 写入评分标准与考试期次关联 */
        $result = EvaluationStandardExamModel::add($standard_id, $post['exam_id']);

        if ($result) {
            message('评分标准添加成功', 'admin/evaluation_standard/index');
        } else {
            message('评分标准添加失败！请重新尝试！', 'admin/evaluation_standard/index');
        }
    }

    /**
     * 更新处理界面
     *
     * @return void
     */
    public function update_save()
    {
        $post = $this->input->post();
        
        /** 验证 */
        /** 标题 */
        if (empty($post['title'])) {
            message('标题不能为空！');
        }

        /** 考试期次 */
        if (count($post['exam_id']) < 1) {
            message('请选择考试期次！');
        }
        
        /** 学科 */
        if (count($post['subject_id']) < 1) {
            message('请选择学科！');
        }
        
        /** 学科权重 */
        
        $post['weight'] = array_filter($post['weight']);
        if (empty($post['weight']))
        {
            message('请填写学科权重！');
        }
        
        $tip_subject = array();
        $subject_weight = array();
        $subject_total_percent = 0;
        foreach ($post['subject_id'] as $subject_id)
        {
            if (empty($post['weight'][$subject_id]))
            {
                $tip_subject[] = C("subject/$subject_id");
            }
            else 
            {
                $subject_weight[$subject_id] = $post['weight'][$subject_id];
                $subject_total_percent += $post['weight'][$subject_id];
            }
        }
        
        if ($tip_subject)
        {
            message("请填写 [". implode('、', $tip_subject) ."] 学科权重");
        }
        
        if ($subject_total_percent != 100)
        {
            message('所有学科的权重占比总和必须等于 100');
        }
        
        /** 评分项 */
        if (count($post['option_id']) < 1) {
            message('请选择评分项！');
        }
        
        foreach ($post['subject_id'] as $subject_id)
        {
            if (empty($post['option_id'][$subject_id]))
            {
                $tip_subject[] = C("subject/$subject_id");
            }
        }
        
        if ($tip_subject)
        {
            message("请选择 [". implode('、', $tip_subject) ."] 学科评分项");
        }

        /* 排序 */
        foreach ($post['sort'] as $subject_id => $option_sort) {
            $sort = array();
            foreach ($option_sort as $key => $k_sort)
            {
                if (empty($k_sort)) {
                    $sort[0][] = $post['option_id'][$subject_id][$key];
                } else{
                    $sort[$k_sort][] = $post['option_id'][$subject_id][$key];
                }
            }
            
            ksort($sort);
            
            $options = array();
            foreach ($sort as $index => $val)
            {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $options[] = $v;
                    }
                }
            }
            
            $post['option_id'][$subject_id] = $options;
        }

        /** 等级及等级所占比例  */
        if (empty($post['level']))
        {
            message("请输入等级名称");
        }
        
        if (empty($post['level_percent']))
        {
            message("请输入等级所占百分比");
        }
        
        $tips = array();
        $levels = array();
        foreach ($post['level'] as $key => $level)
        {
            if (!$level)
            {
                $tips[1] = '请输入等级名称';
            }
            
            if (empty($post['level_percent'][$key]))
            {
                $tips[2] = '请输入等级所占百分比';
            }
            
            if (!$tips)
            {
                $levels[$level] = $post['level_percent'][$key];
            }
        }
        
        if ($tips)
        {
            message(implode("<br>", $tips));
        }
        
        $data = array();
       
        /** 格式转换 */
        $data['title'] = trim($post['title']);
        $data['subject_id'] = implode(',', $post['subject_id']);
        $data['weight'] = json_encode($post['weight']);
        $data['options'] = json_encode($post['option_id']);
        $data['level'] = json_encode($levels);
        $data['status'] = $post['status'];

        /** 写入评分标准 */
        $result = EvaluationStandardModel::update($post['id'], $data);

        if (!$result) {
            message('评分标准修改失败！');
        }

        /** 清楚原本关联并从新写入评分标准与考试期次关联 */
        $result_one = EvaluationStandardExamModel::delete($post['id']);
        $result_two = EvaluationStandardExamModel::add($post['id'], $post['exam_id']);

        if ($result_one && $result_two)
        {
            message('评分标准修改成功', 'admin/evaluation_standard/index');
        } else {
            message('评分标准修改失败！请重新尝试！', 'admin/evaluation_standard/index');
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

        /* 解除评分标准和考试期次的关联 */

        $this->db->trans_begin();

        $delete_relationship = EvaluationStandardExamModel::delete($id);
        $result = EvaluationStandardModel::recycle($id);

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }

        if ($result && $delete_relationship) {
            message('评分标准删除成功', 'admin/evaluation_standard/index');
        } else {
            message('评分标准删除失败！请重新尝试！', 'admin/evaluation_standard/index');
        }
    }

}
