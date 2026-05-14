( function ( $ ) {
	'use strict';

	$( function () {
		var $root = $( '#wc-optic-settings-root' );
		var tab = $root.data( 'active-tab' );

		$( '#wc-optic-quick-add' ).on( 'click', function () {
			$( '#wc-optic-quick-add-panel' ).toggle();
		} );

		$( '#wc-optic-quick-submit' ).on( 'click', function () {
			var name = $( '#wc-optic-quick-name' ).val();
			var slug = $( '#wc-optic-quick-slug' ).val();
			var frag = $( '#wc-optic-quick-frag' ).val();
			if ( ! name ) {
				return;
			}
			$.post( wcOpticAdmin.ajaxUrl, {
				action: 'wc_optic_create_term',
				nonce: wcOpticAdmin.nonce,
				term_type: tab,
				name: name,
				slug: slug,
				sku_fragment: frag
			}, function ( res ) {
				if ( res && res.success ) {
					window.location.reload();
				} else {
					var msg = res && res.data && res.data.message ? res.data.message : 'Error';
					window.alert( msg );
				}
			} );
		} );
	} );
}( jQuery ) );
