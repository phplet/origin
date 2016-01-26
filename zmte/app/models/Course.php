<?php
/**
 * CourseModel
 * @file    Course.php
 * @author  BJP
 * @final   2015-06-17
 */
class CourseModel
{
    /*******************  课程授课模式档案接口  ****************************/
    /**
     * 返回授课模式表
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function courseModeList($field = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$field} FROM t_course_mode ORDER BY cm_id
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 返回授课模式表
     * @return  array   map<int, string>类型数据
     */
    public static function courseModePairs()//{{{
    {
        $sql = <<<EOT
SELECT cm_id, cm_name FROM t_course_mode ORDER BY cm_id
EOT;
        return Fn::db()->fetchPairs($sql);
    }//}}}


    /**
     * 返回一条授课模式信息
     * @param   int     $cm_id     
     * @param   string  $field  = '*'   获取字段
     * @return  array   mpa<string, variant>类型数据
     */
    public static function courseModeInfo(/*int*/$cm_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($cm_id))
        {
            throw new Exception('授课模式ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_course_mode WHERE cm_id = {$cm_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 新增授课模式
     * @param   array   map<string, variant>类型的参数
     *                  int         cm_id
     *                  string      cm_name
     * @return  boolean
     */
    public static function addCourseMode(array $param)//{{{
    {
        $param = Func::param_copy($param, 'cm_id', 'cm_name');
        if (!Validate::isInt($param['cm_id']))
        {
            throw new Exception('错误的授课模式ID');
        }
        if (!Validate::isStringLength($param['cm_name'], 1, 30))
        {
            throw new Exception('授课模式名称不可为空且要小于30个字');
        }

        $db = Fn::db();
        $sql = <<<EOT
SELECT cm_id FROM t_course_mode WHERE cm_id = ? OR cm_name = ?
EOT;
        $row = $db->fetchRow($sql, array($param['cm_id'], $param['cm_name']));
        if ($row)
        {
            throw new Exception('重复的ID或名称记录');
        }
        $v = $db->insert('t_course_mode', $param);
        return $v > 0;
    }//}}}

    /**
     * 修改授课模式
     * @param   int     $cm_id  原ID
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['cm_id']     新ID
     *                          string  $param['cm_name']   新名称
     * @return  boolean
     */
    public static function setCourseMode($cm_id, array $param)//{{{
    {
        $param = Func::param_copy($param, 'cm_id', 'cm_name');
        if (!Validate::isInt($cm_id))
        {
            throw new Exception('错误的授课模式原ID');
        }
        if (!Validate::isInt($param['cm_id']))
        {
            throw new Exception('错误的授课模式ID');
        }
        if (!Validate::isStringLength($param['cm_name'], 1, 30))
        {
            throw new Exception('授课模式名称不可为空且要小于30个字');
        }

        $db = Fn::db();

        $sql = <<<EOT
SELECT cm_id, cm_name FROM t_course_mode WHERE cm_id = ?
EOT;
        $cm_info = $db->fetchRow($sql, $cm_id);
        if (!$cm_info)
        {
            throw new Exception('授课模式ID(' . $cm_id . ')不存在');
        }

        if ($cm_id == $param['cm_id'])
        {
            if (trim($cm_info['cm_name']) == $param['cm_name'])
            {
                throw new Exception('授课模式名称没有改变');
            }
            $sql = <<<EOT
SELECT cm_id FROM t_course_mode WHERE cm_id <> ? AND cm_name = ?
EOT;
            $row = $db->fetchRow($sql, 
                array($param['cm_id'], $param['cm_name']));
            if ($row)
            {
                throw new Exception('重复的授课模式名称记录');
            }
            $v = $db->update('t_course_mode', 
                array('cm_name' => $param['cm_name']),
                'cm_id = ' . $cm_id);
        }
        else
        {
            $sql = <<<EOT
SELECT cm_id FROM t_course_mode
WHERE (cm_id <> ? AND (cm_id = ? OR cm_name = ?))
EOT;
            $row = $db->fetchRow($sql, 
                array($cm_id, $param['cm_id'], $param['cm_name']));
            if ($row)
            {
                throw new Exception('重复的授课模式名称记录');
            }
            $v = 0;
            if ($db->beginTransaction())
            {
                $db->update('t_course',
                    array('cors_cmid' => $param['cm_id']),
                    "cors_cmid = $cm_id");
                $v = $db->update('t_course_mode', $param, 
                    'cm_id = ' . $cm_id);
                if (!$db->commit())
                {
                    $db->rollBack();
                }
            }
        }
        return $v > 0;
    }//}}}

    /**
     * 删除授课模式
     * @param   string  $cm_id_str  形如1,2,3样式的ID列表字符串
     * @return  boolean
     */
    public static function removeCourseMode($cm_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cm_id_str))
        {
            throw new Exception('非法的授课模式ID参数字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT cors_id FROM t_course WHERE cors_cmid IN ({$cm_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('课程中有用到了要删除的类型ID,不可删除');
        }

        $v = $db->delete('t_course_mode', "cm_id IN ($cm_id_str)");
        return $v > 0;
    }//}}}


    /*******************  课程教师来源档案接口  ****************************/
    /**
     * 返回课程教师来源表
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function courseTeachfromList($field = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$field} FROM t_course_teachfrom ORDER BY ctf_id
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 返回课程教师来源表
     * @return  array   map<int, string>类型数据
     */
    public static function courseTeachfromPairs()//{{{
    {
        $sql = <<<EOT
SELECT ctf_id, ctf_name FROM t_course_teachfrom ORDER BY ctf_id
EOT;
        return Fn::db()->fetchPairs($sql);
    }//}}}


    /**
     * 返回一条授课教师来源信息
     * @param   int     $ctf_id     
     * @param   string  $field  = '*'   获取字段
     * @return  array   map<string, variant>类型数据
     */
    public static function courseTeachfromInfo(/*int*/$ctf_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($ctf_id))
        {
            throw new Exception('授课教师来源ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_course_teachfrom WHERE ctf_id = {$ctf_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 新增课程教师来源
     * @param   array   map<string, variant>类型的参数
     *                  int         ctf_id
     *                  string      ctf_name
     * @return  boolean             返回成功与否
     */
    public static function addCourseTeachfrom(array $param)//{{{
    {
        $param = Func::param_copy($param, 'ctf_id', 'ctf_name');
        if (!Validate::isInt($param['ctf_id']))
        {
            throw new Exception('错误的课程教师来源ID');
        }
        if (!Validate::isStringLength($param['ctf_name'], 1, 30))
        {
            throw new Exception('课程教师来源名称不可为空且要小于30个字');
        }

        $db = Fn::db();
        $sql = <<<EOT
SELECT ctf_id FROM t_course_teachfrom WHERE ctf_id = ? OR ctf_name = ?
EOT;
        $row = $db->fetchRow($sql, array($param['ctf_id'], $param['ctf_name']));
        if ($row)
        {
            throw new Exception('重复的ID或名称记录');
        }
        $v = $db->insert('t_course_teachfrom', $param);
        return $v > 0;
    }//}}}

    /**
     * 修改课程教师来源
     * @param   int     $ctf_id  原ID
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['ctf_id']     新ID
     *                          string  $param['ctf_name']   新名称
     * @return  boolean
     */
    public static function setCourseTeachfrom($ctf_id, array $param)//{{{
    {
        $param = Func::param_copy($param, 'ctf_id', 'ctf_name');
        if (!Validate::isInt($ctf_id))
        {
            throw new Exception('错误的课程教师来源原ID');
        }
        if (!Validate::isInt($param['ctf_id']))
        {
            throw new Exception('错误的课程教师来源ID');
        }
        if (!Validate::isStringLength($param['ctf_name'], 1, 30))
        {
            throw new Exception('课程教师来源名称不可为空且要小于30个字');
        }

        $db = Fn::db();

        $sql = <<<EOT
SELECT ctf_id, ctf_name FROM t_course_teachfrom WHERE ctf_id = ?
EOT;
        $ctf_info = $db->fetchRow($sql, $ctf_id);
        if (!$ctf_info)
        {
            throw new Exception('课程教师来源ID(' . $ctf_id . ')不存在');
        }

        if ($ctf_id == $param['ctf_id'])
        {
            if (trim($ctf_info['ctf_name']) == $param['ctf_name'])
            {
                throw new Exception('课程教师来源名称没有改变');
            }
            $sql = <<<EOT
SELECT ctf_id FROM t_course_teachfrom WHERE ctf_id <> ? AND ctf_name = ?
EOT;
            $row = $db->fetchRow($sql, 
                array($param['ctf_id'], $param['ctf_name']));
            if ($row)
            {
                throw new Exception('重复的课程教师来源名称记录');
            }
            $v = $db->update('t_course_teachfrom', 
                array('ctf_name' => $param['ctf_name']),
                'ctf_id = ' . $ctf_id);
        }
        else
        {
            $sql = <<<EOT
SELECT ctf_id FROM t_course_teachfrom
WHERE (ctf_id <> ? AND (ctf_id = ? OR ctf_name = ?))
EOT;
            $row = $db->fetchRow($sql, 
                array($ctf_id, $param['ctf_id'], $param['ctf_name']));
            if ($row)
            {
                throw new Exception('重复的课程教师来源名称记录');
            }
            $v = 0;
            if ($db->beginTransaction())
            {
                $db->update('t_course_campus',
                    array('cc_ctfid' => $param['ctf_id']),
                    "cc_ctfid = $ctf_id");
                $v = $db->update('t_course_teachfrom',
                    $param, 'ctf_id = ' . $ctf_id);
                if (!$db->commit())
                {
                    $db->rollBack();
                }
            }
        }
        return $v > 0;
    }//}}}

    /**
     * 删除课程教师来源
     * @param   string      $ctf_id_str 形如1,2,43样式的ID列表字符串
     * @return  boolean     返回成功与否
     */
    public static function removeCourseTeachfrom($ctf_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ctf_id_str))
        {
            throw new Exception('非法的课程教师来源ID参数字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT cc_id FROM t_course_campus WHERE cc_ctfid IN ({$ctf_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('课程中有用到了要删除的教师来源ID,不可删除');
        }

        $v = $db->delete('t_course_teachfrom', 
                    "ctf_id IN ($ctf_id_str)");
        return $v > 0;
    }//}}}


    /*******************  课程授课人数类别档案接口  *************************/
    /**
     * 返回授课人数类别表
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function courseStuNumTypeList($field = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$field} FROM t_course_stunumtype ORDER BY csnt_id
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 返回授课人数类别表
     * @return  array   map<int, string>类型数据
     */
    public static function courseStuNumTypePairs()//{{{
    {
        $sql = <<<EOT
SELECT csnt_id, csnt_name FROM t_course_stunumtype ORDER BY csnt_id
EOT;
        return Fn::db()->fetchPairs($sql);
    }//}}}

    /**
     * 返回一条授课人数类别信息
     * @param   int     $csnt_id     
     * @param   string  $field  = '*'   获取字段
     * @return  array   mpa<string, variant>类型数据
     */
    public static function courseStuNumTypeInfo(/*int*/$csnt_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($csnt_id))
        {
            throw new Exception('授课人数类别ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_course_stunumtype WHERE csnt_id = {$csnt_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 新增授课人数类别
     * @param   array   $param  map<string, variant>类型的参数
     *                          int         csnt_id
     *                          string      csnt_name
     *                          string      csnt_memo
     * @return  boolean         返回成功与否
     */
    public static function addCourseStuNumType(array $param)//{{{
    {
        $param = Func::param_copy($param, 'csnt_id', 'csnt_name', 'csnt_memo');
        if (!Validate::isInt($param['csnt_id']))
        {
            throw new Exception('错误的授课授课人数类别ID');
        }
        if (!Validate::isStringLength($param['csnt_name'], 1, 30))
        {
            throw new Exception('授课人数类别名称不可为空且要小于30个字');
        }

        $db = Fn::db();
        $sql = <<<EOT
SELECT csnt_id FROM t_course_stunumtype WHERE csnt_id = ? OR csnt_name = ?
EOT;
        $row = $db->fetchRow($sql, array($param['csnt_id'], $param['csnt_name']));
        if ($row)
        {
            throw new Exception('重复的ID或名称记录');
        }
        $v = $db->insert('t_course_stunumtype', $param);
        return $v > 0;
    }//}}}

    /**
     * 修改授课人数类别
     * @param   int     $csnt_id  原ID
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['csnt_id']     新ID
     *                          string  $param['csnt_name']   新名称
     *                          string  $param['csnt_memo']   新名称
     * @return  boolean
     */
    public static function setCourseStuNumType($csnt_id, array $param)//{{{
    {
        $param = Func::param_copy($param, 'csnt_id', 'csnt_name', 'csnt_memo');
        if (!Validate::isInt($csnt_id))
        {
            throw new Exception('错误的授课人数类别原ID');
        }
        if (!Validate::isInt($param['csnt_id']))
        {
            throw new Exception('错误的授课人数类别ID');
        }
        if (!Validate::isStringLength($param['csnt_name'], 1, 30))
        {
            throw new Exception('授课人数类别名称不可为空且要小于30个字');
        }
        if (isset($param['csnt_memo']))
        {
            if ($param['csnt_memo'] == '')
            {
                $param['csnt_memo'] = NULL;
            }
            else if (!Validate::isStringLength($param['csnt_memo'], 1, 255))
            {
                throw new Exception('授课人数类别备注字数不应超过255');
            }
        }

        $db = Fn::db();

        $sql = <<<EOT
SELECT csnt_id, csnt_name FROM t_course_stunumtype WHERE csnt_id = ?
EOT;
        $csnt_info = $db->fetchRow($sql, $csnt_id);
        if (!$csnt_info)
        {
            throw new Exception('授课人数类别ID(' . $csnt_id . ')不存在');
        }

        if ($csnt_id == $param['csnt_id'])
        {
            if (trim($csnt_info['csnt_name']) == $param['csnt_name'])
            {
                throw new Exception('授课人数类别名称没有改变');
            }
            $sql = <<<EOT
SELECT csnt_id FROM t_course_stunumtype WHERE csnt_id <> ? AND csnt_name = ?
EOT;
            $row = $db->fetchRow($sql, 
                array($param['csnt_id'], $param['csnt_name']));
            if ($row)
            {
                throw new Exception('重复的授课人数类别名称记录');
            }
            unset($param['csnt_id']);
            $v = $db->update('t_course_stunumtype', $param,
                'csnt_id = ' . $csnt_id);
        }
        else
        {
            $sql = <<<EOT
SELECT csnt_id FROM t_course_stunumtype
WHERE (csnt_id <> ? AND (csnt_id = ? OR csnt_name = ?))
EOT;
            $row = $db->fetchRow($sql, 
                array($csnt_id, $param['csnt_id'], $param['csnt_name']));
            if ($row)
            {
                throw new Exception('重复的授课人数类别名称记录');
            }
            $v = 0;
            if ($db->beginTransaction())
            {
                $db->update('t_course',
                    array('cors_stunumtype' => $param['csnt_id']),
                    "cors_stunumtype = $csnt_id");
                $v = $db->update('t_course_stunumtype', $param, 
                    'csnt_id = ' . $csnt_id);
                if (!$db->commit())
                {
                    $db->rollBack();
                }
            }
        }
        return $v > 0;
    }//}}}

    /**
     * 删除授课人数类别
     * @param   string      $csnt_id_str    形如1,32,43样式的ID列表字符串
     * @return  boolean
     */
    public static function removeCourseStuNumType($csnt_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($csnt_id_str))
        {
            throw new Exception('非法的授课人数类别ID参数字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT cors_id FROM t_course WHERE cors_stunumtype IN ({$csnt_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('课程中有用到了要删除的课程授课人数类别ID,不可删除');
        }

        $sql = <<<EOT
SELECT sbs_uid FROM t_student_base_stunumtype WHERE sbs_stunumtype IN ({$csnt_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('学生可接受授课班级类型中有用到了要删除的课程授课人数类别ID,不可删除');
        }


        $v = $db->delete('t_course_stunumtype', "csnt_id IN ($csnt_id_str)");
        return $v > 0;
    }//}}}

    /************************** 课程相关接口 *******************************/
    // TODO
    /** 课程列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function courseList(/*string*/$field = NULL,
        array $cond_param = NULL, /*int*/$page = NULL, 
        /*int*/$perpage = NULL)//{{{
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'cors_id', 'cors_name',
                'cors_cmid', 'cors_tiid', 'ti_name', 'grade_id_str',
                'subject_id_str', 'class_id_str');
            if (isset($cond_param['cors_tiid']) 
                && Validate::isInt($cond_param['cors_tiid'])
                && $cond_param['cors_tiid'] > 0)
            {
                $where[] = 'cors_tiid = ' . $cond_param['cors_tiid'];
            }
            if (isset($cond_param['ti_name'])
                && strlen($cond_param['ti_name']) > 0)
            {
                $where[] = 'ti_name LIKE ?';
                $bind[] = '%' . $cond_param['ti_name'] . '%';
            }

            /*
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
             */
            if (isset($cond_param['cors_id'])
                && Validate::isInt($cond_param['cors_id']))
            {
                $where[] = 'cors_id = ?';
                $bind[] = $cond_param['cors_id'];
            }
            if (isset($cond_param['cors_name'])
                && strlen($cond_param['cors_name']) > 0)
            {
                $where[] = 'cors_name LIKE ?';
                $bind[] = '%' . $cond_param['cors_name'] . '%';
            }
            if (isset($cond_param['cors_cmid'])
                && Validate::isInt($cond_param['cors_cmid']))
            {
                $where[] = 'cors_cmid = ?';
                $bind[] = $cond_param['cors_cmid'];
            }
            if (isset($cond_param['grade_id_str'])
                && Validate::isJoinedIntStr($cond_param['grade_id_str']))
            {
                $where[] = 'cors_id IN (SELECT cg_corsid FROM t_course_gradeid WHERE cg_gradeid IN (0,' . $cond_param['grade_id_str'] . '))';
            }
            if (isset($cond_param['subject_id_str'])
                && Validate::isJoinedIntStr($cond_param['subject_id_str']))
            {
                $where[] = 'cors_id IN (SELECT cs_corsid FROM t_course_subjectid WHERE cs_subjectid IN (0,' . $cond_param['subject_id_str']  . '))';
            }
            if (isset($cond_param['class_id_str'])
                && Validate::isJoinedIntStr($cond_param['class_id_str']))
            {
                $where[] = 'cors_id IN (SELECT cci_corsid FROM t_course_classid WHERE cci_classid IN (' . $cond_param['class_id_str'] . '))';
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_course', $field, $where, 
            $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的课程数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function courseListCount(array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::courseList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条课程信息,从v_course视图中查询
     * @param   int     $cors_id        课程ID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function courseInfo(/*int*/$cors_id,
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($cors_id))
        {
            throw new Exception('课程ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM v_course WHERE cors_id = {$cors_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}

    /**
     * 新增培训课程
     * @param   array   $param  map<string, variant>类型的参数,
     *                          string      cors_name
     *                          int         cors_cmid
     *                          int         cors_flag
     *                          int         cors_tiid
     *                          string      cors_url
     *                          string      cors_memo
     *                          int         cors_stunumtype
     *                          list<int>   classid_list
     *                          map<int,int> kid_knprocid_pairs
     *                                      知识点->认知过程
     *                          list<int>   gradeid_list
     *                          list<int>   subjectid_list
     * @return  int     返回新增的课程ID,否则返回0 
     */
    public static function addCourse(array $param)//{{{
    {
        $param = Func::param_copy($param, 'cors_name', 'cors_cmid', 
            'cors_flag', 'cors_tiid', 'cors_url', 'cors_memo', 
            'cors_stunumtype',
            'classid_list', 'kid_knprocid_pairs', 'gradeid_list',
            'subjectid_list');

        if (!Validate::isStringLength($param['cors_name'], 1, 100))
        {
            throw new Exception('课程名称不能为空且长度最多100个字符');
        }
        if (!Validate::isInt($param['cors_cmid']))
        {
            throw new Exception('课程模式ID必须为整数');
        }
        if (!Validate::isInt($param['cors_tiid']))
        {
            throw new Exception('课程所属培训机构ID必须为整数');
        }
        if (!Validate::isInt($param['cors_flag']))
        {
            throw new Exception('课程状态标志必须为整数');
        }
        if (!Validate::isInt($param['cors_stunumtype']))
        {
            throw new Exception('课程人数类别ID必须为整数');
        }
        if (isset($param['cors_url']))
        {
            if ($param['cors_url'] == '')
            {
                $param['cors_url'] = NULL;
            }
            else if (!Validate::isStringLength($param['cors_url'], 1, 512))
            {
                throw new Exception('课程网址最多512个字符');
            }
        }
        if (isset($param['cors_memo']))
        {
            if ($param['cors_memo'] == '')
            {
                $param['cors_memo'] = NULL;
            }
        }
        $classid_list = array();
        if (isset($param['classid_list']))
        {
            if (!is_array($param['classid_list']))
            {
                throw new Exception('考试类型参数类型不正确');
            }
            $param['classid_list'] = array_values($param['classid_list']);
            $param['classid_list'] = array_unique($param['classid_list']);
            foreach ($param['classid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('考试类型ID必须为整数');
                }
            }
            $classid_list = $param['classid_list'];
            unset($param['classid_list']);
        }

        $kid_knprocid_pairs = array();
        if (isset($param['kid_knprocid_pairs']))
        {
            if (!is_array($param['kid_knprocid_pairs']))
            {
                throw new Exception('相关知识点参数类型不正确');
            }
            foreach ($param['kid_knprocid_pairs'] as $k => $v)
            {
                if (!Validate::isInt($v) || !Validate::isInt($k))
                {
                    throw new Exception('相关知识点ID或认知过程ID必须为整数');
                }
            }
            $kid_knprocid_pairs = $param['kid_knprocid_pairs'];
            unset($param['kid_knprocid_pairs']);
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
        if ($param['cors_stunumtype'] != 1 && count($gradeid_list) > 1)
        {
            throw new Exception('年级ID只能填写一个');
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
        if ($param['cors_stunumtype'] != 1 && count($subjectid_list) > 1)
        {
            throw new Exception('学科ID只能填写一个');
        }

        $db = Fn::db();

        $param['cors_addtime'] = date('Y-m-d H:i:s');
        $param['cors_adduid'] = Fn::sess()->userdata('admin_id');
        $param['cors_lastmodify'] = $param['cors_addtime'];

        $cors_id = 0;
        if ($db->beginTransaction())
        {
            $db->insert('t_course', $param);
            $cors_id = $db->lastInsertId('t_course', 'cors_id');
            foreach ($classid_list as $v)
            {
                $db->insert('t_course_classid', 
                    array('cci_corsid' => $cors_id,
                    'cci_classid' => $v));
            }
            foreach ($kid_knprocid_pairs as $k => $v)
            {
                $db->insert('t_course_knowledge', 
                    array('ck_corsid' => $cors_id,
                    'ck_kid' => $k, 'ck_knprocid' => $v));
            }
            foreach ($gradeid_list as $v)
            {
                $db->insert('t_course_gradeid', 
                    array('cg_corsid' => $cors_id,
                    'cg_gradeid' => $v));
            }
            foreach ($subjectid_list as $v)
            {
                $db->insert('t_course_subjectid', 
                    array('cs_corsid' => $cors_id,
                    'cs_subjectid' => $v));
            }
            if (!$db->commit())
            {
                $db->rollBack();
                $cors_id = 0;
            }
        }
        return $cors_id;
    }//}}}

    /**
     * 修改课程(所属机构不可修改)
     * @param   array   $param  map<string, variant>类型的参数,
     *                          int         cors_id
     *                          string      cors_name
     *                          int         cors_cmid
     *                          int         cors_flag
     *                          int         cors_tiid
     *                          string      cors_url
     *                          string      cors_memo
     *                          int         cors_stunumtype
     *                          list<int>   classid_list
     *                          map<int,int> kid_knprocid_pairs
     *                                      知识点->认知过程
     *                          list<int>   gradeid_list
     *                          list<int>   subjectid_list
     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setCourse(array $param, $bUseTrans = true)//{{{
    {
        $param = Func::param_copy($param, 'cors_id', 'cors_name', 
            'cors_cmid', 'cors_flag', 'cors_stunumtype',
            'cors_url', 'cors_memo',
            'classid_list', 'kid_knprocid_pairs',
            'gradeid_list', 'subjectid_list');
        if (!isset($param['cors_id']) || !Validate::isInt($param['cors_id']))
        {
            throw new Exception('课程ID不正确');
        }
        if (count($param) == 1)
        {
            throw new Exception('没有任何要修改的内容');
        }

        if (isset($param['cors_name'])
            && !Validate::isStringLength($param['cors_name'], 1, 100))
        {
            throw new Exception('课程名称不能为空且长度最多100个字符');
        }
        if (isset($param['cors_cmid'])
            && !Validate::isInt($param['cors_cmid']))
        {
            throw new Exception('课程模式ID必须为整数');
        }
        if (isset($param['cors_flag'])
            && !Validate::isInt($param['cors_flag']))
        {
            throw new Exception('课程状态标志必须为整数');
        }
        if (isset($param['cors_stunumtype']) 
            && !Validate::isInt($param['cors_stunumtype']))
        {
            throw new Exception('课程人数类别ID必须为整数');
        }
        if (isset($param['cors_url']))
        {
            if ($param['cors_url'] == '')
            {
                $param['cors_url'] = NULL;
            }
            else if (!Validate::isStringLength($param['cors_url'], 1, 512))
            {
                throw new Exception('课程网址最多512个字符');
            }
        }
        if (isset($param['cors_memo']))
        {
            if ($param['cors_memo'] == '')
            {
                $param['cors_memo'] = NULL;
            }
        }

        $classid_list = NULL;
        if (isset($param['classid_list']))
        {
            if (!is_array($param['classid_list']))
            {
                throw new Exception('考试类型参数类型不正确');
            }
            $param['classid_list'] = array_values($param['classid_list']);
            $param['classid_list'] = array_unique($param['classid_list']);
            foreach ($param['classid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('考试类型ID必须为整数');
                }
            }
            $classid_list = $param['classid_list'];
            unset($param['classid_list']);
        }

        $kid_knprocid_pairs = NULL;
        if (isset($param['kid_knprocid_pairs']))
        {
            if (!is_array($param['kid_knprocid_pairs']))
            {
                throw new Exception('相关知识点参数类型不正确');
            }
            foreach ($param['kid_knprocid_pairs'] as $k => $v)
            {
                if (!Validate::isInt($v) || !Validate::isInt($k))
                {
                    throw new Exception('相关知识点ID或认知过程ID必须为整数');
                }
            }
            $kid_knprocid_pairs = $param['kid_knprocid_pairs'];
            unset($param['kid_knprocid_pairs']);
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

        $cors_id = $param['cors_id'];
        unset($param['cors_id']);

        $db = Fn::db();

        $sql = <<<EOT
SELECT cors_id, cors_stunumtype FROM t_course WHERE cors_id = {$cors_id}
EOT;
        $cors_info = $db->fetchRow($sql);
        if (empty($cors_info))
        {
            throw new Exception('要修改的记录不存在');
        }

        if (!is_null($gradeid_list))
        {
            if (isset($param['cors_stunumtype']))
            {
                if ($param['cors_stunumtype'] != 1 
                    && count($gradeid_list) > 1)
                {
                    throw new Exception('年级ID只能填写一个');
                }
            }
            else if ($cors_info['cors_stunumtype'] != 1
                && count($gradeid_list) > 1)
            {
                throw new Exception('年级ID只能填写一个');
            }
        }
        if (!is_null($subjectid_list))
        {
            if (isset($param['cors_stunumtype']))
            {
                if ($param['cors_stunumtype'] != 1 
                    && count($subjectid_list) > 1)
                {
                    throw new Exception('学科ID只能填写一个');
                }
            }
            else if ($cors_info['cors_stunumtype'] != 1
                && count($subjectid_list) > 1)
            {
                throw new Exception('学科ID只能填写一个');
            }
        }
        if (isset($param['cors_stunumtype']))
        {
            if ($cors_info['cors_stunumtype'] == 1
                && $param['cors_stunumtype'] != 1)
            {
                // 原为一对一，新为一对多，即原年级学科为多，新为一
                if (is_null($gradeid_list))
                {
                    // 要检查原年级设置是否有多条
                    $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM t_course_gradeid WHERE cg_corsid = {$cors_id}
EOT;
                    $cnt = $db->fetchOne($sql);
                    if ($cnt > 1)
                    {
                        throw new Exception('年级只能设置一个');
                    }
                }
                if (is_null($subjectid_list))
                {
                    // 要检查原学科设置是否有多条
                    $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM t_course_subjectid WHERE cs_corsid = {$cors_id}
EOT;
                    $cnt = $db->fetchOne($sql);
                    if ($cnt > 1)
                    {
                        throw new Exception('学科只能设置一个');
                    }
                }
            }
            if ($param['cors_stunumtype'] == 1)
            {
                // 如果新为一对一，则知识点无效
                if (!is_null($kid_knprocid_pairs))
                {
                    $kid_knprocid_pairs = array();
                }
            }
        }
        else
        {
            if ($cors_info['cors_stunumtype'] == 1)
            {
                // 如果为一对一，则知识点无效
                if (!is_null($kid_knprocid_pairs))
                {
                    $kid_knprocid_pairs = array();
                }
            }
        }

        $bOk = false;
        if ($bUseTrans)
        {
            if (!$db->beginTransaction())
            {
                return 0;
            }
        }
        $param['cors_lastmodify'] = date('Y-m-d H:i:s');
        $db->update('t_course', $param, "cors_id = $cors_id");
        if (is_array($classid_list))
        {
            $db->delete('t_course_classid', 'cci_corsid = ' . $cors_id);
            foreach ($classid_list as $v)
            {
                $db->insert('t_course_classid', 
                    array('cci_corsid' => $cors_id,
                    'cci_classid' => $v));
            }
        }
        if (is_array($kid_knprocid_pairs))
        {
            $db->delete('t_course_knowledge', 'ck_corsid = ' . $cors_id);
            foreach ($kid_knprocid_pairs as $k => $v)
            {
                $db->insert('t_course_knowledge', 
                    array('ck_corsid' => $cors_id,
                    'ck_kid' => $k, 'ck_knprocid' => $v));
            }
        }
        if (is_array($gradeid_list))
        {
            $db->delete('t_course_gradeid', 'cg_corsid = ' . $cors_id);
            foreach ($gradeid_list as $v)
            {
                $db->insert('t_course_gradeid', 
                    array('cg_corsid' => $cors_id,
                    'cg_gradeid' => $v));
            }
        }
        if (is_array($subjectid_list))
        {
            $db->delete('t_course_subjectid', 'cs_corsid = ' . $cors_id);
            foreach ($subjectid_list as $v)
            {
                $db->insert('t_course_subjectid', 
                    array('cs_corsid' => $cors_id,
                    'cs_subjectid' => $v));
            }
        }
        
        if ($bUseTrans)
        {
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}

    // TODO 还可能有其它未知的表要删除
    /**
     * 删除课程,若cors_flag > -1则为假删，否则为真删(已使用过的不可真删)
     * @param   string      $cors_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非0,否则返回0
     */
    public static function removeCourse(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表格式不正确,'
                . '应为英文逗号分隔开的ID字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT cors_id FROM t_course
WHERE cors_flag = -1 AND cors_id IN ({$cors_id_str})
EOT;
        $rm_cors_ids = $db->fetchCol($sql);   // 需要真删的ID
        if (!empty($rm_cors_ids))
        {
            // 这里计算出其它地方使用到的现在需要真删的ID,利用差集
            // 计算出能真删的ID
            $rm_cors_str = implode(',', $rm_cors_ids);
            $sql = <<<EOT
SELECT DISTINCT sbc_corsid FROM t_student_base_course WHERE sbc_corsid IN ({$rm_cors_str})
EOT;
            $nrm_cors_ids = $db->fetchCol($sql);  // 不可真删的ID

            $rm_cors_ids = array_diff($rm_cors_ids, $nrm_cors_ids);
        }

        $bOk = false;

        if ($db->beginTransaction())
        {
            if (!empty($rm_cors_ids))
            {
                $rm_cors_str = implode(',', $rm_cors_ids);
                // 可真删的ID
                $db->delete('t_course_campus_teacher',
                    "cct_ccid IN (SELECT cc_id FROM t_course_campus WHERE cc_corsid IN ($rm_cors_str))");
                $db->delete('t_course_campus', 
                    "cc_corsid IN ($rm_cors_str)");
                $db->delete('t_course_classid', 
                    "cci_corsid IN ($rm_cors_str)");
                $db->delete('t_course_knowledge', 
                    "ck_corsid IN ($rm_cors_str)");
                $db->delete('t_course_gradeid', 
                    "cg_corsid IN ($rm_cors_str)");
                $db->delete('t_course_subjectid', 
                    "cs_corsid IN ($rm_cors_str)");
                $db->delete('t_course', "cors_id IN ($rm_cors_str)");
            }
            $db->update('t_course', array('cors_flag' => -1),
                "cors_id IN ($cors_id_str)");
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}

    /**
     * 获取课程关联考试类型 列表, 查v_course_classid视图
     * @param   string  $cors_id_str    形如1,2,3样式的课程ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function courseClassIDList(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_classid WHERE cci_corsid IN ({$cors_id_str})
ORDER BY cci_corsid, cci_classid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取课程关联考试类型 对象, 查v_course_classid视图
     * @param   string  $cors_id_str    形如1,3,4样式的课程ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[cci_corsid][cci_classid] = *
     */
    public static function courseClassIDPairs(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_classid WHERE cci_corsid IN ({$cors_id_str})
ORDER BY cci_corsid, cci_classid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['cci_corsid']]))
            {
                $arr[$v['cci_corsid']] = array();
            }
            $arr[$v['cci_corsid']][$v['cci_classid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}

    /**
     * 获取课程关联知识点 列表, 查v_course_knowledge视图
     * @param   string  $cors_id_str    形如1,2,3样式的课程ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function courseKnowledgeList(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }

        $sql = <<<EOT
SELECT * FROM v_course_knowledge WHERE ck_corsid IN ({$cors_id_str})
ORDER BY ck_corsid, ck_kid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取课程关联知识点 对象, 查v_course_knowledge视图
     * @param   string  $cors_id_str    形如1,3,4样式的课程ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[ck_corsid][ck_kid] = *
     */
    public static function courseKnowledgePairs(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }

        $sql = <<<EOT
SELECT * FROM v_course_knowledge WHERE ck_corsid IN ({$cors_id_str})
ORDER BY ck_corsid, ck_kid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['ck_corsid']]))
            {
                $arr[$v['ck_corsid']] = array();
            }
            $arr[$v['ck_corsid']][$v['ck_kid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}


    /**
     * 获取课程关联年级 列表, 查t_course_gradeid表
     * @param   string  $cors_id_str    形如1,2,3样式的课程ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function courseGradeIDList(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM t_course_gradeid WHERE cg_corsid IN ({$cors_id_str})
ORDER BY cg_corsid, cg_gradeid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取课程关联年级 对象, 查t_course_gradeid表
     * @param   string  $cors_id_str    形如1,3,4样式的课程ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[cg_corsid][cg_gradeid] = *
     */
    public static function courseGradeIDPairs(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM t_course_gradeid WHERE cg_corsid IN ({$cors_id_str})
ORDER BY cg_corsid, cg_gradeid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['cg_corsid']]))
            {
                $arr[$v['cg_corsid']] = array();
            }
            $arr[$v['cg_corsid']][$v['cg_gradeid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}


    /**
     * 获取课程关联学科 列表, 查v_course_subjectid视图
     * @param   string  $cors_id_str    形如1,2,3样式的课程ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function courseSubjectIDList(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_subjectid WHERE cs_corsid IN ({$cors_id_str})
ORDER BY cs_corsid, cs_subjectid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取课程关联学科 对象, 查v_course_subjectid视图
     * @param   string  $cors_id_str    形如1,3,4样式的课程ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[cs_corsid][cs_subjectid] = *
     */
    public static function courseSubjectIDPairs(/*string*/$cors_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cors_id_str))
        {
            throw new Exception('课程ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_subjectid WHERE cs_corsid IN ({$cors_id_str})
ORDER BY cs_corsid, cs_subjectid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['cs_corsid']]))
            {
                $arr[$v['cs_corsid']] = array();
            }
            $arr[$v['cs_corsid']][$v['cs_subjectid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}

    /*********************** 课程校区相关接口 ******************************/

    // TODO
    /** 课程校区列表,查询v_course_campus视图
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function courseCampusList(/*string*/$field = NULL,
        array $cond_param = NULL, /*int*/$page = NULL, 
        /*int*/$perpage = NULL)//{{{
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'cc_id', 'cc_corsid', 
                'tc_name', 'cc_ctfid');
            /*
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
             */
            if (isset($cond_param['cc_id'])
                && Validate::isInt($cond_param['cc_id']))
            {
                $where[] = 'cc_id = ?';
                $bind[] = $cond_param['cc_id'];
            }
            if (isset($cond_param['cc_corsid']))
            {
                if (Validate::isInt($cond_param['cc_corsid']))
                {
                    $where[] = 'cc_corsid = ?';
                    $bind[] = $cond_param['cc_corsid'];
                }
                else if (Validate::isJoinedIntStr($cond_param['cc_corsid']))
                {
                    $where[] = 'cc_corsid IN (' . $cond_param['cc_corsid'] . ')';
                }
            }
            if (isset($cond_param['tc_name'])
                && strlen($cond_param['tc_name']) > 0)
            {
                $where[] = 'cc_tcid IS NOT NULL AND tc_name LIKE ?';
                $bind[] = '%' . $cond_param['tc_name'] . '%';
            }
            if (isset($cond_param['cc_ctfid'])
                && Validate::isInt($cond_param['cc_ctfid']))
            {
                $where[] = 'cc_ctfid = ?';
                $bind[] = $cond_param['cc_ctfid'];
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_course_campus', $field, $where, 
            $bind, $order_by, $page, $perpage);

    }//}}}

    /**
     * 查询符合条件的课程校区数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function courseCampusListCount(array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::courseCampusList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条课程校区信息,从v_course_campus视图中查询
     * @param   int     $cors_id        课程ID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function courseCampusInfo(/*int*/$cc_id,
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($cc_id))
        {
            throw new Exception('课程校区ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM v_course_campus WHERE cc_id = {$cc_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}

    /**
     * 新增课程校区
     * @param   array   $param  map<string, variant>类型的参数
     *                  int         cc_corsid
     *                  int         cc_tcid
     *                  int         cc_ctfid
     *                  string      cc_classtime
     *                  date        cc_begindate
     *                  date        cc_enddate
     *                  int         cc_provid
     *                  int         cc_cityid
     *                  int         cc_areaid
     *                  int         cc_startanytime
     *                  int         cc_hours
     *                  double      cc_price
     *                  string      cc_addr
     *                  string      cc_ctcperson
     *                  string      cc_ctcphone
     *                  list<int>   teacherid_list
     * @return  int     成功则返回新增的课程校区ID,否则返回0
     */
    public static function addCourseCampus(array $param, $bUseTrans = true)//{{{
    {
        $param = Func::param_copy($param, 'cc_corsid', 'cc_tcid', 
            'cc_ctfid', 'cc_classtime', 'cc_begindate', 
            'cc_provid', 'cc_cityid', 'cc_areaid', 'cc_startanytime',
            'cc_enddate', 'cc_hours', 'cc_price', 'cc_addr', 
            'cc_ctcperson', 'cc_ctcphone',
            'teacherid_list');
        if (!Validate::isInt($param['cc_corsid']))
        {
            throw new Exception('课程ID必须为整数');
        }
        if (isset($param['cc_tcid']))
        {
            if ($param['cc_tcid'] == '')
            {
                $param['cc_tcid'] = NULL;
            }
            else if (!Validate::isInt($param['cc_tcid']))
            {
                throw new Exception('培训校区ID必须为整数');
            }
        }
        else
        {
            $param['cc_tcid'] = NULL;
        }
        if (!Validate::isInt($param['cc_ctfid']))
        {
            throw new Exception('教师来源ID必须为整数');
        }
        if (isset($param['cc_classtime']))
        {
            if ($param['cc_classtime'] == '')
            {
                $param['cc_classtime'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_classtime'], 1, 255))
            {
                throw new Exception('课程时间总长度不能超过255个字符');
            }
        }
        if (isset($param['cc_startanytime']))
        {
            if (!Validate::isInt($param['cc_startanytime']))
            {
                throw new Exception('教师来源ID必须为整数');
            }
            if ($param['cc_startanytime'])
            {
                $param['cc_startanytime'] = 1;
            }
            else
            {
                $param['cc_startanytime'] = 0;
            }
        }
        if (isset($param['cc_begindate']))
        {
            if ($param['cc_begindate'] == '')
            {
                $param['cc_begindate'] = NULL;
            }
            else if (!Validate::isDate($param['cc_begindate']))
            {
                throw new Exception('课程开始日期格式不正确');
            }
        }
        if (isset($param['cc_enddate']))
        {
            if ($param['cc_enddate'] == '')
            {
                $param['cc_enddate'] = NULL;
            }
            else if (!Validate::isDate($param['cc_enddate']))
            {
                throw new Exception('课程结束日期格式不正确');
            }
        }
        if (isset($param['cc_hours']))
        {
            if ($param['cc_hours'] == '')
            {
                $param['cc_hours'] = NULL;
            }
            else if (!Validate::isInt($param['cc_hours']))
            {
                throw new Exception('共计课时应为整数');
            }
        }
        if (isset($param['cc_price']))
        {
            if ($param['cc_price'] == '')
            {
                $param['cc_price'] = NULL;
            }
            else if (!Validate::isDigits($param['cc_price']))
            {
                throw new Exception('课程收费应为数字');
            }
        }
        if (!Validate::isInt($param['cc_provid']) 
            || !Validate::isInt($param['cc_cityid'])
            || !Validate::isInt($param['cc_areaid']))
        {
            throw new Exception('课程所在地区ID必须为整数');
        }
        if (isset($param['cc_addr']))
        {
            if ($param['cc_addr'] == '')
            {
                $param['cc_addr'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_ctcperson'], 1, 255))
            {
                throw new Exception('培训校区地址最多255个字符');
            }
        }
        if (isset($param['cc_ctcperson']))
        {
            if ($param['cc_ctcperson'] == '')
            {
                $param['cc_ctcperson'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_ctcperson'], 1, 60))
            {
                throw new Exception('培训校区联系人姓名最多60个字符');
            }
        }
        if (isset($param['cc_ctcphone']))
        {
            if ($param['cc_ctcphone'] == '')
            {
                $param['cc_ctcphone'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_ctcphone'], 1, 120))
            {
                throw new Exception('培训校区联系电话最多120个字符');
            }
        }
        $teacherid_list = array();
        if (isset($param['teacherid_list']))
        {
            if (!is_array($param['teacherid_list']))
            {
                throw new Exception('教师参数类型不正确');
            }
            $param['teacherid_list'] = array_values($param['teacherid_list']);
            $param['teacherid_list'] = array_unique($param['teacherid_list']);
            foreach ($param['teacherid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('教师ID必须为整数');
                }
            }
            $teacherid_list = $param['teacherid_list'];
            unset($param['teacherid_list']);
        }
        /*
        if (empty($teacherid_list))
        {
            throw new Exception('教师ID必须填写');
        }
         */

        $db = Fn::db();
        $cc_id = 0;
        $bOk = false;
        if ($bUseTrans)
        {
            if (!$db->beginTransaction())
            {
                return 0;
            }
        }
        $db->update('t_course', 
            array('cors_lastmodify' => date('Y-m-d H:i:s')), 
            'cors_id = ' . $param['cc_corsid']);
        if ($db->insert('t_course_campus', $param))
        {
            $cc_id = $db->lastInsertId('t_course_campus', 'cc_id');
            foreach ($teacherid_list as $v)
            {
                $db->insert('t_course_campus_teacher',
                    array('cct_ccid' => $cc_id,
                    'cct_ctid' => $v));
            }
            if ($bUseTrans)
            {
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $cc_id = 0;
                    $db->rollBack();
                }
            }
        }
        else
        {
            if ($bUseTrans)
            {
                $db->rollBack();
            }
        }
        return $cc_id;
    }//}}}

    /**
     * 修改课程校区(所属课程不可修改)
     * @param   array   $param  map<string, variant>类型的参数
     *                  int         cc_id
     *                  int         cc_tcid
     *                  int         cc_ctfid
     *                  string      cc_classtime
     *                  date        cc_begindate
     *                  date        cc_enddate
     *                  int         cc_provid
     *                  int         cc_cityid
     *                  int         cc_areaid
     *                  int         cc_startanytime
     *                  int         cc_hours
     *                  double      cc_price
     *                  string      cc_addr
     *                  string      cc_ctcperson
     *                  string      cc_ctcphone
     *                  list<int>   teacherid_list
     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setCourseCampus(array $param, $bUseTrans = true)//{{{
    {
        // cc_corsid不能改变
        $param = Func::param_copy($param, 'cc_id', 'cc_tcid', 
            'cc_ctfid', 'cc_classtime', 'cc_begindate', 'cc_enddate',
            'cc_provid', 'cc_cityid', 'cc_areaid', 'cc_startanytime',
            'cc_hours', 'cc_price', 'cc_addr', 'cc_ctcperson', 
            'cc_ctcphone', 'teacherid_list');
        if (!isset($param['cc_id']) || !Validate::isInt($param['cc_id']))
        {
            throw new Exception('课程校区ID不正确');
        }
        if (count($param) == 1)
        {
            throw new Exception('没有任何要修改的内容');
        }
        if (isset($param['cc_tcid']))
        {
            if ($param['cc_tcid'] == '')
            {
                $param['cc_tcid'] = NULL;
            }
            else if (!Validate::isInt($param['cc_tcid']))
            {
                throw new Exception('培训校区ID必须为整数');
            }
        }
        if (isset($param['cc_ctfid']) 
            && !Validate::isInt($param['cc_ctfid']))
        {
            throw new Exception('教师来源ID必须为整数');
        }
        if (isset($param['cc_classtime']))
        {
            if ($param['cc_classtime'] == '')
            {
                $param['cc_classtime'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_classtime'], 1, 255))
            {
                throw new Exception('课程时间总长度不能超过255个字符');
            }
        }
        if (isset($param['cc_startanytime']))
        {
            if (!Validate::isInt($param['cc_startanytime']))
            {
                throw new Exception('教师来源ID必须为整数');
            }
            if ($param['cc_startanytime'])
            {
                $param['cc_startanytime'] = 1;
            }
            else
            {
                $param['cc_startanytime'] = 0;
            }
        }

        if (isset($param['cc_begindate']))
        {
            if ($param['cc_begindate'] == '')
            {
                $param['cc_begindate'] = NULL;
            }
            else if (!Validate::isDate($param['cc_begindate']))
            {
                throw new Exception('课程开始日期格式不正确');
            }
        }
        if (isset($param['cc_enddate']))
        {
            if ($param['cc_enddate'] == '')
            {
                $param['cc_enddate'] = NULL;
            }
            else if (!Validate::isDate($param['cc_enddate']))
            {
                throw new Exception('课程结束日期格式不正确');
            }
        }
        if (isset($param['cc_hours']))
        {
            if ($param['cc_hours'] == '')
            {
                $param['cc_hours'] = NULL;
            }
            else if (!Validate::isInt($param['cc_hours']))
            {
                throw new Exception('共计课时应为整数');
            }
        }
        if (isset($param['cc_price']))
        {
            if ($param['cc_price'] == '')
            {
                $param['cc_price'] = NULL;
            }
            else if (!Validate::isDigits($param['cc_price']))
            {
                throw new Exception('课程收费应为数字');
            }
        }
        if ((isset($param['cc_provid']) 
                && !Validate::isInt($param['cc_provid']))
            || (isset($param['cc_cityid'])
                && !Validate::isInt($param['cc_cityid']))
            || (isset($param['cc_areaid'])
                && !Validate::isInt($param['cc_areaid'])))
        {
            throw new Exception('课程所在地区ID必须为整数');
        }

        if (isset($param['cc_addr']))
        {
            if ($param['cc_addr'] == '')
            {
                $param['cc_addr'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_addr'], 1, 255))
            {
                throw new Exception('培训校区地址最多255个字符');
            }
        }
        if (isset($param['cc_ctcperson']))
        {
            if ($param['cc_ctcperson'] == '')
            {
                $param['cc_ctcperson'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_ctcperson'], 1, 60))
            {
                throw new Exception('培训校区联系人姓名最多60个字符');
            }
        }
        if (isset($param['cc_ctcphone']))
        {
            if ($param['cc_ctcphone'] == '')
            {
                $param['cc_ctcphone'] = NULL;
            }
            else if (!Validate::isStringLength($param['cc_ctcphone'], 1, 120))
            {
                throw new Exception('培训校区联系电话最多120个字符');
            }
        }

        $teacherid_list = NULL;
        if (isset($param['teacherid_list']))
        {
            if (!is_array($param['teacherid_list']))
            {
                throw new Exception('教师参数类型不正确');
            }
            $param['teacherid_list'] = array_values($param['teacherid_list']);
            $param['teacherid_list'] = array_unique($param['teacherid_list']);
            foreach ($param['teacherid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('教师ID必须为整数');
                }
            }
            $teacherid_list = $param['teacherid_list'];
            unset($param['teacherid_list']);
            /*
            if (empty($teacherid_list))
            {
                throw new Exception('教师ID必须填写');
            }
             */
        }


        $cc_id = $param['cc_id'];
        unset($param['cc_id']);
        $db = Fn::db();
        $bOk = false;
        if ($bUseTrans)
        {
            if (!$db->beginTransaction())
            {
                return 0;
            }
        }
        $db->update('t_course', 
            array('cors_lastmodify' => date('Y-m-d H:i:s')), 
            "cors_id IN (SELECT cc_corsid FROM t_course_campus WHERE cc_id = $cc_id)");

        $db->update('t_course_campus', $param, 'cc_id = ' . $cc_id);
        if (is_array($teacherid_list))
        {
            $db->delete('t_course_campus_teacher', 'cct_ccid = ' . $cc_id);
            foreach ($teacherid_list as $v)
            {
                $db->insert('t_course_campus_teacher',
                    array('cct_ccid' => $cc_id,
                    'cct_ctid' => $v));
            }
        }
        if ($bUseTrans)
        {
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}

    // TODO 还可能有其它未知的表要删除
    /**
     * 删除课程校区(已使用过的不可真删,目前没有其他表使用到)
     * @param   string      $cc_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非0,否则返回0
     */
    public static function removeCourseCampus(/*string*/$cc_id_str, $bUseTrans = true)//{{{
    {
        if (!Validate::isJoinedIntStr($cc_id_str))
        {
            throw new Exception('课程校区ID列表格式不正确,'
                . '应为英文逗号分隔开的ID字符串');
        }
        $db = Fn::db();
        $bOk = false;
        if ($bUseTrans)
        {
            if (!$db->beginTransaction())
            {
                return 0;
            }
        }
        $db->delete('t_course_campus_teacher', "cct_ccid IN ($cc_id_str)");
        $db->delete('t_course_campus', "cc_id IN ($cc_id_str)");
        if ($bUseTrans)
        {
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}

    /**
     * 获取课程校区关联教师 列表, 查v_course_campus_teacher视图
     * @param   string  $cc_id_str    形如1,2,3样式的课程校区ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function courseCampusTeacherList(/*string*/$cc_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cc_id_str))
        {
            throw new Exception('课程校区ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_campus_teacher WHERE cct_ccid IN ({$cc_id_str})
ORDER BY cct_ccid,cct_ctid 
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取课程校区关联老师 对象, 查v_course_campus_teacher视图
     * @param   string  $cc_id_str    形如1,3,4样式的课程ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[cct_ccid][cct_ctid] = *
     */
    public static function courseCampusTeacherPairs(/*string*/$cc_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($cc_id_str))
        {
            throw new Exception('课程校区ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_course_campus_teacher WHERE cct_ccid IN ({$cc_id_str})
ORDER BY cct_ccid,cct_ctid 
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['cct_ccid']]))
            {
                $arr[$v['cct_ccid']] = array();
            }
            $arr[$v['cct_ccid']][$v['cct_ctid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}

    /****************** 关于是否课程推送的接口 ****************************/

    // TODO
    /** 课程推送列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function coursePushList(/*string*/$field = NULL,
        array $cond_param = NULL, /*int*/$page = NULL, 
        /*int*/$perpage = NULL)//{{{
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'cp_exampid', 
                'cp_stuuid');
            /*
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
             */
            if (isset($cond_param['cp_exampid'])
                && Validate::isInt($cond_param['cp_exampid']))
            {
                $where[] = 'cp_exampid = ?';
                $bind[] = $cond_param['cp_exampid'];
            }
            if (isset($cond_param['cp_stuuid'])
                && Validate::isInt($cond_param['cp_stuuid']))
            {
                $where[] = 'cp_stuuid = ?';
                $bind[] = $cond_param['cp_stuuid'];
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_course_push', $field, $where, 
            $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的课程推送数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function coursePushListCount(array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::coursePushList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条课程推送,从v_course_push视图中查询
     * @param   int     $exam_pid       期次ID
     * @param   int     $place_id       场次ID
     * @param   int     $stu_uid        学生UID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function coursePushInfo(/*int*/$exam_pid, /*int*/ $place_id, 
        /*int*/$stu_uid, /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($exam_pid))
        {
            throw new Exception('期次ID类型不正确');
        }
        if (!Validate::isInt($place_id))
        {
            throw new Exception('场次UID类型不正确');
        }
        if (!Validate::isInt($stu_uid))
        {
            throw new Exception('学生UID类型不正确');
        }

        $sql = <<<EOT
SELECT {$field} FROM v_course_push 
WHERE cp_exampid = {$exam_pid} AND cp_examplaceid = {$place_id} 
    AND cp_stuuid = {$stu_uid}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 增加课程推送
     * @param   int     $exam_pid        期次ID
     * @param   int     $place_id        场次ID
     * @param   array   $stu_uid_list   list<int>类型的学生UID列表
     * @return  int 成功返回非0,否则返回0
     */
    public static function addCoursePush(/*int*/$exam_pid, /*int*/$place_id, array $stu_uid_list)//{{{
    {
        if (!Validate::isInt($exam_pid))
        {
            throw new Exception('考试期次ID类型不正确');
        }
        if (!Validate::isInt($place_id))
        {
            throw new Exception('考试场次ID类型不正确');
        }
        foreach ($stu_uid_list as $v)
        {
            if (!Validate::isInt($v))
            {
                throw new Exception('学生ID必须为整数');
            }
        }
        $stu_uid_list = array_unique($stu_uid_list);
        if (empty($stu_uid_list))
        {
            throw new Exception('学生ID列表不可为空');
        }
        $stu_uid_str = implode(',', $stu_uid_list);

        $db = Fn::db();
        $sql = <<<EOT
SELECT cp_stuuid FROM t_course_push 
WHERE cp_exampid = {$exam_pid} AND cp_examplaceid = {$place_id} AND cp_stuuid IN ({$stu_uid_str})
EOT;
        $cp_stuuid_exist = $db->fetchCol($sql);
        if (empty($cp_stuuid_exist))
        {
            $cp_stuuid_exist = array();
        }
        $stu_uid_list = array_diff($stu_uid_list, $cp_stuuid_exist);
        if (empty($stu_uid_list))
        {
            return 1;
        }
        $bOk = false;
        if ($db->beginTransaction())
        {
            $addtime = date('Y-m-d H:i:s');
            foreach ($stu_uid_list as $v)
            {
                $db->insert('t_course_push', array('cp_stuuid' => $v,
                    'cp_exampid' => $exam_pid, 
                    'cp_examplaceid' => $place_id,
                    'cp_addtime' => $addtime));
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}


    /**
     * 删除课程推送
     * @param   int     $exam_pid       期次ID
     * @param   int     $place_id       场次ID
     * @param   array   $stu_uid_list   list<int>类型的学生UID列表
     * @return  int     成功则返回非0,否则返回0
     */
    public static function removeCoursePush(/*int*/$exam_pid, /*int*/$place_id, 
            array $stu_uid_list)//{{{
    {
        if (!Validate::isInt($exam_pid))
        {
            throw new Exception('考试期次ID类型不正确');
        }
        if (!Validate::isInt($place_id))
        {
            throw new Exception('考试场次ID类型不正确');
        }
        foreach ($stu_uid_list as $v)
        {
            if (!Validate::isInt($v))
            {
                throw new Exception('学生ID必须为整数');
            }
        }
        $stu_uid_list = array_unique($stu_uid_list);
        if (empty($stu_uid_list))
        {
            throw new Exception('学生ID列表不可为空');
        }

        $stu_uid_str = implode(',', $stu_uid_list);
        $db = Fn::db();
        $v = $db->delete('t_course_push', 
            "cp_exampid = $exam_pid AND cp_examplaceid = {$place_id} "
            . "AND cp_stuuid IN ($stu_uid_str)");
        return $v ? 1 : 0;
    }//}}}
    
    /**
     * 课程所在校区地址
     * @param   int     $cors_id    课程ID
     * @return  array   map<string, variant>或者返回null
     */
    public static function courseRegion($cors_id)
    {
        if (!$cors_id)
        {
            return array();
        }
        
        $sql = "SELECT DISTINCT region_id, region_name, parent_id FROM rd_region r
                LEFT JOIN t_course_campus cc ON r.region_id = cc.cc_provid  
                    OR r.region_id = cc.cc_cityid OR r.region_id = cc.cc_areaid
                WHERE cc_corsid = $cors_id ORDER BY region_id ASC, parent_id ASC";
        
        return Fn::db()->fetchAll($sql);
    }
}
