ALTER TABLE `rd_summary_region_difficulty`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `paper_id`, `q_type`, `region_id`, `is_school`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_group_type`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `paper_id`, `region_id`, `is_school`, `group_type_id`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_knowledge`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `paper_id`, `region_id`, `is_school`, `knowledge_id`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_method_tactic`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `paper_id`, `region_id`, `is_school`, `method_tactic_id`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_question`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `ques_id`, `region_id`, `is_school`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_student_rank`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `region_id`, `is_school`, `uid`, `is_class`) USING BTREE ;

ALTER TABLE `rd_summary_region_subject`
ADD COLUMN `is_class`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否为班级（0：否，1：是）' AFTER `is_school`,
DROP INDEX `idx_unique` ,
ADD UNIQUE INDEX `idx_unique` (`exam_id`, `region_id`, `is_school`, `is_class`) USING BTREE ;