<?php
/**
 * Pure RFC 5545 iCalendar builder.
 *
 * Dependency-free (no WordPress calls) so the escaping, line-folding, all-day
 * exclusive-end, and STATUS mapping can be unit-tested in isolation.
 *
 * @package VE_Events
 */

namespace VEV\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles VCALENDAR / VEVENT text from plain event data arrays.
 */
final class IcsBuilder {

	private const CRLF = "\r\n";

	/**
	 * Wrap one or more VEVENT blocks in a VCALENDAR envelope.
	 *
	 * @param string[] $vevents       Rendered VEVENT blocks.
	 * @param string   $prodid        PRODID identifier.
	 * @param string   $name          Optional calendar display name (X-WR-CALNAME).
	 * @param int      $refresh_hours Optional refresh interval hint in hours.
	 */
	public static function calendar( array $vevents, string $prodid, string $name = '', int $refresh_hours = 0 ): string {
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:' . self::escape_text( $prodid ),
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
		);

		if ( '' !== $name ) {
			$lines[] = 'X-WR-CALNAME:' . self::escape_text( $name );
		}
		if ( $refresh_hours > 0 ) {
			$lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT' . $refresh_hours . 'H';
			$lines[] = 'X-PUBLISHED-TTL:PT' . $refresh_hours . 'H';
		}

		$folded = array_map( array( __CLASS__, 'fold' ), $lines );
		$body   = implode( self::CRLF, $folded ) . self::CRLF;

		foreach ( $vevents as $vevent ) {
			$body .= $vevent;
		}

		$body .= 'END:VCALENDAR' . self::CRLF;
		return $body;
	}

	/**
	 * Render a single VEVENT block (already folded, CRLF-terminated).
	 *
	 * Expected keys: uid (string), dtstamp/start/end (int UTC), all_day (bool),
	 * tz (DateTimeZone), and optional summary, description, location, url,
	 * status, organizer_name, organizer_url (all strings).
	 *
	 * @param array<string,mixed> $e Event data.
	 */
	public static function vevent( array $e ): string {
		$utc   = new \DateTimeZone( 'UTC' );
		$lines = array( 'BEGIN:VEVENT' );

		$lines[] = 'UID:' . self::escape_text( $e['uid'] );
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', $e['dtstamp'] );

		if ( ! empty( $e['all_day'] ) ) {
			$tz         = $e['tz'];
			$start_date = ( new \DateTimeImmutable( '@' . $e['start'] ) )->setTimezone( $tz );
			// Stored end is 23:59:59 of the last local day; DTEND is exclusive → +1 day.
			$end_local = ( new \DateTimeImmutable( '@' . $e['end'] ) )->setTimezone( $tz );
			$end_excl  = $end_local->modify( '+1 day' );
			$lines[]   = 'DTSTART;VALUE=DATE:' . $start_date->format( 'Ymd' );
			$lines[]   = 'DTEND;VALUE=DATE:' . $end_excl->format( 'Ymd' );
		} else {
			$lines[] = 'DTSTART:' . ( new \DateTimeImmutable( '@' . $e['start'] ) )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			$end     = $e['end'] > 0 ? $e['end'] : $e['start'];
			$lines[] = 'DTEND:' . ( new \DateTimeImmutable( '@' . $end ) )->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		}

		if ( ! empty( $e['summary'] ) ) {
			$lines[] = 'SUMMARY:' . self::escape_text( $e['summary'] );
		}
		if ( ! empty( $e['description'] ) ) {
			$lines[] = 'DESCRIPTION:' . self::escape_text( $e['description'] );
		}
		if ( ! empty( $e['location'] ) ) {
			$lines[] = 'LOCATION:' . self::escape_text( $e['location'] );
		}
		if ( ! empty( $e['url'] ) ) {
			$lines[] = 'URL:' . self::escape_text( $e['url'] );
		}

		$status = isset( $e['status'] ) ? self::status( $e['status'] ) : '';
		if ( '' !== $status ) {
			$lines[] = 'STATUS:' . $status;
		}

		// ORGANIZER requires a URI value; only emit when a URL is available.
		if ( ! empty( $e['organizer_url'] ) ) {
			$cn      = ! empty( $e['organizer_name'] ) ? ';CN=' . self::escape_param( $e['organizer_name'] ) : '';
			$lines[] = 'ORGANIZER' . $cn . ':' . $e['organizer_url'];
		}

		$lines[] = 'END:VEVENT';

		$folded = array_map( array( __CLASS__, 'fold' ), $lines );
		return implode( self::CRLF, $folded ) . self::CRLF;
	}

	/**
	 * Map the plugin's manual status override to an iCal STATUS value.
	 *
	 * @param string $vev_status Manual status override key.
	 */
	public static function status( string $vev_status ): string {
		return match ( $vev_status ) {
			'cancelled'                => 'CANCELLED',
			'postponed', 'rescheduled' => 'TENTATIVE',
			default                    => 'CONFIRMED',
		};
	}

	/**
	 * Escape a TEXT value per RFC 5545 (backslash, semicolon, comma, newlines).
	 *
	 * @param string $text Raw text.
	 */
	public static function escape_text( string $text ): string {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( array( ';', ',' ), array( '\\;', '\\,' ), $text );
		$text = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $text );
		return $text;
	}

	/**
	 * Escape a parameter value (e.g. CN) by stripping structural characters.
	 *
	 * @param string $text Raw text.
	 */
	public static function escape_param( string $text ): string {
		$text = str_replace( array( "\r", "\n" ), ' ', $text );
		$text = str_replace( array( '"', ';', ':', ',' ), ' ', $text );
		return '"' . trim( $text ) . '"';
	}

	/**
	 * Fold a content line to 75 octets per RFC 5545, multibyte-safe.
	 *
	 * @param string $line Content line.
	 */
	public static function fold( string $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$out       = '';
		$current   = '';
		$len       = mb_strlen( $line, 'UTF-8' );
		$max_first = 75;
		$max_cont  = 74; // Continuation lines start with a leading space.

		for ( $i = 0; $i < $len; $i++ ) {
			$char      = mb_substr( $line, $i, 1, 'UTF-8' );
			$limit     = '' === $out ? $max_first : $max_cont;
			$candidate = $current . $char;
			if ( strlen( $candidate ) > $limit ) {
				$out    .= ( '' === $out ? '' : self::CRLF . ' ' ) . $current;
				$current = $char;
			} else {
				$current = $candidate;
			}
		}

		if ( '' !== $current ) {
			$out .= ( '' === $out ? '' : self::CRLF . ' ' ) . $current;
		}

		return $out;
	}
}
