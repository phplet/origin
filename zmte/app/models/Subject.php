<?php
/**
 * SubjectModel
 * @file    Subject.php
 * @author  BJP
 * @final   2015-06-16
 */
class SubjectModel
{
    /**
     * 读取一个学科信息
     * @param   int     $id = 0         学科ID,如果为0则返回FALSE
     * @param   string  $item = NULL    字段名,如果为NULL则获取所有字段
     * @return  mixed   如果无数据，则返回FALSE，
     *                  否则
     *                    若$item有效则返回该字段值variant
     *                    若$item无效则返回该条记录所有字段map<string, variant>
     */
    public static function get_subject($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_subject WHERE subject_id = {$id}
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
    
    /**
     * 读取学科列表, added by BJP, 2015-06-16
     * @param   string  $where = ''     查询条件SQL语句
     * @param   array   $bind = array() 绑定参数
     * @return  array   list<map<string, variant>>类型查询结果集
     */
    public static function get_subjects($where = '', $bind = array())
    {
        $sql = "SELECT * FROM rd_subject";
        if ($where)
        {
            $sql .= ' WHERE '. $where;
        }
        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 查询学科列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     */
    public static function subjectList(/*string*/$field = NULL,
        array $cond_param = NULL)
    {
        $where = array();
        $bind = array();
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }

            $cond_param = Func::param_copy($cond_param, 'subject_id', 
                'subject_name', 'grade_period');
            if (isset($cond_param['subject_id']) 
                && Validate::isInt($cond_param['subject_id']))
            {
                $where[] = 'subject_id = ' . $cond_param['subject_id'];
            }
            if (isset($cond_param['subject_name'])
                && strlen($cond_param['subject_name']) > 0)
            {
                $where[] = 'subject_name LIKE ?';
                $bind[] = '%' . $cond_param['subject_name'] . '%';
            }
            if (isset($cond_param['grade_period'])
                && strlen($cond_param['grade_period']) > 0)
            {
                $where[] = 'grade_period LIKE ?';
                $bind[] = '%' . $cond_param['grade_period'] . '%';
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList('rd_subject', $field, $where, 
            $bind, $order_by);
    }
    
    /**
     * 获取学科列表，按key=val形式的数组
     * 无参数
     * @return  array   map<int, string>类型学科列表
     */
    public static function subject_key_val()
    {
        static $list = NULL;
        if (isset($list)) return $list;

        $sql = <<<EOT
SELECT subject_id, subject_name FROM rd_subject
EOT;
        $list = Fn::db()->fetchPairs($sql);
        return $list;
    }
    
    /**
     * 更新配置文件缓存config/app/setting.php中的subject配置段
     * 无参数，无返回值
     */
    public static function update_cache()
    {
        $cache_file = APPPATH . 'config/app/setting.php';
        $list = self::subject_key_val();
        $file_content = file_get_contents($cache_file);

        $subject_cache = "\r\n".'$config[\'subject\'] = ' . var_export($list, TRUE) . ";\r\n";

        $new_content = preg_replace('/(start:subject_cache)(.*)(end:subject_cache)/s', '\1'.$subject_cache.'// \3', $file_content);
        file_put_contents($cache_file, $new_content);


        $cache_file = APPPATH . 'config/app/setting.sample.php';
        $file_content = file_get_contents($cache_file);

        $subject_cache = "\r\n".'$config[\'subject\'] = ' . var_export($list, TRUE) . ";\r\n";

        $new_content = preg_replace('/(start:subject_cache)(.*)(end:subject_cache)/s', '\1'.$subject_cache.'// \3', $file_content);
        file_put_contents($cache_file, $new_content);
    }
    
    /**
     * 查询学科四维列表
     * @return  array   list<map<string,variant>>
     */
    public static function subjectDimensionList()
    {
        $sql = "SELECT * FROM t_subject_dimension";
        return Fn::db()->fetchAssoc($sql);
    }

    /**
     * 新增学科四维
     * @param   array   map<string,variant>类型的学科思维信息参数　
     *          int     subd_subjectid      学科id
     *          string  subd_value          学科四维赋值
     *          string  subd_professionid   学科关联职业
     * @return  int     若成功则返回1,否则返回0
     */
    public static function addSubjectDimension($param)
    {
        $param = Func::param_copy($param, 'subd_subjectid',
            'subd_value', 'subd_professionid');
        
        if (!Validate::isInt($param['subd_subjectid'])
            || $param['subd_subjectid'] <= 0)
        {
            throw new Exception('请选择学科');
        }
      
        if (count($param['subd_value']) != 4
            || max($param['subd_value']) > 7 
            || min($param['subd_value']) < -7)
        {
            throw new Exception('学科四维赋值不合法');
        }

        if (!$param['subd_professionid'])
        {
            throw new Exception('请选择关联职业');
        }
        
        $param['subd_value'] = implode(',', $param['subd_value']);
        $param['subd_professionid'] = json_encode($param['subd_professionid']);
        
        return Fn::db()->insert('t_subject_dimension', $param);
    }

    /**
     * 编辑四维学科
     * @param   array   map<string,variant>类型的学科思维信息参数　
     *          int     subd_subjectid      学科四维ID
     *          string  subd_value          学科四维赋值
     *          string  subd_professionid   学科关联职业
     * @return  boolean 若成功则返回TRUE,否则返回0
     **/
    
    public static function setSubjectDimension($param)
    {
        $param = Func::param_copy($param, 'subd_subjectid',
            'subd_value', 'subd_professionid');
        
        if (!Validate::isInt($param['subd_subjectid'])
            || $param['subd_subjectid'] <= 0)
        {
            throw new Exception('请选择学科');
        }
      
        if (count($param['subd_value']) != 4
            || max($param['subd_value']) > 7 
            || min($param['subd_value']) < -7)
        {
            throw new Exception('学科四维赋值不合法');
        }

        if (!$param['subd_professionid'])
        {
            throw new Exception('请选择关联职业');
        }
        
        $param['subd_value'] = implode(',', $param['subd_value']);
        $param['subd_professionid'] = json_encode($param['subd_professionid']);
        
        return Fn::db()->update('t_subject_dimension', 
            $param, 'subd_subjectid = ?', array($param['subd_subjectid']));
    }

    /**
     * 删除四维学科
     * @param   array   map<string,variant>类型的学科思维信息参数　
     *          int     subd_subjectid      学科四维ID
     * @return  boolean 若成功则返回TRUE,否则返回0
     **/
    public static function removeSubjectDimension($subd_subjectid)
    {
        if (Validate::isInt($subd_subjectid))
        {
            return Fn::db()->delete('t_subject_dimension', 
                "subd_subjectid = $subd_subjectid");
        }
        else if (Validate::isJoinedIntStr($subd_subjectid))
        {
            return Fn::db()->delete('t_subject_dimension', 
                "subd_subjectid IN ($subd_subjectid)");
        }
        else
        {
            return false;
        }
    }
    
    /**
     * 获取$subd_subjectid 学科四维新增，返回查询结果集
     * @param   int     $subd_subjectid
     * @return  array   map<string,variant>
     */
    public static function subjectDimensionInfo($subd_subjectid)
    {
        if (!Validate::isInt($subd_subjectid)
            || $subd_subjectid <= 0)
        {
            return array();
        }
        
        $sql = "SELECT * FROM t_subject_dimension 
                WHERE subd_subjectid = ?";
        return Fn::db()->fetchRow($sql, array($subd_subjectid));
    }
}
