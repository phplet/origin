<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Index extends S_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function logindiv()
    {
        $this->load->view('index/logindiv');
    }

    // 默认首页
    public function index()
    {
        redirect('student/index/login');
    }

    // 登录页面
    public function login($exam_pid = NULL)
    {
        Fn::ajax_call($this, 'login', 'logout');
        $uid = StudentModel::studentLoginUID();
        if ($uid)
        {
            $student = StudentModel::get_student($uid);
            $query = $this->db->get_where('student_ranking', array('uid' => $uid));
            $score_ranks = $query->result_array();
            if (!$score_ranks && $student['grade_id'] == 6 && $dl != 1)
            {
                redirect('student/profile/awards?dl=1');
            }
            else
            {
                redirect('student/profile/index');
            }
        }

        $data = array();
        $data['exam_ticket_maprule'] = C('exam_ticket_maprule');
        $this->load->view('index/login', $data);
    }

    /**
     * 跳转到学习网继续学习网页
     * 情况1:
     * @param   int     k_zmtekid       二级知识点ID,与k_zmtekpid至少有一个
     * @param   int     k_zmtekpid      一级知识点ID,与k_zmtekid至少有一个
     * @param   string  kp_name         认知过程,可选
     *
     * 情况2:
     * @param   string  subject_name    学科名称,必须
     * @param   string  ms_name         方法策略/信息提取方式名称,必须
     *
     * 情况3:
     * @param   string  subject_name    学科名称,必须
     * @param   string  questype_name   题型名称,必须
     */
    public function studyplus()
    {
        $uid = StudentModel::studentLoginUID();
        if (!$uid)
        {
            message('您没有登录,请先登录系统', 'student/index/login');
        }

        $query = array();
        if (isset($_GET['k_zmtekid']) || isset($_GET['k_zmtekpid']))
        {
            if (isset($_GET['k_zmtekid']))
            {
                $query['k_zmtekid'] = $_GET['k_zmtekid'];
            }
            if (isset($_GET['k_zmtekpid']))
            {
                $query['k_zmtekpid'] = $_GET['k_zmtekpid'];
            }
            if (isset($_GET['kp_name']))
            {
                $query['kp_name'] = $_GET['kp_name'];
            }
        }
        else if (isset($_GET['subject_name']))
        {
            $query['subject_name'] = $_GET['subject_name'];
            if (isset($_GET['ms_name']))
            {
                $query['ms_name'] = $_GET['ms_name'];
            }
            else if (isset($_GET['questype_name']))
            {
                $query['questype_name'] = $_GET['questype_name'];
            }
            else
            {
                message('非法的请求参数');
            }
        }
        else
        {
            message('非法的请求参数');
        }

        $hashcode = C('loginverify')['zmcat']['hashcode'];
        $urlprefix = C('loginverify')['zmcat']['urlprefix'];
        $query = array('query' => Func::encrypt($query, $hashcode));
        $url =  $urlprefix. '/student/exercise/do?' . http_build_query($query);

        $ukey = StudentModel::get_student($uid, 'exam_ticket');
        $data = array(
            'ukey' => $ukey,
            'url' => $url
        );

        $param = array();
        $param['fromid'] = C('loginverify')['zmcat']['fromid'];
        $param['data'] = Func::encrypt($data, $hashcode);
        $str = http_build_query($param);
        redirect($urlprefix . '/user/user/userlogin?' . $str);
    }

    /**
     * 供外部验证登录使用 IMPORTANT
     * GET参数如下:
     * @param   string      callbackparam       jQuery jsonp跨域调用回调JS函数
     * @param   string      from                来源标识
     * @param   string      data                加密数据字符串,data解密后包含如下字段:
     *                                          string  ukey    用户标识
     *                                          string  pass    密码,若无该字段,表示只验证当前登录用户是否与ukey指定用户一致
     *                                                          若有密码字段,表示进行密码验证查看用户是否可登录
     *                                          int     autologin   是否自动登录,0为否, 1为是,若无该字段表示否
     *
     * Response信息为$callbackparam($data2)样式的JS调用代码,需符合jQuery jsonp跨域调用格式
     * $data2为json格式的array数据:
     *      string  error       若有该参数,则表明有错误信息
     *      string  data        该参数表示返回的加密信息字符串,这里返回的是用户信息，解密后为array类型包含如下字段:
     *                          string  ukey            用户标识
     *                          string  username        用户名
     *                          string  fullname        姓名
     *                          string  email           邮箱
     *                          int     grade           年级
     *                          int     gender          性别,0未知,1男,2女
     */
    public function loginverify()
    {
        $resp = new AjaxResponse();
        while (true)
        {
            $from = $_GET['from'];
            $lv_cfg = C('loginverify');
            if (!$lv_cfg)
            {
                $resp->alert('非法访问来源0');
                break;
            }
            if (!isset($lv_cfg[$from]))
            {
                $resp->alert('非法访问来源1');
                break;
            }
            $hashcode = $lv_cfg[$from]['hashcode'];

            $enc_data = $_GET['data'];
            $param0 = Func::decrypt($enc_data, $hashcode);
            //header('Content-Type:application/json;charset=UTF-8');
            if ($param0 === false)
            {
                $resp->alert('非法访问');
                break;
            }
            if (isset($param0['pass']))    // 需要验证用户名、密码是否可登录
            {
                $param = array('ticket' => $param0['ukey'],
                    'password' => $param0['pass']);
                $resp = StudentModel::studentAjaxLogin($param, strlen($param0['pass']) == 32, !$param0['autologin']);
                break;
            }

            // 验证当前登录用户是否与参数相符
            $ukey = $param0['ukey'];
            $uid = StudentModel::studentLoginUID();
            if (!$uid)
            {
                $resp->alert('您没有登录');
            }
            else
            {
                $exdata = StudentModel::get_student($uid, 
                    'uid,email,exam_ticket,external_account,'
                    . 'CONCAT(last_name, first_name) AS fullname,grade_id,sex');
                if ($exdata['exam_ticket'] == $ukey)
                {
                    $resp->exdata = $exdata;
                }
                else
                {
                    $resp->alert('当前登录用户不符');
                }
            }
            break;
        }

        $json_data = array();
        if ($resp->exdata)
        {
            $uinfo = array(
                'ukey' => $resp->exdata['exam_ticket'],
                'username' => $resp->exdata['external_account'] ? $resp->exdata['external_account'] : $resp->exdata['exam_ticket'],
                'fullname' => $resp->exdata['fullname'],
                'email' => $resp->exdata['email'],
                'grade' => $resp->exdata['grade_id'],
                'gender' => $resp->exdata['sex']);
            $json_data['data'] = Func::encrypt($uinfo, $hashcode);
        }
        else
        {
            $resp_data = json_decode($resp->__toString(), true);
            $json_data['error'] = $resp_data[0][1];
        }
        $json_str = json_encode($json_data);
        echo("{$_GET['callbackparam']}({$json_str});");
        exit();
    }

    /**
     * 供外部支付验证使用 IMPORTANT
     * GET参数如下:
     * @param   string      from                来源标识
     * @param   string      data                加密数据字符串,data解密后包含如下字段:
     *                                          string  ukey        用户标识,必须(准考证号)
     *                                          string  pass        密码,若有该字段,表示转账
     *                                          string  auth        密码代替验证,若有该字段,表示转账,是array('ukey' => '', 'amount' => '')的加密值
     *                                          int     amount      转账金额,不可为0,若有该字段,则表示转账
     * 返回的为json格式的array数据:
     *      string  error       若有该参数,则表明有错误信息
     *      string  data        该参数表示返回的加密信息字符串,这里返回的是用户信息，解密后为array类型包含如下字段:
     *                          string  ukey    用户标识
     *                          int     account 当前余额
     */
    public function paytrans()
    {
        $resp = new AjaxResponse();
        while (true)
        {
            $from = $_GET['from'];
            $lv_cfg = C('loginverify');
            if (!$lv_cfg)
            {
                $resp->alert('非法访问来源0');
                break;
            }
            if (!isset($lv_cfg[$from]))
            {
                $resp->alert('非法访问来源1');
                break;
            }
            $hashcode = $lv_cfg[$from]['hashcode'];

            $enc_data = $_GET['data'];
            $param0 = Func::decrypt($enc_data, $hashcode);
            //header('Content-Type:application/json;charset=UTF-8');
            if ($param0 === false)
            {
                $resp->alert('非法访问');
                break;
            }
            if (!isset($param0['ukey']))
            {
                $resp->alert('非法访问参数');
                break;
            }
            $uinfo = StudentModel::get_student_by_exam_ticket($param0['ukey'], 'uid,exam_ticket,password,account');
            if (empty($uinfo))
            {
                $resp->alert('非法用户');
                break;
            }

            if ((isset($param0['pass']) || isset($param0['auth'])) && isset($param0['amount']))
            {
                if (!Validate::isInt($param0['amount']) || $param0['amount'] == 0)
                {
                    $resp->alert('转账金额不能为0');
                    break;
                }

                if ((isset($param0['pass']) && my_md5($param0['pass']) == $uinfo['password'])
                    || (isset($param0['auth']) && Func::encrypt(
                            array('ukey' => $param0['ukey'], 'amount' => $param0['amount']), 
                            $hashcode) == $param0['auth']))
                {
                    if (bcadd($uinfo['account'], $param0['amount'], 0) < 0)
                    {
                        $resp->alert('用户余额不足');
                        break;
                    }
                    // 这里开始交易
                    $tr_no = TransactionRecordModel::genTransactionRecordTrNo();

                    $db = Fn::db();
                    $db->beginTransaction();
                    $rec = array(
                        'tr_no' => $tr_no,
                        'tr_type' => 4,
                        'tr_uid' => $uinfo['uid'],
                        'tr_pid' => NULL,
                        'tr_money' => bcadd($uinfo['account'], $param0['amount'], 0),
                        'tr_cash' => NULL,
                        'tr_trade_amount' => $param0['amount'],
                        'tr_adminid' => 1,
                        'tr_flag' => 2,
                        'tr_createtime' => time());
                    $rec['tr_finishtime'] = $rec['tr_createtime'];
                    if ($param0['amount'] > 0)
                    {
                        $rec['tr_comment'] = "从{$lv_cfg[$from]['name']}转入{$param0['amount']}择明通宝";
                    }
                    else
                    {
                        $v = 0 - $param0['amount'];
                        $rec['tr_comment'] = "转出{$v}择明通宝到{$lv_cfg[$from]['name']}";
                    }
                    try
                    {
                        $db->insert('t_transaction_record', $rec);
                        $db->update('rd_student', array(
                            'account' => $rec['tr_money']),
                        'uid = ' . $uinfo['uid']);
                        $db->commit();
                    }
                    catch (Exception $e)
                    {
                        $db->rollBack();
                        $resp->alert('转账失败');
                        break;
                    }
                    $uinfo['account'] = $rec['tr_money'];
                    $resp->exdata = array('ukey' => $uinfo['exam_ticket'], 'account' => $uinfo['account']);
                }
                else
                {
                    $resp->alert('用户验证未通过');
                }
                break;
            }
            // 只显示余额
            $resp->exdata = array('ukey' => $uinfo['exam_ticket'], 'account' => $uinfo['account']);
            break;
        }

        $json_data = array();
        if ($resp->exdata)
        {
            $json_data['data'] = Func::encrypt($resp->exdata, $hashcode);
        }
        else
        {
            $resp_data = json_decode($resp->__toString(), true);
            $json_data['error'] = $resp_data[0][1];
        }
        $json_str = json_encode($json_data);
        header('Content-Type:application/json;charset=UTF-8');
        echo("{$json_str}");
        exit();
    }

    public function loginFunc($param)
    {
        return StudentModel::studentAjaxLogin($param);
    }

    // 退出登录
    public function logout()
    {
        StudentModel::studentAjaxLogout();
        message('退出成功！', "student/index/login", 'success');
    }

    // 邮箱验证
    public function validate()
    {
        $hash = $this->input->get('code');
        $uid = email_hash('decode', $hash);
        $uid && $student = StudentModel::get_student($uid);

        if ( !$student)
        {
            message('Email认证失败，请检查您的链接是否正确。');
        }

        /*
        if ($student['email_validate'] == 1)
        {
            message('您的Email已认证');
        }
         */

        $this->db->update('student', array('email_validate'=>1), array('uid'=>$uid));
        message('Email认证成功', 'student/index/index', 'success');
    }

    // 取回密码
    public function forget()
    {
        Fn::ajax_call($this, 'login', 'logout');
        if ($this->input->post('act') == 'submit')
        {
            $realname = trim($this->input->post('realname'));
            $email = trim($this->input->post('email'));
            if ( ! $realname || ! is_email($email))
            {
                message('请输入正确的Email格式 和 您的真实姓名');
            }
            $query = $this->db->select('uid,email,first_name,last_name,addtime')->get_where('student', array('email'=>$email));
            $user = $query->row_array();
            if ( ! $user)
            {
                message('该Email地址未注册');
            }
            if ($user['last_name'].$user['first_name'] !== $realname)
            {
                message('真实姓名和Email地址不匹配。');
            }

            // 发送邮件
            $email_tpl = C('email_template/reset_password');
            $mail = array(
                'student' => $user,
                'hash'    => email_hash('encode', $user['uid']),
            );
            $res = send_email($email_tpl['subject'], $this->load->view($email_tpl['tpl'], $mail, TRUE), $user['email']);

            if ($res)
            	message('您的申请已提交，请查收邮件！<hr/><p style="font-weight:bold;font-size:14px;">温馨提示：</p><p style="font-size:12px;text-indent:18px;">1、如发现收件箱中没有收到，请到垃圾邮件/广告邮件查收。</p><br/>', 'student/index/index', 'success');
            else
            	message('邮件发送失败，请确认您的邮箱是否有效！');
        }
        else
        {
            $data = array();
            $data['uinfo'] = StudentModel::studentLoginUInfo();
            // 模版
            $this->load->view('index/forget', $data);
        }
    }

    public function resetpwd()
    {
        Fn::ajax_call($this, 'login', 'logout');

        $hash = $this->input->get('code');
        $uid = email_hash('decode', $hash, 1800);
        $uid && $student = StudentModel::get_student($uid);
        if ( ! $student)
        {
            message('重置链接已失效，请重新提交申请', 'student/index/forget');
        }

        if ($this->input->post('act') == 'submit')
        {
            $password = $this->input->post('password');
            $newpwd_confirm = $this->input->post('password_confirm');

            if (is_string($passwd_msg = is_password($password)))
            {
                message($passwd_msg);
            }
            if ($password != $newpwd_confirm)
            {
                message('您两次输入密码不一致，返回请确认！');
            }

            $this->db->update('student', array('password'=>my_md5($password)), array('uid'=>$uid));

            $now_time = time()-1800;
            $sql = "UPDATE  {pre}user_resetpassword SET expiretime='$now_time' WHERE uid='$uid' and  hash = '$hash'";
            $row = $this->db->query($sql);

            message('您的新密码已设置成功.', 'student/index/login', 'success');
        }
        else
        {
            $data = array();
            $data['uinfo'] = StudentModel::studentLoginUInfo();
            $data['hash'] = $hash;

            // 模版
            $this->load->view('index/resetpwd', $data);
        }
    }

    /**
     * 考试成绩查询
     */
    public function exam_result()
    {
        $uid = StudentModel::studentLoginUID();
        if (!$uid)
        {
            // 登录失效
            redirect('student/index/login');
        }
        
        //检查学生信息是否完善
        //$this->check_perfect_student();

        $exam_list = $this->db->select('distinct(exam_id),exam_name')->from('exam e')
                     ->join('exam_result_publish erp','e.exam_id = erp.exam_pid')
                     ->join('exam_place ep','e.exam_id = ep.exam_pid')
                     ->join('exam_place_student eps','ep.place_id = eps.place_id')
                     ->where('uid', $uid)->get()->result_array();

        $exam_result_list = array();
        foreach ($exam_list as $exam)
        {
            $rule = $this->db->select('id, subject_id')->from('evaluate_rule')->where('exam_pid', $exam['exam_id'])->order_by('subject_id', 'ASC')->get()->result_array();
            $exam_result_list[$exam['exam_id']]['exam_name'] = $exam['exam_name'];
            foreach ($rule as $item)
            {
                $source_path = $item['id'] ."/$uid.zip";
                $filepath = realpath(dirname(APPPATH)) . "/cache/zip/report/" . $source_path;
                if (!file_exists($filepath))
                {
                    continue;
                }

                $exam_result_list[$exam['exam_id']]['list'][$item['subject_id']] = $item['id'];
            }
        }

        $data['exam_result_list'] =  $exam_result_list;
        $data['subject'] = C('subject');
        $this->load->view('index/exam_result_list', $data);
    }

    /**
     * 考试成绩下载
     */
    public function down_file($rule_id, $subject_id, $dump_type)
    {
        $uid = StudentModel::studentLoginUID();
        if (!$uid)
        {
            // 登录失效
            redirect('student/index/login');
        }
        
        //检查学生信息是否完善
        //$this->check_perfect_student();

        if ($dump_type == 'egapenolmthweiv')
        {
            $html_file = realpath(dirname(APPPATH)) . "/cache/html/report/{$rule_id}/{$uid}/{$subject_id}.html";
            if (file_exists($html_file))
            {
                header('Content-Type: text/html; charset=UTF-8');
                @readfile($html_file);
                exit();
            }
        }

        if ($dump_type == 'egapenolmthweiv' || $dump_type == 'fdpfopiznwod')
        {
            $name = "$uid.zip";
            $source_path = $rule_id ."/$uid.zip";
            $filepath = realpath(dirname(APPPATH)) . "/cache/zip/report/" . $source_path;

            if (!file_exists($filepath))
            {
                message('你的考试成绩还未生成，请稍后下载...');
            }

            header("Cache-Control: public");
            header("Content-Description: File Transfer");
            header('Content-disposition: attachment; filename='.$name); //文件名
            header("Content-Type: application/zip"); //zip格式的
            header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
            header('Content-Length: '. filesize($filepath)); //告诉浏览器，文件大小

            @readfile($filepath);
            exit();
        }
        else
        {
            die('Error Request');
        }
    }
    
    /**
     * 面试成绩查询
     */
    public function interview_exam_result()
    {
        $uid = StudentModel::studentLoginUID();
        if (!$uid)
        {
            // 登录失效
            redirect('student/index/login');
        }
        
        //检查学生信息是否完善
        //$this->check_perfect_student();

        // 根据考生id查询面试结果
        $sql = <<<EOT
SELECT er.id AS rule_id, e.exam_name 
FROM rd_evaluate_rule er 
LEFT JOIN rd_interview_result ir ON er.exam_pid = ir.exam_id 
LEFT JOIN rd_exam e ON ir.exam_id = e.exam_id 
WHERE ir.student_id = {$uid}
EOT;
        $rule_info = $this->db->query($sql)->row_array();

        $source_path = $rule_info['rule_id'] ."/$uid.zip";
        $filepath = realpath(dirname(APPPATH)) . "/cache/zip/interview_report/" . $source_path;

        $data = array();

        if (file_exists($filepath))
        {
            $data['exam_name'] = $rule_info['exam_name'];
            $data['rule_id'] = $rule_info['rule_id']; 
        }

        $this->load->view('index/interview_result', $data);
    }

    /**
     * 面试成绩下载
     */
    public function down_interview_file($rule_id)
    {
        $uid = StudentModel::studentLoginUID();

        if (!$uid)
        {
            // 登录失效
            redirect('student/index/login');
        }
        
        //检查学生信息是否完善
        //$this->check_perfect_student();

        $name = "$uid.zip";
        $source_path = $rule_id ."/$uid.zip";
        $filepath = realpath(dirname(APPPATH)) . "/cache/zip/interview_report/" . $source_path;

        if (!file_exists($filepath))
        {
            message('你的考试成绩还未生成，请稍后下载...');
        }

        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename='.$name); //文件名
        header("Content-Type: application/zip"); //zip格式的
        header("Content-Transfer-Encoding: binary"); //告诉浏览器，这是二进制文件
        header('Content-Length: '. filesize($filepath)); //告诉浏览器，文件大小

        @readfile($filepath);
    }
    
    /**
     * 考试人数统计
     */
    public function exam_stat()
    {
        $lifetime = 3600;
        $file = ROOTPATH . '/cache/filecache/exam_times.png';
        $last_modify = 0;
        if (file_exists($file))
        {
            $last_modify = filemtime($file);
        }
        if (time() - $last_modify > $lifetime)
        {
            $sql = 'SELECT COUNT(*) AS c FROM rd_exam_test_paper';
            $exam_times = Fn::db()->fetchOne($sql);
            
            //rd杯面试
            $exam_times += 504;
	
	    //rd分层测试人数
            $exam_times += 3604;
        
            //rd分层测试人数
            $exam_times += 3604;

            // 2015-07-27日之前的实际测试人次
            $exam_times += 89892;

            // 2015-11-20日城东中学分配试卷暂时数量（之后需要去除）
            $exam_times += 2572;
        
            $data = array('code'=> 'success', 'data' => $exam_times);
        
            /*
            header("Content-Type:text/javascript;charset=UTF-8");
            $data = json_encode($data);
            print <<<EOT
var g_exam_stat = {$data};
EOT;
            exit();
             */

            // 输出图片
            //ob_clean();
            $img = imagecreatefrompng('images/index/exam_stat.png');
            $bg = imagecolorallocate($img, 255, 255, 255);
            $textcolor = imagecolorallocate($img, 255, 0, 0);
            $font = '/usr/share/fonts/truetype/DroidSans.ttf';
            imagettftext($img, 24, 0, 40, 130, $textcolor, $font, $exam_times);
            imagepng($img, $file);
            //ob_end_flush();
            imagedestroy($img);

            $last_modify = filemtime($file);
        }
        ob_clean();
        header('Content-Type: image/png');
        header('Content-Length:' . filesize($file));
        header('Last-Modified: ' 
            . gmdate('D, j M Y H:i:s', $last_modify) . ' GMT');
        header('Expires: ' 
            . gmdate('D, j M Y H:i:s', $last_modify + $lifetime) . ' GMT');
        header('Cache-Control: public,max-age=' . $lifetime);
        readfile($file);
        exit();
    }

    /**
     * 考试人数统计
     */
    public function exam_stat2()
    {
        $lifetime = 3600;
        $file = ROOTPATH . '/cache/filecache/exam_times2.png';
        $last_modify = 0;
        if (file_exists($file))
        {
            $last_modify = filemtime($file);
        }
        if (time() - $last_modify > $lifetime)
        {
            $sql = 'SELECT COUNT(*) AS c FROM rd_exam_test_paper';
            $exam_times = Fn::db()->fetchOne($sql);
            
            //rd杯面试
            $exam_times += 504;
	
	    //rd分层测试人数
            $exam_times += 3604;
        
            //rd分层测试人数
            $exam_times += 3604;

            // 2015-07-27日之前的实际测试人次
            $exam_times += 89892;

            // 2015-11-20日城东中学分配试卷暂时数量（之后需要去除）
            $exam_times += 2572;
        
            $data = array('code'=> 'success', 'data' => $exam_times);

            // 输出图片
            $img = imagecreatefrompng('images/index/exam_stat2.png');
            imagesavealpha($img, true);

            $textcolor = imagecolorallocate($img, 216, 37, 69);
            //$font = '/usr/share/fonts/truetype/DroidSans.ttf';
            $font = '/usr/share/fonts/truetype/luxisb.ttf';
            $len = strlen($exam_times);
            for ($i = 0; $i < $len; $i++)
            {
                $x = 119 + 73 * $i;
                imagettftext($img, 48, 0, $x, 246, $textcolor, $font, 
                    substr($exam_times, $i, 1));
            }
            imagepng($img, $file);
            imagedestroy($img);
            $last_modify = filemtime($file);
        }
        ob_clean();
        header('Content-Type: image/png');
        header('Content-Length:' . filesize($file));
        header('Last-Modified: ' 
            . gmdate('D, j M Y H:i:s', $last_modify) . ' GMT');
        header('Expires: ' 
            . gmdate('D, j M Y H:i:s', $last_modify + $lifetime) . ' GMT');
        header('Cache-Control: public,max-age=' . $lifetime);
        readfile($file);
        exit();
    }


    /************************************************************************
     * 未嵌入正式方法中的测试代码在这里写
     ************************************************************************/
    public function PU_TEST($testClassName)
    {
        // ROOTPATH APPPATH
        // 通过调用PU::TEST()方法来加载测试类进行测试
        PU::TEST($testClassName);
    }
}
