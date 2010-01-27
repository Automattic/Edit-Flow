<?php if( current_user_can('edit_usergroups') ) : ?>
	<?php
	// Load POST vars for error handling
	foreach ( array('usergroup_name' => 'name', 'usergroup_slug' => 'slug', 'usergroup_description' => 'description') as $post_field => $var ) {
		$var = $var;
		if ( ! isset($usergroup->$var) )
			$usergroup->$var = isset($_POST[$post_field]) ? esc_html($_POST[$post_field]) : '';
	}
	if( $update ) {
		$delete_nonce = wp_create_nonce('custom-status-delete-nonce');
		$delete_link = ef_get_the_usergroup_delete_link( $usergroup->slug, $delete_nonce );
	}
	?>

	<?php ef_the_message( $message ) ?>
	<?php ef_the_errors( $result ) ?>
	<form class="form-usergroups" method="post" id="addusergroup" name="addusergroup">
		<div id="col-right">
			<div class="col-wrap">
				<div class="form-wrap">
					<h4><?php _e('Users', 'edit-flow') ?></h4>
					<?php 
					$select_form_args = array (
						'input_id' => 'usergroup_users'
						);
					?>
					<?php ef_users_select_form($usergroup_users, $select_form_args) ?>
				</div>
			</div>
		</div>
		
		<div id="col-left">
			<div class="col-wrap">
				<div class="form-wrap">
					<div class="form-field">
						<label for="usergroup_name"><?php _e('Name', 'edit-flow') ?></label>
						<input type="text" size="20" maxlength="30" id="usergroup_name" name="usergroup_name" value="<?php echo esc_attr($usergroup->name) ?>" />
						<p><?php _e('The name is used to identify the usergroup around the WordPress administration interface.', 'edit-flow'); ?></p>
					</div>
				
					<div class="form-field">
						<label for="usergroup_description"><?php _e('Description', 'edit-flow') ?></label>
						<textarea cols="40" rows="5" id="usergroup_description" name="usergroup_description"><?php echo $usergroup->description ?></textarea>
						<p><?php _e('Give the usergroup a sentence or two to describe its function.', 'edit-flow') ?></p>
					</div>
					
					
					<input type="hidden" name="do" value="<?php echo ($update) ? 'update' : 'insert' ?>" />
					<?php if($update) : ?>
						<input type="hidden" name="usergroup_slug" value="<?php echo esc_attr($usergroup->slug) ?>" />
						<?php wp_nonce_field('edit', 'usergroups-edit-nonce') ?>
					<?php else : ?>
						<?php wp_nonce_field('add', 'usergroups-add-nonce') ?>
					<?php endif; ?>
					
					<p class="submit">
						<input type="submit" value="<?php echo ($update) ? __('Update Usergroup', 'edit-flow') : __('Add Usergroup') ?>" name="submit" class="button-primary" />
						<?php if( $update ) : ?>
							<a href="<?php echo $delete_link ?>" class="delete" onclick="if(!confirm('Are you sure you want to delete this usergroup?')) return false;"><?php _e('Delete', 'edit-flow') ?></a>&nbsp;
						<?php endif; ?>
						<?php if( !$update ) : ?>
							<a href="<?php echo EDIT_FLOW_USERGROUPS_PAGE ?>"><?php _e('Cancel', 'edit-flow') ?></a>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>
	</form>
	
<?php else : ?>
	<p><?php _e('Doesn\'t look like you have the permission to do that friend. How about you move along now?', 'edit-flow') ?></p>
<?php endif; ?>