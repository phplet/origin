<?php
/**
 * 职业兴趣/职业能力倾向模块ProfessionRelatedModel
 * @file    ProfessionRelated.php
 * @author  TCG
 * @final   2015-10-22
 */
class ProfessionRelatedModel
{
    /**
     * 获取符合条件的职业兴趣/职业能力倾向列表
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          int     pr_subjectid    若有效且为数字按=查询
     *          string  knowledge_name  若有效按like%xxx%查询
     *          int     pr_knowledgeid  若有效且为数字按=查询
     * @return  array   list<map<string, variant>> 
     */
    public static function professionRelatedList($cond_param = null, $page, $perpage = null)
    {
        $where = array();
        $bind = array();
        if (is_array($cond_param))
        {
            if (Validate::isInt($cond_param['pr_subjectid'])
                && $cond_param['pr_subjectid'] > 0)
            {
                $where[] = 'pr_subjectid = ?';
                $bind[]  = $cond_param['pr_subjectid'];
            }
            
            if (Validate::isInt($cond_param['pr_knowledgeid'])
                && $cond_param['pr_knowledgeid'] > 0)
            {
                $where[] = 'pr_knowledgeid = ?';
                $bind[]  = $cond_param['pr_knowledgeid'];
            }
            
            if (Validate::isNotEmpty($cond_param['knowledge_name']))
            {
                $where[] = 'knowledge_name LIKE ?';
                $bind[]  = '%' . $cond_param['knowledge_name'] . '%';
            }
        }
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $order_by = " pr_id DESC";
        
        return Fn::db()->fetchList('v_profession_related', '*', $where,
            $bind, $order_by, $page, $perpage);
    }
    
    /**
     * 获取符合条件的职业兴趣/职业能力倾向数量
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          int     pr_subjectid    若有效且为数字按=查询
     *          string  knowledge_name  若有效按like%xxx%查询
     *          int     pr_knowledgeid  若有效且为数字按=查询
     * @return  int     $count
     */
    public static function professionRelatedListCount($cond_param = null)
    {
        $where = array();
        $bind = array();
        
        if (is_array($cond_param))
        {
            if (Validate::isInt($cond_param['pr_subjectid'])
                && $cond_param['pr_subjectid'] > 0)
            {
                $where[] = 'pr_subjectid = ?';
                $bind[]  = $cond_param['pr_subjectid'];
            }
            
            if (Validate::isInt($cond_param['pr_knowledgeid'])
                && $cond_param['pr_knowledgeid'] > 0)
            {
                $where[] = 'pr_knowledgeid = ?';
                $bind[]  = $cond_param['pr_knowledgeid'];
            }
            
            if (Validate::isNotEmpty($cond_param['knowledge_name']))
            {
                $where[] = 'knowledge_name LIKE ?';
                $bind[]  = '%' . $cond_param['knowledge_name'] . '%';
            }
        }
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $sql = "SELECT COUNT(*) FROM v_profession_related";
        
        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        return Fn::db()->fetchOne($sql, $bind);
    }
    
    /**
     * 新增职业兴趣/职业能力倾向，成功返回职业兴趣/职业能力倾向id，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     pr_subjectid    学科id
     *          int     pr_knowledgeid  知识点id
     *          string  pr_explain      说明
     *          string  pr_professionid 关联职业（JSON字符串）
     * @return  mixed   
     */
    public static function addProfessionRelated($param)
    {
        $param = Func::param_copy($param, 'pr_subjectid', 
            'pr_knowledgeid', 'pr_explain', 'pr_professionid');

        if (!Validate::isInt($param['pr_subjectid'])
            || $param['pr_subjectid'] <= 0)
        {
            return false;
        }

        if (!Validate::isInt($param['pr_knowledgeid'])
            || $param['pr_knowledgeid'] <= 0)
        {
            throw new Exception('不能为空');
        }
        
        if (empty($param['pr_professionid']))
        {
            throw new Exception('关联职业不能为空');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT pr_id FROM t_profession_related
                WHERE pr_knowledgeid = ?";
        if ($db->fetchOne($sql, array($param['pr_knowledgeid'])))
        {
            throw new Exception('已存在！');
        }
        
        $param['pr_flag'] = 1;
        $param['pr_professionid'] = json_encode($param['pr_professionid']);
        $param['pr_addtime'] = time();
        $param['pr_updatetime'] = time();
        
        $db->insert('t_profession_related', $param);
        
        return $db->lastInsertId('t_profession_related', 'pr_id');
    }
    
    /**
     * 编辑职业兴趣/职业能力倾向，成功返回非0，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     pr_id           职业兴趣/职业能力倾向id
     *          int     pr_subjectid    学科id
     *          int     pr_knowledgeid  知识点id
     *          string  pr_explain      说明
     *          string  pr_professionid 关联职业（JSON字符串）
     * @return  mixed
     */
    public static function setProfessionRelated($param)
    {
        $param = Func::param_copy($param, 'pr_id',
            'pr_explain', 'pr_professionid');
        
        if (empty($param['pr_professionid']))
        {
            throw new Exception('关联职业不能为空');
        }
    
        $db = Fn::db();
    
        $param['pr_professionid'] = json_encode($param['pr_professionid']);
        $param['pr_updatetime'] = time();
    
        return $db->update('t_profession_related', $param, 'pr_id = ?', 
            array($param['pr_id']));
    }
    
    /**
     * 设置职业兴趣/职业能力倾向状态
     * @param   int     $pr_id
     * @param   int     $pr_flag
     * @return  bool    true|false
     */
    public static function setProfessionRelatedFlag($pr_id, $pr_flag)
    {
        if (!$pr_id || !in_array($pr_flag, array(-1, 0, 1)))
        {
            return false;
        }
        
        return Fn::db()->update('t_profession_related', 
            array('pr_flag' => $pr_flag), 
            'pr_id = ?', 
            array($pr_id));
    }
    
    /**
     * 删除职业兴趣/职业能力倾向
     * @param   int     $pr_id_str
     * @return  bool    true|false
     */
    public static function removeProfessionRelated($pr_id_str)
    {
        if (!Validate::isJoinedIntStr($pr_id_str))
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = "DELETE FROM t_profession_related WHERE pr_flag = '-1' 
                AND pr_id IN ($pr_id_str)";
        $db->exec($sql);
        
        $sql = "UPDATE t_profession_related SET pr_flag = '-1'
                WHERE pr_id IN ($pr_id_str)";
        $db->exec($sql);
        
        return true;
    }
    
    /**
     * 获取$pr_id所指定的职业兴趣/职业能力倾向信息，返回结果集
     * @param   int     $pr_id
     * @param   array   map<string, variant>
     */
    public static function professionRelatedInfo($pr_id)
    {
        if (!$pr_id)
        {
            return array();
        }
        
        $sql = "SELECT * FROM v_profession_related
                WHERE pr_id = ?";
        return Fn::db()->fetchRow($sql, array($pr_id));
    }
    
    /**
     * 获取$pr_subjectid所指定的职业兴趣/能力倾向知识点，返回查询结果集
     * @param   int     $pr_subjectid
     * @return  mixed
     */
    public function professionRelatedKnowledgeid($pr_subjectid)
    {
        if (!Validate::isInt($pr_subjectid)
            || $pr_subjectid <= 0)
        {
            return false;
        }
    
        $sql = "SELECT pr_knowledgeid FROM t_profession_related
                WHERE pr_subjectid = $pr_subjectid";
        return Fn::db()->fetchCol($sql);
    }
}