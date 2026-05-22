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
		'sph'          => '_optic_cat_sph',
		'cyl'          => '_optic_cat_cyl',
		'axis'         => '_optic_cat_axis',
		'add'          => '_optic_cat_add',
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
			$parts[] = self::catalog_term_sku_part( $row );
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

	/**
	 * Text segment used in dynamic SKU for one catalog row (SKU fragment field).
	 *
	 * @param object $row Catalog DB row.
	 * @return string
	 */
	public static function catalog_term_sku_part( $row ) {
		if ( ! $row ) {
			return '';
		}
		$frag = isset( $row->sku_fragment ) ? trim( (string) $row->sku_fragment ) : '';
		if ( '' !== $frag ) {
			return $frag;
		}
		$name = isset( $row->name ) ? trim( (string) $row->name ) : '';
		if ( '' !== $name ) {
			return $name;
		}
		return isset( $row->slug ) ? (string) $row->slug : '';
	}
}
