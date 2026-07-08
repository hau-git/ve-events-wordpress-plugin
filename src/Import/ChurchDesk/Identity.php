<?php
/**
 * ChurchDesk event identity helpers for cross-feed matching.
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure helpers to recognise the same ChurchDesk event across feeds.
 *
 * The ChurchDesk iCal export embeds the numeric event id in the `UID` (and in
 * the event `URL`), which is the same id the API returns — so an iCal-imported
 * event and an API-imported event can be matched and merged.
 */
class Identity {

	/**
	 * Extracts the ChurchDesk numeric event id from an iCal UID and/or URL.
	 *
	 * Handles UIDs such as `38523886@churchdesk.com`, `event-38523886@…`,
	 * `churchdesk-38523886@…`, and URLs like
	 * `https://landing.churchdesk.com/de/e/38523886/krabbelgruppe`.
	 *
	 * @param  string $uid iCal UID.
	 * @param  string $url iCal URL (optional).
	 * @return string|null Numeric id as string, or null when none is found.
	 */
	public static function from_ical_uid( string $uid, string $url = '' ): ?string {
		// Prefer an id that looks ChurchDesk-related in the UID.
		if ( preg_match( '/(?:event[-_]?|churchdesk[-_]?)?(\d{4,})@/i', $uid, $m ) ) {
			return $m[1];
		}

		// Fall back to a churchdesk URL containing the event id (…/e/<id>/…).
		if ( '' !== $url && false !== stripos( $url, 'churchdesk' ) && preg_match( '#/e/(\d{4,})#', $url, $m ) ) {
			return $m[1];
		}

		// Last resort: a long digit run anywhere in the UID.
		if ( preg_match( '/(\d{6,})/', $uid, $m ) ) {
			return $m[1];
		}

		return null;
	}

	/**
	 * Normalises a title for the fallback (start-time + title) match key.
	 *
	 * @param  string $title Raw title.
	 * @return string Lowercased, whitespace-collapsed title.
	 */
	public static function normalize_title( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = preg_replace( '/\s+/u', ' ', $title );
		return trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title ) );
	}
}
