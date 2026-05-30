<?php
/**
 * Dynamic SKU generation for optic child configurations.
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

	const CHILD_META_KEY = '_optic_child_configs';
	const SELECTOR_META_KEY = '_optic_selector_ui';
	const GLOBAL_SELECTOR_OPTION = 'wc_optic_selector_ui';
	const MAX_LEGACY_SYNTHETIC_CHILDREN = 200;

	/**
	 * Product-level derived catalog index meta keys.
	 *
	 * @var array<string, string>
	 */
	const INDEX_META_KEYS = array(
		'section'      => '_optic_idx_section',
		'company'      => '_optic_idx_company',
		'brand'        => '_optic_idx_brand',
		'timing'       => '_optic_idx_timing',
		'color'        => '_optic_idx_color',
		'sph'          => '_optic_idx_sph',
		'cyl'          => '_optic_idx_cyl',
		'axis'         => '_optic_idx_axis',
		'add'          => '_optic_idx_add',
		'pack'         => '_optic_idx_pack',
		'transparency' => '_optic_idx_transparency',
	);

	/**
	 * Normalize one or more catalog ids into a unique int array.
	 *
	 * @param mixed $raw Scalar id, serialized meta array, or posted array.
	 * @return int[]
	 */
	public static function normalize_catalog_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$values = $raw;
		} elseif ( null === $raw || '' === trim( (string) $raw ) ) {
			$values = array();
		} else {
			$values = array( $raw );
		}

		$ids = array();
		foreach ( $values as $value ) {
			$id = absint( $value );
			if ( $id ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Normalize a single catalog id.
	 *
	 * @param mixed $raw Raw value.
	 * @return int
	 */
	public static function normalize_catalog_id( $raw ) {
		$ids = self::normalize_catalog_ids( $raw );
		return empty( $ids ) ? 0 : (int) reset( $ids );
	}

	/**
	 * Child selector UI options.
	 *
	 * @return array<string, string>
	 */
	public static function get_selector_ui_options() {
		return array(
			'radio'    => __( 'Radio buttons', 'wc-optic' ),
			'dropdown' => __( 'Dropdown', 'wc-optic' ),
		);
	}

	/**
	 * Get saved selector UI.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function get_selector_ui( ?WC_Product $product = null ) {
		$value = (string) get_option( self::GLOBAL_SELECTOR_OPTION, 'dropdown' );
		if ( ! isset( self::get_selector_ui_options()[ $value ] ) ) {
			return 'dropdown';
		}
		return $value;
	}

	/**
	 * Persist global selector UI option.
	 *
	 * @param string $value Selector mode.
	 * @return string
	 */
	public static function set_selector_ui( $value ) {
		$value = sanitize_key( (string) $value );
		if ( ! isset( self::get_selector_ui_options()[ $value ] ) ) {
			$value = 'dropdown';
		}

		update_option( self::GLOBAL_SELECTOR_OPTION, $value, false );
		return $value;
	}

	/**
	 * Get child configurations for a product.
	 *
	 * New child meta is authoritative. Legacy flat meta is converted on read.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_child_configs( WC_Product $product ) {
		$division = (string) $product->get_meta( '_optic_division', true );
		if ( '' === $division ) {
			return array();
		}

		$stored = $product->get_meta( self::CHILD_META_KEY, true );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return self::normalize_child_configs( $stored, $division );
		}

		return self::get_legacy_child_configs( $product, $division );
	}

	/**
	 * Get only enabled and complete child configurations.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_enabled_child_configs( WC_Product $product ) {
		$division = (string) $product->get_meta( '_optic_division', true );
		$out      = array();
		foreach ( self::get_child_configs( $product ) as $config ) {
			if ( self::child_is_enabled( $config ) && self::child_is_complete( $config, $division ) ) {
				$out[] = $config;
			}
		}

		usort(
			$out,
			function ( $a, $b ) {
				$sort_a = isset( $a['sort'] ) ? (int) $a['sort'] : 0;
				$sort_b = isset( $b['sort'] ) ? (int) $b['sort'] : 0;
				if ( $sort_a === $sort_b ) {
					return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
				}
				return $sort_a <=> $sort_b;
			}
		);

		return array_values( $out );
	}

	/**
	 * Find one child configuration by id.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $child_id Child id.
	 * @param bool       $enabled_only Enabled only.
	 * @return array<string, mixed>|null
	 */
	public static function find_child_config( WC_Product $product, $child_id, $enabled_only = true ) {
		$child_id = (string) $child_id;
		if ( '' === $child_id ) {
			return null;
		}

		$configs = $enabled_only ? self::get_enabled_child_configs( $product ) : self::get_child_configs( $product );
		foreach ( $configs as $config ) {
			if ( $child_id === (string) ( $config['id'] ?? '' ) ) {
				return $config;
			}
		}

		return null;
	}

	/**
	 * Build SKU string for the first enabled child on a product.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function build_for_product( WC_Product $product ) {
		$children = self::get_enabled_child_configs( $product );
		if ( empty( $children ) ) {
			return '';
		}

		return isset( $children[0]['sku'] ) ? (string) $children[0]['sku'] : '';
	}

	/**
	 * Apply a parent/base SKU to the product.
	 *
	 * The parent SKU is kept intentionally neutral. Internal child SKUs are the
	 * operational identifiers and are stored in child config meta + cart/order payload.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function sync_product_sku( WC_Product $product ) {
		if ( ! $product->get_sku( 'edit' ) ) {
			$product->set_sku( '' );
		}
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
	 * Build SKU from one child configuration.
	 *
	 * @param array  $child_config Child config.
	 * @param string $division     Division.
	 * @return string
	 */
	public static function build_for_child_config( array $child_config, $division ) {
		$allowed_powers = $division ? WC_Optic_Plugin::get_powers_for_division( $division ) : array();
		$power_types    = WC_Optic_Catalog::get_power_types();
		$catalog        = isset( $child_config['catalog'] ) && is_array( $child_config['catalog'] ) ? $child_config['catalog'] : array();
		$powers         = isset( $child_config['powers'] ) && is_array( $child_config['powers'] ) ? $child_config['powers'] : array();
		$parts          = array();

		foreach ( self::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, $power_types, true ) && ! in_array( $type, $allowed_powers, true ) ) {
				$parts[] = '';
				continue;
			}

			$id = in_array( $type, $power_types, true ) ? self::normalize_catalog_id( $powers[ $type ] ?? 0 ) : self::normalize_catalog_id( $catalog[ $type ] ?? 0 );
			if ( ! $id ) {
				$parts[] = '';
				continue;
			}

			$row = WC_Optic_Catalog::get_valid_term( $id, $type );
			if ( ! $row ) {
				$parts[] = '';
				continue;
			}

			$parts[] = self::catalog_term_sku_part( $row );
		}

		return implode( '', $parts );
	}

	/**
	 * Normalize one raw child config.
	 *
	 * @param array  $raw      Raw child config.
	 * @param string $division Parent division.
	 * @param int    $index    Visual index.
	 * @return array<string, mixed>
	 */
	public static function normalize_child_config( array $raw, $division, $index = 0 ) {
		$power_types    = WC_Optic_Catalog::get_power_types();
		$allowed_powers = $division ? WC_Optic_Plugin::get_powers_for_division( $division ) : array();
		$catalog        = isset( $raw['catalog'] ) && is_array( $raw['catalog'] ) ? $raw['catalog'] : array();
		$powers         = isset( $raw['powers'] ) && is_array( $raw['powers'] ) ? $raw['powers'] : array();

		$label = isset( $raw['label'] ) ? sanitize_text_field( wp_unslash( $raw['label'] ) ) : '';
		if ( '' === $label ) {
			/* translators: %d: child config position */
			$label = sprintf( __( 'Product %d', 'wc-optic' ), $index + 1 );
		}

		$id = isset( $raw['id'] ) ? sanitize_key( wp_unslash( $raw['id'] ) ) : '';
		if ( '' === $id ) {
			$id = 'child_' . wp_generate_password( 8, false, false );
		}

		$out = array(
			'id'         => $id,
			'label'      => $label,
			'enabled'    => empty( $raw['enabled'] ) ? false : true,
			'sort'       => isset( $raw['sort'] ) ? (int) $raw['sort'] : $index,
			'unit_price' => '',
			'stock_qty'  => '',
			'catalog'    => array(),
			'powers'     => array(),
			'sku'        => '',
		);

		if ( isset( $raw['unit_price'] ) && '' !== trim( (string) $raw['unit_price'] ) ) {
			$out['unit_price'] = (string) wc_format_decimal( wp_unslash( $raw['unit_price'] ) );
		}
		if ( isset( $raw['stock_qty'] ) && '' !== trim( (string) $raw['stock_qty'] ) ) {
			$out['stock_qty'] = (string) absint( wp_unslash( $raw['stock_qty'] ) );
		}

		foreach ( self::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, $power_types, true ) ) {
				$out['powers'][ $type ] = in_array( $type, $allowed_powers, true ) ? self::normalize_catalog_id( $powers[ $type ] ?? 0 ) : 0;
				continue;
			}

			$out['catalog'][ $type ] = self::normalize_catalog_id( $catalog[ $type ] ?? 0 );
		}

		foreach ( $out['catalog'] as $type => $id_value ) {
			if ( $id_value && ! WC_Optic_Catalog::get_valid_term( $id_value, $type ) ) {
				$out['catalog'][ $type ] = 0;
			}
		}

		foreach ( $out['powers'] as $type => $id_value ) {
			if ( $id_value && ! WC_Optic_Catalog::get_valid_term( $id_value, $type ) ) {
				$out['powers'][ $type ] = 0;
			}
		}

		$out['sku'] = self::build_for_child_config( $out, $division );

		return $out;
	}

	/**
	 * Normalize a list of child configs.
	 *
	 * @param array  $raw_configs Raw configs.
	 * @param string $division    Parent division.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_child_configs( array $raw_configs, $division ) {
		$out = array();
		foreach ( array_values( $raw_configs ) as $index => $raw_config ) {
			if ( ! is_array( $raw_config ) ) {
				continue;
			}

			$config = self::normalize_child_config( $raw_config, $division, $index );
			if ( ! self::child_has_any_values( $config ) ) {
				continue;
			}

			if ( isset( $out[ $config['id'] ] ) ) {
				$config['id'] = $config['id'] . '_' . ( $index + 1 );
			}

			$out[ $config['id'] ] = $config;
		}

		return array_values( $out );
	}

	/**
	 * Whether a child has enough data to be considered non-empty.
	 *
	 * @param array $config Child config.
	 * @return bool
	 */
	public static function child_has_any_values( array $config ) {
		if ( ! empty( $config['unit_price'] ) ) {
			return true;
		}

		foreach ( array( 'catalog', 'powers' ) as $key ) {
			$values = isset( $config[ $key ] ) && is_array( $config[ $key ] ) ? $config[ $key ] : array();
			foreach ( $values as $value ) {
				if ( (int) $value > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether a child is complete enough for storefront use.
	 *
	 * @param array  $config   Child config.
	 * @param string $division Parent division.
	 * @return bool
	 */
	public static function child_is_complete( array $config, $division ) {
		if ( empty( $config['unit_price'] ) ) {
			return false;
		}

		foreach ( WC_Optic_Plugin::get_powers_for_division( $division ) as $power ) {
			if ( empty( $config['powers'][ $power ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get child stock quantity, or null when stock is not managed.
	 *
	 * @param array $config Child config.
	 * @return int|null
	 */
	public static function get_child_stock_qty( array $config ) {
		if ( ! isset( $config['stock_qty'] ) || '' === trim( (string) $config['stock_qty'] ) ) {
			return null;
		}

		return max( 0, absint( $config['stock_qty'] ) );
	}

	/**
	 * Whether a child can satisfy the requested quantity.
	 *
	 * @param array $config             Child config.
	 * @param int   $requested_quantity Requested quantity.
	 * @return bool
	 */
	public static function child_is_in_stock( array $config, $requested_quantity = 1 ) {
		$stock = self::get_child_stock_qty( $config );
		if ( null === $stock ) {
			return true;
		}

		return $stock >= max( 1, (int) $requested_quantity );
	}

	/**
	 * Get enabled, complete, and currently purchasable child configs.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_purchasable_child_configs( WC_Product $product ) {
		$out = array();
		foreach ( self::get_enabled_child_configs( $product ) as $config ) {
			if ( self::child_is_in_stock( $config, 1 ) ) {
				$out[] = $config;
			}
		}

		return $out;
	}

	/**
	 * Whether a child is enabled.
	 *
	 * @param array $config Child config.
	 * @return bool
	 */
	public static function child_is_enabled( array $config ) {
		return ! empty( $config['enabled'] );
	}

	/**
	 * Build a user-facing child choice label.
	 *
	 * @param array  $config   Child config.
	 * @param string $division Parent division.
	 * @return string
	 */
	public static function child_display_label( array $config, $division ) {
		$bits = array();
		foreach ( WC_Optic_Plugin::get_powers_for_division( $division ) as $power ) {
			$id  = isset( $config['powers'][ $power ] ) ? (int) $config['powers'][ $power ] : 0;
			$row = $id ? WC_Optic_Catalog::get_valid_term( $id, $power ) : null;
			if ( $row ) {
				$bits[] = WC_Optic_Catalog::get_power_field_label( $power ) . ': ' . WC_Optic_Catalog::get_display_name( $row );
			}
		}

		if ( empty( $bits ) ) {
			return (string) ( $config['label'] ?? '' );
		}

		return implode( ' | ', $bits );
	}

	/**
	 * Get one child's unit price.
	 *
	 * @param array $config Child config.
	 * @return float
	 */
	public static function get_child_unit_price( array $config ) {
		if ( empty( $config['unit_price'] ) ) {
			return 0.0;
		}
		return (float) wc_format_decimal( $config['unit_price'] );
	}

	/**
	 * Get the minimum enabled child price for a product.
	 *
	 * @param WC_Product $product Product.
	 * @return float
	 */
	public static function get_min_child_price( WC_Product $product ) {
		$prices = array();
		$configs = self::get_purchasable_child_configs( $product );
		if ( empty( $configs ) ) {
			$configs = self::get_enabled_child_configs( $product );
		}

		foreach ( $configs as $config ) {
			$price = self::get_child_unit_price( $config );
			if ( $price > 0 ) {
				$prices[] = $price;
			}
		}

		return empty( $prices ) ? 0.0 : (float) min( $prices );
	}

	/**
	 * Persist child configs, selector UI, derived indexes, and a minimal parent price.
	 *
	 * @param WC_Product $product       Product.
	 * @param array      $child_configs Normalized child configs.
	 * @param string     $selector_ui   Selector UI.
	 */
	public static function persist_child_data( WC_Product $product, array $child_configs, $selector_ui = 'dropdown' ) {
		$product->update_meta_data( self::CHILD_META_KEY, array_values( $child_configs ) );
		$product->delete_meta_data( self::SELECTOR_META_KEY );

		$index = self::build_catalog_index_from_children( $child_configs );
		foreach ( self::INDEX_META_KEYS as $type => $meta_key ) {
			$product->update_meta_data( $meta_key, $index[ $type ] ?? array() );
		}

		$min_price = 0.0;
		foreach ( $child_configs as $config ) {
			if ( ! self::child_is_enabled( $config ) ) {
				continue;
			}
			if ( ! self::child_is_complete( $config, (string) $product->get_meta( '_optic_division', true ) ) ) {
				continue;
			}
			$price = self::get_child_unit_price( $config );
			if ( $price <= 0 ) {
				continue;
			}
			$min_price = 0.0 === $min_price ? $price : min( $min_price, $price );
		}

		if ( $min_price > 0 ) {
			$product->set_regular_price( (string) $min_price );
			$product->set_price( (string) $min_price );
		}
	}

	/**
	 * Build derived catalog indexes from children.
	 *
	 * @param array $child_configs Child configs.
	 * @return array<string, array<int, int>>
	 */
	public static function build_catalog_index_from_children( array $child_configs ) {
		$index       = array();
		$power_types = WC_Optic_Catalog::get_power_types();
		foreach ( array_keys( self::INDEX_META_KEYS ) as $type ) {
			$index[ $type ] = array();
		}

		foreach ( $child_configs as $config ) {
			$catalog = isset( $config['catalog'] ) && is_array( $config['catalog'] ) ? $config['catalog'] : array();
			$powers  = isset( $config['powers'] ) && is_array( $config['powers'] ) ? $config['powers'] : array();

			foreach ( $index as $type => $values ) {
				$id = in_array( $type, $power_types, true ) ? self::normalize_catalog_id( $powers[ $type ] ?? 0 ) : self::normalize_catalog_id( $catalog[ $type ] ?? 0 );
				if ( $id ) {
					$index[ $type ][ $id ] = $id;
				}
			}
		}

		foreach ( $index as $type => $values ) {
			$index[ $type ] = array_map( 'strval', array_values( $values ) );
		}

		return $index;
	}

	/**
	 * Get product-level derived catalog ids for one type.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $type    Catalog type.
	 * @return int[]
	 */
	public static function get_product_catalog_ids( WC_Product $product, $type ) {
		if ( isset( self::INDEX_META_KEYS[ $type ] ) ) {
			$ids = self::normalize_catalog_ids( $product->get_meta( self::INDEX_META_KEYS[ $type ], true ) );
			if ( ! empty( $ids ) ) {
				return $ids;
			}
		}

		if ( isset( self::META_KEYS[ $type ] ) ) {
			$legacy = self::normalize_catalog_ids( $product->get_meta( self::META_KEYS[ $type ], true ) );
			if ( ! empty( $legacy ) ) {
				return $legacy;
			}
		}

		$index = self::build_catalog_index_from_children( self::get_child_configs( $product ) );
		return isset( $index[ $type ] ) ? $index[ $type ] : array();
	}

	/**
	 * Get the first saved catalog id for a product/type pair.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $type    Catalog type.
	 * @return int
	 */
	public static function get_primary_product_catalog_id( WC_Product $product, $type ) {
		$ids = self::get_product_catalog_ids( $product, $type );
		return empty( $ids ) ? 0 : (int) reset( $ids );
	}

	/**
	 * Build SKU preview from a raw child config payload.
	 *
	 * @param array  $child_config Raw child config.
	 * @param string $division     Optical division slug.
	 * @return string
	 */
	public static function build_from_catalog_ids( array $child_config, $division = '' ) {
		$config = self::normalize_child_config( $child_config, $division, 0 );
		return self::build_for_child_config( $config, $division );
	}

	/**
	 * Convert legacy flat product meta into synthetic child configs.
	 *
	 * @param WC_Product $product  Product.
	 * @param string     $division Division.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function get_legacy_child_configs( WC_Product $product, $division ) {
		$allowed_powers = WC_Optic_Plugin::get_powers_for_division( $division );
		if ( empty( $allowed_powers ) ) {
			return array();
		}

		$catalog = array();
		foreach ( self::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, WC_Optic_Catalog::get_power_types(), true ) ) {
				continue;
			}
			$catalog[ $type ] = self::normalize_catalog_id( $product->get_meta( $meta_key, true ) );
		}

		$power_values = array();
		foreach ( $allowed_powers as $power ) {
			$ids = self::normalize_catalog_ids( $product->get_meta( self::META_KEYS[ $power ], true ) );
			if ( empty( $ids ) ) {
				return array();
			}
			$power_values[ $power ] = $ids;
		}

		$combinations = self::expand_power_combinations( $power_values );
		if ( empty( $combinations ) ) {
			return array();
		}

		$price   = (string) wc_format_decimal( $product->get_regular_price( 'edit' ) ? $product->get_regular_price( 'edit' ) : $product->get_price( 'edit' ) );
		$configs = array();
		foreach ( $combinations as $index => $powers ) {
			$configs[] = self::normalize_child_config(
				array(
					'id'         => 'legacy_' . ( $index + 1 ),
					'enabled'    => true,
					'sort'       => $index,
					'label'      => sprintf(
						/* translators: %d: synthetic child position */
						__( 'Product %d', 'wc-optic' ),
						$index + 1
					),
					'unit_price' => $price,
					'catalog'    => $catalog,
					'powers'     => $powers,
				),
				$division,
				$index
			);
		}

		return $configs;
	}

	/**
	 * Expand selected legacy powers into concrete child combinations.
	 *
	 * @param array<string, array<int, int>> $power_values Power value ids by type.
	 * @return array<int, array<string, int>>
	 */
	protected static function expand_power_combinations( array $power_values ) {
		$combinations = array( array() );

		foreach ( $power_values as $power => $ids ) {
			$next = array();
			foreach ( $combinations as $combination ) {
				foreach ( $ids as $id ) {
					$combination[ $power ] = (int) $id;
					$next[]                = $combination;
					if ( count( $next ) >= self::MAX_LEGACY_SYNTHETIC_CHILDREN ) {
						return $next;
					}
				}
			}
			$combinations = $next;
		}

		return $combinations;
	}
}
