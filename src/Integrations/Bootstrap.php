<?php
/**
 * Bootstraps the third-party integrations for VE Events.
 *
 * @package VE_Events
 */

namespace VEV\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires up each integration (JetEngine, Elementor) on its original hooks.
 */
final class Bootstrap {

	/**
	 * Initialize every integration component.
	 */
	public static function init(): void {
		JetEngine::init();
		Elementor\Bootstrap::init();
	}
}
