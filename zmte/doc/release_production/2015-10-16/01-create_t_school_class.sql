DROP TABLE IF EXISTS t_school_class;
CREATE TABLE `t_school_class` (
`schcls_id`  int NOT NULL AUTO_INCREMENT COMMENT '班级id' ,
`schcls_schid`  int NOT NULL COMMENT '班级所在学校(外键rd_school.school_id)' ,
`schcls_name`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '班级名称' ,
`schcls_ctime`  int NOT NULL COMMENT '班级添加时间' ,
`schcls_utime`  int NOT NULL COMMENT '班级修改时间' ,
PRIMARY KEY (`schcls_id`),
UNIQUE INDEX `unique_schid_classname` (`schcls_schid`, `schcls_name`) USING BTREE ,
INDEX `index_schcls_id` (`schcls_schid`) USING BTREE ,
INDEX `index_schcls_name` (`schcls_name`) USING BTREE 
)
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
;

CREATE VIEW v_school_class AS
SELECT * FROM t_school_class sc
LEFT JOIN rd_school sch ON sc.schcls_schid = sch.school_id;
