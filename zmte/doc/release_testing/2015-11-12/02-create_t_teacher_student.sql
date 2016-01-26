DROP TABLE IF EXISTS t_teacher_student;
CREATE TABLE `t_teacher_student` (
`tstu_id`  int NOT NULL AUTO_INCREMENT COMMENT '教师学生对应id' ,
`tstu_ctid`  int NOT NULL COMMENT '教师id（外键t_cteacher.ct_id）' ,
`tstu_stuid`  int NOT NULL COMMENT '学生id（外键rd_student.uid）' ,
`tstu_exampid`  int NOT NULL COMMENT '考试期次id（外键rd_exam.exam_id）' ,
`tstu_examid`  int NULL COMMENT '考试id（外键rd_exam.exam_id）' ,
`tstu_subjectid`  int NOT NULL COMMENT '学科id' ,
`tstu_addtime`  int NOT NULL COMMENT '添加时间' ,
PRIMARY KEY (`tstu_id`),
UNIQUE INDEX (`tstu_ctid`, `tstu_stuid`, `tstu_exampid`, `tstu_subjectid`) USING BTREE
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8
COMMENT='学校教师表'
;