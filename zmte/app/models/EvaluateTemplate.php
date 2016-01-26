<?php
/**
 * 评估模板管理--数据层 EvaluateTemplateModel
 * @file    EvaluateTemplate.php
 * @author  BJP
 * @final   2015-07-03
 */
class EvaluateTemplateModel
{
    /**
     * 获取模板详情
     */
    public static function get_evaluate_template_info($template_id, 
        $include_module = true)
    {
        if (!$template_id)
        {
            return array();
        }

        $sql = <<<EOT
SELECT * FROM rd_evaluate_template WHERE template_id = {$template_id}
EOT;
        $template = Fn::db()->fetchRow($sql);
        if ($template && $include_module)
        {
            $sql = <<<EOT
SELECT * FROM rd_evaluate_template_module etm
LEFT JOIN rd_evaluate_module em ON etm.template_module_id = em.module_id
WHERE template_id = {$template_id} AND status = 1
ORDER BY template_module_sort ASC
EOT;
            $module = Fn::db()->fetchAll($sql);
            $module_list = array();
            foreach ($module as $item)
            {
                if ($item['parent_moduleid'] == 0)
                {
                    $module_list[] = $item;
                }
            }

            foreach ($module as $item)
            {
                foreach ($module_list as &$val)
                {
                    if ($item['parent_moduleid'] == $val['module_id'])
                    {
                        $val['children'][$item['module_id']] = $item;
                    }
                }
            }

            $template['module'] = $module_list;
        }
        return $template;
    }

    /**
     * 获取模板列表
     * @param   array   $param
     * @param   int     $page
     * @param   int     $perpage
     * @return  void
     */
    public static function get_evaluate_template_list($param = array(), 
        $page = null, $perpage = null)
    {
        $sql = "SELECT * FROM rd_evaluate_template";

        $where = array();
        $bind = array();
        if ($param)
        {
            if (isset($param['template_type']))
            {
                 if (Validate::isInt($param['template_type']))
                 {
                     $where[] = "template_type = " . intval($param['template_type']);
                 }
                 else if (Validate::isJoinedIntStr($param['template_type']))
                 {
                     $where[] = "template_type IN ( " . $param['template_type'] . ")";
                 }
            }

            if (!empty($param['template_name']))
            {
                $where[] = "template_name LIKE ?";
                $bind[] = '%' . $param['template_name'] . '%';
            }

            if (!empty($param['template_subjectid']))
            {
                $template_subjectid = $param['template_subjectid'];
                if (is_array($template_subjectid))
                {
                    $template_subjectid = implode(',', $template_subjectid);
                }

                $where[] = "template_subjectid LIKE '%,{$template_subjectid},%'";
            }
        }

        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY template_id DESC";

        if ($page && $perpage)
        {
            $start = ($page - 1) * $perpage;
            $sql .= " LIMIT $perpage OFFSET $start";
        }

        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 获取模板数量
     * @param   array   $param
     * @return  void
     */
    public static function get_evaluate_template_list_count($param = array())
    {
        $sql = "SELECT COUNT(*) AS cnt FROM rd_evaluate_template";
        
        $where = array();
        $bind = array();

        $where[] = "template_type = " . (int) $param['template_type'];

        if ($param)
        {
            if (!empty($param['template_name']))
            {
                $where[] = "template_name LIKE ?";
                $bind[] = '%' . $param['template_name'] . '%';
            }

            if (!empty($param['template_subjectid']))
            {
                $template_subjectid = $param['template_subjectid'];
                if (is_array($template_subjectid))
                {
                    $template_subjectid = implode(',', $template_subjectid);
                }

                $where[] = "template_name LIKE '%,{$template_subjectid},%'";
            }
        }

        if ($where)
        {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        return Fn::db()->fetchOne($sql, $bind);
    }

    /**
     * 新增模板
     * @param  array $param 模板信息
     * @return boolean
     */
    public static function add_evaluate_template($param)
    {
        $module = array();

        if (isset($param['module']))
        {
            $module = $param['module'];
            unset($param['module']);
        }

        $param['date_create'] = time();
        $param['date_modify'] = time();

        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->insert('rd_evaluate_template', $param);
            $template_id = $db->lastInsertId('rd_evaluate_template', 
                'template_id');

            foreach ($module as &$item)
            {
                $item['template_id'] = $template_id;
            }

            foreach ($module as $v)
            {
                $db->insert('rd_evaluate_template_module', $v);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
            else
            {
                return $template_id;
            }
        }
        return $bOk;
    }

    /**
     * 修改模板
     * @param  array $param       模板信息
     * @param  int   $template_id 模板id
     * @return boolean
     */
    public static function update_evaluate_template($param, $template_id)
    {
        $module = array();
        if (isset($param['module']))
        {
            $module = $param['module'];
            unset($param['module']);
        }

        $param['date_modify'] = time();

        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->update('rd_evaluate_template', $param , 
                "template_id = $template_id");
            $db->delete('rd_evaluate_template_module', 
                "template_id = $template_id");
            foreach ($module as $v)
            {
                $db->insert('rd_evaluate_template_module', $v);
            }
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }

    /**
     * 删除模板
     * @param  int|string $template_id_str 模板id
     * @return boolean
     */
    public static function remove_evaluate_template($template_id_str)
    {
        if (!$template_id_str)
        {
            return false;
        }
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {
            $db->delete('rd_evaluate_template_module',
                "template_id IN ($template_id_str)");
            $db->delete('rd_evaluate_template',
                "template_id IN ($template_id_str)");
            $bOk = $db->commit();
            if (!$bOk)
            {
                $db->rollBack();
            }
        }
        return $bOk;
    }
}
