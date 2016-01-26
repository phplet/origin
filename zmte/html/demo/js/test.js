/**
考生考试 业务处理
 */
_count_down = count_downs.submit_success;

var _g_refresh_timer_id = -1;

//交卷成功倒计时
function refresh_time ()
{
    if (_g_refresh_timer_id < 0)
    {
        return false;
    }
    if (_count_down <= 1) {
            unbind_onbeforunload();
            clearInterval(_g_refresh_timer_id);
            _g_refresh_timer_id = -1;
            window.location.href = config_urls.login;
            return false;
    }
    _count_down = _count_down - 1;
    $('#dialog_success_count_down').html(_count_down);
}

$(document).ready(function () {
	//定义局部变量
	var _is_single_mode = is_single_mode,//是否是单题模式
		_no_papers = false,//是否没有分配试卷
		subjects = subject_config,
		_exam_config = exam_config,
		paper_info = _exam_config.paper_info,
		$left_subject_ul = $('#left_subject_ul'),
		$left_question_div = $('#left_question_div'),
		$tool_bar_box = $('#tool_bar_box'),
		$question_detail_box = $("#question_detail_box"),
		$body = $('body'),
		$window = $('window'),
		$btn_prev_question = $('#btn_prev_question'),
		$btn_next_question = $('#btn_next_question'),
		$btn_finish = $('.btn_finish'),
		_student_info = student_info;
	
	//没有分配试卷提示
	var no_paper_tiper = function () {
		$.Zebra_Dialog('该场考试未分配试卷，请联系监考老师.', {
		    'type':     'warning',
		    'title':    '系统温馨提示',
		    'buttons':  ['我知道了'],
		    'onClose':  function(caption) {
		    	//关闭
    		 }
		   });
	};
	
	//初始化 科目
	var append_subject = function () {
		var subject_content = [],
			auto_count_subject = 0,
			_paper_info = paper_info,
			letters = ['A', 'B', 'C', 'D', 'E', 'F'],
			current_index = 0;
		
		if (_paper_info.constructor == 'Array' && !_paper_info.length) {
			_no_papers = true;
			no_paper_tiper();
			
			return false;
		}
		
		$.each(subjects, function (i, item) {
			var attach_class = '',
				current_paper = _paper_info[i];
			
			if (typeof(current_paper.etp_id) == 'undefined') {
				return false;
			}
			
			var	etp_id = current_paper.etp_id,
				_ques_num = current_paper.ques_num,
				_fullscore = current_paper.full_score;
			
			if (current_index == auto_count_subject) {
				attach_class = 'current';
			}
			subject_content.push('<li id="subject_' + etp_id + '" class="fl_l subject_menu  ' + attach_class + '" full_score="' + _fullscore + '" ques_num="' + _ques_num + '" etp_id="' + etp_id + '" subject_id="' + i + '" subject_name="' + item + '"><a href="###">' + item + '</a></li>');
			auto_count_subject++;
		});
		
		$left_subject_ul.html(subject_content.join(''));
		
		return true;
	};


	//初始化 题目列表
	var append_questions = function () {
		var current_subject_id = $('li.subject_menu.current').attr('subject_id');
		var auto_count = 0,//小题计数
			global_auto_count = 0,//题型计数
			current_paper = paper_info[current_subject_id],
			etp_id = current_paper.etp_id,
			paper_id = current_paper.paper_id,
			question_list = current_paper.question_list || [],
			question_content = [],
			current_index = 0,
			ch_capital = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十'];

		//获取题型总数
		var question_length = 0;
		$.each(question_list, function (i, item) {
			question_length++;
		});

		question_content.push('<div id="question_list_' + etp_id + '">');
		$.each(question_list, function (i, item) {
			//题组放最后一组
			var _capital = ch_capital[global_auto_count],
				_group_name = $.trim(item.group_name),
				_group_score = item.group_score,
				_is_group_type = item.type == 0;
			
			var _item_list = item.list,
				_length_item_list = 0,
				_first_item = null;

			$.each(_item_list, function (j, sub_item) {
				_length_item_list++;
				if (_first_item == null) {
					_first_item = sub_item;
				}
			});

			var _per_item_percent = _first_item.score;
			
			question_content.push('<div class="cls_ques">');
			question_content.push('    <div class="cls_space_title">');
			question_content.push('        <p class="cls_space_text"> ' + _capital + '、' + _group_name + '</p>');
			if (_is_group_type) {
				question_content.push('        <p class="cls_space_infor" id="q_list_score_' + _first_item.type + '" score="' + _first_item.score + '">(共' + _length_item_list + '题 \\ 总计' + _group_score + '分)</p>');
			} else {
				question_content.push('        <p class="cls_space_infor" id="q_list_score_' + _first_item.type + '" score="' + _first_item.score + '">(' + _per_item_percent + '分*' + _length_item_list + '题 \\ 总计' + _group_score + '分)</p>');
			}
			question_content.push('    </div>');
			
			if (_is_group_type) {
				question_content.push('    <ul class="cls_ques_item cls_q_item group">');
			} else {
				question_content.push('    <ul class="cls_ques_item cls_q_item cls_ques_item_float clearfix">');
			}
			
			$.each(_item_list, function (j, sub_item) {
				var _paper_id = paper_id,
					_type_id = sub_item.type,
					_score = sub_item.score,
					_ques_id = sub_item.ques_id,
					_do_or_undo = sub_item.do_or_undo,
					_etp_id = etp_id,
					_attrs = [];

				var current_index = auto_count+1;
				_attrs.push('paper_id=' + _paper_id);
				_attrs.push('type=' + _type_id);
				_attrs.push('score=' + _score);
				_attrs.push('ques_id=' + _ques_id);
				_attrs.push('etp_id=' + _etp_id);
				_attrs.push('current_index=' + current_index);
               
				if (_is_group_type) {
					question_content.push('<li class="cls_space sub_question '+ _do_or_undo +'" id="question_index_' + _etp_id + '_' + _ques_id + '" ' + (_attrs.join(' ')) + '><a href="' + (_is_single_mode ? '###' : '#') + 'question_item_' + + _etp_id + '_' + _ques_id + '"  name="anchor' + current_index + '"> ' + current_index + ' (' + _score + ' 分)</a></li>');
				} else {
					question_content.push('<li class="cls_space sub_question '+ _do_or_undo +'" id="question_index_' + _etp_id + '_' + _ques_id + '" ' + (_attrs.join(' ')) + '><a href="' + (_is_single_mode ? '###' : '#') + 'question_item_' + + _etp_id + '_' + _ques_id + '"  name="anchor' + current_index + '" > ' + current_index + '</a></li>');
				}
				auto_count++;
			});
			question_content.push('    </ul>');
			question_content.push('</div>');
			
			global_auto_count++;
		});
		
		question_content.push('</div>');
		
		$left_question_div.append(question_content.join(''));
		
		var $question_list = $('#question_list_' + etp_id);
		$question_list.show().siblings().hide();
		$first_question = $question_list.find('li.sub_question').eq(0).addClass('current');

		//点击查看某一题
		go_current_question();
		
		//获取第一个问题详情
		var question_id = _is_single_mode ? $first_question.attr('ques_id') : get_question_ids(etp_id);
		get_question_detail(etp_id, question_id);
	};
	
	//mini信息条科目切换
	var mini_subject_toggle = function () {
		$('input[type=radio].mini_subject_radio').unbind('click').bind('click', function () {
			var subject_id = $(this).val();
			$left_subject_ul.find('li[subject_id=' + subject_id + ']').trigger("click");
		});
	}

	// 切换科目
	var fn_change_subject = function () {
		//collect_question_data_to_submit(false);
		if (arguments.length > 0) {
			index = arguments[0];
		} else {
			index = 0;
		}

		var $thiz = $('#left_subject_ul').find('li.subject_menu').eq(index);

		_subject_id = $thiz.attr('subject_id'),
		
		_subject_name1 = $thiz.attr('subject_name');	
		
		if (_subject_name1=='英语') {
			//alert(config_urls.public_css);
			$('head link[href="'+config_urls.public_css+'"]').attr('href',config_urls.public_qt_css);
		} else {
		    $('head link[href="'+config_urls.public_qt_css+'"]').attr('href',config_urls.public_css);
	    }
	
		var $question_list = $('#question_list_' + $thiz.attr('etp_id'));

		$thiz.addClass('current').siblings().removeClass('current');

		if ($question_list.length) {
		   $question_list.show().siblings().hide();
		}
		
		//变化mini科目切换 
		$('.mini_subject_' + _subject_id).attr('checked', 'checked');
		$('#subject_score_tip_' + _subject_id).show().siblings().hide();

		// 是否为最后一个科目
		var is_last_subject = $('.subject_menu').last().hasClass('current');
		// 是否为第一个科目
		var is_first_subject = $('.subject_menu').first().hasClass('current');

		if (!is_last_subject) {
			$('#btn_next_question').removeClass('disabled');
		}

		if (!is_first_subject) {
			$('#btn_prev_question').removeClass('disabled');
		}

		var $question_detail = $('#question_detail_' + $thiz.attr('etp_id'));

        if ($question_detail.length) {
			$question_detail.show().siblings().hide();
			return true;
        }

        $('#btn_prev_question').removeClass('disabled');
        $('#btn_next_question').removeClass('disabled');

        append_questions();
        mini_subject_toggle();
    	
	}
	

	//切换科目,联动题目列表
	var change_subject = function () {
		
		$left_subject_ul
			.find('li.subject_menu')
			.unbind('click')
			.bind('click', function () {
				collect_question_data_to_submit(false);
			var $thiz = $(this),
				_subject_id = $thiz.attr('subject_id'),
			
			_subject_name1 = $thiz.attr('subject_name');	
			
			if(_subject_name1=='英语')
			{
				//alert(config_urls.public_css);
				$('head link[href="'+config_urls.public_css+'"]').attr('href',config_urls.public_qt_css);

			}
		   else
		   {
			   $('head link[href="'+config_urls.public_qt_css+'"]').attr('href',config_urls.public_css);
		   }
		
			
			var $question_list = $('#question_list_' + $thiz.attr('etp_id'));
			$thiz.addClass('current').siblings().removeClass('current');
			if ($question_list.length) {
			   $question_list.show().siblings().hide();
			}
			
			//变化mini科目切换 
			$('.mini_subject_' + _subject_id).attr('checked', 'checked');
			$('#subject_score_tip_' + _subject_id).show().siblings().hide();
			
			//变化总分
			//$('td.header_full_score').html(parseInt($thiz.attr('full_score'))+' 分');
			
			//试题总数
			//$('td.header_ques_num').html(parseInt($thiz.attr('ques_num'))+' 题');

			// 是否为最后一个科目
			var is_last_subject = $('.subject_menu').last().hasClass('current');
			
			// 是否为第一个科目
			var is_first_subject = $('.subject_menu').first().hasClass('current');

			if (!is_last_subject) {
				$('#btn_next_question').removeClass('disabled');
			}

			if (!is_first_subject) {
				$('#btn_prev_question').removeClass('disabled');
			}

			var $question_detail = $('#question_detail_' + $thiz.attr('etp_id'));

            if ($question_detail.length) {
               $question_detail.show().siblings().hide();
               return true;
            }

            $btn_prev_question.removeClass('disabled');
            $btn_next_question.removeClass('disabled');

			append_questions();	
			
		});
		
		mini_subject_toggle();
	};

	// 初始化科目选中项 修正单选选中科目与标签选中科目不一致
	var init_current_subject = function () {

		if (arguments.length > 0) {
			index = arguments[0];
		} else {
			index = 0;
		}

		var $thiz = $('#left_subject_ul').find('li.subject_menu').eq(index);

		_subject_id = $thiz.attr('subject_id');

		$('.mini_subject_' + _subject_id).attr('checked', 'checked');
	}

	//上一题
	var go_prev_question = function () {
		$btn_prev_question.removeClass('visibility_hide')
			.unbind('click')
			.bind('click', function () {
				if (_no_papers) {
					no_paper_tiper();
					return false;
				}
				var _etp_id = $('li.subject_menu.current').attr('etp_id'),
					$current_question_detail = $('#question_list_' + _etp_id),
					$current_sub = $current_question_detail.find('li.current'),
					$prev_sub = $current_sub.prev(),
					$thiz = $(this);
				
				
				collect_question_data_to_submit(false);
				
				if (!$prev_sub.hasClass('sub_question')) {
					$prev_sub = $current_sub.parent().parent().prev().find('li.sub_question').last();
				}

				// 是否为第一个科目
				var is_first_subject = $('.subject_menu').first().hasClass('current');
				
				//如果第一题，不做操作	
				if (!$prev_sub.length) {

					if (is_first_subject) {
						UtilTiper.message.success('同学，已经到第一题了哦~');
						$thiz.addClass('disabled');
						return false;
					} else {
						// 不是第一科目，跳转到上个科目
						fn_change_subject($('.current').index('.subject_menu') - 1);
						return false;
					}
					
				}
				
				$thiz.removeClass('disabled');
				if (!$prev_sub.prev().length 
					&& !$current_sub.parent().parent().prev().find('li.sub_question').last().length
					&& is_first_subject) {
					$thiz.addClass('disabled');
				}
				$btn_next_question.removeClass('disabled');
				
				$current_sub.removeClass('current');
				$prev_sub.addClass('current');		
				
				var container = $('.cls_ques_list');
			    container.scrollTop(
					$prev_sub.offset().top - container.offset().top + container.scrollTop()
			   );

				//获取问题详情
				var etp_id = $prev_sub.attr('etp_id'),
					question_id = $prev_sub.attr('ques_id');
				
				get_question_detail(etp_id, question_id);
		});
	};
	
	//下一题
	var go_next_question = function () {
		if (_no_papers) {
			$btn_next_question.addClass('disabled');
		} else {
			$btn_next_question.removeClass('disabled');
		}
		
		$btn_next_question.removeClass('visibility_hide')
			.unbind('click')
			.bind('click', function () {
				if (_no_papers) {
					no_paper_tiper();
					return false;
				}
				var _etp_id = $('li.subject_menu.current').attr('etp_id'),
					$current_question_detail = $('#question_list_' + _etp_id),
					$current_sub = $current_question_detail.find('li.current'),
					$next_sub = $current_sub.next(),
					$thiz = $(this);
				
				collect_question_data_to_submit(false);

				if (!$next_sub.hasClass('sub_question')) {
					$next_sub = $current_sub.parent().parent().next().find('li.sub_question').eq(0);
				}

				// 是否为最后一个科目
				var is_last_subject = $('.subject_menu').last().hasClass('current');

				//如果最后一 题，不做操作	
				if (!$next_sub.length) {

					if (is_last_subject) {
						$thiz.addClass('disabled');
						UtilTiper.message.success('同学，已经到最后一题了哦~');
						return false;
					} else {
						// 不是最后最后科目，跳转到下个科目
						fn_change_subject($('.current').index('.subject_menu') + 1);
						return false;
					}
				}
				
				$thiz.removeClass('disabled');

				if (!$next_sub.next().length 
						&& !$current_sub.parent().parent().next().find('li.sub_question').eq(0).length 
						&& is_last_subject) {
					$thiz.addClass('disabled');
				}
				
				$btn_prev_question.removeClass('disabled');
				
				$current_sub.removeClass('current');
				$next_sub.addClass('current');	
				
				
				var container = $('.cls_ques_list');
			    container.scrollTop(
			    		$next_sub.offset().top - container.offset().top + container.scrollTop()
			     );
			    
				//获取问题详情
				var question_id = $next_sub.attr('ques_id');
				get_question_detail(_etp_id, question_id);
		});
	};
	
	/*
	 * 变更上一题，下一题 状态 
	 */
	var change_question_bar_status = function () {
		var _etp_id = $('li.subject_menu.current').attr('etp_id'),
			$current_question_detail = $('#question_list_' + _etp_id),
			$current_sub = $current_question_detail.find('li.current'),
			current_index = $current_sub.attr('current_index'),
			total_questions = $current_question_detail.find('li.sub_question').length;

		// 是否为最后一个科目
		var is_last_subject = $('.subject_menu').last().hasClass('current');
		// 是否为第一个科目
		var is_first_subject = $('.subject_menu').first().hasClass('current');
		
		//变化上一题状态
		if (current_index > 1) {
			$btn_prev_question.removeClass('disabled');
			if (current_index >= total_questions && is_last_subject) {
				$btn_next_question.addClass('disabled');
			} else {
				$btn_next_question.removeClass('disabled');
			}
		} else {
			if (total_questions == 1 && is_first_subject) {
				$btn_prev_question.removeClass('disabled');
				$btn_next_question.hide();
			} else {
				if (is_first_subject) {
					$btn_prev_question.addClass('disabled');
					$btn_next_question.removeClass('disabled');
				} else {
					$btn_prev_question.removeClass('disabled');
					$btn_next_question.removeClass('disabled');
				}
				
			}
		}
	};
	
	//点击查看某一题
	var go_current_question = function () {
		var _etp_id = $('li.subject_menu.current').attr('etp_id'),
			$current_question_detail = $('#question_list_' + _etp_id),
			$sub_questions = $current_question_detail.find('li.sub_question');
		
		$sub_questions
			.unbind('click')
			.bind('click', function () {
				if (_no_papers) {
					no_paper_tiper();
					return false;
				}
				
				var $thiz = $(this),
					_etp_id = $thiz.attr('etp_id'),
					_ques_id = $thiz.attr('ques_id'),
					$current_question_detail = $('#question_list_' + _etp_id),
					$_sub_questions = $current_question_detail.find('li.sub_question');

		
				collect_question_data_to_submit(false);


				
				$_sub_questions.removeClass('current');	
				$thiz.addClass('current');	
				
				if (_is_single_mode) {
					change_question_bar_status();//变化上一题，下一题样式
				}
				
				//获取问题详情
				get_question_detail(_etp_id, _ques_id);
			});
	};

	//获取 当前科目试题ID集合
	var get_question_ids = function (etp_id) {
		var id_collect = [];
		$('#question_list_'+etp_id).find('li.sub_question').each(function () {
			var $thiz = $(this),
				question_id = $thiz.attr('ques_id');
			
			id_collect.push(question_id);
		});
		
		return id_collect.join(','); 
	};

	//获取当前一题或多题目详情
	var get_question_detail = function (etp_id, question_id) {
		var $current_subject_question_box = $('#question_detail_' + etp_id);
		//查看本地是否已经有当前科目的试题列表
		if ($current_subject_question_box.length) {
			if (_is_single_mode) {
				var $question_item = $('#question_item_' + etp_id + '_' + question_id);
				if ($question_item.length) {
					$question_item.show().siblings().hide();
					do_some_callback(etp_id, question_id);
					return false;
				}
			} else {
				$current_subject_question_box.show().siblings().hide();
				return false;
			}
		}
		
		var _post_data = {
				etp_id : etp_id,
				question_id : question_id,
				is_first : 1,
				is_single : _is_single_mode ? 1 : 0
		};
		
		var _is_first = true;
		if (_is_single_mode && $current_subject_question_box.length) {
			_post_data.is_first = 0;
			_is_first = false;
		}
		UtilTiper.message.doing('正在努力拉取试题，请稍等...');
		$.ajax({
			url : config_urls.get_question_detail,
			dataType : 'json',
			type : 'post',
			data : _post_data,
			timeout : 5000,
			error: function (a, b, c) {
				//alert(a + b + c);
				UtilTiper.message.loaded();
				$.Zebra_Dialog('<strong>好像试题拉取失败了哦~</strong>', {
				    'type':     'error',
				    'title':    '系统温馨提示',
				    'buttons':  [
				                    {caption: '重新拉取', callback: function() {get_question_detail(etp_id, question_id);}}/*,
				                    {caption: '刷新页面', callback: function() {
					                    	unbind_onbeforunload();
					                    	window.location.reload();
			                    		}
				                    }*/
				                ]
				});
				
				return false;
			},
			success : function (response) {
				var code = response.code,
					msg = response.msg,
					data = response.data;
				if (code < 0) {
					alert(msg);
					return false;
				}
					
				var _append_data = '<div class="question_detail_div" id="question_detail_' + etp_id + '">' + data + '</div>';
				if (_is_single_mode) {
					if (!_is_first) {
						var _append_data = data,
							_change_type_class = ['cls_danxuan', 'cls_danxuan', 'cls_budingxiang', 'cls_tiankong'];
						
						$current_subject_question_box.find('div.question_box').append(_append_data);
						var _type = $('#question_index_' + etp_id + '_' + question_id).attr('type');
						$('#question_change_box_' + etp_id).attr('class', _change_type_class[_type]);
						$('#question_item_' + etp_id + '_' + question_id).show().siblings().hide();
						
					} else {
						$('.question_detail_div').hide();
						$question_detail_box.append(_append_data);
					}
				} else {
					$('.question_detail_div').hide();
					$question_detail_box.append(_append_data);
				}
				
				//回调事件绑定
				do_some_callback(etp_id, question_id);

				return false;
			}
		});
	};

	//试题加载完 回调事件绑定
	var do_some_callback = function (etp_id, question_id) {
		//绑定问题选项
		do_answer_options_questions();

		//填空题，题组绑定
		do_answer_other_questions();
		
		//填充问题列表分值
		fill_question_score(etp_id);
		
		//填充试题详情序号
		fill_question_index(etp_id, question_id);
		
		//绑定“标记本题”
		bind_mark_question();

		//绑定鼠标聚焦定位到当前一题，高亮试题序号列表
		locate_current_question_hover();
		
		UtilTiper.message.loaded();
		
		//自定义radio 和 checkbox样式
		// $('#question_box').find('input[type=checkbox]').customInput();
	}

	//填充问题列表分值
	var fill_question_score = function (etp_id) {
		var types = [0, 1, 2, 3],
			_length = types.length;
		
		for (var i = 0; i < _length; i++) {
			$('#q_score_' + etp_id + '_' + types[i]).html($('#q_list_score_' + types[i]).attr('score'));
		}
	};
	
	//填充试题详情序号
	var fill_question_index = function (etp_id, question_id) {
		if (_is_single_mode) {
			
			$('#q_index_' + etp_id + '_' + question_id).html($('#question_list_' + etp_id).find('li.current').attr('current_index'));
		}
	};

	//单选，多选题 答题
	var do_answer_options_questions = function () {
		$question_detail_box.find('li.question_option')
			.unbind('click')
			.bind('click', function (e) {
				var $thiz = $(this),
					type = $thiz.attr('type'),
					ques_id = $thiz.attr('question_id'),
					etp_id = $thiz.attr('etp_id'),
					$question_li = $('#question_index_' + etp_id + '_' + ques_id),
				    $question_detail_li = $('#question_item_' + etp_id + '_' + ques_id);
				
				if (type == 'radio') {
					//单选
					$thiz.find('input[type=radio]').attr('checked', 'checked');
					if ($question_li.hasClass('done') && !$question_detail_li.hasClass('undo')) {
						return true;
					}
					$question_detail_li.removeClass('undo');
					$question_li.addClass('done').removeClass('q_i_undo');
					
				} else {
					//多选
					// var target = (event.target)?event.target:event.srcElement;
					// 	tagname = target.tagName.toLocaleLowerCase(),
					// 	_is_checkbox = tagname == 'input' || tagname == 'label' || tagname == 'label input';
					
					// if (!_is_checkbox) {
					// 	var $_checkbox = $thiz.find('input[type=checkbox]');
					// 	if ($_checkbox.is(':checked')) {
					// 		$_checkbox.removeAttr('checked');
					// 	} else {
					// 		$_checkbox.attr('checked', true);
					// 	}
					// }
					
					if ($question_detail_li.find('input[type=checkbox]:checked').length > 0) {
						$question_detail_li.removeClass('undo');
						$question_li.addClass('done').removeClass('q_i_undo');
					} else {
						$question_detail_li.addClass('undo');
						$question_li.removeClass('done').addClass('q_i_undo');
					}
				}
		// e.stopPropagation();	
		});
	};
	
	//绑定“标记本题”
	var bind_mark_question = function () {
		$question_detail_box.find('input.question_mark')
			.unbind('click')
			.bind('click', function () {
				var $thiz = $(this),
					$question_index = $('#' + $thiz.attr('question_index'));
				
				if ($thiz.is(':checked')) {
					$question_index.addClass('mark');
				} else {
					$question_index.removeClass('mark');
				}
			});
	};
	
	//绑定鼠标聚焦定位到当前一题，高亮试题序号列表
	var locate_current_question_hover = function () {
		//$('div.sub_question')).click();	
		
		$('div.question_item')
			.unbind('hover')
			.hover(function () {
				$('#' + $(this).attr('question_index')).addClass('current_hover').siblings().removeClass('current_hover');
				//$(this).click();
				
				
				
			}, function () {
				$('#' + $(this).attr('question_index')).removeClass('current_hover');
			});
	};

	//填空题，连词成句，题组绑定
	var do_answer_other_questions = function () {
		//绑定填空题
		$question_detail_box.find('input[type=text].type_3')
				.unbind('keyup paste cut')
				.bind('keyup paste cut', function () {
					var $thiz = $(this);
					setTimeout(function () {
							_answer = $thiz.val().trim().replace(/\\n/g, ''),
							ques_id = $thiz.attr('ques_id'),
							$question_detail_li = $question_detail_box.find('div.type_3_' + ques_id),
							$question_li = $('#' + $question_detail_li.attr('question_index'));
						
						if (_answer == '') {
							$thiz.removeClass('sub_done').addClass('sub_undo');
						} else {
							$thiz.removeClass('sub_undo').addClass('sub_done');
						}
						
						var _sub_undo_length = $question_detail_li.find('input[type=text].sub_undo').length;
						if (!_sub_undo_length) {
							/**
							 * undone / nodone：未做完全
							 */
							$question_detail_li.removeClass('undone').removeClass('undo');
							$question_li.addClass('done').removeClass('q_i_undo').removeClass('nodone');
						} else {
							var _sub_done_length = $question_detail_li.find('input[type=text].sub_done').length;//已答数
							if (!_sub_done_length) {
								/**
								 * 完全未答
								 */
								$question_detail_li.removeClass('undone').addClass('undo');
								$question_li.removeClass('nodone').removeClass('done').addClass('q_i_undo');
							} else {
								/**
								 * 已答部分，未答完全
								 */
								$question_detail_li.removeClass('undo').addClass('undone');
								$question_li.removeClass('done').removeClass('q_i_undo').addClass('nodone');
							}
						}
					}, 500);
			});

			$question_detail_box.find('input[type=text].type_9').unbind('keyup paste cut').bind('keyup paste cut', function () {
				var $thiz = $(this);
				setTimeout(function () {
					_answer = $thiz.val().trim().replace(/\\n/g, ''),
					ques_id = $thiz.attr('ques_id'),
					$question_detail_li = $question_detail_box.find('div.type_9_' + ques_id),
					$question_li = $('#' + $question_detail_li.attr('question_index'));
					
					if (_answer == '') {
						$thiz.removeClass('sub_done').addClass('sub_undo');
					} else {
						$thiz.removeClass('sub_undo').addClass('sub_done');
					}
					
					var _sub_undo_length = $question_detail_li.find('input[type=text].sub_undo').length;

					if (!_sub_undo_length) {
						/**
						 * undone / nodone：未做完全
						 */
						$question_detail_li.removeClass('undone').removeClass('undo');
						$question_li.addClass('done').removeClass('q_i_undo').removeClass('nodone');
					} else {
						var _sub_done_length = $question_detail_li.find('input[type=text].sub_done').length;//已答数
						
						if (!_sub_done_length) {
							/**
							 * 完全未答
							 */
							$question_detail_li.removeClass('undone').addClass('undo');
							$question_li.removeClass('nodone').removeClass('done').addClass('q_i_undo');
						} else {
							/**
							 * 已答部分，未答完全
							 */
							$question_detail_li.removeClass('undo').addClass('undone');
							$question_li.removeClass('done').removeClass('q_i_undo').addClass('nodone');
						}
					}
				}, 500);
			});
		
		
		//绑定题组
		var change_group_question = function (p_id) {
			var $p_question = $('#' + p_id);
			if (!$p_question.length) {
				return false;
			}
			if (!$p_question.find('div.c_undo').length) {
				$('#' + $p_question.attr('question_index')).addClass('done').removeClass('nodone').removeClass('q_i_undo');
			} else {
				if (!$p_question.find('div.c_done').length) {
					/**
					 * 完全没做
					 */
					$('#' + $p_question.attr('question_index')).removeClass('done').removeClass('nodone').addClass('q_i_undo');
				} else {
					/**
					 * 只答部分
					 */
					$('#' + $p_question.attr('question_index')).removeClass('done').removeClass('q_i_undo').addClass('nodone');
				}
			}
		};
		var types = [1, 2, 3];
		$.each(types, function (i, item) {
			var $target = $('input.type_0_' + item);
			if (!$target.length) {
				return;
			}
			
			if (item == 1) {
				//单选
				$target.unbind('click').bind('click', function () {
					var $thiz = $(this),
						ques_id = $thiz.attr('p_ques_id'),
						g_k = $thiz.attr('g_k'),
						$question_detail_li = $('#type_0_1_' + ques_id + '_' + g_k);
					
					$question_detail_li.removeClass('c_undo').addClass('c_done');
					change_group_question($question_detail_li.attr('p_id'));
				});
				
			} else if (item == 2) {
				//多选(不定项)
				$target.unbind('click').bind('click', function () {
					var $thiz = $(this),
						ques_id = $thiz.attr('p_ques_id'),
						g_k = $thiz.attr('g_k'),
						$question_detail_li = $('#type_0_2_' + ques_id + '_' + g_k);
					
					if ($question_detail_li.find('input[type=checkbox]:checked').length > 0) {
						$question_detail_li.removeClass('c_undo').addClass('c_done');
					} else {
						$question_detail_li.removeClass('c_done').addClass('c_undo');
					}
					change_group_question($question_detail_li.attr('p_id'));
				});
				
			} else if (item == 3) {
				//填空
				$target.unbind('keyup paste cut').bind('keyup paste cut', function () {
						var $thiz = $(this);
						setTimeout(function () {
								var _answer = $thiz.val().trim().replace(/\\n/g, ''),
									ques_id = $thiz.attr('p_ques_id'),
									g_k = $thiz.attr('g_k'),
									$question_detail_li = $('#type_0_3_' + ques_id + '_' + g_k);
							
							if (_answer == '') {
								$thiz.addClass('sub_undo');
							} else {
								$thiz.removeClass('sub_undo');
							}
							
							if (!$question_detail_li.find('input[type=text].sub_undo').length) {
								$question_detail_li.removeClass('c_undo').addClass('c_done');
							} else {
								$question_detail_li.removeClass('c_done').addClass('c_undo');
							}
							
							change_group_question($question_detail_li.attr('p_id'));
						}, 500); 
					
				});

			}
		});
	};

	//提交试卷
	var do_submit = function () {
		$btn_finish.unbind('click').bind('click', function () {
			if (_no_papers) {
				no_paper_tiper();
				return false;
			}
			//查看是否答题有未答题目
			var etp_id = $('.subject_menu.current').attr('etp_id');
			
			if (_is_single_mode) {
				var $undo = $left_question_div.find('li.q_i_undo'),//未答
					$nodone = $left_question_div.find('li.nodone');//只答部分
				if ($undo.length || $nodone.length) {
					var _count_done = $left_question_div.find('li.done').length,//已答
						_count_nodone = $left_question_div.find('li.nodone').length,//只答部分,整题未答完
						_count_undo = global_ques_num - (_count_done + _count_nodone);//未答
					
					_count_undo = _count_undo < 0 ? 0 : _count_undo;
					
					var tip_content = '您还有未答完的题目:\n\n 已答：' +　_count_done　 + ' 题\n 未答：' + _count_undo + ' 题 \n 未答完整：' + _count_nodone + ' 题\n\n是否还要继续交卷? 点“取消”可继续答题';
					
					var _confirm = win_confirm(tip_content, function () {
						//转到未答位置
						var $first_undo = $undo.eq(0),
						scroll_top = $first_undo.offset().top || 0;
						
						$body
							.stop(true, true)
							.animate({'scrollTop':_is_single_mode ? 0 : (scroll_top - 110)}, 300, function () {
								//文字闪动提醒
								shake($undo.find('a'), 'shake_red', 4);
							});
					});
					if (!_confirm) {
						return false;
					}
				}
			} else {
				var $undo = $left_question_div.find('li.q_i_undo');
				if ($undo.length) {
					var _count_done = $left_question_div.find('li.done').length,
						_count_undo = global_ques_num - _count_done;
					
					var tip_content = '您还有未答完的题目:\n\n 已答：' +　_count_done　 + ' 题\n 未答：' + _count_undo + ' 题 \n\n是否还要继续交卷? 点“取消”可继续答题';
					var _confirm = win_confirm(tip_content, function () {
						//转到未答位置
						var $first_undo = $undo.eq(0),
						scroll_top = $first_undo.offset().top || 0;
						
						$body
						.stop(true, true)
						.animate({'scrollTop':_is_single_mode ? 0 : (scroll_top - 110)}, 300, function () {
							//文字闪动提醒
							shake($first_undo, 'shake_red', 4);
							shake($('#' + $first_undo.attr('question_index')).find('a'), 'shake_red', 4);
						});
					});
					if (!_confirm) {
						return false;
					}
				}
			}
			
			
			//是否有标记题目
			var $marked = $question_detail_box.find('input.question_mark:checked');
			if ($marked.length) {
				var _confirm = win_confirm('您还有标记的题目，是否还要继续交卷?\n\n点“取消”可继续答题', function () {
					//转到未答位置
					var $first_mark = $marked.eq(0),
						scroll_top = $first_mark.offset().top || 0;
					
					$body
						.stop(true, true)
						.animate({'scrollTop':_is_single_mode ? 0 : (scroll_top - 210)}, 300, function () {
							//文字闪动提醒
							shake($first_mark, 'shake_red', 4);
							shake($('#' + $first_mark.attr('question_index')).find('a'), 'shake_red', 4);
					});
				});
				
				if (!_confirm) {
					return false;
				}
			}

			//搜集问题答案，提交试卷
			collect_question_data_to_submit(true);
		});
	};

	/**
	 * 收集考生答题记录，并提交
	 */
	var collect_question_data_to_submit = function (is_all,is_force, callback, is_time_end) {
		var is_all = typeof is_all == 'undefined' ? false : is_all,
		     is_force = typeof is_force == 'undefined' ? false : is_force,
			callback = typeof callback == 'undefined' ? false : callback,
			is_time_end = typeof is_time_end == 'undefined' ? false : is_time_end;//是否倒计时自动提交
		//如果为提交试卷
		if(is_all)
		{
			if (_no_papers) {
				$.Zebra_Dialog('该场考试马上结束了，由于没有分配试卷，系统将在 5 秒后自动关闭该页面.', {
				    'type':     'warning',
				    'title':    '系统温馨提示',
				    'buttons':  [],
				    'onClose':  function(caption) {
				    	//关闭
		    		 }
				   });
				setTimeout(function () {close_window}, 5000);
				return false;
			}
			
		}


		var _etp_id = $('li.subject_menu.current').attr('etp_id'),
		$current_question_detail = $('#question_list_' + _etp_id),
		$current_sub = $current_question_detail.find('li.current'),
		$next_sub = $current_sub.next(),
		$thiz = $(this);

		var paper_data = [],
			  has_error = false,
		post_data = {};
		post_data.uid = _student_info.uid;
		post_data.place_id = _exam_config.place_id;
		//试题集合
		var _question_data = [];

		var tmp_paper_data = {};

		tmp_paper_data.paper_id = $current_sub.attr('paper_id');
		tmp_paper_data.etp_id = _etp_id;

		var ques_id=$current_sub.attr('ques_id');
		var question_item='#question_item_'+_etp_id+'_'+ques_id;		

		var $question_box = $(question_item);
		//alert($question_box);

		/*
		 * todo
		 * 单选
		 * 不定项
		 * 填空
		 * 题组
		 */
		//单选
		  $question_box.find('input.type_1:checked').each(function () {
				var $thiz = $(this);
				_question_data.push({'etr_id':$thiz.attr('etr_id'), 'answer':$thiz.val()});
		});
	
		//不定项
		var _type2 = {};
		$question_box.find('input.type_2:checked').each(function () {
			var $thiz = $(this),
				_etr_id = $thiz.attr('etr_id'),
				_answer = $thiz.val();

			if (!_type2[_etr_id]) {
				_type2[_etr_id] = [];
			}
			
			_type2[_etr_id].push(_answer);
		});

		$.each(_type2, function (i, item) {
			_question_data.push({'etr_id':i, 'answer':item.join(',')});
		});

		//填空
		var _type3 = {};
		$question_box.find('input.type_3').each(function () {
			var $thiz = $(this),
				_ques_id = $thiz.attr('ques_id'),
				_etr_id = $question_box.attr('etr_id'),
				_answer = $thiz.val().trim().replace(/\\n/g,'');

			if (!_type3[_etr_id]) {
				_type3[_etr_id] = {};
				_type3[_etr_id].data = [];
				_type3[_etr_id].is_empty = true;
			}

			if (_answer.length) {
				_type3[_etr_id].is_empty = false;
			}

			_type3[_etr_id].data.push(_answer);
		});

		$.each(_type3, function (i, item) {
			if (!item.is_empty) {
				_question_data.push({'etr_id':i, 'answer':item.data.join("\n")});
			}
		});

		//连词填空
		var _type9 = {};
		$question_box.find('input.type_9').each(function () {
			var $thiz = $(this),
				_ques_id = $thiz.attr('ques_id'),
				_etr_id = $question_box.attr('etr_id'),
				_answer = $thiz.val().trim().replace(/\\n/g,'');

			if (!_type9[_etr_id]) {
				_type9[_etr_id] = {};
				_type9[_etr_id].data = [];
				_type9[_etr_id].is_empty = true;
			}

			if (_answer.length) {
				_type9[_etr_id].is_empty = false;
			}

			_type9[_etr_id].data.push(_answer);
		});
		
		$.each(_type9, function (i, item) {
			if (!item.is_empty) {
				_question_data.push({'etr_id':i, 'answer':item.data.join("\n")});
			}
		});

		//题组
		/*
		 * todo 
		 *  	单选
		 *  	不定项
		 *  	填空
			 */ 
		var _type0_2 = {},
			_type0_3 = {};

		//题组->单选
		$question_box.find('input.type_0_1:checked').each(function () {
			var $thiz = $(this);
			_question_data.push({'etr_id':$thiz.attr('etr_id'), 'answer':$thiz.val()});
		});

		//题组->不定项
		var _type0_2 = {};
		$question_box.find('input.type_0_2:checked').each(function () {
			var $thiz = $(this),
				_etr_id = $thiz.attr('etr_id'),
				_answer = $thiz.val();

			if (!_type0_2[_etr_id]) {
				_type0_2[_etr_id] = [];
			}
			
			_type0_2[_etr_id].push(_answer);
		});

		$.each(_type0_2, function (i, item) {
			_question_data.push({'etr_id':i, 'answer':item.join(',')});
		});

		//题组->填空
		var _type0_3 = {};
		$question_box.find('input.type_0_3').each(function () {
			var $thiz = $(this),
				_ques_id = $thiz.attr('p_ques_id'),
				g_k = $thiz.attr('g_k'),
				$question_detail_li = $('#type_0_3_' + _ques_id + '_' + g_k);
				_etr_id = $question_detail_li.attr('etr_id'),
				_answer = $thiz.val().trim().replace(/\\n/g,'');

			if (!_type0_3[_etr_id]) {
				_type0_3[_etr_id] = {};
				_type0_3[_etr_id].data = [];
				_type0_3[_etr_id].is_empty = true;
			}

			if (_answer.length) {
				_type0_3[_etr_id].is_empty = false;
			}

			_type0_3[_etr_id].data.push(_answer);
		});

		$.each(_type0_3, function (i, item) {
			_question_data.push({'etr_id':i, 'answer':item.data.join("\n")});
		});	
	
		tmp_paper_data.question = _question_data;
	
		paper_data.push(tmp_paper_data);

		post_data.paper_data = paper_data;
		//如果是提交试卷
		if(is_all)
		{		
			if (!is_force) {
				UtilTiper.message.doing('正在交卷，请稍等...');
			} else {
				if (!is_time_end) {
					post_data.is_c = '1';
				}
			}
		}
	
		//序列化考试数据->toJson
		post_data = $.toJSON(post_data);
		if(is_all)
		{	
			var submit_url=config_urls.submit_test;
		}
		else
		{
			var submit_url=config_urls.submit_test_p;
		}


		

		$.ajax({

			url : submit_url,
			dataType : 'json',
			type : 'post',
			data : {
				post_data : post_data
			},
			timeout : 5000,
			error: function (a, b, c) {
				//alert(a + b + c);
				$btn_finish.removeClass('disabled');
			},
			success : function (response) {
				var code = response.code,
					msg = response.msg,
					data = response.data;

				if(is_all)
				{	
					has_submited = true;
					UtilTiper.message.success(msg);

					if (is_force) {
						callback()
					} else {
						submit_success_redirect();
					}	
				}

			}
		});

		if (has_error) {
			return false;
		}


		if(is_all)
		{	
			$btn_finish.addClass('disabled');
		}
		
	};
	
	//强制交卷
	window.force_to_submit = function (callback, is_time_end) {
		collect_question_data_to_submit(true,true, callback, is_time_end);
	}
	
	/**
	 * 提交试卷成功反馈
	 */
	var submit_success_redirect = function () {
		$.Zebra_Dialog('恭喜你，交卷成功，系统将在 <strong id="dialog_success_count_down">' + _count_down + '</strong> 秒后跳转测评报告生成页.', {
		    'type':     'confirmation',
		    'title':    '系统温馨提示',
		    'buttons':  [],
		    'onClose':  function(caption) {
		    	//关闭
    		}
	   });
		
            _g_refresh_timer_id = setInterval("refresh_time()", 1000);
	}

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
	 * 考生自行关闭浏览器 提示
	 */
	function test_onbeforeunload() {
	    window.onbeforeunload = function(e) {
	    	is_onbeforeunload = true;
	    	return (e || window.event).returnValue="确认试卷提交过才可以关闭窗口，否则考试信息将丢失，自负后果哦！";
	    }
	}
	
	/**
	 * 消除事件绑定
	 */
	function unbind_onbeforunload() 
	{ 
		window.onbeforeunload = null; 
	}

	window.unbind_onbeforunload = unbind_onbeforunload;
	
	function win_confirm(msg, callback) 
	{ 
		if (!confirm(msg)) {
			pass_blur_check = true;
			callback();
			$window.focus();
			
			setTimeout(function () {pass_blur_check = false;}, 600);
			
			return false;
		} 
		
		return true;
	} 
	
	/**
	 *  
	 */
	function resize_something() 
	{
		
	}
	
	/**
	 * auto ping 
	 */
	// (function(){
	// 	var log = function (msg) {
	// 		if (window.console) window.console.log(msg);
	// 	};
	// 	var ping = function() {
	// 		var _location = window.location;
	// 		var sec = 10;
	// 		var timeout = sec * 1000;
	//         var url = config_urls.ping;
	//         $.ajax({
	//         	type: "HEAD",
	//             url: url,
	//             complete: function(xhr, status,data) {
	//         		if (status == 'success') {
 //        				// var result = xhr.getResponseHeader('Ping');
	//         			// if (result) {
	//         			// 	log('ping:success!');
	//         			// }
	//         		}
	//             },
	//             timeout: timeout,
	//             error: function(xhr, status) {
	//             	log('ping:error:' + status);
	//             }
	//         });
	// 	};
	// 	setInterval(function () {ping();}, 15000);
	// 	ping();
	// })();
	

	
	
	
	
	
	
	
	
	
	function _init() {

		test_onbeforeunload();

		if (append_subject()) {

			prevent_cheating();
			start_testing_confirm_dialog();
			append_questions();
			change_subject();
			// 初始化当前科目 修正单选科目与tab科目不一致
			init_current_subject();
		}

		do_submit();

		//单题模式
		if (_is_single_mode) {
			$('#tool_bar_menu').removeClass('bar_menu');
			go_prev_question();//上一题
			go_next_question();//下一题
			go_current_question();//查看某一题
		}
		
		if ($('#left_subject_ul li').length > 1)
		{
			$('#left_question_div').css('width', (parseInt($('#left_subject_ul').css('width'))+1));
		}
		else
		{
			$('#left_question_div').css('width', (parseInt($('#left_subject_ul').css('width'))+2));
		}
	}
	
	_init();
	
});
