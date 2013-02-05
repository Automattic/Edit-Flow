<?php
/**
 * class EF_Custom_Status
 * Custom statuses make it simple to define the different stages in your publishing workflow.
 *
 * @todo for v0.7
 * - Improve the copy
 * - Thoroughly test what happens when the default post statuses 'Draft' and 'Pending Review' no longer exist
 * - Ensure all of the form processing uses our messages functionality
 */
if ( !class_exists( 'EF_Custom_Status' ) ) {

class EF_Custom_Status extends EF_Module {

	var $module;
	
	// This is taxonomy name used to store all our custom statuses
	const taxonomy_key = 'post_status';	
	
	/**
	 * Register the module with Edit Flow but don't do anything else
	 */
	function __construct() {
		global $edit_flow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
		// Register the module with Edit Flow
		$args = array(
			'title' => __( 'Custom Statuses', 'edit-flow' ),
			'short_description' => __( 'Create custom post statuses to define the stages of your workflow.', 'edit-flow' ),
			'extended_description' => __( 'Create your own post statuses to add structure your publishing workflow. You can change existing or add new ones anytime, and drag and drop to change their order.', 'edit-flow' ),
			'module_url' => $this->module_url,
			'img_url' => $this->module_url . 'lib/custom_status_s128.png',
			'slug' => 'custom-status',
			'default_options' => array(
				'enabled' => 'on',
				'default_status' => 'pitch',
				'always_show_dropdown' => 'off',
				'post_types' => array(
					'post' => 'on',
					'page' => 'on',
				),
			),
			'post_type_support' => 'ef_custom_statuses', // This has been plural in all of our docs
			'configure_page_cb' => 'print_configure_view',
			'configure_link_text' => __( 'Edit Statuses', 'edit-flow' ),
			'messages' => array(
				'status-added' => __( 'Post status created.', 'edit-flow' ),
				'status-missing' => __( "Post status doesn't exist.", 'edit-flow' ),
				'default-status-changed' => __( 'Default post status has been changed.', 'edit-flow'),
				'term-updated' => __( "Post status updated.", 'edit-flow' ),
				'status-deleted' => __( 'Post status deleted.', 'edit-flow' ),
				'status-position-updated' => __( "Status order updated.", 'edit-flow' ),
			),					
			'autoload' => false,
			'settings_help_tab' => array(
				'id' => 'ef-custom-status-overview',
				'title' => __('Overview', 'edit-flow'),
				'content' => __('<p>Edit Flow’s custom statuses allow you to define the most important stages of your editorial workflow. Out of the box, WordPress only offers “Draft” and “Pending Review” as post states. With custom statuses, you can create your own post states like “In Progress”, “Pitch”, or “Waiting for Edit” and keep or delete the originals. You can also drag and drop statuses to set the best order for your workflow.</p><p>Custom statuses are fully integrated into the rest of Edit Flow and the WordPress admin. On the calendar and story budget, you can filter your view to see only posts of a specific post state. Furthermore, email notifications can be sent to a specific group of users when a post changes state.</p>', 'edit-flow'),
				),
			'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/custom-statuses/">Custom Status Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
		);
		$this->module = EditFlow()->register_module( 'custom_status', $args );		
		
	}
		
	/**
	 * Initialize the EF_Custom_Status class if the module is active
	 */
	function init() {
		global $edit_flow;

		// Register custom statuses as a taxonomy
		$this->register_custom_statuses();
		
		// Register our settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Load CSS and JS resources that we probably need
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'no_js_notice' ) );
		add_action( 'admin_print_scripts', array( $this, 'post_admin_header' ) );
		
		// Methods for handling the actions of creating, making default, and deleting post stati
		add_action( 'admin_init', array( $this, 'handle_add_custom_status' ) );
		add_action( 'admin_init', array( $this, 'handle_edit_custom_status' ) );
		add_action( 'admin_init', array( $this, 'handle_make_default_custom_status' ) );
		add_action( 'admin_init', array( $this, 'handle_delete_custom_status' ) );
		add_action( 'wp_ajax_update_status_positions', array( $this, 'handle_ajax_update_status_positions' ) );
		add_action( 'wp_ajax_inline_save_status', array( $this, 'ajax_inline_save_status' ) );
		
		// Hook to add the status column to Manage Posts
		
		add_filter( 'manage_posts_columns', array( $this, '_filter_manage_posts_columns') );
		add_action( 'manage_posts_custom_column', array( $this, '_filter_manage_posts_custom_column') );
		
		// We need these for pages (http://core.trac.wordpress.org/browser/tags/3.3.1/wp-admin/includes/class-wp-posts-list-table.php#L283)
		add_filter( 'manage_pages_columns', array( $this, '_filter_manage_posts_columns' ) );
		add_action( 'manage_pages_custom_column', array( $this, '_filter_manage_posts_custom_column' ) );	
		
		// These seven-ish methods are hacks for fixing bugs in WordPress core
		add_action( 'admin_init', array( $this, 'check_timestamp_on_publish' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'fix_custom_status_timestamp' ) );
		add_action( 'wp_insert_post', array( $this, 'fix_post_name' ), 10, 2 );
		add_filter( 'editable_slug', array( $this, 'fix_editable_slug' ) );
		add_filter( 'preview_post_link', array( $this, 'fix_preview_link_part_one' ) );
		add_filter( 'post_link', array( $this, 'fix_preview_link_part_two' ), 10, 3 );
		add_filter( 'page_link', array( $this, 'fix_preview_link_part_two' ), 10, 3 );
		add_filter( 'post_type_link', array( $this, 'fix_preview_link_part_two' ), 10, 3 );
		add_filter( 'post_row_actions', array( $this, 'fix_post_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'fix_post_row_actions' ), 10, 2 );
		
	}
	
	/**
	 * Create the default set of custom statuses the first time the module is loaded
	 *
	 * @since 0.7
	 */
	function install() {
			
		$default_terms = array(
			array( 
				'term' => __( 'Pitch', 'edit-flow' ),
				'args' => array(
					'slug' => 'pitch',
					'description' => __( 'Idea proposed; waiting for acceptance.', 'edit-flow' ),
					'position' => 1,
				),
			),
			array(
				'term' => __( 'Assigned', 'edit-flow' ),
				'args' => array(
					'slug' => 'assigned',
					'description' => __( 'Post idea assigned to writer.', 'edit-flow' ),
					'position' => 2,
				),
			),
			array(
				'term' => __( 'In Progress', 'edit-flow' ),
				'args' => array(
					'slug' => 'in-progress',
					'description' => __( 'Writer is working on the post.', 'edit-flow' ),
					'position' => 3,
				),
			), 
			array(
				'term' => __( 'Draft', 'edit-flow' ),
				'args' => array(
					'slug' => 'draft',
					'description' => __( 'Post is a draft; not ready for review or publication.', 'edit-flow' ),
					'position' => 4,
				),
			),
			array(
				'term' => __( 'Pending Review' ),
				'args' => array(
					'slug' => 'pending',
					'description' => __( 'Post needs to be reviewed by an editor.', 'edit-flow' ),
					'position' => 5,
				),
			),
		);

		// Okay, now add the default statuses to the db if they don't already exist 
		foreach( $default_terms as $term ) {
			if( !term_exists( $term['term'] ) )
				$this->add_custom_status( $term['term'], $term['args'] );
		}
		
	}

	/**
	 * Upgrade our data in case we need to
	 *
	 * @since 0.7
	 */
	function upgrade( $previous_version ) {
		global $edit_flow;

		// Upgrade path to v0.7
		if ( version_compare( $previous_version, '0.7' , '<' ) ) {
			// Migrate dropdown visibility option
			if ( $dropdown_visible = get_option( 'edit_flow_status_dropdown_visible' ) )
				$dropdown_visible = 'on';
			else
				$dropdown_visible = 'off';
			$edit_flow->update_module_option( $this->module->name, 'always_show_dropdown', $dropdown_visible );
			delete_option( 'edit_flow_status_dropdown_visible' );
			// Migrate default status option
			if ( $default_status = get_option( 'edit_flow_custom_status_default_status' ) )
				$edit_flow->update_module_option( $this->module->name, 'default_status', $default_status );
			delete_option( 'edit_flow_custom_status_default_status' );

			// Technically we've run this code before so we don't want to auto-install new data
			$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
		}
		// Upgrade path to v0.7.4
		if ( version_compare( $previous_version, '0.7.4', '<' ) ) {
			// Custom status descriptions become base64_encoded, instead of maybe json_encoded.
			$this->upgrade_074_term_descriptions( self::taxonomy_key );
		}
		
	}
	
	/**
	 * Makes the call to register_post_status to register the user's custom statuses.
	 * Also unregisters draft and pending, in case the user doesn't want them.
	 */
	function register_custom_statuses() {
		global $wp_post_statuses;

		if ( $this->disable_custom_statuses_for_post_type() )
			return;

		// Register new taxonomy so that we can store all our fancy new custom statuses (or is it stati?)
		if ( !taxonomy_exists( self::taxonomy_key ) ) {
			$args = array(	'hierarchical' => false, 
							'update_count_callback' => '_update_post_term_count',
							'label' => false,
							'query_var' => false,
							'rewrite' => false,
							'show_ui' => false
					);
			register_taxonomy( self::taxonomy_key, 'post', $args );
		}
		
		if ( function_exists( 'register_post_status' ) ) {
			// Users can delete draft and pending statuses if they want, so let's get rid of them
			// They'll get re-added if the user hasn't "deleted" them
			unset( $wp_post_statuses[ 'draft' ] );
			unset( $wp_post_statuses[ 'pending' ] );
			
			$custom_statuses = $this->get_custom_statuses();
			
			// Unfortunately, register_post_status() doesn't accept a
			// post type argument, so we have to register the post
			// statuses for all post types. This results in
			// all post statuses for a post type appearing at the top
			// of manage posts if there is a post with the status
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
	 * Whether custom post statuses should be disabled for this post type.
	 * Used to stop custom statuses from being registered for post types that don't support them.
	 *
	 * @since 0.7.5
	 *
	 * @return bool
	 */
	function disable_custom_statuses_for_post_type( $post_type = null ) {
		global $pagenow;

		// Only allow deregistering on 'edit.php' and 'post.php'
		if ( ! in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ) ) )
			return false;

		if ( is_null( $post_type ) )
			$post_type = $this->get_current_post_type();

		if ( $post_type && ! in_array( $post_type, $this->get_post_types_for_module( $this->module ) ) )
			return true;

		return false;
	}
	
	/**
	 * Enqueue Javascript resources that we need in the admin:
	 * - Primary use of Javascript is to manipulate the post status dropdown on Edit Post and Manage Posts
	 * - jQuery Sortable plugin is used for drag and dropping custom statuses
	 * - We have other custom code for Quick Edit and JS niceties
	 */
	function action_admin_enqueue_scripts() {
		global $pagenow;

		if ( $this->disable_custom_statuses_for_post_type() )
			return;

		// Load Javascript we need to use on the configuration views (jQuery Sortable and Quick Edit)
		if ( $this->is_whitelisted_settings_view( $this->module->name ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );			
			wp_enqueue_script( 'edit-flow-custom-status-configure', $this->module_url . 'lib/custom-status-configure.js', array( 'jquery', 'jquery-ui-sortable', 'edit-flow-settings-js' ), EDIT_FLOW_VERSION, true );
		}
		
		// Custom javascript to modify the post status dropdown where it shows up
		if ( $this->is_whitelisted_page() ) {
			wp_enqueue_script( 'edit_flow-custom_status', $this->module_url . 'lib/custom-status.js', array( 'jquery','post' ), EDIT_FLOW_VERSION, true );
			wp_enqueue_style( 'edit_flow-custom_status', $this->module_url . 'lib/custom-status.css', false, EDIT_FLOW_VERSION, 'all' );
		}
	}
	
	/**
	 * Displays a notice to users if they have JS disabled
	 * Javascript is needed for custom statuses to be fully functional
	 */
	function no_js_notice() {
		if( $this->is_whitelisted_page() ) :
			?>
			<style type="text/css">
			/* Hide post status dropdown by default in case of JS issues **/
			label[for=post_status],
			#post-status-display,
			#post-status-select,
			#publish {
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
	 *
	 * @todo migrate this to the base module class
	 */
	function is_whitelisted_page() {
		global $pagenow;
		
		if ( !in_array( $this->get_current_post_type(), $this->get_post_types_for_module( $this->module ) ) )
			return false;

		$post_type_obj = get_post_type_object( $this->get_current_post_type() );
		
		if( ! current_user_can( $post_type_obj->cap->edit_posts ) )
			return false;
		
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

		if ( $this->disable_custom_statuses_for_post_type() )
			return;
		
		// Get current user
		get_currentuserinfo() ;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		if ( !empty( $post ) && $this->is_whitelisted_page() ) {
			
			$custom_statuses = $this->get_custom_statuses();
	
			// Get the status of the current post		
			if ( $post->ID == 0 || $post->post_status == 'auto-draft' || $pagenow == 'edit.php' ) {
				// TODO: check to make sure that the default exists
				$selected = $this->get_default_custom_status()->slug;

			} else {
				$selected = $post->post_status;
			}
			
			// Get the current post status name
			$selected_name = '';

			foreach ($custom_statuses as $status) {
				if ($status->slug == $selected) {
					$selected_name = $status->name;
				}
			}

			$custom_statuses = apply_filters( 'ef_custom_status_list', $custom_statuses, $post );			

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

 			$post_type_obj = get_post_type_object( $this->get_current_post_type() );
			
			// Now, let's print the JS vars
			?>
			<script type="text/javascript">
				var custom_statuses = <?php echo json_encode( $all_statuses ); ?>;
				var ef_text_no_change = '<?php echo esc_js( __( "&mdash; No Change &mdash;" ) ); ?>';
				var ef_default_custom_status = '<?php echo esc_js( $this->get_default_custom_status()->slug ); ?>';
				var current_status = '<?php echo esc_js( $selected ); ?>';
				var current_status_name = '<?php echo esc_js( $selected_name ); ?>';
				var status_dropdown_visible = <?php echo esc_js( $always_show_dropdown ); ?>;
				var current_user_can_publish_posts = <?php echo current_user_can( $post_type_obj->cap->publish_posts ) ? 1 : 0; ?>;
				var current_user_can_edit_published_posts = <?php echo current_user_can( $post_type_obj->cap->edit_published_posts ) ? 1 : 0; ?>;
			</script>
			
			<?php

		}
		
	}
	
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
	 * @return array|WP_Error $response The Term ID and Term Taxonomy ID
	 */
	function add_custom_status( $term, $args = array() ) {
		$slug = ( ! empty( $args['slug'] ) ) ? $args['slug'] : sanitize_title( $term );
		unset( $args['slug'] );
		$encoded_description = $this->get_encoded_description( $args );
		$response = wp_insert_term( $term, self::taxonomy_key, array( 'slug' => $slug, 'description' => $encoded_description ) );
		return $response;
		
	}
	
	/**
	 * Update an existing custom status 
	 *
	 * @param int @status_id ID for the status
	 * @param array $args Any arguments to be updated
	 * @return object $updated_status Newly updated status object
	 */
	function update_custom_status( $status_id, $args = array() ) {
		global $edit_flow;
		
		$old_status = $this->get_custom_status_by( 'id', $status_id );
		if ( !$old_status || is_wp_error( $old_status ) )
			return new WP_Error( 'invalid', __( "Custom status doesn't exist.", 'edit-flow' ) );		
		
		// If the name was changed, we need to change the slug
		if ( isset( $args['name'] ) && $args['name'] != $old_status->name )
			$args['slug'] = sanitize_title( $args['name'] );

		// Reassign posts to new status slug if the slug changed and isn't restricted
		if ( isset( $args['slug'] ) && $args['slug'] != $old_status->slug && !$this->is_restricted_status( $old_status->slug ) ) {
			$new_status = $args['slug'];
			$this->reassign_post_status( $old_status->slug, $new_status );
			
			$default_status = $this->get_default_custom_status()->slug;			
			if ( $old_status->slug == $default_status )
				$edit_flow->update_module_option( $this->module->name, 'default_status', $new_status );
		}
		// We're encoding metadata that isn't supported by default in the term's description field
		$args_to_encode = array();
		$args_to_encode['description'] = ( isset( $args['description'] ) ) ? $args['description'] : $old_status->description;
		$args_to_encode['position'] = ( isset( $args['position'] ) ) ? $args['position'] : $old_status->position;
		$encoded_description = $this->get_encoded_description( $args_to_encode );
		$args['description'] = $encoded_description;

		$updated_status_array = wp_update_term( $status_id, self::taxonomy_key, $args );
		$updated_status = $this->get_custom_status_by( 'id', $updated_status_array['term_id'] );

		return $updated_status;
		
	}
	
	/**
	 * Deletes a custom status from the wp_terms table.
	 * 
	 * Partly a wrapper for the wp_delete_term function.
	 * BUT, also reassigns posts that currently have the deleted status assigned.  
	 */
	function delete_custom_status( $status_id, $args = array(), $reassign = '' ) {
		global $edit_flow;
		// Reassign posts to alternate status

		// Get slug for the old status
		$old_status = $this->get_custom_status_by( 'id', $status_id )->slug;

		if ( $reassign == $old_status )
			return new WP_Error( 'invalid', __( 'Cannot reassign to the status you want to delete', 'edit-flow' ) );
		
		if( !$this->is_restricted_status( $old_status ) ) {
			$default_status = $this->get_default_custom_status()->slug;
			// If new status in $reassign, use that for all posts of the old_status
			if( !empty( $reassign ) )
				$new_status = $this->get_custom_status_by( 'id', $reassign )->slug;
			else
				$new_status = $default_status;
			if ( $old_status == $default_status && $this->get_custom_status_by( 'slug', 'draft' ) ) { // Deleting default status
				$new_status = 'draft';
				$edit_flow->update_module_option( $this->module->name, 'default_status', $new_status );
			}
			
			$this->reassign_post_status( $old_status, $new_status );
			
			return wp_delete_term( $status_id, self::taxonomy_key, $args );
		} else
			return new WP_Error( 'restricted', __( 'Restricted status ', 'edit-flow' ) . '(' . $this->get_custom_status_by( 'id', $status_id )->name . ')' );
			
	}

	/**
	 * Get all custom statuses as an ordered array
	 *
	 * @param array|string $statuses
	 * @param array $args
	 * @return array $statuses All of the statuses
	 */
	function get_custom_statuses( $args = array() ) {
		global $wp_post_statuses;

		// If this post type doesn't support custom statuses, we should return WP
		// default in our format.
		if ( $this->disable_custom_statuses_for_post_type() ) {
			$draft = $wp_post_statuses['draft'];
			$draft->slug = 'draft';
			$draft->position = 1;

			$pending = $wp_post_statuses['pending'];
			$pending->slug = 'pending';
			$pending->position = 2;
			return array(
					$draft,
					$pending
				);
		}
		
		$default = array(
			'hide_empty' => false,
		);
		$args = array_merge( $default, $args );
		$statuses = get_terms( self::taxonomy_key, $args );
		// Expand and order the statuses		
		$ordered_statuses = array();
		$hold_to_end = array();
		foreach ( $statuses as $key => $status ) {
			// Unencode and set all of our psuedo term meta because we need the position if it exists
			$unencoded_description = $this->get_unencoded_description( $status->description );
			if ( is_array( $unencoded_description ) ) {
				foreach( $unencoded_description as $key => $value ) {
					$status->$key = $value;
				}
			}
			// We require the position key later on (e.g. management table)
			if ( !isset( $status->position ) )
				$status->position = false;
			// Only add the status to the ordered array if it has a set position and doesn't conflict with another key
			// Otherwise, hold it for later	
			if ( $status->position && !array_key_exists( $status->position, $ordered_statuses ) ) {
				$ordered_statuses[(int)$status->position] = $status;
			} else {
				$hold_to_end[] = $status;
			}
		}
		// Sort the items numerically by key
		ksort( $ordered_statuses, SORT_NUMERIC );
		// Append all of the statuses that didn't have an existing position
		foreach( $hold_to_end as $unpositioned_status )
			$ordered_statuses[] = $unpositioned_status;
		return $ordered_statuses;
	}
	
	/**
	 * Returns the a single status object based on ID, title, or slug
	 *
	 * @param string|int $string_or_int The status to search for, either by slug or ID
	 * @return object|WP_Error $status The object for the matching status
	 */
	function get_custom_status_by( $field, $value ) {
		
		$status = get_term_by( $field, $value, self::taxonomy_key );
		if ( !$status || is_wp_error( $status ) )
			return $status;
		// Unencode and set all of our psuedo term meta because we need the position if it exists
		$status->position = false;
		$unencoded_description = $this->get_unencoded_description( $status->description );
		if ( is_array( $unencoded_description ) ) {
			foreach( $unencoded_description as $key => $value ) {
				$status->$key = $value;
			}
		}
		return $status;
		
	}

	/**
	 * Get the term object for the default custom post status
	 *
	 * @return object $default_status Default post status object
	 */
	function get_default_custom_status() {
		$default_status = $this->get_custom_status_by( 'slug', $this->module->options->default_status );
		if ( ! $default_status )
			$default_status = array_shift( $this->get_custom_statuses() );
		return $default_status;
		
	}
	
	/**
	 * Assign new statuses to posts using value provided or the default
	 *
	 * @param string $old_status Slug for the old status
	 * @param string $new_status Slug for the new status
	 */
	function reassign_post_status( $old_status, $new_status = '' ) {
		global $wpdb;
		
		if ( empty( $new_status ) )
			$new_status = $this->get_default_custom_status()->slug;
		
		// Make the database call
		$result = $wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'post_status' => $old_status ), array( '%s' ));
	}
	
	/**
	 * Insert new column header for post status after the title column
	 *
	 * @param array $posts_columns Columns currently shown on the Edit Posts screen
	 * @return array Same array as the input array with a "status" column added after the "title" column
	 */
	function _filter_manage_posts_columns( $posts_columns ) {
		// Return immediately if the supplied parameter isn't an array (which shouldn't happen in practice?)
		// http://wordpress.org/support/topic/plugin-edit-flow-bug-shows-2-drafts-when-there-are-none-leads-to-error-messages
		if ( !is_array( $posts_columns ) )
			return $posts_columns;
		
		// Only do it for the post types this module is activated for
		if ( !in_array( $this->get_current_post_type(), $this->get_post_types_for_module( $this->module ) ) )
			return $posts_columns;

		$result = array();
		foreach ( $posts_columns as $key => $value ) {
			if ($key == 'title') {
				$result[$key] = $value;
				$result['status'] = __('Status', 'edit-flow');
			} else $result[$key] = $value;
		}
		return $result;
		
	}
	
	/**
	 * Adds a Post's status to its row on the Edit page
	 * 
	 * @param string $column_name
	 **/
	function _filter_manage_posts_custom_column( $column_name ) {
		
		if ( $column_name == 'status' ) {
			global $post;
			echo $this->get_post_status_friendly_name( $post->post_status );
		}
		
	}
	
	
	/**
	 * Determines whether the slug indicated belongs to a restricted status or not
	 *
	 * @param string $slug Slug of the status 
	 * @return bool $restricted True if restricted, false if not
	 */
	function is_restricted_status( $slug ) {

		switch( $slug ) {
			case 'publish':
			case 'private':
			case 'future':
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
		
	}
	
	/**
	 * Handles a form's POST request to add a custom status
	 *
	 * @since 0.7
	 */
	function handle_add_custom_status() {
		
		// Check that the current POST request is our POST request
		if ( !isset( $_POST['submit'], $_GET['page'], $_POST['action'] ) 
			|| $_GET['page'] != $this->module->settings_slug || $_POST['action'] != 'add-new' )
				return; 
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'custom-status-add-nonce' ) )
			wp_die( $this->module->messages['nonce-failed'] );
		
		// Validate and sanitize the form data
		$status_name = strip_tags( trim( $_POST['status_name'] ) );
		$status_slug = sanitize_title( $status_name );
		$status_description = strip_tags( trim( $_POST['status_description'] ) );
			
		/**
		 * Form validation
		 * - Name is required and can't conflict with an existing name or slug
		 * - Description is optional
		 */
		$_REQUEST['form-errors'] = array();	
		// Check if name field was filled in
		if( empty( $status_name ) )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the status', 'edit-flow' );
		// Check that the name isn't numeric
		if ( (int)$status_name != 0 )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' );
		// Check that the status name doesn't exceed 20 chars
		if ( strlen( $status_name ) > 20 )
			$_REQUEST['form-errors']['name'] = __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
		// Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
		if ( term_exists( $status_slug ) )
			$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' );
		// Check to make sure the name is not restricted
		if ( $this->is_restricted_status( strtolower( $status_slug ) ) )
			$_REQUEST['form-errors']['name'] = __( 'Status name is restricted. Please choose another name.', 'edit-flow' );
		
		// If there were any form errors, kick out and return them
		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}
		
		// Try to add the status
		$status_args = array( 
			'description' => $status_description,
			'slug' => $status_slug,
		);
		$return = $this->add_custom_status( $status_name, $status_args );
		if ( is_wp_error( $return ) )
			wp_die( __( 'Could not add status: ', 'edit-flow' ) . $return->get_error_message() );
		// Redirect if successful
		$redirect_url = $this->get_link( array( 'message' => 'status-added' ) );
		wp_redirect( $redirect_url );
		exit;
		
	}
	
	/**
	 * Handles a POST request to edit an custom status
	 *
	 * @since 0.7
	 */
	function handle_edit_custom_status() {
		if ( !isset( $_POST['submit'], $_GET['page'], $_GET['action'], $_GET['term-id'] ) 
			|| $_GET['page'] != $this->module->settings_slug || $_GET['action'] != 'edit-status' )
				return; 
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'edit-status' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_options' ) )
			wp_die( $this->module->messages['invalid-permissions'] );			
		
		if ( !$existing_status = $this->get_custom_status_by( 'id', (int)$_GET['term-id'] ) )
			wp_die( $this->module->messsage['term-error'] );			
		
		$name = strip_tags( trim( $_POST['name'] ) );
		$description = strip_tags( trim( $_POST['description'] ) );
			
		/**
		 * Form validation for editing custom status
		 *
		 * Details
		 * - 'name' is a required field and can't conflict with existing name or slug
		 * - 'description' is optional
		 */
		$_REQUEST['form-errors'] = array();	
		// Check if name field was filled in
		if( empty( $name ) )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the status', 'edit-flow' );
		// Check that the name isn't numeric
		if ( is_numeric( $name ) )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' );
		// Check that the status name doesn't exceed 20 chars
		if ( strlen( $name ) > 20 )
			$_REQUEST['form-errors']['name'] = __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
		// Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
		$term_exists = term_exists( sanitize_title( $name ) );
		if ( $term_exists && $term_exists != $existing_status->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' );
		// Check to make sure the status doesn't already exist
		$search_status = $this->get_custom_status_by( 'slug', sanitize_title( $name ) );
		if ( $search_status && $search_status->term_id != $existing_status->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing status. Please choose another.', 'edit-flow' );
		// Check to make sure the name is not restricted
		if ( $this->is_restricted_status( strtolower( sanitize_title( $name ) ) ) )
			$_REQUEST['form-errors']['name'] = __( 'Status name is restricted. Please choose another name.', 'edit-flow' );
	
		// Kick out if there are any errors
		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}
		
		// Try to add the new post status
		$args = array(
			'name' => $name,
			'slug' => sanitize_title( $name ),	
			'description' => $description,
		);
		$return = $this->update_custom_status( $existing_status->term_id, $args );		
		if ( is_wp_error( $return ) )
			wp_die( __( 'Error updating post status.', 'edit-flow' ) );
		
		$redirect_url = $this->get_link( array( 'message' => 'status-updated' ) );
		wp_redirect( $redirect_url );
		exit;
	}
	
	/**
	 * Handles a GET request to make the identified status default
	 *
	 * @since 0.7
	 */
	function handle_make_default_custom_status() {
		global $edit_flow;
		
		// Check that the current GET request is our GET request		
		if ( !isset( $_GET['page'], $_GET['action'], $_GET['term-id'], $_GET['nonce'] )
			|| $_GET['page'] != $this->module->settings_slug || $_GET['action'] != 'make-default' )
			return;
		
		// Check for proper nonce
		if ( !wp_verify_nonce( $_GET['nonce'], 'make-default' ) )
			wp_die( __( 'Invalid nonce for submission.', 'edit-flow' ) );
		
		// Only allow users with the proper caps
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
		
		$term_id = (int)$_GET['term-id'];		
		$term = $this->get_custom_status_by( 'id', $term_id );
		if ( is_object( $term ) ) {
			$edit_flow->update_module_option( $this->module->name, 'default_status', $term->slug );
			// @todo How do we want to handle users who click the link from "Add New Status"
			$redirect_url = $this->get_link( array( 'message' => 'default-status-changed' ) );
			wp_redirect( $redirect_url );
			exit;
		} else {
			wp_die( __( 'Status doesn&#39;t exist.', 'edit-flow' ) );
		}
		
	}
	
	/**
	 * Handles a GET request to delete a specific term
	 *
	 * @since 0.7
	 */
	function handle_delete_custom_status() {
		
		// Check that this GET request is our GET request
		if ( !isset( $_GET['page'], $_GET['action'], $_GET['term-id'], $_GET['nonce'] )
			|| $_GET['page'] != $this->module->settings_slug || $_GET['action'] != 'delete-status' )
			return;
		
		// Check for proper nonce
		if ( !wp_verify_nonce( $_GET['nonce'], 'delete-status' ) )
			wp_die( __( 'Invalid nonce for submission.', 'edit-flow' ) );
		
		// Only allow users with the proper caps
		if ( !current_user_can( 'manage_options' ) )
			wp_die( __( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
		
		// Check to make sure the status isn't already deleted
		$term_id = (int)$_GET['term-id'];
		$term = $this->get_custom_status_by( 'id', $term_id );		
		if( !$term )
 			wp_die( __( 'Status does not exist.', 'edit-flow' ) );

		// Don't allow deletion of default status
		if ( $term->slug == $this->get_default_custom_status()->slug )
			wp_die( __( 'Cannot delete default status.', 'edit-flow' ) );
		
		$return = $this->delete_custom_status( $term_id );
		if ( is_wp_error( $return ) )
			wp_die( __( 'Could not delete the status: ', 'edit-flow' ) . $return->get_error_message() );
		
		$redirect_url = $this->get_link( array( 'message' => 'status-deleted' ) );
		wp_redirect( $redirect_url );
		exit;
		
	}
	
	/**
	 * Generate a link to one of the custom status actions
	 *
	 * @since 0.7
	 *
	 * @param array $args (optional) Action and any query args to add to the URL
	 * @return string $link Direct link to complete the action
	 */
	function get_link( $args = array() ) {
		if ( !isset( $args['action'] ) )
			$args['action'] = '';
		if ( !isset( $args['page'] ) )
			$args['page'] = $this->module->settings_slug;
		// Add other things we may need depending on the action
		switch( $args['action'] ) {
			case 'make-default':
			case 'delete-status':
				$args['nonce'] = wp_create_nonce( $args['action'] );
				break;
			default:
				break;
		}
		return add_query_arg( $args, get_admin_url( null, 'admin.php' ) );
	}	
	
	/**
	 * Handle an ajax request to update the order of custom statuses
	 *
	 * @since 0.7
	 */
	function handle_ajax_update_status_positions() {
		
		if ( !wp_verify_nonce( $_POST['custom_status_sortable_nonce'], 'custom-status-sortable' ) )
			$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
		
		if ( !current_user_can( 'manage_options') )
			$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
		
		if ( !isset( $_POST['status_positions'] ) || !is_array( $_POST['status_positions'] ) )
			$this->print_ajax_response( 'error', __( 'Terms not set.', 'edit-flow' ) );
		
		// Update each custom status with its new position
		foreach ( $_POST['status_positions'] as $position => $term_id ) {
			
			// Have to add 1 to the position because the index started with zero
			$args = array(
				'position' => (int)$position + 1,
			);
			$return = $this->update_custom_status( (int)$term_id, $args );
			// @todo check that this was a valid return
		}
		$this->print_ajax_response( 'success', $this->module->messages['status-position-updated'] );	
	}
	
	/**
	 * Handle an Inline Edit POST request to update status values
	 *
	 * @since 0.7
	 */
	function ajax_inline_save_status() {
		global $edit_flow;
		
		if ( !wp_verify_nonce( $_POST['inline_edit'], 'custom-status-inline-edit-nonce' ) )
			die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_options') )
			die( $this->module->messages['invalid-permissions'] );		
		
		$term_id = (int) $_POST['status_id'];
		$status_name = strip_tags( trim( $_POST['name'] ) );
		$status_slug = sanitize_title( $_POST['name'] );		
		$status_description = strip_tags( trim( $_POST['description'] ) );		
		
		// Check if name field was filled in
		if ( empty( $status_name ) ) {
			$change_error = new WP_Error( 'invalid', __( 'Please enter a name for the status.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}

		// Check that the name isn't numeric
		if( is_numeric( $status_name) ) {
			$change_error = new WP_Error( 'invalid', __( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}

		// Check that the status name doesn't exceed 20 chars
		if ( strlen( $status_name ) > 20 ) {
			$change_error = new WP_Error( 'invalid', __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// Check to make sure the name is not restricted
		if ( $edit_flow->custom_status->is_restricted_status( strtolower( $status_name ) ) ) {
			$change_error = new WP_Error( 'invalid', __( 'Status name is restricted. Please chose another name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// Check to make sure the status doesn't already exist
		if ( $this->get_custom_status_by( 'slug', $status_slug ) && ( $this->get_custom_status_by( 'id', $term_id )->slug != $status_slug ) ) {
			$change_error = new WP_Error( 'invalid', __( 'Status already exists. Please choose another name.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// Check to make sure the status doesn't already exist as another term because otherwise we'd get a fatal error
		$term_exists = term_exists( sanitize_title( $status_name ) );
		if ( $term_exists && $term_exists != $term_id ) {
			$change_error = new WP_Error( 'invalid', __( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' ) );
			die( $change_error->get_error_message() );
		}
		
		// get status_name & status_description
		$args = array(
			'name' => $status_name,
			'description' => $status_description,
			'slug' => $status_slug,
		);		
		$return = $this->update_custom_status( $term_id, $args );
		if( !is_wp_error( $return ) ) {	
			set_current_screen( 'edit-custom-status' );					
			$wp_list_table = new EF_Custom_Status_List_Table();
			$wp_list_table->prepare_items();
			echo $wp_list_table->single_row( $return );
			die();
		} else {
			$change_error = new WP_Error( 'invalid', sprintf( __( 'Could not update the status: <strong>%s</strong>', 'edit-flow' ), $status_name ) );
			die( $change_error->get_error_message() );
		}
	
	}
	
	/**
	 * Register settings for notifications so we can partially use the Settings API
	 * (We use the Settings API for form generation, but not saving)
	 * 
	 * @since 0.7
	 */
	function register_settings() {
		
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Use on these post types:', 'edit-flow' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'always_show_dropdown', __( 'Always show dropdown:', 'edit-flow' ), array( $this, 'settings_always_show_dropdown_option'), $this->module->options_group_name, $this->module->options_group_name . '_general' );

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
	
	/**
	 * Validate input from the end user
	 * 
	 * @since 0.7
	 */
	function settings_validate( $new_options ) {
		
		// Whitelist validation for the post type options
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		// Whitelist validation for the 'always_show_dropdown' optoins
		if ( !isset( $new_options['always_show_dropdown'] ) || $new_options['always_show_dropdown'] != 'on' )
			$new_options['always_show_dropdown'] = 'off';		
		
		return $new_options;
	}
	
	/**
	 * Primary configuration page for custom status class.
	 * Shows form to add new custom statuses on the left and a
	 * WP_List_Table with the custom status terms on the right
	 */
	function print_configure_view() {
		global $edit_flow;
		
		/** Full width view for editing a custom status **/
		if ( isset( $_GET['action'], $_GET['term-id'] ) && $_GET['action'] == 'edit-status' ): ?>
		<?php
			// Check whether the term exists
			$term_id = (int)$_GET['term-id'];
			$status = $this->get_custom_status_by( 'id', $term_id  );
			if ( !$status ) {
				echo '<div class="error"><p>' . $this->module->messages['status-missing'] . '</p></div>';
				return; 
			}		
			$edit_status_link = $this->get_link( array( 'action' => 'edit-status', 'term-id' => $term_id ) );
			
			$name = ( isset( $_POST['name'] ) ) ? stripslashes( $_POST['name'] ) : $status->name;
			$description = ( isset( $_POST['description'] ) ) ? stripslashes( strip_tags( $_POST['description'] ) ) : $status->description;
		?>
		
		<div id="ajax-response"></div>
		<form method="post" action="<?php echo esc_attr( $edit_status_link ); ?>" >
		<input type="hidden" name="term-id" value="<?php echo esc_attr( $term_id ); ?>" />
		<?php
			wp_original_referer_field();
			wp_nonce_field( 'edit-status' );
		?>
		<table class="form-table">
			<tr class="form-field form-required">
				<th scope="row" valign="top"><label for="name"><?php _e( 'Custom Status', 'edit-flow' ); ?></label></th>
				<td><input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" size="40" aria-required="true" />
				<?php $edit_flow->settings->helper_print_error_or_description( 'name', __( 'The name is used to identify the status. (Max: 20 characters)', 'edit-flow' ) ); ?>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top"><?php _e( 'Slug', 'edit-flow' ); ?></th>
				<td>
					<input type="text" disabled="disabled" value="<?php echo esc_attr( $status->slug ); ?>" />
					<?php $edit_flow->settings->helper_print_error_or_description( 'slug', __( 'The slug is the unique ID for the status and is changed when the name is changed.', 'edit-flow' ) ); ?>
				</td>
			</tr>			
			<tr class="form-field">
				<th scope="row" valign="top"><label for="description"><?php _e( 'Description', 'edit-flow' ); ?></label></th>
				<td>
					<textarea name="description" id="description" rows="5" cols="50" style="width: 97%;"><?php echo esc_textarea( $description ); ?></textarea>
				<?php $edit_flow->settings->helper_print_error_or_description( 'description', __( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'edit-flow' ) ); ?>
				</td>
			</tr>
		</table>
		<p class="submit">
		<?php submit_button( __( 'Update Status', 'edit-flow' ), 'primary', 'submit', false ); ?>
		<a class="cancel-settings-link" href="<?php echo esc_url( $this->get_link() ); ?>"><?php _e( 'Cancel', 'edit-flow' ); ?></a>
		</p>
		</form>
		
		<?php else: ?>
		<?php
		$wp_list_table = new EF_Custom_Status_List_Table();
		$wp_list_table->prepare_items();
		?>
		<script type="text/javascript">
			var ef_confirm_delete_status_string = "<?php echo esc_js( __( 'Are you sure you want to delete the post status? All posts with this status will be assigned to the default status.', 'edit-flow' ) ); ?>";
		</script>
			<div id="col-right">
				<div class="col-wrap">
					<?php $wp_list_table->display(); ?>
					<?php wp_nonce_field( 'custom-status-sortable', 'custom-status-sortable' ); ?>
					<p class="description" style="padding-top:10px;"><?php _e( 'Deleting a post status will assign all posts to the default post status.', 'edit-flow' ); ?></p>
				</div>
			</div>
			<div id="col-left">
				<div class="col-wrap">	
				<div class="form-wrap">
				<h3 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( $this->get_link() ); ?>" class="nav-tab<?php if ( !isset( $_GET['action'] ) || $_GET['action'] != 'change-options' ) echo ' nav-tab-active'; ?>"><?php _e( 'Add New', 'edit-flow' ); ?></a>
					<a href="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" class="nav-tab<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'change-options' ) echo ' nav-tab-active'; ?>"><?php _e( 'Options', 'edit-flow' ); ?></a>
				</h3>
				<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'change-options' ): ?>
				<form class="basic-settings" action="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" method="post">
					<?php settings_fields( $this->module->options_group_name ); ?>
					<?php do_settings_sections( $this->module->options_group_name ); ?>	
					<?php echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />'; ?>
					<?php submit_button(); ?>
				</form>
				<?php else: ?>
				<?php /** Custom form for adding a new Custom Status term **/ ?>
					<form class="add:the-list:" action="<?php echo esc_url( $this->get_link() ); ?>" method="post" id="addstatus" name="addstatus">
					<div class="form-field form-required">
						<label for="status_name"><?php _e( 'Name', 'edit-flow' ); ?></label>
						<input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php if ( !empty( $_POST['status_name'] ) ) echo esc_attr( $_POST['status_name'] ) ?>" />
						<?php $edit_flow->settings->helper_print_error_or_description( 'name', __( 'The name is used to identify the status. (Max: 20 characters)', 'edit-flow' ) ); ?>
					</div>
					<div class="form-field">
						<label for="status_description"><?php _e( 'Description', 'edit-flow' ); ?></label>
						<textarea cols="40" rows="5" id="status_description" name="status_description"><?php if ( !empty( $_POST['status_description'] ) ) echo esc_textarea( $_POST['status_description'] ) ?></textarea>
						<?php $edit_flow->settings->helper_print_error_or_description( 'description', __( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'edit-flow' ) ); ?>
					</div>
					<?php wp_nonce_field( 'custom-status-add-nonce' ); ?>
					<?php echo '<input id="action" name="action" type="hidden" value="add-new" />'; ?>					
					<p class="submit"><?php submit_button( __( 'Add New Status', 'edit-flow' ), 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo EDIT_FLOW_SETTINGS_PAGE; ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>
					</form>
				<?php endif; ?>
				</div>
			</div>
			</div>
			<?php $wp_list_table->inline_edit(); ?>
			<?php endif; ?>
		<?php
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

		if ( $this->disable_custom_statuses_for_post_type() )
			return;
		
		// Handles the transition to 'publish' on edit.php
		if ( isset( $edit_flow ) && $pagenow == 'edit.php' && isset( $_REQUEST['bulk_edit'] ) ) {
			// For every post_id, set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt			
			if ( $_REQUEST['_status'] == 'publish' ) {
				$post_ids = array_map( 'intval', (array) $_REQUEST['post'] );
				foreach ( $post_ids as $post_id ) {		
					$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id, 'post_date_gmt' => '0000-00-00 00:00:00' ) );
					clean_post_cache( $post_id );
				}
			}
		}
			
		// Handles the transition to 'publish' on post.php
		if ( isset( $edit_flow ) && $pagenow == 'post.php' && isset( $_POST['publish'] ) ) {
			// Set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt
			if ( isset( $_POST['post_ID'] ) ) {
				$post_id = (int) $_POST['post_ID'];
				$ret = $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $post_id, 'post_date_gmt' => '0000-00-00 00:00:00' ) );
				clean_post_cache( $post_id );
				foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {
					if ( !empty( $_POST['hidden_' . $timeunit] ) && $_POST['hidden_' . $timeunit] != $_POST[$timeunit] ) {
						$edit_date = '1';
						break;
					}
				}
				if ( $ret && empty( $edit_date ) ) {
					add_filter( 'pre_post_date', array( $this, 'helper_timestamp_hack' ) );
					add_filter( 'pre_post_date_gmt', array( $this, 'helper_timestamp_hack' ) );
				}
			}
		}
		
	}
	/**
	 * PHP < 5.3.x doesn't support anonymous functions
	 * This helper is only used for the check_timestamp_on_publish method above
	 *
	 * @since 0.7.3
	 */
	function helper_timestamp_hack() {
		return ( 'pre_post_date' == current_filter() ) ? current_time('mysql') : '';
	}
	/**
	 * This is a hack! hack! hack! until core is fixed/better supports custom statuses
	 *
	 * @since 0.6.5
	 *	
	 * Normalize post_date_gmt if it isn't set to the past or the future
	 * @see Works around this limitation: http://core.trac.wordpress.org/browser/tags/3.2.1/wp-includes/post.php#L2506	
	 * @see Original thread: http://wordpress.org/support/topic/plugin-edit-flow-custom-statuses-create-timestamp-problem
	 * @see Core ticket: http://core.trac.wordpress.org/ticket/18362	
	 */
	function fix_custom_status_timestamp( $data ) {
		global $edit_flow;
		// Don't run this if Edit Flow isn't active, or we're on some other page
		if ( $this->disable_custom_statuses_for_post_type()
			|| ! isset( $edit_flow )
			|| ! isset( $_POST ) )
			return $data;
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
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
	 * Another hack! hack! hack! until core better supports custom statuses
	 *
	 * @since 0.7.4 
	 *
	 * Keep the post_name value empty for posts with custom statuses
	 * Unless they've set it customly
	 * @see https://github.com/danielbachhuber/Edit-Flow/issues/123
	 * @see http://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/post.php#L2530
	 * @see http://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/post.php#L2646
	 */
	public function fix_post_name( $post_id, $post ) {
		global $pagenow;

		// Only modify if we're using a pre-publish status on a supported custom post type
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
		if ( 'post.php' != $pagenow 
			|| ! in_array( $post->post_status, $status_slugs ) 
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) )
			return;

		// The slug has been set by the meta box
		if ( ! empty( $_POST['post_name'] ) )
			return;

		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_name' => '' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}
	/**
	 * Another hack! hack! hack! until core better supports custom statuses
	 *
	 * @since 0.7.4
	 *
	 * If the post_name for the object is empty, set it to sanitize_title() of post_title
	 * However, we only want to do this the first time it's called on the page (slug editor)
	 * and not in the post metabox, because the latter will set the value
	 */
	public function fix_editable_slug( $slug ) {
		global $pagenow;

		// If 'all_admin_notices' hasn't happened yet, we're not even close to the UI yet.
		if ( ! did_action( 'all_admin_notices' ) )
			return $slug;

		// The AJAX-y edit your slug UI happens right below the title,
		// so we can remove the filter after it's done.
		if ( did_action( 'edit_form_after_title' ) ) {
			remove_filter( current_filter(), array( $this, __FUNCTION__ ) );
			return $slug;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			return $slug;

		$post = get_post( get_the_ID() );

		// Only modify if we're using a pre-publish status on a supported custom post type
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
		if ( ! $post
			|| 'post.php' != $pagenow
			|| ! in_array( $post->post_status, $status_slugs ) 
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) )
			|| ! empty( $post->post_name ) )
			return $slug;

		// For hierarchical post types, we only want to modify the last time 'editable_slug' filter runs.
		$ptype = get_post_type_object( $post->post_type );
		if ( $ptype->hierarchical ) {
			static $i;
			$i++;
			if ( $i > 1 )
				return sanitize_title( $post->post_title );
			else
				return $slug;
		}

		$slug = sanitize_title( $post->post_title );

		return $slug;

	}

	/**
	 * Another hack! hack! hack! until core better supports custom statuses
	 *
	 * @since 0.7.4
	 *
	 * The preview link for an unpublished post should always be ?p=
	 */
	public function fix_preview_link_part_one( $preview_link ) {
		global $pagenow;

		$post = get_post( get_the_ID() );

		// Only modify if we're using a pre-publish status on a supported custom post type
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
		if ( ! $post
			|| ! is_admin()
			|| 'post.php' != $pagenow
			|| ! in_array( $post->post_status, $status_slugs ) 
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) )
			return $preview_link;

		if ( 'page' == $post->post_type ) {
			$args = array(
					'page_id'    => $post->ID,
				);
		} else if ( 'post' == $post->post_type ) {
			$args = array(
					'p'          => $post->ID,
				);
		} else {
			$args = array(
					'p'          => $post->ID,
					'post_type'  => $post->post_type,
				);
		}
		$args['preview'] = 'true';
		$preview_link = add_query_arg( $args, home_url() );
		return $preview_link;
	}

	/**
	 * Another hack! hack! hack! until core better supports custom statuses
	 *
	 * @since 0.7.4
	 *
	 * The preview link for an unpublished post should always be ?p=
	 * The code used to trigger a post preview doesn't also apply the 'preview_post_link' filter
	 * So we can't do a targeted filter. Instead, we can even more hackily filter get_permalink
	 * @see http://core.trac.wordpress.org/ticket/19378
	 */
	public function fix_preview_link_part_two( $permalink, $post, $leavename ) {
		global $pagenow;

		// Core has apply_filters( 'page_link', $link, $post->ID, $sample ); too :(
		if ( is_int( $post ) )
			$post = get_post( $post );

		// Only modify if we're using a pre-publish status on a supported custom post type
		// while doing the preview POST action
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
		if ( ! is_admin()
			|| 'post.php' != $pagenow
			|| ( empty( $_POST['wp-preview'] ) || 'dopreview' != $_POST['wp-preview'] )
			|| ! in_array( $post->post_status, $status_slugs ) 
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) )
			return $permalink;

		if ( 'page' == $post->post_type ) {
			$args = array(
					'page_id'    => $post->ID,
				);
		} else if ( 'post' == $post->post_type ) {
			$args = array(
					'p'          => $post->ID,
				);
		} else {
			$args = array(
					'p'          => $post->ID,
					'post_type'  => $post->post_type,
				);
		}
		$permalink = add_query_arg( $args, home_url() );
		return $permalink;
	}

	/**
	 * Another hack! hack! hack! until core better supports custom statuses
	 *
	 * @since 0.7.4
	 *
	 * The preview link for an unpublished post should always be ?p=, even in the list table
	 * @see http://core.trac.wordpress.org/ticket/19378
	 */
	public function fix_post_row_actions( $actions, $post ) {
		global $pagenow;

		// Only modify if we're using a pre-publish status on a supported custom post type
		$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
		if ( 'edit.php' != $pagenow
			|| ! in_array( $post->post_status, $status_slugs ) 
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) )
			return $actions;

		// 'view' is only set if the user has permission to post
		if ( empty( $actions['view'] ) )
			return $actions;

		if ( 'page' == $post->post_type ) {
			$args = array(
					'page_id'    => $post->ID,
				);
		} else if ( 'post' == $post->post_type ) {
			$args = array(
					'p'          => $post->ID,
				);
		} else {
			$args = array(
					'p'          => $post->ID,
					'post_type'  => $post->post_type,
				);
		}
		$args['preview'] = 'true';
		$preview_link = add_query_arg( $args, home_url() );

		$actions['view'] = '<a href="' . esc_url( $preview_link ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
		return $actions;
	}
}

}

/**
 * Custom Statuses uses WordPress' List Table API for generating the custom status management table
 *
 * @since 0.7
 */
class EF_Custom_Status_List_Table extends WP_List_Table
{
	
	var $callback_args;
	var $default_status;
	
	/**
	 * Construct the extended class
	 */
	function __construct() {
		
		parent::__construct( array(
			'plural' => 'custom statuses',
			'singular' => 'custom status',
			'ajax' => true
		) );
		
	}
	
	/**
	 * Pull in the data we'll be displaying on the table
	 *
	 * @since 0.7
	 */
	function prepare_items() {
		global $edit_flow;		
		
		$columns = $this->get_columns();
		$hidden = array(
			'position',
		);
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$this->items = $edit_flow->custom_status->get_custom_statuses();
		$total_items = count( $this->items );
		$this->default_status = $edit_flow->custom_status->get_default_custom_status()->slug;
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $total_items,
		) );		
	}

	/**
	 * Message to be displayed when there are no custom statuses. Should never be displayed, but we'll customize it
	 * just in case.
	 *
	 * @since 0.7
	 */
	function no_items() {
		_e( 'No custom statuses found.', 'edit-flow' );
	}
	
	/**
	 * Table shows (hidden) position, status name, status description, and the post count for each activated
	 * post type
	 *
	 * @since 0.7
	 *
	 * @return array $columns Columns to be registered with the List Table
	 */
	function get_columns() {
		global $edit_flow;

		$columns = array(
			'position'			=> __( 'Position', 'edit-flow' ),
			'name'			    => __( 'Name', 'edit-flow' ),
			'description' 		=> __( 'Description', 'edit-flow' ),
		);
		
		$post_types = get_post_types( '', 'objects' );
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $edit_flow->custom_status->module );		
		foreach ( $post_types as $post_type )
			if ( in_array( $post_type->name, $supported_post_types ) )
				$columns[$post_type->name] = $post_type->label;

		return $columns;
	}
	
	/**
	 * Fallback column callback.
	 * Primarily used to display post count for each post type
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @param string $column_name Name of the column as registered in $this->prepare_items()
	 * @return string $output What will be rendered
	 */
	function column_default( $item, $column_name ) {
		global $edit_flow;
		
		// Handle custom post counts for different post types
		$post_types = get_post_types( '', 'names' );
		if ( in_array( $column_name, $post_types ) ) {
		
			// @todo Cachify this
			$post_count = wp_cache_get( "ef_custom_status_count_$column_name" );
			if ( false === $post_count ) {
				$posts = wp_count_posts( $column_name );
				$post_status = $item->slug;
				// To avoid error notices when changing the name of non-standard statuses
				if ( isset( $posts->$post_status ) )
					$post_count = $posts->$post_status;
				else
					$post_count = 0;
				//wp_cache_set( "ef_custom_status_count_$column_name", $post_count );
			}
			$output = sprintf( '<a title="See all %1$ss saved as \'%2$s\'" href="%3$s">%4$s</a>', $column_name, $item->name, $edit_flow->helpers->filter_posts_link( $item->slug, $column_name ), $post_count );
			return $output;
		}
		
	}
	
	/**
	 * Hidden column for storing the status position
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered	
	 */
	function column_position( $item ) {
		return esc_html( $item->position );
	}
	
	/**
	 * Displayed column showing the name of the status
	 *
	 * @since 0.7
	 * 
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered
	 */
	function column_name( $item ) {
		global $edit_flow;
		
		$item_edit_link = esc_url( $edit_flow->custom_status->get_link( array( 'action' => 'edit-status', 'term-id' => $item->term_id ) ) );
				
		$output = '<strong><a href="' . $item_edit_link . '">' . esc_html( $item->name ) . '</a>';
		if ( $item->slug == $this->default_status )
			$output .= ' - ' . __( 'Default', 'edit-flow' );
		$output .= '</strong>';
		
		// Don't allow for any of these status actions when adding a new custom status
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'add' )
			return $output;
		
		$actions = array();
		$actions['edit'] = "<a href='$item_edit_link'>" . __( 'Edit', 'edit-flow' ) . "</a>";		
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __( 'Quick&nbsp;Edit' ) . '</a>';
		if ( $item->slug != $this->default_status )
			$actions['make_default'] = sprintf( '<a href="%1$s">' . __( 'Make&nbsp;Default', 'edit-flow' ) . '</a>', $edit_flow->custom_status->get_link( array( 'action' => 'make-default', 'term-id' => $item->term_id ) ) );
		
		if ( $item->slug != $this->default_status )
			$actions['delete delete-status'] = sprintf( '<a href="%1$s">' . __( 'Delete', 'edit-flow' ) . '</a>', $edit_flow->custom_status->get_link( array( 'action' => 'delete-status', 'term-id' => $item->term_id ) ) );
		
		$output .= $this->row_actions( $actions, false );
		$output .= '<div class="hidden" id="inline_' . esc_attr( $item->term_id ) . '">';
		$output .= '<div class="name">' . esc_html( $item->name ) . '</div>';
		$output .= '<div class="description">' . esc_html( $item->description ) . '</div>';	
		$output .= '</div>';
		
		return $output;
			
	}	
	
	/**
	 * Displayed column showing the description of the status
	 *
	 * @since 0.7
	 * 
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered
	 */
	function column_description( $item ) {
		return esc_html( $item->description );
	}
	
	/**
	 * Prepare and echo a single custom status row
	 *
	 * @since 0.7
	 */
	function single_row( $item ) {
		static $alternate_class = '';
		$alternate_class = ( $alternate_class == '' ? ' alternate' : '' );
		$row_class = ' class="term-static' . $alternate_class . '"';

		echo '<tr id="term-' . $item->term_id . '"' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}
	
	/**
	 * Hidden form used for inline editing functionality
	 *
	 * @since 0.7
	 */
	function inline_edit() {
		global $edit_flow;
?>
	<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
			<fieldset><div class="inline-edit-col">
				<h4><?php _e( 'Quick Edit' ); ?></h4>
				<label>
					<span class="title"><?php _e( 'Name', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" maxlength="20" /></span>
				</label>
				<label>
					<span class="title"><?php _e( 'Description', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="description" class="pdescription" value="" /></span>
				</label>
			</div></fieldset>
		<p class="inline-edit-save submit">
			<a accesskey="c" href="#inline-edit" title="<?php _e( 'Cancel' ); ?>" class="cancel button-secondary alignleft"><?php _e( 'Cancel' ); ?></a>
			<?php $update_text = __( 'Update Status', 'edit-flow' ); ?>
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
