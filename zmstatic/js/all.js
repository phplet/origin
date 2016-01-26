/**
 * PRIVATE
 * 该全局变量用于记录层对话框的最大z-index属性值,每次有层对话框弹出时
 * 该值都会依次加2(背景mask在当前最大值上加1,对话框本身在当前最大值上加1
 */
var g_zIndexMax = 0;

/**
 * PRIVATE
 * AJAX调用方法,该函数一般不直接调用,而是通过ajax_xxxx函数间接调用
 * @param   string  url         要请求的网址
 * @param   string  func        要请求执行的函数名
 * @param   Array   post_param  参数表数组(每一个元素是一个参数)
 * @return  void
 */
function fnAjaxCall(url, func, post_param)//{{{
{
    var i = 0;
    var param = [];
    for(;i < post_param.length; i++) {
        param.push(post_param[i]);
    }
    $.ajax({
        type: 'POST',
        url: url,
        async: false,
        cache: false,
        data: {'ajax_call': true, 'function': func, 'arguments': param},
        dataType: "json",
        success: function(data, status) {
            for (var i = 0; i < data.length; i++)
            {
                var key = data[i][0];
                if (key == 'alert')
                {
                    alert(data[i][1]);
                }
                else if (key == 'redirect')
                {
                    location.href = data[i][1];
                }
                else if (key == 'refresh')
                {
                    location.reload();
                }
                else if (key == 'call')
                {
                    var thisobj = 'window';
                    var lastIndexOf = data[i][1].lastIndexOf('.');
                    if (lastIndexOf > -1)
                    {
                        thisobj = data[i][1].substr(0, lastIndexOf);
                    }
                    eval(data[i][1] + '.apply(' + thisobj + ', data[i][2]);');
                }
                else if (key == 'script')
                {
                    eval(data[i][1]);
                }
                else if (key == 'attr')
                {
                    $(data[i][1]).attr(data[i][2], data[i][3]);
                }
            }
        }
    });
}//}}}

/**
 * PUBLIC
 * 关闭一个层对话框.如果该层对话框的内置的,则仅是隐藏;如果该层对话框是AJAX的,
 * 则销毁该层对话框(去除所有相关HTML代码)
 * @param   string  id  要关闭的层对话框的id属性
 * @return  void
 */
function fnCloseDialog(id)//{{{
{
    if (id.charAt(0) != '#')
    {
        id = '#' + id;
    }

    var dlg = $(id);
    if (dlg.size() > 0)
    {
        if (dlg.hasClass('cls_dialog'))
        {
            if (dlg.hasClass('cls_dialog_init'))
            {
                var mask = $(id + '_mask');
                dlg.hide();
                mask.hide();

                dlg.replaceWith('');
                mask.replaceWith('');
            }
            else
            {
                dlg.hide();
                $(id + '_mask').hide();
            }
        }
    }
}//}}}

/**
 * PUBLIC
 * 显示一个层对话框.如果参数是一个网址(以"/"或"http://"或"https://"开头),则
 * 通过$.ajax()获取该网址内容并将其作为层对话框显示出来;如果参数是一个id属性
 * 值,则显示该层对话框
 * 层对话框机制:
 *      1. 内嵌层对话框:直接将代表层对话框的div写在某网页内部,例如查询层对话框
 *      2. AJAX层对话框:在某个xxxxinit.phtml模板中写层对话框div,例如编辑层对话框
 *      3. 无论是内嵌层对话框还是AJAX层对话框,都必须设置id属性值.如果是AJAX
 *          层对话框,则会给该div加个cls_dialog_init样式(以标识其为AJAX层对话框)
 *      4. 通过fnShowDialog()显示某个层对话框时,如果代表层对话框的div没有
 *          cls_dialog的样式,则要先给其加cls_dialog样式,并且新增一个其对应的
 *          背景mask层div,其id属性值为该层对话框div id属性值加"_mask"后缀.以
 *          使该层对话框显示时所有背景内容都显示为半透明效果并不可点击.
 *          显示层对话框时,要将其mask层div和自身都显示出来.
 *      5. 通过fnCloseDialog()关闭某个层对话框时,如果是内置层对话框,则只是隐藏
 *          其mask div和自身.如果是AJAX层对话框,则会将mask div和自身div都销毁掉
 * @param   string  id_or_url   要显示的层对话框的id属性或网址
 * @return  void
 */
