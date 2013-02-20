jQuery(document).ready(function($) {
	$('#ef-post_following_users_box ul').listFilterizer();	

	var params = {
		action: 'save_notifications',
		post_id: $('#post_ID').val(),
	};

	$('.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox').click(function() {
		var user_group_ids = [];
		var parent_this = $(this);
		params.ef_notifications_name = $(this).attr('name');

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
			success : function(x) { },
			error : function(r) { 
				$('#ef-post_following_users_box').prev().insertAfter('There was an error. Please reload the page.');
			}
		});
	});
});