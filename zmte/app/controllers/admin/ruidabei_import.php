<?php if ( ! defined('BASEPATH')) exit();

// +------------------------------------------------------------------------------------------
// | Author: TCG <TCG_love@163.com> 
// +------------------------------------------------------------------------------------------
// | There is no true,no evil,no light,there is only power.
// +------------------------------------------------------------------------------------------
// | Description: 评分项管理控制器  Dates: 2015/01/08 
// +------------------------------------------------------------------------------------------

class Ruidabei_import extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
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

        $this->load->view('ruidabei/import', $data);
    }

    /**
     * 导入处理
     *
     * @return mixed void
     */
    public function save()
    {
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

            $this->load->view('ruidabei/import', $data);
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

            /** 导入结果 */
            $start_time = microtime(true);

            foreach ($data as $key => $value) {

                if ($key < 1) {
                    continue;
                }

                $student_ticket = $value[0];
                $sql = "select * from {pre}exam_student_list where student_ticket={$student_ticket}";
                $ruidabei_student = $this->db->query($sql)->row_array();

                if (!$ruidabei_student) {
                    echo "未查询到当前用户数据，在第" . ($key+1) . "行:" . $value[1];
                    continue;
                }

                if ($ruidabei_student['student_name'] != $value[1]) {
                    echo "当前用户睿达杯准考证号与姓名不符，在第" . ($key+1) . "行" . $value[1];
                    continue;
                }

                /** 判断数据是否已存在数据库中 */
                $param = array();
                $param['exam_id'] =  $post['exam_id'];
                $param['student_id'] = $ruidabei_student['uid'];
                $param['school_name'] = $value[2];
                $param['grade'] = $value[7];
                $param['subject'] = $value[6];

                $row = array();
                $row['exam_id'] = $post['exam_id'];
                $row['student_id'] = $ruidabei_student['uid'];
                $row['student_name'] = $value[1];
                $row['school_name'] = $value[2];
                $row['awards'] = $value[3];
                $row['score'] = $value[4];
                $row['ranks'] = $value[5];
                $row['subject'] = $value[6];
                $row['grade'] = $value[7];
                $row['create_time'] = time();


                $query_result = RuidabeiResultModel::get_one($param);
                
                if ($query_result) {
                    echo '<p style="color:red;">警告！本条数据已存在！将覆盖原有数据！在第' . ($key+1) . '行' . '</p><br/>';
                    $result = RuidabeiResultModel::update(array('id' => $query_result['id']), $row);
                } else {
                    $result = RuidabeiResultModel::add($row);
                }

                if ($result) {
                    echo '<p style="color:green;">第' . ($key+1). '行导入成功！</p><br/>';
                    flush();
                } else {
                    echo '<p style="color:red;">错误！第' . ($key+1) . '行导入失败！</p><br/>';
                    flush();
                }
            }

            $end_time = microtime(true);
            $execute_time = $end_time-$start_time;
            echo '导入成功！用时：' . $execute_time . 's';
        }
    }

}
