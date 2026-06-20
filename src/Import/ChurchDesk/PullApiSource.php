<?php
/**
 * ChurchDesk Pull API source (documented, versioned API).
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads events from the documented ChurchDesk Pull API.
 *
 * Endpoint:   https://api.churchdesk.com/v3.0.0/events
 * Auth:       organizationId + partnerToken (query params)
 * Pagination: itemsNumber + pageMarker
 *
 * Events are already in the canonical shape, so {@see fetch()} returns them as-is.
 */
class PullApiSource extends AbstractSource {

	/**
	 * Base URI for the versioned Pull API.
	 */
	const BASE_URI = 'https://api.churchdesk.com/v3.0.0';

	/**
	 * Fetches all upcoming events across pages.
	 *
	 * @return array[]
	 * @throws \RuntimeException On a hard fetch/auth error.
	 */
	public function fetch(): array {
		$events = array();

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$batch = $this->get_json( $this->events_url( $page ) );

			// The /events endpoint returns a bare JSON array of events.
			if ( empty( $batch ) ) {
				break;
			}

			$events = array_merge( $events, $batch );

			if ( count( $batch ) < self::PAGE_SIZE ) {
				break;
			}
		}

		return $events;
	}

	/**
	 * Tests the connection by fetching the first page.
	 *
	 * @return array{ok:bool,count:int,sample:array,error:string}
	 */
	public function test(): array {
		try {
			$batch = $this->get_json( $this->events_url( 1 ) );
		} catch ( \RuntimeException $e ) {
			return array(
				'ok'     => false,
				'count'  => 0,
				'sample' => array(),
				'error'  => $e->getMessage(),
			);
		}

		return $this->preview( $batch );
	}

	/**
	 * Returns the available event categories ([{id,name}, ...]).
	 *
	 * @return array[]
	 */
	public function get_categories(): array {
		try {
			$query = array(
				'organizationId' => (string) ( $this->config['cd_org_id'] ?? '' ),
				'partnerToken'   => (string) ( $this->config['cd_token'] ?? '' ),
			);
			return $this->get_json( self::BASE_URI . '/events/categories?' . http_build_query( $query ) );
		} catch ( \RuntimeException $e ) {
			return array();
		}
	}

	/**
	 * Builds the /events request URL for a given page.
	 *
	 * @param int $page 1-based page marker.
	 */
	private function events_url( int $page ): string {
		$query = array(
			'organizationId' => (string) ( $this->config['cd_org_id'] ?? '' ),
			'partnerToken'   => (string) ( $this->config['cd_token'] ?? '' ),
			'itemsNumber'    => self::PAGE_SIZE,
			'pageMarker'     => $page,
			'imageFormat'    => (string) ( $this->config['cd_image_format'] ?? Mapper::DEFAULT_IMAGE_FORMAT ),
		);

		$categories = $this->config['cd_categories'] ?? array();
		if ( ! empty( $categories ) ) {
			$query['cid'] = implode( ',', array_map( 'intval', (array) $categories ) );
		}

		if ( ! empty( $this->config['cd_start_date'] ) ) {
			$query['startDate'] = (string) $this->config['cd_start_date'];
		}
		if ( ! empty( $this->config['cd_end_date'] ) ) {
			$query['endDate'] = (string) $this->config['cd_end_date'];
		}

		return self::BASE_URI . '/events?' . http_build_query( $query );
	}
}
