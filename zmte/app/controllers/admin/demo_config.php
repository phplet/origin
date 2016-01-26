<?php
if ( ! defined('BASEPATH')) exit();

/**
 * MINI测配置管理
 * @author tcg
 * @final 2015-08-10
 */
class demo_config extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
		
        if ( ! $this->check_power('demo_paper_report,demo_exam_config_manage')) return;
		
    }

    /**
     * MINI测配置列表
     */
    public function index()
    {
        $data['list'] = DemoConfigModel::get_demo_config_list();
        
        $data['grades'] = C('grades');
        $data['subjects'] = C('subject');
        
		$this->load->view('demo_config/index', $data);
    }

    
    /**
     * 添加MINI测配置
     */
    public function edit($dec_id = 0)
    {
        $data = array();
        
        $data['grade'] = C('grades');
        
        $data['exam'] = DemoConfigModel::get_parent_exam();
        
        if ($dec_id > 0)
        {
            $data['config'] = DemoConfigModel::get_demo_config($dec_id);
        }
        
        $this->load->view('demo_config/edit', $data);
    }
    
    /**
     * 设置MINI测配置
     */
    public function save_config()
    {
        $dec_id   = intval($this->input->post('dec_id'));
        $dec_name = trim($this->input->post('dec_name'));
        $grade_id = intval($this->input->post('grade_id'));
        $exam_pid = intval($this->input->post('exam_pid'));
        
        if (empty($dec_name))
        {
            message('名称不能为空');
        }
        
        if ($grade_id < 1 || $grade_id > 12)
        {
            message('年级不合法');
        }
        
        if ($exam_pid < 1)
        {
            message('考试期次不存在');
        }
        
        $config = DemoConfigModel::get_demo_config($dec_id);
        if ($config && $config['dec_grade_id'] == $grade_id 
            && $config['dec_exam_pid'] == $exam_pid)
        {
        }
        else if ($config && $config['dec_grade_id'] == $grade_id 
            && $config['dec_exam_pid'] != $exam_pid)
        {
            $param = array('exam_pid'=>$exam_pid);
            if (DemoConfigModel::check_config_exist($param))
            {
                message('考试期次已经在配置中设置过了');
            }
        }
        else if ($config && $config['dec_grade_id'] != $grade_id
            && $config['dec_exam_pid'] == $exam_pid)
        {
            $param = array('grade_id'=>$grade_id);
            if (DemoConfigModel::check_config_exist($param))
            {
                message('年级已经在配置中设置过了');
            }
        }
        else
        {
            $param = array('grade_id'=>$grade_id, 'exam_pid'=>$exam_pid);
            if (DemoConfigModel::check_config_exist($param))
            {
                message('年级或考试期次已经在配置中设置过了');
            }
        }
        
        $exam = ExamModel::get_exam($exam_pid);
        if (empty($exam))
        {
            message('考试期次不存在');
        }
        
        if ($exam['grade_id'] != $grade_id)
        {
            message('考试期次与所选的年级不匹配');
        }
        
        $exam_subject = DemoConfigModel::get_parent_exam($exam_pid);
        if (empty($exam_subject))
        {
            message('该考试期次未设置考试学科');
        }
        
        $subject = C('subject');
        $subject_name = array();
        foreach ($exam_subject as $item)
        {
            $exam_paper = $this->db->get_where("exam_subject_paper", array('exam_id' => $item['exam_id']))->row_array();
            if (empty($exam_paper))
            {
                $subject_name[] = $subject[$item['subject_id']];
            }
        }
        
        if ($subject_name)
        {
            message("该考试期次中【" . implode("、", $subject_name) . "】学科未分配试卷");
        }
        
        $place = ExamPlaceModel::get_exam_place_list(array('exam_pid'=>$exam_pid));
        if (empty($place))
        {
            message('该考试期次未设置考场');
        }
        
        /*
        $this->load->model('admin/exam_place_subject_model');
        $place_subject = $this->exam_place_subject_model->get_exam_place_subject_list(array('place_id'=>$place[0]['place_id']));
        if (empty($place_subject))
        {
            message('该考试期次考场未添加考试学科');
        }
        */
             
        $data = array(
            'dec_name' => $dec_name,
            'dec_grade_id' => $grade_id,
            'dec_exam_pid' => $exam_pid,
            'place_id' => $place[0]['place_id'],
        );
        
        if ($dec_id > 0)
        {
            $res = DemoConfigModel::update($data, $dec_id);
            admin_log('edit', 'demo_exam_config', $dec_id);
        }
        else 
        {
            $res = DemoConfigModel::add($data);
            admin_log('add', 'demo_exam_config', $this->db->insert_id());
        }
        
        if ($res)
        {
            $this->load->model('cron/summary_paper_model');
            $this->summary_paper_model->do_all($exam_pid);
        }
        
        DemoConfigModel::update_cache();
        $back_url = "/admin/demo_config/index";
        
        message('MINI测' . ($dec_id ? '修改' : '添加') . ($res ? '成功' : '失败'), $back_url);
    }
    
    /**
     * 删除MINI测配置信息
     */
    public function delete($dec_id)
    {
        if (!$dec_id)
        {
            $dec_id = implode(',', $this->input->get('ids'));
        }

        if (!$dec_id)
        {
            message('请选择需要删除的配置信息');
        }
        
        $res = DemoConfigModel::delete($dec_id);
        
        if ($res)
        {
            admin_log('delete', 'demo_exam_config', $dec_id);
            DemoConfigModel::update_cache();
        }
        
        $back_url = "/admin/demo_config/index";
        message('MINI测配置信息删除' . ($res ? '成功' : '失败'), $back_url);
    }
    
    /**
     * 更新MIN测配置缓存
     */
    public function update_cache()
    {
        DemoConfigModel::update_cache();
        
        $back_url = "/admin/demo_config/index";
        message('MINI测配置缓存已更新', $back_url);
    }
    
    /**
     * 更新MIN测试卷
     */
    public function update_demo_paper($exam_pid)
    {
        $this->load->model('cron/summary_paper_model');
        
        $this->summary_paper_model->do_all($exam_pid);
    
        $back_url = "/admin/demo_config/index";
        
        message('试卷已更新', $back_url);
    }
}
