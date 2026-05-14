<?php
/**
 * Frontend template loading and assets.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Frontend
 */
class WC_Optic_Frontend {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'woocommerce_locate_template', array( __CLASS__, 'locate_template' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Load add-to-cart template from plugin.
	 *
	 * @param string $template Path.
	 * @param string $template_name Name.
	 * @param string $template_path Path.
	 * @return string
	 */
	public static function locate_template( $template, $template_name, $template_path ) {
		if ( 'single-product/add-to-cart/optic_product.php' === $template_name ) {
			$plugin = WC_OPTIC_PLUGIN_DIR . 'templates/' . $template_name;
			if ( is_readable( $plugin ) ) {
				return $plugin;
			}
		}
		return $template;
	}

	/**
	 * Scripts and styles for single optic product.
	 */
	public static function enqueue() {
		if ( ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			return;
		}

		wp_enqueue_style(
			'wc-optic-frontend',
			WC_OPTIC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WC_OPTIC_VERSION
		);
		wp_enqueue_script(
			'wc-optic-frontend',
			WC_OPTIC_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wc-add-to-cart' ),
			WC_OPTIC_VERSION,
			true
		);
		wp_localize_script(
			'wc-optic-frontend',
			'wcOpticFront',
			array(
				'i18n' => array(
					'rightEye' => __( 'Right eye (OD)', 'wc-optic' ),
					'leftEye'  => __( 'Left eye (OS)', 'wc-optic' ),
				),
			)
		);
	}
}
