-- DROP TABLE IF EXISTS rd_course_knowledge;
-- DROP TABLE IF EXISTS rd_course;


DROP TABLE IF EXISTS t_course_push;

DROP VIEW IF EXISTS  v_course_campus_teacher;
DROP TABLE IF EXISTS t_course_campus_teacher;

DROP VIEW IF EXISTS v_course_campus;
DROP TABLE IF EXISTS t_course_campus;

DROP VIEW  IF EXISTS v_cteacher_subjectid;
DROP TABLE IF EXISTS t_cteacher_subjectid;
DROP TABLE IF EXISTS t_cteacher_gradeid;
DROP TABLE IF EXISTS t_cteacher;

DROP VIEW IF EXISTS v_course_knowledge;
DROP TABLE IF EXISTS t_course_knowledge;

DROP VIEW IF EXISTS v_course_classid;
DROP TABLE IF EXISTS t_course_classid;

DROP VIEW IF EXISTS v_course_subjectid;
DROP TABLE IF EXISTS t_course_subjectid;

DROP TABLE IF EXISTS t_course_gradeid;

DROP VIEW IF EXISTS v_course;
DROP TABLE IF EXISTS t_course;

DROP TABLE IF EXISTS t_course_stunumtype;
DROP TABLE IF EXISTS t_course_teachfrom;
DROP TABLE IF EXISTS t_course_mode;

DROP VIEW IF EXISTS v_training_campus;
DROP TABLE IF EXISTS t_training_campus;

DROP VIEW IF EXISTS v_training_institution;
DROP TABLE IF EXISTS t_training_institution;

DROP TABLE IF EXISTS t_training_institution_pritype;
DROP TABLE IF EXISTS t_training_institution_type;

CREATE TABLE t_training_institution_type
(
    tit_id SMALLINT UNSIGNED NOT NULL PRIMARY KEY 
        COMMENT '培训机构类型ID,唯一主键',
    tit_name CHAR(30) NOT NULL UNIQUE
        COMMENT '培训机构名称,唯一'
) COMMENT '培训机构类型表';

CREATE TABLE t_training_institution_pritype
(
    tipt_id SMALLINT NOT NULL PRIMARY KEY
        COMMENT '培训机构优先级ID,主键',
    tipt_name CHAR(30) NOT NULL UNIQUE
        COMMENT '培训机构优先级名称,唯一'
) COMMENT '培训机构优先级表';

-- Modified: ti_cooperation, ti_coorperation_addfreqday, ti_coorperation_addenddate 
CREATE TABLE t_training_institution
(
    ti_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
        COMMENT '培训机构ID,自增唯一主键',
    ti_name CHAR(60) NOT NULL UNIQUE
        COMMENT '培训机构名称,唯一',
    ti_typeid SMALLINT UNSIGNED NOT NULL
        COMMENT '培训机构类型ID,外键t_training_institution_type(tit_id)'
        REFERENCES t_training_institution_type(tit_id),
    ti_flag INT NOT NULL
        COMMENT '状态标志,-1已删 0禁用 1启用 大于1 审核中',
    ti_priid SMALLINT NOT NULL
        COMMENT '培训机构优先级ID,外键t_training_institution_pritype(tipt_id)'
        REFERENCES t_training_institution_pritype(tipt_id),
    ti_provid INT NOT NULL 
        COMMENT '所属省份ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    ti_cityid INT NOT NULL
        COMMENT '所属地级市ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    ti_areaid INT NOT NULL
        COMMENT '所属区县ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    ti_addr VARCHAR(255) DEFAULT NULL
        COMMENT '机构地址, 255长度',
    ti_url VARCHAR(512) DEFAULT NULL
        COMMENT '网页网址, 512长度',
    ti_stumax INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '学员人数/每学年',
    ti_reputation INT NOT NULL DEFAULT 0 
        COMMENT '声誉值,默认为0',
    ti_cooperation INT NOT NULL DEFAULT 0
        COMMENT '合作度,默认为0',
    ti_cooperation_addfreqday SMALLINT NOT NULL DEFAULT 0
        COMMENT '合作度增加频率,0为不自动增加,1/7/30分别表示按天/周/月增加',
    ti_cooperation_addenddate DATE DEFAULT NULL
        COMMENT '合作度增加持续时长截止日期,NULL表示永不停止',
    ti_addtime DATETIME NOT NULL 
        COMMENT '添加时间',
    ti_adduid INT UNSIGNED DEFAULT NULL
        COMMENT '添加人员ID, 若为NULL表示非后台添加,外键rd_admin(admin_id)'
        REFERENCES rd_admin(admin_id),
    ti_campusnum INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '校区数量',
    INDEX (ti_typeid),
    INDEX (ti_flag),
    INDEX (ti_priid),
    INDEX (ti_reputation),
    INDEX (ti_cooperation),
    INDEX (ti_cooperation_addfreqday),
    INDEX (ti_cooperation_addenddate)
) COMMENT '培训机构表';

