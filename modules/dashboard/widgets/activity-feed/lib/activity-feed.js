jQuery(document).ready(function ($) {
	inlineEditorialCommentMeta.init();

	// Check if certain hash flag set and take action
	if (location.hash == '#editorialcomments/add') {
		inlineEditorialCommentMeta.open();
	} else if (location.hash.search(/#editorialcomments\/reply/) > -1) {
		var reply_id = location.hash.substring(location.hash.lastIndexOf('/')+1);
		inlineEditorialCommentMeta.open(reply_id);
	}

});

/**
 * Blatantly stolen and modified from /wp-admin/js/edit-comment.dev.js -- yay!
 * Handles inline stuff on the dashboard
 */
inlineEditorialCommentMeta = {
	stored_html: "",
	container_to_restore: "",
	view_comment_id: "",

	init : function() {
		var object_this = this;

		// Bind click events to cancel and submit buttons
		jQuery('#af-outer-wrap').on('click', 'a.ef-replycancel', function(event) {
				return object_this.revert();
			}
		);

		jQuery('#af-outer-wrap').on('click', 'a.ef-replysave', function(event) {
				return object_this.send();
			}
		);

		jQuery('#af-outer-wrap').on('hover', '.date-pick',  function(event) {
			if(!jQuery(this).hasClass('hasDatepicker')) {
				jQuery(this).datetimepicker({
					dateFormat: 'M dd yy',
					firstDay: ef_week_first_day,
					alwaysSetTime: false,
					controlType: 'select'
				});
			}
		});

		jQuery('#af-outer-wrap').on('mouseenter', '#af-mixed-list li', function(event) {
				jQuery(this).find('.af-row-actions').removeClass('af-row-hidden');
			}
		);

		jQuery('#af-outer-wrap').on('mouseleave', '#af-mixed-list li', function(event) {
				jQuery(this).find('.af-row-actions').addClass('af-row-hidden');
			}
		);
	},

	revert : function() {
		var object_this = this; 

		// Fade out slowly, slowly, slowly...
		jQuery('#ef-replyrow').fadeOut('fast', function(){
			object_this.close();
		});

		return false;
	},

	close : function() {		
		//Prevent visual bug
		jQuery('.af-row-actions, .af-row-actions span').show();

		jQuery('#ef-replyrow').hide();

		if(jQuery('#af-active-link').length !== 0)
			this.closeOutOpenLinksWindows(jQuery('#af-active-link'));

		//If a selector has been stored, restore content
		if(this.container_to_restore)
			jQuery(this.container_to_restore).html(this.stored_html);

		if(this.view_comment_id)
			jQuery(this.view_comment_id).parent().removeClass('af-row-dont-show');
		
		// Empty out all the form values
		jQuery('#ef-replycontent').val('');
		jQuery('#ef-comment_parent').val('');

		// Hide error and waiting
		jQuery('#ef-replysubmit .error').html('').hide();
		jQuery('#ef-comment_loading').hide();

		jQuery('#ef-savecancel-buttons').hide()
	},

	/**
	 * @e = event
	 * @id = comment id
	 * @post_id = post id
	 */
	open : function(e, id, post_id) {
		var comment_id = (id) ? id : 0;
		var comment_post_id = (post_id) ? post_id : null;
		var clicked_id = jQuery(e);
		var is_post = clicked_id.closest('.af-post, .af-comment').hasClass('post');
		
		// Close any open reply boxes
		this.close();

		if(comment_id)
			jQuery('#ef-comment_parent').val(comment_id);

		jQuery('#ef-post_id').val(comment_post_id);

		if(is_post || (!is_post && !id)) 
			jQuery('#ef-replybtn').text('Submit Response'); //It's a post or new comment
		else 
			jQuery('#ef-replybtn').text('Submit Reply');  //It's a reply
		
		jQuery('#'+clicked_id.closest('.af-post, .af-comment').attr('id'))
			.append(jQuery('#ef-replyrow').show());

		jQuery(clicked_id.closest('.af-row-actions')).hide();

		return false;
	},

	/**
	 * Sends the ajax response to save the commment
	 * @param bool reply - indicates whether the comment is a reply or not 
	 */
	send : function(reply) {
		var post = {};
		var object_this = this;

		jQuery('#ef-replysubmit .error').html('').hide();
		
		// Validation: check to see if comment entered
		post.content = jQuery.trim(jQuery('#ef-replycontent').val());
		if(!post.content) {
			jQuery('#ef-replyrow .error').html('<span class="red">Please enter a comment</span>').show();
			return false;
		}
		
		jQuery('#ef-comment_loading').show();

		// Prepare data
		post.action = 'ef_activity_feed_add_comment';
		post.parent = (jQuery("#ef-comment_parent").val()=='') ? 0 : jQuery("#ef-comment_parent").val();
		post._nonce = jQuery("#ef_comment_nonce").val();
		post.post_id = jQuery("#ef-post_id").val();
		post.send_response = 'yes';

		// Send the request
		jQuery.ajax({
			type : 'POST',
			url : (ajaxurl) ? ajaxurl : wpListL10n.url,
			data : post,
			success : function(x) { object_this.show(x); },
			error : function(r) { object_this.error(r); }
		});

		return false;
	},

	show : function(xml) {
		var response, widget_html;
		
		// Didn't pass validation, so let's throw an error
		if ( typeof(xml) == 'string' ) {
			this.error({'responseText': xml});
			return false;
		}
		
		// Parse the response
		response = wpAjax.parseAjaxResponse(xml);
		if ( response.errors ) {
			// Uh oh, errors found
			this.error({'responseText': wpAjax.broken});
			return false;
		}
		
		response = response.responses[0];
		widget_html = response.data;
		
		jQuery('#af-inner-wrap').replaceWith(widget_html);
		jQuery('#af-inner-wrap').animate({'opacity': '0'}, 0).animate({'opacity': '1'},400)
	},

	viewContent: function(e){

		//We've encountered a new link!
		if( jQuery(e).attr('id') !== 'af-active-link') {
			//close everything out
			this.close()
			//Can we find an active link?
			if(jQuery('#af-active-link').length !== 0) {
				this.closeOutOpenLinksWindows(jQuery('#af-active-link'));
				this.closeOutOpenLinksWindows(jQuery(e));
			} else {
				this.closeOutOpenLinksWindows(jQuery(e));
			}
			
		}
		else {
			this.closeOutOpenLinksWindows(jQuery(e));
		}
	},

	closeOutOpenLinksWindows: function(being_clicked) {
		var current_text;
		var init_box;
		var list_items;
		var being_clicked_class = being_clicked.parent().attr('class');
		//Can these be localized?
		//Possibly better comparisons?

		switch(being_clicked_class) {
			case 'af-view-meta':
				var current_process = (being_clicked_class == 'af-edit-meta') ? af_widget_text_labels.edit_metadata : af_widget_text_labels.view_metadata;
				var find_class = (being_clicked_class == 'af-edit-meta') ? '.af-edit-metadata' : '.af-view-metadata';

				//It's an editable metadata. Whats the current links text?
				current_text = jQuery(being_clicked).text();
				if(current_text == current_process) {
					jQuery(being_clicked).text(af_widget_text_labels.hide_metadata);
					jQuery(being_clicked).attr('id', 'af-active-link');
					
					init_box = jQuery(being_clicked).closest('.af-post, .af-comment')
						.find(find_class);
					
					init_box.clone()
						.appendTo(init_box.parent())
						.removeClass('hide-af-feed-info')
						.attr('id', 'af-active-element')
					
					if(being_clicked_class == 'af-edit-meta')
						jQuery('#ef-savecancel-buttons').appendTo(init_box.parent()).show();
				}
				else {
					jQuery(being_clicked).text(current_process)

					jQuery(being_clicked).attr('id', '');
					jQuery(being_clicked).closest('.af-post, .af-comment')
						.find('#af-active-element')
						.remove();
					jQuery('#ef-savecancel-buttons').hide();
				}
			break;
			case 'af-comment-parent':
			case 'af-comment-children':
				var clone_item;
				var anchor_li;
				var current_text = jQuery(being_clicked).text();
				var num_parents_counted = 0;
				var num_list_items = 0;
				var list_to_attach_to;
				var hide_item = (being_clicked_class == 'af-comment-parent') ? af_widget_text_labels.hide_parents : af_widget_text_labels.hide_children;
				var show_item = (being_clicked_class == 'af-comment-parent') ? af_widget_text_labels.show_parents : af_widget_text_labels.show_children;
				var lists_to_find = (being_clicked_class == 'af-comment-parent') ? '.af-show-parents li' : '.af-show-children li';

				if(current_text == show_item) {
					jQuery(being_clicked).text(hide_item).attr('id', 'af-active-link')

					list_items = jQuery(being_clicked).closest('.af-post, .af-comment')
						.find(lists_to_find);				
					list_to_attach_to = jQuery(being_clicked).closest('.af-post, .af-comment')
						.find('.af-outer-comment-wrap');

					anchor_li = list_to_attach_to.find('li');
					num_list_items = list_items.length;

					list_items.each(function(i, val) {
						if(being_clicked_class == 'af-comment-parent') {
							clone_item = jQuery(this).clone().css('margin-left', (num_list_items - 1) * 10).addClass('active-list-item');
							jQuery(clone_item).prependTo(list_to_attach_to);
							num_list_items -= 1;
						}
						else {
							clone_item = jQuery(this).clone().css('margin-left', (1 + i) * 10).addClass('active-list-item');
							jQuery(clone_item).appendTo(list_to_attach_to);
						}
						
						num_parents_counted += i;
					});

					if(being_clicked_class == 'af-comment-parent')
						anchor_li.css('margin-left', (num_parents_counted + 1) * 10);
				}
				else {
					var ul;
					var anchor_li; 

					jQuery(being_clicked).attr('id', '').text(show_item);

					ul = jQuery(being_clicked).closest('.af-post, .af-comment')
						.find('.af-outer-comment-wrap');

					anchor_li = ul.find('.af-anchor-li');
					ul.empty().append(anchor_li);

					anchor_li.css('margin-left', '');
				}
			break;
		}
	},

	error : function(r) {
		// Oh noes! We haz an error!
		jQuery('#ef-comment_loading').hide();

		if ( r.responseText ) {
			er = r.responseText.replace( /<.[^<>]*?>/g, '' );
		}

		if ( er ) {
			jQuery('#ef-replysubmit .error').html(er).show();
		}
	}
};