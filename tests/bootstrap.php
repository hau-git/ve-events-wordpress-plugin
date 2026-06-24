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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $string Raw string.
	 */
	function wp_strip_all_tags( $string ): string {
		return trim( wp_strip_tags( (string) $string ) );
	}
}

if ( ! function_exists( 'wp_strip_tags' ) ) {
	/**
	 * @param string $string Raw string.
	 */
	function wp_strip_tags( string $string ): string {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $str Raw string.
	 */
	function sanitize_text_field( $str ): string {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $str ) ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * @param string $string Raw HTML.
	 */
	function wp_kses_post( $string ): string {
		return (string) $string;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL to parse.
	 * @param int    $component Component to return (defaults to all).
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url Raw URL.
	 */
	function esc_url_raw( $url ): string {
		return trim( (string) $url );
	}
}

require_once dirname( __DIR__ ) . '/src/Autoloader.php';
\VEV\Autoloader::register();
