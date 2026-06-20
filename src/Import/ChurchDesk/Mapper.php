<?php
/**
 * ChurchDesk Field Mapper — maps a canonical ChurchDesk event to a normalised row.
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a ChurchDesk event (canonical v3.0.0 shape) onto the normalised row that
 * {@see \VEV\Import\AbstractRunner} consumes.
 *
 * Pure/stateless — it performs no I/O, so it can be unit-tested without a WP
 * runtime or HTTP access.
 */
class Mapper {

	/**
	 * Default ChurchDesk image format requested/used when none is configured.
	 */
	const DEFAULT_IMAGE_FORMAT = 'span7_16-9';

	/**
	 * ChurchDesk palette index → hex colour for category/term colours.
	 * The public API exposes colours as small integers; this is a sensible,
	 * stable approximation written to the `ve_category_color` term meta on
	 * term creation. Unknown indices fall back to a neutral grey.
	 */
	const COLOR_MAP = array(
		0 => '#3a87ad',
		1 => '#468847',
		2 => '#f89406',
		3 => '#b94a48',
		4 => '#999999',
		5 => '#6f5499',
		6 => '#d6487e',
		7 => '#2f96b4',
		8 => '#c09853',
		9 => '#0e8a16',
	);

	/**
	 * Maps a single canonical ChurchDesk event to a normalised row.
	 *
	 * @param  array $event  Canonical ChurchDesk event (decoded JSON object).
	 * @param  array $config Full feed config.
	 * @return array|null    Normalised row, or null when the event has no id/start.
	 */
	public static function map( array $event, array $config ): ?array {
		$id = isset( $event['id'] ) ? (string) $event['id'] : '';
		if ( '' === $id ) {
			return null;
		}

		$start_ts = self::iso_to_utc( $event['startDate'] ?? null );
		if ( ! $start_ts ) {
			return null;
		}
		$end_ts = self::iso_to_utc( $event['endDate'] ?? null ) ?? $start_ts;

		$is_all_day = ! empty( $event['allDay'] );

		// --- Post data ---
		$post_data = array( 'post_status' => $config['post_status'] ?? 'publish' );

		$title                   = isset( $event['title'] ) ? sanitize_text_field( $event['title'] ) : '';
		$post_data['post_title'] = '' !== $title ? $title : __( '(Untitled Event)', 've-events' );

		if ( ! empty( $event['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $event['description'] );
		}
		if ( ! empty( $event['summary'] ) ) {
			$post_data['post_excerpt'] = wp_strip_all_tags( $event['summary'] );
		}

		// --- Meta ---
		$meta = array(
			'_vev_start_utc' => $start_ts,
			'_vev_end_utc'   => $end_ts,
			'_vev_all_day'   => $is_all_day ? 1 : 0,
			'_vev_hide_end'  => self::hide_end( $event ),
		);

		if ( ! empty( $event['contributor'] ) ) {
			$meta['_vev_speaker'] = sanitize_text_field( $event['contributor'] );
		}
		if ( isset( $event['price'] ) && '' !== trim( (string) $event['price'] ) ) {
			$meta['_vev_special_info'] = sanitize_text_field( (string) $event['price'] );
		}

		// --- Taxonomies ---
		$taxonomies = array();
		self::map_categories( $event, $taxonomies );
		self::map_location( $event, $taxonomies );

		return array(
			'uid'            => $id,
			'post_data'      => $post_data,
			'meta'           => $meta,
			'taxonomies'     => $taxonomies,
			'import_hash'    => self::hash( $event ),
			'last_modified'  => ! empty( $event['updatedAt'] ) ? (int) strtotime( (string) $event['updatedAt'] ) : 0,
			'image_url'      => self::image_url( $event, $config ),
			'series_term_id' => null,
			'force_draft'    => false,
		);
	}

	// -------------------------------------------------------------------------
	// Field helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolves the `_vev_hide_end` flag from ChurchDesk's showEndtime/hideEndTime.
	 *
	 * @param array $event Canonical ChurchDesk event.
	 */
	private static function hide_end( array $event ): int {
		if ( array_key_exists( 'showEndtime', $event ) && null !== $event['showEndtime'] ) {
			return filter_var( $event['showEndtime'], FILTER_VALIDATE_BOOLEAN ) ? 0 : 1;
		}
		if ( array_key_exists( 'hideEndTime', $event ) && null !== $event['hideEndTime'] ) {
			return filter_var( $event['hideEndTime'], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}
		return 0;
	}

	/**
	 * Maps the categories array onto the ve_event_category taxonomy, carrying
	 * each category's colour as term meta to apply on term creation.
	 *
	 * @param array $event      Canonical ChurchDesk event.
	 * @param array $taxonomies Taxonomy map (passed by reference).
	 */
	private static function map_categories( array $event, array &$taxonomies ): void {
		if ( empty( $event['categories'] ) || ! is_array( $event['categories'] ) ) {
			return;
		}

		$terms     = array();
		$term_meta = array();

		foreach ( $event['categories'] as $cat ) {
			// API uses `title` in the list endpoint and `name` in the single endpoint.
			$name = sanitize_text_field( $cat['title'] ?? $cat['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$terms[] = $name;

			if ( isset( $cat['color'] ) ) {
				$term_meta[ $name ] = array(
					Constants::TERM_META_CATEGORY_COLOR => self::color_to_hex( (int) $cat['color'] ),
				);
			}
		}

		if ( $terms ) {
			$taxonomies[ Constants::TAX_CATEGORY ] = array(
				'terms'        => $terms,
				'create_terms' => true,
				'term_meta'    => $term_meta,
			);
		}
	}

	/**
	 * Maps the location name onto the ve_event_location taxonomy, carrying the
	 * composed address as term meta to apply on term creation.
	 *
	 * @param array $event      Canonical ChurchDesk event.
	 * @param array $taxonomies Taxonomy map (passed by reference).
	 */
	private static function map_location( array $event, array &$taxonomies ): void {
		$name = sanitize_text_field( $event['locationName'] ?? '' );
		if ( '' === $name ) {
			return;
		}

		$term_meta = array();
		$address   = self::compose_address( $event );
		if ( '' !== $address ) {
			$term_meta[ $name ] = array(
				Constants::TERM_META_LOCATION_ADDRESS => $address,
			);
		}

		$taxonomies[ Constants::TAX_LOCATION ] = array(
			'terms'        => array( $name ),
			'create_terms' => true,
			'term_meta'    => $term_meta,
		);
	}

	/**
	 * Composes a human-readable address from locationObj or the location string.
	 *
	 * @param array $event Canonical ChurchDesk event.
	 */
	private static function compose_address( array $event ): string {
		$obj = $event['locationObj'] ?? null;
		if ( is_array( $obj ) ) {
			$line  = trim( (string) ( $obj['address'] ?? '' ) );
			$city  = trim( (string) ( $obj['zipcode'] ?? '' ) . ' ' . (string) ( $obj['city'] ?? '' ) );
			$parts = array_filter( array( $line, trim( $city ), trim( (string) ( $obj['country'] ?? '' ) ) ) );
			if ( $parts ) {
				return sanitize_text_field( implode( ', ', $parts ) );
			}
		}

		return isset( $event['location'] ) ? sanitize_text_field( (string) $event['location'] ) : '';
	}

	/**
	 * Picks the featured-image URL for the configured image format.
	 *
	 * @param array $event  Canonical ChurchDesk event.
	 * @param array $config Full feed config.
	 */
	private static function image_url( array $event, array $config ): ?string {
		if ( empty( $config['cd_import_image'] ) || empty( $event['image'] ) || ! is_array( $event['image'] ) ) {
			return null;
		}

		$format = $config['cd_image_format'] ?? self::DEFAULT_IMAGE_FORMAT;
		$image  = $event['image'];

		if ( ! empty( $image[ $format ] ) && is_string( $image[ $format ] ) ) {
			return esc_url_raw( $image[ $format ] );
		}

		// Fall back to the first URL-looking string in the image object.
		foreach ( $image as $value ) {
			if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $value );
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Pure conversions (unit-testable without WP)
	// -------------------------------------------------------------------------

	/**
	 * Converts an ISO-8601 date/time (e.g. "2015-10-25T10:00:00Z") to a UTC
	 * Unix timestamp.
	 *
	 * @param  string|null $iso ISO-8601 string.
	 * @return int|null
	 */
	public static function iso_to_utc( ?string $iso ): ?int {
		$iso = is_string( $iso ) ? trim( $iso ) : '';
		if ( '' === $iso ) {
			return null;
		}

		try {
			$dt = new \DateTime( $iso, new \DateTimeZone( 'UTC' ) );
			$dt->setTimezone( new \DateTimeZone( 'UTC' ) );
			return (int) $dt->format( 'U' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Maps a ChurchDesk palette index to a hex colour.
	 *
	 * @param int $color Palette index.
	 */
	public static function color_to_hex( int $color ): string {
		return self::COLOR_MAP[ $color ] ?? '#999999';
	}

	/**
	 * Builds the change-detection hash for an event.
	 *
	 * @param array $event Canonical ChurchDesk event.
	 */
	public static function hash( array $event ): string {
		$input = implode(
			'|',
			array(
				(string) ( $event['id'] ?? '' ),
				(string) ( $event['startDate'] ?? '' ),
				(string) ( $event['endDate'] ?? '' ),
				(string) ( $event['title'] ?? '' ),
				(string) ( $event['description'] ?? '' ),
				(string) ( $event['location'] ?? '' ),
				(string) ( $event['updatedAt'] ?? '' ),
			)
		);

		return md5( $input );
	}
}
