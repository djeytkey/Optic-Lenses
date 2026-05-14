<?php
/**
 * Dynamic SKU generation from catalog selections.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_SKU
 */
class WC_Optic_SKU {

	const META_KEYS = array(
		'section'      => '_optic_cat_section',
		'company'      => '_optic_cat_company',
		'brand'        => '_optic_cat_brand',
		'timing'       => '_optic_cat_timing',
		'color'        => '_optic_cat_color',
		'sign'         => '_optic_cat_sign',
		'rx'           => '_optic_cat_rx',
		'pack'         => '_optic_cat_pack',
		'transparency' => '_optic_cat_transparency',
	);

	/**
	 * Build SKU string for a product object.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function build_for_product( WC_Product $product ) {
		$parts = array();
		foreach ( self::META_KEYS as $type => $meta_key ) {
			$id = (int) $product->get_meta( $meta_key, true );
			if ( ! $id ) {
				$parts[] = '';
				continue;
			}
			$row = WC_Optic_Catalog::get_term( $id );
			if ( ! $row ) {
				$parts[] = '';
				continue;
			}
			$frag = isset( $row->sku_fragment ) ? (string) $row->sku_fragment : '';
			$parts[] = $frag !== '' ? $frag : (string) $row->slug;
		}
		return implode( '', $parts );
	}

	/**
	 * Apply built SKU to product if not manually locked (optional future). For V1 always sync from selections.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function sync_product_sku( WC_Product $product ) {
		$sku = self::build_for_product( $product );
		$product->set_sku( $sku );
	}
}
