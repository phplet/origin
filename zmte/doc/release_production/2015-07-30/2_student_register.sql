DROP VIEW IF EXISTS v_student_base_stunumtype;
DROP VIEW IF EXISTS v_student_base_classid;
DROP VIEW  IF EXISTS v_student_base_course;
DROP VIEW  IF EXISTS v_student_base;
DROP TABLE IF EXISTS t_student_base_stunumtype;
DROP TABLE IF EXISTS t_student_base_classid;
DROP TABLE IF EXISTS t_student_base_course;
DROP TABLE IF EXISTS t_student_base;

CREATE TABLE t_student_base
(
    sb_uid INT UNSIGNED NOT NULL
        COMMENT '学生UID,外键rd_student(uid)'
        REFERENCES rd_student(uid),
    sb_addr_provid INT UNSIGNED NOT NULL
        COMMENT '学生地址所在省ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    sb_addr_cityid INT UNSIGNED NOT NULL
        COMMENT '学生地址所在市ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    sb_addr_areaid INT UNSIGNED NOT NULL
        COMMENT '学生地址所在区ID,外键re_region(region_id)'
        REFERENCES rd_region(region_id),
    sb_addr_desc VARCHAR(128) NOT NULL
        COMMENT '学生地址详细信息',
    PRIMARY KEY(sb_uid) 
) COMMENT '学生学习概况表';

CREATE TABLE t_student_base_course
(
    sbc_uid INT UNSIGNED NOT NULL
        COMMENT '学生UID,外键rd_student(uid)'
        REFERENCES rd_student(uid),
    sbc_idx INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '序号,默认为0',
    sbc_tiid INT UNSIGNED DEFAULT NULL
        COMMENT '课外培训机构,外键t_training_institution(ti_id)'
        REFERENCES t_training_institution(ti_id),
    sbc_corsid INT UNSIGNED DEFAULT NULL
        COMMENT '课外培训课程,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    INDEX(sbc_uid),
    INDEX(sbc_idx)
) COMMENT '学生学习概况表课程子表';

CREATE TABLE t_student_base_stunumtype
(
    sbs_uid INT UNSIGNED NOT NULL
        COMMENT '学生UID,外键rd_student(uid)'
        REFERENCES rd_student(uid),
    sbs_stunumtype SMALLINT UNSIGNED NOT NULL
        COMMENT '授课模式,外键t_course_stunumtype(csnt_id)'
        REFERENCES t_course_stunumtype(csnt_id),
    PRIMARY KEY(sbs_uid, sbs_stunumtype)
) COMMENT '学生可接受课程授课模式子表';

CREATE TABLE t_student_base_classid
(
    sbclassid_uid INT UNSIGNED NOT NULL
        COMMENT '学生UID,外键rd_student(uid)'
        REFERENCES rd_student(uid),
    sbclassid_classid INT UNSIGNED NOT NULL
        COMMENT '考试类型ID,外键rd_question_class(class_id)'
        REFERENCES rd_question_class(class_id),
    PRIMARY KEY(sbclassid_uid, sbclassid_classid)
) COMMENT '学生希望辅导难度子表';

CREATE VIEW v_student_base AS
SELECT a.*, b.region_name AS stu_addr_provname, 
    c.region_name AS stu_addr_cityname,
    d.region_name AS stu_addr_areaname
FROM t_student_base a
LEFT JOIN rd_region b ON a.sb_addr_provid = b.region_id
LEFT JOIN rd_region c ON a.sb_addr_cityid = c.region_id
LEFT JOIN rd_region d ON a.sb_addr_areaid = d.region_id;


CREATE VIEW v_student_base_course AS
SELECT a.*, e.ti_id, e.ti_name, e.ti_flag, f.cors_id, f.cors_name, f.cors_flag
FROM t_student_base_course a
LEFT JOIN t_training_institution e ON a.sbc_tiid = e.ti_id
LEFT JOIN t_course f ON a.sbc_corsid = f.cors_id;

CREATE VIEW v_student_base_stunumtype AS
SELECT a.*, b.*
FROM t_student_base_stunumtype a
LEFT JOIN t_course_stunumtype b ON a.sbs_stunumtype = b.csnt_id;

CREATE VIEW v_student_base_classid AS
SELECT a.*, b.* 
FROM t_student_base_classid a 
LEFT JOIN rd_question_class b ON a.sbclassid_classid = b.class_id;
