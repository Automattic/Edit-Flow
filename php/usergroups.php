<?php

// CONSTANTS!
global $wpdb;
define( EDIT_FLOW_USERGROUPS_PAGE, 'admin.php?page=edit-flow/usergroups' );
define( EDIT_FLOW_USERGROUPS_ADD_LINK, EDIT_FLOW_USERGROUPS_PAGE . '&action=add' );
define( EDIT_FLOW_USERGROUPS_EDIT_LINK, EDIT_FLOW_USERGROUPS_PAGE . '&action=edit' );
define( EDIT_FLOW_USERGROUPS_USERMETA, $wpdb->prefix . 'ef_usergroups' ); // prefix added for MU compatibility

class ef_usergroups_admin {

	/**
	 * Constructor
	 */
	function __construct( ) {
		
		add_action('show_user_profile', array(&$this, 'user_profile_page'));
		add_action('edit_user_profile', array(&$this, 'user_profile_page'));
		add_action('user_profile_update_errors', array(&$this, 'user_profile_update'), 10, 3);
		
	}
	
	/**
	 * Adds stuff to the user profile page to allow adding usergroup selecting options
	 */
	function user_profile_page( ) {
		global $user_id, $profileuser;
		
		if( !$user_id ) return;
		
		// Assemble all necessary data
		$usergroups = ef_get_usergroups();
		$selected_usergroups = ef_get_user_usergroups($user_id);
		$usergroups_form_args = array( 'input_id' => 'ef_usergroups' );
		
		// Only enable editing if user has the proper cap
		if(current_user_can('edit_usergroups')) {
			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<h3><?php _e('User Groups', 'edit-flow') ?></h3> 
							<p>
								<?php _e('Select the usergroups that this user should is a part of.', 'edit-flow') ?><br />
								<!--<small><a href="<?php echo EDIT_FLOW_MAIN_PAGE ?>#usergroups" target="_blank" title="<?php _e('Learn more about user groups and how to use them. Opens new window.', 'edit-flow') ?>"><?php _e('What are user groups?', 'edit-flow') ?></a></small>-->
							</p>
						</th>
						<td>
							<?php ef_usergroups_select_form($selected_usergroups, $usergroups_form_args); ?>
						</td>
					</tr>
				</tbody>
			</table>
			
			<?php 
		}
	}
	
	/**
	 * Function called when a user's profile is updated
	 * Adds user to specified usergroups
	 * @param
	 * @param
	 * @param
	 * @return
	 */
	function user_profile_update( $errors, $update, $user ) {
		
		if($update) {
			
			$user_id = $user->ID;
			
			if ( !current_user_can('edit_user', $user_id) )
				wp_die(__('Hey now, you don\'t have permission to edit this user.'));
				
			if ( current_user_can( 'edit_usergroups' ) ) {
				
				// Get the POSTed usergroups data
				$usergroups = $_POST['ef_usergroups'];
				if( !is_array($usergroups) ) $usergroups = array($usergroups);
				
				// Sanitize the data
				$usergroups = array_map('sanitize_text_field', $usergroups );
				
				// Save the data!
				ef_add_user_to_usergroup( $user_id, $usergroups, false );
			}
		}
			
		return array( &$errors, $update, &$user );
	}

