<?php
/**
 * SchoolModel
 * @file    School.php
 * @author  BJP
 * @final   2015-06-19
 */
class SchoolModel
{
    /**
     * 查询学校信息,若失败,返回NULL
     * @param   int     $schooid            学校ID
     * @param   string  $field = NULL       表示查询字段
     * @return  array                       map<string, variant>
     */
    public static function schoolInfo($school_id, /*string*/ $field = NULL)
    {
        if (!is_numeric($school_id))
        {
            return NULL;
        }
        if (is_null($field))
        {
            $field = '*';
        }
        $sql = <<<EOT
SELECT {$field} FROM v_school WHERE school_id = {$school_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }
    
    // 按条件读取学校列表
    public static function get_schools($where = array())
    {
        $sql = "SELECT * FROM rd_school";
        PDO_DB::build_where($where, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= " WHERE $where_sql";
        }
        $rows = Fn::db()->fetchAll($sql, $bind);
        return $rows;
    }

    public static function get_v_schools($where = array())
    {
        $sql = "SELECT * FROM v_school";
        PDO_DB::build_where($where, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= " WHERE $where_sql";
        }
        $rows = Fn::db()->fetchAll($sql, $bind);
    	return $rows;
    }
    
    /*
     * 搜索学校
     */
    public static function search_school($where = array())
    {
        $bind = array();
    	$sql = "SELECT school_id, school_name FROM rd_school";
    	$sql_str = array();
    	if (!empty($where['area_id']))
    	{
    	    $sql_str[] = " area = " . $where['area_id'];
        }

       	if (!empty($where['city_id']))
    	{
            $sql_str[] = " city = " . $where['city_id'];
        }

        if (!empty($where['school_property']))
    	{
            $sql_str[] = " school_property = " . $where['school_property'];
        }
    	
    	if (!empty($where['keyword']))
    	{
            $sql_str[] = " school_name LIKE ?";
            $bind[] = '%' . $where['keyword'] . '%';
    	}
    	
    	if (!empty($where['grade_period']))
    	{
            $sql_str[] = " grade_period LIKE ?";
            $bind[] = '%' . $where['grade_period'] . '%';
    	}
    	
    	$sql_str[] = " status = 1" ;
    	$sql .= $sql_str ? " WHERE " . implode(' AND ',$sql_str) : '';
    	return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 查询学校列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array                       list<map<string, variant>>
     */
    public static function schoolList(/*string*/ $field = NULL, 
        array $cond_param = NULL, /*int*/ $page = NULL, 
        /*int*/ $perpage = NULL)
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'province', 'city', 
                'area', 'grade_period', 'keyword','school_property');
            if (isset($cond_param['province']) 
                && is_numeric($cond_param['province'])
                && $cond_param['province'] > 0)
            {
                $where[] = 'province = ' . $cond_param['province'];
            }
            if (isset($cond_param['city'])
                && is_numeric($cond_param['city'])
                && $cond_param['city'] > 0)
            {
                $where[] = 'city = ' . $cond_param['city'];
            }
            if (isset($cond_param['area'])
                && is_numeric($cond_param['area'])
                && $cond_param['area'] > 0)
            {
                $where[] = 'area = ' . $cond_param['area'];
            }

            if (isset($cond_param['school_property'])
                && is_numeric($cond_param['school_property'])
                && $cond_param['school_property'] > 0)
            {
                $where[] = 'school_property = ' . $cond_param['school_property'];
            }

            if (isset($cond_param['grade_period'])
                && is_array($cond_param['grade_period']))
            {
                foreach ($cond_param['grade_period'] as $period)
                {
                    $period = intval($period);
                    $where[] = "grade_period LIKE '%$period%'";
                }
            }
            if (isset($cond_param['keyword']))
            {
                $where[] = 'school_name LIKE ?';
                $bind[] = '%' . $cond_param['keyword'] . '%';
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_school', $field, $where, $bind, 
            $order_by, $page, $perpage);
    }

    /**
     * 查询符合条件的学校数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function schoolListCount(array $cond_param = NULL)
    {
        unset($cond_param['order_by']);
        $rs = self::schoolList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }

    /**
     * 删除学校
     * @param   string  $school_id_str  学校ID列表,以英文逗号分隔
     * @return  bool    返回正确与否
     */
    public static function removeSchool($school_id_str)
    {
        $db = Fn::db();
        $sql = <<<EOT
SELECT schcls_id FROM t_school_class
WHERE schcls_schid IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_subject 
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }

