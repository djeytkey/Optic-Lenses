( function ( $ ) {
	'use strict';

	function syncLineQuantity() {
		var perEye = $( '#wc_optic_qty_per_eye' ).is( ':checked' );
		var q = 1;
		if ( perEye ) {
			var l = parseInt( $( '#wc_optic_qty_left' ).val(), 10 ) || 0;
			var r = parseInt( $( '#wc_optic_qty_right' ).val(), 10 ) || 0;
			q = Math.max( 1, l + r );
		} else {
			q = Math.max( 1, parseInt( $( '#wc_optic_qty' ).val(), 10 ) || 1 );
		}
		$( '#wc_optic_line_quantity' ).val( q );
	}

	function toggleSamePower() {
		var same = $( '#wc_optic_same_power' ).is( ':checked' );
		var $right = $( '.wc-optic-eye--right' );
		var $both = $( '.wc-optic-title-both' );
		var $leftTitle = $( '.wc-optic-title-left' );
		if ( same ) {
			$right.prop( 'hidden', true );
			$both.prop( 'hidden', false );
			$leftTitle.prop( 'hidden', true );
			$right.find( 'input' ).prop( 'required', false );
		} else {
			$right.prop( 'hidden', false );
			$both.prop( 'hidden', true );
			$leftTitle.prop( 'hidden', false );
			$right.find( 'input' ).prop( 'required', true );
		}
	}

	function toggleQtyMode() {
		var perEye = $( '#wc_optic_qty_per_eye' ).is( ':checked' );
		$( '.wc-optic-qty--single' ).prop( 'hidden', perEye );
		$( '.wc-optic-qty--dual' ).prop( 'hidden', ! perEye );
		syncLineQuantity();
	}

	$( function () {
		var $form = $( 'form.wc-optic-cart-form' );
		if ( ! $form.length ) {
			return;
		}

		toggleSamePower();
		toggleQtyMode();
		syncLineQuantity();

		$( '#wc_optic_same_power' ).on( 'change', toggleSamePower );
		$( '#wc_optic_qty_per_eye' ).on( 'change', toggleQtyMode );
		$( '#wc_optic_qty, #wc_optic_qty_left, #wc_optic_qty_right' ).on( 'change input', syncLineQuantity );

		$form.on( 'submit', function () {
			syncLineQuantity();
		} );
	} );
}( jQuery ) );
