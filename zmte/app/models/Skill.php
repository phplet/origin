<?php 
/**
 * SkillModel
 * @fil Skill.php
 * @author  BJP
 * @final   2015-06-16
 */
class SkillModel
{
    /**
     * 读取一个信息
     * @param   int     $id = 0
     * @param   string  $item = NULL    要查询的字段列表(以英文逗号分隔开)
     *                                  为NULL表示查询所有字段
     * @return  mixed   若成功则返回某个字段的值或整行
     */
    public static function get_skill($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_skill WHERE id = {$id}
EOT;
        $row =  Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }
    
    /**
     * 读取列表
     * @param   int $subject_id = 0 学科ID,为0表示不限学科
     * @return  map<int, variant>   获取某个学科（或不限学科）的所有技能列表
     */
    public static function get_skills($subject_id = 0)
    {
        static $result = array();
        $hash = &$subject_id;

        if (isset($result[$hash])) return $result[$hash];

        $sql = 'SELECT * FROM rd_skill';
        $list = array();
        if ($subject_id)
        {
            $sql .= ' WHERE subject_id = ' . $subject_id;
        }
        $rows = Fn::db()->fetchAll($sql);
        foreach ($rows as $row)
        {
            $list[$row['id']] = $row;
        }
        $result[$hash] = $list;
        return $list;
    }
}
