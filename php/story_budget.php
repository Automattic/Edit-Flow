<?php

/**
 * This class displays a budgeting system for an editorial desk's publishing workflow.
 * This is a cursory attempt at implementation with many outstanding TODOs.
 *
 * Somewhat prioritized TODOs:
 * TODO: Integrate column number with Screen Options API
 * TODO: Make trash links work (using nonce) and quick edit links
 * TODO: Move JS and CSS to their own files
 * TODO: Review inline TODOs
 *
 * @author Scott Bressler
 */
class ef_story_budget {
	var $taxonomy_used = 'category';
	
	// TODO: Make this editable in Screen Options
	var $num_columns = 2;
	
	/**
	 * Construct a story_budget class. For now we're not doing anything here.
	 */
	function __construct() {
	}

	/**
	 * Create the story budget view. This calls lots of other methods to do its work. This will create
	 * the table navigation, then print the columns based on $this->num_columns, which will in turn
	 * print the stories themselves.
	 */
	function story_budget() {
		$cat = $_GET['cat'];
		if ( isset ( $cat ) && !empty( $cat ) ) {
			$terms = array();
			$terms[] = get_term( $cat, $this->taxonomy_used );
		} else {
			$terms = get_terms($this->taxonomy_used, 'orderby=name&order=asc&parent=0&hide_empty=0');
		}
		$terms = apply_filters( 'story_budget_reorder_terms', $terms ); // allow for reordering or any other filtering of terms

		$this->print_JS();
		$this->print_CSS();
		$this->table_navigation();
?>
		<div id="dashboard-widgets-wrap">
			<div id="dashboard-widgets" class="metabox-holder">
			<?php
				for ($i = 0; $i < $this->num_columns; ++$i) {
					$this->print_column($i, $terms);
				}
			?>
			</div>
		</div><!-- /dashboard-widgets -->
<?php
	}

	/**
	 * Get posts by term and any matching filters
	 * @todo Get this to actually work
	 */
	function get_matching_posts_by_term_and_filters( $term ) {
		global $wpdb, $edit_flow;
		
		// TODO: clean up this query, make it work with an eventual setup_postdata() call
		$query = "SELECT * FROM "/*$wpdb->users, */ . "$wpdb->posts 
					JOIN $wpdb->term_relationships
						ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
					WHERE ";
		
		$post_where = '';		
		
		// Only show approved statuses if we aren't filtering (post_status isn't set or it's 0 or empty), otherwise filter to status
		$post_status = $_GET['post_status'];
		if ( isset( $post_status ) && !empty( $post_status ) ) {
			$post_where .= $wpdb->prepare( "$wpdb->posts.post_status = %s ", $post_status );
		} else {
			$post_where .= "($wpdb->posts.post_status IN ('publish'";
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			foreach( $custom_statuses as $status ) {
				$post_where .= $wpdb->prepare( ", %s", $status->slug );
			}
			$post_where .= ')) ';
		}
		
		// Filter by post_author if it's set
		$post_author = $_GET['post_author'];
		if ( isset( $post_author ) && !empty( $post_author ) ) {
			$post_where .= $wpdb->prepare( "AND $wpdb->posts.post_author = %s ", (int)$post_author );
		}
		
		// Filter by start date if it's set
		$start_date = $_GET['start_date'];
		if ( isset( $start_date ) && !empty( $start_date ) ) {
			// strtotime basically handles turning any date format we give to the function into a valid timestamp
			// so we don't really care what date string format is used on the page, as long as it makes sense
			$mysql_time = date( 'Y-m-d', strtotime( $start_date ) );
			$post_where .= $wpdb->prepare( "AND ($wpdb->posts.post_date >= %s) ", $mysql_time );
		}
		
		// Filter by end date if it's set
		$end_date = $_GET['end_date'];
		if ( isset( $end_date ) && !empty( $end_date) ) {
			$mysql_time = date( 'Y-m-d', strtotime( $end_date ) );
			$post_where .= $wpdb->prepare( "AND ($wpdb->posts.post_date <= %s) ", $mysql_time );
		}
	
		// Limit results to the given category where type is 'post'
		$post_where .= $wpdb->prepare( "AND $wpdb->term_relationships.term_taxonomy_id = %d ", $term->term_taxonomy_id );
		$post_where .= " AND $wpdb->posts.post_type = 'post'";
		
		$query .= apply_filters( 'ef_story_budget_query_where', $post_where ) . ';';
		
		return $wpdb->get_results( $query );
	}
	
	/**
	 * Prints a single column in the story budget.
	 *
	 * @param int $col_num The column which we're going to print.
	 * @param array $terms The terms to print in this column.
	 */
	function print_column($col_num, $terms) {
		// If printing fewer than $this->num_columns terms, only print that many columns
		$num_columns = min( count ( $terms ), $this->num_columns );
?>
		<div class='postbox-container' style='width:<?php echo 98/$num_columns; ?>%'>
			<div class="meta-box-sortables">
			<?php
				for ($i = $col_num; $i < count($terms); $i += $num_columns)
					$this->print_term( $terms[$i] );
			?>
			</div>
		</div>
<?php
	}
	
