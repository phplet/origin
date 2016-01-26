<?php

if (! defined ( 'BASEPATH' )) exit ();
class Role extends A_Controller

{

    public function __construct()
    {
        parent::__construct ();
    }

    /**
     * ription 角色列表
     *
     * @author
     *
     * @final
     *
     * @param int $trash
     *            是否回收站
     * @param int $group_id
     */
    public function index()
    {
        if (! $this->check_power ( 'role_manage' )) return;

        // 查询条件
        $where = array ();
        $param = array ();
        $search = array ();

        $trash = intval ( $this->input->get ( 'trash' ) );
        $search ['trash'] = $trash;
        if ($trash)
        {
            $where [] = "is_delete=1";
            $param [] = "trash=1";
        }

        if ($search ['group_id'] = intval ( $this->input->get ( 'group_id' ) ))
        {
            $where [] = "group_id='$search[group_id]'";
            $param [] = "group_id=$search[group_id]";
        }

        $where = $where ? implode ( ' AND ', $where ) : ' 1 ';

        // 统计数量
        $sql = "SELECT COUNT(*) nums FROM {pre}role WHERE $where";
        $res = $this->db->query ( $sql );
        $row = $res->row_array ();
        $total = $row ['nums'];

        /*
         * 分页读取数据列表，并处理相关数据
         */
        $size = 15;
        $page = isset ( $_GET ['page'] ) && intval ( $_GET ['page'] ) > 1 ? intval ( $_GET ['page'] ) : 1;
        $offset = ($page - 1) * $size;
        if ($total)
        {
            /* 查看所有角色下的管理员 */
            $sql = "SELECT * FROM {pre}admin_role group by role_id";
            $res = $this->db->query ( $sql );
            $temp = array ();
            foreach ( $res->result_array () as $row )
            {
                $temp [$row ['role_id']] = $row ['admin_id'];
            }

            $sql = "SELECT * FROM {pre}role
                    WHERE $where ORDER BY role_id DESC LIMIT $offset,$size";
            $res = $this->db->query ( $sql );
            $list = array ();
            foreach ( $res->result_array () as $row )
            {
                /* 判断该角色是否已分配管理员 */
                $row ['is_delete'] = empty ( $temp [$row ['role_id']] ) ? 1 : 0;

                $list [] = $row;
            }
        }

        $data ['list'] = $list;
        $data ['search'] = $search;
        $data ['priv_delete'] = $this->check_power ( 'cpuser_delete', FALSE );
        $data ['priv_role'] = $this->check_power ( 'cpuser_role', FALSE );
        $data ['priv_import_cpuser'] = $this->check_power ( 'import_cpuser', FALSE );

        // 分页
        $purl = site_url ( 'admin/role/index' ) . ($param ? '?' . implode ( '&', $param ) : '');
        $data ['pagination'] = multipage ( $total, $size, $page, $purl );

        // 模版
        $this->load->view ( 'role/index', $data );
    }

    /**
     * ription 添加角色
     *
     * @author
     *
     * @final
     *
     */
    public function add()
    {
        if (! $this->check_power ( 'role_manage' )) return;

        $data ['subjects'] = C ( 'subject' );

        // 模版
        $this->load->view ( 'role/add', $data );
    }

    /**
     * ription 编辑角色
     *
     * @author
     *
     * @final
     *
     * @param int $id
     *            角色id
     */
    public function edit($id = 0)
    {
        if (! $this->check_power ( 'role_manage' )) return;

        $id = intval ( $id );
        $id && $role = RoleModel::get_role ( $id );
        if (empty ( $role ))
        {
            message ( '角色不存在' );
            return;
        }
        $data ['role'] = $role;

        // 模版
        $this->load->view ( 'role/edit', $data );
    }

    /**
     * ription 添加角色[保存数据库操作]
     *
     * @author
     *
     * @final
     *
     * @param string $role_name
     *            角色名
     * @param string $remark
     *            角色备注
     */
    public function create()
    {
        if (! $this->check_power ( 'role_manage' )) return;

        $data ['role_name'] = trim ( $this->input->post ( 'role_name' ) );
        $data ['remark'] = trim ( $this->input->post ( 'remark' ) );

        if (empty ( $data ['role_name'] ))
        {
            message ( '角色名不能为空！' );
            return;
        }

        $data ['action_list'] = '';
        $this->db->insert ( 'role', $data );

        admin_log ( 'add', 'role', $this->db->insert_id () );
        message ( '角色名添加成功', 'admin/role/index' );
    }

    /**
     * ription 更新角色[保存数据库操作]
     *
     * @author
     *
     * @final
     *
     * @param int $role_id
     *            角色id
     * @param string $role_name
     *            角色名
     * @param string $remark
     *            角色备注
     */
    public function update()
    {
        if (! $this->check_power ( 'role_manage' )) return;
        $session = $this->session->userdata;
        if (! $session ['is_super'])
        {
            message ( '您没有权限！' );
            return;
        }

        $role_id = intval ( $this->input->post ( 'role_id' ) );
        $data ['role_name'] = trim ( $this->input->post ( 'role_name' ) );
        if (empty ( $data ['role_name'] ))
        {
            message ( '角色名不能为空！' );
            return;
        }

        $query = $this->db->select ( 'role_id' )->get_where ( 'role', array ('role_name' => $data ['role_name'],'role_id <>' => $role_id
        ) );
        if ($query->num_rows ())
        {
            message ( '角色名已存在！' );
        }

        $data ['remark'] = trim ( $this->input->post ( 'remark' ) );

        $this->db->update ( 'role', $data, array ('role_id' => $role_id
        ) );
        admin_log ( 'edit', 'role', $role_id );
        message ( '修改成功', 'admin/role/index' );
    }

