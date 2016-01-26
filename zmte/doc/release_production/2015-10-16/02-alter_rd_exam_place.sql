ALTER TABLE `rd_exam_place`
ADD COLUMN `place_schclsid`  int NOT NULL DEFAULT 0 COMMENT '考试班级id,外键t_school_class.schcls_id,0表示没有班级属性,若大于0则表示考试的整体为一个班级' AFTER `school_id`,
ADD INDEX `school_id` (`school_id`) USING BTREE ,
ADD INDEX `place_schclsid` (`place_schclsid`) USING BTREE ;