	/**
	 * Prints the stories in a single term in the story budget.
	 *
	 * @param object $term The term to print.
	 */
	function print_term($term) {
		global $wpdb;
		$posts = $this->get_matching_posts_by_term_and_filters($term);
	//	$wpdb->show_errors();
	//	echo $wpdb->last_query;
	//	echo "<pre>"; print_r($term);echo "</pre>";
		if ( !empty( $posts ) ) : // TODO: is this necessary if get_terms above behaves correctly?
		
?>
	<div class="postbox">
		<div class="handlediv" title="Click to toggle"><br /></div>
		<h3 class='hndle'><span><?php echo $term->name; ?></span></h3>
		<div class="inside">
			<table class="widefat post fixed story-budget" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" id="title" class="manage-column column-title" >Title</th>
						<th scope="col" id="author" class="manage-column column-author">Author</th>
						<th scope="col" id="status" class="manage-column column-author">Status</th>
						<th scope="col" id="categories" class="manage-column column-categories" title="Subcategories of <?php echo $term->name;?>">Subcats</th>
					</tr>
				</thead>

				<tfoot></tfoot>

				<tbody>
				<?php
					foreach ($posts as $post)
						$this->print_post($post, $term);
				?>
				</tbody>
			</table>
		</div>
	</div>
<?php
		endif;
	}
	
	/**
	 * Prints a single post in the story budget.
	 *
	 * @param object $post The post to print.
	 * @param object $parent_term The top-level term to which this post belongs.
	 */
	function print_post( $the_post, $parent_term ) {
		global $post;
		$post = $the_post; // TODO: this isn't right - need to call setup_postdata($the_post). But that doesn't work. Why?
		$authordata = get_userdata($post->post_author); // get the author data so we can use the author's display name
		
		// Build filtering URLs for post_author and post_status
		$filter_url = admin_url() . EDIT_FLOW_STORY_BUDGET_PAGE;	
		$author_filter_url = $filter_url . '&post_author=' . $post->post_author;
		$status_filter_url = $filter_url . '&post_status=' . $post->post_status;
		// Add any existing $_GET parameters to filter links in printed post
		if ( isset($_GET['post_status']) && !empty( $_GET['post_status'] )  ) {
			$author_filter_url .= '&post_status=' . $_GET['post_status'];
		}
		if ( isset( $_GET['post_author'] ) && !empty( $_GET['post_author'] ) ) {
			$status_filter_url .= '&post_author=' . $_GET['post_author'];
		}
		if ( isset( $_GET['start_date'] ) && !empty( $_GET['start_date'] ) ) {
			$author_filter_url .= '&start_date=' . $_GET['start_date'];
			$status_filter_url .= '&start_date=' . $_GET['start_date'];
		}
		if ( isset( $_GET['end_date'] ) && !empty( $_GET['end_date'] ) ) {
			$author_filter_url .= '&end_date=' . $_GET['end_date'];
			$status_filter_url .= '&end_date=' . $_GET['end_date'];
		}
		
		// TODO: use these two lines before and after calling the_excerpt() once setup_postdata works correctly
		//add_filter( 'excerpt_length', array( &$this, 'story_budget_excerpt_length') );
		//remove_filter( 'excerpt_length', array( &$this, 'story_budget_excerpt_length') );
		
?>
			<tr id='post-<?php echo $post->ID; ?>' class='alternate author-self status-publish iedit' valign="top">
				<td class="post-title column-title">
					<strong><a class="row-title" href="post.php?post=<?php echo $post->ID; ?>&action=edit" title="Edit &#8220;<?php echo $post->post_title; ?>&#8221;"><?php echo $post->post_title; ?></a></strong>
					<p><?php echo substr($post->post_content, 0, 5 * $this->story_budget_excerpt_length(0)); // TODO: just call the_excerpt once setup_postadata works ?></p>
					<p><?php do_action('story_budget_post_details'); ?></p>
					<div class="row-actions"><span class='edit'><a href="post.php?post=<?php echo $post->ID; ?>&action=edit">Edit</a> | </span><span class='inline hide-if-no-js'><a href="#" class="editinline" title="Edit this item inline">Quick&nbsp;Edit</a> | </span><span class='trash'><a class='submitdelete' title='Move this item to the Trash' href='#'>Trash</a> | </span><span class='view'><a href="<?php the_permalink(); // TODO: preview link? TODO: this doesn't work ?>" title="View &#8220;Test example post&#8221;" rel="permalink">View</a></span></div>
				</td>
				<td class="author column-author"><a href="<?php echo $author_filter_url; ?>"><?php echo $authordata->display_name; ?></a></td>
				<td class="status column-status"><a href="<?php echo $status_filter_url; ?>"><?php echo $post->post_status; ?></a></td>
				<td class="categories column-categories"><?php $this->print_subcategories( $post->ID, $parent_term ); ?></td>
			</tr>
<?php
	}
	
