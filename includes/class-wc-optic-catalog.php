<?php
/**
 * CRUD for optical catalog terms (global values).
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Catalog
 */
class WC_Optic_Catalog {

	const TYPES = array(
		'section',
		'company',
		'brand',
		'timing',
		'color',
		'sph',
		'cyl',
		'axis',
		'add',
		'pack',
		'transparency',
	);

	/**
	 * Prescription power catalog types (replaces legacy single "rx" list).
	 *
	 * @return string[]
	 */
	public static function get_power_types() {
		return array( 'sph', 'cyl', 'axis', 'add' );
	}

	/**
	 * Human-readable label for a catalog type tab.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$labels = self::get_type_labels();
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * All catalog type labels keyed by slug.
	 *
	 * @return array<string, string>
	 */
	public static function get_type_labels() {
		return array(
			'section'      => __( 'Sections', 'wc-optic' ),
			'company'      => __( 'Companies', 'wc-optic' ),
			'brand'        => __( 'Brands', 'wc-optic' ),
			'timing'       => __( 'Timings', 'wc-optic' ),
			'color'        => __( 'Colors', 'wc-optic' ),
			'sph'          => __( 'SPH', 'wc-optic' ),
			'cyl'          => __( 'CYL', 'wc-optic' ),
			'axis'         => __( 'AXIS', 'wc-optic' ),
			'add'          => __( 'ADD', 'wc-optic' ),
			'pack'         => __( 'Packs', 'wc-optic' ),
			'transparency' => __( 'Transparency', 'wc-optic' ),
		);
	}

	/**
	 * List terms by type.
	 *
	 * @param string $term_type Type key.
	 * @return array<int, object>
	 */
	public static function get_terms( $term_type ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE term_type = %s ORDER BY sort_order ASC, name ASC", $term_type ) );
	}

	/**
	 * Get single row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get_term( $id ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Find by type and slug.
	 *
	 * @param string $term_type Type.
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function get_by_slug( $term_type, $slug ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE term_type = %s AND slug = %s", $term_type, self::sanitize_slug( $slug ) ) );
	}

	/**
	 * Insert term.
	 *
	 * @param string $term_type Type.
	 * @param string $name Display name.
	 * @param string $slug Slug.
	 * @param string $sku_fragment SKU fragment.
	 * @param int    $sort_order Order.
	 * @return int|false Insert id or false on duplicate/error.
	 */
	public static function insert( $term_type, $name, $slug, $sku_fragment = '', $sort_order = 0 ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		$slug  = self::sanitize_slug( $slug ? $slug : $name );
		$sku_fragment = self::sanitize_sku_fragment( $sku_fragment );
		$res   = $wpdb->insert(
			$table,
			array(
				'term_type'    => $term_type,
				'slug'         => $slug,
				'name'         => $name,
				'sku_fragment' => $sku_fragment,
				'sort_order'   => (int) $sort_order,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( ! $res ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update term.
	 *
	 * @param int   $id ID.
	 * @param array $data Fields.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		return (bool) $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	/**
	 * Delete term.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = WC_Optic_Database::table_catalog();
		return (bool) $wpdb->delete( $table, array( 'id' => (int) $id ) );
	}

	/**
	 * Valid type check.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_valid_type( $type ) {
		return in_array( $type, self::TYPES, true );
	}

	/**
	 * Validate a catalog row id belongs to the expected power/type.
	 *
	 * @param int    $id Catalog row id.
	 * @param string $term_type Expected term_type (e.g. sph, axis).
	 * @return object|null Row or null if invalid.
	 */
	public static function get_valid_term( $id, $term_type ) {
		$id = absint( $id );
		if ( ! $id || ! self::is_valid_type( $term_type ) ) {
			return null;
		}
		$row = self::get_term( $id );
		if ( ! $row || (string) $row->term_type !== (string) $term_type ) {
			return null;
		}
		return $row;
	}

	/**
	 * Display label for a power field (AXIS not AXIS from strtoupper axis).
	 *
	 * @param string $power Power key (sph, cyl, axis, add).
	 * @return string
	 */
	public static function get_power_field_label( $power ) {
		if ( in_array( $power, self::get_power_types(), true ) ) {
			return self::get_type_label( $power );
		}
		return strtoupper( $power );
	}

	/**
	 * SKU fragment as entered (keeps +, -, etc.; used in product SKU).
	 *
	 * @param string $raw Raw fragment.
	 * @return string
	 */
	public static function sanitize_sku_fragment( $raw ) {
		return trim( wp_unslash( (string) $raw ) );
	}

	/**
	 * Slug for catalog rows: allows + and - in labels. Unlike sanitize_title(), does not strip these.
	 *
	 * @param string $raw Slug or name to derive from.
	 * @return string
	 */
	public static function sanitize_slug( $raw ) {
		$s = trim( wp_unslash( (string) $raw ) );
		$s = preg_replace( '/\s+/u', '-', $s );
		// Letters (incl. Arabic etc.), digits, underscore, plus, hyphen.
		$s = preg_replace( '/[^\p{L}\p{N}_+\-]/u', '', $s );
		$s = preg_replace( '/-{2,}/u', '-', $s );
		$s = trim( $s, '-_' );
		if ( '' === $s ) {
			return '';
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $s, 'UTF-8' );
		}
		return strtolower( $s );
	}
}
