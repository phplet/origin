
// 检查选项填写是否有效
function check_option_group() {
    // 验证选项
    qtype = $('input:radio[name=qtype]')[0];
    qtype7 = $('input:radio[name=qtype]')[3];

    if (typeof(qtype7) == "undefined") {
    	qtype7 = qtype;
	}

    if ((typeof(is_primary) != "undefined" && is_primary) 
    		|| $("#subject_id").val() == 3)
    {
        var radio_valid_nums = 3;
    }
    else if (qtype.checked == true){
        var radio_valid_nums = 2;
    }
    else if (qtype7.checked == true)
	{
    	var radio_valid_nums = 3;
	}
    else
    {
         var radio_valid_nums = 4;
    }

    var parent_td = $('#option_container');
    var opt_content = $('input[name="option[]"]');
    
    if (qtype.checked == true || qtype7.checked == true) {
        var op_answers = parent_td.find('input:radio[name^=answer]');
        var max_length = 4;
    } else {
        var op_answers = parent_td.find('input:checkbox[name^=answer]');
        var max_length = 10;
    }

    var valid_opts   = 0;  // 有效选项
    var valid_answer = 0;  // 有效答案
    for(var i=0; i<op_answers.length; i++) {
        var content = UE.getEditor('option_'+i).getContent();
        if (content.trim().length) {
            $(opt_content[i]).val(content);
            valid_opts++;
            if (op_answers[i].checked == true) {
                valid_answer++;
            }
        }                        
    }

    var group_valid = true;

    if (qtype.checked == true 
    		|| qtype7.checked == true) 
    {
        // 单选
        if (valid_opts < radio_valid_nums 
        		|| valid_answer < 1) 
        {
            group_valid = false;
        }                    
    } 
    else 
    {
        // 多选
        if (valid_opts < 4 || valid_answer < 1) 
        {
        	group_valid = false;
        }   
    }

    if (group_valid == false) 
    {
    	$('#dosubmit').attr('disabled', false);
    	
        parent_td.find('div.group_notice').addClass('height_light');
       
        parent_td.find('input,textarea').bind('blur',function(){
            check_option_group(qtype);
        });
    } 
    else 
    {
        parent_td.find('div.group_notice').removeClass('height_light');
    }

    return group_valid;
}

function check_option_group2() {
    // 验证选项
    qtype = $('input:radio[name=qtype]:checked').val();
    if (typeof(qtype) == "undefined" || qtype != 14) {
    	return false;
	}
    
    var parent_td = $('#option_container');
    var opt_content = $('input[name="option[]"]');

    var _result = true;
    var _options = 0;
    var op_answers = parent_td.find('input[name^=score_coefficient]');
    
    for(var i=0; i < op_answers.length; i++) {
        var content = UE.getEditor('option_'+i).getContent();
        if (content.trim().length) 
        {
            $(opt_content[i]).val(content);
            if (op_answers.eq(i).val() == ''
            	|| isNaN(op_answers.eq(i).val()) 
        		|| op_answers.eq(i).val() < 0 
        		|| op_answers.eq(i).val() > 100)
        	{
            	alert('请输入第'+(i+1)+'个选项得分系数');
            	op_answers.eq(i).focus();
            	_result = false;
            	return;
        	}
            
            _options++;
        }                        
    }
    
    if (_options < 1)
	{
    	alert('请输入试题选项及选项得分系数');
    	op_answers.eq(0).focus();
    	_result = false;
	}

    return _result;
}

// 检查填空项和答案是否匹配
function check_input_answer() {
    var question_title = window.question_editor ? window.question_editor.getContent() : '';

    if ($('#input_answer_tr').css('display') == 'none') {
        var input_answer = window.editor_answer ? window.editor_answer.getContent() : ''
    } else {
        var input_answer = $('#input_answer').val();
    }

    var matches = question_title.match(/（[\s\d|&nbsp;]*）/g); ///（\s*[\d]+\s*）/g
    if (matches == null) {
        alert('请在题目内容中设置填空项。');
        return false;
    }
    for(var i=0; i<matches.length; i++) {
        var index = matches[i].match(/\d+/);
        if (matches.length == 1 && ! index) continue;
        if (! index || index[0]!=i+1) {
            alert('请按顺序填写填空项中的数字【第'+(i+1)+'个括号：'+matches[i]+'】');
            return false;
        }
    }
    var line_num = 0;
    var lines = input_answer.split("\n");
    for (var i=0; i<lines.length; i++) {
        if (lines[i].trim().length > 0) {
            line_num++;
        }
    }
    
    if (line_num != matches.length && $('input[name^="test_way"]:checked').val() == 1) {
        alert('答案数和填空项数目不匹配');
        return false;
    }
    
    return true;
}

