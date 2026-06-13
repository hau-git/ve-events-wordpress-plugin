<?php
/**
 * Single source of truth for formatting event timestamps in the site timezone.
 *
 * Consolidates the wp_date()/get_option()/wp_timezone() pattern that was
 * previously reimplemented across the frontend helpers and the field registry.
 *
 * @package VE_Events
 */

namespace VEV\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats event timestamps in the site timezone.
 */
final class DateFormatter {

	/**
	 * Format a UTC timestamp as a date in the site timezone.
	 *
	 * @param int $ts_utc UTC timestamp.
	 */
	public static function date_only( int $ts_utc ): string {
		if ( ! $ts_utc ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ), $ts_utc, wp_timezone() );
	}

	/**
	 * Format a UTC timestamp as a time in the site timezone.
	 *
	 * @param int $ts_utc UTC timestamp.
	 */
	public static function time_only( int $ts_utc ): string {
		if ( ! $ts_utc ) {
			return '';
		}
		return wp_date( get_option( 'time_format' ), $ts_utc, wp_timezone() );
	}

	/**
	 * Smart date range; collapses to a single date when start and end fall on
	 * the same day.
	 *
	 * @param array{start_utc:int,end_utc:int} $data Event data.
	 */
	public static function date_range( array $data ): string {
		$start = (int) $data['start_utc'];
		$end   = (int) $data['end_utc'];

		if ( ! $start ) {
			return '';
		}
		if ( ! $end ) {
			$end = $start;
		}

		$tz          = wp_timezone();
		$date_format = get_option( 'date_format' );

		$start_date = wp_date( $date_format, $start, $tz );
		$end_date   = wp_date( $date_format, $end, $tz );

		if ( $start_date === $end_date ) {
			return $start_date;
		}

		return $start_date . ' – ' . $end_date;
	}

	/**
	 * Time range, or "All day". Honors the hide-end flag.
	 *
	 * @param array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool} $data Event data.
	 */
	public static function time_range( array $data ): string {
		if ( $data['all_day'] ) {
			return __( 'All day', 've-events' );
		}

		$start    = (int) $data['start_utc'];
		$end      = (int) $data['end_utc'];
		$hide_end = (bool) $data['hide_end'];

		if ( ! $start ) {
			return '';
		}

		$tz          = wp_timezone();
		$time_format = get_option( 'time_format' );

		$start_time = wp_date( $time_format, $start, $tz );

		if ( ! $end || $hide_end ) {
			return $start_time;
		}

		$end_time = wp_date( $time_format, $end, $tz );

		return $start_time . ' – ' . $end_time;
	}

	/**
	 * Full, human-readable date & time covering all-day and hide-end variants.
	 *
	 * @param array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool} $data Event data.
	 */
	public static function datetime_full( array $data ): string {
		$start    = (int) $data['start_utc'];
		$end      = (int) $data['end_utc'];
		$all_day  = (bool) $data['all_day'];
		$hide_end = (bool) $data['hide_end'];

		if ( ! $start ) {
			return '';
		}
		if ( ! $end ) {
			$end = $start;
		}

		$tz          = wp_timezone();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$start_date = wp_date( $date_format, $start, $tz );
		$end_date   = wp_date( $date_format, $end, $tz );

		if ( $all_day ) {
			if ( $start_date === $end_date ) {
				/* translators: %s: event date. */
				return sprintf( __( '%s (all day)', 've-events' ), $start_date );
			}
			/* translators: 1: start date, 2: end date. */
			return sprintf( __( '%1$s – %2$s (all day)', 've-events' ), $start_date, $end_date );
		}

		$start_time = wp_date( $time_format, $start, $tz );
		$end_time   = wp_date( $time_format, $end, $tz );

		if ( $start_date === $end_date ) {
			if ( $hide_end ) {
				/* translators: 1: date, 2: start time. */
				return sprintf( __( '%1$s, %2$s', 've-events' ), $start_date, $start_time );
			}
			/* translators: 1: date, 2: start time, 3: end time. */
			return sprintf( __( '%1$s, %2$s – %3$s', 've-events' ), $start_date, $start_time, $end_time );
		}

		if ( $hide_end ) {
			/* translators: 1: date, 2: start time. */
			return sprintf( __( '%1$s, %2$s', 've-events' ), $start_date, $start_time );
		}
		/* translators: 1: start date, 2: start time, 3: end date, 4: end time. */
		return sprintf( __( '%1$s %2$s – %3$s %4$s', 've-events' ), $start_date, $start_time, $end_date, $end_time );
	}

	/**
	 * ISO 8601 datetime for Schema.org / Open Graph. All-day events emit a
	 * date-only value.
	 *
	 * @param int                $ts_utc  UTC timestamp.
	 * @param bool               $all_day Whether the event is all-day.
	 * @param \DateTimeZone|null $tz     Timezone (defaults to site timezone).
	 */
	public static function schema( int $ts_utc, bool $all_day, ?\DateTimeZone $tz = null ): string {
		if ( ! $ts_utc ) {
			return '';
		}
		$tz = $tz ?? wp_timezone();

		if ( $all_day ) {
			return wp_date( 'Y-m-d', $ts_utc, $tz );
		}

		return wp_date( 'c', $ts_utc, $tz );
	}
}
