<?php
/**
 * Per-request cache of an event's core date meta.
 *
 * @package VE_Events
 */

namespace VEV\Support;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches an event's core date meta for the duration of a request.
 */
final class EventData {

	/**
	 * Per-request cache: post_id → event data array.
	 *
	 * @var array<int,array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool}>
	 */
	private static array $cache = array();

	/**
	 * Fetch an event's start/end/all-day/hide-end data.
	 *
	 * A single get_post_meta() call primes WordPress' own meta cache, then the
	 * individual keys are read from the returned array.
	 *
	 * @param int $post_id Post ID.
	 * @return array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool}
	 */
	public static function get( int $post_id ): array {
		if ( isset( self::$cache[ $post_id ] ) ) {
			return self::$cache[ $post_id ];
		}

		$meta = get_post_meta( $post_id );

		$start_utc = isset( $meta[ Constants::META_START_UTC ][0] ) ? (int) $meta[ Constants::META_START_UTC ][0] : 0;
		$end_utc   = isset( $meta[ Constants::META_END_UTC ][0] ) ? (int) $meta[ Constants::META_END_UTC ][0] : 0;
		$all_day   = isset( $meta[ Constants::META_ALL_DAY ][0] ) ? (int) $meta[ Constants::META_ALL_DAY ][0] : 0;
		$hide_end  = isset( $meta[ Constants::META_HIDE_END ][0] ) ? (int) $meta[ Constants::META_HIDE_END ][0] : 0;

		if ( ! $end_utc && $start_utc ) {
			$end_utc = $start_utc;
		}

		self::$cache[ $post_id ] = array(
			'start_utc' => $start_utc,
			'end_utc'   => $end_utc,
			'all_day'   => (bool) $all_day,
			'hide_end'  => (bool) $hide_end,
		);

		return self::$cache[ $post_id ];
	}
}
