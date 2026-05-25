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
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'cart_item_price' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'cart_item_quantity' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_cart_scripts' ) );
		add_filter( 'woocommerce_update_cart_action_cart_updated', array( __CLASS__, 'sync_cart_optic_quantities' ), 5, 1 );
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

		$divisions  = WC_Optic_Plugin::get_divisions();
		$different  = empty( $_POST['wc_optic_different_power'] ) ? false : true;
		$same       = ! $different;
		$qty_mode   = $same ? 'single' : 'dual';

		$left  = self::parse_eye_child( $product, 'left', $division );
		$right = $same ? $left : self::parse_eye_child( $product, 'right', $division );

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

		self::$parse_cache[ $product_id ] = $payload;

		return self::$parse_cache[ $product_id ];
	}

	/**
	 * Parse one selected internal child for one eye from POST.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $eye     left|right.
	 * @param string     $division Product division.
	 * @return array|WP_Error
	 */
	protected static function parse_eye_child( WC_Product $product, $eye, $division ) {
		$key      = 'wc_optic_' . $eye . '_child';
		$child_id = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
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
		$o = $cart_item[ self::CART_KEY ];
		$qty_mode = self::get_effective_qty_mode( $o );
		$ql       = isset( $o['qty_left'] ) ? max( 1, (int) $o['qty_left'] ) : 1;
		$qr       = isset( $o['qty_right'] ) ? max( 1, (int) $o['qty_right'] ) : 1;
		$qs       = isset( $o['qty_single'] ) ? max( 1, (int) $o['qty_single'] ) : 1;
		$total    = 'dual' === $qty_mode ? $ql + $qr : $qs;

		ob_start();
		echo '<div class="wc-optic-cart-qty" data-cart-key="' . esc_attr( $cart_item_key ) . '" data-qty-mode="' . esc_attr( $qty_mode ) . '">';
		echo '<input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="' . esc_attr( (string) $total ) . '" class="wc-optic-cart-line-total" />';
		if ( 'dual' === $qty_mode ) {
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'OS', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1" name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][left]" class="wc-optic-cart-q-left input-text qty text" value="' . esc_attr( (string) $ql ) . '" /></span> ';
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'OD', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1" name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][right]" class="wc-optic-cart-q-right input-text qty text" value="' . esc_attr( (string) $qr ) . '" /></span>';
		} else {
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'Both', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1" name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][single]" class="wc-optic-cart-q-single input-text qty text" value="' . esc_attr( (string) $qs ) . '" /></span>';
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
