<?php
/**
 * Block_Editor_Compatible aims to be an abstract enough compatibility logic
 */

 // phpcs:disable WordPressVIPMinimum.Variables.VariableAnalysis.SelfOutsideClass

trait Block_Editor_Compatible {

	/**
	 * Helper function to determine whether we're running WP 5.0.
	 *
	 * @return boolean
	 */
	private function is_at_least_wp_50() {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	/**
	 * Whether or not we are in the block editor.
	 *
	 * @return boolean
	 */
	public function is_block_editor() {
		if ( self::is_at_least_wp_50() && function_exists( 'get_current_screen' ) ) {
			return get_current_screen()->is_block_editor();
		}

		return false;
	}
}
