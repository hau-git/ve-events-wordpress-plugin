<?php
/**
 * Series suggestion system: detects events sharing a title and offers to group
 * them into a series, with an editor notice and AJAX handlers.
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
 * Detects, surfaces, and resolves series suggestions for manually created events.
 */
final class SeriesSuggestions {

	/**
	 * Register the detection, notice, and AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'save_post_' . Constants::POST_TYPE, array( __CLASS__, 'detect_series_suggestion' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_series_suggestion_notice' ) );
		add_action( 'wp_ajax_vev_series_suggestion', array( __CLASS__, 'handle_series_suggestion_ajax' ) );
	}

	/**
	 * Detect sibling events sharing a title and store a pending suggestion.
	 *
	 * @param int $post_id The post being saved.
	 */
	public static function detect_series_suggestion( int $post_id ): void {
		$settings = \VEV\Settings::get();
		if ( empty( $settings['series_suggestions'] ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Skip import-generated posts.
		if ( get_post_meta( $post_id, '_vev_import_feed_id', true ) ) {
			return;
		}

		// Skip if already has a series assigned.
		$existing = wp_get_object_terms( $post_id, Constants::TAX_SERIES, array( 'fields' => 'ids' ) );
		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			delete_post_meta( $post_id, '_vev_series_suggestion' );
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_title ) ) {
			return;
		}

		$normalized_title = self::normalize_title( $post->post_title );

		// Find sibling events with same title.
		$siblings = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'future' ),
				'post__not_in'   => array( $post_id ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				's'              => $post->post_title,
				'exact'          => true,
			)
		);

		// Filter by normalized title.
		$matching_ids = array();
		foreach ( $siblings as $sibling_id ) {
			$sibling_post = get_post( $sibling_id );
			if ( $sibling_post && self::normalize_title( $sibling_post->post_title ) === $normalized_title ) {
				$matching_ids[] = (int) $sibling_id;
			}
		}

		if ( empty( $matching_ids ) ) {
			delete_post_meta( $post_id, '_vev_series_suggestion' );
			return;
		}

		// If siblings already have a series, auto-assign directly (no suggestion needed).
		foreach ( $matching_ids as $sibling_id ) {
			$sibling_series = wp_get_object_terms( $sibling_id, Constants::TAX_SERIES, array( 'fields' => 'ids' ) );
			if ( ! empty( $sibling_series ) && ! is_wp_error( $sibling_series ) ) {
				$series_term_id = (int) $sibling_series[0];
				wp_set_object_terms( $post_id, $series_term_id, Constants::TAX_SERIES, true );
				delete_post_meta( $post_id, '_vev_series_suggestion' );
				Plugin::log( sprintf( 'Auto-assigned post %d to existing series term %d', $post_id, $series_term_id ) );
				return;
			}
		}

		// No series found yet — store suggestion for manual review.
		update_post_meta(
			$post_id,
			'_vev_series_suggestion',
			array(
				'title'       => $post->post_title,
				'sibling_ids' => $matching_ids,
				'status'      => 'pending',
			)
		);
	}

	/**
	 * Render the pending-suggestion notice in the event editor.
	 */
	public static function render_series_suggestion_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}

		global $post;
		if ( ! $post || Constants::POST_TYPE !== $post->post_type ) {
			return;
		}

		$suggestion = get_post_meta( $post->ID, '_vev_series_suggestion', true );
		if ( empty( $suggestion ) || 'pending' !== ( $suggestion['status'] ?? '' ) ) {
			return;
		}

		$sibling_count = count( $suggestion['sibling_ids'] ?? array() );
		$series_terms  = get_terms(
			array(
				'taxonomy'   => Constants::TAX_SERIES,
				'hide_empty' => false,
			)
		);

		$post_id = $post->ID;
		$nonce   = wp_create_nonce( 'vev_series_suggestion_' . $post_id );
		?>
		<div class="notice notice-warning" id="vev-series-suggestion" style="padding:12px 16px;">
			<p><strong><?php esc_html_e( 'Series Suggestion', 've-events' ); ?></strong></p>
			<p>
				<?php
				echo esc_html(
					sprintf(
					/* translators: 1: count of similar events, 2: event title */
						_n(
							'Found %1$d other event with the same title "%2$s". Would you like to add these events to a series?',
							'Found %1$d other events with the same title "%2$s". Would you like to add these events to a series?',
							$sibling_count,
							've-events'
						),
						$sibling_count,
						$suggestion['title']
					)
				);
				?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="vev-series-create"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Create new series & assign', 've-events' ); ?>
				</button>
				&nbsp;
				<?php if ( ! empty( $series_terms ) && ! is_wp_error( $series_terms ) ) : ?>
				<select id="vev-series-existing-select" style="vertical-align:middle;">
					<option value=""><?php esc_html_e( '— or assign to existing series —', 've-events' ); ?></option>
					<?php foreach ( $series_terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="vev-series-assign"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Assign', 've-events' ); ?>
				</button>
				&nbsp;
				<?php endif; ?>
				<button type="button" class="button" id="vev-series-dismiss"
					data-post-id="<?php echo esc_attr( $post_id ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Dismiss', 've-events' ); ?>
				</button>
			</p>
			<div id="vev-series-feedback" style="margin-top:8px;"></div>
		</div>
		<?php
	}

	/**
	 * Handle create / assign / dismiss AJAX actions for a suggestion.
	 */
	public static function handle_series_suggestion_ajax(): void {
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$nonce   = (string) ( $_POST['nonce'] ?? '' );
		$sub     = (string) ( $_POST['sub'] ?? '' );

		if ( ! wp_verify_nonce( $nonce, 'vev_series_suggestion_' . $post_id ) ) {
			wp_send_json_error( __( 'Security check failed.', 've-events' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 've-events' ) );
		}

		$suggestion = get_post_meta( $post_id, '_vev_series_suggestion', true );
		if ( empty( $suggestion ) ) {
			wp_send_json_error( __( 'No suggestion found.', 've-events' ) );
		}

		$sibling_ids = array_map( 'intval', $suggestion['sibling_ids'] ?? array() );

		if ( 'dismiss' === $sub ) {
			$suggestion['status'] = 'dismissed';
			update_post_meta( $post_id, '_vev_series_suggestion', $suggestion );
			wp_send_json_success();
		}

		if ( 'create' === $sub ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( __( 'Post not found.', 've-events' ) );
			}
			$new_term = wp_insert_term( $post->post_title, Constants::TAX_SERIES );
			if ( is_wp_error( $new_term ) ) {
				wp_send_json_error( $new_term->get_error_message() );
			}
			$term_id = (int) $new_term['term_id'];
			wp_set_object_terms( $post_id, $term_id, Constants::TAX_SERIES, true );
			foreach ( $sibling_ids as $sibling_id ) {
				wp_set_object_terms( $sibling_id, $term_id, Constants::TAX_SERIES, true );
			}
			delete_post_meta( $post_id, '_vev_series_suggestion' );
			Plugin::log( sprintf( 'Created series term %d and assigned to post %d + %d siblings', $term_id, $post_id, count( $sibling_ids ) ) );
			wp_send_json_success();
		}

		if ( 'assign' === $sub ) {
			$term_id = (int) ( $_POST['term_id'] ?? 0 );
			if ( ! $term_id || ! term_exists( $term_id, Constants::TAX_SERIES ) ) {
				wp_send_json_error( __( 'Invalid series term.', 've-events' ) );
			}
			wp_set_object_terms( $post_id, $term_id, Constants::TAX_SERIES, true );
			foreach ( $sibling_ids as $sibling_id ) {
				wp_set_object_terms( $sibling_id, $term_id, Constants::TAX_SERIES, true );
			}
			delete_post_meta( $post_id, '_vev_series_suggestion' );
			wp_send_json_success();
		}

		wp_send_json_error( __( 'Unknown action.', 've-events' ) );
	}

	/**
	 * Normalize a title for fuzzy matching (lowercase, collapse whitespace,
	 * strip punctuation).
	 *
	 * @param string $title Raw post title.
	 */
	private static function normalize_title( string $title ): string {
		$title = mb_strtolower( trim( $title ), 'UTF-8' );
		$title = preg_replace( '/\s+/', ' ', $title );
		$title = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $title ?? '' );
		return $title ?? '';
	}
}
