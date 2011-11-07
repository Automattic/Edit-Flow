<?php
// Handles all current and future upgrades for edit_flow
function edit_flow_upgrade( $from ) {
	global $edit_flow;
	if ( !$from || version_compare( $from, '0.1', '<' ) )
		edit_flow_upgrade_01();
	if ( version_compare( $from, '0.3', '<' ) )
		edit_flow_upgrade_03();
	if ( version_compare( $from, '0.5.1', '<' ) )
		edit_flow_upgrade_051();
	if ( version_compare( $from, '0.6', '<' ) )
		edit_flow_upgrade_06();
	update_option( $edit_flow->options_group . 'version', EDIT_FLOW_VERSION );
}

// Upgrade to 0.1
function edit_flow_upgrade_01() {
	global $edit_flow;	
	
	// Create default statuses
	$default_terms = array( 
		array( 'term' => __( 'Draft' ), 'args' => array( 'slug' => 'draft', 'description' => __( 'Post is simply a draft', 'edit-flow' ), ) ),
		array( 'term' => __( 'Pending Review' ), 'args' => array( 'slug' => 'pending', 'description' => __( 'The post needs to be reviewed by an Editor', 'edit-flow' ), ) ),
		array( 'term' => __( 'Pitch', 'edit-flow' ), 'args' => array( 'slug' => 'pitch', 'description' => __( 'Post idea proposed', 'edit-flow' ), ) ),
		array( 'term' => __( 'Assigned', 'edit-flow' ), 'args' => array( 'slug' => 'assigned', 'description' => __( 'The post has been assigned to a writer', 'edit-flow' ), ) ),
		array( 'term' => __( 'Waiting for Feedback', 'edit-flow' ), 'args' => array( 'slug' => 'waiting-for-feedback', 'description' => __( 'The post has been sent to the editor, and is waiting on feedback', 'edit-flow' ) ) ) 
	);
	
	// Okay, now add the default statuses to the db if they don't already exist 
	foreach($default_terms as $term)
		if( !term_exists( $term['term'] ) )
			$edit_flow->custom_status->add_custom_status( $term['term'], $term['args'] );
	
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

		// Add new metadata fields 
		$default_metadata = array(
			array(
				'term' => __( 'Photographer', 'edit-flow' ),
				'args' => array(
					'slug' => 'photographer',
					'description' => json_encode( array(
						'type' => 'user',
						'desc' => __( 'The photographer assigned to this post', 'edit-flow' ),
						) ),
					)
			),
			array(
				'term' => __( 'Due Date', 'edit-flow' ),
				'args' => array(
					'slug' => 'duedate',
					'description' => json_encode( array(
						'type' => 'date',
						'desc' => __( 'The deadline for this post', 'edit-flow' ),
						)
					)
															
				)
			),
			array(
				'term' => __( 'Description', 'edit-flow' ),
				'args' => array( 
					'slug' => 'description',
					'description' => json_encode( array(
						'type' => 'paragraph',
						'desc' => __( 'A short description of what this post will be about.', 'edit-flow' ),
						)
					)
				)
			),
			array(
				'term' => __( 'Contact information', 'edit-flow' ),
				'args' => array(
					'slug' => 'contact-information',
					'description' => json_encode( array(
						'type' => 'paragraph',
						'desc' => __( 'Information on how to contact the writer of this post', 'edit-flow' ),
						)
					)
				)
			),
			array(
				'term' => __( 'Location', 'edit-flow' ),
				'args' => array(
					'slug' => 'location',
					'description' => json_encode( array(
						'type' => 'location',
						'desc' => __( 'The location covered by this post', 'edit-flow' ),
						)
					)
				)
			),
			array(
				'term' => __( 'Needs Photo', 'edit-flow' ),
				'args' => array(
					'slug' => 'needs-photo',
					'description' => json_encode( array(
						'type' => 'checkbox',
						'desc' => __( 'Checked if this post needs a photo', 'edit-flow' ),
						)
					)
				)
			),
			array(
				'term' => __( 'Word Count', 'edit-flow' ),
				'args' => array(
					'slug' => 'word-count',
					'description' => json_encode( array(
						'type' => 'number',
						'desc' => __( 'Required length for this post', 'edit-flow' ),
						)
					)
				)
			),
		);
		
		foreach ( $default_metadata as $term ) {
			if ( !term_exists( $term['args']['slug'], $edit_flow->editorial_metadata->metadata_taxonomy ) ) {
				$new_term = wp_insert_term( $term['term'], $edit_flow->editorial_metadata->metadata_taxonomy, $term['args'] );
				$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $term['args']['description'] ), array( 'term_taxonomy_id' => $new_term['term_taxonomy_id'] ) );
			}
		}
	
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
	
	// Add necessary capabilities to allow management of calendar
	// view_calendar - administrator --> contributor
	$story_budget_roles = array(
		'administrator' => array( 'ef_view_story_budget' ),
		'editor' =>        array( 'ef_view_story_budget' ),
		'author' =>        array( 'ef_view_story_budget' ),
		'contributor' =>   array( 'ef_view_story_budget' )
	);
	
	foreach( $story_budget_roles as $role => $caps ) {
		ef_add_caps_to_role( $role, $caps );
	}
	
	// @todo Remove all of the prior calendar state save data (being stored in user meta now)
	// ..options: 'custom_status_filter', 'custom_category_filter', 'custom_author_filter'
}
