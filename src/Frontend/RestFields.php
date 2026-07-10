<?php
/**
 * Registers the read-only virtual REST fields for events.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

use VEV\Constants;
use VEV\Fields\Registry;

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
	 * Register every callback-backed (virtual) registry field on the event
	 * post type, so REST exposure stays in sync with the field registry
	 * instead of a second hand-maintained list.
	 *
	 * Boolean-typed fields are registered as JSON booleans; everything else
	 * keeps the historical string representation (resolved via get_post_meta,
	 * which routes through ComputedMetaFilter → Registry).
	 */
	public static function register(): void {
		foreach ( Registry::get_fields() as $key => $field ) {
			if ( empty( $field['callback'] ) ) {
				continue;
			}

			$is_bool = 'boolean' === ( $field['type'] ?? '' );

			register_rest_field(
				Constants::POST_TYPE,
				$key,
				array(
					'get_callback' => static function ( array $object ) use ( $key, $is_bool ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
						$value = get_post_meta( (int) $object['id'], $key, true );
						return $is_bool ? (bool) $value : (string) $value;
					},
					'schema'       => array( 'type' => $is_bool ? 'boolean' : 'string' ),
				)
			);
		}
	}
}
