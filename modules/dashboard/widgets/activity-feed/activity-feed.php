<?php
/*
 * A widget to follow user activity. 
 *
 */
class EF_Activity_Feed_Widget {

	public $edit_cap = 'edit_post';

	function __construct() {
		add_action( 'wp_ajax_ef_activity_feed_add_comment', array( $this, 'activity_feed_add_comment' ) );
	}

	/**
	 * Add some localization to labels.
	 *
	 * @since 0.8
	 */
	function add_admin_scripts() {
		EditFlow()->dashboard->enqueue_datepicker_resources();
		wp_enqueue_script( 'edit-flow-activity-feed-widget-js', plugins_url( '/activity-feed/lib/activity-feed.js', dirname(__FILE__) ), false, EDIT_FLOW_VERSION );
		wp_localize_script( 'edit-flow-activity-feed-widget-js', 'af_widget_text_labels', array(
			'hide_metadata' => __( 'Hide metadata', 'edit-flow' ),
			'view_metadata' => __( 'View metadata', 'edit-flow' ),
			'show_parents' => __('View parents', 'edit-flow'),
			'show_children' => __('View children', 'edit-flow'),
			'hide_parents' => __('Hide parents', 'edit-flow'),
			'hide_children' => __('Hide children', 'edit-flow'),
			)
		);
		wp_enqueue_style( 'edit-flow-activity-feed-widget-css', plugins_url( '/activity-feed/lib/activity-feed.css', dirname( __FILE__ ) ), false, EDIT_FLOW_VERSION );
	}
	
