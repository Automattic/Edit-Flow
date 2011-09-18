<?php
/*
Plugin Name: Edit Flow
Plugin URI: http://editflow.org/
Description: Remixing the WordPress admin for better editorial workflow options.
Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Andrew Spittle, et al.
Version: 0.6.4
Author URI: http://editflow.org/

Copyright 2009-2011 Mohammad Jangda, Daniel Bachhuber, et al.

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

// Define contants
define( 'EDIT_FLOW_VERSION' , '0.6.4' );
define( 'EDIT_FLOW_ROOT' , dirname(__FILE__) );
define( 'EDIT_FLOW_FILE_PATH' , EDIT_FLOW_ROOT . '/' . basename(__FILE__) );
define( 'EDIT_FLOW_URL' , plugins_url(plugin_basename(dirname(__FILE__)).'/') );
define( 'EDIT_FLOW_SETTINGS_PAGE' , add_query_arg( 'page', 'edit-flow', get_admin_url( null, 'options-general.php' ) ) );
define( 'EDIT_FLOW_EDITORIAL_METADATA_PAGE' , add_query_arg( 'taxonomy', 'ef_editorial_meta', get_admin_url( null, 'edit-tags.php' ) ) );
define( 'EDIT_FLOW_PREFIX' , 'ef_' );
define( 'EDIT_FLOW_CALENDAR_PAGE', add_query_arg( 'page', 'calendar', get_admin_url( null, 'index.php' ) ) );
define( 'EDIT_FLOW_STORY_BUDGET_PAGE', add_query_arg( 'page', 'story-budget', get_admin_url( null, 'index.php' ) ) );

// Include necessary files, including the path in which to search to avoid conflicts
include_once( EDIT_FLOW_ROOT . '/php/helpers.php' );
include_once( EDIT_FLOW_ROOT . '/php/custom_status.php' );
include_once( EDIT_FLOW_ROOT . '/php/dashboard.php' );
include_once( EDIT_FLOW_ROOT . '/php/editorial_comments.php' );
include_once( EDIT_FLOW_ROOT . '/php/notifications.php' );
include_once( EDIT_FLOW_ROOT . '/php/usergroups.php' );
include_once( EDIT_FLOW_ROOT . '/php/templates/functions.php' );
include_once( EDIT_FLOW_ROOT . '/php/upgrade.php' );
include_once( EDIT_FLOW_ROOT . '/php/util.php' );
include_once( EDIT_FLOW_ROOT . '/php/calendar.php' );
include_once( EDIT_FLOW_ROOT . '/php/story_budget.php' );
include_once( EDIT_FLOW_ROOT . '/php/settings.php' );
include_once( EDIT_FLOW_ROOT . '/php/editorial_metadata.php' );

// Core class
class edit_flow {

	// Unique identified added as a prefix to all options
	var $options_group = 'edit_flow_';
	var $options_group_name = 'edit_flow_options';	
	// Initially stores default option values, but when load_options is run, it is populated with the options stored in the WP db
	var $options = array(
		'version' => 0,
		'status_dropdown_visible' => 1,
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
	
	// used to create an instance of the various classes 
	var $custom_status;
	var $ef_post_metadata;
	var $editorial_metadata;
	var $dashboard;
	var $post_status;
	var $notifications;
	var $usergroups;
	var $story_budget;
	var $modules;

	/**
	 * Constructor
	 */
	function __construct() {
		
		load_plugin_textdomain( 'edit-flow', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$this->modules = (object)array();			
		
		// Load all of our modules, and the load their options after we've confirmed they're loaded
		add_action( 'plugins_loaded', array( &$this, 'register_modules' ) );
		
		// Initialize all of the modules
		add_action( 'init', array( &$this,'init' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		
		// Edit Flow inits in a bunch of places all at the default priority.
		// This is run after all the init calls have been run.
		add_action( 'init', array( &$this, 'do_init_hook' ), 11 );
		
	} // END __construct()
	
	function register_modules() {
		
		$this->helpers = new EF_Helpers();
		
		// Register all of our classes as Edit Flow modules
		$this->custom_status = new EF_Custom_Status();
		$this->calendar = new EF_Calendar();		
		$this->usergroups = new EF_Usergroups_Admin();
		$this->editorial_comments = new EF_Editorial_Comments();
		$this->editorial_metadata = new EF_Editorial_Metadata();
		$this->story_budget = new EF_Story_Budget();
		$this->notifications = new EF_Notifications();
		$this->dashboard = new EF_Dashboard();
		
		$this->settings = new EF_Settings();
		
	}
	
	/**
	 * Inititalizes the Edit Flows!
	 */
	function init() {
		
		// Load all of the module options
		$this->load_module_options();
		
		// Load all of the modules that are enabled.
		// Modules won't have an options value if they aren't enabled
		foreach ( $this->modules as $mod_name => $mod_data )
			if ( isset( $mod_data->options->enabled ) && $mod_data->options->enabled == 'on' )
				$this->$mod_name->init();
		
	} // END: init()
	
	/**
	 * Call a custom hook for Edit Flow Extensions to hook into.
	 */
	function do_init_hook() {
		do_action( 'ef_init' );
	}	
	
	/**
	 * Initialize the plugin for the admin 
	 */
	function admin_init() {
		// Register all plugin settings so that we can change them and such
		foreach( $this->options as $option => $value ) {
	    	register_setting( $this->options_group, $this->get_plugin_option_fullname($option));
	    }
	    	    
		// Upgrade if need be
		$ef_prev_version = $this->get_plugin_option('version');
		if ( version_compare( $ef_prev_version, EDIT_FLOW_VERSION, '<' ) ) edit_flow_upgrade($ef_prev_version);

		$this->register_scripts_and_styles();
		
	} // END: admin_init()
	
	/**
	 * Register a new module with Edit Flow
	 */
	public function register_module( $name, $args = array() ) {
		
		// A title and name is required for every module
		if ( !isset( $args['title'], $name ) )
			return false;
		
		$defaults = array(
			'title' => '',
			'short_description' => '',
			'extended_description' => '',
			'img_url' => false,
			'slug' => '',
			'post_type_support' => '',
			'default_options' => array(),
			'options' => false,
			'configure_page_cb' => false,
			'configure_link_text' => __( 'Configure' ),
			'autoload' => false,
			'load_frontend' => false,
		);
		$args = array_merge( $defaults, $args );
		$args['name'] = $name;
		$args['options_group_name'] = $this->options_group . $name . '_options';
		$this->modules->$name = (object) $args;
		do_action( 'ef_module_registered', $name );
		return $this->modules->$name;
	}
	
	/**
	 * Load all of the module options from the database
	 * If a given option isn't yet set, then set it to the module's default (upgrades, etc.)
	 */
	function load_module_options() {
		foreach ( $this->modules as $mod_name => $mod_data ) {
			// Don't load modules on the frontend unless they're explictly defined as such
			if ( !is_admin() && !$mod_data->load_frontend )
				continue;
			
			$this->modules->$mod_name->options = get_option( $this->options_group . $mod_name . '_options' );
			foreach ( $mod_data->default_options as $default_key => $default_value ) {
				if ( !isset( $this->modules->$mod_name->options->$default_key ) )
					$this->modules->$mod_name->options->$default_key = $default_value;
			}

			if ( isset( $this->modules->$mod_name->options->post_types ) )
				$this->modules->$mod_name->options->post_types = $this->helpers->clean_post_type_options( $this->modules->$mod_name->options->post_types, $mod_data->post_type_support );	
			
			$this->$mod_name->module = $this->modules->$mod_name;
		}
		do_action( 'ef_module_options_loaded' );
	}
	
	/**
	 * Get a module by one of its descriptive values
	 */
	function get_module_by( $key, $value ) {
		$module = false;
		foreach ( $this->modules as $mod_name => $mod_data ) {
			
			if ( $key == 'name' && $value == $mod_name ) {
				$module =  $this->modules->$mod_name;
			} else {
				foreach( $mod_data as $mod_data_key => $mod_data_value ) {
					if ( $mod_data_key == $key && $mod_data_value == $value )
						$module = $this->modules->$mod_name;
				}
			}
		}
		return $module;
	}
	
	/**
	 * Update the $edit_flow object with new value and save to the database
	 */
	function update_module_option( $mod_name, $key, $value ) {
		$this->modules->$mod_name->options->$key = $value;
		$this->$mod_name->module = $this->modules->$mod_name;
		return update_option( $this->options_group . $mod_name . '_options', $this->modules->$mod_name->options );
	}
	
	function update_all_module_options( $mod_name, $new_options ) {
		if ( is_array( $new_options ) )
			$new_options = (object)$new_options;
		$this->modules->$mod_name->options = $new_options;
		$this->$mod_name->module = $this->modules->$mod_name;
		return update_option( $this->options_group . $mod_name . '_options', $this->modules->$mod_name->options );
	}

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
	
	/**
	 * Gets the page string/path
	 * @param string $page
	 * @return string
	 */
	function get_page ( $page = '' ) {
		return 'edit-flow'. (($page) ? '/' . $page : '');
	}
	
	/**
	 * get_current_post_type()
	 * Checks for the current post type
	 * @since 0.6.1
	 * @return string $post_type The post type we've found
	 */
	function get_current_post_type() {
		global $post, $typenow, $pagenow, $current_screen;

		if ( $post && $post->post_type )
			$post_type = $post->post_type;
		elseif ( $typenow )
			$post_type = $typenow;
		elseif ( $current_screen && isset( $current_screen->post_type ) )
			$post_type = $current_screen->post_type;
		elseif ( isset( $_REQUEST['post_type'] ) )
			$post_type = sanitize_key( $_REQUEST['post_type'] );
		else
			$post_type = null;

		return $post_type;
	}
	
	/**
	 * get_all_post_types_for_feature()
	 * Get all of the post types that support a specific bit of functionality
	 * @since 0.6.1
	 * @param string $feature The feature we're querying against
	 * @return array $post_types All of the post types that support the feature
	 */
	function get_all_post_types_for_feature( $feature ) {
		global $_wp_post_type_features;
		
		$post_types = array();
		foreach ( $_wp_post_type_features as $post_type => $features ) {
			if ( isset( $features[$feature] ) ) {
				$post_types[] = $post_type;
			}
		}
		return $post_types;
	}

} // END: class edit_flow

// Create new instance of the edit_flow object
global $edit_flow;
$edit_flow = new edit_flow();

/**
 * ef_loaded()
 * Allow dependent plugins and core actions to attach themselves in a safe way
 */
function ef_loaded() {
	do_action( 'ef_loaded' );
}
add_action( 'plugins_loaded', 'ef_loaded', 20 );

?>