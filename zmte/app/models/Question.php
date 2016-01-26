<?php
/**
 * 知识点模块 QuestionModel
 * @file    Question.php
 * @author  BJP
 * @final   2015-07-01
 */
class QuestionModel
{
    /**
     * 按ID读取一个试题信息
     *
     * @param   int     试题id
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_question($ques_id = 0, $item = NULL)
    {
        if ($ques_id == 0)
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
        $sql .= " FROM rd_question WHERE ques_id = $ques_id LIMIT 1";
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
     * 添加试题
     *
     * @param   array    试题基本信息
     * @param   array    选项信息
     * @param   mixed    试题答案。不定项(array)，其他(string)
     * @param   array    试题关联信息
     * @return  array    包含是否成功，错误信息等
     */
    public static function add(&$question, &$options, &$answer, &$extends)
    {
        $result = array(
            'ques_id'  => '',
            'group_id' => '',
            'msg'      => '',
            'success' => FALSE
        );
        $bOk = false;
        $db = Fn::db();

        try
        {
        if ($db->beginTransaction())
        {
            $question['admin_id'] = Fn::sess()->userdata('admin_id');
            $question['addtime'] = time();
            !isset($question['group_type']) && $question['group_type'] = '';
            !isset($question['tags']) && $question['tags'] = 0;
            !isset($question['sort']) && $question['sort'] = 0;
            empty($question['test_way']) && $question['test_way'] = 0;

            if($question['subject_id'] == 3)
            {
                $word_num = str_word_count(str_replace(array("&nbsp;",'&#39;'),array('',''),
                        strip_tags(htmlspecialchars_decode($question['title']))));
                $question['word_num'] = $word_num;
            }
            else
            {
                $question['word_num'] = 0;
            }

            if (isset($question['parent_id']))
            {
                $ques2 = $db->fetchRow('SELECT * FROM rd_question '
                    . 'WHERE ques_id = ' . $question['parent_id']);
                $question['is_original'] = $ques2['is_original'];
            }

            $db->insert('rd_question', $question);

            $ques_id = $db->lastInsertId('rd_question', 'ques_id');

            // 如果是子题，更新题干子题数
            if (!empty($question['parent_id']))
            {
                $sql = "UPDATE rd_question SET children_num=children_num+1
                        WHERE ques_id='$question[parent_id]'";
                $db->exec($sql);
            }

            $up_row = array();
            // 插入选项，并设置答案
            if (in_array($question['type'],array(1, 2, 7, 14)))
            {
                $new_answer = $question['type'] == 2 ? array() : '';
                foreach ($options as $k => $opt)
                {
                    $opt['ques_id'] = $ques_id;
                    $db->insert('rd_option', $opt);
                    if ($question['type'] == 1 || $question['type'] == 7)
                    {
                        if ($answer == $k)
                        {
                            $new_answer = $db->lastInsertId('rd_option', 'option_id');
                        }
                    }
                    else
                    {
                        if (in_array($k, $answer))
                        {
                            $new_answer[] = $db->lastInsertId('rd_option', 'option_id');
                        }
                    }
                }
                // 更新试题答案、关联分组
                if ($question['type'] == 2)
                {
                    sort($new_answer, SORT_NUMERIC);
                    $new_answer = implode(',', $new_answer);
                }
                $up_row['answer'] = $new_answer;
            }

            // 设置关联分组
            $group_id = $extends['group_id'];
            if ($group_id)
            {
                $group = $db->fetchRow("SELECT * FROM rd_relate_group WHERE group_id = $group_id LIMIT 1");
                if (empty($group))
                {
                    $group_id = 0;
                }
            }
            if (empty($group_id) && ! empty($extends['relate_ques_id']))
            {
                $group_id = (int)self::get_question(
                        $extends['relate_ques_id'], 'group_id');
                if (empty($group_id))
                {
                    $db->insert('rd_relate_group',
                            array('group_name'=>$extends['relate_ques_id']));
                    $group_id = $db->lastInsertId('rd_relate_group', 'group_id');
                    $db->update('rd_question', array('group_id'=>$group_id),
                            'ques_id = ' . $extends['relate_ques_id']);
                }
            }
            if ($group_id)
            {
                $up_row['group_id'] = $group_id;
                $result['group_id'] = $group_id;
            }
            if ($up_row)
            {
                $db->update('rd_question', $up_row, "ques_id = $ques_id");
            }

            $skill_ids     = &$extends['skill_ids'];
            $method_tactic_ids = &$extends['method_tactic_ids'];
            $knowledge_ids = &$extends['knowledge_ids'];
            $know_process = &$extends['know_process'];
            $relate_class  = &$extends['relate_class'];

            // 更新关联技能
            $inserts = array();
            /*
            if (is_array($skill_ids) && count($skill_ids)) {
                foreach ($skill_ids as $skill_id)
                {
                    $relate = array(
                        'ques_id'  => $ques_id,
                        'skill_id' => $skill_id
                    );
                    if (isset($question['parent_id'])
                       && $question['parent_id'] > 0)
                       $relate['is_child'] = 1;
                    $inserts[] = $relate;
                }
            }
            */
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_skill', $v);
                }
            }

            // 更新关联方法策略
            $inserts = array();
            foreach ($method_tactic_ids as $method_tactic_id)
            {
                $relate = array(
                    'ques_id'  => $ques_id,
                    'method_tactic_id' => $method_tactic_id
                );

                if (isset($question['parent_id']) && $question['parent_id'] > 0)
                {
                    $relate['is_child'] = 1;
                }

                $inserts[] = $relate;
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_method_tactic', $v);
                }
            }

