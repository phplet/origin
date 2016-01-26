// 本js在jquery后加载
var charset = document.charset;
var userAgent = navigator.userAgent.toLowerCase();
var is_opera = userAgent.indexOf('opera') != -1 && opera.version();
var is_moz = (navigator.product == 'Gecko') && userAgent.substr(userAgent.indexOf('firefox') + 8, 3);
var is_ie = (userAgent.indexOf('msie') != -1 && !is_opera) && userAgent.substr(userAgent.indexOf('msie') + 5, 3);
var is_chrome = userAgent.indexOf('chrome') != -1 && userAgent.substr(userAgent.indexOf('chrome') + 7, 3);
var is_safari = userAgent.indexOf('safari') != -1 && userAgent.substr(userAgent.indexOf('safari') + 7, 3);

// 去空格
String.prototype.trim = function() {  
    return this.replace(/(^\s*)|(\s*$)/g, "");
}

// 数组搜索
//Array.prototype.indexof = function(value) {
//    for (var i in this) {
//        if(i==value) return this[i];
//    }
//    return null;
//}

function in_array(needle, haystack) {
    if(typeof needle == 'string' || typeof needle == 'number') {
        for(var i in haystack) {
            if(haystack[i] == needle) {
                return true;
            }
        }
    }
    return false;
}

function del_array(key, arr) {
    for (var i=0; i<arr.length; i++) {
        
    }
}

// 判断是否为数字
function is_numeric(str) {
    var patn = /^[0-9-\/]+$/;
    if(!patn.test(str)) return false;
    return true;
}

// 判断是否为 > 0 的正数
function is_positive_number(str) {
	var patn = /^\d+(\.\d+)?$/;
	if(!patn.test(str)) return false;
	return true;
}

// 判断是否为数字
function is_numeric(str) {
	var patn = /^[0-9-\/]+$/;
	if(!patn.test(str)) return false;
	return true;
}


//判断E-mail格式是否正确
function is_email(str) {
    var patn = /^[_a-zA-Z0-9\-]+(\.[_a-zA-Z0-9\-]*)*@[a-zA-Z0-9\-]+([\.][a-zA-Z0-9\-]+)+$/;
    if(!patn.test(str)) return false;
    return true;
}

// 判断是否2-5个字的中文
function is_chinese(str) {
    var patn = /^[\u4E00-\u9FA5]{1,3}$/;
    if(!patn.test(str)) return false;
    return true;
}

// 判断是否18未身份证
function is_idcard(str) {    
    var patn = /^[1-9]\d{5}[1-2]\d{3}((0[1-9])|(1[0-2]))((0[1-9])|([1-2][0-9])|(3[0-1]))\d{3}[\dxX]$/;
    if(!patn.test(str)) return false;
    return true;
}

// 判断是否为手机号码
function is_mobile(str) {
    var patn = /^(13|15|18)\d{9}$/;
    if(!patn.test(str)) return false;
    return true;
}

function is_zipcode(str)
{
    var patn = /^\d{6}$/;
    if(!patn.test(str)) return false;
    return true;
}

//取随机值
function getRandom() {
    return Math.floor(Math.random()*1000+1);
}

//字符串长度
function mb_strlen(str) {
    var len = 0;
    for(var i = 0; i < str.length; i++) {
        len += str.charCodeAt(i) < 0 || str.charCodeAt(i) > 255 ? (document.charset == 'utf-8' ? 3 : 2) : 1;
    }
    return len;
}

//增加行数(textarea)
function addrows(obj, num) {
    obj.rows += num;
}

//减少行数
function decrows(obj, num) {
    if (obj.rows>num) {
        obj.rows -= num;
    }
}

//checkbox反选
function checkbox_inverse(name, range_id) {
    if (typeof(name) == 'undefined') name = '';
    if (typeof(range_id) == 'undefined') range_id = '';
    if (range_id) 
        var check = document.getElementById(range_id).getElementsByTagName('input');
    else
        var check = document.getElementsByTagName('input');
    for (var i=0; i<check.length; i++) {
        if (name && check[i].name != name) continue;
        if (check[i].type == 'checkbox' && !check[i].disabled) {
            check[i].checked = !check[i].checked;
        }
    }
}

function allchecked(name, range_id, base_obj){
    if (typeof(name) == 'undefined') name = '';
    if (typeof(range_id) == 'undefined') range_id = '';
    if (range_id) 
        var check = document.getElementById(range_id).getElementsByTagName('input');
    else
        var check = document.getElementsByTagName('input');
    for (var i=0; i<check.length; i++) {
        if (name && check[i].name != name) continue;
        if (check[i].type == 'checkbox' && !check[i].disabled) {
            if (typeof(base_obj)!='undefined' && base_obj.type=='checkbox')
                check[i].checked = base_obj.checked;
            else
                check[i].checked = !check[i].checked;
        }
    }
}


