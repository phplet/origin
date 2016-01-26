<?php
/**
 * TeacherStudentModel
 * @file    TeacherStudent.php
 * @author  TCG
 * @final   2015-11-16
 */
class TeacherStudentModel
{
    /**
     * 教师学生对应关系列表
     * @param   string  $field = '*'        查询字段
     * @param   array   $cond_param = NULL  表示查询字段及值
     * @param   int     $page = NULL        为NULL表示查询所有不分页
     * @param   int     $perpage = NULL     为NULL表示取默认值或系统配置值
     * @return  array   list<map<string, variant>>类型数据
     */
    public static function teacherStudentList( $field = "*",
        array $cond_param = NULL, $page = NULL, $perpage = NULL)
    {
        $where = array();
        $bind = array();
        if (is_array($cond_param))
        {
            if (Validate::isNotEmpty($cond_param['ct_name']))
            {
                $where[] = 'ct_name LIKE ?';
                $bind[]  = '%' . $cond_param['ct_name'] . '%';
            }
            
            if (Validate::isNotEmpty($cond_param['stu_name']))
            {
                $where[] = 'CONCAT(last_name, first_name) LIKE ?';
                $bind[]  = '%' . $cond_param['stu_name'] . '%';
            }
        
            if (Validate::isNotEmpty($cond_param['exam_pid'])
                && Validate::isInt($cond_param['exam_pid']))
            {
                $where[] = 'tstu_exampid = ?';
                $bind[]  = $cond_param['exam_pid'];
            }
            
            if (Validate::isNotEmpty($cond_param['subject_id'])
                && Validate::isInt($cond_param['subject_id']))
            {
                $where[] = 'tstu_subjectid = ?';
                $bind[]  = $cond_param['subject_id'];
            }
        }
        else if (is_string($cond_param))
        {
            $where = $cond_param;
        }
        
        $field = $field ? $field : '*';
        $sql = "SELECT $field
                FROM t_teacher_student tstu
                LEFT JOIN rd_student s ON s.uid = tstu.tstu_stuid
                LEFT JOIN t_cteacher ct ON ct.ct_id = tstu_ctid
                ";
            
        if (!empty($where))
        {
            $sql .= ' WHERE ('  . implode(') AND (', $where) . ')';
        }
        
        $sql .= " ORDER BY tstu_ctid ASC, tstu_stuid ASC, tstu_subjectid ASC";
        
        if ($page)
        {
            if (!$perpage)
            {
                $perpage = C('default_perpage_num');
                if (!$perpage)
                {
                    $perpage = 15;
                }
            }
            
            $sql = Fn::db()->limitPage($sql, $page, $perpage);
        }
        
        return Fn::db()->fetchAll($sql, $bind);
    }
    
    public static function teacherStudentListCount(array $cond_param = NULL)
    {
        unset($cond_param['ordey_by']);
        $rs = self::teacherStudentList('COUNT(*) AS cnt', $cond_param);
        return $rs[0]['cnt'];
    }
    
    /**
     * 获取某个期次下的教师
     * @param   int     $exam_pid
     * @return  mixed
     */
    public static function examTeacherList($exam_pid)
    {
        if (!Validate::isInt($exam_pid)
            || $exam_pid <= 0)
        {
            return array();
        }
        
        $sql = "SELECT ct.*
                FROM t_teacher_student tstu
                LEFT JOIN t_cteacher ct ON ct.ct_id = tstu_ctid
                WHERE tstu_exampid = $exam_pid
                ";
        return Fn::db()->fetchAssoc($sql);
    }
    
    /**
     * 添加教师学生对应关系
     * $param参数如下
     *          int     tstu_ctid       教师id
     *          int     tstu_stuid      学生id
     *          int     tstu_exampid    考试期次
     *          int     tstu_examid     考试id    
     *          int     tstu_subjectid  学科id
     * @return  bool    true|false
     */
    public static function addTeacherStudent($param)
    {
        $param = Func::param_copy($param, 'tstu_ctid',
            'tstu_stuid', 'tstu_exampid', 'tstu_examid',
            'tstu_subjectid');
        
        if (!Validate::isNotEmpty($param['tstu_ctid'])
            || !Validate::isInt($param['tstu_ctid']))
        {
            throw new Exception('学生关联的教师不能为空');
        }
        
        if (!Validate::isNotEmpty($param['tstu_stuid'])
            || !Validate::isInt($param['tstu_stuid']))
        {
            throw new Exception('教师关联学生的不能为空');
        }
        
        if (!Validate::isNotEmpty($param['tstu_exampid'])
            || !Validate::isInt($param['tstu_exampid']))
        {
            throw new Exception('考试期次的不能为空');
        }
        
        if (!Validate::isNotEmpty($param['tstu_subjectid'])
            || !Validate::isInt($param['tstu_subjectid']))
        {
            throw new Exception('教师授课的学科不能为空');
        }
        
        $param['tstu_addtime'] = time();
        
        Fn::db()->replace('t_teacher_student', $param);
        return Fn::db()->lastInsertId('t_teacher_student', 'tstu_id');
    }
}