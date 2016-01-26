ALTER TABLE `rd_evaluate_rule`
ADD COLUMN `distribution_proportion`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '分布比例（针对班级、教师报告有用），json格式' AFTER `generate_teacher_report`;