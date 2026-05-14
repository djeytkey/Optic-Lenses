<?php
/**
 * Custom tables and activation.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Database
 */
class WC_Optic_Database {

	const TABLE_CATALOG = 'wc_optic_catalog';

	/**
	 * Create tables on activation.
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . self::TABLE_CATALOG;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			term_type varchar(32) NOT NULL,
			slug varchar(191) NOT NULL,
			name varchar(255) NOT NULL,
			sku_fragment varchar(64) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY type_slug (term_type, slug),
			KEY term_type (term_type),
			KEY sort_order (sort_order)
		) {$charset};";

		dbDelta( $sql );

		self::ensure_product_type_term();
	}

	/**
	 * Ensure product_type taxonomy has optic_product term.
	 */
	public static function ensure_product_type_term() {
		if ( ! taxonomy_exists( 'product_type' ) ) {
			return;
		}
		$slug = 'optic_product';
		if ( term_exists( $slug, 'product_type' ) ) {
			return;
		}
		wp_insert_term(
			__( 'Optic Product', 'wc-optic' ),
			'product_type',
			array(
				'slug' => $slug,
			)
		);
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table_catalog() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_CATALOG;
	}
}
