<?php
/**
 * Abstract Import Runner — source-agnostic create/update/delete/match engine.
 *
 * @package VE_Events
 */

namespace VEV\Import;

use VEV\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared import engine.
 *
 * Concrete runners (iCal, ChurchDesk) implement {@see fetch_events()} to return a
 * list of normalised event rows; this base class handles the source-independent
 * work: matching existing posts, creating/updating them, assigning taxonomies,
 * setting the featured image, trashing removed events, logging and stats.
 *
 * A normalised row is an array with the following keys:
 *
 *     array{
 *         uid:            string,        // unique import id for this occurrence
 *         post_data:      array,         // post_title/content/excerpt
 *         meta:           array,         // _vev_* meta values
 *         taxonomies:     array,         // taxonomy => [ terms, create_terms ]
 *         import_hash:    string,        // MD5 of key fields for change detection
 *         last_modified:  int,           // source modification time (unix) or 0
 *         image_url:      string|null,   // featured-image source URL or null
 *         series_term_id: int|null,      // ve_event_series term id or null
 *         force_draft:    bool,          // force post_status = draft (e.g. cancelled)
 *     }
 */
abstract class AbstractRunner {

	// Meta keys stored on imported ve_event posts.
	const META_UID           = '_vev_import_uid';
	const META_FEED_ID       = '_vev_import_feed_id';
	const META_HASH          = '_vev_import_hash';
	const META_LAST_MODIFIED = '_vev_import_last_modified';
	const META_SERIES_UID    = '_vev_import_series_uid';
	const META_IMAGE_SRC     = '_vev_import_image_src';

	/**
	 * Feed configuration for the run.
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Feed post ID being processed.
	 *
	 * @var int
	 */
	protected int $feed_id;

	/**
	 * Running tally of processed items.
	 *
	 * @var array
	 */
	protected array $counts = array(
		'created' => 0,
		'updated' => 0,
		'deleted' => 0,
		'skipped' => 0,
	);

	/**
	 * Errors collected during the run.
	 *
	 * @var array
	 */
	protected array $errors = array();

	/**
	 * UIDs actually written this run.
	 *
	 * @var array
	 */
	protected array $processed_uids = array();

	/**
	 * Loads the feed config for the run.
	 *
	 * @param int $feed_id Feed post ID.
	 */
	public function __construct( int $feed_id ) {
		$this->feed_id = $feed_id;
		$this->config  = Feed::get_config( $feed_id );
	}

	// -------------------------------------------------------------------------
	// Contract
	// -------------------------------------------------------------------------

	/**
	 * Fetches the source and returns normalised event rows.
	 *
	 * @return array|null Array of normalised rows, [] when the source has no
	 *                    events, or null on a hard fetch error (in which case the
	 *                    implementation must have appended to {@see $errors}).
	 */
	abstract protected function fetch_events(): ?array;

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

		$rows = $this->fetch_events();

		if ( null === $rows ) {
			return $this->finish( 'error', $start_ms );
		}

		foreach ( $rows as $row ) {
			$this->process_row( $row );
		}

		if ( $this->config['delete_removed'] ) {
			$this->delete_removed();
		}

		$status = empty( $this->errors )
			? 'success'
			: ( $this->counts['created'] + $this->counts['updated'] > 0 ? 'partial' : 'error' );

