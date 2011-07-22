<?php global $edit_flow; ?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
	<h2><?php _e( 'Custom Post Statuses', 'edit-flow' ); ?></h2>
			
	<?php 
		ef_the_message( $message );
		ef_the_errors( $errors );
	?>
			
			<?php if ( isset( $msg ) && $msg ) : ?>
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
									<th style="" class="manage-column column-name" id="name" scope="col"><?php _e( 'Name', 'edit-flow' ); ?></th>
									<th style="" class="manage-column column-description" id="description" scope="col"><?php _e( 'Description', 'edit-flow' ); ?></th>
									<th style="" class="manage-column column-posts num" id="posts" scope="col"><?php _e( '# of Posts', 'edit-flow' ); ?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th style="" class="manage-column column-cb check-column" scope="col"></th>
									<th style="" class="manage-column column-name" scope="col"><?php _e( 'Name', 'edit-flow') ?></th>
									<th style="" class="manage-column column-description" scope="col"><?php _e( 'Description', 'edit-flow' ); ?></th>
									<th style="" class="manage-column column-posts num" scope="col"><?php _e( 'Posts', 'edit-flow' ); ?></th>
								</tr>
							</tfoot>
							
							<tbody class="list:post_status" id="the-list">
							
							<?php
							
							if ( is_array($custom_statuses) ) {
							
								$delete_nonce = wp_create_nonce('custom-status-delete-nonce');
							
								foreach ($custom_statuses as $status) {
								
									$count = ef_get_custom_status_post_count($status->slug);
								
									$status_link = ef_get_custom_status_filter_link($status->slug);
									$edit_link = ef_get_custom_status_edit_link($status->term_id);
									$delete_link = EDIT_FLOW_CUSTOM_STATUS_PAGE.'&amp;action=delete&amp;term_id='.$status->term_id.'&amp;_wpnonce='.$delete_nonce;
								
									?>
									
									<tr class="iedit alternate" id="status-<?php echo $status->term_id ?>">
										<th class="check-column" scope="row">
										</th>
										<td class="name column-name">
											<?php if ( !$edit_flow->custom_status->is_restricted_status( $status->slug ) ): ?>
											<a title="Edit <?php esc_attr_e($status->name) ?>" href="<?php echo $edit_link ?>" class="row-title">
											<?php echo $status->name ?>
											</a>
											<?php else: ?>
											<strong class="protected-status" style="font-size:12px;"><?php echo $status->name; ?></strong>
											<?php endif; ?>
											<br/>
											<div class="row-actions">
											<?php if ( !$edit_flow->custom_status->is_restricted_status( $status->slug ) ): ?>
												<span class="edit">
													<a href="<?php echo $edit_link ?>">
														<?php _e( 'Edit', 'edit-flow' ); ?>
													</a>
													|
												</span>
												<span class="delete">
													<?php $default_status = $this->ef_get_default_custom_status(); ?>
													<?php $new_status = ($status->slug == $default_status->slug) ? 'Draft' : $default_status->name; ?>
													<a href="<?php echo $delete_link ?>" class="delete:the-list:status-<?php echo $status->term_id ?> submitdelete" onclick="if(!confirm('Are you sure you want to delete this status?\n\nPosts with this status will be assigned to the following status upon deletion: <?php echo $new_status; ?>.')) return false;">
														<?php _e( 'Delete', 'edit-flow' ); ?>
													</a>
												</span>
											<?php else: ?>
												<span style="visibility:hidden;">You can't edit protected statuses.</span>
											<?php endif; ?>													
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
						<?php $default_status = $this->ef_get_default_custom_status()->name; ?>
						<p><?php _e("<strong>Note:</strong><br/>Deleting a status does not delete the posts assigned that status. Instead, the posts will be set to the default status: <strong>$default_status</strong>", 'edit-flow') ?>.
						</p>
					</div>
						
				</div>
			</div>
		
		
		<div id="col-left">
			<div class="col-wrap">
				<div class="form-wrap">
					<h3><?php echo ( isset( $update ) && $update ) ? __('Update Custom Status', 'edit-flow') : __('Add Custom Status', 'edit-flow') ?></h3>
						<form class="add:the-list:" action="<?php echo EDIT_FLOW_CUSTOM_STATUS_PAGE ?>" method="post" id="addstatus" name="addstatus">

							<div class="form-field form-required">
								<label for="status_name"><?php _e('Name', 'edit-flow') ?></label>
								<input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php if ( !empty( $custom_status ) ) esc_attr_e($custom_status->name) ?>" />
								<p><?php _e('The name is used to identify the status. (Max: 20 characters)', 'edit-flow') ?></p>
							</div>
						
							<div class="form-field">
								<label for="status_description"><?php _e( 'Description', 'edit-flow' ); ?></label>
								<textarea cols="40" rows="5" id="status_description" name="status_description"><?php if ( !empty( $custom_status ) ) esc_attr_e($custom_status->description) ?></textarea>
							    <p><?php _e( 'The description is mainly for administrative use, just to give you some context on what the custom status is to be used for or means.', 'edit-flow' ); ?></p>
							</div>

							<input type="hidden" name="action" value="<?php echo ( isset( $update ) && $update ) ? 'update' : 'add' ?>" />
							<?php if ( isset( $update ) && $update ) : ?>
								<input type="hidden" name="term_id" value="<?php if ( !empty( $custom_status ) ) echo $custom_status->term_id ?>" />
							<?php endif; ?>
							<input type="hidden" name="page" value="edit-flow/php/custom_status" />
							<input type="hidden" name="custom-status-add-nonce" id="custom-status-add-nonce" value="<?php echo wp_create_nonce('custom-status-add-nonce') ?>" />
							
							<p class="submit"><input type="submit" value="<?php echo ( isset( $update ) && $update ) ? __('Update Custom Status', 'edit-flow') : __('Add Custom Status', 'edit-flow') ?>" name="submit" class="button"/></p>
						</form>
					</div>
				</div>
			</div>
		</div>
			
		</div>