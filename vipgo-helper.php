<?php
/**
 * Ensure Edit Flow is instantiated
 */
add_action( 'after_setup_theme', 'EditFlow' );

/**
 * Edit Flow loads modules after plugins_loaded, which has already been fired when loading via wpcom_vip_load_plugins
 * Let's run the method at after_setup_themes
 */
add_filter( 'after_setup_theme', 'edit_flow_wpcom_load_modules' );
function edit_flow_wpcom_load_modules() {
	global $edit_flow;
	if ( method_exists( $edit_flow, 'action_ef_loaded_load_modules' ) ) {
		$edit_flow->action_ef_loaded_load_modules();
	}
}
