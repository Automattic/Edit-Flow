<?php

/**
 * This class displays a budgeting system for an editorial desk's publishing workflow.
 *
 * Somewhat prioritized TODOs:
 * TODO: Review inline TODOs
 * TODO: Fix any bugs with collapsing postbox divs and floating columns
 */
class ef_story_budget {
	var $taxonomy_used = 'category';
	
	var $num_columns = 0;
	
	var $max_num_columns;
	
	var $no_matching_posts = true;
	
	var $terms = array();
	
	const screen_width_percent = 98;
	
	const screen_id = 'dashboard_page_edit-flow/story_budget';
	
	const usermeta_key_prefix = 'ef_story_budget_';
	
	const default_num_columns = 1;
	
	/**
	 * Construct a story_budget class and adds screen options.
	 */
	function __construct( $active = 1 ) {
	
		if( $active ) {
			$this->max_num_columns = apply_filters( 'ef_story_budget_max_num_columns', 3 );
			
			include_once( EDIT_FLOW_ROOT . '/php/' . 'screen-options.php' );
			add_screen_options_panel( self::usermeta_key_prefix . 'screen_columns', __( 'Screen Layout', 'edit-flow' ), array( &$this, 'print_column_prefs' ), self::screen_id, array( &$this, 'save_column_prefs' ), true );
			
			// Load necessary scripts and stylesheets
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_admin_scripts' ) );
			add_action( 'admin_print_scripts', array( &$this, 'print_admin_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_admin_styles' ) );		
		}
	}
	
	/**
	 * Enqueue necessary admin scripts
	 */
	function enqueue_admin_scripts() {
		global $current_screen;
		
		if ( $current_screen->id == self::screen_id ) {
			wp_enqueue_script('edit_flow-date-lib', EDIT_FLOW_URL . 'js/lib/date.js', false, EDIT_FLOW_VERSION, true);
			wp_enqueue_script('edit_flow-date_picker-lib', EDIT_FLOW_URL . 'js/lib/jquery.datePicker.js', array( 'jquery' ), EDIT_FLOW_VERSION, true);
			wp_enqueue_script('edit_flow-date_picker', EDIT_FLOW_URL . 'js/ef_date.js', array( 'edit_flow-date_picker-lib', 'edit_flow-date-lib' ), EDIT_FLOW_VERSION, true);
			wp_enqueue_script('edit_flow-story_budget', EDIT_FLOW_URL . 'js/ef_story_budget.js', array( 'edit_flow-date_picker' ), EDIT_FLOW_VERSION, true);
		}
	}
	
	/**
	 * Print admin scripts
	 */
	function print_admin_scripts() {
		?>
		<script type="text/javascript">
			Date.firstDayOfWeek = <?php echo get_option( 'start_of_week' ); ?>;
			editFlowStoryBudgetColumnsWidth = <?php echo self::screen_width_percent ?>;
		</script>
		<?php
	}
	
	/**
	 * Enqueue necessary admin styles
	 */
	function enqueue_admin_styles() {
		global $current_screen;
		
		wp_enqueue_style('edit_flow-datepicker-styles', EDIT_FLOW_URL . 'css/datepicker-editflow.css', false, EDIT_FLOW_VERSION, 'screen');
		if ( $current_screen->id == self::screen_id ) {		
			wp_enqueue_style('edit_flow-story_budget-styles', EDIT_FLOW_URL . 'css/ef_story_budget.css', false, EDIT_FLOW_VERSION, 'screen');
			wp_enqueue_style('edit_flow-story_budget-print-styles', EDIT_FLOW_URL . 'css/ef_story_budget_print.css', false, EDIT_FLOW_VERSION, 'print');
		}
	}
	
	function get_num_columns() {
		if ( empty( $this->num_columns ) ) {
			$current_user = wp_get_current_user();
			if ( function_exists( 'get_user_meta' ) ) {
				$this->num_columns = get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_columns', true );
			} else {
				$this->num_columns = get_usermeta( $current_user->ID, self::usermeta_key_prefix . 'screen_columns' );
			}
			// If usermeta didn't have a value already, use a default value and insert into DB
			if ( empty( $this->num_columns ) ) {
				$this->num_columns = self::default_num_columns;
				$this->save_column_prefs( array( self::usermeta_key_prefix . 'screen_columns' => $this->num_columns ) );
			}
		}
		return $this->num_columns;
	}
	
