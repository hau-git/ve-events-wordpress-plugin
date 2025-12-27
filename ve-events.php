<?php
/**
 * Plugin Name: VE Events
 * Description: Adds a lightweight Events post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.
 * Version: 1.3.1
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Marc Probst
 * Author URI: https://github.com/hau-git
 * Plugin URI: https://github.com/hau-git/ve-events-wordpress-plugin
 * Update URI: https://github.com/hau-git/ve-events-wordpress-plugin
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ve-events
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Events {

        public const VERSION = '1.3.1';

        public const TEXTDOMAIN = 've-events';
        public const POST_TYPE  = 've_event';

        public const TAX_CATEGORY = 've_event_category';
        public const TAX_LOCATION = 've_event_location';
        public const TAX_TOPIC    = 've_event_topic';
        public const TAX_SERIES   = 've_event_series';

        public const META_START_UTC   = '_vev_start_utc';
        public const META_END_UTC     = '_vev_end_utc';
        public const META_ALL_DAY     = '_vev_all_day';
        public const META_HIDE_END    = '_vev_hide_end';
        public const META_SPEAKER     = '_vev_speaker';
        public const META_SPECIAL     = '_vev_special_info';
        public const META_INFO_URL    = '_vev_info_url';

        public const VIRTUAL_STATUS        = 'vev_status';
        public const VIRTUAL_STATUS_LABEL  = 'vev_status_label';
        public const VIRTUAL_IS_LIVE       = 'vev_is_live';
        public const VIRTUAL_IS_PAST       = 'vev_is_past';
        public const VIRTUAL_TIMERANGE     = 'vev_timerange';
        public const VIRTUAL_START_LOCAL   = 'vev_start_local';
        public const VIRTUAL_END_LOCAL     = 'vev_end_local';

        public const QV_SCOPE            = 'vev_event_scope';
        public const QV_INCLUDE_ARCHIVED = 'vev_include_archived';

        public const OPTION_SETTINGS = 'vev_settings';

        private static bool $loaded = false;

        public static function init(): void {
                if ( self::$loaded ) {
                        return;
                }
                self::$loaded = true;

                self::load_dependencies();

                add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );

                VEV_Post_Type::init();
                VEV_Query::init();
                VEV_Frontend::init();

                if ( is_admin() ) {
                        VEV_Admin::init();
                }

                register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
                register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
        }

        private static function load_dependencies(): void {
                $includes_dir = plugin_dir_path( __FILE__ ) . 'includes/';

                require_once $includes_dir . 'class-post-type.php';
                require_once $includes_dir . 'class-admin.php';
                require_once $includes_dir . 'class-frontend.php';
                require_once $includes_dir . 'class-query.php';
                require_once $includes_dir . 'class-github-updater.php';
        }

        public static function get_settings(): array {
                $defaults = array(
                        'disable_gutenberg'     => false,
                        'hide_end_same_day'     => true,
                        'grace_period'          => 1,
                        'hide_archived_search'  => true,
                        'include_series_schema' => true,
                        'slug_single'           => 'event',
                        'slug_archive'          => 'events',
                );
                $settings = get_option( self::OPTION_SETTINGS, array() );
                return wp_parse_args( $settings, $defaults );
        }

        public static function load_textdomain(): void {
                load_plugin_textdomain(
                        self::TEXTDOMAIN,
                        false,
                        dirname( plugin_basename( __FILE__ ) ) . '/languages'
                );
        }

        public static function activate(): void {
                VEV_Post_Type::activate();
        }

        public static function deactivate(): void {
                VEV_Post_Type::deactivate();
        }

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

                @file_put_contents( $file, $line, FILE_APPEND );
        }
}

VEV_Events::init();
VEV_GitHub_Updater::init( __FILE__ );
