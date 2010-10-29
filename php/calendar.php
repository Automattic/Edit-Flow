<?php
/**
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * Somewhat prioritized @todos:
 * @todo Ensure all of the styles work cross-browser
 *
 * @author danielbachhuber
 */
if ( !class_exists('EF_Calendar') ) {

class EF_Calendar {
	
	const usermeta_key_prefix = 'ef_calendar_';
	
	function __construct() {
		
		add_action( 'admin_enqueue_scripts', array(&$this, 'add_admin_scripts' ));
		add_action( 'admin_print_styles', array(&$this, 'add_admin_styles' ));
		
	}
	
	function init() {
		global $edit_flow;
		
	}
	
	/**
	 * Add any necessary Javascript to the WordPress admin
	 */
	function add_admin_scripts() {
		
		//wp_enqueue_script('edit_flow-calendar-js', EDIT_FLOW_URL.'js/calendar.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), false, true);
		
	}
	
	/**
	 * Add any necessary CSS to the WordPress admin
	 */
	function add_admin_styles() {
		
		wp_enqueue_style( 'edit_flow-calendar-css', EDIT_FLOW_URL.'css/calendar.css', false, EDIT_FLOW_VERSION );
		
	}
	
	/**
	 * Get the user's filters for calendar, either with $_GET or from saved
	 * @uses get_user_meta()
	 * @return array $filters All of the set filters
	 */
	function get_filters() {
		global $edit_flow;
		
		$current_user = wp_get_current_user();
		$filters = array();
		// Use the 3.0+ method if it exists to get any saved filters
		if ( function_exists( 'get_user_meta' ) ) {
			$old_filters = get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );
		} else {
			$old_filters = get_usermeta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );
		}
	
		// Set the proper keys to empty so we don't thr
		if ( empty( $old_filters ) ) {
			$old_filters['post_status'] = '';
			$old_filters['cat'] = '';
			$old_filters['author'] = '';
			$old_filters['start_date'] = '';	
		}
		
		// Post status
		if ( isset( $_GET['post_status'] ) ) {
			$filters['post_status'] = $_GET['post_status'];
			// Check to ensure we've been passed a valid post status
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			$all_valid_statuses = array( 'future', 'publish' );
			foreach ( $custom_statuses as $custom_status ) {
				$all_valid_statuses[] = $custom_status->slug;
			}
			if ( !in_array( $filters['post_status'], $all_valid_statuses ) ) {
				$filters['post_status'] = '';
			}
		} else {
			$filters['post_status'] = $old_filters['post_status'];
		}
		
		// Category
		if ( isset( $_GET['cat'] ) ) {
			$filters['cat'] = $_GET['cat'];
		} else {
			$filters['cat'] = $old_filters['cat'];
		}
		
		// Author
		if ( isset( $_GET['author'] ) ) {
			$filters['author'] = $_GET['author'];
		} else {
			$filters['author'] = $old_filters['author'];
		}
		
		// Start date
		if ( isset( $_GET['start_date'] ) && !empty( $_GET['start_date'] ) ) {
			$time = strtotime( $_GET['start_date'] );
			$filters['start_date'] = date('Y-m-d', $time);
		} else {
			$filters['start_date'] = date('Y-m-d');
		}

		$filters['start_date'] = $this->get_end_of_week( $filters['start_date'] ); // don't just set the given date as the end of the week. use the blog's settings
		
		// Use the 3.0+ method if it exists to update our saved filters for a user
		if ( function_exists( 'update_user_meta' ) ) {
			update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );
		} else {
			update_usermeta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );
		}
		
		return $filters;
	}
	
	/**
	 * Build the calendar view
	 */
	function view_calendar() {
		global $edit_flow;

		date_default_timezone_set('UTC');
		
		// Get filters either from $_GET or from user settings
		$filters = $this->get_filters();
		$args = array( 'post_status' => $filters['post_status'],
		 			   'cat'         => $filters['cat'],
					   'author'      => $filters['author']
					  );

		$date = $filters['start_date'];
		// All of the days of the week
		$dates = array();
		for ($i=0; $i<7; $i++) {
			$dates[$i] = $date;
			$date = date('Y-m-d', strtotime("-1 day", strtotime($date)));
		}

		?>
		<div class="wrap">
			<div id="ef-calendar-title"><!-- Calendar Title -->
				<div class="icon32" id="icon-edit"></div>
				<h2><?php _e( 'Calendar', 'edit-flow'); ?></h2>
			</div><!-- /Calendar Title -->

			<div id="ef-calendar-wrap"><!-- Calendar Wrapper -->
					
			<?php $this->print_top_navigation( $filters, $dates ); ?>

			<div id="week-wrap"><!-- Week Wrapper -->
						<div class="week-heading"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
							<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

						<?php
						foreach (array_reverse($dates) as $key => $date) :
							
							$posts = $this->get_calendar_posts( $date, $args );
							
							$date_format = 'Y-m-d';
							$today_css = '';
							// If we're currently outputting posts for today, give it some special CSS treatment
							if ( date( $date_format, strtotime( $date ) ) == date( $date_format ) ) {
								$today_css = ' today';
							}
						?>
						<div class="week-unit<?php if ( $key == 0 ) echo ' left-column'; echo $today_css; ?>"><!-- Week Unit 1 -->
							<ul id="<?php echo date( $date_format, strtotime($date)); ?>" class="week-list connectedSortable">
								<?php
								// We're using The Loop!
								if ( $posts->have_posts() ) : 
								while ( $posts->have_posts()) : $posts->the_post();
									$post_id = get_the_id();
								?>
								<li class="week-item" id="post-<?php the_id(); ?>">
								  <div class="item-handle">
									<span class="item-headline post-title">
										<?php echo edit_post_link( get_the_title(), '', '', $post_id ); ?>
									</span>
									<ul class="item-metadata">
										<li class="item-author">By <?php the_author(); ?></li>
										<li class="item-time"><?php the_time( get_option('time_format') ); ?>
										<li class="item-category">
											<?php
												// Listing of all the categories
												$categories_html = '';
												$categories = get_the_category( $post_id );
												foreach ( $categories as $category ) {
													$categories_html .= $category->name . ', ';
												}
												echo rtrim( $categories_html, ', ' );
											?>
										</li>
									</ul>
									</div>
									<div class="item-actions">
									  <span class="edit">
										<?php echo edit_post_link( 'Edit', '', '', $post_id ); ?>
									  </span> | 
									  <span class="view">
										<a href="<?php echo the_permalink(); ?>">View</a>
									  </span>
									</div>
									<div style="clear:left;"></div>
								</li>
								<?php
								endwhile; endif; // END if ( $posts->have_posts() )
								?>
							</ul>
						</div><!-- /Week Unit 1 -->
						<?php
						endforeach;
						?>

						<div class="clear"></div>
						<div class="week-footing">
						<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

					</div><!-- /Week Wrapper -->
					<ul class="day-navigation">
					  <li class="next-week">
							<a href="<?php echo $this->get_next_link( $dates[0], $filters ); ?>">Next &raquo;</a>
						</li>
						<li class="previous-week">
							<a href="<?php echo $this->get_previous_link( $dates[count($dates)-1], $filters ); ?>">&laquo; Previous</a>
						</li>
					</ul>
					<div class="clear"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	}
	
	/**
	 * Generates the filtering and navigation options for the top of the calendar
	 * @param array $filters Any set filters
	 * @param array $dates All of the days of the week. Used for generating navigation links
	 */
	function print_top_navigation( $filters, $dates ) {
		global $edit_flow;
		
		$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
		?>
		<ul class="day-navigation">
			<li id="calendar-filter">
				<form method="GET">
					<input type="hidden" name="page" value="edit-flow/calendar" />
					<input type="hidden" name="start_date" value="<?php echo $filters['start_date'] ?>"/>
					<!-- Filter by status -->
					<select id="post_status" name="post_status">
						<option value="">View all statuses</option>
						<?php
							foreach ( $custom_statuses as $custom_status ) {
								echo "<option value='$custom_status->slug' " . selected($custom_status->slug, $filters['post_status']) . ">$custom_status->name</option>";
							}
							echo "<option value='future'" . selected('future', $filters['post_status']) . ">Scheduled</option>";
							echo "<option value='publish'" . selected('publish', $filters['post_status']) . ">Published</option>";
						?>
					</select>
					
					<?php
								
					// Filter by categories, borrowed from wp-admin/edit.php
					if ( ef_taxonomy_exists('category') ) {
						$category_dropdown_args = array(
							'show_option_all' => __( 'View all categories' ),
							'hide_empty' => 0,
							'hierarchical' => 1,
							'show_count' => 0,
							'orderby' => 'name',
							'selected' => $filters['cat']
							);
						wp_dropdown_categories( $category_dropdown_args );
					}
					
					$user_dropdown_args = array(
						'show_option_all' => __( 'View all users' ),
						'name'     => 'author',
						'selected' => $filters['author']
						);
					wp_dropdown_users( $user_dropdown_args );
					?>
					<input type="submit" id="post-query-submit" class="button-secondary" value="Filter"/>
				</form>
			</li>
			<!-- Clear filters functionality (all of the fields, but empty) -->
			<li>
				<form method="GET">
					<input type="hidden" name="page" value="edit-flow/calendar" />
					<input type="hidden" name="start_date" value="<?php echo $filters['start_date']; ?>"/>
					<input type="hidden" name="post_status" value="" />
					<input type="hidden" name="cat" value="" />
					<input type="hidden" name="author" value="" />
					<input type="submit" id="post-query-clear" class="button-secondary" value="Reset"/>
				</form>
			</li>
	  
			<!-- Previous and next navigation items -->
			<li class="next-week">
				<a id="trigger-left" href="<?php echo $this->get_next_link( $dates[0], $filters ); ?>">Next &raquo;</a>
			</li>
			<li class="previous-week">
				<a id="trigger-right" href="<?php echo $this->get_previous_link( $dates[count($dates)-1], $filters ); ?>">&laquo; Previous</a>
			</li>
		</ul>
	<?php
	}
	
	function get_time_period_header( $dates ) {
		
		$html = '';
		// Day 1
		$html .= '<div class="day-heading first-heading" style="width: 13.8%; height: 100%; position: absolute; left: 0%; top: 0%; ">';
		$html .= date('l', strtotime($dates[6])) . ', ' . date('M d', strtotime($dates[6]));
		$html .= '</div>';
		// Day 2
		$html .= '<div class="day-heading" style="left: 15.6%; top: 0%; ">';
		$html .= date('l', strtotime($dates[5])) . ', ' . date('M d', strtotime($dates[5]));
		$html .= '</div>';
		// Day 3
		$html .= '<div class="day-heading" style="left: 30%; top: 0%; ">';
		$html .= date('l', strtotime($dates[4])) . ', ' . date('M d', strtotime($dates[4]));
		$html .= '</div>';
		// Day 4
		$html .= '<div class="day-heading" style="left: 44.1%; top: 0%; ">';
		$html .= date('l', strtotime($dates[3])) . ', ' . date('M d', strtotime($dates[3]));
		$html .= '</div>';
		// Day 5
		$html .= '<div class="day-heading" style="left: 58.4%; top: 0%; ">';
		$html .= date('l', strtotime($dates[2])) . ', ' . date('M d', strtotime($dates[2]));
		$html .= '</div>';
		// Day 6
		$html .= '<div class="day-heading" style="left: 72.2%; top: 0%; ">';
		$html .= date('l', strtotime($dates[1])) . ', ' . date('M d', strtotime($dates[1]));
		$html .= '</div>';
		// Day 7
		$html .= '<div class="day-heading last-heading" style="left: 87%; top: 0%; ">';
		$html .= date('l', strtotime($dates[0])) . ', ' . date('M d', strtotime($dates[0]));
		$html .= '</div>';
		
		return $html;
	}
	
	/**
	 * Helper method to determine whether the calendar is viewable or not
	 * @return bool $viewable Whether the calendar is viewable or not
	 */
	function viewable() {
		global $edit_flow;
		
		$calendar_enabled = (int)$edit_flow->get_plugin_option('calendar_enabled');
		if ( $calendar_enabled ) {
			$view_calendar_cap = 'ef_view_calendar';
			$view_calendar_cap = apply_filters( 'ef_view_calendar_cap', $view_calendar_cap );
			if ( current_user_can( $view_calendar_cap ) ) return true;
		}
		return false;
		
	}
	
	/**
	 * Get all of the posts for a given day
	 * @param string $date The date for which we want posts
	 * @param array $args Any filter arguments we want to pass
	 * @return object $posts All of the posts as an object
	 */
	function get_calendar_posts( $date, $args = null ) {
		global $wpdb, $edit_flow;
		
		$defaults = array( 'post_status' => null,
						   'cat'         => null,
						   'author'      => null
						  );
						 
		$args = array_merge( $defaults, $args );
		
		// The WP functions for printing the category and author assign a value of 0 to the default
		// options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
		// unset those arguments. We could alternatively amend the first option from these
		// dropdowns with a regex on the wp_dropdown_cats and wp_dropdown_users filters.
		if ( $args['cat'] === '0' ) unset( $args['cat'] );
		if ( $args['author'] === '0' ) unset( $args['author'] );
				
		$date_array = explode( '-', $date );
		$args['year'] = $date_array[0];
		$args['monthnum'] = $date_array[1];
		$args['day'] = $date_array[2];	
		
		$posts = new WP_Query( $args );
		
		return $posts;
	}
	
	/**
	 * Gets the link for the previous time period
	 * @param string $start_date The start date for the previous period
	 * @param array $filters Any filters that need to be applied
	 * @return string $url The URL for the next page
	 */
	function get_previous_link( $start_date, $filters ) {
		$p_date = date('d-m-Y', strtotime("-1 day", strtotime($start_date)));
		$url = EDIT_FLOW_CALENDAR_PAGE . '&amp;start_date=' . $p_date;
		$url .= '&amp;post_status=' . $filters['post_status'] . '&amp;cat=' . $filters['cat'];
		$url .= '&amp;author=' . $filters['author'];
		return $url;
	}

	/**
	 * Gets the link for the next time period
	 * @param string $start_date The start date for the next period
	 * @param array $filters Any filters that need to be applied
	 * @return string $url The URL for the next page
	 */
	function get_next_link( $start_date, $filters ) {
		$n_date = date('d-m-Y', strtotime("+7 days", strtotime($start_date)));
		$url = EDIT_FLOW_CALENDAR_PAGE . '&amp;start_date=' . $n_date;
		$url .= '&amp;post_status=' . $filters['post_status'] . '&amp;cat=' . $filters['cat'];
		$url .= '&amp;author=' . $filters['author'];
		return $url;
	}
	
	/**
	 * Given a day in string format, returns the day at the end of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
	 */
	function get_end_of_week($date, $format = 'Y-m-d') {
		$date = strtotime( $date );
		$end_of_week = get_option('start_of_week') - 1;
		$day_of_week = date('w', $date);
		$date += ((7 + $end_of_week - $day_of_week) % 7) * 60 * 60 * 24;
		return date($format, $date);
	}
	
}
	
}
