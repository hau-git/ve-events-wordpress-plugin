<?php
/**
 * Plugin lifecycle: bootstrapping, component wiring, activation/deactivation,
 * and debug logging.
 *
 * @package VE_Events
 */

namespace VEV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central bootstrap for the VE Events plugin.
 */
final class Plugin {

	/**
	 * Whether components have already been bootstrapped.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private static string $plugin_file = '';

	/**
	 * Bootstrap the plugin.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public static function init( string $plugin_file ): void {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded      = true;
		self::$plugin_file = $plugin_file;

		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 0 );
		add_action( 'init', array( __CLASS__, 'init_components' ), 1 );

		register_activation_hook( $plugin_file, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Instantiate and wire all runtime components.
	 */
	public static function init_components(): void {
		PostType::init();
		ComputedMeta::init();
		Fields\Registry::init();
		Query\Bootstrap::init();
		Frontend\Bootstrap::init();
		Integrations\Bootstrap::init();
		Export\Endpoint::init();

		if ( is_admin() ) {
			Admin\Bootstrap::init();
		}

		if ( is_admin() || defined( 'DOING_CRON' ) ) {
			Import\Manager::init();
		}
	}

	/**
	 * Load the plugin text domain.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain(
			Constants::TEXTDOMAIN,
			false,
			dirname( plugin_basename( self::$plugin_file ) ) . '/languages'
		);
	}

	/**
	 * Activation handler.
	 */
	public static function activate(): void {
		PostType::activate();
		Import\Manager::on_activate();
	}

	/**
	 * Deactivation handler.
	 */
	public static function deactivate(): void {
		PostType::deactivate();
		Import\Manager::on_deactivate();
	}

	/**
	 * Append a line to the plugin debug log when debugging is enabled.
	 *
	 * @param string $message Message to log.
	 */
	public static function log( string $message ): void {
		$enabled = ( defined( 'VEV_DEBUG' ) && VEV_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		if ( ! $enabled ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		$file = trailingslashit( $uploads['basedir'] ) . 've-events.log';

		$line = sprintf(
			"[%s] %s\n",
			gmdate( 'c' ),
			$message
		);

		@file_put_contents( $file, $line, FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
