<?php
/**
 * class EF_Calendar
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * @todo Somewhat prioritized
 * - Ensure all of the styles work cross-browser
 *
 * @author danielbachhuber
 */
if ( !class_exists('EF_Calendar') ) {

class EF_Calendar {
	
	const usermeta_key_prefix = 'ef_calendar_';
	
	var $module;
	
	var $start_date = '';
	var $current_week = 1;
	var $total_weeks = 4;	
	
	/**
	 * Construct the EF_Calendar class
	 */
	function __construct() {
		global $edit_flow;
	
		// Register the module with Edit Flow	
		$args = array(
			'title' => __( 'Calendar', 'edit-flow' ),
			'short_description' => __( 'See all of your content on a calendar tk', 'edit-flow' ),
			'extended_description' => __( 'This is a longer description that shows up on some views. We might want to include a link to documentation. tk', 'edit-flow' ),
			'img_url' => false,
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
				'update-error' => __( 'There was an error updating the post. Please try again', 'edit-flow' ),
			),
			'configure_page_cb' => 'print_configure_view',
			'configure_link_text' => __( 'Calendar Options', 'edit-flow' ),		
		);
		$this->module = $edit_flow->register_module( 'calendar', $args );		
		
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
		
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action( 'admin_menu', array( &$this, 'action_admin_menu' ) );		
		add_action( 'admin_print_styles', array( &$this, 'add_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_admin_scripts' ) );
		
		// Ajax manipulation for the calendar
		add_action( 'wp_ajax_ef_calendar_drag_and_drop', array( &$this, 'handle_ajax_drag_and_drop' ) );
	}
	
	/**
	 * Add the calendar link underneath the "Dashboard"
	 *
	 * @uses add_submenu_page
	 */
	function action_admin_menu() {
		add_submenu_page('index.php', __('Calendar', 'edit-flow'), __('Calendar', 'edit-flow'), apply_filters( 'ef_view_calendar_cap', 'ef_view_calendar' ), $this->module->slug, array( &$this, 'view_calendar' ) );
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
			wp_enqueue_style( 'edit-flow-calendar-css', EDIT_FLOW_URL.'css/calendar.css', false, EDIT_FLOW_VERSION );
	}
	
	/**
	 * Add any necessary JS to the WordPress admin
	 *
	 * @since 0.7
	 * @uses wp_enqueue_script()
	 */
	function enqueue_admin_scripts() {
		global $edit_flow;
		
		if ( $edit_flow->helpers->is_whitelisted_functional_view() ) {
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
			wp_enqueue_script( 'edit-flow-calendar-js', EDIT_FLOW_URL . 'js/calendar.js', $js_libraries, EDIT_FLOW_VERSION, true );
		}
		
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
		global $edit_flow, $wpdb;
		
		// Nonce check!
		if ( !wp_verify_nonce( $_POST['nonce'], 'ef-calendar-modify' ) )
			$edit_flow->helpers->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
		
		// Check that we got a proper post
		$post_id = (int)$_POST['post_id'];
		$post = get_post( $post_id );
		if ( !$post )
			$edit_flow->helpers->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			
		// Check that the user can modify the post
		if ( !$this->current_user_can_modify_post( $post ) )
			$edit_flow->helpers->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
		
		// Check that the new date passed is a valid one
		$next_date_full = strtotime( $_POST['next_date'] );
		if ( !$next_date_full )
			$edit_flow->helpers->print_ajax_response( 'error', __( 'Something is wrong with the format for the new date.', 'edit-flow' ) );
		
		// Persist the old hourstamp because we can't manipulate the exact time on the calendar
		// Bump the last modified timestamps too
		$existing_time = date( 'H:i:s', strtotime( $post->post_date ) );
		$existing_time_gmt = date( 'H:i:s', strtotime( $post->post_date_gmt ) );
		$new_values = array(
			'post_date' => date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time,
			// By setting the post_date_gmt, we're explictly setting the publication timestamp
			// We may decide we don't want to do this, or add a filter to allow the user to opt out
			// By opting out, regardless of the day the post is set to, it would always be published today
			'post_date_gmt' => date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time_gmt,
			'post_modified' => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);
		// Handle the post status if the post was published and we're moving it to the future
		// Or if the post was scheduled and we're moving it to the past
		if ( 'publish' == $post->post_status ) {
			$now = gmdate('Y-m-d H:i:59');
			if ( mysql2date('U', $new_values['post_date_gmt'], false) > mysql2date('U', $now, false) )
				$new_values['post_status'] = 'future';
		} else if ( 'future' == $post->post_status ) {
			$now = gmdate('Y-m-d H:i:59');
			if ( mysql2date('U', $new_values['post_date_gmt'], false) <= mysql2date('U', $now, false) )
				$new_values['post_status'] = 'publish';
		}
		// We have to do SQL unfortunately because of core bugginess
		// Note to those reading this: bug Nacin to allow us to finish the custom status API
		// See http://core.trac.wordpress.org/ticket/18362
		$response = $wpdb->update( $wpdb->posts, $new_values, array( 'ID' => $post->ID ) );
		if ( !$response )
			$edit_flow->helpers->print_ajax_response( 'error', $this->module->messages['update-error'] );
		
		$edit_flow->helpers->print_ajax_response( 'success', $this->module->messages['post-date-updated'] );
		exit;
	}
	
	/**
	 * Get the user's filters for calendar, either with $_GET or from saved
	 *
	 * @uses get_user_meta()
	 * @return array $filters All of the set or saved calendar filters
	 */
	function get_filters() {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		
		$current_user = wp_get_current_user();
		$filters = array();
		$old_filters = get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );
	
		// Set the proper keys to empty so we don't thr
		if ( empty( $old_filters ) ) {
			$old_filters['post_status'] = '';
			$old_filters['post_type'] = '';		
			$old_filters['cat'] = '';
			$old_filters['author'] = '';
			$old_filters['start_date'] = '';			
		}
		
		// Post status
		if ( isset( $_GET['post_status'] ) ) {
			$filters['post_status'] = $_GET['post_status'];
			// Whitelist-based validation for this parameter
			$all_valid_statuses = array(
				'future',
				'unpublish',
				'publish'
			);
			foreach ( $edit_flow->helpers->get_post_statuses() as $post_status ) {
				$all_valid_statuses[] = $post_status->slug;
			}
			if ( !in_array( $filters['post_status'], $all_valid_statuses ) ) {
				$filters['post_status'] = '';
			}
		} else {
			$filters['post_status'] = $old_filters['post_status'];
		}
		
		// Post type
		$filters['post_type'] = sanitize_key( ( count( $supported_post_types ) > 1 && isset( $_GET['type'] ) ) ? $_GET['type'] : $old_filters['post_type'] );
		
		// Category
		 $filters['cat'] = (int)( isset( $_GET['cat'] ) ) ? $_GET['cat'] : $old_filters['cat'];
		
		// Author
		 $filters['author'] = (int)( isset( $_GET['author'] ) ) ? $_GET['author'] : $old_filters['author'];
		
		// Start date
		if ( isset( $_GET['start_date'] ) && !empty( $_GET['start_date'] ) )
			$filters['start_date'] = date( 'Y-m-d', strtotime( $_GET['start_date'] ) );
		else
			$filters['start_date'] = date( 'Y-m-d' );

		$filters['start_date'] = $this->get_beginning_of_week( $filters['start_date'] ); // don't just set the given date as the end of the week. use the blog's settings
		
		update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );
		
		return $filters;
	}
	
	/**
	 * Build all of the HTML for the calendar view
	 */
	function view_calendar() {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		
		// Total number of weeks to display on the calendar
		$this->total_weeks = apply_filters( 'ef_calendar_total_weeks', $this->total_weeks );
		
		$dotw = array(
			'Sat',
			'Sun',
		);
		$dotw = apply_filters( 'ef_calendar_weekend_days', $dotw );

		date_default_timezone_set('UTC');
		
		// Get filters either from $_GET or from user settings
		$filters = $this->get_filters();
		// For generating the WP Query objects later on
		$args = array(	'post_status' => $filters['post_status'],
						'post_type' => $filters['post_type'],
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
		
		?>
		<div class="wrap">
			<div id="ef-calendar-title"><!-- Calendar Title -->
				<div class="icon32" id="icon-edit"></div>
				<h2><?php _e( 'Calendar', 'edit-flow' ); ?>&nbsp;<span class="time-range"><?php $this->calendar_time_range(); ?></span></h2>
			</div><!-- /Calendar Title -->

			<div id="ef-calendar-wrap"><!-- Calendar Wrapper -->
					
			<?php $this->print_top_navigation( $filters, $dates ); ?>

			<table id="ef-calendar-view">				
				<thead>
				<tr class="calendar-heading">
					<?php echo $this->get_time_period_header( $dates ); ?>
				</tr>
				</thead>
				<tbody>
				
				<?php
				$current_month = date( 'F', strtotime( $filters['start_date'] ) );
				for( $current_week = 1; $current_week <= $this->total_weeks; $current_week++ ):
					// We need to set the global variable for our posts_where filter
					$this->current_week = $current_week;
					$week_posts = $this->get_calendar_posts_for_week( $args );
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
				<?php foreach( $week_dates as $week_single_date ): ?>
				<?php
					$td_classes = array(
						'day-unit',
					);
					$day_name = date( 'D', strtotime( $week_single_date ) );
					
					if ( in_array( $day_name, $dotw ) )
						$td_classes[] = 'weekend-day';
						
					if ( $week_single_date == date( 'Y-m-d' ) )
						$td_classes[] = 'today';
				?>
				<td class="<?php echo implode( ' ', $td_classes ); ?>" id="<?php echo esc_attr( $week_single_date ); ?>">
					<div class="day-unit-label"><?php echo date( 'j', strtotime( $week_single_date ) ); ?></div>
					<ul class="post-list">
						<?php
						$hidden = 0;
						if ( !empty( $week_posts[$week_single_date] ) ): 
						foreach ( $week_posts[$week_single_date] as $num => $post ) :
							$post_id = $post->ID;
							$edit_post_link = get_edit_post_link( $post_id );
							
							$post_classes = array(
								'day-item',
								'custom-status-' . esc_attr( $post->post_status ),
							);
							// Only allow the user to drag the post if they have permissions to
							// This is checked on the ajax request too.
							if ( $this->current_user_can_modify_post( $post ) )
								$post_classes[] = 'sortable';
							
							if ( $num > 3 ) {
								$post_classes[] = 'hidden';
								$hidden++;
							}
						?>
						<li class="<?php echo implode( ' ', $post_classes ); ?>" id="post-<?php esc_attr_e( $post->ID ); ?>">
							<div class="item-status"><span class="status-text"><?php echo $edit_flow->helpers->get_post_status_friendly_name( get_post_status( $post_id ) ); ?></span></div>
							<div class="inner">
								<span class="item-headline post-title"><strong>
								<?php if ( $this->current_user_can_modify_post( $post ) ): ?>
									<a href="<?php echo esc_url( $edit_post_link ); ?>"><?php echo esc_html( $post->post_title );?></a>
								<?php else: ?>
									<?php echo esc_html( $post->post_title ); ?>
								<?php endif; ?>
								</strong></span>
							</div>
							<div style="clear:right;"></div>
						</li>
						<?php endforeach;
						endif; ?>
					</ul>
					<?php if ( $hidden ): ?>
						<a class="show-more" href="#"><?php printf( __( 'Show %1$s more ' ), $hidden ); ?></a>
					<?php endif; ?>
					</td>
					<?php endforeach; ?>
					</tr>
					
					<?php endfor; ?>
					
					</tbody>		
					</table><!-- /Week Wrapper -->
					<?php wp_nonce_field( 'ef-calendar-modify', 'ef-calendar-modify' ); ?>
					
					<div class="clear"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	} // END: view_calendar()
	
	/**
	 * Generates the filtering and navigation options for the top of the calendar
	 *
	 * @param array $filters Any set filters
	 * @param array $dates All of the days of the week. Used for generating navigation links
	 */
	function print_top_navigation( $filters, $dates ) {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		$post_statuses = $edit_flow->helpers->get_post_statuses();
		?>
		<ul class="ef-calendar-navigation">
			<li id="calendar-filter">
				<form method="GET">
					<input type="hidden" name="page" value="calendar" />
					<input type="hidden" name="start_date" value="<?php esc_attr_e( $filters['start_date'] ); ?>"/>
					<!-- Filter by status -->
					<select id="post_status" name="post_status">
						<option value=""><?php _e( 'View all statuses', 'edit-flow' ); ?></option>
						<?php
							foreach ( $post_statuses as $post_status ) {
								echo "<option value='$post_status->slug' " . selected( $post_status->slug, $filters['post_status'] ) . ">" . esc_html( $post_status->name ) . "</option>";
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
					
					$user_dropdown_args = array(
						'show_option_all' => __( 'View all users', 'edit-flow' ),
						'name'     => 'author',
						'selected' => $filters['author']
						);
					wp_dropdown_users( $user_dropdown_args );
			
					if ( count( $supported_post_types ) > 1 ) {
					?>
					<select id="type" name="type">
						<option value=""><?php _e( 'View all types', 'edit-flow' ); ?></option>
					<?php
						foreach ( $supported_post_types as $key => $post_type_name ) {
							$all_post_types = get_post_types( null, 'objects' );
							echo '<option value="' . esc_attr( $post_type_name ) . '"' . selected( $post_type_name, $filters['post_type']) . '>' . esc_html( $all_post_types[$post_type_name]->labels->name ) . '</option>';
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
					<input type="hidden" name="start_date" value="<?php esc_attr_e( $filters['start_date'] ); ?>"/>
					<input type="hidden" name="post_status" value="" />
					<input type="hidden" name="type" value="" />					
					<input type="hidden" name="cat" value="" />
					<input type="hidden" name="author" value="" />
					<input type="submit" id="post-query-clear" class="button-secondary button" value="<?php _e( 'Reset', 'edit-flow' ); ?>"/>
				</form>
			</li>

			<?php /** Previous and next navigation items (translatable so they can be increased if needed )**/ ?>
			<li class="next-week">
				<a id="trigger-left" href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters, 1 ) ); ?>"><?php _e( '&rsaquo;', 'edit-flow' ); ?></a>
				<?php if ( $this->total_weeks > 1): ?>			
				<a id="trigger-left" href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters ) ); ?>"><?php _e( '&raquo;', 'edit-flow' ); ?></a>
				<?php endif; ?>
			</li>
			<li class="today">
				<a href="<?php echo esc_url( $this->get_pagination_link( 'next', $filters, 0 ) ); ?>"><?php _e( 'Today', 'edit-flow' ); ?></a>
			</li>
			<li class="previous-week">
				<?php if ( $this->total_weeks > 1): ?>				
				<a id="trigger-right" href="<?php echo esc_url( $this->get_pagination_link( 'previous', $filters ) ); ?>"><?php _e( '&laquo;', 'edit-flow' ); ?></a>
				<?php endif; ?>
				<a id="trigger-right" href="<?php echo esc_url( $this->get_pagination_link( 'previous', $filters, 1 ) ); ?>"><?php _e( '&lsaquo;', 'edit-flow' ); ?></a>
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
	function get_calendar_posts_for_week( $args = null ) {
		global $wpdb, $edit_flow;
		
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		$defaults = array(	'post_status' => null,
							'cat'         => null,
						  	'author'      => null,
							'post_type' => $supported_post_types,
							'posts_per_page' => -1,
						  );
						 
		$args = array_merge( $defaults, $args );
		
		// Unpublished as a status is just an array of everything but 'publish'
		if ( $args['post_status'] == 'unpublish' ) {
			$post_statuses = $edit_flow->helpers->get_post_statuses();
			foreach ( $post_statuses as $post_status ) {
				$args['post_status'] .= $post_status->slug . ', ';
			}
			if ( apply_filters( 'ef_show_scheduled_as_unpublished', false ) )
				$args['post_status'] .= 'future';
		} // END if ( $args['post_status'] == 'unpublish' )
		
		// The WP functions for printing the category and author assign a value of 0 to the default
		// options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
		// unset those arguments. We could alternatively amend the first option from these
		// dropdowns with a regex on the wp_dropdown_cats and wp_dropdown_users filters.
		if ( $args['cat'] === '0' ) unset( $args['cat'] );
		if ( $args['author'] === '0' ) unset( $args['author'] );
		
		if ( count( $supported_post_types ) > 1 && !$args['post_type'] ) {
			$args['post_type'] = $supported_post_types;
		}
		
		// Filter for an end user to implement
		$args = apply_filters( 'ef_calendar_posts_query_args', $args );
		
		add_filter( 'posts_where', array( &$this, 'posts_where_week_range' ) );
		$post_results = new WP_Query( $args );
		remove_filter( 'posts_where', array( &$this, 'posts_where_week_range' ) );
		
		$posts = array();
		while ( $post_results->have_posts() ) {
			$post_results->the_post();
			global $post;
			$key_date = date( 'Y-m-d', strtotime( $post->post_date ) );
			// @todo Sort within a day by whatever field we're sorting by
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
		global $edit_flow;
	
		$beginning_date = $this->get_beginning_of_week( $this->start_date, 'Y-m-d', $this->current_week );
		$ending_date = $this->get_ending_of_week( $this->start_date, 'Y-m-d', $this->current_week );
		$where .= " AND post_date >= '$beginning_date' AND post_date <= '$ending_date'";
	
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
		global $edit_flow;
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $this->module );
		
		if ( !isset( $weeks_offset ) )
			$weeks_offset = $this->total_weeks;
		else if ( $weeks_offset == 0 )
			$filters['start_date'] = $this->get_beginning_of_week( date( 'Y-m-d' ) );
			
		if ( $direction == 'previous' )
			$weeks_offset = '-' . $weeks_offset;
		
		$filters['start_date'] = date( 'Y-m-d', strtotime( $weeks_offset . " weeks", strtotime( $filters['start_date'] ) ) );
		$url = add_query_arg( $filters, EDIT_FLOW_CALENDAR_PAGE );

		if ( count( $supported_post_types ) > 1 )
			$url = add_query_arg( 'post_type', $filters['post_type'] , $url );
		
		return $url;
		
	}
	
	/**
	 * Given a day in string format, returns the day at the beginning of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 * @param int $week Number of weeks we're offsetting the range	
	 * @return string $formatted_start_of_week End of the week
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
	 */
	function get_beginning_of_week( $date, $format = 'Y-m-d', $week = 1 ) {
		
		$date = strtotime( $date );
		$start_of_week = get_option( 'start_of_week' );
		$day_of_week = date( 'w', $date );
		$date += (( $start_of_week - $day_of_week - 7 ) % 7) * 60 * 60 * 24 * $week;
		$additional = 3600 * 24 * 7 * ( $week - 1 );
		$formatted_start_of_week = date( $format, $date + $additional );
		return $formatted_start_of_week;
		
	} // END: get_beginning_of_week()
	
	/**
	 * Given a day in string format, returns the day at the end of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 * @param int $week Number of weeks we're offsetting the range		
	 * @return string $formatted_end_of_week End of the week
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
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
		
		$first_date = date( 'F jS', strtotime( $this->start_date ) );
		$total_days = ( $this->total_weeks * 7 ) - 1;
		$last_date = date( 'F jS', strtotime( "+" . $total_days . " days", date( 'U', strtotime( $this->start_date ) ) ) );
		echo sprintf( __( 'for %1$s through %2$s'), $first_date, $last_date );
	}
	
	/**
	 * Check whether the current user should have the ability to modify the post
	 * @todo Make this work for custom post types too
	 *
	 * @since 0.7
	 *
	 * @param object $post The post object we're checking
	 * @return bool $can Whether or not the current user can modify the post
	 */
	function current_user_can_modify_post( $post ) {
		
		if ( !$post )
			return false;
			
		$published_statuses = array(
			'publish',
			'future',
			'private',
		);
		// Editors and admins are fine
		if ( current_user_can( 'edit_others_posts' ) )
			return true;
		// Authors and contributors can move their own stuff if it's not published
		if ( current_user_can( 'edit_posts') && wp_get_current_user()->ID == $post->post_author && !in_array( $post->post_status, $published_statuses ) )
			return true;
		// Those who can publish posts can move any of their own stuff
		if ( current_user_can( 'publish_posts') && wp_get_current_user()->ID == $post->post_author )
			return true;
		
		return false;
	}
	
	/**
	 * Register settings for notifications so we can partially use the Settings API
	 * (We use the Settings API for form generation, but not saving)
	 * 
	 * @since 0.7
	 */
	function register_settings() {
		
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', 'Post types to show', array( &$this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );

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
	
	function settings_validate( $new_options ) {
		global $edit_flow;
		
		// Whitelist validation for the post type options
		if ( !isset( $new_options['post_types'] ) )
			$new_options['post_types'] = array();
		$new_options['post_types'] = $edit_flow->helpers->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );
		
		return $new_options;
	}
	
	/**
	 * Settings page for calendar
	 */
	function print_configure_view() {
		global $edit_flow;
		?>
		<form class="basic-settings" action="<?php echo esc_url( add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ) ); ?>" method="post">
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
	
} // END: if ( !class_exists('EF_Calendar') )
