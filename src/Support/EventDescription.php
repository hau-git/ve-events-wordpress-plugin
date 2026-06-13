<?php
/**
 * Builds the plain-text event description shared by Schema.org and Open Graph.
 *
 * @package VE_Events
 */

namespace VEV\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives the shared plain-text event description.
 */
final class EventDescription {

	/**
	 * Derive a description from the excerpt, falling back to trimmed content.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$excerpt = trim( (string) get_the_excerpt( $post ) );
		if ( '' !== $excerpt ) {
			return wp_strip_all_tags( $excerpt );
		}

		$content = wp_strip_all_tags( (string) $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $content, 0, 300 );
		}
		return substr( $content, 0, 300 );
	}
}
