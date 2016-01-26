<?php
class DbmanageModel
{
    /**
     * 开始备份
     * @param string $dbName
     * @param string $dataDir
     * @param string $tableNames
     */
    public static function backupTables($dbName, $dataDir, $tableNames, $is_export_student = false)
    { 
        $tables = self::delarray($dbName, $tableNames, $is_export_student);
        $sqls = '';
        
        foreach ($tables as $type => $tablenames) 
        {
            if (!$tablenames) 
            { // 表不存在时
                continue;
            }
            
            foreach ($tablenames as $tablename)
            {
                if ($type == 'view')
                {
                    // 如果存在表，就先删除
                    $sqls .= "DROP VIEW IF EXISTS $tablename;\n";
                    // 读取表结构
                    $rs = mysql_query("SHOW CREATE VIEW $tablename");
                    $row = mysql_fetch_row($rs);
                    // 获得表结构组成SQL
                    $sqls .= $row['1'] . ";\n\n";
                    unset($rs);
                    unset($row);
                
                    continue;
                }
                
                // ************************以下是形成SQL的前半部分**************
                // 如果存在表，就先删除
                $sqls .= "DROP TABLE IF EXISTS $tablename;\n";
                
                // 读取表结构
                $rs = mysql_query("SHOW CREATE TABLE $tablename");
                $row = mysql_fetch_row($rs);
                
                // 获得表结构组成SQL
                $sqls .= $row['1'] . ";\n\n";
                unset($rs);
                unset($row);
                
                // ************************以下是形成SQL的后半部分**************
                // 查寻出表中的所有数据
                $rs = mysql_query("select * from $tablename");
                
                // 表的字段个数
                $field = mysql_num_fields($rs);
                while ($rows = mysql_fetch_row($rs))
                {
                    $comma = ''; // 逗号
                    $sqls .= "INSERT INTO `$tablename` VALUES(";
                    for ($i = 0; $i < $field; $i ++)
                    {
                        $sqls .= $comma . "'" . $rows[$i] . "'";
                        $comma = ',';
                    }
                
                    $sqls .= ");\n";
                }
                
                $sqls .= "\n\n";
            }
        }
        
        $sql_file = date("YmdHis") . '.sql';
        
        // 写入文件
        
        if (file_put_contents($dataDir . $sql_file, $sqls))
        {
            return $sql_file;
        }
        
        return false;
    }

    private static function delarray($dbName, $array, $is_export_student)
    {   
        // 处理传入进来的数组
        $tableList = array();
        foreach ($array as $tables) 
        {
            if ($tables == '*') 
            { // 所有的表(获得表名时不能按常规方式来组成一个数组)
                $sql = "SELECT table_name, table_type 
                        FROM information_schema.tables 
                        WHERE table_schema = '$dbName'";
                $stmt = Fn::db()->query($sql);
                while ($item = $stmt->fetch(PDO_DB::FETCH_ASSOC)) 
                {
                    if (strtolower($item['table_type']) == 'view')
                    {
                        $tableList['view'][] = $item['table_name'];
                    }
                    else 
                    {
                        if (!$is_export_student
                            && (is_int(strpos($item['table_name'], 'student'))
                                || is_int(strpos($item['table_name'], 'summary')))
                            )
                        {
                            continue;
                        }
                        
                        $tableList['table'][] = $item['table_name'];
                    }
                }
            } 
            else
            {
                $tableList['table'] = $array;
                break;
            }
        }
        
        return $tableList;
    }
}