jQuery( document ).ready( function () {
	const i18n = window.__ef_localize_custom_status;

	jQuery( 'label[for=post_status]' ).show();
	jQuery( '#post-status-display' ).show();

	// 1. Add custom statuses to post.php Status dropdown
	// Or 2. Add custom statuses to quick-edit status dropdowns on edit.php
	if ( jQuery( 'select[name="_status"]' ).length > 0 ) {
		ef_append_to_dropdown( 'select[name="_status"]' );
		// Clean up the bulk edit selector because it's non-standard
		jQuery( '#bulk-edit' )
			.find( 'select[name="_status"]' )
			.prepend( '<option value="">' + i18n.no_change + '</option>' );
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option' ).prop( 'selected', false );
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option[value="future"]' ).remove();
	}

	if ( jQuery( 'ul.subsubsub' ) ) {
		ef_add_tooltips_to_filter_links( 'ul.subsubsub li a' );
	}

	// Add custom statuses to Status dropdown
	function ef_append_to_dropdown( id ) {
		// Empty dropdown except for 'future' because we need to persist that
		jQuery( id + ' option' )
			.not( '[value="future"]' )
			.remove();

		// Add "Published" status to quick-edit for users that can publish
		if ( id == 'select[name="_status"]' && current_user_can_publish_posts ) {
			jQuery( id ).append(
				jQuery( '<option></option' ).attr( 'value', 'publish' ).text( i18n.published )
			);
		}

		// Add remaining statuses to dropdown. 'private' is always handled by a checkbox, and 'future' already exists if we need it
		jQuery.each( custom_statuses, function () {
			if ( this.slug == 'private' || this.slug == 'future' ) {
				return;
			}

			if ( current_status != 'publish' && this.slug == 'publish' ) {
				return;
			}

			const $option = jQuery( '<option></option>' )
				.text( this.name )
				.attr( 'value', this.slug )
				.attr( 'title', this.description ? this.description : '' );
			if ( current_status == this.slug ) {
				$option.attr( 'selected', 'selected' );
			}

			$option.appendTo( jQuery( id ) );
		} );
	}

	function ef_add_tooltips_to_filter_links( selector ) {
		jQuery.each( custom_statuses, function () {
			jQuery( selector + ':contains("' + this.name + '")' ).attr( 'title', this.description );
		} );
	}

	// If we're on the Manage Posts screen, remove the trailing dashes left behind once we hide the post-state span (the status).
	// We do this since we already add a custom column for post status on the screen since custom statuses are a core part of EF.
	if ( jQuery( '.post-state' ).length > 0 ) {
		ef_remove_post_title_trailing_dashes();
	}

	// Remove the " - " in between a post title and the post-state span (separately hidden via CSS).
	// This will not affect the dash before post-state-format spans.
	function ef_remove_post_title_trailing_dashes() {
		jQuery( '.post-title.column-title strong' ).each( function () {
			jQuery( this ).html(
				jQuery( this )
					.html()
					.replace( /(.*) - (<span class="post-state".*<\/span>)$/g, '$1$2' )
			);
		} );
	}
} );
