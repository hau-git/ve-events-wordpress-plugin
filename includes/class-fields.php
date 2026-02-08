<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VEV_Fields {

	private static array $fields = array();

	public static function init(): void {
		self::register_fields();
	}

	private static function register_fields(): void {
		self::$fields = array(
			'_vev_start_utc' => array(
				'label'    => __( 'Start Date/Time', 've-events' ),
				'type'     => 'datetime',
				'format'   => 'datetime',
				'group'    => 'date',
			),
			'_vev_end_utc' => array(
				'label'    => __( 'End Date/Time', 've-events' ),
				'type'     => 'datetime',
				'format'   => 'datetime',
				'group'    => 'date',
			),
			'_vev_all_day' => array(
				'label'    => __( 'All Day Event', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'date',
			),
			'_vev_hide_end' => array(
				'label'    => __( 'Hide End Time', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'date',
			),
			'_vev_speaker' => array(
				'label'    => __( 'Speaker', 've-events' ),
				'type'     => 'text',
				'format'   => 'text',
				'group'    => 'details',
			),
			'_vev_special_info' => array(
				'label'    => __( 'Special Info', 've-events' ),
				'type'     => 'textarea',
				'format'   => 'text',
				'group'    => 'details',
			),
			'_vev_info_url' => array(
				'label'    => __( 'Info URL', 've-events' ),
				'type'     => 'url',
				'format'   => 'url',
				'group'    => 'details',
			),
			've_start_date' => array(
				'label'    => __( 'Start Date (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'date',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_start_date' ),
			),
			've_start_time' => array(
				'label'    => __( 'Start Time (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'time',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_start_time' ),
			),
			've_end_date' => array(
				'label'    => __( 'End Date (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'date',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_end_date' ),
			),
			've_end_time' => array(
				'label'    => __( 'End Time (formatted)', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'time',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_end_time' ),
			),
			've_date_range' => array(
				'label'    => __( 'Date Range', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_date_range' ),
			),
			've_time_range' => array(
				'label'    => __( 'Time Range', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_time_range' ),
			),
			've_datetime_formatted' => array(
				'label'    => __( 'Full Date & Time', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'formatted',
				'callback' => array( __CLASS__, 'get_datetime_formatted' ),
			),
			've_status' => array(
				'label'    => __( 'Event Status', 've-events' ),
				'type'     => 'virtual',
				'format'   => 'text',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_status' ),
			),
			've_is_upcoming' => array(
				'label'    => __( 'Is Upcoming', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_is_upcoming' ),
			),
			've_is_ongoing' => array(
				'label'    => __( 'Is Ongoing', 've-events' ),
				'type'     => 'boolean',
				'format'   => 'yesno',
				'group'    => 'status',
				'callback' => array( __CLASS__, 'get_is_ongoing' ),
			),
		);
	}

	public static function get_fields(): array {
		if ( empty( self::$fields ) ) {
			self::register_fields();
		}
		return self::$fields;
	}

	public static function get_field( string $key ): ?array {
		$fields = self::get_fields();
		return $fields[ $key ] ?? null;
	}

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
				if ( empty( $value ) ) {
					return '';
				}
				return wp_date( get_option( 'date_format' ), (int) $value, wp_timezone() );

			case 'time':
				if ( empty( $value ) ) {
					return '';
				}
				return wp_date( get_option( 'time_format' ), (int) $value, wp_timezone() );

			case 'yesno':
				return $value ? __( 'Yes', 've-events' ) : __( 'No', 've-events' );

			case 'url':
				return esc_url( $value );

			default:
				return (string) $value;
		}
	}

	public static function get_start_date( int $post_id ): string {
		$start = (int) get_post_meta( $post_id, VEV_Events::META_START_UTC, true );
		return VEV_Frontend::format_date_only( $start );
	}

	public static function get_start_time( int $post_id ): string {
		$all_day = (int) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
		if ( $all_day ) {
			return '';
		}
		$start = (int) get_post_meta( $post_id, VEV_Events::META_START_UTC, true );
		return VEV_Frontend::format_time_only( $start );
	}

	public static function get_end_date( int $post_id ): string {
		$end = (int) get_post_meta( $post_id, VEV_Events::META_END_UTC, true );
		return VEV_Frontend::format_date_only( $end );
	}

	public static function get_end_time( int $post_id ): string {
		$all_day  = (int) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
		$hide_end = (int) get_post_meta( $post_id, VEV_Events::META_HIDE_END, true );
		if ( $all_day || $hide_end ) {
			return '';
		}
		$end = (int) get_post_meta( $post_id, VEV_Events::META_END_UTC, true );
		return VEV_Frontend::format_time_only( $end );
	}

	public static function get_date_range( int $post_id ): string {
		$data = VEV_Frontend::get_event_data( $post_id );
		return VEV_Frontend::format_date_range( $data );
	}

	public static function get_time_range( int $post_id ): string {
		$data = VEV_Frontend::get_event_data( $post_id );
		return VEV_Frontend::format_time_range( $data );
	}

	public static function get_datetime_formatted( int $post_id ): string {
		$data = VEV_Frontend::get_event_data( $post_id );
		return VEV_Frontend::format_datetime_full( $data );
	}

	public static function get_status( int $post_id ): string {
		$data   = VEV_Frontend::get_event_data( $post_id );
		$status = VEV_Frontend::get_event_status( $data['start_utc'], $data['end_utc'] );
		return VEV_Frontend::status_label( $status );
	}

	public static function get_is_upcoming( int $post_id ): bool {
		$data   = VEV_Frontend::get_event_data( $post_id );
		$status = VEV_Frontend::get_event_status( $data['start_utc'], $data['end_utc'] );
		return 'upcoming' === $status;
	}

	public static function get_is_ongoing( int $post_id ): bool {
		$data   = VEV_Frontend::get_event_data( $post_id );
		$status = VEV_Frontend::get_event_status( $data['start_utc'], $data['end_utc'] );
		return 'ongoing' === $status;
	}

	public static function get_fields_for_dropdown( bool $include_internal = false ): array {
		$fields = self::get_fields();
		$options = array();

		$groups = array(
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