function allcheckedid(name, range_id, base_obj){
    if (typeof(name) == 'undefined') name = '';
    if (typeof(range_id) == 'undefined') range_id = '';
    if (range_id) 
        var check = document.getElementById(range_id).getElementsByTagName('input');
    else
        var check = document.getElementsByTagName('input');
    for (var i=0; i<check.length; i++) {
        if (name && check[i].id != name) continue;
        if (check[i].type == 'checkbox' && !check[i].disabled) {
            if (typeof(base_obj)!='undefined' && base_obj.type=='checkbox')
                check[i].checked = base_obj.checked;
            else
                check[i].checked = !check[i].checked;
        }
    }
}



//name相同的checkbox反选
function checkbox_checked(name, range_id, obj) {
    if (typeof(range_id) == 'undefined' || ! range_id)
        var check = document.getElementsByTagName('input');
    else
        var check = document.getElementById(range_id).getElementsByTagName('input');
    for (var i=0; i<check.length; i++) {
        if (check[i].type == 'checkbox' && check[i].name == name && !check[i].disabled) {            
            if (typeof(obj)!='undefined' && obj.type=='checkbox')
                check[i].checked = obj.checked;
            else
                check[i].checked = !check[i].checked;
        }
    }
}

//检测checkbox是否有选中
function checkbox_check() {    
    if(typeof(arguments[0]) == 'string') {
        var checkname = arguments[0];
    } else {
        var checkname = null;
    }
    if(typeof(arguments[1]) == 'string') {
        var range_id = arguments[1];
    } else {
        var range_id = null;
    }
    if (range_id)
        var check = document.getElementById(range_id).getElementsByTagName('input');
    else
        var check = document.getElementsByTagName('input');

    var ischecked = false;
    for (var i=0; i<check.length; i++) {
        if (check[i].type == 'checkbox' && check[i].checked && !check[i].disabled) {
            ischecked = checkname == null || check[i].name == checkname;
            if (ischecked) break;
        }
    }
    if(typeof(arguments[2]) == 'undefined') {
        var notice = '请至少选择一项！';
    } else {
        var notice = arguments[2];
    }
    if (!ischecked && notice)
        alert(notice);
    return ischecked;
}

//检测radio是否有选择
function checkradio(obj) {
    if(obj) {
        var check=obj.getElementsByTagName('input');
    } else {
        var check=document.getElementsByTagName('input');
    }
    var ischecked = false;
    for (var i=0; i<check.length; i++) {
        if (check[i].type == 'radio' && check[i].checked) {
            ischecked=true;
        }
    }
    return ischecked;
}

//取单选框radio的值
function getRadio(from,name) {
    if(from) {
        var radios = from.getElementsByTagName('input');
    } else {
        var radios = document.getElementsByTagName('input');
    }
    if(!radios) return;
    var value='';
    for (var i=0; i<radios.length; i++) {
        if (radios[i].type == 'radio' && radios[i].name == name && radios[i].checked) {
            value=radios[i].value;
            break;
        }
    }
    return value;
}

//显示验证码
function show_seccode(url) {
    var sec = document.getElementById('seccode');
    if(!sec.style.display || sec.style.display == 'none') {
        sec.childNodes.length > 0 && sec.removeChild(sec.childNodes[0]);
        var img = document.createElement('img');
        img.src = url+'?x='+getRandom();//'seccode.php?x='+getRandom();
        img.width = 80;
        img.height = 25;
        img.style.cursor = 'pointer';
        img.title = '点击更新验证码';
        img.onclick = function() {
            this.src= url+'?x='+getRandom();
        };
        sec.appendChild(img);
        sec.style.display = 'block';
    }
}

function tot(mobnumber) {
    while(mobnumber.indexOf("０")!=-1){           
        mobnumber = mobnumber.replace("０","0");        
    }                       
    while(mobnumber.indexOf("１")!=-1){             
        mobnumber = mobnumber.replace("１","1");}       
    while(mobnumber.indexOf("２")!=-1){             
        mobnumber = mobnumber.replace("２","2");}       
    while(mobnumber.indexOf("３")!=-1){             
        mobnumber = mobnumber.replace("３","3");}       
    while(mobnumber.indexOf("４")!=-1){             
        mobnumber = mobnumber.replace("４","4");}       
    while(mobnumber.indexOf("５")!=-1){             
        mobnumber = mobnumber.replace("５","5");}       
    while(mobnumber.indexOf("６")!=-1){             
        mobnumber = mobnumber.replace("６","6");}       
    while(mobnumber.indexOf("７")!=-1){             
        mobnumber = mobnumber.replace("７","7");}       
    while(mobnumber.indexOf("８")!=-1){             
        mobnumber = mobnumber.replace("８","8");}       
    while(mobnumber.indexOf("９")!=-1){             
        mobnumber = mobnumber.replace("９","9");}       
    return mobnumber;       
}

