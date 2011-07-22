Date.format = 'mmm dd yyyy';
jQuery(document).ready(function($) {
	$('.date-pick')
		.datePicker({
			createButton: false,
			startDate: 'Jan 01 1970',
			clickInput: true}
			);
			
	$('span.description .clear-date').click(function() {
		$(this).closest('div').find('input.date-pick').val('');
		return false;
	})			
});
