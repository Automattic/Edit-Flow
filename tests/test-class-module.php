<?php

class WP_Test_Edit_Flow_Class_Module extends WP_UnitTestCase {
	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	function _flush_roles() {
		// we want to make sure we're testing against the db, not just in-memory data
		// this will flush everything and reload it from the db
		unset( $GLOBALS['wp_user_roles'] );
		global $wp_roles;
		if ( is_object( $wp_roles ) ) {
			$wp_roles->_init();
		}
	}

	function setUp() {
		parent::setUp();
		// keep track of users we create
		$this->_flush_roles();

	}
	
	function test_add_caps_to_role() {
		$EditFlowModule = new EF_Module();

		$usergroup_roles = array(
			'administrator' => array( 'edit_usergroups' ),
		);

		foreach( $usergroup_roles as $role => $caps ) {
			$EditFlowModule->add_caps_to_role( $role, $caps );
		}

		$user = new WP_User( self::$admin_user_id );

		//Verify before flush
		$this->assertTrue( $user->has_cap( 'edit_usergroups' ), 'User did not have role edit_usergroups' );

		$this->_flush_roles();

		$this->assertTrue( $user->has_cap( 'edit_usergroups' ), 'User did not have role edit_usergroups' );
	}
	
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}
}