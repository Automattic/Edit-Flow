=== Edit Flow ===
Contributors: batmoo, danielbachhuber, sbressler, andrewspittle, jboydston
Donate link: http://www.editflow.org/donate/
Tags: edit flow, workflow, editorial, newsroom, management, journalism, post status, custom status, notifications, email, comments, editorial comments, usergroups, calendars, editorial calendar, story budget
Requires at least: 2.9
Tested up to: 3.1-alpha
Stable tag: 0.6

Redefining your editorial workflow

== Description ==

Edit Flow offers a suite of functionality to redefine your editorial workflow within WordPress. Features include:

* Custom Statuses - Create any number of custom statuses to define the stages of your editorial workflow. By default, Edit Flow adds "Assigned", "Pitch", and "Waiting for Feedback" to WordPress' default "Draft" and "Pending Review".
* Editorial Comments - Threaded commenting in the WordPress admin on every post for discussion about the production of a given piece of content. This can cutdown on long-winded back-and-forth email threads as all comments are conveniently displayed within the Edit Post page.
* Email Notifications - Receive email notifications for new editorial comments or when a post changes status. Notifications are delivered to admins and the post author by default, but any users that comment on posts will receive follow up email notifications.
* Usergroups - Similar to roles, usergroups provice a way to group users that perform similar roles in your organization. At the moment, usergroups can be subscribed to posts so that all members receive post updates.
* Calendar - View all of your upcoming posts on a week-by-week calendar, and filter by post status, category, or user.
* Editorial Metadata - Define custom editorial metadata to be attached to every post. Admins can add editorial metadata like "contact information", "assignment description", "due date", or "location" using the following field types: checkbox, date, location, paragraph, text, or user dropdown.
* Story Budget - View all of your upcoming posts in a more traditional story budget view. Posts are grouped by category, and view can be filtered by post status, category, user, or limited to a date range. Hit the print button to take it on the go.

