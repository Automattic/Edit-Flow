// Story Budget specific JS, assumes that ef_date.js has already been included

jQuery( document ).ready( function ( $ ) {
	// Make print link open up print dialog
	$( '#print_link' ).on( 'click', function () {
		window.print();
		return false;
	} );

	// Hide a single section when directed
	$( 'h3.hndle,div.handlediv' ).on( 'click', function () {
		$( this ).parent().children( 'div.inside' ).toggle();
	} );

	// Change number of columns when choosing a new number from Screen Options

	const columnsSwitch = $( 'input[name=ef_story_budget_screen_columns]' );
	columnsSwitch.on( 'click', function () {
		const numColumns = parseInt( $( this ).val() );
		const classPrefix = 'columns-number-';
		$( '.postbox-container' )
			.removeClass( function () {
				for ( var index = 1, c = []; index <= columnsSwitch.length; index++ ) {
					c.push( classPrefix + index );
				}
				return c.join( ' ' );
			} )
			.addClass( classPrefix + numColumns );
	} );

	$( 'h2 a.change-date' ).on( 'click', function () {
		$( this ).hide();
		$( 'h2 form .form-value' ).hide();
		$( 'h2 form input' ).show();
		$( 'h2 form a.change-date-cancel' ).show();
		return false;
	} );

	$( 'h2 form a.change-date-cancel' ).on( 'click', function () {
		$( this ).hide();
		$( 'h2 form .form-value' ).show();
		$( 'h2 form input' ).hide();
		$( 'h2 form a.change-date' ).show();
		return false;
	} );
} );