	/**
	 * Main controller for Usergroups
	 * Determines what actions to take, takes those actions, and sets up necessary data for views (handled by admin_page())
	 * Sets up a var called $ef_page_data that the admin_page() function can use to pull data from
	 */
	function admin_controller ( ) {
		global $ef_page_data;
		
		// Only allow users with the proper caps
		if ( !current_user_can('edit_usergroups') )
			wp_die(__('Sorry, you do not have permission to edit usergroups.'));
		
		// Global var that holds all the data needed on edit flow pages
		$ef_page_data = array();
		
		$action = esc_html($_GET['action']);
		
		switch($action) {
			
			case 'add':
			
				// If "do" set to "insert" then let's add it as a usergroup
				if( isset($_POST['do']) && 'insert' == $_POST['do'] ) {
					
					// Check nonce!
					check_admin_referer('add', 'usergroups-add-nonce');
					
					// Let's grab the POSTed data
					$data = array();
					$data['name'] = trim(esc_html($_POST['usergroup_name']));
					$data['description'] = esc_html($_POST['usergroup_description']); 
					$slug = sanitize_title($data['name']);
					$data['users'] = ( isset($_POST['usergroup_users']) ) ? array_map('intval', $_POST['usergroup_users']) : array();
					
					// Make the call to the usergroup
					$result = ef_add_usergroup( $slug, $data );
					
					// If everything is hunky dory, then rock on.
					if( !is_wp_error($result) ) {
						$message = __('New usergroup succesfully created!', 'edit-flow');
						$redirect = EDIT_FLOW_USERGROUPS_EDIT_LINK .'&usergroup='. urlencode($result->slug) .'&message='. urlencode($message);
						wp_redirect( $redirect );
					}
				}
				// Setting up page data, woo!
				$ef_page_data['title'] = __('Add Usergroup', 'edit-flow');
				$ef_page_data['usergroup'] = null;
				$ef_page_data['update'] = false;
				$ef_page_data['backlink'] = true;
				$ef_page_data['view'] = 'templates/usergroups_edit.php';
				break;
			
			case 'edit':
				
				if( isset($_POST['do']) && 'update' == $_POST['do'] ) {
						
					// Don't allow any nonce-sense!
					check_admin_referer('edit', 'usergroups-edit-nonce');
					
					// Let's grab some POST grab from the form 
					$slug = esc_html($_POST['usergroup_slug']);
					$data['name'] = trim(esc_html($_POST['usergroup_name']));
					$data['description'] = esc_html($_POST['usergroup_description']);	
					$data['users'] = ( isset($_POST['usergroup_users']) ) ? array_map('intval', $_POST['usergroup_users']) : array();
					
					// Let's make the call to update the usergroup
					$result = ef_update_usergroup( $slug, $data );
					
					if( !is_wp_error($result) ) {
						$message = __('Usergroup succesfully updated!', 'edit-flow');
						$redirect = EDIT_FLOW_USERGROUPS_EDIT_LINK .'&usergroup='. urlencode($result->slug) .'&message='. urlencode($message);
						wp_redirect( $redirect );
					} 
				} 
				
				$slug = esc_html($_GET['usergroup']);
				$usergroup = ef_get_usergroup($slug);
				
				// Some error-checking
				if( $usergroup ) {
					// Prepare necessary data
					$ef_page_data['title'] = __('Update Usergroup', 'edit-flow');
					$ef_page_data['usergroup'] = $usergroup;
					$ef_page_data['usergroup_users'] = $usergroup->get_users();
					$ef_page_data['update'] = true;
					$ef_page_data['backlink'] = true;
					$ef_page_data['view'] = 'templates/usergroups_edit.php';
				} else {
					// Oh, well, we didn't find the usergroup!
					$message = __('Hm, looks like that usergroup does not exist! How about you try again?', 'edit-flow');
					$redirect = EDIT_FLOW_USERGROUPS_PAGE .'&message='. urlencode($message);
					wp_redirect( $redirect );
				}
				break;
				
			case 'delete':
				
				// Don't allow any nonce-sense!
				check_admin_referer();
					
				// Let's grab some POST grab from the form 
				$slug = esc_html($_REQUEST['usergroup']);

				// Delete stuff
				$remove = ef_remove_usergroup($slug);
				
				if( !is_wp_error($remove) ) {
					$message = __('Usergroup was successfully deleted.', 'edit-flow');
					$redirect = EDIT_FLOW_USERGROUPS_PAGE .'&message='. urlencode($message);
					wp_redirect( $redirect );
				} else {
					// @TODO: more decsriptive error message
					$ef_page_data['usergroups'] = ef_get_usergroups();
					$ef_page_data['errors'][] = __('Looks like something went wrong with the delete.', 'edit-flow');
				}
				break;
				
			case 'view':
			default:
				$ef_page_data['usergroups'] = ef_get_usergroups();
				$ef_page_data['view'] = 'templates/usergroups_view.php';
				break;
		}
		
	}

