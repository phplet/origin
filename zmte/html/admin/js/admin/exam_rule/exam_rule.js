//---------------------------------------------------------//
// 重置重点知识点列表。
//---------------------------------------------------------//
function reset_knowledge_rule_list(evt_checkbox) {
    //var knowledge_ids = $('#knowledge_ids').val();
    //var knowledge_id_arr = knowledge_ids.length ? knowledge_ids.split(',') : [];
    var rules = $('div.knowledge_rule');
    if (typeof (evt_checkbox) == 'undefined') evt_checkbox = $('#knowledge_list').find('input:checkbox')[0];
    for(var i=0; i<rules.length; i++) {
        reset_knowledge_rule(evt_checkbox, rules[i], i);
    }
    $('#knowledge_rule_tr').show();
}

//---------------------------------------------------------//
// 重置一条重点知识点规则。
// 1, 知识点选择框和范围知识点同步
// 2, 如果已选择的知识点被取消，则删除该条规则
//---------------------------------------------------------//
function reset_knowledge_rule(evt_checkbox, rule_div, index) {
    var event_kid = evt_checkbox.value;
    var event_pid = $(evt_checkbox).siblings('span').attr('pkid');
    var parent_val = $(rule_div).find('select.knowledge_parent').val();
    var option_val = $(rule_div).find('select.knowledge_child').val();

    // 二级知识点
    if (index && event_pid==parent_val) {
        var children = $(evt_checkbox).siblings(':checked');
        var clength = children.length;
        if (clength == 0 || evt_checkbox.checked == false && option_val==event_kid) {
            $(rule_div).slideUp('slow',function(){
                $(this).remove();
            });

            calculate_count();
            return;
        }
        var string = '';
        for (var i=0; i<clength; i++) {
            var id = children[i].value;
            var name = $('label[for=knowledge'+id+']').html();
            var selected = id==option_val? ' selected ' : '';
            string += '<option value="'+id+'"'+selected+'>'+name+'</option>';
        }
        $(rule_div).find('select.knowledge_parent')[0].length = 1;
        $(rule_div).find('select.knowledge_parent').append(parent_string);
    }

    // 一级知识点
    var parents = $('#knowledge_list').find('div');
    var plength = parents.length;
    var parent_string = '';
    for (var i=0; i<plength; i++) {
        if ($(parents[i]).find('input:checked').length) {
            var pid   = $(parents[i]).find('span').attr('pkid');
            var pname = $(parents[i]).find('span').html();

            
            var pname1 = pname.split("-&gt;");
            var pname2 = pname1[1];
            var pselected = pid==parent_val ? ' selected' : '';
            parent_string += '<option value="'+pid+'"'+pselected+'>'+pname2+'</option>';
        }
    }
    $(rule_div).find('select.knowledge_parent')[0].length = 1;
    $(rule_div).find('select.knowledge_parent').append(parent_string);
}

function next_knowledge(parent) {    
    var pid = parent.val();
    if ( ! pid) {
        return;
    }
    var children = $('#knowledge_list').find('span[pkid='+pid+']').siblings('input:checked');
    var string = '';
    for (var i=0; i<children.length; i++) {
        var id = children[i].value;
        var name = $('label[for=knowledge'+id+']').html();
        var selected = '';
        string += '<option value="'+id+'"'+selected+'>'+name+'</option>';
    }
    parent.parent().find('select.knowledge_child')[0].length = 1;
    parent.parent().find('select.knowledge_child').append(string);
}

//---------------------------------------------------------//
// 重置试题类型选择框
//---------------------------------------------------------//
function set_question_class_option() {
    var grade_id = $('#grade_id').val();
    if ( ! grade_id) return;    
    $.post(
        ajax_url_class,
        {grade_id:grade_id},
        function(data){
            if (typeof(class_id) == 'undefined') class_id = 0;
            var str = '';            
            for (var i=0; i<data.length; i++) {
                var selected = js_class_id==data[i]['class_id'] ? ' selected' : '';
                str += '<option value="'+data[i]['class_id']+'"'+selected+'>'+data[i]['class_name']+'</option>';
            }
            $('#class_id')[0].length = 1;
            $('#class_id').append(str);
        },
        'json'
    );
}