CREATE VIEW v_training_institution AS
SELECT a.*, b.*, c.*, d.admin_user AS ti_adduname, 
    d.realname AS ti_addfullname, cc.region_name AS ti_provname, 
    dd.region_name AS ti_cityname, ee.region_name AS ti_areaname
FROM t_training_institution a
LEFT JOIN t_training_institution_type b ON a.ti_typeid = b.tit_id
LEFT JOIN t_training_institution_pritype c ON a.ti_priid = c.tipt_id
LEFT JOIN rd_admin d ON a.ti_adduid = d.admin_id
LEFT JOIN rd_region cc ON a.ti_provid = cc.region_id
LEFT JOIN rd_region dd ON a.ti_cityid = dd.region_id
LEFT JOIN rd_region ee ON a.ti_areaid = ee.region_id;

CREATE TABLE t_training_campus
(
    tc_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
        COMMENT '培训校区ID,自增唯一主键',
    tc_name CHAR(60) NOT NULL
        COMMENT '校区名称',
    tc_tiid INT UNSIGNED NOT NULL 
        COMMENT '所属培训机构ID,外键t_training_institution(ti_id)'
        REFERENCES t_training_institution(ti_id),
    tc_provid INT NOT NULL 
        COMMENT '所属省份ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    tc_cityid INT NOT NULL
        COMMENT '所属地级市ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    tc_areaid INT NOT NULL
        COMMENT '所属区县ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    tc_flag INT NOT NULL 
        COMMENT '标志属性,-1已删,0禁用,1启用,大于1审核中',
    tc_ctcaddr VARCHAR(255) NOT NULL
        COMMENT '联系地址',
    tc_ctcperson VARCHAR(60) DEFAULT NULL
        COMMENT '联系人姓名',
    tc_ctcphone VARCHAR(120) NOT NULL
        COMMENT '联系电话(可为多个,以英文逗号隔开)',
    tc_environ SMALLINT UNSIGNED NOT NULL DEFAULT 0 
        COMMENT '环境指数'
        CHECK(tc_environ BETWEEN 0 AND 5),
    tc_addtime DATETIME NOT NULL 
        COMMENT '添加时间',
    tc_adduid INT UNSIGNED DEFAULT NULL
        COMMENT '添加人员ID, 若为NULL表示非后台添加,外键rd_admin(admin_id)'
        REFERENCES rd_admin(admin_id),
    UNIQUE(tc_tiid, tc_cityid, tc_name),
    INDEX (tc_provid),
    INDEX (tc_cityid),
    INDEX (tc_areaid),
    INDEX (tc_environ)
) COMMENT '培训校区表';

CREATE VIEW v_training_campus AS
SELECT a.*, b.ti_name, c.region_name AS tc_provname, 
    d.region_name AS tc_cityname, e.region_name AS tc_areaname,
    f.admin_user AS tc_adduname, f.realname AS tc_addfullname
FROM t_training_campus a
LEFT JOIN t_training_institution b ON a.tc_tiid = b.ti_id
LEFT JOIN rd_region c ON a.tc_provid = c.region_id
LEFT JOIN rd_region d ON a.tc_cityid = d.region_id
LEFT JOIN rd_region e ON a.tc_areaid = e.region_id
LEFT JOIN rd_admin f ON a.tc_adduid = f.admin_id;

