<?php
/**
 * 后台管理用户模块RoleModel
 * @file    Role.php
 * @author  BJP
 * @final   2015-06-19
 */
class RoleModel
{
    /**
     * 按ID读取角色信息
     * @param   int     角色ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_role($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_role WHERE role_id = {$id}
EOT;
        $user = Fn::db()->fetchRow($sql);
        if ($item && isset($user[$item]))
        {
            return $user[$item];
        }
        else
        {
            return $user;        
        }
    }

    /**
     * 读取角色列表
     *          
     * @return  array
     */
    public static function get_role_list()
    {
        $rows = Fn::db()->fetchAll("SELECT * FROM rd_role");
        $list = array();
        foreach ($rows as $row)
        {
            $list[$row['role_id']] = $row;
        }
        return $list;
    }
    
    /**
     * 获取当前登录用户可操作的学科列表
     */
    public static function get_allowed_subjects()
    {
         $session = Fn::sess()->all_userdata();
         $is_super_user = $session['is_super'];
         if (!$is_super_user 
             && !self::is_action_type_all('question', 'r') 
             && !self::is_action_type_all('question', 'w'))
         {
             $temp_subjects = C('subject');
             $subjects = array();
             foreach ($session['action'] as $power)
             {
                 if ($power['subject_id'] == -1)
                 {
                     $subjects = $temp_subjects;
                     break;
                 }
                 
                 foreach (explode(',', $power['subject_id']) as $val)
                 {
                     if ($val)
                     {
                         $subjects[$val] = $temp_subjects[$val];
                     }
                 }
             }
         }
         else
         {
            $subjects = C('subject');
         }
        
         return $subjects;
    }

    //解析操作权限范围  property
    private static function separate_action_type()
    {
        $action_type = Fn::sess()->userdata('action_type');
        $action_type = @unserialize($action_type);
        
        return is_array($action_type) ? $action_type : array();
    }
    
    /**
     * 获取操作权限
     * @property
     * @param string $segment 权限模块
     * @param string $item 权限类型(r：读, w：写)
     * @return mixed
     */
    private static function get_action_type_segment($segment = 'question', 
        $item = null)
    {
        $action_types = self::separate_action_type();
        $action_types = isset($action_types[$segment]) ? $action_types[$segment] : array();
        
        return is_null($item) ? $action_types : (isset($action_types[$item]) ? $action_types[$item] : '1');
    }

    //判断权限范围 -- 查看所有
    public static function is_action_type_all($segment, $item, $subject_id = 0)
    {
        //return $this->get_action_type_segment($segment, $item) == '3';
        $action = Fn::sess()->userdata('action');
        $is_action_type_all = false;
        foreach ($action as $power)
        {
            $action_type = @unserialize($power['action_type']);
            if ($subject_id > 0)
            {
                if ($power['subject_id'] == -1
                   || in_array($subject_id, explode(',', $power['subject_id'])))
                {
                    if (!empty($action_type[$segment][$item])
                       && $action_type[$segment][$item] == 3)
                    {
                        $is_action_type_all = true;
                    }
                }
            }
            else 
            {
                if (!empty($action_type[$segment][$item])
                   && $action_type[$segment][$item] == 3)
                {
                    $is_action_type_all = true;
                }
            }
        }
        return $is_action_type_all;
    }
    
    //判断权限范围 -- 只能看所在学科
    public static function is_action_type_subject($segment, $item, 
        $subject_id = 0)
    {
        //return $this->get_action_type_segment($segment, $item) == '2';
        $action = Fn::sess()->userdata('action');
        $is_action_type_subject = false;
        foreach ($action as $power)
        {
            $action_type = @unserialize($power['action_type']);
            if ($subject_id > 0)
            {
                if ($power['subject_id'] == -1
                   || in_array($subject_id, explode(',', $power['subject_id'])))
                {
                    if (!empty($action_type[$segment][$item])
                       && $action_type[$segment][$item] == 2)
                    {
                        $is_action_type_subject = true;
                    }
                }
            }
            else
            {
                if (!empty($action_type[$segment][$item])
                   && $action_type[$segment][$item] == 2)
                {
                    $is_action_type_subject = true;
                }
            }
        }
        
        return $is_action_type_subject;
    }
    
    //判断权限范围 -- 只能看自己创建
    public static function is_action_type_self($segment, $item, $subject_id = 0)
    {
        //return $this->get_action_type_segment($segment, $item) == '1';
        $action = Fn::sess()->userdata('action');
        $is_action_type_self = false;
        foreach ($action as $power)
        {
            $action_type = @unserialize($power['action_type']);
            if ($subject_id > 0)
            {
                if ($power['subject_id'] == -1
                   || in_array($subject_id, explode(',', $power['subject_id'])))
                {
                    if (!empty($action_type[$segment][$item])
                       && $action_type[$segment][$item] == 1)
                    {
                        $is_action_type_self = true;
                    }
                }
            }
            else 
            {
                if (!empty($action_type[$segment][$item])
                   && $action_type[$segment][$item] == 1)
                {
                    $is_action_type_self = true;
                }
            }
        }
        return $is_action_type_self;
    }
}
