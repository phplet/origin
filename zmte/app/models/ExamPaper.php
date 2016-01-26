<?php
/**
 * 考试试卷模块 ExamPaperModel
 * @file    ExamPaper.php
 * @author  BJP
 * @final   2015-07-02
 */
class ExamPaperModelPrivateData
{
    // 重点知识点规则列表
    public $sub_rules = array();
    // 已加入试题类别
    public $ques_ids = array();
    // 已加入试题的分组列表
    public $group_ids = array();
    // 已加入试题的题型数量
    public $ques_num = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    // 已加入试题的难易度
    public $difficulty = array();
    // 范围知识点以外的知识点
    public $except_knowledge_ids = array();
    public $t_except_knowledge_ids = array();
    // 已加入试题的知识点列表
    public $included_knowledge_ids = array();
    // 试卷目标平均难易度
    public $target_difficulty = 0;
    // 已加入的翻译题数量
    public $translations = array('translation_e_c' => 0, 'translation_c_e' => 0);
}

class ExamPaperModel
{
    // 题型、难易度分组
    // array(qtype, start_difficulty, end_difficulty),
    private static $qtype_areas = array(
        // 题组
        0 => array(0, 0, 29.9999), 1 => array(0, 29.9999, 60.0001),
        2 => array(0, 60.0001, 100),
        // 单选
        3 => array(1, 0, 29.9999), 4 => array(1, 29.9999, 60.0001),
        5 => array(1, 60.0001, 100),
        // 不定项
        6 => array(2, 0, 29.9999), 7 => array(2, 29.9999, 60.0001),
        8 => array(2, 60.0001, 100),
        // 填空
        9 => array(3, 0, 29.9999), 10 => array(3, 29.9999, 60.0001),
        11 => array(3, 60.0001, 100),
        // 完形填空
        12 => array(4, 0, 29.9999), 13 => array(4, 29.9999, 60.0001),
        14 => array(4, 60.0001, 100),
        // 匹配题
        15 => array(5, 0, 29.9999), 16 => array(5, 29.9999, 60.0001),
        17 => array(5, 60.0001, 100),
        // 选词填空
        18 => array(6, 0, 29.9999), 19 => array(6, 29.9999, 60.0001),
        20 => array(6, 60.0001, 100),
        // 翻译题
        21 => array(7, 0, 29.9999), 22 => array(7, 29.9999, 60.0001),
        23 => array(7, 60.0001, 100),
        // 阅读填空
        24 => array(8, 0, 29.9999), 25 => array(8, 29.9999, 60.0001),
        26 => array(8, 60.0001, 100),
        // 连词成句
        27 => array(9, 0, 29.9999), 28 => array(9, 29.9999, 60.0001),
        29 => array(9, 60.0001, 100));

    /**
     * 获取一个试卷信息
     *
     * @param
     *            int 试卷ID(paper_id)
     * @param
     *            string 字段列表(多个字段用逗号分割，默认取全部字段)
     * @return mixed 指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_paper($id = 0, $item = NULL)//{{{
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $sql = 'SELECT ' . $item;
        }
        else
        {
            $sql = 'SELECT *';
        }
        $sql .= ' FROM rd_exam_paper WHERE paper_id = ' . $id;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }//}}}

    /**
     * 获取 某个学科所有的关联二级知识点 的差集
     */
    public static function get_diff_knowledges($subject_id, $except_knowledge_ids)//{{{
    {
        $subject_id = intval($subject_id);
        if (!$subject_id)
        {
            return array();
        }
        $sql = <<<EOT
SELECT id FROM rd_knowledge WHERE subject_id = {$subject_id} AND pid > 0
EOT;
        $all_knowledges = Fn::db()->fetchCol($sql);
        return array_diff($all_knowledges, $except_knowledge_ids);
    }//}}}

    /**
     * 检查是否有完全重复的试卷试题
     */
    public static function has_repeat_paper($exam_id, $ques_ids)//{{{
    {
        $sql = <<<EOT
SELECT paper_id, COUNT(*) AS 'COUNT', GROUP_CONCAT(ques_id) AS ques_ids
FROM rd_exam_question WHERE exam_id = {$exam_id} GROUP BY paper_id
EOT;
        $result = Fn::db()->fetchAll($sql);
        $count_ques_id = count($ques_ids);
        $repeat_papers = array();
        foreach ($result as $val)
        {
            if ($val['count'] == $count_ques_id)
            {
                $current_paper_ques_ids = explode(',', $val['ques_ids']);
                $diff_ques_id = array_diff($ques_ids, $current_paper_ques_ids);
                if (empty($diff_ques_id))
                {
                    $current_paper = self::get_paper($val['paper_id'], 'is_delete,paper_name');
                    if ($current_paper['is_delete'])
                    {
                        $repeat_papers[] = '<a href="' . site_url(
                                        'admin/paper/preview/' . $val['paper_id']) .
                                '" target="_blank">' . '试卷名称：' .
                                $current_paper['paper_name'] .
                                "<font color='red'>《回收站》</font>(ID：" .
                                $val['paper_id'] . ")" . '</a>';
                    }
                    else
                    {
                        $repeat_papers[] = '<a href="' . site_url(
                                        'admin/paper/preview/' . $val['paper_id']) .
                                '" target="_blank">' . '试卷名称：' .
                                $current_paper['paper_name'] . "(ID：" .
                                $val['paper_id'] . ")" . '</a>';
                    }
                }
            }
        }
        return implode('、', $repeat_papers);
    }//}}}

