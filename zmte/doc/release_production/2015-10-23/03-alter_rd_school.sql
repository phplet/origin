ALTER TABLE `rd_school`
ADD COLUMN `school_property`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '办学性质（0-公办 1-民办 ）';

ALTER 
VIEW `v_school` AS 
SELECT
sch.*,
r1.region_name AS province_name,
r2.region_name AS city_name,
r3.region_name AS area_name
from (((`rd_school` `sch` left join `rd_region` `r1` on((`sch`.`province` = `r1`.`region_id`))) left join `rd_region` `r2` on((`sch`.`city` = `r2`.`region_id`))) left join `rd_region` `r3` on((`sch`.`area` = `r3`.`region_id`))) ;
