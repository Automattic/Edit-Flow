<?php
 
// Functions related to hooking into custom statuses will go here

if( ! defined( 'EF_NOTIFICATION_USE_CRON' ) )
	define( 'EF_NOTIFICATION_USE_CRON', false );

if ( !class_exists('EF_Notifications') ) {

class EF_Notifications {
	
	// Taxonomy name used to store users following posts
	var $following_users_taxonomy = 'following_users';
	// Taxonomy name used to store users that have opted out of notifications
	var $unfollowing_users_taxonomy = 'unfollowing_users';
	// Taxonomy name used to store user groups following posts
	var $following_usergroups_taxonomy = 'following_usergroups';	
	
	/**
	 * Constructor
	 */
	function __construct ( $active = 1 ) {
		global $edit_flow;
		
		// Register new taxonomy used to track which users are following posts 
		if( !ef_taxonomy_exists( $this->following_users_taxonomy ) ) register_taxonomy( $this->following_users_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false, 'show_ui' => false) );
		// Register new taxonomy used to track which users are UNfollowing posts 
		if( !ef_taxonomy_exists( $this->unfollowing_users_taxonomy ) ) register_taxonomy( $this->unfollowing_users_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false, 'show_ui' => false) );
		// Register new taxonomy used to track which usergroups are following posts 
		if( !ef_taxonomy_exists( $this->following_usergroups_taxonomy ) ) register_taxonomy( $this->following_usergroups_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false, 'show_ui' => false) );
		
		if( $active ) {
			
			add_action( 'init', array( &$this, 'init' ) );
			
			// Notification for post status change
			add_action( 'transition_post_status', array( &$this, 'notification_status_change' ), 10, 3 );
			
			// Notification for new comment
			add_action( 'ef_post_insert_editorial_comment', array( &$this, 'notification_comment') );
			
			add_action( 'save_post', array( &$this, 'save_post' ) );
			
			// Action to reassign posts when a user is deleted
			add_action( 'delete_user',  array(&$this, 'delete_user_action') );
			
			// Sends cron-scheduled emails
			add_action( 'ef_send_scheduled_email', array( &$this, 'send_single_email' ), 10, 4 );
		}
		
	} // END: __construct()
	
	/**
	 * init()
	 */
	function init() {
		global $edit_flow;
		foreach( array( 'post', 'page' ) as $post_type ) {
			add_post_type_support( $post_type, 'ef_notifications' );
		}
	} // END: init()
	
	/**
	 * notification_status_change()
	 * Set up and send post status change notification email
	 */
	function notification_status_change( $new_status, $old_status, $post ) {
		global $edit_flow;
		
		// Kill switch for notification
		if ( ! apply_filters( 'ef_notification_status_change', $new_status, $old_status, $post ) || ! apply_filters( "ef_notification_{$post->post_type}_status_change", $new_status, $old_status, $post ) )
			return false;
		
		if( ! post_type_supports( $post->post_type, 'ef_notifications' ) )
			return;
		
		// No need to notify if it's a revision, auto-draft, or if post status wasn't changed
		$ignored_statuses = apply_filters( 'ef_notification_ignored_statuses', array( $old_status, 'inherit', 'auto-draft' ), $post->post_type );
		
		if ( !in_array( $new_status, $ignored_statuses ) ) {
			
			// Get current user
			$current_user = wp_get_current_user();
			
			$post_author = get_userdata( $post->post_author );
			//$duedate = $edit_flow->post_metadata->get_post_meta($post->ID, 'duedate', true);
			
			$blogname = get_option('blogname');
			
			$body  = '';
			
			$post_id = $post->ID;
			$post_title = ef_draft_or_post_title( $post_id );
			$post_type = ucwords( $post->post_type );

			if( 0 != $current_user->ID ) {
				$current_user_display_name = $current_user->display_name;
				$current_user_email = sprintf( '(%s)', $current_user->user_email );
			} else {
				$current_user_display_name = __( 'WordPress Scheduler', 'edit-flow' );
				$current_user_email = '';
			}
			
			// Email subject and first line of body 
			// Set message subjects according to what action is being taken on the Post	
			if ( $old_status == 'new' || $old_status == 'auto-draft' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] New %2$s Created: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( 'A new %1$s (#%2$s "%3$s") was created by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user->display_name, $current_user->user_email ) . "\r\n";
			} else if ( $new_status == 'trash' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] %2$s Trashed: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( '%1$s #%2$s "%3$s" was moved to the trash by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else if ( $old_status == 'trash' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] %2$s Restored (from Trash): "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( '%1$s #%2$s "%3$s" was restored from trash by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else if ( $new_status == 'future' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __('[%1$s] %2$s Scheduled: "%3$s"'), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( '%1$s #%2$s "%3$s" was scheduled by %4$s %5$s' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else if ( $new_status == 'publish' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] %2$s Published: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( '%1$s #%2$s "%3$s" was published by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else if ( $old_status == 'publish' ) {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] %2$s Unpublished: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( '%1$s #%2$s "%3$s" was unpublished by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			} else {
				/* translators: 1: site name, 2: post type, 3. post title */
				$subject = sprintf( __( '[%1$s] %2$s Status Changed for "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
				/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
				$body .= sprintf( __( 'Status was changed for %1$s #%2$s "%3$s" by %4$s %5$s', 'edit-flow'), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
			}
			
			/* translators: 1: date, 2: time, 3: timezone */
			$body .= sprintf( __( 'This action was taken on %1$s at %2$s %3$s', 'edit-flow' ), date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ), get_option( 'timezone_string' ) ) . "\r\n";
			
			$old_status_friendly_name = $edit_flow->custom_status->get_custom_status_friendly_name( $old_status );
			$new_status_friendly_name = $edit_flow->custom_status->get_custom_status_friendly_name( $new_status );
			
			// Email body
			$body .= "\r\n";
			/* translators: 1: old status, 2: new status */
			$body .= sprintf( __( '%1$s => %2$s', 'edit-flow' ), $old_status_friendly_name, $new_status_friendly_name );
			$body .= "\r\n\r\n";
			
			$body .= "--------------------\r\n\r\n";
			
			$body .= sprintf( __( '== %s Details ==', 'edit-flow' ), $post_type ) . "\r\n";
			$body .= sprintf( __( 'Title: %s', 'edit-flow' ), $post_title ) . "\r\n";
			/* translators: 1: author name, 2: author email */
			$body .= sprintf( __( 'Author: %1$s (%2$s)', 'edit-flow' ), $post_author->display_name, $post_author->user_email ) . "\r\n";
			
			$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
			if ( $new_status != 'publish' ) {
				$preview_nonce = wp_create_nonce( 'post_preview_' . $post_id );
				$view_link = add_query_arg( array( 'preview' => true, 'preview_id' => $post_id, 'preview_nonce' => $preview_nonce ), get_permalink($post_id) );
			} else {
				$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
			}
			$body .= "\r\n";
			$body .= __( '== Actions ==', 'edit-flow' ) . "\r\n";
			$body .= sprintf( __( 'Add editorial comment: %s', 'edit-flow' ), $edit_link . '#editorialcomments/add' ) . "\r\n";
			$body .= sprintf( __( 'Edit: %s', 'edit-flow' ), $edit_link ) . "\r\n";
			$body .= sprintf( __( 'View: %s', 'edit-flow' ), $view_link ) . "\r\n";
				
			$body .= $this->get_notification_footer($post);
			
			$this->send_email( 'status-change', $post, $subject, $body );
		}
		
	} // END: notification_status_change()
	
	/**
	 * notification_comment()
	 * Set up and set editorial comment notification email
	 */
	function notification_comment( $comment ) {
		
		$post = get_post($comment->comment_post_ID);
		
		// Kill switch for notification
		if ( ! apply_filters( 'ef_notification_editorial_comment', $comment, $post ) )
			return false;
		
		$user = get_userdata( $post->post_author );
		$current_user = wp_get_current_user();
	
		$post_id = $post->ID;
		$post_type = ucwords( $post->post_type );
		$post_title = ef_draft_or_post_title( $post_id );
	
		// Check if this a reply
		//$parent_ID = isset( $comment->comment_parent_ID ) ? $comment->comment_parent_ID : 0;
		//if($parent_ID) $parent = get_comment($parent_ID);
		
		// Set user to follow post
		// @TODO: need option to opt-out
		// TODO: This should not be in here... move to separate function
		$this->follow_post_user($post, (int) $current_user->ID);
	
		$blogname = get_option('blogname');
	
		/* translators: 1: blog name, 2: post title */
		$subject = sprintf( __( '[%1$s] New Editorial Comment: "%2$s"', 'edit-flow' ), $blogname, $post_title );

		/* translators: 1: post id, 2: post title, 3. post type */
		$body  = sprintf( __( 'A new editorial comment was added to %3$s #%1$s "%2$s"', 'edit-flow' ), $post_id, $post_title, $post_type ) . "\r\n\r\n";
		/* translators: 1: comment author, 2: author email, 3: date, 4: time */
		$body .= sprintf( __( '%1$s (%2$s) said on %3$s at %4$s:', 'edit-flow' ), $current_user->display_name, $current_user->user_email, mysql2date(get_option('date_format'), $comment->comment_date), mysql2date(get_option('time_format'), $comment->comment_date) ) . "\r\n";
		$body .= "\r\n" . $comment->comment_content . "\r\n";

		// @TODO: mention if it was a reply
		/*
		if($parent) {
			
		}
		*/
		
		$body .= "\r\n--------------------\r\n";
		
		$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
		$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
		
		$body .= "\r\n";
		$body .= __( '== Actions ==', 'edit-flow' ) . "\r\n";
		$body .= sprintf( __( 'Reply: %s', 'edit-flow' ), $edit_link . '#editorialcomments/reply/' . $comment->comment_ID ) . "\r\n";
		$body .= sprintf( __( 'Add editorial comment: %s', 'edit-flow' ), $edit_link . '#editorialcomments/add' ) . "\r\n";
		$body .= sprintf( __( 'Edit: %s', 'edit-flow' ), $edit_link ) . "\r\n";
		$body .= sprintf( __( 'View: %s', 'edit-flow' ), $view_link ) . "\r\n";
		
		$body .= "\r\n" . sprintf( __( 'You can see all editorial comments on this %s here: ', 'edit-flow' ), $post_type ). "\r\n";		
		$body .= $edit_link . "#editorialcomments" . "\r\n\r\n";
		
		$body .= $this->get_notification_footer($post);
		
		$this->send_email( 'comment', $post, $subject, $body );
	}
	
	function get_notification_footer( $post ) {
		$body  = "";
		$body .= "\r\n--------------------\r\n";
		$body .= sprintf( __( 'You are receiving this email because you are subscribed to "%s".', 'edit-flow' ), ef_draft_or_post_title ($post->ID ) );
		$body .= "\r\n";
		$body .= sprintf( __( 'This email was sent %s.', 'edit-flow' ), date( 'r' ) );
		$body .= "\r\n \r\n";
		$body .= get_option('blogname') ." | ". get_bloginfo('url') . " | " . admin_url('/') . "\r\n";
		return $body;
	} 
	
	/**
	 * send_email()
	 */
	function send_email( $action, $post, $subject, $message, $message_headers = '' ) {
	
		// Get list of email recipients -- set them CC		
		$recipients = $this->_get_notification_recipients($post, true);
		
		if( $recipients && ! is_array( $recipients ) )
			$recipients = explode( ',', $recipients );
		
		if( EF_NOTIFICATION_USE_CRON ) {
			$this->schedule_emails( $recipients, $subject, $message, $message_headers );
		} else {
			foreach( $recipients as $recipient ) {
				$this->send_single_email( $recipient, $subject, $message, $message_headers );
			}
		}
	} // END: send_email()
	
	/**
	 * schedule_emails()
	 * Schedules emails to be sent in succession
	 * 
	 * @param mixed $recipients Individual email or array of emails
	 * @param string $subject Subject of the email
	 * @param string $message Body of the email
	 * @param string $message_headers. (optional) Message headers
	 * @param int $time_offset (optional) Delay in seconds per email
	 */
	function schedule_emails( $recipients, $subject, $message, $message_headers = '', $time_offset = 1 ) {
		$recipients = (array) $recipients;
		
		$send_time = time();
		
		foreach( $recipients as $recipient ) {
			wp_schedule_single_event( $send_time, 'ef_send_scheduled_email', array( $recipient, $subject, $message, $message_headers ) );
			$send_time += $time_offset;
		}
		
	} // END: schedule_emails()
	
	/**
	 * send_single_email()
	 * Sends an individual email
	 * 
	 * @param mixed $to Email to send to
	 * @param string $subject Subject of the email
	 * @param string $message Body of the email
	 * @param string $message_headers. (optional) Message headers
	 */
	function send_single_email( $to, $subject, $message, $message_headers = '' ) {
		@wp_mail( $to, $subject, $message, $message_headers );
	} // END: send_single_email()
	
	/**
	 * _get_notification_recipients()
	 * Returns a list of recipients for a given post
	 *
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
		$usergroups = ef_get_following_usergroups( $post_id, 'slugs' );
		if( $usergroups && !empty( $usergroups ) )
			$usergroup_users = ef_get_users_in_usergroup( $usergroups, 'user_email' );
		else
			$usergroup_users = array();
		
		$users = ef_get_following_users( $post_id, 'user_email' );
		
		// Merge arrays
		$recipients = array_merge( $authors, $admins, $users, $usergroup_users );
		
		// Filter out any duplicates
		$recipients = array_unique( $recipients );
		
		// Get rid of empty email entries
		for ( $i = 0; $i < count( $recipients ); $i++ ) {
			if ( empty( $recipients[$i] ) ) unset( $recipients[$i] );
		}
		
		// Filter to allow modification of recipients
		$recipients = apply_filters( 'ef_notification_recipients', $recipients, $post, $string );
		
		// If string set to true, return comma-delimited
		if ( $string && is_array( $recipients ) ) {
			return implode( ',', $recipients );
		} else {
			return $recipients;
		}
	} // END: _get_notification_recipients()
	
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
	 * save_post()
	 * Called when post is saved. Handles saving of user/usergroup followers
	 *
	 * @param int $post ID of the post
	 */
	function save_post( $post ) {
				
		// only if has edit_post_subscriptions cap
		if( ( !wp_is_post_revision($post) && !wp_is_post_autosave($post) ) && isset($_POST['ef-save_followers']) && current_user_can('edit_post_subscriptions') ) {
			$users = isset( $_POST['following_users'] ) ? $_POST['following_users'] : array();
			$usergroups = isset( $_POST['following_usergroups'] ) ? $_POST['following_usergroups'] : array();
			$this->save_post_following_users($post, $users);
			$this->save_post_following_usergroups($post, $usergroups);
		}
		
	} // END: save_post()
	
	/**
	 * save_post_following_users()
	 * Sets users to follow specified post
	 *
	 * @param int $post ID of the post
	 */
	function save_post_following_users( $post, $users = null ) {
		if( !is_array($users) ) $users = array();
		
		// Add current user to following users
		$user = wp_get_current_user();
		if( $user ) $users[] = $user->ID;
		
		$users = array_map( 'intval', $users );

		$follow = $this->follow_post_user($post, $users, false);
		
	} // END: save_post_following_users()
	
	/**
	 * save_post_following_usergroups()
	 * Sets usergroups to follow specified post
	 *
	 * @param int $post ID of the post
	 * @param array $usergroups Usergroups to follow posts
	 */
	function save_post_following_usergroups( $post, $usergroups = null ) {
		
		if( !is_array($usergroups) ) $usergroups = array();
		$usergroups = array_map( 'sanitize_title', $usergroups );

		$follow = $this->follow_post_usergroups($post, $usergroups, false);
	} // END: save_post_following_usergroups()
	
	/**
	 * follow_post_user()
	 * Set set user to follow posts
	 *
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
	} // END: follow_post_user()

	/**
	 * unfollow_post_user()
	 * Removes user from following_users taxonomy for the given Post, so they no longer receive future notifications
	 * Called when delete_user action is fired
	 *
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
		if( ef_term_exists($name, $this->following_users_taxonomy) ) {
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
	} // END: unfollow_post_user()

	/** 
	 * follow_post_usergroups()
	 *
	 */
	function follow_post_usergroups( $post, $usergroups = 0, $append = true ) {
		
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
	} // END: follow_post_usergroups()
	
	/** 
	 * unfollow_post_usergroups()
	 */
	function unfollow_post_usergroups( $post_id, $users = 0 ) {
		
		// TODO: Allow opt-out of email
		
		return;
	}
	
	/**
	 * Removes users that are deleted from receiving future notifications (i.e. makes them unfollow posts FOREVER!)
	 *
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
	  if ( !ef_term_exists($term, $taxonomy) ) {
      $args = array( 'slug' => sanitize_title($term) );		
      return wp_insert_term( $term, $taxonomy, $args );
    }
		return true;
	}
	
} // END: class ef_notifications

} // END: !class_exists('EF_Notifications')

/**
 * ef_get_following_users()
 * Gets a list of the users following the specified post
 *
 * @param int $post_id The ID of the post 
 * @param string $return The field to return
 * @return array $users Users following the specified posts
 */
function ef_get_following_users ( $post_id, $return = 'user_login' ) {
	global $edit_flow;
	
	// Get following_users terms for the post
	$users = wp_get_object_terms($post_id, $edit_flow->notifications->following_users_taxonomy, array('fields' => 'names'));

	// Don't have any following users
	if( !$users || is_wp_error($users) ) return array();
	
	// if just want user_login, return as is
	if( $return == 'user_login' ) return $users;
	
	$users = get_users_field_by( 'user_login', $users, $return );
	if( !$users || is_wp_error($users) )
		$users = array();
	return $users;
	
} // END: ef_get_following_users()

/**
 * ef_get_users_in_usergroup()
 * Returns an array of all users in the specified usergroup(s)
 *
 * @param string|array $slug Slug of the usergroup(s)
 * @return array $users Users in the specified usergroup
 */
function ef_get_users_in_usergroup( $slug, $return = 'ID' ) {
	$users = ef_get_users_by_usermeta( EDIT_FLOW_USERGROUPS_USERMETA, $slug, $return );
	if( !$users || is_wp_error( $users ) ) $users = array();
	return $users;
} // END: ef_get_users_in_usergroup()

/**
 * ef_get_following_usergroups()
 * Gets a list of the usergroups that are following specified post
 *
 * @param int $post_id 
 * @return array $usergroups All of the usergroup slugs
 */
function ef_get_following_usergroups( $post_id, $return = 'all' ) {
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
} // END: ef_get_following_usergroups()

/**
 * ef_get_user_following_posts()
 * Gets a list of posts that a user is following
 *
 * @param string|int $user user_login or id of user
 * @param array $args  
 * @return array $posts Posts a user is following
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
	$post_args = apply_filters( 'ef_user_following_posts_query_args', $post_args );
	$posts = get_posts( $post_args );
	return $posts;
	
} // END: ef_get_user_following_posts()

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
