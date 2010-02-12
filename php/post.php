<?php

// Functions that hook into or modify post.php
define(EDIT_FLOW_META_PREFIX, '_ef_');

class post_metadata
{
	// This is comment type used to differentiate editorial comments
	var $comment_type = 'editorial-comment';
	
	function __construct() {
		// Set up metabox and related actions
		add_action('admin_menu', array(&$this, 'add_post_meta_box'));
		add_action('save_post', array(&$this, 'save_post_meta_box'));
		add_action('edit_post', array(&$this, 'save_post_meta_box'));
		add_action('publish_post', array(&$this, 'save_post_meta_box'));
		
		// Load necessary scripts and stylesheets
		add_action('admin_enqueue_scripts', array(&$this, 'add_admin_scripts'));
		
		// Set up filter to hide editorial comments from front-end
		// Use functioning exits, because we'll be asking users to add the function to their theme file
		// This is to make sure that in the event that the plugin is disabled, editorial comments stay hidden
		if(!function_exists('ef_filter_editorial_comments')) {
			add_filter( 'comments_array', array(&$this, 'filter_editorial_comments') );
		}
		if(!function_exists('ef_filter_editorial_comments_count')) {
			add_filter( 'get_comments_number', array(&$this, 'filter_editorial_comments_count') );
		}
		add_filter('comment_feed_where', array( &$this, 'filter_feed_comments' ));
		
		// init all ajax actions
		$this->add_ajax_actions();
	}
	
	// Add necessary AJAX actions
	function add_ajax_actions( ) {
		add_action('wp_ajax_editflow_ajax_insert_comment', array(&$this, 'ajax_insert_comment' ));
	}
	
	// Loads scripts 
	function add_admin_scripts( ) {
		global $pagenow;
		
		// Only add the script to Edit Post and Edit Page -- don't want to bog down the rest of the admin with unnecessary javascript
		if($pagenow == 'post.php' || $pagenow == 'page.php') {
			wp_enqueue_script('edit_flow-post_comment', EDIT_FLOW_URL.'js/post_comment.js', array('jquery','post'), false, true);
			
			//wp_enqueue_script('edit_flow-usergroups', EDIT_FLOW_URL.'js/usergroups.js', array('jquery','post'), false, true);
			//wp_enqueue_script('jquery-quicksearch', EDIT_FLOW_URL.'js/jquery.quicksearch.pack.js', array('jquery'), false, true);
			$thread_comments = (int) get_option('thread_comments');
			?>
			<script type="text/javascript">
				var ef_thread_comments = <?php echo ($thread_comments) ? $thread_comments : 0; ?>;
			</script>
			<?php
		}
		wp_enqueue_style('edit_flow-styles', EDIT_FLOW_URL.'/css/editflow.css', false, false, 'all');
	}
		
	function get_post_meta( $post_id, $name, $single = true ) {
	
		$meta = get_post_meta($post_id, EDIT_FLOW_META_PREFIX . $name);
		
		if($single)	return $meta[0];
		else return $meta;
	}
	
