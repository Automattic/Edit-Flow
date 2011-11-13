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
	
	/**
	 * Dynamically set the width of the metaboxes on page load
	 */
	jQuery(".postbox-container").css('width', (100 / ef_story_budget_number_of_columns) + '%' );
	
	// Change number of columns when choosing a new number from Screen Options
	$("input[name=ef_story_budget_screen_columns]").click(function() {
		var numColumns = $(this).val();
		
		jQuery(".postbox-container").css('width', (100 / numColumns) + '%' );
	});
});
