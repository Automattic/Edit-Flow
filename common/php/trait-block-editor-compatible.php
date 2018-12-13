<?php
/**
 * Block_Editor_Compatible aims to be an abstract enough compatibility logic
 */

 // phpcs:disable WordPressVIPMinimum.Variables.VariableAnalysis.SelfOutsideClass

trait Block_Editor_Compatible {
	/**
	 * Holds the reference to the Module's object
	 *
	 * @var EF_Module
	 */
	protected $ef_module;
	/**
	 * Holds associative array of hooks and their respective callbacks
	 *
	 * @var array
	 */
	protected $hooks = [];
	protected static $active_plugins;

	/**
	 * This method handles init of Module Compat
	 *
	 * @param EF_Module $module_instance
	 * @param array $hooks associative array of hooks and their respective callbacks
	 *
	 *
	 * @return void
	 */
	function __construct( $module_instance, $hooks = [] ) {
		$this->ef_module = $module_instance;
		$this->hooks = $hooks;

		if ( is_admin() ) {

			add_action( 'init', [ $this, 'action_init_for_admin' ], 15 );
		}
	}

	/**
	 * Unhook the module's hooks and use the module's Compat hooks instead.
	 *
	 * This is currently run on init action, but only when `is_admin` is true.
	 *
	 * @since 0.9
	 *
	 * @return void
	 */
	function action_init_for_admin() {
		$this->check_active_plugins();

		if ( $this->should_apply_compat() ) {
			foreach ( $this->hooks as $hook => $callback ) {
				if ( is_callable( [ $this, $callback ] ) ) {
					remove_action( $hook, array( $this->ef_module, $callback ) );
					add_action( $hook, array( $this, $callback ) );
				}
			}
		}
	}

	function check_active_plugins() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		self::$active_plugins = [
			'classic-editor' => is_plugin_active( 'classic-editor' ),
			'gutenberg'      => is_plugin_active( 'gutenberg' ),
		];
	}

	/**
	 * Helper function to determine whether we're running WP 5.0.
	 *
	 * @return boolean
	 */
	public static function is_at_least_50() {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	/**
	 * Helper to determine whether either Gutenberg plugin or Classic  Editor plugin is loaded.
	 *
	 * @param string $slug
	 * @return boolean
	 */
	public static function is_plugin_active( $slug = '' ) {
		return isset( self::$active_plugins[ $slug ] ) && self::$active_plugins[ $slug ];
	}

	/**
	 * Detect whether we should load compatability for the module.
	 * This runs very early during request lifecycle and may not be precise. It's better to use `get_current_screen()->is_block_editor()` if it's available.
	 *
	 * However, Block Editor can be enabled/disabled on a granular basis via the filters for the core and the plugin versions.
	 *
	 * use_block_editor_for_post
	 * use_block_editor_for_post_type
	 * gutenberg_can_edit_post_type
	 * gutenberg_can_edit_post
	 *
	 *
	 * This needs to be handled in the compat hook callback.
	 *
	 * If any of $conditions evaluates to TRUE, we should apply compat hooks.
	 *
	 * @return boolean
	 */
	protected function should_apply_compat() {
		$conditions = [
			/**
			 * 5.0:
			 *
			 * Classic editor either disabled or enabled (either via an option or with GET argument).
			 * It's a hairy conditional :(
			 */
			// phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
			self::is_at_least_50() && ! self::is_plugin_active( 'classic-editor' ),
			self::is_at_least_50() && self::is_plugin_active( 'classic-editor' ) && ( get_option( 'classic-editor-replace' ) === 'block' && ! isset( $_GET[ 'classic-editor__forget' ] ) ),
			self::is_at_least_50() && self::is_plugin_active( 'classic-editor' ) && ( get_option( 'classic-editor-replace' ) === 'classic' && isset( $_GET[ 'classic-editor__forget' ] ) ),
			/**
			 * < 5.0 but Gutenberg plugin is active.
			 */
			! self::is_at_least_50() && self::is_plugin_active( 'gutenberg' ),
		];

		return count( array_filter( $conditions, function( $c ) { return (bool) $c; } ) ) > 0;
	}
}