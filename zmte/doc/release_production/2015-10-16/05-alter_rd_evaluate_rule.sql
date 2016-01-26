ALTER TABLE `rd_evaluate_rule`
ADD COLUMN `generate_class_report`  tinyint UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否生成班级报告（0-不生成 1-生成）' AFTER `contrast_exam_pid`;