//---------------------------------------------------------//
//重置试题类型 复选框
//---------------------------------------------------------//
function set_question_class_checkbox() {
	var start_grade = parseInt($('#start_grade').val());
    var end_grade = parseInt($('#end_grade').val());
    var subject_id = parseInt($('#subject_id').val());
    
    if (isNaN(start_grade) || isNaN(end_grade) || start_grade > end_grade) {

    	$('#class_list,#subject_type_list').html('');
    	return;
    }
    if (isNaN(subject_id)) subject_id = 0;
    var class_ids = {},
    	subject_types = {};
    for (var i=1; i<=12; i++) {
        if (i < start_grade || i > end_grade) {
        	//do nothing
        } else {
        	var $_grade_class = $('#grade'+i+'_class'),
        		_tmp_class_ids = eval("("+$_grade_class.find('.class_id_box').html()+")");
        	
        	$.each(_tmp_class_ids, function (key, item) {
        		class_ids[key] = item;
        	});
           
            if (i >= 11) {
                if ( subject_id>=1 && subject_id <=3 ) {
                	var _tmp_subject_types = eval("("+$_grade_class.find('.subject_type_box').html()+")");
                	$.each(_tmp_subject_types, function (key, item) {
                		subject_types[key] = item;
                	});
                }
            }            
        }        
    }
    
    var length_class_ids = 0,
    	length_subject_types = 0;
    
    $.each(class_ids, function (key, item) {
    	length_class_ids = 1;
    	return false;
	});
    
    $.each(subject_types, function (key, item) {
    	length_subject_types = 1;
    	return false;
    });
    
    if (length_class_ids > 0) {
    	var str = [],
			search_class_ids = $('#search_class_id').val();
	
    	search_class_ids = search_class_ids.split('_') || [];
    	$.each(class_ids, function (key, item) {
    		var checked="";
    		if ($.inArray(key, search_class_ids) >= 0) {
    			checked = 'checked="checked"';
    		}
    		str.push('<input type="checkbox" name="class_id[]"' + checked + ' id="class_id' + key + '" value="' + key + '"/><label for="class_id' + key + '">' + item + '</label>');
    	});
    	
    	$('#class_list').html(str.join(''));
    } else {
    	$('#class_list').html('');
    }
    
    if (length_subject_types > 0) {
    	var str = [],
    		search_subject_types = $('#search_subject_type').val();
    	
    	search_subject_types = search_subject_types.split('_') || [];
    	$.each(subject_types, function (key, item) {
    		var checked_class="",
    			checked = '';
    		if ($.inArray(key, search_subject_types) >= 0) {
    			checked = 'checked="checked"';
    			checked_class = 'checked';
    		}
    		str.push('<input type="checkbox" class="subject_type ' + checked_class + '"' + checked + 'name="subject_type" id="subject_type' + key + '" value="' + key + '"/><label for="subject_type' + key + '">' + item + '</label>');
    	});
    	$('#subject_type_list').html(str.join(''));
    	bind_subject_type();
    } else {
    	$('#subject_type_list').html('');
    }
    
    function bind_subject_type() {
    	$('input.subject_type').unbind('click').click(function () {
			var $thiz = $(this);
			if ($thiz.hasClass('checked')) {
				$thiz.removeAttr('checked').removeClass('checked');
				return true;
			}

			$thiz.addClass('checked').attr('checked', true).siblings().attr('checked', false).removeClass('checked');
		});
    };
}

//文理科切换
function toggle_subject_type(){
    var subject_id = parseInt($('#subject_id').val());
    var grade_id   = parseInt($('#grade_id').val());
    var class_id   = parseInt($('#class_id').val());
    if (isNaN(grade_id) || grade_id < 11 
        || isNaN(subject_id) || subject_id<1 || subject_id > 3
        || isNaN(class_id) || class_id<2 || class_id >3) {
        $('#subject_type').hide().attr('disabled', true);
    } else {
        $('#subject_type').show().attr('disabled', false);
    }
}

