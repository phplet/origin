ALTER TABLE `rd_summary_student_knowledge`
ADD COLUMN `total_score`  decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT '当前试卷该知识点总分' AFTER `know_process_ques_id`;