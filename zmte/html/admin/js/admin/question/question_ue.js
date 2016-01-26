$(function() {
    //题目编辑器
    var editor = UE.getEditor('question_title');
    editor.ready(function() {
        editor.execCommand('serverparam', {
            'dirName': dirName
        });
    });

    window.question_editor = editor;
});