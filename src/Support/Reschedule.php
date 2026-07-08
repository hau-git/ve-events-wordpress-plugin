<?php
/**
 * DST-safe rescheduling of an event to a new start date.
 *
 * @package VE_Events
 */

namespace VEV\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shifts an event's start/end timestamps to a new calendar date while
 * preserving the local wall-clock time and the event's day-span.
 */
final class Reschedule {

	/**
	 * Move an event so its start falls on $target_date, keeping the local
	 * wall-clock time of both start and end and the number of days between them.
	 *
	 * Works unchanged for all-day events (whose bounds are local 00:00:00 /
	 * 23:59:59) and for multi-day events. The day delta is computed on
	 * timezone-free calendar dates, so DST transitions never distort it.
	 *
	 * @param int           $start_utc   Current start timestamp (UTC).
	 * @param int           $end_utc     Current end timestamp (UTC); 0 → end = start.
	 * @param string        $target_date Target start date (Y-m-d, site timezone).
	 * @param \DateTimeZone $tz          Site timezone.
	 * @return array{start:int,end:int}
	 */
	public static function shift_to_date( int $start_utc, int $end_utc, string $target_date, \DateTimeZone $tz ): array {
		if ( $start_utc <= 0 ) {
			return array(
				'start' => 0,
				'end'   => 0,
			);
		}

		$utc = new \DateTimeZone( 'UTC' );

		$old_start_local = ( new \DateTimeImmutable( '@' . $start_utc ) )->setTimezone( $tz );

		// New start: same wall-clock time, on the target date.
		$new_start_local = new \DateTimeImmutable(
			$target_date . ' ' . $old_start_local->format( 'H:i:s' ),
			$tz
		);
		$new_start       = (int) $new_start_local->setTimezone( $utc )->format( 'U' );

		if ( $end_utc <= 0 ) {
			return array(
				'start' => $new_start,
				'end'   => $new_start,
			);
		}

		// Signed day delta between the old and new start dates, computed on
		// DST-free calendar dates so full-day counts are always exact.
		$old_date_only = new \DateTimeImmutable( $old_start_local->format( 'Y-m-d' ), $utc );
		$new_date_only = new \DateTimeImmutable( $new_start_local->format( 'Y-m-d' ), $utc );
		$day_delta     = (int) $old_date_only->diff( $new_date_only )->format( '%r%a' );

		// Apply the same day shift to the end, preserving its wall-clock time.
		$old_end_local = ( new \DateTimeImmutable( '@' . $end_utc ) )->setTimezone( $tz );
		$new_end_local = $old_end_local->modify( sprintf( '%+d days', $day_delta ) );
		$new_end       = (int) $new_end_local->setTimezone( $utc )->format( 'U' );

		return array(
			'start' => $new_start,
			'end'   => $new_end,
		);
	}
}
