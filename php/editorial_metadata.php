<?php

/**
 * Ways to test and play with this class:
 * 1) Create a new term at by selecting Editorial Metadata from the Edit Flow settings
 * 2) Edit an existing term (slug, description, etc.)
 * 3) Create a post and assign metadata to it
 * 4) Look at the list of terms again - the count should go up!
 * 5) Play with adding more metadata to a post
 * 6) Clear the metadata for a single term in a post and watch the count go down!
 * 6) Delete a term and note the metadata disappears from posts
 * 7) Re-add the term (same slug) and the metadata returns!
 * 
 * Improvements to make:
 * @todo Abstract the permissions check for management to class level
 */
if ( !class_exists('EF_Editorial_Metadata') ) {

class EF_Editorial_Metadata {

	/**
	 * The name of the taxonomy we're going to register for editorial metadata. This could be a
	 * const, but then it would be harder to use in PHP strings, so we'll keep it as a variable.
	 */
	var $metadata_taxonomy = 'ef_editorial_meta';
	var $metadata_postmeta_key = "_ef_editorial_meta";
	var $metadata_string;
	var $screen_id = "edit-ef_editorial_meta";
	var $module_name = 'editorial_metadata';
	
	/**
	 * A cache of the last metadata type that was seen or used. This is used to persist state between the
	 * pre_edit_term and edited_term methods below.
	 */
	var $metadata_type_cache;
	
	var $metadata_slug_cache;

	const description = 'desc';
	const metadata_type_key = 'type';
	
	/**
	 * __construct()
	 * Construct the EF_Editorial_Metadata class
	 */
	function __construct() {
		global $edit_flow;
		
		$metadata_string = __( 'Metadata Type', 'edit-flow' );
		
		// Register the module with Edit Flow
		$args = array(
			'title' => __( 'Editorial Metadata', 'edit-flow' ),
			'short_description' => __( 'Editorial Metadata make it possible to keep track of the details. tk', 'edit-flow' ),
			'extended_description' => __( 'This is a longer description that shows up on some views. We might want to include a link to documentation. tk', 'edit-flow' ),
			'img_url' => false,
			'slug' => 'editorial-metadata',
			'default_options' => array(
				'enabled' => 'on',
			),
			'messages' => array(
				'term-added' => __( "Metadata term added.", 'edit-flow' ),			
				'term-updated' => __( "Metadata term updated.", 'edit-flow' ),
				'term-missing' => __( "Metadata term doesn't exist.", 'edit-flow' ),
				'term-deleted' => __( "Metadata term deleted.", 'edit-flow' ),
			),
			'configure_page_cb' => 'print_configure_view',
		);
		$edit_flow->register_module( $this->module_name, $args );		
		
		
	} // END: __construct()
	
	/**
	 * init()
	 */
	function init() {
		
		$this->register_taxonomy();
		
		add_action( 'admin_init', array( &$this, 'handle_add_editorial_metadata' ) );		
		add_action( 'admin_init', array( &$this, 'handle_edit_editorial_metadata' ) );
		add_action( 'admin_init', array( &$this, 'handle_delete_editorial_metadata' ) );		
		
		add_action( 'add_meta_boxes', array( &$this, 'handle_post_metaboxes' ) );
		add_action( 'save_post', array( &$this, 'save_meta_box' ), 10, 2 );
		
		// Load necessary scripts and stylesheets
		add_action( 'admin_enqueue_scripts', array( &$this, 'add_admin_scripts' ) );	
		
	} // END: init()
	
	/**
	 * get_encoded_description()
	 * Encode a given description and type as JSON 
	 *
	 * @param string $metadata_description Metadata description
	 * @param string $metadata_type Metadata type
	 * @return string $encoded Type and description encoded as JSON
	 */
	function get_encoded_description( $metadata_description, $metadata_type ) {
		// Damn pesky carriage returns...
		$metadata_description = str_replace("\r\n", "\n", $metadata_description);
		$metadata_description = str_replace("\r", "\n", $metadata_description);
		// Convert all newlines to <br /> for storage (and because it's the proper way to present them)
		$metadata_description = str_replace("\n", "<br />", $metadata_description);		
		$allowed_tags = '<b><a><strong><i><ul><li><ol><blockquote><em><br>';
		$metadata_description = strip_tags( $metadata_description, $allowed_tags );
		// Escape any special characters (', ", <, >, &)
		$metadata_description = esc_attr( $metadata_description );
		$metadata_description = htmlentities( $metadata_description, ENT_QUOTES );
		$encoded = json_encode( array( self::description        => $metadata_description,
		                           self::metadata_type_key  => $metadata_type,
		                          )
		                   );
		
		return $encoded;
	} // END: get_encoded_description()
	
	/**
	 * get_select_html()
	 */
	function get_select_html( $description ) {
		$current_metadata_type = $this->get_metadata_type( $description );
		$metadata_types = $this->get_supported_metadata_types();
		?>
		<select id="<?php echo $this->metadata_taxonomy . '_' . self::metadata_type_key; ?>" name="<?php echo $this->metadata_taxonomy . '_' . self::metadata_type_key; ?>">
		<?php foreach ( $metadata_types as $metadata_type => $metadata_type_name ) : ?>
			<option value="<?php echo $metadata_type; ?>" <?php selected( $metadata_type, $current_metadata_type ); ?>><?php echo $metadata_type_name; ?></option>
		<?php endforeach; ?>
		</select>
	<?php
	} // END: get_select_html()
	
	/**
	 * get_supported_metadata_types()
	 * Supported editorial metadata
	 *
	 * @return array $supported_metadata_types All of the supported metadata
	 */
	function get_supported_metadata_types() {
		
		$supported_metadata_types = array(
			'checkbox'		=> __('Checkbox', 'edit-flow'),
			'date'			=> __('Date', 'edit-flow'),
			'location'		=> __('Location', 'edit-flow'),
			'paragraph'		=> __('Paragraph', 'edit-flow'),
			'text'			=> __('Text', 'edit-flow'),
			'user'			=> __('User', 'edit-flow'),
			'number'		=> __('Number', 'edit-flow'),			
		);
		return $supported_metadata_types;
		
	} // END: get_supported_metadata_types()
	
	/**
	 * Enqueue relevant admin Javascript
	 */ 
	function add_admin_scripts() {
		global $current_screen, $edit_flow;
		
		// Add the metabox date picker JS and CSS
		$current_post_type = $edit_flow->helpers->get_current_post_type();
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $edit_flow->custom_status->module );	
		if ( in_array( $current_post_type, $supported_post_types ) ) {
			$edit_flow->helpers->enqueue_datepicker_resources();

			// Now add the rest of the metabox CSS
			wp_enqueue_style('edit_flow-editorial_metadata-styles', EDIT_FLOW_URL . 'css/ef_editorial_metadata.css', false, EDIT_FLOW_VERSION, 'all');
		}
		
		// Either editing the taxonomy or a specific term
		if ( $current_screen->id == $this->screen_id ) {
			wp_enqueue_script( 'edit_flow-editorial_metadata', EDIT_FLOW_URL . 'js/ef_editorial_metadata.js', array( 'jquery',  ), EDIT_FLOW_VERSION, true );
		}
	} // END: add_admin_scripts()
	
	/**
	 * get_metadata_type()
	 * Gets the metadata type described by this term, stored in the term itself. Usually stored in $term->description.
	 *
	 * @param object|string|int term Term from which to get the metadata object (object or term_id) or the metadata type itself.
	 * @return string $metadata_type Metadata type as a string
	 */
	function get_metadata_type( $term ) {
		$metadata_type = '';
		if ( is_object( $term ) ) {
			$metadata_type = $term->description;
		} else if ( is_int( $term ) && $term > 0 ) {
			$metadata_type = get_term_by( 'term_id', $term->term_id, $this->metadata_taxonomy )->description;
		} else {
			$metadata_type = $term;
		}
		$metadata_type = $this->get_unencoded_value( $metadata_type, self::metadata_type_key );
		return $metadata_type;
	} // END: get_metadata_type()
	
	function get_unencoded_value( $string_to_unencode, $key ) {
		$string_to_unencode = stripslashes( htmlspecialchars_decode( $string_to_unencode ) );
		$unencoded_array = json_decode( $string_to_unencode, true );
		if ( is_array( $unencoded_array ) ) {
			$unencoded_array[$key] = html_entity_decode( $unencoded_array[$key], ENT_QUOTES );			
			return $unencoded_array[$key];
		} else {
			return $string_to_unencode;
		}
	}
	
	/**
	 * register_taxonomy()
	 * Register the post metadata taxonomy
	 */
	function register_taxonomy() {
		global $edit_flow;

		// We need to make sure taxonomy is registered for all of the post types that support it
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_editorial_metadata' );
	
		register_taxonomy( $this->metadata_taxonomy, $supported_post_types,
			array(
				'public' => false,
				'labels' => array(
					'name' => _x( 'Editorial Metadata', 'taxonomy general name', 'edit-flow' ),
					'singular_name' => _x( 'Editorial Metadata', 'taxonomy singular name', 'edit-flow' ),
						'search_items' => __( 'Search Editorial Metadata', 'edit-flow' ),
						'popular_items' => __( 'Popular Editorial Metadata', 'edit-flow' ),
						'all_items' => __( 'All Editorial Metadata', 'edit-flow' ),
						'edit_item' => __( 'Edit Editorial Metadata', 'edit-flow' ),
						'update_item' => __( 'Update Editorial Metadata', 'edit-flow' ),
						'add_new_item' => __( 'Add New Editorial Metadata', 'edit-flow' ),
						'new_item_name' => __( 'New Editorial Metadata', 'edit-flow' ),
					)
			)
		);
	} // END: register_taxonomy()
	
	// -------------------------
	// Post metabox stuff
	// -------------------------
	
	function handle_post_metaboxes() {
		global $edit_flow;
			
		// Add the editorial meta meta_box for all of the post types we want to support
		$current_post_type = $edit_flow->helpers->get_current_post_type();
		$post_types = $edit_flow->get_all_post_types_for_feature( 'ef_editorial_metadata' );
		foreach ( $post_types as $post_type ) {
			add_meta_box( $this->metadata_taxonomy, __( 'Editorial Metadata', 'edit-flow' ), array( &$this, 'display_meta_box' ), $post_type, 'side' );
		}
	}
	
	function display_meta_box( $post ) {
		echo "<div id='{$this->metadata_taxonomy}_meta_box'>";
		// Add nonce for verification upon save
		echo "<input type='hidden' name='{$this->metadata_taxonomy}_nonce' value='" . wp_create_nonce(__FILE__) . "' />";
	
		$terms = $this->get_editorial_metadata_terms();
		if ( !count( $terms ) ) {
			$message = __( 'No editorial metadata available.' );
			if ( current_user_can( 'manage_options' ) )
				$message .= sprintf( __( ' <a href="%s">Add fields to get started</a>.' ), EDIT_FLOW_EDITORIAL_METADATA_PAGE );
			else 
				$message .= __( ' Encourage your site administrator to configure your editorial workflow.' );
			echo '<p>' . $message . '</p>';
		} else {
			foreach ( $terms as $term ) {
				$postmeta_key = $this->get_postmeta_key( $term );
				$current_metadata = esc_attr( $this->get_postmeta_value( $term, $post->ID ) );
				$type = $this->get_metadata_type( $term );
				$description = $this->get_unencoded_value( $term->description, self::description );
				if ( $description )
					$description_span = "<span class='description'>$description</span>";
				else
					$description_span = '';
				echo "<div class='{$this->metadata_taxonomy} {$this->metadata_taxonomy}_$type'>";
				switch( $type ) {
					case "date":
						// TODO: Move this to a function
						if ( !empty( $current_metadata ) ) {
							// Turn timestamp into a human-readable date
							$current_metadata = date( 'M d Y' , intval( $current_metadata ) );						
						}
						echo "<div class='{$this->metadata_taxonomy}-item'>";
						echo "<label for='$postmeta_key'>{$term->name}</label>";
						echo "<span class='description'>";
						if ( $description )
							echo "$description&nbsp;&nbsp;&nbsp;";
						echo "<a class='clear-date' href='#'>Clear</a></span>";
						echo "<input id='$postmeta_key' name='$postmeta_key' type='text' class='date-pick' value='$current_metadata' />";
						echo "</div>";
						break;
					case "location":
						echo "<label for='$postmeta_key'>{$term->name}</label>";
						if ( $description_span )
							echo "<label for='$postmeta_key'>$description_span</label>";
						echo "<input id='$postmeta_key' name='$postmeta_key' type='text' value='$current_metadata' />";
						if ( !empty( $current_metadata ) )
							echo "<div><a href='http://maps.google.com/?q={$current_metadata}&t=m' target='_blank'>" . sprintf( __( 'View &#8220;%s&#8221; on Google Maps', 'edit-flow' ), $current_metadata ) . "</a></div>";
						break;
					case "text":
						echo "<label for='$postmeta_key'>{$term->name}$description_span</label>";
						echo "<input id='$postmeta_key' name='$postmeta_key' type='text' value='$current_metadata' />";
						break;
					case "paragraph":
						echo "<label for='$postmeta_key'>{$term->name}$description_span</label>";
						echo "<textarea id='$postmeta_key' name='$postmeta_key'>$current_metadata</textarea>";
						break;
					case "checkbox":
						echo "<label for='$postmeta_key'>{$term->name}$description_span</label>";
						echo "<input id='$postmeta_key' name='$postmeta_key' type='checkbox' value='1' " . checked($current_metadata, 1, false) . " />";
						break;
					case "user": 
						echo "<label for='$postmeta_key'>{$term->name}$description_span</label>";
						$user_dropdown_args = array( 
								'show_option_all' => __( '-- Select a user --', 'edit-flow' ), 
								'name'     => $postmeta_key,
								'selected' => $current_metadata 
							); 
						wp_dropdown_users( $user_dropdown_args );
						break;
					case "number":
						echo "<label for='$postmeta_key'>{$term->name}$description_span</label>";
						echo "<input id='$postmeta_key' name='$postmeta_key' type='text' value='$current_metadata' />";
						break;					
					default:
						echo "<p>" . __( 'This editorial metadata type is not yet supported.', 'edit-flow' ) . "</p>";
				}
			echo "</div>";
			echo "<div class='clear'></div>";
		} // Done iterating through metadata terms
		}		
		echo "</div>";
	}
	
	/**
	 * save_meta_box()
	 * Save any values in the editorial metadata post meta box
	 */
	function save_meta_box( $id, $post ) {
		global $edit_flow;
		// Authentication checks: make sure data came from our meta box and that the current user is allowed to edit the post
		// TODO: switch to using check_admin_referrer? See core (e.g. edit.php) for usage
		if ( ! isset( $_POST[$this->metadata_taxonomy . "_nonce"] )
			|| ! wp_verify_nonce( $_POST[$this->metadata_taxonomy . "_nonce"], __FILE__ ) ) {
			return $id;
		}
		
		if( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! in_array( $post->post_type, $edit_flow->get_all_post_types_for_feature( 'ef_editorial_metadata' ) )
			|| $post->post_type == 'post' && !current_user_can( 'edit_post', $id )
			|| $post->post_type == 'page' && !current_user_can( 'edit_page', $id ) ) {
			return $id;
		}
		
		// Authentication passed, let's save the data		
		$terms = $this->get_editorial_metadata_terms();
		$term_slugs = array();
				
		foreach ( $terms as $term ) {
			// Setup the key for this editorial metadata term (same as what's in $_POST)
			$key = $this->get_postmeta_key( $term );
			
			// Get the current editorial metadata
			// TODO: do we care about the current_metadata at all?
			//$current_metadata = get_post_meta( $id, $key, true );
			
			$new_metadata = isset( $_POST[$key] ) ? $_POST[$key] : '';
			
			if ( empty ( $new_metadata ) ) {
				delete_post_meta( $id, $key );
			} else {
				
				$type = $this->get_metadata_type( $term );
				// TODO: Move this to a function
				if ( $type == 'date' ) {
					$new_metadata = strtotime( $new_metadata );
				}
				if ( $type == 'number' ) {
					$new_metadata = (int)$new_metadata;
				}
				
				$new_metadata = strip_tags( $new_metadata );
				update_post_meta( $id, $key, $new_metadata );
				
				// Add the slugs of the terms with non-empty new metadata to an array
				$term_slugs[] = $term->slug;
			}
		}
		
		// Relate the post to the terms used and taxonomy type (wp_term_relationships table).
		// This will allow us to update and display the count of metadata in posts in use per term.
		// TODO: Core only correlates posts with terms if the post_status is publish. Do we care what it is?
		if ( $post->post_status === 'publish' ) {
			wp_set_object_terms( $id, $term_slugs, $this->metadata_taxonomy );
		}
	} // END: save_meta_box()
	
	/**
	 * get_postmeta_key()
	 * Generate a unique key based on the term
	 * 
	 * @param object $term Term object
	 * @return string $postmeta_key Unique key
	 */
	function get_postmeta_key( $term ) {
		$key = $this->metadata_postmeta_key;
		$type = $this->get_metadata_type( $term );
		$prefix = "{$key}_{$type}";
		$postmeta_key = "{$prefix}_" . ( is_object( $term ) ? $term->slug : $term );
		return $postmeta_key;
	} // END: get_postmeta_key()
	
	/**
	 * Returns the value for the given metadata
	 *
	 * @param object|string|int term The term object, slug or ID for the metadata field term
	 * @param int post_id The ID of the post
	 */
	function get_postmeta_value( $term, $post_id ) {
		if( ! is_object( $term ) )
			$term = $this->get_editorial_metadata_term( $term );
		$postmeta_key = $this->get_postmeta_key( $term );
		return get_metadata( 'post', $post_id, $postmeta_key, true );
	}
	
	function get_editorial_metadata_terms() {
		return get_terms( $this->metadata_taxonomy, array(
		        'orderby'    => apply_filters( 'ef_editorial_metadata_term_order', 'name' ),
		        'hide_empty' => false
			)
		);
	}
	
	/**
	 * get_editorial_metadata_term()
	 * Returns a term for single metadata field
	 *
	 * @param int|string $field The slug or ID for the metadata field term to return 
	 * @return object $term Term's object representation
	 */
	function get_editorial_metadata_term( $field ) {
		
		if ( is_int( $field ) ) {
			$term = get_term_by( 'id', $field, $this->metadata_taxonomy );
		} elseif( is_string( $field ) ) {
			$term = get_term_by( 'slug', $field, $this->metadata_taxonomy );
		}
		
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}
		
		return $term;
		
	} // END: get_editorial_metadata_term()
	
	/**
	 * Update an existing editorial metadata term if the term_id exists
	 *
	 * @since 0.7
	 *
	 * @param int $term_id The term's unique ID
	 * @param array $args Any values that need to be updated for the term
	 * @return object|WP_Error $updated_term The updated term or a WP_Error object if something disastrous happened
	 */
	function update_editorial_metadata_term( $term_id, $args ) {
		
		$new_args = array();
		$old_term = get_term_by( 'id', $term_id, $this->metadata_taxonomy );
		if ( $old_term )
			$new_args = array(
				'name' => $old_term->name,
				'description' => $this->get_unencoded_value( $old_term->description, 'desc' ),
				'type' => $this->get_unencoded_value( $old_term->description, 'type' ),				
			);
		$new_args = array_merge( $new_args, $args );
		if ( $old_term )
			$new_args['slug'] = sanitize_title( $new_args['name'] );
		// These fields have to be encoded into one if they exist
		if ( isset( $new_args['description'] ) || isset( $new_args['type'] ) ) {
			$new_args['description'] = $this->get_encoded_description( $new_args['description'], $new_args['type'] );
		}
		$updated_term = wp_update_term( $term_id, $this->metadata_taxonomy, $new_args );
		return $updated_term;
	}
	
	/**
	 * Insert a new editorial metadata term
	 *
	 * @since 0.7
	 */
	function insert_editorial_metadata_term( $args ) {
		
		$defaults = array(
			'name' => '',
			'slug' => '',
			'description' => '',
			'type' => '',
		);
		$args = array_merge( $defaults, $args );
		$term_name = $args['name'];
		unset( $args['name'] );
		if ( isset( $args['description'] ) || isset( $args['type'] ) )
			$args['description'] = $this->get_encoded_description( $args['description'], $args['type'] );
		$inserted_term = wp_insert_term( $term_name, $this->metadata_taxonomy, $args );
		return $inserted_term;
	}
	
	/**
	 * Delete an existing editorial metadata term
	 *
	 * @since 0.7
	 * 
	 * @param int $term_id The term we want deleted
	 * @return bool $result Whether or not the term was deleted
	 */
	function delete_editorial_metadata_term( $term_id ) {
		$result = wp_delete_term( $term_id, $this->metadata_taxonomy );
		return $result;
	}
	
	/**
	 * Generate a link to edit the existing term
	 *
	 * @since 0.7
	 *
	 * @param object $term The term as an object
	 * @return string $link Generated link
	 */
	function get_edit_term_link( $term ) {
		$args = array(
			'configure' => 'editorial-metadata',
			'action' => 'edit',
			'term-id' => $term->term_id,
		);
		return add_query_arg( $args, EDIT_FLOW_SETTINGS_PAGE );
	}
	
	/**
	 * Generate a link to delete the existing term
	 *
	 * @since 0.7
	 *
	 * @param object $term The term as an object
	 * @return string $link Generated link
	 */
	function get_delete_term_link( $term ) {
		$args = array(
			'configure' => 'editorial-metadata',
			'action' => 'delete',
			'term-id' => $term->term_id,
			'nonce' => wp_create_nonce( 'editorial-metadata-delete-nonce' ),
		);
		return add_query_arg( $args, EDIT_FLOW_SETTINGS_PAGE );
	}
	
	/**
	 * Handles a request to add a new piece of editorial metadata
	 */
	function handle_add_editorial_metadata() {
		
		if ( !isset( $_POST['submit'], $_POST['form-action'], $_GET['configure'] ) 
			|| $_GET['configure'] != 'editorial-metadata' || $_POST['form-action'] != 'add-term' )
				return;	
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'editorial-metadata-add-nonce' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_options' ) )
			wp_die( $this->module->messages['invalid-permissions'] );			
		
		// Sanitize all of the user-entered values
		$term_name = strip_tags( trim( $_POST['name'] ) );
		$term_slug = ( !empty( $_POST['slug'] ) ) ? sanitize_title( $_POST['slug'] ) : sanitize_title( $term_name );
		$term_description = strip_tags( trim( $_POST['description'] ) );
		$term_type = sanitize_key( $_POST['type'] );
		
		$_REQUEST['form-errors'] = array();
		
		/**
		 * Form validation for adding new editorial metadata term
		 *
		 * Details
		 * - "name", "slug", and "type" are required fields
		 * - "description" can accept a limited amount of HTML, and is optional
		 */
		// Field is required
		if ( empty( $term_name ) )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the editorial metadata.', 'edit-flow' );
		// Field is required
		if ( empty( $term_slug ) )
			$_REQUEST['form-errors']['slug'] = __( 'Please enter a slug for the editorial metadata.', 'edit-flow' );			
		// Check to ensure a term with the same name doesn't exist
		if ( get_term_by( 'name', $term_name, $this->metadata_taxonomy ) )
			$_REQUEST['form-errors']['name'] = __( 'Name already in use. Please choose another.', 'edit-flow' );
		// Check to ensure a term with the same slug doesn't exist
		if ( get_term_by( 'slug', $term_slug, $this->metadata_taxonomy ) )
			$_REQUEST['form-errors']['slug'] = __( 'Slug already in use. Please choose another.', 'edit-flow' );
		// Metadata type needs to pass our whitelist check
		$metadata_types = $this->get_supported_metadata_types();
		if ( empty( $_POST['type'] ) || !isset( $metadata_types[$_POST['type'] ] ) )
			$_REQUEST['form-errors']['type'] = __( 'Please select a valid metadata type.', 'edit-flow' );
		// Kick out if there are any errors
		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}

		// Try to add the status
		$args = array(
			'name' => $term_name,			
			'description' => $term_description,
			'slug' => $term_slug,
			'type' => $term_type,
		);
		$return = $this->insert_editorial_metadata_term( $args );
		if ( is_wp_error( $return ) )
			wp_die( __( 'Error adding term.', 'edit-flow' ) );

		$redirect_url = add_query_arg( array( 'configure' => $this->module->slug, 'message' => 'term-added' ), EDIT_FLOW_SETTINGS_PAGE );
		wp_redirect( $redirect_url );
		exit;
		
		
	}
	
	/**
	 * Handles a request to edit an editorial metadata
	 */
	function handle_edit_editorial_metadata() {
		
		if ( !isset( $_POST['submit'], $_GET['configure'], $_GET['action'], $_GET['term-id'] ) 
			|| $_GET['configure'] != 'editorial-metadata' || $_GET['action'] != 'edit' )
				return; 
				
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'editorial-metadata-edit-nonce' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_options' ) )
			wp_die( $this->module->messages['invalid-permissions'] );			
		
		if ( !$existing_term = $this->get_editorial_metadata_term( (int)$_GET['term-id'] ) )
			wp_die( $this->module->messsage['term-error'] );			
		
		$new_name = strip_tags( trim( $_POST['name'] ) );
		$new_description = strip_tags( trim( $_POST['description'] ) );
			
		/**
		 * Form validation for editing editorial metadata term
		 *
		 * Details
		 * - "name", "slug", and "type" are required fields
		 * - "description" can accept a limited amount of HTML, and is optional
		 */
		$_REQUEST['form-errors'] = array();	
		// Check if name field was filled in
		if( empty( $new_name ) )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the editorial metadata', 'edit-flow' );
			
		// Check that the name isn't numeric
		if ( (int)$new_name != 0 )
			$_REQUEST['form-errors']['name'] = __( 'Please enter a valid, non-numeric name for the editorial metadata.', 'edit-flow' );
			
		// Check to ensure a term with the same name doesn't exist,
		$search_term = get_term_by( 'name', $new_name, $this->metadata_taxonomy );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_term->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Name already in use. Please choose another.', 'edit-flow' );
		// or that the term name doesn't map to an existing term's slug			
		$search_term = get_term_by( 'slug', sanitize_title( $new_name ), $this->metadata_taxonomy );
		if ( is_object( $search_term ) && $search_term->term_id != $existing_term->term_id )
			$_REQUEST['form-errors']['name'] = __( 'Name conflicts with slug for another term. Please choose something else.', 'edit-flow' );					
		
		// Check that the term name doesn't exceed 20 chars
		if ( strlen( $new_name ) > 20 )
			$_REQUEST['form-errors']['name'] = __( 'Name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
	
		// Kick out if there are any errors
		if ( count( $_REQUEST['form-errors'] ) ) {
			$_REQUEST['error'] = 'form-error';
			return;
		}
		
		// Try to add the status
		$args = array(
			'name' => $new_name,			
			'description' => $new_description,
		);
		$return = $this->update_editorial_metadata_term( $existing_term->term_id, $args );
		if ( is_wp_error( $return ) )
			wp_die( __( 'Error updating term.', 'edit-flow' ) );
		
		$redirect_url = add_query_arg( array( 'configure' => $this->module->slug, 'message' => 'term-updated' ), EDIT_FLOW_SETTINGS_PAGE );
		wp_redirect( $redirect_url );
		exit;
		
	}
	
	/**
	 * Handles a request to delete an editorial metadata term
	 */
	function handle_delete_editorial_metadata() {
		
		if ( !isset( $_GET['configure'], $_GET['action'], $_GET['term-id'] ) 
			|| $_GET['configure'] != 'editorial-metadata' || $_GET['action'] != 'delete' )
				return;
				
		if ( !wp_verify_nonce( $_GET['nonce'], 'editorial-metadata-delete-nonce' ) )
			wp_die( $this->module->messages['nonce-failed'] );
			
		if ( !current_user_can( 'manage_options' ) )
			wp_die( $this->module->messages['invalid-permissions'] );
			
		if ( !$existing_term = $this->get_editorial_metadata_term( (int)$_GET['term-id'] ) )
			wp_die( $this->module->messsage['term-error'] );			
			
		$result = $this->delete_editorial_metadata_term( $existing_term->term_id );
		if ( !$result || is_wp_error( $result ) )
			wp_die( __( 'Error deleting term.', 'edit-flow' ) );
			
		$redirect_url = add_query_arg( array( 'configure' => $this->module->slug, 'message' => 'term-deleted' ), EDIT_FLOW_SETTINGS_PAGE );
		wp_redirect( $redirect_url );
		exit;		
	}
	
	/**
	 * Prepare and display the configuration view for editorial metadata
	 */ 
	function print_configure_view() {
		global $edit_flow;
		$wp_list_table = new EF_Editorial_Metadata_List_Table();
		$wp_list_table->prepare_items();
		?>
		
		<?php if ( !isset( $_GET['action'] ) || ( isset( $_GET['action'] ) && $_GET['action'] != 'edit' ) ): ?>
		<div id="col-right">
		<div class="col-wrap">
		<form id="posts-filter" action="" method="post">
			<?php $wp_list_table->display(); ?>
		</form>

		</div>
		</div><!-- /col-right -->
		<?php endif; ?>	
		
		<?php if ( isset( $_GET['action'], $_GET['term-id'] ) && $_GET['action'] == 'edit' ): ?>
	
		<?php
			// Check whether the term exists
			$term_id = (int)$_GET['term-id'];
			$term = get_term_by( 'id', $term_id, $this->metadata_taxonomy );
			if ( !$term ) {
				echo '<div class="error"><p>' . $this->module->messages['term-missing'] . '</p></div>';
				return; 
			}
			$metadata_types = $this->get_supported_metadata_types();			
			$type = $this->get_metadata_type( $term );
			$edit_term_link = $this->get_edit_term_link( $term );
			
			$name = ( isset( $_POST['name'] ) ) ? stripslashes( $_POST['name'] ) : $term->name;
			$description = ( isset( $_POST['description'] ) ) ? stripslashes( $_POST['description'] ) : $this->get_unencoded_value( $term->description, 'desc' );
		?>
		
		<div id="ajax-response"></div>
		<form method="post" action="<?php echo esc_attr( $edit_term_link ); ?>" >
		<input type="hidden" name="action" value="editedtag" />
		<input type="hidden" name="tag_id" value="<?php echo esc_attr( $term->term_id ); ?>" />
		<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $this->metadata_taxonomy ) ?>" />
		<?php
			wp_original_referer_field();
			wp_nonce_field( 'editorial-metadata-edit-nonce' );
		?>
		<table class="form-table">
			<tr class="form-field form-required">
				<th scope="row" valign="top"><label for="name"><?php _e( 'Editorial Metadata', 'edit-flow' ); ?></label></th>
				<td><input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" size="40" aria-required="true" />
				<?php if ( isset( $_REQUEST['form-errors']['name'] ) ): ?>
				<div class="form-error">
				<p><?php echo $_REQUEST['form-errors']['name']; ?></p>	
				</div>
				<?php else: ?>					
				<p class="description"><?php _e( 'The name is for labeling the metadata field.', 'edit-flow' ); ?></p></td>
				<?php endif; ?>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top"><?php _e( 'Slug', 'edit-flow' ); ?></th>
				<td>
					<input type="text" disabled="disabled" value="<?php echo esc_attr( $term->slug ); ?>" /><br />
					<span class="description"><?php _e( 'The slug cannot be changed once the term has been created.', 'edit-flow' ); ?></span>
				</td>
			</tr>			
			<tr class="form-field">
				<th scope="row" valign="top"><label for="description"><?php _e( 'Description', 'edit-flow' ); ?></label></th>
				<td><textarea name="description" id="description" rows="5" cols="50" style="width: 97%;"><?php echo esc_html( $description ); ?></textarea><br />
				<?php if ( isset( $_REQUEST['form-errors']['description'] ) ): ?>
				<div class="form-error">
				<p><?php echo $_REQUEST['form-errors']['description']; ?></p>	
				</div>
				<?php else: ?>					
				<span class="description"><?php _e( 'The description can be used to communicate with your team about what the metadata is for.', 'edit-flow' ); ?></span></td>
				<?php endif; ?>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top"><?php _e( 'Type', 'edit-flow' ); ?></th>
				<td>
					<input type="text" disabled="disabled" value="<?php echo esc_attr( $metadata_types[$type] ); ?>" /><br />
					<span class="description"><?php _e( 'The metadata type cannot be changed once created.', 'edit-flow' ); ?></span>
				</td>
			</tr>
		<input type="hidden" name="<?php echo $this->metadata_taxonomy . '_' . self::metadata_type_key; ?>" value="<?php echo $type; ?>" />
		</table>
		<p class="submit">
		<?php submit_button( __( 'Update Metadata', 'edit-flow' ), 'primary', 'submit', false ); ?>
		<a class="cancel-settings-link" href="<?php echo esc_url( add_query_arg( 'configure', $this->module->slug, EDIT_FLOW_SETTINGS_PAGE ) ); ?>"><?php _e( 'Cancel', 'edit-flow' ); ?></a>
		</p>
		</form>
		</div>
		
		<?php else: ?>
		
		<div id="col-left">
			<div class="col-wrap">		
		<?php if ( isset( $_GET['action'] ) && $_GET['action'] == 'change-options' ): ?>
			
		<?php else: ?>
			<div class="form-wrap">
			<h3><?php _e( 'Add Editorial Metadata', 'edit-flow' ); ?></h3>
			<form class="add:the-list:" action="<?php echo esc_url( add_query_arg( array( 'configure' => $this->module->slug ), EDIT_FLOW_SETTINGS_PAGE ) ); ?>" method="post" id="addmetadata" name="addmetadata">
			<div class="form-field form-required">
				<label for="name"><?php _e( 'Name', 'edit-flow' ); ?></label>
				<input type="text" aria-required="true" size="20" maxlength="20" id="name" name="name" value="<?php if ( !empty( $_POST['name'] ) ) esc_attr_e( stripslashes( $_POST['name'] ) ) ?>" />
				<?php if ( isset( $_REQUEST['form-errors']['name'] ) ): ?>
				<div class="form-error">
				<p><?php echo $_REQUEST['form-errors']['name']; ?></p>	
				</div>
				<?php else: ?>
				<p class="description"><?php _e( 'The name is for labeling the metadata field.', 'edit-flow') ?></p>
				<?php endif; ?>
			</div>
			<div class="form-field form-required">
				<label for="name"><?php _e( 'Slug', 'edit-flow' ); ?></label>
				<input type="text" aria-required="true" size="20" maxlength="20" id="slug" name="slug" value="<?php if ( !empty( $_POST['slug'] ) ) esc_attr_e( $_POST['slug'] ) ?>" />
				<?php if ( isset( $_REQUEST['form-errors']['slug'] ) ): ?>
				<div class="form-error">
				<p><?php echo $_REQUEST['form-errors']['slug']; ?></p>	
				</div>
				<?php else: ?>
				<p class="description"><?php _e( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'edit-flow') ?></p>
				<?php endif; ?>
			</div>
			<div class="form-field">
				<label for="description"><?php _e( 'Description', 'edit-flow' ); ?></label>
				<textarea cols="40" rows="5" id="description" name="description"><?php if ( !empty( $_POST['description'] ) ) echo esc_html( stripslashes( $_POST['description'] ) ) ?></textarea>
				<p class="description"><?php _e( 'The description can be used to communicate with your team about what the metadata is for.', 'edit-flow' ); ?></p>
			</div>
			<div class="form-field form-required">
				<label for="name"><?php _e( 'Type', 'edit-flow' ); ?></label>
				<?php
					$current_metadata_type = ( isset( $_POST['type'] ) ) ? $_POST['type'] : false;
					$metadata_types = $this->get_supported_metadata_types();
				?>
				<select id="type" name="type">
				<?php foreach ( $metadata_types as $metadata_type => $metadata_type_name ) : ?>
					<option value="<?php echo esc_attr( $metadata_type ); ?>" <?php selected( $metadata_type, $current_metadata_type ); ?>><?php echo esc_attr( $metadata_type_name ); ?></option>
				<?php endforeach; ?>
				</select>
				<?php if ( isset( $_REQUEST['form-errors']['type'] ) ): ?>
				<div class="form-error">
				<p><?php echo $_REQUEST['form-errors']['type']; ?></p>	
				</div>
				<?php else: ?>
				<p class="description"><?php _e( 'Indicate the type of editorial metadata.', 'edit-flow') ?></p>
				<?php endif; ?>
			</div>			
			<?php wp_nonce_field( 'editorial-metadata-add-nonce' );?>
			<input type="hidden" id="form-action" name="form-action" value="add-term" />
			<p class="submit">
				<?php submit_button( __( 'Add New Metadata', 'edit-flow' ), 'primary', 'submit', false ); ?>
			</p>
			</form>		
			</div>
		<?php endif; ?>
			</div>
		</div>
		
		<?php
		endif;
		
	}
	
} // END: class EF_Editorial_Metadata

} // END: if ( !class_exists('EF_Editorial_Metadata') )


