/* global wp, jQuery, ajaxurl, ef_notifications_localization, document, wpListL10n, ef_post_author_id */

const BADGES_STATUS = {
	error: 'error',
	warning: 'warning',
	success: 'success',
};

const BADGES = {
	NO_ACCESS: {
		id: 'no_access',
		name: ef_notifications_localization.no_access,
		status: BADGES_STATUS.error,
	},
	NO_EMAIL: {
		id: 'no_email',
		name: ef_notifications_localization.no_email,
		status: BADGES_STATUS.error,
	},
	POST_AUTHOR: {
		id: 'post_author',
		name: ef_notifications_localization.post_author,
		class: 'ef-badge-neutral',
	},
	AUTO_SUBSCRIBE: {
		id: 'auto_subscribed',
		name: ef_notifications_localization.auto_subscribed,
		class: 'ef-badge-neutral',
	},
};

const getBadge = ( $el, badge ) => {
	const exists = $el.find( `[data-badge-id='${ badge.id }']` );

	if ( exists.length ) {
		return jQuery( exists[ 0 ] );
	}
	return null;
};

const badgeTemplate = badge => {
	let classes = 'ef-user-badge';

	if ( BADGES_STATUS.error === badge.status ) {
		classes += ' ef-user-badge-error';
	}

	return `<div class="${ classes }" data-badge-id="${ badge.id }">${ badge.name }</div>`;
};

const addBadgeToEl = ( $el, badge ) => {
	if ( getBadge( $el, badge ) ) {
		return;
	}

	$el.append( badgeTemplate( badge ) );
};

const removeBadgeFromEl = ( $el, badge ) => {
	const existingBadge = getBadge( $el, badge );

	if ( ! existingBadge ) {
		return;
	}

	existingBadge.remove();
};

jQuery( document ).ready( function( $ ) {
	jQuery( '#ef-post_following_users_box ul' ).listFilterizer();

	const params = {
		action: 'save_notifications',
		post_id: jQuery( '#post_ID' ).val(),
	};

	const toggleWarningBadges = function( container, { userHasNoAccess, userHasNoEmail } ) {
		const $el = jQuery( container ).parent();
		const $badgesContainer = $el.closest( 'li' ).find( '.ef-user-list-badges' );

		// "No Access" If this user was flagged as not having access
		if ( userHasNoAccess ) {
			addBadgeToEl( $badgesContainer, BADGES.NO_ACCESS );
		} else {
			removeBadgeFromEl( $badgesContainer, BADGES.NO_ACCESS );
		}

		// "No Email" If this user was flagged as not having an email
		if ( userHasNoEmail ) {
			addBadgeToEl( $badgesContainer, BADGES.NO_EMAIL );
		} else {
			removeBadgeFromEl( $badgesContainer, BADGES.NO_EMAIL );
		}
	};

	const show_post_author_badge = () => {
		const $userListItemActions = jQuery( "label[for='ef-selected-users-" + ef_post_author_id + "'] .ef-user-list-badges" );
		addBadgeToEl( $userListItemActions, BADGES.POST_AUTHOR );
	};

	/**
	 * Until assets are correctly loaded on their respective pages, `ef_post_author_id` should
	 * only have a value on a post page, so only execute `show_post_author_badge` if it has a value
	 */
	if ( 'undefined' !== typeof ef_post_author_id ) {
		show_post_author_badge();
	}

	const showAutosubscribedBadge = () => {
		const $userListItemActions = jQuery( "label[for='ef-selected-users-" + ef_post_author_id + "'] .ef-user-list-badges" );
		addBadgeToEl( $userListItemActions, BADGES.AUTO_SUBSCRIBE );
	};

	const disableAutosubscribeCheckbox = () => {
		jQuery( '#ef-selected-users-' + ef_post_author_id ).prop( 'disabled', true );
	};

	if ( typeof ef_post_author_auto_subscribe !== 'undefined' ) {
		showAutosubscribedBadge();
		disableAutosubscribeCheckbox();
	}

	jQuery( document ).on( 'click', '.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox', function() {
		const userGroupIds = [];
		const checkbox = jQuery( this );
		params.ef_notifications_name = jQuery( this ).attr( 'name' );
		params._nonce = jQuery( '#ef_notifications_nonce' ).val();

		jQuery( this )
			.parents( '.ef-post_following_list' )
			.find( 'input:checked' )
			.map( function() {
				userGroupIds.push( jQuery( this ).val() );
			} );

		params.user_group_ids = userGroupIds;

		$.ajax( {
			type: 'POST',
			url: ( ajaxurl ) ? ajaxurl : wpListL10n.url,
			data: params,

			success: function( response ) {
				// Toggle the warning badges ("No Access" and "No Email") to signal the user won't receive notifications
				const userHasNoAccess = response.data.subscribers_with_no_access.includes( parseInt( jQuery( checkbox ).val(), 10 ) );
				const userHasNoEmail = response.data.subscribers_with_no_email.includes( parseInt( jQuery( checkbox ).val(), 10 ) );

				toggleWarningBadges( jQuery( checkbox ), { userHasNoAccess, userHasNoEmail } );

				// Green 40% by default
				let backgroundHighlightColor = '#90d296';

				if ( userHasNoAccess || userHasNoEmail ) {
					// Red 40% if there's a warning
					backgroundHighlightColor = '#ea8484';
				}

				const backgroundColor = 'transparent';
				jQuery( checkbox.parents( 'label' ) )
					.animate( { backgroundColor: backgroundHighlightColor }, 200 )
					.animate( { backgroundColor: backgroundColor }, 200 );

				// This event is used to show an updated list of who will be notified of editorial comments and status updates.
				jQuery( '#ef-post_following_box' ).trigger( 'following_list_updated' );
			},
			error: function() {
				jQuery( '#ef-post_following_users_box' ).prev().append( ' <p class="error">There was an error. Please reload the page.</p>' );
			},
		} );
	} );
} );
