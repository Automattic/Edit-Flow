<?php
/**
 * class EF_Workflow_Manager
 * Manage custom post status transitions.
 */


if ( class_exists( 'EF_Workflow_Manager' ) ) {
	return;
}

class EF_Workflow_Manager extends EF_Module {
	public $module;

	/**
	 * Register the module with Edit Flow but don't do anything else
	 */
	public function __construct() {

		$this->module_url = $this->get_module_url( __FILE__ );
		// Register the module with Edit Flow
		$args = [
			'title'                 => __( 'Workflow Manager', 'edit-flow' ),
			'short_description'     => __( 'Manage custom post status transitions.', 'edit-flow' ),
			'extended_description'  => __( 'Create and manage rules for custom post statuses. Determine how posts change status, when approval is neccessary, and the conditions for allowing a post to be published.', 'edit-flow' ),
			'module_url'            => $this->module_url,
			'img_url'               => $this->module_url . 'lib/workflow_manager_s128.png',
			'slug'                  => 'workflow-manager',
			'default_options'       => [
				'enabled' => 'on',
			],
			'configure_page_cb'     => 'print_configure_view',
			'configure_link_text'   => __( 'Edit Workflow', 'edit-flow' ),
			'messages'              => [],
			'autoload'              => false,
			'settings_help_tab'     => [
				'id'      => 'ef-workflow-manager-overview',
				'title'   => __( 'Overview', 'edit-flow' ),
				'content' => sprintf( '<p>%s</p>', __( 'Create and manage rules for custom post statuses. Determine how posts change status, when approval is neccessary, and the conditions for allowing a post to be published.', 'edit-flow' ) ),
			],
			'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/custom-statuses/">Custom Status Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/Automattic/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
		];

		$this->module = EditFlow()->register_module( 'workflow_manager', $args );
	}

	/**
	 * Initialize the EF_Custom_Status class if the module is active
	 */
	public function init() {
		global $edit_flow;

		if ( ! $edit_flow->custom_status->disable_custom_statuses_for_post_type() ) {
			// Load CSS and JS resources that we probably need in the admin page
			add_action( 'admin_enqueue_scripts', [ $this, 'action_admin_enqueue_scripts' ] );
		}
	}

	/**
	 * Create the default set of custom statuses the first time the module is loaded
	 *
	 * @since 0.7
	 */
	public function install() {
		/* Setup default workflow */
	}

	/**
	 * Enqueue Javascript resources that we need in the admin:
	 * - Primary use of Javascript is to manipulate the post status dropdown on Edit Post and Manage Posts
	 * - jQuery Sortable plugin is used for drag and dropping custom statuses
	 * - We have other custom code for Quick Edit and JS niceties
	 */
	public function action_admin_enqueue_scripts() {
		// Load Javascript we need to use on the configuration views (jQuery Sortable and Quick Edit)
		if ( $this->is_whitelisted_settings_view( $this->module->name ) ) {
			wp_enqueue_script( 'edit-flow-workflow-manager-configure', $this->module_url . 'lib/workflow-manager-configure.js', [], EDIT_FLOW_VERSION, true );
		}
	}

	public function print_configure_view() {
		global $edit_flow;

		printf( '<p>%s</p>', esc_html__( 'Workflow manager.', 'edit-flow' ) );
	}
}