CREATE TABLE t_course_mode
(
    cm_id SMALLINT UNSIGNED NOT NULL PRIMARY KEY
        COMMENT '课程授课模式ID,唯一主键',
    cm_name CHAR(30) NOT NULL UNIQUE
        COMMENT '课程授课模式名称,唯一'
) COMMENT '课程授课模式表';

CREATE TABLE t_course_teachfrom
(
    ctf_id SMALLINT UNSIGNED NOT NULL PRIMARY KEY
        COMMENT '课程教师来源ID,唯一主键',
    ctf_name CHAR(30) NOT NULL UNIQUE
        COMMENT '课程教师来源名称,唯一'
) COMMENT '课程教师来源表';

-- New 
CREATE TABLE t_course_stunumtype
(
    csnt_id SMALLINT UNSIGNED NOT NULL PRIMARY KEY
        COMMENT '授课人数类别ID,唯一主键',
    csnt_name CHAR(30) NOT NULL UNIQUE
        COMMENT '授课人数类别名称,唯一',
    csnt_memo VARCHAR(255)
        COMMENT '备注'
) COMMENT '授课人数类别表';

INSERT INTO t_course_stunumtype VALUES(1, '一对一', '');
INSERT INTO t_course_stunumtype VALUES(2, '小班', '小于等于20人');
INSERT INTO t_course_stunumtype VALUES(3, '大班', '多于20人');


-- Modified: add cors_stunumtype
CREATE TABLE t_course
(
    cors_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY 
        COMMENT '课程ID,自增唯一主键',
    cors_cmid SMALLINT UNSIGNED NOT NULL 
        COMMENT '课程模式,外键t_course_mod(cm_id)'
        REFERENCES t_course_mod(cm_id),
    cors_name CHAR(100) NOT NULL
        COMMENT '课程名称',
    cors_flag INT NOT NULL 
        COMMENT '标志状态, -1已删,0禁用,1启用,大于1审核',
    cors_tiid INT UNSIGNED  NOT NULL
        COMMENT '课程来源机构,外键t_training_institution(ti_id)'
        REFERENCES t_training_institution(ti_id),
    cors_stunumtype  SMALLINT UNSIGNED NOT NULL
        COMMENT '授课人数类别,外键t_course_stunumtype(csnt_id)'
        REFERENCES t_course_stunumtype(csnt_id),
    cors_url VARCHAR(512)
        COMMENT '网址', 
    cors_memo TEXT
        COMMENT '课程简介',
    cors_addtime DATETIME NOT NULL 
        COMMENT '添加时间',
    cors_adduid INT UNSIGNED DEFAULT NULL
        COMMENT '添加人员ID, 若为NULL表示非后台添加,外键rd_admin(admin_id)'
        REFERENCES rd_admin(admin_id),
    INDEX (cors_cmid),
    INDEX (cors_name),
    INDEX (cors_flag),
    INDEX (cors_tiid),
    INDEX (cors_stunumtype)
) COMMENT '课程表';

CREATE VIEW v_course AS 
SELECT a.*, b.*, c.*, f.*, 
    e.admin_user AS cors_adduname, e.realname AS cors_addfullname
FROM t_course a
LEFT JOIN t_course_mode b ON a.cors_cmid = b.cm_id
LEFT JOIN t_training_institution c ON a.cors_tiid = c.ti_id
LEFT JOIN t_course_stunumtype f ON a.cors_stunumtype = f.csnt_id
LEFT JOIN rd_admin e ON a.cors_adduid = e.admin_id;

-- New 
CREATE TABLE t_course_gradeid
(
    cg_corsid INT UNSIGNED NOT NULL
        COMMENT '课程ID,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    cg_gradeid SMALLINT NOT NULL
        COMMENT '年级ID,1到12,0表示不限年级',
    PRIMARY KEY (cg_corsid, cg_gradeid)
) COMMENT '课程年级关联表';

-- New
CREATE TABLE t_course_subjectid
(
    cs_corsid INT UNSIGNED NOT NULL
        COMMENT '课程ID,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    cs_subjectid SMALLINT NOT NULL
        COMMENT '学科ID,0表示不限学科'
        REFERENCES t_subject(subject_id),
    PRIMARY KEY (cs_corsid, cs_subjectid)
) COMMENT '课程学科关联表';

