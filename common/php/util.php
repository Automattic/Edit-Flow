<?php

/**
 * Adds an array of capabilities to a role.
 */
function ef_add_caps_to_role( $role, $caps ) {
	global $wp_roles;
	
	if ( $wp_roles->is_role( $role ) ) {
		$role =& get_role( $role );
		foreach ( $caps as $cap )
			$role->add_cap( $cap );
	}
}

if( ! function_exists( 'ef_draft_or_post_title' ) ) :
	/**
	 * Copy of core's _draft_or_post_title without the filters
	 *
	 * The post title is fetched and if it is blank then a default string is
	 * returned.
	 * @param int $post_id The post id. If not supplied the global $post is used.
	 * @return string The post title if set
	 */
	function ef_draft_or_post_title( $post_id = 0 ) {
		$post = get_post( $post_id );
		return ! empty( $post->post_title ) ? $post->post_title : __( '(no title)', 'edit-flow' );
	}
endif;