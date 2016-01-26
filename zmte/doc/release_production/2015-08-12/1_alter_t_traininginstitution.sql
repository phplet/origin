DROP VIEW IF EXISTS v_training_institution;

ALTER TABLE t_training_institution ADD ti_cooperation_addinc INT NOT NULL DEFAULT 0 COMMENT '合作度自动增加值';

CREATE VIEW v_training_institution AS
SELECT a.*, b.*, c.*, d.admin_user AS ti_adduname, 
    d.realname AS ti_addfullname, cc.region_name AS ti_provname, 
    dd.region_name AS ti_cityname, ee.region_name AS ti_areaname
FROM t_training_institution a
LEFT JOIN t_training_institution_type b ON a.ti_typeid = b.tit_id
LEFT JOIN t_training_institution_pritype c ON a.ti_priid = c.tipt_id
LEFT JOIN rd_admin d ON a.ti_adduid = d.admin_id
LEFT JOIN rd_region cc ON a.ti_provid = cc.region_id
LEFT JOIN rd_region dd ON a.ti_cityid = dd.region_id
LEFT JOIN rd_region ee ON a.ti_areaid = ee.region_id;

