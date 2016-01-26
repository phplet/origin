ALTER TABLE `rd_evaluate_rule`
ADD COLUMN `generate_subject_report`  tinyint(3) NOT NULL DEFAULT 1 COMMENT '是否生成学科报告' AFTER `contrast_exam_pid`,
ADD COLUMN `generate_transcript`  tinyint(3) NOT NULL DEFAULT 0 COMMENT '是否生成学生成绩单' AFTER `generate_subject_report`;