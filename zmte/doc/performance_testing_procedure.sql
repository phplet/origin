DROP PROCEDURE IF EXISTS myProc_student;
DELIMITER // 
CREATE PROCEDURE myProc_student(IN u_id int) 
BEGIN
    
       DECLARE i int;
        SET i=1;
    loop1: WHILE i<=500 DO
       
            insert IGNORE into  rd_student(email,first_name,last_name,idcard,exam_ticket,password,grade_id,sex,birthday,picture,
province,city,area,school_id,address,zipcode,mobile,is_check,last_login,last_ip,email_validate,status,is_delete,addtime,`source_from`)  select CONCAT(i,email),CONCAT(first_name,i),
CONCAT(last_name,i),idcard,(select max(exam_ticket) from rd_student)+1,password,grade_id,sex,birthday,picture,
province,city,area,school_id,address,zipcode,mobile,is_check,last_login,last_ip,email_validate,status,is_delete, unix_timestamp(now()),2 from `rd_student` where uid=u_id;
      
         SET i=i+1;
   END WHILE loop1;

end
// 
DELIMITER ;
