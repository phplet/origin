<?php
class StudentModel
{
    /**
     * 获取学生的信息
     * @param number $uid
     * @param string $item
     * @return boolean|unknown
     */
    public static function get_student($uid = 0, $item = '*')
    {
        return self::_get_student_by_unique('uid', $uid, $item);
    }

    /**
     * 按  Email 获取一个学生信息
     */
    public static function get_student_by_email($email, $item = '*')
    {
        return self::_get_student_by_unique('email', $email, $item);
    }

    /**
     * 按  准考证(exam_ticket)  获取一个学生信息
     */
    public static function get_student_by_exam_ticket($exam_ticket, $item = '*')
    {
        return self::_get_student_by_unique('exam_ticket', $exam_ticket, $item);
    }

    /**
     * 按 UNIQUE/PRIMARY KEY 获取一个学生信息
     *
     * @param   string  字段名称
     * @param   string  字段值
     * @param   string  需要获取的字段值
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    private static function _get_student_by_unique($key, $val, $item = '*')
    {
        $sql = <<<EOT
SELECT {$item} FROM rd_student WHERE {$key} = {$val}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }


    /**
     * 后台用户添加
     */
    public static function add($student, $extends = array())
    {
        $result = array(
            'uid'      => '',
            'msg'      => '',
            'success' => FALSE
        );

        try
        {
            $db = Fn::db();
            $err1 = $err2 = false;

            $row = $db->fetchRow(
                "SELECT uid FROM rd_student WHERE email = ?",
                $student['email']);
            if ($row)
            {
                $err1 = true;
                $msg[] = '该邮箱已被注册!';
            }

            $row = $db->fetchRow(
                "SELECT uid FROM rd_student WHERE idcard = ?",
                $student['idcard']);
            if ($row)
            {
                $err2 = true;
                $msg[] = '该身份证号码已被注册!';
            }

            if ($err1 || $err2)
            {
                throw new Exception(implode("\n", $msg));
            }
            //end

            // 检查基本信息是否已注册
            $where = array(
                'first_name' => $student['first_name'],
                'last_name'  => $student['last_name'],
                'sex'        => $student['sex'],
                'school_id'  => $student['school_id'],
                'grade_id'   => $student['grade_id'],
                'idcard'     => $student['idcard']
            );

            $where_sql = array();
            $bind = array();
            foreach ($where as $k => $v)
            {
                $where_sql[] = "$k = ?";
                $bind[] = $v;
            }
            $sql = <<<EOT
SELECT uid FROM rd_student 
WHERE first_name = ? AND last_name = ? AND sex = ? AND school_id = ? 
AND grade_id = ? AND idcard = ?
EOT;
            $row = $db->fetchRow(
                "SELECT uid FROM rd_student WHERE " 
                . implode(' AND ', $where_sql) . ' LIMIT 1', 
                $bind);
            if ($row)
            {
                throw new Exception('该考生已报名!');
            }

            $db->beginTransaction();

            $idcard = $student['idcard'];
            $region = substr($idcard, 0, 4);
            $year   = substr($idcard, 8, 2);

            $sql = <<<EOT
INSERT INTO rd_student (exam_ticket, email, idcard)
    SELECT IFNULL(MAX(exam_ticket), {$region}{$year}000000) + 1, '{$student['email']}', '{$idcard}'
    FROM rd_student
    WHERE exam_ticket IS NOT NULL AND exam_ticket BETWEEN {$region}{$year}000001 AND {$region}{$year}999999
EOT;
            $res = $db->exec($sql);
            if (!$res)
            {
                throw new Exception('注册失败!');
            }

            unset($student['email']);
            unset($student['idcard']);

            $uid = $db->lastInsertId('rd_student', 'uid');
            $student['addtime'] = time();

            $db->update('rd_student', $student, 'uid = ' . $uid);

            if (!empty($extends))
            {
                $score_ranking = &$extends['score_ranking'];
                $awards_list   = &$extends['awards_list'];
                $practice      = &$extends['practice'];
                $student_wish  = &$extends['student_wish'];
                $parent_wish   = &$extends['parent_wish'];
                $xuekao_xuankao   = &$extends['xuekao_xuankao'];
                $practice['uid']     = $uid;
                $student_wish['uid'] = $uid;
                $parent_wish['uid']  = $uid;
                $xuekao_xuankao['uid']  = $uid;


                $old_xuekao_xuankao = $db->fetchRow(
                    "SELECT id FROM rd_xuekao_xuankao WHERE uid = $uid");
                if($old_xuekao_xuankao)
                {
                    $db->update('rd_xuekao_xuankao', $xuekao_xuankao, 
                        "id = " . $old_xuekao_xuankao['id']);
                }
                else
                {
                    $db->insert('rd_xuekao_xuankao', $xuekao_xuankao);
                }
                $db->insert('rd_student_practice', $practice);
                $db->insert('rd_student_wish', $student_wish);
                $db->insert('rd_student_parent_wish', $parent_wish);
                // $this->db->insert('xuekao_xuankao', $xuekao_xuankao);
                if ($score_ranking)
                {
                    foreach ($score_ranking as &$rank)
                    {
                        $rank['uid'] = $uid;
                        $db->insert('rd_student_ranking', $rank);
                    }
                }

                if ($awards_list)
                {
                    foreach ($awards_list as &$row)
                    {
                        unset($row['id']);
                        $row['uid'] = $uid;
                        $db->insert('rd_student_awards', $row);
                    }
                }
            }

            $db->commit();

            $student = $db->fetchRow(
                "SELECT exam_ticket FROM rd_student WHERE uid = $uid");
            $exam_ticket = $student['exam_ticket'];

            $result['success'] = TRUE;
            $result['uid'] = $uid;
            $result['exam_ticket'] = $exam_ticket;
        }
        catch (Exception $e)
        {
            $result['msg'] = $e->getMessage();
        }
        return $result;
    }

