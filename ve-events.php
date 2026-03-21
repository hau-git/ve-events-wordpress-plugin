<?php
/**
 * Plugin Name: VE Events
 * Description: Adds a lightweight Events post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.
 * Version: 1.9.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
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
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Events {

        public const VERSION = '1.9.0';

        public const TEXTDOMAIN = 've-events';
        public const POST_TYPE  = 've_event';

        public const TAX_CATEGORY = 've_event_category';
        public const TAX_LOCATION = 've_event_location';
        public const TAX_TOPIC    = 've_event_topic';
        public const TAX_SERIES   = 've_event_series';

        // Term meta keys
        public const TERM_META_LOCATION_ADDRESS  = 've_location_address';
        public const TERM_META_LOCATION_MAPS_URL = 've_location_maps_url';
        public const TERM_META_CATEGORY_COLOR    = 've_category_color';

        public const META_START_UTC     = '_vev_start_utc';
        public const META_END_UTC       = '_vev_end_utc';
        public const META_ALL_DAY       = '_vev_all_day';
        public const META_HIDE_END      = '_vev_hide_end';
        public const META_SPEAKER       = '_vev_speaker';
        public const META_SPECIAL       = '_vev_special_info';
        public const META_INFO_URL      = '_vev_info_url';
        public const META_EVENT_STATUS  = '_vev_event_status';   // cancelled|postponed|rescheduled|movedOnline|''

        // Computed/stored date meta (auto-synced from META_START_UTC)
        public const META_START_HOUR    = '_vev_start_hour';     // 0–23, local timezone
        public const META_START_WEEKDAY = '_vev_weekday';        // 1=Mon … 7=Sun (ISO)
        public const META_START_DATE    = '_vev_start_date';     // Y-m-d, local timezone (JetEngine date range filter)
        public const META_START_MONTH   = '_vev_start_month';    // 1–12, local timezone (JetEngine month filter)
        public const META_TIME_SLOT     = '_vev_time_slot';      // morning|afternoon|evening|night (JetEngine time-of-day filter)

        // Virtual meta keys (ve_ prefix) - computed at runtime
        public const VIRTUAL_START_DATE    = 've_start_date';
        public const VIRTUAL_START_TIME    = 've_start_time';
        public const VIRTUAL_END_DATE      = 've_end_date';
        public const VIRTUAL_END_TIME      = 've_end_time';
        public const VIRTUAL_DATE_RANGE    = 've_date_range';
        public const VIRTUAL_TIME_RANGE    = 've_time_range';
        public const VIRTUAL_DATETIME      = 've_datetime_formatted';
        public const VIRTUAL_STATUS        = 've_status';
        public const VIRTUAL_IS_UPCOMING   = 've_is_upcoming';
        public const VIRTUAL_IS_ONGOING    = 've_is_ongoing';
        public const VIRTUAL_STATUS_LABEL  = 've_event_status_label';
        public const VIRTUAL_STATUS_COLOR  = 've_event_status_color';
        public const VIRTUAL_IS_CANCELLED  = 've_is_cancelled';

        public const QV_SCOPE            = 'vev_event_scope';
        public const QV_INCLUDE_ARCHIVED = 'vev_include_archived';
        public const QV_DATE_FROM        = 'vev_date_from';   // Y-m-d or UTC timestamp
        public const QV_DATE_TO          = 'vev_date_to';     // Y-m-d or UTC timestamp
        public const QV_MONTH            = 'vev_month';       // YYYY-MM
        public const QV_TIME_FROM        = 'vev_time_from';   // 0–23
        public const QV_TIME_TO          = 'vev_time_to';     // 0–23
        public const QV_WEEKDAY          = 'vev_weekday';     // 1–7 or comma-separated "1,3,5"

        public const OPTION_SETTINGS = 'vev_settings';

        private static bool  $loaded   = false;
        private static ?array $settings = null;

        public static function init(): void {
                if ( self::$loaded ) {
                        return;
                }
                self::$loaded = true;

                self::load_dependencies();

                add_action( 'init', array( __CLASS__, 'load_textdomain' ), 0 );
                add_action( 'init', array( __CLASS__, 'init_components' ), 1 );

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
                require_once $includes_dir . 'class-fields.php';
                require_once $includes_dir . 'class-jetengine.php';
                require_once $includes_dir . 'class-elementor.php';

                // Import module (admin + cron only)
                if ( is_admin() || defined( 'DOING_CRON' ) ) {
                        require_once $includes_dir . 'import/class-import-manager.php';
                }
        }

        public static function init_components(): void {
                VEV_Post_Type::init();
                VEV_Query::init();
                VEV_Frontend::init();
                VEV_Fields::init();
                VEV_JetEngine::init();
                VEV_Elementor::init();

                if ( is_admin() ) {
                        VEV_Admin::init();
                }

                // Import module
                if ( is_admin() || defined( 'DOING_CRON' ) ) {
                        VEV_Import_Manager::init();
                }
        }

        public static function get_settings(): array {
                if ( null !== self::$settings ) {
                        return self::$settings;
                }
                $defaults = array(
                        'disable_gutenberg'         => false,
                        'hide_end_same_day'         => true,
                        'grace_period'              => 1,
                        'hide_archived_search'      => true,
                        'include_series_schema'     => true,
                        'slug_single'               => 'event',
                        'slug_archive'              => 'events',
                        'series_suggestions'        => false,
                        'output_category_colors'    => true,
                        'og_tags'                   => 'auto',  // 'auto'|'always'|'disabled'
                );
                self::$settings = wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), $defaults );
                return self::$settings;
        }

        /** Clears the settings cache (called after option is saved). */
        public static function flush_settings_cache(): void {
                self::$settings = null;
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
                // Import module needs admin loaded for DB table creation
                if ( ! class_exists( 'VEV_Import_Manager' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/import/class-import-manager.php';
                }
                VEV_Import_Manager::on_activate();
        }

        public static function deactivate(): void {
                VEV_Post_Type::deactivate();
                if ( ! class_exists( 'VEV_Import_Manager' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/import/class-import-manager.php';
                }
                VEV_Import_Manager::on_deactivate();
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
