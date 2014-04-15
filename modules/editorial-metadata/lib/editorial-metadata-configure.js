
(function($) {
inlineEditMetadataTerm = {

	init : function() {
		var t = this, row = $('#inline-edit');

		t.what = '#term-';

		$('.editinline').live('click', function(){
			inlineEditMetadataTerm.edit(this);
			return false;
		});

		// prepare the edit row
		row.keyup(function(e) { if(e.which == 27) return inlineEditMetadataTerm.revert(); });

		$('a.cancel', row).click(function() { return inlineEditMetadataTerm.revert(); });
		$('a.save', row).click(function() { return inlineEditMetadataTerm.save(this); });
		$('input, select', row).keydown(function(e) { if(e.which == 13) return inlineEditMetadataTerm.save(this); });

		$('#posts-filter input[type="submit"]').mousedown(function(e){
			t.revert();
		});
	},

	toggle : function(el) {
		var t = this;
		$(t.what+t.getId(el)).css('display') == 'none' ? t.revert() : t.edit(el);
	},

	edit : function(id) {
		var t = this, editRow;
		t.revert();

		if ( typeof(id) == 'object' )
			id = t.getId(id);

		editRow = $('#inline-edit').clone(true), rowData = $('#inline_'+id);
		$('td', editRow).attr('colspan', $('.widefat:first thead th:visible').length);

		if ( $(t.what+id).hasClass('alternate') )
			$(editRow).addClass('alternate');

		$(t.what+id).hide().after(editRow);

		$(':input[name="name"]', editRow).val( $('.name', rowData).text() );
		$(':input[name="description"]', editRow).val( $('.description', rowData).text() );
		
		$(editRow).attr('id', 'edit-'+id).addClass('inline-editor').show();
		$('.ptitle', editRow).eq(0).focus();

		return false;
	},

	save : function(id) {
		var params, fields, tax = $('input[name="taxonomy"]').val() || '';

		if( typeof(id) == 'object' )
			id = this.getId(id);

		$('table.widefat .inline-edit-save .waiting').show();

		params = {
			action: 'inline_save_term',
			term_id: id,
		};
		
		fields = $('#edit-'+id+' :input').serialize();
		params = fields + '&' + $.param(params);		


		// make ajax request
		$.post(ajaxurl, params,
			function(r) {
				var row, new_id;
				$('table.widefat .inline-edit-save .waiting').hide();

				if (r) {
					if ( -1 != r.indexOf('<tr') ) {
						$(inlineEditMetadataTerm.what+id).remove();
						new_id = $(r).attr('id');

						$('#edit-'+id).before(r).remove();
						row = new_id ? $('#'+new_id) : $(inlineEditMetadataTerm.what+id);
						row.hide().fadeIn();
					} else
						$('#edit-'+id+' .inline-edit-save .error').html(r).show();
				} else
					$('#edit-'+id+' .inline-edit-save .error').html(inlineEditL10n.error).show();
			}
		);
		return false;
	},

	revert : function() {
		var id = $('table.widefat tr.inline-editor').attr('id');

		if ( id ) {
			$('table.widefat .inline-edit-save .waiting').hide();
			$('#'+id).remove();
			id = id.substr( id.lastIndexOf('-') + 1 );
			$(this.what+id).show();
		}

		return false;
	},

	getId : function(o) {
		var id = o.tagName == 'TR' ? o.id : $(o).parents('tr').attr('id'), parts = id.split('-');
		return parts[parts.length - 1];
	}
};

$(document).ready(function(){inlineEditMetadataTerm.init();});
})(jQuery);

jQuery(document).ready(function(){
	
	jQuery('.delete-status a').click(function(){
		if ( !confirm( ef_confirm_delete_term_string ) )
			return false;
	});
	
	/**
	 * Instantiate the drag and drop sorting functionality
	 */
	jQuery( "#the-list" ).sortable({
		items: 'tr.term-static',
		update: function(event, ui) {
			var affected_item = ui.item;
			// Reset the position indicies for all terms
			jQuery('#the-list tr').removeClass('alternate');
			var terms = new Array();
			jQuery('#the-list tr.term-static').each(function(index, value){
				var term_id = jQuery(this).attr('id').replace('term-','');
				terms[index] = term_id;
				jQuery( 'td.position', this ).html( index + 1 );
				// Update the WP core design for alternating rows
				if ( index%2 == 0 )
					jQuery(this).addClass('alternate');
			});
			// Prepare the POST
			var params = {
				action: 'update_term_positions',
				term_positions: terms,
				editorial_metadata_sortable_nonce: jQuery('#editorial-metadata-sortable').val(),
			};
			// Inform WordPress of our updated positions
			jQuery.post( ajaxurl, params, function( retval ){
				jQuery('.edit-flow-admin .edit-flow-message').remove();
				// If there's a success message, print it. Otherwise we assume we received an error message
				if ( retval.status == 'success' ) {
					var message = '<span class="edit-flow-updated-message edit-flow-message">' + retval.message + '</span>';
				} else {
					var message = '<span class="edit-flow-error-message edit-flow-message">' + retval.message + '</span>';
				}
				jQuery('.edit-flow-admin h2').append( message );
				// Set a timeout to eventually remove it
				setTimeout( edit_flow_hide_message, 8000 );
			});
		},
	});
	jQuery( "#the-list tr.term-static" ).disableSelection();
	jQuery( "#metadata_type" ).change(function(){
		if ( this.value === "dropdown" ) {
			jQuery( "#metadata_dropdown_items" ).parent().show(function(){
				jQuery( "#metadata_dropdown_items" ).focus();
			});
		} else {
			jQuery( "#metadata_dropdown_items" ).parent().hide();
		}
	});
});
