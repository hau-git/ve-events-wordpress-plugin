<?php
/**
 * Event editor form rendered below the title, plus meta saving.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the event date/time form and persists the event meta on save.
 */
final class EventForm {

	/**
	 * Register the form-rendering and save hooks.
	 */
	public static function init(): void {
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_event_form' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
	}

	/**
	 * Render the event date/time form directly below the post title.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public static function render_event_form( \WP_Post $post ): void {
		if ( Constants::POST_TYPE !== $post->post_type ) {
			return;
		}

		wp_nonce_field( 'vev_save_event_meta', 'vev_event_meta_nonce' );

		$tz = wp_timezone();

		$start_utc = (int) get_post_meta( $post->ID, Constants::META_START_UTC, true );
		$end_utc   = (int) get_post_meta( $post->ID, Constants::META_END_UTC, true );
		$all_day   = (bool) get_post_meta( $post->ID, Constants::META_ALL_DAY, true );
		$hide_end  = (bool) get_post_meta( $post->ID, Constants::META_HIDE_END, true );

		$start_date = $start_utc ? wp_date( 'Y-m-d', $start_utc, $tz ) : '';
		$start_time = $start_utc ? wp_date( 'H:i', $start_utc, $tz ) : '';
		$end_date   = $end_utc ? wp_date( 'Y-m-d', $end_utc, $tz ) : '';
		$end_time   = $end_utc ? wp_date( 'H:i', $end_utc, $tz ) : '';

		$tz_string = (string) get_option( 'timezone_string' );
		?>
		<div class="vev-event-form-wrap">

		<div class="vev-sections">

			<!-- ① Datum & Uhrzeit -->
			<div class="vev-section">
				<h3 class="vev-section-title">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'Date &amp; Time', 've-events' ); ?>
				</h3>

				<div class="vev-date-grid">
					<div class="vev-field">
						<label for="vev_start_date"><?php esc_html_e( 'Start *', 've-events' ); ?></label>
						<div class="vev-field-row">
							<input type="date" id="vev_start_date" name="vev_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
							<input type="time" id="vev_start_time" name="vev_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
						</div>
					</div>
					<div class="vev-field">
						<label for="vev_end_date"><?php esc_html_e( 'End (optional)', 've-events' ); ?></label>
						<div class="vev-field-row">
							<input type="date" id="vev_end_date" name="vev_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
							<input type="time" id="vev_end_time" name="vev_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
						</div>
					</div>
				</div>

				<div class="vev-checks">
					<label class="vev-check-label">
						<input type="checkbox" id="vev_all_day" name="vev_all_day" value="1" <?php checked( $all_day ); ?> />
						<?php esc_html_e( 'All day', 've-events' ); ?>
					</label>
					<label class="vev-check-label">
						<input type="checkbox" id="vev_hide_end" name="vev_hide_end" value="1" <?php checked( $hide_end ); ?> />
						<?php esc_html_e( 'Hide end time in listings', 've-events' ); ?>
					</label>
				</div>

				<p class="vev-all-day-note" id="vev_all_day_note" style="display:none;">
					<?php esc_html_e( 'All-day events are stored as full-day ranges in your site time zone. Times will not be displayed.', 've-events' ); ?>
				</p>

				<?php if ( $tz_string && 'Europe/Berlin' !== $tz_string ) : ?>
				<p class="vev-tz-note">
					<?php esc_html_e( 'For correct date output, set WordPress timezone to Europe/Berlin (Settings → General).', 've-events' ); ?>
				</p>
				<?php endif; ?>

				<div class="vev-preview" id="vev_date_preview">
					<span style="color:#888;">&#8594; <?php esc_html_e( 'Enter a date above', 've-events' ); ?></span>
				</div>
			</div>

		</div><!-- .vev-sections -->
		</div><!-- .vev-event-form-wrap -->
		<?php
	}

	/**
	 * Persist event meta from the form on save_post.
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 */
	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( Constants::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['vev_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vev_event_meta_nonce'] ) ), 'vev_save_event_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$tz = wp_timezone();

		$start_date = isset( $_POST['vev_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_date'] ) ) : '';
		$start_time = isset( $_POST['vev_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_time'] ) ) : '';

		$end_date = isset( $_POST['vev_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_date'] ) ) : '';
		$end_time = isset( $_POST['vev_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_time'] ) ) : '';

		$all_day  = isset( $_POST['vev_all_day'] ) ? 1 : 0;
		$hide_end = isset( $_POST['vev_hide_end'] ) ? 1 : 0;

		$speaker  = isset( $_POST['vev_speaker'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_speaker'] ) ) : '';
		$special  = isset( $_POST['vev_special_info'] ) ? wp_kses_post( wp_unslash( $_POST['vev_special_info'] ) ) : '';
		$info_url = isset( $_POST['vev_info_url'] ) ? esc_url_raw( wp_unslash( $_POST['vev_info_url'] ) ) : '';

		$start_ts = self::parse_to_utc_timestamp( $start_date, $start_time, (bool) $all_day, true, $tz );
		$end_ts   = self::parse_to_utc_timestamp( $end_date ? $end_date : $start_date, $end_time ? $end_time : $start_time, (bool) $all_day, false, $tz );

		if ( ! $end_ts ) {
			$end_ts = $start_ts;
		}

		if ( $start_ts && $end_ts && ! $all_day && $end_ts < $start_ts ) {
			$end_ts = $start_ts;
		}

		if ( $start_ts ) {
			update_post_meta( $post_id, Constants::META_START_UTC, $start_ts );
		} else {
			delete_post_meta( $post_id, Constants::META_START_UTC );
		}

		if ( $end_ts ) {
			update_post_meta( $post_id, Constants::META_END_UTC, $end_ts );
		} else {
			delete_post_meta( $post_id, Constants::META_END_UTC );
		}

		update_post_meta( $post_id, Constants::META_ALL_DAY, (int) $all_day );
		update_post_meta( $post_id, Constants::META_HIDE_END, (int) $hide_end );

		if ( '' !== $speaker ) {
			update_post_meta( $post_id, Constants::META_SPEAKER, $speaker );
		} else {
			delete_post_meta( $post_id, Constants::META_SPEAKER );
		}

		if ( '' !== $special ) {
			update_post_meta( $post_id, Constants::META_SPECIAL, $special );
		} else {
			delete_post_meta( $post_id, Constants::META_SPECIAL );
		}

		if ( '' !== $info_url ) {
			update_post_meta( $post_id, Constants::META_INFO_URL, $info_url );
		} else {
			delete_post_meta( $post_id, Constants::META_INFO_URL );
		}

		$allowed_statuses = array( '', 'cancelled', 'postponed', 'rescheduled', 'movedOnline' );
		$event_status     = sanitize_text_field( wp_unslash( $_POST['vev_event_status'] ?? '' ) );
		$event_status     = in_array( $event_status, $allowed_statuses, true ) ? $event_status : '';
		if ( '' !== $event_status ) {
			update_post_meta( $post_id, Constants::META_EVENT_STATUS, $event_status );
		} else {
			delete_post_meta( $post_id, Constants::META_EVENT_STATUS );
		}

		Plugin::log(
			sprintf(
				'Event %d saved. start_utc=%s end_utc=%s all_day=%s hide_end=%s',
				$post_id,
				(string) $start_ts,
				(string) $end_ts,
				(string) $all_day,
				(string) $hide_end
			)
		);
	}

	/**
	 * Convert a date/time pair in the site timezone to a UTC timestamp.
	 *
	 * @param string        $date     Date string (Y-m-d).
	 * @param string        $time     Time string (H:i).
	 * @param bool          $all_day  Whether this is an all-day event.
	 * @param bool          $is_start Whether this is the start (vs. end) value.
	 * @param \DateTimeZone $tz       Site timezone.
	 */
	private static function parse_to_utc_timestamp( string $date, string $time, bool $all_day, bool $is_start, \DateTimeZone $tz ): int {
		if ( '' === $date ) {
			return 0;
		}

		try {
			if ( $all_day ) {
				$clock = $is_start ? '00:00:00' : '23:59:59';
				$dt    = new \DateTimeImmutable( $date . ' ' . $clock, $tz );
			} else {
				$clean_time = $time ? $time : '00:00';
				$dt         = new \DateTimeImmutable( $date . ' ' . $clean_time, $tz );
			}

			return (int) $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'U' );
		} catch ( \Exception $e ) {
			Plugin::log( 'Date parse error: ' . $e->getMessage() );
			return 0;
		}
	}
}
