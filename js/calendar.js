jQuery(document).ready(function () {
	
	// Don't make this look grabbable until JS has loaded
	jQuery('.item-handle').hover(function() {
		jQuery(this).css('cursor','move');
	}, function() {
		jQuery(this).css('cursor','auto');
	});
	
	jQuery(document).keydown(function(e){

		switch (e.keyCode) {
			case 37:
				window.location = jQuery('a#trigger-right').attr('href');
				break;
			case 39:
				window.location = jQuery('a#trigger-left').attr('href');
				break;
			default:
				break;
		}
		
	});

	jQuery(".week-list").sortable({
		placeholder: 'ui-state-highlight',
		connectWith: '.connectedSortable',
		cursor: 'move',
		handle: '.item-handle',
		start: function(e, ui) {
			// Match the drop zone height to the height of the dragging element
			jQuery('.ui-state-highlight').height(jQuery(ui.item[0]).height());
		},
		stop: function(e, ui) {

			jQuery('li.performing-ajax').css('display', 'inline-block');
			var post = ui.item[0].id;
			var date = ui.item[0].parentNode.id;

			// Javascript is ghetto and doesn't give month names; this is how we generate it
			var month_names = new Array("January", "February", "March", 
														"April", "May", "June", "July", "August", "September", 
														"October", "November", "December");
           
			// update the post record
			jQuery.post(window.location.href,
				{ 
					post_id: post,
					date: date
				},
				function(data) {
					// Remove the prior message (if it exists) and append a new one
					jQuery('div#message').remove();
					date = date.split('-')
					date = new Date(date[0], date[1], date[2]);
					var day = date.getDate();
					var month = date.getMonth() - 1;
					var message = 'Item due date changed to '+ month_names[month] + ' ' + day + ', ' + date.getFullYear() + '.';
					jQuery('h2').after('<div id="message" class="updated below-h2"><p>'+message+'</p></div>');
					jQuery('li.performing-ajax').css('display', 'none');
				}
       );
       
   }
	});
	jQuery(".week-list").disableSelection();
});

