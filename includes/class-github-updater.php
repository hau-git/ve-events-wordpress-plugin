<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VEV_GitHub_Updater {

	private const GITHUB_USER = 'hau-git';
	private const GITHUB_REPO = 've-events-wordpress-plugin';
	private const PLUGIN_SLUG = 've-events';
	private const CACHE_KEY   = 'vev_github_update_check';
	private const CACHE_TIME  = 43200;

	private static string $plugin_file = '';

	public static function init( string $plugin_file ): void {
		self::$plugin_file = $plugin_file;

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'after_install' ), 10, 3 );
	}

	private static function get_plugin_basename(): string {
		return plugin_basename( self::$plugin_file );
	}

	private static function fetch_github_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array(), self::CACHE_TIME );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, array(), self::CACHE_TIME );
			return null;
		}

		$version = ltrim( $data['tag_name'], 'vV' );

		$download_url = '';
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( str_ends_with( $asset['name'], '.zip' ) ) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( empty( $download_url ) ) {
			$download_url = sprintf(
				'https://github.com/%s/%s/archive/refs/tags/%s.zip',
				self::GITHUB_USER,
				self::GITHUB_REPO,
				$data['tag_name']
			);
		}

		$result = array(
			'version'      => $version,
			'download_url' => $download_url,
			'changelog'    => $data['body'] ?? '',
			'published'    => $data['published_at'] ?? '',
			'html_url'     => $data['html_url'] ?? '',
		);

		set_transient( self::CACHE_KEY, $result, self::CACHE_TIME );

		return $result;
	}

	public static function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::fetch_github_release();
		if ( ! $release ) {
			return $transient;
		}

		$plugin_basename = self::get_plugin_basename();
		$current_version = $transient->checked[ $plugin_basename ] ?? VEV_Events::VERSION;

		if ( version_compare( $release['version'], $current_version, '>' ) ) {
			$transient->response[ $plugin_basename ] = (object) array(
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => $plugin_basename,
				'new_version' => $release['version'],
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => $release['download_url'],
				'icons'       => array(),
				'banners'     => array(),
				'tested'      => '',
				'requires'    => '6.2',
				'requires_php'=> '8.0',
			);
		} else {
			$transient->no_update[ $plugin_basename ] = (object) array(
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => $plugin_basename,
				'new_version' => $current_version,
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => '',
			);
		}

		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( self::PLUGIN_SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = self::fetch_github_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'              => 'VE Events',
			'slug'              => self::PLUGIN_SLUG,
			'version'           => $release['version'],
			'author'            => '<a href="https://github.com/hau-git">Marc Probst</a>',
			'author_profile'    => 'https://github.com/hau-git',
			'homepage'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'short_description' => 'Lightweight Events post type with Schema.org markup and JetEngine/Elementor support.',
			'sections'          => array(
				'description' => 'VE Events adds a lightweight Events custom post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.',
				'changelog'   => nl2br( esc_html( $release['changelog'] ) ),
			),
			'download_link'     => $release['download_url'],
			'requires'          => '6.2',
			'tested'            => '',
			'requires_php'      => '8.0',
			'last_updated'      => $release['published'],
			'banners'           => array(),
		);
	}

	public static function after_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || self::get_plugin_basename() !== $hook_extra['plugin'] ) {
			return $result;
		}

		global $wp_filesystem;

		$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( self::get_plugin_basename() );

		if ( ! empty( $result['destination'] ) && $result['destination'] !== $plugin_dir ) {
			$wp_filesystem->move( $result['destination'], $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		delete_transient( self::CACHE_KEY );

		if ( is_plugin_active( self::get_plugin_basename() ) ) {
			activate_plugin( self::get_plugin_basename() );
		}

		return $result;
	}
}
