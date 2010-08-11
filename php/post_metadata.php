<?php

// TODO: Implement the metaboxes on the post page. All that is below is the term editing
class ef_custom_metadata {

	var $metadata_taxonomy = "ef_post_metadata";

	function __construct() {
		add_action( 'init', array( &$this, 'register_taxonomy' ) );
		
		$this->taxonomy_display_filters();
	}
	
	function taxonomy_display_filters() {
		global $pagenow;
		if($pagenow == 'edit-tags.php') {
			add_filter( "manage_edit-{$this->metadata_taxonomy}_columns", array( &$this, 'edit_column_headers' ) );
			add_filter( "manage_{$this->metadata_taxonomy}_custom_column", array( &$this, 'add_custom_columns' ), 10, 3 );
			add_filter( "get_terms_orderby", array( &$this, 'order_metadata_rows' ), 10, 2 );
			
			add_action('created_term', array( &$this, 'term_type_update' ), 10, 3 );
			add_action('edit_term', array( &$this, 'term_type_update' ), 10, 3 );
			add_action("{$this->metadata_taxonomy}_add_form_fields",array( &$this, "add_form_fields" ) );
			add_action("{$this->metadata_taxonomy}_edit_form_fields",array( &$this,"edit_form_fields" ), 10, 2 );
		}
	}
	
	function register_taxonomy() {
		register_taxonomy($this->metadata_taxonomy, array('post', 'page'),
			array(
				'public' => false,
				'labels' => array(
					'name' => _x( 'Post Metadata', 'taxonomy general name' ),
					'singular_name' => _x( 'Post Metadata', 'taxonomy singular name' ),
						'search_items' => __( 'Search Post Metadata' ),
						'popular_items' => __( 'Popular Post Metadata' ),
						'all_items' => __( 'All Post Metadata' ),
						'edit_item' => __( 'Edit Post Metadata' ),
						'update_item' => __( 'Update Post Metadata' ),
						'add_new_item' => __( 'Add New Post Metadata Field' ),
						'new_item_name' => __( 'New Post Metadata Field' ),
					)
			)
		);
		
		$default_metadata = array(
			array(
				'term' => 'Photographer',
				'args' => array( 
					'slug' => 'photographer',
					'description' => '1-user',)
			),
			array(
				'term' => 'Due Date',
				'args' => array( 
					'slug' => 'due-date',
					'description' => '2-date',)
			),
			array(
				'term' => 'Interview',
				'args' => array( 
					'slug' => 'interview',
					'description' => '3-short-text',)
			),
			array(
				'term' => 'Description',
				'args' => array( 
					'slug' => 'description',
					'description' => '5-long-text',)
			),
			array(
				'term' => 'Interview location',
				'args' => array( 
					'slug' => 'interview-location',
					'description' => '4-location',)
			),
		);
		
		/*foreach ( $default_metadata as $term )
			if ( !is_term( $term['term'] ) )
				wp_insert_term( $term['term'], $this->metadata_taxonomy, $term['args'] );*/
				
	}
	
	 // This hook is called after adding and editing to save $_POST['tag-term']
	function term_type_update($term_id, $tt_id, $taxonomy) {
		//wp_die("term_id: $term_id, tt_id: $tt_id, taxonomy: $taxonomy");
		if ( isset( $_POST['metadata-type'] ) ) {
			$this->update_taxonomy_term_type( $term_id, $taxonomy, $_POST['metadata-type'] );
		}
	}
	
	function get_metadata_type($term, $taxonomy) {
		if ( is_int( $term ) && $term == 0)
			return $term;
		if ( is_object( $term) )
			$term = $term->term_id;
		return get_term_field( 'description', $term, $taxonomy );
	}
	
	function update_taxonomy_term_type($term_id, $taxonomy, $value) {
		// TODO: figure out why this causes a server timeout!
		wp_update_term( $term_id, $taxonomy, array( 'description' => $value ) );
	}
	
	function add_form_fields($taxonomy) {
	?>
		<div class="form-field">
			<label for="metadata-type">Type</label>
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
			<th scope="row" valign="top"><label for="metadata-type">Type</label></th>
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
		// TODO: make this functional
		$selected = selected( $this->get_metadata_type( $term, $taxonomy ) );
	?>
		<select id="metadata-type" name="metadata-type">
			<option value="user" <?php echo $selected; ?> >User</option>
			<option value="text" <?php echo $selected; ?> >Text</option>
			<option value="long-text" <?php echo $selected; ?> >Long Text</option>
			<option value="date" <?php echo $selected; ?> >Date</option>
			<option value="location" <?php echo $selected; ?> >Location</option>
		</select>
	<?php
	}
	
	function edit_column_headers($column_headers) {
		// TODO: implement this using array_diff or array_unshift or something better
		$new_headers = array();
		foreach ( $column_headers as $column_name => $column_display_name )
			if ( $column_name != 'description' )
				$new_headers[$column_name] = $column_display_name;
		
		$new_headers['type'] = 'Type';
		
		return $new_headers;
	}
	
	function add_custom_columns($empty_string, $column_name, $term_id) {
		if ( $column_name == 'type' ) {
			$term = get_term_by( 'term_id', $term_id, $this->metadata_taxonomy );
			$type = strpos($term->description, '-') ? substr($term->description, strpos($term->description, '-') + 1) : $term->description;
			return $type;
		}
	}

	function order_metadata_rows($orderby, $args) {
		global $current_screen;
		
		if ( $current_screen->id == 'edit-ef_post_metadata' ) // only sort by description when editing metadata
			return "tt.description";
		else
			return $orderby;
	}
}
	
	function printarr($arr) {
		echo "<pre>";print_r($arr);echo "</pre>";
	}

?>