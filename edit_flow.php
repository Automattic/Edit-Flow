<?php
/*
Plugin Name: Edit Flow
Plugin URI: http://www.editflow.org/
Description: Remixing the WordPress admin for better editorial workflow options.
Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Andrew Spittle, et al.
Version: 0.6
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
define( 'EDIT_FLOW_VERSION' , '0.6');
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
					'calendar_enabled' => 1,
					'story_budget_enabled' => 1,
				);
	
	// Used to store the names for any custom tables used by the plugin
	var $tables = array();
	
	// used to create an instance of the various classes 
	var $custom_status 		= null;
	var $ef_post_metadata	= null;
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
		
		load_plugin_textdomain( 'edit-flow', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Define custom tables used here
		// Sample array entry: 'table_name' => $wpdb->prefix.'table_name' 
		$this->tables = array();

		// Load plugin options
		$this->load_options();
				
		// Create all of our objects
		$this->custom_status = new EF_Custom_Status();
		$this->usergroups = new EF_Usergroups_Admin();
		$this->ef_post_metadata = new EF_Post_Metadata();
		$this->editorial_metadata = new EF_Editorial_Metadata();
		$this->calendar = new EF_Calendar();
		$this->story_budget = new EF_Story_Budget( (int) $this->get_plugin_option( 'story_budget_enabled' ) );
		$this->settings = new EF_Settings();
		$this->notifications = new EF_Notifications( (int) $this->get_plugin_option('notifications_enabled') );
		$this->post_status = new EF_Post_Status( (int) $this->get_plugin_option('custom_statuses_enabled') );
		$this->dashboard = new EF_Dashboard(); 
		
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
		$ef_prev_version = $this->get_plugin_option('version');
		if ( version_compare( $ef_prev_version, EDIT_FLOW_VERSION, '<' ) ) edit_flow_upgrade($ef_prev_version);

		$this->register_scripts_and_styles();
		
	} // END: admin_init()
	
	/**
	 * This function is called when plugin is activated.
	 */
	function activate_plugin ( ) {
		global $wpdb;
		
		// Run function to generate db tables	
		$this->build_db_tables();
		
	} // END: activate_plugin
	
	/**
	 * This function is called when plugin is activated.
	 */
	function deactivate_plugin( ) {
		
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

	/**
	 * Registers commonly used scripts + styles for easy enqueueing
	 */	
	function register_scripts_and_styles() {
		wp_register_script( 'jquery-listfilterizer', EDIT_FLOW_URL . 'js/jquery.listfilterizer.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
		wp_register_style( 'jquery-listfilterizer', EDIT_FLOW_URL . 'css/jquery.listfilterizer.css', false, EDIT_FLOW_VERSION, 'all' );
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
		add_submenu_page($this->get_page('edit-flow'), __('Usergroups', 'edit-flow'), __('Usergroups', 'edit-flow'), 'manage_options', $this->get_page('usergroups'), array(&$this->usergroups,'admin_page'));
		
		// Add sub-menu page for Calendar
		if ( (int) $this->get_plugin_option( 'calendar_enabled' ) ) {
			add_submenu_page('index.php', __('Calendar', 'edit-flow'), __('Calendar', 'edit-flow'), apply_filters( 'ef_view_calendar_cap', 'ef_view_calendar' ), $this->get_page('calendar'), array(&$this->calendar, 'view_calendar'));
		}
		
		if( (int) $this->get_plugin_option( 'story_budget_enabled' ) ) {
			add_submenu_page( 'index.php', __('Story Budget', 'edit-flow'), __('Story Budget', 'edit-flow'), apply_filters( 'ef_view_story_budget_cap', 'ef_view_story_budget' ), $this->get_page('story_budget'), array(&$this->story_budget, 'story_budget') );
		}
		
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
	 * add_post_type_support()
	 * For our use in registering post functionality with different post types
	 * @since 0.6.1
	 * @param string $post_type The post type we're adding the feature to
	 * @param string
	 */
	function add_post_type_support( $post_type, $feature ) {
		// Use the native method if we're 3.0+. Otherwise copy the method
		if ( function_exists( 'add_post_type_support' ) ) {
			add_post_type_support( $post_type, $feature );
		} else {
			global $_wp_post_type_features;
			
			$features = (array) $feature;
			foreach ( $features as $feature ) {
				if ( func_num_args() == 2 )
					$_wp_post_type_features[$post_type][$feature] = true;
				else
					$_wp_post_type_features[$post_type][$feature] = array_slice( func_get_args(), 2 );
			}
		}
	}
	
	/**
	 * post_type_supports()
	 * @since 0.6.1
	 * @param string $post_type The post type being checked
	 * @param string $feature The feature being checked
	 * @return boolean
	 */
	function post_type_supports( $post_type, feature ) {
		// Use the native method if we're 3.0+. Otherwise copy the method
		if ( function_exists( 'post_type_supports' ) ) {
			return post_type_supports( $post_type, $feature );
		} else {
			global $_wp_post_type_features;	

			if ( !isset( $_wp_post_type_features[$post_type][$feature] ) )
				return false;
			
				// If no args passed then no extra checks need be performed
				if ( func_num_args() <= 2 )
					return true;
						
				return true;
			
		}
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

/**
 * ef_loaded()
 * Allow dependent plugins and core actions to attach themselves in a safe way
 */
function ef_loaded() {
	do_action( 'ef_loaded' );
}
add_action( 'plugins_loaded', 'ef_loaded', 20 );

// Hook to perform action when plugin activated
register_activation_hook( EDIT_FLOW_FILE_PATH, array(&$edit_flow, 'activate_plugin'));
register_deactivation_hook( EDIT_FLOW_FILE_PATH, array(&$edit_flow, 'deactivate_plugin'));

?>