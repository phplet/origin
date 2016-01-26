CREATE VIEW v_school AS 
SELECT sch.*,r1.region_name province_name,r2.region_name city_name,
    r3.region_name area_name
FROM rd_school sch 
LEFT JOIN rd_region r1 ON sch.province=r1.region_id
LEFT JOIN rd_region r2 ON sch.city=r2.region_id
LEFT JOIN rd_region r3 ON sch.area=r3.region_id
