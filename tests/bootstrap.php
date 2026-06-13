<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines just enough of the WordPress runtime (constants + a handful of
 * function stubs) to exercise the pure-logic Support classes in isolation,
 * then registers the plugin's own autoloader. No full WP install required.
 *
 * @package VE_Events
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (ignored in tests).
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): \DateTimeZone {
		return new \DateTimeZone( 'UTC' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		switch ( $name ) {
			case 'date_format':
				return 'Y-m-d';
			case 'time_format':
				return 'H:i';
			default:
				return $default;
		}
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * @param string             $format    Date format.
	 * @param int                $timestamp UTC timestamp.
	 * @param \DateTimeZone|null $timezone Timezone.
	 */
	function wp_date( string $format, int $timestamp, ?\DateTimeZone $timezone = null ): string {
		$tz = $timezone ?? new \DateTimeZone( 'UTC' );
		$dt = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz );
		return $dt->format( $format );
	}
}

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\VEV\Autoloader::register();