    // 生成重点知识点试题
    private static function create_rule_knowledge(ExamPaperModelPrivateData &$this_data, $where, $rule_limit, $subject_id, $rule, $qtypes)//{{{
    {
        for ($include_except_knowledge = 0; $include_except_knowledge < 2; $include_except_knowledge ++)
        {
            if ($include_except_knowledge)
            {
                $except_knowledge = array();
            }
            foreach ($this_data->sub_rules as &$sub_rule)
            {
                $knowledge_id = $sub_rule['knowledge_id'];
                // 认知过程
                $know_process_where = $sub_rule['know_process'] > 0 ? " and know_process=" .
                        $sub_rule['know_process'] : '';
                $rule_where = $where;
                if ($sub_rule['pid'] == 0) {
                    // 一级知识点
                    $rule_where['knowledge'] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                            where knowledge_id IN (" .
                            my_implode($sub_rule['children']) .
                            ") $know_process_where and is_child=0)";
                } else {
                    $rule_where['knowledge'] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                                    where knowledge_id={$sub_rule['knowledge_id']} $know_process_where and is_child=0)";
                }
                foreach ($sub_rule['nums'] as $num_index => $num) {
                    if (empty($num) or ! isset(self::$qtype_areas[$num_index]))
                        continue;
                    list ($qtype, $start_diffficulty, $end_diffficulty) = self::$qtype_areas[$num_index];
                    $new_where = $rule_where;
                    $new_where[] = "q.type={$qtype}";
                    $new_where[] = "rc.difficulty BETWEEN $start_diffficulty AND $end_diffficulty";
                    // 英语匹配题和选词填空标签选择
                    if ($subject_id == 3) {
                        if ($qtype == 4 &&
                                !empty(
                                        $rule_limit[$qtype][0]['children_num'])) {
                            $children_num = (int) $rule_limit[$qtype][0]['children_num'];
                            $new_where[] = "q.children_num = $children_num";
                        }
                        if ($qtype == 5 && !empty($rule_limit[5][0]['tags'])) {
                            $new_where[] = "q.tags = " .
                                    $rule_limit[5][0]['tags'];
                        }
                        if ($qtype == 6 && !empty($rule_limit[6][0]['tags'])) {
                            $new_where[] = "q.tags = " .
                                    $rule_limit[6][0]['tags'];
                        }
                    }
                    // 试题数量限制
                    if ($rule['ques_num'][$qtype] <= $this_data->ques_num[$qtype]) {
                        $num = 0;
                        continue;
                    }
                    do {
                        if ($include_except_knowledge == 0) {
                            $except_knowledge = self::not_rule_knowledge($this_data, 
                                    $knowledge_id);
                        }
                        if ($subject_id == 3) {
                            // 翻译题 翻译类型限制
                            if ($qtype == 7 &&
                                    ($this_data->translations['translation_c_e'] <
                                    $rule_limit[7][0]['translation_c_e'])) {
                                $new_where[] = "q.translation = 1";
                            } else if ($qtype == 7 &&
                                    ($this_data->translations['translation_e_c'] <
                                    $rule_limit[7][0]['translation_e_c'])) {
                                $new_where[] = "q.translation = 2";
                            }
                        }
                        $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge);
                        // TODO 英语题组无知识点题目添加到组题范围内
                        if (!$res && $subject_id == 3 && $qtype == 0) {
                            unset($new_where['knowledge']);
                            $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge);
                        }
                        // 将已取到的试题关联的方法策略分类对应的试题数 进行升序排，试题不够时，用这些方法策略关联的其他试题来补齐
                        if (!$res) {
                            $method_tactic_ids = self::_get_inserted_question_method_tactics($this_data->ques_ids);
                            $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge, array(), 'RAND()', $method_tactic_ids);
                        }
                        if ($res)
                            $num --;
                    }
                    while ($num && $res && $rule['ques_num'][$qtype] >
                    $this_data->ques_num[$qtype]);
                    if ($num && !$include_except_knowledge) {
                        break 2;
                    }
                    if ($num && $include_except_knowledge) {
                        $knowledge_name = KnowledgeModel::get_knowledge(
                                $knowledge_id, 'knowledge_name');
                        $result['msg'] = '试题数量不足（' . $knowledge_name . ',' .
                                $qtypes[$qtype] . ',[' .
                                round($start_diffficulty) . ',' .
                                round($end_diffficulty) . ']）';
                        return $result;
                    }
                }
            }
        }
    }//}}}

    // --------------------------------------------//
    // 范围知识点试题：
    // 1, 先排除已加入试题的知识点
    // 2, 排除重点知识点，再选择一遍
    // 3, 再排除所有知识点，挑选一遍
    // 每一遍挑选，都会实时把新选择的试题知识点加入排除列表
    // --------------------------------------------//
    private static function create_qt_knowledge(ExamPaperModelPrivateData &$this_data, $subject_id, $rule_knowledge_ids, $rule, $rule_limit, $difficulty_area, $qtypes, $where)//{{{
    {
        if ($subject_id == 3) {
            $qtype_index = array(3, 0, 1, 2, 4, 5, 6, 7, 8, 9);
        } else {
            $qtype_index = array(3, 0, 1, 2);
        }
        for ($include_added_knowdege = 0; $include_added_knowdege <= 2; $include_added_knowdege ++) {
            if ($include_added_knowdege == 1) {
                $this_data->included_knowledge_ids = $rule_knowledge_ids;
            } elseif ($include_added_knowdege == 2) {
                $this_data->included_knowledge_ids = array();
            }
            foreach ($qtype_index as $qtype) {
                if (!isset($rule['ques_num'][$qtype]) or
                        empty($rule['ques_num'][$qtype])) {
                    continue;
                }
                if ($rule['ques_num'][$qtype] > $this_data->ques_num[$qtype]) {
                    for ($i = $this_data->ques_num[$qtype]; $i <
                            $rule['ques_num'][$qtype]; $i ++) {
                        $new_where = $where;
                        $new_where[] = "q.type=$qtype";
                        // TODO 英语组题对信息提取方式限制
                        if ($subject_id == 3 && !empty($rule_limit[$qtype])) {
                            // 完形填空题
                            if ($qtype == 4 &&
                                    !empty(
                                            $rule_limit[$qtype][0]['children_num'])) {
                                $children_num = (int) $rule_limit[$qtype][0]['children_num'];
                                $new_where[] = "q.children_num = $children_num";
                            }
                            // 英语匹配题和选词填空标签选择
                            if ($qtype == 5 && !empty(
                                            $rule_limit[5][0]['tags'])) {
                                $new_where[] = "q.tags = " .
                                        $rule_limit[5][0]['tags'];
                            }
                            if ($qtype == 6 && !empty(
                                            $rule_limit[6][0]['tags'])) {
                                $new_where[] = "q.tags = " .
                                        $rule_limit[6][0]['tags'];
                            }
                            // 翻译题 翻译类型限制
                            if ($qtype == 7 &&
                                    ($this_data->translations['translation_c_e'] <
                                    $rule_limit[7][0]['translation_c_e'])) {
                                $new_where[] = "q.translation = 1";
                            } else if ($qtype == 7 &&
                                    ($this_data->translations['translation_e_c'] <
                                    $rule_limit[7][0]['translation_e_c'])) {
                                $new_where[] = "q.translation = 2";
                            }
                            if ($qtype == 0 && !empty($rule_limit[$qtype])) {
                                $limit_where = $new_where;
                                foreach ($rule_limit[$qtype] as $key => &$val) {
                                    if ($val['ques_num'] < 1) {
                                        unset($rule_limit[$qtype][$key]);
                                        continue;
                                    }
                                    if (isset(
                                                    $difficulty_area[$val['difficulty_level'] -
                                                    1])) {
                                        $difficulty = $difficulty_area[$val['difficulty_level'] -
                                                1];
                                        $limit_where[] = "rc.difficulty BETWEEN $difficulty[0] AND $difficulty[1]";
                                    }
                                    if ($val['children_num'] > 0) {
                                        $limit_where[] = "q.children_num = " .
                                                $val['children_num'];
                                    }
                                    if ($val['word_num_min'] > 0) {
                                        $limit_where[] = "q.word_num > " .
                                                ($val['word_num_min'] - 1);
                                    }
                                    if ($val['word_num_max'] > 0) {
                                        $limit_where[] = "q.word_num < " .
                                                ($val['word_num_max'] + 1);
                                    }
                                    $this_data->t_except_knowledge_ids = $this_data->except_knowledge_ids;
                                    // $this->except_knowledge_ids = array ();
                                    $res = self::insert_questions($this_data, 0, $limit_where, 1);
                                    if ($res) {
                                        $this_data->except_knowledge_ids = $this_data->t_except_knowledge_ids;
                                        $val['ques_num'] --;
                                        continue 2;
                                    }
                                }
                                // $result ['msg'] = $this->db->last_query();
                                $result['msg'] = '试题数量不足(' . $qtypes[$qtype] . ')';
                                return $result;
                            }
                        }
                        // 调节难易度
                        if ($diffs = self::get_difficulty($this_data)) {
                            $new_where['difficulty'] = "rc.difficulty BETWEEN $diffs[0] AND $diffs[1]";
                        }
                        $t_except_knowledge = $this_data->included_knowledge_ids;
                        if ($subject_id == 3 && in_array($qtype, array(0, 5))) {
                            $t_except_knowledge = array();
                        }
                        $res = self::insert_questions($this_data, 0, $new_where, 1, $t_except_knowledge);
                        if ($res === false) {
                            // 如果在难易度区间没找到试题，则取消区间再查询一次
                            if ($diffs) {
                                unset($new_where['difficulty']);
                                $res2 = self::insert_questions($this_data, 0, $new_where, 1, $t_except_knowledge);
                                if ($res2 == false) {
                                    if ($include_added_knowdege == 2)
                                        $result['msg'] = '试题数量不足(' .
                                                $qtypes[$qtype] . ')';
                                    break;
                                }
                            }
                            else {
                                if ($include_added_knowdege == 2)
                                    $result['msg'] = '试题数量不足(' .
                                            $qtypes[$qtype] . ')';
                                break;
                            }
                        }
                    }
                    if ($include_added_knowdege == 2 && $result['msg']) {
                        return $result;
                    }
                }
            }
        }
    }//}}}

    /**
     * 生成一分试卷（随机模式）
     *
     * @param
     *            array 考试信息
     * @param
     *            array 规则信息
     * @param
     *            array 重点规则信息
     * @return array
     */
    public static function generate_rand($exam, $rule, $sub_rules)//{{{
    {
        $result = array('success' => false, 'msg' => '', 'code' => '-1');
        if (empty($exam) or empty($rule))
        {
            $result['msg'] = '规则不存在';
            return $result;
        }

        $this_data = new ExamPaperModelPrivateData();
        $db = Fn::db();

        // --------------------------------------------//
        // 生成sql语句
        // --------------------------------------------//
        $subject_id = $rule['subject_id'];
        $grade_id = $rule['grade_id'];
        $class_id = $rule['class_id'];
        $subject_type = $rule['subject_type'];
        $test_way = $rule['test_way'];
        $knowledge_ids = $rule['knowledge_ids'];

        $this_data->target_difficulty = $rule['difficulty'];

        $difficulty_area = C('difficulty_area');
        if ($grade_id < 11 || $subject_id > 3 || !in_array($class_id, array(2, 3)))
        {
            $subject_type = '-1';
        }
        else
        {
            if ($subject_type == '-1')
            {
                $subject_type = 0;
            }
        }
        $qtypes = C('qtype');
        $where = array();
        
        // 学科、年级、类型
        $where[] = "q.is_delete=0 AND q.parent_id=0";
        $where[] = "q.subject_id=$subject_id";
        $where[] = "q.ques_id=rc.ques_id AND rc.grade_id=$grade_id
                    AND rc.class_id=$class_id";
        if ($subject_type >= 0)
        {
            $where[] = "rc.subject_type=$subject_type";
        }
        if (in_array($test_way, array_keys(C('test_way'))))
        {
            $where[] = "q.test_way IN ({$test_way}, 3)";
        }

        /*
         * 英语组题限制
         */
        $rule_limit = array();
        if ($subject_id == 3)
        {
            $rule_limits = $db->fetchAll("SELECT * FROM rd_exam_rule_qtype_limit WHERE exam_rule_id = " . $rule['rule_id']);
            if ($rule_limits)
            {
                foreach ($rule_limits as $key => $item)
                {
                    $rule_limit[$item['qtype']][] = $item;
                }
            }
        }
        
        // 范围知识点
        $knowledge_id_arr = explode(',', $knowledge_ids);
        $query = $db->fetchAll("SELECT id FROM rd_knowledge WHERE subject_id = $subject_id AND pid > 0");
        
        foreach ($query as $row)
        {
            if (!in_array($row['id'], $knowledge_id_arr))
            {
                $this_data->except_knowledge_ids[] = $row['id'];
            }
        }
        $this_data->sub_rules = $sub_rules;
        // 重点知识点列表。挑选范围知识点时，先排除，不够再加进来。
        $rule_knowledge_ids = array();
        foreach ($this_data->sub_rules as &$sub_rule)
        {
            if ($sub_rule['pid'] == 0)
            {
                $rule_knowledge_ids += $sub_rule['children'];
            }
            else
            {
                $rule_knowledge_ids[] = $sub_rule['knowledge_id'];
            }
        }
        $rule_knowledge_ids = array_unique($rule_knowledge_ids);
        if (count($rule_knowledge_ids))
        {
            $rule_knowledge_ids = array_combine(array_values($rule_knowledge_ids), $rule_knowledge_ids);
        }
        $this_data->rule_knowledge_ids = $rule_knowledge_ids;
        // 生成重点知识点试题
        $rule_knowledge_msg = self::create_rule_knowledge($this_data, $where, $rule_limit, $subject_id, $rule, $qtypes);
        if (is_array($rule_knowledge_msg))
        {
            return $rule_knowledge_msg;
        }
        // 其他试题
        // $where[] = "q.ques_id IN (SELECT distinct ques_id FROM {pre}relate_knowledge
        // where knowledge_id IN(".implode(',', $knowledge_id_arr).") and is_child=0)";
        $create_qt_knowledge_msg = self::create_qt_knowledge($this_data, $subject_id, $rule_knowledge_ids, $rule, $rule_limit, $difficulty_area, $qtypes, $where);
        if (is_array($create_qt_knowledge_msg))
        {
            return $create_qt_knowledge_msg;
        }
        // 检查是否有重复的试题试卷，如有有，则中断
        if (($repeat_paper = self::has_repeat_paper($exam['exam_id'], $this_data->ques_ids)) !=
            '')
        {
            $result['msg'] = '已经存在与该份试卷完全一样的试卷，无法再继续生成(<font color="blue">重复试卷：' .
                    $repeat_paper . '</font>)';
            $result['code'] = '-2';
            return $result;
        }

        $bOk = false;
        if ($db->beginTransaction())
        {
            // 生成试卷主干
            $paper = array('exam_id' => $exam['exam_id'],
                'ques_num' => array_sum($this_data->ques_num),
                'qtype_ques_num' => implode(',', $this_data->ques_num),
                'difficulty' => array_sum($this_data->difficulty) / count($this_data->difficulty),
                'uptime' => time(), 
                'addtime' => time(),
                'question_sort' => '',
                'question_score' => '');
            $db->insert('rd_exam_paper', $paper);
            $paper_id = $db->lastInsertId('rd_exam_paper', 'paper_id');
            $db->update('rd_exam_paper', array('paper_name' => $exam['exam_name'] . ' 试卷' . $paper_id), "paper_id = $paper_id");
            
            // 插入试题
            sort($this_data->ques_ids, SORT_NUMERIC);
            $paper_question = array();
            foreach ($this_data->ques_ids as $ques_id)
            {
                $row = array('paper_id' => $paper_id, 'exam_id' => $exam['exam_id'],
                    'ques_id' => $ques_id);
                $paper_question[] = $row;
            }
            if ($paper_question)
            {
                foreach ($paper_question as $v)
                {
                    $db->insert('rd_exam_question', $v);
                }
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('generate', 'exam_paper', $paper_id);
                $result['success'] = TRUE;
            }
        }
        return $result;
    }//}}}

// ------------------------- new -----------------------------------------//
// 优先挑选重点填空题
    private static function create_tiankong(ExamPaperModelPrivateData &$this_data, $rule, $where, $qtypes)//{{{
    {
        if (isset($rule['ques_num'][3]) && $rule['ques_num'][3] > 0) {
// 生成重点知识点试题
            foreach ($this_data->sub_rules as $sub_rule) {
                $knowledge_id = $sub_rule['knowledge_id'];
// 认知过程
                $know_process_where = $sub_rule['know_process'] > 0 ? " and know_process=" .
                        $sub_rule['know_process'] : '';
                $rule_where = $where;
                if ($sub_rule['pid'] == 0) {
// 一级知识点
                    $rule_where[] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                            where knowledge_id IN (" .
                            my_implode($sub_rule['children']) . ") $know_process_where and is_child=0)";
                } else {
                    $rule_where[] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                    where knowledge_id=$knowledge_id $know_process_where and is_child=0)";
                }
                foreach ($sub_rule['nums'] as $num_index => $num) {
                    if ($num_index < 9 || $num_index > 11)
                        continue;
                    if (empty($num) or ! isset(self::$qtype_areas[$num_index]))
                        continue;
                    list ($qtype, $start_diffficulty, $end_diffficulty) = self::$qtype_areas[$num_index];
// 试题数量限制
                    if ($rule['ques_num'][$qtype] <= $this_data->ques_num[$qtype]) {
                        $num = 0;
                        continue;
                    }
                    for ($i = 0; $i < $num; $i ++) {
                        $new_where = $rule_where;
                        $new_where[] = "q.type='$qtype'";
                        $new_where[] = "rc.difficulty BETWEEN $start_diffficulty AND $end_diffficulty";
                        if ($rule['ques_num'][$qtype] > $this_data->ques_num[$qtype]) {
                            $res = self::insert_questions($this_data, 1, $new_where, 1);
                        }
                    }
                    if (!$res) {
// 试题数量不足
                        $knowledge_name = KnowledgeModel::get_knowledge($knowledge_id, 'knowledge_name');
                        $result['msg'] = '试题数量不足（' . $knowledge_name . ',' . $qtypes[$qtype] . ',[' .
                                round($start_diffficulty) . '-' . round($end_diffficulty) . ']）';
                        return $result;
                    }
                }
            }
        }
    }//}}}

