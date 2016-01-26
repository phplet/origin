/**
 * 机考系统-监考人员 登录页
 */

$(document).ready(function () {
	/***期次，场次联动***/
	var $address_span = $('#address_span'),
		$subject_span = $('#subject_span'),
		$student_amount_span = $('#student_amount_span'),
		$start_time_span = $('#start_time_span'),
		$end_time_span = $('#end_time_span'),
		$school_span = $('#school_span'),
		$invigilate_notice_li = $('#invigilate_notice_li'),
		_exam_count_down_config = exam_count_down_config;
	
	//绑定 场次事件
	$('.place_id').change(function () {
		var $thiz = $(this),
			val = $thiz.val(),
			tmp_val = val;
		
		val = eval("("+val+")");	
		var s_time = val.start_time,
			e_time = val.end_time,
			address = val.address,
			student_amount = val.student_amount,
			school = val.school_name,
			invigilate_notice = val.invigilate_notice,
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
	
		subject = !subject.length ? '<font color="red">该场考试未分配科目</font>' : subject; 
				
		$address_span.html(address);
		$subject_span.html(subject);
		$student_amount_span.html(student_amount);
		$start_time_span.html(s_time);
		$end_time_span.html(e_time);
		$school_span.html(school);
		$invigilate_notice_li.html(invigilate_notice);
	
		$('#hidden_place').val(place_id);
	
		_exam_count_down_config.startT = val.start_time;
		_exam_count_down_config.endT = val.end_time;
		$.examTime("#id_statusbar_clock", _exam_count_down_config);
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


	//设置tiper的消失时间间隔为 5s
	UtilTiper.message.dismissin = 2500;
	
	//登录检查
	function check_login()
	{
		$('#btn_login').click(function () {
			var $thiz = $(this),
				$txt_email = $('#txt_email'),
				email = $.trim($txt_email.val()),
				$txt_passwd = $('#txt_passwd'),
				password	= $('#txt_passwd').val();

			if (email.length == 0) {
				UtilTiper.message.error('请输入正确的准考证号.', {'target': $txt_email});
				$txt_email.focus();
				return false;
			}
			
			if (password.length == 0) {
				UtilTiper.message.error('请输入正确的密码.', {'target': $txt_passwd});
				$txt_email.focus();
				return false;
			}

			$thiz.attr('disabled', 'disabled').addClass('disabled').val('登录中...');
			$.ajax({
				url : check_login_url,
				dataType : 'json',
				type : 'post',
				data : {
						email : email,
						password : password,
						exam_id:$('#exam_id').val(),
						place_id:$('#hidden_place').val()
				},
				timeout : 5000,
				error: function (a, b, c) {
					//alert(a + b + c);
					$thiz.removeAttr('disabled').removeClass('disabled').val('登录系统');
					UtilTiper.message.error('登录失败，请联系管理员.', {'target': $thiz});
				},
				success : function (response) {
					var code = response.code,
						msg	= response.msg;
					$thiz.removeAttr('disabled').removeClass('disabled').val('登录系统');
					if (code < 0) {
						UtilTiper.message.error(msg, {'target': $thiz});
						if (response.callback) {
//								var _callback = response.callback;
//								if (typeof _callback == 'function') {
//									_callback();
//								} else {
//									val = eval("("+_callback+")");
//								}
							//window.location.reload();
							return;
						}
						$txt_email.focus();
					} else {
						window.location.href = index_url;
					}
				}
			});
		});

		$('#txt_email, #txt_passwd').keyup(function (e) {
			var  keycode=event.keyCode;
			if(keycode==13) {
				$('#btn_login').click();
			}
		});
	}

	function init()
	{
		$('#txt_email').focus();
		check_login();
	}

	init();
});