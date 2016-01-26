<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-考试报告
 *
 * @author qcchen
 * @final 2013-12-04
 */
class General_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 生成测试报告
     */
    public function general_report()
    {
		$this->load->model('demo/report/subject_question_model', 'sq_model');
		$this->load->model('demo/report/subject_suggest_model', 'ss_model');

		$now = time();

		//考试期次
		$userdata = $this->session->all_userdata();

		//检查该考试是否已经考完 && 作弊
		if (!isset($userdata['report_mark']))
		{
			return false;
		}
		list($exam_pid, $subject_id, $uid, $exam_id) = explode('_', $userdata['report_mark']);
		$subject_name = C('subject/'.$subject_id);

		$filepath = $this->_get_cache_root_path() . "/html/demo_report/{$exam_pid}/{$uid}/{$subject_id}.html";

		if (file_exists($filepath)) 
		{
			return true;
		}

		try {
			//生成成绩
			//require_once ('exam.php');
			//$exam_cron = new Exam();
			//$exam_cron->cal_test_score($exam_pid, $uid);
		    $result = ExamstatModel::calStudentResults($exam_pid, $uid);
		    if (!$result)
		    {
		        return false;
		    }
		    
            //统计报告所需数据
            $result = SummaryModel::summary_demo($exam_pid, $uid);
            if (!$result)
            {
                return false;
            }
            
			//检查该学生是否作弊 & 正在考中
			$sql = "select etp_flag, full_score, test_score 
			         from {pre}exam_test_paper 
			         where exam_pid={$exam_pid} and subject_id={$subject_id} and uid={$uid}";
			$result = $this->db->query($sql)->row_array();
            
			if (isset($result['etp_flag']) 
			    && $result['etp_flag'] != '2') 
			{
                return false;
			}

			$data = array();

			$data['subject_name'] = $subject_name;
            $data['test_score'] = $result['test_score'];
			$data['full_score'] = $result['full_score'];

			//试题分析及与之对应的评价
			$data['sq_all'] = $this->sq_model->get_all($exam_id, $uid);//知识点

		 	//诊断及建议
			$data['ss_summary'] = $this->ss_model->module_summary($exam_id, $uid);//总体水平等级和排名

			$data['ss_application_situation'] = $this->ss_model->module_application_situation($exam_id, $uid);//总体水平等级和排名

			$output = $this->load->view('../demo/report/subject', $data, true);

			//保存html模板
			$res = $this->_put_html_content($exam_pid, $subject_id, $uid, $output);

			return $res;

		} 
		catch (Exception $e) 
		{
			return false;
		}
    }

    /**
     * 获取项目根目录
     */
    private function _get_cache_root_path()
    {
    	return realpath(dirname(APPPATH)) . '/cache';
    }

    /**
     * 生成html文件
     * @note:
     * 	保存路径：cache/html/demo_report/{exam_pid}/{uid}/{$subject_id}.html
     */
    private function _put_html_content($exam_pid, $subject_id, $uid, $html)
    {
    	$html = chr(0xEF).chr(0xBB).chr(0xBF).$html;

    	$file_path = $this->_get_cache_root_path() . "/html/demo_report/{$exam_pid}/{$uid}";
    	if (!is_dir($file_path))
    	{
    		mkdirs($file_path);
    	}

    	$filepath = $file_path."/{$subject_id}.html";
    	if (file_exists($filepath))
    	{
    		return true;
    	}

    	$res = file_put_contents($filepath, $html);

    	return $res > 0;
    }

    /**
     * 获取报告html文件
     * @note:
     * 	保存路径：cache/html/demo_report/{exam_pid}/{uid}/{$subject_id}.html
     */
    public function get_html_content($exam_pid, $subject_id, $uid)
    {
    	$file_path = $this->_get_cache_root_path() . "/html/demo_report/{$exam_pid}/{$uid}/{$subject_id}.html";

    	if (!file_exists($file_path))
    	{
    		return false;
    	}

    	$opts = array(
    			'http' => array(
    					'method'	=> "GET",
    					'timeout'	=> 5,//单位秒
    			)
    	);

    	$content = file_get_contents($file_path, false, stream_context_create($opts));

    	return $content === false ? false : $content;
    }
}