	function print_column_prefs() {
		$return_val = __( 'Number of Columns: ', 'edit-flow' );
		for ( $i = 1; $i <= $this->max_num_columns; ++$i ) {
			$return_val .= "<label><input type='radio' name='" . self::usermeta_key_prefix . "screen_columns' value='$i' " . checked($this->get_num_columns(), $i, false) . " /> $i</label>\n";
		}
		return $return_val;
	}
	
	/**
	 * Save the current user's preference for number of columns.
	 */
	function save_column_prefs( $posted_fields ) {
		$key = self::usermeta_key_prefix . 'screen_columns';
		$this->num_columns = (int) $posted_fields[ $key ];
		
		$current_user = wp_get_current_user();
		if ( function_exists( 'update_user_meta' ) ) { // Let's try to avoid using the deprecated API
			update_user_meta( $current_user->ID, $key, $this->num_columns );
		} else {
			update_usermeta( $current_user->ID, $key, $this->num_columns );
		}
	}

	/**
	 * Create the story budget view. This calls lots of other methods to do its work. This will
	 * ouput any messages, create the table navigation, then print the columns based on
	 * get_num_columns(), which will in turn print the stories themselves.
	 */
	function story_budget() {
		global $current_screen;
		if ( !strpos( $current_screen->id, 'story_budget' ) ) {
			return;
		}
		
		// Update the current user's filters with the variables set in $_GET
		$user_filters = $this->update_user_filters();
		
		$cat = $this->combine_get_with_user_filter( $user_filters, 'cat' );
		if ( !empty( $cat ) ) {
			$terms = array();
			$terms[] = get_term( $cat, $this->taxonomy_used );
		} else {
			// Get all of the terms from the taxonomy, regardless whether there are published posts
			$args = array(
				'orderby' => 'name',
				'order' => 'asc',
				'hide_empty' => 0,
				'parent' => 0,
			);
			$terms = get_terms( $this->taxonomy_used, $args );
		}
		$this->terms = apply_filters( 'ef_story_budget_filter_terms', $terms ); // allow for reordering or any other filtering of terms
		
		?>
		<div class="wrap" id="ef-story-budget-wrap">
			<?php $this->print_messages(); ?>
			<?php $this->table_navigation(); ?>
			<div id="dashboard-widgets-wrap">
				<div id="dashboard-widgets" class="metabox-holder">
				<?php
					$this->print_column( $this->terms );
				?>
				</div>
			</div><!-- /dashboard-widgets -->
			<?php $this->matching_posts_messages(); ?>
		</div><!-- /wrap -->
		<?php
	}

