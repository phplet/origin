DROP VIEW IF EXISTS v_student;
CREATE VIEW v_student AS
SELECT
	`s`.`uid` AS `uid`,
	`s`.`email` AS `email`,
	CONCAT(`s`.`last_name`,`s`.`first_name`) AS fullname,
	`s`.`first_name` AS `first_name`,
	`s`.`last_name` AS `last_name`,
	`s`.`idcard` AS `idcard`,
	`s`.`exam_ticket` AS `exam_ticket`,
	`s`.`password` AS `password`,
	`s`.`grade_id` AS `grade_id`,
	`s`.`sex` AS `sex`,
	`s`.`birthday` AS `birthday`,
	`s`.`picture` AS `picture`,
	`s`.`province` AS `province`,
	`s`.`city` AS `city`,
	`s`.`area` AS `area`,
	`s`.`school_id` AS `school_id`,
	`s`.`address` AS `address`,
	`s`.`zipcode` AS `zipcode`,
	`s`.`mobile` AS `mobile`,
	`s`.`source_from` AS `source_from`,
	`s`.`is_check` AS `is_check`,
	`s`.`last_login` AS `last_login`,
	`s`.`last_ip` AS `last_ip`,
	`s`.`email_validate` AS `email_validate`,
	`s`.`status` AS `status`,
	`s`.`is_delete` AS `is_delete`,
	`s`.`addtime` AS `addtime`,
	`s`.`account` AS `account`,
	`s`.`account_status` AS `account_status`,
	`sch`.`school_name` AS `school_name`
FROM
	(
		`rd_student` `s`
		LEFT JOIN `rd_school` `sch` ON (
			(
				`s`.`school_id` = `sch`.`school_id`
			)
		)
	)
ORDER BY
	`s`.`addtime` DESC;