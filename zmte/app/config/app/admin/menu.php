<?php

if (! defined('BASEPATH')) exit();

// 导航栏菜单
$config['menu_nav'] = array(
        'menu_default'=>'admin/index/main'
);

// 学生信息管理
$config['menu']['menu_student'] = array(
        'menu_student_list'=>'admin/student/index',
        'menu_student_account'=>'admin/account/index',
        'menu_student_add'=>'admin/student/add',
        'menu_school_list'=>'admin/school/index',
        'menu_region_list'=>'admin/region/index',
        'menu_student_trash'=>'admin/student/index/trash'
);

// 产品信息管理
$config['menu']['menu_product'] = array(
        'menu_product_list'=>'admin/production/index',
        'menu_product_c_list'=>'admin/production_category/index',
        'menu_account_trans'=>'admin/account/transactionw'
);
// 课程信息管理
$config['menu']['menu_course'] = array(
    'menu_course_corslist'=>'admin/course/corslist',
    'menu_course_cmlist' => 'admin/course/cmlist',
    'menu_course_csntlist' => 'admin/course/csntlist',
    'menu_course_ctflist' => 'admin/course/ctflist',
    'menu_cteacher_ctlist' => 'admin/cteacher/ctlist'
);

// 培训机构管理
$config['menu']['menu_traininginstitution'] = array(
    'menu_traininginstitution_tilist'=>'admin/traininginstitution/tilist',
    'menu_traininginstitution_titlist' => 'admin/traininginstitution/titlist',
    'menu_traininginstitution_tiptlist' => 'admin/traininginstitution/tiptlist'
);


// 教师信息管理
$config['menu']['menu_teacher'] = array(
        'menu_invigilate_list'=>'admin/invigilate/index',
        'menu_teacher_download_list'=>'admin/teacher_download/index'
);

// 试题及分类管理
$config['menu']['menu_question'] = array(
        'menu_question_list'=>'admin/question/index',
        /* 'menu_question_external_list' => 'admin/question_external/index', */
        'menu_question_add'=>'admin/question/add',
        'menu_question_count'=>'admin/question/count',
        'menu_question_audit'=>'admin/question_audit/index',
        'menu_question_count_tz'=>'admin/question/count_tz',
        'menu_relate_class'=>'admin/relate_class/index',
        'menu_subject_list'=>'admin/subject/index',
        'menu_subject_dimension_list'=>'admin/subject/subject_dimension_list',
        'menu_class_list'=>'admin/question_class/index',
        'menu_knowledge_list'=>'admin/knowledge/index',
        // 'menu_skill_list' => 'admin/skill/index',
        'menu_subject_category_list'=>'admin/subject_category/index',
        'menu_group_type_list'=>'admin/group_type/index',
        'menu_question_trash'=>'admin/question/index/trash'
// 'menu_question_update' => 'admin/question_update/url_update',
);

// 素质测试相关管理
$config['menu']['menu_diathesis'] = array(
    'menu_vocational_aptitude_list'=>'admin/vocational_aptitude/index',
    'menu_learn_style_list'=>'admin/learn_style/index',
    'menu_vocational_interest_list'=>'admin/vocational_interest/index',
    'menu_profession_list'=>'admin/profession/index',
);

// 试卷、组题规则管理
$config['menu']['menu_exam_paper'] = array(
        'menu_exam_list'=>'admin/exam/index',
        'menu_zmoss_list'=>'admin/zmoss/index',
        'menu_exam_rule_list'=>'admin/exam_rule/index',
        'menu_exam_answer_entry'=>'admin/exam_answer_entry/index',
        'menu_paper_diy'=>'admin/paper_diy/index'
);

// 评估相关管理
$config['menu']['menu_evaluate'] = array(
        'menu_comparison_type'=>'admin/comparison_type/index',
        'menu_evaluate_rule'=>'admin/evaluate_rule/index',
        'menu_evaluate_template'=>'admin/evaluate_template/index/1',
/*     'menu_evaluate_template_complex' => 'admin/evaluate_template/index/',
    'menu_evaluate_template_interview' => 'admin/evaluate_template/index/2',
    'menu_template_interview_complex' => 'admin/evaluate_template/index/3', */

);

// 样卷报告管理
$config['menu']['menu_demo_paper_manage'] = array(
        'menu_demo_exam_config'=>'admin/demo_config/index',
        'menu_has_demo_paper_report'=>'admin/demo_paper/index'
);

// 网站设置
$config['menu']['menu_system'] = array(
        'menu_system_config'=>'admin/setting/index',
        //'menu_dbmanage' => 'admin/dbmanage/backup'
);

// 权限管理
$config['menu']['menu_priv_admin'] = array(
        'menu_cpuser_list'=>'admin/cpuser/index',
        'menu_role_list'=>'admin/role/index',
        'menu_import_cpuser'=>'admin/cpuser/import',
        'menu_admin_log'=>'admin/admin_log/index',
        'menu_editpwd'=>'admin/cpuser/editpwd',
        'menu_cpuser_trash'=>'admin/cpuser/index/?trash=1'
);

// 站点监控
$config['menu']['menu_monitor'] = array(
        'menu_cron_schedule'=>'admin/cron_schedule/index'
);

// 面试试题管理
$config['menu']['menu_interview'] = array(
        'menu_interview_list'=>'admin/interview_question/index',
        'menu_interview_add'=>'admin/interview_question/add',
        'menu_interview_trash'=>'admin/interview_question/index/trash',
        'menu_interview_type'=>'admin/interview_type/index',
        'menu_interview_rule'=>'admin/interview_rule/index',
        'menu_evaluation_option'=>'admin/evaluation_option/index',
        'menu_evaluation_standard'=>'admin/evaluation_standard/index',
        'menu_interview_result'=>'admin/interview_result/import',
        'menu_ruidabei_import'=>'admin/ruidabei_import/import'
);

