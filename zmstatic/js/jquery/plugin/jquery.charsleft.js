/** 
 * @projectDescription Characters Left Plug-in
 * @author 	Matt Hobbs (http://nooshu.com/)
 * @version 0.1
 * 
 * Plugin counts the number of characters a user has entered
 * in a textarea / input and adds a short paragraph reminding
 * the user.
 */
(function($){
	/**
	 * mouse paste
	 */
	$.fn.pasteEvents = function( delay ) {
	    if (delay == undefined) delay = 20;
	    return $(this).each(function() {
	        var $el = $(this);
	        $el.on("paste", function() {
	            $el.trigger("prepaste");
	            setTimeout(function() { $el.trigger("postpaste"); }, delay);
	        });
	    });
	};
	
	$.fn.charsLeft = function(customOptions){
		//Merge default and user options
		var options = $.extend({}, $.fn.charsLeft.defaultOptions, customOptions);
		return this.each(function(i){
			var $this = $(this);
			
			//Construct our HTML
			var charHTML = "<div class='" + options.wrapperClass + "'>";
			charHTML += options.charPrefix;
			charHTML += " <span class='" + options.countClass + "'>" + options.maxChars + "</span> ";
			charHTML += options.charSuffix;
			charHTML += "</div>";
			
			//Attach our HTML
			switch(options.attachment){
				case "before":
					$this.before(charHTML);
					$charCount = $this.prev('div').find("." + options.countClass);
					break;
				case "after":
					$this.after(charHTML);
					$charCount = $this.next('div').find("." + options.countClass);
					break;
				default:
					$this.after(charHTML);
					$charCount = $this.next('div').find("." + options.countClass);
					break;
			}
			
			
			//Look at the length / what's left
			var messageLength = $this.val().length;
			var messageCharsLeft = options.maxChars - messageLength;
			
			//On reload, if teaxtarea is filled, update value
			if(messageLength){
				$charCount.text(messageCharsLeft);
			}
			
			//Bind the update on textarea keyup
			var _disableMode = options.disableMode,
				_maxChars = options.maxChars;
			$this.bind("keyup.charsLeft", function(){
				messageLength = $this.val().length;
				messageCharsLeft = _maxChars - messageLength;
				
				//Attach our HTML
				switch(options.attachment){
					case "before":
						$charCount = $this.prev('div').find("." + options.countClass);
						break;
					case "after":
						$charCount = $this.next('div').find("." + options.countClass);
						break;
					default:
						$charCount = $this.next('div').find("." + options.countClass);
						break;
				}
				$charCount.text(messageCharsLeft);
				
				//Add error class if to many chars
				if (!_disableMode) {
					(messageCharsLeft < 0) ? $this.addClass(options.errorClass) : $this.removeClass(options.errorClass);
				} else {
					if (messageCharsLeft < 0) {
						$charCount.text(0);
						$this.val($this.val().substr(0, _maxChars));
					}
				}
			}).on("postpaste", function() { 
			    // paste event do something
				$(this).keyup();
			}).pasteEvents();
		});
	};
	
	//Set our plugin defaults
	$.fn.charsLeft.defaultOptions = {
		maxChars: 140,
		disableMode:false,
		charPrefix: "You have",
		charSuffix: "characters left.",
		attachment: "after",
		wrapperClass: "charsLeft",
		countClass: "charCount",
		errorClass: "charError"
	};
})(jQuery);