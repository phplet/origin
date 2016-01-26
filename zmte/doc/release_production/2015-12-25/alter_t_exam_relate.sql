ALTER TABLE `t_exam_relate`
DROP PRIMARY KEY,
ADD UNIQUE INDEX `er_examid` (`er_examid`, `er_zmoss_examid`, `er_exampid`) USING BTREE ;

ALTER TABLE `t_exam_relate_question`
DROP PRIMARY KEY,
DROP INDEX `erq_zmoss_examid` ,
ADD UNIQUE INDEX (`erq_examid`, `erq_paperid`, `erq_zmoss_examid`) USING BTREE ;