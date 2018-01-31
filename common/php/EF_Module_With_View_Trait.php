<?php


trait EF_Module_With_View_Trait {


	/**
	 * Whether or not the current page is an Edit Flow settings view (either main or module)
	 * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug
	 * If there's no module name specified, it will return true against all Edit Flow settings views
	 *
	 * @since 0.8.3
	 *
	 * @param string $slug (Optional) Module name to check against
	 * @return bool true if is module settings view
	 */
	public function is_module_settings_view( $slug = false ) {
		global $pagenow, $edit_flow;

		// All of the settings views are based on admin.php and a $_GET['page'] parameter
		if ( $pagenow !== 'admin.php' || ! isset( $_GET['page'] ) )
			return false;

		$settings_view_slugs = array();
		// Load all of the modules that have a settings slug/ callback for the settings page
		foreach ( $edit_flow->modules as $mod_name => $mod_data ) {
			if ( isset( $mod_data->options->enabled ) && $mod_data->options->enabled == 'on' && $mod_data->configure_page_cb )
				$settings_view_slugs[] = $mod_data->settings_slug;
		}

		// The current page better be in the array of registered settings view slugs
		if ( empty( $settings_view_slugs ) || ! in_array( $_GET['page'], $settings_view_slugs ) ) {
			return false;
		}

		if ( $slug && $edit_flow->modules->{$slug}->settings_slug !== $_GET['page'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether if we're at module settings view
	 * for the current module based on `is_module_settings_view` method
	 *
	 * @return bool
	 */
	public function is_current_module_settings_view() {
		return $this->is_module_settings_view( $this->module->name );
	}


	/**
	 * Check for admin page and whether the current post type is supported
	 * @param array $allowed_pages
	 *
	 * @return bool
	 */
	function is_active_view( $allowed_pages = array( 'edit.php', 'post.php', 'post-new.php' ) ) {
		return ( $this->is_admin_page( $allowed_pages ) && $this->is_supported_post_type() );
	}


	/**
	 * Check whether the current post type is supported for $this module
	 *
	 * @return bool
	 */
	public function is_supported_post_type() {
		$post_type = $this->get_current_post_type();

		return (
			$post_type
			&&
			in_array( $post_type, $this->get_post_types_for_module( $this->module ), true )
		);
	}

	/**
	 * Checks for the current post type
	 *
	 * @since 0.7
	 * @return string|null $post_type The post type we've found, or null if no post type
	 */
	function get_current_post_type() {
		global $post, $typenow, $pagenow, $current_screen;
		//get_post() needs a variable
		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : false;

		if ( $post && $post->post_type ) {
			$post_type = $post->post_type;
		} elseif ( $typenow ) {
			$post_type = $typenow;
		} elseif ( $current_screen && ! empty( $current_screen->post_type ) ) {
			$post_type = $current_screen->post_type;
		} elseif ( isset( $_REQUEST['post_type'] ) ) {
			$post_type = sanitize_key( $_REQUEST['post_type'] );
		} elseif ( 'post.php' == $pagenow
		           && $post_id
		           && ! empty( get_post( $post_id )->post_type ) ) {
			$post_type = get_post( $post_id )->post_type;
		} elseif ( 'edit.php' == $pagenow && empty( $_REQUEST['post_type'] ) ) {
			$post_type = 'post';
		} else {
			$post_type = null;
		}

		return $post_type;
	}

	/**
	 * Check whether currently viewing the desired admin page
	 *
	 * @param array $allowed_pages
	 *
	 * @return bool
	 */
	public function is_admin_page( $allowed_pages = array( 'edit.php', 'post.php', 'post-new.php' ) ) {
		global $pagenow;

		return ( $pagenow && in_array( $pagenow, $allowed_pages, true ) );
	}


	/**
	 * Shorthand for `is_active_view` to check for list type views ( list of posts pages, custom post types )
	 *
	 * @see is_active_view
	 * @return bool
	 */
	public function is_active_list_view() {
		return $this->is_active_view( array( 'edit.php' ) );
	}

	/**
	 * Shorthand for `is_active_view` to check for editor mode
	 *
	 * @see is_active_view
	 * @return bool
	 */
	public function is_active_editor_view() {
		return $this->is_active_view( array( 'post.php', 'posts-new.php' ) );
	}


	/**
	 * This method was supposed to check whether or not the current page is a user-facing Edit Flow View
	 * But it was never implemented
	 *
	 * It is now deprecated in favor of `$this->is_active_view`
	 * @see        EF_Module::is_active_view()
	 * @since      0.7
	 * @deprecated 0.8.3
	 *
	 * @param string $module_name (Optional) Module name to check against
	 */
	public function is_whitelisted_functional_view( $module_name = null ) {
		_deprecated_function( __FUNCTION__, '0.8.3', 'is_active_view' );

		return true;
	}

	/**
	 * Whether or not the current page is an Edit Flow settings view (either main or module)
	 * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug
	 * If there's no module name specified, it will return true against all Edit Flow settings views
	 *
	 * @since 0.7
	 * @deprecated 0.8.3
	 *
	 * @param string $module_name (Optional) Module name to check against
	 * @return bool $is_settings_view Return true if it is
	 */
	public function is_whitelisted_settings_view( $module_name = null ) {
		_deprecated_function( 'is_whitelisted_settings_view', '0.8.3', 'is_module_settings_view' );

		return $this->is_module_settings_view( $module_name );
	}

}