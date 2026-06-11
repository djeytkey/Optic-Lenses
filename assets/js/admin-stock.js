( function ( $ ) {
	'use strict';

	var cfg = window.wcOpticStock || {};
	var i18n = cfg.i18n || {};
	var dtLang = cfg.dt || {};
	var $root = $( '#wc-optic-stock-root' );
	var $modal = $( '#wc-optic-restock-modal' );
	var activeRow = null;
	var alertsTable = null;

	function getDataTableDefaults() {
		return {
			pageLength: 25,
			lengthMenu: [
				[ 10, 25, 50, 100, -1 ],
				[ 10, 25, 50, 100, 'All' ],
			],
			language: dtLang,
			autoWidth: false,
		};
	}

	function initAlertsDataTable() {
		var $table = $( '#wc-optic-stock-alerts-dt' );
		if ( ! $table.length || ! $.fn.DataTable ) {
			return;
		}

		alertsTable = $table.DataTable(
			$.extend( true, {}, getDataTableDefaults(), {
				order: [ [ 4, 'asc' ], [ 3, 'asc' ] ],
				columnDefs: [
					{ orderable: false, targets: [ 0, 5 ] },
					{ type: 'num', targets: [ 4 ] },
				],
			} )
		);
	}

	function setRowExpanded( $parent, expanded ) {
		var $btn = $parent.find( '.wc-optic-stock-expand' );
		var controls = $btn.attr( 'aria-controls' );
		var $children = controls ? $( '#' + controls ) : $();

		$parent.toggleClass( 'wc-optic-stock-parent--expanded', expanded );
		$btn.attr( 'aria-expanded', expanded ? 'true' : 'false' );
		$btn.attr(
			'title',
			expanded ? i18n.collapse || '' : i18n.expand || ''
		);
		$children.toggleClass( 'wc-optic-is-hidden', ! expanded );
	}

	function toggleRow( $parent ) {
		var expanded = $parent.hasClass( 'wc-optic-stock-parent--expanded' );
		setRowExpanded( $parent, ! expanded );
	}

	function expandAllParents() {
		$( '#wc-optic-stock-management .wc-optic-stock-parent' ).each(
			function () {
				setRowExpanded( $( this ), true );
			}
		);
	}

	function collapseAllParents() {
		$( '#wc-optic-stock-management .wc-optic-stock-parent' ).each(
			function () {
				setRowExpanded( $( this ), false );
			}
		);
	}

	function filterManagementTable( query ) {
		var $mgmt = $( '#wc-optic-stock-management' );
		if ( ! $mgmt.length ) {
			return;
		}

		var term = ( query || '' ).toLowerCase().trim();
		var visible = 0;

		$mgmt.find( '.wc-optic-stock-parent' ).each( function () {
			var $parent = $( this );
			var blob = String( $parent.data( 'search' ) || '' );
			var $children = $(
				'#' + $parent.find( '.wc-optic-stock-expand' ).attr( 'aria-controls' )
			);
			var match = ! term || blob.indexOf( term ) !== -1;

			$parent.toggleClass( 'wc-optic-is-hidden', ! match );
			if ( $children.length ) {
				$children.toggleClass( 'wc-optic-is-hidden', ! match || ! $parent.hasClass( 'wc-optic-stock-parent--expanded' ) );
			}

			if ( match ) {
				visible += 1;
			}
		} );

		$mgmt
			.find( '.wc-optic-stock-search-empty' )
			.toggleClass( 'wc-optic-is-hidden', visible > 0 || ! term );
	}

	function openModal( $row ) {
		activeRow = $row;
		var sku = $row.data( 'sku' ) || '';
		$modal.find( '.wc-optic-restock-modal__sku code' ).text( sku );
		$modal.find( '#wc-optic-restock-qty' ).val( 1 );
		$modal.find( '.wc-optic-restock-modal__message' ).text( '' );
		$modal.removeClass( 'wc-optic-is-hidden' ).removeAttr( 'hidden' );
		$( '#wc-optic-restock-qty' ).trigger( 'focus' );
	}

	function closeModal() {
		activeRow = null;
		$modal.addClass( 'wc-optic-is-hidden' ).attr( 'hidden', 'hidden' );
	}

	function updateQtyCells( productId, childId, stock, isLow ) {
		var selector =
			'[data-product-id="' +
			productId +
			'"][data-child-id="' +
			childId +
			'"]';

		$( selector ).each( function () {
			var $row = $( this );
			$row.find( '.wc-optic-stock-qty-value' ).text( stock );
			$row.toggleClass( 'wc-optic-stock-child--low', !! isLow );
			$row.find( '.wc-optic-stock-low-badge' ).toggle( !! isLow );
		} );

		if ( alertsTable ) {
			alertsTable.rows().invalidate( 'dom' ).draw( false );
		}
	}

	function removeAlertRow( $row ) {
		if ( alertsTable && $row && $row.length ) {
			alertsTable.row( $row ).remove().draw( false );
			if ( alertsTable.rows().count() < 1 ) {
				window.location.reload();
			}
			return;
		}

		$row.fadeOut( 200, function () {
			$( this ).remove();
			if ( ! $( '.wc-optic-stock-alert' ).length ) {
				window.location.reload();
			}
		} );
	}

	function restock( qty ) {
		if ( ! activeRow || ! activeRow.length ) {
			return;
		}

		var productId = activeRow.data( 'product-id' );
		var childId = activeRow.data( 'child-id' );
		var $message = $modal.find( '.wc-optic-restock-modal__message' );
		var $confirm = $modal.find( '.wc-optic-restock-modal__confirm' );

		$confirm.prop( 'disabled', true );
		$message.text( '' );

		$.post( cfg.ajaxUrl, {
			action: 'wc_optic_restock_child',
			nonce: cfg.nonce,
			product_id: productId,
			child_id: childId,
			qty: qty,
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					$message.text( i18n.restockFailed || 'Error' );
					return;
				}

				updateQtyCells(
					productId,
					childId,
					response.data.stock,
					response.data.is_low
				);

				$message.text( i18n.restockSuccess || 'OK' );

				if (
					( cfg.activeTab === 'alerts' ||
						$root.data( 'active-tab' ) === 'alerts' ) &&
					! response.data.is_low
				) {
					removeAlertRow( activeRow );
				}

				window.setTimeout( closeModal, 600 );
			} )
			.fail( function ( xhr ) {
				var message = i18n.restockFailed || 'Error';
				if (
					xhr.responseJSON &&
					xhr.responseJSON.data &&
					xhr.responseJSON.data.message
				) {
					message = xhr.responseJSON.data.message;
				}
				$message.text( message );
			} )
			.always( function () {
				$confirm.prop( 'disabled', false );
			} );
	}

	$root.on( 'click', '.wc-optic-stock-expand', function ( event ) {
		event.preventDefault();
		event.stopPropagation();
		toggleRow( $( this ).closest( '.wc-optic-stock-parent' ) );
	} );

	$root.on( 'click', '.wc-optic-stock-parent', function ( event ) {
		if (
			$( event.target ).closest( 'a, button, .wc-optic-stock-expand' )
				.length
		) {
			return;
		}
		if ( ! $( this ).find( '.wc-optic-stock-expand' ).length ) {
			return;
		}
		toggleRow( $( this ) );
	} );

	$root.on( 'click', '.wc-optic-stock-expand-all', function ( event ) {
		event.preventDefault();
		expandAllParents();
	} );

	$root.on( 'click', '.wc-optic-stock-collapse-all', function ( event ) {
		event.preventDefault();
		collapseAllParents();
	} );

	$root.on( 'input', '#wc-optic-stock-search', function () {
		filterManagementTable( $( this ).val() );
	} );

	$root.on( 'click', '.wc-optic-restock-btn', function () {
		openModal( $( this ).closest( 'tr' ) );
	} );

	$modal.on(
		'click',
		'.wc-optic-restock-modal__cancel, .wc-optic-restock-modal__backdrop',
		function () {
			closeModal();
		}
	);

	$modal.on( 'click', '.wc-optic-restock-modal__confirm', function () {
		var qty = parseInt( $( '#wc-optic-restock-qty' ).val(), 10 );
		if ( ! qty || qty < 1 ) {
			$modal.find( '#wc-optic-restock-qty' ).val( 1 ).trigger( 'focus' );
			return;
		}
		restock( qty );
	} );

	$( document ).on( 'keydown', function ( event ) {
		if (
			event.key === 'Escape' &&
			! $modal.hasClass( 'wc-optic-is-hidden' )
		) {
			closeModal();
		}
	} );

	$( function () {
		if ( cfg.activeTab === 'alerts' || $root.data( 'active-tab' ) === 'alerts' ) {
			initAlertsDataTable();
		}
	} );
} )( jQuery );
