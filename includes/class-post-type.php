<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VEV_Post_Type {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		self::register_post_type();
		self::register_taxonomies();
		self::register_meta();
	}

	private static function register_post_type(): void {
		$labels = array(
			'name'                  => __( 'Events', VEV_Events::TEXTDOMAIN ),
			'singular_name'         => __( 'Event', VEV_Events::TEXTDOMAIN ),
			'add_new'               => __( 'Add New', VEV_Events::TEXTDOMAIN ),
			'add_new_item'          => __( 'Add New Event', VEV_Events::TEXTDOMAIN ),
			'edit_item'             => __( 'Edit Event', VEV_Events::TEXTDOMAIN ),
			'new_item'              => __( 'New Event', VEV_Events::TEXTDOMAIN ),
			'view_item'             => __( 'View Event', VEV_Events::TEXTDOMAIN ),
			'view_items'            => __( 'View Events', VEV_Events::TEXTDOMAIN ),
			'search_items'          => __( 'Search Events', VEV_Events::TEXTDOMAIN ),
			'not_found'             => __( 'No events found', VEV_Events::TEXTDOMAIN ),
			'not_found_in_trash'    => __( 'No events found in Trash', VEV_Events::TEXTDOMAIN ),
			'all_items'             => __( 'All Events', VEV_Events::TEXTDOMAIN ),
			'archives'              => __( 'Event Archives', VEV_Events::TEXTDOMAIN ),
			'attributes'            => __( 'Event Attributes', VEV_Events::TEXTDOMAIN ),
			'insert_into_item'      => __( 'Insert into event', VEV_Events::TEXTDOMAIN ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', VEV_Events::TEXTDOMAIN ),
			'featured_image'        => __( 'Event Image', VEV_Events::TEXTDOMAIN ),
			'set_featured_image'    => __( 'Set event image', VEV_Events::TEXTDOMAIN ),
			'remove_featured_image' => __( 'Remove event image', VEV_Events::TEXTDOMAIN ),
			'use_featured_image'    => __( 'Use as event image', VEV_Events::TEXTDOMAIN ),
			'menu_name'             => __( 'Events', VEV_Events::TEXTDOMAIN ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'event', 'with_front' => false ),
			'capability_type'    => 'post',
			'has_archive'        => 'events',
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_rest'       => true,
			'rest_base'          => 'events',
			'map_meta_cap'       => true,
		);

		register_post_type( VEV_Events::POST_TYPE, $args );
	}

	private static function register_taxonomies(): void {
		register_taxonomy(
			VEV_Events::TAX_CATEGORY,
			array( VEV_Events::POST_TYPE ),
			array(
				'labels'            => array(
					'name'              => __( 'Event Categories', VEV_Events::TEXTDOMAIN ),
					'singular_name'     => __( 'Event Category', VEV_Events::TEXTDOMAIN ),
					'search_items'      => __( 'Search Event Categories', VEV_Events::TEXTDOMAIN ),
					'all_items'         => __( 'All Event Categories', VEV_Events::TEXTDOMAIN ),
					'parent_item'       => __( 'Parent Event Category', VEV_Events::TEXTDOMAIN ),
					'parent_item_colon' => __( 'Parent Event Category:', VEV_Events::TEXTDOMAIN ),
					'edit_item'         => __( 'Edit Event Category', VEV_Events::TEXTDOMAIN ),
					'update_item'       => __( 'Update Event Category', VEV_Events::TEXTDOMAIN ),
					'add_new_item'      => __( 'Add New Event Category', VEV_Events::TEXTDOMAIN ),
					'new_item_name'     => __( 'New Event Category Name', VEV_Events::TEXTDOMAIN ),
					'menu_name'         => __( 'Categories', VEV_Events::TEXTDOMAIN ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-category', 'with_front' => false ),
			)
		);

		register_taxonomy(
			VEV_Events::TAX_LOCATION,
			array( VEV_Events::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Locations', VEV_Events::TEXTDOMAIN ),
					'singular_name' => __( 'Location', VEV_Events::TEXTDOMAIN ),
					'search_items'  => __( 'Search Locations', VEV_Events::TEXTDOMAIN ),
					'all_items'     => __( 'All Locations', VEV_Events::TEXTDOMAIN ),
					'edit_item'     => __( 'Edit Location', VEV_Events::TEXTDOMAIN ),
					'update_item'   => __( 'Update Location', VEV_Events::TEXTDOMAIN ),
					'add_new_item'  => __( 'Add New Location', VEV_Events::TEXTDOMAIN ),
					'new_item_name' => __( 'New Location Name', VEV_Events::TEXTDOMAIN ),
					'menu_name'     => __( 'Locations', VEV_Events::TEXTDOMAIN ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-location', 'with_front' => false ),
			)
		);

		register_taxonomy(
			VEV_Events::TAX_TOPIC,
			array( VEV_Events::POST_TYPE ),
			array(
				'labels'            => array(
					'name'          => __( 'Topics', VEV_Events::TEXTDOMAIN ),
					'singular_name' => __( 'Topic', VEV_Events::TEXTDOMAIN ),
					'search_items'  => __( 'Search Topics', VEV_Events::TEXTDOMAIN ),
					'all_items'     => __( 'All Topics', VEV_Events::TEXTDOMAIN ),
					'edit_item'     => __( 'Edit Topic', VEV_Events::TEXTDOMAIN ),
					'update_item'   => __( 'Update Topic', VEV_Events::TEXTDOMAIN ),
					'add_new_item'  => __( 'Add New Topic', VEV_Events::TEXTDOMAIN ),
					'new_item_name' => __( 'New Topic Name', VEV_Events::TEXTDOMAIN ),
					'menu_name'     => __( 'Topics', VEV_Events::TEXTDOMAIN ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-topic', 'with_front' => false ),
			)
		);

		register_taxonomy(
			VEV_Events::TAX_SERIES,
			array( VEV_Events::POST_TYPE ),
			array(
				'labels'            => array(
					'name'              => __( 'Series', VEV_Events::TEXTDOMAIN ),
					'singular_name'     => __( 'Series', VEV_Events::TEXTDOMAIN ),
					'search_items'      => __( 'Search Series', VEV_Events::TEXTDOMAIN ),
					'all_items'         => __( 'All Series', VEV_Events::TEXTDOMAIN ),
					'parent_item'       => __( 'Parent Series', VEV_Events::TEXTDOMAIN ),
					'parent_item_colon' => __( 'Parent Series:', VEV_Events::TEXTDOMAIN ),
					'edit_item'         => __( 'Edit Series', VEV_Events::TEXTDOMAIN ),
					'update_item'       => __( 'Update Series', VEV_Events::TEXTDOMAIN ),
					'add_new_item'      => __( 'Add New Series', VEV_Events::TEXTDOMAIN ),
					'new_item_name'     => __( 'New Series Name', VEV_Events::TEXTDOMAIN ),
					'menu_name'         => __( 'Series', VEV_Events::TEXTDOMAIN ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-series', 'with_front' => false ),
			)
		);
	}

	private static function register_meta(): void {
		$args = array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => static function () {
				return current_user_can( 'edit_posts' );
			},
		);

		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_START_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_END_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_ALL_DAY, array_merge( $args, array( 'type' => 'boolean' ) ) );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_HIDE_END, array_merge( $args, array( 'type' => 'boolean' ) ) );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_SPEAKER, $args );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_SPECIAL, $args );
		register_post_meta( VEV_Events::POST_TYPE, VEV_Events::META_INFO_URL, array_merge( $args, array( 'type' => 'string' ) ) );
	}

	public static function activate(): void {
		self::register();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