	/**
	 * Add Activity Feed widget
	 * Shows a list of comments and posts sorted by most recent
	 */
	public function init() {
		wp_add_dashboard_widget( 'activity_feed_widget', __( 'Activity Feed', 'edit-flow' ), array( $this, 'activity_feed_widget_wrapper' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );  
		$this->edit_cap = apply_filters( 'ef_activity_feed_edit_cap', $this->edit_cap );
	}

	/**
	 * In order to preserve event delegation when making modifications to html
	 * with jQuery, need a wrapper div that all events will bind to. activity_feed_widget_init()
	 * will be called to refresh the widget on adding a comment, so the wrapper
	 * needs to be added one level above.
	 *
	 * @since 0.8
	 */
	public function activity_feed_widget_wrapper() {
		echo '<div id="af-outer-wrap">';
		echo $this->activity_feed_widget_init();
		echo '</div>';
	}

	 /**
	  * Initial generation of Activity Feed widget content. Will be used to refresh
	  * the widget when a comment is made.
	  *
	  * @since 0.8
	  */
	public function activity_feed_widget_init() {
		//Get a list of recent posts and comments.
		$recent_posts_and_comments = $this->recent_posts_and_comments();

		//To Do: Allow filter to sort by more than posts and comments (add pages, post types, etc).
		$show_posts_and_comments = apply_filters('ef_activity_feed_show_posts_and_comments', array( 'post', 'comment' ) );
		?>
			<div id="af-inner-wrap">
				<ul id="af-mixed-list">
				<?php 
					if( !empty( $recent_posts_and_comments ) ) :
						//Loop through the combined list of posts and comments
						foreach( $recent_posts_and_comments as $post_or_comment ) :
							//Determine if we're looking at a comment or a post
							if( get_class( $post_or_comment ) == "WP_Post" 
								&&  array_search('post', $show_posts_and_comments) !== false )
								$this->single_post( $post_or_comment );
							else if ( get_class ( $post_or_comment ) != "WP_Post"
								&& array_search('comment', $show_posts_and_comments) !== false )
								$this->single_comment( $post_or_comment );
							else
								continue;
							
						endforeach;
					else:
						echo "No recent activity.";
					endif;
				?>
				</ul>
				<?php echo EditFlow()->editorial_comments->the_comment_form( true ); ?>
			</div>	
		<?php
	}

	/**
	 * Get the combined list of recent posts and comments.
	 * @return (WP_Post|stdClass)	  Mixed list of posts and comments
	 *
	 * @since 0.8
	 */
	function recent_posts_and_comments() {
		global $current_user;

		get_currentuserinfo();

		//Get recent posts and comments the user is following
		$num_items_to_show = apply_filters( 'af_num_items_to_show', $num_items_to_show = 5 );
		$recent_posts = $recent_comments = array();

		$recent_posts = EditFlow()->notifications->get_user_following_posts( $current_user->ID, array( 'posts_per_page' => $num_items_to_show ) );
		
		if( EditFlow()->helpers->module_enabled( 'editorial_comments' ) )
			$recent_comments = EditFlow()->notifications->get_user_following_comments( $num_items_to_show );

		//Organize and sort by date (filter this somehow to display certain number of each/total?)
		$posts_and_comments = array_merge( $recent_posts, $recent_comments );
		usort( $posts_and_comments, array( $this, "sort_posts_and_comments" ) );
		array_splice( $posts_and_comments, $num_items_to_show );

		return $posts_and_comments;
	}

	/**
	 * Sorting function for array of comments and posts
	 * @param  WP_Post $a A post object
	 * @param  stdClass $b An stdClass object representing a comment
	 * @return int Representing less than, greater than or equal to
	 *
	 * @since 0.8
	 */
	static function sort_posts_and_comments( $a, $b ) {
		$a_date = ( get_class( $a ) == 'WP_Post' ) ? $a->post_modified : $a->comment_date;
		$b_date = ( get_class( $b ) == 'WP_Post' ) ? $b->post_modified : $b->comment_date;

		if( strtotime( $a_date ) == strtotime( $b_date ) )
			return 0;

		return ( strtotime( $a_date ) > strtotime( $b_date ) ) ? -1 : +1;
	}

	/**
	 * Generate a single post list item
	 * @param  WP_Post  $post
	 * @param  int $li_id List item identifier
	 *
	 * @since 0.8
	 */
	function single_post( $post ) {
		global $current_user;

		$url = esc_url( get_edit_post_link( $post->ID ) );
		$title = esc_html( $post->post_title );
		$status = EditFlow()->custom_status->get_custom_status_by( 'slug', $post->post_status );
		
		//If it's not a custom status, it's published?
		if( !$status ) {
			$status_slug = get_post_status( $post->ID );
			switch( $status_slug ) {
				//Same way post.php handles statuses
				case 'private':
					$status_name = __( 'Privately Published', 'edit-flow' );
					break;
				case 'publish':
					$status_name =  __( 'Published', 'edit-flow' );
					break;
				case 'future':
					$status_name =  __( 'Scheduled', 'edit-flow' );
					break;
				case 'pending':
					$status_name = __( 'Pending Review', 'edit-flow' );
					break;
				case 'draft':
				case 'auto-draft':
					$status_name =  __( 'Draft', 'edit-flow' );
				break;
				default:
					$status_name = "";
				break;
			}

		}
		else {
			$status_slug = $status->slug;
			$status_name = $status->name;
		}

		$status_slug = esc_html( $status_slug );
		$status_name = esc_html( $status_name );

		if( EditFlow()->helpers->module_enabled( 'editorial_metadata' ) )
			$view_meta = $this->activity_feed_view_efmeta( $post );

		get_currentuserinfo();

		?>
			<li id="post-<?php echo $post->ID ?>" class="af-post">
				<h4 class="af-bigger <?php echo $status_slug; ?>"><a href="<?php echo $url ?>" title="<?php _e( 'Edit this post', 'edit-flow' ) ?>"><?php echo $title; ?></a><span class="af-light"><?php if( !empty( $status_name ) ) { ?> &nbsp;-&nbsp;</span> <?php echo $status_name; } ?></h4>
				<span class="af-timestamp"><?php _e( 'Updated on', 'edit-flow' ) ?> <?php echo get_post_modified_time( 'F j, Y \\a\\t g:i a', false, $post ) ?></span>
				<?php if( current_user_can( $this->edit_cap , $post->ID ) ): ?>
					<p class="af-row-actions af-row-hidden">
						<?php if( EditFlow()->helpers->module_enabled( 'editorial_comments' ) ): ?>
							<span class="af-new-comment"><a href="#" onclick="inlineEditorialCommentMeta.open(this, 0, <?php echo esc_html( $post->ID ) ?>);return false;"><?php _e( 'New comment', 'edit_flow' ); ?></a></span>
						<?php endif; ?>
						<?php if( EditFlow()->helpers->module_enabled( 'editorial_metadata' ) ): ?>
							<?php if( current_user_can( $this->edit_cap, $post->ID ) && !empty( $view_meta ) ): ?>
								<span class="af-view-meta"><?php if( EditFlow()->helpers->module_enabled( 'editorial_comments' ) ) { ?> | <?php } ?><a href="#" onclick="inlineEditorialCommentMeta.viewContent(this);return false;"><?php _e( 'View metadata', 'edit_flow' ); ?></a></span>
							<?php endif; ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<input type="hidden" name="af-item-post_id" value="<?php echo esc_html( $post->ID ); ?>" />
				<div class="hidden-post-information">
					<?php 
						if( current_user_can( $this->edit_cap, $post->ID ) && isset( $view_meta ) ) 
							echo '<div class="af-view-metadata hide-af-feed-info">' . $view_meta . '</div>';
					?>
				</div>
			</li>
		<?php
	}

	/**
	 * Generate a single comment list item
	 * @param  stdClass  $comment
	 * @param  integer $li_id
	 *
	 * @since 0.8
	 */
	function single_comment( $comment ) {
		global $current_user;

		$list_of_parents = $list_of_children = array();

		//Useful information about the comment
		$comment_post = get_post( $comment->comment_post_ID );
		$text = esc_html( get_comment_excerpt( $comment->comment_ID ) );

		//Useful information to be displayed about that post
		$url = esc_url( get_edit_post_link( $comment_post->ID ) );
		$comment_url = $url.'#comment-'.$comment->comment_ID;
		$title = esc_html( $comment_post->post_title );

		//Get the parent and grand-parent of this comment
		$list_of_parents = $this->get_parent_comments( $comment, 2 );

		//Get the child and grand-child of this comment
		$parent_comment = get_comments( array( 'parent' => $comment->comment_ID, 'status' => 'editorial-comment', 'number' => 1 ) );
		$list_of_children = $this->get_children_comments( $parent_comment, 2 );

		get_currentuserinfo();

		$from = ( ( !empty( $comment->comment_parent ) ) ? $from = 'Reply from ' : $from = 'From ' ) . esc_html( $comment->comment_author ) . ' on ';

		?>
			<li id="comment-<?php echo $comment->comment_ID ?>" class="af-comment">
				<h4 class="af-larger"><span class="af-comment-from"><?php echo $from ?></span><a href="<?php echo $url ?>" title="<?php __( 'View this post', 'edit-flow' ) ?>"><?php echo $title; ?></a> <a href="<?php echo $comment_url ?>" title="<?php __( 'View this comment', 'edit-flow' ) ?>">#</a></h4>
					<ul class="af-outer-comment-wrap">
						<?php $this->format_comment_text( $comment, true ); ?>
					</ul>
					<?php if( current_user_can( $this->edit_cap, $comment->comment_post_ID ) ): ?>
						<p class="af-row-actions af-row-hidden">
							<span class="af-new-comment"><a href="#" onclick="inlineEditorialCommentMeta.open(this, 0, <?php echo esc_html( $comment->comment_post_ID ) ?>);return false;" >New comment</a></span> | 
							<span class="af-comment-reply"><a href="#" onclick="inlineEditorialCommentMeta.open(this, <?php echo esc_html( $comment->comment_ID ) ?>, <?php echo esc_html( $comment->comment_post_ID ) ?>);return false;" >Reply to comment</a></span>
							<?php if( !empty( $list_of_parents ) ) { ?>
									<span class="af-comment-parent"> | <a href="#" onclick="inlineEditorialCommentMeta.viewContent(this);return false;">View parents</a></span>
							<?php } if( !empty( $list_of_children ) ) { ?>
									<span class="af-comment-children"> | <a href="#" onclick="inlineEditorialCommentMeta.viewContent(this);return false;">View children</a></span>
							<?php } ?>
						</p>
					<?php endif; ?>
					<div class="hidden-post-information">
						<?php 
							if( current_user_can( $this->edit_cap, $comment->comment_post_ID ) ) {
								if( !empty( $list_of_parents ) )
									echo '<div class="af-show-parents hide-af-feed-info">' . $this->activity_feed_view_conversation( $list_of_parents, 'parents' ) . '</div>';
								if( !empty( $list_of_children ) )
									echo '<div class="af-show-children hide-af-feed-info">' . $this->activity_feed_view_conversation( $list_of_children, 'children' ) . '</div>';
							}
						?>
					</div>
			</li>
		<?php
	}

	/**
	 * Loop to get parent comments of comment
	 * @param  $comment WP_Comment
	 * @param  $num_parents int
	 * @return array
	 *
	 * @since 0.8
	 */
	function get_parent_comments( $comment, $num_parents ) {
		$list_of_parents = array();

		for( $parents_count = 0; $parents_count < $num_parents; $parents_count++ ) {
			if( $comment && $comment->comment_parent ) {
				$list_of_parents[] = $comment->comment_parent;
				$comment = get_comment( $comment->comment_parent );
			}
		}
		return $list_of_parents;
	}

	/**
	 * Loop to get comment children
	 * @param  WP_Comment $comment
	 * @param  int $num_children
	 * @return array List of comment children
	 *
	 * @since 0.8
	 */
	function get_children_comments( $comment, $num_children ) {
		//Loop through and get child comments
		$list_of_children = array();

		if( !empty( $comment ) ) {
			for( $i = 0; $i < $num_children; $i++ ) {
				if( isset( $comment[0] ) ) {
					$list_of_children[] = $comment[0]->comment_ID;
					$comment = get_comments( array( 'parent' => $comment[0]->comment_ID, 'status' => 'editorial-comment', 'number' => 1 ) );
				}
			}
		}
		return $list_of_children;
	}

	/**
	 * Format the text of a comment. If it's a single comment,
	 * it's a comment anchor. If it's a conversation it's a list
	 * of parent of children
	 * @param WP_Comment $comment
	 * @param $conversation_or_single
	 *
	 * @since 0.8
	 */
	function format_comment_text( $comment, $conversation_or_single = false ) { 
		$time = date( 'F j, Y \\a\\t g:i a', strtotime( $comment->comment_date ) );
		$classes = 'af-inner-comment-wrap';
		$classes .= ( $conversation_or_single ) ? ' af-anchor-li' : '';
		echo '<li class="' . $classes . '">';
		echo '<span class="af-comment-content">' . esc_html( $comment->comment_content ) . '</span>';
		if( $comment->comment_author && $time )
			echo '<p class="af-comment-user-time">' . esc_html( $comment->comment_author ) . ' said on ' . $time . '</p>';
		echo '</li>';
	}

	/**
	 * Generate a comment conversation
	 * @param array $comment_ids
	 * @return string List of comments
	 * 
	 * @since 0.8
	 */
	function activity_feed_view_conversation( $comment_ids  ) {
		//Get comment parents

		if( empty( $comment_ids ) )
			return;

		$list_of_comments = '<ul>';

		foreach( $comment_ids as $comment_key => $comment_id ) {
			if( $comment_id ) {
				ob_start();
					$comment = get_comment( $comment_id );
					$this->format_comment_text( $comment );
					$list_of_comments .= ob_get_contents();
				ob_end_clean();
			}
		}

		$list_of_comments .= '</ul>';

		return $list_of_comments;
	}

	/**
	 * Generate static metadata to display
	 * @param  WP_Post $post
	 * @return string 
	 *
	 * @since 0.8
	 */
	function activity_feed_view_efmeta( $post ) {

		$editorial_metadata_info = "";
		$editorial_metadata_table = '<table class="af-ed-metadata"><tbody>';
		$terms = EditFlow()->editorial_metadata->get_editorial_metadata_terms();

		foreach( $terms as $term ) {
			if( !$term->viewable )
				continue;

			$editorial_metadata_value = get_post_meta( $post->ID, '_ef_editorial_meta_' . $term->type . '_' . $term->slug, true );
			
			if( empty( $editorial_metadata_value ) )
				continue;

			$editorial_metadata_info .= '<tr class="af-single-ed-meta">';
			$editorial_metadata_info .= '<th>' . $term->name . ': </th>';
			$editorial_metadata_info .= '<td>' . EditFlow()->editorial_metadata->generate_editorial_metadata_term_output( $term, $editorial_metadata_value ) . '</td>';
			$editorial_metadata_info .= '</tr>';
		}

		$editorial_metadata_table .= $editorial_metadata_info;
		$editorial_metadata_table .= '</tbody></table>';
		
		if( empty( $editorial_metadata_info ) )
			return;
		else
			return $editorial_metadata_table;
	}

	/**
	 * Add a comment to post with ajax.
	 * @return WP_Ajax_Response
	 *
	 * @since 0.8
	 */
	function activity_feed_add_comment() {
		global $current_user;

		$id = absint( $_POST['post_id'] );
		// Verify nonce
		if ( !wp_verify_nonce( $_POST['_nonce'], 'comment') )
			die( __( "Nonce check failed. Please ensure you're supposed to be adding editorial comments.", 'edit-flow' ) );

		get_currentuserinfo();

		//This should be taken care of when calling ajax_insert_comment(),
		//but it never hurts to be careful
      	if ( ! current_user_can( $this->edit_cap, $id ) )
			die( __('Sorry, you don\'t have the privileges to view editorial metadata. Please talk to your Administrator.', 'edit-flow' ) );

     	$comment_id = EditFlow()->editorial_comments->ajax_insert_comment();

		$response = new WP_Ajax_Response();
		
		ob_start();
			$this->activity_feed_widget_init();
			$posts_and_comments = ob_get_contents();
		ob_end_clean();
		
		$response->add( array(
			'what' => 'posts_and_comments',
			'data' => $posts_and_comments,
			'id' => $comment_id,
		));
	
		$response->send();

		die();
	}
} //End Activity Feed widget class
?>