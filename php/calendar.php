<?php
/**
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * Somewhat prioritized @todos:
 * @todo Convert calendar navigation from $_GET to $_POST
 * @todo Highlight today on the calendar
 * @todo Save calendar state to usermeta table
 *
 * @author danielbachhuber
 */
if ( !class_exists('ef_calendar') ) {

class ef_calendar {
	
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
		
		wp_enqueue_style('edit_flow-calendar-css', EDIT_FLOW_URL.'css/calendar.css', false, EDIT_FLOW_VERSION, 'all');
		
	}
	
	function view_calendar() {
		global $edit_flow;

		if($_GET['edit_flow_custom_status_filter']) {
			$edit_flow->options['custom_status_filter'] = $_GET['edit_flow_custom_status_filter'];
		}

		if($_GET['edit_flow_custom_category_filter']) {
			$edit_flow->options['custom_category_filter'] = $_GET['edit_flow_custom_category_filter'];  
		}

		if($_GET['edit_flow_custom_author_filter']) {
			$edit_flow->options['custom_author_filter'] = $_GET['edit_flow_custom_author_filter'];
		}


		date_default_timezone_set('UTC');
		$dates = array();
		if ($_GET['date']) {
			$time = strtotime( $_GET['date'] );
			$date = date('Y-m-d', $time);
		} else {
			$time = time();
			$date = date('Y-m-d');
		}

		$date = $this->get_end_of_week($date); // don't just set the given date as the end of the week. use the blog's settings

		for ($i=0; $i<7; $i++) {
			$dates[$i] = $date;
			$date = date('Y-m-d', strtotime("-1 day", strtotime($date)));
		}

		?>
		<div class="wrap">

				<div id="calendar-title"><!-- Calendar Title -->
					<div class="icon32" id="icon-edit"><br/></div>
					<h2><?php echo date('F d, Y', strtotime($dates[count($dates)-1])); ?> - 
					<?php echo date('F d, Y', strtotime($dates[0])); ?></h2>
				</div><!-- /Calendar Title -->

				<div id="calendar-wrap"><!-- Calendar Wrapper -->
					
		<?php echo $this->get_top_navigation(); ?>

			<div id="week-wrap"><!-- Week Wrapper -->
						<div class="week-heading"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
							<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

						<?php
						foreach (array_reverse($dates) as $key => $date) {
							$cal_posts = $this->get_calendar_posts($date);
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
							<a href="<?php echo $this->get_calendar_next_link($dates[0]) ?>">Next &raquo;</a>
						</li>
						<li class="previous-week">
							<a href="<?php echo $this->get_calendar_previous_link($dates[count($dates)-1]) ?>">&laquo; Previous</a>
						</li>
					</ul>
					<div style="clear:both"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	}
	
	/**
	 * Generates the filtering and navigation options for the top of the calendar
	 * @return string $html HTML for the top navigation
	 */
	function get_top_navigation() {
		global $edit_flow;
	
		$html = '';
		$html .= '<ul class="day-navigation"><li id="calendar-filter"><form method="POST">';
		if ( $_GET['date'] ) {
			$html .= '<input type="hidden" name="date" value="'. $_GET['date'] . '"/>';
		}
	
		// Filter by post status
		$html .= '<select name="' . $edit_flow->get_plugin_option_fullname('custom_status_filter') . '" id="custom_status_filter">';
		$html .= '<option value="0">Show All Posts</option>';
		$statuses = $edit_flow->custom_status->get_custom_statuses();
		foreach ( $statuses as $status ) {
			$html .= '<option value="' . esc_attr($status->slug) . '">';
			$html .= 'Status: ' . esc_html($status->name);
			$html .= '</option>';
		}
		$html .= '</select>';
	
		// Filter by categories
		$html .= '<select name="' . $edit_flow->get_plugin_option_fullname('custom_category_filter') . '" id="custom_category_filter">';
		$html .= '<option value="0">View All Categories</option>';
		$categories = get_categories();
		foreach ( $categories as $category ) {
			$html .= '<option value="' . esc_html($category->term_id) . '">';
	 		$html .= esc_html($category->name);
			$html .= '</option>';
		}
		$html .= '</select>';
		$html .= '<select name="' . $edit_flow->get_plugin_option_fullname('custom_author_filter') . '" id="custom_author_filter">';
		$html .= '<option value="0">View All Authors</option>';
		$users = get_users_of_blog();
		foreach ( $users as $user ) {
			$html .= '<option value="' . esc_html($user->ID) . '">';
	 		$html .= esc_html($user->display_name);
			$html .= '</option>';
		}
		$html .= '</select>';
		$html .= '<input type="submit" class="button primary" value="Filter"/>';
		$html .= '</form></li>';
		$html .= '<li class="performing-ajax">';
		$html .= '<img src="' .  EDIT_FLOW_URL . 'img/wpspin_light.gif" alt="Loading" />';
		$html .= '</li>';
	  	/* 
		@todo Redo navigation using a form
		<li class="next-week">
					<a id="trigger-left" href="#">Next &raquo;</a>
				</li>
				<li class="previous-week">
					<a id="trigger-right" href="#">&laquo; Previous</a>
		  </li>
			</ul> */
		
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
	 * @return object $cal_posts All of the posts as an object
	 */
	function get_calendar_posts( $date ) {

		global $wpdb, $edit_flow;
		$q_date = date('Y-m-d', strtotime($date));

		$sql = "SELECT DISTINCT w.ID, w.guid, w.post_date, u.display_name, w.post_title ";
		$sql .= "FROM " . $wpdb->posts . " w, ". $wpdb->users . " u, ";
		$sql .= $wpdb->term_relationships . " t ";
		$sql .= "WHERE u.ID=w.post_author and ";
		if (($edit_flow->get_plugin_option('custom_status_filter') != 'all') && 
			($edit_flow->get_plugin_option('custom_status_filter') != 'my-posts')) {
			$sql .= "w.post_status = '" . $edit_flow->get_plugin_option('custom_status_filter') . "' and ";
		}
		if ($edit_flow->get_plugin_option('custom_status_filter') == 'my-posts') {
			$sql .= " u.ID = " . wp_get_current_user()->ID . " and ";
		}
		$sql .= "w.post_status <> 'auto-draft' and "; // Hide auto draft posts
		$sql .= "w.post_status <> 'trash' and "; // Hide trashed posts
		$sql .= "w.post_type = 'post' and w.post_date like '". $q_date . "%' and ";
		$sql .= "t.object_id = w.ID";
		if ($edit_flow->get_plugin_option('custom_category_filter') != 'all') {
			$sql .= " and t.term_taxonomy_id = " . $edit_flow->get_plugin_option('custom_category_filter');
		}
		if ($edit_flow->get_plugin_option('custom_author_filter') != 'all') {
			$sql .= " and u.ID = " . $edit_flow->get_plugin_option('custom_author_filter');
		}

		$cal_posts = $wpdb->get_results($sql);
		return $cal_posts;
	}
	
	/**
	 * Gets the link for the previous time period
	 */
	function get_calendar_previous_link( $date ) {
		$p_date = date('d-m-Y', strtotime("-1 day", strtotime($date)));
		return EDIT_FLOW_CALENDAR_PAGE . '&amp;date=' . $p_date;
	}

	/**
	 * Gets the link for the next time period
	 */
	function get_calendar_next_link( $date ) {
		$n_date = date('d-m-Y', strtotime("+7 days", strtotime($date)));
		return EDIT_FLOW_CALENDAR_PAGE . '&amp;date=' . $n_date;
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

?>