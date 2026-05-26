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

	function getEyeContainer( eye ) {
		return $( '.wc-optic-eye[data-eye="' + eye + '"]' );
	}

	function getEyeFieldValue( eye ) {
		var $container = getEyeContainer( eye );
		var $radio = $container.find( 'input[type="radio"][name="wc_optic_' + eye + '_child"]:checked' );
		if ( $radio.length ) {
			return $radio.val() || '';
		}
		var $select = $container.find( 'select[name="wc_optic_' + eye + '_child"]' );
		if ( $select.length ) {
			return $select.val() || '';
		}
		return '';
	}

	function getEyeFieldPrice( eye ) {
		var $container = getEyeContainer( eye );
		var $radio = $container.find( 'input[type="radio"][name="wc_optic_' + eye + '_child"]:checked' );
		if ( $radio.length ) {
			return parseFloat( $radio.data( 'price' ) ) || 0;
		}
		var $select = $container.find( 'select[name="wc_optic_' + eye + '_child"]' );
		if ( $select.length ) {
			return parseFloat( $select.find( 'option:selected' ).data( 'price' ) ) || 0;
		}
		return 0;
	}

	function getEyeFieldStock( eye ) {
		var $container = getEyeContainer( eye );
		var $radio = $container.find( 'input[type="radio"][name="wc_optic_' + eye + '_child"]:checked' );
		if ( $radio.length ) {
			if ( $radio.data( 'stock' ) === '' || typeof $radio.data( 'stock' ) === 'undefined' ) {
				return null;
			}
			return parseInt( $radio.data( 'stock' ), 10 ) || 0;
		}
		var $select = $container.find( 'select[name="wc_optic_' + eye + '_child"]' );
		if ( $select.length ) {
			var stock = $select.find( 'option:selected' ).data( 'stock' );
			if ( stock === '' || typeof stock === 'undefined' ) {
				return null;
			}
			return parseInt( stock, 10 ) || 0;
		}
		return null;
	}

	function setEyeFieldValue( eye, value ) {
		var $container = getEyeContainer( eye );
		var $radio = $container.find( 'input[type="radio"][name="wc_optic_' + eye + '_child"]' );
		if ( $radio.length ) {
			$radio.prop( 'checked', false );
			$radio.filter( '[value="' + value + '"]' ).prop( 'checked', true );
			return;
		}
		var $select = $container.find( 'select[name="wc_optic_' + eye + '_child"]' );
		if ( $select.length ) {
			$select.val( value ).trigger( 'change.select2' );
		}
	}

	function initChildDropdowns( $scope ) {
		if ( typeof $.fn.selectWoo !== 'function' ) {
			return;
		}

		( $scope && $scope.length ? $scope : $( document ) )
			.find( 'select.wc-optic-child-dropdown:visible' )
			.each( function () {
				var $el = $( this );
				if ( $el.data( 'select2' ) ) {
					$el.next( '.select2-container' ).css( 'width', '100%' );
					return;
				}

				$el.selectWoo( {
					width: '100%',
					minimumResultsForSearch: 0,
					allowClear: false,
					placeholder: $el.data( 'placeholder' ) || ( typeof wcOpticFront !== 'undefined' && wcOpticFront.i18n ? wcOpticFront.i18n.select : '' ),
				} );
			} );
	}

	function getPricingState() {
		var different = $( '#wc_optic_different_power' ).is( ':checked' );
		var same = ! different;
		var perEye = different;
		var leftPrice = getEyeFieldPrice( 'left' );
		var rightPrice = same ? leftPrice : getEyeFieldPrice( 'right' );
		var qty = Math.max( 1, parseInt( $( '#wc_optic_qty' ).val(), 10 ) || 1 );
		var qtyLeft = Math.max( 1, parseInt( $( '#wc_optic_qty_left' ).val(), 10 ) || 1 );
		var qtyRight = Math.max( 1, parseInt( $( '#wc_optic_qty_right' ).val(), 10 ) || 1 );
		var displayPrice = 0;
		var total = 0;

		if ( perEye ) {
			total = ( leftPrice * qtyLeft ) + ( rightPrice * qtyRight );
			displayPrice = same ? leftPrice : leftPrice + rightPrice;
		} else if ( same || getEyeFieldValue( 'left' ) === getEyeFieldValue( 'right' ) ) {
			total = leftPrice * qty;
			displayPrice = leftPrice;
		} else {
			total = ( leftPrice + rightPrice ) * qty;
			displayPrice = leftPrice + rightPrice;
		}

		return {
			displayPrice: displayPrice,
			total: total,
		};
	}

	function syncLineQuantity() {
		var perEye = $( '#wc_optic_different_power' ).is( ':checked' );
		var q = 1;
		if ( perEye ) {
			var l = parseInt( $( '#wc_optic_qty_left' ).val(), 10 ) || 0;
			var r = parseInt( $( '#wc_optic_qty_right' ).val(), 10 ) || 0;
			q = Math.max( 1, l + r );
		} else {
			q = Math.max( 1, parseInt( $( '#wc_optic_qty' ).val(), 10 ) || 1 );
		}
		$( '#wc_optic_line_quantity' ).val( q );
		updateLineTotal();
	}

	function applyMaxValue( $input, maxValue ) {
		if ( ! $input.length ) {
			return;
		}

		if ( maxValue === null || typeof maxValue === 'undefined' ) {
			$input.removeAttr( 'max' );
			return;
		}

		maxValue = Math.max( 1, parseInt( maxValue, 10 ) || 1 );
		$input.attr( 'max', maxValue );

		var current = parseInt( $input.val(), 10 ) || 1;
		if ( current > maxValue ) {
			$input.val( maxValue );
		}
	}

	function syncQuantityStockLimits() {
		var different = $( '#wc_optic_different_power' ).is( ':checked' );
		if ( different ) {
			applyMaxValue( $( '#wc_optic_qty_left' ), getEyeFieldStock( 'left' ) );
			applyMaxValue( $( '#wc_optic_qty_right' ), getEyeFieldStock( 'right' ) );
			$( '#wc_optic_qty' ).removeAttr( 'max' );
		} else {
			applyMaxValue( $( '#wc_optic_qty' ), getEyeFieldStock( 'left' ) );
			$( '#wc_optic_qty_left, #wc_optic_qty_right' ).removeAttr( 'max' );
		}
	}

	function updateLineTotal() {
		var $wrap = $( '.wc-optic-pricing' );
		var $display = $( '#wc_optic_line_total_display' );
		var $unitDisplay = $( '#wc_optic_unit_price_display' );
		if ( ! $wrap.length || ! $display.length || typeof wcOpticFront === 'undefined' ) {
			return;
		}
		var pricing = getPricingState();
		$unitDisplay.text( formatPrice( pricing.displayPrice ) );
		$display.text( formatPrice( pricing.total ) );
	}

	function syncRightChildFromLeft() {
		setEyeFieldValue( 'right', getEyeFieldValue( 'left' ) );
	}

	function toggleSamePower() {
		var different = $( '#wc_optic_different_power' ).is( ':checked' );
		var same = ! different;
		var $right = $( '.wc-optic-eye--right' );
		var $both = $( '.wc-optic-title-both' );
		var $leftTitle = $( '.wc-optic-title-left' );
		var $singleQty = $( '.wc-optic-qty--single' );
		var $dualQty = $( '.wc-optic-qty--dual' );
		var $rightHeader = $( '.wc-optic-config-table__eye--right' );
		if ( same ) {
			$right.prop( 'hidden', true );
			$rightHeader.prop( 'hidden', true );
			$both.prop( 'hidden', false );
			$leftTitle.prop( 'hidden', true );
			$right.find( 'input, select' ).prop( 'required', false );
			$singleQty.prop( 'hidden', false );
			$dualQty.prop( 'hidden', true );
			syncRightChildFromLeft();
		} else {
			$right.prop( 'hidden', false );
			$rightHeader.prop( 'hidden', false );
			$both.prop( 'hidden', true );
			$leftTitle.prop( 'hidden', false );
			$right.find( 'input, select' ).prop( 'required', true );
			$singleQty.prop( 'hidden', true );
			$dualQty.prop( 'hidden', false );
			initChildDropdowns( $right );
		}
		syncQuantityStockLimits();
		syncLineQuantity();
		updateLineTotal();
	}

	$( function () {
		var $form = $( 'form.wc-optic-cart-form' );
		if ( ! $form.length ) {
			return;
		}

		initChildDropdowns( $form );
		toggleSamePower();
		syncQuantityStockLimits();
		syncLineQuantity();

		$( '#wc_optic_different_power' ).on( 'change', toggleSamePower );
		$( '#wc_optic_qty, #wc_optic_qty_left, #wc_optic_qty_right' ).on( 'change input', syncLineQuantity );
		$form.on( 'change', 'input[name="wc_optic_left_child"], input[name="wc_optic_right_child"], select[name="wc_optic_left_child"], select[name="wc_optic_right_child"]', function () {
			if ( ! $( '#wc_optic_different_power' ).is( ':checked' ) && $( this ).attr( 'name' ) === 'wc_optic_left_child' ) {
				syncRightChildFromLeft();
			}
			syncQuantityStockLimits();
			syncLineQuantity();
			updateLineTotal();
		} );

		$form.on( 'submit', function () {
			if ( ! $( '#wc_optic_different_power' ).is( ':checked' ) ) {
				syncRightChildFromLeft();
			}
			syncLineQuantity();
		} );
	} );
}( jQuery ) );