	/**
	 * Handles the main admin for usergroups. The Controller, if you will.
	 */
	function admin_page( ) {
		global $ef_page_data;
		
		// Set up defaults
		$page_defaults = array(
			'title' => __('Usergroups', 'edit-flow'),
			'view' => 'templates/usergroups_view.php',
			'backlink' => false,
			);
		
		// extract all the args
		$parsed_args = wp_parse_args( $ef_page_data, $page_defaults );
		extract($parsed_args, EXTR_SKIP);

		?>
		<div class="wrap usergroups-page">
			<div id="icon-users" class="icon32"><br /></div>
			<h2><?php echo $title ?> <?php if($backlink) { ?><a href="<?php echo EDIT_FLOW_USERGROUPS_PAGE ?>" class="backlink">&larr; <?php _e('Back to Usergroups', 'edit-flow') ?></a><?php } ?></h2>
			
			<?php require_once($view); ?>
			
		</div>
		<?php
	}
	
}

class EF_UserGroups {

	var $usergroups;
	var $usergroups_option;
	var $usergroup_objects;
	var $usergroup_names;
	var $usergroups_taxonomy;

	/**
	 * Constructor!
	 */
	function __construct( ) {
		global $edit_flow, $ef_usergroups;
		
		//$this->usergroups_option = $edit_flow->get_plugin_option_fullname('usergroups');
		$this->usergroups_taxonomy = $edit_flow->notifications->following_usergroups_taxonomy;
		
		if ( !empty($ef_usergroups) ) {
			$this->usergroups = $ef_usergroups;
		} else {
			//$this->usergroups = $edit_flow->get_plugin_option('usergroups');
			$this->usergroups = get_terms($this->usergroups_taxonomy, 'hide_empty=0');
		}
		
		if ( empty( $this->usergroups ) )
			return;
		
		$this->usergroup_objects = array();
		$this->usergroup_names =  array();
		/*
		foreach ( (array) $this->usergroups as $usergroup => $data ) {
			$this->usergroup_objects[$usergroup] = new EF_UserGroup( $usergroup, $data );
			$this->usergroup_names[$usergroup] = $data['name'];
		}*/
		foreach ( $this->usergroups as $usergroup ) {
			$usergroup_slug = $usergroup->slug;
			$data = array(
				'name' => $usergroup->name,
				'description' => $usergroup->description,
			);
			$this->usergroup_objects[$usergroup_slug] = new EF_UserGroup( $usergroup_slug, $data );
			$this->usergroup_names[$usergroup_slug] = $data['name'];
		}
	}
	
	/**
	 * Gets all usergroups
	 * @return array of all usergroup objects
	 */
	function get_all_usergroups( ) {
		return $this->usergroup_objects;
	}
	
	/**
	 * Gets the usergroup object specified by the slug
	 * @param $slug string Slug identifier for the usergroup
	 */
	function get_usergroup( $slug ) {
		if( isset($this->usergroup_objects[$slug]) )
			return $this->usergroup_objects[$slug];
		else
			return false;
	}
	
