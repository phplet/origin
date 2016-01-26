ALTER TABLE `t_transaction_record`
CHANGE COLUMN `tr_case` `tr_cash`  decimal(10,2) NULL DEFAULT NULL COMMENT '充值金额' AFTER `tr_money`;