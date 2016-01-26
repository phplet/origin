<?php
/**
 * 培训教师相关 CTeacherModel(Course Teacher)
 * @file    CTeacher.php
 * @author  BJP
 * @final   2015-07-28
 */
class CTeacherModel
{
    /************************** 课程教师相关接口 ****************************/
    // TODO
    /** 教师列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function CTeacherList(/*string*/$field = NULL,
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

            $cond_param = Func::param_copy($cond_param, 'ct_id', 'ct_name',
                'ct_contact', 'grade_id_str', 'subject_id_str');
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
            if (isset($cond_param['ct_contact'])
                && strlen($cond_param['ct_contact']) > 0)
            {
                $where[] = 'ct_contact IS NOT NULL AND ct_contact LIKE ?';
                $bind[] = '%' . $cond_param['ct_contact'] . '%';
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
        else
        {
            $order_by = NULL;
        }
        
        $where[] = "ct_id NOT IN (SELECT scht_ctid FROM t_cteacher_school)";
        
        return Fn::db()->fetchList('t_cteacher', $field, $where, 
            $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的教师数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function CTeacherListCount(array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::CTeacherList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条教师信息,从t_cteacher中查询
     * @param   int     $ct_id        教师ID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function CTeacherInfo(/*int*/$ct_id,
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($ct_id))
        {
            throw new Exception('教师ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_cteacher WHERE ct_id = {$ct_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}

    /**
     * 新增教师
     * @param   array   map<string, variant>类型的参数,
     *                  string      ct_name 
     *                  string      ct_contact
     *                  int         ct_flag
     *                  list<int>   gradeid_list
     *                  list<int>   subjectid_list
     *                  list<int>   cct_ccid_list
     * @return  int     返回新增的教师ID,否则返回0 
     */
    public static function addCTeacher(array $param)//{{{
    {
        $param = Func::param_copy($param, 'ct_name', 'ct_contact', 
            'ct_flag', 'gradeid_list', 'subjectid_list', 'cct_ccid_list', 
            'ct_memo');
        if (!Validate::isStringLength($param['ct_name'], 1, 30))
        {
            throw new Exception('教师名称不能为空且长度最多30个字符');
        }
        if (!Validate::isInt($param['ct_flag']))
        {
            throw new Exception('教师状态标志必须为整数');
        }
        if (isset($param['ct_contact']))
        {
            if ($param['ct_contact'] == '')
            {
                $param['ct_contact'] = NULL;
            }
            else if (!Validate::isStringLength($param['ct_contact'], 1, 255))
            {
                throw new Exception('教师联系方式最多255个字符');
            }
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

        $ccid_list = array();
        if (isset($param['cct_ccid_list']))
        {
            if (!is_array($param['cct_ccid_list']))
            {
                throw new Exception('课程参数类型不正确');
            }
            $param['cct_ccid_list'] = array_values($param['cct_ccid_list']);
            $param['cct_ccid_list'] = array_unique($param['cct_ccid_list']);
            foreach ($param['cct_ccid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('课程ID必须为整数');
                }
            }
            $ccid_list = $param['cct_ccid_list'];
            unset($param['cct_ccid_list']);
        }

        $db = Fn::db();
        $ct_id = 0;
        if ($db->beginTransaction())
        {
            $db->insert('t_cteacher', $param);
            $ct_id = $db->lastInsertId('t_cteacher', 'ct_id');
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
            foreach ($ccid_list as $v)
            {
                $db->insert('t_course_campus_teacher', 
                    array('cct_ctid' => $ct_id, 'cct_ccid' => $v));
            }

            if (!$db->commit())
            {
                $db->rollBack();
                $ct_id = 0;
            }
        }
        return $ct_id;
    }//}}}

    /**
     * 修改教师
     * @param   array   map<string, variant>类型的参数
     *                  int         ct_id
     *                  string      ct_name 
     *                  string      ct_contact
     *                  int         ct_flag
     *                  string      ct_memo
     *                  list<int>   gradeid_list
     *                  list<int>   subjectid_list
     *                  list<int>   cct_ccid_list
     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setCTeacher(array $param)//{{{
    {
        $param = Func::param_copy($param, 'ct_id', 'ct_name', 
            'ct_contact', 'ct_flag', 'gradeid_list', 'subjectid_list', 
            'cct_ccid_list', 'ct_memo');
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
        if (isset($param['ct_contact']))
        {
            if ($param['ct_contact'] == '')
            {
                $param['ct_contact'] = NULL;
            }
            else if (!Validate::isStringLength($param['ct_contact'], 1, 255))
            {
                throw new Exception('教师联系方式最多255个字符');
            }
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


        $ccid_list = NULL;
        if (isset($param['cct_ccid_list']))
        {
            if (!is_array($param['cct_ccid_list']))
            {
                throw new Exception('课程参数类型不正确');
            }
            $param['cct_ccid_list'] = array_values($param['cct_ccid_list']);
            $param['cct_ccid_list'] = array_unique($param['cct_ccid_list']);
            foreach ($param['cct_ccid_list'] as $v)
            {
                if (!Validate::isInt($v))
                {
                    throw new Exception('课程ID必须为整数');
                }
            }
            $ccid_list = $param['cct_ccid_list'];
            unset($param['cct_ccid_list']);
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
            if (is_array($ccid_list))
            {
                $db->delete('t_course_campus_teacher', 'cct_ctid = ' . $ct_id);
                foreach ($ccid_list as $v)
                {
                    $db->insert('t_course_campus_teacher', 
                        array('cct_ctid' => $ct_id, 'cct_ccid' => $v));
                }
            }

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
     * 删除教师,若ct_flag > -1则为假删，否则为真删(已使用过的不可真删)
     * @param   string      $ct_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非0,否则返回0
     */
    public static function removeCTeacher(/*string*/$ct_id_str)//{{{
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
            // 这里计算出其它地方使用到的现在需要真删的ID,利用差集
            // 计算出能真删的ID
            $rm_ct_str = implode(',', $rm_ct_ids);
            $sql = <<<EOT
SELECT DISTINCT cct_ctid 
FROM t_course_campus_teacher WHERE cct_ctid IN ({$rm_ct_str})
EOT;
            $nrm_ct_ids = $db->fetchCol($sql);  // 不可真删的ID

            $rm_ct_ids = array_diff($rm_ct_ids, $nrm_ct_ids);
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
                $db->delete('t_cteacher', "ct_id IN ($rm_ct_str)");
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
    }//}}}

    /**
     * 获取教师关联年级 列表, 查t_cteacher_gradeid表
     * @param   string  $ct_id_str    形如1,2,3样式的教师ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function CTeacherGradeIDList(/*string*/$ct_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ct_id_str))
        {
            throw new Exception('教师ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM t_cteacher_gradeid WHERE ctg_ctid IN ({$ct_id_str})
ORDER BY ctg_ctid, ctg_gradeid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取教师关联年级 对象, 查t_cteacher_gradeid表
     * @param   string  $ct_id_str    形如1,3,4样式的教师ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[ctg_ctid][ctg_gradeid] = *
     */
    public static function CTeacherGradeIDPairs(/*string*/$ct_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ct_id_str))
        {
            throw new Exception('教师ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM t_cteacher_gradeid WHERE ctg_ctid IN ({$ct_id_str})
ORDER BY ctg_ctid, ctg_gradeid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['ctg_ctid']]))
            {
                $arr[$v['ctg_ctid']] = array();
            }
            $arr[$v['ctg_ctid']][$v['ctg_gradeid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}


    /**
     * 获取教师关联学科 列表, 查v_cteacher_subjectid视图
     * @param   string  $ct_id_str    形如1,2,3样式的教师ID字符串
     * @return  array   list<map<string, variant>>类型
     */
    public static function CTeacherSubjectIDList(/*string*/$ct_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ct_id_str))
        {
            throw new Exception('教师ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_cteacher_subjectid WHERE cts_ctid IN ({$ct_id_str})
ORDER BY cts_ctid, cts_subjectid
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 获取教师关联学科 对象, 查v_cteacher_subjectid视图
     * @param   string  $ct_id_str    形如1,3,4样式的教师ID字符串
     * @return  array   map<int, map<int, map<string, varaint>>> 
     *                     $arr[cts_ctid][cts_subjectid] = *
     */
    public static function CTeacherSubjectIDPairs(/*string*/$ct_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ct_id_str))
        {
            throw new Exception('教师ID列表应为形如1,2,3样式的ID列表字符串');
        }
        $sql = <<<EOT
SELECT * FROM v_cteacher_subjectid WHERE cts_ctid IN ({$ct_id_str})
ORDER BY cts_ctid, cts_subjectid
EOT;
        $rows = Fn::db()->fetchAll($sql);
        $arr = array();
        foreach ($rows as $v)
        {
            if (!isset($arr[$v['cts_ctid']]))
            {
                $arr[$v['cts_ctid']] = array();
            }
            $arr[$v['cts_ctid']][$v['cts_subjectid']] = $v;
        }
        unset($rows);
        return $arr;
    }//}}}
}