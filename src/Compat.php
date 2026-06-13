<?php
/**
 * Backward-compatibility shims.
 *
 * The plugin was refactored into the VEV\ namespace in 2.0.0. The legacy
 * global class names below remain as thin proxies so existing integrations,
 * theme snippets, and documented constants (e.g. VEV_Events::META_START_UTC,
 * VEV_Fields::get_field_value()) keep working unchanged. These shims are
 * retained for the 2.x series; see CHANGELOG.md.
 *
 * This file declares classes in the global namespace and is loaded explicitly
 * by the bootstrap (it is intentionally not PSR-4 autoloaded).
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This file groups several intentionally global, intentionally unprefixed
 * legacy class names that forward to the namespaced implementation. The sniffs
 * below conflict with that single-purpose shim pattern and are disabled here
 * only.
 */
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
// phpcs:disable Squiz.Commenting.FunctionComment.Missing
// phpcs:disable Generic.Commenting.DocComment.MissingShort

if ( ! class_exists( 'VEV_Events' ) ) {
	/**
	 * Legacy facade for plugin constants and settings/logging helpers.
	 */
	final class VEV_Events {

		public const VERSION    = \VEV\Constants::VERSION;
		public const TEXTDOMAIN = \VEV\Constants::TEXTDOMAIN;
		public const POST_TYPE  = \VEV\Constants::POST_TYPE;

		public const TAX_CATEGORY = \VEV\Constants::TAX_CATEGORY;
		public const TAX_LOCATION = \VEV\Constants::TAX_LOCATION;
		public const TAX_TOPIC    = \VEV\Constants::TAX_TOPIC;
		public const TAX_SERIES   = \VEV\Constants::TAX_SERIES;

		public const TERM_META_LOCATION_ADDRESS  = \VEV\Constants::TERM_META_LOCATION_ADDRESS;
		public const TERM_META_LOCATION_MAPS_URL = \VEV\Constants::TERM_META_LOCATION_MAPS_URL;
		public const TERM_META_CATEGORY_COLOR    = \VEV\Constants::TERM_META_CATEGORY_COLOR;

		public const META_START_UTC    = \VEV\Constants::META_START_UTC;
		public const META_END_UTC      = \VEV\Constants::META_END_UTC;
		public const META_ALL_DAY      = \VEV\Constants::META_ALL_DAY;
		public const META_HIDE_END     = \VEV\Constants::META_HIDE_END;
		public const META_SPEAKER      = \VEV\Constants::META_SPEAKER;
		public const META_SPECIAL      = \VEV\Constants::META_SPECIAL;
		public const META_INFO_URL     = \VEV\Constants::META_INFO_URL;
		public const META_EVENT_STATUS = \VEV\Constants::META_EVENT_STATUS;

		public const META_START_HOUR    = \VEV\Constants::META_START_HOUR;
		public const META_START_WEEKDAY = \VEV\Constants::META_START_WEEKDAY;
		public const META_START_DATE    = \VEV\Constants::META_START_DATE;
		public const META_START_MONTH   = \VEV\Constants::META_START_MONTH;
		public const META_TIME_SLOT     = \VEV\Constants::META_TIME_SLOT;

		public const VIRTUAL_START_DATE   = \VEV\Constants::VIRTUAL_START_DATE;
		public const VIRTUAL_START_TIME   = \VEV\Constants::VIRTUAL_START_TIME;
		public const VIRTUAL_END_DATE     = \VEV\Constants::VIRTUAL_END_DATE;
		public const VIRTUAL_END_TIME     = \VEV\Constants::VIRTUAL_END_TIME;
		public const VIRTUAL_DATE_RANGE   = \VEV\Constants::VIRTUAL_DATE_RANGE;
		public const VIRTUAL_TIME_RANGE   = \VEV\Constants::VIRTUAL_TIME_RANGE;
		public const VIRTUAL_DATETIME     = \VEV\Constants::VIRTUAL_DATETIME;
		public const VIRTUAL_STATUS       = \VEV\Constants::VIRTUAL_STATUS;
		public const VIRTUAL_IS_UPCOMING  = \VEV\Constants::VIRTUAL_IS_UPCOMING;
		public const VIRTUAL_IS_ONGOING   = \VEV\Constants::VIRTUAL_IS_ONGOING;
		public const VIRTUAL_STATUS_LABEL = \VEV\Constants::VIRTUAL_STATUS_LABEL;
		public const VIRTUAL_STATUS_COLOR = \VEV\Constants::VIRTUAL_STATUS_COLOR;
		public const VIRTUAL_IS_CANCELLED = \VEV\Constants::VIRTUAL_IS_CANCELLED;

		public const QV_SCOPE            = \VEV\Constants::QV_SCOPE;
		public const QV_INCLUDE_ARCHIVED = \VEV\Constants::QV_INCLUDE_ARCHIVED;
		public const QV_DATE_FROM        = \VEV\Constants::QV_DATE_FROM;
		public const QV_DATE_TO          = \VEV\Constants::QV_DATE_TO;
		public const QV_MONTH            = \VEV\Constants::QV_MONTH;
		public const QV_TIME_FROM        = \VEV\Constants::QV_TIME_FROM;
		public const QV_TIME_TO          = \VEV\Constants::QV_TIME_TO;
		public const QV_WEEKDAY          = \VEV\Constants::QV_WEEKDAY;

		public const OPTION_SETTINGS = \VEV\Constants::OPTION_SETTINGS;

		/**
		 * @return array<string,mixed>
		 */
		public static function get_settings(): array {
			return \VEV\Settings::get();
		}

		public static function flush_settings_cache(): void {
			\VEV\Settings::flush_cache();
		}

		/**
		 * @param string $message Message to log.
		 */
		public static function log( string $message ): void {
			\VEV\Plugin::log( $message );
		}
	}
}

