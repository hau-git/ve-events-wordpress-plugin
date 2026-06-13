<?php
/**
 * Admin event list filter bar (month / category / location / topic) and the
 * pre_get_posts handler that applies the month filter.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the list filter bar and applies the selected month to the main query.
 */
final class ListFilters {

	/**
	 * Register the filter-bar and query hooks.
	 */
	public static function init(): void {
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_list_filter_bar' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_admin_list_filters' ) );
	}

	/**
	 * Render the month / category / location / topic filter dropdowns.
	 *
	 * @param string $post_type The current list-table post type.
	 */
	public static function render_list_filter_bar( string $post_type ): void {
		if ( Constants::POST_TYPE !== $post_type ) {
			return;
		}

		global $wpdb;

		// Month dropdown — aggregate distinct YYYY-MM from _vev_start_utc.
		$months = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE_FORMAT( FROM_UNIXTIME( CAST( pm.meta_value AS SIGNED ) ), '%%Y-%%m' ) AS ym
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND p.post_type = %s
			   AND p.post_status != 'trash'
			   AND pm.meta_value > 0
			 ORDER BY ym DESC",
				Constants::META_START_UTC,
				Constants::POST_TYPE
			)
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$selected_month = isset( $_GET['vev_list_month'] )
			? sanitize_text_field( wp_unslash( $_GET['vev_list_month'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<select name="vev_list_month">';
		echo '<option value="">' . esc_html__( 'All months', 've-events' ) . '</option>';
		foreach ( $months as $ym ) {
			if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $ym ) ) {
				continue;
			}
			[ $y, $m ] = explode( '-', (string) $ym );
			$label     = wp_date( 'F Y', mktime( 0, 0, 0, (int) $m, 1, (int) $y ) );
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $ym ),
				selected( $selected_month, $ym, false ),
				esc_html( (string) $label )
			);
		}
		echo '</select>&nbsp;';

		// Category dropdown.
		wp_dropdown_categories(
			array(
				'taxonomy'        => Constants::TAX_CATEGORY,
				'name'            => Constants::TAX_CATEGORY,
				'show_option_all' => __( 'All categories', 've-events' ),
				'hide_empty'      => false,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
				'selected'        => isset( $_GET[ Constants::TAX_CATEGORY ] ) ? (int) $_GET[ Constants::TAX_CATEGORY ] : 0,
				'value_field'     => 'term_id',
				'hierarchical'    => true,
			)
		);
		echo '&nbsp;';

		// Location dropdown.
		wp_dropdown_categories(
			array(
				'taxonomy'        => Constants::TAX_LOCATION,
				'name'            => Constants::TAX_LOCATION,
				'show_option_all' => __( 'All locations', 've-events' ),
				'hide_empty'      => false,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
				'selected'        => isset( $_GET[ Constants::TAX_LOCATION ] ) ? (int) $_GET[ Constants::TAX_LOCATION ] : 0,
				'value_field'     => 'term_id',
			)
		);
		echo '&nbsp;';

		// Topic dropdown.
		wp_dropdown_categories(
			array(
				'taxonomy'        => Constants::TAX_TOPIC,
				'name'            => Constants::TAX_TOPIC,
				'show_option_all' => __( 'All topics', 've-events' ),
				'hide_empty'      => false,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
				'selected'        => isset( $_GET[ Constants::TAX_TOPIC ] ) ? (int) $_GET[ Constants::TAX_TOPIC ] : 0,
				'value_field'     => 'term_id',
			)
		);
	}

	/**
	 * Apply the selected month filter to the main admin list query.
	 *
	 * @param \WP_Query $query The current query.
	 */
	public static function apply_admin_list_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Constants::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only admin list filter, no state change.
		$month = isset( $_GET['vev_list_month'] )
			? sanitize_text_field( wp_unslash( $_GET['vev_list_month'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $month && preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			[ $y, $m ] = explode( '-', $month );
			$tz        = wp_timezone();
			$from      = ( new \DateTimeImmutable( $y . '-' . $m . '-01 00:00:00', $tz ) )->getTimestamp();
			$last_day  = (int) ( new \DateTimeImmutable( $y . '-' . $m . '-01', $tz ) )->format( 't' );
			$to        = ( new \DateTimeImmutable( $y . '-' . $m . '-' . $last_day . ' 23:59:59', $tz ) )->getTimestamp();

			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'     => Constants::META_START_UTC,
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
			$query->set( 'meta_query', $meta_query );
		}
	}
}