function checkByteLength(str,minlen,maxlen) {
    if (str == null) return false;
    var l = str.length;
    var blen = 0;
    for(i=0; i<l; i++) {
        if ((str.charCodeAt(i) & 0xff00) != 0) {
            blen ++;
        }
        blen ++;
    }
    if (blen > maxlen || blen < minlen) {
        return false;
    }
    return true;
}

//var unlen = username.replace(/[^\x00-\xff]/g, "**").length;

function isEqual(objid1, objid2) {
    return objid1.value == objid1.value;
}

function tabSelect(showId,idpre) {
    for(i=1; i<=10; i++) {
        var tab = $("#"+idpre+i);
        if(!tab[0]) break;
        if (i==showId) { 
            $("#btn_"+idpre+i).attr("className","selected");
            $("#"+idpre+i).toggleClass("none");
            $("#"+idpre+i).css("display","");
         } else {
            $("#btn_"+idpre+i).attr("className","unselected");
            $("#"+idpre+i).addClass("none");
            $("#"+idpre+i).css("display","none");
        }
    }
}

//操作选择
function selectOperation(select) {
    var url = select.options[select.selectedIndex].value;
    if(url) {
        var cfm = select.options[select.selectedIndex].getAttribute("cfm");
        select.selectedIndex = 0;
        if(cfm && confirm(cfm) || !cfm) {
            window.location = url;
        }
    }
    select.selectedIndex = 0;
}

function set_cookie(name, value, expireDays) {
    name = cookiepre + name;
    var expires = new Date();
    if(!expireDays) expireDays = 1;
    expires.setTime(expires.getTime() + expireDays*24*3600*1000);
    var cookiestr = '';
    cookiestr = name + '=' + escape(value) + '; path=' + cookiepath;
    if(cookiedomain != '') {
        cookiestr += '; domain=' + cookiedomain;
    }
    cookiestr += '; expires=' + expires.toGMTString();
    document.cookie = cookiestr;
}

function get_cookieval(start) {
    var end = document.cookie.indexOf(";", start);
    if(end == -1) {
        end = document.cookie.length;
    }
    return unescape(document.cookie.substring(start, end));
}

function get_cookie(name) {
    name = cookiepre + name;
    var arg = name + "=";
    var alen = arg.length;
    var clen = document.cookie.length;
    var i = 0;
    while(i < clen) {
        var j = i + alen;
        if(document.cookie.substring(i, j) == arg) return get_cookieval(j);
        i = document.cookie.indexOf(" ", i) + 1;
        if(i == 0) break;
    }
    return null;
}

function del_cookie(name) {
    var expires = new Date();
    expires.setTime (expires.getTime() - 1);
    var cval = get_cookie(name);
    name = cookiepre + name;
    document.cookie = name+"=" + cval + "; expires=" + expires.toGMTString();
}


//**********************jquery 方法公用 --qfb 2013-11-14**********************************//
$(document).ready(function () {
  //表单验证(自定义错误)
  var show_error = function (obj) {
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
  };

  window.show_error = show_error;

  var ajax_loader = function () {
	var $window = $(window);
	$('a.ajax_loader').unbind('click').bind('click', function () {
		var $thiz = $(this),
		ajax_url = $thiz.attr('ajax'),
		title = $thiz.attr('name');
			
		new $.Zebra_Dialog('', {
							    source: {
							    	'iframe': {
										'src':  ajax_url,
										'height': $window.height()-100
						    		}
				    			},
							    'type'      :  '',
							    'buttons'   :  false,
							    'width'     : $window.width() - 10,
							    'title'     : title
		});
	});
  }; 
 
  ajax_loader();

  window.ajax_loader = ajax_loader;
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


$('document').ready(function () {
	
	/**
	 * 数据表格选中，单元格变色
	 */
	$('input[type=checkbox]', 'table.maintable').click(function () {
		var $thiz = $(this);
		if ($thiz.attr('name') == 'ids[]' || $thiz.attr('name') == 'id[]') {
			if ($thiz.is(':checked')) {
				$thiz.parent().parent().find('td').addClass('checked');
			} else {
				$thiz.parent().parent().find('td').removeClass('checked');
			}
		}
	});
});