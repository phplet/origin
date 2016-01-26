<?php if ( ! defined('BASEPATH')) exit();

// +------------------------------------------------------------------------------------------
// | Author: TCG
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 评分项管理控制器  Dates: 2015-08-17
// +------------------------------------------------------------------------------------------

class Interview_result extends A_Controller
{
    public function __construct()
    {
        parent::__construct();

        /* 权限检查 */
        if (!$this->check_power('interview_result_import')) return;
    }

    /**
     * 导入页面
     *
     * @return void
     */
    public function import()
    {   
        $data = array();

        $data['exams'] = ExamModel::get_exam_list_all(array('exam_pid' => '0', 'status' => '1'), 'exam_id,exam_name');

        $this->load->view('interview/interview_import', $data);
    }

    /**
     * 导入处理
     *
     * @return mixed void
     */
    public function save()
    {
        ini_set("display_errors", "On");
        error_reporting(-1);
        $post = $this->input->post();

        /** 上传文件 */
        $config['upload_path'] = '../../cache/excel/';
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024 * 10; #单位kb
        $config['overwrite'] = false;

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('file'))
        {
            $data = array();

            $data['exam_id'] = $post['exam_id'];

            $data['error'] = $this->upload->display_errors();

            $data['exams'] = ExamModel::get_exam_list_all(array('exam_pid' => '0', 'status' => '1'), 'exam_id,exam_name');

            $this->load->view('interview/interview_import', $data);
        } 
        else
        {
            /** 实时输出导入结果 */
            ob_end_flush();
            set_time_limit(0);

            $start_time = microtime(true);
            $upload_data = $this->upload->data();
            /** 读取excel */
            $this->load->library('PHPExcel');
            $this->load->library('PHPExcel/IOFactory');
            $inputFileType = IOFactory::identify($upload_data['file_relative_path']);
            $objReader = IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($upload_data['file_relative_path']);
            $data = $objPHPExcel->getSheet(0)->toArray();

            $end_time = microtime(true);
            $execute_time = $end_time-$start_time;

            if (!empty($data)) {
                echo '文件载入成功！执行时间：' . sprintf('%.4f',$execute_time) . 's' . '<hr/>';
                flush();
            }

            /** 评分标准信息 */
            $start_time = microtime(true);

            $relation = EvaluationStandardExamModel::get_standard_by_exam($post['exam_id']);
            $standard = EvaluationStandardModel::get_one($relation['standard_id']);
            $option_count = count(explode(',', $standard['options']));
            $end_time = microtime(true);
            $execute_time = $end_time-$start_time;

            if ($standard && !empty($standard) && ($option_count >= 1)) {
                echo '获取考试期次对应评分标准信息成功！执行时间：' . sprintf('%.4f',$execute_time) . 's' . '<hr/>';
                flush();
            } else {
                echo '获取考试期次对应评分标准信息失败！请检查本期次对应评分标准及评分项!';
                flush();
                exit;
            }

            if ($standard['status'] != 1) {
                echo '当前评分标准已禁用！停止导入！<hr/>';
                flush();
                exit;
            }

            /** 导入结果 */

            $start_time = microtime(true);

            foreach ($data as $key => $value) {

                if ($key < 1) {
                    continue;
                }

                $student_ticket = trim($value[0]);
                $sql = "select * from {pre}exam_student_list where student_ticket={$student_ticket}";
                $ruidabei_student = $this->db->query($sql)->row_array();

                if (!$ruidabei_student['uid'] > 0) {
                    echo "未查询到当前用户数据，在第" . ($key+1) . "行。姓名：". $value['1'] . "<br/>";
                    continue;
                }

                if (!$ruidabei_student) {
                    echo "未查询到当前用户数据，在第" . ($key+1) . "行。姓名：". $value['1'] . "<br/>";
                    continue;
                }

                if ($ruidabei_student['student_name'] != $value[1]) {
                    echo "当前用户睿达杯准考证号与姓名不符，在第" . ($key+1) . "行。姓名:" . $value[1] . "<br/>"; 
                    continue;
                }

                $option_index = 1;

                /* 获取学科信息 */
                $subject_name = "%" . $value[2] . "%";
                $subject_id = $this->db->select('subject_id')->get_where('subject', array('subject_name like' => $subject_name))->row_array();


                foreach ($value as $k => $v) {
                    if ($k < 3 || ($k >= (3 + $option_count))) {
                        continue;
                    }

                    /** 判断数据是否已存在数据库中 */
                    $param = array();
                    $param['exam_id'] =  $post['exam_id'];
                    $param['student_id'] = $ruidabei_student['uid'];
                    $param['subject_id'] = $subject_id['subject_id'];
                    $param['option_index'] = $option_index;
                    
                    $row = array();
                    $row['exam_id'] = $post['exam_id'];
                    $row['student_id'] = $ruidabei_student['uid'];
                    $row['subject_id'] = $subject_id['subject_id'];
                    $row['option_index'] = $option_index;
                    $row['scroe'] = $v ? $v : 0;
                    $row['create_time'] = time();

                    $option_index++;

                    $result = InterviewResultModel::get_one($param);

                    if ($result && !empty($result)) {
                        echo '<p style="color:red;">警告！本条数据已存在！将覆盖原有数据！在第' . ($key+1) . '行:' . $param['student_id'] . '</p><br/>';

                        $result = InterviewResultModel::update(array('id' => $result['id']), $row);
                    } else {
                        $result = InterviewResultModel::add($row);
                    }

                    if ($result) {
                        echo '<p style="color:green;">第' . ($key+1) . '行' . '第' . ($k-2) . '条导入成功！</p><br/>';
                        flush();
                    } else {
                        echo '<p style="color:red;">错误！第' . ($key+1) . '行' . '第' . ($k-2) . '条导入失败！</p><br/>';
                        flush();
                    }
                }
            }
            
            $end_time = microtime(true);
            $execute_time = $end_time-$start_time;
            echo '导入成功！用时：' . $execute_time . 's';
        }
    }

}
