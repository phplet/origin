ALTER TABLE `rd_exam_place`
ADD UNIQUE INDEX (`exam_pid`, `place_name`) USING BTREE ;