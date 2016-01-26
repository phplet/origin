DROP VIEW IF EXISTS v_course_push;
DROP TABLE IF EXISTS t_course_push;

-- New
CREATE TABLE t_course_push
(
    cp_stuuid INT UNSIGNED NOT NULL 
        COMMENT '学生UID'
        REFERENCES rd_student(uid),
    cp_exampid INT UNSIGNED NOT NULL
        COMMENT '考试期次PID'
        REFERENCES rd_exam(exam_id),
    cp_examplaceid INT UNSIGNED NOT NULL
        COMMENT '考试场次ID'
        REFERENCES rd_exam_place(place_id),
    cp_addtime DATETIME NOT NULL
        COMMENT '添加时间',
    PRIMARY KEY (cp_stuuid, cp_exampid, cp_examplaceid)
) COMMENT '课程推送表';

CREATE VIEW v_course_push AS 
SELECT a.*, b.exam_id, b.exam_name, b.status AS exam_status, b.exam_ticket_maprule, c.*, d.place_id, d.exam_pid, d.place_name, d.place_index, d.school_id AS place_schoolid, d.address AS place_address, d.ip AS place_ip, d.start_time AS place_starttime, d.end_time AS place_endtime, d.exam_time_custom
FROM t_course_push a 
LEFT JOIN rd_exam b ON a.cp_exampid = b.exam_id
LEFT JOIN rd_student c ON a.cp_stuuid = c.uid
LEFT JOIN rd_exam_place d ON a.cp_examplaceid = d.place_id;
