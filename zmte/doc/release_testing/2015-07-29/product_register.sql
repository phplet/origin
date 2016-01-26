ALTER TABLE rd_product ADD p_price_pushcourse INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '推送课程时价格';
ALTER TABLE rd_product ADD p_prefixinfo SET('base','score','practice', 'selfwish', 'parentwish') NOT NULL DEFAULT '' COMMENT '产品前置数据';

DROP VIEW IF EXISTS v_production;
CREATE VIEW v_production AS 
select a.p_id, a.p_admin, a.p_name, 
b.pc_id, c.admin_id, b.pc_name, 
a.p_c_time,c.admin_user AS user_name,a.p_status,
d.exam_name, a.exam_pid, a.p_price, a.p_price_pushcourse, a.p_prefixinfo,
a.p_managers,a.p_notice 
from rd_product a 
left join rd_product_category b on a.pc_id = b.pc_id 
left join rd_admin c on a.p_admin = c.admin_id 
left join rd_exam d on a.exam_pid = d.exam_id;

