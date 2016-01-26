$(document).ready(function(){

	if (typeof(subject_ids) == "undefined")
	{
		var is_knowledge_required = true;
		var is_group_type_required = false;
	} else if (subject_ids == 3) {
        // 小学
        if (typeof(is_primary) != "undefined" && is_primary) {
            var is_knowledge_required = true;
            var is_group_type_required = false;
        } else {
            var is_knowledge_required = false;
            var is_group_type_required = false;
        }
	} else {
		var is_knowledge_required = true;
		var is_group_type_required = false;
	}

	if (typeof(cloze_type) == "undefined" ) {
		var is_sort_min = 0;
	} else {
		var is_sort_min = 1;
	}

    // 真题
    if ($('#is_original').val() == 2) {
    	if (paper_diy==1) {
    		var is_exam_question = 1;
    	} else {
    		var is_exam_question = 0;
    	}
        
    } else if($('#is_original').val() == 3) {
    	if (paper_diy==1) {
    		var is_exam_question = 1;
    	} else {
    		var is_exam_question = 0;
    	}
    } else {
    	if (paper_diy==1) {
    		var is_exam_question = 1;
    	} else {
    		var is_exam_question = 0;
    	}
    }

    // 表单验证
    $("#question_form").validate({

        submitHandler:function(form){
            var qtype = getRadio(null, 'qtype') || $('#qtype').val();

            if (is_group == true || qtype == 10 || qtype == 11) {
                form.submit();
            } else {
                if (qtype == 3 || qtype == 9) {
                    if (check_input_answer()) {
                        form.submit();
                    }
                } else if (qtype == 5 || qtype == 6 || qtype == 13) {
                	form.submit();
                }
                else if (qtype == 14)
            	{
                	if (check_option_group2()) {
                        form.submit();
                    }
            	}
                else {
                    if (check_option_group()) {
                        form.submit();
                    }
                }
            }          
        },
        rules: {
            subject_id: "required",
            start_grade: "required",
            end_grade: "required",
            class_id_validate: "required",
            /*'skill_id[]': "required",*/
            'knowledge_id[]':{
            	required : is_knowledge_required
            },
            'group_type_id[]':{
            	required : is_group_type_required
            },
            'option_file[0]':{
            	accept:"jpe?g|gif|png"
            },
            'option_file[1]':{
           	    accept:"jpe?g|gif|png"
            },
            'option_file[2]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[3]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[4]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[5]':{
       	        accept:"jpe?g|gif|png"
            },
            'option_file[6]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[7]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[8]':{
                accept:"jpe?g|gif|png"
            },
            'option_file[9]':{
                accept:"jpe?g|gif|png"
            }, 
            // 真题 原创
            is_original:{
                required:is_exam_question
            },
            exam_year:{
                required:is_exam_question
            },
            simulation:{
                required:is_exam_question
            },
            score_factor:{
                required:true,
                range:[1,10]
            },
            title:'required',
            picture:{
                accept:"jpe?g|gif|png"
            },
            sort:{
            	min:is_sort_min
            },
            'test_way[]':"required"
        },

        groups:{
            grade_id:"start_grade end_grade"
        },

        messages: {
            subject_id: "请选择学科",
            start_grade: "请选择年级区间",
            end_grade: "请选择年级区间",
            class_id_validate: '请选择试题类型并填写相应难易度',
            //'skill_id[]':'请选择技能',
            'knowledge_id[]':{
            	required : '请选择知识点'
            },
            'option_file[0]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[1]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[2]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[3]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[4]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[5]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[6]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[7]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[8]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'option_file[9]':{
            	
            	accept : '只能上传jpg,jpeg,gif,png格式图片'
            },
            'group_type_id[]':{
            	required : '请选择信息提取方式'
            },
            is_original:{
                required : '请选择试题类型'
            },
            exam_year:{
                required : '请选择年份'
            },
            simulation:{
                required : '请输入来源'
            },
            score_factor:{
                required:'请填写分值系数'
            },
            title:'请填写题目内容',
            picture:{
                accept: '只能上传jpg,jpeg,gif,png格式图片'
            },
            sort:{
            	min:'请输入大于0的序号'
            },
            'test_way[]':"请选择考试方式"
        }
    });

    jQuery.validator.addMethod("accept",function(t,e,i)
    		{var a,r,n="string"==typeof i?i.replace(/\s/g,"").replace(/,/g,"|"):"image/*",
    				s=this.optional(e);if(s)return s;
    				if("file"===$(e).attr("type")&&(n=n.replace(/\*/g,".*"),
    						e.files&&e.files.length))for(a=0;e.files.length>a;a++)
    							if(r=e.files[a],!r.type.match(RegExp(".?("+n+")$","i")))return!1;return!0},
    							jQuery.format("Please enter a value with a valid mimetype."))
    							
    // 绑定学科选择的操作
    $('#subject_id').change(function(){
        set_question_class();
        //set_skill();
    });

    // 按年级区间动态调整试题类型
    $('#start_grade,#end_grade').change(function(){
        set_question_class();
    }); 

    // 题型切换
    $('input:radio[name=qtype]').click(function(){
        toggle_qtype();
    });

    $(':checkbox[name="test_way[]"]').change(function(){
        toggle_qtype();
    });

    // 知识点选择
    $('#knowledge_select').click(function(){
        knowledge_select();
    });
    
 // 对已选择的知识点操作，调整隐藏项knowledge_ids的值
    $('#knowledge_list').on('click', 'input:checkbox', function(){
        var objs = $('#knowledge_list').find('input:checkbox');
        var ids = '';
        for (var i=0; i<objs.length; i++) {
            if (objs[i].checked) {
                ids += (ids ? ',' : '') + objs[i].value;
            }
        }
        $('#knowledge_ids').val(ids);
    });
    
    // 信息提取方式选择
    $('#group_type_select').click(function(){
    	group_type_select();
    });
    
 // 对已选择的信息提取方式操作，调整隐藏项group_type_ids的值
    $('#group_type_list').on('click', 'input:checkbox', function(){
        var objs = $('#group_type_list').find('input:checkbox');
        var ids = '';
        for (var i=0; i<objs.length; i++) {
            if (objs[i].checked) {
                ids += (ids ? ',' : '') + objs[i].value;
            }
        }
        $('#group_type_ids').val(ids);
    });
    
    // 方法策略选择
    $('#method_tactic_select').click(function(){
    	method_tactic_select();
    });

    // 绑定验证试题类型的有效性
    $('#class_list').on('click', 'input:checkbox',function(){
        set_class_validate();
    });

    // 初始化难易度输入框的样式
    $('#class_list input').each(function(){
        if ($(this).val() != '难易度') {
            $(this).removeClass('gray_input');
        }
    });

    // 难易度输入框操作
    $('#class_list input').bind('focus',function(){
        toggle_input_sytle('focus', this, '难易度');
    }).bind('blur',function(){
        toggle_input_sytle('blur', this, '难易度');
        set_class_validate();
    });

    // 解答题 作文 添加图片
    $('#add_picture').click(function(){
        var picture_index = $('.picture_title').last().attr('pindex');
        picture_index++;

        var add_picture_str = '<p><span class="picture_title" pindex="' + picture_index +'">图片';
        add_picture_str += picture_index + '：</span>&nbsp;&nbsp;<input type="file" name="picture[]" class="txtbox" />&nbsp;&nbsp;';
        add_picture_str += '<a href="javascript:void(0);">删除</a><label for="picture[]" class="error" style="display:none"></label></p>';
        
        $('#wrap_picture').append(add_picture_str);
    });
    
    // 初始化项目
    init_knowledge();
    init_group_type();
    init_method_tactic();
    //set_skill();    
    set_question_class();
    if (is_group !== true) {
        toggle_qtype();
    }
});