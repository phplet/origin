ALTER TABLE `rd_summary_student_group_type`
ADD COLUMN `total_score`  decimal(6,2) NOT NULL DEFAULT 0 AFTER `is_parent`;

ALTER TABLE `rd_summary_student_method_tactic`
ADD COLUMN `total_score`  decimal(6,2) NOT NULL DEFAULT 0 AFTER `method_tactic_id`;

