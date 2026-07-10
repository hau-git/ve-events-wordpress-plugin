<?php
/**
 * Public iCal export endpoints: a single-event .ics download and a subscribable
 * feed of upcoming events, both served via a query var on template_redirect.
 *
 * @package VE_Events
 */

namespace VEV\Export;

use VEV\Constants;
use VEV\Fields\Registry;
use VEV\Settings;
use VEV\Support\EventData;
use VEV\Support\EventDescription;
use VEV\Support\EventStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the query vars and serves the .ics single download and feed.
 */
final class Endpoint {

	/**
	 * Register query vars and the request handler.
	 */
	public static function init(): void {
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * Register the export query vars.
	 *
	 * @param array<int,string> $vars Existing query vars.
	 * @return array<int,string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = Constants::QV_ICS;
		$vars[] = Constants::QV_ICS_CAT;
		return $vars;
	}

	/**
	 * Public download URL for a single event's .ics file.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function single_url( int $post_id ): string {
		return add_query_arg( Constants::QV_ICS, $post_id, home_url( '/' ) );
	}

	/**
	 * Public subscribable feed URL, optionally filtered by category slug.
	 *
	 * @param string $category_slug Optional category slug.
	 */
	public static function feed_url( string $category_slug = '' ): string {
		$args = array( Constants::QV_ICS => 'feed' );
		if ( '' !== $category_slug ) {
			$args[ Constants::QV_ICS_CAT ] = $category_slug;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Dispatch the request if it targets the export endpoint.
	 */
	public static function maybe_serve(): void {
		$raw = get_query_var( Constants::QV_ICS );
		if ( '' === $raw || null === $raw ) {
			return;
		}

		if ( 'feed' === $raw ) {
			self::serve_feed();
			return;
		}

		if ( ctype_digit( (string) $raw ) ) {
			self::serve_single( (int) $raw );
			return;
		}

		self::not_found();
	}

	/**
	 * Serve a single event's .ics download.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function serve_single( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post
			|| Constants::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
			|| post_password_required( $post )
		) {
			self::not_found();
		}

		$vevent = self::build_vevent( $post );
		if ( '' === $vevent ) {
			self::not_found();
		}

		$ics = IcsBuilder::calendar( array( $vevent ), self::prodid(), get_bloginfo( 'name' ) );

		nocache_headers();
		self::send_headers( sanitize_title( $post->post_name ? $post->post_name : 'event-' . $post_id ) . '.ics', 'attachment' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RFC 5545 text, escaped by IcsBuilder.
		exit;
	}

	/**
	 * Serve the subscribable feed of upcoming events.
	 */
	private static function serve_feed(): void {
		$settings = Settings::get();
		if ( empty( $settings['ical_feed'] ) ) {
			self::not_found();
		}

		$cat_slug = (string) get_query_var( Constants::QV_ICS_CAT );
		$cat_slug = $cat_slug ? sanitize_title( $cat_slug ) : '';

		$args = array(
			'post_type'                    => Constants::POST_TYPE,
			'post_status'                  => 'publish',
			'posts_per_page'               => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded feed.
			'no_found_rows'                => true,
			'ignore_sticky_posts'          => true,
			'meta_query'                   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed numeric compare.
				array(
					'key'     => Constants::META_END_UTC,
					'value'   => time(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			'meta_key'                     => Constants::META_START_UTC, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering by start.
			'orderby'                      => 'meta_value_num',
			'order'                        => 'ASC',
			Constants::QV_INCLUDE_ARCHIVED => 1,
		);

		if ( '' !== $cat_slug ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- single term filter.
				array(
					'taxonomy' => Constants::TAX_CATEGORY,
					'field'    => 'slug',
					'terms'    => $cat_slug,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$vevents  = array();
		$last_mod = 0;
		$ids      = array();

		foreach ( $query->posts as $post ) {
			$vevent = self::build_vevent( $post );
			if ( '' === $vevent ) {
				continue;
			}
			$vevents[] = $vevent;
			$ids[]     = $post->ID;
			$mod       = (int) get_post_time( 'U', true, $post );
			if ( $mod > $last_mod ) {
				$last_mod = $mod;
			}
		}

		// Conditional-request handling to keep subscription polling cheap.
		$etag  = '"' . md5( Constants::VERSION . '|' . $cat_slug . '|' . $last_mod . '|' . implode( ',', $ids ) ) . '"';
		$since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ) : 0;
		$match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) : '';

		if ( ( $match && $match === $etag ) || ( $last_mod && $since && $since >= $last_mod ) ) {
			header( 'ETag: ' . $etag );
			status_header( 304 );
			exit;
		}

		$name = get_bloginfo( 'name' ) . ' – ' . __( 'Events', 've-events' );
		$ics  = IcsBuilder::calendar( $vevents, self::prodid(), $name, 12 );

		header( 'ETag: ' . $etag );
		if ( $last_mod ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_mod ) . ' GMT' );
		}
		header( 'Cache-Control: public, max-age=3600' );
		self::send_headers( 'events.ics', 'inline' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RFC 5545 text, escaped by IcsBuilder.
		exit;
	}

	/**
	 * Build a VEVENT block from a post, or '' when it has no start date.
	 *
	 * @param \WP_Post $post Event post.
	 */
	private static function build_vevent( \WP_Post $post ): string {
		$post_id = (int) $post->ID;
		$data    = EventData::get( $post_id );
		if ( ! $data['start_utc'] ) {
			return '';
		}

		// Location via the Registry's per-request term cache (matters for the
		// feed loop over up to 200 posts).
		$location = Registry::get_location_name( $post_id );
		if ( '' !== $location ) {
			$address = Registry::get_location_address( $post_id );
			if ( '' !== $address ) {
				$location .= ', ' . $address;
			}
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return IcsBuilder::vevent(
			array(
				'uid'            => 'vev-' . $post_id . '@' . $host,
				'dtstamp'        => (int) get_post_time( 'U', true, $post ),
				'start'          => $data['start_utc'],
				'end'            => $data['end_utc'],
				'all_day'        => $data['all_day'],
				'tz'             => wp_timezone(),
				'summary'        => get_the_title( $post_id ),
				'description'    => EventDescription::get( $post_id ),
				'location'       => $location,
				'url'            => (string) get_permalink( $post_id ),
				'status'         => EventStatus::for_post( $post_id ),
				'organizer_name' => (string) get_post_meta( $post_id, Constants::META_ORGANIZER, true ),
				'organizer_url'  => (string) get_post_meta( $post_id, Constants::META_ORGANIZER_URL, true ),
			)
		);
	}

	/**
	 * Emit the shared calendar content-type and disposition headers.
	 *
	 * @param string $filename    Download filename.
	 * @param string $disposition attachment | inline.
	 */
	private static function send_headers( string $filename, string $disposition ): void {
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
	}

	/**
	 * The calendar PRODID string.
	 */
	private static function prodid(): string {
		return '-//VE Events//' . Constants::VERSION . '//EN';
	}

	/**
	 * Send a 404 and stop.
	 */
	private static function not_found(): void {
		status_header( 404 );
		nocache_headers();
		exit;
	}
}
