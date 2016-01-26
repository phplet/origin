ALTER TABLE `rd_exam`
ADD COLUMN `exam_isfree`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否是自由开考,例 0-固定时间 1-随时开考' AFTER `exam_pid`;