    /**
     * ription 删除角色
     *
     * @author
     *
     * @final
     *
     * @param int $role_id
     *            角色id
     */
    public function delete($id = 0)
    {
        if (! $this->check_power ( 'role_delete' )) return;

        $id = intval ( $id );

        $this->db->delete ( 'role', array ('role_id' => $id
        ) );
        admin_log ( 'remove', 'role', $id );

        message ( '删除成功', 'admin/role/index/' );
    }

    /**
     * ription 给角色设置操作权限
     *
     * @author
     *
     * @final
     *
     * @param int $id
     *            角色id
     * @param array $priv
     *            权限节点
     * @param int $r_action_type
     *            读操作
     * @param int $w_action_type
     *            写操作
     */
    public function priv($id = 0)
    {
        if (! $this->check_power ( 'cpuser_role' )) return;

        $id = intval ( $id );
        $id && $role = RoleModel::get_role ( $id );
        if (! $role)
        {
            message ( '角色不存在' );
            return;
        }
        $sql = "select class_id, class_name from {pre}question_class";
        $res = $this->db->query ( $sql )->result_array ();
        $new_q_type = array ();
        foreach ( $res as $row )
        {
            $new_q_type [$row ['class_id']] = $row ['class_name'];
        }
        if ($this->input->post ( 'dosubmit' ))
        {
            $privs = $this->input->post ( 'priv' );
            if (empty ( $privs )) $privs = array ();
            $subject_id_arr = array ();
            $grade_id_arr = array ();
            $q_type_id_arr = array ();
            $arr = array ();
            // pr($privs,1);
            $subject_id_count = 0;

            // 学科为空，过滤掉group_question,group_exam_paper
            if (empty ( $privs ['group_subject'] ))
            {
                unset ( $privs ['group_question'] );
                unset ( $privs ['group_exam_paper'] );
            }

            foreach ( $privs as $key => $val )
            {
                // 单独区分出学科管理
                if ($key == 'group_subject')
                {
                    $subject_id_arr [] = count ( ( array ) $val ) == count ( C ( 'subject' ) ) ? '-1' : implode ( ',', ( array ) $val );
                }
                elseif ($key == 'group_grade')
                {
                    $grade_id_arr [] = count ( ( array ) $val ) == count ( C ( 'grades' ) ) ? '-1' : implode ( ',', ( array ) $val );
                }
                elseif ($key == 'group_q_type')
                {
                    $q_type_id_arr [] = count ( ( array ) $val ) == count ( $new_q_type ) ? '-1' : implode ( ',', ( array ) $val );
                }
                else
                {
                    $arr [] = implode ( ',', ( array ) $val );
                }
            }

            // 题库读写权限
            $r_action_type = intval ( $this->input->post ( 'r_action_type' ) );
            $w_action_type = intval ( $this->input->post ( 'w_action_type' ) );

            $r_action_type = ($r_action_type < 1 || $r_action_type > 3) ? 1 : $r_action_type;
            $w_action_type = ($w_action_type < 1 || $w_action_type > 3) ? 1 : $w_action_type;

            $action_type = array ('question' => array ('r' => $r_action_type,'w' => $w_action_type
            )
            );

            $this->db->update ( 'role', array ('subject_id' => implode ( ',', $subject_id_arr ),'grade_id' => implode ( ',', $grade_id_arr ),'q_type_id' => implode ( ',', $q_type_id_arr ),'action_list' => implode ( ',', $arr ),'action_type' => serialize ( $action_type )
            ), array ('role_id' => $id
            ) );
            admin_log ( 'edit', 'role_priv', $id );
            message ( '权限设置成功', 'admin/role/priv/' . $id );
        }
        else
        {
            // 读写权限
            $data ['action_type'] = array ('1' => '自己创建','2' => '所在学科','3' => '所有学科'
            );

            // 所有学科
            $data ['subject'] = C ( 'subject' );
            $data ['grade'] = C ( 'grades' );

            $data ['q_type'] = $new_q_type;

            // $role['privs_subject'] = explode(',', $role['subject_id']);
            $role ['privs_subject'] = $role ['subject_id'] == '-1' ? array_keys ( $data ['subject'] ) : explode ( ',', $role ['subject_id'] );

            $role ['privs_grade'] = $role ['grade_id'] == '-1' ? array_keys ( $data ['grade'] ) : explode ( ',', $role ['grade_id'] );

            $role ['privs_q_type'] = $role ['q_type_id'] == '-1' ? array_keys ( $data ['q_type'] ) : explode ( ',', $role ['q_type_id'] );

            $role ['privs'] = explode ( ',', $role ['action_list'] );
            $data ['roles'] = C ( 'roles', 'app/admin/roles' );

            $action_type = @unserialize ( $role ['action_type'] );
            $role ['action_type'] = is_array ( $action_type ) ? $action_type : array ();
            $data ['user'] = $role;

            // 模版
            $this->load->view ( 'role/priv', $data );
        }
    }
}