	/**
	 * Adds usergroup
	 * @param $slug string
	 * @param $data array
	 * return 
	 */
	function add_usergroup( $slug, $data, $unique_slug = true ) {
		$errors = new WP_Error();
		
		// name is empty 
		if(!$data['name']) {
			$errors->add('usergroup-empty-name', __('Sorry, the name of the usergroup cannot be empty!', 'edit-flow'));
		}
		
		// If unique_slug flag set and usergroup with slug already exists, append number to the end to make it unique
		if( $unique_slug ) $slug = $this->unique_slug($slug);
		
		if( $errors->get_error_codes() ) return $errors;
		
		$this->usergroups[$slug] = array(
			'name' => $data['name'],
			'slug' => $slug,
			'description' => $data['description'],
			);
		$this->usergroup_objects[$slug] = new EF_Usergroup( $slug, $data );
		$this->usergroup_names[$slug] = $data['name'];
		
		//$save = $this->save_usergroups();
		
		//if( !is_wp_error($save) ) {
			// Add users
			$this->usergroup_objects[$slug]->add_users($data['users']);
			
			// Add as term so we don't have to waste time when saving a post
			if( $usergroup_term = get_term_by( 'slug', $slug, $this->usergroups_taxonomy ) )
				wp_update_term( $usergroup_term->term_id, $this->usergroups_taxonomy, $this->usergroups[$slug]);
			else
				wp_insert_term( $data['name'], $this->usergroups_taxonomy, $this->usergroups[$slug]);
			
			return $this->usergroup_objects[$slug];
		//}
		// return error
		return $save;
	}
	
	/**
	 * Udpdates the specified usergroup
	 * @param $slug
	 * @param $data
	 * @return
	 */
	function update_usergroup( $slug, $data ) {
		$errors = new WP_Error();
		
		// name is empty 
		if(!$data['name']) {
			$errors->add('usergroup-empty-name', __('Name can\'t be empty, fool!', 'edit-flow'));
		}
		
		if( $errors->get_error_codes() ) return $errors;
		
		if( $this->get_usergroup($slug) ) {
			unset($this->usergroups[$slug]);
			unset($this->usergroup_objects[$slug]);
			unset($this->usergroup_names[$slug]);
		}
		
		return $this->add_usergroup($slug, $data, false);
		
	}
	
	/**
	 * Deletes the usergroup specified by the slug
	 * @param $slug
	 * @return
	 */
	function remove_usergroup( $slug ) {
		global $edit_flow;
		
		$usergroup = $this->usergroup_objects[$slug];
		
		if($usergroup) {
			// Remove all users from usergroup
			$users = $usergroup->remove_all_users();
			
			// Remove following_usergroups term
			$usergroup_term = get_term_by('slug', $usergroup->slug, $this->usergroups_taxonomy);
			if($usergroup_term) {
				$delete = wp_delete_term($usergroup_term->term_id, $this->usergroups_taxonomy);
			}
			
			//unset($this->usergroups[$slug]);
			//unset($this->usergroup_objects[$slug]);
			//unset($this->usergroup_names[$slug]);
			//$save = $this->save_usergroups();
			
			if( !is_wp_error($delete) )
				return true;
			else
				return $delete;
		}
		return new WP_Error('no-usergroup', __('Sorry, that usergroup doesn\'t seem to exist.', 'edit-flow'));
	}
	
	/**
	 * Saves all usergroups back to the options table
	 */
	 /*
	function save_usergroups ( ) {
		// Sort by alpha before saving
		ksort($this->usergroups);
		$save = update_option($this->usergroups_option, $this->usergroups);
		return $save;
	}
	*/
	/**
	 * Checks if usergroup exists
	 * @return true if exist; false otherwise
	 */
	function is_usergroup ( $slug ) {
		if(isset($this->usergroup_names[$slug]))
			return true;
		return false;
	}

	/**
	 * 
	 */
	function unique_slug ( $slug, $count = 0 ) {
		if( $this->is_usergroup($slug) ) {
			$count++;
			$slug = $slug .'-'. $count;
			return $this->unique_slug($slug, $count);
		}
		if( !strstr($slug, EDIT_FLOW_PREFIX) )
			$slug = EDIT_FLOW_PREFIX . $slug;
		return $slug;
	}
} // END: class EF_Usergroups

class EF_Usergroup {
	
	var $slug;
	var $name;
	var $description;
	var $users = array();
	var $users_count = 0;

