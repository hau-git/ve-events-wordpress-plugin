<?php
/**
 * Registers the read-only virtual REST fields for events.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the plugin's computed meta as REST API fields.
 */
final class RestFields {

	/**
	 * Register the rest_api_init hook.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register each virtual field on the event post type.
	 */
	public static function register(): void {
		$fields = array(
			've_start_date',
			've_start_time',
			've_end_date',
			've_end_time',
			've_date_range',
			've_time_range',
			've_datetime_formatted',
			've_status',
			've_location_name',
			've_location_address',
			've_location_maps_url',
			've_category_name',
			've_category_color',
			've_series_name',
			've_topic_names',
		);

		foreach ( $fields as $field ) {
			register_rest_field(
				Constants::POST_TYPE,
				$field,
				array(
					'get_callback' => static function ( array $object ) use ( $field ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
						return get_post_meta( (int) $object['id'], $field, true );
					},
					'schema'       => array( 'type' => 'string' ),
				)
			);
		}
	}
}
