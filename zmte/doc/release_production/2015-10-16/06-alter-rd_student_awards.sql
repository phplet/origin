ALTER TABLE rd_student_awards MODIFY COLUMN grade INT NOT NULL DEFAULT 0 COMMENT '年级，如果大于100则表示年份';
