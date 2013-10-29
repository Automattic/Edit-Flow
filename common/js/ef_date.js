jQuery(document).ready(function($) {
	$('.date-time-pick')
		.datetimepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
			alwaysSetTime: false,
			controlType: 'select',
		});

	$('.date-pick')
		.datepicker({
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day
		});
});
