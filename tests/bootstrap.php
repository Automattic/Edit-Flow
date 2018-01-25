<?php

$_tests_dir = '/tmp/wordpress/tests/phpunit';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../edit_flow.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

if (!class_exists('\PHPUnit_Framework_TestCase') && class_exists('\PHPUnit\Framework\TestCase'))
	class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');

require $_tests_dir . '/includes/bootstrap.php';

require dirname( __FILE__ ) . '/testcase-edit-flow-ajax.php';