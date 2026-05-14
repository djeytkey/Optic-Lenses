<?php
/**
 * AJAX handlers (admin instant term creation, SKU preview).
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
		if ( ! WC_Optic_Catalog::is_valid_type( $type ) || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wc-optic' ) ), 400 );
		}
		$slug          = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$sku_fragment  = isset( $_POST['sku_fragment'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_fragment'] ) ) : '';
		$existing      = WC_Optic_Catalog::get_by_slug( $type, $slug ? $slug : $name );
		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'Duplicate slug for this type.', 'wc-optic' ) ), 409 );
		}
		$id = WC_Optic_Catalog::insert( $type, $name, $slug, $sku_fragment );
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
			$frag    = isset( $row->sku_fragment ) ? (string) $row->sku_fragment : '';
			$parts[] = $frag !== '' ? $frag : (string) $row->slug;
		}
		wp_send_json_success( array( 'sku' => implode( '', $parts ) ) );
	}
}
