jQuery(document).ready(function () {
	
	jQuery('a.show-more').click(function(){
		var parent = jQuery(this).closest('td.day-unit');
		console.log( parent );
		jQuery('ul li', parent).removeClass('hidden');
		jQuery(this).hide();
		return false;
	});
	
	// Don't make this look grabbable until JS has loaded
	jQuery('.item-handle').hover(function() {
		jQuery(this).css('cursor','move');
	}, function() {
		jQuery(this).css('cursor','auto');
	});
	
});

