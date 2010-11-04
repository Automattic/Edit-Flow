<?php
/**
 * Displays indicated message
 * @param $message The message to be displayed
 */
function ef_the_message( $message = '' ) {
	if( !$message && isset( $_REQUEST['message'] ) ) $message = esc_html( $_REQUEST['message'] );
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
function ef_the_errors( $errors ) {
	
	if( is_wp_error( $errors ) ) {
		$errors = $errors->get_error_messages();
	}
	
	$errors = (array)$errors;
	
	if( isset( $_REQUEST['errors'] ) ) {
		$errors = array_merge( $errors, array_map( 'esc_html', (array)$_REQUEST['errors'] ) );
	}
	?>
	<?php if( is_array( $errors ) && ! empty( $errors ) ) : ?>
		<div id="ef-error" class="error fade">
			<p>
				<ul>
					<?php foreach( $errors as $error ) : ?>
						<li><?php echo $error ?></li>
					<?php endforeach; ?>
				</ul>
			</p>
		</div>
	<?php endif; ?>
	<?php
}
