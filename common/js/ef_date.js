jQuery(document).ready(function() {
	jQuery('.date-time-pick')
		.datetimepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
			alwaysSetTime: false,
			controlType: 'select'
		});

	jQuery('.date-pick')
		.datepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day
		});
});
