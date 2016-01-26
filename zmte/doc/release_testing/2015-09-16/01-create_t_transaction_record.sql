CREATE TABLE `t_transaction_record` (
`tr_id`  int NOT NULL AUTO_INCREMENT COMMENT '交易记录id' ,
`tr_no`  varchar(50) NOT NULL COMMENT '交易订单号' ,
`tr_type`  tinyint NOT NULL COMMENT '交易类型(1-支付宝充值 2-管理员后台充值 3-购买产品)' ,
`tr_uid`  int NOT NULL COMMENT '用户uid' ,
`tr_pid`  int NULL COMMENT '产品id' ,
`tr_money`  int NOT NULL COMMENT '交易后择明通宝余额' ,
`tr_case`  decimal(10,2) NULL COMMENT '充值金额' ,
`tr_trade_amount`  int NOT NULL COMMENT '实际择明通宝交易数额' ,
`tr_adminid`  int NULL COMMENT '后台充值管理员' ,
`tr_flag`  tinyint NOT NULL COMMENT '交易状态（0-未完成 2-已完成）' ,
`tr_createtime`  int NOT NULL COMMENT '交易创建时间' ,
`tr_finishtime`  int NOT NULL COMMENT '交易完成时间' ,
PRIMARY KEY (`tr_id`),
UNIQUE INDEX `unique_tr_no` (`tr_no`) 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='交易记录表'
;