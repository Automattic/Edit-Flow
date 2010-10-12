<?php

if ( !class_exists('ef_calendar') ) {

class ef_calendar {
	
	function __construct() {
		
		add_action('admin_enqueue_scripts', array(&$this, 'add_admin_scripts'));
	}
	
	function init() {
		
	}

	
	
	/**
	 * Add any necessary Javascript to the WordPress admin
	 */
	function add_admin_scripts() {
		
		wp_enqueue_script('edit_flow-calendar-js', EDIT_FLOW_URL.'js/calendar.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), false, true);
		
	}
	
	function add_admin_styles() {
		
	}
	
	function view_calendar() {
		
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