<?php
/**
 * Import Runner — fetches, parses and imports a single feed.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure parser classes are available.
require_once __DIR__ . '/lib/Event.php';
require_once __DIR__ . '/lib/ICal.php';

class VEV_Import_Runner {

	// Meta keys stored on imported ve_event posts.
	const META_UID           = '_vev_import_uid';
	const META_FEED_ID       = '_vev_import_feed_id';
	const META_HASH          = '_vev_import_hash';
	const META_LAST_MODIFIED = '_vev_import_last_modified';
	const META_SERIES_UID    = '_vev_import_series_uid';

	private array  $config;
	private array  $counts        = array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0 );
	private array  $errors        = array();
	private int    $feed_id;
	private array  $processed_uids = array(); // UIDs actually written this run

	public function __construct( int $feed_id ) {
		$this->feed_id = $feed_id;
		$this->config  = VEV_Import_Feed::get_config( $feed_id );
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Runs the full import for this feed. Returns a result summary array.
	 */
	public function run(): array {
		$start_ms = (int) round( microtime( true ) * 1000 );

		if ( ! $this->config['active'] ) {
			return $this->finish( 'skipped', $start_ms );
		}

		if ( empty( $this->config['url'] ) ) {
			$this->errors[] = __( 'No ICS URL configured.', 've-events' );
			return $this->finish( 'error', $start_ms );
		}

		// 1. Parse ICS
		try {
			$ical = new \VEV_Import\ICal();
			$ical->initUrl( $this->config['url'], null, null, null, null, null, true );
		} catch ( \Exception $e ) {
			$this->errors[] = $e->getMessage();
			return $this->finish( 'error', $start_ms );
		}

		if ( ! $ical->hasEvents() ) {
			return $this->finish( 'success', $start_ms );
		}

		$events = $ical->events();

		// 2. Group by UID to detect series (recurring events)
		$groups = $this->group_by_uid( $events );

		// 3. Process each group
		foreach ( $groups as $uid => $group_events ) {
			$is_series   = count( $group_events ) > 1;
			$series_term = null;

			if ( $is_series ) {
				$series_term = $this->ensure_series_term( $group_events[0] );
			}

			foreach ( $group_events as $event ) {
				$this->process_event( $event, $is_series, $uid, $series_term );
			}
		}

		// 4. Handle removed events (if configured)
		if ( $this->config['delete_removed'] ) {
			$this->delete_removed();
		}

		$status = empty( $this->errors ) ? 'success' : ( $this->counts['created'] + $this->counts['updated'] > 0 ? 'partial' : 'error' );
		return $this->finish( $status, $start_ms );
	}

	// -------------------------------------------------------------------------
	// Per-event processing
	// -------------------------------------------------------------------------

	private function process_event( \VEV_Import\Event $event, bool $is_series, string $base_uid, ?WP_Term $series_term ): void {
		// Build a stable, unique import UID for this specific occurrence
		$import_uid = $is_series
			? $base_uid . '__' . ( $event->dtstart ?? md5( serialize( $event ) ) )
			: $base_uid;

		// Track every UID processed this run so delete_removed() uses the exact same strings.
		$this->processed_uids[] = $import_uid;

		// Map fields
		try {
			$mapped = VEV_Field_Mapper::map( $event, $this->config['field_map'], $this->config );
		} catch ( \Exception $e ) {
			$this->errors[] = sprintf( 'Mapping error for UID %s: %s', $import_uid, $e->getMessage() );
			$this->counts['skipped']++;
			return;
		}

		// Validate: we need at least a start date
		if ( empty( $mapped['meta']['_vev_start_utc'] ) ) {
			$this->errors[] = sprintf( 'Skipped UID %s: no start date.', $import_uid );
			$this->counts['skipped']++;
			return;
		}

		// Look up existing post by import UID + feed
		$existing_id = $this->find_existing( $import_uid );

		if ( $existing_id ) {
			$this->maybe_update( $existing_id, $import_uid, $mapped, $event, $series_term );
		} else {
			$this->create_event( $import_uid, $mapped, $event, $base_uid, $is_series, $series_term );
		}
	}

	// -------------------------------------------------------------------------
	// Create
	// -------------------------------------------------------------------------

	private function create_event(
		string    $import_uid,
		array     $mapped,
		\VEV_Import\Event $event,
		string    $base_uid,
		bool      $is_series,
		?WP_Term  $series_term
	): void {
		$post_data                = $mapped['post_data'];
		$post_data['post_type']   = VEV_Events::POST_TYPE;
		$post_data['meta_input']  = $this->build_meta( $import_uid, $mapped, $event );

		// Handle CANCELLED events
		if ( ( $event->status ?? '' ) === 'CANCELLED' ) {
			$post_data['post_status'] = 'draft';
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->errors[] = sprintf( 'Create failed for UID %s: %s', $import_uid, $post_id->get_error_message() );
			$this->counts['skipped']++;
			return;
		}

		$this->assign_taxonomies( $post_id, $mapped['taxonomies'] );
		$this->assign_default_taxonomies( $post_id );

		if ( $is_series && $series_term ) {
			wp_set_object_terms( $post_id, $series_term->term_id, 've_event_series', true );
		}

		$this->counts['created']++;
	}

	// -------------------------------------------------------------------------
	// Update
	// -------------------------------------------------------------------------

	private function maybe_update(
		int       $existing_id,
		string    $import_uid,
		array     $mapped,
		\VEV_Import\Event $event,
		?WP_Term  $series_term
	): void {
		$mode = $this->config['update_mode'];

		if ( $mode === 'never' ) {
			$this->counts['skipped']++;
			return;
		}

		if ( $mode === 'if_newer' ) {
			$stored_hash = get_post_meta( $existing_id, self::META_HASH, true );
			if ( $stored_hash === $mapped['import_hash'] ) {
				$this->counts['skipped']++;
				return;
			}
		}

		$post_data       = $mapped['post_data'];
		$post_data['ID'] = $existing_id;

		// Handle CANCELLED events
		if ( ( $event->status ?? '' ) === 'CANCELLED' ) {
			$post_data['post_status'] = 'draft';
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			$this->errors[] = sprintf( 'Update failed for UID %s: %s', $import_uid, $result->get_error_message() );
			$this->counts['skipped']++;
			return;
		}

		// Update meta
		foreach ( $this->build_meta( $import_uid, $mapped, $event ) as $key => $value ) {
			update_post_meta( $existing_id, $key, $value );
		}

		$this->assign_taxonomies( $existing_id, $mapped['taxonomies'] );

		if ( $series_term ) {
			wp_set_object_terms( $existing_id, $series_term->term_id, 've_event_series', true );
		}

		$this->counts['updated']++;
	}

	// -------------------------------------------------------------------------
	// Delete removed
	// -------------------------------------------------------------------------

	/**
	 * Trashes events from this feed that were not processed in the current run.
	 * Uses $this->processed_uids which contains the exact UID strings written
	 * during this run — no recomputation needed.
	 */
	private function delete_removed(): void {
		$existing_posts = get_posts( array(
			'post_type'      => VEV_Events::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'future' ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => self::META_FEED_ID,
					'value' => $this->feed_id,
				),
			),
			'fields'         => 'ids',
		) );

		foreach ( $existing_posts as $post_id ) {
			$uid = get_post_meta( $post_id, self::META_UID, true );
			if ( $uid && ! in_array( $uid, $this->processed_uids, true ) ) {
				wp_trash_post( $post_id );
				$this->counts['deleted']++;
			}
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Groups events by their UID. Events with the same UID are RRULE instances.
	 *
	 * @param  \VEV_Import\Event[] $events
	 * @return array<string, \VEV_Import\Event[]>
	 */
	private function group_by_uid( array $events ): array {
		$groups = array();
		foreach ( $events as $event ) {
			$uid = $event->uid ?? md5( serialize( $event ) );
			$groups[ $uid ][] = $event;
		}
		return $groups;
	}

	/**
	 * Ensures a ve_event_series term exists for recurring events.
	 * Uses the SUMMARY as the series name. Returns the WP_Term.
	 */
	private function ensure_series_term( \VEV_Import\Event $event ): ?WP_Term {
		$name = wp_strip_all_tags( $event->summary ?? __( 'Unnamed Series', 've-events' ) );

		// Try existing term first
		$term = get_term_by( 'name', $name, 've_event_series' );
		if ( $term ) {
			return $term;
		}

		$result = wp_insert_term( $name, 've_event_series' );
		if ( is_wp_error( $result ) ) {
			$this->errors[] = sprintf( 'Could not create series term "%s": %s', $name, $result->get_error_message() );
			return null;
		}

		return get_term( $result['term_id'], 've_event_series' );
	}

	/**
	 * Builds the meta array for insert/update.
	 */
	private function build_meta( string $import_uid, array $mapped, \VEV_Import\Event $event ): array {
		$meta = $mapped['meta'];

		// Import tracking
		$meta[ self::META_UID ]           = $import_uid;
		$meta[ self::META_FEED_ID ]       = $this->feed_id;
		$meta[ self::META_HASH ]          = $mapped['import_hash'];
		$meta[ self::META_LAST_MODIFIED ] = $event->last_modified
			? (int) strtotime( $event->last_modified )
			: 0;

		return $meta;
	}

	/**
	 * Assigns taxonomy terms from the mapping result.
	 *
	 * @param int   $post_id
	 * @param array $taxonomies  taxonomy => ['terms' => [...], 'create_terms' => bool]
	 */
	private function assign_taxonomies( int $post_id, array $taxonomies ): void {
		foreach ( $taxonomies as $taxonomy => $config ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term_ids = array();
			foreach ( $config['terms'] as $term_name ) {
				$term_name = sanitize_text_field( $term_name );
				$term      = get_term_by( 'name', $term_name, $taxonomy );

				if ( $term ) {
					$term_ids[] = $term->term_id;
				} elseif ( $config['create_terms'] ) {
					$result = wp_insert_term( $term_name, $taxonomy );
					if ( ! is_wp_error( $result ) ) {
						$term_ids[] = $result['term_id'];
					}
				}
			}

			if ( $term_ids ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
			}
		}
	}

	/**
	 * Assigns feed-level default taxonomy terms to a post.
	 */
	private function assign_default_taxonomies( int $post_id ): void {
		$defaults = $this->config['tax_defaults'] ?? array();
		foreach ( $defaults as $taxonomy => $term_ids ) {
			if ( ! taxonomy_exists( $taxonomy ) || empty( $term_ids ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $term_ids ), $taxonomy, true );
		}
	}

	/**
	 * Finds an existing ve_event post by import UID for this feed.
	 */
	private function find_existing( string $import_uid ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = %s AND m1.meta_value = %s
			 INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = %s AND m2.meta_value = %d
			 WHERE p.post_type = %s AND p.post_status != 'trash'
			 LIMIT 1",
			self::META_UID,
			$import_uid,
			self::META_FEED_ID,
			$this->feed_id,
			VEV_Events::POST_TYPE
		) );

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Finalises a run: logs results, updates feed meta, returns summary.
	 */
	private function finish( string $status, int $start_ms ): array {
		$duration_ms = (int) round( microtime( true ) * 1000 ) - $start_ms;

		VEV_Import_Logger::log( $this->feed_id, $status, $this->counts, $this->errors, $duration_ms );
		VEV_Import_Feed::update_run_stats( $this->feed_id, $status, $this->counts );

		return array(
			'feed_id'     => $this->feed_id,
			'status'      => $status,
			'counts'      => $this->counts,
			'errors'      => $this->errors,
			'duration_ms' => $duration_ms,
		);
	}
}
