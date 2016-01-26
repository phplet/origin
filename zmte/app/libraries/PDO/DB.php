<?php
/**
 * Database access class extended from PDO
 * @author  Bai Jianping
 */

/**
 * @brief   database access class, extended from class PDO:
 * @code
 * class PDO
 * {
 *     bool beginTransaction()
 *     bool commit()
 *     mixed errorCode()
 *     array errorInfo()
 *     int exec(string $statement)
 *     mixed getAttribute(int $attribute)
 *     bool inTransaction()
 *     string lastInsertId()
 *     PDOStatement prepare(string $statement [, array $driver_options = array()])
 *     PDOStatement query (string $statement)
 *     string quote(string $string [, int $parameter_type = PDO::PARAM_STR ])
 *     bool rollBack (void)
 *     bool setAttribute(int $attribute , mixed $value)
 * }
 * @endcode
 */
final class PDO_DB extends PDO
{
    /**
     * @brief   database adapter type (sqlsrv|mysql|pgsql|sqlsrv|oci)
     * @var     string
     */
    private $_adapter = null;
    private $queries  = array();

    /**
     * @brief   Constructor
     * @see     PDO
     * @param   string      $dsn
     * @param   string      $username
     * @param   string      $password
     * @param   array       $driver_options = array()
     * @return  void
     * @code
     * $dsn = 'oci:dbname=mydb';
     * $dsn = 'oci:dbname=//localhost:1521/mydb';
     * $dsn = 'oci:dbname=192.168.10.145/orcl;charset=CL8MSWIN1251';
     * $dsn = 'sqlite:/tmp/mydb.sdb3';
     * $dsn = 'sqlite::memory:';
     * $dsn = 'sqlsrv:Server=BP-SP1\SQLEXPRESS,1521;Database=test';
     * $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=test';
     * $dsn = 'mysql:unix_socket=/tmp/mysql.sock;dbname=test';
     * $dsn = 'pgsql:host=127.0.0.1;port=5432;dbname=test';
     * @endcode
     */
    public function __construct($dsn, $username, $password, $driver_options = array())
    {
        $this->_adapter = strtolower(substr($dsn, 0, strpos($dsn, ':')));
        parent::__construct($dsn, $username, $password, $driver_options);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @brief   destructor
     */
    public function __destruct()//{{{
    {
    }//}}}

    /**
     * @brief   获取数据库类型
     * @return  string  数据库类型字符串
     */
    public function adapter()//{{{
    {
        return $this->_adapter;
    }//}}}

    /**
     * @brief   获取查询语句PDOStatement对象
     * @param   string  $sql
     * @param   mixed   $bind
     * @return  PDOStatement
     * @code
     * $stmt = $db->query("SELECT * FROM t WHERE a = ?", array('234'));
     * $rs = $db->fetchAll($stmt);// or $rs = $stmt->fetchAll();
     * @codeend
     */
    public function query()//    $sql, $bind) //{{{
    {
        if (func_num_args() < 2)
        {
            return parent::query(func_get_arg(0));
        }

        $sql = func_get_arg(0);
        $bind = func_get_arg(1);

        $stmt = $this->prepare($sql);
        $this->queries[] = $sql;
        if (is_array($bind))
        {
            if (empty($bind))
            {
                $stmt->execute();
            }
            else
            {
                $stmt->execute($bind);
            }
        }
        else
        {
            $stmt->execute(array($bind));
        }
        return $stmt;
    }//}}}

    /**
     * @brief   返回SQL查询全部结果
     * @param   string|PDOStatement $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  array                   返回全部结果
     */
    public function fetchAll($sql, $bind = array(), $fetch_mode = PDO::FETCH_ASSOC)//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        return $sql->fetchAll($fetch_mode);
    }//}}}