	/**
	 * Get posts by term and any matching filters
	 * TODO: Get this to actually work
	 */
	function get_matching_posts_by_term_and_filters( $term ) {
		global $wpdb, $edit_flow;
		
		$user_filters = $this->get_user_filters();
		
		// TODO: clean up this query, make it work with an eventual setup_postdata() call
		$query = "SELECT * FROM "/*$wpdb->users, */ . "$wpdb->posts 
					JOIN $wpdb->term_relationships
						ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
					WHERE ";
		
		$post_where = '';		
		
		// Only show approved statuses if we aren't filtering (post_status isn't set or it's 0 or empty), otherwise filter to status
		$post_status = $this->combine_get_with_user_filter( $user_filters, 'post_status' );
		if ( !empty( $post_status ) ) {
			if ( $post_status == 'unpublish' ) {
				$post_where .= "($wpdb->posts.post_status IN (";
				$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
				foreach( $custom_statuses as $status ) {
					$post_where .= $wpdb->prepare( "%s, ", $status->slug );
				}
				$post_where = rtrim( $post_where, ', ' );
				if ( apply_filters( 'ef_show_scheduled_as_unpublished', false ) ) {
					$post_where .= ", 'future'";
				}
				$post_where .= ')) ';
			} else {
				$post_where .= $wpdb->prepare( "$wpdb->posts.post_status = %s ", $post_status );
			}
		} else {
			$post_where .= "($wpdb->posts.post_status IN ('publish', 'future'";
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			foreach( $custom_statuses as $status ) {
				$post_where .= $wpdb->prepare( ", %s", $status->slug );
			}
			$post_where .= ')) ';
		}
		
		// Filter by post_author if it's set
		$post_author = $this->combine_get_with_user_filter( $user_filters, 'post_author' );
		if ( !empty( $post_author ) ) {
			$post_where .= $wpdb->prepare( "AND $wpdb->posts.post_author = %s ", (int) $post_author );
		}
		
		// Filter by start date if it's set
		$start_date = $this->combine_get_with_user_filter( $user_filters, 'start_date' );
		if ( !empty( $start_date ) ) {
			// strtotime basically handles turning any date format we give to the function into a valid timestamp
			// so we don't really care what date string format is used on the page, as long as it makes sense
			$mysql_time = date( 'Y-m-d', strtotime( $start_date ) );
			$post_where .= $wpdb->prepare( "AND ($wpdb->posts.post_date >= %s) ", $mysql_time );
		}
		
		// Filter by end date if it's set
		$end_date = $this->combine_get_with_user_filter( $user_filters, 'end_date' );
		if ( !empty( $end_date) ) {
			$mysql_time = date( 'Y-m-d', strtotime( $end_date ) );
			$post_where .= $wpdb->prepare( "AND ($wpdb->posts.post_date <= %s) ", $mysql_time );
		}
	
		// Limit results to the given category where type is 'post'
		$post_where .= $wpdb->prepare( "AND $wpdb->term_relationships.term_taxonomy_id = %d ", $term->term_taxonomy_id );
		$post_where .= "AND $wpdb->posts.post_type = 'post' ";
		
		// Limit the number of results per category
		$default_query_limit_number = 10;
		$query_limit_number = apply_filters( 'ef_story_budget_query_limit', $default_query_limit_number );
		// Don't allow filtering the limit below 0
		if ( $query_limit_number < 0 ) {
			$query_limit_number = $default_query_limit_number;
		}
		$query_limit = $wpdb->prepare( 'LIMIT %d ', $query_limit_number );
		
		$query .= apply_filters( 'ef_story_budget_query_where', $post_where );
		$query .= apply_filters( 'ef_story_budget_order_by', 'ORDER BY post_modified DESC ' );
		$query .= $query_limit;
		$query .= ';';
		
		return $wpdb->get_results( $query );
	}
	
	function combine_get_with_user_filter( $user_filters, $param ) {
		if ( !isset( $user_filters[$param] ) ) {
			return $this->filter_get_param( $param );
		} else {
			return $user_filters[$param];
		}
	}
	
