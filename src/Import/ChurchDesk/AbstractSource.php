<?php
/**
 * Shared HTTP plumbing for ChurchDesk sources.
 *
 * @package VE_Events
 */

namespace VEV\Import\ChurchDesk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for ChurchDesk sources: holds the feed config and provides a
 * JSON GET helper built on the WordPress HTTP API.
 */
abstract class AbstractSource implements SourceInterface {

	/**
	 * Maximum number of pages fetched in a single run (endless-loop guard).
	 */
	const MAX_PAGES = 25;

	/**
	 * Number of items requested per page.
	 */
	const PAGE_SIZE = 100;

	/**
	 * Feed configuration.
	 *
	 * @var array
	 */
	protected array $config;

	/**
	 * Stores the feed config for the source.
	 *
	 * @param array $config Full feed config.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Performs a GET request and returns the decoded JSON body as an array.
	 *
	 * @param  string $url Fully-qualified request URL (including query string).
	 * @return array       Decoded JSON.
	 * @throws \RuntimeException On transport error or non-2xx status.
	 */
	protected function get_json( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => (int) ( $this->config['http_timeout'] ?? 30 ),
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
				$message = (string) $decoded['message'];
			}
			throw new \RuntimeException(
				esc_html(
					sprintf(
						/* translators: 1: HTTP status code, 2: API error message. */
						__( 'ChurchDesk API error (HTTP %1$d): %2$s', 've-events' ),
						$code,
						'' !== $message ? $message : __( 'Unexpected response.', 've-events' )
					)
				)
			);
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException( esc_html__( 'ChurchDesk API returned invalid JSON.', 've-events' ) );
		}

		return $decoded;
	}

	/**
	 * Builds a small preview array from the first few canonical events.
	 *
	 * @param  array[] $events Canonical events.
	 * @return array{ok:bool,count:int,sample:array,error:string}
	 */
	protected function preview( array $events ): array {
		$sample = array();
		foreach ( array_slice( $events, 0, 5 ) as $event ) {
			$sample[] = array(
				'summary' => (string) ( $event['title'] ?? '(no title)' ),
				'dtstart' => (string) ( $event['startDate'] ?? '' ),
				'uid'     => (string) ( $event['id'] ?? '' ),
			);
		}

		return array(
			'ok'     => true,
			'count'  => count( $events ),
			'sample' => $sample,
			'error'  => '',
		);
	}
}
