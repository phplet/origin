$(function(){
	$("#exam_rule_limit_0").on('change', "input[type='radio']", function() { 
		get_qtype_num($(this), 0);
	}).on('blur', "input[type='text']",function() {
		get_qtype_num($(this), 0);
	}).on('blur', "input[name='ques_limit_num[]']",function() {
		input_ques_limit_num($(this));
	});
	
	setTimeout('qtype_num_init(0)',1000);
	
});

function qtype_num_init(qtype)
{
	$("#exam_rule_limit_"+qtype+" tr").nextAll().each(function(){get_qtype_num($(this).find('input:first'), qtype);});
}

function submit_exam_rule(qtype)
{
	if (s_id != 3)
	{
		$("#rule_form").submit();
		return;
	}
	
	var qtype_num = $("input[name='ques_num[0]']").val();
	var ques_num = 0;
	$("#exam_rule_limit_"+qtype+" input[name='ques_limit_num[]']").each(function(index){
		ques_num += parseInt($(this).val());
	});
	
	var is_access = true;
	$("#exam_rule_limit_"+qtype+" tr").nextAll().each(function(){
		var sort = $(this).attr('sort');
		var word_limit_min = $("input[name='word_limit_num_min["+sort+"]']").val();
		var word_limit_max = $("input[name='word_limit_num_max["+sort+"]']").val();
		if (word_limit_min > 0 && word_limit_max > 0 && parseInt(word_limit_min) >= parseInt(word_limit_max))
		{
			$("#err_qtype_count_"+sort).html('字数限制上限必须大于下限');
			is_access = false;
		}
		else
		{
			var now_ques_num = $(this).find("input[name='ques_limit_num[]']").val();
			var now_ques_count = $('#qtype_count_'+sort).html();
			if (parseInt(now_ques_num) > parseInt(now_ques_count))
			{
				$("#err_qtype_count_"+sort).html('所需题数不能大于当前总题数');
				is_access = false;
			}
			else
			{
				$("#err_qtype_count_"+sort).html('');
			}
		}
	});
	
	if (!is_access)
	{
		return;
	}
	
	if (parseInt(qtype_num) < parseInt(ques_num))
	{
		$("#err_qtype_count").html('所需题数不能超过总的试题数');
		return;
	}
	else
	{
		$("#err_qtype_count").html('');
	}

	$("#rule_form").submit();
}

/**
 * 输入所需题数判断
 * @param obj
 * @returns
 */
function input_ques_limit_num(obj)
{
	var ques_limit_num = parseInt(obj.val());
	var sort = obj.parent('td').parent('tr').attr('sort');
	if (ques_limit_num > 0)
	{
		var now_qtype_nums = parseInt($('#qtype_count_'+sort).html());
		var qtype_num = parseInt($("input[name='ques_num[0]']").val());
		if (ques_limit_num > now_qtype_nums)
		{
			$("#err_qtype_count_"+sort).html('所需题数不能大于当前总题数');
			obj.focus();
			return;
		}
		
		if (ques_limit_num > qtype_num)
		{
			$("#err_qtype_count_"+sort).html('所需题数不能大于总的试题数');
			obj.focus();
			return;
		}
		
		var ques_num = 0;
		obj.parent('td').parent('tr').parent().find("input[name='ques_limit_num[]']").each(function(index){
			ques_num += parseInt($(this).val());
		});
		
		if (ques_num > qtype_num)
		{
			$("#err_qtype_count_"+sort).html('所需题数不能大于总的试题数');
			obj.focus();
			return;
		}
	}
	$("#err_qtype_count_"+sort).html('');
}

/**
 * 查询当前条件所有的试题数
 * @param obj
 * @param qtype
 * @returns
 */
