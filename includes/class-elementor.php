<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VEV_Elementor {

	public static function init(): void {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/loaded', array( __CLASS__, 'register_hooks' ) );
		} else {
			self::register_hooks();
		}
	}

	public static function register_hooks(): void {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_dynamic_tags' ) );
	}

	public static function register_dynamic_tags( $dynamic_tags_manager ): void {
		require_once __DIR__ . '/elementor/class-dynamic-tag-base.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-event-field.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-event-url.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-location.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-location-url.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-category.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-series.php';
		require_once __DIR__ . '/elementor/class-dynamic-tag-topic.php';

		$dynamic_tags_manager->register_group(
			've-events',
			array(
				'title' => __( 'VE Events', 've-events' ),
			)
		);

		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Event_Field() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Event_URL() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Location() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Location_URL() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Category() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Series() );
		$dynamic_tags_manager->register( new VEV_Elementor_Dynamic_Tag_Topic() );
	}
}
