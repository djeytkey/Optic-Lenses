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
		$division       = $product->get_meta( '_optic_division', true );
		$allowed_powers = $division ? WC_Optic_Plugin::get_powers_for_division( $division ) : array();
		$power_types    = WC_Optic_Catalog::get_power_types();

		$parts = array();
		foreach ( self::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, $power_types, true ) && ! in_array( $type, $allowed_powers, true ) ) {
				$parts[] = '';
				continue;
			}
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

	/**
	 * Build SKU preview from posted catalog ids (admin AJAX).
	 *
	 * @param array  $catalog_ids Map of type => catalog row id.
	 * @param string $division    Optical division slug (optional).
	 * @return string
	 */
	public static function build_from_catalog_ids( array $catalog_ids, $division = '' ) {
		$allowed_powers = $division ? WC_Optic_Plugin::get_powers_for_division( $division ) : array();
		$power_types    = WC_Optic_Catalog::get_power_types();
		$parts          = array();

		foreach ( self::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, $power_types, true ) && ! in_array( $type, $allowed_powers, true ) ) {
				$parts[] = '';
				continue;
			}
			$id = isset( $catalog_ids[ $type ] ) ? absint( $catalog_ids[ $type ] ) : 0;
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
}