            // 更新关联知识点
            $inserts = array();
            $know_process = is_array($know_process) ? $know_process : array();
            foreach ($knowledge_ids as $knowledge_id)
            {
                $tmp_know_process = isset($know_process[$knowledge_id]) ?
                                   intval($know_process[$knowledge_id]) : 0;
                $relate = array(
                    'ques_id'      => $ques_id,
                    'knowledge_id' => $knowledge_id,
                    'know_process' => $tmp_know_process,
                );

                 if (isset($question['parent_id']) && $question['parent_id'] > 0)
                     $relate['is_child'] = 1;

                $inserts[] = $relate;
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_knowledge', $v);
                }
            }

            // 更新关联信息提取方式
            if (!empty($extends['group_type_ids']))
            {
                $inserts = array();
                $group_type_ids = $extends['group_type_ids'];
                foreach ($group_type_ids as $group_type_id)
                {
                    $relate = array(
                            'ques_id'      => $ques_id,
                            'group_type_id' => $group_type_id,
                    );

                    if (isset($question['parent_id']) && $question['parent_id'] > 0)
                        $relate['is_child'] = 1;

                    $inserts[] = $relate;
                }
                if ($inserts)
                {
                    foreach ($inserts as $v)
                    {
                        $db->insert('rd_relate_group_type', $v);
                    }
                }
            }

            // 更新关联类型
            foreach ($relate_class as &$relate)
            {
                $relate['ques_id'] = $ques_id;
            }
            if ($relate_class)
            {
                foreach ($relate_class as $v)
                {
                    $db->insert('rd_relate_class', $v);
                }
            }

            //如果是题组子题，更新题组关联的属性
            if (isset($question['parent_id']) && $question['parent_id'])
            {
                self::update_group_question_relates($question['parent_id']);
            }

            // 结束事务
            $result['success'] = $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('add', 'question', $ques_id);
            }
            $result['ques_id'] = $ques_id;

            /** 文件缓存 */
            self::create_question_cache($ques_id);
        }
            if (!$bOk)
            {
                $result['msg'] = '试题添加失败。';
            }
        }
        catch (Exception $e)
        {
            $result['msg'] = '试题添加失败 - ' . $e->getMessage();
        }
        return $result;
    }

    /**
     * 更新试题
     *
     * @param   array    试题基本信息
     * @param   array    选项信息
     * @param   mixed    试题答案。不定项(array)，其他(string)
     * @param   array    试题关联信息
     * @return  array    包含是否成功，错误信息等
     */
    public static function update(&$question, &$options, &$answer, &$extends)
    {
        $result = array(
            'ques_id'  => '',
            'group_id' => '',
            'msg'      => '',
            'success' => FALSE
        );
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $ques_id     = &$question['ques_id'];
            $old_opt_ids = &$extends['old_opt_ids'];

            // 获取老的选项id,各种关联id,难易度
            $old_opt           = array();
            $old_class_ids     = array();
            $old_skill_ids     = array();
            $old_knowledge_ids = array();
            $old_method_tactic_ids = array();

            $tmp_query = $db->fetchAll("SELECT option_id,picture FROM rd_option WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_opt[$arr['option_id']] = $arr;
            }
            $tmp_query = $db->fetchAll("SELECT * FROM rd_relate_class WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_class_ids[$arr['grade_id'].'-'.$arr['class_id']] = $arr['id'];
            }
            $tmp_query = $db->fetchAll("SELECT id, skill_id FROM rd_relate_skill WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_skill_ids[$arr['skill_id']] = $arr['id'];
            }
            $tmp_query = $db->fetchAll("SELECT id, knowledge_id FROM rd_relate_knowledge WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_knowledge_ids[$arr['knowledge_id']] = $arr['id'];
            }
            $tmp_query = $db->fetchAll("SELECT id, method_tactic_id FROM rd_relate_method_tactic WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_method_tactic_ids[$arr['method_tactic_id']] = $arr['id'];
            }
            unset($tmp_query, $arr);

            if (in_array($question['type'],array(1, 2, 7, 14)))
            {
                $new_answer = $question['type'] == 2 ? array() : '';
                $options = $question['type'] == 1 ? array_splice($options, 0, 4) : $options;
                foreach ($options as $k => $opt)
                {
                    $opt['ques_id'] = $ques_id;
                    $opt['picture'] = '';

                    $opt_id = isset($old_opt_ids[$k]) ? $old_opt_ids[$k] : 0;
                    if ($opt_id && isset($old_opt[$opt_id]))
                    {
                        $db->update('rd_option', $opt, "option_id = $opt_id");
                        // 如果更新图片，删除老图片
                        if (!empty($opt['picture'])
                            && $old_opt[$opt_id]['picture']
                            && is_file(_UPLOAD_ROOT_PATH_.$old_opt[$opt_id]['picture']))
                        {
                            @unlink(_UPLOAD_ROOT_PATH_.$old_opt[$opt_id]['picture']);
                        }
                        unset($old_opt[$opt_id]);
                    }
                    else
                    {
                        $db->insert('rd_option', $opt);
                        $opt_id = $db->lastInsertId('rd_option', 'option_id');
                    }

                    if ($question['type'] == 1 
                        || $question['type'] == 7)
                    {
                        if ($answer == $k)
                        {
                            $new_answer = $opt_id;
                        }
                    }
                    else
                    {
                        if (in_array($k, $answer))
                        {
                            $new_answer[] = $opt_id;
                        }
                    }
                }

                // 更新试题
                if ($question['type'] == 2)
                {
                    sort($new_answer, SORT_NUMERIC);
                    $new_answer = implode(',', $new_answer);
                }
                $question['answer'] = $new_answer;
            }
            // 删除多余的老选项
            if ($old_opt)
            {
                foreach ($old_opt as $opt)
                {
                    if ($opt['picture'] && is_file(_UPLOAD_ROOT_PATH_.$opt['picture']))
                    {
                        @unlink(_UPLOAD_ROOT_PATH_.$opt['picture']);
                    }
                }
                $db->delete('rd_option', "option_id IN (" . implode(',', array_keys($old_opt)) . ")");
            }

            !isset($question['group_type']) && $question['group_type'] = '';
            !isset($question['tags']) && $question['tags'] = 0;
            !isset($question['sort']) && $question['sort'] = 0;
            !isset($question['knowledge']) && $question['knowledge'] = '';
            $question['picture'] = '';

            if($question['subject_id'] == 3)
            {
                $word_num = str_word_count(str_replace(array("&nbsp;",'&#39;'),array('',''),
                        strip_tags(htmlspecialchars_decode($question['title']))));
                $question['word_num'] = $word_num;
            }
            else
            {
                $question['word_num'] = 0;
            }

            $db->update('rd_question', $question, "ques_id = $ques_id");

            $skill_ids     = &$extends['skill_ids'];
            $method_tactic_ids = &$extends['method_tactic_ids'];
            $knowledge_ids = &$extends['knowledge_ids'];
            $know_process = &$extends['know_process'];
            $relate_class  = &$extends['relate_class'];

            // 更新关联技能
            $inserts = array();
            /*
            if (is_array($skill_ids) && count($skill_ids)) {
                foreach ($skill_ids as $skill_id)
                {
                    if (isset($old_skill_ids[$skill_id]))
                    {
                        unset($old_skill_ids[$skill_id]);
                    }
                    else
                    {
                        $relate = array(
                            'ques_id' => $ques_id,
                            'skill_id' => $skill_id
                        );

                        $inserts[] = $relate;
                    }
                }
            }*/

            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_skill', $v);
                }
            }
            if ($old_skill_ids)
            {
                $db->delete('rd_relate_skill', 'id IN (' .  implode(',', $old_skill_ids) . ')');
            }

            $parent_id = self::get_question($ques_id, 'parent_id');

            // 更新关联知识点
            $inserts = array();
            $updates = array();
            $know_process = is_array($know_process) ? $know_process : array();
            foreach ($knowledge_ids as $knowledge_id)
            {
                $tmp_know_process = isset($know_process[$knowledge_id])
                                    ? intval($know_process[$knowledge_id]) : 0;
                if (isset($old_knowledge_ids[$knowledge_id]))
                {
                    $updates[] = array(
                                    'id' => $old_knowledge_ids[$knowledge_id],
                                    'know_process' => $tmp_know_process,
                    );
                    unset($old_knowledge_ids[$knowledge_id]);
                }
                else
                {
                    $relate = array(
                        'ques_id'      => $ques_id,
                        'knowledge_id' => $knowledge_id,
                        'know_process' => $tmp_know_process,
                    );
                    if ($parent_id) $relate['is_child'] = 1;
                    $inserts[] = $relate;
                }
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_knowledge', $v);
                }
            }

            if ($updates)
            {
                foreach ($updates as $v)
                {
                    $id = $v['id'];
                    unset($v['id']);
                    $db->update('rd_relate_knowledge', $v, 'id = ' . $id);
                }
            }

            if ($old_knowledge_ids)
            {
                $db->delete('rd_relate_knowledge', 'id IN (' . implode(',', $old_knowledge_ids) . ')');
            }

            // 更新关联信息提取方式
            if (!empty($extends['group_type_ids']))
            {
                $group_type_ids = $extends['group_type_ids'];

                $old_group_type_ids = array();
                $tmp_query = $db->fetchAll("SELECT id, group_type_id FROM rd_relate_group_type WHERE ques_id = $ques_id");
                foreach ($tmp_query as $arr)
                {
                    $old_group_type_ids[$arr['group_type_id']] = $arr['id'];
                }
                unset($tmp_query, $arr);

                $inserts = array();
                foreach ($group_type_ids as $group_type_id)
                {
                    if (isset($old_group_type_ids[$group_type_id]))
                    {
                        unset($old_group_type_ids[$group_type_id]);
                    }
                    else
                    {
                        $relate = array(
                                'ques_id' => $ques_id,
                                'group_type_id' => $group_type_id
                        );
                        if ($parent_id) $relate['is_child'] = 1;
                        $inserts[] = $relate;
                    }
                }

                if ($inserts)
                {
                    foreach ($inserts as $v)
                    {
                        $db->insert('rd_relate_group_type', $v);
                    }
                }

                if ($old_group_type_ids)
                {
                    $db->delete('rd_relate_group_type', 'id IN (' . implode(',', $old_group_type_ids) . ')');
                }
            }

            // 更新关联方法策略
            $inserts = array();
            foreach ($method_tactic_ids as $method_tactic_id)
            {
                if (isset($old_method_tactic_ids[$method_tactic_id]))
                {
                    unset($old_method_tactic_ids[$method_tactic_id]);
                }
                else
                {
                    $relate = array(
                        'ques_id' => $ques_id,
                        'method_tactic_id' => $method_tactic_id
                    );
                    if ($parent_id) $relate['is_child'] = 1;
                    $inserts[] = $relate;
                }
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_method_tactic', $v);
                }
            }
            if ($old_method_tactic_ids)
            {
                $db->delete('rd_relate_method_tactic', 'id IN (' . implode(',', $old_method_tactic_ids) . ')');
            }

            // 更新关联类型
            $inserts = array();
            foreach ($relate_class as $relate)
            {
                $relate['ques_id'] = $ques_id;
                if (isset($old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]))
                {
                    $db->update('rd_relate_class', $relate,
                        'id = ' . $old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]);
                    unset($old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]);
                }
                else
                {
                    $inserts[] = $relate;
                }
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_class', $v);
                }
            }
            if ($old_class_ids)
            {
                $db->delete('rd_relate_class', 'id IN (' . implode(',', $old_class_ids) . ')');
            }

            //如果是题组子题，更新题组关联的属性
            if ($parent_id)
            {
                self::update_group_question_relates($parent_id);
            }

            // 结束事务
            $result['success'] = $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('edit', 'question', $ques_id);
            }
            $result['ques_id'] = $ques_id;

            /** 文件缓存 */
            self::create_question_cache($ques_id);
        }
        if (!$bOk)
        {
            $result['msg'] = '试题编辑失败。';
        }
        return $result;
    }

    /**
     * 更新题组|完形（题干）的关联属性
     * 包括：
     *       knowledge、method_tactic
     */
    public static function update_group_question_relates($parent_id)
    {
        //获取该题组|完形下所有的子题
        $db = Fn::db();
        $child_questions = $db->fetchAll("SELECT ques_id, knowledge, method_tactic, group_type
                FROM rd_question WHERE parent_id={$parent_id}");
        $new_knowledge = '';
        $new_method_tactic = '';
        $new_group_type = '';
        $chlid_ques_ids = array();

        foreach ($child_questions as $row)
        {
            $new_knowledge .= $row['knowledge'];
            $new_method_tactic .= $row['method_tactic'];
            $new_group_type .= $row['group_type'];
            $chlid_ques_ids[] = $row['ques_id'];
        }

        $new_knowledge = explode(',', $new_knowledge);
        $new_knowledge = array_filter($new_knowledge);
        $new_knowledge = array_unique($new_knowledge);

        $new_method_tactic = explode(',', $new_method_tactic);
        $new_method_tactic = array_filter($new_method_tactic);
        $new_method_tactic = array_unique($new_method_tactic);

        $new_group_type = explode(',', $new_group_type);
        $new_group_type = array_filter($new_group_type);
        $new_group_type = array_unique($new_group_type);

        //更新该题组|完形的关联属性
        /*
         * todo:
         *      更新 rd_relate_knowledge
         *      更新 rd_relate_method_tactic
         */
        //获取现有的relate_knowledge
        $result = $db->fetchAll("SELECT id, knowledge_id, know_process
                FROM rd_relate_knowledge WHERE ques_id={$parent_id}");
        $old_knowledges = array();
        foreach ($result as $row)
        {
            $old_knowledges[$row['knowledge_id'] . '_' . $row['know_process']] = $row['id'];
        }

        //获取子题关联的知识点
        $chlid_ques_ids = count($chlid_ques_ids) ? implode(',', $chlid_ques_ids) : '0';
        $child_relates = $db->fetchAll("SELECT knowledge_id, know_process
                FROM rd_relate_knowledge WHERE ques_id IN ({$chlid_ques_ids})");
        $new_knowledge = array();
        foreach ($child_relates as $row)
        {
            $new_knowledge[] = $row['knowledge_id'] . '_' . $row['know_process'];
        }
        $new_knowledge = array_unique($new_knowledge);

        $inserts = array();
        foreach ($new_knowledge as $item)
        {
            @list($knowledge_id, $know_process) = @explode('_', $item);
            if (isset($old_knowledges[$item]))
            {
                unset($old_knowledges[$item]);
            }
            else
            {
                $relate = array(
                    'ques_id' => $parent_id,
                    'knowledge_id' => $knowledge_id,
                    'know_process' => $know_process,
                );
                $inserts[] = $relate;
            }
        }
        if ($inserts)
        {
            foreach ($inserts as $v)
            {
                $db->insert('rd_relate_knowledge', $v);
            }
        }

        if ($old_knowledges)
        {
            $db->delete('rd_relate_knowledge', 'id IN (' . implode(',', $old_knowledges) . ')');
        }

        //获取现有的relate_method_tactic
        $result = $db->fetchAll("SELECT id, method_tactic_id FROM
                  rd_relate_method_tactic WHERE ques_id = {$parent_id}");
        $old_method_tactics = array();
        foreach ($result as $row)
        {
            $old_method_tactics[$row['method_tactic_id']] = $row['id'];
        }

        $inserts = array();
        foreach ($new_method_tactic as $method_tactic_id)
        {
            if (isset($old_method_tactics[$method_tactic_id]))
            {
                unset($old_method_tactics[$method_tactic_id]);
            }
            else
            {
                $relate = array(
                    'ques_id' => $parent_id,
                    'method_tactic_id' => $method_tactic_id
                );
                $inserts[] = $relate;
            }
        }
        if ($inserts)
        {
            foreach ($inserts as $v)
            {
                $db->insert('rd_relate_method_tactic', $v);
            }
        }

        if ($old_method_tactics)
        {
            $db->delete('rd_relate_method_tactic', 'id IN (' . implode(',', $old_method_tactics) . ')');
        }

        //获取现有的relate_group_type
        $result = $db->fetchAll("SELECT id, group_type_id FROM
                  rd_relate_group_type WHERE ques_id={$parent_id}");
        $old_group_types = array();
        foreach ($result as $row)
        {
            $old_group_types[$row['group_type_id']] = $row['id'];
        }

        $inserts = array();
        foreach ($new_group_type as $group_type_id)
        {
            if (isset($old_group_types[$group_type_id]))
            {
                unset($old_group_types[$group_type_id]);
            }
            else
            {
                $relate = array(
                        'ques_id' => $parent_id,
                        'group_type_id' => $group_type_id
                );
                $inserts[] = $relate;
            }
        }
        if ($inserts)
        {
            foreach ($inserts as $v)
            {
                $db->insert('rd_relate_group_type', $v);
            }
        }

        if ($old_group_types)
        {
            $db->delete('rd_relate_group_type', 'id IN (' . implode(',', $old_group_types) . ')');
        }
    }

    /**
     * 添加题组|完形（题干）
     *
     * @param   array    试题基本信息
     * @param   array    试题关联信息
     * @return  array    包含是否成功，错误信息等
     */
    public static function add_group(&$question, &$extends)
    {
        $result = array(
            'msg'      => '',
            'success' => FALSE
        );

        $qtypes = C('q_type');
        $bOk = false;
        $db = Fn::db();
        if ($db->beginTransaction())
        {
            // 关联试题组
            $group_id = $extends['group_id'];
            if ($group_id)
            {
                $group = $db->fetchRow("SELECT * FROM rd_relate_group WHERE group_id = $group_id LIMIT 1");
                if (empty($group))
                {
                    $group_id = 0;
                }
            }
            if (empty($group_id) && $extends['relate_ques_id'])
            {
                $group_id = (int)self::get_question($extends['relate_ques_id'], 'group_id');
                if (empty($group_id))
                {
                    $db->insert('rd_relate_group',
                            array('group_name'=>$extends['relate_ques_id']));
                    $group_id = $db->lastInsertId('rd_relate_group', 'group_id');
                }
            }
            if ($group_id)
            {
                $question['group_id'] = $group_id;
            }

            //$question['type']      = 0;
            $question['is_parent'] = 1;
            $question['addtime']   = time();
            $question['admin_id']  = Fn::sess()->userdata('admin_id');
            !isset($question['group_type']) && $question['group_type'] = '';
            !isset($question['tags']) && $question['tags'] = 0;
            !isset($question['sort']) && $question['sort'] = 0;
            if($question['subject_id'] == 3)
            {
                $word_num = str_word_count(str_replace(array("&nbsp;",'&#39;'),array('',''),
                        strip_tags(htmlspecialchars_decode($question['title']))));
                $question['word_num'] = $word_num;
            }
            else
            {
                $question['word_num'] = 0;
            }

            $db->insert('rd_question', $question);
            $ques_id = $db->lastInsertId('rd_question', 'ques_id');

            $skill_ids     = &$extends['skill_ids'];
            $knowledge_ids = &$extends['knowledge_ids'];
            $relate_class  = &$extends['relate_class'];

            // 更新关联技能
            /*
            $inserts = array();
            if (is_array($skill_ids) && count($skill_ids)) {
                foreach ($skill_ids as $skill_id)
                {
                    $relate = array(
                        'ques_id'  => $ques_id,
                        'skill_id' => $skill_id
                    );
                    $inserts[] = $relate;
                }
            }
            if ($inserts)
                $this->db->insert_batch('relate_skill', $inserts);
            */

            // 更新关联知识点
            /*
            $inserts = array();
            if (is_array($knowledge_ids) && count($knowledge_ids)) {
                foreach ($knowledge_ids as $knowledge_id)
                {
                    $relate = array(
                            'ques_id'      => $ques_id,
                            'knowledge_id' => $knowledge_id,
                    );
                    $inserts[] = $relate;
                }
            }

            if ($inserts)
                $this->db->insert_batch('relate_knowledge', $inserts);
            */

            // 更新关联类型
            foreach ($relate_class as &$relate)
            {
                $relate['ques_id'] = $ques_id;
            }
            if ($relate_class)
            {
                foreach ($relate_class as $v)
                {
                    $db->insert('rd_relate_class', $v);
                }
            }

            // 结束事务
            $result['success'] = $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('add', 'question', $qtypes[$question['type']] . '题干：'.$ques_id);
            }
            $result['ques_id'] = $ques_id;
            /** 文件缓存 */
            self::create_question_cache($ques_id);
        }
        if (!$bOk)
        {
            $result['msg'] = $qtype[$question['type']] . '题干添加失败。';
        }
        return $result;
    }

    /**
     * 更新题组（题干）
     *
     * @param   array    试题基本信息
     * @param   array    试题关联信息
     * @return  array    包含是否成功，错误信息等
     */
    public static function update_group(&$question, &$extends)
    {
        $result = array(
            'msg'      => '',
            'success' => FALSE
        );

        $qtypes = C('q_type');
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $ques_id = $question['ques_id'];

            // 原关联字段
            $old_class_ids = $old_skill_ids = $old_knowledge_ids = array();
            $tmp_query = $db->fetchAll("SELECT * FROM rd_relate_class WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_class_ids[$arr['grade_id'].'-'.$arr['class_id']] = $arr['id'];
            }
            $tmp_query = $db->fetchAll("SELECT id, skill_id FROM rd_relate_skill WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_skill_ids[$arr['skill_id']] = $arr['id'];
            }
            $tmp_query = $db->fetchAll("SELECT id, knowledge_id FROM rd_relate_knowledge WHERE ques_id = $ques_id");
            foreach ($tmp_query as $arr)
            {
                $old_knowledge_ids[$arr['knowledge_id']] = $arr['id'];
            }

            $skill_ids     = &$extends['skill_ids'];
            $knowledge_ids = &$extends['knowledge_ids'];
            $relate_class  = &$extends['relate_class'];

            // 更新关联技能
            $inserts = array();
            /*
            if (is_array($skill_ids) && count($skill_ids))
            {
                foreach ($skill_ids as $skill_id)
                {
                    if (isset($old_skill_ids[$skill_id]))
                    {
                        unset($old_skill_ids[$skill_id]);
                    }
                    else
                    {
                        $relate = array(
                            'ques_id'  => $ques_id,
                            'skill_id' => $skill_id
                        );
                        $inserts[] = $relate;
                    }
                }
            }*/

            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_skill', $v);
                }
            }

            if ($old_skill_ids)
            {
                $db->delete('rd_relate_skill', 'id IN (' . implode(',', $old_skill_ids) . ')');
            }

            // 更新关联知识点
            $inserts = array();
            /*
            if (is_array($knowledge_ids) && count($knowledge_ids)) {
                foreach ($knowledge_ids as $knowledge_id)
                {
                    if (isset($old_knowledge_ids[$knowledge_id]))
                    {
                        unset($old_knowledge_ids[$knowledge_id]);
                    }
                    else
                    {
                        $relate = array(
                            'ques_id'      => $ques_id,
                            'knowledge_id' => $knowledge_id
                        );
                        $inserts[] = $relate;
                    }
                }
            }*/
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_knowledge', $v);
                }
            }
            if ($old_knowledge_ids)
            {
                $db->delete('rd_relate_knowledge', 'id IN (' . implode(',', $old_knowledge_ids) . ')');
            }

            // 更新关联类型
            $inserts = array();
            foreach ($relate_class as $relate)
            {
                $relate['ques_id'] = $ques_id;
                if (isset($old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]))
                {
                    $db->update('rd_relate_class', $relate,
                        'id = ' . $old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]);
                    unset($old_class_ids[$relate['grade_id'].'-'.$relate['class_id']]);
                }
                else
                {
                    $inserts[] = $relate;
                }
            }
            if ($inserts)
            {
                foreach ($inserts as $v)
                {
                    $db->insert('rd_relate_class', $v);
                }
            }
            if ($old_class_ids)
            {
                $db->delete('rd_relate_class', 'id IN (' . implode(',', $old_class_ids) . ')');
            }

            // 更新题干
            //$question['type'] = 0;
            !isset($question['group_type']) && $question['group_type'] = '';
            !isset($question['tags']) && $question['tags'] = 0;
            !isset($question['sort']) && $question['sort'] = 0;
            empty($knowledge_ids) && $question['knowledge'] = '';
            $question['picture'] = '';

            if($question['subject_id'] == 3)
            {
                $word_num = str_word_count(str_replace(array("&nbsp;",'&#39;'),array('',''),
                        strip_tags(htmlspecialchars_decode($question['title']))));
                $question['word_num'] = $word_num;
            }
            else
            {
                $question['word_num'] = 0;
            }
            $db->update('rd_question', $question, "ques_id = $ques_id");

            // 结束事务
            $result['success'] = $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('edit', 'question', C('q_type/' . intval($question['type'])).'：'.$ques_id);
            }
            $result['ques_id'] = $ques_id;

            /** 文件缓存 */
            self::create_question_cache($ques_id);
        }
        if (!$bOk)
        {
            $result['msg'] = C('q_type/' . intval($question['type'])).'编辑失败。';
        }
        return $result;
    }

    /**
     * 读取试题选项列表
     *
     * @param   int    试题id
     * @return  array
     */
    public static function get_options($ques_id)
    {
        return Fn::db()->fetchAll("SELECT * FROM rd_option WHERE ques_id = $ques_id"); 
    }

    /**
     * 获取单个选项信息
     *
     * @author TCG
     * @param int $option_id
     * @return array
     */
    public static function get_option($option_id)
    {
        return Fn::db()->fetchRow("SELECT * FROM rd_option WHERE option_id = $option_id");
    }

    /**
     * 读取题组|完形子题列表
     *
     * @param   int    试题id
     * @return  array
     */
    public static function get_children($ques_id)
    {
        if (empty($ques_id)) return false;

        $list = array();
        $sql = "SELECT ques_id,type,title,picture,answer
                FROM rd_question WHERE parent_id=$ques_id AND is_delete=0
                ORDER BY sort ASC,ques_id ASC";
        $rows = Fn::db()->fetchAll($sql);
        foreach ($rows as $row)
        {
            $row['title'] = str_replace("\r\n", '<br/>', $row['title']);
            //$row['title'] = str_replace(" ", '&nbsp;', $row['title']);
            if (in_array($row['type'],array(3,5,6)))
                $row['answer'] = explode("\n", $row['answer']);
            if ($row['type'] < 3)
                $row['options'] = self::get_options($row['ques_id']);
            $list[] = $row;
        }
        return $list;
    }

    public static function group_question($group_id, $grade_id, $class_id)
    {
        $list = array();
        $sql = <<<EOT
SELECT q.ques_id, q.knowledge, rc.difficulty FROM rd_question q
JOIN relate_class rc ON q.ques_id=rc.ques_id
WHERE q.group_id = {$group_id} AND rc.grade_id = {$grade_id} AND rc.class_id = {$class_id}
EOT;
        $rows = Fn::db()->fetchAll($sql);
        foreach ($query->result_array() as $row)
        {
            $list[$row['ques_id']] = $row;
        }
        return $list;
    }

    // 按条件统计试题数量以及分组关联试题数量
    public static function get_question_nums($where)
    {
        // 试题总数
        $nums  = array('total'=>0, 'in_group' => 0, 'no_group' => 0);
        $sql   = "SELECT COUNT(q.ques_id) nums FROM rd_question q ". $where;
        $row   = Fn::db()->fetchRow($sql);
        $nums['total'] = $row['nums'];


        // 分组数，包含无分组(算一个)
        $sql   = "SELECT COUNT(distinct q.group_id) nums FROM rd_question q ". $where;
        $row   = Fn::db()->fetchRow($sql);
        $nums['in_group'] = $row['nums'];

        // 无分组试题数量
        if ($where)
            $where .= " AND group_id=0";
        else
            $where  = " WHERE group_id=0";
        $sql = "SELECT COUNT(q.ques_id) nums FROM rd_question q ". $where;
        $row = Fn::db()->fetchRow($sql);
        $nums['no_group'] = $row['nums'];

        if ($nums['no_group'])
            $nums['in_group']--;

        $nums['relate_num'] = $nums['in_group'] + $nums['no_group'];
        return $nums;
    }

    // 删除（is_delete=1）
    public static function delete($id)
    {
        $id = intval($id);
        $id && $row = self::get_question($id, 'ques_id,parent_id');
        if (empty($row))
        {
            return -1;
        }

        try
        {
            $db = Fn::db();
            $bOk = false;
            if ($db->beginTransaction())
            {
                $db->update('rd_question', array('is_delete'=>1), "ques_id = $id");
                if($row['parent_id'])
                {
                    $sql = "UPDATE rd_question SET children_num=children_num-1
                            WHERE ques_id='$row[parent_id]' AND children_num>0";
                    $db->exec($sql);
                }
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
                else
                {
                    admin_log('delete', 'question', $id);
                }
            }
            return $bOk;
        }
        catch(Exception $e)
        {
            return false;
        }
    }

    // 移除
    public static function remove($id, $force=false)
    {
        $id = intval($id);
        $id && $row = self::get_question($id, 'type, group_id, picture,
                        parent_id, is_delete, children_num');
        if (empty($row))
        {
            return -1;
        }

        if ( ! $force && empty($row['is_delete']))
        {
            return -2;
        }

        // 如果删除题组，先删除所有子题
        if ($row['type'] == 0)
        {
            $rows = Fn::db()->fetchAll("SELECT ques_id FROM rd_question WHERE parent_id = $id");
            foreach ($rows as $val)
            {
                self::remove($val['ques_id'], true);
            }
        }

        // 获取存在图片的选项（待删除）
        /*
        $query = $this->db->select('picture')->get_where('option',
                array('ques_id'=>$id, 'picture >'=>''));
         */
        $db = Fn::db();
        $options = $db->fetchAll("SELECT picture FROM rd_option WHERE ques_id = $id AND picture <> ''");

        $bOk = false;
        if ($db->beginTransaction())
        {
            // 删除题目
            // 删除选项
            // 删除关联类型
            // 删除关联技能
            // 删除关联知识点
            // 删除关联方法策略
            // 若存在信息提取方式，删除关联信息提取方式
            foreach (array('question','option','relate_class','relate_skill',
                'relate_knowledge', 'relate_method_tactic', 'relate_group_type')
                as $table)
            {
                $db->delete('rd_' . $table, "ques_id = $id");
            }


            // 清理关联分组
            $row['group_id'] && self::clear_relate_group($row['group_id']);

            // 结束事务
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                // 删除试题图片
                if ($row['picture'] && is_file(_UPLOAD_ROOT_PATH_.$row['picture']))
                {
                    @unlink(_UPLOAD_ROOT_PATH_.$row['picture']);
                }

                // 删除选项图片
                foreach ($options as $opt)
                {
                    if ($opt['picture'] && is_file(_UPLOAD_ROOT_PATH_.$opt['picture']))
                    {
                        @unlink(_UPLOAD_ROOT_PATH_.$opt['picture']);
                    }
                }
                admin_log('remove', 'question', $id);
            }
        }
        return $bOk;
    }

    // 还原
    public static function restore($id)
    {
        $id = intval($id);
        $id && $row = self::get_question($id, 'parent_id, is_delete');
        if (empty($row))
        {
            return -1;
        }

        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->update('rd_question', array('is_delete'=>0), "ques_id = $id");
            if($row['parent_id'])
            {
                $sql = "UPDATE rd_question SET children_num=children_num+1
                        WHERE ques_id='$row[parent_id]'";
                $db->exec($sql);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                admin_log('restore', 'question', $id);
            }
        }
        return $bOk;
    }

    // 执行关联操作
    public static function relate($id, $group_id)
    {
        $id = intval($id);
        $group_id = intval($group_id);

        $id && $row = self::get_question($id, 'ques_id,group_id');
        if (empty($row))
        {
            return -1;
        }

        Fn::db()->update('rd_question', array('group_id'=>$group_id), "ques_id = $id");

        // 如果该试题原来属于其他分组
        if ($row['group_id'] && $row['group_id'] != $group_id)
        {
            $sql = "UPDATE rd_question SET group_id = $group_id
                    WHERE group_id = {$row['group_id']}";
                Fn::db()->exec($sql);
                self::clear_relate_group($row['group_id']);
        }

        admin_log('relate', 'question', $id.'，分组:'.$group_id);
        return 1;
    }

    // 执行取消关联
    public static function unrelate($id)
    {
        $id = intval($id);
        $id && $row = self::get_question($id, 'ques_id,group_id');
        if (empty($row))
        {
            return -1;
        }

        if (empty($row['group_id']))
        {
            return -2;
        }

        Fn::db()->update('rd_question', array('group_id'=>0), "ques_id = $id");
        self::clear_relate_group($row['group_id']);
        admin_log('unrelate', 'question', $id);
        return 1;
    }

    // 清理分组（删除没有关联试题的分组）
    public static function clear_relate_group($group_id)
    {
        $group_id = intval($group_id);
        if (empty($group_id)) return;

        $sql = <<<EOT
SELECT ques_id FROM rd_question WHERE group_id = {$group_id} LIMIT 1
EOT;
        $row = Fn::db()->fetchRow($sql);
        if (!$row)
        {
            $sql = <<<EOT
SELECT id FROM rd_interview_question WHERE group_id = {$group_id} LIMIT 1
EOT;
            $row2 = Fn::db()->fetchRow($sql);
            if (!$row2)
            {
                Fn::db()->delete('rd_relate_group', "group_id = $group_id");
            }
        }
    }

    /**
     * 检查某个试题是否被考到过
     */
    public static function question_has_be_tested($ques_id = 0)
    {
        $ques_id = intval($ques_id);
        if (!$ques_id) {
            return false;
        }

        //判断该试题已经被考试过
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_test_result WHERE ques_id = {$ques_id}
EOT;
        $cnt = Fn::db()->fetchOne($sql);
        return $cnt > 0;
    }

    /**
     * 检查某个试题 是否有正在被考到
     */
    public static function question_has_being_tested($ques_id = 0)
    {
        $ques_id = intval($ques_id);
        if (!$ques_id)
        {
            return false;
        }

        $now = time();
        $sql = <<<EOT
SELECT COUNT(ep.exam_pid) AS cnt FROM rd_exam_subject_paper esp
LEFT JOIN rd_exam_place ep ON esp.exam_pid = ep.exam_pid
LEFT JOIN rd_exam_question q ON q.paper_id = esp.paper_id
WHERE q.ques_id = {$ques_id} AND ep.start_time <= {$now} AND ep.end_time >= {$now}
EOT;
        $cnt = Fn::db()->fetchOne($sql);
        return $cnt > 0;
    }

    /**
     * 检查试题 被测试状态:
     *     1、已被考过
     *  2、正在考中
     */
    public static function question_has_test_action($ques_id)
    {
        return false;
        if (self::question_has_be_tested($ques_id) ||
            self::question_has_being_tested($ques_id))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 检查某份试卷是否被考到过
     */
    public static function paper_has_be_tested($paper_id = 0)
    {
        $paper_id = intval($paper_id);
        if (!$paper_id) {
            return false;
        }
        
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_test_result WHERE paper_id = {$paper_id}
EOT;
        $cnt = Fn::db()->fetchOne($sql);
        return $cnt > 0;
    }

    /**
     * 检查某份试卷 是否有正在被考到
     */
    public static function paper_has_being_tested($paper_id = 0)
    {
        $paper_id = intval($paper_id);
        if (!$paper_id)
        {
            return false;
        }

        $now = time();
        $sql = <<<EOT
SELECT COUNT(eps.place_id) AS cnt 
FROM rd_exam_paper p, rd_exam_place_subject eps, rd_exam_place ep
WHERE p.paper_id = {$paper_id} AND p.exam_id = eps.exam_id
AND eps.place_id = ep.place_id AND ep.start_time <= {$now} AND ep.end_time >= {$now}
EOT;
        $cnt = Fn::db()->fetchOne($sql);
        return $cnt > 0;
    }

    /**
     * 检查某张试卷的试题 被测试状态:
     * 1、已被考过
     * 2、正在考中
     */
    public static function paper_question_has_been_tested($paper_id = 0, $ques_id = 0)
    {
        $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_exam_test_result
WHERE paper_id = {$paper_id} AND ques_id = {$ques_id}
EOT;
        $cnt = Fn::db()->fetchOne($sql);
        return $cnt > 0;
    }
    
    /**
     * 检查试卷 被测试状态:
     *  1、已被考过
     *  2、正在考中
     */
    public static function paper_has_test_action($paper_id)
    {
        if (self::paper_has_be_tested($paper_id)
           || self::paper_has_being_tested($paper_id))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 检查当前用户是否有 查看/编辑某个试题的权限
     */
    public static function check_question_power($ques_id = 0, $power_type = 'w', $output = true)
    {
        $ques_id = intval($ques_id);
        if (!$ques_id)
        {
            return true;
        }

        //获取该试题所在的科目
        $question = self::get_question($ques_id, 'admin_id, subject_id, parent_id, type');
        if (!$question)
        {
            if ($output)
            {
                message('该试题不存在');
            }
            else
            {
                return false;
            }
        }

        //超级管理员 或者 有查看所有权限 或者 当前管理员所在学科未 全部学科
        $is_super_user = Fn::sess()->userdata('is_super');
        if ($is_super_user || CpUserModel::is_action_type_all('question', $power_type))
        {
            return true;
        }

        //题组，获取题干的科目
        if ($question['subject_id'] == '0')
        {
            $parent_id = $question['parent_id'];
            $subject_id = self::get_question($parent_id, 'subject_id');
        }
        else
        {
            $subject_id = $question['subject_id'];
        }

        //当前学科
        if (CpUserModel::is_action_type_subject('question', $power_type, $subject_id)) {
            return true;
        }

        //自己创建
        $c_admin_id = Fn::sess()->userdata('admin_id');
        if (CpUserModel::is_action_type_self('question', $power_type))
        {
            if ($question['admin_id'] == $c_admin_id)
            {
                return true;
            }
             else
             {
                if ($output)
                {
                    message('您没权限！');
                }
                else
                {
                    return false;
                }
            }
        }
    }

    /**
     * 检查当前用户是否有 查看/编辑某个学科的权限
     */
    public static function check_subject_power($subject_id = 0, $power_type = 'w', $output = true)
    {
        $subject_id = intval($subject_id);
        if (!$subject_id)
        {
            return true;
        }

        //管理员 或者 有查看所有权限
        $is_super_user = Fn::sess()->userdata('is_super');
        if ($is_super_user
            || CpUserModel::is_action_type_all('question', $power_type))
        {
            return true;
        }

        //当前学科
        $is_action = CpUserModel::is_action_type_subject('question', $power_type, $subject_id);

        if ($output && !$is_action)
        {
            message('您没权限！');
        }
        else
        {
            return $is_action;
        }
    }

    /**
     * 生成试题缓存
     *
     * @author TCG
     * @param int $ques_id 试题id
     * @return boolean 成功返回true，失败返回false
     */
    public static function create_question_cache($ques_id)
    {
        /** 载入缓存功能 */
        $CI = &get_instance();
        $CI->load->driver('cache');

        /** 获取试题数据 */
        $question = self::get_question($ques_id, 'ques_id, type, title, picture, parent_id,subject_id');

        if (!$question)
        {
            return false;
        }

        /** 文本修正 */
        $question['title'] && self::_format_question_content($question['ques_id'], $question['title'], in_array($question['type'], array(3, 9)));

        /** 题组 */
        if (in_array($question['type'],array(0,4,5,6,8)))
        {
            /** 获取子题 */
            $children = self::get_children($question['ques_id']);

            /** 获取子题选项 */
            if (count($children) > 0)
            {
                foreach ($children as $key => $value)
                {
                    $children[$key]['title'] && self::_format_question_content($value['ques_id'], $value['title'], $value['type']==3);
                    $children[$key]['options'] = self::get_options($value['ques_id']);
                }
            }
            $question['children'] = $children;
        }

        /** 获取试题选项数据(选择题) */
        if (in_array($question['type'],array(1,2,7)))
        {
            $question['options'] = self::get_options($ques_id);
        }

        /** 缓存时间 单位second 默认缓存10年 */
        $cache_time = 10 * 365 * 24 * 60 * 60;
        /** 写入文件缓存 */
        if ($CI->cache->file->save($ques_id, $question,$cache_time)) 
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 格式化试题内容
     *
     * @param   integer     试题ID
     * @param   string      试题内容
     * @param   boolean     是否转换填空项
     * @return  void
     */
    private static function _format_question_content(&$ques_id, &$content, $replace_inputs = FALSE)
    {
        $content = str_replace("\n", '<br/>', $content);
        if ($replace_inputs)
        {
            //过滤&nbsp;
            //$content = preg_replace("/（(.*)）/e", 'str_replace("&nbsp;", "", "（\1）")', $content);

            $regex = "/（[\s\d|&nbsp;]*）/";
            $input = "&nbsp;<input{nbsp}type='text'{nbsp}ques_id='{$ques_id}'{nbsp}name='answer_{$ques_id}[]'{nbsp}class='input_answer{nbsp}sub_undo{nbsp}type_3'{nbsp}/>";
            $content = preg_replace($regex, $input, $content);
        }
        //$content = str_replace(" ", '&nbsp;', $content);
        $content = str_replace("{nbsp}", ' ', $content);
        return $content;
    }
}
