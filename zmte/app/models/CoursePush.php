<?php
/**
 * CoursePushModel
 * @file    CoursePush.php
 * @author  TCG
 * @final   2015-08-19
 */
class CoursePushModel
{
    /*******************  课程推送档案接口  ****************************/
    
    /**
     * 学生是否接受课程推送
     * @param   int     $uid
     * @param   int     $exam_pid
     * @param   int     $place_id
     * @return  boolean true|false
     */
    public static function isAcceptPush($uid, $exam_pid, $place_id)
    {
        if (!$uid)
        {
        	return false;
        }
        
        $db = Fn::db();
        
        //检查考试期次是否在产品列表中，若在产品列表中，需要检查学生是否接受推送课程
        if ($exam_pid && $place_id)
        {
            $sql = "SELECT p_id FROM rd_product
                    WHERE exam_pid = ?";
            if ($db->fetchOne($sql, array($exam_pid)))
            {
                $sql = "SELECT COUNT(*) FROM t_course_push
                        WHERE cp_stuuid = ? AND cp_exampid = ?
                        AND cp_examplaceid = ?";
                if (!$db->fetchOne($sql, array($uid, $exam_pid, $place_id)))
                {
                    return false;
                }
            }
        }
        
        $sql = "SELECT COUNT(*) FROM t_student_base_classid 
                WHERE sbclassid_uid = $uid";
        $count = Fn::db()->fetchOne($sql);
        
        if ($count)
        {
            $sql = "SELECT COUNT(*) FROM t_student_base_stunumtype
                    WHERE sbs_uid = $uid";
            $count = Fn::db()->fetchOne($sql);
        }
        
        return $count ? true : false;
    }
    
    /**
     * 校区授课老师
     * @param    array    $cc_id    校区ID
     * @return   array   list<map<string, variant>>类型数据
     */
    public static  function courseTeacher(array $cc_id)
    {
        if (!$cc_id)
        {
            return array();
        }
        
        $sql = "SELECT cct_ccid, ct_name FROM v_course_campus_teacher 
                WHERE cct_ccid IN (" . implode(',', $cc_id) . ")";
        $stmt = Fn::db()->query($sql);
        
        $data = array();
        
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $data[$item['cct_ccid']][] = $item['ct_name'];
        }
        
