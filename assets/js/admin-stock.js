/**
 * Stock admin page interactions.
 * Loaded inline from WC_Optic_Admin_Stock::print_scripts() after wcOpticStock config.
 */
( function ( window, document, $ ) {
	'use strict';

	var cfg = window.wcOpticStock || {};
	var i18n = cfg.i18n || {};
	var dtLang = cfg.dt || {};
	var activeRow = null;
	var alertsTable = null;
	var eventsBound = false;

	function getRoot() {
		return document.getElementById( 'wc-optic-stock-root' );
	}

	function getModal() {
		return document.getElementById( 'wc-optic-restock-modal' );
	}

	function qs( selector, context ) {
		var root = context || document;
		return root.querySelector( selector );
	}

	function qsa( selector, context ) {
		var root = context || document;
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	function rowData( row, key ) {
		return row ? row.getAttribute( 'data-' + key ) || '' : '';
	}

	function closest( el, selector ) {
		while ( el && el.nodeType === 1 ) {
			if ( el.matches( selector ) ) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function setHidden( el, hidden ) {
		if ( ! el ) {
			return;
		}
		if ( hidden ) {
			el.classList.add( 'wc-optic-is-hidden' );
		} else {
			el.classList.remove( 'wc-optic-is-hidden' );
		}
	}

	function setRowExpanded( parentRow, expanded ) {
		if ( ! parentRow ) {
			return;
		}

		var btn = qs( '.wc-optic-stock-expand', parentRow );
		var controls = btn ? btn.getAttribute( 'aria-controls' ) : '';
		var childrenRow = controls ? document.getElementById( controls ) : null;

		parentRow.classList.toggle( 'wc-optic-stock-parent--expanded', expanded );

		if ( btn ) {
			btn.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			btn.setAttribute(
				'title',
				expanded ? i18n.collapse || '' : i18n.expand || ''
			);
		}

		setHidden( childrenRow, ! expanded );
	}

	function toggleRow( parentRow ) {
		if ( ! parentRow ) {
			return;
		}
		setRowExpanded(
			parentRow,
			! parentRow.classList.contains( 'wc-optic-stock-parent--expanded' )
		);
	}

	function expandAllParents() {
		qsa( '#wc-optic-stock-management .wc-optic-stock-parent' ).forEach(
			function ( row ) {
				setRowExpanded( row, true );
			}
		);
	}

	function collapseAllParents() {
		qsa( '#wc-optic-stock-management .wc-optic-stock-parent' ).forEach(
			function ( row ) {
				setRowExpanded( row, false );
			}
		);
	}

	function filterManagementTable( query ) {
		var mgmt = document.getElementById( 'wc-optic-stock-management' );
		if ( ! mgmt ) {
			return;
		}

		var term = ( query || '' ).toLowerCase().trim();
		var visible = 0;

		qsa( '.wc-optic-stock-parent', mgmt ).forEach( function ( parentRow ) {
			var blob = parentRow.getAttribute( 'data-search' ) || '';
			var btn = qs( '.wc-optic-stock-expand', parentRow );
			var controls = btn ? btn.getAttribute( 'aria-controls' ) : '';
			var childrenRow = controls
				? document.getElementById( controls )
				: null;
			var match = ! term || blob.indexOf( term ) !== -1;

			setHidden( parentRow, ! match );
			if ( childrenRow ) {
				setHidden(
					childrenRow,
					! match ||
						! parentRow.classList.contains(
							'wc-optic-stock-parent--expanded'
						)
				);
			}

			if ( match ) {
				visible += 1;
			}
		} );

		var emptyMsg = qs( '.wc-optic-stock-search-empty', mgmt );
		setHidden( emptyMsg, visible > 0 || ! term );
	}

	function openModal( row ) {
		var modal = getModal();
		if ( ! modal || ! row ) {
			return;
		}

		activeRow = row;
		var sku = rowData( row, 'sku' );
		var skuCode = qs( '.wc-optic-restock-modal__sku code', modal );
		var qtyInput = document.getElementById( 'wc-optic-restock-qty' );
		var message = qs( '.wc-optic-restock-modal__message', modal );

		if ( skuCode ) {
			skuCode.textContent = sku;
		}
		if ( qtyInput ) {
			qtyInput.value = '1';
		}
		if ( message ) {
			message.textContent = '';
		}

		modal.classList.remove( 'wc-optic-is-hidden' );
		modal.removeAttribute( 'hidden' );
		if ( qtyInput ) {
			qtyInput.focus();
		}
	}

	function closeModal() {
		var modal = getModal();
		activeRow = null;
		if ( ! modal ) {
			return;
		}
		modal.classList.add( 'wc-optic-is-hidden' );
		modal.setAttribute( 'hidden', 'hidden' );
	}

	function updateQtyCells( productId, childId, stock, isLow ) {
		qsa(
			'[data-product-id="' +
				productId +
				'"][data-child-id="' +
				childId +
				'"]'
		).forEach( function ( row ) {
			var qtyValue = qs( '.wc-optic-stock-qty-value', row );
			var lowBadge = qs( '.wc-optic-stock-low-badge', row );

			if ( qtyValue ) {
				qtyValue.textContent = String( stock );
			}
			row.classList.toggle( 'wc-optic-stock-child--low', !! isLow );
			if ( lowBadge ) {
				lowBadge.style.display = isLow ? '' : 'none';
			}
		} );

		if ( alertsTable ) {
			alertsTable.rows().invalidate( 'dom' ).draw( false );
		}
	}

	function removeAlertRow( row ) {
		if ( alertsTable && row ) {
			alertsTable.row( row ).remove().draw( false );
			if ( alertsTable.rows().count() < 1 ) {
				window.location.reload();
			}
			return;
		}

		if ( ! row || ! $ ) {
			return;
		}

		$( row ).fadeOut( 200, function () {
			$( this ).remove();
			if ( ! document.querySelector( '.wc-optic-stock-alert' ) ) {
				window.location.reload();
			}
		} );
	}

	function restock( qty ) {
		if ( ! activeRow || ! $ ) {
			return;
		}

		var productId = rowData( activeRow, 'product-id' );
		var childId = rowData( activeRow, 'child-id' );
		var modal = getModal();
		var message = modal ? qs( '.wc-optic-restock-modal__message', modal ) : null;
		var confirmBtn = modal
			? qs( '.wc-optic-restock-modal__confirm', modal )
			: null;

		if ( confirmBtn ) {
			confirmBtn.disabled = true;
		}
		if ( message ) {
			message.textContent = '';
		}

		$.post( cfg.ajaxUrl, {
			action: 'wc_optic_restock_child',
			nonce: cfg.nonce,
			product_id: productId,
			child_id: childId,
			qty: qty,
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success || ! response.data ) {
					if ( message ) {
						message.textContent = i18n.restockFailed || 'Error';
					}
					return;
				}

				updateQtyCells(
					productId,
					childId,
					response.data.stock,
					response.data.is_low
				);

				if ( message ) {
					message.textContent = i18n.restockSuccess || 'OK';
				}

				if ( cfg.activeTab === 'alerts' && ! response.data.is_low ) {
					removeAlertRow( activeRow );
				}

				window.setTimeout( closeModal, 600 );
			} )
			.fail( function ( xhr ) {
				var failMessage = i18n.restockFailed || 'Error';
				if (
					xhr.responseJSON &&
					xhr.responseJSON.data &&
					xhr.responseJSON.data.message
				) {
					failMessage = xhr.responseJSON.data.message;
				}
				if ( message ) {
					message.textContent = failMessage;
				}
			} )
			.always( function () {
				if ( confirmBtn ) {
					confirmBtn.disabled = false;
				}
			} );
	}

	function initAlertsDataTable() {
		if ( ! $ || ! $.fn.DataTable ) {
			return;
		}

		var table = $( '#wc-optic-stock-alerts-dt' );
		if ( ! table.length ) {
			return;
		}

		alertsTable = table.DataTable( {
			pageLength: 25,
			lengthMenu: [
				[ 10, 25, 50, 100, -1 ],
				[ 10, 25, 50, 100, 'All' ],
			],
			language: dtLang,
			autoWidth: false,
			order: [ [ 4, 'asc' ], [ 3, 'asc' ] ],
			columnDefs: [
				{ orderable: false, targets: [ 0, 5 ] },
				{ type: 'num', targets: [ 4 ] },
			],
		} );
	}

	function onRootClick( event ) {
		var target = event.target;

		if ( closest( target, '.wc-optic-stock-expand' ) ) {
			event.preventDefault();
			event.stopPropagation();
			toggleRow( closest( target, '.wc-optic-stock-parent' ) );
			return;
		}

		if ( closest( target, '.wc-optic-stock-expand-all' ) ) {
			event.preventDefault();
			expandAllParents();
			return;
		}

		if ( closest( target, '.wc-optic-stock-collapse-all' ) ) {
			event.preventDefault();
			collapseAllParents();
			return;
		}

		if ( closest( target, '.wc-optic-restock-btn' ) ) {
			event.preventDefault();
			openModal(
				closest( target, 'tr.wc-optic-stock-child, tr.wc-optic-stock-alert' )
			);
			return;
		}

		var parentRow = closest( target, '.wc-optic-stock-parent' );
		if (
			parentRow &&
			! closest( target, 'a, button' ) &&
			qs( '.wc-optic-stock-expand', parentRow )
		) {
			toggleRow( parentRow );
		}
	}

	function onRootInput( event ) {
		if ( event.target && event.target.id === 'wc-optic-stock-search' ) {
			filterManagementTable( event.target.value );
		}
	}

	function onDocumentKeydown( event ) {
		if ( event.key === 'Escape' ) {
			var modal = getModal();
			if ( modal && ! modal.classList.contains( 'wc-optic-is-hidden' ) ) {
				closeModal();
			}
		}
	}

	function onModalClick( event ) {
		var modal = getModal();
		if ( ! modal ) {
			return;
		}

		if (
			closest( event.target, '.wc-optic-restock-modal__cancel' ) ||
			closest( event.target, '.wc-optic-restock-modal__backdrop' )
		) {
			event.preventDefault();
			closeModal();
			return;
		}

		if ( closest( event.target, '.wc-optic-restock-modal__confirm' ) ) {
			event.preventDefault();
			var qtyInput = document.getElementById( 'wc-optic-restock-qty' );
			var qty = qtyInput ? parseInt( qtyInput.value, 10 ) : 0;
			if ( ! qty || qty < 1 ) {
				if ( qtyInput ) {
					qtyInput.value = '1';
					qtyInput.focus();
				}
				return;
			}
			restock( qty );
		}
	}

	function bindEvents() {
		if ( eventsBound ) {
			return;
		}

		var root = getRoot();
		var modal = getModal();

		if ( ! root ) {
			return;
		}

		root.addEventListener( 'click', onRootClick );
		root.addEventListener( 'input', onRootInput );
		document.addEventListener( 'keydown', onDocumentKeydown );

		if ( modal ) {
			modal.addEventListener( 'click', onModalClick );
		}

		eventsBound = true;
	}

	function init() {
		if ( ! getRoot() ) {
			return;
		}

		bindEvents();

		if ( cfg.activeTab === 'alerts' ) {
			initAlertsDataTable();
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.wcOpticStockAdminInit = init;
} )( window, document, window.jQuery );
