<?php
/**
 * Import Runner — backward-compatibility alias for {@see IcsRunner}.
 *
 * The original monolithic runner was split into {@see AbstractRunner} (the
 * source-agnostic engine) and {@see IcsRunner} (the ICS source). This thin
 * subclass keeps the historical `VEV\Import\Runner` class name resolvable for
 * any code that referenced it directly.
 *
 * @package VE_Events
 */

namespace VEV\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alias of {@see IcsRunner}, retained for backward compatibility.
 */
class Runner extends IcsRunner {
}
