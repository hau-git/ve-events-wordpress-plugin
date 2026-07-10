<?php
/**
 * Central constant registry for the VE Events plugin.
 *
 * Holds every public identifier (version, text domain, post type, taxonomies,
 * meta keys, virtual field keys, query vars, options) as a single source of
 * truth. The legacy `VEV_Events` class re-exposes these for backward
 * compatibility — see src/Compat.php.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable registry of the plugin's public identifiers.
 */
final class Constants {

	public const VERSION = '2.3.0';

	public const TEXTDOMAIN = 've-events';
	public const POST_TYPE  = 've_event';

	public const TAX_CATEGORY = 've_event_category';
	public const TAX_LOCATION = 've_event_location';
	public const TAX_TOPIC    = 've_event_topic';
	public const TAX_SERIES   = 've_event_series';

	// Term meta keys.
	public const TERM_META_LOCATION_ADDRESS  = 've_location_address';
	public const TERM_META_LOCATION_MAPS_URL = 've_location_maps_url';
	public const TERM_META_CATEGORY_COLOR    = 've_category_color';

	public const META_START_UTC = '_vev_start_utc';
	public const META_END_UTC   = '_vev_end_utc';
	public const META_ALL_DAY   = '_vev_all_day';
	public const META_HIDE_END  = '_vev_hide_end';
	public const META_SPEAKER   = '_vev_speaker';
	public const META_SPECIAL   = '_vev_special_info';
	public const META_INFO_URL  = '_vev_info_url';
	// Override values: cancelled, postponed, rescheduled, movedOnline, or empty.
	public const META_EVENT_STATUS = '_vev_event_status';

	// Organizer & offer/ticket meta.
	public const META_ORGANIZER       = '_vev_organizer';
	public const META_ORGANIZER_URL   = '_vev_organizer_url';
	public const META_PRICE           = '_vev_price';          // Numeric string, or '' when unknown.
	public const META_PRICE_CURRENCY  = '_vev_price_currency'; // ISO 4217, e.g. EUR.
	public const META_AVAILABILITY    = '_vev_availability';   // InStock | SoldOut | PreOrder | ''.
	public const META_ATTENDANCE_MODE = '_vev_attendance_mode'; // online | mixed | '' (offline).

	// Computed/stored date meta (auto-synced from META_START_UTC).
	public const META_START_HOUR    = '_vev_start_hour';    // 0–23, local timezone.
	public const META_START_WEEKDAY = '_vev_weekday';       // 1=Mon … 7=Sun (ISO).
	public const META_START_DATE    = '_vev_start_date';    // Y-m-d, local timezone (JetEngine date range filter).
	public const META_START_MONTH   = '_vev_start_month';   // 1–12, local timezone (JetEngine month filter).
	public const META_TIME_SLOT     = '_vev_time_slot';     // morning|afternoon|evening|night (JetEngine time-of-day filter).

	// Virtual meta keys (ve_ prefix) - computed at runtime.
	public const VIRTUAL_START_DATE      = 've_start_date';
	public const VIRTUAL_START_TIME      = 've_start_time';
	public const VIRTUAL_END_DATE        = 've_end_date';
	public const VIRTUAL_END_TIME        = 've_end_time';
	public const VIRTUAL_DATE_RANGE      = 've_date_range';
	public const VIRTUAL_TIME_RANGE      = 've_time_range';
	public const VIRTUAL_DATETIME        = 've_datetime_formatted';
	public const VIRTUAL_STATUS          = 've_status';
	public const VIRTUAL_IS_UPCOMING     = 've_is_upcoming';
	public const VIRTUAL_IS_ONGOING      = 've_is_ongoing';
	public const VIRTUAL_STATUS_LABEL    = 've_event_status_label';
	public const VIRTUAL_STATUS_COLOR    = 've_event_status_color';
	public const VIRTUAL_IS_CANCELLED    = 've_is_cancelled';
	public const VIRTUAL_PRICE_FORMATTED = 've_price_formatted';
	public const VIRTUAL_ICAL_URL        = 've_ical_url';

	// Virtual taxonomy-derived keys.
	public const VIRTUAL_LOCATION_NAME     = 've_location_name';
	public const VIRTUAL_LOCATION_ADDRESS  = 've_location_address';
	public const VIRTUAL_LOCATION_MAPS_URL = 've_location_maps_url';
	public const VIRTUAL_CATEGORY_NAME     = 've_category_name';
	public const VIRTUAL_CATEGORY_COLOR    = 've_category_color';
	public const VIRTUAL_CATEGORY_CLASS    = 've_category_class';
	public const VIRTUAL_SERIES_NAME       = 've_series_name';
	public const VIRTUAL_TOPIC_NAMES       = 've_topic_names';

	public const QV_SCOPE            = 'vev_event_scope';
	public const QV_INCLUDE_ARCHIVED = 'vev_include_archived';
	public const QV_DATE_FROM        = 'vev_date_from';  // Y-m-d or UTC timestamp.
	public const QV_DATE_TO          = 'vev_date_to';    // Y-m-d or UTC timestamp.
	public const QV_MONTH            = 'vev_month';      // YYYY-MM.
	public const QV_TIME_FROM        = 'vev_time_from';  // 0–23.
	public const QV_TIME_TO          = 'vev_time_to';    // 0–23.
	public const QV_WEEKDAY          = 'vev_weekday';    // 1–7 or comma-separated "1,3,5".

	// iCal export endpoint query vars.
	public const QV_ICS     = 'vev_ics';     // Post ID (single event) or "feed".
	public const QV_ICS_CAT = 'vev_ics_cat'; // Optional category slug filter for the feed.

	public const OPTION_SETTINGS = 'vev_settings';
}
