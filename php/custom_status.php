<?php
 
// Functions related to hooking into custom statuses will go here

class custom_status {

	// This is taxonomy name used to store all our custom statuses
	var $status_taxonomy = 'post_status';
	
	/**
	 * Constructor
	 */
	function __construct ( $active = 1) {
		global $pagenow, $edit_flow;
		
		// Register new taxonomy so that we can store all our fancy new custom statuses (or is it stati?)
		if(!is_taxonomy($this->status_taxonomy)) register_taxonomy( $this->status_taxonomy, 'post', array('hierarchical' => false, 'update_count_callback' => '_update_post_term_count', 'label' => false, 'query_var' => false, 'rewrite' => false) );
		
		// These actions should be called regardless of whether custom statuses are enabled or not
		// Add actions and filters for the Edit/Manage Posts page
		add_action('load-edit.php', array(&$this, 'load_edit_hooks'));
		// Add action and filter for the Edit/Manage Pages page
		add_action('load-edit-pages.php', array(&$this, 'load_edit_hooks'));
		
		if( $active ) {
			
			// Hooks to add "status" column to Edit Posts page
			add_filter('manage_posts_columns', array('custom_status', '_filter_manage_posts_columns'));
			add_action('manage_posts_custom_column', array('custom_status', '_filter_manage_posts_custom_column'));
			
			// Hooks to add "status" column to Edit Pages page, BUT, only add it if not being filtered by post_status
			add_filter('manage_pages_columns', array('custom_status', '_filter_manage_posts_columns'));
			add_action('manage_pages_custom_column', array('custom_status', '_filter_manage_posts_custom_column'));
		}
	} // END: __construct()
	
	/**
	 * Hooks to make modifications to the Manage/Edit Posts
	 */
	function load_edit_hooks() {
		// Add custom stati to Edit/Manage Posts
		add_action('admin_notices',array(&$this, 'enable_custom_status_filters'));
		// Modify the posts_where query to include custom stati
		add_filter('posts_where', array(&$this, 'custom_status_where_filter'));
	} // END: load_edit_hooks()
	
	function load_edit_pages_hooks() {
		global $edit_flow;
		
		if($edit_flow->get_plugin_option('pages_custom_statuses_enabled')) {
			// Add custom stati to Edit/Manage Pages
			add_filter('apply_filters', array(&$this, 'enable_custom_status_filters_pages'));
			// Modify the posts_where query to include custom stati
			add_filter('posts_where', array(&$this, 'custom_status_where_filter'));
		}
	}
	
	/**
	 * Adds custom stati to the $post_stati array.
	 * This is used to generate the list of statuses on the Edit/Manage Posts page.
	 *
	 */
	function enable_custom_status_filters() {
		// This is the array WP uses to store custom stati (really? stati?)
		// The status list at the top of the Manage/Edit Posts page is generated using this array
		global $post_stati;

		if(is_array($post_stati)) {
			
			// @ TODO Don't return statuses that are empty (i.e. no posts)
			// Get a list of ALL the custom statuses
			$custom_statuses = $this->get_custom_statuses();
			
			// Alright, now append them to the $post_stati array
			foreach($custom_statuses as $status) {
				if(!$this->is_restricted_status($status->slug)) {
					$slug = $status->slug;
					$post_stati[$slug] = array(
						$status->name,
						$status->description,
						array(
							$status->name.' <span class="count">(%s)</span>',
							$status->name.' <span class="count">(%s)</span>'
						)
					);
						
				}
			}
		}
	} // END: enable_custom_status_filters()

	/* Adds custom stati to the $post_stati array for pages
	 * This is used to generate the list of statuses on the Edit/Manage Pages page.
	 *
	 */
	function enable_custom_status_filters_pages($post_stati) {
		if(is_array($post_stati)) {
			
			// @ TODO Don't return statuses that are empty (i.e. no posts)
			// Get a list of ALL the custom statuses
			$custom_statuses = $this->get_custom_statuses();
			
			// Alright, now append them to the $post_stati array
			foreach($custom_statuses as $status) {
				if(!$this->is_restricted_status($status->slug)) {
					$slug = $status->slug;
					$post_stati[$slug] = array(
						$status->name,
						$status->description,
						array(
							$status->name.' <span class="count">(%s)</span>',
							$status->name.' <span class="count">(%s)</span>'
						)
					);
						
				}
			}
		}
		return $post_stati;
	} // END: enable_custom_status_filters_pages()

