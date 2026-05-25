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
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script(
			'wc-optic-admin-settings',
			WC_OPTIC_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
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
					'confirmDelete'        => __( 'Are you sure you want to delete this catalog entry? This cannot be undone.', 'wc-optic' ),
					'deleteFailed'         => __( 'Could not delete the entry.', 'wc-optic' ),
					'affectedNoticeTitle'  => __( 'These products still reference the deleted catalog value. Update their optic configuration (SKU components) where needed:', 'wc-optic' ),
					'affectedNone'         => __( 'No products were using this value.', 'wc-optic' ),
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

		self::handle_global_settings_save();

		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'section'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'deletion_log' === $requested ) {
			$active = 'deletion_log';
		} elseif ( WC_Optic_Catalog::is_valid_type( $requested ) ) {
			$active = $requested;
		} else {
			$active = 'section';
		}

		if ( 'deletion_log' !== $active ) {
			self::handle_post_save( $active );
		}

		echo '<div class="wrap woocommerce" id="wc-optic-settings-root" data-active-tab="' . esc_attr( $active ) . '">';
		echo '<h1>' . esc_html__( 'Optic Settings', 'wc-optic' ) . '</h1>';
		self::render_global_settings_panel();
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( WC_Optic_Catalog::TYPES as $type ) {
			$url   = admin_url( 'admin.php?page=wc-optic-settings&tab=' . $type );
			$class = $active === $type ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( self::tab_label( $type ) ) . '</a>';
		}
		$log_url = admin_url( 'admin.php?page=wc-optic-settings&tab=deletion_log' );
		$log_cls = 'deletion_log' === $active ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a class="' . esc_attr( $log_cls ) . '" href="' . esc_url( $log_url ) . '">' . esc_html__( 'Deletion log', 'wc-optic' ) . '</a>';
		$import_tab = ( 'deletion_log' === $active || ! WC_Optic_Catalog::is_valid_type( $active ) ) ? 'section' : $active;
		$import_url = admin_url( 'admin.php?page=wc-optic-import&tab=' . rawurlencode( $import_tab ) );
		echo '<a class="nav-tab" href="' . esc_url( $import_url ) . '">' . esc_html__( 'Import', 'wc-optic' ) . '</a>';
		echo '</h2>';

		echo '<div id="wc-optic-inline-messages" class="wc-optic-inline-messages" aria-live="polite"></div>';

		if ( 'deletion_log' === $active ) {
			self::render_deletion_log_panel();
			echo '</div>';
			return;
		}

		echo '<form method="post" action="" class="wc-optic-settings-form">';
		wp_nonce_field( 'wc_optic_settings_save', 'wc_optic_settings_nonce' );
		echo '<input type="hidden" name="wc_optic_active_tab" value="' . esc_attr( $active ) . '" />';

		$rows = WC_Optic_Catalog::get_terms( $active );
		echo '<p class="description">' . esc_html__( 'Enter a display name and the SKU fragment used when building product SKUs. Both fields are required for each row you save.', 'wc-optic' ) . '</p>';
		echo '<table class="widefat striped wc-optic-settings-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'SKU fragment', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Sort', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wc-optic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			self::render_row( $active, $row, (string) $row->id );
		}
		self::render_row( $active, null, 'new_' . wp_unique_id() );

		echo '</tbody></table>';

		echo '<p class="wc-optic-settings-toolbar">';
		echo '<button type="button" class="button" id="wc-optic-add-row">' . esc_html__( 'Add new', 'wc-optic' ) . '</button> ';
		// echo '<span class="description">' . esc_html__( 'Add as many rows as you need, fill them in, then save once with the button below.', 'wc-optic' ) . '</span>';
		echo '</p>';

		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'wc-optic' ) . '</button></p>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Render global optic settings shared by all products.
	 */
	protected static function render_global_settings_panel() {
		echo '<form method="post" action="" class="wc-optic-global-settings">';
		wp_nonce_field( 'wc_optic_global_settings_save', 'wc_optic_global_settings_nonce' );
		echo '<div class="notice inline wc-optic-global-settings-box">';
		echo '<p><strong>' . esc_html__( 'Global storefront settings', 'wc-optic' ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'These settings affect all optic products in the shop.', 'wc-optic' ) . '</p>';
		echo '<p class="form-field">';
		echo '<label for="wc_optic_global_selector_ui"><strong>' . esc_html__( 'Child selector UI', 'wc-optic' ) . '</strong></label><br />';
		echo '<select name="wc_optic_global_selector_ui" id="wc_optic_global_selector_ui">';
		foreach ( WC_Optic_SKU::get_selector_ui_options() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( WC_Optic_SKU::get_selector_ui(), $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</p>';
		echo '<p><button type="submit" class="button button-secondary">' . esc_html__( 'Save global settings', 'wc-optic' ) . '</button></p>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * Deletion audit log (read-only).
	 */
	protected static function render_deletion_log_panel() {
		echo '<p class="description">' . esc_html__( 'Each deletion stores the user, date, deleted entry, and products that still referenced that catalog id in their optic SKU fields.', 'wc-optic' ) . '</p>';

		$entries = WC_Optic_Deletion_Log::get_entries( 200 );
		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No catalog deletions have been recorded yet.', 'wc-optic' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped wc-optic-deletion-log-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'List', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Deleted entry', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Catalog ID', 'wc-optic' ) . '</th>';
		echo '<th>' . esc_html__( 'Affected products', 'wc-optic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$user         = $entry->deleted_by ? get_userdata( (int) $entry->deleted_by ) : false;
			$user_display = $user ? $user->display_name : '—';
			$date_display = mysql2date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				$entry->deleted_at
			);

			$products = json_decode( $entry->affected_products, true );
			if ( ! is_array( $products ) ) {
				$products = array();
			}

			echo '<tr>';
			echo '<td>' . esc_html( $date_display ) . '</td>';
			echo '<td>' . esc_html( $user_display );
			if ( $user && $user->user_login ) {
				echo '<br /><span class="description">' . esc_html( $user->user_login ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( self::tab_label( $entry->term_type ) ) . '</td>';
			echo '<td><strong>' . esc_html( $entry->term_name ) . '</strong></td>';
			echo '<td>' . esc_html( (string) (int) $entry->catalog_term_id ) . '</td>';
			echo '<td>';
			if ( empty( $products ) ) {
				echo '<em>' . esc_html__( 'None', 'wc-optic' ) . '</em>';
			} else {
				echo '<ul class="wc-optic-deletion-log-products">';
				foreach ( $products as $p ) {
					$pid  = isset( $p['id'] ) ? (int) $p['id'] : 0;
					$pname = isset( $p['name'] ) ? $p['name'] : '';
					$post  = $pid ? get_post( $pid ) : null;
					echo '<li>';
					if ( $post && get_edit_post_link( $pid ) ) {
						echo '<a href="' . esc_url( get_edit_post_link( $pid, 'raw' ) ) . '">' . esc_html( $pname ) . '</a>';
						echo ' <span class="description">#' . esc_html( (string) $pid ) . '</span>';
					} else {
						echo esc_html( $pname );
						if ( $pid ) {
							echo ' <span class="description">#' . esc_html( (string) $pid ) . ' (' . esc_html__( 'removed or no access', 'wc-optic' ) . ')</span>';
						}
					}
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Tab label.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	protected static function tab_label( $type ) {
		return WC_Optic_Catalog::get_type_label( $type );
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
		$frag_val = $row && isset( $row->sku_fragment ) ? (string) $row->sku_fragment : '';
		echo '<td><input type="text" name="' . esc_attr( $pf ) . '[name]" value="' . esc_attr( $row ? $row->name : '' ) . '" class="regular-text wc-optic-catalog-name" autocomplete="off" required /></td>';
		echo '<td><input type="text" name="' . esc_attr( $pf ) . '[sku_fragment]" value="' . esc_attr( $frag_val ) . '" class="regular-text wc-optic-catalog-fragment" autocomplete="off" required /></td>';
		echo '<td><input type="number" name="' . esc_attr( $pf ) . '[sort_order]" value="' . esc_attr( $row ? (int) $row->sort_order : 0 ) . '" class="small-text" /></td>';
		echo '<td>';
		if ( $id ) {
			$delete_label = sprintf(
				/* translators: %s: catalog entry name */
				__( 'Delete %s', 'wc-optic' ),
				$row->name
			);
			echo '<button type="button" class="button-link-delete wc-optic-delete-row" data-id="' . esc_attr( (string) $id ) . '" aria-label="' . esc_attr( $delete_label ) . '">';
			echo '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
			echo '</button> ';
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

		$skipped_duplicate  = 0;
		$skipped_incomplete = 0;

		foreach ( wp_unslash( $_POST['wc_optic_row'] ) as $key => $data ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! is_array( $data ) ) {
				continue;
			}
			$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
			$frag = isset( $data['sku_fragment'] ) ? WC_Optic_Catalog::sanitize_sku_fragment( $data['sku_fragment'] ) : '';
			$sort = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
			$id   = isset( $data['id'] ) ? (int) $data['id'] : 0;

			if ( '' === trim( $name ) && '' === $frag ) {
				continue;
			}

			if ( '' === trim( $name ) || '' === $frag ) {
				++$skipped_incomplete;
				continue;
			}

			$slug = WC_Optic_Catalog::sanitize_slug( $name );

			if ( '' === $slug ) {
				$slug = 'item-' . strtolower( wp_unique_id() );
			}

			if ( $id ) {
				$other = WC_Optic_Catalog::get_by_slug( $active, $slug );
				if ( $other && (int) $other->id !== $id ) {
					$slug = WC_Optic_Catalog::sanitize_slug( $name ) . '-' . $id;
				}
				WC_Optic_Catalog::update(
					$id,
					array(
						'name'           => $name,
						'slug'           => $slug,
						'sku_fragment'   => $frag,
						'sort_order'     => $sort,
					)
				);
			} else {
				if ( WC_Optic_Catalog::get_by_slug( $active, $slug ) ) {
					++$skipped_duplicate;
					continue;
				}
				WC_Optic_Catalog::insert( $active, $name, $slug, $frag, $sort );
			}
		}

		add_action(
			'admin_notices',
			function () use ( $skipped_duplicate, $skipped_incomplete ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Optic catalog saved.', 'wc-optic' ) . '</p></div>';
				if ( $skipped_incomplete > 0 ) {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					echo esc_html(
						sprintf(
							/* translators: %d: number of incomplete rows */
							_n(
								'%d row was not saved because both name and SKU fragment are required.',
								'%d rows were not saved because both name and SKU fragment are required.',
								$skipped_incomplete,
								'wc-optic'
							),
							$skipped_incomplete
						)
					);
					echo '</p></div>';
				}
				if ( $skipped_duplicate > 0 ) {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					echo esc_html(
						sprintf(
							/* translators: %d: number of rows skipped */
							_n(
								'%d new row was not created because another entry with the same label already exists in this list.',
								'%d new rows were not created because other entries with the same label already exist in this list.',
								$skipped_duplicate,
								'wc-optic'
							),
							$skipped_duplicate
						)
					);
					echo '</p></div>';
				}
			}
		);
	}

	/**
	 * Save global settings shared by all optic products.
	 */
	protected static function handle_global_settings_save() {
		if ( empty( $_POST['wc_optic_global_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_optic_global_settings_nonce'] ) ), 'wc_optic_global_settings_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$selector_ui = isset( $_POST['wc_optic_global_selector_ui'] ) ? WC_Optic_SKU::set_selector_ui( wp_unslash( $_POST['wc_optic_global_selector_ui'] ) ) : WC_Optic_SKU::get_selector_ui();

		add_action(
			'admin_notices',
			function () use ( $selector_ui ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html(
					sprintf(
						/* translators: %s: selector mode */
						__( 'Global optic selector UI saved: %s.', 'wc-optic' ),
						$selector_ui
					)
				);
				echo '</p></div>';
			}
		);
	}
}
