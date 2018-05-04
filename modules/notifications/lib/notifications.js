jQuery(document).ready(function($) {
	$('#ef-post_following_users_box ul').listFilterizer();	

	var params = {
		action: 'save_notifications',
		post_id: $('#post_ID').val(),
	};

	var toggle_no_access_badge = function( container, user_has_no_access ) {
		if ( $( container ).siblings( 'span' ).length ) {
			$( container ).siblings( 'span' ).remove();
		} else if ( user_has_no_access ) {
			var span = $( '<span />' );
			span.text( 'No Access' );
			$( container ).parent().prepend( span );
		}
	}

	$(document).on('click','.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox', function() {
		var user_group_ids = [];
		var parent_this = $(this);
		params.ef_notifications_name = $(this).attr('name');
		params._nonce = $("#ef_notifications_nonce").val();

		$(this)
			.parents('.ef-post_following_list')
			.find('input:checked')
			.map(function(){
				user_group_ids.push($(this).val());
			})

		params.user_group_ids = user_group_ids;

		$.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : params,
			success : function( response ) { 
				var backgroundColor = parent_this.css( 'background-color' );
				$(parent_this.parents('li'))
					.animate( { 'backgroundColor':'#CCEEBB' }, 200 )
					.animate( { 'backgroundColor':backgroundColor }, 200 );

					// Toggle the "No Access" badge if the selected user does not have access.
					if ( undefined !== response.data ) {
						var user_has_no_access = response.data.subscribers_with_no_access.includes( parseInt( $( parent_this ).val() ) );
						toggle_no_access_badge( $( parent_this ), user_has_no_access );
					}
			},
			error : function(r) { 
				$('#ef-post_following_users_box').prev().append(' <p class="error">There was an error. Please reload the page.</p>');
			}
		});
	});
});