<?php
/**
 * Excel / CSV import with mapping and logs.
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Admin_Import
 */
class WC_Optic_Admin_Import {

	const OPTION_LOGS = 'wc_optic_import_logs';

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
	}

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Optic Import', 'wc-optic' ),
			__( 'Optic Import', 'wc-optic' ),
			'manage_woocommerce',
			'wc-optic-import',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Append log entry.
	 *
	 * @param array $entry Entry.
	 */
	public static function add_log( array $entry ) {
		$logs = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$entry['time'] = current_time( 'mysql' );
		array_unshift( $logs, $entry );
		$logs = array_slice( $logs, 0, 100 );
		update_option( self::OPTION_LOGS, $logs, false );
	}

	/**
	 * Render import UI.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result = null;
		if ( ! empty( $_POST['wc_optic_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_optic_import_nonce'] ) ), 'wc_optic_import' ) ) {
			$result = self::process_import();
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Optic catalog import', 'wc-optic' ) . '</h1>';

		if ( is_array( $result ) ) {
			$cls = $result['error'] ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $cls ) . '"><p>' . esc_html( $result['message'] ) . '</p></div>';
		}

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'wc_optic_import', 'wc_optic_import_nonce' );
		echo '<p><input type="file" name="wc_optic_file" accept=".csv,.txt,.xlsx" required /></p>';

		echo '<h2>' . esc_html__( 'Column mapping', 'wc-optic' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Enter the letters of Excel columns (A, B, C…) or CSV column indexes starting at 0.', 'wc-optic' ) . '</p>';
		echo '<table class="form-table"><tbody>';
		$fields = array(
			'term_type'    => __( 'Type (section, company, …)', 'wc-optic' ),
			'name'         => __( 'Name', 'wc-optic' ),
			'slug'         => __( 'Slug (optional)', 'wc-optic' ),
			'sku_fragment' => __( 'SKU fragment (optional)', 'wc-optic' ),
			'sort_order'   => __( 'Sort order (optional)', 'wc-optic' ),
		);
		foreach ( $fields as $key => $lbl ) {
			echo '<tr><th><label for="map_' . esc_attr( $key ) . '">' . esc_html( $lbl ) . '</label></th>';
			echo '<td><input name="map_' . esc_attr( $key ) . '" id="map_' . esc_attr( $key ) . '" class="regular-text" placeholder="e.g. A or 0" /></td></tr>';
		}
		echo '</tbody></table>';

		submit_button( __( 'Run import', 'wc-optic' ) );
		echo '</form>';

		$logs = get_option( self::OPTION_LOGS, array() );
		if ( is_array( $logs ) && $logs ) {
			echo '<h2>' . esc_html__( 'Recent import logs', 'wc-optic' ) . '</h2><ul style="list-style:disc;padding-left:20px;">';
			foreach ( array_slice( $logs, 0, 20 ) as $log ) {
				echo '<li><code>' . esc_html( isset( $log['time'] ) ? $log['time'] : '' ) . '</code> — ';
				echo esc_html( isset( $log['message'] ) ? $log['message'] : '' );
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Process uploaded file.
	 *
	 * @return array{error:bool,message:string}|null
	 */
	protected static function process_import() {
		if ( empty( $_FILES['wc_optic_file']['tmp_name'] ) ) {
			return array(
				'error'   => true,
				'message' => __( 'No file uploaded.', 'wc-optic' ),
			);
		}

		$file = $_FILES['wc_optic_file']['tmp_name'];
		$name = isset( $_FILES['wc_optic_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['wc_optic_file']['name'] ) ) : 'import';

		$map = array();
		foreach ( array( 'term_type', 'name', 'slug', 'sku_fragment', 'sort_order' ) as $f ) {
			$key          = 'map_' . $f;
			$map[ $f ]    = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}

		if ( '' === $map['term_type'] || '' === $map['name'] ) {
			return array(
				'error'   => true,
				'message' => __( 'Map at least type and name columns.', 'wc-optic' ),
			);
		}

		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$rows = array();
		if ( 'csv' === $ext || 'txt' === $ext ) {
			$rows = self::parse_csv( $file );
		} elseif ( 'xlsx' === $ext ) {
			$rows = self::parse_xlsx( $file );
			if ( is_wp_error( $rows ) ) {
				return array(
					'error'   => true,
					'message' => $rows->get_error_message(),
				);
			}
		} else {
			return array(
				'error'   => true,
				'message' => __( 'Unsupported file type. Use CSV or XLSX.', 'wc-optic' ),
			);
		}

		$inserted = 0;
		$skipped  = 0;
		foreach ( $rows as $r ) {
			$type = self::map_cell( $r, $map['term_type'] );
			$nm   = self::map_cell( $r, $map['name'] );
			if ( '' === $type || '' === $nm ) {
				continue;
			}
			$type = strtolower( sanitize_key( $type ) );
			if ( ! WC_Optic_Catalog::is_valid_type( $type ) ) {
				++$skipped;
				continue;
			}
			$slug = self::map_cell( $r, $map['slug'] );
			$slug = $slug ? sanitize_title( $slug ) : sanitize_title( $nm );
			$frag = self::map_cell( $r, $map['sku_fragment'] );
			$sort = self::map_cell( $r, $map['sort_order'] );
			$sort = '' !== $sort ? (int) $sort : 0;

			if ( WC_Optic_Catalog::get_by_slug( $type, $slug ) ) {
				++$skipped;
				continue;
			}
			$res = WC_Optic_Catalog::insert( $type, $nm, $slug, $frag, $sort );
			if ( $res ) {
				++$inserted;
			} else {
				++$skipped;
			}
		}

		$msg = sprintf(
			/* translators: 1: inserted count, 2: skipped count */
			__( 'Import finished: %1$d inserted, %2$d skipped (duplicates or invalid).', 'wc-optic' ),
			$inserted,
			$skipped
		);
		self::add_log(
			array(
				'message' => $msg . ' (' . $name . ')',
			)
		);

		return array(
			'error'   => false,
			'message' => $msg,
		);
	}

	/**
	 * Parse CSV to 0-indexed row arrays.
	 *
	 * @param string $path Path.
	 * @return array<int, array<int, string>>
	 */
	protected static function parse_csv( $path ) {
		$rows = array();
		if ( ( $h = fopen( $path, 'r' ) ) !== false ) {
			while ( ( $data = fgetcsv( $h, 10000, ',' ) ) !== false ) {
				$rows[] = $data;
			}
			fclose( $h );
		}
		return $rows;
	}

	/**
	 * Parse XLSX via PhpSpreadsheet.
	 *
	 * @param string $path Path.
	 * @return array<int, array<int, string>>|WP_Error
	 */
	protected static function parse_xlsx( $path ) {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return new WP_Error(
				'wc_optic_xlsx',
				__( 'XLSX support requires Composer dependencies. Run composer install in the plugin folder, or use CSV.', 'wc-optic' )
			);
		}
		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path );
			$sheet       = $spreadsheet->getActiveSheet();
			$rows        = array();
			foreach ( $sheet->getRowIterator() as $row ) {
				$cells = array();
				foreach ( $row->getCellIterator() as $cell ) {
					$cells[] = (string) $cell->getValue();
				}
				$rows[] = $cells;
			}
			return $rows;
		} catch ( Exception $e ) {
			return new WP_Error( 'wc_optic_xlsx_read', $e->getMessage() );
		}
	}

	/**
	 * Resolve mapping token to cell value.
	 *
	 * @param array  $row Row (0-indexed columns).
	 * @param string $map Map token like "A" or "0".
	 * @return string
	 */
	protected static function map_cell( array $row, $map ) {
		$map = trim( (string) $map );
		if ( '' === $map ) {
			return '';
		}
		if ( preg_match( '/^[A-Za-z]+$/', $map ) ) {
			$idx = self::excel_col_to_index( strtoupper( $map ) );
		} else {
			$idx = (int) $map;
		}
		return isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
	}

	/**
	 * Excel column letters to 0-based index.
	 *
	 * @param string $col Column letters.
	 * @return int
	 */
	protected static function excel_col_to_index( $col ) {
		$col = preg_replace( '/[^A-Z]/', '', $col );
		$len = strlen( $col );
		$idx = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$idx = $idx * 26 + ( ord( $col[ $i ] ) - 64 );
		}
		return max( 0, $idx - 1 );
	}
}
