<?php
/**
 * 考场模块ExamPlaceModel
 * @file    ExamPlace.php
 * @author  BJP
 * @final   2015-06-17
 */
class ExamPlaceModel
{
    /**
     * 获取一个考试期次下考场信息
     * int $exam_pid : 期次ID
     * string $item : 需要获取的字段，默认获取全部，多个字段用逗号分割
     * return mixed: 查询多字度，返回数组。单个字段直接返回字段值。
     * @author Zh
     */
    public static function get_exam_place($exam_pid = 0, $item = '*')
    {
        if ($exam_pid == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_exam_place WHERE exam_pid = {$exam_pid}
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
     * 获取一个考场信息
     * int $place_id : 考场ID
     * string $item : 需要获取的字段，默认获取全部，多个字段用逗号分割
     * return mixed: 查询多字度，返回数组。单个字段直接返回字段值。
     */
    public static function get_place($place_id = 0, $item = '*')
    {
        if ($place_id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_exam_place WHERE place_id = {$place_id}
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
     * 获取记录列表
     * @param array $query
     * @param integer $page
     * @param integer $per_page
     * @param string $order_by
     * @param string $select_what
     */
    public static function get_exam_place_list($query, $page = 1, 
        $per_page = 20, $order_by = null, $select_what = '*')
    {
        try
        {
            $where = array();
            $bind = array();

            if (is_array($query) && count($query))
            {
                foreach ($query as $key => $val)
                {
                    switch ($key)
                    {
                    case 'place_id':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "exam_id IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'place_id = ?';
                            $bind[] = intval($val);
                        }
                        break;
                    case 'exam_pid':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "exam_pid IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'exam_pid = ?';
                            $bind[] = $val;
                        }
                        break;
                    case 'school_id':
                        if (is_array($val))
                        {
                            $tmpStr = array();
                            foreach ($val as $k => $v)
                            {
                                $tmpStr[] = '?';
                                $bind[] = intval($v);
                            }
                            $tmpStr = implode(', ', $tmpStr);
                            $where[] = "school_id IN ({$tmpStr})";
                        }
                        else
                        {
                            $where[] = 'school_id = ?';
                            $bind[] = $val;
                        }
                        break;
                    default:;
                    }
                }
            }
            
            $where = count($where) ? (" WHERE " . implode(' AND ', $where)) : '';
            $order_by = !is_null($order_by) ? ' ORDER BY ' . $order_by : '';
            $group_by = '';
            $limit = '';
            $page = intval($page);
            if ($page > 0)
            {
                $per_page = intval($per_page);
                $start = ($page - 1) * $per_page;
                $limit = " LIMIT {$per_page} OFFSET {$start}";
            }
            
            $table_name = 'rd_exam_place';
            $sql = <<<EOT
SELECT {$select_what} FROM $table_name {$where} {$order_by} {$group_by} {$limit}
EOT;
            $data = Fn::db()->fetchAll($sql, $bind);
            return $data;
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
    	}
    }

    /**
     * 执行删除操作
     */
    public static function delete($id)
    {
        $id = intval($id);
        $id && $row = self::get_place($id, 'place_id, exam_pid');
        if (empty($row)) 
        {          
            return -1; // 不存在
        }
        
        $bOk = false;
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            if ($db->delete('rd_exam_place', 'place_id = ' . $id))
            {
                $db->delete('rd_exam_place_student', 'place_id = ' . $id);
                $db->delete('rd_exam_place_invigilator', 'place_id = ' . $id);
                $db->delete('rd_exam_place_subject', 'place_id = ' . $id);
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
            }
            else
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }
    
    /**
     * 检查是否存在关联 监考人员
     */
    public static function has_place_invigilator($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
    	
        $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_exam_place_invigilator 
WHERE place_id = {$place_id}
LIMIT 1 OFFSET 0
EOT;
    	$place_invigilator_result = Fn::db()->fetchAll($sql);
    	return $place_invigilator_result[0]['count'] > 0;
    }
    
    /**
     * 检查是否存在关联 考生
     */
    public static function has_place_student($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
        $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_exam_place_student 
WHERE place_id = {$place_id} LIMIT 1 OFFSET 0
EOT;
    	$place_student_result = Fn::db()->fetchAll($sql);
    	return $place_student_result[0]['count'] > 0;
    }
    
    /**
     * 考场学生列表
     */
    public static function placeStudentList($place_id = 0)
    {
        $place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
        }
        
        $sql = <<<EOT
SELECT uid FROM rd_exam_place_student
WHERE place_id = {$place_id}
EOT;
        return Fn::db()->fetchCol($sql);
    }
    
    /**
     * 考场学生列表
     */
    public static function placeStudentInfoList($place_id = 0)
    {
        $place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
        }
    
        $sql = <<<EOT
SELECT eps.uid,s.* FROM rd_exam_place_student eps
LEFT JOIN rd_student s ON s.uid = eps.uid
WHERE place_id = {$place_id}
EOT;
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 检查是否存在关联 科目
     */
    public static function has_place_subject($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
        $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_exam_place_subject 
WHERE place_id = {$place_id} LIMIT 1 OFFSET 0
EOT;
    	$place_subject_result = Fn::db()->fetchAll($sql);
    	return $place_subject_result[0]['count'] > 0;
    }
    
    /**
     * 检查是否存在关联 考生考试记录
     */
    public static function has_place_test_result($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
        
        $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_exam_test_paper 
WHERE place_id = {$place_id} LIMIT 1 OFFSET 0
EOT;
    	$place_test_result = Fn::db()->fetchAll($sql);
    	return $place_test_result[0]['count'] > 0;
    }
    
    /**
     * 检查某考场是否存在关联信息
     * relate:
     *  监考人员
     *  考生
     *  考试科目	
     *  考生考试记录
     */
    public static function has_place_relate_info($place_id)
    {
    	return self::has_place_invigilator($place_id) || 
    		   self::has_place_student($place_id) || 
                   self::has_place_subject($place_id) || 
                   self::has_place_test_result($place_id);
    }
    
    //================考试状态====================
    /**
     * 检查某考场是否 未开始考试
     */
    public static function place_is_no_start($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
    	
    	$sql = <<<EOT
SELECT e.exam_isfree, ep.start_time, ep.end_time 
FROM rd_exam_place ep
LEFT JOIN rd_exam e ON e.exam_id = ep.exam_pid 
WHERE place_id = {$place_id}
EOT;
    	$result = Fn::db()->fetchRow($sql);
        if (!$result)
        {
            return false;
    	}
    	
    	if ($result['exam_isfree'] == 1)
    	{
    	    return true;
    	}
    	
    	return $result['start_time'] > time();
    }
    
    /**
     * 检查某考场是否 正在考试
     */
    public static function place_is_testting($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
    	}
    	 
        $sql = <<<EOT
SELECT start_time, end_time FROM rd_exam_place WHERE place_id = {$place_id}
EOT;
    	$result = Fn::db()->fetchAll($sql);
        if (!count($result))
        {
            return false;
        }
    	$result = $result[0];
    	$now = time();
    	return $result['start_time'] <= $now && $result['end_time'] >= $now;
    }
    
