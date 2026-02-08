<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

final class VEV_Admin {

        public static function init(): void {
                add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
                add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

                add_filter( 'manage_' . VEV_Events::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
                add_action( 'manage_' . VEV_Events::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
                add_filter( 'manage_edit-' . VEV_Events::POST_TYPE . '_sortable_columns', array( __CLASS__, 'admin_sortable_columns' ) );

                add_filter( 'display_post_states', array( __CLASS__, 'display_post_states' ), 10, 2 );

                add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
                add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

                add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'maybe_disable_gutenberg' ), 10, 2 );
        }

        public static function maybe_disable_gutenberg( bool $use_block_editor, string $post_type ): bool {
                if ( VEV_Events::POST_TYPE !== $post_type ) {
                        return $use_block_editor;
                }
                $settings = VEV_Events::get_settings();
                if ( ! empty( $settings['disable_gutenberg'] ) ) {
                        return false;
                }
                return $use_block_editor;
        }

        public static function add_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                add_submenu_page(
                        'edit.php?post_type=' . VEV_Events::POST_TYPE,
                        __( 'VE Events Settings', VEV_Events::TEXTDOMAIN ),
                        __( 'Settings', VEV_Events::TEXTDOMAIN ),
                        'manage_options',
                        'vev-settings',
                        array( __CLASS__, 'render_settings_page' )
                );
        }

        public static function register_settings(): void {
                register_setting( 'vev_settings_group', VEV_Events::OPTION_SETTINGS, array(
                        'type' => 'array',
                        'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                ) );

                add_action( 'update_option_' . VEV_Events::OPTION_SETTINGS, array( __CLASS__, 'maybe_flush_rewrite_rules' ), 10, 2 );
        }

        public static function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
                $old_single  = $old_value['slug_single'] ?? 'event';
                $old_archive = $old_value['slug_archive'] ?? 'events';
                $new_single  = $new_value['slug_single'] ?? 'event';
                $new_archive = $new_value['slug_archive'] ?? 'events';

                if ( $old_single !== $new_single || $old_archive !== $new_archive ) {
                        set_transient( 'vev_flush_rewrite_rules', 1, 60 );
                }
        }

        public static function sanitize_settings( $input ): array {
                $sanitized = array();
                $sanitized['disable_gutenberg']     = ! empty( $input['disable_gutenberg'] );
                $sanitized['hide_end_same_day']     = ! empty( $input['hide_end_same_day'] );
                $sanitized['grace_period']          = absint( $input['grace_period'] ?? 1 );
                $sanitized['hide_archived_search']  = ! empty( $input['hide_archived_search'] );
                $sanitized['include_series_schema'] = ! empty( $input['include_series_schema'] );

                $slug_single  = isset( $input['slug_single'] ) ? sanitize_title( $input['slug_single'] ) : '';
                $slug_archive = isset( $input['slug_archive'] ) ? sanitize_title( $input['slug_archive'] ) : '';

                $reserved_slugs = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'author', 'order', 'theme', 'category', 'tag', 'admin', 'wp-admin', 'wp-content', 'wp-includes' );

                if ( '' === $slug_single || in_array( $slug_single, $reserved_slugs, true ) || is_numeric( $slug_single ) ) {
                        $slug_single = 'event';
                }
                if ( '' === $slug_archive || in_array( $slug_archive, $reserved_slugs, true ) || is_numeric( $slug_archive ) ) {
                        $slug_archive = 'events';
                }

                if ( $slug_single === $slug_archive ) {
                        $slug_archive = $slug_single . 's';
                        if ( '' === $slug_archive || 's' === $slug_archive ) {
                                $slug_archive = 'events';
                        }
                }

                $sanitized['slug_single']  = $slug_single;
                $sanitized['slug_archive'] = $slug_archive;

                return $sanitized;
        }

        public static function render_settings_page(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }
                $settings = VEV_Events::get_settings();
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'VE Events Settings', VEV_Events::TEXTDOMAIN ); ?></h1>
                        <p class="description"><?php esc_html_e( 'These settings are only visible to administrators and control the global behavior of the VE Events plugin.', VEV_Events::TEXTDOMAIN ); ?></p>

                        <form method="post" action="options.php">
                                <?php settings_fields( 'vev_settings_group' ); ?>

                                <h2><?php esc_html_e( 'URL Settings', VEV_Events::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Event Slug (Single)', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <input type="text" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[slug_single]" value="<?php echo esc_attr( $settings['slug_single'] ); ?>" class="regular-text" placeholder="event" />
                                                        <p class="description"><?php esc_html_e( 'URL slug for single events (e.g., "event" or "veranstaltung"). Default: event', VEV_Events::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Archive Slug', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <input type="text" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[slug_archive]" value="<?php echo esc_attr( $settings['slug_archive'] ); ?>" class="regular-text" placeholder="events" />
                                                        <p class="description"><?php esc_html_e( 'URL slug for event archive (e.g., "events" or "veranstaltungen"). Default: events', VEV_Events::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                </table>
                                <p class="description" style="color:#d63638;"><strong><?php esc_html_e( 'Note: After changing slugs, permalinks will be automatically refreshed. Existing event URLs will change accordingly.', VEV_Events::TEXTDOMAIN ); ?></strong></p>

                                <h2><?php esc_html_e( 'Editor Settings', VEV_Events::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Gutenberg Editor', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[disable_gutenberg]" value="1" <?php checked( $settings['disable_gutenberg'] ); ?> />
                                                                <?php esc_html_e( 'Disable Gutenberg block editor for Events', VEV_Events::TEXTDOMAIN ); ?>
                                                        </label>
                                                        <p class="description"><?php esc_html_e( 'When enabled, events will use the classic WordPress editor instead of the block editor.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Display Settings', VEV_Events::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Hide End Date if Same Day', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[hide_end_same_day]" value="1" <?php checked( $settings['hide_end_same_day'] ); ?> />
                                                                <?php esc_html_e( 'Automatically hide the end date if start and end are on the same day', VEV_Events::TEXTDOMAIN ); ?>
                                                        </label>
                                                        <p class="description">
                                                                <?php esc_html_e( 'Example: "12.06.2025 · 10:00 – 12:00" instead of "12.06.2025 · 10:00 – 12.06.2025 · 12:00"', VEV_Events::TEXTDOMAIN ); ?>
                                                        </p>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Event Visibility & Status', VEV_Events::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Grace Period After Event End', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <select name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[grace_period]">
                                                                <option value="0" <?php selected( $settings['grace_period'], 0 ); ?>><?php esc_html_e( '0 days (immediately hidden)', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                <option value="1" <?php selected( $settings['grace_period'], 1 ); ?>><?php esc_html_e( '1 day (recommended)', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                <option value="3" <?php selected( $settings['grace_period'], 3 ); ?>><?php esc_html_e( '3 days', VEV_Events::TEXTDOMAIN ); ?></option>
                                                                <option value="7" <?php selected( $settings['grace_period'], 7 ); ?>><?php esc_html_e( '7 days', VEV_Events::TEXTDOMAIN ); ?></option>
                                                        </select>
                                                        <p class="description"><?php esc_html_e( 'Choose how long events remain visible on the frontend after ending. Backend visibility is never affected.', VEV_Events::TEXTDOMAIN ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Hide Archived Events from Search', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[hide_archived_search]" value="1" <?php checked( $settings['hide_archived_search'] ); ?> />
                                                                <?php esc_html_e( 'Exclude archived events from WordPress search results', VEV_Events::TEXTDOMAIN ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                </table>

                                <h2><?php esc_html_e( 'Schema.org Settings', VEV_Events::TEXTDOMAIN ); ?></h2>
                                <table class="form-table">
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Include Event Series in Schema', VEV_Events::TEXTDOMAIN ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( VEV_Events::OPTION_SETTINGS ); ?>[include_series_schema]" value="1" <?php checked( $settings['include_series_schema'] ); ?> />
                                                                <?php esc_html_e( 'Add series name to Schema as eventSeries or superEvent', VEV_Events::TEXTDOMAIN ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                </table>

                                <?php submit_button(); ?>
                        </form>

                        <hr />

                        <h2><?php esc_html_e( 'Event Status Logic', VEV_Events::TEXTDOMAIN ); ?></h2>
                        <div class="vev-docs" style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:15px;max-width:800px;">
                                <p><?php esc_html_e( 'Event status is calculated dynamically based on the current time:', VEV_Events::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:500px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Status', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', VEV_Events::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>upcoming</code></td><td><?php esc_html_e( 'Event has not started yet', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ongoing</code></td><td><?php esc_html_e( 'Event is currently running', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>past</code></td><td><?php esc_html_e( 'Event has ended', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>archived</code></td><td><?php esc_html_e( 'Event ended more than the grace period ago', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>
                        </div>

                        <hr />

                        <h2><?php esc_html_e( 'Documentation', VEV_Events::TEXTDOMAIN ); ?></h2>

                        <div class="vev-docs" style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin-top:15px;max-width:800px;">
                                <h3><?php esc_html_e( 'About VE Events', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'VE Events adds a lightweight Events custom post type with WordPress-native admin UI, Schema.org Event markup, and first-class support for Elementor/JetEngine listings.', VEV_Events::TEXTDOMAIN ); ?></p>

                                <h3><?php esc_html_e( 'Core Principle', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'VE Events does not rely on shortcodes. All data is exposed via standard WordPress Meta Keys, Virtual (computed) Meta Keys, and Taxonomies. This ensures full compatibility with JetEngine Listings, sortable and filterable queries, and clean Elementor templates.', VEV_Events::TEXTDOMAIN ); ?></p>

                                <h3><?php esc_html_e( 'Features', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Custom post type "Events" with date/time management', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Event Categories, Locations, Topics, and Series taxonomies', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Speaker/Host and special information fields', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Schema.org structured data output for SEO', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Compatible with JetEngine/Elementor listings', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Multilingual support (WPML & Polylang compatible)', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Automatic event status calculation', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Automatic hiding of past events from frontend', VEV_Events::TEXTDOMAIN ); ?></li>
                                </ul>

                                <h3><?php esc_html_e( 'Stored Meta Keys (Database)', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Use these meta keys for sorting, filtering, and conditions:', VEV_Events::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:600px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Meta Key', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', VEV_Events::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>_vev_start_utc</code></td><td><?php esc_html_e( 'Event start timestamp (UTC)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_end_utc</code></td><td><?php esc_html_e( 'Event end timestamp (UTC)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_all_day</code></td><td><?php esc_html_e( 'All-day event (boolean)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_hide_end</code></td><td><?php esc_html_e( 'Hide end time', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_speaker</code></td><td><?php esc_html_e( 'Speaker / Host', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_special_info</code></td><td><?php esc_html_e( 'Special information', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>_vev_info_url</code></td><td><?php esc_html_e( 'Info / Ticket URL', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Virtual / Computed Meta Keys', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Computed at runtime and usable like normal meta fields:', VEV_Events::TEXTDOMAIN ); ?></p>
                                <table class="widefat" style="max-width:600px;">
                                        <thead>
                                                <tr>
                                                        <th><?php esc_html_e( 'Meta Key', VEV_Events::TEXTDOMAIN ); ?></th>
                                                        <th><?php esc_html_e( 'Description', VEV_Events::TEXTDOMAIN ); ?></th>
                                                </tr>
                                        </thead>
                                        <tbody>
                                                <tr><td><code>ve_start_date</code></td><td><?php esc_html_e( 'Start date (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_start_time</code></td><td><?php esc_html_e( 'Start time (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_end_date</code></td><td><?php esc_html_e( 'End date (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_end_time</code></td><td><?php esc_html_e( 'End time (formatted)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_date_range</code></td><td><?php esc_html_e( 'Date range (smart)', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_time_range</code></td><td><?php esc_html_e( 'Time range or "All day"', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_datetime_formatted</code></td><td><?php esc_html_e( 'Full date & time', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_status</code></td><td><?php esc_html_e( 'Status: Upcoming / Ongoing / Past', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_is_upcoming</code></td><td><?php esc_html_e( 'Boolean: is upcoming', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                                <tr><td><code>ve_is_ongoing</code></td><td><?php esc_html_e( 'Boolean: is ongoing', VEV_Events::TEXTDOMAIN ); ?></td></tr>
                                        </tbody>
                                </table>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'JetEngine Query Setup (Recommended)', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <h4><?php esc_html_e( 'Upcoming Events', VEV_Events::TEXTDOMAIN ); ?></h4>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Post Type: ve_event', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Order By: Meta Value (Number)', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Meta Key: _vev_start_utc', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Order: ASC', VEV_Events::TEXTDOMAIN ); ?></li>
                                </ul>
                                <h4><?php esc_html_e( 'Past Events', VEV_Events::TEXTDOMAIN ); ?></h4>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><?php esc_html_e( 'Same query as above', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><?php esc_html_e( 'Add Query Var: vev_event_scope = past', VEV_Events::TEXTDOMAIN ); ?></li>
                                </ul>

                                <h3 style="margin-top:20px;"><?php esc_html_e( 'Query Scopes', VEV_Events::TEXTDOMAIN ); ?></h3>
                                <p><?php esc_html_e( 'Use these query vars to filter events:', VEV_Events::TEXTDOMAIN ); ?></p>
                                <ul style="list-style:disc;margin-left:20px;">
                                        <li><code>vev_event_scope</code>: <?php esc_html_e( 'Filter by scope (upcoming, past, live, all)', VEV_Events::TEXTDOMAIN ); ?></li>
                                        <li><code>vev_include_archived</code>: <?php esc_html_e( 'Include archived/past events', VEV_Events::TEXTDOMAIN ); ?></li>
                                </ul>
                        </div>
                </div>
                <?php
        }

        public static function add_meta_boxes(): void {
                add_meta_box(
                        'vev_event_details',
                        __( 'Event Details', VEV_Events::TEXTDOMAIN ),
                        array( __CLASS__, 'render_meta_box' ),
                        VEV_Events::POST_TYPE,
                        'normal',
                        'high'
                );
        }

        public static function admin_assets( string $hook ): void {
                if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
                        return;
                }
                $screen = get_current_screen();
                if ( ! $screen || VEV_Events::POST_TYPE !== $screen->post_type ) {
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

                wp_register_script( 'vev-events-admin', '', array(), VEV_Events::VERSION, true );
                wp_add_inline_script( 'vev-events-admin', $js );
                wp_enqueue_script( 'vev-events-admin' );
        }

        public static function render_meta_box( \WP_Post $post ): void {
                wp_nonce_field( 'vev_save_event_meta', 'vev_event_meta_nonce' );

                $tz = wp_timezone();

                $start_utc = (int) get_post_meta( $post->ID, VEV_Events::META_START_UTC, true );
                $end_utc   = (int) get_post_meta( $post->ID, VEV_Events::META_END_UTC, true );

                $all_day  = (bool) get_post_meta( $post->ID, VEV_Events::META_ALL_DAY, true );
                $hide_end = (bool) get_post_meta( $post->ID, VEV_Events::META_HIDE_END, true );

                $speaker  = (string) get_post_meta( $post->ID, VEV_Events::META_SPEAKER, true );
                $special  = (string) get_post_meta( $post->ID, VEV_Events::META_SPECIAL, true );
                $info_url = (string) get_post_meta( $post->ID, VEV_Events::META_INFO_URL, true );

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
                                        <?php echo esc_html__( 'For correct Berlin/Germany date output, set the WordPress time zone to Europe/Berlin (Settings → General).', VEV_Events::TEXTDOMAIN ); ?>
                                </p>
                        </div>
                <?php endif; ?>

                <div class="vev-grid">
                        <div class="vev-field">
                                <label><?php echo esc_html__( 'Start', VEV_Events::TEXTDOMAIN ); ?></label>
                                <div class="vev-inline">
                                        <input type="date" id="vev_start_date" name="vev_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
                                        <input type="time" id="vev_start_time" name="vev_start_time" value="<?php echo esc_attr( $start_time ); ?>" />
                                </div>
                        </div>

                        <div class="vev-field">
                                <label><?php echo esc_html__( 'End', VEV_Events::TEXTDOMAIN ); ?></label>
                                <div class="vev-inline">
                                        <input type="date" id="vev_end_date" name="vev_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
                                        <input type="time" id="vev_end_time" name="vev_end_time" value="<?php echo esc_attr( $end_time ); ?>" />
                                </div>
                                <p class="vev-help"><?php echo esc_html__( 'If empty, the end will default to the start.', VEV_Events::TEXTDOMAIN ); ?></p>
                        </div>
                </div>

                <div class="vev-field">
                        <label>
                                <input type="checkbox" id="vev_all_day" name="vev_all_day" value="1" <?php checked( $all_day ); ?> />
                                <?php echo esc_html__( 'All day', VEV_Events::TEXTDOMAIN ); ?>
                        </label>
                        <p class="vev-help vev-note" id="vev_all_day_note" style="display:none;">
                                <?php echo esc_html__( 'All-day events are stored as full-day ranges in your site time zone. Times will not be displayed in the virtual fields.', VEV_Events::TEXTDOMAIN ); ?>
                        </p>
                </div>

                <div class="vev-field">
                        <label>
                                <input type="checkbox" id="vev_hide_end" name="vev_hide_end" value="1" <?php checked( $hide_end ); ?> />
                                <?php echo esc_html__( 'Hide end time in listings', VEV_Events::TEXTDOMAIN ); ?>
                        </label>
                </div>

                <hr />

                <div class="vev-field">
                        <label for="vev_speaker"><?php echo esc_html__( 'Speaker / Host', VEV_Events::TEXTDOMAIN ); ?></label>
                        <input type="text" id="vev_speaker" name="vev_speaker" class="widefat" value="<?php echo esc_attr( $speaker ); ?>" />
                </div>

                <div class="vev-field">
                        <label for="vev_special_info"><?php echo esc_html__( 'Special information', VEV_Events::TEXTDOMAIN ); ?></label>
                        <textarea id="vev_special_info" name="vev_special_info" class="widefat" rows="4"><?php echo esc_textarea( $special ); ?></textarea>
                        <p class="vev-help"><?php echo esc_html__( 'E.g. registration, ticket costs, access notes, etc.', VEV_Events::TEXTDOMAIN ); ?></p>
                </div>

                <div class="vev-field">
                        <label for="vev_info_url"><?php echo esc_html__( 'Info/Ticket URL', VEV_Events::TEXTDOMAIN ); ?></label>
                        <input type="url" id="vev_info_url" name="vev_info_url" class="widefat" value="<?php echo esc_attr( $info_url ); ?>" placeholder="https://..." />
                </div>

                <hr />

                <div class="vev-note">
                        <p style="margin:0;">
                                <?php echo esc_html__( 'JetEngine/Elementor: Use these meta keys in listings (no shortcodes required):', VEV_Events::TEXTDOMAIN ); ?>
                                <br />
                                <code><?php echo esc_html( VEV_Events::VIRTUAL_DATETIME ); ?></code>,
                                <code><?php echo esc_html( VEV_Events::VIRTUAL_DATE_RANGE ); ?></code>,
                                <code><?php echo esc_html( VEV_Events::VIRTUAL_TIME_RANGE ); ?></code>,
                                <code><?php echo esc_html( VEV_Events::VIRTUAL_STATUS ); ?></code>
                        </p>
                </div>
                <?php
        }

        public static function save_meta( int $post_id, \WP_Post $post ): void {
                if ( VEV_Events::POST_TYPE !== $post->post_type ) {
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

                $start_ts = self::parse_to_utc_timestamp( $start_date, $start_time, (bool) $all_day, true, $tz );
                $end_ts   = self::parse_to_utc_timestamp( $end_date ?: $start_date, $end_time ?: $start_time, (bool) $all_day, false, $tz );

                if ( ! $end_ts ) {
                        $end_ts = $start_ts;
                }

                if ( $start_ts && $end_ts && ! $all_day && $end_ts < $start_ts ) {
                        $end_ts = $start_ts;
                }

                if ( $start_ts ) {
                        update_post_meta( $post_id, VEV_Events::META_START_UTC, $start_ts );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_START_UTC );
                }

                if ( $end_ts ) {
                        update_post_meta( $post_id, VEV_Events::META_END_UTC, $end_ts );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_END_UTC );
                }

                update_post_meta( $post_id, VEV_Events::META_ALL_DAY, (int) $all_day );
                update_post_meta( $post_id, VEV_Events::META_HIDE_END, (int) $hide_end );

                if ( '' !== $speaker ) {
                        update_post_meta( $post_id, VEV_Events::META_SPEAKER, $speaker );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_SPEAKER );
                }

                if ( '' !== $special ) {
                        update_post_meta( $post_id, VEV_Events::META_SPECIAL, $special );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_SPECIAL );
                }

                if ( '' !== $info_url ) {
                        update_post_meta( $post_id, VEV_Events::META_INFO_URL, $info_url );
                } else {
                        delete_post_meta( $post_id, VEV_Events::META_INFO_URL );
                }

                VEV_Events::log(
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
                        VEV_Events::log( 'Date parse error: ' . $e->getMessage() );
                        return 0;
                }
        }

        public static function admin_columns( array $columns ): array {
                $cols = array();

                if ( isset( $columns['cb'] ) ) {
                        $cols['cb'] = $columns['cb'];
                }
                $cols['title'] = __( 'Title', VEV_Events::TEXTDOMAIN );

                $cols['vev_start'] = __( 'Start', VEV_Events::TEXTDOMAIN );
                $cols['vev_end']   = __( 'End', VEV_Events::TEXTDOMAIN );

                $cols[ VEV_Events::TAX_CATEGORY ] = __( 'Category', VEV_Events::TEXTDOMAIN );
                $cols[ VEV_Events::TAX_LOCATION ] = __( 'Location', VEV_Events::TEXTDOMAIN );
                $cols[ VEV_Events::TAX_SERIES ]   = __( 'Series', VEV_Events::TEXTDOMAIN );

                $cols['date'] = __( 'Date', VEV_Events::TEXTDOMAIN );

                return $cols;
        }

        public static function admin_sortable_columns( array $columns ): array {
                $columns['vev_start'] = 'vev_start';
                $columns['vev_end']   = 'vev_end';
                return $columns;
        }

        public static function admin_column_content( string $column, int $post_id ): void {
                $tz = wp_timezone();
                $date_format = (string) get_option( 'date_format' );
                $time_format = (string) get_option( 'time_format' );

                switch ( $column ) {
                        case 'vev_start':
                                $start = (int) get_post_meta( $post_id, VEV_Events::META_START_UTC, true );
                                $all_day = (bool) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
                                if ( $start ) {
                                        if ( $all_day ) {
                                                echo esc_html( wp_date( $date_format, $start, $tz ) );
                                        } else {
                                                echo esc_html( wp_date( $date_format . ' ' . $time_format, $start, $tz ) );
                                        }
                                } else {
                                        echo '—';
                                }
                                break;

                        case 'vev_end':
                                $end = (int) get_post_meta( $post_id, VEV_Events::META_END_UTC, true );
                                $all_day = (bool) get_post_meta( $post_id, VEV_Events::META_ALL_DAY, true );
                                if ( $end ) {
                                        if ( $all_day ) {
                                                echo esc_html( wp_date( $date_format, $end, $tz ) );
                                        } else {
                                                echo esc_html( wp_date( $date_format . ' ' . $time_format, $end, $tz ) );
                                        }
                                } else {
                                        echo '—';
                                }
                                break;

                        case VEV_Events::TAX_CATEGORY:
                        case VEV_Events::TAX_LOCATION:
                        case VEV_Events::TAX_SERIES:
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

        public static function display_post_states( array $post_states, \WP_Post $post ): array {
                if ( VEV_Events::POST_TYPE !== $post->post_type ) {
                        return $post_states;
                }

                $data = VEV_Frontend::get_event_data( $post->ID );
                $raw_status = VEV_Frontend::get_event_status( $data['start_utc'], $data['end_utc'] );
                if ( 'ongoing' === $raw_status ) {
                        $post_states['ve_ongoing'] = __( 'Ongoing', VEV_Events::TEXTDOMAIN );
                } elseif ( 'past' === $raw_status || 'archived' === $raw_status ) {
                        $post_states['ve_past'] = __( 'Past', VEV_Events::TEXTDOMAIN );
                }

                return $post_states;
        }
}
