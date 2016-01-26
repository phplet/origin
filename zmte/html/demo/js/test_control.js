/**
 * 考生考试界面控制
 * @author qfb 
 * @create 2013-12-13
 */

/**
 *  * 开考提示对话框
 *   */
var has_show_cheat_notice = false,
	cache_count_window_blur = 0;;
function start_testing_confirm_dialog() {
	if (screenfull.enabled && screenfull.isFullscreen) {
		return false;
	}
	if (count_window_blur > 0)
	{
		has_show_cheat_notice = true;
		show_cheat_notice();
	}
	else
	{
		var notice = [];
		notice.push('同学，考试已经开始了，一定要认真答题哦~<hr/>');
		notice.push('<strong>在考试期间，以下操作将被视为作弊处理：</strong>');
		notice.push('<p>1、离开考试界面，累计三次；</p>');
		notice.push('<p>2、单次离开考试界面时长超过 2 分钟；</p>');
		new $.Zebra_Dialog(notice.join(''), {
			'type':     'question',
			'title':    '系统温馨提示',
			'buttons':  ['开始考试'],
			'onClose':  function(caption) {
				//关闭
			}
		});
	}
	
	setTimeout(function () {$('a.ZebraDialog_Button_0').attr('href', '###');}, 300);
	    		    		
	//关闭层 触发全屏
	$('a.ZebraDialog_Button_0, a.ZebraDialog_Close').click(function () {
		force_to_fullscreen();
	});
}

function show_cheat_notice() {
	return false;
	if (count_window_blur <= 0 || has_show_cheat_notice && count_window_blur == cache_count_window_blur) return false;
	
	cache_count_window_blur = count_window_blur;
	var _tmp_count = count_window_blur >= max_window_blur ? ('<font color="red">' + count_window_blur + '</font>') : count_window_blur;
	var msgs = ['本场考试你的作弊行为已经达到 '+_tmp_count+'次，超过3次系统将自动提交试卷!'];
	new $.Zebra_Dialog(msgs.join('<hr/>'), {
	    'type':     'question',
	    'title':    '系统警告',
	    'buttons':  ['我知道了'],
	    'onClose':  function(caption) {
	    }
   });
}

/*
 * 防止作弊
 * @note
 * 	 1、 离开当前考试窗口视为作弊行为
 */
var userAgent = navigator.userAgent.toLowerCase();
var is_opera = userAgent.indexOf('opera') != -1 && opera.version();
var is_ie = (userAgent.indexOf('msie') != -1 && !is_opera) && userAgent.substr(userAgent.indexOf('msie') + 5, 3);
var	max_window_blur = 3,
	focused = true,
	blur_timer = null,
	blur_deal_timer = null,
	blur_seconds_timer = null,
	blur_long_request_count = 0,
	pass_blur_check = false,
	is_onbeforeunload = false,
	blur_seconds = 0,
	_window = window,
	_document = document
	;
function prevent_cheating() {
	//强行交卷(see: test.js/force_to_submit)
	var force_submit = function (msg) {
		return false;
		if (window.force_to_submit) {
			force_to_submit(function () {
				unbind_onbeforunload();
				
				var msgs = ['由于您的作弊行为，系统将强制提交试卷! 5秒后将自动关闭该页面.'];
				if (msg) {
					msgs.push(msg);
				}
				//交卷完成提示
				new $.Zebra_Dialog(msgs.join('<hr/>'), {
				    'type':     'confirmation',
				    'title':    '系统温馨提示',
				    'buttons':  [],
				    'onClose':  function(caption) {
				    	//关闭
		    		}
			   });
				
			   setTimeout(function () {window.location.reload();}, 5000);
			});
		}
	};
	
	//显示作弊信息
	var show_cheat_info = function () {
		return false;
		if (count_window_blur > 0) {
			var _tmp_count = count_window_blur >= max_window_blur ? ('<font color="red">' + count_window_blur + '</font>') : count_window_blur;
			$('#cheat_span').show();
			$('#cheat_count').html(_tmp_count);
			$('#cheat_total').html(max_window_blur);
			
			show_cheat_notice();
		}
	}
	
	//随时监听是否作弊
	blur_deal_timer = setInterval(function () {
		return false;
		if (count_window_blur >= max_window_blur) {
			force_submit('作弊原因:<br/><p style="color:red;">离开界面累计次数 ' + max_window_blur + ' 次</p>');
			clearInterval(blur_deal_timer);
			if (blur_timer != null) {
        		clearTimeout(blur_timer);
        	}
		}
	}, 100);
	
	show_cheat_info();
	
	//离开界面时长计时
	var blur_request_status = null;
	var start_blur_seconds_timer = function () {
    	blur_seconds_timer = setInterval(function () {
    		return false;
			blur_seconds++;
			var need_send = false;
			if (blur_long_request_count == 0 && blur_seconds >= 60) {
				need_send = true;
			} else if (blur_long_request_count == 1 && blur_seconds >= 120) {
				need_send = true;
			}
			if (need_send && blur_request_status != 'doing') {
				blur_request_status = 'doing';
				$.post(config_urls.cheat_log, {act:'window_blur_long_time', 'data' : $.toJSON({'time' : blur_seconds})}, function(result){
					blur_request_status = 'done';
					
            		//do nothing
					blur_long_request_count++;
					
					//离开界面>=2分钟，作弊处理
					if (blur_long_request_count >= 2) {
						if (blur_seconds_timer != null) {
							clearInterval(blur_seconds_timer);
							blur_seconds = 0;
						}
						force_submit('作弊原因:<br/><p style="color:red;">离开界面时长超过 2 分钟</p>');
					}
            	});
			}
		}, 1000);
	};
	
	
	var _focusin = function () {
		focused = true;
    	is_onbeforeunload = false;
    	if (blur_timer != null) {
    		clearTimeout(blur_timer);
    	}
    	if (blur_seconds_timer != null) {
    		clearInterval(blur_seconds_timer);
    		blur_seconds = 0;
    		blur_long_request_count = 0;
    	}
	};
	
	var _focusout = function () {
		if (pass_blur_check || is_onbeforeunload) {
    		start_blur_seconds_timer();
    		return true;
    	}
    	focused = false;
    	
    	//离开界面3秒视为作弊一次
    	blur_timer = setTimeout(function () {
    		return false;
    		if (!focused) {
    			count_window_blur++;
    			
    			if (count_window_blur <= (max_window_blur+1)) {
    				setTimeout(function () {
    					$.post(config_urls.cheat_log, {act:'window_blur', 'data' : $.toJSON({'count' : count_window_blur})}, function(result){
    						//do nothing
    					});
    					$.post(config_urls.blur_count, {}, function(result){
    						//do nothing
    						window.console && console.log(result);
    					}, 'json');
    				}, 100);
    			}
    			
    			//显示作弊信息  
    			show_cheat_info();
    			var _tmp_count = count_window_blur >= max_window_blur ? ('<font color="red">' + count_window_blur + '</font>') : count_window_blur;
        		show_dialog('您已离开考试界面 <strong>' + _tmp_count + '</strong> 次，累计<strong>' + max_window_blur + '</strong>次系统将记录为作弊，将无法继续考试.');
    		}
    	}, 3000);	
    	
    	start_blur_seconds_timer();
	};

	if (is_ie) {
		$(_document).bind('focusin', function() { _focusin(); });
		$(_document).bind('focusout', function() { _focusout(); });
	} else {
		$(_window).bind('focus', function() { _focusin(); });
		$(_window).bind('blur', function() { _focusout(); });
	}
	
	//聚焦
	$(_window).focus();
}