    public static function update(&$student, &$extends, $tab = '')
    {
        $result = array(
            'uid'      => '',
            'msg'      => '',
            'success' => FALSE
        );

        try
        {
            $uid = $student['uid'];
            $err1 = $err2 = false;

            if (empty($uid))
            {
                throw new Exception('非法的数据!');
            }

            $db = Fn::db();

            if ($tab == 'basic' || $tab == '')
            {
                if (isset($student['email']) && $student['email'])
                {
                    $sql = <<<EOT
SELECT uid FROM rd_student WHERE email = ? AND uid <> ?
EOT;
                    $row = $db->fetchRow($sql, 
                        array($student['email'], $student['uid']));
                    if ($row)
                    {
                        $err1 = true;
                        $msg[] ='该邮箱已被注册!';
                    }
                }

                if (isset($student['idcard']) && $student['idcard'])
                {
                    $sql = <<<EOT
SELECT uid FROM rd_student WHERE idcard = ? AND uid <> ?
EOT;
                    $row = $db->fetchRow($sql, 
                        array($student['idcard'], $student['uid']));
                    if ($row)
                    {
                        $err2 = true;
                        $msg[] ='该身份号码已被注册!';
                    }
                }
            }

            if ($err1 || $err2)
            {
                throw new Exception(implode("\n", $msg));
            }
            //end

            $old_ranking = $old_awards = array();
            $sql = <<<EOT
SELECT id, grade_id FROM rd_student_ranking WHERE uid = {$uid}
EOT;
            $rows = $db->fetchAll($sql);
            foreach ($rows as $row)
            {
                $old_ranking[$row['grade_id']] = $row['id'];
            }

            $sql = <<<EOT
SELECT id FROM rd_student_awards WHERE uid = {$uid}
EOT;
            $rows = $db->fetchAll($sql);
            foreach ($rows as $row)
            {
                $old_awards[$row['id']] = $row['id'];
            }

            $db->beginTransaction();
            $db->update('rd_student', $student, "uid = $uid");

            $score_ranking = &$extends['score_ranking'];
            $awards_list   = &$extends['awards_list'];
            $practice      = &$extends['practice'];
            $student_wish  = &$extends['student_wish'];
            $parent_wish   = &$extends['parent_wish'];
            $xuekao_xuankao   = &$extends['xuekao_xuankao'];
            $practice['uid']     = $uid;
            $student_wish['uid'] = $uid;
            $parent_wish['uid']  = $uid;
            $xuekao_xuankao['uid'] = $uid;

            if ($tab == 'awards' || $tab == '')
            {
                $old_xuekao_xuankao = $db->fetchRow(
                    "SELECT id FROM rd_xuekao_xuankao WHERE uid = $uid");
                if($old_xuekao_xuankao)
                {
                    $db->update('rd_xuekao_xuankao', $xuekao_xuankao, 
                        'id = ' . $old_xuekao_xuankao['id']);
                }
                else
                {
                    $db->insert('rd_xuekao_xuankao', $xuekao_xuankao);
                }
            }

            if ($tab == 'practice' || $tab == '')
            {
                $db->replace('rd_student_practice', $practice);
            }
            if ($tab == 'wish' || $tab == '')
            {
                $db->replace('rd_student_wish', $student_wish);
            }
            if ($tab == 'pwish' || $tab == '')
            {
                $db->replace('rd_student_parent_wish', $parent_wish);
            }
            // $db->replace('rd_xuekao_xuankao', $xuekao_xuankao);
            if ($tab == 'awards' || $tab == '')
            {
                // 成绩排名
                foreach ($score_ranking as $grade_id => &$rank)
                {
                    $rank['uid'] = $uid;
                    if (isset($old_ranking[$grade_id]))
                    {
                        $db->update('rd_student_ranking', $rank, 
                            'id = ' . $old_ranking[$grade_id]);
                        unset($old_ranking[$grade_id]);
                        unset($score_ranking[$grade_id]);
                    }
                }
                if ($score_ranking)
                {
                    foreach ($score_ranking as $vv)
                    {
                        $db->insert('rd_student_ranking', $vv);
                    }
                }
                if ($old_ranking)
                {
                    $db->delete('rd_student_ranking', 
                        'id IN (' . implode(',', $old_ranking) . ')');
                }

                // 获奖情况
                foreach ($awards_list as $k => &$awards)
                {
                    $awards['uid'] = $uid;
                    if ($awards['id'] && isset($old_awards[$awards['id']]))
                    {
                        $db->update('rd_student_awards', $awards, 
                            'id = ' . $old_awards[$awards['id']]);
                        unset($old_awards[$awards['id']]);
                        unset($awards_list[$k]);
                    }
                    else
                    {
                        unset($awards['id']);
                    }
                }
                if ($awards_list)
                {
                    foreach ($awards_list as $vv)
                    {
                        $db->insert('rd_student_awards', $vv);
                    }
                }
                if ($old_awards)
                {
                    $db->delete('rd_student_awards', 
                        "id IN (" . implode(',', $old_awards) . ')');
                }
            }

            $db->commit();
            $result['success'] = TRUE;
            $result['uid'] = $uid;
        }
        catch(Exception $e)
        {
            $result['msg'] = $e->getMessage();
        }
        return $result;
    }


