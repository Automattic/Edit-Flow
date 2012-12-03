jQuery(document).ready(function () {
	
	jQuery('a.show-more').click(function(){
		var parent = jQuery(this).closest('td.day-unit');
		jQuery('ul li', parent).removeClass('hidden');
		jQuery(this).hide();
		return false;
	});
	
	// Hide a message. Used by setTimeout()
	function edit_flow_calendar_hide_message() {
		jQuery('.edit-flow-message').fadeOut(function(){ jQuery(this).remove(); });
	}
	
	// Close out all of the overlays with your escape key,
	// or by clicking anywhere other than inside an existing overlay
	jQuery(document).keydown(function(event) {
		if (event.keyCode == '27') {
			edit_flow_calendar_close_overlays();
		}
	});
	
	/**
	 * Somewhat hackish way to close overlays automagically when you click outside an overlay
	 */
	jQuery('#wpbody').click(function(event){
		// Only close them if we aren't clicking within the overlay
		if ( jQuery(event.target).closest('ul.post-list li').length == 0 && jQuery(event.target).closest('.item-overlay').length == 0 ) {
			edit_flow_calendar_close_overlays();
		}
	});
	
	/**
	 * Close all of the open overlays on the calendar
	 */
	function edit_flow_calendar_close_overlays() {
		jQuery('.item-overlay').remove();
		jQuery('td.day-unit ul li').removeClass('item-overlay-active');
	}
	
	/**
	 * Show the overlay for a post date
	 */
	function edit_flow_calendar_show_overlay( event ) {
		// Hide the overlay if it's already showing but only if we click on the original clickable area
		if ( jQuery(this).hasClass('item-overlay-active') && jQuery(event.target).closest('.item-inner').length == 0 ) {
			edit_flow_calendar_close_overlays();
		} else {
			edit_flow_calendar_close_overlays();
			var item_information = jQuery(this).html();
			jQuery(this).addClass('item-overlay-active');
			var item_overlay_html = '<div class="item-overlay">' + item_information + '</div>';
			jQuery(this).prepend( item_overlay_html );
		}
	}
	
	/**
	 * Bind the overlay click event to all list items within the posts list
	 */
	function edit_flow_calendar_bind_overlay() {
		jQuery('td.day-unit ul li').bind({
			'click.ef-calendar-show-overlay': edit_flow_calendar_show_overlay
		});
	}
	edit_flow_calendar_bind_overlay();
	
	/**
	 * Instantiates drag and drop sorting for posts on the calendar
	 */
	jQuery('td.day-unit ul').sortable({
		items: 'li.day-item.sortable',
		connectWith: 'td.day-unit ul',
		placeholder: 'ui-state-highlight',
		start: function(event, ui) {
			jQuery(this).disableSelection();
			edit_flow_calendar_close_overlays();
			jQuery('td.day-unit ul li').unbind('click.ef-calendar-show-overlay');
			jQuery(this).css('cursor','move');
		},
		sort: function(event, ui) {
			jQuery('td.day-unit').removeClass('ui-wrapper-highlight');
			jQuery('.ui-state-highlight').closest('td.day-unit').addClass('ui-wrapper-highlight');
		},
		stop: function(event, ui) {
			jQuery(this).css('cursor','auto');
			jQuery('td.day-unit').removeClass('ui-wrapper-highlight');
			// Only do a POST request if we moved the post off today
			if ( jQuery(this).closest('.day-unit').attr('id') != jQuery(ui.item).closest('.day-unit').attr('id') ) {
				var post_id = jQuery(ui.item).attr('id').split('-');
				post_id = post_id[post_id.length - 1];
				var prev_date = jQuery(this).closest('.day-unit').attr('id');
				var next_date = jQuery(ui.item).closest('.day-unit').attr('id');
				var nonce = jQuery(document).find('#ef-calendar-modify').val();
				jQuery('.edit-flow-message').remove();
				jQuery('li.ajax-actions .waiting').show();
				// make ajax request
				var params = {
					action: 'ef_calendar_drag_and_drop',
					post_id: post_id,
					prev_date: prev_date,
					next_date: next_date,
					nonce: nonce
				};
				jQuery.post(ajaxurl, params,
					function(response) {
						jQuery('li.ajax-actions .waiting').hide();
						var html = '';
						if ( response.status == 'success' ) {
							html = '<div class="edit-flow-message edit-flow-updated-message">' + response.message + '</div>';
							//setTimeout( edit_flow_calendar_hide_message, 5000 );
						} else if ( response.status == 'error' ) {
							html = '<div class="edit-flow-message edit-flow-error-message">' + response.message + '</div>';
						}
						jQuery('li.ajax-actions').prepend(html);
						setTimeout( edit_flow_calendar_hide_message, 10000 );
					}
				);
			}
			jQuery(this).enableSelection();
			// Allow the overlays to show up again
			setTimeout( edit_flow_calendar_bind_overlay, 250 );
		}
	});

	// Enables quick creation/edit of drafts on a particular date from the calendar
	var EFQuickPublish = {
		/**
		 * Binds event listeners to UI buttons
		 */
		init : function(){

			// Bind click event to '+' button
			jQuery('td.day-unit .schedule-new-post-button').on('click.editFlow', function(e){

					// Close other overlays
					edit_flow_calendar_close_overlays();

					// Get the current calendar square
					EFQuickPublish.$current_date_square = jQuery(this).parent();

					// Get the square's date from the ID
					EFQuickPublish.date = EFQuickPublish.$current_date_square.attr('id');

					// Define the html for our popup form
					// ToDo: better date formatting
					var schedule_draft_form_html="";
					schedule_draft_form_html += "<form class=\"post-insert-dialog item-overlay\">";
					schedule_draft_form_html += "	<h1>Schedule a draft for "+EFQuickPublish.date+"<\/h1>";
					schedule_draft_form_html += "	<input type=\"text\" class=\"post-insert-dialog-post-title\" name=\"post-insert-dialog-post-title\" placeholder=\"Post Title\">";
					schedule_draft_form_html += "	<div class=\"post-insert-dialog-controls\">";
					schedule_draft_form_html += "		<input type=\"submit\" class=\"button left\" value=\"Schedule Draft\">";
					schedule_draft_form_html += "		<a class=\"post-insert-dialog-edit-post-link\" href=\"#\">Edit Post &raquo;<\/a>";
					schedule_draft_form_html += "	<\/div>";
					schedule_draft_form_html += "	<div class=\"spinner\">&nbsp;<\/div>";
					schedule_draft_form_html += "<\/form>";

					var $box = jQuery(schedule_draft_form_html);

					// Add it to the calendar (it will automatically be removed on click-away because of its 'item-overlay' class)
					EFQuickPublish.$current_date_square.append($box);
					
					// Get the form and input for this calendar square
					var $form = EFQuickPublish.$current_date_square.find('form.post-insert-dialog');
					var $edit_post_link = EFQuickPublish.$current_date_square.find('.post-insert-dialog-edit-post-link');
					EFQuickPublish.$post_title_input = $form.find('.post-insert-dialog-post-title').focus();

					// Setup the ajax mechanism for form submit
					$form.on( 'submit', function(e){
						e.preventDefault();
						EFQuickPublish.ajax_ef_create_draft(false);
					});

					// Setup direct link to new draft
					$edit_post_link.on( 'click', function(e){
						e.preventDefault();
						EFQuickPublish.ajax_ef_create_draft(true);
					} );

					return false; // prevent bubbling up

				}); // add new post click event

		}, // init
		/**
		 * Sends an ajax request to create a new post
		 * @param  bool redirect_to_draft Whether or not we should be redirected to the post's edit screen on success
		 */
		ajax_ef_create_draft : function( redirect_to_draft ){

			// Get some of the form elements for later use
			var $submit_controls = EFQuickPublish.$current_date_square.find('.post-insert-dialog-controls');
			var $spinner = EFQuickPublish.$current_date_square.find('.spinner');

			// Set loading animation
			$submit_controls.hide();
			$spinner.show();

			// Delay submit to prevent spinner flashing
			setTimeout( function(){
			
				jQuery.ajax({

					type: 'POST',
					url: ajaxurl,
					dataType: 'json',
					data: {
						action: 'ef_insert_post',
						ef_insert_date: EFQuickPublish.date,
						ef_insert_title: EFQuickPublish.$post_title_input.val(),
						nonce: jQuery(document).find('#ef-calendar-modify').val()
					},
					success: function(response, textStatus, XMLHttpRequest) {

						if( response.status == 'success' ){

							/**
							 * We created a draft on the given date, now we should either go to the edit
							 * URL for the new post which is provided by response.message, or we should refresh
							 * to show the updated calendar.
							 */
							
							if( redirect_to_draft ){

								//For security, tack the path of the received edit URL onto our current domain
								a = document.createElement('a');
								a.href = response.message;
								var edit_path = a.pathname + a.search;

								//Go to the new draft's edit screen
								window.location = window.location.origin + edit_path;

							} else {
								//Refresh the page to see the new draft on the calendar
								window.location.reload(false);
							}

						} else {
							// Show an error message
							// ToDo: DRY the error clearing
							$submit_controls.show();
							$spinner.hide();
							EFQuickPublish.$current_date_square.find('.error').remove();
							$submit_controls.before('<div class="error">Error: '+response.message+'</div>');
						}
					},
					error: function(MLHttpRequest, textStatus, errorThrown) {
						//Show an error message
						// ToDo: DRY the error clearing
						$submit_controls.show();
						$spinner.hide();
						EFQuickPublish.$current_date_square.find('.error').remove();
						$submit_controls.before('<div class="error">Network Error: '+errorThrown+'</div>');
					}

				}); // .ajax

				return false; // prevent bubbling up

			}, 200); // setTimout

		} // ajax_ef_create_draft

	}; EFQuickPublish.init();
	
});

