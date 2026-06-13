<?php
/**
 * Event editor meta boxes: Details and Event Status.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;
use VEV\Support\EventStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the Details and Event Status meta boxes.
 */
final class MetaBoxes {

	/**
	 * Register the meta-box hook.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	/**
	 * Register the Details and Event Status meta boxes.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'vev_details',
			__( 'Details', 've-events' ),
			array( __CLASS__, 'render_details_metabox' ),
			Constants::POST_TYPE,
			'normal',
			'low'
		);
		add_meta_box(
			'vev_event_status',
			__( 'Event Status', 've-events' ),
			array( __CLASS__, 'render_status_metabox' ),
			Constants::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Render the Details meta box (speaker, info URL, notes).
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public static function render_details_metabox( \WP_Post $post ): void {
		$speaker  = (string) get_post_meta( $post->ID, Constants::META_SPEAKER, true );
		$special  = (string) get_post_meta( $post->ID, Constants::META_SPECIAL, true );
		$info_url = (string) get_post_meta( $post->ID, Constants::META_INFO_URL, true );
		?>
		<div class="vev-detail-grid" style="margin-top:4px;">
			<div class="vev-field">
				<label for="vev_speaker"><?php esc_html_e( 'Speaker / Host', 've-events' ); ?></label>
				<input type="text" id="vev_speaker" name="vev_speaker" value="<?php echo esc_attr( $speaker ); ?>" />
			</div>
			<div class="vev-field">
				<label for="vev_info_url"><?php esc_html_e( 'Info / Ticket URL', 've-events' ); ?></label>
				<input type="url" id="vev_info_url" name="vev_info_url" value="<?php echo esc_attr( $info_url ); ?>" placeholder="https://..." />
			</div>
			<div class="vev-field vev-full">
				<label for="vev_special_info"><?php esc_html_e( 'Additional Notes', 've-events' ); ?></label>
				<textarea id="vev_special_info" name="vev_special_info" rows="2"><?php echo esc_textarea( $special ); ?></textarea>
				<p class="vev-field-help"><?php esc_html_e( 'Short notes for listings: admission, dress code, registration info, etc.', 've-events' ); ?></p>
			</div>
		</div>
		<details class="vev-dev-note">
			<summary><?php esc_html_e( 'Developer: JetEngine / Elementor field keys', 've-events' ); ?></summary>
			<div class="vev-dev-note-body">
				<code><?php echo esc_html( Constants::VIRTUAL_DATETIME ); ?></code>
				&nbsp;<code><?php echo esc_html( Constants::VIRTUAL_DATE_RANGE ); ?></code>
				&nbsp;<code><?php echo esc_html( Constants::VIRTUAL_TIME_RANGE ); ?></code>
				&nbsp;<code><?php echo esc_html( Constants::VIRTUAL_STATUS ); ?></code>
				&nbsp;<code><?php echo esc_html( Constants::VIRTUAL_STATUS_LABEL ); ?></code>
				&nbsp;<code><?php echo esc_html( Constants::VIRTUAL_IS_UPCOMING ); ?></code>
			</div>
		</details>
		<?php
	}

	/**
	 * Render the Event Status meta box (status override select).
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public static function render_status_metabox( \WP_Post $post ): void {
		$event_status = (string) get_post_meta( $post->ID, Constants::META_EVENT_STATUS, true );
		?>
		<div class="vev-field">
			<select id="vev_event_status" name="vev_event_status" style="width:100%;">
				<option value="" <?php selected( $event_status, '' ); ?>><?php esc_html_e( 'Scheduled (default)', 've-events' ); ?></option>
				<?php foreach ( EventStatus::OPTIONS as $status_key ) : ?>
				<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $event_status, $status_key ); ?>><?php echo esc_html( EventStatus::label( $status_key ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="vev-field-help" style="margin-top:6px;"><?php esc_html_e( 'Updates Schema.org eventStatus and shows a badge in the event list.', 've-events' ); ?></p>
		</div>
		<?php
	}
}
