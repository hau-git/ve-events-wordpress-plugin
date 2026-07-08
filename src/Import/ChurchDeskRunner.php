<?php
/**
 * ChurchDesk Import Runner — fetches the ChurchDesk API into normalised rows.
 *
 * @package VE_Events
 */

namespace VEV\Import;

use VEV\Import\ChurchDesk\Mapper;
use VEV\Import\ChurchDesk\SourceFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Concrete runner for ChurchDesk feeds.
 *
 * Delegates fetching to a {@see ChurchDesk\SourceInterface} (Pull API or
 * calendar-view), maps each event with {@see ChurchDesk\Mapper}, and hands
 * normalised rows to {@see AbstractRunner}.
 */
class ChurchDeskRunner extends AbstractRunner {

	/**
	 * Fetches the ChurchDesk API and returns normalised event rows.
	 *
	 * @return array|null Normalised rows, [] when empty, or null on fetch error.
	 */
	protected function fetch_events(): ?array {
		if ( empty( $this->config['cd_org_id'] ) ) {
			$this->add_error( __( 'No ChurchDesk organization id configured.', 've-events' ) );
			return null;
		}

		$source = SourceFactory::make( $this->config );

		try {
			$events = $source->fetch();
		} catch ( \RuntimeException $e ) {
			$this->add_error( $e->getMessage() );
			return null;
		}

		if ( empty( $events ) ) {
			return array();
		}

		$rows = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$row = Mapper::map( $event, $this->config );
			if ( null === $row ) {
				++$this->counts['skipped'];
				continue;
			}
			$rows[] = $row;
		}

		return $rows;
	}
}
