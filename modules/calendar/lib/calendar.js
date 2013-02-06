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
		 * When user clicks the '+' on an individual calendar date,
		 * show a form that allows them to create a post for that date
		 */
		init : function(){

			// Bind the form display to '+' button
			jQuery('td.day-unit .schedule-new-post-button').on('click.editFlow', function(e){

					e.preventDefault();

					// Close other overlays
					edit_flow_calendar_close_overlays();

					// Get the current calendar square
					EFQuickPublish.$current_date_square = jQuery(this).parent();

					//Get our form content
					var $new_post_form_content = EFQuickPublish.$current_date_square.find('.post-insert-dialog');

					//Inject the form (it will automatically be removed on click-away because of its 'item-overlay' class)
					EFQuickPublish.$new_post_form = $new_post_form_content.clone().addClass('item-overlay').appendTo(EFQuickPublish.$current_date_square);
					
					// Get the inputs and controls for this injected form and focus the cursor on the post title box
					var $edit_post_link = EFQuickPublish.$new_post_form.find('.post-insert-dialog-edit-post-link');
					EFQuickPublish.$post_title_input = EFQuickPublish.$new_post_form.find('.post-insert-dialog-post-title').focus();

					// Setup the ajax mechanism for form submit
					EFQuickPublish.$new_post_form.on( 'submit', function(e){
						e.preventDefault();
						EFQuickPublish.ajax_ef_create_post(false);
					});

					// Setup direct link to new draft
					$edit_post_link.on( 'click', function(e){
						e.preventDefault();
						EFQuickPublish.ajax_ef_create_post(true);
					} );

					return false; // prevent bubbling up

				}); // add new post click event

		}, // init

		/**
		 * Sends an ajax request to create a new post
		 * @param  bool redirect_to_draft Whether or not we should be redirected to the post's edit screen on success
		 */
		ajax_ef_create_post : function( redirect_to_draft ){

			// Get some of the form elements for later use
			var $submit_controls = EFQuickPublish.$new_post_form.find('.post-insert-dialog-controls');
			var $spinner = EFQuickPublish.$new_post_form.find('.spinner');

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
						ef_insert_date: EFQuickPublish.$new_post_form.find('input.post-insert-dialog-post-date').val(),
						ef_insert_title: EFQuickPublish.$post_title_input.val(),
						nonce: jQuery(document).find('#ef-calendar-modify').val()
					},
					success: function( response, textStatus, XMLHttpRequest ) {

						if( response.status == 'success' ){

							//The response message on success is the html for the a post list item
							var $new_post = jQuery(response.message);

							if( redirect_to_draft ) {
								//If user clicked on the 'edit post' link, let's send them to the new post
								var edit_url =  $new_post.find('.item-actions .edit a').attr('href');
								window.location = edit_url;
							} else {
								// Otherwise, inject the new post and bind the appropriate click event
								$new_post.appendTo( EFQuickPublish.$current_date_square.find('ul.post-list') );
								$new_post.on('click.ef-calendar-show-overlay', edit_flow_calendar_show_overlay );
								edit_flow_calendar_close_overlays();
							}

						} else {
							EFQuickPublish.display_errors( EFQuickPublish.$new_post_form, response.message );
						}
					},
					error: function( XMLHttpRequest, textStatus, errorThrown ) {
						EFQuickPublish.display_errors( EFQuickPublish.$new_post_form, errorThrown );
					}

				}); // .ajax

				return false; // prevent bubbling up

			}, 200); // setTimout

		}, // ajax_ef_create_post

		/**
		 * Displays form errors and resets the UI
		 * @param  jQueryObj $form The form to display the errors in
		 * @param  str error_msg Error message
		 */
		display_errors : function( $form, error_msg ){

			$form.find('.error').remove(); // clear out old errors
			$form.find('.spinner').hide(); // stop the loading animation

			// show submit controls and the error
			$form.find('.post-insert-dialog-controls').show().before('<div class="error">Error: '+error_msg+'</div>');

		} // display_errors

	}; EFQuickPublish.init();
	
});

