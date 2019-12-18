<?php
class EF_Custom_Status_Block_Editor_Compat {
	// @see commmon/php/trait-block-editor-compatible.php
	use Block_Editor_Compatible;

	/**
	 * Dequeue Classic Editor Edit Flow Statuses and enqueue the block.
	 *
	 * @return void
	 */
	function action_admin_enqueue_scripts() {
		if ( $this->ef_module->disable_custom_statuses_for_post_type() ) {
			return;
		}

		/**
		 * WP_Screen::is_block_editor only available in 5.0. If it's available and is false it's safe to say we should only pass through to the module.
		 */
		if ( Block_Editor_Compatible::is_at_least_50() && ! get_current_screen()->is_block_editor() ) {
			return $this->ef_module->action_admin_enqueue_scripts();
		}

		wp_enqueue_style( 'edit-flow-block-custom-status', EDIT_FLOW_URL . 'blocks/dist/custom-status.editor.build.css', false, EDIT_FLOW_VERSION );
		wp_enqueue_script( 'edit-flow-block-custom-status', EDIT_FLOW_URL . 'blocks/dist/custom-status.build.js', array( 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-plugins', 'wp-components' ), EDIT_FLOW_VERSION );

		wp_localize_script( 'edit-flow-block-custom-status', 'EditFlowCustomStatuses', $this->get_custom_statuses() );
	}

	/**
	 * Just a wrapper to make sure we're have simple array instead of associative one.
	 *
	 * @return array Custom statuses.
	 */
	function get_custom_statuses() {
		return array_values( $this->ef_module->get_custom_statuses() );
	}
}
