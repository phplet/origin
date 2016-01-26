DROP VIEW IF EXISTS v_trans_log;

CREATE VIEW v_trans_log AS 
SELECT a.*,  b.p_name, c.pc_name,
CONCAT(e.last_name,e.first_name) AS a_name,
d.start_time,d.end_time,
f.admin_user ,
d.place_name,
g.exam_name, g.exam_isfree 
FROM rd_production_transaction a 
LEFT JOIN rd_product b ON a.p_id = b.p_id 
LEFT JOIN rd_product_category c ON c.pc_id = b.pc_id 
LEFT JOIN rd_exam_place d ON d.place_id = a.place_id 
LEFT JOIN rd_student e ON e.uid = a.uid 
LEFT JOIN rd_admin f ON a.pt_admin = f.admin_id 
LEFT JOIN rd_exam g ON g.exam_id = a.exam_pid;
