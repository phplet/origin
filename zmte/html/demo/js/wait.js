/**
 * 机考等待页
 */
//倒计时 转到 登陆页
var total_time = null,
	start_left_time = null;//离考试开始倒计时
function refresh_time () {
	if (total_time === null) {
    	return;
	}
	if (total_time <= 1 || start_left_time <= 1) {
		//当前只有一场考试并且已开始，引导到登录页
		if (count_exam == 1 && count_places == 1) {
			redirect_login();
			return false;
		}
		$('#tiper_box').show();
		if (start_left_time <= 1) {
			$('#tiper_text').html('该场考试已经开始了，赶快进入考试吧，');
		} else {
			$('#tiper_text').html('该场考试马上就要开始，赶快进入考试吧，');
		}
		return false;
	}
	total_time--;
	start_left_time--;
}

var count_timer = setInterval("refresh_time()", 1000);

/**
 * 转到登录界面
 */
function redirect_login()
{
	//将当前考场信息保存到cookie中, 在后续考试中作为考场信息
	var place_info = $('#hidden_place').val(),
		_json = eval("("+place_info+")"),
		finish_time = _json.finish_time;
	
	$.get('/public.php?act=time_diff&time=' + finish_time, {}, function (data) {
		$.cookie("zeming_exam_test", $('#hidden_place').val(), {expires: data, path: "/"});
		window.location.href = login_url;
	});
		
}

$(document).ready(function(){
	//进行中的考试列表
	var doing_exams = doing_exams;
	doing_exams = eval("("+doing_exams+")");
	
	var $address_span = $('#address_span'),
		$subject_span = $('#subject_span'),
		$timer_span = $('#timer_span'),
		_login_url = login_url,
		_exam_count_down_config = exam_count_down_config;

	//绑定 场次事件
	$('.place_id').change(function () {
		var $thiz = $(this),
			val = $thiz.val(),
			tmp_val = val;
		
		val = eval("("+val+")");	
		
		//倒计时到 登陆页
      	total_time = parseInt(val.wait_end_seconds);
      	start_left_time = parseInt(val.start_left);
      	
		var s_time = val.start_time + ' ~ ' + val.end_time,
			address = val.address,
			exam_id = $thiz.attr('exam_id'),
			place_id = val.place_id,
			place_name = val.place_name,
			subject = $.trim(val.subject_name);
		
		if ($thiz.find('option').length == 1) {
			$thiz.hide();
			$('.hidden_exam_span').hide();
			$('#hidden_exam_' + exam_id).show().html(place_name);
		} else {
			$thiz.show();
		}

		subject = !subject.length ? '<font color="red">该场考试未分配科目，请联系监考老师</font>' : subject; 
				
		$timer_span.html(s_time);
		$address_span.html(address);
		$subject_span.html(subject);

		$('#hidden_place').val(tmp_val);

		//如果该场考试已经开始，则引导考生直接登录考试
		if ($.inArray(place_id, doing_exams) >= 0) {
			$('#tiper_box').show();
			$('#tiper_text').html('该场考试已经开始，请点击按钮直接进入考场.');
		} else {
			$('#tiper_box').hide();
		}

		_exam_count_down_config.startT = val.start_time;
		_exam_count_down_config.endT = val.end_time;
		$.examTime("#id_statusbar_clock", _exam_count_down_config);
	});

	//进入考场按钮
	$('#btn_go_login').click(function () {
		redirect_login();
	});
	
	//考试期次触发
	var bind_exam = function () {
		if (count_exam > 1) {
			$('#exam_id').change(function () {
				var exam_id = $(this).val();
		
				//联动考试场次
				$('.place').hide();
				$('.place_select').hide();
				$('#place_' + exam_id).show().change();
		
				//联动注意事项
				$('#notice_span').html($('#hidden_notice_'+exam_id).html());
			}).change();
		} else {
			//联动注意事项
			var hidden_exam_id = $('#exam_id').val();
			$('#place_' + hidden_exam_id).show().change();
			$('#notice_span').html($('#hidden_notice_'+hidden_exam_id).html());
		}
	}
	
	bind_exam();
});
