
<?php
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

if(!class_exists('EF_Advanced_custom_fields')) {
    
class EF_Advanced_custom_fields extends EF_Module {
    
    private $module_name = 'advanced_custom_fields';
        
    public function __construct() {
        $this->module_url = $this->get_module_url( __FILE__ );
        
            // Register the module with Edit Flow and configure titles, descriptions etc.
            $args = array(
                    'title' => __( 'Advanced Custom Fields', 'edit-flow' ),
                    'short_description' => __( 'Change this short description.', 'edit-flow' ),
                    'extended_description' => __( 'Change this extended description', 'edit-flow' ),
                    'module_url' => $this->module_url,
                    'img_url' => $this->module_url . 'lib/advanced_custom_fields\_s128.png',
                    'slug' => 'advanced-custom-fields',
                    'default_options' => array(
                            'enabled' => 'on',
                            'post_types' => array(
                                    'post' => 'on',
                                    'page' => 'off',
                            ),
                    ),
                    'messages' => array(
                            'a-message' => __( 'Your message text', 'edit-flow' ),
                    ),
                    'configure_page_cb' => 'print_configure_view',
                    'settings_help_tab' => array(
                            'id' => 'ef-advanced-custom-fields',
                            'title' => __('Overview', 'edit-flow'),
                            'content' => __('<p>Code your own Edit Flow module with ease. Customize this part.</p>', 'edit-flow'),
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
                    add_settings_field( 'post_types', __( 'Add to these post types:', 'advanced-custom-fields' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
    }
    
    /**
     * Enqueue admin scripts
     */ 
    function add_admin_scripts() {
        wp_enqueue_style( 'edit_flow-advanced-custom-fields -styles', $this->module_url . 'lib/advanced-custom-fields.css', false, EDIT_FLOW_VERSION, 'all' );
    }
    
    /**
     * 
     * @since 0.7
     */
    function print_configure_view() {              
        
        ?>
        <form class="basic-settings" action="<?php echo add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ); ?>" method="post">
                <?php settings_fields( $this->module->options_group_name ); ?>
                <?php do_settings_sections( $this->module->options_group_name ); ?>
                <?php
                        echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
                ?>
                <p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo EDIT_FLOW_SETTINGS_PAGE; ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>

        </form>
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
    