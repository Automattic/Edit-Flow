(function($) {
inlineEditUsergroup = {

	init : function() {
		var t = this, row = $('#inline-edit');

		t.what = '#usergroup-';

		$( document ).on( 'click', '.editinline', function(){
			inlineEditUsergroup.edit(this);
			return false;
		});

		// prepare the edit row
		row.on( 'keyup', function(e) { if(e.which == 27) return inlineEditUsergroup.revert(); });

		$('a.cancel', row).on( 'click', function() { return inlineEditUsergroup.revert(); });
		$('a.save', row).on( 'click', function() { return inlineEditUsergroup.save(this); });
		$('input, select', row).on( 'keydown', function(e) { if(e.which == 13) return inlineEditUsergroup.save(this); });

		$('#posts-filter input[type="submit"]').on( 'mousedown', function(e){
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
			action: 'inline_save_usergroup',
			usergroup_id: id,
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
						$(inlineEditUsergroup.what+id).remove();
						new_id = $(r).attr('id');

						$('#edit-'+id).before(r).remove();
						row = new_id ? $('#'+new_id) : $(inlineEditUsergroup.what+id);
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

$(document).ready(function(){inlineEditUsergroup.init();});
})(jQuery);