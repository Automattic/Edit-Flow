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
 * A bunch of TODOs
 * @todo Fully document this class.
 * @todo Add ability for drag-drop of metadata terms?
 * @todo Add ability to specify "due date" in settings based on one of the date metadata fields? Then the calendar could use that again.
 */
if ( !class_exists('EF_Editorial_Metadata') ) {

class EF_Editorial_Metadata {

	/**
	 * The name of the taxonomy we're going to register for editorial metadata. This could be a
	 * const, but then it would be harder to use in PHP strings, so we'll keep it as a variable.
	 */
	var $metadata_taxonomy;
	var $metadata_postmeta_key;
	var $metadata_string;
	var $screen_id;
	
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
		$this->metadata_taxonomy = 'ef_editorial_meta';
		$this->screen_id = "edit-{$this->metadata_taxonomy}";
		$this->metadata_postmeta_key = "_{$this->metadata_taxonomy}";
		$this->metadata_string = __( 'Metadata Type', 'edit-flow' );
		
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'register_taxonomy' ) );
		add_action( 'admin_init', array( &$this, 'metadata_taxonomy_display_hooks' ) );
		add_action( 'add_meta_boxes', array( &$this, 'handle_post_metaboxes' ) );
		add_action( 'save_post', array( &$this, 'save_meta_box' ), 10, 2 );
		
		// Load necessary scripts and stylesheets
		add_action( 'admin_enqueue_scripts', array( &$this, 'add_admin_scripts' ) );
		
	} // END: __construct()
	
	/**
	 * init()
	 */
	function init() {
		global $edit_flow;
		add_post_type_support( 'post', 'ef_editorial_metadata' );
		add_post_type_support( 'page', 'ef_editorial_metadata' );
	} // END: init()
	
	/**
	 * metadata_taxonomy_display_hooks()
	 * Manipulates WordPress' standard taxonomy view to add fields specific to editorial metadata
	 */
	function metadata_taxonomy_display_hooks() {
		global $pagenow;
		
		if ( $pagenow == 'edit-tags.php' ) {
			// Specify a particular ordering of rows for the post metadata taxonomy page 
			add_filter( "get_terms_orderby", array( &$this, 'order_metadata_rows' ), 10, 2 );
			
			// Insert and remove some fields when adding or removing terms from the post metadata taxonomy edit page
			add_action( "{$this->metadata_taxonomy}_add_form_fields", array( &$this, "add_form_fields" ) );
			add_action( "{$this->metadata_taxonomy}_edit_form_fields", array( &$this,"edit_form_fields" ), 10, 2 );
		}
		
		// Adding a term happens via admin-ajax.php, so make sure we copy the metadata_type into description then too
		if ( $pagenow == 'edit-tags.php' || $pagenow == 'admin-ajax.php' ) {
			// Edit the columns for the post metadata taxonomy page (remove the description, add the post metadata type)
			add_filter( "manage_edit-{$this->metadata_taxonomy}_columns", array( &$this, "edit_column_headers" ) );
			add_filter( "manage_{$this->metadata_taxonomy}_custom_column", array( &$this, "add_custom_columns" ), 10, 3 );
			
			add_filter( "pre_{$this->metadata_taxonomy}_description", array( &$this, "insert_metadata_into_description_field" ) );
			
			// Enforce that a metadata slug cannot be change once the term is created
			// We could use edit_{$taxonomy}, but then the value returned by AJAX call on quick edits would still be changed
			add_action( "edit_terms", array( &$this, "pre_edit_term" ) );
			add_action( "edited_terms", array( &$this, "edited_term" ) );
			
			// Enforce that a metadata type cannot be changed once the term is created
			add_action( "edit_term_taxonomy", array( &$this, "pre_edit_term_taxonomy" ), 10, 2);
			add_action( "edited_term_taxonomy", array( &$this, "edited_term_taxonomy" ), 10, 2);
		}
	} // END: metadata_taxonomy_display_hooks()
	
	/**
	 * insert_metadata_into_description_field()
	 *
	 * @param string $description User-submitted description
	 * @return string $encoded_description JSON-encoded array to insert into description field
	 */
	function insert_metadata_into_description_field( $description ) {
		
		$field_prefix = $this->metadata_taxonomy . '_';
		$metadata_type = isset( $_POST[$field_prefix . self::metadata_type_key] ) ? $_POST[$field_prefix . self::metadata_type_key] : '';
		if ( isset( $_POST[$field_prefix . self::description] ) ) {
			$metadata_description = $_POST[$field_prefix . self::description];
		} else if ( $_POST['action'] == 'add-tag' ) {
			// If the posted metadata description is empty, use the given description
			// This code path is executed when adding a term, but should not be executed when editing a term
			$metadata_description = $description;
		} else if ( $_POST['action'] == 'inline-save-tax' ) {
			// This code path is executing when quick editing a term, in which case we have a slashed version of the current description
			$metadata_description = $this->get_unencoded_value( $description, self::description );
		}
		
		$encoded_description = $this->get_encoded_description( $metadata_description, $metadata_type );
		
		return $encoded_description;
		
	} // END: insert_metadata_into_description_field()
	
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
	 * add_form_fields()
	 */
	function add_form_fields( $taxonomy ) {
	?>
		<div class="form-field">
			<label for="<?php echo $this->metadata_taxonomy . '_' . self::metadata_type_key;; ?>"><?php echo $this->metadata_string; ?></label>
			<?php $this->get_select_html(0); ?>
			<p><?php _e( 'Choose which type of metadata you would like to create.', 'edit-flow' ); ?></p>
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Inform users that their postmeta isn't going anywhere but they have to re-add the deleted term if
				// they want to see it. Add this information to the JS confirm dialog upon term deletion
				
				var msg = "<?php _e('\n\nAny metadata for this term will remain but will not be visible unless this term is re-added.', 'edit-flow'); ?>";
				commonL10n.warnDelete += msg; // This is the string in the DOM shown on deletion
				
				<?php if ( isset($_GET['message']) && ( $msg = (int) $_GET['message'] ) && ( $msg === 2 || $msg === 6 ) ) : ?>
					var msgSingleTerm = "<?php _e('Any metadata for the deleted term will remain but will not be visible unless the term is re-added.', 'edit-flow'); ?>";
					var msgMultipleTerms = "<?php _e('Any metadata for the deleted terms will remain but will not be visible unless the terms are re-added.', 'edit-flow'); ?>";
					<?php if ( $msg === 2 ) : ?>
						var msg = msgSingleTerm;
					<?php elseif ( $msg === 6 ) : ?>
						var msg = msgMultipleTerms;
					<?php endif; ?>
					jQuery("#message p").append(" " + msg);
				<?php endif; ?>
			});
		</script>
	<?php
	} // END: add_form_fields()
	
	/**
	 * edit_form_fields()
	 */
	function edit_form_fields( $term, $taxonomy ) {
		// We need to add a new textarea for description that is just like the default one but that contains the right name, ID, and content
		// The default one would have ugly serialized data in it.
		$field_prefix = $this->metadata_taxonomy . '_';
		$metadata_types = $this->get_supported_metadata_types();
		$type = $this->get_metadata_type( $term );
		// For some reason the description's HTML is encoded when we get it as an object
		$description = $this->get_unencoded_value( $term->description, self::description );
		?>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="<?php echo $field_prefix . self::description; ?>"><?php _ex('Description', 'Taxonomy Description'); ?></label></th>
			<td>
				<textarea rows="5" cols="40" name="<?php echo $field_prefix . self::description; ?>" id="<?php echo $field_prefix . self::description; ?>"><?php
					// Process out any '<' and '>', and change <br /> to newlines so it displays properly in the textarea
					$description = preg_replace( "/&lt;/", "<", $description );
					$description = preg_replace( "/&gt;/", ">", $description );					
					$description = preg_replace( "/(<br\s*\/?>\s*)/", "\r\n", $description );
					echo $description;
					?></textarea><br />
				<span class="description"><?php _e( 'The description is not prominent by default, however some themes may show it.', 'edit-flow' ); ?></span>
			</td>
		</tr>
		<?php
		// People could try to change the value of the hidden field below (the metadata type), but they'd get nowhere as
		// pre_edit_term and edited_term will stop them in their tracks!
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><?php _e('Type', 'edit-flow'); ?></th>
			<td>
				<input type="text" disabled="disabled" value="<?php echo $metadata_types[$type]; ?>" /><br />
				<span class="description"><?php _e( 'The metadata type cannot be changed once created.', 'edit-flow' ); ?></span>
			</td>
		</tr>
		<input type="hidden" name="<?php echo $this->metadata_taxonomy . '_' . self::metadata_type_key; ?>" value="<?php echo $type; ?>" />
	<?php
	} // END: edit_form_fields()
	
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
	 * edit_column_headers()
	 *
	 * @param array $column_headers Original column headers
	 * @return array $new_headers Modified column headers
	 */
	function edit_column_headers( $column_headers ) {
		$new_headers = array();
		// Don't display the 'slug' column or the 'posts' column
		unset( $column_headers['slug'] );
		unset( $column_headers['posts'] );
		
		foreach ( $column_headers as $column_name => $column_display_name ) {
			if ( $column_name == 'description' ) {
				// Put the new columns in the place of description
				$new_headers[self::metadata_type_key] = $this->metadata_string;
				$new_headers[self::description] = __( 'Description', 'edit-flow' );
			} else {
				$new_headers[$column_name] = $column_display_name;
			}
		}
		
		return $new_headers;
	} // END: edit_column_headers()
	
	/**
	 * add_custom_columns()
	 */
	function add_custom_columns( $empty_string, $column_name, $term_id ) {
		// Get the full description from the DB and unserialize into an array
		$term = $this->get_editorial_metadata_term( (int) $term_id );
		// Display the information from the DB for this row to the user for our custom columns
		if ( $column_name == self::metadata_type_key ) {
			// Return the display (pretty) type for the metadata. e.g. 'Location' instead of 'location'
			$metadata_types = $this->get_supported_metadata_types();
			return $metadata_types[$this->get_metadata_type( $term )];
		} else if ( $column_name == self::description ) {
			$description = $this->get_unencoded_value( $term->description, self::description );;
			return $description;
		}
	} // END: add_custom_columns()
	
	/**
	 * add_admin_scripts()
	 * Enqueue relevant admin Javascript
	 */ 
	function add_admin_scripts() {
		global $current_screen, $edit_flow;
		
		// Add the metabox date picker JS and CSS
		$current_post_type = $edit_flow->get_current_post_type();
		if ( post_type_supports( $current_post_type, 'ef_editorial_metadata' ) ) {
			// First add the datepicker JS
			wp_enqueue_script('edit_flow-date-lib', EDIT_FLOW_URL . 'js/lib/date.js', false, EDIT_FLOW_VERSION, true);
			wp_enqueue_script('edit_flow-date_picker-lib', EDIT_FLOW_URL . 'js/lib/jquery.datePicker.js', array( 'jquery' ), EDIT_FLOW_VERSION, true);
			?>
			<script type="text/javascript">
				Date.firstDayOfWeek = <?php echo get_option( 'start_of_week' ); ?>;
			</script>
			<?php
			wp_enqueue_script('edit_flow-date_picker', EDIT_FLOW_URL . 'js/ef_date.js', array( 'edit_flow-date_picker-lib', 'edit_flow-date-lib' ), false, true);
			
			// Now add the rest of the metabox CSS
			wp_enqueue_style('edit_flow-datepicker-styles', EDIT_FLOW_URL . 'css/datepicker-editflow.css', false, EDIT_FLOW_VERSION, 'all');
			wp_enqueue_style('edit_flow-editorial_metadata-styles', EDIT_FLOW_URL . 'css/ef_editorial_metadata.css', false, EDIT_FLOW_VERSION, 'all');
		}
		
		// Either editing the taxonomy or a specific term
		if ( $current_screen->id == $this->screen_id ) {
			wp_enqueue_script( 'edit_flow-editorial_metadata', EDIT_FLOW_URL . 'js/ef_editorial_metadata.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
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
	
	function order_metadata_rows($orderby, $args) {
		global $current_screen;
		
		// TODO: add following check in other methods (if possible)
		if ( $current_screen->id == "edit-{$this->metadata_taxonomy}" ) // only sort by description when editing metadata
			return apply_filters( 'ef_editorial_metadata_sort_order', 'name' );
		else // TODO: is this needed if the orderby filter were only added on the metadata screen? (it isn't now, it's on all edit-tags screens, but maybe it could be)
			return $orderby;
	}
		
	// -------------------------
	// Ensure that metadata slugs and types do not change after creation
	// -------------------------
	
	function pre_edit_term( $term_id ) {
		$term = get_term( $term_id, $this->metadata_taxonomy );
		if ( !is_null( $term ) ) {
			// We'll only get a non-null result if we're editing a editorial_meta term (since that's the taxonomy we pass above)
			$this->metadata_slug_cache = $term->slug;
		}
	}
	
	function edited_term( $term_id ) {
		global $wpdb;
		$term = get_term( $term_id, $this->metadata_taxonomy );
		if ( !is_null( $term ) ) {
			// As above, we'll only get a non-null result if we're editing a editorial_meta term (since that's the taxonomy we pass above)
			// Switch back to the cached slug before the attempted update
			$wpdb->update( $wpdb->terms, array( 'slug' => $this->metadata_slug_cache ), compact( 'term_id' ) );
		}
	}
	
	function pre_edit_term_taxonomy( $tt_id, $taxonomy ) {
		if ( $taxonomy === $this->metadata_taxonomy ) {
			global $wpdb;
			
			// TODO: Is get_row the right function to use? Can this be done with a $wpdb function rather than a custom query?
			$desc = $wpdb->get_row( $wpdb->prepare( "SELECT description FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d AND taxonomy = %s", $tt_id, $taxonomy ) )->description;
			$this->metadata_type_cache = $this->get_unencoded_value( $desc, self::metadata_type_key );
		}
	}
	
	function edited_term_taxonomy( $tt_id, $taxonomy ) {
		if ( $taxonomy === $this->metadata_taxonomy ) {
			global $wpdb;
			
			// Get newly saved metadata type
			// TODO: Same as above - can this be done better?
			$encoded_description = $wpdb->get_row( $wpdb->prepare( "SELECT description FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d AND taxonomy = %s", $tt_id, $taxonomy ) )->description;
			
			// If the new type is different from the old type, we need to revert
			if ( $this->metadata_type_cache !== $this->get_unencoded_value( $encoded_description, self::metadata_type_key ) ) {
				$metadata_description = $this->get_unencoded_value( $encoded_description, self::description );
				$updated_encoded_description = $this->get_encoded_description( $metadata_description, $this->metadata_type_cache );
				
				// Revert term type back to old type
				$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $updated_encoded_description ), array( 'term_taxonomy_id' => $tt_id ) );
			} else {
				// Metadata type hasn't changed, so do nothing
			}
		}
		$this->metadata_type_cache = NULL;
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
		if ( function_exists( 'add_meta_box' ) ) {
			
			// Add the editorial meta meta_box for all of the post types we want to support
			$current_post_type = $edit_flow->get_current_post_type();
			$post_types = $edit_flow->get_all_post_types_for_feature( 'ef_editorial_metadata' );
			foreach ( $post_types as $post_type ) {
				add_meta_box( $this->metadata_taxonomy, __( 'Editorial Metadata', 'edit-flow' ), array( &$this, 'display_meta_box' ), $post_type, 'side' );
			}
		
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
	
} // END: class EF_Editorial_Metadata

} // END: if ( !class_exists('EF_Editorial_Metadata') )