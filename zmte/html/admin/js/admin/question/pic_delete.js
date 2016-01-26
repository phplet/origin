$(document).ready(function(){
    $('.pic_delete').click(function(){
        var url = $(this).attr('ajax');
        if (typeof url == 'undefined') return;
        if ( ! confirm('您确定要删除该图片么？')) return;
        $.get(
            url,
            function(data){
                if (data.success == false) {
                    alert(data.msg);
                } else {
                    var box_id = data.type+'_pic_'+data.id;                    
                    if (data.type == 'option') {
                        $('#'+box_id).parent().find('input[name^=old_opt_picture]').val('');
                    }
                    $('#'+box_id).remove();
                }
            },
            'json'
        );
    });
});