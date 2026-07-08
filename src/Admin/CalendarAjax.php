<?php
/**
 * AJAX endpoints backing the interactive admin calendar: month navigation,
 * quick-create, and drag-and-drop rescheduling.
 *
 * Every handler responds with the freshly rendered month app HTML so the client
 * only ever displays authoritative, server-rendered markup.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Support\DateParser;
use VEV\Support\Reschedule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the calendar AJAX actions.
 */
final class CalendarAjax {

	private const NONCE_ACTION = 'vev_calendar';

	/**
	 * Register the AJAX handlers.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_vev_cal_month', array( __CLASS__, 'handle_month' ) );
		add_action( 'wp_ajax_vev_cal_quick_create', array( __CLASS__, 'handle_quick_create' ) );
		add_action( 'wp_ajax_vev_cal_move_event', array( __CLASS__, 'handle_move_event' ) );
	}

	/**
	 * Render a month grid (navigation).
	 */
	public static function handle_month(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 've-events' ) ) );
		}

		$month = CalendarPage::normalize_month( self::post_string( 'month' ) );

		wp_send_json_success(
			array(
				'html'  => CalendarPage::render_app_html( $month ),
				'month' => $month,
			)
		);
	}

	/**
	 * Create a new event from the calendar quick-create popover.
	 */
	public static function handle_quick_create(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 've-events' ) ) );
		}

		$title = trim( self::post_string( 'title' ) );
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter an event title.', 've-events' ) ) );
		}

		$date = self::post_string( 'date' );
		if ( ! self::is_valid_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 've-events' ) ) );
		}

		$all_day    = ! empty( $_POST['all_day'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$start_time = self::sanitize_time( self::post_string( 'start_time' ) );
		$end_time   = self::sanitize_time( self::post_string( 'end_time' ) );

		$status = self::post_string( 'post_status' );
		$status = in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft';
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'draft';
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Constants::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the event.', 've-events' ) ) );
		}

		$tz       = wp_timezone();
		$start_ts = DateParser::to_utc( $date, $start_time, $all_day, true, $tz );
		$end_ts   = DateParser::to_utc( $date, '' !== $end_time ? $end_time : $start_time, $all_day, false, $tz );

		if ( ! $end_ts ) {
			$end_ts = $start_ts;
		}
		if ( $start_ts && $end_ts && ! $all_day && $end_ts < $start_ts ) {
			$end_ts = $start_ts;
		}

		// Writing META_START_UTC triggers ComputedMeta::sync() automatically.
		update_post_meta( $post_id, Constants::META_START_UTC, $start_ts );
		update_post_meta( $post_id, Constants::META_END_UTC, $end_ts );
		update_post_meta( $post_id, Constants::META_ALL_DAY, $all_day ? 1 : 0 );
		update_post_meta( $post_id, Constants::META_HIDE_END, 0 );

		$month = CalendarPage::normalize_month( self::post_string( 'month' ) );

		wp_send_json_success(
			array(
				'html'    => CalendarPage::render_app_html( $month ),
				'month'   => $month,
				'editUrl' => (string) get_edit_post_link( (int) $post_id, 'raw' ),
				'message' => 'publish' === $status
					? __( 'Event published.', 've-events' )
					: __( 'Draft event created.', 've-events' ),
			)
		);
	}

	/**
	 * Reschedule an event to a new day (drag and drop).
	 */
	public static function handle_move_event(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		if ( ! $post_id || Constants::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Event not found.', 've-events' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this event.', 've-events' ) ) );
		}

		$date = self::post_string( 'date' );
		if ( ! self::is_valid_date( $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid date.', 've-events' ) ) );
		}

		$start_utc = (int) get_post_meta( $post_id, Constants::META_START_UTC, true );
		if ( ! $start_utc ) {
			wp_send_json_error( array( 'message' => __( 'This event has no start date and cannot be moved.', 've-events' ) ) );
		}
		$end_utc = (int) get_post_meta( $post_id, Constants::META_END_UTC, true );

		$shifted = Reschedule::shift_to_date( $start_utc, $end_utc ? $end_utc : $start_utc, $date, wp_timezone() );

		// Writing META_START_UTC triggers ComputedMeta::sync() automatically.
		update_post_meta( $post_id, Constants::META_START_UTC, $shifted['start'] );
		update_post_meta( $post_id, Constants::META_END_UTC, $shifted['end'] );

		$month = CalendarPage::normalize_month( self::post_string( 'month' ) );

		wp_send_json_success(
			array(
				'html'  => CalendarPage::render_app_html( $month ),
				'month' => $month,
			)
		);
	}

	/**
	 * Read and sanitize a POST string field.
	 *
	 * @param string $key Field name.
	 */
	private static function post_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Validate a Y-m-d date string, including calendar validity.
	 *
	 * @param string $date Date string.
	 */
	private static function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Validate an H:i time string, returning '' when empty/invalid.
	 *
	 * @param string $time Time string.
	 */
	private static function sanitize_time( string $time ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}
}
