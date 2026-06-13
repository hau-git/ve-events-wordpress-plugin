<?php
/**
 * Import Manager — bootstraps the import module and manages WP-Cron schedules.
 *
 * @package VE_Events
 */

namespace VEV\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the vendored ICal library (not autoloaded — explicit require).
require_once __DIR__ . '/../ThirdParty/ICal/ICal.php';
require_once __DIR__ . '/../ThirdParty/ICal/Event.php';

/**
 * Bootstraps the import module and manages WP-Cron schedules.
 */
class Manager {

	const CRON_HOOK = 'vev_run_import';

	/**
	 * Registers the import module's hooks and ensures the log table exists.
	 */
	public static function init(): void {
		// Register CPT.
		Feed::init();

		// Register admin UI.
		AdminPage::init();

		// Register custom cron intervals.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );

		// Cron callback.
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_feed' ) );

		// Re-schedule cron when a feed post is saved/trashed/deleted.
		add_action( 'save_post_' . Feed::POST_TYPE, array( __CLASS__, 'on_feed_save' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_feed_trashed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_feed_untrashed' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_feed_deleted' ) );

		// DB table.
		Logger::maybe_create_table();
	}

	// -------------------------------------------------------------------------
	// Cron intervals
	// -------------------------------------------------------------------------

	/**
	 * Registers custom WP-Cron intervals used by import feeds.
	 *
	 * @param  array $schedules Existing cron schedules.
	 * @return array
	 */
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
	 *
	 * @param int $feed_id Feed post ID.
	 */
	public static function schedule_feed( int $feed_id ): void {
		$config   = Feed::get_config( $feed_id );
		$interval = $config['schedule'] ?? 'daily';

		// Check currently scheduled hook.
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
	 *
	 * @param int $feed_id Feed post ID.
	 */
	public static function unschedule_feed( int $feed_id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $feed_id ) );
	}

	/**
	 * Returns the currently scheduled interval name for a feed, or null.
	 *
	 * @param int $feed_id Feed post ID.
	 */
	private static function get_scheduled_interval( int $feed_id ): ?string {
		$crons = _get_cron_array();
		if ( ! $crons ) {
			return null;
		}
		$hook = self::CRON_HOOK;
		$key  = md5( serialize( array( $feed_id ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Must match WP-Cron's internal hashing.
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
	 * @param int $feed_id Feed post ID.
	 */
	public static function run_feed( int $feed_id ): array {
		$runner = new Runner( $feed_id );
		return $runner->run();
	}

	/**
	 * Runs a feed immediately (e.g. from admin "Run now" button).
	 *
	 * @param int $feed_id Feed post ID.
	 */
	public static function run_now( int $feed_id ): array {
		return self::run_feed( $feed_id );
	}

	// -------------------------------------------------------------------------
	// Feed post lifecycle hooks
	// -------------------------------------------------------------------------

	/**
	 * Reschedules a feed when its post is saved.
	 *
	 * @param int      $post_id Feed post ID.
	 * @param \WP_Post $post    Feed post object.
	 */
	public static function on_feed_save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' === $post->post_status ) {
			self::schedule_feed( $post_id );
		} else {
			self::unschedule_feed( $post_id );
		}
	}

	/**
	 * Unschedules a feed when its post is trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_feed_trashed( int $post_id ): void {
		if ( get_post_type( $post_id ) === Feed::POST_TYPE ) {
			self::unschedule_feed( $post_id );
		}
	}

	/**
	 * Reschedules a feed when its post is restored from trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_feed_untrashed( int $post_id ): void {
		if ( get_post_type( $post_id ) === Feed::POST_TYPE ) {
			self::schedule_feed( $post_id );
		}
	}

	/**
	 * Cleans up cron and logs when a feed post is permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_feed_deleted( int $post_id ): void {
		if ( get_post_type( $post_id ) === Feed::POST_TYPE ) {
			self::unschedule_feed( $post_id );
			Logger::clear_for_feed( $post_id );
		}
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	/**
	 * Called on plugin activation: create DB table and schedule all active feeds.
	 */
	public static function on_activate(): void {
		Logger::create_table();
		foreach ( Feed::get_active() as $feed ) {
			self::schedule_feed( $feed->ID );
		}
	}

	/**
	 * Called on plugin deactivation: unschedule all feed crons.
	 */
	public static function on_deactivate(): void {
		foreach ( Feed::get_all() as $feed ) {
			self::unschedule_feed( $feed->ID );
		}
	}
}
