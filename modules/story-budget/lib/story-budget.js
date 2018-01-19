// Story Budget specific JS, assumes that ef_date.js has already been included

jQuery(document).ready(function($) {
	// Hide all post details when directed
	$("#toggle_details").click(function() {
		$(".post-title > p").toggle('hidden'); 
	});
	
	// Make print link open up print dialog
	$("#print_link").click(function() {
		window.print();
		return false;
	});
	
	// Hide a single section when directed
	$("h3.hndle,div.handlediv").click(function() {
		$(this).parent().children("div.inside").toggle();
	});
	
	// Change number of columns when choosing a new number from Screen Options
	$("input[name=ef_story_budget_screen_columns]").click(function() {
		var numColumns = $(this).val();

		if ( jQuery(".postbox-container").hasClass('columns-number-1') ||
			jQuery(".postbox-container").hasClass('columns-number-2') ||
			jQuery(".postbox-container").hasClass('columns-number-3') )
			jQuery(".postbox-container").removeClass('columns-number-1 columns-number-2 columns-number-3');
		jQuery(".postbox-container").addClass('columns-number-' + numColumns );

		//jQuery(".postbox").css('width', (90 / numColumns) + '%' );
	});
	
	jQuery('h2 a.change-date').click(function(){
		jQuery(this).hide();
		jQuery('h2 form .form-value').hide();
		jQuery('h2 form input').show();
		jQuery('h2 form a.change-date-cancel').show();
		return false;
	});
	
	jQuery('h2 form a.change-date-cancel').click(function(){
		jQuery(this).hide();
		jQuery('h2 form .form-value').show();
		jQuery('h2 form input').hide();
		jQuery('h2 form a.change-date').show();
		return false;
	});
});