if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EF_Editorial_Metadata_List_Table extends WP_List_Table
{
	
	var $callback_args;
	var $taxonomy;
	var $tax;

	function __construct() {
		global $edit_flow;

		$this->taxonomy = $edit_flow->editorial_metadata->metadata_taxonomy;
		
		$this->tax = get_taxonomy( $this->taxonomy );
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		
		$this->_column_headers = array( $columns, $hidden, $sortable );		

		parent::__construct( array(
			'plural' => 'editorial metadata',
			'singular' => 'editorial metadata',
		) );
	}

	function ajax_user_can() {
		return current_user_can( $this->tax->cap->manage_terms );
	}

	function prepare_items() {
		
		$args = array(
			'hide_empty' => false,
		);
		$this->items = get_terms( $this->taxonomy, $args );

		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page' => count( $this->items ),
		) );
	}
	
	function current_action() {
		return parent::current_action();
	}	

	function has_items() {
		if ( count( $this->items ) )
			return true;
		else
			return false;
	}

	function get_columns() {
		global $edit_flow;
		$columns = array(
			'name'        => __( 'Editorial Metadata', 'edit-flow' ),
			'type'		  => __( 'Metadata Type', 'edit-flow' ),
			'description' => __( 'Description' ),
		);
		
		$post_types = get_post_types( '', 'objects' );
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $edit_flow->editorial_metadata->module );		
		foreach ( $post_types as $post_type )
			if ( in_array( $post_type->name, $supported_post_types ) )
				$columns[$post_type->name] = $post_type->label;
				
		return $columns;
	}
	
	function single_row( $term, $level = 0 ) {
		static $row_class = '';
		$row_class = ( $row_class == '' ? ' class="alternate"' : '' );

		$this->level = $level;

 		echo '<tr id="term-' . $term->term_id . '"' . $row_class . '>';
 		echo $this->single_row_columns( $term );
 		echo '</tr>';
	}

	/**
	 * Column for displaying the term's name
	 */
	function column_name( $item ) {
		global $edit_flow;
		
		$item_edit_link = esc_url( $edit_flow->editorial_metadata->get_edit_term_link( $item ) );
		$item_delete_link = esc_url( $edit_flow->editorial_metadata->get_delete_term_link( $item ) );		
		
		$out = '<strong><a class="row-title" href="' . $item_edit_link . '">' . esc_html( $item->name ) . '</a></strong>';
		
		$actions = array();
		$actions['edit'] = sprintf( '<a href="%1$s">' . __( 'Edit', 'edit-flow' ) . '</a>', $item_edit_link );
		$actions['delete delete-status'] = sprintf( '<a href="%1$s">' . __( 'Delete', 'edit-flow' ) . '</a>', $item_delete_link );
		
		$out .= $this->row_actions( $actions, false );
		$out .= '<div class="hidden" id="inline_' . $item->term_id . '">';
		$out .= '<div class="name">' . $item->name . '</div>';
		$out .= '<div class="description">' . $item->description . '</div>';	
		$out .= '</div>';
		
		return $out;
	}
	
	/**
	 * Column for displaying the type of editorial metadata
	 */
	function column_type( $item ) {
		global $edit_flow;
		return esc_html( $edit_flow->editorial_metadata->get_unencoded_value( $item->description, 'type' ) );
	}

	function column_description( $item ) {
		global $edit_flow;
		
		return esc_html( $edit_flow->editorial_metadata->get_unencoded_value( $item->description, 'desc' ) );
	}

	function column_default( $tag, $column_name ) {
		$screen = get_current_screen();
		
		var_dump( $tag );

		return apply_filters( "manage_{$screen->taxonomy}_custom_column", '', $column_name, $tag->term_id );
	}
	function inline_edit() {
		global $tax;

		if ( ! current_user_can( $tax->cap->edit_terms ) )
			return;
?>

	<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

			<fieldset><div class="inline-edit-col">
				<h4><?php _e( 'Quick Edit' ); ?></h4>

				<label>
					<span class="title"><?php _ex( 'Name', 'term name' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
				</label>
	<?php if ( !global_terms_enabled() ) { ?>
				<label>
					<span class="title"><?php _e( 'Slug' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value="" /></span>
				</label>
	<?php } ?>
			</div></fieldset>
	<?php

		$core_columns = array( 'cb' => true, 'description' => true, 'name' => true, 'slug' => true, 'posts' => true );

		list( $columns ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			if ( isset( $core_columns[$column_name] ) )
				continue;

			do_action( 'quick_edit_custom_box', $column_name, 'edit-tags', $tax->name );
		}

	?>

		<p class="inline-edit-save submit">
			<a accesskey="c" href="#inline-edit" title="<?php _e( 'Cancel' ); ?>" class="cancel button-secondary alignleft"><?php _e( 'Cancel' ); ?></a>
			<?php $update_text = $tax->labels->update_item; ?>
			<a accesskey="s" href="#inline-edit" title="<?php echo esc_attr( $update_text ); ?>" class="save button-primary alignright"><?php echo $update_text; ?></a>
			<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
			<span class="error" style="display:none;"></span>
			<?php wp_nonce_field( 'taxinlineeditnonce', '_inline_edit', false ); ?>
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $tax->name ); ?>" />
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
	<?php
	}
		
}
