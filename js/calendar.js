jQuery(document).ready(function () {
	
	jQuery('a.show-more').click(function(){
		var parent = jQuery(this).closest('td.day-unit');
		jQuery('ul li', parent).removeClass('hidden');
		jQuery(this).hide();
		return false;
	});
	
	/**
	 * Instantiates drag and drop sorting for posts on the calendar
	 */
	jQuery('td.day-unit ul').sortable({
		items: 'li.day-item.sortable',
		connectWith: 'td.day-unit ul',
		placeholder: 'ui-state-highlight',
		start: function(event, ui) {
			jQuery(this).css('cursor','move');
		},
		sort: function(event, ui) {
			jQuery('td.day-unit').removeClass('ui-wrapper-highlight');
			jQuery('.ui-state-highlight').closest('td.day-unit').addClass('ui-wrapper-highlight');
		},
		stop: function(event, ui) {
			jQuery(this).css('cursor','auto');
			jQuery('td.day-unit').removeClass('ui-wrapper-highlight');
			// Don't do anything if we didn't move it past the today
			if ( jQuery(this).closest('.day-unit').attr('id') == jQuery(ui.item).closest('.day-unit').attr('id') )
				return;
			var post_id = jQuery(ui.item).attr('id').split('-');
			post_id = post_id[post_id.length - 1];
			var prev_date = jQuery(this).closest('.day-unit').attr('id');
			var next_date = jQuery(ui.item).closest('.day-unit').attr('id');
			var nonce = jQuery(document).find('#ef-calendar-modify').val();
			// make ajax request
			var params = {
				action: 'ef_calendar_drag_and_drop',
				post_id: post_id,
				prev_date: prev_date,
				next_date: next_date,
				nonce: nonce,
			};
			jQuery.post(ajaxurl, params,
				function(response) {
					// @todo handle the response
				}
			);
		},
	});
	jQuery('td.day-unit ul li.day-item').disableSelection();
	
	
});

