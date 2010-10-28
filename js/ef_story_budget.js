// Story Budget specific JS, assumes that ef_date.js has already been included

jQuery(document).ready(function($) {
	$("#toggle_details").click(function() {
		$(".post-title > p").slideToggle(); // hide post details when directed to
	});
	$("h3.hndle,div.handlediv").click(function() {
		$(this).parent().children("div.inside").slideToggle(); // hide sections when directed to
	});
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
