DROP VIEW IF EXISTS v_summary_region_exam_paper;
CREATE VIEW v_summary_region_exam_paper AS
SELECT
etp.exam_id AS exam_id,
etp.paper_id AS paper_id,
s.school_id AS school_id,
s.province AS province,
s.city AS city,
s.area AS area,
etp.exam_pid AS exam_pid,
ep.place_schclsid AS place_schclsid
from `rd_school` `s` 
left join `rd_student` `stu` on `stu`.`school_id` = `s`.`school_id` 
left join `rd_exam_test_paper` `etp` on `stu`.`uid` = `etp`.`uid` 
left join `rd_exam_place` `ep` on `ep`.`exam_pid` = `etp`.`exam_pid`
where `etp`.`exam_id` > 0 and `etp`.`paper_id` > 0 and stu.school_id > 0
GROUP BY etp.exam_id, etp.paper_id, s.school_id, ep.place_schclsid;