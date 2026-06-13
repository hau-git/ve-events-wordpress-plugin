<?php
/**
 * Elementor integration for VE Events.
 *
 * Registers the VE Events dynamic tag group and its dynamic tags with the
 * Elementor dynamic tags manager.
 *
 * @package VE_Events
 */

namespace VEV\Integrations\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use VEV\Integrations\Elementor\Tags\CategoryTag;
use VEV\Integrations\Elementor\Tags\EventFieldTag;
use VEV\Integrations\Elementor\Tags\EventUrlTag;
use VEV\Integrations\Elementor\Tags\LocationTag;
use VEV\Integrations\Elementor\Tags\LocationUrlTag;
use VEV\Integrations\Elementor\Tags\SeriesTag;
use VEV\Integrations\Elementor\Tags\TopicTag;

/**
 * Hooks the VE Events dynamic tags into Elementor.
 */
final class Bootstrap {

	/**
	 * Initialize the Elementor integration.
	 */
	public static function init(): void {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/loaded', array( __CLASS__, 'register_hooks' ) );
		} else {
			self::register_hooks();
		}
	}

	/**
	 * Register the dynamic tags hook once Elementor is loaded.
	 */
	public static function register_hooks(): void {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_dynamic_tags' ) );
	}

	/**
	 * Register the VE Events dynamic tag group and tags.
	 *
	 * @param mixed $dynamic_tags_manager Elementor dynamic tags manager.
	 */
	public static function register_dynamic_tags( $dynamic_tags_manager ): void {
		$dynamic_tags_manager->register_group(
			've-events',
			array(
				'title' => __( 'VE Events', 've-events' ),
			)
		);

		$dynamic_tags_manager->register( new EventFieldTag() );
		$dynamic_tags_manager->register( new EventUrlTag() );
		$dynamic_tags_manager->register( new LocationTag() );
		$dynamic_tags_manager->register( new LocationUrlTag() );
		$dynamic_tags_manager->register( new CategoryTag() );
		$dynamic_tags_manager->register( new SeriesTag() );
		$dynamic_tags_manager->register( new TopicTag() );
	}
}
