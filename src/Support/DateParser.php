<?php
/**
 * Converts a site-timezone date/time pair into a UTC Unix timestamp.
 *
 * Shared by the event editor save path and the calendar quick-create endpoint
 * so both produce identical stored values.
 *
 * @package VE_Events
 */

namespace VEV\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure date/time → UTC timestamp conversion.
 */
final class DateParser {

	/**
	 * Convert a date/time pair in the site timezone to a UTC timestamp.
	 *
	 * All-day events clamp to 00:00:00 (start) / 23:59:59 (end) of the local day.
	 *
	 * @param string        $date     Date string (Y-m-d).
	 * @param string        $time     Time string (H:i); ignored for all-day.
	 * @param bool          $all_day  Whether this is an all-day event.
	 * @param bool          $is_start Whether this is the start (vs. end) value.
	 * @param \DateTimeZone $tz       Site timezone.
	 * @return int UTC timestamp, or 0 when the date is empty/invalid.
	 */
	public static function to_utc( string $date, string $time, bool $all_day, bool $is_start, \DateTimeZone $tz ): int {
		if ( '' === $date ) {
			return 0;
		}

		try {
			if ( $all_day ) {
				$clock = $is_start ? '00:00:00' : '23:59:59';
				$dt    = new \DateTimeImmutable( $date . ' ' . $clock, $tz );
			} else {
				$clean_time = '' !== $time ? $time : '00:00';
				$dt         = new \DateTimeImmutable( $date . ' ' . $clean_time, $tz );
			}

			return (int) $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'U' );
		} catch ( \Exception $e ) {
			return 0;
		}
	}
}
