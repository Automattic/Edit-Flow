<?php
/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 * @package wordpress-plugins-tests
 */
class WP_Test_Edit_Flow_Starter_Tests extends WP_UnitTestCase {
	
	/**
	 * Run a simple test to ensure that the tests are running
	 */
	function test_editflow_exists() {
		 
		$this->assertTrue( true );
		 
	}
	
	/**
	 * Verify thae minimum version of WordPress is installed
	 */
	function test_wp_version() {
		
		$running_version = getenv( 'WP_VERSION' );
		
		//trunk is always "master" in github terms, but WordPress has a specific way of describing it
		//grab the exact version number to verify that we're on trunk
		if ( $running_version == 'master' || $running_version == 'trunk' ) {
			$file = file_get_contents( 'https://raw.github.com/WordPress/WordPress/master/wp-includes/version.php' );
			preg_match( '#\$wp_version = \'([^\']+)\';#', $file, $matches );
			$running_version = $matches[1];
		}
		
		$this->assertTrue( $running_version > '3.3.3' );
	
	}
	
}
