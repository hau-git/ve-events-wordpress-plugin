<?php
/**
 * Extends WordPress search to cover event meta and taxonomies.
 *
 * @package VE_Events
 */

namespace VEV\Query;

use VEV\Constants;
use VEV\Settings;
use VEV\Support\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Joins, where, distinct, and search-clause filters for event search.
 */
final class SearchFilters {

	/**
	 * Register the search filters.
	 */
	public static function init(): void {
		add_filter( 'posts_join', array( __CLASS__, 'search_join' ), 10, 2 );
		add_filter( 'posts_where', array( __CLASS__, 'search_where' ), 10, 2 );
		add_filter( 'posts_search', array( __CLASS__, 'extend_search' ), 10, 2 );
		add_filter( 'posts_distinct', array( __CLASS__, 'search_distinct' ), 10, 2 );
	}

	/**
	 * Add the meta and taxonomy joins needed for event search.
	 *
	 * @param string    $join  Existing JOIN clause.
	 * @param \WP_Query $query The query being run.
	 */
	public static function search_join( string $join, \WP_Query $query ): string {
		if ( is_admin() || ! $query->is_search() ) {
			return $join;
		}

		global $wpdb;

		if ( false === strpos( $join, 'vev_pm' ) ) {
			$join .= " LEFT JOIN {$wpdb->postmeta} vev_pm ON ({$wpdb->posts}.ID = vev_pm.post_id) ";
		}

		if ( false === strpos( $join, 'vev_pm_end' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are safe WP globals
			$join .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} vev_pm_end ON ({$wpdb->posts}.ID = vev_pm_end.post_id AND vev_pm_end.meta_key = %s) ",
				Constants::META_END_UTC
			);
		}

		if ( false === strpos( $join, 'vev_tr' ) ) {
			$join .= " LEFT JOIN {$wpdb->term_relationships} vev_tr ON ({$wpdb->posts}.ID = vev_tr.object_id) ";
			$join .= " LEFT JOIN {$wpdb->term_taxonomy} vev_tt ON (vev_tr.term_taxonomy_id = vev_tt.term_taxonomy_id) ";
			$join .= " LEFT JOIN {$wpdb->terms} vev_t ON (vev_tt.term_id = vev_t.term_id) ";
		}

		return $join;
	}

	/**
	 * Optionally hide archived events from frontend search results.
	 *
	 * @param string    $where Existing WHERE clause.
	 * @param \WP_Query $query The query being run.
	 */
	public static function search_where( string $where, \WP_Query $query ): string {
		if ( is_admin() || ! $query->is_search() ) {
			return $where;
		}

		$settings = Settings::get();
		if ( empty( $settings['hide_archived_search'] ) ) {
			return $where;
		}

		global $wpdb;
		$now    = time();
		$cutoff = Lifecycle::cutoff( $now );

		$where .= $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_type != %s OR ( CAST( vev_pm_end.meta_value AS SIGNED ) >= %d ) )",
			Constants::POST_TYPE,
			$cutoff
		);

		return $where;
	}

	/**
	 * Force DISTINCT results for event search (taxonomy joins create duplicates).
	 *
	 * @param string    $distinct Existing DISTINCT clause.
	 * @param \WP_Query $query    The query being run.
	 */
	public static function search_distinct( string $distinct, \WP_Query $query ): string {
		if ( is_admin() || ! $query->is_search() ) {
			return $distinct;
		}
		return 'DISTINCT';
	}

	/**
	 * Extend the core search clause to match event meta and taxonomy terms.
	 *
	 * @param string    $search Existing search clause.
	 * @param \WP_Query $query  The query being run.
	 */
	public static function extend_search( string $search, \WP_Query $query ): string {
		if ( is_admin() || ! $query->is_search() ) {
			return $search;
		}

		global $wpdb;

		$term = (string) $query->get( 's' );
		$term = trim( $term );

		if ( '' === $term || '' === $search ) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		$tax_list = array( Constants::TAX_CATEGORY, Constants::TAX_LOCATION, Constants::TAX_TOPIC, Constants::TAX_SERIES );
		$tax_in   = "'" . implode( "','", array_map( 'esc_sql', $tax_list ) ) . "'";

		$meta_keys = array( Constants::META_SPEAKER, Constants::META_SPECIAL, Constants::META_INFO_URL );
		$meta_in   = "'" . implode( "','", array_map( 'esc_sql', $meta_keys ) ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $meta_in/$tax_in are esc_sql'd constant lists; table names are safe WP globals.
		$extra = $wpdb->prepare(
			" OR ( {$wpdb->posts}.post_type = %s AND ( ( vev_pm.meta_key IN ($meta_in) AND vev_pm.meta_value LIKE %s ) OR ( vev_tt.taxonomy IN ($tax_in) AND vev_t.name LIKE %s ) ) )",
			Constants::POST_TYPE,
			$like,
			$like
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$pos = strrpos( $search, '))' );
		if ( false === $pos ) {
			return $search;
		}

		return substr( $search, 0, $pos ) . $extra . substr( $search, $pos );
	}
}
