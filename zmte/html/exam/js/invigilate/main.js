/**
* 机考-监考老师
 */
$(document).ready(function () {
	/**
	 * 全局缓存变量
	 */
	var last_id = 0,//最新一条日志id
		
		g_per_page = 20,//每页条数
		g_current_page = 1,//当前页码
		
		mini_mode = false,//当分页停留在非首页时，开启mini模式，加快后台获取速度
		has_new_record = false//有新数据标志
		
		g_pager = null
		; 
	
	//全局jquery选择器变量
	var $window = $(window),
		$body_div = $('#id_jk_body'),
		
		_student_all_log_box = "#id_jk_single",
		$student_all_log_box = $(_student_all_log_box),
		
		_student_log_box = "#student_log_box",
		$student_log_box = $(_student_log_box),
		
		_student_selecter_box = "#id_jk_student_name",
		$student_selecter_box = $(_student_selecter_box),
		
		$pager_box = $('#pager_box'),
		$pager_tip = $('#pager_tip');
	
	var _win_height = $window.height();
		
	//设置部分尺寸
	var redraw_something = function () {
		
		//设置日志列表高度
		if (screenfull.enabled && screenfull.isFullscreen)  {
		     var _screen_height = screen.height;
		     $body_div.css("height", _screen_height);
		     $student_log_box.css("height", _screen_height-440);
		} else {
		    $body_div.css("height", $window.height());
		    $student_log_box.css("height", $window.height()-440);
		}
		
	};
	
	//学生所有事件tip
	var show_all_log_timer;
	function set_all_log_timer(){
		show_all_log_timer = setTimeout(function(){
			$student_all_log_box.css("display","none");
			$(_student_log_box + " a").removeClass('hover');
		}, 200);
	}
	function clear_all_log_timer(){
		show_all_log_timer && clearTimeout(show_all_log_timer);
	}
	var show_student_all_log_box = function() {
		function load_student_log(truename, uid) {
			var $_student_log_name = $('#student_log_name'),
				$_student_log_list = $('#student_log_list');
			
			$_student_log_name.html(truename);
			$_student_log_list.html('<li class="cls_jk_single_item" style="font-size:12px;color:green;">正在拉取该学生日志...</li>');
			$.ajax({
				url : config_url.get_student_logs,
				dataType : 'json',
				type : 'get',
				data : {uid:uid},
				timeout : 5000,
				error: function (a, b, c) {
					//alert(a + b + c);
					$_student_log_list.html('<li class="cls_jk_single_item" style="font-size:12px;color:red;">拉取失败，请稍后再试 或 刷新页面.</li>');
				},
				success : function (response) {
					var code = response.code,
						msg	= response.msg,
						data = response.data;

					UtilTiper.message.loaded();
					if (code < 0) {
						UtilTiper.message.error(msg, {'target': $student_log_box});
						if (response.callback) {
							window.location.href = response.callback;
							return;
						}
						return false;
					}

					var _total = data.total,
						_content = data.content;

					$_student_log_list.html(_content);
				}
			});
		}
		
		$(_student_log_box + " a, " + _student_all_log_box).unbind('mouseenter mouseleave').bind({
			mouseenter:function() {
				clear_all_log_timer();
				if ($(this).hasClass('box')) {
					$student_all_log_box.css({"display" : "block"});
					return false;
				}
				var _$window = $window;
				var top = parseInt($(this).offset().top + $student_all_log_box.outerHeight() + 20)> _$window.height() ? parseInt( _$window.height() - $student_all_log_box.outerHeight()-50) : $(this).offset().top;
				
				var _left = $(this).offset().left;
				$student_all_log_box.stop(true, true).css({"display" : "block", "top" : top - 180, 'left' : _left - 420}).animate({top:(top - 100)}, 600, function(){});
				
				//加载该学生日志
				var $thiz = $(this),
					_truename = $thiz.attr('truename'),
					_uid = $thiz.attr('uid');
				
				$(_student_log_box + " a").removeClass('hover');
				$thiz.addClass('hover');
				if ($thiz.hasClass('s_log')) {
					load_student_log(_truename, _uid);
				}
			},
			mouseleave:function(){clear_all_log_timer(); set_all_log_timer();}
		});
	};
    
	//学生筛选
    var show_student_selector_timer = null;
    function set_show_selector_timer(){
        show_student_selector_timer = setTimeout(function(){ 
        	$("#txt_uid").val().length == 0 && $("#txt_tmp_uid").val('学生筛选') && $("#txt_ticket").val('');
        	$student_selecter_box.slideUp();
    	}, 500);
    }
    function clear_show_selector_timer(){
        show_student_selector_timer &&  clearTimeout(show_student_selector_timer);
    }
    var show_student_selecter_box = function () {
    	$("#id_jk_student_icon, " + _student_selecter_box).bind({
    		mouseenter:function(){
    			clear_show_selector_timer();
    			$student_selecter_box.slideDown();
    		},
    		mouseleave:function(){ clear_show_selector_timer();set_show_selector_timer();}
    	});
    	
    	//学生筛选输入框绑定
    	$("#txt_tmp_uid").click(function(){
    		$("#txt_uid").val().length <= 0 && $(this).val("");
    		$('#student_' + $("#txt_uid").val()).addClass('current');
    		$student_selecter_box.slideDown();
    	});
    	
    	//学生筛选提示层
    	var $_student_selecter_tab = $("#id_jk_tab_nav"),
    		$_tab_menus = $_student_selecter_tab.children();
    	
    	var $_student_selecter_tab_content = $("#id_jk_tab_content"),
    		$_tab_content = $_student_selecter_tab_content.children();
    	
    	$_tab_menus.hover(function(){
    		var index = $(this).index();
    		$(this).addClass("cls_jk_selected").siblings().removeClass("cls_jk_selected");
    		$_tab_content.eq(index).show().siblings().hide();
    	});
    	
    	//学生姓名绑定
    	var $a_select_user = $student_selecter_box.find('a.a_select_user');
    	$a_select_user.unbind('click').bind('click', function () {
    			
    		var $thiz = $(this);
    		$a_select_user.removeClass('current');
    		$thiz.addClass('current');
    		
			$("#txt_tmp_uid").val($thiz.attr('truename'));
			$("#txt_ticket").val($thiz.attr('trueticket'));
			$("#txt_uid").val($thiz.attr('uid'));
    	});
    	
    	$('#btn_unselect_student').unbind('click').bind('click', function () {
    		$a_select_user.removeClass('current');
    		
    		$("#txt_uid").val('');
    		$("#txt_tmp_uid").val('');
    		$("#txt_ticket").val('');
    	});
    };
    
    //查看全部
    $('#btn_get_all').unbind('click').bind('click', function () {
    	$('#btn_reset').click();
    	setTimeout(function () {$('#btn_search').click();}, 500);
    	return false;
    });
    
	/**
	 * 筛选日志
	 */
	function search_logs() {
		$('#btn_search').unbind('click').bind('click', function () {
			clear_load_timer();
			last_id = 0;
			mini_mode = false;
			load_data({is_ajax:0});
		});
	}
	
	/*
	 * 获取查询条件 
	 */
	function get_params(post_data)
	{
		var post_data = post_data || {};
			$_txt_ticket = $('#txt_ticket'),
			$_txt_uid = $('#txt_uid'),
			$_select_log_type = $('#select_log_type'),
			$_txt_time_start = $('#txt_time_start'),
			$_txt_time_end = $('#txt_time_end');
		
		var txt_ticket = $.trim($_txt_ticket.val()),
			txt_uid = $.trim($_txt_uid.val()),
			txt_log_type = $.trim($_select_log_type.val()),
			txt_time_start = $.trim($_txt_time_start.val()),
			txt_time_end = $.trim($_txt_time_end.val());
		
		if (txt_ticket.length) {
			post_data.ticket = txt_ticket;
		}
		if (txt_uid.length) {
			post_data.uid = txt_uid;
		}
		if (txt_log_type.length) {
			post_data.log_type = txt_log_type;
		}
		if (txt_time_start.length) {
			post_data.t_start = txt_time_start;
		}
		if (txt_time_end.length) {
			post_data.t_end = txt_time_end;
		}
		
		post_data.is_ajax = post_data.is_ajax || '0';
		post_data.last_id = last_id;
		post_data.mini_mode = mini_mode ? '1' : '0';
		
		return post_data;
	}
	
	/*
	 * 获取考生行为日志 错误回调
	 */
	function get_logs_error(post_data, errors)
	{
		//console(errors);
		UtilTiper.message.loaded();
		clear_load_timer();
		$.Zebra_Dialog('<strong>好像数据加载失败了，请重试 或刷新页面~</strong>', {
		    'type':     'error',
		    'title':    '系统温馨提示',
		    'buttons':  [
		                    {
		                    	caption: '重新拉取', 
		                    	callback: function() {
				                    load_data(post_data);
		                   		}
	                    	}
		                ]
		});
	}
	
	
	/*
	 * 获取考生行为日志 成功回调
	 */
	function get_logs_callback(post_data, response)
	{
		var code = response.code,
			msg	= response.msg,
			data = response.data;
	
		UtilTiper.message.loaded();
		if (code < 0) {
			UtilTiper.message.error(msg);
			if (response.callback) {
				window.location.href = response.callback;
				return;
			}
			return false;
		}
	
		var _last_id = data.last_id,
			_content = data.content,
			_page_count = parseInt(data.pageCount) || 0,
			_current_count = data.current_count;
		
		if (_current_count > 0) {
			var $_tr_nodata = $('#tr_no_data');
			$_tr_nodata.length && $_tr_nodata.remove();
			
			if (!is_first_page() && post_data.is_ajax == '1') {
				has_new_record = true;
				$pager_tip.show();
				refresh_new_record();
			} else if(is_first_page()) {
				last_id = _last_id;
			}
		}
		
		if (post_data.is_ajax == '1') {
			var $_content = $(_content);
			$_content.prependTo($student_log_box).hide().slideDown(400)
			shake($_content, 'shake_red', 8);
			
			var $log_items = $student_log_box.find('li.log_item'),
				_length = $log_items.length;
			
			if (_length > g_per_page) {
				$student_log_box.find('li:gt(' + (g_per_page - 1) + ')').remove();
			}
		} else {
			$student_log_box.html(_content).hide().slideDown(500).fadeIn(500);
		}
		
		//该考生所有事件触发
		show_student_all_log_box();
		
		if (post_data.is_ajax == '0') {
			set_load_timer();
		}
		
		//没有数据时，隐藏分页
		if (_page_count == 0) {
			setTimeout(function () {
				$pager_box.html('');
			}, 600);
			
			return false;
		}
	}
	
	/**
	 * 点击 刷新新出现的日志
	 */
	function refresh_new_record()
	{
		var $first_page = $pager_box.find('a[paged=1]').eq(0),
			_offset = $first_page.offset(),
			_left = _offset.left - 20;
			
		$pager_tip.css({'left':_left, 'top':_top}).unbind('click').bind('click', function () {
			$pager_box.find('a[paged=1]').eq(0).click();
			return true;
		});
	}
	
	/**
	 * 分页
	 */
	function load_data(post_data)
	{
		//拼接参数
		var post_data = get_params(post_data);
		var pager_config = {
					cssStyle: "manu",
					currPage: g_current_page,
					panel: {
			            tipInfo_on: true,
			            tipInfo: '  跳{select}/{sumPage}页'
		           },
		           ajax: {
		        	   on: true,
		        	   url: config_url.get_logs,
		        	   param: post_data,
		        	   timeout: 5000,
		        	   error: function (a, b, c) {get_logs_error(post_data, {a: a, b: b, c: c});},
		        	   ajaxStart: function () {
		        		   //UtilTiper.message.doing('正在拉取当前考场监考日志...');
		        	   },
		        	   callback:function(response) {
		        		   get_logs_callback(g_pager.getAjaxParams(), response);
		        	   },
		        	   onClick: function (paged) {
		        		   g_current_page = paged;
		        		   g_pager.resetAjaxParams({is_ajax:0});
		        		   if (paged > 1) {
		        			   mini_mode = true;
		        			   if (has_new_record) {
		        				   clear_load_timer();
		        			   }
		        			   return false;
		        		   }
		        		   
		        		   g_pager.resetAjaxParams({last_id:0, mini_mode:0});
		        		   has_new_record = false;
		        		   mini_mode = false;
		        		   $pager_tip.hide();
		        		   set_load_timer();
		        	   }
		           }
			        
		};
		
		g_pager = $pager_box.myPagination(pager_config);
	}
	
	/**
	 * 判断当前分页是否位于第一页
	 */
	function is_first_page()
	{
		return g_current_page == 1;
	}
	
	//开启获取考生日志定时器
	function set_load_timer() 
	{
		clear_load_timer();
		window.timer_load_data = setInterval(function () {
			//如果停留在分页 非第一页，则不
			load_data({is_ajax:1});
		}, 10000);
	}	

	/*
	 * 清除计时器 
	 */
	function clear_load_timer() 
	{
		if (window.timer_load_data) {
			clearInterval(timer_load_data);
		}	
	}	
	
	//重置监考人员密码
	function reset_invigilate_password() 
	{
		$('#btn_reset_invigilate_password').unbind('click').bind('click', function () {
			new $.Zebra_Dialog('', {
			    'source':  {'ajax': config_url.load_reset_i_passwd},
			    'type':     '',
			    'buttons':  false,
			    width: 880,
			    'title': '修改密码'
			});
		});
	}
	
	//修改考生密码
	function change_student_password() 
	{
		$('#btn_change_student_passwd').unbind('click').bind('click', function () {
			new $.Zebra_Dialog('', {
				'source':  {'ajax': config_url.load_chang_s_pwd},
				'type':     '',
				'buttons':  false,
				width: 900,
				'title': '修改考生密码'
			});
		});
	}
	
	//修改考生密码
	function out_student() 
	{
		$('#btn_out_student').unbind('click').bind('click', function () {
			new $.Zebra_Dialog('', {
				'source':  {'ajax': config_url.load_out_student},
				'type':     '',
				'buttons':  false,
				width: 900,
				'title': '踢出学生'
			});
		});
	}
	
	/**
	 * 设置 准考证号查询 自动感应
	 */
	function set_ticket_autocomplete(is_dialog)
	{
		var is_dialog = is_dialog || '0';
		var users = eval("("+$('#hidden_student_configs').html()+")");
		$("input.student_account")
			.unautocomplete()
			.focus().autocomplete(users, {
				minChars: 0,
				width: 200,
				matchContains: "word",
				autoFill: false,
				formatItem: function(row, i, max) {
					return i + "/" + max + ": \"" + row.truename + "(" + row.py + ")\" [" + row.ticket + "]";
				},
				formatMatch: function(row, i, max) {
					return row.truename + ":" + row.ticket + ":" + row.uid + ':' + row.py;
				},
				formatResult: function(row) {
					return row.truename + ':' + row.uid;
				}
			})
			.result(function (event, data, formatted) {
				if (!data) {
					return false;
				}
				
				var result = formatted.split(':');
				var truename = result[0];
				var ticket = result[1];
				var uid = result[2];
				
				$(this).val(ticket);
				
				if (is_dialog == '0') {
					$('#txt_uid').val(uid);
					$('#txt_tmp_uid').val(truename);
				}
			});
	}
	
	window.set_ticket_autocomplete = set_ticket_autocomplete;
	
	/**
	 * 文字闪动
	 * @ele: dom对象
	 * @cls: 颜色变化的样式
	 * @times: 闪动次数
	 */
	function shake(ele, cls, times){
		var i = 0, t = false, o = ele.attr("class")+" ", c = "", times = times||2;
		if(t) return;
		
		t = setInterval(function(){
			i++;
			c = i%2 ? o+cls : o;
			ele.attr('class', c);
			if(i==2*times){
				clearInterval(t);
				ele.removeClass(cls);
			}
		}, 200);
	}

	/**
 *  *  * 开考提示对话框
 *   *   */
function start_testing_confirm_dialog() {
    if (screenfull.enabled && screenfull.isFullscreen) {
            return false;
    }
    var notice = [];
    notice.push('<strong>尊敬的监考人员，为了方便您的监考，您可以：</strong>');
    notice.push('<p>1、按F11键进入全屏模式，按Esc键即可退出全屏。</p>');
    $.Zebra_Dialog(notice.join(''), {
        'type':     'question',
        'title':    '系统温馨提示',
        'buttons':  ['全屏'],
        'width': 460,
        'onClose':  function(caption) {
            //关闭
        }
    });

	$window.resize(function () {setTimeout(function(){redraw_something();}, 400)});
	
    //关闭层 触发全屏
    $('a.ZebraDialog_Button_0, a.ZebraDialog_Close, #btn_fullscreen').click(function () {
         force_to_fullscreen();
        
     var $thiz = $('#btn_fullscreen'); 
     if (screenfull.enabled && screenfull.isFullscreen)  {
	   $thiz.html('退出全屏');
     } else {
         $thiz.html('全屏模式');
         }
    });
}

/**
 * 统计数据
 */
function statics_online() 
{
	/**
	 * 加载在线人数
	 */
	//开启定时器
	function set_online_timer() 
	{
		clear_online_timer();
		window.timer_load_online = setInterval(function () {
			//如果停留在分页 非第一页，则不
			load_statics_online();
		}, 10000);
	}	

	/*
	 * 清除计时器 
	 */
	function clear_online_timer() 
	{
		if (window.timer_load_online) {
			clearInterval(timer_load_online);
		}	
	}	
	
	//加载统计数据
	var load_statics_online = function () {
		$.ajax({
			url : config_url.get_onlines,
			dataType : 'json',
			type : 'get',
			data : {},
			timeout : 10000,
			error: function (a, b, c) {
				//alert(a + b + c);
			},
			success : function (response) {
				var code = response.code,
					msg	= response.msg,
					data = response.data;

				if (code >= 0) {
					_render_online(data);
					_render_submit(data);
					return true;
				}
			}
		});
	};
	
	//在线人数
	var $_span_onlines = $('#span_onlines');
	var _render_online = function (data) {
		var data = data.online;
		$_span_onlines.html(data);
		
		/*
		var old_count = $_span_onlines.html(),
		old_val = parseInt(old_count) || 0,
		new_val = parseInt(data) || 0,
		diff = new_val - old_val;
		if (diff == 0) {
			var _left = $_span_onlines.offset().left,
				_top = $_span_onlines.offset().top;
		
			$('<i>', {'style':'position:absolute;top:' + _top + ';left:' + _left, 'text' : diff}).appendTo($_span_onlines).animate({'left':'+=10', 'top':'-=10'}, 600, function () {$_span_onlines.html(data).hide().fadeIn();})
		}*/
	};
	
	//交卷人数
	var $_span_submit = $('#span_submit');
	var _render_submit = function (data) {
		var data = data.submit;
		var old_count = $_span_submit.html(),
			old_val = parseInt(old_count) || 0,
			new_val = parseInt(data) || 0,
			diff = new_val - old_val;
		
		$_span_submit.html(data);
	};
	
	load_statics_online();
	set_online_timer();
}
	
/**
 * 初始化设置
 */
function init()
{
	//设置tiper的消失时间间隔为 5s
	UtilTiper.message.dismissin = 2500;
	
//	start_testing_confirm_dialog();
	redraw_something();//设置部分高度
	
	load_data();//首次加载数据
	statics_online();//在线考生人数
	
	show_student_selecter_box();//学生筛选
	show_student_all_log_box();//考生所有事件
	
	search_logs();//筛选
	reset_invigilate_password();
	change_student_password();
	out_student();
	set_ticket_autocomplete();
}

init();
});
