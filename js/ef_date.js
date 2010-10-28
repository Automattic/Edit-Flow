Date.format = 'mmm dd yyyy';
jQuery(document).ready(function($) {
	$('.date-pick')
		.datePicker({
			createButton: false,
			startDate: 'Jan 01 1970',
			clickInput: true}
			);
});
