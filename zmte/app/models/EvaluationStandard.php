<?php
/**
 * 评分标准管理模型 EvaluationStandardModel
 * @file    EvaluationStandard.php
 * @author  BJP
 * @final   2015-07-03
 */
class EvaluationStandardModel
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
            $sql = "SELECT COUNT(*) AS count FROM rd_evaluation_standard";
            return Fn::db()->fetchRow($sql);
        }
        else
        {
            PDO_DB::build_where($params, $where_sql, $bind);
            $sql = <<<EOT
SELECT COUNT(*) AS count FROM rd_evaluation_standard
EOT;
            if ($where_sql)
            {
                $sql .= ' WHERE ' . $where_sql;
            }
            return Fn::db()->fetchRow($sql, $bind);
        }
    }

    /**
     * 获取一条
     *
     * @param int $id id
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_one($id)
    {
        $id = (int)$id;
        $sql = <<<EOT
SELECT * FROM rd_evaluation_standard WHERE id = {$id}
EOT;
        return Fn::db()->fetchRow($sql);
    }

    /**
     * 获取多条
     *
     * @param array $params 查询条件
     * @param string $fields 需要查询的字段，默认为 *
     * @param int $limit 查询的条数，默认为 15
     * @return array 成功返回结果数组，失败返回空数组
     */
    public static function get_multiterm($params, $fields = '*', $limit = 15)
    {
        $sql = <<<EOT
SELECT {$fields} FROM rd_evaluation_standard
EOT;
        PDO_DB::build_where($params, $where_sql, $bind);
        if ($where_sql)
        {
            $sql .= ' WHERE ' . $where_sql;
        }
        $sql .= ' ORDER BY id DESC';
        if (is_string($limit)) {
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
     * 添加一条
     *
     * @param array $data 需要添加的数据
     * @return mixed 成功返回本条记录id，失败返回false
     */
    public static function add($data)
    {
        $result = Fn::db()->insert('rd_evaluation_standard', $data);
        if ($result)
        {
            return Fn::db()->lastInsertId('rd_evaluation_standard', 'id');
        }
        else
        {
            return false;
        }
    }

    /**
     * 更新一条
     *
     * @param int $id id 需要更新行的ID
     * @param array $data 需要跟新的数据
     * @return mixed 成功返回本条记录id，失败返回false
     */
    public static function update($id, $data)
    {
        if (Fn::db()->update('rd_evaluation_standard', $data, "id = $id"))
        {
            return $id;
        }
        else
        {
            return false;
        }
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
        $data['status'] = '-1';
        $v = Fn::db()->update('rd_evaluation_standard', $data, "id = $id");
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
        if (Fn::db()->delete('rd_evaluation_standard', "id = $id"))
        {
            return $id;
        }
        else
        {
            return false;
        }
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
        $v = Fn::db()->update('rd_evaluation_standard', 
            array('status' => $status), "id = $id");
        return $v > 0;
    }
}