    /**
     * 检查某考场是否 已经结束
     */
    public static function place_is_finished($place_id = 0)
    {
    	$place_id = intval($place_id);
        if (!$place_id)
        {
            return false;
        }
        $sql = <<<EOT
SELECT start_time, end_time FROM rd_exam_place WHERE place_id = {$place_id}
EOT;
    	$result = Fn::db()->fetchAll($sql);
        if (!count($result))
        {
            return false;
    	}
    	$result = $result[0];
    	$now = time();
    	return $result['end_time'] < $now;
    }
    
    /**
     * 检查某考场是否 已开始或已结束
     */
    public static function place_is_testting_or_finished($place_id)
    {
    	return !self::place_is_no_start($place_id);
    }
    
    public static function out_exam_place_student($place_id, $uid, $why, $flag)
    {
        $data = array('flag' => $flag, 'why' => $why);
    
        if ($flag <> 1)
        {
            Fn::db()->update('rd_exam_test_paper', array('etp_flag' => 0), 
                        "uid = {$uid} AND place_id = {$place_id}");
        }
        return Fn::db()->update('rd_exam_place_student', $data, 
            "uid = $uid AND place_id = $place_id");
    }
     
    /**
     * 按 考试场次 获取该场次考试科目
     * @param	int	考试场次id(place_id)
     * @return  array
     */
    public static function get_exam_place_subject($place_id)
    {
        $list = array();
        $sql = <<<EOT
SELECT subject_id, exam_pid, exam_id FROM rd_exam_place_subject
WHERE place_id = {$place_id}
EOT;
        $rows = Fn::db()->fetchAll($sql);
        foreach ($rows as $val)
        {
            $list[] = array(
                    'exam_pid' => $val['exam_pid'],
                    'exam_id' => $val['exam_id'],
                    'subject_id' => $val['subject_id'],
                    'subject_name' => C('subject/'.$val['subject_id']),
            );
        }
        return $list;
    }
    
    /**
     * 修改考场信息
     */
    public function setExamPlace($param)
    {
        $param = Func::param_copy($param, 'place_id', 
            'exam_pid','place_name','school_id',
            'place_schclsid','address','ip','start_time',
            'end_time','exam_time_custom');
        if ((!$param['place_id'] || !Validate::isInt($param['place_id']))
            && (!$param['exam_pid'] || !Validate::isInt($param['exam_pid'])))
        {
            return false;
        }
        
        $where = '';
        $where_bind = array();
        if ($param['place_id'])
        {
            unset($param['exam_pid']);
            $where = "place_id = ?";
            $where_bind[] = $param['place_id'];
        }
        else 
        {
            unset($param['place_id']);
            $where = "exam_pid = ?";
            $where_bind[] = $param['exam_pid'];
        }
        
        return Fn::db()->update('rd_exam_place', $param, $where, $where_bind);
    } 
    
    /**
     * 检查考场名称是否重复
     */
    public static function checkPlaceNameIsRepeat($exam_pid, $place_id = null, $place_name)
    {
        if (!$exam_pid || !$place_name)
        {
            throw new Exception('考试期次或考场名称不能为空');
        }
        
        $bind = array($exam_pid, $place_name);
        
        $sql = "SELECT COUNT(*) FROM rd_exam_place
                WHERE exam_pid = ? AND place_name = ?";
        if ($place_id > 0)
        {
            $sql .= " AND place_id <> ?";
            $bind[] = $place_id;
        }
        
        return Fn::db()->fetchOne($sql, $bind);
    }
}