// 添加一条重点规则
function add_rule() {
    var knowledge_ids = $('#knowledge_ids').val();
    var knowledge_id_arr = knowledge_ids.length ? knowledge_ids.split(',') : [];
    if (knowledge_id_arr.length == 0) {
        alert('请选择知识点范围');
        return;
    }
    var rules = $('#knowledge_rule_list .knowledge_rule');
    if (rules.length >= knowledge_id_arr.length) {
        alert('重点知识点数量不能超过范围知识点数量');
        return;
    }
    rule_num++;
    var string = $('#knowledge_rule_demo').html();
    string = string.replace(/\[0\]/g, '['+rule_num+']');
    $('#knowledge_rule_list').append(string);
    $('#knowledge_rule_list div.knowledge_rule').last().slideDown();
}

//---------------------------------------------------------//
// 查询试题数量（ajax）
// type: 0 查询学科、年级、类型相关的试题数量
// type: 1 查询范围知识点相关的试题数量
// type: 2 查询重点知识点的是试题数量，按题型、难易度9个分组
// obj : type=2的时候有效，为要查询的重点知识点选择框(select)对象
//---------------------------------------------------------//
function question_count(type, obj, data_source){
    if (data_source == 'js') {
        var subject_id = js_subject_id;
        var grade_id = js_grade_id;
        var class_id = js_class_id;
        var subject_type = js_subject_type;
        var knowledge_ids = js_knowledge_ids;
        var is_original = js_is_original;
    } else {
        var subject_id   = $('#subject_id').val();
        var grade_id     = $('#grade_id').val();
        var class_id     = $('#class_id').val();
        var subject_type = $('#subject_type').attr('disabled') ? '-1' : $('#subject_type').val();
        if ( ! subject_id || ! grade_id || ! class_id) {
            alert('请选择学科、年级、类型');
            return;
        }
        var knowledge_ids = $('#knowledge_ids').val();
        if (type > 0 && !knowledge_ids) {
            alert('请选择知识点范围！');
            return;
        }
        
        var is_original = $('input[name=is_original]:checked').val();
    }
    var rule_knowledge = 0;
    var know_process = 0;
    if (type == 2) {
        var rule_knowledge = obj.value;
        if ( ! rule_knowledge) {
            rule_knowledge = $(obj).siblings('select').val();
        }
        if (rule_knowledge < 1) return;
        
        know_process = $(obj).parent().find('input.input_know_process:checked').val();
    }
    
    $.post(
        ajax_url_question_count,
        {
        	type:type,
        	subject_id:subject_id,
        	grade_id:grade_id,
        	class_id:class_id,
        	subject_type:subject_type,
        	knowledge_ids:knowledge_ids,
        	rule_knowledge:rule_knowledge,
        	know_process:know_process,
        	is_original: is_original
    	},
        function(data){
            if (data.error) {
                alert(data.error);
            } else {
                if (type < 2)
                    $('#question_count_'+type).html(data['count'][0]+' ['+data['group_count'][0]+']');
                else {
                    var spans = $(obj).parentsUntil('div.knowledge_rule').find('span.knowledge_ques_count');
                    for (var i=0; i<data['count'].length; i++) {
                        $(spans[i]).html(data['count'][i]+' ['+data['group_count'][i]+']');
                    }
                }
            }
        },'json'
    );
    calculate_count();
}

//---------------------------------------------------------//
// 统计已填写的重点知识点数量
//---------------------------------------------------------//
function calculate_count() {
    var type_count = [0,0,0,0,0,0,0,0,0,0];
    var level_count = [0,0,0];
    var type_level_count = [0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0];
    var rules = $('#knowledge_rule_list div.knowledge_rule');
    
    for (var i=0; i<rules.length; i++) {
        var inputs = $(rules[i]).find('input[type=text]');
        for (var j=0; j<inputs.length; j++) {
            var num = parseInt(inputs[j].value.trim());
            if ( ! isNaN(num)) {
                type_level_count[j] += num;    
            }
        }
    }
    for (var i=0; i<30; i++) {        
        type_count[parseInt(i/3)] += type_level_count[i];
        level_count[i%3] += type_level_count[i];
    }

    $.each( type_count, function(i, n){
        $('span.type_count').eq(i).html(n);
    });
    $.each( level_count, function(i, n){
        $('span.level_count').eq(i).html(n);
    }); 
    $('#calculate_count').show();
}