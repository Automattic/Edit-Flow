<?php

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