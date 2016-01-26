<?php
/**
 * 统计相关模型
 */
class SummaryModel
{
    private static $_exam_data = array();
    private static $_exam_subject = array();
    private static $_exam_paper_data = array();
    private static $_exam_student_data = array();
    private static $_exam_paper_ids = array();
    private static $_data = array();
    private static $_data_region = array();
    private static $_data_question = array();
    private static $_data_student_test_score = array();
    private static $_exam_student_papers = array();
    private static $_data_student_test_question = array();
    
    /**
     * 执行 试卷 相关所有关联脚本
     * @param   int     $exam_pid
     * @param   int     $uid        
     * @param   int     $paper_id   需要统计的试卷
     * @param   bool    $reset      是否重新生成试卷统计
     * @return boolean
     */
    public static function summary_paper($exam_pid, $uid = 0, $paper_id = 0, $reset = false)
    {
        set_time_limit(0);
        
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        if ($reset)
        {
            $uid = 0;
        }
         
        self::init_paper_data($exam_pid, $uid, $paper_id);
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $bOK = false;
        
        try {
            if ($db->beginTransaction())
            {
                self::summary_paper_difficulty($exam_pid, $reset);
                self::summary_paper_knowledge($exam_pid, $reset);
                self::summary_paper_method_tactic($exam_pid, $reset);
                self::summary_paper_group_type($exam_pid, $reset);
                self::summary_paper_question($exam_pid, $reset);
                
                $bOK = $db->commit();
                if (!$bOK)
                {
                    $db->rollBack();
                }
            }
        }
        catch (Exception $e)
        {
            return false;
        }
    
        self::$_exam_paper_ids = array();
        
        return $bOK;
    }
    
    /**
     * 执行 地区 相关所有关联脚本
     * @param number $exam_pid
     * @return boolean
     */
    public static function summary_region($exam_pid, $uid = 0)
    {
        set_time_limit(0);
        
        $exam_pid = intval($exam_pid);
        if (!$exam_pid) 
        {
            return false;
        }
    
        self::init_region_data($exam_pid, $uid);
    
        if (!self::$_data
            || !self::$_data_student_test_score)
        {
            return  false;
        }
        
        $db = Fn::db();
        
        $bOK = false;
        
        try {
            if ($db->beginTransaction())
            {
                self::summary_region_difficulty($exam_pid, $uid);
                self::summary_region_knowledge($exam_pid, $uid);
                self::summary_region_method_tactic($exam_pid, $uid);
                self::summary_region_group_type($exam_pid, $uid);
                self::summary_region_question($exam_pid, $uid);
                self::summary_region_student_rank($exam_pid, $uid);
                self::summary_region_subject($exam_pid, $uid);
                
                $bOK = $db->commit();
                if (!$bOK)
                {
                    $db->rollBack();
                }
            }
        }
        catch (Exception $e)
        {
            return false;
        }
        
        self::$_exam_data = array();
        self::$_exam_paper_data = array();
        self::$_exam_student_data = array();
        self::$_exam_paper_ids = array();
        self::$_data = array();
        self::$_data_question = array();
        self::$_data_student_test_score = array();
    
        return $bOK;
    }
    
    /**
    * 执行 学生相关所有关联脚本
    * @param number $exam_pid
    * @return boolean
    */
    public static function summary_student($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        self::init_student_data($exam_pid, $uid);
        
        if (!self::$_data)
        {
            return false;
        }
    
        $db = Fn::db();
        
        $bOK = false;
        try 
        {
            if ($db->beginTransaction())
            {
                self::summary_student_difficulty($exam_pid, $uid);
                self::summary_student_knowledge($exam_pid, $uid);
                self::summary_student_method_tactic($exam_pid, $uid);
                self::summary_student_group_type($exam_pid, $uid);
                self::summary_student_subject_method_tactic($exam_pid, $uid);
                
                $bOK = $db->commit();
                if (!$bOK)
                {
                   $db->rollBack();
                }
            }
        }
        catch (Exception $e)
        {
            return false;   
        }
    
        self::$_data = array();
        self::$_exam_student_papers = array();
        self::$_data_student_test_question = array();
    
        return $bOK;
    }
    
    /**
     * 统计mini测所需数据
     */
    public static function summary_demo($exam_pid, $uid)
    {
        if (!$exam_pid || !$uid)
        {
            return false;
        }
        
        self::init_paper_data($exam_pid, $uid);
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        self::init_region_data($exam_pid, $uid);
        if (!self::$_data
            || !self::$_data_student_test_score)
        {
            return  false;
        }
        
        self::init_student_data($exam_pid, $uid);
        if (!self::$_data)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $bOK = false;
        try
        {
            if ($db->beginTransaction())
            {
                self::summary_paper_question($exam_pid, $uid);
                
                self::summary_region_question($exam_pid, $uid);
                self::summary_region_student_rank($exam_pid, $uid);
        
                self::summary_student_knowledge($exam_pid, $uid);
                
                $bOK = $db->commit();
                if (!$bOK)
                {
                    $db->rollBack();
                }
            }
        }
        catch (Exception $e)
        {
            return false;
        }
                
        self::$_exam_paper_ids = array();
        self::$_exam_data = array();
        self::$_exam_paper_data = array();
        self::$_exam_student_data = array();
        self::$_exam_paper_ids = array();
        self::$_data = array();
        self::$_data_question = array();
        self::$_data_student_test_score = array();
        self::$_data = array();
        self::$_exam_student_papers = array();
        self::$_data_student_test_question = array();
        
        return $bOK;
    }
    
    /**
     * 初始化试卷所需数据
     */
    private static function init_paper_data($exam_pid, $uid = 0, $paper_id = 0)
    {
        if (!$exam_pid)
        {
            return false;   
        }
        
        if ($paper_id)
        {
            self::$_exam_paper_ids = array($paper_id);
        }
        else 
        {
            $db = Fn::db();
            
            $sql = "SELECT DISTINCT(paper_id) FROM rd_exam_test_paper
                    WHERE exam_pid = $exam_pid " . ($uid ? "AND uid = $uid" : '');
            $paper_id = $db->fetchCol($sql);
            if (!$paper_id)
            {
                return false;
            }
            
            self::$_exam_paper_ids = $paper_id;
        }
    }
    
    /**
     * 关联 难易度和题型
     */
    private static function summary_paper_difficulty($exam_pid, $reset = false)
    {
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $paper_id = self::$_exam_paper_ids;
        
        if (!$reset)
        {
            $sql = "SELECT DISTINCT(paper_id) FROM rd_summary_paper_difficulty
                WHERE paper_id IN (" . implode(',', $paper_id) . ")";
            $summary_paper = $db->fetchCol($sql);
            
            $paper_id = array_diff($paper_id, $summary_paper);
            if (!$paper_id)
            {
                return true;
            }
        }
        
        $sql = "SELECT * FROM v_summary_paper_difficulty
                WHERE exam_pid = $exam_pid
                AND paper_id IN (" . implode(',', $paper_id) . ")
                ";
    
        $query = $db->query($sql);
    
        $data = array();
    
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_id = $row['paper_id'];
            $ques_id = $row['ques_id'];
            $difficulty = $row['difficulty'];
            $type = (int)$row['type'];
            	
            if (!isset($data[$paper_id]))
            {
                $data[$paper_id] = array();
            }
            	
            if (!isset($data[$paper_id][$type])) {
                $data[$paper_id][$type] = array(
                    'paper_id'		=> $paper_id,
                    'q_type'		=> $type,
                    	
                    //试题数
                    'low_q_amount' 	=> '0',
                    'mid_q_amount' 	=> '0',
                    'high_q_amount' => '0',
    
                    //占比
                    'low_percent' => '0',
                    'mid_percent' => '0',
                    'high_percent'=> '0',
                    	
                    //关联试题ID
                    'low_ques_id' 	=> array(),
                    'mid_ques_id' 	=> array(),
                    'high_ques_id' 	=> array(),
                );
            }
            	
            if ($difficulty >= 0 && $difficulty < 30)
            {
                //难易度（高）
                $data[$paper_id][$type]['high_q_amount']++;
                $data[$paper_id][$type]['high_ques_id'][] = $ques_id;
    
            }
            else if ($difficulty >= 30 && $difficulty <= 60)
            {
                //难易度（中）
                $data[$paper_id][$type]['mid_q_amount']++;
                $data[$paper_id][$type]['mid_ques_id'][] = $ques_id;
    
            }
            else if ($difficulty > 60 && $difficulty <= 100)
            {
                //难易度（低）
                $data[$paper_id][$type]['low_q_amount']++;
                $data[$paper_id][$type]['low_ques_id'][] = $ques_id;
            }
        }
        
        unset($query);
    
        // 计算百分比
        if ($data)
        {
            //清除数据
            $db->delete('rd_summary_paper_difficulty', "paper_id = $paper_id");
            
            foreach ($data as $paper_id => $paper)
            {
                foreach ($paper as $type => $item)
                {
                    $total_amount = $item['low_q_amount'] + $item['mid_q_amount'] + $item['high_q_amount'];
                    	
                    $item['low_percent']  = $item['low_q_amount'] * 100 / $total_amount;
                    $item['mid_percent']  = $item['mid_q_amount'] * 100 / $total_amount;
                    $item['high_percent'] = $item['high_q_amount'] * 100 / $total_amount;
                    	
                    $item['low_ques_id']  = implode(',', array_unique($item['low_ques_id']));
                    $item['mid_ques_id']  = implode(',', array_unique($item['mid_ques_id']));
                    $item['high_ques_id'] = implode(',', array_unique($item['high_ques_id']));
                    	
                    $item['ctime'] = time();
                    	
                    $db->replace('rd_summary_paper_difficulty', $item);
                }
            }
        }
    
