<?php

// TODO: Refactor/fix all the post meta boxes. Reuse code more, and figure out postmeta
//		 	storage mechanism (need to store with more than just the same key!)
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
		add_action( 'admin_menu', array( &$this, 'add_post_metaboxes' ) );
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
			<option value="text" <?php selected( 'text', $metadata_type ); ?>>Text</option>
			<option value="paragraph" <?php selected( 'paragraph', $metadata_type ); ?>>Paragraph</option>
			<option value="date" <?php selected( 'date', $metadata_type ); ?>>Date</option>
			<option value="location" <?php selected( 'location', $metadata_type ); ?>>Location</option>
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
	 * Gets the metadata type described by this term, stored in the term itself. Usually stores in $term->description.
	 *
	 * @param string|object $value Term from which to get the metadata object or the metadata type itself.
	 */
	function get_metadata_type($value) {
		if ( is_object( $value ) )
			return $value->description;
		else
			return $value;
	}
	
	/* Expensive, makes database call.
	 * TODO: decide if this is necessary
	function get_metadata_type($term, $taxonomy) {
		if ( is_int( $term ) && $term == 0)
			return $term;
		if ( is_object( $term) )
			$term = $term->term_id;
		return get_term_field( 'description', $term, $taxonomy, 'raw' );
	}*/

	function order_metadata_rows($orderby, $args) {
		global $current_screen;
		
		if ( $current_screen->id == 'edit-ef_editorial_metadata' ) // only sort by description when editing metadata
			return "tt.description";
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
				'term' => 'Due Date',
				'args' => array( 
					'slug' => 'due-date',
					'description' => 'date')
			),
			array(
				'term' => 'Description',
				'args' => array( 
					'slug' => 'description',
					'description' => 'paragraph')
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
					'slug' => 'Contact-information',
					'description' => 'paragraph')
			),
			array(
				'term' => 'Location',
				'args' => array( 
					'slug' => 'location',
					'description' => 'location')
			),
		);
		
		foreach ( $default_metadata as $term )
			if ( !is_term( $term['term'] ) )
				wp_insert_term( $term['term'], $this->metadata_taxonomy, $term['args'] );
				
	}
	
	// Post metabox stuff:
	
	function add_post_metaboxes() {
		if ( function_exists( 'add_meta_box' ) )
			add_meta_box( $this->metadata_taxonomy, __( 'Editorial Metadata', 'edit-flow' ), array( &$this, 'display_metabox' ), 'post', 'normal', 'high' ); // todo: remove high priority
	}
	
	function display_metabox( $post ) {
		$metadata = get_terms( $this->metadata_taxonomy, array(
				'orderby'	 => 'description',
				'hide_empty' => false
			)
		);
		foreach ( $metadata as $metadatum ) {
			$metadata_type = $this->get_metadata_type($metadatum);
			switch( $metadata_type ) {
				case "date":
					echo "<label for='date'>{$metadatum->name}: </label>"; // TODO: Needs a more specific 'for'/name/id for this particular field, and below
					echo "<input id='date' name='date' type='text' class='date-pick' value='" . get_post_meta( $post->ID, $this->metadata_postmeta_key, true ) . "' />";
					break;
				case "location":
					echo "<label for='location'>{$metadatum->name}: </label>"; // TODO: Needs a more specific 'for'/name/id for this particular field, and below
					echo "<input id='location' name='location' type='text' value='" . get_post_meta( $post->ID, $this->metadata_postmeta_key, true ) . "' />";
					break;
				case "text":
					echo "<label for='text'>{$metadatum->name}: </label>"; // TODO: Needs a more specific 'for'/name/id for this particular field, and below
					echo "<input id='text' name='location' type='text' value='" . get_post_meta( $post->ID, $this->metadata_postmeta_key, true ) . "' />";
					break;
				case "paragraph":
					echo "<label for='paragraph'>{$metadatum->name}: </label>"; // TODO: Needs a more specific 'for'/name/id for this particular field, and below
					echo "<textarea id='paragraph' name='paragraph'>" . get_post_meta( $post->ID, $this->metadata_postmeta_key, true ) . "</textarea>";
					break;
				case "user": 
					echo "<label for='user'>{$metadatum->name}: </label>"; // TODO: Needs a more specific 'for'/name/id for this particular field, and below 
					$user_dropdown_args = array( 
							'show_option_all' => __( '-- Select a user below --' ), 
							'name'     => 'user',//$this->metadata_postmeta_key, // TODO: this should mimic the 'for' field above 
							'selected' => get_post_meta( $post->ID, $this->metadata_postmeta_key, true ) 
						); 
					wp_dropdown_users( $user_dropdown_args );
					break;
				default:
					echo "<p>This metadata type is not yet supported</p>";
			}
			echo "<p></p>";
		}
	}
	
} // END class

?>