<?php
/**
 * class EF_Helpers
 */

if ( !class_exists( 'EF_Helpers' ) ) {
	
class EF_Helpers {
	
	function __construct() {
		
	}
	
	/**
	 * Cleans up the 'on' and 'off' for post types on a given module (so we don't get warnings all over)
	 * For every post type that doesn't explicitly have the 'on' value, turn it 'off'
	 * If add_post_type_support() has been used anywhere (legacy support), inherit the state
	 *
	 * @param array $module_post_types Current state of post type options for the module
	 * @param string $post_type_support What the feature is called for post_type_support (e.g. 'ef_calendar')
	 * @return array $normalized_post_type_options The setting for each post type, normalized based on rules
	 *
	 * @since 0.7
	 */
	function clean_post_type_options( $module_post_types = array(), $post_type_support = null ) {
		$normalized_post_type_options = array();
		$pt_args = array(
			'_builtin' => false,
		);
		$all_post_types = get_post_types( $pt_args, 'names' );
		array_push( $all_post_types, 'post', 'page' );
		foreach( $all_post_types as $post_type ) {
			if ( ( isset( $module_post_types[$post_type] ) && $module_post_types[$post_type] == 'on' ) || post_type_supports( $post_type, $post_type_support ) )
				$normalized_post_type_options[$post_type] = 'on';
			else
				$normalized_post_type_options[$post_type] = 'off';
		}
		return $normalized_post_type_options;
	}
	
	/**
	 * Collect all of the active post types for a given module
	 *
	 * @param object $module Module's data
	 * @return array $post_types All of the post types that are 'on'
	 *
	 * @since 0.7
	 */
	function get_post_types_for_module( $module ) {
		
		$post_types = array();
		if ( isset( $module->options->post_types ) && is_array( $module->options->post_types ) ) {
			foreach( $module->options->post_types as $post_type => $value )
				if ( 'on' == $value )
					$post_types[] = $post_type;
		}
		return $post_types;
	}
	
	/**
	 * Get all of the currently available post statuses
	 * This should be used in favor of calling $edit_flow->custom_status->get_custom_statuses() directly
	 *
	 * @return object $post_statuses All of the post statuses that aren't a published state
	 *
	 * @since 0.7
	 */
	function get_post_statuses() {
		global $edit_flow;
		
		if ( isset( $edit_flow->custom_status ) && $edit_flow->custom_status->module->options->enabled == 'on' ) {
		 	return $edit_flow->custom_status->get_custom_statuses();
		} else {
			$post_statuses = array(
				(object)array(
					'name' => __( 'Draft' ),
					'description' => '',
					'slug' => 'draft',
				),
				(object)array(
					'name' => __( 'Pending Review' ),
					'description' => '',
					'slug' => 'pending',
				),				
			);
			return (object)$post_statuses;
		}
	}
	
	/**
	 * get_post_status_friendly_name()
	 * Returns the friendly name for a given status
	 *
	 * @since 0.7
	 *
	 * @param string $status The status slug
	 * @return string $status_friendly_name The friendly name for the status
	 */
	function get_post_status_friendly_name( $status ) {
		global $edit_flow;
		
		$status_friendly_name = '';
		
		$builtin_stati = array(
			'publish' => __( 'Published', 'edit-flow' ),
			'draft' => __( 'Draft', 'edit-flow' ),
			'future' => __( 'Scheduled', 'edit-flow' ),
			'private' => __( 'Private', 'edit-flow' ),
			'pending' => __( 'Pending Review', 'edit-flow' ),
			'trash' => __( 'Trash', 'edit-flow' ),
		);
		
		if ( array_key_exists( $status, $builtin_stati ) ) {
			$status_friendly_name = $builtin_stati[$status];
		} else if ( isset( $edit_flow->custom_status ) && $edit_flow->custom_status->module->options->enabled == 'on' ) {
			$status_object = $edit_flow->custom_status->get_custom_status( $status );
			if( $status_object && !is_wp_error( $status_object ) ) {
				$status_friendly_name = $status_object->name;
			}
		}
		
		return $status_friendly_name;
	}
	
	/**
	 * Enqueue any resources (CSS or JS) associated with datepicker functionality
	 *
	 * @since 0.7
	 */
	function enqueue_datepicker_resources() {
		// First scripts
		wp_enqueue_script('jquery-ui-datepicker', EDIT_FLOW_URL . 'js/lib/jquery.ui.datepicker.min.js', array( 'jquery', 'jquery-ui-core'), EDIT_FLOW_VERSION, true );
		wp_enqueue_script('edit_flow-date_picker', EDIT_FLOW_URL . 'js/ef_date.js', array( 'jquery-ui-datepicker' ), EDIT_FLOW_VERSION, true);

		// Now styles
		wp_enqueue_style( 'jquery-ui-datepicker', EDIT_FLOW_URL . 'css/lib/jquery.ui.datepicker.css', array( 'wp-jquery-ui-dialog' ), EDIT_FLOW_VERSION, 'screen' );
		wp_enqueue_style( 'jquery-ui-theme', EDIT_FLOW_URL . 'css/lib/jquery.ui.theme.css', false, EDIT_FLOW_VERSION, 'screen' );
	}
}
}
