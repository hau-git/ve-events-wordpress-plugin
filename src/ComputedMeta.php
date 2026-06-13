<?php
/**
 * Keeps computed date meta in sync with the event start timestamp.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-computes hour, weekday, date, month, and time-slot meta from the start UTC.
 */
final class ComputedMeta {

	/**
	 * Hook the computed-meta sync into WordPress.
	 */
	public static function init(): void {
		add_action( 'added_post_meta', array( __CLASS__, 'sync' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'sync' ), 10, 3 );
	}

	/**
	 * Auto-compute hour-of-day and weekday meta whenever _vev_start_utc is written.
	 * Fires on both add and update, covering admin saves AND import runner.
	 *
	 * @param int    $meta_id  The meta ID.
	 * @param int    $post_id  The post ID.
	 * @param string $meta_key The meta key being written.
	 */
	public static function sync( $meta_id, int $post_id, string $meta_key ): void {
		if ( Constants::META_START_UTC !== $meta_key ) {
			return;
		}
		if ( Constants::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$start_utc = (int) get_post_meta( $post_id, Constants::META_START_UTC, true );
		if ( ! $start_utc ) {
			delete_post_meta( $post_id, Constants::META_START_HOUR );
			delete_post_meta( $post_id, Constants::META_START_WEEKDAY );
			delete_post_meta( $post_id, Constants::META_START_DATE );
			delete_post_meta( $post_id, Constants::META_START_MONTH );
			delete_post_meta( $post_id, Constants::META_TIME_SLOT );
			return;
		}
		$dt   = ( new \DateTimeImmutable( '@' . $start_utc ) )->setTimezone( wp_timezone() );
		$hour = (int) $dt->format( 'G' );
		update_post_meta( $post_id, Constants::META_START_HOUR, $hour );
		update_post_meta( $post_id, Constants::META_START_WEEKDAY, (int) $dt->format( 'N' ) ); // 1–7
		update_post_meta( $post_id, Constants::META_START_DATE, $dt->format( 'Y-m-d' ) );
		update_post_meta( $post_id, Constants::META_START_MONTH, (int) $dt->format( 'n' ) ); // 1–12

		if ( $hour >= 8 && $hour < 12 ) {
			$slot = 'morning';
		} elseif ( $hour >= 12 && $hour < 17 ) {
			$slot = 'afternoon';
		} elseif ( $hour >= 17 && $hour < 21 ) {
			$slot = 'evening';
		} else {
			$slot = 'night'; // 21–23 und 0–7
		}
		update_post_meta( $post_id, Constants::META_TIME_SLOT, $slot );
	}
}