function fnShowDialog(id_or_url)//{{{
{
    if (id_or_url.charAt(0) == '/' 
            || id_or_url.substr(0, 7).toLowerCase() == 'http://' 
            || id_or_url.substr(0, 8).toLowerCase() == 'https://')
    {
        $.ajax({
            type: 'GET',
            url: id_or_url,
            async: false,
            cache: false,
            dataType: "html",
            success: function(data, status) {
                var d1 = $(data);
                for (var i = d1.size() - 1; i > -1; i--)
                {
                    var obj = d1.get(i);
                    if (obj.tagName.toLowerCase() == 'div')
                    {
                        var dlg = $(obj);
                        if (dlg.attr('id') != undefined)
                        {
                            dlg.addClass('cls_dialog_init');
                            dlg.css('display', 'none');
                            d1.appendTo(document.body);
                            fnShowDialog(dlg.attr('id'));
                        }
                        break;
                    }
                }
            }
        });
        return;
    }
    var id = id_or_url;
    if (id.charAt(0) != '#')
    {
        id = '#' + id;
    }

    var dlg = $(id);
    if (dlg.size() > 0)
    {
        if (!dlg.hasClass("cls_dialog"))
        {
            g_zIndexMax++
            var mask = $('<div></div>');
            mask.attr('id', dlg.attr('id') + '_mask');
            mask.addClass('cls_dialog_mask');
            mask.css('z-index', g_zIndexMax);
            fnOnWindowResizeOrScroll(mask);
            mask.appendTo(document.body);

            g_zIndexMax++;
            dlg.addClass("cls_dialog");
            dlg.css('z-index', g_zIndexMax);
            var div_close = $("<div/>");
            div_close.addClass('cls_close');
            div_close.click(function(){
                fnCloseDialog(dlg.attr('id'));
            });
            dlg.children("div.cls_title").append(div_close);
            fnOnWindowResizeOrScroll(dlg);
            dlg.css('display', 'block');
        }
        else
        {
            g_zIndexMax++;
            var mask = $(id + '_mask');
            mask.css('z-index', g_zIndexMax);
            fnOnWindowResizeOrScroll(mask);

            g_zIndexMax++;
            dlg.css('z-index', g_zIndexMax);
            fnOnWindowResizeOrScroll(dlg);
            mask.show();
            dlg.show();
        }
    }
}//}}}

/**
 * PUBLIC
 * 获取ID属性为strID的标签内的所有含有CSS类cls_field的输入标签的值组成一个数组
 * 所有的输入元素都取其name属性值作为键，取其value(textarea取值)作为值
 * @param   string strID    ID属性值
 * @return  Object          即map<string, string>类型数据,对于select多选
 *                          checkbox多选,则将其值用英文逗号分隔开
 */
function fnGetFormData(strID)//{{{
{
    if (strID.charAt(0) != '#')
    {
        strID = '#' + strID;
    }
    var param = new Object();
    $(strID + " input.cls_field[type='text']").each(function(){
        param[$(this).attr('name')] = $(this).get(0).value;
    });

    $(strID + " select.cls_field").each(function(){
        if ($(this).attr('multiple'))
        {
            var val_arr = '';
            $(this).children("option:selected").each(function(){
                if (val_arr == '')
                {
                    val_arr = $(this).attr('value');
                }
                else
                {
                    val_arr += "," + $(this).attr('value');
                }
            });
            param[$(this).attr('name')] = val_arr;
        }
        else
        {
            param[$(this).attr('name')] = $(this).get(0).value;
        }
    });

    $(strID + " textarea.cls_field").each(function(){
        param[$(this).attr('name')] = $(this).get(0).value;
    });

    $(strID + " input.cls_field[type='password']").each(function(){
        param[$(this).attr('name')] = $(this).get(0).value;
    });

    $(strID + " input.cls_field[type='radio']").each(function(){
        if ($(this).get(0).checked == true)
        {
            param[$(this).attr('name')] = $(this).get(0).value;
        }
    });

    $(strID + " input.cls_field[type='checkbox']").each(function(){
        if ($(this).get(0).checked == true)
        {
            if (param[$(this).attr('name')] == undefined)
            {
                param[$(this).attr('name')] = $(this).get(0).value;
            }
            else
            {
                param[$(this).attr('name')] += ',' + $(this).get(0).value;
            }
        }
    });

    $(strID + " input.cls_field[type='hidden']").each(function(){
        param[$(this).attr('name')] = $(this).get(0).value;
    });
    return param;
}//}}}


