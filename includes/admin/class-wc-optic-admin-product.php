<?php
/**
 * Product data admin UI for Optic Product type.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Admin_Product
 */
class WC_Optic_Admin_Product {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_panels' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Tab definition.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function product_tabs( $tabs ) {
		$tabs['optic_config'] = array(
			'label'    => __( 'Optic configuration', 'wc-optic' ),
			'target'   => 'optic_product_data_panel',
			'class'    => array( 'show_if_optic_product' ),
			'priority' => 26,
		);
		return $tabs;
	}

	/**
	 * Panel HTML.
	 */
	public static function product_panels() {
		global $post;

		echo '<div id="optic_product_data_panel" class="panel woocommerce_options_panel hidden">';

		$product = wc_get_product( $post->ID );
		if ( ! $product || 'optic_product' !== $product->get_type() ) {
			echo '<p>' . esc_html__( 'Save as Optic Product to configure optical data.', 'wc-optic' ) . '</p></div>';
			return;
		}

		woocommerce_wp_select(
			array(
				'id'                => '_optic_division',
				'label'             => __( 'Optical division', 'wc-optic' ),
				'options'           => self::division_options(),
				'value'             => $product->get_meta( '_optic_division', true ),
				'class'             => 'wc-enhanced-select wc-optic-select2',
				'wrapper_class'     => 'form-field-wide',
				'custom_attributes' => array(
					'data-placeholder' => __( '— Select —', 'wc-optic' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_optic_unit_price',
				'label'             => __( 'Unit price', 'wc-optic' ),
				'description'       => __( 'Price per lens/unit. Cart line total = unit price × quantity (or × left + right quantities when quantity per eye is enabled). Synced to the WooCommerce product price.', 'wc-optic' ),
				'value'             => $product->get_regular_price( 'edit' ),
				'class'             => 'wc_input_price short',
				'wrapper_class'     => 'form-field-wide',
				'data_type'         => 'price',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_optic_default_qty_per_eye',
				'label'       => __( 'Quantity per eye (default ON)', 'wc-optic' ),
				'description' => __( 'When enabled, the product defaults to separate left/right quantities on the product page.', 'wc-optic' ),
				'value'       => 'yes' === $product->get_meta( '_optic_default_qty_per_eye', true ) ? 'yes' : 'no',
			)
		);

		echo '<p class="form-field"><strong>' . esc_html__( 'SKU components', 'wc-optic' ) . '</strong></p>';
		echo '<p class="form-field wc-optic-sku-powers-hint description">';
		echo esc_html__( 'Power fields (SPH, CYL, AXIS, ADD) depend on the optical division selected above.', 'wc-optic' );
		echo '</p>';

		$power_types = WC_Optic_Catalog::get_power_types();

		foreach ( WC_Optic_SKU::META_KEYS as $type => $meta_key ) {
			$label = self::type_label( $type );
			$rows  = WC_Optic_Catalog::get_terms( $type );
			$opts  = array( '' => __( '— Select —', 'wc-optic' ) );
			foreach ( $rows as $row ) {
				$opts[ $row->id ] = $row->name;
			}

			$wrapper_class = 'wc-optic-sku-field form-field-wide';
			if ( in_array( $type, $power_types, true ) ) {
				$wrapper_class .= ' wc-optic-sku-power';
			}

			woocommerce_wp_select(
				array(
					'id'            => $meta_key,
					'label'         => $label,
					'options'       => $opts,
					'value'         => $product->get_meta( $meta_key, true ),
					'class'             => 'wc-enhanced-select wc-optic-select2 wc-optic-catalog-select',
					'wrapper_class'     => $wrapper_class,
					'custom_attributes' => array(
						'data-optic-type'  => $type,
						'data-placeholder' => __( '— Select —', 'wc-optic' ),
					),
				)
			);
		}

		echo '<p class="form-field">';
		echo '<label>' . esc_html__( 'Live SKU preview', 'wc-optic' ) . '</label>';
		echo '<code id="wc-optic-admin-sku-preview" style="display:block;padding:8px;background:#f6f7f7;"></code>';
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Type label for admin.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	protected static function type_label( $type ) {
		$labels = WC_Optic_Catalog::get_type_labels();
		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ];
		}
		return $type;
	}

	/**
	 * Division <select> options.
	 *
	 * @return array
	 */
	protected static function division_options() {
		$out = array( '' => __( '— Select —', 'wc-optic' ) );
		foreach ( WC_Optic_Plugin::get_divisions() as $slug => $def ) {
			$out[ $slug ] = $def['label'];
		}
		return $out;
	}

	/**
	 * Save handler.
	 *
	 * @param WC_Product $product Product.
	 */
	public static function save_product( $product ) {
		if ( 'optic_product' !== $product->get_type() ) {
			return;
		}
		$division = isset( $_POST['_optic_division'] ) ? sanitize_key( wp_unslash( $_POST['_optic_division'] ) ) : '';
		$divs     = WC_Optic_Plugin::get_divisions();
		if ( $division && ! isset( $divs[ $division ] ) ) {
			$division = '';
		}
		$product->update_meta_data( '_optic_division', $division );
		$product->update_meta_data( '_optic_default_qty_per_eye', isset( $_POST['_optic_default_qty_per_eye'] ) ? 'yes' : 'no' );

		if ( isset( $_POST['_optic_unit_price'] ) ) {
			$unit_price = wc_format_decimal( wp_unslash( $_POST['_optic_unit_price'] ) );
			$product->set_regular_price( $unit_price );
			if ( '' === $product->get_sale_price( 'edit' ) ) {
				$product->set_price( $unit_price );
			}
		}

		$allowed_powers = $division ? WC_Optic_Plugin::get_powers_for_division( $division ) : array();
		$power_types    = WC_Optic_Catalog::get_power_types();

		foreach ( WC_Optic_SKU::META_KEYS as $type => $meta_key ) {
			if ( in_array( $type, $power_types, true ) && ! in_array( $type, $allowed_powers, true ) ) {
				$product->update_meta_data( $meta_key, 0 );
				continue;
			}
			$val = isset( $_POST[ $meta_key ] ) ? absint( wp_unslash( $_POST[ $meta_key ] ) ) : 0;
			$product->update_meta_data( $meta_key, $val );
		}

		WC_Optic_SKU::sync_product_sku( $product );
	}

	/**
	 * Scripts on product edit screen.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style(
			'wc-optic-admin',
			WC_OPTIC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_OPTIC_VERSION
		);

		wp_enqueue_script(
			'wc-optic-admin-product',
			WC_OPTIC_PLUGIN_URL . 'assets/js/admin-product.js',
			array( 'jquery', 'selectWoo', 'wc-enhanced-select', 'wp-util' ),
			WC_OPTIC_VERSION,
			true
		);
		$division_powers = array();
		foreach ( WC_Optic_Plugin::get_divisions() as $slug => $def ) {
			$division_powers[ $slug ] = $def['powers'];
		}

		wp_localize_script(
			'wc-optic-admin-product',
			'wcOpticAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wc_optic_admin' ),
				'divisionPowers'  => $division_powers,
				'powerTypes'      => WC_Optic_Catalog::get_power_types(),
			)
		);
	}
}
