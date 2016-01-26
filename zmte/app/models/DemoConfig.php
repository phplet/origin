<?php
/**
 * MINI测考试配置DemoConfigModel
 * @file    DemoConfig.php
 * @author  BJP
 * @final   2015-06-19
 */
class DemoConfigModel
{
    /**
     * 读取MINI测配置列表
     * @return  array
     */
    public static function get_demo_config_list($param = array(), 
        $page = Null, $perpage = Null)
    {
        $sql = "SELECT a.*,b.* FROM rd_demo_exam_config a 
                LEFT JOIN rd_exam b ON a.dec_exam_pid = b.exam_id";
        $where = array();
        if (!empty($param['subject_id']))
        {
            $where[] = "b.subject_id = " . $param['subject_id'];
        }
        
        if (!empty($param['grade_id']))
        {
            $where[] = "b.grade_id = " . $param['grade_id'];
        }
        
        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        if ($page && $perpage)
        {
            $start = ($page - 1) * $perpage;
            
            $sql .= " LIMIT $start,$perpage";
        }
        
        $data = Fn::db()->fetchAll($sql);
        
        if ($data)
        {
            foreach ($data as &$item)
            {
                if (empty($item['exam_id']))
                {
                    continue;
                }
                
                $sql = "SELECT subject_id FROM rd_exam a 
                        WHERE exam_pid = " . $item['exam_id'] . 
                        " ORDER BY subject_id ASC";
                
                $item['exam_subject'] = Fn::db()->fetchAll($sql);
            }
        }
        return $data;
    }
    
    /**
     * 读取MINI测配置条数
     *
     * @return  array
     */
    public static function get_demo_config_list_count($param = array())
    {
        $sql = "SELECT COUNT(*) AS cnt FROM rd_demo_exam_config a
                LEFT JOIN rd_exam b ON a.dec_exam_pid = b.exam_id";
        $where = array();
        if (!empty($param['subject_id']))
        {
            $where[] = "b.subject_id = " . $param['subject_id'];
        }
    
        if (!empty($param['grade_id']))
        {
            $where[] = "b.grade_id = " . $param['grade_id'];
        }
    
        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
    
        $data = Fn::db()->fetchRow($sql);
    
        return isset($data['cnt']) ? $data['cnt'] : 0;
    }
    
    /**
     * 读取MINI测配置信息
     */
    public static function get_demo_config($dec_id)
    {
        $sql = <<<EOT
SELECT * FROM rd_demo_exam_config WHERE dec_id = {$dec_id}
EOT;
        return Fn::db()->fetchRow($sql);
    } 
    
    /**
     * 判断年级或考试期次已经在配置中了
     */
    public static function check_config_exist($param)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM rd_demo_exam_config";
        
        $where = array();
        if (!empty($param['grade_id']))
        {
            $where[] = "dec_grade_id = " . $param['grade_id'];
        }
        
        if (!empty($param['exam_pid']))
        {
            $where[] = "dec_exam_pid = " . $param['exam_pid'];
        }
        
        $sql .= " WHERE " . implode(' OR ', $where);
        
        $count = Fn::db()->fetchRow($sql);
        
