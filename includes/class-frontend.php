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
		$virtual_keys = array(
			VEV_Events::VIRTUAL_STATUS,
			VEV_Events::VIRTUAL_STATUS_LABEL,
			VEV_Events::VIRTUAL_IS_LIVE,
			VEV_Events::VIRTUAL_IS_PAST,
			VEV_Events::VIRTUAL_TIMERANGE,
			VEV_Events::VIRTUAL_START_LOCAL,
			VEV_Events::VIRTUAL_END_LOCAL,
		);

		if ( ! in_array( $meta_key, $virtual_keys, true ) ) {
			return $value;
		}

		if ( VEV_Events::POST_TYPE !== get_post_type( $object_id ) ) {
			return $value;
		}

		$data = self::get_event_data( $object_id );

		$status = self::get_event_status( $data['start_utc'], $data['end_utc'] );

		if ( VEV_Events::VIRTUAL_STATUS === $meta_key ) {
			return $single ? $status : array( $status );
		}

		if ( VEV_Events::VIRTUAL_STATUS_LABEL === $meta_key ) {
			$label = self::status_label( $status );
			return $single ? $label : array( $label );
		}

		if ( VEV_Events::VIRTUAL_IS_LIVE === $meta_key ) {
			$is_live = ( 'ongoing' === $status ) ? '1' : '0';
			return $single ? $is_live : array( $is_live );
		}

		if ( VEV_Events::VIRTUAL_IS_PAST === $meta_key ) {
			$is_past = ( 'past' === $status || 'archived' === $status ) ? '1' : '0';
			return $single ? $is_past : array( $is_past );
		}

		if ( VEV_Events::VIRTUAL_TIMERANGE === $meta_key ) {
			$timerange = self::format_timerange( $data, true );
			return $single ? $timerange : array( $timerange );
		}

		if ( VEV_Events::VIRTUAL_START_LOCAL === $meta_key ) {
			$val = self::format_single_datetime( $data['start_utc'], (bool) $data['all_day'], true );
			return $single ? $val : array( $val );
		}

		if ( VEV_Events::VIRTUAL_END_LOCAL === $meta_key ) {
			$val = self::format_single_datetime( $data['end_utc'], (bool) $data['all_day'], false );
			return $single ? $val : array( $val );
		}

		return $value;
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

	private static function format_single_datetime( int $ts_utc, bool $all_day, bool $is_start ): string {
		if ( ! $ts_utc ) {
			return '';
		}

		$tz = wp_timezone();

		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );

		if ( $all_day ) {
			return wp_date( $date_format, $ts_utc, $tz );
		}

		return wp_date( $date_format . ' ' . $time_format, $ts_utc, $tz );
	}

	public static function format_timerange( array $data, bool $respect_hide_end ): string {
		$start = (int) $data['start_utc'];
		$end   = (int) $data['end_utc'];
		$all_day = (bool) $data['all_day'];
		$hide_end = (bool) $data['hide_end'];

		if ( ! $start ) {
			return '';
		}
		if ( ! $end ) {
			$end = $start;
		}

		$tz = wp_timezone();
		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );

		$start_date = wp_date( $date_format, $start, $tz );
		$end_date   = wp_date( $date_format, $end, $tz );

		$settings = VEV_Events::get_settings();
		$hide_end_same_day = ! empty( $settings['hide_end_same_day'] );

		if ( $all_day ) {
			if ( $start_date === $end_date ) {
				return sprintf(
					__( '%s (all day)', VEV_Events::TEXTDOMAIN ),
					$start_date
				);
			}
			return sprintf(
				__( '%1$s – %2$s (all day)', VEV_Events::TEXTDOMAIN ),
				$start_date,
				$end_date
			);
		}

		$start_time = wp_date( $time_format, $start, $tz );
		$end_time   = wp_date( $time_format, $end, $tz );

		if ( $respect_hide_end && $hide_end ) {
			return sprintf(
				__( '%1$s, %2$s', VEV_Events::TEXTDOMAIN ),
				$start_date,
				$start_time
			);
		}

		if ( $start_date === $end_date ) {
			if ( $hide_end_same_day ) {
				return sprintf(
					__( '%1$s · %2$s – %3$s', VEV_Events::TEXTDOMAIN ),
					$start_date,
					$start_time,
					$end_time
				);
			}
			return sprintf(
				__( '%1$s · %2$s – %3$s · %4$s', VEV_Events::TEXTDOMAIN ),
				$start_date,
				$start_time,
				$end_date,
				$end_time
			);
		}

		return sprintf(
			__( '%1$s %2$s – %3$s %4$s', VEV_Events::TEXTDOMAIN ),
			$start_date,
			$start_time,
			$end_date,
			$end_time
		);
	}

	public static function register_rest_fields(): void {
		register_rest_field(
			VEV_Events::POST_TYPE,
			'vev_status',
			array(
				'get_callback' => static function ( array $object ) {
					return get_post_meta( (int) $object['id'], VEV_Events::VIRTUAL_STATUS, true );
				},
				'schema'       => array(
					'type' => 'string',
				),
			)
		);

		register_rest_field(
			VEV_Events::POST_TYPE,
			'vev_timerange',
			array(
				'get_callback' => static function ( array $object ) {
					return get_post_meta( (int) $object['id'], VEV_Events::VIRTUAL_TIMERANGE, true );
				},
				'schema'       => array(
					'type' => 'string',
				),
			)
		);
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

		$start_iso = self::schema_datetime( $data['start_utc'], $data['all_day'], true, $tz );
		$end_iso   = self::schema_datetime( $data['end_utc'], $data['all_day'], false, $tz );

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

		$event = apply_filters( 'vev_schema_event', $event, $post_id );

		printf(
			"\n<script type=\"application/ld+json\">%s</script>\n",
			wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	private static function schema_datetime( int $ts_utc, bool $all_day, bool $is_start, \DateTimeZone $tz ): string {
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
