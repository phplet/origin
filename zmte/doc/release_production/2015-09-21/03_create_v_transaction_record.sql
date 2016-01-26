DROP VIEW IF EXISTS v_transaction_record;
CREATE VIEW v_transaction_record
AS
select tr.*, p.*, pc.pc_name, a.admin_user 
from t_transaction_record tr
left join rd_product p on p.p_id = tr.tr_pid
left join rd_product_category pc on p.pc_id = pc.pc_id 
left join rd_admin a on a.admin_id = tr.tr_adminid;
