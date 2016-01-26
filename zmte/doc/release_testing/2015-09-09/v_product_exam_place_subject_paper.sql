DROP VIEW IF EXISTS v_product_exam_place_subject_paper;
CREATE VIEW v_product_exam_place_subject_paper AS
SELECT 
 p.p_id, p.p_name, p.exam_pid,  p.p_status, p.p_price, p.p_notice, p.p_price_pushcourse, p.p_prefixinfo,  
 p.pc_id, pc.pc_name, pc.pc_memo,
 e.exam_id, e.exam_name, e.exam_isfree, e.grade_id, e.class_id, e.introduce AS exam_intro, e.student_notice AS exam_notice, e.status AS exam_status,
 ep.place_id, ep.place_name, ep.address AS place_address, ep.ip AS place_ip,  ep.start_time AS place_start_time, ep.end_time AS place_end_time, 
 eps.subject_id, esp.id AS esp_id, esp.exam_id AS esp_exam_id, esp.paper_id AS esp_paper_id, epaper.paper_id,
 qc.class_name
FROM rd_product p
LEFT JOIN rd_product_category pc ON p.pc_id = pc.pc_id
LEFT JOIN rd_exam e ON p.exam_pid = e.exam_id
LEFT JOIN rd_question_class qc ON e.class_id = qc.class_id
LEFT JOIN rd_exam_place ep ON p.exam_pid = ep.exam_pid
LEFT JOIN rd_exam_place_subject eps ON p.exam_pid = eps.exam_pid AND ep.place_id = eps.place_id 
LEFT JOIN rd_exam_subject_paper esp ON eps.exam_pid = esp.exam_pid AND eps.exam_id = esp.exam_id AND eps.subject_id = esp.subject_id
LEFT JOIN rd_exam_paper epaper ON esp.paper_id = epaper.paper_id AND esp.exam_id = epaper.exam_id;

ALTER TABLE `rd_product` MODIFY COLUMN `p_status` TINYINT DEFAULT 0 COMMENT '状态,0禁用 1启用';
