<?php
/**
 * Stock management and low-stock alerts admin page.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Admin_Stock
 */
class WC_Optic_Admin_Stock {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_scripts' ), 20 );
	}

	/**
	 * Whether the current request is the Stock admin page.
	 *
	 * @param string $hook Optional admin_enqueue_scripts hook suffix.
	 * @return bool
	 */
	public static function is_stock_page( $hook = '' ) {
		if ( isset( $_GET['page'] ) && 'wc-optic-stock' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return WC_Optic_Admin_Menu::STOCK_SCREEN === $hook;
	}

	/**
	 * Active stock tab slug.
	 *
	 * @return string
	 */
	protected static function get_active_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'management'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'management', 'alerts' ), true ) ? $tab : 'management';
	}

	/**
	 * JS config for admin-stock.js.
	 *
	 * @param string $tab Active tab.
	 * @return array<string, mixed>
	 */
	protected static function get_js_config( $tab ) {
		return array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wc_optic_admin' ),
			'activeTab' => $tab,
			'i18n'      => array(
				'restockFailed'  => __( 'Could not update stock.', 'wc-optic' ),
				'restockSuccess' => __( 'Stock updated.', 'wc-optic' ),
				'expand'         => __( 'Show internal products', 'wc-optic' ),
				'collapse'       => __( 'Hide internal products', 'wc-optic' ),
			),
			'dt'        => 'alerts' === $tab ? self::get_datatables_i18n() : array(),
		);
	}

	/**
	 * Enqueue styles (and register scripts) on the stock page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! self::is_stock_page( $hook ) ) {
			return;
		}

		$tab       = self::get_active_tab();
		$is_alerts = 'alerts' === $tab;

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'jquery' );

		$style_deps = array();

		if ( $is_alerts ) {
			wp_enqueue_style(
				'wc-optic-datatables',
				WC_OPTIC_PLUGIN_URL . 'assets/vendor/datatables/dataTables.dataTables.min.css',
				array(),
				'2.1.8'
			);
			$style_deps[] = 'wc-optic-datatables';
			wp_register_script(
				'wc-optic-datatables',
				WC_OPTIC_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.js',
				array( 'jquery' ),
				'2.1.8',
				true
			);
		}

		wp_enqueue_style(
			'wc-optic-admin',
			WC_OPTIC_PLUGIN_URL . 'assets/css/admin.css',
			$style_deps,
			WC_OPTIC_VERSION
		);
	}

	/**
	 * Print stock page scripts in the admin footer (reliable load after page markup).
	 */
	public static function print_scripts() {
		if ( ! self::is_stock_page() ) {
			return;
		}

		$tab       = self::get_active_tab();
		$is_alerts = 'alerts' === $tab;
		$stock_js  = WC_OPTIC_PLUGIN_DIR . 'assets/js/admin-stock.js';
		$version   = is_readable( $stock_js ) ? (string) filemtime( $stock_js ) : WC_OPTIC_VERSION;

		if ( $is_alerts ) {
			echo '<script src="' . esc_url( WC_OPTIC_PLUGIN_URL . 'assets/vendor/datatables/dataTables.min.js' ) . '?ver=2.1.8"></script>' . "\n";
		}

		echo '<script id="wc-optic-stock-config">var wcOpticStock = ';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode.
		echo wp_json_encode( self::get_js_config( $tab ) );
		echo ';</script>' . "\n";

		echo '<script src="' . esc_url( WC_OPTIC_PLUGIN_URL . 'assets/js/admin-stock.js' ) . '?ver=' . esc_attr( $version ) . '"></script>' . "\n";
	}

	/**
	 * DataTables UI strings.
	 *
	 * @return array<string, mixed>
	 */
	protected static function get_datatables_i18n() {
		return array(
			'emptyTable'     => __( 'No data available in table', 'wc-optic' ),
			'info'           => __( 'Showing _START_ to _END_ of _TOTAL_ entries', 'wc-optic' ),
			'infoEmpty'      => __( 'Showing 0 to 0 of 0 entries', 'wc-optic' ),
			'infoFiltered'   => __( '(filtered from _MAX_ total entries)', 'wc-optic' ),
			'lengthMenu'     => __( 'Show _MENU_ entries', 'wc-optic' ),
			'search'         => __( 'Search:', 'wc-optic' ),
			'zeroRecords'    => __( 'No matching records found', 'wc-optic' ),
			'paginate'       => array(
				'first'    => __( 'First', 'wc-optic' ),
				'last'     => __( 'Last', 'wc-optic' ),
				'next'     => __( 'Next', 'wc-optic' ),
				'previous' => __( 'Previous', 'wc-optic' ),
			),
		);
	}

	/**
	 * Render stock page with management and alerts tabs.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'management'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'management', 'alerts' ), true ) ) {
			$tab = 'management';
		}

		$alert_count = WC_Optic_Stock::get_alert_count();
		$alert_qty   = WC_Optic_Stock::get_alert_qty();

		echo '<div class="wrap woocommerce wc-optic-stock-wrap" id="wc-optic-stock-root" data-active-tab="' . esc_attr( $tab ) . '">';
		echo '<h1>' . esc_html__( 'Stock', 'wc-optic' ) . '</h1>';

		echo '<p class="description">';
		if ( WC_Optic_Stock::is_alert_enabled() ) {
			echo esc_html(
				sprintf(
					/* translators: %d: low-stock alert threshold */
					__( 'Global alert threshold: %d unit(s) or less (override per internal product in product edit screen).', 'wc-optic' ),
					$alert_qty
				)
			);
		} else {
			echo esc_html__( 'Stock alerts are disabled in Settings.', 'wc-optic' );
		}
		echo '</p>';

		echo '<h2 class="nav-tab-wrapper wc-optic-stock-tabs">';
		$mgmt_url  = admin_url( 'admin.php?page=wc-optic-stock&tab=management' );
		$mgmt_cls  = 'management' === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
		$alert_url = admin_url( 'admin.php?page=wc-optic-stock&tab=alerts' );
		$alert_cls = 'alerts' === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a class="' . esc_attr( $mgmt_cls ) . '" href="' . esc_url( $mgmt_url ) . '">' . esc_html__( 'Stock management', 'wc-optic' ) . '</a>';
		echo '<a class="' . esc_attr( $alert_cls ) . '" href="' . esc_url( $alert_url ) . '">';
		echo esc_html__( 'Stock alerts', 'wc-optic' );
		if ( $alert_count > 0 ) {
			echo ' <span class="wc-optic-stock-tab-badge count-' . esc_attr( (string) $alert_count ) . '"><span class="count">' . esc_html( number_format_i18n( $alert_count ) ) . '</span></span>';
		}
		echo '</a>';
		echo '</h2>';

		if ( 'alerts' === $tab ) {
			self::render_alerts_tab();
		} else {
			self::render_management_tab();
		}

		self::render_restock_modal();

		echo '</div>';
	}

	/**
	 * Render Global / Custom badge.
	 *
	 * @param bool $is_custom Whether the value is custom.
	 */
	protected static function render_source_badge( $is_custom ) {
		if ( $is_custom ) {
			echo '<span class="wc-optic-stock-badge wc-optic-stock-badge--custom">' . esc_html__( 'Custom', 'wc-optic' ) . '</span>';
			return;
		}

		echo '<span class="wc-optic-stock-badge wc-optic-stock-badge--global">' . esc_html__( 'Global', 'wc-optic' ) . '</span>';
	}

	/**
	 * Render hierarchical stock management table (collapsible parent rows).
	 */
	protected static function render_management_tab() {
		$tree = WC_Optic_Stock::get_inventory_tree();

		if ( empty( $tree ) ) {
			echo '<p>' . esc_html__( 'No optic products with internal variants found.', 'wc-optic' ) . '</p>';
			return;
		}

		echo '<div class="wc-optic-stock-mgmt" id="wc-optic-stock-management">';
		echo '<div class="wc-optic-stock-toolbar">';
		echo '<label class="screen-reader-text" for="wc-optic-stock-search">' . esc_html__( 'Search products', 'wc-optic' ) . '</label>';
		echo '<input type="search" id="wc-optic-stock-search" class="wc-optic-stock-search" placeholder="' . esc_attr__( 'Search product or SKU…', 'wc-optic' ) . '" autocomplete="off" />';
		echo '<div class="wc-optic-stock-toolbar__actions">';
		echo '<button type="button" class="button button-secondary wc-optic-stock-expand-all">' . esc_html__( 'Expand all', 'wc-optic' ) . '</button> ';
		echo '<button type="button" class="button button-secondary wc-optic-stock-collapse-all">' . esc_html__( 'Collapse all', 'wc-optic' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '<p class="wc-optic-stock-search-empty wc-optic-is-hidden" aria-live="polite">' . esc_html__( 'No products match your search.', 'wc-optic' ) . '</p>';

		echo '<table class="widefat wc-optic-stock-table wc-optic-stock-table--management">';
		echo '<thead><tr>';
		echo '<th class="wc-optic-stock-col-expand" scope="col"><span class="screen-reader-text">' . esc_html__( 'Expand', 'wc-optic' ) . '</span></th>';
		echo '<th scope="col">' . esc_html__( 'Product name', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'SKU', 'wc-optic' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $tree as $parent ) {
			$product_id   = (int) $parent['product_id'];
			$child_count  = (int) $parent['child_count'];
			$has_children = $child_count > 0;
			$row_id      = 'wc-optic-stock-parent-' . $product_id;
			$search_bits = array(
				(string) $parent['name'],
				(string) $parent['sku'],
			);
			foreach ( $parent['children'] as $child_row ) {
				$search_bits[] = (string) ( $child_row['sku'] ?? '' );
				$search_bits[] = (string) ( $child_row['powers'] ?? '' );
			}
			$search_blob = strtolower( implode( ' ', array_filter( $search_bits ) ) );

			echo '<tr class="wc-optic-stock-parent" id="' . esc_attr( $row_id ) . '"';
			echo ' data-product-id="' . esc_attr( (string) $product_id ) . '"';
			echo ' data-search="' . esc_attr( $search_blob ) . '">';
			echo '<td class="wc-optic-stock-col-expand">';
			if ( $has_children ) {
				echo '<button type="button" class="wc-optic-stock-expand" aria-expanded="false" aria-controls="' . esc_attr( $row_id ) . '-children" title="' . esc_attr__( 'Show internal products', 'wc-optic' ) . '">';
				echo '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
				echo '<span class="screen-reader-text">' . esc_html__( 'Show internal products', 'wc-optic' ) . '</span>';
				echo '</button>';
			}
			echo '</td>';
			echo '<td class="wc-optic-stock-parent__name">';
			if ( ! empty( $parent['edit_url'] ) ) {
				echo '<a href="' . esc_url( $parent['edit_url'] ) . '">' . esc_html( (string) $parent['name'] ) . '</a>';
			} else {
				echo esc_html( (string) $parent['name'] );
			}
			if ( $has_children ) {
				echo ' <span class="wc-optic-stock-parent__count">' . esc_html( number_format_i18n( $child_count ) ) . ' ' . esc_html__( 'variants', 'wc-optic' ) . '</span>';
			}
			echo '</td>';
			echo '<td class="wc-optic-stock-parent__sku"><code>' . esc_html( (string) $parent['sku'] ) . '</code></td>';
			echo '</tr>';

			if ( ! $has_children ) {
				continue;
			}

			echo '<tr class="wc-optic-stock-children-row wc-optic-is-hidden" id="' . esc_attr( $row_id ) . '-children" data-parent-product-id="' . esc_attr( (string) $product_id ) . '">';
			echo '<td colspan="3" class="wc-optic-stock-children-cell">';
			echo '<div class="wc-optic-stock-children-panel">';
			echo '<table class="widefat wc-optic-stock-children-table">';
			echo '<thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Power', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'SKU', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Current stock', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Backorder units', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Custom backorder', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Alert threshold', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Custom alert', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Price', 'wc-optic' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Actions', 'wc-optic' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $parent['children'] as $child ) {
				$low_class   = ! empty( $child['is_low'] ) ? ' wc-optic-stock-child--low' : '';
				$stock_label = null === $child['stock'] ? '—' : (string) $child['stock'];

				echo '<tr class="wc-optic-stock-child' . esc_attr( $low_class ) . '"';
				echo ' data-product-id="' . esc_attr( (string) $child['product_id'] ) . '"';
				echo ' data-child-id="' . esc_attr( (string) $child['child_id'] ) . '"';
				echo ' data-sku="' . esc_attr( (string) $child['sku'] ) . '">';
				echo '<td class="wc-optic-stock-child__power">' . esc_html( (string) $child['powers'] ) . '</td>';
				echo '<td><code>' . esc_html( (string) $child['sku'] ) . '</code></td>';
				echo '<td class="wc-optic-stock-child__qty">';
				echo '<span class="wc-optic-stock-qty-value">' . esc_html( $stock_label ) . '</span>';
				if ( ! empty( $child['is_low'] ) ) {
					echo ' <span class="wc-optic-stock-low-badge" title="' . esc_attr__( 'Low stock', 'wc-optic' ) . '">' . esc_html__( 'Low', 'wc-optic' ) . '</span>';
				}
				echo '</td>';
				echo '<td>';
				echo esc_html( (string) $child['backorder_units'] );
				if ( (int) $child['backorder_consumed'] > 0 ) {
					echo ' <span class="description">(';
					echo esc_html(
						sprintf(
							/* translators: %d: consumed backorder units */
							__( '%d sold', 'wc-optic' ),
							(int) $child['backorder_consumed']
						)
					);
					echo ')</span>';
				}
				echo '</td>';
				echo '<td>';
				self::render_source_badge( ! empty( $child['backorder_custom'] ) );
				echo '</td>';
				echo '<td>' . esc_html( (string) $child['alert_threshold'] ) . '</td>';
				echo '<td>';
				self::render_source_badge( ! empty( $child['alert_custom'] ) );
				echo '</td>';
				echo '<td class="wc-optic-stock-child__price">';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price HTML.
				echo $child['price_html'];
				echo '</td>';
				echo '<td class="wc-optic-stock-child__actions">';
				echo '<button type="button" class="button button-secondary wc-optic-restock-btn">';
				echo esc_html__( 'Restock', 'wc-optic' );
				echo '</button>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render low-stock alerts DataTable.
	 */
	protected static function render_alerts_tab() {
		$alerts = WC_Optic_Stock::get_alerts();

		if ( empty( $alerts ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'No stock alerts at the moment.', 'wc-optic' ) . '</p></div>';
			return;
		}

		echo '<div class="wc-optic-datatable-wrap">';
		echo '<table id="wc-optic-stock-alerts-dt" class="wc-optic-stock-table wc-optic-stock-table--alerts display stripe" style="width:100%">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'QR code', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Internal SKU', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Power', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Product', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Current quantity', 'wc-optic' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Actions', 'wc-optic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $alerts as $alert ) {
			echo '<tr class="wc-optic-stock-alert"';
			echo ' data-product-id="' . esc_attr( (string) $alert['product_id'] ) . '"';
			echo ' data-child-id="' . esc_attr( (string) $alert['child_id'] ) . '"';
			echo ' data-sku="' . esc_attr( (string) $alert['sku'] ) . '">';
			echo '<td class="wc-optic-stock-alert__qr">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built via WC_Optic_QR.
			echo $alert['qr_html'];
			echo '</td>';
			echo '<td><code>' . esc_html( (string) $alert['sku'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $alert['powers'] ) . '</td>';
			echo '<td>' . esc_html( (string) $alert['product_name'] ) . '</td>';
			echo '<td class="wc-optic-stock-child__qty" data-order="' . esc_attr( (string) (int) $alert['stock'] ) . '">';
			echo '<span class="wc-optic-stock-qty-value">' . esc_html( (string) $alert['stock'] ) . '</span>';
			echo '</td>';
			echo '<td>';
			echo '<button type="button" class="button button-primary wc-optic-restock-btn">';
			echo esc_html__( 'Restock', 'wc-optic' );
			echo '</button>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Restock modal shell (filled by JS).
	 */
	protected static function render_restock_modal() {
		echo '<div id="wc-optic-restock-modal" class="wc-optic-restock-modal wc-optic-is-hidden" role="dialog" aria-modal="true" aria-labelledby="wc-optic-restock-modal-title" hidden>';
		echo '<div class="wc-optic-restock-modal__backdrop" tabindex="-1"></div>';
		echo '<div class="wc-optic-restock-modal__panel">';
		echo '<h2 id="wc-optic-restock-modal-title" class="wc-optic-restock-modal__title">' . esc_html__( 'Restock internal product', 'wc-optic' ) . '</h2>';
		echo '<p class="wc-optic-restock-modal__sku"><code></code></p>';
		echo '<p>';
		echo '<label for="wc-optic-restock-qty">' . esc_html__( 'Quantity to add', 'wc-optic' ) . '</label><br />';
		echo '<input type="number" id="wc-optic-restock-qty" class="wc-optic-restock-modal__input" min="1" step="1" value="1" />';
		echo '</p>';
		echo '<p class="wc-optic-restock-modal__actions">';
		echo '<button type="button" class="button button-primary wc-optic-restock-modal__confirm">' . esc_html__( 'Add stock', 'wc-optic' ) . '</button> ';
		echo '<button type="button" class="button wc-optic-restock-modal__cancel">' . esc_html__( 'Cancel', 'wc-optic' ) . '</button>';
		echo '</p>';
		echo '<p class="wc-optic-restock-modal__message" aria-live="polite"></p>';
		echo '</div>';
		echo '</div>';
	}
}
