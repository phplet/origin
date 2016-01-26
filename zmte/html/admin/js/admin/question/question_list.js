// 按选择的年级区间，显示相应年级的类型的文理科属性
function set_question_class() {
    var start_grade = parseInt($('#start_grade').val());
    var end_grade = parseInt($('#end_grade').val());
    var subject_id = parseInt($('#subject_id').val());
    if (isNaN(start_grade) || isNaN(end_grade) || start_grade > end_grade) return;
    if (isNaN(subject_id)) subject_id = 0;
    for (var i=1; i<=12; i++) {
        if (i < start_grade || i > end_grade) {
            $('#grade'+i+'_class').hide();
        } else {
            $('#grade'+i+'_class').show();
            if (i >= 11) {
                if ( subject_id>=1 && subject_id <=3 ) {
                    $('#grade'+i+'_class').find('select').show();
                } else {
                    $('#grade'+i+'_class').find('select').hide();
                }
            }            
        }        
    }
}