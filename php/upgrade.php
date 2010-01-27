<?php
// Handles all current and future upgrades for edit_flow
function edit_flow_upgrade( $from ) {
	if( $from < 0.3 ) edit_flow_upgrade_03();
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
	if( $wp_roles->is_role('administrator') ) {	
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
		if( !is_term($usergroup['slug'], $edit_flow->notifications->following_usergroups_taxonomy) ) {
			ef_add_usergroup( $usergroup['slug'], $usergroup['args'] );
		}
	}
	update_option($edit_flow->get_plugin_option_fullname('version'), '0.3');
}

?>