    /**
     * @brief   返回SQL查询第一行
     * @param   string|PDOStatement  $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  array                   返回第一行关联数组
     */
    public function fetchRow($sql, $bind = array(), $fetch_mode = PDO::FETCH_ASSOC)//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        return $sql->fetch($fetch_mode);
    }//}}}

    /**
     * @brief   返回SQL查询第一列
     * @param   string|PDOStatement  $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  array                   返回第一列值数组
     */
    public function fetchCol($sql, $bind = array())//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        return $sql->fetchAll(PDO::FETCH_COLUMN, 0);
    }//}}}

    /**
     * @brief   返回SQL查询的第一行第一列字段值
     * @param   string|PDOStatement  $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  string                  返回第一行第一列字段值
     */
    public function fetchOne($sql, $bind = array())//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        return $sql->fetchColumn(0);
    }//}}}

    /**
     * @brief   获取记录并将第一个字段值作为键, 将第二个字段值作为值
     * @param   string|PDOStatement  $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  array                   返回paris数组
     */
    public function fetchPairs($sql, $bind = array())//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        $data = array();

        $bString = null;
        while ($row = $sql->fetch(PDO::FETCH_NUM))
        {
            if (is_null($bString))
            {
                $bString = is_string($row[0]);
            }
            if ($bString)
            {
                $data[trim($row[0])] = $row[1];
            }
            else
            {
                $data[$row[0]] = $row[1];
            }
        }
        return $data;
    }//}}}

    /**
     * @brief   获取记录并将第一个字段的值作为键
     * @param   string|PDOStatement  $sql            SQL语句
     * @param   mixed   $bind = array() 绑定参数,$sql为string时有效
     * @return  array                   返回关联数组，键为第一个字段值
     */
    public function fetchAssoc($sql, $bind = array())//{{{
    {
        if ($sql instanceof PDOStatement)
        {
            $sql->execute();
        }
        else
        {
            $sql = $this->query($sql, $bind);
        }
        $data = array();
        $bString = null;
        while ($row = $sql->fetch(PDO::FETCH_ASSOC))
        {
            $tmp = array_values(array_slice($row, 0, 1));
            if (is_null($bString))
            {
                $bString = is_string($tmp[0]);
            }
            if ($bString)
            {
                $data[trim($tmp[0])] = $row;
            }
            else
            {
                $data[$tmp[0]] = $row;
            }
        }
        return $data;
    }//}}}

    /**
     * @brief   将value加引号后返回，如果是array类型，则加引号后用','连接起来
     *          如果是数字，则不加引号
     * @param   mixed   $value
     * @return  string          返回加引号后的字符串
     * @code
     * $db->quote('3223');  // '3323'
     * $db->quote(3223);    // 3323
     * $db->quote(array(31,23,43,34));    // 31,2343,34
     * $db->quote(array('31','23','43','34'));    // '31','2343','34'
     * @endcode
     */
    public function quote($value)//{{{
    {
        if (is_array($value))
        {
            foreach ($value as &$val)
            {
                $val = $this->quote($val);
            }
            return implode(', ', $value);
        }

        if (is_int($value) || is_float($value))
        {
            return $value;
        }
        return parent::quote($value);
    }//}}}

    /**
     * @brief   将value值加引号后替换到text中的?字符中
     * @param   string  $text           原始字符串
     * @param   mixed   $value          要替换值
     * @param   int     $count = null   替换次数，若为null，则全替换
     * @return  string                  返回替换后的字符串
     * @code
     * $db->quoteInto('a = ? AND b = ?', '3223'); // a = '3323' AND b = '3323'
     * $db->quoteInto('a = ? AND b = ?', '3223', 1);// a = '3323 AND b = ?
     * @endcode
     */
    public function quoteInto($text, $value, $count = null)//{{{
    {
        if ($count === null)
        {
            return str_replace('?', $this->quote($value), $text);
        }
        else
        {
            while ($count > 0)
            {
                if (strpos($text, '?') !== false)
                {
                    $text = substr_replace($text, $this->quote($value),
                                strpos($text, '?'), 1);
                }
                --$count;
            }
            return $text;
        }
    }//}}}

    /**
     * @brief   返回分页查询SQL语句
     * @param   string  $sql        SQL语句
     * @param   int     $page       页码
     * @param   int     $rowCount   每页记录数
     * @return  string              返回SQL语句
     */
    public function limitPage($sql, $page, $rowCount)//{{{
    {
        if (!($page > 0))
        {
            $page = 1;
        }
        if (!($rowCount > 0))
        {
            $rowCount = 1;
        }
        return $this->limit($sql, $rowCount, $rowCount * ($page - 1));
    }//}}}

    /**
     * @brief   返回偏移限制记录查询SQL语句
     * @param   string  $sql        SQL语句
     * @param   int     $count      限制记录数
     * @param   int     $offset = 0 偏移量
     * @return  string              返回SQL语句
     */
    public function limit($sql, $count, $offset = 0)//{{{
    {
        if ($count <= 0)
        {
            throw new Exception("LIMIT argument count=$count is not valid");
        }

        if ($offset < 0)
        {
            throw new Exception("LIMIT argument offset=$offset is not valid");
        }

        if (in_array($this->_adapter, array('pgsql', 'mysql', 'sqlite')))
        {
            $sql .= ' LIMIT ' . $count;
            if ($offset > 0)
            {
                $sql .= ' OFFSET ' . $offset;
            }
        }
        else if ($this->_adapter == 'oci')
        {
            $b1 = $offset + 1;
            $b2 = $offset + $count;
            $sql = <<<EOT
SELECT tc2.*
FROM (
    SELECT tc1.*, ROWNUM AS __TCDA_INDEX__
    FROM ( {$sql} ) tc1
) tc2
WHERE tc2.__TCDA_INDEX__ BETWEEN {$b1} AND {$b2}
EOT;
        }
        else if ($this->_adapter == 'sqlsrv')
        {
            if ($offset == 0)
            {
                $sql = preg_replace('/^SELECT\s/i', 'SELECT TOP ' . $count . ' ', $sql);
            }
            else
            {
                $orderby = stristr($sql, 'ORDER BY');

                if (!$orderby)
                {
                    $over = 'ORDER BY (SELECT 0)';
                }
                else
                {
                    $over = preg_replace('/\"[^,]*\".\"([^,]*)\"/i', '"inner_tbl"."$1"', $orderby);
                }

                // Remove ORDER BY clause from $sql
                $sql = preg_replace('/\s+ORDER BY(.*)/', '', $sql);

                // Add ORDER BY clause as an argument for ROW_NUMBER()
                $sql = "SELECT *, ROW_NUMBER() OVER ($over) AS \"__PDO_INDEX__\" FROM ($sql) AS inner_tbl";

                $start = $offset + 1;
                $end = $offset + $count;

                $sql = "WITH outer_tbl AS ($sql) SELECT * FROM outer_tbl WHERE \"__PDO_INDEX__\" BETWEEN $start AND $end";
            }
        }
        return $sql;
    }//}}}

    /**
     * @brief   返回当前数据库版本
     * return   string      返回版本号
     */
    public function getServerVersion()//{{{
    {
        return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
    }//}}}

    /**
     * @brief   获取上次插入的ID
     * @param   string  $tableName  表名
     * @param   string  $primaryKey 主键名
     * @return  string              返回上次插入的ID字符串
     * @code
     * $db->insert('t_mytable', arrary('f1' => 21, 'f2' => 'sdf'));
     * $id = $db->lastInsertId('t_mytable', 'fid');
     * @endcode
     */
    public function lastInsertId($tableName, $primaryKey)//{{{
    {
        if ($this->_adapter == 'pgsql')
        {
            $sequenceName = $tableName . '_' . $primaryKey . '_seq';
            return parent::lastInsertId($sequenceName);
        }
        else
        {
            return parent::lastInsertId();
        }
    }//}}}

    /**
     * @brief   删除表记录
     * @param   string  $table      表名
     * @param   string  $where = '' WHERE条件
     * @param   array   $where_bind WHERE条件绑定
     * @return  int                 返回受影响的记录数
     * @code
     * $db->delete('t_mytable', 'fid = ?', array($fid));
     * @endcode
     */
    public function delete($table, $where = '', $where_bind = array())//{{{
    {
        $sql = 'DELETE FROM ' . $table;
        if ($where)
        {
            $sql .= ' WHERE ' . $where;
        }
        if (empty($where_bind))
        {
            return parent::exec($sql);
        }
        else
        {
            return $this->query($sql, $where_bind)->rowCount();
        }
    }//}}}

    /**
     * @brief   更新表
     * @param   string  $table      表名
     * @param   array   $bind       字段 => 值
     * @param   string  $where = '' WHERE条件
     * @param   array   $where_bind WHERE条件绑定
     * @return  int                 返回受影响的记录条数
     * @code
     * $db->update('t_mytable', array('f1' => '32', 'f2' => 32), 'fid = ?', array($fid));
     * @endcode
     */
    public function update($table, $bind, $where = '', $where_bind = array())//{{{
    {
        $set = array();
        foreach ($bind as $key => $val)
        {
            $set[] = $key . ' = ?';
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set);
        if ($where)
        {
            $sql .= ' WHERE ' . $where;
        }
        return $this->query($sql, array_merge(array_values($bind), $where_bind))->rowCount();
    }//}}}

    /**
     * @brief   插入表
     * @param   string  $table  表名
     * @param   array   $bind   字段 => 值
     * @return  int             返回受影响的记录条数
     * @code
     * $db->insert('t_mytable', array('f1' => 212, 'f2' => '32323'));
     * @endcode
     */
    public function insert($table, $bind)//{{{
    {
        $cols = array();
        $vals = array();
        foreach ($bind as $key => $val)
        {
            $cols[] = $key;
            $vals[] = '?';
        }
        $sql = 'INSERT INTO ' . $table . '(' . implode(',', $cols) . ') '
            . 'VALUES(' . implode(',', $vals) . ')';
        return $this->query($sql, array_values($bind))->rowCount();
    }//}}}
    
    /**
     * @brief   插入表
     * @param   string  $table  表名
     * @param   array   $bind   字段 => 值
     * @return  int             返回受影响的记录条数
     * @code
     * $db->insert('t_mytable', array('f1' => 212, 'f2' => '32323'));
     * @endcode
     */
    public function replace($table, $bind)//{{{
    {
        $cols = array();
        $vals = array();
        $values = array();
        foreach ($bind as $key => $val)
        {
            $cols[] = $key;
            $vals[] = '?';
            $values[] = $val;
        }
        $sql = 'REPLACE INTO ' . $table . '(' . implode(',', $cols) . ') '
                . 'VALUES(' . implode(',', $vals) . ')';
        return $this->query($sql, $values)->rowCount();
    }//}}}

    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    public function update_batch($table, $values, $index, $where = NULL)
    {
        $ids = array();
        $where = ($where != '' AND count($where) >=1) ? implode(" ", $where).' AND ' : '';

        foreach ($values as $key => $val)
        {
            $ids[] = $val[$index];

            foreach (array_keys($val) as $field)
            {
                if ($field != $index)
                {

                    $final[$field][] =  'WHEN '.$index.' = '.$val[$index].' THEN '."'".$val[$field]."'";
                }
            }
        }

        $sql = "UPDATE ".$table." SET ";
        $cases = '';

        foreach ($final as $k => $v)
        {
            $cases .= $k.' = CASE '."\n";
            foreach ($v as $row)
            {
                $cases .= $row."\n";
            }

            $cases .= 'ELSE '.$k.' END, ';
        }

        $sql .= substr($cases, 0, -2);

        $sql .= ' WHERE '.$where.$index.' IN ('.implode(',', $ids).')';

        return $sql;
    }


    function last_query()
    {
        return end($this->queries);
    }

    public function fetchList(/*string*/$table_or_view, 
        /*string*/ $field = NULL,
        array $where = NULL,
        array $bind = NULL,
        /*string*/ $order_by = NULL,
        /*int*/ $page = NULL,
        /*int*/ $perpage = NULL
        )
    {
        if (is_null($field))
        {
            $field = '*';
        }
        $sql = "SELECT {$field} FROM {$table_or_view}";
        if (!empty($where))
        {
            $sql .= ' WHERE ('  . implode(') AND (', $where) . ')';
        }
        if (!empty($order_by))
        {
            $sql .= ' ORDER BY ' . $order_by;
        }
        if ($page)
        {
            if (!$perpage)
            {
                $perpage = C('default_perpage_num');
                if (!$perpage)
                {
                    $perpage = 15;
                }
            }
            $sql = $this->limitPage($sql, $page, $perpage);
        }
        if (is_null($bind))
        {
            $bind = array();
        }
        return $this->fetchAll($sql, $bind);
    }

    public function fetchListCount(/*string*/ $table_or_view, 
        array $where = NULL, array $bind = NULL)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$table_or_view}";
        if (!empty($where))
        {
            $sql .= ' WHERE ('  . implode(') AND (', $where) . ')';
        }
        if (is_null($bind))
        {
            $bind = array();
        }
        return $this->fetchOne($sql, $bind);
    }

    public static function build_where($where_arr, &$sql, &$bind)
    {
        $where_sql = array();
        foreach ($where_arr as $k => $v)
        {
            if (preg_match("/(\s|<|>|!|=|is null|is not null)/i", $k))
            {
                $where_sql[] = "$k ?";
            }
            else
            {
                $where_sql[] = "$k = ?";
            }
            $bind[] = $v;
        }
        if ($where_sql)
        {
            $sql = implode(' AND ', $where_sql);
        }
        else
        {
            $sql = '';
        }
    }
}
