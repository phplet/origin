<?php if ( ! defined('BASEPATH')) exit();
/**
 * @filename	KyxmController.php
 * @description 科研项目控制器
 * @version     2.0
 * @author      XChinux@163.com
 * @final       2010-05-21
 */
class Kyxm extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
        
    public function report($exam_id)//{{{
    {
        $sql1 = <<<EOT
SELECT count(1) as 'count' 
from rd_student rs,rd_exam_place_student reps , rd_exam_place rep 
where rep.exam_pid='{$exam_id}' and rep.place_id=reps.place_id and rs.uid=reps.uid
EOT;
        $sql2 = <<<EOT
SELECT rs.exam_ticket,CONCAT(rs.last_name,rs.first_name) as xname 
from rd_student rs,rd_exam_place_student reps , rd_exam_place rep 
where rep.exam_pid='{$exam_id}' and rep.place_id=reps.place_id and rs.uid=reps.uid
EOT;
        $result1 = Fn::db()->fetchRow($sql1);

        // 读取数据
        if ($result1['count']>0)
        {
            $sql3 ="SELECT exam_name FROM rd_exam WHERE exam_id={$exam_id}";
            $row = Fn::db()->fetchRow($sql3);
            $title=$row['exam_name'].'.xls';
            $data =array();

             $data['list'] = $this->db->query($sql2)->result_array();
             $header = array('准考证号', '姓名');
             $excel_model = new ExcelModel();
             $excel_model->addHeader($header);
             $excel_model->addBody($data['list']);
             $excel_model->download($title);
        }
        else
        {
            echo '暂无学生，请先添加学生';
        }
    }//}}}
}
