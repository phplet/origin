DROP TABLE IF EXISTS t_subject_dimension;
CREATE TABLE `t_subject_dimension` (
`subd_subjectid`  int NOT NULL COMMENT '学科' ,
`subd_value`  varchar(20) NOT NULL COMMENT '学科四维赋值(用,隔开的数字)' ,
`subd_professionid`  varchar(255) NOT NULL COMMENT '学科关联职业（JSON字符串）' ,
PRIMARY KEY (`subd_subjectid`)
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='学科四维表'
;

DROP TABLE IF EXISTS t_profession;
CREATE TABLE `t_profession` (
`profession_id`  int NOT NULL AUTO_INCREMENT COMMENT '职业ID' ,
`profession_name`  varchar(255) NOT NULL COMMENT '职业名称' ,
`profession_emerging`  tinyint UNSIGNED NULL DEFAULT 0 COMMENT '新兴职业（0-否 1-是）' ,
`profession_explain`  varchar(255) NULL COMMENT '职业说明' ,
`profession_flag`  tinyint NOT NULL DEFAULT 1 COMMENT '职业状态（-1-删除 0-禁用 1-启用）' ,
`profession_addtime`  int NOT NULL COMMENT '职业添加时间' ,
`profession_updatetime`  int NOT NULL COMMENT '职业修改时间' ,
PRIMARY KEY (`profession_id`),
UNIQUE INDEX (`profession_name`)
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='职业表'
;

DROP VIEW  IF EXISTS v_profession_related;
DROP TABLE IF EXISTS t_profession_related;
CREATE TABLE `t_profession_related` (
`pr_id`  int NOT NULL AUTO_INCREMENT COMMENT '职业兴趣/能力倾向ID' ,
`pr_subjectid` int  NOT NULL COMMENT '学科ID' ,
`pr_knowledgeid` int NOT NULL COMMENT '学科知识点' ,
`pr_explain`  varchar(255) NULL COMMENT '职业兴趣/能力倾向说明' ,
`pr_professionid`  varchar(255) NOT NULL COMMENT '关联职业（JSON字符串）' ,
`pr_flag`  tinyint NOT NULL DEFAULT 1 COMMENT '状态（-1-删除 0-禁用 1-启用）' ,
`pr_addtime`  int NOT NULL COMMENT '添加时间' ,
`pr_updatetime`  int NOT NULL COMMENT '修改时间' ,
 PRIMARY KEY (`pr_id`),
 UNIQUE INDEX (`pr_knowledgeid`)
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='职业兴趣/能力倾向表'
;

CREATE VIEW v_profession_related AS
SELECT a.*,b.knowledge_name FROM t_profession_related a
LEFT JOIN rd_knowledge b ON b.id = a.pr_knowledgeid;

DROP VIEW  IF EXISTS v_learn_style;
DROP TABLE IF EXISTS t_learn_style;
CREATE TABLE `t_learn_style` (
`learnstyle_id`  int NOT NULL AUTO_INCREMENT COMMENT '学习风格ID' ,
`learnstyle_knowledgeid` int NOT NULL COMMENT '知识点' ,
`learnstyle_explain`  varchar(255) NULL COMMENT '说明' ,
`learnstyle_flag`  tinyint NOT NULL DEFAULT 1 COMMENT '状态（-1-删除 0-禁用 1-启用）' ,
`learnstyle_addtime`  int NOT NULL COMMENT '添加时间' ,
`learnstyle_updatetime`  int NOT NULL COMMENT '修改时间' ,
 PRIMARY KEY (`learnstyle_id`),
 UNIQUE INDEX (`learnstyle_knowledgeid`)
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='学习风格表'
;

CREATE VIEW v_learn_style AS
SELECT a.*,b.knowledge_name FROM t_learn_style a
LEFT JOIN rd_knowledge b ON b.id = a.learnstyle_knowledgeid;

DROP TABLE IF EXISTS t_learn_style_attribute;
CREATE TABLE `t_learn_style_attribute` (
`lsattr_learnstyleid`  int NOT NULL COMMENT '学习风格id' ,
`lsattr_value`  tinyint NOT NULL COMMENT '学习风格属性id(1-正向 2-负向)' ,
`lsattr_name`  varchar(255) NOT NULL COMMENT '学习风格' ,
`lsattr_define`  varchar(255) NULL COMMENT '学习风格定义' ,
`lsattr_advice`  varchar(255) NULL COMMENT '学习风格建议' ,
`lsattr_addtime`  int NOT NULL COMMENT '添加时间' ,
`lsattr_updatetime`  int NOT NULL COMMENT '修改时间' ,
PRIMARY KEY (`lsattr_learnstyleid`, `lsattr_value`),
UNIQUE INDEX (`lsattr_name`)
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='学习风格属性表'
;