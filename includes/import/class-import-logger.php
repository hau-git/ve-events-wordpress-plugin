<?php
/**
 * Import Logger — creates and manages the import log DB table.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Import_Logger {

	const TABLE_VERSION_OPTION = 'vev_import_log_db_version';
	const TABLE_VERSION        = '1';

	/**
	 * Creates the log table if it does not exist.
	 * Called on plugin activation and on init (version check).
	 */
	public static function maybe_create_table(): void {
		if ( get_option( self::TABLE_VERSION_OPTION ) === self::TABLE_VERSION ) {
			return;
		}
		self::create_table();
	}

	public static function create_table(): void {
		global $wpdb;

		$table      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_id     bigint(20) UNSIGNED NOT NULL,
			run_time    datetime NOT NULL,
			status      varchar(20) NOT NULL DEFAULT 'success',
			created     int NOT NULL DEFAULT 0,
			updated     int NOT NULL DEFAULT 0,
			deleted     int NOT NULL DEFAULT 0,
			skipped     int NOT NULL DEFAULT 0,
			errors      longtext,
			duration_ms int NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY feed_id (feed_id),
			KEY run_time (run_time)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'vev_import_log';
	}

	// -------------------------------------------------------------------------
	// Writing
	// -------------------------------------------------------------------------

	/**
	 * Inserts a log entry.
	 *
	 * @param int    $feed_id
	 * @param string $status      'success'|'error'|'partial'
	 * @param array  $counts      Keys: created, updated, deleted, skipped
	 * @param array  $errors      Array of error strings
	 * @param int    $duration_ms Execution time in milliseconds
	 */
	public static function log(
		int    $feed_id,
		string $status,
		array  $counts,
		array  $errors      = array(),
		int    $duration_ms = 0
	): void {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'feed_id'     => $feed_id,
				'run_time'    => current_time( 'mysql', true ), // UTC
				'status'      => $status,
				'created'     => (int) ( $counts['created']  ?? 0 ),
				'updated'     => (int) ( $counts['updated']  ?? 0 ),
				'deleted'     => (int) ( $counts['deleted']  ?? 0 ),
				'skipped'     => (int) ( $counts['skipped']  ?? 0 ),
				'errors'      => $errors ? wp_json_encode( array_values( $errors ) ) : null,
				'duration_ms' => $duration_ms,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// Reading
	// -------------------------------------------------------------------------

	/**
	 * Returns log entries for a feed, newest first.
	 *
	 * @param  int $feed_id
	 * @param  int $limit   Max number of rows
	 * @return array
	 */
	public static function get_for_feed( int $feed_id, int $limit = 20 ): array {
		global $wpdb;

		$table = self::table_name();
		$limit = absint( $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE feed_id = %d ORDER BY run_time DESC LIMIT %d",
				$feed_id,
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['errors'] = $row['errors'] ? json_decode( $row['errors'], true ) : array();
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Returns the latest log entry for a feed.
	 */
	public static function get_latest( int $feed_id ): ?array {
		$rows = self::get_for_feed( $feed_id, 1 );
		return $rows[0] ?? null;
	}

	/**
	 * Deletes all log entries for a feed.
	 */
	public static function clear_for_feed( int $feed_id ): void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'feed_id' => $feed_id ), array( '%d' ) );
	}

	/**
	 * Deletes log entries older than $days days.
	 */
	public static function prune( int $days = 90 ): void {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE run_time < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