        unset($data);
    }
    
    /**
     * 关联 一级知识点
     * @note:
     * 	关联表： rd_summary_paper_knowledge
     */
    private static function summary_paper_knowledge($exam_pid, $reset = false)
    {
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $paper_id = self::$_exam_paper_ids;
        
        if (!$reset)
        {
            $sql = "SELECT DISTINCT(paper_id) FROM rd_summary_paper_knowledge
                WHERE paper_id IN (" . implode(',', $paper_id) . ")";
            $summary_paper = $db->fetchCol($sql);
            
            $paper_id = array_diff($paper_id, $summary_paper);
            if (!$paper_id)
            {
                return true;
            }
        }
         
        $sql = "SELECT * FROM v_summary_paper_knowledge
                WHERE exam_pid = $exam_pid
                AND paper_id IN (" . implode(',', $paper_id) . ")
                ";
        $query = $db->query($sql);
    
        //知识点总计出现次数
        $knowledge_count = array();
    
        //一级知识点试题
        $parent_knowledges_ques = array();
    
        //二级知识点试题及知识点认知过程对应的试题
        $child_knowledges_ques = array();
        $child_knowledges_process = array();
    
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_id = $row['paper_id'];
    
            if (!isset($knowledge_count[$paper_id]))
            {
                $knowledge_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
            }
             
            //一级知识点
            $k = $paper_id . "_" . $row['pid'];
             
            //判断是否已经循环过此试卷中对应一级知识点下的试题
            if (!isset($parent_knowledges_ques[$k])
                || !in_array($row['ques_id'], $parent_knowledges_ques[$k]))
            {
                $knowledge_count[$paper_id]['parent']++;
            }
             
            $parent_knowledges_ques[$k][] = $row['ques_id'];
             
            //二级知识点
            $knowledge_count[$paper_id]['child']++;
            $child_knowledges_ques[$k][$row['id']][] = $row['ques_id'];
            //if ($row['know_process'] > 0)
            //{
            $child_knowledges_process[$k][$row['id']][$row['know_process']][] = $row['ques_id'];
            //}
        }
    
        unset($query);
    
        // 计算百分比
        if ($parent_knowledges_ques)
        {
            //清除数据
            $db->delete('rd_summary_paper_knowledge', "paper_id = $paper_id");
            
            foreach ($parent_knowledges_ques as $key => $ques_ids)
            {
                list ($paper_id, $p_k_id) = explode('_', $key);
    
                if ($p_k_id < 1) continue;
    
                //一级知识点
                $ques_ids = array_unique($ques_ids);
                $q_mount = count($ques_ids);
    
                $percent = $q_mount * 100 / $knowledge_count[$paper_id]['parent'];
                 
                $insert_data = array(
                    'paper_id'	   => $paper_id,
                    'knowledge_id' => $p_k_id,
                    'q_amount'     => $q_mount,
                    'percent'      => $percent,
                    'ques_id'      => implode(",", $ques_ids),
                    'know_process_ques_id' => '0',
                    'is_parent'    => '1',
                    'ctime'        => time()
                );
    
                $db->replace('rd_summary_paper_knowledge', $insert_data);
    
                if (isset($child_knowledges_ques[$key])
                    && $children = $child_knowledges_ques[$key])
                {
                    foreach ($children as $k_id => $child_ques_ids)
                    {
                        if ($k_id < 1) continue;
    
                        $child_ques_ids = array_unique($child_ques_ids);
                        $q_mount = count($child_ques_ids);
                         
                        $percent = $q_mount * 100 / $knowledge_count[$paper_id]['child'];
                        $know_process_ques_id = isset($child_knowledges_process[$key][$k_id])
                        ? json_encode($child_knowledges_process[$key][$k_id]) : 0;
                         
                        $insert_data = array(
                            'paper_id'		=> $paper_id,
                            'knowledge_id'	=> $k_id,
                            'q_amount'		=> $q_mount,
                            'percent'  		=> $percent,
                            'ques_id' 		=> implode(",", $child_ques_ids) ,
                            'know_process_ques_id' => $know_process_ques_id,
                            'is_parent' 	=> '0',
                            'ctime' 		=> time()
                        );
                         
                        $db->replace('rd_summary_paper_knowledge', $insert_data);
                    }
                }
            }
        }
    
        unset($knowledge_count);
        unset($parent_knowledges_ques);
        unset($child_knowledges_process);
        unset($child_knowledges_ques);
    }
    
    /**
     * 关联 方法策略
     * @note:
     * 	关联表： rd_summary_paper_method_tactic
     */
    private static function summary_paper_method_tactic($exam_pid, $reset = false)
    {
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $paper_id = self::$_exam_paper_ids;
        
        if (!$reset)
        {
            $sql = "SELECT DISTINCT(paper_id) FROM rd_summary_paper_method_tactic
                WHERE paper_id IN (" . implode(',', $paper_id) . ")";
            $summary_paper = $db->fetchCol($sql);
            
            $paper_id = array_diff($paper_id, $summary_paper);
            if (!$paper_id)
            {
                return true;
            }
        }
    
        $sql = "SELECT * FROM v_summary_paper_method_tactic
                WHERE exam_pid = $exam_pid
                AND paper_id IN (" . implode(',', $paper_id) . ")
                ";
        $query = $db->query($sql);
    
        //方法策略总计出现次数
        $method_tactic_count = array();
    
        //方法策略试题
        $method_tactics = array();
    
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_id = $row['paper_id'];
            $method_tactic_id = intval($row['method_tactic_id']);
            	
            if (!$method_tactic_id)
            {
                continue;
            }
            	
            if (!isset($method_tactic_count[$paper_id]))
            {
                $method_tactic_count[$paper_id] = 0;
            }
            	
            $method_tactic_count[$paper_id]++;
            	
            	
            if (!isset($method_tactics[$paper_id][$method_tactic_id]))
            {
                $method_tactics[$paper_id][$method_tactic_id] = array(
                    'ques_id' => array()
                );
            }
            	
            $method_tactics[$paper_id][$method_tactic_id]['ques_id'][] = $row['ques_id'];
        }
    
        // 计算百分比
        if (count($method_tactics))
        {
            //清除数据
            $db->delete('rd_summary_paper_method_tactic', "paper_id = $paper_id");
            
            foreach ($method_tactics as $paper_id => $paper)
            {
                foreach ($paper as $method_tactic_id => $item)
                {
                    $item['ques_id'] = array_unique($item['ques_id']);
                    $item['num']     = count($item['ques_id']);
                    $item['percent'] = $item['num'] * 100/ $method_tactic_count[$paper_id];
                    	
                    $insert_data = array(
                        'paper_id'	        => $paper_id,
                        'method_tactic_id'	=> $method_tactic_id,
                        'q_amount'	        => $item['num'],
                        'percent'           => $item['percent'],
                        'ques_id'           => implode(',', $item['ques_id']) ,
                        'ctime'             => time()
                    );
                    	
                    $db->replace('rd_summary_paper_method_tactic', $insert_data);
                }
            }
        }
    
        unset($method_tactic_count);
        unset($method_tactics);
    }
    /**
     * 关联 一级题组类型（信息提取方式）
     * @note:
     * 	关联表： rd_summary_paper_group_type
     */
    private static function summary_paper_group_type($exam_pid, $reset = false)
    {
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $paper_id = self::$_exam_paper_ids;
        
        if (!$reset)
        {
            $sql = "SELECT DISTINCT(paper_id) FROM rd_summary_paper_group_type
                WHERE paper_id IN (" . implode(',', $paper_id) . ")";
            $summary_paper = $db->fetchCol($sql);
            
            $paper_id = array_diff($paper_id, $summary_paper);
            if (!$paper_id)
            {
                return true;
            }
        }
    
        $sql = "SELECT * FROM v_summary_paper_group_type
                WHERE exam_pid = $exam_pid
                AND paper_id IN (" . implode(',', $paper_id) . ")
                ";
    
        $query = $db->query($sql);
    
        //题组类型（信息提取方式）总计出现次数
        $group_type_count = array();
    
        //一级信息提取方式
        $parent_group_type_ques = array();
    
        //二级信息提取方式
        $child_group_type_ques = array();
    
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_id = $row['paper_id'];
    
            if (!isset($group_type_count[$paper_id]))
            {
                $group_type_count[$paper_id] = array('parent'=>'0', 'child'=>'0');
            }
    
            // 一级题组类型（信息提取方式）
            $k = $paper_id . "_" . $row['pid'];
             
            if (!isset($parent_group_type_ques[$k])
                || !in_array($row['ques_id'], $parent_group_type_ques[$k]))
            {
                $group_type_count[$paper_id]['parent']++;
            }
             
            $parent_group_type_ques[$k][] = $row['ques_id'];
    
            // 二级题组类型（信息提取方式）
            $group_type_count[$paper_id]['child']++;
            $child_group_type_ques[$k][$row['id']][] = $row['ques_id'];
        }
    
        // 计算百分比
        if ($parent_group_type_ques)
        {
            //清除数据
            $db->delete('rd_summary_paper_group_type', "paper_id = $paper_id");
            
            foreach ($parent_group_type_ques as $key => $ques_ids)
            {
                list($paper_id, $p_gt_id) = explode('_', $key);
    
                if ($p_gt_id < 1)
                {
                    continue;
                }
    
                $ques_ids = array_unique($ques_ids);
                $q_mount  = count($ques_ids);
                $percent  = $q_mount * 100 / $group_type_count[$paper_id]['parent'];
    
                $insert_data = array(
                    'paper_id'	    => $paper_id,
                    'group_type_id'	=> $p_gt_id,
                    'q_amount'	    => $q_mount,
                    'percent'  	    => $percent,
                    'ques_id' 	    => implode(',', $ques_ids) ,
                    'is_parent'     => '1',
                    'ctime'         => time()
                );
    
                $db->replace('rd_summary_paper_group_type', $insert_data);
    
                if (isset($child_group_type_ques[$key])
                    && $children = $child_group_type_ques[$key])
                {
                    foreach ($children as $gt_id => $child_ques_ids)
                    {
                        $child_ques_ids = array_unique($child_ques_ids);
                        $q_amount       = count($child_ques_ids);
                        $percent        = $q_amount * 100 / $group_type_count[$paper_id]['child'];
    
                        $insert_data = array(
                            'paper_id'		=> $paper_id,
                            'group_type_id'	=> $gt_id,
                            'q_amount'		=> $q_amount,
                            'percent'  		=> $percent,
                            'ques_id' 		=> implode(',', $child_ques_ids) ,
                            'is_parent' 	=> '0',
                            'ctime' 		=> time()
                        );
    
                        $db->replace('rd_summary_paper_group_type', $insert_data);
                    }
                }
            }
        }
    
        unset($group_type_count);
        unset($parent_group_type_ques);
        unset($child_group_type_ques);
    }
    
    /**
     * 关联 试题
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_paper_question($exam_pid, $reset = false)
    {
        if (!$exam_pid)
        {
            return false;
        }
        
        $db = Fn::db();
        
        $paper_id = self::$_exam_paper_ids;
        
        if (!$reset)
        {
            $sql = "SELECT DISTINCT(paper_id) FROM rd_summary_paper_question
                WHERE paper_id IN (" . implode(',', $paper_id) . ")";
            $summary_paper = $db->fetchCol($sql);
            
            $paper_id = array_diff($paper_id, $summary_paper);
            if (!$paper_id)
            {
                return true;
            }
        }
    
        $questions = array();
    
        $sql = "SELECT * FROM v_summary_paper_question
                WHERE exam_pid={$exam_pid} 
                AND paper_id IN (" . implode(',', $paper_id) . ")
                AND test_score IS NOT NULL";
    
        $result = $db->fetchAll($sql);
    
        //提取试题列表
        $ques_ids = array();
        foreach ($result as $item)
        {
            $ques_ids[] = $item['ques_id'];
        }
    
        //获取题组部分
        $parent_ques = array();
        if ($ques_ids)
        {
            $t_ques_id = implode(',', $ques_ids);
            $sql = "SELECT parent_id, COUNT(parent_id) AS `count`
                    FROM rd_question
                    WHERE parent_id IN($t_ques_id) AND is_delete = 0
                    GROUP BY parent_id
                    ";
    
            $parent_ques = $db->fetchPairs($sql);
        }
    
        if ($result)
        {
            //清除数据
            $db->delete('rd_summary_paper_question', "exam_pid = $exam_pid");
            
            foreach ($result as $item)
            {
                $test_score = $item['test_score'];
                $difficulty = $item['difficulty'];
                if (is_null($test_score)
                    || is_null($difficulty))
                {
                    continue;
                }
                
                //解答题有子题情况和组合题
                if (in_array($item['type'], array(10, 15))
                    && isset($parent_ques[$item['ques_id']]))
                {
                    if (!isset(self::$_data['paper_question_score'][$item['paper_id']]))
                    {
                        $sql = "SELECT question_score FROM rd_exam_paper
                            WHERE paper_id = " . $item['paper_id'];
                        $question_score = @json_decode($db->fetchOne($sql), true);
                        self::$_data['paper_question_score'][$item['paper_id']] = $question_score;
                    }
                    else
                    {
                        $question_score = self::$_data['paper_question_score'][$item['paper_id']];
                    }
                    
                    if (!$question_score)
                    {
                        continue;
                    }
                    
                    $item['expect_score'] = round(array_sum($question_score[$item['ques_id']]) * $difficulty / 100);
                }
                else 
                {
                    if (isset($parent_ques[$item['ques_id']]))
                    {
                        $item['expect_score'] = (round(($parent_ques[$item['ques_id']] * $test_score)) * $difficulty) / 100;
                    }
                    else
                    {
                        $item['expect_score'] = ($test_score * $difficulty)/100;
                    }
                }
                 
                unset($item['type']);
                unset($item['test_score']);
                unset($item['difficulty']);
                 
                $item['exam_pid'] = $exam_pid;
                $item['ctime'] = time();
                $item['mtime'] = time();
                 
                $db->replace('rd_summary_paper_question', $item);
            }
        }
    
        unset($result);
        unset($parent_ques);
        self::$_data['paper_question_score'] = array();
    }
    
    /**
     * 初始化地区统计需要的数据
     * @param int $exam_pid
     */
    private static function init_region_data($exam_pid, $uid)
    {
        $db = Fn::db();
        
        /*
         * 学生考试的试题信息
         */
        $stmt = $db->query("SELECT exam_id,paper_id,uid,ques_id,sub_ques_id,full_score,test_score "
            . " FROM rd_exam_test_result WHERE exam_pid = $exam_pid" . ($uid ? " AND uid = $uid" : ''));
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if ($item['sub_ques_id'] > 0)
            {
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['sub_ques_id']}";
                self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
                 
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['ques_id']}";
                if (isset(self::$_data[$k]))
                {
                    self::$_data[$k]['full_score'] += $item['full_score'];
                    self::$_data[$k]['test_score'] += $item['test_score'];
                }
                else
                {
                    self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
                }
            }
            else
            {
                $k = "{$item['exam_id']}_{$item['uid']}_{$item['ques_id']}";
                 
                self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
            }
        }
        
        /*
         * 参加考试的学生考试得分情况
         */
        $sql = "SELECT exam_id, test_score, uid FROM rd_exam_test_paper
                WHERE exam_pid = $exam_pid " . ($uid ? " AND uid = $uid" : '') . "
                GROUP BY exam_id,uid
                ORDER BY test_score DESC";
        $stmt = $db->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            self::$_data_student_test_score[$row['exam_id']][$row['uid']] = $row['test_score'];
        }
        
        if ($uid)
        {
            $sql = "SELECT CONCAT(exam_id,'_',paper_id,'_',region_id,'_',q_type,'_',is_school,'_',is_class), 
                    id, low_total_score, mid_total_score, high_total_score, 
                    low_test_score, mid_test_score, high_test_score
                    FROM rd_summary_region_difficulty 
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['difficulty'] = $db->fetchAssoc($sql);
            
            $sql = "SELECT CONCAT(exam_id,'_',paper_id,'_',region_id,'_',knowledge_id,'_',is_school,'_',is_class), 
                    id, total_score, test_score
                    FROM rd_summary_region_knowledge
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['knowledge'] = $db->fetchAssoc($sql);
            
            $sql = "SELECT CONCAT(exam_id,'_',paper_id,'_',region_id,'_',method_tactic_id,'_',is_school,'_',is_class), 
                    id, total_score, test_score
                    FROM rd_summary_region_method_tactic
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['method_tactic'] = $db->fetchAssoc($sql);
            
            $sql = "SELECT CONCAT(exam_id,'_',paper_id,'_',region_id,'_',group_type_id,'_',is_school,'_',is_class), 
                    id, total_score, test_score
                    FROM rd_summary_region_group_type
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['group_type'] = $db->fetchAssoc($sql);
            
            $sql = "SELECT CONCAT(exam_id,'_',ques_id,'_',region_id,'_',is_school,'_',is_class), 
                    id,total_score, test_score, student_amount, avg_score
                    FROM rd_summary_region_question
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['question'] = $db->fetchAssoc($sql);
            
            $sql = "SELECT CONCAT(exam_id,'_',region_id,'_',is_school,'_',is_class), id
                    FROM rd_summary_region_subject
                    WHERE exam_pid = $exam_pid";
            self::$_data_region['subject'] = $db->fetchPairs($sql);
        }
         
        unset($stmt);
    }
    
    /**
     * 关联 难易度和题型
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_difficulty($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid) 
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取 按照地域归档 的考试试卷
        $papers = self::_get_exam_papers($exam_pid);
        if (!$papers)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid, $uid);
        if (!$exam_students)
        {
            return false;
        }
    
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        $db = Fn::db();
    
        /*
         * 获取这些试卷关联的题型难易度 from->rd_summary_paper_difficulty
         */
        //所有该学科所有试卷ID
        $all_paper_ids = implode(',', self::$_exam_paper_ids);
    
        $sql = "SELECT paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id
                FROM rd_summary_paper_difficulty
                WHERE paper_id IN ($all_paper_ids)";
        $query = $db->query($sql);
    
        $paper_data = array();
        while ($item = $query->fetch(PDO::FETCH_ASSOC)) {
            $paper_data[$item['paper_id']][] = array(
                'q_type' => $item['q_type'],
                'ques_id' => array(
                    'low' => trim($item['low_ques_id']),
                    'mid' => trim($item['mid_ques_id']),
                    'high' => trim($item['high_ques_id'])
                )
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
        
        //循环每个学科进行统计
        $time = time();
        $data = array();
        $tmp_data = array();
    
        foreach ($exams as $exam_id)
        {
            if (empty($papers[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历试卷
            $tmp_papers = $papers[$exam_id];
            	
            //获取该区域的题型难易度统计信息
            foreach ($tmp_papers as $paper_id => $regions)
            {
                if (empty($paper_data[$paper_id])) continue;
                 
                //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
                        
                        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                        	
                        $paper_info = $paper_data[$paper_id];
                        foreach ($paper_info as $paper)
                        {
                            //获取题型难易度关联试题的总分和试题得分
                            $q_type = $paper['q_type'];
                            $k = $exam_id."_".$paper_id."_".$region_id."_".$is_school."_".$is_class."_".$q_type;
                            if (in_array($k, $tmp_data))
                            {
                                continue;
                            }
    
                            $ques_ids = $paper['ques_id'];
                            $total_scores = array();
                            $test_scores = array();
                            foreach ($ques_ids as $key => $ques_id)
                            {
                                $total_score = 0;
                                $test_score = 0;
                                	
                                if ($ques_id == '')
                                {
                                    $total_scores[$key] = $total_score;
                                    $test_scores[$key] = $test_score;
                                    continue;
                                }
                                	
                                $t_ques_ids = explode(',', $ques_id);
                                	
                                foreach ($uids as $_uid)
                                {
                                    foreach ($t_ques_ids as $ques_id)
                                    {
                                        $tmp_k = $exam_id . "_" . $_uid . "_" . $ques_id;
    
                                        $total_score += self::$_data[$tmp_k]['full_score'];
                                        $test_score  += self::$_data[$tmp_k]['test_score'];
                                    }
                                }
                                
                                $total_scores[$key] = $total_score;
                                $test_scores[$key] = $test_score;
                            }
                            
                            $key = implode("_", array($exam_id, $paper_id,
                                $region_id, $q_type, $is_school, $is_class));
                            
                            if ($uid && $summary = self::$_data_region['difficulty'][$key])
                            {
                                $data = array(
                                    'low_total_score'	=> $summary['low_total_score'] + $total_scores['low'],
                                    'mid_total_score'	=> $summary['mid_total_score'] + $total_scores['mid'],
                                    'high_total_score'	=> $summary['high_total_score'] + $total_scores['high'],
                                    'low_test_score'	=> $summary['low_test_score'] + $test_scores['low'],
                                    'mid_test_score'	=> $summary['mid_test_score'] + $test_scores['mid'],
                                    'high_test_score'	=> $summary['high_test_score'] + $test_scores['high'],
                                    'ctime' => $time
                                );
                                
                                $where = "id = ?";
                                
                                $db->update('rd_summary_region_difficulty', $data, 
                                    $where, array($summary['id']));
                            }
                            else
                            {
                                $data = array(
                                    'exam_pid'	=> $exam_pid,
                                    'exam_id' 	=> $exam_id,
                                    'paper_id' 	=> $paper_id,
                                    'region_id' => $region_id,
                                    'is_school' => $is_school,
                                    'is_class'  => $is_class,
                                    'q_type'	=> $q_type,
                                    'low_total_score'	=> $total_scores['low'],
                                    'mid_total_score'	=> $total_scores['mid'],
                                    'high_total_score'	=> $total_scores['high'],
                                    'low_test_score'	=> $test_scores['low'],
                                    'mid_test_score'	=> $test_scores['mid'],
                                    'high_test_score'	=> $test_scores['high'],
                                    'low_ques_id'		=> $ques_ids['low'] ?  $ques_ids['low']  : '0',
                                    'mid_ques_id'		=> $ques_ids['mid'] ?  $ques_ids['mid']   : '0',
                                    'high_ques_id'		=> $ques_ids['high'] ?  $ques_ids['high']   : '0',
                                    'ctime' => $time
                                );
                                
                                $db->replace('rd_summary_region_difficulty', $data);
                            }
    
                            $tmp_data[] = $k;
                        }
                    }
                }
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_difficulty', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($region_arr);
        unset($tmp_data);
        unset($paper_data);
        unset($data);
        unset($papers);
        unset($exams);
        unset($exam_students);
    }
    
    /**
     * 关联 一级知识点
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_knowledge($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取 按照地域归档 的考试试卷
        $papers = self::_get_exam_papers($exam_pid);
        if (!$papers)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid, $uid);
        if (!$exam_students)
        {
            return false;
        }
    
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        $db = Fn::db();
    
        /*
         * 获取这些试卷关联的知识点 from->rd_summary_paper_knowledge
         */
        //所有该学科所有试卷ID
        $all_paper_ids = implode(',', self::$_exam_paper_ids);
        $sql = "SELECT paper_id, knowledge_id, is_parent, ques_id
                FROM rd_summary_paper_knowledge
                WHERE paper_id IN($all_paper_ids)";
        $query = $db->query($sql);
    
        $paper_data = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'knowledge_id' 	=> $item['knowledge_id'],
                'ques_id' 		=> $item['ques_id'],
                'is_parent' 	=> $item['is_parent']
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        //循环每个学科进行统计
        $data = array();
        $time = time();
    
        foreach ($exams as $exam_id)
        {
            if (empty($papers[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历试卷
            $tmp_papers = $papers[$exam_id];
            	
            //获取该区域的知识点统计信息
            foreach ($tmp_papers as $paper_id => $regions)
            {
                if (empty($paper_data[$paper_id])) continue;
                 
                //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
                        
                        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                        	
                        $paper_info = $paper_data[$paper_id];
                        foreach ($paper_info as $paper)
                        {
                            //获取知识点关联试题的总分和试题得分
                            $knowledge_id = $paper['knowledge_id'];
                            $ques_ids = trim($paper['ques_id']);
                            $is_parent = $paper['is_parent'];
                            	
                            if ($ques_ids == '') continue;
    
                            $total_score = 0;
                            $test_score = 0;
                            
                            $t_ques_ids = explode(',', $ques_ids);
                            foreach ($uids as $_uid)
                            {
                                foreach ($t_ques_ids as $ques_id)
                                {
                                    $k = $exam_id . "_" . $_uid . "_" . $ques_id;
                                    
                                    $total_score += self::$_data[$k]['full_score'];
                                    $test_score  += self::$_data[$k]['test_score'];
                                }
                            }
    
                            $key = implode("_", array($exam_id, $paper_id,$region_id, $knowledge_id, $is_school,$is_class));
                            
                            if ($uid && $summary = self::$_data_region['knowledge'][$key])
                            {
                                $data = array(
                                    'total_score' 	=> $summary['total_score'] + $total_score,
                                    'test_score' 	=> $summary['test_score'] + $test_score,
                                    'ctime'         => $time
                                );
                                
                                $where = "id = ?";
                                
                                $db->update('rd_summary_region_knowledge', $data, 
                                    $where, array($summary['id']));
                            }
                            else 
                            {
                                $data = array(
                                    'exam_pid'	=> $exam_pid,
                                    'exam_id' 	=> $exam_id,
                                    'paper_id' 	=> $paper_id,
                                    'region_id' => $region_id,
                                    'is_school' => $is_school,
                                    'is_class'  => $is_class,
                                    'knowledge_id'	=> $knowledge_id,
                                    'ques_id'		=> $ques_ids ,
                                    'total_score' 	=> $total_score,
                                    'test_score' 	=> $test_score,
                                    'is_parent' 	=> $is_parent,
                                    'ctime'         => $time
                                );
                                
                                $db->replace('rd_summary_region_knowledge', $data);
                            }
                        }
                    }
                }
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_knowledge', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($region_arr);
        unset($paper_data);
        unset($data);
        unset($papers);
        unset($exams);
        unset($exam_students);
    }
    
    /**
     * 关联 方法策略
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_method_tactic($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取 按照地域归档 的考试试卷
        $papers = self::_get_exam_papers($exam_pid);
        if (!$papers)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid, $uid);
        if (!$exam_students)
        {
            return false;
        }
    
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        $db = Fn::db();
    
        /*
         * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
         */
        //所有该学科所有试卷ID
        $all_paper_ids = implode(',', self::$_exam_paper_ids);
        $sql = "SELECT paper_id, method_tactic_id, ques_id
                FROM rd_summary_paper_method_tactic
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'method_tactic_id' 	=> $item['method_tactic_id'],
                'ques_id' 		=> $item['ques_id'],
            );
        }

        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        //循环每个学科进行统计
        $data = array();
        $time = time();
        foreach ($exams as $exam_id)
        {
            if (empty($papers[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历区域
            $tmp_papers = $papers[$exam_id];
            	
            //获取该区域的方法策略统计信息
            foreach ($tmp_papers as $paper_id => $regions)
            {
                if (empty($paper_data[$paper_id])) continue;
                 
                //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
                        
                        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                        	
                        $paper_info = $paper_data[$paper_id];
                        foreach ($paper_info as $paper)
                        {
                            //获取方法策略关联试题的总分和试题得分
                            $method_tactic_id = $paper['method_tactic_id'];
                            $ques_ids = trim($paper['ques_id']);
    
                            if ($ques_ids == '') continue;
    
                            $total_score = 0;
                            $test_score = 0;
    
                            $t_ques_ids = explode(',', $ques_ids);
                            foreach ($uids as $_uid)
                            {
                                foreach ($t_ques_ids as $ques_id)
                                {
                                    $k = $exam_id . "_" . $_uid . "_" . $ques_id;
                                    	
                                    $total_score += self::$_data[$k]['full_score'];
                                    $test_score  += self::$_data[$k]['test_score'];
                                }
                            }
    
                            $key = implode('_', array($exam_id, $paper_id,
                                $region_id, $method_tactic_id, $is_school, $is_class));
                            
                            if ($uid && $summary = self::$_data_region['method_tactic'][$key])
                            {
                                $data = array(
                                    'total_score' 	=> $summary['total_score'] + $total_score,
                                    'test_score' 	=> $summary['test_score'] + $test_score,
                                    'ctime'         => $time
                                );
                                
                                $where = "id = ?";
                                
                                
                                $db->update('rd_summary_region_method_tactic', 
                                    $data, $where, array($summary['id']));
                            }
                            else
                            {
                                $data = array(
                                    'exam_pid'	=> $exam_pid,
                                    'exam_id' 	=> $exam_id,
                                    'paper_id' 	=> $paper_id,
                                    'region_id' => $region_id,
                                    'is_school' => $is_school,
                                    'is_class'  => $is_class,
                                    'method_tactic_id'	=> $method_tactic_id,
                                    'ques_id'		=>  $ques_ids ,
                                    'total_score' 	=> $total_score,
                                    'test_score' 	=> $test_score,
                                    'ctime'         => $time
                                );
                                
                                $db->replace('rd_summary_region_method_tactic', $data);
                            }
                        }
                    }
                }
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($region_arr);
        unset($paper_data);
        unset($data);
        unset($papers);
        unset($exams);
        unset($exam_students);
    }
    
    /**
     * 关联 试题
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_question($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取本期考试考到的试题
        $exam_questions = self::_get_exam_ques($exam_pid);
        if (!$exam_questions)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid, $uid);
        if (!count($exam_students))
        {
            return false;
        }
        
        $db = Fn::db();
    
        /*
         * 获取这些试题的：试题总分、答该题总人数、平均分
         */
        $data = array();
        $time = time();
    
        foreach ($exams as $exam_id)
        {
            if (empty($exam_questions[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历区域
            $tmp_questions = $exam_questions[$exam_id];
           
            //获取该区域的统计信息
            foreach ($tmp_questions as $region_id => $ques_ids)
            {
                $uids = $exam_students[$exam_id][$region_id];
                if (!$uids)
                {
                    continue;
                }
                
                $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                 
                foreach ($ques_ids as $ques_id)
                {
                    $total_score = 0;
                    $test_score  = 0;
                    $student_amount = 0;
                     
                    foreach ($uids as $_uid)
                    {
                        $k = "{$exam_id}_{$_uid}_{$ques_id}";
                        if (isset(self::$_data[$k]))
                        {
                            $total_score += self::$_data[$k]['full_score'];
                            $test_score  += self::$_data[$k]['test_score'];
                            $student_amount++;
                        }
                    }
                    
                    $key = implode('_', array($exam_id, $ques_id,
                            $region_id, $is_school,$is_class));
                    
                    if ($uid && $summary = self::$_data_region['question'][$key])
                    {
                        $data = array(
                            'total_score' 	=> $summary['total_score'] + $total_score,
                            'test_score' 	=> $summary['test_score'] + $test_score,
                            'student_amount'=> $summary['student_amount'] + 1,
                            'avg_score' 	=> ($summary['test_score'] + $test_score)/($summary['student_amount'] + 1),
                            'ctime'         => $time
                        );
                        
                        $where = "id = ?";
                        
                        $db->update('rd_summary_region_question', $data, 
                            $where, array($summary['id']));
                    }
                    else
                    {
                        $data = array(
                            'exam_pid'	        => $exam_pid,
                            'exam_id' 	        => $exam_id,
                            'ques_id' 	        => $ques_id,
                            'region_id'         => $region_id,
                            'is_school'         => $is_school,
                            'is_class'          => $is_class,
                            'total_score'		=> $total_score,
                            'test_score'		=> $test_score,
                            'student_amount' 	=> $student_amount,
                            'avg_score' 		=> ($student_amount ? $test_score/$student_amount : 0),
                            'ctime'             => $time
                        );
                         
                        $db->replace('rd_summary_region_question', $data);
                    }
                }
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_question', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($exam_questions);
        unset($exams);
        unset($tmp_questions);
    }
    
    /**
     * 学生排名
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_student_rank($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        //生成该学生排名
        if ($uid)
        {
            return self::_gen_student_rank($exam_pid, $uid);
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
        
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid);
        if (!$exam_students)
        {
            return false;
        }
    
        $db = Fn::db();
        
        /*
         * 对这些考生进行得分排名
         */
    
        //循环每个学科进行统计
        $data = array();
        $time = time();
        foreach ($exams as $exam_id)
        {
            if (empty($exam_students[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历区域
            $tmp_students = $exam_students[$exam_id];
    
            //获取该区域的统计信息
            foreach ($tmp_students as $region_id => $uids)
            {
                if (!$uids || empty(self::$_data_student_test_score[$exam_id]))
                {
                    continue;
                }
                 
                $tmp_data = self::$_data_student_test_score[$exam_id];
                 
                $tmp_tank = 0;
                $rank = 0;
                
                $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                 
                //对这些学生进行得分排名
                foreach ($tmp_data as $u_id => $test_score)
                {
                    if (!in_array($u_id, $uids))
                    {
                        continue;
                    }
                     
                    $tmp_tank++;
                     
                    if (!isset($old_test_score) || $test_score != $old_test_score)
                    {
                        if ($tmp_tank != $rank)
                        {
                            $rank = $tmp_tank;
                        }
                        else
                        {
                            $rank++;
                        }
                    }
    
                    $old_test_score = $test_score;
    
                    $data = array(
                        'exam_pid'	=> $exam_pid,
                        'exam_id' 	=> $exam_id,
                        'region_id' => $region_id,
                        'is_school' => $is_school,
                        'is_class'  => $is_class,
                        'uid' 		=> $u_id,
                        'rank'		=> $rank,
                        'test_score'=> $test_score,
                        'ctime'     => $time
                    );
                    
                    $db->replace('rd_summary_region_student_rank', $data);
                }
    
                unset($old_test_score);
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_student_rank', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($exams);
        unset($exam_students);
        unset($tmp_data);
    }
    
    /**
     * 学科
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_subject($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid, $uid);
        if (!$exam_students)
        {
            return false;
        }
        
        $db = Fn::db();
    
        //循环每个学科进行统计
        $data = array();
        $time = time();
        foreach ($exams as $exam_id)
        {
            if (empty($exam_students[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历区域
            $tmp_students = $exam_students[$exam_id];
    
            //获取该区域的统计信息
            foreach ($tmp_students as $region_id => $uids)
            {
                $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                
                $key = implode('_', array($exam_id,$region_id, $is_school, $is_class));
                
                if ($uid && $id = self::$_data_region['subject'][$key])
                {
                    $sql = "UPDATE rd_summary_region_subject SET 
                            student_amount = student_amount + 1
                            WHERE id = ?";
                    
                    $db->query($sql, array($id));
                }
                else 
                {
                    $data = array(
                        'exam_pid'	=> $exam_pid,
                        'exam_id' 	=> $exam_id,
                        'region_id' => $region_id,
                        'is_school' => $is_school,
                        'is_class'  => $is_class,
                        'student_amount' => count($uids),
                        'ctime'     => $time
                    );
                    
                    $db->replace('rd_summary_region_subject', $data);
                }
            }
        }
    
        if ($data && !$uid)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_subject', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($exams);
        unset($exam_students);
        unset($tmp_students);
    }
    
    /**
     * 关联 信息提取方式
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_region_group_type($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
    
        //获取考试学科
        $exams = self::_get_test_exams($exam_pid);
        if (!$exams)
        {
            return false;
        }
    
        //获取 按照地域归档 的考试试卷
        $papers = self::_get_exam_papers($exam_pid);
        if (!$papers)
        {
            return false;
        }
    
        //获取参与本期考试的所有考生
        $exam_students = self::_get_exam_students($exam_pid);
        if (!$exam_students)
        {
            return false;
        }
    
        if (!self::$_exam_paper_ids)
        {
            return false;
        }
        
        $db = Fn::db();
         
        /*
         * 获取这些试卷关联的信息提取方式 from->rd_summary_paper_group_type
         */
        //所有该学科所有试卷ID
        $all_paper_ids = implode(',', self::$_exam_paper_ids);
         
        $sql = "SELECT paper_id, group_type_id, is_parent, ques_id
                FROM rd_summary_paper_group_type
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
         
        $paper_data = array();
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'group_type_id' => $item['group_type_id'],
                'ques_id' 		=> $item['ques_id'],
                'is_parent' 	=> $item['is_parent']
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
         
        //循环每个学科进行统计
        $data = array();
        $time = time();
        foreach ($exams as $exam_id)
        {
            if (empty($papers[$exam_id]))
            {
                continue;
            }
            
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
    
            //遍历区域
            $tmp_papers = $papers[$exam_id];
    
            //获取该区域的信息提取方式统计信息
            foreach ($tmp_papers as $paper_id => $regions)
            {
                if (empty($paper_data[$paper_id])) continue;
                 
                //已统计过的地区
                $region_arr = array();
                
                foreach ($regions as $region)
                {
                    foreach ($region as $region_id)
                    {
                        if (in_array($region_id, $region_arr))
                        {
                            continue;
                        }
                        
                        $uids = isset($exam_students[$exam_id][$region_id]) ? $exam_students[$exam_id][$region_id] : array();
                        if (!$uids)
                        {
                            continue;
                        }
                        
                        $region_arr[] = $region_id;
                        
                        $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                        $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                        $region_id = str_ireplace(array('school', 'class'), array('', ''), $region_id);
                         
                        $paper_info = $paper_data[$paper_id];
                        foreach ($paper_info as $paper)
                        {
                            //获取知识点关联试题的总分和试题得分
                            $group_type_id = $paper['group_type_id'];
                            $ques_ids = trim($paper['ques_id']);
                            $is_parent = $paper['is_parent'];
    
                            if ($ques_ids == '' || $group_type_id < 1) continue;
    
                            //将试题拆分为 题组 和 非题组
                            $total_score = 0;
                            $test_score = 0;
                             
                            $t_ques_ids = explode(',', $ques_ids);
                            foreach ($uids as $_uid)
                            {
                                foreach ($t_ques_ids as $ques_id)
                                {
                                    $k = $exam_id . "_" . $_uid . "_" . $ques_id;
    
                                    $total_score += self::$_data[$k]['full_score'];
                                    $test_score  += self::$_data[$k]['test_score'];
                                }
                            }
                             
                            $key = implode('_', array($exam_id, $paper_id,
                                    $region_id, $group_type_id, $is_school, $is_class));
    
                            if ($uid && $summary = self::$_data_region['group_type'][$key])
                            {
                                $data = array(
                                    'total_score' 	=> $summary['total_score'] + $total_score,
                                    'test_score' 	=> $summary['test_score'] + $test_score,
                                    'ctime'         => $time
                                );
                                
                                $where = "id = ?";
                                
                                $db->update('rd_summary_region_group_type', 
                                    $data, $where, array($summary['id']));
                            }
                            else
                            {
                                $data = array(
                                    'exam_pid'	=> $exam_pid,
                                    'exam_id' 	=> $exam_id,
                                    'paper_id' 	=> $paper_id,
                                    'region_id' => $region_id,
                                    'is_school' => $is_school,
                                    'is_class'  => $is_class,
                                    'group_type_id'	=> $group_type_id,
                                    'ques_id'		=> $ques_ids ,
                                    'total_score' 	=> $total_score,
                                    'test_score' 	=> $test_score,
                                    'is_parent' 	=> $is_parent,
                                    'ctime'         => $time
                                );
        
                                $db->replace('rd_summary_region_group_type', $data);
                            }
                        }
                    }
                }
            }
        }
    
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_region_group_type', "exam_pid = $exam_pid AND ctime < $time");
        }
         
        unset($region_arr);
        unset($paper_data);
        unset($data);
        unset($papers);
        unset($exams);
        unset($exam_students);
    }
    
    
    //=====================公共方法===============================================================
    /**
     * 获取某期考试 按照地域分类，从高->低
     * note:
     * 		关联表： rd_exam_place rd_school
     */
    private static function _get_exam_papers($exam_pid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return array();
        }
        
        if (self::$_exam_paper_data)
        {
            return self::$_exam_paper_data;
        }
        
        $db = Fn::db();
        
        $sql = "SELECT * FROM v_summary_region_exam_paper
                WHERE exam_pid={$exam_pid}
                ";
        $query = $db->query($sql);
        $data = array();
        
        //自由考试根据评估规则对比等级生成统计信息
        $sql = "SELECT comparison_level FROM rd_evaluate_rule er
                LEFT JOIN rd_exam e ON e.exam_id = er.exam_pid
                WHERE er.exam_pid = $exam_pid AND e.exam_isfree = 1";
        $comparison_level = $db->fetchOne($sql);
        if (strlen($comparison_level) > 0)
        {
            $comparison_level = explode(',', $comparison_level);
        }
        
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            self::$_exam_paper_ids[] = $item['paper_id'];
    
            if ($comparison_level)
            {
                $tmp_arr = array();
                if (in_array(0, $comparison_level) 
                    || in_array(-1, $comparison_level))
                {
                    $tmp_arr[] = 1;
                }
                
                if (in_array(1, $comparison_level))
                {
                    $tmp_arr[] = $item['province'];
                }
                
                if (in_array(2, $comparison_level))
                {
                    $tmp_arr[] = $item['city'];
                }
                
                if (in_array(3, $comparison_level))
                {
                    $tmp_arr[] = $item['area'];
                }
                
                if (in_array(100, $comparison_level))
                {
                    $tmp_arr[] = 'school' . $item['school_id'];
                }
                
                $data[$item['exam_id']][$item['paper_id']][] = $tmp_arr;
            }
            else 
            {
                $arr = array(
                    0 => 1,
                    1 => $item['province'],
                    2 => $item['city'],
                    3 => $item['area'],
                    4 => 'school' . $item['school_id'],
                );
                
                if ($item['place_schclsid'] > 0)
                {
                    $arr[5] = 'class' . $item['place_schclsid'];
                }
                
                $data[$item['exam_id']][$item['paper_id']][] = $arr;
            }
        }
        
        self::$_exam_paper_data = $data;
    
        return $data;
    }
    
    /**
     * 获取某期考试 按照地域 归档的考生id
     * note:
     * 		关联表：  rd_exam_place rd_school
     */
    private static function _get_exam_students($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return array();
        }
         
        if (self::$_exam_student_data)
        {
            return self::$_exam_student_data;
        }
    
        $data = array();
        
        $db = Fn::db();
        
        //自由考试根据评估规则对比等级生成统计信息
        $sql = "SELECT comparison_level FROM rd_evaluate_rule er
                LEFT JOIN rd_exam e ON e.exam_id = er.exam_pid
                WHERE er.exam_pid = $exam_pid AND e.exam_isfree = 1";
        $comparison_level = $db->fetchOne($sql);
        if (strlen($comparison_level) > 0)
        {
            $comparison_level = explode(',', $comparison_level);
        }
        else 
        {
            $sql = "SELECT eps.uid, ep.place_schclsid FROM rd_exam_place ep
                    LEFT JOIN rd_exam_place_student eps ON eps.place_id = ep.place_id
                    WHERE exam_pid = $exam_pid AND ep.place_schclsid > 0
                    " . ($uid ? "AND eps.uid = $uid" : '');
            $uid_class = $db->fetchPairs($sql);
        }
        	
        $sql = "SELECT * FROM v_summary_region_exam_student
                WHERE exam_pid={$exam_pid}
                " . ($uid ? "AND uid = $uid" : '');
        $query = $db->query($sql);
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            if ($comparison_level)
            {
                if (in_array(0, $comparison_level)
                    || in_array(-1, $comparison_level))
                {
                    $data[$item['exam_id']][1][] = $item['uid'];
                }
                
                if (in_array(1, $comparison_level))
                {
                    $data[$item['exam_id']][$item['province']][] = $item['uid'];
                }
                
                if (in_array(2, $comparison_level))
                {
                    $data[$item['exam_id']][$item['city']][] = $item['uid'];
                }
                
                if (in_array(3, $comparison_level))
                {
                    $data[$item['exam_id']][$item['area']][] = $item['uid'];
                }
                
                if (in_array(100, $comparison_level))
                {
                    $data[$item['exam_id']]['school' . $item['school_id']][] = $item['uid'];
                }
            }
            else 
            {
                $data[$item['exam_id']][1][] = $item['uid'];
                $data[$item['exam_id']][$item['province']][] = $item['uid'];
                $data[$item['exam_id']][$item['city']][] = $item['uid'];
                $data[$item['exam_id']][$item['area']][] = $item['uid'];
                $data[$item['exam_id']]['school' . $item['school_id']][] = $item['uid'];
                
                if (isset($uid_class[$item['uid']])
                    && $uid_class[$item['uid']] > 0)
                {
                    $data[$item['exam_id']]['class' . $uid_class[$item['uid']]][] = $item['uid'];
                }
            }
        }
        
        self::$_exam_student_data = $data;
    
        return $data;
    }
    
    /**
     * 获取某期考试 按照地域 归档的试题id
     * note:
     * 		关联表：  rd_exam_place rd_school
     */
    private static function _get_exam_ques($exam_pid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return array();
        }
    
        $data = array();
    
        //获取按区域归档的考试试卷
        $paper_data = self::_get_exam_papers($exam_pid);
        if (!$paper_data)
        {
            return array();
        }
    
        $t_paper_id_str = implode(',', array_unique(self::$_exam_paper_ids));
    
        $sql = "SELECT ques_id, exam_id, paper_id
                FROM rd_exam_question_score
                WHERE exam_pid=$exam_pid AND paper_id IN ($t_paper_id_str)";
        $query = Fn::db()->query($sql);
    
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $exam_id = $row['exam_id'];
            $paper_id = $row['paper_id'];
            foreach ($paper_data[$exam_id][$paper_id] as $region)
            {
                foreach ($region as $region_id) 
                {
                    if (isset($data[$exam_id][$region_id])
                        && in_array($row['ques_id'], $data[$exam_id][$region_id]))
                    {
                        continue;
                    }
                    	
                    $data[$exam_id][$region_id][] = $row['ques_id'];
                }
            }
        }
    
        return $data;
    }
    
    /**
     * 获取某考试期次下考试学科
     */
    private static function _get_test_exams($exam_pid = 0)
    {
        if (!empty(self::$_exam_data[$exam_pid]))
        {
            return self::$_exam_data[$exam_pid];
        }
         
        $exam_pid = intval($exam_pid);
        if (!$exam_pid) 
        {
            return array();
        }
         
        $sql = "SELECT exam_id, subject_id FROM rd_exam 
                WHERE exam_pid=$exam_pid";
        self::$_exam_subject = Fn::db()->fetchPairs($sql);
        self::$_exam_data[$exam_pid] = array_keys(self::$_exam_subject);
        
        return self::$_exam_data[$exam_pid];
    }
    
    /**
     * 计算学生在地区排名情况
     */
    private static function _gen_student_rank($exam_pid = 0, $uid = 0)
    {
        if (!$exam_pid || !$uid)
        {
            return false;
        }
        
        $exams = self::_get_test_exams($exam_pid);
        $exam_student = self::_get_exam_students($exam_pid, $uid);
        
        $db = Fn::db();
        
        foreach ($exams as $exam_id)
        {
            $test_score = self::$_data_student_test_score[$exam_id][$uid];
            $test_score = $test_score ? $test_score : 0;
            
            $sql = "SELECT CONCAT(region_id,'_',is_school,'_',is_class) AS `key`, COUNT(uid)
                    FROM rd_summary_region_student_rank
                    WHERE exam_id = $exam_id AND test_score > $test_score
                    GROUP BY region_id, is_school, is_class
                    ";
            
            $region_student = $exam_student[$exam_id];
            
            $list = $db->fetchPairs($sql);
            
            foreach ($region_student as $region_id => $uids)
            {
                $is_school = stripos($region_id, 'school') === false ? '0' : '1';
                $is_class = stripos($region_id, 'class') === false ? '0' : '1';
                $region_id = str_ireplace(array('school','class'), array('',''), $region_id);
                
                if (!$list)
                {
                    if (!in_array($uid, $uids))
                    {
                        continue;
                    }
                    
                    $data = array(
                        'exam_pid'	=> $exam_pid,
                        'exam_id' 	=> $exam_id,
                        'region_id' => $region_id,
                        'is_school' => $is_school,
                        'is_class'  => $is_class,
                        'uid' 		=> $uid,
                        'rank'		=> 1,
                        'test_score'=> $test_score,
                        'ctime'     => time()
                    );
                    
                    $db->replace('rd_summary_region_student_rank', $data);
                    
                    continue;
                }

                foreach ($list as $key => $amount)
                {
                    if (!in_array($uid, $uids) 
                        || $key != $region_id . "_" . $is_school. "_" . $is_class)
                    {
                        continue;
                    }
                    
                    $sql = "SELECT rank FROM rd_summary_region_student_rank
                            WHERE exam_id = $exam_id AND region_id = $region_id
                            AND uid = $uid AND is_school = $is_school 
                            AND is_class = $is_class";
                    if ($db->fetchOne($sql))
                    {
                        continue;
                    }
                    
                    //更新当前排名之后的排名
                    $where_bind = array($exam_id, $region_id, $test_score, $is_school, $is_class);
                    $sql = "UPDATE rd_summary_region_student_rank SET rank = rank + 1
                            WHERE exam_id = ? AND region_id = ? AND test_score < ?
                            AND is_school = ? AND is_class = ?";
                    $db->query($sql, $where_bind);
                    
                    $data = array(
                        'exam_pid'	=> $exam_pid,
                        'exam_id' 	=> $exam_id,
                        'region_id' => $region_id,
                        'is_school' => $is_school,
                        'is_class'  => $is_class,
                        'uid' 		=> $uid,
                        'rank'		=> $amount + 1,
                        'test_score'=> $test_score,
                        'ctime'     => time()
                    );
                    
                    $db->replace('rd_summary_region_student_rank', $data);
                    
                    unset($list[$region_id]);
                    
                    break;
                }
            }
        }
        
        return true;
    }
    
    /**
     * 初始化学生统计数据
     */
    private static function init_student_data($exam_pid, $uid)
    {
        /*
         * 学生考试的试题信息
         */
        
        $db = Fn::db();
         
        $stmt = $db->query("SELECT exam_id,paper_id,uid,ques_id,sub_ques_id,full_score,test_score "
            . "FROM rd_exam_test_result WHERE exam_pid = $exam_pid" . ($uid ? " AND uid = $uid" : ''));
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            if ($item['sub_ques_id'] > 0)
            {
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['sub_ques_id']}";
                self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
    
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
                if (isset(self::$_data[$k]))
                {
                    self::$_data[$k]['full_score'] += $item['full_score'];
                    self::$_data[$k]['test_score'] += $item['test_score'];
                }
                else
                {
                    self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
                }
            }
            else
            {
                $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
    
                self::$_data[$k] = array('full_score'=>$item['full_score'], 'test_score'=>$item['test_score']);
            }
        }
    
        /*
         * 获取方法策略关联试题的试题得分及试题难易度
         */
        unset($stmt);
        $sql = "SELECT COUNT(*) FROM rd_exam_test_result etr
                LEFT JOIN rd_exam e ON etr.exam_id=e.exam_id
                LEFT JOIN rd_relate_class rc ON etr.ques_id=rc.ques_id
                AND rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                AND rc.subject_type=e.subject_type
                WHERE etr.exam_pid=$exam_pid";
        $total = $db->fetchOne($sql);
        
        $total_page = ceil($total / 10000);
        
        for ($page = 1; $page <= $total_page; $page++)
        {
            $perpage = ($page - 1) * 10000;
            $sql = "SELECT etr.exam_id,paper_id,etr.uid,
                    SUM(etr.test_score) as test_score,
                    rc.difficulty,etr.ques_id,sub_ques_id
                    FROM rd_exam_test_result etr
                    LEFT JOIN rd_exam e ON etr.exam_id=e.exam_id
                    LEFT JOIN rd_relate_class rc ON etr.ques_id=rc.ques_id
                    AND rc.grade_id=e.grade_id AND rc.class_id=e.class_id
                    AND rc.subject_type=e.subject_type
                    WHERE etr.exam_pid=$exam_pid
                    " . ($uid ? " AND etr.uid = $uid" : '') . "
                    GROUP BY etr.etp_id,etr.ques_id,etr.sub_ques_id
                    LIMIT 10000 OFFSET $perpage
                    ";
            $stmt = $db->query($sql);
            while ($item = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                if ($item['sub_ques_id'] > 0)
                {
                    $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['sub_ques_id']}";
                    self::$_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
                     
                    $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
                    if (isset(self::$_data_student_test_question[$k]))
                    {
                        self::$_data_student_test_question[$k]['test_score'] += $item['test_score'];
                    }
                    else
                    {
                        self::$_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
                    }
                }
                else
                {
                    $k = "{$item['exam_id']}_{$item['paper_id']}_{$item['uid']}_{$item['ques_id']}";
                     
                    self::$_data_student_test_question[$k] = array('difficulty'=>$item['difficulty'], 'test_score'=>$item['test_score']);
                }
            }
        }
         
        unset($stmt);
    }
    
    /**
     * 关联 难易度和题型
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_student_difficulty($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        self::_get_test_exams($exam_pid);
    
        //获取 考生的  的考试试卷
        $papers = self::_get_exam_student_papers($exam_pid, $uid);
        if (!$papers || empty($papers['paper']))
        {
            return false;
        }
    
        /*
         * 获取这些试卷关联的难易度和题型 from->rd_summary_paper_difficulty
         */
    
        //循环每个学科进行统计
        $student_papers = $papers['student_paper'];
        $all_paper_ids = implode(',', $papers['paper']);
        $time = time();
        
        $db = Fn::db();
    
        //该期次考试的所有试卷ID
        $sql = "SELECT paper_id, q_type, low_ques_id, mid_ques_id, high_ques_id
                FROM rd_summary_paper_difficulty
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'q_type'  => $item['q_type'],
                'ques_id' => array(
                    'low' => trim($item['low_ques_id']),
                    'mid' => trim($item['mid_ques_id']),
                    'high' => trim($item['high_ques_id']),
                )
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        $data = array();
        foreach ($student_papers as $exam_id => $paper)
        {
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
            
            //获取该区域的难易度和题型统计信息
            foreach ($paper as $paper_id => $u_ids)
            {
                if (empty($paper_data[$paper_id]) || !$u_ids)
                {
                    continue;
                }
                 
                $paper_info = $paper_data[$paper_id];
    
                foreach ($paper_info as $u_paper)
                {
                    //获取难易度和题型关联试题的试题得分
                    $q_type = $u_paper['q_type'];
                    $ques_ids = $u_paper['ques_id'];
                    $test_scores = array();
                    	
                    foreach ($ques_ids as $key => $ques_id)
                    {
                        //统计试题得分
                        if ($ques_id == '')
                        {
                            $test_scores[$key] = array();
                            continue;
                        }
                        	
                        $t_ques_ids = explode(',', $ques_id);
                        	
                        foreach ($u_ids as $uid)
                        {
                            $test_score = 0;
                            foreach ($t_ques_ids as $ques_id)
                            {
                                $k = $exam_id . "_" . $paper_id . "_" . $uid . "_" . $ques_id;
                                	
                                $test_score += self::$_data[$k]['test_score'];
                            }
                             
                            $test_scores[$key][$uid] = $test_score;
                        }
                    }
                    	
                    foreach ($u_ids as $uid)
                    {
                        $low_test_score = isset($test_scores['low'][$uid]) ? $test_scores['low'][$uid] : 0;
                        $mid_test_score = isset($test_scores['mid'][$uid]) ? $test_scores['mid'][$uid] : 0;
                        $high_test_score = isset($test_scores['high'][$uid]) ? $test_scores['high'][$uid] : 0;
                        	
                        $data = array(
                            'exam_pid' => $exam_pid,
                            'exam_id'  => $exam_id,
                            'paper_id' => $paper_id,
                            'uid'      => $uid,
                            'q_type'   => $q_type,
                            'low_test_score'  => $low_test_score,
                            'mid_test_score'  => $mid_test_score,
                            'high_test_score' => $high_test_score,
                            'ctime' => $time
                        );
                        
                        $db->replace('rd_summary_student_difficulty', $data);
                    }
                }
            }
        }
    
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_student_difficulty', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($paper_data);
        unset($papers);
        unset($student_papers);
        unset($paper_info);
    }
    
    /**
     * 关联 一级知识点
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_student_knowledge($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        //获取 考生的  的考试试卷
        $papers = self::_get_exam_student_papers($exam_pid, $uid);
        if (!$papers || empty($papers['paper']))
        {
            return false;
        }
    
        /*
         * 获取这些试卷关联的知识点 from->rd_summary_paper_knowledge
         */
    
        //循环每个学科进行统计
        $student_papers = $papers['student_paper'];
        $all_paper_ids = implode(',', $papers['paper']);
        
        $db = Fn::db();
    
        //该期次考试的所有试卷ID
        $sql = "SELECT paper_id, knowledge_id, is_parent, ques_id, know_process_ques_id
                FROM rd_summary_paper_knowledge
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'knowledge_id' 	=> $item['knowledge_id'],
                'ques_id' 		=> $item['ques_id'],
                'know_process_ques_id' => $item['know_process_ques_id'],
                'is_parent' 	=> $item['is_parent']
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        $time = time();
        $data = array();
        foreach ($student_papers as $exam_id => $paper)
        {
            //获取该区域的知识点统计信息
            foreach ($paper as $paper_id => $u_ids)
            {
                if (empty($paper_data[$paper_id]) || !$u_ids)
                {
                    continue;
                }
                 
                $paper_info = $paper_data[$paper_id];
    
                foreach ($paper_info as $u_paper)
                {
                    $ques_ids = trim($u_paper['ques_id']);
                    if ($ques_ids == '')
                    {
                        continue;
                    }
    
                    //获取知识点关联试题的试题得分
                    $knowledge_id = $u_paper['knowledge_id'];
                    $know_process_ques_ids = $u_paper['know_process_ques_id'] ? json_decode($u_paper['know_process_ques_id'], true) : array();
                    $know_process_ques_ids = !is_array($know_process_ques_ids) ? array() : $know_process_ques_ids;
                    $is_parent = $u_paper['is_parent'];
                    	
                    $total_scores = array();
                    $test_scores = array();
                    	
                    $t_ques_ids = explode(',', $ques_ids);
                    foreach ($u_ids as $uid)
                    {
                        $total_score = 0;
                        $test_score = 0;
                        foreach ($t_ques_ids as $ques_id)
                        {
                            $k = $exam_id . "_" . $paper_id . "_" . $uid . "_" . $ques_id;
    
                            $total_score += self::$_data[$k]['full_score'];
                            $test_score += self::$_data[$k]['test_score'];
                        }
    
                        $total_scores[$uid] = $total_score;
                        $test_scores[$uid] = $test_score;
                    }
                    	
                    //统计答对的认知过程关联试题
                    $kp_right_ques_ids = array();
                    if ($know_process_ques_ids)
                    {
                        foreach ($know_process_ques_ids as $know_process => $kp_ques_ids)
                        {
                            foreach ($u_ids as $uid)
                            {
                                foreach ($kp_ques_ids as $kp_ques_id)
                                {
                                    $k = $exam_id."_".$paper_id."_".$uid."_".$kp_ques_id;
    
                                    if (self::$_data[$k]['full_score'] == self::$_data[$k]['test_score'])
                                    {
                                        $kp_right_ques_ids[$uid][$know_process][] = $kp_ques_id;
                                    }
                                }
                            }
                        }
                    }
                    	
                    foreach ($u_ids as $uid)
                    {
                        $total_score = isset($total_scores[$uid]) ? $total_scores[$uid] : 0;
                        $test_score = isset($test_scores[$uid]) ? $test_scores[$uid] : 0;
                        $kp_right_ques_id = isset($kp_right_ques_ids[$uid]) ? $kp_right_ques_ids[$uid] : array();
                        	
                        $data = array(
                            'exam_pid'	=> $exam_pid,
                            'exam_id' 	=> $exam_id,
                            'paper_id' 	=> $paper_id,
                            'uid' 		=> $uid,
                            'knowledge_id' => $knowledge_id,
                            'know_process_ques_id' => json_encode($kp_right_ques_id),
                            'total_score'   => $total_score,
                            'test_score' 	=> $test_score,
                            'is_parent' 	=> $is_parent,
                            'ctime'         => $time
                        );
                        	
                        $db->replace('rd_summary_student_knowledge', $data);
                    }
                }
            }
        }
    
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_student_knowledge', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($paper_data);
        unset($papers);
        unset($student_papers);
        unset($paper_info);
        unset($know_process_ques_ids);
    }
    
    /**
     * 关联 方法策略
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_student_method_tactic($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        self::_get_test_exams($exam_pid);
    
        //获取 考生的  的考试试卷
        $papers = self::_get_exam_student_papers($exam_pid, $uid);
        if (!$papers || empty($papers['paper']))
        {
            return false;
        }
    
        /*
         * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
         */
    
        //循环每个学科进行统计
        $student_papers = $papers['student_paper'];
        $all_paper_ids = implode(',', $papers['paper']);
        
        $db = Fn::db();
    
        //该期次考试的所有试卷ID
        $sql = "SELECT paper_id, method_tactic_id, ques_id
                FROM rd_summary_paper_method_tactic
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'method_tactic_id' 	=> $item['method_tactic_id'],
                'ques_id' 		=> $item['ques_id'],
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        $time = time();
        $data = array();
        foreach ($student_papers as $exam_id => $paper)
        {
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
            
            //获取该区域的方法策略统计信息
            foreach ($paper as $paper_id => $u_ids)
            {
                if (empty($paper_data[$paper_id]) || !$u_ids)
                {
                    continue;
                }
                	
                $paper_info = $paper_data[$paper_id];
    
                foreach ($paper_info as $u_paper)
                {
                    //获取方法策略关联试题的试题得分
                    $method_tactic_id = $u_paper['method_tactic_id'];
                    $ques_ids = trim($u_paper['ques_id']);
                    	
                    if ($ques_ids == '')
                    {
                        continue;
                    }
                    	
                    $t_ques_ids = explode(',', $ques_ids);
                    foreach ($u_ids as $uid)
                    {
                        $test_score = 0;
                        $total_score = 0;
                        foreach ($t_ques_ids as $ques_id)
                        {
                            $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
                             
                            $test_score += self::$_data[$k]['test_score'];
                            $total_score += self::$_data[$k]['full_score'];
                        }
                        	
                        $data = array(
                            'exam_pid'	=> $exam_pid,
                            'exam_id' 	=> $exam_id,
                            'paper_id' 	=> $paper_id,
                            'uid' 		=> $uid,
                            'method_tactic_id'	=> $method_tactic_id,
                            'total_score'   => $total_score,
                            'test_score' 	=> $test_score,
                            'ctime' => $time
                        );
                        	
                        $db->replace('rd_summary_student_method_tactic', $data);
                    }
                }
            }
        }
    
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_student_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($paper_data);
        unset($papers);
        unset($student_papers);
        unset($paper_info);
    }
    
    /**
     * 关联 考生-学科-方法策略
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_student_subject_method_tactic($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        self::_get_test_exams($exam_pid);
    
        //获取 考生的  的考试试卷
        $papers = self::_get_exam_student_papers($exam_pid, $uid);
        if (!$papers || empty($papers['paper']))
        {
            return false;
        }
    
        /*
         * 获取这些试卷关联的方法策略 from->rd_summary_paper_method_tactic
         */
    
        //循环每个学科进行统计
        $student_papers = $papers['student_paper'];
        $all_paper_ids = implode(',', $papers['paper']);
        
        $db = Fn::db();
    
        //该期次考试的所有试卷ID
        $sql = "SELECT paper_id, method_tactic_id, ques_id
                FROM rd_summary_paper_method_tactic
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'method_tactic_id' 	=> $item['method_tactic_id'],
                'ques_id' 		=> $item['ques_id'],
            );
        }
    
        unset($query);
    
        if (!$paper_data)
        {
            return false;
        }
    
        $time = time();
        $data = array();
        foreach ($student_papers as $exam_id => $paper)
        {
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
            
            //获取该区域的方法策略统计信息
            foreach ($paper as $paper_id => $u_ids)
            {
                if (empty($paper_data[$paper_id]) || !$u_ids)
                {
                    continue;
                }
                	
                $paper_info = $paper_data[$paper_id];
    
                foreach ($paper_info as $u_paper)
                {
                    //获取方法策略关联试题的试题得分
                    $method_tactic_id = $u_paper['method_tactic_id'];
                    $ques_ids = trim($u_paper['ques_id']);
                    	
                    if ($ques_ids == '')
                    {
                        continue;
                    }
                    	
                    $t_ques_ids = explode(',', $ques_ids);
                    $ques_num = count($t_ques_ids);
                    foreach ($u_ids as $uid)
                    {
                        $u_test_score = 0;
    
                        foreach ($t_ques_ids as $ques_id)
                        {
                            $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
                             
                            $test_score = self::$_data_student_test_question[$k]['test_score'];
                            $difficulty = self::$_data_student_test_question[$k]['difficulty'];
                             
                            if ($test_score > 0)
                            {
                                $u_test_score += (100 - $difficulty);
                            }
                            else
                            {
                                $u_test_score += (0 - $difficulty);
                            }
                        }
    
                        $data = array(
                            'exam_pid' => $exam_pid,
                            'exam_id'  => $exam_id,
                            'paper_id' => $paper_id,
                            'method_tactic_id' => $method_tactic_id,
                            'uid'      => $uid,
                            '`usage`'    => $u_test_score/$ques_num,
                            'ctime'    => $time
                        );
                        	
                        $db->replace('rd_summary_student_subject_method_tactic', $data);
                    }
                }
            }
        }
    
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_student_subject_method_tactic', "exam_pid = $exam_pid AND ctime < $time");
        }
    
        unset($data);
        unset($paper_data);
        unset($papers);
        unset($student_papers);
        unset($paper_info);
    }
    
    /**
     * 关联 信息提取方式
     * @param number $exam_pid 考试期次ID
     */
    private static function summary_student_group_type($exam_pid = 0, $uid = 0)
    {
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return false;
        }
        
        self::_get_test_exams($exam_pid);
    
        //获取 考生的  的考试试卷
        $papers = self::_get_exam_student_papers($exam_pid, $uid);
        if (!$papers || empty($papers['paper']))
        {
            return false;
        }
    
        /*
         * 获取这些试卷关联的信息提取方式 from->rd_summary_paper_group_type
         */
        //循环每个学科进行统计
        $student_papers = $papers['student_paper'];
        $all_paper_ids = implode(',', $papers['paper']);
        
        $db = Fn::db();
    
        //该期次考试的所有试卷ID
        $sql = "SELECT paper_id, group_type_id, is_parent, ques_id
                FROM rd_summary_paper_group_type
                WHERE paper_id IN($all_paper_ids)
                ";
        $query = $db->query($sql);
    
        $paper_data = array();
         
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $paper_data[$item['paper_id']][] = array(
                'group_type_id' => $item['group_type_id'],
                'ques_id' 		=> $item['ques_id'],
                'is_parent' 	=> $item['is_parent']
            );
        }
         
        unset($query);
         
        if (!$paper_data)
        {
            return false;
        }
         
        $time = time();
        $data = array();
        foreach ($student_papers as $exam_id => $paper)
        {
            if (in_array(self::$_exam_subject[$exam_id], array(13, 14, 15, 16)))
            {
                continue;
            }
            
            //获取该区域的信息提取方式统计信息
            foreach ($paper as $paper_id => $u_ids)
            {
                if (empty($paper_data[$paper_id]) || !$u_ids)
                {
                    continue;
                }
    
                $paper_info = $paper_data[$paper_id];
                 
                foreach ($paper_info as $u_paper)
                {
                    //获取信息提取方式关联试题的试题得分
                    $group_type_id = $u_paper['group_type_id'];
                    $ques_ids = trim($u_paper['ques_id']);
                    $is_parent = $u_paper['is_parent'];
                     
                    if ($ques_ids == '' || $group_type_id < 1)
                    {
                        continue;
                    }
                     
                    $t_ques_ids = explode(',', $ques_ids);
                    foreach ($u_ids as $uid)
                    {
                        $test_score = 0;
                        $total_score = 0;
                        foreach ($t_ques_ids as $ques_id)
                        {
                            $k = $exam_id."_".$paper_id."_".$uid."_".$ques_id;
                            $test_score += self::$_data[$k]['test_score'];
                            $total_score += self::$_data[$k]['full_score'];
                        }
    
                        $data = array(
                            'exam_pid'	=> $exam_pid,
                            'exam_id' 	=> $exam_id,
                            'paper_id' 	=> $paper_id,
                            'uid' 		=> $uid,
                            'group_type_id'	=> $group_type_id,
                            'full_score' 	=> $total_score,
                            'test_score' 	=> $test_score,
                            'is_parent' 	=> $is_parent,
                            'ctime' => $time
                        );
                         
                        $db->replace('rd_summary_student_group_type', $data);
                    }
                }
            }
        }
         
        if ($data)
        {
            //清除多余的数据
            $db->delete('rd_summary_student_group_type', "exam_pid = $exam_pid AND ctime < $time");
        }
         
        unset($data);
        unset($paper_data);
        unset($papers);
        unset($student_papers);
        unset($paper_info);
    }
    
    //=====================公共方法===============================================================
    /**
     * 获取某期考试 按照考生分类
     */
    private static function _get_exam_student_papers($exam_pid = 0, $uid = 0)
    {
        if (self::$_exam_student_papers[$uid])
        {
            return self::$_exam_student_papers[$uid];
        }
    
        $exam_pid = intval($exam_pid);
        if (!$exam_pid)
        {
            return array();
        }
        
        $db = Fn::db();
    
        $data = array();
    
        /*
         * 前提：已生成成绩
        */
        $sql = "SELECT exam_id, paper_id, uid, subject_id
                FROM rd_exam_test_paper
                WHERE exam_pid = $exam_pid AND etp_flag = 2
                " . ($uid ? " AND uid = $uid" : '')
                ;
    
        $query = $db->query($sql);
        $paper_ids = array();
    
        while ($item = $query->fetch(PDO::FETCH_ASSOC))
        {
            $data[$item['exam_id']][$item['paper_id']][] = $item['uid'];
            $paper_ids[] = $item['paper_id'];
        }
    
        $data = array('paper' => array_unique($paper_ids), 'student_paper' => $data);
    
        self::$_exam_student_papers[$uid] = $data;
    
        return self::$_exam_student_papers[$uid];
    }
}