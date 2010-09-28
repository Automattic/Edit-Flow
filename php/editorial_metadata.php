<?php

// TODO: Refactor/fix all the post meta boxes. Reuse code more.
// TODO: Allow reordering of metadata terms?
class ef_editorial_metadata {

	var $metadata_taxonomy;
	var $metadata_postmeta_key;
	var $metadata_separator;
	var $metadata_string;

	function __construct() {
		$this->metadata_taxonomy = 'ef_editorial_meta';
		$this->metadata_postmeta_key = "_{$this->metadata_taxonomy}";
		$this->metadata_separator = '|';
		$this->metadata_string = __( 'Metadata Type', 'edit-flow' );
		$this->metadata_taxonomy_display_filters();
		
		add_action( 'init', array( &$this, 'register_taxonomy' ) );
		add_action( 'admin_init', array( &$this, 'handle_post_metaboxes' ) );
	}
	
	function metadata_taxonomy_display_filters() {
		global $pagenow;
		
		if ( $pagenow == 'edit-tags.php' ) { // TODO: also should check that we're editing $this->metadata_taxonomy. How?
			// Edit the columns for the post metadata taxonomy page (remove the description, add the post metadata type)
			add_filter( "manage_edit-{$this->metadata_taxonomy}_columns", array( &$this, 'edit_column_headers' ) );
			add_filter( "manage_{$this->metadata_taxonomy}_custom_column", array( &$this, 'add_custom_columns' ), 10, 3 );
			
			// Specify a particular ordering of rows for the post metadata taxonomy page 
			add_filter( "get_terms_orderby", array( &$this, 'order_metadata_rows' ), 10, 2 );
			
			// Insert and remove some fields when adding or removing terms from the post metadata taxonomy edit page
			add_action( "{$this->metadata_taxonomy}_add_form_fields", array( &$this, "add_form_fields" ) );
			add_action( "{$this->metadata_taxonomy}_edit_form_fields", array( &$this,"edit_form_fields" ), 10, 2 );
			
			// Copy the metadata_type into the description field before it's inserted into the database
			add_filter( "pre_{$this->metadata_taxonomy}_description", array( &$this, 'insert_metadata_type_into_description_field' ) );
		}
		
		// Adding a term happens via admin-ajax.php, so make sure we copy the metadata_type into description then too
		if ($pagenow == 'admin-ajax.php') {
			add_filter( "pre_{$this->metadata_taxonomy}_description", array( &$this, 'insert_metadata_type_into_description_field' ) );
		}
	}
	
	function insert_metadata_type_into_description_field( $description ) {
		if ( !empty( $_POST ) && !empty( $_POST[$this->metadata_taxonomy] ) )
			return $_POST[$this->metadata_taxonomy];
		else
			return $description;
	}
	
	function add_form_fields($taxonomy) {
?>
		<div class="form-field">
			<label for="<?php echo $this->metadata_taxonomy; ?>"><?php echo $this->metadata_string; ?></label>
			<?php $this->get_select_html(0, $taxonomy); ?>
			<p>Choose which type of metadata you would like to create.</p>
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$("textarea#tag-description").parent().hide();
			});
		</script>
<?php
	}
	
	function edit_form_fields($term, $taxonomy) {
?>
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="<?php echo $this->metadata_taxonomy; ?>"><?php echo $this->metadata_string; ?></label></th>
			<td><?php $this->get_select_html( $term, $taxonomy ); ?></td>
		</tr>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$("textarea#description").parent().parent().hide();
			});
		</script>
