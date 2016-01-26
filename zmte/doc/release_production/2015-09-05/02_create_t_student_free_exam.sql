DROP TABLE IF EXISTS t_student_free_exam;

CREATE TABLE `t_student_free_exam` (
`sfe_uid`  int NOT NULL COMMENT '学生uid,外键rd_student.uid' ,
`sfe_exampid`  int NOT NULL COMMENT '学生参加的考试期次,外键rd_exam.exam_id' ,
`sfe_placeid`  int NOT NULL COMMENT '学生所在的考场,外键rd_exam_place.place_id' ,
`sfe_starttime`  int NOT NULL COMMENT '学生自由考试开始时间' ,
`sfe_endtime`  int NOT NULL COMMENT '学生自由考试结束时间' ,
`sfe_report_status`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '学生自由考试报告生成状态，0-正在考试 1-需要生成报告 2-报告已生成',
`sfe_data`     longtext NULL  COMMENT '学生自由考试生成报告数据' ,
UNIQUE INDEX `unique_uid_exampid_placeid` (`sfe_uid`, `sfe_exampid`, `sfe_placeid`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
;