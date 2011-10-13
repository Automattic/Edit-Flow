<?php
/**
 * class EF_Dashboard
 * All of the code for the dashboard widgets from Edit Flow
 *
 * Dashboard widgets currently:
 * - Post Status Widget - Shows numbers for all (custom|post) statuses
 * - Posts I'm Following Widget - Show the headlines with edit links for posts I'm following
 *
 * @todo for 0.7
 * - Update the Posts I'm Following widget to use new activity class
 */

if ( !class_exists('EF_Dashboard') ) {

class EF_Dashboard {
	
	/**
	 * Load the EF_Dashboard class as an Edit Flow module
	 */
	function __construct () {
		global $edit_flow;
		
		// Register the module with Edit Flow	
		$args = array(
			'title' => __( 'Dashboard Widgets', 'edit-flow' ),
			'short_description' => __( 'Quickly access an overview of your content.', 'edit-flow' ),
			'extended_description' => __( 'This is a longer description that shows up on some views. We might want to include a link to documentation. tk', 'edit-flow' ),
			'img_url' => false,
			'slug' => 'dashboard',
			'post_type_support' => 'ef_dashboard',
			'default_options' => array(
				'enabled' => 'on',
				'post_status_widget' => 'on',
				'my_posts_widget' => 'on',
			),
			'configure_page_cb' => 'print_configure_view',
			'configure_link_text' => __( 'Widget Options', 'edit-flow' ),		
		);
		$this->module = $edit_flow->register_module( 'dashboard', $args );
	}
	
	/**
	 * Initialize all of the class' functionality if its enabled
	 */
	function init() {
		
		// Add the widgets to the dashboard
		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widgets') );
		
		// Register our settings
		add_action( 'admin_init', array( &$this, 'register_settings' ) );		
		
	}
	
	/**
	 * Add Edit Flow dashboard widgets to the WordPress admin dashboard
	 */
	function add_dashboard_widgets() {
		global $edit_flow, $current_user;
		
		// Only show dashboard widgets for Contributor or higher
		if ( !$current_user->has_cap('edit_posts') ) 
			return;
		
		wp_enqueue_style( 'edit-flow-dashboard-css', EDIT_FLOW_URL . 'css/dashboard.css', false, EDIT_FLOW_VERSION, 'all' );			
			
		// Set up Post Status widget but, first, check to see if it's enabled
		if ( $this->module->options->post_status_widget == 'on')
			wp_add_dashboard_widget( 'post_status_widget', __( 'Unpublished Content', 'edit-flow' ), array( &$this, 'post_status_widget' ) );
			
		// Add the MyPosts widget, if enabled
		if ( $this->module->options->my_posts_widget == 'on')			
			wp_add_dashboard_widget( 'myposts_widget', __( 'Posts I\'m Following', 'edit-flow' ), array( &$this, 'myposts_widget' ) );

	}
	
	/**
	 * Creates Post Status widget
	 * Display an at-a-glance view of post counts for all (post|custom) statuses in the system
	 *
	 * @todo Support custom post types
	 */
	function post_status_widget () {
		global $edit_flow;
		
		$statuses = $edit_flow->helpers->get_post_statuses();
		// If custom statuses are enabled, we'll output a link to edit the terms just below the post counts
		if ( $edit_flow->helpers->module_enabled( 'custom_status' ) )
			$edit_custom_status_url = add_query_arg( 'configure', 'custom-status', EDIT_FLOW_SETTINGS_PAGE );
		
		?>
		<p class="sub"><?php _e('Posts at a Glance', 'edit-flow') ?></p>
		
		<div class="table">
			<table>
				<tbody>
					<?php foreach($statuses as $status) : ?>
						<?php $filter_link = esc_url($edit_flow->helpers->filter_posts_link($status->slug)) ?>
						<tr>
							<td class="b">
								<a href="<?php echo $filter_link; ?>">
									<?php
									$post_count = wp_count_posts( 'post' );
									$slug = $status->slug;
									echo esc_html( $post_count->$slug ); ?>
								</a>
							</td>
							<td>
								<a href="<?php echo $filter_link; ?>"><?php echo esc_html( $status->name ); ?></a>
							</td>
						</tr>
							
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( isset( $edit_custom_status_url ) ) : ?>
				<span class="small"><a href="<?php echo $edit_custom_status_url; ?>"><?php _e( 'Edit Custom Statuses', 'edit-flow' ); ?></a></span>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * Creates My Posts widget
	 * Shows a list of the "posts you're following" sorted by most recent activity.
	 */ 
	function myposts_widget() {
		global $edit_flow;
		
		$myposts = ef_get_user_following_posts();
		
		?>
		<div class="ef-myposts">
			<?php if( !empty($myposts) ) : ?>
				
				<?php foreach( $myposts as $post ) : ?>
					<?php
					$url = esc_url(get_edit_post_link( $post->ID ));
					$title = esc_html($post->post_title);
					?>
					<li>
						<h4><a href="<?php echo $url ?>" title="<?php _e('Edit this post', 'edit-flow') ?>"><?php echo $title; ?></a></h4>
						<span class="ef-myposts-timestamp"><?php _e('This post was last updated on', 'edit-flow') ?> <?php echo get_the_time('F j, Y \\a\\t g:i a', $post) ?></span>
					</li>	
				<?php endforeach; ?>
			<?php else : ?>
				<p><?php _e('Sorry! You\'re not subscribed to any posts!', 'edit-flow') ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * Register settings for notifications so we can partially use the Settings API
	 * (We use the Settings API for form generation, but not saving)
	 * 
	 * @since 0.7
	 */
	function register_settings() {
		
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_status_widget', __( 'Post Status Widget', 'edit-flow' ), array( &$this, 'settings_post_status_widget_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'my_posts_widget',__( 'Posts I\'m Following', 'edit-flow' ), array( &$this, 'settings_my_posts_widget_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );

	}
	
	/**
	 * Enable or disable the Post Status Widget for the WP dashboard
	 *
	 * @since 0.7
	 */
	function settings_post_status_widget_option() {
		$options = array(
			'off' => __( 'Disabled', 'edit-flow' ),			
			'on' => __( 'Enabled', 'edit-flow' ),
		);
		echo '<select id="post_status_widget" name="' . $this->module->options_group_name . '[post_status_widget]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->post_status_widget, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
	
	/**
	 * Enable or disable the Posts I'm Following Widget for the WP dashboard
	 *
	 * @since 0.7
	 */
	function settings_my_posts_widget_option() {
		$options = array(
			'off' => __( 'Disabled', 'edit-flow' ),			
			'on' => __( 'Enabled', 'edit-flow' ),
		);
		echo '<select id="my_posts_widget" name="' . $this->module->options_group_name . '[my_posts_widget]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"';
			echo selected( $this->module->options->my_posts_widget, $value );			
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';		
	}
	
	/**
	 * Validate the field submitted by the user
	 *
	 * @since 0.7
	 */
	function settings_validate( $new_options ) {
		global $edit_flow;
		
		// Follow whitelist validation for modules
		if ( $new_options['post_status_widget'] != 'on' )
			$new_options['post_status_widget'] = 'off';
			
		if ( $new_options['my_posts_widget'] != 'on' )
			$new_options['my_posts_widget'] = 'off';
		
		return $new_options;
	}	
	
	/**
	 * Settings page for the dashboard
	 *
	 * @since 0.7
	 */
	function print_configure_view() {
		global $edit_flow;
		?>
		<form class="basic-settings" action="<?php echo esc_url( add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>	
			<?php				
				echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
				submit_button();
			?>
		</form>
		<?php
	}
}

} // END - !class_exists('EF_Dashboard')