-- New
CREATE VIEW v_course_subjectid AS
SELECT a.*, b.* 
FROM t_course_subjectid a 
LEFT JOIN rd_subject b ON a.cs_subjectid = b.subject_id;

CREATE TABLE t_course_classid
(
    cci_corsid INT UNSIGNED NOT NULL
        COMMENT '课程ID,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    cci_classid INT UNSIGNED NOT NULL
        COMMENT '考试类型ID,外键rd_question_class(class_id)'
        REFERENCES rd_question_class(class_id),
    PRIMARY KEY(cci_corsid, cci_classid)
) COMMENT '课程考试类型关联表';

CREATE VIEW v_course_classid AS
SELECT a.*, b.* 
FROM t_course_classid a 
LEFT JOIN rd_question_class b ON a.cci_classid = b.class_id;

CREATE TABLE t_course_knowledge
(
    ck_corsid INT UNSIGNED NOT NULL
        COMMENT '课程ID,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    ck_kid INT UNSIGNED NOT NULL
        COMMENT '知识点ID,外键rd_knowledge(id)'
        REFERENCES rd_knowledge(id),
    ck_knprocid TINYINT UNSIGNED NOT NULL 
        COMMENT '认知过程,1记忆 2理解 3应用'
        CHECK(ck_knprocid IN (1,2,3)),
    PRIMARY KEY (ck_corsid, ck_kid)
) COMMENT '课程知识点关联表';

CREATE VIEW v_course_knowledge AS
SELECT a.*, b.knowledge_name AS ck_kname, b.pid AS ck_kpid
FROM t_course_knowledge a
LEFT JOIN rd_knowledge b ON a.ck_kid = b.id;

-- Modified: cc_tcid NOT NULL to NULL
-- add provid, cityid, areaid, add startanytime
CREATE TABLE t_course_campus
(
    cc_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
        COMMENT '课程关联校区表ID,自增唯一主键',
    cc_corsid INT UNSIGNED NOT NULL
        COMMENT '所属课程ID,外键t_course(cors_id)'
        REFERENCES t_course(cors_id),
    cc_tcid INT UNSIGNED
        COMMENT '校区ID,外键t_training_campus(tc_id),若为NULL表示一对一授课'
        REFERENCES t_training_campus(tc_id),
    cc_ctfid SMALLINT NOT NULL 
        COMMENT '教师来源,t_course_teachfrom(ctf_id)'
        REFERENCES t_course_teachfrom(ctf_id),
    cc_classtime VARCHAR(255) DEFAULT NULL
        COMMENT '课程时间,例如 每周周六周日上课，上课时间18:00到21:00',
    cc_begindate DATE DEFAULT NULL
        COMMENT '课程开始日期',
    cc_enddate DATE DEFAULT NULL
        COMMENT '课程结束日期',
    cc_startanytime SMALLINT NOT NULL DEFAULT 0
        COMMENT '随时开课(一对一授课方有效,即cc_tcid = NULL)',
    cc_hours INT UNSIGNED DEFAULT NULL
        COMMENT '共计课时',
    cc_price DECIMAL(18,2) DEFAULT NULL
        COMMENT '课程收费', 
    cc_provid INT NOT NULL 
        COMMENT '所属省份ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    cc_cityid INT NOT NULL
        COMMENT '所属地级市ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    cc_areaid INT NOT NULL
        COMMENT '所属区县ID,外键rd_region(region_id)'
        REFERENCES rd_region(region_id),
    cc_addr VARCHAR(255) DEFAULT NULL
        COMMENT '上课地址',
    cc_ctcperson VARCHAR(60) DEFAULT NULL
        COMMENT '联系人姓名',
    cc_ctcphone VARCHAR(120) DEFAULT NULL
        COMMENT '联系电话(可为多个,以英文逗号隔开)',
    UNIQUE (cc_corsid, cc_tcid),
    INDEX (cc_tcid),
    INDEX (cc_ctfid),
    INDEX (cc_begindate),
    INDEX (cc_enddate),
    INDEX (cc_hours),
    INDEX (cc_price),
    INDEX (cc_startanytime),
    INDEX (cc_provid),
    INDEX (cc_cityid),
    INDEX (cc_areaid)
) COMMENT '课程关联校区表';

