<?php
/**
 * Import Manager — bootstraps the import module and manages WP-Cron schedules.
 *
 * Load order: this class requires all other import classes, so only this file
 * needs to be required from the main plugin file.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load all import sub-classes.
require_once __DIR__ . '/class-import-feed.php';
require_once __DIR__ . '/class-import-logger.php';
require_once __DIR__ . '/class-field-mapper.php';
require_once __DIR__ . '/class-import-runner.php';
require_once __DIR__ . '/class-import-admin.php';

class VEV_Import_Manager {

	const CRON_HOOK = 'vev_run_import';

	public static function init(): void {
		// Register CPT.
		VEV_Import_Feed::init();

		// Register admin UI.
		VEV_Import_Admin::init();

		// Register custom cron intervals.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );

		// Cron callback.
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_feed' ) );

		// Re-schedule cron when a feed post is saved/trashed/deleted.
		add_action( 'save_post_' . VEV_Import_Feed::POST_TYPE, array( __CLASS__, 'on_feed_save' ), 10, 2 );
		add_action( 'trashed_post',   array( __CLASS__, 'on_feed_trashed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_feed_untrashed' ) );
		add_action( 'deleted_post',   array( __CLASS__, 'on_feed_deleted' ) );

		// DB table.
		VEV_Import_Logger::maybe_create_table();
	}

	// -------------------------------------------------------------------------
	// Cron intervals
	// -------------------------------------------------------------------------

	public static function add_cron_intervals( array $schedules ): array {
		if ( ! isset( $schedules['every15min'] ) ) {
			$schedules['every15min'] = array(
				'interval' => 900,
				'display'  => __( 'Every 15 Minutes', 've-events' ),
			);
		}
		if ( ! isset( $schedules['every30min'] ) ) {
			$schedules['every30min'] = array(
				'interval' => 1800,
				'display'  => __( 'Every 30 Minutes', 've-events' ),
			);
		}
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display'  => __( 'Weekly', 've-events' ),
			);
		}
		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Cron scheduling
	// -------------------------------------------------------------------------

	/**
	 * Schedules a cron event for a feed.
	 * If an event is already scheduled with the same interval it is left as-is.
	 * Otherwise the old event is cleared and a new one registered.
	 */
	public static function schedule_feed( int $feed_id ): void {
		$config   = VEV_Import_Feed::get_config( $feed_id );
		$interval = $config['schedule'] ?? 'daily';

		// Check currently scheduled hook
		$next = wp_next_scheduled( self::CRON_HOOK, array( $feed_id ) );

		if ( $next ) {
			// If same interval, leave it; wp-cron will fire at the right time.
			$scheduled_interval = self::get_scheduled_interval( $feed_id );
			if ( $scheduled_interval === $interval ) {
				return;
			}
			// Different interval — reschedule.
			wp_clear_scheduled_hook( self::CRON_HOOK, array( $feed_id ) );
		}

		if ( $config['active'] ) {
			wp_schedule_event( time(), $interval, self::CRON_HOOK, array( $feed_id ) );
		}
	}

	/**
	 * Removes the cron event for a feed.
	 */
	public static function unschedule_feed( int $feed_id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $feed_id ) );
	}

	/**
	 * Returns the currently scheduled interval name for a feed, or null.
	 */
	private static function get_scheduled_interval( int $feed_id ): ?string {
		$crons = _get_cron_array();
		if ( ! $crons ) {
			return null;
		}
		$hook = self::CRON_HOOK;
		$key  = md5( serialize( array( $feed_id ) ) );
		foreach ( $crons as $timestamp => $hooks ) {
			if ( isset( $hooks[ $hook ][ $key ] ) ) {
				return $hooks[ $hook ][ $key ]['schedule'] ?? null;
			}
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Cron callback
	// -------------------------------------------------------------------------

	/**
	 * WP-Cron callback — runs a single feed import.
	 *
	 * @param int $feed_id
	 */
	public static function run_feed( int $feed_id ): array {
		$runner = new VEV_Import_Runner( $feed_id );
		return $runner->run();
	}

	/**
	 * Runs a feed immediately (e.g. from admin "Run now" button).
	 */
	public static function run_now( int $feed_id ): array {
		return self::run_feed( $feed_id );
	}

	// -------------------------------------------------------------------------
	// Feed post lifecycle hooks
	// -------------------------------------------------------------------------

	public static function on_feed_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_status === 'publish' ) {
			self::schedule_feed( $post_id );
		} else {
			self::unschedule_feed( $post_id );
		}
	}

	public static function on_feed_trashed( int $post_id ): void {
		if ( get_post_type( $post_id ) === VEV_Import_Feed::POST_TYPE ) {
			self::unschedule_feed( $post_id );
		}
	}

	public static function on_feed_untrashed( int $post_id ): void {
		if ( get_post_type( $post_id ) === VEV_Import_Feed::POST_TYPE ) {
			self::schedule_feed( $post_id );
		}
	}

	public static function on_feed_deleted( int $post_id ): void {
		if ( get_post_type( $post_id ) === VEV_Import_Feed::POST_TYPE ) {
			self::unschedule_feed( $post_id );
			VEV_Import_Logger::clear_for_feed( $post_id );
		}
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	/**
	 * Called on plugin activation: create DB table and schedule all active feeds.
	 */
	public static function on_activate(): void {
		VEV_Import_Logger::create_table();
		foreach ( VEV_Import_Feed::get_active() as $feed ) {
			self::schedule_feed( $feed->ID );
		}
	}

	/**
	 * Called on plugin deactivation: unschedule all feed crons.
	 */
	public static function on_deactivate(): void {
		foreach ( VEV_Import_Feed::get_all() as $feed ) {
			self::unschedule_feed( $feed->ID );
		}
	}
}
