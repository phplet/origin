DROP VIEW IF EXISTS v_complex_level_percent;
CREATE VIEW v_complex_level_percent AS
select a.exam_pid, a.exam_id, d.subject_id, a.uid,
sum(a.full_score) AS full_score,
sum(a.test_score) AS test_score from 
rd_exam_test_result a 
left join rd_exam d on a.exam_id = d.exam_id
left join rd_relate_class b on (a.ques_id = b.ques_id 
		and b.grade_id = d.grade_id and b.class_id = d.class_id)
left join rd_exam_paper c on a.paper_id = c.paper_id  
where b.difficulty >= c.difficulty 
group by a.exam_id, a.uid;