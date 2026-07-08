<?php
/**
 * Shared sub-views navigation (Upcoming / Past / All / Drafts / Trash / Calendar)
 * used by both the event list table and the calendar grid page.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the event sub-views navigation and its counts.
 */
final class ViewsNav {

	/**
	 * Count events for each view.
	 *
	 * @return array{upcoming:int,past:int,all:int,draft:int,trash:int}
	 */
	public static function get_counts(): array {
		global $wpdb;

		$pt  = Constants::POST_TYPE;
		$now = time();

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

		$counts = wp_count_posts( $pt );

		return array(
			'upcoming' => $count_upcoming,
			'past'     => $count_past,
			'all'      => (int) ( $counts->publish ?? 0 ),
			'draft'    => (int) ( $counts->draft ?? 0 ),
			'trash'    => (int) ( $counts->trash ?? 0 ),
		);
	}

	/**
	 * Resolve the active view key from the current admin request.
	 */
	public static function current_from_request(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$page        = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$view        = isset( $_GET['vev_view'] ) ? sanitize_key( $_GET['vev_view'] ) : '';
		$post_status = isset( $_GET['post_status'] ) ? sanitize_key( $_GET['post_status'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'vev-calendar' === $page ) {
			return 'calendar';
		}
		if ( 'draft' === $post_status || 'trash' === $post_status ) {
			return $post_status;
		}
		if ( '' !== $view ) {
			return $view;
		}
		return 'upcoming';
	}

	/**
	 * Build the view links keyed by view slug.
	 *
	 * @param string $current The active view key.
	 * @return array<string,string>
	 */
	public static function build( string $current ): array {
		$base_url = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE );
		$cal_url  = admin_url( 'edit.php?post_type=' . Constants::POST_TYPE . '&page=vev-calendar' );
		$counts   = self::get_counts();

		$views = array();

		$views['upcoming'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'upcoming', $base_url ) ),
			'upcoming' === $current ? 'current' : '',
			__( 'Upcoming', 've-events' ),
			number_format_i18n( $counts['upcoming'] )
		);

		$views['past'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'past', $base_url ) ),
			'past' === $current ? 'current' : '',
			__( 'Past', 've-events' ),
			number_format_i18n( $counts['past'] )
		);

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'vev_view', 'all', $base_url ) ),
			'all' === $current ? 'current' : '',
			__( 'All', 've-events' ),
			number_format_i18n( $counts['all'] )
		);

		if ( $counts['draft'] > 0 ) {
			$views['draft'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'post_status', 'draft', $base_url ) ),
				'draft' === $current ? 'current' : '',
				__( 'Drafts', 've-events' ),
				number_format_i18n( $counts['draft'] )
			);
		}

		if ( $counts['trash'] > 0 ) {
			$views['trash'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'post_status', 'trash', $base_url ) ),
				'trash' === $current ? 'current' : '',
				__( 'Trash', 've-events' ),
				number_format_i18n( $counts['trash'] )
			);
		}

		$views['calendar'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $cal_url ),
			'calendar' === $current ? 'current' : '',
			esc_html__( 'Calendar', 've-events' )
		);

		return $views;
	}

	/**
	 * Echo the sub-views navigation as a subsubsub list (calendar page uses this;
	 * the list table lets WordPress render the built array itself).
	 *
	 * @param string $current The active view key.
	 */
	public static function render( string $current ): void {
		$views = self::build( $current );
		echo '<ul class="subsubsub">';
		$last = array_key_last( $views );
		foreach ( $views as $key => $html ) {
			printf(
				'<li class="%s">%s%s</li>',
				esc_attr( $key ),
				$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with escaping in build().
				$key !== $last ? ' |' : ''
			);
		}
		echo '</ul>';
	}
}
