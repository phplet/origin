ALTER TABLE `rd_question`
ADD COLUMN `difficulty_ratio`  float(3,1) NULL DEFAULT 1 COMMENT '只适用英语题型,子题相对题干的难易度比值（取(0,2]的区间值，保留一位小数）';