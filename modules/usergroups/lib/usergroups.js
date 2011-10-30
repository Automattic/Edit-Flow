jQuery(document).ready(function () {
	jQuery('ul#ef-post_following_users li').quicksearch({
		position: 'before',
		attached: 'ul#ef-post_following_users',
		loaderText: '',
		delay: 100
	})
	jQuery('#ef-usergroup-users ul').listFilterizer();
});