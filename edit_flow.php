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
include_once('php/story_budget.php');
include_once('php/post_metadata.php');

// Define contants
define( 'EDIT_FLOW_VERSION' , '0.5.3');
define( 'EDIT_FLOW_FILE_PATH' , dirname(__FILE__).'/'.basename(__FILE__) );
define( 'EDIT_FLOW_URL' , plugins_url(plugin_basename(dirname(__FILE__)).'/') );
define( 'EDIT_FLOW_MAIN_PAGE' , 'admin.php?page=edit-flow/edit_flow' );
define( 'EDIT_FLOW_SETTINGS_PAGE' , 'admin.php?page=edit-flow/settings' );
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
					'custom_status_filter' => 'draft',
					'custom_category_filter' => 'all',
					'custom_author_filter' => 'all',
					'calendar_enabled' => 1
				);
	
	// Used to store the names for any custom tables used by the plugin
	var $tables = array();
	
	// used to create an instance of the various classes 
	var $custom_status 	= null;
	var $post_metadata 	= null;
	var $dashboard		= null;
	var $post_status 	= null;
	var $notifications	= null;
	var $usergroups		= null;
	var $story_budget	= null;

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
				
		// Create the new custom_status object
		$this->custom_status = new custom_status();
		
		// Create a new user groups object
		$this->usergroups = new ef_usergroups_admin();
		
		// Create a new edit_flow_dashboard object
		if($this->get_plugin_option('dashboard_widgets_enabled')) $this->dashboard = new edit_flow_dashboard(); 
		
		// Create the post_metadata object
		$this->post_metadata = new ef_custom_metadata();
		
		// Create the story budgeting object
		$this->story_budget = new ef_story_budget();
		
		// Create a new post_status object, if custom statuses enabled
		$post_status_active = (int) $this->get_plugin_option('custom_statuses_enabled');
		$this->post_status = new ef_post_status($post_status_active);
		
		// Create a new ef_notifications object, if notifications enabled
		$notifications_active = (int) $this->get_plugin_option('notifications_enabled');
		$this->notifications = new ef_notifications($notifications_active);
		
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
		if( is_array($this->options) && !empty( $this->options[$name] ) ) {
			$this->options[$name] = $new_value;
			update_option( $this->get_plugin_option_fullname( $name ), $new_value );
		}
	}
	
	function global_admin_controller ( ) {
		$page = null;
		if ( array_key_exists( 'page', $_REQUEST) )
			$page = esc_html( $_REQUEST['page'] );
		
		// Only check if we have page query string and it's for edit-flow
		if ( !empty($page) && strstr($page, 'edit-flow') ) {
			$component = substr( $page, (strrpos($page, '/') + 1) );
			
			switch( $component ) {
				case 'usergroups':
					$this->usergroups->admin_controller();
					break;
				
				case 'custom_status':
					// @TODO: Set up custom statuses to use controller
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
		// Add Top-level Admin Menu
		add_menu_page(__('Edit Flow', 'edit-flow'), __('Edit Flow', 'edit-flow'), 'manage_options', $this->get_page('edit-flow'), array(&$this, 'toplevel_page'));
		
		// Add sub-menu page for Custom statuses		
		add_submenu_page($this->get_page('edit-flow'), __('Custom Status', 'edit-flow'), __('Custom Status', 'edit-flow'), 'manage_options', $this->get_page('custom_status'), array(&$this->custom_status,'admin_page'));
		
		// Add sub-menu page for User Groups
		add_submenu_page($this->get_page('edit-flow'), __('User Groups', 'edit-flow'), __('User Groups', 'edit-flow'), 'manage_options', $this->get_page('usergroups'), array(&$this->usergroups,'admin_page'));
		
		// Add sub-menu page for Settings		
		add_submenu_page($this->get_page('edit-flow'), __('Settings', 'edit-flow'), __('Settings', 'edit-flow'), 'manage_options', $this->get_page('settings'), array(&$this, 'settings_page'));
		
		// Add sub-menu page for Calendar
		if ( $this->calendar_viewable() ) {
			add_submenu_page('index.php', __('Edit Flow Calendar', 'edit-flow'), __('Edit Flow Calendar', 'edit-flow'), 'edit_posts', $this->get_page('calendar'), array(&$this,'calendar'));
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
		
		// TODO: Need to move to calendar class
		if( $pagenow == 'index.php' && $plugin_page == $this->get_page( 'calendar' ) )
			wp_enqueue_script('edit_flow-calendar-js', EDIT_FLOW_URL.'js/calendar.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), false, true);
	}
	
	/* 
	 * Adds Settings page for Edit Flow.
	 */
	function settings_page( ) {
		global $wp_roles;
		
		$msg = null;
		if( array_key_exists( 'updated', $_GET ) && $_GET['updated']=='true' ) { $msg = __('Settings Saved', 'edit-flow'); }
		
		?>
			<div class="wrap">
				<div class="icon32" id="icon-options-general"><br/></div>
				<h2><?php _e('Edit Flow Settings', 'edit-flow') ?></h2>
				
				<?php if($msg) : ?>
					<div class="updated fade" id="message">
						<p><strong><?php echo $msg ?></strong></p>
					</div>
				<?php endif; ?>
				
				<form method="post" action="options.php">
					<?php settings_fields($this->options_group); ?>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><strong><?php _e('Custom Statuses', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="custom_statuses_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('custom_statuses_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('custom_statuses_enabled')) ? 'checked="checked"' : ''; ?> id="custom_statuses_enabled" /> 
										<?php _e('Enable Custom Statuses for Posts', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php printf( __('Enabling this option allow you to assign custom statuses to your Posts. <br />Custom Statuses can be created and managed here: <a href="%s">Edit Flow > Custom Status</a>', 'edit-flow'), EDIT_FLOW_CUSTOM_STATUS_PAGE) ?></span>
								</p>
								<p>
									<label for="pages_custom_statuses_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('pages_custom_statuses_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('pages_custom_statuses_enabled')) ? 'checked="checked"' : ''; ?> id="pages_custom_statuses_enabled" /> 
										<?php _e('Enable Custom Statuses for Pages', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Enabling this option allow you to assign custom statuses to your Pages.') ?></span>
								</p>
								<p>
									<label for="custom_status_default_status">
										<?php _e('Default Status for new posts', 'edit-flow') ?>
									</label>
									<select name="<?php  echo $this->get_plugin_option_fullname('custom_status_default_status') ?>" id="custom_status_default_status">
										
										<?php $statuses = $this->custom_status->get_custom_statuses() ?>
										<?php foreach($statuses as $status) : ?>
										
											<?php $selected = ($this->get_plugin_option('custom_status_default_status')==$status->slug) ? 'selected="selected"' : ''; ?>
											<option value="<?php esc_attr_e($status->slug) ?>" <?php echo $selected ?>>
												<?php esc_html_e($status->name); ?>
											</option>
											
										<?php endforeach; ?>
									</select>
									<br />
									<span class="description"><?php _e('The default status that is applied when a new post is created.', 'edit-flow') ?></span>
								</p>
								
								<p>
									<label for="status_dropdown_visible">
										<input type="checkbox" name="<?php echo $this->get_plugin_option_fullname('status_dropdown_visible') ?>" value="1" <?php echo ($this->get_plugin_option('status_dropdown_visible')) ? 'checked="checked"' : ''; ?> id="status_dropdown_visible" />
										<?php _e('Always show status dropdown', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Enabling this option will keep the "Status" dropdown visible at all times when editing posts and pages to allow for easy updating of statuses.', 'edit-flow') ?></span>
								</p>
									
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Calendar', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="calendar_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('calendar_enabled') ?>" value="1" <?php checked($this->get_plugin_option('calendar_enabled')); ?> id="calendar_enabled" />
										<?php _e('Enable Edit Flow Calendar', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('This enables the Edit Flow Calendar to view editorial content at a glance.', 'edit-flow') ?></span>
								</p>
								<?php
								/* Options for modifying the roles with calendar viewing privileges, though the logic to actually modify those roles was never written. Just didn't want to delete this yet.
								<p>
									<strong>Roles that can view calendar</strong><br />
									<?php foreach($wp_roles->get_names() as $role => $role_name) :
										if ( $wp_roles->is_role( $role ) ) :
											$target_role =& get_role( $role );
											$role_has_cap = $target_role->has_cap( 'view_calendar' );
											?>
											<label for="calendar_view_<?php echo $role; ?>">
												<input type="checkbox" id="calendar_view_<?php echo $role; ?>" value="<?php echo $role; ?>" <?php echo ($role_has_cap ? 'checked="yes"' : '');?> style="margin-bottom: 5px;" />
												<?php _e($role_name, 'edit-flow') ?>
											</label>
											<br />
										<?php endif; ?>
									<?php endforeach; ?>
									<span class="description"><?php _e('Select which roles above may view the Edit Flow Calendar.', 'edit-flow') ?></span>
								</p>
								*/
								?>
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Dashboard', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="dashboard_widgets_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('dashboard_widgets_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('dashboard_widgets_enabled')) ? 'checked="checked"' : ''; ?> id="dashboard_widgets_enabled" />
										<?php _e('Enable Dashboard Widgets', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Enables a special set of dashboard widgets for use with Edit Flow. Enable this setting to view the list of available widgets.', 'edit-flow') ?></span>
								</p>
								<?php if($this->get_plugin_option('dashboard_widgets_enabled')) : ?>
								<p>
									<label for="post_status_widget_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('post_status_widget_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('post_status_widget_enabled')) ? 'checked="checked"' : ''; ?> id="post_status_widget_enabled" />
										<?php _e('Enable Post Status Dashboard Widget', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you an at-a-glance view of the current status of your unpublished content.', 'edit-flow') ?></span>
								</p>
								<p>
									<label for="quickpitch_widget_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('quickpitch_widget_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('quickpitch_widget_enabled')) ? 'checked="checked"' : ''; ?> id="quickpitch_widget_enabled" />
										<?php _e('Enable QuickPitch Dashboard Widget') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you the ability to create a pitch or draft post from the dashboard.', 'edit-flow') ?></span>
								</p>
								<p>
									<label for="myposts_widget_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('myposts_widget_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('myposts_widget_enabled')) ? 'checked="checked"' : ''; ?> id="myposts_widget_enabled" />
										<?php _e('Enable My Posts Dashboard Widget') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you quick access to Posts that you are currently following.', 'edit-flow') ?></span>
								</p>
								<?php endif; ?>
								
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Notifications', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="notifications_enabled">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('notifications_enabled') ?>" value="1" <?php echo ($this->get_plugin_option('notifications_enabled')) ? 'checked="checked"' : ''; ?> id="notifications_enabled" />
										<?php _e('Enable Email Notifications', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('This sends out emails notifications whenever certain actions related to posts occur. Currently, only the following notifications are available: a) email notification on post status change; and b) email notification when an editorial comment is added to a post.', 'edit-flow') ?></span>
								</p>
								<p>
									<label for="always_notify_admin">
										<input type="checkbox" name="<?php  echo $this->get_plugin_option_fullname('always_notify_admin') ?>" value="1" <?php echo ($this->get_plugin_option('always_notify_admin')) ? 'checked="checked"' : ''; ?> id="always_notify_admin" />
										<?php _e('Always Notify Admin', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('If notifications are enabled, the blog administrator will always receive notifications.', 'edit-flow') ?></span>
								</p>
							</td>
						</tr>
						
					</table>
									
					<p class="submit">
						<input type="hidden" name="<?php echo $this->get_plugin_option_fullname('version') ?>" value="<?php echo ($this->get_plugin_option('version')) ?>" />
					    <input type="hidden" name="<?php echo $this->get_plugin_option_fullname('custom_status_filter') ?>" value="<?php echo ($this->get_plugin_option('custom_status_filter')) ?>" id="custom_status_filter" />
				        <input type="hidden" name="<?php echo $this->get_plugin_option_fullname('custom_category_filter') ?>" value="<?php echo ($this->get_plugin_option('custom_category_filter')) ?>" id="custom_category_filter" />
				        <input type="hidden" name="<?php echo $this->get_plugin_option_fullname('custom_author_filter') ?>" value="<?php echo ($this->get_plugin_option('custom_author_filter')) ?>" id="custom_author_filter" />
						<input type="submit" class="button-primary" value="<?php _e('Save Changes', 'edit-flow') ?>" />
					</p>
				</form>
			</div>
		<?php 
	}
	
	/**
	 * Add Top-level Admin Menu
	 * This should be exported into a separate file (?)
	 */
	function toplevel_page() {
		?>
		<div class="wrap">
			<div id="icon-edit" class="icon32"><br /></div>
			<h2>Edit Flow</h2>
			
			<p>The overall goal of this plugin is to improve WordPress' admin for a multi-user newsroom's editorial workflow.</p>
				
			<p>Through discussions with existing newsrooms that use WordPress as their CMS of choice, we have identified the following weak points within the WordPress Administration interface in the context of a multi-user environment:</p>
				
			<ul>
				<li>- The editorial workflow is limited and does not scale well where numerous individuals interact with a single Post or where more complex editing workflows are required;
				</li>
				<li>- The editorial workflow is not conducive to planning of future content (while the Draft feature does facilitate this to some extent, the existing feature set does not scale in scenarios where different users are responsible for different components of a post, or different steps within the workflow.
				</li>
				<li>- The ability for users to communicate within the Administration interface is limited, both the planning of future Posts/assignments and during the editing process.
				</li>
			</ul>
			
			<p>To help you use WordPress more effectively, we're adding cool features that make you go "Yay!" and add all kinds of efficiency to your publishing workflow!</p>
			
			<p>Learn more about <a href="http://www.editflow.org" target="_blank">Edit Flow</a>. Got an itch that Edit Flow doesn't scratch yet? <a href="http://mailto:editflow@copress.org">Get in touch!</a></p>
			
			<p><small>One day we'll put something a little more useful here. Like an ice-cream maker. Or a self-destruct button.</small></p>
			
		</div>
		<?php 
	} // END: toplevel_menu()

    function calendar() {
		include('php/templates/calendar.php');
    }
	
	function calendar_viewable() {
		$calendar_enabled = (int)$this->get_plugin_option('calendar_enabled');
		
		if ($calendar_enabled) {
			$view_calendar_cap = 'ef_view_calendar';
			$view_calendar_cap = apply_filters( 'ef_view_calendar_cap', $view_calendar_cap );

			if( current_user_can( $view_calendar_cap ) )
				return true;
		}
		return false;
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