	function post_meta_box() {
		global $post, $edit_flow;
		
		$user = wp_get_current_user();
		
		// Get the assignment description from the custom field
		$description = get_post_meta($post->ID, '_ef_description');
		$description = esc_html( $description[0] );
		
		// Get the assignment due date from the custom field
		$duedate = get_post_meta($post->ID, '_ef_duedate');
		$duedate = absint( $duedate[0] );
		if($duedate) {
			$duedate_month = date('M', $duedate);
			$duedate_day = date('j', $duedate);
			$duedate_year = date('Y', $duedate);
		} else {
			$duedate_month = date('M');
			$duedate_day = date('j');
			$duedate_year = date('Y');	
		}
		
		// Get the assignment location from the custom field
		$location = get_post_meta($post->ID, '_ef_location');
		$location = esc_html( $location[0] );
		
		// Get the assignment workflow from the custom field
//		$workflow = get_post_meta($post->ID, '_ef_workflow');
//		$workflow = esc_html( $workflow[0] );
		
		$following = ef_is_user_following_post($post, $user);
		
		$show_subscriptions = ($edit_flow->get_plugin_option('notifications_enabled') && current_user_can('edit_post_subscriptions'));
		
		// TODO: need to separate view into separate template
		
		?>
		
		<div id="ef_meta-data">
			<h4><?php _e('Post Metadata', 'edit-flow') ?></h4>
			
			<div id="ef_description">
				<label for="ef-description"><?php _e('Description:', 'edit-flow') ?></label>
				<span id="ef_description-display"><?php 
					if ($description != '' && $description != null) {
						echo $description;
					} else {
						_e('None assigned', 'edit-flow');
					} ?></span>&nbsp;
				<a href="#ef-metadata" onclick="jQuery(this).hide();
					jQuery('#ef_description-edit').slideDown(300);
					return false;" id="ef_description-edit_button"><?php _e('Edit', 'edit-flow') ?></a>
				<div id="ef_description-edit" style="display:none;">
					<textarea cols="20" rows="3" id="ef-description" name="ef-description" maxlength="140" autocomplete="off"><?php echo $description; ?></textarea>
					<br />
					<a href="#ef-metadata" class="button" onclick="jQuery('#ef_description-edit').slideUp(300);
						var description = jQuery('#ef-description').val();
						jQuery('#ef_description-display').text(description == '' ? 'None assigned' : description).show();
						jQuery('#ef_description-edit_button').show();
						return false;"><?php _e('OK', 'edit-flow') ?></a>&nbsp;
					<a href="#ef-metadata" onclick="jQuery('#ef_description-edit').slideUp(300);
						var description = jQuery('#ef_description-display').text();
						if (description != 'None assigned')
							jQuery('#ef-description').val(description);
						jQuery('#ef_description-display').show();
						jQuery('#ef_description-edit_button').show();
						return false;"><?php _e('Cancel', 'edit-flow') ?></a>
				</div>
			</div>
			
			<?php //TODO: Need to move the js into a seperate file, since a lot is duplicated ?>
			<div id="ef_duedate">
				<label for="ef_duedate_month"><?php _e('Due Date:', 'edit-flow') ?></label>
				<span id="ef_duedate-display"><?php
					if ($duedate != null) {
						echo $duedate_month . ' ' . $duedate_day . ', ' . $duedate_year;
					} else {
						_e('None assigned', 'edit-flow');
					} ?></span>&nbsp;
				<a href="#ef-metadata" onclick="jQuery(this).hide();
					jQuery('#ef_duedate-edit').slideDown(300);
					return false;" id="ef_duedate-edit_button"><?php _e('Edit', 'edit-flow') ?></a>
				<div id="ef_duedate-edit" style="display:none;">
					<select id="ef_duedate_month" name="ef_duedate_month">
						<?php $months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'); ?>
						<?php foreach( $months as $month ) : ?>
							<option <?php if ($duedate_month == $month) echo 'selected="selected"'; ?>><?php echo $month ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" id="ef_duedate_day" name="ef_duedate_day" value="<?php echo $duedate_day; ?>" size="2" maxlength="2" autocomplete="off" />,
					<input type="text" id="ef_duedate_year" name="ef_duedate_year" value="<?php echo $duedate_year; ?>" size="4" maxlength="4" autocomplete="off" />
					<br />
					<a href="#ef-metadata" class="button" onclick="jQuery('#ef_duedate-edit').slideUp(300);
						var duedate_month = jQuery('#ef_duedate_month').val();
				 		var duedate_day = jQuery('#ef_duedate_day').val();
						var duedate_year = jQuery('#ef_duedate_year').val();
						var duedate = duedate_month + ' ' + duedate_day + ', ' + duedate_year;
						jQuery('#ef_duedate-display').text(duedate).show();
						jQuery('#ef_duedate-edit_button').show();
						return false;"><?php _e('OK', 'edit-flow') ?></a>&nbsp;
					<a href="#ef-metadata" onclick="jQuery('#ef_duedate-edit').slideUp(300);
						var duedate_full = jQuery('#ef_duedate-display').text();
						if (duedate_full != 'None assigned') {
							var month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'July', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
							var duedate = new Date(duedate_full);
							var duedate_month = duedate.getMonth();
							var duedate_day = duedate.getDate();
							var duedate_year = duedate.getFullYear();
							jQuery('#ef_duedate_month').val(month[duedate_month]);
							jQuery('#ef_duedate_day').val(duedate_day);
							jQuery('#ef_duedate_year').val(duedate_year);
						}
						jQuery('#ef_duedate-edit_button').show();
						return false;"><?php _e('Cancel', 'edit-flow') ?></a>
				</div>
			</div>
			
			<div id="ef_location">
				<label for="ef-location"><?php _e('Location:', 'edit-flow') ?></label>
				<span id="ef_location-display"><?php
					if ($location != null && $location != '') {
						echo $location;
					} else {
						_e('None assigned', 'edit-flow');
					} ?></span>&nbsp;
				<a href="#ef-metadata" onclick="jQuery(this).hide();
					jQuery('#ef_location-edit').slideDown(300);
					return false;" id="ef_location-edit_button"><?php _e('Edit', 'edit-flow') ?></a>
				<div id="ef_location-edit" style="display:none;">
					<input type="text" size="20" id="ef-location" name="ef-location" maxlength="50" autocomplete="off" value="<?php echo esc_attr($location); ?>">
					<br />
					<a href="#ef-metadata" class="button" onclick="jQuery('#ef_location-edit').slideUp(300);
						var location = jQuery('#ef-location').val();
						jQuery('#ef_location-display').text(location == '' ? 'None assigned' : location);
						jQuery('#ef_location-edit_button').show();
						return false;"><?php _e('OK', 'edit-flow') ?></a>&nbsp;
					<a href="#ef-metadata" onclick="jQuery('#ef_location-edit').slideUp(300);
						var location = jQuery('#ef_location-display').text();
						if (location != 'None assigned') {
							jQuery('#ef-location').val(location);
						}
						jQuery('#ef_location-edit_button').show();
						return false;"><?php _e('Cancel', 'edit-flow') ?></a>
				</div>
			</div>
		
			<!-- <div id="ef_workflow">
				<label for="ef_workflow">Workflow:</label>
				<span id="ef_workflow-display"><?php
					if ($workflow != null && $workflow != '') {
						//echo $workflow;
					} else {
						//echo 'None assigned';
					} ?></span>&nbsp;
				<a href="#ef-metadata" onclick="jQuery(this).hide();
					jQuery('#ef_workflow-edit').slideDown(300);
					return false;" id="ef_workflow-edit_button">Edit</a>
				<div id="ef_workflow-edit" style="display:none;">
					<select id="ef-workflow" name="ef-workflow">
						<option value="01">Workflows are coming</option>
						<option value="02">Second workflow might be sports</option>
						<option value="02">And the third could be opinion</option>
					</select>
					<a href="#ef-metadata" class="button" onclick="jQuery('#ef_workflow-edit').slideUp(300);
						var location = jQuery('#ef-location').val();
						jQuery('#ef_workflow-display').text(location);
						jQuery('#ef_workflow-edit_button').show();
						return false;">OK</a>&nbsp;
					<a href="#ef-metadata" onclick="jQuery('#ef_workflow-edit').slideUp(300);
						var location = jQuery('#ef_workflow-display').text();
						if (location != 'None assigned') {
							jQuery('#ef-location').val(location);
						}
						jQuery('#ef_workflow-edit_button').show();
						return false;">Cancel</a>
				</div>
			</div> -->
			<!--
			<div>
				<?php // TODO: ONLY show if notifications enabled ?>
				<?php if( $following ) : ?>
					<p><?php _e('You are subscribed to notifications for this post', 'edit-flow'); ?></p>
					<a href="" class="button"><?php _e('Unfollow this post', 'edit-flow'); ?></a>
				<?php else : ?>
					<a href="" class="button"><?php _e('Follow this post', 'edit-flow'); ?></a>
				<?php endif; ?>
				
			</div>
			-->
			
			<?php if( $show_subscriptions ) : ?>
				<!--<a href="#TB_inline?width=600&inlineId=ef-post_following_box" class="button thickbox"><?php _e('Manage Subscriptions', 'edit-flow') ?></a>-->
			<?php endif; ?>
			
			<input type="hidden" name="ef-nonce" id="ef-nonce" value="<?php echo wp_create_nonce('ef-nonce'); ?>" />
		</div>
		
		<?php $this->post_comments_box(); ?>

		<div class="clear"></div>
		
		<?php
	}
	
