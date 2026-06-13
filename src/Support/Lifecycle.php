<?php
/**
 * Single source of truth for the event lifecycle status
 * (upcoming / ongoing / past / archived) and the grace-period cutoff.
 *
 * Consolidates logic that was previously duplicated across the frontend status
 * helper and two places in the query layer.
 *
 * @package VE_Events
 */

namespace VEV\Support;

use VEV\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the event lifecycle status and the grace-period cutoff.
 */
final class Lifecycle {

	/**
	 * Grace period (in seconds) during which an ended event still counts as
	 * "past" rather than "archived".
	 */
	public static function grace_seconds(): int {
		$settings     = Settings::get();
		$grace_period = absint( $settings['grace_period'] ?? 24 );
		return $grace_period * HOUR_IN_SECONDS;
	}

	/**
	 * Archive cutoff timestamp: events whose end is before this are archived.
	 *
	 * @param int|null $now Reference timestamp (defaults to time()). Pass a
	 *                      shared value to keep results consistent within one
	 *                      query pass.
	 */
	public static function cutoff( ?int $now = null ): int {
		$now = $now ?? time();
		return $now - self::grace_seconds();
	}

	/**
	 * Determine an event's lifecycle status.
	 *
	 * @param int      $start_utc     Start timestamp (UTC).
	 * @param int      $end_utc       End timestamp (UTC).
	 * @param int|null $now           Reference timestamp (defaults to time()).
	 * @param int|null $grace_seconds Grace window (defaults to settings value).
	 */
	public static function status( int $start_utc, int $end_utc, ?int $now = null, ?int $grace_seconds = null ): string {
		if ( ! $start_utc ) {
			return 'upcoming';
		}
		if ( ! $end_utc ) {
			$end_utc = $start_utc;
		}

		$now = $now ?? time();

		if ( $now < $start_utc ) {
			return 'upcoming';
		}

		if ( $now <= $end_utc ) {
			return 'ongoing';
		}

		$grace_seconds = $grace_seconds ?? self::grace_seconds();

		if ( $now <= ( $end_utc + $grace_seconds ) ) {
			return 'past';
		}

		return 'archived';
	}

	/**
	 * Human-readable label for a lifecycle status.
	 *
	 * @param string $status Lifecycle status key.
	 */
	public static function label( string $status ): string {
		return match ( $status ) {
			'ongoing'  => __( 'Ongoing', 've-events' ),
			'past'     => __( 'Past', 've-events' ),
			'archived' => __( 'Archived', 've-events' ),
			default    => __( 'Upcoming', 've-events' ),
		};
	}
}
