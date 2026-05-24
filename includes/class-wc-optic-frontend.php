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
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'selectWoo' );

		wp_enqueue_script(
			'wc-optic-frontend',
			WC_OPTIC_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'selectWoo', 'wc-add-to-cart' ),
			WC_OPTIC_VERSION,
			true
		);
		$unit_price = WC_Optic_Pricing::get_unit_price( $product );

		wp_localize_script(
			'wc-optic-frontend',
			'wcOpticFront',
			array(
				'unitPrice'      => $unit_price,
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'decimalSep'     => wc_get_price_decimal_separator(),
				'thousandSep'    => wc_get_price_thousand_separator(),
				'decimals'       => wc_get_price_decimals(),
				'priceFormat'    => get_woocommerce_price_format(),
				'i18n'           => array(
					'rightEye'       => __( 'Right eye (OD)', 'wc-optic' ),
					'leftEye'        => __( 'Left eye (OS)', 'wc-optic' ),
					'select'         => __( '— Select —', 'wc-optic' ),
					'unitPrice'      => __( 'Unit price', 'wc-optic' ),
					'estimatedTotal' => __( 'Estimated total', 'wc-optic' ),
				),
			)
		);
	}

	/**
	 * Render a searchable power select from the global catalog.
	 *
	 * @param string $eye       left|right.
	 * @param string $power     sph|cyl|axis|add.
	 * @param bool   $required  HTML required attribute.
	 */
	public static function render_power_select( $eye, $power, $required = true ) {
		$eye   = 'right' === $eye ? 'right' : 'left';
		$power = sanitize_key( $power );
		$id    = 'wc_optic_' . $eye . '_' . $power;
		$name  = $id;
		$terms = WC_Optic_Catalog::get_terms( $power );
		$label = WC_Optic_Catalog::get_power_field_label( $power );

		echo '<p class="form-row wc-optic-power">';
		echo '<label for="' . esc_attr( $id ) . '">';
		echo '<span class="wc-optic-ltr" dir="ltr">' . esc_html( $label ) . '</span>';
		echo '</label>';

		if ( empty( $terms ) ) {
			echo '<span class="wc-optic-no-terms">' . esc_html__( 'No values configured. Ask the store to add powers in Optic Settings.', 'wc-optic' ) . '</span>';
			echo '</p>';
			return;
		}

		printf(
			'<select name="%1$s" id="%2$s" class="wc-optic-power-select" %3$s data-placeholder="%4$s">',
			esc_attr( $name ),
			esc_attr( $id ),
			$required ? 'required' : '',
			esc_attr__( '— Select —', 'wc-optic' )
		);
		echo '<option value=""></option>';
		foreach ( $terms as $row ) {
			printf(
				'<option value="%1$d">%2$s</option>',
				(int) $row->id,
				esc_html( $row->name )
			);
		}
		echo '</select></p>';
	}
}