// ----------------------------------------------------------//
//
// 在同期试卷（之前生成的最近N份试卷）的每一个试题分组中，
// 挑选未被使用过的同组试题，且满足重点知识点规则
// ----------------------------------------------------------//
    private static function recent_group(ExamPaperModelPrivateData &$this_data, $exam, $where, $rule)//{{{
    {
// 获取最近生成试卷的分组
        self::recent_paper_question($this_data, $exam['exam_id'], 10);
// 获取最近生成试卷的分组，相对应的所有试题
        $group_questions = array();
        if ($this_data->recent_paper_group) {
            $where_new = $where;
            $where_new[] = "q.group_id IN(" .
                    my_implode(array_keys($this_data->recent_paper_group)) . ")";
            $sql = "SELECT q.ques_id,q.type,q.group_id,q.knowledge,rc.difficulty
        FROM rd_question q,rd_relate_class rc
        WHERE " .
                    implode(' AND ', $where_new) . " ORDER BY q.type DESC";
            $query = Fn::db()->fetchAll($sql);
            foreach ($query as $row) {
                $group_questions[$row['group_id']][$row['ques_id']] = $row;
            }
        }
// -------------------------------------------------------------//
// 遍历同期试卷的分组
// 如果同组试题有未被使用的试题, 且满足重点知识点，则优先选中
// -------------------------------------------------------------//
        foreach ($this_data->recent_paper_group as $group_id => $group_ques) {
            if (!isset($group_questions[$group_id]))
                continue;
            if (isset($this_data->group_ids[$group_id]))
                continue;
            foreach ($group_questions[$group_id] as $ques_id => $row) {
// 跳过之前试卷已被使用的试题
                if (isset($group_ques[$ques_id]) && $row['type'] != 3)
                    continue;
                if (isset($this_data->ques_ids[$ques_id]))
                    continue;
// 满足范围知识点
                $row['knowledge'] = explode(',', trim($row['knowledge'], ','));
                if (!array_diff($row['knowledge'], $rule['knowledge_id_arr']) &&
                        $rule['ques_num'][$row['type']] > $this_data->ques_num[$row['type']]) {
// 尝试加入试卷
                    if (self::reset_rule_ques_num($this_data, $row)) {
// 同分组只能加入一个试题
                        break;
                    }
                }
            }
        }
    }//}}}

    private static function rule_know(ExamPaperModelPrivateData &$this_data, $where, $subject_id, $rule_limit, $qtypes, $rule)//{{{
    {
// -------------------------------------------------------------//
// 重点知识点：
// -------------------------------------------------------------//
        $except_knowledge = array();
        if (self::check_rule_num($this_data))
        {
            for ($include_except_knowledge = 0; $include_except_knowledge < 2; $include_except_knowledge ++) {
                if ($include_except_knowledge) {
                    $except_knowledge = array();
                }
// 生成重点知识点试题
                foreach ($this_data->sub_rules as $item) {
                    $knowledge_id = $item['knowledge_id'];
// 认知过程
                    $know_process_where = $item['know_process'] > 0 ? " and know_process=" .
                            $item['know_process'] : '';
                    $rule_where = $where;
                    if ($item['pid'] == 0) {
// 一级知识点
                        $rule_where['knowledge'] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                where knowledge_id IN (" . my_implode($item['children']) .
                                ") $know_process_where and is_child=0)";
                    } else {
                        $rule_where['knowledge'] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_knowledge
                            where knowledge_id={$knowledge_id} $know_process_where and is_child=0)";
                    }
                    foreach ($item['nums'] as $num_index => $num) {
                        if (empty($num) || !isset(self::$qtype_areas[$num_index]))
                            continue;
                        list ($qtype, $start_diffficulty, $end_diffficulty) = self::$qtype_areas[$num_index];
                        $new_where = $rule_where;
                        $new_where[] = "q.type='$qtype'";
                        $new_where[] = "rc.difficulty BETWEEN $start_diffficulty AND $end_diffficulty";
// 英语完形填空子题数限制；匹配题和选词填空标签选择
                        if ($subject_id == 3) {
                            if ($qtype == 4 && !empty($rule_limit[$qtype][0]['children_num'])) {
                                $children_num = (int) $rule_limit[$qtype][0]['children_num'];
                                $new_where[] = "q.children_num = $children_num";
                            }
                            if ($qtype == 5 && !empty($rule_limit[5][0]['tags'])) {
                                $new_where[] = "q.tags = " . $rule_limit[$qtype][0]['tags'];
                            }
                            if ($qtype == 6 && !empty($rule_limit[6][0]['tags'])) {
                                $new_where[] = "q.tags = " . $rule_limit[$qtype][0]['tags'];
                            }
                        }
// 试题数量限制
                        if ($rule['ques_num'][$qtype] <= $this_data->ques_num[$qtype]) {
                            $num = 0;
                            continue;
                        }
                        $not_found = false;
                        do {
                            if ($include_except_knowledge == 0) {
                                $except_knowledge = self::not_rule_knowledge($this_data, $knowledge_id);
                            }
                            if ($subject_id == 3) {
                                $except_knowledge = array();
// 翻译题 翻译类型限制
                                if ($qtype == 7 && ($this_data->translations['translation_c_e'] <
                                        $rule_limit[7][0]['translation_c_e'])) {
                                    $new_where[] = "q.translation = 1";
                                } else if ($qtype == 7 && ($this_data->translations['translation_e_c'] <
                                        $rule_limit[7][0]['translation_e_c'])) {
                                    $new_where[] = "q.translation = 2";
                                }
                            }
// 先排除之前试卷的分组
                            if ($not_found == false) {
                                $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge, array_keys($this_data->recent_paper_group), 'q.group_id DESC');
// TODO 英语题组无知识点题目添加到组题范围内
                                if (!$res && $subject_id == 3 && $qtype == 0) {
                                    unset($new_where['knowledge']);
                                    $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge);
                                }
                            } else {
                                $res = false;
                            }
                            if (!$res) {
                                $not_found = true;
                                $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge, array(), 'q.group_id DESC');
                            }
// 将已取到的试题关联的方法策略分类对应的试题数 进行升序排，试题不够时，用这些方法策略关联的其他试题来补齐
                            if (!$res) {
                                $method_tactic_ids = self::_get_inserted_question_method_tactics($this_data->ques_ids);
                                $res = self::insert_questions($this_data, 1, $new_where, 1, $except_knowledge, array(), 'q.group_id DESC', $method_tactic_ids);
                            }
                            if ($res) {
                                $num --;
                            }
                        } while ($num && $res && $rule['ques_num'][$qtype] > $this_data->ques_num[$qtype]);

                        if ($num && !$include_except_knowledge) {
                            break 2;
                        }
                        if ($num && $include_except_knowledge) {
// 试题数量不足
                            $knowledge_name = KnowledgeModel::get_knowledge($knowledge_id, 'knowledge_name');
                            $result['msg'] = '试题数量不足（' . $knowledge_name . ',' . $qtypes[$qtype] . ',[' .
                                    round($start_diffficulty) . '-' . round($end_diffficulty) . ']）';
                            return $result;
                        }
                    }
                }
            }
        }
    }//}}}

