<?php
// Handles all current and future upgrades for edit_flow
function edit_flow_upgrade( $from ) {
	global $edit_flow;
	if ( version_compare( $from, '0.3', '<' ) )
		edit_flow_upgrade_03();
	if ( version_compare( $from, '0.6', '<' ) )
		edit_flow_upgrade_06();
	update_option( $edit_flow->options_group . 'version', EDIT_FLOW_VERSION );
}

function edit_flow_upgrade_06() {
	global $wpdb, $edit_flow;
	
	// Editorial comments should have comment_approved set a given key instead of just 1 
	$wpdb->update( $wpdb->comments, array( 'comment_approved' => 'editorial-comment' ), array( 'comment_type' => 'editorial-comment' ), array( '%s' ), array( '%s' ) );
	
	// Upgrade old metadata to new metadata
	$metadata_fields = array( 'duedate', 'location', 'description' );
	foreach( $metadata_fields as $metadata_field ) {
		
		$old_meta_key = '_ef_' . $metadata_field;
		$new_meta_term = $edit_flow->editorial_metadata->get_editorial_metadata_term( $metadata_field );
		$new_meta_key = $edit_flow->editorial_metadata->get_postmeta_key( $new_meta_term );
		
		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => $new_meta_key ), array( 'meta_key' => $old_meta_key ), array( '%s' ), array( '%s' ) );
	}
	
	// Delete old _ef_workflow metas since they're just unused and clogging the database
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_ef_workflow'" );
	
	// @todo Remove all of the prior calendar state save data (being stored in user meta now)
	// ..options: 'custom_status_filter', 'custom_category_filter', 'custom_author_filter'
}
