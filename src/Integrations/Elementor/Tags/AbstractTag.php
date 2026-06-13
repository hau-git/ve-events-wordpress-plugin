<?php
/**
 * Base dynamic tag for VE Events Elementor integration.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor\Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Constants;

/**
 * Shared behaviour for every VE Events dynamic tag.
 */
abstract class AbstractTag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Group the tag belongs to.
	 *
	 * @return string Group slug.
	 */
	public function get_group(): string {
		return 've-events';
	}

	/**
	 * Resolve the current event post ID using a four-source fallback.
	 *
	 * @return int Post ID, or 0 when none could be resolved.
	 */
	protected function get_post_id(): int {
		global $post;

		if ( isset( $post->ID ) && get_post_type( $post->ID ) === Constants::POST_TYPE ) {
			return (int) $post->ID;
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id && get_post_type( $queried_id ) === Constants::POST_TYPE ) {
			return (int) $queried_id;
		}

		$post_id = get_the_ID();
		if ( $post_id && get_post_type( $post_id ) === Constants::POST_TYPE ) {
			return (int) $post_id;
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			// Elementor editor preview context; read-only, no form processing.
			$preview_id = isset( $_GET['preview_id'] ) ? absint( wp_unslash( $_GET['preview_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $preview_id && get_post_type( $preview_id ) === Constants::POST_TYPE ) {
				return $preview_id;
			}
		}

		return $post_id ? (int) $post_id : 0;
	}

	/**
	 * Determine whether the resolved post is a VE Events event.
	 *
	 * @return bool True when the current post is an event.
	 */
	protected function is_event_post(): bool {
		$post_id = $this->get_post_id();
		return $post_id && get_post_type( $post_id ) === Constants::POST_TYPE;
	}
}
