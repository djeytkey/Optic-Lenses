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

		$product = ( $post && ! empty( $post->ID ) ) ? wc_get_product( $post->ID ) : null;
		if ( ( ! $product || 'optic_product' !== $product->get_type() ) && self::is_new_product_screen() ) {
			$product = new WC_Product_Optic_Product();
		}

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

		woocommerce_wp_checkbox(
			array(
				'id'          => '_optic_default_qty_per_eye',
				'label'       => __( 'Quantity per eye (default ON)', 'wc-optic' ),
				'description' => __( 'When enabled, the product defaults to separate left/right quantities on the product page.', 'wc-optic' ),
				'value'       => 'yes' === $product->get_meta( '_optic_default_qty_per_eye', true ) ? 'yes' : 'no',
			)
		);

		$division      = (string) $product->get_meta( '_optic_division', true );
		$child_configs = WC_Optic_SKU::get_child_configs( $product );
		if ( empty( $child_configs ) ) {
			$child_configs[] = WC_Optic_SKU::normalize_child_config(
				array(
					'enabled' => true,
				),
				$division,
				0
			);
		}

		echo '<div class="wc-optic-child-configs">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Internal products', 'wc-optic' ) . '</strong></p>';
		echo '<p class="form-field wc-optic-sku-powers-hint description">';
		echo esc_html__( 'Create one internal product per sellable power combination. Each internal product gets its own price and SKU.', 'wc-optic' );
		echo '</p>';
		echo '<div id="wc-optic-child-config-list">';
		foreach ( array_values( $child_configs ) as $index => $config ) {
			self::render_child_config_block( $config, (string) $index, $division );
		}
		echo '</div>';
		echo '<p class="wc-optic-child-actions">';
		echo '<button type="button" class="button button-secondary" id="wc-optic-add-child">+</button> ';
		echo '<span class="description">' . esc_html__( 'Add another internal product.', 'wc-optic' ) . '</span>';
		echo '</p>';
		echo '</div>';

		echo '<script type="text/html" id="wc-optic-child-config-template">';
		self::render_child_config_block(
			WC_Optic_SKU::normalize_child_config(
				array(
					'enabled' => true,
				),
				$division,
				0
			),
			'__INDEX__',
			$division,
			true
		);
		echo '</script>';

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

		$raw_children = isset( $_POST['_optic_child_configs'] ) && is_array( $_POST['_optic_child_configs'] ) ? wp_unslash( $_POST['_optic_child_configs'] ) : array();
		$children     = WC_Optic_SKU::normalize_child_configs( $raw_children, $division );

		WC_Optic_SKU::persist_child_data( $product, $children );
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
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wc_optic_admin' ),
				'isNewProduct'   => self::is_new_product_screen(),
				'divisionPowers' => $division_powers,
				'powerTypes'     => WC_Optic_Catalog::get_power_types(),
				'i18n'           => array(
					'product' => __( 'Product', 'wc-optic' ),
					'remove'  => __( 'Remove', 'wc-optic' ),
				),
			)
		);
	}

	/**
	 * Whether the current admin screen is the new product screen.
	 *
	 * @return bool
	 */
	protected static function is_new_product_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'product' === $screen->id && 'add' === $screen->action;
	}

	/**
	 * Render one repeatable child config block.
	 *
	 * @param array  $config      Child config.
	 * @param string $index_token Field index token.
	 * @param string $division    Parent division.
	 * @param bool   $is_template Template mode.
	 */
	protected static function render_child_config_block( array $config, $index_token, $division, $is_template = false ) {
		$power_types = WC_Optic_Catalog::get_power_types();
		$title       = ! empty( $config['label'] ) ? (string) $config['label'] : __( 'Product', 'wc-optic' );

		echo '<div class="wc-optic-child-config" data-child-index="' . esc_attr( $index_token ) . '">';
		echo '<div class="wc-optic-child-config__header">';
		echo '<h4 class="wc-optic-child-config__title">' . esc_html( $title ) . '</h4>';
		echo '<button type="button" class="button-link-delete wc-optic-remove-child">' . esc_html__( 'Remove', 'wc-optic' ) . '</button>';
		echo '</div>';

		echo '<input type="hidden" class="wc-optic-child-id" name="' . esc_attr( '_optic_child_configs[' . $index_token . '][id]' ) . '" value="' . esc_attr( (string) ( $config['id'] ?? '' ) ) . '" />';
		echo '<input type="hidden" class="wc-optic-child-sort" name="' . esc_attr( '_optic_child_configs[' . $index_token . '][sort]' ) . '" value="' . esc_attr( (string) ( $config['sort'] ?? 0 ) ) . '" />';

		echo '<p class="form-field form-field-wide wc-optic-child-enabled">';
		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( '_optic_child_configs[' . $index_token . '][enabled]' ) . '" value="1" ' . checked( ! empty( $config['enabled'] ), true, false ) . ' />';
		echo ' ' . esc_html__( 'Enabled', 'wc-optic' );
		echo '</label>';
		echo '</p>';

		self::render_child_text_input(
			'_optic_child_configs[' . $index_token . '][label]',
			'wc_optic_child_' . $index_token . '_label',
			__( 'Label', 'wc-optic' ),
			(string) ( $config['label'] ?? '' ),
			'wc-optic-child-label'
		);

		self::render_child_text_input(
			'_optic_child_configs[' . $index_token . '][unit_price]',
			'wc_optic_child_' . $index_token . '_unit_price',
			__( 'Unit price', 'wc-optic' ),
			(string) ( $config['unit_price'] ?? '' ),
			'wc-optic-child-unit-price wc_input_price'
		);

		self::render_child_text_input(
			'_optic_child_configs[' . $index_token . '][stock_qty]',
			'wc_optic_child_' . $index_token . '_stock_qty',
			__( 'Stock quantity', 'wc-optic' ),
			(string) ( $config['stock_qty'] ?? '' ),
			'wc-optic-child-stock-qty',
			'number',
			array(
				'min'  => '0',
				'step' => '1',
			)
		);

		echo '<div class="wc-optic-child-fields-grid">';
		foreach ( WC_Optic_SKU::META_KEYS as $type => $meta_key ) {
			$value = in_array( $type, $power_types, true ) ? (int) ( $config['powers'][ $type ] ?? 0 ) : (int) ( $config['catalog'][ $type ] ?? 0 );
			self::render_child_select_field(
				$index_token,
				$type,
				$value,
				in_array( $type, $power_types, true ),
				$division
			);
		}
		echo '</div>';

		echo '<p class="form-field form-field-wide wc-optic-child-sku">';
		echo '<label>' . esc_html__( 'SKU preview', 'wc-optic' ) . '</label>';
		echo '<code class="wc-optic-child-sku-preview">' . esc_html( (string) ( $config['sku'] ?? '' ) ) . '</code>';
		echo '</p>';

		echo '</div>';
	}

	/**
	 * Render child text input.
	 *
	 * @param string $name       Field name.
	 * @param string $id         Field id.
	 * @param string $label      Label.
	 * @param string $value      Value.
	 * @param string $class      CSS classes.
	 * @param string $type       Input type.
	 * @param array  $attributes HTML attributes.
	 */
	protected static function render_child_text_input( $name, $id, $label, $value, $class = '', $type = 'text', array $attributes = array() ) {
		$attrs = '';
		foreach ( $attributes as $attr_key => $attr_value ) {
			$attrs .= ' ' . sanitize_key( $attr_key ) . '="' . esc_attr( (string) $attr_value ) . '"';
		}

		echo '<p class="form-field form-field-wide">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( $type ) . '" class="' . esc_attr( $class ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '"' . $attrs . ' />';
		echo '</p>';
	}

	/**
	 * Render one catalog select inside a child block.
	 *
	 * @param string $index_token Field index token.
	 * @param string $type        Catalog type.
	 * @param int    $selected    Selected id.
	 * @param bool   $is_power    Whether field is a power.
	 * @param string $division    Parent division.
	 */
	protected static function render_child_select_field( $index_token, $type, $selected, $is_power, $division ) {
		$name = $is_power ? '_optic_child_configs[' . $index_token . '][powers][' . $type . ']' : '_optic_child_configs[' . $index_token . '][catalog][' . $type . ']';
		$id   = 'wc_optic_child_' . $index_token . '_' . $type;

		$wrapper = 'form-field form-field-wide wc-optic-child-field';
		if ( $is_power ) {
			$wrapper .= ' wc-optic-child-power';
		}

		echo '<p class="' . esc_attr( $wrapper ) . '" data-optic-type="' . esc_attr( $type ) . '">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( self::type_label( $type ) ) . '</label>';
		echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="wc-enhanced-select wc-optic-select2 wc-optic-child-select" data-optic-type="' . esc_attr( $type ) . '" data-is-power="' . esc_attr( $is_power ? '1' : '0' ) . '" data-placeholder="' . esc_attr__( '- Select -', 'wc-optic' ) . '">';
		echo '<option value=""></option>';
		foreach ( WC_Optic_Catalog::get_terms( $type ) as $row ) {
			echo '<option value="' . esc_attr( (string) $row->id ) . '" ' . selected( (int) $selected, (int) $row->id, false ) . '>' . esc_html( $row->name ) . '</option>';
		}
		echo '</select>';
		echo '</p>';
	}
}