    /**
     * 重置学生密码
     * @param string $uid
     * @param string $new_password
     * @return boolean
     */
    public static function reset_password($uid, $new_password)
    {
        $uid = intval($uid);
        if ($uid <= 0)
        {
            return false;
        }

        try
        {
            $update_data = array(
                'password' => $new_password,
            );
            Fn::db()->update('rd_student', $update_data, "uid = $uid");
            return true;
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    /**
     * 学生注册前置项完整性检查 前置项列表配置:C('product/prefixinfo'),
     * @param   int     $uid            学生UID
     * @param   string  $prefixinfo     逗号分隔的要检查的前置项
     * @return  array                   map<string, bool>类型的前置项检查结果
     *                                  比如 array('base' => true, 
     *                                          'score' => false),键即为
     *                                  参数$prefixinfo的各项
     */
    public static function registerItemCompleteCheck($uid, $prefixinfo)
    {
        $item_arr = explode(',', $prefixinfo);
        if (!is_array($item_arr))
        {
            return array();
        }
        if (empty($item_arr))
        {
            return $item_arr;
        }

        $item_full = C('product_prefixinfo');
        $item_arr = array_unique(array_intersect(array_keys($item_full), 
                        $item_arr));
        if (empty($item_arr))
        {
            return $item_arr;
        }

        $item_map = array();
        foreach ($item_arr as $item)
        {
            $item_map[$item] = false;
        }

        $db = Fn::db();
        // 学习概况
        while (isset($item_map['base']))
        {
            $sql = <<<EOT
SELECT a.uid, a.school_id, b.*, GROUP_CONCAT(c.sbclassid_classid) AS classid_str, 
GROUP_CONCAT(d.sbs_stunumtype) AS stunumtype_str, e.*
FROM rd_student a 
LEFT JOIN t_student_base b ON a.uid = b.sb_uid
LEFT JOIN t_student_base_classid c ON a.uid = c.sbclassid_uid
LEFT JOIN t_student_base_stunumtype d ON a.uid = d.sbs_uid
LEFT JOIN t_student_base_course e ON a.uid = e.sbc_uid AND e.sbc_idx = 0
WHERE a.uid = {$uid}
EOT;
            $row = $db->fetchRow($sql);
            if (empty($row))
            {
                break;
            }
            /*
            // 学校, NULL or 0
            if (empty($row['school_id']))
            {
                break;
            }
             */
            // 家庭住址省, NULL or 0
            if (empty($row['sb_addr_provid']))
            {
                break;
            }
            // 家庭住址详细 NULL or ''
            if (is_null($row['sb_addr_desc']) 
                || trim($row['sb_addr_desc']) == '')
            {
                break;
            }
            // 希望辅导难度 NULL
            if (is_null($row['classid_str']))
            {
                break;
            }
            // 可接受授课模式 NULL
            if (is_null($row['stunumtype_str']))
            {
                break;
            }
            // 正在培训课程
            if (!is_null($row['sbc_uid']))
            {
                // 机构、课程、老师
                if (empty($row['sbc_tiid'])
                    || empty($row['sbc_corsid'])
                    || is_null($row['sbc_teachers'])
                    || trim($row['sbc_teachers']) == '')
                {
                    break;
                }
            }
            $item_map['base'] = true;
            break;
        }

        // 学习成绩
        while (isset($item_map['score']))
        {
            $sql = <<<EOT
SELECT COUNT(*) AS cnt FROM rd_student_ranking WHERE uid = {$uid}
EOT;
            $cnt = $db->fetchOne($sql);
            if ($cnt < 3)
            {
                break;
            }


            $sql = <<<EOT
SELECT a.uid, a.grade_id, b.id, b.is_in_class, b.subject_first 
FROM rd_student a LEFT JOIN rd_xuekao_xuankao b ON a.uid = b.uid
WHERE a.uid = {$uid}
EOT;
            $row = $db->fetchRow($sql);
            if (empty($row))
            {
                break;
            }
            // 是否实验班 NULL
            if (is_null($row['is_in_class']))
            {
                break;
            }
            if ($row['grade_id'] > 10)
            {
                if (is_null($row['subject_first']))
                {
                    break;
                }
                // 大于高一时,必填
                $arr = unserialize($row['subject_first']);
                if (empty($arr['subject_id'])
                    || empty($arr['fenshu'])
                    || empty($arr['shijian']))
                {
                    break;
                }
            }
            
            $item_map['score'] = true;
            break;
        }

        // 社会实践情况
        while (isset($item_map['practice']))
        {
            $item_map['practice'] = true;
            break;
        }

        // 学生意愿检查
        while (isset($item_map['selfwish']))
        {
            $sql = <<<EOT
SELECT uid, upmethod, wish FROM rd_student_wish WHERE uid = {$uid}
EOT;
            $row = $db->fetchRow($sql);
            if (empty($row))
            {
                break;
            }

            // 升学途径
            if (is_null($row['upmethod']) || trim($row['upmethod']) == '')
            {
                break;
            }

            // 发展意愿
            if (is_null($row['wish']) || trim($row['wish']) == '')
            {
                break;
            }

            $item_map['selfwish'] = true;
            break;
        }

        // 家长意愿检查
        while (isset($item_map['parentwish']))
        {
            $sql = <<<EOT
SELECT * FROM rd_student_parent_wish WHERE uid = {$uid}
EOT;
            $row = $db->fetchRow($sql);
            if (empty($row))
            {
                break;
            }
            // 家庭职业等
            if ((is_null($row['family_bg']) || trim($row['family_bg']) == '')
                && (is_null($row['other_bg']) || trim($row['other_bg']) == ''))
            {
                break;
            }

            // 升学途径
            if (is_null($row['upmethod']) || trim($row['upmethod']) == '')
            {
                break;
            }

            // 发展意愿
            if (is_null($row['wish']) || trim($row['wish']) == '')
            {
                break;
            }

            // 户籍、监护人等
            if (is_null($row['family_bg_qt']) 
                || trim($row['family_bg_qt']) == '')
            {
                break;
            }

            $item_map['parentwish'] = true;
            break;
        }
        return $item_map;
    }

    public static function studentUpdateSession()
    {
        $uid = Fn::sess()->userdata('uid');
        if ($uid)
        {
            $sql = <<<EOT
SELECT uid, email, first_name, last_name, idcard, exam_ticket, 
    CONCAT(last_name, first_name) AS fullname,
    external_account, maprule, grade_id, sex, birthday, picture, mobile,
    is_check, last_login, last_ip, email_validate, status, is_delete, addtime,
    account, account_status
FROM rd_student WHERE uid = {$uid}
EOT;
            $user = Fn::db()->fetchRow($sql);
            if ($user)
            {
                if (trim($user['picture']))
                {
                    $user['avatar_url'] = __IMG_ROOT_URL__ . $user['picture'];
                }
                else
                {
                    $user['avatar_url'] = __IMG_ROOT_URL__ . 'zeming/exam/head.gif';
                }
                Fn::sess()->set_userdata(array('uid' => $uid, 'uinfo' => $user));
                return;
            }
        }
        Fn::sess()->set_userdata(array('uid' => '', 
            'uinfo' => array('fullname' => '[游客]', 'uid' => '',
            'avatar_url' => __IMG_ROOT_URL__ . 'zeming/exam/head.gif')
        ));
    }

    public static function studentAjaxLogin($param, $bPasswordEnc = false, $bValidateOnly = false)
    {
        $resp = new AjaxResponse();
        
        $param = Func::param_copy($param, 'ticket', 'password');
        if (empty($param['ticket']) || empty($param['password'])) 
        {
            $resp->alert('帐号或密码不能为空！');
            return $resp;
        }

        $where = array();
        $bind = array();

        if (is_email($param['ticket']))
        {
            $where[] = 'email = ?';
            $bind[] = $param['ticket'];
        }
        else if (is_idcard($param['ticket']))
        {
            $where[] = 'idcard = ?';
            $bind[] = $param['ticket'];
        }
        else
        {
            //message('请输入合法的登陆帐号');
            $where[] = 'exam_ticket = ? OR external_account = ?';
            $bind[] = $param['ticket'];
            $bind[] = $param['ticket'];
        }

        $where[] = 'password = ?';
        if ($bPasswordEnc)
        {
            $bind[] = $param['password'];
        }
        else
        {
            $bind[] = my_md5($param['password']);
        }

        $sql_where = implode(') AND (', $where);
        $sql = <<<EOT
SELECT uid, email, first_name, last_name, idcard, exam_ticket, 
    CONCAT(last_name, first_name) AS fullname,
    external_account, maprule, grade_id, sex, birthday, picture, mobile,
    is_check, last_login, last_ip, email_validate, status, is_delete, addtime,
    account, account_status
FROM rd_student WHERE ($sql_where)
EOT;
        $user = Fn::db()->fetchRow($sql, $bind);
        if ($user)
        {
            $uid = $user['uid'];

            if (trim($user['picture']))
            {
                $user['avatar_url'] = __IMG_ROOT_URL__ . $user['picture'];
            }
            else
            {
                $user['avatar_url'] = __IMG_ROOT_URL__ . 'zeming/exam/head.gif';
            }

            $resp->exdata = $user;

            if (!$bValidateOnly)
            {
                $sess = Fn::sess();
                if ($sess->userdata('uid') == $uid)
                {
                    // 当前登录用户已经是请求登录用户,不需要再登录了
                    $resp->refresh();
                }
                else
                {
                    $data = array();
                    $data['last_login'] = time();
                    $data['last_ip'] = Func::get_client_ip();
                    Fn::db()->update('rd_student', $data, 'uid = ' . $uid);
                    $sess->set_userdata(array('uid' => $uid, 'uinfo' => $user));

                    $sql = "SELECT * FROM rd_student_ranking WHERE uid = $uid";
                    $score_ranks = Fn::db()->fetchRow($sql);
                    if (!$score_ranks && $user['grade_id'] == 6)
                    {
                        // 在basic页面会自动判断是否填写完全学生成绩并进行提示跳转
                        $resp->redirect(site_url('student/profile/basic'));
                    }
                    else
                    {
                        $resp->refresh();
                    }
                }
            }
        }
        else
        {
            $resp->alert('帐号或密码不正确！');
        }
        return $resp;
    }

    public static function studentAjaxLogout($url = NULL)
    {
        $resp = new AjaxResponse();
        Fn::sess()->set_userdata(array('uid' => '', 
            'uinfo' => array('fullname' => '[游客]', 'uid' => '',
            'avatar_url' => __IMG_ROOT_URL__ . 'zeming/exam/head.gif')
        ));
        if ($url)
        {
            $resp->redirect($url);
        }
        else
        {
            $resp->refresh();
        }
        return $resp;
    }

    public static function studentLoginUID()
    {
        return Fn::sess()->userdata('uid');
    }

    public static function studentLoginUInfo()
    {
        $uinfo = Fn::sess()->userdata('uinfo');
        if (empty($uinfo))
        {
            $uinfo = array('fullname' => '[游客]', 'uid' => '',
                'avatar_url' => __IMG_ROOT_URL__ . 'zeming/exam/head.gif');
            Fn::sess()->set_userdata(array('uid' => '', 'uinfo' => $uinfo));
        }
        return $uinfo;
    }
}
