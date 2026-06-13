<?php
/**
 * Admin maintenance tools: resync of computed event meta via AJAX.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\ComputedMeta;
use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "Sync computed fields" maintenance action.
 */
final class Tools {

	/**
	 * Register the resync AJAX handler.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_vev_resync_computed_meta', array( __CLASS__, 'ajax_resync_computed_meta' ) );
	}

	/**
	 * Recompute the stored date meta for every existing event.
	 */
	public static function ajax_resync_computed_meta(): void {
		check_ajax_referer( 'vev_resync_computed_meta' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		$ids = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			ComputedMeta::sync( 0, (int) $id, Constants::META_START_UTC );
		}
		wp_send_json_success( array( 'count' => count( $ids ) ) );
	}
}