	/**
	 * Edits the WHERE clause for the the get_post query.
	 * This is used to show all the posts with custom statuses.
	 * Why? Because WordPress automatically hides anything without an allowed status (e.g. "publish", "draft",, etc.)
	 */	
	function custom_status_where_filter($where){
		global $wpdb, $user_ID;
		
		/** 
		 * Replacement code fixes filtering issue
		 * Could not filter by category, author, search, on Manage Posts page
		 *
		 * Mad props to David Smith from Columbia U.
		 **/
		if(is_admin() ) {
			if(!(isset($_GET['post_status'])) && !(isset($_POST['post_status']))) {			
				$custom_statuses = $this->get_custom_statuses();
				//insert custom post_status where statements into the existing the post_status where statements - "post_status = publish OR"
				//the search string
				$search_string = $wpdb->posts.".post_status = 'publish' OR ";
	
				//build up the replacement string
				$replace_string = $search_string;
				foreach($custom_statuses as $status) {
					$replace_string .= $wpdb->posts.".post_status = '".$status->slug."' OR "; 
				}
	
				$where = str_replace($search_string, $replace_string, $where);
				
			} else {
				// Okay, we're filtering by statuses
				$status = $_GET['post_status'];
				
				// if not one of inbuilt custom statuses, delete query where AND (wp_posts.post_status = 'publish' OR wp_posts.post_status = 'future' OR wp_posts.post_status = 'draft' OR wp_posts.post_status = 'pending' OR wp_posts.post_status = 'private')
				// append status to where
				
				if(is_term($status, $this->status_taxonomy)) {
					//delete only the offending query --- not the entire query
					 $search_string = "AND (".$wpdb->posts.".post_status = 'publish' OR ".$wpdb->posts.".post_status = 'future' OR ".$wpdb->posts.".post_status = 'draft' OR ".$wpdb->posts.".post_status = 'pending'";
					if ( is_user_logged_in() ) {
						$search_string .= current_user_can( "read_private_posts" ) ? " OR $wpdb->posts.post_status = 'private'" : " OR $wpdb->posts.post_author = $user_ID AND $wpdb->posts.post_status = 'private'";
					}
					$search_string .= ")";
	
					$replace_string = "AND (".$wpdb->posts.".post_status = '".$status."')";
					$where = str_replace($search_string, $replace_string, $where);
				}
			}
	
		}
		return $where;
	} // END: custom_status_where_filter()
	
	/**
	 * Adds a new custom status as a term in the wp_terms table.
	 * Basically a wrapper for the wp_insert_term class.
	 *
	 * The arguments decide how the term is handled based on the $args parameter.
	 * The following is a list of the available overrides and the defaults.
	 *
	 * 'description'. There is no default. If exists, will be added to the database
	 * along with the term. Expected to be a string.
	 *
	 * 'slug'. Expected to be a string. There is no default.
	 *
	 * @param int|string $term The status to add or update
	 * @param array|string $args Change the values of the inserted term
	 * @return array|WP_Error The Term ID and Term Taxonomy ID
	 *
	 */
	function add_custom_status ( $term, $args = array() ) {
		// $args = array( 'alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => '');
		return wp_insert_term( $term, $this->status_taxonomy, $args );
		
	} // END: add_custom_status
	
	/**
	 * 
	 * Basically a wrapper for the wp_update_term function
	 */
	function update_custom_status ( $status_id, $args = array() ) {
	
		// Reassign posts to new status slug
		$old_status = get_term($status_id, $this->status_taxonomy)->slug;

		if(!$this->is_restricted_status($old_status)) {
			// If new status not indicated, set to "draft", else get slug for new status
			$new_status = $args['slug'];
			$this->reassign_post_status( $old_status, $new_status );
		}

		return wp_update_term( $status_id, $this->status_taxonomy, $args );
	} // END: update_custom_status
	
