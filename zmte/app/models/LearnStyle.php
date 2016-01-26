<?php
/**
 * 学习风格模块LearnStyleModel
 * @file    LearnStyle.php
 * @author  TCG
 * @final   2015-10-23 10:10
 */
class LearnStyleModel
{
    /**
     * 获取符合条件的职业列表
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          string  knowledge_name     若有效按like%xxx%查询
     *          int|string learnstyle_knowledgeid 若有效且为数字按=查询,若形如1,2,3则按IN查询
     * @return  array   list<map<string, variant>> 
     */
    public static function learnStyleList($cond_param = null, $page = null, $perpage = null)
    {
        $where = array();
        $bind = array();
        if (is_array($cond_param))
        {
            if (Validate::isNotEmpty($cond_param['knowledge_name']))
            {
                $where[] = 'knowledge_name LIKE ?';
                $bind[]  = '%' . $cond_param['knowledge_name'] . '%';
            }
            
            if (Validate::isInt($cond_param['learnstyle_knowledgeid']))
            {
                $where[] = 'learnstyle_knowledgeid = ?';
                $bind[]  = $cond_param['learnstyle_knowledgeid'];
            }
            else if (Validate::isJoinedIntStr($cond_param['learnstyle_knowledgeid']))
            {
                $where[] = 'learnstyle_knowledgeid IN ('.$cond_param['learnstyle_knowledgeid'].')';
            }
        } 
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $order_by = " learnstyle_id DESC";
        
        return Fn::db()->fetchList('v_learn_style', '*', $where,
            $bind, $order_by, $page, $perpage);
    }
    
    /**
     * 获取符合条件的职业数量
     * 若$cond_param为null，则查询所有
     * 若$cond_param为string，则为SQL WHERE
     * 若$cond_param为array，则为map<string, variant>，参数如下：
     *          string  knowledge_name     若有效按like%xxx%查询
     *          int|string learnstyle_knowledgeid 若有效且为数字按=查询,若形如1,2,3则按IN查询
     * @return  int     $count
     */
    public static function learnStyleListCount($cond_param = null)
    {
        $where = array();
        $bind = array();
        if (is_array($cond_param))
        {
            if (Validate::isNotEmpty($cond_param['knowledge_name']))
            {
                $where[] = 'knowledge_name LIKE ?';
                $bind[]  = '%' . $cond_param['knowledge_name'] . '%';
            }
            
            if (Validate::isInt($cond_param['learnstyle_knowledgeid']))
            {
                $where[] = 'learnstyle_knowledgeid = ?';
                $bind[]  = $cond_param['learnstyle_knowledgeid'];
            }
            else if (Validate::isJoinedIntStr($cond_param['learnstyle_knowledgeid']))
            {
                $where[] = 'learnstyle_knowledgeid IN ('.$cond_param['learnstyle_knowledgeid'].')';
            }
        } 
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $sql = "SELECT COUNT(*) FROM v_learn_style";
        
        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        return Fn::db()->fetchOne($sql, $bind);
    }
    
