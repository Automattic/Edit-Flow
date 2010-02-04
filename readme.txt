=== Edit Flow ===
Contributors: batmoo, danielbachhuber, sbressler, andrewspittle
Donate link: http://copress.org/donate/
Tags: edit flow, workflow, editorial, newsroom, management, journalism, post status, custom status, notifications, email, comments, editorial comments, usergroups
Requires at least: 2.9.1
Tested up to: 2.9.1
Stable tag: 0.3.3

Plugin for WordPress to allow for better editorial workflow.

== Description ==

The overall goal of this plugin is to improve the WordPress Admin interface for a multi-user newsroom’s editorial workflow. 

NOTE: Edit Flow requires PHP5+ to work. Don't have PHP5? Talk to your hosting provider and hop on the PHP5 bandwagon. It's awesome.

There are a few key components to this project: 

* improving the meta data on top of posts to better reflect the information that needs to be recorded about an assignment;
* empowering newsrooms, blog networks and multi-user blogs to manage more of their editorial workflow from within the WordPress admin; and
* building out a way to track all of this active meta data within the system as a way of visualizing content and priorities at a glance.

More details can be found on the [CoPress Wiki](http://www.copress.org/wiki/Edit_Flow_Project)

We welcome any and all feedback. Ideas are more awesome than money (okay, not always). Contact us at [editflow@copress.org](mailto:editflow@copress.org).

= Stage 1: Custom Post Statuses =

Enables users to create custom statuses for posts, and assign those to posts.

**Adding/Editing/Managing Custom Statuses**

* Upon activation, the plugin adds five default statuses ("Assigned", "Draft", "Pending Review", "Pitch", "Waiting for Feedback"). These can all be edited or deleted (with the exception of "Draft" and "Pending Review", which can only be deleted). Users can also add additional custom statuses. Overall, we tried to make this as flexible as possible, acknowledging the extreme diversity in workflows and requirements across different newsrooms.
* The "Add/Edit/Manage Custom Statuses" screen is reminiscent of the interface used to manage categories and tags. From a design stand-point, we tried to keep with standard WordPress interface conventions (to minimize the learning curve) and, similarly, make the plugin as less intrusive as possible.

**Assigning Custom Statuses to Posts**

* With custom statuses defined, they can now be assigned to posts. The plugin adds the custom statuses to the “Status” dropdown when editing a post (screenshot above). Additionally, given the likely frequency of use of this feature, the Status dropdown is made visible by default. The plugin also allows you to set a default status for new posts, which WordPress sets to "Draft" by default.

**Managing Posts**

* A new column is added to the Edit/Manage Posts screen that indicates the current status of the post. Additionally, the posts on this screen can be filtered by status, by clicking on the links at the top.
* To ease the management of content, a new column is added to the Edit/Manage Posts screen that indicates the current status of the posts displayed. Additionally, the posts on this screen can be filtered by status, by clicking on the links at the top.

**Dashboard Widget**

* As a small bonus, we threw in a small dashboard widget that gives you a quick glance of the state of currently unpublished content. As this was a last minute addition, it's minimal and largely unstyled, but something we'll clean up and build out more in the coming days.

= Stage 2: Post Metadata and Editorial Commenting =

**Post Metadata**

* We've added some basic fields to allow you to capture some additional data for each article.

**Quick Pitch**

* Similar to QuickPress, this dashboard widget allows users to create a new pitch for an article.

**Editorial Comments**

* Edit Flow now supports editorial comments. Discussions on posts/articles can now take place between editorial staff within the WordPress Administration interface. This can cutdown on long-winded back-and-forth email threads as all comments are conveniently displayed within the Edit Posts page and better facilitate online workflows. Threading is supported (assuming it's enabled on your install)

**Notifications**

* We've added basic email notification support. Email notifications are delivered when a post's status changes or an editorial comment is added to a post. Notifications are delivered to: 
** Post author and Administrators, by default;
** Any specified roles (under Edit Flow > settings); and
** Any users that comment on posts.

= Stage 3: Usergroups =

* We've added a feature called usergroups. It's sort of like roles, except that it just provides a way for you to group users that perform similar roles in your organization into cohesive virtual groups. Once grouped, usergroups can be subscribed to posts, so that all members receive post updates. This is just the start! We're looking a bunch of cool things to do with usergroups down the road.
* Speaking of notifications, you can now select which users should receive notifications for specific posts!
* Again on the topic of notifications, we've made emails a bit more specific in the type of information they contain as well some specific action links that'll probably save you a bunch of time.

= Stage 4: To come =

**What to expect**

* We're looking at visualizations (editorial calendars!), post checklists, more dashboard widgets, more interesting ways to keep up-to-date (activity streams!) and other goodies.

== Installation ==

This section describes how to install the plugin and get it working.

1. Extract the contents of the zip file into your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Party.

== Other Notes ==

= Editorial Comments =

**Hiding comments from front-end**

Editorial comments are stored as regular comments but identified in the database as different from regular comments. This allows However, given limitation in WordPress, editorial comments will only be hidden as long as Edit Flow is activated. To ensure that editorial comments stay hidden if you chose to deactivate Edit Flow, please add the following code to your theme's functions.php file:

`
<?php
/* Function to filter out editorial comments from comments array. Used to hide editorial comments from front-end if the Edit Flow plugin is ever deactivated */
$active_plugins = get_option('active_plugins');
if ( !in_array( 'edit-flow/edit_flow.php' , $active_plugins ) ) {
	add_filter( 'comments_array', 'ef_filter_editorial_comments' );
	add_filter( 'get_comments_number', 'ef_filter_editorial_comments_count' );
	
	if(!function_exists('ef_filter_editorial_comments')) {
		add_filter( 'comments_array', 'ef_filter_editorial_comments' );
		function ef_filter_editorial_comments( $comments ) {
			// Only filter if viewing front-end
			if( !is_admin() ) {
				$count = 0;
				foreach($comments as $comment) {
					if($comment->comment_type == 'editorial-comment') {
						unset($comments[$count]);
					}
					$count++;
				}
			}
			return $comments;
		}
	}
	if(!function_exists('ef_filter_editorial_comments_count')) {
		function ef_filter_editorial_comments_count( $count ) {
			global $post;
			// Only filter if viewing front-end
			if( !is_admin() ) {
				// Get number of editorial comments
				$editorial_count = ef_get_editorial_comment_count($post->ID);
				$count = $count - $editorial_count;
			}
			return $count;
		}
	}
	if(!function_exists('ef_get_editorial_comment_count')) {
		function ef_get_editorial_comment_count( $id ) {
			global $wpdb; 
			$comment_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s", $id, 'editorial-comment'));
			if(!comment_count) $comment_count = 0;
			return $comment_count;
		}
	}
}
?>
`

**Threaded Commenting**

For replies to work properly, you need to enable threaded commenting in your blog's settings. Find it under **Settings > Discussion** and enable the setting called **Enable threaded (nested) comments**.


== Screenshots ==

1. The ability to Add, Edit, and Delete Custom Statuses
2. Custom Statuses are automatically added to Status dropdown on the Edit Post and Quick Edit Post screens
3. A new column is added to the Edit Posts screen and the ability to filter by status
4. Threaded editorial commenting and additional metadata for posts
5. Usergroups allow you to bunch together groups of users
6. You can now chose who should receive notifications on a per-post basis

== Changelog ==

= 0.3.3 =
* Added tooltips with descriptions to the Status dropwdown and Status Filter links. Thanks to [Gil Namur](lifeasahuman.com) for the great idea!
* Fixed the issue where subscribed users/usergroups were not receiving notifications

= 0.3.2 =
* Fixed fatal error if notifications were disabled

= 0.3.1 =
* Small bug fixes

= 0.3 =
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

== Frequently Asked Questions ==

= Edit Flow doesn't do X, Y, and Z. That makes me sad. =

Contact us at [editflow@copress.org](editflow@copress.org) and we'll see what we can do.

= I'm stuck at WordPress 2.8! How do I use v0.3 onwards? =

Upgrade to WordPress 2.9 or later, fools!
