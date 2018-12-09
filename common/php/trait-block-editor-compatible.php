<?php
/**
 * Experiment: a trait that abstracts the logic to overwrite anything that a specific module does.
 * A prime use case would be not enqueueing EF classic editor assets.
 *
 */
trait Block_Editor_Compatible {
  protected $ef_module;
  protected $hooks;

  /**
   * This method handles init of Module Compat
   *
   * @param EF_Module $module_instance
   * @param array $hooks associative array of hooks and their respective callbacks
   *
   * @todo handle priorities too.
   *
   * @return void
   */
  function __construct( $module_instance, $hooks = [] ) {
    $this->ef_module = $module_instance;
    $this->hooks = $hooks;
    // Highly debatable
    add_action( 'admin_init', [ $this, 'action_admin_init' ], 0 );
  }

  /**
   * Unhook the module's hooks and use the module's Compat hooks instead.
   *
   * This is currently run on admin_init, but needs more testing.
   *
   * @since 0.9
   *
   * @return void
   */
  function action_admin_init() {
    // This conditional is probably not catching all the instances where Gutenberg might be enabled.
    if ( ! isset( $_GET['classic-editor'] ) ) {
      foreach( $this->hooks as $hook => $callback ) {
        remove_action( $hook, array( $this->ef_module, $callback ) );
        add_action( $hook, array( $this, $callback ) );
      }
    }
  }
}