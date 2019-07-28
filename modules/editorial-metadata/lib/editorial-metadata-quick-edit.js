(function($) {

	// we create a copy of the WP inline edit post function
	var $wp_inline_edit = inlineEditPost.edit;

	// and then we overwrite the function with our own code
	inlineEditPost.edit = function( id ) {

		// "call" the original WP edit function
		// we don't want to leave WordPress hanging
		$wp_inline_edit.apply( this, arguments );

		// now we take care of our business

		// check for ef_quick_edit global var
		if ( !window.hasOwnProperty( 'ef_quick_edit' ) )
			return;

		// get the post ID
		var $post_id = 0;

		if ( typeof( id ) == 'object' ) {
			$post_id = parseInt( this.getId( id ) );
		}

		if ( $post_id > 0 ) {
			// define the edit row
			var $edit_row = $( '#edit-' + $post_id );
			var $post_row = $( '#post-' + $post_id );

			// loop through input names and display value from column value ( fetched with selector )
			for ( var name in ef_quick_edit ) {
				var selector = ef_quick_edit[name].selector,
					type = ef_quick_edit[name].type,
					val = $( selector, $post_row ).text(),
					$input = $( '#' + name, $edit_row );

				if ( type == 'checkbox' ) {
					val = val.toLowerCase();
					$input.prop( 'checked', val == 'yes' ? true : false );
				} else if ( type == 'user' ) {
					$input.find( 'option' ).filter( function() {
						return $( this ).text() == val;
					} ).prop( 'selected', true );
				} else {
					$input.val( val );
				}
			}
		}
	};

})(jQuery);