        $sql = <<<EOT
SELECT id FROM rd_summary_region_student_rank
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT id FROM rd_summary_region_question
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT id FROM rd_summary_region_method_tactic
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT id FROM rd_summary_region_knowledge
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT id FROM rd_summary_region_group_type
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT id FROM rd_summary_region_difficulty
WHERE is_school = 1 AND region_id IN ($school_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }


        $sql = <<<EOT
SELECT uid FROM rd_student
WHERE school_id IN ($school_id_str)
EOT;

        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有学校在其它地方已使用');
        }

        $afr = $db->delete('rd_school', "school_id IN ($school_id_str)");
        return $afr > 0;
    }
    
    /**
     * 查询学校班级列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array                       list<map<string, variant>>
     */
    public static function schoolClassList(/*string*/ $field = NULL,
        array $cond_param = NULL, /*int*/ $page = NULL,
        /*int*/ $perpage = NULL)
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }
    
            $cond_param = Func::param_copy($cond_param, 'schcls_schid', 'schcls_name');
            
            if (isset($cond_param['schcls_schid'])
                && is_numeric($cond_param['schcls_schid'])
                && $cond_param['schcls_schid'] > 0)
            {
                $where[] = 'schcls_schid = ?';
                $bind[] =  $cond_param['schcls_schid'];
            }
            else if (isset($cond_param['schcls_schid'])
                && Validate::isJoinedIntStr($cond_param['schcls_schid']))
            {
                $where[] = 'schcls_schid IN ( ' . $cond_param['schcls_schid'] . ')';
            }
            
            if (isset($cond_param['schcls_name']))
            {
                $where[] = 'schcls_name LIKE ?';
                $bind[] = '%' . $cond_param['schcls_name'] . '%';
            }
        }
        else
        {
            $order_by = NULL;
        }
        
        return Fn::db()->fetchList('v_school_class', $field, $where, $bind,
            $order_by, $page, $perpage);
    }
    
    /**
     * 查询符合条件的学校班级数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function schoolClassListCount(array $cond_param = NULL)
    {
        unset($cond_param['order_by']);
        $rs = self::schoolClassList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }
    
    /**
     * 查询学校班级信息,若失败,返回NULL
     * @param   mixed   $schcls_id          学校班级ID
     * @param   string  $field = NULL       表示查询字段
     * @return  array                       map<string, variant>
     */
    public static function schoolClassInfo($schcls_id, /*string*/ $field = NULL)
    {
        if (!Validate::isInt($schcls_id))
        {
            return NULL;
        }
        
        if (is_null($field))
        {
            $field = '*';
        }
        
        $sql = "SELECT {$field} FROM t_school_class 
                WHERE schcls_id  = $schcls_id";
        $row = Fn::db()->fetchRow($sql);
        if (isset($row[$field]))
        {
            return $row[$field];
        }
        
        return $row;
    }
    
    /**
     * 查询学校班级信息,若失败,返回NULL
     * @param   mixed   $schcls_id          学校班级ID
     * @param   string  $field = NULL       表示查询字段
     * @return  array                       map<string, variant>
     */
    public static function schoolClassListInfo($schcls_id)
    {
        if (!Validate::isJoinedIntStr($schcls_id))
        {
            return NULL;
        }
    
        $sql = "SELECT * FROM t_school_class
                WHERE schcls_id
                " . (Validate::isInt($schcls_id) 
                    ? " = $schcls_id" : " IN ($schcls_id)");
    
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 新增学校班级
     * $params形如list<map<string, variant>>数据列表如下
     *          int     schcls_schid    学校id
     *          string  schcls_name     学校名称
     * @return  int     $schcls_id      学校班级ID
     */
    public static function addSchoolClass(array $params)
    {
        $params = Func::param_copy($params, 
            'schcls_schid', 'schcls_name');
        
        if (!Validate::isInt($params['schcls_schid'])
            || $params['schcls_schid'] <= 0)
        {
            throw new Exception('班级所属学校不能为空');
        }
        
        if (!Validate::isNotEmpty($params['schcls_name']))
        {
            throw new Exception('班级名称不能为空');
        }
        
        $db = Fn::db();
        
        $sql = "SELECT schcls_id FROM t_school_class
                WHERE schcls_schid = ? AND schcls_name = ?";
        if ($db->fetchOne($sql, array_values($params)))
        {
            throw new Exception('班级名称已经存在');
        }
        
        $params['schcls_ctime'] = time();
        $params['schcls_utime'] = time();
        
        $db->insert('t_school_class', $params);
        return $db->lastInsertId('t_school_class', 'schcls_id');
    }
    
    /**
     * 编辑学校班级
     * $params形如list<map<string, variant>>数据列表如下
     *          int     schcls_id       班级ID
     *          string  schcls_name     学校名称
     * @return  boolean true|false
     */
    public static function setSchoolClass(array $params)
    {
        $params = Func::param_copy($params,
            'schcls_id', 'schcls_name');
    
        if (!Validate::isInt($params['schcls_id'])
            || $params['schcls_id'] <= 0)
        {
            throw new Exception('班级ID不能为空');
        }
    
        if (!Validate::isNotEmpty($params['schcls_name']))
        {
            throw new Exception('班级名称不能为空');
        }
        
        $class = self::schoolClassInfo($params['schcls_id']);
        if (!$class)
        {
            throw new Exception('班级不存在');
        }
        
        $db = Fn::db();
    
        $sql = "SELECT schcls_id FROM t_school_class
                WHERE schcls_schid = ? AND schcls_name = ?
                AND schcls_id <> ?"; 
        $bind = array($class['schcls_id'], $params['schcls_name'], $params['schcls_id']);
        if ($db->fetchOne($sql, $bind))
        {
            throw new Exception('班级名称已经存在');
        }
    
        $params['schcls_utime'] = time();
    
        return $db->update('t_school_class', $params, 
            'schcls_id = ?', array($params['schcls_id']));
    }
    
    /**
     * 删除学校班级
     * @param   string  $schcls_id_str  班级ID列表,以英文逗号分隔
     * @return  bool    返回正确与否
     */
    public static function removeSchoolClass($schcls_id_str)
    {
        if (!Validate::isJoinedIntStr($schcls_id_str))
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = <<<EOT
SELECT place_id FROM rd_exam_place
WHERE place_schclsid IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_difficulty
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_knowledge
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_method_tactic
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_group_type
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_question
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_student_rank
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $sql = <<<EOT
SELECT id FROM rd_summary_region_subject
WHERE is_class = 1 AND region_id IN ($schcls_id_str)
EOT;
        $row = $db->fetchRow($sql);
        if ($row)
        {
            throw new Exception('有班级在其它地方已使用');
        }
        
        $afr = $db->delete('t_school_class', "schcls_id IN ($schcls_id_str)");
        return $afr > 0;
    }
    
    /**
     * 查询学校教师列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array                       list<map<string, variant>>
     */
    public static function schoolTeacherList(/*string*/ $field = NULL,
        array $cond_param = NULL, /*int*/ $page = NULL,
        /*int*/ $perpage = NULL)
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            $cond_param = Func::param_copy($cond_param, 'scht_schid', 'ct_id', 'ct_name',
                'ct_contact', 'grade_id_str', 'subject_id_str');
            
            if (isset($cond_param['scht_schid'])
                && Validate::isInt($cond_param['scht_schid']))
            {
                $where[] = 'scht_schid = ?';
                $bind[] = $cond_param['scht_schid'];
            }
            
            if (isset($cond_param['ct_id'])
                && Validate::isInt($cond_param['ct_id']))
            {
                $where[] = 'ct_id = ?';
                $bind[] = $cond_param['ct_id'];
            }
            if (isset($cond_param['ct_name'])
                && strlen($cond_param['ct_name']) > 0)
            {
                $where[] = 'ct_name LIKE ?';
                $bind[] = '%' . $cond_param['ct_name'] . '%';
            }

            if (isset($cond_param['grade_id_str'])
                && Validate::isJoinedIntStr($cond_param['grade_id_str']))
            {
                $where[] = 'ct_id IN (SELECT ctg_ctid FROM t_cteacher_gradeid WHERE ctg_gradeid IN (0,' . $cond_param['grade_id_str'] . '))';
            }
            if (isset($cond_param['subject_id_str'])
                && Validate::isJoinedIntStr($cond_param['subject_id_str']))
            {
                $where[] = 'ct_id IN (SELECT cts_ctid FROM t_cteacher_subjectid WHERE cts_subjectid IN (0,' . $cond_param['subject_id_str'] . '))';
            }
        }
        
        $field = $field ? $field : "*";
        
        $sql = "SELECT $field FROM t_cteacher_school
                LEFT JOIN t_cteacher ON scht_ctid = ct_id
                ";
        
        if (!empty($where))
        {
            $sql .= ' WHERE (' .implode(') AND (', $where). ')';
        }
        
        if (isset($cond_param['order_by']))
        {
            $sql .= ' ORDER BY '.$cond_param['order_by'];
        }

        $db = Fn::db();
        
        $page = $page ? $page : 1;
        $perpage = $perpage ? $perpage :
            C('default_perpage_num');
        $sql = $db->limitPage($sql, $page, $perpage);
        
        return $db->fetchAssoc($sql, $bind);
    }
    
    /**
     * 查询符合条件的学校教师数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function schoolTeacherListCount(array $cond_param = NULL)
    {
        unset($cond_param['order_by']);
        $rs = array_values(self::schoolTeacherList('COUNT(*) AS cnt', $cond_param));
        return $rs[0]['cnt'];
    }
    
    /**
     * 新增学校教师
     * @param   array   map<string, variant>类型的参数,
     *                  string      ct_name 
     *                  string      ct_contact
     *                  int         ct_flag
     *                  list<int>   gradeid_list
     *                  list<int>   subjectid_list
     *                  list<int>   cct_ccid_list
     * @return  int     返回新增的教师ID,否则返回0 
     */
    public static function addSchoolTeacher(array $param)//{{{
    {
        $param = Func::param_copy($param, 'scht_schid', 'ct_name',
            'ct_flag', 'gradeid_list', 'subjectid_list', 'ct_memo');
        
        if (!Validate::isInt($param['scht_schid'])
            || $param['scht_schid'] <= 0)
        {
            throw new Exception('教师所属学校不能为空');
        }
        
        if (!self::schoolInfo($param['scht_schid']))
        {
            throw new Exception('学校不存在');
        }
        
        if (!Validate::isStringLength($param['ct_name'], 1, 30))
        {
            throw new Exception('教师名称不能为空且长度最多30个字符');
        }
        
        if (!Validate::isInt($param['ct_flag']))
        {
            throw new Exception('教师状态标志必须为整数');
        }
        
        if (isset($param['ct_memo']))
        {
            if ($param['ct_memo'] == '')
            {
                $param['ct_memo'] = NULL;
            }
        }
        $gradeid_list = array();
        if (isset($param['gradeid_list']))
        {
            if (!is_array($param['gradeid_list']))
            {
                throw new Exception('年级参数类型不正确');
            }
            
            $param['gradeid_list'] = array_values($param['gradeid_list']);
            $param['gradeid_list'] = array_unique($param['gradeid_list']);
            foreach ($param['gradeid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('年级ID必须为整数');
                }
            }
            $gradeid_list = $param['gradeid_list'];
            unset($param['gradeid_list']);
        }
        
        if (empty($gradeid_list))
        {
            throw new Exception('年级ID必须填写');
        }
    
        $subjectid_list = array();
        if (isset($param['subjectid_list']))
        {
            if (!is_array($param['subjectid_list']))
            {
                throw new Exception('学科参数类型不正确');
            }
            $param['subjectid_list'] = array_values($param['subjectid_list']);
            $param['subjectid_list'] = array_unique($param['subjectid_list']);
            foreach ($param['subjectid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('学科ID必须为整数');
                }
            }
            $subjectid_list = $param['subjectid_list'];
            unset($param['subjectid_list']);
        }
        
        if (empty($subjectid_list))
        {
            throw new Exception('学科ID必须填写');
        }
    
        $db = Fn::db();
        $ct_id = 0;
        if ($db->beginTransaction())
        {
            $scht_schid = $param['scht_schid'];
            unset($param['scht_schid']);
            $db->insert('t_cteacher', $param);
            $ct_id = $db->lastInsertId('t_cteacher', 'ct_id');
            
            $bind = array(
                'scht_ctid' => $ct_id,
                'scht_schid' => $scht_schid,
            );
            $db->insert('t_cteacher_school', $bind);
            
            foreach ($gradeid_list as $v)
            {
                $db->insert('t_cteacher_gradeid',
                    array('ctg_ctid' => $ct_id,
                        'ctg_gradeid' => $v));
            }
            foreach ($subjectid_list as $v)
            {
                $db->insert('t_cteacher_subjectid',
                    array('cts_ctid' => $ct_id,
                        'cts_subjectid' => $v));
            }
    
            if (!$db->commit())
            {
                $db->rollBack();
                $ct_id = 0;
            }
        }
        
        return $ct_id;
    }
    
    /**
     * 修改教师
     * @param   array   map<string, variant>类型的参数
     *                  int         ct_id
     *                  string      ct_name
     *                  int         ct_flag
     *                  string      ct_memo
     *                  list<int>   gradeid_list
     *                  list<int>   subjectid_list
     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setSchoolTeacher(array $param)
    {
        $param = Func::param_copy($param, 'ct_id', 'ct_name',
            'ct_flag', 'gradeid_list', 'subjectid_list', 'ct_memo');
        if (!isset($param['ct_id']) || !Validate::isInt($param['ct_id']))
        {
            throw new Exception('教师ID不正确');
        }
        if (count($param) == 1)
        {
            throw new Exception('没有任何要修改的内容');
        }
    
        if (isset($param['ct_name'])
            && !Validate::isStringLength($param['ct_name'], 1, 30))
        {
            throw new Exception('教师名称不能为空且长度最多30个字符');
        }
        
        if (isset($param['ct_flag'])
            && !Validate::isInt($param['ct_flag']))
        {
            throw new Exception('教师状态标志必须为整数');
        }
        
        if (isset($param['ct_memo']))
        {
            if ($param['ct_memo'] == '')
            {
                $param['ct_memo'] = NULL;
            }
        }
    
        $gradeid_list = NULL;
        if (isset($param['gradeid_list']))
        {
            if (!is_array($param['gradeid_list']))
            {
                throw new Exception('年级参数类型不正确');
            }
            $param['gradeid_list'] = array_values($param['gradeid_list']);
            $param['gradeid_list'] = array_unique($param['gradeid_list']);
            foreach ($param['gradeid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('年级ID必须为整数');
                }
            }
            $gradeid_list = $param['gradeid_list'];
            unset($param['gradeid_list']);
    
            if (empty($gradeid_list))
            {
                throw new Exception('年级ID必须填写');
            }
        }
    
        $subjectid_list = NULL;
        if (isset($param['subjectid_list']))
        {
            if (!is_array($param['subjectid_list']))
            {
                throw new Exception('学科参数类型不正确');
            }
            $param['subjectid_list'] = array_values($param['subjectid_list']);
            $param['subjectid_list'] = array_unique($param['subjectid_list']);
            foreach ($param['subjectid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('学科ID必须为整数');
                }
            }
            $subjectid_list = $param['subjectid_list'];
            unset($param['subjectid_list']);
            if (empty($subjectid_list))
            {
                throw new Exception('学科ID必须填写');
            }
        }
    
        $ct_id = $param['ct_id'];
        unset($param['ct_id']);
    
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->update('t_cteacher', $param, "ct_id = $ct_id");
            if (is_array($gradeid_list))
            {
                $db->delete('t_cteacher_gradeid', 'ctg_ctid = ' . $ct_id);
                foreach ($gradeid_list as $v)
                {
                    $db->insert('t_cteacher_gradeid',
                        array('ctg_ctid' => $ct_id,
                            'ctg_gradeid' => $v));
                }
            }
            if (is_array($subjectid_list))
            {
                $db->delete('t_cteacher_subjectid', 'cts_ctid = ' . $ct_id);
                foreach ($subjectid_list as $v)
                {
                    $db->insert('t_cteacher_subjectid',
                        array('cts_ctid' => $ct_id,
                            'cts_subjectid' => $v));
                }
            }
    
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        
        return $bOk ? 1 : 0;
    }
    
    /**
     * 删除教师,若ct_flag > -1则为假删，否则为真删(已使用过的不可真删)
     * @param   string      $ct_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非0,否则返回0
     */
    public static function removeSchoolTeacher(/*string*/$ct_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ct_id_str))
        {
            throw new Exception('教师ID列表格式不正确,'
                . '应为英文逗号分隔开的ID字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT ct_id FROM t_cteacher
WHERE ct_flag = -1 AND ct_id IN ({$ct_id_str})
EOT;
        $rm_ct_ids = $db->fetchCol($sql);   // 需要真删的ID
        if (!empty($rm_ct_ids))
        {
           
        }
    
        $bOk = false;
    
        if ($db->beginTransaction())
        {
            if (!empty($rm_ct_ids))
            {
                $rm_ct_str = implode(',', $rm_ct_ids);
                
                // 可真删的ID
                $db->delete('t_cteacher_gradeid',
                    "ctg_ctid IN ($rm_ct_str)");
                
                $db->delete('t_cteacher_subjectid',
                    "cts_ctid IN ($rm_ct_str)");
                
                $db->delete('t_cteacher_school', 
                    "scht_ctid IN ($rm_ct_str)");
                
                $db->delete('t_cteacher', 
                    "ct_id IN ($rm_ct_str)");
            }
            
            $db->update('t_cteacher', array('ct_flag' => -1),
                "ct_id IN ($ct_id_str)");
            
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        
        return $bOk ? 1 : 0;
    }
}


