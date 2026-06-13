<?php
/**
 * Single source of truth for the manual event-status override
 * (cancelled / postponed / rescheduled / movedOnline).
 *
 * Replaces the status maps that were previously duplicated across the admin
 * list, the field registry, and the Schema.org output.
 *
 * @package VE_Events
 */

namespace VEV\Support;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the manual status override to labels, colors, and Schema.org URIs.
 */
final class EventStatus {

	/**
	 * Selectable override values, in display order. An empty value means
	 * "Scheduled (default)".
	 *
	 * @var string[]
	 */
	public const OPTIONS = array( 'cancelled', 'postponed', 'rescheduled', 'movedOnline' );

	/**
	 * Human-readable label for a status override. Empty string for default.
	 *
	 * @param string $status Status key.
	 */
	public static function label( string $status ): string {
		return match ( $status ) {
			'cancelled'   => __( 'Cancelled', 've-events' ),
			'postponed'   => __( 'Postponed', 've-events' ),
			'rescheduled' => __( 'Rescheduled', 've-events' ),
			'movedOnline' => __( 'Moved Online', 've-events' ),
			default       => '',
		};
	}

	/**
	 * Badge / accent color (hex) for a status override. Empty string for default.
	 *
	 * @param string $status Status key.
	 */
	public static function color( string $status ): string {
		return match ( $status ) {
			'cancelled'   => '#d63638',
			'postponed'   => '#dba617',
			'rescheduled' => '#2271b1',
			'movedOnline' => '#007cba',
			default       => '',
		};
	}

	/**
	 * Schema.org eventStatus URI. Falls back to EventScheduled.
	 *
	 * @param string $status Status key.
	 */
	public static function schema_uri( string $status ): string {
		return match ( $status ) {
			'cancelled'   => 'https://schema.org/EventCancelled',
			'postponed'   => 'https://schema.org/EventPostponed',
			'rescheduled' => 'https://schema.org/EventRescheduled',
			'movedOnline' => 'https://schema.org/EventMovedOnline',
			default       => 'https://schema.org/EventScheduled',
		};
	}

	/**
	 * CSS class used for the status badge in the admin list.
	 *
	 * @param string $status Status key.
	 */
	public static function badge_class( string $status ): string {
		return 've-status-' . $status;
	}

	/**
	 * Read the stored override status for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function for_post( int $post_id ): string {
		return (string) get_post_meta( $post_id, Constants::META_EVENT_STATUS, true );
	}
}