	function subscriptions_meta_box ( ) {
		global $edit_flow;
		
		if( $edit_flow->get_plugin_option('notifications_enabled') && current_user_can('edit_post_subscriptions') ) 
			$this->post_followers_box( $followers_box_args );
	}
	
	function save_post_meta_box($post_id) {
		global $edit_flow, $post;
		
		if ( !wp_verify_nonce( $_POST['ef-nonce'], 'ef-nonce')) {
			return $post_id;  
		}
		
		if( !wp_is_post_revision($post) && !wp_is_post_autosave($post) ) {
			
			// Get the assignment description and save it to a custom field
			$description = esc_html($_POST['ef-description']);
			update_post_meta($post_id, '_ef_description', $description);	
			
			// Get the assignment due date and save it to a custom field
			$duedate_month = esc_html($_POST['ef_duedate_month']);
			$duedate_day = (int)$_POST['ef_duedate_day'];
			$duedate_year = (int)$_POST['ef_duedate_year'];
			$duedate = strtotime($duedate_month . ' ' . $duedate_day . ', ' . $duedate_year);
			update_post_meta($post_id, '_ef_duedate', $duedate);
			
			// Get the assignment location and save it to the custom field
			$location = esc_html($_POST['ef-location']);
			update_post_meta($post_id, '_ef_location', $location);
			
			// Get the assignment workflow and save it to the custom field
			//$workflow = $_POST['ef-workflow'];
			//update_post_meta($post_id, '_ef_workflow', $workflow);
		}		
	}
	