	/**
	 * Prints the subcategories of a single post in the story budget.
	 *
	 * @param int $id The post id whose subcategories should be printed.
	 * @param object $parent_term The top-level term to which the post with given ID belongs.
	 */
	function print_subcategories( $id, $parent_term ) {
		// Display the subcategories of the post
		$subterms = get_the_category( $id );
		for ($i = 0; $i < count($subterms); $i++) {
			$subterm = $subterms[$i];
			if ($subterm->term_id != $parent_term->term_id) {
				echo "<a href='edit.php?post_type=post&category_name={$subterm->slug}'>{$subterm->name}</a>";
				echo ($i < count( $subterms ) - 1) ? ', ' : ''; // Separate list (all but last item) with commas
			}
		}
	}
	
	/**
	 * Print the table navigation and filter controls.
	 */
	function table_navigation() {
		global $edit_flow;
		$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
?>
	<div class="tablenav">
		<div class="alignleft actions">
			<form method="get" action="<?php echo admin_url() . EDIT_FLOW_STORY_BUDGET_PAGE; ?>">
			<select id='post_status' name='post_status'><!-- Status selectors -->
				<option value='0'>Show all statuses</option>
				<?php
					foreach ( $custom_statuses as $custom_status ) {
						echo "<option value='$custom_status->slug' " . selected($custom_status->slug, $_GET['post_status']) . ">$custom_status->name</option>";
					}
					echo "<option value='publish'" . selected('publish', $_GET['post_status']) . ">Published</option>";
				?>
			</select>

			<?php
				// Borrowed from wp-admin/edit.php
				if ( ef_taxonomy_exists('category') ) {
					$category_dropdown_args = array(
						'show_option_all' => __( 'Show all categories' ),
						'hide_empty' => 0,
						'hierarchical' => 1,
						'show_count' => 0,
						'orderby' => 'name',
						'selected' => $_GET['cat']
						);
					wp_dropdown_categories($category_dropdown_args);
				}
				
				// TODO: Consider getting rid of this dropdown? The Edit Posts page doesn't have it and only allows filtering by user by clicking on their name. Should we do the same here?
				$user_dropdown_args = array(
					'show_option_all' => __( 'Show all users' ),
					'name'     => 'post_author',
					'selected' => $_GET['post_author']
					);
				wp_dropdown_users( $user_dropdown_args );
			?>
			
			<label for="start_date">From: </label>
			<input id='start_date' name='start_date' type='text' class="date-pick" value="<?php echo $_GET['start_date']; ?>" />
			<label for="end_date">To: </label>
			<input id='end_date' name='end_date' type='text' size='20' class="date-pick" value="<?php echo $_GET['end_date']; ?>" />
			<?php $this->print_date_scripts_and_style(); ?>
			<input type="hidden" name="page" value="edit-flow/story_budget"/>
			<input type="submit" id="post-query-submit" value="Filter" class="button-secondary" />
			</form>
		</div><!-- /alignleft actions -->

		<p class="print-box" style="float:right; margin-right: 30px;"><!-- Print link -->
			<a href="#" id="toggle_details">Toggle Post Details</a> | <a href="#">Print</a>
		</p>
		<div class="clear"></div>
		
	</div><!-- /tablenav -->
	<div class="clear"></div>
<?php
	}

	/**
	 * Print the CSS needed for the story budget. This should probably be included from a separate file.
	 */
	function print_CSS() {
?>
		<style type="text/css">
		#dashboard-widgets-wrap .postbox {
			min-width: 0px;
		}
		</style>
<?php 
	}

	/**
	 * Print the CSS needed for the story budget. This should probably be included from a separate file.
	 */
	function print_JS() {
?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$("#toggle_details").click(function() {
				$(".post-title > p").slideToggle(); // hide post details when directed to
			});
			$("h3.hndle,div.handlediv").click(function() {
				$(this).parent().children("div.inside").slideToggle(); // hide sections when directed to
			});
		});
		</script>
<?php
	}
	
	function print_date_scripts_and_style() {
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
				)
		$('#start_date').bind(
			'dpClosed',
			function(e, selectedDates) {
				var d = selectedDates[0];
				if (d) {
					d = new Date(d);
					$('#end_date').dpSetStartDate(d.addDays(1).asString());
				}
			}
		);
		$('#end_date').bind(
			'dpClosed',
			function(e, selectedDates) {
				var d = selectedDates[0];
				if (d) {
					d = new Date(d);
					$('#start_date').dpSetEndDate(d.addDays(-1).asString());
				}
			}
		);

	});
	</script>
	<style type="text/css">
	@import url("<?php echo EDIT_FLOW_URL; ?>css/datepicker-editflow.css");
	</style>
<?php
	}
	
	function story_budget_excerpt_length( $default_length ) {
		return 60 / $this->num_columns;
	}
}

?>
