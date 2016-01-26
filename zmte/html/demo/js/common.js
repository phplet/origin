/**
 * 考生考试界面控制
 * @author qfb 
 * @create 2013-12-13
 */
/*
var userAgent = navigator.userAgent.toLowerCase();
var is_opera = userAgent.indexOf('opera') != -1 && opera.version();
var is_moz = (navigator.product == 'Gecko') && userAgent.substr(userAgent.indexOf('firefox') + 8, 3);
var is_ie = (userAgent.indexOf('msie') != -1 && !is_opera) && userAgent.substr(userAgent.indexOf('msie') + 5, 3);
var is_real_ie = is_ie && userAgent.indexOf('.net') < 0;
var is_chrome = userAgent.indexOf('chrome') != -1 && userAgent.substr(userAgent.indexOf('chrome') + 7, 3);
var is_safari = userAgent.indexOf('safari') != -1 && userAgent.substr(userAgent.indexOf('safari') + 7, 3);
*/

// 去空格
String.prototype.trim = function() {  
    return this.replace(/(^\s*)|(\s*$)/g, "");
}

/**
 * 控制浏览器版本
 * 只支持ie系列，chrome //safari   opera   mozilla 
 */
function control_agent_version()
{
//	if (!($.ua().isIe || $.ua().isChrome)) {
//		alert('不支持当前浏览器， 建议使用ie 或 chrome浏览器');
//		return false;
//	}
//	
//	return true;
}

/**
 * 显示对话框
 */
function show_dialog(msg, type) {
	var type = type || 'tip';
	
	if (type == 'tip') {
		new $.Zebra_Dialog(msg, {
			'buttons':  false,
			'modal': false,
			'position': ['right - 20', 'top + 20'],
			'auto_close': 2000
		});
	}
}

//关闭窗口
function close_window()
{
	window.open('','_self');
	window.close();
}

/**
 *  * 强制使浏览器全屏（模拟F11键）
 *   */
function force_to_fullscreen() {
	if (screenfull.enabled && screenfull.isFullscreen) {
			screenfull.exit();
			return false;
	}
	try {
		screenfull.request();  plugin.close();
	} catch(error){
		//do nothing
	}
		
	jQuery(window).focus();//焦点聚焦在当前窗口
}

/*
 *  屏蔽部分按键功能
 */
function disable_keydown() { 
    var _window = window,
	_document = document;
	
    _document.oncontextmenu = new Function("event.returnValue=false;"); //禁止右键功能,单击右键将无任何反应
    _document.onselectstart = new Function("event.returnValue=false;"); //禁止选择,也就是无法复制
    _document.onkeydown = function () {
		//屏蔽鼠标右键、Ctrl+n、shift+F10、F5刷新、退格键  
	    if ((event.altKey) &&
	            ((_window.event.keyCode == 37) || //屏蔽   Alt+   方向键   ←  
	                    (_window.event.keyCode == 39))) {     //屏蔽   Alt+   方向键   →  
	        event.returnValue = false;
	    }
	    
	    if (/*(event.keyCode == 8) || *///屏蔽退格删除键  
	            (event.keyCode == 116) || //屏蔽   F5   刷新键  
	            (event.keyCode == 112) || //屏蔽   F1   刷新键  
	            (event.keyCode == 122) || //屏蔽   F11   刷新键  
	            (event.keyCode == 123) || //屏蔽   F12   刷新键  
	            (event.ctrlKey && event.keyCode == 82)) {   //Ctrl   +   R  
	        event.keyCode = 0;
	        event.returnValue = false;
	    }
	    if ((event.ctrlKey) && (event.keyCode == 78 || event.keyCode == 83))       //屏蔽   Ctrl+n  or Ctrl + s
	        event.returnValue = false;
	    if ((event.shiftKey) && (event.keyCode == 121))   //屏蔽   shift+F10  
	        event.returnValue = false;
	    if (_window.event.srcElement.tagName == "A" && _window.event.shiftKey)
	        _window.event.returnValue = false;     //屏蔽   shift   加鼠标左键新开一网页  
	    if ((_window.event.altKey) && (_window.event.keyCode == 115)) {   //屏蔽Alt+F4  
	        _window.showModelessDialog("about:blank", "", "dialogWidth:1px;dialogheight:1px");
	        return   false;
	    }
	   
	    if ((_window.event.altkey) && (_window.event.escKey)) {
		event.returnValue = false;
		return false;
	    }
	};//控制按键
}

//

/*
//监听部分 不被允许按键（提示用户）
function listen_keydown() {
	var kk = new Kibo();
	
	//不允许按alt、tab
    kk.down(['alt', 'tab'], handlerAlt);
    function handlerAlt() {
        //show_dialog('认真答题，不允许使用ALT或TAB键！ ');
        return false;
    }

    //f5
    kk.down('f5', function() {
        //show_dialog('认真答题，不允许刷新页面！ ');
        return false;
    });
}*/

/**
 * 消除事件绑定
 */
function unbind_onbeforunload() 
{ 
	window.onbeforeunload = null; 
} 

/**
 * 初始化考试
 */
function init_test_control() {
//	disable_keydown();
}

//表单验证(自定义错误)
function show_form_error(obj) {
    $.each(obj, function(i, item){
        var msg = item.msg,
            o = item.obj;

        var $o = $(o).focus()
		              .addClass('error')
		              .bind('keyup', function(e) {
		                var  keycode=e.keyCode;
		                if (keycode == '13') {
		                    return false;
		                }
		                var $obj = $(this).removeClass('error').parent().find('font').eq(0);
		                if ($o.prev().hasClass('notice')) {
		                	$o.prev().show();
		                }
		                $obj.html('');
              	}).parent().find('font').eq(0);
        
        if ($o.prev().hasClass('notice')) {
        	$o.prev().hide();
        }
        $o.html(msg);
    });

    $(obj[0].obj).focus();
}

function get_base_url()
{
	var _location = window.location;
	return _location.protocol + '//' + _location.hostname;
}

$(function() {
	//placeholder
	$('input.placeholder').placeholder();
	
	init_test_control();
	/*
	if (control_agent_version()) {
		init_test_control();
	} else {
		$('body').hide();
	}*/
});

/**
 * 密码验证
 */
function is_password(passwd, is_strong)
{
	is_strong = is_strong || false;
	var passwd = (passwd instanceof jQuery) ? passwd.val() : (typeof passwd === 'string' ? passwd : passwd.value);
	
	if (is_strong)
	{
		var num = 0;
	    if(passwd.search(/[0-9]/)!=-1)
	    {
	  		num+=1;
	  	}
	    
		if(passwd.search(/[A-Z]/)!=-1 || passwd.search(/[a-z]/)!=-1)
	  	{
	 		num+=1;
	    }
		
	  	if(passwd.search(/[^A-Za-z0-9]/)!=-1)
	  	{
	  		num+=1;
	  	}
	  	
	  	if(num>2 && (passwd.length>=6 && passwd.length<=20))
	  	{
		    return true;
	    }
	    else
	    {
		    return '密码必须同时包含数字、字母、符号，区分大小写， 长度为6~20个字符';
	    }
	}
	else
	{
		if(passwd.length >= 6 && passwd.length <= 20)
	  	{
		    return true;
	    }
	    else
	    {
		    return '密码长度必须为6~20个字符';
	    }
	}
}