/**
 * PUBLIC
 * 设置ID属性为strID的标签内的所有checkbox元素中名字为strCheckBoxName的选中状态为
 * checked所指状态
 * @param   string  strID           范围ID属性值
 * @param   string  strCheckBoxName checkbox的name属性值
 * @param   bool    checked         是否选中
 */
function fnSelAll(strID, strCheckBoxName, checked)
{
    $("#" + strID + " input[name='" + strCheckBoxName + "']").each(function(){
        this.checked = checked;
    });
}

/**
 * PUBLIC
 * 获取ID属性为strID的标签内的所有checkbox元素中选中项的name属性值为
 * strCheckBoxName的value属性值,将其用","连接起来返回
 * @param   string  strID           范围ID属性值
 * @param   string  strCheckBoxName checkbox的name属性值
 * @return  string                  返回选中项的value值连接字符串
 */
function fnGetCheckBoxValues(strID, strCheckBoxName)
{
    var str = '';
    $("#" + strID + " input[name='" + strCheckBoxName + "']:checked").each(function(){
        str += ',' + this.value;
    });
    if (str.length > 0)
    {
        str = str.substr(1);
    }
    return str;
}


/**
 * objoptions使用方法：
 * 对于要编辑的单元格td,添加editable=true属性
 * fnInitGrid(xxxxxx, {oncellsubmit: fnOnCellSubmit});
 * function fnOnCellSubmit(objtd, objeditor)
 * {
 *      var cellValue = objeditor.value;
 *      var cellName = $(objtd).attr('cell');
 *      // 这中间写数据验证方法
 *      ........
 *      return true;    // 返回false，则验证未通过，返回true则验证统共，编辑器消失
 * }
 */
function fnInitGrid(strExp, objoptions)
{
    if ($(strExp).hasClass('cls_flexigrid'))
    {
        return;
    }
    if ($(strExp).hasClass("cls_grid") == false)
    {
        $(strExp).addClass("cls_grid");
    }
    $(strExp).addClass('cls_flexigrid');

    var td1 = $(strExp).find("tr.cls_title td:first");
    var str_checkbox_html = '';
    if (td1.find("input[type='checkbox']").size() > 0)
    {
        str_checkbox_html = td1.html();
    }
    $(strExp).each(function(){
        var header = new Array();
        $(this).find("tr.cls_title td").each(function(){
            var col = {display: $(this).text(), 
                            width: $(this).attr('width'), 
                            align: $(this).attr('align')};
            if (col.width == undefined)
            {
                col.width = 80;
            }
            if (col.align == undefined)
            {
                col.align = 'left';
            }
            header[header.length] = col;
        });
        $(this).find("tr.cls_title").remove();

        var property = {height: 'auto', 
                        colModel: header, 
                        striped: false, 
                        resizable: false,
                        dblClickResize: true,
                        showToggleBtn: false};
        //var property = param;
        if ($(this).attr('height') != undefined)
        {
            property.height = $(this).attr('height');
            $(this).removeAttr('height');
        }
        if ($(this).attr('title') != undefined)
        {
            property.title = $(this).attr('title');
            if ($(this).attr('showTableToggleBtn') != undefined)
            {
                property.showTableToggleBtn = $(this).attr('showTableToggleBtn') == 'true';
                $(this).removeAttr('showTableToggleBtn');
            }
            else
            {
                property.showTableToggleBtn = false;
            }
            $(this).removeAttr('title');
        }


        // add editor for editable cell
        $(this).find("td[editable='true']").dblclick(function(){
            if ($(this).find("input").size() > 0)
            {
                return;
            }
            var v = $(this).text().trim();
            var editor = $("<input/>").attr('type', 'text').attr('value', v)
                .css('width', '100%').keypress(function(ev){
                    if (ev.keyCode == 13)
                    {
                        if (typeof(objoptions) == 'object'
                            && typeof(objoptions.oncellsubmit) == 'function')
                        {
                            var cell = $(this).parent().parent().get(0);
                            if (objoptions.oncellsubmit(cell, this) == false)
                            {
                                return;
                            }
                        }
                        //$(this).parent().html($(this).attr('value'));
                    }
                    else if (ev.keyCode == 27)
                    {
                        if (v == '')
                        {
                            v = '&nbsp;';
                        }
                        $(this).parent().html(v);
                    }
                });
            $(this).children("div").empty().append(editor);
            editor.focus();
            editor.select();
        });
        ///////////////////////////////

        $(this).flexigrid(property);
    });

    if (str_checkbox_html != '')
    {
        $(strExp).parent("div.bDiv").prev().prev().find(
                "thead tr:first th:first div").html(str_checkbox_html);
    }
}

