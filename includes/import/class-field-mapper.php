<?php
/**
 * Field Mapper — maps ICS event fields to VE Events post/meta/taxonomy fields.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Field_Mapper {

	/**
	 * All possible mapping targets keyed by identifier.
	 * Used to build the admin UI dropdowns.
	 */
	public static function get_targets(): array {
		return array(
			// Post fields
			'post_title'         => __( 'Event Title',              've-events' ),
			'post_content'       => __( 'Description (Content)',    've-events' ),
			'post_excerpt'       => __( 'Excerpt',                  've-events' ),
			// Core meta
			'_vev_start_utc'     => __( 'Start Date/Time (internal)', 've-events' ),
			'_vev_end_utc'       => __( 'End Date/Time (internal)',   've-events' ),
			'_vev_speaker'       => __( 'Speaker / Host',             've-events' ),
			'_vev_special_info'  => __( 'Special Information',        've-events' ),
			'_vev_info_url'      => __( 'Info / Ticket URL',          've-events' ),
			// Taxonomies
			've_event_category'  => __( 'Category (Taxonomy)',        've-events' ),
			've_event_location'  => __( 'Location (Taxonomy)',        've-events' ),
			've_event_topic'     => __( 'Topic (Taxonomy)',           've-events' ),
			// series is handled automatically from RRULE — not a manual target
		);
	}

	/**
	 * Source ICS fields available for mapping.
	 */
	public static function get_sources(): array {
		return array(
			'summary'      => 'SUMMARY',
			'description'  => 'DESCRIPTION',
			'location'     => 'LOCATION',
			'url'          => 'URL',
			'organizer'    => 'ORGANIZER (CN)',
			'categories'   => 'CATEGORIES',
			'status'       => 'STATUS',
		);
	}

	/**
	 * Maps a single ICS event object to an array ready for wp_insert_post / meta.
	 *
	 * @param  \VEV_Import\Event $event      Parsed ICS event.
	 * @param  array             $field_map  Feed field map config.
	 * @param  array             $config     Full feed config.
	 * @return array {
	 *     @type array  $post_data     wp_insert_post / wp_update_post args.
	 *     @type array  $meta          post_meta key => value pairs.
	 *     @type array  $taxonomies    taxonomy => [term names].
	 *     @type bool   $is_all_day
	 *     @type string $import_hash   MD5 of key fields for change detection.
	 * }
	 */
	public static function map( \VEV_Import\Event $event, array $field_map, array $config ): array {
		$post_data  = array( 'post_status' => $config['post_status'] ?? 'publish' );
		$meta       = array();
		$taxonomies = array();
		$is_all_day = false;

		// --- Dates (always mapped, not user-configurable) ---
		$start_ts = self::ics_date_to_utc( $event->dtstart_array ?? array(), $event->dtstart ?? '' );
		$end_ts   = self::ics_date_to_utc( $event->dtend_array   ?? array(), $event->dtend   ?? '' );

		// Detect all-day: dtstart is date-only (no time component)
		if ( self::is_date_only( $event->dtstart ?? '' ) ) {
			$is_all_day = true;
			// All-day end is exclusive in ICS (next day 00:00) → subtract 1 second
			if ( $end_ts ) {
				$end_ts -= 1;
			}
		}

		if ( $start_ts ) {
			$meta['_vev_start_utc'] = $start_ts;
		}
		if ( $end_ts ) {
			$meta['_vev_end_utc'] = $end_ts;
		}
		$meta['_vev_all_day'] = $is_all_day ? 1 : 0;

		// --- User-configurable field mapping ---
		foreach ( $field_map as $source => $map_config ) {
			if ( empty( $map_config['enabled'] ) ) {
				continue;
			}

			$target       = $map_config['target']       ?? '';
			$create_terms = $map_config['create_terms'] ?? true;

			if ( ! $target ) {
				continue;
			}

			$value = self::get_source_value( $event, $source );

			if ( $value === null || $value === '' ) {
				continue;
			}

			// Route to post_data, meta or taxonomy
			if ( in_array( $target, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
				$post_data[ $target ] = $value;
			} elseif ( taxonomy_exists( $target ) ) {
				$taxonomies[ $target ] = array(
					'terms'        => is_array( $value ) ? $value : array( $value ),
					'create_terms' => $create_terms,
				);
			} else {
				// Meta field
				$meta[ $target ] = $value;
			}
		}

		// Fallback title
		if ( empty( $post_data['post_title'] ) ) {
			$post_data['post_title'] = __( '(Untitled Event)', 've-events' );
		}

		// Import hash for change detection
		$hash_input = implode( '|', array(
			$event->summary      ?? '',
			$event->dtstart      ?? '',
			$event->dtend        ?? '',
			$event->location     ?? '',
			$event->description  ?? '',
			$event->url          ?? '',
			$event->organizer    ?? '',
			$event->last_modified ?? '',
		) );

		return array(
			'post_data'   => $post_data,
			'meta'        => $meta,
			'taxonomies'  => $taxonomies,
			'is_all_day'  => $is_all_day,
			'import_hash' => md5( $hash_input ),
		);
	}

	// -------------------------------------------------------------------------
	// Source value extraction
	// -------------------------------------------------------------------------

	/**
	 * Extracts the value for a given source key from an ICS event.
	 *
	 * @param  \VEV_Import\Event $event
	 * @param  string            $source  e.g. 'summary', 'organizer', 'categories'
	 * @return string|array|null
	 */
	private static function get_source_value( \VEV_Import\Event $event, string $source ): string|array|null {
		switch ( $source ) {
			case 'summary':
				return $event->summary ? wp_strip_all_tags( $event->summary ) : null;

			case 'description':
				return $event->description ? wp_kses_post( nl2br( $event->description ) ) : null;

			case 'location':
				return $event->location ? sanitize_text_field( $event->location ) : null;

			case 'url':
				$url = $event->url ?? null;
				return ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) ? esc_url_raw( $url ) : null;

			case 'organizer':
				// ICS organizer: "CN=John Doe:mailto:john@example.com" or just "mailto:john@example.com"
				$org = $event->organizer ?? '';
				if ( ! $org ) {
					return null;
				}
				// Try to extract CN parameter
				if ( preg_match( '/CN=([^;:]+)/i', $org, $m ) ) {
					return sanitize_text_field( trim( $m[1], '"' ) );
				}
				// Strip mailto: prefix
				$org = preg_replace( '/^mailto:/i', '', $org );
				return sanitize_text_field( $org );

			case 'categories':
				$cats = $event->categories ?? null;
				if ( ! $cats ) {
					return null;
				}
				// May be comma-separated string
				if ( is_string( $cats ) ) {
					$cats = array_map( 'trim', explode( ',', $cats ) );
				}
				return array_filter( array_map( 'sanitize_text_field', (array) $cats ) );

			case 'status':
				return $event->status ? strtoupper( $event->status ) : null;

			default:
				return null;
		}
	}

	// -------------------------------------------------------------------------
	// Date helpers
	// -------------------------------------------------------------------------

	/**
	 * Converts an ICS dtstart/dtend to a UTC Unix timestamp.
	 *
	 * The parser provides dtstart_array = [ params_array, value_string, timestamp_int, ... ]
	 *
	 * @param  array  $date_array  dtstart_array or dtend_array from the parser.
	 * @param  string $date_string Raw ICS string (fallback).
	 * @return int|null
	 */
	public static function ics_date_to_utc( array $date_array, string $date_string ): ?int {
		// Parser stores a Unix timestamp at index 2
		if ( isset( $date_array[2] ) && is_numeric( $date_array[2] ) ) {
			return (int) $date_array[2];
		}

		// Fallback: parse the raw string
		if ( ! $date_string ) {
			return null;
		}

		try {
			// Extract TZID if present in the array params (index 0)
			$tz_string = $date_array[0]['TZID'] ?? 'UTC';
			$tz        = new DateTimeZone( $tz_string );
		} catch ( \Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}

		// DATE-only: 20250115
		if ( preg_match( '/^\d{8}$/', $date_string ) ) {
			try {
				$dt = new DateTime( $date_string, new DateTimeZone( 'UTC' ) );
				return (int) $dt->format( 'U' );
			} catch ( \Exception $e ) {
				return null;
			}
		}

		// DATE-TIME: 20250115T143000 or 20250115T143000Z
		$clean = rtrim( $date_string, 'Z' );
		try {
			$is_utc = str_ends_with( $date_string, 'Z' );
			$dt     = new DateTime( $clean, $is_utc ? new DateTimeZone( 'UTC' ) : $tz );
			$dt->setTimezone( new DateTimeZone( 'UTC' ) );
			return (int) $dt->format( 'U' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Returns true if the ICS date string is date-only (no time component).
	 */
	public static function is_date_only( string $date_string ): bool {
		return (bool) preg_match( '/^\d{8}$/', $date_string );
	}
}
