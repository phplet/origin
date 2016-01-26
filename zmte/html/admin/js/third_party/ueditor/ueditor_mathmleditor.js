UE.registerUI('formula_mathmleditor', function(editor, uiName){
    /*
    editor.registerCommand(uiName, {
        execCommand:function(){
            alert('hello');
        }
    });
    */

    var dialog = new UE.ui.Dialog({
        iframeUrl: '/js/third_party/MathMLEditor/MathMLEditor.html',
        editor: editor,
        name: 'dialog_' + uiName,
        title: 'MathMLEditor公式编辑',
        cssRules: 'width:620px;height:380px;',
        leftDelim: '\\(',
        texSource: null,
        rightDelim: '\\)',
        buttons:[
            {
                className: 'edui-okbutton',
                label: '确定',
                onclick:function(){
                    var code = document.getElementById(dialog.id + '_iframe').contentWindow.fnGetSWF().getLaTeX();
                    if (code == undefined)
                    {
                        alert('公式编辑器没有成功加载');
                        dialog.close(false);
                        editor.focus();
                        return;
                    }
                    var pos1 = code.indexOf('$') + 1;
                    var pos2 = code.lastIndexOf('$');
                    code = dialog.leftDelim + code.substr(pos1, pos2 - pos1) + dialog.rightDelim;

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
        var obj = document.getElementById(dialog.id + '_iframe').contentWindow.fnGetSWF();
        if (obj == undefined)
        {
            fnInitProc();
            return;
        }
        obj.setLaTeX('$' + dialog.texSource + '$');
        fnInitProc = null;
    };

    var fnInitProc = null;

    var btn = new UE.ui.Button({
        name: 'button_' + uiName,
        title: 'MathMLEditor',
        cssRules:'background-position: -750px -76px;',
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
