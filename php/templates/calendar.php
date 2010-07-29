<?php
if ('POST' == $_SERVER['REQUEST_METHOD']) {
	if ((isset($_POST['post_id']) && (isset($_POST['date'])))) {
		global $wpdb;
		
		$wpdb->update( $wpdb->posts, array( 'post_date' => $_POST['date'],  ), 
			array( 'ID' => $_POST['post_id'] ), array( '%s' ), array( '%d' ) );      
		die('updated');  
	}
}

global $edit_flow;

if($_GET['edit_flow_custom_status_filter']) {
	$edit_flow->options['custom_status_filter'] = $_GET['edit_flow_custom_status_filter'];  
	update_option($edit_flow->get_plugin_option_fullname('custom_status_filter'), 
		$_GET['edit_flow_custom_status_filter']);
}

if($_GET['edit_flow_custom_category_filter']) {
	$edit_flow->options['custom_category_filter'] = $_GET['edit_flow_custom_category_filter'];  
	update_option($edit_flow->get_plugin_option_fullname('custom_category_filter'), 
		$_GET['edit_flow_custom_category_filter']);
}

if($_GET['edit_flow_custom_author_filter']) {
	$edit_flow->options['custom_author_filter'] = $_GET['edit_flow_custom_author_filter'];  
	update_option($edit_flow->get_plugin_option_fullname('custom_author_filter'), 
		$_GET['edit_flow_custom_author_filter']);
}


date_default_timezone_set('UTC');
$dates = array();
if ($_GET['date']) {
	$time = strtotime( $_GET['date'] );
	$date = date('Y-m-d', $time);
} else {
	$time = time();
	$date = date('Y-m-d');
}

$date = get_end_of_week($date); // don't just set the given date as the end of the week. use the blog's settings

for ($i=0; $i<7; $i++) {
	$dates[$i] = $date;
	$date = date('Y-m-d', strtotime("-1 day", strtotime($date)));
}

