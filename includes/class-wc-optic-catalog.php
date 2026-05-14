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
		'sign',
		'rx',
		'pack',
		'transparency',
	);

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
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE term_type = %s AND slug = %s", $term_type, $slug ) );
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
		$slug  = sanitize_title( $slug ? $slug : $name );
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
}
