<?php
/**
 * 评分标准关联考试期次模型 EvaluationStandardExamModel
 * @file    EvaluationStandardExam.php
 * @author  BJP
 * @final   2015-07-03
 */
class EvaluationStandardExamModel
{
    /**
     * 添加一条
     *
     * @param int $standard_id 评分标准ID
     * @param mixed $exam_ids 考试期次ID
     * @return boolean 成功返回true，失败返回false
     */
    public static function add($standard_id, $exam_ids)
    {
        $data = array();
        $db = Fn::db();
        if (is_array($exam_ids))
        {
            $bOk = false;
            if ($db->beginTransaction())
            {
                foreach ($exam_ids as $key => $value)
                {
                    $db->insert('rd_evaluation_standard_exam', 
                        array('standard_id' => $standard_id, 
                        'exam_id' => $value));
                }
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
            }
            return $bOk;
        }
        else
        {
            $data['standard_id'] = $standard_id;
            $data['exam_id'] = $exam_ids;
            $v = $db->insert('rd_evaluation_standard_exam', $data);
            return $v > 0;
        }
    }

    /**
     * 删除一条
     *
     * @param int $standard_id 评分标准ID
     * @param mixed $exam_ids 考试期次ID
     * @return boolean 成功返回true，失败返回false
     */
    public static function delete($standard_id, $exam_ids = NULL)
    {
        $db = Fn::db();
        if (is_array($exam_ids))
        {
            $v = $db->delete('rd_evaluation_standard_exam', 
                "standard_id = $standard_id AND exam_id IN ("
                . implode(',', $exam_ids) . ')');
            return $v > 0;
        } 
        else if (is_null($exam_ids))
        {
            $v = $db->delete('rd_evaluation_standard_exam', 
                "standard_id = $standard_id");
            return $v > 0;
        }
        else
        {
            $v = $db->delete('rd_evaluation_standard_exam', 
                "standard_id = $standard_id AND exam_id = $exam_ids");
            return $v > 0;
        }   
    }

    /**
     * 根据标准ID获取考试期次
     *
     * @param int $standard_id 评分标准ID
     * @return mixed 成功返回结果集，失败false
     */
    public static function get_exam_by_standard($standard_id)
    {
        $sql = <<<EOT
SELECT * FROM rd_evaluation_standard_exam WHERE standard_id = {$standard_id}
EOT;
        return Fn::db()->fetchAll($sql);
    }

    /**
     * 根据考试期次ID获取评分标准
     *
     * @param int $standard_id 考试期次ID
     * @return mixed 成功返回结果集，失败false
     */
    public static function get_standard_by_exam($exam_id)
    {
        $sql = <<<EOT
SELECT * FROM rd_evaluation_standard_exam WHERE exam_id = {$exam_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }
}
