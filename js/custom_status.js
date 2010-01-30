jQuery(document).ready(function () {

	// Add custom statuses to post.php Status dropdown
	if(jQuery('select[name="post_status"]')) {
		
		// Set the Save button to generic text by default
		updateSaveButton('Save');
		
		// Bind event when OK button is clicked
		jQuery('.save-post-status').bind('click', function() {	
			updateSaveButton();
		});
		
		// Add custom statuses to Status dropdown
		append_to_dropdown('select[name="post_status"]');
		
		// Make status dropdown visible on load if enabled
		if(status_dropdown_visible) {
			jQuery('#post-status-select').show();
			jQuery('.edit-post-status').hide();
		}
		
		// If custom status set for post, then set is as #post-status-display
		jQuery('#post-status-display').text(get_status_name(current_status));

	}
	
	// Add custom statuses to quick-edit status dropdowns on edit.php
	if(jQuery('select[name="_status"]')) {
		append_to_dropdown('select[name="_status"]');
	}
	
	if( jQuery('ul.subsubsub') ) {
		add_tooltips_to_filter_links('ul.subsubsub a');
	}
	
	// Add custom statuses to Status dropdown
	function append_to_dropdown ( id ) {
	
		// Empty dropdown
		jQuery(id).empty();

		// Add "Published" status to quick-edit
		if(id=='select[name="_status"]') {
			jQuery(id).append(jQuery('<option></option')
				.attr('value','publish')
				.text('Published')
			)
		}

		// Add remaining statuses to dropdown
		jQuery.each(custom_statuses, function() {
			var $option = jQuery('<option></option>')
							.text(this.name)
							.attr('value', this.slug)
							.attr('title', (this.description) ? this.description : '')
							;
							
			if( current_status == this.slug ) $option.attr('selected','selected');
			
			$option.appendTo( jQuery(id) );
		});
	}
	
	function add_tooltips_to_filter_links( selector ) {
		jQuery.each(custom_statuses, function() {
			jQuery(selector + ':contains("'+ this.name +'")')
				.attr('title', this.description)
		})
		
	}
	
	// Update "Save" button text
	function updateSaveButton( text ) {
		if(!text) text = 'Save as ' + jQuery('select[name="post_status"] :selected').text();
		jQuery(':input#save-post').attr('value', text);
	}
	
	// Returns the name of the status given a slug
	function get_status_name ( slug ) {
		var name = '';
		jQuery.each(custom_statuses, function() {
			if(this.slug==slug) name = this.name;
		});
		return name;
						
	}
	
});