        return isset($count['cnt']) ? $count['cnt'] : 0;
    }    
    
    /**
     * 获取不存在配置中的考试信息
     *
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_parent_exam($exam_pid = 0, $param = array())
    {
        $sql = "SELECT * FROM rd_exam WHERE exam_pid = $exam_pid";
        
        $sql .= " AND exam_id NOT IN (SELECT exam_pid FROM rd_demo_exam_config)";
    
        $where = array();
        if (!empty($param['subject_id']))
        {
            $where[] = "subject_id = " . $param['subject_id'];
        }
    
        if (!empty($param['grade_id']))
        {
            $where[] = "grade_id = " . $param['grade_id'];
        }
    
        if ($where)
        {
            $sql .= implode(' AND ', $where);
        }
    
        return Fn::db()->fetchAll($sql);
    }
    
    /**
     * 新增MINI测配置信息
     */
    public static function add($param)
    {
        !isset($param['dec_date_create']) && $param['dec_date_create'] = time();
        !isset($param['dec_date_modify']) && $param['dec_date_modify'] = time();
        
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            if (isset($param['place_id']) && $param['place_id'])
            {
                $sql = "UPDATE rd_exam_place SET school_id = 0, address='', ip='',
                    start_time = '0', end_time = '0' WHERE place_id = " . $param['place_id'];
                $db->exec($sql);
                
                $sql = "DELETE FROM rd_exam_place_student WHERE place_id = " . $param['place_id'];
                $db->exec($sql);
                
                unset($param['place_id']);
            }
            
            $db->insert('rd_demo_exam_config', $param);
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    } 
    
    /**
     * 修改配置信息
     */
    public static function update($param, $dec_id)
    {
        !isset($param['dec_date_modify']) && $param['dec_date_modify'] = time();
        
        $bOk = false;
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            if (isset($param['place_id']) && $param['place_id'])
            {
                $sql = "UPDATE rd_exam_place SET school_id = 0, address='', ip='',
                    start_time=0, end_time=0 WHERE place_id = " . $param['place_id'];
                $db->exec($sql);
                
                $sql = "DELETE FROM rd_exam_place_student WHERE place_id = " . $param['place_id'];
                $db->exec($sql);
                
                unset($param['place_id']);
            }
        
            $db->update('rd_demo_exam_config', $param, 'dec_id = ' . $dec_id);

            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }
    
    /**
     * 删除配置信息
     */
    
    public static function delete($dec_id)
    {
        $sql = "DELETE FROM rd_demo_exam_config WHERE dec_id IN ($dec_id)";
        return Fn::db()->exec($sql) > 0;
    }
    
    /**
     * 更新配置文件缓存config/app/demo/website.php中的demo_exam_config配置段
     * 无参数，无返回值
     */
    public static function update_cache()
    {
        $cache_file = APPPATH.'config/app/demo/website.php';
        $data = self::get_demo_config_list();
        $file_content = file_get_contents($cache_file);
        
        $list = array();
        $default = array(
                //机考系统别名
                'system_name'	=> C('webconfig')['site_name'] . '上机考试系统演示',
                'introduce'		=> '考试介绍',
                'student_notice'=> '学生须知',
                'exam_type'		=> '1',
                //倒计时刷新时间(单位：秒)
                'refresh_time' 	=> array(
                        'submit_success' => '5'//交卷成功页
                )
        );
        
        $subjects = C('subject');
        
        foreach ($data as $item)
        {
            $grade_id = $item['dec_grade_id'];
            
            $list[$grade_id] = $default;
            $list[$grade_id]['exam_name'] = $item['exam_name'];
            $list[$grade_id]['exam_pid'] = $item['exam_id'];
            $list[$grade_id]['grade_id'] = $grade_id;
            if ($item['exam_subject'])
            {
                foreach ($item['exam_subject'] as $val)
                {
                    $list[$grade_id]['subject_id'][$val['subject_id']] = $subjects[$val['subject_id']];
                }
                
                ksort($list[$grade_id]['subject_id']);
            }
            else
            {
                $list[$grade_id]['subject_id'] = array();
            }
            
            $sql = "SELECT place_id FROM rd_exam_place 
                    WHERE exam_pid = {$item['exam_id']} ORDER BY place_id ASC";
            $place = Fn::db()->fetchRow($sql);
            if ($place)
            {
                $list[$grade_id]['place_id'] = $place['place_id'];
            }
            else 
            {
                $list[$grade_id]['place_id'] = 0;
            }
        }
        
        ksort($list);
    
        $demo_exam_config_cache = "\r\n".'$config[\'demo_exam_config\'] = ' . var_export($list, TRUE) . ";\r\n";
    
        $new_content = preg_replace('/(start:demo_exam_config_cache)(.*)(end:demo_exam_config_cache)/s', '\1'.$demo_exam_config_cache.'// \3', $file_content);
        file_put_contents($cache_file, $new_content);
    }
}