// 按选择的年级区间，显示相应年级的类型、难易度填写框
function set_question_class() {
    var start_grade = parseInt($('#start_grade').val());
    var end_grade = parseInt($('#end_grade').val());
    var subject_id = parseInt($('#subject_id').val());
    if (isNaN(start_grade) || isNaN(end_grade) || start_grade > end_grade) return;
    if (isNaN(subject_id)) subject_id = 0;
    for (var i=1; i<=12; i++) {
        if (i < start_grade || i > end_grade) {
            $('#grade'+i+'_class').hide();
        } else {
            $('#grade'+i+'_class').show();
            if (i >= 11) {
                if ( subject_id>=1 && subject_id <=3 ) {
                    $('#grade'+i+'_class').find('select').show();
                } else {
                    $('#grade'+i+'_class').find('select').hide();
                }
            }            
        }        
    }
    set_class_validate();
}

// 验证填写的年级类型、难易度等填写是否有效
function set_class_validate() {
    var class_checked = false;
    var grade_class_list = $('#class_list').find('div:visible');
    var first_check = grade_class_list.first().find('input:checked');
    var last_check = grade_class_list.last().find('input:checked');
    class_checked = first_check.length && last_check.length;

    if (class_checked) {
        var checked_arr = grade_class_list.find('input:checked');
        for (var i=0; i<checked_arr.length; i++) {
            var input = $(checked_arr[i]).nextUntil('input[type=text]').next();
            var diff_value = parseInt(input.val().trim());
            if (isNaN(diff_value) || diff_value<1 || diff_value>100) {
                $('#class_id_validate')[0].checked = false;
                $('label[for=class_id_validate]').show().html('请完整填写相应类型的难易度[1-100]');
                return;
            }
        }
        $('#class_id_validate')[0].checked = true;
        $('label[for=class_id_validate]').hide();
    } else {
        $('#class_id_validate')[0].checked = false;
        $('label[for=class_id_validate]').show().html('首尾年级类型必须选择');
    }
}

// 按选择的学科，ajax方式获取技能
function set_skill() {
	if (typeof ajax_url_skill == 'undefined') {return;}
    var subject_id = $('#subject_id').val();
    if (subject_id == '') return;
    $.post(
        ajax_url_skill,
        {subject_id:subject_id},
        function(data){
            var str = '';  
            if (typeof(skill_ids) == 'undefined') skill_ids = [];
            for (var i=0; i<data.length; i++) {                
                var checked = in_array(data[i]['id'], skill_ids) ? ' checked' : '';
                str += '<input type="checkbox" name="skill_id[]" id="skill'+data[i]['id']+'" value="'+data[i]['id']+'"'+checked+' /> <label for="skill'+data[i]['id']+'">'+data[i]['skill_name']+'</label>';            
            }
            $('#skill_list').html(str);
        },
        'json'
    );
}

// 是否机考
function computer_based_testing() 
{
    if ($(":checkbox[name^='test_way']:checked").val() != 2
    		&& $("#group_test_way").val() != 2)
	{
    	return true;
	}
    
    return false;
}

