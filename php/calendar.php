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
	
}
	
}

?>