CREATE VIEW v_course_campus AS
SELECT a.*, aa.*, b.*, c.*, 
d.region_name AS tc_provname, 
e.region_name AS tc_cityname, 
f.region_name AS tc_areaname
FROM t_course_campus a 
LEFT JOIN t_course aa ON a.cc_corsid = aa.cors_id
LEFT JOIN t_course_teachfrom b ON a.cc_ctfid = b.ctf_id
LEFT JOIN t_training_campus c ON a.cc_tcid = c.tc_id
LEFT JOIN rd_region d ON a.cc_provid = d.region_id
LEFT JOIN rd_region e ON a.cc_cityid = e.region_id
LEFT JOIN rd_region f ON a.cc_areaid = f.region_id;

-- New
CREATE TABLE t_cteacher
(
    ct_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
        COMMENT '教师ID,自增主键',
    ct_name CHAR(30) NOT NULL
        COMMENT '教师姓名',
    ct_contact VARCHAR(255)
        COMMENT '联系方式',
    ct_flag INT NOT NULL
        COMMENT '状态标志,-1已删 0禁用 1启用 大于1 审核中',
    INDEX (ct_name)
) COMMENT '教师列表';

-- New
CREATE TABLE t_cteacher_gradeid
(
    ctg_ctid INT UNSIGNED NOT NULL
        COMMENT '教师ID,外键t_cteacher(ct_id)'
        REFERENCES t_cteacher(ct_id),
    ctg_gradeid SMALLINT NOT NULL
        COMMENT '年级ID,1到12,0表示不限年级',
    PRIMARY KEY (ctg_ctid, ctg_gradeid)
) COMMENT '教师年级关联表';

-- New
CREATE TABLE t_cteacher_subjectid
(
    cts_ctid INT UNSIGNED NOT NULL
        COMMENT '教师ID,外键t_cteacher(ct_id)'
        REFERENCES t_cteacher(ct_id),
    cts_subjectid SMALLINT NOT NULL
        COMMENT '学科ID,0表示不限学科'
        REFERENCES t_subject(subject_id),
    PRIMARY KEY (cts_ctid, cts_subjectid)
) COMMENT '教师学科关联表';

-- New
CREATE VIEW v_cteacher_subjectid AS
SELECT a.*, b.* 
FROM t_cteacher_subjectid a 
LEFT JOIN rd_subject b ON a.cts_subjectid = b.subject_id;

-- New
CREATE TABLE t_course_campus_teacher
(
    cct_ccid INT UNSIGNED NOT NULL 
        COMMENT '课程校区ID,外键t_course_campus(cc_id)'
        REFERENCES t_course_campus(cc_id),
    cct_ctid INT UNSIGNED NOT NULL
        COMMENT '教师ID,外键t_cteacher(ct_id)'
        REFERENCES t_cteacher(ct_id),
    PRIMARY KEY (cct_ccid, cct_ctid)
) COMMENT '课程校区-教师关联表';

-- New
CREATE VIEW v_course_campus_teacher AS
SELECT a.*, b.* 
FROM t_course_campus_teacher a 
LEFT JOIN t_cteacher b ON a.cct_ctid = b.ct_id;

-- New
CREATE TABLE t_course_push
(
    cp_stuuid INT UNSIGNED NOT NULL 
        COMMENT '学生UID'
        REFERENCES rd_student(uid),
    cp_examid INT UNSIGNED NOT NULL
        COMMENT '考试期次ID'
        REFERENCES rd_exam(exam_id),
    cp_addtime DATETIME NOT NULL
        COMMENT '添加时间',
    PRIMARY KEY (cp_stuuid, cp_examid)
) COMMENT '课程推送表';

CREATE VIEW v_course_push AS 
SELECT a.*, b.exam_id, b.exam_name, b.exam_pid, b.status AS exam_status, 
    b.exam_ticket_maprule, c.* 
FROM t_course_push a 
LEFT JOIN rd_exam b ON a.cp_examid = b.exam_id
LEFT JOIN rd_student c ON a.cp_stuuid = c.uid;
