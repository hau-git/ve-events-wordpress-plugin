<?php
/**
 * Admin event list table: columns, content, sortable columns, views, and
 * post-state labels.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Support\EventData;
use VEV\Support\EventStatus;
use VEV\Support\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customizes the event list table columns, content, views, and post states.
 */
final class ListTable {

	/**
	 * Register the list-table column, view, and post-state hooks.
	 */
	public static function init(): void {
		add_filter( 'manage_' . Constants::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . Constants::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . Constants::POST_TYPE . '_sortable_columns', array( __CLASS__, 'admin_sortable_columns' ) );

		add_filter( 'display_post_states', array( __CLASS__, 'display_post_states' ), 10, 2 );

		add_filter( 'views_edit-' . Constants::POST_TYPE, array( __CLASS__, 'admin_views' ) );
	}

	/**
	 * Define the event list table columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function admin_columns( array $columns ): array {
		$cols = array();

		if ( isset( $columns['cb'] ) ) {
			$cols['cb'] = $columns['cb'];
		}
		$cols['title']    = __( 'Title', 've-events' );
		$cols['vev_when'] = __( 'When', 've-events' );

		$cols[ Constants::TAX_CATEGORY ] = __( 'Category', 've-events' );
		$cols[ Constants::TAX_LOCATION ] = __( 'Location', 've-events' );
		$cols[ Constants::TAX_TOPIC ]    = __( 'Topic', 've-events' );
		$cols[ Constants::TAX_SERIES ]   = __( 'Series', 've-events' );

		return $cols;
	}

	/**
	 * Mark the "When" column as sortable.
	 *
	 * @param array<string,string> $columns Existing sortable columns.
	 * @return array<string,string>
	 */
	public static function admin_sortable_columns( array $columns ): array {
		$columns['vev_when'] = 'vev_when';
		return $columns;
	}

	/**
	 * Render the content of a custom event list column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function admin_column_content( string $column, int $post_id ): void {
		$tz = wp_timezone();

		switch ( $column ) {
			case 'vev_when':
				$start   = (int) get_post_meta( $post_id, Constants::META_START_UTC, true );
				$end     = (int) get_post_meta( $post_id, Constants::META_END_UTC, true );
				$all_day = (bool) get_post_meta( $post_id, Constants::META_ALL_DAY, true );
				if ( $start ) {
					$month    = wp_date( 'Y-m', $start, $tz );
					$date_str = wp_date( 'j. M Y', $start, $tz );
					printf(
						'<span class="vev-when-date" data-vev-month="%s">%s</span>',
						esc_attr( $month ),
						esc_html( $date_str )
					);
					if ( ! $all_day ) {
						$time_str = wp_date( 'H:i', $start, $tz );
						if ( $end && $end !== $start ) {
							$time_str .= ' – ' . wp_date( 'H:i', $end, $tz );
						}
						printf( '<span class="vev-when-time">%s</span>', esc_html( $time_str ) );
					}
					$event_status = EventStatus::for_post( $post_id );
					if ( $event_status ) {
						$status_label = EventStatus::label( $event_status );
						printf(
							'<br><span class="ve-status-badge %s">%s</span>',
							esc_attr( EventStatus::badge_class( $event_status ) ),
							esc_html( $status_label ? $status_label : $event_status )
						);
					}
				} else {
					echo '—';
				}
				break;

			case Constants::TAX_CATEGORY:
			case Constants::TAX_LOCATION:
			case Constants::TAX_TOPIC:
			case Constants::TAX_SERIES:
				$terms = get_the_terms( $post_id, $column );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * Replace the list-table views (Upcoming / Past / All / Drafts / Trash / Calendar).
	 *
	 * @param array<string,string> $views Existing views.
	 * @return array<string,string>
	 */
	public static function admin_views( array $views ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- required by the views_edit-{post_type} filter signature.
		$base_url = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE );
		$now      = time();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$current_view = isset( $_GET['vev_view'] ) ? sanitize_key( $_GET['vev_view'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$post_status = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';

		if ( '' === $current_view && '' === $post_status ) {
			$current_view = 'upcoming';
		}

		global $wpdb;
		$pt = Constants::POST_TYPE;

		$count_upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			 AND ( CAST( pm.meta_value AS SIGNED ) >= %d OR pm.meta_value IS NULL )",
				Constants::META_END_UTC,
				$pt,
				$now
			)
		);

