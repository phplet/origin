<?php
/**
 * 知识点模块KnowledgeModel
 * @file    Knowledge.php
 * @author  BJP
 * @final 2015-06-17
 */
class KnowledgeModel
{
    /**
     * 判断同学科同级知识点是否重名
     * @param   int     $pid            必须  知识点父ID
     * @param   int     $subject_id     必须 知识点名称
     * @param   string  $knowledge_name 必须 知识点名称
     * @return  int     三者查询数量
     */
    public static function get_knowledge_psk($pid = 0, $subject_id = 0,
        $knowledge_name = '', $id = 0)
    {
        $sql = <<<EOT
SELECT COUNT(*) AS 'count' FROM rd_knowledge
WHERE pid = ? AND subject_id = ? AND knowledge_name = ?
EOT;
        $bind = array($pid, $subject_id, $knowledge_name);
        if ($id > 0)
        {
            $sql .= " AND id <> ?";
            $bind[] = $id;
        }
        $row = Fn::db()->fetchRow($sql, $bind);
        return $row['count'];
    }

    /**
     * 按ID读取知识点
     * @param   int     知识点ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_knowledge($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }        
        static $result = array();
        $hash = $id. '-' . $item;
        if (isset($result[$hash]))
        {
            return $result[$hash];
        }

        $sql = <<<EOT
SELECT {$item} FROM rd_knowledge WHERE id = {$id}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            $result[$hash] = $row[$item];
        }
        else
        {
            $result[$hash] = $row;
        }
        return $result[$hash];
    }

    /**
     * 按学科ID，父ID，读取知识点列表
     * @param   int       学科ID
     * @param   int       上级ID
     * @param   boolean   是否计算关联试题数量
     * @return  array
     */
    public static function get_knowledge_list($subject_id=0, $pid = 0, 
        $relate_num = TRUE)
    {
        static $result = array();
        $hash = $subject_id . '-'. $pid .'-'. $relate_num;
        if (isset($result[$hash]))
        {
            return $result[$hash];
        }
        
        $list = array();
        
        $sql = "SELECT * FROM rd_knowledge WHERE pid = $pid";
        
        if (false !== strpos($subject_id, ','))
        {
            $sql .= " AND subject_id IN ($subject_id)";
        }
        else if ($subject_id > 0)
        {
            $sql .= " AND subject_id = $subject_id";
        }
        
        $rows = Fn::db()->fetchAll($sql);
        foreach ($rows as $row)
        {
            $list[$row['id']] = $row;
        }

        if ($relate_num)
        {
            foreach($list as &$val)
            {
                $val = self::get_konwledge_ques_num($val);
            }
        }
        $result[$hash] = $list;
        return $result[$hash];
    }
    
    /**
     * 按学科ID，父ID的二级知识点列表
     * @param   int       学科ID
     * @param   int       上级ID
     * @return  array
     */
    public static function get_knowledge_children_list($subject_id = 0, $pid = 0)
    {
        if (!$subject_id || (!Validate::isInt($subject_id) 
            && !Validate::isJoinedIntStr($subject_id)))
        {
            return false;
        }
        
        $sql = "SELECT * FROM rd_knowledge WHERE ";
    
        if (Validate::isInt($subject_id))
        {
            $sql .= " subject_id = $subject_id";
        }
        else if (Validate::isJoinedIntStr($subject_id))
        {
            $sql .= " subject_id IN ($subject_id)";
        }
        
        if ($pid > 0)
        {
            $sql .= " AND pid = $pid";
        }
        else 
        {
            $sql .= " AND pid > 0";
        }
        
        return Fn::db()->fetchAssoc($sql);
    }

    /**
     * 计算知识点关联的试题数量，返回知识点信息数组
     * @param   mixed      知识点数组/知识点id
     * @return  array
     */
    public static function get_konwledge_ques_num($knowledge = array())
    {
        if (!is_array($knowledge) && $knowledge) 
        {
            $knowledge = self::get_knowledge(intval($knowledge));
        }
        if (empty($knowledge))
        {
            return array();
        }

        $id = $knowledge['id'];
        $pid = $knowledge['pid'];

        $where = array();
        $where[] = "q.ques_id = rk.ques_id";
        $where[] = "q.is_delete <> 1";
        $where[] = "q.parent_id = 0";
        
        // 如果是一级知识点，获取下级知识点id
        if ($pid == 0)
        {
            $ids = self::get_knowledge_list(0, $id, 0);
            $ids = array_keys($ids);
            $knowledge['children'] = count($ids);
        }

        //--------------------------------------------//
        // 统计试题数
        //--------------------------------------------//
        if ($pid || empty($ids))
        {
            $where[] = "rk.knowledge_id = $id";
        }
        else
        {
            if (count($ids) == 1)
            {
                $where[] = "rk.knowledge_id = $ids[0]";
            }
            else
            {
                $where[] = "rk.knowledge_id IN (" . my_implode($ids) . ")";
            }
        }
        $sql = "SELECT COUNT(DISTINCT q.ques_id) nums FROM rd_question q, rd_relate_knowledge rk 
                WHERE ".implode(' AND ', $where) . ' AND rk.is_child = 0';
        
        
        $row = Fn::db()->fetchRow($sql);
        $knowledge['ques_num'] = (int)$row['nums'];

        //--------------------------------------------//
        // 统计关联试题数
        // = 无分组试题数 + 分组数量
        //--------------------------------------------//
        
        // 独立试题数
        $sql = "SELECT COUNT(DISTINCT q.ques_id) nums FROM rd_question q, rd_relate_knowledge rk 
                WHERE " . implode(' AND ', $where) . " AND q.group_id = 0 AND rk.is_child = 0";
         $row = Fn::db()->fetchRow($sql);
        $knowledge['relate_ques_num'] = (int)$row['nums'];
        
        // 分组数
        $sql = "SELECT COUNT(distinct q.group_id) nums FROM rd_question q,rd_relate_knowledge rk 
                WHERE " . implode(' AND ', $where) . " AND q.group_id > 0 AND rk.is_child = 0";
        $row = Fn::db()->fetchRow($sql);
        $knowledge['relate_ques_num'] += (int)$row['nums'];
        return $knowledge;
    }
}
