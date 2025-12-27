<?php
/**
 * Plugin Name: VE Events
 * Description: Adds a lightweight Events post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.
 * Version: 1.2.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Marc Probst
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ve-events
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Events {

        public const VERSION = '1.2.0';

        // Core identifiers.
        public const TEXTDOMAIN = 've-events';
        public const POST_TYPE  = 've_event';

        // Taxonomies.
        public const TAX_CATEGORY = 've_event_category';
        public const TAX_LOCATION = 've_event_location';
        public const TAX_TOPIC    = 've_event_topic';
        public const TAX_SERIES   = 've_event_series';

        // Stored meta (UTC timestamps).
        public const META_START_UTC   = '_vev_start_utc';
        public const META_END_UTC     = '_vev_end_utc';
        public const META_ALL_DAY     = '_vev_all_day';
        public const META_HIDE_END    = '_vev_hide_end';
        public const META_SPEAKER     = '_vev_speaker';
        public const META_SPECIAL     = '_vev_special_info';
        public const META_INFO_URL    = '_vev_info_url';

        // Virtual/computed "meta" keys (not stored; computed at runtime via get_post_meta()).
        public const VIRTUAL_STATUS        = 'vev_status';
        public const VIRTUAL_STATUS_LABEL  = 'vev_status_label';
        public const VIRTUAL_IS_LIVE       = 'vev_is_live';
        public const VIRTUAL_IS_PAST       = 'vev_is_past';
        public const VIRTUAL_TIMERANGE     = 'vev_timerange';
        public const VIRTUAL_START_LOCAL   = 'vev_start_local';
        public const VIRTUAL_END_LOCAL     = 'vev_end_local';

        // Query vars for convenience scopes.
        public const QV_SCOPE            = 'vev_event_scope';
        public const QV_INCLUDE_ARCHIVED = 'vev_include_archived';

        // Settings option name
        public const OPTION_SETTINGS = 'vev_settings';

        /**
         * Boot.
         */
        public static function init(): void {
                add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
                add_action( 'init', array( __CLASS__, 'register' ) );
                add_action( 'rest_api_init', array( __CLASS__, 'register_rest_fields' ) );

                add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
                add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

                add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
                add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
                add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'admin_sortable_columns' ) );

                add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
                add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );

                add_filter( 'display_post_states', array( __CLASS__, 'display_post_states' ), 10, 2 );

                add_action( 'wp_head', array( __CLASS__, 'output_schema' ), 99 );

                // Virtual meta keys (for JetEngine/Elementor listings without shortcodes).
                add_filter( 'get_post_metadata', array( __CLASS__, 'computed_meta' ), 10, 4 );

                // Extend search to include selected event meta fields and event taxonomy terms.
                add_filter( 'posts_join', array( __CLASS__, 'search_join' ), 10, 2 );
                add_filter( 'posts_where', array( __CLASS__, 'search_where' ), 10, 2 );
                add_filter( 'posts_search', array( __CLASS__, 'extend_search' ), 10, 2 );
                add_filter( 'posts_distinct', array( __CLASS__, 'search_distinct' ), 10, 2 );

                // Settings page (admin only)
                add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
                add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

                // Disable Gutenberg for events if setting is enabled
                add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'maybe_disable_gutenberg' ), 10, 2 );

                register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
                register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
        }

        /**
         * Get plugin settings.
         */
        public static function get_settings(): array {
                $defaults = array(
                        'disable_gutenberg'     => false,
                        'hide_end_same_day'     => true,
                        'grace_period'          => 1,
                        'hide_archived_search'  => true,
                        'include_series_schema' => true,
                );
                $settings = get_option( self::OPTION_SETTINGS, array() );
                return wp_parse_args( $settings, $defaults );
        }

        /**
         * Maybe disable Gutenberg for events.
         */
        public static function maybe_disable_gutenberg( bool $use_block_editor, string $post_type ): bool {
                if ( self::POST_TYPE !== $post_type ) {
                        return $use_block_editor;
                }
                $settings = self::get_settings();
                if ( ! empty( $settings['disable_gutenberg'] ) ) {
                        return false;
                }
                return $use_block_editor;
        }

        /**
         * Add settings page under Events menu.
         */
        public static function add_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                add_submenu_page(
                        'edit.php?post_type=' . self::POST_TYPE,
                        __( 'VE Events Settings', self::TEXTDOMAIN ),
                        __( 'Settings', self::TEXTDOMAIN ),
                        'manage_options',
                        'vev-settings',
                        array( __CLASS__, 'render_settings_page' )
                );
        }

        /**
         * Register settings.
         */
        public static function register_settings(): void {
                register_setting( 'vev_settings_group', self::OPTION_SETTINGS, array(
                        'type' => 'array',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                ) );
        }

        /**
         * Sanitize settings.
         */
        public static function sanitize_settings( $input ): array {
                $sanitized = array();
                $sanitized['disable_gutenberg']     = ! empty( $input['disable_gutenberg'] );
                $sanitized['hide_end_same_day']     = ! empty( $input['hide_end_same_day'] );
                $sanitized['grace_period']          = absint( $input['grace_period'] ?? 1 );
                $sanitized['hide_archived_search']  = ! empty( $input['hide_archived_search'] );
                $sanitized['include_series_schema'] = ! empty( $input['include_series_schema'] );
                return $sanitized;
        }

        /**
         * Render settings page.
         */
        public static function render_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                $settings = self::get_settings();
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'VE Events Settings', self::TEXTDOMAIN ); ?></h1>
                        <p class="description"><?php esc_html_e( 'These settings are only visible to administrators and control the global behavior of the VE Events plugin.', self::TEXTDOMAIN ); ?></p>

                        <form method="post" action="options.php">
                                <?php settings_fields( 'vev_settings_group' ); ?>

                                <h2><?php esc_html_e( 'Editor Settings', self::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Gutenberg Editor', self::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[disable_gutenberg]" value="1" <?php checked( $settings['disable_gutenberg'] ); ?> />
                                                                <?php esc_html_e( 'Disable Gutenberg block editor for Events', self::TEXTDOMAIN ); ?>
                                                        </label>
                                                        <p class="description"><?php esc_html_e( 'When enabled, events will use the classic WordPress editor instead of the block editor.', self::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Display Settings', self::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Hide End Date if Same Day', self::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[hide_end_same_day]" value="1" <?php checked( $settings['hide_end_same_day'] ); ?> />
                                                                <?php esc_html_e( 'Automatically hide the end date if start and end are on the same day', self::TEXTDOMAIN ); ?>
                                                        </label>
                                                        <p class="description">
                                                                <?php esc_html_e( 'Example: "12.06.2025 · 10:00 – 12:00" instead of "12.06.2025 · 10:00 – 12.06.2025 · 12:00"', self::TEXTDOMAIN ); ?>
                                                        </p>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Event Visibility & Status', self::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Grace Period After Event End', self::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <select name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[grace_period]">
                                                                <option value="0" <?php selected( $settings['grace_period'], 0 ); ?>><?php esc_html_e( '0 days (immediately hidden)', self::TEXTDOMAIN ); ?></option>
                                                                <option value="1" <?php selected( $settings['grace_period'], 1 ); ?>><?php esc_html_e( '1 day (recommended)', self::TEXTDOMAIN ); ?></option>
                                                                <option value="3" <?php selected( $settings['grace_period'], 3 ); ?>><?php esc_html_e( '3 days', self::TEXTDOMAIN ); ?></option>
                                                                <option value="7" <?php selected( $settings['grace_period'], 7 ); ?>><?php esc_html_e( '7 days', self::TEXTDOMAIN ); ?></option>
                                                        </select>
                                                        <p class="description"><?php esc_html_e( 'Choose how long events remain visible on the frontend after ending. Backend visibility is never affected.', self::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Hide Archived Events from Search', self::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[hide_archived_search]" value="1" <?php checked( $settings['hide_archived_search'] ); ?> />
                                                                <?php esc_html_e( 'Exclude archived events from WordPress search results', self::TEXTDOMAIN ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Schema.org Settings', self::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Include Event Series in Schema', self::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[include_series_schema]" value="1" <?php checked( $settings['include_series_schema'] ); ?> />
                                                                <?php esc_html_e( 'Add series name to Schema as eventSeries or superEvent', self::TEXTDOMAIN ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                </table>

                                <?php submit_button(); ?>
                        </form>

                        <hr />

                        <h2><?php esc_html_e( 'Event Status Logic', self::TEXTDOMAIN ); ?></h2>
                        <div class="vev-docs" style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:15px;max-width:800px;">
                                <p><?php esc_html_e( 'Event status is calculated dynamically based on the current time:', self::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:500px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Status', self::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', self::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>upcoming</code></td><td><?php esc_html_e( 'Event has not started yet', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ongoing</code></td><td><?php esc_html_e( 'Event is currently running', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>past</code></td><td><?php esc_html_e( 'Event has ended', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>archived</code></td><td><?php esc_html_e( 'Event ended more than the grace period ago', self::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>
                        </div>

                        <hr />

                        <h2><?php esc_html_e( 'Documentation', self::TEXTDOMAIN ); ?></h2>

                        <div class="vev-docs" style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:15px;max-width:800px;">
                                <h3><?php esc_html_e( 'About VE Events', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'VE Events adds a lightweight Events custom post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.', self::TEXTDOMAIN ); ?></p>

                                <h3><?php esc_html_e( 'Core Principle', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'VE Events does not rely on shortcodes. All data is exposed via standard WordPress Meta Keys, Virtual (computed) Meta Keys, and Taxonomies. This ensures full compatibility with JetEngine Listings, sortable and filterable queries, and clean Elementor templates.', self::TEXTDOMAIN ); ?></p>

                                <h3><?php esc_html_e( 'Features', self::TEXTDOMAIN ); ?></h3>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Custom post type "Events" with date/time management', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Event Categories, Locations, Topics, and Series taxonomies', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Speaker/Host and special information fields', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Schema.org structured data output for SEO', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Compatible with JetEngine/Elementor listings', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Multilingual support (WPML & Polylang compatible)', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Automatic event status calculation', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Automatic hiding of past events from frontend', self::TEXTDOMAIN ); ?></li>
                                </ul>

                                <h3><?php esc_html_e( 'Stored Meta Keys (Database)', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Use these meta keys for sorting, filtering, and conditions:', self::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:600px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Meta Key', self::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', self::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>_vev_start_utc</code></td><td><?php esc_html_e( 'Event start timestamp (UTC)', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_end_utc</code></td><td><?php esc_html_e( 'Event end timestamp (UTC)', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_all_day</code></td><td><?php esc_html_e( 'All-day event (boolean)', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_hide_end</code></td><td><?php esc_html_e( 'Hide end time', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_speaker</code></td><td><?php esc_html_e( 'Speaker / Host', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_special_info</code></td><td><?php esc_html_e( 'Special information', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_info_url</code></td><td><?php esc_html_e( 'Info / Ticket URL', self::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Virtual / Computed Meta Keys', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Computed at runtime and usable like normal meta fields:', self::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:600px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Meta Key', self::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', self::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>vev_status</code></td><td><?php esc_html_e( 'upcoming / ongoing / past / archived', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_status_label</code></td><td><?php esc_html_e( 'Translated status label', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_is_live</code></td><td><?php esc_html_e( '1 if currently running', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_is_past</code></td><td><?php esc_html_e( '1 if past', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_timerange</code></td><td><?php esc_html_e( 'Fully formatted date/time range', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_start_local</code></td><td><?php esc_html_e( 'Start date/time (site timezone)', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>vev_end_local</code></td><td><?php esc_html_e( 'End date/time (site timezone)', self::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'JetEngine Query Setup (Recommended)', self::TEXTDOMAIN ); ?></h3>
                                <h4><?php esc_html_e( 'Upcoming Events', self::TEXTDOMAIN ); ?></h4>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Post Type: ve_event', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Order By: Meta Value (Number)', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Meta Key: _vev_start_utc', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Order: ASC', self::TEXTDOMAIN ); ?></li>
                                </ul>
                                <h4><?php esc_html_e( 'Past Events', self::TEXTDOMAIN ); ?></h4>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Same query as above', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Add Query Var: vev_event_scope = past', self::TEXTDOMAIN ); ?></li>
                                </ul>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Query Scopes', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Use these query vars to filter events:', self::TEXTDOMAIN ); ?></p>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><code>vev_event_scope</code>: <?php esc_html_e( 'Filter by scope (upcoming, past, live, all)', self::TEXTDOMAIN ); ?></li>
                                        <li><code>vev_include_archived</code>: <?php esc_html_e( 'Include archived/past events', self::TEXTDOMAIN ); ?></li>
                                </ul>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Taxonomies', self::TEXTDOMAIN ); ?></h3>
                                <table class="widefat" style="max-width:600px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Taxonomy', self::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Slug', self::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Type', self::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><?php esc_html_e( 'Event Categories', self::TEXTDOMAIN ); ?></td><td><code>ve_event_category</code></td><td><?php esc_html_e( 'Hierarchical', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><?php esc_html_e( 'Locations', self::TEXTDOMAIN ); ?></td><td><code>ve_event_location</code></td><td><?php esc_html_e( 'Non-hierarchical', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><?php esc_html_e( 'Topics', self::TEXTDOMAIN ); ?></td><td><code>ve_event_topic</code></td><td><?php esc_html_e( 'Non-hierarchical', self::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><?php esc_html_e( 'Series', self::TEXTDOMAIN ); ?></td><td><code>ve_event_series</code></td><td><?php esc_html_e( 'Hierarchical', self::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Multilingual Support', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'VE Events is fully compatible with WPML and Polylang. Included:', self::TEXTDOMAIN ); ?></p>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'English default strings', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'German translation (de_DE)', self::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'wpml-config.xml', self::TEXTDOMAIN ); ?></li>
                                </ul>
                                <p><?php esc_html_e( 'Translation behavior: Dates/times are copied, Speaker/Info fields are translatable, Taxonomies are translatable.', self::TEXTDOMAIN ); ?></p>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Debugging', self::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Enable debugging by adding to wp-config.php:', self::TEXTDOMAIN ); ?></p>
                                <pre style="background:#f6f7f7;padding:10px;border:1px solid #dcdcde;">define('VEV_DEBUG', true);</pre>
                                <p><?php printf( esc_html__( 'Log file: %s', self::TEXTDOMAIN ), '<code>wp-content/uploads/ve-events.log</code>' ); ?></p>
                                <p><?php esc_html_e( 'Logs include: Status calculations, Query modifications, Schema generation', self::TEXTDOMAIN ); ?></p>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Version Information', self::TEXTDOMAIN ); ?></h3>
                                <p>
                                        <?php printf( esc_html__( 'VE Events Version: %s', self::TEXTDOMAIN ), '<strong>' . esc_html( self::VERSION ) . '</strong>' ); ?><br>
                                        <?php esc_html_e( 'Author: Marc Probst', self::TEXTDOMAIN ); ?><br>
                                        <?php esc_html_e( 'License: GPL-2.0+', self::TEXTDOMAIN ); ?>
                                </p>
                        </div>
                </div>
                <?php
        }

        public static function activate(): void {
                // Ensure CPT/taxonomies exist before flushing rules.
                self::register();
                flush_rewrite_rules();
        }

        public static function deactivate(): void {
                flush_rewrite_rules();
        }

        public static function load_textdomain(): void {
                load_plugin_textdomain(
                        self::TEXTDOMAIN,
                        false,
                        dirname( plugin_basename( __FILE__ ) ) . '/languages'
                );
        }

        public static function register_query_vars( array $vars ): array {
                $vars[] = self::QV_SCOPE;
                $vars[] = self::QV_INCLUDE_ARCHIVED;
                return $vars;
        }

        /**
         * Register CPT, taxonomies and meta.
         */
        public static function register(): void {
                self::register_post_type();
                self::register_taxonomies();
                self::register_meta();
        }

        private static function register_post_type(): void {
                $slug = apply_filters( 'vev_events_post_type_slug', 'events' );

                $labels = array(
                        'name'                  => __( 'Events', self::TEXTDOMAIN ),
                        'singular_name'         => __( 'Event', self::TEXTDOMAIN ),
                        'menu_name'             => __( 'Events', self::TEXTDOMAIN ),
                        'name_admin_bar'        => __( 'Event', self::TEXTDOMAIN ),
                        'add_new'               => __( 'Add New', self::TEXTDOMAIN ),
                        'add_new_item'          => __( 'Add New Event', self::TEXTDOMAIN ),
                        'new_item'              => __( 'New Event', self::TEXTDOMAIN ),
                        'edit_item'             => __( 'Edit Event', self::TEXTDOMAIN ),
                        'view_item'             => __( 'View Event', self::TEXTDOMAIN ),
                        'all_items'             => __( 'All Events', self::TEXTDOMAIN ),
                        'search_items'          => __( 'Search Events', self::TEXTDOMAIN ),
                        'parent_item_colon'     => __( 'Parent Events:', self::TEXTDOMAIN ),
                        'not_found'             => __( 'No events found.', self::TEXTDOMAIN ),
                        'not_found_in_trash'    => __( 'No events found in Trash.', self::TEXTDOMAIN ),
                        'featured_image'        => __( 'Featured image', self::TEXTDOMAIN ),
                        'set_featured_image'    => __( 'Set featured image', self::TEXTDOMAIN ),
                        'remove_featured_image' => __( 'Remove featured image', self::TEXTDOMAIN ),
                        'use_featured_image'    => __( 'Use as featured image', self::TEXTDOMAIN ),
                );

                $args = array(
                        'labels'             => $labels,
                        'public'             => true,
                        'show_in_rest'       => true,
                        'menu_position'      => 20,
                        'menu_icon'          => 'dashicons-calendar-alt',
                        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
                        'has_archive'        => true,
                        'rewrite'            => array( 'slug' => $slug, 'with_front' => false ),
                        'capability_type'    => 'post',
                        'map_meta_cap'       => true,
                );

                register_post_type( self::POST_TYPE, $args );
        }

        private static function register_taxonomies(): void {

                // Category (hierarchical).
                register_taxonomy(
                        self::TAX_CATEGORY,
                        array( self::POST_TYPE ),
                        array(
                                'labels'            => array(
                                        'name'              => __( 'Event Categories', self::TEXTDOMAIN ),
                                        'singular_name'     => __( 'Event Category', self::TEXTDOMAIN ),
                                        'search_items'      => __( 'Search Event Categories', self::TEXTDOMAIN ),
                                        'all_items'         => __( 'All Event Categories', self::TEXTDOMAIN ),
                                        'parent_item'       => __( 'Parent Event Category', self::TEXTDOMAIN ),
                                        'parent_item_colon' => __( 'Parent Event Category:', self::TEXTDOMAIN ),
                                        'edit_item'         => __( 'Edit Event Category', self::TEXTDOMAIN ),
                                        'update_item'       => __( 'Update Event Category', self::TEXTDOMAIN ),
                                        'add_new_item'      => __( 'Add New Event Category', self::TEXTDOMAIN ),
                                        'new_item_name'     => __( 'New Event Category Name', self::TEXTDOMAIN ),
                                        'menu_name'         => __( 'Categories', self::TEXTDOMAIN ),
                                ),
                                'public'            => true,
                                'show_in_rest'      => true,
                                'hierarchical'      => true,
                                'show_admin_column' => true,
                                'rewrite'           => array( 'slug' => 'event-category', 'with_front' => false ),
                        )
                );

                // Location (non-hierarchical).
                register_taxonomy(
                        self::TAX_LOCATION,
                        array( self::POST_TYPE ),
                        array(
                                'labels'            => array(
                                        'name'          => __( 'Locations', self::TEXTDOMAIN ),
                                        'singular_name' => __( 'Location', self::TEXTDOMAIN ),
                                        'search_items'  => __( 'Search Locations', self::TEXTDOMAIN ),
                                        'all_items'     => __( 'All Locations', self::TEXTDOMAIN ),
                                        'edit_item'     => __( 'Edit Location', self::TEXTDOMAIN ),
                                        'update_item'   => __( 'Update Location', self::TEXTDOMAIN ),
                                        'add_new_item'  => __( 'Add New Location', self::TEXTDOMAIN ),
                                        'new_item_name' => __( 'New Location Name', self::TEXTDOMAIN ),
                                        'menu_name'     => __( 'Locations', self::TEXTDOMAIN ),
                                ),
                                'public'            => true,
                                'show_in_rest'      => true,
                                'hierarchical'      => false,
                                'show_admin_column' => true,
                                'rewrite'           => array( 'slug' => 'event-location', 'with_front' => false ),
                        )
                );

                // Topic (non-hierarchical, tag-like).
                register_taxonomy(
                        self::TAX_TOPIC,
                        array( self::POST_TYPE ),
                        array(
                                'labels'            => array(
                                        'name'          => __( 'Topics', self::TEXTDOMAIN ),
                                        'singular_name' => __( 'Topic', self::TEXTDOMAIN ),
                                        'search_items'  => __( 'Search Topics', self::TEXTDOMAIN ),
                                        'all_items'     => __( 'All Topics', self::TEXTDOMAIN ),
                                        'edit_item'     => __( 'Edit Topic', self::TEXTDOMAIN ),
                                        'update_item'   => __( 'Update Topic', self::TEXTDOMAIN ),
                                        'add_new_item'  => __( 'Add New Topic', self::TEXTDOMAIN ),
                                        'new_item_name' => __( 'New Topic Name', self::TEXTDOMAIN ),
                                        'menu_name'     => __( 'Topics', self::TEXTDOMAIN ),
                                ),
                                'public'            => true,
                                'show_in_rest'      => true,
                                'hierarchical'      => false,
                                'show_admin_column' => true,
                                'rewrite'           => array( 'slug' => 'event-topic', 'with_front' => false ),
                        )
                );

                // Series (hierarchical, category-like).
                register_taxonomy(
                        self::TAX_SERIES,
                        array( self::POST_TYPE ),
                        array(
                                'labels'            => array(
                                        'name'              => __( 'Series', self::TEXTDOMAIN ),
                                        'singular_name'     => __( 'Series', self::TEXTDOMAIN ),
                                        'search_items'      => __( 'Search Series', self::TEXTDOMAIN ),
                                        'all_items'         => __( 'All Series', self::TEXTDOMAIN ),
                                        'parent_item'       => __( 'Parent Series', self::TEXTDOMAIN ),
                                        'parent_item_colon' => __( 'Parent Series:', self::TEXTDOMAIN ),
                                        'edit_item'         => __( 'Edit Series', self::TEXTDOMAIN ),
                                        'update_item'       => __( 'Update Series', self::TEXTDOMAIN ),
                                        'add_new_item'      => __( 'Add New Series', self::TEXTDOMAIN ),
                                        'new_item_name'     => __( 'New Series Name', self::TEXTDOMAIN ),
                                        'menu_name'         => __( 'Series', self::TEXTDOMAIN ),
                                ),
                                'public'            => true,
                                'show_in_rest'      => true,
                                'hierarchical'      => true,
                                'show_admin_column' => true,
                                'rewrite'           => array( 'slug' => 'event-series', 'with_front' => false ),
                        )
                );
        }

        private static function register_meta(): void {
                $args = array(
                        'type'              => 'string',
                        'single'            => true,
                        'show_in_rest'      => true,
                        'auth_callback'     => static function () {
                                return current_user_can( 'edit_posts' );
                        },
                );

                register_post_meta( self::POST_TYPE, self::META_START_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
                register_post_meta( self::POST_TYPE, self::META_END_UTC, array_merge( $args, array( 'type' => 'integer' ) ) );
                register_post_meta( self::POST_TYPE, self::META_ALL_DAY, array_merge( $args, array( 'type' => 'boolean' ) ) );
                register_post_meta( self::POST_TYPE, self::META_HIDE_END, array_merge( $args, array( 'type' => 'boolean' ) ) );
                register_post_meta( self::POST_TYPE, self::META_SPEAKER, $args );
                register_post_meta( self::POST_TYPE, self::META_SPECIAL, $args );
                register_post_meta( self::POST_TYPE, self::META_INFO_URL, array_merge( $args, array( 'type' => 'string' ) ) );
        }

        /**
         * Admin meta box.
         */
        public static function add_meta_boxes(): void {
                add_meta_box(
                        'vev_event_details',
                        __( 'Event Details', self::TEXTDOMAIN ),
                        array( __CLASS__, 'render_meta_box' ),
                        self::POST_TYPE,
                        'normal',
                        'high'
                );
        }

        public static function admin_assets( string $hook ): void {
                if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
                        return;
                }
                $screen = get_current_screen();
                if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
                        return;
                }

                $js = <<<JS
(function() {
  function toggleAllDay() {
    var allDay = document.getElementById('vev_all_day');
    if (!allDay) return;

    var startTime = document.getElementById('vev_start_time');
    var endTime = document.getElementById('vev_end_time');
    if (startTime) startTime.disabled = allDay.checked;
    if (endTime) endTime.disabled = allDay.checked;

    var note = document.getElementById('vev_all_day_note');
    if (note) note.style.display = allDay.checked ? 'block' : 'none';
  }

  function syncEndDateMin() {
    var startDate = document.getElementById('vev_start_date');
    var startTime = document.getElementById('vev_start_time');
    var endDate = document.getElementById('vev_end_date');
    var endTime = document.getElementById('vev_end_time');

    if (!startDate || !endDate) return;

    var startVal = startDate.value;
    if (startVal) {
      endDate.min = startVal;
      if (endDate.value && endDate.value < startVal) {
        endDate.value = startVal;
      }
      if (!endDate.value) {
        endDate.value = startVal;
      }
    }

    if (startDate.value && endDate.value && startDate.value === endDate.value) {
      if (startTime && endTime && startTime.value) {
        endTime.min = startTime.value;
        if (endTime.value && endTime.value < startTime.value) {
          endTime.value = startTime.value;
        }
        if (!endTime.value) {
          endTime.value = startTime.value;
        }
      }
    } else if (endTime) {
      endTime.min = '';
    }
  }

  document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'vev_all_day') {
      toggleAllDay();
    }
    if (e.target && (e.target.id === 'vev_start_date' || e.target.id === 'vev_start_time' || e.target.id === 'vev_end_date')) {
      syncEndDateMin();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    toggleAllDay();
    syncEndDateMin();
  });
})();
JS;

                wp_register_script( 'vev-events-admin', '', array(), self::VERSION, true );
                wp_add_inline_script( 'vev-events-admin', $js );
                wp_enqueue_script( 'vev-events-admin' );
        }

        public static function render_meta_box( \WP_Post $post ): void {
                wp_nonce_field( 'vev_save_event_meta', 'vev_event_meta_nonce' );

                $tz = wp_timezone();

                $start_utc = (int) get_post_meta( $post->ID, self::META_START_UTC, true );
                $end_utc   = (int) get_post_meta( $post->ID, self::META_END_UTC, true );

                $all_day  = (bool) get_post_meta( $post->ID, self::META_ALL_DAY, true );
                $hide_end = (bool) get_post_meta( $post->ID, self::META_HIDE_END, true );

                $speaker  = (string) get_post_meta( $post->ID, self::META_SPEAKER, true );
                $special  = (string) get_post_meta( $post->ID, self::META_SPECIAL, true );
                $info_url = (string) get_post_meta( $post->ID, self::META_INFO_URL, true );

                $start_date = $start_utc ? wp_date( 'Y-m-d', $start_utc, $tz ) : '';
                $start_time = $start_utc ? wp_date( 'H:i', $start_utc, $tz ) : '';
                $end_date   = $end_utc ? wp_date( 'Y-m-d', $end_utc, $tz ) : '';
                $end_time   = $end_utc ? wp_date( 'H:i', $end_utc, $tz ) : '';

                $tz_string = (string) get_option( 'timezone_string' );
                ?>
                <style>
                        .vev-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
                        .vev-field{margin-bottom:12px;}
                        .vev-field label{font-weight:600;display:block;margin-bottom:6px;}
                        .vev-inline{display:flex;gap:10px;align-items:center;}
                        .vev-help{color:#666;margin:6px 0 0 0;font-size:12px;}
                        .vev-note{background:#f6f7f7;border:1px solid #dcdcde;padding:10px;border-radius:4px;}
                </style>

                <?php if ( $tz_string && 'Europe/Berlin' !== $tz_string ) : ?>
                        <div class="notice notice-warning inline">
                                <p>
                                        <?php echo esc_html__( 'For correct Berlin/Germany date output, set the WordPress time zone to Europe/Berlin (Settings → General).', self::TEXTDOMAIN ); ?>
                                </p>
                        </div>
                <?php endif; ?>

                <div class="vev-grid">
                        <div class="vev-field">
                                <label><?php echo esc_html__( 'Start', self::TEXTDOMAIN ); ?></label>
                                <div class="vev-inline">
                                        <input type="date" id="vev_start_date" name="vev_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                                        <input type="time" id="vev_start_time" name="vev_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
                                </div>
                        </div>

                        <div class="vev-field">
                                <label><?php echo esc_html__( 'End', self::TEXTDOMAIN ); ?></label>
                                <div class="vev-inline">
                                        <input type="date" id="vev_end_date" name="vev_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                                        <input type="time" id="vev_end_time" name="vev_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
                                </div>
                                <p class="vev-help"><?php echo esc_html__( 'If empty, the end will default to the start.', self::TEXTDOMAIN ); ?></p>
                        </div>
                </div>

                <div class="vev-field">
                        <label>
                                <input type="checkbox" id="vev_all_day" name="vev_all_day" value="1" <?php checked( $all_day ); ?> />
                                <?php echo esc_html__( 'All day', self::TEXTDOMAIN ); ?>
                        </label>
                        <p class="vev-help vev-note" id="vev_all_day_note" style="display:none;">
                                <?php echo esc_html__( 'All-day events are stored as full-day ranges in your site time zone. Times will not be displayed in the virtual fields.', self::TEXTDOMAIN ); ?>
                        </p>
                </div>

                <div class="vev-field">
                        <label>
                                <input type="checkbox" id="vev_hide_end" name="vev_hide_end" value="1" <?php checked( $hide_end ); ?> />
                                <?php echo esc_html__( 'Hide end time in listings', self::TEXTDOMAIN ); ?>
                        </label>
                </div>

                <hr />

                <div class="vev-field">
                        <label for="vev_speaker"><?php echo esc_html__( 'Speaker / Host', self::TEXTDOMAIN ); ?></label>
                        <input type="text" id="vev_speaker" name="vev_speaker" class="widefat" value="<?php echo esc_attr( $speaker ); ?>" />
                </div>

                <div class="vev-field">
                        <label for="vev_special_info"><?php echo esc_html__( 'Special information', self::TEXTDOMAIN ); ?></label>
                        <textarea id="vev_special_info" name="vev_special_info" class="widefat" rows="4"><?php echo esc_textarea( $special ); ?></textarea>
                        <p class="vev-help"><?php echo esc_html__( 'E.g. registration, ticket costs, access notes, etc.', self::TEXTDOMAIN ); ?></p>
                </div>

                <div class="vev-field">
                        <label for="vev_info_url"><?php echo esc_html__( 'Info/Ticket URL', self::TEXTDOMAIN ); ?></label>
                        <input type="url" id="vev_info_url" name="vev_info_url" class="widefat" value="<?php echo esc_attr( $info_url ); ?>" placeholder="https://..." />
                </div>

                <hr />

                <div class="vev-note">
                        <p style="margin:0;">
                                <?php echo esc_html__( 'JetEngine/Elementor: Use these meta keys in listings (no shortcodes required):', self::TEXTDOMAIN ); ?>
                                <br />
                                <code><?php echo esc_html( self::META_START_UTC ); ?></code>,
                                <code><?php echo esc_html( self::META_END_UTC ); ?></code>,
                                <code><?php echo esc_html( self::VIRTUAL_TIMERANGE ); ?></code>,
                                <code><?php echo esc_html( self::VIRTUAL_STATUS_LABEL ); ?></code>,
                                <code><?php echo esc_html( self::VIRTUAL_IS_LIVE ); ?></code>
                        </p>
                </div>
                <?php
        }

        public static function save_meta( int $post_id, \WP_Post $post ): void {
                if ( self::POST_TYPE !== $post->post_type ) {
                        return;
                }

                if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                        return;
                }

                if ( ! isset( $_POST['vev_event_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vev_event_meta_nonce'] ) ), 'vev_save_event_meta' ) ) {
                        return;
                }

                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                        return;
                }

                $tz = wp_timezone();

                $start_date = isset( $_POST['vev_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_date'] ) ) : '';
                $start_time = isset( $_POST['vev_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_start_time'] ) ) : '';

                $end_date = isset( $_POST['vev_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_date'] ) ) : '';
                $end_time = isset( $_POST['vev_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_end_time'] ) ) : '';

                $all_day  = isset( $_POST['vev_all_day'] ) ? 1 : 0;
                $hide_end = isset( $_POST['vev_hide_end'] ) ? 1 : 0;

                $speaker = isset( $_POST['vev_speaker'] ) ? sanitize_text_field( wp_unslash( $_POST['vev_speaker'] ) ) : '';
                $special = isset( $_POST['vev_special_info'] ) ? wp_kses_post( wp_unslash( $_POST['vev_special_info'] ) ) : '';
                $info_url = isset( $_POST['vev_info_url'] ) ? esc_url_raw( wp_unslash( $_POST['vev_info_url'] ) ) : '';

                // Parse dates/times in site timezone, store as UTC timestamps.
                $start_ts = self::parse_to_utc_timestamp( $start_date, $start_time, (bool) $all_day, true, $tz );
                $end_ts   = self::parse_to_utc_timestamp( $end_date ?: $start_date, $end_time ?: $start_time, (bool) $all_day, false, $tz );

                // If end is missing or still invalid, default to start.
                if ( ! $end_ts ) {
                        $end_ts = $start_ts;
                }

                // For non-all-day events, ensure end >= start when both are set.
                if ( $start_ts && $end_ts && ! $all_day && $end_ts < $start_ts ) {
                        $end_ts = $start_ts;
                }

                if ( $start_ts ) {
                        update_post_meta( $post_id, self::META_START_UTC, $start_ts );
                } else {
                        delete_post_meta( $post_id, self::META_START_UTC );
                }

                if ( $end_ts ) {
                        update_post_meta( $post_id, self::META_END_UTC, $end_ts );
                } else {
                        delete_post_meta( $post_id, self::META_END_UTC );
                }

                update_post_meta( $post_id, self::META_ALL_DAY, (int) $all_day );
                update_post_meta( $post_id, self::META_HIDE_END, (int) $hide_end );

                if ( '' !== $speaker ) {
                        update_post_meta( $post_id, self::META_SPEAKER, $speaker );
                } else {
                        delete_post_meta( $post_id, self::META_SPEAKER );
                }

                if ( '' !== $special ) {
                        update_post_meta( $post_id, self::META_SPECIAL, $special );
                } else {
                        delete_post_meta( $post_id, self::META_SPECIAL );
                }

                if ( '' !== $info_url ) {
                        update_post_meta( $post_id, self::META_INFO_URL, $info_url );
                } else {
                        delete_post_meta( $post_id, self::META_INFO_URL );
                }

                self::log(
                        sprintf(
                                'Event %d saved. start_utc=%s end_utc=%s all_day=%s hide_end=%s',
                                $post_id,
                                (string) $start_ts,
                                (string) $end_ts,
                                (string) $all_day,
                                (string) $hide_end
                        )
                );
        }

        /**
         * Parses a (date, time) tuple in $tz to UTC timestamp.
         *
         * @param bool $all_day  If true: ignore time and build full-day boundary.
         * @param bool $is_start Whether this is start boundary (00:00:00) or end boundary (23:59:59).
         */
        private static function parse_to_utc_timestamp( string $date, string $time, bool $all_day, bool $is_start, \DateTimeZone $tz ): int {
                if ( '' === $date ) {
                        return 0;
                }

                try {
                        if ( $all_day ) {
                                $clock = $is_start ? '00:00:00' : '23:59:59';
                                $dt    = new \DateTimeImmutable( $date . ' ' . $clock, $tz );
                        } else {
                                $clean_time = $time ?: ( $is_start ? '00:00' : '00:00' );
                                $dt         = new \DateTimeImmutable( $date . ' ' . $clean_time, $tz );
                        }

                        return (int) $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'U' );
                } catch ( \Exception $e ) {
                        self::log( 'Date parse error: ' . $e->getMessage() );
                        return 0;
                }
        }

        /**
         * Admin list columns.
         */
        public static function admin_columns( array $columns ): array {
                $cols = array();

                // Keep checkbox + title.
                if ( isset( $columns['cb'] ) ) {
                        $cols['cb'] = $columns['cb'];
                }
                $cols['title'] = __( 'Title', self::TEXTDOMAIN );

                $cols['vev_start']  = __( 'Start', self::TEXTDOMAIN );
                $cols['vev_end']    = __( 'End', self::TEXTDOMAIN );
                $cols['vev_status'] = __( 'Status', self::TEXTDOMAIN );

                $cols[ self::TAX_CATEGORY ] = __( 'Category', self::TEXTDOMAIN );
                $cols[ self::TAX_LOCATION ] = __( 'Location', self::TEXTDOMAIN );
                $cols[ self::TAX_TOPIC ]    = __( 'Topic', self::TEXTDOMAIN );
                $cols[ self::TAX_SERIES ]   = __( 'Series', self::TEXTDOMAIN );

                // Append date.
                if ( isset( $columns['date'] ) ) {
                        $cols['date'] = $columns['date'];
                }

                return $cols;
        }

        public static function admin_sortable_columns( array $columns ): array {
                $columns['vev_start'] = 'vev_start';
                $columns['vev_end']   = 'vev_end';
                return $columns;
        }

        public static function admin_column_content( string $column, int $post_id ): void {
                switch ( $column ) {
                        case 'vev_start':
                                echo esc_html( (string) get_post_meta( $post_id, self::VIRTUAL_START_LOCAL, true ) );
                                break;

                        case 'vev_end':
                                echo esc_html( (string) get_post_meta( $post_id, self::VIRTUAL_END_LOCAL, true ) );
                                break;

                        case 'vev_status':
                                echo esc_html( (string) get_post_meta( $post_id, self::VIRTUAL_STATUS_LABEL, true ) );
                                break;

                        case self::TAX_CATEGORY:
                        case self::TAX_LOCATION:
                        case self::TAX_TOPIC:
                        case self::TAX_SERIES:
                                $terms = get_the_terms( $post_id, $column );
                                if ( is_array( $terms ) && ! empty( $terms ) ) {
                                        $names = wp_list_pluck( $terms, 'name' );
                                        echo esc_html( implode( ', ', $names ) );
                                } else {
                                        echo '—';
                                }
                                break;
                }
        }

        /**
         * Query adjustments:
         * - Admin: default sort by start_utc.
         * - Frontend: default filter to hide archived events (one day after end) + sort by start_utc.
         * - Optional scopes via vev_event_scope query var.
         */
        public static function pre_get_posts( \WP_Query $query ): void {
                if ( ! $query instanceof \WP_Query ) {
                        return;
                }

                $post_type = $query->get( 'post_type' );

                $is_event_query = false;
                $is_event_only_query = false;
                if ( is_string( $post_type ) ) {
                        $is_event_query      = ( self::POST_TYPE === $post_type );
                        $is_event_only_query = $is_event_query;
                } elseif ( is_array( $post_type ) ) {
                        $is_event_query      = in_array( self::POST_TYPE, $post_type, true );
                        $is_event_only_query = ( $is_event_query && 1 === count( $post_type ) );
                } else {
                        // If post_type is empty, this can be the main search query; we still extend later.
                        $is_event_query      = false;
                        $is_event_only_query = false;
                }

                // Admin list: default sort by start time.
                if ( is_admin() && $query->is_main_query() ) {
                        if ( self::POST_TYPE === $post_type && 'edit.php' === $GLOBALS['pagenow'] ) {
                                self::apply_ordering( $query, 'asc', true );

                                // Handle sortable columns.
                                $orderby = (string) $query->get( 'orderby' );
                                if ( 'vev_start' === $orderby ) {
                                        self::apply_ordering( $query, (string) $query->get( 'order' ) ?: 'asc', true );
                                }
                                if ( 'vev_end' === $orderby ) {
                                        $query->set( 'meta_key', self::META_END_UTC );
                                        $query->set( 'orderby', 'meta_value_num' );
                                }
                        }
                        return;
                }

                // Frontend: modify any query that explicitly requests our post type.
                if ( ! is_admin() && $is_event_only_query && ! $query->get( 'suppress_filters' ) ) {

                        $include_archived = (int) $query->get( self::QV_INCLUDE_ARCHIVED );
                        $scope            = (string) $query->get( self::QV_SCOPE );

                        $now = time();
                        $one_day_ago = $now - DAY_IN_SECONDS;

                        $meta_query = (array) $query->get( 'meta_query' );
                        if ( empty( $meta_query ) ) {
                                $meta_query = array();
                        }

                        if ( 1 !== $include_archived ) {
                                // Default behavior: hide events one day after end.
                                // Equivalent: end_utc >= now - 1 day.
                                $meta_query[] = array(
                                        'key'     => self::META_END_UTC,
                                        'value'   => $one_day_ago,
                                        'compare' => '>=',
                                        'type'    => 'NUMERIC',
                                );
                        }

                        // Optional scope refinement.
                        if ( $scope ) {
                                switch ( $scope ) {
                                        case 'upcoming':
                                                $meta_query[] = array(
                                                        'key'     => self::META_START_UTC,
                                                        'value'   => $now,
                                                        'compare' => '>',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'ongoing':
                                                $meta_query[] = array(
                                                        'key'     => self::META_START_UTC,
                                                        'value'   => $now,
                                                        'compare' => '<=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                $meta_query[] = array(
                                                        'key'     => self::META_END_UTC,
                                                        'value'   => $now,
                                                        'compare' => '>=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'past':
                                                $meta_query[] = array(
                                                        'key'     => self::META_END_UTC,
                                                        'value'   => $now,
                                                        'compare' => '<',
                                                        'type'    => 'NUMERIC',
                                                );
                                                $meta_query[] = array(
                                                        'key'     => self::META_END_UTC,
                                                        'value'   => $one_day_ago,
                                                        'compare' => '>=',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'archived':
                                                $meta_query[] = array(
                                                        'key'     => self::META_END_UTC,
                                                        'value'   => $one_day_ago,
                                                        'compare' => '<',
                                                        'type'    => 'NUMERIC',
                                                );
                                                break;

                                        case 'all':
                                        default:
                                                // no additional constraints.
                                                break;
                                }
                        }

                        $query->set( 'meta_query', $meta_query );

                        // Default ordering: start_utc ascending, but do not override explicit meta ordering.
                        $orderby = $query->get( 'orderby' );
                        $meta_key = (string) $query->get( 'meta_key' );

                        if ( empty( $orderby ) || ( 'date' === $orderby && '' === $meta_key ) ) {
                                self::apply_ordering( $query, (string) ( $query->get( 'order' ) ?: 'asc' ), false );
                        }
                }

                // Frontend search: include events in search by default (if post_type not set).
                if ( ! is_admin() && $query->is_search() && ! $query->get( 'suppress_filters' ) ) {
                        $pt = $query->get( 'post_type' );
                        if ( empty( $pt ) ) {
                                $query->set( 'post_type', array( 'post', 'page', self::POST_TYPE ) );
                        }
                }
        }

        private static function apply_ordering( \WP_Query $query, string $order, bool $force ): void {
                $order = strtoupper( $order );
                if ( 'DESC' !== $order ) {
                        $order = 'ASC';
                }

                // If not forced and query already defines meta ordering, do not override.
                if ( ! $force ) {
                        $orderby = (string) $query->get( 'orderby' );
                        $meta_key = (string) $query->get( 'meta_key' );
                        if ( 'meta_value_num' === $orderby && '' !== $meta_key ) {
                                return;
                        }
                }

                $query->set( 'meta_key', self::META_START_UTC );
                $query->set( 'orderby', 'meta_value_num' );
                $query->set( 'order', $order );
        }

        /**
         * Post states in admin list ("Past", "Ongoing").
         */
        public static function display_post_states( array $post_states, \WP_Post $post ): array {
                if ( self::POST_TYPE !== $post->post_type ) {
                        return $post_states;
                }

                $status = (string) get_post_meta( $post->ID, self::VIRTUAL_STATUS, true );
                if ( 'ongoing' === $status ) {
                        $post_states['vev_ongoing'] = __( 'Ongoing', self::TEXTDOMAIN );
                } elseif ( 'past' === $status || 'archived' === $status ) {
                        $post_states['vev_past'] = __( 'Past', self::TEXTDOMAIN );
                }

                return $post_states;
        }

        /**
         * Virtual computed meta keys for listings.
         * This intentionally avoids shortcodes and works with JetEngine/Elementor because they rely on get_post_meta().
         *
         * Keys:
         * - vev_status: upcoming|ongoing|past|archived
         * - vev_status_label: localized label
         * - vev_is_live: 1|0
         * - vev_is_past: 1|0   (past OR archived)
         * - vev_timerange: localized formatted date/time range, respects "hide end"
         * - vev_start_local, vev_end_local: localized single values (date/time), does NOT respect hide end (end is still returned)
         */
        public static function computed_meta( $value, int $object_id, string $meta_key, bool $single ) {

                // Only intercept our virtual keys.
                $virtual_keys = array(
                        self::VIRTUAL_STATUS,
                        self::VIRTUAL_STATUS_LABEL,
                        self::VIRTUAL_IS_LIVE,
                        self::VIRTUAL_IS_PAST,
                        self::VIRTUAL_TIMERANGE,
                        self::VIRTUAL_START_LOCAL,
                        self::VIRTUAL_END_LOCAL,
                );

                if ( ! in_array( $meta_key, $virtual_keys, true ) ) {
                        return $value; // null -> default processing.
                }

                if ( self::POST_TYPE !== get_post_type( $object_id ) ) {
                        return $value;
                }

                $data = self::get_event_data( $object_id );

                $status = self::get_event_status( $data['start_utc'], $data['end_utc'] );

                if ( self::VIRTUAL_STATUS === $meta_key ) {
                        return $single ? $status : array( $status );
                }

                if ( self::VIRTUAL_STATUS_LABEL === $meta_key ) {
                        $label = self::status_label( $status );
                        return $single ? $label : array( $label );
                }

                if ( self::VIRTUAL_IS_LIVE === $meta_key ) {
                        $is_live = ( 'ongoing' === $status ) ? '1' : '0';
                        return $single ? $is_live : array( $is_live );
                }

                if ( self::VIRTUAL_IS_PAST === $meta_key ) {
                        $is_past = ( 'past' === $status || 'archived' === $status ) ? '1' : '0';
                        return $single ? $is_past : array( $is_past );
                }

                if ( self::VIRTUAL_TIMERANGE === $meta_key ) {
                        $timerange = self::format_timerange( $data, true );
                        return $single ? $timerange : array( $timerange );
                }

                if ( self::VIRTUAL_START_LOCAL === $meta_key ) {
                        $val = self::format_single_datetime( $data['start_utc'], (bool) $data['all_day'], true );
                        return $single ? $val : array( $val );
                }

                if ( self::VIRTUAL_END_LOCAL === $meta_key ) {
                        $val = self::format_single_datetime( $data['end_utc'], (bool) $data['all_day'], false );
                        return $single ? $val : array( $val );
                }

                return $value;
        }

        private static function get_event_data( int $post_id ): array {
                $start_utc = (int) get_post_meta( $post_id, self::META_START_UTC, true );
                $end_utc   = (int) get_post_meta( $post_id, self::META_END_UTC, true );

                $all_day   = (int) get_post_meta( $post_id, self::META_ALL_DAY, true );
                $hide_end  = (int) get_post_meta( $post_id, self::META_HIDE_END, true );

                if ( ! $end_utc && $start_utc ) {
                        $end_utc = $start_utc;
                }

                return array(
                        'start_utc' => $start_utc,
                        'end_utc'   => $end_utc,
                        'all_day'   => (bool) $all_day,
                        'hide_end'  => (bool) $hide_end,
                );
        }

        private static function get_event_status( int $start_utc, int $end_utc ): string {
                if ( ! $start_utc ) {
                        return 'upcoming';
                }
                if ( ! $end_utc ) {
                        $end_utc = $start_utc;
                }

                $now = time();

                if ( $now < $start_utc ) {
                        return 'upcoming';
                }

                if ( $now <= $end_utc ) {
                        return 'ongoing';
                }

                $settings = self::get_settings();
                $grace_period = absint( $settings['grace_period'] ?? 1 );
                $grace_seconds = $grace_period * DAY_IN_SECONDS;

                if ( $now <= ( $end_utc + $grace_seconds ) ) {
                        return 'past';
                }

                return 'archived';
        }

        private static function status_label( string $status ): string {
                switch ( $status ) {
                        case 'ongoing':
                                return __( 'Ongoing', self::TEXTDOMAIN );
                        case 'past':
                                return __( 'Past', self::TEXTDOMAIN );
                        case 'archived':
                                return __( 'Archived', self::TEXTDOMAIN );
                        case 'upcoming':
                        default:
                                return __( 'Upcoming', self::TEXTDOMAIN );
                }
        }

        private static function format_single_datetime( int $ts_utc, bool $all_day, bool $is_start ): string {
                if ( ! $ts_utc ) {
                        return '';
                }

                $tz = wp_timezone();

                $date_format = (string) get_option( 'date_format' );
                $time_format = (string) get_option( 'time_format' );

                if ( $all_day ) {
                        // For all-day, show date only.
                        return wp_date( $date_format, $ts_utc, $tz );
                }

                // Date + time.
                return wp_date( $date_format . ' ' . $time_format, $ts_utc, $tz );
        }

        private static function format_timerange( array $data, bool $respect_hide_end ): string {
                $start = (int) $data['start_utc'];
                $end   = (int) $data['end_utc'];
                $all_day = (bool) $data['all_day'];
                $hide_end = (bool) $data['hide_end'];

                if ( ! $start ) {
                        return '';
                }
                if ( ! $end ) {
                        $end = $start;
                }

                $tz = wp_timezone();
                $date_format = (string) get_option( 'date_format' );
                $time_format = (string) get_option( 'time_format' );

                $start_date = wp_date( $date_format, $start, $tz );
                $end_date   = wp_date( $date_format, $end, $tz );

                $settings = self::get_settings();
                $hide_end_same_day = ! empty( $settings['hide_end_same_day'] );

                if ( $all_day ) {
                        if ( $start_date === $end_date ) {
                                return sprintf(
                                        /* translators: %s: date */
                                        __( '%s (all day)', self::TEXTDOMAIN ),
                                        $start_date
                                );
                        }
                        return sprintf(
                                /* translators: 1: start date, 2: end date */
                                __( '%1$s – %2$s (all day)', self::TEXTDOMAIN ),
                                $start_date,
                                $end_date
                        );
                }

                $start_time = wp_date( $time_format, $start, $tz );
                $end_time   = wp_date( $time_format, $end, $tz );

                if ( $respect_hide_end && $hide_end ) {
                        return sprintf(
                                /* translators: 1: date, 2: time */
                                __( '%1$s, %2$s', self::TEXTDOMAIN ),
                                $start_date,
                                $start_time
                        );
                }

                if ( $start_date === $end_date ) {
                        if ( $hide_end_same_day ) {
                                return sprintf(
                                        /* translators: 1: date, 2: start time, 3: end time. Format: "12.06.2025 · 10:00 – 12:00" */
                                        __( '%1$s · %2$s – %3$s', self::TEXTDOMAIN ),
                                        $start_date,
                                        $start_time,
                                        $end_time
                                );
                        }
                        return sprintf(
                                /* translators: 1: start date, 2: start time, 3: end date, 4: end time. Format: "12.06.2025 · 10:00 – 12.06.2025 · 12:00" */
                                __( '%1$s · %2$s – %3$s · %4$s', self::TEXTDOMAIN ),
                                $start_date,
                                $start_time,
                                $end_date,
                                $end_time
                        );
                }

                return sprintf(
                        /* translators: 1: start date, 2: start time, 3: end date, 4: end time */
                        __( '%1$s %2$s – %3$s %4$s', self::TEXTDOMAIN ),
                        $start_date,
                        $start_time,
                        $end_date,
                        $end_time
                );
        }

        /**
         * REST: Add computed fields for headless usage.
         */
        public static function register_rest_fields(): void {
                register_rest_field(
                        self::POST_TYPE,
                        'vev_status',
                        array(
                                'get_callback' => static function ( array $object ) {
                                        return get_post_meta( (int) $object['id'], self::VIRTUAL_STATUS, true );
                                },
                                'schema'       => array(
                                        'type' => 'string',
                                ),
                        )
                );

                register_rest_field(
                        self::POST_TYPE,
                        'vev_timerange',
                        array(
                                'get_callback' => static function ( array $object ) {
                                        return get_post_meta( (int) $object['id'], self::VIRTUAL_TIMERANGE, true );
                                },
                                'schema'       => array(
                                        'type' => 'string',
                                ),
                        )
                );
        }

        /**
         * Schema.org Event JSON-LD output on single event pages.
         */
        public static function output_schema(): void {
                if ( ! is_singular( self::POST_TYPE ) ) {
                        return;
                }

                $post_id = (int) get_queried_object_id();
                if ( ! $post_id ) {
                        return;
                }

                $data = self::get_event_data( $post_id );
                if ( ! $data['start_utc'] ) {
                        return;
                }

                $tz = wp_timezone();

                $start_iso = self::schema_datetime( $data['start_utc'], $data['all_day'], true, $tz );
                $end_iso   = self::schema_datetime( $data['end_utc'], $data['all_day'], false, $tz );

                $event = array(
                        '@context'    => 'https://schema.org',
                        '@type'       => 'Event',
                        'name'        => get_the_title( $post_id ),
                        'description' => self::schema_description( $post_id ),
                        'url'         => get_permalink( $post_id ),
                        'startDate'   => $start_iso,
                        'endDate'     => $end_iso,
                );

                $img = get_the_post_thumbnail_url( $post_id, 'full' );
                if ( $img ) {
                        $event['image'] = array( $img );
                }

                // Location: first term name.
                $loc_terms = get_the_terms( $post_id, self::TAX_LOCATION );
                if ( is_array( $loc_terms ) && ! empty( $loc_terms ) ) {
                        $loc = $loc_terms[0];
                        $event['location'] = array(
                                '@type' => 'Place',
                                'name'  => $loc->name,
                        );
                }

                // Speaker/Host as performer.
                $speaker = (string) get_post_meta( $post_id, self::META_SPEAKER, true );
                if ( '' !== $speaker ) {
                        $event['performer'] = array(
                                '@type' => 'Person',
                                'name'  => $speaker,
                        );
                }

                // Ticket/info URL.
                $info_url = (string) get_post_meta( $post_id, self::META_INFO_URL, true );
                if ( '' !== $info_url ) {
                        $event['offers'] = array(
                                '@type' => 'Offer',
                                'url'   => $info_url,
                        );
                }

                // Series as superEvent (if enabled in settings).
                $settings = self::get_settings();
                if ( ! empty( $settings['include_series_schema'] ) ) {
                        $series_terms = get_the_terms( $post_id, self::TAX_SERIES );
                        if ( is_array( $series_terms ) && ! empty( $series_terms ) ) {
                                $series = $series_terms[0];
                                $event['superEvent'] = array(
                                        '@type' => 'EventSeries',
                                        'name'  => $series->name,
                                        'url'   => get_term_link( $series ),
                                );
                        }
                }

                $event = apply_filters( 'vev_schema_event', $event, $post_id );

                printf(
                        "\n<script type=\"application/ld+json\">%s</script>\n",
                        wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                );
        }

        private static function schema_datetime( int $ts_utc, bool $all_day, bool $is_start, \DateTimeZone $tz ): string {
                if ( ! $ts_utc ) {
                        return '';
                }

                if ( $all_day ) {
                        // Date only.
                        return wp_date( 'Y-m-d', $ts_utc, $tz );
                }

                // Full ISO 8601 with timezone offset based on site timezone.
                return wp_date( 'c', $ts_utc, $tz );
        }

        private static function schema_description( int $post_id ): string {
                $post = get_post( $post_id );
                if ( ! $post ) {
                        return '';
                }

                // Prefer excerpt; fallback to trimmed content.
                $excerpt = trim( (string) get_the_excerpt( $post ) );
                if ( '' !== $excerpt ) {
                        return wp_strip_all_tags( $excerpt );
                }

                $content = wp_strip_all_tags( (string) $post->post_content );
                $content = preg_replace( '/\s+/', ' ', $content );
                $content = trim( $content );
                if ( function_exists( 'mb_substr' ) ) {
                        return mb_substr( $content, 0, 300 );
                }
                return substr( $content, 0, 300 );
        }

        /**
         * Search: join postmeta + terms (only for frontend search queries).
         */
        public static function search_join( string $join, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $join;
                }

                global $wpdb;

                // Join once.
                if ( false === strpos( $join, 'vev_pm' ) ) {
                        $join .= " LEFT JOIN {$wpdb->postmeta} vev_pm ON ({$wpdb->posts}.ID = vev_pm.post_id) ";
                }

                // Dedicated join for end timestamp (used to hide archived events in mixed post_type searches).
                if ( false === strpos( $join, 'vev_pm_end' ) ) {
                        $join .= " LEFT JOIN {$wpdb->postmeta} vev_pm_end ON ({$wpdb->posts}.ID = vev_pm_end.post_id AND vev_pm_end.meta_key = '" . esc_sql( self::META_END_UTC ) . "') ";
                }

                if ( false === strpos( $join, 'vev_tr' ) ) {
                        $join .= " LEFT JOIN {$wpdb->term_relationships} vev_tr ON ({$wpdb->posts}.ID = vev_tr.object_id) ";
                        $join .= " LEFT JOIN {$wpdb->term_taxonomy} vev_tt ON (vev_tr.term_taxonomy_id = vev_tt.term_taxonomy_id) ";
                        $join .= " LEFT JOIN {$wpdb->terms} vev_t ON (vev_tt.term_id = vev_t.term_id) ";
                }

                return $join;
        }

        public static function search_where( string $where, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $where;
                }

                $settings = self::get_settings();
                if ( empty( $settings['hide_archived_search'] ) ) {
                        return $where;
                }

                global $wpdb;
                $now = time();
                $grace_period = absint( $settings['grace_period'] ?? 1 );
                $grace_seconds = $grace_period * DAY_IN_SECONDS;
                $cutoff = $now - $grace_seconds;

                $where .= $wpdb->prepare(
                        " AND ( {$wpdb->posts}.post_type != %s OR ( CAST( vev_pm_end.meta_value AS SIGNED ) >= %d ) )",
                        self::POST_TYPE,
                        $cutoff
                );

                return $where;
        }

        public static function search_distinct( string $distinct, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $distinct;
                }
                return 'DISTINCT';
        }

        /**
         * Search: extend the search clause to include event meta + event taxonomy terms.
         */
        public static function extend_search( string $search, \WP_Query $query ): string {
                if ( is_admin() || ! $query->is_search() ) {
                        return $search;
                }

                global $wpdb;

                $term = (string) $query->get( 's' );
                $term = trim( $term );

                if ( '' === $term || '' === $search ) {
                        return $search;
                }

                $like = '%' . $wpdb->esc_like( $term ) . '%';

                $tax_list = array( self::TAX_CATEGORY, self::TAX_LOCATION, self::TAX_TOPIC, self::TAX_SERIES );
                $tax_in   = "'" . implode( "','", array_map( 'esc_sql', $tax_list ) ) . "'";

                $meta_keys = array( self::META_SPEAKER, self::META_SPECIAL, self::META_INFO_URL );
                $meta_in   = "'" . implode( "','", array_map( 'esc_sql', $meta_keys ) ) . "'";

                // Only broaden matches for Events; avoid affecting other post types.
                $extra = $wpdb->prepare(
                        " OR ( {$wpdb->posts}.post_type = %s AND ( ( vev_pm.meta_key IN ($meta_in) AND vev_pm.meta_value LIKE %s ) OR ( vev_tt.taxonomy IN ($tax_in) AND vev_t.name LIKE %s ) ) )",
                        self::POST_TYPE,
                        $like,
                        $like
                );

                // Insert extra OR before the last closing parentheses of the existing search clause.
                $pos = strrpos( $search, '))' );
                if ( false === $pos ) {
                        return $search;
                }

                return substr( $search, 0, $pos ) . $extra . substr( $search, $pos );
        }

        /**
         * Minimal logger to uploads directory.
         * Enable via WP_DEBUG or define('VEV_DEBUG', true).
         */
        private static function log( string $message ): void {
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

                // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
                @file_put_contents( $file, $line, FILE_APPEND );
        }
}

VEV_Events::init();
