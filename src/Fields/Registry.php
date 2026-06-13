<?php
/**
 * Field registry for the VE Events plugin.
 *
 * Defines the canonical map of event fields (raw meta, virtual, and stored)
 * and exposes resolution/formatting helpers consumed by integrations such as
 * JetEngine. Formatting and status logic is delegated to the shared Support
 * single-source-of-truth layer.
 *
 * @package VE_Events
 */

namespace VEV\Fields;

use VEV\Constants;
use VEV\Support\DateFormatter;
use VEV\Support\EventData;
use VEV\Support\EventStatus;
use VEV\Support\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and resolves the plugin's event fields.
 */
final class Registry {

	/**
	 * The field definition map, keyed by meta/virtual key.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $fields = array();

	/**
	 * Initialize the registry.
	 */
	public static function init(): void {
		self::register_fields();
	}

	/**
	 * Build the canonical field definition map.
	 */
	private static function register_fields(): void {
		self::$fields = array(
			'_vev_start_utc'              => array(
				'label'  => __( 'Start Date/Time', 've-events' ),
				'type'   => 'datetime',
				'format' => 'datetime',
				'group'  => 'date',
			),
			'_vev_end_utc'                => array(
				'label'  => __( 'End Date/Time', 've-events' ),
				'type'   => 'datetime',
				'format' => 'datetime',
				'group'  => 'date',
			),
			'_vev_all_day'                => array(
				'label'  => __( 'All Day Event', 've-events' ),
				'type'   => 'boolean',
				'format' => 'yesno',
				'group'  => 'date',
			),
			'_vev_hide_end'               => array(
				'label'  => __( 'Hide End Time', 've-events' ),
				'type'   => 'boolean',
				'format' => 'yesno',
				'group'  => 'date',
			),
			'_vev_speaker'                => array(
				'label'  => __( 'Speaker', 've-events' ),
				'type'   => 'text',
				'format' => 'text',
				'group'  => 'details',
			),
			'_vev_special_info'           => array(
				'label'  => __( 'Special Info', 've-events' ),
				'type'   => 'textarea',
				'format' => 'text',
				'group'  => 'details',
			),
			'_vev_info_url'               => array(
				'label'  => __( 'Info URL', 've-events' ),
				'type'   => 'url',
				'format' => 'url',
				'group'  => 'details',
			),
			've_start_date'               => array(
				'label'    => __( 'Start Date (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'date',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_start_date' ),
			),
			've_start_time'               => array(
				'label'    => __( 'Start Time (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'time',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_start_time' ),
			),
			've_end_date'                 => array(
				'label'    => __( 'End Date (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'date',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_end_date' ),
			),
			've_end_time'                 => array(
				'label'    => __( 'End Time (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'time',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_end_time' ),
			),
			've_date_range'               => array(
				'label'    => __( 'Date Range', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_date_range' ),
			),
			've_time_range'               => array(
				'label'    => __( 'Time Range', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_time_range' ),
			),
			've_datetime_formatted'       => array(
				'label'    => __( 'Full Date & Time', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_datetime_formatted' ),
			),
			've_status'                   => array(
				'label'    => __( 'Event Status', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_status' ),
			),
			've_is_upcoming'              => array(
				'label'    => __( 'Is Upcoming', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_is_upcoming' ),
			),
			've_is_ongoing'               => array(
				'label'    => __( 'Is Ongoing', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_is_ongoing' ),
			),
			've_location_name'            => array(
				'label'    => __( 'Location Name', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_location_name' ),
			),
			've_location_address'         => array(
				'label'    => __( 'Location Address', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_location_address' ),
			),
			've_location_maps_url'        => array(
				'label'    => __( 'Location Maps URL', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'url',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_location_maps_url' ),
			),
			've_category_name'            => array(
				'label'    => __( 'Category Name', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_category_name' ),
			),
			've_category_color'           => array(
				'label'    => __( 'Category Color', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_category_color' ),
			),
			've_series_name'              => array(
				'label'    => __( 'Series Name', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_series_name' ),
			),
			've_topic_names'              => array(
				'label'    => __( 'Topic Name(s)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_topic_names' ),
			),
			've_category_class'           => array(
				'label'    => __( 'Category CSS Class', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'taxonomy',
				'callback' => array( __CLASS__, 'get_category_class' ),
			),

			// Event status override.
			've_event_status_label'       => array(
				'label'    => __( 'Event Status Label', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_event_status_label' ),
			),
			've_event_status_color'       => array(
				'label'    => __( 'Event Status Color', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_event_status_color' ),
			),
			've_is_cancelled'             => array(
				'label'    => __( 'Is Cancelled', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'boolean',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_is_cancelled' ),
			),

			// Computed date meta — stored, directly filterable in JetEngine.
			Constants::META_START_HOUR    => array(
				'label'  => __( 'Start Hour (0–23)', 've-events' ),
				'type'   => 'stored',
				'format' => 'text',
				'group'  => 'date',
			),
			Constants::META_START_WEEKDAY => array(
				'label'  => __( 'Weekday (1=Mon, 7=Sun)', 've-events' ),
				'type'   => 'stored',
				'format' => 'text',
				'group'  => 'date',
			),
		);
	}

	/**
	 * Get the full field definition map, registering it on first access.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields(): array {
		if ( empty( self::$fields ) ) {
			self::register_fields();
		}
		return self::$fields;
	}

	/**
	 * Get a single field definition by key.
	 *
	 * @param string $key Field key.
	 * @return array<string,mixed>|null
	 */
	public static function get_field( string $key ): ?array {
		$fields = self::get_fields();
		return $fields[ $key ] ?? null;
	}

	/**
	 * Resolve a field's raw value for a post.
	 *
	 * @param string $key     Field key.
	 * @param int    $post_id Post ID (defaults to the current post).
	 */
	public static function get_field_value( string $key, int $post_id = 0 ): mixed {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return '';
		}

		$field = self::get_field( $key );
		if ( ! $field ) {
			return get_post_meta( $post_id, $key, true );
		}

		if ( isset( $field['callback'] ) && is_callable( $field['callback'] ) ) {
			return call_user_func( $field['callback'], $post_id );
		}

		return get_post_meta( $post_id, $key, true );
	}

	/**
	 * Resolve a field's display-formatted value for a post.
	 *
	 * @param string $key     Field key.
	 * @param int    $post_id Post ID (defaults to the current post).
	 */
	public static function get_formatted_value( string $key, int $post_id = 0 ): string {
		$value = self::get_field_value( $key, $post_id );
		$field = self::get_field( $key );

		if ( ! $field ) {
			return (string) $value;
		}

		if ( isset( $field['callback'] ) ) {
			return (string) $value;
		}

		switch ( $field['format'] ) {
			case 'datetime':
				if ( empty( $value ) ) {
					return '';
				}
				return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $value, wp_timezone() );

			case 'date':
				return DateFormatter::date_only( (int) $value );

			case 'time':
				return DateFormatter::time_only( (int) $value );

			case 'yesno':
				return $value ? __( 'Yes', 've-events' ) : __( 'No', 've-events' );

			case 'url':
				return esc_url( $value );

			default:
				return (string) $value;
		}
	}

	/**
	 * Formatted start date in the site timezone.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_start_date( int $post_id ): string {
		$start = (int) get_post_meta( $post_id, Constants::META_START_UTC, true );
		return DateFormatter::date_only( $start );
	}

	/**
	 * Formatted start time, suppressed for all-day events.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_start_time( int $post_id ): string {
		$all_day = (int) get_post_meta( $post_id, Constants::META_ALL_DAY, true );
		if ( $all_day ) {
			return '';
		}
		$start = (int) get_post_meta( $post_id, Constants::META_START_UTC, true );
		return DateFormatter::time_only( $start );
	}

	/**
	 * Formatted end date in the site timezone.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_end_date( int $post_id ): string {
		$end = (int) get_post_meta( $post_id, Constants::META_END_UTC, true );
		return DateFormatter::date_only( $end );
	}

	/**
	 * Formatted end time, suppressed for all-day or hidden-end events.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_end_time( int $post_id ): string {
		$all_day  = (int) get_post_meta( $post_id, Constants::META_ALL_DAY, true );
		$hide_end = (int) get_post_meta( $post_id, Constants::META_HIDE_END, true );
		if ( $all_day || $hide_end ) {
			return '';
		}
		$end = (int) get_post_meta( $post_id, Constants::META_END_UTC, true );
		return DateFormatter::time_only( $end );
	}

	/**
	 * Smart date range for the event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_date_range( int $post_id ): string {
		$data = EventData::get( $post_id );
		return DateFormatter::date_range( $data );
	}

	/**
	 * Time range (or "All day") for the event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_time_range( int $post_id ): string {
		$data = EventData::get( $post_id );
		return DateFormatter::time_range( $data );
	}

	/**
	 * Full, human-readable date & time for the event.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_datetime_formatted( int $post_id ): string {
		$data = EventData::get( $post_id );
		return DateFormatter::datetime_full( $data );
	}

	/**
	 * Human-readable lifecycle status label.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_status( int $post_id ): string {
		$data   = EventData::get( $post_id );
		$status = Lifecycle::status( $data['start_utc'], $data['end_utc'] );
		return Lifecycle::label( $status );
	}

	/**
	 * Whether the event is upcoming.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_is_upcoming( int $post_id ): bool {
		$data   = EventData::get( $post_id );
		$status = Lifecycle::status( $data['start_utc'], $data['end_utc'] );
		return 'upcoming' === $status;
	}

	/**
	 * Whether the event is ongoing.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_is_ongoing( int $post_id ): bool {
		$data   = EventData::get( $post_id );
		$status = Lifecycle::status( $data['start_utc'], $data['end_utc'] );
		return 'ongoing' === $status;
	}

	/**
	 * Primary location term name.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_location_name( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_LOCATION );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return $terms[0]->name;
	}

	/**
	 * Primary location address term meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_location_address( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_LOCATION );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return (string) get_term_meta( $terms[0]->term_id, Constants::TERM_META_LOCATION_ADDRESS, true );
	}

	/**
	 * Maps URL for the primary location, derived from the address when unset.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_location_maps_url( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_LOCATION );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		$term_id = $terms[0]->term_id;
		$custom  = (string) get_term_meta( $term_id, Constants::TERM_META_LOCATION_MAPS_URL, true );
		if ( $custom ) {
			return $custom;
		}
		$address = (string) get_term_meta( $term_id, Constants::TERM_META_LOCATION_ADDRESS, true );
		if ( $address ) {
			return 'https://maps.google.com/?q=' . rawurlencode( $address );
		}
		return '';
	}

	/**
	 * Primary category term name.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_category_name( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_CATEGORY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return $terms[0]->name;
	}

	/**
	 * Primary category color term meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_category_color( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_CATEGORY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return (string) get_term_meta( $terms[0]->term_id, Constants::TERM_META_CATEGORY_COLOR, true );
	}

	/**
	 * Primary series term name.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_series_name( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_SERIES );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return $terms[0]->name;
	}

	/**
	 * Comma-separated topic term names.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_topic_names( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_TOPIC );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * CSS class for the primary category.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_category_class( int $post_id ): string {
		$terms = get_the_terms( $post_id, Constants::TAX_CATEGORY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return 've-cat-' . sanitize_html_class( $terms[0]->slug );
	}

	/**
	 * Human-readable manual status override label.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_event_status_label( int $post_id ): string {
		return EventStatus::label( EventStatus::for_post( $post_id ) );
	}

	/**
	 * Color for the manual status override.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_event_status_color( int $post_id ): string {
		return EventStatus::color( EventStatus::for_post( $post_id ) );
	}

	/**
	 * Whether the event is manually marked cancelled.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get_is_cancelled( int $post_id ): bool {
		return 'cancelled' === EventStatus::for_post( $post_id );
	}

	/**
	 * Build grouped field options for an admin dropdown.
	 *
	 * @param bool $include_internal Whether to include internal/raw fields.
	 * @return array<string,string>
	 */
	public static function get_fields_for_dropdown( bool $include_internal = false ): array {
		$fields  = self::get_fields();
		$options = array();

		$groups = array(
			'taxonomy'  => __( 'Location & Taxonomy', 've-events' ),
			'formatted' => __( 'Formatted Output', 've-events' ),
			'details'   => __( 'Event Details', 've-events' ),
			'status'    => __( 'Event Status', 've-events' ),
		);

		if ( $include_internal ) {
			$groups['date'] = __( 'Raw Data', 've-events' );
		}

		foreach ( $groups as $group_key => $group_label ) {
			foreach ( $fields as $key => $field ) {
				if ( ( $field['group'] ?? '' ) !== $group_key ) {
					continue;
				}
				if ( 'boolean' === ( $field['type'] ?? '' ) && ! $include_internal ) {
					continue;
				}
				$options[ $key ] = $group_label . ': ' . $field['label'];
			}
		}

		return $options;
	}
}
