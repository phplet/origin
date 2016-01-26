function fnChangeOption(maxWidth, optionObj, spanStr)
{
  var allOptionWidth = 0;
  var optionArr = new Array();
  var optionHeightArr = new Array();

  optionObj.each(function () {
    var optionWidth = $(this).width() + parseInt($(this).css("margin-left"));
    var fourWidth = Math.floor((maxWidth) / 4);
    var twoWidth = Math.floor((maxWidth) / 2);
    var optionStr = $(this).find('.' + spanStr).text();
    var option = optionStr.substr(0, 1);
    
    if (option == 'A') {
      if (maxWidth < allOptionWidth)
      {
        optionArr = optionArr.sort();
        maxOptionWidth = optionArr[optionArr.length - 1];

        if (maxOptionWidth <= fourWidth)
        {
          fourWidth = fourWidth - parseInt($(this).css("margin-left"));
          $('.cls_inside').width(fourWidth + 'px');
        } 
        else if (maxOptionWidth <= twoWidth && maxOptionWidth > fourWidth)
        {
          twoWidth = twoWidth - parseInt($(this).css("margin-left"));
          $('.cls_inside').width(twoWidth + 'px');
        } 
        else if (maxOptionWidth <= maxWidth && maxOptionWidth > twoWidth)
        {
          $('.cls_inside').width(maxWidth + 'px');
        }
      }

      $(".cls_inside").has("img").each(function(){
          optionHeightArr.push($(this).find("img").height());
      });

      maxOptionHeight = Math.max.apply(Math,optionHeightArr);

      $(".cls_inside").has("img").css("height",maxOptionHeight);
      $(".cls_inside").css({"lineHeight":maxOptionHeight + "px","height":maxOptionHeight + "px"})

      $('.cls_inside').addClass('cls_outside');
      $('.cls_outside').removeClass('cls_inside');
      allOptionWidth = 0;
      optionArr = [];
      optionHeightArr = [];
      allOptionWidth += optionWidth;
      optionArr.push(optionWidth);
      $(this).addClass('cls_inside');
    } else {
      allOptionWidth += optionWidth;
      optionArr.push(optionWidth);
      $(this).addClass('cls_inside');
    }

    var imgHeight = $(this).has("img").height();
    $(this).has("img").find("." + spanStr).addClass("fl").css({"lineHeight":imgHeight + "px","height":imgHeight + "px"});

  });
}
$(function(){
    maxWidth = Math.round($('.q_info').width() * 0.94);
    optionObj = $('.q_option');
    spanStr = 'q_option_font';
    //spanObj = $(".q_option .q_option_font")
    $(window).load(function(){
       fnChangeOption(maxWidth, optionObj, spanStr);
    });
    
});