	/**
	 * Creates a sweet-lookin' UserGroup object
	 * 
	 */
	function __construct( $slug, $data = array() ) {
		$this->slug = $slug;
		$this->name = $data['name'];
		$this->description = $data['description'];
	}
	
	/**
	 * Adds specified users to the user group
	 * @param $users int|array user_id or array of user_ids 
	 */
	function add_users( $users ) {
		// get a clean array of new users
		if( !is_array($users) ) $users = array($users);
		$users = array_map( 'intval', $users );
		
		// get a list of old users
		$old_users = $this->get_users();
		
		if( !is_wp_error($old_users) ) {
			$remove_users = array_diff($old_users, $users);
			
			foreach( $remove_users as $remove ) {
				ef_remove_user_from_usergroup( intval($remove), $this->slug );
			}
		}
		
		foreach( $users as  $user ) {
			ef_add_user_to_usergroup( intval($user), $this->slug );
		}
		
		return;
	}
	/**
	 * Removes the user from the usergroup
	 * @param $users int|array user_id or array of user_ids
	 */
	function remove_users( $users ) {
		if( !is_array($users) ) $users = array($users);
		
		//intval the ids
		$users = array_map( 'intval', $users );
		
		foreach( $users as $user ) {
			ef_remove_user_from_usergroup( $user, $this->slug );
		}
		return;
	}

	/**
	 * Removes all users from this usergroup
	 * @return 
	 */
	function remove_all_users ( ) {
		$users = $this->get_users();
		
		if( is_array($users) ) {
			$this->remove_users($users);
			return true;
		}
		return false;
	}
	
	/**
	 * Returns the number of users that belong to this groups
	 * @return array of ids
	 */
	function get_user_count( ) {
		$users = $this->get_users();
		if( !is_wp_error($users) )
			return count($users);
		return 0;
	}
	
	/**
	 * Returns a list of users that belong to this groups
	 * @return array of ids
	 */
	function get_users( ) {
		global $wpdb;
		
		if( !empty($this->users) ) return $this->users;
		
		$user_query_vars = array(
			'usermeta' => array(EDIT_FLOW_USERGROUPS_USERMETA => $this->slug)
			);
		
		// Inst. the search class and get results
		$user_query = new EF_User_Query($user_query_vars);
		$users = $user_query->get_results();
		
		if( is_wp_error($users) )
			$this->users = array();
		else
			$this->users = $users;
		
		return $this->users;
	}
	
} // END: class EF_Usergroup

/**
 * Returns a list of usergroups that the user belongs to
 * @param int $user_id (optional) If not specified, reverts to logged in user
 */
function ef_get_user_usergroups( $user_id = 0 ) {
	if( !$user_id ) $user_id = wp_get_current_user()->ID;
	if( !$user_id ) return;
	return get_metadata('user', $user_id, EDIT_FLOW_USERGROUPS_USERMETA);
}

/**
 * Adds user to the specified usergroup(s)
 * @param $user int|string user id or username
 * @param $usergroup string|array Key or array of keys for usergroups to add the user to
 * @param $append 
 */
function ef_add_user_to_usergroup ( $user, $usergroups, $append = true ) {
	
	// if we have a username get user_id
	$user = ( is_int($user) ) ? $user : get_user_by_login($user);
	if( !$user ) return;
	
	if( !is_array($usergroups) ) $usergroups = array($usergroups);

	$old_usergroups = get_metadata('user', $user, EDIT_FLOW_USERGROUPS_USERMETA);
	if( !is_array($old_usergroups) ) $old_usergroups = array($old_usergroups);
	
	$added_usergroups = $deleted_usergroups = array();
	
	// merge the old and new usergroup arrays
	if($append) {
		foreach( $usergroups as $usergroup ) {
			if( !in_array($usergroup, $old_usergroups) ) {
				$added_usergroups[] = $usergroup;
			}
		}
	} else {
		
		$added_usergroups = array_diff($usergroups, $old_usergroups);
		$deleted_usergroups = array_diff($old_usergroups, $usergroups);
		
		// Delete usergroups that were removed
		foreach( $deleted_usergroups as $del ) {
			delete_metadata('user', $user, EDIT_FLOW_USERGROUPS_USERMETA, $del);
		}
	}
		
	// Add usergroups that were added
	foreach( $added_usergroups as $add ) {
		add_metadata('user', $user, EDIT_FLOW_USERGROUPS_USERMETA, $add);
	}
	
	return;
}