function get_qtype_num(obj, qtype)
{
	if (obj.attr('name').indexOf('ques_limit_num') >= 0)
	{
		return;
	}

	var win_obj = obj.parent('td').parent('tr');
	var sort = win_obj.attr('sort');
	var difficulty_limit = win_obj.find("input[type=radio]:checked").val();
	var word_limit_num_min = win_obj.find("input[name='word_limit_num_min["+sort+"]']").val();
	var word_limit_num_max = win_obj.find("input[name='word_limit_num_max["+sort+"]']").val();
	var children_limit_num = win_obj.find("input[name='children_limit_num["+sort+"]']").val();

	if (difficulty_limit == 'undefined') difficulty_limit = 0;
	
	if (isNaN(word_limit_num_min) || isNaN(word_limit_num_max))
	{
		$("#err_qtype_count_"+sort).html('字数限制必须为数字');
		return;
	}
	
	if (isNaN(children_limit_num))
	{
		$("#err_qtype_count_"+sort).html('子题数限制必须为数字');
		return;
	}
	
	if (word_limit_num_min < 0)
	{
		$("#err_qtype_count_"+sort).html('字数限制下限必须大于0');
		return;
	}
	
	if (word_limit_num_max < 0)
	{
		$("#err_qtype_count_"+sort).html('字数限制下限必须大于0');
		return;
	}
	
	if (word_limit_num_min > 0 &&　word_limit_num_max > 0 && parseInt(word_limit_num_min) >= parseInt(word_limit_num_max))
	{
		$("#err_qtype_count_"+sort).html('字数限制上限必须大于下限');
		return;
	}
	
	if (children_limit_num < 0)
	{
		$("#err_qtype_count_"+sort).html('子题数限制必须大于0');
		return;
	}
	
	var subject_id = $('#subject_id').val();
	var grade_id = $('#grade_id').val();
	var class_id = $('#class_id').val();
	var subject_type = $('#subject_type').val();
	if (subject_id && (difficulty_limit || word_limit_num_min || word_limit_num_max || children_limit_num))
	{
        $.post(
        	ajax_url_qtype_count_init,
            {subject_id:subject_id,type:qtype,grade_id:grade_id,class_id:class_id,subject_type:subject_type,difficulty_limit:difficulty_limit,word_limit_num_min:word_limit_num_min,word_limit_num_max:word_limit_num_max,children_limit_num:children_limit_num},
            function(data){
               $("#qtype_count_"+sort).html(data['nums']);
            },
            'json'
        );
	}
}

var i = 0;

/**
 * 添加题组限制
 * @param pid
 */
function set_exam_rule_limit(pid)
{
	var qtype_num = $("input[name='ques_num[0]']").val();
	var tr_length = $("#" + pid +" tr").length - 1;
	if (i == 0 && tr_length > 0)
	{
		i = tr_length;
	}
	
	var ques_num = 0;
	$("#"+pid+" input[name='ques_limit_num[]']").each(function(index){
		ques_num += parseInt($(this).val());
	});
	
	if (qtype_num <= ques_num && tr_length == 1)
	{
		$("#err_qtype_count").html('所需题数已经等于总的试题数了');
		return;
	}
	
	if (qtype_num <= tr_length)
	{
		$("#err_qtype_count").html('限制条数不能超过试题数');
		return;
	}
	
	i++;
	
	var html = '<tr sort='+i+'>';
	html += '<td><input type="radio" value="3" name="difficulty_limit['+i+']">低<input type="radio" value="2" name="difficulty_limit['+i+']">中<input type="radio" value="1" name="difficulty_limit['+i+']">高</td>';
    html += '<td><input type="text" name="word_limit_num_min['+i+']" class="txtbox5" value="" /> - <input type="text" name="word_limit_num_max['+i+']" class="txtbox5" value="" /></td>';
    html += '<td><input type="text" name="children_limit_num['+i+']" class="txtbox5" value="" /></td>';
    html += '<td><font class="font_4"  id="qtype_count_'+i+'"></font></td>';
    html += '<td><input type="text" name="ques_limit_num[]" class="txtbox5" value="" /></td>';
    html += '<td><a href="javascript:void(0);" onclick="remove_exam_rule_limit($(this));">删除</a></td>';
    html += '<td><span class="font_4" id="err_qtype_count_'+i+'"></span></td>';
    html += '</tr>';
    $("#"+pid).append(html);
    $("#err_qtype_count").html('');
}

/**
 * 删除题组限制
 * @param obj
 */
function remove_exam_rule_limit(obj)
{
	obj.parent('td').parent('tr').remove();
}

/**
 * 显示隐藏学科题型
 * @param s_id
 */
function qtype_toggle(s_id)
{
	if (s_id != 3)
	{
	    for (var i=4;i<=9;i++)
	    {
	    	$('.subject_q_type_'+i).each(function(){
	    	    $(this).hide();
		    });
		}

		$(".subject_exam_rule_limit_3").each(function(){
			$(this).hide();
		});
	}
	else
	{
		for (var i=4;i<=9;i++)
	    {
	    	$('.subject_q_type_'+i).each(function(){
	    	    $(this).show();
		    })
		}

		$(".subject_exam_rule_limit_3").each(function(){
			$(this).show();
		});
	}
}

/**
 * 显示隐藏连词成句
 * @param int grade_id 年级id
*/
function primary_type (grade_id)
{
	if (grade_id <= 6)
	{
		$('.subject_q_type_9').show();
	}
	else
	{
		$('.subject_q_type_9').hide();
	}
}