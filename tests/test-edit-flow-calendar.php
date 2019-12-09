<?php

class WP_Test_Edit_Flow_Calendar extends WP_UnitTestCase {

	protected static $admin_user_id;
	protected $old_wp_scripts;
		
	public static function wpSetUpBeforeClass( $factory ) {
		global $edit_flow;

		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		/**
		 * There's a capability's check in EF_Calendar `init` that will prevent embedding of assets
		 * and setting of the `create_post_cap` property, so we set that capability here
		 * 
		 * @see https://github.com/Automattic/Edit-Flow/blob/b4430c5d652b1c87736c3980ab8cf032bf49b6ad/modules/calendar/calendar.php#L84
		 * @see https://github.com/Automattic/Edit-Flow/blob/b4430c5d652b1c87736c3980ab8cf032bf49b6ad/modules/calendar/calendar.php#L87
		 */
		$edit_flow->calendar->create_post_cap = 'edit_posts';
		
		$edit_flow->calendar->install();
		$edit_flow->calendar->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	function setUp() {
		parent::setUp();

		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts']                  = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		global $pagenow;
		$pagenow = 'post.php';
	}

	function tearDown() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
		
		global $pagenow;
		$pagenow = 'index.php';

		parent::tearDown();
	}

	/**
	 * The calendar js should be enqueued on pages like post.php with a valid post type
	 */
	public function test_scripts_enqueued_calendar_admin() {
		global $edit_flow, $pagenow, $wp_scripts;

		set_current_screen( 'admin' );

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$pagenow      = 'index.php';
		$_GET['page'] = 'calendar';

		wp_set_current_user( self::$admin_user_id );

		$edit_flow->calendar->enqueue_admin_scripts();

		$expected = "<script type='text/javascript' src='" . $edit_flow->calendar->module_url . 'lib/calendar.js?ver=' . EDIT_FLOW_VERSION . "'></script>";

		$footer = get_echo( 'wp_print_footer_scripts' );

		$this->assertContains( $expected, $footer );
	}
	/**
	 * The custom status js should not be enqueued on pages like admin.php
	 */
	public function test_scripts_not_enqueued_calendar_admin() {
		global $edit_flow, $pagenow, $wp_scripts;

		set_current_screen( 'admin' );

		wp_default_scripts( $wp_scripts );
		wp_default_packages( $wp_scripts );

		$pagenow = 'admin.php';

		wp_set_current_user( self::$admin_user_id );

		$edit_flow->calendar->enqueue_admin_scripts();

		$expected = "<script type='text/javascript' src='" . $edit_flow->calendar->module_url . 'lib/calendar.js?ver=' . EDIT_FLOW_VERSION . "'></script>";

		$footer = get_echo( 'wp_print_footer_scripts' );

		$this->assertNotContains( $expected, $footer );
	}
}
