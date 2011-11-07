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

// Upgrade to 0.3
function edit_flow_upgrade_03 () {
	global $wp_roles, $edit_flow;

	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	// Add necessary capabilities to allow management of usergroups and post subscriptions
	// edit_post_subscriptions - administrator + editor
	// edit_usergroups - adminstrator
	if( $wp_roles->is_role('administrator') ) {
		$admin_role =& get_role('administrator');
		$admin_role->add_cap('edit_post_subscriptions');
		$admin_role->add_cap('edit_usergroups');
	}
	if( $wp_roles->is_role('editor') ) {	
		$editor_role =& get_role('editor');
		$editor_role->add_cap('edit_post_subscriptions');
	}
	
	$default_usergroups = array( 
		array( 'slug' => 'ef_copy-editors', 'args' => array( 'name' => __( 'Copy Editors', 'edit-flow' ), 'description' => __( 'The ones who correct stuff.', 'edit-flow' ), 'users' => '' ) ),
		array( 'slug' => 'ef_photographers', 'args' => array( 'name' => __( 'Photographers', 'edit-flow' ), 'description' => __( 'The ones who take pretty pictures.', 'edit-flow' ), 'users' => '' ) ),
		
		array( 'slug' => 'ef_reporters', 'args' => array( 'name' => __( 'Reporters', 'edit-flow' ), 'description' => __( 'The ones who write stuff.', 'edit-flow' ), 'users' => '' ) ),
		array( 'slug' => 'ef_section-editors', 'args' => array( 'name' => __( 'Section Editors', 'edit-flow' ), 'description' => __( 'The ones who tell others what to do and generally just boss them around.', 'edit-flow' ), 'users' => '' ) ),
		array( 'slug' => 'ef_web-team', 'args' => array( 'name' => __( 'Web Team', 'edit-flow' ), 'description' => __( 'The ones you call when your computer starts doing that weird thing.', 'edit-flow' ), 'users' => '' ) ),
		array( 'slug' => 'ef_sales-team', 'args' => array( 'name' => __( 'Sales Team', 'edit-flow' ), 'description' => __( 'Yeah, they technically pay our salaries. But we still don\'t like them.', 'edit-flow' ), 'users' => '' ) ),
	);
	
	// Okay, now add the default statuses to the db if they don't already exist 
	foreach($default_usergroups as $usergroup) {
		if( !term_exists( $usergroup['slug'], $edit_flow->notifications->following_usergroups_taxonomy ) ) {
			ef_add_usergroup( $usergroup['slug'], $usergroup['args'] );
		}
	}
}

function edit_flow_upgrade_06() {
	global $wpdb, $edit_flow, $wp_roles;
	
	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();
	
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
	
	if( $wp_roles->is_role('author') ) {	
		$author_role =& get_role('author');
		$author_role->add_cap('edit_post_subscriptions');
	}
	
	// @todo Remove all of the prior calendar state save data (being stored in user meta now)
	// ..options: 'custom_status_filter', 'custom_category_filter', 'custom_author_filter'
}
