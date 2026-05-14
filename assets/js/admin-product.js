( function ( $ ) {
	'use strict';

	function refreshSkuPreview() {
		var data = {
			action: 'wc_optic_preview_sku',
			nonce: wcOpticAdmin.nonce
		};
		$( '.wc-optic-catalog-select' ).each( function () {
			var t = $( this ).data( 'optic-type' );
			if ( t ) {
				data[ 'cat_' + t ] = $( this ).val();
			}
		} );
		$.post( wcOpticAdmin.ajaxUrl, data, function ( res ) {
			if ( res && res.success && res.data && typeof res.data.sku === 'string' ) {
				$( '#wc-optic-admin-sku-preview' ).text( res.data.sku );
				var $sku = $( '#_sku' );
				if ( $sku.length ) {
					$sku.val( res.data.sku );
				}
			}
		} );
	}

	$( document.body )
		.on( 'change', '.wc-optic-catalog-select', refreshSkuPreview )
		.on( 'woocommerce-product-type-change', function () {
			if ( $( 'select#product-type' ).val() === 'optic_product' ) {
				$( document.body ).trigger( 'wc-enhanced-select-init' );
				setTimeout( refreshSkuPreview, 200 );
			}
		} );

	$( function () {
		if ( $( 'select#product-type' ).val() === 'optic_product' ) {
			$( '.wc-optic-catalog-select' ).filter( '.wc-enhanced-select' ).each( function () {
				$( this ).selectWoo( { width: '100%' } );
			} );
			refreshSkuPreview();
		}
	} );
}( jQuery ) );