	/**
	 * Adds Edit Flow meta_box to Post/Page edit pages 
	 */
	function add_post_meta_box() {
		global $edit_flow;
		
		if (function_exists('add_meta_box')) {
			add_meta_box('edit-flow', __('Edit Flow', 'edit-flow'), array(&$this, 'post_meta_box'), 'post', 'normal', 'high');
			add_meta_box('edit-flow', __('Edit Flow', 'edit-flow'), array(&$this, 'post_meta_box'), 'page', 'normal', 'high');
			
			if( $edit_flow->get_plugin_option('notifications_enabled') ) {
				add_meta_box('edit-flow-subscriptions', __('Notification Subscriptions', 'edit-flow'), array(&$this, 'subscriptions_meta_box'), 'post', 'normal', 'high');
				add_meta_box('edit-flow-subscriptions', __('Notification Subscriptions', 'edit-flow'), array(&$this, 'subscriptions_meta_box'), 'page', 'normal', 'high');
			}
		}
	}
	
	/**
	 * Function to filter out editorial comments from comments array
	 * Mainly used to hide editorial comments from front-end
	 */
	function filter_editorial_comments( $comments ) {
		// Only filter if viewing front-end
		if( !is_admin() ) {
			$count = 0;
			foreach($comments as $comment) {
				//print_r($comment);
				if($comment->comment_type == $this->comment_type) {
					unset($comments[$count]);
				}
				$count++;
			}
		}
		return $comments;
	}
	/**
	 * Function to recalculate the number of comments on a post
	 * Mainly used to hide editorial comments from front-end
	 */
	function filter_editorial_comments_count( $count ) {
		global $post;
		// Only filter if viewing front-end
		if( !is_admin() ) {
			// Get number of editorial comments
			$editorial_count = $this->get_editorial_comment_count($post->ID);
			$count = $count - $editorial_count;
		}
		return $count;
	}
	private function get_editorial_comment_count( $id ) {
		global $wpdb; 
		$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s", $id, $this->comment_type));
		if(!$comment_count) $comment_count = 0;
		return $comment_count;
	}
	// Filter editorial comments from feeds
	function filter_feed_comments( $cwhere ) {
		$cwhere .= " AND (comment_type = '' OR comment_type = 'comment' OR comment_type = 'pingback' OR comment_type = 'trackback') ";
		return $cwhere;
	}
	

	
	function post_comments_box( ) {
		global $post, $post_ID;
		?>
		<div id="ef-comments_wrapper">
			<a name="editorialcomments"></a>
			<h4><?php _e('Editorial Comments', 'edit-flow') ?></h4>
			
			<?php
			// Show comments only if not a new post
			if($post_ID != 0) :
				
				// Unused since switched to wp_list_comments
				$editorial_comments = ef_get_comments_plus (
								array(
									'post_id' => $post->ID,
									'comment_type' => $this->comment_type,
									'orderby' => 'comment_date',
									'order' => 'ASC'
								)
							);
				?>
					
				<ul id="ef-comments">
					<?php 
						// We use this so we can take advantage of threading and such
						
						wp_list_comments(
							array(
								'type' => $this->comment_type,
								'callback' => array($this, 'the_comment'),
							), 
							$editorial_comments
						);
					?>
				</ul>
				
				<?php $this->the_comment_form(); ?>
				
			<?php
			else :
			?>
				<p><?php _e('You can\'t add comments yet as this is a new post. Come back once you\'ve saved it. Cool?', 'edit-flow') ?></p>
			<?php
			endif;
			?>
			<div class="clear"></div>
		</div>
		<?php
	}
	
