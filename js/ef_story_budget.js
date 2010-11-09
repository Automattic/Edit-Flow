// Story Budget specific JS, assumes that ef_date.js has already been included

jQuery(document).ready(function($) {
	// Hide all post details when directed
	$("#toggle_details").click(function() {
		$(".post-title > p").toggleClass('hidden'); 
	});
	
	// Make print link open up print dialog
	$("#print_link").click(function() {
		window.print();
		return false;
	});
	
	// Hide a single section when directed
	$("h3.hndle,div.handlediv").click(function() {
		$(this).parent().children("div.inside").slideToggle();
	});
	
	// Change number of columns when choosing a new number from Screen Options
	$("input[name=ef_story_budget_screen_columns]").click(function() {
		// Get the new number of columns
		var numColumns = $(this).val();
		
		// Grab the total width constant (percentage) output by the PHP
		var totalWidth = typeof(editFlowStoryBudgetColumnsWidth) !== 'undefined' ? editFlowStoryBudgetColumnsWidth : 98;
		
		// Set the width of each column based on the new number of columns
		jQuery(".postbox").css('width', totalWidth / numColumns + '%');
	});
	
	$('#start_date').bind(
		'dpClosed',
		function(e, selectedDates) {
			var d = selectedDates[0];
			if (d) {
				d = new Date(d);
				$('#end_date').dpSetStartDate(d.addDays(1).asString());
			}
		}
	);
	$('#end_date').bind(
		'dpClosed',
		function(e, selectedDates) {
			var d = selectedDates[0];
			if (d) {
				d = new Date(d);
				$('#start_date').dpSetEndDate(d.addDays(-1).asString());
			}
		}
	);
});
