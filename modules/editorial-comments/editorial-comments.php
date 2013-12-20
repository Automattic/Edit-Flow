<?php
/**
 * class EF_Editorial_Comments
 * Threaded commenting in the admin for discussion between writers and editors
 *
 * @author batmoo
 */

if ( !class_exists( 'EF_Editorial_Comments' ) ) {

class EF_Editorial_Comments extends EF_Module
{
	// This is comment type used to differentiate editorial comments
	const comment_type = 'editorial-comment';
	
	function __construct() {
		global $edit_flow;
		
		$this->module_url = $this->get_module_url( __FILE__ );
		// Register the module with Edit Flow
		$args = array(
			'title' => __( 'Editorial Comments', 'edit-flow' ),
			'short_description' => __( 'Share internal notes with your team.', 'edit-flow' ),
			'extended_description' => __( 'Use editorial comments to hold a private discussion about a post. Communicate directly with your writers or editors about what works and what needs to be improved for each piece.', 'edit-flow' ),
			'module_url' => $this->module_url,
			'img_url' => $this->module_url . 'lib/editorial_comments_s128.png',
			'slug' => 'editorial-comments',
			'default_options' => array(
				'enabled' => 'on',
				'post_types' => array(
					'post' => 'on',
					'page' => 'on',
				),
			),
			'configure_page_cb' => 'print_configure_view',
			'configure_link_text' => __( 'Choose Post Types', 'edit-flow' ),
			'autoload' => false,
			'settings_help_tab' => array(
				'id' => 'ef-editorial-comments-overview',
				'title' => __('Overview', 'edit-flow'),
				'content' => __('<p>Editorial comments help you cut down on email overload and keep the conversation close to where it matters: your content. Threaded commenting in the admin, similar to what you find at the end of a blog post, allows writers and editors to privately leave feedback and discuss what needs to be changed before publication.</p><p>Anyone with access to view the story in progress will also have the ability to comment on it. If you have notifications enabled, those following the post will receive an email every time a comment is left.</p>', 'edit-flow'),
				),
			'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/editorial-comments/">Editorial Comments Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
		);
		$this->module = EditFlow()->register_module( 'editorial_comments', $args );
	}

	/**
	 * Initialize the rest of the stuff in the class if the module is active
	 */	
	function init() {

		add_action( 'add_meta_boxes', array ( $this, 'add_post_meta_box' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );		
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );
		add_action( 'wp_ajax_editflow_ajax_insert_comment', array( $this, 'ajax_insert_comment' ) );

		// Add Editorial Comments to the calendar if the calendar is activated
		if ( $this->module_enabled( 'calendar' ) ) {
			// Still in progress. See: https://www.pivotaltracker.com/story/show/5930884 and https://www.pivotaltracker.com/story/show/5930895
			//add_filter( 'ef_calendar_item_information_fields', array( $this, 'filter_calendar_item_fields' ), null, 2 );
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
			// Technically we've run this code before so we don't want to auto-install new data
			$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
		}
		
	}
	
	/**
	 * Load any of the admin scripts we need but only on the pages we need them
	 */
	function add_admin_scripts( ) {
		global $pagenow;
		
		$post_type = $this->get_current_post_type();
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		if ( !in_array( $post_type, $supported_post_types ) )
			return;
			
		if ( !in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'page-new.php' ) ) )
			return;
		
		wp_enqueue_script( 'edit_flow-post_comment', $this->module_url . 'lib/editorial-comments.js', array( 'jquery','post' ), EDIT_FLOW_VERSION, true );
		wp_enqueue_style( 'edit-flow-editorial-comments-css', $this->module_url . 'lib/editorial-comments.css', false, EDIT_FLOW_VERSION, 'all' );
				
		$thread_comments = (int) get_option('thread_comments');
		?>
		<script type="text/javascript">
			var ef_thread_comments = <?php echo ($thread_comments) ? $thread_comments : 0; ?>;
		</script>
		<?php
		
	}
	
	/**
	 * Add the editorial comments metabox to enabled post types
	 * 
	 * @uses add_meta_box()
	 */
	function add_post_meta_box() {
		
		$supported_post_types = $this->get_post_types_for_module( $this->module );
		foreach ( $supported_post_types as $post_type )
			add_meta_box('edit-flow-editorial-comments', __('Editorial Comments', 'edit-flow'), array($this, 'editorial_comments_meta_box'), $post_type, 'normal' );
			
	}
	
	/**
	 * Get the total number of editorial comments for a post
	 *
	 * @param int $id Unique post ID
	 * @return int $comment_count Number of editorial comments for a post
	 */
	function get_editorial_comment_count( $id ) {
		global $wpdb; 
		$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s", $id, self::comment_type));
		if ( !$comment_count )
			$comment_count = 0;
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
									'comment_type' => self::comment_type,
									'orderby' => 'comment_date',
									'order' => 'ASC',
									'status' => self::comment_type
								)
							);
				?>
					
				<ul id="ef-comments">
					<?php 
						// We use this so we can take advantage of threading and such
						
						wp_list_comments(
							array(
								'type' => self::comment_type,
								'callback' => array($this, 'the_comment'),
								'end-callback' => '__return_false'
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
	
	/**
	 * Displays the main commenting form
	 */
	function the_comment_form( ) {
		global $post;
		
		?>
		<a href="#" id="ef-comment_respond" onclick="editorialCommentReply.open();return false;" class="button-primary alignright hide-if-no-js" title=" <?php _e( 'Respond to this post', 'edit-flow' ); ?>"><span><?php _e( 'Respond to this post', 'edit-flow' ); ?></span></a>
		
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
			<input type="hidden" name="ef-post_id" id="ef-post_id" value="<?php echo esc_attr( $post->ID ); ?>" />
			
			<?php wp_nonce_field('comment', 'ef_comment_nonce', false); ?>
			
			<br class="clear" />
		</div>

		<?php
	}
	
	/**
	 * Displays a single comment
	 */
	function the_comment($comment, $args, $depth) {
		global $current_user, $userdata;
		
		// Get current user
		get_currentuserinfo() ;
		
		$GLOBALS['comment'] = $comment;

		// Deleting editorial comments is not enabled for now for the sake of transparency. However, we could consider
		// EF comment edits (with history, if possible). P2 already allows for edits without history, so even that might work.
		// Pivotal ticket: https://www.pivotaltracker.com/story/show/18483757
		//$delete_url = esc_url( wp_nonce_url( "comment.php?action=deletecomment&p=$comment->comment_post_ID&c=$comment->comment_ID", "delete-comment_$comment->comment_ID" ) );
	
		$actions = array();
	
		$actions_string = '';
		// Comments can only be added by users that can edit the post
		if ( current_user_can('edit_post', $comment->comment_post_ID) ) {
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

		<li id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status($comment->comment_ID) ) ); ?>>
		
			<?php echo get_avatar( $comment->comment_author_email, 50 ); ?>

			<div class="post-comment-wrap">
				<h5 class="comment-meta">
					<?php printf( __('<span class="comment-author">%1$s</span><span class="meta"> said on %2$s at %3$s</span>', 'edit-flow'),
							comment_author_email_link( $comment->comment_author ),
							get_comment_date( get_option( 'date_format' ) ),
							get_comment_time() ); ?>
				</h5>
	
				<div class="comment-content"><?php comment_text(); ?></div>
				<p class="row-actions"><?php echo $actions_string; ?></p>
	
			</div>
		</li>	
		<?php
	}
		
	/**
	 * Handles AJAX insert comment
	 */
	function ajax_insert_comment( ) {
		global $current_user, $user_ID, $wpdb;
		
		// Verify nonce
		if ( !wp_verify_nonce( $_POST['_nonce'], 'comment') )
			die( __( "Nonce check failed. Please ensure you're supposed to be adding editorial comments.", 'edit-flow' ) );
		
		// Get user info
      	get_currentuserinfo();
      	
      	// Set up comment data
		$post_id = absint( $_POST['post_id'] );
		$parent = absint( $_POST['parent'] );
      	
      	// Only allow the comment if user can edit post
      	// @TODO: allow contributers to add comments as well (?)
		if ( ! current_user_can( 'edit_post', $post_id ) )
			die( __('Sorry, you don\'t have the privileges to add editorial comments. Please talk to your Administrator.', 'edit-flow' ) );
		
		// Verify that comment was actually entered
		$comment_content = trim($_POST['content']);
		if( !$comment_content )
			die( __( "Please enter a comment.", 'edit-flow' ) );
		
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
			    'comment_type' => self::comment_type,
			    'comment_parent' => (int) $parent,
			    'user_id' => (int) $user_ID,
			    'comment_author_IP' => esc_sql($_SERVER['REMOTE_ADDR']),
			    'comment_agent' => esc_sql($_SERVER['HTTP_USER_AGENT']),
			    'comment_date' => $time,
			    'comment_date_gmt' => $time,
				// Set to -1?
			    'comment_approved' => self::comment_type,
			);
			
			apply_filters( 'ef_pre_insert_editorial_comment', $data );
			
			// Insert Comment
			$comment_id = wp_insert_comment($data);
			$comment = get_comment($comment_id);
			
			// Register actions -- will be used to set up notifications and other modules can hook into this
			if ( $comment_id )
				do_action( 'ef_post_insert_editorial_comment', $comment );

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
	 * Register settings for editorial comments so we can partially use the Settings API
	 * (We use the Settings API for form generation, but not saving)
	 * 
	 * @since 0.7
	 */
	function register_settings() {
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Enable for these post types:', 'edit-flow' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
	}

	/**
	 * Chose the post types for editorial comments
	 *
	 * @since 0.7
	 */
	function settings_post_types_option() {
		global $edit_flow;
		$edit_flow->settings->helper_option_custom_post_type( $this->module );	
	}

	/**
	 * Validate our user input as the settings are being saved
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
	 * Settings page for editorial comments
	 *
	 * @since 0.7	
	 */
	function print_configure_view() {
		?>

		<form class="basic-settings" action="<?php echo add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>
			<?php
				echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';				
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo EDIT_FLOW_SETTINGS_PAGE; ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>

		</form>
		<?php
	}

	/**
	 * If the Edit Flow Calendar is enabled, add the editorial comment count to the post overlay.
	 *
	 * @since 0.7
	 * @uses apply_filters( 'ef_calendar_item_information_fields' )
	 *
	 * @param array $calendar_fields Additional data fields to include on the calendar
	 * @param int $post_id Unique ID for the post data we're building
	 * @return array $calendar_fields Calendar fields with our viewable Editorial Metadata added
	 */
	function filter_calendar_item_fields( $calendar_fields, $post_id ) {
		// Make sure we respect which post type we're on
		if ( !in_array( get_post_type( $post_id ), $this->get_post_types_for_module( $this->module ) ) )
			return $calendar_fields;

		// Name/value for the field to add
		$comment_count_data = array(
			'label' => $this->module->title,
			'value' => $this->get_editorial_comment_count( $post_id ),
		);

		$calendar_fields[$this->module->slug] = $comment_count_data;

		return $calendar_fields;
	}

}

}

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
