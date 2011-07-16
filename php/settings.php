<?php

if ( !class_exists('EF_Settings') ) {

class EF_Settings {
	
	function __construct() {
		
	}
	
	function init() {
		
	}
	
	/* 
	 * Adds Settings page for Edit Flow.
	 */
	function settings_page( ) {
		global $edit_flow, $wp_roles;
		
		$msg = null;
		if( array_key_exists( 'updated', $_GET ) && $_GET['updated']=='true' ) { $msg = __('Settings Saved', 'edit-flow'); }
		
		?>
			<div class="wrap">
				<div class="icon32" id="icon-options-general"><br/></div>
				<h2><?php _e('Edit Flow Settings', 'edit-flow') ?></h2>
				
				<?php if($msg) : ?>
					<div class="updated fade" id="message">
						<p><strong><?php echo $msg ?></strong></p>
					</div>
				<?php endif; ?>
				
				<form method="post" action="options.php">
					<?php settings_fields( $edit_flow->options_group ); ?>
					
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><strong><?php _e('Custom Statuses', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="custom_status_default_status">
										<?php _e('Default Status for new posts', 'edit-flow') ?>
									</label>
									<select name="<?php  echo $edit_flow->get_plugin_option_fullname('custom_status_default_status') ?>" id="custom_status_default_status">
										
										<?php $statuses = $edit_flow->custom_status->get_custom_statuses() ?>
										<?php foreach($statuses as $status) : ?>
										
											<?php $selected = ($edit_flow->get_plugin_option('custom_status_default_status')==$status->slug) ? 'selected="selected"' : ''; ?>
											<option value="<?php echo esc_attr($status->slug) ?>" <?php echo $selected ?>>
												<?php echo esc_html($status->name); ?>
											</option>
											
										<?php endforeach; ?>
									</select>
									<br />
									<span class="description"><?php _e('The default status that is applied when a new post is created.', 'edit-flow') ?></span>
								</p>
								
								<p>
									<label for="status_dropdown_visible">
										<input type="checkbox" name="<?php echo $edit_flow->get_plugin_option_fullname('status_dropdown_visible') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('status_dropdown_visible')) ? 'checked="checked"' : ''; ?> id="status_dropdown_visible" />
										<?php _e('Always show status dropdown', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Enabling this option will keep the "Status" dropdown visible at all times when editing posts and pages to allow for easy updating of statuses.', 'edit-flow') ?></span>
								</p>
									
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Calendar', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="calendar_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('calendar_enabled') ?>" value="1" <?php checked($edit_flow->get_plugin_option('calendar_enabled')); ?> id="calendar_enabled" />
										<?php _e('Enable Edit Flow Calendar', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('This enables the Edit Flow Calendar to view editorial content at a glance.', 'edit-flow') ?></span>
								</p>
								<?php
								/* Options for modifying the roles with calendar viewing privileges, though the logic to actually modify those roles was never written. Just didn't want to delete this yet.
								<p>
									<strong>Roles that can view calendar</strong><br />
									<?php foreach($wp_roles->get_names() as $role => $role_name) :
										if ( $wp_roles->is_role( $role ) ) :
											$target_role =& get_role( $role );
											$role_has_cap = $target_role->has_cap( 'view_calendar' );
											?>
											<label for="calendar_view_<?php echo $role; ?>">
												<input type="checkbox" id="calendar_view_<?php echo $role; ?>" value="<?php echo $role; ?>" <?php echo ($role_has_cap ? 'checked="yes"' : '');?> style="margin-bottom: 5px;" />
												<?php _e($role_name, 'edit-flow') ?>
											</label>
											<br />
										<?php endif; ?>
									<?php endforeach; ?>
									<span class="description"><?php _e('Select which roles above may view the Edit Flow Calendar.', 'edit-flow') ?></span>
								</p>
								*/
								?>
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Story Budget', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="story_budget_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('story_budget_enabled') ?>" value="1" <?php checked($edit_flow->get_plugin_option('story_budget_enabled')); ?> id="story_budget_enabled" />
										<?php _e('Enable Story Budget', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('This enables the Story Budget, an optimized dashboard to view the progress of your content.', 'edit-flow') ?></span>
								</p>
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Dashboard', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="dashboard_widgets_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('dashboard_widgets_enabled') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('dashboard_widgets_enabled')) ? 'checked="checked"' : ''; ?> id="dashboard_widgets_enabled" />
										<?php _e('Enable Dashboard Widgets', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Enables a special set of dashboard widgets for use with Edit Flow. Enable this setting to view the list of available widgets.', 'edit-flow') ?></span>
								</p>
								<?php if($edit_flow->get_plugin_option('dashboard_widgets_enabled')) : ?>
								<p>
									<label for="post_status_widget_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('post_status_widget_enabled') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('post_status_widget_enabled')) ? 'checked="checked"' : ''; ?> id="post_status_widget_enabled" />
										<?php _e('Enable Post Status Dashboard Widget', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you an at-a-glance view of the current status of your unpublished content.', 'edit-flow') ?></span>
								</p>
								<?php
								/* 
								QuickPitch widget is disabled as of v0.6 because we need to refactor editorial metadata handling ^DB
								<p>
									<label for="quickpitch_widget_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('quickpitch_widget_enabled') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('quickpitch_widget_enabled')) ? 'checked="checked"' : ''; ?> id="quickpitch_widget_enabled" />
										<?php _e('Enable QuickPitch Dashboard Widget') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you the ability to create a pitch or draft post from the dashboard.', 'edit-flow') ?></span>
								</p> */ ?>
								<p>
									<label for="myposts_widget_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('myposts_widget_enabled') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('myposts_widget_enabled')) ? 'checked="checked"' : ''; ?> id="myposts_widget_enabled" />
										<?php _e('Enable My Posts Dashboard Widget', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('Gives you quick access to Posts that you are currently following.', 'edit-flow') ?></span>
								</p>
								<?php endif; ?>
								
							</td>
						</tr>
						
						<tr valign="top">
							<th scope="row"><strong><?php _e('Notifications', 'edit-flow') ?></strong></th>
							<td>
								<p>
									<label for="notifications_enabled">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('notifications_enabled') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('notifications_enabled')) ? 'checked="checked"' : ''; ?> id="notifications_enabled" />
										<?php _e('Enable Email Notifications', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('This sends out emails notifications whenever certain actions related to posts occur. Currently, only the following notifications are available: a) email notification on post status change; and b) email notification when an editorial comment is added to a post.', 'edit-flow') ?></span>
								</p>
								<p>
									<label for="always_notify_admin">
										<input type="checkbox" name="<?php  echo $edit_flow->get_plugin_option_fullname('always_notify_admin') ?>" value="1" <?php echo ($edit_flow->get_plugin_option('always_notify_admin')) ? 'checked="checked"' : ''; ?> id="always_notify_admin" />
										<?php _e('Always Notify Admin', 'edit-flow') ?>
									</label> <br />
									<span class="description"><?php _e('If notifications are enabled, the blog administrator will always receive notifications.', 'edit-flow') ?></span>
								</p>
							</td>
						</tr>
						
					</table>
									
					<p class="submit">
						<input type="hidden" name="<?php echo $edit_flow->get_plugin_option_fullname('version') ?>" value="<?php echo ($edit_flow->get_plugin_option('version')) ?>" />
					    <input type="hidden" name="<?php echo $edit_flow->get_plugin_option_fullname('custom_status_filter') ?>" value="<?php echo ($edit_flow->get_plugin_option('custom_status_filter')) ?>" id="custom_status_filter" />
				        <input type="hidden" name="<?php echo $edit_flow->get_plugin_option_fullname('custom_category_filter') ?>" value="<?php echo ($edit_flow->get_plugin_option('custom_category_filter')) ?>" id="custom_category_filter" />
				        <input type="hidden" name="<?php echo $edit_flow->get_plugin_option_fullname('custom_author_filter') ?>" value="<?php echo ($edit_flow->get_plugin_option('custom_author_filter')) ?>" id="custom_author_filter" />
						<input type="submit" class="button-primary" value="<?php _e('Save Changes', 'edit-flow') ?>" />
					</p>
				</form>
			</div>
		<?php 
	}
	
}

}