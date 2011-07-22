<?php
/**
 * This class displays an editorial calendar for viewing upcoming and past content at a glance
 *
 * Somewhat prioritized @todos:
 * @todo Ensure all of the styles work cross-browser
 *
 * @author danielbachhuber
 */
if ( !class_exists('EF_Calendar') ) {

class EF_Calendar {
	
	const usermeta_key_prefix = 'ef_calendar_';
	
	/**
	 * __construct()
	 * Construct the EF_Calendar class
	 */
	function __construct() {
		
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array(&$this, 'add_admin_scripts' ));
		add_action( 'admin_print_styles', array(&$this, 'add_admin_styles' ));
		
	} // END: __construct()
	
	/**
	 * init()
	 */
	function init() {
		
		global $edit_flow;
		// Calendar supports the 'post' post type by default
		// Other support can be added with the add_post_type_support method
		add_post_type_support( 'post', 'ef_calendar' );
		
	} // END: init()
	
	/**
	 * add_admin_scripts()
	 * Add any necessary Javascript to the WordPress admin
	 */
	function add_admin_scripts() {
		
		//wp_enqueue_script('edit_flow-calendar-js', EDIT_FLOW_URL.'js/calendar.js', array('jquery', 'jquery-ui-core', 'jquery-ui-sortable'), EDIT_FLOW_VERSION, true);
		
	} // END: add_admin_scripts()
	
	/**
	 * add_admin_styles()
	 * Add any necessary CSS to the WordPress admin
	 */
	function add_admin_styles() {
		
		global $pagenow;
		// Only load calendar styles on the calendar page
		if ( $pagenow == 'index.php' && isset( $_GET['page'] ) && $_GET['page'] == 'edit-flow/calendar' ) {
			wp_enqueue_style( 'edit_flow-calendar-css', EDIT_FLOW_URL.'css/calendar.css', false, EDIT_FLOW_VERSION );
		}
		
	} // END: add_admin_styles()
	
	/**
	 * get_filters()
	 * Get the user's filters for calendar, either with $_GET or from saved
	 *
	 * @uses get_user_meta()
	 * @return array $filters All of the set or saved calendar filters
	 */
	function get_filters() {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );
		
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
			// Check to ensure we've been passed a valid post status (either a built-in status or a custom status)
			$all_valid_statuses = array(
				'future',
				'unpublish',
				'publish'
			);
			foreach ( $edit_flow->custom_status->get_custom_statuses() as $custom_status ) {
				$all_valid_statuses[] = $custom_status->slug;
			}
			if ( !in_array( $filters['post_status'], $all_valid_statuses ) ) {
				$filters['post_status'] = '';
			}
		} else {
			$filters['post_status'] = $old_filters['post_status'];
		}
		
		// Post type
		if ( count( $supported_post_types ) > 1 && isset( $_GET['type'] ) ) {
			$filters['post_type'] = $_GET['type'];
		} else {
			$filters['post_type'] = $old_filters['post_type'];
		}
		
		// Category
		if ( isset( $_GET['cat'] ) ) {
			$filters['cat'] = $_GET['cat'];
		} else {
			$filters['cat'] = $old_filters['cat'];
		}
		
		// Author
		if ( isset( $_GET['author'] ) ) {
			$filters['author'] = $_GET['author'];
		} else {
			$filters['author'] = $old_filters['author'];
		}
		
		// Start date
		if ( isset( $_GET['start_date'] ) && !empty( $_GET['start_date'] ) ) {
			$time = strtotime( $_GET['start_date'] );
			$filters['start_date'] = date('Y-m-d', $time);
		} else {
			$filters['start_date'] = date('Y-m-d');
		}

		$filters['start_date'] = $this->get_end_of_week( $filters['start_date'] ); // don't just set the given date as the end of the week. use the blog's settings
		
		update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );
		
		return $filters;
	} // END: get_filters()
	
	/**
	 * view_calendar()
	 * Build the calendar view
	 */
	function view_calendar() {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );

		date_default_timezone_set('UTC');
		
		// Get filters either from $_GET or from user settings
		$filters = $this->get_filters();
		$args = array(	'post_status' => $filters['post_status'],
						'post_type' => $filters['post_type'],
						'cat'         => $filters['cat'],
						'author'      => $filters['author']
					  );

		$date = $filters['start_date'];
		// All of the days of the week
		$dates = array();
		for ($i=0; $i<7; $i++) {
			$dates[$i] = $date;
			$date = date('Y-m-d', strtotime("-1 day", strtotime($date)));
		}
		
		// We use this later to label posts if they need labeling
		if ( count( $supported_post_types ) > 1 ) {
			$all_post_types = get_post_types( null, 'objects' );
		}
		
		?>
		<div class="wrap">
			<div id="ef-calendar-title"><!-- Calendar Title -->
				<div class="icon32" id="icon-edit"></div>
				<h2><?php _e( 'Calendar', 'edit-flow' ); ?></h2>
			</div><!-- /Calendar Title -->

			<div id="ef-calendar-wrap"><!-- Calendar Wrapper -->
					
			<?php $this->print_top_navigation( $filters, $dates ); ?>

			<div id="week-wrap"><!-- Week Wrapper -->
						<div class="week-heading"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
							<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

						<?php
						foreach (array_reverse($dates) as $key => $date) :
							
							$posts = $this->get_calendar_posts( $date, $args );
							
							$date_format = 'Y-m-d';
							$today_css = '';
							// If we're currently outputting posts for today, give it some special CSS treatment
							if ( date( $date_format, strtotime( $date ) ) == date( $date_format ) ) {
								$today_css = ' today';
							}
						?>
						<div class="week-unit<?php if ( $key == 0 ) echo ' left-column'; echo $today_css; ?>"><!-- Week Unit 1 -->
							<ul id="<?php echo date( $date_format, strtotime($date)); ?>" class="week-list connectedSortable">
								<?php
								// We're using The Loop!
								if ( $posts->have_posts() ) : 
								while ( $posts->have_posts()) : $posts->the_post();
									$post_id = get_the_id();
									$edit_post_link = get_edit_post_link( $post_id );
								?>
								<li class="week-item <?php echo 'custom-status-'.get_post_status( $post_id ); ?>" id="post-<?php the_id(); ?>">
								  <div class="item-handle">
									<div class="item-headline post-title">
										<?php if ( $edit_post_link ): ?>
										<strong><?php edit_post_link( get_the_title(), '', '', $post_id ); ?></strong>
										<?php else: ?>
										<strong><?php the_title(); ?></strong>
										<?php endif; ?>
										<span class="item-status">[<?php if ( count( $supported_post_types ) > 1 ) {
											$post_type = get_post_type( $post_id );
											echo $all_post_types[$post_type]->labels->singular_name . ': ';
										} ?><?php echo $edit_flow->custom_status->get_custom_status_friendly_name( get_post_status( $post_id ) ); ?>]</span>
									</div>
									<ul class="item-metadata">
										<li class="item-author"><?php echo sprintf( __( 'By %s', 'edit-flow' ), get_the_author() ); ?></li>
										<li class="item-time"><?php the_time( get_option('time_format') ); ?>
										<li class="item-category">
											<?php
												// Listing of all the categories
												$categories_html = '';
												$categories = get_the_category( $post_id );
												foreach ( $categories as $category ) {
													$categories_html .= $category->name . ', ';
												}
												echo rtrim( $categories_html, ', ' );
											?>
										</li>
									</ul>
									</div>
									<div class="item-actions">
										<?php if ( $edit_post_link ): ?>
									  <span class="edit">
										<?php edit_post_link( __( 'Edit', 'edit-flow' ), '', '', $post_id ); ?>
									  </span> | 
										<?php endif; ?>
									  <span class="view">
										<a href="<?php echo the_permalink(); ?>"><?php _e( 'View', 'edit-flow' ); ?></a>
									  </span>
									</div>
									<div style="clear:left;"></div>
								</li>
								<?php
								endwhile; endif; // END if ( $posts->have_posts() )
								?>
							</ul>
						</div><!-- /Week Unit 1 -->
						<?php
						endforeach;
						?>

						<div class="clear"></div>
						<div class="week-footing">
						<?php echo $this->get_time_period_header( $dates ); ?>
						</div>

					</div><!-- /Week Wrapper -->
					<ul class="day-navigation">
					  <li class="next-week">
							<a href="<?php echo $this->get_next_link( $dates[0], $filters ); ?>"><?php _e( 'Next &raquo;', 'edit-flow' ); ?></a>
						</li>
						<li class="previous-week">
							<a href="<?php echo $this->get_previous_link( $dates[count($dates)-1], $filters ); ?>"><?php _e( '&laquo; Previous', 'edit-flow' ); ?></a>
						</li>
					</ul>
					<div class="clear"></div>
				</div><!-- /Calendar Wrapper -->

			  </div>

		<?php 
		
	} // END: view_calendar()
	
	/**
	 * print_top_navigation()
	 * Generates the filtering and navigation options for the top of the calendar
	 *
	 * @param array $filters Any set filters
	 * @param array $dates All of the days of the week. Used for generating navigation links
	 */
	function print_top_navigation( $filters, $dates ) {
		global $edit_flow;
		
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );
		$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
		?>
		<ul class="day-navigation">
			<li id="calendar-filter">
				<form method="GET">
					<input type="hidden" name="page" value="edit-flow/calendar" />
					<input type="hidden" name="start_date" value="<?php echo $filters['start_date'] ?>"/>
					<!-- Filter by status -->
					<select id="post_status" name="post_status">
						<option value=""><?php _e( 'View all statuses', 'edit-flow' ); ?></option>
						<?php
							foreach ( $custom_statuses as $custom_status ) {
								echo "<option value='$custom_status->slug' " . selected( $custom_status->slug, $filters['post_status'] ) . ">$custom_status->name</option>";
							}
							echo "<option value='future'" . selected( 'future', $filters['post_status'] ) . ">" . __( 'Scheduled', 'edit-flow' ) . "</option>";
							echo "<option value='unpublish'" . selected( 'unpublish', $filters['post_status'] ) . ">" . __( 'Unpublished', 'edit-flow' ) . "</option>";
							echo "<option value='publish'" . selected( 'publish', $filters['post_status'] ) . ">" . __( 'Published', 'edit-flow' ) . "</option>";
						?>
					</select>
					
					<?php
								
					// Filter by categories, borrowed from wp-admin/edit.php
					if ( ef_taxonomy_exists('category') ) {
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
							echo '<option value="' . $post_type_name . '"' . selected( $post_type_name, $filters['post_type']) . '>' . $all_post_types[$post_type_name]->labels->name . '</option>';
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
					<input type="hidden" name="page" value="edit-flow/calendar" />
					<input type="hidden" name="start_date" value="<?php echo $filters['start_date']; ?>"/>
					<input type="hidden" name="post_status" value="" />
					<input type="hidden" name="type" value="" />					
					<input type="hidden" name="cat" value="" />
					<input type="hidden" name="author" value="" />
					<input type="submit" id="post-query-clear" class="button-secondary button" value="<?php _e( 'Reset', 'edit-flow' ); ?>"/>
				</form>
			</li>
	  
			<!-- Previous and next navigation items -->
			<li class="next-week">
				<a id="trigger-left" href="<?php echo $this->get_next_link( $dates[0], $filters ); ?>"><?php _e( 'Next &raquo;', 'edit-flow' ); ?></a>
			</li>
			<li class="previous-week">
				<a id="trigger-right" href="<?php echo $this->get_previous_link( $dates[count($dates)-1], $filters ); ?>"><?php _e( '&laquo; Previous', 'edit-flow' ); ?></a>
			</li>
		</ul>
	<?php
	} // END: print_top_navigation()
	
	/**
	 * get_time_period_header()
	 * Generate the calendar header for a given range of dates
	 *
	 * @param array $dates Date range for the header
	 * @return string $html Generated HTML for the header
	 */
	function get_time_period_header( $dates ) {
		
		$html = '';
		// Day 1
		$html .= '<div class="day-heading first-heading" style="width: 13.8%; height: 100%; position: absolute; left: 0%; top: 0%; ">';
		$html .= date('l', strtotime($dates[6])) . ', ' . date('M d', strtotime($dates[6]));
		$html .= '</div>';
		// Day 2
		$html .= '<div class="day-heading" style="left: 15.6%; top: 0%; ">';
		$html .= date('l', strtotime($dates[5])) . ', ' . date('M d', strtotime($dates[5]));
		$html .= '</div>';
		// Day 3
		$html .= '<div class="day-heading" style="left: 30%; top: 0%; ">';
		$html .= date('l', strtotime($dates[4])) . ', ' . date('M d', strtotime($dates[4]));
		$html .= '</div>';
		// Day 4
		$html .= '<div class="day-heading" style="left: 44.1%; top: 0%; ">';
		$html .= date('l', strtotime($dates[3])) . ', ' . date('M d', strtotime($dates[3]));
		$html .= '</div>';
		// Day 5
		$html .= '<div class="day-heading" style="left: 58.4%; top: 0%; ">';
		$html .= date('l', strtotime($dates[2])) . ', ' . date('M d', strtotime($dates[2]));
		$html .= '</div>';
		// Day 6
		$html .= '<div class="day-heading" style="left: 72.2%; top: 0%; ">';
		$html .= date('l', strtotime($dates[1])) . ', ' . date('M d', strtotime($dates[1]));
		$html .= '</div>';
		// Day 7
		$html .= '<div class="day-heading last-heading" style="left: 87%; top: 0%; ">';
		$html .= date('l', strtotime($dates[0])) . ', ' . date('M d', strtotime($dates[0]));
		$html .= '</div>';
		
		return $html;
		
	} // END: get_time_period_header()
	
	/**
	 * viewable()
	 * Helper method to determine whether the calendar is viewable or not
	 *
	 * @return bool $viewable Whether the calendar is viewable or not
	 */
	function viewable() {
		global $edit_flow;
		
		$calendar_enabled = (int)$edit_flow->get_plugin_option('calendar_enabled');
		if ( $calendar_enabled ) {
			$view_calendar_cap = 'ef_view_calendar';
			$view_calendar_cap = apply_filters( 'ef_view_calendar_cap', $view_calendar_cap );
			if ( current_user_can( $view_calendar_cap ) ) return true;
		}
		return false;
		
	} // END: viewable()
	
	/**
	 * get_calendar_posts()
	 * Query to get all of the calendar posts for a given day
	 *
	 * @param string $date The date for which we want posts
	 * @param array $args Any filter arguments we want to pass
	 * @return object $posts All of the posts as an object
	 */
	function get_calendar_posts( $date, $args = null ) {
		global $wpdb, $edit_flow;
		
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );
		$defaults = array(	'post_status' => null,
							'cat'         => null,
						  	'author'      => null,
							'post_type' => $supported_post_types,
						  );
						 
		$args = array_merge( $defaults, $args );
		
		// Unpublished as a status is just an array of everything but 'publish'
		if ( $args['post_status'] == 'unpublish' ) {
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			foreach ( $custom_statuses as $custom_status ) {
				$args['post_status'] .= $custom_status->slug . ', ';
			}
			if ( apply_filters( 'ef_show_scheduled_as_unpublished', false ) ) {
				$args['post_status'] .= 'future';
			}
		} // END if ( $args['post_status'] == 'unpublish' )
		
		// The WP functions for printing the category and author assign a value of 0 to the default
		// options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
		// unset those arguments. We could alternatively amend the first option from these
		// dropdowns with a regex on the wp_dropdown_cats and wp_dropdown_users filters.
		if ( $args['cat'] === '0' ) unset( $args['cat'] );
		if ( $args['author'] === '0' ) unset( $args['author'] );
				
		$date_array = explode( '-', $date );
		$args['year'] = $date_array[0];
		$args['monthnum'] = $date_array[1];
		$args['day'] = $date_array[2];
		
		if ( count( $supported_post_types ) > 1 && !$args['post_type'] ) {
			$args['post_type'] = $supported_post_types;
		}
		
		// Filter for an end user to implement
		$args = apply_filters( 'ef_calendar_posts_query_args', $args );
		
		$posts = new WP_Query( $args );
		
		return $posts;
		
	} // END: get_calendar_posts()
	
	/**
	 * get_previous_link()
	 * Gets the link for the previous time period
	 *
	 * @param string $start_date The start date for the previous period
	 * @param array $filters Any filters that need to be applied
	 * @return string $url The URL for the next page
	 */
	function get_previous_link( $start_date, $filters ) {
		global $edit_flow;
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );
		
		$p_date = date('d-m-Y', strtotime("-1 day", strtotime($start_date)));
		$url = EDIT_FLOW_CALENDAR_PAGE . '&amp;start_date=' . $p_date;
		$url .= '&amp;post_status=' . $filters['post_status'] . '&amp;cat=' . $filters['cat'];
		$url .= '&amp;author=' . $filters['author'];
		if ( count( $supported_post_types ) > 1 ) {
			$url .= '&amp;type=' . $filters['post_type'];
		}
		return $url;
		
	} // END: get_previous_link()

	/**
	 * get_next_link()
	 * Gets the link for the next time period
	 *
	 * @param string $start_date The start date for the next period
	 * @param array $filters Any filters that need to be applied
	 * @return string $url The URL for the next page
	 */
	function get_next_link( $start_date, $filters ) {
		
		global $edit_flow;
		$supported_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_calendar' );
		
		$n_date = date('d-m-Y', strtotime("+7 days", strtotime($start_date)));
		$url = EDIT_FLOW_CALENDAR_PAGE . '&amp;start_date=' . $n_date;
		$url .= '&amp;post_status=' . $filters['post_status'] . '&amp;cat=' . $filters['cat'];
		$url .= '&amp;author=' . $filters['author'];
		if ( count( $supported_post_types ) > 1 ) {
			$url .= '&amp;type=' . $filters['post_type'];
		}
		return $url;
		
	} // END: get_next_link()
	
	/**
	 * get_end_of_week()
	 * Given a day in string format, returns the day at the end of that week, which can be the given date.
	 * The end of the week is determined by the blog option, 'start_of_week'.
	 *
	 * @param string $date String representing a date
	 * @param string $format Date format in which the end of the week should be returned
	 * @return string $formatted_end_of_week End of the week
	 *
	 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
	 */
	function get_end_of_week( $date, $format = 'Y-m-d' ) {
		
		$date = strtotime( $date );
		$end_of_week = get_option('start_of_week') - 1;
		$day_of_week = date('w', $date);
		$date += ((7 + $end_of_week - $day_of_week) % 7) * 60 * 60 * 24;
		$formatted_end_of_week = date($format, $date);
		return $formatted_end_of_week;
		
	} // END: get_end_of_week()
	
} // END: class EF_Calendar
	
} // END: if ( !class_exists('EF_Calendar') )
