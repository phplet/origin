<?php
/**
 * 产品模型 ProductModel
 * @file    Product.php
 * @author  BJP
 * @final   2015-09-09
 */
class ProductModel
{
    /** 可用产品考试考场学科试卷列表
     * 
SELECT 
    p.p_id, p.p_name, p.exam_pid,  p.p_status, p.p_price, p.p_notice, 
    p.p_price_pushcourse, p.p_prefixinfo,
    pc.pc_id, pc.pc_name, pc.pc_memo,
    e.exam_id, e.exam_name, e.exam_isfree, e.grade_id, e.class_id, 
    e.introduce AS exam_intro, e.student_notice AS exam_notice, 
    e.status AS exam_status,
    ep.place_id, ep.place_name, ep.address AS place_address, ep.ip AS place_ip,
    ep.start_time AS place_start_time, ep.end_time AS place_end_time, 
    eps.subject_id, esp.id AS esp_id, esp.exam_id AS esp_exam_id, esp.paper_id,
    pt.pt_id, pt.pt_status,pt.pt_money, pt.pt_money_in, pt.pt_type, pt.pt_memo, 
    pt.pt_log, cp.cp_addtime
FROM rd_product p
LEFT JOIN rd_product_category pc ON p.pc_id = pc.pc_id
LEFT JOIN rd_exam e ON p.exam_pid = e.exam_id
LEFT JOIN rd_exam_place ep ON p.exam_pid = ep.exam_pid
LEFT JOIN rd_exam_place_subject eps ON p.exam_pid = eps.exam_pid 
    AND ep.place_id = eps.place_id 
LEFT JOIN rd_exam_subject_paper esp ON eps.exam_pid = esp.exam_pid 
    AND eps.exam_id = esp.exam_id AND eps.subject_id = esp.subject_id
LEFT JOIN rd_exam_paper epaper ON esp.paper_id = epaper.paper_id 
    AND esp.exam_id = epaper.exam_id
LEFT JOIN rd_production_transaction pt ON p.exam_pid = pt.exam_pid 
    AND ep.place_id = pt.place_id AND pt.uid = 87
LEFT JOIN rd_exam_place_student epstu ON ep.place_id = epstu.place_id 
    AND epstu.uid = 87
LEFT JOIN t_course_push cp ON p.exam_pid = cp.cp_exampid 
    AND ep.place_id = cp.cp_examplaceid AND cp.cp_stuuid = 87
WHERE p.pc_id = 12  -- 分类
-- AND p.p_status = 1  -- 产品状态
-- AND e.status = 1 -- 考试状态
-- AND ((e.exam_isfree = 0 AND ep.start_time > unix_timestamp() )  
        OR e.exam_isfree = 1) -- 考试时间限制
AND eps.subject_id IS NOT NULL -- 考场已分配学科 
AND eps.subject_id = 2 -- 学科
AND e.grade_id = 5 -- 年级
AND esp.id IS NOT NULL -- 学科试卷以分配存
AND epaper.paper_id IS NOT NULL -- 试卷存在


     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function productExamPlaceSubjectPaperList(
        /*string*/$field = NULL, array $cond_param = NULL, 
        /*int*/$page = NULL, /*int*/$perpage = NULL)//{{{
    {
        $where = array();
        $bind = array();
        $where[] = 'subject_id IS NOT NULL';
        $where[] = 'esp_id IS NOT NULL';
        $where[] = 'paper_id IS NOT NULL';
        $table = 'v_product_exam_place_subject_paper a';
        if ($cond_param)
        {
            $cond_param = Func::param_copy($cond_param, 'pc_id', 'p_status',
                'exam_status', 'subject_id', 'grade_id', 'exam_isfree',
                'place_available', 'stu_uid');
            if (isset($cond_param['pc_id']) 
                && Validate::isInt($cond_param['pc_id']))
            {
                $where[] = 'a.pc_id = ' . $cond_param['pc_id'];
            }
            if (isset($cond_param['p_status'])
                && Validate::isInt($cond_param['p_status']))
            {
                $where[] = 'a.p_status = ' . $cond_param['p_status'];
            }
            if (isset($cond_param['exam_status'])
                && Validate::isInt($cond_param['exam_status']))
            {
                $where[] = 'a.exam_status = ' . $cond_param['exam_status'];
            }
            if (isset($cond_param['place_available'])
                && Validate::isInt($cond_param['place_available'])
                && $cond_param['place_available'])
            {
                $time = time();
                if (isset($cond_param['exam_isfree'])
                    && Validate::isInt($cond_param['exam_isfree']))
                {
                    if ($cond_param['exam_isfree'] == 0)
                    {
                        $where[] = <<<EOT
(a.exam_isfree = 0 AND a.place_start_time > $time)
EOT;
                    }
                    else if ($cond_param['exam_isfree'] == 1)
                    {
                        $where[] = 'a.exam_isfree = 1';
                    }
                    else
                    {
                        $where[] = 'a.exam_isfree = ' . $cond_param['exam_isfree'];
                    }
                }
                else
                {
                    $where[] = <<<EOT
(a.exam_isfree = 0 AND a.place_start_time > $time) OR a.exam_isfree = 1
EOT;
                }
            }
            else
            {
                if (isset($cond_param['exam_isfree'])
                    && Validate::isInt($cond_param['exam_isfree']))
                {
                    $where[] = 'a.exam_isfree = ' . $cond_param['exam_isfree'];
                }
            }

            if (isset($cond_param['subject_id'])
                && Validate::isInt($cond_param['subject_id']))
            {
                $where[] = 'a.subject_id = ' . $cond_param['subject_id'];
            }
            if (isset($cond_param['grade_id'])
                && Validate::isInt($cond_param['grade_id']))
            {
                $where[] = 'a.grade_id = ' . $cond_param['grade_id'];
            }
            if (isset($cond_param['stu_uid'])
                && Validate::isInt($cond_param['stu_uid']))
            {
                $a_arr = explode(',', 'p_id,p_name,exam_pid,p_status,'
                   . 'p_price,p_notice,p_price_pushcourse,p_prefixinfo,'  
                   . 'pc_id,pc_name,pc_memo,exam_id,exam_name,exam_isfree,'
                   . 'grade_id,class_id,exam_intro,exam_notice,exam_status,'
                   . 'place_id,place_name,place_address,place_ip,'
                   . 'place_start_time,place_end_time,subject_id,'
                   . 'esp_id,esp_exam_id,esp_paper_id,paper_id,class_name');
                $b_arr = explode(',', 'epstu_uid,cp_addtime');

                if (is_null($field) || trim($field) == '*')
                {
                    $field = <<<EOT
a.*, epstu.uid AS epstu_uid, cp.cp_addtime
EOT;
                }
                else if ($field == 'COUNT(*) AS cnt')
                {
                }
                else
                {
                    $field_arr = explode(',', $field);
                    $field_arr_new = array();
                    foreach ($field_arr as $v)
                    {
                        $v = strtolower(trim($v));
                        if ($v == '' || $v == '*')
                        {
                            continue;
                        }
                        if (in_array($v, $b_arr))
                        {
                            $field_arr_new[] = 'b.' . $v;
                        }
                        else if (in_array($v, $a_arr))
                        {
                            $field_arr_new[] = 'a.' . $v;
                        }
                    }
                    if (empty($field_arr_new))
                    {
                        $field = <<<EOT
a.*, epstu.uid AS epstu_uid, cp.cp_addtime
EOT;
                    }
                    else
                    {
                        $field = str_replace(
                            array('b.epstu_uid', 'b.cp_'),
                            array('epstu.uid AS epstu_uid', 'cp.cp_'),
                            implode(',', $field_arr_new));
                    }
                }
                $uid = $cond_param['stu_uid'];
                $table = <<<EOT
v_product_exam_place_subject_paper a
LEFT JOIN rd_exam_place_student epstu ON a.place_id = epstu.place_id AND epstu.uid = {$uid}
LEFT JOIN t_course_push cp ON a.exam_pid = cp.cp_exampid AND a.place_id = cp.cp_examplaceid AND cp.cp_stuuid = {$uid}
EOT;
            }
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }
        }
        else
        {
            $order_by = NULL;
        }
        return Fn::db()->fetchList($table,
            $field, $where, $bind, $order_by, $page, $perpage);
    }//}}}

    /**
     * 查询符合条件的产品考试场次学科试卷
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     */
    public static function productExamPlaceSubjectPaperListCount(
        array $cond_param = NULL)//{{{
    {
        unset($cond_param['order_by']);
        $rs = self::productExamPlaceSubjectPaperList('COUNT(*) AS cnt', 
            $cond_param);
        return $rs[0]['cnt'];
    }//}}}
}
