<?php

// Code for all dashboard widgets will go here

class edit_flow_dashboard {
	
	function __construct () {
		add_action('wp_dashboard_setup', array(&$this, 'add_dashboard_widgets'));
	}
	
	function add_dashboard_widgets () {
		global $edit_flow, $current_user;
		
		// If the current user is a Contributor or greater, show the dashboard widgets
		if ($current_user->has_cap('edit_posts')) {
			
			// Set up Post Status widget but, first, check to see if it's enabled
			if ($edit_flow->get_plugin_option('post_status_widget_enabled')) {
				wp_add_dashboard_widget('post_status_widget', __('Unpublished Content', 'edit-flow'), array(&$this, 'post_status_widget'));
			}
			
			// Add the QuickPitch widget, if enabled
			if ($edit_flow->get_plugin_option('quickpitch_widget_enabled')) {
				wp_add_dashboard_widget('quick_pitch_widget', __('QuickPitch', 'edit-flow'), array(&$this, 'quick_pitch_widget'));
			}
			
			// Add the QuickPitch widget, if enabled
			if ($edit_flow->get_plugin_option('myposts_widget_enabled')) {
				wp_add_dashboard_widget('myposts_widget', __('Posts I\'m Following', 'edit-flow'), array(&$this, 'myposts_widget'));
			}
		}
		
	}
	
	function quick_pitch_widget() {
		global $post, $edit_flow, $current_user;
		
		if( isset($_POST['ef_submit']) && trim($_POST['ef_title']) != '') {
			
			check_admin_referer( 'quickpitch-submit', 'ef-quickpitch_nonce' );
			
			// Get WordPress-specific post data
			$post['post_title'] = esc_sql($_POST['ef_title']);
			$post['post_author'] = esc_sql($_POST['ef_author']);
			$post['post_status'] = $edit_flow->get_plugin_option('custom_status_default_status');
			
			$post_id = wp_insert_post($post);
			
			// Get metadata specific to Edit Flow and save it in custom fields
			$description = esc_sql($_POST['ef_description']);
			update_post_meta($post_id, '_ef_description', $description);
			//$duedate = esc_sql($_POST['ef_duedate']);
			//$duedate_unix = strtotime($duedate);
			//update_post_meta($post_id, '_ef_duedate', $duedate_unix);
			$location = esc_sql($_POST['ef_location']);
			update_post_meta($post_id, '_ef_location', $location);
			//$workflow = esc_sql($_POST['ef_workflow']);
			//update_post_meta($post_id, '_ef_workflow', $workflow);
			
			?>
			
			<div id="quick-pitch-notification" onload="jQuery('#quick-pitch-form').hide();">
				
				<h3><?php _e('Pitch Saved', 'edit-flow') ?></h3>
				
				<p><strong><?php _e('Title:', 'edit-flow') ?></strong> <?php echo $post['post_title']; ?></p>
				<p><strong><?php _e('Author:', 'edit-flow') ?></strong> <?php the_author_meta('user_nicename', $post['post_author']); ?></p>
				<p><strong><?php _e('Details:', 'edit-flow') ?></strong> <?php if ($description != "") { echo $description; } else { echo "<em>None specified</em>"; } ?></p>
				<p><strong><?php _e('Location:', 'edit-flow') ?></strong> <?php if ($location != "") { echo $location; } else { echo "<em>None specified</em>"; } ?></p>
				<!--<p><strong><?php _e('Due date:', 'edit-flow') ?></strong> <?php if ($duedate != "") { echo $duedate; } else { echo "<em>None specified</em>"; } ?></p>-->
				
				<div class="ef-submit">
					<a href="<?php echo admin_url('post.php') ?>?action=edit&amp;post=<?php echo $post_id; ?>" class="button">Edit</a>&nbsp;&nbsp;
					<a href="javascript:void(0);" onclick="jQuery('#quick-pitch-notification').hide();jQuery('#quick-pitch-form').slideDown(300);" class="button-primary"><?php _e('New Pitch', 'edit-flow') ?></a>
				</div>
			</div>
			
			<?php
			
		}
		
		$authors = get_editable_user_ids( $current_user->id );
		if ( $post->post_author && !in_array($post->post_author, $authors) )
			$authors[] = $post->post_author;
			
		?>
		
		<div id="quick-pitch-form" <?php if (isset($_POST['ef_submit']) && $_POST['ef_title'] != "") { echo 'style="display:none;"'; } ?>>
		<?php if (isset($_POST['ef_submit']) && $_POST['ef_title'] == "") { ?>
			<div class="quick-pitch-notice"><?php _e('Missing title. Title is required.', 'edit-flow') ?></div>
		<?php } ?>
		
		<form name="pitch" id="quick-pitch" method="post">
			
			<p><?php _e('Got an idea for a new post or have some lingering thoughts you want to stash somewhere? Use the form below to flesh out the idea and we\'ll save it for you!', 'edit-flow') ?></p>
			
			<h4><label for="ef_title"><?php _e('Title', 'edit-flow') ?></label></h4>
			<div class="input-text-wrap">
				<input type="text" name="ef_title" id="ef_title" autocomplete="off" value="" />
			</div>
			
			
			<?php if ($current_user->has_cap('edit_others_posts')) : ?>
				<div id="ef_author_div">
					<h4><label for="ef_author"><?php _e('Author', 'edit-flow') ?></label></h4>
					<?php wp_dropdown_users(array('name' => 'ef_author')); ?>
				</div>
			<?php else : ?>
				<input type="hidden" name="ef_author" value="<?php echo $current_user->ID ?>" />
			<?php endif; ?>
			
			<h4><label for="ef_description"><?php _e('Details', 'edit-flow') ?></label></h4>
			<div class="textarea-wrap">
				<textarea name="ef_description" id="ef_description" rows="3" cols="15" maxlength="255"></textarea>
			</div>
			
			<h4><label for="ef_location"><?php _e('Location', 'edit-flow') ?></label></h4>
			<div class="input-text-wrap">
				<input type="text" id="ef_location" name="ef_location" maxlength="50" />
			</div>
			
			<!--
			<h4><label for="ef_duedate"><?php _e('Due date', 'edit-flow') ?></label></h4>
			<div class="input-text-wrap">
				<input type="text" id="ef_duedate" name="ef_duedate" maxlength="25" />
			</div>
			-->
			
			<div class="ef-submit">
				<a href="javascript:void(0);" class="button" onclick="jQuery('#ef_title').val('');
						jQuery('#ef_description').val('');
						jQuery('#ef_location').val('');
						jQuery('#ef_duedate').val('');"><?php _e('Reset', 'edit-flow') ?></a>&nbsp;&nbsp;
				<input type="submit" name="ef_submit" id="ef_submit" class="button-primary" value="<?php _e('Save Pitch', 'edit-flow') ?>" />
			</div>
			<?php wp_nonce_field( 'quickpitch-submit', 'ef-quickpitch_nonce' ) ?>
			
		</form>
		
		</div>
		
		<?php
	}
	
