<?php
/**
 * InterviewQuestionModel
 * @file    InterviewQuestion.php
 * @author  BJP
 * @final   2015-06-17
 */
class InterviewQuestionModel
{
    /**
     * 读取一个试题
     */
    public static function get_question($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_interview_question WHERE id = {$id}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }
}
