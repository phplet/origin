$(document).ready(function(){
    // 表单验证
    $("#rule_form").validate({
        submitHandler:function(form){
       $("#bb").val($('#body').html());
        	
            form.submit();
        },
        rules: {
            rule_name:'required',
            subject_id: "required",
            grade_id: "required",
            class_id: "required",
            test_way: "required",
            'knowledge_id[]':'required',
            difficulty: {
                required:true,
                range:[0,100]
            },
            ques_num1:{
                required:true,
                digits:true
            },
            ques_num2:{
                required:true,
                digits:true
            }
        },
        groups:{
            ques_num:"ques_num1 ques_num2"
        },
        messages: {
            rule_name:'请填写规则名称',
            subject_id: "请选择学科",
            grade_id: "请选择年级",
            class_id: "请选择类型",
            test_way: "请选择考试方式",
            'knowledge_id[]':'请选择知识点范围',
            difficulty: {
                required:'请填写平均难易度',
                range:'请填写0-100的数字'
            },
            ques_num1:{
                required:'请填写单选试题数量',
                digits:'试题数量只能是数字'
            },
            ques_num2:{
                required:'请填写多选试题数量',
                digits:'试题数量只能是数字'
            }
        }
    });
    // 范围知识点选择
    $('#knowledge_select').click(function(){
        if (!$('#subject_id').val() || !$('#grade_id').val() || !$('#class_id').val()) {
            alert('请先选择学科、年级、类型');return;
        }
        knowledge_select();
    });
    // 绑定已选择知识点的操作，重置重点知识点
    $('#knowledge_list').on('click',' input:checkbox', function(){
        var objs = $('#knowledge_list').find('input:checkbox');
        var ids = '';
        for (var i=0; i<objs.length; i++) {
            if (objs[i].checked) {
                ids += (ids ? ',' : '') + objs[i].value;
            }
        }
        $('#knowledge_ids').val(ids);
        reset_knowledge_rule_list(this);
    });

    $('#subject_id').change(function(){
        toggle_subject_type();
    });

    $('#grade_id').change(function(){
        set_question_class_option();
        toggle_subject_type();
    });

    $('#class_id').change(function(){
        toggle_subject_type();
    });

    $('#knowledge_add').click(function(){
        add_rule();
    });
    
    // 删除重点知识点
    $('#knowledge_rule_list').on('click', '#knowledge_del',function(){
        $(this).parentsUntil('#knowledge_rule_list').slideUp('slow',function(){
            $(this).remove();
        });
    });
    
    // 绑定重点知识点选择框操作：统计重点知识点试题数
    $('#knowledge_rule_list').on('change','select.knowledge_parent',function(){
        next_knowledge($(this));

        //联动认知过程
        if ($(this).val() == '') {
        	$(this).parent().find('span.know_process').hide();
        } else {
        	$(this).parent().find('span.know_process').show();
        }
    });

    // 绑定重点知识点选择框操作：统计重点知识点试题数
    $('#knowledge_rule_list').on('change','select.knowledge_child',function(){
    	var $thiz = $(this),
    		cur_kp = $thiz.next('span').find('input:checked').val();
    	
        var sels = $('div.knowledge_rule').find('select.knowledge_child');
        for(var i=0; i<sels.length; i++) {
            if (sels[i] === this) continue;
            if (sels[i].value && sels[i].value==this.value && $(sels[i]).next('span').find('input:checked').val() == cur_kp) {
                alert('该重点知识点已存在');
                this.selectedIndex = 0;
                return;
            }
        }
        question_count(2,this);
    });

    $('#knowledge_rule_list').on('change','select.knowledge_parent',function(){
        if ( ! $(this).val()) return;
        question_count(2,$(this).siblings('select')[0]);
    });
    
    // 检查重点知识点试题数量是否填写正确。并统计总数。
    $('#knowledge_rule_list').on('blur', 'input',function(){
    	if ($(this).attr('type') != 'text') {
    		return false;
    	}
    	
        var value = $(this).val().trim();
        if (value) {
            max_value = $(this).parent().next().find('span').text();
            max_value = parseInt(max_value.replace(/.*?\[(\d+)\]/, "$1"));
            if (isNaN(max_value)) max_value = 0;
            value = parseInt(value);
            if (isNaN(value)) {
                alert('请填写有效数字');
                return;
            }
            if (value > max_value) {
                alert('您填写的数字不能多于总数');
                $(this).val('').focus();
                return;
            }
            calculate_count();
        }        
    });
    
    //认知过程联动
    $('#knowledge_rule_list').on('click', '.input_know_process',function(){
    	var $parent = $(this).parent().parent(),
    		$select_parent = $parent.find('select').eq(0),
    		$select_child = $parent.find('select').eq(1);
    	
    	if ($select_child.val() != '') {
    		$select_child.change();
    	} else if($select_parent.val() != '') {
    		$select_parent.change();
    	}
    });
    
    // 初始化数据
    set_question_class_option();
    toggle_subject_type();
    init_knowledge();

    
    // 初始化统计数量
    $('#knowledge_rule_list').find('select.knowledge_child').each(function(){
        question_count(2, this, 'js');
    });

});