	/**
	 * Deletes a custom status from the wp_terms table.
	 * 
	 * Partly a wrapper for the wp_delete_term function.
	 * BUT, also reassigns posts that currently have the deleted status assigned.  
	 */
	function delete_custom_status ( $status_id, $args = array(), $reassign = '' ) {
	
		// Reassign posts to alternate status

		// Get slug for the old status
		$old_status = get_term($status_id, $this->status_taxonomy)->slug;

		if(!$this->is_restricted_status($old_status)) {
		
			// If new status not indicated, set to "draft", else get slug for new status
			if(!$reassign) $new_status = 'draft';
			else $new_status = get_term($reassign, $this->status_taxonomy)->slug;
			
			$this->reassign_post_status( $old_status, $new_status );
		}
	
		return wp_delete_term( $status_id, $this->status_taxonomy, $args );
	} // END: delete_custom_status
	

	function get_custom_statuses ($statuses = '', $args ='' ) {
		
		// @TODO: implement $args, to allow for pagination, etc. 

		if(!$statuses) {
			// return all stati			
			$statuses = get_terms( $this->status_taxonomy, array( 'get' => 'all' ) );
		} else if (!is_array($statuses)) {
			// return a single status			
		} else {
			// return multiple stati 	
		}
		
		return $statuses;		
	}
	