// 单选、多选切换
function toggle_qtype() {
    var qtypes = $('input:radio[name=qtype]');
    var is_computer_based_testing = computer_based_testing();

    if (qtypes.length) {
        var qtype = getRadio(null, 'qtype');
        $('#input_reference_answer_tr').hide();

        if (qtype == 3) {
            $('#options').hide();
            $('#input_answer_notice').show();
            $('#knowledges').show();
            
            if (!is_computer_based_testing) {
                $('#input_answer_tr_ue').show();
                $('#input_answer_tr').hide();
            } else {
                $('#input_answer_tr_ue').hide();
                $('#input_answer_tr').show();
            }
        
        } else if (qtype == 10) {
            $('#options').hide();
            $('#input_answer_tr').hide();
            $('#input_answer_tr_ue').hide();
            $('#input_answer_notice').hide();
            
            if ($('input[name="is_parent"]:checked').val() == 1)
        	{
        		$('#knowledges').hide();
        		$('#input_reference_answer_tr').hide();
        	}
        	else
        	{
        		$('#knowledges').show();
        		$('#input_reference_answer_tr').show();
        	}
        } 
        else if (qtype == 11) {
            $('#options').hide();
            $('#input_answer_tr').hide();
            $('#input_answer_notice').hide();
            $('#knowledges').hide();
            $('#input_reference_answer_tr').show();
        } else {
            $('#input_answer_tr').hide();
            $('#input_answer_tr_ue').hide();
            $('#input_answer_notice').hide();
            
            var parent_td = $('#options').show();
            $('#knowledges').show();

            if (qtype == 2 || qtype == 14) {
                $('#div_option4').show();
                $('#div_option5').show();
                $('#div_option6').show();
                $('#div_option7').show();
                $('#div_option8').show();
                $('#div_option9').show();
            }

            if (qtype == 1) {
                parent_td.find('input:radio').attr('disabled', false).show();
                parent_td.find('input:checkbox').attr('disabled', true).hide();
                parent_td.find('div.extend_option').hide().find('input:file,textarea').attr('disabled', true);
                parent_td.find('label').eq(1).hide();
                $('#div_option4').hide();
                $('#div_option5').hide();
                $('#div_option6').hide();
                $('#div_option7').hide();
                $('#div_option8').hide();
                $('#div_option9').hide();
            } else if (qtype == 7){
                parent_td.find('input:radio').attr('disabled', false).show();
                parent_td.find('input:checkbox').attr('disabled', true).hide();
                parent_td.find('div.extend_option').hide().find('input:file,textarea').attr('disabled', true);
                parent_td.find('label').eq(1).hide();
                $('#div_option5').hide();
                $('#div_option6').hide();
                $('#div_option7').hide();
                $('#div_option8').hide();
                $('#div_option9').hide();
            } else {
                parent_td.find('input:radio').attr('disabled', true).hide();
                parent_td.find('input:checkbox').attr('disabled', false).show();
                parent_td.find('div.extend_option').show().find('input:file,textarea').attr('disabled', false);
                parent_td.find('label').eq(0).hide();
            }
            
            if (qtype == 14)
        	{
            	$("input[name^=answer]").hide();
            	$('span[name="score_coefficient"]').show();
            	$('input[name^="score_coefficient"]').attr('disabled', false);
        	}
            else
        	{
            	$('span[name="score_coefficient"]').hide();
            	$('input[name^="score_coefficient"]').attr('disabled', true);
        	}
        }
    }    
}

// 知识点选择弹出框
function knowledge_select() {
    var subject_id = $('#subject_id').val();
    
    if (subject_id == 11)
	{
    	subject_id = '';
    	$("span[id=subject_id_list] input[name='subject_str[]']:checked").each(function(){
    		subject_id += ',' + $(this).val();
    	});
    	
    	if (subject_id.length > 0)
        {
    		subject_id = subject_id.substr(1);
        }
	}
    
    if (subject_id == '') {
        alert('请选择学科');
        return;
    }
    
    var input_knowledge_ids = $('#knowledge_ids').val();
    var input_know_process = $('#k_know_process').html();
    $.post(
        ajax_url_knowledge_select,
        {
    		subject_id:subject_id,
    		knowledge_ids:input_knowledge_ids,
    		know_process:input_know_process,
    		is_question_mode:(typeof is_question_mode != 'undefined' ? is_question_mode : 0)
		},
        function(data){
            var __widowWidth = $(window).width();
            var __left = (__widowWidth - 800)/2 + 'px';
            $.blockUI({
                theme:true,
                message:data,
                themedCSS:{background:"#fff",width:"800px",left:__left,top:'10%',overflow:'auto'}
            });
            $(".close").click($.unblockUI);
            $('.blockOverlay').click($.unblockUI);
        },
        'html'
    );
}

