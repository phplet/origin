(function($) {

    var timers = {};
    function leftTop(obj){
        var self = this;
        self.obj =  $(obj);

      
        var _pos=function(){
            var divHeight = parseInt(self.obj[0].offsetTop);
        
            var divHeight1 = $(".cls_footer_bg").outerHeight(true)-50;
            
            var p = parseInt($(window).scrollTop());
        
            	
            
            
                self.obj.css('position',(p > 390) ? 'fixed' : 'static');

                if(self.obj.css('position')=='fixed'){
   
                 var divHeight2 =  $(".cls_footer_bg").outerHeight(true)-50;
         
                    self.obj.css('bottom',divHeight2+'px');
    
                } else {
                    self.obj.css('top', '');
                   
                }

           /* if (tagDiv.offsetTop > window.scrollY) {
                var balloonBottom = balloonDiv.offsetTop + balloonDiv.offsetHeight;
                if (balloonBottom > window.scrollY + window.innerHeight) {
                    balloonDiv.style.top = tagDiv.offsetTop - balloonDiv.offsetHeight + "px";
                }
            }*/

        };
        _pos();
        $.event.add(window, "scroll", function() {
            _pos();
        });



        return self;
    }


    window.leftTop = leftTop;

})(jQuery);
