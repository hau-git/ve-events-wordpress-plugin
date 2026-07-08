<?php
/**
 * Registers the event post type, taxonomies, and meta.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration of the event post type, taxonomies, and meta fields.
 */
final class PostType {

	/**
	 * Hook registration into WordPress.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the post type, taxonomies, and meta.
	 */
	public static function register(): void {
		self::register_post_type();
		self::register_taxonomies();
		self::register_meta();
		self::register_term_meta();
		self::maybe_flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules once after registration when flagged.
	 */
	private static function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'vev_flush_rewrite_rules' ) ) {
			delete_transient( 'vev_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Register the event post type.
	 */
	private static function register_post_type(): void {
		$settings     = Settings::get();
		$slug_single  = $settings['slug_single'] ?? 'event';
		$slug_archive = $settings['slug_archive'] ?? 'events';

		$labels = array(
			'name'                  => _x( 'Events', 'post type general name', 've-events' ),
			'singular_name'         => _x( 'Event', 'post type singular name', 've-events' ),
			'add_new'               => _x( 'Add New', 'event', 've-events' ),
			'add_new_item'          => __( 'Add New Event', 've-events' ),
			'edit_item'             => __( 'Edit Event', 've-events' ),
			'new_item'              => __( 'New Event', 've-events' ),
			'view_item'             => __( 'View Event', 've-events' ),
			'view_items'            => __( 'View Events', 've-events' ),
			'search_items'          => __( 'Search Events', 've-events' ),
			'not_found'             => __( 'No events found', 've-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash', 've-events' ),
			'all_items'             => __( 'All Events', 've-events' ),
			'archives'              => __( 'Event Archives', 've-events' ),
			'attributes'            => __( 'Event Attributes', 've-events' ),
			'insert_into_item'      => __( 'Insert into event', 've-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 've-events' ),
			'featured_image'        => __( 'Event Image', 've-events' ),
			'set_featured_image'    => __( 'Set event image', 've-events' ),
			'remove_featured_image' => __( 'Remove event image', 've-events' ),
			'use_featured_image'    => __( 'Use as event image', 've-events' ),
			'menu_name'             => _x( 'Events', 'admin menu', 've-events' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $slug_single,
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => $slug_archive,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_rest'       => true,
			'rest_base'          => 'events',
			'map_meta_cap'       => true,
		);

		register_post_type( Constants::POST_TYPE, $args );
	}

	/**
	 * Register the event taxonomies.
	 */
	private static function register_taxonomies(): void {
		register_taxonomy(
			Constants::TAX_CATEGORY,
			array( Constants::POST_TYPE ),
			array(
				'labels'            => array(
					'name'              => __( 'Event Categories', 've-events' ),
					'singular_name'     => __( 'Event Category', 've-events' ),
					'search_items'      => __( 'Search Event Categories', 've-events' ),
					'all_items'         => __( 'All Event Categories', 've-events' ),
					'parent_item'       => __( 'Parent Event Category', 've-events' ),
					'parent_item_colon' => __( 'Parent Event Category:', 've-events' ),
					'edit_item'         => __( 'Edit Event Category', 've-events' ),
					'update_item'       => __( 'Update Event Category', 've-events' ),
					'add_new_item'      => __( 'Add New Event Category', 've-events' ),
					'new_item_name'     => __( 'New Event Category Name', 've-events' ),
					'menu_name'         => __( 'Categories', 've-events' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'event-category',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			Constants::TAX_LOCATION,
			array( Constants::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Locations', 've-events' ),
					'singular_name' => __( 'Location', 've-events' ),
					'search_items'  => __( 'Search Locations', 've-events' ),
					'all_items'     => __( 'All Locations', 've-events' ),
					'edit_item'     => __( 'Edit Location', 've-events' ),
					'update_item'   => __( 'Update Location', 've-events' ),
					'add_new_item'  => __( 'Add New Location', 've-events' ),
					'new_item_name' => __( 'New Location Name', 've-events' ),
					'menu_name'     => __( 'Locations', 've-events' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'event-location',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			Constants::TAX_TOPIC,
			array( Constants::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Topics', 've-events' ),
					'singular_name' => __( 'Topic', 've-events' ),
					'search_items'  => __( 'Search Topics', 've-events' ),
					'all_items'     => __( 'All Topics', 've-events' ),
					'edit_item'     => __( 'Edit Topic', 've-events' ),
					'update_item'   => __( 'Update Topic', 've-events' ),
					'add_new_item'  => __( 'Add New Topic', 've-events' ),
					'new_item_name' => __( 'New Topic Name', 've-events' ),
					'menu_name'     => __( 'Topics', 've-events' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'event-topic',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			Constants::TAX_SERIES,
			array( Constants::POST_TYPE ),
			array(
				'labels'            => array(
					'name'              => __( 'Series', 've-events' ),
					'singular_name'     => __( 'Series', 've-events' ),
					'search_items'      => __( 'Search Series', 've-events' ),
					'all_items'         => __( 'All Series', 've-events' ),
					'parent_item'       => __( 'Parent Series', 've-events' ),
					'parent_item_colon' => __( 'Parent Series:', 've-events' ),
					'edit_item'         => __( 'Edit Series', 've-events' ),
					'update_item'       => __( 'Update Series', 've-events' ),
					'add_new_item'      => __( 'Add New Series', 've-events' ),
					'new_item_name'     => __( 'New Series Name', 've-events' ),
					'menu_name'         => __( 'Series', 've-events' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'event-series',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register term meta for the event taxonomies.
	 */
	private static function register_term_meta(): void {
		$auth = static function () {
			return current_user_can( 'manage_categories' );
		};
		$base = array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => $auth,
		);

		register_term_meta( Constants::TAX_LOCATION, Constants::TERM_META_LOCATION_ADDRESS, $base );
		register_term_meta( Constants::TAX_LOCATION, Constants::TERM_META_LOCATION_MAPS_URL, $base );
		register_term_meta( Constants::TAX_CATEGORY, Constants::TERM_META_CATEGORY_COLOR, $base );
	}

	/**
	 * Register post meta for the event post type.
	 */
	private static function register_meta(): void {
		$args = array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => static function () {
				return current_user_can( 'edit_posts' );
			},
		);

		register_post_meta( Constants::POST_TYPE, Constants::META_START_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_END_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_ALL_DAY, array_merge( $args, array( 'type' => 'boolean' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_HIDE_END, array_merge( $args, array( 'type' => 'boolean' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_SPEAKER, $args );
		register_post_meta( Constants::POST_TYPE, Constants::META_SPECIAL, $args );
		register_post_meta( Constants::POST_TYPE, Constants::META_INFO_URL, array_merge( $args, array( 'type' => 'string' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_EVENT_STATUS, $args );

		// Organizer & offer/ticket meta.
		register_post_meta( Constants::POST_TYPE, Constants::META_ORGANIZER, array_merge( $args, array( 'sanitize_callback' => 'sanitize_text_field' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_ORGANIZER_URL, array_merge( $args, array( 'sanitize_callback' => 'esc_url_raw' ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_PRICE, array_merge( $args, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_price' ) ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_PRICE_CURRENCY, array_merge( $args, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_currency' ) ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_AVAILABILITY, array_merge( $args, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_availability' ) ) ) );
		register_post_meta( Constants::POST_TYPE, Constants::META_ATTENDANCE_MODE, array_merge( $args, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_attendance_mode' ) ) ) );

		// Computed date meta — internal, not exposed via REST.
		$int_args = array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		);
		$str_args = array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_post_meta( Constants::POST_TYPE, Constants::META_START_HOUR, $int_args );
		register_post_meta( Constants::POST_TYPE, Constants::META_START_WEEKDAY, $int_args );
		register_post_meta( Constants::POST_TYPE, Constants::META_START_MONTH, $int_args );
		register_post_meta( Constants::POST_TYPE, Constants::META_START_DATE, $str_args );
		register_post_meta( Constants::POST_TYPE, Constants::META_TIME_SLOT, $str_args );
	}

	/**
	 * Sanitize a price into a normalized numeric string (or '' when unset/invalid).
	 *
	 * Accepts comma or dot decimals; stores as a string so Schema.org offers can
	 * emit it verbatim without float rounding artefacts.
	 *
	 * @param mixed $value Raw price value.
	 */
	public static function sanitize_price( $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$value = str_replace( ',', '.', $value );
		return preg_match( '/^\d+(\.\d{1,2})?$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize an ISO 4217 currency code (uppercase, 3 letters), or ''.
	 *
	 * @param mixed $value Raw currency value.
	 */
	public static function sanitize_currency( $value ): string {
		$value = strtoupper( sanitize_text_field( (string) $value ) );
		return preg_match( '/^[A-Z]{3}$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize the Schema.org availability value against a whitelist.
	 *
	 * @param mixed $value Raw availability value.
	 */
	public static function sanitize_availability( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, array( 'InStock', 'SoldOut', 'PreOrder' ), true ) ? $value : '';
	}

	/**
	 * Sanitize the attendance-mode value against a whitelist.
	 *
	 * @param mixed $value Raw attendance-mode value.
	 */
	public static function sanitize_attendance_mode( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, \VEV\Support\AttendanceMode::OPTIONS, true ) ? $value : '';
	}

	/**
	 * Register and flush rewrite rules on plugin activation.
	 */
	public static function activate(): void {
		self::register();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
