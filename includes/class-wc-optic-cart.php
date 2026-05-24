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

		if ( '' === $product->get_price() ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'This product has no unit price set.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		$division = $product->get_meta( '_optic_division', true );
		if ( ! $division ) {
			self::$parse_cache[ $product_id ] = new WP_Error( 'wc_optic', __( 'This product is not configured for prescriptions.', 'wc-optic' ) );
			return self::$parse_cache[ $product_id ];
		}

		$divisions  = WC_Optic_Plugin::get_divisions();
		$powers_def = WC_Optic_Plugin::get_powers_for_division( $division );
		$same       = empty( $_POST['wc_optic_same_power'] ) ? false : true;
		$qty_mode   = empty( $_POST['wc_optic_qty_per_eye'] ) ? 'single' : 'dual';

		$left  = self::parse_eye_powers( 'left', $powers_def );
		$right = $same ? $left : self::parse_eye_powers( 'right', $powers_def );

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

		$labels = self::snapshot_catalog_labels( $product );

		self::$parse_cache[ $product_id ] = array(
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
			'catalog'      => $labels,
			'product_id'   => $product_id,
			'unit_price'   => WC_Optic_Pricing::get_unit_price( $product ),
		);

		return self::$parse_cache[ $product_id ];
	}

	/**
	 * Parse powers for one eye from POST.
	 *
	 * @param string $eye left|right.
	 * @param array  $powers_def Power keys.
	 * @return array|WP_Error
	 */
	protected static function parse_eye_powers( $eye, array $powers_def ) {
		$out = array();
		foreach ( $powers_def as $p ) {
			$key = 'wc_optic_' . $eye . '_' . $p;
			$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' === trim( (string) $val ) ) {
				return new WP_Error( 'wc_optic', __( 'Please complete all prescription fields before adding to cart.', 'wc-optic' ) );
			}
			$out[ $p ] = $val;
		}
		return $out;
	}

	/**
	 * Catalog labels snapshot for order display.
	 *
	 * @param WC_Product $product Product.
	 * @return array
	 */
	protected static function snapshot_catalog_labels( WC_Product $product ) {
		$labels = array();
		foreach ( WC_Optic_SKU::META_KEYS as $type => $meta_key ) {
			$id = (int) $product->get_meta( $meta_key, true );
			if ( ! $id ) {
				$labels[ $type ] = '';
				continue;
			}
			$row = WC_Optic_Catalog::get_term( $id );
			$labels[ $type ] = $row ? $row->name : '';
		}
		return $labels;
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
			'name'  => __( 'Optical division', 'wc-optic' ),
			'value' => isset( $o['division_lbl'] ) ? wc_clean( $o['division_lbl'] ) : '',
		);

		$item_data[] = array(
			'name'  => __( 'Prescription (OD / OS)', 'wc-optic' ),
			'value' => self::format_prescription_plain( $o ),
		);

		if ( isset( $o['qty_mode'] ) && 'dual' === $o['qty_mode'] ) {
			$item_data[] = array(
				'name'  => __( 'Quantities', 'wc-optic' ),
				'value' => sprintf(
					/* translators: 1: left qty, 2: right qty */
					esc_html__( 'Left: %1$d — Right: %2$d', 'wc-optic' ),
					isset( $o['qty_left'] ) ? (int) $o['qty_left'] : 0,
					isset( $o['qty_right'] ) ? (int) $o['qty_right'] : 0
				),
			);
		}

		return $item_data;
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
		$item->add_meta_data( __( 'Optical division', 'wc-optic' ), isset( $o['division_lbl'] ) ? $o['division_lbl'] : '', true );
		$item->add_meta_data( __( 'Prescription', 'wc-optic' ), wp_strip_all_tags( self::format_prescription_plain( $o ) ), true );
		if ( isset( $o['qty_mode'] ) && 'dual' === $o['qty_mode'] ) {
			$item->add_meta_data(
				__( 'Eye quantities', 'wc-optic' ),
				sprintf(
					'%d / %d',
					isset( $o['qty_left'] ) ? (int) $o['qty_left'] : 0,
					isset( $o['qty_right'] ) ? (int) $o['qty_right'] : 0
				),
				true
			);
		}
	}

	/**
	 * Plain text prescription for order emails.
	 *
	 * @param array $o Data.
	 * @return string
	 */
	protected static function format_prescription_plain( array $o ) {
		$left  = isset( $o['left'] ) && is_array( $o['left'] ) ? $o['left'] : array();
		$right = isset( $o['right'] ) && is_array( $o['right'] ) ? $o['right'] : array();
		$fmt   = function ( $eye_key, $powers ) {
			$bits = array();
			foreach ( $powers as $k => $v ) {
				$bits[] = strtoupper( $k ) . ': ' . $v;
			}
			$eye_label = 'left' === $eye_key ? __( 'OS (left)', 'wc-optic' ) : __( 'OD (right)', 'wc-optic' );
			return $eye_label . ' — ' . implode( ', ', $bits );
		};
		// Right (OD) first per medical / RTL-friendly ordering.
		return $fmt( 'right', $right ) . ' | ' . $fmt( 'left', $left );
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
		if ( isset( $o['qty_mode'] ) && 'dual' === $o['qty_mode'] ) {
			$ql = isset( $o['qty_left'] ) ? (int) $o['qty_left'] : 1;
			$qr = isset( $o['qty_right'] ) ? (int) $o['qty_right'] : 1;
			$total = max( 1, $ql + $qr );
			ob_start();
			echo '<div class="wc-optic-cart-qty" data-cart-key="' . esc_attr( $cart_item_key ) . '">';
			echo '<input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="' . esc_attr( (string) $total ) . '" class="wc-optic-cart-line-total" />';
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'L', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1" name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][left]" class="wc-optic-cart-q-left input-text qty text" value="' . esc_attr( (string) $ql ) . '" /></span> ';
			echo '<span class="wc-optic-ltr" dir="ltr"><label>' . esc_html__( 'R', 'wc-optic' ) . '</label> ';
			echo '<input type="number" min="1" name="wc_optic_cart[' . esc_attr( $cart_item_key ) . '][right]" class="wc-optic-cart-q-right input-text qty text" value="' . esc_attr( (string) $qr ) . '" /></span>';
			echo '</div>';
			return ob_get_clean();
		}
		return $html;
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
			if ( empty( $item[ self::CART_KEY ]['qty_mode'] ) || 'dual' !== $item[ self::CART_KEY ]['qty_mode'] ) {
				continue;
			}
			$l = isset( $eyes['left'] ) ? max( 1, absint( $eyes['left'] ) ) : 1;
			$r = isset( $eyes['right'] ) ? max( 1, absint( $eyes['right'] ) ) : 1;
			if ( ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				continue;
			}
			WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_left']   = $l;
			WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['qty_right']  = $r;
			WC()->cart->cart_contents[ $cart_item_key ][ self::CART_KEY ]['line_qty']  = $l + $r;
			$new_total = $l + $r;
			if ( (int) WC()->cart->cart_contents[ $cart_item_key ]['quantity'] !== (int) $new_total ) {
				WC()->cart->set_quantity( $cart_item_key, $new_total, false );
			}
			$cart_updated = true;
		}
		return $cart_updated;
	}

	/**
	 * Enqueue cart JS.
	 */
	public static function enqueue_cart_scripts() {
		if ( ! is_cart() ) {
			return;
		}
		wp_enqueue_script(
			'wc-optic-cart',
			WC_OPTIC_PLUGIN_URL . 'assets/js/cart.js',
			array( 'jquery' ),
			WC_OPTIC_VERSION,
			true
		);
	}
}
