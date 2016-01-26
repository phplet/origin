<?php
/**
 * 交易模型 TransactionRecordModel
 * @file    TransactionRecord.php
 * @author  TCG
 * @final   2015-09-16
 */
class TransactionRecordModel
{
    /** 交易记录列表
     * @param   string  $field = NULL       表示查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function transactionRecordList($field = NULL,
        array $cond_param = NULL, $page = NULL, 
        $perpage = NULL)
    {
        $where = array();
        $bind = array();
        
        if ($cond_param)
        {
            if (isset($cond_param['order_by']))
            {
                $order_by = $cond_param['order_by'];
            }
        
            $cond_param = Func::param_copy($cond_param, 'tr_id', 'tr_type',
                'tr_uid', 'tr_pid', 'tr_adminid', 'tr_flag', 's_time', 'e_time');
            
            $fields = array('tr_id', 'tr_type',
                'tr_uid', 'tr_pid', 'tr_adminid', 'tr_flag');
            foreach ($fields as $key)
            {
                if (isset($cond_param[$key])
                    && Validate::isInt($cond_param[$key]))
                {
                    $where[] = "$key = ?";
                    $bind[]  = $cond_param[$key];
                }
            }
            
            if (isset($cond_param['s_time'])
                && Validate::isInt($cond_param['s_time']))
            {
                $where[] = "tr_createtime >= ?";
                $bind[]  = $cond_param['s_time'];
            }
            
            if (isset($cond_param['e_time'])
                && Validate::isInt($cond_param['e_time']))
            {
                $where[] = "tr_createtime <= ?";
                $bind[]  = $cond_param['e_time'];
            }
        }
        else
        {
            $order_by = NULL;
        }
        
        return Fn::db()->fetchList('v_transaction_record', $field, $where,
            $bind, $order_by, $page, $perpage);
    }
    
    /**
     * 查询符合条件的交易数量
     * @param   mixed   $cond_param = null  若为字符串表示查询SQL语句
     *                                      若为数组表示查询字段及值
     * @return  int     符合条件的记录数   
     */
    public static function transactionRecordListCount(array $cond_param = NULL)
    {
        unset($cond_param['order_by']);
        
        $rs = self::transactionRecordList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }
    
    /**
     * 新增交易记录
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['tr_no']     交易订单号
     *                          int     $param['tr_type']   交易类型
     *                          int     $param['tr_uid']    交易人
     *                          int     $param['tr_pid']    产品id
     *                          int     $param['tr_money']  择明通宝余额
     *                          int     $param['tr_cash']   充值金额
     *                          int     $param['tr_trade_amount']   交易金额
     *                          int     $param['tr_adminid'] 后台充值管理员
     *                          string  $param['tr_comment'] 交易说明
     *                          int     $param['tr_flag']   交易状态
     * @return  string  $tr_no
     */
    public static function addTransactionRecord($param)
    {
        $param = Func::param_copy($param, 'tr_type', 
            'tr_uid', 'tr_pid', 'tr_money', 'tr_cash', 
            'tr_trade_amount', 'tr_adminid', 'tr_comment', 'tr_flag');
        
        if (!Validate::isInt($param['tr_type']))
        {
            throw new Exception('交易类型不能为空');
        }
        
        if (!Validate::isInt($param['tr_uid']))
        {
            throw new Exception('交易用户不能为空');
        }
        
        $db = Fn::db();
        
        $param['tr_no'] = self::genTransactionRecordTrNo();
        $param['tr_createtime'] = time();
        $param['tr_finishtime'] = time();
        
        $db->insert('t_transaction_record', $param);
        if ($db->lastInsertId('t_transaction_record', 'tr_id'))
        {
            return $param['tr_no'];
        }
        else
        {
            return false;
        }
    }
    
    /**
     * 修改交易记录
     * @param   int     $tr_id  交易id
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['tr_money']  择明通宝余额
     *                          int     $param['tr_cash']   充值金额
     *                          int     $param['tr_trade_amount']   交易金额
     *                          string  $param['tr_comment'] 交易说明
     *                          int     $param['tr_flag']   交易状态
     * @return  boolean  true|false
     */
    public static function setTransactionRecordByTrId($tr_id, $param)
    {
        if (!Validate::isInt($tr_id) || !$tr_id)
        {
            return false;
        }
        
        $param = Func::param_copy($param, 'tr_money', 'tr_cash',
            'tr_trade_amount', 'tr_comment', 'tr_flag', 'tr_finishtime');
        
        return Fn::db()->update('t_transaction_record', 
                $param, 'tr_id = ?', array($tr_id));
    }
    
    /**
     * 根据交易号修改交易记录
     * @param   string  $tr_id  交易号
     * @param   array   $param  map<string, variant>类型的新记录
     *                          int     $param['tr_money']  择明通宝余额
     *                          int     $param['tr_cash']   充值金额
     *                          int     $param['tr_trade_amount']   交易金额
     *                          string  $param['tr_comment'] 交易说明
     *                          int     $param['tr_flag']   交易状态
     * @return  boolean  true|false
     */
    public static function setTransactionRecordByTrNo($tr_no, $param)
    {
        if (!$tr_no)
        {
            return false;
        }
    
        $param = Func::param_copy($param, 'tr_money', 'tr_cash',
            'tr_trade_amount', 'tr_comment', 'tr_flag', 'tr_finishtime');
    
        return Fn::db()->update('t_transaction_record',
            $param, 'tr_no = ?', array($tr_no));
    }
    
    /**
     * 获取交易订单号
     * @return  string  $tr_no
     */
    public static function genTransactionRecordTrNo()
    {
        $tr_no = Func::buildOrderNo();
    
        $sql = "SELECT COUNT(*) FROM t_transaction_record
                WHERE tr_no = ?";

        if (Fn::db()->fetchOne($sql, array($tr_no)))
        {
            return self::genTransactionRecordTrNo();
        }
        else 
        {
            return $tr_no;
        }
    }
    
    /**
     * 获取交易记录
     * @param   string  $tr_id  交易ID
     * @return  array   map<string, variant>类型数据
     */
    public static function transactionRecordInfoByTrId($tr_id, $item = "*")
    {
        if (!$tr_id)
        {
            return false;
        }
    
        $item = $item ? $item : '*';
    
        $sql = "SELECT $item FROM t_transaction_record
        WHERE tr_id = ?";
    
        return Fn::db()->fetchRow($sql, array($tr_id));
    }
    
    /**
     * 获取交易记录
     * @param   string  $tr_no  交易号
     * @return  array   map<string, variant>类型数据
     */
    public static function transactionRecordInfoByTrNo($tr_no, $item = "*")
    {
        if (!$tr_no)
        {
            return false;
        }
        
        $item = $item ? $item : '*';
        
        $sql = "SELECT $item FROM t_transaction_record
                WHERE tr_no = ?";
    
        return Fn::db()->fetchRow($sql, array($tr_no));
    }
}