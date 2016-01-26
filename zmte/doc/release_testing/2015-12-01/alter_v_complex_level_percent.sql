DROP VIEW IF EXISTS v_complex_level_percent;
CREATE VIEW v_complex_level_percent AS
SELECT
	`a`.`exam_pid` AS `exam_pid`,
	`a`.`exam_id` AS `exam_id`,
	`d`.`subject_id` AS `subject_id`,
	`a`.`uid` AS `uid`,
	`a`.`ques_id` AS `ques_id`,
	sum(`a`.`full_score`) AS `full_score`,
	sum(`a`.`test_score`) AS `test_score`
FROM
	(
		(
			(
				`rd_exam_test_result` `a`
				LEFT JOIN `rd_exam` `d` ON (
					(
						`a`.`exam_id` = `d`.`exam_id`
					)
				)
			)
			LEFT JOIN `rd_relate_class` `b` ON (
				(
					(
						`a`.`ques_id` = `b`.`ques_id`
					)
					AND (
						`b`.`grade_id` = `d`.`grade_id`
					)
					AND (
						`b`.`class_id` = `d`.`class_id`
					)
				)
			)
		)
		LEFT JOIN `rd_exam_paper` `c` ON (
			(
				`a`.`paper_id` = `c`.`paper_id`
			)
		)
	)
WHERE
	(
		`b`.`difficulty` >= `c`.`difficulty`
	)
GROUP BY
	`a`.`exam_id`,
	`a`.`uid`,
	`a`.`ques_id`;