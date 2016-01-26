var root_url = typeof(root_url) == "undefined" ? '' : root_url;

/**
 * 获取省
 * @param p_id
 * @param school_country
 * @param school_province
 * @param school_area
 */
function region_province(p_id, school_country, school_province, school_area,  grade_id, school_property)
{
	p_id = p_id || 0;
	
    $.getJSON(root_url+'/student/common/regions/',{'p_id':p_id},function(data) {
        var len = data.length;
        var _depth_1_html = '';
        for(var i=0;i<len;i++){
    		_depth_1_html += '<li id="c_'+data[i]['region_id']+'"><a>'+data[i]['region_name']+'</a></li>';
    	}
    	
    	$("#popup-country").html(_depth_1_html);
    	if (school_country > 0)
    	{
    		var province_id = school_country;
    		$('#c_'+school_country).addClass("active");
        }
    	else
    	{
    		var province_id = $('li[id^=c_]').eq(0).attr('id').split('_')[1]
    		$('li[id^=c_]').eq(0).addClass("active");
    	}
        region_city(province_id, school_province, school_area,  grade_id, school_property);
	});
}

/**
 * 获取市
 * @param p_id
 * @param school_province
 * @param school_area
 */
function region_city(p_id, school_province, school_area,  grade_id, school_property)
{
	p_id = p_id || 0;
	
    $.getJSON(root_url+'/student/common/regions/',{'p_id':p_id},function(data) {
        var len = data.length;
        var _depth_2_html = '';
        for(var i=0;i<len;i++){
    		_depth_2_html += '<li id="p_'+data[i]['region_id']+'"><a>'+data[i]['region_name']+'</a></li>';
    	}

        $("#popup-province").html(_depth_2_html);
        if (school_province > 0)
        {
        	var city_id = school_province;
    		$('#p_'+school_province).addClass("active");
        }
        else
        {
        	var city_id = $('li[id^=p_]').eq(0).attr('id').split('_')[1]
    		$('li[id^=p_]').eq(0).addClass("active");
        }
		
        region_area(city_id, school_area, grade_id, school_property);
	});
}

/**
 * 获取地区
 * @param p_id
 * @param school_area
 */
function region_area(p_id, school_area, grade_id, school_property)
{
	p_id = p_id || 0;
	
    $.getJSON(root_url+'/student/common/regions/',{'p_id':p_id},function(data) {
        var len = data.length;
        var _depth_3_html = '';
        _depth_3_html += '<li id="a_0"><a>全部</a></li>';
        for(var i=0;i<len;i++){
    		_depth_3_html += '<li id="a_'+data[i]['region_id']+'"><a>'+data[i]['region_name']+'</a></li>';
    	}

        $("#popup-area").html(_depth_3_html);
        if (school_area > 0)
        {
        	var area_id = school_area;
    		$('#a_'+school_area).addClass("active");
        }
        else
        {
            var area_id = $('li[id^=a_]').eq(0).attr('id').split('_')[1];
    		$('li[id^=a_]').eq(0).addClass("active");
        }

		show_school(area_id, p_id, grade_id, school_property);
	});
}

/**
 * 获取学校
 * @param area_id
 */
function show_school(area_id, city_id, val_grade_id, val_school_property){
	var keyword = $("#school_search_input").val();
	var grade_id = val_grade_id == undefined ?  $("#select_grade_id").val() : val_grade_id;
	var school_property = val_school_property == undefined ? $("#select_school_property").val() : val_school_property;
	$.getJSON(root_url+'/student/common/school_list/',{'area_id':area_id,'keyword':keyword,'grade_id':grade_id,'city_id':city_id,'school_property':school_property},function(data) {
        var len = data.length;
        var _school_html = '';
        for(var i=0;i<len;i++){
        	_school_html += '<li id="u_'+data[i]['school_id']+'"><a>'+data[i]['school_name']+'</a></li>';
    	}
    	
        if(_school_html==''){
			$('#popup-unis').html('<li>此年级下，没有匹配的学校</li>');
		}else{
			$('#popup-unis').html(_school_html);
		}
	});
}
