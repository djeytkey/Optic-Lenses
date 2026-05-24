<?php
/**
 * Optic product pricing: unit price × line quantity.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Pricing
 */
class WC_Optic_Pricing {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'woocommerce_add_to_cart_quantity', array( __CLASS__, 'add_to_cart_quantity' ), 10, 2 );
	}

	/**
	 * Use optic line quantity (single qty or left + right) when adding to cart.
	 *
	 * @param int $quantity   Requested quantity.
	 * @param int $product_id Product id.
	 * @return int
	 */
	public static function add_to_cart_quantity( $quantity, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			return $quantity;
		}

		$parsed = WC_Optic_Cart::parse_request_for_product( $product_id );
		if ( is_wp_error( $parsed ) ) {
			return $quantity;
		}

		return max( 1, (int) $parsed['line_qty'] );
	}

	/**
	 * Unit price for an optic product (active price, incl. sale if set).
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	public static function get_unit_price( WC_Product $product ) {
		$price = $product->get_price();
		if ( '' === $price || null === $price ) {
			return 0.0;
		}
		return (float) wc_format_decimal( $price );
	}

	/**
	 * Line total for unit price × quantity.
	 *
	 * @param float $unit_price Unit price.
	 * @param int   $quantity   Line quantity.
	 * @return float
	 */
	public static function calculate_line_total( $unit_price, $quantity ) {
		return (float) wc_format_decimal( (float) $unit_price * max( 1, (int) $quantity ) );
	}
}