/**
 * Removes a user from the specified usergroups
 * @param int $user ID of user
 * @param string|array $usergroups 
 * @return 
 */
function ef_remove_user_from_usergroup ( $user, $usergroups ) {
	
	// if we have a username get user_id
	$user = ( is_int($user) ) ? $user : get_userdatabylogin($user)->ID;
	if ( !$user ) return;
	
	// make sure we're working with an array
	if( !is_array($usergroups) ) $usergroups = array($usergroups);
	
	foreach( $usergroups as $usergroup ) {
		delete_metadata( 'user', $user, EDIT_FLOW_USERGROUPS_USERMETA, $usergroup );
	}
	
	return;
}
	
/**
 * Returns an array of all users in the specified usergroup
 * @param $slug string slug of the usergroup
 */
 /*
function get_users_in_usergroup ( $slug ) {
	
}*/

/**
 * Returns an array of all users in the specified usergroup
 * @param $slug string slug of the usergroup
 */
function ef_is_usergroup ( $slug ) {
	global $ef_usergroups;
	
	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_Usergroups();
		
	return $ef_usergroups->is_usergroup( $slug );
}

/**
 * Adds a usergroup
 * @param $slug
 * @param $data
 * @return EF_Usergroup|WP_Error
 */
function ef_add_usergroup( $slug, $data ) {
	global $ef_usergroups;
	
	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_Usergroups();
		
	return $ef_usergroups->add_usergroup( $slug, $data );
}

/**
 * Updates a usergroup
 * @param $slug
 * @param $data
 * @return EF_Usergroup|WP_Error
 */
function ef_update_usergroup( $slug, $data ) {
	global $ef_usergroups;
	
	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_Usergroups();
		
	return $ef_usergroups->update_usergroup( $slug, $data );
}

/**
 * Adds a usergroup
 * @param $slug
 * @param $name
 * @param $data
 * @return true|WP_Error
 */
function ef_remove_usergroup( $slug ) {
	global $ef_usergroups;
	
	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_Usergroups();
		
	return $ef_usergroups->remove_usergroup( $slug );
}


/**
 * Returns an array of all UserGroup objects in WordPress
 * 
 */
function ef_get_usergroups ( ) {
	global $ef_usergroups;

	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_UserGroups();

	return $ef_usergroups->get_all_usergroups();
}

/**
 * Returns usergroup with the given slug
 * 
 */
function ef_get_usergroup ( $slug ) {
	global $ef_usergroups;

	if ( ! isset( $ef_usergroups ) )
		$ef_usergroups = new EF_UserGroups();

	return $ef_usergroups->get_usergroup($slug);
}

function ef_the_usergroup_edit_link ( $slug ) {
	echo ef_get_the_usergroup_edit_link($slug);
}
function ef_get_the_usergroup_edit_link ( $slug ) {
	return ( EDIT_FLOW_USERGROUPS_PAGE . '&amp;action=edit&amp;usergroup='. $slug );
}
function ef_the_usergroup_delete_link ( $slug, $nonce ) {
	return ef_get_the_usergroup_delete_link ( $slug, $nonce );
}
function ef_get_the_usergroup_delete_link ( $slug, $nonce ) {
	return ( EDIT_FLOW_USERGROUPS_PAGE . '&amp;action=delete&amp;usergroup='. $slug .'&amp;_wpnonce='. $nonce );
}

/**
 * Displays a list of usergroups with checkboxes
 * @param $input_name string 
 * @param $selected array List of usergroup keys that should be checked
 * @param $args
 */