/**
 * PRIVATE
 * 在窗口大小改变或滚动时动态调整层对话框的位置,该方法为层对话框机制内部使用方法
 * 在层对话框显示和窗口大小改变或滚动时需要调用该方法,以动态调整层对话框的位置
 * 和大小
 * @param   object  jqobj   如果其type值为undefined,则表示为显示某一个层对话框
 *                          调用,否则为window.resize或window.scroll事件调用
 * @return  void
 */
function fnOnWindowResizeOrScroll(jqobj)//{{{
{
    var h = $(window).height();
    var w = $(window).width();
    //var l = $(window).scrollLeft();
    //var t = $(window).scrollTop();

    if (jqobj.type == undefined)
    {
        if (jqobj.hasClass('cls_dialog_mask'))
        {
            //jqobj.css('top', t);
            //jqobj.css('left', l);
            jqobj.height(h);
            jqobj.width(w);
        }
        else if (jqobj.hasClass('cls_dialog'))
        {
            var top = (h - jqobj.height()) / 3;
            if (top < 0)
            {
                top = 0;
            }
            jqobj.css('top', top);
            var left = (w - jqobj.width()) / 2;
            if (left < 0)
            {
                left = 0;
            }
            jqobj.css('left', left);
        }
    }
    else
    {
        var mask = $("div.cls_dialog_mask");
        mask.height(h);
        mask.width(w);
        //mask.css('left', l);
        //mask.css('top', t);

        $("div.cls_dialog").each(function(){
            if ($(this).css('display') == 'block')
            {
                var top = (h  - $(this).height()) / 3;
                if (top < 0)
                {
                    top = 0;
                }
                $(this).css('top', top);
                var left = (w - $(this).width()) / 2;
                if (left < 0)
                {
                    left = 0;
                }
                $(this).css('left', left);
            }
        });
    }
}//}}}

/**
 * PRIVATE
 * 初始化执行过程:
 *      1. 防止在编辑框里点击backspace键时网页回退
 *      2. 注册窗口调整和滚动事件时的层对话框处理事件
 */
$(function(){//{{{
    $(document).bind("keydown", function(e){
        var doPrevent;
        if (e.keyCode == 8)
        {
            var d = e.srcElement || e.target;
            if (d.tagName.toUpperCase() == 'INPUT' 
                || d.tagName.toUpperCase() == 'TEXTAREA')
            {
                doPrevent = d.readOnly || d.disabled;
            }
            else
            {
                doPrevent = true;
            }
        }
        else
        {
            doPrevent = false;
        }
        
        if (doPrevent)
        {
            e.preventDefault();
        }
    });

    $(window).resize(fnOnWindowResizeOrScroll);
    $(window).scroll(fnOnWindowResizeOrScroll);

    //fnInitGrid("table.cls_grid");
});//}}}
