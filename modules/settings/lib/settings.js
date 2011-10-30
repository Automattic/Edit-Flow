jQuery(document).ready(function(){

	jQuery('.enable-disable-edit-flow-module').click(function(){
		if ( jQuery(this).hasClass('button-primary') )
			var module_action = 'enable';
		else if ( jQuery(this).hasClass('button-remove') )
			var module_action = 'disable';
		
		var slug = jQuery(this).closest('.edit-flow-module').attr('id');
		var change_module_nonce = jQuery('#' + slug + ' #change-module-nonce').val();
		jQuery('#' + slug + ' .waiting').show();
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
					jQuery('#' + slug + ' .enable-disable-edit-flow-module.button-primary' ).show();
					jQuery('#' + slug + ' a.configure-edit-flow-module' ).fadeOut();					
				} else if ( module_action == 'enable' ) {
					jQuery('#' + slug + ' .enable-disable-edit-flow-module.button-remove' ).show();
					jQuery('#' + slug + ' a.configure-edit-flow-module' ).fadeIn();					
				}
			}
			jQuery('#' + slug + ' .waiting').hide();
			return false;
			
		});
		
		return false;
	});
	
});