if ( ! class_exists( 'VEV_Fields' ) ) {
	/**
	 * Legacy facade for the field registry.
	 */
	final class VEV_Fields {

		/**
		 * @return array<string,array<string,mixed>>
		 */
		public static function get_fields(): array {
			return \VEV\Fields\Registry::get_fields();
		}

		/**
		 * @param string $key Field key.
		 * @return array<string,mixed>|null
		 */
		public static function get_field( string $key ): ?array {
			return \VEV\Fields\Registry::get_field( $key );
		}

		/**
		 * @param string $key     Field key.
		 * @param int    $post_id Post ID.
		 * @return mixed
		 */
		public static function get_field_value( string $key, int $post_id = 0 ) {
			return \VEV\Fields\Registry::get_field_value( $key, $post_id );
		}

		/**
		 * @param string $key     Field key.
		 * @param int    $post_id Post ID.
		 */
		public static function get_formatted_value( string $key, int $post_id = 0 ): string {
			return \VEV\Fields\Registry::get_formatted_value( $key, $post_id );
		}

		/**
		 * @param bool $include_internal Whether to include internal fields.
		 * @return array<string,string>
		 */
		public static function get_fields_for_dropdown( bool $include_internal = false ): array {
			return \VEV\Fields\Registry::get_fields_for_dropdown( $include_internal );
		}
	}
}

if ( ! class_exists( 'VEV_Frontend' ) ) {
	/**
	 * Legacy facade for the frontend date/status helpers.
	 */
	final class VEV_Frontend {

		/**
		 * @param int $post_id Post ID.
		 * @return array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool}
		 */
		public static function get_event_data( int $post_id ): array {
			return \VEV\Support\EventData::get( $post_id );
		}

		/**
		 * @param int $start_utc Start timestamp.
		 * @param int $end_utc   End timestamp.
		 */
		public static function get_event_status( int $start_utc, int $end_utc ): string {
			return \VEV\Support\Lifecycle::status( $start_utc, $end_utc );
		}

		/**
		 * @param string $status Lifecycle status.
		 */
		public static function status_label( string $status ): string {
			return \VEV\Support\Lifecycle::label( $status );
		}

		/**
		 * @param int $ts_utc UTC timestamp.
		 */
		public static function format_date_only( int $ts_utc ): string {
			return \VEV\Support\DateFormatter::date_only( $ts_utc );
		}

		/**
		 * @param int $ts_utc UTC timestamp.
		 */
		public static function format_time_only( int $ts_utc ): string {
			return \VEV\Support\DateFormatter::time_only( $ts_utc );
		}

		/**
		 * @param array{start_utc:int,end_utc:int} $data Event data.
		 */
		public static function format_date_range( array $data ): string {
			return \VEV\Support\DateFormatter::date_range( $data );
		}

		/**
		 * @param array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool} $data Event data.
		 */
		public static function format_time_range( array $data ): string {
			return \VEV\Support\DateFormatter::time_range( $data );
		}

		/**
		 * @param array{start_utc:int,end_utc:int,all_day:bool,hide_end:bool} $data Event data.
		 */
		public static function format_datetime_full( array $data ): string {
			return \VEV\Support\DateFormatter::datetime_full( $data );
		}
	}
}

if ( ! class_exists( 'VEV_Post_Type' ) ) {
	/**
	 * Legacy facade for post type activation/deactivation and meta sync.
	 */
	final class VEV_Post_Type {

		public static function activate(): void {
			\VEV\PostType::activate();
		}

		public static function deactivate(): void {
			\VEV\PostType::deactivate();
		}

		/**
		 * @param mixed  $meta_id  Meta row ID.
		 * @param int    $post_id  Post ID.
		 * @param string $meta_key Meta key.
		 */
		public static function sync_computed_date_meta( $meta_id, int $post_id, string $meta_key ): void {
			\VEV\ComputedMeta::sync( $meta_id, $post_id, $meta_key );
		}
	}
}
