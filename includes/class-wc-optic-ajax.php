<?php
/**
 * AJAX handlers (admin catalog CRUD, SKU preview).
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Ajax
 */
class WC_Optic_Ajax {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'wp_ajax_wc_optic_create_term', array( __CLASS__, 'create_term' ) );
		add_action( 'wp_ajax_wc_optic_delete_term', array( __CLASS__, 'delete_term' ) );
		add_action( 'wp_ajax_wc_optic_preview_sku', array( __CLASS__, 'preview_sku' ) );
	}

	/**
	 * Create catalog term (admin).
	 */
	public static function create_term() {
		check_ajax_referer( 'wc_optic_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-optic' ) ), 403 );
		}
		$type = isset( $_POST['term_type'] ) ? sanitize_key( wp_unslash( $_POST['term_type'] ) ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$frag = isset( $_POST['sku_fragment'] ) ? WC_Optic_Catalog::sanitize_sku_fragment( wp_unslash( $_POST['sku_fragment'] ) ) : '';
		if ( ! WC_Optic_Catalog::is_valid_type( $type ) || '' === $name || '' === $frag ) {
			wp_send_json_error( array( 'message' => __( 'Name and SKU fragment are required.', 'wc-optic' ) ), 400 );
		}
		$slug_check = WC_Optic_Catalog::sanitize_slug( $name );
		$existing    = WC_Optic_Catalog::get_by_slug( $type, $slug_check );
		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'An entry with the same label already exists in this list.', 'wc-optic' ) ), 409 );
		}
		$id = WC_Optic_Catalog::insert( $type, $name, $slug_check, $frag, 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save.', 'wc-optic' ) ), 500 );
		}
		wp_send_json_success(
			array(
				'id'   => $id,
				'text' => $name,
			)
		);
	}

	/**
	 * Delete catalog term (admin settings).
	 */
	public static function delete_term() {
		check_ajax_referer( 'wc_optic_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-optic' ) ), 403 );
		}
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$type = isset( $_POST['term_type'] ) ? sanitize_key( wp_unslash( $_POST['term_type'] ) ) : '';
		if ( ! $id || ! WC_Optic_Catalog::is_valid_type( $type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wc-optic' ) ), 400 );
		}
		$row = WC_Optic_Catalog::get_term( $id );
		if ( ! $row || (string) $row->term_type !== $type ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'wc-optic' ) ), 404 );
		}

		$affected = WC_Optic_Deletion_Log::find_products_using_term( $type, $id );

		if ( ! WC_Optic_Catalog::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete.', 'wc-optic' ) ), 500 );
		}

		$log_id = WC_Optic_Deletion_Log::record( $row, get_current_user_id(), $affected );
		if ( ! $log_id ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wc-optic: catalog term deleted but deletion log insert failed for catalog id ' . (string) $id );
		}

		wp_send_json_success(
			array(
				'log_id'              => $log_id,
				'affected_products'   => $affected,
				'deleted_term_name'   => (string) $row->name,
			)
		);
	}

	/**
	 * Preview SKU from posted catalog ids (admin product screen).
	 */
	public static function preview_sku() {
		check_ajax_referer( 'wc_optic_admin', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-optic' ) ), 403 );
		}
		$parts = array();
		foreach ( WC_Optic_SKU::META_KEYS as $type => $meta_key ) {
			$key = 'cat_' . $type;
			$id  = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
			if ( ! $id ) {
				$parts[] = '';
				continue;
			}
			$row = WC_Optic_Catalog::get_term( $id );
			if ( ! $row ) {
				$parts[] = '';
				continue;
			}
			$parts[] = WC_Optic_SKU::catalog_term_sku_part( $row );
		}
		wp_send_json_success( array( 'sku' => implode( '', $parts ) ) );
	}
}
