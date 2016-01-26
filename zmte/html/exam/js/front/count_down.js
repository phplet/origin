/*
* 机考倒计时
* */
(function($) {
    var defaultS = {
	        'startT' : 'November 26,2013 09:48:00',//正常格式都可以兼容
	        'endT':'2013-12-06 16:55:00',
	        'startText':'距离考试开始时间还有',
	        'endText':'距离考试结束时间还有',
	        'sText':'考试开始！',
	        'eText':'考试结束！',
	        'currentTime':'2013-12-06 16:55:00',
	        'startCallback':function(){},
	        'endCallback':function(){},
	        'tipMin' : 10, //剩余分钟小于该值时标红提示
            'exam_place_info_url' : "http://"+ location.host + "/exam/test/exam_place_info",
            'exam_palce_request_time' : 10, //单位：分钟
        };
    $.examTime = function (obj,config){
        var self=this;
        self.box = $(obj);
        self.config = $.extend(defaultS, config);
        self.examTime._init(self.box,self.config);
    };
    $.extend($.examTime, {
        _init :function(box,config){
            var self=this;
            self._startTime(box,config);

        },
        _hm_to_date:function(msd, tipMin){
            var getTime;
            var seconds = msd/1000;
            var minutes = Math.floor(seconds/60);
            var hours = Math.floor(minutes/60);
            var CDay = Math.floor(hours/24) ;
            var CHour= hours % 24;
            var CMinute= minutes % 60;
            var CSecond= Math.floor(seconds%60);//"%"是取余运算，可以理解为60进
            
            var _CMinute = CMinute;
            var _CSecond = CSecond;
            if (CDay <= 0 && CHour <= 0 && CMinute <= tipMin) {
            	var _CMinute = '<font color="red">' + CMinute + '</font>';
            	var _CSecond = '<font color="red">' + CSecond + '</font>';
        	}
            if( CDay > 0){ getTime = CDay +" 天 " + CHour +" 小时 "+  _CMinute +" 分钟 " + _CSecond + " 秒"}
            if( CDay <=0 && CHour > 0){ getTime = CHour +" 小时 "+  _CMinute +" 分钟 " + _CSecond + " 秒"}
            if(CDay <=0 && CHour <= 0 && CMinute>0){ getTime =   _CMinute +" 分钟 " + _CSecond + " 秒"}
            if(CDay <=0 && CHour <= 0 && CMinute<=0 && CSecond>0 ){ getTime =  _CSecond + " 秒"}
            if(CDay <=0 && CHour <= 0 && CMinute<=0 && CSecond<=0 ){getTime =  ""}
            return  getTime;
        },
        
        //清空相关计时器
        _clearInterval: function () {
        	var self = this;
        	if (self.startSetInter) {
        		clearInterval(self.startSetInter);
        	}
        },
        
         //业务处理，判断考试阶段，给出不同反馈
        _startTime : function(box, config){
            var self=this;
            var startT = parseInt(new Date(config['startT'].replace(/-/g,"/")).getTime());
            var startText = config['startText'];
            var stratCallback = config['startCallback'];
          //  var myDate = Date.parse(new Date());
            
            var myDate =  parseInt(new Date(config['currentTime'].replace(/-/g,"/")).getTime());
            var currentDate = setInterval(function(){myDate= myDate + 1000; return myDate },1000);
            
            var endT =  parseInt(new Date(config['endT'].replace(/-/g,"/")).getTime());
           
            
            var endText = config['endText'];
            var endCallback = config['endCallback'];
            var sText = config['sText'];
            var eText = config['eText'];
            var tipMin = config['tipMin'];
            var exam_place_info_url = config['exam_place_info_url'];
            var exam_palce_request_time = config['exam_palce_request_time'] * 60 * 1000;
            var off = true; //剩余10分钟请求开关
                
           var _judge = function (starttime,endtime,text,btext,callback){
        	
                if(endtime > myDate){
                	
                	self._clearInterval();
                	self.startSetInter = setInterval( function(){
                        var msd = endtime - myDate;

                        
                        //msd = msd - 1000;
                      
                        // 剩余十分钟,从新请求时间并重置时间
                        if ((msd == exam_palce_request_time)&& off) {
                    
                            off = false;
                            $.post(exam_place_info_url, function(data){
                            	
                                var data = $.parseJSON(data);
                         
                                var end_time = parseInt(new Date(data.end_time.replace(/-/g,"/")).getTime());
                                
                                if (data && (endtime != end_time)) {
                                    // 重置计时器
                                    off = true;
                                    _judge(starttime,end_time,text,btext,callback);
                                };
                            })
                        };
                        if(msd > 1000) {
                            $(box).html(text + "<em class='cls_form_time_now'>" + self._hm_to_date(msd, tipMin) + "</em>");
                        } else {
                            self._clearInterval();
                            $(box).html(btext);
                            callback();
                        }
                    },1000);
                } else {
                   self._clearInterval();
                   $(box).html(btext);
                   callback();
                }
            };
            
        	if(startT > myDate){    
        		//考试未开始
        		_judge(myDate,startT,startText,sText,stratCallback);
        	} else if(startT <= myDate && endT >= myDate){    
        		//考试开始到结束
        		_judge(startT,endT,endText,eText,endCallback);
        	} else{
        		//已结束
        		_judge(startT,endT,endText,eText,endCallback);
        	}
        }
    });
})(jQuery);





