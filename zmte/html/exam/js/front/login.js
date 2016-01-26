/*
*
* */
(function($) {
    var defaultS = {
        'passname':{
            "backright":"&radic;",
            "backerror":"&Chi;"
        },
        'password':{
            "backright":"&radic;",
            "backerror":"&Chi;"
        },
        'aInput': '.cls_login_input'
    };
    $.logincheck = function (obj,config){
        var self=this;
        self.box = $(obj);
        self.config = $.extend(defaultS, config);
        self.logincheck._init(self.box,self.config);
    };
    $.extend($.logincheck, {
        _init :function(box,config){
            var self=this;
            self._event(box,config);
            self._submit(box,config);
//            self._all(box,config);
        },
        _event:function(box,config){
            var self = this;
            $(config['input'],box).bind('focus blur', function (e) {
                var target = e.target;
                var targetVal = $(target).val();

                if(e.type == "focus"){
                   $(target).css("background-color","#FFFFCC");
                   targetVal == "" && $(target).next().html($(target).attr("tip")).addClass('cls_tips');
               }else{
                   $(target).css("background-color","#FFF");
                   if(targetVal == ""){
                       $(target).next().html("");
                   }else{
                       $(target).next().html("");
                    self._regExp(target,targetVal,config);
                   }
               }
                
            });
        },
        _ajax:function(box,config){
                 $.ajax({
                    url : $(box).attr("ajaxurl"),
                    dataType : 'json',
                    type : 'post',
                    data : {
                        exam_ticket : $(config['input'],box)[0],
                        password : $(config['input'],box)[1]
                    },
                    timeout : 5000,
                    error: function (a, b, c) {
                      alert(a + b + c);
                     },
                    success : function (response) {
                        var code = response.code,
                            msg	= response.msg;

                        if (code < 0) {
//                            alert(msg);
                            $txt_exam_ticket.focus();
                        } else {
//                            alert("信息正确");
//                            window.location.reload();
                        }
                    }
                });
            },
        _regExp:function(target,targetVal,config){
            var self = this;
            var reginfor;
            var oinput = $(target).attr("pass");
            //准考证号
            targetVal.indexOf("@") ==-1 ? reginfor = $(target).attr("reg2"):reginfor = $(target).attr("reg");
            if((new RegExp(reginfor)).test(targetVal)){
                $(target).next().addClass('cls_login_right').removeClass('cls_login_error').removeClass('cls_tips');
            }
            else if(!(new RegExp(reginfor)).test(targetVal)){
                $(target).next().html(config[oinput]['backerror']);
                $(target).next().addClass('cls_login_error').removeClass('cls_tips');
            }
        },
        _submit:function(box,config){
            var self = this;
//            self._ajax(box,config) && self._all(box,config) && $(box).submit();
        },
        _all:function(box,config){
             var inputLength = $(config['aInput'],box).length;
             var i;
             $(config['input'],box).each(function(item){
                item.hasClass("cls_login_right");
                i=i+1;
            });
//             i=inputLength && return true;

         }


    });
})(jQuery);