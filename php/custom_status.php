<?php
 
// Functions related to hooking into custom statuses will go here

class custom_status {

	// This is taxonomy name used to store all our custom statuses
	var $status_taxonomy = 'post_status';
	
	/**
	 * Constructor
	 */
	function __construct ($active = 1) {
		global $pagenow, $edit_flow;
		
		// Register new taxonomy so that we can store all our fancy new custom statuses (or is it stati?)
		if( !ef_taxonomy_exists( $this->status_taxonomy ) ) register_taxonomy( $this->status_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false, 'show_ui' => false) );
		
		
		// Note: These hooks do not need to be called as of 3.0 since it supports built in post statuses
		// Note: For pre-3.0, these actions should be called regardless of whether custom statuses are enabled or not
		if( ! function_exists( 'register_post_status' ) ) {
			// Add actions and filters for the Edit/Manage Posts page
			add_action( 'load-edit.php', array( &$this, 'load_edit_hooks' ) );
			// Add action and filter for the Edit/Manage Pages page
			add_action( 'load-edit-pages.php', array( &$this, 'load_edit_hooks' ) );
		}
				
		if( $active ) {
			
			// Register custom statuses
			$this->register_custom_statuses();
			
			// Hooks to add "status" column to Edit Posts page
			add_filter('manage_posts_columns', array(&$this, '_filter_manage_posts_columns'));
			add_action('manage_posts_custom_column', array(&$this, '_filter_manage_posts_custom_column'));
			
			// Hooks to add "status" column to Edit Pages page, BUT, only add it if not being filtered by post_status
			add_filter('manage_pages_columns', array(&$this, '_filter_manage_posts_columns'));
			add_action('manage_pages_custom_column', array(&$this, '_filter_manage_posts_custom_column'));
			
		}
	} // END: __construct()
	
	
	/**
	 * Makes the call to register_post_status to register the user's custom statuses.
	 * Also unregisters draft and pending, in case the user doesn't want them.
	 */
	function register_custom_statuses() {
		global $wp_post_statuses;
		
		// @TODO: support for custom post types
		if( function_exists( 'register_post_status' ) ) {
			// Users can delete draft and pending statuses if they want, so let's get rid of them
			// They'll get re-added if the user hasn't "deleted" them
			unset( $wp_post_statuses[ 'draft' ] );
			unset( $wp_post_statuses[ 'pending' ] );
			
			$custom_statuses = $this->get_custom_statuses();
			
			foreach( $custom_statuses as $status ) {
				register_post_status( $status->slug, array(
					'label'       => $status->name
					, 'protected'   => true
					, '_builtin'    => false
					, 'label_count' => _n_noop( "{$status->name} <span class='count'>(%s)</span>", "{$status->name} <span class='count'>(%s)</span>" )
				) );
			}
		}
	}
	
	/**
	 * Hooks to make modifications to the Manage/Edit Posts
	 * Only used for pre-3.0
	 */
	function load_edit_hooks() {
		// Add custom stati to Edit/Manage Posts
		add_action('admin_notices',array(&$this, 'enable_custom_status_filters'));
		// Modify the posts_where query to include custom stati
		add_filter('posts_where', array(&$this, 'custom_status_where_filter'));
	} // END: load_edit_hooks()

	/**
	 * Hooks to make modifications to the Manage/Edit Pages
	 * Only used for pre-3.0
	 */
	function load_edit_pages_hooks() {
		global $edit_flow;
		
		if($edit_flow->get_plugin_option('pages_custom_statuses_enabled')) {
			// Add custom stati to Edit/Manage Pages
			add_filter('apply_filters', array(&$this, 'enable_custom_status_filters_pages'));
			// Modify the posts_where query to include custom stati
			add_filter('posts_where', array(&$this, 'custom_status_where_filter'));
		}
	}
	
	/**
	 * Adds custom stati to the $post_stati array.
	 * This is used to generate the list of statuses on the Edit/Manage Posts page.
	 *
	 */
	function enable_custom_status_filters() {
		// This is the array WP uses to store custom stati (really? stati?)
		// The status list at the top of the Manage/Edit Posts page is generated using this array
		global $post_stati;

		if(is_array($post_stati)) {
			
			// @ TODO Don't return statuses that are empty (i.e. no posts)
			// Get a list of ALL the custom statuses
			$custom_statuses = $this->get_custom_statuses();
			
			// Alright, now append them to the $post_stati array
			foreach($custom_statuses as $status) {
				if(!$this->is_restricted_status($status->slug)) {
					$slug = $status->slug;
					$post_stati[$slug] = array(
						$status->name,
						$status->description,
						array(
							$status->name.' <span class="count">(%s)</span>',
							$status->name.' <span class="count">(%s)</span>'
						)
					);
						
				}
			}
		}
	} // END: enable_custom_status_filters()

	/* Adds custom stati to the $post_stati array for pages
	 * This is used to generate the list of statuses on the Edit/Manage Pages page.
	 *
	 */
	function enable_custom_status_filters_pages($post_stati) {
		if(is_array($post_stati)) {
			
			// @ TODO Don't return statuses that are empty (i.e. no posts)
			// Get a list of ALL the custom statuses
			$custom_statuses = $this->get_custom_statuses();
			
			// Alright, now append them to the $post_stati array
			foreach($custom_statuses as $status) {
				if(!$this->is_restricted_status($status->slug)) {
					$slug = $status->slug;
					$post_stati[$slug] = array(
						$status->name,
						$status->description,
						array(
							$status->name.' <span class="count">(%s)</span>',
							$status->name.' <span class="count">(%s)</span>'
						)
					);
						
				}
			}
		}
		return $post_stati;
	} // END: enable_custom_status_filters_pages()

	/**
	 * Edits the WHERE clause for the the get_post query.
	 * This is used to show all the posts with custom statuses.
	 * Why? Because WordPress automatically hides anything without an allowed status (e.g. "publish", "draft",, etc.)
	 */	
	function custom_status_where_filter($where){
		global $wpdb, $user_ID;
		
		/** 
		 * Replacement code fixes filtering issue
		 * Could not filter by category, author, search, on Manage Posts page
		 *
		 * Mad props to David Smith from Columbia U.
		 **/
		if(is_admin() ) {
			if(!(isset($_GET['post_status'])) && !(isset($_POST['post_status']))) {			
				$custom_statuses = $this->get_custom_statuses();
				//insert custom post_status where statements into the existing the post_status where statements - "post_status = publish OR"
				//the search string
				$search_string = $wpdb->posts.".post_status = 'publish' OR ";
	
				//build up the replacement string
				$replace_string = $search_string;
				foreach($custom_statuses as $status) {
					$replace_string .= $wpdb->posts.".post_status = '".$status->slug."' OR "; 
				}
	
				$where = str_replace($search_string, $replace_string, $where);
				
			} else {
				// Okay, we're filtering by statuses
				$status = $_GET['post_status'];
				
				// if not one of inbuilt custom statuses, delete query where AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private')
				// append status to where
				
				if( ef_term_exists( $status, $this->status_taxonomy ) ) {
					//delete only the offending query --- not the entire query
					 $search_string = "AND (".$wpdb->posts.".post_status = 'publish' OR ".$wpdb->posts.".post_status = 'future' OR ".$wpdb->posts.".post_status = 'draft' OR ".$wpdb->posts.".post_status = 'pending'";
					if ( is_user_logged_in() ) {
						$search_string .= current_user_can( "read_private_posts" ) ? " OR $wpdb->posts.post_status = 'private'" : " OR $wpdb->posts.post_author = $user_ID AND $wpdb->posts.post_status = 'private'";
					}
					$search_string .= ")";
	
					$replace_string = "AND (".$wpdb->posts.".post_status = '".$status."')";
					$where = str_replace($search_string, $replace_string, $where);
				}
			}
	
		}
		return $where;
	} // END: custom_status_where_filter()
	
	/**
	 * Adds a new custom status as a term in the wp_terms table.
	 * Basically a wrapper for the wp_insert_term class.
	 *
	 * The arguments decide how the term is handled based on the $args parameter.
	 * The following is a list of the available overrides and the defaults.
	 *
	 * 'description'. There is no default. If exists, will be added to the database
	 * along with the term. Expected to be a string.
	 *
	 * 'slug'. Expected to be a string. There is no default.
	 *
	 * @param int|string $term The status to add or update
	 * @param array|string $args Change the values of the inserted term
	 * @return array|WP_Error The Term ID and Term Taxonomy ID
	 *
	 */
	function add_custom_status ( $term, $args = array() ) {
		// $args = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
		return wp_insert_term( $term, $this->status_taxonomy, $args );
		
	} // END: add_custom_status
	
	/**
	 * 
	 * Basically a wrapper for the wp_update_term function
	 */
	function update_custom_status ( $status_id, $args = array() ) {
		global $edit_flow;
		
		$default_status = $this->ef_get_default_custom_status()->slug;
		$defaults = array(
				'slug' => $default_status
		);

		// If new status not indicated, use default status
		$args = wp_parse_args( $args, $defaults );
	
		// Reassign posts to new status slug
		$old_status = get_term($status_id, $this->status_taxonomy)->slug;

		if(!$this->is_restricted_status($old_status)) {
			$new_status = $args['slug'];
			$this->reassign_post_status( $old_status, $new_status );
			
			if ($old_status == $default_status)
				$edit_flow->update_plugin_option( 'custom_status_default_status', $new_status );
		}

		return wp_update_term( $status_id, $this->status_taxonomy, $args );
	} // END: update_custom_status
	
	/**
	 * Deletes a custom status from the wp_terms table.
	 * 
	 * Partly a wrapper for the wp_delete_term function.
	 * BUT, also reassigns posts that currently have the deleted status assigned.  
	 */
	function delete_custom_status ( $status_id, $args = array(), $reassign = '' ) {
		global $edit_flow;
		// Reassign posts to alternate status

		// Get slug for the old status
		$old_status = get_term($status_id, $this->status_taxonomy)->slug;

		if ($reassign == $old_status)
			return new WP_Error( 'invalid', __( 'cannot reassign to the status you want to delete', 'edit-flow' ) );
		
		if(!$this->is_restricted_status($old_status)) {
			$default_status = $this->ef_get_default_custom_status()->slug;
			// If new status in $reassign, use that for all posts of the old_status
			if( !empty( $reassign ) )
				$new_status = get_term($reassign, $this->status_taxonomy)->slug;
			else
				$new_status = $default_status;
			if ( $old_status == $default_status && ef_term_exists( 'draft', $this->status_taxonomy ) ) { // Deleting default status
				$new_status = 'draft';
				$edit_flow->update_plugin_option( 'custom_status_default_status', $new_status );
			}
			
			$this->reassign_post_status( $old_status, $new_status );
			
			return wp_delete_term( $status_id, $this->status_taxonomy, $args );
		} else
			return new WP_Error( 'restricted', __( 'restricted status ', 'edit-flow' ) . '(' . get_term($status_id, $this->status_taxonomy)->name . ')' );
	} // END: delete_custom_status

	function get_custom_statuses ($statuses = '', $args ='' ) {
		
		// @TODO: implement $args, to allow for pagination, etc. 

		if(!$statuses) {
			// return all stati			
			$statuses = get_terms( $this->status_taxonomy, array( 'get' => 'all' ) );
		} else if (!is_array($statuses)) {
			// return a single status			
		} else {
			// return multiple stati 	
		}
		
		return $statuses;		
	}
	
	/**
	 * Returns the a single status object based on ID or slug
	 * @param 
	 */
	function get_custom_status( $status ) {
		if( is_int( $status ) ) {
			return get_term_by( 'id', $status, $this->status_taxonomy );
		} else {
			return get_term_by( 'slug', $status, $this->status_taxonomy );
		}
	}
	
	/**
	 * Returns the friendly name for a given status
	 * @param 
	 */
	function get_custom_status_friendly_name( $status ) {
		
		$status_friendly_name = '';
		
		$builtin_stati = array(
			'publish' => 'Published',
			'draft' => 'Draft',
			'future' => 'Scheduled',
			'private' => 'Private',
			'pending' => 'Pending Review',
			'trash' => 'Trash',
		);
		
		if( array_key_exists( $status, $builtin_stati ) ) {
			$status_friendly_name = $builtin_stati[$status];
		} else {
			$status_object = $this->get_custom_status( $status );
			if( $status_object && !is_wp_error( $status_object ) ) {
				$status_friendly_name = $status_object->name;
			}
		}
		
		return $status_friendly_name;
	}

	/**
	 * Returns the term object from the database for the default custom status. Commonly used fields are name and slug.
	 */
	function ef_get_default_custom_status() {
		global $edit_flow;
		return get_term_by('slug', $edit_flow->get_plugin_option( 'custom_status_default_status' ), $this->status_taxonomy );
	}
	
	function reassign_post_status( $old_status, $new_status = '' ) {
		global $wpdb;
		
		if ( empty( $new_status ) )
			$new_status = $this->ef_get_default_custom_status()->slug;
		
		// Make the database call
		$result = $wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'post_status' => $old_status ), array( '%s' ));
	}
	
	/**
	 * Insert new column header for post status
	 * 
	 * @param array $post_columns
	 **/
	function _filter_manage_posts_columns($posts_columns) {
		$result = array();
		foreach ($posts_columns as $key => $value) {
			if ($key == 'title') {
				$result[$key] = $value;
				$result['status'] = __('Status', 'edit-flow');
			} else $result[$key] = $value;
		}
		return $result;
	} // END: _filter_manage_posts_columns
	
	/**
	 * Adds a Post's status to its row on the Edit page
	 * 
	 * @param string $column_name
	 **/
	function _filter_manage_posts_custom_column($column_name) {
		if ($column_name == 'status') {
			global $post, $custom_status;
			echo $this->get_custom_status_friendly_name( $post->post_status );
		}
	}
	
	
	/**
	 * Determines whether the slug indicated belongs to a restricted status or not
	 * @param string Slug of the status 
	 * @return bool True if restricted, false if not
	 */
	function is_restricted_status ( $slug ) {

		switch($slug) {
			case 'publish':
			case 'draft':
			case 'private':
			case 'future':
			case 'pending':
			case 'new':
			case 'inherit':
			case 'auto-draft':
			case 'trash':
				$return = true;
				break;
			
			default:
				$return = false;
				break;
		}
		return $return;	
	}

	
	/**
	 * Main controller for Usergroups
	 * Determines what actions to take, takes those actions, and sets up necessary data for views (handled by admin_page())
	 * Sets up a var called $ef_page_data that the admin_page() function can use to pull data from
	 */
	function admin_controller ( ) {
		global $ef_page_data;
		
		// Only allow users with the proper caps
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
		
		// Global var that holds all the data needed on edit flow pages
		$ef_page_data = array();
		
		$nonce_fail_msg = __('There\'s something fishy going on! We don\'t like this type of nonce-sense. Hmph.', 'edit-flow');
		$msg_class = 'updated';
		
		$action = null; $error_details = null; $msg = null; $edit_status = null; $update = null;
		
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';

		switch( $action ) {
			
			case 'add':
			
				// Verfiy nonce
				if( wp_verify_nonce( $_POST['custom-status-add-nonce'], 'custom-status-add-nonce' ) ) {
					
					$ef_page_data['update'] = false;
					
					$status_name = esc_html( trim( $_POST['status_name'] ) );
					$status_slug = sanitize_title( $status_name );
					$status_description = esc_html( $_POST['status_description'] );
					
					// Check if name field was filled in
					if( ! $status_name || empty( $status_name ) ) {
						$ef_page_data['errors'] = __( 'Please enter a name for the status', 'edit-flow' );
						break;
					}
					
					// Check that the name isn't numeric
					if( (int)$status_name != 0 ) {
						$ef_page_data['errors'] = __( 'Please enter a valid name.', 'edit-flow' );
					}
					
					// Check that the status name doesn't exceed 20 chars
					if( count( $status_name ) > 20 ) {
						$ef_page_data['errors'] = __( 'The status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
						break;
					}
					
					// Check to make sure the name is not restricted
					if($this->is_restricted_status( strtolower( $status_slug ) ) ) {
						$ef_page_data['errors'] = __( 'That status name is restricted. Please use another name.', 'edit-flow' );
						break;
					}
					
					// Check to make sure the status doesn't already exist
					if( ef_term_exists( $status_slug ) ) {
						$ef_page_data['errors'] = __( 'That status already exists. Please use another name.', 'edit-flow' );
						break;
					}
					
					// Try to add the status
					$status_args = array( 
						'description' => $status_description
						, 'slug' => $status_slug
					);
					$return = $this->add_custom_status( $status_name, $status_args );
					
					if( ! is_wp_error( $return ) ) {
						$msg = __( 'Status successfully added.', 'edit-flow' );
						$redirect = EDIT_FLOW_CUSTOM_STATUS_PAGE .'&message='. urlencode( $msg );
						wp_redirect( $redirect );
					} else {
						$ef_page_data['errors'] = sprintf( __( 'Could not add the status: ', 'edit-flow' ), $return->get_error_message() );
					}
					
				} else {
					$ef_page_data['errors'] = $nonce_fail_msg;
				}
				
				break;
			
			case 'edit':
				$term_id = (int) $_GET['term_id'];
				
				if( $term_id && $the_status = get_term( $term_id, $this->status_taxonomy ) ) {
					
					// Stop users from editing restricted statuses
					if($this->is_restricted_status($the_status->slug)) {
						$message = __( 'That status is restricted and cannot be edited. However, you are welcome to delete it.', 'edit-flow' );
						$redirect = EDIT_FLOW_CUSTOM_STATUS_PAGE .'&errors='. urlencode( $message );
						wp_redirect( $redirect );
					} else {
						$ef_page_data['title'] = __( 'Update Custom Status', 'edit-flow' );
						$ef_page_data['custom_status'] = $the_status;
						$ef_page_data['update'] = true;
					}
				}
				
				break;
			
			case 'update':
				
				
				// Verfiy nonce
				if(wp_verify_nonce($_POST['custom-status-add-nonce'], 'custom-status-add-nonce')) {
					$term_id = (int) $_POST['term_id'];
					
					$status_name = esc_html(trim($_POST['status_name']));
					$status_slug = sanitize_title($_POST['status_name']);
					$status_description = esc_html($_POST['status_description']);
					
					// Check if name field was filled in
					if(!$status_name || empty($status_name)) {
						$ef_page_data['errors'] = __('Please enter a name for the status', 'edit-flow');
						break;
					}

					// Check that the name isn't numeric
					if( (int)$status_name != 0 ) {
						$ef_page_data['errors'] = __('Please enter a valid name.', 'edit-flow');
					}

					// Check that the status name doesn't exceed 20 chars
					if( count($status_name) > 20 ) {
						$ef_page_data['errors'] = __('The status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the name is not restricted
					if($this->is_restricted_status(strtolower($status_slug))) {
						$ef_page_data['errors'] = __('That status name is restricted. Please use another name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the status doesn't already exist
					if( ef_term_exists( $status_slug ) && (get_term($term_id, $this->status_taxonomy)->slug != $status_slug)) {
						$ef_page_data['errors'] = __('That status already exists. Please use another name.', 'edit-flow');
						break;
					}
					
					// get status_name & status_description
					$args = array( 'name' => $status_name, 'description' => $status_description, 'slug' => $status_slug );
					
					$return = $this->update_custom_status($term_id, $args);
					if(!is_wp_error($return)) {
						$message = __('Status successfully updated.', 'edit-flow');
						$redirect = EDIT_FLOW_CUSTOM_STATUS_PAGE .'&message='. urlencode($message);
						wp_redirect( $redirect );
						
					} else {
						$ef_page_data['errors'] = __('Could not update the status: ', 'edit-flow') . $return->get_error_message();
					}
					
				} else {
					$ef_page_data['errors'] = $nonce_fail_msg;
				}
			
				break;
				
			case 'delete':
				
				// Verfiy nonce
				if(wp_verify_nonce($_GET['_wpnonce'], 'custom-status-delete-nonce')) {
					$term_id = (int) $_GET['term_id'];

					// Check to make sure the status isn't already deleted
					if( !ef_term_exists( $term_id, $this->status_taxonomy ) ) {
						$error_details = __('That status does not exist. Try again?', 'edit-flow');
						break;
					}
					
					$return = $this->delete_custom_status($term_id);
					if(!is_wp_error($return)) {
						$message = __('Status successfully deleted.', 'edit-flow');
						$redirect = EDIT_FLOW_CUSTOM_STATUS_PAGE .'&message='. urlencode($message);
						wp_redirect( $redirect );
					} else {
						$ef_page_data['errors'] = __('Could not delete the status: ', 'edit-flow') . $return->get_error_message();
					}
						
				} else {
					$ef_page_data['errors'] = $nonce_fail_msg;
				}
				
				break;
				
			case 'view':
			default:
				break;
		}

		$ef_page_data['custom_statuses'] = $this->get_custom_statuses();
		
	}

	/**
	 * Handles the main admin page for Custom Statuses.
	 */
	function admin_page( ) {
		global $ef_page_data;
		
		// Set up defaults
		$page_defaults = array(
			'title' => __( 'Custom Post Statuses', 'edit-flow' )
			, 'view' => 'templates/custom_status_main.php',
		);
		
		// extract all the args
		$ef_page_data = wp_parse_args( $ef_page_data, $page_defaults );
		extract( $ef_page_data );
		
		include_once( $ef_page_data['view'] );
		
	}	
		
} // END: class custom_status

function ef_get_custom_status_filter_link ( $slug ) {
	return 'edit.php?post_status='.$slug;
}

function ef_get_custom_status_edit_link( $id ) {
	return EDIT_FLOW_CUSTOM_STATUS_PAGE.'&amp;action=edit&amp;term_id='.$id;
}

function ef_get_custom_status_post_count ( $status ) {
	global $wpdb;
	
	if(is_int($status)) {
		$status = get_term_by('term_id', $status, 'post_status')->slug;
	}
	
	$query = $wpdb->prepare("SELECT count(ID) FROM $wpdb->posts WHERE post_status = %s", $status);
	
	return $wpdb->get_var($query);
}
