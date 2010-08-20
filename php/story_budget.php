<?php

/**
 * This class displays a budgeting system for an editorial desks publishing workflow.
 * This is a cursory attempt at implementation with many outstanding TODOs.
 *
 * Somewhat prioritized TODOs:
 * TODO: Any and all filtering
 * TODO: Integrate with Screen Options API
 * TODO: Add filtering for single day. Month filtering as it currently exists probably useless?
 * TODO: Make trash links work (using nonce) and quick edit links
 * TODO: Verify author, status, and category links for each post work as planned (vs. filtering in budget rather than switching to edit screen)
 * TODO: Make sure working properly with custom statuses
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
		$terms = get_terms($this->taxonomy_used, 'orderby=name&order=asc&parent=0');
		$terms = apply_filters( 'story_budget_reorder_terms', $terms ); // allow for reordering or any other filtering of terms

		printJS();
		printCSS();
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
		
		$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
		
		$query = "SELECT * FROM $wpdb->posts posts JOIN $wpdb->term_relationships ON posts.ID = $wpdb->term_relationships.object_id WHERE ";
		
		$post_where = '';		
		
		//if (!isset($_GET['post_status'])) {
			$post_where .= $wpdb->prepare( '(posts.post_status = %s ', 'publish' );
			foreach($custom_statuses as $status) {
				$post_where .= $wpdb->prepare( ' OR posts.post_status = %s', $status->slug );
			}
			$post_where .= ') ';
	
		
		//$post_where .= $wpdb->prepare( 'AND (posts.term_taxonomy_id = %d) ', $term->term_id );
		$post_where .= ' AND posts.post_type = "post"';
		$query .= $post_where . ';';
		
		var_dump($query);
		
		return $wpdb->get_results( $query );
		
	}
	
	/**
	 * Prints a single column in the story budget.
	 *
	 * @param int $col_num The column which we're going to print.
	 * @param array $terms The terms to print in this column.
	 */
	function print_column($col_num, $terms) {
?>
	<div class='postbox-container' style='width:<?php echo 98/$this->num_columns; ?>%'>
		<div class="meta-box-sortables">
		<?php
			for ($i = $col_num; $i < count($terms); $i += $this->num_columns)
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
					$posts = $this->get_matching_posts_by_term_and_filters($term);			
					foreach ($posts as $post)
						$this->print_post($post, $term);
				?>
				</tbody>
			</table>
		</div>
	</div>
<?php
	}
	
	/**
	 * Prints a single story in the story budget.
	 *
	 * @param object $the_post The post to print.
	 * @param object $parent_term The top-level term to which this post belongs.
	 */
	function print_post($the_post, $parent_term) {
		global $post;
		setup_postdata($the_post);
?>
			<tr id='post-<?php echo $post->ID; ?>' class='alternate author-self status-publish iedit' valign="top">

				<td class="post-title column-title">
					<strong><a class="row-title" href="post.php?post=<?php echo $post->ID; ?>&action=edit" title="Edit &#8220;<?php the_title(); ?>&#8221;"><?php the_title(); ?></a></strong>
					<p><?php the_excerpt(); ?></p>
					<p><?php do_action('story_budget_post_details'); ?></p>
					<div class="row-actions"><span class='edit'><a href="post.php?post=<?php echo $post->ID; ?>&action=edit">Edit</a> | </span><span class='inline hide-if-no-js'><a href="#" class="editinline" title="Edit this item inline">Quick&nbsp;Edit</a> | </span><span class='trash'><a class='submitdelete' title='Move this item to the Trash' href='#'>Trash</a> | </span><span class='view'><a href="<?php the_permalink(); // TODO: preview link? ?>" title="View &#8220;Test example post&#8221;" rel="permalink">View</a></span></div>
				</td>
				<td class="author column-author"><a href="edit.php?post_type=post&author=<?php echo $post->post_author;?>"><?php the_author(); ?></a></td>
				<td class="status column-status"><a href="edit.php?post_type=post&post_status=<?php echo $post->post_status; ?>"><?php echo $post->post_status; // TODO: figure out why this doesn't work: get_term_by('slug', $post->post_status, 'post_status'); Probably related to fact that hardcoded switch is used here: http://phpxref.ftwr.co.uk/wordpress/nav.html?wp-admin/includes/template.php.source.html#l1328 ?></a></td>
				<td class="categories column-categories">
				<?php
					// Display the subcategories of the post
					$subterms = get_the_category();
					for ($i = 0; $i < count($subterms); $i++) {
						$subterm = $subterms[$i];
						if ($subterm->term_id != $parent_term->term_id) {
							echo "<a href='edit.php?post_type=post&category_name={$subterm->slug}'>{$subterm->name}</a>";
							echo ($i < count($subterms) - 1) ? ', ' : ''; // Separate list (all but last item) with commas
						}
					}
				?>
				</td>
			</tr>
<?php
	}
	
	/**
	 * Print the table navigation and filter controls.
	 */
	function table_navigation() {
		global $edit_flow;
?>
	<div class="tablenav">
		<div class="alignleft actions">
			<form method="get" action="">
			<?php $custom_statuses = $edit_flow->custom_status->get_custom_statuses(); ?>
			<select name='status'><!-- Status selectors -->
				<option selected='selected' value='0'>Show all statuses</option>
				<?php
					foreach ( $custom_statuses as $custom_status )
						echo "<option value='{$custom_status->slug}'>{$custom_status->name}</option>";
				?>
			</select>
			<select name='m'><!-- Archive selectors -->
				<option selected='selected' value='0'>Show all dates</option>
				<?php // TODO: Do something useful here, probably in PHP ?>
				<option value='201007'>July 2010</option>
				<option value='201006'>June 2010</option>
				<option value='201005'>May 2010</option>
				<option value='201004'>April 2010</option>
				<option value='201003'>March 2010</option>
				<option value='201002'>February 2010</option>
				<option value='200912'>December 2009</option>
				<option value='200911'>November 2009</option>
				<option value='200910'>October 2009</option>
				<option value='200909'>September 2009</option>
				<option value='200908'>August 2009</option>
				<option value='200907'>July 2009</option>
				<option value='200906'>June 2009</option>
				<option value='200905'>May 2009</option>
				<option value='200904'>April 2009</option>
				<option value='200903'>March 2009</option>
				<option value='200902'>February 2009</option>
				<option value='200901'>January 2009</option>
				<option value='200812'>December 2008</option>
				<option value='200811'>November 2008</option>
			</select>

			<?php
				// Borrowed from wp-admin/edit.php
				if ( ef_taxonomy_exists('category') ) {
					$dropdown_options = array(
						'show_option_all' => __('View all categories'),
						'hide_empty' => 0,
						'hierarchical' => 1,
						'show_count' => 0,
						'orderby' => 'name',
						'selected' => $cat
						);
					wp_dropdown_categories($dropdown_options);
				}
			?>
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
	function printCSS() {
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
	function printJS() {
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
}

?>