// 初始化知识点
function init_knowledge() {
    var subject_id = $('#subject_id').val();
    
    if (subject_id == 11)
	{
    	subject_id = '';
    	$("span[id=subject_id_list] input[name='subject_str[]']:checked").each(function(){
    		subject_id += ',' + $(this).val();
    	});
    	
    	if (subject_id.length > 0)
        {
    		subject_id = subject_id.substr(1);
        }
	}
    
    var input_knowledge_ids = $('#knowledge_ids').val();
    var _is_question_mode = typeof is_question_mode != 'undefined' ? is_question_mode : 0;
    var _knowledge_know_process = typeof knowledge_know_process != 'undefined' ? knowledge_know_process : {};
    if (input_knowledge_ids) {
        $.post(
            ajax_url_knowledge_init,
            {subject_id:subject_id,knowledge_ids:input_knowledge_ids},
            function(data){
                var str = '';
                var know_process_cache = {};
                for (var i=0; i<data.length; i++) {
                    var parent = data[i]['children'];
                    str += '<div><span class="font_4" pkid="'+data[i]['id']+'">'+data[i]['knowledge_name']+'('+data[i]['ques_num']+'['+data[i]['relate_ques_num']+'])</span>：';
                    for (j=0; j<parent.length; j++) {
                        var checked = in_array(parent[j]['id'], knowledge_ids) ? ' checked' : '';
                        var knowledge_id = parent[j]['id'];
                        var know_process_str = '';
                        if (_is_question_mode && _knowledge_know_process[knowledge_id]) {
                        	var kp = _knowledge_know_process[knowledge_id]['kp'],
                        		kp_name = _knowledge_know_process[knowledge_id]['name'];
                        	
                        	if (kp > 0) {
                        		know_process_cache[knowledge_id] = kp;
                        		know_process_str = '<input type="hidden" name="know_process[' + knowledge_id + ']" value="' + kp + '"/>(认知过程：' + kp_name + ')';
                        	}
                        }
                        str += '<input type="checkbox" name="knowledge_id[]" id="knowledge'+parent[j]['id']+'" value="'+parent[j]['id']+'"'+checked+' /><label for="knowledge'+parent[j]['id']+'">'+parent[j]['knowledge_name']+'('+parent[j]['ques_num']+'['+parent[j]['relate_ques_num']+'])' + know_process_str + '</label>';
                    }
                    str += '</div><hr/>';
                }
                
                if (!$.isEmptyObject(know_process_cache)) {
                	know_process_cache = $.toJSON(know_process_cache);
                	str += '<div style="display:none;" id="k_know_process">' + know_process_cache + '</div>';
                }
                
                $('#knowledge_list').html(str);
            },
            'json'
        );
    }
}

//信息提取方式选择弹出框
function group_type_select() {
    var subject_id = $('#subject_id').val();
    if (subject_id == '') {
        alert('请选择学科');
        return;
    }
    var input_group_type_ids = $('#group_type_ids').val();
    $.post(
        ajax_url_group_type_select,
        {
    		subject_id:subject_id,
    		group_type_ids:input_group_type_ids
		},
        function(data){
            var __widowWidth = $(window).width();
            var __left = (__widowWidth - 800)/2 + 'px';
            $.blockUI({
                theme:true,
                message:data,
                themedCSS:{background:"#fff",width:"800px",left:__left,top:'10%',overflow:'auto'}
            });
            $(".close").click($.unblockUI);
            $('.blockOverlay').click($.unblockUI);
        },
        'html'
    );
}

// 初始化信息提取方式
function init_group_type() {
    var subject_id = $('#subject_id').val();
    var input_group_type_ids = $('#group_type_ids').val();
    if (input_group_type_ids) {
        $.post(
            ajax_url_group_type_init,
            {subject_id:subject_id,group_type_ids:input_group_type_ids},
            function(data){
                var str = '';
                for (var i=0; i<data.length; i++) {
                    var parent = data[i]['children'];
                    str += '<div><span class="font_4" pkid="'+data[i]['id']+'">'+data[i]['group_type_name']+'</span>：';
                    for (j=0; j<parent.length; j++) {
                        var checked = in_array(parent[j]['id'], input_group_type_ids.split(",")) ? ' checked' : '';
                        var group_type_id = parent[j]['id'];
                        str += '<input type="checkbox" name="group_type_id[]" id="group_type'+group_type_id+'" value="'+group_type_id+'" '+checked+' /><label for="group_type'+group_type_id+'">'+parent[j]['group_type_name']+ '</label>';
                    }
                    str += '</div><hr/>';
                }
                
                $('#group_type_list').html(str);
            },
            'json'
        );
    }
}

