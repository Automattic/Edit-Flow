jQuery( document ).ready( function() {

	var $ = window.jQuery;
	var ef = window.__admin_edit_flow.variables;
	var i18n = window.__admin_edit_flow.i18n;

	$( 'label[for=post_status]' ).show();
	$( '#post-status-display' ).show();

	if ( $( 'select[name="_status"]' ).length == 0 ) { // not on quick edit

		if ( ef.current_user_can_publish_posts ||
			( ef.current_status == 'publish' && ef.current_user_can_edit_published_posts ) ) {
			// show publish button if allowed to publish
			$( '#publish' ).show();
		} else {
			// mimic default post status dropdown
			$( '<span>&nbsp;<a href="#post_status" class="edit-post-status" tabindex=\'4\'>Edit</a></span>' +
				' <div id="post-status-select">' +
				' <input type="hidden" name="hidden_post_status" id="hidden_post_status" value="in-progress" />' +
				' <select name=\'post_status\' id=\'post_status\' tabindex=\'4\'>' +
				' </select>' +
				'  <a href="#post_status" class="save-post-status button">OK</a>' +
				'  <a href="#post_status" class="cancel-post-status">Cancel</a>' +
				' </div>' ).insertAfter( '#post-status-display' );

			if ( ! ef.status_dropdown_visible ) {
				$( '#post-status-select' ).hide();
				$( '.edit-post-status' ).show();
			}

			$( '.edit-post-status' ).click( function() {
				$( '#post-status-select' ).slideDown();
				$( '.edit-post-status' ).hide();
				return false;
			} );
			$( '.cancel-post-status, .save-post-status' ).click( function() {
				$( '#post-status-select' ).slideUp();
				$( '.edit-post-status' ).show();
				return false;
			} );
			$( '.save-post-status' ).click( function() {
				$( '#post-status-display' ).
					text( $( 'select[name="post_status"] :selected' ).text() );
				return false;
			} );
		}
	}

	// 1. Add custom statuses to post.php Status dropdown
	// Or 2. Add custom statuses to quick-edit status dropdowns on edit.php
	// Or 3. Hide two inputs with the default workflow status to override 'Draft' as the default contributor status
	if ( $( 'select[name="post_status"]' ).length > 0 ) {

		// Set the Save button to generic text by default
		ef_update_save_button( i18n.save );

		// Bind event when OK button is clicked
		$( '.save-post-status' ).bind( 'click', function() {
			ef_update_save_button();
		} );

		// Add custom statuses to Status dropdown
		ef_append_to_dropdown( 'select[name="post_status"]' );

		// Make status dropdown visible on load if enabled
		if ( ef.status_dropdown_visible ) {
			$( '#post-status-select' ).show();
			$( '.edit-post-status' ).hide();
		}

		// Hide status dropdown if not allowed to edit
		if ( !ef_can_change_status( ef.current_status ) ) {
			$( '#post-status-select' ).hide();
			$( '.edit-post-status' ).hide();

			// set the current status as the selected one
			var $option = $( '<option></option>' ).
				text( ef.current_status_name ).
				attr( 'value', ef.current_status ).
				attr( 'selected', 'selected' );

			$option.appendTo( 'select[name="post_status"]' );
		}

		// If custom status set for post, then set is as #post-status-display
		$( '#post-status-display' ).text( ef_get_status_name( ef.current_status ) );

	} else if ( $( 'select[name="_status"]' ).length > 0 ) {
		ef_append_to_dropdown( 'select[name="_status"]' );
		// Refresh the custom status dropdowns everytime Quick Edit is loaded
		$( '#the-list a.editinline' ).bind( 'click', function() {
			ef_append_to_dropdown( '#the-list select[name="_status"]' );
		} );
		// Clean up the bulk edit selector because it's non-standard
		$( '#bulk-edit' ).
			find( 'select[name="_status"]' ).
			prepend( '<option value="">' + i18n.no_change + '</option>' );
		$( '#bulk-edit' ).find( 'select[name="_status"] option' ).removeAttr( 'selected' );
		$( '#bulk-edit' ).find( 'select[name="_status"] option[value="future"]' ).remove();
	} else {

		// Set the Save button to generic text by default
		ef_update_save_button( i18n.save );

		// If custom status set for post, then set is as #post-status-display
		$( '#post-status-display' ).text( ef_get_status_name( ef.current_status ) );

	}

	if ( $( 'ul.subsubsub' ) ) {
		ef_add_tooltips_to_filter_links( 'ul.subsubsub li a' );
	}

	// Add custom statuses to Status dropdown
	function ef_append_to_dropdown( id ) {

		// Empty dropdown except for 'future' because we need to persist that
		$( id + ' option' ).not( '[value="future"]' ).remove();

		// Add "Published" status to quick-edit for users that can publish
		if ( id == 'select[name="_status"]' && ef.current_user_can_publish_posts ) {
			$( id ).append( $( '<option></option' ).attr( 'value', 'publish' ).text( i18n.published )
			);
		}

		// Add remaining statuses to dropdown. 'private' is always handled by a checkbox, and 'future' already exists if we need it
		$.each( ef.custom_statuses, function() {
			if ( this.slug == 'private' || this.slug == 'future' )
				return;

			if ( ef.current_status != 'publish' && this.slug == 'publish' )
				return;

			var $option = $( '<option></option>' ).
				text( this.name ).
				attr( 'value', this.slug ).
				attr( 'title', ( this.description ) ? this.description : '' )
			;

			if ( ef.current_status == this.slug ) $option.attr( 'selected', 'selected' );

			$option.appendTo( $( id ) );
		} );
	}

	function ef_can_change_status( slug ) {
		var change = false;

		$.each( ef.custom_statuses, function() {
			if ( this.slug == slug ) change = true;
		} );
		if ( slug == 'publish' && ! ef.current_user_can_publish_posts ) {
			change = false;
		}
		return change;
	}

	function ef_add_tooltips_to_filter_links( selector ) {
		$.each( ef.custom_statuses, function() {
			$( selector + ':contains("' + this.name + '")' ).attr( 'title', this.description );
		} );

	}

	// Update "Save" button text
	function ef_update_save_button( text ) {
		if ( !text ) text = i18n.save_as + ' ' + $( 'select[name="post_status"] :selected' ).text();
		$( ':input#save-post' ).attr( 'value', text );
	}

	// Returns the name of the status given a slug
	function ef_get_status_name( slug ) {
		var name = '';
		$.each( ef.custom_statuses, function() {
			if ( this.slug == slug ) name = this.name;
		} );

		if ( !name ) {
			name = ef.current_status_name;
		}

		return name;
	}

	// If we're on the Manage Posts screen, remove the trailing dashes left behind once we hide the post-state span (the status).
	// We do this since we already add a custom column for post status on the screen since custom statuses are a core part of EF.
	if ( $( '.post-state' ).length > 0 ) {
		ef_remove_post_title_trailing_dashes();
	}

	// Remove the " - " in between a post title and the post-state span (separately hidden via CSS).
	// This will not affect the dash before post-state-format spans.
	function ef_remove_post_title_trailing_dashes() {
		$( '.post-title.column-title strong' ).each( function() {
			$( this ).
				html( $( this ).
					html().
					replace( /(.*) - (<span class="post-state".*<\/span>)$/g, '$1$2' ) );
		} );
	}

} );