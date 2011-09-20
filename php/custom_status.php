<?php
 
// Functions related to hooking into custom statuses will go here

if ( !class_exists( 'EF_Custom_Status' ) ) {

class EF_Custom_Status {

	// This is taxonomy name used to store all our custom statuses
	var $status_taxonomy = 'post_status';
	var $module;
	
	/**
	 * Register the module with Edit Flow but don't do anything else
	 */
	function __construct() {
		global $edit_flow;
		
		// Register the module with Edit Flow
		// @todo default options for registering the statuses
		$args = array(
			'title' => __( 'Custom Statuses', 'edit-flow' ),
			'short_description' => __( 'Custom statuses allow you to do many awesome things with your workflow. tk', 'edit-flow' ),
			'extended_description' => __( 'This is a longer description that shows up on some views. We might want to include a link to documentation. tk', 'edit-flow' ),
			'img_url' => false,
			'slug' => 'custom-status',
			'default_options' => array(
				'enabled' => 'on',
				'default_status' => 'draft',
				'always_show_dropdown' => 'off',
				'post_types' => array(
					'post' => 'on',
					'page' => 'on',
				),
			),
			'configure_page_cb' => 'configure_page',
			'configure_link_text' => __( 'Edit Statuses' ),			
			'autoload' => false,
		);
		$this->module = $edit_flow->register_module( 'custom_status', $args );		
		
	}
		
	/**
	 * Initialize the rest of the stuff in the class if the module is active
	 */
	function init() {
		global $edit_flow;
		
		// Register custom statuses
		$this->register_custom_statuses();
		
		// Register our settings
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action( 'admin_init', array( &$this, 'handle_settings_and_creation' ) );
		
		// Hooks to add "status" column to Edit Posts page
		add_filter( 'manage_posts_columns', array( &$this, '_filter_manage_posts_columns') );
		add_action( 'manage_posts_custom_column', array( &$this, '_filter_manage_posts_custom_column') );
		
		// Hooks to add "status" column to Edit Pages page, BUT, only add it if not being filtered by post_status
		add_filter( 'manage_pages_columns', array( &$this, '_filter_manage_posts_columns' ) );
		add_action( 'manage_pages_custom_column', array( &$this, '_filter_manage_posts_custom_column' ) );
		
		add_action( 'wp_ajax_inline_save_status', array( &$this, 'ajax_inline_save_status' ) );		
		add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( &$this, 'no_js_notice' ) );
		add_action( 'admin_print_scripts', array( &$this, 'post_admin_header' ) );		
		
		add_action( 'admin_init', array( &$this, 'check_timestamp_on_publish' ) );
		add_filter( 'wp_insert_post_data', array( &$this, 'fix_custom_status_timestamp' ) );		
		
	}
	
	/**
	 * Makes the call to register_post_status to register the user's custom statuses.
	 * Also unregisters draft and pending, in case the user doesn't want them.
	 */
	function register_custom_statuses() {
		global $wp_post_statuses;
		
		// Register new taxonomy so that we can store all our fancy new custom statuses (or is it stati?)
		if ( !ef_taxonomy_exists( $this->status_taxonomy ) ) {
			$args = array(	'hierarchical' => false, 
							'update_count_callback' => '_update_post_term_count',
							'label' => false,
							'query_var' => false,
							'rewrite' => false,
							'show_ui' => false
					);
			register_taxonomy( $this->status_taxonomy, 'post', $args );
		}		
		
		if ( function_exists( 'register_post_status' ) ) {
			// Users can delete draft and pending statuses if they want, so let's get rid of them
			// They'll get re-added if the user hasn't "deleted" them
			unset( $wp_post_statuses[ 'draft' ] );
			unset( $wp_post_statuses[ 'pending' ] );
			
			$custom_statuses = $this->get_custom_statuses();
			
			foreach ( $custom_statuses as $status ) {
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
	 * enable_custom_status_filters()
	 * Adds custom stati to the $post_stati array.
	 * This is used to generate the list of statuses on the Edit/Manage Posts page.
	 */
	function enable_custom_status_filters() {
		// This is the array WP uses to store custom stati (really? stati?)
		// The status list at the top of the Manage/Edit Posts page is generated using this array
		global $post_stati;

		if ( is_array( $post_stati ) ) {
			
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

	/**
	 * enable_custom_status_filters_pages()
	 * Adds custom stati to the $post_stati array for pages
	 * This is used to generate the list of statuses on the Edit/Manage Pages page.
	 * 
	 * @param object $post_stati All of the post statuses
	 * @return object $post_stati Post statuses with Edit Flow custom statuses
	 */
	function enable_custom_status_filters_pages( $post_stati ) {
		
		if ( is_array( $post_stati ) ) {
			
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
	 * 
	 */
	function action_admin_enqueue_scripts() {
		global $pagenow;
		
		if ( $pagenow == 'options-general.php' && isset( $_GET['page'], $_GET['configure'] )
		 	&& $_GET['page'] == 'edit-flow' && $_GET['configure'] == 'custom-status' )
			wp_enqueue_script( 'edit-flow-custom-status-configure', EDIT_FLOW_URL . 'js/custom_status_configure.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
			
		if ( $this->is_whitelisted_page() )
			wp_enqueue_script( 'edit_flow-custom_status', EDIT_FLOW_URL.'js/custom_status.js', array('jquery','post'), EDIT_FLOW_VERSION, true );
			
	}
	
	/**
	 * Displays a notice to users if they have JS disabled
	 */
	function no_js_notice() {
		if( $this->is_whitelisted_page() ) :
			?>
			<style type="text/css">
			/* Hide post status dropdown by default in case of JS issues **/
			label[for=post_status],
			#post-status-display,
			#post-status-select {
				display: none;
			}
			</style>		
			<div class="update-nag hide-if-js">
				<?php _e( '<strong>Note:</strong> Your browser does not support JavaScript or has JavaScript disabled. You will not be able to access or change the post status.', 'edit-flow' ); ?>
			</div>
			<?php
		endif;
	}	
	
	/**
	 * Check whether custom status stuff should be loaded on this page
	 */
	function is_whitelisted_page() {
		global $edit_flow, $pagenow;
		
		$post_type = $edit_flow->get_current_post_type();
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		if ( !in_array( $post_type, $supported_post_types ) )
			return;
		
		if( ! current_user_can('edit_posts') )
			return;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		return in_array( $pagenow, array( 'post.php', 'edit.php', 'post-new.php', 'page.php', 'edit-pages.php', 'page-new.php' ) );
	}
	
	/**
	 * Adds all necessary javascripts to make custom statuses work
	 *
	 * @todo Support private and future posts on edit.php view
	 */
	function post_admin_header() {
		global $post, $edit_flow, $pagenow, $current_user;
		
		// Get current user
		get_currentuserinfo() ;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		if ( !empty( $post ) && $this->is_whitelisted_page() ) {
			
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			$custom_statuses = apply_filters( 'ef_custom_status_list', $custom_statuses, $post );			
	
			// Get the status of the current post		
			if ( $post->ID == 0 || $post->post_status == 'auto-draft' || $pagenow == 'edit.php' ) {
				// TODO: check to make sure that the default exists
				$selected = $edit_flow->get_plugin_option('custom_status_default_status');

			} else {
				$selected = $post->post_status;
			}

			// All right, we want to set up the JS var which contains all custom statuses
			$all_statuses = array(); 
			
			// The default statuses from WordPress
			$all_statuses[] = array(
				'name' => __( 'Published', 'edit-flow' ),
				'slug' => 'publish',
				'description' => '',
			);
			$all_statuses[] = array(
				'name' => __( 'Privately Published', 'edit-flow' ),
				'slug' => 'private',
				'description' => '',
			);
			$all_statuses[] = array(
				'name' => __( 'Scheduled', 'edit-flow' ),
				'slug' => 'future',
				'description' => '',
			);

			// Load the custom statuses
			foreach( $custom_statuses as $status ) {
				$all_statuses[] = array(
					'name' => esc_js( $status->name ),
					'slug' => esc_js( $status->slug ),
					'description' => esc_js( $status->description ),
				);
			}
			
 			$always_show_dropdown = ( $this->module->options->always_show_dropdown == 'on' ) ? 1 : 0;
			
			// Now, let's print the JS vars
			?>
			<script type="text/javascript">
				var custom_statuses = <?php echo json_encode( $all_statuses ); ?>;
				var ef_text_no_change = '<?php _e( "&mdash; No Change &mdash;" ); ?>';
				var ef_default_custom_status = '<?php $edit_flow->get_plugin_option("custom_status_default_status"); ?>';
				var current_status = '<?php echo $selected ?>';
				var status_dropdown_visible = <?php echo $always_show_dropdown ?>;
				var current_user_can_publish_posts = <?php if ( current_user_can('publish_posts') ) { echo 1; } else { echo 0; } ?>;
			</script>
			
			<?php

		}
		
	}

	/**
	 * custom_status_where_filter()
	 * Edits the WHERE clause for the the get_post query.
	 * This is used to show all the posts with custom statuses.
	 * Why? Because WordPress automatically hides anything without an allowed status (e.g. "publish", "draft",, etc.)
	 *
	 * @param string $where Original SQL query
	 * @return string $where Modified SQL query
	 */	
	function custom_status_where_filter( $where ){
		global $wpdb, $user_ID;
		
		/** 
		 * Replacement code fixes filtering issue
		 * Could not filter by category, author, search, on Manage Posts page
		 *
		 * Mad props to David Smith from Columbia U.
		 **/
		if ( is_admin() ) {
			if(!(isset($_GET['post_status'])) && !(isset($_POST['post_status']))) {			
				$custom_statuses = $this->get_custom_statuses();
				//insert custom post_status where statements into the existing the post_status where statements - "post_status = publish OR"
				//the search string
				$search_string = $wpdb->posts.".post_status = 'publish' OR ";
	
				//build up the replacement string
				$replace_string = $search_string;
				foreach ( $custom_statuses as $status ) {
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
	 * add_custom_status()
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
	 * @return array|WP_Error $response The Term ID and Term Taxonomy ID
	 */
	function add_custom_status( $term, $args = array() ) {
		// $args = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
		$response = wp_insert_term( $term, $this->status_taxonomy, $args );
		return $response;
		
	} // END: add_custom_status()
	
	/**
	 * update_custom_status()
	 * Basically a wrapper for the wp_update_term function
	 *
	 * @param int @status_id ID for the status
	 * @param array $args Any arguments to be updated
	 * @return object $updated_status Newly updated status object
	 */
	function update_custom_status( $status_id, $args = array() ) {
		global $edit_flow;
		
		$default_status = $this->ef_get_default_custom_status()->slug;
		$defaults = array( 'slug' => $default_status );

		// If new status not indicated, use default status
		$args = wp_parse_args( $args, $defaults );
	
		// Reassign posts to new status slug
		$old_status = get_term( $status_id, $this->status_taxonomy )->slug;

		if( !$this->is_restricted_status( $old_status ) ) {
			$new_status = $args['slug'];
			$this->reassign_post_status( $old_status, $new_status );
			
			if ( $old_status == $default_status )
				$edit_flow->update_plugin_option( 'custom_status_default_status', $new_status );
		}

		$updated_status_array = wp_update_term( $status_id, $this->status_taxonomy, $args );
		$updated_status = $this->get_custom_status( $updated_status_array['term_id']);

		return $updated_status;
		
	} // END: update_custom_status()
	
	/**
	 * delete_custom_status()
	 * Deletes a custom status from the wp_terms table.
	 * 
	 * Partly a wrapper for the wp_delete_term function.
	 * BUT, also reassigns posts that currently have the deleted status assigned.  
	 */
	function delete_custom_status( $status_id, $args = array(), $reassign = '' ) {
		global $edit_flow;
		// Reassign posts to alternate status

		// Get slug for the old status
		$old_status = get_term($status_id, $this->status_taxonomy)->slug;

		if ($reassign == $old_status)
			return new WP_Error( 'invalid', __( 'Cannot reassign to the status you want to delete', 'edit-flow' ) );
		
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
			return new WP_Error( 'restricted', __( 'Restricted status ', 'edit-flow' ) . '(' . get_term($status_id, $this->status_taxonomy)->name . ')' );
			
	} // END: delete_custom_status

	/**
	 * get_custom_statuses()
	 *
	 * @param array|string $statuses
	 * @param array $args
	 * @return object $statuses All of the statuses
	 */
	function get_custom_statuses( $statuses = '', $args ='' ) {
		
		// @TODO: implement $args, to allow for pagination, etc. 

		if ( !$statuses ) {
			// return all stati			
			$statuses = get_terms( $this->status_taxonomy, array( 'get' => 'all' ) );
		} else if ( !is_array( $statuses ) ) {
			// return a single status			
		} else {
			// return multiple stati 	
		}
		
		return $statuses;
				
	} // END: get_custom_statuses()
	
	/**
	 * get_custom_status()
	 * Returns the a single status object based on ID or slug
	 *
	 * @param string|int $status The status to search for, either by slug or ID
	 * @return object $status The object for the matching status
	 */
	function get_custom_status( $status ) {
		
		if ( is_int( $status ) ) {
			return get_term_by( 'id', $status, $this->status_taxonomy );
		} else {
			return get_term_by( 'slug', $status, $this->status_taxonomy );
		}
		
	} // END: get_custom_status()

	/**
	 * ef_get_default_custom_status()
	 * Get the term object for the default custom post status
	 *
	 * @return object $default_status Default post status object
	 */
	function ef_get_default_custom_status() {
		global $edit_flow;
		$default_status = get_term_by('slug', $edit_flow->get_plugin_option( 'custom_status_default_status' ), $this->status_taxonomy );
		return $default_status;
		
	} // END: ef_get_default_custom_status()
	
	/**
	 * reassign_post_status()
	 * Assign new statuses to posts using value provided or the default
	 *
	 * @param string $old_status Slug for the old status
	 * @param string $new_status Slug for the new status
	 */
	function reassign_post_status( $old_status, $new_status = '' ) {
		global $wpdb;
		
		if ( empty( $new_status ) )
			$new_status = $this->ef_get_default_custom_status()->slug;
		
		// Make the database call
		$result = $wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'post_status' => $old_status ), array( '%s' ));
	} // END: reassign_post_status()
	
	/**
	 * _filter_manage_posts_columns()
	 * Insert new column header for post status
	 *
	 * @param array $post_columns
	 **/
	function _filter_manage_posts_columns( $posts_columns ) {
		
		$result = array();
		foreach ( $posts_columns as $key => $value ) {
			if ($key == 'title') {
				$result[$key] = $value;
				$result['status'] = __('Status', 'edit-flow');
			} else $result[$key] = $value;
		}
		return $result;
		
	} // END: _filter_manage_posts_columns()
	
	/**
	 * _filter_manage_posts_custom_column()
	 * Adds a Post's status to its row on the Edit page
	 * 
	 * @param string $column_name
	 **/
	function _filter_manage_posts_custom_column( $column_name ) {
		
		if ( $column_name == 'status' ) {
			global $post, $custom_status;
			echo $this->get_custom_status_friendly_name( $post->post_status );
		}
		
	} // END: _filter_manage_posts_custom_column()
	
	
	/**
	 * is_restricted_status()
	 * Determines whether the slug indicated belongs to a restricted status or not
	 *
	 * @param string $slug Slug of the status 
	 * @return bool $restricted True if restricted, false if not
	 */
	function is_restricted_status( $slug ) {

		switch( $slug ) {
			case 'publish':
			case 'draft':
			case 'private':
			case 'future':
			case 'pending':
			case 'new':
			case 'inherit':
			case 'auto-draft':
			case 'trash':
				$restricted = true;
				break;
			
			default:
				$restricted = false;
				break;
		}
		return $restricted;
		
	} // END: is_restricted_status()
	
	/**
	 * admin_controller()
	 * Main controller for Usergroups
	 * Determines what actions to take, takes those actions, and sets up necessary data for views (handled by admin_page())
	 * Sets up a var called $ef_page_data that the admin_page() function can use to pull data from
	 */
	function admin_controller() {
		global $ef_page_data;
		
		// Only allow users with the proper caps
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
		
		// Global var that holds all the data needed on edit flow pages
		$ef_page_data = array();
		
		$nonce_fail_msg = __( 'Nonce check failed. Please make sure you have proper permissions to edit custom statuses.', 'edit-flow' );
		$msg_class = 'updated';
		
		$action = $error_details = $msg = $edit_status = $update = null;
		
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';

		// Global var that holds all the data needed on edit flow pages
		$ef_page_data = array(
			'message' => null,
			'errors' => null,
		);

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
						$ef_page_data['errors'] = __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
						break;
					}
					
					// Check to make sure the name is not restricted
					if($this->is_restricted_status( strtolower( $status_slug ) ) ) {
						$ef_page_data['errors'] = __( 'Status name is restricted. Please use another name.', 'edit-flow' );
						break;
					}
					
					// Check to make sure the status doesn't already exist
					if( ef_term_exists( $status_slug ) ) {
						$ef_page_data['errors'] = __( 'Status already exists. Please use another name.', 'edit-flow' );
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
						$message = __( 'Specified status is restricted and cannot be edited.', 'edit-flow' );
						$redirect = EDIT_FLOW_CUSTOM_STATUS_PAGE .'&errors='. urlencode( $message );
						wp_redirect( $redirect );
					} else {
						$ef_page_data['title'] = __( 'Update Custom Status', 'edit-flow' );
						$ef_page_data['custom_status'] = $the_status;
						$ef_page_data['update'] = true;
					}
				}
				
				break;			
				
			case 'delete':
				
				// Verfiy nonce
				if (wp_verify_nonce($_GET['_wpnonce'], 'custom-status-delete-nonce')) {
					$term_id = (int) $_GET['term_id'];

					// Check to make sure the status isn't already deleted
					if( !ef_term_exists( $term_id, $this->status_taxonomy ) ) {
						$error_details = __('Status does not exist. Try again?', 'edit-flow');
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
	
	function handle_settings_and_creation() {
		
	}
	
	/**
	 * Register settings for notifications so we can partially use the Settings API
	 * (We use the Settings API for form generation, but not saving)
	 * 
	 * @since 0.7
	 */
	function register_settings() {
		
			add_settings_section( $this->module->options_group_name . '_general', false, array( &$this, 'settings_general_section'), $this->module->options_group_name );
			add_settings_field( 'post_types', 'Use on these post types:', array( &$this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'always_show_dropdown', 'Always show dropdown:', array( &$this, 'settings_always_show_dropdown_option'), $this->module->options_group_name, $this->module->options_group_name . '_general' );

	}
	
	function settings_general_section() {
		
	}
	
	/**
	 * Choose the post types that should be displayed on the calendar
	 *
	 * @since 0.7
	 */
	function settings_post_types_option() {
		global $edit_flow;
		$edit_flow->settings->helper_option_custom_post_type( $this->module );
	}
	
	/**
	 * Option for whether the blog admin email address should be always notified or not
	 *
	 * @since 0.7
	 */
	function settings_always_show_dropdown_option() {
		$options = array(
			'off' => __( 'Disabled', 'edit-flow' ),			
			'on' => __( 'Enabled', 'edit-flow' ),
		);
		echo '<select id="always_show_dropdown" name="' . $this->module->options_group_name . '[always_show_dropdown]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->always_show_dropdown, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
	
	function settings_validate( $new_options ) {
		global $edit_flow;
		
		// Whitelist validation for the post type options
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $edit_flow->helpers->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		// Whitelist validation for the 'always_show_dropdown' optoins
		if ( !isset( $new_options['always_show_dropdown'] ) || $new_options['always_show_dropdown'] != 'on' )
			$new_options['always_show_dropdown'] = 'off';		
		
		return $new_options;
	}	
	
	function configure_page() {
		global $edit_flow;
		
		$wp_list_table = new EF_Custom_Status_List_Table();
		$wp_list_table->prepare_items();
		?>	
			<div id="col-right">
				<div class="col-wrap">
					<?php $wp_list_table->display(); ?>
				</div>
			</div>
			<div id="col-left">
				<div class="col-wrap">
					<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'add' ) : ?>
					<div class="form-wrap">
						<h3><?php _e( 'Add Custom Status', 'edit-flow' ); ?></h3>
						<form class="add:the-list:" action="<?php echo esc_url( 'configure', $this->module->slug, EDIT_FLOW_SETTINGS_PAGE ); ?>" method="post" id="addstatus" name="addstatus">
						<div class="form-field form-required">
							<label for="status_name"><?php _e( 'Name', 'edit-flow' ); ?></label>
							<input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php if ( !empty( $custom_status ) ) esc_attr_e($custom_status->name) ?>" />
							<p><?php _e('The name is used to identify the status. (Max: 20 characters)', 'edit-flow') ?></p>
						</div>
						<div class="form-field">
							<label for="status_description"><?php _e( 'Description', 'edit-flow' ); ?></label>
							<textarea cols="40" rows="5" id="status_description" name="status_description"></textarea>
 							<p><?php _e( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'edit-flow' ); ?></p>
						</div>
						<input type="hidden" name="page" value="edit-flow/php/custom_status" />
						<input type="hidden" name="custom-status-add-nonce" id="custom-status-add-nonce" value="<?php echo wp_create_nonce('custom-status-add-nonce') ?>" />
						<p class="submit"><input type="submit" value="<?php _e( 'Add Custom Status', 'edit-flow' ) ?>" name="submit" class="button"/></p>
						</form>
					</div>
					<?php else: ?>
					<div class="form-wrap">
						<h3><?php _e( 'Custom Status Settings', 'edit-flow' ); ?></h3>
					<form class="basic-settings" action="<?php echo esc_url( add_query_arg( 'configure', $this->module->slug, EDIT_FLOW_SETTINGS_PAGE ) ); ?>" method="post">
						<?php settings_fields( $this->module->options_group_name ); ?>
						<?php do_settings_sections( $this->module->options_group_name ); ?>	
						<?php
							echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
						?>
						<p class="submit"><input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" /><a class="cancel-settings-link" href="<?php echo esc_url( EDIT_FLOW_SETTINGS_PAGE ); ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>
					</form>
					</div>
					<?php endif; ?>
				</div>
			</div>
		
			<?php $wp_list_table->inline_edit(); ?>
		<?php
	}
	
	function ajax_inline_save_status() {
		global $edit_flow;
		
		if ( !wp_verify_nonce( $_POST['inline_edit'], 'custom-status-inline-edit-nonce' ) || !current_user_can( 'manage_options') )
			wp_die( __( 'Cheatin&#8217; uh?' ) );
	
		if ( !$_POST['name'] || !$_POST['status_id'] )
			die('-1');
		
		$term_id = (int) $_POST['status_id'];
		
		$status_name = esc_html( trim( $_POST['name'] ) );
		$status_slug = sanitize_title( $_POST['name'] );
		$status_description = esc_html( $_POST['description'] );
		
		// Check if name field was filled in
		if ( !$status_name ) {
			$change_error = new WP_Error( 'invalid', __( 'Please enter a name for the status.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}

		// Check that the name isn't numeric
		if( (int)$status_name != 0 ) {
			$change_error = new WP_Error( 'invalid', __( 'Please enter a non-numeric name for the status.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}

		// Check that the status name doesn't exceed 20 chars
		if ( count( $status_name ) > 20 ) {
			$change_error = new WP_Error( 'invalid', __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );			
		}
		
		// Check to make sure the name is not restricted
		if ( $edit_flow->custom_status->is_restricted_status( strtolower( $status_name ) ) ) {
			$change_error = new WP_Error( 'invalid', __( 'Status name is restricted. Please use another name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// Check to make sure the status doesn't already exist
		if( ef_term_exists( $status_slug ) && (get_term($term_id, $this->status_taxonomy)->slug != $status_slug)) {
			$change_error = new WP_Error( 'invalid', __( 'Status already exists. Please use another name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// get status_name & status_description
		$args = array(
			'name' => $status_name,
			'description' => $status_description,
			'slug' => $status_slug
		);		
		$return = $this->update_custom_status( $term_id, $args );
		if( !is_wp_error( $return ) ) {	
			set_current_screen( 'edit-custom-status' );					
			$wp_list_table = new EF_Custom_Status_List_Table();
			$wp_list_table->prepare_items();
			echo $wp_list_table->single_row( $return );
			die();
		} else {
			$change_error = new WP_Error( 'invalid', __( 'Could not update the status:' . $status_name, 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
	
	}
	
	/**
	 * This is a hack! hack! hack! until core is fixed/better supports custom statuses
	 *	
	 * When publishing a post with a custom status, set the status to 'pending' temporarily
	 * @see Works around this limitation: http://core.trac.wordpress.org/browser/tags/3.2.1/wp-includes/post.php#L2694
	 * @see Original thread: http://wordpress.org/support/topic/plugin-edit-flow-custom-statuses-create-timestamp-problem
	 * @see Core ticket: http://core.trac.wordpress.org/ticket/18362
	 */
	function check_timestamp_on_publish() {
		global $edit_flow, $pagenow, $wpdb;
		
		// Handles the transition to 'publish' on edit.php
		if ( isset( $edit_flow ) && $pagenow == 'edit.php' && isset( $_REQUEST['bulk_edit'] ) ) {
			// For every post_id, set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt			
			if ( $_REQUEST['_status'] == 'publish' ) {
				$post_ids = array_map( 'intval', (array) $_REQUEST['post'] );
				foreach ( $post_ids as $post_id ) {		
					$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id, 'post_date_gmt' => '0000-00-00 00:00:00' ) );
				}
			}
		}
			
		// Handles the transition to 'publish' on post.php
		if ( isset( $edit_flow ) && $pagenow == 'post.php' && isset( $_POST['publish'] ) ) {
			// Set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt
			if ( isset( $_POST['post_ID'] ) ) {
				$post_id = (int) $_POST['post_ID'];
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id, 'post_date_gmt' => '0000-00-00 00:00:00' ) );
			}
		}
		
	}
	/**
	 * This is a hack! hack! hack! until core is fixed/better supports custom statuses
	 *	
	 * Normalize post_date_gmt if it isn't set to the past or the future
	 * @see Works around this limitation: http://core.trac.wordpress.org/browser/tags/3.2.1/wp-includes/post.php#L2506	
	 * @see Original thread: http://wordpress.org/support/topic/plugin-edit-flow-custom-statuses-create-timestamp-problem
	 * @see Core ticket: http://core.trac.wordpress.org/ticket/18362	
	 */
	function fix_custom_status_timestamp( $data ) {
		global $edit_flow, $pagenow;
		// Don't run this if Edit Flow isn't active, or we're on some other page
		if ( !isset( $edit_flow ) || $pagenow != 'post.php' || !isset( $_POST ) )
			return $data;
		$custom_statuses = get_terms( 'post_status', array( 'get' => 'all' ) );
		$status_slugs = array();
		foreach( $custom_statuses as $custom_status )
		    $status_slugs[] = $custom_status->slug;
		$ef_normalize_post_date_gmt = true;
		// We're only going to normalize the post_date_gmt if the user hasn't set a custom date in the metabox
		// and the current post_date_gmt isn't already future or past-ized
		foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {
			if ( !empty( $_POST['hidden_' . $timeunit] ) && (($_POST['hidden_' . $timeunit] != $_POST[$timeunit] ) || ( $_POST['hidden_' . $timeunit] != $_POST['cur_' . $timeunit] )) ) {
				$ef_normalize_post_date_gmt = false;
				break;
			}
		}
		if ( $ef_normalize_post_date_gmt )
			if ( in_array( $data['post_status'], $status_slugs ) )
				$data['post_date_gmt'] = '0000-00-00 00:00:00';
		return $data;
	}
	
	/**
	 * Generate a link to make the custom status the default
	 */
	function make_status_default_link( $id ) {
		$args = array(
			'configure' => 'custom-status',
			'action' => 'make_default',
			'term_id' => (int) $id,
			'nonce' => wp_create_nonce( 'custom-status-make-default' ),
		);
 		return add_query_arg( $args, EDIT_FLOW_SETTINGS_PAGE );
	}
		
} // END: class custom_status

} // END: !class_exists('EF_Custom_Status')

/**
 * ef_get_custom_status_post_count()
 * Get the total number of posts for any given status
 *
 * @param string|int $status Slug or ID of the status to look up
 * @return int $count
 */
function ef_get_custom_status_post_count( $status ) {
	global $wpdb;
	
	// Look up the status object if passed an int ID
	if ( is_int( $status ) ) {
		$status = get_term_by( 'term_id', $status, 'post_status' )->slug;
	}
	
	$query = $wpdb->prepare("SELECT count(ID) FROM $wpdb->posts WHERE post_status = %s", $status);
	$count = $wpdb->get_var($query);
	
	return $count;
} // END: ef_get_custom_status_post_count()


if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EF_Custom_Status_List_Table extends WP_List_Table
{
	
	var $callback_args;
	var $default_status;
	
	function __construct() {
		
		parent::__construct( array(
			'plural' => 'custom statuses',
			'singular' => 'custom status',
			'ajax' => true
		) );
		
	}
	
	function prepare_items() {
		global $edit_flow;		
		
		$per_page = 20;
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->items = $edit_flow->custom_status->get_custom_statuses();
		$total_items = count( $this->items );
		$this->default_status = $edit_flow->custom_status->ef_get_default_custom_status();
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page,
		) );		
		
	}	
	
	function current_action() {
		
		return parent::current_action();
	}
	
	function get_columns() {

		$columns = array(
			'custom_status'        => __( 'Custom Status', 'edit flow' ),
			'description' => __( 'Description', 'edit flow' ),
		);
		
		$post_types = get_post_types( '', 'objects' );
		foreach ( $post_types as $post_type )
			if ( post_type_supports( $post_type->name, 'ef_custom_statuses' ) )
				$columns[$post_type->name] = $post_type->label;

		return $columns;
	}
	
	function column_default( $item, $column_name ) {
		global $edit_flow;
		
		// Handle custom post counts for different post types
		$post_types = get_post_types( '', 'names' );
		if ( in_array( $column_name, $post_types ) ) {
		
			$post_count = wp_cache_get( "ef_custom_status_count_$column_name" );
			if ( false === $post_count ) {
				$posts = wp_count_posts( $column_name );
				$post_status = $item->slug;
				$post_count = $posts->$post_status;
				// @todo Cachify this
				//wp_cache_set( "ef_custom_status_count_$column_name", $post_count );
			}
			$output = sprintf( '<a href="%1$s">%2$s</a>', $edit_flow->helpers->filter_posts_link( $item->slug, $column_name ), $post_count );
			return $output;
		}
		
	}
	
	/**
	 * Handle the 'description' column for the table of custom statuses
	 */
	function column_description( $item ) {
		return esc_html( $item->description );
	}
	
	
	function column_custom_status( $item ) {
		global $edit_flow;
		
		$output = '<strong>' . $item->name . '</strong>';
			
		$actions = array();
		if ( $item->slug != $this->default_status->slug )
			$actions['make_default'] = sprintf( '<a href="%1$s">' . __( 'Make&nbsp;Default', 'edit-flow' ) . '</a>', $edit_flow->custom_status->make_status_default_link( $item->term_id ) );
		else
			$actions['make_default'] = __( 'Default', 'edit-flow' );
			
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __( 'Quick&nbsp;Edit' ) . '</a>';
		$output .= $this->row_actions( $actions, true );
		$output .= '<div class="hidden" id="inline_' . $item->term_id . '">';
		$output .= '<div class="name">' . $item->name . '</div>';
		$output .= '<div class="description">' . $item->description . '</div>';	
		$output .= '</div>';
		
		return $output;
			
	}
	
	function single_row( $item, $level = 0 ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		$this->level = $level;

		echo '<tr id="status-' . $item->term_id . '"' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}
	
	function inline_edit() {
?>
	<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
			<fieldset><div class="inline-edit-col">
				<h4><?php _e( 'Quick Edit' ); ?></h4>
				<label>
					<span class="title"><?php _ex( 'Name', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
				</label>
				<label>
					<span class="title"><?php _ex( 'Description', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="description" class="pdescription" value="" /></span>
				</label>
			</div></fieldset>
		<p class="inline-edit-save submit">
			<a accesskey="c" href="#inline-edit" title="<?php _e( 'Cancel' ); ?>" class="cancel button-secondary alignleft"><?php _e( 'Cancel' ); ?></a>
			<?php $update_text = __( 'Update Custom Status', 'edit-flow' ); ?>
			<a accesskey="s" href="#inline-edit" title="<?php echo esc_attr( $update_text ); ?>" class="save button-primary alignright"><?php echo $update_text; ?></a>
			<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
			<span class="error" style="display:none;"></span>
			<?php wp_nonce_field( 'custom-status-inline-edit-nonce', 'inline_edit', false ); ?>
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
	<?php
	}
		
}
