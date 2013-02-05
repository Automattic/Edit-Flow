<?php
/*
Plugin Name: Edit Flow
Plugin URI: http://editflow.org/
Description: Remixing the WordPress admin for better editorial workflow options.
Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and others
Version: 0.7.6
Author URI: http://editflow.org/

Copyright 2009-2012 Mohammad Jangda, Daniel Bachhuber, et al.

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
define( 'EDIT_FLOW_VERSION' , '0.7.6' );
define( 'EDIT_FLOW_ROOT' , dirname(__FILE__) );
define( 'EDIT_FLOW_FILE_PATH' , EDIT_FLOW_ROOT . '/' . basename(__FILE__) );
define( 'EDIT_FLOW_URL' , plugins_url( '/', __FILE__ ) );
define( 'EDIT_FLOW_SETTINGS_PAGE' , add_query_arg( 'page', 'ef-settings', get_admin_url( null, 'admin.php' ) ) );

// Core class
class edit_flow {

	// Unique identified added as a prefix to all options
	var $options_group = 'edit_flow_';
	var $options_group_name = 'edit_flow_options';

	/**
	 * @var EditFlow The one true EditFlow
	 */
	private static $instance;

	/**
	 * Main EditFlow Instance
	 *
	 * Insures that only one instance of EditFlow exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since EditFlow 0.7.4
	 * @staticvar array $instance
	 * @uses EditFlow::setup_globals() Setup the globals needed
	 * @uses EditFlow::includes() Include the required files
	 * @uses EditFlow::setup_actions() Setup the hooks and actions
	 * @see EditFlow()
	 * @return The one true EditFlow
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new edit_flow;
			self::$instance->setup_globals();
			self::$instance->setup_actions();
			// Backwards compat for when we promoted use of the $edit_flow global
			global $edit_flow;
			$edit_flow = self::$instance;
		}
		return self::$instance;
	}

	private function __construct() {
		/** Do nothing **/
	}

	private function setup_globals() {

		$this->modules = new stdClass();

	}

	/**
	 * Include the common resources to Edit Flow and dynamically load the modules
	 */
	private function load_modules() {

		// We use the WP_List_Table API for some of the table gen
		if ( !class_exists( 'WP_List_Table' ) )
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

		// Edit Flow base module
		require_once( EDIT_FLOW_ROOT . '/common/php/class-module.php' );
		
		// Scan the modules directory and include any modules that exist there
		$module_dirs = scandir( EDIT_FLOW_ROOT . '/modules/' );
		$class_names = array();
		foreach( $module_dirs as $module_dir ) {
			if ( file_exists( EDIT_FLOW_ROOT . "/modules/{$module_dir}/$module_dir.php" ) ) {
				include_once( EDIT_FLOW_ROOT . "/modules/{$module_dir}/$module_dir.php" );
				// Prepare the class name because it should be standardized
				$tmp = explode( '-', $module_dir );
				$class_name = '';
				$slug_name = '';
				foreach( $tmp as $word ) {
					$class_name .= ucfirst( $word ) . '_';
					$slug_name .= $word . '_';
				}
				$slug_name = rtrim( $slug_name, '_' );
				$class_names[$slug_name] = 'EF_' . rtrim( $class_name, '_' );
			}
		}

		// Instantiate EF_Module as $helpers for back compat and so we can
		// use it in this class
		$this->helpers = new EF_Module();
		
		// Other utils
		require_once( EDIT_FLOW_ROOT . '/common/php/util.php' );
		
		// Instantiate all of our classes onto the Edit Flow object
		// but make sure they exist too
		foreach( $class_names as $slug => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->$slug = new $class_name();
			}
		}
		
		// Supplementary plugins can hook into this, include their own modules
		// and add them to the $edit_flow object
		do_action( 'ef_modules_loaded' );
		
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since EditFlow 0.7.4
	 * @access private
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'init', array( $this, 'action_init_after' ), 1000 );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		do_action_ref_array( 'editflow_after_setup_actions', array( &$this ) );
	}

	/**
	 * Inititalizes the Edit Flows!
	 * Loads options for each registered module and then initializes it if it's active
	 */
	function action_init() {

		load_plugin_textdomain( 'edit-flow', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$this->load_modules();
		
		// Load all of the module options
		$this->load_module_options();
		
		// Load all of the modules that are enabled.
		// Modules won't have an options value if they aren't enabled
		foreach ( $this->modules as $mod_name => $mod_data )
			if ( isset( $mod_data->options->enabled ) && $mod_data->options->enabled == 'on' )
				$this->$mod_name->init();
		
		do_action( 'ef_init' );
	}

	/**
	 * Initialize the plugin for the admin 
	 */
	function action_admin_init() {
	    	    
		// Upgrade if need be but don't run the upgrade if the plugin has never been used
		$previous_version = get_option( $this->options_group . 'version' );
		if ( $previous_version && version_compare( $previous_version, EDIT_FLOW_VERSION, '<' ) ) {
			foreach ( $this->modules as $mod_name => $mod_data ) {
				if ( method_exists( $this->$mod_name, 'upgrade' ) )
						$this->$mod_name->upgrade( $previous_version );
			}
			update_option( $this->options_group . 'version', EDIT_FLOW_VERSION );
		} else if ( !$previous_version ) {
			update_option( $this->options_group . 'version', EDIT_FLOW_VERSION );
		}
			
		// For each module that's been loaded, auto-load data if it's never been run before
		foreach ( $this->modules as $mod_name => $mod_data ) {
			// If the module has never been loaded before, run the install method if there is one
			if ( !isset( $mod_data->options->loaded_once ) || !$mod_data->options->loaded_once ) {
				if ( method_exists( $this->$mod_name, 'install' ) )
					$this->$mod_name->install();
				$this->update_module_option( $mod_name, 'loaded_once', true );
			}
		}

		$this->register_scripts_and_styles();
		
	}
	
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
				'nonce-failed' => __( 'Cheatin&#8217; uh?', 'edit-flow' ),
				'invalid-permissions' => __( 'You do not have necessary permissions to complete this action.', 'edit-flow' ),
				'missing-post' => __( 'Post does not exist', 'edit-flow' ),
			),
			'autoload' => false, // autoloading a module will remove the ability to enable or disable it
		);
		if ( isset( $args['messages'] ) )
			$args['messages'] = array_merge( (array)$args['messages'], $defaults['messages'] );
		$args = array_merge( $defaults, $args );
		$args['name'] = $name;
		$args['options_group_name'] = $this->options_group . $name . '_options';
		if ( !isset( $args['settings_slug'] ) )
			$args['settings_slug'] = 'ef-' . $args['slug'] . '-settings';
		if ( empty( $args['post_type_support'] ) )
			$args['post_type_support'] = 'ef_' . $name;
		// If there's a Help Screen registered for the module, make sure we
		// auto-load it
		if ( !empty( $args['settings_help_tab'] ) )
			add_action( 'load-edit-flow_page_' . $args['settings_slug'], array( &$this->$name, 'action_settings_help_menu' ) );
		
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

			$this->modules->$mod_name->options = get_option( $this->options_group . $mod_name . '_options', new stdClass );
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
	 * Load the post type options again so we give add_post_type_support() a chance to work
	 *
	 * @see http://dev.editflow.org/2011/11/17/edit-flow-v0-7-alpha2-notes/#comment-232
	 */
	function action_init_after() {
		foreach ( $this->modules as $mod_name => $mod_data ) {

			if ( isset( $this->modules->$mod_name->options->post_types ) )
				$this->modules->$mod_name->options->post_types = $this->helpers->clean_post_type_options( $this->modules->$mod_name->options->post_types, $mod_data->post_type_support );	
			
			$this->$mod_name->module = $this->modules->$mod_name;
		}
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
		wp_register_script( 'jquery-listfilterizer', EDIT_FLOW_URL . 'common/js/jquery.listfilterizer.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
		wp_register_style( 'jquery-listfilterizer', EDIT_FLOW_URL . 'common/css/jquery.listfilterizer.css', false, EDIT_FLOW_VERSION, 'all' );

		wp_register_script( 'jquery-quicksearch', EDIT_FLOW_URL . 'common/js/jquery.quicksearch.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );

		// @compat 3.3
		// Register jQuery datepicker plugin if it doesn't already exist. Datepicker plugin was added in WordPress 3.3
		global $wp_scripts;
		if ( !isset( $wp_scripts->registered['jquery-ui-datepicker'] ) )
			wp_register_script( 'jquery-ui-datepicker', EDIT_FLOW_URL . 'common/js/jquery.ui.datepicker.min.js', array( 'jquery', 'jquery-ui-core'), '1.8.16', true );		
	}

}

function EditFlow() {
	return edit_flow::instance();
}
add_action( 'plugins_loaded', 'EditFlow' );