<?php
/**
 * 评分项管理模型 EvaluationOptionModel
 * @file    EvaluationOptoin.php
 * @author  BJP
 * @final   2015-06-23
 */
class EvaluationOptionModel
{
    /**
     * 统计记录总数
     *
     * @param array $params 查询条件
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_count($params = null)
    {
        if (is_null($params))
        {
            $sql = "SELECT COUNT(*) as count FROM rd_evaluation_option";
            return Fn::db()->fetchRow($sql);
        }
        else
        {
            PDO_DB::build_where($params, $where_sql, $bind);
            $sql = "SELECT COUNT(*) as count FROM rd_evaluation_option";
            if ($where_sql)
            {
                $sql .= " WHERE " . $where_sql;
            }
            return Fn::db()->fetchRow($sql, $bind);
        }
    }

    /**
     * 获取一条评分项
     *
     * @param int $id id
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_one($id, $fields = "*")
    {
        $id = (int)$id;
        $sql = "SELECT {$fields} FROM rd_evaluation_option WHERE id = {$id}";
        $row = Fn::db()->fetchRow($sql);
        return $row;
    }

    /**
     * 获取多条评分项
     *
     * @param array $params 查询条件
     * @param string $fields 需要查询的字段，默认为 *
     * @param int $limit 查询的条数，默认为 15
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_options($params, $fields = '*', $limit = 15)
    {
        $sql = <<<EOT
SELECT {$fields} FROM rd_evaluation_option
EOT;
        PDO_DB::build_where($params, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= ' WHERE ' . $where_sql;
        }
        if (is_string($limit))
        {
            $args = explode(',', $limit);
            $sql .= ' LIMIT ' . $args[1] . ' OFFSET ' . $args[0];
        }
        else
        {
            $sql .= ' LIMIT ' . $limit;
        }
        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 获取所有评分项
     *
     * @param array $params 查询条件
     * @param string $fields 需要查询的字段，默认为 *
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_options_all($params, $fields = '*')
    {
        $sql = <<<EOT
SELECT {$fields} FROM rd_evaluation_option
EOT;
        PDO_DB::build_where($params, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= ' WHERE ' . $where_sql;
        }
        return Fn::db()->fetchAll($sql, $bind);
    }

    /**
     * 添加一条
     *
     * @param array $data 需要添加的数据
     * @return bool     返回成功与否
     */
    public static function add($data)
    {
        $v = Fn::db()->insert('rd_evaluation_option', $data);
        return $v > 0;
    }

    /**
     * 更新一条
     *
     * @param int $id id 需要更新行的ID
     * @param array $data 需要跟新的数据
     * @return bool 返回成功与否
     */
    public static function update($id, $data)
    {
        $v = Fn::db()->update('rd_evaluation_option', $data, "id = $id");
        return $v > 0;
    }

    /**
     * 回收一条
     *
     * @param int $id id
     * @return boolean 成功返回true，失败返回false
     */
    public static function recycle($id)
    {
        $data = array();
        $data['status'] = -1;
        $v = Fn::db()->update('rd_evaluation_option', $data, "id = $id");
        return $v > 0;
    }

    /**
     * 删除一条
     *
     * @param int $id id 需要删除行的ID
     * @return mixed 成功返回本条记录id，失败返回false
     */
    public static function delete($id)
    {
        $v = Fn::db()->delete('rd_evaluation_option', "id = $id");
        return $v > 0;
    }

    /**
     * 设置本条记录状态
     *
     * @param int $id 评分标准ID
     * @param int $status 状态代码 (-1:删除,0:禁用,1:启用)
     * @return boolean 成功返回true 失败返回false
     */
    public static function set_status($id, $status = 0)
    {
        $v = Fn::db()->update('rd_evaluation_option', array('status'=> $status),
            "id = $id");
        return $v > 0;
    }
}
