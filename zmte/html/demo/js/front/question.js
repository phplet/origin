/*
*
* */
(function($) {
    var defaultS = {
        'prev' : '#id_btn_prev',
         'next':'#id_btn_next',
        'listCls':'.cls_space2' ,
        'currentCls':'cls_current'
        };
    $.currentCss = function (config){
        var self=this;
        self.config = $.extend(defaultS, config);
        self.currentCss._init(self.config);
    };
    $.extend($.currentCss, {
        _init :function(config){
            var self=this;
//            self._randomcurrent(config);
            self._current(config)
        },
        _current:function (config){
            var self = this;
            $(config['listCls']).each( function(i,item) {
                if($(item).hasClass(config['currentCls'])){
                    self._ordercurrent(config,i);
                }
            });
        },
        _ordercurrent:function(config,i){
            $(config['prev']).click(function(){
                $(config['listCls']).eq(i).removeClass(config['currentCls']);
                $(config['listCls']).eq(i-1).addClass(config['currentCls']);
            });
            $(config['next']).click(function(){
                $(config['listCls']).eq(i).removeClass(config['currentCls']);
                $(config['listCls']).eq(i+1).addClass(config['currentCls']);
            })
        },
        _randomcurrent:function(config){
            $(config['listCls']).click(function(e){
                $(config['listCls']).removeClass(config['currentCls']);
                $(e.target).addClass(config['currentCls'])
            })
        }
    });

})(jQuery);