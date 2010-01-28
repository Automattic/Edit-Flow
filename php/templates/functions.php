<?php
/**
 * Displays indicated message
 * @param $message The message to be displayed
 */
function ef_the_message( $message = '' ) {
	if(!$message && isset($_REQUEST['message'])) $message = esc_html($_REQUEST['message']);
	?>
	<?php if( $message ) : ?>
		<div id="message" class="updated fade">
			<p><?php echo $message ?></p>
		</div>
	<?php endif; ?>
	<?php 
}

/**
 * Displays a list of error messages
 * @param $errors
 */
function ef_the_errors( $errors = array() ) {
	?>
	<?php if( isset($errors) && is_wp_error( $errors ) ) : ?>
		<div id="error" class="error fade">
			<p>
				<?php _e('Uh oh! Looks like we found some errors:', 'edit-flow'); ?>
				<ul>
					<?php foreach( $errors->get_error_messages() as $error ) : ?>
						- <strong><?php echo $error ?></strong>
					<?php endforeach; ?>
				</ul>
			</p>
		</div>
	<?php endif; ?>
	<?php
}
?>