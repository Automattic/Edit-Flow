<?php
/**
 * A notepad for the dashboard
 */

class EF_Dashboard_Notepad_Widget {

	const notepad_post_type = 'dashboard-note';

	public $edit_cap = 'edit_others_posts';

	function __construct() {
		// Silence is golden
	}

	public function init() {

		register_post_type( self::notepad_post_type );

		$this->edit_cap = apply_filters( 'ef_dashboard_notepad_edit_cap', $this->edit_cap );

		add_action( 'admin_init', array( $this, 'handle_notepad_update' ) );
	}

	/**
	 * Handle a dashboard note being created or updated
	 *
	 * @since 0.8
	 */
	public function handle_notepad_update() {
		global $pagenow;

		if ( 'index.php' != $pagenow
		|| ( empty( $_REQUEST['action'] ) || 'dashboard-notepad' != $_REQUEST['action'] ) )
			return;

		check_admin_referer( 'dashboard-notepad' );

		if ( ! current_user_can( $this->edit_cap ) )
			wp_die( EditFlow()->dashboard->messages['invalid-permissions'] );

		$current_id = (int)$_REQUEST['notepad-id'];
		$current_notepad = get_post( $current_id );
		$new_note = array(
				'post_content'           => wp_filter_nohtml_kses( $_REQUEST['note'] ),
				'post_type'              => self::notepad_post_type,
				'post_status'            => 'draft',
				'post_author'            => get_current_user_id(),
			);
		if ( $current_notepad
			&& self::notepad_post_type == $current_notepad->post_type
			&& ! isset ( $_REQUEST['create-note'] ) ) {
			$new_note['ID'] = $current_id;
			wp_update_post( $new_note );
		} else {
			wp_insert_post( $new_note );
		}

		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Notepad Widget
	 * Editors can leave notes in the dashboard for authors and contributors
	 *
	 * @since 0.8
	 */
	public function notepad_widget() {

		$args = array(
				'posts_per_page'   => 1,
				'post_status'      => 'draft',
				'post_type'        => self::notepad_post_type,
			);
		$posts = get_posts( $args );
		$current_note = ( ! empty( $posts[0]->post_content ) ) ? $posts[0]->post_content : '';
		$current_id = ( ! empty( $posts[0]->ID ) ) ? $posts[0]->ID : 0;
		$current_post = ( ! empty( $posts[0] ) ) ? $posts[0] : false;

		if ( $current_post )
			$last_updated = '<span id="dashboard-notepad-last-updated">' . sprintf( __( '%1$s last updated on %2$s', 'edit-flow' ), get_user_by( 'id', $current_post->post_author )->display_name, get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $current_post ) ) . '</span>';
		else
			$last_updated = '';

		if ( current_user_can( $this->edit_cap ) ) {
			echo '<form id="dashboard-notepad">';
			echo '<input type="hidden" name="action" value="dashboard-notepad" />';
			echo '<input type="hidden" name="notepad-id" value="' . esc_attr( $current_id ) . '" />';
			echo '<textarea style="width:100%" rows="10" name="note">';
			echo esc_textarea( trim( $current_note ) );
			echo '</textarea>';
			echo '<p class="submit">';
			echo $last_updated;
			echo '<span id="dashboard-notepad-submit-buttons">';
			submit_button( __( 'Update Note', 'edit-flow' ), 'primary', 'update-note', false );
			echo '</span>';
			echo '<div style="clear:both;"></div>';
			wp_nonce_field( 'dashboard-notepad' );
			echo '</form>';
		} else {
			echo '<form id="dashboard-notepad">';
			echo '<textarea style="width:100%" rows="10" name="note" disabled="disabled">';
			echo esc_textarea( trim( $current_note ) );
			echo '</textarea>';
			echo $last_updated;
			echo '</form>';
		}
	}

}