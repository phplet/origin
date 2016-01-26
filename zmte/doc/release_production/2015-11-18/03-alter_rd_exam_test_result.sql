ALTER TABLE `rd_exam_test_result`
DROP INDEX `i_etpid` ,
ADD UNIQUE INDEX `i_etpid` (`etp_id`, `ques_id`, `sub_ques_id`) USING BTREE ;