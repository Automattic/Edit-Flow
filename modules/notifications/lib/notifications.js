jQuery(document).ready(function($) {
	$('#ef-post_following_users_box ul').listFilterizer();	

	var params = {
		action: 'save_notifications',
		post_id: $('#post_ID').val(),
	};

	// Display the user/group follower count in the post submit box
	ef_displayFollowerCountInSubmitBox();

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
				// Update the user/group follower count in the post submit box
				ef_displayFollowerCountInSubmitBox();
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
 * Count the users and user groups who will be notified of a status change
 * Display the message in the submit box
 */
var ef_displayFollowerCountInSubmitBox = function() {
	var checkedFollowers, checkedUserGroups, countDisplay, message = '', followerMessage = '', userGroupMessage = '',conjunction = '';

	// Get checked checkboxes
	checkedFollowers = jQuery('.ef-post_following_list li input:checkbox:checked');
	checkedUserGroups = jQuery('#ef-following_usergroups li input:checkbox:checked');
	// checkedFollowers includes user groups
	userCount = checkedFollowers.length - checkedUserGroups.length;
	userGroupCount = checkedUserGroups.length;

	// The <span> within which the message will be displayed
	countDisplay = jQuery('#post-follower-count-display');

	// Create the individual messages
	if (userCount > 0) {
		followerMessage = userCount + ((userCount === 1) ? ' user' : ' users');
	}
	if (userGroupCount > 0) {
		userGroupMessage = userGroupCount + ((userGroupCount === 1) ? ' user group' : ' user groups');
	}

	if (userCount > 0 && userGroupCount > 0) {
		// Both will be displayed, so we need a conjunction
		conjunction = ' & ';
		message = (followerMessage + conjunction + userGroupMessage);
	} else if (userCount > 0 || userGroupCount > 0) {
		// Only one will be displayed, the other is an empty string
		message = (followerMessage + userGroupMessage);
	} else {
		// Default message
		message = 'none';
	}

	// Print the message
	countDisplay.html(message);
};
