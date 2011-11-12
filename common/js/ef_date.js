jQuery(document).ready(function() {
	jQuery('.date-pick')
		.datepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
		});
			
	jQuery('span.description .clear-date').click(function() {
		$(this).closest('div').find('input.date-pick').val('');
		return false;
	})			
});
