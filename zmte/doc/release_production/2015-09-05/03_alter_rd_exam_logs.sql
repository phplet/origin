ALTER TABLE `rd_exam_logs`
MODIFY COLUMN `etp_id`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '考试记录' AFTER `place_id`;