<?php

if ( !class_exists('EF_Settings') ) {

class EF_Settings {
	
	var $module;
	
	/**
	 * Register the module with Edit Flow but don't do anything else
	 */
	function __construct() {	
		global $edit_flow;
		
		// Register the module with Edit Flow
		// @todo default options for registering the statuses
		$args = array(
			'title' => __( 'Edit Flow', 'edit-flow' ),
			'short_description' => __( 'Introduction to Edit Flow. tk', 'edit-flow' ),
			'extended_description' => __( 'Longer description of what Edit Flow does. tk', 'edit-flow' ),
			'img_url' => false,
			'slug' => 'edit-flow',
			'default_options' => array(
				'enabled' => 'on',
			),
			'configure_page_cb' => false,
			'autoload' => true,
		);
		$this->module = $edit_flow->register_module( 'settings', $args );
	}
	
	/**
	 * Initialize the rest of the stuff in the class if the module is active
	 */
	function init() {
		
		add_action( 'admin_print_styles', array( &$this, 'action_admin_print_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( &$this, 'action_admin_menu' ) );
		
		add_action( 'wp_ajax_change_edit_flow_module_state', array( &$this, 'ajax_change_edit_flow_module_state' ) );
		
	}
	
	/**
	 * Add necessary things to the admin menu
	 */
	function action_admin_menu() {
		add_submenu_page( 'options-general.php', __('Edit Flow', 'edit-flow'), __('Edit Flow', 'edit-flow'), 'manage_options', $this->module->slug, array( &$this, 'settings_page_controller' ) ) ;
	}
	
	function action_admin_enqueue_scripts() {
		global $pagenow;
		
		if ( $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'edit-flow' )
			wp_enqueue_script( 'edit-flow-settings-js', EDIT_FLOW_URL . 'js/settings.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
			
	}
	
	/**
	 * Add settings styles to the settings page
	 */
	function action_admin_print_styles() {		
		global $pagenow;
		
		if ( $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'edit-flow' )
			wp_enqueue_style( 'edit_flow-calendar-css', EDIT_FLOW_URL.'css/settings.css', false, EDIT_FLOW_VERSION );
		
		
	}
	
	function ajax_change_edit_flow_module_state() {
		global $edit_flow;
		
		if ( !wp_verify_nonce( $_POST['change_module_nonce'], 'change-edit-flow-module-nonce' ) || !current_user_can( 'manage_options') )
			wp_die( __( 'Cheatin&#8217; uh?' ) );
	
		if ( !isset( $_POST['module_action'], $_POST['slug'] ) )
			die('-1');
			
		$module_action = sanitize_key( $_POST['module_action'] );
		$slug = sanitize_key( $_POST['slug'] );
		
		$module = $edit_flow->get_module_by( 'slug', $slug );
		
		if ( !$module )
			die('-1');
		
		if ( $module_action == 'enable' )
			$return = $edit_flow->update_module_option( $module->name, 'enabled', 'on' );
		else if ( $module_action == 'disable' )
			$return = $edit_flow->update_module_option( $module->name, 'enabled', 'off' );
	
		if ( $return )
			die('1');
		else
			die('-1');
	}
	
	/**
	 * Handles all settings and configuration page requests. Required element for Edit Flow
	 */
	function settings_page_controller() {
		global $edit_flow;
		
		$requested_module = $this->module;
		if ( $_GET['page'] == 'edit-flow' && isset( $_GET['configure'] ) ) {
			$requested_module = $edit_flow->get_module_by( 'slug', $_GET['configure'] );
			if ( !$requested_module )
				wp_die( __( 'Not a registered Edit Flow module', 'edit-flow' ) );
			$configure_callback = $requested_module->configure_page_cb;
		}
		$requested_module_name = $requested_module->name;	
		
		$this->print_default_header( $requested_module );
		switch( $requested_module_name ) {
			case 'settings':
				$this->print_default_settings();
				break;
			default:
				$edit_flow->$requested_module_name->$configure_callback();
				break;
		}
		
	}
	
	/**
	 *
	 */
	function print_default_header( $current_module ) {
		?>
		<div class="wrap edit-flow-admin">
			<div class="icon32" id="icon-options-general"><br/></div>
			<?php if ( $current_module->name != 'settings' ): ?>
			<h2><a href="<?php echo EDIT_FLOW_SETTINGS_PAGE; ?>"><?php _e('Edit Flow', 'edit-flow') ?></a>&nbsp;&rarr;&nbsp;<?php echo $current_module->title; ?></h2>
			<?php else: ?>
			<h2><?php _e('Edit Flow', 'edit-flow') ?></h2>
			<?php endif; ?>
			
			<div class="explanation">
				<?php if ( $current_module->short_description ): ?>
				<p><?php echo $current_module->short_description; ?></p>
				<?php endif; ?>
				<?php if ( $current_module->extended_description ): ?>
				<p><?php echo $current_module->extended_description; ?></p>
				<?php endif; ?>				
			</div>
		<?php
	}
	
	/** 
	 * Adds Settings page for Edit Flow.
	 */
	function print_default_settings() {
		
		?>
		<div class="edit-flow-modules">
			<?php $this->print_modules(); ?>
		</div>
		<?php 
	}
	
	function print_default_footer() {
		?>
		</div>
		<?php
	}
	
	function print_modules() {
		global $edit_flow;
		
		if ( !count( $edit_flow->modules ) ) {
			echo '<div class="message error">' . __( 'There are no Edit Flow modules registered', 'edit-flow' ) . '</div>';
		} else {
			
			foreach ( $edit_flow->modules as $mod_name => $mod_data ) {
				if ( $mod_data->autoload )
					continue;
				echo '<div class="edit-flow-module" id="' . $mod_data->slug . '">';
				echo '<form method="get" action="' . get_admin_url( null, 'options.php' ) . '">';
				echo '<h4>' . esc_html( $mod_data->title ) . '</h4>';
				echo '<p>' . esc_html( $mod_data->short_description ) . '</p>';
				echo '<img class="waiting" style="display:none;" src="' . esc_url( get_admin_url( null, 'images/wpspin_light.gif' ) ) . '" alt="" />';				
				echo '<p class="edit-flow-module-actions">';
				echo '<input type="submit" class="button-primary button enable-disable-edit-flow-module"';
				if ( $mod_data->options->enabled == 'on' ) echo ' style="display:none;"';	
				echo ' value="' . __( 'Enable', 'edit-flow' ) . '" />';
				echo '<input type="submit" class="button-remove button enable-disable-edit-flow-module"';
				if ( $mod_data->options->enabled == 'off' ) echo ' style="display:none;"';				
				echo ' value="' . __( 'Disable', 'edit-flow' ) . '" />';
				if ( $mod_data->configure_page_cb ) {
					$configure_url = add_query_arg( 'configure', $mod_data->slug, EDIT_FLOW_SETTINGS_PAGE );
					echo '<a href="' . $configure_url . '" class="configure-edit-flow-module"';
					if ( $mod_data->options->enabled == 'off' ) echo ' style="display:none;"';
					echo '>' . $mod_data->configure_link_text . '</a>';
				}
				echo '</p>';
				wp_nonce_field( 'change-edit-flow-module-nonce', 'change-module-nonce', false );
				echo '</form>';
				echo '</div>';
			}
			
		}
		
	}
	
}

}