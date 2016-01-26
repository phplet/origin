ALTER TABLE `rd_evaluate_rule`
ADD COLUMN `generate_teacher_report`  tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否生成教师报告（0-不生成 1-生成）' AFTER `generate_class_report`;