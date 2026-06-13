<?php
/**
 * Bootstraps the query layer (query-var/pre_get_posts filters and search filters).
 *
 * @package VE_Events
 */

namespace VEV\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires up the query and search filter sub-modules.
 */
final class Bootstrap {

	/**
	 * Initialize the query layer.
	 */
	public static function init(): void {
		QueryFilters::init();
		SearchFilters::init();
	}
}
