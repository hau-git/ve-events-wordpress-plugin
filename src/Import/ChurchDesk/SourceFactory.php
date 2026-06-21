<?php
/**
 * ChurchDesk source factory.
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selects the ChurchDesk source implementation for a feed.
 */
class SourceFactory {

	const ENDPOINT_PULL_API      = 'pull_api';
	const ENDPOINT_CALENDAR_VIEW = 'calendar_view';

	/**
	 * Returns the source implementation for the given feed config.
	 *
	 * @param array $config Full feed config.
	 */
	public static function make( array $config ): SourceInterface {
		$endpoint = $config['cd_endpoint'] ?? self::ENDPOINT_PULL_API;

		if ( self::ENDPOINT_CALENDAR_VIEW === $endpoint ) {
			return new CalendarViewSource( $config );
		}

		return new PullApiSource( $config );
	}

	/**
	 * Returns the available endpoint choices keyed by value.
	 */
	public static function get_endpoints(): array {
		return array(
			self::ENDPOINT_PULL_API      => __( 'Pull API (partner token)', 've-events' ),
			self::ENDPOINT_CALENDAR_VIEW => __( 'Calendar View (organization id)', 've-events' ),
		);
	}
}
