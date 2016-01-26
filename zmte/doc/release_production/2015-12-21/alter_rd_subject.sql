ALTER TABLE `rd_subject`
ADD COLUMN `relate_subject_id`  varchar(100) NOT NULL COMMENT '学科关联的学科' AFTER `grade_period`;