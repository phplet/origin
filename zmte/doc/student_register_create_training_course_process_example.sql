-- 1.插入培训机构
insert into t_training_institution(ti_name, ti_typeid, ti_flag, ti_priid, 
ti_provid, ti_cityid, ti_areaid, ti_addr, ti_url, ti_stumax, ti_reputation, 
ti_cooperation, ti_cooperation_addenddate, ti_cooperation_addfreqday,
ti_addtime, ti_adduid, ti_campusnum) 
values('测试机构', 1, 1, 0, 31, 467, 3065, '学院路31号', 'http://www.ttt.com', 5000,
0,  0, NULL, 0, now(), 1, 0);

-- 2.插入课程主表
insert into t_course(cors_cmid, cors_name, cors_flag, cors_tiid, cors_stunumtype, cors_url,
cors_memo, cors_addtime, cors_adduid)
values(1, '数学提高', 1, 1, 1, 'http://ssss.fnc.com', 'memo...', now(), 1);

-- 3.插入课程年级
insert into t_course_gradeid values(1, 12);

-- 4.插入课程考试类型
insert into t_course_classid values(1, 1);

-- 5.插入课程学科
insert into t_course_subjectid values(1, 0);

-- 6.插入课程学院
insert into t_course_campus(cc_corsid, cc_tcid, cc_ctfid, cc_classtime, cc_begindate,
cc_enddate, cc_startanytime, cc_hours, cc_price, cc_provid, cc_cityid, cc_areaid,
cc_addr, cc_ctcperson, cc_ctcphone) values(1, NULL, 1, '每周六晚18:00-20:00',
NULL, NULL, 1, 40, 2000, 31, 467, 3065, '学院路31#', '张老师', '15899122121');

-- 7.插入教师
insert into t_cteacher (ct_name, ct_contact, ct_flag) values('张无忌', '12442323232', 1);

-- 8.插入教师年级
insert into t_cteacher_gradeid values(1, 0);

-- 9.插入教师学科
insert into t_cteacher_subjectid values(1, 2);

-- 10.插入课程教师
insert into t_course_campus_teacher values(1, 1);