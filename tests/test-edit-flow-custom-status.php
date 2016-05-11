<?php

class WP_Test_Edit_Flow_Custom_Status extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	/**
	 * Test that a published post post_date_gmt is not altered
	 */
	function test_insert_post_publish_respect_post_date_gmt() {
		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'publish',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( get_date_from_gmt( $post['post_date_gmt'] ), $out->post_date) ;
		$this->assertEquals( $post['post_date_gmt'], $out->post_date_gmt );
	}

	/**
	 * Test that when post is published, post_date_gmt is set to post_date
	 */
	function test_insert_post_publish_post_date_set() {
		$past_date = strftime( "%Y-%m-%d %H:%M:%S", strtotime( '-1 second' ) );

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'publish',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date' => $past_date,
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, $past_date );
		$this->assertEquals( $out->post_date, $past_date );
	}


	/**
	 * Test that post_date_gmt is unset when using 'draft' status
	 */
	function test_insert_post_draft_post_date_gmt_empty() {
		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'draft',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}


	/**
	 * Test that post_date_gmt is unset when using 'pending' status
	 */
	function test_insert_post_pending_post_date_gmt_unset() {
		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'pending',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}

	/**
	 * Test that post_date_gmt is unset when using 'pitch' status
	 */
	function test_insert_post_pitch_post_date_gmt_unset() {
		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'pitch',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}


	/**
	 * When a post_date is in the future check that post_date_gmt
	 * is not set when the status is not 'future' 
	 */
	function test_insert_scheduled_post_gmt_set() {
		$future_date = strftime( "%Y-%m-%d %H:%M:%S", strtotime('+1 day') );

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'draft',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date'  => $future_date,
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );


		// fetch the post and make sure it matches
		$out = get_post( $id );


		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertEquals( $post['post_date'], $out->post_date );
	}

	/**
	 * A post with 'future' status should correctly set post_date_gmt from post_date
	 */
	function test_insert_draft_to_future_post_date_gmt_set() {
		$future_date = strftime( "%Y-%m-%d %H:%M:%S" , strtotime( '+1 day' ) );

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'future',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date'  => $future_date,
			'post_date_gmt' => ''
		);

		$id = wp_insert_post( $post );


		// fetch the post and make sure it matches
		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, $future_date );
		$this->assertEquals( $post['post_date'], $out->post_date );
	}
}