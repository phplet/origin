ALTER TABLE `rd_evaluate_rule`
ADD COLUMN `contrast_exam_pid`  int NULL DEFAULT 0 COMMENT '对比考试期次' AFTER `exam_pid`;