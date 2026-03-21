<?php
/**
 * Import Feed CPT — registers and manages vev_import_feed posts.
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VEV_Import_Feed {

	const POST_TYPE = 'vev_import_feed';

	// Meta keys
	const META_TYPE            = '_vev_if_type';
	const META_URL             = '_vev_if_url';
	const META_SCHEDULE        = '_vev_if_schedule';
	const META_FIELD_MAP       = '_vev_if_field_map';
	const META_TAX_DEFAULTS    = '_vev_if_tax_defaults';
	const META_UPDATE_MODE     = '_vev_if_update_mode';
	const META_DELETE_REMOVED  = '_vev_if_delete_removed';
	const META_POST_STATUS     = '_vev_if_post_status';
	const META_LAST_RUN        = '_vev_if_last_run';
	const META_LAST_STATUS     = '_vev_if_last_status';
	const META_LAST_COUNTS     = '_vev_if_last_counts';
	const META_HTTP_TIMEOUT    = '_vev_if_http_timeout';

	// Default field mapping
	const DEFAULT_FIELD_MAP = array(
		'summary'     => array( 'target' => 'post_title',          'enabled' => true,  'create_terms' => false ),
		'description' => array( 'target' => 'post_content',        'enabled' => true,  'create_terms' => false ),
		'location'    => array( 'target' => 've_event_location',   'enabled' => true,  'create_terms' => true  ),
		'url'         => array( 'target' => '_vev_info_url',        'enabled' => true,  'create_terms' => false ),
		'organizer'   => array( 'target' => '_vev_speaker',         'enabled' => true,  'create_terms' => false ),
		'categories'  => array( 'target' => 've_event_category',   'enabled' => true,  'create_terms' => true  ),
	);

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Import Feeds', 've-events' ),
					'singular_name'      => __( 'Import Feed', 've-events' ),
					'add_new'            => __( 'Add Feed', 've-events' ),
					'add_new_item'       => __( 'Add Import Feed', 've-events' ),
					'edit_item'          => __( 'Edit Import Feed', 've-events' ),
					'not_found'          => __( 'No import feeds found.', 've-events' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Returns all feeds (published = active, draft = inactive).
	 *
	 * @param string|null $status 'publish'|'draft'|null (all)
	 * @return WP_Post[]
	 */
	public static function get_all( ?string $status = null ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $status ) {
			$args['post_status'] = $status;
		} else {
			$args['post_status'] = array( 'publish', 'draft' );
		}
		return get_posts( $args );
	}

	/**
	 * Returns active feeds only.
	 *
	 * @return WP_Post[]
	 */
	public static function get_active(): array {
		return self::get_all( 'publish' );
	}

	/**
	 * Returns a single feed post.
	 */
	public static function get( int $feed_id ): ?WP_Post {
		$post = get_post( $feed_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}
		return $post;
	}

	/**
	 * Creates a new feed.
	 *
	 * @param  array $data Keys: title, type, url, schedule, field_map, tax_defaults,
	 *                           update_mode, delete_removed, post_status, http_timeout, active
	 * @return int|\WP_Error  New post ID or WP_Error.
	 */
	public static function create( array $data ): int|\WP_Error {
		$post_id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sanitize_text_field( $data['title'] ?? __( 'Unnamed Feed', 've-events' ) ),
			'post_status' => ! empty( $data['active'] ) ? 'publish' : 'draft',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $data );
		return $post_id;
	}

	/**
	 * Updates an existing feed.
	 *
	 * @param  int   $feed_id
	 * @param  array $data
	 * @return bool
	 */
	public static function update( int $feed_id, array $data ): bool {
		$update = array(
			'ID'         => $feed_id,
			'post_title' => sanitize_text_field( $data['title'] ?? '' ),
		);

		if ( isset( $data['active'] ) ) {
			$update['post_status'] = $data['active'] ? 'publish' : 'draft';
		}

		$result = wp_update_post( $update );
		if ( ! $result || is_wp_error( $result ) ) {
			return false;
		}

		self::save_meta( $feed_id, $data );
		return true;
	}

	/**
	 * Deletes a feed and all its tracked events' import meta.
	 */
	public static function delete( int $feed_id ): void {
		wp_delete_post( $feed_id, true );
	}

	/**
	 * Toggles active status.
	 */
	public static function set_active( int $feed_id, bool $active ): void {
		wp_update_post( array(
			'ID'          => $feed_id,
			'post_status' => $active ? 'publish' : 'draft',
		) );
	}

	// -------------------------------------------------------------------------
	// Meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Saves feed configuration meta from a data array.
	 */
	private static function save_meta( int $post_id, array $data ): void {
		$map = array(
			'type'           => self::META_TYPE,
			'url'            => self::META_URL,
			'schedule'       => self::META_SCHEDULE,
			'update_mode'    => self::META_UPDATE_MODE,
			'post_status'    => self::META_POST_STATUS,
			'http_timeout'   => self::META_HTTP_TIMEOUT,
		);

		foreach ( $map as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $data[ $key ] ) );
			}
		}

		if ( isset( $data['delete_removed'] ) ) {
			update_post_meta( $post_id, self::META_DELETE_REMOVED, (int) (bool) $data['delete_removed'] );
		}

		if ( isset( $data['field_map'] ) && is_array( $data['field_map'] ) ) {
			update_post_meta( $post_id, self::META_FIELD_MAP, wp_json_encode( $data['field_map'] ) );
		}

		if ( isset( $data['tax_defaults'] ) && is_array( $data['tax_defaults'] ) ) {
			update_post_meta( $post_id, self::META_TAX_DEFAULTS, wp_json_encode( $data['tax_defaults'] ) );
		}
	}

	/**
	 * Returns a structured config array for a feed.
	 */
	public static function get_config( int $feed_id ): array {
		$field_map_raw   = get_post_meta( $feed_id, self::META_FIELD_MAP, true );
		$tax_default_raw = get_post_meta( $feed_id, self::META_TAX_DEFAULTS, true );

		$field_map   = $field_map_raw   ? json_decode( $field_map_raw, true )   : self::DEFAULT_FIELD_MAP;
		$tax_default = $tax_default_raw ? json_decode( $tax_default_raw, true ) : array();

		return array(
			'id'             => $feed_id,
			'title'          => get_the_title( $feed_id ),
			'active'         => get_post_status( $feed_id ) === 'publish',
			'type'           => get_post_meta( $feed_id, self::META_TYPE, true ) ?: 'ics_url',
			'url'            => get_post_meta( $feed_id, self::META_URL, true ),
			'schedule'       => get_post_meta( $feed_id, self::META_SCHEDULE, true ) ?: 'daily',
			'field_map'      => $field_map,
			'tax_defaults'   => $tax_default,
			'update_mode'    => get_post_meta( $feed_id, self::META_UPDATE_MODE, true ) ?: 'if_newer',
			'delete_removed' => (bool) get_post_meta( $feed_id, self::META_DELETE_REMOVED, true ),
			'post_status'    => get_post_meta( $feed_id, self::META_POST_STATUS, true ) ?: 'publish',
			'http_timeout'   => (int) ( get_post_meta( $feed_id, self::META_HTTP_TIMEOUT, true ) ?: 30 ),
			'last_run'       => (int) get_post_meta( $feed_id, self::META_LAST_RUN, true ),
			'last_status'    => get_post_meta( $feed_id, self::META_LAST_STATUS, true ),
			'last_counts'    => json_decode( get_post_meta( $feed_id, self::META_LAST_COUNTS, true ) ?: '{}', true ),
		);
	}

	/**
	 * Updates last-run stats on the feed post.
	 */
	public static function update_run_stats( int $feed_id, string $status, array $counts ): void {
		update_post_meta( $feed_id, self::META_LAST_RUN, time() );
		update_post_meta( $feed_id, self::META_LAST_STATUS, $status );
		update_post_meta( $feed_id, self::META_LAST_COUNTS, wp_json_encode( $counts ) );
	}

	// -------------------------------------------------------------------------
	// Schedule helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns available import schedules.
	 */
	public static function get_schedules(): array {
		return array(
			'every15min' => __( 'Every 15 Minutes', 've-events' ),
			'every30min' => __( 'Every 30 Minutes', 've-events' ),
			'hourly'     => __( 'Hourly', 've-events' ),
			'twicedaily' => __( 'Twice Daily', 've-events' ),
			'daily'      => __( 'Daily', 've-events' ),
			'weekly'     => __( 'Weekly', 've-events' ),
		);
	}

	/**
	 * Returns available update modes.
	 */
	public static function get_update_modes(): array {
		return array(
			'if_newer' => __( 'Update if changed', 've-events' ),
			'always'   => __( 'Always overwrite', 've-events' ),
			'never'    => __( 'Never update', 've-events' ),
		);
	}
}
