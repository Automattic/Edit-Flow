// Hide a given message
function edit_flow_hide_message() {
	jQuery('.edit-flow-message').fadeOut(function(){ jQuery(this).remove(); });
}

jQuery(document).ready(function(){
	
	// Restore the Edit Flow submenu if there are no modules enabled
	// We need it down below for dynamically rebuilding the link list when on the settings page
	var ef_settings_submenu_html = '<div class="wp-submenu"><div class="wp-submenu-wrap"><div class="wp-submenu-head">Edit Flow</div><ul><li class="wp-first-item current"><a tabindex="1" class="wp-first-item current" href="admin.php?page=ef-settings">Edit Flow</a></li></ul></div></div>';		
	if ( jQuery( 'li#toplevel_page_ef-settings .wp-submenu' ).length == 0 ) {
		jQuery( 'li#toplevel_page_ef-settings' ).addClass('wp-has-submenu wp-has-current-submenu wp-menu-open');
		jQuery( 'li#toplevel_page_ef-settings' ).append( ef_settings_submenu_html );
		jQuery( 'li#toplevel_page_ef-settings .wp-submenu' ).show();
	}

	// Set auto-removal to 8 seconds
	if ( jQuery('.edit-flow-message').length > 0 ) {
		setTimeout( edit_flow_hide_message, 8000 );
	}

	jQuery('.enable-disable-edit-flow-module').click(function(){
		if ( jQuery(this).hasClass('button-primary') )
			var module_action = 'enable';
		else if ( jQuery(this).hasClass('button-remove') )
			var module_action = 'disable';
		
		var slug = jQuery(this).closest('.edit-flow-module').attr('id');
		var change_module_nonce = jQuery('#' + slug + ' #change-module-nonce').val();
		var data = {
			action: 'change_edit_flow_module_state',
			module_action: module_action,
			slug: slug,
			change_module_nonce: change_module_nonce,
		}
		
		jQuery.post( ajaxurl, data, function(response) {
			
			if ( response == 1 ) {
				jQuery('#' + slug + ' .enable-disable-edit-flow-module' ).hide();
				if ( module_action == 'disable' ) {
					jQuery('#' + slug).addClass('module-disabled').removeClass('module-enabled');
					jQuery('#' + slug + ' .enable-disable-edit-flow-module.button-primary' ).show();
					jQuery('#' + slug + ' a.configure-edit-flow-module' ).hide().addClass('hidden');
					// If there was a configuration URL in the module, let's hide it from the left nav too
					if ( jQuery('#' + slug + ' a.configure-edit-flow-module' ).length > 0 ) {
						var configure_url = jQuery('#' + slug + ' a.configure-edit-flow-module' ).attr('href').replace(ef_admin_url, '');
						var top_level_menu = jQuery('#' + adminpage );
						jQuery('.wp-submenu-wrap li a', top_level_menu ).each(function(){
							if ( jQuery(this).attr('href') == configure_url )
								jQuery(this).closest('li').fadeOut(function(){ jQuery(this).remove(); });
						});
					}
				} else if ( module_action == 'enable' ) {
					jQuery('#' + slug).addClass('module-enabled').removeClass('module-disabled');
					jQuery('#' + slug + ' .enable-disable-edit-flow-module.button-remove' ).show();
					jQuery('#' + slug + ' a.configure-edit-flow-module' ).show().removeClass('hidden');
					// If there was a configuration URL in the module, let's go through the complex process of adding it again to the left nav
					if ( jQuery('#' + slug + ' a.configure-edit-flow-module' ).length > 0 ) {
						// Identify the order it should be in
						var link_order = 0;
						var counter = 0;
						jQuery('.edit-flow-module.has-configure-link').each(function(key,item){
							if ( jQuery(this).attr('id') == slug && !jQuery('a.configure-edit-flow-module', this).hasClass('hidden') )
								link_order = counter;
							if ( !jQuery('a.configure-edit-flow-module', this).hasClass('hidden') )
								counter++;
						});
						// Build the HTML for the new link
						var configure_url = jQuery('#' + slug + ' a.configure-edit-flow-module' ).attr('href').replace(ef_admin_url, '');
						var top_level_menu = jQuery('#' + adminpage );
						var html_title = jQuery('#' + slug + ' h4').html();
						var html_insert = '<li><a class="ef-settings-fade-in" style="display:none;" href="' + configure_url + '" tabindex="1">' + html_title + '</a>';
						jQuery('.wp-submenu-wrap ul li', top_level_menu).each(function(key,item) {
							if ( key == link_order )
								jQuery(this).after(html_insert);
						});
						// Trick way to do a fade in: add a class of 'ef-settings-fade-in' and run it after the action
						jQuery('.ef-settings-fade-in').fadeIn().removeClass('ef-settings-fade-in');
					}
				}
			}
			return false;
			
		});
		
		return false;
	});
	
});