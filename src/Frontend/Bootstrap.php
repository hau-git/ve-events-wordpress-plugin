<?php
/**
 * Wires up the frontend output and REST integrations for VE Events.
 *
 * @package VE_Events
 */

namespace VEV\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers each frontend component on its original hook and priority.
 */
final class Bootstrap {

	/**
	 * Initialize every frontend component.
	 */
	public static function init(): void {
		ComputedMetaFilter::init();
		OpenGraph::init();
		CategoryStyles::init();
		SchemaOutput::init();
		RestFields::init();
	}
}