?>
	<style>
		.week-heading, .week-footing {
			background: #DFDFDF url('<?php echo admin_url('/images/menu-bits.gif'); ?>') repeat-x scroll left top;
		}
	</style>
	  <div class="wrap">
  
		<div id="calendar-title"><!-- Calendar Title -->
			<div class="icon32" id="icon-edit"><br/></div><!-- These two lines will now fit with the WP style. The icon-edit ID could be changed if we'd like a different icon to appear there. -->
			<h2><?php echo date('F d, Y', strtotime($dates[count($dates)-1])); ?> - 
			<?php echo date('F d, Y', strtotime($dates[0])); ?></h2>
		</div><!-- /Calendar Title -->

		<div id="calendar-wrap"><!-- Calendar Wrapper -->
			<ul class="day-navigation">
			  <li id="calendar-filter">
				<form method="GET" action="">
			<?php
			if ($_GET['date']) { echo '<input type="hidden" name="date" value="'. $_GET['date'] . '"/>'; }
			?>
			<select name="<?php  echo $edit_flow->get_plugin_option_fullname('custom_status_filter') ?>" id="custom_status_filter">
			<option value="all" <?php if ($edit_flow->get_plugin_option('custom_status_filter')=='all') { echo 'selected="selected"';}?>>Show All Posts</option>
			<option value="my-posts" <?php if ($edit_flow->get_plugin_option('custom_status_filter')=='my-posts') { echo 'selected="selected"';}?>>Show My Posts</option>
			<?php $statuses = $edit_flow->custom_status->get_custom_statuses() ?>
				<?php foreach($statuses as $status) : ?>

						<?php $selected = ($edit_flow->get_plugin_option('custom_status_filter')==$status->slug) ? 'selected="selected"' : ''; ?>
						<option value="<?php esc_attr_e($status->slug) ?>" <?php echo $selected ?>>
								Status: <?php esc_html_e($status->name); ?>
						</option>

				<?php endforeach; ?>
			</select>
			<select name="<?php echo $edit_flow->get_plugin_option_fullname('custom_category_filter') ?>" id="custom_category_filter">
				<option value="all" <?php if ($edit_flow->get_plugin_option('custom_category_filter')=='all') { echo 'selected="selected"';}?>>View  All Categories</option>
				<?php $categories = get_categories(); ?>
				<?php foreach ($categories as $category) : ?>
					<?php $selected = ($edit_flow->get_plugin_option('custom_category_filter')==$category->term_id) ? 'selected="selected"' : ''; ?>
					<option value="<?php esc_html_e($category->term_id) ?>" <?php echo $selected ?>>
					  <?php esc_html_e($category->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="<?php echo $edit_flow->get_plugin_option_fullname('custom_author_filter') ?>" id="custom_author_filter">
				<option value="all" <?php if ($edit_flow->get_plugin_option('custom_author_filter')=='all') { echo 'selected="selected"';}?>>View  All Authors</option>
				<?php $users = get_users_of_blog(); ?>
				<?php foreach ($users as $user) : ?>
					<?php $selected = ($edit_flow->get_plugin_option('custom_author_filter')==$user->ID) ? 'selected="selected"' : ''; ?>
					<option value="<?php esc_html_e($user->ID) ?>" <?php echo $selected ?>>
					  <?php esc_html_e($user->display_name); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<input type="hidden" name="page" value="edit-flow/calendar"/>
			<input type="submit" class="button primary" value="Filter"/>
			</form>
		  </li>
		  <li class="performing-ajax">
			<img src="<?php echo EDIT_FLOW_URL; ?>img/wpspin_light.gif" alt="Loading" />
		  </li>
		  <li class="next-week">
					<a id="trigger-left" href="<?php echo ef_get_calendar_next_link($dates[0]) ?>">Next &raquo;</a>
				</li>
				<li class="previous-week">
					<a id="trigger-right" href="<?php echo ef_get_calendar_previous_link($dates[count($dates)-1]) ?>">&laquo; Previous</a>
		  </li>
			</ul>

			<div id="week-wrap"><!-- Week Wrapper -->
				<div class="week-heading"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
					<div class="day-heading first-heading" style="width: 13.8%; height: 100%; position: absolute; left: 0%; top: 0%; ">
						<?php echo date('l', strtotime($dates[6])); ?>, <?php echo date('M d', strtotime($dates[6])); ?>
					</div>
					<div class="day-heading" style="left: 15.6%; top: 0%; ">
            <?php echo date('l', strtotime($dates[5])); ?>, <?php echo date('M d', strtotime($dates[5])); ?>
					</div>
					<div class="day-heading" style="left: 30%; top: 0%; ">
            <?php echo date('l', strtotime($dates[4])); ?>, <?php echo date('M d', strtotime($dates[4])); ?>
					</div>
					<div class="day-heading" style="left: 44.1%; top: 0%; ">
            <?php echo date('l', strtotime($dates[3])); ?>, <?php echo date('M d', strtotime($dates[3])); ?>
					</div>
					<div class="day-heading" style="left: 58.4%; top: 0%; ">
					  <?php echo date('l', strtotime($dates[2])); ?>, <?php echo date('M d', strtotime($dates[2])); ?>
					</div>
					<div class="day-heading" style="left: 72.2%; top: 0%; ">
					<?php echo date('l', strtotime($dates[1])); ?>, <?php echo date('M d', strtotime($dates[1])); ?>
					</div>
					<div class="day-heading last-heading" style="left: 87%; top: 0%; ">
					<?php echo date('l', strtotime($dates[0])); ?>, <?php echo date('M d', strtotime($dates[0])); ?>
					</div>
				</div><!-- From here on it is the same HTML but you can add two more week-units now to get the 7 days into the calendar. -->
				
				<?php
				foreach (array_reverse($dates) as $key => $date) {
					$cal_posts = ef_get_calendar_posts($date);
				?>
				<div class="week-unit<?php if ($key == 0) echo ' left-column'; ?>"><!-- Week Unit 1 -->
					<ul id="<?php echo date('Y-m-d', strtotime($date)) ?>" class="week-list connectedSortable">
						<?php
						foreach ($cal_posts as $cal_post) {
							$cats = wp_get_object_terms($cal_post->ID, 'category');
							$cat = $cats[0]->name;
							if (count($cats) > 1) { 
								$cat .= " and  " . (count($cats) - 1);
								if (count($cats)-1 == 1) { $cat .= " other"; }
								else { $cat .= " others"; }
							}
							
						?>
						<li class="week-item" id="<?php echo $cal_post->ID ?>">
						  <div class="item-handle">
							<span class="item-headline post-title">
								<?php echo $cal_post->post_title; ?>
							</span>
							<ul class="item-metadata">
								<li class="item-author">By <?php echo $cal_post->display_name ?></li>
								<li class="item-category">
									<?php echo $cat ?>
								</li>
							</ul>
							</div>
							<div class="item-actions">
							  <span class="edit">
								<?php echo edit_post_link('Edit', '', '', $cal_post->ID); ?>
							  </span> | 
							  <span class="view">
								<a href="<?php echo get_permalink($cal_post->ID); ?>">View</a>
							  </span>
							</div>
							<div style="clear:left;"></div>
						</li>
						<?php
						}
						?>
					</ul>
				</div><!-- /Week Unit 1 -->
				<?php
				}
				?>
				
				<div style="clear:both"></div>
				<div class="week-footing"><!-- New HTML begins with this week-heading div. Adds a WP-style dark grey heading to the calendar. Styles were added inline here to save having 7 different divs for this. -->
					<div class="day-heading first-heading" style="width: 13.8%; height: 100%; position: absolute; left: 0%; top: 0%; ">
						<?php echo date('l', strtotime($dates[6])); ?>, <?php echo date('M d', strtotime($dates[6])); ?>
					</div>
					<div class="day-heading" style="left: 15.6%; top: 0%; ">
					  <?php echo date('l', strtotime($dates[5])); ?>, <?php echo date('M d', strtotime($dates[5])); ?>
					</div>
					<div class="day-heading" style="left: 30%; top: 0%; ">
            <?php echo date('l', strtotime($dates[4])); ?>, <?php echo date('M d', strtotime($dates[4])); ?>
					</div>
					<div class="day-heading" style="left: 44.1%; top: 0%; ">
            <?php echo date('l', strtotime($dates[3])); ?>, <?php echo date('M d', strtotime($dates[3])); ?>
					</div>
					<div class="day-heading" style="left: 58.4%; top: 0%; ">
					  <?php echo date('l', strtotime($dates[2])); ?>, <?php echo date('M d', strtotime($dates[2])); ?>
					</div>
					<div class="day-heading" style="left: 72.2%; top: 0%; ">
            <?php echo date('l', strtotime($dates[1])); ?>, <?php echo date('M d', strtotime($dates[1])); ?>
					</div>
					<div class="day-heading last-heading" style="left: 87%; top: 0%; ">
            <?php echo date('l', strtotime($dates[0])); ?>, <?php echo date('M d', strtotime($dates[0])); ?>					  
					</div>
				</div><!-- From here on it is the same HTML but you can add two more week-units now to get the 7 days into the calendar. -->
				
			</div><!-- /Week Wrapper -->
			<ul class="day-navigation">
			  <li class="next-week">
					<a href="<?php echo ef_get_calendar_next_link($dates[0]) ?>">Next &raquo;</a>
				</li>
				<li class="previous-week">
					<a href="<?php echo ef_get_calendar_previous_link($dates[count($dates)-1]) ?>">&laquo; Previous</a>
				</li>
			</ul>
			<div style="clear:both"></div>
		</div><!-- /Calendar Wrapper -->

	  </div>

<?php 
function ef_get_calendar_previous_link( $date ) {
	$p_date = date('d-m-Y', strtotime("-1 day", strtotime($date)));
	return EDIT_FLOW_CALENDAR_PAGE.'&amp;date='.$p_date;
}

function ef_get_calendar_next_link( $date ) {
	$n_date = date('d-m-Y', strtotime("+7 days", strtotime($date)));
	return EDIT_FLOW_CALENDAR_PAGE.'&amp;date='.$n_date;
}

function ef_get_calendar_posts( $date ) {
 
	global $wpdb, $edit_flow;
	$q_date = date('Y-m-d', strtotime($date));
	
	$sql = "SELECT DISTINCT w.ID, w.guid, w.post_date, u.display_name, w.post_title ";
	$sql .= "FROM " . $wpdb->posts . " w, ". $wpdb->users . " u, ";
	$sql .= $wpdb->term_relationships . " t ";
	$sql .= "WHERE u.ID=w.post_author and ";
	if (($edit_flow->get_plugin_option('custom_status_filter') != 'all') && 
		($edit_flow->get_plugin_option('custom_status_filter') != 'my-posts')) {
		$sql .= "w.post_status = '" . $edit_flow->get_plugin_option('custom_status_filter') . "' and ";
	}
	if ($edit_flow->get_plugin_option('custom_status_filter') == 'my-posts') {
		$sql .= " u.ID = " . wp_get_current_user()->ID . " and ";
	}
	$sql .= "w.post_status <> 'auto-draft' and "; // Hide auto draft posts
	$sql .= "w.post_status <> 'trash' and "; // Hide trashed posts
	$sql .= "w.post_type = 'post' and w.post_date like '". $q_date . "%' and ";
	$sql .= "t.object_id = w.ID";
	if ($edit_flow->get_plugin_option('custom_category_filter') != 'all') {
		$sql .= " and t.term_taxonomy_id = " . $edit_flow->get_plugin_option('custom_category_filter');
	}
	if ($edit_flow->get_plugin_option('custom_author_filter') != 'all') {
		$sql .= " and u.ID = " . $edit_flow->get_plugin_option('custom_author_filter');
	}
	
	#echo "<pre>" . $sql . "</pre>";
	$cal_posts = $wpdb->get_results($sql);
	return $cal_posts;
}

/**
 * Given a day in string format, returns the day at the end of that week, which can be the given date.
 * The end of the week is determined by the blog option, 'start_of_week'.
 *
 * @param string $date String representing a date
 * @param string $format Date format in which the end of the week should be returned
 *
 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
 */
function get_end_of_week($date, $format = 'Y-m-d') {
	$date = strtotime( $date );
	$end_of_week = get_option('start_of_week') - 1;
	$day_of_week = date('w', $date);
	$date += ((7 + $end_of_week - $day_of_week) % 7) * 60 * 60 * 24;
	return date($format, $date);
}