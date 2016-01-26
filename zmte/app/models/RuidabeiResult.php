<?php
/**
 * 睿达杯结果模型 RuidabeiResultModel
 * @file    RuidabeiResult.php
 * @author  BJP
 * @final   2015-06-22
 */
class RuidabeiResultModel
{
    /**
     * 获取一条
     *
     * @param array $param 查询条件
     * @return array 数据结果集
     */
    public static function get_one($param)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $sql = "SELECT * FROM rd_ruidabei_result";
        if ($where_sql)
        {
            $sql .= " WHERE " . $where_sql;
        }
        return Fn::db()->fetchRow($sql, $bind);
    }

    /**
     * 添加一条     
     *
     * @param array $data 需要添加的数据
     * @return boolean 成功返回true 失败返回false
     */
    public static function add($data)
    {
        $v = Fn::db()->insert('rd_ruidabei_result', $data);
        return $v > 0;
     }

    /**
     * 更新
     *
     * @param array $param 查询条件
     * @param array $data 需要更新的数据
     * @return boolean 成功返回true 失败返回false
     */
    public static function update($param, $data)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $v = Fn::db()->update('rd_ruidabei_result', $data, $where_sql, $bind);
        return $v > 0;
    }

    /**
     * 获取平均值
     *
     * @param array $param 查询条件
     * @param string $field 查询字段
     * @return array 数据结果集
     */
    public static function get_average($param, $field)
    {
        PDO_DB::build_where($param, $where_sql, $bind);
        $sql = <<<EOT
SELECT AVG({$field}) AS {$field} FROM rd_ruidabei_result
EOT;
        if ($where_sql)
        {
            $sql .= " WHERE " . $where_sql;
        }
        return Fn::db()->fetchRow($sql, $bind);
    }
}
