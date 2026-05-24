( function ( $ ) {
	'use strict';

	var $panel = null;

	function getPanel() {
		if ( ! $panel || ! $panel.length ) {
			$panel = $( '#optic_product_data_panel' );
		}
		return $panel;
	}

	function getSelectedDivision() {
		return $( '#_optic_division' ).val() || '';
	}

	function getAllowedPowers( division ) {
		if ( ! division || ! wcOpticAdmin.divisionPowers || ! wcOpticAdmin.divisionPowers[ division ] ) {
			return [];
		}
		return wcOpticAdmin.divisionPowers[ division ];
	}

	function isPowerType( type ) {
		return (
			wcOpticAdmin.powerTypes &&
			wcOpticAdmin.powerTypes.indexOf( type ) !== -1
		);
	}

	function getSelect2Language() {
		if ( typeof wc_enhanced_select_params === 'undefined' ) {
			return {};
		}
		return {
			language: {
				noResults: function () {
					return wc_enhanced_select_params.i18n_no_matches;
				},
				searching: function () {
					return wc_enhanced_select_params.i18n_searching;
				},
			},
		};
	}

	function getSelect2Args( $el ) {
		return $.extend(
			{
				width: '100%',
				minimumResultsForSearch: 0,
				allowClear: true,
				placeholder: $el.data( 'placeholder' ) || '',
			},
			getSelect2Language()
		);
	}

	function destroySelect2( $el ) {
		if ( ! $el || ! $el.length ) {
			return;
		}
		if ( $el.hasClass( 'enhanced' ) && $el.data( 'select2' ) ) {
			$el.selectWoo( 'destroy' );
		}
		$el.removeClass( 'enhanced' );
	}

	function initSelect2( $el ) {
		if ( ! $el || ! $el.length || ! $el.is( ':visible' ) ) {
			return;
		}
		if ( $el.hasClass( 'enhanced' ) ) {
			$el.next( '.select2-container' ).css( 'width', '100%' );
			return;
		}
		$el.selectWoo( getSelect2Args( $el ) ).addClass( 'enhanced' );
		$el.next( '.select2-container' ).css( 'width', '100%' );
	}

	function initAllOpticSelect2() {
		getPanel().find( 'select.wc-optic-select2:visible' ).each( function () {
			initSelect2( $( this ) );
		} );
	}

	function applyDivisionPowerFields() {
		var division = getSelectedDivision();
		var allowed = getAllowedPowers( division );

		$( '.wc-optic-sku-power' ).each( function () {
			var $row = $( this );
			var $select = $row.find( 'select.wc-optic-catalog-select' );
			var type = $select.data( 'optic-type' );
			var show = type && allowed.indexOf( type ) !== -1;

			if ( ! show ) {
				destroySelect2( $select );
				$select.val( '' );
				$row.hide();
				return;
			}

			$row.show();
			initSelect2( $select );
		} );

		initSelect2( $( '#_optic_division' ) );
	}

	function refreshSkuPreview() {
		var data = {
			action: 'wc_optic_preview_sku',
			nonce: wcOpticAdmin.nonce,
			optic_division: getSelectedDivision(),
		};

		$( '.wc-optic-catalog-select' ).each( function () {
			var $el = $( this );
			var t = $el.data( 'optic-type' );
			if ( ! t ) {
				return;
			}
			if ( isPowerType( t ) && ! $el.closest( '.wc-optic-sku-power' ).is( ':visible' ) ) {
				data[ 'cat_' + t ] = '';
				return;
			}
			data[ 'cat_' + t ] = $el.val();
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

	function initOpticProductPanel() {
		getPanel().find( 'select.wc-optic-select2' ).each( function () {
			destroySelect2( $( this ) );
		} );
		applyDivisionPowerFields();
		getPanel()
			.find( '.wc-optic-sku-field:not(.wc-optic-sku-power) select.wc-optic-select2' )
			.each( function () {
				initSelect2( $( this ) );
			} );
		refreshSkuPreview();
	}

	function isOpticProductScreen() {
		return $( 'select#product-type' ).val() === 'optic_product';
	}

	$( document.body )
		.on( 'change', '.wc-optic-catalog-select', refreshSkuPreview )
		.on( 'change', '#_optic_division', function () {
			applyDivisionPowerFields();
			refreshSkuPreview();
		} )
		.on( 'woocommerce-product-type-change', function () {
			if ( isOpticProductScreen() ) {
				setTimeout( initOpticProductPanel, 100 );
			}
		} )
		.on( 'click', 'ul.product_data_tabs li a[href="#optic_product_data_panel"]', function () {
			setTimeout( initOpticProductPanel, 50 );
		} );

	$( function () {
		if ( isOpticProductScreen() ) {
			initOpticProductPanel();
		}
	} );
}( jQuery ) );
