<?php
/**
 * Screen-gated enqueue of all admin styles and scripts for VE Events.
 *
 * @package VE_Events
 */

namespace VEV\Admin;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the extracted admin CSS/JS on the screens that need them.
 */
final class Assets {

	/**
	 * Register the admin enqueue hook.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue admin assets, gated per screen to mirror the legacy behavior.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public static function enqueue( string $hook ): void {
		$base = plugins_url( 'assets/', dirname( __DIR__, 2 ) . '/ve-events.php' );
		$ver  = Constants::VERSION;

		// Color picker for category taxonomy pages.
		if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			$screen = get_current_screen();
			if ( $screen && Constants::TAX_CATEGORY === $screen->taxonomy ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script(
					'vev-admin-color-picker',
					$base . 'js/admin-color-picker.js',
					array( 'wp-color-picker' ),
					$ver,
					true
				);
			}
		}

		// Settings page.
		if ( 've_event_page_vev-settings' === $hook ) {
			wp_enqueue_style( 'vev-admin-settings', $base . 'css/admin-settings.css', array(), $ver );
			wp_enqueue_script( 'vev-admin-settings', $base . 'js/admin-settings.js', array(), $ver, true );
			wp_localize_script(
				'vev-admin-settings',
				'vevSettings',
				array(
					'resyncNonce' => wp_create_nonce( 'vev_resync_computed_meta' ),
					'running'     => __( 'Running…', 've-events' ),
					'synced'      => __( 'events synced.', 've-events' ),
					'error'       => __( 'Error.', 've-events' ),
				)
			);
		}

		// Calendar page.
		if ( 'admin_page_vev-calendar' === $hook ) {
			wp_enqueue_style( 'vev-admin-calendar', $base . 'css/admin-calendar.css', array(), $ver );
		}

		// Event list screen (edit-ve_event): list styles + month-separator footer JS.
		$screen = get_current_screen();
		if ( $screen && 'edit-' . Constants::POST_TYPE === $screen->id ) {
			wp_enqueue_style( 'vev-admin-list', $base . 'css/admin-list.css', array(), $ver );
			wp_enqueue_script( 'vev-admin-list', $base . 'js/admin-list.js', array(), $ver, true );
		}

		// Event editor screen (post.php / post-new.php for ve_event).
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			if ( $screen && Constants::POST_TYPE === $screen->post_type ) {
				wp_enqueue_style( 'vev-admin-event-form', $base . 'css/admin-event-form.css', array(), $ver );
				wp_enqueue_script( 'vev-admin-event-form', $base . 'js/admin-event-form.js', array(), $ver, true );

				wp_enqueue_script(
					'vev-admin-series-suggestions',
					$base . 'js/admin-series-suggestions.js',
					array( 'jquery' ),
					$ver,
					true
				);
				wp_localize_script(
					'vev-admin-series-suggestions',
					'vevSeriesSuggestion',
					array(
						'errorRetry'  => __( 'Error. Please try again.', 've-events' ),
						'selectFirst' => __( 'Please select a series first.', 've-events' ),
					)
				);
			}
		}
	}
}