    /**
     * 新增学习风格，成功返回学习风格id，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     learnstyle_knowledgeid  知识点id
     *          string  learnstyle_explain      说明
     * @return  mixed   
     */
    public static function addLearnStyle($param)
    {
        $param = Func::param_copy($param, 'learnstyle_knowledgeid', 
            'learnstyle_explain');
        if (!Validate::isInt($param['learnstyle_knowledgeid'])
            || $param['learnstyle_knowledgeid'] <= 0)
        {
            throw new Exception('内化过程不能为空！');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT learnstyle_id FROM t_learn_style
                WHERE learnstyle_knowledgeid = ?";
        if ($db->fetchOne($sql, array($param['learnstyle_knowledgeid'])))
        {
            throw new Exception('内化过程已存在，不能重复添加！');
        }
        
        $param['learnstyle_flag'] = 1;
        $param['learnstyle_addtime'] = time();
        $param['learnstyle_updatetime'] = time();
        
        $db->insert('t_learn_style', $param);
        
        return $db->lastInsertId('t_learn_style', 'learnstyle_id');
    }
    
    /**
     * 编辑学习风格，成功返回非0，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     learnstyle_id       职业id
     *          string  learnstyle_explain  说明
     * @return  mixed
     */
    public static function setLearnStyle($param)
    {
        $param = Func::param_copy($param, 'learnstyle_id', 'learnstyle_explain');
        if (!Validate::isInt($param['learnstyle_id'])
            || $param['learnstyle_id'] <= 0)
        {
            return false;
        }
    
        $param['learnstyle_updatetime'] = time();
    
        return Fn::db()->update('t_learn_style', $param, 'learnstyle_id = ?', 
            array($param['learnstyle_id']));
    }
    
    /**
     * 设置职业状态
     * @param   int     $learnstyle_id
     * @param   int     $learnstyle_flag
     * @return  bool    true|false
     */
    public static function setLearnStyleFlag($learnstyle_id, $learnstyle_flag)
    {
        if (!$learnstyle_id 
            || !in_array($learnstyle_flag, array(-1, 0, 1)))
        {
            return false;
        }
        
        return Fn::db()->update('t_learn_style', 
            array('learnstyle_flag' => $learnstyle_flag), 
            'learnstyle_id = ?', 
            array($learnstyle_id));
    }
    
    /**
     * 删除职业
     * @param   int     $profession_id_str
     * @return  bool    true|false
     */
    public static function removeLearnStyle($learnstyle_id_str)
    {
        if (!Validate::isJoinedIntStr($learnstyle_id_str))
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = "DELETE FROM t_learn_style WHERE learnstyle_flag = '-1' 
                AND learnstyle_id IN ($learnstyle_id_str)";
        $db->exec($sql);
        
        $sql = "UPDATE t_learn_style SET learnstyle_flag = '-1'
                WHERE learnstyle_id IN ($learnstyle_id_str)";
        $db->exec($sql);
        
        $sql = "DELETE FROM t_learn_style_attribute 
                WHERE lsattr_learnstyleid NOT IN 
                (
                    SELECT learnstyle_id FROM t_learn_style
                )";
        $db->exec($sql);
        
        return true;
    }
    
    /**
     * 查询学习风格-内化过程信息，成功返回结果集，失败返回NULL
     * @param   int     $learnstyle_id
     * @return  array   map<string, variant>
     */
    public static function learnStyleInfo($learnstyle_id)
    {
        if (!Validate::isInt($learnstyle_id)
            || $learnstyle_id <= 0)
        {
            return false;
        }
        
        $sql = "SELECT * FROM v_learn_style 
                WHERE learnstyle_id = ?";
        return Fn::db()->fetchRow($sql, array($learnstyle_id));
    }
    
    /**
     * 获取学习风格知识点，返回查询结果集
     * @return  mixed
     */
    public function learnStyleKnowledgeid()
    {
        $sql = "SELECT learnstyle_knowledgeid FROM t_learn_style";
        return Fn::db()->fetchCol($sql);
    }
    
    /**
     * 查询学习风格属性信息，成功返回结果集，失败返回NULL
     * @param   int     $lsattr_learnstyleid
     * @param   int     $lsattr_value
     * @param   array   map<string, variant>
     */
    public static function learnStyleAttributeInfo($lsattr_learnstyleid, $lsattr_value)
    {
        if (!Validate::isInt($lsattr_learnstyleid)
            || $lsattr_learnstyleid <= 0
            || !Validate::isInt($lsattr_value)
            || !in_array($lsattr_value, array(1, 2)))
        {
            return array();
        }
        
        $sql = "SELECT * FROM t_learn_style_attribute
                WHERE lsattr_learnstyleid = ? AND lsattr_value = ?";
        return Fn::db()->fetchRow($sql, array($lsattr_learnstyleid, $lsattr_value));
    }
    
    /**
     * 新增学习风格属性，成功返回非0，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     lsattr_learnstyleid 学习风格id
     *          in      lsattr_value        学习风格属性id(1-正向 2-负向)
     *          string  lsattr_name     学习风格
     *          string  lsattr_define   学习风格定义
     *          string  lsattr_advice   学习风格建议
     * @return  bool    true|false
     * 
     */
    public static function addLearnStyleAttribute($param)
    {
        $param = Func::param_copy($param, 'lsattr_learnstyleid',
            'lsattr_value', 'lsattr_name', 'lsattr_define', 'lsattr_advice');
        if (!Validate::isInt($param['lsattr_learnstyleid'])
            || $param['lsattr_learnstyleid'] <= 0
            || !in_array($param['lsattr_value'], array(1, 2)))
        {
            return false;
        }
        
        if (!Validate::isNotEmpty($param['lsattr_name']))
        {
            throw new Exception('学习风格不能为空！');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT lsattr_name FROM t_learn_style_attribute
                WHERE lsattr_name = ?";
        if ($db->fetchOne($sql, array($param['lsattr_name'])))
        {
            throw new Exception('学习风格已经存在！');
        }
        
        $sql = "SELECT lsattr_value FROM t_learn_style_attribute
                WHERE lsattr_learnstyleid = ? AND lsattr_value = ?";
        if ($db->fetchOne($sql, array($param['lsattr_learnstyleid'], 
                $param['lsattr_value'])))
        {
            throw new Exception('学习风格属性已经存在！');
        }
        
        $param['lsattr_addtime'] = time();
        $param['lsattr_updatetime'] = time();
        
        return $db->insert('t_learn_style_attribute', $param);
    }
    
    /**
     * 编辑学习风格属性，成功返回非0，失败返回0
     * $param为array，则为map<string, variant>，参数如下：
     *          int     lsattr_learnstyleid 学习风格id
     *          int     lsattr_value        学习风格属性id(1-正向 2-负向)
     *          string  lsattr_name     学习风格
     *          string  lsattr_define   学习风格定义
     *          string  lsattr_advice   学习风格建议
     * @return  bool    true|false
     *
     */
    public static function setLearnStyleAttribute($param)
    {
        $param = Func::param_copy($param, 'lsattr_learnstyleid',
            'lsattr_value', 'lsattr_name', 'lsattr_define', 'lsattr_advice');
    
        if (!Validate::isInt($param['lsattr_learnstyleid'])
            || $param['lsattr_learnstyleid'] <= 0
            || !in_array($param['lsattr_value'], array(1, 2)))
        {
            return false;
        }
        
        if (!Validate::isNotEmpty($param['lsattr_name']))
        {
            throw new Exception('学习风格不能为空！');
        }
    
        $db = Fn::db();
    
        $sql = "SELECT lsattr_name FROM t_learn_style_attribute
                WHERE lsattr_name = ? AND lsattr_learnstyleid <> ? 
                AND lsattr_value <> ?";
        if ($db->fetchOne($sql, array($param['lsattr_name'], 
            $param['lsattr_learnstyleid'], $param['lsattr_value'])))
        {
            throw new Exception('学习风格已经存在！');
        }
        
        $bind = array(
            $param['lsattr_learnstyleid'], 
            $param['lsattr_value']
        );
        unset($param['lsattr_learnstyleid']);
        unset($param['lsattr_value']);
    
        $param['lsattr_updatetime'] = time();
    
        return $db->update('t_learn_style_attribute', $param,
            'lsattr_learnstyleid = ? AND lsattr_value = ?',$bind);
    }
    
    /**
     * 获取符合$learnstyle_id条件的学习风格属性，返回查询结果集
     * @param   int     $learnstyle_id
     * @return  array   list<map<string, variant>>
     */
    public static function learnStyleAttributeList($learnstyle_id)
    {
        if (!$learnstyle_id)
        {
            return false;
        }
        
        $where[] = 'lsattr_learnstyleid = ?';
        $bind[] = $learnstyle_id;
        
        return Fn::db()->fetchList('t_learn_style_attribute', '*', $where,
            $bind, null, null, null);
    }
    
    /**
     * 删除学习风格属性信息，成功返回非0，失败返回0
     * @param   int     $lsattr_learnstyleid
     * @param   int     $lsattr_value
     * @return  bool    true|false
     */
    public static function removeLearnStyleAttribute($lsattr_learnstyleid, $lsattr_value)
    {
        if (!Validate::isInt($lsattr_learnstyleid)
            || $lsattr_learnstyleid <= 0
            || !in_array($lsattr_value, array(1, 2)))
        {
            return false;
        }
        
        $where  = 'lsattr_learnstyleid = ? AND lsattr_value = ?';
        $where_bind[] = $lsattr_learnstyleid;
        $where_bind[] = $lsattr_value;
        
        return Fn::db()->delete('t_learn_style_attribute', 
            $where, $where_bind);
    }
}