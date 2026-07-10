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
		return ViewsNav::build( ViewsNav::current_from_request() );
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

		$event_status = EventStatus::for_post( $post->ID );
		if ( in_array( $event_status, EventStatus::OPTIONS, true ) ) {
			$post_states[ 've_' . strtolower( $event_status ) ] = EventStatus::label( $event_status );
		}

		return $post_states;
	}
}
