( function ( $ ) {
	'use strict';

	var $panel = null;
	var childIndexCounter = 0;
	var copiedCatalogTypes = [ 'section', 'company', 'brand', 'timing', 'color' ];

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
				closeOnSelect: ! $el.prop( 'multiple' ),
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

	function getChildBlocks() {
		return getPanel().find( '.wc-optic-child-config' );
	}

	function nextChildIndex() {
		childIndexCounter += 1;
		return childIndexCounter;
	}

	function syncChildCounter() {
		getChildBlocks().each( function () {
			var raw = parseInt( $( this ).attr( 'data-child-index' ), 10 );
			if ( ! isNaN( raw ) && raw >= childIndexCounter ) {
				childIndexCounter = raw;
			}
		} );
	}

	function getChildTitle( $block, index ) {
		var label = $.trim( $block.find( '.wc-optic-child-label' ).val() || '' );
		if ( label ) {
			return label;
		}
		return ( wcOpticAdmin.i18n && wcOpticAdmin.i18n.product ? wcOpticAdmin.i18n.product : 'Product' ) + ' ' + ( index + 1 );
	}

	function renumberBlocks() {
		getChildBlocks().each( function ( index ) {
			var $block = $( this );
			$block.find( '.wc-optic-child-sort' ).val( index );
			$block.find( '.wc-optic-child-config__title' ).text( getChildTitle( $block, index ) );
		} );
	}

	function applyDivisionPowerFields() {
		var division = getSelectedDivision();
		var allowed = getAllowedPowers( division );

		getChildBlocks().find( '.wc-optic-child-power' ).each( function () {
			var $row = $( this );
			var $select = $row.find( 'select.wc-optic-child-select' );
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

	function collectChildConfig( $block ) {
		var config = {
			id: $block.find( '.wc-optic-child-id' ).val() || '',
			label: $block.find( '.wc-optic-child-label' ).val() || '',
			enabled: $block.find( 'input[type="checkbox"][name*="[enabled]"]' ).is( ':checked' ) ? '1' : '',
			sort: $block.find( '.wc-optic-child-sort' ).val() || '0',
			unit_price: $block.find( '.wc-optic-child-unit-price' ).val() || '',
			catalog: {},
			powers: {},
		};

		$block.find( 'select.wc-optic-child-select' ).each( function () {
			var $el = $( this );
			var type = $el.data( 'optic-type' );
			if ( ! type ) {
				return;
			}
			if ( $el.data( 'is-power' ) ) {
				config.powers[ type ] = $el.val() || '';
			} else {
				config.catalog[ type ] = $el.val() || '';
			}
		} );

		return config;
	}

	function refreshBlockSkuPreview( $block ) {
		if ( ! $block || ! $block.length ) {
			return;
		}

		var data = {
			action: 'wc_optic_preview_sku',
			nonce: wcOpticAdmin.nonce,
			optic_division: getSelectedDivision(),
			child_config: collectChildConfig( $block ),
		};

		$.post( wcOpticAdmin.ajaxUrl, data, function ( res ) {
			if ( res && res.success && res.data && typeof res.data.sku === 'string' ) {
				$block.find( '.wc-optic-child-sku-preview' ).text( res.data.sku );
			}
		} );
	}

	function refreshAllSkuPreviews() {
		getChildBlocks().each( function () {
			refreshBlockSkuPreview( $( this ) );
		} );
	}

	function initChildBlock( $block ) {
		if ( ! $block || ! $block.length ) {
			return;
		}
		$block.find( 'select.wc-optic-select2' ).each( function () {
			destroySelect2( $( this ) );
			initSelect2( $( this ) );
		} );
		refreshBlockSkuPreview( $block );
	}

	function copyCatalogValuesFromFirstChild( $targetBlock ) {
		var $blocks = getChildBlocks();
		var $sourceBlock = $blocks.first();

		if (
			! $targetBlock ||
			! $targetBlock.length ||
			! $sourceBlock.length ||
			$sourceBlock.is( $targetBlock )
		) {
			return;
		}

		$.each( copiedCatalogTypes, function ( _, type ) {
			var $source = $sourceBlock.find( 'select.wc-optic-child-select[data-optic-type="' + type + '"]' );
			var $target = $targetBlock.find( 'select.wc-optic-child-select[data-optic-type="' + type + '"]' );

			if ( ! $source.length || ! $target.length ) {
				return;
			}

			$target.val( $source.val() || '' );
		} );
	}

	function addChildBlock() {
		var tpl = $( '#wc-optic-child-config-template' ).html();
		if ( ! tpl ) {
			return;
		}

		var index = nextChildIndex();
		var html = tpl.replace( /__INDEX__/g, String( index ) );
		var $block = $( $.trim( html ) );
		$block.find( '.wc-optic-child-id' ).val( 'child_' + index );
		getPanel().find( '#wc-optic-child-config-list' ).append( $block );
		copyCatalogValuesFromFirstChild( $block );
		initChildBlock( $block );
		applyDivisionPowerFields();
		renumberBlocks();
	}

	function initOpticProductPanel() {
		getPanel().find( 'select.wc-optic-select2' ).each( function () {
			destroySelect2( $( this ) );
		} );
		syncChildCounter();
		applyDivisionPowerFields();
		initAllOpticSelect2();
		renumberBlocks();
		refreshAllSkuPreviews();
	}

	function isOpticProductScreen() {
		return $( 'select#product-type' ).val() === 'optic_product';
	}

	function shouldDefaultToOpticProduct() {
		return !! ( wcOpticAdmin && wcOpticAdmin.isNewProduct && $( 'select#product-type option[value="optic_product"]' ).length );
	}

	function ensureDefaultOpticProductType() {
		var $type = $( 'select#product-type' );
		if ( ! shouldDefaultToOpticProduct() || ! $type.length || isOpticProductScreen() ) {
			return;
		}

		$type.val( 'optic_product' ).trigger( 'change' );
	}

	$( document.body )
		.on( 'change', '.wc-optic-child-select', function () {
			refreshBlockSkuPreview( $( this ).closest( '.wc-optic-child-config' ) );
		} )
		.on( 'input', '.wc-optic-child-label', function () {
			renumberBlocks();
		} )
		.on( 'input', '.wc-optic-child-unit-price', function () {
			refreshBlockSkuPreview( $( this ).closest( '.wc-optic-child-config' ) );
		} )
		.on( 'change', '#_optic_division', function () {
			applyDivisionPowerFields();
			refreshAllSkuPreviews();
			renumberBlocks();
		} )
		.on( 'click', '#wc-optic-add-child', function ( e ) {
			e.preventDefault();
			addChildBlock();
		} )
		.on( 'click', '.wc-optic-remove-child', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.wc-optic-child-config' ).remove();
			renumberBlocks();
			refreshAllSkuPreviews();
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
		ensureDefaultOpticProductType();
		if ( isOpticProductScreen() ) {
			initOpticProductPanel();
		}
	} );
}( jQuery ) );
