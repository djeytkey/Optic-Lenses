( function ( $ ) {
	'use strict';

	/**
	 * Unique key for new rows (matches PHP `new_` + unique id pattern).
	 *
	 * @return {string}
	 */
	function newRowSuffix() {
		return 'new_' + Date.now() + '_' + Math.random().toString( 36 ).slice( 2, 11 );
	}

	/**
	 * Append one empty catalog row to the settings table.
	 *
	 * @return {jQuery}
	 */
	function buildEmptyRow() {
		var suffix = newRowSuffix();
		var pf = 'wc_optic_row[' + suffix + ']';
		var html =
			'<tr class="wc-optic-new-row">' +
			'<td><input type="text" name="' +
			pf +
			'[name]" value="" class="regular-text wc-optic-catalog-name" autocomplete="off" required /></td>' +
			'<td><input type="text" name="' +
			pf +
			'[sku_fragment]" value="" class="regular-text wc-optic-catalog-fragment" autocomplete="off" required /></td>' +
			'<td><input type="number" name="' +
			pf +
			'[sort_order]" value="0" class="small-text" /></td>' +
			'<td></td>' +
			'</tr>';
		return $( html );
	}

	/**
	 * Parse JSON error message from failed admin-ajax response.
	 *
	 * @param {jqXHR} xhr XHR.
	 * @param {string} fallback Fallback message.
	 * @return {string}
	 */
	function parseAjaxErrorMessage( xhr, fallback ) {
		if ( ! xhr || ! xhr.responseText ) {
			return fallback;
		}
		try {
			var r = JSON.parse( xhr.responseText );
			if ( r && r.data && r.data.message ) {
				return r.data.message;
			}
		} catch ( e ) {
			// ignore
		}
		return fallback;
	}

	/**
	 * After AJAX delete: show products that still referenced the term.
	 *
	 * @param {Object} data Response data from wp_send_json_success.
	 */
	function showDeletionFollowupNotice( data ) {
		var $box = $( '#wc-optic-inline-messages' );
		if ( ! $box.length ) {
			return;
		}
		$box.empty();
		var list = data && data.affected_products ? data.affected_products : [];
		if ( ! list.length ) {
			var $info = $( '<div class="notice notice-info is-dismissible"></div>' );
			$info.append( $( '<p></p>' ).text( wcOpticAdmin.i18n.affectedNone ) );
			$box.append( $info );
			return;
		}
		var $warn = $( '<div class="notice notice-warning is-dismissible"></div>' );
		$warn.append( $( '<p></p>' ).text( wcOpticAdmin.i18n.affectedNoticeTitle ) );
		var $ul = $( '<ul class="wc-optic-affected-list"></ul>' );
		list.forEach( function ( p ) {
			var $li = $( '<li></li>' );
			if ( p.edit_url ) {
				$li.append(
					$( '<a></a>' )
						.attr( 'href', p.edit_url )
						.text( p.name + ' (#' + p.id + ')' )
				);
			} else {
				$li.text( ( p.name || '' ) + ' (#' + p.id + ')' );
			}
			$ul.append( $li );
		} );
		$warn.append( $ul );
		$box.append( $warn );
		if ( $box[ 0 ] && $box[ 0 ].scrollIntoView ) {
			$box[ 0 ].scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
		}
	}

	$( function () {
		var $root = $( '#wc-optic-settings-root' );

		$( '#wc-optic-add-row' ).on( 'click', function () {
			var $tbody = $( 'table.wc-optic-settings-table tbody' );
			if ( ! $tbody.length ) {
				return;
			}
			var $tr = buildEmptyRow();
			$tbody.append( $tr );
			$tr.find( '.wc-optic-catalog-name' ).first().trigger( 'focus' );
		} );

		$( document ).on( 'click', '.wc-optic-delete-row', function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			var $tr = $btn.closest( 'tr' );
			var id = parseInt( $btn.attr( 'data-id' ), 10 );
			var tab = $root.attr( 'data-active-tab' ) || 'section';
			var name = $.trim( $tr.find( 'input[name*="[name]"]' ).first().val() || '' );
			var msg = wcOpticAdmin.i18n.confirmDelete;
			if ( name ) {
				msg += '\n\n' + name;
			}
			if ( ! window.confirm( msg ) ) {
				return;
			}
			$btn.prop( 'disabled', true );
			$.ajax( {
				url: wcOpticAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_optic_delete_term',
					nonce: wcOpticAdmin.nonce,
					id: id,
					term_type: tab,
				},
			} )
				.done( function ( res ) {
					if ( res && res.success ) {
						showDeletionFollowupNotice( res.data || {} );
						$tr.fadeOut( 200, function () {
							$( this ).remove();
						} );
					} else {
						var m =
							res && res.data && res.data.message
								? res.data.message
								: wcOpticAdmin.i18n.deleteFailed;
						window.alert( m );
					}
				} )
				.fail( function ( xhr ) {
					window.alert(
						parseAjaxErrorMessage( xhr, wcOpticAdmin.i18n.deleteFailed )
					);
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	} );
}( jQuery ) );
