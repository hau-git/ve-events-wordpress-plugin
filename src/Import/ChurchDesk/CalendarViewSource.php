<?php
/**
 * ChurchDesk calendar-view source (public portal/embed endpoint).
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads events from the ChurchDesk collaboration calendar-view endpoint — the
 * source that powers ChurchDesk's public portal/embed calendar.
 *
 * Endpoint:   https://api2.churchdesk.com/collaboration/calendar-view
 * Auth:       filters[0][organizationId] (no partner token)
 * Pagination: limit + offset
 *
 * This endpoint is not part of the documented, versioned Pull API and its exact
 * JSON shape can differ, so {@see normalize()} defensively remaps each raw item
 * onto the canonical event shape consumed by {@see Mapper}.
 */
class CalendarViewSource extends AbstractSource {

	/**
	 * Calendar-view endpoint URL.
	 */
	const BASE_URI = 'https://api2.churchdesk.com/collaboration/calendar-view';

	/**
	 * Fetches all events across pages and returns them in canonical shape.
	 *
	 * @return array[]
	 * @throws \RuntimeException On a hard fetch error.
	 */
	public function fetch(): array {
		$events = array();

		for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
			$batch = $this->get_json( $this->calendar_url( $page * self::PAGE_SIZE ) );
			$items = $this->extract_items( $batch );

			if ( empty( $items ) ) {
				break;
			}

			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$events[] = self::normalize( $item );
				}
			}

			if ( count( $items ) < self::PAGE_SIZE ) {
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
			$batch = $this->get_json( $this->calendar_url( 0 ) );
		} catch ( \RuntimeException $e ) {
			return array(
				'ok'     => false,
				'count'  => 0,
				'sample' => array(),
				'error'  => $e->getMessage(),
			);
		}

		$events = array();
		foreach ( $this->extract_items( $batch ) as $item ) {
			if ( is_array( $item ) ) {
				$events[] = self::normalize( $item );
			}
		}

		return $this->preview( $events );
	}

	/**
	 * Builds the calendar-view request URL for a given offset.
	 *
	 * @param int $offset Zero-based record offset.
	 */
	private function calendar_url( int $offset ): string {
		$query = array(
			'imageFormat' => (string) ( $this->config['cd_image_format'] ?? Mapper::DEFAULT_IMAGE_FORMAT ),
			'limit'       => self::PAGE_SIZE,
			'offset'      => $offset,
			'filters'     => array(
				array( 'organizationId' => (string) ( $this->config['cd_org_id'] ?? '' ) ),
			),
		);

		return self::BASE_URI . '?' . http_build_query( $query );
	}

	/**
	 * Extracts the list of raw event items from a calendar-view response,
	 * tolerating both a bare array and a wrapped `{ data: [...] }` shape.
	 *
	 * @param array $batch Decoded response.
	 */
	private function extract_items( array $batch ): array {
		if ( isset( $batch['data'] ) && is_array( $batch['data'] ) ) {
			return $batch['data'];
		}
		if ( isset( $batch['events'] ) && is_array( $batch['events'] ) ) {
			return $batch['events'];
		}
		return $batch;
	}

	/**
	 * Remaps a raw calendar-view item onto the canonical v3.0.0 event shape.
	 *
	 * The calendar-view payload is undocumented; this favours the canonical
	 * field names and falls back to common alternatives.
	 *
	 * @param  array $raw Raw calendar-view item.
	 * @return array      Canonical event.
	 */
	public static function normalize( array $raw ): array {
		$location_obj  = $raw['locationObj'] ?? ( is_array( $raw['location'] ?? null ) ? $raw['location'] : null );
		$location_str  = is_string( $raw['location'] ?? null ) ? $raw['location'] : ( $raw['locationAddress'] ?? '' );
		$location_name = $raw['locationName'] ?? ( is_array( $raw['location'] ?? null ) ? ( $raw['location']['name'] ?? '' ) : '' );

		return array(
			'id'           => $raw['id'] ?? $raw['eventId'] ?? '',
			'title'        => $raw['title'] ?? $raw['name'] ?? '',
			'description'  => $raw['description'] ?? $raw['body'] ?? '',
			'summary'      => $raw['summary'] ?? '',
			'startDate'    => $raw['startDate'] ?? $raw['start'] ?? $raw['startsAt'] ?? null,
			'endDate'      => $raw['endDate'] ?? $raw['end'] ?? $raw['endsAt'] ?? null,
			'allDay'       => $raw['allDay'] ?? $raw['fullDay'] ?? false,
			'showEndtime'  => $raw['showEndtime'] ?? null,
			'hideEndTime'  => $raw['hideEndTime'] ?? null,
			'contributor'  => $raw['contributor'] ?? '',
			'price'        => $raw['price'] ?? null,
			'locationName' => $location_name,
			'locationObj'  => $location_obj,
			'location'     => $location_str,
			'categories'   => $raw['categories'] ?? array(),
			'image'        => $raw['image'] ?? array(),
			'updatedAt'    => $raw['updatedAt'] ?? $raw['updated'] ?? '',
		);
	}
}