	/**
	 * Creates Post Status widget
	 * Display an at-a-glance view of post counts for all custom statuses in the system
	 */
	function post_status_widget () {
		global $edit_flow;
		
		$statuses = $edit_flow->custom_status->get_custom_statuses();
		
		?>
		<p class="sub"><?php _e('Posts at a Glance', 'edit-flow') ?></p>
		
		<div class="table">
			<table>
				<tbody>
					<?php foreach($statuses as $status) : ?>
						<?php $filter_link = esc_url(ef_get_custom_status_filter_link($status->slug)) ?>
						<tr>
							<td class="b">
								<a href="<?php echo $filter_link; ?>">
									<?php esc_html_e(ef_get_custom_status_post_count($status->slug)) ?>
								</a>
							</td>
							<td>
								<a href="<?php echo $filter_link; ?>"><?php esc_html_e($status->name) ?></a>
								<span class="small"><a href="<?php echo ef_get_custom_status_edit_link($status->term_id) ?>">[edit]</a></span>
							</td>
						</tr>
							
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	
	function myposts_widget ( ) {
		global $edit_flow;
		
		$myposts = ef_get_user_following_posts();
		
		?>
		<div class="ef-myposts">
			<?php if( !empty($myposts) ) : ?>
				
				<?php foreach( $myposts as $post ) : ?>
					<?php
					$url = esc_url(get_edit_post_link( $post->ID ));
					$title = esc_html($post->post_title);
					$item = "<h4><a href='$url' title='" . sprintf( __( 'Edit &#8220;%s&#8221;' ), esc_attr( $title ) ) . "'>" . esc_html($title) . "</a> <abbr title='" . get_the_time(__('Y/m/d g:i:s A'), $draft) . "'>" . get_the_time( get_option( 'date_format' ), $draft ) . '</abbr></h4>';
					?>
					<li>
						<h4><a href="<?php echo $url ?>" title="<?php _e('Edit this post', 'edit-flow') ?>"><?php echo $title; ?></a></h4>
						<?php _e('last updated:', 'edit-flow') ?> <?php echo get_the_time('Y/m/d g:i:s A', $post) ?>
					</li>
					
				<?php endforeach; ?>
			<?php else : ?>
				<p><?php _e('Sorry! You\'re not subscribed to any posts!', 'edit-flow') ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}


?>