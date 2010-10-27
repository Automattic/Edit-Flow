<?php
// Handles all current and future upgrades for edit_flow
function edit_flow_upgrade( $from ) {
	if ( !$from || version_compare( $from, '0.1', '<' ) ) edit_flow_upgrade_01();
	if ( version_compare( $from, '0.3', '<' ) edit_flow_upgrade_03();
	if ( version_compare( $from, '0.5.1', '<' ) ) edit_flow_upgrade_051();
	if ( version_compare( $from, '0.6', '<' ) ) edit_flow_upgrade_06();
}

// Upgrade to 0.1
function edit_flow_upgrade_01() {
	global $edit_flow;
	
	// Create default statuses
	$default_terms = array( 
		array( 'term' => 'Draft', 'args' => array( 'slug' => 'draft', 'description' => 'Post is simply a draft', ) ),
		array( 'term' => 'Pending Review', 'args' => array( 'slug' => 'pending', 'description' => 'The post needs to be reviewed by an Editor', ) ),
		array( 'term' => 'Pitch', 'args' => array( 'slug' => 'pitch', 'description' => 'Post idea proposed', ) ),
		array( 'term' => 'Assigned', 'args' => array( 'slug' => 'assigned', 'description' => 'The post has been assigned to a writer' ) ),
		array( 'term' => 'Waiting for Feedback', 'args' => array( 'slug' => 'waiting-for-feedback', 'description' => 'The post has been sent to the editor, and is waiting on feedback' ) ) 
	);
	
	// Okay, now add the default statuses to the db if they don't already exist 
	foreach($default_terms as $term) {
		if( !ef_term_exists( $term['term'] ) ) $edit_flow->custom_status->add_custom_status( $term['term'], $term['args'] );
	}
	
	$edit_flow->update_plugin_option( 'version', '0.1' );
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
		array( 'slug' => 'ef_copy-editors', 'args' => array( 'name' => 'Copy Editors', 'description' => 'The ones who correct stuff.' ) ),
		array( 'slug' => 'ef_photographers', 'args' => array( 'name' => 'Photographers', 'description' => 'The ones who take pretty pictures.' ) ),
		
		array( 'slug' => 'ef_reporters', 'args' => array( 'name' => 'Reporters', 'description' => 'The ones who write stuff.' ) ),
		array( 'slug' => 'ef_section-editors', 'args' => array( 'name' => 'Section Editors', 'description' => 'The ones who tell others what to do and generally just boss them around.' ) ),
		array( 'slug' => 'ef_web-team', 'args' => array( 'name' => 'Web Team', 'description' => 'The ones you call when your computer starts doing that weird thing.' ) ),
		array( 'slug' => 'ef_sales-team', 'args' => array( 'name' => 'Sales Team', 'description' => 'Yeah, they technically pay our salaries. But we still don\'t like them.' ) ),
	);
	
	// Okay, now add the default statuses to the db if they don't already exist 
	foreach($default_usergroups as $usergroup) {
		if( !ef_term_exists( $usergroup['slug'], $edit_flow->notifications->following_usergroups_taxonomy ) ) {
			ef_add_usergroup( $usergroup['slug'], $usergroup['args'] );
		}
	}
	$edit_flow->update_plugin_option( 'version', '0.3' );
}

function edit_flow_upgrade_051() {
	global $wp_roles, $edit_flow;

	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	// Add necessary capabilities to allow management of calendar
	// view_calendar - administrator --> contributor
	$calendar_roles = array( 'administrator' => array('ef_view_calendar'),
	                         'editor' =>        array('ef_view_calendar'),
	                         'author' =>        array('ef_view_calendar'),
	                         'contributor' =>   array('ef_view_calendar') );
	
	foreach ($calendar_roles as $role => $caps) {
		ef_add_caps_to_role( $role, $caps );
	}
	
	$edit_flow->update_plugin_option( 'version', '0.5.1' );
}

function edit_flow_upgrade_06() {
	global $wpdb, $edit_flow;
	
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->comments SET comment_approved = %s WHERE comment_type = %s", $edit_flow->ef_post_metadata->comment_type, $edit_flow->ef_post_metadata->comment_type ) );
	
	// @todo Remove all of the prior calendar state save data (being stored in user meta now)
	// ..options: 'custom_status_filter', 'custom_category_filter', 'custom_author_filter'
	
	$edit_flow->update_plugin_option( 'version', '0.6' );
}