//方法策略选择弹出框
function method_tactic_select() {
	var subject_id = $('#subject_id').val();
	if (subject_id == '') {
		alert('请选择学科');
		return;
	}
	
    if (subject_id == 11)
	{
    	subject_id = '';
    	$("span[id=subject_id_list] input[name='subject_str[]']:checked").each(function(){
    		subject_id += ',' + $(this).val();
    	});
    	
    	if (subject_id.length > 0)
        {
    		subject_id = subject_id.substr(1);
        }
	}
	
	var input_method_tactic_ids = $('#method_tactic_ids').val();
	$.post(
			ajax_url_method_tactic_select,
			{subject_id:subject_id,method_tactic_ids:input_method_tactic_ids},
			function(data){
				 var __widowWidth = $(window).width();
				 var __left = (__widowWidth - 800)/2 + 'px';
		         $.blockUI({
		             theme:true,
		             message:data,
		             themedCSS:{background:"#fff",width:"800px",left:__left,top:'10%',overflow:'auto'}
		         });
		         $(".close").click($.unblockUI);
		         $('.blockOverlay').click($.unblockUI);
			},
		'html'
 );
}

//初始化方法策略
function init_method_tactic() {
	var subject_id = $('#subject_id').val();
	
    if (subject_id == 11)
	{
    	subject_id = '';
    	$("span[id=subject_id_list] input[name='subject_str[]']:checked").each(function(){
    		subject_id += ',' + $(this).val();
    	});
    	
    	if (subject_id.length > 0)
        {
    		subject_id = subject_id.substr(1);
        }
	}
    
	var input_method_tactic_ids = $('#method_tactic_ids').val();
	if (input_method_tactic_ids) {
		$.post(
         ajax_url_method_tactic_init,
         {subject_id:subject_id,method_tactic_ids:input_method_tactic_ids},
         function(data){
             var str = '';
             $.each(data, function (i, item){
            	 var _subject_category_name = item['name'],
            	 	_subject_category_id = i,
            	 	_method_tactics = item['method_tactics'];
            	 
            	 str += '<div><span class="font_4" pkid="'+_subject_category_id+'">'+_subject_category_name+'</span>：';
            	 $.each(_method_tactics, function (j, val) {
            	  	 var id = val['id'],
            	  	 	 name = val['name'];
            		 var checked = in_array(id, method_tactic_ids) ? ' checked' : '';
            		 str += '<input type="checkbox" name="method_tactic_id[]" id="method_tactic'+id+'" value="'+id+'"'+checked+' /><label for="method_tactic'+id+'">'+name+'</label>';
            	 });
            	 str += '</div><hr/>';
             });
             
             $('#method_tactic_list').html(str);
         },
         'json'
     );
   }
}



// 输入框按focus/blur显示或隐藏默认文字。用于难易度
function toggle_input_sytle(event_type, obj, word) {
    var value = obj.value.trim();
    if (event_type == 'focus') {
        if (value == word) {
            obj.value = '';
            $(obj).removeClass('gray_input');
        }
    } else if(event_type == 'blur') {
        if (value == '') {
            obj.value = word;
            $(obj).addClass('gray_input');
        } else {
            // todo
        }
    }
}

$(function(){
	$('#question_form').submit(function(){
		$('#dosubmit').attr('disabled', true);
	});
	$('#question_form input').change(function(){
		$('#dosubmit').attr('disabled', false);
	});
	$('#question_form select').change(function(){
		$('#dosubmit').attr('disabled', false);
	});
	$('#question_form button').click(function(){
		$('#dosubmit').attr('disabled', false);
	});
	$('#question_form textarea').focus(function(){
		$('#dosubmit').attr('disabled', false);
	});
	$('#question_form input').focus(function(){
		$('#dosubmit').attr('disabled', false);
	});
	$('div[id^="uedi"]').click(function(){
		$('#dosubmit').attr('disabled', false);
	});
});