// ---------菜单项关联权限---------------------------//

$config['purview'] = array(
        'menu_default'=>'',
        // 学生信息管理
        'menu_student_list'=>array(
                'student_index'

        ),
        'menu_student_add'=>'student_add',
        'menu_school_list'=>array(
                'school_index'
        ),
        'menu_region_list'=>array(
                'region_index'
        ),
        'menu_student_trash'=>array(
                'student_index'

        ),
        'menu_student_account'=>array(
                'account_index'
        ),
        // 产品信息管理
        'menu_product_list'=>array(
                'production_index'
        ),
        'menu_product_c_list'=>array(
                'production_category_index'
        ),

        'menu_account_trans'=>array(
                'account_index'
        ),
        // 课程信息管理
        'menu_course_corslist'  => 'course_corslist',
        'menu_course_cmlist'    => 'course_cmlist',
        'menu_course_csntlist'  => 'course_csntlist',
        'menu_course_ctflist'   => 'course_ctflist',
        'menu_cteacher_ctlist'  => 'cteacher_ctlist',

        // 培训机构管理
        'menu_traininginstitution_tilist'   => 'traininginstitution_tilist',
        'menu_traininginstitution_titlist'  => 'traininginstitution_titlist',
        'menu_traininginstitution_tiptlist' => 'traininginstitution_tiptlist',

        // 教师信息管理
        'menu_invigilate_list'=>'invigilate_index',
        'menu_teacher_download_list'=>array(
                'teacher_download_index'
        ),

        // 试题及分类管理
        'menu_question_list'=>array(
                'question_index'
        ),
        'menu_question_count'=>'question_count',
        'menu_question_count_tz'=>'question_count_tz',
        'menu_question_audit'=>array(
                'question_audit_index'
        ),
        'menu_question_add'=>'question_add',
        'menu_relate_class'=>array(
                'relate_class_index'
        ),
        'menu_subject_list'=>array(
                'subject_index'
        ),
        'menu_subject_dimension_list' => 'subject_subject_dimension_list',
        'menu_class_list'=>array(
                'question_class_index'
        )
        ,
        'menu_knowledge_list'=>array(
                'knowledge_index'
        )
        ,
        'menu_skill_list'=>array(
                'skill_list',
                'skill_manage'
        ),
        'menu_subject_category_list'=>array(
                'subject_category_index'
        )
        ,
        'menu_group_type_list'=>array(
                'group_type_index'
        ),
        'menu_question_trash'=>'question_index',
        'menu_question_update'=>'question_update_url',
    
        // 素质测试相关管理
        'menu_vocational_aptitude_list'=>'vocational_aptitude_index',
        'menu_learn_style_list'=>'learn_style_index',
        'menu_vocational_interest_list' => 'vocational_interest_index',
        'menu_profession_list' => 'profession_index',

        // 试卷、组题规则管理
        'menu_exam_list'=>array(
                'exam_index',
                'exam_edit',
                'exam_delete',
                'exam_add',
                'down_exam'
        ),
        'menu_exam_rule_list'=>array(
                'exam_rule_index'
        ),
        'menu_exam_answer_entry'=>array(
                'exam_answer_entry_index'
        ),
        'menu_paper_diy'=>array(
                'paper_diy_index'
        ),
        'menu_zmoss_list'=>array(
            'zmoss_index'
        ),

        // 面试试题管理
        'menu_interview_list'=>array(
                'interview_question_index'
        ),
        'menu_interview_add'=>'interview_question_add',
        'menu_interview_trash'=>array(
                'interview_question_index'
        ),
        'menu_interview_type'=>array(
                'interview_type_index'
        ),
        'menu_interview_rule'=>array(
                'interview_rule_index'
        ),
        'menu_evaluation_option'=>array(
                'evaluation_option_index'
        ),
        'menu_evaluation_standard'=>array(
                'evaluation_standard_index'
        ),
        'menu_interview_result'=>'interview_result_import',
        'menu_ruidabei_import'=>'ruidabei_import_import',

        // 评估管理
        'menu_comparison_type'=>array(
                'comparison_type_index'
        ),
        'menu_evaluate_rule'=>array(
                'evaluate_rule_index'
        ),
        'menu_evaluate_template'=>array(
                'evaluate_template_index'
        ),
        'menu_evaluate_template_complex'=>array(
                'evaluate_template_manage'
        ),
        'menu_evaluate_template_interview'=>array(
                'evaluate_template_index'
        ),

        // MINI测
        'menu_demo_exam_config'=>array(
                'demo_config_index'
        ),
        'menu_has_demo_paper_report'=>array(

                'demo_paper_index'
        ),

        // 系统设置
        'menu_system_config'=>'system_config',
        'menu_dbmanage' => 'dbmanage_backup,dbmanage_restore',

        // 权限管理
        'menu_cpuser_list'=>array(
                'cpuser_index'
        ),
        'menu_role_list'=>array(
                'role_index'
        ),
        'menu_import_cpuser'=>array(
                'import_cpuser'
        ),
        'menu_admin_log'=>array(
                'admin_log_index',
                'admin_log_del'
        ),
        'menu_editpwd'=>'',
        'menu_cpuser_trash'=>array(
                'cpuser_index'
        ),

        // 站点监控
        'menu_cron_schedule'=>array(
                'cron_schedule_index'
        )
);
