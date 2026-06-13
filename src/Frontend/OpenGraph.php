<?php
/**
 * Outputs Open Graph and Twitter Card meta tags for single events.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

use VEV\Constants;
use VEV\Settings;
use VEV\Support\DateFormatter;
use VEV\Support\EventData;
use VEV\Support\EventDescription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits event Open Graph / Twitter meta tags in the document head.
 */
final class OpenGraph {

	/**
	 * Register the wp_head output.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 2 );
	}

	/**
	 * Print the Open Graph and Twitter Card meta tags.
	 */
	public static function output(): void {
		if ( ! is_singular( Constants::POST_TYPE ) ) {
			return;
		}
		$settings = Settings::get();
		$mode     = $settings['og_tags'] ?? 'auto';
		if ( 'disabled' === $mode ) {
			return;
		}
		if ( 'auto' === $mode ) {
			if (
				defined( 'WPSEO_VERSION' ) ||
				defined( 'RANK_MATH_VERSION' ) ||
				defined( 'AIOSEO_VERSION' ) ||
				class_exists( 'The_SEO_Framework\Load' )
			) {
				return;
			}
		}

		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$data        = EventData::get( $post_id );
		$title       = get_the_title( $post_id );
		$description = EventDescription::get( $post_id );
		$url         = (string) get_permalink( $post_id );
		$img         = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		$tz          = wp_timezone();
		$start_iso   = $data['start_utc'] ? DateFormatter::schema( $data['start_utc'], $data['all_day'], $tz ) : '';
		$end_iso     = $data['end_utc'] ? DateFormatter::schema( $data['end_utc'], $data['all_day'], $tz ) : '';

		$metas = array(
			array( 'property', 'og:type', 'event' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:url', $url ),
		);
		if ( $description ) {
			$metas[] = array( 'property', 'og:description', $description );
			$metas[] = array( 'name', 'description', $description );
		}
		if ( $img ) {
			$metas[] = array( 'property', 'og:image', $img );
			$metas[] = array( 'name', 'twitter:image', $img );
		}
		if ( $start_iso ) {
			$metas[] = array( 'property', 'og:start_time', $start_iso );
		}
		if ( $end_iso ) {
			$metas[] = array( 'property', 'og:end_time', $end_iso );
		}
		$metas[] = array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' );
		$metas[] = array( 'name', 'twitter:title', $title );
		if ( $description ) {
			$metas[] = array( 'name', 'twitter:description', $description );
		}

		echo "\n<!-- VE Events OG Tags -->\n";
		foreach ( $metas as $m ) {
			printf(
				"<meta %s=\"%s\" content=\"%s\">\n",
				esc_attr( $m[0] ),
				esc_attr( $m[1] ),
				esc_attr( $m[2] )
			);
		}
	}
}
