// @charset "utf-8";
/***********************************************

* 返回顶部组件 version 1.0
* @author: 阙发镔<quefabin@hexin.com> 
* @create: 2013-04-19
* @dependence: jquery > 1.4.1
* @demo:
*	  html 部分:
		</body>
			<script type="text/javascript" src="jquery.min.js"></script>
			<script type="text/javascript" src="scroll_to_top.js"></script>
			<noscript><a href="javascript:void(0);">返回顶部</a></noscript>
		</html>
      
	  js 部分:
		  $(document).ready(function (){
			  var setting = {};
			  scrollToTop.init(setting);
		  }
*
***********************************************/
var scrollToTop = {

	//配置
    setting: {
				startline:100, //开始出现返回顶部按钮位置
				scrollto: 0, //返回顶部的距离位置
				scrollduration:1000, //返回顶部的速度
				fadeduration:[500, 100],//返回顶部按钮显示隐藏的淡入淡出速度
				controlattrs: {offsetx:40, offsety:40},//返回顶部按钮出现相对屏幕定位的位置
				controlHTML: null, //返回顶部按钮html填充，该属性优先于 iconNumber
				anchorkeyword: '#top', //需要自行绑定的返回顶部的锚点
				title:'返回顶部',//title提示
				iconNumber:'1',//图片序号
				scrollBox:null,//对应的容器
				appendTo:null,//需要填充的容器
				sourceUrl:config_urls.s_source_url+'/images/jquery/plugin/scroll_to_top',//图标资源地址
				onerrorImg:config_urls.s_source_url+'/images/jquery/plugin/scroll_to_top' + '/arrow1.png' //图片找不到时，替换图标
			}
    /**
	 * 深拷贝对象
	 */
	,_deepCopy: function(p, c) {
		var c = c || {},
			parentThiz = this;
		for (var i in p) {
			if (typeof p[i] === 'object') {
				c[i] = (p[i].constructor === Array) ? [] : {};
				parentThiz._deepCopy(p[i], c[i]);
			} else {
				c[i] = p[i];
			}
		}
		return c;
	}
	
	,state: {isvisible:false, shouldvisible:false}
	
    ,scrollup:function(){

		//if control is positioned using JavaScript
        if (!this.cssfixedsupport) {
            this.$control.css({opacity:0}); //hide control immediately after clicking it
		}
		
        var dest = isNaN(this.setting.scrollto) ? this.setting.scrollto : parseInt(this.setting.scrollto);
        if (typeof dest == "string" && jQuery('#'+dest).length == 1) {//check element set by string exists
            dest = jQuery('#'+dest).offset().top;
        } else {
            dest = 0;
		}

        this.$body.animate({scrollTop : dest}, this.setting.scrollduration);

    }

    ,keepfixed:function(){
		var _setting = this.setting;
		var _scrollBox = _setting.scrollBox;
		var $window = $(_scrollBox).length ? $(_scrollBox) : $(window);
		var $body = $(_scrollBox).length ? $(_scrollBox) : $('body');
		
		$body = $body.get(0);
		var _clientHeight = $body.clientHeight,
			_clientWidth = $body.clientWidth;
		
		var $control = this.$control;
        var controlx = $window.scrollLeft() + $window.width() - $control.width() - _setting.controlattrs.offsetx;
        var controly = $window.scrollTop() + $window.height() - $control.height() - _setting.controlattrs.offsety;
        if (controly > _clientHeight || controlx > _clientWidth) {
        	return false;
        }
		
        this.$control.css({left:controlx + 'px', top:controly + 'px'});
    }

    ,togglecontrol:function(){
		var _setting = this.setting;
		var _scrollBox = _setting.scrollBox;
		var $scrObj = $(_scrollBox).length ? $(_scrollBox) : $(window);
        var scrolltop = $scrObj.scrollTop();
		
        if (!this.cssfixedsupport) {
			this.keepfixed();
		}

        this.state.shouldvisible = (scrolltop>=this.setting.startline) ? true : false
		if (this.state.shouldvisible && !this.state.isvisible){
			this.$control.stop().animate({opacity:1, 'right':_setting.controlattrs.offsetx + 10}, this.setting.fadeduration[0]);
            this.state.isvisible = true;
        } else if (this.state.shouldvisible == false && this.state.isvisible){
            this.$control.stop().animate({opacity:0, 'right':_setting.controlattrs.offsetx - 10}, this.setting.fadeduration[1]);
            this.state.isvisible = false;
        }
    }
   
    ,init:function(options){
		//配置参数赋值
		var _setting = this.setting;
		this.setting = this._deepCopy(options || {}, _setting);
		
        jQuery(document).ready(function($){
			if ($('#topcontrol').length) {
				return;
			}
            var mainobj=scrollToTop,
				iebrws=document.all,
				setting = mainobj.setting;
					
			var controlHTML = setting.controlHTML,
				_html = controlHTML;
				
			if (controlHTML === null) {
				var _onerrorImg = setting.onerrorImg;
				_html = '<img src="' + setting.sourceUrl + '/arrow' + setting.iconNumber + '.png"';
				_html += ' onerror="this.src=\'' + _onerrorImg + '\'"/>';
			}
			
            mainobj.cssfixedsupport = !iebrws || iebrws && document.compatMode=="CSS1Compat" && window.XMLHttpRequest; //not IE or IE7+ browsers in standards mode
			
			var scrollBox = setting.scrollBox,
				$scrollBox = $(scrollBox);
				scrollBoxLength = $scrollBox.length,
				$appendTo = null;
				
			if (scrollBoxLength) {
				mainobj.$body = $scrollBox;
				var $appendTo = $(mainobj.setting.appendTo) || $scrollBox.parent();
				if ($appendTo.length) {
					$appendTo.css({'position':'relative'});
				}
			} else {
				mainobj.$body = $appendTo = (window.opera)? (document.compatMode=="CSS1Compat"? $('html') : $('body')) : $('html,body');
			}
			
            mainobj.$control = $('<div id="topcontrol">'+_html+'</div>')
								 .css({
										position:(mainobj.cssfixedsupport ? (!scrollBoxLength ? 'fixed' : 'absolute') : 'absolute'), bottom:(setting.controlattrs.offsety), 
										right:(setting.controlattrs.offsetx), 
										opacity:0, 
										cursor:'pointer', 
										'z-index':'9999'
									})
								.attr({title:setting.title})
								.click(function(){mainobj.scrollup(); return false;})
								.appendTo($appendTo);
				
			//loose check for IE6 and below, plus whether control contains any text
            if (document.all && !window.XMLHttpRequest && mainobj.$control.text() != '') {
				//IE6- seems to require an explicit width on a DIV containing text
                mainobj.$control.css({width:mainobj.$control.width()}); 
			}
			
            mainobj.togglecontrol();

			$('a[href="' + setting.anchorkeyword +'"]').unbind('click').bind('click', function(){
                mainobj.scrollup();
                return false;
            })
			
			var $window = scrollBoxLength ? $(scrollBox) : $(window);
            $window.bind('scroll resize', function(e){
				mainobj.togglecontrol();
            });
        });
    }
}
