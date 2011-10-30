<?php
/*
Plugin Name: Edit Flow
Plugin URI: http://editflow.org/
Description: Remixing the WordPress admin for better editorial workflow options.
Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Andrew Spittle, et al.
Version: 0.6.5
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
define( 'EDIT_FLOW_VERSION' , '0.6.5' );
define( 'EDIT_FLOW_ROOT' , dirname(__FILE__) );
define( 'EDIT_FLOW_FILE_PATH' , EDIT_FLOW_ROOT . '/' . basename(__FILE__) );
define( 'EDIT_FLOW_URL' , plugins_url(plugin_basename(dirname(__FILE__)).'/') );
define( 'EDIT_FLOW_SETTINGS_PAGE' , add_query_arg( 'page', 'ef-settings', get_admin_url( null, 'admin.php' ) ) );
define( 'EDIT_FLOW_EDITORIAL_METADATA_PAGE' , add_query_arg( 'taxonomy', 'ef_editorial_meta', get_admin_url( null, 'edit-tags.php' ) ) );
define( 'EDIT_FLOW_PREFIX' , 'ef_' );
define( 'EDIT_FLOW_CALENDAR_PAGE', add_query_arg( 'page', 'calendar', get_admin_url( null, 'index.php' ) ) );
define( 'EDIT_FLOW_STORY_BUDGET_PAGE', add_query_arg( 'page', 'story-budget', get_admin_url( null, 'index.php' ) ) );

// Include necessary files, including the path in which to search to avoid conflicts
include_once( EDIT_FLOW_ROOT . '/php/custom_status.php' );
include_once( EDIT_FLOW_ROOT . '/php/dashboard.php' );
include_once( EDIT_FLOW_ROOT . '/php/editorial_comments.php' );
include_once( EDIT_FLOW_ROOT . '/php/notifications.php' );
include_once( EDIT_FLOW_ROOT . '/php/usergroups.php' );
include_once( EDIT_FLOW_ROOT . '/modules/calendar/calendar.php' );
include_once( EDIT_FLOW_ROOT . '/php/story_budget.php' );
include_once( EDIT_FLOW_ROOT . '/php/settings.php' );
include_once( EDIT_FLOW_ROOT . '/modules/editorial-metadata/editorial-metadata.php' );

// Common Edit Flow utilities and helpers
include_once( EDIT_FLOW_ROOT . '/lib/php/util.php' );
include_once( EDIT_FLOW_ROOT . '/lib/php/helpers.php' );
include_once( EDIT_FLOW_ROOT . '/lib/php/upgrade.php' );

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
	var $calendar;
	var $dashboard;
	var $post_status;
	var $notifications;
	var $usergroups;
	var $story_budget;
	var $modules;
	var $helpers;

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
		$this->usergroups = new EF_Usergroups();
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
	    	    
		// Upgrade if need be
		$previous_version = get_option( $this->options_group . 'version' );
		if ( version_compare( $previous_version, EDIT_FLOW_VERSION, '<' ) )
			edit_flow_upgrade( $previous_version );

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
			'configure_link_text' => __( 'Configure', 'edit-flow' ),
			// These messages are applied to modules and can be overridden if custom messages are needed
			'messages' => array(
				'settings-updated' => __( 'Settings updated.', 'edit-flow' ),
				'form-error' => __( 'Please correct your form errors below and try again.', 'edit-flow' ),
				'nonce-failed' => __( 'Cheatin&#8217; uh?' ),
				'invalid-permissions' => __( 'You do not have necessary permissions to complete this action.' ),
				'missing-post' => __( 'Post does not exist', 'edit-flow' ),
			),
			'autoload' => false, // autoloading a module will remove the ability to enable or disable it
			'load_frontend' => false, // Whether or not the module should be loaded on the frontend too
		);
		if ( isset( $args['messages'] ) )
			$args['messages'] = array_merge( (array)$args['messages'], $defaults['messages'] );
		$args = array_merge( $defaults, $args );
		$args['name'] = $name;
		$args['options_group_name'] = $this->options_group . $name . '_options';
		if ( !isset( $args['settings_slug'] ) )
			$args['settings_slug'] = 'ef-' . $args['slug'] . '-settings';
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
	 * Registers commonly used scripts + styles for easy enqueueing
	 */	
	function register_scripts_and_styles() {
		wp_register_script( 'jquery-listfilterizer', EDIT_FLOW_URL . 'js/jquery.listfilterizer.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
		wp_register_style( 'jquery-listfilterizer', EDIT_FLOW_URL . 'css/jquery.listfilterizer.css', false, EDIT_FLOW_VERSION, 'all' );

		wp_register_script( 'jquery-quicksearch', EDIT_FLOW_URL . 'js/lib/jquery.quicksearch.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );

		// @compat 3.3
		// Register jQuery datepicker plugin if it doesn't already exist. Datepicker plugin was added in WordPress 3.3
		global $wp_scripts;
		if ( !isset( $wp_scripts->registered['jquery-ui-datepicker'] ) )
			wp_register_script( 'jquery-ui-datepicker', EDIT_FLOW_URL . 'js/lib/jquery.ui.datepicker.min.js', array( 'jquery', 'jquery-ui-core'), '1.8.16', true );		
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