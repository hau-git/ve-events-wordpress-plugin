<?php
/**
 * Plugin Name: VE Events
 * Description: Adds a lightweight Events post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.
 * Version: 2.4.0
 * Requires at least: 6.4
 * Requires PHP: 8.3
 * Author: Marc Probst
 * Author URI: https://github.com/hau-git
 * Plugin URI: https://github.com/hau-git/ve-events-wordpress-plugin
 * Update URI: https://github.com/hau-git/ve-events-wordpress-plugin
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ve-events
 * Domain Path: /languages
 * Elementor tested up to: 3.35
 * Elementor Pro tested up to: 3.35
 *
 * @package VE_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';
\VEV\Autoloader::register();

// Legacy global class shims (VEV_Events, VEV_Fields, VEV_Frontend, VEV_Post_Type).
require_once __DIR__ . '/src/Compat.php';

\VEV\Plugin::init( __FILE__ );
\VEV\Updater\GitHubUpdater::init( __FILE__ );
