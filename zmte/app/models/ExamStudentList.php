<?php
/**
 * 考试期次--学生对应表 ExamStudentListModel
 * @file    ExamStudentList.php
 * @author  BJP
 * @final   2015-06-26
 */
class ExamStudentListModel
{
    /**
     *插入数据
     *
     *@param   array  考试期次,学生信息,uid($exam_student_array)
     *@return     true成功,flase 失败
     */
    public static function insert($exam_student_array)
    {
        $bOk = false;
        if(is_array($exam_student_array) && !empty($exam_student_array))
        {
            $db = Fn::db();
            if ($db->beginTransaction())
            {
                foreach ($exam_student_array as $v)
                {
                    $db->insert('rd_exam_student_list', $v);
                }
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
            }
        }
        return $bOk;
    }


    /**
     *更新数据
     *
     *@param    array  考试期次,学生信息,uid($exam_student_array)
     *@return          true成功,flase 失败
     */
    public static function update($exam_student_array = array())
    {
        if (!is_array($exam_student_array))
        {
            return false;
        }
        
        $exam_pid = $exam_student_array[0]["exam_pid"];
        
        $sql = <<<EOT
SELECT exam_pid, student_name, student_ticket, uid FROM rd_exam_student_list
WHERE exam_pid = {$exam_pid}
EOT;
        $db = Fn::db();
        $rows = $db->fetchAll($sql);
        $exam_student_list = array();
        foreach ($rows as $item)
        {
            $k = $item['student_name'] . "," . $item['student_ticket'];
            $exam_student_list[$k] = $item['uid'];
        }
        
        foreach ($exam_student_array as $key => &$val)
        {
            $k = $val['student_name'] . "," . $val['student_ticket'];
            if (isset($exam_student_list[$k]))
            {
                $val['uid'] = $exam_student_list[$k];
                continue;
            }
        }
        
        $db->delete('rd_exam_student_list', "exam_pid = $exam_pid");
        $bOk = false;
        if ($db->beginTransaction())
        {
            foreach ($exam_student_array as $v)
            {
                $db->insert('rd_exam_student_list', $v);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }

    /*
    *判断数据是否已在数据库中
    *
    *@param   int 考试期次 $exam_id
    *@return     true是,flase否
    */
    public static function is_exist($exam_id)
    {
        if(is_int($exam_id) && !empty($exam_id))
        {
            $sql = <<<EOT
SELECT exam_pid FROM rd_exam_student_list WHERE exam_pid = {$exam_id}
EOT;
            $row = Fn::db()->fetchRow($sql);
            if ($row)
            {
                return true;
            }
        }
        return false;
    }

    /*
    *数据查找
    *
    *@param   int 考试期次 $exam_id
    *@return     true是,flase否
    */
    public static function select_exam_id($exam_id)
    {
        if(is_int($exam_id) && !empty($exam_id))
        {
            $sql = <<<EOT
SELECT * FROM rd_exam_student_list 
WHERE exam_pid = {$exam_id} ORDER BY student_ticket
EOT;
            $rows = Fn::db()->fetchAll($sql);
            return $rows;
        }
        else
        {
            return false;
        }
    }
}