function ef_usergroups_select_form( $selected = array(), $args = null ) {
	
	// TODO add $args for additional options
	// e.g. showing members assigned to group (John Smith, Jane Doe, and 9 others)
	// before <tag>, after <tag>, class, id names?
	$defaults = array(
		'list_class' => 'ef-post_following_list',
		'list_id' => 'ef-following_usergroups',
		'input_id' => 'following_usergroups'
	);

	$parsed_args = wp_parse_args( $args, $defaults );
	extract($parsed_args, EXTR_SKIP);
	
	$usergroups = ef_get_usergroups();
	
	if( !is_array($selected) ) $selected = array();
	
	if( empty($usergroups) ) {
		?>
		
		<p>
			<?php __('Whoops! We didn\'t find any user groups.', 'edit-flow') ?> 
			<a href="<?php echo EF_USERGROUPS_ADD_LINK ?>" title="<?php _e('Add a new user group. Opens new window.', 'edit-flow') ?>" target="_blank">
				<?php _e('How about adding one?', 'edit-flow') ?>
			</a>
		</p>
		<?php
	} else {
		
		?>
		<ul id="<?php echo $list_id ?>" class="<?php echo $list_class ?>">
		<?php
		foreach( $usergroups as $usergroup ) {
			$checked = (in_array($usergroup->slug, $selected)) ? 'checked="checked"' : '';
			?>
			<li>
				<label for="<?php echo $input_id . $usergroup->slug ?>" title="<?php echo esc_attr($usergroup->description) ?>">
					<input type="checkbox" id="<?php echo $input_id . $usergroup->slug ?>" name="<?php echo $input_id ?>[]" value="<?php echo $usergroup->slug ?>" <?php echo $checked ?> />
					<span class="ef-usergroup_name"><?php echo $usergroup->name ?></span>
					<span class="ef-usergroup_description" title="<?php echo esc_attr($usergroup->description) ?>">
						<?php echo substr($usergroup->description, 0, 50) ?>
					</span>
					
				</label>
			</li>
			<?php
		}
		?>
		</ul>
		<?php
	}
}

/**
 * Displays a list of users that can be selected!
 * @param
 * @param 
 */
function ef_users_select_form ( $selected = null, $args = null ) {
	global $blog_id;
	
	// TODO: Add pagination support for blogs with billions of usrs
	
	// Set up arguments
	$defaults = array(
		'list_class' => 'ef-post_following_list', 
		'input_id' => 'following_users'
	);
	$parsed_args = wp_parse_args( $args, $defaults );
	extract($parsed_args, EXTR_SKIP);
	
	// Using blog_id for MU support
	$users = get_users_of_blog($blog_id);
	
	if( !is_array($selected) ) $selected = array();
	?>
		
	<?php if( !empty($users) ) : ?>
		<?php //TODO: Links to select All, None, etc. ?>
		<!--<input type="text" class="ef-users_search" value="Search for a user" />-->
		<ul id="ef-post_following_users" class="<?php echo $list_class ?>">
			<?php foreach( $users as $user ) : ?>
				<?php $checked = ( in_array($user->ID, $selected) ) ? 'checked="checked"' : ''; ?>
				<li>
					<label for="<?php echo $input_id .'_'. $user->ID ?>">
						<input type="checkbox" id="<?php echo $input_id .'_'. $user->ID ?>" name="<?php echo $input_id ?>[]" value="<?php echo $user->ID ?>" <?php echo $checked ?> />
						<span class="ef-user_displayname"><?php echo $user->display_name ?></span>
						<span class="ef-user_useremail"><?php echo $user->user_email ?></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<!--
	<h5>Subscribed users</h5>
	<?php if( !empty($followers) ) : ?>
		<ul class="">
			<?php foreach( $followers as $follower ) : ?>
				<li><?php echo $follower ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php _e('No users are subscribed to this this post :(', 'edit-flow'); ?></p>
	<?php endif; ?>
	-->
	<?php
}

?>