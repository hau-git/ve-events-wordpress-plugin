<?php
/**
 * ICal Import Runner — fetches and parses an ICS feed into normalised rows.
 *
 * @package VE_Events
 */

namespace VEV\Import;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Concrete runner for ICS/iCal feeds.
 *
 * Wraps the vendored ICal parser, groups occurrences by UID to detect recurring
 * series, maps each occurrence with {@see FieldMapper}, and hands normalised rows
 * to {@see AbstractRunner}.
 */
class IcsRunner extends AbstractRunner {

	/**
	 * Fetches and parses the ICS feed into normalised event rows.
	 *
	 * @return array|null Normalised rows, [] when empty, or null on fetch error.
	 */
	protected function fetch_events(): ?array {
		if ( empty( $this->config['url'] ) ) {
			$this->add_error( __( 'No ICS URL configured.', 've-events' ) );
			return null;
		}

		try {
			$ical = new \VEV_Import\ICal();
			$ical->initUrl( $this->config['url'], null, null, null, null, null, true );
		} catch ( \Exception $e ) {
			$this->add_error( $e->getMessage() );
			return null;
		}

		if ( ! $ical->hasEvents() ) {
			return array();
		}

		$rows   = array();
		$groups = $this->group_by_uid( $ical->events() );

		foreach ( $groups as $uid => $group_events ) {
			$is_series   = count( $group_events ) > 1;
			$series_term = $is_series ? $this->ensure_series_term( $group_events[0] ) : null;

			foreach ( $group_events as $event ) {
				$row = $this->build_row( $event, $is_series, (string) $uid, $series_term );
				if ( $row ) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * Builds a normalised row from a single ICS occurrence.
	 *
	 * @param  \VEV_Import\Event $event       Parsed ICS event.
	 * @param  bool              $is_series   Whether the event belongs to a series.
	 * @param  string            $base_uid    Base UID shared by series occurrences.
	 * @param  \WP_Term|null     $series_term Series taxonomy term, if any.
	 * @return array|null
	 */
	private function build_row( \VEV_Import\Event $event, bool $is_series, string $base_uid, ?\WP_Term $series_term ): ?array {
		// Build a stable, unique import UID for this specific occurrence.
		$import_uid = $is_series
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- hashing an occurrence signature, not storing user data.
			? $base_uid . '__' . ( $event->dtstart ?? md5( serialize( $event ) ) )
			: $base_uid;

		try {
			$mapped = FieldMapper::map( $event, $this->config['field_map'], $this->config );
		} catch ( \Exception $e ) {
			$this->add_error( sprintf( 'Mapping error for UID %s: %s', $import_uid, $e->getMessage() ) );
			++$this->counts['skipped'];
			return null;
		}

		return array(
			'uid'            => $import_uid,
			'post_data'      => $mapped['post_data'],
			'meta'           => $mapped['meta'],
			'taxonomies'     => $mapped['taxonomies'],
			'import_hash'    => $mapped['import_hash'],
			'last_modified'  => $event->last_modified ? (int) strtotime( $event->last_modified ) : 0,
			'image_url'      => null,
			'series_term_id' => $series_term ? (int) $series_term->term_id : null,
			'force_draft'    => ( $event->status ?? '' ) === 'CANCELLED',
		);
	}

	/**
	 * Groups events by their UID. Events with the same UID are RRULE instances.
	 *
	 * @param  \VEV_Import\Event[] $events Parsed ICS events.
	 * @return array<string, \VEV_Import\Event[]>
	 */
	private function group_by_uid( array $events ): array {
		$groups = array();
		foreach ( $events as $event ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- hashing an occurrence signature, not storing user data.
			$uid              = $event->uid ?? md5( serialize( $event ) );
			$groups[ $uid ][] = $event;
		}
		return $groups;
	}

	/**
	 * Ensures a ve_event_series term exists for recurring events.
	 * Uses the SUMMARY as the series name. Returns the WP_Term.
	 *
	 * @param \VEV_Import\Event $event Parsed ICS event.
	 */
	private function ensure_series_term( \VEV_Import\Event $event ): ?\WP_Term {
		$name = wp_strip_all_tags( $event->summary ?? __( 'Unnamed Series', 've-events' ) );

		$term = get_term_by( 'name', $name, Constants::TAX_SERIES );
		if ( $term ) {
			return $term;
		}

		$result = wp_insert_term( $name, Constants::TAX_SERIES );
		if ( is_wp_error( $result ) ) {
			$this->add_error( sprintf( 'Could not create series term "%s": %s', $name, $result->get_error_message() ) );
			return null;
		}

		return get_term( $result['term_id'], Constants::TAX_SERIES );
	}
}