	function reassign_post_status( $old_status, $new_status = 'draft' ) {
		global $wpdb;
		
		// Make the database call
		$result = $wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'post_status' => $old_status ), array( '%s' ));
	}
	
	/**
	 * Insert new column header for post status
	 * 
	 * @param array $post_columns
	 **/
	function _filter_manage_posts_columns($posts_columns) {
		$result = array();
		foreach ($posts_columns as $key => $value) {
			if ($key == 'title') {
				$result[$key] = $value;
				$result['status'] = __('Status', 'edit-flow');
			} else $result[$key] = $value;
		}
		return $result;
	} // END: _filter_manage_posts_columns
	
	/**
	 * Adds a Post's status to its row on the Edit page
	 * 
	 * @param string $column_name
	 **/
	function _filter_manage_posts_custom_column($column_name) {
		if ($column_name == 'status') {
			global $post, $custom_status;
			echo ef_get_status_name('slug' , $post->post_status);
		}
	}
	
	
	/**
	 * Determines whether the slug indicated belongs to a restricted status or not
	 * @param string Slug of the status 
	 * @return bool True if restricted, false if not
	 */
	function is_restricted_status ( $slug ) {

		switch($slug) {
			case 'publish':
			case 'draft':
			case 'private':
			case 'future':
			case 'pending':
			case 'new':
			case 'inherit':
				$return = true;
				break;
			
			default:
				$return = false;
				break;
		}
		return $return;	
	}

	
	
	function admin_page ( ) {
		global $wpdb, $edit_flow;
		
		$nonce_fail_msg = __('There\'s something fishy going on! We don\'t like this type of nonce-sense. Hmph.', 'edit-flow');
		$msg_class = 'updated';
		
		$action = ($_POST['action']) ? $wpdb->escape($_POST['action']) : $wpdb->escape($_GET['action']);
		
		switch($action) {
			
			case 'add':
				
				// Verfiy nonce
				if(wp_verify_nonce($_POST['custom-status-add-nonce'], 'custom-status-add-nonce')) {
					
					$status_name = esc_html(trim($_POST['status_name']));
					$status_slug = sanitize_title($status_name);
					$status_description = esc_html($_POST['status_description']);
					
					// Check if name field was filled in
					if(!$status_name || empty($status_name)) {
						$error_details = __('Please enter a name for the status', 'edit-flow');
						break;
					}
					
					// Check that the name isn't numeric
					if( (int)$status_name != 0 ) {
						$error_details = __('Please enter a valid name.', 'edit-flow');
					}
					
					// Check that the status name doesn't exceed 20 chars
					if( count($status_name) > 20 ) {
						$error_details = __('The status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the name is not restricted
					if($this->is_restricted_status(strtolower($status_slug))) {
						$error_details = __('That status name is restricted. Please use another name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the status doesn't already exist
					if(is_term($status_slug)) {
						$error_details = __('That status already exists. Please use another name.', 'edit-flow');
						break;
					}
					
					$args = array('description' => $status_description, 'slug' => $status_slug );
					
					// Try to add the status
					$return = $this->add_custom_status($status_name, $args);
					if(!is_wp_error($return)) {
						$msg = __('Status successfully added.', 'edit-flow');
					} else {
						$error_details = __('Could not add the status: ', 'edit-flow') . $return->get_error_message();
					}
					
				} else {
					$error_details = $nonce_fail_msg;
				}
				
				break;
			
			case 'edit':
				$term_id = (int) $_GET['term_id'];
				
				if($term_id && $the_status = get_term($term_id, $this->status_taxonomy)) {
					
					// Stop users from editing restricted statuses
					if($this->is_restricted_status($the_status->slug)) {
						$error_details = __('That status is restricted and cannot be edited. You are welcome to delete it, however.', 'edit-flow'); 
						$update = false;
					} else {
						$update = true;
						$edit_status = $the_status;
					}
				}
				break;
			
			case 'update':
			
				// @TODO if updated status is the same as the default_status, update the default
				
				// Verfiy nonce
				if(wp_verify_nonce($_POST['custom-status-add-nonce'], 'custom-status-add-nonce')) {
					$term_id = (int) $_POST['term_id'];
					
					$status_name = esc_html(trim($_POST['status_name']));
					$status_slug = sanitize_title($_POST['status_name']);
					$status_description = esc_html($_POST['status_description']);
					
					// Check if name field was filled in
					if(!$status_name || empty($status_name)) {
						$error_details = __('Please enter a name for the status', 'edit-flow');
						break;
					}

					// Check that the name isn't numeric
					if( (int)$status_name != 0 ) {
						$error_details = __('Please enter a valid name.', 'edit-flow');
					}

					// Check that the status name doesn't exceed 20 chars
					if( count($status_name) > 20 ) {
						$error_details = __('The status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the name is not restricted
					if($this->is_restricted_status(strtolower($status_slug))) {
						$error_details = __('That status name is restricted. Please use another name.', 'edit-flow');
						break;
					}
					
					// Check to make sure the status doesn't already exist
					if(is_term($status_slug) && (get_term($term_id, $this->status_taxonomy)->slug != $status_slug)) {
						$error_details = __('That status already exists. Please use another name.', 'edit-flow');
						break;
					}
					
					// get status_name & status_description
					$args = array( 'name' => $status_name, 'description' => $status_description, 'slug' => $status_slug );
					
					$return = $this->update_custom_status($term_id, $args);
					if(!is_wp_error($return)) {
						$msg = __('Status successfully updated.', 'edit-flow');						
					} else {
						$error_details = __('Could not update the status: ', 'edit-flow') . $return->get_error_message();
					}
					
				} else {
					$error_details = $nonce_fail_msg;
				}

				
				break;
			
			case 'delete':
			
				// @TODO if updated status is the same as the default_status, update the default
			
				// Verfiy nonce
				if(wp_verify_nonce($_GET['_wpnonce'], 'custom-status-delete-nonce')) {
					$term_id = (int) $_GET['term_id'];

					// Check to make sure the status doesn't already exist
					if(!is_term($term_id, $this->status_taxonomy)) {
						$error_details = __('That status does not exist. Try again?', 'edit-flow');
						break;
					}
					
					$return = $this->delete_custom_status($term_id);
					if(!is_wp_error($return)) {
						$msg = __('Status successfully deleted.', 'edit-flow');
					} else {
						$error_details = __('Could not delete the status: ', 'edit-flow') . $return->get_error_message();
					}
						
				} else {
					$error_details = $nonce_fail_msg;
				}
				
				break;
				
			default:
				$update = false;
				break;
				
		}
		
		if($error_details) {
			$msg = __('There was an error with your request: ', 'edit-flow');
			$msg .= '<br /><strong>'.$error_details.'</strong>';
			$msg_class = 'error';
		}
		
		$statuses = $this->get_custom_statuses();
	
		?>
		<div class="wrap">
			<div id="icon-tools" class="icon32"><br /></div>
			<h2><?php _e('Custom Post Statuses', 'edit-flow') ?></h2>
			
			<?php if(!$edit_flow->get_plugin_option('custom_statuses_enabled')) : ?>
				<div class="error" id="plugin-message">
					<p><strong><?php _e('Note: Custom Statuses are currently disabled', 'edit-flow') ?></strong></p>
					<p><em><?php _e('While you are free to add, edit and delete to your heart\'s content, please note that you will not be able to assign posts to custom statuses unless you <a href="'. EDIT_FLOW_SETTINGS_PAGE.'">enable them</a>.', 'edit-flow') ?></em></p>
				</div>
			<?php endif ?>
			
			<?php if($msg) : ?>
				<div id="message" class="<?php echo $msg_class ?> fade below-h2">
					<p><?php echo $msg ?></p>
				</div>
			<?php endif; ?>
			
			
			<div id="col-container">
				<div id="col-right">
					<div class="col-wrap">
							
						<table cellspacing="0" class="widefat fixed">
							<thead>
								<tr>
									<th class="manage-column column-cb check-column" id="cb" scope="col"></th>
									<th style="" class="manage-column column-name" id="name" scope="col"><?php _e('Name', 'edit-flow') ?></th>
									<th style="" class="manage-column column-description" id="description" scope="col"><?php _e('Description') ?></th>
									<th style="" class="manage-column column-posts num" id="posts" scope="col"><?php _e('# of Posts', 'edit-flow') ?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th style="" class="manage-column column-cb check-column" scope="col"></th>
									<th style="" class="manage-column column-name" scope="col"><?php _e('Name', 'edit-flow') ?></th>
									<th style="" class="manage-column column-description" scope="col"><?php _e('Description', 'edit-flow') ?></th>
									<th style="" class="manage-column column-posts num" scope="col"><?php _e('Posts', 'edit-flow') ?></th>
								</tr>
							</tfoot>
							
							<tbody class="list:post_status" id="the-list">
							
							<?php
							if (is_array($statuses)) {
							
								$delete_nonce = wp_create_nonce('custom-status-delete-nonce');
							
								foreach ($statuses as $status) {
								
									$count = ef_get_custom_status_post_count($status->slug);
								
									$status_link = ef_get_custom_status_filter_link($status->slug);
									$edit_link = ef_get_custom_status_edit_link($status->term_id);
									$delete_link = EDIT_FLOW_CUSTOM_STATUS_PAGE.'&amp;action=delete&amp;term_id='.$status->term_id.'&amp;_wpnonce='.$delete_nonce;
								
									?>
									
									<tr class="iedit alternate" id="status-<?php echo $status->term_id ?>">
										<th class="check-column" scope="row">
										</th>
										<td class="name column-name">
											<a title="Edit <?php esc_attr_e($status->name) ?>" href="<?php echo $edit_link ?>" class="row-title">
											<?php echo $status->name ?>
											</a>
											<br/>
											<div class="row-actions">
												<span class="edit">
													<a href="<?php echo $edit_link ?>">
														<?php _e('Edit', 'edit-flow') ?>
													</a>
													|
												</span>
												<span class="delete">
													<a href="<?php echo $delete_link ?>" class="delete:the-list:status-<?php echo $status->term_id ?> submitdelete" onclick="if(!confirm('Are you sure you want to delete this status?')) return false;">
														<?php _e('Delete', 'edit-flow') ?>
													</a>
												</span>
											</div>
										</td>
										<td class="description column-description">
											<?php esc_html_e($status->description) ?>
										</td>
										<td class="posts column-posts num">
											<a href="<?php echo $status_link ?>" title="View all posts with the status <?php esc_attr_e($status->name) ?>"><?php echo $count ?></a>
										</td>
									<?php
								}
							}
							?>
							</tr>
						</tbody>
					</table>
												
					<div class="form-wrap">
						<p><?php _e('<strong>Note:</strong><br/>Deleting a status does not delete the posts assigned that status. Instead, the posts will be set to the default status: <strong>Draft</strong>', 'edit-flow') ?>.
						</p>
					</div>
						
				</div>
			</div>
		
		
		<div id="col-left">
			<div class="col-wrap">
				<div class="form-wrap">
					<h3><?php echo ($update) ? __('Update Custom Status', 'edit-flow') : __('Add Custom Status', 'edit-flow') ?></h3>
						<form class="add:the-list:" action="<?php echo EDIT_FLOW_CUSTOM_STATUS_PAGE ?>" method="post" id="addstatus" name="addstatus">

							<div class="form-field form-required">
								<label for="status_name"><?php _e('Name', 'edit-flow') ?></label>
								<input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php esc_attr_e($edit_status->name) ?>" />
								<p><?php _e('The name is used to identify the status. (Max: 20 characters)', 'edit-flow') ?></p>
							</div>
						
							<div class="form-field">
								<label for="status_description"><?php _e('Description', 'edit-flow') ?></label>
								<textarea cols="40" rows="5" id="status_description" name="status_description"><?php esc_attr_e($edit_status->description) ?></textarea>
							    <p><?php _e('The description is mainly for administrative use, just to give you some context on what the custom status is to be used for or means.', 'edit-flow') ?></p>
							</div>
							
							<input type="hidden" name="action" value="<?php echo ($update) ? 'update' : 'add' ?>" />
							<?php if($update) : ?>
								<input type="hidden" name="term_id" value="<?php echo $edit_status->term_id ?>" />
							<?php endif; ?>
							<input type="hidden" name="page" value="edit-flow/php/custom_status.php" />
							<input type="hidden" name="custom-status-add-nonce" id="custom-status-add-nonce" value="<?php echo wp_create_nonce('custom-status-add-nonce') ?>" />
							
							<p class="submit"><input type="submit" value="<?php echo ($update) ? __('Update Custom Status', 'edit-flow') : __('Add Custom Status', 'edit-flow') ?>" name="submit" class="button"/></p>
						</form>
					</div>
				</div>
			</div>
		</div>
			
		</div>
		<?php
	} // END: admin_page()
	
	
} // END: class custom_status



/**
 * Gets the proper name for the custom status
 * Can be fetched using either the "slug" or "id"
 * @param $field The field to search by: "slug" or "id"
 * @param $value The value to search for
 */
function ef_get_status_name ( $field, $value ) { 
	switch($value) {
		case 'publish':
			return 'Published';	
		case 'draft':
			return 'Draft';
		case 'future':
			return  'Scheduled';
		case 'private':
			return 'Private';
		case 'pending':
			return 'Pending Review';
			
		default:
			switch($field) {
				case 'slug':
					$status = get_term_by('slug', $value, 'post_status');
					break;
				case 'id':
					$status = get_term_by('term_id', $value, 'post_status');
					break;
				default:
					return null;
			}
			break;
	}
	return $status->name;
} // END: ef_get_status_name()

function ef_get_custom_status_filter_link ( $slug ) {
	return 'edit.php?post_status='.$slug;
}

function ef_get_custom_status_edit_link( $id ) {
	return EDIT_FLOW_CUSTOM_STATUS_PAGE.'&amp;action=edit&amp;term_id='.$id;
}

function ef_get_custom_status_post_count ( $status ) {
	global $wpdb;
	
	if(is_int($status)) {
		$status = get_term_by('term_id', $status, 'post_status')->slug;
	}
	
	$query = $wpdb->prepare("SELECT count(ID) FROM $wpdb->posts WHERE post_status = %s", $status);
	
	return $wpdb->get_var($query);
}

?>