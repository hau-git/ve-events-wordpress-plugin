<?php
/**
 * Resolves virtual ve_* meta keys at read time via get_post_metadata.
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
 * Short-circuits get_post_meta() for the plugin's computed virtual fields.
 */
final class ComputedMetaFilter {

	/**
	 * Register the metadata filter.
	 */
	public static function init(): void {
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter' ), 10, 4 );
	}

	/**
	 * Compute a virtual field value when the requested meta key is registered.
	 *
	 * @param mixed  $value     The existing meta value (or null to defer).
	 * @param int    $object_id Post ID being queried.
	 * @param string $meta_key  Meta key being requested.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public static function filter( $value, int $object_id, string $meta_key, bool $single ) {
		$field = Registry::get_field( $meta_key );
		if ( ! $field || ! isset( $field['callback'] ) ) {
			return $value;
		}
		if ( Constants::POST_TYPE !== get_post_type( $object_id ) ) {
			return $value;
		}
		$result = Registry::get_field_value( $meta_key, $object_id );
		return $single ? $result : array( $result );
	}
}
