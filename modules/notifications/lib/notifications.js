jQuery(document).ready(function($) {
	$('#ef-post_following_users_box ul').listFilterizer();	

	var params = {
		action: 'save_notifications',
		post_id: $('#post_ID').val(),
	};

	// Display the user/group follower count in the post submit box and watch for changes.
	ef_displayFollowerCountInSubmitBox();
	$( '#ef-post_following_box' ).on( 'following_list_updated', function() {
		ef_displayFollowerCountInSubmitBox();
	} );

	$(document).on('click','.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox', function() {
		var user_group_ids = [];
		var parent_this = $(this);
		params.ef_notifications_name = $(this).attr('name');
		params._nonce = $("#ef_notifications_nonce").val();

		$(this)
			.parent()
			.parent()
			.parent()
			.find('input:checked')
			.map(function(){
				user_group_ids.push($(this).val());
			})

		params.user_group_ids = user_group_ids;

		$.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : params,
			success : function(x) {

				// This event is used to show an updated list of who will be notified of editorial comments and status updates.
				$( '#ef-post_following_box' ).trigger( 'following_list_updated' );

				var backgroundColor = parent_this.css( 'background-color' );
				$(parent_this.parent().parent())
					.animate( { 'backgroundColor':'#CCEEBB' }, 200 )
					.animate( { 'backgroundColor':backgroundColor }, 200 );
			},
			error : function(r) { 
				$('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
			}
		});
	});
});

/**
 * Display the number of users and user groups who will be notified of a status change in the submit box.
 */
var ef_displayFollowerCountInSubmitBox = function() {
	var message_wrapper   = jQuery( '#post-follower-count-display' );
	var subscribed_users  = jQuery( '#ef-post_following_users_box li input:checkbox:checked' ).length;
	var subscribed_groups = jQuery( '#ef-following_usergroups li input:checkbox:checked' ).length;

	// Example: "1 user" or "23 users".
	var users_message_part = '';
	if ( subscribed_users > 0 ) {
		if ( 1 === subscribed_users ) {
			users_message_part = subscribed_users + ' ' + __ef_localize_notifications.user;
		} else {
			users_message_part = subscribed_users + ' ' + __ef_localize_notifications.users;
		}
	}

	// Example: "1 user group" or "23 user groups".
	var groups_message_part = '';
	if ( subscribed_groups > 0 ) {
		if ( 1 === subscribed_groups ) {
			groups_message_part = subscribed_groups + ' ' + __ef_localize_notifications.user_group;
		} else {
			groups_message_part = subscribed_groups + ' ' + __ef_localize_notifications.user_groups;
		}
	}

	var message = __ef_localize_notifications.none;
	if ( subscribed_users > 0 && subscribed_groups > 0 ) {
		message = users_message_part + ' ' + __ef_localize_notifications.ampersand + ' ' + groups_message_part;
	} else if ( subscribed_users > 0 || subscribed_groups > 0 ) {
		// Only one will be displayed, the other is an empty string.
		message = users_message_part + groups_message_part;
	}

	message_wrapper.text( message );
};
