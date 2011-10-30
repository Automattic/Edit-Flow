jQuery(document).ready(function($) {
	$('.date-pick')
		.datepicker({
			dateFormat: 'M dd yy'
		});
			
	$('span.description .clear-date').click(function() {
		$(this).closest('div').find('input.date-pick').val('');
		return false;
	})			
});
