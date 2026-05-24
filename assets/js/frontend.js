( function ( $ ) {
	'use strict';

	function formatPrice( amount ) {
		if ( typeof wcOpticFront === 'undefined' ) {
			return String( amount );
		}
		var n = parseFloat( amount );
		if ( isNaN( n ) ) {
			n = 0;
		}
		var decimals = parseInt( wcOpticFront.decimals, 10 );
		if ( isNaN( decimals ) ) {
			decimals = 2;
		}
		var parts = n.toFixed( decimals ).split( '.' );
		var intPart = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, wcOpticFront.thousandSep || ',' );
		var formatted = parts.length > 1 ? intPart + ( wcOpticFront.decimalSep || '.' ) + parts[ 1 ] : intPart;
		var format = wcOpticFront.priceFormat || '%1$s%2$s';
		return format.replace( '%1$s', wcOpticFront.currencySymbol || '' ).replace( '%2$s', formatted );
	}

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
		updateLineTotal( q );
	}

	function updateLineTotal( qty ) {
		var $wrap = $( '.wc-optic-pricing' );
		var $display = $( '#wc_optic_line_total_display' );
		if ( ! $wrap.length || ! $display.length || typeof wcOpticFront === 'undefined' ) {
			return;
		}
		var unit = parseFloat( $wrap.data( 'unit-price' ) );
		if ( isNaN( unit ) ) {
			unit = parseFloat( wcOpticFront.unitPrice );
		}
		var total = unit * Math.max( 1, qty || 1 );
		$display.text( formatPrice( total ) );
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