	// Displays the main commenting form
	function the_comment_form( ) {
		global $post;
		
		?>
		<a href="#" id="ef-comment_respond" onclick="editorialCommentReply.open();return false;" class="button-primary alignright" title=" <?php _e('Respond to this post', 'edit-flow') ?>"><span><?php _e('Respond to this Post', 'edit-flow') ?></span></a>
		
		<!-- Reply form, hidden until reply clicked by user -->
		<div id="ef-replyrow" style="display: none;">
			<div id="ef-replycontainer">
				<textarea id="ef-replycontent" name="replycontent" cols="40" rows="5"></textarea>
			</div>
		
			<p id="ef-replysubmit">
				<a class="ef-replysave button-primary alignright" href="#comments-form">
					<span id="ef-replybtn"><?php _e('Submit Response', 'edit-flow') ?></span>
				</a>
				<a class="ef-replycancel button-secondary alignright" href="#comments-form"><?php _e('Cancel', 'edit-flow') ?></a>
				<img alt="Sending comment..." src="<?php echo admin_url('/images/wpspin_light.gif') ?>" class="alignright" style="display: none;" id="ef-comment_loading" />
				<br class="clear" style="margin-bottom:35px;" />
				<span style="display: none;" class="error"></span>
			</p>
		
			<input type="hidden" value="" id="ef-comment_parent" name="ef-comment_parent" />
			<input type="hidden" name="ef-post_id" id="ef-post_id" value="<?php echo $post->ID; ?>" />
			<!--<input type="hidden" name="ef-comment_nonce" id="ef-comment_nonce" value="<?php echo wp_create_nonce('ef_comment_nonce'); ?>" />-->
			<?php wp_nonce_field('comment', 'ef_comment_nonce', false); ?>
			
			<br class="clear" />
		</div>

		<?php
	}
	
