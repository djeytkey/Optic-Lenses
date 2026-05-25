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
		add_filter( 'wc_product_sku_enabled', array( __CLASS__, 'hide_parent_sku_for_optic_products' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_optic_product_add_to_cart', array( __CLASS__, 'render_add_to_cart' ), 30 );
	}

	/**
	 * Hide the parent product SKU on optic product pages.
	 *
	 * @param bool $enabled Whether WooCommerce should render the SKU block.
	 * @return bool
	 */
	public static function hide_parent_sku_for_optic_products( $enabled ) {
		if ( ! $enabled || ! is_product() ) {
			return $enabled;
		}

		global $product;
		if ( $product instanceof WC_Product && 'optic_product' === $product->get_type() ) {
			return false;
		}

		return $enabled;
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
	 * Render the add-to-cart form for the custom optic product type.
	 */
	public static function render_add_to_cart() {
		wc_get_template( 'single-product/add-to-cart/optic_product.php' );
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
				'selectorUi'     => WC_Optic_SKU::get_selector_ui( $product ),
				'i18n'           => array(
					'rightEye'       => __( 'Right eye (OD)', 'wc-optic' ),
					'leftEye'        => __( 'Left eye (OS)', 'wc-optic' ),
					'select'         => __( '— Select —', 'wc-optic' ),
					'selectedPrice'  => __( 'Selected price', 'wc-optic' ),
					'estimatedTotal' => __( 'Estimated total', 'wc-optic' ),
				),
			)
		);
	}

	/**
	 * Get enabled child configurations for storefront use.
	 *
	 * @param WC_Product $product Product.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_storefront_child_configs( WC_Product $product ) {
		return WC_Optic_SKU::get_enabled_child_configs( $product );
	}

	/**
	 * Whether a product has sellable child options.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function has_child_options( WC_Product $product ) {
		return ! empty( self::get_storefront_child_configs( $product ) );
	}

	/**
	 * Render one child selector for a specific eye.
	 *
	 * @param WC_Product $product  Product.
	 * @param string     $eye      left|right.
	 * @param bool       $required HTML required attribute.
	 */
	public static function render_child_selector( WC_Product $product, $eye, $required = true ) {
		$eye          = 'right' === $eye ? 'right' : 'left';
		$children     = self::get_storefront_child_configs( $product );
		$selector_ui  = WC_Optic_SKU::get_selector_ui( $product );
		$division     = (string) $product->get_meta( '_optic_division', true );
		$field_name   = 'wc_optic_' . $eye . '_child';
		$field_id     = $field_name;
		$default_id   = ! empty( $children[0]['id'] ) ? (string) $children[0]['id'] : '';
		$label        = 'right' === $eye ? __( 'Right eye (OD)', 'wc-optic' ) : __( 'Left eye (OS)', 'wc-optic' );

		echo '<div class="wc-optic-child-selector wc-optic-child-selector--' . esc_attr( $selector_ui ) . '" data-eye="' . esc_attr( $eye ) . '">';
		echo '<span class="wc-optic-eye-selector-label">' . esc_html( $label ) . '</span>';

		if ( 'radio' === $selector_ui ) {
			echo '<div class="wc-optic-child-radio-list">';
			foreach ( $children as $index => $config ) {
				$child_id = (string) ( $config['id'] ?? '' );
				echo '<label class="wc-optic-child-choice">';
				echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $child_id ) . '" data-price="' . esc_attr( (string) WC_Optic_SKU::get_child_unit_price( $config ) ) . '" ' . checked( 0 === $index, true, false ) . ( $required ? ' required' : '' ) . ' />';
				echo '<span class="wc-optic-child-choice__content">';
				echo '<span class="wc-optic-child-choice__powers">' . wp_kses_post( self::render_child_choice_powers( $config, $division ) ) . '</span>';
				echo '<span class="wc-optic-child-choice__meta">';
				echo '<span class="wc-optic-child-choice__price">' . wp_kses_post( wc_price( WC_Optic_SKU::get_child_unit_price( $config ) ) ) . '</span>';
				echo '</span>';
				echo '</span>';
				echo '</label>';
			}
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" class="wc-optic-child-dropdown" ' . ( $required ? 'required ' : '' ) . 'data-placeholder="' . esc_attr__( '— Select —', 'wc-optic' ) . '">';
		echo '<option value=""></option>';
		foreach ( $children as $config ) {
			$child_id = (string) ( $config['id'] ?? '' );
			echo '<option value="' . esc_attr( $child_id ) . '" data-price="' . esc_attr( (string) WC_Optic_SKU::get_child_unit_price( $config ) ) . '" ' . selected( $child_id, $default_id, false ) . '>';
			echo esc_html( WC_Optic_SKU::child_display_label( $config, $division ) . ' - ' . (string) ( $config['sku'] ?? '' ) );
			echo '</option>';
		}
		echo '</select>';
		echo '</div>';
	}

	/**
	 * Render a vertical list of powers for one child choice.
	 *
	 * @param array  $config   Child config.
	 * @param string $division Product division.
	 * @return string
	 */
	protected static function render_child_choice_powers( array $config, $division ) {
		$html = '<ul class="wc-optic-child-choice__powers-list">';
		foreach ( WC_Optic_Plugin::get_powers_for_division( $division ) as $power ) {
			$id  = isset( $config['powers'][ $power ] ) ? (int) $config['powers'][ $power ] : 0;
			$row = $id ? WC_Optic_Catalog::get_valid_term( $id, $power ) : null;
			if ( ! $row ) {
				continue;
			}

			$html .= '<li class="wc-optic-child-choice__power-item">';
			$html .= esc_html( WC_Optic_Catalog::get_power_field_label( $power ) . ': ' . $row->name );
			$html .= '</li>';
		}
		$html .= '</ul>';

		return $html;
	}
}
