<?php

// Functions that hook into or modify post.php
define( 'EDIT_FLOW_META_PREFIX', '_ef_' );

if ( !class_exists('EF_Post_Metadata') ) {

class EF_Post_Metadata
{
	// This is comment type used to differentiate editorial comments
	var $comment_type = 'editorial-comment';
	
	function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
		
		// Set up metabox and related actions
		add_action('admin_menu', array(&$this, 'add_post_meta_box'));
		
		// Load necessary scripts and stylesheets
		add_action('admin_enqueue_scripts', array(&$this, 'add_admin_scripts'));
		
		// init all ajax actions
		$this->add_ajax_actions();
	}
	
	function init() {
		global $edit_flow;
		foreach( array( 'post', 'page' ) as $post_type ) {
			add_post_type_support( $post_type, 'ef_editorial_comments' );
		}
	}
	
	// Add necessary AJAX actions
	function add_ajax_actions( ) {
		add_action( 'wp_ajax_editflow_ajax_insert_comment', array( &$this, 'ajax_insert_comment' ) );
	}
	
	// Loads scripts 
	function add_admin_scripts( ) {
		global $pagenow, $edit_flow;
		
		wp_enqueue_style( 'edit_flow-styles', EDIT_FLOW_URL . 'css/editflow.css', false, EDIT_FLOW_VERSION, 'all' );
		
		$post_type = $edit_flow->get_current_post_type();
		
		// Only add the script to Edit Post and Edit Page -- don't want to bog down the rest of the admin with unnecessary javascript
		if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'page-new.php' ) ) ) {
			
			if( post_type_supports( $post_type, 'ef_editorial_comments' ) ) {
				wp_enqueue_script( 'edit_flow-post_comment', EDIT_FLOW_URL . 'js/post_comment.js', array( 'jquery','post' ), EDIT_FLOW_VERSION, true );
				
				$thread_comments = (int) get_option('thread_comments');
				?>
				<script type="text/javascript">
					var ef_thread_comments = <?php echo ($thread_comments) ? $thread_comments : 0; ?>;
				</script>
				<?php
			}
			
			if( post_type_supports( $post_type, 'ef_notifications' ) ) {
				wp_enqueue_script( 'jquery-listfilterizer' );
				wp_enqueue_style( 'jquery-listfilterizer' );
			}
		}
	}
		
	function get_post_meta( $post_id, $name, $single = true ) {
	
		$meta = get_post_meta($post_id, EDIT_FLOW_META_PREFIX . $name);
		
		if($single)	return $meta[0];
		else return $meta;
	}
		
	function subscriptions_meta_box ( ) {
		$post_subscriptions_cap = apply_filters( 'ef_edit_post_subscriptions_cap', 'edit_post_subscriptions' );
		
		if( current_user_can( $post_subscriptions_cap ) ) 
			$this->post_followers_box();
	}
	
	/**
	 * Adds Edit Flow meta_box to Post/Page edit pages 
	 */
	function add_post_meta_box() {
		global $edit_flow;
		
		if (function_exists('add_meta_box')) {
			$comment_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_editorial_comments' );
			foreach ( $comment_post_types as $post_type ) {
				add_meta_box('edit-flow-editorial-comments', __('Editorial Comments', 'edit-flow'), array(&$this, 'editorial_comments_meta_box'), $post_type, 'normal', 'high');
			}
			
			if( $edit_flow->get_plugin_option('notifications_enabled') ) {
				$notification_post_types = $edit_flow->get_all_post_types_for_feature( 'ef_notifications' );
				foreach ( $notification_post_types as $post_type ) {
					add_meta_box('edit-flow-subscriptions', __('Notification Subscriptions', 'edit-flow'), array(&$this, 'subscriptions_meta_box'), $post_type, 'advanced', 'high');
				}
			}
		}
	}
	function get_editorial_comment_count( $id ) {
		global $wpdb; 
		$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s", $id, $this->comment_type));
		if(!$comment_count) $comment_count = 0;
		return $comment_count;
	}
	
	function editorial_comments_meta_box( ) {
		global $post, $post_ID;
		?>
		<div id="ef-comments_wrapper">
			<a name="editorialcomments"></a>
			
			<?php
			// Show comments only if not a new post
			if( ! in_array( $post->post_status, array( 'new', 'auto-draft' ) ) ) :
				
				// Unused since switched to wp_list_comments
				$editorial_comments = ef_get_comments_plus (
								array(
									'post_id' => $post->ID,
									'comment_type' => $this->comment_type,
									'orderby' => 'comment_date',
									'order' => 'ASC',
									'status' => $this->comment_type
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
				<p><?php _e( 'You can add editorial comments to a post once you\'ve saved it for the first time.', 'edit-flow' ); ?></p>
			<?php
			endif;
			?>
			<div class="clear"></div>
		</div>
		<div class="clear"></div>
		<?php
	}
	
	// Displays the main commenting form
	function the_comment_form( ) {
		global $post;
		
		?>
		<a href="#" id="ef-comment_respond" onclick="editorialCommentReply.open();return false;" class="button-primary alignright hide-if-no-js" title=" <?php _e( 'Respond to this post', 'edit-flow' ); ?>"><span><?php _e( 'Respond to this Post', 'edit-flow' ); ?></span></a>
		
		<!-- Reply form, hidden until reply clicked by user -->
		<div id="ef-replyrow" style="display: none;">
			<div id="ef-replycontainer">
				<textarea id="ef-replycontent" name="replycontent" cols="40" rows="5"></textarea>
			</div>
		
			<p id="ef-replysubmit">
				<a class="ef-replysave button-primary alignright" href="#comments-form">
					<span id="ef-replybtn"><?php _e('Submit Response', 'edit-flow') ?></span>
				</a>
				<a class="ef-replycancel button-secondary alignright" href="#comments-form"><?php _e( 'Cancel', 'edit-flow' ); ?></a>
				<img alt="Sending comment..." src="<?php echo admin_url('/images/wpspin_light.gif') ?>" class="alignright" style="display: none;" id="ef-comment_loading" />
				<br class="clear" style="margin-bottom:35px;" />
				<span style="display: none;" class="error"></span>
			</p>
		
			<input type="hidden" value="" id="ef-comment_parent" name="ef-comment_parent" />
			<input type="hidden" name="ef-post_id" id="ef-post_id" value="<?php echo $post->ID; ?>" />
			
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
			$actions['reply'] = '<a onclick="editorialCommentReply.open(\''.$comment->comment_ID.'\',\''.$comment->comment_post_ID.'\');return false;" class="vim-r hide-if-no-js" title="'.__( 'Reply to this comment', 'edit-flow' ).'" href="#">' . __( 'Reply', 'edit-flow' ) . '</a>';
			
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
						<?php comment_author_email_link($comment->comment_author) ?>
					</span>
					<span class="meta">
						<?php printf( __(' said on %s at %s', 'edit-flow'), get_comment_date( get_option('date_format') ), get_comment_time() ); ?>
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
			die( __( "Nonce check failed. Please ensure you're supposed to be adding editorial comments.", 'edit-flow' ) );
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
			    'comment_approved' => $this->comment_type,
			);
			
			apply_filters( 'ef_pre_insert_editorial_comment', $data );
			
			// Insert Comment
			$comment_id = wp_insert_comment($data);
			$comment = get_comment($comment_id);
			
			// Register actions -- will be used to set up notifications
			if ( $comment_id ) {
				do_action( 'ef_post_insert_editorial_comment', $comment );
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
			die( __('There was a problem of some sort. Try again or contact your administrator.', 'edit-flow') );
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
			<p><?php _e( 'Notifications are disabled. You won\'t be able to add new subscriptions to this post.', 'edit-flow' ); ?></p>
			<?php
			return;
		}
		
		// Only show on posts that have been saved
		if( in_array( $post->post_status, array( 'new', 'auto-draft' ) ) ) {
			?>
			<p><?php _e( 'Subscribers can be added to a post after the post has been saved for the first time.', 'edit-flow' ); ?></p>
			<?php
			return;
		}
		
		$followers = ef_get_following_users( $post->ID, 'id' );
		
		if( !is_array($followers) ) $followers = (array) $followers;
		$following_usergroups = ef_get_following_usergroups($post->ID, 'slugs');
		
		$user_form_args = array();
		
		$usergroups_form_args = array();
		?>
		<div id="ef-post_following_box">
			<a name="subscriptions"></a>

			<p><?php _e( 'Select the users and usergroups that should receive notifications when the status of this post is updated or when an editorial comment is added.', 'edit-flow' ); ?></p>
			<div id="ef-post_following_users_box">
				<h4><?php _e( 'Users', 'edit-flow' ); ?></h4>
				<?php //$this->select_all_button( "following_users" ); ?>
				<?php ef_users_select_form($followers, $user_form_args); ?>
			</div>
			
			<div id="ef-post_following_usergroups_box">
				<h4><?php _e('User Groups', 'edit-flow') ?></h4>
				<?php //$this->select_all_button( "following_usergroups" ); ?>
				<?php ef_usergroups_select_form($following_usergroups, $usergroups_form_args); ?>
			</div>
			<div class="clear"></div>
			<input type="hidden" name="ef-save_followers" value="1" /> <?php // Extra protection against autosaves ?>
		</div>
		
		<script>
			jQuery(document).ready(function(){
				jQuery('#ef-post_following_box ul').listFilterizer();
			});
		</script>
		
		<?php
	}

	function select_all_button( $id ) {
	?>
		<label class="ef-select_all_box">
			<span><?php _e( 'Select All', 'edit-flow' ); ?> </span>
			<input type="checkbox" id="<?php echo $id;?>" class="follow_all" />
		</label>
	<?php
	}
}

} // END: !class_exists('EF_Post_Metadata')