// --------------------------------------------//
// 范围知识点试题：
// 1, 先排除已加入试题的知识点
// 2, 排除重点知识点，再选择一遍
// 3, 再排除所有知识点，挑选一遍
// 每一遍挑选，都会实时把新选择的试题知识点加入排除列表
// --------------------------------------------//
    private static function fanwei_knowledge(ExamPaperModelPrivateData &$this_data, $subject_id, $rule_knowledge_ids, $where, $rule, $rule_limit, $difficulty_area, $qtypes)//{{{
    {
        if ($subject_id == 3) {
            $qtype_index = array(3, 0, 1, 2, 4, 5, 6, 7, 8, 9);
        } else {
            $qtype_index = array(3, 0, 1, 2);
        }
        for ($include_added_knowdege = 0; $include_added_knowdege <= 2; $include_added_knowdege ++) {
            if ($include_added_knowdege == 1) {
                $this_data->included_knowledge_ids = $rule_knowledge_ids;
            }
            foreach ($qtype_index as $qtype) {
                if (!isset($rule['ques_num'][$qtype]) or empty($rule['ques_num'][$qtype]))
                    continue;
                if ($rule['ques_num'][$qtype] <= $this_data->ques_num[$qtype])
                    continue;
                for ($i = $this_data->ques_num[$qtype]; $i < $rule['ques_num'][$qtype]; $i ++) {
// 第三次筛选,排除知识点
                    if ($include_added_knowdege == 2) {
                        $this_data->included_knowledge_ids = array();
                    }
                    $new_where = $where;
                    $new_where[] = "q.type=$qtype";
// TODO 英语组题对信息提取方式限制
                    if ($subject_id == 3 && !empty($rule_limit[$qtype])) {
// 完形填空题
                        if ($qtype == 4 && !empty($rule_limit[$qtype][0]['children_num'])) {
                            $children_num = (int) $rule_limit[$qtype][0]['children_num'];
                            $new_where[] = "q.children_num = $children_num";
                        }
// 英语匹配题和选词填空标签选择
                        if ($qtype == 5 && !empty($rule_limit[5][0]['tags'])) {
                            $new_where[] = "q.tags = " . $rule_limit[5][0]['tags'];
                        }
                        if ($qtype == 6 && !empty($rule_limit[6][0]['tags'])) {
                            $new_where[] = "q.tags = " . $rule_limit[6][0]['tags'];
                        }
// 翻译题 翻译类型限制
                        if ($qtype == 7 && ($this_data->translations['translation_c_e'] <
                                $rule_limit[7][0]['translation_c_e'])) {
                            $new_where[] = "q.translation = 1";
                        } else if ($qtype == 7 && ($this_data->translations['translation_e_c'] <
                                $rule_limit[7][0]['translation_e_c'])) {
                            $new_where[] = "q.translation = 2";
                        }
                        if ($qtype == 0 && !empty($rule_limit[$qtype])) {
                            $limit_where = $new_where;
                            foreach ($rule_limit[$qtype] as $key => &$val) {
                                if ($val['ques_num'] < 1) {
                                    unset($rule_limit[$qtype][$key]);
                                    continue;
                                }
                                if (isset($difficulty_area[$val['difficulty_level'] - 1])) {
                                    $difficulty = $difficulty_area[$val['difficulty_level'] - 1];
                                    $limit_where[] = "rc.difficulty BETWEEN $difficulty[0] AND $difficulty[1]";
                                }
                                if ($val['children_num'] > 0) {
                                    $limit_where[] = "q.children_num = " . $val['children_num'];
                                }
                                if ($val['word_num_min'] > 0) {
                                    $limit_where[] = "q.word_num > " . ($val['word_num_min'] - 1);
                                }
                                if ($val['word_num_max'] > 0) {
                                    $limit_where[] = "q.word_num < " . ($val['word_num_max'] + 1);
                                }
                                $this_data->t_except_knowledge_ids = $this_data->except_knowledge_ids;
                                $this_data->except_knowledge_ids = array();
                                $res = self::insert_questions($this_data, 0, $limit_where, 1);
                                if ($res) {
                                    $this_data->except_knowledge_ids = $this_data->t_except_knowledge_ids;
                                    $val['ques_num'] --;
                                    continue 2;
                                }
                            }
                            $result['msg'] = '试题数量不足(' . $qtypes[$qtype] . ')';
                            return $result;
                        }
                    }
// 调节难易度
                    if ($diffs = self::get_difficulty($this_data)) {
                        $new_where['difficulty'] = "rc.difficulty BETWEEN $diffs[0] AND $diffs[1]";
                    }
                    $t_except_knowledge = $this_data->included_knowledge_ids;
                    if ($subject_id == 3 && in_array($qtype, array(0, 5))) {
                        $t_except_knowledge = array();
                    }
                    $res = self::insert_questions($this_data, 0, $new_where, 1, $t_except_knowledge, array(), 'q.group_id DESC');
                    if ($res === false) {
// 如果在难易度区间没找到试题，则取消区间再查询一次
                        if ($diffs) {
                            unset($new_where['difficulty']);
                            $res2 = self::insert_questions($this_data, 0, $new_where, 1, $t_except_knowledge, array(), 'q.group_id DESC');
                            if ($res2 == false) {
                                if ($include_added_knowdege == 2) {
                                    $result['msg'] = '试题数量不足(' . $qtypes[$qtype] . ')';
                                }
                                break;
                            }
                        } else {
                            if ($include_added_knowdege == 2) {
                                $result['msg'] = '试题数量不足(' . $qtypes[$qtype] . ')';
                            }
                            break;
                        }
                    }
                }
                if ($include_added_knowdege == 2 && $result['msg']) {
                    return $result;
                }
            }
        }
    }//}}}

    /**
     * 生成一分试卷（分组优先模式）
     *
     * @param
     *            array 考试信息
     * @param
     *            array 规则信息
     * @param
     *            array 重点规则信息
     * @return array
     */
    public static function generate($exam, $rule, $sub_rules)//{{{
    {
        $result = array('success' => false, 'msg' => '', 'code' => '-1');
        if (empty($exam) or empty($rule)) {
            $result['msg'] = '规则不存在';
            return $result;
        }

        $this_data = new ExamPaperModelPrivateData();
// --------------------------------------------//
// 初始化
// --------------------------------------------//
        $qtypes = C('qtype');
        $subject_id = &$rule['subject_id'];
        $grade_id = &$rule['grade_id'];
        $class_id = &$rule['class_id'];
        $subject_type = &$rule['subject_type'];
        $test_way = &$rule['test_way'];
        $knowledge_ids = &$rule['knowledge_ids'];
        $is_original = &$rule['is_original'];
        $difficulty_area = C('difficulty_area');
//
        if ($grade_id < 11 || $subject_id > 3 || !in_array($class_id, array(2, 3))) {
            $subject_type = '-1';
        } else {
            if ($subject_type == '-1') {
                $subject_type = 0;
            }
        }
        $this_data->target_difficulty = &$rule['difficulty'];
        $this_data->sub_rules = &$sub_rules;
        $where = array();
// 学科、年级、类型
        $where[] = "q.is_delete=0 AND q.parent_id=0";
        $where[] = "q.subject_id=$subject_id";
        $where[] = "q.ques_id=rc.ques_id AND rc.grade_id=$grade_id
                    AND rc.class_id=$class_id";
        if ($subject_type >= 0) {
            $where[] = "rc.subject_type=$subject_type";
        }
        if (in_array($test_way, array_keys(C('test_way')))) {
            $where[] = "q.test_way IN ({$test_way}, 3)";
        }
        /**
         * 题目类型 真题&原创
         */
        if (!empty($is_original)) {
            $where[] = "q.is_original in (" . $is_original . ")";
        }
        /*
         * 英语组题限制
         */
        $rule_limit = array();
        if ($subject_id == 3) {
            $rule_limits = Fn::db()->fetchAll("SELECT * FROM rd_exam_rule_qtype_limit WHERE exam_rule_id = " . $rule['rule_id']);
            if ($rule_limits) {
                foreach ($rule_limits as $key => $item) {
                    $rule_limit[$item['qtype']][] = $item;
                }
            }
        }
// 排除知识点
        $knowledge_id_arr = explode(',', $knowledge_ids);
        $query = Fn::db()->fetchAll("SELECT id FROM rd_knowledge WHERE subject_id = $subject_id AND pid > 0");
        foreach ($query as $row)
        {
            if (!in_array($row['id'], $rule['knowledge_id_arr']))
            {
                $this_data->except_knowledge_ids[] = $row['id'];
            }
        }
// 优先挑选重点填空题
        $create_tiankong_msg = self::create_tiankong($this_data, $rule, $where, $qtypes);
        if (is_array($create_tiankong_msg)) {
            return $create_tiankong_msg;
        }
// ----------------------------------------------------------//
//
// 在同期试卷（之前生成的最近N份试卷）的每一个试题分组中，
// 挑选未被使用过的同组试题，且满足重点知识点规则
// ----------------------------------------------------------//
        self::recent_group($this_data, $exam, $where, $rule);

// 重点知识点列表。挑选范围知识点时，先排除，不够再加进来。
        $rule_knowledge_ids = array();
        foreach ($this_data->sub_rules as &$sub_rule) {
            if ($sub_rule['pid'] == 0) {
                $rule_knowledge_ids += $sub_rule['children'];
            } else {
                $rule_knowledge_ids[] = $sub_rule['knowledge_id'];
            }
        }
        $rule_knowledge_ids = array_unique($rule_knowledge_ids);
        if (count($rule_knowledge_ids)) {
            $rule_knowledge_ids = array_combine(array_values($rule_knowledge_ids), $rule_knowledge_ids);
        }
        $this_data->rule_knowledge_ids = $rule_knowledge_ids;
// 优先挑选填空题
        if ($rule['ques_num'][3] > $this_data->ques_num[3]) {
            $num = $rule['ques_num'][3] - $this_data->ques_num[3];
            $new_where = $where;
            $new_where[] = "q.type=3";
            $res = self::insert_questions($this_data, 1, $new_where, $num);
            if (!$res) {
                $result['msg'] = '试题数量不足（填空）';
                return $result;
            }
        }
// -------------------------------------------------------------//
// 其他试题：范围知识点
// -------------------------------------------------------------//
        $rule_know_msg = self::rule_know($this_data, $where, $subject_id, $rule_limit, $qtypes, $rule);
        if (is_array($rule_know_msg)) {
            return $rule_know_msg;
        }
// $where[] = "q.ques_id IN (SELECT distinct ques_id FROM {pre}relate_knowledge
// where knowledge_id IN(".implode(',', $rule['knowledge_id_arr']).") and
// is_child=0)";
// --------------------------------------------//
// 范围知识点试题：
// 1, 先排除已加入试题的知识点
// 2, 排除重点知识点，再选择一遍
// 3, 再排除所有知识点，挑选一遍
// 每一遍挑选，都会实时把新选择的试题知识点加入排除列表
        $fanwei_knowledge_msg = self::fanwei_knowledge($this_data, $subject_id, $rule_knowledge_ids, $where, $rule, $rule_limit, $difficulty_area, $qtypes);
        if (is_array($fanwei_knowledge_msg)) {
            return $fanwei_knowledge_msg;
        }
// 检查是否有重复的试题试卷，如有有，则中断
        if (($repeat_paper = self::has_repeat_paper($exam['exam_id'], $this_data->ques_ids)) !=
                '') {
            $result['msg'] = '已经存在与该份试卷完全一样的试卷，无法再继续生成(<font color="blue">重复试卷：' .
                    $repeat_paper . '</font>)';
            $result['code'] = '-2';
            return $result;
        }

        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            // 生成试卷主干
            $paper = array('exam_id' => $exam['exam_id'],
                'ques_num' => array_sum($this_data->ques_num),
                'qtype_ques_num' => implode(',', $this_data->ques_num),
                'difficulty' => array_sum($this_data->difficulty) / count($this_data->difficulty),
                'uptime' => time(), 'addtime' => time(),
                'question_sort' => '',
                'question_score' => '');
            $db->insert('rd_exam_paper', $paper);
            $paper_id = $db->lastInsertId('rd_exam_paper', 'paper_id');
            $db->update('rd_exam_paper', array('paper_name' => $exam['exam_name'] . ' 试卷' . $paper_id), "paper_id = $paper_id");
            // 插入试题
            sort($this_data->ques_ids, SORT_NUMERIC);
            $paper_question = array();
            foreach ($this_data->ques_ids as $ques_id) {
                $row = array('paper_id' => $paper_id, 'exam_id' => $exam['exam_id'],
                    'ques_id' => $ques_id);
                $paper_question[] = $row;
            }
            if ($paper_question)
            {
                foreach ($paper_question as $v)
                {
                    $db->insert('rd_exam_question', $v);
                }
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('generate', 'exam_paper', $paper_id);
                $result['success'] = TRUE;
            }
        }
        return $result;
    }//}}}

    /**
     * 获取最近试卷的分组及其试题
     *
     * @param
     *            int 考试ID
     * @param
     *            int 指定获取最近的N份试卷信息
     * @return array
     */
    private static function recent_paper_question(ExamPaperModelPrivateData &$this_data, $exam_id, $n)//{{{
    {
        $this_data->recent_paper_group = array();
        $this_data->recent_paper_ques = array();
        $sql = "SELECT q.ques_id,q.group_id,q.knowledge FROM rd_exam_question eq,rd_question q
                WHERE eq.ques_id=q.ques_id AND eq.exam_id=$exam_id
                AND eq.paper_id IN (
                    SELECT paper_id FROM (
                        SELECT paper_id FROM rd_exam_paper
                        WHERE exam_id=$exam_id AND is_delete=0 ORDER BY paper_id DESC LIMIT 0, $n
                    )  as a
                )
                GROUP BY eq.ques_id ORDER BY q.group_id DESC";
        $query = Fn::db()->fetchAll($sql);
        foreach ($query as $row) {
            if ($row['group_id']) {
                $this_data->recent_paper_group[$row['group_id']][$row['ques_id']] = $row['knowledge'];
            }
            $this_data->recent_paper_ques[$row['ques_id']] = $row['knowledge'];
        }
    }//}}}

    /**
     * 尝试加入一个试题到重点知识点
     *
     * @param
     *            array 试题信息
     * @param
     *            boolean 在试题不符合任何重点知识点规则的时候，是否强制加入
     * @return boolean
     */
    private static function reset_rule_ques_num(ExamPaperModelPrivateData &$this_data, $question, $force = FALSE)//{{{
    {
        $added = $force;
// 修改待添加的重点知识点试题数量
        $pids = array();
        $offset = $question['type'] * 3 +
                ($question['difficulty'] <= 30 ? 0 : ($question['difficulty'] <= 60 ? 1 : 2));
        if (!is_array($question['knowledge']))
            $question['knowledge'] = explode(',', trim($question['knowledge'], ','));
        foreach ($question['knowledge'] as $kid) {
            foreach ($this_data->sub_rules as &$val) {
                if ($val['knowledge_id'] == $kid && $val['nums'][$offset] > 0) {
                    $val['nums'][$offset] --;
                    $added = TRUE;
                }
            }
// 如果存在一级重点知识点
            $pid = KnowledgeModel::get_knowledge($kid, 'pid');
            if (!isset($pids[$pid])) {
                foreach ($this_data->sub_rules as &$val) {
                    if ($val['knowledge_id'] == $pid && $val['nums'][$offset] > 0) {
                        $val['nums'][$offset] --;
                        $pids[$pid] = $pid;
                        $added = TRUE;
                    }
                }
            }
        }
        if ($added) {
            self::insert_question($this_data, $question);
        }
        return $added;
    }//}}}

    /**
     * 加入试题到试卷
     *
     * @param
     *            array 试题信息
     */
    private static function insert_question(ExamPaperModelPrivateData &$this_data, &$question)//{{{
    {
        if ($question['type'] == 7 && $question['translation'] == 1) {
            $this_data->translations['translation_c_e'] ++;
        } else if ($question['type'] == 7 && $question['translation'] == 2) {
            $this_data->translations['translation_e_c'] ++;
        }
        $this_data->ques_num[$question['type']] ++;
        if ($question['group_id']) {
            $this_data->group_ids[$question['group_id']] = $question['group_id'];
        }
        $this_data->ques_ids[$question['ques_id']] = $question['ques_id'];
        $this_data->difficulty[$question['ques_id']] = $question['difficulty'];
// 更新已存在的知识点
        if (!is_array($question['knowledge'])) {
            $question['knowledge'] = explode(',', trim($question['knowledge'], ','));
        }
        foreach ($question['knowledge'] as $kid) {
            $this_data->included_knowledge_ids[$kid] = $kid;
        }
    }//}}}

    /**
     * 获取在挑选重点知识点的时候，要先排除的：
     * 1，非重点知识点
     * 2，已完成的重点知识点
     *
     * @param
     *            int 正在挑选的知识点ID
     * @return array
     */
    private static function not_rule_knowledge(ExamPaperModelPrivateData &$this_data, $knowledge_id)//{{{
    {
        $list = array();
// 非重点知识点
        foreach ($this_data->included_knowledge_ids as $kid) {
            if (!isset($this_data->rule_knowledge_ids[$kid])) {
                $list[$kid] = $kid;
                continue;
            }
        }
// 已完成的重点知识点
        foreach ($this_data->sub_rules as &$sub) {
            $kid = $sub['knowledge_id'];
            if ($knowledge_id == $kid)
                break;
            if ($sub['pid'] == 0) {
                foreach ($sub['children'] as $cid) {
                    $list[$cid] = $cid;
                }
            } else {
                $list[$kid] = $kid;
            }
        }
        return $list;
    }//}}}

    /**
     * 判断重点知识点试题是否已完成
     */
    private static function check_rule_num(ExamPaperModelPrivateData &$this_data)//{{{
    {
        foreach ($this_data->sub_rules as $rule_id => &$sub_rule) {
            if (array_sum($sub_rule['nums'])) {
                return TRUE;
            }
        }
        return FALSE;
    }//}}}

    /**
     * 按试卷目标难易度，获取难易度调节区间
     */
    private static function get_difficulty(ExamPaperModelPrivateData &$this_data)//{{{
    {
        if (!$this_data->difficulty)
            return array();
        $target = $this_data->target_difficulty;
        $average = floor(array_sum($this_data->difficulty) / count($this_data->difficulty));
        if (abs($target - $average) <= 10)
            return array();
        if ($target > $average) {
            return array($target, $target + 30);
        } elseif ($target < $average) {
            $start = $target > 30 ? $target - 30 : 1;
            return array($start, $target);
        }
    }//}}}

    /**
     * 按条件挑选试题
     */
    private static function get_questions(ExamPaperModelPrivateData &$this_data, $where = array(), $except_group = array(), $except_knowledge = array())//{{{
    {
        if ($this_data->ques_ids) {
            $where[] = "q.ques_id NOT IN(" . my_implode($this_data->ques_ids) . ")";
        }
        if ($this_data->group_ids || $except_group) {
            $except = array_unique($this_data->group_ids + $except_group);
            $where[] = "q.group_id NOT IN (" . my_implode($except) . ")";
        }
        if ($this_data->except_knowledge_ids || $except_knowledge) {
            $except = array_unique($this_data->except_knowledge_ids + $except_knowledge);
            $where[] = "q.ques_id NOT IN (SELECT distinct ques_id FROM rd_relate_knowledge
                    where knowledge_id IN(" . my_implode($except) .
                    ") and is_child=0)";
        }
        $sql = "SELECT q.ques_id, q.type, q.group_id, q.knowledge, rc.difficulty
                FROM rd_question q, rd_relate_class rc
                WHERE " . implode(' AND ', $where) . "
                ORDER BY q.group_id DESC, q.ques_id DESC LIMIT 100";
        $query = Fn::db()->fetchAll($sql);
        return $query;
    }//}}}

    /**
     * 按条件挑选试题，并加入试卷
     */
    private static function insert_questions(ExamPaperModelPrivateData &$this_data, $is_rule, $where = array(), $num = 1, $except_knowledge = array(), $except_group = array(), $orderby = 'RAND()', $method_tactic_ids = array())//{{{
    {
        $old_where = $where;
        if ($this_data->ques_ids) {
            $where[] = "q.ques_id NOT IN(" . my_implode($this_data->ques_ids) . ")";
        }
        if ($this_data->group_ids || $except_group) {
            $except = array_unique($this_data->group_ids + $except_group);
            $where[] = "q.group_id NOT IN (" . my_implode($except) . ")";
        }
        if ($this_data->except_knowledge_ids) {
            $except = array_unique($this_data->except_knowledge_ids + $except_knowledge);
            $where[] = "q.ques_id NOT IN (SELECT distinct ques_id FROM rd_relate_knowledge
                    where knowledge_id IN(" . my_implode($except) . "))";
            // $where[] = "rk.knowledge_id NOT IN (" . my_implode($except) . ")";
        }
        if ($method_tactic_ids) {
            $where[] = "q.ques_id IN (SELECT distinct ques_id FROM rd_relate_method_tactic
                    where method_tactic_id IN(" .
                    my_implode($method_tactic_ids) . ") and is_child=0)";
        }
        $where_str = implode(' AND ', $where);
// 被考到的次数越小的试题，优先考虑
        $sql = "SELECT q.ques_id, q.type, q.group_id, q.knowledge, q.translation, rc.difficulty,
            (SELECT COUNT(eq.ques_id) FROM rd_exam_question eq WHERE eq.ques_id=q.ques_id) as times,
            (SELECT COUNT(etr.ques_id) FROM rd_exam_test_result etr WHERE etr.ques_id=q.ques_id) as tests
            FROM rd_question q, rd_relate_class rc
            WHERE {$where_str}
            ORDER BY (tests*100+times) ASC,$orderby LIMIT $num";
        $query = Fn::db()->fetchAll($sql);
        $has_group = FALSE;
        foreach ($query as $row) {
            if (isset($row['count']))
                unset($row['count']);
            if (isset($this_data->ques_ids[$row['ques_id']]))
                continue;
            if (isset($this_data->group_ids[$row['group_id']]))
                continue;
            if ($row['group_id'])
                $has_group = TRUE;
            if ($is_rule) {
                self::reset_rule_ques_num($this_data, $row, TRUE);
            } else {
                self::insert_question($this_data, $row);
            }
            if (--$num == 0) {
                break;
            }
        }
        if ($num && $has_group) {
            return self::insert_questions($this_data, $is_rule, $old_where, $num, $except_knowledge, $except_group, $orderby);
        }
        return $num == 0;
    }//}}}

    // --------------------------------------------------------------------------------//
    /**
     * 更新试卷统计信息
     *
     * @param
     *            int 试卷ID
     */
    public static function renew($id)//{{{
    {
        $exam_id = self::get_paper($id, 'exam_id');
        if (!$exam_id)
        {
            return FALSE;
        }
        $db = Fn::db();
        $sql = <<<EOT
SELECT subject_id,grade_id,class_id FROM rd_exam WHERE exam_id = {$exam_id}
EOT;
        $exam = $db->fetchRow($sql);
        if (empty($exam))
        {
            return FALSE;
        }
       
        $update = array('uptime' => time());
        
        // 平均难易度
        $sql = "SELECT AVG(rc.difficulty) avg_diff FROM rd_exam_question eq, rd_relate_class rc
                 WHERE eq.paper_id=$id AND eq.ques_id=rc.ques_id
                 AND rc.grade_id={$exam['grade_id']} AND rc.class_id={$exam['class_id']}";
        $avg = $db->fetchOne($sql);
        $update['difficulty'] = $avg ? $avg : 0;
        
        // 按题型分组统计数量
        $qtype_nums = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
        $sql = "SELECT q.type, COUNT(*) nums FROM rd_exam_question eq, rd_question q
                 WHERE eq.ques_id=q.ques_id AND eq.paper_id=$id
                 GROUP BY q.type";
        $query = $db->fetchAll($sql);
        foreach ($query as $row)
        {
            $qtype_nums[$row['type']] = $row['nums'];
        }
        $update['ques_num'] = array_sum($qtype_nums);
        $update['qtype_ques_num'] = implode(',', $qtype_nums);
        
        return $db->update('rd_exam_paper', $update, 'paper_id = '  . $id);
    }//}}}

    /**
     * 统计试卷详情
     *
     * @param
     *            int 试卷ID
     * @param
     *            boolean 技能是否按试题数量排序
     * @param
     *            boolean 知识点是否按试题数量排序
     * @return array
     */
    public static function detail($id, $skill_order = false, $knowledge_order = false)//{{{
    {
        $paper = self::get_paper($id);
        $paper_score = unserialize($paper['paper_score']);
        if (empty($paper))
        {
            return false;
        }
        
        // 方法逻辑包含学科
        $db = Fn::db();
        $res = $db->fetchRow(
                'SELECT GROUP_CONCAT(DISTINCT subject_id) AS subject_ids FROM rd_subject_category_subject');
        $paper['subject_ids'] = explode(',', $res['subject_ids']);
        $exam = $db->fetchRow("SELECT subject_id,qtype_score,total_score
                FROM rd_exam WHERE exam_id = " . $paper['exam_id'] . " LIMIT 1");
        $paper['exam'] = $exam;
        $paper['uptime'] = date('Y-m-d H:i:s', $paper['uptime']);
        
        // 试题数量
        $paper['qtype_ques_num_arr'] = array();
        foreach (explode(',', $paper['qtype_ques_num']) as $k => $v)
        {
            $paper['qtype_ques_num_arr'][$k] = $v;
        }
        /**
         * 试题分数 score
         */
        $total = 0;
        $qtype_score = explode(',', $exam['qtype_score']);
        // 单选 不定项 填空 完形填空 匹配题 选词填空 翻译 阅读填空 连词成句
        // 1 2 3 4 5 6 7 8 9
        foreach ($paper['qtype_ques_num_arr'] as $k => $v)
        {
            if ($k < 1)
            {
                continue;
            }
            if ($exam['subject_id'] != 3 && $k > 3)
            {
                continue;
            }
            $paper['score'][$k] = $qtype_score[$k - 1];
            $paper['total_score'][$k] = $v * $qtype_score[$k - 1];
            $total += $v * $qtype_score[$k - 1];
        }
        
        // 计算题组分数
        $total_0 = $exam['total_score'] - $total;
        $paper['total_score'][0] = $total_0;
        
        /**
         * 题组分值系数总和
         */
        $sql = "SELECT SUM(score_factor) AS sum FROM rd_question q
		LEFT JOIN rd_exam_question eq ON eq.ques_id=q.ques_id
		WHERE eq.paper_id=$id AND q.type=0";
        $sum_score_factor = $db->fetchRow($sql);
        
        // 试题id
        $paper['ques_ids'] = array();
        $query = $db->fetchAll("SELECT ques_id FROM rd_exam_question WHERE paper_id = $id");
        foreach ($query as $row)
        {
            $paper['ques_ids'][] = $row['ques_id'];
        }
        
        // 分组id
        $paper['group_ids'] = array();

        $sql = <<<EOT
SELECT DISTINCT q.group_id
FROM rd_exam_question eq
LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
WHERE eq.paper_id = {$id} AND q.group_id > 0
EOT;
        $query = $db->fetchAll($sql);
        foreach ($query as $row) {
            $paper['group_ids'][] = $row['group_id'];
        }
        $paper['group_count'] = count($paper['group_ids']);
        /* 题组类型试题 */
        $q_type_group = implode(',', C('q_type_group'));

        // -------------------------------------------------------------------//
        // 技能分布
        // -------------------------------------------------------------------//
                /*
                 * $skills = array(); $query = $this->db->select('id,skill_name')
                 * ->get_where('skill', array('subject_id'=>$exam['subject_id'])); foreach
                 * ($query->result_array() as $row) { $row['num'] = 0; $row['percent'] =
                 * '0.00%'; $skills[$row['id']] = $row; } $sql = "SELECT rs.ques_id,rs.skill_id
                 * FROM {pre}exam_question eq LEFT JOIN {pre}relate_skill rs ON
                 * eq.ques_id=rs.ques_id WHERE eq.paper_id=$id"; $query =
                 * $this->db->query($sql); foreach ($query->result_array() as $row) {
                 * $skills[$row['skill_id']]['num']++; } if ($paper['ques_num']) { foreach
                 * ($skills as &$skill) { $skill['percent'] =
                 * round($skill['num']*100/$paper['ques_num'], 2).'%'; } } if ($skill_order) {
                 * usort($skills, 'cmp_by_num_desc'); } $paper['skills'] = &$skills;
                 * //pr($skills, 1);
                 */
        // -------------------------------------------------------------------//
        // 方法策略分类分布
        // -------------------------------------------------------------------//
        if ($exam['subject_id'] && in_array($exam['subject_id'], $paper['subject_ids']))
        {
            $method_tactics = array();
            $result = $db->fetchAll(
                            "SELECT mt.id, mt.name
            FROM rd_method_tactic mt, rd_subject_category_subject scs
            WHERE mt.subject_category_id=scs.subject_category_id
            AND scs.subject_id=" . $exam['subject_id']);
            foreach ($result as $row) {
                $row['num'] = 0;
                $row['percent'] = '0.00%';
                $row['score'] = 0;
                $row['percent_score'] = '0.00%';
                $method_tactics[$row['id']] = $row;
            }
            /*
              $sql = "SELECT q.ques_id,rmt.method_tactic_id,q.parent_id,q.type,q.score_factor,q.subject_id
              FROM rd_exam_question eq
              LEFT JOIN rd_relate_method_tactic rmt ON eq.ques_id=rmt.ques_id
              LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
              WHERE eq.paper_id=$id and q.is_delete=0 and q.type not in ($q_type_group)
              UNION
              SELECT q.ques_id,rmt.method_tactic_id,q.parent_id,q.type,q.score_factor,q.subject_id
              FROM rd_relate_method_tactic rmt
              LEFT JOIN rd_question q ON q.ques_id=rmt.ques_id
              LEFT JOIN rd_exam_question eq ON q.parent_id=eq.ques_id
              WHERE eq.paper_id=$id and q.is_delete=0";
             */

            $sql = "SELECT q.ques_id,rmt.method_tactic_id,q.parent_id,q.type,q.score_factor,q.subject_id
		    FROM rd_exam_question eq
		    LEFT JOIN rd_relate_method_tactic rmt ON eq.ques_id=rmt.ques_id
		    LEFT JOIN rd_question q ON eq.ques_id=q.ques_id
		    WHERE eq.paper_id=$id and q.is_delete=0 AND q.type NOT IN ($q_type_group) AND rmt.ques_id>0
		    UNION
		    SELECT q.ques_id,rmt.method_tactic_id,q.parent_id,q.type,q.score_factor,q.subject_id
		    FROM rd_exam_question eq
		    LEFT JOIN rd_question q ON q.parent_id=eq.ques_id
		    LEFT JOIN rd_relate_method_tactic rmt ON q.ques_id=rmt.ques_id
		    WHERE eq.paper_id=$id AND q.is_delete=0 AND q.ques_id>0 AND rmt.ques_id>0";

            $result = $db->fetchAll($sql);
            $total_mt_count = 0;
            foreach ($result as $row)
            {
                if (isset($method_tactics[$row['method_tactic_id']])) {
                    $method_tactics[$row['method_tactic_id']]['num'] ++;
                    $total_mt_count ++;
                }
            }
            if ($paper['ques_num'])
            {
                foreach ($method_tactics as &$method_tactic)
                {
                    // 百分比
                    $method_tactic['percent'] = number_format(
                                    round($method_tactic['num'] * 100 / $total_mt_count, 2), 2) . '%';
                }
            }
            
            // 方法策略分类分数统计
            // 总分
            $mt_total_score = 0;
            foreach ($result as $k => $v)
            {
                if (isset($method_tactics[$v['method_tactic_id']]))
                {
                    if ($exam['subject_id'] != 3 && $v['type'] > 3)
                    {
                        continue;
                    }
                    if ($v['parent_id'] <= 0)
                    {
                        $method_tactics[$v['method_tactic_id']]['score'] += $paper_score[parent][$v[ques_id]][score][0]?$paper_score[parent][$v[ques_id]][score][0]:$qtype_score[$v['type'] - 1];
                        $mt_total_score += $qtype_score[$v['type'] - 1];
                    }
                    else
                    {
                        $yy = self::_get_group_score($v['ques_id'], $total_0, $qtype_score, $sum_score_factor);
                        $method_tactics[$v['method_tactic_id']]['score'] += $paper_score[parent][$v[parent_id]][children][$v[ques_id]][score][0]?$paper_score[parent][$v[parent_id]][children][$v[ques_id]][score][0]:$yy;
                        $mt_total_score += $yy;
                    }
                }
            }
            
            // 方法策略分类分数百分比
            if ($paper['ques_num'])
            {
                foreach ($method_tactics as &$method_tactic)
                {
                    // 百分比
                    $method_tactic['percent_score'] = number_format(
                                    round($method_tactic['score'] * 100 / $mt_total_score, 2), 2) . '%';
                }
            }
            /* 去除为空的方法策略 */
            foreach ($method_tactics as $key => $value)
            {
                if ($value['num'] <= 0)
                {
                    unset($method_tactics[$key]);
                }
            }
            if ($skill_order)
            {
                usort($method_tactics, 'cmp_by_num_desc');
            }
            $paper['method_tactics'] = &$method_tactics;
        }
        // pr($method_tactics, 1);
        // -------------------------------------------------------------------//
        // 知识点分布
        // -------------------------------------------------------------------//
        $knowledges = array();
        /*
          // 非题组
          $sql = "SELECT rk.ques_id,k.id,k.knowledge_name,k.pid,q.type,kp.knowledge_name as pname,q.subject_id,q.parent_id FROM
          {pre}exam_question eq
          LEFT JOIN {pre}question q on eq.ques_id=q.ques_id and q.is_delete=0
          LEFT JOIN {pre}relate_knowledge rk ON eq.ques_id=rk.ques_id
          LEFT JOIN {pre}knowledge k ON rk.knowledge_id=k.id
          LEFT JOIN {pre}knowledge kp ON k.pid=kp.id
          WHERE eq.paper_id=$id and q.type not in ($q_type_group) ";
          $query = $this->db->query($sql);
          $result_tmp = $query->result_array();
          echo $this->db->last_query();
          $result = array();
          foreach ($result_tmp as $v)
          {
          if (! empty($v['ques_id']))
          {
          $result[] = $v;
          }
          }
          // 题组
          $sql = "SELECT rk.ques_id,k.id,k.knowledge_name,k.pid,kp.knowledge_name as pname,rq.parent_id FROM
          {pre}relate_knowledge rk
          LEFT JOIN {pre}knowledge k ON rk.knowledge_id=k.id
          LEFT JOIN {pre}knowledge kp ON k.pid=kp.id
          LEFT JOIN {pre}question as rq on rk.ques_id=rq.ques_id
          WHERE rk.ques_id in(
          select q.ques_id
          from {pre}question q, {pre}exam_question eq
          where q.parent_id=eq.ques_id and eq.paper_id=$id  and q.is_delete=0
          )";
          $query = $this->db->query($sql);
          $group_result = $query->result_array();

          foreach ($group_result as $row)
          {
          if (! empty($row['ques_id']))
          {
          $row['type'] = 0;
          $result[] = $row;
          }
          }
         */
        $result = array();
        $sql = "SELECT rk.ques_id,k.id,k.knowledge_name,k.pid,q.type,kp.knowledge_name as pname,q.subject_id,q.parent_id FROM rd_exam_question eq
LEFT JOIN rd_question q on eq.ques_id=q.ques_id and q.is_delete=0
LEFT JOIN rd_relate_knowledge rk ON eq.ques_id=rk.ques_id
LEFT JOIN rd_knowledge k ON rk.knowledge_id=k.id
LEFT JOIN rd_knowledge kp ON k.pid=kp.id
WHERE eq.paper_id=$id AND q.type NOT IN ($q_type_group) AND rk.ques_id>0
UNION
SELECT rk.ques_id,k.id,k.knowledge_name,k.pid,q.type,kp.knowledge_name as pname,q.subject_id,q.parent_id
FROM rd_exam_question eq
LEFT JOIN rd_question q ON eq.ques_id=q.parent_id AND q.is_delete=0
LEFT JOIN rd_relate_knowledge rk ON q.ques_id=rk.ques_id
LEFT JOIN rd_knowledge k ON rk.knowledge_id=k.id
LEFT JOIN rd_knowledge kp ON k.pid=kp.id
WHERE eq.paper_id=$id AND rk.ques_id>0";
        $group_result = $db->fetchAll($sql);
        foreach ($group_result as $row)
        {
            if (!empty($row['ques_id']))
            {
                $row['type'] = 0;
                $result[] = $row;
            }
        }

        
        // 一级知识点总计出现次数
        $parent_knowledge_count = 0;
        
        // 二级知识点总计出现次数
        $chlid_knowledge_count = 0;
        
        // 知识点总分
        $knowledge_total_scroe = 0;
        
        // 临时数组 - 去除重复出现的知识点
        $pknowledge_ques = array();
        foreach ($result as $row)
        {
            // 一级知识点
            if (!isset($knowledges[$row['pid']]))
            {
                $knowledges[$row['pid']] = array('id' => $row['pid'], 'name' => $row['pname'],
                    'num' => 1, 'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%',
                    'children' => array());
                $parent_knowledge_count ++;
            }
            else if (!isset($pknowledge_ques[$row['pid']][$row['ques_id']]))
            {
                $knowledges[$row['pid']]['num']++;
                $parent_knowledge_count ++;
            }
            // 分数
            if ($row['parent_id'] <= 0)
            {
                $knowledges[$row['pid']]['score'] += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] - 1];
                $knowledge_total_scroe += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] - 1];
            }
            else
            {
                $group_child_score = self::_get_group_score($row['ques_id'], $total_0, $qtype_score, $sum_score_factor);
                $knowledges[$row['pid']]['score'] += $paper_score[parent][$row[parent_id]][children][$v[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$v[ques_id]][score][0]:$group_child_score;
                $knowledge_total_scroe += $paper_score[parent][$row[parent_id]][children][$v[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$v[ques_id]][score][0]:$group_child_score;
            }
            $pknowledge_ques[$row['pid']][$row['ques_id']] = 1;
            
            // 二级知识点
            if (!isset($knowledges[$row['pid']]['children'][$row['id']]))
            {
                $child = array('id' => $row['id'], 'name' => $row['knowledge_name'], 'num' => 1,
                    'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%');
                $knowledges[$row['pid']]['children'][$row['id']] = $child;
                $chlid_knowledge_count ++;
            }
            else
            {
                $knowledges[$row['pid']]['children'][$row['id']]['num'] ++;
                $chlid_knowledge_count ++;
            }
            
            // 分数
            if ($row['parent_id'] <= 0)
            {
                $knowledges[$row['pid']]['children'][$row['id']]['score'] += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] -
                        1];
            }
            else
            {
                $group_child_score = self::_get_group_score($row['ques_id'], $total_0, $qtype_score, $sum_score_factor);
                $knowledges[$row['pid']]['children'][$row['id']]['score'] += $paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]:$group_child_score;
            }
        }
        
        // 计算百分比number_format(
        if ($paper['ques_num'])
        {
            foreach ($knowledges as &$parent)
            {
                $parent['percent'] = number_format(
                                round($parent['num'] * 100 / $parent_knowledge_count, 2), 2) . '%';
                $parent['percent_score'] = number_format(
                                round($parent['score'] * 100 / $knowledge_total_scroe, 2), 2) . '%';
                foreach ($parent['children'] as &$child)
                {
                    $child['percent'] = number_format(
                                    round($child['num'] * 100 / $chlid_knowledge_count, 2), 2) . '%';
                    $child['percent_score'] = number_format(
                                    round($child['score'] * 100 / $knowledge_total_scroe, 2), 2) . '%';
                }
                
                // 取消引用
                unset($child);
                if ($knowledge_order)
                {
                    usort($parent['children'], 'cmp_by_num_desc');
                }
            }
            if ($knowledge_order)
            {
                usort($knowledges, 'cmp_by_num_desc');
            }
        }
        $paper['knowledges'] = $knowledges;
        
        // --------------------------------信息提取方式分布---------------------------
        // 取得本张试卷的所有信息提取方式
        $paper['subject_id'] = $exam['subject_id'];
        if ($paper['subject_id'] == 3) {

            $result = array();
            /*
              // 非题组
              $sql = "SELECT rgt.ques_id,g.id,g.group_type_name,q.type,g.pid,gp.group_type_name as pname,q.parent_id
              FROM {pre}exam_question eq
              LEFT JOIN {pre}question q on eq.ques_id=q.ques_id
              LEFT JOIN {pre}relate_group_type rgt ON eq.ques_id=rgt.ques_id
              LEFT JOIN {pre}group_type g ON rgt.group_type_id=g.id
              LEFT JOIN {pre}group_type gp ON g.pid=gp.id
              WHERE eq.paper_id=$id and q.type not in ($q_type_group) and q.is_delete=0";
              $query = $this->db->query($sql);

              $result_tmp = $query->result_array();
              foreach ($result_tmp as $v)
              {
              if (! empty($v['ques_id']))
              {
              $result[] = $v;
              }
              }
              // 题组
              $sql = "SELECT rgt.ques_id,g.id,g.group_type_name,g.pid,gp.group_type_name as pname,rq.parent_id
              FROM {pre}relate_group_type rgt
              LEFT JOIN {pre}group_type g ON rgt.group_type_id=g.id
              LEFT JOIN {pre}group_type gp ON g.pid=gp.id
              LEFT JOIN {pre}question rq ON rgt.ques_id=rq.ques_id
              WHERE rgt.ques_id in(
              select q.ques_id
              from {pre}question q, {pre}exam_question eq
              where q.parent_id=eq.ques_id and eq.paper_id=$id and q.is_delete=0
              )";
              $query = $this->db->query($sql);
              $group_result = $query->result_array();

              foreach ($group_result as $row)
              {
              if (! empty($row['ques_id']))
              {
              $row['type'] = 0;
              $result[] = $row;
              }
              }
             */


            $sql = <<<EOT
SELECT rgt.ques_id,g.id,g.group_type_name,q.type,g.pid,gp.group_type_name as pname,q.parent_id
FROM rd_exam_question eq
LEFT JOIN rd_question q on eq.ques_id=q.ques_id
LEFT JOIN rd_relate_group_type rgt ON eq.ques_id=rgt.ques_id
LEFT JOIN rd_group_type g ON rgt.group_type_id=g.id
LEFT JOIN rd_group_type gp ON g.pid=gp.id
WHERE eq.paper_id = {$id} AND q.type NOT IN ({$q_type_group}) AND q.is_delete=0 AND rgt.ques_id>0
UNION
SELECT rgt.ques_id,g.id,g.group_type_name,q.type,g.pid,gp.group_type_name as pname,q.parent_id
FROM rd_exam_question eq
LEFT JOIN rd_question q on q.parent_id=eq.ques_id
LEFT JOIN rd_relate_group_type rgt ON q.ques_id=rgt.ques_id
LEFT JOIN rd_group_type g ON rgt.group_type_id=g.id
LEFT JOIN rd_group_type gp ON g.pid=gp.id
WHERE eq.paper_id=$id AND q.is_delete=0 AND rgt.ques_id>0
EOT;
            $group_result = $db->fetchAll($sql);
            foreach ($group_result as $row)
            {
                if (!empty($row['ques_id']))
                {
                    $row['type'] = 0;
                    $result[] = $row;
                }
            }


            
            // 统计出现次数
            $group_types = array();
            
            // 一级提取方式总计出现次数
            $parent_group_type_count = 0;
            
            // 二级提取方式总计出现次数
            $chlid_group_type_count = 0;
            
            // 提取方式总分
            $group_type_count_total = 0;
            
            // 临时数组 - 去除重复出现的提取方式
            $group_type_temporary = array();
            foreach ($result as $row)
            {
                // 一级提取方式
                if (!isset($group_types[$row['pid']]))
                {
                    $group_types[$row['pid']] = array('id' => $row['pid'], 'name' => $row['pname'],
                        'num' => 1, 'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%',
                        'children' => array());
                    $parent_group_type_count ++;
                }
                else if (!isset($group_type_temporary[$row['pid']][$row['ques_id']]))
                {
                    $group_types[$row['pid']]['num'] ++;
                    $parent_group_type_count ++;
                }
                $group_type_temporary[$row['pid']][$row['ques_id']] = 1;
                
                // 分数
                if ($row['parent_id'] <= 0)
                {
                    $group_types[$row['pid']]['score'] += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] - 1];
                    $group_type_count_total += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] - 1];
                }
                else
                {
                    $group_child_score = self::_get_group_score($row['ques_id'], $total_0, $qtype_score, $sum_score_factor);
                    $group_types[$row['pid']]['score'] += $paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]:$group_child_score;
                    $group_type_count_total += $paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]:$group_child_score;
                }
                
                // 二级提取方式
                if (!isset($group_types[$row['pid']]['children'][$row['id']]))
                {
                    $child = array('id' => $row['id'], 'name' => $row['group_type_name'], 'num' => 1,
                        'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%');
                    $group_types[$row['pid']]['children'][$row['id']] = $child;
                    $chlid_group_type_count++;
                }
                else
                {
                    $group_types[$row['pid']]['children'][$row['id']]['num']++;
                    $chlid_group_type_count ++;
                }
                
                // 分数
                if ($row['parent_id'] <= 0)
                {
                    $group_types[$row['pid']]['children'][$row['id']]['score'] += $paper_score[parent][$row[ques_id]][score][0]?$paper_score[parent][$row[ques_id]][score][0]:$qtype_score[$row['type'] -
                            1];
                }
                else
                {
                    $group_child_score = self::_get_group_score($row['ques_id'], $total_0, $qtype_score, $sum_score_factor);
                    $group_types[$row['pid']]['children'][$row['id']]['score'] += $paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]?$paper_score[parent][$row[parent_id]][children][$row[ques_id]][score][0]:$group_child_score;
                }
            }
            
            // 计算百分比, 2)
            if ($paper['ques_num'])
            {
                foreach ($group_types as &$parent)
                {
                    $parent['percent'] = number_format(
                                    round($parent['num'] * 100 / $parent_group_type_count, 2), 2) . '%';
                    $parent['percent_score'] = number_format(
                                    round($parent['score'] * 100 / $group_type_count_total, 2), 2) . '%';
                    foreach ($parent['children'] as &$child)
                    {
                        $child['percent'] = number_format(
                                        round($child['num'] * 100 / $chlid_group_type_count, 2), 2) . '%';
                        $child['percent_score'] = number_format(
                                        round($child['score'] * 100 / $group_type_count_total, 2), 2) . '%';
                    }
                }
            }
            $paper['group_types'] = $group_types;
        }
        return $paper;
    }//}}}

    /**
     * 统计手工试卷详情
     *
     * @param
     *            int 试卷ID
     * @param
     *            boolean 技能是否按试题数量排序
     * @param
     *            boolean 知识点是否按试题数量排序
     * @return array
     */
    public static function detail_sg($id, $skill_order = false, $knowledge_order = false)//{{{
    {
        $paper = self::get_paper($id);
        $question_scores = json_decode($paper['question_score'], true);
        if (empty($paper))
        {
            return false;
        }
        // 方法逻辑包含学科
        $db = Fn::db();
        $sql = <<<EOT
SELECT GROUP_CONCAT(DISTINCT subject_id) AS subject_ids 
FROM rd_subject_category_subject
EOT;
        $res = $db->fetchRow($sql);
        $paper['subject_ids'] = explode(',', $res['subject_ids']);
        $paper['uptime'] = date('Y-m-d H:i:s', $paper['uptime']);
        // 试题数量
        $paper['qtype_ques_num_arr'] = explode(',', $paper['qtype_ques_num']);
        /* 试题总分 */
        $total = 0;
        foreach ($question_scores as $key => $value)
        {
            $total += array_sum($value);
        }
        $paper['total_scores'] = $total;
        // 试题id
        $paper['ques_ids'] = json_decode($paper['question_sort'], true);
        $ques_ids_str = implode(',', $paper['ques_ids']);
        // 分组id
        $paper['group_ids'] = array();
        $sql = <<<EOT
SELECT COUNT(DISTINCT group_id) AS c FROM rd_question 
WHERE ques_id IN ({$ques_ids_str}) AND group_id > 0
EOT;
        $res = $db->fetchRow($sql);
        $paper['group_count'] = $res['c'];
        /* 题组类型试题 */
        $q_type_group = implode(',', C('q_type_group'));
        
        $sql = "SELECT ques_id FROM rd_question
                WHERE ques_id IN ($ques_ids_str) 
                AND children_num > 0";
        $paren_id_str = implode(',', $db->fetchCol($sql));
        $where_str = $paren_id_str ? " AND q.ques_id NOT IN ($paren_id_str)" : "";
        
        // -------------------------------------------------------------------//
        // 方法策略分类分布
        // -------------------------------------------------------------------//
        if ($paper['subject_id'])
        {
            $method_tactics = array();
            $subject_id = $paper['subject_id'];
            $sql = <<<EOT
SELECT mt.id, mt.name
FROM rd_method_tactic mt, rd_subject_category_subject scs
WHERE mt.subject_category_id = scs.subject_category_id
AND scs.subject_id = {$subject_id}
EOT;
            $result = $db->fetchAll($sql);
            foreach ($result as $row)
            {
                $row['num'] = 0;
                $row['percent'] = '0.00%';
                $row['score'] = 0;
                $row['percent_score'] = '0.00%';
                $method_tactics[$row['id']] = $row;
            }
            $sql = <<<EOT
SELECT q.ques_id,rmt.method_tactic_id,q.parent_id,q.type,q.score_factor
FROM rd_relate_method_tactic rmt
LEFT JOIN rd_question q ON q.ques_id=rmt.ques_id
WHERE ((q.ques_id IN ({$ques_ids_str}) AND q.type NOT IN ({$q_type_group})) 
OR (q.parent_id IN ({$ques_ids_str}))) $where_str
EOT;
            $result = $db->fetchAll($sql);
            $total_mt_count = 0;
            /* 方法策略总分 */
            $total_mt_score = 0;
            foreach ($result as $row)
            {
                if (isset($method_tactics[$row['method_tactic_id']]))
                {
                    $method_tactics[$row['method_tactic_id']]['num']++;
                    $total_mt_count++;
                }
                /* 分数 */
                if (isset($method_tactics[$row['method_tactic_id']]))
                {
                    if ($row['parent_id'] <= 0)
                    {
                        $method_tactics[$row['method_tactic_id']]['score'] += $question_scores[$row['ques_id']][0];
                        $total_mt_score += $question_scores[$row['ques_id']][0];
                    }
                    else
                    {
                        $method_tactics_score = self::_get_group_score_1($row['ques_id'], $question_scores);
                        $method_tactics[$row['method_tactic_id']]['score'] += $method_tactics_score;
                        $total_mt_score += $method_tactics_score;
                    }
                }
            }
            if ($paper['ques_num'])
            {
                foreach ($method_tactics as &$method_tactic)
                {
                    // 百分比
                    $method_tactic['percent'] = number_format(
                                    round($method_tactic['num'] * 100 / $total_mt_count, 2), 2) . '%';
                    // 分数百分比
                    $method_tactic['percent_score'] = number_format(
                                    round($method_tactic['score'] * 100 / $total_mt_score, 2), 2) . '%';
                }
            }
            if ($skill_order)
            {
                usort($method_tactics, 'cmp_by_num_desc');
            }
            /* 去除出现次数为零的方法策略 */
            foreach ($method_tactics as $key => $value)
            {
                if ($value['num'] <= 0)
                {
                    unset($method_tactics[$key]);
                }
            }
            $paper['method_tactics'] = $method_tactics;
        }
        // -------------------------------------------------------------------//
        // 知识点分布
        // -------------------------------------------------------------------//
        $knowledges = array();
        $sql = <<<EOT
SELECT q.ques_id,q.parent_id,k.id,k.knowledge_name,k.pid,q.type,kp.knowledge_name pname
FROM rd_question q
LEFT JOIN rd_relate_knowledge rk ON q.ques_id=rk.ques_id
LEFT JOIN rd_knowledge k ON rk.knowledge_id=k.id
LEFT JOIN rd_knowledge kp ON k.pid=kp.id
WHERE ((q.ques_id IN ({$ques_ids_str}) AND q.type NOT IN ({$q_type_group})) 
OR (q.parent_id IN ({$ques_ids_str}))) $where_str
EOT;
        $result_tmp = $db->fetchAll($sql);
        $result = array();
        foreach ($result_tmp as $v)
        {
            if (!empty($v['ques_id']) && !empty($v['id']))
            {
                $result[] = $v;
            }
        }
        // 一级知识点总计出现次数
        $parent_knowledge_count = 0;
        // 二级知识点总计出现次数
        $chlid_knowledge_count = 0;
        /* 知识点总分 */
        $knowledge_total_score = 0;
        // 临时数组 - 去除重复出现的知识点
        $pknowledge_ques = array();
        foreach ($result as $row)
        {
            // 一级知识点
            if (!isset($knowledges[$row['pid']]))
            {
                $knowledges[$row['pid']] = array('id' => $row['pid'], 'name' => $row['pname'],
                    'num' => 1, 'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%',
                    'children' => array());
                $parent_knowledge_count++;
            }
            else if (!isset($pknowledge_ques[$row['pid']][$row['ques_id']]))
            {
                $knowledges[$row['pid']]['num']++;
                $parent_knowledge_count++;
            }
            // 分数
            if ($row['parent_id'] <= 0)
            {
                $knowledges[$row['pid']]['score'] += $question_scores[$row['ques_id']][0];
            }
            else
            {
                $group_child_score = self::_get_group_score_1($row['ques_id'], $question_scores);
                $knowledges[$row['pid']]['score'] += $group_child_score;
            }
            $pknowledge_ques[$row['pid']][$row['ques_id']] = 1;
            // 二级知识点
            if (!isset($knowledges[$row['pid']]['children'][$row['id']]))
            {
                $child = array('id' => $row['id'], 'name' => $row['knowledge_name'], 'num' => 1,
                    'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%');
                $knowledges[$row['pid']]['children'][$row['id']] = $child;
                $chlid_knowledge_count++;
            }
            else
            {
                $knowledges[$row['pid']]['children'][$row['id']]['num']++;
                $chlid_knowledge_count++;
            }
            // 分数
            if ($row['parent_id'] <= 0)
            {
                $knowledges[$row['pid']]['children'][$row['id']]['score'] += $question_scores[$row['ques_id']][0];
                $knowledge_total_score += $question_scores[$row['ques_id']][0];
            }
            else
            {
                $group_child_score = self::_get_group_score_1($row['ques_id'], $question_scores);
                $knowledges[$row['pid']]['children'][$row['id']]['score'] += $group_child_score;
                $knowledge_total_score += $group_child_score;
            }
        }
        // 计算百分比
        foreach ($knowledges as &$parent)
        {
            $parent['percent'] = number_format(
                            round($parent['num'] * 100 / $parent_knowledge_count, 2), 2) . '%';
            $parent['percent_score'] = number_format(
                            round($parent['score'] * 100 / $knowledge_total_score, 2), 2) . '%';
            foreach ($parent['children'] as &$child)
            {
                $child['percent'] = number_format(
                                round($child['num'] * 100 / $chlid_knowledge_count, 2), 2) . '%';
                $child['percent_score'] = number_format(
                                round($child['score'] * 100 / $knowledge_total_score, 2), 2) . '%';
            }
            // 取消引用
            unset($child);
            if ($knowledge_order)
            {
                usort($parent['children'], 'cmp_by_num_desc');
            }
        }
        if ($knowledge_order)
        {
            usort($knowledges, 'cmp_by_num_desc');
        }
        $paper['knowledges'] = $knowledges;
        // --------------------------------信息提取方式分布---------------------------
        // 取得本张试卷的所有信息提取方式
        $result = array();
        $sql = <<<EOT
SELECT q.ques_id,q.parent_id,g.id,g.group_type_name,q.type,g.pid,gp.group_type_name pname
FROM rd_question q
LEFT JOIN rd_relate_group_type rgt ON q.ques_id=rgt.ques_id
LEFT JOIN rd_group_type g ON rgt.group_type_id=g.id
LEFT JOIN rd_group_type gp ON g.pid=gp.id
WHERE ((q.ques_id IN ({$ques_ids_str}) AND q.type NOT IN ({$q_type_group})) OR 
(q.parent_id IN ({$ques_ids_str}))) $where_str
EOT;
        $result_tmp = $db->fetchAll($sql);
        foreach ($result_tmp as $v)
        {
            if (!empty($v['ques_id']) && !empty($v['id']))
            {
                $result[] = $v;
            }
        }
        // 统计出现次数
        $group_types = array();
        // 一级提取方式总计出现次数
        $parent_group_type_count = 0;
        // 二级提取方式总计出现次数
        $chlid_group_type_count = 0;
        /* 信息提取方式总分 */
        $group_type_total = 0;
        // 临时数组 - 去除重复出现的提取方式
        $group_type_temporary = array();
        foreach ($result as $row)
        {
            // 一级提取方式
            if (!isset($group_types[$row['pid']]))
            {
                $group_types[$row['pid']] = array('id' => $row['pid'], 'name' => $row['pname'],
                    'num' => 1, 'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%',
                    'children' => array());
                $parent_group_type_count++;
            }
            else if (!isset($group_type_temporary[$row['pid']][$row['ques_id']]))
            {
                $group_types[$row['pid']]['num']++;
                $parent_group_type_count++;
            }
            $group_type_temporary[$row['pid']][$row['ques_id']] = 1;
            /* 分数 */
            if ($row['parent_id'] <= 0)
            {
                $group_types[$row['pid']]['score'] += $question_scores[$row['ques_id']][0];
            }
            else
            {
                $group_child_score = self::_get_group_score_1($row['ques_id'], $question_scores);
                $group_types[$row['pid']]['score'] += $group_child_score;
            }
            // 二级提取方式
            if (!isset($group_types[$row['pid']]['children'][$row['id']]))
            {
                $child = array('id' => $row['id'], 'name' => $row['group_type_name'], 'num' => 1,
                    'percent' => '0.00%', 'score' => 0, 'percent_score' => '0.00%');
                $group_types[$row['pid']]['children'][$row['id']] = $child;
                $chlid_group_type_count++;
            }
            else
            {
                $group_types[$row['pid']]['children'][$row['id']]['num']++;
                $chlid_group_type_count++;
            }
            /* 分数 */
            if ($row['parent_id'] <= 0)
            {
                $group_types[$row['pid']]['children'][$row['id']]['score'] += $question_scores[$row['ques_id']][0];
            }
            else
            {
                $group_child_score = self::_get_group_score_1($row['ques_id'], $question_scores);
                $group_types[$row['pid']]['children'][$row['id']]['score'] += $group_child_score;
                $group_type_total += $group_child_score;
            }
        }
        // 计算百分比
        foreach ($group_types as &$parent)
        {
            $parent['percent'] = number_format(
                            round($parent['num'] * 100 / $parent_group_type_count, 2), 2) . '%';
            $parent['percent_score'] = number_format(
                            round($parent['score'] * 100 / $group_type_total, 2), 2) . '%';
            foreach ($parent['children'] as &$child)
            {
                $child['percent'] = number_format(
                                round($child['num'] * 100 / $chlid_group_type_count, 2), 2) . '%';
                $child['percent_score'] = number_format(
                                round($child['score'] * 100 / $group_type_total, 2), 2) . '%';
            }
        }
        $paper['group_types'] = $group_types;
        return $paper;
    }//}}}

    /**
     * 将已取到的试题关联的方法策略分类对应的试题数 进行升序排，
     * 试题不够时，用这些方法策略分类关联的其他试题来补齐
     */
    private static function _get_inserted_question_method_tactics($ques_ids)//{{{
    {
        $method_tactics = Fn::db()->fetchAll(
                        "SELECT method_tactic_id, COUNT(*) AS `count`
    	                  FROM rd_relate_method_tactic
    	                  WHERE ques_id IN (" . my_implode($ques_ids) .
                        ")
    	                  GROUP BY method_tactic_id ORDER BY `count` ASC LIMIT 10");
        $method_tactic_ids = array();
        foreach ($method_tactics as $row)
        {
            $method_tactic_ids[] = $row['method_tactic_id'];
        }
        return $method_tactic_ids;
    }//}}}

    /**
     * 计算题组子题分数
     *
     * @author TCG
     * @param int $group_id
     *            题组id
     * @param int $total_group
     *            题组总分
     * @return array 分数
     */
    private static function _get_group_score($group_id, $total_group, $qtype_score, $sum_score_factor)//{{{
    {
        if ($total_group < 0)
        {
            return 0;
        }
        // 题组信息
        $sql = <<<EOT
SELECT children_num,score_factor,is_parent,parent_id
FROM rd_question WHERE ques_id = {$group_id} LIMIT 1
EOT;
        $question = Fn::db()->fetchRow($sql);
        // 题组子题 在查询一遍
        if (!$question['is_parent'] && $question['parent_id'] > 0)
        {
            $ques_id = $question['parent_id'];
            $sql = <<<EOT
SELECT ques_id,children_num,score_factor,is_parent,type,subject_id
FROM rd_question WHERE ques_id = {$ques_id} LIMIT 1
EOT;
            $question = Fn::db()->fetchRow($sql);
        }
        if ($question['type'] > 0)
        {
            $score = round(
                    (($qtype_score[$question['type'] - 1]) / $question['children_num']), 2);
        }
        else
        {
            $score = round(
                    ($total_group * $question['score_factor']) /
                    ($question['children_num'] * $sum_score_factor['sum']));
        }
        return $score;
    }//}}}

    private static function _get_group_score_1($group_id, $qtype_score)//{{{
    {
        // 题组信息
        $sql = <<<EOT
SELECT children_num,score_factor,is_parent,parent_id,type
FROM rd_question WHERE ques_id = {$group_id} LIMIT 1
EOT;
        $question = Fn::db()->fetchRow($sql);
        
        
        if ($question['type'] == 10)
        {
            $sql = "SELECT ques_id, parent_id FROM rd_question
                    WHERE (parent_id = $group_id 
                    OR parent_id = {$question['parent_id']})
                    AND is_delete = 0
                    ORDER BY ques_id ASC ";
            $ques_ids = Fn::db()->fetchPairs($sql);
            $parent_id = current(array_unique(array_values($ques_ids)));
            $ques_ids = array_keys($ques_ids);
            foreach ($ques_ids as $k => $q_id)
            {
                if ($q_id == $group_id)
                {
                    $score = $qtype_score[$parent_id][$k];
                }
            }
        }
        else
        {
            // 题组子题 在查询一遍
            if (!$question['is_parent'] 
                && $question['parent_id'] > 0)
            {
                $ques_id = $question['parent_id'];
                
                $sql = <<<EOT
SELECT ques_id,children_num,score_factor,is_parent,type,subject_id
FROM rd_question WHERE ques_id = {$ques_id} LIMIT 1
EOT;
                $question = Fn::db()->fetchRow($sql);
                
                //组合题
                if ($question['type'] == 15)
                {
                    $sql = "SELECT ques_id FROM rd_question
                            WHERE (parent_id = $group_id
                            OR parent_id = {$ques_id})
                            AND is_delete = 0
                            ORDER BY ques_id ASC";
                    $ques_ids = Fn::db()->fetchCol($sql);
                    foreach ($ques_ids as $k => $q_id)
                    {
                        if ($q_id == $group_id)
                        {
                            $score = $qtype_score[$ques_id][$k];
                        }
                    }
                    
                    return $score;
                }
            }
            
            $score = round(
                (($qtype_score[$question['ques_id']][0]) / $question['children_num']), 2);
        }
        
        return $score;
    }//}}}

    /**
     * 获取一个试卷信息
     *
     * @param
     *            int 试卷ID(paper_id)
     * @param
     *            string 字段列表(多个字段用逗号分割，默认取全部字段)
     * @return mixed 指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_paper_by_id($id = 0, $item = NULL)//{{{
    {
        if ($id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $sql = 'SELECT ' . $item;
        }
        else
        {
            $sql = 'SELECT *';
        }
        $sql .= ' FROM rd_exam_paper WHERE paper_id = ' . $id;
        $row = Fn::db()->fetchRow($sql);
        if (is_string($item) && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }//}}}
    
    /**
     * 获取考试试卷
     */
    public static function examSubjectPaperList($exam_id)
    {
        if (!Validate::isInt($exam_id)
            || $exam_id <= 0)
        {
            return array();
        }
        
        $sql = "SELECT ep.* FROM rd_exam_subject_paper esp
                LEFT JOIN rd_exam_paper ep ON esp.paper_id = ep.paper_id
                WHERE esp.exam_id = $exam_id
                ";
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 获取试卷包含的试题（子题也需要统计）
     */
    public static function examPaperQuestion($paper_id, $item = 'q.*', $where = '', $order_by = '')
    {
        if (!Validate::isInt($paper_id)
            || $paper_id <= 0)
        {
            return array();
        }
        
        $item = $item ? $item : 'q.*';
        
        $sql = "SELECT $item FROM rd_exam_question epq
                LEFT JOIN rd_question q ON epq.ques_id = q.ques_id 
                    OR q.parent_id = epq.ques_id
                LEFT JOIN rd_question pq ON pq.ques_id = q.parent_id
                WHERE epq.paper_id = $paper_id AND q.is_delete = 0
                $where
                $order_by
                ";
        return Fn::db()->fetchAssoc($sql);
    }
}
