<?php
/**
 * Plugin settings storage, defaults, caching, and sanitization.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, caches, defaults, and sanitizes the plugin settings option.
 */
final class Settings {

	/**
	 * Per-request cache of merged settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'disable_gutenberg'      => false,
			'hide_end_same_day'      => true,
			'grace_period'           => 24,
			'hide_archived_search'   => true,
			'include_series_schema'  => true,
			'slug_single'            => 'event',
			'slug_archive'           => 'events',
			'series_suggestions'     => false,
			'output_category_colors' => true,
			'og_tags'                => 'auto', // One of: auto, always, disabled.
		);
	}

	/**
	 * Merged settings (stored option over defaults), cached per request.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		self::$cache = wp_parse_args( get_option( Constants::OPTION_SETTINGS, array() ), self::defaults() );
		return self::$cache;
	}

	/**
	 * Clear the per-request settings cache (called after the option is saved).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Sanitize the settings array before it is written to the database.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$sanitized                          = array();
		$sanitized['disable_gutenberg']     = ! empty( $input['disable_gutenberg'] );
		$sanitized['hide_end_same_day']     = ! empty( $input['hide_end_same_day'] );
		$sanitized['grace_period']          = absint( $input['grace_period'] ?? 24 );
		$sanitized['hide_archived_search']  = ! empty( $input['hide_archived_search'] );
		$sanitized['include_series_schema'] = ! empty( $input['include_series_schema'] );

		$slug_single  = isset( $input['slug_single'] ) ? sanitize_title( $input['slug_single'] ) : '';
		$slug_archive = isset( $input['slug_archive'] ) ? sanitize_title( $input['slug_archive'] ) : '';

		$reserved_slugs = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'author', 'order', 'theme', 'category', 'tag', 'admin', 'wp-admin', 'wp-content', 'wp-includes' );

		if ( '' === $slug_single || in_array( $slug_single, $reserved_slugs, true ) || is_numeric( $slug_single ) ) {
			$slug_single = 'event';
		}
		if ( '' === $slug_archive || in_array( $slug_archive, $reserved_slugs, true ) || is_numeric( $slug_archive ) ) {
			$slug_archive = 'events';
		}

		if ( $slug_single === $slug_archive ) {
			$slug_archive = $slug_single . 's';
			if ( '' === $slug_archive || 's' === $slug_archive ) {
				$slug_archive = 'events';
			}
		}

		$sanitized['slug_single']            = $slug_single;
		$sanitized['slug_archive']           = $slug_archive;
		$sanitized['series_suggestions']     = ! empty( $input['series_suggestions'] );
		$sanitized['output_category_colors'] = ! empty( $input['output_category_colors'] );

		$og_tags_raw          = $input['og_tags'] ?? 'auto';
		$sanitized['og_tags'] = in_array( $og_tags_raw, array( 'auto', 'always', 'disabled' ), true ) ? $og_tags_raw : 'auto';

		return $sanitized;
	}
}
