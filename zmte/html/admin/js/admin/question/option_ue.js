$(function(){
    // 解答题答案编辑器
    var editor_reference = UE.getEditor('input_reference_answer');
    editor_reference.ready(function() {
        editor_reference.execCommand('serverparam', {
            'dirName': dirName
        });
    });

    // 填空题非机考输入框
    var editor_answer = UE.getEditor('input_answer_ue');
    editor_answer.ready(function() {
        editor_answer.execCommand('serverparam', {
            'dirName': dirName
        });
    });

    window.editor_answer = editor_answer;

    // 选项编辑器
    var option_editor = {};
    $(".ueditor_option").each(function(i,item){
        $(this).attr('id','option_'+i);
        option_editor.i = UE.getEditor('option_'+i,{
            enterTag:'<br>',
            initialFrameWidth:720,
            initialFrameHeight:60
        })

        option_editor.i.ready(function() {
            this.execCommand('serverparam', {
                'dirName': dirName
            });
        });
    });
})