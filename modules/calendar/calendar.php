<?php
/**
 * class EF_Calendar
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * @author danielbachhuber
 */
if ( !class_exists('EF_Calendar') ) {

class EF_Calendar extends EF_Module {
	
	const usermeta_key_prefix = 'ef_calendar_';
	const screen_id = 'dashboard_page_calendar';
	
	var $module;
	
	var $start_date = '';
	var $current_week = 1;
	var $total_weeks = 6;	
	
	/**
	 * Construct the EF_Calendar class
	 */
	function __construct() {
		global $edit_flow;
	
		$this->module_url = $this->get_module_url( __FILE__ );
		// Register the module with Edit Flow	
		$args = array(
			'title' => __( 'Calendar', 'edit-flow' ),
			'short_description' => __( 'View upcoming content in a customizable calendar.', 'edit-flow' ),
			'extended_description' => __( 'Edit Flow’s calendar lets you see your posts over a customizable date range. Filter by status or click on the post title to see its details. Drag and drop posts between days to change their publication date date.', 'edit-flow' ),
			'module_url' => $this->module_url,
			'img_url' => $this->module_url . 'lib/calendar_s128.png',
			'slug' => 'calendar',
			'post_type_support' => 'ef_calendar',
			'default_options' => array(
				'enabled' => 'on',
				'post_types' => array(
					'post' => 'on',
					'page' => 'off',
				),
			),
			'messages' => array(
				'post-date-updated' => __( "Post date updated.", 'edit-flow' ),
				'update-error' => __( 'There was an error updating the post. Please try again.', 'edit-flow' ),
				'published-post-ajax' => __( "Updating the post date dynamically doesn't work for published content. Please <a href='%s'>edit the post</a>.", 'edit-flow' ),
			),
			'configure_page_cb' => 'print_configure_view',
			'configure_link_text' => __( 'Calendar Options', 'edit-flow' ),
			'settings_help_tab' => array(
				'id' => 'ef-calendar-overview',
				'title' => __('Overview', 'edit-flow'),
				'content' => __('<p>The calendar is a convenient week-by-week or month-by-month view into your content. Quickly see which stories are on track to being published on time, and which will need extra effort.</p>', 'edit-flow'),
				),
			'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/calendar/">Calendar Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
		);
		$this->module = EditFlow()->register_module( 'calendar', $args );		
		
	}
	
	/**
	 * Initialize all of our methods and such. Only runs if the module is active
	 *
	 * @uses add_action()
	 */
	function init() {
		
		// Check whether the user should have the ability to view the calendar
		$view_calendar_cap = 'ef_view_calendar';
		$view_calendar_cap = apply_filters( 'ef_view_calendar_cap', $view_calendar_cap );
		if ( !current_user_can( $view_calendar_cap ) ) return false;
		
		require_once( EDIT_FLOW_ROOT . '/common/php/' . 'screen-options.php' );
		add_screen_options_panel( self::usermeta_key_prefix . 'screen_options', __( 'Calendar Options', 'edit-flow' ), array( $this, 'generate_screen_options' ), self::screen_id, false, true );
		add_action( 'admin_init', array( $this, 'handle_save_screen_options' ) );
		
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );		
		add_action( 'admin_print_styles', array( $this, 'add_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Ajax manipulation for the calendar
		add_action( 'wp_ajax_ef_calendar_drag_and_drop', array( $this, 'handle_ajax_drag_and_drop' ) );
	}
	
	/**
	 * Load the capabilities onto users the first time the module is run
	 *
	 * @since 0.7
	 */
	function install() {

		// Add necessary capabilities to allow management of calendar
		// view_calendar - administrator --> contributor
		$calendar_roles = array(
			'administrator' => array('ef_view_calendar'),
			'editor' =>        array('ef_view_calendar'),
			'author' =>        array('ef_view_calendar'),
			'contributor' =>   array('ef_view_calendar')
		);

		foreach ( $calendar_roles as $role => $caps ) {
			$this->add_caps_to_role( $role, $caps );
		}
	}

	/**
	 * Upgrade our data in case we need to
	 *
	 * @since 0.7
	 */
	function upgrade( $previous_version ) {
		global $edit_flow;

		// Upgrade path to v0.7
		if ( version_compare( $previous_version, '0.7' , '<' ) ) {
			// Migrate whether the calendar was enabled or not and clean up old option
			if ( $enabled = get_option( 'edit_flow_calendar_enabled' ) )
				$enabled = 'on';
			else
				$enabled = 'off';
			$edit_flow->update_module_option( $this->module->name, 'enabled', $enabled );
			delete_option( 'edit_flow_calendar_enabled' );

			// Technically we've run this code before so we don't want to auto-install new data
			$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
		}
		
	}
	
	/**
	 * Add the calendar link underneath the "Dashboard"
	 *
	 * @uses add_submenu_page
	 */
	function action_admin_menu() {	
		add_submenu_page('index.php', __('Calendar', 'edit-flow'), __('Calendar', 'edit-flow'), apply_filters( 'ef_view_calendar_cap', 'ef_view_calendar' ), $this->module->slug, array( $this, 'view_calendar' ) );
	}
	
	/**
	 * Add any necessary CSS to the WordPress admin
	 *
	 * @uses wp_enqueue_style()
	 */
	function add_admin_styles() {
		global $pagenow;
		// Only load calendar styles on the calendar page
		if ( $pagenow == 'index.php' && isset( $_GET['page'] ) && $_GET['page'] == 'calendar' )
			wp_enqueue_style( 'edit-flow-calendar-css', $this->module_url . 'lib/calendar.css', false, EDIT_FLOW_VERSION );
	}
	
	/**
	 * Add any necessary JS to the WordPress admin
	 *
	 * @since 0.7
	 * @uses wp_enqueue_script()
	 */
	function enqueue_admin_scripts() {
		
		if ( $this->is_whitelisted_functional_view() ) {
			$js_libraries = array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-sortable',
				'jquery-ui-draggable',
				'jquery-ui-droppable',
			);
			foreach( $js_libraries as $js_library ) {
				wp_enqueue_script( $js_library );
			}
			wp_enqueue_script( 'edit-flow-calendar-js', $this->module_url . 'lib/calendar.js', $js_libraries, EDIT_FLOW_VERSION, true );
		}
		
	}
	
	/**
	 * Prepare the options that need to appear in Screen Options
	 *
	 * @since 0.7
	 */ 
	function generate_screen_options() {
		
		$output = '';
		$screen_options = $this->get_screen_options();
		
		$output .= __( 'Number of Weeks: ', 'edit-flow' );
		$output .= '<select id="' . self::usermeta_key_prefix . 'num_weeks" name="' . self::usermeta_key_prefix . 'num_weeks">';
		for( $i = 1; $i <= 12; $i++ ) {
			$output .= '<option value="' . esc_attr( $i ) . '" ' . selected( $i, $screen_options['num_weeks'], false ) . '>' . esc_attr( $i ) . '</option>';
		}
		$output .= '</select>';
		
		$output .= '&nbsp;&nbsp;&nbsp;<input id="screen-options-apply" name="screen-options-apply" type="submit" value="' . __( 'Apply' ) . '" class="button-secondary" />';
		
		return $output;	
	}
	
	/**
	 * Handle the request to save the screen options
	 *
	 * @since 0.7
	 */
	function handle_save_screen_options() {
		
		// Only handle screen options submissions from the current screen
		if ( !isset( $_POST['screen-options-apply'], $_POST['ef_calendar_num_weeks'] ) )
			return;
		
		// Nonce check
		if ( !wp_verify_nonce( $_POST['_wpnonce-' . self::usermeta_key_prefix . 'screen_options'], 'save_settings-' . self::usermeta_key_prefix . 'screen_options' ) )
			wp_die( $this->module->messages['nonce-failed'] );
		
		// Get the current screen options
		$screen_options = $this->get_screen_options();
		
		// Save the number of weeks to show
		$screen_options['num_weeks'] = (int)$_POST['ef_calendar_num_weeks'];
		
		// Save the screen options
		$current_user = wp_get_current_user();
		$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_options', $screen_options );
		
		// Redirect after we're complete
		$redirect_to = menu_page_url( $this->module->slug, false );
		wp_redirect( $redirect_to );
		exit;
	}
	
	/**
	 * Handle an AJAX request from the calendar to update a post's timestamp.
	 * Notes:
	 * - For Post Time, if the post is unpublished, the change sets the publication timestamp
	 * - If the post was published or scheduled for the future, the change will change the timestamp. 'publish' posts
	 * will become scheduled if moved past today and 'future' posts will be published if moved before today
	 * - Need to respect user permissions. Editors can move all, authors can move their own, and contributors can't move at all
	 *
	 * @since 0.7
	 */
	function handle_ajax_drag_and_drop() {
		global $wpdb;
		
		// Nonce check!
		if ( !wp_verify_nonce( $_POST['nonce'], 'ef-calendar-modify' ) )
			$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
		
		// Check that we got a proper post
		$post_id = (int)$_POST['post_id'];
		$post = get_post( $post_id );
		if ( !$post )
			$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			
		// Check that the user can modify the post
		if ( !$this->current_user_can_modify_post( $post ) )
			$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			
		// Check that it's not yet published
		$published_statuses = array(
			'publish',
			'future',
			'private',
		);
		if ( in_array( $post->post_status, $published_statuses ) )
			$this->print_ajax_response( 'error', sprintf( $this->module->messages['published-post-ajax'], get_edit_post_link( $post_id ) ) );
		
		// Check that the new date passed is a valid one
		$next_date_full = strtotime( $_POST['next_date'] );
		if ( !$next_date_full )
			$this->print_ajax_response( 'error', __( 'Something is wrong with the format for the new date.', 'edit-flow' ) );
		
		// Persist the old hourstamp because we can't manipulate the exact time on the calendar
		// Bump the last modified timestamps too
		$existing_time = date( 'H:i:s', strtotime( $post->post_date ) );
		$existing_time_gmt = date( 'H:i:s', strtotime( $post->post_date_gmt ) );
		$new_values = array(
			'post_date' => date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time,
			'post_modified' => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		
		// By default, changing a post on the calendar won't set the timestamp.
		// If the user desires that to be the behaviour, they can set the result of this filter to 'true'
		// With how WordPress works internally, setting 'post_date_gmt' will set the timestamp
		if ( apply_filters( 'ef_calendar_allow_ajax_to_set_timestamp', false ) )
			$new_values['post_date_gmt'] = date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time_gmt;
		
		// We have to do SQL unfortunately because of core bugginess
		// Note to those reading this: bug Nacin to allow us to finish the custom status API
		// See http://core.trac.wordpress.org/ticket/18362
		$response = $wpdb->update( $wpdb->posts, $new_values, array( 'ID' => $post->ID ) );
		if ( !$response )
			$this->print_ajax_response( 'error', $this->module->messages['update-error'] );
		
		$this->print_ajax_response( 'success', $this->module->messages['post-date-updated'] );
		exit;
	}
	
	/**
	 * Get a user's screen options
	 *
	 * @since 0.7
	 * @uses get_user_meta()
	 *
	 * @return array $screen_options The screen options values
	 */
	function get_screen_options() {
		
		$defaults = array(
			'num_weeks' => (int)$this->total_weeks,
		);
		$current_user = wp_get_current_user();
		$screen_options = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_options', true );
		$screen_options = array_merge( (array)$defaults, (array)$screen_options );
		
		return $screen_options;
	}
	
	/**
	 * Get the user's filters for calendar, either with $_GET or from saved
	 *
	 * @uses get_user_meta()
	 * @return array $filters All of the set or saved calendar filters
	 */
	function get_filters() {
		
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		
		$current_user = wp_get_current_user();
		$filters = array();
		$old_filters = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );

		$default_filters = array(
				'post_status' => '',
				'cpt' => $supported_post_types,
				'cat' => '',
				'author' => '',
				'start_date' => date( 'Y-m-d', current_time( 'timestamp' ) ),
			);
		$old_filters = array_merge( $default_filters, (array)$old_filters );
		
		// Post status
		if ( isset( $_GET['post_status'] ) ) {
			$filters['post_status'] = $_GET['post_status'];
			// Whitelist-based validation for this parameter
			$all_valid_statuses = array(
				'future',
				'unpublish',
				'publish'
			);
			foreach ( $this->get_post_statuses() as $post_status ) {
				$all_valid_statuses[] = $post_status->slug;
			}
			if ( !in_array( $filters['post_status'], $all_valid_statuses ) ) {
				$filters['post_status'] = '';
			}
		} else {
			$filters['post_status'] = $old_filters['post_status'];
		}
		
		// Post type
		$filters['cpt'] = sanitize_key( ( isset( $_GET['cpt'] ) ) ? $_GET['cpt'] : $old_filters['cpt'] );
		
		// Category
		 $filters['cat'] = (int)( isset( $_GET['cat'] ) ) ? $_GET['cat'] : $old_filters['cat'];
		
		// Author
		 $filters['author'] = (int)( isset( $_GET['author'] ) ) ? $_GET['author'] : $old_filters['author'];
		
		// Start date
		if ( isset( $_GET['start_date'] ) && !empty( $_GET['start_date'] ) )
			$filters['start_date'] = date( 'Y-m-d', strtotime( $_GET['start_date'] ) );
		else
			$filters['start_date'] = $old_filters['start_date'];

		// Set the start date as the beginning of the week, according to blog settings
		$filters['start_date'] = $this->get_beginning_of_week( $filters['start_date'] );
		
		$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );
		
		return $filters;
	}
	
	/**
	 * Build all of the HTML for the calendar view
	 */
	function view_calendar() {
		
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		
		// Get the user's screen options for displaying the data
		$screen_options = $this->get_screen_options();
		// Total number of weeks to display on the calendar. Run it through a filter in case we want to override the
		// user's standard
		$this->total_weeks = apply_filters( 'ef_calendar_total_weeks', $screen_options['num_weeks'] );
		
		$dotw = array(
			'Sat',
			'Sun',
		);
		$dotw = apply_filters( 'ef_calendar_weekend_days', $dotw );
		
		// Get filters either from $_GET or from user settings
		$filters = $this->get_filters();
		// For generating the WP Query objects later on
		$post_query_args = array(
			'post_status' => $filters['post_status'],
			'post_type'   => $filters['cpt'],
			'cat'         => $filters['cat'],
			'author'      => $filters['author']
		);
		$this->start_date = $filters['start_date'];
		
		// We use this later to label posts if they need labeling
		if ( count( $supported_post_types ) > 1 ) {
			$all_post_types = get_post_types( null, 'objects' );
		}
		$dates = array();
		$heading_date = $filters['start_date'];
		for ( $i=0; $i<7; $i++ ) {
			$dates[$i] = $heading_date;
			$heading_date = date( 'Y-m-d', strtotime( "+1 day", strtotime( $heading_date ) ) );
		}
		
		// we sort by post statuses....... eventually
		$post_statuses = $this->get_post_statuses();
		?>
		<div class="wrap">
			<div id="ef-calendar-title"><!-- Calendar Title -->
				<?php echo '<img src="' . esc_url( $this->module->img_url ) . '" class="module-icon icon32" />'; ?>
				<h2><?php _e( 'Calendar', 'edit-flow' ); ?>&nbsp;<span class="time-range"><?php $this->calendar_time_range(); ?></span></h2>
			</div><!-- /Calendar Title -->

			<?php
				// Handle posts that have been trashed or untrashed
				if ( isset( $_GET['trashed'] ) || isset( $_GET['untrashed'] ) ) {

					echo '<div id="trashed-message" class="updated"><p>';
					if ( isset( $_GET['trashed'] ) && (int) $_GET['trashed'] ) {
						printf( _n( 'Post moved to the trash.', '%d posts moved to the trash.', $_GET['trashed'] ), number_format_i18n( $_GET['trashed'] ) );
						$ids = isset($_GET['ids']) ? $_GET['ids'] : 0;
						$pid = explode( ',', $ids );
						$post_type = get_post_type( $pid[0] );
						echo ' <a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __( 'Undo', 'edit-flow' ) . '</a><br />';
						unset( $_GET['trashed'] );
					}
					if ( isset($_GET['untrashed'] ) && (int) $_GET['untrashed'] ) {
						printf( _n( 'Post restored from the Trash.', '%d posts restored from the Trash.', $_GET['untrashed'] ), number_format_i18n( $_GET['untrashed'] ) );
						unset( $_GET['undeleted'] );
					}
					echo '</p></div>';
				}
			?>

			<div id="ef-calendar-wrap"><!-- Calendar Wrapper -->
					
			<?php $this->print_top_navigation( $filters, $dates ); ?>

			<?php
				$table_classes = array();
				// CSS don't like our classes to start with numbers
				if ( $this->total_weeks == 1 )
					$table_classes[] = 'one-week-showing';
				elseif ( $this->total_weeks == 2 )
					$table_classes[] = 'two-weeks-showing';
				elseif ( $this->total_weeks == 3 )
					$table_classes[] = 'three-weeks-showing';
					
				$table_classes = apply_filters( 'ef_calendar_table_classes', $table_classes );
			?>
			<table id="ef-calendar-view" class="<?php echo esc_attr( implode( ' ', $table_classes ) ); ?>">
				<thead>
				<tr class="calendar-heading">
					<?php echo $this->get_time_period_header( $dates ); ?>
				</tr>
				</thead>
				<tbody>
				
				<?php
				$current_month = date( 'F', strtotime( $filters['start_date'] ) );
				for( $current_week = 1; $current_week <= $this->total_weeks; $current_week++ ):
					// We need to set the object variable for our posts_where filter
					$this->current_week = $current_week;
					$week_posts = $this->get_calendar_posts_for_week( $post_query_args );
					$date_format = 'Y-m-d';
					$week_single_date = $this->get_beginning_of_week( $filters['start_date'], $date_format, $current_week );
					$week_dates = array();
					$split_month = false;
					for ( $i = 0 ; $i < 7; $i++ ) {
						$week_dates[$i] = $week_single_date;
						$single_date_month = date( 'F', strtotime( $week_single_date ) );
						if ( $single_date_month != $current_month ) {
							$split_month = $single_date_month;
							$current_month = $single_date_month;
						}
						$week_single_date = date( 'Y-m-d', strtotime( "+1 day", strtotime( $week_single_date ) ) );
					}			
				?>
				<?php if ( $split_month ): ?>
				<tr class="month-marker">
					<?php foreach( $week_dates as $key => $week_single_date ) {
						if ( date( 'F', strtotime( $week_single_date ) ) != $split_month && date( 'F', strtotime( "+1 day", strtotime( $week_single_date ) ) ) == $split_month ) {
							$previous_month = date( 'F', strtotime( $week_single_date ) );
							echo '<td class="month-marker-previous">' . esc_html( $previous_month ) . '</td>';
						} else if ( date( 'F', strtotime( $week_single_date ) ) == $split_month && date( 'F', strtotime( "-1 day", strtotime( $week_single_date ) ) ) != $split_month ) {
							echo '<td class="month-marker-current">' . esc_html( $split_month ) . '</td>';
						} else {
							echo '<td class="month-marker-empty"></td>';
						}
					} ?>
				</tr>
				<?php endif; ?>

				<tr class="week-unit">
				<?php foreach( $week_dates as $day_num => $week_single_date ): ?>
				<?php
					// Somewhat ghetto way of sorting all of the day's posts by post status order
					if ( !empty( $week_posts[$week_single_date] ) ) {
						$week_posts_by_status = array();
						foreach( $post_statuses as $post_status ) {
							$week_posts_by_status[$post_status->slug] = array();
						}
						// These statuses aren't handled by custom statuses or post statuses
						$week_posts_by_status['private'] = array();
						$week_posts_by_status['publish'] = array();
						$week_posts_by_status['future'] = array();
						foreach( $week_posts[$week_single_date] as $num => $post ) {
							$week_posts_by_status[$post->post_status][$num] = $post;
						}
						unset( $week_posts[$week_single_date] );
						foreach( $week_posts_by_status as $status ) {
							foreach( $status as $num => $post ) {
								$week_posts[$week_single_date][] = $post; 
							}
						}
					}
				
					$td_classes = array(
						'day-unit',
					);
					$day_name = date( 'D', strtotime( $week_single_date ) );
					
					if ( in_array( $day_name, $dotw ) )
						$td_classes[] = 'weekend-day';
					
					if ( $week_single_date == date( 'Y-m-d', current_time( 'timestamp' ) ) )
						$td_classes[] = 'today';
						
					// Last day of the week
					if ( $day_num == 6 )
						$td_classes[] = 'last-day';
						
					$td_classes = apply_filters( 'ef_calendar_table_td_classes', $td_classes, $week_single_date );
				?>
				<td class="<?php echo esc_attr( implode( ' ', $td_classes ) ); ?>" id="<?php echo esc_attr( $week_single_date ); ?>">
					<?php if ( $week_single_date == date( 'Y-m-d', current_time( 'timestamp' ) ) ): ?>
						<div class="day-unit-today"><?php _e( 'Today', 'edit-flow' ); ?></div>
					<?php endif; ?>
					<div class="day-unit-label"><?php echo esc_html( date( 'j', strtotime( $week_single_date ) ) ); ?></div>
					<ul class="post-list">
						<?php
						$hidden = 0;
						if ( !empty( $week_posts[$week_single_date] ) ):
						$week_posts[$week_single_date] = apply_filters( 'ef_calendar_posts_for_week', $week_posts[$week_single_date] );
						foreach ( $week_posts[$week_single_date] as $num => $post ) :
							$post_id = $post->ID;
							$edit_post_link = get_edit_post_link( $post_id );
							
							$post_classes = array(
								'day-item',
								'custom-status-' . esc_attr( $post->post_status ),
							);
							// Only allow the user to drag the post if they have permissions to
							// or if it's in an approved post status
							// This is checked on the ajax request too.
							$published_statuses = array(
								'publish',
								'future',
								'private',
							);
							if ( $this->current_user_can_modify_post( $post ) && !in_array( $post->post_status, $published_statuses ) )
								$post_classes[] = 'sortable';
							
							if ( in_array( $post->post_status, $published_statuses ) )
								$post_classes[] = 'is-published';
							
							// Don't hide posts for just a couple of weeks
							if ( $num > 3 && $this->total_weeks > 2 ) {
								$post_classes[] = 'hidden';
								$hidden++;
							}
							$post_classes = apply_filters( 'ef_calendar_table_td_li_classes', $post_classes, $week_single_date, $post->ID );
						?>
						<li class="<?php echo esc_attr( implode( ' ', $post_classes ) ); ?>" id="post-<?php echo esc_attr( $post->ID ); ?>">
							<div class="item-default-visible">
							<div class="item-status"><span class="status-text"><?php echo esc_html( $this->get_post_status_friendly_name( get_post_status( $post_id ) ) ); ?></span></div>
							<div class="inner">
								<span class="item-headline post-title"><strong><?php echo esc_html( $post->post_title ); ?></strong></span>
							</div>
							<?php
								// All of the item information we're going to display
								$ef_calendar_item_information_fields = array();
								// Post author
								$ef_calendar_item_information_fields['author'] = array(
									'label' => __( 'Author', 'edit-flow' ),
									'value' => get_the_author_meta( 'display_name', $post->post_author ),
								);
								// If the calendar supports more than one post type, show the post type label
								if ( count( $this->get_post_types_for_module( $this->module ) ) > 1 ) {
									$ef_calendar_item_information_fields['post_type'] = array(
										'label' => __( 'Post Type', 'edit-flow' ),
										'value' => get_post_type_object( $post->post_type )->labels->singular_name,
									);
								}
								// Publication time for published statuses
								if ( in_array( $post->post_status, $published_statuses ) ) {
									if ( $post->post_status == 'future' ) {
										$ef_calendar_item_information_fields['post_date'] = array(
											'label' => __( 'Scheduled', 'edit-flow' ),
											'value' => get_the_time( null, $post->ID ),
										);
									} else {
										$ef_calendar_item_information_fields['post_date'] = array(
											'label' => __( 'Published', 'edit-flow' ),
											'value' => get_the_time( null, $post->ID ),
										);
									}
								}
								// Taxonomies and their values
								$args = array(
									'post_type' => $post->post_type,
								);
								$taxonomies = get_object_taxonomies( $args, 'object' );
								foreach( (array)$taxonomies as $taxonomy ) {
									// Sometimes taxonomies skip by, so let's make sure it has a label too
									if ( !$taxonomy->public || !$taxonomy->label )
										continue;
									$terms = wp_get_object_terms( $post->ID, $taxonomy->name );
									$key = 'tax_' . $taxonomy->name;
									if ( count( $terms ) ) {
										$value = '';
										foreach( (array)$terms as $term ) {
											$value .= $term->name . ', ';
										}
										$value = rtrim( $value, ', ' );
									} else {
										$value = '';
									}
									$ef_calendar_item_information_fields[$key] = array(
										'label' => $taxonomy->label,
										'value' => $value,
									);
								}
								
								$ef_calendar_item_information_fields = apply_filters( 'ef_calendar_item_information_fields', $ef_calendar_item_information_fields, $post->ID );
							?>
							</div>
							<div style="clear:right;"></div>
							<div class="item-inner">
							<table class="item-information">
								<?php foreach( $ef_calendar_item_information_fields as $field => $values ): ?>
									<?php
										// Allow filters to hide empty fields or to hide any given individual field. Hide empty fields by default.
										if ( ( apply_filters( 'ef_calendar_hide_empty_item_information_fields', true, $post->ID ) && empty( $values['value'] ) )
												|| apply_filters( "ef_calendar_hide_{$field}_item_information_field", false, $post->ID ) )
											continue;
									?>
									<tr class="item-field item-information-<?php echo esc_attr( $field ); ?>">
										<th class="label"><?php echo esc_html( $values['label'] ); ?>:</th>
										<?php if ( $values['value'] ): ?>
										<td class="value"><?php echo esc_html( $values['value'] ); ?></td>
										<?php else: ?>
										<td class="value"><em class="none"><?php echo _e( 'None', 'edit-flow' ); ?></em></td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
								<?php do_action( 'ef_calendar_item_additional_html', $post->ID ); ?>
							</table>
							<?php
								$post_type_object = get_post_type_object( $post->post_type );
								$item_actions = array();
								if ( $this->current_user_can_modify_post( $post ) ) {
									// Edit this post
									$item_actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '" title="' . esc_attr( __( 'Edit this item', 'edit-flow' ) ) . '">' . __( 'Edit', 'edit-flow' ) . '</a>';
									// Trash this post
									$item_actions['trash'] = '<a href="'. get_delete_post_link( $post->ID) . '" title="' . esc_attr( __( 'Trash this item' ), 'edit-flow' ) . '">' . __( 'Trash', 'edit-flow' ) . '</a>';
									// Preview/view this post
									if ( !in_array( $post->post_status, $published_statuses ) ) {
										$item_actions['view'] = '<a href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;', 'edit-flow' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview', 'edit-flow' ) . '</a>';
									} elseif ( 'trash' != $post->post_status ) {
										$item_actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'edit-flow' ), $post->post_title ) ) . '" rel="permalink">' . __( 'View', 'edit-flow' ) . '</a>';
									}
								}
								// Allow other plugins to add actions
								$item_actions = apply_filters( 'ef_calendar_item_actions', $item_actions, $post->ID );
								if ( count( $item_actions ) ) {
									echo '<div class="item-actions">';
									$html = '';
									foreach ( $item_actions as $class => $item_action ) {
										$html .= '<span class="' . esc_attr( $class ) . '">' . $item_action . '</span> | ';
									}
									echo rtrim( $html, '| ' );
									echo '</div>';
								}
							?>
							<div style="clear:right;"></div>
							</div>
						</li>
						<?php endforeach;
						endif; ?>
					</ul>
					<?php if ( $hidden ): ?>
						<a class="show-more" href="#"><?php printf( __( 'Show %1$s more ', 'edit-flow' ), $hidden ); ?></a>
					<?php endif; ?>
					</td>
					<?php endforeach; ?>
					</tr>
					
					<?php endfor; ?>
					
					</tbody>		
					</table><!-- /Week Wrapper -->
					<?php
					// Nonce field for AJAX actions
					wp_nonce_field( 'ef-calendar-modify', 'ef-calendar-modify' ); ?>
					
					<div class="clear"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	}
	
	/**
	 * Generates the filtering and navigation options for the top of the calendar
	 *
	 * @param array $filters Any set filters
	 * @param array $dates All of the days of the week. Used for generating navigation links
	 */
	function print_top_navigation( $filters, $dates ) {
		
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		$post_statuses = $this->get_post_statuses();
		?>
		<ul class="ef-calendar-navigation">
			<li id="calendar-filter">
				<form method="GET">
					<input type="hidden" name="page" value="calendar" />
					<input type="hidden" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>"/>
					<!-- Filter by status -->
					<select id="post_status" name="post_status">
						<option value=""><?php _e( 'View all statuses', 'edit-flow' ); ?></option>
						<?php
							foreach ( $post_statuses as $post_status ) {
								echo "<option value='" . esc_attr( $post_status->slug ) . "' " . selected( $post_status->slug, $filters['post_status'] ) . ">" . esc_html( $post_status->name ) . "</option>";
							}
							echo "<option value='future'" . selected( 'future', $filters['post_status'] ) . ">" . __( 'Scheduled', 'edit-flow' ) . "</option>";
							echo "<option value='unpublish'" . selected( 'unpublish', $filters['post_status'] ) . ">" . __( 'Unpublished', 'edit-flow' ) . "</option>";
							echo "<option value='publish'" . selected( 'publish', $filters['post_status'] ) . ">" . __( 'Published', 'edit-flow' ) . "</option>";
						?>
					</select>
					
					<?php
								
					// Filter by categories, borrowed from wp-admin/edit.php
					if ( taxonomy_exists('category') ) {
						$category_dropdown_args = array(
							'show_option_all' => __( 'View all categories', 'edit-flow' ),
							'hide_empty' => 0,
							'hierarchical' => 1,
							'show_count' => 0,
							'orderby' => 'name',
							'selected' => $filters['cat']
							);
						wp_dropdown_categories( $category_dropdown_args );
					}
					
					$users_dropdown_args = array(
						'show_option_all'   => __( 'View all users', 'edit-flow' ),
						'name'              => 'author',
						'selected'          => $filters['author'],
						'who'               => 'authors',
						);
					$users_dropdown_args = apply_filters( 'ef_calendar_users_dropdown_args', $users_dropdown_args );
					wp_dropdown_users( $users_dropdown_args );
			
					if ( count( $supported_post_types ) > 1 ) {
					?>
					<select id="type" name="cpt">
						<option value=""><?php _e( 'View all types', 'edit-flow' ); ?></option>
					<?php
						foreach ( $supported_post_types as $key => $post_type_name ) {
							$all_post_types = get_post_types( null, 'objects' );
							echo '<option value="' . esc_attr( $post_type_name ) . '"' . selected( $post_type_name, $filters['cpt'] ) . '>' . esc_html( $all_post_types[$post_type_name]->labels->name ) . '</option>';
						}
					?>
					</select>
					<?php
					}
					?>
					<input type="submit" id="post-query-submit" class="button-primary button" value="<?php _e( 'Filter', 'edit-flow' ); ?>"/>
				</form>
			</li>
			<!-- Clear filters functionality (all of the fields, but empty) -->
			<li>
				<form method="GET">
					<input type="hidden" name="page" value="calendar" />
					<input type="hidden" name="start_date" value="<?php echo esc_attr( $filters['start_date'] ); ?>"/>
					<input type="hidden" name="post_status" value="" />
					<input type="hidden" name="cpt" value="" />					
					<input type="hidden" name="cat" value="" />
					<input type="hidden" name="author" value="" />
					<input type="submit" id="post-query-clear" class="button-secondary button" value="<?php _e( 'Reset', 'edit-flow' ); ?>"/>
				</form>
			</li>

			<?php /** Previous and next navigation items (translatable so they can be increased if needed )**/ ?>
			<li class="date-change next-week">
				<a title="<?php printf( __( 'Forward 1 week', 'edit-flow' ) ); ?>" href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters, 1 ) ); ?>"><?php _e( '&rsaquo;', 'edit-flow' ); ?></a>
				<?php if ( $this->total_weeks > 1): ?>			
				<a title="<?php printf( __( 'Forward %d weeks', 'edit-flow' ), $this->total_weeks ); ?>" href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters ) ); ?>"><?php _e( '&raquo;', 'edit-flow' ); ?></a>
				<?php endif; ?>
			</li>
			<li class="date-change today">
				<a title="<?php printf( __( 'Today is %s', 'edit-flow' ), date( get_option( 'date_format' ), current_time( 'timestamp' ) ) ); ?>" href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters, 0 ) ); ?>"><?php _e( 'Today', 'edit-flow' ); ?></a>
			</li>
			<li class="date-change previous-week">
				<?php if ( $this->total_weeks > 1): ?>				
				<a title="<?php printf( __( 'Back %d weeks', 'edit-flow' ), $this->total_weeks ); ?>"  href="<?php echo esc_url( $this->get_pagination_link( 'previous', $filters ) ); ?>"><?php _e( '&laquo;', 'edit-flow' ); ?></a>
				<?php endif; ?>
				<a title="<?php printf( __( 'Back 1 week', 'edit-flow' ) ); ?>" href="<?php echo esc_url( $this->get_pagination_link( 'previous', $filters, 1 ) ); ?>"><?php _e( '&lsaquo;', 'edit-flow' ); ?></a>
			</li>
			<li class="ajax-actions">
				<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
			</li>			
		</ul>
	<?php
	}
	
	/**
	 * Generate the calendar header for a given range of dates
	 *
	 * @param array $dates Date range for the header
	 * @return string $html Generated HTML for the header
	 */
	function get_time_period_header( $dates ) {
		
		$html = '';
		foreach( $dates as $date ) {
			$html .= '<th class="column-heading" >';
			$html .= esc_html( date('l', strtotime( $date ) ) );
			$html .= '</th>';
		}
		
		return $html;
		
	}
		
	/**
	 * Query to get all of the calendar posts for a given day
	 *
	 * @param array $args Any filter arguments we want to pass
	 * @return array $posts All of the posts as an array sorted by date
	 */
	function get_calendar_posts_for_week( $args = array() ) {
		global $wpdb;
		
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		$defaults = array(
			'post_status'      => null,
			'cat'              => null,
			'author'           => null,
			'post_type'        => $supported_post_types,
			'posts_per_page'   => -1,
		);
						 
		$args = array_merge( $defaults, $args );
		
		// Unpublished as a status is just an array of everything but 'publish'
		if ( $args['post_status'] == 'unpublish' ) {
			$args['post_status'] = '';
			$post_statuses = $this->get_post_statuses();
			foreach ( $post_statuses as $post_status ) {
				$args['post_status'] .= $post_status->slug . ', ';
			}
			$args['post_status'] = rtrim( $args['post_status'], ', ' );
			// Optional filter to include scheduled content as unpublished
			if ( apply_filters( 'ef_show_scheduled_as_unpublished', false ) )
				$args['post_status'] .= ', future';
		}
		// The WP functions for printing the category and author assign a value of 0 to the default
		// options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
		// unset those arguments.
		if ( $args['cat'] === '0' ) unset( $args['cat'] );
		if ( $args['author'] === '0' ) unset( $args['author'] );

		if ( empty( $args['post_type'] ) || ! in_array( $args['post_type'], $supported_post_types ) )
			$args['post_type'] = $supported_post_types;
		
		// Filter for an end user to implement any of their own query args
		$args = apply_filters( 'ef_calendar_posts_query_args', $args );
		
		add_filter( 'posts_where', array( $this, 'posts_where_week_range' ) );
		$post_results = new WP_Query( $args );
		remove_filter( 'posts_where', array( $this, 'posts_where_week_range' ) );
		
		$posts = array();
		while ( $post_results->have_posts() ) {
			$post_results->the_post();
			global $post;
			$key_date = date( 'Y-m-d', strtotime( $post->post_date ) );
			$posts[$key_date][] = $post;
		}
		
		return $posts;
		
	}
	
	/**
	 * Filter the WP_Query so we can get a week range of posts
	 *
	 * @param string $where The original WHERE SQL query string
	 * @return string $where Our modified WHERE query string
	 */
	function posts_where_week_range( $where = '' ) {
		global $wpdb;
	
		$beginning_date = $this->get_beginning_of_week( $this->start_date, 'Y-m-d', $this->current_week );
		$ending_date = $this->get_ending_of_week( $this->start_date, 'Y-m-d', $this->current_week );
		// Adjust the ending date to account for the entire day of the last day of the week
		$ending_date = date( "Y-m-d", strtotime( "+1 day", strtotime( $ending_date ) ) );
		$where = $where . $wpdb->prepare( " AND ($wpdb->posts.post_date >= %s AND $wpdb->posts.post_date < %s)", $beginning_date, $ending_date );
	
		return $where;
	} 
	
	/**
	 * Gets the link for the next time period
	 *
	 * @param string $direction 'previous' or 'next', direction to go in time
	 * @param array $filters Any filters that need to be applied
	 * @param int $weeks_offset Number of weeks we're offsetting the range	
	 * @return string $url The URL for the next page
	 */
	function get_pagination_link( $direction = 'next', $filters = array(), $weeks_offset = null ) {

		$supported_post_types = $this->get_post_types_for_module( $this->module );
		
		if ( !isset( $weeks_offset ) )
			$weeks_offset = $this->total_weeks;
		else if ( $weeks_offset == 0 )
			$filters['start_date'] = $this->get_beginning_of_week( date( 'Y-m-d', current_time( 'timestamp' ) ) );
			
		if ( $direction == 'previous' )
			$weeks_offset = '-' . $weeks_offset;
		
		$filters['start_date'] = date( 'Y-m-d', strtotime( $weeks_offset . " weeks", strtotime( $filters['start_date'] ) ) );
		$url = add_query_arg( $filters, menu_page_url( $this->module->slug, false ) );

		if ( count( $supported_post_types ) > 1 )
			$url = add_query_arg( 'cpt', $filters['cpt'] , $url );
		
		return $url;
		
	}
	
	/**
	 * Given a day in string format, returns the day at the beginning of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 * @param int $week Number of weeks we're offsetting the range	
	 * @return string $formatted_start_of_week End of the week
	 */
	function get_beginning_of_week( $date, $format = 'Y-m-d', $week = 1 ) {
		
		$date = strtotime( $date );
		$start_of_week = get_option( 'start_of_week' );
		$day_of_week = date( 'w', $date );
		$date += (( $start_of_week - $day_of_week - 7 ) % 7) * 60 * 60 * 24 * $week;
		$additional = 3600 * 24 * 7 * ( $week - 1 );
		$formatted_start_of_week = date( $format, $date + $additional );
		return $formatted_start_of_week;
		
	}
	
	/**
	 * Given a day in string format, returns the day at the end of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats	
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 * @param int $week Number of weeks we're offsetting the range		
	 * @return string $formatted_end_of_week End of the week
	 */
	function get_ending_of_week( $date, $format = 'Y-m-d', $week = 1  ) {
		
		$date = strtotime( $date );
		$end_of_week = get_option( 'start_of_week' ) - 1;
		$day_of_week = date( 'w', $date );
		$date += (( $end_of_week - $day_of_week + 7 ) % 7) * 60 * 60 * 24;
		$additional = 3600 * 24 * 7 * ( $week - 1 );
		$formatted_end_of_week = date( $format, $date + $additional );
		return $formatted_end_of_week;
		
	}
	
	/**
	 * Human-readable time range for the calendar
	 * Shows something like "for October 30th through November 26th" for a four-week period
	 *
	 * @since 0.7
	 */
	function calendar_time_range() {
		
		$first_datetime = strtotime( $this->start_date );
		if ( date( 'Y', current_time( 'timestamp' ) ) != date( 'Y', $first_datetime ) )
			$first_date = date( 'F jS, Y', $first_datetime );
		else	
			$first_date = date( 'F jS', $first_datetime );
		$total_days = ( $this->total_weeks * 7 ) - 1;
		$last_datetime = strtotime( "+" . $total_days . " days", date( 'U', strtotime( $this->start_date ) ) );
		if ( date( 'Y', current_time( 'timestamp' ) ) != date( 'Y', $last_datetime ) )
			$last_date = date( 'F jS, Y', $last_datetime );
		else
			$last_date = date( 'F jS', $last_datetime );
		echo sprintf( __( 'for %1$s through %2$s'), $first_date, $last_date );
	}
	
	/**
	 * Check whether the current user should have the ability to modify the post
	 *
	 * @since 0.7
	 *
	 * @param object $post The post object we're checking
	 * @return bool $can Whether or not the current user can modify the post
	 */
	function current_user_can_modify_post( $post ) {
		
		if ( !$post )
			return false;
			
		$post_type_object = get_post_type_object( $post->post_type );
			
		$published_statuses = array(
			'publish',
			'future',
			'private',
		);
		// Editors and admins are fine
		if ( current_user_can( $post_type_object->cap->edit_others_posts, $post->ID ) )
			return true;
		// Authors and contributors can move their own stuff if it's not published
		if ( current_user_can( $post_type_object->cap->edit_post, $post->ID ) && wp_get_current_user()->ID == $post->post_author && !in_array( $post->post_status, $published_statuses ) )
			return true;
		// Those who can publish posts can move any of their own stuff
		if ( current_user_can( $post_type_object->cap->publish_posts, $post->ID ) && wp_get_current_user()->ID == $post->post_author )
			return true;
		
		return false;
	}
	
	/**
	 * Register settings for notifications so we can partially use the Settings API
	 * We use the Settings API for form generation, but not saving because we have our
	 * own way of handling the data.
	 * 
	 * @since 0.7
	 */
	function register_settings() {
		
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Post types to show', 'edit-flow' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'number_of_weeks', __( 'Number of weeks to show', 'edit-flow' ), array( $this, 'settings_number_weeks_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );

	}
	
	/**
	 * Choose the post types that should be displayed on the calendar
	 *
	 * @since 0.7
	 */
	function settings_post_types_option() {
		global $edit_flow;
		$edit_flow->settings->helper_option_custom_post_type( $this->module );
	}

	/**
	 * Give a bit of helper text to indicate the user can change
	 * number of weeks in the screen options
	 *
	 * @since 0.7
	 */
	function settings_number_weeks_option() {
		echo '<span class="description">' . __( 'The number of weeks shown on the calendar can be changed on a user-by-user basis using the calendar\'s screen options.', 'edit-flow' ) . '</span>';
	}
	
	/**
	 * Validate the data submitted by the user in calendar settings
	 *
	 * @since 0.7
	 */
	function settings_validate( $new_options ) {
		
		// Whitelist validation for the post type options
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		return $new_options;
	}
	
	/**
	 * Settings page for calendar
	 */
	function print_configure_view() {
		global $edit_flow;
		?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>	
			<?php				
				echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo esc_url( EDIT_FLOW_SETTINGS_PAGE ); ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>
		</form>
		<?php
	}
	
}
	
}
