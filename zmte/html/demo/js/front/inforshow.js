(function($) {
    var defaultS = {
        'showTime' : '5',//展开信息显示时间(秒)
        'btnname':'.cls_infor_btn',
        'auto_hide_count_down' : '.auto_hide_count_down',
        'auto_hide_count_down_text' : '秒之后将自动收起'
//        'callback' : '变化位置联动方法'
    };
    topShow = function (obj, config){
        var self = this,
            _data = self.__data || {};
            _data[obj] = {};
            _data[obj]['target'] = $(obj);
            _data[obj]['config'] = $.extend(defaultS, config);
        self.__data = _data;
        self._init(obj);
    };

    topShow.prototype ={
        _init :function(obj){
            var self = this;

            self._showTime(obj);
            self._bind(obj);
        },

        _showTime: function(obj){

            var self = this;
            var _data = self.__data[obj];

            if (_data.timer_auto_hide) {
                return false;
            }
            _data.count_down_auto_hide = parseInt(_data.config['showTime']);
            var timer_auto_hide_count_down = function () {
                var $_auto_hide_count_down = $(_data.config['auto_hide_count_down']);
                if (_data.count_down_auto_hide <= 0 || !$_auto_hide_count_down.length) {
                    clearInterval(_data.timer_auto_hide_count_down);

                    return false;
                }

                if ($_auto_hide_count_down.length) {
                    $_auto_hide_count_down.html('(' + _data.count_down_auto_hide + ' ' + _data.config.auto_hide_count_down_text + ')');
                }
                _data.count_down_auto_hide--;
            };
            timer_auto_hide_count_down();
            _data.timer_auto_hide_count_down = setInterval(function(){timer_auto_hide_count_down()}, 1000);

            _data.timer_auto_hide = setTimeout(function(){
               var $_box_target = $(_data.target);
                $_box_target.show() && $_box_target.hide().next().show();
                self._clearTimer(obj);
               /* var _callback = _data.config["callback"] || function () {};
                _callback();*/

                self.__data[obj] = _data;

            }, _data.config['showTime']*1000);

        },

        _bind:function(obj){
            var self = this;
            var _data = self.__data[obj];
            $(_data['config']['btnname'], _data.target).click(function(e) {
                if (_data.timer_auto_hide) {
                    self._clearTimer(obj);
                }
                _data.target.stop(true, true).show();
                $(this).parent().stop(true, true).hide();
             /*   var _callback = _data.config["callback"] || function () {};
                _callback();*/

                self.__data[obj] = _data;

            })
        },
        _clearTimer: function (obj) {
            var self = this,
                _data = self.__data[obj];

            clearTimeout(_data.timer_auto_hide);
            clearInterval(_data.timer_auto_hide_count_down);

            var $_auto_hide_count_down = $(_data.config['auto_hide_count_down']);
            if ($_auto_hide_count_down.length) {
                $_auto_hide_count_down.html('');
            }
        }
    };
    $.topShow = topShow;
})(jQuery);