DROP TABLE IF EXISTS t_exam_relate;
CREATE TABLE `t_exam_relate` (
`er_examid`  int NOT NULL COMMENT '测评考试id' ,
`er_zmoss_examid`  int NOT NULL COMMENT '阅卷系统考试id' ,
`er_exampid`  int NOT NULL COMMENT '父级考试期次' ,
`er_adminid`  int NOT NULL COMMENT '管理员id' ,
`er_flag`  tinyint NOT NULL DEFAULT 0 COMMENT '成绩同步状态（0-未同步 1-进行中  2-同步失败 3-同步成功）' ,
`er_addtime` int NOT NULL COMMENT '添加时间' ,
PRIMARY KEY (`er_examid`),
INDEX (`er_zmoss_examid`) USING BTREE 
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
COMMENT='测评考试与阅卷系统考试对应关系'
;

DROP TABLE IF EXISTS t_exam_relate_question;
CREATE TABLE `t_exam_relate_question` (
`erq_examid`  int NOT NULL COMMENT '测评考试id' ,
`erq_paperid` int NOT NULL COMMENT '测评考试试卷id' ,
`erq_zmoss_examid`  int NOT NULL COMMENT '阅卷考试id' ,
`erq_relate_data`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '试题对应关系，json字符串' ,
`erq_adminid`  int NOT NULL COMMENT '管理员id' ,
`erq_addtime`  int NOT NULL COMMENT '添加时间',
PRIMARY KEY (`erq_examid`),
INDEX (`erq_zmoss_examid`) USING BTREE 
)
;
