<?php
/**
 * 培训机构相关 TrainingInstitutionmodel
 * @file    TrainingInstitution.php
 * @author  BJP
 * @final   2015-07-21
 */
class TrainingInstitutionModel
{
    /***********************  培训机构类型接口  ****************************/
    /**
     * 返回培训机构类型表
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function trainingInstitutionTypeList($field = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$field} FROM t_training_institution_type ORDER BY tit_id
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 返回培训机构类型表
     * @return  array   map<int, string>类型数据
     */
    public static function trainingInstitutionTypePairs()//{{{
    {
        $sql = <<<EOT
SELECT tit_id, tit_name FROM t_training_institution_type ORDER BY tit_id
EOT;
        return Fn::db()->fetchPairs($sql);
    }//}}}


    /**
     * 返回一条培训机构类型信息
     * @param   int     $tit_id     
     * @param   string  $field  = '*'   获取字段
     * @return  array   mpa<string, variant>类型数据
     */
    public static function trainingInstitutionTypeInfo(/*int*/$tit_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($tit_id))
        {
            throw new Exception('培训机构类型ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_training_institution_type WHERE tit_id = {$tit_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 新增培训机构类型
     * @param   array   map<string, variant>类型的参数,
     *                  int         tit_id
     *                  int         tit_name
     * @return  boolean
     */
    public static function addTrainingInstitutionType(array $param)//{{{
    {
        $param = Func::param_copy($param, 'tit_id', 'tit_name');
        if (!Validate::isInt($param['tit_id']))
        {
            throw new Exception('错误的培训机构类型ID');
        }
        if (!Validate::isStringLength($param['tit_name'], 1, 30))
        {
            throw new Exception('培训机构类型名称不可为空且要小于30个字');
        }

        $db = Fn::db();
        $sql = <<<EOT
SELECT tit_id FROM t_training_institution_type WHERE tit_id = ? OR tit_name = ?
EOT;
        $row = $db->fetchRow($sql, array($param['tit_id'], $param['tit_name']));
        if ($row)
        {
            throw new Exception('重复的ID或名称记录');
        }
        $v = $db->insert('t_training_institution_type', $param);
        return $v > 0;
    }//}}}

    /**
     * 修改培训机构优先级
     * @param   int     $tit_id     优先级旧ID
     * @param   array   map<string, variant>类型的参数,
     *                  int         tit_id
     *                  int         tit_name
     * @return  boolean
     */
    public static function setTrainingInstitutionType(/*int*/$tit_id, array $param)//{{{
    {
        $param = Func::param_copy($param, 'tit_id', 'tit_name');
        if (!Validate::isInt($tit_id))
        {
            throw new Exception('错误的培训机构类型原ID');
        }
        if (!Validate::isInt($param['tit_id']))
        {
            throw new Exception('错误的培训机构类型ID');
        }
        if (!Validate::isStringLength($param['tit_name'], 1, 30))
        {
            throw new Exception('培训机构类型名称不可为空且要小于30个字');
        }

        $db = Fn::db();

        $sql = <<<EOT
SELECT tit_id, tit_name FROM t_training_institution_type WHERE tit_id = ?
EOT;
        $tit_info = $db->fetchRow($sql, $tit_id);
        if (!$tit_info)
        {
            throw new Exception('培训机构类型ID(' . $tit_id . ')不存在');
        }

        if ($tit_id == $param['tit_id'])
        {
            if (trim($tit_info['tit_name']) == $param['tit_name'])
            {
                throw new Exception('培训机构类型名称没有改变');
            }
            $sql = <<<EOT
SELECT tit_id FROM t_training_institution_type 
WHERE tit_id <> ? AND tit_name = ?
EOT;
            $row = $db->fetchRow($sql, 
                array($param['tit_id'], $param['tit_name']));
            if ($row)
            {
                throw new Exception('重复的培训机构类型名称记录');
            }
            $v = $db->update('t_training_institution_type', 
                array('tit_name' => $param['tit_name']),
                'tit_id = ' . $tit_id);
        }
        else
        {
            $sql = <<<EOT
SELECT tit_id FROM t_training_institution_type 
WHERE (tit_id <> ? AND (tit_id = ? OR tit_name = ?))
EOT;
            $row = $db->fetchRow($sql, 
                array($tit_id, $param['tit_id'], $param['tit_name']));
            if ($row)
            {
                throw new Exception('重复的培训机构类型名称记录');
            }
            $v = 0;
            if ($db->beginTransaction())
            {
                $db->update('t_training_institution',
                    array('ti_typeid' => $param['tit_id']),
                    "ti_typeid = $tit_id");
                $v = $db->update('t_training_institution_type',
                    $param, 'tit_id = ' . $tit_id);
                if (!$db->commit())
                {
                    $db->rollBack();
                }
            }
        }
        return $v > 0;
    }//}}}

    /**
     * 删除培训机构类型
     * @param   string  $tit_id_str 形如1,3,4样式的ID列表字符串
     * @return  boolean
     */
    public static function removeTrainingInstitutionType(/*string*/$tit_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($tit_id_str))
        {
            throw new Exception('非法的培训机构类型ID参数字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT ti_id FROM t_training_institution WHERE ti_typeid IN ({$tit_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('培训机构中有用到了要删除的类型ID,不可删除');
        }

        $v = $db->delete('t_training_institution_type', 
                    "tit_id IN ($tit_id_str)");
        return $v > 0;
    }//}}}

    /***********************  培训机构优先级接口  **************************/
    /**
     * 返回培训机构优先级表
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function trainingInstitutionPriTypeList($field = '*')//{{{
    {
        $sql = <<<EOT
SELECT {$field} FROM t_training_institution_pritype ORDER BY tipt_id
EOT;
        return Fn::db()->fetchAll($sql);
    }//}}}

    /**
     * 返回培训机构优先级表
     * @return  array   map<int, string>类型数据
     */
    public static function trainingInstitutionPriTypePairs()//{{{
    {
        $sql = <<<EOT
SELECT tipt_id, tipt_name FROM t_training_institution_pritype ORDER BY tipt_id
EOT;
        return Fn::db()->fetchPairs($sql);
    }//}}}

    /**
     * 返回一条培训机构优先类型信息
     * @param   int     $tipt_id     
     * @param   string  $field  = '*'   获取字段
     * @return  array   mpa<string, variant>类型数据
     */
    public static function trainingInstitutionPriTypeInfo(/*int*/$tipt_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($tipt_id))
        {
            throw new Exception('培训机构优先类型ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM t_training_institution_pritype WHERE tipt_id = {$tipt_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}


    /**
     * 新增培训机构优先级
     * @param   array   map<string, variant>类型的参数,
     *                  int         tipt_id
     *                  int         tipt_name
     * @return  boolean
     */
    public static function addTrainingInstitutionPriType(array $param)//{{{
    {
        $param = Func::param_copy($param, 'tipt_id', 'tipt_name');
        if (!Validate::isInt($param['tipt_id']))
        {
            throw new Exception('错误的培训机构优先级ID');
        }
        if (!Validate::isStringLength($param['tipt_name'], 1, 30))
        {
            throw new Exception('培训机构优先级名称不可为空且要小于30个字');
        }

        $db = Fn::db();
        $sql = <<<EOT
SELECT tipt_id FROM t_training_institution_pritype WHERE tipt_id = ? OR tipt_name = ?
EOT;
        $row = $db->fetchRow($sql, array($param['tipt_id'], $param['tipt_name']));
        if ($row)
        {
            throw new Exception('重复的ID或名称记录');
        }
        $v = $db->insert('t_training_institution_pritype', $param);
        return $v > 0;
    }//}}}

    /**
     * 修改培训机构优先级
     * @param   int     $tipt_id        旧ID
     * @param   array   map<string, variant>类型的参数,
     *                  int         tipt_id
     *                  int         tipt_name
     * @return  boolean
     */
    public static function setTrainingInstitutionPriType(/*int*/$tipt_id, 
        array $param)//{{{
    {
        $param = Func::param_copy($param, 'tipt_id', 'tipt_name');
        if (!Validate::isInt($tipt_id))
        {
            throw new Exception('错误的培训机构优先级原ID');
        }
        if (!Validate::isInt($param['tipt_id']))
        {
            throw new Exception('错误的培训机构优先级ID');
        }
        if (!Validate::isStringLength($param['tipt_name'], 1, 30))
        {
            throw new Exception('培训机构优先级名称不可为空且要小于30个字');
        }

        $db = Fn::db();

        $sql = <<<EOT
SELECT tipt_id, tipt_name FROM t_training_institution_pritype WHERE tipt_id = ?
EOT;
        $tipt_info = $db->fetchRow($sql, $tipt_id);
        if (!$tipt_info)
        {
            throw new Exception('培训机构优先级ID(' . $tipt_id . ')不存在');
        }

        if ($tipt_id == $param['tipt_id'])
        {
            if (trim($tipt_info['tipt_name']) == $param['tipt_name'])
            {
                throw new Exception('培训机构优先级名称没有改变');
            }
            $sql = <<<EOT
SELECT tipt_id FROM t_training_institution_pritype 
WHERE tipt_id <> ? AND tipt_name = ?
EOT;
            $row = $db->fetchRow($sql, 
                array($param['tipt_id'], $param['tipt_name']));
            if ($row)
            {
                throw new Exception('重复的培训机构优先级名称记录');
            }
            $v = $db->update('t_training_institution_pritype', 
                array('tipt_name' => $param['tipt_name']),
                'tipt_id = ' . $tipt_id);
        }
        else
        {
            $sql = <<<EOT
SELECT tipt_id FROM t_training_institution_pritype 
WHERE (tipt_id <> ? AND (tipt_id = ? OR tipt_name = ?))
EOT;
            $row = $db->fetchRow($sql, 
                array($tipt_id, $param['tipt_id'], $param['tipt_name']));
            if ($row)
            {
                throw new Exception('重复的培训机构优先级名称记录');
            }
            $v = 0;
            if ($db->beginTransaction())
            {
                $db->update('t_training_institution',
                    array('ti_priid' => $param['tipt_id']),
                    "ti_priid = $tipt_id");
                $v = $db->update('t_training_institution_pritype',
                    $param, 'tipt_id = ' . $tipt_id);
                if (!$db->commit())
                {
                    $db->rollBack();
                }
            }
        }
        return $v > 0;
    }//}}}

    /**
     * 删除培训机构优先级
     * @param   string  $tipt_id_str    形如1,3,4样式的ID列表字符串
     * @return  boolean
     */
    public static function removeTrainingInstitutionPriType(
                /*string*/$tipt_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($tipt_id_str))
        {
            throw new Exception('非法的培训机构优先级ID参数字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT ti_id FROM t_training_institution WHERE ti_priid IN ({$tipt_id_str})
EOT;
        $v = $db->fetchRow($sql);
        if ($v)
        {
            throw new Exception('培训机构中有用到了要删除的优先级ID,不可删除');
        }
        $v = $db->delete('t_training_institution_pritype', 
                    "tipt_id IN ($tipt_id_str)");
        return $v > 0;
    }//}}}

    /************************* 培训机构接口  *******************************/
    /**
     * TODO
     * 培训机构列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function trainingInstitutionList(/*string*/ $field = NULL, 
        array $cond_param = NULL, /*int*/ $page = NULL, 
        /*int*/ $perpage = NULL)//{{{
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'ti_name', 'ti_typeid',
                'ti_provid', 'ti_cityid', 'ti_areaid');
            if (isset($cond_param['ti_provid']) 
                && Validate::isInt($cond_param['ti_provid'])
                && $cond_param['ti_provid'] > 0)
            {
                $where[] = 'ti_provid = ' . $cond_param['ti_provid'];
            }
            if (isset($cond_param['ti_cityid']) 
                && Validate::isInt($cond_param['ti_cityid'])
                && $cond_param['ti_cityid'] > 0)
            {
                $where[] = 'ti_cityid = ' . $cond_param['ti_cityid'];
            }
            if (isset($cond_param['ti_areaid']) 
                && Validate::isInt($cond_param['ti_areaid'])
                && $cond_param['ti_areaid'] > 0)
            {
                $where[] = 'ti_areaid = ' . $cond_param['ti_areaid'];
            }
            /*
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
            if (isset($cond_param['ti_name'])
                && strlen($cond_param['ti_name']) > 0)
            {
                $where[] = 'ti_name LIKE ?';
                $bind[] = '%' . $cond_param['ti_name'] . '%';
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_training_institution', $field, $where, 
            $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的培训机构数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function trainingInstitutionListCount(
        array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::trainingInstitutionList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条培训机构信息,从v_training_institution视图中查询
     * @param   int     $ti_id          培训机构ID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function trainingInstitutionInfo(/*int*/$ti_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($ti_id))
        {
            throw new Exception('培训机构ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM v_training_institution WHERE ti_id = {$ti_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}

    /**
     * 新增培训机构
     * @param   array   map<string, variant>类型的参数
     *                  string      ti_name
     *                  int         ti_typeid
     *                  int         ti_flag
     *                  int         ti_priid
     *                  int         ti_provid
     *                  int         ti_cityid
     *                  int         ti_areaid
     *                  string      ti_addr
     *                  string      ti_url
     *                  int         ti_stumax
     *                  int         ti_reputation
     *                  int         ti_cooperation
     *                  int         ti_cooperation_addfreqday
     *                  int         ti_cooperation_addinc
     *                  date        ti_cooperation_addenddate
     * @return  int     返回新增的培训机构ID,否则返回0 
     */
    public static function addTrainingInstitution(array $param)//{{{
    {
        $param = Func::param_copy($param, 'ti_name', 'ti_typeid', 'ti_flag',
            'ti_priid', 'ti_provid', 'ti_cityid', 'ti_areaid', 'ti_addr',
            'ti_url', 'ti_stumax', 'ti_reputation', 'ti_cooperation',
            'ti_cooperation_addinc',
            'ti_cooperation_addfreqday', 'ti_cooperation_addenddate');

        if (!Validate::isStringLength($param['ti_name'], 1, 60))
        {
            throw new Exception('培训机构名称不能为空且长度最多60个字符');
        }
        if (!Validate::isInt($param['ti_typeid']))
        {
            throw new Exception('培训机构类型ID必须为整数');
        }
        if (!Validate::isInt($param['ti_flag']))
        {
            throw new Exception('培训机构状态标志必须为整数');
        }
        if (!Validate::isInt($param['ti_priid']))
        {
            throw new Exception('培训机构优先级ID必须为整数');
        }
        if (!Validate::isInt($param['ti_provid']) 
            || !Validate::isInt($param['ti_cityid'])
            || !Validate::isInt($param['ti_areaid']))
        {
            throw new Exception('培训机构所在地区ID必须为整数');
        }
        if (isset($param['ti_addr']))
        {
            if ($param['ti_addr'] == '')
            {
                $param['ti_addr'] = NULL;
            }
            else if (!Validate::isStringLength($param['ti_addr'], 1, 255))
            {
                throw new Exception('培训机构地址长度最多为255个字符');
            }
        }
        if (isset($param['ti_url']))
        {
            if ($param['ti_url'] == '')
            {
                $param['ti_url'] = NULL;
            }
            else if (!Validate::isStringLength($param['ti_url'], 1, 255))
            {
                throw new Exception('培训机构网址长度最多为255个字符');
            }
        }
        if (isset($param['ti_stumax'])
            && !Validate::isInt($param['ti_stumax']))
        {
            throw new Exception('培训机构学员人数必须为整数');
        }
        if (isset($param['ti_reputation']) 
            && !Validate::isInt($param['ti_reputation']))
        {
            throw new Exception('培训机构声誉值必须为整数');
        }
        if (isset($param['ti_cooperation']))
        {
            if ($param['ti_cooperation'] == '')
            {
                $param['ti_cooperation'] = 0;
            }
            else if (!Validate::isInt($param['ti_cooperation']))
            {
                throw new Exception('合作度不正确');
            }
        }
        else
        {
            $param['ti_cooperation'] = 0;
        }
        if (isset($param['ti_cooperation_addfreqday']))
        {
            if ($param['ti_cooperation_addfreqday'] == '')
            {
                $param['ti_cooperation_addfreqday'] = 0;
            }
            else if (!Validate::isInt($param['ti_cooperation_addfreqday']))
            {
                throw new Exception('合作度增加频率不正确');
            }
        }
        else
        {
            $param['ti_cooperation_addfreqday'] = 0;
        }
        if (isset($param['ti_cooperation_addinc']))
        {
            if ($param['ti_cooperation_addinc'] == '')
            {
                $param['ti_cooperation_addinc'] = 0;
            }
            else if (!Validate::isInt($param['ti_cooperation_addinc']))
            {
                throw new Exception('合作度增加值不正确');
            }
        }
        else
        {
            $param['ti_cooperation_addinc'] = 0;
        }
        if (isset($param['ti_cooperation_addenddate']))
        {
            if ($param['ti_cooperation_addenddate'] == '')
            {
                $param['ti_cooperation_addenddate'] = NULL;
            }
            else if (!Validate::isDate($param['ti_cooperation_addenddate']))
            {
                throw new Exception('合作度增加持续日期格式不正确');
            }
        }


        $db = Fn::db();

        $param['ti_addtime'] = date('Y-m-d H:i:s');
        $param['ti_adduid'] = Fn::sess()->userdata('admin_id');

        $ti_id = 0;
        if ($db->insert('t_training_institution', $param))
        {
            $ti_id = $db->lastInsertId('t_training_institution', 'ti_id');
        }
        return $ti_id;
    }//}}}

    /**
     * 修改培训机构
     * @param   array   map<string, variant>类型的参数
     *                  int         ti_id
     *                  string      ti_name
     *                  int         ti_typeid
     *                  int         ti_flag
     *                  int         ti_priid
     *                  int         ti_provid
     *                  int         ti_cityid
     *                  int         ti_areaid
     *                  string      ti_addr
     *                  string      ti_url
     *                  int         ti_campusnum
     *                  int         ti_stumax
     *                  int         ti_reputation
     *                  int         ti_cooperation
     *                  int         ti_cooperation_addinc
     *                  int         ti_cooperation_addfreqday
     *                  date        ti_cooperation_addenddate

     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setTrainingInstitution(array $param)//{{{
    {
        $param = Func::param_copy($param, 'ti_id', 'ti_name', 'ti_typeid', 
            'ti_flag', 'ti_priid', 'ti_provid', 'ti_cityid', 'ti_areaid', 
            'ti_addr', 'ti_url', 'ti_stumax', 'ti_reputation', 'ti_campusnum',
            'ti_cooperation', 'ti_cooperation_addfreqday', 
            'ti_cooperation_addinc', 'ti_cooperation_addenddate');

        if (!isset($param['ti_id']) || !Validate::isInt($param['ti_id']))
        {
            throw new Exception('培训机构ID不正确');
        }
        if (count($param) == 1)
        {
            throw new Exception('没有任何要修改的内容');
        }
        if (isset($param['ti_name'])
            && !Validate::isStringLength($param['ti_name'], 1, 60))
        {
            throw new Exception('培训机构名称不能为空且长度最多60个字符');
        }
        if (isset($param['ti_typeid'])
            && !Validate::isInt($param['ti_typeid']))
        {
            throw new Exception('培训机构类型ID必须为整数');
        }
        if (isset($param['ti_flag']) 
            && !Validate::isInt($param['ti_flag']))
        {
            throw new Exception('培训机构状态标志必须为整数');
        }
        if (isset($param['ti_priid'])
            && !Validate::isInt($param['ti_priid']))
        {
            throw new Exception('培训机构优先级ID必须为整数');
        }
        if ((isset($param['ti_provid']) 
                && !Validate::isInt($param['ti_provid']))
            || (isset($param['ti_cityid']) 
                && !Validate::isInt($param['ti_cityid']))
            || (isset($param['ti_areaid'])
                && !Validate::isInt($param['ti_areaid'])))
        {
            throw new Exception('培训机构所在地区ID必须为整数');
        }
        if (isset($param['ti_addr']))
        {
            if ($param['ti_addr'] == '')
            {
                $param['ti_addr'] = NULL;
            }
            else if (!Validate::isStringLength($param['ti_addr'], 1, 255))
            {
                throw new Exception('培训机构地址长度最多为255个字符');
            }
        }
        if (isset($param['ti_url']))
        {
            if ($param['ti_url'] == '')
            {
                $param['ti_url'] = NULL;
            }
            else if (!Validate::isStringLength($param['ti_url'], 1, 255))
            {
                throw new Exception('培训机构网址长度最多为255个字符');
            }
        }
        if (isset($param['ti_stumax'])
            && !Validate::isInt($param['ti_stumax']))
        {
            throw new Exception('培训机构学员人数必须为整数');
        }
        if (isset($param['ti_reputation'])
            && !Validate::isInt($param['ti_reputation']))
        {
            throw new Exception('培训机构声誉值必须为整数');
        }
        if (isset($param['ti_campusnum'])
            && !Validate::isInt($param['ti_campusnum']))
        {
            throw new Exception('培训机构校区数量必须为整数');
        }

        if (isset($param['ti_cooperation']))
        {
            if (!Validate::isInt($param['ti_cooperation']))
            {
                throw new Exception('合作度不正确');
            }
        }
        if (isset($param['ti_cooperation_addinc']))
        {
            if (!Validate::isInt($param['ti_cooperation_addinc']))
            {
                throw new Exception('合作度增加值不正确');
            }
        }
        if (isset($param['ti_cooperation_addfreqday']))
        {
            if (!Validate::isInt($param['ti_cooperation_addfreqday']))
            {
                throw new Exception('合作度增加频率不正确');
            }
            /*
            if ($param['ti_cooperation_addfreqday'] == 0)
            {
                $param['ti_cooperation_addenddate'] = NULL;
            }
             */
        }
        if (isset($param['ti_cooperation_addenddate']))
        {
            if ($param['ti_cooperation_addenddate'] == '')
            {
                $param['ti_cooperation_addenddate'] = NULL;
            }
            else if (!Validate::isDate($param['ti_cooperation_addenddate']))
            {
                throw new Exception('合作度增加持续日期格式不正确');
            }
        }


        $ti_id = $param['ti_id'];
        unset($param['ti_id']);
        $db = Fn::db();

        $v = $db->update('t_training_institution', $param, 'ti_id = ' . $ti_id);
        return $v ? 1 : 0;
    }//}}}

    /**
     * 删除培训机构,若ti_flag > -1则为假删，否则为真删(已使用过的不可真删)
     * @param   string      $ti_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非,否则返回0
     */
    public static function removeTrainingInstitution(/*string*/$ti_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($ti_id_str))
        {
            throw new Exception('培训机构ID列表格式不正确,'
                . '应为英文逗号分隔开的ID字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT ti_id FROM t_training_institution 
WHERE ti_flag = -1 AND ti_id IN ({$ti_id_str})
EOT;
        $rm_ti_ids = $db->fetchCol($sql);   // 需要真删的ID
        if (!empty($rm_ti_ids))
        {
            $rm_ti_str = implode(',', $rm_ti_ids);
            $sql = <<<EOT
SELECT DISTINCT cors_tiid FROM t_course WHERE cors_tiid IN ({$rm_ti_str})
EOT;
            $nrm_ti_ids = $db->fetchCol($sql);  // 不可真删的ID

            $sql = <<<EOT
SELECT DISTINCT sbc_tiid FROM t_student_base_course WHERE sbc_tiid IN ({$rm_ti_str})
EOT;
            $nrm_ti_ids2 = $db->fetchCol($sql);  // 不可真删的ID 2

            $nrm_ti_ids = array_merge($nrm_ti_ids, $nrm_ti_ids2);
            $rm_ti_ids = array_diff($rm_ti_ids, $nrm_ti_ids);
        }

        $bOk = false;

        if ($db->beginTransaction())
        {
            if (!empty($rm_ti_ids))
            {
                $rm_ti_str = implode(',', $rm_ti_ids);
                // 可真删的ID
                $db->delete('t_training_campus', "tc_tiid IN ($rm_ti_str)");
                $db->delete('t_training_institution', 
                    "ti_id IN ($rm_ti_str)");
            }
            $db->update('t_training_institution', array('ti_flag' => -1),
                "ti_id IN ($ti_id_str)");
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}



    /************************* 培训校区接口  *******************************/
    // TODO
    /** 培训校区列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function trainingCampusList(/*string*/$field = NULL,
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

            $cond_param = Func::param_copy($cond_param, 'tc_id', 'tc_name',
                'tc_tiid');
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
            if (isset($cond_param['tc_id'])
                && Validate::isInt($cond_param['tc_id']))
            {
                $where[] = 'tc_id = ?';
                $bind[] = $cond_param['tc_id'];
            }
            else if (isset($cond_param['tc_id'])
                && Validate::isJoinedIntStr($cond_param['tc_id']))
            {
                $where[] = 'tc_id NOT IN (' . $cond_param['tc_id'] . ')';
            }
            
            if (isset($cond_param['tc_name'])
                && strlen($cond_param['tc_name']) > 0)
            {
                $where[] = 'tc_name LIKE ?';
                $bind[] = '%' . $cond_param['tc_name'] . '%';
            }
            if (isset($cond_param['tc_tiid'])
                && Validate::isInt($cond_param['tc_tiid']))
            {
                $where[] = 'tc_tiid = ?';
                $bind[] = $cond_param['tc_tiid'];
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('v_training_campus', $field, $where, 
            $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的校区数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function trainingCampusListCount(array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::trainingCampusList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }//}}}

    /**
     * 查询返回一条培训校区信息,从v_training_campus视图中查询
     * @param   int     $tc_id          培训校区ID
     * @param   string  $field = '*'    查询字段
     * @return  array   map<string, variant>或者返回null
     */
    public static function trainingCampusInfo(/*int*/$tc_id, 
        /*string*/$field = '*')//{{{
    {
        if (!Validate::isInt($tc_id))
        {
            throw new Exception('培训校区ID类型不正确');
        }
        $sql = <<<EOT
SELECT {$field} FROM v_training_campus WHERE tc_id = {$tc_id}
EOT;
        return Fn::db()->fetchRow($sql);
    }//}}}

    /**
     * 新增培训校区
     * @param   array   map<string, variant>类型的参数
     *                  string      tc_name
     *                  int         tc_tiid
     *                  int         tc_flag
     *                  int         tc_provid
     *                  int         tc_cityid
     *                  int         tc_areaid
     *                  string      tc_ctcaddr
     *                  string      tc_ctcphone
     *                  string      tc_ctcperson
     *                  int         tc_environ
     * @return  int     返回新增的培训校区ID,否则返回0 
     */
    public static function addTrainingCampus(array $param)//{{{
    {
        $param = Func::param_copy($param, 'tc_name', 'tc_tiid', 'tc_flag',
            'tc_provid', 'tc_cityid', 'tc_areaid', 'tc_ctcaddr', 'tc_ctcphone',
            'tc_ctcperson', 'tc_environ');

        if (!Validate::isStringLength($param['tc_name'], 1, 60))
        {
            throw new Exception('培训校区名称不能为空且长度最多60个字符');
        }
        if (!Validate::isInt($param['tc_tiid']))
        {
            throw new Exception('培训校区所属机构ID必须为整数');
        }
        if (!Validate::isInt($param['tc_flag']))
        {
            throw new Exception('培训校区状态标志必须为整数');
        }
        if (!Validate::isInt($param['tc_provid']) 
            || !Validate::isInt($param['tc_cityid'])
            || !Validate::isInt($param['tc_areaid']))
        {
            throw new Exception('培训校区所在地区ID必须为整数');
        }
        if (!Validate::isStringLength($param['tc_ctcaddr'], 1, 255))
        {
            throw new Exception('培训校区联系地址长度最多为255个字符');
        }
        if (isset($param['tc_ctcperson']))
        {
            if ($param['tc_ctcperson'] == '')
            {
                $param['tc_ctcperson'] = NULL;
            }
            else if (!Validate::isStringLength($param['tc_ctcperson'], 1, 60))
            {
                throw new Exception('培训校区联系人姓名最多60个字符');
            }
        }
        if (!Validate::isStringLength($param['tc_ctcphone'], 1, 120))
        {
            throw new Exception('培训校区联系电话长度最多为255个字符');
        }
        if (isset($param['tc_environ'])
            && !Validate::isInt($param['tc_environ']))
        {
            throw new Exception('培训校区环境指数必须为整数');
        }

        $db = Fn::db();

        $param['tc_addtime'] = date('Y-m-d H:i:s');
        $param['tc_adduid'] = Fn::sess()->userdata('admin_id');

        $tc_id = 0;
        if ($db->beginTransaction())
        {
            if ($db->insert('t_training_campus', $param))
            {
                $tc_id = $db->lastInsertId('t_training_campus', 'tc_id');
                $ti_id = $param['tc_tiid'];
                $sql = <<<EOT
UPDATE t_training_institution SET ti_campusnum = ti_campusnum + 1 
WHERE ti_id = {$ti_id}
EOT;
                $db->exec($sql);
                if (!$db->commit())
                {
                    $tc_id = 0;
                    $db->rollBack();
                }
            }
            else
            {
                $db->rollBack();
            }
        }
        return $tc_id;
    }//}}}

    /**
     * 修改培训校区(所属机构不可修改)
     * @param   array   map<string, variant>类型的参数
     *                  int         tc_id
     *                  string      tc_name
     *                  int         tc_flag
     *                  int         tc_provid
     *                  int         tc_cityid
     *                  int         tc_areaid
     *                  string      tc_ctcaddr
     *                  string      tc_ctcphone
     *                  string      tc_ctcperson
     *                  int         tc_environ
     * @return  int     若成功返回非0,若失败返回0
     */
    public static function setTrainingCampus(array $param)//{{{
    {
        $param = Func::param_copy($param, 'tc_id', 'tc_name', 'tc_flag',
            'tc_provid', 'tc_cityid', 'tc_areaid', 'tc_ctcaddr', 'tc_ctcphone',
            'tc_ctcperson', 'tc_environ');

        if (!isset($param['tc_id']) || !Validate::isInt($param['tc_id']))
        {
            throw new Exception('培训校区ID不正确');
        }
        if (count($param) == 1)
        {
            throw new Exception('没有任何要修改的内容');
        }
        if (isset($param['tc_name'])
            && !Validate::isStringLength($param['tc_name'], 1, 60))
        {
            throw new Exception('培训校区名称不能为空且长度最多60个字符');
        }
        if (isset($param['tc_flag'])
            && !Validate::isInt($param['tc_flag']))
        {
            throw new Exception('培训校区状态标志必须为整数');
        }
        if ((isset($param['tc_provid'])
                && !Validate::isInt($param['tc_provid']))
            || (isset($param['tc_cityid'])
                && !Validate::isInt($param['tc_cityid']))
            || (isset($param['tc_areaid'])
                && !Validate::isInt($param['tc_areaid'])))
        {
            throw new Exception('培训校区所在地区ID必须为整数');
        }
        if (isset($param['tc_ctcaddr'])
            && !Validate::isStringLength($param['tc_ctcaddr'], 1, 255))
        {
            throw new Exception('培训校区联系地址长度最多为255个字符');
        }
        if (isset($param['tc_ctcperson']))
        {
            if ($param['tc_ctcperson'] == '')
            {
                $param['tc_ctcperson'] = NULL;
            }
            else if (!Validate::isStringLength($param['tc_ctcperson'], 1, 60))
            {
                throw new Exception('培训校区联系人姓名最多60个字符');
            }
        }
        if (isset($param['tc_ctc_phone'])
            && !Validate::isStringLength($param['tc_ctcphone'], 1, 120))
        {
            throw new Exception('培训校区联系电话长度最多为255个字符');
        }
        if (isset($param['tc_environ'])
            && !Validate::isInt($param['tc_environ']))
        {
            throw new Exception('培训校区环境指数必须为整数');
        }

        $tc_id = $param['tc_id'];
        unset($param['tc_id']);
        $db = Fn::db();

        $v = $db->update('t_training_campus', $param, 'tc_id = ' . $tc_id);
        return $v ? 1 : 0;
    }//}}}

    /**
     * 删除培训校区,若tc_flag > -1则为假删，否则为真删(已使用过的不可真删)
     * @param   string      $tc_id_str  形似1,2,3样式的ID列表
     * @return  int     成功执行则返回非,否则返回0
     */
    public static function removeTrainingCampus(/*string*/$tc_id_str)//{{{
    {
        if (!Validate::isJoinedIntStr($tc_id_str))
        {
            throw new Exception('培训校区ID列表格式不正确,'
                . '应为英文逗号分隔开的ID字符串');
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT tc_id FROM t_training_campus
WHERE tc_flag = -1 AND tc_id IN ({$tc_id_str})
EOT;
        $rm_tc_ids = $db->fetchCol($sql);   // 需要真删的ID
        if (!empty($rm_tc_ids))
        {
            $rm_tc_str = implode(',', $rm_tc_ids);
            $sql = <<<EOT
SELECT DISTINCT cc_tcid FROM t_course_campus WHERE cc_tcid IN ({$rm_tc_str})
EOT;
            $nrm_tc_ids = $db->fetchCol($sql);  // 不可真删的ID
            $rm_tc_ids = array_diff($rm_tc_ids, $nrm_tc_ids);
        }

        $bOk = false;

        if ($db->beginTransaction())
        {
            if (!empty($rm_tc_ids))
            {
                $rm_tc_str = implode(',', $rm_tc_ids);
                // 可真删的ID
                $sql = <<<EOT
SELECT tc_id, tc_tiid FROM t_training_campus WHERE tc_id IN ({$rm_tc_str})
EOT;
                $rows = $db->fetchAll($sql);
                $db->delete('t_training_campus', "tc_id IN ($rm_tc_str)");
                foreach ($rows as $row)
                {
                    $ti_id = $row['tc_tiid'];
                    $sql = <<<EOT
UPDATE t_training_institution SET ti_campusnum = ti_campusnum - 1 
WHERE ti_id = {$ti_id}
EOT;
                    $db->exec($sql);
                }
            }
            $db->update('t_training_campus', array('tc_flag' => -1),
                "tc_id IN ($tc_id_str)");
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk ? 1 : 0;
    }//}}}
}
