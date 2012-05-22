function stopPropag(event) {
	event.stopPropagation();
	return false ; 
}

function folderToggle(event, element) {
	element.parent().toggleClass("minus_folder plus_folder")
	event.stopPropagation();
	jQuery.fn.fadeThenSlideToggle = function(speed, easing, callback) {
		if (this.is(":hidden")) {
			return this.slideDown(speed, easing).fadeTo(speed, 1, easing, callback);
		} else {
			return this.fadeTo(speed, 0, easing).slideUp(speed, easing, callback);
		}
	};
	
	element.fadeThenSlideToggle(500);
	
	return false ; 
}