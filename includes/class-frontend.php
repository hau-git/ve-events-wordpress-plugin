<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VEV_Frontend {

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output_schema' ), 99 );
		add_filter( 'get_post_metadata', array( __CLASS__, 'computed_meta' ), 10, 4 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_fields' ) );
	}

	public static function computed_meta( $value, int $object_id, string $meta_key, bool $single ) {
		$field = VEV_Fields::get_field( $meta_key );
		if ( ! $field || ! isset( $field['callback'] ) ) {
			return $value;
		}
		if ( VEV_Events::POST_TYPE !== get_post_type( $object_id ) ) {
			return $value;
		}
		$result = VEV_Fields::get_field_value( $meta_key, $object_id );
		return $single ? $result : array( $result );
	}

	public static function get_event_data( int $post_id ): array {
		$start_utc = (int) get_post_meta( $post_id, VEV_Events::META_START_UTC, true );
		$end_utc   = (int) get_post_meta( $post_id, VEV_Events::META_END_UTC, true );

		$all_day   = (int) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
		$hide_end  = (int) get_post_meta( $post_id, VEV_Events::META_HIDE_END, true );

		if ( ! $end_utc && $start_utc ) {
			$end_utc = $start_utc;
		}

		return array(
			'start_utc' => $start_utc,
			'end_utc'   => $end_utc,
			'all_day'   => (bool) $all_day,
			'hide_end'  => (bool) $hide_end,
		);
	}

	public static function get_event_status( int $start_utc, int $end_utc ): string {
		if ( ! $start_utc ) {
			return 'upcoming';
		}
		if ( ! $end_utc ) {
			$end_utc = $start_utc;
		}

		$now = time();

		if ( $now < $start_utc ) {
			return 'upcoming';
		}

		if ( $now <= $end_utc ) {
			return 'ongoing';
		}

		$settings = VEV_Events::get_settings();
		$grace_period = absint( $settings['grace_period'] ?? 1 );
		$grace_seconds = $grace_period * DAY_IN_SECONDS;

		if ( $now <= ( $end_utc + $grace_seconds ) ) {
			return 'past';
		}

		return 'archived';
	}

	public static function status_label( string $status ): string {
		switch ( $status ) {
			case 'ongoing':
				return __( 'Ongoing', VEV_Events::TEXTDOMAIN );
			case 'past':
				return __( 'Past', VEV_Events::TEXTDOMAIN );
			case 'archived':
				return __( 'Archived', VEV_Events::TEXTDOMAIN );
			case 'upcoming':
			default:
				return __( 'Upcoming', VEV_Events::TEXTDOMAIN );
		}
	}

	public static function format_date_only( int $ts_utc ): string {
		if ( ! $ts_utc ) {
			return '';
		}
		return wp_date( get_option( 'date_format' ), $ts_utc, wp_timezone() );
	}

	public static function format_time_only( int $ts_utc ): string {
		if ( ! $ts_utc ) {
			return '';
		}
		return wp_date( get_option( 'time_format' ), $ts_utc, wp_timezone() );
	}

	public static function format_date_range( array $data ): string {
		$start = (int) $data['start_utc'];
		$end   = (int) $data['end_utc'];

		if ( ! $start ) {
			return '';
		}
		if ( ! $end ) {
			$end = $start;
		}

		$tz = wp_timezone();
		$date_format = get_option( 'date_format' );

		$start_date = wp_date( $date_format, $start, $tz );
		$end_date   = wp_date( $date_format, $end, $tz );

		if ( $start_date === $end_date ) {
			return $start_date;
		}

		return $start_date . ' – ' . $end_date;
	}

	public static function format_time_range( array $data ): string {
		if ( $data['all_day'] ) {
			return __( 'All day', VEV_Events::TEXTDOMAIN );
		}

		$start = (int) $data['start_utc'];
		$end   = (int) $data['end_utc'];
		$hide_end = (bool) $data['hide_end'];

		if ( ! $start ) {
			return '';
		}

		$tz = wp_timezone();
		$time_format = get_option( 'time_format' );

		$start_time = wp_date( $time_format, $start, $tz );

		if ( ! $end || $hide_end ) {
			return $start_time;
		}

		$end_time = wp_date( $time_format, $end, $tz );

		return $start_time . ' – ' . $end_time;
	}

	public static function format_datetime_full( array $data ): string {
		$start = (int) $data['start_utc'];
		$end   = (int) $data['end_utc'];
		$all_day  = (bool) $data['all_day'];
		$hide_end = (bool) $data['hide_end'];

		if ( ! $start ) {
			return '';
		}
		if ( ! $end ) {
			$end = $start;
		}

		$tz = wp_timezone();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$start_date = wp_date( $date_format, $start, $tz );
		$end_date   = wp_date( $date_format, $end, $tz );

		if ( $all_day ) {
			if ( $start_date === $end_date ) {
				return sprintf( __( '%s (all day)', VEV_Events::TEXTDOMAIN ), $start_date );
			}
			return sprintf( __( '%1$s – %2$s (all day)', VEV_Events::TEXTDOMAIN ), $start_date, $end_date );
		}

		$start_time = wp_date( $time_format, $start, $tz );
		$end_time   = wp_date( $time_format, $end, $tz );

		if ( $start_date === $end_date ) {
			if ( $hide_end ) {
				return sprintf( __( '%1$s, %2$s', VEV_Events::TEXTDOMAIN ), $start_date, $start_time );
			}
			return sprintf( __( '%1$s, %2$s – %3$s', VEV_Events::TEXTDOMAIN ), $start_date, $start_time, $end_time );
		}

		if ( $hide_end ) {
			return sprintf( __( '%1$s, %2$s', VEV_Events::TEXTDOMAIN ), $start_date, $start_time );
		}
		return sprintf( __( '%1$s %2$s – %3$s %4$s', VEV_Events::TEXTDOMAIN ), $start_date, $start_time, $end_date, $end_time );
	}

	public static function register_rest_fields(): void {
		$fields = array(
			've_start_date',
			've_start_time',
			've_end_date',
			've_end_time',
			've_date_range',
			've_time_range',
			've_datetime_formatted',
			've_status',
		);

		foreach ( $fields as $field ) {
			register_rest_field(
				VEV_Events::POST_TYPE,
				$field,
				array(
					'get_callback' => static function ( array $object ) use ( $field ) {
						return get_post_meta( (int) $object['id'], $field, true );
					},
					'schema'       => array( 'type' => 'string' ),
				)
			);
		}
	}

	public static function output_schema(): void {
		if ( ! is_singular( VEV_Events::POST_TYPE ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$data = self::get_event_data( $post_id );
		if ( ! $data['start_utc'] ) {
			return;
		}

		$tz = wp_timezone();

		$start_iso = self::schema_datetime( $data['start_utc'], $data['all_day'], $tz );
		$end_iso   = self::schema_datetime( $data['end_utc'], $data['all_day'], $tz );

		$event = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => get_the_title( $post_id ),
			'description' => self::schema_description( $post_id ),
			'url'         => get_permalink( $post_id ),
			'startDate'   => $start_iso,
			'endDate'     => $end_iso,
		);

		$img = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $img ) {
			$event['image'] = array( $img );
		}

		$loc_terms = get_the_terms( $post_id, VEV_Events::TAX_LOCATION );
		if ( is_array( $loc_terms ) && ! empty( $loc_terms ) ) {
			$loc = $loc_terms[0];
			$event['location'] = array(
				'@type' => 'Place',
				'name'  => $loc->name,
			);
		}

		$speaker = (string) get_post_meta( $post_id, VEV_Events::META_SPEAKER, true );
		if ( '' !== $speaker ) {
			$event['performer'] = array(
				'@type' => 'Person',
				'name'  => $speaker,
			);
		}

		$info_url = (string) get_post_meta( $post_id, VEV_Events::META_INFO_URL, true );
		if ( '' !== $info_url ) {
			$event['offers'] = array(
				'@type' => 'Offer',
				'url'   => $info_url,
			);
		}

		$settings = VEV_Events::get_settings();
		if ( ! empty( $settings['include_series_schema'] ) ) {
			$series_terms = get_the_terms( $post_id, VEV_Events::TAX_SERIES );
			if ( is_array( $series_terms ) && ! empty( $series_terms ) ) {
				$series = $series_terms[0];
				$event['superEvent'] = array(
					'@type' => 'EventSeries',
					'name'  => $series->name,
					'url'   => get_term_link( $series ),
				);
			}
		}

		$event = apply_filters( 've_schema_event', $event, $post_id );

		printf(
			"\n<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	private static function schema_datetime( int $ts_utc, bool $all_day, \DateTimeZone $tz ): string {
		if ( ! $ts_utc ) {
			return '';
		}

		if ( $all_day ) {
			return wp_date( 'Y-m-d', $ts_utc, $tz );
		}

		return wp_date( 'c', $ts_utc, $tz );
	}

	private static function schema_description( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$excerpt = trim( (string) get_the_excerpt( $post ) );
		if ( '' !== $excerpt ) {
			return wp_strip_all_tags( $excerpt );
		}

		$content = wp_strip_all_tags( (string) $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $content, 0, 300 );
		}
		return substr( $content, 0, 300 );
	}
}
