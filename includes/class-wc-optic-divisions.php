<?php
/**
 * Configurable optical divisions (label + associated prescription powers).
 *
 * @package WC_Optic_Product
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Optic_Divisions
 */
class WC_Optic_Divisions {

	const OPTION_KEY = 'wc_optic_divisions';

	/**
	 * Built-in division definitions used when nothing is stored yet.
	 *
	 * @return array<string, array{label:string, powers:string[]}>
	 */
	public static function get_default_divisions() {
		return array(
			'color_lenses'       => array(
				'label'  => __( 'Color lenses', 'wc-optic' ),
				'powers' => array( 'sph' ),
			),
			'sama_color_lenses'  => array(
				'label'  => __( 'SAMA Color Lenses', 'wc-optic' ),
				'powers' => array( 'sph', 'cyl', 'axis' ),
			),
			'astigmatism_toric'  => array(
				'label'  => __( 'Astigmatism Toric', 'wc-optic' ),
				'powers' => array( 'sph', 'cyl', 'axis' ),
			),
			'multifocal_bifocal' => array(
				'label'  => __( 'Multifocal Bifocal', 'wc-optic' ),
				'powers' => array( 'sph', 'add' ),
			),
		);
	}

	/**
	 * All configured divisions keyed by slug.
	 *
	 * @return array<string, array{label:string, powers:string[]}>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return self::get_default_divisions();
		}

		$out = array();
		foreach ( $stored as $slug => $def ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! is_array( $def ) ) {
				continue;
			}
			$label = isset( $def['label'] ) ? sanitize_text_field( (string) $def['label'] ) : '';
			if ( '' === trim( $label ) ) {
				continue;
			}
			$powers = self::sanitize_powers( isset( $def['powers'] ) ? $def['powers'] : array() );
			if ( empty( $powers ) ) {
				continue;
			}
			$out[ $slug ] = array(
				'label'  => $label,
				'powers' => $powers,
			);
		}

		if ( empty( $out ) ) {
			return self::get_default_divisions();
		}

		foreach ( $out as $slug => $def ) {
			$out[ $slug ]['label'] = apply_filters( 'wc_optic_division_label', $def['label'], $slug );
		}

		return $out;
	}

	/**
	 * Persist divisions (raw structure without WPML filter on labels).
	 *
	 * @param array<string, array{label:string, powers:string[]}> $divisions Divisions.
	 * @return bool
	 */
	public static function save( array $divisions ) {
		$clean = array();
		foreach ( $divisions as $slug => $def ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! is_array( $def ) ) {
				continue;
			}
			$label = isset( $def['label'] ) ? sanitize_text_field( (string) $def['label'] ) : '';
			if ( '' === trim( $label ) ) {
				continue;
			}
			$powers = self::sanitize_powers( isset( $def['powers'] ) ? $def['powers'] : array() );
			if ( empty( $powers ) ) {
				continue;
			}
			$clean[ $slug ] = array(
				'label'  => $label,
				'powers' => $powers,
			);
		}

		if ( empty( $clean ) ) {
			return false;
		}