	/**
	 * Displays a single comment
	 */
	function the_comment($comment, $args, $depth) {
		global $current_user, $userdata, $post_ID;
		
		// Get current user
		get_currentuserinfo() ;
		
		$GLOBALS['comment'] = $comment;
		
		//$delete_url = esc_url( wp_nonce_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "delete-comment_$comment->comment_ID" ) );
	
		$actions = array();
	
		$actions_string = '';
		// Comments can only be added by users that can edit the post
		if ( current_user_can('edit_post', $post_ID) ) {
			$actions['reply'] = '<a onclick="editorialCommentReply.open(\''.$comment->comment_ID.'\',\''.$comment->comment_post_ID.'\');return false;" class="vim-r hide-if-no-js" title="'.__('Reply to this comment', 'edit-flow').'" href="#">' . __('Reply', 'edit-flow') . '</a>';
			
			$sep = ' ';
			$i = 0;
			foreach ( $actions as $action => $link ) {
				++$i;
				// Reply and quickedit need a hide-if-no-js span
				if ( 'reply' == $action || 'quickedit' == $action )
					$action .= ' hide-if-no-js';
	
				$actions_string .= "<span class='$action'>$sep$link</span>";
			}
		}
	
	?>

		<li id="comment-<?php echo $comment->comment_ID; ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status($comment->comment_ID) ) ); ?>>
		
			<?php echo get_avatar( $comment->comment_author_email, 50 ); ?>

			<div class="post-comment-wrap">
				<h5 class="comment-meta">
				
					<span class="comment-author">
						<?php comment_author_link() ?>
					</span>
					<span class="meta">
						<?php printf( __('%s at %s', 'edit-flow'),  get_comment_date( get_option('date_format') ), get_comment_time() ); ?>
					</span>
				</h5>
	
				<div class="comment-content"><?php comment_text(); ?></div>
				<p class="row-actions"><?php echo $actions_string; ?></p>
	
			</div>
		</li>	
		<?php
	}
		
	// Handles AJAX insert comment
	function ajax_insert_comment( ) {
		global $current_user, $user_ID, $wpdb;
		
		// Verify nonce
		if ( !wp_verify_nonce( $_POST['_nonce'], 'comment')) {
			die( __( "We detected some fishy nonce-sence. Knock it off.", 'edit-flow' ) );
			return;
		}
		
		// Get user info
      	get_currentuserinfo();
      	
      	// Set up comment data
		$post_id = absint($_POST['post_id']);
		$parent = absint($_POST['parent']);
      	
      	// Only allow the comment if user can edit post
      	// @TODO: allow contributers to add comments as well (?)
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			die( __('Sorry, you don\'t have the privileges to add editorial comments. Please talk to your Administrator.', 'edit-flow' ) );
		}
		
		// Verify that comment was actually entered
		$comment_content = trim($_POST['content']);
		if(!$comment_content) {
			die( __( "Please enter a comment.", 'edit-flow' ) ); 
		}
		
