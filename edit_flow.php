<?php
/*
Plugin Name: Edit Flow
Plugin URI: http://www.editflow.org/
Description: Remixing the WordPress admin for better editorial workflow options.
Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Andrew Spittle, et al.
Version: 0.5.3
Author URI: http://www.editflow.org/

Copyright 2009-2010 Mohammad Jangda, Daniel Bachhuber, et al.

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

// Include necessary files
include_once('php/custom_status.php');
include_once('php/dashboard.php');
include_once('php/post.php');
include_once('php/notifications.php');
include_once('php/usergroups.php');
include_once('php/templates/functions.php');
include_once('php/upgrade.php');
include_once('php/util.php');
include_once('php/calendar.php');
include_once('php/story_budget.php');
include_once('php/settings.php');
include_once('php/editorial_metadata.php');

// Define contants
define( 'EDIT_FLOW_VERSION' , '0.5.3');
define( 'EDIT_FLOW_FILE_PATH' , dirname(__FILE__).'/'.basename(__FILE__) );
define( 'EDIT_FLOW_URL' , plugins_url(plugin_basename(dirname(__FILE__)).'/') );
define( 'EDIT_FLOW_SETTINGS_PAGE' , 'admin.php?page=edit-flow/edit-flow' );
define( 'EDIT_FLOW_CUSTOM_STATUS_PAGE' , 'admin.php?page=edit-flow/custom_status' );
define( 'EDIT_FLOW_PREFIX' , 'ef_' );
define( 'EDIT_FLOW_CALENDAR_PAGE', 'index.php?page=edit-flow/calendar');
define( 'EDIT_FLOW_STORY_BUDGET_PAGE', 'index.php?page=edit-flow/story_budget');

// Core class
class edit_flow {

	// Unique identified added as a prefix to all options
	var $options_group = 'edit_flow_';
	// Initially stores default option values, but when load_options is run, it is populated with the options stored in the WP db
	var $options = array(
					'version' => 0,
					'custom_statuses_enabled' => 1,
					'status_dropdown_visible' => 1,
					'pages_custom_statuses_enabled' => 1,
					'custom_status_default_status' => 'draft',
					'dashboard_widgets_enabled' => 1,
					'post_status_widget_enabled' => 1,
					'quickpitch_widget_enabled' => 1,
					'myposts_widget_enabled' => 1,
					'notifications_enabled' => 1,
					'always_notify_admin' => 0,
					'calendar_enabled' => 1
				);
	
	// Used to store the names for any custom tables used by the plugin
	var $tables = array();
	
	// used to create an instance of the various classes 
	var $custom_status 		= null;
	var $editorial_metadata = null;
	var $dashboard			= null;
	var $post_status 		= null;
	var $notifications		= null;
	var $usergroups			= null;
	var $story_budget		= null;

	/**
	 * Constructor
	 */
	function __construct() {
		global $wpdb;
		
		// Define custom tables used here
		// Sample array entry: 'table_name' => $wpdb->prefix.'table_name' 
		$this->tables = array();

		// Load plugin options
		$this->load_options();
				
		// Create all of our objects
		$this->custom_status = new custom_status();
		$this->usergroups = new ef_usergroups_admin();
		$this->editorial_metadata = new ef_editorial_metadata();
		$this->calendar = new ef_calendar();
		$this->story_budget = new ef_story_budget();
		$this->settings = new ef_settings();
		$this->notifications = new ef_notifications( (int) $this->get_plugin_option('notifications_enabled') );
		$this->post_status = new ef_post_status( (int) $this->get_plugin_option('custom_statuses_enabled') );
		$this->dashboard = new edit_flow_dashboard(); 
		
		// The main controller for the plugin - redirects to child controllers where necessary
		add_action( 'admin_init', array( &$this, 'global_admin_controller' ) );
		
		// Add any necessary javascript
		add_action('admin_enqueue_scripts', array(&$this, 'add_admin_scripts'));
		
		
	} // END __construct()
	
	/**
	 * Inititalizes the plugin
	 */
	function init() {
		if(is_admin()) {
			//Add the necessary pages for the plugin 
			add_action('admin_menu', array(&$this, 'add_menu_items'));
		}
		
	} // END: init()
		
	/**
	 * Initialize the plugin for the admin 
	 */
	function admin_init() {
		// Register all plugin settings so that we can change them and such
		foreach($this->options as $option => $value) {
	    	register_setting($this->options_group, $this->get_plugin_option_fullname($option));
	    }
	    	    
		// Upgrade if need be
		$ef_prev_version = ef_version_number_float($this->get_plugin_option('version'));
		if($ef_prev_version < EDIT_FLOW_VERSION) edit_flow_upgrade($ef_prev_version);

	} // END: admin_init()
	
	/**
	 * This function is called when plugin is activated.
	 */
	function activate_plugin ( ) {
		global $wpdb;
		
		// Run function to generate db tables	
		$this->build_db_tables();
		
		// Do other fancy stuff!
		// Like load default values for Custom Status
		
		// re-approve editorial comments
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->comments SET comment_approved = 1 WHERE comment_type = %s", $this->post_metadata->comment_type));
		
		
	} // END: activate_plugin
	
	/**
	 * This function is called when plugin is activated.
	 */
	function deactivate_plugin( ) {
		global $wpdb;
		
		// unapprove editorial comments
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->comments SET comment_approved = 0 WHERE comment_type = %s", $this->post_metadata->comment_type));
		
		
	} // END: deactivate_plugin
	
	/**
	 * Creates all necessary db tables for plugin, if they don't exist.
	 * @return void
	 */
	function build_db_tables() {
		global $wpdb;
		
		/*
			NOTE: Make sure to add table name to the $this->table array (in the __construct() function). We can then access it plugin-wide by:
				
				global $edit_flow;
				$edit_flow->tables['table_name']
			
				SQL code for table structure also goes in here
			
			WordPress wasn't letting me access the table via $wpdb->table_name, which is why I've set this up.
		*/
										
	} // END: build_db_tables


	/**
	 * Loads options for the plugin.
	 * If option doesn't exist in database, it is added
	 *
	 * Note: default values are stored in the $this->options array
	 * Note: a prefix unique to the plugin is appended to all options. Prefix is stored in $this->options_group 
	 */
	function load_options ( ) {

		$new_options = array();
		
		foreach($this->options as $option => $value) {
			$name = $this->get_plugin_option_fullname($option);
			$return = get_option($name);
			if($return === false) {
				add_option($name, $value);
				$new_array[$option] = $value;
			} else {
				$new_array[$option] = $return;
			}
		}
		$this->options = $new_array;
		
	} // END: load_options


	/**
	 * Returns option for the plugin specified by $name, e.g. custom_stati_enabled
	 *
	 * Note: The plugin option prefix does not need to be included in $name 
	 * 
	 * @param string name of the option
	 * @return option|null if not found
	 *
	 */
	function get_plugin_option ( $name ) {
		if(is_array($this->options) && $option = $this->options[$name])
			return $option;
		else 
			return null;
	} // END: get_option
	
	// Utility function: appends the option prefix and returns the full name of the option as it is stored in the wp_options db
	function get_plugin_option_fullname ( $name ) {
		return $this->options_group . $name;
	}
	
	/**
	 * Updates option for the plugin specified by $name, e.g. custom_stati_enabled
	 *
	 * Note: The plugin option prefix does not need to be included in $name 
	 * 
	 * @param string name of the option
	 * @param string value to be set
	 *
	 */
	function update_plugin_option( $name, $new_value ) {
		if( is_array($this->options) /* && !empty( $this->options[$name] ) */ ) {
			$this->options[$name] = $new_value;
			update_option( $this->get_plugin_option_fullname( $name ), $new_value );
		}
	}
	
	function global_admin_controller ( ) {
		$page = null;
		if ( array_key_exists( 'page', $_REQUEST) )
			$page = esc_html( $_REQUEST['page'] );
		
		// Only check if we have page query string and it's for edit-flow
		if ( !empty( $page ) && strstr( $page, 'edit-flow' ) ) {
			$component = substr( $page, ( strrpos( $page, '/' ) + 1 ) );
			
			switch( $component ) {
				case 'usergroups':
					$this->usergroups->admin_controller();
					break;
				
				case 'custom_status':
					$this->custom_status->admin_controller();
					break;
				
				default:
					break;
			}
		}
		return;
	}
	
	/**
	 * Adds menu items for the plugin
	 */
	function add_menu_items ( ) {
		
		/**
		 * Add Top-level Admin Menu
		 */
		add_menu_page(__('Edit Flow', 'edit-flow'), __('Edit Flow', 'edit-flow'), 'manage_options', $this->get_page('edit-flow'), array(&$this->settings, 'settings_page'));
		
		// Add sub-menu page for Custom statuses		
		add_submenu_page( $this->get_page('edit-flow'), __('Custom Status', 'edit-flow'), __('Custom Status', 'edit-flow'), 'manage_options', $this->get_page('custom_status'), array(&$this->custom_status,'admin_page'));
		
		add_submenu_page( $this->get_page('edit-flow'), __('Editorial Metadata', 'edit-flow'),
                        __('Editorial Metadata', 'edit-flow'), 'manage_options',
                        'edit-tags.php?taxonomy='.$this->editorial_metadata->metadata_taxonomy);
		
		// Add sub-menu page for User Groups
		add_submenu_page($this->get_page('edit-flow'), __('User Groups', 'edit-flow'), __('User Groups', 'edit-flow'), 'manage_options', $this->get_page('usergroups'), array(&$this->usergroups,'admin_page'));
		
		// Add sub-menu page for Calendar
		if ( $this->calendar->viewable() ) {
			add_submenu_page('index.php', __('Edit Flow Calendar', 'edit-flow'), __('Edit Flow Calendar', 'edit-flow'), 'edit_posts', $this->get_page('calendar'), array(&$this->calendar, 'view_calendar'));
		}
		
		add_submenu_page( 'index.php', __('Story Budget', 'edit-flow'), __('Story Budget', 'edit-flow'), 'edit_others_posts', $this->get_page('story_budget'), array(&$this->story_budget, 'story_budget') );
		
	} // END: add_menu_items() 
	
	/**
	 * Gets the page string/path
	 * @param string $page
	 * @return string
	 */
	function get_page ( $page = '' ) {
		return 'edit-flow'. (($page) ? '/' . $page : '');
	}
	
	/**
	 * Adds necessary Javascript to admin
	 */
	function add_admin_scripts() {
		global $pagenow, $plugin_page;
		
		wp_enqueue_script('edit_flow-js', EDIT_FLOW_URL.'js/edit_flow.js', array('jquery'), false, true);
		
	}

} // END: class edit_flow

// Create new instance of the edit_flow object
global $edit_flow;
$edit_flow = new edit_flow();

// Core hooks to initialize the plugin
add_action('init', array(&$edit_flow,'init'));
add_action('admin_init', array(&$edit_flow,'admin_init'));

// Hook to perform action when plugin activated
register_activation_hook( EDIT_FLOW_FILE_PATH, array(&$edit_flow, 'activate_plugin'));
register_deactivation_hook( EDIT_FLOW_FILE_PATH, array(&$edit_flow, 'deactivate_plugin'));

?>