		return update_option( self::OPTION_KEY, $clean, false );
	}

	/**
	 * Seed defaults on first install if option is missing.
	 */
	public static function maybe_seed_defaults() {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}
		self::save( self::get_default_divisions() );
	}

	/**
	 * Allowed prescription power slugs for divisions.
	 *
	 * @return string[]
	 */
	public static function get_available_powers() {
		return WC_Optic_Catalog::get_power_types();
	}

	/**
	 * Human label for a power slug.
	 *
	 * @param string $power Power slug.
	 * @return string
	 */
	public static function get_power_label( $power ) {
		return WC_Optic_Catalog::get_type_label( $power );
	}

	/**
	 * Normalize and order power slugs.
	 *
	 * @param mixed $powers Raw power list.
	 * @return string[]
	 */
	public static function sanitize_powers( $powers ) {
		if ( ! is_array( $powers ) ) {
			return array();
		}
		$allowed = self::get_available_powers();
		$set     = array();
		foreach ( $powers as $power ) {
			$power = sanitize_key( (string) $power );
			if ( in_array( $power, $allowed, true ) ) {
				$set[ $power ] = true;
			}
		}
		$ordered = array();
		foreach ( $allowed as $power ) {
			if ( isset( $set[ $power ] ) ) {
				$ordered[] = $power;
			}
		}
		return $ordered;
	}

	/**
	 * Parse division rows from settings form POST.
	 *
	 * @param array $rows Posted rows keyed by suffix.
	 * @return array{divisions: array<string, array{label:string, powers:string[]}>, skipped_incomplete: int, skipped_duplicate: int}
	 */
	public static function parse_form_rows( array $rows ) {
		$divisions          = array();
		$skipped_incomplete = 0;
		$skipped_duplicate  = 0;
		$labels_seen        = array();

		foreach ( $rows as $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$label  = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : '';
			$slug   = isset( $data['slug'] ) ? sanitize_key( (string) $data['slug'] ) : '';
			$powers = self::sanitize_powers( isset( $data['powers'] ) ? $data['powers'] : array() );

			if ( '' === trim( $label ) && empty( $powers ) ) {
				continue;
			}

			if ( '' === trim( $label ) || empty( $powers ) ) {
				++$skipped_incomplete;
				continue;
			}

			if ( '' === $slug ) {
				$slug = WC_Optic_Catalog::sanitize_slug( $label );
			}
			if ( '' === $slug ) {
				$slug = 'division-' . strtolower( wp_unique_id() );
			}

			$label_key = strtolower( $label );
			if ( isset( $labels_seen[ $label_key ] ) ) {
				++$skipped_duplicate;
				continue;
			}
			$labels_seen[ $label_key ] = true;

			if ( isset( $divisions[ $slug ] ) ) {
				$slug = $slug . '-' . strtolower( wp_unique_id() );
			}

			$divisions[ $slug ] = array(
				'label'  => $label,
				'powers' => $powers,
			);
		}

		return array(
			'divisions'          => $divisions,
			'skipped_incomplete' => $skipped_incomplete,
			'skipped_duplicate'  => $skipped_duplicate,
		);
	}

	/**
	 * Products that reference a division slug.
	 *
	 * @param string $division_slug Division slug.
	 * @return array<int, array{id:int, name:string, edit_url:string}>
	 */
	public static function find_products_using_division( $division_slug ) {
		$division_slug = sanitize_key( (string) $division_slug );
		if ( '' === $division_slug ) {
			return array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_optic_division'
				AND pm.meta_value = %s
				AND p.post_type = 'product'
				AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
				$division_slug
			)
		);

		$out = array();
		foreach ( $post_ids as $pid ) {
			$pid  = (int) $pid;
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$edit_url = get_edit_post_link( $pid, 'raw' );
			$out[]    = array(
				'id'       => $pid,
				'name'     => $post->post_title,
				'edit_url' => $edit_url ? $edit_url : '',
			);
		}

		return $out;
	}

	/**
	 * Division slugs removed by a save that still have products assigned.
	 *
	 * @param array<string, array{label:string, powers:string[]}> $new_divisions New config.
	 * @return array<string, array<int, array{id:int, name:string, edit_url:string}>>
	 */
	public static function find_blocked_removals( array $new_divisions ) {
		$current = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $current ) || empty( $current ) ) {
			$current = self::get_default_divisions();
		}

		$new_slugs = array_keys( $new_divisions );
		$blocked   = array();

		foreach ( array_keys( $current ) as $old_slug ) {
			if ( in_array( $old_slug, $new_slugs, true ) ) {
				continue;
			}
			$affected = self::find_products_using_division( $old_slug );
			if ( ! empty( $affected ) ) {
				$blocked[ $old_slug ] = $affected;
			}
		}

		return $blocked;
	}
}