		// Check that we have a post_id and user logged in
		if( $post_id && $current_user ) {
			
			// set current time
			$time = current_time('mysql', $gmt = 0); 
			
			// Set comment data
			$data = array(
			    'comment_post_ID' => (int) $post_id,
			    'comment_author' => esc_sql($current_user->display_name),
			    'comment_author_email' => esc_sql($current_user->user_email),
			    'comment_author_url' => esc_sql($current_user->user_url),
			    'comment_content' => wp_kses($comment_content, array('a' => array('href' => array(),'title' => array()),'b' => array(),'i' => array(),'strong' => array(),'em' => array(),'u' => array(),'del' => array(), 'blockquote' => array(), 'sub' => array(), 'sup' => array() )),
			    'comment_type' => $this->comment_type,
			    'comment_parent' => (int) $parent,
			    'user_ID' => (int) $user_ID,
			    'comment_author_IP' => esc_sql($_SERVER['REMOTE_ADDR']),
			    'comment_agent' => esc_sql($_SERVER['HTTP_USER_AGENT']),
			    'comment_date' => $time,
			    'comment_date_gmt' => $time,
				// Set to -1?
			    'comment_approved' => 1,
			);
			
			// Insert Comment
			$comment_id = wp_insert_comment($data);
			$comment = get_comment($comment_id);
			
			// Register actions -- will be used to set up notifications
			if($comment_id) {
				do_action('editflow_comment', $comment);
				/*
				if($parent) {
					do_action('editflow_comment_reply', $comment, $parent);
				} else {
					do_action('editflow_comment_new', $comment);
				}*/
			}

			// Prepare response
			$response = new WP_Ajax_Response();
			
			ob_start();
				$this->the_comment( $comment, '', '' );
				$comment_list_item = ob_get_contents();
			ob_end_clean();
			
			$response->add( array(
				'what' => 'comment',
				'id' => $comment_id,
				'data' => $comment_list_item,
				'action' => ($parent) ? 'reply' : 'new'
			));
		
			$response->send();
						
		} else {
			die( __('Uh oh, we ran into bit of a problem! Try again?', 'edit-flow') );
		}
	}

	/**
	 * Outputs box used to subscribe users and usergroups to Posts
	 * @param $followers array|user 
	 */	
	function post_followers_box ( $args = null ) {
		global $post, $post_ID, $edit_flow;
		
		// TODO: add_cap to set subscribers for posts; default to Admin and editors
		
		// Show subscriptions box if notifications enabled
		// TODO: Remove this check when adding activity stream
		if( !$edit_flow->get_plugin_option('notifications_enabled') ) {
			?>
			<p><?php _e('Aww, notifications aren\'t set up, so you can\'t add any subscriptions to this post.', 'edit-flow'); ?></p>
			<?php
			return;
		}
		
		// Only show on posts that have been saved
		if( !$post_ID || $post_ID == 0 ) {
			?>
			<p><?php _e('You can\'t add subscribers yet since this is a new post. Come back once you\'ve saved it. Deal?', 'edit-flow') ?></p>
			<?php
			return;
		}
		
		$followers = ef_get_following_users( $post->ID, 'id' );
		
		if( !is_array($followers) ) $followers = array($followers);
		$following_usergroups = ef_get_following_usergroups($post->ID, 'slugs');
		
		$user_form_args = array();
		
		$usergroups_form_args = array();
		?>
		<div id="ef-post_following_box">
			<a name="subscriptions"></a>

			<p><?php _e('Select the users and usergroups that should receive notifications when the status of this post is updated or when an editorial comment is added.', 'edit-flow') ?></p>
			<div id="ef-post_following_users_box">
				<h4><?php _e('Users', 'edit-flow') ?></h4>
				<?php ef_users_select_form($followers, $user_form_args); ?>
			</div>
			
			<div id="ef-post_following_usergroups_box">
				<h4><?php _e('User Groups', 'edit-flow') ?></h4>
				<?php ef_usergroups_select_form($following_usergroups, $usergroups_form_args); ?>
			</div>
			<div class="clear"></div>
			<input type="hidden" name="ef-save_followers" value="1" /> <?php // Extra protection against autosaves ?>
		</div>
		<?php
	}

}

/**
 * @class post_status
 * Main class that handles post status on Edit Post pages 
 */
class post_status
{
	
	function __construct() {
		add_action('admin_enqueue_scripts', array(&$this, 'post_admin_header'));
	} // END: __construct()
	
	/**
	 * Adds all necessary javascripts to make custom statuses work
	 */
	function post_admin_header() {
		global $post, $post_ID, $edit_flow, $pagenow, $current_user;
		
		// Get current user
		get_currentuserinfo() ;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		if(current_user_can('edit_posts') && (($edit_flow->get_plugin_option('custom_statuses_enabled') && ($pagenow == 'post.php' || $pagenow == 'edit.php' || $pagenow == 'post-new.php')) || ($edit_flow->get_plugin_option('pages_custom_statuses_enabled') && ($pagenow == 'page.php' || $pagenow == 'edit-pages.php' || $pagenow == 'page-new.php')))) {
			
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
	
			// Get the status of the current post		
			if($post_ID==0) {
				$selected = $edit_flow->get_plugin_option('custom_status_default_status');
			} else {
				$selected = $post->post_status;
			}
	
			// Alright, we want to set up the JS var which contains all custom statuses
			$count = 1;
			$status_array = ''; // actually a JSON object
			// Add the "Publish" status if the post is published
			if($selected == "publish") $status_array .= "{ name: '".__('Published', 'edit-flow')."', slug: '".__('publish', 'edit-flow')."' }, ";
			
			foreach($custom_statuses as $status) {
				$status_array .= "{ name: '". esc_js($status->name) ."', slug: '". esc_js($status->slug) ."', description: '". esc_js($status->description) ."' }";
				$status_array .= ($count == count($custom_statuses)) ? '' : ',';
				$count++;
			}
			
			// Now, let's print the JS vars
			?>
			<script type="text/javascript">
				var custom_statuses = [<?php echo $status_array ?>];
				var current_status = '<?php echo $selected ?>';
				var status_dropdown_visible = <?php echo (int) $edit_flow->get_plugin_option('status_dropdown_visible') ?>;
			</script>
			
			<?php
			// Enqueue custom_status.js
			wp_enqueue_script('edit_flow-custom_status', EDIT_FLOW_URL.'js/custom_status.js', array('jquery','post'), false, true);
		}
	} // END: post_admin_header()
} // END: class

