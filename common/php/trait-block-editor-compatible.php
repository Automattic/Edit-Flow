<?php
/**
 * Experiment: a trait that abstracts the logic to overwrite anything that a specific module does.
 * A prime use case would be not enqueueing
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
  function init( $module_instance, $hooks = [] ) {
    $this->ef_module = $module_instance;
    $this->hooks = $hooks;
    // Highly debatable
    add_action( 'admin_init', array( $this, 'action_admin_init' ), 0 );
    return $this;
  }

  /**
   * `wp` is the first place where we know whether Gutenberg is loaded or not.
   *
   * @return void
   */
  function action_admin_init() {
    if ( ! isset( $_GET['classic-editor'] ) ) {
      foreach( $this->hooks as $hook => $callback ) {
        remove_action( $hook, array( $this->ef_module, $callback ) );
        add_action( $hook, array( $this, $callback ) );
      }
    }
  }
}