        return $data;
    }
    
    /**
     * 课程授课范围
     * @param    array    $cors_id    课程ID
     * @return   array   list<map<string, variant>>类型数据
     */
    public static  function courseRange(array $cors_id)
    {
        if (!$cors_id)
        {
            return array();
        }
        
        $data = array();
        $grade = C('grades');
        
        //课程所包含的年纪
        $sql = "SELECT * FROM t_course_gradeid
                WHERE cg_corsid IN (" . implode(',', $cors_id) . ")";
        $stmt = Fn::db()->query($sql);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $data['grade'][$item['cg_corsid']][] = $grade[$item['cg_gradeid']];
        }
    
        //课程所包含的学科
        $sql = "SELECT * FROM v_course_subjectid
                WHERE cs_corsid IN (" . implode(',', $cors_id) . ")";
        $stmt = Fn::db()->query($sql);
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $data['subject'][$item['cs_corsid']][] = $item['subject_name'];
        }
    
        return $data;
    }
    
    /**
     * 按机构声誉推送课程
     * @param   int     $uid            学生uid
     * @param   int     $subject_id     学生考试的学科
     * @param   int     $paper_id       学生考的试卷
     * @param   array   $cc_ids         要进行排序的校区课程
     * @param   boolean $is_all_cors    是否返回所有课程
     * @param   boolean $is_city        是否只取市级
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function reputationPush($uid, $subject_id, $paper_id, $cc_ids = array(), $is_all_cors = false, $is_city = true)
    {
        if (!$uid || !$subject_id || !$paper_id)
        {
        	return false;
        }
        
        $db = Fn::db();
        
        $sql = self::bindSql($uid, $subject_id, $cc_ids, $is_city);
        if (!$sql)
        {
            return false;
        }
        
        $data = array();
        
        $sql .= " ORDER BY ti.ti_reputation DESC, cc_price2 ASC";
        
        $result = $db->fetchAssoc($sql);
        if ((!$result || count($result) < 3) && !$cc_ids && !$is_city)
        {
            $sql = self::bindSql($uid, $subject_id, $cc_ids, true);
            if ($result)
            {
                $data = $result;
                
                $sql .= " AND cc_id NOT IN (" . implode(',', array_keys($result)) . ")";
            }
            
            $sql .= " ORDER BY ti.ti_reputation DESC, cc_price2 ASC";

            $result = $db->fetchAssoc($sql);
        }
        
        if (!$result)
        {
            return array();
        }
        
        if ($cc_ids || $is_all_cors)
        {
            return $result;
        }
        
        $result2 = array_values($result);
        
        $count = 3 - count($data);
        
        if ($result2[$count-1]['ti_reputation'] > $result2[$count]['ti_reputation'])
        {
            return array_slice($result, 0, $count, true);
        }
        
        $ti_reputation = $result2[$count-1]['ti_reputation'];
        $t_cc_ids = array();
        foreach ($result as $key => $item)
        {
            if ($ti_reputation < $item['ti_reputation'])
            {
                $data[$key] = $item;
                unset($result[$key]);
            }
            else if ($ti_reputation == $item['ti_reputation'])
            {
                $t_cc_ids[] = $item['cc_id'];
            }
            else 
            {
            	break;
            }
        }
        
        //如果存在课程并列情况，则按照知识点排序区分
        $list = self::knowledgePush($uid, $subject_id, $paper_id, $t_cc_ids);
        if ($list)
        {
            $data = self::calKnowledgePush($list, $data);
            if (count($data) >= 3)
            {
                return $data;
            }
        }
        
        //遇到并列情况，根据课程价格升序排序
        $data = self::calPricePush($result, $data, $t_cc_ids);
        $count = count($data);
        if ($count >= 3)
        {
        	return $data;
        }
        
        //遇到并列情况，根据课程最后修改时间降序排序
        $list = self::lastModifyPush($t_cc_ids, 3 - $count);
        if ($list)
        {
            $data = self::calLastModifyPush($list, $data);
        }
        
        return $data;
    }
    
    /**
     * 按知识点符合度推送课程
     * @param   int     $uid            学生uid
     * @param   int     $subject_id     学生考试的学科
     * @param   int     $paper_id       学生考的试卷
     * @param   array   $cc_ids         要进行排序的校区课程
     * @param   boolean $is_all_cors    是否返回所有课程
     * @param   boolean $is_city        是否只取市级
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function knowledgePush($uid, $subject_id, $paper_id, $cc_ids = array(), $is_all_cors = false, $is_city = true)
    {
        if (!$uid || !$subject_id || !$paper_id)
        {
        	return false;
        }
        
        $db = Fn::db();
        
        $sql = self::bindSql($uid, $subject_id, $cc_ids, $is_city);
        if (!$sql)
        {
            return false;
        }
        
        $result = $db->fetchAssoc($sql);
        if ((!$result || count($result) < 3) && !$cc_ids && !$is_city)
        {
            $sql = self::bindSql($uid, $subject_id, $cc_ids, true);
            $result = $db->fetchAssoc($sql);
        }
        
        if (!$result)
        {
        	return array();
        }
        
        $list = array();
        
        $t_cc_id = array();
        $t_cc_id2 = array();
        $t_cors_id = array();
        
        foreach ($result as $key => $item)
        {
            if ($item['cors_cmid'] == 1)
            {
                $item['k_percent'] = 100;
            	$list[$key] = $item;
            	$t_cc_id2[] = $item['cc_id'];
            	unset($result[$key]);
            }
            
            $t_cc_id[] = $item['cc_id'];
            $t_cors_id[] = $item['cors_id'];
        }
        
        $data = array();
        $t_cc_ids = array(); 
        
        //如果一对一课程记录大于3条，则根据机构声誉进行排序
        if (count($list) >= 3 && !$cc_ids && !$is_all_cors)
        {
            //如果取出来的一对一的课程刚好是3条，则直接返回即可
            if (count($list) == 3)
            {
                return $list;
            }
            
            $t_cc_ids = $t_cc_id2;
        }
        else 
        {
            //去除重复的课程学校
            $t_cc_ids = $t_cc_id;
            
            //去除重复的课程
            $t_cors_id_str = implode(',', array_unique($t_cors_id));
            
            //获取这些课程对应的知识点
            $sql = "SELECT ck_corsid, ck_kid FROM t_course_knowledge
                    WHERE ck_corsid IN ($t_cors_id_str)";
            $stmt = $db->query($sql);
            
            $cors_kids = array();
            $k_percent = array();
            
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                //知识点为全部知识点的课程，设定知识点符合度为0
                if ($item['ck_kid'] == 0)
                {
                    $k_percent[$item['ck_corsid']] = 0;
                }
                else 
                {
                    $cors_kids[$item['ck_corsid']][] = $item['ck_kid'];
                }
            }
            
            //取本次考试该学生考到的未得满分的知识点
            $sql = "SELECT knowledge_id FROM rd_summary_student_knowledge
                    WHERE paper_id = $paper_id AND uid = $uid
                    AND is_parent = 0 AND total_score > test_score
                    ";
            $knowledge = $db->fetchCol($sql);
            
            $total_knowledge = count($knowledge);
            
            foreach ($cors_kids as $cors_id => $kids)
            {
            	if (!array_diff($knowledge, $kids))
            	{
            	    $k_percent[$cors_id] = round($total_knowledge / count($kids) * 100);
            	}
            }
            
            arsort($k_percent);
            
            foreach ($k_percent as $cors_id => $percent)
            {
                foreach ($result as $key => $item)
            	{
                    if ($cors_id == $item['cors_id'])
                    {
                    	$item['k_percent'] = $percent;
                    	$list[$key] = $item;
                    	unset($result[$key]);
                    }
                }
            }
        }
        
        if ($cc_ids || $is_all_cors)
        {
            return $list;
        }
        
        $list2 = array_values($list);
        
        //如果第三条记录与第四条记录不相等，则取前三条记录即可
        if ($list2[2]['k_percent'] > $list2[3]['k_percent'])
        {
            return array_slice($list, 0, 3, true);
        }
        else
        {
            //如果第三条记录与第四条记录相等，则取大于第三条符合度的记录
            $ck_percent = $list2[2]['k_percent'];
            foreach ($list as $key => $val)
            {
                if ($val['k_percent'] > $ck_percent)
                {
                    $data[$key] = $val;
                }
            }
        }
        
        //遇到并列情况，根据机构声誉降序排序
        $list = self::reputationPush($uid, $subject_id, $paper_id, $t_cc_ids);
        if ($list)
        {
            $data = self::calreputationPush($list, $data);
            if (count($data) >= 3)
            {
                return $data;
            }
        }
        
        //遇到并列情况，根据课程价格升序排序
        $data = self::calPricePush($list, $data);
        $count = count($data);
        if ($count >= 3)
        {
            return $data;
        }
        
        //遇到并列情况，根据课程最后修改时间降序排序
        $list = self::lastModifyPush($t_cc_ids, 3 - $count);
        if ($list)
        {
            $data = self::calLastModifyPush($list, $data);
        }
        
        return $data;
    }
    
    /**
     * 按课程收费推送课程
     * @param   int     $uid            学生uid
     * @param   int     $subject_id     学生考试的学科
     * @param   int     $paper_id       学生考的试卷
     * @param   array   $cc_ids         要进行排序的校区课程
     * @param   boolean $is_all_cors    是否返回所有课程
     * @param   boolean $is_city        是否只取市级
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function pricePush($uid, $subject_id, $paper_id, $cc_ids = array(), $is_all_cors = false, $is_city = true)
    {
        if (!$uid || !$subject_id || !$paper_id)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = self::bindSql($uid, $subject_id, $cc_ids, $is_city);
        if (!$sql)
        {
            return false;
        }
        
        $data = array();
        
        $sql .= " ORDER BY cc_price2 ASC, ti.ti_reputation DESC";
        
        $result = $db->fetchAssoc($sql);
        if ((!$result || count($result) < 3) && !$cc_ids && !$is_city)
        {
            $sql = self::bindSql($uid, $subject_id, $cc_ids, true);
            if ($result)
            {
                $data = $result;
                
                $sql .= " AND cc_id NOT IN (" . implode(',', array_keys($result)) . ")";
            }
            
            $sql .= " ORDER BY cc_price2 ASC, ti.ti_reputation DESC";
            
            $result = $db->fetchAssoc($sql);
        }
        
        if (!$result)
        {
            return array();
        }
        
        if ($cc_ids || $is_all_cors)
        {
            return $result;
        }
        
        $result2 = array_values($result); 
        
        //当前推荐课程几条
        $count = 3 - count($data);
        
        //如果第三条记录与第四条记录价格不相等，则直接进行推荐
        //如果第三条记录与第四条记录价格相等，但是第三条记录与第四条记录机构声誉不相等则直接进行推荐
        if ($result2[$count-1]['cc_price'] < $result2[$count]['cc_price']
            || ($result2[$count-1]['cc_price'] == $result2[$count]['cc_price'] 
                && $result2[$count-1]['ti_reputation'] > $result2[$count]['ti_reputation']))
        {
            return array_slice($result, 0, $count, true);
        }
        
        $cc_price = $result2[$count-1]['cc_price'];
        $ti_reputation = $result2[$count-1]['ti_reputation'];
        $t_cc_ids = array();
        foreach ($result as $key => $item)
        {
            if ($cc_price > $item['cc_price']
                || ($cc_price == $item['cc_price'] 
                    && $ti_reputation < $item['ti_reputation']))
            {
                $data[$key] = $item;
            }
        	else if ($cc_price == $item['cc_price'] 
        	        && $ti_reputation == $item['ti_reputation'])
        	{
        	    $t_cc_ids[] = $item['cc_id'];
        	}
        	else
        	{
        	    break;
        	}
        }
        
        //如果按照价格和机构声誉降序排序还存在并列情况，则按照知识点符合度排序
        $list = self::knowledgePush($uid, $subject_id, $paper_id, $t_cc_ids);
        if ($list)
        {
            $data = self::calKnowledgePush($list, $data);
            $count = count($data);
            if ($count >= 3)
            {
                return $data;
            }
        }
        
        //遇到并列情况，根据课程最后修改时间降序排序
        $list = self::lastModifyPush($t_cc_ids, 3 - $count);
        if ($list)
        {
           $data = self::calLastModifyPush($list, $data);
        }
        
        return $data;
    }
    
    /**
     * 按课程最后修改时间推送
     * @param   array   $cc_ids    要进行排序的校区课程
     * @param   int     $limit     需要推荐的课程数
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function lastModifyPush($cc_ids, $limit)
    {
        if (!$cc_ids)
        {
            return false;
        }
        
        $sql = "SELECT cc.*, cors.*, ti.ti_name FROM t_course cors
                LEFT JOIN t_training_institution ti ON cors.cors_tiid = ti.ti_id
                LEFT JOIN t_course_campus cc ON cors.cors_id = cc.cc_corsid
                WHERE cc.cc_id IN (" . implode(',', $cc_ids) . ")
                ORDER BY RAND()
                LIMIT $limit
                ";
        
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 按课程最后修改时间推送
     * @param   int     $uid        学生uid
     * @param   int     $subject_id 学生考试的学科
     * @param   int     $paper_id   学生考的试卷
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function comprehensivePush($uid, $subject_id, $paper_id)
    {
        $total_sort = array();
        
        $data_list = array();
        
        //按机构声誉排序
        $list = self::reputationPush($uid, $subject_id, $paper_id, array(), true, false);
        $prev_value = 0;//上一个排名所对应的值
        $rank = 1; //排名
        $total_sort1 = array();
        foreach ($list as $cc_id => $item)
        {
            if ($prev_value > $item['ti_reputation'])
            {
                $rank++;
            }
            
            $total_sort1[$cc_id] = $rank;
            
            $prev_value = $item['ti_reputation'];
            $data_list[$cc_id] = $item;
        }
        unset($list);
        
        //按知识点符合度排序
        $list2 = self::knowledgePush($uid, $subject_id, $paper_id, array(), true, false);
        $rank = 1; //排名
        $prev_value = 0;//上一个排名所对应的值
        $total_sort2 = $total_sort1;
        foreach ($list2 as $cc_id => $item)
        {
            if (!isset($total_sort2[$cc_id]))
            {
                continue;
            }
            
            if ($prev_value > $item['k_percent'])
            {
                $rank++;
            }
            
            $total_sort2[$cc_id] += $rank;
            
            $data_list[$cc_id] = $item;
            $prev_value = $item['k_percent'];
        }
        $total_sort2 = array_diff($total_sort2, $total_sort1);
        unset($list2);
        
        //按课程价格排序
        $list3 = self::pricePush($uid, $subject_id, $paper_id, array(), true, false);
        $rank = 1; //排名
        $prev_value = 0;//上一个排名所对应的值
        $total_sort3 = $total_sort2;
        foreach ($list3 as $cc_id => $item)
        {
            if (!isset($total_sort3[$cc_id]))
            {
                continue;
            }
            
            if ($prev_value > $item['cc_price'])
            {
                $rank++;
            }
            
            $total_sort3[$cc_id] += $rank;
            
            $data_list[$cc_id] = $item;
            $prev_value = $item['cc_price'];
        }
        unset($list3);
        
        $total_sort = array_diff($total_sort3, $total_sort2);
        asort($total_sort);
        $sort = array_values($total_sort);
        
        $data = array();
        
        if (count($total_sort) < 3)
        {
            foreach ($total_sort as $cc_id => $item)
            {
                $data[$cc_id] = $data_list[$cc_id];
            }
            
            return $data;
        }
        
        if ($sort[2] < $sort[3])
        {
        	$cc_push = array_slice($total_sort, 0, 3, true);
        	foreach ($cc_push as $cc_id => $item)
        	{
        		$data[$cc_id] = $data_list[$cc_id];
        	}
        }
        else 
        {
            $cc_ids = array();
            
        	foreach ($total_sort as $cc_id => $val)
        	{
        	    if (!$cc_id)
        	    {
        	        continue;
        	    }
        	    
        		if ($sort[2] > $val)
        		{
        		    $data[$cc_id] = $data_list[$cc_id];
        		}
        		else if ($sort[2] == $val)
        		{
        		    $cc_ids[] = $cc_id;
        		}
        		else 
        		{
        			break;
        		}
        	}
        	
        	$list = self::lastModifyPush($cc_ids, 3 - count($data));
        	$data = array_merge($data, $list);
        }
        
        return $data;
    }    
    
    /**
     * 计算按照知识点符合度排序推荐的课程
     * @param   array   $list   按照价格排序列表
     * @param   array   $data   已推荐的课程列表
     * @return  array   list<map<string, variant>>类型数据
     */
    private static function calKnowledgePush($list, $data = array())
    {
        //当前还差几条推荐课程
        $count = 3 - count($data);
        //已存在的课程及关联的学校
        $ccids = array_keys($data);
        
        if (!$list || !$count)
        {
            return $data;
        }
    
        //去掉已在推荐课程中的课程
        foreach ($ccids as $cc_id)
        {
            if (isset($list[$cc_id]))
            {
                unset($list[$cc_id]);
            }
        }
    
        $list2 = array_values($list);
    
        //如果要取到的最后一条记录与下一条记录不相等，则直接取相应记录并返回
        if ($list2[$count-1]['k_percent'] > $list2[$count]['k_percent'])
        {
            $data = array_merge($data, array_slice($list, 0, $count, true));
        }
        else
        {
            //若取到的最后一条记录与后一条相等，则取大于这条记录值之前的记录
            $k_percent = $list2[$count-1]['k_percent'];
            foreach ($list as $key => $item)
            {
                if ($k_percent < $item['k_percent'])
                {
                    $data[$key] = $item;
                }
                else
                {
                    break;
                }
            }
        }
    
        return $data;
    }
    
    /**
     * 计算按照机构声誉排序推荐的课程
     * @param   array   $list   按照价格排序列表
     * @param   array   $data   已推荐的课程列表
     * @return  array   list<map<string, variant>>类型数据
     */
    private static function calreputationPush($list, $data = array())
    {
        if (!$list || !$count)
        {
            return $data;
        }
        
        //当前还差几条推荐课程
        $count = 3 - count($data);
        //已存在的课程及关联的学校
        $ccids = array_keys($data);
        
        //去掉已在推荐课程中的课程
        foreach ($ccids as $cc_id)
        {
            if (isset($list[$cc_id]))
            {
                unset($list[$cc_id]);
            }
        }
        
        $list2 = array_values($list);
        
        //如果要取到的最后一条记录与下一条记录不相等，则直接取相应记录并返回
        if ($list2[$count-1]['ti_reputation'] > $list2[$count]['ti_reputation'])
        {
            $data = array_merge($data, array_slice($list, 0, $count, true));
        }
        else 
        {
            //若取到的最后一条记录与后一条相等，则取大于这条记录值之前的记录
            $ti_reputation = $list2[$count-1]['ti_reputation'];
            foreach ($list as $key => $item)
            {
                if ($ti_reputation < $item['ti_reputation'])
                {
                    $data[$key] = $item;
                }
                else
                {
                    break;
                }
            }
        }
    
        return $data;
    }
    
    /**
     * 计算按照价格排序推荐的课程
     * @param   array   $list     按照价格排序列表
     * @param   array   $data     已推荐的课程列表
     * @param   array   $cc_ids   机构声誉相等的课程所在学校
     * @return  array   list<map<string, variant>>类型数据
     */
    private static function calPricePush($list, $data = array(), $cc_ids = array())
    {
        //当前还差几条推荐课程
        $count = 3 - count($data);
        //已存在的课程及关联的学校
        $ccids = array_keys($data);
        
        if (!$list || !$count)
        {
            return $data;
        }
        
        if ($cc_ids)
        {
            //去掉已在推荐课程中的课程
            $cc_ids = array_diff($cc_ids, $ccids);
            
            $list2 = array();
            foreach ($cc_ids as $cc_id)
            {
                $list2[] = $list[$cc_id];
            }
        }
        else
        {
        	foreach ($ccids as $cc_id)
        	{
        		if (isset($list[$cc_id]))
        		{
                    unset($list[$cc_id]);
        		}
        	}
        	
        	$list2 = array_values($list);
        }
    
        //如果要取到的最后一条记录与下一条记录不相等，则直接取相应记录并返回
        if ($list2[$count-1]['cc_price'] < $list2[$count]['cc_price'])
        {
            $data = array_merge($data, array_slice($list, 0, $count, true));
        }
        else
        {
            //若取到的最后一条记录与后一条相等，则取大于这条记录值之前的记录
            $cc_price = $list2[$count-1]['cc_price'];
            foreach ($list as $key => $item)
            {
                if ($cc_price > $item['cc_price'])
                {
                    $data[$key] = $item;
                }
                else
                {
                    break;
                }
            }
        }
    
        return $data;
    }
    
    /**
     * 计算按照课程最后修改时间排序推荐课程
     * @param   array   $list   按照价格排序列表
     * @param   array   $data   已推荐的课程列表
     * @return  array   list<map<string, variant>>类型数据
     */
    private static function calLastModifyPush($list, $data = array())
    {
        if (!$list)
        {
            return $data;
        }
        
        //已存在的课程及关联的学校
        $ccids = array_keys($data);
    
        //去掉已在推荐课程中的课程
        foreach ($ccids as $cc_id)
        {
            if (isset($list[$cc_id]))
            {
                unset($list[$cc_id]);
            }
        }
    
        $data = array_merge($data, $list);
    
        return $data;
    }
    
    /**
     * 拼接课程推荐sql语句
     * @param   int     $uid            学生uid
     * @param   int     $subject_id     学生考试的学科
     * @param   array   $cc_ids         要进行排序的校区课程
     * @param   boolean $is_city        是否取到市
     * @return  string  $sql
     */
    private static function bindSql($uid, $subject_id, $cc_ids = array(), $is_city = false)
    {
        if ($cc_ids)
        {
            $sql = "SELECT cc.*, cors.*, ti.ti_name, ti.ti_reputation, 
                    if(cc_price <= 0, 100000, cc_price) AS cc_price2
                    FROM t_course cors
                    LEFT JOIN t_training_institution ti ON cors.cors_tiid = ti.ti_id
                    LEFT JOIN t_course_campus cc ON cors.cors_id = cc.cc_corsid
                    WHERE cc.cc_id IN (" . implode(',', $cc_ids) . ")
                    ";
            
            return $sql;
        }
        
        if (!$uid || !$subject_id)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $sql = "SELECT grade_id FROM rd_student WHERE uid = $uid";
        $grade_id = $db->fetchOne($sql);
        if (!$grade_id)
        {
            return false;
        }
        
        $sql = "SELECT sbs_stunumtype FROM t_student_base_stunumtype
                WHERE sbs_uid = $uid";
        $sbs_stunumtype_str = implode(',', $db->fetchCol($sql));
        if (!$sbs_stunumtype_str)
        {
            return false;
        }
        
        $sql = "SELECT sbclassid_classid FROM t_student_base_classid
                WHERE sbclassid_uid = $uid";
        $sbclassid_classid_str = implode(',', $db->fetchCol($sql));
        if (!$sbclassid_classid_str)
        {
            return false;
        }
        
        $sql = "SELECT cc.*, cors.*, ti.ti_name, ti.ti_reputation,
                if(cc_price <= 0, 100000, cc_price) AS cc_price2
                FROM t_course cors
                LEFT JOIN t_training_institution ti ON cors.cors_tiid = ti.ti_id
                LEFT JOIN t_course_campus cc ON cors.cors_id = cc.cc_corsid
                LEFT JOIN t_course_subjectid cs ON cors.cors_id = cs.cs_corsid
                LEFT JOIN t_course_classid cci ON cors.cors_id = cci.cci_corsid
                LEFT JOIN t_course_gradeid cg ON cors.cors_id = cg.cg_corsid
                LEFT JOIN t_student_base sb ON " . ($is_city ? "sb.sb_addr_cityid = cc.cc_cityid" 
                    :  "sb.sb_addr_areaid = cc.cc_areaid") . "
                WHERE sb.sb_uid = $uid AND cs.cs_subjectid = $subject_id
                AND cg.cg_gradeid = $grade_id
                AND cors.cors_stunumtype IN ($sbs_stunumtype_str)
                AND cci_classid IN ($sbclassid_classid_str)
                AND (cc_begindate > '" . date('Y-m-d H:i:s') . "' OR cc_startanytime > 0)
                ";
        
        return $sql;
    }
}