		return $this->finish( $status, $start_ms );
	}

	// -------------------------------------------------------------------------
	// Per-row processing
	// -------------------------------------------------------------------------

	/**
	 * Imports a single normalised event row (create or update).
	 *
	 * @param array $row Normalised event row.
	 */
	protected function process_row( array $row ): void {
		$import_uid = (string) $row['uid'];

		// Track every UID processed this run so delete_removed() uses the exact same strings.
		$this->processed_uids[] = $import_uid;

		// Validate: we need at least a start date.
		if ( empty( $row['meta']['_vev_start_utc'] ) ) {
			$this->errors[] = sprintf( 'Skipped UID %s: no start date.', $import_uid );
			++$this->counts['skipped'];
			return;
		}

		$existing_id = $this->find_existing( $import_uid );

		if ( $existing_id ) {
			$this->maybe_update( $existing_id, $row );
		} else {
			$this->create_event( $row );
		}
	}

	// -------------------------------------------------------------------------
	// Create
	// -------------------------------------------------------------------------

	/**
	 * Inserts a new event post from a normalised row.
	 *
	 * @param array $row Normalised event row.
	 */
	protected function create_event( array $row ): void {
		$post_data               = $row['post_data'];
		$post_data['post_type']  = Constants::POST_TYPE;
		$post_data['meta_input'] = $this->build_meta( $row );

		if ( ! empty( $row['force_draft'] ) ) {
			$post_data['post_status'] = 'draft';
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->errors[] = sprintf( 'Create failed for UID %s: %s', $row['uid'], $post_id->get_error_message() );
			++$this->counts['skipped'];
			return;
		}

		$this->assign_taxonomies( $post_id, $row['taxonomies'] );
		$this->assign_default_taxonomies( $post_id );

		if ( ! empty( $row['series_term_id'] ) ) {
			wp_set_object_terms( $post_id, (int) $row['series_term_id'], Constants::TAX_SERIES, true );
		}

		$this->maybe_set_featured_image( $post_id, $row['image_url'] ?? null );

		++$this->counts['created'];
	}

	// -------------------------------------------------------------------------
	// Update
	// -------------------------------------------------------------------------

	/**
	 * Updates an existing event post if the update mode permits.
	 *
	 * @param int   $existing_id Existing event post ID.
	 * @param array $row         Normalised event row.
	 */
	protected function maybe_update( int $existing_id, array $row ): void {
		$mode = $this->config['update_mode'];

		if ( 'never' === $mode ) {
			++$this->counts['skipped'];
			return;
		}

		if ( 'if_newer' === $mode ) {
			$stored_hash = get_post_meta( $existing_id, self::META_HASH, true );
			if ( $stored_hash === $row['import_hash'] ) {
				++$this->counts['skipped'];
				return;
			}
		}

		$post_data       = $row['post_data'];
		$post_data['ID'] = $existing_id;

		if ( ! empty( $row['force_draft'] ) ) {
			$post_data['post_status'] = 'draft';
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			$this->errors[] = sprintf( 'Update failed for UID %s: %s', $row['uid'], $result->get_error_message() );
			++$this->counts['skipped'];
			return;
		}

		foreach ( $this->build_meta( $row ) as $key => $value ) {
			update_post_meta( $existing_id, $key, $value );
		}

		$this->assign_taxonomies( $existing_id, $row['taxonomies'] );

		if ( ! empty( $row['series_term_id'] ) ) {
			wp_set_object_terms( $existing_id, (int) $row['series_term_id'], Constants::TAX_SERIES, true );
		}

		$this->maybe_set_featured_image( $existing_id, $row['image_url'] ?? null );

		++$this->counts['updated'];
	}

	// -------------------------------------------------------------------------
	// Delete removed
	// -------------------------------------------------------------------------

	/**
	 * Trashes events from this feed that were not processed in the current run.
	 * Uses $this->processed_uids which contains the exact UID strings written
	 * during this run — no recomputation needed.
	 */
	protected function delete_removed(): void {
		$existing_posts = get_posts(
			array(
				'post_type'      => Constants::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'future' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_FEED_ID,
						'value' => $this->feed_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $existing_posts as $post_id ) {
			$uid = get_post_meta( $post_id, self::META_UID, true );
			if ( $uid && ! in_array( $uid, $this->processed_uids, true ) ) {
				wp_trash_post( $post_id );
				++$this->counts['deleted'];
			}
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds the meta array for insert/update from a normalised row.
	 *
	 * @param array $row Normalised event row.
	 */
	protected function build_meta( array $row ): array {
		$meta = $row['meta'];

		// Import tracking.
		$meta[ self::META_UID ]           = (string) $row['uid'];
		$meta[ self::META_FEED_ID ]       = $this->feed_id;
		$meta[ self::META_HASH ]          = $row['import_hash'];
		$meta[ self::META_LAST_MODIFIED ] = (int) ( $row['last_modified'] ?? 0 );

		return $meta;
	}

	/**
	 * Assigns taxonomy terms from a normalised row's taxonomy map.
	 *
	 * @param int   $post_id    Event post ID.
	 * @param array $taxonomies taxonomy => ['terms' => [...], 'create_terms' => bool].
	 */
	protected function assign_taxonomies( int $post_id, array $taxonomies ): void {
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

						// Apply optional per-term meta (e.g. category colour) on creation.
						if ( ! empty( $config['term_meta'][ $term_name ] ) && is_array( $config['term_meta'][ $term_name ] ) ) {
							foreach ( $config['term_meta'][ $term_name ] as $mkey => $mval ) {
								update_term_meta( $result['term_id'], $mkey, $mval );
							}
						}
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
	 *
	 * @param int $post_id Event post ID.
	 */
	protected function assign_default_taxonomies( int $post_id ): void {
		$defaults = $this->config['tax_defaults'] ?? array();
		foreach ( $defaults as $taxonomy => $term_ids ) {
			if ( ! taxonomy_exists( $taxonomy ) || empty( $term_ids ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $term_ids ), $taxonomy, true );
		}
	}

	/**
	 * Downloads and assigns a featured image, skipping unchanged sources.
	 *
	 * @param int         $post_id Event post ID.
	 * @param string|null $url     Source image URL, or null to skip.
	 */
	protected function maybe_set_featured_image( int $post_id, ?string $url ): void {
		if ( empty( $url ) || ! function_exists( 'media_sideload_image' ) ) {
			return;
		}

		$src_hash  = md5( $url );
		$stored    = get_post_meta( $post_id, self::META_IMAGE_SRC, true );
		$has_thumb = (bool) get_post_thumbnail_id( $post_id );

		// Same source already imported and thumbnail still present — nothing to do.
		if ( $stored === $src_hash && $has_thumb ) {
			return;
		}

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			$this->errors[] = sprintf( 'Image import failed for post %d: %s', $post_id, $attachment_id->get_error_message() );
			return;
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
		update_post_meta( $post_id, self::META_IMAGE_SRC, $src_hash );
	}

	/**
	 * Finds an existing ve_event post by import UID for this feed.
	 *
	 * @param string $import_uid Import UID for this occurrence.
	 */
	protected function find_existing( string $import_uid ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = %s AND m1.meta_value = %s
			 INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = %s AND m2.meta_value = %d
			 WHERE p.post_type = %s AND p.post_status != 'trash'
			 LIMIT 1",
				self::META_UID,
				$import_uid,
				self::META_FEED_ID,
				$this->feed_id,
				Constants::POST_TYPE
			)
		);

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Adds an error message to the run.
	 *
	 * @param string $message Error message.
	 */
	protected function add_error( string $message ): void {
		$this->errors[] = $message;
	}

	/**
	 * Finalises a run: logs results, updates feed meta, returns summary.
	 *
	 * @param string $status   Run status.
	 * @param int    $start_ms Run start time in milliseconds.
	 */
	protected function finish( string $status, int $start_ms ): array {
		$duration_ms = (int) round( microtime( true ) * 1000 ) - $start_ms;

		Logger::log( $this->feed_id, $status, $this->counts, $this->errors, $duration_ms );
		Feed::update_run_stats( $this->feed_id, $status, $this->counts );

		return array(
			'feed_id'     => $this->feed_id,
			'status'      => $status,
			'counts'      => $this->counts,
			'errors'      => $this->errors,
			'duration_ms' => $duration_ms,
		);
	}
}
