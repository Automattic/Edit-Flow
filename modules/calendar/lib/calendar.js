var dispatch = wp.data.dispatch;

jQuery(document).ready(function ($) {
	
	$('a.show-more').click(function(){
		var parent = $(this).closest('td.day-unit');
		$('ul li', parent).removeClass('hidden');
		$(this).hide();
		return false;
	});

	/**
	 * Listen for click event and subsitute correct type of replacement
	 * html given the input type
	 */
	$('.day-unit').on('click', '.editable-value', function( event ) {	
		//Reset anything that was currently being edited.
		reset_editorial_metadata();
		var  t = this,
		$editable_el = $(this).addClass('hidden').next('.editable-html');

		if( $editable_el.children().first().is( 'select' ) ) {
			$editable_el.find('option')
				.each( function() {
					if( $(this).text() == $(t).text() ) {
						$(this).attr('selected', 'selected' );
					}
				});
		}

		$editable_el.removeClass('hidden')
			.addClass('editing')
			.closest('.day-item')
			.find('.item-actions .save')
			.removeClass('hidden');

	});

	//Save the editorial metadata we've changed
	$('.day-unit').on('click', 'a#save-editorial-metadata', function() {
		var post_id = $(this).attr('class').replace('post-', '');
		save_editorial_metadata(post_id);
		return false;
	});


	function reset_editorial_metadata() {
		$('.editing').removeClass('editing')
			.addClass('hidden')
			.prev()
			.removeClass('hidden');

		$('.item-actions .save').addClass('hidden');
	}

	/**
	 * save_editorial_metadata
	 * Save the editorial metadata that's been edited (whatever is marked '#actively-editing').
	 * @param post_id Id of post we're editing
	 */
	function save_editorial_metadata(post_id) {
		var metadata_info = {
			action: 'ef_calendar_update_metadata',
			nonce: $("#ef-calendar-modify").val(),
			metadata_type: $('.editing').attr('data-type'),
			metadata_value: $('.editing').children().first().val(),
			metadata_term: $('.editing').attr('data-metadataterm'),
			post_id: post_id
		};

		$('.editing').addClass('hidden')
			.after($('<div class="spinner spinner-calendar"></div>').show());

		// Send the request
		jQuery.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : metadata_info,
			success : function(x) { 
				var val = $('.editing').children().first();
				if( val.is('select') ) {
					val = val.find('option:selected').text();
				} else {
					val = val.val();
				}

				$('.editing').next()
					.remove();

				$('.editing').addClass('hidden')
					.removeClass('editing')
					.prev()
					.removeClass('hidden')
					.text(val);

					reset_editorial_metadata();
			},
			error : function(r) { 
				$('.editing').next('.spinner').replaceWith('<span class="edit-flow-error-message">Error saving metadata.</span>'); 
			}
		});
		
	}
	
	// Hide a message. Used by setTimeout()
	function edit_flow_calendar_hide_message() {
		$('.edit-flow-message').fadeOut(function(){ $(this).remove(); });
	}
	
	// Close out all of the overlays with your escape key,
	// or by clicking anywhere other than inside an existing overlay
	$(document).keydown(function(event) {
		if (event.keyCode == '27') {
			edit_flow_calendar_close_overlays();
		}
	});
	
	/**
	 * Somewhat hackish way to close overlays automagically when you click outside an overlay
	 */
	$(document).click(function(event){
		//Did we click on a list item? How do we figure that out?
		//First let's see if we directly clicked on a .day-item
		var target = $(event.target);
		//Case where we've clicked on the list item directly
		if( target.hasClass('day-item') ) {
			if( target.hasClass('active') ) {
				return;
			}
			else if( target.hasClass( 'post-insert-overlay' ) ) {
				return;
			}
			else {
				edit_flow_calendar_close_overlays();

				target.addClass('active')
					.find('.item-static')
					.removeClass('item-static')
					.addClass('item-overlay');

				return;
			}
		}

		//Case where we've clicked in the list item
		target = target.closest('.day-item');
		if( target.length ) {
			if( target.hasClass('day-item') ) {
				if( target.hasClass('active') ) {
					return;
				}
				else if( target.hasClass( 'post-insert-overlay' ) ) {
					return;
				}
				else {
					edit_flow_calendar_close_overlays();

					target.addClass('active')
						.find('.item-static')
						.removeClass('item-static')
						.addClass('item-overlay');

					return;
				}
			}
		}

		target = $(event.target).closest('#ui-datepicker-div');
		if( target.length )
			return;

		target = $(event.target).closest('.post-insert-dialog');
		if( target.length )
			return;

		edit_flow_calendar_close_overlays();
	});

	function edit_flow_calendar_close_overlays() {
		reset_editorial_metadata();
		$('.day-item.active').removeClass('active')
			.find('.item-overlay').removeClass('item-overlay')
			.addClass('item-static');

		$('.post-insert-overlay').remove();
	}
	
	/**
	 * Instantiates drag and drop sorting for posts on the calendar
	 */
	let snackbarMessageTimeout = null;
	$('td.day-unit ul').sortable({
		items: 'li.day-item.sortable',
		connectWith: 'td.day-unit ul',
		placeholder: 'ui-state-highlight',
		start: function(event, ui) {
			$(this).disableSelection();
			edit_flow_calendar_close_overlays();
			$('td.day-unit ul li').unbind('click.ef-calendar-show-overlay');
			$(this).css('cursor','move');
		},
		sort: function(event, ui) {
			$('td.day-unit').removeClass('ui-wrapper-highlight');
			$('.ui-state-highlight').closest('td.day-unit').addClass('ui-wrapper-highlight');
		},
		stop: function(event, ui) {
			$(this).css('cursor','auto');
			$('td.day-unit').removeClass('ui-wrapper-highlight');
			// Only do a POST request if we moved the post off today
			if ( $(this).closest('.day-unit').attr('id') != $(ui.item).closest('.day-unit').attr('id') ) {
				var post_id = $(ui.item).attr('id').split('-');
				post_id = post_id[post_id.length - 1];
				var prev_date = $(this).closest('.day-unit').attr('id');
				var next_date = $(ui.item).closest('.day-unit').attr('id');
				var nonce = $(document).find('#ef-calendar-modify').val();
				$('.edit-flow-message').remove();
				dispatch( 'edit-flow/calendar' ).setCalendarIsLoading( true );
				// $('li.ajax-actions .waiting').show();
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
						if ( snackbarMessageTimeout ) {
							clearTimeout( snackbarMessageTimeout );
						}

						dispatch( 'edit-flow/calendar' ).setPostSaved( response.message );

						snackbarMessageTimeout = setTimeout( () => {
							dispatch( 'edit-flow/calendar' ).clearCalendarSnackbarMessage();
						}, 2500 );
						
						setTimeout( edit_flow_calendar_hide_message, 10000 );
					}
				);
			}
			$(this).enableSelection();
		}
	});

	// Enables quick creation/edit of drafts on a particular date from the calendar
	var EFQuickPublish = {
		/**
		 * When user clicks the '+' on an individual calendar date or
		 * double clicks on a calendar square pop up a form that allows
		 * them to create a post for that date
		 */
		init : function(){

			var $day_units = $('td.day-unit');

			// Bind the form display to the '+' button
			// or to a double click on the calendar square
			$day_units.find('.schedule-new-post-button').on('click.editFlow.quickPublish', EFQuickPublish.open_quickpost_dialogue );
			$day_units.on('dblclick.editFlow.quickPublish', EFQuickPublish.open_quickpost_dialogue );
			$day_units.hover(
				function(){ $(this).find('.schedule-new-post-button').stop().delay(500).fadeIn(100);},
				function(){ $(this).find('.schedule-new-post-button').stop().hide();}
			);
		}, // init

		/**
		 * Callback for click and double click events that open the
		 * quickpost dialogue
		 * @param  Event e The user interaction event
		 */
		open_quickpost_dialogue : function(e){

			e.preventDefault();

			// Close other overlays
			edit_flow_calendar_close_overlays();

			$this = $(this);

			// Get the current calendar square
			if( $this.is('td.day-unit') )
				EFQuickPublish.$current_date_square = $this;
			else if( $this.is('.schedule-new-post-button') )
				EFQuickPublish.$current_date_square = $this.parent();

			//Get our form content
			var $new_post_form_content = EFQuickPublish.$current_date_square.find('.post-insert-dialog');

			//Inject the form (it will automatically be removed on click-away because of its 'item-overlay' class)
			EFQuickPublish.$new_post_form = $new_post_form_content.clone().addClass('item-overlay post-insert-overlay').appendTo(EFQuickPublish.$current_date_square);
			
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

		},

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
						nonce: $(document).find('#ef-calendar-modify').val()
					},
					success: function( response, textStatus, XMLHttpRequest ) {

						if( response.status == 'success' ){

							//The response message on success is the html for the a post list item
							var $new_post = $(response.message);

							if( redirect_to_draft ) {
								//If user clicked on the 'edit post' link, let's send them to the new post
								var edit_url =  $new_post.find('.item-actions .edit a').attr('href');
								window.location = edit_url;
							} else {
								// Otherwise, inject the new post and bind the appropriate click event
								$new_post.appendTo( EFQuickPublish.$current_date_square.find('ul.post-list') );
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

	};

	if( ef_calendar_params.can_add_posts === 'true' )
		EFQuickPublish.init();
	
});

