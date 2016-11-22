
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
                    'short_description' => __( 'This modules integrates the popular Advanced Custom Field Plugin.', 'edit-flow' ),
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
    
    public function init() {
        //Enable Advanced Custom Fields forms can be edited
        acf_form_head();
        
        // Anything that needs to happen in the admin
	add_action( 'admin_init', array( $this, 'action_admin_init' ) );
        
        // Register settings
        add_action( 'admin_init', array($this,'ef_acf_settings_init' ));

        // Load necessary scripts and stylesheets
        add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );
        
        //Add Advanced Custom Fields to calenddar if the calendar is activated
        if($this->module_enabled('calendar')) {
            add_filter('ef_calendar_advanced_custom_fields',array($this, 'calendar_integration'));
        }        
    }
            
    /**
     * Anything that needs to happen on the 'admin_init' hook
     *
     * @since 0.7.4
     */
    public function action_admin_init() {
        
    }
    
    /**
     * Enqueue admin scripts
     */ 
    public function add_admin_scripts() {
        wp_enqueue_style( 'edit_flow-advanced-custom-fields -styles', $this->module_url . 'lib/advanced-custom-fields.css', false, EDIT_FLOW_VERSION, 'all' );
    }
    
    /**
     * 
     * @since 0.7
     */
    public function print_configure_view() {              
        $this->ef_acf_options_page();                    
    }
    
    /**
     * Chose the post types for your module
     *
     * @since 0.7
     */
    public function settings_post_types_option() {
            global $edit_flow;
            $edit_flow->settings->helper_option_custom_post_type( $this->module );
    }   
    
    
    /**
     * Generates the fields, which are going to be shown in the calendar view.
     * 
     * @return string
     */    
    public function calendar_integration($post_id){          
        $options = get_option('ef_acf_settings');
        
        $visible_fields = array();
        
        foreach ($this->get_post_meta_keys() as $value) {
            if(checked($options[$value],1,false)) {
                $visible_fields[] = $value;
            }
        }
        
        acf_form(array('post_id' => $post_id,'fields' => $visible_fields));        
    }
    
    public function ef_acf_settings_init() { 

	register_setting( 'pluginPage', 'ef_acf_settings' );

	add_settings_section(
		'ef_acf_pluginPage_section', 
		__( '', 'wordpress' ), 
		array($this,'ef_acf_settings_section_callback'), 
		'pluginPage'
	);
        
        $this->add_settings_fields();
    }
    
    private function add_settings_fields(){
        foreach ($this->get_post_meta_keys() as $key => $value) {
            
            add_settings_field(
                    $value,
                    $value,
                    array($this, 'render_checkbox'),
                    'pluginPage',
                    'ef_acf_pluginPage_section',
                    array('value' => $value)
            );
        }
    }
    
    public function render_checkbox($value){
        $options = get_option('ef_acf_settings');
        
        ?>
        <input type="checkbox" name="ef_acf_settings[<?php echo $value['value']; ?>]" <?php checked($options[$value['value']],1); ?> value="1">
        <?php
    }
    
    public function ef_acf_settings_section_callback() { 
            echo __( 'Select which of the Advanced Custom Fields should be shown on the calendar', 'wordpress' );
    }

    public function ef_acf_options_page() { 
        ?>
        <form action='options.php' method='post'>
                <?php
                settings_fields( 'pluginPage' );
                do_settings_sections( 'pluginPage' );
                submit_button();
                ?>
        </form>
        <?php
    }

    private function get_post_meta_keys() {         
        //get all post ids
        $args = array('post_type' => 'post');
        $all_post_ids = array();        
        $post_query = new WP_Query($args);
        
        if($post_query->have_posts() ) {
            while($post_query->have_posts() ) {
                $post_query->the_post();
                $all_post_ids[] = get_the_ID();
            }
        }                
        
        //get all custom fields
        $all_meta_fields = array();
        
        foreach ($all_post_ids as $post_id) {
            foreach (get_post_custom_keys($post_id) as $key => $value ) {
                $valuet = trim($value);
                if ( '_' == $valuet{0} )
                    continue;            
                if(!in_array($value, $all_meta_fields)){
                    $all_meta_fields[] = $value;
                }
            } 
        }        
        return $all_meta_fields;
    }    
}    
}    