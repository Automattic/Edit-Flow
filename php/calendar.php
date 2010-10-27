<?php
/**
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * Somewhat prioritized @todos:
 * @todo Highlight today on the calendar
 * @todo Rewrite SQL query to get all of the posts in one go
 * @todo Re-enable pagination links on the top
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
			$old_filters['category_name'] = '';
			$old_filters['author'] = '';
			$old_filters['start_date'] = '';	
		}
		
		// Post status
		if ( isset( $_GET['post_status'] ) ) {
			$filters['post_status'] = $_GET['post_status'];
		} else {
			$filters['post_status'] = $old_filters['post_status'];
		}
		
		// Category
		if ( isset( $_GET['category_name'] ) ) {
			$filters['category_name'] = $_GET['category_name'];
		} else {
			$filters['category_name'] = $old_filters['category_name'];
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
			if ( $old_filters['start_date'] ) {
				$filters['start_date'] = $old_filters['start_date'];
			} else {
				$filters['start_date'] = date('Y-m-d');
			}
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
		$args = array(	'post_status' => $filters['post_status'],
		 				'category_name' => $filters['category_name'],
						'author' => $filters['author']
					);

		$date = $filters['start_date'];
		$dates = array();
		for ($i=0; $i<7; $i++) {
			$dates[$i] = $date;
			$date = date('Y-m-d', strtotime("-1 day", strtotime($date)));
		}

		?>
		<div class="wrap">

				<div id="calendar-title"><!-- Calendar Title -->
					<div class="icon32" id="icon-edit"><br/></div>
					<h2><?php _e( 'Edit Flow Calendar', 'edit-flow'); ?></h2>
				</div><!-- /Calendar Title -->

				<div id="calendar-wrap"><!-- Calendar Wrapper -->
					
		<?php echo $this->get_top_navigation( $filters, $dates ); ?>

			<div id="week-wrap"><!-- Week Wrapper -->
						<div class="week-heading"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
							<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

						<?php
						foreach (array_reverse($dates) as $key => $date) {
							
							$cal_posts = $this->get_calendar_posts( $date, $args );
							
						?>
						<div class="week-unit<?php if ($key == 0) echo ' left-column'; ?>"><!-- Week Unit 1 -->
							<ul id="<?php echo date('Y-m-d', strtotime($date)) ?>" class="week-list connectedSortable">
								<?php
								foreach ($cal_posts as $cal_post) {
									$cats = wp_get_object_terms($cal_post->ID, 'category');
									$cat = $cats[0]->name;
									if (count($cats) > 1) { 
										$cat .= " and  " . (count($cats) - 1);
										if (count($cats)-1 == 1) { $cat .= " other"; }
										else { $cat .= " others"; }
									}

								?>
								<li class="week-item" id="<?php echo $cal_post->ID ?>">
								  <div class="item-handle">
									<span class="item-headline post-title">
										<?php echo $cal_post->post_title; ?>
									</span>
									<ul class="item-metadata">
										<li class="item-author">By <?php echo $cal_post->display_name ?></li>
										<li class="item-category">
											<?php echo $cat ?>
										</li>
									</ul>
									</div>
									<div class="item-actions">
									  <span class="edit">
										<?php echo edit_post_link('Edit', '', '', $cal_post->ID); ?>
									  </span> | 
									  <span class="view">
										<a href="<?php echo get_permalink($cal_post->ID); ?>">View</a>
									  </span>
									</div>
									<div style="clear:left;"></div>
								</li>
								<?php
								}
								?>
							</ul>
						</div><!-- /Week Unit 1 -->
						<?php
						}
						?>

						<div style="clear:both"></div>
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
					<div style="clear:both"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	}
	
	/**
	 * Generates the filtering and navigation options for the top of the calendar
	 * @param array $filters Any set filters
	 * @param array $dates All of the days of the week. Used for generating navigation links
	 * @return string $html HTML for the top navigation
	 */
	function get_top_navigation( $filters, $dates ) {
		global $edit_flow;
	
		$html = '';
		$html .= '<ul class="day-navigation"><li id="calendar-filter"><form method="GET">';
		if ( $filters['start_date'] ) {
			$html .= '<input type="hidden" name="start_date" value="'. $filters['start_date'] . '"/>';
		}
		$html .= '<input type="hidden" name="page" value="edit-flow/calendar" />';
		
		// Filter by post status
		$html .= '<select name="post_status" id="post_status_filter">';
		$html .= '<option value="">Show All Posts</option>';
		$statuses = $edit_flow->custom_status->get_custom_statuses();
		foreach ( $statuses as $status ) {
			$html .= '<option value="' . $status->slug . '"';
			if ( $filters['post_status'] ==  $status->slug ) {
				$html .= ' selected="selected"';
			}
			$html .= '>';
			$html .= 'Status: ' . esc_html($status->name);
			$html .= '</option>';
		}
		$html .= '</select>';
	
		// Filter by categories
		$html .= '<select name="category_name" id="custom_category_filter">';
		$html .= '<option value="">View All Categories</option>';
		$categories = get_categories();
		foreach ( $categories as $category ) {
			$html .= '<option value="' . $category->slug . '"';
			if ( $filters['category_name'] ==  $category->slug ) {
				$html .= ' selected="selected"';
			}
			$html .= '>';
	 		$html .= esc_html($category->name);
			$html .= '</option>';
		}
		$html .= '</select>';
		
		// Filter by users
		$html .= '<select name="author" id="custom_author_filter">';
		$html .= '<option value="">View All Authors</option>';
		$users = get_users_of_blog();
		foreach ( $users as $user ) {
			$html .= '<option value="' . $user->ID . '"';
			if ( $filters['author'] ==  $user->ID ) {
				$html .= ' selected="selected"';
			}
			$html .= '>';
	 		$html .= esc_html($user->display_name);
			$html .= '</option>';
		}
		$html .= '</select>';
		$html .= '<input type="submit" class="button primary" value="Filter"/>';
		$html .= '</form></li>';
	  
		// Previous and next navigation items
		$html .= '<li class="next-week">';
		$html .= '<a id="trigger-left" href="' . $this->get_next_link( $dates[0], $filters ) . '">Next &raquo;</a></li>';
		$html .= '<li class="previous-week">';
		$html .= '<a id="trigger-right" href="' . $this->get_previous_link( $dates[count($dates)-1], $filters ) . '">&laquo; Previous</a></li></ul>';
		
		return $html;
		
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
	 * Get all of the posts within a week's period from the date specified
	 * @todo Rewrite the query for getting posts
	 * @return object $cal_posts All of the posts as an object
	 */
	function get_calendar_posts( $date, $args = null ) {
		global $wpdb, $edit_flow;
		
		$defaults = array( 	'post_status' => null,
							'category_name' => null,
							'author' => null
						);
		
		$args = array_merge( $defaults, $args );
		
		$q_date = date('Y-m-d', strtotime($date));

		$query = "SELECT DISTINCT w.ID, w.guid, w.post_date, u.display_name, w.post_title ";
		$query .= "FROM " . $wpdb->posts . " w, ". $wpdb->users . " u, ";
		$query .= $wpdb->term_relationships . " r, " . $wpdb->terms . " t ";
		$query .= "WHERE u.ID=w.post_author AND ";
		if ( $args['post_status'] ) {
			$query .= "w.post_status = '" . $args['post_status'] . "' AND ";
		}
		$query .= "w.post_status <> 'auto-draft' AND "; // Hide auto draft posts
		$query .= "w.post_status <> 'trash' AND "; // Hide trashed posts
		$query .= "w.post_type = 'post' and w.post_date like '". $q_date . "%' AND ";
		$query .= "r.object_id = w.ID";
		if ( $args['category_name'] ) {
			$query .= " AND t.slug = '" . $args['category_name'] . "'";
		}
		if ( $args['author'] ) {
			$query .= " and u.ID = " . $args['author'];
		}
		$query .= ";";
		$cal_posts = $wpdb->get_results( $query );
		return $cal_posts;
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