		$count_past = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT p.ID ) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			 AND CAST( pm.meta_value AS SIGNED ) < %d",
				Constants::META_END_UTC,
				$pt,
				$now
			)
		);

		$counts      = wp_count_posts( $pt );
		$count_all   = (int) ( $counts->publish ?? 0 );
		$count_draft = (int) ( $counts->draft ?? 0 );
		$count_trash = (int) ( $counts->trash ?? 0 );

		$new_views = array();

		$class                 = ( 'upcoming' === $current_view ) ? 'current' : '';
		$new_views['upcoming'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'upcoming', $base_url ) ),
			$class,
			__( 'Upcoming', 've-events' ),
			number_format_i18n( $count_upcoming )
		);

		$class             = ( 'past' === $current_view ) ? 'current' : '';
		$new_views['past'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'past', $base_url ) ),
			$class,
			__( 'Past', 've-events' ),
			number_format_i18n( $count_past )
		);

		$class            = ( 'all' === $current_view ) ? 'current' : '';
		$new_views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'all', $base_url ) ),
			$class,
			__( 'All', 've-events' ),
			number_format_i18n( $count_all )
		);

		if ( $count_draft > 0 ) {
			$class              = ( 'draft' === $post_status ) ? 'current' : '';
			$new_views['draft'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'post_status', 'draft', $base_url ) ),
				$class,
				__( 'Drafts', 've-events' ),
				number_format_i18n( $count_draft )
			);
		}

		if ( $count_trash > 0 ) {
			$class              = ( 'trash' === $post_status ) ? 'current' : '';
			$new_views['trash'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'post_status', 'trash', $base_url ) ),
				$class,
				__( 'Trash', 've-events' ),
				number_format_i18n( $count_trash )
			);
		}

		// Calendar Grid View.
		$cal_url = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE . '&page=vev-calendar' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$current_page          = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$new_views['calendar'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $cal_url ),
			( 'vev-calendar' === $current_page ) ? 'current' : '',
			esc_html__( 'Calendar', 've-events' )
		);

		return $new_views;
	}

	/**
	 * Append lifecycle and status post-state labels in the list table.
	 *
	 * @param array<string,string> $post_states Existing post states.
	 * @param \WP_Post             $post        The post object.
	 * @return array<string,string>
	 */
	public static function display_post_states( array $post_states, \WP_Post $post ): array {
		if ( Constants::POST_TYPE !== $post->post_type ) {
			return $post_states;
		}

		$data       = EventData::get( $post->ID );
		$raw_status = Lifecycle::status( $data['start_utc'], $data['end_utc'] );
		if ( 'ongoing' === $raw_status ) {
			$post_states['ve_ongoing'] = __( 'Ongoing', 've-events' );
		} elseif ( 'past' === $raw_status || 'archived' === $raw_status ) {
			$post_states['ve_past'] = __( 'Past', 've-events' );
		}

		$event_status = (string) get_post_meta( $post->ID, Constants::META_EVENT_STATUS, true );
		if ( 'cancelled' === $event_status ) {
			$post_states['ve_cancelled'] = __( 'Cancelled', 've-events' );
		} elseif ( 'postponed' === $event_status ) {
			$post_states['ve_postponed'] = __( 'Postponed', 've-events' );
		} elseif ( 'rescheduled' === $event_status ) {
			$post_states['ve_rescheduled'] = __( 'Rescheduled', 've-events' );
		} elseif ( 'movedOnline' === $event_status ) {
			$post_states['ve_movedonline'] = __( 'Moved Online', 've-events' );
		}

		return $post_states;
	}
}