/**
 * Retrieve a list of comments -- overloaded from get_comments and with mods by filosofo (SVN Ticket #10668)
 *
 * @param mixed $args Optional. Array or string of options to override defaults.
 * @return array List of comments.
 */
function ef_get_comments_plus( $args = '' ) {
	global $wpdb;

	$defaults = array( 
	                'author_email' => '', 
	                'ID' => '', 
	                'karma' => '', 
	                'number' => '',  
	                'offset' => '',  
	                'orderby' => '',  
	                'order' => 'DESC',  
	                'parent' => '', 
	                'post_ID' => '', 
	                'post_id' => 0, 
	                'status' => '',  
	                'type' => '', 
	                'user_id' => '', 
	        ); 

	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_SKIP );

	// $args can be whatever, only use the args defined in defaults to compute the key
	$key = md5( serialize( compact(array_keys($defaults)) )  );
	$last_changed = wp_cache_get('last_changed', 'comment');
	if ( !$last_changed ) {
		$last_changed = time();
		wp_cache_set('last_changed', $last_changed, 'comment');
	}
	$cache_key = "get_comments:$key:$last_changed";

	if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
		return $cache;
	}

	$post_id = absint($post_id);

	if ( 'hold' == $status )
		$approved = "comment_approved = '0'";
	elseif ( 'approve' == $status )
		$approved = "comment_approved = '1'";
	elseif ( 'spam' == $status )
		$approved = "comment_approved = 'spam'";
	else
		$approved = "( comment_approved = '0' OR comment_approved = '1' )";

	$order = ( 'ASC' == $order ) ? 'ASC' : 'DESC';

	if ( ! empty( $orderby ) ) { 
            $ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby); 
            $ordersby = array_intersect( 
                    $ordersby,  
                    array( 
                            'comment_agent', 
                            'comment_approved', 
                            'comment_author', 
                            'comment_author_email', 
                            'comment_author_IP', 
                            'comment_author_url', 
                            'comment_content', 
                            'comment_date', 
                            'comment_date_gmt', 
                            'comment_ID', 
                            'comment_karma', 
                            'comment_parent', 
                            'comment_post_ID', 
                            'comment_type', 
                            'user_id', 
                    ) 
            ); 
            $orderby = empty( $ordersby ) ? 'comment_date_gmt' : implode(', ', $ordersby); 
    } else { 
            $orderby = 'comment_date_gmt'; 
    } 

	$number = absint($number);
	$offset = absint($offset);

	if ( !empty($number) ) {
		if ( $offset )
			$number = 'LIMIT ' . $offset . ',' . $number;
		else
			$number = 'LIMIT ' . $number;

	} else {
		$number = '';
	}
	
	$post_where = '';

	if ( ! empty($post_id) )
		$post_where .= $wpdb->prepare( 'comment_post_ID = %d AND ', $post_id ); 
    if ( '' !== $author_email )  
            $post_where .= $wpdb->prepare( 'comment_author_email = %s AND ', $author_email ); 
    if ( '' !== $karma ) 
            $post_where .= $wpdb->prepare( 'comment_karma = %d AND ', $karma ); 
    if ( 'comment' == $type ) 
            $post_where .= "comment_type = '' AND "; 
    elseif ( ! empty( $type ) )  
            $post_where .= $wpdb->prepare( 'comment_type = %s AND ', $type ); 
    if ( '' !== $parent ) 
            $post_where .= $wpdb->prepare( 'comment_parent = %d AND ', $parent ); 
    if ( '' !== $user_id ) 
            $post_where .= $wpdb->prepare( 'user_id = %d AND ', $user_id ); 

	$comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE $post_where $approved ORDER BY $orderby $order $number" );
	wp_cache_add( $cache_key, $comments, 'comment' );

	return $comments;
}


?>