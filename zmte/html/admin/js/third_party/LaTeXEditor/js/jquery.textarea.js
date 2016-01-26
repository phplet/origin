/*
 *
 * 调用方法：
 * $(文本域选择器).insertContent("插入的内容"); 
 * $(文本域选择器).insertContent("插入的内容"，数值); 
 * 根据数值选中插入文本内容两边的边界, 数值: 0是表示插入文字全部选择，-1表示插入文字两边各少选中一个字符。
 */
$(function() {  
    /*  在textarea处插入文本--Start */  
    (function($) {  
        $.fn.extend({  
            insertContent : function(myValue, pos, t) {  
                var $t = $(this)[0];  
                if (document.selection) { // ie   
                    this.focus();  
                    var sel = document.selection.createRange();  
                    sel.text = myValue;
                    this.focus();
                    var wee = sel.text.length;
                    if (arguments.length >= 2 && pos > 0) {
                        sel.moveEnd('character', wee + pos - myValue.length);
                        sel.select();
                    } else {
                        sel.moveStart('character', -l); 
                    }
                    if (arguments.length == 3) {  
                        var l = $t.value.length;  
                        sel.moveEnd("character", wee + t);  
                        t <= 0 ? sel.moveStart("character", wee - 2 * t  
                                - myValue.length) : sel.moveStart(  
                                "character", wee - t - myValue.length);  
                        sel.select();  
                    }
                } else if ($t.selectionStart  
                        || $t.selectionStart == '0') {  
                    var startPos = $t.selectionStart;  
                    var endPos = $t.selectionEnd;  
                    var scrollTop = $t.scrollTop;  
                    $t.value = $t.value.substring(0, startPos)  
                            + myValue  
                            + $t.value.substring(endPos,  
                                    $t.value.length);  
                    this.focus();
                    
                    if (arguments.length >= 2 && pos > 0) {
                       $t.selectionStart = startPos + pos;
                       $t.selectionEnd = startPos + pos;
                    } else {
                        $t.selectionStart = startPos + myValue.length;  
                        $t.selectionEnd = startPos + myValue.length;
                    }
                    $t.scrollTop = scrollTop;
                    if (arguments.length == 3) {  
                        $t.setSelectionRange(startPos - t,  
                                $t.selectionEnd + t);  
                        this.focus();  
                    }  
                } else {  
                    this.value += myValue;  
                    this.focus();  
                }  
            }  
        })  
    })(jQuery);  
    /* 在textarea处插入文本--Ending */  
});  