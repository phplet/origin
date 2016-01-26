UE.registerUI('formula_latexeditor', function(editor, uiName){
    /*
    editor.registerCommand(uiName, {
        execCommand:function(){
            alert('hello');
        }
    });
    */

    var dialog = new UE.ui.Dialog({
        iframeUrl: '/js/third_party/LaTeXEditor/LaTeXEditor.html',
        editor: editor,
        name: 'dialog_' + uiName,
        title: 'LaTeXEditor公式编辑',
        cssRules: 'width:620px;height:380px;',
        leftDelim: '\\(',
        texSource: null,
        rightDelim: '\\)',
        buttons:[
            {
                className: 'edui-okbutton',
                label: '确定',
                onclick:function(){
                    var code = document.getElementById(dialog.id + '_iframe').contentWindow.document.getElementById("MathInput").value;
                    if (code == undefined)
                    {
                        alert('公式编辑器没有成功加载');
                        dialog.close(false);
                        editor.focus();
                        return;
                    }
                    code = dialog.leftDelim + $.trim(code) + dialog.rightDelim;

                    dialog.close(true);
                    editor.focus();
                    if (editor.selection.getText().length > 0)
                    {
                        // replacement
                        editor.selection.clearRange();
                        editor.execCommand('inserthtml', code);
                    }
                    else
                    {
                        // insert
                        editor.execCommand('inserthtml', code);
                    }
                }
            },
            {
                className:'edui-cancelbutton',
                label: '取消',
                onclick:function(){
                    dialog.close(false);
                    editor.focus();
                }
            }
        ]
    });

    var fnInitMathMLEditor = function()
    {
        var win = document.getElementById(dialog.id + '_iframe').contentWindow;
        if (win == undefined)
        {
            fnInitProc();
            return;
        }
        var obj = win.document.getElementById("MathInput");
        if (obj == undefined)
        {
            fnInitProc();
            return;
        }
        obj.value = dialog.texSource;
        win.Preview.Update();
        fnInitProc = null;
    };

    var fnInitProc = null;

    var btn = new UE.ui.Button({
        name: 'button_' + uiName,
        title: 'LaTeXEditor',
        cssRules:'background-position: -750px -100px;',
        onclick:function(){
            editor.focus();
            dialog.leftDelim = '\\(';
            dialog.rightDelim = '\\)';
            dialog.texSource = null;
            fnInitProc = null;

            var str = $.trim(editor.selection.getText());
            var a2 = str.substr(0, 2);
            var b2 = str.substr(-2, 2);
            if (a2 == '$$' && b2 == '$$')
            {
                dialog.leftDelim = '$$';
                dialog.rightDelim = '$$';
                dialog.texSource = str.substr(2, str.length - 4);
            }
            else if (a2 == '\\(' && b2 == '\\)')
            {
                dialog.texSource = str.substr(2, str.length - 4);
            }
            else if (a2 == '\\[' && b2 == '\\]')
            {
                dialog.leftDelim = '\\[';
                dialog.rightDelim = '\\]';
                dialog.texSource = str.substr(2, str.length - 4);
            }
            if (dialog.texSource == null)
            {
            }
            else
            {
                fnInitProc = UE.utils.defer(fnInitMathMLEditor, 1000, true);
                fnInitProc();
            }
            dialog.render();
            dialog.open();
        }
    });

    return btn;
});
