<?php
/**
 * Single source of truth for the Schema.org eventAttendanceMode.
 *
 * @package VE_Events
 */

namespace VEV\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the attendance-mode override to labels and Schema.org URIs.
 *
 * An empty value means offline (the Schema.org default). The `movedOnline`
 * event-status override always forces the online mode, regardless of this
 * field, because a moved-online event is by definition online.
 */
final class AttendanceMode {

	/**
	 * Selectable non-default values, in display order.
	 *
	 * @var string[]
	 */
	public const OPTIONS = array( 'online', 'mixed' );

	/**
	 * Human-readable label. Empty string for the offline default.
	 *
	 * @param string $mode Mode key.
	 */
	public static function label( string $mode ): string {
		return match ( $mode ) {
			'online' => __( 'Online', 've-events' ),
			'mixed'  => __( 'Mixed (offline + online)', 've-events' ),
			default  => '',
		};
	}

	/**
	 * Schema.org eventAttendanceMode URI.
	 *
	 * @param string $mode         Attendance mode key.
	 * @param string $event_status Manual event-status override (movedOnline forces online).
	 */
	public static function schema_uri( string $mode, string $event_status = '' ): string {
		if ( 'movedOnline' === $event_status ) {
			return 'https://schema.org/OnlineEventAttendanceMode';
		}
		return match ( $mode ) {
			'online' => 'https://schema.org/OnlineEventAttendanceMode',
			'mixed'  => 'https://schema.org/MixedEventAttendanceMode',
			default  => 'https://schema.org/OfflineEventAttendanceMode',
		};
	}

	/**
	 * Whether the resolved mode includes an online component.
	 *
	 * @param string $mode         Attendance mode key.
	 * @param string $event_status Manual event-status override.
	 */
	public static function is_online( string $mode, string $event_status = '' ): bool {
		return 'movedOnline' === $event_status || 'online' === $mode || 'mixed' === $mode;
	}
}