<?php
	}
	
	function get_select_html($term, $taxonomy) {
		$metadata_type = $this->get_metadata_type( $term );
?>
		<select id="<?php echo $this->metadata_taxonomy; ?>" name="<?php echo $this->metadata_taxonomy; ?>">
			<option value="checkbox" <?php selected( 'checkbox', $metadata_type ); ?>>Checkbox</option> 
			<option value="date" <?php selected( 'date', $metadata_type ); ?>>Date</option>
			<option value="location" <?php selected( 'location', $metadata_type ); ?>>Location</option>
			<option value="paragraph" <?php selected( 'paragraph', $metadata_type ); ?>>Paragraph</option>
			<option value="text" <?php selected( 'text', $metadata_type ); ?>>Text</option>
			<option value="user" <?php selected( 'user', $metadata_type ); ?>>User</option> 
		</select>
<?php
	}
	
	function edit_column_headers( $column_headers ) {
		// TODO: implement this using array_diff or array_unshift or something better?
		$new_headers = array();
		foreach ( $column_headers as $column_name => $column_display_name ) {
			if ( $column_name != 'description' ) {
				$new_headers[$column_name] = $column_display_name;
			} else { // Put the new column in the place of description
				$new_headers[$this->metadata_taxonomy] = $this->metadata_string;
			}
		}
		
		return $new_headers;
	}
	
	function add_custom_columns( $empty_string, $column_name, $term_id ) {
		if ( $column_name == $this->metadata_taxonomy ) {
			$term = get_term_by( 'term_id', $term_id, $this->metadata_taxonomy );
			$separator_pos = strpos( $metadata_type, $this->metadata_separator );
			$metadata_type = $term->description;
			$metadata_type = $separator_pos ? substr( $metadata_type, $separator_pos + 1 ) : $metadata_type;
			return $metadata_type;
		}
	}
	
	/**
	 * Gets the metadata type described by this term, stored in the term itself. Usually stored in $term->description.
	 *
	 * @param object|string|int $value Term from which to get the metadata object (object or term_id) or the metadata type itself.
	 */
	function get_metadata_type($value) {
		if ( is_object( $value ) ) {
			return $value->description;
		} else if ( is_int( $value ) && $value > 0 ) {
			return get_term_field( 'description', $value, $this->metadata_taxonomy, 'raw' );
		} else
			return $value;
	}
	
	function get_postmeta_key( $term ) {
		$key = $this->metadata_postmeta_key;
		$type = $this->get_metadata_type( $term );
		$prefix = "{$key}_{$type}";
		return "{$prefix}_" . ( is_object( $term ) ? $term->term_id : $term );
	}
	
	function get_editorial_metadata_terms() {
		return get_terms( $this->metadata_taxonomy, array(
				'orderby'	 => 'description',
				'hide_empty' => false
			)
		);
	}
	
	function order_metadata_rows($orderby, $args) {
		global $current_screen;
		
		if ( $current_screen->id == "edit-{$this->metadata_taxonomy}" ) // only sort by description when editing metadata
			return apply_filters( 'ef_editorial_metadata_term_sort_order', 'tt.description' );
		else // TODO: is this needed if the orderby filter were only added on the metadata screen? (it isn't now, it's on all edit-tags screens, but maybe it could be)
			return $orderby;
	}
	
	// Register the post metadata taxonomy and add some default terms
	
	function register_taxonomy() {
		register_taxonomy( $this->metadata_taxonomy, array( 'post' ),
			array(
				'public' => false,
				'labels' => array(
					'name' => _x( 'Editorial Metadata', 'taxonomy general name' ),
					'singular_name' => _x( 'Editorial Metadata', 'taxonomy singular name' ),
						'search_items' => __( 'Search Editorial Metadata' ),
						'popular_items' => __( 'Popular Editorial Metadata' ),
						'all_items' => __( 'All Editorial Metadata' ),
						'edit_item' => __( 'Edit Editorial Metadata' ),
						'update_item' => __( 'Update Editorial Metadata' ),
						'add_new_item' => __( 'Add New Editorial Metadata Term' ),
						'new_item_name' => __( 'New Editorial Metadata Term' ),
					)
			)
		);
		
		// TODO: Remove these for production use. Or at least make sure they are only inserted once!
		$default_metadata = array(
			array(
				'term' => 'Photographer',
				'args' => array( 
					'slug' => 'photographer',
					'description' => 'user')
			),
			array(
				'term' => 'Due Date',
				'args' => array( 
					'slug' => 'due-date',
					'description' => 'date')
			),
			array(
				'term' => 'Notes',
				'args' => array( 
					'slug' => 'interview-notes',
					'description' => 'paragraph')
			),
			array(
				'term' => 'Contact information',
				'args' => array( 
					'slug' => 'contact-information',
					'description' => 'paragraph')
			),
			array(
				'term' => 'Location',
				'args' => array( 
					'slug' => 'location',
					'description' => 'location')
			),
			array(
				'term' => 'Needs photo',
				'args' => array( 
					'slug' => 'needs-photo',
					'description' => 'checkbox')
			),
		);
		
		foreach ( $default_metadata as $term )
			if ( !is_term( $term['term'] ) )
				wp_insert_term( $term['term'], $this->metadata_taxonomy, $term['args'] );
				
	}
	
	// Post metabox stuff:
	
	function handle_post_metaboxes() {
		if ( function_exists( 'add_meta_box' ) ) {
			add_meta_box( $this->metadata_taxonomy, __( 'Editorial Metadata', 'edit-flow' ), array( &$this, 'display_meta_box' ), 'post', 'normal', 'high' ); // todo: remove high priority
			add_action( 'save_post', array(&$this, 'save_meta_box'), 10, 2 );			
			add_action( 'edit_post', array( &$this, 'save_meta_box' ), 10, 2 );
			add_action( 'publish_post', array( &$this, 'save_meta_box' ), 10, 2 );
		}
	}
	
	function display_meta_box( $post ) {
		// Add CSS so that labels (particularly for textareas) are aligned to the top
?>
		<style type="text/css">
			#<?php echo "{$this->metadata_taxonomy}_meta_box"; ?> label {
				vertical-align: top;
			}
		</style>
