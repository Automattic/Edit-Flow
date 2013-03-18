jQuery(document).ready(function () {
	
	jQuery('a.show-more').click(function(){
		var parent = jQuery(this).closest('td.day-unit');
		jQuery('ul li', parent).removeClass('hidden');
		jQuery(this).hide();
		return false;
	});

	//Necessary for keeping track of what's currently
	//been seleced
	var metadata_term;
	var current_text;
	var prev_input_type;
	var post_selector;

	//.day-unit does not get replaced, so good anchor
	jQuery('.day-unit').on('click', '.editable-value', function( event ) {		
		jQuery(this).removeClass('editable-value');
		//Input types allowed for editorial metadata
		//Is it possible to add more select "types" with a filter?
		//Add these in with php and tack on extra?
		var input_types = ['date', 'location', 'text', 'number', 'paragraph', 'checkbox', 'user', 'author', 'taxonomy'];
		var tr_classes = jQuery(event.target).closest('tr.item-field').attr('class').split(' ');
		post_selector = '#'+jQuery(event.target).closest('.day-item').attr('id');
		
		//Always gotta be traversin that dom for inserted elements
		for(class_selector in tr_classes) {
			if(tr_classes[class_selector] !== 'item-field') {
				metadata_term = tr_classes[class_selector];
			}
		}

		//Retrieve the correct selector for what we're looking to replace
		top_level_selector = post_selector + ' .item-overlay .item-inner' + ' .' + metadata_term;
		th_label = jQuery(jQuery(top_level_selector).children('th.label')[0]);
		metadata_value_element = jQuery(this);

		var metadata_value_classes = metadata_value_element.attr('class').split(' ');

		//See if we should be changing the static value to an input
		for(link_class in metadata_value_classes) {
			for(input in input_types) {
				if(metadata_value_classes[link_class] === input_types[input]) {
					input_type = metadata_value_classes[link_class];
					subsitute_input(metadata_value_classes[link_class], metadata_value_element, top_level_selector);
				}
			}	
		}

		jQuery('.save-metadata-hide').show();
		return false;
	});

	//Save the editorial metadata we've changed
	jQuery('.day-unit').on('click', 'a#save-editorial-metadata', function() {
		var post_id = jQuery(this).attr('class').replace('post-', '');
		save_editorial_metadata(post_id);
		return false;
	});

	/**
	 * save_editorial_metadata
	 * Save the editorial metadata that's been edited (whatever is marked '#actively-editing').
	 * @param post_id Id of post we're editing
	 */
	function save_editorial_metadata(post_id) {
		var metadata_info = {}
		//Get active input type
		switch(prev_input_type) {
			case 'text':
			case 'location':
			case 'number':
			case 'paragraph':
				metadata_info.attr_type = prev_input_type;
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				metadata_info.metadata_term = metadata_term.replace('item-information-editorial-metadata-', '');
			break;
			case 'date':
				metadata_info.attr_type = 'date';
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				metadata_info.metadata_term = metadata_term.replace('item-information-editorial-metadata-', '');
			break;
			case 'checkbox':
				metadata_info.attr_type = 'checkbox';
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				if(metadata_info.metadata_value === 'No')
					metadata_info.metadata_value = 0;
				else
					metadata_info.metadata_value = 1;
				metadata_info.metadata_term = metadata_term.replace('item-information-editorial-metadata-', '');
			break;
			case 'user':
				metadata_info.attr_type = 'user';
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				metadata_info.metadata_term = metadata_term.replace('item-information-editorial-metadata-', '');
				jQuery('#tax_user_dropdown_lists').append(jQuery('.ef_calendar_user_dropdown').attr('id', ''));
			break;
			case 'author':
				metadata_info.attr_type = 'author';
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				metadata_info.metadata_term = 'author';
				jQuery('#tax_user_dropdown_lists').append(jQuery('.ef_calendar_user_dropdown').attr('id', ''));
			break;
			case 'taxonomy':
				//Don't care about taxonomy type at this point. Val will either be a comma separated list of
				//terms, or term ids. This is good because term ids are needed when hierarchical and list of 
				//terms needed when not
				metadata_info.attr_type = 'taxonomy';
				metadata_info.metadata_value = jQuery('#actively-editing').val();
				metadata_info.metadata_term = metadata_term.replace('item-information-tax_', '');
				jQuery('#tax_user_dropdown_lists').append(jQuery('.tax_dropdown-'+metadata_info.metadata_term).attr('id', ''));
			break;
		}

		//Save before ajax so there's no delay
		jQuery('.save-metadata-hide').hide();

		//Setup information used in saving
		metadata_info.action = 'editflow_ajax_update_metadata';
		metadata_info.nonce = jQuery("#ef-calendar-modify").val();
		metadata_info.post_id = post_id;

		metadata_value_element.html(jQuery('<div class="spinner spinner-calendar"></div>').show());

		// Send the request
		jQuery.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : metadata_info,
			success : function(x) { replace_inner_information(x); },
			error : function(r) { jQuery(post_selector + ' .'+metadata_term).html('Error saving metadata.'); }
		});
		
	}

	/**
	 * subsitute_input
	 * Change the static text value back to it's corresponding input/select/textarea type.
	 * @param  string type
	 * @param  jQuery metadata_value_element
	 * @param  string top_level_selector
	 */
	function subsitute_input(type, metadata_value_element, top_level_selector) {
		var current_value;

		//If the user hasn't clicked on any metadata to edit, kill this function
		if(current_text !== undefined)
			reset_to_normal_html(current_text)

		//Figure out what we need to switch the text to (input/select/textarea) and switch it
		switch(type) {
			case 'text':
			case 'number':
			case 'location':
				current_text = current_value = metadata_value_element.text();
				jQuery(top_level_selector + ' td.'+ type).html('<input type="text" id="actively-editing" name="ef-alter-text" value="' + current_value + '" class="metadata-edit-'+ type+ '"/>');
			break;
			case 'paragraph':
				current_text = current_value = metadata_value_element.text();
				jQuery(top_level_selector + ' td.'+ type).html('<textarea type="text" id="actively-editing" name="ef-alter-text" class="metadata-edit-'+ type+ '">'+current_value+'</textarea>');
			break;
			case 'date':
				current_text = current_value = metadata_value_element.text();
				jQuery(top_level_selector + ' td.'+ type).html('<input type="text" id="actively-editing" name="ef-alter-text" value="' + current_value + '" class="metadata-edit-' + type + ' date-pick"/>');
				//Always be traversin the DOM and rebinding necessary functionality
				jQuery(top_level_selector + ' td.'+ type + ' #actively-editing').datetimepicker({dateFormat: 'M dd yy', firstDay: ef_week_first_day,});
			break;
			case 'checkbox':
				current_text = current_value = metadata_value_element.text();
				if(current_value === 'No')
					current_value = '<option>No</option><option>Yes</option>';
				else
					current_value = '<option>Yes</option><option>No</option>';

				jQuery(top_level_selector + ' td.'+type).html('<select id="actively-editing" name="ef-alter-text" class="metadata-edit">' + current_value + '</select>');
			break;
			case 'user':
			case 'author':
				var selected_index;

				current_text = current_value = metadata_value_element.text();
				jQuery('.ef_calendar_user_dropdown option').each(function(i) {
					if(current_text == jQuery(this).text()) {
						selected_index = jQuery(this).val();
					}
				});

				jQuery('.ef_calendar_user_dropdown').val(selected_index);
				jQuery('.ef_calendar_user_dropdown').attr('id', 'actively-editing');
				jQuery(top_level_selector + ' td.'+type).html(jQuery('.ef_calendar_user_dropdown'));
			break;
			case 'taxonomy':
				var taxonomy = {};
				var array_of_terms = selected_indexes = new Array();
				var tax_name;
				var tax_selector;

				//Determine if we have a hierarchical taxonomy or a regular
				//one. Need to know if a list is being subsituted or not
				if(metadata_value_element.hasClass('hierarchical')) {
					current_text = current_value = metadata_value_element.text();
					tax_name = metadata_term.replace('item-information-tax_', '');
					tax_selector = '.tax_dropdown-'+tax_name;
					array_of_terms = current_text.split(',');

					for(term in array_of_terms)
						array_of_terms[term] = array_of_terms[term].trim();

					//Determine what taxonomies are selected
					jQuery(tax_selector+' option').each(function(i) {
						for(term_index in array_of_terms) {
							if(array_of_terms[term_index] == jQuery(this).text()) {
								selected_indexes.push(jQuery(this).val());
							}
						}
					});

					jQuery(tax_selector).val(selected_indexes);
					jQuery(tax_selector).attr('id', 'actively-editing');
					jQuery(top_level_selector + ' td.'+type).html(jQuery(tax_selector));
				} else {
					current_text = current_value = metadata_value_element.text();
					jQuery(top_level_selector + ' td.'+ type).html('<input type="text" id="actively-editing" name="ef-alter-text" value="' + current_value + '" class="metadata-edit-'+ type+ '"/>');
				}
			break;
		}
		//Focus on the metadata
		//Some wonky stuff going on with double clicking, might want to make an exception for
		//double clicks when user is clicking on inputs.
		jQuery('#actively-editing').focus();
		prev_top_level_selector = top_level_selector
		normal_html = jQuery(top_level_selector + ' td').html();
		prev_input_type = type;
	}

	/**
	 * reset_to_normal_html
	 * Change the input/select/textarea back to normal static text
	 */
	function reset_to_normal_html() {
		//Always hide the save button
		jQuery('.save-metadata-hide').hide();

		//Haven't chosen a metadata? Kill this function.
		if(jQuery('#actively-editing').length === 0)
			return;

		jQuery('#actively-editing').closest('td').addClass('editable-value');

		var current_td = jQuery('#actively-editing').closest('td');

		if(jQuery('#actively-editing').hasClass('ef_user_tax_dropdown'))
			jQuery('#tax_user_dropdown_lists').append(jQuery('#actively-editing').attr('id', ''));
		else
			jQuery('#actively-editing').attr('class').replace('metadata-edit-', '');
		
		jQuery('#actively-editing').remove();
		current_td.html(current_text);
	}

	/**
	 * replace_inner_information
	 * Replace the overlay with the content received from the ajax call.
	 * 
	 * @param  string xml
	 */
	function replace_inner_information(content) {
		if(content.message === false)
			reset_to_normal_html();
		else
			jQuery(post_selector + ' .'+metadata_term).closest('.item-inner').html(content.message);
	}
	
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
		//Reset input fields
		reset_to_normal_html();

		jQuery('.item-overlay').remove();
		jQuery('td.day-unit ul li').removeClass('item-overlay-active');
	}
	
	/**
	 * Show the overlay for a post date
	 */
	function edit_flow_calendar_show_overlay( event ) {	
		//If we've clicked on the original overlay (not the top part!), don't close it
		if(jQuery(this).hasClass('item-overlay-active') && ( jQuery(event.target).hasClass('inner') ||  jQuery(event.target).hasClass('item-status') || jQuery(event.target).hasClass('item-overlay') || jQuery(event.target).hasClass('status-text')) ) {
			edit_flow_calendar_close_overlays();
		} 
		else if ( !jQuery(this).hasClass('item-overlay-active' ) ) {
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
		 * When user clicks the '+' on an individual calendar date or
		 * double clicks on a calendar square pop up a form that allows
		 * them to create a post for that date
		 */
		init : function(){

			var $day_units = jQuery('td.day-unit');

			// Bind the form display to the '+' button
			// or to a double click on the calendar square
			$day_units.find('.schedule-new-post-button').on('click.editFlow.quickPublish', EFQuickPublish.open_quickpost_dialogue );
			$day_units.on('dblclick.editFlow.quickPublish', EFQuickPublish.open_quickpost_dialogue );
			$day_units.hover(
				function(){ jQuery(this).find('.schedule-new-post-button').stop().delay(500).fadeIn(100);},
				function(){ jQuery(this).find('.schedule-new-post-button').stop().hide();}
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

			$this = jQuery(this);

			// Get the current calendar square
			if( $this.is('td.day-unit') )
				EFQuickPublish.$current_date_square = $this;
			else if( $this.is('.schedule-new-post-button') )
				EFQuickPublish.$current_date_square = $this.parent();

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

	};

	if( ef_calendar_params.can_add_posts === 'true' )
		EFQuickPublish.init();
	
});

