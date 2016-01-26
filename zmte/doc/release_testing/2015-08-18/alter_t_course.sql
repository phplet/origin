ALTER TABLE t_course ADD cors_lastmodify DATETIME NOT NULL DEFAULT NOW() COMMENT '最后更新时间';
DROP VIEW v_course;
DROP VIEW v_course_campus;
CREATE VIEW `v_course` AS 
SELECT a.*, b.*,c.*,
f.*,e.admin_user AS cors_adduname,
e.realname AS cors_addfullname 
FROM t_course a 
LEFT JOIN t_course_mode b ON a.cors_cmid = b.cm_id
LEFT JOIN t_training_institution c ON a.cors_tiid = c.ti_id
LEFT JOIN t_course_stunumtype f ON a.cors_stunumtype = f.csnt_id
LEFT JOIN rd_admin e ON a.cors_adduid = e.admin_id;

CREATE VIEW v_course_campus AS 
SELECT a.*, aa.*, b.*,  c.*,d.region_name AS cc_provname,e.region_name AS cc_cityname,
f.region_name AS cc_areaname 
FROM t_course_campus a 
LEFT JOIN t_course aa ON a.cc_corsid = aa.cors_id
LEFT JOIN t_course_teachfrom b ON a.cc_ctfid = b.ctf_id
LEFT JOIN t_training_campus c ON a.cc_tcid = c.tc_id 
LEFT JOIN rd_region d ON a.cc_provid = d.region_id
LEFT JOIN rd_region e ON a.cc_cityid = e.region_id
LEFT JOIN rd_region f ON a.cc_areaid = f.region_id;
