<?php
/**
 * WooCommerce → Optic Settings.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Admin_Settings
 */
class WC_Optic_Admin_Settings {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Optic Settings', 'wc-optic' ),
			__( 'Optic Settings', 'wc-optic' ),
			'manage_woocommerce',
			'wc-optic-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue SelectWoo on settings page.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'woocommerce_page_wc-optic-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script(
			'wc-optic-admin-settings',
			WC_OPTIC_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery', 'wp-util' ),
			WC_OPTIC_VERSION,
			true
		);
		wp_localize_script(
			'wc-optic-admin-settings',
			'wcOpticAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wc_optic_admin' ),
				'i18n'    => array(
					'name'   => __( 'Name', 'wc-optic' ),
					'slug'   => __( 'Slug', 'wc-optic' ),
					'skuFrag'=> __( 'SKU fragment', 'wc-optic' ),
					'add'    => __( 'Add row', 'wc-optic' ),
					'save'   => __( 'Save', 'wc-optic' ),
					'delete' => __( 'Delete', 'wc-optic' ),
				),
			)
		);
		wp_enqueue_style(
			'wc-optic-admin',
			WC_OPTIC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_OPTIC_VERSION
		);
	}

	/**
	 * Render settings tabs and tables.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'section'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! WC_Optic_Catalog::is_valid_type( $active ) ) {
			$active = 'section';
		}

		self::handle_post_save( $active );

		echo '<div class="wrap woocommerce" id="wc-optic-settings-root" data-active-tab="' . esc_attr( $active ) . '">';
		echo '<h1>' . esc_html__( 'Optic Settings', 'wc-optic' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( WC_Optic_Catalog::TYPES as $type ) {
			$url   = admin_url( 'admin.php?page=wc-optic-settings&tab=' . $type );
			$class = $active === $type ? 'nav-tab nav-tab-active' : 'nav-tab';
			/* translators: %s: settings section name */
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( self::tab_label( $type ) ) . '</a>';
		}
		$import_url = admin_url( 'admin.php?page=wc-optic-import' );
		echo '<a class="nav-tab" href="' . esc_url( $import_url ) . '">' . esc_html__( 'Import', 'wc-optic' ) . '</a>';
		echo '</h2>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'wc_optic_settings_save', 'wc_optic_settings_nonce' );
		echo '<input type="hidden" name="wc_optic_active_tab" value="' . esc_attr( $active ) . '" />';

		$rows = WC_Optic_Catalog::get_terms( $active );
		echo '<table class="widefat striped wc-optic-settings-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'SKU fragment', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Sort', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wc-optic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			self::render_row( $active, $row, (string) $row->id );
		}
		self::render_row( $active, null, 'new_' . wp_unique_id() );

		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'wc-optic' ) . '</button></p>';
		echo '</form>';

		echo '<hr/><p><button type="button" class="button" id="wc-optic-quick-add">' . esc_html__( 'Quick add (AJAX)', 'wc-optic' ) . '</button></p>';
		echo '<div id="wc-optic-quick-add-panel" style="display:none;margin-top:10px;">';
		echo '<p><input type="text" id="wc-optic-quick-name" placeholder="' . esc_attr__( 'Name', 'wc-optic' ) . '" class="regular-text" /></p>';
		echo '<p><input type="text" id="wc-optic-quick-slug" placeholder="' . esc_attr__( 'Slug (optional)', 'wc-optic' ) . '" class="regular-text" /></p>';
		echo '<p><input type="text" id="wc-optic-quick-frag" placeholder="' . esc_attr__( 'SKU fragment (optional)', 'wc-optic' ) . '" class="regular-text" /></p>';
		echo '<p><button type="button" class="button button-primary" id="wc-optic-quick-submit">' . esc_html__( 'Create', 'wc-optic' ) . '</button></p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Tab label.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	protected static function tab_label( $type ) {
		$map = array(
			'section'      => __( 'Sections', 'wc-optic' ),
			'company'      => __( 'Companies', 'wc-optic' ),
			'brand'        => __( 'Brands', 'wc-optic' ),
			'timing'       => __( 'Timings', 'wc-optic' ),
			'color'        => __( 'Colors', 'wc-optic' ),
			'sign'         => __( 'Signs', 'wc-optic' ),
			'rx'           => __( 'RX', 'wc-optic' ),
			'pack'         => __( 'Packs', 'wc-optic' ),
			'transparency' => __( 'Transparency', 'wc-optic' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $type;
	}

	/**
	 * Table row.
	 *
	 * @param string     $type Term type.
	 * @param object|null $row DB row or null for empty row.
	 * @param string     $suffix Form array key suffix.
	 */
	protected static function render_row( $type, $row, $suffix ) {
		$id = $row ? (int) $row->id : 0;
		$pf = 'wc_optic_row[' . $suffix . ']';
		echo '<tr>';
		echo '<td><input type="text" name="' . esc_attr( $pf ) . '[name]" value="' . esc_attr( $row ? $row->name : '' ) . '" class="regular-text" /></td>';
		echo '<td><input type="text" name="' . esc_attr( $pf ) . '[slug]" value="' . esc_attr( $row ? $row->slug : '' ) . '" class="regular-text" ' . ( $id ? 'readonly="readonly"' : '' ) . ' /></td>';
		echo '<td><input type="text" name="' . esc_attr( $pf ) . '[sku_fragment]" value="' . esc_attr( $row && isset( $row->sku_fragment ) ? $row->sku_fragment : '' ) . '" class="regular-text" /></td>';
		echo '<td><input type="number" name="' . esc_attr( $pf ) . '[sort_order]" value="' . esc_attr( $row ? (int) $row->sort_order : 0 ) . '" class="small-text" /></td>';
		echo '<td>';
		if ( $id ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $pf ) . '[delete]" value="1" /> ' . esc_html__( 'Delete', 'wc-optic' ) . '</label>';
			echo '<input type="hidden" name="' . esc_attr( $pf ) . '[id]" value="' . esc_attr( (string) $id ) . '" />';
		}
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Handle bulk save from form.
	 *
	 * @param string $active Active type tab.
	 */
	protected static function handle_post_save( $active ) {
		if ( empty( $_POST['wc_optic_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_optic_settings_nonce'] ) ), 'wc_optic_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_POST['wc_optic_row'] ) || ! is_array( $_POST['wc_optic_row'] ) ) {
			return;
		}

		foreach ( wp_unslash( $_POST['wc_optic_row'] ) as $key => $data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! is_array( $data ) ) {
				continue;
			}
			$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
			$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
			$frag = isset( $data['sku_fragment'] ) ? sanitize_text_field( $data['sku_fragment'] ) : '';
			$sort = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
			$id   = isset( $data['id'] ) ? (int) $data['id'] : 0;
			$del  = ! empty( $data['delete'] );

			if ( $del && $id ) {
				WC_Optic_Catalog::delete( $id );
				continue;
			}

			if ( '' === $name ) {
				continue;
			}

			if ( $id ) {
				WC_Optic_Catalog::update(
					$id,
					array(
						'name'         => $name,
						'sku_fragment' => $frag,
						'sort_order'   => $sort,
					)
				);
			} else {
				if ( ! $slug ) {
					$slug = sanitize_title( $name );
				}
				if ( WC_Optic_Catalog::get_by_slug( $active, $slug ) ) {
					continue;
				}
				WC_Optic_Catalog::insert( $active, $name, $slug, $frag, $sort );
			}
		}

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Optic catalog saved.', 'wc-optic' ) . '</p></div>';
			}
		);
	}
}