/**
 * @class post_status
 * Main class that handles post status on Edit Post pages 
 */
if ( !class_exists('EF_Post_Status') ) {

class EF_Post_Status
{
	
	function __construct() {
		
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_scripts', array( &$this, 'post_admin_header' ) );
		add_action( 'admin_notices', array( &$this, 'no_js_notice' ) );		
		
	} // END: __construct()
	
	/**
	 * admin_enqueue_scripts()
	 * Enqueue our admin scripts
	 */
	function admin_enqueue_scripts() {
		if( $this->is_whitelisted_page() ) {
			// Enqueue custom_status.js
			wp_enqueue_script( 'edit_flow-custom_status', EDIT_FLOW_URL.'js/custom_status.js', array('jquery','post'), EDIT_FLOW_VERSION, true );
		}
	}
	
	function is_whitelisted_page() {
		global $edit_flow, $pagenow;
		
		$post_type = $edit_flow->get_current_post_type();
		
		if( !post_type_supports( $post_type, 'ef_custom_statuses' ) )
			return;
		
		if( ! current_user_can('edit_posts') )
			return;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		return in_array( $pagenow, array( 'post.php', 'edit.php', 'post-new.php', 'page.php', 'edit-pages.php', 'page-new.php' ) );
	}
	
	/**
	 * Displays a notice to users if they have JS disabled
	 */
	function no_js_notice() {
		if( $this->is_whitelisted_page() ) :
			?>
			<style type="text/css">
			/* Hide post status dropdown by default in case of JS issues **/
			label[for=post_status],
			#post-status-display,
			#post-status-select {
				display: none;
			}
			</style>		
			<div class="update-nag hide-if-js">
				<?php _e( '<strong>Note:</strong> Your browser does not support JavaScript or has JavaScript disabled. You will not be able to access or change the post status.', 'edit-flow' ); ?>
			</div>
			<?php
		endif;
	}
	
	/**
	 * post_admin_header()
	 * Adds all necessary javascripts to make custom statuses work
	 *
	 * @todo Support private and future posts on edit.php view
	 */
	function post_admin_header() {
		global $post, $edit_flow, $pagenow, $current_user;
		
		// declare variables
		$is_bulkable = false;
		$em_dash_char = "";
		
		// Get current user
		get_currentuserinfo() ;
		
		// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
		if ( !empty( $post ) && $this->is_whitelisted_page() ) {
			
			$custom_statuses = $edit_flow->custom_status->get_custom_statuses();
			$custom_statuses = apply_filters( 'ef_custom_status_list', $custom_statuses, $post );			
	
			// Get the status of the current post		
			if ( $post->ID == 0 || $post->post_status == 'auto-draft' || $pagenow == 'edit.php' ) {
				// TODO: check to make sure that the default exists
				$selected = $edit_flow->get_plugin_option('custom_status_default_status');

			} else {
				$selected = $post->post_status;
			}

			// All right, we want to set up the JS var which contains all custom statuses
			$all_statuses = array(); 
			
			// The default statuses from WordPress
			$all_statuses[] = array(
				'name' => __( 'Published', 'edit-flow' ),
				'slug' => 'publish',
				'description' => '',
			);
			$all_statuses[] = array(
				'name' => __( 'Privately Published', 'edit-flow' ),
				'slug' => 'private',
				'description' => '',
			);
			$all_statuses[] = array(
				'name' => __( 'Scheduled', 'edit-flow' ),
				'slug' => 'future',
				'description' => '',
			);

			// Load the custom statuses
			foreach( $custom_statuses as $status ) {
				$all_statuses[] = array(
					'name' => esc_js( $status->name ),
					'slug' => esc_js( $status->slug ),
					'description' => esc_js( $status->description ),
				);
			}
			
			// Now, let's print the JS vars
			?>
			<script type="text/javascript">
				var custom_statuses = <?php echo json_encode( $all_statuses ); ?>;
				var ef_text_no_change = '<?php _e( "&mdash; No Change &mdash;" ); ?>';
				var ef_default_custom_status = '<?php $edit_flow->get_plugin_option("custom_status_default_status"); ?>';
				var current_status = '<?php echo $selected ?>';
				var status_dropdown_visible = <?php echo (int) $edit_flow->get_plugin_option('status_dropdown_visible') ?>;
				var current_user_can_publish_posts = <?php if ( current_user_can('publish_posts') ) { echo 1; } else { echo 0; } ?>;
			</script>
			
			<?php

		}
		
	} // END: post_admin_header()
	
} // END: class EF_Post_Status

} // END: !class_exists('EF_Post_Status')

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
	elseif( ! empty( $status ) )
		$approved = $wpdb->prepare( "comment_approved = %s", $status );
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
