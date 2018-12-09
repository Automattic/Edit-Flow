<?php
class EF_Custom_Status_Block_Editor_Compat {
  protected $ef_module;
  // @see the trait for the implementation details
  use Block_Editor_Compatible;

  function action_admin_enqueue_scripts() {
    if ( $this->ef_module->disable_custom_statuses_for_post_type() )
      return;

		wp_enqueue_style( 'edit-flow-blocks', EDIT_FLOW_URL . 'blocks/dist/blocks.editor.build.css', false, null  );
		wp_enqueue_script( 'edit-flow-blocks', EDIT_FLOW_URL . 'blocks/dist/blocks.build.js', array("wp-blocks", "wp-element", "wp-edit-post", "wp-plugins", "wp-components" ) );

		wp_localize_script( 'edit-flow-blocks', 'EditFlowCustomStatuses', $this->get_custom_statuses() );

  }

  function get_custom_statuses() {
    return array_values( $this->ef_module->get_custom_statuses() );
  }
}