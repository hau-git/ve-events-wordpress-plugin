<?php
/**
 * Outputs Schema.org Event JSON-LD for single events.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

use VEV\Constants;
use VEV\Settings;
use VEV\Support\DateFormatter;
use VEV\Support\EventData;
use VEV\Support\EventDescription;
use VEV\Support\EventStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits the Schema.org Event JSON-LD block in the document head.
 */
final class SchemaOutput {

	/**
	 * Register the wp_head output.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 99 );
	}

	/**
	 * Print the Schema.org Event JSON-LD.
	 */
	public static function output(): void {
		if ( ! is_singular( Constants::POST_TYPE ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$data = EventData::get( $post_id );
		if ( ! $data['start_utc'] ) {
			return;
		}

		$tz = wp_timezone();

		$start_iso = DateFormatter::schema( $data['start_utc'], $data['all_day'], $tz );
		$end_iso   = DateFormatter::schema( $data['end_utc'], $data['all_day'], $tz );

		$event_status_val = (string) get_post_meta( $post_id, Constants::META_EVENT_STATUS, true );

		$event = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => get_the_title( $post_id ),
			'description' => EventDescription::get( $post_id ),
			'url'         => get_permalink( $post_id ),
			'startDate'   => $start_iso,
			'endDate'     => $end_iso,
			'eventStatus' => EventStatus::schema_uri( $event_status_val ),
		);

		$img = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $img ) {
			$event['image'] = array( $img );
		}

		$loc_terms = get_the_terms( $post_id, Constants::TAX_LOCATION );
		if ( is_array( $loc_terms ) && ! empty( $loc_terms ) ) {
			$loc     = $loc_terms[0];
			$address = (string) get_term_meta( $loc->term_id, Constants::TERM_META_LOCATION_ADDRESS, true );
			$place   = array(
				'@type' => 'Place',
				'name'  => $loc->name,
			);
			if ( $address ) {
				$place['address'] = array(
					'@type'         => 'PostalAddress',
					'streetAddress' => $address,
				);
			}
			$event['location'] = $place;
		}

		$speaker = (string) get_post_meta( $post_id, Constants::META_SPEAKER, true );
		if ( '' !== $speaker ) {
			$event['performer'] = array(
				'@type' => 'Person',
				'name'  => $speaker,
			);
		}

		$info_url = (string) get_post_meta( $post_id, Constants::META_INFO_URL, true );
		if ( '' !== $info_url ) {
			$event['offers'] = array(
				'@type' => 'Offer',
				'url'   => $info_url,
			);
		}

		$settings = Settings::get();
		if ( ! empty( $settings['include_series_schema'] ) ) {
			$series_terms = get_the_terms( $post_id, Constants::TAX_SERIES );
			if ( is_array( $series_terms ) && ! empty( $series_terms ) ) {
				$series              = $series_terms[0];
				$event['superEvent'] = array(
					'@type' => 'EventSeries',
					'name'  => $series->name,
					'url'   => get_term_link( $series ),
				);
			}
		}

		$event = apply_filters( 've_schema_event', $event, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		printf(
			"\n<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}
}
