(function($) {
    var defaultS = {
        'topobj' :  ""
    };
    var timers = {};
    function headerTop(obj, config){
        var self = this;
        self.obj =  $(obj);
        self.config = $.extend(defaultS, config);

        var _init = function(){
            _pos();
           self.resize = _pos;
        };
         var _pos=function(){
             var divHeight = parseInt(self.obj[0].offsetTop);
             var p = parseInt($(window).scrollTop());
        	 self.obj.css('position',(p > divHeight) ? 'fixed' : 'static');
        	 if(self.obj.css('position')=='fixed'){
        		 self.obj.css('top', 0);
        	 } else {
        		 self.obj.css('top','');
        	 }
         };
         $.event.add(window, "scroll", function() {
//         	clearTimeout(timers.scroll);
//         	timers.scroll = setTimeout(function () {_pos();}, 600);
//         	return false;
         });

        _init();

        return self;
    }

    window.headerTop = headerTop;

})(jQuery);