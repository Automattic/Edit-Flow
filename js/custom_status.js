jQuery(document).ready(function() {

	jQuery('label[for=post_status]').show();
	jQuery('#post-status-display').show();

	// Add custom statuses to post.php Status dropdown
	if(jQuery('select[name="post_status"]')) {
		
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

	}
	
	// Add custom statuses to quick-edit status dropdowns on edit.php
	if ( jQuery('select[name="_status"]') ) {
		ef_append_to_dropdown('select[name="_status"]');
		ef_apply_post_state_to_titles();
		jQuery( '#bulk-edit.inline-edit-row' ).find( 'select[name="_status"]' ).prepend( '<option value="">' + ef_text_no_change + '</option>' );
		jQuery( '#bulk-edit.inline-edit-row' ).find( 'select[name="_status"] option' ).removeAttr('selected');
	}
		
	if (jQuery('ul.subsubsub')) {
		ef_add_tooltips_to_filter_links('ul.subsubsub li a');
	}
	
	// Add custom statuses to Status dropdown
	function ef_append_to_dropdown(id) {
	
		// Empty dropdown
		jQuery(id).empty();

		// Add "Published" status to quick-edit for users that can publish
		if ( id=='select[name="_status"]' && current_user_can_publish_posts ) {
			jQuery(id).append(jQuery('<option></option')
				.attr('value','publish')
				.text('Published')
			);
		}
		
		// Add remaining statuses to dropdown
		jQuery.each(custom_statuses, function() {
			if ( this.slug == 'private' ) {
				return;
			}
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
	
	/**
	 * Add the post state to post titles on edit.php, mimicking the 'draft' and private that are already added
	 */
	function ef_apply_post_state_to_titles() {
		var status_blacklist = new Array( 'Published', 'Scheduled' );
		jQuery('#the-list tr').each( function() {
			var status = jQuery(this).find('td.status').html();
			if ( jQuery.inArray( status, status_blacklist ) == -1 && jQuery(this).find('.post-title strong .post-state').length == 0 )
				jQuery(this).find('.post-title strong').append( ' - <span class="post-state">' + status + '</span>' );
				
		});
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
	
});