DROP TABLE IF EXISTS t_cteacher_school;

CREATE TABLE `t_cteacher_school` (
`scht_ctid`  int NOT NULL AUTO_INCREMENT COMMENT '教师id' ,
`scht_schid`  int NOT NULL COMMENT '教师所在学校' ,
PRIMARY KEY (`scht_ctid`, `scht_schid`)
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8
COMMENT='学校教师表'
;

DROP VIEW IF EXISTS v_cteacher_school;

CREATE VIEW v_cteacher_school AS
SELECT * FROM t_cteacher_school
LEFT JOIN t_cteacher ON ct_id = scht_ctid
LEFT JOIN t_cteacher_gradeid ON ctg_ctid = scht_ctid
LEFT JOIN t_cteacher_subjectid ON cts_ctid = scht_ctid;