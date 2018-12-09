<?php
class EF_Custom_Status_Block_Editor_Compat {
	protected $ef_module;
	// @see commmon/php/trait-block-editor-compatible
	use Block_Editor_Compatible;

	/**
	 * Dequeue Classic Editor Edit Flow Statuses and enqueue the block.
	 *
	 * @return void
	 */
	function action_admin_enqueue_scripts() {
		if ( $this->ef_module->disable_custom_statuses_for_post_type() || !function_exists( 'is_gutenberg_page' ) || ! is_gutenberg_page() )
			return;

		wp_enqueue_style( 'edit-flow-block-custom-status', EDIT_FLOW_URL . 'blocks/dist/custom-status.editor.build.css', false, null );
		wp_enqueue_script( 'edit-flow-block-custom-status', EDIT_FLOW_URL . 'blocks/dist/custom-status.build.js', array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-plugins', 'wp-components' ) );

		wp_localize_script( 'edit-flow-block-custom-status', 'EditFlowCustomStatuses', $this->get_custom_statuses() );
	}

	/**
	 * Just a wrapper to make sure we're have simple array instead of associative.
	 *
	 * @return array Custom statuses.
	 */
	function get_custom_statuses() {
		return array_values( $this->ef_module->get_custom_statuses() );
	}
}