	/**
	 * Prints a single column in the story budget.
	 *
	 * @param int $col_num The column which we're going to print.
	 * @param array $terms The terms to print in this column.
	 */
	// function print_column($col_num, $terms) {
	function print_column( $terms ) {
		// If printing fewer than get_num_columns() terms, only print that many columns
		$num_columns = $this->get_num_columns();
		?>
		<div class="postbox-container">
			<div class="meta-box-sortables">
			<?php
				// for ($i = $col_num; $i < count($terms); $i += $num_columns)
				for ($i = 0; $i < count($terms); $i++)
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
	function print_term( $term ) {
		global $wpdb;
		$posts = $this->get_matching_posts_by_term_and_filters( $term );
		if ( !empty( $posts ) ) :
			// Don't display the message for $no_matching_posts
			$this->no_matching_posts = false;
			
	?>
	<div class="postbox" style='width: <?php echo self::screen_width_percent / $this->get_num_columns(); ?>%'>
		<div class="handlediv" title="<?php _e( 'Click to toggle', 'edit-flow' ); ?>"><br /></div>
		<h3 class='hndle'><span><?php echo $term->name; ?></span></h3>
		<div class="inside">
			<table class="widefat post fixed story-budget" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" id="title" class="manage-column column-title" ><?php _e( 'Title', 'edit-flow' ); ?></th>
						<th scope="col" id="author" class="manage-column column-author"><?php _e( 'Author', 'edit-flow' ); ?></th>
						<!-- Intentionally using column-author below for CSS -->
						<th scope="col" id="status" class="manage-column column-author"><?php _e( 'Status', 'edit-flow' ); ?></th>
						<th scope="col" id="updated" class="manage-column column-author" title="<?php _e( 'Last update time', 'edit-flow'); ?>"><?php _e( 'Updated', 'edit-flow' ); ?></th>
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
		global $post, $edit_flow;
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
		
		$post_owner = ( get_current_user_id() == $post->post_author ? 'self' : 'other' );
		$edit_link = get_edit_post_link( $post->ID );
		$post_title = _draft_or_post_title();				
		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post = current_user_can( $post_type_object->cap->edit_post, $post->ID );
				
		// TODO: use these two lines before and after calling the_excerpt() once setup_postdata works correctly
		//add_filter( 'excerpt_length', array( &$this, 'story_budget_excerpt_length') );
		//remove_filter( 'excerpt_length', array( &$this, 'story_budget_excerpt_length') );
		
		// Get the friendly name for the status (e.g. Pending Review for pending)
		$status = $edit_flow->custom_status->get_custom_status_friendly_name( $post->post_status );
		?>
			<tr id='post-<?php echo $post->ID; ?>' class='alternate author-self status-publish iedit' valign="top">
				<td class="post-title column-title">
					<?php if ( $can_edit_post ): ?>
						<strong><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php sprintf( __( 'Edit &#8220;%s&#8221', 'edit-flow' ), $post->post_title ); ?>"><?php echo $post_title; ?></a></strong>
					<?php else: ?>
						<strong><?php echo $post_title; ?></strong>
					<?php endif; ?>
					<p><?php echo strip_tags( substr( $post->post_content, 0, 5 * $this->story_budget_excerpt_length(0) ) ); // TODO: just call the_excerpt once setup_postadata works ?></p>
					<p><?php do_action('story_budget_post_details'); ?></p>
					<div class="row-actions">
						<?php if ( $can_edit_post ) : ?>
							<span class='edit'><a title='<?php _e( 'Edit this item', 'edit-flow' ); ?>' href="<?php echo $edit_link; ?>"><?php _e( 'Edit', 'edit-flow' ); ?></a> | </span>
						<?php endif; ?>
						<?php if ( EMPTY_TRASH_DAYS > 0 && current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) : ?>
						<span class='trash'><a class='submitdelete' title='<?php _e( 'Move this item to the Trash', 'edit-flow' ); ?>' href='<?php echo get_delete_post_link( $post->ID ); ?>'><?php _e( 'Trash', 'edit-flow' ); ?></a> | </span>
						<?php endif; ?>
						<span class='view'><a href="<?php get_permalink( $post->ID ); ?>" title="<?php echo esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'edit-flow' ), $post_title ) ); ?>" rel="permalink"><?php _e( 'View', 'edit-flow' ); ?></a></span></div>
				</td>
				<td class="author column-author"><a href="<?php echo $author_filter_url; ?>"><?php echo $authordata->display_name; ?></a></td>
				<td class="status column-status"><a href="<?php echo $status_filter_url; ?>"><?php echo $status ?></a></td>
				<td class="last-updated column-updated"><abbr class="ef-timeago" title="<?php echo printf( __( 'Last updated at %s', 'edit-flow' ), date( 'c', get_the_modified_date( 'U' ) ) ); ?>"><?php echo ef_timesince(get_the_modified_date('U')); ?><?php //$this->print_subcategories( $post->ID, $parent_term ); ?></abbr></td>
			</tr>
		<?php
	}
	
	/**
	 * Prints the subcategories of a single post in the story budget.
	 *
	 * @param int $id The post id whose subcategories should be printed.
	 * @param object $parent_term The top-level term to which the post with given ID belongs.
	 */
	// TODO: Add this as an optional field
	function print_subcategories( $id, $parent_term ) {
		// Display the subcategories of the post
		$subterms = get_the_category( $id );
		for ($i = 0; $i < count($subterms); $i++) {
			$subterm = $subterms[$i];
			if ($subterm->term_id != $parent_term->term_id) {
				$subterm_url = esc_url( add_query_arg( array( 'post_type' => 'post', 'category_name' => $subterm_slug ), admin_url( 'edit.php' ) ) );	
				echo "<a href='$subterm_url'>{$subterm->name}</a>";
				echo ($i < count( $subterms ) - 1) ? ', ' : ''; // Separate list (all but last item) with commas
			}
		}
	}
	
	function print_messages() {
	?>
		<div id="ef-story-budget-title"><!-- Story Budget Title -->
			<div class="icon32" id="icon-edit"></div>
			<h2><?php _e( 'Story Budget', 'edit-flow' ); ?></h2>
		</div><!-- /Story Budget Title -->
	
	<?php
		if ( isset($_GET['trashed']) || isset($_GET['untrashed']) ) {

			echo '<div id="trashed-message" class="updated"><p>';
			
			// Following mostly stolen from edit.php
			
			if ( isset( $_GET['trashed'] ) && (int) $_GET['trashed'] ) {
				printf( _n( 'Item moved to the trash.', '%s items moved to the trash.', $_GET['trashed'] ), number_format_i18n( $_GET['trashed'] ) );
				$ids = isset($_GET['ids']) ? $_GET['ids'] : 0;
				echo ' <a href="' . esc_url( wp_nonce_url( "edit.php?post_type=post&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __( 'Undo', 'edit-flow' ) . '</a><br />';
				unset($_GET['trashed']);
			}

			if ( isset($_GET['untrashed'] ) && (int) $_GET['untrashed'] ) {
				printf( _n( 'Item restored from the Trash.', '%s items restored from the Trash.', $_GET['untrashed'] ), number_format_i18n( $_GET['untrashed'] ) );
				unset($_GET['undeleted']);
			}
			
			echo '</p></div>';
		}
	}
	
	/**
	 * Print the table navigation and filter controls, using the current user's filters if any are set.
	 */
	function table_navigation() {
		global $edit_flow;
		$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
		$user_filters = $this->get_user_filters();
	?>
	<div class="tablenav" id="ef-story-budget-tablenav">
		<div class="alignleft actions">
			<form method="get" action="<?php echo admin_url() . EDIT_FLOW_STORY_BUDGET_PAGE; ?>" style="float: left;">
				<input type="hidden" name="page" value="edit-flow/story_budget"/>
				<select id="post_status" name="post_status"><!-- Status selectors -->
					<option value=""><?php _e( 'View all statuses', 'edit-flow' ); ?></option>
					<?php
						foreach ( $custom_statuses as $custom_status ) {
							echo "<option value='$custom_status->slug' " . selected($custom_status->slug, $user_filters['post_status']) . ">$custom_status->name</option>";
						}
						echo "<option value='future'" . selected('future', $user_filters['post_status']) . ">" . __( 'Scheduled', 'edit-flow' ) . "</option>";
						echo "<option value='unpublish'" . selected('unpublish', $user_filters['post_status']) . ">" . __( 'Unpublished', 'edit-flow' ) . "</option>";
						echo "<option value='publish'" . selected('publish', $user_filters['post_status']) . ">" . __( 'Published', 'edit-flow' ) . "</option>";
					?>
				</select>

				<?php
					// Borrowed from wp-admin/edit.php
					if ( ef_taxonomy_exists('category') ) {
						$category_dropdown_args = array(
							'show_option_all' => __( 'View all categories', 'edit-flow' ),
							'hide_empty' => 0,
							'hierarchical' => 1,
							'show_count' => 0,
							'orderby' => 'name',
							'selected' => $user_filters['cat']
							);
						wp_dropdown_categories( $category_dropdown_args );
					}
					
					// TODO: Consider getting rid of this dropdown? The Edit Posts page doesn't have it and only allows filtering by user by clicking on their name. Should we do the same here?
					$user_dropdown_args = array(
						'show_option_all' => __( 'View all users', 'edit-flow' ),
						'name'     => 'post_author',
						'selected' => $user_filters['post_author']
						);
					wp_dropdown_users( $user_dropdown_args );
				?>
				
				<label for="start_date"><?php _e( 'From:', 'edit-flow' ); ?> </label>
				<input id='start_date' name='start_date' type='text' class="date-pick" value="<?php echo $user_filters['start_date']; ?>" autocomplete="off" />
				<label for="end_date"><?php _e( 'To:', 'edit-flow' ); ?> </label>
				<input id='end_date' name='end_date' type='text' size='20' class="date-pick" value="<?php echo $user_filters['end_date']; ?>" autocomplete="off" />
				<input type="submit" id="post-query-submit" value="<?php _e( 'Filter', 'edit-flow' ); ?>" class="button-primary button" />
			</form>
			<form method="get" action="<?php echo admin_url() . EDIT_FLOW_STORY_BUDGET_PAGE; ?>" style="float: left;">
				<input type="hidden" name="page" value="edit-flow/story_budget"/>
				<input type="hidden" name="post_status" value=""/>
				<input type="hidden" name="cat" value=""/>
				<input type="hidden" name="post_author" value=""/>
				<input type="hidden" name="start_date" value=""/>
				<input type="hidden" name="end_date" value=""/>
				<input type="submit" id="post-query-clear" value="<?php _e( 'Reset', 'edit-flow' ); ?>" class="button-secondary button" />
			</form>
		</div><!-- /alignleft actions -->
		
		<p class="print-box" style="float:right; margin-right: 30px;"><!-- Print link -->
			<a href="#" id="toggle_details"><?php _e( 'Toggle Post Details', 'edit-flow' ); ?></a> | <a href="#" id="print_link"><?php _e( 'Print', 'edit-flow' ); ?></a>
		</p>
		<div class="clear"></div>
		
	</div><!-- /tablenav -->
	<?php
	}
	
	/**
	 * Display any messages after displaying all the story budget boxes. This will likely be for messages when no
	 * stories are found to match the current filters.
	 */
	function matching_posts_messages() {
		if ( $this->no_matching_posts ) { ?>
		<style type="text/css">
			/* Apparently the meta-box-sortables class has a minimum height of 300px. Not good with nothing inside them! */
			.postbox-container .meta-box-sortables { min-height: 0; }
			.print-box { display: none; }
		</style>
		<div id="noposts-message" class="ef-updated"><p><?php _e( 'There are currently no matching posts.', 'edit-flow' ); ?></p></div>
		<?php
		}
	}

	function story_budget_excerpt_length( $default_length ) {
		return 60 / $this->get_num_columns();
	}
	
	/**
	 * Update the current user's filters for story budget display with the filters in $_GET. The filters
	 * in $_GET take precedence over the current users filters if they exist.
	 */
	function update_user_filters() {
		$current_user = wp_get_current_user();
		
		$user_filters = array(
								'post_status' 	=> $this->filter_get_param( 'post_status' ),
								'cat' 			=> $this->filter_get_param( 'cat' ),
								'post_author' 	=> $this->filter_get_param( 'post_author' ),
								'start_date' 	=> $this->filter_get_param( 'start_date' ),
								'end_date' 		=> $this->filter_get_param( 'end_date' )
							  );
		
		$current_user_filters = array();
		if ( function_exists( 'get_user_meta' ) ) { // Let's try to avoid using the deprecated API
			$current_user_filters = get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );
		} else {
			$current_user_filters = get_usermeta( $current_user->ID, self::usermeta_key_prefix . 'filters' );
		}
		
		// If any of the $_GET vars are missing, then use the current user filter
		foreach ( $user_filters as $key => $value ) {
			if ( is_null( $value ) && !empty( $current_user_filters[$key] ) ) {
				$user_filters[$key] = $current_user_filters[$key];
			}
		}
		
		if ( function_exists( 'update_user_meta' ) ) { // Let's try to avoid using the deprecated API
			update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $user_filters );
		} else {
			update_usermeta( $current_user->ID, self::usermeta_key_prefix . 'filters', $user_filters );
		}
		return $user_filters;
	}
	
	/**
	 * Get the filters for the current user for the story budget display, or insert the default
	 * filters if not already set.
	 * 
	 * @return array The filters for the current user, or the default filters if the current user has none.
	 */
	function get_user_filters() {
		$current_user = wp_get_current_user();
		$user_filters = array();
		if ( function_exists( 'get_user_meta' ) ) { // Let's try to avoid using the deprecated API
			$user_filters = get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );
		} else {
			$user_filters = get_usermeta( $current_user->ID, self::usermeta_key_prefix . 'filters' );
		}
		
		// If usermeta didn't have filters already, insert defaults into DB
		if ( empty( $user_filters ) ) {
			$user_filters = $this->update_user_filters();
		}
		return $user_filters;
	}
	
	/**
	 *
	 * @param string $param The parameter to look for in $_GET
	 * @return null if the parameter is not set in $_GET, empty string if the parameter is empty in $_GET,
	 *		   or a sanitized version of the parameter from $_GET if set and not empty
	 */
	function filter_get_param( $param ) {
		// Sure, this could be done in one line. But we're cooler than that: let's make it more readable!
		if ( !isset( $_GET[$param] ) ) {
			return null;
		} else if ( empty( $_GET[$param] ) ) {
			return '';
		}
		
		// TODO: is this the correct sanitization/secure enough?
		return htmlspecialchars( $_GET[$param] );
	}
	
} // End class EF_Story_Budget
