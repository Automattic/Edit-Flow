<?php
/**
 * class EF_Helpers
 */

if ( !class_exists( 'EF_Helpers' ) ) {
	
class EF_Helpers
{
	
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
	
}	
	
}
