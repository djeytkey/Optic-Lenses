<?php
/**
 * Cart item meta, validation, session, order persistence.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Cart
 */
class WC_Optic_Cart {

	const CART_KEY = '_wc_optic';

	/**
	 * Parsed add-to-cart payload cache for the current request.
	 *
	 * @var array<int, array|WP_Error>
	 */
	protected static $parse_cache = array();

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 4 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'sync_cart_item_after_add' ), 20, 6 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'sync_cart_item_from_session' ), 20, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_admin_order_itemmeta' ) );
		add_filter( 'woocommerce_admin_html_order_item_class', array( __CLASS__, 'add_admin_order_item_class' ), 20, 3 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'render_admin_order_item_summary' ), 20, 3 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_order_assets' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'cart_item_price' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'cart_item_quantity' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_cart_scripts' ) );
		add_filter( 'woocommerce_update_cart_action_cart_updated', array( __CLASS__, 'sync_cart_optic_quantities' ), 5, 1 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart_stock' ) );
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'reduce_order_internal_stock' ) );
		add_action( 'woocommerce_restore_order_stock', array( __CLASS__, 'restore_order_internal_stock' ) );
	}

	/**
	 * Build optic payload from request (public for pricing/qty helpers).
	 *
	 * @param int $product_id Product id.
	 * @return array|WP_Error
	 */
	public static function parse_request_for_product( $product_id ) {
		return self::parse_request( $product_id );
	}

	/**
	 * Build optic payload from request.
	 *
	 * @param int $product_id Product id.
	 * @return array|WP_Error
	 */
	protected static function parse_request( $product_id ) {
		$product_id = absint( $product_id );
		if ( isset( self::$parse_cache[ $product_id ] ) ) {
			return self::$parse_cache[ $product_id ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'Invalid optic product.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		if ( ! empty( $_POST['wc_optic_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_optic_nonce'] ) ), 'wc_optic_add_to_cart' ) ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'Security check failed. Please reload the page.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		$division = $product->get_meta( '_optic_division', true );
		if ( ! $division ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'This product is not configured for prescriptions.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		if ( ! WC_Optic_Frontend::has_child_options( $product ) ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'This product has no internal products configured yet.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		$divisions        = WC_Optic_Plugin::get_divisions();
		$different        = empty( $_POST['wc_optic_different_power'] ) ? false : true;
		$same             = ! $different;
		$qty_mode         = $same ? 'single' : 'dual';
		$available_config = WC_Optic_SKU::get_purchasable_child_configs( $product );
		$default_child_id = 1 === count( $available_config ) && ! empty( $available_config[0]['id'] ) ? (string) $available_config[0]['id'] : '';

		$left  = self::parse_eye_child( $product, 'left', $division, $default_child_id );
		$right = $same ? $left : self::parse_eye_child( $product, 'right', $division, $default_child_id );

		if ( is_wp_error( $left ) ) {
			self::$parse_cache[ $product_id ] = $left;
			return self::$parse_cache[ $product_id ];
		}
		if ( is_wp_error( $right ) ) {
			self::$parse_cache[ $product_id ] = $right;
			return self::$parse_cache[ $product_id ];
		}

		$qty       = isset( $_POST['wc_optic_qty'] ) ? max( 1, (int) $_POST['wc_optic_qty'] ) : 1;
		$qty_left  = isset( $_POST['wc_optic_qty_left'] ) ? max( 0, (int) $_POST['wc_optic_qty_left'] ) : 0;
		$qty_right = isset( $_POST['wc_optic_qty_right'] ) ? max( 0, (int) $_POST['wc_optic_qty_right'] ) : 0;

		if ( 'dual' === $qty_mode ) {
			if ( $qty_left < 1 || $qty_right < 1 ) {
				self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'Please enter a valid quantity for each eye.', 'wc-optic' ) );
				return self::$parse_cache[ $product_id ];
			}
			$line_qty = $qty_left + $qty_right;
		} else {
			$line_qty = $qty;
		}

		$payload = array(
			'division'     => $division,
			'division_lbl' => isset( $divisions[ $division ] ) ? $divisions[ $division ]['label'] : $division,
			'same_power'   => $same,
			'qty_mode'     => $qty_mode,
			'qty_single'   => $qty,
			'qty_left'     => $qty_left,
			'qty_right'    => $qty_right,
			'line_qty'     => $line_qty,
			'left'         => $left,
			'right'        => $right,
			'catalog'      => array(),
			'product_id'   => $product_id,
			'unit_price'   => 0,
			'line_total'   => 0,
		);

		$payload['line_total'] = WC_Optic_Pricing::calculate_payload_total( $payload );
		$payload['unit_price'] = WC_Optic_Pricing::get_payload_effective_unit_price( $payload );

		$stock_validation = self::validate_payload_stock( $product, $payload );
		if ( is_wp_error( $stock_validation ) ) {
			self::$parse_cache[ $product_id ] = $stock_validation;
			return self::$parse_cache[ $product_id ];
		}

		self::$parse_cache[ $product_id ] = $payload;

		return self::$parse_cache[ $product_id ];
	}

	/**
	 * Parse one selected internal child for one eye from POST.
	 *
	 * @param WC_Product $product          Product.
	 * @param string     $eye              left|right.
	 * @param string     $division         Product division.
	 * @param string     $default_child_id Default child id when the product has only one sellable option.
	 * @return array|WP_Error
	 */
	protected static function parse_eye_child( WC_Product $product, $eye, $division, $default_child_id = '' ) {
		$key      = 'wc_optic_' . $eye . '_child';
		$child_id = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
		if ( '' === $child_id && '' !== $default_child_id ) {
			$child_id = $default_child_id;
		}
		if ( '' === $child_id ) {
			return new WP_Error( 'wc_optic', __( 'Please select a valid internal product before adding to cart.', 'wc-optic' ) );
		}

		$config = WC_Optic_SKU::find_child_config( $product, $child_id, true );
		if ( ! $config ) {
			return new WP_Error( 'wc_optic', __( 'Please choose a valid internal product.', 'wc-optic' ) );
		}

		$powers = array();
		foreach ( WC_Optic_Plugin::get_powers_for_division( $division ) as $power ) {
			$id  = isset( $config['powers'][ $power ] ) ? (int) $config['powers'][ $power ] : 0;
			$row = $id ? WC_Optic_Catalog::get_valid_term( $id, $power ) : null;
			if ( ! $row ) {
				return new WP_Error( 'wc_optic', __( 'Selected internal product is incomplete.', 'wc-optic' ) );
			}
			$powers[ $power ] = array(
				'id'    => $id,
				'label' => (string) $row->name,
			);
		}

		return array(
			'child_id'    => (string) $config['id'],
			'label'       => (string) $config['label'],
			'display'     => WC_Optic_SKU::child_display_label( $config, $division ),
			'sku'         => (string) $config['sku'],
			'unit_price'  => WC_Optic_SKU::get_child_unit_price( $config ),
			'stock_qty'   => WC_Optic_SKU::get_child_stock_qty( $config ),
			'powers'      => $powers,
		);
	}

	/**
	 * Merge optic data into cart line.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product id.
	 * @param int   $variation_id Variation.
	 * @param int   $quantity Qty.
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			return $cart_item_data;
		}
		$parsed = self::parse_request( $product_id );
		if ( is_wp_error( $parsed ) ) {
			return $cart_item_data;
		}
		$cart_item_data[ self::CART_KEY ] = $parsed;
		return $cart_item_data;
	}

	/**
	 * Sync optic payload after WooCommerce merges or adds a cart line.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product id.
	 * @param int    $quantity      Added quantity.
	 * @param int    $variation_id  Variation id.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Original cart item data.
	 */
	public static function sync_cart_item_after_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( ! WC()->cart || ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			return;
		}

		$item = WC()->cart->cart_contents[ $cart_item_key ];
		if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product || 'optic_product' !== $item['data']->get_type() ) {
			return;
		}

		$item = self::sync_cart_item_payload_quantities( $item );
		WC()->cart->cart_contents[ $cart_item_key ] = $item;
	}

	/**
	 * Sync optic payload when cart items are restored from session.
	 *
	 * @param array  $session_data Cart item data with product object.
	 * @param array  $values       Raw session values.
	 * @param string $cart_item_key Cart item key.
	 * @return array
	 */
	public static function sync_cart_item_from_session( $session_data, $values, $cart_item_key ) {
		if ( empty( $session_data['data'] ) || ! $session_data['data'] instanceof WC_Product || 'optic_product' !== $session_data['data']->get_type() ) {
			return $session_data;
		}

		return self::sync_cart_item_payload_quantities( $session_data );
	}

	/**
	 * Validate before add to cart.
	 *
	 * @param bool $passed Passed.
	 * @param int  $product_id Product id.
	 * @param int  $quantity Quantity.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			return $passed;
		}
		$parsed = self::parse_request( $product_id );
		if ( is_wp_error( $parsed ) ) {
			wc_add_notice( $parsed->get_error_message(), 'error' );
			return false;
		}
		if ( (int) $parsed['line_qty'] < 1 ) {
			wc_add_notice( __( 'Invalid quantity.', 'wc-optic' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Hide optic meta rows from the default admin order-item meta table.
	 *
	 * @param array $hidden Hidden meta keys.
	 * @return array
	 */
	public static function hide_admin_order_itemmeta( $hidden ) {
		$hidden[] = '_wc_optic_payload';
		$hidden[] = __( 'Internal SKUs', 'wc-optic' );
		$hidden[] = __( 'Eye pricing', 'wc-optic' );
		$hidden[] = __( 'Eye quantities', 'wc-optic' );

		return array_values( array_unique( $hidden ) );
	}

	/**
	 * Add an optic-specific CSS class to admin order rows.
	 *
	 * @param string        $class Existing classes.
	 * @param WC_Order_Item $item  Order item.
	 * @param WC_Order      $order Order.
	 * @return string
	 */
	public static function add_admin_order_item_class( $class, $item, $order ) {
		if ( self::get_order_item_optic_payload( $item ) ) {
			$class = trim( $class . ' wc-optic-order-item' );
		}

		return $class;
	}

	/**
	 * Render a styled optic summary block on the admin order page.
	 *
	 * @param int              $item_id Item id.
	 * @param WC_Order_Item    $item    Order item.
	 * @param WC_Product|false $product Product.
	 */
	public static function render_admin_order_item_summary( $item_id, $item, $product ) {
		$payload = self::get_order_item_optic_payload( $item );
		if ( ! $payload ) {
			return;
		}

		$order    = $item instanceof WC_Order_Item_Product ? $item->get_order() : null;
		$currency = $order instanceof WC_Order ? $order->get_currency() : '';
		$rows     = array(
			__( 'Internal SKUs', 'wc-optic' )  => self::format_internal_skus_plain( $payload ),
			__( 'Eye pricing', 'wc-optic' )    => self::format_eye_pricing_plain( $payload, $currency ),
			__( 'Eye quantities', 'wc-optic' ) => self::format_eye_quantities_plain( $payload ),
		);

		echo '<div class="wc-optic-order-summary">';
		foreach ( $rows as $label => $value ) {
			echo '<div class="wc-optic-order-summary__row">';
			echo '<span class="wc-optic-order-summary__label">' . esc_html( $label ) . '</span>';
			echo '<span class="wc-optic-order-summary__value">' . esc_html( $value ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Enqueue admin order styles/scripts for optic lines.
	 */
	public static function enqueue_admin_order_assets() {
		if ( ! self::is_admin_order_screen() ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_add_inline_style(
			'woocommerce_admin_styles',
			'.woocommerce_order_items.wc-optic-order-only th.item_cost,
			.woocommerce_order_items.wc-optic-order-only td.item_cost,
			.woocommerce_order_items.wc-optic-order-only th.quantity,
			.woocommerce_order_items.wc-optic-order-only td.quantity { display: none; }
			.wc-optic-order-summary { margin-top: 10px; padding: 10px 12px; border: 1px solid #dcdcde; border-radius: 6px; background: #f6f7f7; }
			.wc-optic-order-summary__row { display: flex; gap: 10px; align-items: flex-start; }
			.wc-optic-order-summary__row + .wc-optic-order-summary__row { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
			.wc-optic-order-summary__label { min-width: 110px; color: #50575e; font-weight: 600; }
			.wc-optic-order-summary__value { color: #1d2327; font-weight: 500; }'
		);

		wp_add_inline_script(
			'jquery',
			'jQuery(function($){
				$("table.woocommerce_order_items").each(function(){
					var $table = $(this);
					var $rows = $table.find("tbody#order_line_items tr.item");
					if ($rows.length && $rows.length === $rows.filter(".wc-optic-order-item").length) {
						$table.addClass("wc-optic-order-only");
					}
					$table.find(".wc-optic-order-item table.display_meta").each(function(){
						if (!$(this).find("tr").length) {
							$(this).hide();
						}
					});
				});
			});',
			'after'
		);
	}

	/**
	 * Cart / checkout line item meta display.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function get_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $item_data;
		}
		$o = $cart_item[ self::CART_KEY ];

		$item_data[] = array(
			'name'  => __( 'Internal SKUs', 'wc-optic' ),
			'value' => self::format_internal_skus_plain( $o ),
		);
		$item_data[] = array(
			'name'  => __( 'Eye quantities', 'wc-optic' ),
			'value' => self::format_eye_quantities_plain( $o ),
		);

		return $item_data;
	}

	/**
	 * Replace misleading averaged unit price in cart for optic items.
	 *
	 * @param string $price_html Cart item price HTML.
	 * @param array  $cart_item  Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public static function cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $price_html;
		}

		$o           = $cart_item[ self::CART_KEY ];
		$qty_mode    = self::get_effective_qty_mode( $o );
		$left_price  = isset( $o['left']['unit_price'] ) ? (float) wc_format_decimal( $o['left']['unit_price'] ) : 0.0;
		$right_price = isset( $o['right']['unit_price'] ) ? (float) wc_format_decimal( $o['right']['unit_price'] ) : 0.0;

		if ( 'dual' !== $qty_mode ) {
			return $left_price > 0 ? wc_price( $left_price ) : $price_html;
		}

		$out = '<div class="wc-optic-cart-eye-prices">';
		$out .= '<span class="wc-optic-cart-eye-price"><span class="wc-optic-ltr" dir="ltr">' . esc_html__( 'OD', 'wc-optic' ) . '</span>: ' . wp_kses_post( wc_price( $right_price ) ) . '</span>';
		$out .= '<span class="wc-optic-cart-eye-price"><span class="wc-optic-ltr" dir="ltr">' . esc_html__( 'OS', 'wc-optic' ) . '</span>: ' . wp_kses_post( wc_price( $left_price ) ) . '</span>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Persist to order item meta.
	 *
	 * @param WC_Order_Item_Product $item Item.
	 * @param string                $cart_item_key Key.
	 * @param array                 $values Values.
	 * @param WC_Order              $order Order.
	 */
	public static function order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values[ self::CART_KEY ] ) || ! is_array( $values[ self::CART_KEY ] ) ) {
			return;
		}
		$o = $values[ self::CART_KEY ];
		$item->add_meta_data( '_wc_optic_payload', wp_json_encode( $o ), true );
		$item->add_meta_data( __( 'Internal SKUs', 'wc-optic' ), self::format_internal_skus_plain( $o ), true );
		$item->add_meta_data( __( 'Eye pricing', 'wc-optic' ), self::format_eye_pricing_plain( $o, $order->get_currency() ), true );
		$item->add_meta_data( __( 'Eye quantities', 'wc-optic' ), self::format_eye_quantities_plain( $o ), true );
	}

	/**
	 * Plain text prescription for order emails.
	 *
	 * @param array $o Data.
	 * @return string
	 */
	protected static function format_prescription_plain( array $o ) {
		$left  = isset( $o['left']['powers'] ) && is_array( $o['left']['powers'] ) ? $o['left']['powers'] : array();
		$right = isset( $o['right']['powers'] ) && is_array( $o['right']['powers'] ) ? $o['right']['powers'] : array();
		$fmt   = function ( $eye_key, $powers ) {
			$bits = array();
			foreach ( $powers as $k => $v ) {
				$label = is_array( $v ) && isset( $v['label'] ) ? $v['label'] : $v;
				$bits[] = strtoupper( $k ) . ': ' . $label;
			}
			$eye_label = 'left' === $eye_key ? __( 'OS (left)', 'wc-optic' ) : __( 'OD (right)', 'wc-optic' );
			return $eye_label . ' — ' . implode( ', ', $bits );
		};
		// Right (OD) first per medical / RTL-friendly ordering.
		return $fmt( 'right', $right ) . ' | ' . $fmt( 'left', $left );
	}

	/**
	 * Plain text child selection summary.
	 *
	 * @param array $o Data.
	 * @return string
	 */
	protected static function format_child_selection_plain( array $o ) {
		$fmt = function ( $eye_key, $data ) {
			$eye_label = 'left' === $eye_key ? __( 'OS (left)', 'wc-optic' ) : __( 'OD (right)', 'wc-optic' );
			$label     = isset( $data['display'] ) ? (string) $data['display'] : '';
			$sku       = isset( $data['sku'] ) ? (string) $data['sku'] : '';
			return trim( $eye_label . ' - ' . $label . ( $sku ? ' [' . $sku . ']' : '' ) );
		};

		$left  = isset( $o['left'] ) && is_array( $o['left'] ) ? $o['left'] : array();
		$right = isset( $o['right'] ) && is_array( $o['right'] ) ? $o['right'] : array();

		return $fmt( 'right', $right ) . ' | ' . $fmt( 'left', $left );
	}

	/**
	 * Plain text internal SKU summary only.
	 *
	 * @param array $o Data.
	 * @return string
	 */
	protected static function format_internal_skus_plain( array $o ) {
		$left_sku  = isset( $o['left']['sku'] ) ? trim( (string) $o['left']['sku'] ) : '';
		$right_sku = isset( $o['right']['sku'] ) ? trim( (string) $o['right']['sku'] ) : '';
		$same      = ! empty( $o['same_power'] ) || ( $left_sku && $left_sku === $right_sku );

		if ( $same ) {
			return $left_sku ? $left_sku : $right_sku;
		}

		return trim( 'OD: ' . $right_sku . ' | OS: ' . $left_sku );
	}

	/**
	 * Plain text eye quantity summary.
	 *
	 * @param array $o Data.
	 * @return string
	 */
	protected static function format_eye_quantities_plain( array $o ) {
		$qty_mode = self::get_effective_qty_mode( $o );
		if ( 'dual' !== $qty_mode ) {
			$qty = isset( $o['qty_single'] ) ? max( 1, (int) $o['qty_single'] ) : ( isset( $o['line_qty'] ) ? max( 1, (int) $o['line_qty'] ) : 1 );
			return 'Same: ' . $qty;
		}

		$qty_right = isset( $o['qty_right'] ) ? max( 1, (int) $o['qty_right'] ) : 1;
		$qty_left  = isset( $o['qty_left'] ) ? max( 1, (int) $o['qty_left'] ) : 1;

		return 'OD: ' . $qty_right . ' | OS: ' . $qty_left;
	}

	/**
	 * Plain text eye pricing summary for order admin.
	 *
	 * @param array  $o        Data.
	 * @param string $currency Order currency.
	 * @return string
	 */
	protected static function format_eye_pricing_plain( array $o, $currency = '' ) {
		$qty_mode    = self::get_effective_qty_mode( $o );
		$left_price  = isset( $o['left']['unit_price'] ) ? (float) wc_format_decimal( $o['left']['unit_price'] ) : 0.0;
		$right_price = isset( $o['right']['unit_price'] ) ? (float) wc_format_decimal( $o['right']['unit_price'] ) : 0.0;

		if ( 'dual' !== $qty_mode ) {
			$qty = isset( $o['qty_single'] ) ? max( 1, (int) $o['qty_single'] ) : ( isset( $o['line_qty'] ) ? max( 1, (int) $o['line_qty'] ) : 1 );
			return 'Same: ' . self::format_plain_price( $left_price, $currency ) . ' x ' . $qty;
		}

		$qty_right = isset( $o['qty_right'] ) ? max( 1, (int) $o['qty_right'] ) : 1;
		$qty_left  = isset( $o['qty_left'] ) ? max( 1, (int) $o['qty_left'] ) : 1;

		return 'OD: ' . self::format_plain_price( $right_price, $currency ) . ' x ' . $qty_right . ' | OS: ' . self::format_plain_price( $left_price, $currency ) . ' x ' . $qty_left;
	}

	/**
	 * Build a plain text price string.
	 *
	 * @param float  $amount   Price amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	protected static function format_plain_price( $amount, $currency = '' ) {
		$args  = $currency ? array( 'currency' => $currency ) : array();
		$price = wp_strip_all_tags( html_entity_decode( wc_price( $amount, $args ) ) );
		return trim( preg_replace( '/\s+/u', ' ', $price ) );
	}

	/**
	 * Read optic payload from an order item.
	 *
	 * @param WC_Order_Item $item Order item.
	 * @return array<string, mixed>|null
	 */
	protected static function get_order_item_optic_payload( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return null;
		}

		$payload_json = $item->get_meta( '_wc_optic_payload', true );
		if ( ! $payload_json ) {
			return null;
		}

		$payload = json_decode( (string) $payload_json, true );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Whether the current screen is an admin order editor.
	 *
	 * @return bool
	 */
	protected static function is_admin_order_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
	}

	/**
	 * Replace quantity input on cart for optic lines.
	 *
	 * @param string $html HTML.
	 * @param string $cart_item_key Key.
	 * @param array  $cart_item Item.
	 * @return string
	 */
	public static function cart_item_quantity( $html, $cart_item_key, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) ) {
			return $html;
		}

		$cart_item = self::sync_cart_item_payload_quantities( $cart_item );
		$o         = $cart_item[ self::CART_KEY ];
		$qty_mode = self::get_effective_qty_mode( $o );
		$ql       = isset( $o['qty_left'] ) ? max( 1, (int) $o['qty_left'] ) : 1;
		$qr       = isset( $o['qty_right'] ) ? max( 1, (int) $o['qty_right'] ) : 1;
		$qs       = isset( $o['qty_single'] ) ? max( 1, (int) $o['qty_single'] ) : 1;
		$total    = 'dual' === $qty_mode ? $ql + $qr : $qs;
		$product  = ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ? $cart_item['data'] : null;
		$left_cfg = $product && ! empty( $o['left']['child_id'] ) ? WC_Optic_SKU::find_child_config( $product, (string) $o['left']['child_id'], false ) : null;
		$right_cfg = $product && ! empty( $o['right']['child_id'] ) ? WC_Optic_SKU::find_child_config( $product, (string) $o['right']['child_id'], false ) : null;
		$single_max = is_array( $left_cfg ) ? self::get_remaining_child_stock( $product, $left_cfg, $cart_item_key ) : null;
		$left_max   = is_array( $left_cfg ) ? self::get_remaining_child_stock( $product, $left_cfg, $cart_item_key ) : null;
		$right_max  = is_array( $right_cfg ) ? self::get_remaining_child_stock( $product, $right_cfg, $cart_item_key ) : null;
		$single_max_attr = null === $single_max ? '' : ' max="' . esc_attr( (string) $single_max ) . '"';
		$left_max_attr   = null === $left_max ? '' : ' max="' . esc_attr( (string) $left_max ) . '"';
		$right_max_attr  = null === $right_max ? '' : ' max="' . esc_attr( (string) $right_max ) . '"';

		ob_start();
		echo '<div class="wc-optic-cart-qty" data-cart-key="' . esc_attr( $cart_item_key ) . '" data-qty-mode="' . esc_attr( $qty_mode ) . '">';
		echo '<input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="' . esc_attr( (string) $total ) . '" class="wc-optic-cart-line-total" />';
		if ( 'dual' === $qty_mode ) {
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'OS', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1"' . $left_max_attr . ' name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][left]" class="wc-optic-cart-q-left input-text qty text" value="' . esc_attr( (string) $ql ) . '" /></span> ';
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'OD', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1"' . $right_max_attr . ' name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][right]" class="wc-optic-cart-q-right input-text qty text" value="' . esc_attr( (string) $qr ) . '" /></span>';
		} else {
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'Both', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1"' . $single_max_attr . ' name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][single]" class="wc-optic-cart-q-single input-text qty text" value="' . esc_attr( (string) $qs ) . '" /></span>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * After cart form processing, sync per-eye quantities from POST into cart item payload.
	 *
	 * @param bool $cart_updated Whether cart was updated.
	 * @return bool
	 */
	public static function sync_cart_optic_quantities( $cart_updated ) {
		if ( empty( $_POST['wc_optic_cart'] ) || ! is_array( $_POST['wc_optic_cart'] ) ) {
			return $cart_updated;
		}
		$optic_post = wp_unslash( $_POST['wc_optic_cart'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( $optic_post as $cart_item_key => $eyes ) {
			$cart_item_key = sanitize_text_field( $cart_item_key );
			$item          = WC()->cart->get_cart_item( $cart_item_key );
			if ( ! $item || empty( $item[ self::CART_KEY ] ) || ! is_array( $item[ self::CART_KEY ] ) ) {
				continue;
			}
			if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				continue;
			}

			if ( 'dual' !== self::get_effective_qty_mode( $item[ self::CART_KEY ] ) ) {
				$s = isset( $eyes['single'] ) ? max( 1, absint( $eyes['single'] ) ) : 1;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_mode']   = 'single';
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['same_power'] = true;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_single'] = $s;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['line_qty']   = $s;
				$new_total = $s;
			} else {
				$l = isset( $eyes['left'] ) ? max( 1, absint( $eyes['left'] ) ) : 1;
				$r = isset( $eyes['right'] ) ? max( 1, absint( $eyes['right'] ) ) : 1;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_mode']   = 'dual';
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['same_power'] = false;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_left']   = $l;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_right']  = $r;
				WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['line_qty']   = $l + $r;
				$new_total = $l + $r;
			}

			if ( (int) WC()->cart->cart_contents[ $cart_item_key ]['quantity'] !== (int) $new_total ) {
				WC()->cart->set_quantity( $cart_item_key, $new_total, false );
			}
			$cart_updated = true;
		}
		return $cart_updated;
	}

	/**
	 * Validate optic stock across the whole cart.
	 */
	public static function validate_cart_stock() {
		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
				continue;
			}

			$product = ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ? $cart_item['data'] : wc_get_product( $cart_item['product_id'] ?? 0 );
			if ( ! $product || 'optic_product' !== $product->get_type() ) {
				continue;
			}

			$validation = self::validate_payload_stock( $product, $cart_item[ self::CART_KEY ], $cart_item['key'] ?? '' );
			if ( is_wp_error( $validation ) ) {
				wc_add_notice( $validation->get_error_message(), 'error' );
			}
		}
	}

	/**
	 * Reduce internal child stock quantities when Woo reduces order stock.
	 *
	 * @param int|WC_Order $order Order object or id.
	 */
	public static function reduce_order_internal_stock( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || 'yes' === $order->get_meta( '_wc_optic_internal_stock_reduced', true ) ) {
			return;
		}

		self::apply_order_internal_stock_delta( $order, -1 );
		$order->update_meta_data( '_wc_optic_internal_stock_reduced', 'yes' );
		$order->save();
	}

	/**
	 * Restore internal child stock quantities when Woo restores order stock.
	 *
	 * @param int|WC_Order $order Order object or id.
	 */
	public static function restore_order_internal_stock( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || 'yes' !== $order->get_meta( '_wc_optic_internal_stock_reduced', true ) ) {
			return;
		}

		self::apply_order_internal_stock_delta( $order, 1 );
		$order->delete_meta_data( '_wc_optic_internal_stock_reduced' );
		$order->save();
	}

	/**
	 * Apply stock deltas from an order payload onto internal child configs.
	 *
	 * @param WC_Order $order     Order.
	 * @param int      $direction -1 to reduce, +1 to restore.
	 */
	protected static function apply_order_internal_stock_delta( WC_Order $order, $direction ) {
		$updates = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$payload_json = $item->get_meta( '_wc_optic_payload', true );
			if ( ! $payload_json ) {
				continue;
			}

			$payload = json_decode( (string) $payload_json, true );
			if ( ! is_array( $payload ) ) {
				continue;
			}

			$product_id = $item->get_product_id();
			if ( $product_id < 1 ) {
				continue;
			}

			foreach ( self::build_child_quantity_map( $payload ) as $child_id => $entry ) {
				$qty = isset( $entry['qty'] ) ? absint( $entry['qty'] ) : 0;
				if ( $qty < 1 ) {
					continue;
				}

				if ( ! isset( $updates[ $product_id ] ) ) {
					$updates[ $product_id ] = array();
				}
				if ( ! isset( $updates[ $product_id ][ $child_id ] ) ) {
					$updates[ $product_id ][ $child_id ] = 0;
				}

				$updates[ $product_id ][ $child_id ] += $qty * (int) $direction;
			}
		}

		foreach ( $updates as $product_id => $child_deltas ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || 'optic_product' !== $product->get_type() ) {
				continue;
			}

			$configs = WC_Optic_SKU::get_child_configs( $product );
			foreach ( $configs as &$config ) {
				$child_id = isset( $config['id'] ) ? (string) $config['id'] : '';
				if ( '' === $child_id || ! isset( $child_deltas[ $child_id ] ) ) {
					continue;
				}

				$current_stock = WC_Optic_SKU::get_child_stock_qty( $config );
				if ( null === $current_stock ) {
					continue;
				}

				$config['stock_qty'] = (string) max( 0, $current_stock + (int) $child_deltas[ $child_id ] );
			}
			unset( $config );

			WC_Optic_SKU::persist_child_data( $product, $configs );
			$product->save();
		}
	}

	/**
	 * Build requested quantities grouped by internal child id.
	 *
	 * @param array $payload Optic payload.
	 * @return array<string, array<string, mixed>>
	 */
	protected static function build_child_quantity_map( array $payload ) {
		$map      = array();
		$qty_mode = self::get_effective_qty_mode( $payload );

		if ( 'dual' === $qty_mode ) {
			self::add_child_quantity_to_map( $map, $payload['left'] ?? array(), isset( $payload['qty_left'] ) ? (int) $payload['qty_left'] : 0 );
			self::add_child_quantity_to_map( $map, $payload['right'] ?? array(), isset( $payload['qty_right'] ) ? (int) $payload['qty_right'] : 0 );
			return $map;
		}

		self::add_child_quantity_to_map( $map, $payload['left'] ?? array(), isset( $payload['qty_single'] ) ? (int) $payload['qty_single'] : 0 );
		return $map;
	}

	/**
	 * Add one eye quantity into the grouped stock request map.
	 *
	 * @param array $map   Mutable quantity map.
	 * @param array $eye   Eye payload.
	 * @param int   $qty   Requested quantity.
	 */
	protected static function add_child_quantity_to_map( array &$map, array $eye, $qty ) {
		$child_id = isset( $eye['child_id'] ) ? (string) $eye['child_id'] : '';
		$qty      = max( 0, (int) $qty );
		if ( '' === $child_id || $qty < 1 ) {
			return;
		}

		if ( ! isset( $map[ $child_id ] ) ) {
			$map[ $child_id ] = array(
				'qty'   => 0,
				'label' => isset( $eye['display'] ) ? (string) $eye['display'] : '',
				'sku'   => isset( $eye['sku'] ) ? (string) $eye['sku'] : '',
			);
		}

		$map[ $child_id ]['qty'] += $qty;
	}

	/**
	 * Validate a parsed optic payload against current child stock.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $payload Parsed payload.
	 * @return true|WP_Error
	 */
	protected static function validate_payload_stock( WC_Product $product, array $payload, $exclude_cart_item_key = '' ) {
		foreach ( self::build_child_quantity_map( $payload ) as $child_id => $entry ) {
			$config = WC_Optic_SKU::find_child_config( $product, $child_id, false );
			if ( ! $config ) {
				return new WP_Error( 'wc_optic', __( 'Selected internal product is no longer available.', 'wc-optic' ) );
			}

			$requested = isset( $entry['qty'] ) ? max( 1, (int) $entry['qty'] ) : 1;
			$remaining = self::get_remaining_child_stock( $product, $config, $exclude_cart_item_key );
			if ( null === $remaining || $requested <= $remaining ) {
				continue;
			}

			$label     = self::get_stock_error_label( $config, $entry );
			return new WP_Error(
				'wc_optic_stock',
				sprintf(
					/* translators: 1: internal product label, 2: remaining stock quantity */
					__( '%1$s only has %2$d unit(s) left in stock.', 'wc-optic' ),
					$label,
					max( 0, (int) $remaining )
				)
			);
		}

		return true;
	}

	/**
	 * Get the quantity of one internal child already reserved in the current cart.
	 *
	 * @param WC_Product $product               Product.
	 * @param string     $child_id              Child id.
	 * @param string     $exclude_cart_item_key Optional cart item key to exclude.
	 * @return int
	 */
	public static function get_reserved_child_quantity( WC_Product $product, $child_id, $exclude_cart_item_key = '' ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$child_id = (string) $child_id;
		if ( '' === $child_id ) {
			return 0;
		}

		$reserved = 0;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( $exclude_cart_item_key && $cart_item_key === $exclude_cart_item_key ) {
				continue;
			}
			if ( empty( $cart_item['product_id'] ) || (int) $cart_item['product_id'] !== (int) $product->get_id() ) {
				continue;
			}
			if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
				continue;
			}

			$cart_item = self::sync_cart_item_payload_quantities( $cart_item );
			foreach ( self::build_child_quantity_map( $cart_item[ self::CART_KEY ] ) as $reserved_child_id => $entry ) {
				if ( $reserved_child_id !== $child_id ) {
					continue;
				}
				$reserved += isset( $entry['qty'] ) ? max( 0, (int) $entry['qty'] ) : 0;
			}
		}

		return $reserved;
	}

	/**
	 * Get the remaining stock for one internal child after current cart reservations.
	 *
	 * @param WC_Product $product               Product.
	 * @param array      $config                Child config.
	 * @param string     $exclude_cart_item_key Optional cart item key to exclude.
	 * @return int|null
	 */
	public static function get_remaining_child_stock( WC_Product $product, array $config, $exclude_cart_item_key = '' ) {
		$stock_qty = WC_Optic_SKU::get_child_stock_qty( $config );
		if ( null === $stock_qty ) {
			return null;
		}

		$child_id = isset( $config['id'] ) ? (string) $config['id'] : '';
		if ( '' === $child_id ) {
			return $stock_qty;
		}

		return max( 0, $stock_qty - self::get_reserved_child_quantity( $product, $child_id, $exclude_cart_item_key ) );
	}

	/**
	 * Get a human-readable label for stock errors.
	 *
	 * @param array $config Child config.
	 * @param array $entry  Requested quantity entry.
	 * @return string
	 */
	protected static function get_stock_error_label( array $config, array $entry ) {
		$label = '';
		if ( ! empty( $entry['label'] ) ) {
			$label = (string) $entry['label'];
		} elseif ( ! empty( $config['label'] ) ) {
			$label = (string) $config['label'];
		}

		$sku = ! empty( $entry['sku'] ) ? (string) $entry['sku'] : (string) ( $config['sku'] ?? '' );
		if ( $sku ) {
			return trim( $label ? $label . ' [' . $sku . ']' : $sku );
		}

		return $label ? $label : __( 'Selected internal product', 'wc-optic' );
	}

	/**
	 * Derive the effective quantity mode from the payload.
	 *
	 * This keeps cart behavior correct even for legacy/stale payloads already in session.
	 *
	 * @param array $payload Optic payload.
	 * @return string
	 */
	protected static function get_effective_qty_mode( array $payload ) {
		$left_child  = isset( $payload['left']['child_id'] ) ? (string) $payload['left']['child_id'] : '';
		$right_child = isset( $payload['right']['child_id'] ) ? (string) $payload['right']['child_id'] : '';
		if ( $left_child && $right_child && $left_child !== $right_child ) {
			return 'dual';
		}

		return ( isset( $payload['qty_mode'] ) && 'dual' === $payload['qty_mode'] ) ? 'dual' : 'single';
	}

	/**
	 * Keep optic payload quantities aligned with the actual WooCommerce cart line quantity.
	 *
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	protected static function sync_cart_item_payload_quantities( array $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $cart_item;
		}

		$payload  = $cart_item[ self::CART_KEY ];
		$line_qty = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : ( isset( $payload['line_qty'] ) ? max( 1, (int) $payload['line_qty'] ) : 1 );
		$qty_mode = self::get_effective_qty_mode( $payload );

		if ( 'dual' === $qty_mode ) {
			$payload['line_qty'] = ( isset( $payload['qty_left'] ) ? max( 1, (int) $payload['qty_left'] ) : 1 ) + ( isset( $payload['qty_right'] ) ? max( 1, (int) $payload['qty_right'] ) : 1 );
		} else {
			$payload['same_power'] = true;
			$payload['qty_mode']   = 'single';
			$payload['qty_single'] = $line_qty;
			$payload['line_qty']   = $line_qty;
			if ( empty( $payload['right'] ) && ! empty( $payload['left'] ) ) {
				$payload['right'] = $payload['left'];
			}
		}

		$payload['line_total'] = WC_Optic_Pricing::calculate_payload_total( $payload );
		$payload['unit_price'] = WC_Optic_Pricing::get_payload_effective_unit_price( $payload );

		$cart_item[ self::CART_KEY ] = $payload;
		return $cart_item;
	}

	/**
	 * Enqueue cart JS.
	 */
	public static function enqueue_cart_scripts() {
		if ( ! is_cart() ) {
			return;
		}
		wp_enqueue_style(
			'wc-optic-frontend',
			WC_OPTIC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WC_OPTIC_VERSION
		);
		wp_enqueue_script(
			'wc-optic-cart',
			WC_OPTIC_PLUGIN_URL . 'assets/js/cart.js',
			array( 'jquery' ),
			WC_OPTIC_VERSION,
			true
		);
	}
}