<?php
		echo "<div id='{$this->metadata_taxonomy}_meta_box'>";
		// Add nonce for verification upon save
		echo "<input type='hidden' name='{$this->metadata_taxonomy}_nonce' value='" . wp_create_nonce(__FILE__) . "' />";
	
		$terms = $this->get_editorial_metadata_terms();
		foreach ( $terms as $term ) {
			$postmeta_key = $this->get_postmeta_key( $term );
			$current_metadata = esc_attr( get_post_meta( $post->ID, $postmeta_key, true ) );
			switch( $this->get_metadata_type( $term ) ) {
				case "date":
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					echo "<input id='$postmeta_key' name='$postmeta_key' type='text' class='date-pick' value='$current_metadata' />";
					break;
				case "location":
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					echo "<input id='$postmeta_key' name='$postmeta_key' type='text' value='$current_metadata' />";
					if ( !empty( $current_metadata ) )
						echo "&nbsp;&nbsp;<a href='http://maps.google.com/?q={$current_metadata}&t=m' target='_blank'>Google Map for $current_metadata</a>";
					break;
				case "text":
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					echo "<input id='$postmeta_key' name='$postmeta_key' type='text' value='$current_metadata' />";
					break;
				case "paragraph":
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					echo "<textarea id='$postmeta_key' name='$postmeta_key'>$current_metadata</textarea>";
					break;
				case "checkbox":
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					echo "<input id='$postmeta_key' name='$postmeta_key' type='checkbox' value='1' " . checked($current_metadata, 1, false) . " />";
					break;
				case "user": 
					echo "<label for='$postmeta_key'>{$term->name}: </label>";
					$user_dropdown_args = array( 
							'show_option_all' => __( '-- Select a user below --' ), 
							'name'     => $postmeta_key,
							'selected' => $current_metadata 
						); 
					wp_dropdown_users( $user_dropdown_args );
					break;
				default:
					echo "<p>This editorial metadata type is not yet supported</p>";
			}
			echo "<p></p>";
		} // Done iterating through metadata terms
		$this->print_date_scripts_and_styles();
		echo "</div>";
	}
	
	function print_date_scripts_and_styles() {
	// TODO: add this all via filters and enqueue_script (dependency on jQuery, obviously)
?>
	<script src="<?php echo EDIT_FLOW_URL; ?>js/lib/date.js" type="text/javascript"></script>
	<script src="<?php echo EDIT_FLOW_URL; ?>js/lib/jquery.datePicker.js" type="text/javascript"></script>
	<script type="text/javascript">
	Date.firstDayOfWeek = <?php echo get_option( 'start_of_week' ); ?>;
	Date.format = 'mm/dd/yyyy';
	jQuery(document).ready(function($) {
		$('.date-pick')
			.datePicker({
				createButton: false,
				startDate: '01/01/2010',
				endDate: (new Date()).asString(),
				clickInput: true}
				);
	});
	</script>
	
	<style type="text/css">
	@import url("<?php echo EDIT_FLOW_URL; ?>css/datepicker-editflow.css");
	</style>
<?php
	}
	
	function save_meta_box( $id, $post ) {
		// Authentication checks: make sure data came from our meta box and that the current user is allowed to edit the post
		if ( !wp_verify_nonce( $_POST[$this->metadata_taxonomy . "_nonce"], __FILE__ )
			|| !current_user_can( 'edit_post', $id )
			|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $id;
		}
		
		// Authentication passed, save data		
		$terms = $this->get_editorial_metadata_terms();
		foreach ( $terms as $term ) {
			// Setup the key for this editorial metadata term (same as what's in $_POST
			$key = $this->get_postmeta_key( $term );
			
			// Get the current editorial metadata
			// TODO: do we care about the current_metadata at all?
			//$current_metadata = get_post_meta( $id, $key, true );
			
			// $new_metadata = addslashes_gpc( $_POST[$key] ); // TODO: is this necessary?
			$new_metadata = $_POST[$key];
			
			if ( empty ( $new_metadata ) )
				delete_post_meta( $id, $key );
			else
				update_post_meta( $id, $key, $new_metadata );
		}
	}
	
} // END class

?>