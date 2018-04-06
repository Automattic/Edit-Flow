<?php
require_once '/lib/coder.php';
/**
 * class EF_My_Module
 * This class is the core of your new ef module. It sets up the main admin backend functionalities.
 * 
 * The class needs to extend the EF_Module
 * 
 * @author Michael Scheurer based on code and comments from sbressler, danielbachhuber and batmoo
 * 
 * 
 */

if(!class_exists('EF_My_Module')) {
    
class EF_My_Module extends EF_Module {

    private $module_name = 'my_module';    
    
    public function __construct() {
        $this->module_url = $this->get_module_url( __FILE__ );
        
            // Register the module with Edit Flow and configure titles, descriptions etc.
            $args = array(
                    'title' => __( 'My Module', 'edit-flow' ),
                    'short_description' => __( 'Create your own Edit Flow Module within one click.', 'edit-flow' ),
                    'extended_description' => __( 'This module includes the basic functionalities to code easily your '
                            . 'own Edit Flow module. The module sets up the main admin functionalities.', 'edit-flow' ),
                    'module_url' => $this->module_url,
                    'img_url' => $this->module_url . 'lib/my_module_s128.png',
                    'slug' => 'my-module',
                    'default_options' => array(
                            'enabled' => 'off',
                            'post_types' => array(
                                    'post' => 'off',
                                    'page' => 'off',
                            ),
                    ),
                    'messages' => array(
                            'a-message' => __( "Your message text", 'edit-flow' ),
                    ),
                    'configure_page_cb' => 'print_configure_view',
                    'settings_help_tab' => array(
                            'id' => 'ef-my-module',
                            'title' => __('Overview', 'edit-flow'),
                            'content' => __('<p>Code your own Edit Flow module with ease. Just copy the folder and extend the code.</p>', 'edit-flow'),
                            ),
                    'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
            );
            EditFlow()->register_module( $this->module_name, $args );
    }
    
    function init() {
        // Anything that needs to happen in the admin
	add_action( 'admin_init', array( $this, 'action_admin_init' ) );
        
        // Register our settings
	add_action( 'admin_init', array( $this, 'register_settings' ) );        

        // Load necessary scripts and stylesheets
        add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );
        
        //handle form actions
        add_action('admin_init', array($this, 'create_module'));
    }
    
    function create_module(){
        
        if(isset($_POST['my-module-name'])){
            $coder = new EF_Coder($_POST['my-module-name']); 
            
            header("Refresh:0");
        }               
    }
    
    /**
     * Anything that needs to happen on the 'admin_init' hook
     *
     * @since 0.7.4
     */
    function action_admin_init() {

    }
    
    /**
     * Register settings for notifications so we can partially use the Settings API
     * (We use the Settings API for form generation, but not saving)
     * 
     * @since 0.7
     * @uses add_settings_section(), add_settings_field()
     */
    function register_settings() {
                    add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
                    add_settings_field( 'post_types', __( 'Add to these post types:', 'my-module' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
    }
    
    /**
     * Enqueue admin scripts
     */ 
    function add_admin_scripts() {
        wp_enqueue_style( 'edit_flow-my-module-styles', $this->module_url . 'lib/my-module.css', false, EDIT_FLOW_VERSION, 'all' );
    }
    
    /**
     * 
     * @since 0.7
     */
    function print_configure_view() {              
        
        ?>

<div>
    <hr>
    <h2>Step 1: Choose the name</h2>
        <form class="basic-settings" action="<?php echo add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ); ?>" method="POST">

            <input id="my-module-name" name="my-module-name" value="title of your module"> The Title will be shown on the left side in the admin bar of Edit Flow.
            <?php            submit_button() ?>
        </form>
    
    <h2>Step 2: Extend it!</h2>
        On the left side, you'll see now your new module in the admin bar.<br><br>
        Load the new code into your IDE and extend the new Edit Flow module.
    
    <h2>Step 3: Share it!</h2>
    Make thousands of yousers happy with your new module. Share it on <a href="https://github.com/Automattic/Edit-Flow">Github.</a><br>
    Fork the project, and make a pull request with you new module. <br>
    
    <h3>Attention: If you don't work with github, your new Plugin will be lost on the next update of Edit Flow!</h3>
    
</div>

        <?php
    }
    
    /**
     * Chose the post types for your module
     *
     * @since 0.7
     */
    function settings_post_types_option() {
            global $edit_flow;
            $edit_flow->settings->helper_option_custom_post_type( $this->module );
    }

}
    
}