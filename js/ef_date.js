Date.format = 'mm/dd/yyyy';
jQuery(document).ready(function($) {
	$('.date-pick')
		.datePicker({
			createButton: false,
			startDate: '01/01/2010',
			endDate: (new Date()).asString(),
			clickInput: true}
			);
});