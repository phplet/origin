/**
 * 机考登录页
 */
$(document).ready(function () {
	//设置tiper的消失时间间隔为 5s
	UtilTiper.message.dismissin = 2500;
	
	//登录检查
	function check_login()
	{
		$('#btn_login').click(function () {
			var $thiz = $(this),
				$txt_exam_ticket = $('#txt_exam_ticket'),
				exam_ticket = $.trim($txt_exam_ticket.val()),
				$txt_passwd = $('#txt_passwd'),
				password	= $('#txt_passwd').val();

			if (exam_ticket.length == 0) {
				UtilTiper.message.error('请输入正确的准考证号.', {'target': $txt_exam_ticket});
				$txt_exam_ticket.focus();
				return false;
			}
			
			if (password.length == 0) {
				UtilTiper.message.error('请输入正确的密码.', {'target': $txt_passwd});
				$txt_exam_ticket.focus();
				return false;
			}

			$thiz.attr('disabled', 'disabled').addClass('disabled').val('登录中...');
			$.ajax({
				url : check_login_url,
				dataType : 'json',
				type : 'post',
				data : {
						exam_ticket : exam_ticket,
						password : password
				},
				timeout : 5000,
				error: function (a, b, c) {
					alert(a+b+c);
					$thiz.removeAttr('disabled').removeClass('disabled').val('登录系统');
					UtilTiper.message.error('登录失败，请联系监考老师.', {'target': $thiz});
				},
				success : function (response) {
					var code = response.code,
						msg	= response.msg;
					$thiz.removeAttr('disabled').removeClass('disabled').val('登录系统');
					if (code < 0) {
						UtilTiper.message.error(msg, {'target': $thiz});
						if (response.callback) {
							var _callback = response.callback;
							if (typeof _callback == 'function') {
								_callback();
							} else {
								val = eval("("+_callback+")");
							}
							//window.location.reload();
							return;
						}
						$txt_exam_ticket.focus();
					} else {
						window.location.reload(); 
					}
				}
			});
		});

		$('#txt_exam_ticket, #txt_passwd').keyup(function (e) {
			var  keycode=event.keyCode;
			if(keycode==13) {
				$('#btn_login').click();
			}
		});
	}

	function init()
	{
		$('#txt_exam_ticket').focus();
		check_login();
	}

	init();
});