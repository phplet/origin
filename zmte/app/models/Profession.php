<?php
/**
 * 职业模块ProfessionModel
 * @file    Profession.php
 * @author  TCG
 * @final   2015-10-22
 */
class ProfessionModel
{
    /**
     * 获取符合条件的职业列表
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          string  profession_name     若有效按like%xxx%查询
     *          int     profession_emerging 若有效且为数字按=查询,若形如1,2,3则按IN查询
     * @return  array   list<map<string, variant>> 
     */
    public static function professionList($cond_param = null, $page = null, $perpage = null)
    {
        $where = array();
        $bind = array();
        if (is_array($cond_param))
        {
            if (Validate::isNotEmpty($cond_param['profession_name']))
            {
                $where[] = 'profession_name LIKE ?';
                $bind[]  = '%' . $cond_param['profession_name'] . '%';
            }
            
            if (Validate::isJoinedIntStr($cond_param['profession_emerging']))
            {
                $where[] = 'profession_emerging IN ('.$cond_param['profession_emerging'].')';
            }
        }
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $order_by = " profession_id DESC";
        
        return Fn::db()->fetchList('t_profession', '*', $where,
            $bind, $order_by, $page, $perpage);
    }
    
    /**
     * 获取符合条件的职业数量
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          string  profession_name     若有效按like%xxx%查询
     *          int     profession_emerging 若有效且为数字按=查询,若形如1,2,3则按IN查询
     * @return  int     $count
     */
    public static function professionListCount($cond_param = null)
    {
        $where = array();
        $bind = array();
        
        if (is_array($cond_param))
        {
            if (Validate::isNotEmpty($cond_param['profession_name']))
            {
                $where[] = 'profession_name LIKE ?';
                $bind[]  = '%' . $cond_param['profession_name'] . '%';
            }
        
            if (Validate::isJoinedIntStr($cond_param['profession_emerging']))
            {
                $where[] = 'profession_emerging IN ('.$cond_param['profession_emerging'].')';
            }
        }
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $sql = "SELECT COUNT(*) FROM t_profession";
        
        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        return Fn::db()->fetchOne($sql, $bind);
    }
    
    /**
     * 新增职业，成功返回职业id，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          string  profession_name     职业名称
     *          int     profession_emerging 新兴职业（0-否 1-是）
     *          string  profession_explain  职业说明
     * @return  mixed   
     */
    public static function addProfession($param)
    {
        $param = Func::param_copy($param, 'profession_name', 
            'profession_emerging', 'profession_explain');
        if (!Validate::isNotEmpty($param['profession_name']))
        {
            throw new Exception('职业名称不能为空！');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT profession_id FROM t_profession
                WHERE profession_name = ?";
        if ($db->fetchOne($sql, array($param['profession_name'])))
        {
            throw new Exception('职业名称已存在，不能重复添加！');
        }
        
        $param['profession_flag'] = 1;
        $param['profession_addtime'] = time();
        $param['profession_updatetime'] = time();
        
        $db->insert('t_profession', $param);
        
        return $db->lastInsertId('t_profession', 'profession_id');
    }
    
    /**
     * 编辑职业，成功返回非0，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     profession_id       职业id
     *          string  profession_name     职业名称
     *          int     profession_emerging 新兴职业（0-否 1-是）
     *          string  profession_explain  职业说明
     * @return  mixed
     */
    public static function setProfession($param)
    {
        $param = Func::param_copy($param, 'profession_id', 'profession_name',
            'profession_emerging', 'profession_explain');
        if (!Validate::isNotEmpty($param['profession_name']))
        {
            throw new Exception('职业名称不能为空！');
        }
    
        $db = Fn::db();
    
        $sql = "SELECT profession_id FROM t_profession
                WHERE profession_name = ? AND profession_id <> ?";
        if ($db->fetchOne($sql, array($param['profession_name'], 
            $param['profession_id'])))
        {
            throw new Exception('职业名称已存在！');
        }
    
        $param['profession_updatetime'] = time();
    
        return $db->update('t_profession', $param, 'profession_id = ?', 
            array($param['profession_id']));
    }
    
    /**
     * 设置职业状态
     * @param   int     $profession_id
     * @param   int     $profession_flag
     * @return  bool    true|false
     */
    public static function setProfessionFlag($profession_id, $profession_flag)
    {
        if (!$profession_id || !in_array($profession_flag, array(-1, 0, 1)))
        {
            return false;
        }
        
        return Fn::db()->update('t_profession', 
            array('profession_flag' => $profession_flag), 
            'profession_id = ?', 
            array($profession_id));
    }
    
    /**
     * 删除职业
     * @param   int     $profession_id_str
     * @return  bool    true|false
     */
    public static function removeProfession($profession_id_str)
    {
        if (!Validate::isJoinedIntStr($profession_id_str))
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = "DELETE FROM t_profession WHERE profession_flag = '-1' 
                AND profession_id IN ($profession_id_str)";
        $db->exec($sql);
        
        $sql = "UPDATE t_profession SET profession_flag = '-1'
                WHERE profession_id IN ($profession_id_str)";
        $db->exec($sql);
        
        return true;
    }
    
    /**
     * 获取$profession_id所指定的职业信息，返回结果集
     * @param   mixed   $profession_id
     * @param   array   map<string, variant>
     */
    public static function professionInfo($profession_id)
    {
        if (!$profession_id)
        {
            return array();
        }
        
        if (Validate::isInt($profession_id))
        {
            $sql = "SELECT * FROM t_profession
                    WHERE profession_id = ?";
            return Fn::db()->fetchRow($sql, array($profession_id));
        }
        else if (Validate::isJoinedIntStr($profession_id))
        {
            $sql = "SELECT * FROM t_profession
                    WHERE profession_id IN ($profession_id)";
            return Fn::db()->fetchAssoc($sql);
        }
        else
        {
            return array();
        }
    }
}