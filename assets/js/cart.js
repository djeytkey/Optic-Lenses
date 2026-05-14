( function ( $ ) {
	'use strict';

	function syncRow( $wrap ) {
		var l = parseInt( $wrap.find( '.wc-optic-cart-q-left' ).val(), 10 ) || 0;
		var r = parseInt( $wrap.find( '.wc-optic-cart-q-right' ).val(), 10 ) || 0;
		l = Math.max( 1, l );
		r = Math.max( 1, r );
		$wrap.find( '.wc-optic-cart-line-total' ).val( l + r );
	}

	$( function () {
		$( document.body ).on( 'change input', '.wc-optic-cart-q-left, .wc-optic-cart-q-right', function () {
			syncRow( $( this ).closest( '.wc-optic-cart-qty' ) );
		} );
	} );
}( jQuery ) );