More details and documentation can be found on the [Edit Flow website](http://www.editflow.org/)

For support questions, feedback and ideas, please use the [WordPress.org forums](http://wordpress.org/tags/edit-flow?forum_id=10).

For everything else, say [hello@editflow.org](mailto:hello@editflow.org).

== Installation ==

The easiest way to install this plugin is to go to Add New in the Plugins section of your blog admin and search for "Edit Flow." On the far right side of the search results, click "Install."

If the automatic process above fails, follow these simple steps to do a manual install:

1. Extract the contents of the zip file into your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Write and enjoy the merits of a structured editorial workflow!

== Screenshots ==

1. The ability to Add, Edit, and Delete Custom Statuses
2. Custom Statuses are automatically added to Status dropdown on the Edit Post and Quick Edit Post screens
3. A new column is added to the Edit Posts screen and the ability to filter by status
4. Threaded editorial commenting and additional metadata for posts
5. See posts your upcoming posts on the Calendar
6. Get a sense of your upcoming content on the Story Budget
7. Editorial metadata comes with a bunch of built-in terms, but these can be changed to your heart's content
8. Within each post you can modify the metadata recorded for the post

== Frequently Asked Questions ==

= Why do I get an error like 'Parse error: syntax error, unexpected T_STRING, expecting T_OLD_FUNCTION or T_FUNCTION or T_VAR' on activation? =

Edit Flow requires PHP5+ to work and you're probably not on PHP5. Talk to your hosting provider and hop on the PHP5 bandwagon. It's awesome.

= I don't like the preset roles that are able to view the calendar. How can I edit them? =

There are two ways you can do this. One is to put something like the following code in your functions.php file:

`add_filter( 'ef_view_calendar_cap', 'change_editflows_stupid_default_caps' );

function change_editflows_stupid_default_caps( $cap ) {
	return 'edit_posts';
}`
This will allow anyone with the capability to edit posts to view the calendar. See other capabilities you could use [here](http://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table).

The other option is to install a role management plugin, like Justin Tadlock's excellent [Members plugin](http://wordpress.org/extend/plugins/members/), and let the plugin do the heavy lifting of customizing the roles and capabilities to your heart's content.

= I'm stuck at WordPress 2.8! How do I use v0.3 onwards? =

Upgrade to WordPress 2.9 or later, peeps!

= Edit Flow doesn't do X, Y, and Z. That makes me sad. =

Contact us at [hello@editflow.org](mailto:hello@editflow.org) and we'll see what we can do.

== Other Notes ==

= Custom Post Type Support =

As of v0.6.1, we have added custom post type support for almost all features in Edit Flow. By default, post and pages have most features enabled.

You can add features to other post types one of two ways. (Note: the code below should be called in the init hook for best results.)

* Option 1: supports arg in register_post_type

`
register_post_type( 'event', array( 
	supports( 'title', 'ef_notifications' )
) );
`

* Option 2: add_post_type_support **

`
remove_post_type_support( 'event', 'ef_notifications' ); 
`

You can remove support for features using the `remove_post_type_support` function.

** Note for 2.9.x users **

To add/remove features from either Posts or Pages, you can use the wrapper functions in the $edit_flow object, e.g.

`
global $edit_flow;
$edit_flow->add_post_type_support( 'page', 'ef_story_budget' );
$edit_flow->remove_post_type_support( 'post', 'ef_notifications' );
`

= List of Features =

You can add / remove the following feautures:

* ef_custom_statuses
* ef_notifications
* ef_editorial_comments
* ef_calendar
* ef_story_budget
* ef_editorial_metadata

= Editorial Comments =

**Hiding comments from front-end**

*UPDATE (2010-02-11):* You no longer have to worry about hiding editorial comments if Edit Flow is ever disabled. Edit Flow will automatically show (in the admin) and hide (in the front-end) editorial comments on activation/deactivation.

**Threaded Commenting**

For replies to work properly, you need to enable threaded commenting in your blog's settings. Find it under **Settings > Discussion** and enable the setting called **Enable threaded (nested) comments**.

== Upgrade Notice ==

= 0.6.1 =
@todo Upgrade notice
* We have removed the option to enable/disable Custom Statuses for Pages from the Settings page. Custom Statuses are enabled by default for Pages. To remove support for statuses, please add the following to your functions.php:

`
function my_ef_remove_page_custom_statuses() {
	remove_post_type_support( 'page', 'ef_custom_statuses' ); 
}
add_action( 'init', 'my_ef_remove_page_custom_statuses', 11 );
`

= 0.6 =
New features, including story budget and editorial metadata, a completely rewritten calendar view, and many bug fixes, including one for editorial comments appearing in the admin.

== Changelog ==

= 0.6.1 =
* Custom Post Type support (Thanks to all who requested this!)
* Added search and filtering tools for user and usergroup lists
* Users without JavaScript no longer see the status dropdown
* Users with JavaScript no longer see the respond button for editorial comments
* Email notifications are now queued to improve performance and avoid issues with spam
* Posts in calendar now have a unique classname based on the status (Thanks [erikajurney](http://wordpress.org/support/profile/erikajurney)!)
* The "Posts I'm Following" widget now has a cleaner and far superior look!
* Bug fix: Proper i18n support (Thanks Beto Frega and others)
* Bug fix: Editorial Comments issue in IE (Thanks [asecondwill](http://wordpress.org/support/profile/asecondwill) and James Skaggs!)
* Bug fix: Always email admin feature was not working (Thanks [nicomollet](http://wordpress.org/support/profile/nicomollet)!)
* Bug fix: Notifications for scheduled posts did not include links (Thanks [erikajurney](http://wordpress.org/support/profile/erikajurney)!)

= 0.6 (Nov. 9, 2010) =
* New feature: Editorial Metadata. Previously, Edit Flow had 'due date', 'location' and 'description', as available editorial metadata. We've expanded this functionality to be completely customizable; admins can add any number of editorial metadata with the following types: checkbox, date, location, paragraph, text, or user dropdown.
* New feature: Story Budget. View all of your upcoming posts in a more traditional story budget view. Posts are grouped by category, and view can be filtered by post status, category, user, or limited to a date range. Hit the print button to take it on the go.
* Completely rewritten calendar view now saves filter state on a user by user basis. Also, highlights current day, and displays status and time for each post.
* Temporarily disabled QuickPitch widget until we rewrite it to support editorial metadata.
* Bug fix: Editorial comments should no longer show up in the stock Recent Comments widget or in the comments view in the WordPress Admin. The comment count number should also be correct.
* Bug fix: Duplicate custom post statuses and usergroups are handled in more sane ways (aka creating, editing, and deleting should work as expected)

= 0.5.3 (Oct. 6, 2010) =
* Fixes issue where default Custom Statuses and User Groups were returning even after being deleted

= 0.5.1 (Jul. 29, 2010) =
* Editorial calendar improvements: filter by category or author
* QuickPitch stories get default status instead of pitch status
* No email notifications for “Auto Draft” post status
* Backwards compatibility with WordPress 2.9.x

= 0.5 (Jul. 3, 2010) =
* Calendar view for visualizing and spec assignments at a glance
* Improvements for WordPress 3.0 compatibility

= 0.4 =
* Users that edit a post automatically get subscribed to that post (only if they have the manage subscriptions capability)
* Edit Flow automatically hides editorial comments if the plugin is disabled
* Moved default custom status additions to upgrade function so they don't get added every time you activate
* Bug fix: remove editorial comments from comments feed

= 0.3.3 (Feb. 4, 2010) =
* Added tooltips with descriptions to the Status dropdown and Status Filter links. Thanks to [Gil Namur](http://lifeasahuman.com) for the great idea!
* Fixed the issue where subscribed users/usergroups were not receiving notifications

= 0.3.2 (Jan. 28, 2010)=
* Fixed fatal error if notifications were disabled

= 0.3.1 =
* Small bug fixes

= 0.3 =
* *Note:* Edit Flow now requires 2.9+
* Notification emails on status change now have specific subject lines messages based on action taken
* Action links in comment notifications now take the user to the comment form; i.e. clicking reply link in the email will focus on the comment text box and reply to the message
* Usergroups!
* Assign users and usergroups that should be notified of post updates
* Removed notify by role option since it's redundant because of usergroups
* Added "Always notify admin option"
* Added option to hide the status dropdown on Post and Page edit pages (default set to show)
* Added option to globally disable QuickPitch widget
* Bug fix: Custom Status names cannot be longer than 20 chars
* Bug fix: Deleted users are removed as subscribers from posts
* Bug fix: Blank menu items should now be sorta hidden 

= 0.2 =
* Custom Statuses are now supported for pages
* Editorial Comments (with threading)
* Email Notifications (on post status change and editorial comment)
* Additional Post metadata 
* Quick Pitch Dashboard widget
* Bug fix: sorting issue on Manage Posts page (Mad props to David Smith from Columbia U.)
* Other bug fixes
* Better localization support

= 0.1.5 =
* Ability to assign custom statuses to posts
