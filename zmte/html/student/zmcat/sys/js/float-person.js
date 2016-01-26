/**
 * @copyright www.zmcat.net
 * @VERSION 1.1
 * @author liugang
 * @DATE 2015-05-12 
 * 小人物
 *
 */
var FloatPerson = function(){
    //显示人物对话框
    function _show(info, class_name) {
        var n = function(m, n){
            return Math.floor(Math.random() * (n - m) + m);
        }(0, info.length);

        var class_name = class_name || "cls_float_word";
        var html = info[n];

        $("." + class_name).html(html);
    }

    //菜单栏以外的信息提示
    this.common = function(){
        var _other = [
            "好好学习，天天向上~~",
            "勤奋才是成功的不二法门~",
            "学海无涯，我的小舟正在飘飘荡荡~~",
            "积跬步，以至千里！"
        ];

        _show(_other);
    };

    //首页
    this.index = function(param){
        var name = param['name'];
        var has_course = param['has_course'];
        var has_testwork = param['has_testwork'];
        var _index = [
            name + "同学，欢迎来到学习网~",
            "要去错题集看看么？"
            ];

        if (has_course) {
            _index.push(name + "同学,今天有在线课程，记得准时参加哟");
        }

        if (has_testwork) {
            _index.push(name + "同学，你今天还有作业要完成哦！");
        }

        _show(_index);
    }

    //个人中心
    this.userinfo = function(){
        var _userinfo = [
            "完善个人信息，可以更好的让小伙伴找到你哦~"
            ];	
        _show(_userinfo);
    }

    //开始学习
    /*
       this.selcourseknowledge = function() {
       var _selcourseknowledge = [
       "好好学习，天天向上~~",
       "勤奋才是成功的不二法门~",
       "学海无涯，我的小舟正在飘飘荡荡~~",
       "积跬步，以至千里！"
       ];

       _show(_selcourseknowledge);
       };
       */

    //在线课堂
    this.onlineclasslist = function(has_course) {
        var _onlineclasslist = [];

        var font_class = font_class || "cls_float_word";
        if (has_course) {
            _onlineclasslist.push("今天有课，要提前预习哟。");
        }
        else {
            _onlineclasslist.push("咦，今天有课么？");
        }

        _show(_onlineclasslist);
    };

    //错题集
    this.myerrorstatlist = function(){
        var _myerrorstatlist = [
            "决不在一个坑里跌倒N次！",
            "腾空这里！"
                ];

        _show(_myerrorstatlist);
    };

    //我的钱包
    this.mywallet = function(){
        var _mywallet = [
            "别看我现在是小树苗，好好灌溉会变成参天大树哦~",
            "一分耕耘一分收获，一分投资更多收获哟~",
            "金钱改变不了命运，知识可以！"
                ];

        _show(_mywallet);
    };

    //我的作业
    this.mytestworklist = function(hastestwork) {
        var _mytestworklist = [];
        var font_class = font_class || "cls_float_word";
        if (hastestwork){
            _mytestworklist.push("咦，竟然还有作业没完成，快快消灭它~");
        }
        else {
            _mytestworklist.push("nice，作业都完成了，要不要再去练习下呢？~");
        }

        _show(_mytestworklist);
    };
};

FloatPerson.prototype = {
    move_left: function(font_class,time){
        var x = this.rand_num(30,150);
        var y = Math.sqrt(12500 - (x - 30) * (x - 30))  + 40;
        $("." + font_class).animate({"right":x + "px","bottom":y+"px"},time);
    },
    move_right: function(font_class,time){
        $("." + font_class).animate({"right":"30px","bottom":"40px"},time);
    },
    move_top: function(font_class,time){
        $("." + font_class).animate({"bottom":"100px"},time);
    },
          //返回 m-n间的正整数(没有n)
    rand_num: function(m, n){
        return Math.floor(Math.random() * (n - m) + m);
    }
};

