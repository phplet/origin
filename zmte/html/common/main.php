<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
        ul
        {
             list-style-type:none;
             padding:0px;
             margin:0px;
        }
        .cls_bg
        {
            background:url("<?=$http_url?>/images/zeming/2.png") no-repeat fixed center 0;
            height:100%;
            left:0;
            position:fixed;
            top:0;
            width:100%;
            z-index:-1;
            min-height:650px;    
        }
        .cls_box
        {
            min-height: 100px;
            height: 200px;
            width: 100%;
        }
        .cls_info
        {
            font-family:"微软雅黑";
            color:white;
            margin-left: 120px;
            font-size: 35px;
            color:#FCDA9F;
        }
        .cls_info li
        {
            margin-top:10px;
        }
        </style>
    </head>

    <body onload="timer()">
        <div class="cls_bg">
            <div class="cls_box">
            </div>
            <ul class="cls_info">
                <li>预计<?php echo $date_str;?>开启服务</li>
                <li>倒计时进行中：<div id="timer"></div></li>
            </ul>
        </div>
    </body>

    <script type="text/javascript">
            function timer()
            {
                //计算剩余的毫秒数
                var ts = <?php echo $system_date;?> - (new Date());
                //计算剩余的天数
                var dd = parseInt(ts / 1000 / 60 / 60 / 24,10);
                //计算剩余小时数
                var hh = parseInt(ts / 1000 / 60 / 60 % 24,10);
                //计算剩余的分钟数
                var mm = parseInt(ts / 1000 / 60 % 60, 10);
                //计算剩余的秒数
                var ss = parseInt(ts / 1000 % 60, 10);
                dd = checkTime(dd);
                hh = checkTime(hh);
                mm = checkTime(mm);
                ss = checkTime(ss);
                var dateStr = dd + "天" + hh + "小时" + mm + "分" + ss + "秒";
                document.getElementById("timer").innerHTML = dateStr;

                //invoke("timer()",0,1000);
                //setInterval("timer()",1000);
                setTimeout("timer()",1000);
            }

            function checkTime(i)
            {
                if(i < 10)
                {
                    i = "0" + i;
                }
                return i;
            }

            function invoke(f, start, interval, end)
            {
                if(!start)
                { 
                    start = 0;
                }
                if(arguments.length <=2)
                {
                    setTimeout(f,start);
                }
                else
                {
                    setTimeout(repeat, start);
                    function repeat()
                    {
                        var h = setInterval(f, interval);
                        if (end) 
                        {
                            setTimeout(function(){clearIntarval(h);},end);
                        }
                    }
                }
            }
        </script>
</html>
