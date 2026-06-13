<?php
/**
 * Lightweight PSR-4 style autoloader for the VEV namespace.
 *
 * A custom autoloader is used instead of Composer's vendor/autoload.php so the
 * plugin ships with zero runtime dependencies and cannot collide with another
 * plugin's bundled Composer autoloader. Composer is used for dev tooling only.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps VEV\ class names to files under src/.
 */
final class Autoloader {

	private const PREFIX = 'VEV\\';

	/**
	 * Whether the autoloader has already been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the autoloader. Safe to call multiple times.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Map a fully-qualified class name in the VEV\ namespace to a file in src/.
	 *
	 * Example: VEV\Admin\TermMeta\LocationTermMeta → src/Admin/TermMeta/LocationTermMeta.php
	 *
	 * @param string $class_name Fully-qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strncmp( self::PREFIX, $class_name, strlen( self::PREFIX ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
