<?php
 
// Functions related to hooking into custom statuses will go here

class ef_notifications {
	
	// Taxonomy name used to store users following posts
	var $following_users_taxonomy = 'following_users';
	// Taxonomy name used to store users that have opted out of notifications
	var $unfollowing_users_taxonomy = 'unfollowing_users';
	// Taxonomy name used to store user groups following posts
	var $following_usergroups_taxonomy = 'following_usergroups';	
	
	/**
	 * Constructor
	 */
	function __construct ( $active = 1) {
		global $edit_flow;
		
		// Register new taxonomy used to track which users are following posts 
		if(!is_taxonomy($this->following_users_taxonomy)) register_taxonomy( $this->following_users_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false) );
		// Register new taxonomy used to track which users are UNfollowing posts 
		if(!is_taxonomy($this->unfollowing_users_taxonomy)) register_taxonomy( $this->unfollowing_users_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false) );
		// Register new taxonomy used to track which usergroups are following posts 
		if(!is_taxonomy($this->following_usergroups_taxonomy)) register_taxonomy( $this->following_usergroups_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false) );
		
		if( $active ) {
			
			// Notification for post status change
			add_action('transition_post_status', array( &$this, 'notification_status_change'), 10, 3 );
			
			// Notification for new comment
			add_action( 'editflow_comment', array( &$this, 'notification_comment') );
			
			add_action( 'save_post', array( &$this, 'save_post' ) );
			
			// Action to reassign posts when a user is deleted
			add_action( 'delete_user',  array(&$this, 'delete_user_action') );
		}
		
	} // END: __construct()
	
	/**
	 *
	 */
	function notification_status_change ($new_status, $old_status, $post) {
		global $current_user, $edit_flow;
		
		// No need to notify if it's a revision
		// Also no need to email if post status wasn't changed
		if ($new_status != 'inherit' && $old_status != $new_status) {
			
			// Get current user
			get_currentuserinfo();
			
			$post_author = get_userdata( $post->post_author );
			$description = $edit_flow->post_metadata->get_post_meta($post->ID, 'description', true);
			$duedate = $edit_flow->post_metadata->get_post_meta($post->ID, 'duedate', true);
			$location = $edit_flow->post_metadata->get_post_meta($post->ID, 'location', true);
			
			$blogname = get_option('blogname');
			
			$body  = '';
			
			$post_id = $post->ID;
			$post_title = $post->post_title;
			$post_type = ucwords($post->post_type);
			$current_user_display_name = $current_user->display_name;
			$current_user_email = $current_user->user_email;
			
			// Email subject and first line of body 
			// Set message subjects according to what action is being taken on the Post			
			if ($old_status == 'new') {
				$subject = sprintf( __('[%1$s] New %2$s Created: "%3$s"'), $blogname, $post_type, $post_title );
				$body .= sprintf( __('A new %1$s (#%2$s "%3$s") was created by %4$s (%5$s)'), $post_type, $post_id, $post_title, $current_user->display_name, $current_user->user_email ) . "\r\n";
			} else if ( $new_status == 'publish') {
				$subject = sprintf( __('[%1$s] %2$s Published: "%3$s"'), $blogname, $post_type, $post_title );
				$body .= sprintf( __('%1$s #%2$s "%3$s" was published by %4$s (%5$s)'), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else if ( $old_status == 'publish') {
				$subject = sprintf( __('[%1$s] %2$s Unpublished: "%3$s"'), $blogname, $post_type, $post_title );
				$body .= sprintf( __('%1$s #%2$s "%3$s" was unpublished by %4$s (%5$s)'), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else {
				$subject = sprintf( __('[%1$s] %2$s Status Changed for "%3$s"'), $blogname, $post_type, $post_title );
				$body .= sprintf( __('Status was changed on %1$s #%2$s "%3$s" by %4$s (%5$s)'), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			}
			
			// Email body
			$body .= "\r\n";
			// This is funky and causes layout issues so I changed it something simpler. Once we move to HTML emails, it'll be far more awesome.
			//$body .= __('Prev Status:') . ' ' . $old_status . "\r\n";
			//$body .= 	'			|| '  . "\r\n";
			//$body .= 	'			\/ '  . "\r\n";
			//$body .= __('New Status:')  . ' ' . $new_status;
			$body .= $old_status  . ' => ' .  $new_status;
			$body .= "\r\n\r\n";
			
			$body .= "--------------------\r\n";
			
			$body .= sprintf( __('%s Details:'), $post_type) . "\r\n";
			$body .= sprintf( __('Title: %s'), $post_title ) . "\r\n";
			$body .= sprintf( __('Author: %1$s (%2$s)'), $post_author->display_name, $post_author->user_email ) . "\r\n";
			$body .= sprintf( __('Due Date: %s'), ($duedate) ? date('M j, Y', $duedate) : __('Not assigned') ) . "\r\n";
			$body .= sprintf( __('Description: %s'), ($description) ? $description : __('Not assigned') ) . "\r\n";
			$body .= sprintf( __('Location: %s'), ($location) ? $location : __('Not assigned') ) . "\r\n";
			
			if( current_user_can('edit_post', $post_id) ) {
				$edit_link = htmlspecialchars_decode(get_edit_post_link($post_id));
				$view_link = htmlspecialchars_decode(get_permalink($post_id));
				$body .= "\r\n";
				$body .= __('Actions you can take: ') . "\r\n";
				$body .= sprintf( __('Add editorial comment: %s'), $edit_link . '#editorialcomments/add' ) . "\r\n";
				$body .= sprintf( __('Edit %1$s: %2$s'), $post_type, $edit_link ) . "\r\n";
				$body .= sprintf( __('View %1$s: %2$s'), $post_type, $view_link ) . "\r\n";
			}
				
			$body .= $this->get_notification_footer($post);
			
			$this->send_email( 'status-change', $post, $subject, $body );
			return;
		}
	}
	
	/* 
	 * 
	 */
	function notification_comment ( $comment ) {

		$post    = get_post($comment->comment_post_ID);
		$user    = get_userdata( $post->post_author );
		$current_user = wp_get_current_user();
	
		// Check if this a reply
		$parent_ID = $comment->comment_parent_ID;
		if($parent_ID) $parent = get_comment($parent_ID);
		
		// Set user to follow post
		// @TODO: need option to opt-out
		$this->follow_post_user($post, (int) $current_user->ID);
	
		$blogname = get_option('blogname');
	
		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __('[%1$s] New Editorial Comment: "%2$s"'), $blogname, $post->post_title );

		/* translators: 1: post id, 2: post title */
		$body  = sprintf( __('A new editorial comment was added to post #%1$s "%2$s"'), $comment->comment_post_ID, $post->post_title ) . "\r\n\r\n";
		/* translators: 1: comment author, 2: author email, 3: date, 4: time */
		$body .= sprintf( __('%1$s (%2$s) said on %3$s at %4$s:'), $current_user->display_name, $current_user->user_email, mysql2date(get_option('date_format'), $comment->comment_date), mysql2date(get_option('time_format'), $comment->comment_date) ) . "\r\n";
		$body .= "\r\n" . $comment->comment_content . "\r\n";

		// @TODO: mention if it was a reply
		if($parent) {
			
		}
		
		$body .= "\r\n--------------------\r\n";
		
		if( current_user_can('edit_post', $post->ID) ) {
			$edit_link = htmlspecialchars_decode(get_edit_post_link($post->ID));
			$view_link = htmlspecialchars_decode(get_permalink($post->ID));
			
			$body .= "\r\n";
			$body .= __('Actions you can take: ') . "\r\n";
			$body .= sprintf( __('Reply: %s'), $edit_link . '#editorialcomments/reply/' . $comment->comment_ID ) . "\r\n";
			$body .= sprintf( __('Add new comment: %s'), $edit_link . '#editorialcomments/add' ) . "\r\n";
			$body .= sprintf( __('Edit post: %s'), $edit_link ) . "\r\n";
			$body .= sprintf( __('View post: %s'), $view_link ) . "\r\n";
			
			$body .= "\r\n" . __('You can see all editorial comments on this post here: ') . "\r\n";		
			$body .= $edit_link . "#editorialcomments" . "\r\n\r\n";
			
		}
		
		$body .= $this->get_notification_footer($post);
		
		$this->send_email( 'comment', $post, $subject, $body );
		return;
	}
	
	function get_notification_footer( $post ) {
		$body  = "";
		$body .= "\r\n--------------------\r\n";
		$body .= "The following email has been sent to you because you are following the post \"$post->post_title\"";
		$body .= "\r\n \r\n";
		$body .= get_option('blogname') ." | ". get_bloginfo('url') . " | " . admin_url('/') . "\r\n";
		return $body;
	} 
	
	/*
	 *
	 */
	function send_email( $action, $post, $msg_subject, $msg_body  ) {
	
		// Set To: to admin email
		$admin_email = get_option('admin_email');
		
		// Get list of email recipients -- set them CC		
		$recipients = $this->_get_notification_recipients($post, true);
		
		$blogname = get_option('blogname');
		$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
		
		/*
		$msg_headers = array( 
			// @TODO: Make this configurable
			//'From' => $edit_flow->get_option('from_name') .' <'. $edit_flow->get_option('from_email') .'>',
			'from' => " WordPress <$wp_email>",
			'reply-to' => "$admin_email",
			'bcc' => $recipients,
		);
		*/
		$msg_headers  = '';
		//$msg_headers .= 'From: "WordPress" <'. $wp_email .'> \r\n';
		//$msg_headers .= 'Reply-to: '. $admin_email .'\r\n';
		if($recipients) $msg_headers .= 'Bcc: '. $recipients ." \n";
		
		$return = @wp_mail( $admin_email, $msg_subject, $msg_body, $msg_headers);
		
		// @TODO Logging for failed notifications
		//if(!$return) $this->log_error();
		return $return;
	}
	
	/* Returns a list of recipients for a given post
	 * @param $post object
	 * @param $string bool Whether to return recipients as comma-delimited string or array 
	 * @return string or array of recipients to receive notification 
	 */
	private function _get_notification_recipients( $post, $string = false ) {
		global $edit_flow;
		
		$post_id = $post->ID;
		if( !$post_id ) return;
		
		$authors = array();
		$admins = array();
		$recipients = array();
		
		// Email author(s) (setting needs to be enabled)
		$authors[] = get_userdata($post->post_author)->user_email;
		
		// Email all admins, if enabled
		$notify_admins = $edit_flow->get_plugin_option('always_notify_admin');
		if( $notify_admins ) $admins[] = get_option('admin_email');
		
		// Get following users and usergroups
		$usergroups = ef_get_following_usergroups($post_id, 'slugs');
		$users = ef_get_following_users($post_id);

		// Set up args for the user search
		$user_query_vars = array('return_fields' => 'user_email');		
		if( is_array($users) && !empty($users) )
			$user_query_vars['search_fields'] = array('user_login' => $users);
		if( is_array($usergroups) && !empty($usergroups) )
			$user_query_vars['usermeta'] = array(EDIT_FLOW_USERGROUPS_USERMETA => $usergroups);
		
		// Inst. the search class and get results
		$user_query = new EF_User_Query($user_query_vars);
		$following_recipients = $user_query->get_results();
		
		if( is_wp_error($following_recipients) || !is_array($following_recipients) ) {
			$following_recipients = array();
		}

		// Merge arrays and get rid of duplicates
		$recipients = array_merge($authors, $admins, $following_recipients);
		$recipients = array_unique($recipients);
		
		// Get rid of empty email entries
		for( $i = 0; $i < count( $recipients ); $i++ ) {
			if( empty( $recipients[$i] ) ) unset( $recipients[$i] );
		}

		// If string set to true, return comma-delimited
		if($string && is_array($recipients)) {
			return implode(',', $recipients);
		} else {
			return $recipients;
		}
	}
	
	/**
	 * Personalized notification RSS feeds
	 * To come in future versions! STAY TUNED!
	 * 
	 */
	// TODO: build admin page -- currently using options
	/*
	function rss_feed( ) {
		// Authenticate based on private key stored individually per user
		// Will need activity stream built before this
	}
	*/
		
	/**
	 * Function called when post is saved. Handles saving of user/usergroup followers
	 * @param int $post ID of the post
	 */
	function save_post ( $post ) {
				
		// only if has edit_post_subscriptions cap
		if( ( !wp_is_post_revision($post) && !wp_is_post_autosave($post) ) && isset($_POST['ef-save_followers']) && current_user_can('edit_post_subscriptions') ) {
			$users = $_POST['following_users'];
			$usergroups = $_POST['following_usergroups'];
			$this->save_post_following_users($post, $users);
			$this->save_post_following_usergroups($post, $usergroups);
		}
		
	}
	
	/**
	 * Sets users to follow specified post
	 * @param int $post ID of the post
	 */
	function save_post_following_users ( $post, $users = null ) {
		if( !is_array($users) ) $users = array();
		$users = array_map( 'intval', $users );
		
		$follow = $this->follow_post_user($post, $users, false);
		
	}
	
	/**
	 * Sets usergroups to follow specified post
	 * @param int $post ID of the post
	 */
	function save_post_following_usergroups ( $post, $usergroups = null ) {
		
		if( !is_array($usergroups) ) $usergroups = array();
		$usergroups = array_map( 'sanitize_title', $usergroups );

		$follow = $this->follow_post_usergroups($post, $usergroups, false);
	}
	
	/**
	 * Set set user to follow posts
	 * @param $post Post object
	 * @param $users array (optional) List of users to be added. If not included, the current user is added.
	 * @param $append bool Whether users should be added to following_users list or replace existing list
	 */
	function follow_post_user( $post, $users, $append = true ) {

		// Clean up data we're using
		$post_id = ( is_int($post) ) ? $post : $post->ID;
		if( !is_array($users) ) $users = array($users);

		$user_terms = array();
		
		foreach( $users as $user ) {
			if( is_int($user) ) 
				$user_data = get_userdata($user);
			else if( is_string($user) )
				$user_data = get_userdatabylogin($user);
			else
				$user_data = $user;
			
			if( $user_data ) {
				// Name and slug of term are the username;
				$name = $user_data->user_login;
				
				/** TODO: ONLY ADD IF USER IS NOT PART OF $this->exclude_users_taxonomy for the post **/
				// if( !user_excluding_post($post_id, $user) )
				
				// Add user as a term if they don't exist
				$term = $this->add_term_if_not_exists($name, $this->following_users_taxonomy);
				
				if(!is_wp_error($term)) {
					$user_terms[] = $name;
				}
			}
		}
		$set = wp_set_object_terms( $post_id, $user_terms, $this->following_users_taxonomy, $append );
		
		return;
	}

	/**
	 * Removes user from following_users taxonomy for the given Post, so they no longer receive future notifications
	 * Called when delete_user action is fired
	 * @param $post Post object
	 */
	 function unfollow_post_user( $post, $user = 0 ) {
		global $current_user;
		
		// TODO: Finish this
		
		$post_id = $post->ID;
		//if(!$user) $user = wp_get_current_user();

        if(!$post_id || !$user || $user->ID == 0)
			return;
			
		// Name and slug of term are the username;  
		$name = $user->user_login;
		
		// Remove the user from the following_users taxonomy
		if( is_term($name, $this->following_users_taxonomy) ) {
			$set = wp_set_object_terms( $post->ID, $name, $this->following_users_taxonomy, true );
			$old_term_ids =  wp_get_object_terms($post_id, $this->following_users_taxonomy, array('fields' => 'ids', 'orderby' => 'none'));
	
/*			
			$delete_terms = array_diff($old_tt_ids, $tt_ids);
			if ( $delete_terms ) {
				$in_delete_terms = "'" . implode("', '", $delete_terms) . "'";
				do_action( 'delete_term_relationships', $object_id, $delete_terms );
				$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id IN ($in_delete_terms)", $object_id) );
				do_action( 'deleted_term_relationships', $object_id, $delete_terms );
				wp_update_term_count($delete_terms, $taxonomy);
			}
*/
		}
		
		// Add user to the unfollowing_users taxonomy
		$insert = $this->add_term_if_not_exists($name, $this->unfollowing_users_taxonomy);
		
		if(!is_wp_error($insert)) {
			$exclude = wp_set_object_terms( $post_id, $name, $this->unfollowing_users_taxonomy, true );
		}
		
		return;
	}

	/** 
	 *
	 */
	function follow_post_usergroups ( $post, $usergroups = 0, $append = true ) {
		
		$post_id = ( is_int($post) ) ? $post : $post->ID;
		if( !is_array($usergroups) ) $usergroups = array($usergroups);

		$usergroup_terms = array();
		
		foreach( $usergroups as $usergroup ) {

			// Name and slug of term is the usergroup slug;
			$usergroup_data = ef_get_usergroup($usergroup);
			if( $usergroup_data ) {
				$name = $usergroup_data->slug;
				
				// Add usergroup as a term if they don't exist
				$term = $this->add_term_if_not_exists($name, $this->following_usergroups_taxonomy);
				
				if(!is_wp_error($term)) {
					$usergroup_terms[] = $name;
				}
			}
		}
		$set = wp_set_object_terms( $post_id, $usergroup_terms, $this->following_usergroups_taxonomy, $append );
		return;
	}
	
	/** 
	 *
	 */
	function unfollow_post_usergroups ( $post_id, $users = 0 ) {
		
		// TODO: Allow opt-out of email
		
		return;
	}
	
	/**
	 * Removes users that are deleted from receiving future notifications (i.e. makes them unfollow posts FOREVER!)
	 * @param $id int ID of the user
	 */
	function delete_user_action( $id ) {
		if( !$id ) return;
		
		// get user data
		$user = get_userdata($id);
		
		if( $user ) {
			// Delete term from the following_users taxonomy
			$user_following_term = get_term_by('name', $user->user_login, $this->following_users_taxonomy);
			if( $user_following_term ) wp_delete_term($user_following_term->term_id, $this->following_users_taxonomy);
			// Delete term from the unfollowing_users taxonomy
			$user_unfollowing_term = get_term_by('name', $user->user_login, $this->unfollowing_users_taxonomy);
			if( $user_unfollowing_term ) wp_delete_term($user_unfollowing_term->term_id, $this->unfollowing_users_taxonomy);
		}
		return;
	}
		
	/**
	 * Add user as a term if they aren't already
	 * @param $term string term to be added
	 * @param $taxonomy string taxonomy to add term to
	 * @return WP_error if insert fails, true otherwise
	 */
	function add_term_if_not_exists( $term, $taxonomy ) {
		if( !is_term($term, $taxonomy) ) {
			$args = array( 'slug' => sanitize_title($term) );		
			return wp_insert_term( $term, $taxonomy, $args );
		} 
		return true;
	}
	
} // END: class ef_notifications

/**
 * Gets a list of the users following this post
 * @param $post_id string term to be added
 * @param $return string taxonomy to add term to
 * @return array of users
 */
function ef_get_following_users ( $post_id, $return = 'user_login' ) {
	global $edit_flow;

	$users = wp_get_object_terms($post_id, $edit_flow->notifications->following_users_taxonomy, array('fields' => 'names'));

	if( is_wp_error($users) || empty($users) ) return false;
	
	$args = array( 'search_fields' => array('user_login' => $users) );

	switch( $return ) {
		case 'id':
			// get just ids; don't need to set anything since query class handles it all
			break;
			
		case 'all':
			$args['return_fields'] = 'all';			
			break;
		
		case 'email':
			// get just emails
			$args['return_fields'] = 'user_email';
			break;
			
		case 'user_login':
		default:
			return $users; // no action taken since our object terms return is already user_login form
			break;
	}
	// Create user query obj and get results
	$search = new EF_User_Query($args);
	$results = $search->get_results();
	if( !$results || is_wp_error($results) )
		return false;
	return $results;
}

/**
 * Gets a list of the usergroups that are following specified post
 * @param $post_id string term to be added
 * @return array of usergroup slugs
 */
function ef_get_following_usergroups ( $post_id, $return = 'all' ) {
	global $edit_flow;
	
	// Workaround for the fact that get_object_terms doesn't return just slugs
	if( $return == 'slugs' )
		$fields = 'all';
	else
		$fields = $return;
	
	$usergroups = wp_get_object_terms($post_id, $edit_flow->notifications->following_usergroups_taxonomy, array('fields' => $fields));
	
	if( $return == 'slugs' ) {
		$slugs = array();
		foreach($usergroups as $usergroup) {
			$slugs[] = $usergroup->slug; 	
		}
		$usergroups = $slugs;
	}
	return $usergroups;
}

/**
 * Gets a list of posts that a user is following
 * @param $user string|int user_login or id of user
 * @param $args  
 * @return array of posts
 */
function ef_get_user_following_posts ( $user = 0, $args = null ) {
	global $edit_flow;
	if( !$user ) $user = (int) wp_get_current_user()->ID;
	
	if( is_int($user) ) $user = get_userdata($user)->user_login;
	
	$post_args = array(
		$edit_flow->notifications->following_users_taxonomy => $user,
		'posts_per_page' => '10',
		'orderby' => 'modified',
		'order' => 'DESC',
	);
	$posts = get_posts($post_args);
	return $posts;
}

function ef_is_user_following_post( $post, $user ) {
	/*
	if( is_int($user) ) $user = get_user( $user )->login;
	if( !$user ) return false;
	
	$user_following = //post has term?
	
	if( $user_following ) return true;
	*/
	// TODO: Finish function
	return false;
}
function ef_is_user_unfollowing_post( ) {
	// TODO: Finish function
	return false;
}

?>