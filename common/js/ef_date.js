jQuery(document).ready(function() {
	jQuery('.date-pick')
		.datepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
		});
});
