jQuery(document).ready(function() {

	jQuery('label[for=post_status]').show();
	jQuery('#post-status-display').show();

	// 1. Add custom statuses to post.php Status dropdown
	// Or 2. Add custom statuses to quick-edit status dropdowns on edit.php
	// Or 3. Hide two inputs with the default workflow status to override 'Draft' as the default contributor status
	if ( jQuery('select[name="post_status"]').length > 0 ) {
		
		// Set the Save button to generic text by default
		ef_update_save_button('Save');
		
		// Bind event when OK button is clicked
		jQuery('.save-post-status').bind('click', function() {	
			ef_update_save_button();
		});
		
		// Add custom statuses to Status dropdown
		ef_append_to_dropdown('select[name="post_status"]');
		
		// Make status dropdown visible on load if enabled
		if ( status_dropdown_visible ) {
			jQuery('#post-status-select').show();
			jQuery('.edit-post-status').hide();
		}
		
		// If custom status set for post, then set is as #post-status-display
		jQuery('#post-status-display').text(ef_get_status_name(current_status));

	} else if ( jQuery('select[name="_status"]').length > 0 ) {
		ef_append_to_dropdown('select[name="_status"]');
		// Refresh the custom status dropdowns everytime Quick Edit is loaded
		jQuery('#the-list a.editinline').bind( 'click', function() {
			ef_append_to_dropdown('#the-list select[name="_status"]');
		} );
		// Clean up the bulk edit selector because it's non-standard
		jQuery( '#bulk-edit' ).find( 'select[name="_status"]' ).prepend( '<option value="">' + ef_text_no_change + '</option>' );
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option' ).removeAttr('selected');
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option[value="future"]').remove();
	} else {
		if ( !jQuery('input[name="hidden_post_status"]').length ) {
			jQuery('.misc-pub-section').append(jQuery('<input>')
				.attr('type', 'hidden')
				.attr('id', 'hidden_post_status')
				.attr('name', 'hidden_post_status')
				.attr('value', ef_default_custom_status));
			jQuery('.misc-pub-section').append(jQuery('<input>')
				.attr('type', 'hidden')
				.attr('id', 'post_status')
				.attr('name', 'post_status')
				.attr('value', ef_default_custom_status ));
		}
	}
		
	if (jQuery('ul.subsubsub')) {
		ef_add_tooltips_to_filter_links('ul.subsubsub li a');
	}
	
	// Add custom statuses to Status dropdown
	function ef_append_to_dropdown( id ) {
	
		// Empty dropdown except for 'future' because we need to persist that
		jQuery(id + ' option').not('[value="future"]').remove();
		
		// Add "Published" status to quick-edit for users that can publish
		if ( id=='select[name="_status"]' && current_user_can_publish_posts ) {
			jQuery(id).append(jQuery('<option></option')
				.attr('value','publish')
				.text('Published')
			);
		}
		
		// Add remaining statuses to dropdown. 'private' is always handled by a checkbox, and 'future' already exists if we need it
		jQuery.each( custom_statuses, function() {
			if ( this.slug == 'private' || this.slug == 'future' )
				return;
			
			if ( current_status != 'publish' && this.slug == 'publish' )
				return;
				
			var $option = jQuery('<option></option>')
							.text(this.name)
							.attr('value', this.slug)
							.attr('title', (this.description) ? this.description : '')
							;
							
			if( current_status == this.slug ) $option.attr('selected','selected');
			
			$option.appendTo( jQuery(id) );
		});
	}
	
	function ef_add_tooltips_to_filter_links(selector) {	
		jQuery.each(custom_statuses, function() {
			jQuery(selector + ':contains("'+ this.name +'")')
				.attr('title', this.description)
		})
		
	}
	
	// Update "Save" button text
	function ef_update_save_button( text ) {
		if(!text) text = 'Save as ' + jQuery('select[name="post_status"] :selected').text();
		jQuery(':input#save-post').attr('value', text);
	}
	
	// Returns the name of the status given a slug
	function ef_get_status_name (slug) {
		var name = '';
		jQuery.each(custom_statuses, function() {
			if(this.slug==slug) name = this.name;
		});
		return name;
	}

	// If we're on the Manage Posts screen, remove the trailing dashes left behind once we hide the post-state span (the status).
	// We do this since we already add a custom column for post status on the screen since custom statuses are a core part of EF.
	if ( jQuery('.post-state').length > 0 ) {
		ef_remove_post_title_trailing_dashes();
	}

	// Remove the " - " in between a post title and the post-state span (separately hidden via CSS).
	// This will not affect the dash before post-state-format spans.
	function ef_remove_post_title_trailing_dashes() {
		jQuery('.post-title.column-title strong').each(function() {
			jQuery(this).html(jQuery(this).html().replace(/(.*) - (<span class="post-state".*<\/span>)$/g, "$1$2"));
		});
	}
	
});