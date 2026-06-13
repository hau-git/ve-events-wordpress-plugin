<?php
/**
 * Registers query vars and shapes event queries via pre_get_posts.
 *
 * @package VE_Events
 */

namespace VEV\Query;

use VEV\Constants;
use VEV\Support\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query-var registration and pre_get_posts shaping for event queries.
 */
final class QueryFilters {

	/**
	 * Register the query filters.
	 */
	public static function init(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
	}

	/**
	 * Register the plugin's public query vars.
	 *
	 * @param array $vars Existing query vars.
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = Constants::QV_SCOPE;
		$vars[] = Constants::QV_INCLUDE_ARCHIVED;
		$vars[] = Constants::QV_DATE_FROM;
		$vars[] = Constants::QV_DATE_TO;
		$vars[] = Constants::QV_MONTH;
		$vars[] = Constants::QV_TIME_FROM;
		$vars[] = Constants::QV_TIME_TO;
		$vars[] = Constants::QV_WEEKDAY;
		return $vars;
	}

	/**
	 * Shape event queries (admin views and frontend scope/date/time/weekday filters).
	 *
	 * @param \WP_Query $query The query being prepared.
	 */
	public static function pre_get_posts( \WP_Query $query ): void {
		if ( ! $query instanceof \WP_Query ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		$is_event_query      = false;
		$is_event_only_query = false;
		if ( is_string( $post_type ) ) {
			$is_event_query      = ( Constants::POST_TYPE === $post_type );
			$is_event_only_query = $is_event_query;
		} elseif ( is_array( $post_type ) ) {
			$is_event_query      = in_array( Constants::POST_TYPE, $post_type, true );
			$is_event_only_query = ( $is_event_query && 1 === count( $post_type ) );
		} else {
			$is_event_query      = false;
			$is_event_only_query = false;
		}

		if ( is_admin() && $query->is_main_query() ) {
			if ( Constants::POST_TYPE === $post_type && 'edit.php' === $GLOBALS['pagenow'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filtering, no state change.
				$vev_view = isset( $_GET['vev_view'] ) ? sanitize_key( $_GET['vev_view'] ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filtering, no state change.
				$post_status = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';

				if ( '' === $vev_view && '' === $post_status ) {
					$vev_view = 'upcoming';
				}

				if ( '' !== $vev_view && '' === $post_status ) {
					$now        = time();
					$meta_query = (array) $query->get( 'meta_query' );
					if ( empty( $meta_query ) ) {
						$meta_query = array();
					}

					if ( 'upcoming' === $vev_view ) {
						$meta_query[] = array(
							'relation' => 'OR',
							array(
								'key'     => Constants::META_END_UTC,
								'value'   => $now,
								'compare' => '>=',
								'type'    => 'NUMERIC',
							),
							array(
								'key'     => Constants::META_END_UTC,
								'compare' => 'NOT EXISTS',
							),
						);
					} elseif ( 'past' === $vev_view ) {
						$meta_query[] = array(
							'key'     => Constants::META_END_UTC,
							'value'   => $now,
							'compare' => '<',
							'type'    => 'NUMERIC',
						);
					}

					if ( ! empty( $meta_query ) ) {
						$query->set( 'meta_query', $meta_query );
					}
				}

				$orderby = (string) $query->get( 'orderby' );
				$order   = strtoupper( (string) $query->get( 'order' ) );
				if ( 'DESC' !== $order ) {
					$order = 'ASC';
				}

				if ( 'vev_end' === $orderby ) {
					$query->set( 'meta_key', Constants::META_END_UTC );
					$query->set( 'orderby', 'meta_value_num' );
					$query->set( 'order', $order );
				} elseif ( '' === $orderby || 'vev_start' === $orderby ) {
					self::apply_ordering( $query, $order, true );
				}
			}
			return;
		}

		if ( ! is_admin() && $is_event_only_query && ! $query->get( 'suppress_filters' ) ) {
			$include_archived = (int) $query->get( Constants::QV_INCLUDE_ARCHIVED );
			$scope            = (string) $query->get( Constants::QV_SCOPE );

			// Read date range vars early so we can decide whether to skip the cutoff.
			$date_from = (string) $query->get( Constants::QV_DATE_FROM );
			$date_to   = (string) $query->get( Constants::QV_DATE_TO );
			$month_qv  = (string) $query->get( Constants::QV_MONTH );

			$has_explicit_date_range = $date_from || $date_to
				|| ( $month_qv && preg_match( '/^\d{4}-\d{2}$/', $month_qv ) );

			$now    = time();
			$cutoff = Lifecycle::cutoff( $now );

			$meta_query = (array) $query->get( 'meta_query' );
			if ( empty( $meta_query ) ) {
				$meta_query = array();
			}

			// Skip the archived cutoff when an explicit date range is requested —
			// otherwise querying past months would always return zero results.
			if ( 1 !== $include_archived && ! $has_explicit_date_range ) {
				$meta_query[] = array(
					'key'     => Constants::META_END_UTC,
					'value'   => $cutoff,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}

			if ( $scope ) {
				switch ( $scope ) {
					case 'upcoming':
						$meta_query[] = array(
							'key'     => Constants::META_START_UTC,
							'value'   => $now,
							'compare' => '>',
							'type'    => 'NUMERIC',
						);
						break;

					case 'ongoing':
						$meta_query[] = array(
							'key'     => Constants::META_START_UTC,
							'value'   => $now,
							'compare' => '<=',
							'type'    => 'NUMERIC',
						);
						$meta_query[] = array(
							'key'     => Constants::META_END_UTC,
							'value'   => $now,
							'compare' => '>=',
							'type'    => 'NUMERIC',
						);
						break;

					case 'past':
						$meta_query[] = array(
							'key'     => Constants::META_END_UTC,
							'value'   => $now,
							'compare' => '<',
							'type'    => 'NUMERIC',
						);
						$meta_query[] = array(
							'key'     => Constants::META_END_UTC,
							'value'   => $cutoff,
							'compare' => '>=',
							'type'    => 'NUMERIC',
						);
						break;

					case 'archived':
						$meta_query[] = array(
							'key'     => Constants::META_END_UTC,
							'value'   => $cutoff,
							'compare' => '<',
							'type'    => 'NUMERIC',
						);
						break;

					case 'all':
					default:
						break;
				}
			}

			// --- Date range / Month filter ---
			if ( $month_qv && preg_match( '/^\d{4}-\d{2}$/', $month_qv ) && ! $date_from && ! $date_to ) {
				$tz        = wp_timezone();
				$m_start   = new \DateTimeImmutable( $month_qv . '-01 00:00:00', $tz );
				$m_end     = $m_start->modify( 'last day of this month 23:59:59' );
				$date_from = (string) $m_start->getTimestamp();
				$date_to   = (string) $m_end->getTimestamp();
			}
			if ( $date_from ) {
				$ts           = is_numeric( $date_from )
					? (int) $date_from
					: ( new \DateTimeImmutable( $date_from . ' 00:00:00', wp_timezone() ) )->getTimestamp();
				$meta_query[] = array(
					'key'     => Constants::META_START_UTC,
					'value'   => $ts,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}
			if ( $date_to ) {
				$ts           = is_numeric( $date_to )
					? (int) $date_to
					: ( new \DateTimeImmutable( $date_to . ' 23:59:59', wp_timezone() ) )->getTimestamp();
				$meta_query[] = array(
					'key'     => Constants::META_START_UTC,
					'value'   => $ts,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}

			// --- Time of day filter ---
			$time_from = $query->get( Constants::QV_TIME_FROM );
			$time_to   = $query->get( Constants::QV_TIME_TO );
			if ( '' !== $time_from && false !== $time_from && '' !== $time_to && false !== $time_to ) {
				$meta_query[] = array(
					'key'     => Constants::META_START_HOUR,
					'value'   => array( (int) $time_from, (int) $time_to ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				);
			} elseif ( '' !== $time_from && false !== $time_from ) {
				$meta_query[] = array(
					'key'     => Constants::META_START_HOUR,
					'value'   => (int) $time_from,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			} elseif ( '' !== $time_to && false !== $time_to ) {
				$meta_query[] = array(
					'key'     => Constants::META_START_HOUR,
					'value'   => (int) $time_to,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}

			// --- Weekday filter ---
			$weekday_qv = $query->get( Constants::QV_WEEKDAY );
			if ( '' !== $weekday_qv && false !== $weekday_qv ) {
				if ( is_string( $weekday_qv ) && str_contains( $weekday_qv, ',' ) ) {
					$weekday_qv = array_map( 'absint', explode( ',', $weekday_qv ) );
				}
				$meta_query[] = is_array( $weekday_qv )
					? array(
						'key'     => Constants::META_START_WEEKDAY,
						'value'   => $weekday_qv,
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					)
					: array(
						'key'     => Constants::META_START_WEEKDAY,
						'value'   => (int) $weekday_qv,
						'compare' => '=',
						'type'    => 'NUMERIC',
					);
			}

			$query->set( 'meta_query', $meta_query );

			$orderby  = $query->get( 'orderby' );
			$meta_key = (string) $query->get( 'meta_key' );

			if ( empty( $orderby ) || ( 'date' === $orderby && '' === $meta_key ) ) {
				// phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preserve byte-identical legacy default ordering.
				self::apply_ordering( $query, (string) ( $query->get( 'order' ) ?: 'asc' ), false );
			}
		}

		if ( ! is_admin() && $query->is_search() && ! $query->get( 'suppress_filters' ) ) {
			$pt = $query->get( 'post_type' );
			if ( empty( $pt ) ) {
				$query->set( 'post_type', array( 'post', 'page', Constants::POST_TYPE ) );
			}
		}
	}

	/**
	 * Apply meta-value ordering by event start, honoring an existing meta sort.
	 *
	 * @param \WP_Query $query The query being prepared.
	 * @param string    $order Sort direction (ASC/DESC).
	 * @param bool      $force Whether to override an existing meta ordering.
	 */
	private static function apply_ordering( \WP_Query $query, string $order, bool $force ): void {
		$order = strtoupper( $order );
		if ( 'DESC' !== $order ) {
			$order = 'ASC';
		}

		if ( ! $force ) {
			$orderby  = (string) $query->get( 'orderby' );
			$meta_key = (string) $query->get( 'meta_key' );
			if ( 'meta_value_num' === $orderby && '' !== $meta_key ) {
				return;
			}
		}

		$query->set( 'meta_key', Constants::META_START_UTC );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', $order );
	}
}
