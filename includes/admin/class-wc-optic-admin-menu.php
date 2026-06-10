<?php
/**
 * Top-level WordPress admin menu for Alwaleed Optics.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Admin_Menu
 */
class WC_Optic_Admin_Menu {

	const PARENT_SLUG     = 'wc-optic-settings';
	const MENU_POSITION   = 3;
	const SETTINGS_SCREEN = 'toplevel_page_wc-optic-settings';
	const IMPORT_SCREEN   = 'wc-optic-settings_page_wc-optic-import';

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register main menu and submenus (Settings, Import).
	 */
	public static function register() {
		add_menu_page(
			__( 'Alwaleed Optics', 'wc-optic' ),
			__( 'Alwaleed Optics', 'wc-optic' ),
			'manage_woocommerce',
			self::PARENT_SLUG,
			array( 'WC_Optic_Admin_Settings', 'render_page' ),
			'dashicons-visibility',
			self::MENU_POSITION
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings', 'wc-optic' ),
			__( 'Settings', 'wc-optic' ),
			'manage_woocommerce',
			self::PARENT_SLUG,
			array( 'WC_Optic_Admin_Settings', 'render_page' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Import', 'wc-optic' ),
			__( 'Import', 'wc-optic' ),
			'manage_woocommerce',
			'wc-optic-import',
			array( 'WC_Optic_Admin_Import', 'render_page' )
		);
	}
}
