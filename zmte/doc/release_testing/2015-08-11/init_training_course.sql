TRUNCATE TABLE t_course_mode;
TRUNCATE TABLE t_course_teachfrom;
TRUNCATE TABLE t_training_institution_type;
TRUNCATE TABLE t_training_institution_pritype;

INSERT INTO t_course_mode VALUES(1, '一对一授课');
INSERT INTO t_course_mode VALUES(2, '班级授课');

INSERT INTO t_course_teachfrom VALUES(1, '机构全职教师');
INSERT INTO t_course_teachfrom VALUES(2, '高校兼职教师');

INSERT INTO t_training_institution_type VALUES(1, '教育培训');
INSERT INTO t_training_institution_type VALUES(2, '全日制学校');

INSERT INTO t_training_institution_pritype VALUES(-1, '低');
INSERT INTO t_training_institution_pritype VALUES(0, '一般');
INSERT INTO t_training_institution_pritype VALUES(1, '高');
